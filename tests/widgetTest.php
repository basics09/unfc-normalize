<?php
/**
 * Test widget filters.
 *
 * @group unfc
 * @group unfc_widget
 */

class Tests_UNFC_Widget extends WP_UnitTestCase {

	function clean_up_global_scope() {
		global $wp_widget_factory, $wp_registered_sidebars, $wp_registered_widgets, $wp_registered_widget_controls, $wp_registered_widget_updates;

		$wp_registered_sidebars = array();
		$wp_registered_widgets = array();
		$wp_registered_widget_controls = array();
		$wp_registered_widget_updates = array();
		$wp_widget_factory->widgets = array();

		parent::clean_up_global_scope();
	}

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
		$pagenow = 'widgets.php';
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
		global $wp_customize;
		$wp_customize = null;

		if ( self::$is_less_than_wp_4 && $this->caught_deprecated && 'define()' === $this->caught_deprecated[0] ) {
			array_shift( $this->caught_deprecated );
		}
		parent::tearDown();

		if ( ! method_exists( 'WP_UnitTestCase', 'wpSetUpBeforeClass' ) ) { // Hack for WP testcase.php versions prior to 4.4
			self::wpTearDownAfterClass();
		}
	}

    /**
	 * @ticket unfc_widget_widget
     */
	function test_widget() {
		$this->assertTrue( is_admin() ) ;

		do_action( 'init' );

		global $unfc_normalize;
		$this->assertArrayHasKey( 'widget', $unfc_normalize->added_filters );

		$decomposed_str = "u\xCC\x88"; // u umlaut.

		$widget = new UNFC_Widget_Mock( 'foo', 'Foo' );

		$setting1 = 'Setting1' . $decomposed_str;
		$setting2 = 'Setting2' . $decomposed_str;

		$_POST = array(
			'widget-foo' => array(
				array(
					'setting1' => $setting1,
					'setting2' => $setting2,
				)
			),
		);

		$widget->update_callback();

		$this->assertTrue( $widget->updated );

		$settings = $widget->get_settings();

		$this->assertSame( UNFC_Normalizer::normalize( $setting1 ), $settings[0]['setting1'] );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $setting1 ), $settings[0]['setting1'] );
		$this->assertSame( UNFC_Normalizer::normalize( $setting2 ), $settings[0]['setting2'] );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $setting2 ), $settings[0]['setting2'] );
	}
}
class UNFC_Widget_Mock extends WP_Widget {
	public function widget( $args, $instance ) {
		return;
	}
}
