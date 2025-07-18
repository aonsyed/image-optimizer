<?php
/**
 * Converter Interface
 *
 * Defines the standard interface for image conversion implementations.
 *
 * @package WP_Image_Optimizer
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for image converters.
 *
 * This interface defines the standard methods that all image converter
 * implementations must provide for converting images to modern formats.
 */
interface Converter_Interface {

	/**
	 * Check if the converter is available on the server.
	 *
	 * @return bool True if the converter is available, false otherwise.
	 */
	public function is_available();

	/**
	 * Convert an image to WebP format.
	 *
	 * @param string $source_path      Path to the source image file.
	 * @param string $destination_path Path where the WebP image should be saved.
	 * @param int    $quality          Quality setting (1-100).
	 *
	 * @return bool True on successful conversion, false on failure.
	 */
	public function convert_to_webp( $source_path, $destination_path, $quality = 80 );

	/**
	 * Convert an image to AVIF format.
	 *
	 * @param string $source_path      Path to the source image file.
	 * @param string $destination_path Path where the AVIF image should be saved.
	 * @param int    $quality          Quality setting (1-100).
	 *
	 * @return bool True on successful conversion, false on failure.
	 */
	public function convert_to_avif( $source_path, $destination_path, $quality = 75 );

	/**
	 * Get the list of supported output formats.
	 *
	 * @return array Array of supported format strings (e.g., ['webp', 'avif']).
	 */
	public function get_supported_formats();

	/**
	 * Get the name of the converter.
	 *
	 * @return string The converter name (e.g., 'ImageMagick', 'GD').
	 */
	public function get_name();

	/**
	 * Get the priority of this converter.
	 *
	 * Lower numbers indicate higher priority.
	 *
	 * @return int Priority value.
	 */
	public function get_priority();
}