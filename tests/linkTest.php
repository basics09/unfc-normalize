<?php
/**
 * Test link filters.
 *
 * @group tln
 * @group tln_link
 */
class Tests_TLN_Link extends WP_UnitTestCase {

	static $normalizer_state = array();

	public static function wpSetUpBeforeClass() {
		global $tlnormalizer;
		self::$normalizer_state = array( $tlnormalizer->dont_js, $tlnormalizer->dont_filter, $tlnormalizer->no_normalizer );
		$tlnormalizer->dont_js = true;
		$tlnormalizer->dont_filter = false;
		$tlnormalizer->no_normalizer = true;

		global $pagenow;
		$pagenow = 'link.php';
		set_current_screen( $pagenow );

		//add_filter( 'pre_option_link_manager_enabled', '__return_true' );
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
	 * @ticket tln_link_link
     */
	function test_link() {
		$this->assertTrue( is_admin() ) ;

		do_action( 'init' );

		global $tlnormalizer;
		$this->assertArrayHasKey( 'link', $tlnormalizer->added_filters );

		$decomposed_str = "o\xcc\x88"; // o umlaut.

		$data = array(
			'link_url' => 'http://example.com/address' . $decomposed_str,
			'link_name' => 'name' . $decomposed_str,
			'link_image' => 'http://example.com/image' . $decomposed_str . '.jpg',
			'link_description' => 'desc' . $decomposed_str,
			'link_notes' => 'notes' . $decomposed_str,
			'link_rss' => 'http://example.com/rss' . $decomposed_str,
		);

		$link_id = wp_insert_link( $data );
		$this->assertTrue( is_numeric( $link_id ) );
		$this->assertTrue( $link_id > 0 );

		$out = get_link_to_edit( $link_id );
		$this->assertTrue( is_object( $out ) );

		$this->assertSame( TLN_Normalizer::normalize( $data['link_url'] ), $out->link_url );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $data['link_url'] ), $out->link_url );
		$this->assertSame( TLN_Normalizer::normalize( $data['link_name'] ), $out->link_name );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $data['link_name'] ), $out->link_name );
		$this->assertSame( TLN_Normalizer::normalize( $data['link_image'] ), $out->link_image );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $data['link_description'] ), $out->link_description );
		$this->assertSame( TLN_Normalizer::normalize( $data['link_notes'] ), $out->link_notes );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $data['link_notes'] ), $out->link_notes );
		$this->assertSame( TLN_Normalizer::normalize( $data['link_rss'] ), $out->link_rss );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $data['link_rss'] ), $out->link_rss );
		$this->assertSame( TLN_Normalizer::normalize( $data['link_name'] ), $out->link_name );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $data['link_name'] ), $out->link_name );
	}
}
