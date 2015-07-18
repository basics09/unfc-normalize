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