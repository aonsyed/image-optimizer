<?php
/**
 * Integration tests for ImageMagick converter with the factory system.
 *
 * @package WP_Image_Optimizer
 * @since   1.0.0
 */

/**
 * Test class for ImageMagick converter integration.
 */
class Test_ImageMagick_Integration extends WP_UnitTestCase {

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Include required files.
		require_once ABSPATH . 'wp-content/plugins/wp-image-optimizer/includes/interfaces/interface-converter.php';
		require_once ABSPATH . 'wp-content/plugins/wp-image-optimizer/includes/converters/class-imagemagick-converter.php';
		require_once ABSPATH . 'wp-content/plugins/wp-image-optimizer/includes/converters/class-converter-factory.php';
	}

	/**
	 * Test that ImageMagick converter can be instantiated.
	 */
	public function test_imagemagick_converter_instantiation() {
		$converter = new ImageMagick_Converter();
		$this->assertInstanceOf( 'ImageMagick_Converter', $converter );
		$this->assertInstanceOf( 'Converter_Interface', $converter );
	}

	/**
	 * Test that ImageMagick converter is recognized by the factory.
	 */
	public function test_imagemagick_in_factory() {
		$available_converters = Converter_Factory::get_available_converters();
		
		// Check if any converter is ImageMagick (depends on server setup).
		$has_imagemagick = false;
		foreach ( $available_converters as $converter ) {
			if ( $converter instanceof ImageMagick_Converter ) {
				$has_imagemagick = true;
				break;
			}
		}

		// If ImageMagick is available on the server, it should be in the list.
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			$this->assertTrue( $has_imagemagick, 'ImageMagick converter should be available when extension is loaded' );
		}
	}

	/**
	 * Test getting ImageMagick converter by name.
	 */
	public function test_get_imagemagick_by_name() {
		$converter = Converter_Factory::get_converter_by_name( 'ImageMagick' );
		
		// If ImageMagick is available, should return the converter.
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			$this->assertInstanceOf( 'ImageMagick_Converter', $converter );
		} else {
			$this->assertNull( $converter );
		}
	}

	/**
	 * Test server capabilities detection includes ImageMagick.
	 */
	public function test_server_capabilities_imagemagick() {
		$capabilities = Converter_Factory::get_server_capabilities();
		
		$this->assertIsArray( $capabilities );
		$this->assertArrayHasKey( 'imagemagick', $capabilities );
		$this->assertIsBool( $capabilities['imagemagick'] );
		
		// If ImageMagick extension is loaded, capability should be true.
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			$this->assertTrue( $capabilities['imagemagick'] );
		}
	}

	/**
	 * Test that ImageMagick converter has correct priority.
	 */
	public function test_imagemagick_priority() {
		$converter = new ImageMagick_Converter();
		$priority = $converter->get_priority();
		
		$this->assertIsInt( $priority );
		$this->assertEquals( 10, $priority );
		
		// ImageMagick should have higher priority than GD (lower number = higher priority).
		$this->assertLessThan( 20, $priority, 'ImageMagick should have higher priority than GD' );
	}
}