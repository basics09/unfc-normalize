<?php
/**
 * Test customize filters.
 *
 * @group unfc
 * @group unfc_customize
 */
class TestUNFC_Customize extends WP_UnitTestCase {

	static $normalizer_state = array();
	static $is_less_than_wp_4 = false;
	static $pre_wp_filter = null;

	public static function wpSetUpBeforeClass() {
		global $unfc_normalize;
		self::$normalizer_state = array( $unfc_normalize->dont_js, $unfc_normalize->dont_filter, $unfc_normalize->no_normalizer );
		$unfc_normalize->dont_js = true;
		$unfc_normalize->dont_filter = false;
		$unfc_normalize->no_normalizer = true;

		global $wp_version;
		self::$is_less_than_wp_4 = version_compare( $wp_version, '4', '<' );

		global $pagenow;
		$pagenow = 'customize.php';
		set_current_screen( $pagenow );

		global $wp_filter;
		self::$pre_wp_filter = $wp_filter;
	}

	public static function wpTearDownAfterClass() {
		global $unfc_normalize;
		list( $unfc_normalize->dont_js, $unfc_normalize->dont_filter, $unfc_normalize->no_normalizer ) = self::$normalizer_state;

		global $wp_filter;
		$wp_filter = self::$pre_wp_filter;
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
	function test_customize_filters() {
		$this->assertTrue( is_admin() ) ;

		do_action( 'init' );

		global $unfc_normalize;
		// $this->assertArrayHasKey( 'customize', $unfc_normalize->added_filters );

		// TODO: anything?

		global $pagenow;
		$pagenow = 'admin-ajax.php';
		set_current_screen( $pagenow );
		$_REQUEST['action'] = 'customize_save';

		do_action( 'init' );

		$this->assertArrayHasKey( 'menus', $unfc_normalize->added_filters );
		$this->assertArrayHasKey( 'options', $unfc_normalize->added_filters );
		$this->assertArrayHasKey( 'permalink', $unfc_normalize->added_filters );
		$this->assertArrayHasKey( 'settings', $unfc_normalize->added_filters );
		$this->assertArrayHasKey( 'widget', $unfc_normalize->added_filters );

		// TODO: Specific tests.
	}
}
