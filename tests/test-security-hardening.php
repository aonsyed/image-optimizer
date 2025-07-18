<?php
/**
 * Tests for security hardening
 *
 * @package WP_Image_Optimizer
 */

class Test_Security_Hardening extends WP_UnitTestCase {

	/**
	 * Test file path validation
	 */
	public function test_file_path_validation() {
		// Create security validator
		$security_validator = new WP_Image_Optimizer_Security_Validator();
		
		// Valid paths
		$valid_paths = array(
			WP_CONTENT_DIR . '/uploads/test.jpg',
			WP_CONTENT_DIR . '/uploads/2023/01/test.jpg',
			get_template_directory() . '/images/test.jpg',
			plugin_dir_path( WP_IMAGE_OPTIMIZER_PLUGIN_FILE ) . 'assets/test.jpg',
		);
		
		foreach ( $valid_paths as $path ) {
			$this->assertTrue( $security_validator->validate_file_path( $path ), "Path should be valid: $path" );
		}
		
		// Invalid paths
		$invalid_paths = array(
			'/etc/passwd',
			'../../../wp-config.php',
			'http://example.com/test.jpg',
			'ftp://example.com/test.jpg',
			'php://filter/convert.base64-encode/resource=../wp-config.php',
		);
		
		foreach ( $invalid_paths as $path ) {
			$this->assertFalse( $security_validator->validate_file_path( $path ), "Path should be invalid: $path" );
		}
	}

	/**
	 * Test MIME type validation
	 */
	public function test_mime_type_validation() {
		// Create security validator
		$security_validator = new WP_Image_Optimizer_Security_Validator();
		
		// Valid MIME types
		$valid_mime_types = array(
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'image/avif',
		);
		
		foreach ( $valid_mime_types as $mime_type ) {
			$this->assertTrue( $security_validator->validate_mime_type( $mime_type ), "MIME type should be valid: $mime_type" );
		}
		
		// Invalid MIME types
		$invalid_mime_types = array(
			'application/php',
			'application/javascript',
			'text/html',
			'application/x-httpd-php',
			'application/octet-stream',
		);
		
		foreach ( $invalid_mime_types as $mime_type ) {
			$this->assertFalse( $security_validator->validate_mime_type( $mime_type ), "MIME type should be invalid: $mime_type" );
		}
	}

	/**
	 * Test file size validation
	 */
	public function test_file_size_validation() {
		// Create security validator
		$security_validator = new WP_Image_Optimizer_Security_Validator();
		
		// Create test file
		$test_file = wp_tempnam( 'test-security' );
		
		// Write 1KB of data
		file_put_contents( $test_file, str_repeat( 'a', 1024 ) );
		
		// Test with default max size (10MB)
		$this->assertTrue( $security_validator->validate_file_size( $test_file ) );
		
		// Test with custom max size (512 bytes)
		$this->assertFalse( $security_validator->validate_file_size( $test_file, 512 ) );
		
		// Test with custom max size (2KB)
		$this->assertTrue( $security_validator->validate_file_size( $test_file, 2048 ) );
		
		// Clean up
		unlink( $test_file );
	}

	/**
	 * Test nonce verification
	 */
	public function test_nonce_verification() {
		// Create security validator
		$security_validator = new WP_Image_Optimizer_Security_Validator();
		
		// Create valid nonce
		$action = 'test_nonce_action';
		$nonce = wp_create_nonce( $action );
		
		// Test with valid nonce
		$this->assertTrue( $security_validator->verify_nonce( $nonce, $action ) );
		
		// Test with invalid nonce
		$this->assertFalse( $security_validator->verify_nonce( 'invalid_nonce', $action ) );
		
		// Test with empty nonce
		$this->assertFalse( $security_validator->verify_nonce( '', $action ) );
		
		// Test with empty action
		$this->assertFalse( $security_validator->verify_nonce( $nonce, '' ) );
	}

	/**
	 * Test capability checking
	 */
	public function test_capability_checking() {
		// Create security validator
		$security_validator = new WP_Image_Optimizer_Security_Validator();
		
		// Create test user with no capabilities
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );
		
		// Test with manage_options capability
		$this->assertFalse( $security_validator->current_user_can( 'manage_options' ) );
		
		// Test with upload_files capability
		$this->assertFalse( $security_validator->current_user_can( 'upload_files' ) );
		
		// Create test user with admin capabilities
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		
		// Test with manage_options capability
		$this->assertTrue( $security_validator->current_user_can( 'manage_options' ) );
		
		// Test with upload_files capability
		$this->assertTrue( $security_validator->current_user_can( 'upload_files' ) );
		
		// Reset current user
		wp_set_current_user( 0 );
	}

	/**
	 * Test input sanitization
	 */
	public function test_input_sanitization() {
		// Create security validator
		$security_validator = new WP_Image_Optimizer_Security_Validator();
		
		// Test sanitize_text_field
		$input = '<script>alert("XSS")</script> Test';
		$expected = 'Test';
		$this->assertEquals( $expected, $security_validator->sanitize_text( $input ) );
		
		// Test sanitize_textarea_field
		$input = "Line 1\n<script>alert('XSS')</script>\nLine 3";
		$expected = "Line 1\n\nLine 3";
		$this->assertEquals( $expected, $security_validator->sanitize_textarea( $input ) );
		
		// Test sanitize_key
		$input = 'Test Key!@#$%^&*()';
		$expected = 'testkey';
		$this->assertEquals( $expected, $security_validator->sanitize_key( $input ) );
		
		// Test sanitize_file_name
		$input = '../../../etc/passwd';
		$expected = 'etcpasswd';
		$this->assertEquals( $expected, $security_validator->sanitize_file_name( $input ) );
	}

	/**
	 * Test rate limiting
	 */
	public function test_rate_limiting() {
		// Create security validator
		$security_validator = new WP_Image_Optimizer_Security_Validator();
		
		// Clear any existing rate limit data
		delete_transient( 'wp_image_optimizer_rate_limit_test' );
		
		// Test initial rate limit
		$this->assertTrue( $security_validator->check_rate_limit( 'test', 5, 60 ) );
		
		// Test multiple requests within limit
		for ( $i = 0; $i < 4; $i++ ) {
			$this->assertTrue( $security_validator->check_rate_limit( 'test', 5, 60 ) );
		}
		
		// Test exceeding rate limit
		$this->assertFalse( $security_validator->check_rate_limit( 'test', 5, 60 ) );
		
		// Clean up
		delete_transient( 'wp_image_optimizer_rate_limit_test' );
	}

	/**
	 * Test file type detection
	 */
	public function test_file_type_detection() {
		// Create security validator
		$security_validator = new WP_Image_Optimizer_Security_Validator();
		
		// Create test files
		$upload_dir = wp_upload_dir();
		$jpeg_file = $upload_dir['basedir'] . '/test-security.jpg';
		$php_file = $upload_dir['basedir'] . '/test-security.php';
		
		// Create JPEG file with JPEG header
		file_put_contents( $jpeg_file, "\xFF\xD8\xFF" . str_repeat( 'a', 1024 ) );
		
		// Create PHP file with PHP header
		file_put_contents( $php_file, "<?php\n" . str_repeat( 'a', 1024 ) );
		
		// Test JPEG file
		$this->assertTrue( $security_validator->validate_file_type( $jpeg_file, 'image/jpeg' ) );
		
		// Test PHP file
		$this->assertFalse( $security_validator->validate_file_type( $php_file, 'image/jpeg' ) );
		
		// Clean up
		unlink( $jpeg_file );
		unlink( $php_file );
	}

	/**
	 * Test directory traversal prevention
	 */
	public function test_directory_traversal_prevention() {
		// Create security validator
		$security_validator = new WP_Image_Optimizer_Security_Validator();
		
		// Test with directory traversal attempts
		$base_dir = WP_CONTENT_DIR . '/uploads/';
		$traversal_attempts = array(
			'../../../wp-config.php',
			'..\\..\\..\\wp-config.php',
			'test/../../../wp-config.php',
			'./test/../../wp-config.php',
			'test/./../../wp-config.php',
		);
		
		foreach ( $traversal_attempts as $attempt ) {
			$path = $base_dir . $attempt;
			$this->assertFalse( $security_validator->validate_file_path( $path ), "Path should be invalid: $path" );
		}
	}
}