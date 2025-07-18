<?php
/**
 * Security Validator class for comprehensive input validation and security checks
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security Validator class
 * 
 * Provides comprehensive input validation, sanitization, and security checks
 * for the WordPress Image Optimizer plugin.
 */
class WP_Image_Optimizer_Security_Validator {

	/**
	 * Instance of the security validator
	 *
	 * @var WP_Image_Optimizer_Security_Validator|null
	 */
	private static $instance = null;

	/**
	 * Rate limiting data
	 *
	 * @var array
	 */
	private $rate_limits = array();

	/**
	 * Rate limit option name
	 *
	 * @var string
	 */
	const RATE_LIMIT_OPTION = 'wp_image_optimizer_rate_limits';

	/**
	 * Get singleton instance
	 *
	 * @return WP_Image_Optimizer_Security_Validator
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
		$this->init();
	}

	/**
	 * Initialize security validator
	 */
	private function init() {
		// Load rate limiting data
		$this->rate_limits = get_option( self::RATE_LIMIT_OPTION, array() );
		
		// Clean up expired rate limits
		$this->cleanup_expired_rate_limits();
	}

	/**
	 * Validate and sanitize text input
	 *
	 * @param string $input Input to sanitize
	 * @param bool   $allow_html Whether to allow HTML
	 * @param array  $allowed_html Array of allowed HTML tags
	 * @return string Sanitized input
	 */
	public function sanitize_text_input( $input, $allow_html = false, $allowed_html = array() ) {
		if ( ! is_string( $input ) ) {
			return '';
		}

		// Strip all HTML if not allowed
		if ( ! $allow_html ) {
			return sanitize_text_field( $input );
		}

		// Use wp_kses to allow specific HTML tags
		if ( empty( $allowed_html ) ) {
			$allowed_html = wp_kses_allowed_html( 'post' );
		}

		return wp_kses( $input, $allowed_html );
	}

	/**
	 * Validate and sanitize integer input
	 *
	 * @param mixed $input Input to sanitize
	 * @param int   $min Minimum allowed value
	 * @param int   $max Maximum allowed value
	 * @param int   $default Default value if input is invalid
	 * @return int Sanitized integer
	 */
	public function sanitize_integer( $input, $min = 0, $max = PHP_INT_MAX, $default = 0 ) {
		$input = filter_var( $input, FILTER_VALIDATE_INT );
		
		if ( false === $input ) {
			return $default;
		}

		return max( $min, min( $max, $input ) );
	}

	/**
	 * Validate and sanitize float input
	 *
	 * @param mixed $input Input to sanitize
	 * @param float $min Minimum allowed value
	 * @param float $max Maximum allowed value
	 * @param float $default Default value if input is invalid
	 * @return float Sanitized float
	 */
	public function sanitize_float( $input, $min = 0.0, $max = PHP_FLOAT_MAX, $default = 0.0 ) {
		$input = filter_var( $input, FILTER_VALIDATE_FLOAT );
		
		if ( false === $input ) {
			return $default;
		}

		return max( $min, min( $max, $input ) );
	}

	/**
	 * Validate and sanitize boolean input
	 *
	 * @param mixed $input Input to sanitize
	 * @param bool  $default Default value if input is invalid
	 * @return bool Sanitized boolean
	 */
	public function sanitize_boolean( $input, $default = false ) {
		if ( is_bool( $input ) ) {
			return $input;
		}

		if ( is_string( $input ) ) {
			$input = strtolower( $input );
			if ( in_array( $input, array( 'true', 'yes', '1', 'on' ), true ) ) {
				return true;
			}
			if ( in_array( $input, array( 'false', 'no', '0', 'off' ), true ) ) {
				return false;
			}
		}

		if ( is_numeric( $input ) ) {
			return (bool) $input;
		}

		return $default;
	}

	/**
	 * Validate and sanitize array input
	 *
	 * @param mixed  $input Input to sanitize
	 * @param string $sanitize_callback Callback function to sanitize array items
	 * @param array  $allowed_keys Array of allowed keys (empty for any keys)
	 * @return array Sanitized array
	 */
	public function sanitize_array( $input, $sanitize_callback = 'sanitize_text_field', $allowed_keys = array() ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $input as $key => $value ) {
			// Skip if key is not allowed (when allowed_keys is not empty)
			if ( ! empty( $allowed_keys ) && ! in_array( $key, $allowed_keys, true ) ) {
				continue;
			}

			// Sanitize key
			$key = sanitize_key( $key );

			// Sanitize value based on type
			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_array( $value, $sanitize_callback );
			} elseif ( is_callable( $sanitize_callback ) ) {
				$sanitized[ $key ] = call_user_func( $sanitize_callback, $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Validate and sanitize URL input
	 *
	 * @param string $input URL to sanitize
	 * @param array  $allowed_protocols Allowed protocols
	 * @param string $default Default value if URL is invalid
	 * @return string Sanitized URL
	 */
	public function sanitize_url( $input, $allowed_protocols = array(), $default = '' ) {
		if ( empty( $allowed_protocols ) ) {
			$allowed_protocols = array( 'http', 'https' );
		}

		$sanitized_url = esc_url_raw( $input, $allowed_protocols );
		
		if ( empty( $sanitized_url ) ) {
			return $default;
		}

		return $sanitized_url;
	}

	/**
	 * Validate and sanitize file path
	 *
	 * @param string $input File path to sanitize
	 * @param string $base_dir Base directory to restrict paths to
	 * @param array  $allowed_extensions Allowed file extensions
	 * @return string|WP_Error Sanitized file path or error
	 */
	public function sanitize_file_path( $input, $base_dir = '', $allowed_extensions = array() ) {
		if ( empty( $input ) ) {
			return new WP_Error( 'empty_path', __( 'File path cannot be empty.', 'wp-image-optimizer' ) );
		}

		// Remove any null bytes (null byte injection)
		$input = str_replace( chr( 0 ), '', $input );
		
		// Normalize path separators
		$input = wp_normalize_path( $input );
		
		// Remove any directory traversal attempts
		if ( strpos( $input, '..' ) !== false ) {
			return new WP_Error( 
				'invalid_path', 
				__( 'File path contains invalid directory traversal.', 'wp-image-optimizer' ) 
			);
		}

		// If base directory is provided, ensure path is within it
		if ( ! empty( $base_dir ) ) {
			$base_dir = wp_normalize_path( $base_dir );
			
			// Convert to absolute path if relative
			if ( ! path_is_absolute( $input ) ) {
				$input = $base_dir . '/' . ltrim( $input, '/' );
			}
			
			// Ensure path is within base directory
			if ( strpos( $input, $base_dir ) !== 0 ) {
				return new WP_Error( 
					'security_violation', 
					__( 'File path is outside the allowed directory.', 'wp-image-optimizer' ) 
				);
			}
		}

		// Validate file extension if allowed extensions are provided
		if ( ! empty( $allowed_extensions ) ) {
			$extension = strtolower( pathinfo( $input, PATHINFO_EXTENSION ) );
			if ( ! in_array( $extension, $allowed_extensions, true ) ) {
				return new WP_Error( 
					'invalid_extension', 
					sprintf( 
						/* translators: %s: file extension */
						__( 'File extension "%s" is not allowed.', 'wp-image-optimizer' ), 
						$extension 
					) 
				);
			}
		}

		return $input;
	}

	/**
	 * Validate file upload
	 *
	 * @param array $file File data from $_FILES
	 * @param array $allowed_mime_types Allowed MIME types
	 * @param int   $max_size Maximum file size in bytes
	 * @return array|WP_Error Validated file data or error
	 */
	public function validate_file_upload( $file, $allowed_mime_types = array(), $max_size = 0 ) {
		// Check if file upload is valid
		if ( ! isset( $file['tmp_name'] ) || empty( $file['tmp_name'] ) ) {
			return new WP_Error( 'invalid_upload', __( 'No file was uploaded or upload failed.', 'wp-image-optimizer' ) );
		}

		// Check for upload errors
		if ( isset( $file['error'] ) && $file['error'] !== UPLOAD_ERR_OK ) {
			$error_message = $this->get_upload_error_message( $file['error'] );
			return new WP_Error( 'upload_error', $error_message );
		}

		// Check file size
		if ( $max_size > 0 && $file['size'] > $max_size ) {
			return new WP_Error( 
				'file_too_large', 
				sprintf( 
					/* translators: %1$s: file size, %2$s: max allowed size */
					__( 'File size (%1$s) exceeds maximum allowed size (%2$s).', 'wp-image-optimizer' ), 
					size_format( $file['size'] ), 
					size_format( $max_size ) 
				) 
			);
		}

		// Validate MIME type
		$file_type = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		
		if ( empty( $file_type['type'] ) ) {
			return new WP_Error( 'invalid_mime_type', __( 'Could not determine file type.', 'wp-image-optimizer' ) );
		}

		// Check against allowed MIME types if provided
		if ( ! empty( $allowed_mime_types ) && ! in_array( $file_type['type'], $allowed_mime_types, true ) ) {
			return new WP_Error( 
				'disallowed_mime_type', 
				sprintf( 
					/* translators: %1$s: file MIME type, %2$s: allowed MIME types */
					__( 'File type "%1$s" is not allowed. Allowed types: %2$s', 'wp-image-optimizer' ), 
					$file_type['type'], 
					implode( ', ', $allowed_mime_types ) 
				) 
			);
		}

		// Validate file contents (check for PHP code, etc.)
		$file_contents = file_get_contents( $file['tmp_name'] );
		if ( $this->contains_php_code( $file_contents ) && ! in_array( 'application/x-php', $allowed_mime_types, true ) ) {
			return new WP_Error( 'security_threat', __( 'File contains potentially malicious code.', 'wp-image-optimizer' ) );
		}

		// Return validated file data
		return array(
			'name' => sanitize_file_name( $file['name'] ),
			'type' => $file_type['type'],
			'tmp_name' => $file['tmp_name'],
			'size' => $file['size'],
			'ext' => $file_type['ext'],
			'proper_filename' => $file_type['proper_filename'],
		);
	}

	/**
	 * Verify nonce
	 *
	 * @param string $nonce Nonce value
	 * @param string $action Nonce action
	 * @return bool True if nonce is valid
	 */
	public function verify_nonce( $nonce, $action ) {
		return wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Check user capability
	 *
	 * @param string $capability Capability to check
	 * @return bool True if user has capability
	 */
	public function current_user_can( $capability ) {
		return current_user_can( $capability );
	}

	/**
	 * Apply rate limiting
	 *
	 * @param string $key Rate limit key (e.g., IP address or user ID)
	 * @param string $action Action being rate limited
	 * @param int    $limit Maximum number of requests
	 * @param int    $period Time period in seconds
	 * @return bool|WP_Error True if within rate limit, WP_Error if limit exceeded
	 */
	public function check_rate_limit( $key, $action, $limit = 10, $period = 60 ) {
		$current_time = time();
		$rate_key = md5( $key . '_' . $action );
		
		// Initialize rate limit entry if not exists
		if ( ! isset( $this->rate_limits[ $rate_key ] ) ) {
			$this->rate_limits[ $rate_key ] = array(
				'count' => 0,
				'first_request' => $current_time,
				'last_request' => $current_time,
			);
		}
		
		$rate_data = &$this->rate_limits[ $rate_key ];
		
		// Reset count if period has passed
		if ( $current_time - $rate_data['first_request'] > $period ) {
			$rate_data['count'] = 0;
			$rate_data['first_request'] = $current_time;
		}
		
		// Increment request count
		$rate_data['count']++;
		$rate_data['last_request'] = $current_time;
		
		// Save updated rate limits
		update_option( self::RATE_LIMIT_OPTION, $this->rate_limits );
		
		// Check if limit exceeded
		if ( $rate_data['count'] > $limit ) {
			$retry_after = $period - ( $current_time - $rate_data['first_request'] );
			
			return new WP_Error( 
				'rate_limit_exceeded', 
				sprintf( 
					/* translators: %d: seconds until retry allowed */
					__( 'Rate limit exceeded. Please try again in %d seconds.', 'wp-image-optimizer' ), 
					$retry_after 
				),
				array( 'retry_after' => $retry_after )
			);
		}
		
		return true;
	}

	/**
	 * Clean up expired rate limits
	 */
	private function cleanup_expired_rate_limits() {
		$current_time = time();
		$max_age = 24 * HOUR_IN_SECONDS; // Keep rate limit data for 24 hours max
		
		foreach ( $this->rate_limits as $key => $data ) {
			if ( $current_time - $data['last_request'] > $max_age ) {
				unset( $this->rate_limits[ $key ] );
			}
		}
		
		update_option( self::RATE_LIMIT_OPTION, $this->rate_limits );
	}

	/**
	 * Get upload error message
	 *
	 * @param int $error_code PHP upload error code
	 * @return string Error message
	 */
	private function get_upload_error_message( $error_code ) {
		$upload_errors = array(
			UPLOAD_ERR_INI_SIZE => __( 'The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'wp-image-optimizer' ),
			UPLOAD_ERR_FORM_SIZE => __( 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.', 'wp-image-optimizer' ),
			UPLOAD_ERR_PARTIAL => __( 'The uploaded file was only partially uploaded.', 'wp-image-optimizer' ),
			UPLOAD_ERR_NO_FILE => __( 'No file was uploaded.', 'wp-image-optimizer' ),
			UPLOAD_ERR_NO_TMP_DIR => __( 'Missing a temporary folder.', 'wp-image-optimizer' ),
			UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'wp-image-optimizer' ),
			UPLOAD_ERR_EXTENSION => __( 'A PHP extension stopped the file upload.', 'wp-image-optimizer' ),
		);

		if ( isset( $upload_errors[ $error_code ] ) ) {
			return $upload_errors[ $error_code ];
		}

		return __( 'Unknown upload error.', 'wp-image-optimizer' );
	}

	/**
	 * Check if content contains PHP code
	 *
	 * @param string $content Content to check
	 * @return bool True if content contains PHP code
	 */
	private function contains_php_code( $content ) {
		$suspicious_patterns = array(
			'/<\?(?:php|=)/i',
			'/eval\s*\(/i',
			'/base64_decode\s*\(/i',
			'/system\s*\(/i',
			'/exec\s*\(/i',
			'/shell_exec\s*\(/i',
			'/passthru\s*\(/i',
			'/preg_replace\s*\(.+\/e/i',
			'/create_function\s*\(/i',
			'/include\s*\([\'"]|include_once\s*\([\'"]|require\s*\([\'"]|require_once\s*\([\'"]/',
		);

		foreach ( $suspicious_patterns as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate AJAX request
	 *
	 * @param string $action Nonce action
	 * @param string $nonce_field Nonce field name
	 * @param string $capability Required capability
	 * @return bool|WP_Error True if valid, WP_Error otherwise
	 */
	public function validate_ajax_request( $action, $nonce_field = 'nonce', $capability = 'manage_options' ) {
		// Check if nonce exists
		if ( ! isset( $_POST[ $nonce_field ] ) ) {
			return new WP_Error( 'missing_nonce', __( 'Security token is missing.', 'wp-image-optimizer' ) );
		}

		// Verify nonce
		$nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) );
		if ( ! $this->verify_nonce( $nonce, $action ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'wp-image-optimizer' ) );
		}

		// Check user capability
		if ( ! $this->current_user_can( $capability ) ) {
			return new WP_Error( 'insufficient_permissions', __( 'You do not have permission to perform this action.', 'wp-image-optimizer' ) );
		}

		return true;
	}

	/**
	 * Apply rate limiting to AJAX requests
	 *
	 * @param string $action Action being performed
	 * @param int    $limit Maximum number of requests
	 * @param int    $period Time period in seconds
	 * @return bool|WP_Error True if within rate limit, WP_Error if limit exceeded
	 */
	public function apply_ajax_rate_limit( $action, $limit = 10, $period = 60 ) {
		// Get client IP address
		$ip = $this->get_client_ip();
		
		// Apply rate limiting
		return $this->check_rate_limit( $ip, 'ajax_' . $action, $limit, $period );
	}

	/**
	 * Get client IP address
	 *
	 * @return string Client IP address
	 */
	public function get_client_ip() {
		$ip_headers = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $ip_headers as $header ) {
			if ( isset( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				
				// Handle multiple IPs (use first one)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip_list = explode( ',', $ip );
					$ip = trim( $ip_list[0] );
				}
				
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '127.0.0.1'; // Default to localhost if no valid IP found
	}
}