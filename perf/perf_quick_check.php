<?php

ini_set( 'ignore_repeated_errors', true );

$basename = basename( __FILE__ );
$dirname = dirname( __FILE__ );
$dirdirname = dirname( $dirname );

error_log( "(===begin " . $basename );

define( 'ABSPATH', $dirname . '/' );
define( 'WP_DEBUG', true );

require $dirdirname . '/tools/functions.php';
require $dirdirname . '/Symfony/unfc_regex_alts.php';

	function unfc_null( $string ) {
		return ! empty( $string );
	}

	function unfc_is_subset_NFC( $string ) {
		return 1 === preg_match(
			'/\A(?:
			  [\x00-\x7F]                                             # ASCII
			| [\xC2-\xCB][\x80-\xBF]                                  # 110xxxxx 10xxxxxx up to U+02FF
			)*+\z/x',
			$string
		);
	}

	function unfc_is_subset_NFC_u( $string ) {
		return 0 === preg_match( '/[^\x00-\x{2FF}]/u', $string );
	}

	function unfc_is_subset_NFC_2( $string ) {
		return 1 === preg_match( '/\A[\x00-\x{2FF}]*+\z/u', $string );
	}

	function unfc_is_subset_NFC_m( $string ) {
		if ( 1 !== preg_match( UNFC_REGEX_NFC_NOES_MAYBES_REORDERS, $string ) ) {
			return true;
		}
		return false;
	}

global $unfc_nfc_noes_maybes_reorders;
error_log( "count(unfc_nfc_noes_maybes_reorders)=" . count( $unfc_nfc_noes_maybes_reorders ) );

$strs_num = 50;
$loop_num = 10;
$str_min = 1;
$str_max = 100000;

$strs = array(
	'zer_oooo' => array(), 'one_thou' => array(), 'one_cent' => array(), 'fiv_cent' => array(), 'ten_cent' => array(),
	'twe_cent' => array(), 'for_cent' => array(), 'fif_cent' => array(), 'eig_cent' => array(), 'hun_cent' => array(),
);

for ( $i = 0; $i < $strs_num; $i++ ) {
	$strs['zer_oooo'][] = unfc_utf8_rand_str( rand( $str_min, $str_max ), 0x2ff );
	$strs['one_thou'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 0.001, $unfc_nfc_noes_maybes_reorders );
	$strs['one_cent'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 0.01, $unfc_nfc_noes_maybes_reorders );
	$strs['fiv_cent'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 0.05, $unfc_nfc_noes_maybes_reorders );
	$strs['ten_cent'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 0.1, $unfc_nfc_noes_maybes_reorders );
	$strs['twe_cent'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 0.2, $unfc_nfc_noes_maybes_reorders );
	$strs['for_cent'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 0.4, $unfc_nfc_noes_maybes_reorders );
	$strs['fif_cent'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 0.5, $unfc_nfc_noes_maybes_reorders );
	$strs['eig_cent'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 0.8, $unfc_nfc_noes_maybes_reorders );
	$strs['hun_cent'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 1.0, $unfc_nfc_noes_maybes_reorders );
	//$strs['ran_domm'][] = unfc_utf8_rand_str( rand( $str_min, $str_max ), 0xfff );
}

foreach ( array_keys( $strs ) as $idx ) {
	foreach ( $strs[ $idx ] as $i => $str ) {
		if ( 'zer_oooo' === $idx ) {
			if ( ! unfc_is_subset_NFC( $str ) ) {
				error_log( "bad result strs[ $idx ][ $i ]" );
				return;
			}
		}
		if ( unfc_is_subset_NFC( $str ) !== unfc_is_subset_NFC_u( $str ) ) {
			error_log( "bad match strs[ $idx ][ $i ]" );
			return;
		}
	}
}

$tots_t = array( 'zer_oooo' => 0, 'one_thou' => 0, 'one_cent' => 0, 'fiv_cent' => 0, 'ten_cent' => 0, 'twe_cent' => 0, 'for_cent' => 0, 'fif_cent' => 0, 'eig_cent' => 0, 'hun_cent' => 0, );
$tots_u = array( 'zer_oooo' => 0, 'one_thou' => 0, 'one_cent' => 0, 'fiv_cent' => 0, 'ten_cent' => 0, 'twe_cent' => 0, 'for_cent' => 0, 'fif_cent' => 0, 'eig_cent' => 0, 'hun_cent' => 0, );
$tots_2 = array( 'zer_oooo' => 0, 'one_thou' => 0, 'one_cent' => 0, 'fiv_cent' => 0, 'ten_cent' => 0, 'twe_cent' => 0, 'for_cent' => 0, 'fif_cent' => 0, 'eig_cent' => 0, 'hun_cent' => 0, );
$tots_m = array( 'zer_oooo' => 0, 'one_thou' => 0, 'one_cent' => 0, 'fiv_cent' => 0, 'ten_cent' => 0, 'twe_cent' => 0, 'for_cent' => 0, 'fif_cent' => 0, 'eig_cent' => 0, 'hun_cent' => 0, );

for ( $i = 0; $i < $loop_num; $i++ ) {
	foreach ( array_keys( $strs ) as $idx ) {
		foreach ( $strs[ $idx ] as $str ) {

			unfc_null( $str );

			$tots_t[ $idx ] += -microtime( true );
			unfc_is_subset_NFC( $str );
			$tots_t[ $idx ] += microtime( true );

			$tots_u[ $idx ] += -microtime( true );
			unfc_is_subset_NFC_u( $str );
			$tots_u[ $idx ] += microtime( true );

			$tots_2[ $idx ] += -microtime( true );
			unfc_is_subset_NFC_2( $str );
			$tots_2[ $idx ] += microtime( true );

			$tots_m[ $idx ] += -microtime( true );
			unfc_is_subset_NFC_m( $str );
			$tots_m[ $idx ] += microtime( true );
		}
	}
}

$tots = array( 'tots_t' => array(), 'tots_u' => array(), 'tots_2' => array(), 'tots_m' => array() );
foreach ( array_keys( $strs ) as $idx ) {
	$tots['tots_t'][ $idx ] = " t=" . sprintf( '%.10f', $tots_t[ $idx ] );
	$tots['tots_u'][ $idx ] = " u=" . sprintf( '%.10f', $tots_u[ $idx ] );
	$tots['tots_2'][ $idx ] = " 2=" . sprintf( '%.10f', $tots_2[ $idx ] );
	$tots['tots_m'][ $idx ] = " m=" . sprintf( '%.10f', $tots_m[ $idx ] );
}
$ret = "\n" . ' zer_oooo        one_thou        one_cent        fiv_cent        ten_cent        twe_cent        for_cent        fif_cent        eig_cent        hun_cent';
foreach ( $tots as $key => $val ) {
	$ret .= "\n" . implode( ' ', $val );
}
error_log( $ret );

error_log( ")===end " . $basename );
