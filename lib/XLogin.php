<?php
/**
 * External login for WordPress.
 * @copyright Copyright (c) 2019,2020 Patrick Lai
 */

namespace PL2010\WordPress;

use PL2010\WordPress\OAuth2\YahooProvider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\Facebook;
use League\OAuth2\Client\Provider\Google;

use WP_Error;
use WP_Session_Tokens;
use WP_User;

use Throwable;

/**
 * External login service for a WordPress plugin.
 *
 * Instead of using WordPress password to login, a user may authenticate
 * to an external service (e.g. Yahoo), and mapped back to a WordPress
 * user. The flow of external login is typically as follows:
 *
 *  1) PHP code calls {@link getStartUrl()} for an URL to start external
 *     login webflow. This start link is rendered on the login page
 *     'wp-login.php'.
 *
 *  2) User clicks the start link, which causes the plugin to call
 *     {@link launchLoginFlow()} for a URL to redirect the client
 *     (i.e. the browser) to the external service that provides the
 *     authentication.
 *
 *  3) The external service is expected to send authentication result
 *     (be it success or failure) back to the plugin. That typically
 *     is done with a browser redirect. The plugin receives the result
 *     (with 'recv.php') and calls {@link recvLoginCredential()}. For
 *     successful authentication, the external user is mapped to a
 *     WordPress user, and the user ID is stashed in the webflow session.
 *     The client is then redirected back to the login page.
 *
 *  4) The login page detects that authentication has already been
 *     complete, and submit the login request immediately. The plugin
 *     has set up authentication filter to extract information of
 *     the externally authenticated user from the webflow session.
 *
 * The flow is modelled after the authorization code grant flow of
 * OAuth 2. For example, authentication failure is communicated in
 * step 3 as 'error' and 'error_description' parameters. This service
 * thus works best with OAuth 2 authorization service; this version
 * supports Facebook, Google, and Yahoo. The OAuth 2.0 Client of the
 * PHP League and its official Facebook and Google providers are
 * used.
 *
 * The mapping of external user identity to WordPress user is by
 * profile matching -- specifically email address in this version.
 * Additional mapping outside of WordPress user profile is also
 * supported. A WordPress user may register multiple 'external
 * aliases' (email addresses). It is thus possible to allow multiple
 * users to share the same WordPress account without shared knowledge
 * of the WordPress password. A 'guest' user may be configured as
 * wildcard for no-match situation.
 *
 * For privacy consideration, external aliases are stored in the
 * database as hashes. The hash includes WordPress AUTH_SALT, and
 * so is WordPress installation specific. Partial external aliases
 * are optionally saved to support search. OAuth2 client secrets
 * are also encrypted in the database using AUTH_KEY for key.
 */
class XLogin /*{{{*/
{
	/** Version number. */
	const VERSION = '1.1.2';

	/** @var string Default Facebook Graph API version to request. */
	public static $FACEBOOK_GRAPH_API_VERS = 'v3.3';

	/** @var array Capabilities that a guest user must not have. */
	public static $GUEST_USER_FORBIDDEN_CAPS = [
		// TODO: configurable via settings
		'edit_posts',           // c.f. anyone above 'subscriber'
		'delete_posts',         // c.f. 'contributor' or above
		'publish_posts',        // c.f. 'author' or above
		'edit_pages',           // c.f. 'editor' or above
		'manage_options',       // c.f. 'administrator' or above
	];

	/** @var array Capabilities to disable for guest user. */
	public static $GUEST_USER_DISABLED_CAPS = [
		// TODO: configurable via settings
		'read',                 // access to dashboard and profile
		'edit_user',            // profile update (via REST API)
	];

	/** @var array External login service instances by name. */
	private static $INSTANCES;

	/** @var string Name of the service. */
	private $name;

	/** @var string Base URL of the plugin directory; ends with '/'. */
	private $url_base;

	/** @var callback Debug logger taking an message. */
	private $dbg_logger;

	/** @var array Last error label. */
	private $err_last;

	/** @var array Settings/options of the login service. */
	private $options;

	/** @var array External user info by WP user ID. */
	private $xu_cache;

	/** @var callback Webflow attribute getter, see {@link configWebflow()}. */
	private $wf_get;

	/** @var callback Weblfow attribute setter, see {@link configWebflow()}. */
	private $wf_set;

	/** @var boolean PHP session init flag. */
	private $sess_init = false;

	/**
	 * Construct external login service.
	 * @param string $name WordPress plugin name.
	 * @param string $baseUrl URL for the plugin directory.
	 */
	protected function __construct($name, $baseUrl) /*{{{*/
	{
		$this->name = $name;
		$this->url_base = $baseUrl;
	} /*}}}*/

	/**
	 * Provide callback for accessing webflow attributes.
	 *
	 * By default, PHP session mechanism (session_start(), $_SESSION, etc.)
	 * is used to keep information across webflow requests. This allows
	 * override: $getter returns the value of an attribute given its name;
	 * $setter takes attribute name and value to save, and returning true/false
	 * to indicate success/failure.
	 *
	 * Alternatively one may define a subclass and override methods
	 * {@link flowAttrGet()} and {@link flowAttrSet()}.
	 *
	 * @param callback $getter Callback to retrieve attribute.
	 * @param callback $setter Callback to update attribute.
	 */
	public function configWebflow($getter, $setter) /*{{{*/
	{
		$this->wf_get = $getter;
		$this->wf_set = $setter;
	} /*}}}*/

	/**
	 * Decrypt data.
	 * The $salt and $secret values must match those for {@link encrypt()}.
	 * @param string $data Encrypted data from {@link encrypt()}.
	 * @param string $salt Salt for generating initialization vector.
	 * @param string $key Encryption key.
	 * @return string Decrypted data, or null if error.
	 */
	protected static function decrypt($data, $salt, $key=null) /*{{{*/
	{
		$alg = 'AES-128-CBC';

		$key = $key != '' ? $key : AUTH_KEY;

		$iv = sha1($salt, true);
		$ivlen = openssl_cipher_iv_length($alg);
		$ivdiff = strlen($iv) - $ivlen;
		if ($ivdiff > 0)
			$iv = substr($iv, 0, $ivlen);
		else
			$iv = str_pad($iv, $ivlen);

		$result = openssl_decrypt($data, $alg, $key, $options=0, $iv);
		return $result !== false ? $result : null;
	} /*}}}*/

	/**
	 * Decrypt sensitive information in an auth config.
	 * @param string $type External login type.
	 * @param array $config Auth configuration.
	 * @return array Config with sensitive information decrypted.
	 */
	protected function decryptAuthConfig($type, $config) /*{{{*/
	{
		switch ($this->getLoginModel($type)) {
		case 'oauth2':
			// Decrypt client secret if it is in (salt, encrypted) form.
			$clientSecret = $config['client_secret'] ?? null;
			if (!$clientSecret || !is_array($clientSecret))
				return $config;
			if (count($clientSecret) < 2)
				return $config;
			list($salt, $encrypted) = $clientSecret;
			$salt = base64_decode($salt);
			$clientSecret = static::decrypt($encrypted, $salt);
			if ($clientSecret === null)
				return $config;
			$config['client_secret'] = $clientSecret;
			return $config;
		default:
			return $config;
		}
	} /*}}}*/

	/**
	 * Encrypt data.
	 *
	 * This performs AES-128-CBD encryption. The encryption key is
	 * by default AUTH_KEY.
	 *
	 * @param string $data Data to encrypt.
	 * @param string $salt Salt for generating initialization vector.
	 * @param string $key Encryption key.
	 * @return string Encrypted data, base64 encoded; null if error.
	 */
	protected static function encrypt($data, $salt, $key=null) /*{{{*/
	{
		$alg = 'AES-128-CBC';

		$key = $key != '' ? $key : AUTH_KEY;

		$iv = sha1($salt, true);
		$ivlen = openssl_cipher_iv_length($alg);
		$ivdiff = strlen($iv) - $ivlen;
		if ($ivdiff > 0)
			$iv = substr($iv, 0, $ivlen);
		else
			$iv = str_pad($iv, $ivlen);

		$result = openssl_encrypt($data, $alg, $key, $options=0, $iv);
		return $result !== false ? $result : null;
	} /*}}}*/

	/**
	 * Encrypt sensitive information in an auth config.
	 * @param string $type External login type.
	 * @param array $config Auth configuration.
	 * @return array Config with sensitive information encrypted.
	 */
	protected function encryptAuthConfig($type, $config) /*{{{*/
	{
		switch ($this->getLoginModel($type)) {
		case 'oauth2':
			// Encrypt client secret, replacing it with a tuple
			// of (salt, encrypted).
			$clientSecret = $config['client_secret'] ?? null;
			if ($clientSecret == '' || is_array($clientSecret))
				return $config;
			$salt = openssl_random_pseudo_bytes(16);
			$encrypted = static::encrypt($clientSecret, $salt);
			if ($encrypted === null)
				return $config;
			$config['client_secret'] = [
				base64_encode($salt),
				$encrypted,
			];
			return $config;
		default:
			return $config;
		}
	} /*}}}*/

	/**
	 * Get the value of a webflow attribute.
	 *
	 * @param string $n Attribute name, unscoped.
	 * @return mixed $v Attribute value.
	 */
	protected function flowAttrGet($n) /*{{{*/
	{
		$n = $this->getScopedAttrName($n);
		if ($this->wf_get)
			return call_user_func($this->wf_get, $n);

		if (!$this->sess_init) {
			if (!session_start()) {
				$this->setError('session-start-error');
				return null;
			}
			$this->sess_init = true;
			$this->logDebug("PHP session ...".substr(session_id(), -4));
		}
		return $_SESSION[$n] ?? null;
	} /*}}}*/

	/**
	 * Set the value of a webflow attribute.
	 *
	 * @param string $n Attribute name, unscoped.
	 * @param mixed $v Attribute value; null to unset the attribute.
	 * @return boolean True if set successful.
	 */
	protected function flowAttrSet($n, $v) /*{{{*/
	{
		$n = $this->getScopedAttrName($n);
		if ($this->wf_set) {
			if (call_user_func($this->wf_set, $n, $v))
				return true;
			$this->setError('session-error');
			return false;
		}

		if (!$this->sess_init) {
			if (!session_start()) {
				$this->setError('session-start-error');
				return false;
			}
			$this->sess_init = true;
			$this->logDebug("PHP session ...".substr(session_id(), -4));
		}
		if ($v !== null)
			$_SESSION[$n] = $v;
		else {
			if (isset($_SESSION[$n]))
				unset($_SESSION[$n]);
		}
		return true;
	} /*}}}*/

	/**
	 * Get webflow error.
	 * 
	 * @param boolean $clear Clear error afterwards.
	 * @return array Tuple of error label and text.
	 */
	public function flowErrorGet($clear=false) /*{{{*/
	{
		if ($error = $this->flowAttrGet('flow-error')) {
			if ($clear)
				$this->flowAttrSet('flow-error', null);
			return $error;
		}
		return [ null, null ];
	} /*}}}*/

	/**
	 * Receive error from webflow.
	 * 
	 * @param string $error Error code/label from external login service.
	 * @param string $etext Error text message from external login service.
	 * @return array Tuple of XLogin error code and message.
	 */
	public function flowErrorRecv($error, $etext) /*{{{*/
	{
		return [
			$error,
			$error == '' ? $etext : null
		];
	} /*}}}*/

	/**
	 * Set webflow error.
	 * Error information is saved to webflow session.
	 * 
	 * @param string $error Error code/label; null to clear error.
	 * @param string $etext Error text message.
	 */
	public function flowErrorSet($error, $etext) /*{{{*/
	{
		$this->flowAttrSet('flow-error', [
			$error,
			$etext,
		]);
	} /*}}}*/

	/**
	 * Get configuration of an authentication type.
	 * @param string $type Authentication type, e.g. 'google'.
	 * @param boolean $enabled If set, the auth type must be enabled.
	 * @return array Configuration array; null if not found.
	 */
	public function getAuthConfig($type, $enabled=true) /*{{{*/
	{
		$provider = $this->getProviderConfig($type, $enabled);
		if (!$provider)
			return null;
		$config = $provider['config'] ?? null;

		return $config;
	} /*}}}*/

	/**
	 * Get friendly name of an auth type.
	 * @param string $type Type name, e.g. 'google'.
	 * @param string $txtdom Text domain to localize auth type name.
	 * @return array Login type to description/name.
	 */
	public function getAuthTypeName($type, $txtdom='pl2010') /*{{{*/
	{
		switch ($type) {
		case 'facebook':
		case 'google':
		default:
		//	$desc = __('Sign in with '.ucwords($type), $txtdom);
			$desc = __(ucwords($type), $txtdom);
			break;
		case 'yahoo':
			$desc = __('Yahoo!', $txtdom);
			break;
		}
		return $desc;
	} /*}}}*/

	/**
	 * Get list of all supported login/auth types.
	 * @return array List of auth types.
	 */
	public function getAuthTypes() /*{{{*/
	{
		return [
			'facebook',
			'google',
			'yahoo',
		];
	} /*}}}*/

	/**
	 * Get list of login/auth types activated.
	 * @param string $txtdom Text domain to localize auth type name.
	 * @return array Login type to description/name.
	 */
	public function getAuthTypesActivated($txtdom='pl2010') /*{{{*/
	{
		$activated = [];
		foreach ($this->getAuthTypesEnabled() as $type) {
			if ($this->getAuthConfig($type)) {
				$activated[$type] = $this->getAuthTypeName($type, $txtdom);
			}
		}
		return $activated;
	} /*}}}*/

	/**
	 * Get list of login/auth types enabled.
	 * @return array List of login types.
	 */
	public function getAuthTypesEnabled() /*{{{*/
	{
		$options = $this->getOptions();

		$enabled = [];
		foreach ($this->getAuthTypes() as $type) {
			if ($options['providers'][$type]['enabled'] ?? false)
				$enabled[] = $type;
		}
		return $enabled;
	} /*}}}*/

	/**
	 * Get user authenticated already from external login.
	 *
	 * A previous login flow would have saved external user information
	 * in the webflow. This checks for it, and if successful, remembers
	 * it in {@link xu_cache}.
	 *
	 * If $clear is set, external user information will be cleared from
	 * the webflow.
	 *
	 * @param string $type Authentication type, e.g. 'google'.
	 * @param string $name External auth user name if known.
	 * @param boolean $clear Clear external auth from session.
	 * @param boolean &$guest Tell if authenticated user is a guest.
	 * @return WP_User|WP_Error|NULL Null if no external user.
	 */
	public function getAuthenticated(
		$type,
		$name=null,
		$clear=false,
		&$guest=false
	) /*{{{*/
	{
		$this->logDebug("getAuthenticated(): type=$type name=$name");
		$guest = null;

		if (!($xu = $this->flowAttrGet("$type-xuser")))
			return null;
		assert($xu['xtype'] == $type);

		if ($clear)
			$this->flowAttrSet("$type-xuser", null);

		$user = null;
		switch ($type) {
		case 'facebook':
		case 'google':
		case 'yahoo':
			$email = $xu['email'] ?? null;
			if ($name != '' && strcasecmp($email, $name) != 0) {
				return new WP_Error(
					'auth-fail',
					ucwords($type).' username and email mismatch'
				);
			}

			$user = get_user_by('id', $xu['id']);
			if (!$user)
				return new WP_Error('auth-fail', 'Invalid user ID');
			break;
		case '':
			return null;
		default:
			return new WP_Error('input-invalid', "invalid xlogin auth: $type");
		}

		if ($user) {
			assert($user instanceof WP_User);
			assert($xu['id'] == $user->ID);
			$this->xu_cache[$user->ID] = $xu;
			$guest = $xu['guest'] ?? false;
		}
		return $user;
	} /*}}}*/

	/**
	 * Get callback name from request URI.
	 * @param string $uri Request URI.
	 * @param string &$type Login type extracted from URI.
	 * @return string Callback name, or null.
	 */
	public function getCallbackByUri($uri, &$type=null) /*{{{*/
	{
		$type = null;

		$reqpath = parse_url($uri, PHP_URL_PATH);

		$prefix = $this->getCallbackPathPrefix();
		$pfxlen = strlen($prefix);
		if (substr($reqpath, 0, $pfxlen) != $prefix)
			return null;

		$suffix = substr($reqpath, $pfxlen);
		$pieces = explode('/', $suffix, 3);
		$callbk = reset($pieces);
		switch ($callbk) {
		case 'recv':
		case 'start':
			if (count($pieces) > 1)
				$type = $pieces[1];
			return $callbk;
		default:
			return null;
		}
	} /*}}}*/

	/**
	 * Get URI path prefix for callback.
	 * The path prefix is common to all callback URIs; it starts and ends '/'.
	 * @return string Callback URI path, or common prefix if $cb not given.
	 */
	protected function getCallbackPathPrefix() /*{{{*/
	{
		return parse_url($this->url_base, PHP_URL_PATH)
			. $this->getCallbackPathRelative();
	} /*}}}*/

	/**
	 * Get callback URI path relative to plugin base.
	 * The relative path does not start with '/'. If $cb is empty, this
	 * yields a prefix ending with '/'.
	 * @param string $cb Callback name, e.g. 'recv'.
	 * @param string $type Login type, e.g. 'google'.
	 * @return string Callback relative URI path.
	 */
	protected function getCallbackPathRelative($cb=null, $type=null) /*{{{*/
	{
		$path = 'callback/';
		if ($cb != '') {
			$path .= $cb;
			if ($type != '')
				$path .= "/$type";
		}
		return $path;
	} /*}}}*/

	/**
	 * Get URI for callback.
	 * Callback paths are specific to callback name and external login type.
	 * @param string $cb Callback name, e.g. 'recv'.
	 * @param string $type Login type, e.g. 'google'.
	 * @return string Callback URI, or common prefix if $cb not given.
	 */
	public function getCallbackUri($cb, $type) /*{{{*/
	{
		// Allow OAuth2 redirect_uri to override 'recv'.
		if  ($cb == 'recv' && $this->getLoginModel($type) == 'oauth2') {
			$config = $this->getAuthConfig($type);
			$redir = $config['redirect_uri'] ?? null;
			if ($redir != '')
				return $redir;
		}

		$uri = $this->url_base.$this->getCallbackPathRelative($cb, $type);
		return $uri;
	} /*}}}*/

	/**
	 * Get configuration items for customization.
	 * @return array Customization config options.
	 */
	public function getCustomization() /*{{{*/
	{
		$options = $this->getOptions();
		return $options['customize'] ?? null;
	} /*}}}*/

	/**
	 * Get default options.
	 * @return array Default options.
	 */
	public function getDefaultOptions() /*{{{*/
	{
		return $this->sanitizeOptions([]);
	} /*}}}*/

	/**
	 * Get external login service instance.
	 *
	 * If no $file is provided, the single unambiguous instance (if
	 * there is such), is returned.
	 *
	 * The instance for a plugin corresponds to an instance named
	 * with the plugin directory name.
	 *
	 * @param string $file Path to plugin file.
	 * @return \PL2010\WordPress\XLogin
	 */
	public static function getInstance($file=null) /*{{{*/
	{
		if ($file == '')
       		return self::$INSTANCES && count(self::$INSTANCES) == 1
				? reset(self::$INSTANCES)
				: null;

		$name = dirname(plugin_basename($file));

		$inst = self::$INSTANCES[$name] ?? null;
		if (!$inst) {
			$baseUrl = plugin_dir_url($file);
			$inst = self::$INSTANCES[$name] = new static($name, $baseUrl);

			if (defined('PL2010_XLOGIN_DEBUG')) {
				if ($debug = PL2010_XLOGIN_DEBUG) {
					if (is_callable($debug))
						$inst->setDebugLogger($debug);
					else {
						$tag = "PL2010 {$inst->getName()}";
						$inst->setDebugLogger(function($msg) use($tag) {
							error_log("$tag: $msg");
						});
					}
				}
			}
		}
		return $inst;
	} /*}}}*/

	/**
	 * Get named external login service instance.
	 *
	 * @param string $name Name of the instance.
	 * @return \PL2010\WordPress\XLogin Null if not available.
	 */
	public static function getInstanceByName($name) /*{{{*/
	{
		if ($name == '')
			return null;
		return self::$INSTANCES[$name] ?? null;
	} /*}}}*/

	/**
	 * Get error object describing last DB error.
	 * @return WP_Error
	 */
	protected static function getLastDbError() /*{{{*/
	{
		global $wpdb;

		// TODO: beware of db error message bubbling up
		return new WP_Error('db-error', $wpdb->last_error);
	} /*}}}*/

	/**
	 * Retrieve last error.
	 * @param boolean $clear Clear error afterwards.
	 * @param string $txtdom Text domain to localize error message.
	 * @return array Tuple of error label and text.
	 */
	public function getLastError($clear=false, $txtdom='pl2010') /*{{{*/
	{
		if (!$this->err_last)
			return [ null, null ];

		$last = $this->err_last;
		if ($clear)
			$this->err_last = null;

		if ($txtdom == '')
			return $last;
		return [
			$last[0],
			__($last[1], $txtdom),
		];
	} /*}}}*/

	/**
	 * Get expected credential fields for an external login type.
	 * @param string $type Login type, e.g. 'google'.
	 * @return array Field name to field type (boolean, integer, string).
	 */
	public function getLoginCredentFields($type) { /*{{{*/
		switch ($this->getLoginModel($type)) {
		case 'oauth2':
			// These are OAuth2 response items.
			return [
				'code' => 'string',
				'error' => 'string',
				'error_description' => 'string',
				'scope' => 'string', // Google does this
				'state' => 'string',
			];
		default:
			return [];
		}
	} /*}}}*/

	/**
	 * Get login model.
	 * @param string $type Login type, e.g. 'google'.
	 * @return string Login model, e.g. 'oauth2' for OAuth2.
	 */
	public function getLoginModel($type) /*{{{*/
	{
		switch ($type) {
		case 'facebook':
		case 'google':
		case 'yahoo':
			return 'oauth2';
		default:
			return 'generic';
		}
	} /*}}}*/

	/**
	 * Get name of this service.
	 * @return string
	 */
	public function getName() /*{{{*/
	{
		return $this->name;
	} /*}}}*/

	/**
	 * Get sanitized name of this service.
	 * The sanitized name has '-' replaced by '_', etc.
	 * @return string
	 */
	public function getNameSanitized() /*{{{*/
	{
		return str_replace('-', '_', $this->getName());
	} /*}}}*/

	/**
	 * Get OAuth2 provider.
	 * @param array $config Auth config of the provider.
	 * @param string $type Type of external login, e.g. 'google'.
	 * @return \League\OAuth2\Client\Provider\AbstractProvider
	 */
	protected function getOAuth2Provider($config, $type) /*{{{*/
	{
		assert(!empty($config));

		$options = [
			'clientId' => $config['client_id'] ?? null,
			'clientSecret' => $config['client_secret'] ?? null,
			'redirectUri' => $this->getRedirectUrl($type),
		];

		switch ($type) {
		case 'facebook':
			$cust = $this->getCustomization();
			$options['graphApiVersion'] =
				$cust['facebook_graph_api'] ?? static::$FACEBOOK_GRAPH_API_VERS;
			$provider = new Facebook($options);
			break;
		case 'google':
			$provider = new Google($options);
			break;
		case 'yahoo':
			$provider = new YahooProvider($options);
			break;
		default:
			// This should not happen.
			$this->setError('server_error', 'Unknown OAuth2 type.');
			return null;
		}

		return $provider;
	} /*}}}*/

	/**
	 * Obtain OAuth2 user information.
	 * @param \League\OAuth2\Client\Provider\AbstractProvider $provider
	 * @param string $token Access token.
	 * @return array External user info; null if unavailable or error.
	 */
	protected function getOAuth2UserInfo($provider, $token) /*{{{*/
	{
		try {
			$user = $provider->getResourceOwner($token);
		}
		catch (IdentityProviderException $ex) {
			$this->logDebug("OAuth2 exception: $ex");
			$user = null;
		}
		if (!$user) {
			$this->setError('invalid_grant', 'Failed to obtain user info.');
			return null;
		}

		if ($provider instanceof Facebook
			|| $provider instanceof Google
			|| $provider instanceof YahooProvider
		) {
			$email = $user->getEmail();
			if ($email != '') {
				$email = static::sanitizeXUserAlias('email', $email);
				if ($email instanceof WP_Error) {
					$this->setError(
						'server_error',
						'Invalid email from external login service.'
					);
					return null;
				}
			}
			return [
				'name' => sanitize_text_field($this->getUserDisplayName(
					$user->getLastName(),
					$user->getFirstName(),
					$user->getLocale()
				)),
				'email' => $email,
				'xhash' => static::getXUserHash('email', $email),
			];
		}

		// This should not happen.
		$this->setError('server_error', 'Unknown OAuth2 provider.');
		return null;
	} /*}}}*/

	/**
	 * Get settings/options of this service.
	 * @param boolean $cache Use cached value.
	 * @return array Null if not available.
	 */
	public function getOptions($cache=true) /*{{{*/
	{
		if (!$cache || $this->options === null)
			$this->options = get_option($this->getOptionsName(), null);
		return $this->options;
	} /*}}}*/

	/**
	 * Get WordPress option name, e.g. for get_option().
	 * @return string
	 */
	public function getOptionsName() /*{{{*/
	{
		return "{$this->getName()}_options";
	} /*}}}*/

	/**
	 * Get configuration of an authentication provider.
	 * @param string $type Authentication type, e.g. 'google'.
	 * @param boolean $enabled If set, the auth type must be enabled.
	 * @return array Configuration array; null if not found.
	 */
	public function getProviderConfig($type, $enabled=true) /*{{{*/
	{
		$options = $this->getOptions();
		$provider = $options['providers'][$type] ?? null;

		if ($enabled && !($provider['enabled'] ?? false))
			return null;

		// Get guest login name from ID.
		if (is_int($guest = $provider['guest'] ?? null)) {
			if ($guest = get_user_by('ID', $guest))
				$provider['guest'] = $guest->user_login;
			else
				unset($provider['guest']);
			$this->options['providers'][$type] = $provider;
		}

		// Decrypt auth config.
		if ($config = $provider['config'] ?? null) {
			if (!is_array($config))
				$config = json_decode($config, true);
			$provider['config'] = $this->decryptAuthConfig($type, $config);
			$this->options['providers'][$type] = $provider;
		}

		return $provider;
	} /*}}}*/

	/**
	 * Get redirect URL to receive login result.
	 * @param string $type Login type, e.g. 'google'.
	 * @return string
	 */
	protected function getRedirectUrl($type) /*{{{*/
	{
		return $this->getCallbackUri('recv', $type);
	} /*}}}*/

	/**
	 * Get namespaced attribute name to avoid conflict with other plugins, etc.
	 * @param string $name Attribute name.
	 * @return string Name of attribute, with a namespace prefix.
	 */
	protected function getScopedAttrName($name) /*{{{*/
	{
		return "{$this->getName()}.{$name}";
	} /*}}}*/

	/**
	 * Get URL to start login flow.
	 * @param string $type Login type, e.g. 'google'.
	 * @param string $redir Redirect URL for successful login.
	 * @return string Null if error.
	 */
	public function getStartUrl($type, $redir=null) /*{{{*/
	{
		if (!$this->flowAttrSet("$type.redir", $redir))
			return null;
		$url = $this->getCallbackUri('start', $type);
		return $url;
	} /*}}}*/

	/**
	 * Build display name of user.
	 * @param string $family Family name.
	 * @param string $given Given name.
	 * @param string $locale Locale name.
	 * @return string
	 */
	protected function getUserDisplayName($family, $given, $locale) /*{{{*/
	{
		if ($given == '' || $family == '')
			return $family.$given;

		// TODO: locale specific way to build display name; for now
		// assume multi-byte names follow <family><given> convention.
		if (strlen($family) == mb_strlen($family)
			&& strlen($given) == mb_strlen($given)
		) {
			// Name has single byte characters only, "<given> <family>".
			return $given.' '.$family;
		}
		else {
			// Name has multiple byte characters, "<family><given>".
			return $family.$given;
		}
	} /*}}}*/

	/**
	 * Get name of database table for external users.
	 * @return string
	 */
	protected function getTableXUsers() /*{{{*/
	{
		global $wpdb;

		$prefix = $wpdb->prefix.$this->getNameSanitized();
		return "{$prefix}_users";
	} /*}}}*/

	/**
	 * Get hash of external user alias.
	 * The hash is an obfuscated identify of the user. The input $type
	 * and $name are assumed to be valid, e.g. having passed
	 * {@link sanitizeXUserAlias()}.
	 * @param string $type Type of alias, e.g. 'email'.
	 * @param string $name External user alias.
	 * @param string &$obscure Obscured alias is returned if not empty.
	 * @return string Hash of alias.
	 */
	public static function getXUserHash($type, $name, &$obscure=null) /*{{{*/
	{
		if (!$obscure)
			$obscure = null;

		switch ($type) {
		case 'email':
			$name = strtolower($name);
			if ($obscure) {
				$pieces = explode('@', $name, 2);
				$obscure = static::maskOffMiddle($pieces[0])
					.'@'
					.($pieces[1]??'');
			}
			break;
		default:
			if ($obscure)
				$obscure = static::maskOffMiddle($name);
			break;
		}

		$salt = AUTH_SALT;
		$hash = hash('sha256', $type.'/'.urlencode($name).'/'.$salt, false);

		// URL-safe base64 encoding (c.f. RFC4648) without padding
		// (since pre-encoding hash is fixed length).
		$hash = str_replace([
			'+', '/', '=',
		], [
			'-', '_', '',
		], $hash);
		return $hash;
	} /*}}}*/

	/**
	 * Get external user information from session.
	 * @param int $uid WordPress User ID.
	 * @return array User information.
	 */
	protected function getXUserInfo($uid) /*{{{*/
	{
		if (!$uid)
			return null;

		$sessTokens = WP_Session_Tokens::get_instance($uid);
		$token = wp_get_session_token();
		$this->logDebug('getting user from WP session ...'.substr($token, -8));
		if ($token == '')
			return null;

		$sess = $sessTokens->get($token);
		if (!($xu = $sess[$this->getScopedAttrName('xuser')] ?? null))
			return null;

		if (($xu['id'] ?? null) != $uid)
			return null;

		return $xu;
	} /*}}}*/

	/**
	 * Import external user information for current WordPress request.
	 *
	 * @internal This updates various WordPress global variables to reflect
	 * external user information, and is thus sensitive to WordPress version.
	 *
	 * @param boolean &$guest Flag to indicate if imported user is guest.
	 * @return boolean True if import is done.
	 */
	public function importXUser(&$guest=false) /*{{{*/
	{
		global $current_user;
		global $userdata, $user_ID, $user_identity, $user_email;

		$guest = null;
		if (!($xu = $this->getXUserInfo($user_ID)))
			return false;

		$this->logDebug(function() use($userdata, $xu) {
			$udata = $userdata->data;
			return "override user info: "
				." userdata=".json_encode([
					'ID' => $udata->ID ?? null,
				//	'user_login' => $udata->user_login ?? null,
				//	'user_nicename' => $udata->user_nicename ?? null,
					'user_email' => $udata->user_email ?? null,
					'display_name' => $udata->display_name ?? null,
				])." xuser=".json_encode($xu);
		});

		if ($xu['name'] ?? null)
			$current_user->display_name
				= $userdata->display_name
				= $user_identity
				= $xu['name'].' ('.$this->getAuthTypeName($xu['xtype']).')';
		if ($xu['email'] ?? null)
			$current_user->user_email
				= $userdata->user_email
				= $user_email
				= $xu['email'];
		$guest = $xu['guest'] ?? false;

		// Tighten access control for guests.
		if ($guest) {
			// Disable capabilities for guest.
			add_filter('user_has_cap', function($allcaps, $caps, $args) {
				$disabled = static::$GUEST_USER_DISABLED_CAPS;
				if (!$disabled)
					return $allcaps;
				$wanted = $args[0];
				if ($allcaps[$wanted] ?? true && in_array($wanted, $disabled))
					$allcaps[$wanted] = false;
				return $allcaps;
			}, 10, 3);
		}

		return true;
	} /*}}}*/

	/**
	 * Mask off a middle section of a string.
	 * The string $data is divided into three sections: head, middle, and
	 * tail. The middle part is replaced by $mask. The head and the tail
	 * are trimmed to no more than $plain characters.
	 * @param string $data String to mask off.
	 * @param string $mask Mask string.
	 * @param int $plain Maximum # of plain characters in head or tail.
	 * @return string Middle section of $data replaced with $mask.
	 */
	protected static function maskOffMiddle($data,$mask='***',$plain=2) /*{{{*/
	{
		$dlen = strlen($data);
		$mlen = strlen($mask);
		if ($dlen <= $mlen)
			return $mask;

		$head = $tail = ($dlen - $mlen) / 2;
		$head = max(min($head, $plain), 1);
		$tail = max(min($tail, $plain), 1);

		return substr($data, 0, $head)
			. $mask
			. substr($data, -$tail);
	} /*}}}*/

	/**
	 * Launch external login flow.
	 *
	 * @param string $type Type of external login, e.g. 'google'.
	 * @param string $redir Landing URL for successful login.
	 * @return string URL to redirect client to; null if error.
	 */
	public function launchLoginFlow($type, $redir=null) /*{{{*/
	{
		$config = $this->getAuthConfig($type);
		if (!$config) {
			$this->setError('xlogin-not-configured');
			return null;
		}

		if ($redir == '')
			$redir = $this->flowAttrGet("$type.redir");

		switch ($this->getLoginModel($type)) {
		case 'oauth2':
			return $this->launchOAuth2($config, $type, $redir);
		default:
			$this->setError('unknown-xlogin-type');
			return null;
		}
	} /*}}}*/

	/**
	 * Launch OAuth2 authz flow.
	 *
	 * @param array $config Auth config of the provider.
	 * @param string $type Type of external login, e.g. 'google'.
	 * @param string $redir Landing URL for successful login.
	 * @return string URL to redirect client to; null if error.
	 */
	protected function launchOAuth2($config, $type, $redir=null) /*{{{*/
	{
		$provider = $this->getOAuth2Provider($config, $type);
		if (!$provider)
			return null;

		// Convert comma/space separated scopes to array.
		if ($scope = $config['scope'] ?? null) {
			if (!is_array($scope)) {
				$scope = trim($scope);
				if ($scope != '')
					$scope = preg_split('/[\s,]+/', $scope);
			}
		}

		$authzUrl = $provider->getAuthorizationUrl([
			'scope' => $scope,
		]);
		$state = $provider->getState();
		if (!$this->flowAttrSet("$type.oauth2-state", $state))
			return null;
		if (!$this->flowAttrSet("$type.oauth2-redir", $redir))
			return null;
		return $authzUrl;
	} /*}}}*/

	/**
	 * Log debugging message.
	 * @param string|callable $info Error message or callback to get it.
	 */
	public function logDebug($info) /*{{{*/
	{
		if (!$this->dbg_logger)
			return false;

		if (is_callable($info))
			$info = call_user_func($info);
		call_user_func($this->dbg_logger, $info);
	} /*}}}*/

	/**
	 * Check if a WP user is acceptable as guest via external login.
	 *
	 * A guest user is one authenticated by an external login service
	 * but with an alias not recognized, and should have minimal
	 * privilege. Generally want 'subscriber' level, but role is
	 * fully customizable in WP, so check for some capabilities.
	 *
	 * @param WP_User $user User to check.
	 * @param string &$emsg Error message if not acceptable.
	 * @return boolean True if user acceptable as guest.
	 */
	public function isAcceptableGuest($user, &$emsg=null) /*{{{*/
	{
		$emsg = null;

		if (!$user instanceof WP_User) {
			$emsg = 'Invalid user.';
			return false;
		}

		foreach (static::$GUEST_USER_FORBIDDEN_CAPS ?? [] as $forbid) {
			if ($user->has_cap($forbid)) {
				$emsg = 'User is too privileged to be guest login.';
				return false;
			}
		}

		return true;
	} /*}}}*/

	/**
	 * Receive credential from external login service.
	 * @param string $type Login type, e.g. 'google'.
	 * @param array $input Input data, e.g. $_GET.
	 * @return string Redirect to next step in the flow.
	 */
	public function recvLoginCredential($type, $input) /*{{{*/
	{
		$config = $this->getAuthConfig($type);
		if (!$config) {
			$this->setError('xlogin-not-configured');
			return null;
		}

		switch ($this->getLoginModel($type)) {
		case 'oauth2':
			$provider = $this->getOAuth2Provider($config, $type);
			if (!$provider)
				return null;
			$target = $this->recvOAuth2($provider, $type, $input);
			if ($target === null)
				return null;
			break;
		default:
			$this->setError('unknown-xlogin-type');
			return null;
		}

		return $target;
	} /*}}}*/

	/**
	 * Receive authz result (e.g. authz code) from OAuth2 server.
	 * @param \League\OAuth2\Client\Provider\AbstractProvider $provider
	 * @param string $type OAuth2 login type, e.g. 'google'.
	 * @param array $input Input data, e.g. $_GET.
	 * @return NULL|string Null if auth failure; landing URL otherwise.
	 */
	public function recvOAuth2($provider, $type, $input) /*{{{*/
	{
		$this->logDebug(function() use($type, $input) {
			if (isset($input['code']))
				$input['code'] = '***['.strlen($input['code']).']***';
			return "receiving OAuth2[$type] result: ".json_encode($input);
		});
		if (($code = $input['code'] ?? null) == '') {
			$this->setError('invalid_request', 'Missing code.');
			return null;
		}
		if (($state = $input['state'] ?? null) == '') {
			$this->setError('invalid_request', 'Missing state.');
			return null;
		}
		if ($state != $this->flowAttrGet("$type.oauth2-state")) {
			$this->setError('invalid_request', 'Invalid state.');
			return null;
		}
		$redir = $this->flowAttrGet("$type.oauth2-redir");

		try {
			$token = $provider->getAccessToken('authorization_code', [
				'code' => $code,
			]);
		}
		catch (IdentityProviderException $ex) {
			$this->logDebug("OAuth2 exception: $ex");
			$token = null;
		}
		if ($token == '') {
			$this->setError('invalid_grant', 'Failed to obtain token.');
			return null;
		}

		if (!($xu = $this->getOAuth2UserInfo($provider, $token)))
			return null;

		$config = $this->getProviderConfig($type);

		// Verify that OAuth2 user is recognized WP users.
		// Resolve user by email only if the provider is unrestricted.
		$email = $xu['email'] ?? null;
		if (!$email)
			return null;
		$user = ($config['restricted'] ?? false)
			? null
			: get_user_by('email', $email);
		if (!$user)
			$user = $this->resolveXUserByAlias('email', $email);

		// Attempt guest user if no specific one matched.
		$guest = null;
		if (!$user && !empty($config['guest'])) {
			if ($guest = get_user_by('login', $config['guest'])) {
				if ($this->isAcceptableGuest($guest))
					$user = $guest;
				else
					$guest = null;
			}
		}

		if (!$user) {
			$this->setError(
				'unknown-user',
				"{$this->getAuthTypeName($type)} user not recognized."
			);
			return null;
		}

		// Discard external profile info unless needed.
		if (!($config['override'] ?? false))
			$xu = [];

		$xu['id'] = $user->ID;
		$xu['xtype'] = $type;
		if ($guest)
			$xu['guest'] = true;
		if (!$this->flowAttrSet("$type-xuser", $xu))
			return null;

		$this->logDebug("OAuth2 success: $redir");
		return (string)$redir;
	} /*}}}*/

	/**
	 * Add external alias registration.
	 * @param string $type External alias type.
	 * @param string $name External alias value.
	 * @param WP_User $user User to login in.
	 * @param boolean $replace Replace conflicting registration.
	 * @param boolean $obscure Whether to include obscured alias.
	 * @return array|WP_Error Registration data or error.
	 */
	public function registrationAdd(
		$type,
		$name,
		$user,
		$replace=false,
		$obscure=true
	) /*{{{*/
	{
		global $wpdb;
		$usersTable = $this->getTableXUsers();

		$name = static::sanitizeXUserAlias($type, $name);
		if ($name instanceof WP_Error)
			return $name;

		$xhash = static::getXUserHash($type, $name, $obscure);

		// Insert/replace registration.
		$op = $replace ? 'replace' : 'insert';
		if ($obscure) {
			$result = $wpdb->$op($usersTable, [
				'uid' => $user->ID,
				'obscure' => $obscure,
				'xhash' => $xhash,
			], [
				'%d',
				'%s',
				'%s',
			]);
		}
		else {
			$result = $wpdb->$op($usersTable, [
				'uid' => $user->ID,
				'obscure' => null,
				'xhash' => $xhash,
			], [
				'%d',
				null,
				'%s',
			]);
		}
		if ($result === false)
			return static::getLastDbError();

		return [
			'id' => $wpdb->insert_id,
			'uid' => $user->ID,
			'login' => $user->user_login,
			'obscure' => $obscure,
			'xhash' => $xhash,
		];
	} /*}}}*/

	/**
	 * Delete an external alias registration.
	 * @param int $id Registration ID.
	 * @return WP_Error|boolean True if anything deleted.
	 */
	public function registrationDelete($id) /*{{{*/
	{
		global $wpdb;
		$usersTable = $this->getTableXUsers();

		$result = $wpdb->delete($usersTable, [
			'id' => $id,
		], '%d');
		if ($result === false)
			return static::getLastDbError();
		return $result > 0;
	} /*}}}*/

	/**
	 * Retrieve an external alias registration.
	 * If there are multiple matches, the first one is returned.
	 * @param string $field Field name, one of 'id', 'uid', or 'xhash'.
	 * @param mixed $value Field value.
	 * @return array|WP_Error Registration data, null, or error.
	 */
	public function registrationGetBy($field, $value) /*{{{*/
	{
		global $wpdb;
		$usersTable = $this->getTableXUsers();

		switch ($field) {
		case 'id':
		case 'uid':
			$fmt = '%d';
			break;
		case 'obscure':
		case 'xhash':
			$fmt = '%s';
			break;
		default:
			return new WP_Error('input-invalid', "Unknown field '$field'");
		}

		$sql = $wpdb->prepare("SELECT * FROM $usersTable WHERE $field=$fmt", [
			$value,
		]);
		assert($sql != '');

		$reg = $wpdb->get_row($sql);
		if (!$reg)
			return null;

		$user = get_user_by('ID', $reg->uid);
		return [
			'id' => $reg->id,
			'uid' => $reg->uid,
			'login' => $user ? $user->user_login : "#{$reg->uid}",
			'obscure' => $reg->obscure,
			'xhash' => $reg->xhash,
		];
	} /*}}}*/

	/**
	 * Get list of external alias registrations.
	 * If $conds is specified, only matcing registrations are included.
	 * @param array $conds Tuples of property name, comparision op, and value.
	 * @param int $off Offset for pagination.
	 * @param int $max Limit for pagination.
	 * @param int &$ttl Return total # of registrations here if set.
	 * @return array List of matching registrations.
	 */
	public function registrationList(
		$conds=null,
		$off=0,
		$max=10,
		&$ttl=null
	) /*{{{*/
	{
		global $wpdb;
		$table = $this->getTableXUsers();

		if ($ttl !== null)
			$ttl = 0;

		$clauses = [];
		$params = [];
		foreach ($conds ?? [] as $tuple) {
			list($pn, $op, $pv) = $tuple;
			switch ($pn) {
			case 'uid':
				$clauses[] = "uid $op %d";
				$params[] = $pv;
				break;
			case 'obscure':
				$clauses[] = "obscure $op %s";
				$params[] = $pv;
				break;
			default:
				return [];
			}
		}
		$where = $clauses ? ' WHERE '.implode(' AND ', $clauses) : '';
		$limit = "LIMIT $off,$max";

		$count = null;
		if ($ttl !== null) {
			$sql = $wpdb->prepare("SELECT COUNT(*) FROM $table$where", $params);
			if ($sql == '')
				return [];
			$count = $wpdb->get_var($sql);
			if ($count === null)
				return [];
			$count = (int)$count;
			if ($count == 0) {
				$ttl = 0;
				return [];
			}
		}

		$sql = $wpdb->prepare("SELECT * FROM $table$where $limit", $params);
		if ($sql == '')
			return [];

		$rlist = $wpdb->get_results($sql);
		if (!$rlist)
			return [];

		$ttl = $count;
		return array_map(function($reg) {
			$user = get_user_by('ID', $reg->uid);
			return [
				'id' => $reg->id,
				'uid' => $reg->uid,
				'login' => $user ? $user->user_login : "#{$reg->uid}",
				'obscure' => $reg->obscure,
				'xhash' => $reg->xhash,
			];
		}, $rlist);
	} /*}}}*/

	/**
	 * Wipe all external alias registrations.
	 * @return WP_Error|int Number of registrations deleted.
	 */
	public function registrationWipe() /*{{{*/
	{
		global $wpdb;
		$usersTable = $this->getTableXUsers();

		$result = $wpdb->query("DELETE FROM $usersTable", []);
		if ($result === false)
			return static::getLastDbError();
		return $result;
	} /*}}}*/

	/**
	 * Look up a user by his external alias.
	 * Note that a user may have multiple external aliases.
	 * @param string $type Type of name, e.g. 'email'.
	 * @param string $name External user name, e.g. Google email address.
	 * @return WP_User Null if not found.
	 */
	protected function resolveXUserByAlias($type, $name) /*{{{*/
	{
		global $wpdb;
		$usersTable = $this->getTableXUsers();

		$name = static::sanitizeXUserAlias($type, $name);
		if ($name instanceof WP_Error)
			return null;

		$xhash = static::getXUserHash($type, $name);
		$sql = $wpdb->prepare("SELECT * FROM $usersTable WHERE xhash=%s", [
			$xhash,
		]);
		assert($sql != '');

		$xuser = $wpdb->get_row($sql);
		if (!$xuser)
			return null;

		$user = get_user_by('id', $xuser->uid);
		return $user ? $user : null;
	} /*}}}*/

	/**
	 * Sanitize user external alias.
	 * @param string $type Alias type, e.g. 'email'.
	 * @param array $name Alias name, e.g. an email address.
	 * @return string|WP_Error Sanitized $name, or error.
	 */
	public static function sanitizeXUserAlias($type, $name) /*{{{*/
	{
		switch ($type) {
		case 'email':
			$name = sanitize_email($name);
			if ($name == '')
				return new WP_Error('input-invalid', 'Invalid email address.');
			return $name;
		default:
			return new WP_Error('input-invalid', 'Unknown user alias type.');
		}
	} /*}}}*/

	/**
	 * Set error code and description for {@link getLastError()}.
	 * @param string $error Error label.
	 * @param string $etext Error description.
	 */
	protected function setError($error, $etext=null) /*{{{*/
	{
		$this->err_last = [$error, $etext];
	} /*}}}*/

	/**
	 * Save to WordPress session information of externally authenticated user.
	 *
	 * A previous call to {@link getAuthenticated()} in the same request
	 * has established an externally authenticated user. This saves
	 * information about that user to the WordPress session.
	 *
	 * @param string $token Token for logged-in cookie.
	 * @param int $uid WP user ID.
	 */
	public function setXUserToSession($token, $uid) /*{{{*/
	{
		$xu = $this->xu_cache[$uid] ?? null;
		if ($xu && $xu['id'] == $uid)
			$this->setXUserInfo($uid, $xu, $token);
	} /*}}}*/

	/**
	 * Sanitize options.
	 * This is intended to be used as 'sanitize_callback' for
	 * WordPress register_setting().
	 *
	 * Sensitive data (e.g. OAuth2 client secret) is encrypted
	 * by this method.
	 *
	 * @todo Report errors via add_settings_error()?
	 * @param array $input Input options.
	 * @return array Sanitized options.
	 */
	public function sanitizeOptions($input) /*{{{*/
	{
		$data = [
			'vers' => static::VERSION,
			'code' => static::class,
		];

		// Customization config items.
		if (is_array($input['customize']??null)) {
			foreach ($input['customize'] as $key => $val) {
				switch ($key) {
				case 'login_buttons_info':
					try {
						$val = strval($val);
						$data['customize'][$key] = sanitize_text_field($val);
					}
					catch (Throwable $err) {
						$this->logDebug("invalid customization '$key' value");
					}
					break;
				case 'facebook_graph_api':
					try {
						$val = trim(strval($val));
						if ($val == '')
							break;
						if (!preg_match('%^v[1-9][0-9]*\.[0-9]$%', $val))
							throw new WP_Error(
								'input-invalid',
								"Invalid Facebook Graph API version '$val'."
							);
						$data['customize'][$key] = $val;
					}
					catch (Throwable $err) {
						$this->logDebug("invalid customization '$key' value");
					}
					break;
				default:
					$this->logDebug("unknown customization '$key'");
					break;
				}
			}
		}

		// Login/auth provider configuration.
		if (is_array($input['providers']??null)) {
			foreach ($this->getAuthTypes() as $type) {
				if ($provider = $input['providers'][$type] ?? null) {
					if (!is_array($provider))
						continue;

					$slot =& $data['providers'][$type];
					if ($provider['enabled'] ?? false) {
						$slot['enabled'] = true;
					}
					if ($provider['restricted'] ?? false) {
						$slot['restricted'] = true;
					}
					if ($provider['override'] ?? false) {
						$slot['override'] = true;
					}

					$guest = $provider['guest'] ?? null;
					if (is_string($guest) && $guest != '') {
						$guest = get_user_by('login', $guest);
						if ($guest && $this->isAcceptableGuest($guest))
							$slot['guest'] = $guest->ID;
					}

					if ($raw = $provider['config'] ?? false) {
						$config = is_array($raw)? $raw : json_decode($raw,true);
						if (is_array($config)) {
							$config = $this->encryptAuthConfig($type, $config);
							$slot['config'] = $config;
						}
					}
				}
			}
		}

		return $data;
	} /*}}}*/

	/**
	 * Set debug logger.
	 * @param callback $debug Debug logger taking string message.
	 */
	public function setDebugLogger($logger) /*{{{*/
	{
		$this->dbg_logger = $logger;
	} /*}}}*/

	/**
	 * Set external user information in WordPress session.
	 * @param int $uid WordPress User ID.
	 * @param array $xu External user info.
	 * @param string $token WordPress session token if available.
	 * @return boolean True if info set.
	 */
	protected function setXUserInfo($uid, $xu, $token=null) /*{{{*/
	{
		if (!$uid)
			return false;
		assert($uid == ($xu['id'] ?? $uid));

		$sessTokens = WP_Session_Tokens::get_instance($uid);
		if ($token == '')
			$token = wp_get_session_token();
		$this->logDebug('setting user to WP session ...'.substr($token, -8));
		if (!$token)
			return false;
		$sess = $sessTokens->get($token);
		$sess[$this->getScopedAttrName('xuser')] = $xu ? array_merge($xu, [
			'id' => $uid,
		]) : null;
		$sessTokens->update($token, $sess);

		return true;
	} /*}}}*/

	/**
	 * Update customization configuration items.
	 * @param array $data Config data.
	 * @return array|WP_Error Updated config data or error.
	 */
	public function updateCustomization($data) /*{{{*/
	{
		$options = $this->getOptions();
		$options['customize'] = $data;
		$options = $this->sanitizeOptions($options);

		update_option($this->getOptionsName(), $options);

		// Reload options.
		$this->getOptions($cache=false);
		return $this->getCustomization();
	} /*}}}*/

	/**
	 * Perform database schema update.
	 */
	public function updateDbSchema() /*{{{*/
	{
		global $wpdb;

		// Get db schema helper functions like dbDelta().
		require_once ABSPATH.'wp-admin/includes/upgrade.php';

		$usersTable = $this->getTableXUsers();
		$xhashIndex = 'user_xhash';
		$wpuidIndex = 'user_wpuid';
		$obscuIndex = 'user_obscu';

		// Create user table.
		$charCollate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE $usersTable (
			id int(9) unsigned NOT NULL AUTO_INCREMENT,
			uid bigint(20) unsigned,
			obscure varchar(48),
			xhash varchar(64) NOT NULL,
			INDEX $wpuidIndex (uid),
			INDEX $obscuIndex (obscure),
			UNIQUE INDEX $xhashIndex (xhash),
			PRIMARY KEY  (id)
		) $charCollate;";

		dbDelta($sql);
	} /*}}}*/

	/**
	 * Update provider configuration.
	 * @param string $type Service type, e.g. 'google'.
	 * @param array $data Provider config data.
	 * @return array|WP_Error Updated config data or error.
	 */
	public function updateProviderConfig($type, $data) /*{{{*/
	{
		if (!in_array($type, $this->getAuthTypes()))
			return new WP_Error('input-invalid', "invalid xlogin auth: $type");

		$options = $this->getOptions();
		$options['providers'][$type] = $data;
		$options = $this->sanitizeOptions($options);

		update_option($this->getOptionsName(), $options);

		// Reload options.
		$this->getOptions($cache=false);
		return $this->getProviderConfig($type, $enabled=false);
	} /*}}}*/
} /*}}}*/

// vim: set ts=4 noexpandtab fdm=marker syntax=php: ('zR' to unfold all)
