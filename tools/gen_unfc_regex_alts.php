<?php
/**
 * Output "Symfony/unfc_regex_alts.php", generating regular expression alternatives defines
 * from the UCD derived normalization properties file "DerivedNormalizationProps.txt"
 * and the derived combining class file "DerivedCombiningClass.txt".
 *
 * Requires data directory (default "tests/UCD-<unicode-version>") containing UCD Unicode data files:
 *   https://www.unicode.org/Public/12.1.0/ucd/DerivedNormalizationProps.txt
 *   https://www.unicode.org/Public/12.1.0/ucd/extracted/DerivedCombiningClass.txt
 *
 * unzip tests/UCD-12.1.0.zip DerivedNormalizationProps.txt extracted/DerivedCombiningClass.txt -d tests/UCD-12.1.0
 *
 * For unit testing need to set UNFC_DEBUG to generate Unicode regexs and dumps in "tests/unfc_regex_alts_u-debug.php".
 *   UNFC_DEBUG=1 php tools/gen_unfc_regex_alts.php
 */

$basename = basename( __FILE__ );
$dirname = dirname( __FILE__ );
$dirdirname = dirname( $dirname );
$subdirname = basename( $dirname );

require $dirname . '/functions.php';

$opts = getopt( 'u:d:o:p:' );
$unicode_version = isset( $opts['u'] ) ? $opts['u'] : '12.1.0'; // Unicode version number.
$datadirname = isset( $opts['d'] ) ? $opts['d'] : ( 'tests/UCD-' . $unicode_version ); // Where to load Unicode data files from.
$outdirname = isset( $opts['o'] ) ? $opts['o'] : 'Symfony'; // Where to put output.
$prefix = isset( $opts['p'] ) ? $opts['p'] : 'UNFC_';

// Open the properties file.

$filename_props = $datadirname . '/DerivedNormalizationProps.txt';
$file = $dirdirname . '/' . $filename_props;
error_log( "$basename: reading file=$file" );

// Read the file.

if ( false === ( $get = file_get_contents( $file ) ) ) {
	error_log( $error = "$basename: ERROR: Could not read UCD derived normalization properties file \"$file\"" );
	exit( $error . PHP_EOL );
}

$lines = array_map( 'unfc_get_cb', explode( "\n", $get ) ); // Strip carriage returns.

// Parse the file, creating arrays of NO or MAYBE unicode codepoints.

$idx_strs = array(
	'nfd_noes' => '# NFD_Quick_Check=No',
	'nfc_noes' => '# NFC_Quick_Check=No',
	'nfc_maybes' => '# NFC_Quick_Check=Maybe',
	'nfkd_noes' => '# NFKD_Quick_Check=No',
	'nfkc_noes' => '# NFKC_Quick_Check=No',
	'nfkc_maybes' => '# NFKC_Quick_Check=Maybe',
);

// Only interested in NFC stuff for the mo.
$out_idxs = array(
	'nfc_noes',
	/*
	'nfc_maybes',
	'reorders',
	'nfc_maybes_reorders',
	*/
	'nfc_noes_maybes_reorders',
);

foreach ( $idx_strs as $idx => $str ) {
	$codepoints[ $idx ] = array();
}

$haves = array();
$in = false;
$line_num = 0;
foreach ( $lines as $line ) {
	$line_num++;
	$line = trim( $line );
	if ( '' === $line ) {
		continue;
	}
	if ( ! $in ) {
		if ( 0 === strpos( $line, $idx_strs['nfd_noes'] ) ) {
			$idx = 'nfd_noes';
		} elseif ( 0 === strpos( $line, $idx_strs['nfc_noes'] ) ) {
			$idx = 'nfc_noes';
		} elseif ( 0 === strpos( $line, $idx_strs['nfc_maybes'] ) ) {
			$idx = 'nfc_maybes';
		} elseif ( 0 === strpos( $line, $idx_strs['nfkd_noes'] ) ) {
			$idx = 'nfkd_noes';
		} elseif ( 0 === strpos( $line, $idx_strs['nfkc_noes'] ) ) {
			$idx = 'nfkc_noes';
		} elseif ( 0 === strpos( $line, $idx_strs['nfkc_maybes'] ) ) {
			$idx = 'nfkc_maybes';
		} else {
			continue;
		}
		$in = true;
		$haves[ $idx ] = true;
	} else {
		if ( '#' === $line[0] ) {
			if ( 0 === strpos( $line, '# =====' ) ) {
				if ( $in ) {
					$in = false;
					if ( count( $haves ) === count( $idx_strs ) ) {
						break;
					}
				}
			}
			continue;
		}
		$parts = explode( ';', $line );
		$code = trim( $parts[0] );

		$codes = explode( '..', $code );
		if ( count( $codes ) > 1 ) {
			$begin = hexdec( $codes[0] );
			$end = hexdec( $codes[1] );
			for ( $i = $begin; $i <= $end; $i++ ) {
				$codepoints[ $idx ][] = $i;
			}
		} else {
			$codepoints[ $idx ][] = hexdec( $code );
		}
	}
}
if ( count( $haves ) !== count( $idx_strs ) ) {
	error_log( $error = "$basename: ERROR: Missing NO or MAYBE codepoints in UCD derived normalization properties file \"$file\"" );
	exit( $error . PHP_EOL );
}

// Open the combining file.

$filename_combines = $datadirname . '/extracted/DerivedCombiningClass.txt';
$file = $dirdirname . '/' . $filename_combines;
error_log( "$basename: reading file=$file" );

// Read the file.

if ( false === ( $get = file_get_contents( $file ) ) ) {
	error_log( $error = "$basename: ERROR: Could not read UCD derived combining class file \"$file\"" );
	exit( $error . PHP_EOL );
}

$lines = array_map( 'unfc_get_cb', explode( "\n", $get ) ); // Strip carriage returns.

// Parse the file, creating array of codepoint => class.

$idx = 'reorders';
$codepoints[ $idx ] = array();
$in = false;
$line_num = 0;
foreach ( $lines as $line ) {
	$line_num++;
	$line = trim( $line );
	if ( '' === $line ) {
		continue;
	}
	if ( ! $in ) {
		if ( 0 !== strpos( $line, '# Canonical_Combining_Class=' ) ) {
			continue;
		}
		if ( '# Canonical_Combining_Class=Not_Reordered' !== $line ) {
			$in = true;
		}
	} else {
		if ( '#' === $line[0] ) {
			continue;
		}
		$parts = explode( ';', $line );
		if ( 2 !== count( $parts ) ) {
			continue;
		}
		$code = trim( $parts[0] );
		if ( 0 < ( $pos = strpos( $parts[1], '#' ) ) ) {
			$parts[1] = substr( $parts[1], 0, $pos - 1 );
		}

		$codes = explode( '..', $code );
		if ( count( $codes ) > 1 ) {
			$begin = hexdec( $codes[0] );
			$end = hexdec( $codes[1] );
			for ( $i = $begin; $i <= $end; $i++ ) {
				$codepoints[ $idx ][] = $i;
			}
		} else {
			$codepoints[ $idx ][] = hexdec( $code );
		}
	}
}

// Put maybes and reorders in one regex.
$codepoints['nfc_maybes_reorders'] = array_values( array_unique( array_merge( $codepoints['nfc_maybes'], $codepoints['reorders'] ) ) );
// Put noes, maybes and reorders in one regex.
$codepoints['nfc_noes_maybes_reorders'] = array_values( array_unique( array_merge( $codepoints['nfc_noes'], $codepoints['nfc_maybes_reorders'] ) ) );

$regex_alts = array();

foreach ( $out_idxs as $idx ) {
	sort( $codepoints[ $idx ] );

	// Calculate the UTF-8 byte sequence ranges from the unicode codepoints.

	$ranges = unfc_utf8_ranges_from_codepoints( $codepoints[ $idx ] );

	// Generate the regular expression alternatives.

	$regex_alts[ $idx ] = unfc_utf8_regex_alts( $ranges );
}

// Output.

$out = array();
$out[] =  '<?php';
$out[] = '/**';
$out[] = ' * Generated by "' . $subdirname . '/' . $basename . '". Don\'t edit!';
$out[] = ' * Alternatives generated from "' . $filename_props . '" and "' . $filename_combines . '".';
$out[] = ' * Quick check NO and MAYBE codepoints, and reordered codepoints.';
$out[] = ' */';

foreach ( $out_idxs as $idx ) {
	$IDX = strtoupper( $idx );
	$out[] = '';
	$out[] = "define( '{$prefix}REGEX_ALTS_{$IDX}', '" . $regex_alts[ $idx ] . "' );";
	$out[] = "define( '{$prefix}REGEX_{$IDX}', '/' . {$prefix}REGEX_ALTS_{$IDX} . '/' );";
}
$out[] = '';

if ( ! file_put_contents( $file = $outdirname . '/unfc_regex_alts.php', implode( "\n", $out ) ) ) {
	exit( "$basename: ERROR: Could not write PHP file \"$file\"" . PHP_EOL );
}

if ( ! empty( $_SERVER['UNFC_DEBUG'] ) ) { // Set via command line "UNFC_DEBUG=1 php __FILE__".
	$out = array();
	$out[] =  '<?php';
	$out[] = '/**';
	$out[] = ' * Generated by "' . $subdirname . '/' . $basename . '". Don\'t edit!';
	$out[] = ' * The following unicode versions of the global variable regex alternatives and dumps are for testing/debugging purposes only.';
	$out[] = ' */';
	foreach ( $out_idxs as $idx ) {
		// Unicode (UCS) regular expression charset.
		$regex_alts = unfc_unicode_regex_chars_from_codepoints( $codepoints[ $idx ] );

		$IDX = strtoupper( $idx );
		$out[] = '';
		$out[] = "define( '{$prefix}REGEX_ALTS_{$IDX}_U', '" . $regex_alts . "' );";
		$out[] = "define( '{$prefix}REGEX_{$IDX}_U', '/[' . {$prefix}REGEX_ALTS_{$IDX}_U . ']/u' );";

		$out[] = '';
		$out[] = "global \$unfc_{$idx};";
		$out[] = "\$unfc_{$idx} = array( // " . count( $codepoints[ $idx ] ) . ' codepoints';
		foreach ( array_chunk( $codepoints[ $idx ], 20 ) as $codepoints_chunk ) {
			$out[] = "\t" . implode( ', ', array_map( 'unfc_unicode_fmt', $codepoints_chunk ) ) . ',';
		}
		$out[] = ');';
	}
	$out[] = '';

	if ( ! file_put_contents( $file = 'tests/unfc_regex_alts_u-debug.php', implode( "\n", $out ) ) ) {
		exit( "$basename: ERROR: Could not write PHP file \"$file\"" . PHP_EOL );
	}
}
