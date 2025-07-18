<?php
/**
 * WP-CLI Database Commands class
 *
 * Provides WP-CLI integration for database optimization and management
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Image Optimizer Database CLI Commands
 */
class WP_Image_Optimizer_CLI_Commands_DB extends WP_CLI_Command {

	/**
	 * Database manager instance
	 *
	 * @var WP_Image_Optimizer_Database_Manager
	 */
	private $db_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize required classes
		$this->db_manager = WP_Image_Optimizer_Database_Manager::get_instance();
	}

	/**
	 * Optimize the database by cleaning up expired transients and old logs
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would be optimized without actually making changes
	 *
	 * ## EXAMPLES
	 *
	 *     wp image-optimizer db optimize
	 *     wp image-optimizer db optimize --dry-run
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Named arguments
	 */
	public function optimize( $args, $assoc_args ) {
		$dry_run = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		if ( $dry_run ) {
			WP_CLI::log( 'DRY RUN MODE - No changes will be made' );
			
			// Get database health information
			$health = $this->db_manager->get_database_health();
			
			WP_CLI::log( sprintf( 'Plugin option size: %s', $this->format_bytes( $health['option_size'] ) ) );
			WP_CLI::log( sprintf( 'Cache entries: %d', $health['cache_count'] ) );
			WP_CLI::log( sprintf( 'Cache size: %s', $this->format_bytes( $health['cache_size'] ) ) );
			WP_CLI::log( sprintf( 'Migration log entries: %d', $health['migration_count'] ) );
			
			WP_CLI::log( 'The following optimizations would be performed:' );
			WP_CLI::log( '- Clean expired transients' );
			WP_CLI::log( '- Trim migration logs to last 5 entries' );
			WP_CLI::log( '- Compress statistics data' );
			
			return;
		}

		// Run optimization
		$results = $this->db_manager->optimize_database();
		
		WP_CLI::success( sprintf( 
			'Database optimized successfully. Cleaned %d expired cache entries and %d log entries.',
			$results['cleaned_cache'],
			$results['cleaned_logs']
		) );
		
		if ( $results['compressed_data'] ) {
			WP_CLI::log( 'Statistics data was compressed for better performance.' );
		}
	}

	/**
	 * Show database health information
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format (table, json, yaml). Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp image-optimizer db status
	 *     wp image-optimizer db status --format=json
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Named arguments
	 */
	public function status( $args, $assoc_args ) {
		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		
		// Get database health information
		$health = $this->db_manager->get_database_health();
		
		// Get database size information
		$size_info = $this->db_manager->get_database_size_info();
		
		// Combine data for display
		$status_data = array_merge( $health, $size_info );
		
		// Format byte values for display
		if ( 'table' === $format ) {
			$status_data['option_size'] = $this->format_bytes( $status_data['option_size'] );
			$status_data['cache_size'] = $this->format_bytes( $status_data['cache_size'] );
			$status_data['plugin_data_size'] = $this->format_bytes( $status_data['plugin_data_size'] );
			$status_data['total_transients_size'] = $this->format_bytes( $status_data['total_transients_size'] );
			$status_data['plugin_transients_size'] = $this->format_bytes( $status_data['plugin_transients_size'] );
		}
		
		if ( 'table' === $format ) {
			WP_CLI\Utils\format_items( 'table', array( $status_data ), array_keys( $status_data ) );
		} else {
			WP_CLI::print_value( $status_data, $assoc_args );
		}
	}

	/**
	 * Export plugin data for backup
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : Path to output file. Default: wp-image-optimizer-backup-{date}.json
	 *
	 * ## EXAMPLES
	 *
	 *     wp image-optimizer db export
	 *     wp image-optimizer db export --file=my-backup.json
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Named arguments
	 */
	public function export( $args, $assoc_args ) {
		$file = WP_CLI\Utils\get_flag_value( 
			$assoc_args, 
			'file', 
			'wp-image-optimizer-backup-' . date( 'Y-m-d' ) . '.json'
		);
		
		// Export data
		$export_data = $this->db_manager->export_plugin_data();
		
		// Write to file
		$result = file_put_contents( $file, wp_json_encode( $export_data, JSON_PRETTY_PRINT ) );
		
		if ( false === $result ) {
			WP_CLI::error( sprintf( 'Failed to write to file: %s', $file ) );
		}
		
		WP_CLI::success( sprintf( 
			'Plugin data exported successfully to %s (%s)',
			$file,
			$this->format_bytes( filesize( $file ) )
		) );
	}

	/**
	 * Import plugin data from backup
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to input file
	 *
	 * [--yes]
	 * : Skip confirmation prompt
	 *
	 * ## EXAMPLES
	 *
	 *     wp image-optimizer db import backup.json
	 *     wp image-optimizer db import backup.json --yes
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Named arguments
	 */
	public function import( $args, $assoc_args ) {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'File path is required.' );
		}
		
		$file = $args[0];
		$skip_confirmation = WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false );
		
		// Check if file exists
		if ( ! file_exists( $file ) ) {
			WP_CLI::error( sprintf( 'File not found: %s', $file ) );
		}
		
		// Read file
		$json_data = file_get_contents( $file );
		if ( false === $json_data ) {
			WP_CLI::error( sprintf( 'Failed to read file: %s', $file ) );
		}
		
		// Parse JSON
		$import_data = json_decode( $json_data, true );
		if ( null === $import_data ) {
			WP_CLI::error( 'Invalid JSON data in file.' );
		}
		
		// Confirm import
		if ( ! $skip_confirmation ) {
			WP_CLI::confirm( sprintf( 
				'Are you sure you want to import data from %s? This will overwrite your current settings.',
				$file
			) );
		}
		
		// Import data
		$result = $this->db_manager->import_plugin_data( $import_data );
		
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		
		WP_CLI::success( 'Plugin data imported successfully.' );
	}

	/**
	 * Format bytes to human-readable format
	 *
	 * @param int $bytes Number of bytes
	 * @param int $precision Precision of formatting
	 * @return string Formatted size
	 */
	private function format_bytes( $bytes, $precision = 2 ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		
		$bytes = max( $bytes, 0 );
		$pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow = min( $pow, count( $units ) - 1 );
		
		$bytes /= pow( 1024, $pow );
		
		return round( $bytes, $precision ) . ' ' . $units[ $pow ];
	}
}