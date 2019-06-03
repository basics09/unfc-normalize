<?php
/**
 * Test user filters.
 *
 * @group unfc
 * @group unfc_user
 */
class Tests_UNFC_User extends WP_UnitTestCase {

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
		$pagenow = 'user.php';
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
	function test_user() {
		$this->assertTrue( is_admin() ) ;

		do_action( 'init' );

		global $unfc_normalize;
		$this->assertArrayHasKey( 'user', $unfc_normalize->added_filters );

		$decomposed_str = "u\xCC\x88"; // u umlaut.

		$_POST = $_GET = $_REQUEST = array();
		$_POST['role'] = 'subscriber';
		$_POST['email'] = 'user1@example.com';
		$_POST['user_login'] = 'user_login1'/* . $decomposed_str*/; // Can't use in user_login as validate_username() strips to ASCII.
		$_POST['nickname'] = 'nickname1' . $decomposed_str;
		$_POST['description'] = 'description' . $decomposed_str;
		$_POST['display_name'] = 'display_name1' . $decomposed_str;
		$_POST['first_name'] = 'first_name1' . $decomposed_str;
		$_POST['last_name'] = 'last_name1' . $decomposed_str;
		$_POST['pass1'] = $_POST['pass2'] = 'password' . $decomposed_str;
		$_POST['aim'] = 'AIM' . $decomposed_str;

		add_filter( 'user_contactmethods', array( $this, 'user_contactmethods_filter' ), 10, 2 );

		$id = edit_user();

		$this->assertInternalType( 'int', $id );

		$user = get_user_by( 'id', $id );

		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( UNFC_Normalizer::normalize( $_POST['nickname'] ), $user->nickname );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $_POST['nickname'] ), $user->nickname );
		$this->assertSame( UNFC_Normalizer::normalize( $_POST['description'] ), $user->description );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $_POST['description'] ), $user->description );
		$this->assertSame( UNFC_Normalizer::normalize( $_POST['display_name'] ), $user->display_name );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $_POST['display_name'] ), $user->display_name );
		$this->assertSame( UNFC_Normalizer::normalize( $_POST['first_name'] ), $user->first_name );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $_POST['first_name'] ), $user->first_name );
		$this->assertSame( UNFC_Normalizer::normalize( $_POST['last_name'] ), $user->last_name );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $_POST['last_name'] ), $user->last_name );
		$this->assertTrue( wp_check_password( $_POST['pass1'], $user->user_pass ) ); // Not normalized.
		$this->assertFalse( wp_check_password( UNFC_Normalizer::normalize( $_POST['pass1'] ), $user->user_pass ) ); // Not normalized.
		if ( class_exists( 'Normalizer' ) ) $this->assertFalse( wp_check_password( Normalizer::normalize( $_POST['pass1'] ), $user->user_pass ) ); // Not normalized.

		$out = get_user_meta( $id, 'nickname', true );

		$this->assertSame( UNFC_Normalizer::normalize( $_POST['nickname'] ), $out );
		if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $_POST['nickname'] ), $out );

		global $wp_version;
		if ( version_compare( $wp_version, '4.4', '>=' ) ) {
			$out = get_user_meta( $id, 'aim', true );

			$this->assertSame( UNFC_Normalizer::normalize( $_POST['aim'] ), $out );
			if ( class_exists( 'Normalizer' ) ) $this->assertSame( Normalizer::normalize( $_POST['aim'] ), $out );
		}
	}

	function user_contactmethods_filter( $methods, $user = null ) {
		$methods['aim'] = __( 'AIM' );
		return $methods;
	}
}
