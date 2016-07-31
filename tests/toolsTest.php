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
	 * @ticket unfc_utf8_regex_alts
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
	 * @ticket unfc_u_equivalence
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
	 * @ticket unfc_utf8_chr
	 */
    function test_utf8_chr() {
		$this->assertSame( "\x00", unfc_utf8_chr( 0 ) );
		$this->assertSame( "\x01", unfc_utf8_chr( 1 ) );
		$this->assertSame( "\xf4\x8f\xbf\xbe", unfc_utf8_chr( 0x10fffe ) );
		$this->assertSame( "\xf4\x8f\xbf\xbf", unfc_utf8_chr( 0x10ffff ) );
		$this->assertSame( "\xf4\x8f\xbf\xbf", unfc_utf8_chr( 0x120000 ) );
	}

	/**
	 * @ticket unfc_utf8_rand_ratio_str
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
	 * @ticket unfc_utf8_rand_chr
	 */
    function test_utf8_rand_chr() {
		$ASCII = "\x20\x65\x69\x61\x73\x6E\x74\x72\x6F\x6C\x75\x64\x5D\x5B\x63\x6D\x70\x27\x0A\x67\x7C\x68\x76\x2E\x66\x62\x2C\x3A\x3D\x2D\x71\x31\x30\x43\x32\x2A\x79\x78\x29\x28\x4C\x39\x41\x53\x2F\x50\x22\x45\x6A\x4D\x49\x6B\x33\x3E\x35\x54\x3C\x44\x34\x7D\x42\x7B\x38\x46\x77\x52\x36\x37\x55\x47\x4E\x3B\x4A\x7A\x56\x23\x48\x4F\x57\x5F\x26\x21\x4B\x3F\x58\x51\x25\x59\x5C\x09\x5A\x2B\x7E\x5E\x24\x40\x60\x7F\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F";

		$out = unfc_utf8_rand_str();
        $this->assertTrue( ! isset( $out[ strspn( $out, $ASCII ) ] ) );
		$out = unfc_utf8_rand_str( 1000, 0x10000 );
        $this->assertTrue( unfc_is_valid_utf8( $out ) );
	}

	/**
	 * @ticket unfc_utf8_ranges
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
	 * @ticket unfc_unicode_fmt
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
	 * @ticket unfc_get_cb
	 */
    function test_get_cb() {
		$in = "\rasdfasdf\n\r";
		$expected = "\rasdfasdf\n";
		$out = unfc_get_cb( $in );
		$this->assertSame( $expected, $out );
	}

	/**
	 * @ticket unfc_array_map_recursive
	 */
    function test_array_map_recursive() {
		$arr = array( 'a' => array( 'b' => array( 'c' => 'a', 'd' => 'b' ), 'e' => 'c' ), 'f' => 'd', 'g' => array( 'h' => 'e' ) ); 
		$expected = array( 'a' => array( 'b' => array( 'c' => 'A', 'd' => 'B' ), 'e' => 'C' ), 'f' => 'D', 'g' => array( 'h' => 'E' ) );
		$out = unfc_array_map_recursive( 'strtoupper', $arr );
		$this->assertSame( $expected, $out );
	}

	/**
	 * @ticket unfc_list_pluck
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
