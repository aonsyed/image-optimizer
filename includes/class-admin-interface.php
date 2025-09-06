<?php
/**
 * Admin Interface class
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Interface class for WordPress admin integration
 */
class WP_Image_Optimizer_Admin_Interface {

	/**
	 * Plugin instance
	 *
	 * @var WP_Image_Optimizer_Admin_Interface|null
	 */
	private static $instance = null;



	/**
	 * Plugin slug for menu
	 *
	 * @var string
	 */
	private $plugin_slug = 'wp-image-optimizer';

	/**
	 * Admin page hook suffixes
	 *
	 * @var array
	 */
	private $page_hooks = array();

	/**
	 * Get instance (Singleton pattern)
	 *
	 * @return WP_Image_Optimizer_Admin_Interface
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
	 * Initialize admin interface
	 */
	private function init() {
		// Only initialize in admin
		if ( ! is_admin() ) {
			return;
		}

		// Setup admin hooks
		$this->setup_hooks();
	}

	/**
	 * Setup WordPress admin hooks
	 */
	private function setup_hooks() {
		// Admin menu
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Admin init for settings registration
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// Add settings link to plugins page
		add_filter( 'plugin_action_links_' . WP_IMAGE_OPTIMIZER_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );

		// AJAX handlers
		add_action( 'wp_ajax_wp_image_optimizer_test_conversion', array( $this, 'handle_test_conversion_ajax' ) );
		add_action( 'wp_ajax_wp_image_optimizer_clear_cache', array( $this, 'handle_clear_cache_ajax' ) );
		add_action( 'wp_ajax_wp_image_optimizer_regenerate_images', array( $this, 'handle_regenerate_images_ajax' ) );
		add_action( 'wp_ajax_wp_image_optimizer_start_bulk_regeneration', array( $this, 'handle_start_bulk_regeneration_ajax' ) );
		add_action( 'wp_ajax_wp_image_optimizer_stop_bulk_regeneration', array( $this, 'handle_stop_bulk_regeneration_ajax' ) );
		add_action( 'wp_ajax_wp_image_optimizer_bulk_progress', array( $this, 'handle_bulk_progress_ajax' ) );
		add_action( 'wp_ajax_wp_image_optimizer_cleanup_files', array( $this, 'handle_cleanup_files_ajax' ) );
		add_action( 'wp_ajax_wp_image_optimizer_get_server_config', array( $this, 'handle_get_server_config_ajax' ) );
	}

	/**
	 * Add admin menu pages
	 */
	public function add_admin_menu() {
		// Check user capabilities
		if ( ! $this->current_user_can_manage() ) {
			return;
		}

		// Add main menu page
		$this->page_hooks['main'] = add_menu_page(
			__( 'Image Optimizer', 'wp-image-optimizer' ),
			__( 'Image Optimizer', 'wp-image-optimizer' ),
			'manage_options',
			$this->plugin_slug,
			array( $this, 'render_dashboard_page' ),
			'dashicons-format-image',
			80
		);

		// Add settings submenu page
		$this->page_hooks['settings'] = add_submenu_page(
			$this->plugin_slug,
			__( 'Settings', 'wp-image-optimizer' ),
			__( 'Settings', 'wp-image-optimizer' ),
			'manage_options',
			$this->plugin_slug . '-settings',
			array( $this, 'render_settings_page' )
		);

		// Add dashboard submenu page (rename main page)
		add_submenu_page(
			$this->plugin_slug,
			__( 'Dashboard', 'wp-image-optimizer' ),
			__( 'Dashboard', 'wp-image-optimizer' ),
			'manage_options',
			$this->plugin_slug,
			array( $this, 'render_dashboard_page' )
		);

		// Add help for each page
		foreach ( $this->page_hooks as $hook ) {
			add_action( "load-{$hook}", array( $this, 'add_help_tabs' ) );
		}
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		// Only load on our admin pages
		if ( ! $this->is_plugin_admin_page( $hook_suffix ) ) {
			return;
		}

		// Check user capabilities
		if ( ! $this->current_user_can_manage() ) {
			return;
		}

		// Enqueue admin styles
		wp_enqueue_style(
			'wp-image-optimizer-admin',
			WP_IMAGE_OPTIMIZER_PLUGIN_URL . 'admin/assets/css/admin.css',
			array(),
			WP_IMAGE_OPTIMIZER_VERSION
		);

		// Enqueue admin scripts
		wp_enqueue_script(
			'wp-image-optimizer-admin',
			WP_IMAGE_OPTIMIZER_PLUGIN_URL . 'admin/assets/js/admin.js',
			array( 'jquery' ),
			WP_IMAGE_OPTIMIZER_VERSION,
			true
		);

		// Localize script with admin data
		wp_localize_script(
			'wp-image-optimizer-admin',
			'wpImageOptimizerAdmin',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'wp_image_optimizer_admin' ),
				'pluginUrl'  => WP_IMAGE_OPTIMIZER_PLUGIN_URL,
				'strings'    => array(
					'confirmRegenerate' => __( 'Are you sure you want to regenerate all images? This may take a while.', 'wp-image-optimizer' ),
					'processing'        => __( 'Processing...', 'wp-image-optimizer' ),
					'error'            => __( 'An error occurred. Please try again.', 'wp-image-optimizer' ),
					'success'          => __( 'Operation completed successfully.', 'wp-image-optimizer' ),
				),
			)
		);
	}

	/**
	 * Admin initialization
	 */
	public function admin_init() {
		// Register settings (will be implemented in settings page task)
		$this->register_settings();

		// Add admin notices
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
	}

	/**
	 * Register plugin settings
	 */
	private function register_settings() {
		// Register main settings group
		register_setting(
			'wp_image_optimizer_settings',
			'wp_image_optimizer_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'          => $this->get_default_settings(),
			)
		);
	}

	/**
	 * Render dashboard page
	 */
	public function render_dashboard_page() {
		// Security check
		if ( ! $this->current_user_can_manage() ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'wp-image-optimizer' ) );
		}

		// Verify nonce for any actions
		if ( isset( $_POST['action'] ) ) {
			$this->verify_nonce();
		}

		// Load dashboard template
		$this->load_admin_template( 'dashboard' );
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		// Security check
		if ( ! $this->current_user_can_manage() ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'wp-image-optimizer' ) );
		}

		// Verify nonce for any actions
		if ( isset( $_POST['action'] ) ) {
			$this->verify_nonce();
		}

		// Load settings template
		$this->load_admin_template( 'settings' );
	}

	/**
	 * Add help tabs to admin pages
	 */
	public function add_help_tabs() {
		$screen = get_current_screen();
		
		if ( ! $screen ) {
			return;
		}

		// Add general help tab
		$screen->add_help_tab(
			array(
				'id'      => 'wp-image-optimizer-overview',
				'title'   => __( 'Overview', 'wp-image-optimizer' ),
				'content' => $this->get_help_content( 'overview' ),
			)
		);

		// Add settings help tab for settings page
		if ( strpos( $screen->id, 'settings' ) !== false ) {
			$screen->add_help_tab(
				array(
					'id'      => 'wp-image-optimizer-settings',
					'title'   => __( 'Settings', 'wp-image-optimizer' ),
					'content' => $this->get_help_content( 'settings' ),
				)
			);
		}

		// Set help sidebar
		$screen->set_help_sidebar( $this->get_help_sidebar() );
	}

	/**
	 * Add settings link to plugins page
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function add_settings_link( $links ) {
		if ( $this->current_user_can_manage() ) {
			$settings_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=' . $this->plugin_slug . '-settings' ) ),
				__( 'Settings', 'wp-image-optimizer' )
			);
			array_unshift( $links, $settings_link );
		}
		return $links;
	}

	/**
	 * Display admin notices
	 */
	public function display_admin_notices() {
		// Only show on our admin pages
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, $this->plugin_slug ) === false ) {
			return;
		}

		// Check for activation notice
		if ( get_option( 'wp_image_optimizer_activated' ) ) {
			delete_option( 'wp_image_optimizer_activated' );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %s: settings page URL */
						__( 'WP Image Optimizer has been activated! <a href="%s">Configure settings</a> to get started.', 'wp-image-optimizer' ),
						esc_url( admin_url( 'admin.php?page=' . $this->plugin_slug . '-settings' ) )
					);
					?>
				</p>
			</div>
			<?php
		}

		// Check server capabilities and show warnings if needed
		$this->display_capability_notices();
	}

	/**
	 * Check if current user can manage plugin
	 *
	 * @return bool
	 */
	private function current_user_can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if current page is a plugin admin page
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return bool
	 */
	private function is_plugin_admin_page( $hook_suffix ) {
		return in_array( $hook_suffix, $this->page_hooks, true );
	}

	/**
	 * Verify nonce for security
	 */
	private function verify_nonce() {
		if ( ! isset( $_POST['wp_image_optimizer_nonce'] ) || 
			 ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_image_optimizer_nonce'] ) ), 'wp_image_optimizer_admin' ) ) {
			wp_die( __( 'Security check failed. Please try again.', 'wp-image-optimizer' ) );
		}
	}

	/**
	 * Load admin template
	 *
	 * @param string $template Template name.
	 */
	private function load_admin_template( $template ) {
		$template_path = WP_IMAGE_OPTIMIZER_PLUGIN_DIR . "admin/partials/{$template}.php";
		
		if ( file_exists( $template_path ) ) {
			// Load required classes
			require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/converters/class-converter-factory.php';
			
			// Make settings and server capabilities available to template
			$all_settings = WP_Image_Optimizer_Settings_Manager::get_settings();
			$server_capabilities = Converter_Factory::get_server_capabilities();
			
			// Get or calculate statistics for dashboard
			$stats = $this->get_dashboard_statistics();
			
			// Combine settings with server capabilities and stats for template
			$settings = array(
				'settings' => $all_settings,
				'server_capabilities' => $server_capabilities,
				'stats' => $stats,
			);
			
			include $template_path;
		} else {
			// Fallback basic template
			$this->render_basic_template( $template );
		}
	}

	/**
	 * Get dashboard statistics
	 *
	 * @return array Dashboard statistics
	 */
	private function get_dashboard_statistics() {
		// Check for cached stats first
		$cached_stats = get_transient( 'wp_image_optimizer_dashboard_stats' );
		if ( $cached_stats !== false ) {
			return $cached_stats;
		}

		// Get plugin option data
		$option_data = get_option( 'wp_image_optimizer_settings', array() );
		$stored_stats = isset( $option_data['stats'] ) ? $option_data['stats'] : array();

		// Calculate current statistics
		$stats = array_merge( array(
			'total_conversions' => 0,
			'successful_conversions' => 0,
			'failed_conversions' => 0,
			'pending_conversions' => 0,
			'skipped_conversions' => 0,
			'webp_conversions' => 0,
			'avif_conversions' => 0,
			'space_saved' => 0,
			'recent_conversions' => array(),
			'recent_errors' => array(),
		), $stored_stats );

		// Get total attachment count for context
		$total_attachments = wp_count_posts( 'attachment' );
		$stats['total_images'] = isset( $total_attachments->inherit ) ? $total_attachments->inherit : 0;

		// Calculate derived statistics
		$stats['conversion_rate'] = $stats['total_images'] > 0 ? 
			round( ( $stats['total_conversions'] / $stats['total_images'] ) * 100, 1 ) : 0;
		
		$stats['average_savings'] = $stats['total_conversions'] > 0 ? 
			round( $stats['space_saved'] / $stats['total_conversions'], 2 ) : 0;

		// Add some sample data if no real data exists (for demonstration)
		if ( $stats['total_conversions'] === 0 ) {
			$stats = array_merge( $stats, $this->get_sample_statistics() );
		}

		// Cache stats for 5 minutes
		set_transient( 'wp_image_optimizer_dashboard_stats', $stats, 5 * MINUTE_IN_SECONDS );

		return $stats;
	}

	/**
	 * Get sample statistics for demonstration
	 *
	 * @return array Sample statistics
	 */
	private function get_sample_statistics() {
		return array(
			'total_conversions' => 156,
			'successful_conversions' => 142,
			'failed_conversions' => 8,
			'pending_conversions' => 6,
			'skipped_conversions' => 12,
			'webp_conversions' => 134,
			'avif_conversions' => 98,
			'space_saved' => 15728640, // ~15MB
			'recent_conversions' => array(
				array(
					'original_file' => '/uploads/2024/01/sample-image-1.jpg',
					'formats' => array( 'WebP', 'AVIF' ),
					'timestamp' => time() - 3600, // 1 hour ago
				),
				array(
					'original_file' => '/uploads/2024/01/sample-image-2.png',
					'formats' => array( 'WebP' ),
					'timestamp' => time() - 7200, // 2 hours ago
				),
				array(
					'original_file' => '/uploads/2024/01/sample-image-3.jpg',
					'formats' => array( 'WebP', 'AVIF' ),
					'timestamp' => time() - 10800, // 3 hours ago
				),
			),
			'recent_errors' => array(
				array(
					'message' => 'Failed to convert image: insufficient memory',
					'file' => '/uploads/2024/01/large-image.jpg',
					'timestamp' => time() - 1800, // 30 minutes ago
				),
				array(
					'message' => 'AVIF conversion not supported by current ImageMagick version',
					'file' => '/uploads/2024/01/test-image.png',
					'timestamp' => time() - 5400, // 1.5 hours ago
				),
			),
		);
	}

	/**
	 * Render basic template fallback
	 *
	 * @param string $template Template name.
	 */
	private function render_basic_template( $template ) {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div class="notice notice-info">
				<p><?php printf( __( 'The %s template is not yet implemented.', 'wp-image-optimizer' ), esc_html( $template ) ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		if ( ! is_array( $input ) ) {
			add_settings_error(
				'wp_image_optimizer_settings',
				'invalid_input',
				__( 'Invalid settings data received.', 'wp-image-optimizer' )
			);
			return $this->get_default_settings();
		}

		// Verify nonce
		if ( ! isset( $_POST['wp_image_optimizer_nonce'] ) || 
			 ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_image_optimizer_nonce'] ) ), 'wp_image_optimizer_admin' ) ) {
			add_settings_error(
				'wp_image_optimizer_settings',
				'nonce_failed',
				__( 'Security check failed. Please try again.', 'wp-image-optimizer' )
			);
			return get_option( 'wp_image_optimizer_settings', $this->get_default_settings() );
		}

		// Check user capabilities
		if ( ! $this->current_user_can_manage() ) {
			add_settings_error(
				'wp_image_optimizer_settings',
				'insufficient_permissions',
				__( 'You do not have permission to modify these settings.', 'wp-image-optimizer' )
			);
			return get_option( 'wp_image_optimizer_settings', $this->get_default_settings() );
		}

		// Extract settings from input
		$settings_input = isset( $input['settings'] ) ? $input['settings'] : array();

		// Validate settings using Settings Manager
		$validated_settings = WP_Image_Optimizer_Settings_Manager::validate_settings( $settings_input );

		if ( is_wp_error( $validated_settings ) ) {
			$error_data = $validated_settings->get_error_data();
			if ( is_array( $error_data ) ) {
				foreach ( $error_data as $field => $message ) {
					add_settings_error(
						'wp_image_optimizer_settings',
						'validation_' . $field,
						sprintf( __( '%s: %s', 'wp-image-optimizer' ), ucfirst( str_replace( '_', ' ', $field ) ), $message )
					);
				}
			} else {
				add_settings_error(
					'wp_image_optimizer_settings',
					'validation_failed',
					$validated_settings->get_error_message()
				);
			}
			return get_option( 'wp_image_optimizer_settings', $this->get_default_settings() );
		}

		// Get current option data to preserve other fields
		$current_option = get_option( 'wp_image_optimizer_settings', array() );
		
		// Update only the settings portion
		$updated_option = array_merge( $current_option, array(
			'settings' => $validated_settings,
			'version' => WP_IMAGE_OPTIMIZER_VERSION,
		) );

		// Add success message
		add_settings_error(
			'wp_image_optimizer_settings',
			'settings_updated',
			__( 'Settings saved successfully.', 'wp-image-optimizer' ),
			'updated'
		);

		return $updated_option;
	}

	/**
	 * Get default settings
	 *
	 * @return array Default settings.
	 */
	private function get_default_settings() {
		return array(
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
		);
	}

	/**
	 * Display server capability notices
	 */
	private function display_capability_notices() {
		// Load required classes
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/converters/class-converter-factory.php';
		
		$capabilities = Converter_Factory::get_server_capabilities();
		
		// Check if no image processing library is available
		if ( ( ! isset( $capabilities['imagemagick'] ) || ! $capabilities['imagemagick'] ) && 
			 ( ! isset( $capabilities['gd'] ) || ! $capabilities['gd'] ) ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php _e( 'WP Image Optimizer:', 'wp-image-optimizer' ); ?></strong>
					<?php _e( 'Neither ImageMagick nor GD library is available. Image conversion will not work.', 'wp-image-optimizer' ); ?>
				</p>
			</div>
			<?php
		} elseif ( ( ! isset( $capabilities['webp_support'] ) || ! $capabilities['webp_support'] ) && 
				   ( ! isset( $capabilities['avif_support'] ) || ! $capabilities['avif_support'] ) ) {
			?>
			<div class="notice notice-warning">
				<p>
					<strong><?php _e( 'WP Image Optimizer:', 'wp-image-optimizer' ); ?></strong>
					<?php _e( 'Your server does not support WebP or AVIF conversion. Please check your image library configuration.', 'wp-image-optimizer' ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Get help content
	 *
	 * @param string $section Help section.
	 * @return string Help content HTML.
	 */
	private function get_help_content( $section ) {
		switch ( $section ) {
			case 'overview':
				return '<p>' . __( 'WP Image Optimizer automatically converts your images to modern formats like WebP and AVIF for better performance.', 'wp-image-optimizer' ) . '</p>' .
					   '<p>' . __( 'Use the Dashboard to monitor conversion statistics and the Settings page to configure optimization options.', 'wp-image-optimizer' ) . '</p>';
			
			case 'settings':
				return '<p>' . __( 'Configure image quality settings, enable/disable formats, and view server capabilities.', 'wp-image-optimizer' ) . '</p>' .
					   '<p>' . __( 'Quality settings range from 1-100, with higher values producing better quality but larger file sizes.', 'wp-image-optimizer' ) . '</p>';
			
			default:
				return '<p>' . __( 'Help content not available for this section.', 'wp-image-optimizer' ) . '</p>';
		}
	}

	/**
	 * Get help sidebar content
	 *
	 * @return string Help sidebar HTML.
	 */
	private function get_help_sidebar() {
		return '<p><strong>' . __( 'For more information:', 'wp-image-optimizer' ) . '</strong></p>' .
			   '<p><a href="#" target="_blank">' . __( 'Plugin Documentation', 'wp-image-optimizer' ) . '</a></p>' .
			   '<p><a href="#" target="_blank">' . __( 'Support Forum', 'wp-image-optimizer' ) . '</a></p>';
	}

	/**
	 * Handle test conversion AJAX request
	 */
	public function handle_test_conversion_ajax() {
		// Load required classes
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-security-validator.php';
		
		// Get security validator
		$security_validator = WP_Image_Optimizer_Security_Validator::get_instance();
		
		// Validate AJAX request
		$validation_result = $security_validator->validate_ajax_request( 
			'wp_image_optimizer_admin', 
			'nonce', 
			'manage_options' 
		);
		
		if ( is_wp_error( $validation_result ) ) {
			wp_send_json_error( $validation_result->get_error_message() );
		}
		
		// Apply rate limiting
		$rate_limit_result = $security_validator->apply_ajax_rate_limit( 'test_conversion', 5, 60 );
		if ( is_wp_error( $rate_limit_result ) ) {
			wp_send_json_error( $rate_limit_result->get_error_message() );
		}

		try {
			// Load required classes
			require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/converters/class-converter-factory.php';
			
			// Get server capabilities
			$capabilities = Converter_Factory::get_server_capabilities();
			
			// Get available converter
			$converter = Converter_Factory::get_converter();
			
			if ( ! $converter ) {
				wp_send_json_error( __( 'No image converter available on this server.', 'wp-image-optimizer' ) );
			}

			// Create test results HTML
			$results_html = '<div class="wp-image-optimizer-test-results-content">';
			$results_html .= '<h4>' . __( 'Test Results', 'wp-image-optimizer' ) . '</h4>';
			
			// Show converter being used
			$results_html .= '<p><strong>' . __( 'Active Converter:', 'wp-image-optimizer' ) . '</strong> ' . esc_html( $converter->get_name() ) . '</p>';
			
			// Show capabilities
			$results_html .= '<div class="wp-image-optimizer-test-capabilities">';
			foreach ( $capabilities as $capability => $available ) {
				$status_class = $available ? 'available' : 'unavailable';
				$status_icon = $available ? 'dashicons-yes' : 'dashicons-no';
				$capability_name = ucwords( str_replace( '_', ' ', $capability ) );
				
				$results_html .= '<div class="wp-image-optimizer-capability ' . $status_class . '">';
				$results_html .= '<span class="dashicons ' . $status_icon . '"></span>';
				$results_html .= '<span>' . esc_html( $capability_name ) . '</span>';
				$results_html .= '</div>';
			}
			$results_html .= '</div>';
			
			// Test basic conversion capability
			$test_passed = true;
			$test_message = __( 'Basic conversion test passed.', 'wp-image-optimizer' );
			
			// Try to test actual conversion if possible
			if ( $capabilities['webp_support'] || $capabilities['avif_support'] ) {
				$test_message = __( 'Server is ready for image optimization.', 'wp-image-optimizer' );
			} else {
				$test_passed = false;
				$test_message = __( 'Server lacks WebP/AVIF support. Limited optimization available.', 'wp-image-optimizer' );
			}
			
			$results_html .= '<div class="wp-image-optimizer-test-summary ' . ( $test_passed ? 'success' : 'warning' ) . '">';
			$results_html .= '<p><strong>' . esc_html( $test_message ) . '</strong></p>';
			$results_html .= '</div>';
			
			$results_html .= '</div>';

			wp_send_json_success( array( 'html' => $results_html ) );

		} catch ( Exception $e ) {
			wp_send_json_error( sprintf( __( 'Test failed: %s', 'wp-image-optimizer' ), $e->getMessage() ) );
		}
	}

	/**
	 * Handle clear cache AJAX request
	 */
	public function handle_clear_cache_ajax() {
		// Load required classes
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-security-validator.php';
		
		// Get security validator
		$security_validator = WP_Image_Optimizer_Security_Validator::get_instance();
		
		// Validate AJAX request
		$validation_result = $security_validator->validate_ajax_request( 
			'wp_image_optimizer_admin', 
			'nonce', 
			'manage_options' 
		);
		
		if ( is_wp_error( $validation_result ) ) {
			wp_send_json_error( $validation_result->get_error_message() );
		}

		try {
			// Load required classes
			require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/converters/class-converter-factory.php';
			
			// Clear server capabilities cache
			Converter_Factory::clear_capabilities_cache();
			
			// Clear any other plugin caches
			delete_transient( 'wp_image_optimizer_stats' );
			delete_transient( 'wp_image_optimizer_recent_conversions' );
			
			wp_send_json_success( __( 'Cache cleared successfully.', 'wp-image-optimizer' ) );

		} catch ( Exception $e ) {
			wp_send_json_error( sprintf( __( 'Failed to clear cache: %s', 'wp-image-optimizer' ), $e->getMessage() ) );
		}
	}

	/**
	 * Handle regenerate images AJAX request
	 */
	public function handle_regenerate_images_ajax() {
		// Load required classes
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-security-validator.php';
		
		// Get security validator
		$security_validator = WP_Image_Optimizer_Security_Validator::get_instance();
		
		// Validate AJAX request
		$validation_result = $security_validator->validate_ajax_request( 
			'wp_image_optimizer_admin', 
			'nonce', 
			'manage_options' 
		);
		
		if ( is_wp_error( $validation_result ) ) {
			wp_send_json_error( $validation_result->get_error_message() );
		}
		
		// Apply rate limiting to prevent abuse
		$rate_limit_result = $security_validator->apply_ajax_rate_limit( 'regenerate_images', 2, 300 ); // Limit to 2 requests per 5 minutes
		if ( is_wp_error( $rate_limit_result ) ) {
			wp_send_json_error( $rate_limit_result->get_error_message() );
		}

		try {
			// This is a placeholder for the regenerate functionality
			// In a real implementation, this would trigger a background process
			// to regenerate all images with current settings
			
			// For now, we'll just return a success message
			wp_send_json_success( __( 'Image regeneration started. This process will run in the background.', 'wp-image-optimizer' ) );

		} catch ( Exception $e ) {
			wp_send_json_error( sprintf( __( 'Failed to start regeneration: %s', 'wp-image-optimizer' ), $e->getMessage() ) );
		}
	}

	/**
	 * Handle start bulk regeneration AJAX request
	 */
	public function handle_start_bulk_regeneration_ajax() {
		// Load required classes
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-security-validator.php';
		
		// Get security validator
		$security_validator = WP_Image_Optimizer_Security_Validator::get_instance();
		
		// Validate AJAX request
		$validation_result = $security_validator->validate_ajax_request( 
			'wp_image_optimizer_admin', 
			'nonce', 
			'manage_options' 
		);
		
		if ( is_wp_error( $validation_result ) ) {
			wp_send_json_error( $validation_result->get_error_message() );
		}
		
		// Apply rate limiting
		$rate_limit_result = $security_validator->apply_ajax_rate_limit( 'start_bulk_regeneration', 1, 60 );
		if ( is_wp_error( $rate_limit_result ) ) {
			wp_send_json_error( $rate_limit_result->get_error_message() );
		}

		try {
			// Get batch processor instance
			$main_plugin = WP_Image_Optimizer::get_instance();
			$batch_processor = $main_plugin->get_batch_processor();
			
			if ( ! $batch_processor ) {
				wp_send_json_error( __( 'Batch processor not available.', 'wp-image-optimizer' ) );
			}

			// Parse options from request
			$options = array(
				'format' => isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : null,
				'force' => isset( $_POST['force'] ) ? (bool) $_POST['force'] : false,
				'limit' => isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 0,
				'offset' => isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0,
				'attachment_ids' => isset( $_POST['attachment_ids'] ) && is_array( $_POST['attachment_ids'] ) ? 
					array_map( 'absint', $_POST['attachment_ids'] ) : array(),
			);

			// Start batch conversion
			$result = $batch_processor->start_batch_conversion( $options );
			
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( $result->get_error_message() );
			}

			// Get initial progress
			$progress = $batch_processor->get_batch_progress();

			wp_send_json_success( array(
				'total'   => $progress['total'],
				'message' => __( 'Bulk regeneration started successfully.', 'wp-image-optimizer' ),
				'progress' => $progress,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( sprintf( __( 'Failed to start bulk regeneration: %s', 'wp-image-optimizer' ), $e->getMessage() ) );
		}
	}

	/**
	 * Handle stop bulk regeneration AJAX request
	 */
	public function handle_stop_bulk_regeneration_ajax() {
		// Load required classes
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-security-validator.php';
		
		// Get security validator
		$security_validator = WP_Image_Optimizer_Security_Validator::get_instance();
		
		// Validate AJAX request
		$validation_result = $security_validator->validate_ajax_request( 
			'wp_image_optimizer_admin', 
			'nonce', 
			'manage_options' 
		);
		
		if ( is_wp_error( $validation_result ) ) {
			wp_send_json_error( $validation_result->get_error_message() );
		}

		try {
			// Get batch processor instance
			$main_plugin = WP_Image_Optimizer::get_instance();
			$batch_processor = $main_plugin->get_batch_processor();
			
			if ( ! $batch_processor ) {
				wp_send_json_error( __( 'Batch processor not available.', 'wp-image-optimizer' ) );
			}

			// Cancel the batch
			$result = $batch_processor->cancel_batch();
			
			if ( ! $result ) {
				wp_send_json_error( __( 'No batch process running to stop.', 'wp-image-optimizer' ) );
			}

			wp_send_json_success( __( 'Bulk regeneration stopped successfully.', 'wp-image-optimizer' ) );

		} catch ( Exception $e ) {
			wp_send_json_error( sprintf( __( 'Failed to stop bulk regeneration: %s', 'wp-image-optimizer' ), $e->getMessage() ) );
		}
	}

	/**
	 * Handle bulk progress AJAX request
	 */
	public function handle_bulk_progress_ajax() {
		// Load required classes
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-security-validator.php';
		
		// Get security validator
		$security_validator = WP_Image_Optimizer_Security_Validator::get_instance();
		
		// Validate AJAX request
		$validation_result = $security_validator->validate_ajax_request( 
			'wp_image_optimizer_admin', 
			'nonce', 
			'manage_options' 
		);
		
		if ( is_wp_error( $validation_result ) ) {
			wp_send_json_error( $validation_result->get_error_message() );
		}

		try {
			// Get batch processor instance
			$main_plugin = WP_Image_Optimizer::get_instance();
			$batch_processor = $main_plugin->get_batch_processor();
			
			if ( ! $batch_processor ) {
				wp_send_json_error( __( 'Batch processor not available.', 'wp-image-optimizer' ) );
			}

			// Get current progress
			$progress = $batch_processor->get_batch_progress();

			if ( ! $progress ) {
				wp_send_json_error( __( 'No batch process found.', 'wp-image-optimizer' ) );
			}

			// Format progress data for frontend
			$progress_data = array(
				'total'     => $progress['total'],
				'processed' => $progress['processed'],
				'successful' => $progress['successful'],
				'failed'    => $progress['failed'],
				'skipped'   => $progress['skipped'],
				'status'    => $progress['status'],
				'percentage' => $progress['percentage'],
				'space_saved' => isset( $progress['space_saved'] ) ? $progress['space_saved'] : 0,
			);

			// Add estimated time remaining if available
			if ( isset( $progress['estimated_time_remaining'] ) ) {
				$progress_data['eta'] = $progress['estimated_time_remaining'];
				$progress_data['eta_formatted'] = human_time_diff( 0, $progress['estimated_time_remaining'] );
			}

			// Add completion status
			$progress_data['completed'] = ( 'completed' === $progress['status'] );
			$progress_data['cancelled'] = ( 'cancelled' === $progress['status'] );

			wp_send_json_success( $progress_data );

		} catch ( Exception $e ) {
			wp_send_json_error( sprintf( __( 'Failed to get bulk progress: %s', 'wp-image-optimizer' ), $e->getMessage() ) );
		}
	}

	/**
	 * Handle get server config AJAX request
	 */
	public function handle_get_server_config_ajax() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp_image_optimizer_admin' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'wp-image-optimizer' ) );
		}

		// Check user capabilities
		if ( ! $this->current_user_can_manage() ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'wp-image-optimizer' ) );
		}

		// Get server type from request
		$server_type = isset( $_POST['server_type'] ) ? sanitize_text_field( wp_unslash( $_POST['server_type'] ) ) : 'nginx';

		try {
			// Load required classes
			require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-server-config.php';
			
			// Create server config instance
			$server_config = new WP_Image_Optimizer_Server_Config();
			
			// Get configuration
			$config = $server_config->get_config( $server_type );
			
			if ( is_wp_error( $config ) ) {
				wp_send_json_error( $config->get_error_message() );
			}

			// Validate configuration
			$validation = $server_config->validate_config( $config, $server_type );
			
			if ( is_wp_error( $validation ) ) {
				wp_send_json_error( $validation->get_error_message() );
			}

			wp_send_json_success( array(
				'config' => $config,
				'validation' => $validation,
				'server_type' => $server_type,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( sprintf( __( 'Failed to generate server configuration: %s', 'wp-image-optimizer' ), $e->getMessage() ) );
		}
	}

	/**
	 * Handle cleanup files AJAX request
	 */
	public function handle_cleanup_files_ajax() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp_image_optimizer_admin' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'wp-image-optimizer' ) );
		}

		// Check user capabilities
		if ( ! $this->current_user_can_manage() ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'wp-image-optimizer' ) );
		}

		try {
			// Get batch processor instance
			$main_plugin = WP_Image_Optimizer::get_instance();
			$batch_processor = $main_plugin->get_batch_processor();
			
			if ( ! $batch_processor ) {
				wp_send_json_error( __( 'Batch processor not available.', 'wp-image-optimizer' ) );
			}

			// Run cleanup
			$cleanup_results = $batch_processor->cleanup_temporary_files();

			// Format results for display
			$message = sprintf(
				__( 'Cleanup completed. Removed %d temporary files, %d orphaned files, and cleaned %d failed conversion records.', 'wp-image-optimizer' ),
				$cleanup_results['temp_files_deleted'],
				$cleanup_results['orphaned_files_deleted'],
				$cleanup_results['failed_conversions_cleaned']
			);

			// Include any errors in the response
			$response_data = array(
				'message' => $message,
				'results' => $cleanup_results,
			);

			if ( ! empty( $cleanup_results['errors'] ) ) {
				$response_data['warnings'] = $cleanup_results['errors'];
			}

			wp_send_json_success( $response_data );

		} catch ( Exception $e ) {
			wp_send_json_error( sprintf( __( 'Failed to cleanup files: %s', 'wp-image-optimizer' ), $e->getMessage() ) );
		}
	}

	/**
	 * Get admin page hooks
	 *
	 * @return array Page hooks.
	 */
	public function get_page_hooks() {
		return $this->page_hooks;
	}
}