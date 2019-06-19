<?php

define( 'UNFC_TESTING', true );

function _manually_load_plugin() {
	if ( ! defined( 'UNFC_DEBUG' ) ) define( 'UNFC_DEBUG', true );
	if ( getenv( 'TRAVIS' ) ) {
		ini_set( 'error_log', '/var/log/php_errors.log' ); // Lessen noise on travis.
	}
	require dirname( dirname( __FILE__ ) ) . '/unfc-normalize.php';
}
WP_CLI::add_wp_hook( 'muplugins_loaded', '_manually_load_plugin' );
