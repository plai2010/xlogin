<?php
/**
 * Receive result from login flow.
 * @copyright Copyright (c) 2020 Patrick Lai
 */
use PL2010\WordPress\XLogin;

/*
use Exception;
*/

require_once __DIR__.'/var/boot.php';

/**
 * Receive login credential and continue the flow.
 * @param \PL2010\WordPress\XLogin $xlogin External login service.
 * @param string $path Request path info.
 * @param string $error Error code.
 * @param string $etext Error message text.
 */
call_user_func_array(function($xlogin, $path, $error, $etext) {
	if ($error == '') {
		// Login type is encoded as first path info element.
		$path = $path ? explode('/', $path) : [];
		$xtype = count($path) > 1 ? $path[1] : null;
		if ($xtype == '') {
			$error = 'invalid-request';
			$etext = __('No external login type in request path.', 'pl2010');
		}
		else {
			try {
				$redir = $xlogin->recvLoginCredential($xtype, $_GET??null);
				if ($redir != '') {
					header("Location: $redir");
					return;
				}
				list(
					$error,
					$etext,
				) = $xlogin->getLastError(true, 'pl2010');
			}
			catch (Exception $ex) {
				error_log("$ex");
				$error = 'unexpected-error';
				$etext = null;
			}
		}
	}

	if ($error != '') {
		http_response_code(500);
		require __DIR__.'/error.php';
	}
}, [
	XLogin::getInstanceByName(basename(__DIR__)),
	$_SERVER['PATH_INFO'] ?? null,
	$_REQUEST['error'] ?? null,
	$_REQUEST['error_description'] ?? null,
]);

// vim: set ts=4 noexpandtab fdm=marker syntax=php: ('zR' to unfold all)
