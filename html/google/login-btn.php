<?php
/**
 * Generate body of login button for Google.
 *
 * @param string $imgBaseUrl Base URL for images of the plugin.
 * @copyright Copyright (c) 2019,2020 Patrick Lai
 */
wp_enqueue_style(
	'pl2010-roboto',
	'https://fonts.googleapis.com/css?family=Roboto'
);
//------------------------------------------------------------------
// HTML to go inside <button>...</button> follows.
?>
<span>
	<img src="<?php echo $imgBaseUrl, 'google/btn-signin.svg'; ?>">
	<label>Google</label>
</span>
<?php
// vim: set ts=4 noexpandtab fdm=marker syntax=php: ('zR' to unfold all)
