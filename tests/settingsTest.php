<?php
/**
 * Test settings filters.
 *
 * @group tln
 * @group tln_settings
 */
class Tests_TLN_Settings extends WP_UnitTestCase {

	static $normalizer_state = array();

	public static function wpSetUpBeforeClass() {
		global $tlnormalizer;
		self::$normalizer_state = array( $tlnormalizer->dont_js, $tlnormalizer->dont_filter, $tlnormalizer->no_normalizer );
		$tlnormalizer->dont_js = true;
		$tlnormalizer->dont_filter = false;
		$tlnormalizer->no_normalizer = true;

		global $pagenow;
		$pagenow = 'settings.php';
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
	 * @ticket tln_settings_settings
     */
	function test_settings() {
		$this->assertTrue( is_admin() ) ;

		do_action( 'init' );

		global $tlnormalizer;
		$this->assertArrayHasKey( 'settings', $tlnormalizer->added_filters );

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

			$this->assertSame( TLN_Normalizer::normalize( $value ), $out );
			if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $value ), $out );
		}
	}
}
