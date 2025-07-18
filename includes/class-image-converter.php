<?php
/**
 * Image Converter class
 *
 * Main conversion orchestrator that integrates converter factory and file handler
 * to provide automatic and on-demand image conversion functionality.
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Converter class
 * 
 * Orchestrates the image conversion process by integrating converter factory,
 * file handler, and settings manager to provide comprehensive image optimization.
 */
class WP_Image_Optimizer_Image_Converter {

	/**
	 * File handler instance
	 *
	 * @var WP_Image_Optimizer_File_Handler
	 */
	private $file_handler;

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
	 * Current converter instance
	 *
	 * @var Converter_Interface|null
	 */
	private $converter;

	/**
	 * Conversion statistics
	 *
	 * @var array
	 */
	private $stats = array(
		'conversions_attempted' => 0,
		'conversions_successful' => 0,
		'conversions_failed' => 0,
		'space_saved' => 0,
	);

	/**
	 * Constructor
	 *
	 * @param WP_Image_Optimizer_File_Handler|null $file_handler Optional file handler instance
	 */
	public function __construct( $file_handler = null ) {
		$this->settings_manager = new WP_Image_Optimizer_Settings_Manager();
		$this->error_handler = WP_Image_Optimizer_Error_Handler::get_instance();
		
		// Initialize file handler with current settings
		$settings = $this->settings_manager->get_settings();
		$this->file_handler = $file_handler ?: new WP_Image_Optimizer_File_Handler( $settings );
		
		// Get the best available converter
		$this->converter = Converter_Factory::get_converter();
		
		// Set error context
		$this->error_handler->set_error_context( array(
			'component' => 'image_converter',
			'converter' => $this->converter ? $this->converter->get_name() : 'none',
		) );
	}

	/**
	 * Convert uploaded image automatically
	 *
	 * @param array $upload_data Upload data from wp_handle_upload
	 * @return array|WP_Error Conversion results or error
	 */
	public function convert_uploaded_image( $upload_data ) {
		$this->error_handler->add_error_context( 'method', 'convert_uploaded_image' );
		
		if ( ! is_array( $upload_data ) || ! isset( $upload_data['file'] ) ) {
			$error = new WP_Error( 
				'invalid_upload_data', 
				__( 'Invalid upload data provided.', 'wp-image-optimizer' ) 
			);
			$this->error_handler->log_error( $error, 'error', 'validation', array( 'upload_data' => $upload_data ) );
			return $error;
		}

		$original_path = $upload_data['file'];
		$this->error_handler->add_error_context( 'original_file', basename( $original_path ) );
		
		// Check if conversion is enabled and in auto mode
		$settings = $this->settings_manager->get_settings();
		if ( ! $settings['enabled'] || $settings['conversion_mode'] !== 'auto' ) {
			return array( 'skipped' => true, 'reason' => 'conversion_disabled_or_manual' );
		}

		$result = $this->convert_image( $original_path );
		
		// Log successful conversion
		if ( ! is_wp_error( $result ) && ! empty( $result['conversions'] ) ) {
			$this->error_handler->log_error(
				sprintf( 'Successfully converted uploaded image: %s', basename( $original_path ) ),
				'info',
				'conversion',
				array( 'conversions' => array_keys( $result['conversions'] ) )
			);
		}
		
		return $result;
	}

	/**
	 * Convert image on-demand
	 *
	 * @param string $original_path Path to original image
	 * @param string $requested_format Requested format (webp, avif)
	 * @return string|WP_Error Path to converted image or error
	 */
	public function convert_on_demand( $original_path, $requested_format ) {
		$this->error_handler->add_error_context( 'method', 'convert_on_demand' );
		$this->error_handler->add_error_context( 'requested_format', $requested_format );
		
		if ( empty( $original_path ) || empty( $requested_format ) ) {
			$error = new WP_Error( 
				'invalid_parameters', 
				__( 'Original path and requested format are required.', 'wp-image-optimizer' ) 
			);
			$this->error_handler->log_error( $error, 'error', 'validation', array(
				'original_path' => $original_path,
				'requested_format' => $requested_format,
			) );
			return $error;
		}

		// Validate format
		$allowed_formats = array( 'webp', 'avif' );
		if ( ! in_array( strtolower( $requested_format ), $allowed_formats, true ) ) {
			$error = new WP_Error( 
				'invalid_format', 
				sprintf( 
					/* translators: %s: format name */
					__( 'Invalid format requested: %s', 'wp-image-optimizer' ), 
					$requested_format 
				) 
			);
			$this->error_handler->log_error( $error, 'error', 'validation', array(
				'requested_format' => $requested_format,
				'allowed_formats' => $allowed_formats,
			) );
			return $error;
		}

		// Check if format is enabled
		$settings = $this->settings_manager->get_settings();
		$format_key = strtolower( $requested_format );
		if ( ! $settings['formats'][ $format_key ]['enabled'] ) {
			$error = new WP_Error( 
				'format_disabled', 
				sprintf( 
					/* translators: %s: format name */
					__( 'Format %s is disabled in settings.', 'wp-image-optimizer' ), 
					$requested_format 
				) 
			);
			$this->error_handler->log_error( $error, 'warning', 'configuration', array(
				'format' => $requested_format,
				'format_settings' => $settings['formats'][ $format_key ],
			) );
			return $error;
		}

		// Generate converted file path
		$converted_path = $this->file_handler->generate_converted_path( $original_path, $requested_format );
		if ( is_wp_error( $converted_path ) ) {
			$this->error_handler->log_error( $converted_path, 'error', 'file_system', array(
				'original_path' => $original_path,
				'requested_format' => $requested_format,
			) );
			return $converted_path;
		}

		// Check if converted file already exists
		if ( file_exists( $converted_path ) ) {
			return $converted_path;
		}

		// Perform conversion
		$conversion_result = $this->convert_single_format( $original_path, $requested_format );
		if ( is_wp_error( $conversion_result ) ) {
			$this->error_handler->log_conversion_error( 
				$conversion_result, 
				$original_path, 
				$requested_format,
				array( 'context' => 'on_demand_conversion' )
			);
			return $conversion_result;
		}

		return $converted_path;
	}

	/**
	 * Convert image to all enabled formats
	 *
	 * @param string $original_path Path to original image
	 * @return array|WP_Error Conversion results or error
	 */
	public function convert_image( $original_path ) {
		$this->error_handler->add_error_context( 'method', 'convert_image' );
		$this->error_handler->add_error_context( 'original_file', basename( $original_path ) );
		
		if ( empty( $original_path ) ) {
			$error = new WP_Error( 'empty_path', __( 'Original image path cannot be empty.', 'wp-image-optimizer' ) );
			$this->error_handler->log_error( $error, 'error', 'validation' );
			return $error;
		}

		// Validate original file
		$validation_result = $this->validate_original_file( $original_path );
		if ( is_wp_error( $validation_result ) ) {
			$this->error_handler->log_error( $validation_result, 'error', 'validation', array(
				'original_path' => $original_path,
			) );
			return $validation_result;
		}

		// Check if converter is available
		if ( ! $this->converter ) {
			$error = new WP_Error( 
				'no_converter', 
				__( 'No image converter available. Please ensure ImageMagick or GD is installed.', 'wp-image-optimizer' ) 
			);
			$this->error_handler->log_critical_error( $error, 'system', array(
				'available_extensions' => get_loaded_extensions(),
			) );
			return $error;
		}

		$settings = $this->settings_manager->get_settings();
		$results = array(
			'original_path' => $original_path,
			'conversions' => array(),
			'errors' => array(),
			'space_saved' => 0,
		);

		$conversion_errors = array();

		// Convert to each enabled format
		foreach ( $settings['formats'] as $format => $format_settings ) {
			if ( ! $format_settings['enabled'] ) {
				continue;
			}

			$this->stats['conversions_attempted']++;
			$this->error_handler->add_error_context( 'current_format', $format );
			
			$conversion_result = $this->convert_single_format( $original_path, $format );
			
			if ( is_wp_error( $conversion_result ) ) {
				$this->stats['conversions_failed']++;
				$results['errors'][ $format ] = $conversion_result->get_error_message();
				$conversion_errors[] = $format;
				
				// Log conversion error with specific context
				$this->error_handler->log_conversion_error( 
					$conversion_result, 
					$original_path, 
					$format,
					array( 
						'quality' => $format_settings['quality'],
						'converter' => $this->converter->get_name(),
					)
				);
			} else {
				$this->stats['conversions_successful']++;
				$results['conversions'][ $format ] = $conversion_result;
				
				// Calculate space savings
				if ( isset( $conversion_result['space_saved'] ) ) {
					$results['space_saved'] += $conversion_result['space_saved'];
					$this->stats['space_saved'] += $conversion_result['space_saved'];
				}
			}
		}

		// Log overall conversion result
		if ( empty( $results['conversions'] ) && ! empty( $conversion_errors ) ) {
			$this->error_handler->log_error(
				sprintf( 'All format conversions failed for image: %s', basename( $original_path ) ),
				'error',
				'conversion',
				array( 
					'failed_formats' => $conversion_errors,
					'total_formats_attempted' => count( $conversion_errors ),
				)
			);
		} elseif ( ! empty( $results['conversions'] ) ) {
			$this->error_handler->log_error(
				sprintf( 'Successfully converted image to %d format(s): %s', 
					count( $results['conversions'] ), 
					basename( $original_path ) 
				),
				'info',
				'conversion',
				array( 
					'successful_formats' => array_keys( $results['conversions'] ),
					'space_saved' => $results['space_saved'],
				)
			);
		}

		// Store conversion metadata
		$this->store_conversion_metadata( $original_path, $results );

		return $results;
	}

	/**
	 * Convert image to a single format
	 *
	 * @param string $original_path Path to original image
	 * @param string $format Target format (webp, avif)
	 * @return array|WP_Error Conversion result or error
	 */
	private function convert_single_format( $original_path, $format ) {
		$format = strtolower( $format );
		$settings = $this->settings_manager->get_settings();
		
		// Get quality setting for format
		$quality = $settings['formats'][ $format ]['quality'];
		
		// Generate converted file path
		$converted_path = $this->file_handler->generate_converted_path( $original_path, $format );
		if ( is_wp_error( $converted_path ) ) {
			$this->error_handler->log_error( $converted_path, 'error', 'file_system', array(
				'operation' => 'generate_converted_path',
				'format' => $format,
			) );
			return $converted_path;
		}

		// Check write permissions
		$write_check = $this->file_handler->validate_write_permissions( $converted_path );
		if ( is_wp_error( $write_check ) ) {
			$this->error_handler->log_error( $write_check, 'error', 'file_system', array(
				'operation' => 'validate_write_permissions',
				'converted_path' => $converted_path,
			) );
			return $write_check;
		}

		// Check if format is supported by current converter
		$supported_formats = $this->converter->get_supported_formats();
		if ( ! in_array( $format, $supported_formats, true ) ) {
			$error = new WP_Error( 
				'format_not_supported', 
				sprintf( 
					/* translators: %1$s: format name, %2$s: converter name */
					__( 'Format %1$s is not supported by %2$s converter.', 'wp-image-optimizer' ), 
					$format, 
					$this->converter->get_name() 
				) 
			);
			$this->error_handler->log_error( $error, 'error', 'configuration', array(
				'requested_format' => $format,
				'supported_formats' => $supported_formats,
				'converter' => $this->converter->get_name(),
			) );
			return $error;
		}

		// Perform conversion with error handling
		$conversion_success = false;
		$conversion_method = 'convert_to_' . $format;
		
		try {
			if ( method_exists( $this->converter, $conversion_method ) ) {
				$conversion_success = $this->converter->$conversion_method( $original_path, $converted_path, $quality );
			} else {
				$error = new WP_Error( 
					'conversion_method_missing', 
					sprintf( 
						/* translators: %1$s: method name, %2$s: converter name */
						__( 'Conversion method %1$s not found in %2$s converter.', 'wp-image-optimizer' ), 
						$conversion_method, 
						$this->converter->get_name() 
					) 
				);
				$this->error_handler->log_error( $error, 'error', 'system', array(
					'conversion_method' => $conversion_method,
					'converter' => $this->converter->get_name(),
				) );
				return $error;
			}
		} catch ( Exception $e ) {
			$error = new WP_Error( 
				'conversion_exception', 
				sprintf( 
					/* translators: %s: exception message */
					__( 'Conversion failed with exception: %s', 'wp-image-optimizer' ), 
					$e->getMessage() 
				) 
			);
			$this->error_handler->log_error( $error, 'error', 'runtime', array(
				'exception_message' => $e->getMessage(),
				'exception_file' => $e->getFile(),
				'exception_line' => $e->getLine(),
				'format' => $format,
				'quality' => $quality,
			) );
			return $error;
		}

		if ( ! $conversion_success ) {
			$error = new WP_Error( 
				'conversion_failed', 
				sprintf( 
					/* translators: %1$s: format name, %2$s: original file path */
					__( 'Failed to convert %2$s to %1$s format.', 'wp-image-optimizer' ), 
					$format, 
					basename( $original_path ) 
				) 
			);
			$this->error_handler->log_conversion_error( $error, $original_path, $format, array(
				'quality' => $quality,
				'converter' => $this->converter->get_name(),
				'converted_path' => $converted_path,
			) );
			return $error;
		}

		// Verify converted file was created
		if ( ! file_exists( $converted_path ) ) {
			$error = new WP_Error( 
				'converted_file_missing', 
				sprintf( 
					/* translators: %s: converted file path */
					__( 'Converted file was not created: %s', 'wp-image-optimizer' ), 
					$converted_path 
				) 
			);
			$this->error_handler->log_error( $error, 'error', 'file_system', array(
				'converted_path' => $converted_path,
				'format' => $format,
				'conversion_reported_success' => $conversion_success,
			) );
			return $error;
		}

		// Get file information for both original and converted
		$original_info = $this->file_handler->get_file_info( $original_path );
		$converted_info = $this->file_handler->get_file_info( $converted_path );
		
		if ( is_wp_error( $original_info ) || is_wp_error( $converted_info ) ) {
			$error = new WP_Error( 
				'file_info_error', 
				__( 'Could not retrieve file information for conversion result.', 'wp-image-optimizer' ) 
			);
			$this->error_handler->log_error( $error, 'warning', 'file_system', array(
				'original_info_error' => is_wp_error( $original_info ),
				'converted_info_error' => is_wp_error( $converted_info ),
			) );
			return $error;
		}

		// Calculate space savings
		$space_saved = $original_info['size'] - $converted_info['size'];
		$compression_ratio = $original_info['size'] > 0 ? 
			( $space_saved / $original_info['size'] ) * 100 : 0;

		return array(
			'format' => $format,
			'original_path' => $original_path,
			'converted_path' => $converted_path,
			'original_size' => $original_info['size'],
			'converted_size' => $converted_info['size'],
			'space_saved' => $space_saved,
			'compression_ratio' => round( $compression_ratio, 2 ),
			'quality' => $quality,
			'converter' => $this->converter->get_name(),
			'timestamp' => time(),
		);
	}

	/**
	 * Validate original file before conversion
	 *
	 * @param string $original_path Path to original image
	 * @return bool|WP_Error True if valid, error otherwise
	 */
	private function validate_original_file( $original_path ) {
		// Check if file exists and is readable
		$exists_check = $this->file_handler->file_exists_and_readable( $original_path );
		if ( is_wp_error( $exists_check ) ) {
			return $exists_check;
		}

		// Validate file size
		$size_check = $this->file_handler->validate_file_size( $original_path );
		if ( is_wp_error( $size_check ) ) {
			return $size_check;
		}

		// Validate MIME type
		$mime_check = $this->file_handler->validate_mime_type( $original_path );
		if ( is_wp_error( $mime_check ) ) {
			return $mime_check;
		}

		return true;
	}

	/**
	 * Store conversion metadata
	 *
	 * @param string $original_path Path to original image
	 * @param array  $conversion_results Conversion results
	 * @return bool True on success, false on failure
	 */
	private function store_conversion_metadata( $original_path, $conversion_results ) {
		// Try to get attachment ID from path
		$attachment_id = $this->get_attachment_id_from_path( $original_path );
		
		if ( ! $attachment_id ) {
			// If we can't find attachment ID, store in transient for later retrieval
			$transient_key = 'wp_image_optimizer_conversion_' . md5( $original_path );
			set_transient( $transient_key, $conversion_results, DAY_IN_SECONDS );
			return true;
		}

		// Get existing metadata
		$existing_meta = get_post_meta( $attachment_id, '_wp_image_optimizer_conversions', true );
		if ( ! is_array( $existing_meta ) ) {
			$existing_meta = array();
		}

		// Add new conversion data
		$existing_meta[ time() ] = array(
			'original_path' => $original_path,
			'conversions' => $conversion_results['conversions'],
			'errors' => $conversion_results['errors'],
			'space_saved' => $conversion_results['space_saved'],
			'converter' => $this->converter ? $this->converter->get_name() : 'unknown',
		);

		// Keep only the last 5 conversion records per attachment
		if ( count( $existing_meta ) > 5 ) {
			$existing_meta = array_slice( $existing_meta, -5, null, true );
		}

		return update_post_meta( $attachment_id, '_wp_image_optimizer_conversions', $existing_meta );
	}

	/**
	 * Get attachment ID from file path
	 *
	 * @param string $file_path Path to image file
	 * @return int|false Attachment ID or false if not found
	 */
	private function get_attachment_id_from_path( $file_path ) {
		global $wpdb;

		// Get upload directory info
		$upload_dir = wp_upload_dir();
		$file_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file_path );

		// Query for attachment with this URL
		$attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s",
			'%' . basename( $file_path )
		) );

		return $attachment_id ? (int) $attachment_id : false;
	}

	/**
	 * Get conversion metadata for an attachment
	 *
	 * @param int $attachment_id Attachment ID
	 * @return array|false Conversion metadata or false if not found
	 */
	public function get_conversion_metadata( $attachment_id ) {
		if ( ! is_numeric( $attachment_id ) || $attachment_id <= 0 ) {
			return false;
		}

		$metadata = get_post_meta( $attachment_id, '_wp_image_optimizer_conversions', true );
		return is_array( $metadata ) ? $metadata : false;
	}

	/**
	 * Check if converted versions exist for an image
	 *
	 * @param string $original_path Path to original image
	 * @return array Status of converted versions
	 */
	public function check_converted_versions( $original_path ) {
		if ( empty( $original_path ) ) {
			return array();
		}

		$settings = $this->settings_manager->get_settings();
		$versions = array();

		foreach ( $settings['formats'] as $format => $format_settings ) {
			$converted_path = $this->file_handler->generate_converted_path( $original_path, $format );
			
			$versions[ $format ] = array(
				'enabled' => $format_settings['enabled'],
				'exists' => ! is_wp_error( $converted_path ) && file_exists( $converted_path ),
				'path' => is_wp_error( $converted_path ) ? null : $converted_path,
			);

			if ( $versions[ $format ]['exists'] ) {
				$file_info = $this->file_handler->get_file_info( $converted_path );
				if ( ! is_wp_error( $file_info ) ) {
					$versions[ $format ]['size'] = $file_info['size'];
					$versions[ $format ]['modified'] = $file_info['modified_time'];
				}
			}
		}

		return $versions;
	}

	/**
	 * Clean up converted files for an image
	 *
	 * @param string $original_path Path to original image
	 * @return array|WP_Error Cleanup results or error
	 */
	public function cleanup_converted_files( $original_path ) {
		return $this->file_handler->cleanup_converted_files( $original_path );
	}

	/**
	 * Get conversion statistics
	 *
	 * @return array Current conversion statistics
	 */
	public function get_conversion_stats() {
		return $this->stats;
	}

	/**
	 * Reset conversion statistics
	 */
	public function reset_conversion_stats() {
		$this->stats = array(
			'conversions_attempted' => 0,
			'conversions_successful' => 0,
			'conversions_failed' => 0,
			'space_saved' => 0,
		);
	}

	/**
	 * Get current converter information
	 *
	 * @return array|null Converter information or null if no converter available
	 */
	public function get_converter_info() {
		if ( ! $this->converter ) {
			return null;
		}

		return array(
			'name' => $this->converter->get_name(),
			'priority' => $this->converter->get_priority(),
			'supported_formats' => $this->converter->get_supported_formats(),
			'is_available' => $this->converter->is_available(),
		);
	}

	/**
	 * Check if conversion is possible
	 *
	 * @return bool|WP_Error True if conversion is possible, error otherwise
	 */
	public function can_convert() {
		if ( ! $this->converter ) {
			return new WP_Error( 
				'no_converter', 
				__( 'No image converter available.', 'wp-image-optimizer' ) 
			);
		}

		if ( ! $this->converter->is_available() ) {
			return new WP_Error( 
				'converter_unavailable', 
				sprintf( 
					/* translators: %s: converter name */
					__( '%s converter is not available.', 'wp-image-optimizer' ), 
					$this->converter->get_name() 
				) 
			);
		}

		$settings = $this->settings_manager->get_settings();
		if ( ! $settings['enabled'] ) {
			return new WP_Error( 
				'conversion_disabled', 
				__( 'Image conversion is disabled in settings.', 'wp-image-optimizer' ) 
			);
		}

		// Check if at least one format is enabled
		$has_enabled_format = false;
		foreach ( $settings['formats'] as $format_settings ) {
			if ( $format_settings['enabled'] ) {
				$has_enabled_format = true;
				break;
			}
		}

		if ( ! $has_enabled_format ) {
			return new WP_Error( 
				'no_formats_enabled', 
				__( 'No image formats are enabled for conversion.', 'wp-image-optimizer' ) 
			);
		}

		return true;
	}
}