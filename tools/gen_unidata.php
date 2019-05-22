<?php
/**
 * Port of https://github.com/nicolas-grekas/Patchwork-UTF8/blob/master/src/Patchwork/Utf8/Compiler.php
 *
 * Output the various mapping classes used by the UNFC_Normalizer class to the "Symfony/Resources/unidata" directory.
 *
 * See http://www.unicode.org/Public/12.1.0/ucd/UnicodeData.txt, http://www.unicode.org/Public/12.1.0/ucd/CompositionExclusions.txt
 * and http://www.unicode.org/Public/12.1.0/ucd/DerivedNormalizationProps.txt
 */

$basename = basename( __FILE__ );
$dirname = dirname( __FILE__ );
$dirdirname = dirname( $dirname );

require $dirname . '/functions.php';

$opts = getopt( 'v:sd:o:' );
$version = isset( $opts['v'] ) ? $opts['v'] : '12.1.0'; // Unicode version number.
$suffix = isset( $opts['s'] ); // If set will add Unicode version number as suffix to filename (before ".php" that is).
$datadirname = isset( $opts['d'] ) ? $opts['d'] : ( 'tests/UCD-' . $version ); // Where to load Unicode data files from.
$outdirname = isset( $opts['o'] ) ? $opts['o'] : 'Symfony/Resources/unidata'; // Where to put output.

$filename_suffix = $suffix ? ( '-' . $version ) : '';

if ( ! function_exists( '__' ) ) {
	function __( $str, $td ) { return $str; }
}

// First create the decomposition exclusion array.

// Open the exclusions file.

$filename = $datadirname . '/CompositionExclusions.txt';
$file = $dirdirname . '/' . $filename;
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

// Next create the combining class, canonical composition, canonical decomposition, compatibility decomposition and raw decomposition arrays.

// Callback for Unicode data file parser.
function parse_unicode_data_cb( &$codepoints, $cp, $name, $parts, $in_interval, $first_cp, $last_cp ) {
	global $exclusions, $combining_classes, $canonical_compositions, $canonical_decompositions, $compatibility_decompositions, $raw_decompositions;

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

		$raw_decompositions[ $code ] = array( $decomp, $canonic );
	}
}

$combining_classes = $canonical_compositions = $canonical_decompositions = $compatibility_decompositions = $raw_decompositions = array();

// Parse the main unicode file.

$filename = $datadirname . '/UnicodeData.txt';
$file = $dirdirname . '/' . $filename;
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

// Lastly create the NFKC case folding array.

// Open the derived normalization properties file.

$filename = $datadirname . '/DerivedNormalizationProps.txt';
$file = $dirdirname . '/' . $filename;
error_log( "$basename: reading file=$file" );

// Read the file.

if ( false === ( $get = file_get_contents( $file ) ) ) {
	/* translators: %s: file name */
	$error = sprintf( __( 'Could not read derived normalization properties file "%s"', 'unfc-normalize' ), $file );
	error_log( $error );
	return $error;
}

$lines = array_map( 'unfc_get_cb', explode( "\n", $get ) ); // Strip newlines.

// Parse the file, creating array of NFKC_CF upper to lowercase codepoints.

$kc_case_foldings = array();
$in = false;
$line_num = 0;
foreach ( $lines as $line ) {
	$line_num++;
	$line = trim( $line );
	if ( '' === $line ) {
		continue;
	}
	if ( ! preg_match( '/^([0-9A-F]{4,}(?:\.\.[0-9A-F]{4,})?) *; NFKC_CF;([ 0-9A-F]+)/', $line, $matches ) ) {
		continue;
	}

	$lower = trim( $matches[2] );
	if ( '' !== $lower ) {
		$lowers = explode( ' ', trim( $matches[2] ) );

		$lowers = array_map( 'hexdec', $lowers );
		$lowers = array_map( 'unfc_utf8_chr', $lowers );
		$lower = implode( '', $lowers ); // Leave unnormalized.
	}
	$upper = $matches[1];
	if (false !== strpos($upper, '..')) {
		$range = explode( '..', $matches[1] );
		$first_cp = hexdec( $range[0] );
		$last_cp = hexdec( $range[1] );
		for ( $cp = $first_cp; $cp <= $last_cp; $cp++ ) {
			if ($cp < 0xE0000 || $cp > 0xE0FFF) { // Treat this block, which goes to zero-length string, specially to lessen file size.
				$kc_case_foldings[ unfc_utf8_chr( $cp ) ] = $lower;
			}
		}
	} else {
		$kc_case_foldings[ unfc_utf8_chr( hexdec( $upper ) ) ] = $lower;
	}
}

//error_log( "combining_classes(" . count( $combining_classes ) . ")=" . print_r( $combining_classes, true ) );
//error_log( "canonical_compositions(" . count( $canonical_compositions ) . ")=" . print_r( $canonical_compositions, true ) );
//error_log( "canonical_decompositions(" . count( $canonical_decompositions ) . ")=" . print_r( $canonical_decompositions, true ) );
//error_log( "compatibility_decompositions(" . count( $compatibility_decompositions ) . ")=" . print_r( $compatibility_decompositions, true ) );
//error_log( "raw_decompositions(" . count( $raw_decompositions ) . ")=" . print_r( $raw_decompositions, true ) );
//error_log( "kc_case_foldings(" . count( $kc_case_foldings ) . ")=" . print_r( $kc_case_foldings, true ) );

function out_esc( $str ) {
	return str_replace( array( '\\', '\'' ), array( '\\\\', '\\\'', ), $str );
}

// Output.

$out = array();
$out[] =  '<?php';
$out[] = '';
$out[] = 'return array(';

foreach ( $combining_classes as $code => $class ) {
	$out[] = '  \'' . $code . '\' => ' . $class . ',';
}
$out[] = ');';
$out[] = '';

file_put_contents( $outdirname . '/combiningClass' . $filename_suffix . '.php', implode( "\n", $out ) );

$out = array();
$out[] =  '<?php';
$out[] = '';
$out[] = 'return array(';

foreach ( $canonical_compositions as $decomp => $code ) {
	$out[] = '  \'' . $decomp . '\' => \'' . $code . '\',';
}
$out[] = ');';
$out[] = '';

file_put_contents( $outdirname . '/canonicalComposition' . $filename_suffix . '.php', implode( "\n", $out ) );

$out = array();
$out[] =  '<?php';
$out[] = '';
$out[] = 'return array(';

foreach ( $canonical_decompositions as $code => $decomp ) {
	$out[] = '  \'' . $code . '\' => \'' . $decomp . '\',';
}
$out[] = ');';
$out[] = '';

file_put_contents( $outdirname . '/canonicalDecomposition' . $filename_suffix . '.php', implode( "\n", $out ) );

$out = array();
$out[] =  '<?php';
$out[] = '';
$out[] = 'return array(';

foreach ( $compatibility_decompositions as $code => $decomp ) {
	$out[] = '  \'' . out_esc( $code ) . '\' => \'' . out_esc( $decomp ) . '\',';
}
$out[] = ');';
$out[] = '';

file_put_contents( $outdirname . '/compatibilityDecomposition' . $filename_suffix . '.php', implode( "\n", $out ) );

$out = array();
$out[] =  '<?php';
$out[] = '';
$out[] = 'return array(';

foreach ( $raw_decompositions as $code => $entry ) {
	if ( $entry[1] ) {
		$out[] = '  \'' . out_esc( $code ) . '\' => array(\'' . out_esc( $entry[0] ) . '\'),';
	} else {
		$out[] = '  \'' . out_esc( $code ) . '\' => \'' . out_esc( $entry[0] ) . '\',';
	}
}
$out[] = ');';
$out[] = '';

file_put_contents( $outdirname . '/rawDecomposition' . $filename_suffix . '.php', implode( "\n", $out ) );

$out = array();
$out[] =  '<?php';
$out[] = '';
$out[] = 'return array(';

foreach ( $kc_case_foldings as $upper => $lower ) {
	$out[] = '  \'' . out_esc( $upper ) . '\' => \'' . out_esc( $lower ) . '\',';
}
$out[] = ');';
$out[] = '';

file_put_contents( $outdirname . '/kcCaseFolding' . $filename_suffix . '.php', implode( "\n", $out ) );
