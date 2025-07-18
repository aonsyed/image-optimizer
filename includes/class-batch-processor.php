<?php
/**
 * Batch Processor class
 *
 * Handles batch processing and background operations for image conversion
 * with WordPress cron integration, queue management, and progress tracking.
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Batch Processor class
 */
class WP_Image_Optimizer_Batch_Processor {

	/**
	 * Queue option name
	 *
	 * @var string
	 */
	const QUEUE_OPTION = 'wp_image_optimizer_batch_queue';

	/**
	 * Progress option name
	 *
	 * @var string
	 */
	const PROGRESS_OPTION = 'wp_image_optimizer_batch_progress';

	/**
	 * Cron hook name
	 *
	 * @var string
	 */
	const CRON_HOOK = 'wp_image_optimizer_batch_process';

	/**
	 * Maximum items to process per batch
	 *
	 * @var int
	 */
	const BATCH_SIZE = 10;

	/**
	 * Maximum execution time per batch (seconds)
	 *
	 * @var int
	 */
	const MAX_EXECUTION_TIME = 25;

	/**
	 * Memory limit threshold (percentage)
	 *
	 * @var float
	 */
	const MEMORY_THRESHOLD = 0.8;

	/**
	 * Maximum retry attempts for failed conversions
	 *
	 * @var int
	 */
	const MAX_RETRY_ATTEMPTS = 3;

	/**
	 * Priority levels for queue items
	 */
	const PRIORITY_HIGH = 1;
	const PRIORITY_NORMAL = 2;
	const PRIORITY_LOW = 3;

	/**
	 * Image converter instance
	 *
	 * @var WP_Image_Optimizer_Image_Converter
	 */
	private $image_converter;

	/**
	 * Settings manager instance
	 *
	 * @var WP_Image_Optimizer_Settings_Manager
	 */
	private $settings_manager;

	/**
	 * Error handler instance
	 *
	 * @var WP_Image_Optimizer_Error_Handler
	 */
	private $error_handler;

	/**
	 * File handler instance
	 *
	 * @var WP_Image_Optimizer_File_Handler
	 */
	private $file_handler;

	/**
	 * Batch start time
	 *
	 * @var float
	 */
	private $batch_start_time;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings_manager = new WP_Image_Optimizer_Settings_Manager();
		$this->error_handler = WP_Image_Optimizer_Error_Handler::get_instance();
		
		$settings = $this->settings_manager->get_settings();
		$this->file_handler = new WP_Image_Optimizer_File_Handler( $settings );
		$this->image_converter = new WP_Image_Optimizer_Image_Converter( $this->file_handler );
		
		$this->error_handler->set_error_context( array(
			'component' => 'batch_processor',
		) );

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Register cron hook
		add_action( self::CRON_HOOK, array( $this, 'process_batch' ) );
		
		// Add custom cron schedule if not exists
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
		
		// Clean up on plugin deactivation
		register_deactivation_hook( WP_IMAGE_OPTIMIZER_PLUGIN_FILE, array( $this, 'cleanup_on_deactivation' ) );
	}

	/**
	 * Add custom cron schedules
	 *
	 * @param array $schedules Existing schedules
	 * @return array Modified schedules
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['wp_image_optimizer_batch'] = array(
			'interval' => 60, // 1 minute
			'display' => __( 'WP Image Optimizer Batch Processing', 'wp-image-optimizer' ),
		);
		
		return $schedules;
	}

	/**
	 * Start batch conversion for all images
	 *
	 * @param array $options Batch options
	 * @return bool|WP_Error True on success, error on failure
	 */
	public function start_batch_conversion( $options = array() ) {
		$this->error_handler->add_error_context( 'method', 'start_batch_conversion' );
		
		// Check if batch is already running
		if ( $this->is_batch_running() ) {
			$error = new WP_Error( 
				'batch_already_running', 
				__( 'Batch conversion is already running.', 'wp-image-optimizer' ) 
			);
			$this->error_handler->log_error( $error, 'warning', 'batch_processing' );
			return $error;
		}

		// Default options
		$options = wp_parse_args( $options, array(
			'format' => null, // null for all formats, or specific format
			'force' => false, // force reconversion
			'limit' => 0, // 0 for no limit
			'offset' => 0,
			'attachment_ids' => array(), // specific attachment IDs, empty for all
		) );

		// Build queue
		$queue = $this->build_conversion_queue( $options );
		if ( is_wp_error( $queue ) ) {
			$this->error_handler->log_error( $queue, 'error', 'batch_processing', array( 'options' => $options ) );
			return $queue;
		}

		if ( empty( $queue ) ) {
			$error = new WP_Error( 
				'empty_queue', 
				__( 'No images found to convert.', 'wp-image-optimizer' ) 
			);
			$this->error_handler->log_error( $error, 'info', 'batch_processing', array( 'options' => $options ) );
			return $error;
		}

		// Initialize batch progress
		$progress_data = array(
			'status' => 'running',
			'total' => count( $queue ),
			'processed' => 0,
			'successful' => 0,
			'failed' => 0,
			'skipped' => 0,
			'space_saved' => 0,
			'start_time' => time(),
			'end_time' => null,
			'options' => $options,
			'errors' => array(),
		);

		// Store queue and progress
		update_option( self::QUEUE_OPTION, $queue );
		update_option( self::PROGRESS_OPTION, $progress_data );

		// Schedule cron job
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'wp_image_optimizer_batch', self::CRON_HOOK );
		}

		$this->error_handler->log_error(
			sprintf( 'Batch conversion started with %d images in queue', count( $queue ) ),
			'info',
			'batch_processing',
			array( 
				'queue_size' => count( $queue ),
				'options' => $options,
			)
		);

		return true;
	}

	/**
	 * Process a batch of images
	 */
	public function process_batch() {
		$this->batch_start_time = microtime( true );
		$this->error_handler->add_error_context( 'method', 'process_batch' );
		
		// Check if batch is running
		if ( ! $this->is_batch_running() ) {
			return;
		}

		// Get queue and progress
		$queue = get_option( self::QUEUE_OPTION, array() );
		$progress = get_option( self::PROGRESS_OPTION, array() );

		if ( empty( $queue ) ) {
			$this->complete_batch();
			return;
		}

		// Process items in batch
		$processed_count = 0;
		$batch_errors = array();

		while ( ! empty( $queue ) && $processed_count < self::BATCH_SIZE && $this->can_continue_processing() ) {
			$item = array_shift( $queue );
			
			// Check if item is scheduled for retry and not ready yet
			if ( isset( $item['retry_after'] ) && $item['retry_after'] > time() ) {
				// Put item back at end of queue and continue
				$queue[] = $item;
				continue;
			}
			
			$result = $this->process_queue_item( $item );
			
			$progress['processed']++;
			$processed_count++;

			if ( is_wp_error( $result ) ) {
				$progress['failed']++;
				$batch_errors[] = array(
					'item' => $item,
					'error' => $result->get_error_message(),
					'time' => time(),
				);
				
				$this->error_handler->log_error( $result, 'error', 'batch_processing', array(
					'queue_item' => $item,
					'batch_progress' => $progress['processed'] . '/' . $progress['total'],
				) );
			} elseif ( isset( $result['skipped'] ) && $result['skipped'] ) {
				$progress['skipped']++;
			} else {
				$progress['successful']++;
				if ( isset( $result['space_saved'] ) ) {
					$progress['space_saved'] += $result['space_saved'];
				}
			}

			// Update progress periodically
			if ( $processed_count % 5 === 0 ) {
				$this->update_batch_progress( $progress, $batch_errors );
			}
		}

		// Store batch errors
		if ( ! empty( $batch_errors ) ) {
			$progress['errors'] = array_merge( 
				isset( $progress['errors'] ) ? $progress['errors'] : array(), 
				$batch_errors 
			);
		}

		// Update queue and progress
		update_option( self::QUEUE_OPTION, $queue );
		$this->update_batch_progress( $progress );

		// Complete batch if queue is empty
		if ( empty( $queue ) ) {
			$this->complete_batch();
		}

		$this->error_handler->log_error(
			sprintf( 'Processed batch of %d items. Queue remaining: %d', $processed_count, count( $queue ) ),
			'info',
			'batch_processing',
			array( 
				'processed_in_batch' => $processed_count,
				'queue_remaining' => count( $queue ),
				'execution_time' => microtime( true ) - $this->batch_start_time,
			)
		);
	}

	/**
	 * Process a single queue item
	 *
	 * @param array $item Queue item
	 * @return array|WP_Error Processing result
	 */
	private function process_queue_item( $item ) {
		$this->error_handler->add_error_context( 'queue_item_id', $item['attachment_id'] );
		
		// Get attachment file path
		$file_path = get_attached_file( $item['attachment_id'] );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new WP_Error( 
				'file_not_found', 
				sprintf( 'File not found for attachment %d', $item['attachment_id'] ) 
			);
		}

		// Check if already converted (unless forcing)
		if ( ! $item['force'] ) {
			$existing_versions = $this->image_converter->check_converted_versions( $file_path );
			$needs_conversion = false;
			
			if ( $item['format'] ) {
				$needs_conversion = ! $existing_versions[ $item['format'] ]['exists'];
			} else {
				foreach ( $existing_versions as $format => $info ) {
					if ( $info['enabled'] && ! $info['exists'] ) {
						$needs_conversion = true;
						break;
					}
				}
			}
			
			if ( ! $needs_conversion ) {
				return array( 'skipped' => true, 'reason' => 'already_converted' );
			}
		}

		// Perform conversion with retry logic
		$result = $this->attempt_conversion_with_retry( $item, $file_path );
		
		return $result;
	}

	/**
	 * Attempt conversion with retry mechanism
	 *
	 * @param array  $item Queue item
	 * @param string $file_path File path to convert
	 * @return array|WP_Error Processing result
	 */
	private function attempt_conversion_with_retry( $item, $file_path ) {
		$last_error = null;
		
		// Perform conversion
		if ( $item['format'] ) {
			// Convert to specific format
			$converted_path = $this->image_converter->convert_on_demand( $file_path, $item['format'] );
			if ( is_wp_error( $converted_path ) ) {
				$last_error = $converted_path;
			} else {
				// Calculate space saved
				$original_size = filesize( $file_path );
				$converted_size = filesize( $converted_path );
				$space_saved = $original_size - $converted_size;
				
				return array(
					'format' => $item['format'],
					'converted_path' => $converted_path,
					'space_saved' => max( 0, $space_saved ),
				);
			}
		} else {
			// Convert to all enabled formats
			$result = $this->image_converter->convert_image( $file_path );
			if ( is_wp_error( $result ) ) {
				$last_error = $result;
			} else {
				return $result;
			}
		}
		
		// If we get here, conversion failed
		if ( $last_error && $this->should_retry_item( $item, $last_error ) ) {
			// Add item back to queue for retry
			$this->add_item_for_retry( $item, $last_error );
			return array( 'skipped' => true, 'reason' => 'queued_for_retry' );
		}
		
		return $last_error ? $last_error : new WP_Error( 'conversion_failed', 'Unknown conversion error' );
	}

	/**
	 * Check if an item should be retried
	 *
	 * @param array    $item Queue item
	 * @param WP_Error $error Last error
	 * @return bool True if should retry, false otherwise
	 */
	private function should_retry_item( $item, $error ) {
		// Don't retry if we've already reached max attempts
		if ( $item['retry_count'] >= self::MAX_RETRY_ATTEMPTS ) {
			return false;
		}
		
		// Don't retry certain types of errors
		$non_retryable_errors = array(
			'file_not_found',
			'invalid_file_type',
			'file_too_large',
			'permission_denied',
		);
		
		if ( in_array( $error->get_error_code(), $non_retryable_errors, true ) ) {
			return false;
		}
		
		return true;
	}

	/**
	 * Add item back to queue for retry
	 *
	 * @param array    $item Queue item
	 * @param WP_Error $error Last error
	 */
	private function add_item_for_retry( $item, $error ) {
		$queue = get_option( self::QUEUE_OPTION, array() );
		
		// Increment retry count and add delay
		$item['retry_count']++;
		$item['retry_after'] = time() + ( $item['retry_count'] * 60 ); // Exponential backoff: 1min, 2min, 3min
		$item['last_error'] = $error->get_error_message();
		
		// Add to end of queue with lower priority for retries
		$item['priority'] = self::PRIORITY_LOW;
		$queue[] = $item;
		
		update_option( self::QUEUE_OPTION, $queue );
		
		$this->error_handler->log_error(
			sprintf( 'Item %d queued for retry (attempt %d/%d): %s', 
				$item['attachment_id'], 
				$item['retry_count'], 
				self::MAX_RETRY_ATTEMPTS,
				$error->get_error_message()
			),
			'info',
			'batch_processing',
			array( 'retry_item' => $item )
		);
	}

	/**
	 * Build conversion queue based on options
	 *
	 * @param array $options Queue options
	 * @return array|WP_Error Queue items or error
	 */
	private function build_conversion_queue( $options ) {
		$queue = array();

		if ( ! empty( $options['attachment_ids'] ) ) {
			// Process specific attachment IDs
			foreach ( $options['attachment_ids'] as $attachment_id ) {
				if ( wp_attachment_is_image( $attachment_id ) ) {
					$queue[] = array(
						'attachment_id' => $attachment_id,
						'format' => $options['format'],
						'force' => $options['force'],
						'priority' => isset( $options['priority'] ) ? $options['priority'] : self::PRIORITY_NORMAL,
						'retry_count' => 0,
						'created_time' => time(),
					);
				}
			}
		} else {
			// Get all image attachments
			$query_args = array(
				'post_type' => 'attachment',
				'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif' ),
				'post_status' => 'inherit',
				'posts_per_page' => $options['limit'] > 0 ? $options['limit'] : -1,
				'offset' => $options['offset'],
				'fields' => 'ids',
			);

			$attachments = get_posts( $query_args );
			
			foreach ( $attachments as $attachment_id ) {
				// Determine priority based on file size (smaller files get higher priority for faster initial progress)
				$priority = $this->determine_item_priority( $attachment_id );
				
				$queue[] = array(
					'attachment_id' => $attachment_id,
					'format' => $options['format'],
					'force' => $options['force'],
					'priority' => $priority,
					'retry_count' => 0,
					'created_time' => time(),
				);
			}
		}

		// Sort queue by priority (lower number = higher priority)
		usort( $queue, function( $a, $b ) {
			if ( $a['priority'] === $b['priority'] ) {
				// Same priority, sort by creation time (FIFO)
				return $a['created_time'] - $b['created_time'];
			}
			return $a['priority'] - $b['priority'];
		} );

		return $queue;
	}

	/**
	 * Determine priority for a queue item based on file characteristics
	 *
	 * @param int $attachment_id Attachment ID
	 * @return int Priority level
	 */
	private function determine_item_priority( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return self::PRIORITY_LOW;
		}

		$file_size = filesize( $file_path );
		
		// Prioritize smaller files for faster initial progress feedback
		if ( $file_size < 500 * 1024 ) { // Less than 500KB
			return self::PRIORITY_HIGH;
		} elseif ( $file_size < 2 * 1024 * 1024 ) { // Less than 2MB
			return self::PRIORITY_NORMAL;
		} else {
			return self::PRIORITY_LOW;
		}
	}

	/**
	 * Check if batch processing can continue
	 *
	 * @return bool True if can continue, false otherwise
	 */
	private function can_continue_processing() {
		// Check execution time
		$elapsed_time = microtime( true ) - $this->batch_start_time;
		if ( $elapsed_time >= self::MAX_EXECUTION_TIME ) {
			return false;
		}

		// Check memory usage
		$memory_limit = $this->get_memory_limit();
		$memory_usage = memory_get_usage( true );
		
		if ( $memory_limit > 0 && ( $memory_usage / $memory_limit ) >= self::MEMORY_THRESHOLD ) {
			$this->error_handler->log_error(
				'Batch processing stopped due to memory threshold',
				'warning',
				'batch_processing',
				array(
					'memory_usage' => $memory_usage,
					'memory_limit' => $memory_limit,
					'threshold' => self::MEMORY_THRESHOLD,
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Get PHP memory limit in bytes
	 *
	 * @return int Memory limit in bytes, 0 if unlimited
	 */
	private function get_memory_limit() {
		$memory_limit = ini_get( 'memory_limit' );
		
		if ( '-1' === $memory_limit ) {
			return 0; // Unlimited
		}
		
		$unit = strtolower( substr( $memory_limit, -1 ) );
		$value = (int) $memory_limit;
		
		switch ( $unit ) {
			case 'g':
				$value *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$value *= 1024 * 1024;
				break;
			case 'k':
				$value *= 1024;
				break;
		}
		
		return $value;
	}

	/**
	 * Complete batch processing
	 */
	private function complete_batch() {
		$progress = get_option( self::PROGRESS_OPTION, array() );
		
		$progress['status'] = 'completed';
		$progress['end_time'] = time();
		
		// Clear queue
		delete_option( self::QUEUE_OPTION );
		
		// Update final progress
		update_option( self::PROGRESS_OPTION, $progress );
		
		// Clear scheduled cron
		wp_clear_scheduled_hook( self::CRON_HOOK );
		
		$this->error_handler->log_error(
			sprintf( 
				'Batch conversion completed. Total: %d, Successful: %d, Failed: %d, Skipped: %d, Space saved: %s',
				$progress['total'],
				$progress['successful'],
				$progress['failed'],
				$progress['skipped'],
				$this->format_bytes( $progress['space_saved'] )
			),
			'info',
			'batch_processing',
			array( 'final_progress' => $progress )
		);

		// Trigger completion action
		do_action( 'wp_image_optimizer_batch_completed', $progress );
	}

	/**
	 * Cancel batch processing
	 *
	 * @return bool True on success, false on failure
	 */
	public function cancel_batch() {
		$this->error_handler->add_error_context( 'method', 'cancel_batch' );
		
		if ( ! $this->is_batch_running() ) {
			return false;
		}

		$progress = get_option( self::PROGRESS_OPTION, array() );
		$progress['status'] = 'cancelled';
		$progress['end_time'] = time();
		
		// Clear queue and cron
		delete_option( self::QUEUE_OPTION );
		wp_clear_scheduled_hook( self::CRON_HOOK );
		
		// Update progress
		update_option( self::PROGRESS_OPTION, $progress );
		
		$this->error_handler->log_error(
			'Batch conversion cancelled by user',
			'info',
			'batch_processing',
			array( 'cancelled_progress' => $progress )
		);

		// Trigger cancellation action
		do_action( 'wp_image_optimizer_batch_cancelled', $progress );
		
		return true;
	}

	/**
	 * Check if batch is currently running
	 *
	 * @return bool True if running, false otherwise
	 */
	public function is_batch_running() {
		$progress = get_option( self::PROGRESS_OPTION, array() );
		return isset( $progress['status'] ) && 'running' === $progress['status'];
	}

	/**
	 * Get batch progress
	 *
	 * @return array|false Progress data or false if no batch
	 */
	public function get_batch_progress() {
		$progress = get_option( self::PROGRESS_OPTION, false );
		
		if ( $progress && isset( $progress['status'] ) ) {
			// Calculate percentage
			$progress['percentage'] = $progress['total'] > 0 ? 
				round( ( $progress['processed'] / $progress['total'] ) * 100, 2 ) : 0;
			
			// Calculate estimated time remaining
			if ( 'running' === $progress['status'] && $progress['processed'] > 0 ) {
				$elapsed_time = time() - $progress['start_time'];
				$avg_time_per_item = $elapsed_time / $progress['processed'];
				$remaining_items = $progress['total'] - $progress['processed'];
				$progress['estimated_time_remaining'] = round( $remaining_items * $avg_time_per_item );
			}
		}
		
		return $progress;
	}

	/**
	 * Update batch progress
	 *
	 * @param array $progress Progress data
	 * @param array $new_errors New errors to add
	 */
	private function update_batch_progress( $progress, $new_errors = array() ) {
		if ( ! empty( $new_errors ) ) {
			$progress['errors'] = array_merge( 
				isset( $progress['errors'] ) ? $progress['errors'] : array(), 
				$new_errors 
			);
		}
		
		update_option( self::PROGRESS_OPTION, $progress );
		
		// Trigger progress update action
		do_action( 'wp_image_optimizer_batch_progress_updated', $progress );
	}

	/**
	 * Clean up temporary files and failed operations
	 *
	 * @return array Cleanup results
	 */
	public function cleanup_temporary_files() {
		$this->error_handler->add_error_context( 'method', 'cleanup_temporary_files' );
		
		$cleanup_results = array(
			'temp_files_deleted' => 0,
			'failed_conversions_cleaned' => 0,
			'orphaned_files_deleted' => 0,
			'errors' => array(),
		);

		// Clean up WordPress upload temporary files
		$upload_dir = wp_upload_dir();
		$temp_patterns = array(
			$upload_dir['basedir'] . '/**/wp-image-optimizer-temp-*',
			$upload_dir['basedir'] . '/**/*.tmp',
		);

		foreach ( $temp_patterns as $pattern ) {
			$temp_files = glob( $pattern, GLOB_BRACE );
			if ( $temp_files ) {
				foreach ( $temp_files as $temp_file ) {
					if ( is_file( $temp_file ) && is_writable( $temp_file ) ) {
						// Check if file is older than 1 hour
						if ( filemtime( $temp_file ) < ( time() - HOUR_IN_SECONDS ) ) {
							if ( unlink( $temp_file ) ) {
								$cleanup_results['temp_files_deleted']++;
							} else {
								$cleanup_results['errors'][] = sprintf( 'Failed to delete temp file: %s', $temp_file );
							}
						}
					}
				}
			}
		}

		// Clean up orphaned converted files (converted files without original)
		$this->cleanup_orphaned_converted_files( $cleanup_results );

		// Clean up failed conversion metadata
		$this->cleanup_failed_conversion_metadata( $cleanup_results );

		$this->error_handler->log_error(
			sprintf( 
				'Cleanup completed. Temp files: %d, Failed conversions: %d, Orphaned files: %d',
				$cleanup_results['temp_files_deleted'],
				$cleanup_results['failed_conversions_cleaned'],
				$cleanup_results['orphaned_files_deleted']
			),
			'info',
			'cleanup',
			array( 'cleanup_results' => $cleanup_results )
		);

		return $cleanup_results;
	}

	/**
	 * Clean up orphaned converted files
	 *
	 * @param array &$cleanup_results Cleanup results array (passed by reference)
	 */
	private function cleanup_orphaned_converted_files( &$cleanup_results ) {
		$upload_dir = wp_upload_dir();
		$converted_patterns = array(
			$upload_dir['basedir'] . '/**/*.webp',
			$upload_dir['basedir'] . '/**/*.avif',
		);

		foreach ( $converted_patterns as $pattern ) {
			$converted_files = glob( $pattern, GLOB_BRACE );
			if ( $converted_files ) {
				foreach ( $converted_files as $converted_file ) {
					// Check if original file exists
					$original_file = $this->get_original_file_from_converted( $converted_file );
					
					if ( $original_file && ! file_exists( $original_file ) ) {
						// Original doesn't exist, this is orphaned
						if ( is_writable( $converted_file ) && unlink( $converted_file ) ) {
							$cleanup_results['orphaned_files_deleted']++;
						} else {
							$cleanup_results['errors'][] = sprintf( 'Failed to delete orphaned file: %s', $converted_file );
						}
					}
				}
			}
		}
	}

	/**
	 * Get original file path from converted file path
	 *
	 * @param string $converted_file Path to converted file
	 * @return string|null Original file path or null if not determinable
	 */
	private function get_original_file_from_converted( $converted_file ) {
		$path_info = pathinfo( $converted_file );
		$original_extensions = array( 'jpg', 'jpeg', 'png', 'gif' );
		
		foreach ( $original_extensions as $ext ) {
			$original_file = $path_info['dirname'] . '/' . $path_info['filename'] . '.' . $ext;
			if ( file_exists( $original_file ) ) {
				return $original_file;
			}
		}
		
		return null;
	}

	/**
	 * Clean up failed conversion metadata
	 *
	 * @param array &$cleanup_results Cleanup results array (passed by reference)
	 */
	private function cleanup_failed_conversion_metadata( &$cleanup_results ) {
		global $wpdb;
		
		// Clean up old conversion metadata (older than 30 days)
		$old_metadata = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_id, meta_value FROM {$wpdb->postmeta} 
			WHERE meta_key = '_wp_image_optimizer_conversions' 
			AND meta_value LIKE %s",
			'%failed%'
		) );

		foreach ( $old_metadata as $meta ) {
			$metadata = maybe_unserialize( $meta->meta_value );
			if ( is_array( $metadata ) ) {
				$cleaned_metadata = array();
				$has_changes = false;
				
				foreach ( $metadata as $timestamp => $data ) {
					// Remove entries older than 30 days
					if ( $timestamp < ( time() - ( 30 * DAY_IN_SECONDS ) ) ) {
						$has_changes = true;
					} else {
						$cleaned_metadata[ $timestamp ] = $data;
					}
				}
				
				if ( $has_changes ) {
					if ( empty( $cleaned_metadata ) ) {
						delete_post_meta( $meta->post_id, '_wp_image_optimizer_conversions' );
					} else {
						update_post_meta( $meta->post_id, '_wp_image_optimizer_conversions', $cleaned_metadata );
					}
					$cleanup_results['failed_conversions_cleaned']++;
				}
			}
		}
	}

	/**
	 * Clean up on plugin deactivation
	 */
	public function cleanup_on_deactivation() {
		// Cancel any running batch
		$this->cancel_batch();
		
		// Clear scheduled events
		wp_clear_scheduled_hook( self::CRON_HOOK );
		
		// Clean up temporary files
		$this->cleanup_temporary_files();
	}

	/**
	 * Format bytes into human readable format
	 *
	 * @param int $bytes Number of bytes
	 * @return string Formatted string
	 */
	private function format_bytes( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		
		for ( $i = 0; $bytes >= 1024 && $i < count( $units ) - 1; $i++ ) {
			$bytes /= 1024;
		}
		
		return round( $bytes, 2 ) . ' ' . $units[ $i ];
	}

	/**
	 * Get queue status
	 *
	 * @return array Queue status information
	 */
	public function get_queue_status() {
		$queue = get_option( self::QUEUE_OPTION, array() );
		$progress = $this->get_batch_progress();
		
		// Analyze queue composition
		$queue_analysis = $this->analyze_queue_composition( $queue );
		
		return array(
			'queue_size' => count( $queue ),
			'is_running' => $this->is_batch_running(),
			'progress' => $progress,
			'next_scheduled' => wp_next_scheduled( self::CRON_HOOK ),
			'queue_analysis' => $queue_analysis,
		);
	}

	/**
	 * Analyze queue composition for detailed reporting
	 *
	 * @param array $queue Current queue
	 * @return array Queue analysis data
	 */
	private function analyze_queue_composition( $queue ) {
		$analysis = array(
			'priority_breakdown' => array(
				'high' => 0,
				'normal' => 0,
				'low' => 0,
			),
			'retry_breakdown' => array(
				'first_attempt' => 0,
				'retries' => 0,
			),
			'format_breakdown' => array(),
			'estimated_processing_time' => 0,
		);

		foreach ( $queue as $item ) {
			// Priority breakdown
			switch ( $item['priority'] ) {
				case self::PRIORITY_HIGH:
					$analysis['priority_breakdown']['high']++;
					break;
				case self::PRIORITY_NORMAL:
					$analysis['priority_breakdown']['normal']++;
					break;
				case self::PRIORITY_LOW:
					$analysis['priority_breakdown']['low']++;
					break;
			}

			// Retry breakdown
			if ( $item['retry_count'] > 0 ) {
				$analysis['retry_breakdown']['retries']++;
			} else {
				$analysis['retry_breakdown']['first_attempt']++;
			}

			// Format breakdown
			$format = $item['format'] ? $item['format'] : 'all';
			if ( ! isset( $analysis['format_breakdown'][ $format ] ) ) {
				$analysis['format_breakdown'][ $format ] = 0;
			}
			$analysis['format_breakdown'][ $format ]++;

			// Estimate processing time (rough estimate based on priority)
			switch ( $item['priority'] ) {
				case self::PRIORITY_HIGH:
					$analysis['estimated_processing_time'] += 2; // 2 seconds for small files
					break;
				case self::PRIORITY_NORMAL:
					$analysis['estimated_processing_time'] += 5; // 5 seconds for medium files
					break;
				case self::PRIORITY_LOW:
					$analysis['estimated_processing_time'] += 10; // 10 seconds for large files
					break;
			}
		}

		return $analysis;
	}

	/**
	 * Get detailed batch statistics
	 *
	 * @return array Detailed statistics
	 */
	public function get_detailed_statistics() {
		$progress = $this->get_batch_progress();
		$queue_status = $this->get_queue_status();
		
		if ( ! $progress ) {
			return array(
				'status' => 'no_batch',
				'message' => 'No batch processing has been run recently.',
			);
		}

		$stats = array(
			'batch_info' => array(
				'status' => $progress['status'],
				'total_items' => $progress['total'],
				'processed_items' => $progress['processed'],
				'success_rate' => $progress['total'] > 0 ? round( ( $progress['successful'] / $progress['total'] ) * 100, 2 ) : 0,
				'completion_percentage' => $progress['percentage'],
			),
			'performance_metrics' => array(),
			'error_analysis' => array(),
			'queue_info' => $queue_status['queue_analysis'],
		);

		// Performance metrics
		if ( $progress['start_time'] ) {
			$elapsed_time = ( $progress['end_time'] ? $progress['end_time'] : time() ) - $progress['start_time'];
			$stats['performance_metrics'] = array(
				'elapsed_time' => $elapsed_time,
				'items_per_minute' => $progress['processed'] > 0 && $elapsed_time > 0 ? 
					round( ( $progress['processed'] / $elapsed_time ) * 60, 2 ) : 0,
				'average_time_per_item' => $progress['processed'] > 0 ? 
					round( $elapsed_time / $progress['processed'], 2 ) : 0,
				'estimated_completion' => isset( $progress['estimated_time_remaining'] ) ? 
					$progress['estimated_time_remaining'] : null,
			);
		}

		// Error analysis
		if ( ! empty( $progress['errors'] ) ) {
			$error_types = array();
			foreach ( $progress['errors'] as $error ) {
				$error_key = $this->categorize_error( $error['error'] );
				if ( ! isset( $error_types[ $error_key ] ) ) {
					$error_types[ $error_key ] = 0;
				}
				$error_types[ $error_key ]++;
			}
			
			$stats['error_analysis'] = array(
				'total_errors' => count( $progress['errors'] ),
				'error_types' => $error_types,
				'error_rate' => $progress['total'] > 0 ? 
					round( ( count( $progress['errors'] ) / $progress['total'] ) * 100, 2 ) : 0,
			);
		}

		return $stats;
	}

	/**
	 * Categorize error for analysis
	 *
	 * @param string $error_message Error message
	 * @return string Error category
	 */
	private function categorize_error( $error_message ) {
		$error_message = strtolower( $error_message );
		
		if ( strpos( $error_message, 'memory' ) !== false ) {
			return 'memory_issues';
		} elseif ( strpos( $error_message, 'file not found' ) !== false ) {
			return 'file_not_found';
		} elseif ( strpos( $error_message, 'permission' ) !== false ) {
			return 'permission_issues';
		} elseif ( strpos( $error_message, 'conversion' ) !== false ) {
			return 'conversion_failures';
		} elseif ( strpos( $error_message, 'timeout' ) !== false ) {
			return 'timeout_issues';
		} else {
			return 'other';
		}
	}

	/**
	 * Force process next batch (for testing/debugging)
	 *
	 * @return bool True if batch was processed, false otherwise
	 */
	public function force_process_batch() {
		if ( ! $this->is_batch_running() ) {
			return false;
		}
		
		$this->process_batch();
		return true;
	}
}