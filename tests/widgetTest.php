<?php
/**
 * Test widget filters.
 *
 * @group tln
 * @group tln_widget
 */

class Tests_TLN_Widget extends WP_UnitTestCase {

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

	public static function wpSetUpBeforeClass() {
		global $tlnormalizer;
		self::$normalizer_state = array( $tlnormalizer->dont_js, $tlnormalizer->dont_filter, $tlnormalizer->no_normalizer );
		$tlnormalizer->dont_js = true;
		$tlnormalizer->dont_filter = false;
		$tlnormalizer->no_normalizer = true;

		global $pagenow;
		$pagenow = 'widgets.php';
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
		global $wp_customize;
		$wp_customize = null;

		parent::tearDown();

		if ( ! method_exists( 'WP_UnitTestCase', 'wpSetUpBeforeClass' ) ) { // Hack for WP testcase.php versions prior to 4.4
			self::wpTearDownAfterClass();
		}
	}

    /**
	 * @ticket tln_widget_widget
     */
	function test_widget() {
		$this->assertTrue( is_admin() ) ;

		do_action( 'init' );

		global $tlnormalizer;
		$this->assertArrayHasKey( 'widget', $tlnormalizer->added_filters );

		$decomposed_str = "u\xCC\x88"; // u umlaut.

		$widget = new TLN_Widget_Mock( 'foo', 'Foo' );

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

		$this->assertSame( TLN_Normalizer::normalize( $setting1 ), $settings[0]['setting1'] );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $setting1 ), $settings[0]['setting1'] );
		$this->assertSame( TLN_Normalizer::normalize( $setting2 ), $settings[0]['setting2'] );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $setting2 ), $settings[0]['setting2'] );
	}
}
class TLN_Widget_Mock extends WP_Widget {
	public function widget( $args, $instance ) {
		return;
	}
}
