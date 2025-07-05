<?php
/**
 * Logger class
 *
 * @package ImageOptimizer
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger class
 */
class Logger {

	/**
	 * Log file path
	 *
	 * @var string
	 */
	private static $log_file;

	/**
	 * Initialize logger
	 */
	public static function init() {
		$upload_dir = wp_upload_dir();
		$log_dir = $upload_dir['basedir'] . '/image-optimizer-logs';
		
		// Create log directory if it doesn't exist
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
			
			// Create .htaccess to protect logs
			$htaccess_file = $log_dir . '/.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				file_put_contents( $htaccess_file, "Order deny,allow\nDeny from all\n" );
			}
		}

		self::$log_file = $log_dir . '/image-optimizer.log';
	}

	/**
	 * Log a message
	 *
	 * @param string $message Message to log.
	 * @param string $level Log level (info, warning, error).
	 */
	public static function log( $message, $level = 'info' ) {
		if ( ! self::$log_file ) {
			self::init();
		}

		$timestamp = current_time( 'Y-m-d H:i:s' );
		$log_entry = sprintf( '[%s] [%s] %s%s', $timestamp, strtoupper( $level ), $message, PHP_EOL );

		// Write to log file
		error_log( $log_entry, 3, self::$log_file );

		// Also log to WordPress debug log if enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Image Optimizer: ' . $message );
		}
	}

	/**
	 * Log info message
	 *
	 * @param string $message Message to log.
	 */
	public static function info( $message ) {
		self::log( $message, 'info' );
	}

	/**
	 * Log warning message
	 *
	 * @param string $message Message to log.
	 */
	public static function warning( $message ) {
		self::log( $message, 'warning' );
	}

	/**
	 * Log error message
	 *
	 * @param string $message Message to log.
	 */
	public static function error( $message ) {
		self::log( $message, 'error' );
	}

	/**
	 * Get log file path
	 *
	 * @return string Log file path.
	 */
	public static function get_log_file() {
		if ( ! self::$log_file ) {
			self::init();
		}
		return self::$log_file;
	}

	/**
	 * Get log contents
	 *
	 * @param int $lines Number of lines to retrieve (0 for all).
	 * @return string Log contents.
	 */
	public static function get_log_contents( $lines = 0 ) {
		$log_file = self::get_log_file();
		
		if ( ! file_exists( $log_file ) ) {
			return '';
		}

		$contents = file_get_contents( $log_file );
		
		if ( $lines > 0 ) {
			$lines_array = explode( PHP_EOL, $contents );
			$lines_array = array_filter( $lines_array ); // Remove empty lines
			$lines_array = array_slice( $lines_array, -$lines );
			$contents = implode( PHP_EOL, $lines_array );
		}

		return $contents;
	}

	/**
	 * Clear log file
	 */
	public static function clear_log() {
		$log_file = self::get_log_file();
		
		if ( file_exists( $log_file ) ) {
			file_put_contents( $log_file, '' );
		}
	}

	/**
	 * Get log file size
	 *
	 * @return int Log file size in bytes.
	 */
	public static function get_log_size() {
		$log_file = self::get_log_file();
		
		if ( file_exists( $log_file ) ) {
			return filesize( $log_file );
		}
		
		return 0;
	}

	/**
	 * Check if logging is enabled
	 *
	 * @return bool True if logging is enabled.
	 */
	public static function is_enabled() {
		return get_option( 'image_optimizer_enable_logging', true );
	}
}
