<?php

class Woozoho_Connector_Logger {

	public static function writeDebug( $type, $data ) {
		if ( Woozoho_Connector::get_option( "debugging" ) ) {
			$multisite = is_multisite() ? "[" . get_bloginfo( 'name' ) . "]" : ( "" ); //Multi-site support.
			$logfile   = realpath( __DIR__ . '/..' ) . '/debug_log';
			file_put_contents( $logfile,
				$multisite . "[" . date( "Y-m-d H:i:s" ) . "] [" . $type . "] " . $data . "\n", FILE_APPEND );
		}
	}

}