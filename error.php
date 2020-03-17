<?php
/*
 * Render a basic error page or redirect to login page to display error.
 *
 * @todo WordPress way of error page generation?
 * @param string $error Error code.
 * @param string $etext Error message text.
 * @param string $title Optional error page title.
 *
 * @copyright Copyright (c) 2020 Patrick Lai
 */
call_user_func(function($error, $etext, $title) use($xlogin) {
	$login = wp_login_url();
	
	// Redirect to login page with error info.
	// TODO: pass on landing page URL?
	//
	if ($login != '') {
		$xlogin->flowErrorSet($error, $etext);
		if (wp_redirect($login))
			exit;
	}

	// No login page provided or redirect failed; generate barebone error page.
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
