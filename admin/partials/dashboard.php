<?php
/**
 * Admin Dashboard Template
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current settings and stats
$settings = isset( $settings ) ? $settings : array();
$stats = isset( $settings['stats'] ) ? $settings['stats'] : array();
$server_capabilities = isset( $settings['server_capabilities'] ) ? $settings['server_capabilities'] : array();

// Calculate additional statistics
$total_images = wp_count_posts( 'attachment' )->inherit ?? 0;
$conversion_percentage = $total_images > 0 ? round( ( $stats['total_conversions'] ?? 0 ) / $total_images * 100, 1 ) : 0;
$average_savings = ( $stats['total_conversions'] ?? 0 ) > 0 ? round( ( $stats['space_saved'] ?? 0 ) / ( $stats['total_conversions'] ?? 1 ), 2 ) : 0;
?>

<div class="wrap wp-image-optimizer-admin">
	<div class="wp-image-optimizer-header">
		<h1><?php esc_html_e( 'Image Optimizer Dashboard', 'wp-image-optimizer' ); ?></h1>
		<p><?php esc_html_e( 'Monitor your image optimization statistics and server status.', 'wp-image-optimizer' ); ?></p>
	</div>

	<!-- Statistics Overview -->
	<div class="wp-image-optimizer-stats">
		<div class="wp-image-optimizer-stat" data-stat="conversions">
			<span class="wp-image-optimizer-stat-value">
				<?php echo esc_html( isset( $stats['total_conversions'] ) ? number_format( $stats['total_conversions'] ) : '0' ); ?>
			</span>
			<span class="wp-image-optimizer-stat-label">
				<?php esc_html_e( 'Images Converted', 'wp-image-optimizer' ); ?>
			</span>
		</div>

		<div class="wp-image-optimizer-stat" data-stat="space-saved">
			<span class="wp-image-optimizer-stat-value">
				<?php 
				$space_saved = isset( $stats['space_saved'] ) ? $stats['space_saved'] : 0;
				echo esc_html( size_format( $space_saved ) );
				?>
			</span>
			<span class="wp-image-optimizer-stat-label">
				<?php esc_html_e( 'Space Saved', 'wp-image-optimizer' ); ?>
			</span>
		</div>

		<div class="wp-image-optimizer-stat" data-stat="conversion-rate">
			<span class="wp-image-optimizer-stat-value">
				<?php echo esc_html( $conversion_percentage . '%' ); ?>
			</span>
			<span class="wp-image-optimizer-stat-label">
				<?php esc_html_e( 'Conversion Rate', 'wp-image-optimizer' ); ?>
			</span>
		</div>

		<div class="wp-image-optimizer-stat" data-stat="avg-savings">
			<span class="wp-image-optimizer-stat-value">
				<?php echo esc_html( size_format( $average_savings ) ); ?>
			</span>
			<span class="wp-image-optimizer-stat-label">
				<?php esc_html_e( 'Avg. Savings/Image', 'wp-image-optimizer' ); ?>
			</span>
		</div>

		<div class="wp-image-optimizer-stat" data-stat="webp-count">
			<span class="wp-image-optimizer-stat-value">
				<?php echo esc_html( isset( $stats['webp_conversions'] ) ? number_format( $stats['webp_conversions'] ) : '0' ); ?>
			</span>
			<span class="wp-image-optimizer-stat-label">
				<?php esc_html_e( 'WebP Images', 'wp-image-optimizer' ); ?>
			</span>
		</div>

		<div class="wp-image-optimizer-stat" data-stat="avif-count">
			<span class="wp-image-optimizer-stat-value">
				<?php echo esc_html( isset( $stats['avif_conversions'] ) ? number_format( $stats['avif_conversions'] ) : '0' ); ?>
			</span>
			<span class="wp-image-optimizer-stat-label">
				<?php esc_html_e( 'AVIF Images', 'wp-image-optimizer' ); ?>
			</span>
		</div>
	</div>

	<!-- Server Capabilities -->
	<div class="wp-image-optimizer-card">
		<h2><?php esc_html_e( 'Server Capabilities', 'wp-image-optimizer' ); ?></h2>
		<div class="wp-image-optimizer-capabilities">
			<div class="wp-image-optimizer-capability <?php echo isset( $server_capabilities['imagemagick'] ) && $server_capabilities['imagemagick'] ? 'available' : 'unavailable'; ?>">
				<span class="wp-image-optimizer-capability-icon dashicons <?php echo isset( $server_capabilities['imagemagick'] ) && $server_capabilities['imagemagick'] ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
				<span class="wp-image-optimizer-capability-text">
					<?php esc_html_e( 'ImageMagick', 'wp-image-optimizer' ); ?>
				</span>
			</div>

			<div class="wp-image-optimizer-capability <?php echo isset( $server_capabilities['gd'] ) && $server_capabilities['gd'] ? 'available' : 'unavailable'; ?>">
				<span class="wp-image-optimizer-capability-icon dashicons <?php echo isset( $server_capabilities['gd'] ) && $server_capabilities['gd'] ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
				<span class="wp-image-optimizer-capability-text">
					<?php esc_html_e( 'GD Library', 'wp-image-optimizer' ); ?>
				</span>
			</div>

			<div class="wp-image-optimizer-capability <?php echo isset( $server_capabilities['webp_support'] ) && $server_capabilities['webp_support'] ? 'available' : 'unavailable'; ?>">
				<span class="wp-image-optimizer-capability-icon dashicons <?php echo isset( $server_capabilities['webp_support'] ) && $server_capabilities['webp_support'] ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
				<span class="wp-image-optimizer-capability-text">
					<?php esc_html_e( 'WebP Support', 'wp-image-optimizer' ); ?>
				</span>
			</div>

			<div class="wp-image-optimizer-capability <?php echo isset( $server_capabilities['avif_support'] ) && $server_capabilities['avif_support'] ? 'available' : 'unavailable'; ?>">
				<span class="wp-image-optimizer-capability-icon dashicons <?php echo isset( $server_capabilities['avif_support'] ) && $server_capabilities['avif_support'] ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
				<span class="wp-image-optimizer-capability-text">
					<?php esc_html_e( 'AVIF Support', 'wp-image-optimizer' ); ?>
				</span>
			</div>
		</div>
	</div>

	<!-- Conversion Status Overview -->
	<div class="wp-image-optimizer-card">
		<h2><?php esc_html_e( 'Conversion Status Overview', 'wp-image-optimizer' ); ?></h2>
		<div class="wp-image-optimizer-status-grid">
			<div class="wp-image-optimizer-status-item">
				<div class="wp-image-optimizer-status-header">
					<span class="wp-image-optimizer-status-icon dashicons dashicons-yes-alt"></span>
					<span class="wp-image-optimizer-status-title"><?php esc_html_e( 'Successful Conversions', 'wp-image-optimizer' ); ?></span>
				</div>
				<div class="wp-image-optimizer-status-count">
					<?php echo esc_html( number_format( $stats['successful_conversions'] ?? 0 ) ); ?>
				</div>
			</div>

			<div class="wp-image-optimizer-status-item">
				<div class="wp-image-optimizer-status-header">
					<span class="wp-image-optimizer-status-icon dashicons dashicons-warning"></span>
					<span class="wp-image-optimizer-status-title"><?php esc_html_e( 'Failed Conversions', 'wp-image-optimizer' ); ?></span>
				</div>
				<div class="wp-image-optimizer-status-count">
					<?php echo esc_html( number_format( $stats['failed_conversions'] ?? 0 ) ); ?>
				</div>
			</div>

			<div class="wp-image-optimizer-status-item">
				<div class="wp-image-optimizer-status-header">
					<span class="wp-image-optimizer-status-icon dashicons dashicons-clock"></span>
					<span class="wp-image-optimizer-status-title"><?php esc_html_e( 'Pending Conversions', 'wp-image-optimizer' ); ?></span>
				</div>
				<div class="wp-image-optimizer-status-count">
					<?php echo esc_html( number_format( $stats['pending_conversions'] ?? 0 ) ); ?>
				</div>
			</div>

			<div class="wp-image-optimizer-status-item">
				<div class="wp-image-optimizer-status-header">
					<span class="wp-image-optimizer-status-icon dashicons dashicons-dismiss"></span>
					<span class="wp-image-optimizer-status-title"><?php esc_html_e( 'Skipped Images', 'wp-image-optimizer' ); ?></span>
				</div>
				<div class="wp-image-optimizer-status-count">
					<?php echo esc_html( number_format( $stats['skipped_conversions'] ?? 0 ) ); ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Error Reporting -->
	<?php if ( isset( $stats['recent_errors'] ) && ! empty( $stats['recent_errors'] ) ) : ?>
	<div class="wp-image-optimizer-card">
		<h2><?php esc_html_e( 'Recent Errors', 'wp-image-optimizer' ); ?></h2>
		<div class="wp-image-optimizer-error-list">
			<?php foreach ( array_slice( $stats['recent_errors'], 0, 5 ) as $error ) : ?>
				<div class="wp-image-optimizer-error-item">
					<div class="wp-image-optimizer-error-header">
						<span class="wp-image-optimizer-error-icon dashicons dashicons-warning"></span>
						<span class="wp-image-optimizer-error-time">
							<?php echo esc_html( human_time_diff( $error['timestamp'] ) . ' ago' ); ?>
						</span>
					</div>
					<div class="wp-image-optimizer-error-message">
						<?php echo esc_html( $error['message'] ); ?>
					</div>
					<?php if ( ! empty( $error['file'] ) ) : ?>
						<div class="wp-image-optimizer-error-file">
							<?php printf( __( 'File: %s', 'wp-image-optimizer' ), esc_html( basename( $error['file'] ) ) ); ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php if ( count( $stats['recent_errors'] ) > 5 ) : ?>
			<p class="wp-image-optimizer-error-more">
				<?php printf( __( 'And %d more errors...', 'wp-image-optimizer' ), count( $stats['recent_errors'] ) - 5 ); ?>
			</p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<!-- Bulk Regeneration Interface -->
	<div class="wp-image-optimizer-card">
		<h2><?php esc_html_e( 'Bulk Operations', 'wp-image-optimizer' ); ?></h2>
		<div class="wp-image-optimizer-bulk-operations">
			<div class="wp-image-optimizer-bulk-info">
				<p><?php esc_html_e( 'Regenerate optimized versions of all images with current settings.', 'wp-image-optimizer' ); ?></p>
				<p class="wp-image-optimizer-bulk-warning">
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'This process may take a while depending on the number of images.', 'wp-image-optimizer' ); ?>
				</p>
			</div>

			<div class="wp-image-optimizer-bulk-progress" style="display: none;">
				<div class="wp-image-optimizer-progress">
					<div class="wp-image-optimizer-progress-bar" style="width: 0%;"></div>
				</div>
				<div class="wp-image-optimizer-progress-text">
					<span class="wp-image-optimizer-progress-current">0</span> / 
					<span class="wp-image-optimizer-progress-total">0</span> images processed
				</div>
				<div class="wp-image-optimizer-progress-status"></div>
			</div>

			<div class="wp-image-optimizer-bulk-controls">
				<button type="button" class="button button-primary wp-image-optimizer-start-bulk" data-original-text="<?php esc_attr_e( 'Start Bulk Regeneration', 'wp-image-optimizer' ); ?>">
					<?php esc_html_e( 'Start Bulk Regeneration', 'wp-image-optimizer' ); ?>
				</button>
				<button type="button" class="button wp-image-optimizer-stop-bulk" style="display: none;">
					<?php esc_html_e( 'Stop Process', 'wp-image-optimizer' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Server Configuration Snippets -->
	<div class="wp-image-optimizer-card">
		<h2><?php esc_html_e( 'Server Configuration', 'wp-image-optimizer' ); ?></h2>
		<p><?php esc_html_e( 'Configure your web server to serve optimized images automatically for better performance.', 'wp-image-optimizer' ); ?></p>
		
		<div class="wp-image-optimizer-config-tabs">
			<div class="wp-image-optimizer-config-tab-nav">
				<button type="button" class="wp-image-optimizer-config-tab active" data-tab="nginx">
					<?php esc_html_e( 'Nginx', 'wp-image-optimizer' ); ?>
				</button>
				<button type="button" class="wp-image-optimizer-config-tab" data-tab="apache">
					<?php esc_html_e( 'Apache', 'wp-image-optimizer' ); ?>
				</button>
			</div>

			<div class="wp-image-optimizer-config-content">
				<div class="wp-image-optimizer-config-panel active" id="nginx-config">
					<h4><?php esc_html_e( 'Nginx Configuration', 'wp-image-optimizer' ); ?></h4>
					<p><?php esc_html_e( 'Add this configuration to your Nginx server block:', 'wp-image-optimizer' ); ?></p>
					<div class="wp-image-optimizer-config-code">
						<pre><code># WebP/AVIF serving with fallback
location ~* \.(jpe?g|png|gif)$ {
    set $webp_suffix "";
    set $avif_suffix "";
    
    if ($http_accept ~* "image/avif") {
        set $avif_suffix ".avif";
    }
    if ($http_accept ~* "image/webp") {
        set $webp_suffix ".webp";
    }
    
    # Try AVIF first, then WebP, then original
    try_files $uri$avif_suffix $uri$webp_suffix $uri @wp_image_optimizer;
    
    expires 1y;
    add_header Cache-Control "public, immutable";
    add_header Vary "Accept";
}

location @wp_image_optimizer {
    rewrite ^(.+)$ <?php echo esc_html( str_replace( ABSPATH, '/', WP_IMAGE_OPTIMIZER_PLUGIN_DIR ) ); ?>public/endpoint.php?file=$1 last;
}</code></pre>
					</div>
					<button type="button" class="button wp-image-optimizer-copy-config" data-config="nginx">
						<?php esc_html_e( 'Copy Configuration', 'wp-image-optimizer' ); ?>
					</button>
				</div>

				<div class="wp-image-optimizer-config-panel" id="apache-config">
					<h4><?php esc_html_e( 'Apache Configuration', 'wp-image-optimizer' ); ?></h4>
					<p><?php esc_html_e( 'Add this to your .htaccess file or Apache configuration:', 'wp-image-optimizer' ); ?></p>
					<div class="wp-image-optimizer-config-code">
						<pre><code>&lt;IfModule mod_rewrite.c&gt;
    RewriteEngine On
    
    # Check for AVIF support
    RewriteCond %{HTTP_ACCEPT} image/avif
    RewriteCond %{REQUEST_FILENAME} \.(jpe?g|png|gif)$
    RewriteCond %{REQUEST_FILENAME}\.avif -f
    RewriteRule ^(.+)$ $1.avif [T=image/avif,E=accept:1,L]
    
    # Check for WebP support
    RewriteCond %{HTTP_ACCEPT} image/webp
    RewriteCond %{REQUEST_FILENAME} \.(jpe?g|png|gif)$
    RewriteCond %{REQUEST_FILENAME}\.webp -f
    RewriteRule ^(.+)$ $1.webp [T=image/webp,E=accept:1,L]
    
    # Fallback to PHP handler for on-demand conversion
    RewriteCond %{REQUEST_FILENAME} \.(jpe?g|png|gif)$
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.+)$ <?php echo esc_html( str_replace( ABSPATH, '/', WP_IMAGE_OPTIMIZER_PLUGIN_DIR ) ); ?>public/endpoint.php?file=$1 [QSA,L]
&lt;/IfModule&gt;

&lt;IfModule mod_headers.c&gt;
    Header append Vary Accept env=accept
&lt;/IfModule&gt;

&lt;IfModule mod_expires.c&gt;
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/avif "access plus 1 year"
&lt;/IfModule&gt;</code></pre>
					</div>
					<button type="button" class="button wp-image-optimizer-copy-config" data-config="apache">
						<?php esc_html_e( 'Copy Configuration', 'wp-image-optimizer' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Recent Activity -->
	<div class="wp-image-optimizer-card">
		<h2><?php esc_html_e( 'Recent Activity', 'wp-image-optimizer' ); ?></h2>
		<?php if ( isset( $stats['recent_conversions'] ) && ! empty( $stats['recent_conversions'] ) ) : ?>
			<div class="wp-image-optimizer-recent-activity">
				<?php foreach ( $stats['recent_conversions'] as $conversion ) : ?>
					<div class="wp-image-optimizer-activity-item">
						<span class="wp-image-optimizer-activity-file">
							<?php echo esc_html( basename( $conversion['original_file'] ) ); ?>
						</span>
						<span class="wp-image-optimizer-activity-formats">
							<?php echo esc_html( implode( ', ', $conversion['formats'] ) ); ?>
						</span>
						<span class="wp-image-optimizer-activity-date">
							<?php echo esc_html( human_time_diff( $conversion['timestamp'] ) . ' ago' ); ?>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'No recent conversions found. Upload some images to see activity here.', 'wp-image-optimizer' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Quick Actions -->
	<div class="wp-image-optimizer-card">
		<h2><?php esc_html_e( 'Quick Actions', 'wp-image-optimizer' ); ?></h2>
		<div class="wp-image-optimizer-button-group">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-image-optimizer-settings' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Settings', 'wp-image-optimizer' ); ?>
			</a>
			<button type="button" class="button wp-image-optimizer-regenerate" data-original-text="<?php esc_attr_e( 'Regenerate All Images', 'wp-image-optimizer' ); ?>">
				<?php esc_html_e( 'Regenerate All Images', 'wp-image-optimizer' ); ?>
			</button>
			<button type="button" class="button wp-image-optimizer-clear-cache">
				<?php esc_html_e( 'Clear Cache', 'wp-image-optimizer' ); ?>
			</button>
			<button type="button" class="button wp-image-optimizer-cleanup-files" data-original-text="<?php esc_attr_e( 'Cleanup Files', 'wp-image-optimizer' ); ?>">
				<?php esc_html_e( 'Cleanup Files', 'wp-image-optimizer' ); ?>
			</button>
		</div>
	</div>
</div>