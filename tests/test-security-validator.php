<?php
/**
 * Security Validator Test Class
 *
 * @package WP_Image_Optimizer
 */

/**
 * Security Validator test case.
 */
class Test_Security_Validator extends WP_UnitTestCase {

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
		$this->security_validator = WP_Image_Optimizer_Security_Validator::get_instance();
	}

	/**
	 * Test sanitize_text_input method
	 */
	public function test_sanitize_text_input() {
		// Test basic sanitization
		$input = 'Test <script>alert("XSS")</script> string';
		$expected = 'Test string';
		$this->assertEquals( $expected, $this->security_validator->sanitize_text_input( $input ) );

		// Test with allowed HTML
		$input = '<p>Test <strong>string</strong> with <em>allowed</em> HTML</p>';
		$allowed_html = array(
			'p' => array(),
			'strong' => array(),
			'em' => array(),
		);
		$this->assertEquals( $input, $this->security_validator->sanitize_text_input( $input, true, $allowed_html ) );

		// Test with non-string input
		$input = array( 'test' => 'value' );
		$this->assertEquals( '', $this->security_validator->sanitize_text_input( $input ) );
	}

	/**
	 * Test sanitize_integer method
	 */
	public function test_sanitize_integer() {
		// Test valid integer
		$this->assertEquals( 42, $this->security_validator->sanitize_integer( '42' ) );

		// Test float input (should truncate)
		$this->assertEquals( 42, $this->security_validator->sanitize_integer( 42.75 ) );

		// Test min/max constraints
		$this->assertEquals( 10, $this->security_validator->sanitize_integer( 5, 10, 100 ) );
		$this->assertEquals( 100, $this->security_validator->sanitize_integer( 200, 10, 100 ) );

		// Test invalid input
		$this->assertEquals( 0, $this->security_validator->sanitize_integer( 'not-a-number' ) );
		$this->assertEquals( 42, $this->security_validator->sanitize_integer( 'not-a-number', 0, 100, 42 ) );
	}

	/**
	 * Test sanitize_float method
	 */
	public function test_sanitize_float() {
		// Test valid float
		$this->assertEquals( 42.75, $this->security_validator->sanitize_float( '42.75' ) );

		// Test min/max constraints
		$this->assertEquals( 10.5, $this->security_validator->sanitize_float( 5.25, 10.5, 100.5 ) );
		$this->assertEquals( 100.5, $this->security_validator->sanitize_float( 200.75, 10.5, 100.5 ) );

		// Test invalid input
		$this->assertEquals( 0.0, $this->security_validator->sanitize_float( 'not-a-number' ) );
		$this->assertEquals( 42.5, $this->security_validator->sanitize_float( 'not-a-number', 0.0, 100.0, 42.5 ) );
	}

	/**
	 * Test sanitize_boolean method
	 */
	public function test_sanitize_boolean() {
		// Test boolean input
		$this->assertTrue( $this->security_validator->sanitize_boolean( true ) );
		$this->assertFalse( $this->security_validator->sanitize_boolean( false ) );

		// Test string input
		$this->assertTrue( $this->security_validator->sanitize_boolean( 'true' ) );
		$this->assertTrue( $this->security_validator->sanitize_boolean( 'yes' ) );
		$this->assertTrue( $this->security_validator->sanitize_boolean( '1' ) );
		$this->assertTrue( $this->security_validator->sanitize_boolean( 'on' ) );

		$this->assertFalse( $this->security_validator->sanitize_boolean( 'false' ) );
		$this->assertFalse( $this->security_validator->sanitize_boolean( 'no' ) );
		$this->assertFalse( $this->security_validator->sanitize_boolean( '0' ) );
		$this->assertFalse( $this->security_validator->sanitize_boolean( 'off' ) );

		// Test numeric input
		$this->assertTrue( $this->security_validator->sanitize_boolean( 1 ) );
		$this->assertFalse( $this->security_validator->sanitize_boolean( 0 ) );

		// Test default value
		$this->assertTrue( $this->security_validator->sanitize_boolean( 'invalid', true ) );
		$this->assertFalse( $this->security_validator->sanitize_boolean( 'invalid' ) );
	}

	/**
	 * Test sanitize_array method
	 */
	public function test_sanitize_array() {
		// Test basic array sanitization
		$input = array(
			'key1' => 'value1',
			'key2' => '<script>alert("XSS")</script>',
			'key3' => array(
				'nested' => '<script>alert("Nested XSS")</script>',
			),
		);

		$expected = array(
			'key1' => 'value1',
			'key2' => 'alert("XSS")',
			'key3' => array(
				'nested' => 'alert("Nested XSS")',
			),
		);

		$this->assertEquals( $expected, $this->security_validator->sanitize_array( $input ) );

		// Test with allowed keys
		$allowed_keys = array( 'key1', 'key3' );
		$expected_filtered = array(
			'key1' => 'value1',
			'key3' => array(
				'nested' => 'alert("Nested XSS")',
			),
		);

		$this->assertEquals( $expected_filtered, $this->security_validator->sanitize_array( $input, 'sanitize_text_field', $allowed_keys ) );

		// Test with non-array input
		$this->assertEquals( array(), $this->security_validator->sanitize_array( 'not-an-array' ) );
	}

	/**
	 * Test sanitize_url method
	 */
	public function test_sanitize_url() {
		// Test valid URL
		$this->assertEquals( 'https://example.com', $this->security_validator->sanitize_url( 'https://example.com' ) );

		// Test URL with query parameters
		$this->assertEquals( 
			'https://example.com/path?query=value&param=test', 
			$this->security_validator->sanitize_url( 'https://example.com/path?query=value&param=test' ) 
		);

		// Test URL with disallowed protocol
		$this->assertEquals( 
			'', 
			$this->security_validator->sanitize_url( 'javascript:alert(1)' ) 
		);

		// Test URL with custom allowed protocols
		$this->assertEquals( 
			'ftp://example.com', 
			$this->security_validator->sanitize_url( 'ftp://example.com', array( 'ftp', 'http', 'https' ) ) 
		);

		// Test invalid URL with default value
		$this->assertEquals( 
			'https://default.com', 
			$this->security_validator->sanitize_url( 'javascript:alert(1)', array(), 'https://default.com' ) 
		);
	}

	/**
	 * Test sanitize_file_path method
	 */
	public function test_sanitize_file_path() {
		// Test valid path
		$path = '/var/www/uploads/image.jpg';
		$this->assertEquals( $path, $this->security_validator->sanitize_file_path( $path ) );

		// Test path with directory traversal
		$path = '/var/www/uploads/../../../etc/passwd';
		$this->assertInstanceOf( 'WP_Error', $this->security_validator->sanitize_file_path( $path ) );

		// Test path with null byte
		$path = "/var/www/uploads/image.jpg\0.php";
		$this->assertEquals( '/var/www/uploads/image.jpg.php', $this->security_validator->sanitize_file_path( $path ) );

		// Test path with base directory restriction
		$base_dir = '/var/www/uploads';
		$path = '/var/www/uploads/subfolder/image.jpg';
		$this->assertEquals( $path, $this->security_validator->sanitize_file_path( $path, $base_dir ) );

		// Test path outside base directory
		$base_dir = '/var/www/uploads';
		$path = '/var/www/public_html/image.jpg';
		$this->assertInstanceOf( 'WP_Error', $this->security_validator->sanitize_file_path( $path, $base_dir ) );

		// Test path with allowed extensions
		$path = '/var/www/uploads/image.jpg';
		$allowed_extensions = array( 'jpg', 'png', 'gif' );
		$this->assertEquals( $path, $this->security_validator->sanitize_file_path( $path, '', $allowed_extensions ) );

		// Test path with disallowed extension
		$path = '/var/www/uploads/script.php';
		$allowed_extensions = array( 'jpg', 'png', 'gif' );
		$this->assertInstanceOf( 'WP_Error', $this->security_validator->sanitize_file_path( $path, '', $allowed_extensions ) );
	}

	/**
	 * Test rate limiting functionality
	 */
	public function test_rate_limiting() {
		// Test within rate limit
		$result = $this->security_validator->check_rate_limit( 'test_ip', 'test_action', 5, 60 );
		$this->assertTrue( $result );

		// Test exceeding rate limit
		for ( $i = 0; $i < 5; $i++ ) {
			$this->security_validator->check_rate_limit( 'test_ip', 'test_action', 5, 60 );
		}

		$result = $this->security_validator->check_rate_limit( 'test_ip', 'test_action', 5, 60 );
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'rate_limit_exceeded', $result->get_error_code() );
	}
}