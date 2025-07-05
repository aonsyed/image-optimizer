<?php
/**
 * Admin UI class
 *
 * @package ImageOptimizer
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI class
 */
class Admin_UI {

	/**
	 * Admin notices
	 *
	 * @var array
	 */
	private static $notices = array();

	/**
	 * Initialize the admin UI
	 */
	public static function init() {
		// Add settings page
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

		// Add bulk action button
		add_filter( 'bulk_actions-upload', array( __CLASS__, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-upload', array( __CLASS__, 'handle_bulk_actions' ), 10, 3 );
		
		// Add single convert button
		add_filter( 'media_row_actions', array( __CLASS__, 'add_convert_button' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'handle_single_conversion' ) );

		// Add media library columns
		add_filter( 'manage_media_columns', array( __CLASS__, 'add_media_columns' ) );
		add_action( 'manage_media_custom_column', array( __CLASS__, 'render_media_columns' ), 10, 2 );

		// Admin notices
		add_action( 'admin_notices', array( __CLASS__, 'display_admin_notices' ) );
	}

	/**
	 * Add settings page
	 */
	public static function add_settings_page() {
		add_options_page(
			esc_html__( 'Image Optimizer Settings', 'image-optimizer' ),
			esc_html__( 'Image Optimizer', 'image-optimizer' ),
			'manage_options',
			'image-optimizer',
			array( __CLASS__, 'create_settings_page' )
		);
	}

	/**
	 * Create settings page
	 */
	public static function create_settings_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'image-optimizer' ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<div class="image-optimizer-admin">
				<div class="image-optimizer-header">
					<h2><?php esc_html_e( 'Image Optimization Settings', 'image-optimizer' ); ?></h2>
					<p><?php esc_html_e( 'Configure how images are optimized and converted to modern formats.', 'image-optimizer' ); ?></p>
				</div>

				<div class="image-optimizer-content">
					<form method="post" action="options.php">
						<?php
						settings_fields( 'image_optimizer_settings' );
						do_settings_sections( 'image-optimizer' );
						submit_button();
						?>
					</form>

					<div class="image-optimizer-actions">
						<h3><?php esc_html_e( 'Bulk Actions', 'image-optimizer' ); ?></h3>
						<div class="action-buttons">
							<button type="button" id="schedule-bulk-conversion" class="button button-primary">
								<?php esc_html_e( 'Schedule Bulk Conversion', 'image-optimizer' ); ?>
							</button>
							<button type="button" id="clean-up-optimized-images" class="button button-secondary">
								<?php esc_html_e( 'Clean Up Optimized Images', 'image-optimizer' ); ?>
							</button>
						</div>
					</div>

					<div class="image-optimizer-toggles">
						<h3><?php esc_html_e( 'Conversion Settings', 'image-optimizer' ); ?></h3>
						<div class="toggle-settings">
							<div class="toggle-field">
								<label for="toggle-scheduler">
									<input type="checkbox" id="toggle-scheduler" <?php checked( get_option( 'image_optimizer_enable_scheduler', false ) ); ?>>
									<?php esc_html_e( 'Enable Scheduler', 'image-optimizer' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Automatically convert images in the background.', 'image-optimizer' ); ?></p>
							</div>

							<div class="toggle-field">
								<label for="toggle-conversion-on-upload">
									<input type="checkbox" id="toggle-conversion-on-upload" <?php checked( get_option( 'image_optimizer_convert_on_upload', true ) ); ?>>
									<?php esc_html_e( 'Convert on Upload', 'image-optimizer' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Automatically convert images when they are uploaded.', 'image-optimizer' ); ?></p>
							</div>

							<div class="toggle-field">
								<label for="toggle-remove-originals">
									<input type="checkbox" id="toggle-remove-originals" <?php checked( get_option( 'image_optimizer_remove_originals', false ) ); ?>>
									<?php esc_html_e( 'Remove Originals on Conversion', 'image-optimizer' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Delete original images after successful conversion (use with caution).', 'image-optimizer' ); ?></p>
							</div>

							<div class="toggle-field">
								<label for="set-conversion-format"><?php esc_html_e( 'Conversion Format', 'image-optimizer' ); ?></label>
								<select id="set-conversion-format" name="image_optimizer_conversion_format">
									<option value="both" <?php selected( get_option( 'image_optimizer_conversion_format', 'both' ), 'both' ); ?>>
										<?php esc_html_e( 'WebP and AVIF', 'image-optimizer' ); ?>
									</option>
									<option value="webp" <?php selected( get_option( 'image_optimizer_conversion_format', 'both' ), 'webp' ); ?>>
										<?php esc_html_e( 'WebP Only', 'image-optimizer' ); ?>
									</option>
									<option value="avif" <?php selected( get_option( 'image_optimizer_conversion_format', 'both' ), 'avif' ); ?>>
										<?php esc_html_e( 'AVIF Only', 'image-optimizer' ); ?>
									</option>
								</select>
								<p class="description"><?php esc_html_e( 'Choose which formats to convert images to.', 'image-optimizer' ); ?></p>
							</div>
						</div>
					</div>

					<div class="image-optimizer-status">
						<h3><?php esc_html_e( 'System Status', 'image-optimizer' ); ?></h3>
						<div class="status-grid">
							<div class="status-item">
								<span class="status-label"><?php esc_html_e( 'WebP Support:', 'image-optimizer' ); ?></span>
								<span class="status-value <?php echo Image_Converter::is_webp_supported() ? 'status-ok' : 'status-error'; ?>">
									<?php echo Image_Converter::is_webp_supported() ? esc_html__( 'Available', 'image-optimizer' ) : esc_html__( 'Not Available', 'image-optimizer' ); ?>
								</span>
							</div>
							<div class="status-item">
								<span class="status-label"><?php esc_html_e( 'AVIF Support:', 'image-optimizer' ); ?></span>
								<span class="status-value <?php echo Image_Converter::is_avif_supported() ? 'status-ok' : 'status-warning'; ?>">
									<?php echo Image_Converter::is_avif_supported() ? esc_html__( 'Available', 'image-optimizer' ) : esc_html__( 'Requires Imagick', 'image-optimizer' ); ?>
								</span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Register settings
	 */
	public static function register_settings() {
		register_setting( 'image_optimizer_settings', 'image_optimizer_webp_quality', array(
			'type'              => 'integer',
			'sanitize_callback' => array( __CLASS__, 'sanitize_quality' ),
			'default'           => 80,
		) );

		register_setting( 'image_optimizer_settings', 'image_optimizer_avif_quality', array(
			'type'              => 'integer',
			'sanitize_callback' => array( __CLASS__, 'sanitize_quality' ),
			'default'           => 80,
		) );

		register_setting( 'image_optimizer_settings', 'image_optimizer_excluded_sizes', array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize_excluded_sizes' ),
			'default'           => array(),
		) );

		add_settings_section(
			'image_optimizer_section',
			esc_html__( 'Quality Settings', 'image-optimizer' ),
			array( __CLASS__, 'settings_section_callback' ),
			'image-optimizer'
		);

		add_settings_field(
			'image_optimizer_webp_quality',
			esc_html__( 'WebP Quality', 'image-optimizer' ),
			array( __CLASS__, 'webp_quality_callback' ),
			'image-optimizer',
			'image_optimizer_section'
		);

		add_settings_field(
			'image_optimizer_avif_quality',
			esc_html__( 'AVIF Quality', 'image-optimizer' ),
			array( __CLASS__, 'avif_quality_callback' ),
			'image-optimizer',
			'image_optimizer_section'
		);

		add_settings_field(
			'image_optimizer_excluded_sizes',
			esc_html__( 'Excluded Sizes', 'image-optimizer' ),
			array( __CLASS__, 'excluded_sizes_callback' ),
			'image-optimizer',
			'image_optimizer_section'
		);
	}

	/**
	 * Settings section callback
	 */
	public static function settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure the quality settings for image conversion.', 'image-optimizer' ) . '</p>';
	}

	/**
	 * WebP quality callback
	 */
	public static function webp_quality_callback() {
		$webp_quality = get_option( 'image_optimizer_webp_quality', 80 );
		echo '<input type="number" name="image_optimizer_webp_quality" value="' . esc_attr( $webp_quality ) . '" min="0" max="100" step="1">';
		echo '<p class="description">' . esc_html__( 'Set the quality for WebP images (0-100). Higher values mean better quality but larger file sizes.', 'image-optimizer' ) . '</p>';
	}

	/**
	 * AVIF quality callback
	 */
	public static function avif_quality_callback() {
		$avif_quality = get_option( 'image_optimizer_avif_quality', 80 );
		echo '<input type="number" name="image_optimizer_avif_quality" value="' . esc_attr( $avif_quality ) . '" min="0" max="100" step="1">';
		echo '<p class="description">' . esc_html__( 'Set the quality for AVIF images (0-100). Higher values mean better quality but larger file sizes.', 'image-optimizer' ) . '</p>';
	}

	/**
	 * Excluded sizes callback
	 */
	public static function excluded_sizes_callback() {
		$excluded_sizes = get_option( 'image_optimizer_excluded_sizes', array() );
		$sizes = get_intermediate_image_sizes();
		
		echo '<div class="excluded-sizes-grid">';
		foreach ( $sizes as $size ) {
			$checked = in_array( $size, $excluded_sizes, true ) ? 'checked' : '';
			echo '<div class="size-checkbox">';
			echo '<input type="checkbox" name="image_optimizer_excluded_sizes[]" value="' . esc_attr( $size ) . '" ' . $checked . '>';
			echo '<label>' . esc_html( $size ) . '</label>';
			echo '</div>';
		}
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Select the image sizes to exclude from conversion.', 'image-optimizer' ) . '</p>';
	}

	/**
	 * Sanitize quality setting
	 *
	 * @param mixed $value Quality value.
	 * @return int Sanitized quality value.
	 */
	public static function sanitize_quality( $value ) {
		$value = absint( $value );
		return max( 0, min( 100, $value ) );
	}

	/**
	 * Sanitize excluded sizes
	 *
	 * @param mixed $value Excluded sizes value.
	 * @return array Sanitized excluded sizes.
	 */
	public static function sanitize_excluded_sizes( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_map( 'sanitize_text_field', $value );
	}

	/**
	 * Register bulk actions
	 *
	 * @param array $bulk_actions Bulk actions.
	 * @return array
	 */
	public static function register_bulk_actions( $bulk_actions ) {
		$bulk_actions['convert_images'] = esc_html__( 'Convert to WebP/AVIF', 'image-optimizer' );
		return $bulk_actions;
	}

	/**
	 * Handle bulk actions
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $doaction Action to perform.
	 * @param array  $post_ids Post IDs.
	 * @return string
	 */
	public static function handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
		if ( 'convert_images' !== $doaction ) {
			return $redirect_to;
		}

		$converted_count = 0;
		foreach ( $post_ids as $post_id ) {
			$image_path = get_attached_file( $post_id );
			if ( $image_path && file_exists( $image_path ) ) {
				try {
					$data = Image_Converter::convert_image( $image_path );
					if ( $data ) {
						update_post_meta( $post_id, '_image_optimizer_optimized', true );
						update_post_meta( $post_id, '_image_optimizer_data', $data );
						$converted_count++;
					}
				} catch ( Exception $e ) {
					Logger::log( 'Error converting image ID ' . $post_id . ': ' . $e->getMessage() );
				}
			}
		}

		$redirect_to = add_query_arg( 'bulk_converted', $converted_count, $redirect_to );
		return $redirect_to;
	}

	/**
	 * Add convert button to media row actions
	 *
	 * @param array    $actions Row actions.
	 * @param WP_Post  $post Post object.
	 * @return array
	 */
	public static function add_convert_button( $actions, $post ) {
		$is_optimized = get_post_meta( $post->ID, '_image_optimizer_optimized', true );
		$mime_type = $post->post_mime_type;
		
		if ( in_array( $mime_type, array( 'image/jpeg', 'image/png', 'image/gif' ), true ) && ! $is_optimized ) {
			$actions['convert_to_webp_avif'] = sprintf(
				'<a href="%s" class="convert-to-webp-avif" data-id="%d">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'upload.php?convert_to_webp_avif=' . $post->ID ), 'convert_image_' . $post->ID ) ),
				esc_attr( $post->ID ),
				esc_html__( 'Convert to WebP/AVIF', 'image-optimizer' )
			);
		}
		return $actions;
	}

	/**
	 * Handle single conversion
	 */
	public static function handle_single_conversion() {
		if ( ! isset( $_GET['convert_to_webp_avif'] ) ) {
			return;
		}

		$post_id = absint( $_GET['convert_to_webp_avif'] );
		
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'convert_image_' . $post_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'image-optimizer' ) );
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'image-optimizer' ) );
		}

		$image_path = get_attached_file( $post_id );
		if ( $image_path && file_exists( $image_path ) ) {
			try {
				$data = Image_Converter::convert_image( $image_path );
				if ( $data ) {
					update_post_meta( $post_id, '_image_optimizer_optimized', true );
					update_post_meta( $post_id, '_image_optimizer_data', $data );
					self::add_admin_notice( esc_html__( 'Image converted successfully.', 'image-optimizer' ), 'success' );
				}
			} catch ( Exception $e ) {
				Logger::log( 'Error converting image ID ' . $post_id . ': ' . $e->getMessage() );
				self::add_admin_notice( esc_html__( 'Error converting image: ', 'image-optimizer' ) . $e->getMessage(), 'error' );
			}
		}

		wp_redirect( remove_query_arg( array( 'convert_to_webp_avif', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Add media columns
	 *
	 * @param array $columns Media columns.
	 * @return array
	 */
	public static function add_media_columns( $columns ) {
		$columns['image_optimizer'] = esc_html__( 'Image Optimizer', 'image-optimizer' );
		return $columns;
	}

	/**
	 * Render media columns
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render_media_columns( $column_name, $post_id ) {
		if ( 'image_optimizer' !== $column_name ) {
			return;
		}

		$is_optimized = get_post_meta( $post_id, '_image_optimizer_optimized', true );
		$data = get_post_meta( $post_id, '_image_optimizer_data', true );
		
		if ( $is_optimized && $data ) {
			$original_size = isset( $data['original_size'] ) ? $data['original_size'] : 0;
			$webp_size = isset( $data['webp_size'] ) ? $data['webp_size'] : 0;
			$avif_size = isset( $data['avif_size'] ) ? $data['avif_size'] : 0;

			echo '<div class="optimization-stats">';
			if ( $original_size > 0 ) {
				echo '<div class="stat-item">';
				echo '<span class="stat-label">' . esc_html__( 'Original:', 'image-optimizer' ) . '</span>';
				echo '<span class="stat-value">' . esc_html( size_format( $original_size ) ) . '</span>';
				echo '</div>';
			}
			
			if ( $webp_size > 0 ) {
				$webp_savings = $original_size - $webp_size;
				$webp_percent = $original_size > 0 ? round( ( $webp_savings / $original_size ) * 100, 1 ) : 0;
				echo '<div class="stat-item">';
				echo '<span class="stat-label">' . esc_html__( 'WebP:', 'image-optimizer' ) . '</span>';
				echo '<span class="stat-value">' . esc_html( size_format( $webp_size ) ) . ' (' . esc_html( $webp_percent ) . '% smaller)</span>';
				echo '</div>';
			}
			
			if ( $avif_size > 0 ) {
				$avif_savings = $original_size - $avif_size;
				$avif_percent = $original_size > 0 ? round( ( $avif_savings / $original_size ) * 100, 1 ) : 0;
				echo '<div class="stat-item">';
				echo '<span class="stat-label">' . esc_html__( 'AVIF:', 'image-optimizer' ) . '</span>';
				echo '<span class="stat-value">' . esc_html( size_format( $avif_size ) ) . ' (' . esc_html( $avif_percent ) . '% smaller)</span>';
				echo '</div>';
			}
			echo '</div>';
		} else {
			echo '<span class="not-optimized">' . esc_html__( 'Not optimized', 'image-optimizer' ) . '</span>';
		}
	}

	/**
	 * Add admin notice
	 *
	 * @param string $message Notice message.
	 * @param string $type Notice type.
	 */
	public static function add_admin_notice( $message, $type = 'success' ) {
		self::$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);
	}

	/**
	 * Display admin notices
	 */
	public static function display_admin_notices() {
		foreach ( self::$notices as $notice ) {
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $notice['type'] ),
				esc_html( $notice['message'] )
			);
		}

		// Display bulk action notices
		if ( isset( $_GET['bulk_converted'] ) ) {
			$converted_count = absint( $_GET['bulk_converted'] );
			if ( $converted_count > 0 ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					sprintf(
						esc_html( _n( '%d image converted successfully.', '%d images converted successfully.', $converted_count, 'image-optimizer' ) ),
						$converted_count
					)
				);
			}
		}
	}
}
