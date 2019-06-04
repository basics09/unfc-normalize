<?php
/**
 * Test link filters.
 *
 * @group unfc
 * @group unfc_link
 */
class TestUNFC_Link extends WP_UnitTestCase {

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
		$pagenow = 'link.php';
		set_current_screen( $pagenow );

		//add_filter( 'pre_option_link_manager_enabled', '__return_true' );

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
	function test_link_filters() {
		$this->assertTrue( is_admin() ) ;

		do_action( 'init' );

		global $unfc_normalize;
		$this->assertArrayHasKey( 'link', $unfc_normalize->added_filters );

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

		$this->assertSame( UNFC_Normalizer::normalize( $data['link_url'] ), $out->link_url );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $data['link_url'] ), $out->link_url );
		$this->assertSame( UNFC_Normalizer::normalize( $data['link_name'] ), $out->link_name );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $data['link_name'] ), $out->link_name );
		$this->assertSame( UNFC_Normalizer::normalize( $data['link_image'] ), $out->link_image );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $data['link_description'] ), $out->link_description );
		$this->assertSame( UNFC_Normalizer::normalize( $data['link_notes'] ), $out->link_notes );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $data['link_notes'] ), $out->link_notes );
		$this->assertSame( UNFC_Normalizer::normalize( $data['link_rss'] ), $out->link_rss );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $data['link_rss'] ), $out->link_rss );
		$this->assertSame( UNFC_Normalizer::normalize( $data['link_name'] ), $out->link_name );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $data['link_name'] ), $out->link_name );
	}
}
