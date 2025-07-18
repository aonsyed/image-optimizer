<?php
/**
 * Mock Converter for Testing
 *
 * @package WP_Image_Optimizer
 */

/**
 * Mock converter class for testing purposes.
 */
class Mock_Converter implements Converter_Interface {

	/**
	 * Whether this converter should report as available.
	 *
	 * @var bool
	 */
	private $is_available;

	/**
	 * Supported formats for this mock converter.
	 *
	 * @var array
	 */
	private $supported_formats;

	/**
	 * Priority for this converter.
	 *
	 * @var int
	 */
	private $priority;

	/**
	 * Name of this converter.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Whether conversions should succeed.
	 *
	 * @var bool
	 */
	private $conversion_success;

	/**
	 * Constructor.
	 *
	 * @param array $config Configuration for the mock converter.
	 */
	public function __construct( $config = array() ) {
		$defaults = array(
			'is_available'       => true,
			'supported_formats'  => array( 'webp', 'avif' ),
			'priority'           => 10,
			'name'               => 'Mock_Converter',
			'conversion_success' => true,
		);

		$config = array_merge( $defaults, $config );

		$this->is_available       = $config['is_available'];
		$this->supported_formats  = $config['supported_formats'];
		$this->priority           = $config['priority'];
		$this->name               = $config['name'];
		$this->conversion_success = $config['conversion_success'];
	}

	/**
	 * Check if the converter is available on the server.
	 *
	 * @return bool True if the converter is available, false otherwise.
	 */
	public function is_available() {
		return $this->is_available;
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
		if ( ! in_array( 'webp', $this->supported_formats, true ) ) {
			return false;
		}

		if ( ! $this->conversion_success ) {
			return false;
		}

		// Simulate file creation for testing
		if ( $this->conversion_success && is_writable( dirname( $destination_path ) ) ) {
			file_put_contents( $destination_path, 'mock webp content' );
		}

		return $this->conversion_success;
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
		if ( ! in_array( 'avif', $this->supported_formats, true ) ) {
			return false;
		}

		if ( ! $this->conversion_success ) {
			return false;
		}

		// Simulate file creation for testing
		if ( $this->conversion_success && is_writable( dirname( $destination_path ) ) ) {
			file_put_contents( $destination_path, 'mock avif content' );
		}

		return $this->conversion_success;
	}

	/**
	 * Get the list of supported output formats.
	 *
	 * @return array Array of supported format strings (e.g., ['webp', 'avif']).
	 */
	public function get_supported_formats() {
		return $this->supported_formats;
	}

	/**
	 * Get the name of the converter.
	 *
	 * @return string The converter name (e.g., 'ImageMagick', 'GD').
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get the priority of this converter.
	 *
	 * Lower numbers indicate higher priority.
	 *
	 * @return int Priority value.
	 */
	public function get_priority() {
		return $this->priority;
	}

	/**
	 * Set availability for testing.
	 *
	 * @param bool $available Whether the converter should be available.
	 */
	public function set_available( $available ) {
		$this->is_available = $available;
	}

	/**
	 * Set conversion success for testing.
	 *
	 * @param bool $success Whether conversions should succeed.
	 */
	public function set_conversion_success( $success ) {
		$this->conversion_success = $success;
	}

	/**
	 * Set supported formats for testing.
	 *
	 * @param array $formats Array of supported format strings.
	 */
	public function set_supported_formats( $formats ) {
		$this->supported_formats = $formats;
	}
}