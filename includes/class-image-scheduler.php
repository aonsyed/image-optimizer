<?php
/**
 * Image Scheduler class
 *
 * @package ImageOptimizer
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Scheduler class
 */
class Image_Scheduler {

	/**
	 * Initialize the scheduler
	 */
	public static function init() {
		// Register cron event
		add_action( 'image_optimizer_bulk_conversion', array( __CLASS__, 'run_scheduled_conversion' ) );
		
		// Add cron interval
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_interval' ) );
	}

	/**
	 * Add custom cron interval
	 *
	 * @param array $schedules Cron schedules.
	 * @return array
	 */
	public static function add_cron_interval( $schedules ) {
		$schedules['image_optimizer_interval'] = array(
			'interval' => 3600, // 1 hour
			'display'  => esc_html__( 'Every Hour (Image Optimizer)', 'image-optimizer' ),
		);
		return $schedules;
	}

	/**
	 * Run scheduled bulk conversion
	 */
	public static function run_scheduled_conversion() {
		// Check if scheduler is enabled
		if ( ! get_option( 'image_optimizer_enable_scheduler', false ) ) {
			return;
		}

		Logger::info( 'Starting scheduled bulk conversion' );

		// Get unconverted images
		$attachments = self::get_unconverted_attachments();
		
		if ( empty( $attachments ) ) {
			Logger::info( 'No unconverted images found' );
			return;
		}

		$converted_count = 0;
		$error_count = 0;
		$batch_size = get_option( 'image_optimizer_batch_size', 10 );

		foreach ( $attachments as $attachment ) {
			try {
				$image_path = get_attached_file( $attachment->ID );
				
				if ( ! $image_path || ! file_exists( $image_path ) ) {
					Logger::warning( 'Image file not found for attachment ID: ' . $attachment->ID );
					continue;
				}

				$data = Image_Converter::convert_image( $image_path );
				
				if ( $data ) {
					update_post_meta( $attachment->ID, '_image_optimizer_optimized', true );
					update_post_meta( $attachment->ID, '_image_optimizer_data', $data );
					$converted_count++;
					
					Logger::info( 'Converted image ID: ' . $attachment->ID );
				}

				// Limit batch size to prevent timeouts
				if ( $converted_count >= $batch_size ) {
					break;
				}

			} catch ( Exception $e ) {
				Logger::error( 'Error converting image ID ' . $attachment->ID . ': ' . $e->getMessage() );
				$error_count++;
			}
		}

		Logger::info( sprintf( 'Scheduled conversion completed. Converted: %d, Errors: %d', $converted_count, $error_count ) );

		// Schedule next run if there are more images
		if ( count( $attachments ) > $batch_size ) {
			wp_schedule_single_event( time() + 300, 'image_optimizer_bulk_conversion' ); // 5 minutes later
		}
	}

	/**
	 * Get unconverted attachments
	 *
	 * @param int $limit Number of attachments to retrieve.
	 * @return array Array of attachment objects.
	 */
	private static function get_unconverted_attachments( $limit = 50 ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif' ),
			'posts_per_page' => $limit,
			'post_status'    => 'inherit',
			'meta_query'     => array(
				array(
					'key'     => '_image_optimizer_optimized',
					'compare' => 'NOT EXISTS',
				),
			),
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		return get_posts( $args );
	}

	/**
	 * Schedule bulk conversion
	 *
	 * @param array $args Conversion arguments.
	 * @return bool True if scheduled successfully.
	 */
	public static function schedule_bulk_conversion( $args = array() ) {
		$defaults = array(
			'year'    => null,
			'month'   => null,
			'format'  => get_option( 'image_optimizer_conversion_format', 'both' ),
			'quality' => null,
			'sizes'   => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		// Store conversion arguments
		update_option( 'image_optimizer_bulk_conversion_args', $args );

		// Schedule the conversion
		if ( ! wp_next_scheduled( 'image_optimizer_bulk_conversion' ) ) {
			$scheduled = wp_schedule_single_event( time() + 60, 'image_optimizer_bulk_conversion' );
			
			if ( $scheduled ) {
				Logger::info( 'Bulk conversion scheduled successfully' );
				return true;
			} else {
				Logger::error( 'Failed to schedule bulk conversion' );
				return false;
			}
		}

		return true;
	}

	/**
	 * Get conversion progress
	 *
	 * @return array Progress information.
	 */
	public static function get_conversion_progress() {
		$total_attachments = self::get_total_attachments();
		$converted_attachments = self::get_converted_attachments_count();
		$unconverted_attachments = $total_attachments - $converted_attachments;

		$progress = array(
			'total'       => $total_attachments,
			'converted'   => $converted_attachments,
			'unconverted' => $unconverted_attachments,
			'percentage'  => $total_attachments > 0 ? round( ( $converted_attachments / $total_attachments ) * 100, 1 ) : 0,
		);

		return $progress;
	}

	/**
	 * Get total attachments count
	 *
	 * @return int Total attachments count.
	 */
	private static function get_total_attachments() {
		$args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif' ),
			'posts_per_page' => -1,
			'post_status'    => 'inherit',
			'fields'         => 'ids',
		);

		$query = new WP_Query( $args );
		return $query->found_posts;
	}

	/**
	 * Get converted attachments count
	 *
	 * @return int Converted attachments count.
	 */
	private static function get_converted_attachments_count() {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID) 
				FROM {$wpdb->posts} p 
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
				WHERE p.post_type = %s 
				AND p.post_mime_type IN ('image/jpeg', 'image/png', 'image/gif') 
				AND p.post_status = %s 
				AND pm.meta_key = %s",
				'attachment',
				'inherit',
				'_image_optimizer_optimized'
			)
		);

		return (int) $count;
	}

	/**
	 * Clear scheduled events
	 */
	public static function clear_scheduled_events() {
		wp_clear_scheduled_hook( 'image_optimizer_bulk_conversion' );
		Logger::info( 'Cleared scheduled conversion events' );
	}

	/**
	 * Get next scheduled time
	 *
	 * @return int|false Next scheduled time or false.
	 */
	public static function get_next_scheduled_time() {
		return wp_next_scheduled( 'image_optimizer_bulk_conversion' );
	}

	/**
	 * Check if conversion is in progress
	 *
	 * @return bool True if conversion is in progress.
	 */
	public static function is_conversion_in_progress() {
		$next_scheduled = self::get_next_scheduled_time();
		return $next_scheduled && $next_scheduled > time();
	}
}
