<?php
/**
 * Test database check functionality.
 *
 * @group unfc
 * @group unfc_db_check
 */
class Tests_UNFC_DB_Check extends WP_UnitTestCase {

	static $normalizer_state = array();
	static $is_less_than_wp_4_2 = false;
	static $is_less_than_wp_4_3 = false;
	static $is_less_than_wp_4 = false;

	public static function wpSetUpBeforeClass() {
		global $unfc_normalize;
		self::$normalizer_state = array( $unfc_normalize->dont_js, $unfc_normalize->dont_filter, $unfc_normalize->no_normalizer );
		$unfc_normalize->dont_js = false;
		$unfc_normalize->dont_filter = true;
		$unfc_normalize->no_normalizer = true;

		global $wp_version;
		self::$is_less_than_wp_4_3 = version_compare( $wp_version, '4.3', '<' );
		self::$is_less_than_wp_4_2 = version_compare( $wp_version, '4.2', '<' );
		self::$is_less_than_wp_4 = version_compare( $wp_version, '4', '<' );
		if ( version_compare( PHP_VERSION, '7', '>=' ) && self::$is_less_than_wp_4_3 ) {
			error_reporting( error_reporting() ^ 8192 /*E_DEPRECATED*/ );
		}

		global $pagenow;
		$pagenow = 'tools.php';
		set_current_screen( $pagenow );

		if ( ! function_exists( 'unfc_list_pluck' ) ) {
			require dirname( dirname( __FILE__ ) ) . '/tools/functions.php';
		}
	}

	public static function wpTearDownAfterClass() {
		global $unfc_normalize;
		list( $unfc_normalize->dont_js, $unfc_normalize->dont_filter, $unfc_normalize->no_normalizer ) = self::$normalizer_state;
	}

	function setUp() {
		parent::setUp();
		self::clear_func_args();
		add_filter( 'wp_redirect', array( __CLASS__, 'wp_redirect' ), 10, 2 );
		remove_filter( 'admin_init', 'wp_admin_headers' ); // Don't send headers.
		if ( ! method_exists( 'WP_UnitTestCase', 'wpSetUpBeforeClass' ) ) { // Hack for WP testcase.php versions prior to 4.4
			self::wpSetUpBeforeClass();
		}
		if ( self::$is_less_than_wp_4 ) {
			mbstring_binary_safe_encoding(); // For <= WP 3.9.13 compatibility - remove_accents() uses naked strlen().
		}
	}

	function tearDown() {
		if ( self::$is_less_than_wp_4 && $this->caught_deprecated && 'define()' === $this->caught_deprecated[0] ) {
			array_shift( $this->caught_deprecated );
		}
		parent::tearDown();
		add_filter( 'admin_init', 'wp_admin_headers' );
		remove_filter( 'wp_redirect', array( __CLASS__, 'wp_redirect' ), 10, 2 );
		if ( ! method_exists( 'WP_UnitTestCase', 'wpSetUpBeforeClass' ) ) { // Hack for WP testcase.php versions prior to 4.4
			self::wpTearDownAfterClass();
		}
		if ( self::$is_less_than_wp_4 ) {
			reset_mbstring_encoding(); // For <= WP 3.9.13 compatibility - remove_accents() uses naked strlen().
		}
	}

	function get_wp_die_handler( $handler ) {
		return array( __CLASS__, 'wp_die' );
	}

	static $func_args = array();

	static function clear_func_args() {
		self::$func_args = array(
			'wp_clear_auth_cookie' => array(), 'wp_die' => array(), 'wp_redirect' => array(), 'wp_safe_redirect' => array(),
		);
	}

	static function wp_die( $message, $title = '', $args = array() ) {
		self::$func_args['wp_die'][] = compact( 'message', 'title', 'args' );
		throw new WPDieException( count( self::$func_args['wp_die'] ) - 1 );
	}

	static function wp_redirect( $location, $status = 302 ) {
		self::$func_args['wp_redirect'][] = compact( 'location', 'status' );
		return false;
	}

    /**
     */
	function test_db_check_post() {
		$this->assertTrue( is_admin() );

		global $unfc_normalize;
		if ( self::$is_less_than_wp_4 ) {
			// For <= WP 3.9.13 compatibility - filters seem to get left hanging around.
			foreach( $unfc_normalize->post_filters as $filter ) {
				remove_filter( $filter, array( $unfc_normalize, 'tl_normalizer' ), $unfc_normalize->priority );
			}
		}

		$decomposed_str1 = "o\xcc\x88"; // o umlaut.

		$title1 = 'post1-title' . $decomposed_str1;
		$content1 = 'post1-content';

		$post1 = $this->factory->post->create_and_get( array( 'post_title' => $title1, 'post_content' => $content1, 'post_type' => 'post' ) );
		$this->assertTrue( is_object( $post1 ) );
		$this->assertTrue( is_int( $post1->ID ) );

		$decomposed_str2 = "a\xe1\x84\x91\xe1\x85\xb4\xe1\x86\xaf"; // a + decomposed hangul.

		$title2 = 'post2-title';
		$content2 = 'post2-content' . $decomposed_str2;

		$post2 = $this->factory->post->create_and_get( array( 'post_title' => $title2, 'post_content' => $content2, 'post_type' => 'page' ) );
		$this->assertTrue( is_object( $post2 ) );

		$decomposed_str3 = "\xf0\xaf\xa0\x87"; // CJK COMPATIBILITY IDEOGRAPH-2F807

		$title3 = 'post3-title' . str_repeat( "\xc2\x80", UNFC_DB_CHECK_TITLE_MAX_LEN );
		$content3 = 'post3-content';
		// Pre-WP 4.2 can't handle 4-byte UTF-8 (MySQL). Also neither can database used in travis for PHP 5.3.29.
		$dont_use_4byte = self::$is_less_than_wp_4_2 || ( version_compare( PHP_VERSION, '5.3', '>=' ) && version_compare( PHP_VERSION, '5.4', '<' ) );
		$excerpt3 = 'post3-excerpt' . ( $dont_use_4byte ? $decomposed_str1 : $decomposed_str3 );

		$post3 = $this->factory->post->create_and_get( array( 'post_title' => $title3, 'post_content' => $content3, 'post_excerpt' => $excerpt3, 'post_type' => 'post' ) );
		$this->assertTrue( is_object( $post3 ) );
		$this->assertTrue( $post3->post_excerpt === $excerpt3 );

		$title4 = 'post4-title';
		$content4 = 'post4-content';
		$excerpt4 = 'post4-excerpt';

		$post4 = $this->factory->post->create_and_get( array( 'post_title' => $title4, 'post_content' => $content4, 'post_excerpt' => $excerpt4, 'post_type' => 'post' ) );

		$maybe_str = "\xcc\x9b";

		$title5 = 'post5-title' . $maybe_str;
		$content5 = 'post5-content' . $maybe_str;
		$excerpt5 = 'post5-excerpt' . $maybe_str;

		$post5 = $this->factory->post->create_and_get( array( 'post_title' => $title5, 'post_content' => $content5, 'post_excerpt' => $excerpt5, 'post_type' => 'post' ) );

		$admin_notices = array();
		$ret = $unfc_normalize->db_check_items( $admin_notices );

		$this->assertTrue( is_array( $ret['items'] ) );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 3, $ret['num_items'] );
		$this->assertSame( array( $post1->ID, $post2->ID, $post3->ID ), array_map( 'intval', unfc_list_pluck( $ret['items'], 'id' ) ) );

		// Meta.

		$meta_value1_1 = 'meta_value1_1' . $decomposed_str1;
		$meta_value1_2 = 'meta_value1_2' . $decomposed_str1;

		$meta1_1_id = add_post_meta( $post1->ID, 'meta_key1', $meta_value1_1 );
		$meta1_2_id = add_post_meta( $post1->ID, 'meta_key1', $meta_value1_2 );

		$meta2_id = add_post_meta( $post2->ID, 'meta_key2', 'meta_value2' );

		$maybe_str = "\xcc\x9b";

		$meta3_id = add_post_meta( $post3->ID, 'meta_key3', 'meta_value3' . $maybe_str );

		$meta4_id = add_post_meta( $post4->ID, 'meta_key4', 'meta_value4' . $maybe_str );

		$meta5_id = add_post_meta( $post5->ID, 'meta_key5', 'meta_value5' . $decomposed_str2 );

		$admin_notices = array();
		$ret = $unfc_normalize->db_check_items( $admin_notices );

		$this->assertTrue( is_array( $ret['items'] ) );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 4, $ret['num_items'] );
		$this->assertSame( array( $post1->ID, $post2->ID, $post3->ID, $post5->ID ), array_map( 'intval', unfc_list_pluck( $ret['items'], 'id' ) ) );
	}

    /**
     */
	function test_db_check_comment() {
		$this->assertTrue( is_admin() );

		$decomposed_str1 = "o\xcc\x88"; // o umlaut.

		$post1 = $this->factory->post->create_and_get( array( 'post_title' => 'post1-title', 'post_type' => 'post' ) );
		$post2 = $this->factory->post->create_and_get( array( 'post_title' => 'post2-title', 'post_type' => 'post' ) );
		$post3 = $this->factory->post->create_and_get( array( 'post_title' => 'post3-title', 'post_type' => 'post' ) );

		$comment1_1_id = $this->factory->comment->create( array( 'comment_post_ID' => $post1->ID, 'comment_content' => 'comment1-content' . $decomposed_str1 ) );
		$comment1_2_id = $this->factory->comment->create( array( 'comment_post_ID' => $post1->ID, 'comment_author' => 'comment1-author' . $decomposed_str1 ) );
		$comment2_id = $this->factory->comment->create( array( 'comment_post_ID' => $post2->ID, 'comment_author' => 'comment2-author', 'comment_content' => 'comment2-content' ) );

		global $unfc_normalize;
		$admin_notices = array();
		$ret = $unfc_normalize->db_check_items( $admin_notices );

		$this->assertTrue( is_array( $ret['items'] ) );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 2, $ret['num_items'] );
		$this->assertSame( array( $comment1_1_id, $comment1_2_id ), array_map( 'intval', unfc_list_pluck( $ret['items'], 'id' ) ) );

		// Meta.

		$comment3_id = $this->factory->comment->create( array( 'comment_post_ID' => $post3->ID, 'comment_author' => 'comment3-author', 'comment_content' => 'comment3-content' ) );

		$meta1_id = add_comment_meta( $comment1_1_id, 'meta_key1', 'meta_value1' . $decomposed_str1 );
		$meta2_id = add_comment_meta( $comment2_id, 'meta_key2', 'meta_value2' . $decomposed_str1 );
		$meta3_id = add_comment_meta( $comment3_id, 'meta_key3', 'meta_value3' );

		$admin_notices = array();
		$ret = $unfc_normalize->db_check_items( $admin_notices );

		$this->assertTrue( is_array( $ret['items'] ) );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 3, $ret['num_items'] );
		$this->assertSame( array( $comment1_1_id, $comment1_2_id, $comment2_id ), array_map( 'intval', unfc_list_pluck( $ret['items'], 'id' ) ) );
	}

    /**
     */
	function test_db_check_user() {
		$this->assertTrue( is_admin() );

		$decomposed_str1 = "o\xcc\x88"; // o umlaut.

		$user1_id = $this->factory->user->create( array( 'user_login' => 'user1_login', 'display_name' => 'display1' . $decomposed_str1 ) );
		$user2_id = $this->factory->user->create( array( 'user_login' => 'user2_login', 'display_name' => 'display2' ) );
		$user3_id = $this->factory->user->create( array( 'user_login' => 'user3_login', 'display_name' => 'display3' ) );
		$user4_id = $this->factory->user->create( array( 'user_login' => 'user4_login', 'display_name' => 'display4', 'last_name' => 'last4' . $decomposed_str1 ) );

		global $unfc_normalize;
		$admin_notices = array();
		$ret = $unfc_normalize->db_check_items( $admin_notices );

		$this->assertTrue( is_array( $ret['items'] ) );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 2, $ret['num_items'] );
		$this->assertSame( array( $user1_id, $user4_id ), array_map( 'intval', unfc_list_pluck( $ret['items'], 'id' ) ) );
	}

    /**
     */
	function test_db_check_term() {
		$this->assertTrue( is_admin() );

		global $unfc_normalize;
		if ( self::$is_less_than_wp_4 ) {
			// For <= WP 3.9.13 compatibility - filters seem to get left hanging around.
			foreach( $unfc_normalize->term_filters as $filter ) {
				remove_filter( $filter, array( $unfc_normalize, 'tl_normalizer' ), $unfc_normalize->priority );
			}
		}

		$decomposed_str1 = "o\xcc\x88"; // o umlaut.

		$term1_id = $this->factory->term->create( array( 'name' => 'term1' . $decomposed_str1, 'taxonomy' => 'category', 'description' => 'desc1' ) );
		$term2_id = $this->factory->term->create( array( 'name' => 'term2', 'taxonomy' => 'post_tag', 'description' => 'desc2' . $decomposed_str1 ) );
		$term3_id = $this->factory->term->create( array( 'name' => 'term3', 'taxonomy' => 'post_tag', 'description' => 'desc3' ) );
		$term4_id = $this->factory->term->create( array( 'name' => 'term4' . $decomposed_str1, 'taxonomy' => 'post_tag', 'description' => 'desc4' . $decomposed_str1 ) );
		$term5_id = $this->factory->term->create( array( 'name' => 'term5' . $decomposed_str1, 'taxonomy' => 'category', 'description' => 'desc5' . $decomposed_str1 ) );
		$term6_id = $this->factory->term->create( array( 'name' => 'term6', 'taxonomy' => 'category', 'description' => 'desc6' ) );

		$admin_notices = array();
		$ret = $unfc_normalize->db_check_items( $admin_notices );

		$this->assertTrue( is_array( $ret['items'] ) );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 4, $ret['num_items'] );
		$this->assertSame( array( $term1_id, $term2_id, $term4_id, $term5_id ), array_map( 'intval', unfc_list_pluck( $ret['items'], 'id' ) ) );

		global $wpdb;
		if ( isset( $wpdb->termmeta ) ) { // Check if termmeta.
			$term6_id = $this->factory->term->create( array( 'name' => 'term6', 'taxonomy' => 'post_tag', 'description' => 'desc6' ) );

			$meta_val1 = 'meta_val1' . $decomposed_str1;
			add_term_meta( $term6_id, 'meta_key1', $meta_val1 );

			$admin_notices = array();
			$ret = $unfc_normalize->db_check_items( $admin_notices );

			$this->assertTrue( is_array( $ret['items'] ) );
			$this->assertSame( 1, count( $admin_notices ) );
			$this->assertSame( 5, $ret['num_items'] );
			$this->assertSame( array( $term1_id, $term2_id, $term4_id, $term5_id, $term6_id ), array_map( 'intval', unfc_list_pluck( $ret['items'], 'id' ) ) );

			// For WP < 4.2.
			$termmeta = $wpdb->termmeta;
			unset( $wpdb->termmeta );

			$admin_notices = array();
			$ret = $unfc_normalize->db_check_items( $admin_notices );

			$this->assertTrue( is_array( $ret['items'] ) );
			$this->assertSame( 1, count( $admin_notices ) );
			$this->assertSame( 4, $ret['num_items'] );
			$this->assertSame( array( $term1_id, $term2_id, $term4_id, $term5_id ), array_map( 'intval', unfc_list_pluck( $ret['items'], 'id' ) ) );

			$wpdb->termmeta = $termmeta;
		}
	}

    /**
     */
	function test_db_check_options() {
		$this->assertTrue( is_admin() );

		$decomposed_str1 = "o\xcc\x88"; // o umlaut.

		add_option( 'option1', 'val1' . $decomposed_str1 );
		add_option( 'option2', 'val2' );
		add_option( 'option3', 'val3' . $decomposed_str1 );
		add_option( 'option4', 'val4' );
		
		global $wpdb;
		$ids = $wpdb->get_col( "SELECT option_id FROM {$wpdb->options} WHERE option_name IN ('option1','option3') ORDER BY option_id ASC" );
		$ids = array_map( 'intval', $ids );

		global $unfc_normalize;
		$admin_notices = array();
		$ret = $unfc_normalize->db_check_items( $admin_notices );

		$this->assertTrue( is_array( $ret['items'] ) );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 2, $ret['num_items'] );
		$this->assertSame( $ids, array_map( 'intval', unfc_list_pluck( $ret['items'], 'id' ) ) );
	}

    /**
     */
	function test_db_check_settings() {
		$this->assertTrue( is_admin() );
		$this->assertTrue( is_multisite() );

		$decomposed_str1 = "o\xcc\x88"; // o umlaut.

		add_site_option( 'option1', 'val1' . $decomposed_str1 );
		add_site_option( 'option2', 'val2' );
		add_site_option( 'option3', 'val3' );
		add_site_option( 'option4', 'val4' . $decomposed_str1 );

		global $wpdb;
		$ids = $wpdb->get_col( "SELECT meta_id FROM {$wpdb->sitemeta} WHERE meta_key IN ('option1','option4') ORDER BY meta_id ASC" );
		$ids = array_map( 'intval', $ids );

		global $unfc_normalize;
		$admin_notices = array();
		$ret = $unfc_normalize->db_check_items( $admin_notices );

		$this->assertTrue( is_array( $ret['items'] ) );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 2, $ret['num_items'] );
		$this->assertSame( $ids, array_map( 'intval', unfc_list_pluck( $ret['items'], 'id' ) ) );
	}

    /**
     */
	function test_db_check_link() {
		$this->assertTrue( is_admin() );

		add_filter( 'pre_option_link_manager_enabled', '__return_true' );

		$decomposed_str1 = "o\xcc\x88"; // o umlaut.

		$linkdata1 = array(
			'link_url' => 'http://example.org/address',
			'link_name' => 'name1' . $decomposed_str1,
			'link_image' => 'http://example.org/image' . $decomposed_str1 . '.jpg',
			'link_description' => 'desc' . $decomposed_str1,
			'link_notes' => 'notes' . $decomposed_str1,
			'link_rss' => 'http://example.org/rss',
		);

		$link1_id = wp_insert_link( $linkdata1 );
		$this->assertTrue( is_numeric( $link1_id ) );
		$this->assertTrue( $link1_id > 0 );

		$linkdata2 = array(
			'link_url' => 'http://example.org/address',
			'link_name' => 'name2',
			'link_image' => 'http://example.org/image.jpg',
			'link_description' => 'desc' . $decomposed_str1,
			'link_notes' => 'notes',
			'link_rss' => 'http://example.org/rss',
		);

		$link2_id = wp_insert_link( $linkdata2 );
		$this->assertTrue( is_numeric( $link2_id ) );
		$this->assertTrue( $link2_id > 0 );

		$linkdata3 = array(
			'link_url' => 'http://example.org/address',
			'link_name' => 'name3',
		);

		$link3_id = wp_insert_link( $linkdata3 );
		$this->assertTrue( is_numeric( $link3_id ) );
		$this->assertTrue( $link3_id > 0 );

		global $unfc_normalize;
		unset( $unfc_normalize->db_fields['link'] );
		$admin_notices = array();
		$ret = $unfc_normalize->db_check_items( $admin_notices );

		$this->assertTrue( is_array( $ret['items'] ) );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 2, $ret['num_items'] );
		$this->assertSame( array( $link1_id, $link2_id ), array_map( 'intval', unfc_list_pluck( $ret['items'], 'id' ) ) );

		remove_filter( 'pre_option_link_manager_enabled', '__return_true', 10 );
	}

    /**
     */
	function test_db_check_items() {
		$this->assertTrue( is_admin() );

		wp_set_current_user( 1 ); // Need current user for user options.

		global $unfc_normalize;
		$admin_notices = array();
		$ret = $unfc_normalize->db_check_items( $admin_notices );

		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 0, $ret['num_items'] );
		$this->assertEmpty( $ret['items'] );

		$decomposed_str1 = "o\xcc\x88"; // o umlaut.

		$title1 = 'post1-title' . $decomposed_str1;
		$content1 = 'post1-content';

		$post1 = $this->factory->post->create_and_get( array( 'post_title' => $title1, 'post_content' => $content1, 'post_type' => 'post' ) );
		$this->assertTrue( is_object( $post1 ) );
		$this->assertTrue( is_int( $post1->ID ) );

		$title2 = 'post2-title';
		$content2 = 'post2-content' . $decomposed_str1;

		$post2 = $this->factory->post->create_and_get( array( 'post_title' => $title2, 'post_content' => $content2, 'post_type' => 'post' ) );
		$this->assertTrue( is_object( $post2 ) );

		$title3 = 'post3-title';
		$content3 = 'post3-content';
		$excerpt3 = 'post3-excerpt';

		$post3 = $this->factory->post->create_and_get( array( 'post_title' => $title3, 'post_content' => $content3, 'post_excerpt' => $excerpt3, 'post_type' => 'post' ) );
		$this->assertTrue( is_object( $post3 ) );

		$title4 = 'post4-title';
		$content4 = 'post4-content';
		$excerpt4 = 'post4-excerpt' . $decomposed_str1;

		$post4 = $this->factory->post->create_and_get( array( 'post_title' => $title4, 'post_content' => $content4, 'post_excerpt' => $excerpt4, 'post_type' => 'post' ) );
		$this->assertTrue( is_object( $post4 ) );

		$title1 = 'page1-title' . $decomposed_str1;
		$content1 = 'page1-content';

		$page1 = $this->factory->post->create_and_get( array( 'post_title' => $title1, 'post_content' => $content1, 'post_type' => 'page' ) );
		$this->assertTrue( is_object( $page1 ) );
		$this->assertTrue( is_int( $page1->ID ) );

		$user1_id = $this->factory->user->create( array( 'user_login' => 'user1_login', 'display_name' => 'display1' ) );
		$this->assertTrue( is_int( $user1_id ) );
		$user2_id = $this->factory->user->create( array( 'user_login' => 'user2_login', 'display_name' => 'display2' . $decomposed_str1 ) );
		$this->assertTrue( is_int( $user2_id ) );

		$_REQUEST = array( 'unfc_type' => 'post:post' );
		$admin_notices = array();
		$ret = $unfc_normalize->db_check_items( $admin_notices );

		$this->assertTrue( is_array( $ret['items'] ) );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 3, $ret['num_items'] );
		$this->assertSame( array( $post1->ID, $post2->ID, $post4->ID ), array_map( 'intval', unfc_list_pluck( $ret['items'], 'id' ) ) );

		$_REQUEST = array( 'unfc_type' => 'post:page' );
		$admin_notices = array();
		$ret = $unfc_normalize->db_check_items( $admin_notices );

		$this->assertTrue( is_array( $ret['items'] ) );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 1, $ret['num_items'] );
		$this->assertSame( array( $page1->ID ), array_map( 'intval', unfc_list_pluck( $ret['items'], 'id' ) ) );

		$_REQUEST = array( 'unfc_type' => 'user' );
		$admin_notices = array();
		$ret = $unfc_normalize->db_check_items( $admin_notices );

		$this->assertTrue( is_array( $ret['items'] ) );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 1, $ret['num_items'] );
		$this->assertSame( array( $user2_id ), array_map( 'intval', unfc_list_pluck( $ret['items'], 'id' ) ) );

		$_REQUEST = array( 'unfc_type' => 'user' );
		$admin_notices = array();
		$ret = $unfc_normalize->db_check_items( $admin_notices );

		$this->assertTrue( is_array( $ret['items'] ) );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 1, $ret['num_items'] );
		$this->assertSame( array( $user2_id ), array_map( 'intval', unfc_list_pluck( $ret['items'], 'id' ) ) );

		$_REQUEST = array();
		$admin_notices = array();
		$ret = $unfc_normalize->db_check_items( $admin_notices );

		$this->assertTrue( is_array( $ret['items'] ) );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 5, $ret['num_items'] );
		$this->assertSame( array( $post1->ID, $post2->ID, $post4->ID, $page1->ID, $user2_id ), array_map( 'intval', unfc_list_pluck( $ret['items'], 'id' ) ) );
	}

    /**
     */
	function test_db_check_meta() {

		$decomposed_str1 = "o\xcc\x88"; // o umlaut.

		$post1 = $this->factory->post->create_and_get( array( 'post_title' => 'post1-title', 'post_type' => 'post' ) );

		$repeat = 16384;
		$meta_value1_1 = 'meta_value1_1'. str_repeat( 'a', $repeat )  . $decomposed_str1;
		$meta_value1_2 = $decomposed_str1 . 'meta_value1_2' . $decomposed_str1 . str_repeat( 'a', $repeat ) . 'b';

		// add_post_meta() expects slashed data.
		$meta1_1_id = add_post_meta( $post1->ID, 'meta_key1', wp_slash( $meta_value1_1 ) );
		$meta1_2_id = add_post_meta( $post1->ID, 'meta_key1', wp_slash( $meta_value1_2 ) );

		global $unfc_normalize;
		UNFC_Normalize::$have_set_group_concat_max_len = false; // Make sure we set this for this session.

		$_REQUEST = array();

		$admin_notices = array();
		$ret = $unfc_normalize->db_check_items( $admin_notices );

		$this->assertTrue( is_array( $ret['items'] ) );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 1, $ret['num_items'] );
		$this->assertSame( 1, count( $ret['items'] ) );

		$meta_value1_3 = 'meta_value1_3' . $decomposed_str1 . '\\' . "\x1f";
		$meta_value1_4 = '\\' . "\x1f" . 'meta_value1_4' . $decomposed_str1 . '\\';

		$meta1_3_id = add_post_meta( $post1->ID, 'meta_key1', wp_slash( $meta_value1_3 ) );
		$meta1_4_id = add_post_meta( $post1->ID, 'meta_key1', wp_slash( $meta_value1_4 ) );

		$admin_notices = array();
		$ret = $unfc_normalize->db_check_items( $admin_notices );

		$this->assertTrue( is_array( $ret['items'] ) );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 1, $ret['num_items'] );
		$this->assertSame( 1, count( $ret['items'] ) );

		$meta_value1_5 = 'meta_value1_5' . $decomposed_str1 . '\\\\';
		$meta_value1_6 = '\\' . 'meta_value1_6' . $decomposed_str1 . '\\\\' . "\x1f";
		$meta_value1_7 = '\\' . 'meta_value1_7' . $decomposed_str1;

		$meta1_5_id = add_post_meta( $post1->ID, 'meta_key1', wp_slash( $meta_value1_5 ) );
		$meta1_6_id = add_post_meta( $post1->ID, 'meta_key1', wp_slash( $meta_value1_6 ) );
		$meta1_7_id = add_post_meta( $post1->ID, 'meta_key1', wp_slash( $meta_value1_7 ) );

		$admin_notices = array();
		$ret = $unfc_normalize->db_check_items( $admin_notices );

		$this->assertTrue( is_array( $ret['items'] ) );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 1, $ret['num_items'] );
		$this->assertSame( 1, count( $ret['items'] ) );
	}

    /**
     */
	function test_db_check_admin_menu() {
		$this->assertTrue( is_admin() );

		wp_set_current_user( 1 ); // Need manage_options privileges.

		$_REQUEST['page'] = UNFC_DB_CHECK_MENU_SLUG;

		global $unfc_normalize;

		do_action( 'init' );

		do_action( 'admin_menu' );

		$hook_suffix = 'admin_page_' . UNFC_DB_CHECK_MENU_SLUG;

		$this->assertSame( $hook_suffix, $unfc_normalize->db_check_hook_suffix );

		$_REQUEST['_wp_http_referer'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;
		$_REQUEST['action'] = '';

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$this->assertSame( '_wp_http_referer', $unfc_normalize->db_check_button() );

		self::clear_func_args();

		$_REQUEST['unfc_db_check_items'] = 'unfc_db_check_items';

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );

		$this->assertSame( $_REQUEST['unfc_db_check_items'], $unfc_normalize->db_check_button() );

		//do_action( 'admin_init' );
	}

    /**
     */
	function test_db_check_button() {
		global $unfc_normalize;

		$_REQUEST = array();
		$this->assertFalse( $unfc_normalize->db_check_button() );

		$_REQUEST = array();
		$_REQUEST['unfc_db_check_items'] = 'unfc_db_check_items';
		$this->assertSame( $_REQUEST['unfc_db_check_items'], $unfc_normalize->db_check_button() );

		$_REQUEST = array();
		$_REQUEST['unfc_db_check_normalize_all'] = 'unfc_db_check_normalize_all';
		$this->assertSame( $_REQUEST['unfc_db_check_normalize_all'], $unfc_normalize->db_check_button() );

		$_REQUEST = array();
		$_REQUEST['unfc_db_check_slugs'] = 'unfc_db_check_slugs';
		$this->assertSame( $_REQUEST['unfc_db_check_slugs'], $unfc_normalize->db_check_button() );

		$_REQUEST = array();
		$_REQUEST['action'] = 'unfc_db_check_normalize_slugs';
		$this->assertSame( $_REQUEST['action'], $unfc_normalize->db_check_button() );

		$_REQUEST = array();
		$_REQUEST['action2'] = 'unfc_db_check_normalize_slugs';
		$this->assertSame( $_REQUEST['action2'], $unfc_normalize->db_check_button() );

		$_REQUEST = array();
		$_REQUEST['screen-options-apply'] = 'Apply';
		$_REQUEST['wp_screen_options'] = array( 'option' => UNFC_DB_CHECK_PER_PAGE );
		$this->assertSame( UNFC_DB_CHECK_PER_PAGE, $unfc_normalize->db_check_button() );

		$_REQUEST = array();
		$_REQUEST['_wp_http_referer'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;
		$this->assertSame( '_wp_http_referer', $unfc_normalize->db_check_button() );
	}

    /**
     */
	function test_db_check_transient() {
		global $unfc_normalize;

		$_REQUEST = array();
		$this->assertFalse( $unfc_normalize->db_check_transient() );

		$items = array();
		$items[] = array( 'id' => 1, 'title' => 'post-title1', 'type' => 'post', 'subtype' => 'post', 'field' => 'post_content', 'idx' => $item1_idx = count( $items ) );

		$_REQUEST['_wpnonce_items'] = wp_create_nonce( UNFC_DB_CHECK_MENU_SLUG . '-items' );
		$transient_key = 'unfc_db_check_items' . $_REQUEST['_wpnonce_items'];
		set_transient( $transient_key, array( 'num_items' => count( $items ), 'items' => $items ), intval( wp_nonce_tick() ) );

		$_REQUEST = array();
		$_REQUEST['unfc_trans'] = $transient_key;
		$this->assertSame( $transient_key, $unfc_normalize->db_check_transient() );
		$this->assertSame( $transient_key, $unfc_normalize->db_check_transient( 'unfc_db_check_items' ) );
		$this->assertSame( $transient_key, $unfc_normalize->db_check_transient( 'unfc_db_check_items', true /*dont_get*/ ) );
		$this->assertSame( $transient_key, $unfc_normalize->db_check_transient( 'unfc_db_check_items', false /*dont_get*/, true /*dont_set*/ ) );
		$this->assertFalse( $unfc_normalize->db_check_transient( 'unfc_db_check_slugs' ) );

		set_transient( $transient_key, array( 'num_slugs' => count( $items ), 'slugs' => $items ), intval( wp_nonce_tick() ) );
		$this->assertFalse( $unfc_normalize->db_check_transient( 'unfc_db_check_items' ) );

		$_REQUEST['_wpnonce_slugs'] = wp_create_nonce( UNFC_DB_CHECK_MENU_SLUG . '-slugs' );
		$transient_key = 'unfc_db_check_slugs' . $_REQUEST['_wpnonce_slugs'];
		set_transient( $transient_key, array( 'num_slugs' => count( $items ), 'slugs' => $items ), intval( wp_nonce_tick() ) );

		$_REQUEST = array();
		$_REQUEST['unfc_trans'] = $transient_key;
		$this->assertSame( $transient_key, $unfc_normalize->db_check_transient() );
		$this->assertSame( $transient_key, $unfc_normalize->db_check_transient( 'unfc_db_check_slugs' ) );
		$this->assertSame( $transient_key, $unfc_normalize->db_check_transient( 'unfc_db_check_slugs', true /*dont_get*/ ) );
		$this->assertSame( $transient_key, $unfc_normalize->db_check_transient( 'unfc_db_check_slugs', false /*dont_get*/, true /*dont_set*/ ) );
		$this->assertFalse( $unfc_normalize->db_check_transient( 'unfc_db_check_items' ) );

		set_transient( $transient_key, array( 'num_items' => count( $items ), 'items' => $items ), intval( wp_nonce_tick() ) );
		$this->assertFalse( $unfc_normalize->db_check_transient( 'unfc_db_check_slugs' ) );
	}

    /**
     */
	function test_db_check_db_check() {
		$this->assertTrue( is_admin() );

		global $unfc_normalize;

		$_REQUEST = array();
		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		$out = wp_set_current_user( 1 ); // Need manage_options cap to add load-XXX

		do_action( 'init' );
		do_action( 'admin_init' );

		do_action( 'admin_menu' );

		$hook_suffix = 'admin_page_' . UNFC_DB_CHECK_MENU_SLUG;

		$this->assertSame( $hook_suffix, $unfc_normalize->db_check_hook_suffix );

		// Permission errors.

		$editor_user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$out = wp_set_current_user( $editor_user_id );

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0];
		$this->assertTrue( false !== stripos( $args['message'], 'allowed' ) );

		self::clear_func_args();

		try {
			do_action( $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0];
		$this->assertTrue( false !== stripos( $args['message'], 'allowed' ) );

		self::clear_func_args();

		$out = wp_set_current_user( 1 ); // Need manage_options cap.

		$_REQUEST = array();
		$_REQUEST['unfc_db_check_items'] = 'unfc_db_check_items';

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0];
		$this->assertSame( 1, preg_match( '/failure|error|wrong/i', $args['title'] ) ); // Cater for various versions of message.

		self::clear_func_args();

		// Permission ok, initial load.

		$_REQUEST = array();
		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		$out = wp_set_current_user( 1 ); // Need manage_options cap.

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 0, count( self::$func_args['wp_die'] ) );

		ob_start();
		do_action( $hook_suffix );
		$out = ob_get_clean();

		$this->assertTrue( false !== stripos( $out, 'unfc_db_check_items' ) );
		$this->assertTrue( false !== stripos( $out, 'unfc_db_check_slugs' ) );
		$this->assertTrue( false === stripos( $out, 'unfc_db_check_normalize_all' ) );

		// Scan. 1 item.

		$num_items = 0;

		$decomposed_str1 = "o\xcc\x88"; // o umlaut.

		$title1 = 'post1-title' . $decomposed_str1;
		$content1 = 'post1-content';

		$post1 = $this->factory->post->create_and_get( array( 'post_title' => $title1, 'post_content' => $content1, 'post_type' => 'post' ) );
		$this->assertTrue( is_object( $post1 ) );
		$this->assertTrue( is_int( $post1->ID ) );
		$num_items++;

		$_REQUEST = array();
		$_REQUEST['unfc_db_check_items'] = 'unfc_db_check_items';
		$_REQUEST['_wpnonce_items'] = wp_create_nonce( UNFC_DB_CHECK_MENU_SLUG . '-items' );
		$this->assertTrue( 1 === wp_verify_nonce( $_REQUEST['_wpnonce_items'], UNFC_DB_CHECK_MENU_SLUG . '-items' ) );

		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;
		$_REQUEST['unfc_type'] = 'post';
		$_REQUEST['orderby'] = 'type';
		$_REQUEST['order'] = 'desc';
		$_REQUEST['paged'] = '1';

		add_filter( 'unfc_batch_limit', array( $this, 'unfc_batch_limit_filter' ) );

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertArrayHasKey( 'title', $die );
		$this->assertSame( 'wp_redirect', $die['title'] );
		$this->assertArrayHasKey( 'args', $die );
		$this->assertArrayHasKey( 'num_items', $die['args'] );
		$this->assertSame( 1, (int) $die['args']['num_items'] );

		$admin_notices_filter = is_network_admin() ? 'network_admin_notices' : ( is_user_admin() ? 'user_admin_notices' : 'admin_notices' );
		ob_start();
		do_action( $admin_notices_filter );
		$out = ob_get_clean();

		$this->assertTrue( false !== stripos( $out, 'detected' ) );
		$this->assertTrue( false !== stripos( $out, '1' ) );

		$unfc_normalize->db_check_num_items = $die['args']['num_items'];
		$unfc_normalize->db_check_items = $die['args']['items'];

		ob_start();
		do_action( $hook_suffix );
		do_action( 'admin_print_footer_scripts' );
		$out = ob_get_clean();

		$this->assertTrue( false !== stripos( $out, $title1 ) );
		$this->assertTrue( false !== stripos( $out, UNFC_DB_CHECK_ITEMS_LIST_SEL ) );

		remove_filter( 'unfc_batch_limit', array( $this, 'unfc_batch_limit_filter' ) );

		self::clear_func_args();

		// Multiple items.

		$title2 = 'post2-title';
		$content2 = 'post2-content' . $decomposed_str1;

		$post2 = $this->factory->post->create_and_get( array( 'post_title' => $title2, 'post_content' => $content2, 'post_type' => 'post' ) );
		$this->assertTrue( is_object( $post2 ) );
		$this->assertTrue( is_int( $post2->ID ) );
		$num_items++;

		$comment1_id = $this->factory->comment->create( array( 'comment_post_ID' => $post1->ID, 'comment_content' => 'comment1-content' . $decomposed_str1 ) );
		$num_items++;
		$comment2_id = $this->factory->comment->create( array( 'comment_post_ID' => $post2->ID, 'comment_author' => 'comment2-author', 'comment_content' => '' ) );

		$user1_id = $this->factory->user->create( array( 'user_login' => 'user1_login', 'display_name' => 'display1' . $decomposed_str1 ) );
		$num_items++;
		$user2_id = $this->factory->user->create( array( 'user_login' => 'user2_login', 'display_name' => 'display2' ) );

		$term1_id = $this->factory->term->create( array( 'name' => 'term1' . $decomposed_str1, 'taxonomy' => 'category', 'description' => 'desc1' ) );
		$num_items++;
		$term2_id = $this->factory->term->create( array( 'name' => 'term2', 'taxonomy' => 'post_tag', 'description' => 'desc2' . $decomposed_str1 ) );
		$num_items++;
		$term3_id = $this->factory->term->create( array( 'name' => 'term3', 'taxonomy' => 'post_tag', 'description' => 'desc3' ) );

		$menu1_id = wp_create_nav_menu( 'menu1-name' . $decomposed_str1 );
		$this->assertTrue( is_numeric( $menu1_id ) );
		$this->assertTrue( $menu1_id > 0 );
		$num_items++;
		$item_data = array(
			'menu-item-title' => 'item1-title' . $decomposed_str1,
			'menu-item-url' => 'item1-url' . $decomposed_str1,
		);
		$menu_item1_id = wp_update_nav_menu_item( $menu1_id, 0, $item_data );
		$this->assertTrue( is_numeric( $menu_item1_id ) );
		$this->assertTrue( $menu_item1_id > 0 );
		$num_items++;

		add_option( 'option1', 'val1' . $decomposed_str1 );
		add_option( 'option2', 'val2' );
		global $wpdb;
		$option_id1 = intval( $wpdb->get_var( "SELECT option_id FROM {$wpdb->options} WHERE option_name IN ('option1') ORDER BY option_id ASC" ) );
		$num_items++;

		add_site_option( 'option1', 'val1' . $decomposed_str1 );
		add_site_option( 'option2', 'val2' );
		$setting_id1 = intval( $wpdb->get_var( "SELECT meta_id FROM {$wpdb->sitemeta} WHERE meta_key IN ('option1','option4') ORDER BY meta_id ASC" ) );
		$num_items++;

		$linkdata1 = array(
			'link_url' => 'http://example.org/address',
			'link_name' => 'name1' . $decomposed_str1,
			'link_image' => 'http://example.org/image' . $decomposed_str1 . '.jpg',
			'link_description' => 'desc' . $decomposed_str1,
			'link_notes' => 'notes' . $decomposed_str1,
			'link_rss' => 'http://example.org/rss',
		);

		$link1_id = wp_insert_link( $linkdata1 );
		$this->assertTrue( is_numeric( $link1_id ) );

		$_REQUEST = array();
		$_REQUEST['unfc_db_check_items'] = 'unfc_db_check_items';
		$_REQUEST['_wpnonce_items'] = wp_create_nonce( UNFC_DB_CHECK_MENU_SLUG . '-items' );
		$this->assertTrue( 1 === wp_verify_nonce( $_REQUEST['_wpnonce_items'], UNFC_DB_CHECK_MENU_SLUG . '-items' ) );

		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		add_filter( 'unfc_list_limit', array( $this, 'unfc_list_limit_filter' ) );

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertArrayHasKey( 'num_items', $die['args'] );
		$this->assertSame( $num_items, (int) $die['args']['num_items'] );

		$unfc_normalize->db_check_num_items = $die['args']['num_items'];
		$unfc_normalize->db_check_items = $die['args']['items'];

		ob_start();
		do_action( $hook_suffix );
		$out = ob_get_clean();

		$this->assertTrue( false !== stripos( $out, $title1 ) );
		$this->assertTrue( false === stripos( $out, $title2 ) );

		remove_filter( 'unfc_list_limit', array( $this, 'unfc_list_limit_filter' ) );

		self::clear_func_args();

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertArrayHasKey( 'num_items', $die['args'] );
		$this->assertSame( $num_items, (int) $die['args']['num_items'] );

		$unfc_normalize->db_check_num_items = $die['args']['num_items'];
		$unfc_normalize->db_check_items = $die['args']['items'];

		$transient_key = 'unfc_db_check_items' . $_REQUEST['_wpnonce_items'];
		set_transient( $transient_key, array( 'num_items' => $die['args']['num_items'], 'items' => $die['args']['items'] ), intval( wp_nonce_tick() ) );

		$_REQUEST['unfc_trans'] = $transient_key;

		ob_start();
		do_action( $hook_suffix );
		$out = ob_get_clean();

		$this->assertTrue( false !== stripos( $out, $title1 ) );
		$this->assertTrue( false !== stripos( $out, $title2 ) );
		$this->assertTrue( false !== stripos( $out, 'option1' ) );
		$this->assertTrue( false !== stripos( $out, 'unfc_trans' ) );

		self::clear_func_args();

		add_filter( 'pre_option_link_manager_enabled', '__return_true' );
		$num_items++;

		$_REQUEST['unfc_type'] = 'asdf';

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertArrayHasKey( 'num_items', $die['args'] );
		$this->assertSame( $num_items, (int) $die['args']['num_items'] );

		$unfc_normalize->db_check_num_items = $die['args']['num_items'];
		$unfc_normalize->db_check_items = $die['args']['items'];

		$_REQUEST['orderby'] = 'field';

		ob_start();
		do_action( $hook_suffix );
		$out = ob_get_clean();

		$this->assertTrue( false !== stripos( $out, $title1 ) );
		$this->assertTrue( false !== stripos( $out, $linkdata1['link_name'] ) );

		remove_filter( 'pre_option_link_manager_enabled', '__return_true', 10 );

		self::clear_func_args();
		delete_transient( 'unfc_admin_notices' );

		global $wpdb;
		$ready = $wpdb->ready;
		$last_result = $wpdb->last_result;
		$wpdb->ready = false;
		$wpdb->last_result = null;

		$_REQUEST = array();
		$_REQUEST['unfc_db_check_items'] = 'unfc_db_check_items';
		$_REQUEST['_wpnonce_items'] = wp_create_nonce( UNFC_DB_CHECK_MENU_SLUG . '-items' );
		$this->assertTrue( 1 === wp_verify_nonce( $_REQUEST['_wpnonce_items'], UNFC_DB_CHECK_MENU_SLUG . '-items' ) );

		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertArrayHasKey( 'num_items', $die['args'] );
		$this->assertSame( 0, (int) $die['args']['num_items'] );
		$this->assertSame( 'error', $die['args'][0][0] );
		$this->assertSame( $unfc_normalize->db_check_error_msg( UNFC_DB_CHECK_DB_ERROR ), $die['args'][0][1] );

		$wpdb->ready = $ready;
		$wpdb->last_result = $last_result;
	}

	static $batch_limit = 1;

	function unfc_batch_limit_filter( $limit ) {
		return self::$batch_limit;
	}

	static $list_limit = 1;

	function unfc_list_limit_filter( $limit ) {
		return self::$list_limit;
	}

    /**
     */
	function test_db_check_normalize_all() {
		$this->assertTrue( is_admin() );

		global $unfc_normalize;

		$_REQUEST = array();
		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		$out = wp_set_current_user( 1 ); // Need manage_options cap to add load-XXX

		do_action( 'init' );

		do_action( 'admin_menu' );

		$hook_suffix = 'admin_page_' . UNFC_DB_CHECK_MENU_SLUG;

		$this->assertSame( $hook_suffix, $unfc_normalize->db_check_hook_suffix );

		// Permission errors.

		$_REQUEST = array();
		$_REQUEST['unfc_db_check_normalize_all'] = 'unfc_db_check_normalize_all';

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0];
		$this->assertSame( 1, preg_match( '/failure|error|wrong/i', $args['title'] ) ); // Cater for various versions of message.

		self::clear_func_args();

		$decomposed_str1 = "o\xcc\x88"; // o umlaut.

		$num_updates = 0;

		$title1 = 'post1-title' . $decomposed_str1;
		$content1 = 'post1-content';
		$num_updates++;

		$post1 = $this->factory->post->create_and_get( array( 'post_title' => $title1, 'post_content' => $content1, 'post_type' => 'post' ) );
		$this->assertTrue( is_object( $post1 ) );
		$this->assertTrue( is_int( $post1->ID ) );

		$title2 = 'post2-title';
		$content2 = 'post2-content' . $decomposed_str1;
		$num_updates++;

		$post2 = $this->factory->post->create_and_get( array( 'post_title' => $title2, 'post_content' => $content2, 'post_type' => 'post' ) );
		$this->assertTrue( is_object( $post2 ) );

		$title3 = 'post3-title';
		$content3 = 'post3-content';
		$excerpt3 = 'post3-excerpt';

		$post3 = $this->factory->post->create_and_get( array( 'post_title' => $title3, 'post_content' => $content3, 'post_excerpt' => $excerpt3, 'post_type' => 'post' ) );
		$this->assertTrue( is_object( $post3 ) );

		$title4 = 'post4-title';
		$content4 = 'post4-content' . $decomposed_str1;
		$excerpt4 = 'post4-excerpt' . $decomposed_str1;
		$num_updates++;

		$post4 = $this->factory->post->create_and_get( array( 'post_title' => $title4, 'post_content' => $content4, 'post_excerpt' => $excerpt4, 'post_type' => 'post' ) );
		$this->assertTrue( is_object( $post4 ) );

		$page_title1 = 'page1-title' . $decomposed_str1;
		$page_content1 = 'page1-content';
		$num_updates++;

		$page1 = $this->factory->post->create_and_get( array( 'post_title' => $page_title1, 'post_content' => $page_content1, 'post_type' => 'page' ) );
		$this->assertTrue( is_object( $page1 ) );
		$this->assertTrue( is_int( $page1->ID ) );

		$page_title2 = 'page2-title';
		$page_excerpt2 = 'page2-excerpt' . $decomposed_str1;
		$num_updates++;

		$page2 = $this->factory->post->create_and_get( array( 'post_title' => $page_title2, 'post_excerpt' => $page_excerpt2, 'post_type' => 'page' ) );
		$this->assertTrue( is_object( $page2 ) );
		$this->assertTrue( is_int( $page2->ID ) );

		$meta_value1_1_1 = 'meta_value1_1_1' . $decomposed_str1;
		$meta_value1_1_2 = 'meta_value1_1_2' . $decomposed_str1;
		$meta_value1_2 = 'meta_value1_2' . $decomposed_str1;

		$meta1_1_1_id = add_post_meta( $post1->ID, 'meta_key1', $meta_value1_1_1 );
		$meta1_1_2_id = add_post_meta( $post1->ID, 'meta_key1', $meta_value1_1_2 );
		$meta1_2_id = add_post_meta( $post1->ID, 'meta_key2', $meta_value1_2 );

		$meta2_id = add_post_meta( $page2->ID, 'meta_key2', 'meta_value2' . $decomposed_str1 );

		$meta3_id = add_post_meta( $post3->ID, 'meta_key3', 'meta_value3' );

		$comment1_1_id = $this->factory->comment->create( array( 'comment_post_ID' => $post1->ID, 'comment_content' => 'comment1-content' . $decomposed_str1 ) );
		$num_updates++;
		$comment1_2_id = $this->factory->comment->create( array( 'comment_post_ID' => $post1->ID, 'comment_author' => 'comment1-author' . $decomposed_str1 ) );
		$comment2_id = $this->factory->comment->create( array( 'comment_post_ID' => $post2->ID, 'comment_author' => 'comment2-author', 'comment_content' => '' ) );
		$num_updates++;

		$user1_id = $this->factory->user->create( array( 'user_login' => 'user1_login', 'display_name' => 'display1' . $decomposed_str1 ) );
		$num_updates++;
		$user2_id = $this->factory->user->create( array( 'user_login' => 'user2_login', 'display_name' => 'display2' ) );

		$term1_id = $this->factory->term->create( array( 'name' => 'term1' . $decomposed_str1, 'taxonomy' => 'post_tag', 'description' => 'desc1' . $decomposed_str1 ) );
		$num_updates++;
		$term2_id = $this->factory->term->create( array( 'name' => 'term2' . $decomposed_str1, 'taxonomy' => 'category', 'description' => 'desc2' . $decomposed_str1 ) );
		$num_updates++;

		$menu1_id = wp_create_nav_menu( 'menu1-name' . $decomposed_str1 );
		$this->assertTrue( is_numeric( $menu1_id ) );
		$this->assertTrue( $menu1_id > 0 );
		$num_updates++;
		$item_data = array(
			'menu-item-title' => 'item1-title' . $decomposed_str1,
			'menu-item-url' => 'item1-url' . $decomposed_str1,
		);
		$menu_item1_id = wp_update_nav_menu_item( $menu1_id, 0, $item_data );
		$this->assertTrue( is_numeric( $menu_item1_id ) );
		$this->assertTrue( $menu_item1_id > 0 );
		$num_updates++;

		add_option( 'option1', 'val1' . $decomposed_str1 );
		add_option( 'option2', 'val2' );
		global $wpdb;
		$option_id1 = intval( $wpdb->get_var( "SELECT option_id FROM {$wpdb->options} WHERE option_name IN ('option1') ORDER BY option_id ASC" ) );
		$num_updates++;
		add_option( 'option3', array( 'key1' => 'val3' . $decomposed_str1 ) );
		$option_id3 = intval( $wpdb->get_var( "SELECT option_id FROM {$wpdb->options} WHERE option_name IN ('option3') ORDER BY option_id ASC" ) );
		$num_updates++;
		add_option( 'option4', array( 'key1' => array( 'key2' => 'val4' . $decomposed_str1 ) ) );
		$option_id4 = intval( $wpdb->get_var( "SELECT option_id FROM {$wpdb->options} WHERE option_name IN ('option4') ORDER BY option_id ASC" ) );
		$num_updates++;

		add_site_option( 'option1', 'val1' . $decomposed_str1 );
		add_site_option( 'option2', 'val2' );
		$setting_id1 = intval( $wpdb->get_var( "SELECT meta_id FROM {$wpdb->sitemeta} WHERE meta_key IN ('option1','option4') ORDER BY meta_id ASC" ) );
		$num_updates++;

		$linkdata1 = array(
			'link_url' => 'http://example.org/address',
			'link_name' => 'name1' . $decomposed_str1,
			'link_image' => 'http://example.org/image' . $decomposed_str1 . '.jpg',
			'link_description' => 'desc' . $decomposed_str1,
			'link_notes' => 'notes' . $decomposed_str1,
			'link_rss' => 'http://example.org/rss',
		);

		$link1_id = wp_insert_link( $linkdata1 );
		$this->assertTrue( is_numeric( $link1_id ) );

		$editor_user_id = $this->factory->user->create( array( 'role' => 'editor' ) );

		$out = wp_set_current_user( $editor_user_id ); // Need different user for lock.
		$this->assertTrue( $out instanceOf WP_User );
		$this->assertNotEquals( 1, $out->ID );
		$this->assertSame( wp_get_current_user()->ID, $out->ID );

		$lock = wp_set_post_lock( $page1->ID );
		$this->assertTrue( is_array( $lock ) );
		$this->assertSame( wp_get_current_user()->ID, $lock[1] );
		$num_updates--;

		$out = wp_set_current_user( 1 );

		// Do normalize.

		$_REQUEST = array();
		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		$this->assertSame( 1, $out->ID );
		$this->assertSame( wp_get_current_user()->ID, $out->ID );

		global $unfc_normalize;

		do_action( 'init' );

		do_action( 'admin_menu' );

		$hook_suffix = 'admin_page_' . UNFC_DB_CHECK_MENU_SLUG;

		$this->assertSame( $hook_suffix, $unfc_normalize->db_check_hook_suffix );

		$_REQUEST = array();
		$_REQUEST['unfc_db_check_normalize_all'] = 'unfc_db_check_normalize_all';
		$_REQUEST['_wpnonce_normalize_all'] = wp_create_nonce( UNFC_DB_CHECK_MENU_SLUG . '-normalize_all' );
		$this->assertTrue( 1 === wp_verify_nonce( $_REQUEST['_wpnonce_normalize_all'], UNFC_DB_CHECK_MENU_SLUG . '-normalize_all' ) );

		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0]['args'];
		$this->assertSame( 2, count( $args ) );
		$this->assertTrue( false !== stripos( $args[0][1], (string) $num_updates ) );
		$this->assertTrue( false !== stripos( $args[1][1], '1' ) ); // Locked.

		self::clear_func_args();

		add_filter( 'unfc_batch_limit', array( $this, 'unfc_batch_limit_filter' ) );
		add_filter( 'pre_option_link_manager_enabled', '__return_true' );

		update_post_meta( $page1->ID, '_edit_lock', 0, implode( ':', $lock ) );

		$_REQUEST = array();
		$_REQUEST['unfc_db_check_normalize_all'] = 'unfc_db_check_normalize_all';
		$_REQUEST['_wpnonce_normalize_all'] = wp_create_nonce( UNFC_DB_CHECK_MENU_SLUG . '-normalize_all' );
		$this->assertTrue( 1 === wp_verify_nonce( $_REQUEST['_wpnonce_normalize_all'], UNFC_DB_CHECK_MENU_SLUG . '-normalize_all' ) );

		$transient_key = 'unfc_db_check_items' . $_REQUEST['_wpnonce_normalize_all'];
		set_transient( $transient_key, array( 'num_items' => 0, 'items' => array() ), intval( wp_nonce_tick() ) );

		$_REQUEST['unfc_trans'] = $transient_key;

		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0]['args'];
		$this->assertSame( 1, count( $args ) );
		$this->assertTrue( false !== stripos( $args[0][1], '2' ) );

		remove_filter( 'unfc_batch_limit', array( $this, 'unfc_batch_limit_filter' ) );
		remove_filter( 'pre_option_link_manager_enabled', '__return_true', 10 );

		self::clear_func_args();

		$_REQUEST = array();
		$_REQUEST['unfc_db_check_normalize_all'] = 'unfc_db_check_normalize_all';
		$_REQUEST['_wpnonce_normalize_all'] = wp_create_nonce( UNFC_DB_CHECK_MENU_SLUG . '-normalize_all' );
		$this->assertTrue( 1 === wp_verify_nonce( $_REQUEST['_wpnonce_normalize_all'], UNFC_DB_CHECK_MENU_SLUG . '-normalize_all' ) );

		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		$_REQUEST['unfc_type'] = 'post:asdf';

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0]['args'];
		$this->assertSame( 1, count( $args ) );
		$this->assertTrue( false !== stripos( $args[0][1], 'nothing' ) );

		global $wpdb;
		$ready = $wpdb->ready;
		$last_result = $wpdb->last_result;
		$wpdb->ready = false;
		$wpdb->last_result = null;

		self::clear_func_args();

		$_REQUEST = array();
		$_REQUEST['unfc_db_check_normalize_all'] = 'unfc_db_check_normalize_all';
		$_REQUEST['_wpnonce_normalize_all'] = wp_create_nonce( UNFC_DB_CHECK_MENU_SLUG . '-normalize_all' );
		$this->assertTrue( 1 === wp_verify_nonce( $_REQUEST['_wpnonce_normalize_all'], UNFC_DB_CHECK_MENU_SLUG . '-normalize_all' ) );

		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		$_REQUEST['unfc_type'] = 'post';

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertSame( 'error', $die['args'][0][0] );
		$this->assertSame( $unfc_normalize->db_check_error_msg( UNFC_DB_CHECK_DB_ERROR ), $die['args'][0][1] );

		$wpdb->ready = $ready;
		$wpdb->last_result = $last_result;
	}

    /**
     */
	function test_db_check_slugs() {
		$this->assertTrue( is_admin() );

		global $unfc_normalize;

		$_REQUEST = array();
		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		$out = wp_set_current_user( 1 ); // Need manage_options cap to add load-XXX

		do_action( 'init' );

		do_action( 'admin_menu' );

		$hook_suffix = 'admin_page_' . UNFC_DB_CHECK_MENU_SLUG;

		$this->assertSame( $hook_suffix, $unfc_normalize->db_check_hook_suffix );

		// Permission errors.

		$_REQUEST = array();
		$_REQUEST['unfc_db_check_slugs'] = 'unfc_db_check_slugs';

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0];
		$this->assertSame( 1, preg_match( '/failure|error|wrong/i', $args['title'] ) ); // Cater for various versions of message.

		self::clear_func_args();

		// Nothing.

		$_REQUEST = array();
		$_REQUEST['unfc_db_check_slugs'] = 'unfc_db_check_slugs';
		$_REQUEST['_wpnonce_slugs'] = wp_create_nonce( UNFC_DB_CHECK_MENU_SLUG . '-slugs' );
		$this->assertTrue( 1 === wp_verify_nonce( $_REQUEST['_wpnonce_slugs'], UNFC_DB_CHECK_MENU_SLUG . '-slugs' ) );

		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0]['args'];
		$this->assertSame( 0, $args['num_slugs'] );

		self::clear_func_args();

		// Items.

		$decomposed_str1 = "o\xcc\x88"; // o umlaut.

		$num_slugs = 0;

		$title1 = 'post1-title' . $decomposed_str1;
		$content1 = 'post1-content';
		$post_name1 = strtolower( rawurlencode( $title1 ) );

		$post1 = $this->factory->post->create_and_get( array( 'post_title' => $title1, 'post_content' => $content1, 'post_type' => 'post', 'post_name' => $post_name1 ) );
		$this->assertTrue( is_object( $post1 ) );
		$this->assertTrue( is_int( $post1->ID ) );
		$this->assertSame( $post_name1, $post1->post_name ); 
		$num_slugs++;

		$title2 = 'post2-title';
		$content2 = 'post2-content' . $decomposed_str1;
		$post_name2 = "post2-name-%ce";

		$post2 = $this->factory->post->create_and_get( array( 'post_title' => $title2, 'post_content' => $content2, 'post_type' => 'post', 'post_name' => $post_name2 ) );
		$this->assertTrue( is_object( $post2 ) );
		$this->assertSame( $post_name2, $post2->post_name ); 

		$title3 = 'post3-title' . str_repeat( "\xc2\x80", UNFC_DB_CHECK_TITLE_MAX_LEN );
		$content3 = 'post3-content';
		$post_name3 = "post2-name-u%cc%88";

		$post3 = $this->factory->post->create_and_get( array( 'post_title' => $title3, 'post_content' => $content3, 'post_type' => 'post', 'post_name' => $post_name3 ) );
		$this->assertTrue( is_object( $post3 ) );
		$this->assertSame( $post_name3, $post3->post_name ); 
		$num_slugs++;

		$user_nicename1 = "nicenice1-o%cc%88";
		$user1_id = $this->factory->user->create( array( 'user_login' => 'user1_login', 'display_name' => 'display1', 'user_nicename' => $user_nicename1 ) );
		$this->assertTrue( is_int( $user1_id ) );
		// Nicenames sanitized so fake it.
		global $wpdb;
		$wpdb->update( $wpdb->users, array( 'user_nicename' => $user_nicename1 ), array( 'ID' => $user1_id ) );
		wp_cache_delete( $user1_id, 'users' ); // Remove from cache.
		$user1 = get_userdata( $user1_id );
		$this->assertSame( $user_nicename1, $user1->user_nicename );
		$num_slugs++;

		$user_nicename2 = "nicenice2-%cc%88";
		$user2_id = $this->factory->user->create( array( 'user_login' => 'user2_login', 'display_name' => 'display2', 'user_nicename' => $user_nicename2 ) );
		$this->assertTrue( is_int( $user2_id ) );

		$term_slug1 = "slug1-o%cc%88";
		$term1_id = $this->factory->term->create( array( 'name' => 'term1', 'taxonomy' => 'category', 'description' => 'desc1', 'slug' => $term_slug1 ) );
		$num_slugs++;

		$term2_id = $this->factory->term->create( array( 'name' => 'term2', 'taxonomy' => 'post_tag', 'description' => 'desc2' ) );

		$term_slug3 = "slug3-%f4";
		$term3_id = $this->factory->term->create( array( 'name' => 'term3', 'taxonomy' => 'post_tag', 'description' => 'desc3', 'slug' => $term_slug3 ) );

		$_REQUEST = array();
		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		$out = wp_set_current_user( 1 ); // Need manage_options cap to add load-XXX and normalize.
		$this->assertSame( 1, $out->ID );
		$this->assertSame( wp_get_current_user()->ID, $out->ID );

		global $unfc_normalize;

		do_action( 'init' );

		do_action( 'admin_menu' );

		$hook_suffix = 'admin_page_' . UNFC_DB_CHECK_MENU_SLUG;

		$this->assertSame( $hook_suffix, $unfc_normalize->db_check_hook_suffix );

		$_REQUEST = array();
		$_REQUEST['unfc_db_check_slugs'] = 'unfc_db_check_slugs';
		$_REQUEST['_wpnonce_slugs'] = wp_create_nonce( UNFC_DB_CHECK_MENU_SLUG . '-slugs' );
		$this->assertTrue( 1 === wp_verify_nonce( $_REQUEST['_wpnonce_slugs'], UNFC_DB_CHECK_MENU_SLUG . '-slugs' ) );

		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0]['args'];
		$this->assertSame( $num_slugs, $args['num_slugs'] );
		$this->assertSame( array( $post1->ID, $post3->ID, $user1_id, $term1_id, ), array_map( 'intval', unfc_list_pluck( $args['slugs'], 'id' ) ) );

		$transient_key = 'unfc_db_check_slugs' . $_REQUEST['_wpnonce_slugs'];
		$_REQUEST['unfc_trans'] = $transient_key;
		unset( $_REQUEST['unfc_db_check_slugs'] );

		self::clear_func_args();

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 0, count( self::$func_args['wp_die'] ) );

		ob_start();
		do_action( $hook_suffix );
		do_action( 'admin_print_footer_scripts' );
		$out = ob_get_clean();

		$this->assertTrue( false !== stripos( $out, $title1 ) );
		$this->assertTrue( false === stripos( $out, $title2 ) );
		$this->assertTrue( false !== stripos( $out, mb_substr( $title3, 0, UNFC_DB_CHECK_TITLE_MAX_LEN, 'UTF-8' ) . '...' ) );
		$this->assertTrue( false !== stripos( $out, 'user1_login' ) );
		$this->assertTrue( false !== stripos( $out, 'term1' ) );
		$this->assertTrue( false !== stripos( $out, UNFC_DB_CHECK_SLUGS_LIST_SEL ) );

		self::clear_func_args();

		$_REQUEST['unfc_type'] = 'post';
		$_REQUEST['unfc_db_check_slugs'] = 'unfc_db_check_slugs';
		$_REQUEST['orderby'] = 'slug';

		add_filter( 'unfc_batch_limit', array( $this, 'unfc_batch_limit_filter' ) );
		add_filter( 'unfc_list_limit', array( $this, 'unfc_list_limit_filter' ) );

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0]['args'];
		$this->assertSame( 2, $args['num_slugs'] );
		$this->assertSame( array( $post1->ID ), array_map( 'intval', unfc_list_pluck( $args['slugs'], 'id' ) ) );

		$unfc_normalize->db_check_num_slugs = $args['num_slugs'];
		$unfc_normalize->db_check_slugs = $args['slugs'];

		ob_start();
		do_action( $hook_suffix );
		$out = ob_get_clean();

		$this->assertTrue( false !== stripos( $out, $title1 ) );
		$this->assertTrue( false === stripos( $out, $title2 ) );

		remove_filter( 'unfc_batch_limit', array( $this, 'unfc_batch_limit_filter' ) );
		remove_filter( 'unfc_list_limit', array( $this, 'unfc_list_limit_filter' ) );

		global $wpdb;
		$ready = $wpdb->ready;
		$last_result = $wpdb->last_result;
		$wpdb->ready = false;
		$wpdb->last_result = null;

		self::clear_func_args();

		$_REQUEST = array();
		$_REQUEST['unfc_db_check_slugs'] = 'unfc_db_check_slugs';
		$_REQUEST['_wpnonce_slugs'] = wp_create_nonce( UNFC_DB_CHECK_MENU_SLUG . '-slugs' );
		$this->assertTrue( 1 === wp_verify_nonce( $_REQUEST['_wpnonce_slugs'], UNFC_DB_CHECK_MENU_SLUG . '-slugs' ) );

		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertSame( 'error', $die['args'][0][0] );
		$this->assertSame( $unfc_normalize->db_check_error_msg( UNFC_DB_CHECK_DB_ERROR ), $die['args'][0][1] );

		$wpdb->ready = $ready;
		$wpdb->last_result = $last_result;
	}

    /**
     */
	function test_db_check_normalize_slugs() {
		$this->assertTrue( is_admin() );

		global $unfc_normalize;

		$_REQUEST = array();
		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		$out = wp_set_current_user( 1 ); // Need manage_options cap to add load-XXX

		do_action( 'init' );

		do_action( 'admin_menu' );

		$hook_suffix = 'admin_page_' . UNFC_DB_CHECK_MENU_SLUG;

		$this->assertSame( $hook_suffix, $unfc_normalize->db_check_hook_suffix );

		// Permission errors.

		$_REQUEST = array();
		$_REQUEST['action'] = 'unfc_db_check_normalize_slugs';

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0];
		$this->assertSame( 1, preg_match( '/failure|error|wrong/i', $args['title'] ) ); // Cater for various versions of message.

		self::clear_func_args();

		$decomposed_str1 = "o\xcc\x88"; // o umlaut.

		$num_slugs = 0;
		$items = array();

		$title1 = 'post1-title' . $decomposed_str1;
		$content1 = 'post1-content';
		$post_name1 = strtolower( rawurlencode( $title1 ) );

		$post1 = $this->factory->post->create_and_get( array( 'post_title' => $title1, 'post_content' => $content1, 'post_type' => 'post', 'post_name' => $post_name1 ) );
		$this->assertTrue( is_object( $post1 ) );
		$this->assertTrue( is_int( $post1->ID ) );
		$this->assertSame( $post_name1, $post1->post_name ); 
		$num_slugs++;
		$items[] = array( 'id' => $post1->ID, 'title' => $title1, 'type' => 'post', 'subtype' => 'post', 'slug' => $post_name1, 'idx' => $post1_idx = count( $items ) );

		$title2 = 'post2-title';
		$content2 = 'post2-content' . $decomposed_str1;
		$post_name2 = "post2-name-%c3%bc"; // Not decomposed.

		$post2 = $this->factory->post->create_and_get( array( 'post_title' => $title2, 'post_content' => $content2, 'post_type' => 'post', 'post_name' => $post_name2 ) );
		$this->assertTrue( is_object( $post2 ) );
		$this->assertSame( $post_name2, $post2->post_name ); 
		$items[] = array( 'id' => $post2->ID, 'title' => $title2, 'type' => 'post', 'subtype' => 'post', 'slug' => $post_name2, 'idx' => $post2_idx = count( $items ) );

		$title3 = 'post3-title';
		$content3 = 'post3-content';
		$post_name3 = "post3-name-u%cc%88";

		$post3 = $this->factory->post->create_and_get( array( 'post_title' => $title3, 'post_content' => $content3, 'post_type' => 'post', 'post_name' => $post_name3 ) );
		$this->assertTrue( is_object( $post3 ) );
		$this->assertSame( $post_name3, $post3->post_name ); 
		$num_slugs++;
		$items[] = array( 'id' => $post3->ID, 'title' => $title3, 'type' => 'post', 'subtype' => 'post', 'slug' => $post_name3, 'idx' => $post3_idx = count( $items ) );

		$page_title1 = 'page1-title' . $decomposed_str1;
		$page_content1 = 'page1-content';
		$page_name1 = "page1-name-u%cc%88";

		$page1 = $this->factory->post->create_and_get( array( 'post_title' => $page_title1, 'post_content' => $page_content1, 'post_type' => 'page', 'post_name' => $page_name1 ) );
		$this->assertTrue( is_object( $page1 ) );
		$this->assertSame( $page_name1, $page1->post_name ); 
		$num_slugs++;
		$items[] = array( 'id' => $page1->ID, 'title' => $page_title1, 'type' => 'post', 'subtype' => 'page', 'slug' => $page_name1, 'idx' => $page1_idx = count( $items ) );

		// Lock page1.
		$editor_user_id = $this->factory->user->create( array( 'role' => 'editor' ) );

		$out = wp_set_current_user( $editor_user_id ); // Need different user for lock.
		$this->assertTrue( $out instanceOf WP_User );
		$this->assertNotEquals( 1, $out->ID );
		$this->assertSame( wp_get_current_user()->ID, $out->ID );

		$lock = wp_set_post_lock( $page1->ID );
		$this->assertTrue( is_array( $lock ) );
		$this->assertSame( wp_get_current_user()->ID, $lock[1] );

		$out = wp_set_current_user( 1 ); // Restore.

		// Users.
		$user_nicename1 = "nicenice1-o%cc%88";
		$user1_id = $this->factory->user->create( array( 'user_login' => 'user1_login', 'display_name' => 'display1', 'user_nicename' => $user_nicename1 ) );
		$this->assertTrue( is_int( $user1_id ) );
		// Nicenames sanitized so fake it.
		global $wpdb;
		$wpdb->update( $wpdb->users, array( 'user_nicename' => $user_nicename1 ), array( 'ID' => $user1_id ) );
		wp_cache_delete( $user1_id, 'users' ); // Remove from cache.
		$user1 = get_userdata( $user1_id );
		$this->assertSame( $user_nicename1, $user1->user_nicename );
		$num_slugs++;
		$items[] = array( 'id' => $user1_id, 'title' => 'user1_login', 'type' => 'user', 'subtype' => 'user', 'slug' => $user_nicename1, 'idx' => $user1_idx = count( $items ) );

		$user_nicename2 = "nicenice2-%cc%88";
		$user2_id = $this->factory->user->create( array( 'user_login' => 'user2_login', 'display_name' => 'display2', 'user_nicename' => $user_nicename2 ) );
		$this->assertTrue( is_int( $user2_id ) );

		// Terms.
		$term_slug1 = "slug1-o%cc%88";
		$term1_id = $this->factory->term->create( array( 'name' => 'term1', 'taxonomy' => 'category', 'description' => 'desc1', 'slug' => $term_slug1 ) );
		$num_slugs++;
		$items[] = array( 'id' => $term1_id, 'title' => 'term1', 'type' => 'term', 'subtype' => 'category', 'slug' => $term_slug1, 'idx' => $term1_idx = count( $items ) );

		$term2_id = $this->factory->term->create( array( 'name' => 'term2', 'taxonomy' => 'post_tag', 'description' => 'desc2' ) );

		$term_slug3 = "slug3-%f0%af%a0%87"; // CJK COMPATIBILITY IDEOGRAPH-2F807
		$term3_id = $this->factory->term->create( array( 'name' => 'term3', 'taxonomy' => 'post_tag', 'description' => 'desc3', 'slug' => $term_slug3 ) );
		$num_slugs++;
		$items[] = array( 'id' => $term3_id, 'title' => 'term3', 'type' => 'term', 'subtype' => 'post_tag', 'slug' => $term_slug3, 'idx' => $term3_idx = count( $items ) );

		$_REQUEST = array();
		$_REQUEST['action'] = 'unfc_db_check_normalize_slugs';
		$bulk_action = 'bulk-' . UNFC_DB_CHECK_MENU_SLUG;
		$_REQUEST['_wpnonce'] = wp_create_nonce( $bulk_action );
		$this->assertTrue( 1 === wp_verify_nonce( $_REQUEST['_wpnonce'], $bulk_action ) );
		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		$transient_key = 'unfc_db_check_slugs' . $_REQUEST['_wpnonce'];
		set_transient( $transient_key, array( 'num_slugs' => count( $items ), 'slugs' => $items ), intval( wp_nonce_tick() ) );
		$_REQUEST['unfc_trans'] = $transient_key;

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertSame( 'warning', $die['args'][0][0] );
		$this->assertSame( $unfc_normalize->db_check_error_msg( UNFC_DB_CHECK_SELECT_ERROR ), $die['args'][0][1] );

		self::clear_func_args();

		// Item set, succeed.
		$_REQUEST['item'] = array( "{$post1->ID}:post:$post1_idx" );

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertSame( count( $items ) - count( $_REQUEST['item'] ), $die['args']['num_slugs'] );
		$this->assertNotContains( $title1, unfc_list_pluck( $die['args']['slugs'], 'title' ) ); // Can't use id as could be same between types.
		$this->assertContains( $post_name1, (array) get_post_meta( $post1->ID, '_wp_old_slug' ) );

		ob_start();
		do_action( $hook_suffix );
		$out = ob_get_clean();

		$this->assertTrue( false === stripos( $out, $_REQUEST['item'][0] ) );

		self::clear_func_args();

		$_REQUEST['item'] = array( "{$page1->ID}:post:$page1_idx", "{$post3->ID}:post:$post3_idx", "{$term1_id}:term:$term1_idx", "{$term3_id}:term:$term3_idx", "{$user1_id}:user:$user1_idx" );

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertSame( count( $items ) - count( $_REQUEST['item'] ), $die['args']['num_slugs'] );

		self::clear_func_args();

		set_transient( $transient_key, array( 'num_slugs' => $die['args']['num_slugs'], 'slugs' => $die['args']['slugs'] ), intval( wp_nonce_tick() ) );

		$_REQUEST['item'] = array( "{$post3->ID}:post:$post3_idx", );

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertSame( 2, $die['args']['num_slugs'] );
		$this->assertSame( $unfc_normalize->db_check_error_msg( UNFC_DB_CHECK_SYNC_ERROR ), $die['args'][0][1] );
		$this->assertContains( $post_name3, (array) get_post_meta( $post3->ID, '_wp_old_slug' ) );

		self::clear_func_args();

		$_REQUEST['item'] = array( "{$post2->ID}:post:$post2_idx", );

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertSame( 1, $die['args']['num_slugs'] );
		$this->assertTrue( false !== stripos( $die['args'][0][1], 'nothing' ) );
		$this->assertSame( 'warning', $die['args'][1][0] );
		$this->assertTrue( false !== stripos( $die['args'][1][1], '1' ) );
		$this->assertNotContains( $post_name2, (array) get_post_meta( $post2->ID, '_wp_old_slug' ) ); // Slug was normalized (%c3%b2).

		self::clear_func_args();

		$_REQUEST['item'] = array( "2:asdfasdf:1", );

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertSame( $unfc_normalize->db_check_error_msg( UNFC_DB_CHECK_PARAM_ERROR ), $die['args'][0][1] );

		self::clear_func_args();

		$_REQUEST['item'] = array( ":asdfasdf:1", );

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertSame( $unfc_normalize->db_check_error_msg( UNFC_DB_CHECK_PARAM_ERROR ), $die['args'][0][1] );

		self::clear_func_args();

		$items = array( array( 'id' => 1234, 'title' => 'term3', 'type' => 'term', 'subtype' => 'post_tag', 'slug' => $term_slug3 ) );
		set_transient( $transient_key, array( 'num_slugs' => count( $items ), 'slugs' => $items ), intval( wp_nonce_tick() ) );

		$_REQUEST['item'] = array( "1234:term:0", );

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertSame( 0, $die['args']['num_slugs'] );

		self::clear_func_args();

		$_REQUEST['item'] = array( "1234:term:1", );

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertSame( $unfc_normalize->db_check_error_msg( UNFC_DB_CHECK_SYNC_ERROR ), $die['args'][0][1] );

		self::clear_func_args();

		unset( $_REQUEST['unfc_trans'] );

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertSame( $unfc_normalize->db_check_error_msg( UNFC_DB_CHECK_TRANS_ERROR ), $die['args'][0][1] );
	}

    /**
     */
	function test_db_check_screen_options() {
		$this->assertTrue( is_admin() );

		global $unfc_normalize;

		$_REQUEST = array();
		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		$out = wp_set_current_user( 1 ); // Need manage_options cap to add load-XXX

		do_action( 'init' );

		do_action( 'admin_menu' );

		$hook_suffix = 'admin_page_' . UNFC_DB_CHECK_MENU_SLUG;

		$this->assertSame( $hook_suffix, $unfc_normalize->db_check_hook_suffix );

		$out = wp_set_current_user( 1 );

		$_REQUEST['screen-options-apply'] = 'Apply';
		$_REQUEST['wp_screen_options'] = array( 'option' => UNFC_DB_CHECK_PER_PAGE, 'value' => '42' );
		$_REQUEST['screenoptionnonce'] = wp_create_nonce( 'screen-options-nonce' );
		$this->assertTrue( 1 === wp_verify_nonce( $_REQUEST['screenoptionnonce'], 'screen-options-nonce' ) );

		$transient_key = 'unfc_db_check_items' . $_REQUEST['screenoptionnonce'];
		set_transient( $transient_key, array( 'num_items' => 0, 'items' => array() ), intval( wp_nonce_tick() ) );
		$_REQUEST['unfc_trans'] = $transient_key;

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );

		$out = get_user_meta( 1, UNFC_DB_CHECK_PER_PAGE );
		$this->assertSame( array( '42' ), $out );

		self::clear_func_args();

		unset( $_REQUEST['unfc_trans'] );

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertSame( $unfc_normalize->db_check_error_msg( UNFC_DB_CHECK_TRANS_ERROR ), $die['args'][0][1] );
	}

    /**
     */
	function test_db_check_referer() {
		$this->assertTrue( is_admin() );

		global $unfc_normalize;

		$_REQUEST = array();
		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;

		$out = wp_set_current_user( 1 ); // Need manage_options cap to add load-XXX

		do_action( 'init' );

		do_action( 'admin_menu' );

		$hook_suffix = 'admin_page_' . UNFC_DB_CHECK_MENU_SLUG;

		$this->assertSame( $hook_suffix, $unfc_normalize->db_check_hook_suffix );

		$out = wp_set_current_user( 1 );

		$_REQUEST['_wp_http_referer'] = 'http://example.org/wp-admin/tools.php?page=' . UNFC_DB_CHECK_MENU_SLUG;
		$_REQUEST['action'] = $_REQUEST['action2'] = '-1';
		$bulk_action = 'bulk-' . UNFC_DB_CHECK_MENU_SLUG;
		$_REQUEST['_wpnonce'] = wp_create_nonce( $bulk_action );
		$this->assertTrue( 1 === wp_verify_nonce( $_REQUEST['_wpnonce'], $bulk_action ) );

		$transient_key = 'unfc_db_check_items' . $_REQUEST['_wpnonce'];
		set_transient( $transient_key, array( 'num_items' => 0, 'items' => array() ), intval( wp_nonce_tick() ) );
		$_REQUEST['unfc_trans'] = $transient_key;

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertSame( 'wp_redirect', $die['title'] );

		self::clear_func_args();

		$_REQUEST['action'] = $_REQUEST['action2'] = '';

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertSame( 'wp_redirect', $die['title'] );

		self::clear_func_args();

		$_REQUEST['action'] = $_REQUEST['action2'] = '-1';
		unset( $_REQUEST['unfc_trans'] );

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$die = self::$func_args['wp_die'][0];
		$this->assertSame( $unfc_normalize->db_check_error_msg( UNFC_DB_CHECK_TRANS_ERROR ), $die['args'][0][1] );

		self::clear_func_args();

		unset( $_REQUEST['_wp_http_referer'] );
		$_REQUEST['unfc_trans'] = $transient_key;

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( 0, count( self::$func_args['wp_die'] ) );

		self::clear_func_args();

		delete_transient( $transient_key );

		try {
			do_action( 'load-' . $hook_suffix );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( 0, count( self::$func_args['wp_die'] ) );
		$admin_notices = get_transient( 'unfc_admin_notices' );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( $unfc_normalize->db_check_error_msg( UNFC_DB_CHECK_TRANS_ERROR ), $admin_notices[0][1] );
	}

    /**
	 * @dataProvider data_percent_decode
     */
	function test_db_check_percent_decode( $encoded, $decoded ) {

		$out = UNFC_Normalize::percent_decode( $encoded );
		$this->assertSame( $decoded, $out );
		$out = UNFC_Normalize::percent_encode( $out );
		$this->assertSame( $encoded, $out );
	}

	function data_percent_decode() {
		return array(
			array( "slug", "slug" ),
			array( "slug-%7f", "slug-%7f" ),
			array( "slug-%cc%20", "slug-%cc%20" ),
			array( "slug-%cc%80", "slug-\xcc\x80" ),
			array( "slug-%c0%80", "slug-%c0%80" ),
			array( "slug-%7e%cc%80", "slug-%7e\xcc\x80" ),
			array( "slug-%7e%c0%80%00", "slug-%7e%c0%80%00" ),
			array( "slug-%7e%cc%80%00", "slug-%7e\xcc\x80%00" ),
			array( "slug-%e1%80%bf", "slug-\xe1\x80\xbf" ),
			array( "slug-%e1%80%bf%80", "slug-\xe1\x80\xbf%80" ),
			array( "slug-%f4%80%80%bf", "slug-\xf4\x80\x80\xbf" ),
			array( "sl%91ug-%f4%8f%bf%bf", "sl%91ug-\xf4\x8f\xbf\xbf" ),
			array( "sl%91ug-%f4%8f%bf%bf%80%df%80%ef%bf%bf%40", "sl%91ug-\xf4\x8f\xbf\xbf%80\xdf\x80\xef\xbf\xbf%40" ),
		);
	}
}
