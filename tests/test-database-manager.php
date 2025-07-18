<?php
/**
 * Tests for Database Manager class
 *
 * @package WP_Image_Optimizer
 */

class Test_Database_Manager extends WP_UnitTestCase {

	/**
	 * Database Manager instance
	 *
	 * @var WP_Image_Optimizer_Database_Manager
	 */
	private $db_manager;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Load required classes
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-database-manager.php';
		
		$this->db_manager = WP_Image_Optimizer_Database_Manager::get_instance();
		
		// Clean up any existing data
		delete_option( 'wp_image_optimizer_settings' );
		delete_option( 'wp_image_optimizer_db_version' );
		$this->db_manager->clear_all_cache();
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		// Clean up test data
		delete_option( 'wp_image_optimizer_settings' );
		delete_option( 'wp_image_optimizer_db_version' );
		$this->db_manager->clear_all_cache();
		
		parent::tearDown();
	}

	/**
	 * Test database initialization
	 */
	public function test_initialize_database() {
		// Test initial database creation
		$result = $this->db_manager->initialize_database();
		$this->assertTrue( $result );
		
		// Verify option was created
		$option_data = get_option( 'wp_image_optimizer_settings' );
		$this->assertIsArray( $option_data );
		$this->assertArrayHasKey( 'version', $option_data );
		$this->assertArrayHasKey( 'settings', $option_data );
		$this->assertArrayHasKey( 'stats', $option_data );
		$this->assertArrayHasKey( 'server_capabilities', $option_data );
		
		// Verify database version was set
		$db_version = get_option( 'wp_image_optimizer_db_version' );
		$this->assertEquals( '1.0.0', $db_version );
		
		// Test that it doesn't overwrite existing data
		$existing_data = array( 'test' => 'value' );
		update_option( 'wp_image_optimizer_settings', $existing_data );
		
		$result = $this->db_manager->initialize_database();
		$this->assertTrue( $result );
		
		$option_data = get_option( 'wp_image_optimizer_settings' );
		$this->assertEquals( $existing_data, $option_data );
	}

	/**
	 * Test database migration
	 */
	public function test_database_migration() {
		// Set up old version data
		$old_data = array(
			'version' => '0.9.0',
			'settings' => array( 'enabled' => true ),
		);
		update_option( 'wp_image_optimizer_settings', $old_data );
		update_option( 'wp_image_optimizer_db_version', '0.9.0' );
		
		// Perform migration
		$result = $this->db_manager->migrate_database( '0.9.0', '1.0.0' );
		$this->assertTrue( $result );
		
		// Verify migration completed
		$migrated_data = get_option( 'wp_image_optimizer_settings' );
		$this->assertArrayHasKey( 'migration_log', $migrated_data );
		$this->assertArrayHasKey( 'stats', $migrated_data );
		$this->assertArrayHasKey( 'server_capabilities', $migrated_data );
		$this->assertEquals( '1.0.0', $migrated_data['db_version'] );
		
		// Verify database version was updated
		$db_version = get_option( 'wp_image_optimizer_db_version' );
		$this->assertEquals( '1.0.0', $db_version );
		
		// Verify migration log
		$migration_log = $migrated_data['migration_log'];
		$this->assertIsArray( $migration_log );
		$this->assertNotEmpty( $migration_log );
		$last_migration = end( $migration_log );
		$this->assertEquals( 'completed', $last_migration['status'] );
		$this->assertEquals( '0.9.0', $last_migration['from_version'] );
		$this->assertEquals( '1.0.0', $last_migration['to_version'] );
	}

	/**
	 * Test caching functionality
	 */
	public function test_caching() {
		$cache_key = 'test_cache';
		$test_data = array( 'test' => 'data', 'number' => 123 );
		
		// Test setting cache
		$result = $this->db_manager->set_cached_data( $cache_key, $test_data, 300 );
		$this->assertTrue( $result );
		
		// Test getting cached data
		$cached_data = $this->db_manager->get_cached_data( $cache_key );
		$this->assertEquals( $test_data, $cached_data );
		
		// Test cache with callback
		$callback_called = false;
		$callback_data = $this->db_manager->get_cached_data( 'new_cache', function() use ( &$callback_called ) {
			$callback_called = true;
			return array( 'generated' => 'data' );
		} );
		
		$this->assertTrue( $callback_called );
		$this->assertEquals( array( 'generated' => 'data' ), $callback_data );
		
		// Test that callback is not called when data is cached
		$callback_called = false;
		$cached_callback_data = $this->db_manager->get_cached_data( 'new_cache', function() use ( &$callback_called ) {
			$callback_called = true;
			return array( 'should_not_be_called' => 'data' );
		} );
		
		$this->assertFalse( $callback_called );
		$this->assertEquals( array( 'generated' => 'data' ), $cached_callback_data );
		
		// Test deleting cache
		$result = $this->db_manager->delete_cached_data( $cache_key );
		$this->assertTrue( $result );
		
		$cached_data = $this->db_manager->get_cached_data( $cache_key );
		$this->assertFalse( $cached_data );
	}

	/**
	 * Test server capabilities caching
	 */
	public function test_server_capabilities_caching() {
		// Test getting server capabilities (should be cached)
		$capabilities1 = $this->db_manager->get_server_capabilities();
		$this->assertIsArray( $capabilities1 );
		$this->assertArrayHasKey( 'imagemagick', $capabilities1 );
		$this->assertArrayHasKey( 'gd', $capabilities1 );
		$this->assertArrayHasKey( 'webp_support', $capabilities1 );
		$this->assertArrayHasKey( 'avif_support', $capabilities1 );
		$this->assertArrayHasKey( 'last_checked', $capabilities1 );
		
		// Test that subsequent calls return cached data
		$capabilities2 = $this->db_manager->get_server_capabilities();
		$this->assertEquals( $capabilities1, $capabilities2 );
		
		// Test force refresh
		$capabilities3 = $this->db_manager->get_server_capabilities( true );
		$this->assertIsArray( $capabilities3 );
		// The structure should be the same, but last_checked might be different
		$this->assertArrayHasKey( 'last_checked', $capabilities3 );
	}

	/**
	 * Test statistics management
	 */
	public function test_statistics_management() {
		// Initialize database first
		$this->db_manager->initialize_database();
		
		// Test updating stats
		$new_stats = array(
			'total_conversions' => 10,
			'space_saved' => 1024000,
		);
		
		$result = $this->db_manager->update_stats( $new_stats );
		$this->assertTrue( $result );
		
		// Test getting stats
		$stats = $this->db_manager->get_stats();
		$this->assertIsArray( $stats );
		$this->assertEquals( 10, $stats['total_conversions'] );
		$this->assertEquals( 1024000, $stats['space_saved'] );
		
		// Test updating additional stats
		$additional_stats = array(
			'conversion_errors' => 2,
			'last_error_time' => current_time( 'mysql' ),
		);
		
		$result = $this->db_manager->update_stats( $additional_stats );
		$this->assertTrue( $result );
		
		$updated_stats = $this->db_manager->get_stats( true ); // Force refresh
		$this->assertEquals( 10, $updated_stats['total_conversions'] );
		$this->assertEquals( 2, $updated_stats['conversion_errors'] );
	}

	/**
	 * Test cached query functionality
	 */
	public function test_cached_query() {
		$query_key = 'test_query';
		$query_executed = false;
		
		$callback = function() use ( &$query_executed ) {
			$query_executed = true;
			return array( 'query' => 'result', 'timestamp' => time() );
		};
		
		// First call should execute the query
		$result1 = $this->db_manager->cached_query( $query_key, $callback, 300 );
		$this->assertTrue( $query_executed );
		$this->assertIsArray( $result1 );
		$this->assertEquals( 'result', $result1['query'] );
		
		// Second call should use cached result
		$query_executed = false;
		$result2 = $this->db_manager->cached_query( $query_key, $callback, 300 );
		$this->assertFalse( $query_executed );
		$this->assertEquals( $result1, $result2 );
	}

	/**
	 * Test database health information
	 */
	public function test_database_health() {
		// Initialize database
		$this->db_manager->initialize_database();
		
		// Add some cache data
		$this->db_manager->set_cached_data( 'test1', array( 'data' => 'value1' ) );
		$this->db_manager->set_cached_data( 'test2', array( 'data' => 'value2' ) );
		
		// Get health information
		$health = $this->db_manager->get_database_health();
		
		$this->assertIsArray( $health );
		$this->assertArrayHasKey( 'option_size', $health );
		$this->assertArrayHasKey( 'cache_count', $health );
		$this->assertArrayHasKey( 'cache_size', $health );
		$this->assertArrayHasKey( 'migration_count', $health );
		$this->assertArrayHasKey( 'last_migration', $health );
		
		$this->assertGreaterThan( 0, $health['option_size'] );
		$this->assertGreaterThanOrEqual( 2, $health['cache_count'] );
	}

	/**
	 * Test database optimization
	 */
	public function test_database_optimization() {
		// Initialize database
		$this->db_manager->initialize_database();
		
		// Add some expired cache data manually
		global $wpdb;
		$expired_time = time() - 3600; // 1 hour ago
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name' => '_transient_timeout_wp_img_opt_expired_test',
				'option_value' => $expired_time,
			)
		);
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name' => '_transient_wp_img_opt_expired_test',
				'option_value' => 'expired_data',
			)
		);
		
		// Add migration logs to test cleanup
		$option_data = get_option( 'wp_image_optimizer_settings' );
		$option_data['migration_log'] = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$option_data['migration_log'][] = array(
				'timestamp' => current_time( 'mysql' ),
				'from_version' => '0.' . $i . '.0',
				'to_version' => '0.' . ( $i + 1 ) . '.0',
				'status' => 'completed',
			);
		}
		update_option( 'wp_image_optimizer_settings', $option_data );
		
		// Run optimization
		$results = $this->db_manager->optimize_database();
		
		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'cleaned_cache', $results );
		$this->assertArrayHasKey( 'cleaned_logs', $results );
		$this->assertArrayHasKey( 'optimized_option', $results );
		
		// Verify migration logs were cleaned up
		$optimized_data = get_option( 'wp_image_optimizer_settings' );
		$this->assertLessThanOrEqual( 5, count( $optimized_data['migration_log'] ) );
	}

	/**
	 * Test cleanup on uninstall
	 */
	public function test_cleanup_on_uninstall() {
		// Initialize database
		$this->db_manager->initialize_database();
		
		// Add some cache data
		$this->db_manager->set_cached_data( 'test_cleanup', array( 'data' => 'value' ) );
		
		// Set activation flag
		add_option( 'wp_image_optimizer_activated', true );
		
		// Verify data exists before cleanup
		$this->assertNotFalse( get_option( 'wp_image_optimizer_settings' ) );
		$this->assertNotFalse( get_option( 'wp_image_optimizer_db_version' ) );
		$this->assertNotFalse( get_option( 'wp_image_optimizer_activated' ) );
		$this->assertNotFalse( $this->db_manager->get_cached_data( 'test_cleanup' ) );
		
		// Perform cleanup
		$result = $this->db_manager->cleanup_on_uninstall( false );
		$this->assertTrue( $result );
		
		// Verify data was removed
		$this->assertFalse( get_option( 'wp_image_optimizer_settings' ) );
		$this->assertFalse( get_option( 'wp_image_optimizer_db_version' ) );
		$this->assertFalse( get_option( 'wp_image_optimizer_activated' ) );
		$this->assertFalse( $this->db_manager->get_cached_data( 'test_cleanup' ) );
	}

	/**
	 * Test cache clearing
	 */
	public function test_clear_all_cache() {
		// Set multiple cache entries
		$this->db_manager->set_cached_data( 'cache1', array( 'data' => 'value1' ) );
		$this->db_manager->set_cached_data( 'cache2', array( 'data' => 'value2' ) );
		$this->db_manager->set_cached_data( 'cache3', array( 'data' => 'value3' ) );
		
		// Verify cache exists
		$this->assertNotFalse( $this->db_manager->get_cached_data( 'cache1' ) );
		$this->assertNotFalse( $this->db_manager->get_cached_data( 'cache2' ) );
		$this->assertNotFalse( $this->db_manager->get_cached_data( 'cache3' ) );
		
		// Clear all cache
		$this->db_manager->clear_all_cache();
		
		// Verify cache was cleared
		$this->assertFalse( $this->db_manager->get_cached_data( 'cache1' ) );
		$this->assertFalse( $this->db_manager->get_cached_data( 'cache2' ) );
		$this->assertFalse( $this->db_manager->get_cached_data( 'cache3' ) );
	}

	/**
	 * Test error handling in migration
	 */
	public function test_migration_error_handling() {
		// Set up invalid data that might cause migration issues
		update_option( 'wp_image_optimizer_settings', 'invalid_data' );
		update_option( 'wp_image_optimizer_db_version', '0.9.0' );
		
		// Migration should handle the error gracefully
		$result = $this->db_manager->migrate_database( '0.9.0', '1.0.0' );
		
		// Even with invalid data, migration should attempt to fix the structure
		$this->assertTrue( $result );
		
		// Verify the data structure was corrected
		$migrated_data = get_option( 'wp_image_optimizer_settings' );
		$this->assertIsArray( $migrated_data );
		$this->assertArrayHasKey( 'settings', $migrated_data );
	}

	/**
	 * Test singleton pattern
	 */
	public function test_singleton_pattern() {
		$instance1 = WP_Image_Optimizer_Database_Manager::get_instance();
		$instance2 = WP_Image_Optimizer_Database_Manager::get_instance();
		
		$this->assertSame( $instance1, $instance2 );
	}
}