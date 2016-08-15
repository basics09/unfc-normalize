<?php
/**
 * Port of https://github.com/nicolas-grekas/Patchwork-UTF8/blob/master/src/Patchwork/Utf8/Compiler.php
 *
 * Output the various mapping classes used by the UNFC_Normalizer class to the "Symfony/Resources/unidata" directory.
 *
 * See http://www.unicode.org/Public/9.0.0/ucd/UnicodeData.txt and http://www.unicode.org/Public/9.0.0/ucd/CompositionExclusions.txt
 */

$basename = basename( __FILE__ );
$dirname = dirname( __FILE__ );
$dirdirname = dirname( $dirname );
$subdirname = basename( $dirname );
$outdirname = $argc && ! empty( $argv[1] ) ? $argv[1] : 'Symfony/Resources/unidata';

require $dirname . '/functions.php';

if ( ! function_exists( '__' ) ) {
	function __( $str, $td ) { return $str; }
}

// Open the exclusions file.

$filename = '/tests/UCD-9.0.0/CompositionExclusions.txt';
$file = $dirdirname . $filename;
error_log( "$basename: reading file=$file" );

// Read the file.

if ( false === ( $get = file_get_contents( $file ) ) ) {
	/* translators: %s: file name */
	$error = sprintf( __( 'Could not read composition exclusions file "%s"', 'unfc-normalize' ), $file );
	error_log( $error );
	return $error;
}

$lines = array_map( 'unfc_get_cb', explode( "\n", $get ) ); // Strip newlines.

// Parse the file, creating array of exclusion codepoints.

$exclusions = array();
$in = false;
$line_num = 0;
foreach ( $lines as $line ) {
	$line_num++;
	$line = trim( $line );
	if ( '' === $line ) {
		continue;
	}
	if ( ! preg_match( '/^(?:# )?([0-9A-F]{4,})(\.\.[0-9A-F]{4,})?/', $line, $matches ) ) {
		continue;
	}
	if ( isset( $matches[2] ) ) {
		$begin = hexdec( $matches[1] );
		$end = hexdec( substr( $matches[2], 2 ) );
		for ( $i = $begin; $i <= $end; $i++ ) {
			$exclusions[ unfc_utf8_chr( $i ) ] = true;
		}
	} else {
		$exclusions[ unfc_utf8_chr( hexdec( $matches[1] ) ) ] = true;
	}
}

// Callback for Unicode data file parser.
function parse_unicode_data_cb( &$codepoints, $cp, $name, $parts, $in_interval, $first_cp, $last_cp ) {
	global $exclusions, $combining_classes, $canonical_compositions, $canonical_decompositions, $compatibility_decompositions;

	$code = unfc_utf8_chr( $cp );

	$combining_class = intval( $parts[3] );
	if ( $combining_class ) {
		$combining_classes[ $code ] = $combining_class;
	}

	$decomp = $parts[5];
	if ( $decomp ) {
		$canonic = '<' !== $decomp[0];
		if ( ! $canonic ) {
			$decomp = preg_replace( '/^<.*> /', '', $decomp );
		}
		$decomps = explode( ' ', $decomp );

		$decomps = array_map( 'hexdec', $decomps );
		$decomps = array_map( 'unfc_utf8_chr', $decomps );
		$decomp = implode( '', $decomps );

		if ( $canonic ) {
			$canonical_decompositions[ $code ] = $decomp;
			$exclude = 1 === count( $decomps ) || isset( $exclusions[ $code ] );
			if ( ! $exclude ) {
				$canonical_compositions[ $decomp ] = $code;
			}
		}

		$compatibility_decompositions[ $code ] = $decomp;
	}
}

$combining_classes = $canonical_compositions = $canonical_decompositions = $compatibility_decompositions = array();

// Parse the main unicode file.

$filename = '/tests/UCD-9.0.0/UnicodeData.txt';
$file = $dirdirname . $filename;
error_log( "$basename: reading file=$file" );

if ( false === unfc_parse_unicode_data( $file, 'parse_unicode_data_cb' ) ) {
	/* translators: %s: file name */
	$error = sprintf( __( 'Could not read unicode data file "%s"', 'unfc-normalize' ), $file );
	error_log( $error );
	return $error;
}

do {
	$change = false;
	foreach ( $canonical_decompositions as $code => $decomp ) {
		$trans = strtr( $decomp, $canonical_decompositions );
		if ( $trans !== $decomp ) {
			$canonical_decompositions[ $code ] = $trans;
			$change = true;
		}
	}
} while ( $change );

do {
	$change = false;
	foreach ( $compatibility_decompositions as $code => $decomp ) {
		$trans = strtr( $decomp, $compatibility_decompositions );
		if ( $trans !== $decomp ) {
			$compatibility_decompositions[ $code ] = $trans;
			$change = true;
		}
	}
} while ( $change );

foreach ( $compatibility_decompositions as $code => $decomp ) {
	if ( isset( $canonical_decompositions[ $code ] ) && $canonical_decompositions[ $code ] === $decomp ) {
		unset( $compatibility_decompositions[ $code ] );
	}
}

//error_log( "combining_classes(" . count( $combining_classes ) . ")=" . print_r( $combining_classes, true ) );
//error_log( "canonical_compositions(" . count( $canonical_compositions ) . ")=" . print_r( $canonical_compositions, true ) );
//error_log( "canonical_decompositions(" . count( $canonical_decompositions ) . ")=" . print_r( $canonical_decompositions, true ) );
//error_log( "compatibility_decompositions(" . count( $compatibility_decompositions ) . ")=" . print_r( $compatibility_decompositions, true ) );

function out_esc( $str ) {
	return str_replace( array( '\\', '\'' ), array( '\\\\', '\\\'', ), $str );
}

// Output.

$out = array();
$out[] =  '<?php';
$out[] = '';
$out[] = 'static $data = array (';

foreach ( $combining_classes as $code => $class ) {
	$out[] = '  \'' . $code . '\' => ' . $class . ',';
}
$out[] = ');';
$out[] = '';
$out[] = '$result =& $data;';
$out[] = 'unset($data);';
$out[] = '';
$out[] = 'return $result;';
$out[] = '';

file_put_contents( $outdirname . '/combiningClass.php', implode( "\n", $out ) );

$out = array();
$out[] =  '<?php';
$out[] = '';
$out[] = 'static $data = array (';

foreach ( $canonical_compositions as $decomp => $code ) {
	$out[] = '  \'' . $decomp . '\' => \'' . $code . '\',';
}
$out[] = ');';
$out[] = '';
$out[] = '$result =& $data;';
$out[] = 'unset($data);';
$out[] = '';
$out[] = 'return $result;';
$out[] = '';

file_put_contents( $outdirname . '/canonicalComposition.php', implode( "\n", $out ) );

$out = array();
$out[] =  '<?php';
$out[] = '';
$out[] = 'static $data = array (';

foreach ( $canonical_decompositions as $code => $decomp ) {
	$out[] = '  \'' . $code . '\' => \'' . $decomp . '\',';
}
$out[] = ');';
$out[] = '';
$out[] = '$result =& $data;';
$out[] = 'unset($data);';
$out[] = '';
$out[] = 'return $result;';
$out[] = '';

file_put_contents( $outdirname . '/canonicalDecomposition.php', implode( "\n", $out ) );

$out = array();
$out[] =  '<?php';
$out[] = '';
$out[] = 'static $data = array (';

foreach ( $compatibility_decompositions as $code => $decomp ) {
	$out[] = '  \'' . out_esc( $code ) . '\' => \'' . out_esc( $decomp ) . '\',';
}
$out[] = ');';
$out[] = '';
$out[] = '$result =& $data;';
$out[] = 'unset($data);';
$out[] = '';
$out[] = 'return $result;';
$out[] = '';

file_put_contents( $outdirname . '/compatibilityDecomposition.php', implode( "\n", $out ) );
