<?php
/**
 * Small activity log kept in one option (last 300 lines).
 *
 * @package kadvia-bot
 */

defined( 'ABSPATH' ) || exit;

class KB_Log {

	const OPTION = 'kb_log';
	const MAX    = 300;

	public static function add( $text, $level = 'info' ) {
		$log = get_option( self::OPTION, array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift(
			$log,
			array(
				't' => current_time( 'mysql' ),
				'l' => $level,
				'm' => wp_strip_all_tags( (string) $text ),
			)
		);
		update_option( self::OPTION, array_slice( $log, 0, self::MAX ), false );
	}

	public static function error( $text ) {
		self::add( $text, 'error' );
	}

	public static function lines( $limit = 300 ) {
		$log = get_option( self::OPTION, array() );
		return array_slice( is_array( $log ) ? $log : array(), 0, $limit );
	}
}
