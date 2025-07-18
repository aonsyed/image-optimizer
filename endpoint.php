<?php
/**
 * Image serving endpoint for on-demand conversion
 *
 * This file handles direct image requests and serves optimized versions
 * based on browser capabilities. It's designed to be called by web server
 * rewrite rules when converted images don't exist.
 *
 * @package WP_Image_Optimizer
 */

// Security check - ensure this is called in the right context
if ( ! defined( 'ABSPATH' ) ) {
	// Try to load WordPress if not already loaded
	$wp_load_paths = array(
		dirname( __FILE__ ) . '/../../wp-load.php',
		dirname( __FILE__ ) . '/../../../wp-load.php',
		dirname( __FILE__ ) . '/../../../../wp-load.php',
		dirname( __FILE__ ) . '/../../../../../wp-load.php',
	);

	$wp_loaded = false;
	foreach ( $wp_load_paths as $wp_load_path ) {
		if ( file_exists( $wp_load_path ) ) {
			require_once $wp_load_path;
			$wp_loaded = true;
			break;
		}
	}

	if ( ! $wp_loaded ) {
		http_response_code( 500 );
		header( 'Content-Type: text/plain' );
		echo 'WordPress not found';
		exit;
	}
}

// Ensure plugin classes are loaded
if ( ! class_exists( 'WP_Image_Optimizer_Image_Handler' ) ) {
	$plugin_file = dirname( __FILE__ ) . '/wp-image-optimizer.php';
	if ( file_exists( $plugin_file ) ) {
		require_once $plugin_file;
	} else {
		http_response_code( 500 );
		header( 'Content-Type: text/plain' );
		echo 'Plugin not found';
		exit;
	}
}

/**
 * Main endpoint handler
 */
function wp_image_optimizer_handle_endpoint() {
	try {
		// Get requested file from various sources
		$requested_file = wp_image_optimizer_get_requested_file();
		
		if ( empty( $requested_file ) ) {
			wp_image_optimizer_serve_error( 400, 'No file specified' );
			return;
		}

		// Apply rate limiting
		$rate_limit_result = wp_image_optimizer_apply_rate_limit();
		if ( is_wp_error( $rate_limit_result ) ) {
			$retry_after = $rate_limit_result->get_error_data( 'retry_after' ) ?: 60;
			header( 'Retry-After: ' . $retry_after );
			wp_image_optimizer_serve_error( 429, $rate_limit_result->get_error_message() );
			return;
		}

		// Create image handler and process request
		$image_handler = new WP_Image_Optimizer_Image_Handler();
		$image_handler->handle_image_request( $requested_file );

	} catch ( Exception $e ) {
		// Log the error if debugging is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WP Image Optimizer Endpoint Error: ' . $e->getMessage() );
		}
		
		wp_image_optimizer_serve_error( 500, 'Internal server error' );
	}
}

/**
 * Get requested file from various sources
 *
 * @return string|null Requested file path
 */
function wp_image_optimizer_get_requested_file() {
	// Get security validator
	$security_validator = WP_Image_Optimizer_Security_Validator::get_instance();
	
	// Try different methods to get the requested file
	
	// Method 1: GET parameter (for testing and fallback)
	if ( isset( $_GET['file'] ) && ! empty( $_GET['file'] ) ) {
		return $security_validator->sanitize_text_input( wp_unslash( $_GET['file'] ) );
	}

	// Method 2: REQUEST_URI parsing
	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		$request_uri = $_SERVER['REQUEST_URI'];
		
		// Remove query string
		$request_uri = strtok( $request_uri, '?' );
		
		// Decode URL
		$request_uri = urldecode( $request_uri );
		
		// Extract file path from URI
		// This assumes the endpoint is called with the file path as part of the URL
		if ( preg_match( '/\.(jpe?g|png|gif)$/i', $request_uri ) ) {
			// Sanitize the path
			return $security_validator->sanitize_text_input( $request_uri );
		}
	}

	// Method 3: PATH_INFO
	if ( isset( $_SERVER['PATH_INFO'] ) && ! empty( $_SERVER['PATH_INFO'] ) ) {
		$path_info = $_SERVER['PATH_INFO'];
		if ( preg_match( '/\.(jpe?g|png|gif)$/i', $path_info ) ) {
			// Sanitize the path
			return $security_validator->sanitize_text_input( $path_info );
		}
	}

	// Method 4: SCRIPT_NAME parsing for direct calls
	if ( isset( $_SERVER['SCRIPT_NAME'] ) && isset( $_SERVER['REQUEST_URI'] ) ) {
		$script_name = $_SERVER['SCRIPT_NAME'];
		$request_uri = strtok( $_SERVER['REQUEST_URI'], '?' );
		
		// If the request URI contains more than just the script name, extract the difference
		if ( strpos( $request_uri, $script_name ) === 0 ) {
			$file_path = substr( $request_uri, strlen( $script_name ) );
			$file_path = ltrim( $file_path, '/' );
			
			if ( ! empty( $file_path ) && preg_match( '/\.(jpe?g|png|gif)$/i', $file_path ) ) {
				// Sanitize the path
				return $security_validator->sanitize_text_input( $file_path );
			}
		}
	}

	return null;
}

/**
 * Serve error response
 *
 * @param int    $code HTTP status code
 * @param string $message Error message
 * @return void
 */
function wp_image_optimizer_serve_error( $code, $message ) {
	http_response_code( $code );
	header( 'Content-Type: text/plain' );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	
	// Add security headers
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: DENY' );
	
	// Log security-related errors
	if ( $code === 403 || $code === 401 ) {
		$error_handler = WP_Image_Optimizer_Error_Handler::get_instance();
		$security_validator = WP_Image_Optimizer_Security_Validator::get_instance();
		
		$error_handler->log_error(
			$message,
			'warning',
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
 * Check if request should be handled by this endpoint
 *
 * @return bool True if request should be handled
 */
function wp_image_optimizer_should_handle_request() {
	// Don't handle admin requests
	if ( is_admin() ) {
		return false;
	}

	// Don't handle if plugin is not active
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	
	if ( ! is_plugin_active( 'wp-image-optimizer/wp-image-optimizer.php' ) ) {
		return false;
	}

	// Check if image optimization is enabled
	$settings = WP_Image_Optimizer_Settings_Manager::get_settings();
	if ( ! $settings['enabled'] ) {
		return false;
	}

	return true;
}

/**
 * Add CORS headers if needed
 *
 * @return void
 */
function wp_image_optimizer_add_cors_headers() {
	// Only add CORS headers if explicitly enabled
	if ( defined( 'WP_IMAGE_OPTIMIZER_CORS' ) && WP_IMAGE_OPTIMIZER_CORS ) {
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET' );
		header( 'Access-Control-Allow-Headers: Accept' );
	}
}

/**
 * Log request for debugging
 *
 * @param string $requested_file Requested file
 * @return void
 */
function wp_image_optimizer_log_request( $requested_file ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		// Get security validator
		$security_validator = WP_Image_Optimizer_Security_Validator::get_instance();
		
		$log_data = array(
			'timestamp' => current_time( 'mysql' ),
			'requested_file' => $requested_file,
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? 
				$security_validator->sanitize_text_input( $_SERVER['HTTP_USER_AGENT'] ) : '',
			'accept_header' => isset( $_SERVER['HTTP_ACCEPT'] ) ? 
				$security_validator->sanitize_text_input( $_SERVER['HTTP_ACCEPT'] ) : '',
			'referer' => isset( $_SERVER['HTTP_REFERER'] ) ? 
				$security_validator->sanitize_text_input( $_SERVER['HTTP_REFERER'] ) : '',
			'remote_addr' => isset( $_SERVER['REMOTE_ADDR'] ) ? 
				$security_validator->sanitize_text_input( $_SERVER['REMOTE_ADDR'] ) : '',
		);
		
		error_log( 'WP Image Optimizer Request: ' . wp_json_encode( $log_data ) );
	}
}

/**
 * Apply rate limiting to conversion endpoint
 *
 * @return bool|WP_Error True if within rate limit, WP_Error if limit exceeded
 */
function wp_image_optimizer_apply_rate_limit() {
	// Get settings
	$settings = WP_Image_Optimizer_Settings_Manager::get_settings();
	
	// Default rate limits
	$limit = 30; // 30 requests
	$period = 60; // per minute
	
	// Allow overriding via settings or constants
	if ( defined( 'WP_IMAGE_OPTIMIZER_RATE_LIMIT' ) ) {
		$limit = (int) WP_IMAGE_OPTIMIZER_RATE_LIMIT;
	}
	
	if ( defined( 'WP_IMAGE_OPTIMIZER_RATE_PERIOD' ) ) {
		$period = (int) WP_IMAGE_OPTIMIZER_RATE_PERIOD;
	}
	
	// Skip rate limiting for logged-in administrators
	if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
		return true;
	}
	
	// Get security validator instance
	$security_validator = WP_Image_Optimizer_Security_Validator::get_instance();
	
	// Get client IP
	$ip = $security_validator->get_client_ip();
	
	// Apply rate limiting
	return $security_validator->check_rate_limit( $ip, 'image_conversion', $limit, $period );
}

// Main execution
if ( wp_image_optimizer_should_handle_request() ) {
	// Add CORS headers if needed
	wp_image_optimizer_add_cors_headers();
	
	// Log request if debugging is enabled
	$requested_file = wp_image_optimizer_get_requested_file();
	if ( $requested_file ) {
		wp_image_optimizer_log_request( $requested_file );
	}
	
	// Handle the request
	wp_image_optimizer_handle_endpoint();
} else {
	// Plugin not active or disabled, serve 404
	wp_image_optimizer_serve_error( 404, 'Image optimization not available' );
}