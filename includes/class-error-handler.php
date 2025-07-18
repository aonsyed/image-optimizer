<?php
/**
 * Error Handler class for comprehensive error handling and logging
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Error Handler class
 * 
 * Provides comprehensive error handling, logging, and recovery mechanisms
 * for the WordPress Image Optimizer plugin.
 */
class WP_Image_Optimizer_Error_Handler {

	/**
	 * Error log option name
	 *
	 * @var string
	 */
	const ERROR_LOG_OPTION = 'wp_image_optimizer_error_log';

	/**
	 * Error statistics option name
	 *
	 * @var string
	 */
	const ERROR_STATS_OPTION = 'wp_image_optimizer_error_stats';

	/**
	 * Maximum number of error log entries to keep
	 *
	 * @var int
	 */
	const MAX_LOG_ENTRIES = 100;

	/**
	 * Error severity levels
	 *
	 * @var array
	 */
	const SEVERITY_LEVELS = array(
		'critical' => 1,
		'error' => 2,
		'warning' => 3,
		'notice' => 4,
		'info' => 5,
		'debug' => 6,
	);

	/**
	 * Error categories
	 *
	 * @var array
	 */
	const ERROR_CATEGORIES = array(
		'system' => 'System Error',
		'conversion' => 'Conversion Error',
		'configuration' => 'Configuration Error',
		'runtime' => 'Runtime Error',
		'security' => 'Security Error',
		'file_system' => 'File System Error',
		'validation' => 'Validation Error',
	);

	/**
	 * Instance of the error handler
	 *
	 * @var WP_Image_Optimizer_Error_Handler|null
	 */
	private static $instance = null;

	/**
	 * Current error context
	 *
	 * @var array
	 */
	private $error_context = array();

	/**
	 * Error recovery callbacks
	 *
	 * @var array
	 */
	private $recovery_callbacks = array();

	/**
	 * Get singleton instance
	 *
	 * @return WP_Image_Optimizer_Error_Handler
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton pattern
	 */
	private function __construct() {
		$this->init_error_handling();
	}

	/**
	 * Initialize error handling system
	 */
	private function init_error_handling() {
		// Set up error recovery callbacks
		$this->setup_recovery_callbacks();
		
		// Initialize error statistics if not exists
		$this->init_error_statistics();
	}

	/**
	 * Log an error with comprehensive information
	 *
	 * @param string|WP_Error $error Error message or WP_Error object
	 * @param string          $severity Error severity level
	 * @param string          $category Error category
	 * @param array           $context Additional context information
	 * @param bool            $user_friendly Whether to show user-friendly message
	 * @return string|false Error ID on success, false on failure
	 */
	public function log_error( $error, $severity = 'error', $category = 'runtime', $context = array(), $user_friendly = false ) {
		// Validate severity level
		if ( ! array_key_exists( $severity, self::SEVERITY_LEVELS ) ) {
			$severity = 'error';
		}

		// Validate category
		if ( ! array_key_exists( $category, self::ERROR_CATEGORIES ) ) {
			$category = 'runtime';
		}

		// Extract error information
		$error_data = $this->extract_error_data( $error );
		
		// Generate unique error ID
		$error_id = $this->generate_error_id();
		
		// Prepare error entry
		$error_entry = array(
			'id' => $error_id,
			'timestamp' => time(),
			'severity' => $severity,
			'category' => $category,
			'code' => $error_data['code'],
			'message' => $error_data['message'],
			'data' => $error_data['data'],
			'context' => array_merge( $this->error_context, $context ),
			'user_friendly' => $user_friendly,
			'stack_trace' => $this->get_stack_trace(),
			'wp_debug_info' => $this->get_wp_debug_info(),
		);

		// Log to WordPress debug log if enabled
		$this->log_to_wp_debug( $error_entry );
		
		// Store in plugin error log
		$this->store_error_log( $error_entry );
		
		// Update error statistics
		$this->update_error_statistics( $error_entry );
		
		// Attempt error recovery if applicable
		$this->attempt_error_recovery( $error_entry );
		
		// Trigger action for external handling
		do_action( 'wp_image_optimizer_error_logged', $error_entry );

		return $error_id;
	}

	/**
	 * Log a critical error that requires immediate attention
	 *
	 * @param string|WP_Error $error Error message or WP_Error object
	 * @param string          $category Error category
	 * @param array           $context Additional context information
	 * @return string|false Error ID on success, false on failure
	 */
	public function log_critical_error( $error, $category = 'system', $context = array() ) {
		$error_id = $this->log_error( $error, 'critical', $category, $context, true );
		
		// Send admin notification for critical errors
		$this->send_admin_notification( $error_id );
		
		return $error_id;
	}

	/**
	 * Log a conversion error with specific context
	 *
	 * @param string|WP_Error $error Error message or WP_Error object
	 * @param string          $original_file Original file path
	 * @param string          $target_format Target format
	 * @param array           $conversion_context Additional conversion context
	 * @return string|false Error ID on success, false on failure
	 */
	public function log_conversion_error( $error, $original_file = '', $target_format = '', $conversion_context = array() ) {
		$context = array_merge( array(
			'original_file' => $original_file,
			'target_format' => $target_format,
			'converter' => isset( $conversion_context['converter'] ) ? $conversion_context['converter'] : 'unknown',
		), $conversion_context );

		return $this->log_error( $error, 'error', 'conversion', $context, true );
	}

	/**
	 * Set error context for subsequent error logging
	 *
	 * @param array $context Context information
	 */
	public function set_error_context( $context ) {
		$this->error_context = is_array( $context ) ? $context : array();
	}

	/**
	 * Add to error context
	 *
	 * @param string $key Context key
	 * @param mixed  $value Context value
	 */
	public function add_error_context( $key, $value ) {
		$this->error_context[ $key ] = $value;
	}

	/**
	 * Clear error context
	 */
	public function clear_error_context() {
		$this->error_context = array();
	}

	/**
	 * Get recent error logs
	 *
	 * @param int    $limit Number of entries to retrieve
	 * @param string $severity Minimum severity level
	 * @param string $category Error category filter
	 * @return array Array of error log entries
	 */
	public function get_error_logs( $limit = 20, $severity = null, $category = null ) {
		$error_logs = get_option( self::ERROR_LOG_OPTION, array() );
		
		if ( empty( $error_logs ) ) {
			return array();
		}

		// Filter by severity if specified
		if ( $severity && array_key_exists( $severity, self::SEVERITY_LEVELS ) ) {
			$min_severity_level = self::SEVERITY_LEVELS[ $severity ];
			$error_logs = array_filter( $error_logs, function( $entry ) use ( $min_severity_level ) {
				return self::SEVERITY_LEVELS[ $entry['severity'] ] <= $min_severity_level;
			});
		}

		// Filter by category if specified
		if ( $category && array_key_exists( $category, self::ERROR_CATEGORIES ) ) {
			$error_logs = array_filter( $error_logs, function( $entry ) use ( $category ) {
				return $entry['category'] === $category;
			});
		}

		// Sort by timestamp (newest first)
		usort( $error_logs, function( $a, $b ) {
			return $b['timestamp'] - $a['timestamp'];
		});

		// Limit results
		return array_slice( $error_logs, 0, $limit );
	}

	/**
	 * Get error statistics
	 *
	 * @return array Error statistics
	 */
	public function get_error_statistics() {
		return get_option( self::ERROR_STATS_OPTION, array(
			'total_errors' => 0,
			'errors_by_severity' => array(),
			'errors_by_category' => array(),
			'recent_errors' => 0,
			'last_error_time' => 0,
		) );
	}

	/**
	 * Clear error logs
	 *
	 * @param string $severity Optional severity level to clear
	 * @param string $category Optional category to clear
	 * @return bool True on success, false on failure
	 */
	public function clear_error_logs( $severity = null, $category = null ) {
		if ( ! $severity && ! $category ) {
			// Clear all logs
			$result = delete_option( self::ERROR_LOG_OPTION );
			$this->reset_error_statistics();
			return $result;
		}

		$error_logs = get_option( self::ERROR_LOG_OPTION, array() );
		
		if ( empty( $error_logs ) ) {
			return true;
		}

		// Filter out entries matching criteria
		$filtered_logs = array_filter( $error_logs, function( $entry ) use ( $severity, $category ) {
			$keep_entry = true;
			
			if ( $severity && $entry['severity'] === $severity ) {
				$keep_entry = false;
			}
			
			if ( $category && $entry['category'] === $category ) {
				$keep_entry = false;
			}
			
			return $keep_entry;
		});

		$result = update_option( self::ERROR_LOG_OPTION, array_values( $filtered_logs ) );
		
		if ( $result ) {
			$this->recalculate_error_statistics();
		}
		
		return $result;
	}

	/**
	 * Register error recovery callback
	 *
	 * @param string   $error_code Error code to handle
	 * @param callable $callback Recovery callback function
	 * @param int      $priority Callback priority (lower = higher priority)
	 */
	public function register_recovery_callback( $error_code, $callback, $priority = 10 ) {
		if ( ! is_callable( $callback ) ) {
			return;
		}

		if ( ! isset( $this->recovery_callbacks[ $error_code ] ) ) {
			$this->recovery_callbacks[ $error_code ] = array();
		}

		$this->recovery_callbacks[ $error_code ][] = array(
			'callback' => $callback,
			'priority' => $priority,
		);

		// Sort by priority
		usort( $this->recovery_callbacks[ $error_code ], function( $a, $b ) {
			return $a['priority'] - $b['priority'];
		});
	}

	/**
	 * Get user-friendly error message
	 *
	 * @param string|WP_Error $error Error message or WP_Error object
	 * @param string          $context Error context for better messaging
	 * @return string User-friendly error message
	 */
	public function get_user_friendly_message( $error, $context = '' ) {
		$error_data = $this->extract_error_data( $error );
		$error_code = $error_data['code'];
		
		// Map of error codes to user-friendly messages
		$friendly_messages = array(
			'no_converter' => __( 'Image conversion is not available. Please ensure ImageMagick or GD library is installed on your server.', 'wp-image-optimizer' ),
			'conversion_failed' => __( 'Image conversion failed. The image may be corrupted or in an unsupported format.', 'wp-image-optimizer' ),
			'file_not_found' => __( 'The image file could not be found. It may have been moved or deleted.', 'wp-image-optimizer' ),
			'file_too_large' => __( 'The image file is too large to process. Please try with a smaller image.', 'wp-image-optimizer' ),
			'invalid_format' => __( 'The requested image format is not supported.', 'wp-image-optimizer' ),
			'permission_denied' => __( 'Permission denied. Please check file and directory permissions.', 'wp-image-optimizer' ),
			'disk_space' => __( 'Insufficient disk space to complete the operation.', 'wp-image-optimizer' ),
			'memory_limit' => __( 'Not enough memory to process this image. Try increasing PHP memory limit.', 'wp-image-optimizer' ),
		);

		// Return friendly message if available, otherwise return original message
		if ( isset( $friendly_messages[ $error_code ] ) ) {
			return $friendly_messages[ $error_code ];
		}

		// For unknown errors, provide a generic friendly message
		if ( $context ) {
			return sprintf( 
				/* translators: %s: error context */
				__( 'An error occurred during %s. Please try again or contact support if the problem persists.', 'wp-image-optimizer' ), 
				$context 
			);
		}

		return __( 'An unexpected error occurred. Please try again or contact support if the problem persists.', 'wp-image-optimizer' );
	}

	/**
	 * Extract error data from various error types
	 *
	 * @param string|WP_Error $error Error to extract data from
	 * @return array Error data with code, message, and additional data
	 */
	private function extract_error_data( $error ) {
		if ( is_wp_error( $error ) ) {
			return array(
				'code' => $error->get_error_code(),
				'message' => $error->get_error_message(),
				'data' => $error->get_error_data(),
			);
		}

		if ( is_string( $error ) ) {
			return array(
				'code' => 'generic_error',
				'message' => $error,
				'data' => null,
			);
		}

		if ( is_array( $error ) && isset( $error['message'] ) ) {
			return array(
				'code' => isset( $error['code'] ) ? $error['code'] : 'generic_error',
				'message' => $error['message'],
				'data' => isset( $error['data'] ) ? $error['data'] : null,
			);
		}

		return array(
			'code' => 'unknown_error',
			'message' => __( 'An unknown error occurred.', 'wp-image-optimizer' ),
			'data' => $error,
		);
	}

	/**
	 * Generate unique error ID
	 *
	 * @return string Unique error ID
	 */
	private function generate_error_id() {
		return 'wpio_' . uniqid() . '_' . wp_rand( 1000, 9999 );
	}

	/**
	 * Get stack trace for debugging
	 *
	 * @return array Stack trace information
	 */
	private function get_stack_trace() {
		if ( ! WP_DEBUG ) {
			return array();
		}

		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
		$filtered_trace = array();

		foreach ( $trace as $frame ) {
			// Skip internal error handler frames
			if ( isset( $frame['class'] ) && $frame['class'] === __CLASS__ ) {
				continue;
			}

			$filtered_trace[] = array(
				'file' => isset( $frame['file'] ) ? $frame['file'] : 'unknown',
				'line' => isset( $frame['line'] ) ? $frame['line'] : 'unknown',
				'function' => isset( $frame['function'] ) ? $frame['function'] : 'unknown',
				'class' => isset( $frame['class'] ) ? $frame['class'] : null,
			);
		}

		return $filtered_trace;
	}

	/**
	 * Get WordPress debug information
	 *
	 * @return array WordPress debug information
	 */
	private function get_wp_debug_info() {
		return array(
			'wp_version' => get_bloginfo( 'version' ),
			'php_version' => PHP_VERSION,
			'wp_debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'wp_debug_log' => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'memory_limit' => ini_get( 'memory_limit' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
			'post_max_size' => ini_get( 'post_max_size' ),
		);
	}

	/**
	 * Log error to WordPress debug log
	 *
	 * @param array $error_entry Error entry data
	 */
	private function log_to_wp_debug( $error_entry ) {
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		$log_message = sprintf(
			'[WP Image Optimizer] %s - %s: %s (ID: %s)',
			strtoupper( $error_entry['severity'] ),
			self::ERROR_CATEGORIES[ $error_entry['category'] ],
			$error_entry['message'],
			$error_entry['id']
		);

		if ( ! empty( $error_entry['context'] ) ) {
			$log_message .= ' | Context: ' . wp_json_encode( $error_entry['context'] );
		}

		error_log( $log_message );
	}

	/**
	 * Store error in plugin error log
	 *
	 * @param array $error_entry Error entry data
	 */
	private function store_error_log( $error_entry ) {
		$error_logs = get_option( self::ERROR_LOG_OPTION, array() );
		
		// Add new entry
		$error_logs[] = $error_entry;
		
		// Keep only the most recent entries
		if ( count( $error_logs ) > self::MAX_LOG_ENTRIES ) {
			$error_logs = array_slice( $error_logs, -self::MAX_LOG_ENTRIES );
		}
		
		update_option( self::ERROR_LOG_OPTION, $error_logs );
	}

	/**
	 * Initialize error statistics
	 */
	private function init_error_statistics() {
		$stats = get_option( self::ERROR_STATS_OPTION );
		
		if ( false === $stats ) {
			$default_stats = array(
				'total_errors' => 0,
				'errors_by_severity' => array_fill_keys( array_keys( self::SEVERITY_LEVELS ), 0 ),
				'errors_by_category' => array_fill_keys( array_keys( self::ERROR_CATEGORIES ), 0 ),
				'recent_errors' => 0,
				'last_error_time' => 0,
			);
			
			update_option( self::ERROR_STATS_OPTION, $default_stats );
		}
	}

	/**
	 * Update error statistics
	 *
	 * @param array $error_entry Error entry data
	 */
	private function update_error_statistics( $error_entry ) {
		$stats = get_option( self::ERROR_STATS_OPTION, array() );
		
		// Update total errors
		$stats['total_errors'] = isset( $stats['total_errors'] ) ? $stats['total_errors'] + 1 : 1;
		
		// Update errors by severity
		if ( ! isset( $stats['errors_by_severity'] ) ) {
			$stats['errors_by_severity'] = array();
		}
		$severity = $error_entry['severity'];
		$stats['errors_by_severity'][ $severity ] = isset( $stats['errors_by_severity'][ $severity ] ) ? 
			$stats['errors_by_severity'][ $severity ] + 1 : 1;
		
		// Update errors by category
		if ( ! isset( $stats['errors_by_category'] ) ) {
			$stats['errors_by_category'] = array();
		}
		$category = $error_entry['category'];
		$stats['errors_by_category'][ $category ] = isset( $stats['errors_by_category'][ $category ] ) ? 
			$stats['errors_by_category'][ $category ] + 1 : 1;
		
		// Update recent errors (last 24 hours)
		$recent_cutoff = time() - DAY_IN_SECONDS;
		$error_logs = get_option( self::ERROR_LOG_OPTION, array() );
		$stats['recent_errors'] = count( array_filter( $error_logs, function( $entry ) use ( $recent_cutoff ) {
			return $entry['timestamp'] > $recent_cutoff;
		}));
		
		// Update last error time
		$stats['last_error_time'] = $error_entry['timestamp'];
		
		update_option( self::ERROR_STATS_OPTION, $stats );
	}

	/**
	 * Reset error statistics
	 */
	private function reset_error_statistics() {
		$default_stats = array(
			'total_errors' => 0,
			'errors_by_severity' => array_fill_keys( array_keys( self::SEVERITY_LEVELS ), 0 ),
			'errors_by_category' => array_fill_keys( array_keys( self::ERROR_CATEGORIES ), 0 ),
			'recent_errors' => 0,
			'last_error_time' => 0,
		);
		
		update_option( self::ERROR_STATS_OPTION, $default_stats );
	}

	/**
	 * Recalculate error statistics from current logs
	 */
	private function recalculate_error_statistics() {
		$error_logs = get_option( self::ERROR_LOG_OPTION, array() );
		
		$stats = array(
			'total_errors' => count( $error_logs ),
			'errors_by_severity' => array_fill_keys( array_keys( self::SEVERITY_LEVELS ), 0 ),
			'errors_by_category' => array_fill_keys( array_keys( self::ERROR_CATEGORIES ), 0 ),
			'recent_errors' => 0,
			'last_error_time' => 0,
		);
		
		$recent_cutoff = time() - DAY_IN_SECONDS;
		
		foreach ( $error_logs as $entry ) {
			// Count by severity
			if ( isset( $entry['severity'] ) ) {
				$stats['errors_by_severity'][ $entry['severity'] ]++;
			}
			
			// Count by category
			if ( isset( $entry['category'] ) ) {
				$stats['errors_by_category'][ $entry['category'] ]++;
			}
			
			// Count recent errors
			if ( isset( $entry['timestamp'] ) && $entry['timestamp'] > $recent_cutoff ) {
				$stats['recent_errors']++;
			}
			
			// Track last error time
			if ( isset( $entry['timestamp'] ) && $entry['timestamp'] > $stats['last_error_time'] ) {
				$stats['last_error_time'] = $entry['timestamp'];
			}
		}
		
		update_option( self::ERROR_STATS_OPTION, $stats );
	}

	/**
	 * Setup default error recovery callbacks
	 */
	private function setup_recovery_callbacks() {
		// Recovery for converter not available
		$this->register_recovery_callback( 'no_converter', array( $this, 'recover_no_converter' ), 10 );
		
		// Recovery for file permission issues
		$this->register_recovery_callback( 'permission_denied', array( $this, 'recover_permission_denied' ), 10 );
		
		// Recovery for memory limit issues
		$this->register_recovery_callback( 'memory_limit', array( $this, 'recover_memory_limit' ), 10 );
		
		// Recovery for disk space issues
		$this->register_recovery_callback( 'disk_space', array( $this, 'recover_disk_space' ), 10 );
	}

	/**
	 * Attempt error recovery
	 *
	 * @param array $error_entry Error entry data
	 * @return bool True if recovery was attempted, false otherwise
	 */
	private function attempt_error_recovery( $error_entry ) {
		$error_code = $error_entry['code'];
		
		if ( ! isset( $this->recovery_callbacks[ $error_code ] ) ) {
			return false;
		}

		foreach ( $this->recovery_callbacks[ $error_code ] as $callback_data ) {
			try {
				$result = call_user_func( $callback_data['callback'], $error_entry );
				
				if ( $result ) {
					// Log successful recovery
					$this->log_error(
						sprintf( 'Error recovery successful for error ID: %s', $error_entry['id'] ),
						'info',
						'runtime',
						array( 'recovered_error_id' => $error_entry['id'] )
					);
					return true;
				}
			} catch ( Exception $e ) {
				// Log recovery failure
				$this->log_error(
					sprintf( 'Error recovery failed for error ID: %s - %s', $error_entry['id'], $e->getMessage() ),
					'warning',
					'runtime',
					array( 'recovered_error_id' => $error_entry['id'] )
				);
			}
		}

		return false;
	}

	/**
	 * Recovery callback for no converter available
	 *
	 * @param array $error_entry Error entry data
	 * @return bool True if recovery successful
	 */
	public function recover_no_converter( $error_entry ) {
		// Try to reinitialize converter factory
		if ( class_exists( 'Converter_Factory' ) ) {
			$converter = Converter_Factory::get_converter();
			if ( $converter && $converter->is_available() ) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Recovery callback for permission denied errors
	 *
	 * @param array $error_entry Error entry data
	 * @return bool True if recovery successful
	 */
	public function recover_permission_denied( $error_entry ) {
		// This would typically require manual intervention
		// Log suggestion for admin
		$this->log_error(
			__( 'Permission denied error detected. Please check file and directory permissions for the WordPress uploads directory.', 'wp-image-optimizer' ),
			'notice',
			'system',
			array( 'recovery_suggestion' => true )
		);
		
		return false;
	}

	/**
	 * Recovery callback for memory limit errors
	 *
	 * @param array $error_entry Error entry data
	 * @return bool True if recovery successful
	 */
	public function recover_memory_limit( $error_entry ) {
		// Try to increase memory limit temporarily
		$current_limit = ini_get( 'memory_limit' );
		$current_bytes = wp_convert_hr_to_bytes( $current_limit );
		$new_limit = $current_bytes * 2;
		
		if ( $new_limit <= wp_convert_hr_to_bytes( '512M' ) ) {
			ini_set( 'memory_limit', size_format( $new_limit, 0 ) );
			return true;
		}
		
		return false;
	}

	/**
	 * Recovery callback for disk space errors
	 *
	 * @param array $error_entry Error entry data
	 * @return bool True if recovery successful
	 */
	public function recover_disk_space( $error_entry ) {
		// Try to clean up temporary files
		$upload_dir = wp_upload_dir();
		$temp_files_cleaned = 0;
		
		// This is a simplified cleanup - in practice, you'd want more sophisticated cleanup
		$temp_patterns = array( '*.tmp', '*.temp', '*~' );
		
		foreach ( $temp_patterns as $pattern ) {
			$files = glob( $upload_dir['basedir'] . '/' . $pattern );
			foreach ( $files as $file ) {
				if ( is_file( $file ) && time() - filemtime( $file ) > 3600 ) { // Older than 1 hour
					if ( unlink( $file ) ) {
						$temp_files_cleaned++;
					}
				}
			}
		}
		
		return $temp_files_cleaned > 0;
	}

	/**
	 * Send admin notification for critical errors
	 *
	 * @param string $error_id Error ID
	 */
	private function send_admin_notification( $error_id ) {
		// Only send notifications if not in development mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return;
		}

		// Throttle notifications to prevent spam
		$last_notification = get_transient( 'wp_image_optimizer_last_notification' );
		if ( $last_notification && ( time() - $last_notification ) < 3600 ) { // 1 hour throttle
			return;
		}

		$admin_email = get_option( 'admin_email' );
		if ( ! $admin_email ) {
			return;
		}

		$subject = sprintf( 
			/* translators: %s: site name */
			__( '[%s] WP Image Optimizer Critical Error', 'wp-image-optimizer' ), 
			get_bloginfo( 'name' ) 
		);

		$message = sprintf(
			/* translators: %1$s: error ID, %2$s: site URL */
			__( "A critical error has occurred in WP Image Optimizer.\n\nError ID: %1$s\n\nPlease check your WordPress admin dashboard for more details.\n\nSite: %2$s", 'wp-image-optimizer' ),
			$error_id,
			home_url()
		);

		wp_mail( $admin_email, $subject, $message );
		
		// Set throttle
		set_transient( 'wp_image_optimizer_last_notification', time(), 3600 );
	}
}