<?php
/**
 * Utility functions for tools.
 */

define( 'UNFC_UTF8_MAX', 0x10ffff ); // Maximum legal unicode codepoint in UTF-8.

/**
 * Unicode codepoint as UTF-8 string.
 * Based on https://github.com/symfony/polyfill/blob/master/tests/Intl/Normalizer/NormalizerTest.php NormalizerTest::chr().
 */
function unfc_utf8_chr( $c ) {
	if ( $c > UNFC_UTF8_MAX || $c < 0 ) {
		$c = UNFC_UTF8_MAX;
	}
	if ( $c < 0x80 ) {
		return chr( $c );
	}
	if ( $c < 0x800 ) {
		return chr( 0xc0 | $c >> 6 ) . chr( 0x80 | $c & 0x3f );
	}
	if ( $c < 0x10000 ) {
		return chr( 0xe0 | $c >> 12 ) . chr( 0x80 | $c >> 6 & 0x3f ) . chr( 0x80 | $c & 0x3f );
	}

	return chr( 0xf0 | $c >> 18 ) . chr( 0x80 | $c >> 12 & 0x3f ) . chr( 0x80 | $c >> 6 & 0x3f ) . chr( 0x80 | $c & 0x3f );
}

/**
 * UTF-8 character as unicode codepoint.
 */
function unfc_unicode_chr( $c ) {
	$len = unfc_utf8_chr_len( $c );
	if ( 0 === $len ) {
		return 0;
	}
	if ( 1 === $len ) {
		return ord( $c[0] );
	}
	$uchr = unpack( 'C*', substr( $c, 0, $len ) );
	if ( 2 === $len ) {
		return ( ( $uchr[1] & 0x1F ) << 6 ) + ( $uchr[2] & 0x3F );
	}
	if ( 3 === $len ) {
		return ( ( $uchr[1] & 0x0F ) << 12 ) + ( ( $uchr[2] & 0x3F ) << 6 ) + ( $uchr[3] & 0x3F );
	}
	return ( ( $uchr[1] & 0x07 ) << 18 ) + ( ( $uchr[2] & 0x3F ) << 12 ) + ( ( $uchr[3] & 0x3F ) << 6 ) + ( $uchr[4] & 0x3F );
}

/**
 * Length of UTF-8 character.
 */
function unfc_utf8_chr_len( $c ) {
	static $ulenMask = array( "\x00" => 1, "\x10" => 1, "\x20" => 1, "\x30" => 1, "\x40" => 1, "\x50" => 1, "\x60" => 1, "\x70" => 1, "\xC0" => 2, "\xD0" => 2, "\xE0" => 3, "\xF0" => 4 );
	if ( '' === $c ) {
		return 0;
	}
	return $ulenMask[ $c[0] & "\xF0" ];
}

/**
 * Unicode codepoint as array of 4 UTF-8 ints.
 * Based on https://github.com/symfony/polyfill/blob/master/tests/Intl/Normalizer/NormalizerTest.php NormalizerTest::chr().
 */
function unfc_utf8_ints( $c ) {
	if ( $c > UNFC_UTF8_MAX || $c < 0 ) {
		$c = UNFC_UTF8_MAX;
	}
	if ( $c < 0x80 ) {
		return array( 0, 0, 0, $c );
	}
	if ( $c < 0x800 ) {
		return array( 0, 0, 0xc0 | $c >> 6, 0x80 | $c & 0x3f );
	}
	if ( $c < 0x10000 ) {
		return array( 0, 0xe0 | $c >> 12, 0x80 | $c >> 6 & 0x3f, 0x80 | $c & 0x3f );
	}

	return array( 0xf0 | $c >> 18, 0x80 | $c >> 12 & 0x3f, 0x80 | $c >> 6 & 0x3f, 0x80 | $c & 0x3f );
}

/**
 * Given 2 unicode codepoints, $c1 <= $c2, calculate the UTF-8 ranges between them.
 */
function unfc_utf8_ranges( &$ranges, $c1, $c2 ) {
	return unfc_utf8_4range( $ranges, unfc_utf8_ints( $c1 ), unfc_utf8_ints( $c2 ) );
}

/**
 * Calculate ranges between UTF-8 ints arrays.
 */
function unfc_utf8_4range( &$ranges, $ints1, $ints2, $i = 0 ) {
	static $minmaxs = array(
		array( array( 0xf0, 0x80, 0x80, 0x80 ), array( 0xf4, 0xbf, 0xbf, 0xbf ) ),
		array( array( 0x00, 0xe0, 0x80, 0x80 ), array( 0x00, 0xef, 0xbf, 0xbf ) ),
		array( array( 0x00, 0x00, 0xc2, 0x80 ), array( 0x00, 0x00, 0xdf, 0xbf ) ),
		array( array( 0x00, 0x00, 0x00, 0x00 ), array( 0x00, 0x00, 0x00, 0x7f ) ),
	);

	if ( 3 === $i ) {
		$ranges[] = unfc_utf8_range( $ints1, $ints2, $i );
		return $ranges;
	}

	if ( $ints1[ $i ] === $ints2[ $i ] ) { // If main index the same, just do lower indexes.
		unfc_utf8_4range( $ranges, $ints1, $ints2, $i + 1 );
		return $ranges;
	}

	if ( 0 === $ints1[ $i ] ) { // Need to span byte range.
		unfc_utf8_4range( $ranges, $ints1, unfc_utf8_replace( $ints1, $i + 1, $minmaxs[ $i + 1 ][1] ), $i + 1 ); // Range up to highest previous byte range.
		unfc_utf8_4range( $ranges, $minmaxs[ $i ][0], $ints2 ); // Range from lowest current byte range.
		return $ranges;
	}

	// Do first part of range.
	if ( 0x80 === $ints1[ $i + 1 ] ) { // Optimize to not output separate range if 1st int array at lowest value.

		// Check if can further optimize when both int arrays at lowest and highest respectively.
		if ( array_slice( $minmaxs[ $i ][0], $i + 1 ) === array_slice( $ints1, $i + 1 ) && array_slice( $minmaxs[ $i ][1], $i + 1 ) === array_slice( $ints2, $i + 1 ) ) {
			$ranges[] = unfc_utf8_replace( $ints1, $i, array( array( $ints1[ $i ], $ints2[ $i ] ) ), null, 0 ); // One part.
			return $ranges;
		}

		$ints1[ $i ] -= 1; // Fall through to processing of middle & last part with $ints1[ $i ] + 1.
	} else {
		unfc_utf8_4range( $ranges, $ints1, unfc_utf8_replace( $ints1, $i + 1, array(), null, 0, 0xbf ), $i + 1 );
	}

	// Middle and last part.
	if ( $ints1[ $i ] + 1 === $ints2[ $i ] ) { // Optimize to not output separate range if same.
		unfc_utf8_4range( $ranges, unfc_utf8_replace( $ints2, $i + 1, array(), null, 0, 0x80 ), $ints2, $i + 1 );

	} elseif ( $ints1[ $i ] + 1 === $ints2[ $i ] - 1 ) { // Optimize to not insert inner range if contiguous.
		$ranges[] = unfc_utf8_replace( $ints1, $i, array( $ints1[ $i ] + 1 ), null, 0 );
		unfc_utf8_4range( $ranges, unfc_utf8_replace( $ints2, $i + 1, array(), null, 0, 0x80 ), $ints2, $i + 1 );

	} else {
		if ( array_slice( $minmaxs[ $i ][1], $i + 1 ) === array_slice( $ints2, $i + 1 ) ) { // Optimize to not output separate range if 2nd int array at highest value.
			$ranges[] = unfc_utf8_replace( $ints1, $i, array( array( $ints1[ $i ] + 1, $ints2[ $i ] ) ), null, 0 );

		} else { // The full Monty.
			$ranges[] = unfc_utf8_replace( $ints1, $i, array( array( $ints1[ $i ] + 1, $ints2[ $i ] - 1 ) ), null, 0 );
			unfc_utf8_4range( $ranges, unfc_utf8_replace( $ints2, $i + 1, array(), null, 0, 0x80 ), $ints2, $i + 1 );
		}
	}

	return $ranges;
}

/**
 * Helper for unfc_utf8_4range() to insert values into ints array.
 */
function unfc_utf8_replace( $ints1, $i1 = 0, $ints2 = array(), $len = null, $i2 = null, $fill = array( 0x80, 0xbf ) ) {

	if ( null === $len ) {
		$len = 4 - $i1;
	}
	if ( null === $i2 ) {
		$i2 = $i1;
	}
	$ints2 = array_slice( $ints2, $i2, $len );
	$cnt2 = count( $ints2 );
	if ( $cnt2 < $len ) {
		$ints2 = array_merge( $ints2, array_fill( 0, $len - $cnt2, $fill ) );
	}

	array_splice( $ints1, $i1, $len, $ints2 ); // Length of array shouldn't change.

	return $ints1;
}

/**
 * Helper for unfc_utf8_4range(). Given UTF-8 ints arrays, if there's a range between them at $i then insert (else just return first).
 */
function unfc_utf8_range( $ints1, $ints2, $i = 0 ) {

	if ( $ints1[ $i ] === $ints2[ $i ] ) {
		return $ints1;
	}

	array_splice( $ints1, $i, 1, array( array( $ints1[ $i ], $ints2[ $i ] ) ) );

	return $ints1;
}

/**
 * Format a unicode codepoint.
 */
function unfc_unicode_fmt( $c ) {
	return sprintf( '0x%x', $c ); // Use lowercase to avoid 8/B similarity.
}

/**
 * Format a unicode codepoint for preg_XXX().
 */
function unfc_unicode_preg_fmt( $c ) {
	return sprintf( $c < 0x100 ? '\\x%02x' : '\\x{%x}', $c );
}

/**
 * Format a UTF-8 byte for preg_XXX().
 */
function unfc_utf8_preg_fmt( $c ) {
	return sprintf( '\\x%02x', $c );
}

/**
 * Format a UTF-8 range entry for preg_XXX(). A range entry can be either an int (single value) or an array of 2 ints (interval).
 */
function unfc_utf8_preg_fmt_range_entry( $range_entry ) {
	if ( is_int( $range_entry ) ) {
		return unfc_utf8_preg_fmt( $range_entry );
	}

	if ( $range_entry[0] === $range_entry[1] ) {
		return unfc_utf8_preg_fmt( $range_entry[0] );
	}

	if ( $range_entry[0] + 1 === $range_entry[1] ) {
		return unfc_utf8_preg_fmt( $range_entry[0] ) . unfc_utf8_preg_fmt( $range_entry[1] );
	}

	return unfc_utf8_preg_fmt( $range_entry[0] ) . '-' . unfc_utf8_preg_fmt( $range_entry[1] );
}

/**
 * Add range to trie array.
 */
function unfc_utf8_trie( &$trie, $range, $idx = 0, $parent = null, $fmt = null ) {
	$cnt = count( $range );

	if ( $idx === $cnt - 1 ) {
		if ( $parent ) {
			// If at lowest index, append formatted range to parent rather than add trie.
			if ( is_array( $parent[ $fmt ] ) ) {
				$parent[ $fmt ] = '';
			}
			$parent[ $fmt ] .= unfc_utf8_preg_fmt_range_entry( $range[ $idx ] );
		} else {
			if ( ! isset( $trie[''] ) ) {
				$trie[''] = '';
			}
			$trie[''] .= unfc_utf8_preg_fmt_range_entry( $range[ $idx ] );
		}
	} elseif ( $idx < $cnt && is_array( $trie ) ) {
		// Create key entry (trie).
		$fmt = unfc_utf8_preg_fmt_range_entry( $range[ $idx ] );
		if ( ! isset( $trie[ $fmt ] ) ) {
			$trie[ $fmt ] = array();
		}
		unfc_utf8_trie( $trie[ $fmt ], $range, $idx + 1, $trie, $fmt );
	}
}

/**
 * Calculate regular expression alternatives from trie array.
 */
function unfc_utf8_trie_regex_alts( $trie ) {
	$ret = array();

	foreach ( $trie as $key => $val ) {
		if ( substr_count( $key, '\\' ) > 1 ) { // If more than one character, put in character class.
			$key = '[' . $key . ']';
		}
		if ( is_array( $val ) ) { // Recurse.
			if ( count( $val ) > 1 ) {
				$ret[] = $key . '(?:' . implode( '|', unfc_utf8_trie_regex_alts( $val ) ) . ')';
 			} else {
				$ret[] = $key . implode( '', unfc_utf8_trie_regex_alts( $val ) );
			}
		} else { // At lowest level.
			if ( substr_count( $val, '\\' ) > 1 ) { // If more than one character, put in character class.
				$val = '[' . $val . ']';
			}
			$ret[] = $key . $val;
		}
	}

	return $ret;
}

/**
 * Return regular expression alternatives for given UTF-8 ranges.
 */
function unfc_utf8_regex_alts( $ranges ) {
	// Convert ranges to trie array to make generating regex easier.
	$trie = array();

	foreach ( $ranges as $range ) {
		// Find first non-empty index of range (if any).
		for ( $idx = 0, $cnt = count( $range ); $idx < $cnt && ! $range[ $idx ]; $idx++ );
		if ( $cnt && $idx && $idx === $cnt ) {
			$idx--;
		}

		unfc_utf8_trie( $trie, $range, $idx );
	}

	return implode( '|', unfc_utf8_trie_regex_alts( $trie ) );
}

/**
 * Calculate the UTF-8 byte sequence ranges from unicode codepoints.
 */
function unfc_utf8_ranges_from_codepoints( $codepoints ) {
	$ranges = array();

	if ( ! $codepoints ) {
		return $ranges;
	}
	$last = array_shift( $codepoints );
	$first = $last;
	$carry = null;
	foreach ( $codepoints as $codepoint ) {
		if ( $codepoint === $last + 1 ) {
			$carry = $codepoint;
		} else {
			if ( null === $carry ) {
				$ranges[] = unfc_utf8_ints( $last );
			} else {
				if ( $first + 1 === $carry ) {
					$ranges[] = unfc_utf8_ints( $first );
					$ranges[] = unfc_utf8_ints( $carry );
				} else {
					unfc_utf8_ranges( $ranges, $first, $carry );
				}
				$carry = null;
			}
			$first = $codepoint;
		}
		$last = $codepoint;
	}
	if ( null === $carry ) {
		$ranges[] = unfc_utf8_ints( $last );
	} else {
		if ( $first + 1 === $carry ) {
			$ranges[] = unfc_utf8_ints( $first );
			$ranges[] = unfc_utf8_ints( $carry );
		} else {
			unfc_utf8_ranges( $ranges, $first, $carry );
		}
	}

	return $ranges;
}

/**
 * Calculate the Unicode (UCS) ranges from unicode codepoints.
 */
function unfc_unicode_ranges_from_codepoints( $codepoints ) {
	$ranges = array();

	if ( ! $codepoints ) {
		return $ranges;
	}
	$last = array_shift( $codepoints );
	$first = $last;
	$carry = null;
	foreach ( $codepoints as $codepoint ) {
		if ( $codepoint === $last + 1 ) {
			$carry = $codepoint;
		} else {
			if ( null === $carry ) {
				$ranges[] = $last;
			} else {
				$ranges[] = array( $first, $carry );
				$carry = null;
			}
			$first = $codepoint;
		}
		$last = $codepoint;
	}
	if ( null === $carry ) {
		$ranges[] = $last;
	} else {
		$ranges[] = array( $first, $carry );
	}

	return $ranges;
}

/**
 * Calculate the Unicode (UCS) alternatives from unicode codepoints.
 */
function unfc_unicode_regex_chars_from_codepoints( $codepoints ) {
	$regex_alts = '';

	$last = array_shift( $codepoints );
	$first = $last;
	$carry = null;
	foreach ( $codepoints as $codepoint ) {
		if ( $codepoint === $last + 1 ) {
			$carry = $codepoint;
		} else {
			if ( null === $carry ) {
				$regex_alts .= unfc_unicode_preg_fmt( $last );
			} else {
				$regex_alts .= unfc_unicode_preg_fmt( $first ) . ( $first + 1 === $carry ? '' : '-' ) . unfc_unicode_preg_fmt( $carry );
				$carry = null;
			}
			$first = $codepoint;
		}
		$last = $codepoint;
	}
	if ( null === $carry ) {
		$regex_alts .= unfc_unicode_preg_fmt( $last );
	} else {
		$regex_alts .= unfc_unicode_preg_fmt( $first ) . ( $first + 1 === $carry ? '' : '-' ) . unfc_unicode_preg_fmt( $carry );
	}

	return $regex_alts;
}

// Parts fields of Unicode data file http://www.unicode.org/Public/9.0.0/ucd/UnicodeData.txt
define( 'UNFC_UCD_CODEPOINT', 0 );
define( 'UNFC_UCD_NAME', 1 );
define( 'UNFC_UCD_GENERAL_CATEGORY', 2 );
define( 'UNFC_UCD_CANONICAL_COMBINING_CLASS', 3 );
define( 'UNFC_UCD_BIDI_CLASS', 4 );
define( 'UNFC_UCD_DECOMPOSITION_TYPE_MAPPING', 5 );
define( 'UNFC_UCD_NUMERIC_TYPE_VAL1', 6 );
define( 'UNFC_UCD_NUMERIC_TYPE_VAL2', 7 );
define( 'UNFC_UCD_NUMERIC_TYPE_VAL3', 8 );
define( 'UNFC_UCD_BIDI_MIRRORED', 9 );
define( 'UNFC_UCD_UNICODE_1_NAME', 10 );
define( 'UNFC_UCD_ISO_COMMENT', 11 );
define( 'UNFC_UCD_SIMPLE_UPPERCASE_MAPPING', 12 );
define( 'UNFC_UCD_SIMPLE_LOWERCASE_MAPPING', 13 );
define( 'UNFC_UCD_SIMPLE_TITLECASE_MAPPING', 14 );

/**
 * Parse the Unicode data file http://www.unicode.org/Public/9.0.0/ucd/UnicodeData.txt
 * Calls the $callback to collect codepoints of interest in the passed-in $codepoints array, which is returned.
 * In particular, deals with intervals, calling the $callback for each codepoint in the interval.
 */
function unfc_parse_unicode_data( $file, $callback ) {

	// Read the file.

	if ( false === ( $get = file_get_contents( $file ) ) ) {
		error_log( __FUNCTION__ . "(): failed to read file \"$file\"" );
		return false;
	}

	$lines = array_map( 'unfc_get_cb', explode( "\n", $get ) ); // Strip carriage returns.

	$first = 'First>';
	$first_len_minus = -strlen( $first );
	$last = 'Last>';
	$last_len_minus = -strlen( $last );

	// Parse the file.

	$codepoints = array();
	$line_num = 0;
	$in_interval = false;
	$first_cp = 0;
	foreach ( $lines as $line ) {
		$line_num++;
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts = array_map( 'trim', explode( ';', $line ) );
		
		$name = isset( $parts[1] ) ? $parts[1] : null;

		if ( $in_interval ) {
			if ( $last === substr( $name, $last_len_minus ) ) {
				$last_cp = hexdec( $parts[0] );
				for ( $cp = $first_cp + 1; $cp <= $last_cp; $cp++ ) {
					if ( false === call_user_func_array( $callback, array( &$codepoints, $cp, $name, $parts, $in_interval, $first_cp, $last_cp ) ) ) {
						error_log( __FUNCTION__ . "(): user func fail line_num=$line_num" );
						return false;
					}
				}
			} else {
				error_log( __FUNCTION__ . "(): invalid first/last pair line_num=$line_num" );
				return false;
			}
			$in_interval = false;
		} else {
			$cp = hexdec( $parts[0] );
			if ( $first === substr( $name, $first_len_minus ) ) {
				$in_interval = true;
				$first_cp = $cp;
			}
			if ( false === call_user_func_array( $callback, array( &$codepoints, $cp, $name, $parts, $in_interval, $first_cp, 0 /*$last_cp*/ ) ) ) {
				error_log( __FUNCTION__ . "(): user func fail line_num=$line_num" );
				return false;
			}
		}
	}

	return $codepoints;
}

/**
 * Parse the Scripts file http://www.unicode.org/Public/9.0.0/ucd/Scripts.txt
 * Calls the $callback to collect codepoints of interest in the passed-in $codepoints array, which is returned.
 * In particular, deals with intervals, calling the $callback for each codepoint in the interval.
 */
function unfc_parse_scripts( $file, $callback ) {

	// Read the file.

	if ( false === ( $get = file_get_contents( $file ) ) ) {
		error_log( __FUNCTION__ . "(): failed to read file '$file'" );
		return false;
	}

	$lines = array_map( 'unfc_get_cb', explode( "\n", $get ) ); // Strip carriage returns.

	// Parse the file.

	$codepoints = array();
	$line_num = 0;
	foreach ( $lines as $line ) {
		$line_num++;
		$line = trim( $line );
		if ( '' === $line || '#' === $line[0] ) {
			continue;
		}
		$parts = explode( ';', $line );
		if ( 2 !== count( $parts ) ) {
			continue;
		}

		if ( 0 < ( $pos = strpos( $parts[1], '#' ) ) ) {
			$parts[2] = trim( substr( $parts[1], $pos + 1 ) );
			$parts[1] = substr( $parts[1], 0, $pos - 1 );
		}
		$script = trim( $parts[1] );

		$code = trim( $parts[0] );
		$codes = explode( '..', $code );
		if ( count( $codes ) > 1 ) {
			$first_cp = hexdec( $codes[0] );
			$last_cp = hexdec( $codes[1] );
			for ( $cp = $first_cp; $cp <= $last_cp; $cp++ ) {
				if ( false === call_user_func_array( $callback, array( &$codepoints, $cp, $script, $parts, true /*$in_interval*/, $first_cp, $last_cp ) ) ) {
					error_log( __FUNCTION__ . "(): user func fail line_num=$line_num" );
					return false;
				}
			}
		} else {
			$cp = hexdec( $code );
			if ( false === call_user_func_array( $callback, array( &$codepoints, $cp, $script, $parts, false /*$in_interval*/, 0 /*$first_cp*/, 0 /*$last_cp*/ ) ) ) {
				error_log( __FUNCTION__ . "(): user func fail line_num=$line_num" );
				return false;
			}
		}
	}

	return $codepoints;
}

/**
 * Strip any invalid UTF-8 sequences from string.
 */
function unfc_strip_invalid_utf8( $string ) {
	// Based on wpdb::strip_invalid_text() in "wp-includes/wp-db.php".
	// See https://www.w3.org/International/questions/qa-forms-utf-8
	// See http://stackoverflow.com/a/1401716/664741
	return preg_replace(
		'/
		 (
			(?: [\x00-\x7f]                                     # ASCII
			|   [\xc2-\xdf][\x80-\xbf]                          # non-overlong 2-byte
			|   \xe0[\xa0-\xbf][\x80-\xbf]                      # excluding overlongs
			|   [\xe1-\xec\xee\xef][\x80-\xbf][\x80-\xbf]       # straight 3-byte
			|   \xed[\x80-\x9f][\x80-\xbf]                      # excluding surrogates
			|   \xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]           # planes 1-3
			|   [\xf1-\xf3][\x80-\xbf][\x80-\xbf][\x80-\xbf]    # planes 4-15
			|   \xf4[\x80-\x8f][\x80-\xbf][\x80-\xbf]           # plane 16
			){1,40}                                             # ...one or more times
		 )
		 | .                                                    # anything else
		/x', '$1', $string );
}

/**
 * Return random UTF-8 string, with given ratio of other (default invalid) characters.
 */
function unfc_utf8_rand_ratio_str( $utf8_len = 10000, $other_ratio = 0.001, $other_strs = null, $other_is_codepoints = true ) {
	static $valid_strs = array(
		// Single-byte.
		"\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07", "\x08", "\x09", "\x0a", "\x0b", "\x0c", "\x0d", "\x0e", "\x0f", 
		"\x10", "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17", "\x18", "\x19", "\x1a", "\x1b", "\x1c", "\x1d", "\x1e", "\x1f", 
		"\x20", "\x21", "\x22", "\x23", "\x24", "\x25", "\x26", "\x27", "\x28", "\x29", "\x2a", "\x2b", "\x2c", "\x2d", "\x2e", "\x2f", 
		"\x30", "\x31", "\x32", "\x33", "\x34", "\x35", "\x36", "\x37", "\x38", "\x39", "\x3a", "\x3b", "\x3c", "\x3d", "\x3e", "\x3f", 
		"\x40", "\x41", "\x42", "\x43", "\x44", "\x45", "\x46", "\x47", "\x48", "\x49", "\x4a", "\x4b", "\x4c", "\x4d", "\x4e", "\x4f", 
		"\x50", "\x51", "\x52", "\x53", "\x54", "\x55", "\x56", "\x57", "\x58", "\x59", "\x5a", "\x5b", "\x5c", "\x5d", "\x5e", "\x5f", 
		"\x60", "\x61", "\x62", "\x63", "\x64", "\x65", "\x66", "\x67", "\x68", "\x69", "\x6a", "\x6b", "\x6c", "\x6d", "\x6e", "\x6f", 
		"\x70", "\x71", "\x72", "\x73", "\x74", "\x75", "\x76", "\x77", "\x78", "\x79", "\x7a", "\x7b", "\x7c", "\x7d", "\x7e", "\x7f", 
		// Double-byte.
		"\xc2\x80", "\xc3\xbf", "\xc4\x80", "\xc5\xa2", "\xc6\xb3", "\xc7\x82", "\xc8\x93", "\xc9\xa4", "\xca\xb5", "\xcb\x83", "\xcc\x94", "\xcd\xa5", "\xce\xb6", "\xcf\x84",
		"\xd0\x80", "\xd1\xbf", "\xd2\x85", "\xd3\x96", "\xd4\xa7", "\xd5\xb8", "\xd6\xb9", "\xd7\x8a", "\xd8\x9b", "\xd9\xac", "\xda\xbd", "\xdb\x8e", "\xdc\x9f", "\xdd\xa0",
		"\xde\xb1", "\xdf\xaf",
		"\xc2\x81", "\xc3\xb3", "\xc4\x91", "\xc5\xbe", "\xc6\x80", "\xc7\xbf", "\xc8\x81", "\xc9\xbe", "\xca\x80", "\xcb\x81", "\xcc\xbf", "\xcd\xbe", "\xce\x80", "\xcf\xbf",
		"\xd0\x81", "\xd1\xb2", "\xd2\x92", "\xd3\xbf", "\xd4\x80", "\xd5\xbe", "\xd6\x80", "\xd7\xbf", "\xd8\x81", "\xd9\x80", "\xda\xbe", "\xdb\xbf", "\xdc\x81", "\xdd\xbe",
		"\xde\x81", "\xdf\xbf",
		// Triple-byte.
		"\xe0\xa0\x80", "\xe0\xbf\xbf", "\xe0\xb1\x91", "\xe0\xa2\xa3", "\xe0\xa3\xb4", "\xe0\xa4\xa4",
		"\xe1\x80\x80", "\xe1\xbf\xbf", "\xe1\x91\x81", "\xe1\xa1\x91", "\xe1\xb2\xa2", "\xe1\x93\x93", "\xe1\xa3\xa3",
		"\xe2\x80\x80", "\xe2\xbf\xbf", "\xe2\x92\x82", "\xe2\xa3\x93", "\xe2\xb4\xa4", "\xe1\x95\x95", "\xe1\xa6\xa6",
		"\xe3\x80\x80", "\xe3\xbf\xbf", "\xe3\x93\x83", "\xe3\xa4\x94", "\xe3\xb5\xa5", "\xe3\x96\x96", "\xe3\xa7\xa7",
		"\xe4\x80\x80", "\xe4\xbf\xbf", "\xe4\x94\x84", "\xe4\xa5\x95", "\xe4\xb6\xa6", "\xe4\x97\x97", "\xe4\xa8\xa8",
		"\xe5\x80\x80", "\xe5\xbf\xbf", "\xe5\x95\x85", "\xe5\xa6\x96", "\xe5\xb7\xa7", "\xe5\x98\x98", "\xe5\xa9\xa9",
		"\xe6\x80\x80", "\xe6\xbf\xbf", "\xe6\x96\x86", "\xe6\xa7\x97", "\xe6\xb8\xa8", "\xe6\x99\x99", "\xe6\xaa\xaa",
		"\xe7\x80\x80", "\xe7\xbf\xbf", "\xe7\x97\x87", "\xe7\xa8\x98", "\xe7\xb9\xa9", "\xe7\x9a\x9a", "\xe7\xab\xab",
		"\xe8\x80\x80", "\xe8\xbf\xbf", "\xe8\x98\x88", "\xe8\xa9\x99", "\xe8\xba\xaa", "\xe8\x9b\x9b", "\xe8\xac\xac",
		"\xe9\x80\x80", "\xe9\xbf\xbf", "\xe9\x99\x89", "\xe9\xaa\x9a", "\xe9\xbb\xab", "\xe9\x9c\x97", "\xe9\xad\xad",
		"\xea\x80\x80", "\xea\xbf\xbf", "\xea\x9a\x8a", "\xea\xab\x9b", "\xea\xbc\xac", "\xea\x9d\x9d", "\xea\xae\xae",
		"\xeb\x80\x80", "\xeb\xbf\xbf", "\xeb\x9b\x8b", "\xeb\xac\x9c", "\xeb\xbd\xad", "\xeb\x9e\x9e", "\xeb\xaf\xaf",
		"\xec\x80\x80", "\xec\xbf\xbf", "\xec\x9c\x8c", "\xec\xad\x9d", "\xec\xbe\xae", "\xec\x9f\x9f", "\xec\xa0\xa0",
		"\xed\x80\x80", "\xed\x9f\xbf", "\xed\x9d\x8d", "\xed\x8e\x9e", "\xed\x9f\xaf", "\xed\x90\x90", "\xed\x91\x91",
		"\xee\x80\x80", "\xee\xbf\xbf", "\xee\x9e\x8e", "\xee\xaf\x9f", "\xee\xb0\xa0", "\xee\x91\x91", "\xee\xa2\xa2",
		"\xef\x80\x80", "\xef\xbf\xbf", "\xef\x9f\x8f", "\xef\xa0\x90", "\xef\xb1\xa1", "\xef\x92\x92", "\xef\xa3\xa3",
		// Quadruple-byte.
		"\xf0\x90\x80\x80", "\xf0\xbf\xbf\xbf", "\xf0\x91\x91\x91", "\xf0\xa2\xa2\xa2", "\xf0\xb3\xb3\xb3", "\xf0\x92\x93\x94", "\xf0\xa3\x94\x94",
		"\xf1\x80\x80\x80", "\xf1\xbf\xbf\xbf", "\xf1\x82\x82\x82", "\xf1\x93\x93\x93", "\xf1\xa4\xa4\xa4", "\xf1\xb5\xb5\xb5", "\xf1\x93\x93\x95", "\xf1\x94\x95\x95",
		"\xf2\x80\x80\x80", "\xf2\xbf\xbf\xbf", "\xf2\x83\x83\x83", "\xf2\x94\x94\x94", "\xf2\xa5\xa5\xa5", "\xf2\xb6\xb6\xb6", "\xf2\x94\x94\x96", "\xf2\x95\x96\x96",
		"\xf3\x80\x80\x80", "\xf3\xbf\xbf\xbf", "\xf3\x84\x84\x84", "\xf3\x95\x95\x95", "\xf3\xa6\xa6\xa6", "\xf3\xb7\xb7\xb7", "\xf3\x96\x96\x97", "\xf3\x96\x97\x97",
		"\xf4\x80\x80\x80", "\xf4\x8f\xbf\xbf", "\xf4\x85\x85\x85", "\xf4\x86\x96\x96", "\xf4\x87\xa7\xa7", "\xf4\x88\xb8\xb8", "\xf4\x87\x97\x98", "\xf4\x87\x98\x98",
	);

	static $invalid_strs = array(
		// Single-byte.
		"\x80", "\x81", "\x82", "\x83", "\x84", "\x85", "\x86", "\x87", "\x88", "\x89", "\x8a", "\x8b", "\x8c", "\x8d", "\x8e", "\x8f", 
		"\x90", "\x91", "\x92", "\x93", "\x94", "\x95", "\x96", "\x97", "\x98", "\x99", "\x9a", "\x9b", "\x9c", "\x9d", "\x9e", "\x9f", 
		"\xa0", "\xa1", "\xa2", "\xa3", "\xa4", "\xa5", "\xa6", "\xa7", "\xa8", "\xa9", "\xaa", "\xab", "\xac", "\xad", "\xae", "\xaf", 
		"\xb0", "\xb1", "\xb2", "\xb3", "\xb4", "\xb5", "\xb6", "\xb7", "\xb8", "\xb9", "\xba", "\xbb", "\xbc", "\xbd", "\xbe", "\xbf", 
		"\xc0", "\xc1", "\xc2", "\xc3", "\xc4", "\xc5", "\xc6", "\xc7", "\xc8", "\xc9", "\xca", "\xcb", "\xcc", "\xcd", "\xce", "\xcf", 
		"\xd0", "\xd1", "\xd2", "\xd3", "\xd4", "\xd5", "\xd6", "\xd7", "\xd8", "\xd9", "\xda", "\xdb", "\xdc", "\xdd", "\xde", "\xdf", 
		"\xe0", "\xe1", "\xe2", "\xe3", "\xe4", "\xe5", "\xe6", "\xe7", "\xe8", "\xe9", "\xea", "\xeb", "\xec", "\xed", "\xee", "\xef", 
		"\xf0", "\xf1", "\xf2", "\xf3", "\xf4", "\xf5", "\xf6", "\xf7", "\xf8", "\xf9", "\xfa", "\xfb", "\xfc", "\xfd", "\xfe", "\xff", 
		// Double-byte.
		"\xc2\x7f", "\xc2\xc0", "\xc3\x7f", "\xc3\xc0", "\xc4\x7f", "\xc4\xc0", "\xc5\x7f", "\xc5\xc0", "\xc6\x7f", "\xc6\xc0", "\xc7\x7f", "\xc7\xc0",
		"\xc8\x7f", "\xc8\xc0", "\xc9\x7f", "\xc9\xc0", "\xca\x7f", "\xca\xc0", "\xcb\x7f", "\xcb\xc0", "\xcc\x7f", "\xcc\xc0", "\xcd\x7f", "\xcd\xc0",
		"\xce\x7f", "\xce\xc0", "\xcf\x7f", "\xcf\xc0",
		"\xd0\x7f", "\xd0\xc0", "\xd1\x7f", "\xd1\xc0", "\xd2\x7f", "\xd2\xc0", "\xd3\x7f", "\xd3\xc0", "\xd4\x7f", "\xd4\xc0", "\xd5\x7f", "\xd5\xc0",
		"\xd6\x7f", "\xd6\xc0", "\xd7\x7f", "\xd7\xc0",
		"\xd8\x7f", "\xd8\xc0", "\xd9\x7f", "\xd9\xc0", "\xda\x7f", "\xda\xc0", "\xdb\x7f", "\xdb\xc0", "\xdc\x7f", "\xdc\xc0", "\xdd\x7f", "\xdd\xc0",
		"\xde\x7f", "\xde\xc0", "\xdf\x7f", "\xdf\xc0",
		"\xc0\x80", "\xc1\x80", "\xc0\xbf", "\xc1\xbf", "\xc0\x7f", "\xc0\xc0", "\x8e\xa1", "\x80\x80",
		"\xe0\xa0", "\xe1\x80", "\xe0\x80", "\xf0\x90", "\xf1\x80", "\xf4\x80", "\xf4\x90",
		// Triple-byte.
		"\xe0\x9f\x80", "\xe0\xc0\xbf", "\xe0\x80\x7f", "\xe0\x80\xc0", "\xe0\xbf\x7f", "\xe0\xbf\xc0",
		"\xe1\x7f\x80", "\xe1\xc0\xbf", "\xe1\x80\x7f", "\xe1\x80\xc0", "\xe1\xbf\x7f", "\xe1\xbf\xc0",
		"\xe2\x7f\x80", "\xe2\xc0\xbf", "\xe2\x80\x7f", "\xe2\x80\xc0", "\xe2\xbf\x7f", "\xe2\xbf\xc0",
		"\xe3\x7f\x80", "\xe3\xc0\xbf", "\xe3\x80\x7f", "\xe3\x80\xc0", "\xe3\xbf\x7f", "\xe3\xbf\xc0",
		"\xe4\x7f\x80", "\xe4\xc0\xbf", "\xe4\x80\x7f", "\xe4\x80\xc0", "\xe4\xbf\x7f", "\xe4\xbf\xc0",
		"\xe5\x7f\x80", "\xe5\xc0\xbf", "\xe5\x80\x7f", "\xe5\x80\xc0", "\xe5\xbf\x7f", "\xe5\xbf\xc0",
		"\xe6\x7f\x80", "\xe6\xc0\xbf", "\xe6\x80\x7f", "\xe6\x80\xc0", "\xe6\xbf\x7f", "\xe6\xbf\xc0",
		"\xe7\x7f\x80", "\xe7\xc0\xbf", "\xe7\x80\x7f", "\xe7\x80\xc0", "\xe7\xbf\x7f", "\xe7\xbf\xc0",
		"\xe8\x7f\x80", "\xe8\xc0\xbf", "\xe8\x80\x7f", "\xe8\x80\xc0", "\xe8\xbf\x7f", "\xe8\xbf\xc0",
		"\xe9\x7f\x80", "\xe9\xc0\xbf", "\xe9\x80\x7f", "\xe9\x80\xc0", "\xe9\xbf\x7f", "\xe9\xbf\xc0",
		"\xea\x7f\x80", "\xea\xc0\xbf", "\xea\x80\x7f", "\xea\x80\xc0", "\xea\xbf\x7f", "\xea\xbf\xc0",
		"\xeb\x7f\x80", "\xeb\xc0\xbf", "\xeb\x80\x7f", "\xeb\x80\xc0", "\xeb\xbf\x7f", "\xeb\xbf\xc0",
		"\xec\x7f\x80", "\xec\xc0\xbf", "\xec\x80\x7f", "\xec\x80\xc0", "\xec\xbf\x7f", "\xec\xbf\xc0",
		"\xed\x7f\x80", "\xed\xa0\xbf", "\xed\x80\x7f", "\xed\x80\xc0", "\xed\xbf\x7f", "\xed\xbf\xc0",
		"\xee\x7f\x80", "\xee\xc0\xbf", "\xee\x80\x7f", "\xee\x80\xc0", "\xee\xbf\x7f", "\xee\xbf\xc0",
		"\xef\x7f\x80", "\xef\xc0\xbf", "\xef\x80\x7f", "\xef\x80\xc0", "\xef\xbf\x7f", "\xef\xbf\xc0",
		"\xf0\x90\x80", "\xf1\x80\x80", "\xf4\x80\x80", "\xf4\x90\x80",
		// Quadruple-byte.
		"\xf0\x8f\x80\x80", "\xf0\xc0\xbf\xbf", "\xf0\x90\x7f\x80", "\xf0\xbf\xc0\xbf", "\xf0\x90\x80\x7f",
		"\xf0\xbf\xbf\xc0", "\xf0\x8f\x7f\x80", "\xf0\xbf\xc0\xc0", "\xf0\x8f\x7f\x7f", "\xf0\xc0\xc0\xc0",
		"\xf0\x8f\xbf\xc0", "\xf0\x8f\x80\x7f", "\xf0\x7f\x7f\x7f", "\xf0\x7f\x7f\xc0", "\xf0\x7f\xc0\x80",
		"\xf1\x7f\x80\x80", "\xf1\xc0\xbf\xbf", "\xf1\x80\x7f\x80", "\xf1\xbf\xc0\xbf", "\xf1\x80\x80\x7f",
		"\xf1\xbf\xbf\xc0", "\xf1\x7f\x7f\x80", "\xf1\xbf\xc0\xc0", "\xf1\x7f\x7f\x7f", "\xf1\xc0\xc0\xc0",
		"\xf1\x8f\xbf\xc1", "\xf1\x7f\x81\x7f", "\xf1\x7f\x7f\x7f", "\xf1\x7f\x7f\xc1", "\xf1\x7f\xc1\x81",
		"\xf2\x7f\x80\x80", "\xf2\xc0\xbf\xbf", "\xf2\x80\x7f\x80", "\xf2\xbf\xc0\xbf", "\xf2\x80\x80\x7f",
		"\xf2\xbf\xbf\xc0", "\xf2\x7f\x7f\x80", "\xf2\xbf\xc0\xc0", "\xf2\x7f\x7f\x7f", "\xf2\xc0\xc0\xc0",
		"\xf2\x8f\xbf\xc2", "\xf2\x7f\x82\x7f", "\xf2\x7f\x7f\x7f", "\xf2\x7f\x7f\xc2", "\xf2\x7f\xc2\x82",
		"\xf3\x7f\x80\x80", "\xf3\xc0\xbf\xbf", "\xf3\x80\x7f\x80", "\xf3\xbf\xc0\xbf", "\xf3\x80\x80\x7f",
		"\xf3\xbf\xbf\xc0", "\xf3\x7f\x7f\x80", "\xf3\xbf\xc0\xc0", "\xf3\x7f\x7f\x7f", "\xf3\xc0\xc0\xc0",
		"\xf3\x8f\xbf\xc3", "\xf3\x7f\x83\x7f", "\xf3\x7f\x7f\x7f", "\xf3\x7f\x7f\xc3", "\xf3\x7f\xc3\x83",
		"\xf4\x7f\x80\x80", "\xf4\x90\xbf\xbf", "\xf4\x80\x7f\x80", "\xf4\x8f\xc0\xbf", "\xf4\x80\x80\x7f",
		"\xf4\x7f\xbf\xc0", "\xf4\x7f\x7f\x80", "\xf4\x8f\xc0\xc0", "\xf4\x8f\x7f\x7f", "\xf4\x90\xc0\xc0",
		"\xf4\x8f\xbf\xc4", "\xf4\x7f\x84\x7f", "\xf4\x7f\x7f\x7f", "\xf4\x7f\x7f\xc4", "\xf4\x7f\xc4\x84",
	);

	if ( null === $other_strs ) {
		$other_strs = $invalid_strs;
		$other_is_codepoints = false;
	}

	$valid_max = count( $valid_strs ) - 1;
	$other_max = count( $other_strs ) - 1;

	$ret = array();

	$other_len = min( intval( $utf8_len * $other_ratio ), $utf8_len );
	$valid_len = $utf8_len - $other_len;

	// TODO: very slow for large $len.
	for ( $i = 0; $i < $valid_len; $i++ ) {
		$ret[] = $valid_strs[ rand( 0, $valid_max ) ];
	}
	if ( $other_is_codepoints ) {
		for ( $i = 0; $i < $other_len; $i++ ) {
			$ret[] = unfc_utf8_chr( $other_strs[ rand( 0, $other_max ) ] );
		}
	} else {
		for ( $i = 0; $i < $other_len; $i++ ) {
			$ret[] = $other_strs[ rand( 0, $other_max ) ];
		}
	}

	shuffle( $ret );
	return implode( '', $ret );
}

/**
 * Random UTF-8 string.
 */
function unfc_utf8_rand_str( $len = 10000, $chr_max = 0x7f ) {
	$ret = array();

	// TODO: very slow for large $len.
	if ( $chr_max > 0x7f ) {
		for ( $i = 0; $i < $len; $i++ ) {
			$ret[] = unfc_strip_invalid_utf8( unfc_utf8_chr( rand( 0, $chr_max ) ) );
		}
	} else {
		for ( $i = 0; $i < $len; $i++ ) {
			$ret[] = chr( rand( 0, $chr_max ) );
		}
	}

	shuffle( $ret );
	return implode( '', $ret );
}

/**
 * Get ICU version of loaded "intl" extension.
 */
function unfc_icu_version() {
	if ( defined( 'INTL_ICU_VERSION' ) ) { // Introduced PHP 5.3.6: https://bugs.php.net/bug.php?id=54561
		return INTL_ICU_VERSION;
	}
	ob_start();
	phpinfo( INFO_MODULES );
	$lines = explode( "\n", ob_get_clean() );
	foreach ( $lines as $line ) {
		if ( preg_match( '/icu +version +=> +(\d+\.\d+(?:\.\d+)?)/i', $line, $matches ) ) {
			return $matches[1];
		}
	}
	return false;
}

/**
 * Strip carriage returns callback.
 */
function unfc_get_cb( $el ) {
	return rtrim( $el, "\r" );
}

/**
 * Return UTF-8 of space-separated hex entry in Unicode file.
 */
function unfc_utf8_entry( $entry, &$char_cnt = -1 ) {
    if ( ! $entry ) {
        return '';
    }
    $chars = explode( ' ', $entry );
    $chars = array_map( 'hexdec', $chars );
    $chars = array_map( 'unfc_utf8_chr', $chars );
	if ( -1 !== $char_cnt ) {
		$char_cnt = count( $chars );
	}
    return implode( '', $chars );
}

/**
 * Escape for output as PHP string.
 */
function unfc_out_esc( $str ) {
    return str_replace( array( '\\', '\'' ), array( '\\\\', '\\\'' ), $str );
}

/**
 * Output a PHP array file.
 */
function unfc_output_array_file( $array, $file, $unicode_version, $ucd_name ) {
	$out = array();
	$out[] =  '<?php';
	$out[] = '';
	$out[] = 'return array( // https://www.unicode.org/Public/' . $unicode_version . '/ucd/' . $ucd_name;

	foreach ( $array as $code => $entry ) {
		if ( is_int( $entry ) ) {
			$out[] = '  \'' . $code . '\' => ' . $entry . ',';
		} elseif( is_array( $entry ) ) {
			if ( $entry[1] ) {
				$out[] = '  \'' . unfc_out_esc( $code ) . '\' => array(\'' . unfc_out_esc( $entry[0] ) . '\'),'; // Use type being array as flag true to save space.
			} else {
				$out[] = '  \'' . unfc_out_esc( $code ) . '\' => \'' . unfc_out_esc( $entry[0] ) . '\',';
			}
		} else {
			$out[] = '  \'' . unfc_out_esc( $code ) . '\' => \'' . unfc_out_esc( $entry ) . '\',';
		}
	}
	$out[] = ');';
	$out[] = '';

	return file_put_contents( $file, implode( "\n", $out ) );
}

/**
 * Recursive version of array_map(). From http://php.net/manual/en/function.array-map.php#116938
 */
function unfc_array_map_recursive( $callback, $array ) {
	return filter_var( $array, FILTER_CALLBACK, array( 'options' => $callback ) );
}

/**
 * Version of wp_list_pluck that allows for unset fields.
 */
function unfc_list_pluck( $list, $field ) {
	foreach ( $list as $key => $value ) {
		if ( is_object( $value ) ) {
			$list[ $key ] = isset( $value->$field ) ? $value->$field : null;
		} else {
			$list[ $key ] = isset( $value[ $field ] ) ? $value[ $field ] : null;
		}
	}
	return array_filter( $list );
}
