<?php
/**
 * Image Optimizer CLI Commands
 *
 * @package ImageOptimizer
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only load if WP-CLI is available
if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Image Optimizer CLI Commands
	 */
	class Image_Optimizer_CLI {

		/**
		 * Register CLI commands
		 */
		public static function register_commands() {
			WP_CLI::add_command( 'image-optimizer', __CLASS__ );
		}

		/**
		 * Convert images command
		 *
		 * ## OPTIONS
		 *
		 * [--year=<year>]
		 * : Filter images by year
		 *
		 * [--month=<month>]
		 * : Filter images by month
		 *
		 * [--format=<format>]
		 * : Conversion format (webp, avif, both)
		 * ---
		 * default: both
		 * options:
		 *   - webp
		 *   - avif
		 *   - both
		 * ---
		 *
		 * [--quality=<quality>]
		 * : Quality setting (0-100)
		 * ---
		 * default: 80
		 * ---
		 *
		 * [--sizes=<sizes>]
		 * : Comma-separated list of image sizes to convert
		 *
		 * [--dry-run]
		 * : Show what would be converted without actually converting
		 *
		 * [--limit=<limit>]
		 * : Limit the number of images to process
		 *
		 * ## EXAMPLES
		 *
		 *     # Convert all unconverted images
		 *     $ wp image-optimizer convert
		 *
		 *     # Convert images from 2023
		 *     $ wp image-optimizer convert --year=2023
		 *
		 *     # Convert images from January 2023 with WebP only
		 *     $ wp image-optimizer convert --year=2023 --month=1 --format=webp
		 *
		 *     # Convert with custom quality
		 *     $ wp image-optimizer convert --quality=90
		 *
		 *     # Dry run to see what would be converted
		 *     $ wp image-optimizer convert --dry-run
		 *
		 * @param array $args Command arguments.
		 * @param array $assoc_args Command options.
		 */
		public function convert( $args, $assoc_args ) {
			// Parse arguments
			$year = isset( $assoc_args['year'] ) ? absint( $assoc_args['year'] ) : null;
			$month = isset( $assoc_args['month'] ) ? absint( $assoc_args['month'] ) : null;
			$format = isset( $assoc_args['format'] ) ? sanitize_text_field( $assoc_args['format'] ) : 'both';
			$quality = isset( $assoc_args['quality'] ) ? absint( $assoc_args['quality'] ) : 80;
			$sizes = isset( $assoc_args['sizes'] ) ? explode( ',', $assoc_args['sizes'] ) : array();
			$dry_run = isset( $assoc_args['dry-run'] );
			$limit = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : null;

			// Validate format
			if ( ! in_array( $format, array( 'webp', 'avif', 'both' ), true ) ) {
				WP_CLI::error( 'Invalid format. Use webp, avif, or both.' );
			}

			// Validate quality
			if ( $quality < 0 || $quality > 100 ) {
				WP_CLI::error( 'Quality must be between 0 and 100.' );
			}

			// Build query arguments
			$query_args = array(
				'post_type'      => 'attachment',
				'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif' ),
				'posts_per_page' => $limit ?: -1,
				'post_status'    => 'inherit',
				'meta_query'     => array(
					array(
						'key'     => '_image_optimizer_optimized',
						'compare' => 'NOT EXISTS',
					),
				),
			);

			// Add date query if specified
			if ( $year || $month ) {
				$date_query = array();
				if ( $year ) {
					$date_query['year'] = $year;
				}
				if ( $month ) {
					$date_query['monthnum'] = $month;
				}
				$query_args['date_query'] = array( $date_query );
			}

			// Get images
			$query = new WP_Query( $query_args );
			$total_images = $query->found_posts;

			if ( $total_images === 0 ) {
				WP_CLI::warning( 'No unconverted images found.' );
				return;
			}

			WP_CLI::log( sprintf( 'Found %d unconverted images.', $total_images ) );

			if ( $dry_run ) {
				WP_CLI::log( 'DRY RUN - No images will be converted.' );
			}

			// Process images
			$converted_count = 0;
			$error_count = 0;
			$total_savings = 0;

			$progress = \WP_CLI\Utils\make_progress_bar( 'Converting images', $total_images );

			while ( $query->have_posts() ) {
				$query->the_post();
				$attachment_id = get_the_ID();
				$image_path = get_attached_file( $attachment_id );

				if ( ! $image_path || ! file_exists( $image_path ) ) {
					WP_CLI::warning( sprintf( 'Image file not found for attachment ID %d', $attachment_id ) );
					$error_count++;
					$progress->tick();
					continue;
				}

				try {
					if ( ! $dry_run ) {
						// Temporarily set conversion format
						$original_format = get_option( 'image_optimizer_conversion_format', 'both' );
						update_option( 'image_optimizer_conversion_format', $format );

						$data = Image_Converter::convert_image( $image_path, $quality, $sizes );

						// Restore original format
						update_option( 'image_optimizer_conversion_format', $original_format );

						if ( $data ) {
							update_post_meta( $attachment_id, '_image_optimizer_optimized', true );
							update_post_meta( $attachment_id, '_image_optimizer_data', $data );

							$converted_count++;

							// Calculate savings
							$original_size = $data['original_size'];
							$webp_size = $data['webp_size'] ?: 0;
							$avif_size = $data['avif_size'] ?: 0;

							if ( $webp_size > 0 ) {
								$total_savings += ( $original_size - $webp_size );
							}
							if ( $avif_size > 0 ) {
								$total_savings += ( $original_size - $avif_size );
							}

							// Log conversion details
							$savings_text = '';
							if ( $webp_size > 0 ) {
								$webp_savings = $original_size - $webp_size;
								$webp_percent = round( ( $webp_savings / $original_size ) * 100, 1 );
								$savings_text .= sprintf( 'WebP: %s (%d%% smaller)', size_format( $webp_size ), $webp_percent );
							}
							if ( $avif_size > 0 ) {
								$avif_savings = $original_size - $avif_size;
								$avif_percent = round( ( $avif_savings / $original_size ) * 100, 1 );
								$savings_text .= sprintf( ' AVIF: %s (%d%% smaller)', size_format( $avif_size ), $avif_percent );
							}

							WP_CLI::log( sprintf( 'Converted ID %d: %s', $attachment_id, $savings_text ) );
						}
					} else {
						$converted_count++;
						WP_CLI::log( sprintf( 'Would convert ID %d: %s', $attachment_id, basename( $image_path ) ) );
					}
				} catch ( Exception $e ) {
					WP_CLI::warning( sprintf( 'Error converting image ID %d: %s', $attachment_id, $e->getMessage() ) );
					$error_count++;
				}

				$progress->tick();
			}

			$progress->finish();

			// Summary
			if ( $dry_run ) {
				WP_CLI::success( sprintf( 'Dry run completed. Would convert %d images.', $converted_count ) );
			} else {
				WP_CLI::success( sprintf( 'Conversion completed. Converted: %d, Errors: %d', $converted_count, $error_count ) );
				
				if ( $total_savings > 0 ) {
					WP_CLI::log( sprintf( 'Total space saved: %s', size_format( $total_savings ) ) );
				}
			}
		}

		/**
		 * Status command
		 *
		 * ## OPTIONS
		 *
		 * [--format=<format>]
		 * : Output format (table, csv, json, count)
		 * ---
		 * default: table
		 * options:
		 *   - table
		 *   - csv
		 *   - json
		 *   - count
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     # Show conversion status
		 *     $ wp image-optimizer status
		 *
		 *     # Get count of converted images
		 *     $ wp image-optimizer status --format=count
		 *
		 * @param array $args Command arguments.
		 * @param array $assoc_args Command options.
		 */
		public function status( $args, $assoc_args ) {
			$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

			// Get statistics
			$total_attachments = $this->get_total_attachments();
			$converted_attachments = $this->get_converted_attachments_count();
			$unconverted_attachments = $total_attachments - $converted_attachments;
			$percentage = $total_attachments > 0 ? round( ( $converted_attachments / $total_attachments ) * 100, 1 ) : 0;

			$data = array(
				array(
					'Metric'     => 'Total Images',
					'Count'      => $total_attachments,
					'Percentage' => '100%',
				),
				array(
					'Metric'     => 'Converted',
					'Count'      => $converted_attachments,
					'Percentage' => $percentage . '%',
				),
				array(
					'Metric'     => 'Unconverted',
					'Count'      => $unconverted_attachments,
					'Percentage' => ( 100 - $percentage ) . '%',
				),
			);

			if ( 'count' === $format ) {
				WP_CLI::line( $converted_attachments );
			} elseif ( 'json' === $format ) {
				WP_CLI::line( json_encode( $data ) );
			} else {
				\WP_CLI\Utils\format_items( $format, $data, array( 'Metric', 'Count', 'Percentage' ) );
			}
		}

		/**
		 * Cleanup command
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Show what would be cleaned up without actually doing it
		 *
		 * ## EXAMPLES
		 *
		 *     # Clean up optimized images
		 *     $ wp image-optimizer cleanup
		 *
		 *     # Dry run cleanup
		 *     $ wp image-optimizer cleanup --dry-run
		 *
		 * @param array $args Command arguments.
		 * @param array $assoc_args Command options.
		 */
		public function cleanup( $args, $assoc_args ) {
			$dry_run = isset( $assoc_args['dry-run'] );

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
			$total_size_removed = 0;

			foreach ( $attachments as $attachment ) {
				$image_path = get_attached_file( $attachment->ID );

				if ( $image_path ) {
					// Calculate size to be removed
					$webp_path = preg_replace( '/\.(jpe?g|png|gif)$/i', '.webp', $image_path );
					$avif_path = preg_replace( '/\.(jpe?g|png|gif)$/i', '.avif', $image_path );

					if ( file_exists( $webp_path ) ) {
						$total_size_removed += filesize( $webp_path );
					}
					if ( file_exists( $avif_path ) ) {
						$total_size_removed += filesize( $avif_path );
					}

					if ( ! $dry_run ) {
						// Remove files
						if ( file_exists( $webp_path ) ) {
							unlink( $webp_path );
						}
						if ( file_exists( $avif_path ) ) {
							unlink( $avif_path );
						}

						// Remove metadata
						delete_post_meta( $attachment->ID, '_image_optimizer_optimized' );
						delete_post_meta( $attachment->ID, '_image_optimizer_data' );
					}

					$cleaned_count++;
				}
			}

			if ( $dry_run ) {
				WP_CLI::success( sprintf( 'Dry run: Would clean up %d images (%s)', $cleaned_count, size_format( $total_size_removed ) ) );
			} else {
				WP_CLI::success( sprintf( 'Cleaned up %d images (%s)', $cleaned_count, size_format( $total_size_removed ) ) );
			}
		}

		/**
		 * Get total attachments count
		 *
		 * @return int Total attachments count.
		 */
		private function get_total_attachments() {
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
		private function get_converted_attachments_count() {
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
	}

	// Register commands
	Image_Optimizer_CLI::register_commands();
}
