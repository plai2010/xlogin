<?php
/**
 * Plugin Name: XLogin
 * Description: Login using external auth mechanisms.
 * Version: 1.1.2
 * Author: Patrick Lai
 *
 * @copyright Copyright (c) 2019,2020 Patrick Lai
 */
use PL2010\WordPress\XLogin;

/*
use WP_User;

use Exception;
*/

// define('PL2010_XLOGIN_DEBUG', true);

call_user_func(function($CTX) {
require_once __DIR__.'/vendor/autoload.php';

//--------------------------------------------------------------
/** Handle callbacks from external login services. */
add_action('init', function() use($CTX) /*{{{*/ {
	$xlogin = XLogin::getInstance($CTX['plugin']);
	$plugin = $xlogin->getName();

	$uri = $_SERVER['REQUEST_URI'] ?? null;
	if ($uri) {
		if ($callbk = $xlogin->getCallbackByUri($uri, $xtype)) {
			// Don't expect anything funny in error code/label.
			$error = null;
			if (isset($_REQUEST['error'])) {
				$error = preg_replace(
					'/[^.A-Za-z0-9_\-]/',
					'',
					$_REQUEST['error']
				);
			}

			// Error message could generally be anything text, but let's
			// say it shouldn't be longer than certain length.
			$etext = null;
			if (isset($_REQUEST['error_description'])) {
				$etext = wp_check_invalid_utf8(
					substr($_REQUEST['error_description'], 0, 80)
				);
			}

			if ($error != '') {
				// Handle error conveyed to callback by external service.
				list(
					$error,
					$etext,
				) = $xlogin->flowErrorRecv($error, $etext);
			}
			else {
				// Callback script expects these: $xlogin, $xtype.
				// It returns true for success, and false with $error
				// and $etext set otherwise.
				if ($xtype == '') {
					$error = 'invalid_request';
					$etext = __('External login type missing.', 'pl2010');
				}
				else {
					// Done if callback succeeds.
					if (require __DIR__."/$callbk.php")
						exit();
				}
			}

			// Error encountered if we get here. Make sure error script
			// has an $error to work on.
			$error = $error ?: 'server_error';
			http_response_code(500);
			require __DIR__.'/error.php';
			exit();
		}
	}
} /*}}}*/);

//--------------------------------------------------------------
/** Override in-memory user properties with info from external auth. */
add_action('set_current_user', function() use($CTX) /*{{{*/ {
	$xlogin = XLogin::getInstance($CTX['plugin']);
	$xlogin->importXUser();
} /*}}}*/);

/** Accept external authentication credential. */
add_filter('authenticate', function($user, $name, $pass) use($CTX) /*{{{*/ {
	if ($user instanceof WP_User)
		return $user;

	if ($pass != '')
		return $user;

	$xlogin = XLogin::getInstance($CTX['plugin']);

	$auth = null;
	if (isset($_REQUEST['pl2010_xauth'])
		&& in_array($_REQUEST['pl2010_xauth'], $xlogin->getAuthTypesEnabled())
	) {
		$auth = $_REQUEST['pl2010_xauth'];
	}
	if ($auth == '')
		return $user;

	if ($xu = $xlogin->getAuthenticated($auth, $name, $clear=true, $guest)) {
		if ($guest) {
			// Guest is not allowed to access admin page, so replace
			// use site URL if login redirect looks like an admin page
			// so that user does not get an error page.
			add_filter('login_redirect', function($url) {
				$redir = trailingslashit(parse_url($url, PHP_URL_PATH));
				$admin = trailingslashit(parse_url(admin_url(), PHP_URL_PATH));
				if (strpos($redir, $admin) === 0)
					return site_url();
				return $url;
			});
		}
		return $xu;
	}
	return $user;
} /*}}}*/, 10, 3);

/** Save external user information to session. */
add_action('set_logged_in_cookie', function(
	$cookie, $expire, $expiration, $uid, $scheme, $token
) use($CTX) /*{{{*/ {
	$xlogin = XLogin::getInstance($CTX['plugin']);
	$xlogin->setXUserToSession($token, $uid);
} /*}}}*/, 10, 6);

/** Activation hook for the plugin. */
register_activation_hook(__FILE__, function() use($CTX) /*{{{*/ {
	$xlogin = XLogin::getInstance($CTX['plugin']);
	$pluginName = $xlogin->getName();

	$xlogin->updateDbSchema();
	add_option("${pluginName}_options", $xlogin->getDefaultOptions());
} /*}}}*/);

/** Deactivation hook for the plugin. */
register_deactivation_hook(__FILE__, function() /*{{{*/ {
	// TODO: drop database table?
} /*}}}*/);

require __DIR__.'/includes/admin.php';
require __DIR__.'/includes/api.php';
require __DIR__.'/includes/login.php';
//--------------------------------------------------------------
}, [
	'plugin' => __FILE__,
]);

// vim: set ts=4 noexpandtab fdm=marker syntax=php: ('zR' to unfold all)
