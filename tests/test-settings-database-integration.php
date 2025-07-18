<?php
/**
 * Tests for settings and database integration
 *
 * @package WP_Image_Optimizer
 */

class Test_Settings_Database_Integration extends WP_UnitTestCase {

	/**
	 * Database manager instance
	 *
	 * @var WP_Image_Optimizer_Database_Manager
	 */
	private $db_manager;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Create database manager
		$this->db_manager = WP_Image_Optimizer_Database_Manager::get_instance();
		
		// Reset settings
		WP_Image_Optimizer_Settings_Manager::reset_settings( true );
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		// Reset settings
		WP_Image_Optimizer_Settings_Manager::reset_settings( true );
		
		parent::tearDown();
	}

	/**
	 * Test settings initialization
	 */
	public function test_settings_initialization() {
		// Delete settings option
		delete_option( 'wp_image_optimizer_settings' );
		
		// Initialize settings
		$settings = WP_Image_Optimizer_Settings_Manager::get_settings();
		
		// Check if settings are initialized with defaults
		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'enabled', $settings );
		$this->assertTrue( $settings['enabled'] );
		$this->assertArrayHasKey( 'formats', $settings );
		$this->assertIsArray( $settings['formats'] );
		$this->assertArrayHasKey( 'webp', $settings['formats'] );
		$this->assertArrayHasKey( 'avif', $settings['formats'] );
	}

	/**
	 * Test settings update and retrieval
	 */
	public function test_settings_update_and_retrieval() {
		// Update settings
		$new_settings = array(
			'enabled' => false,
			'formats' => array(
				'webp' => array(
					'enabled' => true,
					'quality' => 90,
				),
				'avif' => array(
					'enabled' => false,
					'quality' => 60,
				),
			),
			'conversion_mode' => 'manual',
		);
		
		$result = WP_Image_Optimizer_Settings_Manager::update_settings( $new_settings );
		$this->assertTrue( $result );
		
		// Get settings
		$settings = WP_Image_Optimizer_Settings_Manager::get_settings();
		
		// Check if settings are updated
		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'enabled', $settings );
		$this->assertFalse( $settings['enabled'] );
		$this->assertArrayHasKey( 'formats', $settings );
		$this->assertIsArray( $settings['formats'] );
		$this->assertArrayHasKey( 'webp', $settings['formats'] );
		$this->assertArrayHasKey( 'avif', $settings['formats'] );
		$this->assertTrue( $settings['formats']['webp']['enabled'] );
		$this->assertEquals( 90, $settings['formats']['webp']['quality'] );
		$this->assertFalse( $settings['formats']['avif']['enabled'] );
		$this->assertEquals( 60, $settings['formats']['avif']['quality'] );
		$this->assertEquals( 'manual', $settings['conversion_mode'] );
	}

	/**
	 * Test settings validation
	 */
	public function test_settings_validation() {
		// Test with invalid settings
		$invalid_settings = array(
			'enabled' => 'not_a_boolean',
			'formats' => 'not_an_array',
			'conversion_mode' => 'invalid_mode',
		);
		
		$result = WP_Image_Optimizer_Settings_Manager::update_settings( $invalid_settings );
		$this->assertFalse( $result );
		
		// Get settings
		$settings = WP_Image_Optimizer_Settings_Manager::get_settings();
		
		// Check if settings are not updated
		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'enabled', $settings );
		$this->assertTrue( $settings['enabled'] );
		$this->assertArrayHasKey( 'formats', $settings );
		$this->assertIsArray( $settings['formats'] );
		$this->assertNotEquals( 'invalid_mode', $settings['conversion_mode'] );
	}

	/**
	 * Test settings reset
	 */
	public function test_settings_reset() {
		// Update settings
		$new_settings = array(
			'enabled' => false,
			'formats' => array(
				'webp' => array(
					'enabled' => false,
					'quality' => 50,
				),
			),
		);
		
		WP_Image_Optimizer_Settings_Manager::update_settings( $new_settings );
		
		// Reset settings
		WP_Image_Optimizer_Settings_Manager::reset_settings();
		
		// Get settings
		$settings = WP_Image_Optimizer_Settings_Manager::get_settings();
		
		// Check if settings are reset to defaults
		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'enabled', $settings );
		$this->assertTrue( $settings['enabled'] );
		$this->assertArrayHasKey( 'formats', $settings );
		$this->assertIsArray( $settings['formats'] );
		$this->assertArrayHasKey( 'webp', $settings['formats'] );
		$this->assertTrue( $settings['formats']['webp']['enabled'] );
		$this->assertNotEquals( 50, $settings['formats']['webp']['quality'] );
	}

	/**
	 * Test settings merge
	 */
	public function test_settings_merge() {
		// Get default settings
		$default_settings = WP_Image_Optimizer_Settings_Manager::get_default_settings();
		
		// Update partial settings
		$partial_settings = array(
			'formats' => array(
				'webp' => array(
					'quality' => 95,
				),
			),
		);
		
		WP_Image_Optimizer_Settings_Manager::update_settings( $partial_settings );
		
		// Get settings
		$settings = WP_Image_Optimizer_Settings_Manager::get_settings();
		
		// Check if settings are merged correctly
		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'enabled', $settings );
		$this->assertEquals( $default_settings['enabled'], $settings['enabled'] );
		$this->assertArrayHasKey( 'formats', $settings );
		$this->assertIsArray( $settings['formats'] );
		$this->assertArrayHasKey( 'webp', $settings['formats'] );
		$this->assertEquals( $default_settings['formats']['webp']['enabled'], $settings['formats']['webp']['enabled'] );
		$this->assertEquals( 95, $settings['formats']['webp']['quality'] );
		$this->assertArrayHasKey( 'avif', $settings['formats'] );
		$this->assertEquals( $default_settings['formats']['avif']['enabled'], $settings['formats']['avif']['enabled'] );
		$this->assertEquals( $default_settings['formats']['avif']['quality'], $settings['formats']['avif']['quality'] );
	}

	/**
	 * Test database initialization
	 */
	public function test_database_initialization() {
		// Initialize database
		$this->db_manager->initialize_database();
		
		// Check if settings option exists
		$this->assertTrue( get_option( 'wp_image_optimizer_settings' ) !== false );
		
		// Check if version option exists
		$this->assertTrue( get_option( 'wp_image_optimizer_db_version' ) !== false );
	}

	/**
	 * Test database cleanup
	 */
	public function test_database_cleanup() {
		// Create test options
		add_option( 'wp_image_optimizer_test_option', 'test_value' );
		add_option( 'wp_image_optimizer_test_transient', 'test_value' );
		
		// Create test transient
		set_transient( 'wp_image_optimizer_test_transient', 'test_value', HOUR_IN_SECONDS );
		
		// Clear all cache
		$this->db_manager->clear_all_cache();
		
		// Check if transient is deleted
		$this->assertFalse( get_transient( 'wp_image_optimizer_test_transient' ) );
		
		// Check if options are not deleted
		$this->assertEquals( 'test_value', get_option( 'wp_image_optimizer_test_option' ) );
		
		// Clean up
		delete_option( 'wp_image_optimizer_test_option' );
	}

	/**
	 * Test database optimization
	 */
	public function test_database_optimization() {
		// Create test metadata
		$attachment_id = $this->factory->attachment->create( array(
			'post_mime_type' => 'image/jpeg',
		) );
		
		// Add test conversion metadata
		$test_metadata = array(
			time() => array(
				'original_path' => '/path/to/image.jpg',
				'conversions' => array( 'webp' => array( 'path' => '/path/to/image.webp' ) ),
				'errors' => array(),
				'space_saved' => 1024,
			),
		);
		update_post_meta( $attachment_id, '_wp_image_optimizer_conversions', $test_metadata );
		
		// Optimize database
		$this->db_manager->optimize_database();
		
		// Check if metadata still exists
		$meta = get_post_meta( $attachment_id, '_wp_image_optimizer_conversions', true );
		$this->assertIsArray( $meta );
		$this->assertEquals( $test_metadata, $meta );
	}

	/**
	 * Test database uninstall cleanup
	 */
	public function test_database_uninstall_cleanup() {
		// Create test options
		add_option( 'wp_image_optimizer_test_option', 'test_value' );
		add_option( 'wp_image_optimizer_settings', array( 'test' => 'value' ) );
		add_option( 'wp_image_optimizer_db_version', '1.0.0' );
		
		// Create test transient
		set_transient( 'wp_image_optimizer_test_transient', 'test_value', HOUR_IN_SECONDS );
		
		// Create test attachment
		$attachment_id = $this->factory->attachment->create( array(
			'post_mime_type' => 'image/jpeg',
		) );
		
		// Add test conversion metadata
		update_post_meta( $attachment_id, '_wp_image_optimizer_conversions', array( 'test' => 'value' ) );
		
		// Perform uninstall cleanup
		$this->db_manager->cleanup_on_uninstall( false );
		
		// Check if options are deleted
		$this->assertFalse( get_option( 'wp_image_optimizer_settings', false ) );
		$this->assertFalse( get_option( 'wp_image_optimizer_db_version', false ) );
		$this->assertFalse( get_option( 'wp_image_optimizer_test_option', false ) );
		
		// Check if transient is deleted
		$this->assertFalse( get_transient( 'wp_image_optimizer_test_transient' ) );
		
		// Check if metadata is deleted
		$meta = get_post_meta( $attachment_id, '_wp_image_optimizer_conversions', true );
		$this->assertEmpty( $meta );
	}

	/**
	 * Test database version upgrade
	 */
	public function test_database_version_upgrade() {
		// Set old version
		update_option( 'wp_image_optimizer_db_version', '0.9.0' );
		
		// Initialize database
		$this->db_manager->initialize_database();
		
		// Check if version is updated
		$version = get_option( 'wp_image_optimizer_db_version' );
		$this->assertNotEquals( '0.9.0', $version );
		$this->assertEquals( WP_IMAGE_OPTIMIZER_VERSION, $version );
	}

	/**
	 * Test settings caching
	 */
	public function test_settings_caching() {
		// Clear settings cache
		wp_cache_delete( 'settings', 'wp_image_optimizer' );
		
		// Get settings (should load from database)
		$settings1 = WP_Image_Optimizer_Settings_Manager::get_settings();
		
		// Check if settings are cached
		$cached_settings = wp_cache_get( 'settings', 'wp_image_optimizer' );
		$this->assertIsArray( $cached_settings );
		$this->assertEquals( $settings1, $cached_settings );
		
		// Update settings directly in database
		update_option( 'wp_image_optimizer_settings', array( 'direct_update' => true ) );
		
		// Get settings again (should use cache)
		$settings2 = WP_Image_Optimizer_Settings_Manager::get_settings();
		
		// Should be the same as before (cached)
		$this->assertEquals( $settings1, $settings2 );
		
		// Clear settings cache
		wp_cache_delete( 'settings', 'wp_image_optimizer' );
		
		// Get settings again (should load from database)
		$settings3 = WP_Image_Optimizer_Settings_Manager::get_settings();
		
		// Should reflect the direct update
		$this->assertArrayHasKey( 'direct_update', $settings3 );
		$this->assertTrue( $settings3['direct_update'] );
	}

	/**
	 * Test database statistics
	 */
	public function test_database_statistics() {
		// Create test attachments
		$attachment_ids = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$attachment_ids[] = $this->factory->attachment->create( array(
				'post_mime_type' => 'image/jpeg',
			) );
		}
		
		// Add test conversion metadata
		foreach ( $attachment_ids as $index => $id ) {
			$test_metadata = array(
				time() => array(
					'original_path' => "/path/to/image$index.jpg",
					'conversions' => array(
						'webp' => array( 'path' => "/path/to/image$index.webp" ),
						'avif' => array( 'path' => "/path/to/image$index.avif" ),
					),
					'errors' => array(),
					'space_saved' => 1024 * ( $index + 1 ),
				),
			);
			update_post_meta( $id, '_wp_image_optimizer_conversions', $test_metadata );
		}
		
		// Get statistics
		$stats = $this->db_manager->get_conversion_statistics();
		
		// Check statistics
		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'total_images', $stats );
		$this->assertArrayHasKey( 'converted_images', $stats );
		$this->assertArrayHasKey( 'total_conversions', $stats );
		$this->assertArrayHasKey( 'space_saved', $stats );
		$this->assertArrayHasKey( 'formats', $stats );
		
		$this->assertEquals( 5, $stats['converted_images'] );
		$this->assertEquals( 10, $stats['total_conversions'] );
		$this->assertEquals( 15360, $stats['space_saved'] );
		$this->assertIsArray( $stats['formats'] );
		$this->assertArrayHasKey( 'webp', $stats['formats'] );
		$this->assertArrayHasKey( 'avif', $stats['formats'] );
		$this->assertEquals( 5, $stats['formats']['webp'] );
		$this->assertEquals( 5, $stats['formats']['avif'] );
	}
}