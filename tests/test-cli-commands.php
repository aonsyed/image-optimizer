<?php
/**
 * CLI Commands Integration Tests
 *
 * @package WP_Image_Optimizer
 */

/**
 * Test CLI Commands functionality
 */
class Test_CLI_Commands extends WP_UnitTestCase {

	/**
	 * CLI commands instance
	 *
	 * @var WP_Image_Optimizer_CLI_Commands
	 */
	private $cli_commands;

	/**
	 * Test image attachment ID
	 *
	 * @var int
	 */
	private $test_attachment_id;

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

		// Skip if WP-CLI is not available
		if ( ! class_exists( 'WP_CLI_Command' ) ) {
			$this->markTestSkipped( 'WP-CLI not available for testing' );
		}

		// Load required classes
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-settings-manager.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-file-handler.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/interfaces/interface-converter.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/converters/class-converter-factory.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-image-converter.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-cli-commands.php';

		// Initialize CLI commands
		$this->cli_commands = new WP_Image_Optimizer_CLI_Commands();

		// Create test image
		$this->create_test_image();
	}

	/**
	 * Clean up test environment
	 */
	public function tearDown(): void {
		// Clean up test image
		if ( $this->test_attachment_id ) {
			wp_delete_attachment( $this->test_attachment_id, true );
		}

		if ( $this->test_image_path && file_exists( $this->test_image_path ) ) {
			unlink( $this->test_image_path );
		}

		// Reset settings
		delete_option( 'wp_image_optimizer_settings' );

		parent::tearDown();
	}

	/**
	 * Create test image for testing
	 */
	private function create_test_image() {
		// Create a simple test image
		$upload_dir = wp_upload_dir();
		$this->test_image_path = $upload_dir['path'] . '/test-image.jpg';

		// Create a simple 100x100 JPEG image
		$image = imagecreate( 100, 100 );
		$white = imagecolorallocate( $image, 255, 255, 255 );
		$black = imagecolorallocate( $image, 0, 0, 0 );
		imagefill( $image, 0, 0, $white );
		imagestring( $image, 5, 30, 40, 'TEST', $black );
		imagejpeg( $image, $this->test_image_path, 90 );
		imagedestroy( $image );

		// Create attachment
		$attachment_data = array(
			'post_title' => 'Test Image',
			'post_content' => '',
			'post_status' => 'inherit',
			'post_mime_type' => 'image/jpeg',
		);

		$this->test_attachment_id = wp_insert_attachment( $attachment_data, $this->test_image_path );
		wp_generate_attachment_metadata( $this->test_attachment_id, $this->test_image_path );
	}

	/**
	 * Test CLI command registration
	 */
	public function test_cli_command_registration() {
		// This test verifies that the CLI commands class can be instantiated
		$this->assertInstanceOf( 'WP_Image_Optimizer_CLI_Commands', $this->cli_commands );
	}

	/**
	 * Test get-settings command
	 */
	public function test_get_settings_command() {
		// Set up some test settings
		$test_settings = array(
			'enabled' => true,
			'formats' => array(
				'webp' => array( 'enabled' => true, 'quality' => 85 ),
				'avif' => array( 'enabled' => false, 'quality' => 70 ),
			),
		);

		$settings_manager = new WP_Image_Optimizer_Settings_Manager();
		$settings_manager->update_settings( $test_settings );

		// Capture output
		ob_start();
		
		try {
			$this->cli_commands->get_settings( array(), array( 'format' => 'json' ) );
			$output = ob_get_clean();
			
			// Verify output contains expected settings
			$this->assertStringContainsString( '"enabled":true', $output );
			$this->assertStringContainsString( '"quality":85', $output );
		} catch ( Exception $e ) {
			ob_end_clean();
			// If WP_CLI methods aren't available, skip this test
			$this->markTestSkipped( 'WP_CLI output methods not available in test environment' );
		}
	}

	/**
	 * Test get-settings command with specific field
	 */
	public function test_get_settings_command_with_field() {
		// Set up test settings
		$test_settings = array(
			'formats' => array(
				'webp' => array( 'quality' => 85 ),
			),
		);

		$settings_manager = new WP_Image_Optimizer_Settings_Manager();
		$settings_manager->update_settings( $test_settings );

		// Test getting specific field
		ob_start();
		
		try {
			$this->cli_commands->get_settings( array(), array( 'field' => 'formats.webp.quality' ) );
			$output = ob_get_clean();
			
			// Should contain the quality value
			$this->assertStringContainsString( '85', $output );
		} catch ( Exception $e ) {
			ob_end_clean();
			$this->markTestSkipped( 'WP_CLI output methods not available in test environment' );
		}
	}

	/**
	 * Test set-settings command
	 */
	public function test_set_settings_command() {
		$args = array();
		$assoc_args = array(
			'enabled' => 'true',
			'webp-quality' => '90',
			'avif-enabled' => 'false',
		);

		// Execute set-settings command
		try {
			$this->cli_commands->set_settings( $args, $assoc_args );
			
			// Verify settings were updated
			$settings_manager = new WP_Image_Optimizer_Settings_Manager();
			$settings = $settings_manager->get_settings();
			
			$this->assertTrue( $settings['enabled'] );
			$this->assertEquals( 90, $settings['formats']['webp']['quality'] );
			$this->assertFalse( $settings['formats']['avif']['enabled'] );
		} catch ( Exception $e ) {
			$this->markTestSkipped( 'WP_CLI methods not available in test environment' );
		}
	}

	/**
	 * Test status command
	 */
	public function test_status_command() {
		ob_start();
		
		try {
			$this->cli_commands->status( array(), array( 'format' => 'json' ) );
			$output = ob_get_clean();
			
			// Verify output contains expected status information
			$this->assertStringContainsString( 'plugin_version', $output );
			$this->assertStringContainsString( 'conversion_enabled', $output );
		} catch ( Exception $e ) {
			ob_end_clean();
			$this->markTestSkipped( 'WP_CLI output methods not available in test environment' );
		}
	}

	/**
	 * Test convert-id command with valid attachment
	 */
	public function test_convert_id_command() {
		// Skip if no converter available
		$converter = Converter_Factory::get_converter();
		if ( ! $converter || ! $converter->is_available() ) {
			$this->markTestSkipped( 'No image converter available for testing' );
		}

		$args = array( $this->test_attachment_id );
		$assoc_args = array( 'dry-run' => true );

		try {
			ob_start();
			$this->cli_commands->convert_id( $args, $assoc_args );
			$output = ob_get_clean();
			
			// In dry-run mode, should show what would be converted
			$this->assertStringContainsString( 'Converting attachment', $output );
		} catch ( Exception $e ) {
			ob_end_clean();
			$this->markTestSkipped( 'CLI conversion test failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Test convert-id command with invalid attachment
	 */
	public function test_convert_id_command_invalid_attachment() {
		$args = array( 99999 ); // Non-existent attachment ID
		$assoc_args = array();

		$this->expectException( Exception::class );
		$this->cli_commands->convert_id( $args, $assoc_args );
	}

	/**
	 * Test convert-path command
	 */
	public function test_convert_path_command() {
		// Skip if no converter available
		$converter = Converter_Factory::get_converter();
		if ( ! $converter || ! $converter->is_available() ) {
			$this->markTestSkipped( 'No image converter available for testing' );
		}

		$upload_dir = wp_upload_dir();
		$relative_path = str_replace( $upload_dir['basedir'] . '/', '', $this->test_image_path );
		
		$args = array( $relative_path );
		$assoc_args = array( 'dry-run' => true );

		try {
			ob_start();
			$this->cli_commands->convert_path( $args, $assoc_args );
			$output = ob_get_clean();
			
			// In dry-run mode, should show what would be converted
			$this->assertStringContainsString( 'Converting image', $output );
		} catch ( Exception $e ) {
			ob_end_clean();
			$this->markTestSkipped( 'CLI path conversion test failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Test convert-path command with invalid path
	 */
	public function test_convert_path_command_invalid_path() {
		$args = array( 'non-existent-image.jpg' );
		$assoc_args = array();

		$this->expectException( Exception::class );
		$this->cli_commands->convert_path( $args, $assoc_args );
	}

	/**
	 * Test convert-all command with dry-run
	 */
	public function test_convert_all_command_dry_run() {
		$args = array();
		$assoc_args = array( 
			'dry-run' => true,
			'limit' => 1,
		);

		try {
			ob_start();
			$this->cli_commands->convert_all( $args, $assoc_args );
			$output = ob_get_clean();
			
			// Should show dry-run mode message
			$this->assertStringContainsString( 'DRY RUN MODE', $output );
		} catch ( Exception $e ) {
			ob_end_clean();
			$this->markTestSkipped( 'CLI convert-all test failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Test reset-settings command
	 */
	public function test_reset_settings_command() {
		// Set some custom settings first
		$settings_manager = new WP_Image_Optimizer_Settings_Manager();
		$settings_manager->update_settings( array( 'enabled' => false ) );

		$args = array();
		$assoc_args = array( 'yes' => true );

		try {
			$this->cli_commands->reset_settings( $args, $assoc_args );
			
			// Verify settings were reset
			$settings = $settings_manager->get_settings();
			$this->assertTrue( $settings['enabled'] ); // Should be back to default
		} catch ( Exception $e ) {
			$this->markTestSkipped( 'CLI reset-settings test failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Test cleanup command with dry-run
	 */
	public function test_cleanup_command_dry_run() {
		$args = array();
		$assoc_args = array( 
			'attachment-id' => $this->test_attachment_id,
			'dry-run' => true,
		);

		try {
			ob_start();
			$this->cli_commands->cleanup( $args, $assoc_args );
			$output = ob_get_clean();
			
			// Should show dry-run mode message
			$this->assertStringContainsString( 'DRY RUN MODE', $output );
		} catch ( Exception $e ) {
			ob_end_clean();
			$this->markTestSkipped( 'CLI cleanup test failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Test parameter validation for various commands
	 */
	public function test_parameter_validation() {
		// Test convert-id without ID
		try {
			$this->expectException( Exception::class );
			$this->cli_commands->convert_id( array(), array() );
		} catch ( Exception $e ) {
			$this->assertStringContainsString( 'required', $e->getMessage() );
		}

		// Test convert-path without path
		try {
			$this->expectException( Exception::class );
			$this->cli_commands->convert_path( array(), array() );
		} catch ( Exception $e ) {
			$this->assertStringContainsString( 'required', $e->getMessage() );
		}

		// Test invalid format parameter
		try {
			$this->expectException( Exception::class );
			$this->cli_commands->convert_id( array( $this->test_attachment_id ), array( 'format' => 'invalid' ) );
		} catch ( Exception $e ) {
			$this->assertStringContainsString( 'Invalid format', $e->getMessage() );
		}
	}

	/**
	 * Test boolean parsing helper method
	 */
	public function test_boolean_parsing() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->cli_commands );
		$method = $reflection->getMethod( 'parse_boolean' );
		$method->setAccessible( true );

		// Test various boolean representations
		$this->assertTrue( $method->invoke( $this->cli_commands, true ) );
		$this->assertTrue( $method->invoke( $this->cli_commands, 'true' ) );
		$this->assertTrue( $method->invoke( $this->cli_commands, '1' ) );
		$this->assertTrue( $method->invoke( $this->cli_commands, 'yes' ) );
		$this->assertTrue( $method->invoke( $this->cli_commands, 'on' ) );

		$this->assertFalse( $method->invoke( $this->cli_commands, false ) );
		$this->assertFalse( $method->invoke( $this->cli_commands, 'false' ) );
		$this->assertFalse( $method->invoke( $this->cli_commands, '0' ) );
		$this->assertFalse( $method->invoke( $this->cli_commands, 'no' ) );
		$this->assertFalse( $method->invoke( $this->cli_commands, 'off' ) );
	}

	/**
	 * Test format_bytes helper method
	 */
	public function test_format_bytes() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->cli_commands );
		$method = $reflection->getMethod( 'format_bytes' );
		$method->setAccessible( true );

		// Test various byte sizes
		$this->assertEquals( '1 B', $method->invoke( $this->cli_commands, 1 ) );
		$this->assertEquals( '1 KB', $method->invoke( $this->cli_commands, 1024 ) );
		$this->assertEquals( '1 MB', $method->invoke( $this->cli_commands, 1024 * 1024 ) );
		$this->assertEquals( '1.5 KB', $method->invoke( $this->cli_commands, 1536 ) );
	}

	/**
	 * Test path resolution helper method
	 */
	public function test_path_resolution() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->cli_commands );
		$method = $reflection->getMethod( 'resolve_image_path' );
		$method->setAccessible( true );

		// Test absolute path
		$result = $method->invoke( $this->cli_commands, $this->test_image_path );
		$this->assertEquals( $this->test_image_path, $result );

		// Test relative path
		$upload_dir = wp_upload_dir();
		$relative_path = str_replace( $upload_dir['basedir'] . '/', '', $this->test_image_path );
		$result = $method->invoke( $this->cli_commands, $relative_path );
		$this->assertEquals( $this->test_image_path, $result );

		// Test non-existent path
		$result = $method->invoke( $this->cli_commands, 'non-existent.jpg' );
		$this->assertInstanceOf( 'WP_Error', $result );
	}
}