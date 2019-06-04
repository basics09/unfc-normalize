<?php
/**
 * Test options filters.
 *
 * @group unfc
 * @group unfc_options
 */
class TestUNFC_Options extends WP_UnitTestCase {

	static $normalizer_state = array();
	static $is_less_than_wp_4 = false;

	public static function wpSetUpBeforeClass() {
		global $unfc_normalize;
		self::$normalizer_state = array( $unfc_normalize->dont_js, $unfc_normalize->dont_filter, $unfc_normalize->no_normalizer );
		$unfc_normalize->dont_js = true;
		$unfc_normalize->dont_filter = false;
		$unfc_normalize->no_normalizer = true;

		global $wp_version;
		self::$is_less_than_wp_4 = version_compare( $wp_version, '4', '<' );

		global $pagenow;
		$pagenow = 'options.php';
		set_current_screen( $pagenow );
	}

	public static function wpTearDownAfterClass() {
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
		if ( self::$is_less_than_wp_4 && $this->caught_deprecated && 'define()' === $this->caught_deprecated[0] ) {
			array_shift( $this->caught_deprecated );
		}
		parent::tearDown();
		if ( ! method_exists( 'WP_UnitTestCase', 'wpSetUpBeforeClass' ) ) { // Hack for WP testcase.php versions prior to 4.4
			self::wpTearDownAfterClass();
		}
	}

    /**
     */
	function test_options_filters() {
		$this->assertTrue( is_admin() ) ;

		do_action( 'init' );

		global $unfc_normalize;
		$this->assertArrayHasKey( 'options', $unfc_normalize->added_filters );

		$decomposed_str = "u\xcc\x88"; // u umlaut.

		$data = array(
			'blogname' => 'Blogname' . $decomposed_str,
			'blogdescription' => 'Blogdescription' . $decomposed_str,
		);

		foreach ( $data as $option => $value ) {
			update_option( $option, $value );
		}

		foreach ( $data as $option => $value ) {
			$out = get_option( $option );

			$this->assertSame( UNFC_Normalizer::normalize( $value ), $out );
			if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $value ), $out );
		}

		$unfc_normalize->do_all_options = false;

		do_action( 'init' );

		$data['blogname'] = 'Blogname2' . $decomposed_str;
		$data['blogdescription'] = 'Blogdescription2' . $decomposed_str;

		foreach ( $data as $option => $value ) {
			update_option( $option, $value );
		}

		foreach ( $data as $option => $value ) {
			$out = get_option( $option );

			$this->assertSame( UNFC_Normalizer::normalize( $value ), $out );
			if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $value ), $out );
		}
	}

    /**
     */
	function test_options_format() {
		global $pagenow;
		$pagenow = 'admin-ajax.php';
		set_current_screen( $pagenow );

		$this->assertTrue( is_admin() ) ;

		$decomposed_str = "u\xcc\x88"; // u umlaut.

		$_REQUEST['action'] = 'date_format';

		do_action( 'init' );

		$_POST['date'] = 'j F Y' . $decomposed_str;

		$out = sanitize_option( 'date_format', $_POST['date'] );
		$this->assertSame( UNFC_Normalizer::normalize( $_POST['date'] ), $out );

		$_REQUEST['action'] = 'time_format';

		do_action( 'init' );

		$_POST['date'] = 'j F Y' . $decomposed_str;

		$out = sanitize_option( 'time_format', $_POST['date'] );
		$this->assertSame( UNFC_Normalizer::normalize( $_POST['date'] ), $out );
	}
}
