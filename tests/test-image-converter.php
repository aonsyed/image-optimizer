<?php
/**
 * Tests for WP_Image_Optimizer_Image_Converter class
 *
 * @package WP_Image_Optimizer
 */

class Test_Image_Converter extends WP_UnitTestCase {

	/**
	 * Image converter instance
	 *
	 * @var WP_Image_Optimizer_Image_Converter
	 */
	private $converter;

	/**
	 * Mock file handler
	 *
	 * @var WP_Image_Optimizer_File_Handler
	 */
	private $mock_file_handler;

	/**
	 * Test image paths
	 *
	 * @var array
	 */
	private $test_images = array();

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Create mock file handler
		$this->mock_file_handler = $this->createMock( WP_Image_Optimizer_File_Handler::class );
		
		// Initialize converter with mock file handler
		$this->converter = new WP_Image_Optimizer_Image_Converter( $this->mock_file_handler );

		// Set up test image paths
		$upload_dir = wp_upload_dir();
		$this->test_images = array(
			'jpeg' => $upload_dir['basedir'] . '/test-image.jpg',
			'png' => $upload_dir['basedir'] . '/test-image.png',
			'webp' => $upload_dir['basedir'] . '/test-image.webp',
			'avif' => $upload_dir['basedir'] . '/test-image.avif',
		);

		// Reset settings to defaults
		WP_Image_Optimizer_Settings_Manager::reset_settings( true );
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		// Clean up test files
		foreach ( $this->test_images as $path ) {
			if ( file_exists( $path ) ) {
				wp_delete_file( $path );
			}
		}

		parent::tearDown();
	}

	/**
	 * Test constructor initialization
	 */
	public function test_constructor_initialization() {
		$converter = new WP_Image_Optimizer_Image_Converter();
		
		$this->assertInstanceOf( WP_Image_Optimizer_Image_Converter::class, $converter );
		
		// Test with custom file handler
		$file_handler = new WP_Image_Optimizer_File_Handler();
		$converter_with_handler = new WP_Image_Optimizer_Image_Converter( $file_handler );
		
		$this->assertInstanceOf( WP_Image_Optimizer_Image_Converter::class, $converter_with_handler );
	}

	/**
	 * Test convert_uploaded_image with invalid data
	 */
	public function test_convert_uploaded_image_invalid_data() {
		// Test with null data
		$result = $this->converter->convert_uploaded_image( null );
		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_upload_data', $result->get_error_code() );

		// Test with empty array
		$result = $this->converter->convert_uploaded_image( array() );
		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_upload_data', $result->get_error_code() );

		// Test with array missing 'file' key
		$result = $this->converter->convert_uploaded_image( array( 'url' => 'test.jpg' ) );
		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_upload_data', $result->get_error_code() );
	}

	/**
	 * Test convert_uploaded_image when conversion is disabled
	 */
	public function test_convert_uploaded_image_disabled() {
		// Disable conversion
		WP_Image_Optimizer_Settings_Manager::update_settings( array( 'enabled' => false ) );

		$upload_data = array( 'file' => $this->test_images['jpeg'] );
		$result = $this->converter->convert_uploaded_image( $upload_data );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['skipped'] );
		$this->assertEquals( 'conversion_disabled_or_manual', $result['reason'] );
	}

	/**
	 * Test convert_uploaded_image in manual mode
	 */
	public function test_convert_uploaded_image_manual_mode() {
		// Set to manual mode
		WP_Image_Optimizer_Settings_Manager::update_settings( array( 'conversion_mode' => 'manual' ) );

		$upload_data = array( 'file' => $this->test_images['jpeg'] );
		$result = $this->converter->convert_uploaded_image( $upload_data );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['skipped'] );
		$this->assertEquals( 'conversion_disabled_or_manual', $result['reason'] );
	}

	/**
	 * Test convert_on_demand with invalid parameters
	 */
	public function test_convert_on_demand_invalid_parameters() {
		// Test with empty path
		$result = $this->converter->convert_on_demand( '', 'webp' );
		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_parameters', $result->get_error_code() );

		// Test with empty format
		$result = $this->converter->convert_on_demand( $this->test_images['jpeg'], '' );
		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_parameters', $result->get_error_code() );

		// Test with invalid format
		$result = $this->converter->convert_on_demand( $this->test_images['jpeg'], 'invalid' );
		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_format', $result->get_error_code() );
	}

	/**
	 * Test convert_on_demand with disabled format
	 */
	public function test_convert_on_demand_disabled_format() {
		// Disable WebP format
		WP_Image_Optimizer_Settings_Manager::update_settings( array(
			'formats' => array(
				'webp' => array( 'enabled' => false ),
			),
		) );

		$result = $this->converter->convert_on_demand( $this->test_images['jpeg'], 'webp' );
		$this->assertWPError( $result );
		$this->assertEquals( 'format_disabled', $result->get_error_code() );
	}

	/**
	 * Test convert_on_demand when converted file already exists
	 */
	public function test_convert_on_demand_file_exists() {
		$original_path = $this->test_images['jpeg'];
		$converted_path = $this->test_images['webp'];

		// Mock file handler to return valid converted path
		$this->mock_file_handler->method( 'generate_converted_path' )
			->willReturn( $converted_path );

		// Create the converted file
		touch( $converted_path );

		$result = $this->converter->convert_on_demand( $original_path, 'webp' );
		$this->assertEquals( $converted_path, $result );
	}

	/**
	 * Test convert_image with empty path
	 */
	public function test_convert_image_empty_path() {
		$result = $this->converter->convert_image( '' );
		$this->assertWPError( $result );
		$this->assertEquals( 'empty_path', $result->get_error_code() );
	}

	/**
	 * Test convert_image with file validation failure
	 */
	public function test_convert_image_validation_failure() {
		$original_path = $this->test_images['jpeg'];

		// Mock file handler to return validation error
		$this->mock_file_handler->method( 'file_exists_and_readable' )
			->willReturn( new WP_Error( 'file_not_found', 'File not found' ) );

		$result = $this->converter->convert_image( $original_path );
		$this->assertWPError( $result );
		$this->assertEquals( 'file_not_found', $result->get_error_code() );
	}

	/**
	 * Test check_converted_versions with empty path
	 */
	public function test_check_converted_versions_empty_path() {
		$result = $this->converter->check_converted_versions( '' );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test check_converted_versions with valid path
	 */
	public function test_check_converted_versions_valid_path() {
		$original_path = $this->test_images['jpeg'];

		// Mock file handler responses
		$this->mock_file_handler->method( 'generate_converted_path' )
			->willReturnCallback( function( $path, $format ) {
				return str_replace( '.jpg', '.' . $format, $path );
			} );

		$result = $this->converter->check_converted_versions( $original_path );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'webp', $result );
		$this->assertArrayHasKey( 'avif', $result );

		// Check structure of each format
		foreach ( $result as $format => $info ) {
			$this->assertArrayHasKey( 'enabled', $info );
			$this->assertArrayHasKey( 'exists', $info );
			$this->assertArrayHasKey( 'path', $info );
		}
	}

	/**
	 * Test cleanup_converted_files
	 */
	public function test_cleanup_converted_files() {
		$original_path = $this->test_images['jpeg'];
		$expected_result = array( 'cleaned_file1.webp', 'cleaned_file2.avif' );

		// Mock file handler cleanup method
		$this->mock_file_handler->method( 'cleanup_converted_files' )
			->with( $original_path )
			->willReturn( $expected_result );

		$result = $this->converter->cleanup_converted_files( $original_path );
		$this->assertEquals( $expected_result, $result );
	}

	/**
	 * Test get_conversion_stats
	 */
	public function test_get_conversion_stats() {
		$stats = $this->converter->get_conversion_stats();

		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'conversions_attempted', $stats );
		$this->assertArrayHasKey( 'conversions_successful', $stats );
		$this->assertArrayHasKey( 'conversions_failed', $stats );
		$this->assertArrayHasKey( 'space_saved', $stats );

		// Initial stats should be zero
		$this->assertEquals( 0, $stats['conversions_attempted'] );
		$this->assertEquals( 0, $stats['conversions_successful'] );
		$this->assertEquals( 0, $stats['conversions_failed'] );
		$this->assertEquals( 0, $stats['space_saved'] );
	}

	/**
	 * Test reset_conversion_stats
	 */
	public function test_reset_conversion_stats() {
		// Get initial stats
		$initial_stats = $this->converter->get_conversion_stats();

		// Reset stats
		$this->converter->reset_conversion_stats();

		// Get stats after reset
		$reset_stats = $this->converter->get_conversion_stats();

		// Should be the same as initial (all zeros)
		$this->assertEquals( $initial_stats, $reset_stats );
	}

	/**
	 * Test get_converter_info when no converter available
	 */
	public function test_get_converter_info_no_converter() {
		// Create converter instance that will have no converter available
		// This happens when neither ImageMagick nor GD are available
		$converter = new WP_Image_Optimizer_Image_Converter();
		
		// If no converter is available, should return null
		$info = $converter->get_converter_info();
		
		// This test depends on server configuration
		// If a converter is available, info will be an array
		// If no converter is available, info will be null
		if ( $info === null ) {
			$this->assertNull( $info );
		} else {
			$this->assertIsArray( $info );
			$this->assertArrayHasKey( 'name', $info );
			$this->assertArrayHasKey( 'priority', $info );
			$this->assertArrayHasKey( 'supported_formats', $info );
			$this->assertArrayHasKey( 'is_available', $info );
		}
	}

	/**
	 * Test can_convert when conversion is disabled
	 */
	public function test_can_convert_disabled() {
		// Disable conversion
		WP_Image_Optimizer_Settings_Manager::update_settings( array( 'enabled' => false ) );

		$result = $this->converter->can_convert();
		$this->assertWPError( $result );
		$this->assertEquals( 'conversion_disabled', $result->get_error_code() );
	}

	/**
	 * Test can_convert when no formats are enabled
	 */
	public function test_can_convert_no_formats_enabled() {
		// Disable all formats
		WP_Image_Optimizer_Settings_Manager::update_settings( array(
			'formats' => array(
				'webp' => array( 'enabled' => false ),
				'avif' => array( 'enabled' => false ),
			),
		) );

		$result = $this->converter->can_convert();
		$this->assertWPError( $result );
		$this->assertEquals( 'no_formats_enabled', $result->get_error_code() );
	}

	/**
	 * Test get_conversion_metadata with invalid attachment ID
	 */
	public function test_get_conversion_metadata_invalid_id() {
		// Test with non-numeric ID
		$result = $this->converter->get_conversion_metadata( 'invalid' );
		$this->assertFalse( $result );

		// Test with zero ID
		$result = $this->converter->get_conversion_metadata( 0 );
		$this->assertFalse( $result );

		// Test with negative ID
		$result = $this->converter->get_conversion_metadata( -1 );
		$this->assertFalse( $result );
	}

	/**
	 * Test get_conversion_metadata with valid attachment ID
	 */
	public function test_get_conversion_metadata_valid_id() {
		// Create a test attachment
		$attachment_id = $this->factory->attachment->create( array(
			'post_mime_type' => 'image/jpeg',
		) );

		// Test with attachment that has no conversion metadata
		$result = $this->converter->get_conversion_metadata( $attachment_id );
		$this->assertFalse( $result );

		// Add some conversion metadata
		$test_metadata = array(
			time() => array(
				'original_path' => '/path/to/image.jpg',
				'conversions' => array( 'webp' => array( 'path' => '/path/to/image.webp' ) ),
				'errors' => array(),
				'space_saved' => 1024,
			),
		);
		update_post_meta( $attachment_id, '_wp_image_optimizer_conversions', $test_metadata );

		// Test with attachment that has conversion metadata
		$result = $this->converter->get_conversion_metadata( $attachment_id );
		$this->assertIsArray( $result );
		$this->assertEquals( $test_metadata, $result );
	}

	/**
	 * Test integration with settings manager
	 */
	public function test_settings_integration() {
		// Update settings
		$new_settings = array(
			'formats' => array(
				'webp' => array(
					'enabled' => true,
					'quality' => 90,
				),
				'avif' => array(
					'enabled' => false,
					'quality' => 60,
				),
			),
		);
		WP_Image_Optimizer_Settings_Manager::update_settings( $new_settings );

		// Create new converter to pick up updated settings
		$converter = new WP_Image_Optimizer_Image_Converter();

		// Test that settings are properly integrated
		$versions = $converter->check_converted_versions( $this->test_images['jpeg'] );
		
		$this->assertTrue( $versions['webp']['enabled'] );
		$this->assertFalse( $versions['avif']['enabled'] );
	}

	/**
	 * Helper method to create a test image file
	 *
	 * @param string $path Path to create the image
	 * @param string $content Optional content for the file
	 */
	private function create_test_image( $path, $content = 'test image content' ) {
		$dir = dirname( $path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		file_put_contents( $path, $content );
	}

	/**
	 * Test error handling in convert_single_format
	 */
	public function test_convert_single_format_error_handling() {
		$original_path = $this->test_images['jpeg'];

		// Mock file handler to return error for path generation
		$this->mock_file_handler->method( 'generate_converted_path' )
			->willReturn( new WP_Error( 'path_error', 'Path generation failed' ) );

		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->converter );
		$method = $reflection->getMethod( 'convert_single_format' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->converter, $original_path, 'webp' );
		$this->assertWPError( $result );
		$this->assertEquals( 'path_error', $result->get_error_code() );
	}

	/**
	 * Test file validation in validate_original_file
	 */
	public function test_validate_original_file() {
		$original_path = $this->test_images['jpeg'];

		// Mock file handler methods for validation
		$this->mock_file_handler->method( 'file_exists_and_readable' )
			->willReturn( true );
		$this->mock_file_handler->method( 'validate_file_size' )
			->willReturn( true );
		$this->mock_file_handler->method( 'validate_mime_type' )
			->willReturn( true );

		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->converter );
		$method = $reflection->getMethod( 'validate_original_file' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->converter, $original_path );
		$this->assertTrue( $result );
	}
}