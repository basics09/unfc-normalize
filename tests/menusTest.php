<?php
/**
 * Test menus filters.
 *
 * @group tln
 * @group tln_menus
 */
class Tests_TLN_Menus extends WP_UnitTestCase {

	static $normalizer_state = array();

	public static function wpSetUpBeforeClass() {
		global $tlnormalizer;
		self::$normalizer_state = array( $tlnormalizer->dont_js, $tlnormalizer->dont_filter, $tlnormalizer->no_normalizer );
		$tlnormalizer->dont_js = true;
		$tlnormalizer->dont_filter = false;
		$tlnormalizer->no_normalizer = true;

		global $pagenow;
		$pagenow = 'nav-menus.php';
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
	 * @ticket tln_menus_menus
     */
	function test_menus() {
		$this->assertTrue( is_admin() ) ;

		do_action( 'init' );

		global $tlnormalizer;
		$this->assertArrayHasKey( 'menus', $tlnormalizer->added_filters );

		$decomposed_str = "u\xCC\x88"; // u umlaut.

		$menu_data = array(
			'menu-name' => wp_slash( 'menus name ' . $decomposed_str ),
			'description' => 'Menus description ' . $decomposed_str,
		);

		$menu_id = wp_update_nav_menu_object( 0, $menu_data );

		$this->assertTrue( is_numeric( $menu_id ) );
		$this->assertTrue( $menu_id > 0 );

		$out = wp_get_nav_menu_object( $menu_id );
		if ( class_exists( 'WP_Term' ) ) {
			$this->assertInstanceOf( 'WP_Term', $out );
		} else {
			$this->assertTrue( is_object( $out ) );
		}

		$this->assertSame( TLN_Normalizer::normalize( $menu_data['menu-name'] ), $out->name );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $menu_data['menu-name'] ), $out->name );
		$this->assertSame( TLN_Normalizer::normalize( $menu_data['description'] ), $out->description );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $menu_data['description'] ), $out->description );

		$menu_date['menu-name'] = wp_slash( 'menus name2 ' . $decomposed_str );
		$menu_date['description'] = 'Menus description2 ' . $decomposed_str;

		$out = wp_update_nav_menu_object( $menu_id, $menu_data );
		$this->assertSame( $menu_id, $out );

		$menu = wp_get_nav_menu_object( $menu_id );
		if ( class_exists( 'WP_Term' ) ) {
			$this->assertInstanceOf( 'WP_Term', $menu );
		} else {
			$this->assertTrue( is_object( $menu ) );
		}

		$this->assertSame( TLN_Normalizer::normalize( $menu_data['menu-name'] ), $menu->name );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $menu_data['menu-name'] ), $menu->name );
		$this->assertSame( TLN_Normalizer::normalize( $menu_data['description'] ), $menu->description );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $menu_data['description'] ), $menu->description );

		$item_data = array(
			'menu-item-title' => 'item1-title' . $decomposed_str,
			'menu-item-url' => 'item1-url' . $decomposed_str,
			'menu-item-description' => 'item1-description' . $decomposed_str,
			'menu-item-attr-title' => 'item1-attr-title' . $decomposed_str,
			'menu-item-classes' => 'item1-classes' . $decomposed_str,
			'menu-item-xfn' => 'item1-xfn' . $decomposed_str,
		);

		$menu_item1_id = wp_update_nav_menu_item( $menu_id, 0, $item_data );
		$this->assertTrue( is_numeric( $menu_item1_id ) );
		$this->assertTrue( $menu_item1_id > 0 );

		$items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'publish,draft' ) );
		$this->assertTrue( is_array( $items ) );
		$this->assertSame( 1, count( $items ) );
		$item = $items[0];
		$this->assertSame( TLN_Normalizer::normalize( $item_data['menu-item-title'] ), $item->post_title );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $item_data['menu-item-title'] ), $item->post_title );
		$this->assertSame( TLN_Normalizer::normalize( esc_url_raw( $item_data['menu-item-url'] ) ), $item->url );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( esc_url_raw( $item_data['menu-item-url'] ) ), $item->url );
		$this->assertSame( TLN_Normalizer::normalize( $item_data['menu-item-description'] ), $item->post_content );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $item_data['menu-item-description'] ), $item->post_content );
		$this->assertSame( TLN_Normalizer::normalize( $item_data['menu-item-description'] ), $item->description ); // In 2 places.
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $item_data['menu-item-description'] ), $item->description );
		$this->assertSame( TLN_Normalizer::normalize( $item_data['menu-item-attr-title'] ), $item->attr_title );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $item_data['menu-item-attr-title'] ), $item->attr_title );
		// classes and xfn stripped to ASCII.
		$this->assertSame( preg_replace( '/[^\x00-\x7f]/', '', $item_data['menu-item-classes'] ), implode( ' ', $item->classes ) );
		$this->assertSame( preg_replace( '/[^\x00-\x7f]/', '', $item_data['menu-item-xfn'] ), $item->xfn );
	}
}
