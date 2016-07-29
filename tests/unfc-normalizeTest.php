<?php
/**
 * Test general unfc-normalize functionality.
 *
 * @group unfc
 * @group unfc_normalize
 */
class Tests_UNFC_Normalize extends WP_UnitTestCase {

	static $normalizer_state = array();
	static $is_less_than_wp_4 = false;

	public static function wpSetUpBeforeClass() {
		global $unfc_normalize;
		self::$normalizer_state = array( $unfc_normalize->dont_js, $unfc_normalize->dont_filter, $unfc_normalize->no_normalizer );
		$unfc_normalize->dont_js = false;
		$unfc_normalize->dont_filter = false;
		$unfc_normalize->no_normalizer = true;

		global $wp_version;
		self::$is_less_than_wp_4 = version_compare( $wp_version, '4', '<' );
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
	 * @ticket unfc_extra_filters
	 */
	function test_extra_filters() {
		$decomposed_str = "u\xcc\x88"; // u umlaut.

		global $pagenow;
		$pagenow = 'admin-ajax.php';
		set_current_screen( $pagenow );
		$_REQUEST['action'] = 'replyto-comment';

		add_filter( 'unfc_extra_filters', array( $this, 'unfc_extra_filters_filter' ) );

		do_action( 'init' );

		global $unfc_normalize;
		$this->assertArrayHasKey( 'extra_filters', $unfc_normalize->added_filters );

		add_filter( 'unfc_extra_filter', array( $this, 'unfc_extra_filter' ) );

		apply_filters( 'unfc_extra_filter', 'Content' . $decomposed_str );
	}

	function unfc_extra_filters_filter( $extra_filters ) {
		$extra_filters[] = 'unfc_extra_filter';
		return $extra_filters;
	}

	function unfc_extra_filter( $content ) {
		$this->assertTrue( UNFC_Normalizer::isNormalized( $content ) );
		if ( class_exists( 'Normalizer' ) ) $this->assertTrue( Normalizer::isNormalized( $content ) );
	}

	/**
	 * @ticket unfc_print_scripts
	 */
	function test_print_scripts() {
		global $pagenow;
		$pagenow = 'front.php';
		set_current_screen( $pagenow );

		$this->assertFalse( is_admin() ) ;

		global $wp_scripts;
		$old_wp_scripts = $wp_scripts;

		do_action( 'init' );

		do_action( 'wp_enqueue_scripts' );

		ob_start();
		do_action( 'wp_print_footer_scripts' );
		$out = ob_get_clean();

		$this->assertTrue( false !== strpos( $out, 'unorm.js' ) );
		$this->assertTrue( false !== strpos( $out, 'rangyinputs-jquery' ) );
		$this->assertTrue( false !== strpos( $out, 'unfc-normalize' ) );
		$this->assertTrue( false !== strpos( $out, 'unfc_normalize.' ) );

		$wp_scripts = $old_wp_scripts;

		global $pagenow;
		$pagenow = 'index.php';
		set_current_screen( $pagenow );

		$this->assertTrue( is_admin() ) ;

		do_action( 'init' );

		do_action( 'admin_enqueue_scripts' );

		ob_start();
		do_action( 'admin_print_footer_scripts' );
		$out = ob_get_clean();

		$this->assertTrue( false !== strpos( $out, 'unorm.js' ) );
		$this->assertTrue( false !== strpos( $out, 'rangyinputs-jquery' ) );
		$this->assertTrue( false !== strpos( $out, 'unfc-normalize' ) );
		$this->assertTrue( false !== strpos( $out, 'unfc_normalize.' ) );
	}

	/**
	 * @ticket unfc_unfc_normalize
	 */
	function test_unfc_normalize() {
		$decomposed_str = "u\xcc\x88"; // u umlaut.
		$composed_str = "\xc3\xbc";

		$content = 'Content' . $decomposed_str;
		$expected = 'Content' . $composed_str;

		$unfc = new UNFC_Normalize();
		$this->assertFalse( UNFC_Normalize::$not_compat );
		$this->assertFalse( $unfc->dont_js );
		$this->assertFalse( $unfc->dont_paste );
		$this->assertFalse( $unfc->dont_filter );
		$this->assertFalse( $unfc->no_normalizer );

		$out = $unfc->normalize( $content );
		$this->assertSame( $expected, $out );

		$out = $unfc->normalize( array( $content ) );
		$this->assertSame( array( $expected ), $out );

		$out = $unfc->normalize( true );
		$this->assertTrue( $out );

		$out = $unfc->normalize( 1.5 );
		$this->assertSame( 1.5, $out );
	}

	/**
	 * @ticket unfc_compat
	 */
	function test_compat() {
		$decomposed_str = "u\xcc\x88"; // u umlaut.

		global $wp_filter;
		$old_wp_filter = $wp_filter;

		$wp_filter = array();

		UNFC_Normalize::$dirname = null;
		UNFC_Normalize::$plugin_basename = null;

		global $pagenow;
		$pagenow = 'tools.php';
		set_current_screen( $pagenow );
		$_REQUEST['page'] = UNFC_DB_CHECK_MENU_SLUG;

		$unfc = new UNFC_Normalize();

		$unfc->activation_check();

		$this->assertTrue( $unfc->check_version() );
		$this->assertSame( 10, has_filter( 'admin_init', array( $unfc, 'admin_init' ) ) );
		$this->assertSame( 10, has_filter( 'init', array( $unfc, 'init' ) ) );

		do_action( 'init' );

		UNFC_Normalize::$doing_ajax = true;

		do_action( 'admin_init' );

		$this->assertSame( 10, has_filter( 'wp_ajax_unfc_db_check_list_bulk', array( $unfc, 'wp_ajax_unfc_db_check_list_bulk' ) ) );
		$this->assertSame( 10, has_filter( 'wp_ajax_unfc_db_check_list_page', array( $unfc, 'wp_ajax_unfc_db_check_list_page' ) ) );
		$this->assertSame( 10, has_filter( 'wp_ajax_unfc_db_check_list_sort', array( $unfc, 'wp_ajax_unfc_db_check_list_sort' ) ) );
		$this->assertSame( 10, has_filter( 'wp_ajax_unfc_db_check_list_screen_options', array( $unfc, 'wp_ajax_unfc_db_check_list_screen_options' ) ) );

		UNFC_Normalize::$doing_ajax = null;

		UNFC_Normalize::$not_compat = true;
		UNFC_Normalize::$plugin_basename = WP_PLUGIN_DIR . '/normalizer/unfc-normalize.php';

		$wp_filter = array();

		$unfc = new UNFC_Normalize();

		$this->assertFalse( $unfc->check_version() );
		$this->assertSame( 10, has_filter( 'admin_init', array( $unfc, 'admin_init' ) ) );
		$this->assertFalse( has_filter( 'init', array( $unfc, 'init' ) ) );

		$old_plugins = $current = get_site_option( 'active_sitewide_plugins', array() );
		$current[UNFC_Normalize::$plugin_basename] = time();
		$_GET['activate'] = true;
		update_site_option( 'active_sitewide_plugins', $current );

		do_action( 'init' );

		do_action( 'admin_init' );

		$admin_notices_filter = is_network_admin() ? 'network_admin_notices' : ( is_user_admin() ? 'user_admin_notices' : 'admin_notices' );
		$this->assertSame( 10, has_filter( $admin_notices_filter, array( $unfc, 'disabled_notice' ) ) );
		$this->assertFalse( isset( $_GET['activate'] ) );

		ob_start();
		do_action( $admin_notices_filter );
		$out = ob_get_clean();
		$this->assertTrue( false !== stripos( $out, 'deactivated' ) );

		add_filter( 'wp_die_handler', array( $this, 'get_wp_die_handler' ), 10 );
		$out = '';
		try {
			$unfc->activation_check();
		} catch ( WPDieException $e ) {
			$out = $e->getMessage();
			unset( $e );
		}
		$this->assertTrue( false !== stripos( $out, 'activated' ) );

		UNFC_Normalize::$not_compat = false;
		global $wp_version;
		$old_wp_version = $wp_version;
		$wp_version = '3.9';

		$wp_filter = array();

		$unfc = new UNFC_Normalize();
		$this->assertFalse( UNFC_Normalize::tested_wp_version() );

		$unfc->activation_check();
		$admin_notices_filter = is_network_admin() ? 'network_admin_notices' : ( is_user_admin() ? 'user_admin_notices' : 'admin_notices' );
		$this->assertSame( 10, has_filter( $admin_notices_filter, array( 'UNFC_Normalize', 'untested_notice' ) ) );

		ob_start();
		do_action( $admin_notices_filter );
		$out = ob_get_clean();
		$this->assertTrue( false !== stripos( $out, 'untested' ) );

		$wp_version = $old_wp_version;

		$old_blog_charset = get_option( 'blog_charset' );
		update_option( 'blog_charset', 'latin1' );

		$unfc = new UNFC_Normalize();

		$this->assertSame( 10, has_filter( 'admin_init', array( $unfc, 'admin_init' ) ) );
		$this->assertFalse( has_filter( 'init', array( $unfc, 'init' ) ) );

		// Restore.
		$wp_filter = $old_wp_filter;
		update_site_option( 'active_sitewide_plugins', $old_plugins );
		update_option( 'blog_charset', $old_blog_charset );
	}

	/**
	 * @ticket unfc_get_base
	 * @dataProvider data_get_base
	 */
	function test_get_base( $page, $action, $expected ) {
		global $pagenow;

		if ( ! $page ) {
			$page = 'admin-ajax.php';
		}

		$pagenow = $page;
		set_current_screen( $pagenow );

		if ( $action ) {
			$_REQUEST['action'] = $action;
		} else {
			unset( $_REQUEST['action'] );
		}

		global $unfc_normalize;
		$out = $unfc_normalize->get_base();

		$this->assertSame( $expected, $out );
	}

	function data_get_base() {
		return array(
			array( 'post.php', null, 'post' ), // Edit post/attachment/page.
			array( 'post-new.php', null, 'post' ), // Add post/page.
			array( null, 'inline-save', 'post' ), // Quick edit post/page.

			array( null, 'add-meta', 'post' ), // Add/update custom field.

			array( null, 'save-attachment', 'post' ), // Update media.
			array( 'async-upload.php', 'upload-attachment', 'post' ), // Add media.
			array( 'media-new.php', null, 'post' ), // Add media no-js.

			array( 'comment.php', null, 'comment' ), // Edit comment.
			array( null, 'edit-comment', 'comment' ), // Quick edit comment.
			array( null, 'replyto-comment', 'comment' ), // Reply to comment, add comment (post/attachment/page).

			array( 'user-edit.php', null, 'user' ), // Edit user.
			array( 'user-new.php', null, 'user' ), // New user.
			array( 'profile.php', null, 'user' ), // Edit current user.

			array( null, 'add-tag', 'term' ), // Add category/tag.
			array( null, 'inline-save-tax', 'term' ), // Save category/tag.
			array( null, 'add-category', 'term' ), // Add category (post/attachment).

			array( 'options', null, 'options' ), // Options.
			array( null, 'date_format', 'date_format' ), // Date format preview.
			array( null, 'time_format', 'time_format' ), // Time format preview.

			array( 'settings', null, 'settings' ), // Settings (multisite network options).

			array( 'nav-menus.php', null, 'menus' ), // Add/update menus.

			array( null, 'save-widget', 'widget' ), //  Update widget.
			array( null, 'update-widget', 'widget' ), //  Update widget customizer.
			array( 'widgets.php', null, 'widget' ), //  Add/update widgets (no-js).

			array( null, 'sample-permalink', 'permalink' ), // Permalink preview.

			array( null, 'customize', 'customize' ), // Customizer preview.
			array( null, 'customize_save', 'customize_save' ), // Customizer update.

			array( 'link.php', null, 'link' ), // Edit link.
			array( 'link-add.php', null, 'link' ), // Add link.
		);
	}

	/**
	 * @ticket unfc_unfc_normalize_php
	 */
	function test_unfc_normalize_php() {
		global $pagenow;
		$pagenow = 'index.php';
		set_current_screen( $pagenow );

		$this->assertTrue( is_admin() ) ;

		global $unfc_normalize;
		$old_unfc_normalize = $unfc_normalize;

		$file = dirname( dirname( __FILE__ ) ) . '/' . 'unfc-normalize.php';
		require $file;

		$unfc_normalize = $old_unfc_normalize; // Restore first before asserting so as not to mess up other tests on failure.

		$this->assertSame( 10, has_filter( 'activate_' . plugin_basename( $file ), array( 'UNFC_Normalize', 'activation_check' ) ) );
		//$this->assertTrue( is_textdomain_loaded( 'unfc-normalize' ) ); Doesn't load as running in development directory.
	}
}
