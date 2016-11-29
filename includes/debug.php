<?php
/**
 * Some debugging helpers.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'UNFC_DEBUG' ) ) define( 'UNFC_DEBUG', defined( 'WP_DEBUG' ) ? WP_DEBUG : false );

if ( UNFC_DEBUG ) :

if ( ! defined( 'UNFC_DEBUG_PRINT_LIMIT' ) ) define( 'UNFC_DEBUG_PRINT_LIMIT', 256 );

if ( ! defined( 'WP_CONTENT_DIR' ) ) define( 'WP_CONTENT_DIR', '' );

/**
 * Error log helper. Prints arguments, prefixing file, line and function called from.
 */
function unfc_error_log() {
	$func_get_args = func_get_args();
	$ret = unfc_debug_trace( debug_backtrace(), $func_get_args );
	$ret[0] = 'ERROR: ' . $ret[0] . "\n\t";
	$ret = implode( '', $ret );
	error_log( $ret );
	return $ret;
}

/**
 * Debug log helper. Same as unfc_error_log() except it prints nothing unless UNFC_DEBUG set, and doesn't prefix with "ERROR:".
 */
function unfc_debug_log() {
	if ( ! UNFC_DEBUG ) return '';
	$func_get_args = func_get_args();
	$ret = unfc_debug_trace( debug_backtrace(), $func_get_args );
	$ret[0] = $ret[0] . "\n\t";
	$ret = implode( '', $ret );
	error_log( $ret );
	return $ret;
}

/**
 * Common routine for unfc_error_log() and unfc_debug_log().
 */
function unfc_debug_trace( $trace, $func_get_args ) {
	$file = str_replace( array( WP_CONTENT_DIR, ABSPATH ), array( '../..', '../../../' ), isset( $trace[0]['file'] ) ? $trace[0]['file'] : '' ); // Assuming in wp-content/themes/mytheme.
	$line = isset( $trace[0]['line'] ) ? $trace[0]['line'] : '';
	$function_args = '';
	if ( ( $function = isset( $trace[1]['function'] ) ? $trace[1]['function'] : '' ) && ! empty( $trace[1]['args'] ) ) {
		$function_args = array_reduce( $trace[1]['args'], 'unfc_debug_trace_array_reduce_cb' );
	}

	$ret[] = $file . ':' . $line . ' ' . $function . '(' . unfc_print_r( $function_args ) . ') '; // Limit $function_args length.

	foreach ( $func_get_args as $func_get_arg ) $ret[] = is_array( $func_get_arg ) || is_object( $func_get_arg ) ? print_r( $func_get_arg, true ) : $func_get_arg;

	return $ret;
}

/**
 * Callback for array_reduce() in unfc_debug_trace().
 */
function unfc_debug_trace_array_reduce_cb( $carry, $item ) {
	return ( $carry === null ? '' : $carry . ', ' )
			. ( is_array( $item ) ? 'Array' : ( is_object( $item ) ? 'Object' : ( is_null( $item ) ? 'null' : str_replace( "\n", '', print_r( $item, true ) ) ) ) );
}

/**
 * Backtrace formatter for debugging.
 */
function unfc_backtrace( $offset = 0, $length = null ) {
	if ( ! UNFC_DEBUG ) return '';
	$ret = array();
	$backs = debug_backtrace();
	$i = count( $backs );
	foreach ( $backs as $back ) {
		$entry = "\t" . $i . '. ';
		$entry .= isset($back['class']) ? "{$back['class']}::" : '';
		$entry .= isset($back['function']) ? "{$back['function']} " : '';
		$entry .= isset($back['file']) ? "{$back['file']}:" : '';
		$entry .= isset($back['line']) ? "{$back['line']} " : '';
		$ret[] = $entry;
		$i--;
	}
	if ( $length === null ) $length = 20;
	return "\n" . implode( "\n", array_reverse( array_slice( $ret, $offset + 1, $length ) ) );
}

/**
 * Wrapper around PHP print_r() to limit length dumped.
 */
function unfc_print_r( $var ) {
	if ( is_array( $var ) ) {
		return print_r( array_map( 'unfc_print_r', $var ), true );
	}
	return unfc_print_r_limit_cb( print_r( $var, true ) );
}

/**
 * Callback for unfc_print_r().
 */
function unfc_print_r_limit_cb( $str ) {
	if ( strlen( $str ) > UNFC_DEBUG_PRINT_LIMIT ) {
		return substr( $str, 0, UNFC_DEBUG_PRINT_LIMIT ) . '...';
	}
	return $str;
}

/**
 * Hex dump version of unfc_print_r().
 */
function unfc_print_r_hex( $var ) {
	if ( is_array( $var ) ) {
		return trim( print_r( array_map( 'unfc_print_r_hex', $var ), true ) );
	}
	return unfc_print_r_limit_cb( unfc_bin2hex( $var ) );
}

/**
 * Dump a variable as a string.
 */
function unfc_dump( $var, $format = false ) {
	if ( ! UNFC_DEBUG ) return '';
	ob_start();
	debug_zval_dump( $var );
	$ret = ob_get_clean();
	if ( $format ) {
		$ret = preg_replace( '/ refcount\(\d+\)$/m', '', $ret );
		$ret = preg_replace( '/ refcount\(\d+\)/m', ' ', $ret );
		$ret = preg_replace( '/=>[ \n]+/', '=> ', $ret );
	}
	return $ret;
}

/**
 * Hex dump a variable if string.
 */
function unfc_bin2hex( $var ) {
	$ret = '';
	if ( ! isset( $var ) || is_null( $var ) ) {
		$ret .= '(null)';
	} elseif ( is_array( $var ) || is_object( $var ) ) {
		$ret .= '(' . gettype( $var ) . ')';
		foreach ( (array) $var as $k => $v ) {
			$ret .= ';' . $k . '=' . ( is_scalar( $v ) ? unfc_bin2hex( $v ) : print_r( $v, true ) );
		}
	} elseif ( is_string( $var ) ) {
		$ret .= bin2hex( $var );
	} elseif ( is_bool( $var ) ) {
		$ret .= '(' . gettype( $var ) . ')' . ( $var ? 'true' : 'false' );
	} elseif ( is_int( $var ) ) {
		$ret .= '(' . gettype( $var) . ')' . $var;
	} elseif ( is_float( $var ) ) {
		$ret .= '(' . gettype( $var ) . ')' . $var;
	} elseif ( is_resource( $var ) ) {
		$ret .= '(' . get_resource_type( $var ) . ')' . $var;
	} else {
		$ret .= '(' . gettype( $var ) . ')';
	}
	return $ret;
}

/**
 * Format bytes in human-friendly manner.
 * From http://stackoverflow.com/a/2510459
 */
function unfc_format_bytes( $bytes, $precision = 2 ) { 
    $units = array( 'B', 'KB', 'MB', 'GB', 'TB' ); 

    $bytes = max( $bytes, 0 ); 
    $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) ); 
    $pow = min( $pow, count( $units ) - 1 ); 

    // Uncomment one of the following alternatives
    // $bytes /= pow( 1024, $pow );
    $bytes /= ( 1 << ( 10 * $pow ) ); 

    return round( $bytes, $precision ) . ' ' . $units[$pow]; 
}

else :

function unfc_error_log() { return ''; }
function unfc_debug_log() { return ''; }
function unfc_backtrace( $offset = 0, $length = null ) { return ''; }
function unfc_print_r( $var ) { return ''; }
function unfc_print_r_limit_cb( $str ) { return ''; }
function unfc_print_r_hex( $var ) { return ''; }
function unfc_dump( $var, $format = false ) { return ''; }
function unfc_bin2hex( $var ) { return ''; }
function unfc_format_bytes( $bytes, $precision = 2 ) { return ''; }

endif;
