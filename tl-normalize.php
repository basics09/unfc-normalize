<?php
/**
 * Plugin Name: Normalizer
 * Plugin URI: https://github.com/Zodiac1978/tl-normalizer
 * Description: Normalizes content, excerpt, title and comment content to Normalization Form C.
 * Version: 1.0.0
 * Author: Torsten Landsiedel
 * Author URI: http://torstenlandsiedel.de
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tl-normalizer
 * Domain Path: /languages
 */


function check_php() {
	if ( !extension_loaded( 'intl' ) && !extension_loaded( 'icu' ) ) {
		// extensions are NOT loaded
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( __( '<strong>Activation failed:</strong> Your PHP is missing one of the required extensions intl and icu.', 'tl-normalizer' ) );
	}


	if ( !version_compare( phpversion(), "5.3.0", ">=" ) ) {
		// you're NOT on PHP 5.3.0 or later
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( __( '<strong>Activation failed:</strong> Your PHP version hast to be 5.3.0 or later.', 'tl-normalizer' ) );
	}
}
add_action( 'admin_init', 'check_php' );


function tl_normalizer( $content ) {

	/*
	 * Why?
	 *
	 * For everyone getting this warning from W3C: "Text run is not in Unicode Normalization Form C."
	 * http://www.w3.org/International/docs/charmod-norm/#choice-of-normalization-form
	 *
	 * Requires PHP 5.3+
	 * Be sure to have the PHP-Normalizer-extension (intl and icu) installed.
	 * See: http://php.net/manual/en/normalizer.normalize.php
	 */
	if ( ! normalizer_is_normalized( $content, Normalizer::FORM_C ) ) {
		$content = normalizer_normalize( $content, Normalizer::FORM_C );
	}

	return $content;
}

add_filter( 'content_save_pre', 'tl_normalizer' );
add_filter( 'title_save_pre' , 'tl_normalizer' );
add_filter( 'pre_comment_content' , 'tl_normalizer' );
add_filter( 'excerpt_save_pre' , 'tl_normalizer' );
