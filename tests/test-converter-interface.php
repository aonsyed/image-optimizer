<?php
/**
 * Unit tests for Converter Interface
 *
 * @package WP_Image_Optimizer
 */

/**
 * Test class for Converter_Interface
 */
class Test_Converter_Interface extends WP_UnitTestCase {

	/**
	 * Mock converter instance
	 *
	 * @var Mock_Converter
	 */
	private $mock_converter;

	/**
	 * Test upload directory
	 *
	 * @var string
	 */
	private $test_upload_dir;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Include required files
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/interfaces/interface-converter.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'tests/mocks/class-mock-converter.php';
		
		// Create mock converter
		$this->mock_converter = new Mock_Converter();
		
		// Set up test upload directory
		$upload_dir = wp_upload_dir();
		$this->test_upload_dir = $upload_dir['basedir'] . '/test-converter/';
		
		if ( ! file_exists( $this->test_upload_dir ) ) {
			wp_mkdir_p( $this->test_upload_dir );
		}
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		// Clean up test files
		if ( file_exists( $this->test_upload_dir ) ) {
			$this->remove_directory( $this->test_upload_dir );
		}
		
		parent::tearDown();
	}

	/**
	 * Test converter interface implementation
	 */
	public function test_converter_interface_implementation() {
		$this->assertInstanceOf( 'Converter_Interface', $this->mock_converter );
	}

	/**
	 * Test is_available method
	 */
	public function test_is_available() {
		// Test default availability
		$this->assertTrue( $this->mock_converter->is_available() );
		
		// Test setting availability to false
		$this->mock_converter->set_available( false );
		$this->assertFalse( $this->mock_converter->is_available() );
		
		// Test setting availability back to true
		$this->mock_converter->set_available( true );
		$this->assertTrue( $this->mock_converter->is_available() );
	}

	/**
	 * Test convert_to_webp method
	 */
	public function test_convert_to_webp() {
		$source_path = $this->test_upload_dir . 'test.jpg';
		$destination_path = $this->test_upload_dir . 'test.webp';
		
		// Create a mock source file
		file_put_contents( $source_path, 'mock image content' );
		
		// Test successful conversion
		$result = $this->mock_converter->convert_to_webp( $source_path, $destination_path, 80 );
		$this->assertTrue( $result );
		$this->assertFileExists( $destination_path );
		
		// Clean up
		unlink( $destination_path );
		
		// Test conversion failure
		$this->mock_converter->set_conversion_success( false );
		$result = $this->mock_converter->convert_to_webp( $source_path, $destination_path, 80 );
		$this->assertFalse( $result );
		$this->assertFileDoesNotExist( $destination_path );
		
		// Clean up
		unlink( $source_path );
	}

	/**
	 * Test convert_to_avif method
	 */
	public function test_convert_to_avif() {
		$source_path = $this->test_upload_dir . 'test.jpg';
		$destination_path = $this->test_upload_dir . 'test.avif';
		
		// Create a mock source file
		file_put_contents( $source_path, 'mock image content' );
		
		// Test successful conversion
		$result = $this->mock_converter->convert_to_avif( $source_path, $destination_path, 75 );
		$this->assertTrue( $result );
		$this->assertFileExists( $destination_path );
		
		// Clean up
		unlink( $destination_path );
		
		// Test conversion failure
		$this->mock_converter->set_conversion_success( false );
		$result = $this->mock_converter->convert_to_avif( $source_path, $destination_path, 75 );
		$this->assertFalse( $result );
		$this->assertFileDoesNotExist( $destination_path );
		
		// Clean up
		unlink( $source_path );
	}

	/**
	 * Test get_supported_formats method
	 */
	public function test_get_supported_formats() {
		$formats = $this->mock_converter->get_supported_formats();
		
		$this->assertIsArray( $formats );
		$this->assertContains( 'webp', $formats );
		$this->assertContains( 'avif', $formats );
		
		// Test setting custom formats
		$custom_formats = array( 'webp' );
		$this->mock_converter->set_supported_formats( $custom_formats );
		$formats = $this->mock_converter->get_supported_formats();
		$this->assertEquals( $custom_formats, $formats );
	}

	/**
	 * Test get_name method
	 */
	public function test_get_name() {
		$name = $this->mock_converter->get_name();
		$this->assertIsString( $name );
		$this->assertEquals( 'Mock_Converter', $name );
	}

	/**
	 * Test get_priority method
	 */
	public function test_get_priority() {
		$priority = $this->mock_converter->get_priority();
		$this->assertIsInt( $priority );
		$this->assertEquals( 10, $priority );
	}

	/**
	 * Test conversion with unsupported format
	 */
	public function test_conversion_with_unsupported_format() {
		// Set converter to only support WebP
		$this->mock_converter->set_supported_formats( array( 'webp' ) );
		
		$source_path = $this->test_upload_dir . 'test.jpg';
		$destination_path = $this->test_upload_dir . 'test.avif';
		
		// Create a mock source file
		file_put_contents( $source_path, 'mock image content' );
		
		// Try to convert to AVIF (unsupported)
		$result = $this->mock_converter->convert_to_avif( $source_path, $destination_path, 75 );
		$this->assertFalse( $result );
		$this->assertFileDoesNotExist( $destination_path );
		
		// Clean up
		unlink( $source_path );
	}

	/**
	 * Test conversion quality parameters
	 */
	public function test_conversion_quality_parameters() {
		$source_path = $this->test_upload_dir . 'test.jpg';
		$destination_path = $this->test_upload_dir . 'test.webp';
		
		// Create a mock source file
		file_put_contents( $source_path, 'mock image content' );
		
		// Test with different quality values
		$qualities = array( 1, 50, 80, 100 );
		
		foreach ( $qualities as $quality ) {
			$result = $this->mock_converter->convert_to_webp( $source_path, $destination_path, $quality );
			$this->assertTrue( $result, "Conversion should succeed with quality {$quality}" );
			
			if ( file_exists( $destination_path ) ) {
				unlink( $destination_path );
			}
		}
		
		// Clean up
		unlink( $source_path );
	}

	/**
	 * Test converter with different configurations
	 */
	public function test_converter_configurations() {
		// Test converter with only WebP support
		$webp_only_converter = new Mock_Converter( array(
			'supported_formats' => array( 'webp' ),
			'name'              => 'WebP_Only_Converter',
			'priority'          => 5,
		) );
		
		$this->assertEquals( array( 'webp' ), $webp_only_converter->get_supported_formats() );
		$this->assertEquals( 'WebP_Only_Converter', $webp_only_converter->get_name() );
		$this->assertEquals( 5, $webp_only_converter->get_priority() );
		
		// Test unavailable converter
		$unavailable_converter = new Mock_Converter( array(
			'is_available' => false,
		) );
		
		$this->assertFalse( $unavailable_converter->is_available() );
		
		// Test converter that always fails conversions
		$failing_converter = new Mock_Converter( array(
			'conversion_success' => false,
		) );
		
		$source_path = $this->test_upload_dir . 'test.jpg';
		$destination_path = $this->test_upload_dir . 'test.webp';
		
		file_put_contents( $source_path, 'mock image content' );
		
		$result = $failing_converter->convert_to_webp( $source_path, $destination_path );
		$this->assertFalse( $result );
		
		unlink( $source_path );
	}

	/**
	 * Test converter interface method signatures
	 */
	public function test_interface_method_signatures() {
		$reflection = new ReflectionClass( 'Converter_Interface' );
		
		// Test is_available method
		$this->assertTrue( $reflection->hasMethod( 'is_available' ) );
		$method = $reflection->getMethod( 'is_available' );
		$this->assertEquals( 0, $method->getNumberOfParameters() );
		
		// Test convert_to_webp method
		$this->assertTrue( $reflection->hasMethod( 'convert_to_webp' ) );
		$method = $reflection->getMethod( 'convert_to_webp' );
		$this->assertEquals( 3, $method->getNumberOfParameters() );
		
		// Test convert_to_avif method
		$this->assertTrue( $reflection->hasMethod( 'convert_to_avif' ) );
		$method = $reflection->getMethod( 'convert_to_avif' );
		$this->assertEquals( 3, $method->getNumberOfParameters() );
		
		// Test get_supported_formats method
		$this->assertTrue( $reflection->hasMethod( 'get_supported_formats' ) );
		$method = $reflection->getMethod( 'get_supported_formats' );
		$this->assertEquals( 0, $method->getNumberOfParameters() );
		
		// Test get_name method
		$this->assertTrue( $reflection->hasMethod( 'get_name' ) );
		$method = $reflection->getMethod( 'get_name' );
		$this->assertEquals( 0, $method->getNumberOfParameters() );
		
		// Test get_priority method
		$this->assertTrue( $reflection->hasMethod( 'get_priority' ) );
		$method = $reflection->getMethod( 'get_priority' );
		$this->assertEquals( 0, $method->getNumberOfParameters() );
	}

	/**
	 * Helper method to remove directory recursively
	 *
	 * @param string $dir Directory path to remove.
	 */
	private function remove_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		
		foreach ( $files as $file ) {
			$path = $dir . DIRECTORY_SEPARATOR . $file;
			if ( is_dir( $path ) ) {
				$this->remove_directory( $path );
			} else {
				unlink( $path );
			}
		}
		
		rmdir( $dir );
	}
}