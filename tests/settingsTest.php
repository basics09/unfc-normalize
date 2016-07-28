<?php
/**
 * Test settings filters.
 *
 * @group unfc
 * @group unfc_settings
 */
class Tests_UNFC_Settings extends WP_UnitTestCase {

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
		$pagenow = 'settings.php';
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
	 * @ticket unfc_settings_settings
     */
	function test_settings() {
		$this->assertTrue( is_admin() ) ;

		do_action( 'init' );

		global $unfc_normalize;
		$this->assertArrayHasKey( 'settings', $unfc_normalize->added_filters );

		$decomposed_str = "u\xCC\x88"; // u umlaut.

		$data = array(
			'blogname' => 'Blogname' . $decomposed_str,
			'blogdescription' => 'Blogdescription' . $decomposed_str,
			'site_name' => 'Site name' . $decomposed_str,
			'welcome_email' => 'Hello' . $decomposed_str,
			'first_comment' => 'I was just looking at your My Site | Just another WordPress site website and see that your website has the potential to get a lot of visitors' . $decomposed_str,
		);

		foreach ( $data as $setting => $value ) {
			update_site_option( $setting, $value );
		}

		foreach ( $data as $setting => $value ) {
			$out = get_site_option( $setting );

			$this->assertSame( UNFC_Normalizer::normalize( $value ), $out );
			if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $value ), $out );
		}
	}
}
