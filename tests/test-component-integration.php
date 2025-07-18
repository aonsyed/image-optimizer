<?php
/**
 * Tests for component integration
 *
 * @package WP_Image_Optimizer
 */

class Test_Component_Integration extends WP_UnitTestCase {

	/**
	 * Test image converter and settings integration
	 */
	public function test_image_converter_settings_integration() {
		// Update settings
		WP_Image_Optimizer_Settings_Manager::update_settings( array(
			'enabled' => true,
			'formats' => array(
				'webp' => array(
					'enabled' => true,
					'quality' => 85,
				),
				'avif' => array(
					'enabled' => true,
					'quality' => 70,
				),
			),
		) );
		
		// Create image converter
		$image_converter = new WP_Image_Optimizer_Image_Converter();
		
		// Check if settings are properly integrated
		$reflection = new ReflectionClass( $image_converter );
		$property = $reflection->getProperty( 'settings' );
		$property->setAccessible( true );
		$settings = $property->getValue( $image_converter );
		
		$this->assertTrue( $settings['enabled'] );
		$this->assertTrue( $settings['formats']['webp']['enabled'] );
		$this->assertEquals( 85, $settings['formats']['webp']['quality'] );
		$this->assertTrue( $settings['formats']['avif']['enabled'] );
		$this->assertEquals( 70, $settings['formats']['avif']['quality'] );
	}

	/**
	 * Test converter factory and image converter integration
	 */
	public function test_converter_factory_image_converter_integration() {
		// Create image converter
		$image_converter = new WP_Image_Optimizer_Image_Converter();
		
		// Get converter info
		$converter_info = $image_converter->get_converter_info();
		
		// If no converter is available, skip test
		if ( $converter_info === null ) {
			$this->markTestSkipped( 'No image converter available for testing' );
			return;
		}
		
		// Check converter info structure
		$this->assertIsArray( $converter_info );
		$this->assertArrayHasKey( 'name', $converter_info );
		$this->assertArrayHasKey( 'priority', $converter_info );
		$this->assertArrayHasKey( 'supported_formats', $converter_info );
		$this->assertArrayHasKey( 'is_available', $converter_info );
		$this->assertTrue( $converter_info['is_available'] );
	}

	/**
	 * Test file handler and image converter integration
	 */
	public function test_file_handler_image_converter_integration() {
		// Create file handler
		$file_handler = new WP_Image_Optimizer_File_Handler();
		
		// Create image converter with file handler
		$image_converter = new WP_Image_Optimizer_Image_Converter( $file_handler );
		
		// Create test image path
		$upload_dir = wp_upload_dir();
		$test_image_path = $upload_dir['basedir'] . '/test-integration-image.jpg';
		
		// Check converted versions
		$versions = $image_converter->check_converted_versions( $test_image_path );
		
		// Verify structure
		$this->assertIsArray( $versions );
		$this->assertArrayHasKey( 'webp', $versions );
		$this->assertArrayHasKey( 'avif', $versions );
		
		// Check each format
		foreach ( $versions as $format => $info ) {
			$this->assertArrayHasKey( 'enabled', $info );
			$this->assertArrayHasKey( 'exists', $info );
			$this->assertArrayHasKey( 'path', $info );
			
			// Path should be generated correctly
			$expected_path = str_replace( '.jpg', '.' . $format, $test_image_path );
			$this->assertEquals( $expected_path, $info['path'] );
		}
	}

	/**
	 * Test hooks integration and image converter
	 */
	public function test_hooks_integration_image_converter() {
		// Get hooks integration instance
		$hooks_integration = WP_Image_Optimizer::get_instance()->get_hooks_integration();
		
		// Check if hooks are registered
		$this->assertTrue( has_filter( 'wp_handle_upload' ) );
		
		// Create a mock upload data
		$upload_data = array(
			'file' => '/path/to/test.jpg',
			'url' => 'http://example.com/test.jpg',
			'type' => 'image/jpeg',
		);
		
		// Apply the filter
		$filtered_data = apply_filters( 'wp_handle_upload', $upload_data );
		
		// Should be the same since we're not actually converting
		$this->assertEquals( $upload_data, $filtered_data );
	}

	/**
	 * Test batch processor and image converter integration
	 */
	public function test_batch_processor_image_converter_integration() {
		// Get batch processor instance
		$batch_processor = WP_Image_Optimizer::get_instance()->get_batch_processor();
		
		// Check if batch processor has image converter
		$reflection = new ReflectionClass( $batch_processor );
		$property = $reflection->getProperty( 'image_converter' );
		$property->setAccessible( true );
		$image_converter = $property->getValue( $batch_processor );
		
		$this->assertInstanceOf( 'WP_Image_Optimizer_Image_Converter', $image_converter );
	}

	/**
	 * Test database manager and settings manager integration
	 */
	public function test_database_manager_settings_manager_integration() {
		// Get database manager instance
		$db_manager = WP_Image_Optimizer_Database_Manager::get_instance();
		
		// Update settings
		$test_settings = array(
			'enabled' => false,
			'test_key' => 'test_value',
		);
		WP_Image_Optimizer_Settings_Manager::update_settings( $test_settings );
		
		// Get settings from database
		$option = get_option( 'wp_image_optimizer_settings' );
		$this->assertIsArray( $option );
		$this->assertArrayHasKey( 'enabled', $option );
		$this->assertFalse( $option['enabled'] );
		$this->assertArrayHasKey( 'test_key', $option );
		$this->assertEquals( 'test_value', $option['test_key'] );
		
		// Reset settings
		WP_Image_Optimizer_Settings_Manager::reset_settings();
		
		// Check if settings are reset
		$reset_option = get_option( 'wp_image_optimizer_settings' );
		$this->assertIsArray( $reset_option );
		$this->assertArrayHasKey( 'enabled', $reset_option );
		$this->assertTrue( $reset_option['enabled'] );
		$this->assertArrayNotHasKey( 'test_key', $reset_option );
	}

	/**
	 * Test error handler integration
	 */
	public function test_error_handler_integration() {
		// Create error handler
		$error_handler = new WP_Image_Optimizer_Error_Handler();
		
		// Log an error
		$error_code = 'test_error';
		$error_message = 'Test error message';
		$error_handler->log_error( $error_code, $error_message );
		
		// Get error logs
		$error_logs = $error_handler->get_error_logs();
		
		// Check if error is logged
		$this->assertIsArray( $error_logs );
		$this->assertNotEmpty( $error_logs );
		
		// Get the latest error
		$latest_error = end( $error_logs );
		$this->assertIsArray( $latest_error );
		$this->assertArrayHasKey( 'code', $latest_error );
		$this->assertArrayHasKey( 'message', $latest_error );
		$this->assertArrayHasKey( 'timestamp', $latest_error );
		$this->assertEquals( $error_code, $latest_error['code'] );
		$this->assertEquals( $error_message, $latest_error['message'] );
	}

	/**
	 * Test security validator integration
	 */
	public function test_security_validator_integration() {
		// Create security validator
		$security_validator = new WP_Image_Optimizer_Security_Validator();
		
		// Test file path validation
		$valid_path = WP_CONTENT_DIR . '/uploads/test.jpg';
		$invalid_path = '/etc/passwd';
		
		$this->assertTrue( $security_validator->validate_file_path( $valid_path ) );
		$this->assertFalse( $security_validator->validate_file_path( $invalid_path ) );
		
		// Test MIME type validation
		$this->assertTrue( $security_validator->validate_mime_type( 'image/jpeg' ) );
		$this->assertTrue( $security_validator->validate_mime_type( 'image/png' ) );
		$this->assertTrue( $security_validator->validate_mime_type( 'image/gif' ) );
		$this->assertFalse( $security_validator->validate_mime_type( 'application/php' ) );
		$this->assertFalse( $security_validator->validate_mime_type( 'text/html' ) );
	}

	/**
	 * Test cleanup manager integration
	 */
	public function test_cleanup_manager_integration() {
		// Create cleanup manager
		$cleanup_manager = new WP_Image_Optimizer_Cleanup_Manager();
		
		// Create test file paths
		$upload_dir = wp_upload_dir();
		$original_path = $upload_dir['basedir'] . '/test-cleanup.jpg';
		$webp_path = $upload_dir['basedir'] . '/test-cleanup.webp';
		$avif_path = $upload_dir['basedir'] . '/test-cleanup.avif';
		
		// Create test files
		touch( $original_path );
		touch( $webp_path );
		touch( $avif_path );
		
		// Register files for cleanup
		$cleanup_manager->register_converted_file( $original_path, $webp_path );
		$cleanup_manager->register_converted_file( $original_path, $avif_path );
		
		// Clean up files
		$cleaned_files = $cleanup_manager->cleanup_for_original( $original_path );
		
		// Check if files are cleaned up
		$this->assertIsArray( $cleaned_files );
		$this->assertCount( 2, $cleaned_files );
		$this->assertContains( $webp_path, $cleaned_files );
		$this->assertContains( $avif_path, $cleaned_files );
		
		// Files should be deleted
		$this->assertFileDoesNotExist( $webp_path );
		$this->assertFileDoesNotExist( $avif_path );
		
		// Clean up original file
		if ( file_exists( $original_path ) ) {
			unlink( $original_path );
		}
	}
}