<?php

class PHPUnit_Util_Test extends PHPUnit\Util\Test {

	public static function getTickets( $className, $methodName ) {
		$annotations = self::parseTestMethodAnnotations( $className, $methodName );

		$tickets = array();

		if ( isset( $annotations['class']['ticket'] ) ) {
			$tickets = $annotations['class']['ticket'];
		}

		if ( isset( $annotations['method']['ticket'] ) ) {
			$tickets = array_merge( $tickets, $annotations['method']['ticket'] );
		}

		return array_unique( $tickets );
	}
}
