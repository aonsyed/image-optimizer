<?php
/**
 * Plugin Name: WP Image Optimizer
 * Plugin URI: https://github.com/your-username/wp-image-optimizer
 * Description: Automatically converts images to modern formats (WebP, AVIF) using ImageMagick or GD libraries for improved website performance.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-image-optimizer
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'WP_IMAGE_OPTIMIZER_VERSION', '1.0.0' );
define( 'WP_IMAGE_OPTIMIZER_PLUGIN_FILE', __FILE__ );
define( 'WP_IMAGE_OPTIMIZER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_IMAGE_OPTIMIZER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_IMAGE_OPTIMIZER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Check WordPress and PHP version compatibility
if ( ! function_exists( 'wp_image_optimizer_check_requirements' ) ) {
	/**
	 * Check if the current environment meets plugin requirements
	 *
	 * @return bool True if requirements are met, false otherwise
	 */
	function wp_image_optimizer_check_requirements() {
		global $wp_version;
		
		$min_wp_version = '5.0';
		$min_php_version = '7.4';
		
		// Check WordPress version
		if ( version_compare( $wp_version, $min_wp_version, '<' ) ) {
			add_action( 'admin_notices', function() use ( $min_wp_version ) {
				echo '<div class="notice notice-error"><p>';
				printf( 
					/* translators: %s: minimum WordPress version */
					esc_html__( 'WP Image Optimizer requires WordPress %s or higher. Please update WordPress.', 'wp-image-optimizer' ),
					esc_html( $min_wp_version )
				);
				echo '</p></div>';
			} );
			return false;
		}
		
		// Check PHP version
		if ( version_compare( PHP_VERSION, $min_php_version, '<' ) ) {
			add_action( 'admin_notices', function() use ( $min_php_version ) {
				echo '<div class="notice notice-error"><p>';
				printf( 
					/* translators: %s: minimum PHP version */
					esc_html__( 'WP Image Optimizer requires PHP %s or higher. Please update PHP.', 'wp-image-optimizer' ),
					esc_html( $min_php_version )
				);
				echo '</p></div>';
			} );
			return false;
		}
		
		return true;
	}
}

// Only proceed if requirements are met
if ( ! wp_image_optimizer_check_requirements() ) {
	return;
}

// Include the main plugin class
require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-wp-image-optimizer.php';

/**
 * Initialize the plugin
 *
 * @return WP_Image_Optimizer|null Plugin instance or null if requirements not met
 */
function wp_image_optimizer() {
	return WP_Image_Optimizer::get_instance();
}

// Initialize the plugin
add_action( 'plugins_loaded', 'wp_image_optimizer' );

// Activation hook
register_activation_hook( __FILE__, array( 'WP_Image_Optimizer', 'activate' ) );

// Deactivation hook
register_deactivation_hook( __FILE__, array( 'WP_Image_Optimizer', 'deactivate' ) );

// Uninstall hook
register_uninstall_hook( __FILE__, array( 'WP_Image_Optimizer', 'uninstall' ) );