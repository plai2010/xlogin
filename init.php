<?php
/**
 * Plugin Name: XLogin
 * Description: Login using external auth mechanisms.
 * Version: 1.0
 * Author: Patrick Lai
 *
 * @copyright Copyright (c) 2019,2020 Patrick Lai
 */
use PL2010\WordPress\XLogin;

/*
use WP_User;

use Exception;
*/

call_user_func(function($CTX) {
require_once __DIR__.'/vendor/autoload.php';

//--------------------------------------------------------------
/** Handle callbacks from external login services. */
add_action('init', function() use($CTX) /*{{{*/ {
	$xlogin = XLogin::getInstance($CTX['plugin']);
	$plugin = $xlogin->getName();

	if ($CTX['debug'] ?? false) {
		$xlogin->setDebugLogger(function($msg) use($xlogin) {
			error_log("PL2010 {$xlogin->getName()}: $msg");
		});
	}

	$uri = $_SERVER['REQUEST_URI'] ?? null;
	if ($uri) {
		if ($callbk = $xlogin->getCallbackByUri($uri, $xtype)) {
			$error = $_REQUEST['error'] ?? null;
			$etext = $_REQUEST['error_description'] ?? null;

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

	if ($CTX['debug'] ?? false) {
		$xlogin->setDebugLogger(function($msg) use($xlogin) {
			error_log("PL2010 {$xlogin->getName()}: $msg");
		});
	}

	$xlogin->importXUser();
} /*}}}*/);

/** Accept external authentication credential. */
add_filter('authenticate', function($user, $name, $pass) use($CTX) /*{{{*/ {
	if ($user instanceof WP_User)
		return $user;

	if ($pass != '')
		return $user;

	$auth = $_REQUEST['pl2010_xauth'] ?? '';
	if ($auth == '')
		return $user;

	$xlogin = XLogin::getInstance($CTX['plugin']);
	if ($CTX['debug'] ?? false) {
		$xlogin->setDebugLogger(function($msg) use($xlogin) {
			error_log("PL2010 {$xlogin->getName()}: $msg");
		});
	}

	if ($auth != '')
		return $xlogin->getAuthenticated($auth, $name, $clear=true) ?? $user;
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
//	'debug' => true,
]);

// vim: set ts=4 noexpandtab fdm=marker syntax=php: ('zR' to unfold all)
