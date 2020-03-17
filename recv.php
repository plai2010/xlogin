<?php
/**
 * Receive login credential from login flow and continue the flow.
 *
 * This script returns true/false to indicate success/failure. On
 * failure, it also sets $error and $etext for error label and message
 * respectively.
 *
 * @param \PL2010\WordPress\XLogin $xlogin External login service.
 * @param string $xtype External login type, e.g. 'google'.
 * @return boolean
 *
 * @copyright Copyright (c) 2020 Patrick Lai
 */
assert($xlogin && $xtype != '');
return call_user_func(function() use($xlogin, $xtype, &$error, &$etext) {
	$error = $etext = null;

	try {
		$redir = $xlogin->recvLoginCredential($xtype, $_GET??null);
		if ($redir != '') {
			header("Location: $redir");
			return true;
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

	$error = $error ?: 'server_error';
	return false;
});

// vim: set ts=4 noexpandtab fdm=marker syntax=php: ('zR' to unfold all)
