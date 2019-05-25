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

require_once 'Symfony/Normalizer.php';

/**
 * @group unfc
 * @group unfc_normalizer
 */
class Tests_UNFC_Normalizer extends WP_UnitTestCase {

	static $normalizer_state = array();

	static $new_cc_regex = '';
	static $new_8_0_0 = array( 0x8e3, 0xa69e, /*0xa69f,*/ 0xfe2e, 0xfe2f, 0x111ca, 0x1172b, ); // Combining class additions UCD 8.0.0 over 7.0.0
	static $new_9_0_0 = array( // Combining class additions (63) UCD 9.0.0 over 8.0.0
		0x8d4, 0x8d5, 0x8d6, 0x8d7, 0x8d8, 0x8d9, 0x8da, 0x8db, 0x8dc, 0x8dd, 0x8de, 0x8df, 0x8e0, 0x8e1,
		0x1dfb,
		0x11442, 0x11446, 0x11c3f,
		0x1e000, 0x1e001, 0x1e002, 0x1e003, 0x1e004, 0x1e005, 0x1e006,
		0x1e008, 0x1e009, 0x1e00a, 0x1e00b, 0x1e00c, 0x1e00d, 0x1e00e, 0x1e00f, 0x1e010, 0x1e011, 0x1e012, 0x1e013, 0x1e014, 0x1e015, 0x1e016, 0x1e017, 0x1e018,
		0x1e01b, 0x1e01c, 0x1e01d, 0x1e01e, 0x1e01f, 0x1e020, 0x1e021, 0x1e023, 0x1e024, 0x1e026, 0x1e027, 0x1e028, 0x1e029, 0x1e02a,
		0x1e944, 0x1e945, 0x1e946, 0x1e947, 0x1e948, 0x1e949, 0x1e94a,
	);
	static $new_10_0_0 = array( // Combining class additions (12) UCD 10.0.0 over 9.0.0
		0x0D3B, 0x0D3C, 0x1DF6, 0x1DF7, 0x1DF8, 0x1DF9, 0x11A34, 0x11A47, 0x11A99, 0x11D42, 0x11D44, 0x11D45,
	);
	static $new_11_0_0 = array( // Combining class additions (23) UCD 11.0.0 over 10.0.0
		0x07FD, 0x08D3, 0x09FE, 0x10D24, 0x10D25, 0x10D26, 0x10D27,
		0x10F46, 0x10F47, 0x10F48, 0x10F49, 0x10F4A, 0x10F4B, 0x10F4C, 0x10F4D, 0x10F4E, 0x10F4F, 0x10F50,
		0x1133B, 0x1145E, 0x11839, 0x1183A, 0x11D97,
	);
	static $new_12_0_0 = array( // Combining class additions (13) UCD 12.0.0 over 11.0.0
		0x0EBA, 0x119E0, 0x1E130, 0x1E131, 0x1E132, 0x1E133, 0x1E134, 0x1E135, 0x1E136, 0x1E2EC, 0x1E2ED, 0x1E2EE, 0x1E2EF,
	);
	// No new Combining class additions UCD 12.1.0 over 12.0.0

	static $at_least_55_1 = false;
	static $pcre_version = PCRE_VERSION;
	static $true = true;
	static $false = false;
	static $doing_coverage = false;
	static $unorm2 = false;
	static $REIWA = false;
	static $ignore_REIWA = false;

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
		self::$at_least_55_1 = version_compare( $icu_version, '55.1', '>=' ); // ICU 55.1 uses Unicode 7.0 (first used ICU 54.1).

		$new_cc = array();
		if ( version_compare( $icu_version, '56.1', '<' ) ) { // ICU 56.1 uses Unicode 8.0 (first use).
			$new_cc = array_merge( $new_cc, self::$new_8_0_0);
		}
		if ( version_compare( $icu_version, '58.1', '<' ) ) { // ICU 58.1 uses Unicode 9.0 (first use).
			$new_cc = array_merge( $new_cc, self::$new_9_0_0);
		}
		if ( version_compare( $icu_version, '60.1', '<' ) ) { // ICU 60.1 uses Unicode 10.0 (first use).
			$new_cc = array_merge( $new_cc, self::$new_10_0_0);
		}
		if ( version_compare( $icu_version, '62.1', '<' ) ) { // ICU 62.1 uses Unicode 11.0 (first use).
			$new_cc = array_merge( $new_cc, self::$new_11_0_0);
		}
		if ( version_compare( $icu_version, '64.1', '<' ) ) { // ICU 64.1 uses Unicode 12.0 (first use).
			$new_cc = array_merge( $new_cc, self::$new_12_0_0);
		}
		if ( $new_cc ) {
			self::$new_cc_regex = '/' . implode( '|', array_map( __CLASS__.'::chr', $new_cc ) ) . '/';
		}

		self::$pcre_version = substr( PCRE_VERSION, 0, strspn( PCRE_VERSION, '0123456789.' ) );

		// Normalizer::isNormalized() returns an integer on HHVM and a boolean on PHP
		list( self::$true, self::$false ) = defined( 'HHVM_VERSION' ) ? array( 1, 0 ) : array( true, false );

		self::$unorm2 = version_compare( PHP_VERSION, '7.3', '>=' ) && defined( 'INTL_ICU_VERSION' ) && version_compare( INTL_ICU_VERSION, '56', '>=' );
		self::$REIWA = self::chr( 0x32FF ); // Unicode 12.1.0 addition - avoid for comparison to PHP Normalizer built against ICU < 64.2.
		self::$ignore_REIWA = ! defined( 'INTL_ICU_VERSION' ) || version_compare( INTL_ICU_VERSION, '64.2', '<' );

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

			if ( ! self::$unorm2 ) {
				unset( $rpn['NFKC_CF'] ); // Only defined for PHP >= 7.3 and ICU >= 56
			}

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

        $rpn = new ReflectionClass( 'UNFC_BaseNormalizer' );

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

			$prop = $rpn->getProperty( 'kcCF' );
			$prop->setAccessible( true );
			$prop->setValue( null );

			$this->assertSame( "a", UNFC_Normalizer::getRawDecomposition( "A", UNFC_Normalizer::NFKC_CF ) );

			$prop = $rpn->getProperty( 'kcCF' );
			$prop->setAccessible( true );
			$prop->setValue( null );
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
        $this->assertSame( self::$true, UNFC_Normalizer::isNormalized( $c, UNFC_Normalizer::NFKC ) );
        $this->assertSame( self::$false, UNFC_Normalizer::isNormalized( $c, UNFC_Normalizer::NFKD ) );
        $this->assertSame( self::$true, UNFC_Normalizer::isNormalized( $c, UNFC_Normalizer::NFKC_CF ) );

        $this->assertSame( self::$false, UNFC_Normalizer::isNormalized( $d, UNFC_Normalizer::NFC ) );
        $this->assertSame( self::$true, UNFC_Normalizer::isNormalized( $d, UNFC_Normalizer::NFD ) );
        $this->assertSame( self::$false, UNFC_Normalizer::isNormalized( $d, UNFC_Normalizer::NFKC ) );
        $this->assertSame( self::$true, UNFC_Normalizer::isNormalized( $d, UNFC_Normalizer::NFKD ) );
        $this->assertSame( self::$false, UNFC_Normalizer::isNormalized( $d, UNFC_Normalizer::NFKC_CF ) );

        $this->assertSame( self::$false, UNFC_Normalizer::isNormalized( "\xFF" ) );

		$this->assertSame( self::$false, UNFC_Normalizer::isNormalized( "u\xcc\x88", UNFC_Normalizer::NFC ) ); // u umlaut.
		$this->assertSame( self::$false, UNFC_Normalizer::isNormalized( "u\xcc\x88\xed\x9e\xa0", UNFC_Normalizer::NFC ) ); // u umlaut + Hangul

		if ( class_exists( 'Normalizer' ) ) {
			$this->assertSame( $d, Normalizer::normalize( $c, Normalizer::NFD ) );

			$this->assertSame( Normalizer::isNormalized( '' ), UNFC_Normalizer::isNormalized( '' ) );
			$this->assertSame( Normalizer::isNormalized( 'abc' ), UNFC_Normalizer::isNormalized( 'abc' ) );

			$this->assertSame( Normalizer::isNormalized( $c ), UNFC_Normalizer::isNormalized( $c ) );
			$this->assertSame( Normalizer::isNormalized( $c, Normalizer::NFC ), UNFC_Normalizer::isNormalized( $c, UNFC_Normalizer::NFC ) );
			$this->assertSame( Normalizer::isNormalized( $c, Normalizer::NFD ), UNFC_Normalizer::isNormalized( $c, UNFC_Normalizer::NFD ) );
			$this->assertSame( Normalizer::isNormalized( $c, Normalizer::NFKC ), UNFC_Normalizer::isNormalized( $c, UNFC_Normalizer::NFKC ) );
			$this->assertSame( Normalizer::isNormalized( $c, Normalizer::NFKD ), UNFC_Normalizer::isNormalized( $c, UNFC_Normalizer::NFKD ) );
			if ( self::$unorm2 ) {
				$this->assertSame( Normalizer::isNormalized( $c, Normalizer::NFKC_CF ), UNFC_Normalizer::isNormalized( $c, UNFC_Normalizer::NFKC_CF ) );
			}

			$this->assertSame( Normalizer::isNormalized( $d, Normalizer::NFD ), UNFC_Normalizer::isNormalized( $d, UNFC_Normalizer::NFD ) );
			$this->assertSame( Normalizer::isNormalized( $d, Normalizer::NFC ), UNFC_Normalizer::isNormalized( $d, UNFC_Normalizer::NFC ) );
			$this->assertSame( Normalizer::isNormalized( $d, Normalizer::NFKC ), UNFC_Normalizer::isNormalized( $d, UNFC_Normalizer::NFKC ) );
			$this->assertSame( Normalizer::isNormalized( $d, Normalizer::NFKD ), UNFC_Normalizer::isNormalized( $d, UNFC_Normalizer::NFKD ) );
			if ( self::$unorm2 ) {
				$this->assertSame( Normalizer::isNormalized( $d, Normalizer::NFKC_CF ), UNFC_Normalizer::isNormalized( $d, UNFC_Normalizer::NFKC_CF ) );
			}

			$this->assertSame( Normalizer::isNormalized( "\xFF" ), UNFC_Normalizer::isNormalized( "\xFF" ) );

			$this->assertSame( self::$true, Normalizer::isNormalized( $d, Normalizer::NFD ) );
			$this->assertSame( self::$true, Normalizer::isNormalized( $d, Normalizer::NFKD ) );
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
		if ( class_exists( 'Normalizer' ) ) {
			if ( version_compare( PHP_VERSION, '7.3', '>=' ) ) { // Normalizer::NONE deprecated PHP 7.3 so suppress warning.
				$this->assertSame( $c, @Normalizer::normalize( $c, Normalizer::NONE ) );
			} else {
				$this->assertSame( $c, Normalizer::normalize( $c, Normalizer::NONE ) );
			}
		}

        $c = 'déjà 훈쇼™';
        $d = UNFC_Normalizer::normalize( $c, UNFC_Normalizer::NFD );
        $kc = UNFC_Normalizer::normalize( $c, UNFC_Normalizer::NFKC );
        $kd = UNFC_Normalizer::normalize( $c, UNFC_Normalizer::NFKD );
		if ( self::$unorm2 ) {
			$kc_cf = UNFC_Normalizer::normalize( $c, UNFC_Normalizer::NFKC_CF );
		}

        $this->assertSame( '', UNFC_Normalizer::normalize( '' ) );
		if ( class_exists( 'Normalizer' ) ) {
        	$this->assertSame( $c, Normalizer::normalize( $d ) );
        	$this->assertSame( $c, Normalizer::normalize( $d, Normalizer::NFC ) );
        	$this->assertSame( $d, Normalizer::normalize( $c, Normalizer::NFD ) );
        	$this->assertSame( $kc, Normalizer::normalize( $d, Normalizer::NFKC ) );
        	$this->assertSame( $kd, Normalizer::normalize( $c, Normalizer::NFKD ) );
			if ( self::$unorm2 ) {
				$this->assertSame( $kc_cf, Normalizer::normalize( $c, Normalizer::NFKC_CF ) );
			}
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
			$forms = array( 0, -1, 6, -2, PHP_INT_MAX, -PHP_INT_MAX, Normalizer::NONE, Normalizer::NFD, Normalizer::NFKD, Normalizer::NFC, Normalizer::NFKC );
			if ( self::$unorm2 ) {
				$forms[] = Normalizer::NFKC_CF;
			}

			foreach ( $forms as $form ) {
				$is_normalized = Normalizer::isNormalized( $string, $form );
				if ( Normalizer::NONE === $form && version_compare( PHP_VERSION, '7.3', '>=' ) ) { // Normalizer::NONE deprecated PHP 7.3 so suppress warning.
					$normalize = @Normalizer::normalize( $string, $form );
				} else {
					$normalize = Normalizer::normalize( $string, $form );
				}
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
			array( 'a' ), array( ' ' ), array( "\x80" ), array( "\xc2" ), array( "\xe0" ), array( "\xf0" ),
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
	 * @ticket unfc_conformance_12_1_0
     */
    function test_conformance_12_1_0() {

        $t = file( dirname( __FILE__ ) . '/UCD-12.1.0/NormalizationTest.txt' );
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
					if ( ( ! self::$new_cc_regex || ! preg_match( self::$new_cc_regex, $c[1] ) ) ) {
						$this->assertSame( $normalize_n = Normalizer::normalize( $c[1], Normalizer::NFC ), $normalize_t = UNFC_Normalizer::normalize( $c[1], UNFC_Normalizer::NFC ), "$line_num: {$line}c[1]=" . bin2hex( $c[1] ) . ", normalize_n=" . bin2hex( $normalize_n ) . ", normalize_t=" . bin2hex( $normalize_t ) . ", c[2]=" . bin2hex( $c[2] ) );
						$this->assertSame( $normalize_n = Normalizer::normalize( $c[3], Normalizer::NFC ), $normalize_t = UNFC_Normalizer::normalize( $c[3], UNFC_Normalizer::NFC ), "$line_num: {$line}c[1]=" . bin2hex( $c[1] ) . ", normalize_n=" . bin2hex( $normalize_n ) . ", normalize_t=" . bin2hex( $normalize_t ) . ", c[3]=" . bin2hex( $c[3] ) );
					}
					$this->assertSame( Normalizer::normalize( $c[2], Normalizer::NFC ), UNFC_Normalizer::normalize( $c[2], UNFC_Normalizer::NFC ) );
					if ( $c[2] !== $c[4] ) {
						$this->assertSame( Normalizer::isNormalized( $c[4], Normalizer::NFC ), UNFC_Normalizer::isNormalized( $c[4], UNFC_Normalizer::NFC ) );
					}
					$this->assertSame( Normalizer::normalize( $c[4], Normalizer::NFC ), UNFC_Normalizer::normalize( $c[4], UNFC_Normalizer::NFC ) );
					$this->assertSame( Normalizer::normalize( $c[5], Normalizer::NFC ), UNFC_Normalizer::normalize( $c[5], UNFC_Normalizer::NFC ) );

					if ( $last9_c1s ) {
						shuffle( $last9_c1s );
						$c1 = implode( '', $last9_c1s );
						if ( self::$new_cc_regex ) {
							$c1 = preg_replace( self::$new_cc_regex, '', $c1 );
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

				if ( class_exists( 'Normalizer' ) && self::$unorm2 ) {
					if ( self::$REIWA !== $c[1] || ! self::$ignore_REIWA ) {
						$this->assertSame( Normalizer::isNormalized( $c[1], Normalizer::NFKC_CF ), UNFC_Normalizer::isNormalized( $c[1], UNFC_Normalizer::NFKC_CF ) );
						$this->assertSame( Normalizer::normalize( $c[1], Normalizer::NFKC_CF ), UNFC_Normalizer::normalize( $c[1], UNFC_Normalizer::NFKC_CF ) );
						if ( 1 === strlen( $c[1] ) ) {
							$this->assertSame( Normalizer::getRawDecomposition( $c[1], Normalizer::NFKC ), UNFC_Normalizer::getRawDecomposition( $c[1], UNFC_Normalizer::NFKC ) );
							$this->assertSame( Normalizer::getRawDecomposition( $c[1], Normalizer::NFKD ), UNFC_Normalizer::getRawDecomposition( $c[1], UNFC_Normalizer::NFKD ) );
							$this->assertSame( Normalizer::getRawDecomposition( $c[1], Normalizer::NFC ), UNFC_Normalizer::getRawDecomposition( $c[1], UNFC_Normalizer::NFC ) );
							$this->assertSame( Normalizer::getRawDecomposition( $c[1], Normalizer::NFD ), UNFC_Normalizer::getRawDecomposition( $c[1], UNFC_Normalizer::NFD ) );
							$this->assertSame( Normalizer::getRawDecomposition( $c[1], Normalizer::NFKC_CF ), UNFC_Normalizer::getRawDecomposition( $c[1], UNFC_Normalizer::NFKC_CF ) );
						}
					}
				}

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
				if ( self::$unorm2 ) {
					$this->assertSame( Normalizer::isNormalized( $str, Normalizer::NFKC_CF ), UNFC_Normalizer::isNormalized( $str, UNFC_Normalizer::NFKC_CF ) );
					$this->assertSame( Normalizer::normalize( $str, Normalizer::NFKC_CF ), UNFC_Normalizer::normalize( $str, UNFC_Normalizer::NFKC_CF ) );
				}
			}

			$num_tests = self::$doing_coverage ? 1 : 42; // Shorten lengthy tests if doing code coverage.
			global $unfc_nfc_maybes_or_reorders;
			for ( $i = 0; $i < 42; $i++ ) {
				$str = unfc_utf8_rand_ratio_str( rand( 100, 100000 ), 0.5, $unfc_nfc_maybes_or_reorders );
				if ( self::$new_cc_regex ) {
					$str = preg_replace( self::$new_cc_regex, '', $str );
				}
				$this->assertSame( Normalizer::isNormalized( $str ), UNFC_Normalizer::isNormalized( $str ) );
				$this->assertSame( Normalizer::normalize( $str ), UNFC_Normalizer::normalize( $str ) );
				if ( self::$unorm2 ) {
					$this->assertSame( Normalizer::isNormalized( $str, Normalizer::NFKC_CF ), UNFC_Normalizer::isNormalized( $str, UNFC_Normalizer::NFKC_CF ) );
					$this->assertSame( Normalizer::normalize( $str, Normalizer::NFKC_CF ), UNFC_Normalizer::normalize( $str, UNFC_Normalizer::NFKC_CF ) );
				}
				unset( $str );
			}
		} else {
			$this->markTestSkipped( 'Tests_UNFC_Normalizer::test_random: no class Normalizer' );
		}
	}

	/**
	 * @ticket unfc_nfkc_cf
	 * @dataProvider data_nfkc_cf
	 */
	function test_nfkc_cf( $str, $expected ) {

		$actual = UNFC_Normalizer::normalize( $str, UNFC_Normalizer::NFKC_CF );
		$this->assertSame( $actual, $expected );
		$this->assertSame( self::$true, UNFC_Normalizer::isNormalized( $actual, UNFC_Normalizer::NFKC_CF ) );
		$this->assertSame( UNFC_Normalizer::isNormalized( $str, UNFC_Normalizer::NFKC_CF ), $str === $expected );

		if ( class_exists( 'Normalizer' ) && self::$unorm2 ) {
			$n = Normalizer::normalize( $str, Normalizer::NFKC_CF );
			$this->assertSame( $n, $expected ); // Check data good.
			$this->assertSame( $actual, $n );
			$this->assertSame( UNFC_Normalizer::isNormalized( $str, UNFC_Normalizer::NFKC_CF ), Normalizer::isNormalized( $str, Normalizer::NFKC_CF ) );
			$this->assertSame( UNFC_Normalizer::isNormalized( $actual, UNFC_Normalizer::NFKC_CF ), Normalizer::isNormalized( $actual, Normalizer::NFKC_CF ) );
		}
	}

	function data_nfkc_cf() {

		// Data from php-7.3.5/ext/intl/tests/normalizer_normalize_kc_cf.phpt

		$char_a_diaeresis = "\xC3\xA4";	// 'LATIN SMALL LETTER A WITH DIAERESIS' (U+00E4)
		$char_a_ring = "\xC3\xA5";		// 'LATIN SMALL LETTER A WITH RING ABOVE' (U+00E5)
		$char_o_diaeresis = "\xC3\xB6";    // 'LATIN SMALL LETTER O WITH DIAERESIS' (U+00F6)

		$char_angstrom_sign = "\xE2\x84\xAB"; // 'ANGSTROM SIGN' (U+212B)
		$char_A_ring = "\xC3\x85";	// 'LATIN CAPITAL LETTER A WITH RING ABOVE' (U+00C5)

		$char_ohm_sign = "\xE2\x84\xA6";	// 'OHM SIGN' (U+2126)
		$char_omega = "\xCE\xA9";  // 'GREEK CAPITAL LETTER OMEGA' (U+03A9)
		$char_small_omega = "\xCF\x89"; // 'GREEK SMALL LETTER OMEGA' (U+03C9)

		$char_combining_ring_above = "\xCC\x8A";  // 'COMBINING RING ABOVE' (U+030A)

		$char_fi_ligature = "\xEF\xAC\x81";  // 'LATIN SMALL LIGATURE FI' (U+FB01)

		$char_long_s_dot = "\xE1\xBA\x9B";	// 'LATIN SMALL LETTER LONG S WITH DOT ABOVE' (U+1E9B)
		$char_small_s_dot = "\xE1\xB9\xA1"; // 'LATIN SMALL LETTER S WITH DOT ABOVE' (U+1E61)

		// Data from icu4c-64_2-src/source/test/intltest/tstnorm.cpp

		$u0308 = self::chr( 0x0308 ); // 'COMBINING DIAERESIS' (U+0308)
		$u00AD = self::chr( 0x00AD ); // 'SOFT HYPHEN' (U+00AD)
		$u0323 = self::chr( 0x0323 ); // 'COMBINING DOT BELOW' (U+0323)
		$u1100 = self::chr( 0x1100 ); // 'HANGUL CHOSEONG KIYEOK' (U+1100)
		$u1161 = self::chr( 0x1161 ); // 'HANGUL JUNGSEONG A' (U+1161)
		$u11A8 = self::chr( 0x11A8 ); // 'HANGUL JONGSEONG KIYEOK' (U+11A8)
		$u3133 = self::chr( 0x3133 ); // 'HANGUL LETTER KIYEOK-SIOS' (U+3133)

		$ret = array(
			array('ABC', 'abc'),
			array('abc', 'abc'),
			array($char_a_diaeresis . '||' . $char_a_ring . '||' . $char_o_diaeresis, $char_a_diaeresis . '||' . $char_a_ring . '||' . $char_o_diaeresis),
			array($char_angstrom_sign . '||' . $char_A_ring . '||' . 'A' . $char_combining_ring_above, $char_a_ring . '||' . $char_a_ring . '||' . $char_a_ring),
			array($char_ohm_sign . '||' . $char_omega, "\xCF\x89" . '||' . "\xCF\x89"),
			array($char_fi_ligature, 'fi'),
			array($char_long_s_dot, $char_small_s_dot),

			array(
				"  AÄA{$u0308}A{$u0308}{$u00AD}{$u0323}Ä{$u0323},{$u00AD}{$u1100}{$u1161}가{$u11A8}가{$u3133}  ",
				"  aääạ{$u0308}ạ{$u0308},가각갃  "
			),

			array( 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz' ),
			array( $u00AD, '' ),
			array( self::chr( 0xFFDA ), "\xe1\x85\xb3" ),
			array( self::chr( 0xFDFA ), "\xd8\xb5\xd9\x84\xd9\x89\x20\xd8\xa7\xd9\x84\xd9\x84\xd9\x87\x20\xd8\xb9\xd9\x84\xd9\x8a\xd9\x87\x20\xd9\x88\xd8\xb3\xd9\x84\xd9\x85" ),
			array( "\xF5", false ), // Invalid UTF-8
			array( "", "" ),
			array( self::chr( 0xC4 ), self::chr( 0xE4 ) ), // LATIN CAPITAL LETTER A WITH DIAERESIS
			array( self::chr( 0xE4 ), self::chr( 0xE4 ) ),
			array( self::chr( 0x1E08 ), self::chr( 0x1E09 ) ), // LATIN CAPITAL LETTER C WITH CEDILLA AND ACUTE
			array( self::chr( 0x212B ), self::chr( 0xE5 ) ), // ANGSTROM SIGN
			array( self::chr( 0xAC00 ), self::chr( 0xAC00 ) ), // HANGUL SYLLABLE GA
			array( self::chr( 0xAC01 ), self::chr( 0xAC01 ) ), // HANGUL SYLLABLE GAG
			array( self::chr( 0x0130 ), self::chr( 0x69 ) . self::chr( 0x0307 ) ), // LATIN CAPITAL LETTER I WITH DOT ABOVE
			array( self::chr( 0x0132 ), self::chr( 0x69 ) . self::chr( 0x6A ) ), // LATIN CAPITAL LIGATURE IJ
			array( self::chr( 0x0133 ), self::chr( 0x69 ) . self::chr( 0x6A ) ), // LATIN SMALL LIGATURE IJ
			array( self::chr( 0x0134 ), self::chr( 0x0135 ) ), // LATIN CAPITAL LETTER J WITH CIRCUMFLEX
			array( self::chr( 0x2FA1D ), self::chr( 0x2A600 ) ), // CJK COMPATIBILITY IDEOGRAPH-2FA1D
			array( self::chr( 0x2FA1E ), self::chr( 0x2FA1E ) ), // Unassigned
			array( self::chr( 0xE0000 ), '' ), // Unassigned
			array( self::chr( 0xE0001 ), '' ), // LANGUAGE TAG
			array( self::chr( 0xE0FFF ), '' ), // Unassigned
			array( self::chr( 0xE2000 ), self::chr( 0xE2000 ) ), // Unassigned
			array( self::chr( 0xF0000 ), self::chr( 0xF0000 ) ), // <Plane 15 Private Use, First>
		);

		$concat_str = $concat_expected = '';
		foreach ( $ret as $r ) {
			if ( false !== $r[1] ) {
				$concat_str .= $r[0];
				$concat_expected .= $r[1];
			}
		}
		$ret[] = array( $concat_str, $concat_expected );

		return $ret;
	}

	/**
	 * @ticket unfc_get_raw_decomposition
	 * @dataProvider data_get_raw_decomposition
	 */
	function test_get_raw_decomposition($c, $form_expecteds) {

		foreach ( $form_expecteds as $form => $expected ) {
			$actual = UNFC_Normalizer::getRawDecomposition( $c, $form );
			$this->assertSame( $actual, $expected );
			if ( class_exists( 'Normalizer' ) && self::$unorm2 ) {
				$n = Normalizer::getRawDecomposition( $c, $form );
				$this->assertSame( $expected, $n ); // Check data good.
				$this->assertSame( $actual, $n );
			}
			if ( UNFC_Normalizer::NFC === $form ) { // Default arg check.
				$this->assertSame( $actual, UNFC_Normalizer::getRawDecomposition( $c ) );
				if ( class_exists( 'Normalizer' ) && self::$unorm2 ) {
					$this->assertSame( $actual, Normalizer::getRawDecomposition( $c ) );
				}
			}
		}
	}

	function data_get_raw_decomposition() {
		return array(
			// From php-7.3.5/ext/intl/tests/normalizer_get_raw_decomposition.phpt
			array( "a", array(
				UNFC_Normalizer::NFKC => null,
				UNFC_Normalizer::NFKD => null,
				UNFC_Normalizer::NFC => null,
				UNFC_Normalizer::NFD => null,
				UNFC_Normalizer::NFKC_CF => null,
				UNFC_Normalizer::NONE => '',
				0 => '',
			) ),
			array( self::chr( 0xFFDA ) /* HALFWIDTH HANGUL LETTER EU */, array(
				UNFC_Normalizer::NFKC => "\xe3\x85\xa1", // U+3161 compatibility decomposition (with recursive decomposition to U+1173 not done).
				UNFC_Normalizer::NFKD => "\xe3\x85\xa1",
				UNFC_Normalizer::NFC => null,
				UNFC_Normalizer::NFD => null,
				UNFC_Normalizer::NFKC_CF => "\xe1\x85\xb3", // U+1173
				UNFC_Normalizer::NONE => '',
				0 => '',
			) ),
			array( self::chr( 0xFDFA ) /* ARABIC LIGATURE SALLALLAHOU ALAYHE WASALLAM */, array (
				UNFC_Normalizer::NFKC => "\xd8\xb5\xd9\x84\xd9\x89\x20\xd8\xa7\xd9\x84\xd9\x84\xd9\x87\x20\xd8\xb9\xd9\x84\xd9\x8a\xd9\x87\x20\xd9\x88\xd8\xb3\xd9\x84\xd9\x85", // Compatibility.
				UNFC_Normalizer::NFKD => "\xd8\xb5\xd9\x84\xd9\x89\x20\xd8\xa7\xd9\x84\xd9\x84\xd9\x87\x20\xd8\xb9\xd9\x84\xd9\x8a\xd9\x87\x20\xd9\x88\xd8\xb3\xd9\x84\xd9\x85",
				UNFC_Normalizer::NFC => null,
				UNFC_Normalizer::NFD => null,
				UNFC_Normalizer::NFKC_CF => "\xd8\xb5\xd9\x84\xd9\x89\x20\xd8\xa7\xd9\x84\xd9\x84\xd9\x87\x20\xd8\xb9\xd9\x84\xd9\x8a\xd9\x87\x20\xd9\x88\xd8\xb3\xd9\x84\xd9\x85",
				UNFC_Normalizer::NONE => '',
				0 => '',
			) ),
			array( "", array( // Single char required.
				UNFC_Normalizer::NFKC => null,
				UNFC_Normalizer::NFKD => null,
				UNFC_Normalizer::NFC => null,
				UNFC_Normalizer::NFD => null,
				UNFC_Normalizer::NFKC_CF => null,
				UNFC_Normalizer::NONE => null,
				0 => null,
			) ),
			array( "aa", array( // Single char required.
				UNFC_Normalizer::NFKC => null,
				UNFC_Normalizer::NFKD => null,
				UNFC_Normalizer::NFC => null,
				UNFC_Normalizer::NFD => null,
				UNFC_Normalizer::NFKC_CF => null,
				UNFC_Normalizer::NONE => null,
				0 => null,
			) ),
			array( "\xC2\xA2a", array( UNFC_Normalizer::NFKC => null ) ), // Additional checks for single char required with multi-byte UTF-8.
			array( "\xE0\xA4\xB9a", array( UNFC_Normalizer::NFKC => null ) ),
			array( "\xF0\x90\x8D\x88a", array( UNFC_Normalizer::NFKC => null ) ),
			array( "\xF5", array( // Invalid UTF-8.
				UNFC_Normalizer::NFKC => null,
				UNFC_Normalizer::NFKD => null,
				UNFC_Normalizer::NFC => null,
				UNFC_Normalizer::NFD => null,
				UNFC_Normalizer::NFKC_CF => null,
				UNFC_Normalizer::NONE => null,
				0 => null,
			) ),

			// From icu4c-64_2-src/source/test/cintltst/cnormtst.c
			array( " ", array( UNFC_Normalizer::NFKC => null ) ),
			array( self::chr( 0xE4 ) /* LATIN SMALL LETTER A WITH DIAERESIS */, array(
				UNFC_Normalizer::NFKC => "a" . self::chr( 0x0308 ),
				UNFC_Normalizer::NFKD => "a" . self::chr( 0x0308 ),
				UNFC_Normalizer::NFC => "a" . self::chr( 0x0308 ), // Canonical.
				UNFC_Normalizer::NFKC_CF => "a" . self::chr( 0x0308 ),
			) ),
			array( self::chr( 0x1E08 ) /* LATIN CAPITAL LETTER C WITH CEDILLA AND ACUTE */, array(
				UNFC_Normalizer::NFKC => self::chr( 0xC7 ) . self::chr( 0x0301 ), // U+00C7 LATIN CAPITAL LETTER C WITH CEDILLA
				UNFC_Normalizer::NFC => self::chr( 0xC7 ) . self::chr( 0x0301 ), // Canonical.
				UNFC_Normalizer::NFKC_CF => self::chr( 0x1E09 ), // U+1E09 LATIN SMALL LETTER C WITH CEDILLA AND ACUTE
			) ),
			array( self::chr( 0x212B ) /* ANGSTROM SIGN */, array(
				UNFC_Normalizer::NFKC => self::chr( 0xC5 ), // LATIN CAPITAL LETTER A WITH RING ABOVE
				UNFC_Normalizer::NFKC_CF => self::chr( 0xE5 ), // LATIN SMALL LETTER A WITH RING ABOVE
			) ),
			array( self::chr( 0xAC00 ), /* <Hangul Syllable, First> GA */ array(
				UNFC_Normalizer::NFKC => self::chr( 0x1100 ) . self::chr( 0x1161 ),
				UNFC_Normalizer::NFKD => self::chr( 0x1100 ) . self::chr( 0x1161 ),
				UNFC_Normalizer::NFC => self::chr( 0x1100 ) . self::chr( 0x1161 ),
				UNFC_Normalizer::NFD => self::chr( 0x1100 ) . self::chr( 0x1161 ),
				UNFC_Normalizer::NFKC_CF => self::chr( 0x1100 ) . self::chr( 0x1161 ),
				UNFC_Normalizer::NONE => '',
				0 => '',
			) ),
			array( self::chr( 0xAC01 ) /* A Hangul LVT syllable has a raw decomposition of an LV syllable + T. */, array(
				UNFC_Normalizer::NFKC => self::chr( 0xAC00 ) . self::chr( 0x11A8 ),
				UNFC_Normalizer::NFKD => self::chr( 0xAC00 ) . self::chr( 0x11A8 ),
				UNFC_Normalizer::NFC => self::chr( 0xAC00 ) . self::chr( 0x11A8 ),
				UNFC_Normalizer::NFD => self::chr( 0xAC00 ) . self::chr( 0x11A8 ),
				UNFC_Normalizer::NFKC_CF => self::chr( 0xAC00 ) . self::chr( 0x11A8 ),
			) ),
			array( self::chr( 0xAC19 ), array( UNFC_Normalizer::NFKC => self::chr( 0xAC00 ) . self::chr( 0x11C0 ) ) ), // c2 == 25
			array( self::chr( 0xBBF8 ), array( UNFC_Normalizer::NFKC => self::chr( 0x1106 ) . self::chr( 0x1175 ) ) ), // LV
			array( self::chr( 0xBBF9 ), array( UNFC_Normalizer::NFKC => self::chr( 0xBBF8 ) . self::chr( 0x11A8 ) ) ), // LVT with c2 == 1
			array( self::chr( 0xBC13 ), array( UNFC_Normalizer::NFKC => self::chr( 0xBBF8 ) . self::chr( 0x11C2 ) ) ), // LVT with c2 == 27
			array( self::chr( 0xD4DB ), array( UNFC_Normalizer::NFKC => self::chr( 0xD4CC ) . self::chr( 0x11B6 ) ) ), // LVT with c2 == 15

			// Canonical/compatibility.
			array( self::chr( 0x0130 ) /* LATIN CAPITAL LETTER I WITH DOT ABOVE */, array(
				UNFC_Normalizer::NFKC => self::chr( 0x49 ) . self::chr( 0x0307 ),
				UNFC_Normalizer::NFKD => self::chr( 0x49 ) . self::chr( 0x0307 ),
				UNFC_Normalizer::NFC => self::chr( 0x49 ) . self::chr( 0x0307 ),
				UNFC_Normalizer::NFD => self::chr( 0x49 ) . self::chr( 0x0307 ),
				UNFC_Normalizer::NFKC_CF => self::chr( 0x69 ) . self::chr( 0x0307 ),
			) ),
			array( self::chr( 0x0132 ) /* LATIN CAPITAL LIGATURE IJ */, array(
				UNFC_Normalizer::NFKC => self::chr( 0x49 ) . self::chr( 0x4A ),
				UNFC_Normalizer::NFKD => self::chr( 0x49 ) . self::chr( 0x4A ),
				UNFC_Normalizer::NFC => null,
				UNFC_Normalizer::NFD => null,
				UNFC_Normalizer::NFKC_CF => self::chr( 0x69 ) . self::chr( 0x6A ),
			) ),
			array( self::chr( 0x0133 ) /* LATIN SMALL LIGATURE IJ */, array(
				UNFC_Normalizer::NFKC => self::chr( 0x69 ) . self::chr( 0x6A ),
				UNFC_Normalizer::NFC => null,
				UNFC_Normalizer::NFKC_CF => self::chr( 0x69 ) . self::chr( 0x6A ),
			) ),
			array( self::chr( 0x0134 ) /* LATIN CAPITAL LETTER J WITH CIRCUMFLEX */, array(
				UNFC_Normalizer::NFKC => self::chr( 0x4A ) . self::chr( 0x0302 ),
				UNFC_Normalizer::NFC => self::chr( 0x4A ) . self::chr( 0x0302 ),
				UNFC_Normalizer::NFKC_CF => self::chr( 0x0135 ),
			) ),

			// Boundary and U+E0000..E0FFF cases.
			array( self::chr( 0x2FA1E ) /* Unassigned */ , array(
				UNFC_Normalizer::NFKC => null,
				UNFC_Normalizer::NFC => null,
				UNFC_Normalizer::NFKC_CF => null,
			) ),
			array( self::chr( 0xE0000 ) /* Tag */, array(
				UNFC_Normalizer::NFKC => null,
				UNFC_Normalizer::NFC => null,
				UNFC_Normalizer::NFKC_CF => '',
			) ),
			array( self::chr( 0xE0001 ) /* LANGUAGE TAG */, array(
				UNFC_Normalizer::NFKC => null,
				UNFC_Normalizer::NFC => null,
				UNFC_Normalizer::NFKC_CF => '',
			) ),
			array( self::chr( 0xE0FFF ) /* Unassigned */, array(
				UNFC_Normalizer::NFKC => null,
				UNFC_Normalizer::NFC => null,
				UNFC_Normalizer::NFKC_CF => '',
			) ),
			array( self::chr( 0xE2000 ) /* Unassigned */, array(
				UNFC_Normalizer::NFKC => null,
				UNFC_Normalizer::NFC => null,
				UNFC_Normalizer::NFKC_CF => null,
			) ),
			array( self::chr( 0xF0000 ) /* <Plane 15 Private Use, First> */, array(
				UNFC_Normalizer::NFKC => null,
				UNFC_Normalizer::NFC => null,
				UNFC_Normalizer::NFKC_CF => null,
			) ),
			array( "A", array(
				UNFC_Normalizer::NFKC => null,
				UNFC_Normalizer::NFKC_CF => "a",
			) ),
		);
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
