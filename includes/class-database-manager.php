<?php
/**
 * Database Manager class
 *
 * Handles database optimization, caching, and migration for the plugin
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database Manager class
 */
class WP_Image_Optimizer_Database_Manager {

	/**
	 * Plugin option name
	 *
	 * @var string
	 */
	const OPTION_NAME = 'wp_image_optimizer_settings';

	/**
	 * Database version option name
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'wp_image_optimizer_db_version';

	/**
	 * Current database version
	 *
	 * @var string
	 */
	const CURRENT_DB_VERSION = '1.0.0';

	/**
	 * Transient cache prefix
	 *
	 * @var string
	 */
	const CACHE_PREFIX = 'wp_img_opt_';

	/**
	 * Default cache expiration (1 hour)
	 *
	 * @var int
	 */
	const DEFAULT_CACHE_EXPIRATION = 3600;

	/**
	 * Instance of this class
	 *
	 * @var WP_Image_Optimizer_Database_Manager|null
	 */
	private static $instance = null;

	/**
	 * Get instance (Singleton pattern)
	 *
	 * @return WP_Image_Optimizer_Database_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Check for database migrations on init
		add_action( 'init', array( $this, 'maybe_migrate_database' ) );
	}

	/**
	 * Initialize database schema
	 *
	 * Creates the minimal database structure using single options entry
	 *
	 * @return bool True on success, false on failure
	 */
	public function initialize_database() {
		$default_data = $this->get_default_database_structure();
		
		// Only create if option doesn't exist
		if ( false === get_option( self::OPTION_NAME ) ) {
			$result = add_option( self::OPTION_NAME, $default_data );
			
			if ( $result ) {
				// Set database version
				add_option( self::DB_VERSION_OPTION, self::CURRENT_DB_VERSION );
				
				// Log successful initialization
				error_log( 'WP Image Optimizer: Database initialized successfully' );
			}
			
			return $result;
		}
		
		return true;
	}

	/**
	 * Get default database structure
	 *
	 * @return array Default database structure
	 */
	private function get_default_database_structure() {
		return array(
			'version' => WP_IMAGE_OPTIMIZER_VERSION,
			'db_version' => self::CURRENT_DB_VERSION,
			'settings' => array(
				'enabled' => true,
				'formats' => array(
					'webp' => array( 'enabled' => true, 'quality' => 80 ),
					'avif' => array( 'enabled' => true, 'quality' => 75 ),
				),
				'conversion_mode' => 'auto',
				'preserve_originals' => true,
				'max_file_size' => 10485760, // 10MB
				'allowed_mime_types' => array( 'image/jpeg', 'image/png', 'image/gif' ),
				'server_config_type' => 'nginx',
			),
			'cli_settings' => array(),
			'stats' => array(
				'total_conversions' => 0,
				'space_saved' => 0,
				'last_batch_run' => null,
				'conversion_errors' => 0,
				'last_error_time' => null,
			),
			'server_capabilities' => array(
				'imagemagick' => false,
				'gd' => false,
				'webp_support' => false,
				'avif_support' => false,
				'last_checked' => null,
			),
			'migration_log' => array(),
		);
	}

	/**
	 * Check if database migration is needed and perform it
	 */
	public function maybe_migrate_database() {
		$current_db_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );
		
		if ( version_compare( $current_db_version, self::CURRENT_DB_VERSION, '<' ) ) {
			$this->migrate_database( $current_db_version, self::CURRENT_DB_VERSION );
		}
	}

	/**
	 * Migrate database from one version to another
	 *
	 * @param string $from_version Current database version
	 * @param string $to_version Target database version
	 * @return bool True on success, false on failure
	 */
	public function migrate_database( $from_version, $to_version ) {
		$migration_log = array(
			'timestamp' => current_time( 'mysql' ),
			'from_version' => $from_version,
			'to_version' => $to_version,
			'status' => 'started',
			'steps' => array(),
		);

		try {
			// Get current data
			$current_data = get_option( self::OPTION_NAME, array() );
			$default_structure = $this->get_default_database_structure();
			
			// Perform version-specific migrations
			$migrated_data = $this->perform_version_migrations( $current_data, $from_version, $to_version );
			
			// Merge with default structure to ensure all fields exist
			$final_data = $this->merge_with_defaults( $migrated_data, $default_structure );
			
			// Update database version in the data
			$final_data['db_version'] = $to_version;
			
			// Add migration log entry
			if ( ! isset( $final_data['migration_log'] ) ) {
				$final_data['migration_log'] = array();
			}
			
			$migration_log['status'] = 'completed';
			$migration_log['steps'][] = 'Data structure updated';
			$final_data['migration_log'][] = $migration_log;
			
			// Keep only last 10 migration log entries
			if ( count( $final_data['migration_log'] ) > 10 ) {
				$final_data['migration_log'] = array_slice( $final_data['migration_log'], -10 );
			}
			
			// Update the option
			$result = update_option( self::OPTION_NAME, $final_data );
			
			if ( $result ) {
				// Update database version
				update_option( self::DB_VERSION_OPTION, $to_version );
				
				// Clear all plugin caches after migration
				$this->clear_all_cache();
				
				error_log( "WP Image Optimizer: Database migrated from {$from_version} to {$to_version}" );
			}
			
			return $result;
			
		} catch ( Exception $e ) {
			$migration_log['status'] = 'failed';
			$migration_log['error'] = $e->getMessage();
			
			// Try to log the failed migration
			$current_data = get_option( self::OPTION_NAME, array() );
			if ( ! isset( $current_data['migration_log'] ) ) {
				$current_data['migration_log'] = array();
			}
			$current_data['migration_log'][] = $migration_log;
			update_option( self::OPTION_NAME, $current_data );
			
			error_log( "WP Image Optimizer: Database migration failed: " . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Perform version-specific migrations
	 *
	 * @param array  $data Current data
	 * @param string $from_version Source version
	 * @param string $to_version Target version
	 * @return array Migrated data
	 */
	private function perform_version_migrations( $data, $from_version, $to_version ) {
		// Future version-specific migrations will be added here
		// For now, just return the data as-is since this is the initial version
		
		// Example of how future migrations would work:
		// if ( version_compare( $from_version, '1.1.0', '<' ) && version_compare( $to_version, '1.1.0', '>=' ) ) {
		//     $data = $this->migrate_to_1_1_0( $data );
		// }
		
		return $data;
	}

	/**
	 * Merge data with default structure
	 *
	 * @param array $data Current data
	 * @param array $defaults Default structure
	 * @return array Merged data
	 */
	private function merge_with_defaults( $data, $defaults ) {
		foreach ( $defaults as $key => $default_value ) {
			if ( ! isset( $data[ $key ] ) ) {
				$data[ $key ] = $default_value;
			} elseif ( is_array( $default_value ) && is_array( $data[ $key ] ) ) {
				$data[ $key ] = $this->merge_with_defaults( $data[ $key ], $default_value );
			}
		}
		
		return $data;
	}

	/**
	 * Get cached data with fallback
	 *
	 * @param string   $key Cache key
	 * @param callable $callback Callback to generate data if not cached
	 * @param int      $expiration Cache expiration in seconds
	 * @return mixed Cached or generated data
	 */
	public function get_cached_data( $key, $callback = null, $expiration = null ) {
		if ( null === $expiration ) {
			$expiration = self::DEFAULT_CACHE_EXPIRATION;
		}
		
		$cache_key = self::CACHE_PREFIX . $key;
		$cached_data = get_transient( $cache_key );
		
		if ( false !== $cached_data ) {
			return $cached_data;
		}
		
		// If no callback provided, return false
		if ( null === $callback || ! is_callable( $callback ) ) {
			return false;
		}
		
		// Generate fresh data
		$fresh_data = call_user_func( $callback );
		
		// Cache the data
		set_transient( $cache_key, $fresh_data, $expiration );
		
		return $fresh_data;
	}

	/**
	 * Set cached data
	 *
	 * @param string $key Cache key
	 * @param mixed  $data Data to cache
	 * @param int    $expiration Cache expiration in seconds
	 * @return bool True on success, false on failure
	 */
	public function set_cached_data( $key, $data, $expiration = null ) {
		if ( null === $expiration ) {
			$expiration = self::DEFAULT_CACHE_EXPIRATION;
		}
		
		$cache_key = self::CACHE_PREFIX . $key;
		return set_transient( $cache_key, $data, $expiration );
	}

	/**
	 * Delete cached data
	 *
	 * @param string $key Cache key
	 * @return bool True on success, false on failure
	 */
	public function delete_cached_data( $key ) {
		$cache_key = self::CACHE_PREFIX . $key;
		return delete_transient( $cache_key );
	}

	/**
	 * Clear all plugin cache
	 */
	public function clear_all_cache() {
		global $wpdb;
		
		// Delete all transients with our prefix
		$wpdb->query( 
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_' . self::CACHE_PREFIX . '%',
				'_transient_timeout_' . self::CACHE_PREFIX . '%'
			)
		);
		
		// Clear object cache if available
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'wp_image_optimizer' );
		}
	}

	/**
	 * Get server capabilities with caching
	 *
	 * @param bool $force_refresh Force refresh of cached data
	 * @return array Server capabilities
	 */
	public function get_server_capabilities( $force_refresh = false ) {
		if ( $force_refresh ) {
			$this->delete_cached_data( 'server_capabilities' );
		}
		
		return $this->get_cached_data( 'server_capabilities', array( $this, 'detect_server_capabilities' ), 3600 );
	}

	/**
	 * Detect server capabilities
	 *
	 * @return array Server capabilities
	 */
	public function detect_server_capabilities() {
		$capabilities = array(
			'imagemagick' => false,
			'gd' => false,
			'webp_support' => false,
			'avif_support' => false,
			'last_checked' => current_time( 'mysql' ),
		);
		
		// Check ImageMagick
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			$capabilities['imagemagick'] = true;
			
			// Check ImageMagick format support
			$imagick = new Imagick();
			$formats = $imagick->queryFormats();
			$capabilities['webp_support'] = in_array( 'WEBP', $formats, true );
			$capabilities['avif_support'] = in_array( 'AVIF', $formats, true );
		}
		
		// Check GD
		if ( extension_loaded( 'gd' ) && function_exists( 'gd_info' ) ) {
			$capabilities['gd'] = true;
			
			// Check GD format support
			$gd_info = gd_info();
			if ( ! $capabilities['webp_support'] ) {
				$capabilities['webp_support'] = isset( $gd_info['WebP Support'] ) && $gd_info['WebP Support'];
			}
			// GD doesn't support AVIF natively
		}
		
		return $capabilities;
	}

	/**
	 * Update plugin statistics
	 *
	 * @param array $stats Statistics to update
	 * @return bool True on success, false on failure
	 */
	public function update_stats( $stats ) {
		$current_data = get_option( self::OPTION_NAME, array() );
		
		if ( ! isset( $current_data['stats'] ) ) {
			$current_data['stats'] = array();
		}
		
		// Merge stats
		$current_data['stats'] = array_merge( $current_data['stats'], $stats );
		
		// Update option
		$result = update_option( self::OPTION_NAME, $current_data );
		
		// Clear stats cache
		if ( $result ) {
			$this->delete_cached_data( 'stats' );
		}
		
		return $result;
	}

	/**
	 * Get plugin statistics with caching
	 *
	 * @param bool $force_refresh Force refresh of cached data
	 * @return array Plugin statistics
	 */
	public function get_stats( $force_refresh = false ) {
		if ( $force_refresh ) {
			$this->delete_cached_data( 'stats' );
		}
		
		return $this->get_cached_data( 'stats', function() {
			$data = get_option( self::OPTION_NAME, array() );
			return isset( $data['stats'] ) ? $data['stats'] : array();
		}, 300 ); // Cache for 5 minutes
	}

	/**
	 * Optimize database queries by using WordPress object cache
	 *
	 * @param string $query_key Unique key for the query
	 * @param callable $query_callback Callback that executes the query
	 * @param int $cache_time Cache time in seconds
	 * @return mixed Query result
	 */
	public function cached_query( $query_key, $query_callback, $cache_time = 300 ) {
		$cache_key = 'query_' . md5( $query_key );
		
		// Try to get from object cache first (fastest)
		$cached_result = wp_cache_get( $cache_key, 'wp_image_optimizer' );
		if ( false !== $cached_result ) {
			return $cached_result;
		}
		
		// Try transient cache (slower but persistent)
		$result = $this->get_cached_data( $cache_key, $query_callback, $cache_time );
		
		// Store in object cache for this request
		wp_cache_set( $cache_key, $result, 'wp_image_optimizer', $cache_time );
		
		return $result;
	}
	
	/**
	 * Prepare and cache database query
	 * 
	 * @param string $query SQL query with placeholders
	 * @param array $args Query arguments
	 * @param string $cache_key Cache key
	 * @param int $cache_time Cache time in seconds
	 * @param bool $single Whether to return a single row
	 * @return mixed Query results
	 */
	public function prepare_cached_query( $query, $args = array(), $cache_key = '', $cache_time = 300, $single = false ) {
		global $wpdb;
		
		if ( empty( $cache_key ) ) {
			$cache_key = 'query_' . md5( $query . wp_json_encode( $args ) );
		}
		
		return $this->cached_query( $cache_key, function() use ( $wpdb, $query, $args, $single ) {
			if ( ! empty( $args ) ) {
				$prepared_query = $wpdb->prepare( $query, $args );
			} else {
				$prepared_query = $query;
			}
			
			if ( $single ) {
				return $wpdb->get_row( $prepared_query );
			} else {
				return $wpdb->get_results( $prepared_query );
			}
		}, $cache_time );
	}
	
	/**
	 * Get cached attachment metadata
	 * 
	 * @param int $attachment_id Attachment ID
	 * @return array|false Attachment metadata or false if not found
	 */
	public function get_cached_attachment_metadata( $attachment_id ) {
		$cache_key = 'attachment_meta_' . $attachment_id;
		
		return $this->cached_query( $cache_key, function() use ( $attachment_id ) {
			return wp_get_attachment_metadata( $attachment_id );
		}, 600 ); // Cache for 10 minutes
	}
	
	/**
	 * Invalidate cached attachment metadata
	 * 
	 * @param int $attachment_id Attachment ID
	 * @return bool True on success
	 */
	public function invalidate_attachment_metadata_cache( $attachment_id ) {
		$cache_key = 'attachment_meta_' . $attachment_id;
		wp_cache_delete( 'query_' . md5( $cache_key ), 'wp_image_optimizer' );
		return $this->delete_cached_data( $cache_key );
	}

	/**
	 * Clean up plugin data on uninstall
	 *
	 * @param bool $remove_converted_images Whether to remove converted images
	 * @return bool True on success, false on failure
	 */
	public function cleanup_on_uninstall( $remove_converted_images = false ) {
		$cleanup_log = array(
			'timestamp' => current_time( 'mysql' ),
			'steps' => array(),
			'errors' => array(),
		);
		
		try {
			// Remove main plugin option
			if ( delete_option( self::OPTION_NAME ) ) {
				$cleanup_log['steps'][] = 'Removed main plugin option';
			}
			
			// Remove database version option
			if ( delete_option( self::DB_VERSION_OPTION ) ) {
				$cleanup_log['steps'][] = 'Removed database version option';
			}
			
			// Remove activation flag
			if ( delete_option( 'wp_image_optimizer_activated' ) ) {
				$cleanup_log['steps'][] = 'Removed activation flag';
			}
			
			// Clear all transients
			$this->clear_all_cache();
			$cleanup_log['steps'][] = 'Cleared all cached data';
			
			// Remove converted images if requested
			if ( $remove_converted_images ) {
				$removed_files = $this->remove_converted_images();
				$cleanup_log['steps'][] = "Removed {$removed_files} converted image files";
			}
			
			// Clear WordPress cache
			wp_cache_flush();
			$cleanup_log['steps'][] = 'Flushed WordPress cache';
			
			// Log successful cleanup
			error_log( 'WP Image Optimizer: Cleanup completed successfully - ' . wp_json_encode( $cleanup_log ) );
			
			return true;
			
		} catch ( Exception $e ) {
			$cleanup_log['errors'][] = $e->getMessage();
			error_log( 'WP Image Optimizer: Cleanup failed - ' . wp_json_encode( $cleanup_log ) );
			return false;
		}
	}

	/**
	 * Remove converted image files
	 *
	 * @return int Number of files removed
	 */
	private function remove_converted_images() {
		$upload_dir = wp_upload_dir();
		$base_dir = $upload_dir['basedir'];
		$removed_count = 0;
		
		if ( ! is_dir( $base_dir ) ) {
			return 0;
		}
		
		// Recursively find and remove .webp and .avif files
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		
		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$extension = strtolower( $file->getExtension() );
				if ( in_array( $extension, array( 'webp', 'avif' ), true ) ) {
					if ( unlink( $file->getPathname() ) ) {
						$removed_count++;
					}
				}
			}
		}
		
		return $removed_count;
	}

	/**
	 * Get database health information
	 *
	 * @return array Database health information
	 */
	public function get_database_health() {
		$health = array(
			'option_size' => 0,
			'cache_count' => 0,
			'cache_size' => 0,
			'migration_count' => 0,
			'last_migration' => null,
		);
		
		// Get option size
		$option_data = get_option( self::OPTION_NAME, array() );
		$health['option_size'] = strlen( serialize( $option_data ) );
		
		// Get cache information
		global $wpdb;
		$cache_query = $wpdb->prepare(
			"SELECT COUNT(*) as count, SUM(LENGTH(option_value)) as size 
			FROM {$wpdb->options} 
			WHERE option_name LIKE %s",
			'_transient_' . self::CACHE_PREFIX . '%'
		);
		$cache_info = $wpdb->get_row( $cache_query );
		if ( $cache_info ) {
			$health['cache_count'] = (int) $cache_info->count;
			$health['cache_size'] = (int) $cache_info->size;
		}
		
		// Get migration information
		if ( isset( $option_data['migration_log'] ) && is_array( $option_data['migration_log'] ) ) {
			$health['migration_count'] = count( $option_data['migration_log'] );
			if ( ! empty( $option_data['migration_log'] ) ) {
				$last_migration = end( $option_data['migration_log'] );
				$health['last_migration'] = $last_migration['timestamp'];
			}
		}
		
		return $health;
	}

	/**
	 * Optimize database by cleaning up old data
	 *
	 * @return array Optimization results
	 */
	public function optimize_database() {
		$results = array(
			'cleaned_cache' => 0,
			'cleaned_logs' => 0,
			'optimized_option' => false,
			'compressed_data' => false,
		);
		
		// Clean expired transients
		global $wpdb;
		$expired_transients = $wpdb->query(
			$wpdb->prepare(
				"DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b 
				WHERE a.option_name LIKE %s 
				AND a.option_name = CONCAT('_transient_timeout_', SUBSTRING(b.option_name, 12))
				AND b.option_name LIKE %s 
				AND a.option_value < %d",
				'_transient_timeout_' . self::CACHE_PREFIX . '%',
				'_transient_' . self::CACHE_PREFIX . '%',
				time()
			)
		);
		$results['cleaned_cache'] = $expired_transients;
		
		// Clean old migration logs (keep only last 5)
		$option_data = get_option( self::OPTION_NAME, array() );
		if ( isset( $option_data['migration_log'] ) && count( $option_data['migration_log'] ) > 5 ) {
			$option_data['migration_log'] = array_slice( $option_data['migration_log'], -5 );
			$results['cleaned_logs'] = 1;
		}
		
		// Compress statistics data to save space
		if ( isset( $option_data['stats'] ) && ! empty( $option_data['stats'] ) ) {
			// Remove any redundant or temporary data
			if ( isset( $option_data['stats']['temp_data'] ) ) {
				unset( $option_data['stats']['temp_data'] );
			}
			
			// Round large numbers to save space
			if ( isset( $option_data['stats']['space_saved'] ) && $option_data['stats']['space_saved'] > 1000 ) {
				$option_data['stats']['space_saved'] = round( $option_data['stats']['space_saved'] / 1000 ) * 1000;
			}
			
			$results['compressed_data'] = true;
		}
		
		// Update the option with optimized data
		$results['optimized_option'] = update_option( self::OPTION_NAME, $option_data );
		
		// Clear any object cache entries
		wp_cache_delete( self::OPTION_NAME, 'options' );
		
		return $results;
	}
	
	/**
	 * Schedule regular database optimization
	 * 
	 * @return bool True if scheduled, false otherwise
	 */
	public function schedule_optimization() {
		// Schedule daily optimization if not already scheduled
		if ( ! wp_next_scheduled( 'wp_image_optimizer_db_optimize' ) ) {
			return wp_schedule_event( time(), 'daily', 'wp_image_optimizer_db_optimize' );
		}
		return false;
	}
	
	/**
	 * Unschedule database optimization
	 */
	public function unschedule_optimization() {
		wp_clear_scheduled_hook( 'wp_image_optimizer_db_optimize' );
	}
	
	/**
	 * Register database optimization hooks
	 */
	public function register_optimization_hooks() {
		// Register the optimization action
		add_action( 'wp_image_optimizer_db_optimize', array( $this, 'optimize_database' ) );
		
		// Schedule optimization if not already scheduled
		$this->schedule_optimization();
	}
	
	/**
	 * Get database size information
	 * 
	 * @return array Database size information
	 */
	public function get_database_size_info() {
		global $wpdb;
		
		$size_info = array(
			'plugin_data_size' => 0,
			'total_transients_size' => 0,
			'plugin_transients_size' => 0,
			'total_options_count' => 0,
			'plugin_options_count' => 1, // Main plugin option
		);
		
		// Get plugin data size
		$option_data = get_option( self::OPTION_NAME, array() );
		$size_info['plugin_data_size'] = strlen( serialize( $option_data ) );
		
		// Get total options count
		$count_query = "SELECT COUNT(*) FROM {$wpdb->options}";
		$size_info['total_options_count'] = (int) $wpdb->get_var( $count_query );
		
		// Get total transients size
		$transients_query = "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'";
		$size_info['total_transients_size'] = (int) $wpdb->get_var( $transients_query );
		
		// Get plugin transients size
		$plugin_transients_query = $wpdb->prepare(
			"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE %s",
			'_transient_' . self::CACHE_PREFIX . '%'
		);
		$size_info['plugin_transients_size'] = (int) $wpdb->get_var( $plugin_transients_query );
		
		return $size_info;
	}
	
	/**
	 * Export plugin data for backup
	 * 
	 * @return array Plugin data for backup
	 */
	public function export_plugin_data() {
		$export_data = array(
			'timestamp' => current_time( 'mysql' ),
			'version' => WP_IMAGE_OPTIMIZER_VERSION,
			'db_version' => self::CURRENT_DB_VERSION,
			'data' => get_option( self::OPTION_NAME, array() ),
		);
		
		return $export_data;
	}
	
	/**
	 * Import plugin data from backup
	 * 
	 * @param array $import_data Data to import
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function import_plugin_data( $import_data ) {
		// Validate import data
		if ( ! is_array( $import_data ) || ! isset( $import_data['data'] ) || ! is_array( $import_data['data'] ) ) {
			return new WP_Error( 'invalid_import_data', __( 'Invalid import data format.', 'wp-image-optimizer' ) );
		}
		
		// Check version compatibility
		if ( isset( $import_data['db_version'] ) && version_compare( $import_data['db_version'], self::CURRENT_DB_VERSION, '>' ) ) {
			return new WP_Error( 
				'incompatible_version', 
				sprintf( 
					__( 'Import data is from a newer version (%s) than current database version (%s).', 'wp-image-optimizer' ),
					$import_data['db_version'],
					self::CURRENT_DB_VERSION
				)
			);
		}
		
		// Backup current data before import
		$current_data = get_option( self::OPTION_NAME, array() );
		$backup_key = 'backup_before_import_' . time();
		$this->set_cached_data( $backup_key, $current_data, DAY_IN_SECONDS );
		
		// Import the data
		$result = update_option( self::OPTION_NAME, $import_data['data'] );
		
		if ( ! $result ) {
			return new WP_Error( 'import_failed', __( 'Failed to import data.', 'wp-image-optimizer' ) );
		}
		
		// Clear all caches
		$this->clear_all_cache();
		
		return true;
	}
}