<?php
/**
 * Image Converter class
 *
 * @package ImageOptimizer
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Converter class
 */
class Image_Converter {

	/**
	 * Convert image to WebP and/or AVIF format
	 *
	 * @param string $image_path Path to the image file.
	 * @param int    $quality Quality setting (0-100).
	 * @param array  $sizes Array of image sizes to convert.
	 * @return array|false Conversion data or false on failure.
	 */
	public static function convert_image( $image_path, $quality = null, $sizes = array() ) {
		// Validate file path
		if ( ! file_exists( $image_path ) ) {
			throw new Exception( 'Image file does not exist: ' . $image_path );
		}

		// Get image information
		$image_info = getimagesize( $image_path );
		if ( false === $image_info ) {
			throw new Exception( 'Unable to get image information: ' . $image_path );
		}

		$mime_type = $image_info['mime'];
		
		// Check if image format is supported
		if ( ! in_array( $mime_type, array( 'image/jpeg', 'image/png', 'image/gif' ), true ) ) {
			throw new Exception( 'Unsupported image format: ' . $mime_type );
		}

		// Get conversion settings
		$formats = get_option( 'image_optimizer_conversion_format', 'both' );
		$remove_originals = get_option( 'image_optimizer_remove_originals', false );

		// Check if we should convert this size
		if ( ! empty( $sizes ) && ! in_array( $image_info[3], $sizes, true ) ) {
			return false;
		}

		$original_size = filesize( $image_path );
		$webp_size = null;
		$avif_size = null;

		try {
			// Convert to WebP if enabled
			if ( 'webp' === $formats || 'both' === $formats ) {
				$webp_size = self::convert_to_webp( $image_path, $quality );
			}

			// Convert to AVIF if enabled and Imagick is available
			if ( ( 'avif' === $formats || 'both' === $formats ) && class_exists( 'Imagick' ) ) {
				$avif_size = self::convert_to_avif( $image_path, $quality );
			}

			// Remove original if enabled
			if ( $remove_originals && ( $webp_size || $avif_size ) ) {
				unlink( $image_path );
			}

			// Return conversion data
			return array(
				'original_size' => $original_size,
				'webp_size'     => $webp_size,
				'avif_size'     => $avif_size,
				'formats'       => $formats,
				'timestamp'     => current_time( 'timestamp' ),
			);

		} catch ( Exception $e ) {
			Logger::log( 'Error converting image: ' . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Convert image to WebP format
	 *
	 * @param string $image_path Path to the image file.
	 * @param int    $quality Quality setting (0-100).
	 * @return int|false File size of converted image or false on failure.
	 */
	private static function convert_to_webp( $image_path, $quality = null ) {
		// Set default quality if not provided
		if ( null === $quality ) {
			$quality = get_option( 'image_optimizer_webp_quality', 80 );
		}

		// Validate quality range
		$quality = max( 0, min( 100, (int) $quality ) );

		// Get image content
		$image_content = file_get_contents( $image_path );
		if ( false === $image_content ) {
			throw new Exception( 'Unable to read image file: ' . $image_path );
		}

		// Create image resource
		$image = imagecreatefromstring( $image_content );
		if ( false === $image ) {
			throw new Exception( 'Unable to create image from string: ' . $image_path );
		}

		// Generate WebP path
		$webp_path = preg_replace( '/\.(jpe?g|png|gif)$/i', '.webp', $image_path );

		// Convert to WebP
		$result = imagewebp( $image, $webp_path, $quality );
		
		// Clean up
		imagedestroy( $image );

		if ( false === $result ) {
			throw new Exception( 'Failed to convert image to WebP: ' . $image_path );
		}

		// Return file size
		return file_exists( $webp_path ) ? filesize( $webp_path ) : false;
	}

	/**
	 * Convert image to AVIF format
	 *
	 * @param string $image_path Path to the image file.
	 * @param int    $quality Quality setting (0-100).
	 * @return int|false File size of converted image or false on failure.
	 */
	private static function convert_to_avif( $image_path, $quality = null ) {
		// Check if Imagick is available
		if ( ! class_exists( 'Imagick' ) ) {
			throw new Exception( 'Imagick extension is required for AVIF conversion' );
		}

		// Set default quality if not provided
		if ( null === $quality ) {
			$quality = get_option( 'image_optimizer_avif_quality', 80 );
		}

		// Validate quality range
		$quality = max( 0, min( 100, (int) $quality ) );

		try {
			// Create Imagick instance
			$imagick = new Imagick( $image_path );
			
			// Set image format to AVIF
			$imagick->setImageFormat( 'avif' );
			
			// Set compression quality
			$imagick->setImageCompressionQuality( $quality );
			
			// Generate AVIF path
			$avif_path = preg_replace( '/\.(jpe?g|png|gif)$/i', '.avif', $image_path );
			
			// Write AVIF file
			$result = $imagick->writeImage( $avif_path );
			
			// Clean up
			$imagick->clear();
			$imagick->destroy();

			if ( false === $result ) {
				throw new Exception( 'Failed to write AVIF file: ' . $avif_path );
			}

			// Return file size
			return file_exists( $avif_path ) ? filesize( $avif_path ) : false;

		} catch ( ImagickException $e ) {
			throw new Exception( 'Imagick error: ' . $e->getMessage() );
		}
	}

	/**
	 * Get supported image formats
	 *
	 * @return array Array of supported MIME types.
	 */
	public static function get_supported_formats() {
		$formats = array( 'image/jpeg', 'image/png' );

		// Add GIF support if available
		if ( function_exists( 'imagecreatefromgif' ) ) {
			$formats[] = 'image/gif';
		}

		return $formats;
	}

	/**
	 * Check if WebP conversion is supported
	 *
	 * @return bool True if WebP is supported.
	 */
	public static function is_webp_supported() {
		return function_exists( 'imagewebp' ) && function_exists( 'imagecreatefromstring' );
	}

	/**
	 * Check if AVIF conversion is supported
	 *
	 * @return bool True if AVIF is supported.
	 */
	public static function is_avif_supported() {
		return class_exists( 'Imagick' );
	}

	/**
	 * Get conversion statistics
	 *
	 * @param string $image_path Path to the image file.
	 * @return array Conversion statistics.
	 */
	public static function get_conversion_stats( $image_path ) {
		$stats = array(
			'original_size' => 0,
			'webp_size'     => 0,
			'avif_size'     => 0,
			'webp_savings'  => 0,
			'avif_savings'  => 0,
		);

		if ( ! file_exists( $image_path ) ) {
			return $stats;
		}

		$stats['original_size'] = filesize( $image_path );

		// Check WebP file
		$webp_path = preg_replace( '/\.(jpe?g|png|gif)$/i', '.webp', $image_path );
		if ( file_exists( $webp_path ) ) {
			$stats['webp_size'] = filesize( $webp_path );
			$stats['webp_savings'] = $stats['original_size'] - $stats['webp_size'];
		}

		// Check AVIF file
		$avif_path = preg_replace( '/\.(jpe?g|png|gif)$/i', '.avif', $image_path );
		if ( file_exists( $avif_path ) ) {
			$stats['avif_size'] = filesize( $avif_path );
			$stats['avif_savings'] = $stats['original_size'] - $stats['avif_size'];
		}

		return $stats;
	}
}
