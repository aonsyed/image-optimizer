<?php
/**
 * Plugin Name: Image Optimizer
 * Plugin URI: https://github.com/your-username/image-to-webp-converter
 * Description: Optimizes images and converts them to WebP or AVIF format for better performance.
 * Version: 1.0.0
 * Requires at least: 5.6
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Author: Aon
 * Author URI: https://aon.sh
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: image-optimizer
 * Domain Path: /languages
 * Network: true
 *
 * @package ImageOptimizer
 * @version 1.0.0
 * @author Aon
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'IMAGE_OPTIMIZER_VERSION', '1.0.0' );
define( 'IMAGE_OPTIMIZER_PLUGIN_FILE', __FILE__ );
define( 'IMAGE_OPTIMIZER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IMAGE_OPTIMIZER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IMAGE_OPTIMIZER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Include required files
require_once IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-image-optimizer.php';
require_once IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-image-converter.php';
require_once IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-admin-ui.php';
require_once IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-logger.php';
require_once IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-image-servicer.php';
require_once IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-image-scheduler.php';

// Include CLI commands if WP-CLI is available
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once IMAGE_OPTIMIZER_PLUGIN_DIR . 'cli/class-cli-commands.php';
}

/**
 * Main plugin class
 */
final class Image_Optimizer_Plugin {

	/**
	 * Plugin instance
	 *
	 * @var Image_Optimizer_Plugin
	 */
	private static $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return Image_Optimizer_Plugin
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
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Activation and deactivation hooks
		register_activation_hook( IMAGE_OPTIMIZER_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( IMAGE_OPTIMIZER_PLUGIN_FILE, array( $this, 'deactivate' ) );

		// Initialize plugin
		add_action( 'plugins_loaded', array( $this, 'init' ) );

		// Add settings link to plugin listing
		add_filter( 'plugin_action_links_' . IMAGE_OPTIMIZER_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Initialize the plugin
	 */
	public function init() {
		// Load text domain
		load_plugin_textdomain( 'image-optimizer', false, dirname( IMAGE_OPTIMIZER_PLUGIN_BASENAME ) . '/languages' );

		// Initialize components
		Image_Optimizer::init();
		Admin_UI::init();
		Logger::init();

		// Register deletion hook
		add_action( 'delete_attachment', array( 'Image_Optimizer', 'delete_converted_images' ) );
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Check for required PHP extensions
		if ( ! extension_loaded( 'gd' ) ) {
			deactivate_plugins( IMAGE_OPTIMIZER_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'The Image Optimizer plugin requires the GD PHP extension. Please install and activate it.', 'image-optimizer' ),
				esc_html__( 'Plugin Activation Error', 'image-optimizer' ),
				array( 'back_link' => true )
			);
		}

		// Check for Imagick extension for AVIF support
		if ( ! extension_loaded( 'imagick' ) ) {
			// Add admin notice about AVIF support
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-warning is-dismissible">';
				echo '<p>' . esc_html__( 'Image Optimizer: AVIF conversion requires the Imagick PHP extension. WebP conversion will still work.', 'image-optimizer' ) . '</p>';
				echo '</div>';
			});
		}

		// Set default options
		$this->set_default_options();

		// Create logs directory
		$this->create_logs_directory();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Clear scheduled events
		wp_clear_scheduled_hook( 'image_optimizer_bulk_conversion' );

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Set default options
	 */
	private function set_default_options() {
		$defaults = array(
			'image_optimizer_webp_quality' => 80,
			'image_optimizer_avif_quality' => 80,
			'image_optimizer_convert_on_upload' => true,
			'image_optimizer_enable_scheduler' => false,
			'image_optimizer_remove_originals' => false,
			'image_optimizer_conversion_format' => 'both',
			'image_optimizer_excluded_sizes' => array(),
		);

		foreach ( $defaults as $option => $value ) {
			if ( false === get_option( $option ) ) {
				add_option( $option, $value );
			}
		}
	}

	/**
	 * Create logs directory
	 */
	private function create_logs_directory() {
		$logs_dir = IMAGE_OPTIMIZER_PLUGIN_DIR . 'logs';
		if ( ! file_exists( $logs_dir ) ) {
			wp_mkdir_p( $logs_dir );
		}

		// Create .htaccess to protect logs
		$htaccess_file = $logs_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			file_put_contents( $htaccess_file, "Order deny,allow\nDeny from all\n" );
		}
	}

	/**
	 * Add settings link to plugin listing
	 *
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'options-general.php?page=image-optimizer' ),
			esc_html__( 'Settings', 'image-optimizer' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}
}

// Initialize the plugin
Image_Optimizer_Plugin::get_instance();
