<?php

/**
 * @author Adddshore
 */
class WikimediaGraphite {

	public static function send( $metricName, $value, $date ) {
		list( $host, $port ) = self::getHostAndPort();
		exec( "echo \"$metricName $value `date -d \"$date\" +%s`\" | nc -q0 $host $port" );
	}

	public static function sendNow( $metricName, $value ) {
		list( $host, $port ) = self::getHostAndPort();
		exec( "echo \"$metricName $value `date +%s`\" | nc -q0 $host $port" );
	}

	private static function getHostAndPort() {
		$host = Config::getValue( 'graphite_host' );
		$port = '2003';
		if ( strstr( $host, ':' ) ) {
			return explode( ':', $host );
		}
		return array( $host, $port );
	}

}
