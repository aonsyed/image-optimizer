<?php
/**
 * Tests for Image Handler class
 *
 * @package WP_Image_Optimizer
 */

class Test_Image_Handler extends WP_UnitTestCase {

	/**
	 * Image handler instance
	 *
	 * @var WP_Image_Optimizer_Image_Handler
	 */
	private $image_handler;

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

		// Load required classes
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-settings-manager.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-file-handler.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-image-converter.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-image-handler.php';

		$this->image_handler = new WP_Image_Optimizer_Image_Handler();
		$this->upload_dir = wp_upload_dir();

		// Create test image
		$this->create_test_image();
	}

	/**
	 * Clean up test environment
	 */
	public function tearDown(): void {
		// Clean up test files
		if ( $this->test_image_path && file_exists( $this->test_image_path ) ) {
			unlink( $this->test_image_path );
		}

		// Clean up any converted files
		$formats = array( 'webp', 'avif' );
		foreach ( $formats as $format ) {
			$converted_path = str_replace( '.jpg', '.' . $format, $this->test_image_path );
			if ( file_exists( $converted_path ) ) {
				unlink( $converted_path );
			}
		}

		parent::tearDown();
	}

	/**
	 * Create test image file
	 */
	private function create_test_image() {
		$this->test_image_path = $this->upload_dir['basedir'] . '/test-image.jpg';
		
		// Create a simple test image
		$image = imagecreate( 100, 100 );
		$white = imagecolorallocate( $image, 255, 255, 255 );
		$black = imagecolorallocate( $image, 0, 0, 0 );
		imagestring( $image, 5, 30, 40, 'TEST', $black );
		
		imagejpeg( $image, $this->test_image_path, 90 );
		imagedestroy( $image );
	}

	/**
	 * Test browser capability detection
	 */
	public function test_browser_capabilities_detection() {
		// Test WebP support detection
		$_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8';
		$capabilities = $this->image_handler->get_browser_capabilities();
		
		$this->assertTrue( $capabilities['supports_webp'] );
		$this->assertFalse( $capabilities['supports_avif'] );
		$this->assertStringContainsString( 'image/webp', $capabilities['accept_header'] );

		// Test AVIF support detection
		$_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8';
		$capabilities = $this->image_handler->get_browser_capabilities();
		
		$this->assertTrue( $capabilities['supports_webp'] );
		$this->assertTrue( $capabilities['supports_avif'] );
		$this->assertStringContainsString( 'image/avif', $capabilities['accept_header'] );

		// Test no modern format support
		$_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/jpeg,image/png,*/*;q=0.8';
		$capabilities = $this->image_handler->get_browser_capabilities();
		
		$this->assertFalse( $capabilities['supports_webp'] );
		$this->assertFalse( $capabilities['supports_avif'] );
	}

	/**
	 * Test format negotiation
	 */
	public function test_format_negotiation() {
		// Create relative path for testing
		$relative_path = str_replace( $this->upload_dir['basedir'] . '/', '', $this->test_image_path );
		
		// Test with AVIF support
		$_SERVER['HTTP_ACCEPT'] = 'image/avif,image/webp,*/*';
		$results = $this->image_handler->test_format_negotiation( $relative_path );
		
		$this->assertTrue( $results['original_exists'] );
		$this->assertTrue( $results['browser_capabilities']['supports_avif'] );
		$this->assertTrue( $results['browser_capabilities']['supports_webp'] );
		$this->assertEquals( 'avif', $results['best_format'] );

		// Test with WebP support only
		$_SERVER['HTTP_ACCEPT'] = 'image/webp,*/*';
		$results = $this->image_handler->test_format_negotiation( $relative_path );
		
		$this->assertFalse( $results['browser_capabilities']['supports_avif'] );
		$this->assertTrue( $results['browser_capabilities']['supports_webp'] );
		$this->assertEquals( 'webp', $results['best_format'] );

		// Test with no modern format support
		$_SERVER['HTTP_ACCEPT'] = 'image/jpeg,image/png,*/*';
		$results = $this->image_handler->test_format_negotiation( $relative_path );
		
		$this->assertFalse( $results['browser_capabilities']['supports_avif'] );
		$this->assertFalse( $results['browser_capabilities']['supports_webp'] );
		$this->assertNull( $results['best_format'] );
	}

	/**
	 * Test file path validation
	 */
	public function test_file_path_validation() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->image_handler );
		$validate_method = $reflection->getMethod( 'validate_requested_file' );
		$validate_method->setAccessible( true );

		// Test valid file path
		$relative_path = str_replace( $this->upload_dir['basedir'] . '/', '', $this->test_image_path );
		$result = $validate_method->invoke( $this->image_handler, $relative_path );
		$this->assertEquals( $this->test_image_path, $result );

		// Test absolute path
		$result = $validate_method->invoke( $this->image_handler, $this->test_image_path );
		$this->assertEquals( $this->test_image_path, $result );

		// Test directory traversal attempt
		$result = $validate_method->invoke( $this->image_handler, '../../../etc/passwd' );
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid_path', $result->get_error_code() );

		// Test invalid file extension
		$result = $validate_method->invoke( $this->image_handler, 'test.txt' );
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid_extension', $result->get_error_code() );

		// Test empty file path
		$result = $validate_method->invoke( $this->image_handler, '' );
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'empty_file', $result->get_error_code() );
	}

	/**
	 * Test Accept header parsing
	 */
	public function test_accept_header_parsing() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->image_handler );
		$get_accept_method = $reflection->getMethod( 'get_accept_header' );
		$get_accept_method->setAccessible( true );

		// Test HTTP_ACCEPT server variable
		$_SERVER['HTTP_ACCEPT'] = 'image/avif,image/webp,*/*';
		$result = $get_accept_method->invoke( $this->image_handler );
		$this->assertEquals( 'image/avif,image/webp,*/*', $result );

		// Test missing Accept header
		unset( $_SERVER['HTTP_ACCEPT'] );
		$result = $get_accept_method->invoke( $this->image_handler );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test browser format support detection
	 */
	public function test_browser_format_support() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->image_handler );
		$supports_method = $reflection->getMethod( 'browser_supports_format' );
		$supports_method->setAccessible( true );

		// Test WebP support
		$accept_header = 'text/html,image/webp,*/*';
		$result = $supports_method->invoke( $this->image_handler, 'webp', $accept_header );
		$this->assertTrue( $result );

		// Test AVIF support
		$accept_header = 'text/html,image/avif,image/webp,*/*';
		$result = $supports_method->invoke( $this->image_handler, 'avif', $accept_header );
		$this->assertTrue( $result );

		// Test no support
		$accept_header = 'text/html,image/jpeg,image/png,*/*';
		$result = $supports_method->invoke( $this->image_handler, 'webp', $accept_header );
		$this->assertFalse( $result );

		// Test invalid format
		$accept_header = 'text/html,image/webp,*/*';
		$result = $supports_method->invoke( $this->image_handler, 'invalid', $accept_header );
		$this->assertFalse( $result );
	}

	/**
	 * Test cache duration configuration
	 */
	public function test_cache_duration() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->image_handler );
		$cache_method = $reflection->getMethod( 'get_cache_duration' );
		$cache_method->setAccessible( true );

		// Test default cache duration
		$result = $cache_method->invoke( $this->image_handler );
		$this->assertEquals( YEAR_IN_SECONDS, $result );

		// Test filter modification
		add_filter( 'wp_image_optimizer_cache_duration', function() {
			return DAY_IN_SECONDS;
		} );

		$result = $cache_method->invoke( $this->image_handler );
		$this->assertEquals( DAY_IN_SECONDS, $result );

		// Clean up filter
		remove_all_filters( 'wp_image_optimizer_cache_duration' );
	}

	/**
	 * Test error handling
	 */
	public function test_error_handling() {
		// Test handling non-existent file
		ob_start();
		$this->image_handler->handle_image_request( 'non-existent-file.jpg' );
		$output = ob_get_clean();
		
		// Should output error message
		$this->assertStringContainsString( 'not found', $output );

		// Test handling invalid file path
		ob_start();
		$this->image_handler->handle_image_request( '../../../etc/passwd' );
		$output = ob_get_clean();
		
		// Should output error message
		$this->assertStringContainsString( 'Invalid', $output );
	}

	/**
	 * Test client caching headers
	 */
	public function test_client_caching() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->image_handler );
		$cached_method = $reflection->getMethod( 'is_client_cached' );
		$cached_method->setAccessible( true );

		$file_info = array(
			'path' => $this->test_image_path,
			'modified_time' => time() - 3600, // 1 hour ago
			'size' => 1000,
		);

		// Test If-Modified-Since header (file not modified)
		$_SERVER['HTTP_IF_MODIFIED_SINCE'] = gmdate( 'D, d M Y H:i:s', time() ) . ' GMT';
		$result = $cached_method->invoke( $this->image_handler, $file_info );
		$this->assertTrue( $result );

		// Test If-Modified-Since header (file modified)
		$_SERVER['HTTP_IF_MODIFIED_SINCE'] = gmdate( 'D, d M Y H:i:s', time() - 7200 ) . ' GMT';
		$result = $cached_method->invoke( $this->image_handler, $file_info );
		$this->assertFalse( $result );

		// Test ETag matching
		unset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
		$etag = md5( $file_info['path'] . $file_info['modified_time'] . $file_info['size'] );
		$_SERVER['HTTP_IF_NONE_MATCH'] = '"' . $etag . '"';
		$result = $cached_method->invoke( $this->image_handler, $file_info );
		$this->assertTrue( $result );

		// Test ETag not matching
		$_SERVER['HTTP_IF_NONE_MATCH'] = '"different-etag"';
		$result = $cached_method->invoke( $this->image_handler, $file_info );
		$this->assertFalse( $result );

		// Clean up
		unset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
		unset( $_SERVER['HTTP_IF_NONE_MATCH'] );
	}

	/**
	 * Test settings integration
	 */
	public function test_settings_integration() {
		// Disable WebP format
		WP_Image_Optimizer_Settings_Manager::update_settings( array(
			'formats' => array(
				'webp' => array( 'enabled' => false ),
				'avif' => array( 'enabled' => true ),
			),
		) );

		// Test format negotiation with WebP disabled
		$_SERVER['HTTP_ACCEPT'] = 'image/avif,image/webp,*/*';
		$relative_path = str_replace( $this->upload_dir['basedir'] . '/', '', $this->test_image_path );
		$results = $this->image_handler->test_format_negotiation( $relative_path );
		
		// Should prefer AVIF even though WebP is in Accept header
		$this->assertEquals( 'avif', $results['best_format'] );

		// Disable all modern formats
		WP_Image_Optimizer_Settings_Manager::update_settings( array(
			'formats' => array(
				'webp' => array( 'enabled' => false ),
				'avif' => array( 'enabled' => false ),
			),
		) );

		$results = $this->image_handler->test_format_negotiation( $relative_path );
		$this->assertNull( $results['best_format'] );

		// Reset settings
		WP_Image_Optimizer_Settings_Manager::reset_settings();
	}

	/**
	 * Test security measures
	 */
	public function test_security_measures() {
		// Test path traversal prevention
		$malicious_paths = array(
			'../../../etc/passwd',
			'..\\..\\..\\windows\\system32\\config\\sam',
			'/etc/passwd',
			'C:\\windows\\system32\\config\\sam',
			'test.jpg?../../../etc/passwd',
			'test.jpg#../../../etc/passwd',
		);

		foreach ( $malicious_paths as $path ) {
			ob_start();
			$this->image_handler->handle_image_request( $path );
			$output = ob_get_clean();
			
			// Should not serve the malicious file
			$this->assertStringContainsString( 'Invalid', $output );
		}
	}

	/**
	 * Test MIME type handling
	 */
	public function test_mime_type_handling() {
		// Create test files with different extensions
		$test_files = array(
			'test.png' => 'image/png',
			'test.gif' => 'image/gif',
			'test.webp' => 'image/webp',
		);

		foreach ( $test_files as $filename => $expected_mime ) {
			$test_path = $this->upload_dir['basedir'] . '/' . $filename;
			
			// Create a simple test file
			file_put_contents( $test_path, 'test content' );
			
			// Use reflection to test MIME type detection
			$reflection = new ReflectionClass( $this->image_handler );
			$serve_method = $reflection->getMethod( 'serve_original_image' );
			$serve_method->setAccessible( true );

			// Clean up
			unlink( $test_path );
		}
	}
}