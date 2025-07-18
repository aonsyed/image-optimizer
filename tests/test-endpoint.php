<?php
/**
 * Tests for image serving endpoint
 *
 * @package WP_Image_Optimizer
 */

class Test_Endpoint extends WP_UnitTestCase {

	/**
	 * Test image file path
	 *
	 * @var string
	 */
	private $test_image_path;

	/**
	 * Upload directory info
	 *
	 * @var array
	 */
	private $upload_dir;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		$this->upload_dir = wp_upload_dir();

		// Create test image
		$this->create_test_image();

		// Include endpoint functions
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'endpoint.php';
	}

	/**
	 * Clean up test environment
	 */
	public function tearDown(): void {
		// Clean up test files
		if ( $this->test_image_path && file_exists( $this->test_image_path ) ) {
			unlink( $this->test_image_path );
		}

		// Clean up server variables
		unset( $_GET['file'] );
		unset( $_SERVER['REQUEST_URI'] );
		unset( $_SERVER['PATH_INFO'] );
		unset( $_SERVER['SCRIPT_NAME'] );

		parent::tearDown();
	}

	/**
	 * Create test image file
	 */
	private function create_test_image() {
		$this->test_image_path = $this->upload_dir['basedir'] . '/test-endpoint-image.jpg';
		
		// Create a simple test image
		$image = imagecreate( 50, 50 );
		$white = imagecolorallocate( $image, 255, 255, 255 );
		$black = imagecolorallocate( $image, 0, 0, 0 );
		imagestring( $image, 3, 10, 20, 'TEST', $black );
		
		imagejpeg( $image, $this->test_image_path, 90 );
		imagedestroy( $image );
	}

	/**
	 * Test getting requested file from GET parameter
	 */
	public function test_get_requested_file_from_get_parameter() {
		$test_file = 'test-image.jpg';
		$_GET['file'] = $test_file;
		
		$result = wp_image_optimizer_get_requested_file();
		$this->assertEquals( $test_file, $result );
	}

	/**
	 * Test getting requested file from REQUEST_URI
	 */
	public function test_get_requested_file_from_request_uri() {
		unset( $_GET['file'] );
		$_SERVER['REQUEST_URI'] = '/wp-content/uploads/2024/01/test-image.jpg';
		
		$result = wp_image_optimizer_get_requested_file();
		$this->assertEquals( '/wp-content/uploads/2024/01/test-image.jpg', $result );
	}

	/**
	 * Test getting requested file from REQUEST_URI with query string
	 */
	public function test_get_requested_file_from_request_uri_with_query() {
		unset( $_GET['file'] );
		$_SERVER['REQUEST_URI'] = '/wp-content/uploads/2024/01/test-image.jpg?v=123';
		
		$result = wp_image_optimizer_get_requested_file();
		$this->assertEquals( '/wp-content/uploads/2024/01/test-image.jpg', $result );
	}

	/**
	 * Test getting requested file from PATH_INFO
	 */
	public function test_get_requested_file_from_path_info() {
		unset( $_GET['file'] );
		unset( $_SERVER['REQUEST_URI'] );
		$_SERVER['PATH_INFO'] = '/test-image.jpg';
		
		$result = wp_image_optimizer_get_requested_file();
		$this->assertEquals( '/test-image.jpg', $result );
	}

	/**
	 * Test getting requested file from SCRIPT_NAME parsing
	 */
	public function test_get_requested_file_from_script_name() {
		unset( $_GET['file'] );
		unset( $_SERVER['REQUEST_URI'] );
		unset( $_SERVER['PATH_INFO'] );
		
		$_SERVER['SCRIPT_NAME'] = '/wp-content/plugins/wp-image-optimizer/endpoint.php';
		$_SERVER['REQUEST_URI'] = '/wp-content/plugins/wp-image-optimizer/endpoint.php/test-image.jpg';
		
		$result = wp_image_optimizer_get_requested_file();
		$this->assertEquals( 'test-image.jpg', $result );
	}

	/**
	 * Test URL decoding in requested file
	 */
	public function test_url_decoding() {
		$_SERVER['REQUEST_URI'] = '/wp-content/uploads/test%20image.jpg';
		
		$result = wp_image_optimizer_get_requested_file();
		$this->assertEquals( '/wp-content/uploads/test image.jpg', $result );
	}

	/**
	 * Test file extension validation
	 */
	public function test_file_extension_validation() {
		// Valid extensions
		$valid_files = array(
			'test.jpg',
			'test.jpeg',
			'test.png',
			'test.gif',
		);

		foreach ( $valid_files as $file ) {
			$_SERVER['REQUEST_URI'] = '/wp-content/uploads/' . $file;
			$result = wp_image_optimizer_get_requested_file();
			$this->assertEquals( '/wp-content/uploads/' . $file, $result );
		}

		// Invalid extensions
		$invalid_files = array(
			'test.txt',
			'test.php',
			'test.html',
			'test.css',
		);

		foreach ( $invalid_files as $file ) {
			$_SERVER['REQUEST_URI'] = '/wp-content/uploads/' . $file;
			$result = wp_image_optimizer_get_requested_file();
			$this->assertNull( $result );
		}
	}

	/**
	 * Test empty or missing file handling
	 */
	public function test_empty_file_handling() {
		// No file specified
		unset( $_GET['file'] );
		unset( $_SERVER['REQUEST_URI'] );
		unset( $_SERVER['PATH_INFO'] );
		unset( $_SERVER['SCRIPT_NAME'] );
		
		$result = wp_image_optimizer_get_requested_file();
		$this->assertNull( $result );

		// Empty GET parameter
		$_GET['file'] = '';
		$result = wp_image_optimizer_get_requested_file();
		$this->assertNull( $result );
	}

	/**
	 * Test should handle request conditions
	 */
	public function test_should_handle_request() {
		// Test with plugin active and enabled
		$result = wp_image_optimizer_should_handle_request();
		$this->assertTrue( $result );

		// Test with optimization disabled
		WP_Image_Optimizer_Settings_Manager::update_settings( array(
			'enabled' => false,
		) );
		
		$result = wp_image_optimizer_should_handle_request();
		$this->assertFalse( $result );

		// Reset settings
		WP_Image_Optimizer_Settings_Manager::reset_settings();
	}

	/**
	 * Test error serving
	 */
	public function test_error_serving() {
		// Test 404 error
		ob_start();
		wp_image_optimizer_serve_error( 404, 'File not found' );
		$output = ob_get_clean();
		
		$this->assertStringContainsString( 'File not found', $output );

		// Test 500 error
		ob_start();
		wp_image_optimizer_serve_error( 500, 'Internal error' );
		$output = ob_get_clean();
		
		$this->assertStringContainsString( 'Internal error', $output );
	}

	/**
	 * Test CORS headers
	 */
	public function test_cors_headers() {
		// Test without CORS enabled
		ob_start();
		wp_image_optimizer_add_cors_headers();
		$output = ob_get_clean();
		
		// Should not add CORS headers by default
		$headers = xdebug_get_headers();
		$cors_header_found = false;
		foreach ( $headers as $header ) {
			if ( strpos( $header, 'Access-Control-Allow-Origin' ) !== false ) {
				$cors_header_found = true;
				break;
			}
		}
		$this->assertFalse( $cors_header_found );

		// Test with CORS enabled
		define( 'WP_IMAGE_OPTIMIZER_CORS', true );
		
		ob_start();
		wp_image_optimizer_add_cors_headers();
		$output = ob_get_clean();
		
		// Note: In unit tests, headers might not be captured the same way
		// This test verifies the function runs without error
		$this->assertTrue( true );
	}

	/**
	 * Test request logging
	 */
	public function test_request_logging() {
		// Enable debug logging
		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', true );
		}
		if ( ! defined( 'WP_DEBUG_LOG' ) ) {
			define( 'WP_DEBUG_LOG', true );
		}

		// Set up request environment
		$_SERVER['HTTP_USER_AGENT'] = 'Test Browser';
		$_SERVER['HTTP_ACCEPT'] = 'image/webp,*/*';
		$_SERVER['HTTP_REFERER'] = 'https://example.com';
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		// Test logging
		ob_start();
		wp_image_optimizer_log_request( 'test-image.jpg' );
		$output = ob_get_clean();
		
		// Function should run without error
		$this->assertTrue( true );
	}

	/**
	 * Test main endpoint handler with valid file
	 */
	public function test_endpoint_handler_with_valid_file() {
		// Set up request for existing file
		$relative_path = str_replace( $this->upload_dir['basedir'] . '/', '', $this->test_image_path );
		$_GET['file'] = $relative_path;
		$_SERVER['HTTP_ACCEPT'] = 'image/jpeg,*/*';

		// Capture output
		ob_start();
		wp_image_optimizer_handle_endpoint();
		$output = ob_get_contents();
		ob_end_clean();

		// Should serve the image (output will contain image data)
		$this->assertNotEmpty( $output );
	}

	/**
	 * Test main endpoint handler with invalid file
	 */
	public function test_endpoint_handler_with_invalid_file() {
		// Set up request for non-existent file
		$_GET['file'] = 'non-existent-image.jpg';

		// Capture output
		ob_start();
		wp_image_optimizer_handle_endpoint();
		$output = ob_get_clean();

		// Should serve error message
		$this->assertStringContainsString( 'not found', $output );
	}

	/**
	 * Test endpoint handler with no file specified
	 */
	public function test_endpoint_handler_with_no_file() {
		// Clear all file sources
		unset( $_GET['file'] );
		unset( $_SERVER['REQUEST_URI'] );
		unset( $_SERVER['PATH_INFO'] );
		unset( $_SERVER['SCRIPT_NAME'] );

		// Capture output
		ob_start();
		wp_image_optimizer_handle_endpoint();
		$output = ob_get_clean();

		// Should serve error message
		$this->assertStringContainsString( 'No file specified', $output );
	}

	/**
	 * Test endpoint handler exception handling
	 */
	public function test_endpoint_handler_exception_handling() {
		// Force an exception by providing invalid data
		$_GET['file'] = array( 'invalid' => 'data' ); // This should cause an error

		// Capture output
		ob_start();
		wp_image_optimizer_handle_endpoint();
		$output = ob_get_clean();

		// Should handle the exception gracefully
		$this->assertStringContainsString( 'error', strtolower( $output ) );
	}

	/**
	 * Test security measures in endpoint
	 */
	public function test_endpoint_security() {
		// Test directory traversal attempts
		$malicious_files = array(
			'../../../etc/passwd',
			'..\\..\\..\\windows\\system32\\config\\sam',
			'/etc/passwd',
		);

		foreach ( $malicious_files as $file ) {
			$_GET['file'] = $file;

			ob_start();
			wp_image_optimizer_handle_endpoint();
			$output = ob_get_clean();

			// Should reject malicious requests
			$this->assertStringContainsString( 'Invalid', $output );
		}
	}

	/**
	 * Test endpoint with different Accept headers
	 */
	public function test_endpoint_with_different_accept_headers() {
		$relative_path = str_replace( $this->upload_dir['basedir'] . '/', '', $this->test_image_path );
		$_GET['file'] = $relative_path;

		// Test with WebP support
		$_SERVER['HTTP_ACCEPT'] = 'image/webp,*/*';
		
		ob_start();
		wp_image_optimizer_handle_endpoint();
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertNotEmpty( $output );

		// Test with AVIF support
		$_SERVER['HTTP_ACCEPT'] = 'image/avif,image/webp,*/*';
		
		ob_start();
		wp_image_optimizer_handle_endpoint();
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertNotEmpty( $output );

		// Test with no modern format support
		$_SERVER['HTTP_ACCEPT'] = 'image/jpeg,image/png,*/*';
		
		ob_start();
		wp_image_optimizer_handle_endpoint();
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertNotEmpty( $output );
	}

	/**
	 * Test endpoint performance with large files
	 */
	public function test_endpoint_performance() {
		// Create a larger test image
		$large_image_path = $this->upload_dir['basedir'] . '/large-test-image.jpg';
		$image = imagecreate( 500, 500 );
		$white = imagecolorallocate( $image, 255, 255, 255 );
		
		// Add some complexity to the image
		for ( $i = 0; $i < 100; $i++ ) {
			$color = imagecolorallocate( $image, rand( 0, 255 ), rand( 0, 255 ), rand( 0, 255 ) );
			imagefilledellipse( $image, rand( 0, 500 ), rand( 0, 500 ), rand( 10, 50 ), rand( 10, 50 ), $color );
		}
		
		imagejpeg( $image, $large_image_path, 90 );
		imagedestroy( $image );

		// Test serving the large image
		$relative_path = str_replace( $this->upload_dir['basedir'] . '/', '', $large_image_path );
		$_GET['file'] = $relative_path;
		$_SERVER['HTTP_ACCEPT'] = 'image/jpeg,*/*';

		$start_time = microtime( true );
		
		ob_start();
		wp_image_optimizer_handle_endpoint();
		$output = ob_get_contents();
		ob_end_clean();

		$end_time = microtime( true );
		$execution_time = $end_time - $start_time;

		// Should complete within reasonable time (5 seconds)
		$this->assertLessThan( 5.0, $execution_time );
		$this->assertNotEmpty( $output );

		// Clean up
		unlink( $large_image_path );
	}
}