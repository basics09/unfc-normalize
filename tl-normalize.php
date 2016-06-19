<?php
/**
 * Plugin Name: Normalizer
 * Plugin URI: https://github.com/Zodiac1978/tl-normalizer
 * Description: Normalizes UTF-8 input to Normalization Form C.
 * Version: 2.0.7
 * Author: Torsten Landsiedel
 * Author URI: http://torstenlandsiedel.de
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: normalizer
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'TLN_VERSION' ) ) {
	// Need to be synced with "readme.txt".
	define( 'TLN_VERSION', '2.0.7' );
	define( 'TLN_WP_AT_LEAST_VERSION', '4.0.11' );
	define( 'TLN_WP_UP_TO_VERSION', '4.5.2' );
}

/*
 * Why?
 *
 * For everyone getting this warning from W3C: "Text run is not in Unicode Normalization Form C."
 * http://www.w3.org/International/docs/charmod-norm/#choice-of-normalization-form
 *
 * As falling back to polyfill it's not required to have PHP 5.3+ or have the PHP-Normalizer-extension (intl and icu) installed.
 * But for performance reasons it's best.
 * See: http://php.net/manual/en/normalizer.normalize.php
 */

// See https://core.trac.wordpress.org/ticket/30130
// See also https://github.com/tinymce/tinymce/issues/1971

/*
Thank you very much for this code, Gary Pendergast!
http://pento.net/2014/02/18/dont-let-your-plugin-be-activated-on-incompatible-sites/
*/

load_plugin_textdomain( 'normalizer', false, basename( dirname( __FILE__ ) ) . '/languages' );

global $tlnormalizer;

// Where the magic happens.
if ( ! class_exists( 'TLNormalizer' ) ) {
	require dirname( __FILE__ ) . '/class-tl-normalizer.php';
}

$tlnormalizer = new TLNormalizer();

register_activation_hook( __FILE__, array( 'TLNormalizer', 'activation_check' ) );
