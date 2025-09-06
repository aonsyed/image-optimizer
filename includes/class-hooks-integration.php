<?php
/**
 * WordPress Hooks Integration class
 *
 * Handles integration with WordPress hooks for automatic image conversion,
 * metadata storage, URL modification, and on-demand conversion requests.
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress Hooks Integration class
 * 
 * Manages all WordPress hook integrations for the image optimization plugin.
 */
class WP_Image_Optimizer_Hooks_Integration {

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
	 * Constructor
	 */
	public function __construct() {
		$this->settings_manager = new WP_Image_Optimizer_Settings_Manager();
		$this->image_converter = new WP_Image_Optimizer_Image_Converter();
		
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Hook into upload process for automatic conversion
		add_filter( 'wp_handle_upload', array( $this, 'handle_upload_conversion' ), 10, 2 );
		
		// Hook into attachment metadata generation
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'generate_attachment_metadata' ), 10, 3 );
		
		// Hook into image source URL modification
		add_filter( 'wp_get_attachment_image_src', array( $this, 'modify_attachment_image_src' ), 10, 4 );
		
		// Hook into template redirect for on-demand conversion
		add_action( 'template_redirect', array( $this, 'handle_on_demand_conversion' ) );
		
		// Hook into attachment deletion for cleanup
		add_action( 'delete_attachment', array( $this, 'cleanup_on_attachment_delete' ) );
	}

	/**
	 * Handle image conversion on upload
	 *
	 * @param array $upload Upload data from wp_handle_upload
	 * @param array $context Upload context
	 * @return array Modified upload data
	 */
	public function handle_upload_conversion( $upload, $context = array() ) {
		// Check if this is an image upload
		if ( ! isset( $upload['type'] ) || ! $this->is_convertible_image( $upload['type'] ) ) {
			return $upload;
		}

		// Check if conversion is enabled
		$settings = $this->settings_manager->get_settings();
		if ( ! $settings['enabled'] || $settings['conversion_mode'] !== 'auto' ) {
			return $upload;
		}

		// Perform conversion
		$conversion_result = $this->image_converter->convert_uploaded_image( $upload );
		
		if ( is_wp_error( $conversion_result ) ) {
			// Log error but don't fail the upload
			error_log( 'WP Image Optimizer: Upload conversion failed - ' . $conversion_result->get_error_message() );
		} elseif ( ! isset( $conversion_result['skipped'] ) ) {
			// Add conversion data to upload info for later use
			$upload['wp_image_optimizer_conversions'] = $conversion_result;
		}

		return $upload;
	}

	/**
	 * Generate attachment metadata with conversion information
	 *
	 * @param array $metadata Attachment metadata
	 * @param int   $attachment_id Attachment ID
	 * @param string $context Context (create, update, etc.)
	 * @return array Modified metadata
	 */
	public function generate_attachment_metadata( $metadata, $attachment_id, $context = 'create' ) {
		// Only process during creation or when explicitly requested
		if ( 'create' !== $context && 'wp_image_optimizer_regenerate' !== $context ) {
			return $metadata;
		}

		// Get attachment file path
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return $metadata;
		}

		// Check if this is a convertible image
		$mime_type = get_post_mime_type( $attachment_id );
		if ( ! $this->is_convertible_image( $mime_type ) ) {
			return $metadata;
		}

		// Check conversion settings
		$settings = $this->settings_manager->get_settings();
		if ( ! $settings['enabled'] ) {
			return $metadata;
		}

		// Check if we already have conversion data from upload
		$upload_conversions = get_post_meta( $attachment_id, '_wp_image_optimizer_upload_conversions', true );
		
		if ( $upload_conversions && is_array( $upload_conversions ) ) {
			// Use existing conversion data
			$conversion_result = $upload_conversions;
			delete_post_meta( $attachment_id, '_wp_image_optimizer_upload_conversions' );
		} else {
			// Perform conversion now
			$conversion_result = $this->image_converter->convert_image( $file_path );
			
			if ( is_wp_error( $conversion_result ) ) {
				error_log( 'WP Image Optimizer: Metadata conversion failed - ' . $conversion_result->get_error_message() );
				return $metadata;
			}
		}

		// Add conversion information to metadata
		if ( ! isset( $metadata['wp_image_optimizer'] ) ) {
			$metadata['wp_image_optimizer'] = array();
		}

		$metadata['wp_image_optimizer']['conversions'] = $conversion_result['conversions'];
		$metadata['wp_image_optimizer']['space_saved'] = $conversion_result['space_saved'];
		$metadata['wp_image_optimizer']['conversion_date'] = current_time( 'mysql' );
		$metadata['wp_image_optimizer']['converter'] = $this->image_converter->get_converter_info();

		// Also convert thumbnail sizes if they exist
		if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$upload_dir = wp_upload_dir();
			$base_dir = trailingslashit( dirname( $file_path ) );
			
			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				if ( ! isset( $size_data['file'] ) ) {
					continue;
				}
				
				$thumbnail_path = $base_dir . $size_data['file'];
				if ( file_exists( $thumbnail_path ) ) {
					$thumb_conversion = $this->image_converter->convert_image( $thumbnail_path );
					
					if ( ! is_wp_error( $thumb_conversion ) ) {
						$metadata['wp_image_optimizer']['thumbnails'][ $size_name ] = $thumb_conversion;
					}
				}
			}
		}

		// Update plugin statistics
		$this->update_plugin_statistics( $conversion_result );

		return $metadata;
	}

	/**
	 * Modify attachment image source to serve optimized versions
	 *
	 * @param array|false  $image Array of image data or false
	 * @param int          $attachment_id Attachment ID
	 * @param string|array $size Image size
	 * @param bool         $icon Whether the image should be treated as an icon
	 * @return array|false Modified image data or false
	 */
	public function modify_attachment_image_src( $image, $attachment_id, $size, $icon ) {
		// Don't modify if we don't have valid image data
		if ( false === $image || ! is_array( $image ) || empty( $image[0] ) ) {
			return $image;
		}

		// Don't modify icons or if conversion is disabled
		if ( $icon ) {
			return $image;
		}

		$settings = $this->settings_manager->get_settings();
		if ( ! $settings['enabled'] ) {
			return $image;
		}

		// Get the best format for the current browser
		$best_format = $this->get_best_format_for_browser();
		if ( ! $best_format ) {
			return $image;
		}

		// Get original file path
		$original_url = $image[0];
		$upload_dir = wp_upload_dir();
		$original_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $original_url );

		// Check if converted version exists
		if ( ! class_exists( 'WP_Image_Optimizer_File_Handler' ) ) {
			require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-file-handler.php';
		}
		$file_handler = new WP_Image_Optimizer_File_Handler( $settings );
		$converted_path = $file_handler->generate_converted_path( $original_path, $best_format );
		
		if ( is_wp_error( $converted_path ) || ! file_exists( $converted_path ) ) {
			return $image;
		}

		// Replace URL with converted version
		$converted_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $converted_path );
		$image[0] = $converted_url;

		return $image;
	}

	/**
	 * Handle on-demand conversion requests
	 */
	public function handle_on_demand_conversion() {
		// Check if this is an image request that might need conversion
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		
		// Only process image requests
		if ( ! preg_match( '/\.(jpe?g|png|gif)$/i', $request_uri ) ) {
			return;
		}

		// Check if the requested file doesn't exist (404)
		$upload_dir = wp_upload_dir();
		$file_path = $upload_dir['basedir'] . parse_url( $request_uri, PHP_URL_PATH );
		
		// If original file exists, let WordPress handle it normally
		if ( file_exists( $file_path ) ) {
			return;
		}

		// Check if this might be a request for a converted image
		$best_format = $this->get_best_format_for_browser();
		if ( ! $best_format ) {
			return;
		}

		// Try to find the original image
		$original_extensions = array( '.jpg', '.jpeg', '.png', '.gif' );
		$original_path = null;
		
		foreach ( $original_extensions as $ext ) {
			$potential_original = preg_replace( '/\.(jpe?g|png|gif)$/i', $ext, $file_path );
			if ( file_exists( $potential_original ) ) {
				$original_path = $potential_original;
				break;
			}
		}

		if ( ! $original_path ) {
			return;
		}

		// Perform on-demand conversion
		$converted_path = $this->image_converter->convert_on_demand( $original_path, $best_format );
		
		if ( is_wp_error( $converted_path ) || ! file_exists( $converted_path ) ) {
			return;
		}

		// Serve the converted image
		$this->serve_converted_image( $converted_path, $best_format );
	}

	/**
	 * Clean up converted files when attachment is deleted
	 *
	 * @param int $attachment_id Attachment ID being deleted
	 */
	public function cleanup_on_attachment_delete( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path ) {
			return;
		}

		// Clean up converted versions
		$cleanup_result = $this->image_converter->cleanup_converted_files( $file_path );
		
		if ( is_wp_error( $cleanup_result ) ) {
			error_log( 'WP Image Optimizer: Cleanup failed - ' . $cleanup_result->get_error_message() );
		}

		// Clean up metadata
		delete_post_meta( $attachment_id, '_wp_image_optimizer_conversions' );
	}

	/**
	 * Check if image type is convertible
	 *
	 * @param string $mime_type MIME type to check
	 * @return bool True if convertible, false otherwise
	 */
	private function is_convertible_image( $mime_type ) {
		$settings = $this->settings_manager->get_settings();
		$allowed_types = $settings['allowed_mime_types'] ?? array( 'image/jpeg', 'image/png', 'image/gif' );
		
		return in_array( $mime_type, $allowed_types, true );
	}

	/**
	 * Get the best image format for the current browser
	 *
	 * @return string|false Best format or false if none supported
	 */
	private function get_best_format_for_browser() {
		$settings = $this->settings_manager->get_settings();
		$accept_header = $_SERVER['HTTP_ACCEPT'] ?? '';

		// Check for AVIF support first (best compression)
		if ( $settings['formats']['avif']['enabled'] && strpos( $accept_header, 'image/avif' ) !== false ) {
			return 'avif';
		}

		// Check for WebP support
		if ( $settings['formats']['webp']['enabled'] && strpos( $accept_header, 'image/webp' ) !== false ) {
			return 'webp';
		}

		return false;
	}

	/**
	 * Serve converted image with appropriate headers
	 *
	 * @param string $file_path Path to converted image
	 * @param string $format Image format
	 */
	private function serve_converted_image( $file_path, $format ) {
		if ( ! file_exists( $file_path ) ) {
			return;
		}

		// Set appropriate headers
		$mime_types = array(
			'webp' => 'image/webp',
			'avif' => 'image/avif',
		);

		$mime_type = $mime_types[ $format ] ?? 'application/octet-stream';
		
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: public, max-age=31536000, immutable' );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 31536000 ) . ' GMT' );
		header( 'Vary: Accept' );

		// Output the file
		readfile( $file_path );
		exit;
	}

	/**
	 * Update plugin statistics
	 *
	 * @param array $conversion_result Conversion result data
	 */
	private function update_plugin_statistics( $conversion_result ) {
		$options = get_option( 'wp_image_optimizer_settings', array() );
		
		if ( ! isset( $options['stats'] ) ) {
			$options['stats'] = array(
				'total_conversions' => 0,
				'space_saved' => 0,
				'last_batch_run' => null,
			);
		}

		// Update statistics
		if ( isset( $conversion_result['conversions'] ) ) {
			$options['stats']['total_conversions'] += count( $conversion_result['conversions'] );
		}
		
		if ( isset( $conversion_result['space_saved'] ) ) {
			$options['stats']['space_saved'] += $conversion_result['space_saved'];
		}

		update_option( 'wp_image_optimizer_settings', $options );
	}

	/**
	 * Store upload conversion data temporarily
	 *
	 * @param int   $attachment_id Attachment ID
	 * @param array $conversion_data Conversion data
	 */
	public function store_upload_conversion_data( $attachment_id, $conversion_data ) {
		update_post_meta( $attachment_id, '_wp_image_optimizer_upload_conversions', $conversion_data );
	}

	/**
	 * Check if hooks are properly initialized
	 *
	 * @return bool True if hooks are initialized
	 */
	public function are_hooks_initialized() {
		return (
			has_filter( 'wp_handle_upload', array( $this, 'handle_upload_conversion' ) ) &&
			has_filter( 'wp_generate_attachment_metadata', array( $this, 'generate_attachment_metadata' ) ) &&
			has_filter( 'wp_get_attachment_image_src', array( $this, 'modify_attachment_image_src' ) ) &&
			has_action( 'template_redirect', array( $this, 'handle_on_demand_conversion' ) )
		);
	}

	/**
	 * Remove all hooks (for testing or deactivation)
	 */
	public function remove_hooks() {
		remove_filter( 'wp_handle_upload', array( $this, 'handle_upload_conversion' ) );
		remove_filter( 'wp_generate_attachment_metadata', array( $this, 'generate_attachment_metadata' ) );
		remove_filter( 'wp_get_attachment_image_src', array( $this, 'modify_attachment_image_src' ) );
		remove_action( 'template_redirect', array( $this, 'handle_on_demand_conversion' ) );
		remove_action( 'delete_attachment', array( $this, 'cleanup_on_attachment_delete' ) );
	}
}