<?php
/**
 * ImageMagick Converter Class
 *
 * Handles image conversion using ImageMagick library.
 *
 * @package WP_Image_Optimizer
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ImageMagick converter implementation.
 *
 * This class provides image conversion functionality using the ImageMagick
 * library for converting images to WebP and AVIF formats.
 */
class ImageMagick_Converter implements Converter_Interface {

	/**
	 * Check if ImageMagick is available on the server.
	 *
	 * @return bool True if ImageMagick is available, false otherwise.
	 */
	public function is_available() {
		// Check if ImageMagick extension is loaded.
		if ( ! extension_loaded( 'imagick' ) ) {
			return false;
		}

		// Check if Imagick class exists.
		if ( ! class_exists( 'Imagick' ) ) {
			return false;
		}

		try {
			// Try to create an Imagick instance to verify it's working.
			$imagick = new Imagick();
			$imagick->clear();
			return true;
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

		if ( ! $this->validate_conversion_params( $source_path, $destination_path, $quality ) ) {
			return false;
		}

		// Skip conversion if source is already WebP.
		if ( $this->is_image_format( $source_path, 'webp' ) ) {
			return copy( $source_path, $destination_path );
		}

		try {
			$imagick = new Imagick();
			$imagick->readImage( $source_path );

			// Set WebP format and quality.
			$imagick->setImageFormat( 'webp' );
			$imagick->setImageCompressionQuality( $quality );

			// Optimize for web.
			$imagick->stripImage();

			// Write the converted image.
			$result = $imagick->writeImage( $destination_path );
			$imagick->clear();

			return $result;
		} catch ( Exception $e ) {
			error_log( 'ImageMagick WebP conversion failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Convert an image to AVIF format.
	 *
	 * @param string $source_path      Path to the source image file.
	 * @param string $destination_path Path where the AVIF image should be saved.
	 * @param int    $quality          Quality setting (1-100).
	 *
	 * @return bool True on successful conversion, false on failure.
	 */
	public function convert_to_avif( $source_path, $destination_path, $quality = 75 ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		if ( ! $this->supports_avif() ) {
			return false;
		}

		if ( ! $this->validate_conversion_params( $source_path, $destination_path, $quality ) ) {
			return false;
		}

		// Skip conversion if source is already AVIF.
		if ( $this->is_image_format( $source_path, 'avif' ) ) {
			return copy( $source_path, $destination_path );
		}

		try {
			$imagick = new Imagick();
			$imagick->readImage( $source_path );

			// Set AVIF format and quality.
			$imagick->setImageFormat( 'avif' );
			$imagick->setImageCompressionQuality( $quality );

			// Optimize for web.
			$imagick->stripImage();

			// Write the converted image.
			$result = $imagick->writeImage( $destination_path );
			$imagick->clear();

			return $result;
		} catch ( Exception $e ) {
			error_log( 'ImageMagick AVIF conversion failed: ' . $e->getMessage() );
			return false;
		}
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

		// WebP is generally supported in modern ImageMagick versions.
		if ( $this->supports_webp() ) {
			$formats[] = 'webp';
		}

		// AVIF support depends on ImageMagick version and compilation.
		if ( $this->supports_avif() ) {
			$formats[] = 'avif';
		}

		return $formats;
	}

	/**
	 * Get the name of the converter.
	 *
	 * @return string The converter name.
	 */
	public function get_name() {
		return 'ImageMagick';
	}

	/**
	 * Get the priority of this converter.
	 *
	 * ImageMagick has higher priority than GD due to better quality and format support.
	 *
	 * @return int Priority value (lower = higher priority).
	 */
	public function get_priority() {
		return 10;
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

		try {
			$imagick = new Imagick();
			$formats = $imagick->queryFormats( 'WEBP' );
			$imagick->clear();
			return ! empty( $formats );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Check if AVIF format is supported.
	 *
	 * @return bool True if AVIF is supported, false otherwise.
	 */
	private function supports_avif() {
		if ( ! $this->is_available() ) {
			return false;
		}

		try {
			$imagick = new Imagick();
			$formats = $imagick->queryFormats( 'AVIF' );
			$imagick->clear();
			return ! empty( $formats );
		} catch ( Exception $e ) {
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
			error_log( 'ImageMagick conversion: Source file not found or not readable: ' . $source_path );
			return false;
		}

		// Check if destination directory exists and is writable.
		$destination_dir = dirname( $destination_path );
		if ( ! is_dir( $destination_dir ) || ! is_writable( $destination_dir ) ) {
			error_log( 'ImageMagick conversion: Destination directory not writable: ' . $destination_dir );
			return false;
		}

		// Validate quality parameter.
		if ( ! is_numeric( $quality ) || $quality < 1 || $quality > 100 ) {
			error_log( 'ImageMagick conversion: Invalid quality parameter: ' . $quality );
			return false;
		}

		// Check if source is a valid image file.
		$image_info = getimagesize( $source_path );
		if ( false === $image_info ) {
			error_log( 'ImageMagick conversion: Invalid image file: ' . $source_path );
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
			$quality = isset( $conversion['quality'] ) ? $conversion['quality'] : ( 'webp' === $format ? 80 : 75 );

			// Perform conversion based on format.
			if ( 'webp' === $format ) {
				$success = $this->convert_to_webp( $source, $destination, $quality );
			} elseif ( 'avif' === $format ) {
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