<?php
/**
 * Port of https://github.com/nicolas-grekas/Patchwork-UTF8/blob/master/src/Patchwork/Utf8/Compiler.php
 *
 * Output the various mapping classes used by the UNFC_Normalizer class to the "Symfony/Resources/unidata" directory.
 *
 * Requires data directory (default "tests/UCD-<unicode-version>") containing UCD Unicode data files:
 *   https://www.unicode.org/Public/12.1.0/ucd/UnicodeData.txt
 *   https://www.unicode.org/Public/12.1.0/ucd/CompositionExclusions.txt
 *   https://www.unicode.org/Public/12.1.0/ucd/DerivedNormalizationProps.txt
 *
 * unzip tests/UCD-12.1.0.zip UnicodeData.txt CompositionExclusions.txt DerivedNormalizationProps.txt -d tests/UCD-12.1.0
 */

$basename = basename( __FILE__ );
$dirname = dirname( __FILE__ );
$dirdirname = dirname( $dirname );

require $dirname . '/functions.php';

$opts = getopt( 'u:d:o:s' );
$unicode_version = isset( $opts['u'] ) ? $opts['u'] : '12.1.0'; // Unicode version number.
$datadirname = isset( $opts['d'] ) ? $opts['d'] : ( 'tests/UCD-' . $unicode_version ); // Where to load Unicode data files from.
$outdirname = isset( $opts['o'] ) ? $opts['o'] : 'Symfony/Resources/unidata'; // Where to put output.
$suffix = isset( $opts['s'] ); // If set will add Unicode version number as suffix to filename (before ".php" that is).

$filename_suffix = $suffix ? ( '-' . $unicode_version ) : '';

// First create the decomposition exclusion array.

// Open the exclusions file.

$filename = $datadirname . '/CompositionExclusions.txt';
$file = $dirdirname . '/' . $filename;
error_log( "$basename: reading file=$file" );

// Read the file.

if ( false === ( $get = file_get_contents( $file ) ) ) {
	error_log( $error = "$basename: ERROR: Could not read UCD composition exclusions file \"$file\"" );
	exit( $error . PHP_EOL );
}

$lines = array_map( 'unfc_get_cb', explode( "\n", $get ) ); // Strip carriage returns.

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

	$combining_class = intval( $parts[UNFC_UCD_CANONICAL_COMBINING_CLASS] );
	if ( $combining_class ) {
		$combining_classes[ $code ] = $combining_class;
	}

	$decomp = $parts[UNFC_UCD_DECOMPOSITION_TYPE_MAPPING];
	if ( $decomp ) {
		$canonic = '<' !== $decomp[0];
		if ( ! $canonic ) {
			$decomp = preg_replace( '/^<.*> /', '', $decomp );
		}
		$decomp = unfc_utf8_entry( $decomp, $char_cnt );

		if ( $canonic ) {
			$canonical_decompositions[ $code ] = $decomp;
			$exclude = 1 === $char_cnt || isset( $exclusions[ $code ] );
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
	error_log( $error = "$basename: ERROR: Could not parse UCD unicode data file \"$file\"" );
	exit( $error . PHP_EOL );
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
	error_log( $error = "$basename: ERROR: Could not read UCD derived normalization properties file \"$file\"" );
	exit( $error . PHP_EOL );
}

$lines = array_map( 'unfc_get_cb', explode( "\n", $get ) ); // Strip carriage returns.

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

	$case_fold = unfc_utf8_entry( trim( $matches[2] ) ); // Leave unnormalized.
	if ( false !== strpos( $matches[1], '..' ) ) {
		$codes = explode( '..', $matches[1] );
		$first_cp = hexdec( $codes[0] );
		$last_cp = hexdec( $codes[1] );
		for ( $cp = $first_cp; $cp <= $last_cp; $cp++ ) {
			if ( $cp < 0xE0000 || $cp > 0xE0FFF ) { // Treat this block, which goes to zero-length string, specially to lessen file size.
				$kc_case_foldings[ unfc_utf8_chr( $cp ) ] = $case_fold;
			}
		}
	} else {
		$cp = hexdec( $matches[1] );
		if ( $cp < 0xE0000 || $cp > 0xE0FFF ) { // Treat this block, which goes to zero-length string, specially to lessen file size.
			$kc_case_foldings[ unfc_utf8_chr( $cp ) ] = $case_fold;
		}
	}
}

// Output.

if ( ! unfc_output_array_file( $combining_classes, $file = $outdirname . '/combiningClass' . $filename_suffix . '.php', $unicode_version, 'UnicodeData.txt' ) ) {
	error_log( $error = "$basename: ERROR: Could not write PHP file \"$file\"" );
	exit( $error . PHP_EOL );
}
if ( ! unfc_output_array_file( $canonical_compositions, $file = $outdirname . '/canonicalComposition' . $filename_suffix . '.php', $unicode_version, 'UnicodeData.txt' ) ) {
	error_log( $error = "$basename: ERROR: Could not write PHP file \"$file\"" );
	exit( $error . PHP_EOL );
}
if ( ! unfc_output_array_file( $canonical_decompositions, $file = $outdirname . '/canonicalDecomposition' . $filename_suffix . '.php', $unicode_version, 'UnicodeData.txt' ) ) {
	error_log( $error = "$basename: ERROR: Could not write PHP file \"$file\"" );
	exit( $error . PHP_EOL );
}
if ( ! unfc_output_array_file( $compatibility_decompositions, $file = $outdirname . '/compatibilityDecomposition' . $filename_suffix . '.php', $unicode_version, 'UnicodeData.txt' ) ) {
	error_log( $error = "$basename: ERROR: Could not write PHP file \"$file\"" );
	exit( $error . PHP_EOL );
}
if ( ! unfc_output_array_file( $raw_decompositions, $file = $outdirname . '/rawDecomposition' . $filename_suffix . '.php', $unicode_version, 'UnicodeData.txt' ) ) {
	error_log( $error = "$basename: ERROR: Could not write PHP file \"$file\"" );
	exit( $error . PHP_EOL );
}
if ( ! unfc_output_array_file( $kc_case_foldings, $file = $outdirname . '/kcCaseFolding' . $filename_suffix . '.php', $unicode_version, 'DerivedNormalizationProps.txt' ) ) {
	error_log( $error = "$basename: ERROR: Could not write PHP file \"$file\"" );
	exit( $error . PHP_EOL );
}
