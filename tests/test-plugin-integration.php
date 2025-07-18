<?php
/**
 * Integration test for the WP Image Optimizer plugin
 *
 * @package WP_Image_Optimizer
 */

class Test_Plugin_Integration extends WP_UnitTestCase {

	/**
	 * Test plugin initialization
	 */
	public function test_plugin_initialization() {
		// Get plugin instance
		$plugin = wp_image_optimizer();
		
		// Check if plugin instance is returned
		$this->assertInstanceOf( 'WP_Image_Optimizer', $plugin );
		
		// Check if plugin is initialized
		$this->assertTrue( $plugin->is_initialized() );
		
		// Check plugin version
		$this->assertEquals( WP_IMAGE_OPTIMIZER_VERSION, $plugin->get_version() );
	}

	/**
	 * Test hooks integration
	 */
	public function test_hooks_integration() {
		// Get plugin instance
		$plugin = wp_image_optimizer();
		
		// Check if hooks integration is available
		$hooks_integration = $plugin->get_hooks_integration();
		$this->assertInstanceOf( 'WP_Image_Optimizer_Hooks_Integration', $hooks_integration );
		
		// Check if required filters are added
		$this->assertGreaterThan( 0, has_filter( 'wp_handle_upload', array( $hooks_integration, 'handle_upload' ) ) );
		$this->assertGreaterThan( 0, has_filter( 'wp_generate_attachment_metadata', array( $hooks_integration, 'update_attachment_metadata' ) ) );
		$this->assertGreaterThan( 0, has_filter( 'wp_get_attachment_image_src', array( $hooks_integration, 'get_attachment_image_src' ) ) );
	}

	/**
	 * Test database manager
	 */
	public function test_database_manager() {
		// Get database manager instance
		$db_manager = WP_Image_Optimizer_Database_Manager::get_instance();
		$this->assertInstanceOf( 'WP_Image_Optimizer_Database_Manager', $db_manager );
		
		// Check if database is initialized
		$option = get_option( 'wp_image_optimizer_settings' );
		$this->assertNotFalse( $option );
		
		// Check if database structure is correct
		$this->assertArrayHasKey( 'version', $option );
		$this->assertArrayHasKey( 'settings', $option );
		$this->assertArrayHasKey( 'stats', $option );
		$this->assertArrayHasKey( 'server_capabilities', $option );
	}

	/**
	 * Test error handler
	 */
	public function test_error_handler() {
		// Get error handler instance
		$error_handler = WP_Image_Optimizer_Error_Handler::get_instance();
		$this->assertInstanceOf( 'WP_Image_Optimizer_Error_Handler', $error_handler );
		
		// Test error logging
		$error_id = $error_handler->log_error( 'Test error message', 'info', 'system', array( 'test' => true ) );
		$this->assertNotFalse( $error_id );
		
		// Get error logs
		$logs = $error_handler->get_error_logs( 1 );
		$this->assertCount( 1, $logs );
		$this->assertEquals( 'Test error message', $logs[0]['message'] );
		
		// Clean up
		$error_handler->clear_error_logs();
	}

	/**
	 * Test cleanup manager
	 */
	public function test_cleanup_manager() {
		// Create cleanup manager instance
		$cleanup_manager = new WP_Image_Optimizer_Cleanup_Manager();
		$this->assertInstanceOf( 'WP_Image_Optimizer_Cleanup_Manager', $cleanup_manager );
		
		// Test temp file cleanup (dry run)
		$results = $cleanup_manager->cleanup_temp_files();
		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'scanned', $results );
	}
}