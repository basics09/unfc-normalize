<?php
/**
 * Test post filters.
 *
 * @group unfc
 * @group unfc_post
 */
class Tests_UNFC_Post extends WP_UnitTestCase {

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
		$pagenow = 'post.php';
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
	function test_post() {
		$this->assertTrue( is_admin() ) ;

		do_action( 'init' );

		global $unfc_normalize;
		$this->assertArrayHasKey( 'post', $unfc_normalize->added_filters );

		$decomposed_str = "u\xCC\x88"; // u umlaut.

		$post = array(
			'post_status' => 'publish',
			'post_title' => 'Title' . $decomposed_str,
			'post_content' => 'Content' . $decomposed_str,
			'post_excerpt' => 'Excerpt' . $decomposed_str,
			'post_type' => 'post',
		);

		$id = wp_insert_post( $post );

		$this->assertTrue( is_numeric( $id ) );
		$this->assertTrue( $id > 0 );

		// Fetch the post and make sure it matches.
		$out = get_post( $id );

		$this->assertSame( UNFC_Normalizer::normalize( $post['post_title'] ), $out->post_title );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $post['post_title'] ), $out->post_title );
		$this->assertSame( UNFC_Normalizer::normalize( $post['post_content'] ), $out->post_content );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $post['post_content'] ), $out->post_content );
		$this->assertSame( UNFC_Normalizer::normalize( $post['post_excerpt'] ), $out->post_excerpt );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $post['post_excerpt'] ), $out->post_excerpt );

		$post['ID'] = $id;

		$id = wp_update_post( $post );

		$this->assertTrue( is_numeric( $id ) );
		$this->assertTrue( $id > 0 );

		// Fetch the post and make sure it matches.
		$out = get_post( $id );

		$this->assertSame( UNFC_Normalizer::normalize( $post['post_content'] ), $out->post_content );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $post['post_content'] ), $out->post_content );
		$this->assertSame( UNFC_Normalizer::normalize( $post['post_title'] ), $out->post_title );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $post['post_title'] ), $out->post_title );
	}

    /**
     */
	function test_meta() {
		$this->assertTrue( is_admin() ) ;

		wp_set_current_user( 1 ); // Need editor privileges.

		$decomposed_str = "o\xCC\x88"; // o umlaut.

		// Emulate "post-new.php".
		$post = get_default_post_to_edit( 'post', true ); // Auto-draft.
		$this->assertInstanceOf( 'WP_Post', $post );

		$id = $post->ID;
		$this->assertTrue( is_numeric( $id ) );
		$this->assertTrue( $id > 0 );

		// Emulate POST to "post.php".

		$tag1 = 'Tag1' . $decomposed_str;
		$cat1 = wp_insert_term( 'cat1', 'category' );

		$_POST = array(
			'post_ID' => $id,
			'post_status' => 'publish',
			'post_title' => 'Title' . $decomposed_str,
			'post_type' => 'post',
			'meta_input' => array(
				'meta_input_key' => 'meta_input_value' . $decomposed_str,
			),
			'metakeyinput' => 'metakeyinput_key' . $decomposed_str,
			'metavalue' => 'metakeyinput_value' . $decomposed_str,
			'tax_input' => array(
				'post_tag' => $tag1,
				'category' => $cat1['term_id'], // This is just to see if can trigger ! is_array( $terms ) part of pre_post_tax_input() in WP >= 4.2
			),
		);

		global $wp_version;
		if ( version_compare( $wp_version, '5.1', '>=' ) ) {
			// Due to bug #46338 https://core.trac.wordpress.org/ticket/46338 make arrayish, defeating purpose.
			$_POST['tax_input']['category'] = array( $_POST['tax_input']['category'] );
		}

		// meta_input arg introduced WP 4.4 but use in edit_post() security issue so only functions in specific windows.
		$have_meta_input_through_edit = false;
		if ( ( version_compare( $wp_version, '4.4', '>=' ) && version_compare( $wp_version, '4.4.17', '<' ) )
				|| ( version_compare( $wp_version, '4.5', '>=' ) && version_compare( $wp_version, '4.5.16', '<' ) )
				|| ( version_compare( $wp_version, '4.6', '>=' ) && version_compare( $wp_version, '4.6.13', '<' ) )
				|| ( version_compare( $wp_version, '4.7', '>=' ) && version_compare( $wp_version, '4.7.12', '<' ) )
				|| ( version_compare( $wp_version, '4.8', '>=' ) && version_compare( $wp_version, '4.8.8', '<' ) )
				|| ( version_compare( $wp_version, '4.9', '>=' ) && version_compare( $wp_version, '4.9.9', '<' ) )
				|| version_compare( $wp_version, '5.0', '=' ) ) {
			$have_meta_input_through_edit = true;
		}

		do_action( 'init' );

		global $unfc_normalize;
		$this->assertArrayHasKey( 'post', $unfc_normalize->added_filters );

		// Add (update auto-draft).
		$out = edit_post();
		$this->assertSame( $id, $out );

		// Fetch the post and make sure it matches.
		$out = get_post( $id );

		$this->assertSame( UNFC_Normalizer::normalize( $_POST['post_title'] ), $out->post_title );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $_POST['post_title'] ), $out->post_title );

		if ( $have_meta_input_through_edit ) {
			$out = get_post_meta( $id, 'meta_input_key', true );

			$this->assertSame( UNFC_Normalizer::normalize( $_POST['meta_input']['meta_input_key'] ), $out );
			if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $_POST['meta_input']['meta_input_key'] ), $out );
		}

		$out = get_post_meta( $id, 'metakeyinput_key' . $decomposed_str, true );

		$this->assertSame( UNFC_Normalizer::normalize( $_POST['metavalue'] ), $out );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $_POST['metavalue'] ), $out );

		$out = get_term_by( 'name', UNFC_Normalizer::normalize( $tag1 ), 'post_tag' );
		if ( class_exists( 'WP_Term' ) ) {
			$this->assertInstanceOf( 'WP_Term', $out );
		} else {
			$this->assertTrue( is_object( $out ) );
		}

		$out = wp_get_post_tags( $id );
		$this->assertTrue( is_array( $out ) );
		$this->assertTrue( is_object( $out[0] ) );
		$this->assertSame( UNFC_Normalizer::normalize( $tag1 ), $out[0]->name );

		// Update.

		global $wpdb;

		if ( $have_meta_input_through_edit ) {
			$meta_input_key_id = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $id, 'meta_input_key' ) );
			$this->assertTrue( is_numeric( $meta_input_key_id ) );
			$this->assertTrue( $meta_input_key_id > 0 );
		} else {
			$meta_input_key_id = 1; // Dummy.
		}

		$metakeyinput_id = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $id, 'metakeyinput_key' . $decomposed_str ) );
		$this->assertTrue( is_numeric( $metakeyinput_id ) );
		$this->assertTrue( $metakeyinput_id > 0 );

		// Emulate POST to "post.php".

		$_POST = array(
			'post_ID' => $id,
			'post_status' => 'publish',
			'post_title' => 'Title' . $decomposed_str,
			'post_type' => 'post',
			'meta' => array(
				$meta_input_key_id => array( 'key' => 'meta_input_key', 'value' => 'meta_input_value updated' . $decomposed_str ),
				$metakeyinput_id => array( 'key' => 'metakeyinput_key' . $decomposed_str, 'value' => 'metakeyinput_value updated' . $decomposed_str ),
			),
		);

		do_action( 'init' );

		$out = edit_post();
		$this->assertSame( $id, $out );

		if ( $have_meta_input_through_edit ) {
			$out = get_post_meta( $id, 'meta_input_key', true );

			$this->assertSame( UNFC_Normalizer::normalize( $_POST['meta'][$meta_input_key_id]['value'] ), $out );
			if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $_POST['meta'][$meta_input_key_id]['value'] ), $out );
		}

		$out = get_post_meta( $id, 'metakeyinput_key' . $decomposed_str, true );

		$this->assertSame( UNFC_Normalizer::normalize( $_POST['meta'][$metakeyinput_id]['value'] ), $out );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $_POST['meta'][$metakeyinput_id]['value'] ), $out );
	}

    /**
     */
	function test_attachment() {
		$this->assertTrue( is_admin() ) ;

		wp_set_current_user( 1 ); // Need editor privileges.

		$decomposed_str = "u\xCC\x88"; // u umlaut.

		// Emulate "post-new.php".
		$post = get_default_post_to_edit( 'attachment', true ); // Auto-draft.
		$this->assertInstanceOf( 'WP_Post', $post );

		$id = $post->ID;
		$this->assertTrue( is_numeric( $id ) );
		$this->assertTrue( $id > 0 );

		// Emulate POST to "post.php".

		$_POST = array(
			'post_ID' => $id,
			'post_status' => 'publish',
			'post_title' => 'Title' . $decomposed_str,
			'post_content' => 'Content' . $decomposed_str,
			'post_excerpt' => 'Excerpt' . $decomposed_str,
			'post_type' => 'attachment',
			'_wp_attachment_image_alt' => 'Alt' . $decomposed_str,
		);

		do_action( 'init' );

		global $unfc_normalize;
		$this->assertArrayHasKey( 'post', $unfc_normalize->added_filters );

		// Add (update auto-draft).
		$out = edit_post();
		$this->assertSame( $id, $out );

		$out = get_post_meta( $id, '_wp_attachment_image_alt', true );

		$this->assertSame( UNFC_Normalizer::normalize( $_POST['_wp_attachment_image_alt'] ), $out );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $_POST['_wp_attachment_image_alt'] ), $out );
	}

    /**
     */
	function test_media() {
		$this->assertTrue( is_admin() ) ;

		wp_set_current_user( 1 ); // Need editor privileges.

		$decomposed_str = "u\xCC\x88"; // u umlaut.

		$post = array(
			'post_status' => 'publish',
			'post_title' => 'Title' . $decomposed_str,
			'post_content' => 'Content' . $decomposed_str,
			'post_excerpt' => 'Excerpt' . $decomposed_str,
			'post_type' => 'attachment',
			'post_mime_type' => 'audio/mpeg',
		);

		$id = wp_insert_attachment( $post );

		$this->assertTrue( is_numeric( $id ) );
		$this->assertTrue( $id > 0 );

		$out = get_post( $id );
		$this->assertInstanceOf( 'WP_Post', $out );
		$this->assertSame( $id, $out->ID );

		// Emulate POST to "post.php".

		$_POST = array(
			'post_ID' => $id,
			'post_status' => 'publish',
			'post_title' => 'Title' . $decomposed_str,
			'post_content' => 'Content' . $decomposed_str,
			'post_excerpt' => 'Excerpt' . $decomposed_str,
			'post_type' => 'attachment',
			'post_mime_type' => 'audio/mpeg',
		);

		if ( function_exists( 'wp_get_attachment_id3_keys' ) ) {
			$id3_keys = wp_get_attachment_id3_keys( null, 'edit' );
			foreach ( $id3_keys as $key => $label ) {
				$_POST[ 'id3_' . $key ] = $label . $decomposed_str;
			}

			do_action( 'init' );

			global $unfc_normalize;
			$this->assertArrayHasKey( 'post', $unfc_normalize->added_filters );

			// Update.
			$out = edit_post();
			$this->assertSame( $id, $out );

			$out = get_post_meta( $id, '_wp_attachment_metadata', true );
			$this->assertInternalType( 'array', $out );

			foreach ( $id3_keys as $key => $label ) {
				$this->assertSame( UNFC_Normalizer::normalize( $_POST[ 'id3_' . $key ] ), $out[ $key ] );
				if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $_POST[ 'id3_' . $key ] ), $out[ $key ] );
			}
		}
	}

    /**
     */
	function test_custom() {
		$this->assertTrue( is_admin() ) ;

		wp_set_current_user( 1 ); // Need editor privileges.

		$decomposed_str = "o\xCC\x88"; // o umlaut.

		$labels = array(
			'name'					=> _x( 'Name1', 'Post type general name', 'wpfm' ),
			'singular_name'			=> _x( 'Name1', 'Post type singular name', 'wpfm' ),
			'menu_name'				=> _x( 'Name1s', 'Post type menu name', 'wpfm' ),
			'add_new'				=> _x( 'Add New', 'Add new name1', 'wpfm' ),
			'add_new_item'			=> __( 'Add Name1', 'wpfm' ),
			'edit_item'				=> __( 'Edit Name1', 'wpfm' ),
			'new_item'				=> __( 'New Name1', 'wpfm' ),
			'view_item'				=> __( 'View Name1', 'wpfm' ),
			'search_items'			=> __( 'Search Name1s', 'wpfm' ),
			'not_found'				=> __( 'No name1s found', 'wpfm' ),
			'not_found_in_trash'	=> __( 'No name1s found in Trash', 'wpfm' ),
		);
		$def = array(
			'labels'				=> $labels,
			'description'			=> __( 'Post type for Name1s', 'wpfm' ),
			'activate'				=> true,
			'public'				=> true,
			'show_ui'				=> true,
			'show_in_menu'			=> true,
			'can_export'			=> true,
			'show_in_nav_menus'		=> true,
			'menu_position'			=> null,
			'query_var'				=> true,
			'supports'				=> array( 'title', 'editor', 'excerpt', 'thumbnail', 'comments' ),
			'rewrite'				=> array( 'slug' => 'name1' ),
			'taxonomies'			=> array( 'unfc_name1_tax' ),
			'has_archive'			=> false,
			'menu_icon'				=> '',
			'capability_type'		=> 'name1',
			'map_meta_cap'			=> true,
		);
		$ret = register_post_type( 'unfc_name1', $def );
		$this->assertFalse( is_wp_error( $ret ) );

		$labels = array(
			'name' => _x( 'Name1Taxs', 'Taxonomy general name', 'wpfm' ), 
			'singular_name' => _x( 'Name1Tax', 'Taxonomy singular name', 'wpfm' ), 
			'all_items' => __( 'All Name1Taxs', 'wpfm' ),
			'edit_item' => __( 'Edit Name1Tax', 'wpfm' ),
			'view_item' => __( 'View Name1Tax', 'wpfm' ),
			'update_item' => __( 'Update Name1Tax', 'wpfm' ),
			'add_new_item' => __( 'Add New Name1Tax', 'wpfm' ),
			'new_item_name' => __( 'New Name1Tax', 'wpfm' ),
			'parent_item' => __( 'Parent Name1Tax', 'wpfm' ),
			'parent_item_colon' => __( 'Parent Name1Tax:', 'wpfm' ),
			'search_items' => __( 'Search Name1Taxs', 'wpfm' ),
		);

		$ret = register_taxonomy( 'unfc_name1_tax', array( 'unfc_name1' ),
			array(
				'labels' => $labels,
				'public' => true,
				'show_tagcloud' => false,
				'show_admin_column' => true,
				'hierarchical' => true, 
				'query_var' => true,
				'rewrite' => array( 'slug' => 'name1_tax' ),
				'capabilities' => array(
					'manage_terms' => 'edit_name1s',
					'edit_terms' => 'edit_name1s',
					'delete_terms' => 'edit_name1s',
					'assign_terms' => 'edit_name1s'
				),
			)
		);
		$this->assertFalse( is_wp_error( $ret ) );

		$post = array(
			'post_status' => 'publish',
			'post_title' => 'Title' . $decomposed_str,
			'post_content' => 'Content' . $decomposed_str,
			'post_excerpt' => 'Excerpt' . $decomposed_str,
			'post_type' => 'unfc_name1',
		);

		do_action( 'init' );

		global $unfc_normalize;
		$this->assertArrayHasKey( 'post', $unfc_normalize->added_filters );

		$id = wp_insert_post( $post );

		$this->assertTrue( is_numeric( $id ) );
		$this->assertTrue( $id > 0 );

		// Fetch the post and make sure it matches.
		$out = get_post( $id );

		$this->assertSame( UNFC_Normalizer::normalize( $post['post_title'] ), $out->post_title );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $post['post_title'] ), $out->post_title );
		$this->assertSame( UNFC_Normalizer::normalize( $post['post_content'] ), $out->post_content );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $post['post_content'] ), $out->post_content );
		$this->assertSame( UNFC_Normalizer::normalize( $post['post_excerpt'] ), $out->post_excerpt );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $post['post_excerpt'] ), $out->post_excerpt );

		// Do custom category while we're here.
		$name = 'term name ' . $decomposed_str;
		$args = array(
			'description' => 'Term description ' . $decomposed_str,
		);
		$cat = 'unfc_name1_tax';

		global $pagenow;
		$pagenow = 'edit-tags.php';
		set_current_screen( $pagenow );

		do_action( 'init' );

		$this->assertArrayHasKey( 'term', $unfc_normalize->added_filters );

		$result = wp_insert_term( $name, $cat, $args );

		$this->assertTrue( is_array( $result ) );
		$this->assertTrue( is_numeric( $result['term_id'] ) );

		$id = $result['term_id'];
		$this->assertTrue( $id > 0 );

		// Fetch the term and make sure it matches.
		$out = get_term( $id, $cat );
		if ( class_exists( 'WP_Term' ) ) {
			$this->assertInstanceOf( 'WP_Term', $out );
		} else {
			$this->assertTrue( is_object( $out ) );
		}

		$this->assertSame( UNFC_Normalizer::normalize( $name ), $out->name );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $name ), $out->name );
		$this->assertSame( UNFC_Normalizer::normalize( $args['description'] ), $out->description );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $args['description'] ), $out->description );

		$pagenow = 'post.php';
		set_current_screen( $pagenow );
	}
}
