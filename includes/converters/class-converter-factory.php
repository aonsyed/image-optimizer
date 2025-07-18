<?php
/**
 * Converter Factory
 *
 * Factory class for detecting and instantiating appropriate image converters.
 *
 * @package WP_Image_Optimizer
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Factory class for image converters.
 *
 * This class handles detection of available image processing libraries
 * and instantiates the appropriate converter based on server capabilities.
 */
class Converter_Factory {

	/**
	 * Available converter classes.
	 *
	 * @var array
	 */
	private static $converter_classes = array(
		'ImageMagick_Converter',
		'GD_Converter',
	);

	/**
	 * Cached converter instances.
	 *
	 * @var array
	 */
	private static $converter_cache = array();

	/**
	 * Server capabilities cache.
	 *
	 * @var array|null
	 */
	private static $capabilities_cache = null;

	/**
	 * Get the best available converter.
	 *
	 * Returns the converter with the highest priority that is available
	 * on the current server.
	 *
	 * @return Converter_Interface|null The best available converter or null if none available.
	 */
	public static function get_converter() {
		$available_converters = self::get_available_converters();
		
		if ( empty( $available_converters ) ) {
			return null;
		}

		// Sort by priority (lower number = higher priority).
		usort( $available_converters, function( $a, $b ) {
			return $a->get_priority() - $b->get_priority();
		});

		return $available_converters[0];
	}

	/**
	 * Get all available converters.
	 *
	 * @return array Array of available converter instances.
	 */
	public static function get_available_converters() {
		$available = array();

		foreach ( self::$converter_classes as $class_name ) {
			$converter = self::get_converter_instance( $class_name );
			
			if ( $converter && $converter->is_available() ) {
				$available[] = $converter;
			}
		}

		return $available;
	}

	/**
	 * Get a specific converter by name.
	 *
	 * @param string $converter_name The name of the converter to get.
	 *
	 * @return Converter_Interface|null The converter instance or null if not available.
	 */
	public static function get_converter_by_name( $converter_name ) {
		foreach ( self::$converter_classes as $class_name ) {
			$converter = self::get_converter_instance( $class_name );
			
			if ( $converter && $converter->get_name() === $converter_name && $converter->is_available() ) {
				return $converter;
			}
		}

		return null;
	}

	/**
	 * Check if any converter is available.
	 *
	 * @return bool True if at least one converter is available.
	 */
	public static function has_available_converter() {
		return ! empty( self::get_available_converters() );
	}

	/**
	 * Get server capabilities for image processing.
	 *
	 * @return array Array of server capabilities.
	 */
	public static function get_server_capabilities() {
		if ( null !== self::$capabilities_cache ) {
			return self::$capabilities_cache;
		}

		$capabilities = array(
			'imagemagick'  => self::detect_imagemagick(),
			'gd'           => self::detect_gd(),
			'webp_support' => self::detect_webp_support(),
			'avif_support' => self::detect_avif_support(),
		);

		// Cache the results for 1 hour.
		set_transient( 'wp_image_optimizer_capabilities', $capabilities, HOUR_IN_SECONDS );
		self::$capabilities_cache = $capabilities;

		return $capabilities;
	}

	/**
	 * Clear the capabilities cache.
	 *
	 * Forces a fresh detection of server capabilities on next request.
	 */
	public static function clear_capabilities_cache() {
		delete_transient( 'wp_image_optimizer_capabilities' );
		self::$capabilities_cache = null;
	}

	/**
	 * Get converter instance with caching.
	 *
	 * @param string $class_name The converter class name.
	 *
	 * @return Converter_Interface|null The converter instance or null if class doesn't exist.
	 */
	private static function get_converter_instance( $class_name ) {
		if ( isset( self::$converter_cache[ $class_name ] ) ) {
			return self::$converter_cache[ $class_name ];
		}

		if ( ! class_exists( $class_name ) ) {
			return null;
		}

		$converter = new $class_name();
		
		if ( ! $converter instanceof Converter_Interface ) {
			return null;
		}

		self::$converter_cache[ $class_name ] = $converter;
		return $converter;
	}

	/**
	 * Detect if ImageMagick is available.
	 *
	 * @return bool True if ImageMagick is available.
	 */
	private static function detect_imagemagick() {
		// Check if Imagick extension is loaded.
		if ( ! extension_loaded( 'imagick' ) ) {
			return false;
		}

		// Check if Imagick class exists.
		if ( ! class_exists( 'Imagick' ) ) {
			return false;
		}

		// Try to create an Imagick instance.
		try {
			$imagick = new Imagick();
			return true;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Detect if GD library is available.
	 *
	 * @return bool True if GD is available.
	 */
	private static function detect_gd() {
		return extension_loaded( 'gd' ) && function_exists( 'gd_info' );
	}

	/**
	 * Detect WebP support across available libraries.
	 *
	 * @return bool True if WebP is supported by at least one library.
	 */
	private static function detect_webp_support() {
		$webp_support = false;

		// Check ImageMagick WebP support.
		if ( self::detect_imagemagick() ) {
			try {
				$imagick = new Imagick();
				$formats = $imagick->queryFormats( 'WEBP' );
				$webp_support = ! empty( $formats );
			} catch ( Exception $e ) {
				// ImageMagick available but WebP not supported.
			}
		}

		// Check GD WebP support.
		if ( ! $webp_support && self::detect_gd() ) {
			$gd_info = gd_info();
			$webp_support = ! empty( $gd_info['WebP Support'] );
		}

		return $webp_support;
	}

	/**
	 * Detect AVIF support across available libraries.
	 *
	 * @return bool True if AVIF is supported by at least one library.
	 */
	private static function detect_avif_support() {
		$avif_support = false;

		// Check ImageMagick AVIF support.
		if ( self::detect_imagemagick() ) {
			try {
				$imagick = new Imagick();
				$formats = $imagick->queryFormats( 'AVIF' );
				$avif_support = ! empty( $formats );
			} catch ( Exception $e ) {
				// ImageMagick available but AVIF not supported.
			}
		}

		// Note: GD library doesn't natively support AVIF as of PHP 8.1.
		// This could be extended in the future if GD adds AVIF support.

		return $avif_support;
	}

	/**
	 * Load capabilities from cache if available.
	 */
	private static function load_capabilities_cache() {
		if ( null === self::$capabilities_cache ) {
			$cached = get_transient( 'wp_image_optimizer_capabilities' );
			if ( false !== $cached && is_array( $cached ) ) {
				self::$capabilities_cache = $cached;
			}
		}
	}
}