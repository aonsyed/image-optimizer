<?php
/**
 * Tests for WordPress Hooks Integration
 *
 * @package WP_Image_Optimizer
 */

/**
 * Test WordPress hooks integration functionality
 */
class Test_WP_Image_Optimizer_Hooks_Integration extends WP_UnitTestCase {

	/**
	 * Hooks integration instance
	 *
	 * @var WP_Image_Optimizer_Hooks_Integration
	 */
	private $hooks_integration;

	/**
	 * Test image file path
	 *
	 * @var string
	 */
	private $test_image_path;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Load required classes
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-settings-manager.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-file-handler.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/interfaces/interface-converter.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/converters/class-converter-factory.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-image-converter.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-hooks-integration.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'tests/mocks/class-mock-converter.php';

		// Create hooks integration instance
		$this->hooks_integration = new WP_Image_Optimizer_Hooks_Integration();

		// Create test image
		$this->test_image_path = $this->create_test_image();

		// Set up default plugin options
		$default_options = array(
			'version' => WP_IMAGE_OPTIMIZER_VERSION,
			'settings' => array(
				'enabled' => true,
				'formats' => array(
					'webp' => array( 'enabled' => true, 'quality' => 80 ),
					'avif' => array( 'enabled' => true, 'quality' => 75 ),
				),
				'conversion_mode' => 'auto',
				'preserve_originals' => true,
				'max_file_size' => 10485760,
				'allowed_mime_types' => array( 'image/jpeg', 'image/png', 'image/gif' ),
				'server_config_type' => 'nginx',
			),
			'stats' => array(
				'total_conversions' => 0,
				'space_saved' => 0,
				'last_batch_run' => null,
			),
		);
		update_option( 'wp_image_optimizer_settings', $default_options );
	}

	/**
	 * Clean up test environment
	 */
	public function tearDown(): void {
		// Remove hooks
		if ( $this->hooks_integration ) {
			$this->hooks_integration->remove_hooks();
		}

		// Clean up test files
		if ( $this->test_image_path && file_exists( $this->test_image_path ) ) {
			unlink( $this->test_image_path );
		}

		// Clean up options
		delete_option( 'wp_image_optimizer_settings' );

		parent::tearDown();
	}

	/**
	 * Test hooks initialization
	 */
	public function test_hooks_initialization() {
		$this->assertTrue( $this->hooks_integration->are_hooks_initialized() );
		
		// Test individual hooks
		$this->assertTrue( has_filter( 'wp_handle_upload' ) );
		$this->assertTrue( has_filter( 'wp_generate_attachment_metadata' ) );
		$this->assertTrue( has_filter( 'wp_get_attachment_image_src' ) );
		$this->assertTrue( has_action( 'template_redirect' ) );
		$this->assertTrue( has_action( 'delete_attachment' ) );
	}

	/**
	 * Test upload conversion hook
	 */
	public function test_handle_upload_conversion() {
		$upload_data = array(
			'file' => $this->test_image_path,
			'url' => 'http://example.com/test.jpg',
			'type' => 'image/jpeg',
		);

		$result = $this->hooks_integration->handle_upload_conversion( $upload_data );

		$this->assertIsArray( $result );
		$this->assertEquals( $this->test_image_path, $result['file'] );
		$this->assertEquals( 'image/jpeg', $result['type'] );
	}

	/**
	 * Test upload conversion with non-image file
	 */
	public function test_handle_upload_conversion_non_image() {
		$upload_data = array(
			'file' => '/path/to/document.pdf',
			'url' => 'http://example.com/document.pdf',
			'type' => 'application/pdf',
		);

		$result = $this->hooks_integration->handle_upload_conversion( $upload_data );

		$this->assertIsArray( $result );
		$this->assertEquals( $upload_data, $result );
	}

	/**
	 * Test upload conversion when disabled
	 */
	public function test_handle_upload_conversion_disabled() {
		// Disable conversion
		$options = get_option( 'wp_image_optimizer_settings' );
		$options['settings']['enabled'] = false;
		update_option( 'wp_image_optimizer_settings', $options );

		$upload_data = array(
			'file' => $this->test_image_path,
			'url' => 'http://example.com/test.jpg',
			'type' => 'image/jpeg',
		);

		$result = $this->hooks_integration->handle_upload_conversion( $upload_data );

		$this->assertIsArray( $result );
		$this->assertEquals( $upload_data, $result );
	}

	/**
	 * Test attachment metadata generation
	 */
	public function test_generate_attachment_metadata() {
		// Create attachment
		$attachment_id = $this->factory->attachment->create_upload_object( $this->test_image_path );

		$metadata = array(
			'width' => 100,
			'height' => 100,
			'file' => '2023/12/test.jpg',
			'sizes' => array(),
		);

		$result = $this->hooks_integration->generate_attachment_metadata( $metadata, $attachment_id, 'create' );

		$this->assertIsArray( $result );
		$this->assertEquals( 100, $result['width'] );
		$this->assertEquals( 100, $result['height'] );
	}

	/**
	 * Test attachment metadata generation with wrong context
	 */
	public function test_generate_attachment_metadata_wrong_context() {
		$attachment_id = $this->factory->attachment->create_upload_object( $this->test_image_path );

		$metadata = array(
			'width' => 100,
			'height' => 100,
			'file' => '2023/12/test.jpg',
		);

		$result = $this->hooks_integration->generate_attachment_metadata( $metadata, $attachment_id, 'update' );

		$this->assertIsArray( $result );
		$this->assertEquals( $metadata, $result );
	}

	/**
	 * Test image source URL modification
	 */
	public function test_modify_attachment_image_src() {
		$attachment_id = $this->factory->attachment->create_upload_object( $this->test_image_path );

		$image_data = array(
			'http://example.com/wp-content/uploads/2023/12/test.jpg',
			100,
			100,
			false,
		);

		$result = $this->hooks_integration->modify_attachment_image_src( $image_data, $attachment_id, 'full', false );

		$this->assertIsArray( $result );
		$this->assertCount( 4, $result );
	}

	/**
	 * Test image source URL modification with icon
	 */
	public function test_modify_attachment_image_src_icon() {
		$attachment_id = $this->factory->attachment->create_upload_object( $this->test_image_path );

		$image_data = array(
			'http://example.com/wp-content/uploads/2023/12/test.jpg',
			100,
			100,
			false,
		);

		$result = $this->hooks_integration->modify_attachment_image_src( $image_data, $attachment_id, 'full', true );

		$this->assertIsArray( $result );
		$this->assertEquals( $image_data, $result );
	}

	/**
	 * Test image source URL modification with false input
	 */
	public function test_modify_attachment_image_src_false_input() {
		$attachment_id = $this->factory->attachment->create_upload_object( $this->test_image_path );

		$result = $this->hooks_integration->modify_attachment_image_src( false, $attachment_id, 'full', false );

		$this->assertFalse( $result );
	}

	/**
	 * Test cleanup on attachment delete
	 */
	public function test_cleanup_on_attachment_delete() {
		$attachment_id = $this->factory->attachment->create_upload_object( $this->test_image_path );

		// Add some metadata
		update_post_meta( $attachment_id, '_wp_image_optimizer_conversions', array( 'test' => 'data' ) );

		$this->hooks_integration->cleanup_on_attachment_delete( $attachment_id );

		$metadata = get_post_meta( $attachment_id, '_wp_image_optimizer_conversions', true );
		$this->assertEmpty( $metadata );
	}

	/**
	 * Test convertible image detection
	 */
	public function test_is_convertible_image() {
		$reflection = new ReflectionClass( $this->hooks_integration );
		$method = $reflection->getMethod( 'is_convertible_image' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( $this->hooks_integration, 'image/jpeg' ) );
		$this->assertTrue( $method->invoke( $this->hooks_integration, 'image/png' ) );
		$this->assertTrue( $method->invoke( $this->hooks_integration, 'image/gif' ) );
		$this->assertFalse( $method->invoke( $this->hooks_integration, 'application/pdf' ) );
		$this->assertFalse( $method->invoke( $this->hooks_integration, 'text/plain' ) );
	}

	/**
	 * Test best format detection for browser
	 */
	public function test_get_best_format_for_browser() {
		$reflection = new ReflectionClass( $this->hooks_integration );
		$method = $reflection->getMethod( 'get_best_format_for_browser' );
		$method->setAccessible( true );

		// Test AVIF support
		$_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8';
		$result = $method->invoke( $this->hooks_integration );
		$this->assertEquals( 'avif', $result );

		// Test WebP support only
		$_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8';
		$result = $method->invoke( $this->hooks_integration );
		$this->assertEquals( 'webp', $result );

		// Test no modern format support
		$_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/png,*/*;q=0.8';
		$result = $method->invoke( $this->hooks_integration );
		$this->assertFalse( $result );
	}

	/**
	 * Test upload conversion data storage
	 */
	public function test_store_upload_conversion_data() {
		$attachment_id = $this->factory->attachment->create_upload_object( $this->test_image_path );
		$conversion_data = array(
			'conversions' => array( 'webp' => array( 'path' => '/test.webp' ) ),
			'space_saved' => 1024,
		);

		$this->hooks_integration->store_upload_conversion_data( $attachment_id, $conversion_data );

		$stored_data = get_post_meta( $attachment_id, '_wp_image_optimizer_upload_conversions', true );
		$this->assertEquals( $conversion_data, $stored_data );
	}

	/**
	 * Test hooks removal
	 */
	public function test_remove_hooks() {
		$this->assertTrue( $this->hooks_integration->are_hooks_initialized() );

		$this->hooks_integration->remove_hooks();

		$this->assertFalse( $this->hooks_integration->are_hooks_initialized() );
	}

	/**
	 * Test plugin statistics update
	 */
	public function test_update_plugin_statistics() {
		$reflection = new ReflectionClass( $this->hooks_integration );
		$method = $reflection->getMethod( 'update_plugin_statistics' );
		$method->setAccessible( true );

		$conversion_result = array(
			'conversions' => array(
				'webp' => array( 'path' => '/test.webp' ),
				'avif' => array( 'path' => '/test.avif' ),
			),
			'space_saved' => 2048,
		);

		$method->invoke( $this->hooks_integration, $conversion_result );

		$options = get_option( 'wp_image_optimizer_settings' );
		$this->assertEquals( 2, $options['stats']['total_conversions'] );
		$this->assertEquals( 2048, $options['stats']['space_saved'] );
	}

	/**
	 * Test on-demand conversion handling
	 */
	public function test_handle_on_demand_conversion() {
		// Mock server request for image
		$_SERVER['REQUEST_URI'] = '/wp-content/uploads/2023/12/test.jpg';

		// This method primarily handles 404 scenarios and serves files
		// In a unit test environment, we can't fully test file serving
		// but we can ensure the method doesn't cause errors
		$this->hooks_integration->handle_on_demand_conversion();

		// If we reach here without errors, the method handled the request properly
		$this->assertTrue( true );
	}

	/**
	 * Create a test image file
	 *
	 * @return string Path to test image
	 */
	private function create_test_image() {
		$upload_dir = wp_upload_dir();
		$test_file = $upload_dir['basedir'] . '/test-image.jpg';

		// Create a simple test image
		$image = imagecreate( 100, 100 );
		$white = imagecolorallocate( $image, 255, 255, 255 );
		$black = imagecolorallocate( $image, 0, 0, 0 );
		imagefill( $image, 0, 0, $white );
		imagestring( $image, 5, 30, 40, 'TEST', $black );
		imagejpeg( $image, $test_file, 90 );
		imagedestroy( $image );

		return $test_file;
	}
}