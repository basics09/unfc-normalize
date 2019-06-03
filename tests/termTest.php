<?php
/**
 * Test term filters.
 *
 * @group unfc
 * @group unfc_term
 */
class Tests_UNFC_Term extends WP_UnitTestCase {

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
		$pagenow = 'edit-tags.php';
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
	function test_term() {
		$this->assertTrue( is_admin() ) ;

		do_action( 'init' );

		global $unfc_normalize;
		$this->assertArrayHasKey( 'term', $unfc_normalize->added_filters );

		$decomposed_str = "u\xcc\x88"; // u umlaut.

		$name1 = 'Cat name ' . $decomposed_str;
		$args = array(
			'description' => 'Cat description ' . $decomposed_str,
		);
		$tax1 = 'category';

		$result = wp_insert_term( $name1, $tax1, $args );

		$this->assertTrue( is_array( $result ) );
		$this->assertTrue( is_numeric( $result['term_id'] ) );

		$id1 = $result['term_id'];
		$this->assertTrue( $id1 > 0 );

		// Fetch the term and make sure it matches.
		$out = get_term( $id1, $tax1 );
		if ( class_exists( 'WP_Term' ) ) {
			$this->assertInstanceOf( 'WP_Term', $out );
		} else {
			$this->assertTrue( is_object( $out ) );
		}

		$this->assertSame( UNFC_Normalizer::normalize( $name1 ), $out->name );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $name1 ), $out->name );
		$this->assertSame( UNFC_Normalizer::normalize( $args['description'] ), $out->description );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $args['description'] ), $out->description );
	}
}
