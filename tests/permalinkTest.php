<?php
/**
 * Test permalink filters.
 *
 * @group tln
 * @group tln_permalink
 */
class Tests_TLN_Permalink extends WP_UnitTestCase {

	static $normalizer_state = array();

	public static function wpSetUpBeforeClass() {
		global $tlnormalizer;
		self::$normalizer_state = array( $tlnormalizer->dont_js, $tlnormalizer->dont_filter, $tlnormalizer->no_normalizer );
		$tlnormalizer->dont_js = true;
		$tlnormalizer->dont_filter = false;
		$tlnormalizer->no_normalizer = true;

		global $pagenow;
		$pagenow = 'admin-ajax.php';
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
	 * @ticket tln_permalink_permalink
     */
	function test_permalink() {
		$this->assertTrue( is_admin() ) ;

		$_REQUEST['action'] = 'sample-permalink';

		do_action( 'init' );

		global $tlnormalizer;
		$this->assertArrayHasKey( 'permalink', $tlnormalizer->added_filters );

		$decomposed_str = "u\xcc\x88"; // u umlaut.

		$title = 'some-post' . $decomposed_str;

		$post = $this->factory->post->create_and_get( array( 'post_title' => $title, 'post_type' => 'post' ) );

		$out = get_sample_permalink( $post->ID, $title );

		$this->assertSame( TLN_Normalizer::normalize( $title ), $out[1] );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $title ), $out[1] );

		$name = 'name' . $decomposed_str;

		$out = get_sample_permalink( $post->ID, null, $name );

		$this->assertSame( TLN_Normalizer::normalize( $name ), $out[1] );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $name ), $out[1] );
	}
}
