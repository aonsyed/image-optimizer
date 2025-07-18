<?php
/**
 * GD Converter Class
 *
 * Handles image conversion using GD library.
 *
 * @package WP_Image_Optimizer
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GD converter implementation.
 *
 * This class provides image conversion functionality using the GD
 * library for converting images to WebP format. AVIF is not supported
 * by GD library natively.
 */
class GD_Converter implements Converter_Interface {

	/**
	 * Check if GD is available on the server.
	 *
	 * @return bool True if GD is available, false otherwise.
	 */
	public function is_available() {
		// Check if GD extension is loaded.
		if ( ! extension_loaded( 'gd' ) ) {
			return false;
		}

		// Check if required GD functions exist.
		if ( ! function_exists( 'gd_info' ) ) {
			return false;
		}

		try {
			// Try to get GD info to verify it's working.
			$gd_info = gd_info();
			return is_array( $gd_info );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Convert an image to WebP format.
	 *
	 * @param string $source_path      Path to the source image file.
	 * @param string $destination_path Path where the WebP image should be saved.
	 * @param int    $quality          Quality setting (1-100).
	 *
	 * @return bool True on successful conversion, false on failure.
	 */
	public function convert_to_webp( $source_path, $destination_path, $quality = 80 ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		if ( ! $this->supports_webp() ) {
			return false;
		}

		if ( ! $this->validate_conversion_params( $source_path, $destination_path, $quality ) ) {
			return false;
		}

		// Skip conversion if source is already WebP.
		if ( $this->is_image_format( $source_path, 'webp' ) ) {
			return copy( $source_path, $destination_path );
		}

		try {
			// Load the source image based on its type.
			$source_image = $this->load_image( $source_path );
			if ( false === $source_image ) {
				error_log( 'GD WebP conversion: Failed to load source image: ' . $source_path );
				return false;
			}

			// Convert to WebP.
			$result = imagewebp( $source_image, $destination_path, $quality );

			// Clean up memory.
			imagedestroy( $source_image );

			if ( ! $result ) {
				error_log( 'GD WebP conversion: Failed to save WebP image: ' . $destination_path );
				return false;
			}

			return true;
		} catch ( Exception $e ) {
			error_log( 'GD WebP conversion failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Convert an image to AVIF format.
	 *
	 * GD library does not support AVIF format natively.
	 * This method returns false to indicate AVIF is not supported.
	 *
	 * @param string $source_path      Path to the source image file.
	 * @param string $destination_path Path where the AVIF image should be saved.
	 * @param int    $quality          Quality setting (1-100).
	 *
	 * @return bool Always returns false as GD doesn't support AVIF.
	 */
	public function convert_to_avif( $source_path, $destination_path, $quality = 75 ) {
		// GD library does not support AVIF format.
		error_log( 'GD AVIF conversion: AVIF format is not supported by GD library' );
		return false;
	}

	/**
	 * Get the list of supported output formats.
	 *
	 * @return array Array of supported format strings.
	 */
	public function get_supported_formats() {
		$formats = array();

		if ( ! $this->is_available() ) {
			return $formats;
		}

		// WebP support depends on GD compilation.
		if ( $this->supports_webp() ) {
			$formats[] = 'webp';
		}

		// GD does not support AVIF natively.
		// AVIF is intentionally not included in supported formats.

		return $formats;
	}

	/**
	 * Get the name of the converter.
	 *
	 * @return string The converter name.
	 */
	public function get_name() {
		return 'GD';
	}

	/**
	 * Get the priority of this converter.
	 *
	 * GD has lower priority than ImageMagick due to limited format support.
	 *
	 * @return int Priority value (lower = higher priority).
	 */
	public function get_priority() {
		return 20;
	}

	/**
	 * Check if WebP format is supported.
	 *
	 * @return bool True if WebP is supported, false otherwise.
	 */
	private function supports_webp() {
		if ( ! $this->is_available() ) {
			return false;
		}

		// Check if imagewebp function exists.
		if ( ! function_exists( 'imagewebp' ) ) {
			return false;
		}

		// Check GD info for WebP support.
		$gd_info = gd_info();
		return isset( $gd_info['WebP Support'] ) && $gd_info['WebP Support'];
	}

	/**
	 * Load an image resource from file based on its type.
	 *
	 * @param string $image_path Path to the image file.
	 *
	 * @return resource|false Image resource on success, false on failure.
	 */
	private function load_image( $image_path ) {
		if ( ! file_exists( $image_path ) ) {
			return false;
		}

		$image_info = getimagesize( $image_path );
		if ( false === $image_info ) {
			return false;
		}

		$mime_type = $image_info['mime'];

		switch ( $mime_type ) {
			case 'image/jpeg':
				return imagecreatefromjpeg( $image_path );
			case 'image/png':
				$image = imagecreatefrompng( $image_path );
				if ( false !== $image ) {
					// Preserve transparency for PNG images.
					imagealphablending( $image, false );
					imagesavealpha( $image, true );
				}
				return $image;
			case 'image/gif':
				return imagecreatefromgif( $image_path );
			case 'image/webp':
				if ( function_exists( 'imagecreatefromwebp' ) ) {
					return imagecreatefromwebp( $image_path );
				}
				return false;
			default:
				error_log( 'GD converter: Unsupported image type: ' . $mime_type );
				return false;
		}
	}

	/**
	 * Validate conversion parameters.
	 *
	 * @param string $source_path      Path to the source image file.
	 * @param string $destination_path Path where the converted image should be saved.
	 * @param int    $quality          Quality setting (1-100).
	 *
	 * @return bool True if parameters are valid, false otherwise.
	 */
	private function validate_conversion_params( $source_path, $destination_path, $quality ) {
		// Check if source file exists and is readable.
		if ( ! file_exists( $source_path ) || ! is_readable( $source_path ) ) {
			error_log( 'GD conversion: Source file not found or not readable: ' . $source_path );
			return false;
		}

		// Check if destination directory exists and is writable.
		$destination_dir = dirname( $destination_path );
		if ( ! is_dir( $destination_dir ) || ! is_writable( $destination_dir ) ) {
			error_log( 'GD conversion: Destination directory not writable: ' . $destination_dir );
			return false;
		}

		// Validate quality parameter.
		if ( ! is_numeric( $quality ) || $quality < 1 || $quality > 100 ) {
			error_log( 'GD conversion: Invalid quality parameter: ' . $quality );
			return false;
		}

		// Check if source is a valid image file.
		$image_info = getimagesize( $source_path );
		if ( false === $image_info ) {
			error_log( 'GD conversion: Invalid image file: ' . $source_path );
			return false;
		}

		// Check if the image type is supported by GD.
		$supported_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		if ( ! in_array( $image_info['mime'], $supported_types, true ) ) {
			error_log( 'GD conversion: Unsupported image type: ' . $image_info['mime'] );
			return false;
		}

		return true;
	}

	/**
	 * Check if an image file is already in the specified format.
	 *
	 * @param string $image_path Path to the image file.
	 * @param string $format     Format to check for ('webp', 'avif', etc.).
	 *
	 * @return bool True if image is already in the specified format, false otherwise.
	 */
	private function is_image_format( $image_path, $format ) {
		if ( ! file_exists( $image_path ) ) {
			return false;
		}

		$image_info = getimagesize( $image_path );
		if ( false === $image_info ) {
			return false;
		}

		$mime_type = $image_info['mime'];

		switch ( strtolower( $format ) ) {
			case 'webp':
				return 'image/webp' === $mime_type;
			case 'avif':
				return 'image/avif' === $mime_type;
			case 'jpeg':
			case 'jpg':
				return 'image/jpeg' === $mime_type;
			case 'png':
				return 'image/png' === $mime_type;
			case 'gif':
				return 'image/gif' === $mime_type;
			default:
				return false;
		}
	}

	/**
	 * Convert multiple images in batch.
	 *
	 * @param array    $conversions Array of conversion tasks with 'source', 'destination', 'format', 'quality'.
	 * @param callable $progress_callback Optional callback to report progress.
	 *
	 * @return array Results array with success/failure status for each conversion.
	 */
	public function batch_convert( $conversions, $progress_callback = null ) {
		$results = array();
		$total = count( $conversions );

		foreach ( $conversions as $index => $conversion ) {
			$source = $conversion['source'];
			$destination = $conversion['destination'];
			$format = $conversion['format'];
			$quality = isset( $conversion['quality'] ) ? $conversion['quality'] : 80;

			// Perform conversion based on format.
			if ( 'webp' === $format ) {
				$success = $this->convert_to_webp( $source, $destination, $quality );
			} elseif ( 'avif' === $format ) {
				// GD doesn't support AVIF, so this will always fail.
				$success = $this->convert_to_avif( $source, $destination, $quality );
			} else {
				$success = false;
			}

			$results[] = array(
				'source' => $source,
				'destination' => $destination,
				'format' => $format,
				'success' => $success,
			);

			// Report progress if callback provided.
			if ( is_callable( $progress_callback ) ) {
				call_user_func( $progress_callback, $index + 1, $total, $success );
			}
		}

		return $results;
	}
}