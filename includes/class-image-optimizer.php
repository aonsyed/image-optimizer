<?php
/**
 * Main Image Optimizer class
 *
 * @package ImageOptimizer
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Image Optimizer class
 */
class Image_Optimizer {

	/**
	 * Initialize the plugin
	 */
	public static function init() {
		// Hook into image upload
		add_filter( 'wp_handle_upload', array( __CLASS__, 'optimize_and_convert_image' ) );
		
		// Hook into image sizes generation
		add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'convert_attachment_metadata' ), 10, 2 );
		
		// Enqueue admin scripts
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		
		// Add AJAX actions
		add_action( 'wp_ajax_convert_image', array( __CLASS__, 'ajax_convert_image' ) );
		add_action( 'wp_ajax_schedule_bulk_conversion', array( __CLASS__, 'ajax_schedule_bulk_conversion' ) );
		add_action( 'wp_ajax_toggle_scheduler', array( __CLASS__, 'ajax_toggle_scheduler' ) );
		add_action( 'wp_ajax_toggle_conversion_on_upload', array( __CLASS__, 'ajax_toggle_conversion_on_upload' ) );
		add_action( 'wp_ajax_clean_up_optimized_images', array( __CLASS__, 'ajax_clean_up_optimized_images' ) );
		add_action( 'wp_ajax_toggle_remove_originals', array( __CLASS__, 'ajax_toggle_remove_originals' ) );
		add_action( 'wp_ajax_set_conversion_format', array( __CLASS__, 'ajax_set_conversion_format' ) );
		
		// Serve WebP/AVIF images
		add_filter( 'wp_get_attachment_url', array( 'Image_Servicer', 'serve_optimized_image' ), 10, 2 );

		// Initialize scheduler if enabled
		if ( get_option( 'image_optimizer_enable_scheduler', false ) ) {
			add_action( 'image_optimizer_bulk_conversion', array( 'Image_Scheduler', 'run_scheduled_conversion' ) );
		}
	}

	/**
	 * Optimize and convert image on upload
	 *
	 * @param array $file Upload file data.
	 * @return array
	 */
	public static function optimize_and_convert_image( $file ) {
		if ( get_option( 'image_optimizer_convert_on_upload', true ) ) {
			$image_path = $file['file'];
			
			// Ensure the file path is correct
			if ( file_exists( $image_path ) ) {
				try {
					Image_Converter::convert_image( $image_path );
				} catch ( Exception $e ) {
					Logger::log( 'Error converting uploaded image: ' . $e->getMessage() );
				}
			}
		}
		return $file;
	}

	/**
	 * Convert attachment metadata
	 *
	 * @param array $metadata Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array
	 */
	public static function convert_attachment_metadata( $metadata, $attachment_id ) {
		if ( ! get_option( 'image_optimizer_convert_on_upload', true ) ) {
			return $metadata;
		}

		$upload_dir = wp_upload_dir();
		$excluded_sizes = get_option( 'image_optimizer_excluded_sizes', array() );

		if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				// Skip excluded sizes
				if ( in_array( $size_name, $excluded_sizes, true ) ) {
					continue;
				}

				$image_path = $upload_dir['basedir'] . '/' . dirname( $metadata['file'] ) . '/' . $size_data['file'];
				
				if ( file_exists( $image_path ) ) {
					try {
						Image_Converter::convert_image( $image_path );
					} catch ( Exception $e ) {
						Logger::log( 'Error converting attachment metadata for ID ' . $attachment_id . ': ' . $e->getMessage() );
					}
				}
			}
		}

		return $metadata;
	}

	/**
	 * Enqueue admin scripts
	 */
	public static function enqueue_scripts() {
		$screen = get_current_screen();
		
		// Only load on relevant admin pages
		if ( ! in_array( $screen->id, array( 'upload', 'settings_page_image-optimizer' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'image-optimizer-admin',
			IMAGE_OPTIMIZER_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			IMAGE_OPTIMIZER_VERSION
		);

		wp_enqueue_script(
			'image-optimizer-admin',
			IMAGE_OPTIMIZER_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			IMAGE_OPTIMIZER_VERSION,
			true
		);

		wp_localize_script(
			'image-optimizer-admin',
			'imageOptimizerAjax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'image_optimizer_nonce' ),
				'strings'  => array(
					'converting' => esc_html__( 'Converting...', 'image-optimizer' ),
					'success'    => esc_html__( 'Success!', 'image-optimizer' ),
					'error'      => esc_html__( 'Error!', 'image-optimizer' ),
				),
			)
		);
	}

	/**
	 * AJAX handler for converting single image
	 */
	public static function ajax_convert_image() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'image_optimizer_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Security check failed.', 'image-optimizer' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'image-optimizer' ) );
		}

		$attachment_id = absint( $_POST['attachment_id'] );
		
		if ( ! $attachment_id ) {
			wp_send_json_error( esc_html__( 'Invalid attachment ID.', 'image-optimizer' ) );
		}

		$image_path = get_attached_file( $attachment_id );
		
		if ( ! $image_path || ! file_exists( $image_path ) ) {
			wp_send_json_error( esc_html__( 'Image file not found.', 'image-optimizer' ) );
		}

		try {
			$data = Image_Converter::convert_image( $image_path );
			if ( $data ) {
				update_post_meta( $attachment_id, '_image_optimizer_optimized', true );
				update_post_meta( $attachment_id, '_image_optimizer_data', $data );
				wp_send_json_success( esc_html__( 'Image converted successfully.', 'image-optimizer' ) );
			} else {
				wp_send_json_error( esc_html__( 'Failed to convert image.', 'image-optimizer' ) );
			}
		} catch ( Exception $e ) {
			Logger::log( 'Error converting image ID ' . $attachment_id . ': ' . $e->getMessage() );
			wp_send_json_error( esc_html__( 'Error converting image: ', 'image-optimizer' ) . $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for scheduling bulk conversion
	 */
	public static function ajax_schedule_bulk_conversion() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'image_optimizer_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Security check failed.', 'image-optimizer' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'image-optimizer' ) );
		}

		// Schedule the bulk conversion
		if ( ! wp_next_scheduled( 'image_optimizer_bulk_conversion' ) ) {
			wp_schedule_event( time(), 'hourly', 'image_optimizer_bulk_conversion' );
		}

		wp_send_json_success( esc_html__( 'Bulk conversion scheduled successfully.', 'image-optimizer' ) );
	}

	/**
	 * AJAX handler for toggling scheduler
	 */
	public static function ajax_toggle_scheduler() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'image_optimizer_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Security check failed.', 'image-optimizer' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'image-optimizer' ) );
		}

		$enabled = isset( $_POST['enabled'] ) ? (bool) $_POST['enabled'] : false;
		update_option( 'image_optimizer_enable_scheduler', $enabled );

		if ( $enabled && ! wp_next_scheduled( 'image_optimizer_bulk_conversion' ) ) {
			wp_schedule_event( time(), 'hourly', 'image_optimizer_bulk_conversion' );
		} elseif ( ! $enabled && wp_next_scheduled( 'image_optimizer_bulk_conversion' ) ) {
			wp_clear_scheduled_hook( 'image_optimizer_bulk_conversion' );
		}

		wp_send_json_success( esc_html__( 'Scheduler toggled successfully.', 'image-optimizer' ) );
	}

	/**
	 * AJAX handler for toggling conversion on upload
	 */
	public static function ajax_toggle_conversion_on_upload() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'image_optimizer_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Security check failed.', 'image-optimizer' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'image-optimizer' ) );
		}

		$enabled = isset( $_POST['enabled'] ) ? (bool) $_POST['enabled'] : false;
		update_option( 'image_optimizer_convert_on_upload', $enabled );

		wp_send_json_success( esc_html__( 'Conversion on upload toggled successfully.', 'image-optimizer' ) );
	}

	/**
	 * AJAX handler for cleaning up optimized images
	 */
	public static function ajax_clean_up_optimized_images() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'image_optimizer_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Security check failed.', 'image-optimizer' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'image-optimizer' ) );
		}

		$attachments = get_posts( array(
			'post_type'      => 'attachment',
			'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif' ),
			'posts_per_page' => -1,
			'post_status'    => 'inherit',
			'meta_query'     => array(
				array(
					'key'     => '_image_optimizer_optimized',
					'compare' => 'EXISTS',
				),
			),
		) );

		$cleaned_count = 0;
		foreach ( $attachments as $attachment ) {
			$image_path = get_attached_file( $attachment->ID );
			
			if ( $image_path ) {
				// Remove WebP file
				$webp_path = preg_replace( '/\.(jpe?g|png|gif)$/i', '.webp', $image_path );
				if ( file_exists( $webp_path ) ) {
					unlink( $webp_path );
				}

				// Remove AVIF file
				$avif_path = preg_replace( '/\.(jpe?g|png|gif)$/i', '.avif', $image_path );
				if ( file_exists( $avif_path ) ) {
					unlink( $avif_path );
				}

				// Remove metadata
				delete_post_meta( $attachment->ID, '_image_optimizer_optimized' );
				delete_post_meta( $attachment->ID, '_image_optimizer_data' );
				
				$cleaned_count++;
			}
		}

		wp_send_json_success( sprintf( esc_html__( 'Cleaned up %d optimized images.', 'image-optimizer' ), $cleaned_count ) );
	}

	/**
	 * AJAX handler for toggling remove originals
	 */
	public static function ajax_toggle_remove_originals() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'image_optimizer_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Security check failed.', 'image-optimizer' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'image-optimizer' ) );
		}

		$enabled = isset( $_POST['enabled'] ) ? (bool) $_POST['enabled'] : false;
		update_option( 'image_optimizer_remove_originals', $enabled );

		wp_send_json_success( esc_html__( 'Remove originals setting toggled successfully.', 'image-optimizer' ) );
	}

	/**
	 * AJAX handler for setting conversion format
	 */
	public static function ajax_set_conversion_format() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'image_optimizer_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Security check failed.', 'image-optimizer' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'image-optimizer' ) );
		}

		$format = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'both';
		
		if ( ! in_array( $format, array( 'webp', 'avif', 'both' ), true ) ) {
			wp_send_json_error( esc_html__( 'Invalid conversion format.', 'image-optimizer' ) );
		}

		update_option( 'image_optimizer_conversion_format', $format );
		wp_send_json_success( esc_html__( 'Conversion format set successfully.', 'image-optimizer' ) );
	}

	/**
	 * Delete converted images when attachment is deleted
	 *
	 * @param int $post_id Post ID.
	 */
	public static function delete_converted_images( $post_id ) {
		$image_path = get_attached_file( $post_id );
		
		if ( $image_path ) {
			// Remove WebP file
			$webp_path = preg_replace( '/\.(jpe?g|png|gif)$/i', '.webp', $image_path );
			if ( file_exists( $webp_path ) ) {
				unlink( $webp_path );
			}

			// Remove AVIF file
			$avif_path = preg_replace( '/\.(jpe?g|png|gif)$/i', '.avif', $image_path );
			if ( file_exists( $avif_path ) ) {
				unlink( $avif_path );
			}
		}
	}
}
