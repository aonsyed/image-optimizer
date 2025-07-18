<?php
/**
 * Image Handler class for frontend image requests
 *
 * Handles browser capability detection, format selection, and image serving
 * for the public-facing image optimization functionality.
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Handler class
 * 
 * Manages frontend image requests, browser capability detection,
 * and serves optimized images with proper HTTP headers and caching.
 */
class WP_Image_Optimizer_Image_Handler {

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
	 * File handler instance
	 *
	 * @var WP_Image_Optimizer_File_Handler
	 */
	private $file_handler;

	/**
	 * Supported image formats in order of preference
	 *
	 * @var array
	 */
	private $format_preference = array( 'avif', 'webp' );

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings_manager = new WP_Image_Optimizer_Settings_Manager();
		$settings = $this->settings_manager->get_settings();
		
		$this->file_handler = new WP_Image_Optimizer_File_Handler( $settings );
		$this->image_converter = new WP_Image_Optimizer_Image_Converter( $this->file_handler );
	}

	/**
	 * Handle image request and serve optimized version
	 *
	 * @param string $requested_file Requested image file path
	 * @return void
	 */
	public function handle_image_request( $requested_file ) {
		// Validate and sanitize the requested file path
		$original_path = $this->validate_requested_file( $requested_file );
		if ( is_wp_error( $original_path ) ) {
			$this->serve_error( 404, $original_path->get_error_message() );
			return;
		}

		// Check if original file exists
		if ( ! file_exists( $original_path ) ) {
			$this->serve_error( 404, 'Original image not found' );
			return;
		}

		// Detect browser capabilities and determine best format
		$best_format = $this->detect_best_format();
		
		// Try to serve the best format
		if ( $best_format ) {
			$served = $this->try_serve_format( $original_path, $best_format );
			if ( $served ) {
				return;
			}
		}

		// Fallback to original image
		$this->serve_original_image( $original_path );
	}

	/**
	 * Validate requested file path
	 *
	 * @param string $requested_file Requested file path
	 * @return string|WP_Error Validated path or error
	 */
	private function validate_requested_file( $requested_file ) {
		// Get security validator
		$security_validator = WP_Image_Optimizer_Security_Validator::get_instance();
		
		if ( empty( $requested_file ) ) {
			return new WP_Error( 'empty_file', 'No file specified' );
		}

		// Remove query parameters and decode URL
		$file_path = urldecode( strtok( $requested_file, '?' ) );
		
		// Get upload directory info
		$upload_dir = wp_upload_dir();
		$base_dir = wp_normalize_path( $upload_dir['basedir'] );
		
		// Define allowed extensions
		$allowed_extensions = array( 'jpg', 'jpeg', 'png', 'gif' );
		
		// Use security validator to sanitize and validate the file path
		$validated_path = $security_validator->sanitize_file_path( 
			$file_path, 
			$base_dir, 
			$allowed_extensions 
		);
		
		if ( is_wp_error( $validated_path ) ) {
			// Log security violation attempt
			$error_handler = WP_Image_Optimizer_Error_Handler::get_instance();
			$error_handler->log_error(
				$validated_path,
				'warning',
				'security',
				array(
					'requested_file' => $requested_file,
					'client_ip' => $security_validator->get_client_ip(),
					'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
				)
			);
			
			return $validated_path;
		}
		
		// Convert relative path to absolute path if needed
		if ( strpos( $validated_path, $base_dir ) !== 0 ) {
			$validated_path = $base_dir . '/' . ltrim( $validated_path, '/' );
		}
		
		return $validated_path;
	}

	/**
	 * Detect browser capabilities and determine best format
	 *
	 * @return string|null Best supported format or null
	 */
	private function detect_best_format() {
		$settings = $this->settings_manager->get_settings();
		$accept_header = $this->get_accept_header();

		// Check each format in order of preference
		foreach ( $this->format_preference as $format ) {
			// Skip if format is disabled in settings
			if ( ! $settings['formats'][ $format ]['enabled'] ) {
				continue;
			}

			// Check if browser supports this format
			if ( $this->browser_supports_format( $format, $accept_header ) ) {
				return $format;
			}
		}

		return null;
	}

	/**
	 * Get Accept header from request
	 *
	 * @return string Accept header value
	 */
	private function get_accept_header() {
		// Get security validator
		$security_validator = WP_Image_Optimizer_Security_Validator::get_instance();
		
		// Try different ways to get the Accept header
		if ( isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			return $security_validator->sanitize_text_input( $_SERVER['HTTP_ACCEPT'] );
		}

		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			if ( isset( $headers['Accept'] ) ) {
				return $security_validator->sanitize_text_input( $headers['Accept'] );
			}
			// Case-insensitive search
			foreach ( $headers as $name => $value ) {
				if ( strtolower( $name ) === 'accept' ) {
					return $security_validator->sanitize_text_input( $value );
				}
			}
		}

		return '';
	}

	/**
	 * Check if browser supports a specific format
	 *
	 * @param string $format Image format (webp, avif)
	 * @param string $accept_header Accept header value
	 * @return bool True if supported
	 */
	private function browser_supports_format( $format, $accept_header ) {
		// Validate format parameter
		$format = strtolower( trim( $format ) );
		
		$format_mime_types = array(
			'webp' => 'image/webp',
			'avif' => 'image/avif',
		);

		if ( ! isset( $format_mime_types[ $format ] ) ) {
			return false;
		}

		// Sanitize accept header
		$accept_header = strtolower( trim( $accept_header ) );
		if ( empty( $accept_header ) ) {
			return false;
		}
		
		$mime_type = $format_mime_types[ $format ];
		
		// Check if the MIME type is in the Accept header
		// More precise check to avoid false positives
		if ( strpos( $accept_header, $mime_type ) !== false ) {
			// Check for exact MIME type match or MIME type with quality parameter
			if ( preg_match( '/' . preg_quote( $mime_type, '/' ) . '($|;|\s|,)/', $accept_header ) ) {
				return true;
			}
		}
		
		// Check for wildcard acceptance
		if ( strpos( $accept_header, 'image/*' ) !== false || strpos( $accept_header, '*/*' ) !== false ) {
			return true;
		}
		
		return false;
	}

	/**
	 * Try to serve image in specified format
	 *
	 * @param string $original_path Original image path
	 * @param string $format Target format
	 * @return bool True if served successfully
	 */
	private function try_serve_format( $original_path, $format ) {
		// Generate converted file path
		$converted_path = $this->file_handler->generate_converted_path( $original_path, $format );
		if ( is_wp_error( $converted_path ) ) {
			return false;
		}

		// If converted file exists, serve it
		if ( file_exists( $converted_path ) ) {
			$this->serve_image_file( $converted_path, $format );
			return true;
		}

		// Try to convert on-demand
		$conversion_result = $this->image_converter->convert_on_demand( $original_path, $format );
		if ( is_wp_error( $conversion_result ) ) {
			// Log conversion error but don't fail the request
			$this->log_conversion_error( $original_path, $format, $conversion_result );
			return false;
		}

		// Serve the newly converted file
		if ( file_exists( $conversion_result ) ) {
			$this->serve_image_file( $conversion_result, $format );
			return true;
		}

		return false;
	}

	/**
	 * Serve original image file
	 *
	 * @param string $original_path Original image path
	 * @return void
	 */
	private function serve_original_image( $original_path ) {
		// Determine original format from file extension
		$extension = strtolower( pathinfo( $original_path, PATHINFO_EXTENSION ) );
		$format_map = array(
			'jpg'  => 'jpeg',
			'jpeg' => 'jpeg',
			'png'  => 'png',
			'gif'  => 'gif',
		);

		$format = isset( $format_map[ $extension ] ) ? $format_map[ $extension ] : 'jpeg';
		$this->serve_image_file( $original_path, $format );
	}

	/**
	 * Serve image file with proper headers
	 *
	 * @param string $file_path Path to image file
	 * @param string $format Image format (jpeg, png, gif, webp, avif)
	 * @return void
	 */
	private function serve_image_file( $file_path, $format ) {
		// Get security validator
		$security_validator = WP_Image_Optimizer_Security_Validator::get_instance();
		
		// Validate format parameter
		$format = strtolower( trim( $format ) );
		$allowed_formats = array( 'jpeg', 'png', 'gif', 'webp', 'avif' );
		if ( ! in_array( $format, $allowed_formats, true ) ) {
			$this->serve_error( 400, 'Invalid image format' );
			return;
		}
		
		// Validate file path
		$upload_dir = wp_upload_dir();
		$base_dir = wp_normalize_path( $upload_dir['basedir'] );
		$validated_path = $security_validator->sanitize_file_path( $file_path, $base_dir );
		
		if ( is_wp_error( $validated_path ) ) {
			$this->serve_error( 403, 'Access denied' );
			return;
		}
		
		// Validate file exists and is readable
		if ( ! file_exists( $validated_path ) || ! is_readable( $validated_path ) ) {
			$this->serve_error( 404, 'Image file not found or not readable' );
			return;
		}

		// Get file information
		$file_info = $this->file_handler->get_file_info( $validated_path );
		if ( is_wp_error( $file_info ) ) {
			$this->serve_error( 500, 'Could not read file information' );
			return;
		}

		// Set MIME type
		$mime_types = array(
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'avif' => 'image/avif',
		);

		$mime_type = isset( $mime_types[ $format ] ) ? $mime_types[ $format ] : 'image/jpeg';

		// Set HTTP headers
		$this->set_image_headers( $file_info, $mime_type );

		// Check if client has cached version
		if ( $this->is_client_cached( $file_info ) ) {
			http_response_code( 304 );
			exit;
		}

		// Serve the file
		$this->output_image_file( $validated_path );
	}

	/**
	 * Set HTTP headers for image serving
	 *
	 * @param array  $file_info File information
	 * @param string $mime_type MIME type
	 * @return void
	 */
	private function set_image_headers( $file_info, $mime_type ) {
		// Validate MIME type to prevent header injection
		$allowed_mime_types = array(
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'image/avif',
		);
		
		if ( ! in_array( $mime_type, $allowed_mime_types, true ) ) {
			$mime_type = 'application/octet-stream'; // Fallback to safe default
		}
		
		// Content type
		header( 'Content-Type: ' . $mime_type );
		
		// Content length
		$size = isset( $file_info['size'] ) ? (int) $file_info['size'] : 0;
		header( 'Content-Length: ' . $size );
		
		// Last modified
		$modified_time = isset( $file_info['modified_time'] ) ? (int) $file_info['modified_time'] : time();
		$last_modified = gmdate( 'D, d M Y H:i:s', $modified_time ) . ' GMT';
		header( 'Last-Modified: ' . $last_modified );
		
		// ETag - use hash of file path, modified time and size for uniqueness
		$etag_data = isset( $file_info['path'] ) ? $file_info['path'] . $modified_time . $size : uniqid( '', true );
		$etag = '"' . md5( $etag_data ) . '"';
		header( 'ETag: ' . $etag );
		
		// Cache control
		$cache_duration = $this->get_cache_duration();
		header( 'Cache-Control: public, max-age=' . $cache_duration . ', immutable' );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $cache_duration ) . ' GMT' );
		
		// Vary header for content negotiation
		header( 'Vary: Accept' );
		
		// Security headers
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' ); // Prevent clickjacking
		header( 'Referrer-Policy: strict-origin-when-cross-origin' ); // Control referrer information
		
		// Disable content type sniffing
		header( 'X-Download-Options: noopen' ); // For IE8+
		
		// Set Content-Disposition to inline to ensure browser renders the image
		header( 'Content-Disposition: inline; filename="' . basename( $file_info['path'] ) . '"' );
	}

	/**
	 * Check if client has cached version
	 *
	 * @param array $file_info File information
	 * @return bool True if client has cached version
	 */
	private function is_client_cached( $file_info ) {
		// Check If-Modified-Since header
		if ( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {
			$if_modified_since = strtotime( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
			if ( $if_modified_since >= $file_info['modified_time'] ) {
				return true;
			}
		}

		// Check If-None-Match header (ETag)
		if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) {
			$etag = '"' . md5( $file_info['path'] . $file_info['modified_time'] . $file_info['size'] ) . '"';
			$client_etag = trim( $_SERVER['HTTP_IF_NONE_MATCH'], '"' );
			if ( $etag === '"' . $client_etag . '"' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Output image file content
	 *
	 * @param string $file_path Path to image file
	 * @return void
	 */
	private function output_image_file( $file_path ) {
		// Final security check before output
		$upload_dir = wp_upload_dir();
		$base_dir = wp_normalize_path( $upload_dir['basedir'] );
		$file_path = wp_normalize_path( $file_path );
		
		// Ensure file is within upload directory
		if ( strpos( $file_path, $base_dir ) !== 0 ) {
			$this->serve_error( 403, 'Access denied' );
			return;
		}
		
		// Verify file exists and is readable
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			$this->serve_error( 404, 'File not found or not readable' );
			return;
		}
		
		// Verify file is an image by checking MIME type
		$mime_info = wp_check_filetype( $file_path );
		$allowed_mime_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif' );
		
		if ( ! in_array( $mime_info['type'], $allowed_mime_types, true ) ) {
			$this->serve_error( 403, 'Invalid file type' );
			return;
		}
		
		// Use readfile for efficient file output
		if ( function_exists( 'readfile' ) ) {
			readfile( $file_path );
		} else {
			// Fallback for systems without readfile
			$handle = fopen( $file_path, 'rb' );
			if ( $handle ) {
				while ( ! feof( $handle ) ) {
					echo fread( $handle, 8192 );
					if ( ob_get_level() ) {
						ob_flush();
					}
					flush();
				}
				fclose( $handle );
			}
		}
		
		exit;
	}

	/**
	 * Get cache duration in seconds
	 *
	 * @return int Cache duration
	 */
	private function get_cache_duration() {
		// Default to 1 year for images
		$default_duration = YEAR_IN_SECONDS;
		
		/**
		 * Filter the cache duration for optimized images
		 *
		 * @param int $duration Cache duration in seconds
		 */
		return apply_filters( 'wp_image_optimizer_cache_duration', $default_duration );
	}

	/**
	 * Serve error response
	 *
	 * @param int    $code HTTP status code
	 * @param string $message Error message
	 * @return void
	 */
	private function serve_error( $code, $message ) {
		http_response_code( $code );
		header( 'Content-Type: text/plain' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		
		// Add security headers
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: DENY' );
		
		// Log security-related errors
		if ( $code === 403 || $code === 401 || $code === 404 ) {
			$error_handler = WP_Image_Optimizer_Error_Handler::get_instance();
			$security_validator = WP_Image_Optimizer_Security_Validator::get_instance();
			
			$error_handler->log_error(
				$message,
				$code < 404 ? 'warning' : 'notice',
				'security',
				array(
					'http_code' => $code,
					'client_ip' => $security_validator->get_client_ip(),
					'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? 
						$security_validator->sanitize_text_input( $_SERVER['HTTP_USER_AGENT'] ) : '',
					'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? 
						$security_validator->sanitize_text_input( $_SERVER['REQUEST_URI'] ) : '',
				)
			);
		}
		
		echo esc_html( $message );
		exit;
	}

	/**
	 * Log conversion error
	 *
	 * @param string   $original_path Original image path
	 * @param string   $format Target format
	 * @param WP_Error $error Error object
	 * @return void
	 */
	private function log_conversion_error( $original_path, $format, $error ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'WP Image Optimizer: Failed to convert %s to %s format. Error: %s',
				basename( $original_path ),
				$format,
				$error->get_error_message()
			) );
		}
	}

	/**
	 * Get browser capabilities information
	 *
	 * @return array Browser capabilities
	 */
	public function get_browser_capabilities() {
		// Get security validator
		$security_validator = WP_Image_Optimizer_Security_Validator::get_instance();
		
		$accept_header = $this->get_accept_header();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? 
			$security_validator->sanitize_text_input( $_SERVER['HTTP_USER_AGENT'] ) : '';
		
		return array(
			'accept_header' => $accept_header,
			'supports_webp' => $this->browser_supports_format( 'webp', $accept_header ),
			'supports_avif' => $this->browser_supports_format( 'avif', $accept_header ),
			'user_agent' => $user_agent,
		);
	}

	/**
	 * Test format negotiation
	 *
	 * @param string $test_file Test file path
	 * @return array Test results
	 */
	public function test_format_negotiation( $test_file ) {
		$results = array(
			'original_exists' => false,
			'browser_capabilities' => $this->get_browser_capabilities(),
			'best_format' => null,
			'available_formats' => array(),
		);

		// Validate test file
		$original_path = $this->validate_requested_file( $test_file );
		if ( is_wp_error( $original_path ) ) {
			$results['error'] = $original_path->get_error_message();
			return $results;
		}

		$results['original_exists'] = file_exists( $original_path );
		
		if ( $results['original_exists'] ) {
			// Check available converted formats
			$settings = $this->settings_manager->get_settings();
			foreach ( $this->format_preference as $format ) {
				if ( $settings['formats'][ $format ]['enabled'] ) {
					$converted_path = $this->file_handler->generate_converted_path( $original_path, $format );
					if ( ! is_wp_error( $converted_path ) ) {
						$results['available_formats'][ $format ] = file_exists( $converted_path );
					}
				}
			}

			// Determine best format
			$results['best_format'] = $this->detect_best_format();
		}

		return $results;
	}
}