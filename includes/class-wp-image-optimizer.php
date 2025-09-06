<?php
/**
 * Main plugin class
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main WP Image Optimizer class
 */
class WP_Image_Optimizer {

	/**
	 * Plugin instance
	 *
	 * @var WP_Image_Optimizer|null
	 */
	private static $instance = null;

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Plugin initialization status
	 *
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Hooks integration instance
	 *
	 * @var WP_Image_Optimizer_Hooks_Integration
	 */
	private $hooks_integration;

	/**
	 * Admin interface instance
	 *
	 * @var WP_Image_Optimizer_Admin_Interface
	 */
	private $admin_interface;

	/**
	 * Batch processor instance
	 *
	 * @var WP_Image_Optimizer_Batch_Processor
	 */
	private $batch_processor;

	/**
	 * Get plugin instance (Singleton pattern)
	 *
	 * @return WP_Image_Optimizer
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Private to enforce singleton pattern
	 */
	private function __construct() {
		$this->version = WP_IMAGE_OPTIMIZER_VERSION;
		$this->init();
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization
	 */
	public function __wakeup() {
		throw new Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Initialize the plugin
	 */
	private function init() {
		if ( $this->initialized ) {
			return;
		}

		// Perform security checks
		if ( ! $this->security_checks() ) {
			return;
		}

		// Check for plugin updates
		add_action( 'admin_init', array( $this, 'check_for_updates' ) );

		// Load plugin text domain for translations
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Initialize plugin components
		add_action( 'init', array( $this, 'init_components' ), 10 );

		// Mark as initialized
		$this->initialized = true;

		// Hook into WordPress
		$this->setup_hooks();
	}

	/**
	 * Perform security checks
	 *
	 * @return bool True if all security checks pass
	 */
	private function security_checks() {
		// Check if WordPress is loaded properly
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			return false;
		}

		// Check for required WordPress functions
		$required_functions = array(
			'wp_handle_upload',
			'wp_generate_attachment_metadata',
			'wp_get_attachment_image_src',
			'add_action',
			'add_filter',
		);

		foreach ( $required_functions as $function ) {
			if ( ! function_exists( $function ) ) {
				error_log( "WP Image Optimizer: Required function {$function} not found" );
				return false;
			}
		}

		// Check for required PHP extensions
		$required_extensions = array( 'gd' );
		foreach ( $required_extensions as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				// GD is preferred but not strictly required if ImageMagick is available
				// This will be handled in converter detection
			}
		}

		return true;
	}

	/**
	 * Load plugin text domain for translations
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'wp-image-optimizer',
			false,
			dirname( WP_IMAGE_OPTIMIZER_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize plugin components
	 */
	public function init_components() {
		// Load Database Manager first
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-database-manager.php';
		$db_manager = WP_Image_Optimizer_Database_Manager::get_instance();
		
		// Register database optimization hooks
		$db_manager->register_optimization_hooks();
		
		// Load Settings Manager
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-settings-manager.php';
		
		// Load Error Handler
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-error-handler.php';
		
		// Load Security Validator
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-security-validator.php';
		
		// Load required classes for hooks integration
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-file-handler.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/interfaces/interface-converter.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/converters/class-converter-factory.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/converters/class-gd-converter.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/converters/class-imagemagick-converter.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-image-converter.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-image-handler.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-hooks-integration.php';
		
		// Load batch processor
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-batch-processor.php';
		
		// Load Cleanup Manager
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-cleanup-manager.php';
		
		// Initialize hooks integration
		$this->hooks_integration = new WP_Image_Optimizer_Hooks_Integration();
		
		// Initialize batch processor
		$this->batch_processor = new WP_Image_Optimizer_Batch_Processor();
		
		// Load and initialize admin interface (only in admin)
		if ( is_admin() ) {
			require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-admin-interface.php';
			$this->admin_interface = WP_Image_Optimizer_Admin_Interface::get_instance();
		}
		
		// Register WP-CLI commands if WP-CLI is available
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_cli_commands();
		}
		
		do_action( 'wp_image_optimizer_components_loaded' );
	}

	/**
	 * Setup WordPress hooks
	 */
	private function setup_hooks() {
		// Admin hooks
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'admin_init' ) );
		}

		// Public hooks will be added here in future tasks
	}

	/**
	 * Admin initialization
	 */
	public function admin_init() {
		// Admin functionality will be implemented in future tasks
	}

	/**
	 * Get plugin version
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Check if plugin is initialized
	 *
	 * @return bool
	 */
	public function is_initialized() {
		return $this->initialized;
	}

	/**
	 * Get hooks integration instance
	 *
	 * @return WP_Image_Optimizer_Hooks_Integration|null
	 */
	public function get_hooks_integration() {
		return $this->hooks_integration;
	}

	/**
	 * Get admin interface instance
	 *
	 * @return WP_Image_Optimizer_Admin_Interface|null
	 */
	public function get_admin_interface() {
		return $this->admin_interface;
	}

	/**
	 * Get batch processor instance
	 *
	 * @return WP_Image_Optimizer_Batch_Processor|null
	 */
	public function get_batch_processor() {
		return $this->batch_processor;
	}

	/**
	 * Register WP-CLI commands
	 */
	private function register_cli_commands() {
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-cli-commands.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-cli-commands-db.php';
		WP_CLI::add_command( 'image-optimizer', 'WP_Image_Optimizer_CLI_Commands' );
		WP_CLI::add_command( 'image-optimizer db', 'WP_Image_Optimizer_CLI_Commands_DB' );
	}

	/**
	 * Plugin activation hook
	 */
	public static function activate() {
		// Check requirements again during activation
		if ( ! wp_image_optimizer_check_requirements() ) {
			wp_die( 
				esc_html__( 'WP Image Optimizer cannot be activated due to unmet requirements.', 'wp-image-optimizer' ),
				esc_html__( 'Plugin Activation Error', 'wp-image-optimizer' ),
				array( 'back_link' => true )
			);
		}

		// Load Database Manager
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-database-manager.php';
		$db_manager = WP_Image_Optimizer_Database_Manager::get_instance();
		
		// Initialize database with optimized structure
		$db_manager->initialize_database();

		// Set activation flag
		add_option( 'wp_image_optimizer_activated', true );
		
		// Store activation timestamp
		add_option( 'wp_image_optimizer_activation_time', time() );
		
		// Store activation version for future updates
		add_option( 'wp_image_optimizer_activation_version', WP_IMAGE_OPTIMIZER_VERSION );

		// Create necessary directories
		self::create_plugin_directories();

		// Clear any cached data
		wp_cache_flush();

		do_action( 'wp_image_optimizer_activated' );
	}

	/**
	 * Plugin deactivation hook
	 */
	public static function deactivate() {
		// Load Database Manager
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-database-manager.php';
		$db_manager = WP_Image_Optimizer_Database_Manager::get_instance();
		
		// Clear scheduled events
		wp_clear_scheduled_hook( 'wp_image_optimizer_batch_process' );

		// Clear all plugin cache using Database Manager
		$db_manager->clear_all_cache();

		// Clear any cached data
		wp_cache_flush();

		do_action( 'wp_image_optimizer_deactivated' );
	}

	/**
	 * Plugin uninstall hook
	 */
	public static function uninstall() {
		// Load Database Manager
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-database-manager.php';
		$db_manager = WP_Image_Optimizer_Database_Manager::get_instance();
		
		// Perform complete cleanup using Database Manager
		// Note: This will ask user if they want to remove converted images
		$remove_images = apply_filters( 'wp_image_optimizer_uninstall_remove_images', false );
		$db_manager->cleanup_on_uninstall( $remove_images );

		// Remove all plugin options
		delete_option( 'wp_image_optimizer_activated' );
		delete_option( 'wp_image_optimizer_activation_time' );
		delete_option( 'wp_image_optimizer_activation_version' );
		delete_option( 'wp_image_optimizer_db_version' );
		
		// Clear all scheduled events
		wp_clear_scheduled_hook( 'wp_image_optimizer_batch_process' );
		wp_clear_scheduled_hook( 'wp_image_optimizer_db_optimize' );

		do_action( 'wp_image_optimizer_uninstalled' );
	}
	
	/**
	 * Create necessary plugin directories
	 */
	private static function create_plugin_directories() {
		// Get WordPress upload directory
		$upload_dir = wp_upload_dir();
		$base_dir = $upload_dir['basedir'];
		
		// Create temp directory for plugin operations
		$temp_dir = $base_dir . '/wp-image-optimizer-temp';
		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
			
			// Create .htaccess file to protect temp directory
			$htaccess_content = "# Disable directory browsing\n";
			$htaccess_content .= "Options -Indexes\n\n";
			$htaccess_content .= "# Deny access to all files\n";
			$htaccess_content .= "<FilesMatch \".*\">\n";
			$htaccess_content .= "    Order Allow,Deny\n";
			$htaccess_content .= "    Deny from all\n";
			$htaccess_content .= "</FilesMatch>\n";
			
			@file_put_contents( $temp_dir . '/.htaccess', $htaccess_content );
		}
		
		// Create logs directory
		$logs_dir = WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'logs';
		if ( ! file_exists( $logs_dir ) ) {
			wp_mkdir_p( $logs_dir );
			
			// Create .htaccess file to protect logs directory
			$htaccess_content = "# Disable directory browsing\n";
			$htaccess_content .= "Options -Indexes\n\n";
			$htaccess_content .= "# Deny access to all files\n";
			$htaccess_content .= "<FilesMatch \".*\">\n";
			$htaccess_content .= "    Order Allow,Deny\n";
			$htaccess_content .= "    Deny from all\n";
			$htaccess_content .= "</FilesMatch>\n";
			
			@file_put_contents( $logs_dir . '/.htaccess', $htaccess_content );
		}
		
		// Create index.php files to prevent directory listing
		$index_content = "<?php\n// Silence is golden.\n";
		@file_put_contents( $temp_dir . '/index.php', $index_content );
		@file_put_contents( $logs_dir . '/index.php', $index_content );
	}
	
	/**
	 * Check for plugin updates and run migrations if needed
	 */
	public function check_for_updates() {
		$current_version = WP_IMAGE_OPTIMIZER_VERSION;
		$stored_version = get_option( 'wp_image_optimizer_version', '0.0.0' );
		
		// If versions match, no update needed
		if ( version_compare( $stored_version, $current_version, '==' ) ) {
			return false;
		}
		
		// Run version-specific updates
		$updated = $this->run_version_updates( $stored_version, $current_version );
		
		// Update stored version
		update_option( 'wp_image_optimizer_version', $current_version );
		
		// Log the update
		if ( $updated ) {
			// Load error handler if available
			if ( ! class_exists( 'WP_Image_Optimizer_Error_Handler' ) ) {
				require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-error-handler.php';
			}
			if ( class_exists( 'WP_Image_Optimizer_Error_Handler' ) ) {
				$error_handler = WP_Image_Optimizer_Error_Handler::get_instance();
				$error_handler->log_error(
					sprintf( 'Plugin updated from %s to %s', $stored_version, $current_version ),
					'info',
					'system',
					array( 'automatic_update' => true )
				);
			}
		}
		
		return $updated;
	}
	
	/**
	 * Run version-specific updates
	 *
	 * @param string $from_version Current version
	 * @param string $to_version Target version
	 * @return bool True if updates were performed
	 */
	private function run_version_updates( $from_version, $to_version ) {
		$updates_performed = false;
		
		// Example of how to handle version-specific updates
		// For future versions, add conditions here
		
		// if ( version_compare( $from_version, '1.1.0', '<' ) && version_compare( $to_version, '1.1.0', '>=' ) ) {
		//     $this->update_to_1_1_0();
		//     $updates_performed = true;
		// }
		
		// if ( version_compare( $from_version, '1.2.0', '<' ) && version_compare( $to_version, '1.2.0', '>=' ) ) {
		//     $this->update_to_1_2_0();
		//     $updates_performed = true;
		// }
		
		// Run database migrations if needed
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-database-manager.php';
		$db_manager = WP_Image_Optimizer_Database_Manager::get_instance();
		$db_manager->maybe_migrate_database();
		
		return $updates_performed;
	}
}