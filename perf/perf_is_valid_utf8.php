<?php

ini_set( 'ignore_repeated_errors', true );

$basename = basename( __FILE__ );
$dirname = dirname( __FILE__ );
$dirdirname = dirname( $dirname );

error_log( "(===begin " . $basename );

require $dirdirname . '/tools/functions.php';
require $dirdirname . '/Symfony/Normalizer.php';

define( 'UNFC_REGEX_IS_VALID_UTF8',
			'/\A(?:
			  [\x00-\x7f]                                     # ASCII
			| [\xc2-\xdf][\x80-\xbf]                          # non-overlong 2-byte
			| \xe0[\xa0-\xbf][\x80-\xbf]                      # excluding overlongs
			| [\xe1-\xec\xee\xef][\x80-\xbf][\x80-\xbf]       # straight 3-byte
			| \xed[\x80-\x9f][\x80-\xbf]                      # excluding surrogates
			| \xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]           # planes 1-3
			| [\xf1-\xf3][\x80-\xbf][\x80-\xbf][\x80-\xbf]    # planes 4-15
			| \xf4[\x80-\x8f][\x80-\xbf][\x80-\xbf]           # plane 16
			)*+\z/x'
);

function unfc_null( $str ) {
	return ! empty( $str );
}

// Valids.
		$ret = array(
			array( "\x00" ), array( "a" ), array( "\x7f" ), array( "a\x7f" ), array( "\xc2\x80" ),
			array( "\xdf\xaf" ), array( "a\xdf\xbf" ), array( "\xdf\xbfb" ), array( "a\xde\xbfb" ), array( "\xe0\xa0\x80" ),
			array( "\xef\xbf\xbf" ), array( "a\xe1\x80\x80" ), array( "\xef\xb7\x90b" ), array( "a\xef\xbf\xafb" ), array( "\xf0\x90\x80\x80" ),
			array( "\xf4\x8f\xbf\xbf" ), array( "a\xf1\x80\x80\x80" ), array( "\xf2\x80\x80\x80b" ), array( "a\xf3\xbf\xbf\xbfb" ), /*array( "" ),*/
			array( "\xe7\xab\xa0\xe5\xad\x90\xe6\x80\xa1" ),
			array( "\x46\x72\x61\x6e\xc3\xa7\x6f\x69\x73\x20\x54\x72\x75\x66\x66\x61\x75\x74" ),
			array( "\xe1\x83\xa1\xe1\x83\x90\xe1\x83\xa5\xe1\x83\x90\xe1\x83\xa0\xe1\x83\x97\xe1\x83\x95\xe1\x83\x94\xe1\x83\x9a\xe1\x83\x9d" ),
			array( "\x42\x6a\xc3\xb6\x72\x6b\x20\x47\x75\xc3\xb0\x6d\x75\x6e\x64\x73\x64\xc3\xb3\x74\x74\x69\x72" ),
			array( "\xe5\xae\xae\xe5\xb4\x8e\xe3\x80\x80\xe9\xa7\xbf" ),
			array( "\xf0\x9f\x91\x8d" ),
		);

foreach ( $ret as $i => $strarr ) {
	$str = $strarr[0];
	if ( 1 !== preg_match( UNFC_REGEX_IS_VALID_UTF8, $str ) ) {
		error_log( "bad result valids [ $i ]" );
		return;
	}
	if ( ( 1 === preg_match( UNFC_REGEX_IS_VALID_UTF8, $str ) ) !== ( 1 !== preg_match( UNFC_REGEX_IS_INVALID_UTF8_SKIP, $str ) ) ) {
		error_log( "bad match valids invalid2 [ $i ]" );
		error_log( "str=" . bin2hex( $str ) );
		return;
	}
}

// Invalids.
		$ret = array(
			array( "\x80" ), array( "\xff" ), array( "a\x81" ), array( "\x83b" ), array( "a\x81b" ),
			array( "ab\xff"), array( "\xc2\x7f" ), array( "\xc0\xb1" ), array( "\xc1\x81" ), array( "a\xc2\xc0" ),
			array( "a\xd0\x7fb" ), array( "ab\xdf\xc0" ), array( "\xe2\x80" ), array( "a\xe2\x80" ), array( "a\xe2\x80b" ),
			array( "\xf1\x80" ), array( "\xe1\x7f\x80" ), array( "\xe0\x9f\x80" ), array( "\xed\xa0\x80" ), array( "\xef\x7f\x80" ),
			array( "\xef\xbf\xc0" ), array( "\xc2\xa0\x80" ), array( "\xf0\x90\x80" ), array( "\xe2\xa0\x80\x80" ), array( "\xf5\x80\x80\x80" ),
			array( "\xf0\x8f\x80\x80" ), array( "\xf4\x90\x80\x80" ), array( "\xf5\x80\x80\x80\x80" ), array( "a\xf5\x80\x80\x80\x80" ), array( "a\xf5\x80\x80\x80\x80b" ),
			array( "a\xc2\x80\x80b" ), array( "a\xc2\x80\xef\xbf\xbf\x80c" ), array( "a\xc2\x80\xe2\x80\x80\xf3\x80\x80\x80\x80b" ), array( "\xe0\x80\xb1" ), array( "\xf0\x80\x80\xb1" ),
			array( "\xf8\x80\x80\x80\xb1" ), array( "\xfc\x80\x80\x80\x80\xb1" ),
			array( "\xaa\xa9\xa5\xbb" ), array( "\xa4\xc0\xc3\xfe" ), array( "\xc0\xf4\xb9\xd2" ), array( "\xa9\xca\xbd\xe8" ), array( "\xad\xba\xad\xb6" ),
		);

foreach ( $ret as $i => $strarr ) {
	$str = $strarr[0];
	if ( 1 === preg_match( UNFC_REGEX_IS_VALID_UTF8, $str ) ) {
		error_log( "bad result invalids [ $i ]" );
		return;
	}
	if ( ( 1 === preg_match( UNFC_REGEX_IS_VALID_UTF8, $str ) ) !== ( 1 !== preg_match( UNFC_REGEX_IS_INVALID_UTF8_SKIP, $str ) ) ) {
		error_log( "bad match invalids invalid2 [ $i ]" );
		error_log( "str=" . bin2hex( $str ) );
		return;
	}
}

$check = true;
$strs_num = 100;
$loop_num = 50;
$str_min = 0;
$str_max = 10000;
error_log( "check=$check, strs_num=$strs_num, loop_num=$loop_num, str_min=$str_min, str_max=$str_max" );

$strs = array(
	'zer_oooo' => array(), 'one_thou' => array(), 'one_cent' => array(), //'fiv_cent' => array(), 'ten_cent' => array(),
	//'twe_cent' => array(), 'thi_cent' => array(), 'fif_cent' => array(), 'eig_cent' => array(), 'hun_cent' => array(),
);

for ( $i = 0; $i < $strs_num; $i++ ) {
	$strs['zer_oooo'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 0 );
	$strs['one_thou'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 0.001 );
	$strs['one_cent'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 0.01 );
	/*
	$strs['fiv_cent'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 0.05 );
	$strs['ten_cent'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 0.1 );
	$strs['twe_cent'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 0.2 );
	$strs['thi_cent'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 0.3 );
	$strs['fif_cent'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 0.5 );
	$strs['eig_cent'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 0.8 );
	$strs['hun_cent'][] = unfc_utf8_rand_ratio_str( rand( $str_min, $str_max ), 1.0 );
	*/
}
error_log( "done strs" );

if ( $check ) {
	foreach ( array_keys( $strs ) as $idx ) {
		foreach ( $strs[ $idx ] as $i => $str ) {
			if ( 'zer_oooo' === $idx ) {
				if ( 1 !== preg_match( UNFC_REGEX_IS_VALID_UTF8, $str ) ) {
					error_log( "bad result $idx [ $i ]" );
					return;
				}
			}
			if ( ( 1 === preg_match( UNFC_REGEX_IS_VALID_UTF8, $str ) ) !== ( 1 === preg_match( '//u', $str ) ) ) {
				error_log( "bad match $idx [ $i ]" );
				error_log( "str=" . bin2hex( $str ) );
				return;
			}
			if ( ( 1 === preg_match( UNFC_REGEX_IS_VALID_UTF8, $str ) ) !== ( '' === $str || '' !== htmlspecialchars( $str, ENT_NOQUOTES, "UTF-8" ) ) ) {
				error_log( "bad match html $idx [ $i ]" );
				error_log( "str=" . bin2hex( $str ) );
				return;
			}
			if ( ( 1 === preg_match( UNFC_REGEX_IS_VALID_UTF8, $str ) ) !== ( 1 !== preg_match( UNFC_REGEX_IS_INVALID_UTF8, $str ) ) ) {
				error_log( "bad match invalid $idx [ $i ]" );
				error_log( "str=" . bin2hex( $str ) );
				return;
			}
			if ( ( 1 === preg_match( UNFC_REGEX_IS_VALID_UTF8, $str ) ) !== ( 1 !== preg_match( UNFC_REGEX_IS_INVALID_UTF8_SKIP, $str ) ) ) {
				error_log( "bad match invalid2 $idx [ $i ]" );
				error_log( "str=" . bin2hex( $str ) );
				return;
			}
			if ( ( 1 === preg_match( UNFC_REGEX_IS_VALID_UTF8, $str ) ) !== unfc_is_valid_utf8( $str ) ) {
				error_log( "bad match invalid2 $idx [ $i ]" );
				error_log( "str=" . bin2hex( $str ) );
				return;
			}
		}
	}
}
error_log( "done check" );

$tots_u = array( 'zer_oooo' => 0, 'one_thou' => 0, 'one_cent' => 0, /*'fiv_cent' => 0, 'ten_cent' => 0, /*'twe_cent' => 0, 'thi_cent' => 0, 'fif_cent' => 0, 'eig_cent' => 0, 'hun_cent' => 0,*/ );
$tots_t = array( 'zer_oooo' => 0, 'one_thou' => 0, 'one_cent' => 0, /*'fiv_cent' => 0, 'ten_cent' => 0, /*'twe_cent' => 0, 'thi_cent' => 0, 'fif_cent' => 0, 'eig_cent' => 0, 'hun_cent' => 0,*/ );
$tots_h = array( 'zer_oooo' => 0, 'one_thou' => 0, 'one_cent' => 0, /*'fiv_cent' => 0, 'ten_cent' => 0, /*'twe_cent' => 0, 'thi_cent' => 0, 'fif_cent' => 0, 'eig_cent' => 0, 'hun_cent' => 0,*/ );
$tots_i = array( 'zer_oooo' => 0, 'one_thou' => 0, 'one_cent' => 0, /*'fiv_cent' => 0, 'ten_cent' => 0, /*'twe_cent' => 0, 'thi_cent' => 0, 'fif_cent' => 0, 'eig_cent' => 0, 'hun_cent' => 0,*/ );
$tots_s = array( 'zer_oooo' => 0, 'one_thou' => 0, 'one_cent' => 0, /*'fiv_cent' => 0, 'ten_cent' => 0, /*'twe_cent' => 0, 'thi_cent' => 0, 'fif_cent' => 0, 'eig_cent' => 0, 'hun_cent' => 0,*/ );
$tots_v = array( 'zer_oooo' => 0, 'one_thou' => 0, 'one_cent' => 0, /*'fiv_cent' => 0, 'ten_cent' => 0, /*'twe_cent' => 0, 'thi_cent' => 0, 'fif_cent' => 0, 'eig_cent' => 0, 'hun_cent' => 0,*/ );

for ( $i = 0; $i < $loop_num; $i++ ) {
	foreach ( array_keys( $strs ) as $idx ) {
		foreach ( $strs[ $idx ] as $str ) {

			unfc_null( $str );

			$tots_u[ $idx ] += -microtime( true );
			1 === preg_match( '//u', $str ); // Original Normalizer validity check.
			$tots_u[ $idx ] += microtime( true );

			$tots_t[ $idx ] += -microtime( true );
			1 === preg_match( UNFC_REGEX_IS_VALID_UTF8, $str );
			$tots_t[ $idx ] += microtime( true );

			$tots_h[ $idx ] += -microtime( true );
			'' === $str || '' !== htmlspecialchars( $str, ENT_NOQUOTES, "UTF-8" );
			$tots_h[ $idx ] += microtime( true );

			$tots_i[ $idx ] += -microtime( true );
			1 !== preg_match( UNFC_REGEX_IS_INVALID_UTF8, $str );
			$tots_i[ $idx ] += microtime( true );

			$tots_s[ $idx ] += -microtime( true );
			1 !== preg_match( UNFC_REGEX_IS_INVALID_UTF8_SKIP, $str );
			$tots_s[ $idx ] += microtime( true );

			$tots_v[ $idx ] += -microtime( true );
			unfc_is_valid_utf8( $str );
			$tots_v[ $idx ] += microtime( true );
		}
	}
}

$tots = array( 'tots_u' => array(), 'tots_t' => array(), 'tots_h' => array(), 'tots_i' => array(), 'tots_s' => array(), 'tots_v' => array() );
foreach ( array_keys( $strs ) as $idx ) {
	$tots['tots_u'][ $idx ] = " u=" . sprintf( '%.10f', $tots_u[ $idx ] );
	$tots['tots_t'][ $idx ] = " t=" . sprintf( '%.10f', $tots_t[ $idx ] );
	$tots['tots_h'][ $idx ] = " h=" . sprintf( '%.10f', $tots_h[ $idx ] );
	$tots['tots_i'][ $idx ] = " i=" . sprintf( '%.10f', $tots_i[ $idx ] );
	$tots['tots_s'][ $idx ] = " s=" . sprintf( '%.10f', $tots_s[ $idx ] );
	$tots['tots_v'][ $idx ] = " v=" . sprintf( '%.10f', $tots_v[ $idx ] );
}
//$ret = "\n" . ' zer_oooo        one_thou        one_cent        fiv_cent        ten_cent        twe_cent        thi_cent        fif_cent        eig_cent        hun_cent';
$ret = "\n" . ' zer_oooo        one_thou        one_cent';
foreach ( $tots as $key => $val ) {
	$ret .= "\n" . implode( ' ', $val );
}
error_log( $ret );

error_log( ")===end " . $basename );
