<?php
/**
 * Render a basic error page.
 * @copyright Copyright (c) 2020 Patrick Lai
 *
 * @todo WordPress way of error page generation?
 * @param string $error Error code.
 * @param string $etext Error message text.
 * @param string $title Optional error page title.
 */

require_once __DIR__.'/var/boot.php';

call_user_func(function($error, $etext, $title) {
	// TODO: Configurable login URL?
	//
	$login = site_url('/wp-login.php');
	
	// Redirect to login page with error info.
	// TODO: pass on landing page URL?
	//
	if ($login) {
		$login .= (strpos($login, '?') === false) ? '?' : '&';

		$login .= implode('&', [
			'pl2010_xlogin_error='.rawurlencode($error),
			'pl2010_xlogin_etext='.rawurlencode($etext),
		]);
		wp_redirect($login);
		exit;
	}

	// No login page provided; generate barebone error page.
	//
	if ($title == '')
		$title = __('External Login Error', 'pl2010');
	?>
	<html>
		<head>
			<title><?php esc_html_e($title); ?></title>
		</head>
		<body>
			<h3><strong><?php esc_html_e($error); ?></strong></h3>
			<p><?php esc_html_e($etext); ?></p>
		</body>
	</html>
	<?php
}, $error, $etext??null, $title??null);

// vim: set ts=4 noexpandtab fdm=marker syntax=php: ('zR' to unfold all)
