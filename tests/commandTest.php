<?php
/**
 * Test WP-CLI command functionality.
 *
 * @group unfc
 * @group unfc_command
 * @require PHP 5.3
 */
class TestUNFC_Command extends WP_UnitTestCase {

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

		add_filter( 'pre_schedule_event', '__return_true' ); // Disable WP cron stuff, was causing MySQL locking issues due to wp test transactioning.
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
		if ( self::$is_less_than_wp_4 ) {
			mbstring_binary_safe_encoding(); // For <= WP 3.9.13 compatibility - remove_accents() uses naked strlen().
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
		if ( self::$is_less_than_wp_4 ) {
			reset_mbstring_encoding(); // For <= WP 3.9.13 compatibility - remove_accents() uses naked strlen().
		}
	}

    /**
     */
	function test_scan_db_command() {

		$dirname = dirname( __FILE__ );
		$wp_fmt = 'wp unfc-normalize scan-db %s --path=' . ABSPATH . ' --require=' . $dirname . '/wp-cli-bootstrap.php 2>&1';

		// No non-normalized.

		$output = $return_var = null;
		$cmd = sprintf( $wp_fmt, '' );
		exec( $cmd, $output, $return_var );
		$this->assertSame( 1, count( $output ), $cmd . "\n" . implode( "\n", $output ) );
		$this->assertSame( 'Success: No non-normalized data detected!', $output[0] );

		// Same as default.
		$output2 = $return_var = null;
		$cmd = sprintf( $wp_fmt, '--format=table' );
		exec( $cmd, $output2, $return_var );
		$this->assertSame( $output, $output2 );

		$output = $return_var = null;
		$cmd = sprintf( $wp_fmt, '--format=csv' );
		exec( $cmd, $output, $return_var );
		$this->assertSame( 1, count( $output ) );
		$this->assertSame( 'Success: No non-normalized data detected!', $output[0] );

		$output = $return_var = null;
		$cmd = sprintf( $wp_fmt, '--quiet' );
		exec( $cmd, $output, $return_var );
		$this->assertTrue( empty( $output ) );

		// With non-normalized.

		$decomposed_str1 = "o\xcc\x88"; // o umlaut.

		$title1 = 'post1-title' . $decomposed_str1;
		$content1 = 'post1-content';
		$post1 = $this->factory->post->create_and_get( array( 'post_title' => $title1, 'post_content' => $content1, 'post_type' => 'post' ) );
		$this->assertTrue( is_object( $post1 ) );

		$decomposed_str2 = "a\xe1\x84\x91\xe1\x85\xb4\xe1\x86\xaf"; // a + decomposed hangul.

		$title2 = 'post2-title';
		$content2 = 'post2-content' . $decomposed_str2;
		$post2 = $this->factory->post->create_and_get( array( 'post_title' => $title2, 'post_content' => $content2, 'post_type' => 'page' ) );
		$this->assertTrue( is_object( $post2 ) );

		$comment_content1_1 = 'comment1-content' . $decomposed_str1;
		$comment1_1_id = $this->factory->comment->create( array( 'comment_post_ID' => $post1->ID, 'comment_content' => $comment_content1_1 ) );

		// Too messy to manually remove user on multisite so don't bother adding.

		$term1_name = 'term1' . $decomposed_str1;
		$term1_id = $this->factory->term->create( array( 'name' => $term1_name, 'taxonomy' => 'category', 'description' => 'desc1' ) );

		$option1_name = 'option1';
		add_option( 'option1', 'val1' . $decomposed_str1 );
		// add_site_option( 'option1', 'val1' . $decomposed_str1 ); // We're a fake multisite which wp-cli won't see so don't try to test.

		global $wpdb;
		$wpdb->query( 'COMMIT' ); // Need to write data so wp-cli can see it.

		$output = $return_var = null;
		$cmd = sprintf( $wp_fmt, '' );
		exec( $cmd, $output, $return_var );
		$cnt = count( $output );
		$this->assertSame( 7, $cnt );
		$this->assertSame( 'Success: 5 non-normalized items detected.', $output[ $cnt - 1 ] );
		$concat = implode( "\n", $output );
		$this->assertTrue( false !== strpos( $concat, (string) $post1->ID ) );
		$this->assertTrue( false !== strpos( $concat, $title1 ) );
		$this->assertTrue( false !== strpos( $concat, 'post_title' ) );
		$this->assertTrue( false !== strpos( $concat, (string) $post2->ID ) );
		$this->assertTrue( false !== strpos( $concat, $title2 ) );
		$this->assertTrue( false !== strpos( $concat, 'post_content' ) );
		$this->assertTrue( false !== strpos( $concat, (string) $comment1_1_id ) );
		$this->assertTrue( false !== strpos( $concat, (string) $comment_content1_1 ) );
		$this->assertTrue( false !== strpos( $concat, 'comment_content' ) );
		$this->assertTrue( false !== strpos( $concat, (string) $term1_id ) );
		$this->assertTrue( false !== strpos( $concat, $term1_name ) );
		$this->assertTrue( false !== strpos( $concat, 'category' ) );
		$this->assertTrue( false !== strpos( $concat, $option1_name ) );
		$this->assertTrue( false !== strpos( $concat, 'option_value' ) );

		$output = $return_var = null;
		$cmd = sprintf( $wp_fmt, '--format=csv --quiet' );
		exec( $cmd, $output, $return_var );
		$cnt = count( $output );
		$this->assertSame( 6, $cnt );
		$this->assertSame( 'ID,Title,Type,Subtype,"Field (1st detected only)"', $output[0] );

		$output = $return_var = null;
		$cmd = sprintf( $wp_fmt, '--format=count' );
		exec( $cmd, $output, $return_var );
		$this->assertSame( 1, count( $output ) );
		$this->assertSame( '5', $output[0] );

		// As committed need to manually remove data.
		$ret = wp_delete_post( $post1->ID, true /*force_delete*/ ); // Removes comment also.
		$this->assertTrue( is_object( $ret ) );
		$ret = wp_delete_post( $post2->ID, true /*force_delete*/ );
		$this->assertTrue( is_object( $ret ) );
		$ret = wp_delete_term( $term1_id, 'category' );
		$this->assertTrue( $ret );
		$ret = delete_option( $option1_name );
		$this->assertTrue( $ret );
		$wpdb->query( 'COMMIT' ); // Need to write data so wp-cli can see it.

		// Make sure everything gone.
		$output = $return_var = null;
		$cmd = sprintf( $wp_fmt, '' );
		exec( $cmd, $output, $return_var );
		$this->assertSame( 1, count( $output ) );
		$this->assertSame( 'Success: No non-normalized data detected!', $output[0] );
	}

    /**
     */
	function test_db_command() {

		$dirname = dirname( __FILE__ );
		$wp_fmt = 'wp unfc-normalize db %s --path=' . ABSPATH . ' --require=' . $dirname . '/wp-cli-bootstrap.php 2>&1';

		$output = $return_var = null;
		$cmd = sprintf( $wp_fmt, '--yes' );
		exec( $cmd, $output, $return_var );
		$this->assertSame( 1, count( $output ) );
		$this->assertSame( 'Success: No non-normalized data detected - nothing updated!', $output[0] );

		$decomposed_str1 = "o\xcc\x88"; // o umlaut.

		$title1 = 'post1-title' . $decomposed_str1;
		$content1 = 'post1-content';
		$post1 = $this->factory->post->create_and_get( array( 'post_title' => $title1, 'post_content' => $content1, 'post_type' => 'post' ) );
		$this->assertTrue( is_object( $post1 ) );

		$decomposed_str2 = "a\xe1\x84\x91\xe1\x85\xb4\xe1\x86\xaf"; // a + decomposed hangul.

		$title2 = 'post2-title';
		$content2 = 'post2-content' . $decomposed_str2;
		$post2 = $this->factory->post->create_and_get( array( 'post_title' => $title2, 'post_content' => $content2, 'post_type' => 'page' ) );
		$this->assertTrue( is_object( $post2 ) );

		$comment_content1_1 = 'comment1-content' . $decomposed_str1;
		$comment1_1_id = $this->factory->comment->create( array( 'comment_post_ID' => $post1->ID, 'comment_content' => $comment_content1_1 ) );

		global $wpdb;
		$wpdb->query( 'COMMIT' ); // Need to write data so wp-cli can see it.

		$output = $return_var = null;
		$cmd = 'echo y | ' . sprintf( $wp_fmt, '' );
		exec( $cmd, $output, $return_var );
		$cnt = count( $output );
		$this->assertSame( 2, $cnt ); // Confirmation & success message go on to one line.
		$this->assertTrue( false !== strpos( $output[ $cnt - 1 ], 'Success: 3 items normalized.' ) );

		// As committed need to manually remove data.
		$ret = wp_delete_post( $post1->ID, true /*force_delete*/ ); // Removes comment also.
		$this->assertTrue( is_object( $ret ) );
		$ret = wp_delete_post( $post2->ID, true /*force_delete*/ );
		$this->assertTrue( is_object( $ret ) );
		$wpdb->query( 'COMMIT' ); // Need to write data so wp-cli can see it.

		$output = $return_var = null;
		$cmd = sprintf( $wp_fmt, '--yes' );
		exec( $cmd, $output, $return_var );
		$this->assertSame( 1, count( $output ) );
		$this->assertSame( 'Success: No non-normalized data detected - nothing updated!', $output[0] );
	}

    /**
     */
	function test_scan_slugs_command() {

		$dirname = dirname( __FILE__ );
		$wp_fmt = 'wp unfc-normalize scan-slugs %s --path=' . ABSPATH . ' --require=' . $dirname . '/wp-cli-bootstrap.php 2>&1';

		// No non-normalized.

		$output = $return_var = null;
		$cmd = sprintf( $wp_fmt, '' );
		exec( $cmd, $output, $return_var );
		$this->assertSame( 1, count( $output ) );
		$this->assertSame( 'Success: No non-normalized percent-encoded slugs detected!', $output[0] );

		// Same as default.
		$output2 = $return_var = null;
		$cmd = sprintf( $wp_fmt, '--format=table' );
		exec( $cmd, $output2, $return_var );
		$this->assertSame( $output, $output2 );

		$output = $return_var = null;
		$cmd = sprintf( $wp_fmt, '--format=csv' );
		exec( $cmd, $output, $return_var );
		$this->assertSame( 1, count( $output ) );
		$this->assertSame( 'Success: No non-normalized percent-encoded slugs detected!', $output[0] );

		$output = $return_var = null;
		$cmd = sprintf( $wp_fmt, '--quiet' );
		exec( $cmd, $output, $return_var );
		$this->assertTrue( empty( $output ) );

		// With non-normalized.

		$decomposed_str1 = "o\xcc\x88"; // o umlaut.

		$title1 = 'post1-title' . $decomposed_str1;
		$content1 = 'post1-content';
		$post1 = $this->factory->post->create_and_get( array( 'post_title' => $title1, 'post_content' => $content1, 'post_type' => 'post' ) );
		$this->assertTrue( is_object( $post1 ) );

		$decomposed_str2 = "a\xe1\x84\x91\xe1\x85\xb4\xe1\x86\xaf"; // a + decomposed hangul.

		$title2 = 'post2-title' . $decomposed_str2;;
		$content2 = 'post2-content';
		$post2 = $this->factory->post->create_and_get( array( 'post_title' => $title2, 'post_content' => $content2, 'post_type' => 'page' ) );
		$this->assertTrue( is_object( $post2 ) );

		// Too messy to manually remove user on multisite so don't bother adding.

		$term1_name = 'term1' . $decomposed_str1;
		$term1_id = $this->factory->term->create( array( 'name' => $term1_name, 'taxonomy' => 'category', 'description' => 'desc1' ) );

		global $wpdb;
		$wpdb->query( 'COMMIT' ); // Need to write data so wp-cli can see it.

		$output = $return_var = null;
		$cmd = sprintf( $wp_fmt, '' );
		exec( $cmd, $output, $return_var );
		$cnt = count( $output );
		$this->assertSame( 5, $cnt );
		$this->assertSame( 'Success: 3 non-normalized percent-encoded slugs detected.', $output[ $cnt - 1 ] );
		$concat = implode( "\n", $output );
		$this->assertTrue( false !== strpos( $concat, (string) $post1->ID ) );
		$this->assertTrue( false !== strpos( $concat, $title1 ) );
		$this->assertTrue( false !== strpos( $concat, (string) $post2->ID ) );
		$this->assertTrue( false !== strpos( $concat, $title2 ) );
		$this->assertTrue( false !== strpos( $concat, (string) $term1_id ) );
		$this->assertTrue( false !== strpos( $concat, $term1_name ) );

		$output = $return_var = null;
		$cmd = sprintf( $wp_fmt, '--format=csv --quiet' );
		exec( $cmd, $output, $return_var );
		$cnt = count( $output );
		$this->assertSame( 4, $cnt );
		$this->assertSame( 'ID,Title,Type,Slug,Decoded,"If Normalized","Normalized Decoded"', $output[0] );

		$output = $return_var = null;
		$cmd = sprintf( $wp_fmt, '--format=count' );
		exec( $cmd, $output, $return_var );
		$this->assertSame( 1, count( $output ) );
		$this->assertSame( '3', $output[0] );

		// As committed need to manually remove data.
		$ret = wp_delete_post( $post1->ID, true /*force_delete*/ ); // Removes comment also.
		$this->assertTrue( is_object( $ret ) );
		$ret = wp_delete_post( $post2->ID, true /*force_delete*/ );
		$this->assertTrue( is_object( $ret ) );
		$ret = wp_delete_term( $term1_id, 'category' );
		$this->assertTrue( $ret );
		$wpdb->query( 'COMMIT' ); // Need to write data so wp-cli can see it.

		// Make sure everything gone.
		$output = $return_var = null;
		$cmd = sprintf( $wp_fmt, '' );
		exec( $cmd, $output, $return_var );
		$this->assertSame( 1, count( $output ) );
		$this->assertSame( 'Success: No non-normalized percent-encoded slugs detected!', $output[0] );
	}

    /**
     */
	function test_slugs_command() {

		$dirname = dirname( __FILE__ );
		$wp_fmt = 'wp unfc-normalize slugs %s --path=' . ABSPATH . ' --require=' . $dirname . '/wp-cli-bootstrap.php 2>&1';

		$output = $return_var = null;
		$cmd = sprintf( $wp_fmt, '--yes' );
		exec( $cmd, $output, $return_var );
		$this->assertSame( 1, count( $output ) );
		$this->assertSame( 'Success: No non-normalized percent-encoded slugs detected - nothing updated!', $output[0] );

		$decomposed_str1 = "o\xcc\x88"; // o umlaut.

		$title1 = 'post1-title' . $decomposed_str1;
		$content1 = 'post1-content';
		$post1 = $this->factory->post->create_and_get( array( 'post_title' => $title1, 'post_content' => $content1, 'post_type' => 'post' ) );
		$this->assertTrue( is_object( $post1 ) );

		$decomposed_str2 = "a\xe1\x84\x91\xe1\x85\xb4\xe1\x86\xaf"; // a + decomposed hangul.

		$title2 = 'post2-title' . $decomposed_str2;
		$content2 = 'post2-content';
		$post2 = $this->factory->post->create_and_get( array( 'post_title' => $title2, 'post_content' => $content2, 'post_type' => 'page' ) );
		$this->assertTrue( is_object( $post2 ) );

		$term1_name = 'term1' . $decomposed_str1;
		$term1_id = $this->factory->term->create( array( 'name' => $term1_name, 'taxonomy' => 'category', 'description' => 'desc1' ) );

		global $wpdb;
		$wpdb->query( 'COMMIT' ); // Need to write data so wp-cli can see it.

		$output = $return_var = null;
		$cmd = 'echo y | ' . sprintf( $wp_fmt, '' );
		exec( $cmd, $output, $return_var );
		$cnt = count( $output );
		$this->assertSame( 2, $cnt ); // Confirmation & success message go on to one line.
		$this->assertTrue( false !== strpos( $output[ $cnt - 1 ], 'Success: 3 slugs normalized.' ) );

		// As committed need to manually remove data.
		$ret = wp_delete_post( $post1->ID, true /*force_delete*/ ); // Removes comment also.
		$this->assertTrue( is_object( $ret ) );
		$ret = wp_delete_post( $post2->ID, true /*force_delete*/ );
		$this->assertTrue( is_object( $ret ) );
		$ret = wp_delete_term( $term1_id, 'category' );
		$this->assertTrue( $ret );
		$wpdb->query( 'COMMIT' ); // Need to write data so wp-cli can see it.

		$output = $return_var = null;
		$cmd = sprintf( $wp_fmt, '--yes' );
		exec( $cmd, $output, $return_var );
		$this->assertSame( 1, count( $output ) );
		$this->assertSame( 'Success: No non-normalized percent-encoded slugs detected - nothing updated!', $output[0] );
	}
}
