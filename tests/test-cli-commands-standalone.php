<?php
/**
 * Tests for CLI commands standalone functionality
 *
 * @package WP_Image_Optimizer
 */

class Test_CLI_Commands_Standalone extends WP_UnitTestCase {

	/**
	 * CLI commands instance
	 *
	 * @var WP_Image_Optimizer_CLI_Commands
	 */
	private $cli_commands;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Skip if WP-CLI is not available
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			$this->markTestSkipped( 'WP-CLI is not available' );
			return;
		}
		
		// Create CLI commands instance
		$this->cli_commands = new WP_Image_Optimizer_CLI_Commands();
	}

	/**
	 * Test status command
	 */
	public function test_status_command() {
		// Capture output
		ob_start();
		$this->cli_commands->status();
		$output = ob_get_clean();
		
		// Check if output contains required information
		$this->assertStringContainsString( 'WP Image Optimizer Status', $output );
		$this->assertStringContainsString( 'Plugin Version', $output );
		$this->assertStringContainsString( 'Conversion Enabled', $output );
		$this->assertStringContainsString( 'WebP Support', $output );
		$this->assertStringContainsString( 'AVIF Support', $output );
	}

	/**
	 * Test settings command
	 */
	public function test_settings_command() {
		// Capture output
		ob_start();
		$this->cli_commands->settings();
		$output = ob_get_clean();
		
		// Check if output contains required information
		$this->assertStringContainsString( 'WP Image Optimizer Settings', $output );
		$this->assertStringContainsString( 'enabled', $output );
		$this->assertStringContainsString( 'formats', $output );
		$this->assertStringContainsString( 'webp', $output );
		$this->assertStringContainsString( 'avif', $output );
	}

	/**
	 * Test update settings command
	 */
	public function test_update_settings_command() {
		// Get current settings
		$current_settings = WP_Image_Optimizer_Settings_Manager::get_settings();
		
		// Create assoc args
		$assoc_args = array(
			'enabled' => 'false',
			'webp-quality' => '95',
			'avif-quality' => '65',
		);
		
		// Capture output
		ob_start();
		$this->cli_commands->update_settings( array(), $assoc_args );
		$output = ob_get_clean();
		
		// Check if output contains success message
		$this->assertStringContainsString( 'Settings updated successfully', $output );
		
		// Get updated settings
		$updated_settings = WP_Image_Optimizer_Settings_Manager::get_settings();
		
		// Check if settings are updated
		$this->assertFalse( $updated_settings['enabled'] );
		$this->assertEquals( 95, $updated_settings['formats']['webp']['quality'] );
		$this->assertEquals( 65, $updated_settings['formats']['avif']['quality'] );
		
		// Reset settings
		WP_Image_Optimizer_Settings_Manager::update_settings( $current_settings );
	}

	/**
	 * Test reset settings command
	 */
	public function test_reset_settings_command() {
		// Update settings
		WP_Image_Optimizer_Settings_Manager::update_settings( array(
			'enabled' => false,
			'formats' => array(
				'webp' => array(
					'enabled' => false,
					'quality' => 50,
				),
			),
		) );
		
		// Capture output
		ob_start();
		$this->cli_commands->reset_settings();
		$output = ob_get_clean();
		
		// Check if output contains success message
		$this->assertStringContainsString( 'Settings reset to defaults', $output );
		
		// Get reset settings
		$reset_settings = WP_Image_Optimizer_Settings_Manager::get_settings();
		
		// Check if settings are reset to defaults
		$this->assertTrue( $reset_settings['enabled'] );
		$this->assertTrue( $reset_settings['formats']['webp']['enabled'] );
		$this->assertNotEquals( 50, $reset_settings['formats']['webp']['quality'] );
	}

	/**
	 * Test convert command with invalid ID
	 */
	public function test_convert_command_with_invalid_id() {
		// Capture output
		ob_start();
		$this->cli_commands->convert( array( '999999' ) );
		$output = ob_get_clean();
		
		// Check if output contains error message
		$this->assertStringContainsString( 'Error', $output );
		$this->assertStringContainsString( 'Invalid attachment ID', $output );
	}

	/**
	 * Test convert command with valid ID
	 */
	public function test_convert_command_with_valid_id() {
		// Create test image
		$upload_dir = wp_upload_dir();
		$test_image_path = $upload_dir['basedir'] . '/test-cli-image.jpg';
		wp_image_optimizer_create_test_image( $test_image_path );
		
		// Create test attachment
		$attachment_id = wp_image_optimizer_create_test_attachment( $test_image_path );
		
		// Capture output
		ob_start();
		$this->cli_commands->convert( array( $attachment_id ) );
		$output = ob_get_clean();
		
		// Check if output contains success or processing message
		$this->assertTrue(
			strpos( $output, 'Converting' ) !== false ||
			strpos( $output, 'Converted' ) !== false ||
			strpos( $output, 'Skipped' ) !== false
		);
		
		// Clean up
		wp_delete_attachment( $attachment_id, true );
		wp_image_optimizer_cleanup_test_image( $test_image_path );
	}

	/**
	 * Test bulk convert command
	 */
	public function test_bulk_convert_command() {
		// Create test images
		$upload_dir = wp_upload_dir();
		$test_image_paths = array(
			$upload_dir['basedir'] . '/test-cli-bulk-1.jpg',
			$upload_dir['basedir'] . '/test-cli-bulk-2.jpg',
		);
		
		$attachment_ids = array();
		foreach ( $test_image_paths as $path ) {
			wp_image_optimizer_create_test_image( $path );
			$attachment_ids[] = wp_image_optimizer_create_test_attachment( $path );
		}
		
		// Create assoc args
		$assoc_args = array(
			'limit' => '10',
			'force' => 'true',
		);
		
		// Capture output
		ob_start();
		$this->cli_commands->bulk_convert( array(), $assoc_args );
		$output = ob_get_clean();
		
		// Check if output contains processing message
		$this->assertStringContainsString( 'Starting bulk conversion', $output );
		
		// Clean up
		foreach ( $attachment_ids as $id ) {
			wp_delete_attachment( $id, true );
		}
		foreach ( $test_image_paths as $path ) {
			wp_image_optimizer_cleanup_test_image( $path );
		}
	}

	/**
	 * Test stats command
	 */
	public function test_stats_command() {
		// Capture output
		ob_start();
		$this->cli_commands->stats();
		$output = ob_get_clean();
		
		// Check if output contains required information
		$this->assertStringContainsString( 'WP Image Optimizer Statistics', $output );
		$this->assertStringContainsString( 'Total Images', $output );
		$this->assertStringContainsString( 'Converted Images', $output );
		$this->assertStringContainsString( 'Total Conversions', $output );
		$this->assertStringContainsString( 'Space Saved', $output );
	}

	/**
	 * Test cleanup command
	 */
	public function test_cleanup_command() {
		// Create assoc args
		$assoc_args = array(
			'orphaned' => 'true',
			'transients' => 'true',
		);
		
		// Capture output
		ob_start();
		$this->cli_commands->cleanup( array(), $assoc_args );
		$output = ob_get_clean();
		
		// Check if output contains success message
		$this->assertStringContainsString( 'Cleanup completed', $output );
	}

	/**
	 * Test server-config command
	 */
	public function test_server_config_command() {
		// Create assoc args for Nginx
		$nginx_args = array(
			'type' => 'nginx',
		);
		
		// Capture output for Nginx
		ob_start();
		$this->cli_commands->server_config( array(), $nginx_args );
		$nginx_output = ob_get_clean();
		
		// Check if output contains Nginx configuration
		$this->assertStringContainsString( 'location', $nginx_output );
		
		// Create assoc args for Apache
		$apache_args = array(
			'type' => 'apache',
		);
		
		// Capture output for Apache
		ob_start();
		$this->cli_commands->server_config( array(), $apache_args );
		$apache_output = ob_get_clean();
		
		// Check if output contains Apache configuration
		$this->assertStringContainsString( 'RewriteEngine', $apache_output );
	}

	/**
	 * Test version command
	 */
	public function test_version_command() {
		// Capture output
		ob_start();
		$this->cli_commands->version();
		$output = ob_get_clean();
		
		// Check if output contains version information
		$this->assertStringContainsString( 'WP Image Optimizer', $output );
		$this->assertStringContainsString( WP_IMAGE_OPTIMIZER_VERSION, $output );
	}

	/**
	 * Test validate command
	 */
	public function test_validate_command() {
		// Create test image
		$upload_dir = wp_upload_dir();
		$test_image_path = $upload_dir['basedir'] . '/test-cli-validate.jpg';
		wp_image_optimizer_create_test_image( $test_image_path );
		
		// Create assoc args
		$assoc_args = array(
			'path' => $test_image_path,
		);
		
		// Capture output
		ob_start();
		$this->cli_commands->validate( array(), $assoc_args );
		$output = ob_get_clean();
		
		// Check if output contains validation information
		$this->assertStringContainsString( 'Validation Results', $output );
		$this->assertStringContainsString( 'File exists', $output );
		$this->assertStringContainsString( 'File is readable', $output );
		$this->assertStringContainsString( 'MIME type', $output );
		
		// Clean up
		wp_image_optimizer_cleanup_test_image( $test_image_path );
	}

	/**
	 * Test help command
	 */
	public function test_help_command() {
		// Capture output
		ob_start();
		$this->cli_commands->help();
		$output = ob_get_clean();
		
		// Check if output contains help information
		$this->assertStringContainsString( 'WP Image Optimizer CLI Commands', $output );
		$this->assertStringContainsString( 'Available Commands', $output );
		$this->assertStringContainsString( 'status', $output );
		$this->assertStringContainsString( 'settings', $output );
		$this->assertStringContainsString( 'convert', $output );
		$this->assertStringContainsString( 'bulk-convert', $output );
	}
}