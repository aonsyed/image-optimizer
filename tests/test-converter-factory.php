<?php
/**
 * Unit tests for Converter Factory
 *
 * @package WP_Image_Optimizer
 */

/**
 * Test class for Converter_Factory
 */
class Test_Converter_Factory extends WP_UnitTestCase {

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Include required files
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/interfaces/interface-converter.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/converters/class-converter-factory.php';
		
		// Clear capabilities cache
		Converter_Factory::clear_capabilities_cache();
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		// Clear capabilities cache
		Converter_Factory::clear_capabilities_cache();
		
		parent::tearDown();
	}

	/**
	 * Test server capabilities detection
	 */
	public function test_get_server_capabilities() {
		$capabilities = Converter_Factory::get_server_capabilities();
		
		$this->assertIsArray( $capabilities );
		$this->assertArrayHasKey( 'imagemagick', $capabilities );
		$this->assertArrayHasKey( 'gd', $capabilities );
		$this->assertArrayHasKey( 'webp_support', $capabilities );
		$this->assertArrayHasKey( 'avif_support', $capabilities );
		
		// All values should be boolean
		$this->assertIsBool( $capabilities['imagemagick'] );
		$this->assertIsBool( $capabilities['gd'] );
		$this->assertIsBool( $capabilities['webp_support'] );
		$this->assertIsBool( $capabilities['avif_support'] );
	}

	/**
	 * Test capabilities caching
	 */
	public function test_capabilities_caching() {
		// First call should set cache
		$capabilities1 = Converter_Factory::get_server_capabilities();
		
		// Verify transient is set
		$cached = get_transient( 'wp_image_optimizer_capabilities' );
		$this->assertEquals( $capabilities1, $cached );
		
		// Second call should use cache
		$capabilities2 = Converter_Factory::get_server_capabilities();
		$this->assertEquals( $capabilities1, $capabilities2 );
	}

	/**
	 * Test clearing capabilities cache
	 */
	public function test_clear_capabilities_cache() {
		// Set cache
		Converter_Factory::get_server_capabilities();
		$this->assertNotFalse( get_transient( 'wp_image_optimizer_capabilities' ) );
		
		// Clear cache
		Converter_Factory::clear_capabilities_cache();
		$this->assertFalse( get_transient( 'wp_image_optimizer_capabilities' ) );
	}

	/**
	 * Test getting available converters when none exist
	 */
	public function test_get_available_converters_empty() {
		// Mock scenario where no converter classes exist
		$reflection = new ReflectionClass( 'Converter_Factory' );
		$property = $reflection->getProperty( 'converter_classes' );
		$property->setAccessible( true );
		$original_classes = $property->getValue();
		
		// Set empty converter classes
		$property->setValue( array() );
		
		$converters = Converter_Factory::get_available_converters();
		$this->assertIsArray( $converters );
		$this->assertEmpty( $converters );
		
		// Restore original classes
		$property->setValue( $original_classes );
	}

	/**
	 * Test has_available_converter method
	 */
	public function test_has_available_converter() {
		$has_converter = Converter_Factory::has_available_converter();
		$this->assertIsBool( $has_converter );
		
		$available_converters = Converter_Factory::get_available_converters();
		$expected = ! empty( $available_converters );
		$this->assertEquals( $expected, $has_converter );
	}

	/**
	 * Test getting converter by name with non-existent converter
	 */
	public function test_get_converter_by_name_nonexistent() {
		$converter = Converter_Factory::get_converter_by_name( 'NonExistentConverter' );
		$this->assertNull( $converter );
	}

	/**
	 * Test getting best converter
	 */
	public function test_get_converter() {
		$converter = Converter_Factory::get_converter();
		
		// Should either return a converter instance or null
		$this->assertTrue( $converter instanceof Converter_Interface || is_null( $converter ) );
		
		// If we have available converters, should return one
		$available = Converter_Factory::get_available_converters();
		if ( ! empty( $available ) ) {
			$this->assertInstanceOf( 'Converter_Interface', $converter );
		} else {
			$this->assertNull( $converter );
		}
	}

	/**
	 * Test converter priority sorting
	 */
	public function test_converter_priority_sorting() {
		$available = Converter_Factory::get_available_converters();
		
		if ( count( $available ) > 1 ) {
			// Check that converters are sorted by priority
			$priorities = array();
			foreach ( $available as $converter ) {
				$priorities[] = $converter->get_priority();
			}
			
			$sorted_priorities = $priorities;
			sort( $sorted_priorities );
			
			$this->assertEquals( $sorted_priorities, $priorities );
		} else {
			// If we don't have multiple converters, mark test as skipped
			$this->markTestSkipped( 'Multiple converters not available for priority testing' );
		}
	}

	/**
	 * Test ImageMagick detection
	 */
	public function test_imagemagick_detection() {
		$reflection = new ReflectionClass( 'Converter_Factory' );
		$method = $reflection->getMethod( 'detect_imagemagick' );
		$method->setAccessible( true );
		
		$result = $method->invoke( null );
		$this->assertIsBool( $result );
		
		// Result should match actual extension availability
		$expected = extension_loaded( 'imagick' ) && class_exists( 'Imagick' );
		if ( $expected ) {
			// If extension is loaded, try to create instance
			try {
				new Imagick();
				$this->assertTrue( $result );
			} catch ( Exception $e ) {
				$this->assertFalse( $result );
			}
		} else {
			$this->assertFalse( $result );
		}
	}

	/**
	 * Test GD detection
	 */
	public function test_gd_detection() {
		$reflection = new ReflectionClass( 'Converter_Factory' );
		$method = $reflection->getMethod( 'detect_gd' );
		$method->setAccessible( true );
		
		$result = $method->invoke( null );
		$this->assertIsBool( $result );
		
		// Result should match actual extension availability
		$expected = extension_loaded( 'gd' ) && function_exists( 'gd_info' );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test WebP support detection
	 */
	public function test_webp_support_detection() {
		$reflection = new ReflectionClass( 'Converter_Factory' );
		$method = $reflection->getMethod( 'detect_webp_support' );
		$method->setAccessible( true );
		
		$result = $method->invoke( null );
		$this->assertIsBool( $result );
		
		// If we have GD, check if WebP is supported
		if ( extension_loaded( 'gd' ) && function_exists( 'gd_info' ) ) {
			$gd_info = gd_info();
			$gd_webp_support = ! empty( $gd_info['WebP Support'] );
			
			// If GD supports WebP, result should be true
			if ( $gd_webp_support ) {
				$this->assertTrue( $result );
			}
		}
	}

	/**
	 * Test AVIF support detection
	 */
	public function test_avif_support_detection() {
		$reflection = new ReflectionClass( 'Converter_Factory' );
		$method = $reflection->getMethod( 'detect_avif_support' );
		$method->setAccessible( true );
		
		$result = $method->invoke( null );
		$this->assertIsBool( $result );
		
		// AVIF support is primarily through ImageMagick
		// GD doesn't natively support AVIF as of PHP 8.1
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			try {
				$imagick = new Imagick();
				$formats = $imagick->queryFormats( 'AVIF' );
				$imagick_avif_support = ! empty( $formats );
				
				if ( $imagick_avif_support ) {
					$this->assertTrue( $result );
				}
			} catch ( Exception $e ) {
				// ImageMagick available but AVIF not supported
			}
		}
	}

	/**
	 * Test converter instance caching
	 */
	public function test_converter_instance_caching() {
		$reflection = new ReflectionClass( 'Converter_Factory' );
		$method = $reflection->getMethod( 'get_converter_instance' );
		$method->setAccessible( true );
		
		// Test with non-existent class
		$result = $method->invoke( null, 'NonExistentClass' );
		$this->assertNull( $result );
		
		// Clear cache property for testing
		$cache_property = $reflection->getProperty( 'converter_cache' );
		$cache_property->setAccessible( true );
		$cache_property->setValue( array() );
	}

	/**
	 * Test capabilities cache loading
	 */
	public function test_capabilities_cache_loading() {
		// Set a transient manually
		$test_capabilities = array(
			'imagemagick'  => true,
			'gd'           => false,
			'webp_support' => true,
			'avif_support' => false,
		);
		
		set_transient( 'wp_image_optimizer_capabilities', $test_capabilities, HOUR_IN_SECONDS );
		
		// Clear the static cache
		Converter_Factory::clear_capabilities_cache();
		
		// Get capabilities should load from transient
		$capabilities = Converter_Factory::get_server_capabilities();
		$this->assertEquals( $test_capabilities, $capabilities );
	}

	/**
	 * Test error handling in ImageMagick detection
	 */
	public function test_imagemagick_detection_error_handling() {
		// This test verifies that exceptions in ImageMagick detection are handled
		$reflection = new ReflectionClass( 'Converter_Factory' );
		$method = $reflection->getMethod( 'detect_imagemagick' );
		$method->setAccessible( true );
		
		// The method should not throw exceptions
		try {
			$result = $method->invoke( null );
			$this->assertIsBool( $result );
		} catch ( Exception $e ) {
			$this->fail( 'ImageMagick detection should not throw exceptions: ' . $e->getMessage() );
		}
	}

	/**
	 * Test error handling in WebP detection
	 */
	public function test_webp_detection_error_handling() {
		$reflection = new ReflectionClass( 'Converter_Factory' );
		$method = $reflection->getMethod( 'detect_webp_support' );
		$method->setAccessible( true );
		
		// The method should not throw exceptions
		try {
			$result = $method->invoke( null );
			$this->assertIsBool( $result );
		} catch ( Exception $e ) {
			$this->fail( 'WebP detection should not throw exceptions: ' . $e->getMessage() );
		}
	}

	/**
	 * Test error handling in AVIF detection
	 */
	public function test_avif_detection_error_handling() {
		$reflection = new ReflectionClass( 'Converter_Factory' );
		$method = $reflection->getMethod( 'detect_avif_support' );
		$method->setAccessible( true );
		
		// The method should not throw exceptions
		try {
			$result = $method->invoke( null );
			$this->assertIsBool( $result );
		} catch ( Exception $e ) {
			$this->fail( 'AVIF detection should not throw exceptions: ' . $e->getMessage() );
		}
	}
}