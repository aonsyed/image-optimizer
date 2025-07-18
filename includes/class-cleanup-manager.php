<?php
/**
 * Cleanup Manager class for handling file cleanup operations
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cleanup Manager class
 * 
 * Handles cleanup operations for orphaned files, temporary files,
 * and other maintenance tasks.
 */
class WP_Image_Optimizer_Cleanup_Manager {

	/**
	 * File handler instance
	 *
	 * @var WP_Image_Optimizer_File_Handler
	 */
	private $file_handler;

	/**
	 * Error handler instance
	 *
	 * @var WP_Image_Optimizer_Error_Handler
	 */
	private $error_handler;

	/**
	 * Security validator instance
	 *
	 * @var WP_Image_Optimizer_Security_Validator
	 */
	private $security_validator;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->file_handler = new WP_Image_Optimizer_File_Handler();
		$this->error_handler = WP_Image_Optimizer_Error_Handler::get_instance();
		$this->security_validator = WP_Image_Optimizer_Security_Validator::get_instance();
	}

	/**
	 * Clean up orphaned converted files
	 *
	 * @param bool $dry_run Whether to perform a dry run (don't actually delete files)
	 * @return array|WP_Error Results of cleanup operation
	 */
	public function cleanup_orphaned_files( $dry_run = false ) {
		// Get WordPress upload directory
		$upload_dir = wp_upload_dir();
		$base_dir = $upload_dir['basedir'];
		
		// Set context for error logging
		$this->error_handler->set_error_context( array(
			'operation' => 'cleanup_orphaned_files',
			'dry_run' => $dry_run,
		) );
		
		try {
			$results = array(
				'scanned' => 0,
				'orphaned' => 0,
				'deleted' => 0,
				'failed' => 0,
				'files' => array(),
			);
			
			// Get all image attachments
			$attachments = $this->get_all_image_attachments();
			$results['scanned'] = count( $attachments );
			
			// Process each attachment
			foreach ( $attachments as $attachment_id ) {
				$file_path = get_attached_file( $attachment_id );
				
				if ( ! $file_path || ! file_exists( $file_path ) ) {
					// Original file doesn't exist, check for orphaned converted files
					$meta = wp_get_attachment_metadata( $attachment_id );
					
					if ( isset( $meta['file'] ) ) {
						$original_path = $base_dir . '/' . $meta['file'];
						$cleanup_result = $this->file_handler->cleanup_orphaned_files( $original_path );
						
						if ( ! is_wp_error( $cleanup_result ) && ! empty( $cleanup_result ) ) {
							$results['orphaned'] += count( $cleanup_result );
							
							if ( ! $dry_run ) {
								$results['deleted'] += count( $cleanup_result );
								$results['files'] = array_merge( $results['files'], $cleanup_result );
							}
						}
					}
				}
			}
			
			// Clean up error context
			$this->error_handler->clear_error_context();
			
			return $results;
			
		} catch ( Exception $e ) {
			$this->error_handler->log_error(
				$e->getMessage(),
				'error',
				'file_system',
				array( 'exception' => get_class( $e ) )
			);
			
			// Clean up error context
			$this->error_handler->clear_error_context();
			
			return new WP_Error(
				'cleanup_failed',
				__( 'Failed to clean up orphaned files.', 'wp-image-optimizer' ),
				$e->getMessage()
			);
		}
	}

	/**
	 * Clean up temporary files
	 *
	 * @param int $max_age Maximum age of files to keep (in seconds)
	 * @return array|WP_Error Results of cleanup operation
	 */
	public function cleanup_temp_files( $max_age = 86400 ) { // Default: 24 hours
		// Get WordPress upload directory
		$upload_dir = wp_upload_dir();
		$temp_dir = $upload_dir['basedir'] . '/wp-image-optimizer-temp';
		
		// Set context for error logging
		$this->error_handler->set_error_context( array(
			'operation' => 'cleanup_temp_files',
			'max_age' => $max_age,
		) );
		
		try {
			$results = array(
				'scanned' => 0,
				'deleted' => 0,
				'failed' => 0,
				'files' => array(),
			);
			
			// Check if temp directory exists
			if ( ! is_dir( $temp_dir ) ) {
				return $results; // Nothing to clean up
			}
			
			// Get all files in temp directory
			$files = glob( $temp_dir . '/*' );
			$results['scanned'] = count( $files );
			
			// Process each file
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					$file_age = time() - filemtime( $file );
					
					// Delete files older than max_age
					if ( $file_age > $max_age ) {
						// Validate file path for security
						$validated_path = $this->security_validator->sanitize_file_path( $file, $temp_dir );
						
						if ( ! is_wp_error( $validated_path ) ) {
							$delete_result = $this->file_handler->delete_file( $validated_path );
							
							if ( ! is_wp_error( $delete_result ) ) {
								$results['deleted']++;
								$results['files'][] = $file;
							} else {
								$results['failed']++;
							}
						} else {
							$results['failed']++;
						}
					}
				}
			}
			
			// Clean up error context
			$this->error_handler->clear_error_context();
			
			return $results;
			
		} catch ( Exception $e ) {
			$this->error_handler->log_error(
				$e->getMessage(),
				'error',
				'file_system',
				array( 'exception' => get_class( $e ) )
			);
			
			// Clean up error context
			$this->error_handler->clear_error_context();
			
			return new WP_Error(
				'cleanup_failed',
				__( 'Failed to clean up temporary files.', 'wp-image-optimizer' ),
				$e->getMessage()
			);
		}
	}

	/**
	 * Clean up all converted files for a specific attachment
	 *
	 * @param int  $attachment_id Attachment ID
	 * @param bool $dry_run Whether to perform a dry run (don't actually delete files)
	 * @return array|WP_Error Results of cleanup operation
	 */
	public function cleanup_attachment_converted_files( $attachment_id, $dry_run = false ) {
		// Set context for error logging
		$this->error_handler->set_error_context( array(
			'operation' => 'cleanup_attachment_converted_files',
			'attachment_id' => $attachment_id,
			'dry_run' => $dry_run,
		) );
		
		try {
			$results = array(
				'attachment_id' => $attachment_id,
				'deleted' => array(),
			);
			
			// Get attachment file path
			$file_path = get_attached_file( $attachment_id );
			
			if ( ! $file_path ) {
				return new WP_Error(
					'invalid_attachment',
					__( 'Could not get attachment file path.', 'wp-image-optimizer' )
				);
			}
			
			// Clean up converted files
			$cleanup_result = $this->file_handler->cleanup_converted_files( $file_path, $dry_run );
			
			if ( ! is_wp_error( $cleanup_result ) ) {
				$results['deleted'] = $cleanup_result['deleted'];
			} else {
				return $cleanup_result;
			}
			
			// Clean up error context
			$this->error_handler->clear_error_context();
			
			return $results;
			
		} catch ( Exception $e ) {
			$this->error_handler->log_error(
				$e->getMessage(),
				'error',
				'file_system',
				array( 'exception' => get_class( $e ) )
			);
			
			// Clean up error context
			$this->error_handler->clear_error_context();
			
			return new WP_Error(
				'cleanup_failed',
				__( 'Failed to clean up converted files.', 'wp-image-optimizer' ),
				$e->getMessage()
			);
		}
	}

	/**
	 * Get all image attachments
	 *
	 * @param int $limit Maximum number of attachments to retrieve (0 for no limit)
	 * @param int $offset Offset for pagination
	 * @return array Array of attachment IDs
	 */
	private function get_all_image_attachments( $limit = 0, $offset = 0 ) {
		$args = array(
			'post_type' => 'attachment',
			'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif' ),
			'post_status' => 'inherit',
			'fields' => 'ids',
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'offset' => $offset,
		);
		
		$query = new WP_Query( $args );
		
		return $query->posts;
	}

	/**
	 * Purge plugin data
	 *
	 * @param bool $delete_settings Whether to delete plugin settings
	 * @param bool $delete_logs Whether to delete error logs
	 * @param bool $delete_files Whether to delete converted files
	 * @return array Results of purge operation
	 */
	public function purge_plugin_data( $delete_settings = false, $delete_logs = true, $delete_files = false ) {
		// Set context for error logging
		$this->error_handler->set_error_context( array(
			'operation' => 'purge_plugin_data',
			'delete_settings' => $delete_settings,
			'delete_logs' => $delete_logs,
			'delete_files' => $delete_files,
		) );
		
		$results = array(
			'settings_deleted' => false,
			'logs_deleted' => false,
			'files_deleted' => 0,
			'errors' => array(),
		);
		
		// Delete settings if requested
		if ( $delete_settings ) {
			delete_option( 'wp_image_optimizer_settings' );
			delete_option( 'wp_image_optimizer_activated' );
			delete_transient( 'wp_image_optimizer_dashboard_stats' );
			delete_transient( 'wp_image_optimizer_recent_conversions' );
			$results['settings_deleted'] = true;
		}
		
		// Delete logs if requested
		if ( $delete_logs ) {
			$this->error_handler->clear_error_logs();
			$results['logs_deleted'] = true;
		}
		
		// Delete converted files if requested
		if ( $delete_files ) {
			try {
				// Get all image attachments
				$attachments = $this->get_all_image_attachments();
				
				// Process each attachment
				foreach ( $attachments as $attachment_id ) {
					$cleanup_result = $this->cleanup_attachment_converted_files( $attachment_id, false );
					
					if ( ! is_wp_error( $cleanup_result ) ) {
						$results['files_deleted'] += count( $cleanup_result['deleted'] );
					} else {
						$results['errors'][] = $cleanup_result->get_error_message();
					}
				}
			} catch ( Exception $e ) {
				$this->error_handler->log_error(
					$e->getMessage(),
					'error',
					'file_system',
					array( 'exception' => get_class( $e ) )
				);
				
				$results['errors'][] = $e->getMessage();
			}
		}
		
		// Clean up error context
		$this->error_handler->clear_error_context();
		
		return $results;
	}
}