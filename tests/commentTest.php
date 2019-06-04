<?php

global $wp_version;
error_log( "\nWordPress $wp_version\n" );

/**
 * Test comment filters.
 *
 * @group unfc
 * @group unfc_comment
 */
class TestUNFC_Comment extends WP_UnitTestCase {

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
		$pagenow = 'comment.php';
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
	function test_comment_filters() {
		$this->assertTrue( is_admin() ) ;

		do_action( 'init' );

		global $unfc_normalize;
		$this->assertArrayHasKey( 'comment', $unfc_normalize->added_filters );

		$decomposed_str = "o\xcc\x88"; // o umlaut.

		$post = $this->factory->post->create_and_get( array( 'post_title' => 'some-post', 'post_type' => 'post' ) );
		$this->assertInstanceOf( 'WP_Post', $post );
		$comment_id = $this->factory->comment->create( array( 'comment_post_ID' => $post->post_id ) );

		$updated_comment_text = 'Comment text' . $decomposed_str;
		$update = wp_update_comment( array( 'comment_ID' => $comment_id, 'comment_content' => $updated_comment_text ) );

		$this->assertSame( 1, $update );

		$comment = get_comment( $comment_id );
		if ( class_exists( 'WP_Comment' ) ) {
			$this->assertInstanceOf( 'WP_Comment', $comment );
		} else {
			$this->assertTrue( is_object( $comment ) );
		}
		$this->assertSame( UNFC_Normalizer::normalize( $updated_comment_text ), $comment->comment_content );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $updated_comment_text ), $comment->comment_content );

		$comment_id2 = $this->factory->comment->create( array( 'comment_post_ID' => $post->post_id ) );

		$comment_author = 'Comment author' . $decomposed_str;
		$comment_author_url = 'http://example.com/address' . $decomposed_str;
		$update = wp_update_comment( array( 'comment_ID' => $comment_id2, 'comment_author' => $comment_author, 'comment_author_url' => $comment_author_url, 'comment_content' => '' ) );

		$comment = get_comment( $comment_id2 );
		if ( class_exists( 'WP_Comment' ) ) {
			$this->assertInstanceOf( 'WP_Comment', $comment );
		} else {
			$this->assertTrue( is_object( $comment ) );
		}
		$this->assertSame( UNFC_Normalizer::normalize( $comment_author ), $comment->comment_author );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $comment_author ), $comment->comment_author );
		$this->assertSame( UNFC_Normalizer::normalize( $comment_author_url ), $comment->comment_author_url );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $comment_author_url ), $comment->comment_author_url );

		// Appears to be no filter available for 'comment_meta'.

		/*
		$meta_key1 = 'metakey1' . $decomposed_str;
		$meta_val1 = 'metaval1' . $decomposed_str;
		$meta_key2 = 'metakey2' . $decomposed_str;
		$meta_val2 = 'metaval2' . $decomposed_str;
		$update = wp_update_comment( array( 'comment_ID' => $comment_id2, 'comment_meta' => array( $meta_key1 => $meta_val1, $meta_key2 => $meta_val1 ) ) );

		$out = get_comment_meta( $comment_id2, $meta_key1 );
		$this->assertSame( UNFC_Normalizer::normalize( $meta_val1 ), $meta_val1 );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $meta_val1 ), $meta_val1 );
		$out = get_comment_meta( $comment_id2, $meta_key2 );
		$this->assertSame( UNFC_Normalizer::normalize( $meta_val2 ), $meta_val2 );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $meta_val2 ), $meta_val2 );
		*/
	}
}
