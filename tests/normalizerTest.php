<?php
/**
 * Test TLN_Normalizer.
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
 * @group tln
 * @group tln_normalizer
 */
class Tests_TLN_Normalizer extends WP_UnitTestCase {

	static $normalizer_state = array();
	static $new_8_0_0 = array( 0x8e3, 0xa69e, /*0xa69f,*/ 0xfe2e, 0xfe2f, 0x111ca, 0x1172b, ); // Combining class additions UCD 8.0.0 over 7.0.0
	static $new_8_0_0_regex = '';
	static $at_least_55_1 = false;
	static $true = true;
	static $false = false;
	static $doing_coverage = false;

	static function wpSetUpBeforeClass() {
		global $tlnormalizer;
		self::$normalizer_state = array( $tlnormalizer->dont_js, $tlnormalizer->dont_filter, $tlnormalizer->no_normalizer );
		$tlnormalizer->dont_js = true;
		$tlnormalizer->dont_filter = true;
		$tlnormalizer->no_normalizer = true;
		$tlnormalizer->load_tln_normalizer_class();

		$dirname = dirname( dirname( __FILE__ ) );
		require_once $dirname . '/tools/functions.php';

		$icu_version = tln_icu_version();
		self::$at_least_55_1 = version_compare( $icu_version, '55.1', '>=' );

		if ( version_compare( $icu_version, '56.1', '<' ) ) {
			// Enable if using intl built with icu less than 56.1
			self::$new_8_0_0_regex = '/' . implode( '|', array_map( __CLASS__.'::chr', self::$new_8_0_0 ) ) . '/';
		}

		// Normalizer::isNormalized() returns an integer on HHVM and a boolean on PHP
		list( self::$true, self::$false ) = defined( 'HHVM_VERSION' ) ? array( 1, 0 ) : array( true, false );

		global $argv;
		self::$doing_coverage = ! empty( preg_grep( '/--coverage/', $argv ) );
	}

	static function wpTearDownAfterClass() {
		global $tlnormalizer;
		list( $tlnormalizer->dont_js, $tlnormalizer->dont_filter, $tlnormalizer->no_normalizer ) = self::$normalizer_state;
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
	 * @ticket tln_constants
	 * @requires extension intl
	 */
    function test_constants() {

		if ( class_exists( 'Normalizer' ) ) {
			$rpn = new ReflectionClass( 'TLN_Normalizer' );
			$rin = new ReflectionClass( 'Normalizer' );

			$rpn = $rpn->getConstants();
			$rin = $rin->getConstants();

			ksort( $rpn );
			ksort( $rin );

			$this->assertSame( $rin, $rpn );
		}
    }

	/**
	 * @ticket tln_props
	 */
    function test_props() {

        $rpn = new ReflectionClass( 'TLN_Normalizer' );

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

			$this->assertSame( "\xc3\xbc", TLN_Normalizer::normalize( "u\xcc\x88" ) );
		}
    }

    /**
	 * @ticket tln_is_normalized
     */
    function test_is_normalized() {

        $c = 'déjà';
        $d = TLN_Normalizer::normalize( $c, TLN_Normalizer::NFD );

        $this->assertSame( self::$true, TLN_Normalizer::isNormalized( '' ) );
        $this->assertSame( self::$true, TLN_Normalizer::isNormalized( 'abc' ) );
        $this->assertSame( self::$true, TLN_Normalizer::isNormalized( $c ) );
        $this->assertSame( self::$true, TLN_Normalizer::isNormalized( $c, TLN_Normalizer::NFC ) );
        $this->assertSame( self::$false, TLN_Normalizer::isNormalized( $c, TLN_Normalizer::NFD ) );
        $this->assertSame( self::$false, TLN_Normalizer::isNormalized( $d, TLN_Normalizer::NFC ) );
        $this->assertSame( self::$false, TLN_Normalizer::isNormalized( "\xFF" ) );

        $this->assertSame( self::$true, TLN_Normalizer::isNormalized( $d, TLN_Normalizer::NFD ) );
		$this->assertSame( self::$false, TLN_Normalizer::isNormalized( "u\xcc\x88", TLN_Normalizer::NFC ) ); // u umlaut.
		$this->assertSame( self::$false, TLN_Normalizer::isNormalized( "u\xcc\x88\xed\x9e\xa0", TLN_Normalizer::NFC ) ); // u umlaut + Hangul

		if ( class_exists( 'Normalizer' ) ) {
        	$this->assertSame( $d, Normalizer::normalize( $c, Normalizer::NFD ) );

        	$this->assertSame( Normalizer::isNormalized( '' ), TLN_Normalizer::isNormalized( '' ) );
        	$this->assertSame( Normalizer::isNormalized( 'abc' ), TLN_Normalizer::isNormalized( 'abc' ) );
        	$this->assertSame( Normalizer::isNormalized( $c ), TLN_Normalizer::isNormalized( $c ) );
        	$this->assertSame( Normalizer::isNormalized( $c, Normalizer::NFC ), TLN_Normalizer::isNormalized( $c, TLN_Normalizer::NFC ) );
        	$this->assertSame( Normalizer::isNormalized( $c, Normalizer::NFD ), TLN_Normalizer::isNormalized( $c, TLN_Normalizer::NFD ) );
        	$this->assertSame( Normalizer::isNormalized( $d, Normalizer::NFC ), TLN_Normalizer::isNormalized( $d, TLN_Normalizer::NFC ) );
        	$this->assertSame( Normalizer::isNormalized( "\xFF" ), TLN_Normalizer::isNormalized( "\xFF" ) );

        	$this->assertSame( self::$true, Normalizer::isNormalized( $d, Normalizer::NFD ) );
			$this->assertSame( self::$false, Normalizer::isNormalized( "u\xcc\x88", Normalizer::NFC ) ); // u umlaut.
			$this->assertSame( self::$false, Normalizer::isNormalized( "u\xcc\x88\xed\x9e\xa0", Normalizer::NFC ) ); // u umlaut + Hangul
		}
    }

    /**
	 * @ticket tln_normalize
     */
    function test_normalize() {

		if ( class_exists( 'Normalizer' ) ) {
			$c = Normalizer::normalize( 'déjà', TLN_Normalizer::NFC ).Normalizer::normalize( '훈쇼™', TLN_Normalizer::NFD );
			$this->assertSame( $c, TLN_Normalizer::normalize( $c, TLN_Normalizer::NONE ) );
		}
        $c = TLN_Normalizer::normalize( 'déjà', TLN_Normalizer::NFC ).TLN_Normalizer::normalize( '훈쇼™', TLN_Normalizer::NFD );
        $this->assertSame( $c, TLN_Normalizer::normalize( $c, TLN_Normalizer::NONE ) );
        if ( class_exists( 'Normalizer' ) ) $this->assertSame( $c, Normalizer::normalize( $c, Normalizer::NONE ) );

        $c = 'déjà 훈쇼™';
        $d = TLN_Normalizer::normalize( $c, TLN_Normalizer::NFD );
        $kc = TLN_Normalizer::normalize( $c, TLN_Normalizer::NFKC );
        $kd = TLN_Normalizer::normalize( $c, TLN_Normalizer::NFKD );

        $this->assertSame( '', TLN_Normalizer::normalize( '' ) );
		if ( class_exists( 'Normalizer' ) ) {
        	$this->assertSame( $c, Normalizer::normalize( $d ) );
        	$this->assertSame( $c, Normalizer::normalize( $d, Normalizer::NFC ) );
        	$this->assertSame( $d, Normalizer::normalize( $c, Normalizer::NFD ) );
        	$this->assertSame( $kc, Normalizer::normalize( $d, Normalizer::NFKC ) );
        	$this->assertSame( $kd, Normalizer::normalize( $c, Normalizer::NFKD ) );
		}

        $this->assertSame( self::$false, TLN_Normalizer::normalize( $c, -1 ) );
        $this->assertFalse( TLN_Normalizer::normalize( "\xFF" ) );
    }

	/**
	 * @ticket tln_args_compat
	 * @dataProvider data_args_compat
	 * @requires extension intl
	 */
	function test_args_compat( $string ) {

		if ( class_exists( 'Normalizer' ) ) {
			$forms = array( 0, -1, 6, -2, PHP_INT_MAX, -PHP_INT_MAX, Normalizer::NONE, Normalizer::NFD, Normalizer::NFKD, Normalizer::NFC, Normalizer::NFKD );

			foreach ( $forms as $form ) {
				$is_normalized = Normalizer::isNormalized( $string, $form );
				$normalize = Normalizer::normalize( $string, $form );
				$tln_is_normalized = TLN_Normalizer::isNormalized( $string, $form );
				$tln_normalize = TLN_Normalizer::normalize( $string, $form );

				$this->assertSame( $is_normalized, $tln_is_normalized );
				$this->assertSame( $normalize, $tln_normalize );
			}
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
	 * @ticket tln_mbstring
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

		$this->assertSame( self::$true, TLN_Normalizer::isNormalized( 'abc' ) );
		$this->assertSame( $encoding, mb_internal_encoding() );
		$this->assertSame( self::$true, TLN_Normalizer::isNormalized( "\xe2\x8e\xa1" ) );
		$this->assertSame( $encoding, mb_internal_encoding() );
		$this->assertSame( self::$false, TLN_Normalizer::isNormalized( "u\xcc\x88" ) );
		$this->assertSame( $encoding, mb_internal_encoding() );

		$this->assertSame( 'abc', TLN_Normalizer::normalize( 'abc' ) );
		$this->assertSame( $encoding, mb_internal_encoding() );
		$this->assertSame( "\xe2\x8e\xa1", TLN_Normalizer::normalize( "\xe2\x8e\xa1" ) );
		$this->assertSame( $encoding, mb_internal_encoding() );
		$this->assertSame( "\xc3\xbc", TLN_Normalizer::normalize( "u\xcc\x88" ) );
		$this->assertSame( $encoding, mb_internal_encoding() );

		if ( version_compare( PHP_VERSION, '5.3', '>=' ) ) { // For availability of ReflectionClass::setAccessible()
			$rpn = new ReflectionClass( 'TLN_Normalizer' );
			$prop = $rpn->getProperty( 'mb_overload_string' );
			$prop->setAccessible( true );
			$prop->setValue( null );
			$this->assertSame( "\xc3\xbc", TLN_Normalizer::normalize( "u\xcc\x88" ) );
			$this->assertSame( $encoding, mb_internal_encoding() );
		}

		mb_internal_encoding( $mb_internal_encoding );
	}

    /**
	 * @ticket tln_conformance_8_0_0
     */
    function test_conformance_8_0_0() {

        $t = file( dirname( __FILE__ ) . '/UCD-8.0.0/NormalizationTest.txt' );
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
		global $line_num;
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

				$this->assertSame( self::$true, TLN_Normalizer::isNormalized( $c[2], TLN_Normalizer::NFC ), "$line_num: {$line}c[2]=" . bin2hex( $c[2] ) );
				$this->assertSame( $c[2], TLN_Normalizer::normalize( $c[1], TLN_Normalizer::NFC ) );
				$this->assertSame( $c[2], TLN_Normalizer::normalize( $c[2], TLN_Normalizer::NFC ) );
				$this->assertSame( $c[2], TLN_Normalizer::normalize( $c[3], TLN_Normalizer::NFC ) );
				$this->assertSame( $c[4], TLN_Normalizer::normalize( $c[4], TLN_Normalizer::NFC ) );
				$this->assertSame( $c[4], TLN_Normalizer::normalize( $c[5], TLN_Normalizer::NFC ) );

				if ( class_exists( 'Normalizer' ) && self::$at_least_55_1 ) {
					if ( $c[2] !== $c[1] ) {
						$this->assertSame( self::$false, TLN_Normalizer::isNormalized( $c[1], TLN_Normalizer::NFC ) );
					}
					if ( ! self::$new_8_0_0_regex || ! preg_match( self::$new_8_0_0_regex, $c[1] ) ) {
						$this->assertSame( $normalize_n = Normalizer::normalize( $c[1], Normalizer::NFC ), $normalize_t = TLN_Normalizer::normalize( $c[1], TLN_Normalizer::NFC ), "$line_num: {$line}c[1]=" . bin2hex( $c[1] ) . ", normalize_n=" . bin2hex( $normalize_n ) . ", normalize_t=" . bin2hex( $normalize_t ) );
					}
					$this->assertSame( Normalizer::normalize( $c[2], Normalizer::NFC ), TLN_Normalizer::normalize( $c[2], TLN_Normalizer::NFC ) );
					$this->assertSame( Normalizer::normalize( $c[3], Normalizer::NFC ), TLN_Normalizer::normalize( $c[3], TLN_Normalizer::NFC ) );
					if ( $c[2] !== $c[4] ) {
						$this->assertSame( Normalizer::isNormalized( $c[4], Normalizer::NFC ), TLN_Normalizer::isNormalized( $c[4], TLN_Normalizer::NFC ) );
					}
					$this->assertSame( Normalizer::normalize( $c[4], Normalizer::NFC ), TLN_Normalizer::normalize( $c[4], TLN_Normalizer::NFC ) );
					$this->assertSame( Normalizer::normalize( $c[5], Normalizer::NFC ), TLN_Normalizer::normalize( $c[5], TLN_Normalizer::NFC ) );

					if ( $last9_c1s ) {
						shuffle( $last9_c1s );
						$c1 = implode( '', $last9_c1s );
						if ( self::$new_8_0_0_regex ) {
							$c1 = preg_replace( self::$new_8_0_0_regex, '', $c1 );
						}
						$this->assertSame( Normalizer::normalize( $c1, Normalizer::NFC ), TLN_Normalizer::normalize( $c1, TLN_Normalizer::NFC ), "$line_num: {$line}c1=" . bin2hex( $c1 ) );
					}
				}

				$this->assertSame( $c[3], TLN_Normalizer::normalize( $c[1], TLN_Normalizer::NFD ) );
				$this->assertSame( $c[3], TLN_Normalizer::normalize( $c[2], TLN_Normalizer::NFD ) );
				$this->assertSame( $c[3], TLN_Normalizer::normalize( $c[3], TLN_Normalizer::NFD ) );
				$this->assertSame( $c[5], TLN_Normalizer::normalize( $c[4], TLN_Normalizer::NFD ) );
				$this->assertSame( $c[5], TLN_Normalizer::normalize( $c[5], TLN_Normalizer::NFD ) );

				$this->assertSame( $c[4], TLN_Normalizer::normalize( $c[1], TLN_Normalizer::NFKC ) );
				$this->assertSame( $c[4], TLN_Normalizer::normalize( $c[2], TLN_Normalizer::NFKC ) );
				$this->assertSame( $c[4], TLN_Normalizer::normalize( $c[3], TLN_Normalizer::NFKC ) );
				$this->assertSame( $c[4], TLN_Normalizer::normalize( $c[4], TLN_Normalizer::NFKC ) );
				$this->assertSame( $c[4], TLN_Normalizer::normalize( $c[5], TLN_Normalizer::NFKC ) );

				$this->assertSame( $c[5], TLN_Normalizer::normalize( $c[1], TLN_Normalizer::NFKD ) );
				$this->assertSame( $c[5], TLN_Normalizer::normalize( $c[2], TLN_Normalizer::NFKD ) );
				$this->assertSame( $c[5], TLN_Normalizer::normalize( $c[3], TLN_Normalizer::NFKD ) );
				$this->assertSame( $c[5], TLN_Normalizer::normalize( $c[4], TLN_Normalizer::NFKD ) );
				$this->assertSame( $c[5], TLN_Normalizer::normalize( $c[5], TLN_Normalizer::NFKD ) );

				if ( $x ) {
					for ( $i = $last_x + 1; $i < $x; $i++ ) {
						$c1 = self::chr( $i );
						if ( 1 === preg_match( TLN_REGEX_IS_VALID_UTF8, $c1 ) ) {
							$this->assertSame( self::$true, TLN_Normalizer::isNormalized( $c1, TLN_Normalizer::NFC ), "$line_num: {$line}c1=" . bin2hex( $c1 ) );
							$this->assertSame( $c1, TLN_Normalizer::normalize( $c1, TLN_Normalizer::NFC ) );
						}
					}
					$last_x = $x;
				}
            }
        }
    }

    /**
	 * @ticket tln_random
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
				$this->assertSame( Normalizer::isNormalized( $str ), TLN_Normalizer::isNormalized( $str ) );
				$this->assertSame( Normalizer::normalize( $str ), TLN_Normalizer::normalize( $str ) );
			}

			$num_tests = self::$doing_coverage ? 1 : 42; // Shorten lengthy tests if doing code coverage.
			global $tln_nfc_maybes_or_reorders;
			for ( $i = 0; $i < 42; $i++ ) {
				$str = tln_utf8_rand_ratio_str( rand( 100, 100000 ), 0.5, $tln_nfc_maybes_or_reorders );
				if ( self::$new_8_0_0_regex ) {
					$str = preg_replace( self::$new_8_0_0_regex, '', $str );
				}
				$this->assertSame( Normalizer::isNormalized( $str ), TLN_Normalizer::isNormalized( $str ) );
				$this->assertSame( Normalizer::normalize( $str ), TLN_Normalizer::normalize( $str ) );
				unset( $str );
			}
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
