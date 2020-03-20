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
		// Some basic filtering here. Strictly speaking not necessary
		// if recvLoginCredential() is robust.
		$creds = [];
		foreach ($xlogin->getLoginCredentFields($xtype) as $fn => $ft) {
			if (isset($_GET[$fn])) {
				switch ($ft) {
				case 'boolean':
					$fv = filter_var(
						$_GET[$fn],
						FILTER_VALIDATE_BOOLEAN,
						FILTER_NULL_ON_FAILURE
					);
					if ($fv !== null)
						$creds[$fn] = $fv;
					break;
				case 'integer':
					$fv = filter_var($_GET[$fn], FILTER_VALIDATE_INT);
					if ($fv !== false)
						$creds[$fn] = $fv;
					break;
				case 'string':
				default:
					$creds[$fn] = wp_check_invalid_utf8($_GET[$fn]);
					break;
				}
			}
		}

		$redir = $xlogin->recvLoginCredential($xtype, $creds);
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
