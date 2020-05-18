<?php
/**
 * Integration with WordPress stock login page.
 *
 * @param array $CTX See init.php.
 * @copyright Copyright (c) 2019,2020 Patrick Lai
 */
use PL2010\WordPress\XLogin;

/** Extra scripts needed for login page. */
add_action('login_enqueue_scripts', function() /*{{{*/ {
	wp_enqueue_script('jquery');
} /*}}}*/);

/** Extra header items for login page. */
add_action('login_head', function() use($CTX) /*{{{*/ {
	$xlogin = XLogin::getInstance($CTX['plugin']);

	//----------------------------------------------------------
	// HTML <head> items follows
	// TODO: sign-on hook JS function assumes login page with elements
	// 'loginform', etc.
	?>
	<script type="text/javascript">
		var PL2010 = PL2010 || {};
		PL2010.debug = function() {};
		<?php if (defined('PL2010_XLOGIN_DEBUG') && PL2010_XLOGIN_DEBUG) { ?>
		PL2010.debug = console.debug;
		<?php } ?>

		// Look for xlogin authentication in query string.
		// TODO: configurable query string parameter(s)
		(function(qstr) {
			if (!qstr)
				return;
			PL2010.debug("xlogin: parsing query string '"+qstr+"'");

			// External auth done already? If so, submit login form immediately.
			let xauth = qstr.match(/[?&]pl2010_xauth=([^?&]*)/);
			if (xauth) {
				xauth = xauth[1];

				PL2010.debug("xlogin: detected authentication '"+xauth+"'");
				jQuery(document).ready(function() {
					jQuery('#pl2010-xlogin-auth').val(xauth);

					PL2010.debug('xlogin: auto submission of login');
					jQuery('#loginform').submit();
				});
				return;
			}

			// TODO: update #user_login with external user name?
		})(window.location.search);
	</script>
	<?php
} /*}}}*/);

/** Extra element(s) for login form. */
add_action('login_form', function() use($CTX) /*{{{*/ {
	?>
	<input id="pl2010-xlogin-auth" name="pl2010_xauth" value="" type="hidden">
	<?php
} /*}}}*/);

/** Present external login options in footer. */
add_action('login_footer', function() use($CTX) /*{{{*/ {
	$xlogin = XLogin::getInstance($CTX['plugin']);

	$pluginUrl = plugin_dir_url($CTX['plugin']);
	$pluginDir = plugin_dir_path($CTX['plugin']);

	$imgBaseUrl = "{$pluginUrl}images/";
	$imgBaseDir = "{$pluginDir}images/";

	$activated = $xlogin->getAuthTypesActivated('pl2010');
	if (!$activated)
		return;

	wp_enqueue_style(
		'pl2010-xlogin',
		"{$pluginUrl}css/login.css",
		[],
		filemtime("{$pluginDir}css/login.css")
	);

	$customize = $xlogin->getCustomization();
	?>
	<div class="pl2010-xlogin-launch">
		<p class="title"><?php
			esc_html_e(__('Sign in with:', 'pl2010'));
		?></p>
		<?php
		// Reconstruct URL back to this page (which is the login page).
		// However, make sure to filter out 'reauth' which causes loss
		// of WP session. (The 'reauth' has already taken its effect.)
		// TODO: other query parameters to filter out?
		$login = parse_url(wp_login_url());
		$redir = $login['scheme'].'://'.$login['host'];
		if (isset($login['port']))
			$redir .= ':'.$login['port'];
		$redir .= $_SERVER['REQUEST_URI'];
		if (!empty($_SERVER['QUERY_STRING'])) {
			$qmark = strpos($redir, '?');
			if ($qmark !== false)
				$redir = substr($redir, 0, $qmark+1);
			else
				$redir .= '?';
			$qparams = explode('&', $_SERVER['QUERY_STRING']);
			$qparams = array_filter($qparams, function($nvp) {
				$eq = strpos($nvp, '=');
				$n = urldecode($eq === false ? $nvp : substr($nvp, 0, $eq));
				return $n != 'reauth';
			});
			$redir .= implode('&', $qparams);
		}

		// Render start button for each login type.
		foreach ($activated as $type => $desc) {
			// Add pl2010_xauth to the redirect back to login.
			$url = $xlogin->getStartUrl($type, add_query_arg([
				'pl2010_xauth' => urlencode($type),
			], $redir));
			if ($url == null)
				continue;
			$xlogin->logDebug("$type start: $url");
			?>
			<button type="button" class="button button-medium" onclick="<?php
				echo "window.location='", esc_attr($url), "'";
			?>" data-xtype="<?php
				echo esc_attr($type);
			?>"><?php
				require "$pluginDir/html/$type/login-btn.php";
			?></button>
			<?php
		}
		if ($customize['login_buttons_info'] ?? null) {
			?><p class="description"><?php
			esc_html_e($customize['login_buttons_info']);
			?></p><?php
		}
		?>
	</div>
	<?php
} /*}}}*/);

/** Add failed external login error. */
add_action('wp_login_errors', function($errors, $redir) use($CTX) /*{{{*/ {
	$xlogin = XLogin::getInstance($CTX['plugin']);
	list(
		$error,
		$etext,
	) = $xlogin->flowErrorGet($clear=true);

	if ($error != '') {
		$ecode = 0;
		if ($etext == '')
			$etext = __('Error: ', 'pl2010').$error;
		$errors->add($ecode, $etext);
	}
	return $errors;
} /*}}}*/, 10, 2);

// vim: set ts=4 noexpandtab fdm=marker syntax=php: ('zR' to unfold all)
