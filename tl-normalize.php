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

/* 
Thank you very much for this code, Gary Pendergast!
http://pento.net/2014/02/18/dont-let-your-plugin-be-activated-on-incompatible-sites/
*/

//  In this example, only allow activation on WordPress 3.7 or higher
class TLNormalizer {
    function __construct() {
        add_action( 'admin_init', array( $this, 'check_version' ) );

        // Don't run anything else in the plugin, if we're on an incompatible WordPress version
        if ( ! self::compatible_version() ) {
            return;
        }

        add_filter( 'content_save_pre', array( $this, 'tl_normalizer' ) );
        add_filter( 'title_save_pre' , array( $this, 'tl_normalizer' ) );
        add_filter( 'pre_comment_content' , array( $this, 'tl_normalizer' ) );
        add_filter( 'excerpt_save_pre' , array( $this, 'tl_normalizer' ) );
    }

    // The primary sanity check, automatically disable the plugin on activation if it doesn't
    // meet minimum requirements.
    static function activation_check() {
        if ( ! self::compatible_version() ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            wp_die( __( 'Your PHP version is not 5.3.0 or later or your PHP is missing one of the required extensions (intl and icu).', 'tl-normalizer' ) );
        }
    }

    // The backup sanity check, in case the plugin is activated in a weird way,
    // or the versions change after activation.
    function check_version() {
        if ( ! self::compatible_version() ) {
            if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
                deactivate_plugins( plugin_basename( __FILE__ ) );
                add_action( 'admin_notices', array( $this, 'disabled_notice' ) );
                if ( isset( $_GET['activate'] ) ) {
                    unset( $_GET['activate'] );
                }
            }
        }
    }

    function disabled_notice() {
    	$error_message  = '<div id="message" class="updated notice is-dismissible">';
    	$error_message .= '<p><strong>' . __('Plugin deactivated!') . '</strong> ';
    	$error_message .= esc_html__( 'Your PHP version is not 5.3.0 or later or your PHP is missing one of the required extensions (intl and icu).', 'tl-normalizer' );
    	$error_message .= '</p></div>';
    	echo $error_message;
    } 

    static function compatible_version() {

        // Add sanity checks for other version requirements here
        if ( !extension_loaded( 'intl' ) && !extension_loaded( 'icu' ) ) {
             return false;
        }

        if ( !version_compare( phpversion(), "5.3.0", ">=" ) ) {
             return false;
        }

        return true;
    }

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
}

global $normalizer;
$normalizer = new TLNormalizer();

register_activation_hook( __FILE__, array( 'TLNormalizer', 'activation_check' ) );