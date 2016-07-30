<?php
/**
 * Plugin Name: UNFC Nörmalize
 * Plugin URI: https://github.com/gitlost/unfc-normalize
 * Description: Normalizes UTF-8 input to Normalization Form C.
 * Version: 1.0.0
 * Author: gitlost
 * Author URI: https://profiles.wordpress.org/gitlost
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: unfc-normalize
 * Domain Path: /languages
 */

/*
 * Originally a fork of https://github.com/Zodiac1978/tl-normalizer by Torsten Landsiedel.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'UNFC_VERSION' ) ) {
	// These need to be synced with "readme.txt".
	define( 'UNFC_VERSION', '1.0.0' ); // Sync also "package.json" and "language/unfc-normalize.pot".
	define( 'UNFC_WP_AT_LEAST_VERSION', '3.9.13' );
	define( 'UNFC_WP_UP_TO_VERSION', '4.5.3' );
}

load_plugin_textdomain( 'unfc-normalize', false, basename( dirname( __FILE__ ) ) . '/languages' );

global $unfc_normalize; // The single instance.

// Where the magic (and the tragic) happens.
if ( ! class_exists( 'UNFC_Normalize' ) ) {
	require dirname( __FILE__ ) . '/class-unfc-normalize.php';
}

$unfc_normalize = new UNFC_Normalize();

register_activation_hook( __FILE__, array( 'UNFC_Normalize', 'activation_check' ) );
