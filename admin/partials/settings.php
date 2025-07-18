<?php
/**
 * Admin Settings Template
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current settings
$settings = isset( $settings ) ? $settings : array();
$plugin_settings = isset( $settings['settings'] ) ? $settings['settings'] : array();
$server_capabilities = isset( $settings['server_capabilities'] ) ? $settings['server_capabilities'] : array();
?>

<div class="wrap wp-image-optimizer-admin">
	<div class="wp-image-optimizer-header">
		<h1><?php esc_html_e( 'Image Optimizer Settings', 'wp-image-optimizer' ); ?></h1>
		<p><?php esc_html_e( 'Configure image optimization settings and preferences.', 'wp-image-optimizer' ); ?></p>
	</div>

	<form method="post" action="options.php" id="wp-image-optimizer-settings-form">
		<?php
		settings_fields( 'wp_image_optimizer_settings' );
		do_settings_sections( 'wp_image_optimizer_settings' );
		?>

		<!-- General Settings -->
		<div class="wp-image-optimizer-card">
			<h2><?php esc_html_e( 'General Settings', 'wp-image-optimizer' ); ?></h2>
			
			<table class="wp-image-optimizer-form-table">
				<tr>
					<th scope="row">
						<label for="wp_image_optimizer_enabled">
							<?php esc_html_e( 'Enable Image Optimization', 'wp-image-optimizer' ); ?>
						</label>
					</th>
					<td>
						<input type="checkbox" 
							   id="wp_image_optimizer_enabled" 
							   name="wp_image_optimizer_settings[settings][enabled]" 
							   value="1" 
							   <?php checked( isset( $plugin_settings['enabled'] ) ? $plugin_settings['enabled'] : false ); ?> />
						<p class="description">
							<?php esc_html_e( 'Enable or disable automatic image optimization.', 'wp-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="wp_image_optimizer_conversion_mode">
							<?php esc_html_e( 'Conversion Mode', 'wp-image-optimizer' ); ?>
						</label>
					</th>
					<td>
						<select id="wp_image_optimizer_conversion_mode" 
								name="wp_image_optimizer_settings[settings][conversion_mode]">
							<option value="auto" <?php selected( isset( $plugin_settings['conversion_mode'] ) ? $plugin_settings['conversion_mode'] : 'auto', 'auto' ); ?>>
								<?php esc_html_e( 'Automatic', 'wp-image-optimizer' ); ?>
							</option>
							<option value="manual" <?php selected( isset( $plugin_settings['conversion_mode'] ) ? $plugin_settings['conversion_mode'] : 'auto', 'manual' ); ?>>
								<?php esc_html_e( 'Manual', 'wp-image-optimizer' ); ?>
							</option>
							<option value="cli_only" <?php selected( isset( $plugin_settings['conversion_mode'] ) ? $plugin_settings['conversion_mode'] : 'auto', 'cli_only' ); ?>>
								<?php esc_html_e( 'CLI Only', 'wp-image-optimizer' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Choose when images should be converted. Automatic converts on upload, Manual requires user action, CLI Only disables web interface conversion.', 'wp-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="wp_image_optimizer_preserve_originals">
							<?php esc_html_e( 'Preserve Original Images', 'wp-image-optimizer' ); ?>
						</label>
					</th>
					<td>
						<input type="checkbox" 
							   id="wp_image_optimizer_preserve_originals" 
							   name="wp_image_optimizer_settings[settings][preserve_originals]" 
							   value="1" 
							   <?php checked( isset( $plugin_settings['preserve_originals'] ) ? $plugin_settings['preserve_originals'] : true ); ?> />
						<p class="description">
							<?php esc_html_e( 'Keep original images alongside converted versions.', 'wp-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="wp_image_optimizer_max_file_size">
							<?php esc_html_e( 'Maximum File Size', 'wp-image-optimizer' ); ?>
						</label>
					</th>
					<td>
						<input type="number" 
							   id="wp_image_optimizer_max_file_size" 
							   name="wp_image_optimizer_settings[settings][max_file_size]" 
							   value="<?php echo esc_attr( isset( $plugin_settings['max_file_size'] ) ? $plugin_settings['max_file_size'] / 1048576 : 10 ); ?>"
							   min="1" 
							   max="100" 
							   step="0.1" />
						<span><?php esc_html_e( 'MB', 'wp-image-optimizer' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'Maximum file size for image optimization (in megabytes).', 'wp-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="wp_image_optimizer_server_config_type">
							<?php esc_html_e( 'Server Configuration', 'wp-image-optimizer' ); ?>
						</label>
					</th>
					<td>
						<select id="wp_image_optimizer_server_config_type" 
								name="wp_image_optimizer_settings[settings][server_config_type]">
							<option value="nginx" <?php selected( isset( $plugin_settings['server_config_type'] ) ? $plugin_settings['server_config_type'] : 'nginx', 'nginx' ); ?>>
								<?php esc_html_e( 'Nginx', 'wp-image-optimizer' ); ?>
							</option>
							<option value="apache" <?php selected( isset( $plugin_settings['server_config_type'] ) ? $plugin_settings['server_config_type'] : 'nginx', 'apache' ); ?>>
								<?php esc_html_e( 'Apache', 'wp-image-optimizer' ); ?>
							</option>
							<option value="none" <?php selected( isset( $plugin_settings['server_config_type'] ) ? $plugin_settings['server_config_type'] : 'nginx', 'none' ); ?>>
								<?php esc_html_e( 'None (Manual Configuration)', 'wp-image-optimizer' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Select your web server type for automatic configuration generation.', 'wp-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Format Settings -->
		<div class="wp-image-optimizer-card">
			<h2><?php esc_html_e( 'Format Settings', 'wp-image-optimizer' ); ?></h2>
			
			<table class="wp-image-optimizer-form-table">
				<!-- WebP Settings -->
				<tr>
					<th scope="row">
						<label for="wp_image_optimizer_webp_enabled">
							<?php esc_html_e( 'Enable WebP', 'wp-image-optimizer' ); ?>
						</label>
					</th>
					<td>
						<input type="checkbox" 
							   id="wp_image_optimizer_webp_enabled" 
							   name="wp_image_optimizer_settings[settings][formats][webp][enabled]" 
							   value="1" 
							   class="wp-image-optimizer-format-enabled"
							   <?php checked( isset( $plugin_settings['formats']['webp']['enabled'] ) ? $plugin_settings['formats']['webp']['enabled'] : true ); ?> />
						<p class="description">
							<?php esc_html_e( 'Convert images to WebP format.', 'wp-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
				
				<tr class="wp-image-optimizer-quality-row" <?php echo ! isset( $plugin_settings['formats']['webp']['enabled'] ) || ! $plugin_settings['formats']['webp']['enabled'] ? 'style="display:none;"' : ''; ?>>
					<th scope="row">
						<label for="wp_image_optimizer_webp_quality">
							<?php esc_html_e( 'WebP Quality', 'wp-image-optimizer' ); ?>
						</label>
					</th>
					<td>
						<input type="range" 
							   id="wp_image_optimizer_webp_quality" 
							   name="wp_image_optimizer_settings[settings][formats][webp][quality]" 
							   min="1" 
							   max="100" 
							   value="<?php echo esc_attr( isset( $plugin_settings['formats']['webp']['quality'] ) ? $plugin_settings['formats']['webp']['quality'] : 80 ); ?>"
							   class="wp-image-optimizer-quality-slider" />
						<span class="wp-image-optimizer-quality-value">80</span>
						<p class="description">
							<?php esc_html_e( 'Quality setting for WebP conversion (1-100).', 'wp-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<!-- AVIF Settings -->
				<tr>
					<th scope="row">
						<label for="wp_image_optimizer_avif_enabled">
							<?php esc_html_e( 'Enable AVIF', 'wp-image-optimizer' ); ?>
						</label>
					</th>
					<td>
						<input type="checkbox" 
							   id="wp_image_optimizer_avif_enabled" 
							   name="wp_image_optimizer_settings[settings][formats][avif][enabled]" 
							   value="1" 
							   class="wp-image-optimizer-format-enabled"
							   <?php checked( isset( $plugin_settings['formats']['avif']['enabled'] ) ? $plugin_settings['formats']['avif']['enabled'] : true ); ?> />
						<p class="description">
							<?php esc_html_e( 'Convert images to AVIF format.', 'wp-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
				
				<tr class="wp-image-optimizer-quality-row" <?php echo ! isset( $plugin_settings['formats']['avif']['enabled'] ) || ! $plugin_settings['formats']['avif']['enabled'] ? 'style="display:none;"' : ''; ?>>
					<th scope="row">
						<label for="wp_image_optimizer_avif_quality">
							<?php esc_html_e( 'AVIF Quality', 'wp-image-optimizer' ); ?>
						</label>
					</th>
					<td>
						<input type="range" 
							   id="wp_image_optimizer_avif_quality" 
							   name="wp_image_optimizer_settings[settings][formats][avif][quality]" 
							   min="1" 
							   max="100" 
							   value="<?php echo esc_attr( isset( $plugin_settings['formats']['avif']['quality'] ) ? $plugin_settings['formats']['avif']['quality'] : 75 ); ?>"
							   class="wp-image-optimizer-quality-slider" />
						<span class="wp-image-optimizer-quality-value">75</span>
						<p class="description">
							<?php esc_html_e( 'Quality setting for AVIF conversion (1-100).', 'wp-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Server Configuration -->
		<div class="wp-image-optimizer-card">
			<h2><?php esc_html_e( 'Server Configuration', 'wp-image-optimizer' ); ?></h2>
			<p><?php esc_html_e( 'Current server capabilities and configuration options.', 'wp-image-optimizer' ); ?></p>
			
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

			<div class="wp-image-optimizer-test-section" style="margin-top: 20px;">
				<h3><?php esc_html_e( 'Test Conversion', 'wp-image-optimizer' ); ?></h3>
				<p><?php esc_html_e( 'Test your server\'s image conversion capabilities with a sample image.', 'wp-image-optimizer' ); ?></p>
				<button type="button" class="button wp-image-optimizer-test-conversion" data-original-text="<?php esc_attr_e( 'Test Conversion', 'wp-image-optimizer' ); ?>">
					<?php esc_html_e( 'Test Conversion', 'wp-image-optimizer' ); ?>
				</button>
				<div class="wp-image-optimizer-test-results" style="margin-top: 15px;"></div>
			</div>

			<?php if ( isset( $plugin_settings['server_config_type'] ) && $plugin_settings['server_config_type'] !== 'none' ) : ?>
			<div class="wp-image-optimizer-server-config-section" style="margin-top: 30px;">
				<h3><?php esc_html_e( 'Generated Server Configuration', 'wp-image-optimizer' ); ?></h3>
				<p><?php esc_html_e( 'Copy and paste this configuration into your web server to enable automatic image serving.', 'wp-image-optimizer' ); ?></p>
				
				<div class="wp-image-optimizer-config-container">
					<div class="wp-image-optimizer-config-header">
						<span class="wp-image-optimizer-config-type">
							<?php echo esc_html( ucfirst( $plugin_settings['server_config_type'] ) ); ?> Configuration
						</span>
						<button type="button" class="button button-secondary wp-image-optimizer-copy-config" data-config-type="<?php echo esc_attr( $plugin_settings['server_config_type'] ); ?>">
							<span class="dashicons dashicons-admin-page"></span>
							<?php esc_html_e( 'Copy to Clipboard', 'wp-image-optimizer' ); ?>
						</button>
					</div>
					<textarea class="wp-image-optimizer-config-textarea" readonly id="wp-image-optimizer-server-config"></textarea>
					<div class="wp-image-optimizer-config-validation" id="wp-image-optimizer-config-validation"></div>
				</div>
			</div>
			<?php endif; ?>
		</div>

		<!-- Submit Button -->
		<div class="wp-image-optimizer-button-group">
			<?php wp_nonce_field( 'wp_image_optimizer_admin', 'wp_image_optimizer_nonce' ); ?>
			<?php submit_button( __( 'Save Settings', 'wp-image-optimizer' ), 'primary', 'submit', false ); ?>
		</div>
	</form>
</div>