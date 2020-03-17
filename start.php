<?php
/**
 * Start external login flow by redirecting to external authorization.
 *
 * This script returns true/false to indicate success/failure. On
 * failure, it also sets $error and $etext for error label and message
 * respectively.
 *
 * @param \PL2010\WordPress\XLogin $xlogin XLogin instance.
 * @param string $xtype External login type, e.g. 'google'.
 * @param array $_REQUEST Provide landing URL as 'redir'.
 * @return boolean
 *
 * @copyright Copyright (c) 2020 Patrick Lai
 */
assert($xlogin && $xtype != '');
return call_user_func(function() use($xlogin, $xtype, &$error, &$etext) {
	// TODO: sanitize $redir?
	$redir = $_REQUEST['redir'] ?? null;

	$error = $etext = null;
	if ($xtype == '') {
		$error = 'invalid_request';
		$etext = __('External login type missing.', 'pl2010');
		return false;
	}

	try {
		$redir = $xlogin->launchLoginFlow($xtype, $redir);
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
