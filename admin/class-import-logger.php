<?php


namespace WP_Vote;

class Import_Logger {

	const LOG_NOTICE = 0;
	const LOG_WARN = 1;
	const LOG_ERROR = 2;

	public static $logbook = array();

	private static function log( $message, $level = self::LOG_NOTICE, $log_name = 'default' ) {

		if ( empty( self::$logbook ) ) {
			update_option( WP_Vote::get_prefix( 'import_log' ), array(), false );
		}

		if ( ! array_key_exists( $log_name, self::$logbook ) ) {
			self::$logbook[ $log_name ] = array(
				self::LOG_NOTICE => array(),
				self::LOG_WARN   => array(),
				self::LOG_ERROR  => array(),
			);
		}

		self::$logbook[ $log_name ][ $level ][] = $message;

		update_option( WP_Vote::get_prefix( 'import_log' ), self::$logbook, false );
	}

	public static function notice( $message, $log_name = 'default' ) {
		self::log( $message, self::LOG_NOTICE, $log_name );
	}

	public static function warn( $message, $log_name = 'default' ) {
		self::log( $message, self::LOG_WARN, $log_name );
	}

	public static function error( $message, $log_name = 'default' ) {
		self::log( $message, self::LOG_ERROR, $log_name );
	}

	public static function get_log( $level = false, $log_name = 'default ' ) {

		self::$logbook = get_option( WP_Vote::get_prefix( 'import_log' ), true );

		if ( ! array_key_exists( $log_name, self::$logbook ) ) {
			return array();
		}

		if ( false !== $level && ! array_key_exists( $level, self::$logbook[ $log_name ] ) ) {
			return array();
		}

		if ( false === $level ) {
			return self::$logbook[ $log_name ];
		}

		return self::$logbook[ $log_name ][ $level ];
	}

	public static function get_notices( $log_name = 'default' ) {
		return self::get_log( self::LOG_NOTICE, $log_name );
	}

	public static function get_warnings( $log_name = 'default' ) {
		return self::get_log( self::LOG_WARN, $log_name );
	}

	public static function get_errors( $log_name = 'default' ) {
		return self::get_log( self::LOG_ERROR, $log_name );
	}

}