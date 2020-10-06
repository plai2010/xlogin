<?php
/**
 * Integration with WordPress administration.
 *
 * @param array $CTX See init.php.
 * @copyright Copyright (c) 2019,2020 Patrick Lai
 */
use PL2010\WordPress\XLogin;

/** Custom settings of the plugin. */
add_action('admin_init', function() use($CTX) /*{{{*/ {
	$xlogin = XLogin::getInstance($CTX['plugin']);
	$pluginName = $xlogin->getName();

	$pluginUrl = plugin_dir_url($CTX['plugin']);
	$pluginDir = plugin_dir_path($CTX['plugin']);

/*
	register_setting('pl2010-xlogin', "{$pluginName}_options", [
		'sanitize_callback' => [ $xlogin, 'sanitizeOptions' ],
	]);
*/
 
	// Load various JS libraries.
	wp_enqueue_script('wp-api');
	wp_enqueue_script('jquery');

	wp_enqueue_script('pl2010_xlogin_settings', "{$pluginUrl}js/settings.js", [
		'jquery',
		'wp-api',
	], null);

	wp_enqueue_style(
		'pl2010-xlogin',
		"{$pluginUrl}css/admin.css",
		[],
		filemtime("{$pluginDir}css/admin.css")
	);
	//------------------------------------------------------
	// Login service providers section. {{{
	//
	add_settings_section(
		'pl2010-xlogin-xsvcs',
		__('External Login Services', 'pl2010'),
		function($args) {
			echo '<pl2010-xlogin-xsvcs id="pl2010-xlogin-xsvcs">';
			echo '</pl2010-xlogin-xsvcs>';
		},
		'pl2010-xlogin'
	);
	// }}}
	//------------------------------------------------------
	// External user aliases section. {{{
	//
	add_settings_section(
		'pl2010-xlogin-xusers',
		__('External Aliases', 'pl2010'),
		function($args) {
			echo '<pl2010-xlogin-xusers id="pl2010-xlogin-xusers">';
			echo '</pl2010-xlogin-xusers>';
		},
		'pl2010-xlogin'
	);
	// }}}
	//------------------------------------------------------
	// Customization section. {{{
	//
	add_settings_section(
		'pl2010-xlogin-customize',
		__('Customization', 'pl2010'),
		function($args) {
			echo '<pl2010-xlogin-customize id="pl2010-xlogin-customize">';
			echo '</pl2010-xlogin-customize>';
		},
		'pl2010-xlogin'
	);
	// }}}
	//------------------------------------------------------
} /*}}}*/);

/** Register settings page to admin menu. */
add_action('admin_menu', function() use($CTX) /*{{{*/ {
	$xlogin = XLogin::getInstance($CTX['plugin']);
	$pluginName = $xlogin->getName();

	$menuSlug = 'pl2010-xlogin';
	$baseTitle = $pluginName != 'xlogin'
		? "XLogin [$pluginName]"
		: 'XLogin';
	$pageTitle = "$baseTitle Settings";
	$menuTitle = $baseTitle;

	add_submenu_page(
		'options-general.php',
		__($pageTitle, 'pl2010'),
		__($menuTitle, 'pl2010'),
		'manage_options',
		$menuSlug,
		function() /*{{{*/ {
			// Check permission.
			// TODO: is this necessary? wouldn't the $capability
			// for add_options_page() already take care of it?
			if (!current_user_can('manage_options'))
				return;

			//------------------------------------------------------
			// Settings page HTML {{{
			?>
			<div class="wrap">
				<h1><?php esc_html_e(get_admin_page_title()); ?></h1>
				<?php
				do_settings_sections('pl2010-xlogin');
				?>
			</div>
			<?php
			// }}}
			//------------------------------------------------------
		} /*}}}*/
	);
} /*}}}*/);

// vim: set ts=4 noexpandtab fdm=marker syntax=php: ('zR' to unfold all)
