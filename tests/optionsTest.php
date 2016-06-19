<?php
/**
 * Test options filters.
 *
 * @group tln
 * @group tln_options
 */
class Tests_TLN_Options extends WP_UnitTestCase {

	static $normalizer_state = array();

	public static function wpSetUpBeforeClass() {
		global $tlnormalizer;
		self::$normalizer_state = array( $tlnormalizer->dont_js, $tlnormalizer->dont_filter, $tlnormalizer->no_normalizer );
		$tlnormalizer->dont_js = true;
		$tlnormalizer->dont_filter = false;
		$tlnormalizer->no_normalizer = true;

		global $pagenow;
		$pagenow = 'options.php';
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
	 * @ticket tln_options_options
     */
	function test_options() {
		$this->assertTrue( is_admin() ) ;

		do_action( 'init' );

		global $tlnormalizer;
		$this->assertArrayHasKey( 'options', $tlnormalizer->added_filters );

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

			$this->assertSame( TLN_Normalizer::normalize( $value ), $out );
			if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $value ), $out );
		}

		$tlnormalizer->do_all_options = false;

		do_action( 'init' );

		$data['blogname'] = 'Blogname2' . $decomposed_str;
		$data['blogdescription'] = 'Blogdescription2' . $decomposed_str;

		foreach ( $data as $option => $value ) {
			update_option( $option, $value );
		}

		foreach ( $data as $option => $value ) {
			$out = get_option( $option );

			$this->assertSame( TLN_Normalizer::normalize( $value ), $out );
			if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $value ), $out );
		}
	}

    /**
	 * @ticket tln_options_format
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
		$this->assertSame( TLN_Normalizer::normalize( $_POST['date'] ), $out );

		$_REQUEST['action'] = 'time_format';

		do_action( 'init' );

		$_POST['date'] = 'j F Y' . $decomposed_str;

		$out = sanitize_option( 'time_format', $_POST['date'] );
		$this->assertSame( TLN_Normalizer::normalize( $_POST['date'] ), $out );
	}
}
