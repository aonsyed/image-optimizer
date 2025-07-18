<?php
/**
 * WP-CLI Commands class
 *
 * Provides WP-CLI integration for bulk conversion, individual image conversion,
 * and settings management functionality.
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Image Optimizer CLI Commands
 */
class WP_Image_Optimizer_CLI_Commands extends WP_CLI_Command {

	/**
	 * Image converter instance
	 *
	 * @var WP_Image_Optimizer_Image_Converter
	 */
	private $image_converter;

	/**
	 * Settings manager instance
	 *
	 * @var WP_Image_Optimizer_Settings_Manager
	 */
	private $settings_manager;

	/**
	 * File handler instance
	 *
	 * @var WP_Image_Optimizer_File_Handler
	 */
	private $file_handler;

	/**
	 * Batch processor instance
	 *
	 * @var WP_Image_Optimizer_Batch_Processor
	 */
	private $batch_processor;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize required classes
		$this->settings_manager = new WP_Image_Optimizer_Settings_Manager();
		$settings = $this->settings_manager->get_settings();
		$this->file_handler = new WP_Image_Optimizer_File_Handler( $settings );
		$this->image_converter = new WP_Image_Optimizer_Image_Converter( $this->file_handler );
		
		// Get batch processor from main plugin instance
		$plugin = WP_Image_Optimizer::get_instance();
		$this->batch_processor = $plugin->get_batch_processor();
	}

	/**
	 * Convert all existing images to modern formats
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Specific format to convert to (webp, avif). Default: all enabled formats
	 *
	 * [--limit=<number>]
	 * : Maximum number of images to process. Default: no limit
	 *
	 * [--offset=<number>]
	 * : Number of images to skip. Default: 0
	 *
	 * [--force]
	 * : Force reconversion of already converted images
	 *
	 * [--dry-run]
	 * : Show what would be converted without actually converting
	 *
	 * ## EXAMPLES
	 *
	 *     wp image-optimizer convert-all
	 *     wp image-optimizer convert-all --format=webp --limit=100
	 *     wp image-optimizer convert-all --force --dry-run
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Named arguments
	 */
	public function convert_all( $args, $assoc_args ) {
		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );
		$limit = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 0 );
		$offset = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'offset', 0 );
		$force = WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );
		$dry_run = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		// Validate format if specified
		if ( $format && ! in_array( strtolower( $format ), array( 'webp', 'avif' ), true ) ) {
			WP_CLI::error( sprintf( 'Invalid format: %s. Allowed formats: webp, avif', $format ) );
		}

		// Check if conversion is possible
		$can_convert = $this->image_converter->can_convert();
		if ( is_wp_error( $can_convert ) ) {
			WP_CLI::error( $can_convert->get_error_message() );
		}

		WP_CLI::log( 'Starting bulk image conversion...' );
		
		if ( $dry_run ) {
			WP_CLI::log( 'DRY RUN MODE - No files will be converted' );
		}

		// Get all image attachments
		$query_args = array(
			'post_type' => 'attachment',
			'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif' ),
			'post_status' => 'inherit',
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'offset' => $offset,
			'fields' => 'ids',
		);

		$attachments = get_posts( $query_args );
		$total_attachments = count( $attachments );

		if ( empty( $attachments ) ) {
			WP_CLI::success( 'No images found to convert.' );
			return;
		}

		WP_CLI::log( sprintf( 'Found %d images to process', $total_attachments ) );

		// Initialize progress bar
		$progress = WP_CLI\Utils\make_progress_bar( 'Converting images', $total_attachments );

		$stats = array(
			'processed' => 0,
			'converted' => 0,
			'skipped' => 0,
			'errors' => 0,
			'space_saved' => 0,
		);

		foreach ( $attachments as $attachment_id ) {
			$result = $this->convert_single_attachment( $attachment_id, $format, $force, $dry_run );
			
			$stats['processed']++;
			
			if ( is_wp_error( $result ) ) {
				$stats['errors']++;
				WP_CLI::debug( sprintf( 'Error converting attachment %d: %s', $attachment_id, $result->get_error_message() ) );
			} elseif ( isset( $result['skipped'] ) && $result['skipped'] ) {
				$stats['skipped']++;
			} else {
				$stats['converted']++;
				if ( isset( $result['space_saved'] ) ) {
					$stats['space_saved'] += $result['space_saved'];
				}
			}

			$progress->tick();
		}

		$progress->finish();

		// Display final statistics
		WP_CLI::success( sprintf(
			'Bulk conversion completed. Processed: %d, Converted: %d, Skipped: %d, Errors: %d, Space saved: %s',
			$stats['processed'],
			$stats['converted'],
			$stats['skipped'],
			$stats['errors'],
			$this->format_bytes( $stats['space_saved'] )
		) );
	}

	/**
	 * Convert a specific image by attachment ID
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Attachment ID to convert
	 *
	 * [--format=<format>]
	 * : Specific format to convert to (webp, avif). Default: all enabled formats
	 *
	 * [--force]
	 * : Force reconversion of already converted images
	 *
	 * [--dry-run]
	 * : Show what would be converted without actually converting
	 *
	 * ## EXAMPLES
	 *
	 *     wp image-optimizer convert-id 123
	 *     wp image-optimizer convert-id 123 --format=webp
	 *     wp image-optimizer convert-id 123 --force
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Named arguments
	 */
	public function convert_id( $args, $assoc_args ) {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Attachment ID is required.' );
		}

		$attachment_id = (int) $args[0];
		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );
		$force = WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );
		$dry_run = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		// Validate attachment exists
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			WP_CLI::error( sprintf( 'Attachment %d is not a valid image.', $attachment_id ) );
		}

		// Validate format if specified
		if ( $format && ! in_array( strtolower( $format ), array( 'webp', 'avif' ), true ) ) {
			WP_CLI::error( sprintf( 'Invalid format: %s. Allowed formats: webp, avif', $format ) );
		}

		WP_CLI::log( sprintf( 'Converting attachment %d...', $attachment_id ) );

		$result = $this->convert_single_attachment( $attachment_id, $format, $force, $dry_run );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		if ( isset( $result['skipped'] ) && $result['skipped'] ) {
			WP_CLI::success( sprintf( 'Attachment %d was skipped: %s', $attachment_id, $result['reason'] ) );
		} else {
			$space_saved = isset( $result['space_saved'] ) ? $this->format_bytes( $result['space_saved'] ) : '0 bytes';
			WP_CLI::success( sprintf( 'Attachment %d converted successfully. Space saved: %s', $attachment_id, $space_saved ) );
		}
	}

	/**
	 * Convert a specific image by file path
	 *
	 * ## OPTIONS
	 *
	 * <path>
	 * : Path to image file (relative to uploads directory or absolute)
	 *
	 * [--format=<format>]
	 * : Specific format to convert to (webp, avif). Default: all enabled formats
	 *
	 * [--force]
	 * : Force reconversion of already converted images
	 *
	 * [--dry-run]
	 * : Show what would be converted without actually converting
	 *
	 * ## EXAMPLES
	 *
	 *     wp image-optimizer convert-path 2024/01/image.jpg
	 *     wp image-optimizer convert-path /var/www/wp-content/uploads/2024/01/image.jpg --format=webp
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Named arguments
	 */
	public function convert_path( $args, $assoc_args ) {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Image path is required.' );
		}

		$path = $args[0];
		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );
		$force = WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );
		$dry_run = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		// Resolve path
		$resolved_path = $this->resolve_image_path( $path );
		if ( is_wp_error( $resolved_path ) ) {
			WP_CLI::error( $resolved_path->get_error_message() );
		}

		// Validate format if specified
		if ( $format && ! in_array( strtolower( $format ), array( 'webp', 'avif' ), true ) ) {
			WP_CLI::error( sprintf( 'Invalid format: %s. Allowed formats: webp, avif', $format ) );
		}

		WP_CLI::log( sprintf( 'Converting image: %s', $resolved_path ) );

		if ( $dry_run ) {
			WP_CLI::log( 'DRY RUN MODE - File will not be converted' );
			$this->show_conversion_preview( $resolved_path, $format );
			return;
		}

		$result = $this->convert_image_by_path( $resolved_path, $format, $force );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		if ( isset( $result['skipped'] ) && $result['skipped'] ) {
			WP_CLI::success( sprintf( 'Image was skipped: %s', $result['reason'] ) );
		} else {
			$space_saved = isset( $result['space_saved'] ) ? $this->format_bytes( $result['space_saved'] ) : '0 bytes';
			WP_CLI::success( sprintf( 'Image converted successfully. Space saved: %s', $space_saved ) );
		}
	}

	/**

	 * Get current plugin settings
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format (table, json, yaml). Default: table
	 *
	 * [--field=<field>]
	 * : Get specific setting field (supports dot notation)
	 *
	 * ## EXAMPLES
	 *
	 *     wp image-optimizer get-settings
	 *     wp image-optimizer get-settings --format=json
	 *     wp image-optimizer get-settings --field=formats.webp.quality
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Named arguments
	 */
	public function get_settings( $args, $assoc_args ) {
		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$field = WP_CLI\Utils\get_flag_value( $assoc_args, 'field' );

		$settings = $this->settings_manager->get_settings();

		if ( $field ) {
			$value = $this->settings_manager->get_setting( $field );
			if ( null === $value ) {
				WP_CLI::error( sprintf( 'Setting field "%s" not found.', $field ) );
			}
			WP_CLI::print_value( $value, $assoc_args );
			return;
		}

		if ( 'table' === $format ) {
			$this->display_settings_table( $settings );
		} else {
			WP_CLI::print_value( $settings, $assoc_args );
		}
	}

	/**
	 * Update plugin settings
	 *
	 * ## OPTIONS
	 *
	 * [--enabled=<boolean>]
	 * : Enable or disable image conversion
	 *
	 * [--conversion-mode=<mode>]
	 * : Conversion mode (auto, manual, cli_only)
	 *
	 * [--webp-enabled=<boolean>]
	 * : Enable or disable WebP conversion
	 *
	 * [--webp-quality=<number>]
	 * : WebP quality (1-100)
	 *
	 * [--avif-enabled=<boolean>]
	 * : Enable or disable AVIF conversion
	 *
	 * [--avif-quality=<number>]
	 * : AVIF quality (1-100)
	 *
	 * [--max-file-size=<bytes>]
	 * : Maximum file size for conversion in bytes
	 *
	 * [--preserve-originals=<boolean>]
	 * : Whether to preserve original images
	 *
	 * [--server-config-type=<type>]
	 * : Server configuration type (nginx, apache, none)
	 *
	 * ## EXAMPLES
	 *
	 *     wp image-optimizer set-settings --enabled=true --webp-quality=85
	 *     wp image-optimizer set-settings --conversion-mode=cli_only
	 *     wp image-optimizer set-settings --avif-enabled=false
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Named arguments
	 */
	public function set_settings( $args, $assoc_args ) {
		$updates = array();

		// Map CLI arguments to settings structure
		$setting_map = array(
			'enabled' => 'enabled',
			'conversion-mode' => 'conversion_mode',
			'preserve-originals' => 'preserve_originals',
			'max-file-size' => 'max_file_size',
			'server-config-type' => 'server_config_type',
		);

		foreach ( $setting_map as $cli_key => $setting_key ) {
			if ( isset( $assoc_args[ $cli_key ] ) ) {
				$value = $assoc_args[ $cli_key ];
				
				// Convert string booleans
				if ( in_array( $cli_key, array( 'enabled', 'preserve-originals' ), true ) ) {
					$value = $this->parse_boolean( $value );
				} elseif ( 'max-file-size' === $cli_key ) {
					$value = (int) $value;
				}
				
				$updates[ $setting_key ] = $value;
			}
		}

		// Handle format-specific settings
		$format_settings = array();
		
		if ( isset( $assoc_args['webp-enabled'] ) ) {
			$format_settings['webp']['enabled'] = $this->parse_boolean( $assoc_args['webp-enabled'] );
		}
		
		if ( isset( $assoc_args['webp-quality'] ) ) {
			$format_settings['webp']['quality'] = (int) $assoc_args['webp-quality'];
		}
		
		if ( isset( $assoc_args['avif-enabled'] ) ) {
			$format_settings['avif']['enabled'] = $this->parse_boolean( $assoc_args['avif-enabled'] );
		}
		
		if ( isset( $assoc_args['avif-quality'] ) ) {
			$format_settings['avif']['quality'] = (int) $assoc_args['avif-quality'];
		}

		if ( ! empty( $format_settings ) ) {
			$current_formats = $this->settings_manager->get_setting( 'formats', array() );
			$updates['formats'] = array_replace_recursive( $current_formats, $format_settings );
		}

		if ( empty( $updates ) ) {
			WP_CLI::error( 'No settings provided to update.' );
		}

		// Update CLI settings (these take precedence over UI settings)
		$result = $this->settings_manager->update_cli_settings( $updates );

		if ( ! $result ) {
			WP_CLI::error( 'Failed to update settings.' );
		}

		WP_CLI::success( 'Settings updated successfully.' );
		
		// Show updated settings
		WP_CLI::log( 'Updated settings:' );
		$this->display_settings_table( $this->settings_manager->get_settings() );
	}

	/**
	 * Show plugin status and server capabilities
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format (table, json, yaml). Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp image-optimizer status
	 *     wp image-optimizer status --format=json
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Named arguments
	 */
	public function status( $args, $assoc_args ) {
		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		// Get converter info
		$converter_info = $this->image_converter->get_converter_info();
		
		// Get settings
		$settings = $this->settings_manager->get_settings();
		
		// Check conversion capability
		$can_convert = $this->image_converter->can_convert();
		
		$status_data = array(
			'plugin_version' => WP_IMAGE_OPTIMIZER_VERSION,
			'conversion_enabled' => $settings['enabled'],
			'conversion_mode' => $settings['conversion_mode'],
			'can_convert' => ! is_wp_error( $can_convert ),
			'converter_available' => ! empty( $converter_info ),
			'converter_name' => $converter_info ? $converter_info['name'] : 'None',
			'supported_formats' => $converter_info ? implode( ', ', $converter_info['supported_formats'] ) : 'None',
			'webp_enabled' => $settings['formats']['webp']['enabled'],
			'webp_quality' => $settings['formats']['webp']['quality'],
			'avif_enabled' => $settings['formats']['avif']['enabled'],
			'avif_quality' => $settings['formats']['avif']['quality'],
		);

		if ( is_wp_error( $can_convert ) ) {
			$status_data['conversion_error'] = $can_convert->get_error_message();
		}

		if ( 'table' === $format ) {
			$this->display_status_table( $status_data );
		} else {
			WP_CLI::print_value( $status_data, $assoc_args );
		}
	}

	/**
	 * Reset plugin settings to defaults
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Reset all settings including CLI settings
	 *
	 * [--yes]
	 * : Skip confirmation prompt
	 *
	 * ## EXAMPLES
	 *
	 *     wp image-optimizer reset-settings
	 *     wp image-optimizer reset-settings --all --yes
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Named arguments
	 */
	public function reset_settings( $args, $assoc_args ) {
		$reset_all = WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );
		$skip_confirmation = WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false );

		$message = $reset_all ? 
			'This will reset ALL plugin settings (including CLI settings) to defaults.' :
			'This will reset UI settings to defaults (CLI settings will be preserved).';

		if ( ! $skip_confirmation ) {
			WP_CLI::confirm( $message . ' Are you sure?' );
		}

		$result = $this->settings_manager->reset_settings( $reset_all );

		if ( ! $result ) {
			WP_CLI::error( 'Failed to reset settings.' );
		}

		WP_CLI::success( 'Settings reset to defaults successfully.' );
	}

	/**
	 * Start batch conversion in background
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Specific format to convert to (webp, avif). Default: all enabled formats
	 *
	 * [--limit=<number>]
	 * : Maximum number of images to process. Default: no limit
	 *
	 * [--offset=<number>]
	 * : Number of images to skip. Default: 0
	 *
	 * [--force]
	 * : Force reconversion of already converted images
	 *
	 * [--attachment-ids=<ids>]
	 * : Comma-separated list of specific attachment IDs to convert
	 *
	 * ## EXAMPLES
	 *
	 *     wp image-optimizer batch-start
	 *     wp image-optimizer batch-start --format=webp --limit=100
	 *     wp image-optimizer batch-start --attachment-ids=123,456,789
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Named arguments
	 */
	public function batch_start( $args, $assoc_args ) {
		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );
		$limit = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 0 );
		$offset = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'offset', 0 );
		$force = WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );
		$attachment_ids_str = WP_CLI\Utils\get_flag_value( $assoc_args, 'attachment-ids' );

		// Validate format if specified
		if ( $format && ! in_array( strtolower( $format ), array( 'webp', 'avif' ), true ) ) {
			WP_CLI::error( sprintf( 'Invalid format: %s. Allowed formats: webp, avif', $format ) );
		}

		// Parse attachment IDs if provided
		$attachment_ids = array();
		if ( $attachment_ids_str ) {
			$attachment_ids = array_map( 'intval', explode( ',', $attachment_ids_str ) );
			$attachment_ids = array_filter( $attachment_ids, 'wp_attachment_is_image' );
			
			if ( empty( $attachment_ids ) ) {
				WP_CLI::error( 'No valid image attachment IDs provided.' );
			}
		}

		// Check if batch processor is available
		if ( ! $this->batch_processor ) {
			WP_CLI::error( 'Batch processor is not available.' );
		}

		// Check if batch is already running
		if ( $this->batch_processor->is_batch_running() ) {
			WP_CLI::error( 'Batch conversion is already running. Use "batch-status" to check progress or "batch-cancel" to cancel.' );
		}

		// Prepare batch options
		$options = array(
			'format' => $format,
			'force' => $force,
			'limit' => $limit,
			'offset' => $offset,
			'attachment_ids' => $attachment_ids,
		);

		// Start batch conversion
		$result = $this->batch_processor->start_batch_conversion( $options );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( 'Batch conversion started successfully. Use "batch-status" to monitor progress.' );
	}

	/**
	 * Check batch conversion status
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format (table, json, yaml). Default: table
	 *
	 * [--watch]
	 * : Watch mode - continuously update status every 5 seconds
	 *
	 * ## EXAMPLES
	 *
	 *     wp image-optimizer batch-status
	 *     wp image-optimizer batch-status --format=json
	 *     wp image-optimizer batch-status --watch
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Named arguments
	 */
	public function batch_status( $args, $assoc_args ) {
		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$watch = WP_CLI\Utils\get_flag_value( $assoc_args, 'watch', false );

		if ( ! $this->batch_processor ) {
			WP_CLI::error( 'Batch processor is not available.' );
		}

		do {
			$progress = $this->batch_processor->get_batch_progress();
			$queue_status = $this->batch_processor->get_queue_status();

			if ( ! $progress ) {
				WP_CLI::log( 'No batch conversion is currently running or has been run recently.' );
				return;
			}

			if ( 'table' === $format ) {
				$this->display_batch_status_table( $progress, $queue_status );
			} else {
				WP_CLI::print_value( array( 'progress' => $progress, 'queue_status' => $queue_status ), $assoc_args );
			}

			if ( $watch && 'running' === $progress['status'] ) {
				WP_CLI::log( "\n" . str_repeat( '-', 50 ) . "\n" );
				sleep( 5 );
			} else {
				break;
			}
		} while ( $watch );
	}

	/**
	 * Cancel running batch conversion
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation prompt
	 *
	 * ## EXAMPLES
	 *
	 *     wp image-optimizer batch-cancel
	 *     wp image-optimizer batch-cancel --yes
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Named arguments
	 */
	public function batch_cancel( $args, $assoc_args ) {
		$skip_confirmation = WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false );

		if ( ! $this->batch_processor ) {
			WP_CLI::error( 'Batch processor is not available.' );
		}

		if ( ! $this->batch_processor->is_batch_running() ) {
			WP_CLI::log( 'No batch conversion is currently running.' );
			return;
		}

		if ( ! $skip_confirmation ) {
			WP_CLI::confirm( 'Are you sure you want to cancel the running batch conversion?' );
		}

		$result = $this->batch_processor->cancel_batch();

		if ( ! $result ) {
			WP_CLI::error( 'Failed to cancel batch conversion.' );
		}

		WP_CLI::success( 'Batch conversion cancelled successfully.' );
	}

	/**
	 * Clean up temporary files and failed operations
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would be cleaned up without actually deleting files
	 *
	 * [--yes]
	 * : Skip confirmation prompt
	 *
	 * ## EXAMPLES
	 *
	 *     wp image-optimizer batch-cleanup --dry-run
	 *     wp image-optimizer batch-cleanup --yes
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Named arguments
	 */
	public function batch_cleanup( $args, $assoc_args ) {
		$dry_run = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$skip_confirmation = WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false );

		if ( ! $this->batch_processor ) {
			WP_CLI::error( 'Batch processor is not available.' );
		}

		if ( $dry_run ) {
			WP_CLI::log( 'DRY RUN MODE - No files will be deleted' );
		} elseif ( ! $skip_confirmation ) {
			WP_CLI::confirm( 'This will clean up temporary files and failed conversion metadata. Are you sure?' );
		}

		// For dry run, we'll simulate the cleanup
		if ( $dry_run ) {
			WP_CLI::log( 'Scanning for temporary files and failed operations...' );
			WP_CLI::log( 'This would clean up:' );
			WP_CLI::log( '- Temporary conversion files older than 1 hour' );
			WP_CLI::log( '- Orphaned converted files (without original)' );
			WP_CLI::log( '- Failed conversion metadata older than 30 days' );
			WP_CLI::success( 'Dry run completed. Use without --dry-run to perform actual cleanup.' );
			return;
		}

		$result = $this->batch_processor->cleanup_temporary_files();

		WP_CLI::success( sprintf(
			'Cleanup completed. Temp files: %d, Failed conversions: %d, Orphaned files: %d',
			$result['temp_files_deleted'],
			$result['failed_conversions_cleaned'],
			$result['orphaned_files_deleted']
		) );

		if ( ! empty( $result['errors'] ) ) {
			WP_CLI::warning( sprintf( '%d errors occurred during cleanup:', count( $result['errors'] ) ) );
			foreach ( $result['errors'] as $error ) {
				WP_CLI::log( '  - ' . $error );
			}
		}
	}

	/**
	 * Clean up converted files for specific images or all images
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Clean up all converted files
	 *
	 * [--attachment-id=<id>]
	 * : Clean up converted files for specific attachment ID
	 *
	 * [--path=<path>]
	 * : Clean up converted files for specific image path
	 *
	 * [--dry-run]
	 * : Show what would be cleaned up without actually deleting files
	 *
	 * [--yes]
	 * : Skip confirmation prompt
	 *
	 * ## EXAMPLES
	 *
	 *     wp image-optimizer cleanup --all --dry-run
	 *     wp image-optimizer cleanup --attachment-id=123
	 *     wp image-optimizer cleanup --path=2024/01/image.jpg
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Named arguments
	 */
	public function cleanup( $args, $assoc_args ) {
		$clean_all = WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );
		$attachment_id = WP_CLI\Utils\get_flag_value( $assoc_args, 'attachment-id' );
		$path = WP_CLI\Utils\get_flag_value( $assoc_args, 'path' );
		$dry_run = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$skip_confirmation = WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false );

		// Validate arguments
		$options_count = (int) $clean_all + (int) ! empty( $attachment_id ) + (int) ! empty( $path );
		if ( $options_count !== 1 ) {
			WP_CLI::error( 'Please specify exactly one option: --all, --attachment-id, or --path' );
		}

		if ( $dry_run ) {
			WP_CLI::log( 'DRY RUN MODE - No files will be deleted' );
		}

		if ( $clean_all ) {
			$this->cleanup_all_converted_files( $dry_run, $skip_confirmation );
		} elseif ( $attachment_id ) {
			$this->cleanup_attachment_converted_files( (int) $attachment_id, $dry_run, $skip_confirmation );
		} else {
			$this->cleanup_path_converted_files( $path, $dry_run, $skip_confirmation );
		}
	}

	/**
	 * Convert a single attachment
	 *
	 * @param int         $attachment_id Attachment ID
	 * @param string|null $format Specific format or null for all
	 * @param bool        $force Force reconversion
	 * @param bool        $dry_run Dry run mode
	 * @return array|WP_Error Conversion result
	 */
	private function convert_single_attachment( $attachment_id, $format = null, $force = false, $dry_run = false ) {
		// Get attachment file path
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', sprintf( 'File not found for attachment %d', $attachment_id ) );
		}

		// Check if already converted (unless forcing)
		if ( ! $force && ! $dry_run ) {
			$existing_versions = $this->image_converter->check_converted_versions( $file_path );
			$needs_conversion = false;
			
			if ( $format ) {
				$needs_conversion = ! $existing_versions[ $format ]['exists'];
			} else {
				foreach ( $existing_versions as $fmt => $info ) {
					if ( $info['enabled'] && ! $info['exists'] ) {
						$needs_conversion = true;
						break;
					}
				}
			}
			
			if ( ! $needs_conversion ) {
				return array( 'skipped' => true, 'reason' => 'already_converted' );
			}
		}

		if ( $dry_run ) {
			$this->show_conversion_preview( $file_path, $format );
			return array( 'skipped' => true, 'reason' => 'dry_run' );
		}

		return $this->convert_image_by_path( $file_path, $format, $force );
	}

	/**
	 * Convert image by file path
	 *
	 * @param string      $file_path Path to image file
	 * @param string|null $format Specific format or null for all
	 * @param bool        $force Force reconversion
	 * @return array|WP_Error Conversion result
	 */
	private function convert_image_by_path( $file_path, $format = null, $force = false ) {
		if ( $format ) {
			// Convert to specific format
			$result = $this->image_converter->convert_on_demand( $file_path, $format );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			
			// Get file info for space calculation
			$original_size = filesize( $file_path );
			$converted_size = filesize( $result );
			$space_saved = $original_size - $converted_size;
			
			return array(
				'format' => $format,
				'converted_path' => $result,
				'space_saved' => $space_saved,
			);
		} else {
			// Convert to all enabled formats
			return $this->image_converter->convert_image( $file_path );
		}
	}

	/**
	 * Resolve image path (relative to uploads or absolute)
	 *
	 * @param string $path Input path
	 * @return string|WP_Error Resolved absolute path or error
	 */
	private function resolve_image_path( $path ) {
		// If already absolute and exists, return as-is
		if ( path_is_absolute( $path ) && file_exists( $path ) ) {
			return $path;
		}

		// Try relative to uploads directory
		$upload_dir = wp_upload_dir();
		$absolute_path = trailingslashit( $upload_dir['basedir'] ) . ltrim( $path, '/' );
		
		if ( file_exists( $absolute_path ) ) {
			return $absolute_path;
		}

		return new WP_Error( 'file_not_found', sprintf( 'Image file not found: %s', $path ) );
	}

	/**
	 * Show conversion preview for dry run
	 *
	 * @param string      $file_path Path to image file
	 * @param string|null $format Specific format or null for all
	 */
	private function show_conversion_preview( $file_path, $format = null ) {
		$settings = $this->settings_manager->get_settings();
		$existing_versions = $this->image_converter->check_converted_versions( $file_path );
		
		WP_CLI::log( sprintf( 'Image: %s', basename( $file_path ) ) );
		WP_CLI::log( sprintf( 'Size: %s', $this->format_bytes( filesize( $file_path ) ) ) );
		
		$formats_to_check = $format ? array( $format ) : array_keys( $settings['formats'] );
		
		foreach ( $formats_to_check as $fmt ) {
			if ( ! $settings['formats'][ $fmt ]['enabled'] ) {
				WP_CLI::log( sprintf( '  %s: Disabled', strtoupper( $fmt ) ) );
				continue;
			}
			
			$status = $existing_versions[ $fmt ]['exists'] ? 'Exists (would be overwritten)' : 'Would be created';
			WP_CLI::log( sprintf( '  %s: %s', strtoupper( $fmt ), $status ) );
		}
	}

	/**
	 * Display settings in table format
	 *
	 * @param array $settings Settings array
	 */
	private function display_settings_table( $settings ) {
		$table_data = array();
		
		// Flatten settings for table display
		$table_data[] = array( 'Setting', 'Value' );
		$table_data[] = array( 'Enabled', $settings['enabled'] ? 'Yes' : 'No' );
		$table_data[] = array( 'Conversion Mode', $settings['conversion_mode'] );
		$table_data[] = array( 'Preserve Originals', $settings['preserve_originals'] ? 'Yes' : 'No' );
		$table_data[] = array( 'Max File Size', $this->format_bytes( $settings['max_file_size'] ) );
		$table_data[] = array( 'Server Config Type', $settings['server_config_type'] );
		$table_data[] = array( 'WebP Enabled', $settings['formats']['webp']['enabled'] ? 'Yes' : 'No' );
		$table_data[] = array( 'WebP Quality', $settings['formats']['webp']['quality'] );
		$table_data[] = array( 'AVIF Enabled', $settings['formats']['avif']['enabled'] ? 'Yes' : 'No' );
		$table_data[] = array( 'AVIF Quality', $settings['formats']['avif']['quality'] );
		
		WP_CLI\Utils\format_items( 'table', $table_data, array( 'Setting', 'Value' ) );
	}

	/**
	 * Display status in table format
	 *
	 * @param array $status_data Status data array
	 */
	private function display_status_table( $status_data ) {
		$table_data = array();
		
		foreach ( $status_data as $key => $value ) {
			$label = ucwords( str_replace( '_', ' ', $key ) );
			$display_value = is_bool( $value ) ? ( $value ? 'Yes' : 'No' ) : $value;
			$table_data[] = array( 'Property' => $label, 'Value' => $display_value );
		}
		
		WP_CLI\Utils\format_items( 'table', $table_data, array( 'Property', 'Value' ) );
	}

	/**
	 * Clean up all converted files
	 *
	 * @param bool $dry_run Dry run mode
	 * @param bool $skip_confirmation Skip confirmation
	 */
	private function cleanup_all_converted_files( $dry_run, $skip_confirmation ) {
		if ( ! $skip_confirmation && ! $dry_run ) {
			WP_CLI::confirm( 'This will delete ALL converted image files. Are you sure?' );
		}

		// Get all image attachments
		$attachments = get_posts( array(
			'post_type' => 'attachment',
			'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif' ),
			'post_status' => 'inherit',
			'posts_per_page' => -1,
			'fields' => 'ids',
		) );

		if ( empty( $attachments ) ) {
			WP_CLI::success( 'No image attachments found.' );
			return;
		}

		$progress = WP_CLI\Utils\make_progress_bar( 'Cleaning up converted files', count( $attachments ) );
		$cleaned_count = 0;

		foreach ( $attachments as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$result = $this->file_handler->cleanup_converted_files( $file_path, $dry_run );
				if ( ! is_wp_error( $result ) && ! empty( $result['deleted'] ) ) {
					$cleaned_count += count( $result['deleted'] );
				}
			}
			$progress->tick();
		}

		$progress->finish();
		
		$action = $dry_run ? 'would be cleaned up' : 'cleaned up';
		WP_CLI::success( sprintf( '%d converted files %s.', $cleaned_count, $action ) );
	}

	/**
	 * Clean up converted files for specific attachment
	 *
	 * @param int  $attachment_id Attachment ID
	 * @param bool $dry_run Dry run mode
	 * @param bool $skip_confirmation Skip confirmation
	 */
	private function cleanup_attachment_converted_files( $attachment_id, $dry_run, $skip_confirmation ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			WP_CLI::error( sprintf( 'Attachment %d is not a valid image.', $attachment_id ) );
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			WP_CLI::error( sprintf( 'File not found for attachment %d', $attachment_id ) );
		}

		if ( ! $skip_confirmation && ! $dry_run ) {
			WP_CLI::confirm( sprintf( 'This will delete converted files for attachment %d. Are you sure?', $attachment_id ) );
		}

		$result = $this->file_handler->cleanup_converted_files( $file_path, $dry_run );
		
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		$count = count( $result['deleted'] );
		$action = $dry_run ? 'would be deleted' : 'deleted';
		WP_CLI::success( sprintf( '%d converted files %s for attachment %d.', $count, $action, $attachment_id ) );
	}

	/**
	 * Clean up converted files for specific path
	 *
	 * @param string $path Image path
	 * @param bool   $dry_run Dry run mode
	 * @param bool   $skip_confirmation Skip confirmation
	 */
	private function cleanup_path_converted_files( $path, $dry_run, $skip_confirmation ) {
		$resolved_path = $this->resolve_image_path( $path );
		if ( is_wp_error( $resolved_path ) ) {
			WP_CLI::error( $resolved_path->get_error_message() );
		}

		if ( ! $skip_confirmation && ! $dry_run ) {
			WP_CLI::confirm( sprintf( 'This will delete converted files for %s. Are you sure?', basename( $resolved_path ) ) );
		}

		$result = $this->file_handler->cleanup_converted_files( $resolved_path, $dry_run );
		
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		$count = count( $result['deleted'] );
		$action = $dry_run ? 'would be deleted' : 'deleted';
		WP_CLI::success( sprintf( '%d converted files %s for %s.', $count, $action, basename( $resolved_path ) ) );
	}

	/**
	 * Parse boolean value from string
	 *
	 * @param mixed $value Input value
	 * @return bool Parsed boolean value
	 */
	private function parse_boolean( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		
		$lower = strtolower( (string) $value );
		return in_array( $lower, array( 'true', '1', 'yes', 'on' ), true );
	}

	/**
	 * Display batch status in table format
	 *
	 * @param array $progress Progress data
	 * @param array $queue_status Queue status data
	 */
	private function display_batch_status_table( $progress, $queue_status ) {
		$table_data = array();
		
		$table_data[] = array( 'Property', 'Value' );
		$table_data[] = array( 'Status', ucfirst( $progress['status'] ) );
		$table_data[] = array( 'Total Images', $progress['total'] );
		$table_data[] = array( 'Processed', $progress['processed'] );
		$table_data[] = array( 'Successful', $progress['successful'] );
		$table_data[] = array( 'Failed', $progress['failed'] );
		$table_data[] = array( 'Skipped', $progress['skipped'] );
		$table_data[] = array( 'Progress', $progress['percentage'] . '%' );
		$table_data[] = array( 'Space Saved', $this->format_bytes( $progress['space_saved'] ) );
		$table_data[] = array( 'Queue Size', $queue_status['queue_size'] );
		
		if ( isset( $progress['estimated_time_remaining'] ) ) {
			$table_data[] = array( 'Est. Time Remaining', $this->format_duration( $progress['estimated_time_remaining'] ) );
		}
		
		if ( $progress['start_time'] ) {
			$table_data[] = array( 'Started', date( 'Y-m-d H:i:s', $progress['start_time'] ) );
		}
		
		if ( $progress['end_time'] ) {
			$table_data[] = array( 'Ended', date( 'Y-m-d H:i:s', $progress['end_time'] ) );
		}
		
		WP_CLI\Utils\format_items( 'table', $table_data, array( 'Property', 'Value' ) );
		
		// Show recent errors if any
		if ( ! empty( $progress['errors'] ) ) {
			$recent_errors = array_slice( $progress['errors'], -5 );
			WP_CLI::log( "\nRecent Errors:" );
			foreach ( $recent_errors as $error ) {
				WP_CLI::log( sprintf( '  - %s: %s', 
					isset( $error['item']['attachment_id'] ) ? 'ID ' . $error['item']['attachment_id'] : 'Unknown',
					$error['error'] 
				) );
			}
		}
	}

	/**
	 * Format duration in seconds to human readable format
	 *
	 * @param int $seconds Duration in seconds
	 * @return string Formatted duration
	 */
	private function format_duration( $seconds ) {
		if ( $seconds < 60 ) {
			return $seconds . ' seconds';
		} elseif ( $seconds < 3600 ) {
			return round( $seconds / 60 ) . ' minutes';
		} else {
			return round( $seconds / 3600, 1 ) . ' hours';
		}
	}

	/**
	 * Format bytes into human readable format
	 *
	 * @param int $bytes Number of bytes
	 * @return string Formatted string
	 */
	private function format_bytes( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		
		for ( $i = 0; $bytes >= 1024 && $i < count( $units ) - 1; $i++ ) {
			$bytes /= 1024;
		}
		
		return round( $bytes, 2 ) . ' ' . $units[ $i ];
	}
}