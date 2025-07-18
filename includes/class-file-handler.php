<?php
/**
 * File Handler class for secure file system operations
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File Handler class
 * 
 * Handles secure file system operations for image conversion,
 * including path generation, validation, and cleanup operations.
 */
class WP_Image_Optimizer_File_Handler {

	/**
	 * Maximum allowed file size (10MB by default)
	 *
	 * @var int
	 */
	private $max_file_size;

	/**
	 * Allowed MIME types for processing
	 *
	 * @var array
	 */
	private $allowed_mime_types;

	/**
	 * WordPress upload directory information
	 *
	 * @var array
	 */
	private $upload_dir;

	/**
	 * Constructor
	 *
	 * @param array $settings Optional settings array
	 */
	public function __construct( $settings = array() ) {
		$this->max_file_size = isset( $settings['max_file_size'] ) ? 
			(int) $settings['max_file_size'] : 10485760; // 10MB default

		$this->allowed_mime_types = isset( $settings['allowed_mime_types'] ) ? 
			$settings['allowed_mime_types'] : array( 'image/jpeg', 'image/png', 'image/gif' );

		$this->upload_dir = wp_upload_dir();
	}

	/**
	 * Generate converted image file path
	 *
	 * @param string $original_path Original image file path
	 * @param string $format Target format (webp, avif)
	 * @return string|WP_Error Converted file path or error
	 */
	public function generate_converted_path( $original_path, $format ) {
		// Validate input parameters
		if ( empty( $original_path ) || empty( $format ) ) {
			return new WP_Error( 
				'invalid_parameters', 
				__( 'Original path and format are required.', 'wp-image-optimizer' ) 
			);
		}

		// Validate format
		$allowed_formats = array( 'webp', 'avif' );
		if ( ! in_array( strtolower( $format ), $allowed_formats, true ) ) {
			return new WP_Error( 
				'invalid_format', 
				sprintf( 
					/* translators: %s: format name */
					__( 'Invalid format: %s. Allowed formats: webp, avif', 'wp-image-optimizer' ), 
					$format 
				) 
			);
		}

		// Sanitize the original path
		$original_path = $this->sanitize_file_path( $original_path );
		if ( is_wp_error( $original_path ) ) {
			return $original_path;
		}

		// Check if original file exists
		if ( ! file_exists( $original_path ) ) {
			return new WP_Error( 
				'file_not_found', 
				sprintf( 
					/* translators: %s: file path */
					__( 'Original file not found: %s', 'wp-image-optimizer' ), 
					$original_path 
				) 
			);
		}

		// Generate new path with format extension
		$path_info = pathinfo( $original_path );
		$converted_path = $path_info['dirname'] . '/' . $path_info['filename'] . '.' . strtolower( $format );

		return $converted_path;
	}

	/**
	 * Check if file exists and is readable
	 *
	 * @param string $file_path File path to check
	 * @return bool|WP_Error True if file exists and is readable, error otherwise
	 */
	public function file_exists_and_readable( $file_path ) {
		if ( empty( $file_path ) ) {
			return new WP_Error( 'empty_path', __( 'File path cannot be empty.', 'wp-image-optimizer' ) );
		}

		$file_path = $this->sanitize_file_path( $file_path );
		if ( is_wp_error( $file_path ) ) {
			return $file_path;
		}

		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		if ( ! is_readable( $file_path ) ) {
			return new WP_Error( 
				'file_not_readable', 
				sprintf( 
					/* translators: %s: file path */
					__( 'File is not readable: %s', 'wp-image-optimizer' ), 
					$file_path 
				) 
			);
		}

		return true;
	}

	/**
	 * Validate file permissions for writing
	 *
	 * @param string $file_path File path to check
	 * @return bool|WP_Error True if writable, error otherwise
	 */
	public function validate_write_permissions( $file_path ) {
		if ( empty( $file_path ) ) {
			return new WP_Error( 'empty_path', __( 'File path cannot be empty.', 'wp-image-optimizer' ) );
		}

		$file_path = $this->sanitize_file_path( $file_path );
		if ( is_wp_error( $file_path ) ) {
			return $file_path;
		}

		$directory = dirname( $file_path );

		// Check if directory exists
		if ( ! is_dir( $directory ) ) {
			return new WP_Error( 
				'directory_not_found', 
				sprintf( 
					/* translators: %s: directory path */
					__( 'Directory does not exist: %s', 'wp-image-optimizer' ), 
					$directory 
				) 
			);
		}

		// Check if directory is writable
		if ( ! is_writable( $directory ) ) {
			return new WP_Error( 
				'directory_not_writable', 
				sprintf( 
					/* translators: %s: directory path */
					__( 'Directory is not writable: %s', 'wp-image-optimizer' ), 
					$directory 
				) 
			);
		}

		// If file exists, check if it's writable
		if ( file_exists( $file_path ) && ! is_writable( $file_path ) ) {
			return new WP_Error( 
				'file_not_writable', 
				sprintf( 
					/* translators: %s: file path */
					__( 'File is not writable: %s', 'wp-image-optimizer' ), 
					$file_path 
				) 
			);
		}

		return true;
	}

	/**
	 * Validate file size
	 *
	 * @param string $file_path File path to check
	 * @return bool|WP_Error True if valid size, error otherwise
	 */
	public function validate_file_size( $file_path ) {
		if ( empty( $file_path ) ) {
			return new WP_Error( 'empty_path', __( 'File path cannot be empty.', 'wp-image-optimizer' ) );
		}

		$file_path = $this->sanitize_file_path( $file_path );
		if ( is_wp_error( $file_path ) ) {
			return $file_path;
		}

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 
				'file_not_found', 
				sprintf( 
					/* translators: %s: file path */
					__( 'File not found: %s', 'wp-image-optimizer' ), 
					$file_path 
				) 
			);
		}

		$file_size = filesize( $file_path );
		if ( false === $file_size ) {
			return new WP_Error( 
				'filesize_error', 
				sprintf( 
					/* translators: %s: file path */
					__( 'Could not determine file size: %s', 'wp-image-optimizer' ), 
					$file_path 
				) 
			);
		}

		if ( $file_size > $this->max_file_size ) {
			return new WP_Error( 
				'file_too_large', 
				sprintf( 
					/* translators: %1$s: file size, %2$s: max allowed size */
					__( 'File size (%1$s) exceeds maximum allowed size (%2$s).', 'wp-image-optimizer' ), 
					size_format( $file_size ), 
					size_format( $this->max_file_size ) 
				) 
			);
		}

		return true;
	}

	/**
	 * Validate MIME type
	 *
	 * @param string $file_path File path to check
	 * @return bool|WP_Error True if valid MIME type, error otherwise
	 */
	public function validate_mime_type( $file_path ) {
		if ( empty( $file_path ) ) {
			return new WP_Error( 'empty_path', __( 'File path cannot be empty.', 'wp-image-optimizer' ) );
		}

		$file_path = $this->sanitize_file_path( $file_path );
		if ( is_wp_error( $file_path ) ) {
			return $file_path;
		}

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 
				'file_not_found', 
				sprintf( 
					/* translators: %s: file path */
					__( 'File not found: %s', 'wp-image-optimizer' ), 
					$file_path 
				) 
			);
		}

		// Get MIME type using WordPress function
		$mime_type = wp_check_filetype_and_ext( $file_path, basename( $file_path ) );
		
		if ( ! $mime_type['type'] ) {
			return new WP_Error( 
				'invalid_mime_type', 
				sprintf( 
					/* translators: %s: file path */
					__( 'Could not determine MIME type for file: %s', 'wp-image-optimizer' ), 
					$file_path 
				) 
			);
		}

		if ( ! in_array( $mime_type['type'], $this->allowed_mime_types, true ) ) {
			return new WP_Error( 
				'unsupported_mime_type', 
				sprintf( 
					/* translators: %1$s: detected MIME type, %2$s: allowed MIME types */
					__( 'Unsupported MIME type: %1$s. Allowed types: %2$s', 'wp-image-optimizer' ), 
					$mime_type['type'], 
					implode( ', ', $this->allowed_mime_types ) 
				) 
			);
		}

		return true;
	}

	/**
	 * Clean up orphaned converted files
	 *
	 * @param string $original_path Original image file path
	 * @return array|WP_Error Array of cleaned files or error
	 */
	public function cleanup_orphaned_files( $original_path ) {
		if ( empty( $original_path ) ) {
			return new WP_Error( 'empty_path', __( 'Original file path cannot be empty.', 'wp-image-optimizer' ) );
		}

		$original_path = $this->sanitize_file_path( $original_path );
		if ( is_wp_error( $original_path ) ) {
			return $original_path;
		}

		$cleaned_files = array();
		$formats = array( 'webp', 'avif' );

		foreach ( $formats as $format ) {
			$converted_path = $this->generate_converted_path( $original_path, $format );
			
			if ( is_wp_error( $converted_path ) ) {
				continue; // Skip this format if path generation failed
			}

			// If original file doesn't exist but converted file does, it's orphaned
			if ( ! file_exists( $original_path ) && file_exists( $converted_path ) ) {
				if ( $this->delete_file( $converted_path ) ) {
					$cleaned_files[] = $converted_path;
				}
			}
		}

		return $cleaned_files;
	}

	/**
	 * Clean up all converted files for a given original file
	 *
	 * @param string $original_path Original image file path
	 * @param bool   $dry_run Whether to perform a dry run (don't actually delete files)
	 * @return array|WP_Error Array of deleted files or error
	 */
	public function cleanup_converted_files( $original_path, $dry_run = false ) {
		if ( empty( $original_path ) ) {
			return new WP_Error( 'empty_path', __( 'Original file path cannot be empty.', 'wp-image-optimizer' ) );
		}

		$original_path = $this->sanitize_file_path( $original_path );
		if ( is_wp_error( $original_path ) ) {
			return $original_path;
		}

		$deleted_files = array();
		$formats = array( 'webp', 'avif' );

		foreach ( $formats as $format ) {
			$converted_path = $this->generate_converted_path( $original_path, $format );
			
			if ( is_wp_error( $converted_path ) ) {
				continue; // Skip this format if path generation failed
			}

			if ( file_exists( $converted_path ) ) {
				if ( $dry_run ) {
					// In dry run mode, just add to list without deleting
					$deleted_files[] = $converted_path;
				} else {
					// Actually delete the file
					if ( $this->delete_file( $converted_path ) ) {
						$deleted_files[] = $converted_path;
					}
				}
			}
		}

		return array( 'deleted' => $deleted_files );
	}

	/**
	 * Safely delete a file
	 *
	 * @param string $file_path File path to delete
	 * @return bool|WP_Error True on success, error on failure
	 */
	public function delete_file( $file_path ) {
		if ( empty( $file_path ) ) {
			return new WP_Error( 'empty_path', __( 'File path cannot be empty.', 'wp-image-optimizer' ) );
		}

		$file_path = $this->sanitize_file_path( $file_path );
		if ( is_wp_error( $file_path ) ) {
			return $file_path;
		}

		if ( ! file_exists( $file_path ) ) {
			return true; // File doesn't exist, consider it deleted
		}

		// Security check: ensure file is within upload directory
		if ( ! $this->is_within_upload_directory( $file_path ) ) {
			return new WP_Error( 
				'security_violation', 
				__( 'File is not within the allowed upload directory.', 'wp-image-optimizer' ) 
			);
		}

		// Attempt to delete the file
		if ( ! wp_delete_file( $file_path ) ) {
			return new WP_Error( 
				'delete_failed', 
				sprintf( 
					/* translators: %s: file path */
					__( 'Failed to delete file: %s', 'wp-image-optimizer' ), 
					$file_path 
				) 
			);
		}

		return true;
	}

	/**
	 * Get file information
	 *
	 * @param string $file_path File path to analyze
	 * @return array|WP_Error File information array or error
	 */
	public function get_file_info( $file_path ) {
		if ( empty( $file_path ) ) {
			return new WP_Error( 'empty_path', __( 'File path cannot be empty.', 'wp-image-optimizer' ) );
		}

		$file_path = $this->sanitize_file_path( $file_path );
		if ( is_wp_error( $file_path ) ) {
			return $file_path;
		}

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 
				'file_not_found', 
				sprintf( 
					/* translators: %s: file path */
					__( 'File not found: %s', 'wp-image-optimizer' ), 
					$file_path 
				) 
			);
		}

		$file_info = array();
		
		// Basic file information
		$file_info['path'] = $file_path;
		$file_info['basename'] = basename( $file_path );
		$file_info['dirname'] = dirname( $file_path );
		$file_info['extension'] = pathinfo( $file_path, PATHINFO_EXTENSION );
		$file_info['filename'] = pathinfo( $file_path, PATHINFO_FILENAME );
		
		// File size
		$file_size = filesize( $file_path );
		$file_info['size'] = $file_size !== false ? $file_size : 0;
		$file_info['size_formatted'] = size_format( $file_info['size'] );
		
		// MIME type
		$mime_info = wp_check_filetype_and_ext( $file_path, basename( $file_path ) );
		$file_info['mime_type'] = $mime_info['type'];
		$file_info['proper_filename'] = $mime_info['proper_filename'];
		
		// File timestamps
		$file_info['modified_time'] = filemtime( $file_path );
		$file_info['modified_time_formatted'] = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $file_info['modified_time'] );
		
		// Permissions
		$file_info['is_readable'] = is_readable( $file_path );
		$file_info['is_writable'] = is_writable( $file_path );
		
		// Security check
		$file_info['is_within_upload_dir'] = $this->is_within_upload_directory( $file_path );

		return $file_info;
	}

	/**
	 * Sanitize file path
	 *
	 * @param string $file_path File path to sanitize
	 * @return string|WP_Error Sanitized path or error
	 */
	private function sanitize_file_path( $file_path ) {
		if ( empty( $file_path ) ) {
			return new WP_Error( 'empty_path', __( 'File path cannot be empty.', 'wp-image-optimizer' ) );
		}

		// Remove any null bytes
		$file_path = str_replace( chr( 0 ), '', $file_path );
		
		// Normalize path separators
		$file_path = wp_normalize_path( $file_path );
		
		// Remove any directory traversal attempts
		if ( strpos( $file_path, '..' ) !== false ) {
			return new WP_Error( 
				'invalid_path', 
				__( 'File path contains invalid directory traversal.', 'wp-image-optimizer' ) 
			);
		}

		// Convert to absolute path if relative
		if ( ! path_is_absolute( $file_path ) ) {
			$file_path = $this->upload_dir['basedir'] . '/' . ltrim( $file_path, '/' );
		}

		return $file_path;
	}

	/**
	 * Check if file path is within upload directory
	 *
	 * @param string $file_path File path to check
	 * @return bool True if within upload directory
	 */
	private function is_within_upload_directory( $file_path ) {
		$file_path = wp_normalize_path( $file_path );
		$upload_basedir = wp_normalize_path( $this->upload_dir['basedir'] );
		
		return strpos( $file_path, $upload_basedir ) === 0;
	}

	/**
	 * Get maximum file size
	 *
	 * @return int Maximum file size in bytes
	 */
	public function get_max_file_size() {
		return $this->max_file_size;
	}

	/**
	 * Set maximum file size
	 *
	 * @param int $size Maximum file size in bytes
	 * @return bool True on success
	 */
	public function set_max_file_size( $size ) {
		if ( ! is_numeric( $size ) || $size < 0 ) {
			return false;
		}
		
		$this->max_file_size = (int) $size;
		return true;
	}

	/**
	 * Get allowed MIME types
	 *
	 * @return array Allowed MIME types
	 */
	public function get_allowed_mime_types() {
		return $this->allowed_mime_types;
	}

	/**
	 * Set allowed MIME types
	 *
	 * @param array $mime_types Array of allowed MIME types
	 * @return bool True on success
	 */
	public function set_allowed_mime_types( $mime_types ) {
		if ( ! is_array( $mime_types ) || empty( $mime_types ) ) {
			return false;
		}
		
		$this->allowed_mime_types = $mime_types;
		return true;
	}

	/**
	 * Get upload directory information
	 *
	 * @return array Upload directory information
	 */
	public function get_upload_dir() {
		return $this->upload_dir;
	}
}