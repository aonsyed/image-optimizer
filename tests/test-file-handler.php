<?php
/**
 * Unit tests for WP_Image_Optimizer_File_Handler class
 *
 * @package WP_Image_Optimizer
 */

class Test_WP_Image_Optimizer_File_Handler extends WP_UnitTestCase {

	/**
	 * File handler instance
	 *
	 * @var WP_Image_Optimizer_File_Handler
	 */
	private $file_handler;

	/**
	 * Test upload directory
	 *
	 * @var string
	 */
	private $test_upload_dir;

	/**
	 * Test file path
	 *
	 * @var string
	 */
	private $test_file_path;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Create file handler instance
		$this->file_handler = new WP_Image_Optimizer_File_Handler();
		
		// Set up test upload directory
		$upload_dir = wp_upload_dir();
		$this->test_upload_dir = $upload_dir['basedir'] . '/test-images';
		
		// Create test directory if it doesn't exist
		if ( ! is_dir( $this->test_upload_dir ) ) {
			wp_mkdir_p( $this->test_upload_dir );
		}
		
		// Create a test image file
		$this->test_file_path = $this->test_upload_dir . '/test-image.jpg';
		$this->create_test_image( $this->test_file_path );
	}

	/**
	 * Clean up test environment
	 */
	public function tearDown(): void {
		// Clean up test files
		$this->cleanup_test_files();
		
		parent::tearDown();
	}

	/**
	 * Test constructor with default settings
	 */
	public function test_constructor_default_settings() {
		$file_handler = new WP_Image_Optimizer_File_Handler();
		
		$this->assertEquals( 10485760, $file_handler->get_max_file_size() ); // 10MB
		$this->assertEquals( 
			array( 'image/jpeg', 'image/png', 'image/gif' ), 
			$file_handler->get_allowed_mime_types() 
		);
	}

	/**
	 * Test constructor with custom settings
	 */
	public function test_constructor_custom_settings() {
		$settings = array(
			'max_file_size' => 5242880, // 5MB
			'allowed_mime_types' => array( 'image/jpeg', 'image/png' )
		);
		
		$file_handler = new WP_Image_Optimizer_File_Handler( $settings );
		
		$this->assertEquals( 5242880, $file_handler->get_max_file_size() );
		$this->assertEquals( 
			array( 'image/jpeg', 'image/png' ), 
			$file_handler->get_allowed_mime_types() 
		);
	}

	/**
	 * Test generate_converted_path with valid inputs
	 */
	public function test_generate_converted_path_valid() {
		$webp_path = $this->file_handler->generate_converted_path( $this->test_file_path, 'webp' );
		$avif_path = $this->file_handler->generate_converted_path( $this->test_file_path, 'avif' );
		
		$this->assertNotInstanceOf( 'WP_Error', $webp_path );
		$this->assertNotInstanceOf( 'WP_Error', $avif_path );
		$this->assertEquals( $this->test_upload_dir . '/test-image.webp', $webp_path );
		$this->assertEquals( $this->test_upload_dir . '/test-image.avif', $avif_path );
	}

	/**
	 * Test generate_converted_path with invalid format
	 */
	public function test_generate_converted_path_invalid_format() {
		$result = $this->file_handler->generate_converted_path( $this->test_file_path, 'invalid' );
		
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid_format', $result->get_error_code() );
	}

	/**
	 * Test generate_converted_path with empty parameters
	 */
	public function test_generate_converted_path_empty_parameters() {
		$result1 = $this->file_handler->generate_converted_path( '', 'webp' );
		$result2 = $this->file_handler->generate_converted_path( $this->test_file_path, '' );
		
		$this->assertInstanceOf( 'WP_Error', $result1 );
		$this->assertInstanceOf( 'WP_Error', $result2 );
		$this->assertEquals( 'invalid_parameters', $result1->get_error_code() );
		$this->assertEquals( 'invalid_parameters', $result2->get_error_code() );
	}

	/**
	 * Test generate_converted_path with non-existent file
	 */
	public function test_generate_converted_path_nonexistent_file() {
		$nonexistent_path = $this->test_upload_dir . '/nonexistent.jpg';
		$result = $this->file_handler->generate_converted_path( $nonexistent_path, 'webp' );
		
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'file_not_found', $result->get_error_code() );
	}

	/**
	 * Test file_exists_and_readable with existing file
	 */
	public function test_file_exists_and_readable_existing_file() {
		$result = $this->file_handler->file_exists_and_readable( $this->test_file_path );
		
		$this->assertTrue( $result );
	}

	/**
	 * Test file_exists_and_readable with non-existent file
	 */
	public function test_file_exists_and_readable_nonexistent_file() {
		$nonexistent_path = $this->test_upload_dir . '/nonexistent.jpg';
		$result = $this->file_handler->file_exists_and_readable( $nonexistent_path );
		
		$this->assertFalse( $result );
	}

	/**
	 * Test validate_write_permissions with valid directory
	 */
	public function test_validate_write_permissions_valid() {
		$test_path = $this->test_upload_dir . '/new-file.webp';
		$result = $this->file_handler->validate_write_permissions( $test_path );
		
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_file_size with valid file
	 */
	public function test_validate_file_size_valid() {
		$result = $this->file_handler->validate_file_size( $this->test_file_path );
		
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_file_size with file too large
	 */
	public function test_validate_file_size_too_large() {
		// Set a very small max file size
		$this->file_handler->set_max_file_size( 100 ); // 100 bytes
		
		$result = $this->file_handler->validate_file_size( $this->test_file_path );
		
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'file_too_large', $result->get_error_code() );
	}

	/**
	 * Test validate_mime_type with valid JPEG file
	 */
	public function test_validate_mime_type_valid() {
		$result = $this->file_handler->validate_mime_type( $this->test_file_path );
		
		$this->assertTrue( $result );
	}

	/**
	 * Test cleanup_converted_files
	 */
	public function test_cleanup_converted_files() {
		// Create converted files
		$webp_path = $this->test_upload_dir . '/test-image.webp';
		$avif_path = $this->test_upload_dir . '/test-image.avif';
		
		$this->create_test_image( $webp_path );
		$this->create_test_image( $avif_path );
		
		// Verify files exist
		$this->assertTrue( file_exists( $webp_path ) );
		$this->assertTrue( file_exists( $avif_path ) );
		
		// Clean up converted files
		$result = $this->file_handler->cleanup_converted_files( $this->test_file_path );
		
		$this->assertNotInstanceOf( 'WP_Error', $result );
		$this->assertIsArray( $result );
		$this->assertContains( $webp_path, $result );
		$this->assertContains( $avif_path, $result );
		
		// Verify files are deleted
		$this->assertFalse( file_exists( $webp_path ) );
		$this->assertFalse( file_exists( $avif_path ) );
	}

	/**
	 * Test cleanup_orphaned_files
	 */
	public function test_cleanup_orphaned_files() {
		// Create converted files
		$webp_path = $this->test_upload_dir . '/orphaned-image.webp';
		$avif_path = $this->test_upload_dir . '/orphaned-image.avif';
		$original_path = $this->test_upload_dir . '/orphaned-image.jpg';
		
		$this->create_test_image( $webp_path );
		$this->create_test_image( $avif_path );
		
		// Don't create the original file (making the converted files orphaned)
		
		// Clean up orphaned files
		$result = $this->file_handler->cleanup_orphaned_files( $original_path );
		
		$this->assertNotInstanceOf( 'WP_Error', $result );
		$this->assertIsArray( $result );
		$this->assertContains( $webp_path, $result );
		$this->assertContains( $avif_path, $result );
		
		// Verify files are deleted
		$this->assertFalse( file_exists( $webp_path ) );
		$this->assertFalse( file_exists( $avif_path ) );
	}

	/**
	 * Test delete_file with valid file
	 */
	public function test_delete_file_valid() {
		$test_file = $this->test_upload_dir . '/delete-test.jpg';
		$this->create_test_image( $test_file );
		
		$this->assertTrue( file_exists( $test_file ) );
		
		$result = $this->file_handler->delete_file( $test_file );
		
		$this->assertTrue( $result );
		$this->assertFalse( file_exists( $test_file ) );
	}

	/**
	 * Test delete_file with non-existent file
	 */
	public function test_delete_file_nonexistent() {
		$nonexistent_file = $this->test_upload_dir . '/nonexistent.jpg';
		
		$result = $this->file_handler->delete_file( $nonexistent_file );
		
		$this->assertTrue( $result ); // Should return true for non-existent files
	}

	/**
	 * Test get_file_info with valid file
	 */
	public function test_get_file_info_valid() {
		$result = $this->file_handler->get_file_info( $this->test_file_path );
		
		$this->assertNotInstanceOf( 'WP_Error', $result );
		$this->assertIsArray( $result );
		$this->assertEquals( $this->test_file_path, $result['path'] );
		$this->assertEquals( 'test-image.jpg', $result['basename'] );
		$this->assertEquals( 'jpg', $result['extension'] );
		$this->assertEquals( 'test-image', $result['filename'] );
		$this->assertTrue( $result['is_readable'] );
		$this->assertTrue( $result['is_within_upload_dir'] );
	}

	/**
	 * Test setter and getter methods
	 */
	public function test_setters_and_getters() {
		// Test max file size
		$this->assertTrue( $this->file_handler->set_max_file_size( 5242880 ) );
		$this->assertEquals( 5242880, $this->file_handler->get_max_file_size() );
		
		// Test invalid max file size
		$this->assertFalse( $this->file_handler->set_max_file_size( -1 ) );
		$this->assertFalse( $this->file_handler->set_max_file_size( 'invalid' ) );
		
		// Test allowed MIME types
		$new_types = array( 'image/jpeg', 'image/png' );
		$this->assertTrue( $this->file_handler->set_allowed_mime_types( $new_types ) );
		$this->assertEquals( $new_types, $this->file_handler->get_allowed_mime_types() );
		
		// Test invalid MIME types
		$this->assertFalse( $this->file_handler->set_allowed_mime_types( array() ) );
		$this->assertFalse( $this->file_handler->set_allowed_mime_types( 'invalid' ) );
	}

	/**
	 * Test get_upload_dir method
	 */
	public function test_get_upload_dir() {
		$result = $this->file_handler->get_upload_dir();
		$expected = wp_upload_dir();
		
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test path sanitization with directory traversal
	 */
	public function test_path_sanitization_directory_traversal() {
		$malicious_path = $this->test_upload_dir . '/../../../etc/passwd';
		
		// This should trigger the sanitize_file_path method internally
		$result = $this->file_handler->file_exists_and_readable( $malicious_path );
		
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid_path', $result->get_error_code() );
	}

	/**
	 * Test empty path validation
	 */
	public function test_empty_path_validation() {
		$methods_to_test = array(
			'file_exists_and_readable',
			'validate_write_permissions',
			'validate_file_size',
			'validate_mime_type',
			'delete_file',
			'get_file_info',
			'cleanup_orphaned_files',
			'cleanup_converted_files'
		);
		
		foreach ( $methods_to_test as $method ) {
			$result = $this->file_handler->$method( '' );
			$this->assertInstanceOf( 'WP_Error', $result );
			$this->assertEquals( 'empty_path', $result->get_error_code() );
		}
	}

	/**
	 * Create a test image file
	 *
	 * @param string $file_path Path where to create the test file
	 */
	private function create_test_image( $file_path ) {
		// Create a simple test image (1x1 pixel JPEG)
		$image_data = base64_decode( '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A' );
		file_put_contents( $file_path, $image_data );
	}

	/**
	 * Clean up test files and directories
	 */
	private function cleanup_test_files() {
		if ( is_dir( $this->test_upload_dir ) ) {
			$files = glob( $this->test_upload_dir . '/*' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file );
				}
			}
			rmdir( $this->test_upload_dir );
		}
	}
}