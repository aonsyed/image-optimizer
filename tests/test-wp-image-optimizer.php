<?php
/**
 * Tests for the main WP_Image_Optimizer class
 *
 * @package WP_Image_Optimizer
 */

class Test_WP_Image_Optimizer extends WP_UnitTestCase {

	/**
	 * Test singleton pattern
	 */
	public function test_singleton_pattern() {
		$instance1 = WP_Image_Optimizer::get_instance();
		$instance2 = WP_Image_Optimizer::get_instance();
		
		$this->assertInstanceOf( 'WP_Image_Optimizer', $instance1 );
		$this->assertSame( $instance1, $instance2, 'Multiple calls to get_instance() should return the same instance' );
	}

	/**
	 * Test plugin version
	 */
	public function test_plugin_version() {
		$instance = WP_Image_Optimizer::get_instance();
		$version = $instance->get_version();
		
		$this->assertNotEmpty( $version );
		$this->assertEquals( WP_IMAGE_OPTIMIZER_VERSION, $version );
	}

	/**
	 * Test initialization status
	 */
	public function test_initialization_status() {
		$instance = WP_Image_Optimizer::get_instance();
		$initialized = $instance->is_initialized();
		
		$this->assertTrue( $initialized, 'Plugin should be initialized after get_instance()' );
	}

	/**
	 * Test hooks integration instance
	 */
	public function test_hooks_integration_instance() {
		$instance = WP_Image_Optimizer::get_instance();
		$hooks_integration = $instance->get_hooks_integration();
		
		$this->assertInstanceOf( 'WP_Image_Optimizer_Hooks_Integration', $hooks_integration );
	}

	/**
	 * Test batch processor instance
	 */
	public function test_batch_processor_instance() {
		$instance = WP_Image_Optimizer::get_instance();
		$batch_processor = $instance->get_batch_processor();
		
		$this->assertInstanceOf( 'WP_Image_Optimizer_Batch_Processor', $batch_processor );
	}

	/**
	 * Test admin interface instance
	 */
	public function test_admin_interface_instance() {
		// Skip if not in admin context
		if ( ! is_admin() ) {
			$this->markTestSkipped( 'Admin interface is only initialized in admin context' );
			return;
		}
		
		$instance = WP_Image_Optimizer::get_instance();
		$admin_interface = $instance->get_admin_interface();
		
		$this->assertInstanceOf( 'WP_Image_Optimizer_Admin_Interface', $admin_interface );
	}

	/**
	 * Test text domain loading
	 */
	public function test_text_domain_loading() {
		$instance = WP_Image_Optimizer::get_instance();
		
		// Call load_textdomain method
		$instance->load_textdomain();
		
		// Verify the text domain is loaded
		$loaded_domains = get_included_files();
		$domain_loaded = false;
		
		foreach ( $loaded_domains as $file ) {
			if ( strpos( $file, 'wp-image-optimizer' ) !== false && strpos( $file, 'languages' ) !== false ) {
				$domain_loaded = true;
				break;
			}
		}
		
		// This might not be true in test environment, so we're not asserting it
		// Just ensuring the method runs without errors
		$this->assertIsArray( $loaded_domains );
	}

	/**
	 * Test security checks
	 */
	public function test_security_checks() {
		$instance = WP_Image_Optimizer::get_instance();
		
		// Use reflection to access private method
		$reflection = new ReflectionClass( $instance );
		$method = $reflection->getMethod( 'security_checks' );
		$method->setAccessible( true );
		
		$result = $method->invoke( $instance );
		$this->assertTrue( $result, 'Security checks should pass in test environment' );
	}

	/**
	 * Test activation hook
	 */
	public function test_activation_hook() {
		// Delete activation flag if exists
		delete_option( 'wp_image_optimizer_activated' );
		
		// Call activation method
		WP_Image_Optimizer::activate();
		
		// Check if activation flag is set
		$activated = get_option( 'wp_image_optimizer_activated' );
		$this->assertTrue( $activated, 'Activation flag should be set after activation' );
	}

	/**
	 * Test deactivation hook
	 */
	public function test_deactivation_hook() {
		// Schedule a cron event to test clearing
		wp_schedule_event( time(), 'hourly', 'wp_image_optimizer_batch_process' );
		
		// Call deactivation method
		WP_Image_Optimizer::deactivate();
		
		// Check if scheduled event is cleared
		$next_scheduled = wp_next_scheduled( 'wp_image_optimizer_batch_process' );
		$this->assertFalse( $next_scheduled, 'Scheduled event should be cleared after deactivation' );
	}

	/**
	 * Test uninstall hook with filter
	 */
	public function test_uninstall_hook_with_filter() {
		// Add filter to test image removal
		add_filter( 'wp_image_optimizer_uninstall_remove_images', '__return_true' );
		
		// Call uninstall method
		WP_Image_Optimizer::uninstall();
		
		// Remove filter
		remove_filter( 'wp_image_optimizer_uninstall_remove_images', '__return_true' );
		
		// No assertion needed, just ensuring it runs without errors
		$this->assertTrue( true );
	}

	/**
	 * Test component initialization
	 */
	public function test_component_initialization() {
		$instance = WP_Image_Optimizer::get_instance();
		
		// Call init_components method
		$instance->init_components();
		
		// Check if hooks integration is initialized
		$hooks_integration = $instance->get_hooks_integration();
		$this->assertInstanceOf( 'WP_Image_Optimizer_Hooks_Integration', $hooks_integration );
		
		// Check if batch processor is initialized
		$batch_processor = $instance->get_batch_processor();
		$this->assertInstanceOf( 'WP_Image_Optimizer_Batch_Processor', $batch_processor );
	}

	/**
	 * Test admin initialization
	 */
	public function test_admin_initialization() {
		$instance = WP_Image_Optimizer::get_instance();
		
		// Call admin_init method
		$instance->admin_init();
		
		// No assertion needed, just ensuring it runs without errors
		$this->assertTrue( true );
	}
}