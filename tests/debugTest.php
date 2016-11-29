<?php
/**
 * Test debug.
 *
 * @group unfc
 * @group unfc_debug
 */
class Tests_UNFC_Debug extends WP_UnitTestCase {

    /**
	 * @ticket unfc_debug_debug
     */
	function test_debug() {
		$this->assertTrue( UNFC_DEBUG );

		$output = unfc_error_log( "Test" );
		$this->assertRegExp( '/^ERROR: .+?\/unfc-normalize\/tests\/debugTest.php:[1-2][0-9] test_debug\(\)[ \n\t]+Test$/', $output );

		$output = unfc_debug_log( "Test" );
		$this->assertRegExp( '/\/unfc-normalize\/tests\/debugTest.php:[1-2][0-9] test_debug\(\)[ \n\t]+Test$/', $output );

		$output = explode( "\n", unfc_backtrace() );
		$last_idx = count( $output ) - 1;
		$this->assertSame( "{$last_idx}. Tests_UNFC_Debug::test_debug", trim( $output[ $last_idx ] ) );
	}

    /**
	 * @ticket unfc_debug_print_r
     */
	function test_print_r() {
		$el = str_repeat( 'a', UNFC_DEBUG_PRINT_LIMIT + 1 );
		$var = array( $el );
		$output = unfc_print_r( $var );
		$expected = print_r( array( substr( $el, 0, -1 ) . '...' ), true );
		$this->assertSame( $expected, $output );

		$el = "\xc2\xa0";
		$var = array( $el, true, 1010, 1.2, array( $el ), null );
		$output = unfc_print_r_hex( $var );
		$expected = trim( print_r( array( 'c2a0', '(boolean)true', '(integer)1010', '(double)1.2', "Array\n(\n    [0] => c2a0\n)", '(null)' ), true ) );
		$this->assertSame( $expected, $output );

		$fd = fopen( '/dev/null', 'r' );
		$output = unfc_print_r_hex( $fd );
		fclose( $fd );
		$this->assertSame( "(stream)" . $fd, $output );

		$var = array( "a", true, 1010, 1.2, null );
		$output = unfc_dump( $var, true /*$format*/ );
		$this->assertRegExp( '/array\(5\) {\n  \[0\]=> string\(1\) "a"\n  \[1\]=> bool\(true\)\n  \[2\]=> (?:int|long)\(1010\)\n  \[3\]=> (?:float|double)\(1\.2\)\n  \[4\]=> NULL\n}\n/', $output );

		$output = unfc_bin2hex( array( __CLASS__, 'test_print_r' ) );
		$this->assertSame( "(array);0=" . bin2hex( 'Tests_UNFC_Debug' ) . ";1=" . bin2hex( 'test_print_r' ), $output );
	}

    /**
	 * @ticket unfc_debug_format_bytes
	 * @dataProvider data_format_bytes
     */
	function test_format_bytes( $bytes, $precision, $expected ) {
		$output = unfc_format_bytes( $bytes, $precision );
		$this->assertSame( $expected, $output );
	}

	function data_format_bytes() {
		return array(
			array( 1024 * 1024 * 1024 * 1024 * 100, 2, '100 TB' ),
			array( 1024 * 1024 * 1024 * 12, 4, '12 GB' ),
			array( 1024 * 1024 * 2, 2, '2 MB' ),
			array( 1024 * 14, 2, '14 KB' ),
			array( 134, 2, '134 B' ),
			array( 1024 * 12 + 1, 2, '12 KB' ),
			array( 1024 * 12 + 128, 3, '12.125 KB' ),
			array( 1024 * 12 + 255, 2, '12.25 KB' ),
			array( 1024 * 12 + 511, 2, '12.5 KB' ),
			array( 1024 * 12 + 511, 0, '12 KB' ),
			array( 1024 * 12 + 512, 2, '12.5 KB' ),
			array( 1024 * 12 + 512, 0, '13 KB' ),
			array( 1024 * 12 + 1023, 2, '13 KB' ),
		);
	}
}
