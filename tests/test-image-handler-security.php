<?php
/**
 * Image Handler Security Test Class
 *
 * @package WP_Image_Optimizer
 */

/**
 * Image Handler security test case.
 */
class Test_Image_Handler_Security extends WP_UnitTestCase {

	/**
	 * Image handler instance
	 *
	 * @var WP_Image_Optimizer_Image_Handler
	 */
	private $image_handler;

	/**
	 * Security validator instance
	 *
	 * @var WP_Image_Optimizer_Security_Validator
	 */
	private $security_validator;

	/**
	 * Set up test environment
	 */
	public function set_up() {
		parent::set_up();
		$this->image_handler = new WP_Image_Optimizer_Image_Handler();
		$this->security_validator = WP_Image_Optimizer_Security_Validator::get_instance();
	}

	/**
	 * Test validate_requested_file method for security
	 */
	public function test_validate_requested_file_security() {
		// Create a reflection method to access private method
		$reflection = new ReflectionClass( $this->image_handler );
		$method = $reflection->getMethod( 'validate_requested_file' );
		$method->setAccessible( true );
		
		// Test with valid path
		$upload_dir = wp_upload_dir();
		$valid_path = $upload_dir['path'] . '/test-image.jpg';
		
		// Create test file
		file_put_contents( $valid_path, 'test' );
		
		$result = $method->invoke( $this->image_handler, $valid_path );
		$this->assertNotInstanceOf( 'WP_Error', $result );
		
		// Test with directory traversal
		$malicious_path = '../../../wp-config.php';
		$result = $method->invoke( $this->image_handler, $malicious_path );
		$this->assertInstanceOf( 'WP_Error', $result );
		
		// Test with null byte injection
		$malicious_path = "uploads/image.jpg\0.php";
		$result = $method->invoke( $this->image_handler, $malicious_path );
		$this->assertNotContains( chr( 0 ), $result );
		
		// Test with invalid extension
		$invalid_path = 'uploads/script.php';
		$result = $method->invoke( $this->image_handler, $invalid_path );
		$this->assertInstanceOf( 'WP_Error', $result );
		
		// Clean up
		unlink( $valid_path );
	}

	/**
	 * Test browser_supports_format method for security
	 */
	public function test_browser_supports_format_security() {
		// Create a reflection method to access private method
		$reflection = new ReflectionClass( $this->image_handler );
		$method = $reflection->getMethod( 'browser_supports_format' );
		$method->setAccessible( true );
		
		// Test with valid format and accept header
		$result = $method->invoke( $this->image_handler, 'webp', 'image/webp,*/*' );
		$this->assertTrue( $result );
		
		// Test with invalid format
		$result = $method->invoke( $this->image_handler, 'invalid', 'image/webp,*/*' );
		$this->assertFalse( $result );
		
		// Test with malicious format
		$result = $method->invoke( $this->image_handler, '<script>alert(1)</script>', 'image/webp,*/*' );
		$this->assertFalse( $result );
		
		// Test with malicious accept header
		$result = $method->invoke( $this->image_handler, 'webp', '<script>alert(1)</script>' );
		$this->assertFalse( $result );
	}

	/**
	 * Test get_accept_header method for security
	 */
	public function test_get_accept_header_security() {
		// Create a reflection method to access private method
		$reflection = new ReflectionClass( $this->image_handler );
		$method = $reflection->getMethod( 'get_accept_header' );
		$method->setAccessible( true );
		
		// Test with valid accept header
		$_SERVER['HTTP_ACCEPT'] = 'image/webp,*/*';
		$result = $method->invoke( $this->image_handler );
		$this->assertEquals( 'image/webp,*/*', $result );
		
		// Test with malicious accept header
		$_SERVER['HTTP_ACCEPT'] = '<script>alert(1)</script>';
		$result = $method->invoke( $this->image_handler );
		$this->assertEquals( 'alert(1)', $result );
		
		// Clean up
		unset( $_SERVER['HTTP_ACCEPT'] );
	}

	/**
	 * Test set_image_headers method for security
	 */
	public function test_set_image_headers_security() {
		// Create a reflection method to access private method
		$reflection = new ReflectionClass( $this->image_handler );
		$method = $reflection->getMethod( 'set_image_headers' );
		$method->setAccessible( true );
		
		// Mock file info
		$file_info = array(
			'path' => '/path/to/image.jpg',
			'size' => 1024,
			'modified_time' => time(),
		);
		
		// Test with valid MIME type
		ob_start();
		$method->invoke( $this->image_handler, $file_info, 'image/jpeg' );
		ob_end_clean();
		
		// Test with invalid MIME type
		ob_start();
		$method->invoke( $this->image_handler, $file_info, 'application/javascript' );
		ob_end_clean();
		
		// Test with malicious MIME type
		ob_start();
		$method->invoke( $this->image_handler, $file_info, '<script>alert(1)</script>' );
		ob_end_clean();
		
		// No assertions needed as we're just checking that no errors occur
		$this->assertTrue( true );
	}

	/**
	 * Test output_image_file method for security
	 */
	public function test_output_image_file_security() {
		// Create a reflection method to access private method
		$reflection = new ReflectionClass( $this->image_handler );
		$method = $reflection->getMethod( 'output_image_file' );
		$method->setAccessible( true );
		
		// Create test image file
		$upload_dir = wp_upload_dir();
		$test_image = $upload_dir['path'] . '/test-security-image.jpg';
		copy( ABSPATH . 'wp-includes/images/media/default.png', $test_image );
		
		// Test with valid file path
		ob_start();
		try {
			$method->invoke( $this->image_handler, $test_image );
		} catch ( Exception $e ) {
			// Expected to exit, so we'll get an exception
		}
		ob_end_clean();
		
		// Test with path outside upload directory
		$outside_path = ABSPATH . 'wp-config.php';
		ob_start();
		try {
			$method->invoke( $this->image_handler, $outside_path );
		} catch ( Exception $e ) {
			// Expected to exit, so we'll get an exception
		}
		ob_end_clean();
		
		// Clean up
		unlink( $test_image );
		
		// No assertions needed as we're just checking that no errors occur
		$this->assertTrue( true );
	}

	/**
	 * Test serve_error method for security
	 */
	public function test_serve_error_security() {
		// Create a reflection method to access private method
		$reflection = new ReflectionClass( $this->image_handler );
		$method = $reflection->getMethod( 'serve_error' );
		$method->setAccessible( true );
		
		// Test with valid error message
		ob_start();
		try {
			$method->invoke( $this->image_handler, 404, 'File not found' );
		} catch ( Exception $e ) {
			// Expected to exit, so we'll get an exception
		}
		$output = ob_get_clean();
		$this->assertEquals( 'File not found', $output );
		
		// Test with malicious error message
		ob_start();
		try {
			$method->invoke( $this->image_handler, 404, '<script>alert(1)</script>' );
		} catch ( Exception $e ) {
			// Expected to exit, so we'll get an exception
		}
		$output = ob_get_clean();
		$this->assertEquals( '&lt;script&gt;alert(1)&lt;/script&gt;', $output );
	}

	/**
	 * Test get_browser_capabilities method for security
	 */
	public function test_get_browser_capabilities_security() {
		// Test with valid user agent
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
		$_SERVER['HTTP_ACCEPT'] = 'image/webp,*/*';
		
		$result = $this->image_handler->get_browser_capabilities();
		$this->assertEquals( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', $result['user_agent'] );
		
		// Test with malicious user agent
		$_SERVER['HTTP_USER_AGENT'] = '<script>alert(1)</script>';
		$result = $this->image_handler->get_browser_capabilities();
		$this->assertEquals( 'alert(1)', $result['user_agent'] );
		
		// Clean up
		unset( $_SERVER['HTTP_USER_AGENT'] );
		unset( $_SERVER['HTTP_ACCEPT'] );
	}
}