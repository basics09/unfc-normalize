<?php
/**
 * Test customize filters.
 *
 * @group tln
 * @group tln_customize
 */
class Tests_TLN_Customize extends WP_UnitTestCase {

	static $normalizer_state = array();

	public static function wpSetUpBeforeClass() {
		global $tlnormalizer;
		self::$normalizer_state = array( $tlnormalizer->dont_js, $tlnormalizer->dont_filter, $tlnormalizer->no_normalizer );
		$tlnormalizer->dont_js = true;
		$tlnormalizer->dont_filter = false;
		$tlnormalizer->no_normalizer = true;

		global $pagenow;
		$pagenow = 'customize.php';
		set_current_screen( $pagenow );
	}

	public static function wpTearDownAfterClass() {
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
	 * @ticket tln_customize_customize
     */
	function test_customize() {
		$this->assertTrue( is_admin() ) ;

		do_action( 'init' );

		global $tlnormalizer;
		// $this->assertArrayHasKey( 'customize', $tlnormalizer->added_filters );

		// TODO: anything?

		global $pagenow;
		$pagenow = 'admin-ajax.php';
		set_current_screen( $pagenow );
		$_REQUEST['action'] = 'customize_save';

		do_action( 'init' );

		$this->assertArrayHasKey( 'menus', $tlnormalizer->added_filters );
		$this->assertArrayHasKey( 'options', $tlnormalizer->added_filters );
		$this->assertArrayHasKey( 'permalink', $tlnormalizer->added_filters );
		$this->assertArrayHasKey( 'settings', $tlnormalizer->added_filters );
		$this->assertArrayHasKey( 'widget', $tlnormalizer->added_filters );

		// TODO: Specific tests.
	}
}
