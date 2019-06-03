<?php

/**
 * Test tools.
 *
 * @group unfc
 * @group unfc_tools
 */
class Tests_UNFC_Tools extends WP_UnitTestCase {

	public static function wpSetUpBeforeClass() {
		$dirname = dirname( dirname( __FILE__ ) );
		require_once $dirname . '/tools/functions.php';
		require_once $dirname . '/Symfony/unfc_regex_alts.php';
	}

	function setUp() {
		parent::setUp();
		if ( ! method_exists( 'WP_UnitTestCase', 'wpSetUpBeforeClass' ) ) { // Hack for WP testcase.php versions prior to 4.4
			self::wpSetUpBeforeClass();
		}
	}

	/**
	 */
    function test_utf8_regex_alts() {

		$arr = array(
			/**/
			array( array( 0, 0, 0, 0 ), array( 0, 0, 0, 0xa ), '[\x00-\x0a]' ),
			array( array( 0, 0, 0, 0x1 ), array( 0, 0, 0xc2, 0xa0 ), '[\x01-\x7f]|\xc2[\x80-\xa0]' ),
			array( array( 0, 0, 0xc2, 0x80 ), array( 0, 0, 0xc2, 0x85 ), '\xc2[\x80-\x85]' ),
			array( array( 0, 0, 0xc2, 0x80 ), array( 0, 0, 0xc2, 0x81 ), '\xc2[\x80\x81]' ),
			array( array( 0, 0, 0xc2, 0x80 ), array( 0, 0, 0xc3, 0x81 ), '\xc2[\x80-\xbf]|\xc3[\x80\x81]' ),
			array( array( 0, 0, 0xc2, 0x80 ), array( 0, 0, 0xc3, 0xbf ), '[\xc2\xc3][\x80-\xbf]' ),
			array( array( 0, 0, 0xc3, 0x80 ), array( 0, 0, 0xd0, 0x80 ), '[\xc3-\xcf][\x80-\xbf]|\xd0\x80' ),
			array( array( 0, 0, 0xc4, 0x81 ), array( 0, 0, 0xdf, 0xbf ), '\xc4[\x81-\xbf]|[\xc5-\xdf][\x80-\xbf]' ),
			array( array( 0, 0, 0xd2, 0x81 ), array( 0, 0, 0xdf, 0xbe ), '\xd2[\x81-\xbf]|[\xd3-\xde][\x80-\xbf]|\xdf[\x80-\xbe]' ),
			array( array( 0, 0xe0, 0x83, 0xbe ), array( 0, 0xe0, 0x84, 0x80 ), '\xe0(?:\x83[\xbe\xbf]|\x84\x80)' ),
			array( array( 0, 0xe0, 0x83, 0xbe ), array( 0, 0xe0, 0x84, 0x90 ), '\xe0(?:\x83[\xbe\xbf]|\x84[\x80-\x90])' ),
			array( array( 0, 0xe0, 0x83, 0x80 ), array( 0, 0xe0, 0x84, 0xbf ), '\xe0[\x83\x84][\x80-\xbf]' ),
			array( array( 0, 0xe0, 0x83, 0xbd ), array( 0, 0xe1, 0x84, 0x91 ), '\xe0(?:\x83[\xbd-\xbf]|[\x84-\xbf][\x80-\xbf])|\xe1(?:[\x80-\x83][\x80-\xbf]|\x84[\x80-\x91])' ),
			array( array( 0, 0xe0, 0x83, 0xbd ), array( 0, 0xe2, 0x84, 0x91 ),
				'\xe0(?:\x83[\xbd-\xbf]|[\x84-\xbf][\x80-\xbf])|\xe1[\x80-\xbf][\x80-\xbf]|\xe2(?:[\x80-\x83][\x80-\xbf]|\x84[\x80-\x91])' ),
			array( array( 0, 0, 0xc3, 0x9e ), array( 0, 0xe0, 0x85, 0x80 ), '\xc3[\x9e-\xbf]|[\xc4-\xdf][\x80-\xbf]|\xe0(?:[\x80-\x84][\x80-\xbf]|\x85\x80)' ),
			array( array( 0, 0, 0, 0x7e ), array( 0, 0xe0, 0x86, 0x81 ), '[\x7e\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0(?:[\x80-\x85][\x80-\xbf]|\x86[\x80\x81])' ),
			array( array( 0xf0, 0x83, 0xbe, 0x90 ), array( 0xf0, 0x84, 0x80, 0x80 ), '\xf0(?:\x83(?:\xbe[\x90-\xbf]|\xbf[\x80-\xbf])|\x84\x80\x80)' ),
			array( array( 0xf0, 0x83, 0xbb, 0x90 ), array( 0xf1, 0x85, 0x81, 0x81 ),
				'\xf0(?:\x83(?:\xbb[\x90-\xbf]|[\xbc-\xbf][\x80-\xbf])|[\x84-\xbf][\x80-\xbf][\x80-\xbf])|\xf1(?:[\x80-\x84][\x80-\xbf][\x80-\xbf]|\x85(?:\x80[\x80-\xbf]|\x81[\x80\x81]))' ),
			array( array( 0xf0, 0x83, 0x80, 0x80 ), array( 0xf0, 0x84, 0xbf, 0xbf ), '\xf0[\x83\x84][\x80-\xbf][\x80-\xbf]' ),
			array( array( 0xf0, 0x84, 0xbe, 0x90 ), array( 0xf4, 0x84, 0xbe, 0x90 ),
				'\xf0(?:\x84(?:\xbe[\x90-\xbf]|\xbf[\x80-\xbf])|[\x85-\xbf][\x80-\xbf][\x80-\xbf])|[\xf1-\xf3][\x80-\xbf][\x80-\xbf][\x80-\xbf]|\xf4(?:[\x80-\x83][\x80-\xbf][\x80-\xbf]|\x84(?:[\x80-\xbd][\x80-\xbf]|\xbe[\x80-\x90]))' ),
			array( array( 0, 0xe3, 0x81, 0xa0 ), array( 0xf2, 0x84, 0xaf, 0xb0 ),
				'\xe3(?:\x81[\xa0-\xbf]|[\x82-\xbf][\x80-\xbf])|[\xe4-\xef][\x80-\xbf][\x80-\xbf]|[\xf0\xf1][\x80-\xbf][\x80-\xbf][\x80-\xbf]|\xf2(?:[\x80-\x83][\x80-\xbf][\x80-\xbf]|\x84(?:[\x80-\xae][\x80-\xbf]|\xaf[\x80-\xb0]))' ),
			array( array( 0, 0, 0xd1, 0xbe ), array( 0xf3, 0x84, 0x80, 0xb0 ),
				'\xd1[\xbe\xbf]|[\xd2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf][\x80-\xbf]|[\xf0-\xf2][\x80-\xbf][\x80-\xbf][\x80-\xbf]|\xf3(?:[\x80-\x83][\x80-\xbf][\x80-\xbf]|\x84\x80[\x80-\xb0])' ),
			array( array( 0, 0, 0, 0 ), array( 0xf4, 0x8f, 0xbf, 0xbf ),
				'[\x00-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf][\x80-\xbf]|[\xf0-\xf3][\x80-\xbf][\x80-\xbf][\x80-\xbf]|\xf4[\x80-\x8f][\x80-\xbf][\x80-\xbf]' ),
			array( array( 0, 0, 0, 0 ), array( 0, 0, 0, 0 ), '\x00' ),
			array( array( 0, 0, 0, 1 ), array( 0, 0, 0, 1 ), '\x01' ),
			array( array( 0, 0, 0xc2, 0x80 ), array( 0, 0, 0xc2, 0x80 ), '\xc2\x80' ),
			array( array( 0, 0xe0, 0x80, 0x80 ), array( 0, 0xe0, 0x80, 0x80 ), '\xe0\x80\x80' ),
			array( array( 0xf0, 0x80, 0x80, 0x80 ), array( 0xf0, 0x80, 0x80, 0x80 ), '\xf0\x80\x80\x80' ),
			/**/
		);

		foreach ( $arr as $item ) {
			list( $c1, $c2, $expected ) = $item;
			$ranges = array();
			unfc_utf8_4range( $ranges, $c1, $c2 );
			$actual = unfc_utf8_regex_alts( $ranges );
			$this->assertSame( $expected, $actual );
		}
    }

	/**
	 */
    function test_utf8_ranges_from_codepoints() {
		$this->assertSame( array(), unfc_utf8_ranges_from_codepoints( array() ) );

		$out = unfc_utf8_regex_alts( unfc_utf8_ranges_from_codepoints( array( 0x9, 0x0a, 0xb ) ) );
		$this->assertSame( '[\x09-\x0b]', $out );
		$out_utf16 = unfc_unicode_regex_chars_from_codepoints( array( 0x9, 0x0a, 0xb ) );
		$this->assertSame( '\x09-\x0b', $out_utf16 );

		$codepoints = array(
			0x9, 0xa, 0xb, 0xc, 0xd, 0x20,
		);

		$out = unfc_utf8_regex_alts( unfc_utf8_ranges_from_codepoints( $codepoints ) );
		$this->assertSame( '[\x09-\x0d\x20]', $out );

		$codepoints = array_merge( $codepoints, array( 0xa1, 0xa2, 0xa3, 0xa4, 0xa5, 0xa6, 0xa7, 0xa9, 0xaa ) );
		sort( $codepoints );
		$out = unfc_utf8_regex_alts( unfc_utf8_ranges_from_codepoints( $codepoints ) );
		$this->assertSame( '[\x09-\x0d\x20]|\xc2[\xa1-\xa7\xa9\xaa]', $out );

		$codepoints = array_merge( $codepoints, array( 0x42, 0x43, 0x5f ) );
		sort( $codepoints );
		$out = unfc_utf8_regex_alts( unfc_utf8_ranges_from_codepoints( $codepoints ) );
		$this->assertSame( '[\x09-\x0d\x20\x42\x43\x5f]|\xc2[\xa1-\xa7\xa9\xaa]', $out );

		$codepoints = array_merge( $codepoints, array( 0xe, 0xf, 0x21, 0x22, 0x24 ) );
		sort( $codepoints );
		$out = unfc_utf8_regex_alts( unfc_utf8_ranges_from_codepoints( $codepoints ) );
		$this->assertSame( '[\x09-\x0f\x20-\x22\x24\x42\x43\x5f]|\xc2[\xa1-\xa7\xa9\xaa]', $out );
	}

	/**
	 */
    function test_unicode_ranges_from_codepoints() {
		$this->assertSame( array(), unfc_unicode_ranges_from_codepoints( array() ) );

		$out = unfc_unicode_ranges_from_codepoints( array( 0x9, 0x0a, 0xb ) );
		$this->assertSame( array( array( 0x9, 0xb ) ), $out );

		$codepoints = array(
			0x9, 0xa, 0xb, 0xc, 0xd, 0x20,
		);

		$out = unfc_unicode_ranges_from_codepoints( $codepoints );
		$this->assertSame( array( array( 0x9, 0xd ), 0x20 ), $out );

		$codepoints = array_merge( $codepoints, array( 0xa1, 0xa2, 0xa3, 0xa4, 0xa5, 0xa6, 0xa7, 0xa9, 0xaa ) );
		sort( $codepoints );
		$out = unfc_unicode_ranges_from_codepoints( $codepoints );
		$this->assertSame( array( array( 0x9, 0xd ), 0x20, array( 0xa1, 0xa7 ), array( 0xa9, 0xaa ) ), $out );

		$codepoints = array_merge( $codepoints, array( 0x42, 0x43, 0x5f ) );
		sort( $codepoints );
		$out = unfc_unicode_ranges_from_codepoints( $codepoints );
		$this->assertSame( array( array( 0x9, 0xd ), 0x20, array( 0x42, 0x43 ), 0x5f, array( 0xa1, 0xa7 ), array( 0xa9, 0xaa ) ), $out );

		$codepoints = array_merge( $codepoints, array( 0xe, 0xf, 0x21, 0x22, 0x24, 0x44, 0x60, 0xa8 ) );
		sort( $codepoints );
		$out = unfc_unicode_ranges_from_codepoints( $codepoints );
		$this->assertSame( array( array( 0x9, 0xf ), array( 0x20, 0x22 ), 0x24, array( 0x42, 0x44 ), array( 0x5f, 0x60 ), array( 0xa1, 0xaa ) ), $out );
	}

	/**
	 */
    function test_utf8_parse_unicode_data() {
		$file = 'tests/UCD-9.0.0/UnicodeData.txt';

		$codepoints = unfc_parse_unicode_data( $file, __CLASS__ . '::parse_unicode_data_cb' );
		$this->assertFalse( empty( $codepoints['Z'] ) );
		sort( $codepoints['Z'] );
		$out_utf8 = unfc_utf8_regex_alts( unfc_utf8_ranges_from_codepoints( $codepoints['Z'] ) );
		$this->assertSame( '\x20|\xc2\xa0|\xe1\x9a\x80|\xe2(?:\x80[\x80-\x8a\xa8\xa9\xaf]|\x81\x9f)|\xe3\x80\x80', $out_utf8 );
		$out_utf16 = unfc_unicode_regex_chars_from_codepoints( $codepoints['Z'] );
		$this->assertSame( '\x20\xa0\x{1680}\x{2000}-\x{200a}\x{2028}\x{2029}\x{202f}\x{205f}\x{3000}', $out_utf16 );
		$str = " \x20\xe2\x80\x89";
		$this->assertSame( preg_match( '/' . $out_utf8 . '/', $str ), preg_match( '/[' . $out_utf16 . ']/u', $str ) );
	}

	static function parse_unicode_data_cb( &$codepoints, $cp, $name, $parts, $in_interval, $first_cp, $last_cp ) {
		$general_cat = $parts[UNFC_UCD_GENERAL_CATEGORY];
		if ( strlen( $general_cat ) > 1 ) {
			$general_cat_super = $general_cat[0];
		} else {
			$general_cat_super = null;
		}
		if ( ! isset( $codepoints[ $general_cat ] ) ) {
			$codepoints[ $general_cat ] = array();
		}
		$codepoints[ $general_cat ][] = $cp;
		if ( $general_cat_super ) {
			if ( ! isset( $general_cat_super ) ) {
				$codepoints[ $general_cat_super ] = array();
			}
			$codepoints[ $general_cat_super ][] = $cp;
		}
	}

	/**
	 */
    function test_utf8_parse_scripts() {
		$file = 'tests/UCD-9.0.0/Scripts.txt';

		$codepoints = unfc_parse_scripts( $file, __CLASS__ . '::parse_scripts_cb' );
		$this->assertFalse( empty( $codepoints['Latin'] ) );
		$this->assertFalse( empty( $codepoints['Greek'] ) );
	}

	static function parse_scripts_cb( &$codepoints, $cp, $script, $parts, $in_interval, $first_cp, $last_cp ) {
		if ( 'Latin' !== $script && 'Greek' !== $script ) {
			return;
		}
		if ( ! isset( $codepoints[ $script ] ) ) {
			$codepoints[ $script ] = array();
		}
		$codepoints[ $script ][] = $cp;
	}

	/**
	 */
    function test_u_equivalence() {
		global $unfc_nfc_noes, $unfc_nfc_noes_maybes_reorders;
		$this->assertTrue( is_array( $unfc_nfc_noes ) );

		foreach ( $unfc_nfc_noes as $no ) {
			$chr = unfc_utf8_chr( $no );
			$this->assertSame( 1, preg_match( UNFC_REGEX_NFC_NOES, $chr ) );
			$this->assertSame( 1, preg_match( UNFC_REGEX_NFC_NOES_U, $chr ) );
		}
		foreach ( $unfc_nfc_noes_maybes_reorders as $nmr ) {
			$chr = unfc_utf8_chr( $nmr );
			$this->assertSame( 1, preg_match( UNFC_REGEX_NFC_NOES_MAYBES_REORDERS, $chr ) );
			$this->assertSame( 1, preg_match( UNFC_REGEX_NFC_NOES_MAYBES_REORDERS_U, $chr ) );
		}
	}

	/**
	 */
    function test_utf8_chr() {
		$this->assertSame( "\x00", unfc_utf8_chr( 0 ) );
		$this->assertSame( "\x01", unfc_utf8_chr( 1 ) );
		$this->assertSame( "\xf4\x8f\xbf\xbe", unfc_utf8_chr( 0x10fffe ) );
		$this->assertSame( "\xf4\x8f\xbf\xbf", unfc_utf8_chr( 0x10ffff ) );
		$this->assertSame( "\xf4\x8f\xbf\xbf", unfc_utf8_chr( 0x120000 ) );
	}

	/**
	 */
    function test_unicode_chr() {
		$this->assertSame( 0, unfc_unicode_chr( "" ) );
		$this->assertSame( 0, unfc_unicode_chr( "\x00" ) );
		$this->assertSame( 1, unfc_unicode_chr( "\x01" ) );
		$this->assertSame( 0x41, unfc_unicode_chr( "A" ) );
		$this->assertSame( 0x7f, unfc_unicode_chr( "\x7f" ) );
		$this->assertSame( 0x0080, unfc_unicode_chr( "\xc2\x80" ) );
		$this->assertSame( 0x00e0, unfc_unicode_chr( "\xc3\xa0" ) );
		$this->assertSame( 0x03ff, unfc_unicode_chr( "\xcf\xbf" ) );
		$this->assertSame( 0x0400, unfc_unicode_chr( "\xd0\x80" ) );
		$this->assertSame( 0x0409, unfc_unicode_chr( "\xd0\x89" ) );
		$this->assertSame( 0x04d6, unfc_unicode_chr( "\xd3\x96" ) );
		$this->assertSame( 0x07ff, unfc_unicode_chr( "\xdf\xbf" ) );
		$this->assertSame( 0x0800, unfc_unicode_chr( "\xe0\xa0\x80" ) );
		$this->assertSame( 0x0fda, unfc_unicode_chr( "\xe0\xbf\x9a" ) );
		$this->assertSame( 0x0fff, unfc_unicode_chr( "\xe0\xbf\xbf" ) );
		$this->assertSame( 0x1000, unfc_unicode_chr( "\xe1\x80\x80" ) );
		$this->assertSame( 0xfffd, unfc_unicode_chr( "\xef\xbf\xbd" ) );
		$this->assertSame( 0xffff, unfc_unicode_chr( "\xef\xbf\xbf" ) );
		$this->assertSame( 0x10000, unfc_unicode_chr( "\xf0\x90\x80\x80" ) );
		$this->assertSame( 0x10001, unfc_unicode_chr( "\xf0\x90\x80\x81" ) );
		$this->assertSame( 0xe0000, unfc_unicode_chr( "\xf3\xa0\x80\x80" ) );
		$this->assertSame( 0xe0001, unfc_unicode_chr( "\xf3\xa0\x80\x81" ) );
		$this->assertSame( 0x10fffe, unfc_unicode_chr( "\xf4\x8f\xbf\xbe" ) );
		$this->assertSame( 0x10ffff, unfc_unicode_chr( "\xf4\x8f\xbf\xbf" ) );
		$this->assertSame( 0x110000, unfc_unicode_chr( "\xf4\x90\x80\x80" ) );
	}

	/**
	 */
    function test_utf8_chr_len() {
		$this->assertSame( 0, unfc_utf8_chr_len( "" ) );
		$this->assertSame( 1, unfc_utf8_chr_len( "\x00" ) );
		$this->assertSame( 1, unfc_utf8_chr_len( "A" ) );
		$this->assertSame( 2, unfc_utf8_chr_len( "\xc2\xa2" ) );
		$this->assertSame( 3, unfc_utf8_chr_len( "\xe3\xa2\xa2" ) );
		$this->assertSame( 4, unfc_utf8_chr_len( "\xf2\xa2\xa2\xa2" ) );
	}

	/**
	 */
    function test_utf8_rand_ratio_str() {
		global $unfc_normalize;
		$unfc_normalize->load_unfc_normalizer_class();

		for ( $i = 0; $i < 1000; $i++ ) {
			$out = unfc_utf8_rand_ratio_str( 100, 0.01 );
			$this->assertFalse( unfc_is_valid_utf8( $out ) );
		}

		global $unfc_nfc_noes_maybes_reorders;
		for ( $i = 0; $i < 1000; $i++ ) {
			$out = unfc_utf8_rand_ratio_str( 100, 1, $unfc_nfc_noes_maybes_reorders );
			$this->assertTrue( unfc_is_valid_utf8( $out ) );
		}
	}

	/**
	 */
    function test_utf8_rand_chr() {
		$ASCII = "\x20\x65\x69\x61\x73\x6E\x74\x72\x6F\x6C\x75\x64\x5D\x5B\x63\x6D\x70\x27\x0A\x67\x7C\x68\x76\x2E\x66\x62\x2C\x3A\x3D\x2D\x71\x31\x30\x43\x32\x2A\x79\x78\x29\x28\x4C\x39\x41\x53\x2F\x50\x22\x45\x6A\x4D\x49\x6B\x33\x3E\x35\x54\x3C\x44\x34\x7D\x42\x7B\x38\x46\x77\x52\x36\x37\x55\x47\x4E\x3B\x4A\x7A\x56\x23\x48\x4F\x57\x5F\x26\x21\x4B\x3F\x58\x51\x25\x59\x5C\x09\x5A\x2B\x7E\x5E\x24\x40\x60\x7F\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F";

		$out = unfc_utf8_rand_str();
        $this->assertTrue( ! isset( $out[ strspn( $out, $ASCII ) ] ) );
		$out = unfc_utf8_rand_str( 1000, 0x10000 );
        $this->assertTrue( unfc_is_valid_utf8( $out ) );
	}

	/**
	 */
    function test_utf8_ints() {
		$ints_max = array( 0xf4, 0x8f, 0xbf, 0xbf );
		$this->assertSame( $ints_max, unfc_utf8_ints( UNFC_UTF8_MAX ) );
		$this->assertSame( $ints_max, unfc_utf8_ints( UNFC_UTF8_MAX + 1 ) );
		$this->assertSame( $ints_max, unfc_utf8_ints( -1 ) );
		$this->assertSame( array( 0, 0, 0, 0 ), unfc_utf8_ints( 0 ) );
		$this->assertSame( array( 0, 0, 0, 0x7f ), unfc_utf8_ints( 0x7f ) );
		$this->assertSame( array( 0, 0, 0xc2, 0x80 ), unfc_utf8_ints( 0x80 ) );
		$this->assertSame( array( 0, 0, 0xdf, 0xbf ), unfc_utf8_ints( 0x7ff ) );
		$this->assertSame( array( 0, 0xe0, 0xa0, 0x80 ), unfc_utf8_ints( 0x800 ) );
		$this->assertSame( array( 0, 0xef, 0xbf, 0xbf ), unfc_utf8_ints( 0xffff ) );
		$this->assertSame( array( 0xf0, 0x90, 0x80, 0x80 ), unfc_utf8_ints( 0x10000 ) );
	}

	/**
	 */
    function test_utf8_ranges() {
		$arr = array(
			/**/
			array( 0, 0xa, '[\x00-\x0a]' ),
			array( 0x80, 0x85, '\xc2[\x80-\x85]' ),
			array( 0x800, 0x850, '\xe0(?:\xa0[\x80-\xbf]|\xa1[\x80-\x90])' ),
			array( 0x10000, 0x10040, '\xf0\x90(?:\x80[\x80-\xbf]|\x81\x80)' ),
			/**/
		);

		foreach ( $arr as $item ) {
			list( $c1, $c2, $expected ) = $item;
			$ranges = array();
			unfc_utf8_ranges( $ranges, $c1, $c2 );
			$actual = unfc_utf8_regex_alts( $ranges );
			$this->assertSame( $expected, $actual );
		}
	}

	/**
	 */
    function test_unicode_fmt() {
		$arr = array(
			/**/
			array( 0, '0x0', '\x00' ),
			array( 0xa, '0xa', '\x0a' ),
			array( 0x80, '0x80', '\x80' ),
			array( 0xff, '0xff', '\xff' ),
			array( 0x800, '0x800', '\x{800}' ),
			array( 0x10000, '0x10000', '\x{10000}' ),
			/**/
		);

		foreach ( $arr as $item ) {
			list( $c, $expected, $expected_preg ) = $item;
			$actual = unfc_unicode_fmt( $c );
			$this->assertSame( $expected, $actual );
			$actual_preg = unfc_unicode_preg_fmt( $c );
			$this->assertSame( $expected_preg, $actual_preg );
		}
	}

	/**
	 */
    function test_get_cb() {
		$in = "\rasdfasdf\n\r";
		$expected = "\rasdfasdf\n";
		$out = unfc_get_cb( $in );
		$this->assertSame( $expected, $out );
	}

	/**
	 */
    function test_array_map_recursive() {
		$arr = array( 'a' => array( 'b' => array( 'c' => 'a', 'd' => 'b' ), 'e' => 'c' ), 'f' => 'd', 'g' => array( 'h' => 'e' ) ); 
		$expected = array( 'a' => array( 'b' => array( 'c' => 'A', 'd' => 'B' ), 'e' => 'C' ), 'f' => 'D', 'g' => array( 'h' => 'E' ) );
		$out = unfc_array_map_recursive( 'strtoupper', $arr );
		$this->assertSame( $expected, $out );
	}

	/**
	 */
    function test_list_pluck() {
		$obj1 = new stdclass;
		$obj1->id = 1;
		$obj2 = new stdclass;
		$obj2->id = 2;
		$obj3 = new stdclass;
		$obj4 = new stdclass;
		$obj4->id = 4;

		$list = array( $obj1, $obj2, $obj3, $obj4 );
		$out = unfc_list_pluck( $list, 'id' );
		$this->assertSame( array( 0 => 1, 1 => 2, 3 => 4 ), $out );
	}
}
