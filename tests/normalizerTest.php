<?php
/**
 * Test UNFC_Normalizer.
 */

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Based on https://github.com/symfony/polyfill/blob/master/tests/Intl/Normalizer/NormalizerTest.php
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */

//namespace Symfony\Polyfill\Tests\Intl\Normalizer;

//use Symfony\Polyfill\Intl\Normalizer\Normalizer as pn;
//use Normalizer as in;

/**
 * @group unfc
 * @group unfc_normalizer
 */
class Tests_UNFC_Normalizer extends WP_UnitTestCase {

	static $normalizer_state = array();
	static $new_8_0_0 = array( 0x8e3, 0xa69e, /*0xa69f,*/ 0xfe2e, 0xfe2f, 0x111ca, 0x1172b, ); // Combining class additions UCD 8.0.0 over 7.0.0
	static $new_8_0_0_regex = '';
	static $new_9_0_0 = array( // Combining class additions UCD 9.0.0 over 8.0.0
		0x8d4, 0x8d5, 0x8d6, 0x8d7, 0x8d8, 0x8d9, 0x8da, 0x8db, 0x8dc, 0x8dd, 0x8de, 0x8df, 0x8e0, 0x8e1,
		0x1dfb,
		0x11442, 0x11446, 0x11c3f,
		0x1e000, 0x1e001, 0x1e002, 0x1e003, 0x1e004, 0x1e005, 0x1e006,
		0x1e008, 0x1e009, 0x1e00a, 0x1e00b, 0x1e00c, 0x1e00d, 0x1e00e, 0x1e00f, 0x1e010, 0x1e011, 0x1e012, 0x1e013, 0x1e014, 0x1e015, 0x1e016, 0x1e017, 0x1e018,
		0x1e01b, 0x1e01c, 0x1e01d, 0x1e01e, 0x1e01f, 0x1e020, 0x1e021, 0x1e023, 0x1e024, 0x1e026, 0x1e027, 0x1e028, 0x1e029, 0x1e02a,
		0x1e944, 0x1e945, 0x1e946, 0x1e947, 0x1e948, 0x1e949, 0x1e94a,
	);
	static $new_9_0_0_regex = '';
	static $at_least_55_1 = false;
	static $pcre_version = PCRE_VERSION;
	static $true = true;
	static $false = false;
	static $doing_coverage = false;

	static function wpSetUpBeforeClass() {
		global $unfc_normalize;
		self::$normalizer_state = array( $unfc_normalize->dont_js, $unfc_normalize->dont_filter, $unfc_normalize->no_normalizer );
		$unfc_normalize->dont_js = true;
		$unfc_normalize->dont_filter = true;
		$unfc_normalize->no_normalizer = true;
		$unfc_normalize->load_unfc_normalizer_class();

		$dirname = dirname( dirname( __FILE__ ) );
		require_once $dirname . '/tools/functions.php';

		$icu_version = unfc_icu_version();
		self::$at_least_55_1 = version_compare( $icu_version, '55.1', '>=' );

		if ( version_compare( $icu_version, '56.1', '<' ) ) {
			// Enable if using intl built with icu less than 56.1
			self::$new_8_0_0_regex = '/' . implode( '|', array_map( __CLASS__.'::chr', self::$new_8_0_0 ) ) . '/';
		}
		// Always set for the mo as icu for Unicode 9.0.0 not yet released as of September 2016.
		self::$new_9_0_0_regex = '/' . implode( '|', array_map( __CLASS__.'::chr', self::$new_9_0_0 ) ) . '/';

		self::$pcre_version = substr( PCRE_VERSION, 0, strspn( PCRE_VERSION, '0123456789.' ) );

		// Normalizer::isNormalized() returns an integer on HHVM and a boolean on PHP
		list( self::$true, self::$false ) = defined( 'HHVM_VERSION' ) ? array( 1, 0 ) : array( true, false );

		global $argv;
		$grep = preg_grep( '/--coverage/', $argv );
		self::$doing_coverage = ! empty( $grep );
	}

	static function wpTearDownAfterClass() {
		global $unfc_normalize;
		list( $unfc_normalize->dont_js, $unfc_normalize->dont_filter, $unfc_normalize->no_normalizer ) = self::$normalizer_state;
	}

	function setUp() {
		parent::setUp();
		if ( ! method_exists( 'WP_UnitTestCase', 'wpSetUpBeforeClass' ) ) { // Hack for WP testcase.php versions prior to 4.4
			self::wpSetUpBeforeClass();
		}
	}

	function tearDown() {
		parent::tearDown();
		if ( ! method_exists( 'WP_UnitTestCase', 'wpSetUpBeforeClass' ) ) { // Hack for WP testcase.php versions prior to 4.4
			self::wpTearDownAfterClass();
		}
	}

	/**
	 * @ticket unfc_constants
	 * @requires extension intl
	 */
    function test_constants() {

		if ( class_exists( 'Normalizer' ) ) {
			$rpn = new ReflectionClass( 'UNFC_Normalizer' );
			$rin = new ReflectionClass( 'Normalizer' );

			$rpn = $rpn->getConstants();
			$rin = $rin->getConstants();

			ksort( $rpn );
			ksort( $rin );

			$this->assertSame( $rin, $rpn );
		} else {
			$this->markTestSkipped( 'Tests_UNFC_Normalizer::test_constants: no class Normalizer' );
		}
    }

	/**
	 * @ticket unfc_props
	 */
    function test_props() {

        $rpn = new ReflectionClass( 'UNFC_Normalizer' );

		$props = $rpn->getStaticProperties();
		$this->assertArrayHasKey( 'ASCII', $props );

		$ascii = array_values( array_unique( str_split( $props['ASCII'] ) ) );
		$this->assertSame( 0x80, count( $ascii ) );
		for ( $i = 0; $i < 0x80; $i++ ) {
			$this->assertSame( true, in_array( chr( $i ), $ascii ) );
		}

		if ( version_compare( PHP_VERSION, '5.3', '>=' ) ) { // For availability of ReflectionClass::setAccessible()
			$prop = $rpn->getProperty( 'D' );
			$prop->setAccessible( true );
			$prop->setValue( null );

			$prop = $rpn->getProperty( 'C' );
			$prop->setAccessible( true );
			$prop->setValue( null );

			$this->assertSame( "\xc3\xbc", UNFC_Normalizer::normalize( "u\xcc\x88" ) );
		}
    }

    /**
	 * @ticket unfc_is_normalized
     */
    function test_is_normalized() {

        $c = 'déjà';
        $d = UNFC_Normalizer::normalize( $c, UNFC_Normalizer::NFD );

        $this->assertSame( self::$true, UNFC_Normalizer::isNormalized( '' ) );
        $this->assertSame( self::$true, UNFC_Normalizer::isNormalized( 'abc' ) );
        $this->assertSame( self::$true, UNFC_Normalizer::isNormalized( $c ) );
        $this->assertSame( self::$true, UNFC_Normalizer::isNormalized( $c, UNFC_Normalizer::NFC ) );
        $this->assertSame( self::$false, UNFC_Normalizer::isNormalized( $c, UNFC_Normalizer::NFD ) );
        $this->assertSame( self::$false, UNFC_Normalizer::isNormalized( $d, UNFC_Normalizer::NFC ) );
        $this->assertSame( self::$false, UNFC_Normalizer::isNormalized( "\xFF" ) );

        $this->assertSame( self::$true, UNFC_Normalizer::isNormalized( $d, UNFC_Normalizer::NFD ) );
		$this->assertSame( self::$false, UNFC_Normalizer::isNormalized( "u\xcc\x88", UNFC_Normalizer::NFC ) ); // u umlaut.
		$this->assertSame( self::$false, UNFC_Normalizer::isNormalized( "u\xcc\x88\xed\x9e\xa0", UNFC_Normalizer::NFC ) ); // u umlaut + Hangul

		if ( class_exists( 'Normalizer' ) ) {
        	$this->assertSame( $d, Normalizer::normalize( $c, Normalizer::NFD ) );

        	$this->assertSame( Normalizer::isNormalized( '' ), UNFC_Normalizer::isNormalized( '' ) );
        	$this->assertSame( Normalizer::isNormalized( 'abc' ), UNFC_Normalizer::isNormalized( 'abc' ) );
        	$this->assertSame( Normalizer::isNormalized( $c ), UNFC_Normalizer::isNormalized( $c ) );
        	$this->assertSame( Normalizer::isNormalized( $c, Normalizer::NFC ), UNFC_Normalizer::isNormalized( $c, UNFC_Normalizer::NFC ) );
        	$this->assertSame( Normalizer::isNormalized( $c, Normalizer::NFD ), UNFC_Normalizer::isNormalized( $c, UNFC_Normalizer::NFD ) );
        	$this->assertSame( Normalizer::isNormalized( $d, Normalizer::NFC ), UNFC_Normalizer::isNormalized( $d, UNFC_Normalizer::NFC ) );
        	$this->assertSame( Normalizer::isNormalized( "\xFF" ), UNFC_Normalizer::isNormalized( "\xFF" ) );

        	$this->assertSame( self::$true, Normalizer::isNormalized( $d, Normalizer::NFD ) );
			$this->assertSame( self::$false, Normalizer::isNormalized( "u\xcc\x88", Normalizer::NFC ) ); // u umlaut.
			$this->assertSame( self::$false, Normalizer::isNormalized( "u\xcc\x88\xed\x9e\xa0", Normalizer::NFC ) ); // u umlaut + Hangul
		}
    }

    /**
	 * @ticket unfc_normalize
     */
    function test_normalize() {

		if ( class_exists( 'Normalizer' ) ) {
			$c = Normalizer::normalize( 'déjà', UNFC_Normalizer::NFC ).Normalizer::normalize( '훈쇼™', UNFC_Normalizer::NFD );
			$this->assertSame( $c, UNFC_Normalizer::normalize( $c, UNFC_Normalizer::NONE ) );
		}
        $c = UNFC_Normalizer::normalize( 'déjà', UNFC_Normalizer::NFC ).UNFC_Normalizer::normalize( '훈쇼™', UNFC_Normalizer::NFD );
        $this->assertSame( $c, UNFC_Normalizer::normalize( $c, UNFC_Normalizer::NONE ) );
        if ( class_exists( 'Normalizer' ) ) $this->assertSame( $c, Normalizer::normalize( $c, Normalizer::NONE ) );

        $c = 'déjà 훈쇼™';
        $d = UNFC_Normalizer::normalize( $c, UNFC_Normalizer::NFD );
        $kc = UNFC_Normalizer::normalize( $c, UNFC_Normalizer::NFKC );
        $kd = UNFC_Normalizer::normalize( $c, UNFC_Normalizer::NFKD );

        $this->assertSame( '', UNFC_Normalizer::normalize( '' ) );
		if ( class_exists( 'Normalizer' ) ) {
        	$this->assertSame( $c, Normalizer::normalize( $d ) );
        	$this->assertSame( $c, Normalizer::normalize( $d, Normalizer::NFC ) );
        	$this->assertSame( $d, Normalizer::normalize( $c, Normalizer::NFD ) );
        	$this->assertSame( $kc, Normalizer::normalize( $d, Normalizer::NFKC ) );
        	$this->assertSame( $kd, Normalizer::normalize( $c, Normalizer::NFKD ) );
		}

        $this->assertSame( self::$false, UNFC_Normalizer::normalize( $c, -1 ) );
        $this->assertFalse( UNFC_Normalizer::normalize( "\xFF" ) );
    }

	/**
	 * @ticket unfc_args_compat
	 * @dataProvider data_args_compat
	 * @requires extension intl
	 */
	function test_args_compat( $string ) {

		if ( class_exists( 'Normalizer' ) ) {
			$forms = array( 0, -1, 6, -2, PHP_INT_MAX, -PHP_INT_MAX, Normalizer::NONE, Normalizer::NFD, Normalizer::NFKD, Normalizer::NFC, Normalizer::NFKD );

			foreach ( $forms as $form ) {
				$is_normalized = Normalizer::isNormalized( $string, $form );
				$normalize = Normalizer::normalize( $string, $form );
				$unfc_is_normalized = UNFC_Normalizer::isNormalized( $string, $form );
				$unfc_normalize = UNFC_Normalizer::normalize( $string, $form );

				$this->assertSame( $is_normalized, $unfc_is_normalized );
				$this->assertSame( $normalize, $unfc_normalize );
			}
		} else {
			$this->markTestSkipped( 'Tests_UNFC_Normalizer::test_args_compat: no class Normalizer' );
		}
	}

	function data_args_compat() {
		return array(
			array( '' ),
			array( 'a' ), array( "\x80" ), array( "\xc2" ), array( "\xe0" ), array( "\xf0" ),
			array( "\xc2\x80" ), array( "\xc0\x80" ), array( "\xc2\x7f" ), array( "\xc2\xc0" ), array( "\xdf\xc0" ), array( "\xe0\x80" ), array( "\xf0\x80" ),
			array( "\xe0\x80\x80" ), array( "\xe0\x9f\x80" ), array( "\xed\x80\xbf" ), array( "\xef\xbf\xc0" ), array( "\xf0\x80\x80" ),
			array( "\xf0\x80\x80\x80" ), array( "\xf0\x8f\x80\x80" ), array( "\xf1\xc0\x80\x80" ), array( "\xf2\x8f\xbf\xc2" ), array( "\xf4\x90\xbf\xbf" ),
			array( 0 ), array( 2 ), array( -1 ), array( true ), array( false ), array( 0.0 ), array( '0' ), array( null ),
		);
	}

	/**
	 * @ticket unfc_mbstring
	 * @requires extension mbstring
	 *
	 * NOTE: need to run phpunit as "PHPRC=. phpunit" to pick up "php-cli.ini" in normalizer directory for "mbstring.func_overload = 2" to be set.
	 */
	function test_mbstring() {
		$this->assertTrue( defined( 'MB_OVERLOAD_STRING' ) && ( ini_get( 'mbstring.func_overload' ) & MB_OVERLOAD_STRING ) );

		$mb_internal_encoding = mb_internal_encoding();

		$encoding = 'UTF-16';
		mb_internal_encoding( $encoding );

		$this->assertSame( 1, mb_strlen( "\x8e\xa1" ) );
		$this->assertSame( 1, strlen( "\x8e\xa1" ) );
		$this->assertSame( "\x8e\xa1", mb_substr( "\x8e\xa1", 0, 1 ) );
		$this->assertSame( "\x8e\xa1", substr( "\x8e\xa1", 0, 1 ) );

		$this->assertSame( self::$true, UNFC_Normalizer::isNormalized( 'abc' ) );
		$this->assertSame( $encoding, mb_internal_encoding() );
		$this->assertSame( self::$true, UNFC_Normalizer::isNormalized( "\xe2\x8e\xa1" ) );
		$this->assertSame( $encoding, mb_internal_encoding() );
		$this->assertSame( self::$false, UNFC_Normalizer::isNormalized( "u\xcc\x88" ) );
		$this->assertSame( $encoding, mb_internal_encoding() );

		$this->assertSame( 'abc', UNFC_Normalizer::normalize( 'abc' ) );
		$this->assertSame( $encoding, mb_internal_encoding() );
		$this->assertSame( "\xe2\x8e\xa1", UNFC_Normalizer::normalize( "\xe2\x8e\xa1" ) );
		$this->assertSame( $encoding, mb_internal_encoding() );
		$this->assertSame( "\xc3\xbc", UNFC_Normalizer::normalize( "u\xcc\x88" ) );
		$this->assertSame( $encoding, mb_internal_encoding() );

		if ( version_compare( PHP_VERSION, '5.3', '>=' ) ) { // For availability of ReflectionClass::setAccessible()
			$rpn = new ReflectionClass( 'UNFC_Normalizer' );
			$prop = $rpn->getProperty( 'mb_overload_string' );
			$prop->setAccessible( true );
			$prop->setValue( null );
			$this->assertSame( "\xc3\xbc", UNFC_Normalizer::normalize( "u\xcc\x88" ) );
			$this->assertSame( $encoding, mb_internal_encoding() );
		}

		mb_internal_encoding( $mb_internal_encoding );
	}

    /**
	 * @ticket unfc_conformance_9_0_0
     */
    function test_conformance_9_0_0() {

        $t = file( dirname( __FILE__ ) . '/UCD-9.0.0/NormalizationTest.txt' );
        $c = array();

		// From NormalizationTest.txt header:

		# Format:
		#
		#   Columns (c1, c2,...) are separated by semicolons
		#   They have the following meaning:
		#      source; NFC; NFD; NFKC; NFKD
		#   Comments are indicated with hash marks
		#   Each of the columns may have one or more code points.
		#
		# CONFORMANCE:
		# 1. The following invariants must be true for all conformant implementations
		#
		#    NFC
		#      c2 ==  toNFC(c1) ==  toNFC(c2) ==  toNFC(c3)
		#      c4 ==  toNFC(c4) ==  toNFC(c5)
		#
		#    NFD
		#      c3 ==  toNFD(c1) ==  toNFD(c2) ==  toNFD(c3)
		#      c5 ==  toNFD(c4) ==  toNFD(c5)
		#
		#    NFKC
		#      c4 == toNFKC(c1) == toNFKC(c2) == toNFKC(c3) == toNFKC(c4) == toNFKC(c5)
		#
		#    NFKD
		#      c5 == toNFKD(c1) == toNFKD(c2) == toNFKD(c3) == toNFKD(c4) == toNFKD(c5)
		#
		# 2. For every code point X assigned in this version of Unicode that is not specifically
		#    listed in Part 1, the following invariants must be true for all conformant
		#    implementations:
		#
		#      X == toNFC(X) == toNFD(X) == toNFKC(X) == toNFKD(X)

		$last9_c1s = array();
		$last_x = 0;
		$in_part1 = false;
        foreach ( $t as $line_num => $line ) {
			$line_num++;
			if ( '@Part' === substr( $line, 0, 5 ) ) {
				$in_part1 = ( '@Part1 ' === substr( $line, 0, 7 ) );
				continue;
			}
			if ( self::$doing_coverage && $in_part1 ) { // Shorten lengthy tests if doing code coverage.
				continue;
			}
            $t = explode( '#', $line );
            $t = explode( ';', $t[0] );

            if ( 6 === count( $t ) ) {
				$x = $in_part1 ? hexdec( $t[0] ) : 0;
                foreach ( $t as $k => $s ) {
                    $t = explode( ' ', $s );
                    $t = array_map( 'hexdec', $t );
                    $t = array_map( __CLASS__.'::chr', $t );
                    $c[$k] = implode( '', $t );
                }
				array_unshift( $c, '' ); // Make 1-based like in NormalizationTest.txt header.
				$last9_c1s[] = $c[1];
				if ( count( $last9_c1s ) > 9 ) {
					array_shift( $last9_c1s );
				}

				$this->assertSame( self::$true, UNFC_Normalizer::isNormalized( $c[2], UNFC_Normalizer::NFC ), "$line_num: {$line}c[2]=" . bin2hex( $c[2] ) );
				$this->assertSame( $c[2], UNFC_Normalizer::normalize( $c[1], UNFC_Normalizer::NFC ) );
				$this->assertSame( $c[2], UNFC_Normalizer::normalize( $c[2], UNFC_Normalizer::NFC ) );
				$this->assertSame( $c[2], UNFC_Normalizer::normalize( $c[3], UNFC_Normalizer::NFC ) );
				$this->assertSame( $c[4], UNFC_Normalizer::normalize( $c[4], UNFC_Normalizer::NFC ) );
				$this->assertSame( $c[4], UNFC_Normalizer::normalize( $c[5], UNFC_Normalizer::NFC ) );

				if ( class_exists( 'Normalizer' ) && self::$at_least_55_1 ) {
					if ( $c[2] !== $c[1] ) {
						$this->assertSame( self::$false, UNFC_Normalizer::isNormalized( $c[1], UNFC_Normalizer::NFC ) );
					}
					if ( ( ! self::$new_8_0_0_regex || ! preg_match( self::$new_8_0_0_regex, $c[1] ) ) && ( ! self::$new_9_0_0_regex || ! preg_match( self::$new_9_0_0_regex, $c[1] ) ) ) {
						$this->assertSame( $normalize_n = Normalizer::normalize( $c[1], Normalizer::NFC ), $normalize_t = UNFC_Normalizer::normalize( $c[1], UNFC_Normalizer::NFC ), "$line_num: {$line}c[1]=" . bin2hex( $c[1] ) . ", normalize_n=" . bin2hex( $normalize_n ) . ", normalize_t=" . bin2hex( $normalize_t ) );
					}
					$this->assertSame( Normalizer::normalize( $c[2], Normalizer::NFC ), UNFC_Normalizer::normalize( $c[2], UNFC_Normalizer::NFC ) );
					$this->assertSame( Normalizer::normalize( $c[3], Normalizer::NFC ), UNFC_Normalizer::normalize( $c[3], UNFC_Normalizer::NFC ) );
					if ( $c[2] !== $c[4] ) {
						$this->assertSame( Normalizer::isNormalized( $c[4], Normalizer::NFC ), UNFC_Normalizer::isNormalized( $c[4], UNFC_Normalizer::NFC ) );
					}
					$this->assertSame( Normalizer::normalize( $c[4], Normalizer::NFC ), UNFC_Normalizer::normalize( $c[4], UNFC_Normalizer::NFC ) );
					$this->assertSame( Normalizer::normalize( $c[5], Normalizer::NFC ), UNFC_Normalizer::normalize( $c[5], UNFC_Normalizer::NFC ) );

					if ( $last9_c1s ) {
						shuffle( $last9_c1s );
						$c1 = implode( '', $last9_c1s );
						if ( self::$new_8_0_0_regex ) {
							$c1 = preg_replace( self::$new_8_0_0_regex, '', $c1 );
						}
						if ( self::$new_9_0_0_regex ) {
							$c1 = preg_replace( self::$new_9_0_0_regex, '', $c1 );
						}
						$this->assertSame( Normalizer::normalize( $c1, Normalizer::NFC ), UNFC_Normalizer::normalize( $c1, UNFC_Normalizer::NFC ), "$line_num: {$line}c1=" . bin2hex( $c1 ) );
					}
				}

				$this->assertSame( $c[3], UNFC_Normalizer::normalize( $c[1], UNFC_Normalizer::NFD ), "$line_num: {$line}c[3]=" . bin2hex( $c[3] ) );
				$this->assertSame( $c[3], UNFC_Normalizer::normalize( $c[2], UNFC_Normalizer::NFD ) );
				$this->assertSame( $c[3], UNFC_Normalizer::normalize( $c[3], UNFC_Normalizer::NFD ) );
				$this->assertSame( $c[5], UNFC_Normalizer::normalize( $c[4], UNFC_Normalizer::NFD ) );
				$this->assertSame( $c[5], UNFC_Normalizer::normalize( $c[5], UNFC_Normalizer::NFD ) );

				$this->assertSame( $c[4], UNFC_Normalizer::normalize( $c[1], UNFC_Normalizer::NFKC ), "$line_num: {$line}c[4]=" . bin2hex( $c[4] ) );
				$this->assertSame( $c[4], UNFC_Normalizer::normalize( $c[2], UNFC_Normalizer::NFKC ) );
				$this->assertSame( $c[4], UNFC_Normalizer::normalize( $c[3], UNFC_Normalizer::NFKC ) );
				$this->assertSame( $c[4], UNFC_Normalizer::normalize( $c[4], UNFC_Normalizer::NFKC ) );
				$this->assertSame( $c[4], UNFC_Normalizer::normalize( $c[5], UNFC_Normalizer::NFKC ) );

				$this->assertSame( $c[5], UNFC_Normalizer::normalize( $c[1], UNFC_Normalizer::NFKD ) );
				$this->assertSame( $c[5], UNFC_Normalizer::normalize( $c[2], UNFC_Normalizer::NFKD ) );
				$this->assertSame( $c[5], UNFC_Normalizer::normalize( $c[3], UNFC_Normalizer::NFKD ) );
				$this->assertSame( $c[5], UNFC_Normalizer::normalize( $c[4], UNFC_Normalizer::NFKD ) );
				$this->assertSame( $c[5], UNFC_Normalizer::normalize( $c[5], UNFC_Normalizer::NFKD ) );

				if ( $x ) {
					for ( $i = $last_x + 1; $i < $x; $i++ ) {
						$c1 = self::chr( $i );
						if ( unfc_is_valid_utf8( $c1 ) ) {
							$this->assertSame( self::$true, UNFC_Normalizer::isNormalized( $c1, UNFC_Normalizer::NFC ), "$line_num: {$line}c1=" . bin2hex( $c1 ) );
							$this->assertSame( $c1, UNFC_Normalizer::normalize( $c1, UNFC_Normalizer::NFC ) );
						}
					}
					$last_x = $x;
				}
            }
        }
    }

    /**
	 * @ticket unfc_random
	 * @requires extension intl
     */
	function test_random() {
		require_once dirname( dirname( __FILE__ ) ) . '/tools/functions.php';

		if ( class_exists( 'Normalizer' ) ) {
			// Some known problematics.
			$strs = array(
				"\xcc\x83\xc3\x92\xd5\x9b", // \u0303\u00d2\u055b
				"\x72\x1c\xce\xaf", // r\u001c\u03af
				"\xe0\xbd\xb6\xe0\xbe\x81", // \u0f76\u0f81
			);
			for ( $i = 0, $len = count( $strs ); $i < $len; $i++ ) {
				$str = $strs[ $i ];
				$this->assertSame( Normalizer::isNormalized( $str ), UNFC_Normalizer::isNormalized( $str ) );
				$this->assertSame( Normalizer::normalize( $str ), UNFC_Normalizer::normalize( $str ) );
			}

			$num_tests = self::$doing_coverage ? 1 : 42; // Shorten lengthy tests if doing code coverage.
			global $unfc_nfc_maybes_or_reorders;
			for ( $i = 0; $i < 42; $i++ ) {
				$str = unfc_utf8_rand_ratio_str( rand( 100, 100000 ), 0.5, $unfc_nfc_maybes_or_reorders );
				if ( self::$new_8_0_0_regex ) {
					$str = preg_replace( self::$new_8_0_0_regex, '', $str );
				}
				if ( self::$new_9_0_0_regex ) {
					$str = preg_replace( self::$new_9_0_0_regex, '', $str );
				}
				$this->assertSame( Normalizer::isNormalized( $str ), UNFC_Normalizer::isNormalized( $str ) );
				$this->assertSame( Normalizer::normalize( $str ), UNFC_Normalizer::normalize( $str ) );
				unset( $str );
			}
		} else {
			$this->markTestSkipped( 'Tests_UNFC_Normalizer::test_random: no class Normalizer' );
		}
	}

	/**
	 * @ticket unfc_is_valid_utf8_true
	 * @dataProvider data_is_valid_utf8_true
	 */
	function test_is_valid_utf8_true( $str ) {
		$this->assertTrue( unfc_is_valid_utf8( $str ) );
		if ( version_compare( self::$pcre_version, '7.3', '>=' ) && version_compare( self::$pcre_version, '8.32', '!=' ) ) { // RFC 3629 compliant and without 8.32 regression (rejecting non-chars).
			$this->assertTrue( 1 === preg_match( '//u', $str ) );
		}
		if ( version_compare( PHP_VERSION, '5.3.4', '>=' ) ) { // RFC 3629 compliant.
			$this->assertTrue( '' === $str || '' !== htmlspecialchars( $str, ENT_NOQUOTES, 'UTF-8' ) );
		}
		$this->assertTrue( 0 === preg_match( UNFC_REGEX_IS_INVALID_UTF8_NOVERBS, $str ) );
		if ( version_compare( self::$pcre_version, '7.3', '>=' ) ) { // Verbs available.
			$this->assertTrue( 0 === preg_match( UNFC_REGEX_IS_INVALID_UTF8, $str ) );
		}
	}

	function data_is_valid_utf8_true() {
		$ret = array(
			array( "\x00" ), array( "a" ), array( "\x7f" ), array( "a\x7f" ), array( "\xc2\x80" ),
			array( "\xdf\xaf" ), array( "a\xdf\xbf" ), array( "\xdf\xbfb" ), array( "a\xde\xbfb" ), array( "\xe0\xa0\x80" ),
			array( "\xef\xbf\xbf" ), array( "a\xe1\x80\x80" ), array( "\xef\xb7\x90b" ), array( "a\xef\xbf\xafb" ), array( "\xf0\x90\x80\x80" ),
			array( "\xf4\x8f\xbf\xbf" ), array( "a\xf1\x80\x80\x80" ), array( "\xf2\x80\x80\x80b" ), array( "a\xf3\xbf\xbf\xbfb" ), array( "" ),
		);

		// From "tests/phpunit/tests/formatting/SeemsUtf8.php", "tests/phpunit/data/formatting/utf-8/utf-8.txt".
		$utf8_strings = array(
			array( "\xe7\xab\xa0\xe5\xad\x90\xe6\x80\xa1" ),
			array( "\x46\x72\x61\x6e\xc3\xa7\x6f\x69\x73\x20\x54\x72\x75\x66\x66\x61\x75\x74" ),
			array( "\xe1\x83\xa1\xe1\x83\x90\xe1\x83\xa5\xe1\x83\x90\xe1\x83\xa0\xe1\x83\x97\xe1\x83\x95\xe1\x83\x94\xe1\x83\x9a\xe1\x83\x9d" ),
			array( "\x42\x6a\xc3\xb6\x72\x6b\x20\x47\x75\xc3\xb0\x6d\x75\x6e\x64\x73\x64\xc3\xb3\x74\x74\x69\x72" ),
			array( "\xe5\xae\xae\xe5\xb4\x8e\xe3\x80\x80\xe9\xa7\xbf" ),
			array( "\xf0\x9f\x91\x8d" ),
		);

		$ret = array_merge( $ret, $utf8_strings );
		return $ret;
	}

	/**
	 * @ticket unfc_is_valid_utf8_false
	 * @dataProvider data_is_valid_utf8_false
	 */
	function test_is_valid_utf8_false( $str ) {
		$this->assertFalse( unfc_is_valid_utf8( $str ) );
		if ( version_compare( self::$pcre_version, '7.3', '>=' ) && version_compare( self::$pcre_version, '8.32', '!=' ) ) { // RFC 3629 compliant and without 8.32 regression (rejecting non-chars).
			$this->assertFalse( 1 === preg_match( '//u', $str ) );
		}
		if ( version_compare( PHP_VERSION, '5.3.4', '>=' ) ) { // RFC 3629 compliant.
			$this->assertFalse( '' === $str || '' !== htmlspecialchars( $str, ENT_NOQUOTES, 'UTF-8' ) );
		}
		$this->assertFalse( 0 === preg_match( UNFC_REGEX_IS_INVALID_UTF8_NOVERBS, $str ) );
		if ( version_compare( self::$pcre_version, '7.3', '>=' ) ) { // Verbs available.
			$this->assertFalse( 0 === preg_match( UNFC_REGEX_IS_INVALID_UTF8, $str ) );
		}
	}

	function data_is_valid_utf8_false() {
		$ret = array(
			array( "\x80" ), array( "\xff" ), array( "a\x81" ), array( "\x83b" ), array( "a\x81b" ),
			array( "ab\xff"), array( "\xc2\x7f" ), array( "\xc0\xb1" ), array( "\xc1\x81" ), array( "a\xc2\xc0" ),
			array( "a\xd0\x7fb" ), array( "ab\xdf\xc0" ), array( "\xe2\x80" ), array( "a\xe2\x80" ), array( "a\xe2\x80b" ),
			array( "\xf1\x80" ), array( "\xe1\x7f\x80" ), array( "\xe0\x9f\x80" ), array( "\xed\xa0\x80" ), array( "\xef\x7f\x80" ),
			array( "\xef\xbf\xc0" ), array( "\xc2\xa0\x80" ), array( "\xf0\x90\x80" ), array( "\xe2\xa0\x80\x80" ), array( "\xf5\x80\x80\x80" ),
			array( "\xf0\x8f\x80\x80" ), array( "\xf4\x90\x80\x80" ), array( "\xf5\x80\x80\x80\x80" ), array( "a\xf5\x80\x80\x80\x80" ), array( "a\xf5\x80\x80\x80\x80b" ),
			array( "a\xc2\x80\x80b" ), array( "a\xc2\x80\xef\xbf\xbf\x80c" ), array( "a\xc2\x80\xe2\x80\x80\xf3\x80\x80\x80\x80b" ), array( "\xe0\x80\xb1" ), array( "\xf0\x80\x80\xb1" ),
			array( "\xf8\x80\x80\x80\xb1" ), array( "\xfc\x80\x80\x80\x80\xb1" ),
		);

		// From "tests/phpunit/tests/formatting/SeemsUtf8.php", "tests/phpunit/data/formatting/big5.txt".
		$big5_strings = array(
			array( "\xaa\xa9\xa5\xbb" ), array( "\xa4\xc0\xc3\xfe" ), array( "\xc0\xf4\xb9\xd2" ), array( "\xa9\xca\xbd\xe8" ), array( "\xad\xba\xad\xb6" ),
		);

		$ret = array_merge( $ret, $big5_strings );
		return $ret;
	}

	/**
	 * @ticket unfc_is_valid_utf8_false_random
	 */
	function test_is_valid_utf8_false_random() {
		require_once dirname( dirname( __FILE__ ) ) . '/tools/functions.php';

		$num_tests = self::$doing_coverage ? 100 : 42000; // Shorten lengthy tests if doing code coverage.
		for ( $i = 0; $i < $num_tests; $i++ ) {
			$str = unfc_utf8_rand_ratio_str( 100, 0.1 );
			$this->assertFalse( unfc_is_valid_utf8( $str ) );
			if ( version_compare( self::$pcre_version, '7.3', '>=' ) ) { // RFC 3629 compliant.
				$this->assertFalse( 1 === preg_match( '//u', $str ) );
			}
			if ( version_compare( PHP_VERSION, '5.3.4', '>=' ) ) { // RFC 3629 compliant.
				$this->assertFalse( '' === $str || '' !== htmlspecialchars( $str, ENT_NOQUOTES, 'UTF-8' ) );
			}
			$this->assertFalse( 0 === preg_match( UNFC_REGEX_IS_INVALID_UTF8, $str ) );
		}
	}

    private static function chr($c)
    {
        if (0x80 > $c %= 0x200000) {
            return chr($c);
        }
        if (0x800 > $c) {
            return chr(0xC0 | $c >> 6).chr(0x80 | $c & 0x3F);
        }
        if (0x10000 > $c) {
            return chr(0xE0 | $c >> 12).chr(0x80 | $c >> 6 & 0x3F).chr(0x80 | $c & 0x3F);
        }

        return chr(0xF0 | $c >> 18).chr(0x80 | $c >> 12 & 0x3F).chr(0x80 | $c >> 6 & 0x3F).chr(0x80 | $c & 0x3F);
    }
}
