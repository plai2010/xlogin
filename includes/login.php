<?php
/**
 * Integration with WordPress stock login page.
 *
 * @param array $CTX See init.php.
 * @copyright Copyright (c) 2019,2020 Patrick Lai
 */
use PL2010\WordPress\XLogin;

/** Extra header items for login form. */
add_action('login_head', function() use($CTX) /*{{{*/ {
	$xlogin = XLogin::getInstance($CTX['plugin']);

	wp_enqueue_script('jquery');
	//----------------------------------------------------------
	// HTML <head> items follows
	// TODO: sign-on hook JS function assumes login page with elements
	// 'loginform', etc.
	?>
	<script type="text/javascript">
		var PL2010 = PL2010 || {};
		PL2010.debug = function() {};
		<?php if ($CTX['debug'] ?? false) { ?>
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
	?>
	<div class="pl2010-xlogin-launch">
		<p class="title"><?php
			esc_html_e(__('Sign in with:', 'pl2010'));
		?></p>
		<?php
		// Redirect URL is expected to be back to WordPress.
		$redir = $_REQUEST['redirect_to'] ?? null;
		if ($redir != '') {
			if (strpos($redir, home_url()) !== 0
				&& strpos($redir, get_site_url()) !== 0
			) {
				$redir = null;
			}
		}
		foreach ($activated as $type => $desc) {
			$url = $xlogin->getStartUrl($type, $redir);
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
