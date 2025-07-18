<?php
/**
 * Admin Error Logs Display
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$error_handler = WP_Image_Optimizer_Error_Handler::get_instance();
$error_stats = $error_handler->get_error_statistics();
$recent_errors = $error_handler->get_error_logs( 20 );

// Handle clear logs action
if ( isset( $_POST['clear_logs'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'clear_error_logs' ) ) {
	$severity = isset( $_POST['severity'] ) ? sanitize_text_field( $_POST['severity'] ) : null;
	$category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : null;
	
	$cleared = $error_handler->clear_error_logs( $severity, $category );
	if ( $cleared ) {
		echo '<div class="notice notice-success"><p>' . __( 'Error logs cleared successfully.', 'wp-image-optimizer' ) . '</p></div>';
		// Refresh data after clearing
		$error_stats = $error_handler->get_error_statistics();
		$recent_errors = $error_handler->get_error_logs( 20 );
	}
}
?>

<div class="wrap">
	<h1><?php _e( 'Error Logs & Diagnostics', 'wp-image-optimizer' ); ?></h1>
	
	<!-- Error Statistics -->
	<div class="card">
		<h2><?php _e( 'Error Statistics', 'wp-image-optimizer' ); ?></h2>
		<table class="widefat">
			<tbody>
				<tr>
					<td><strong><?php _e( 'Total Errors', 'wp-image-optimizer' ); ?></strong></td>
					<td><?php echo esc_html( $error_stats['total_errors'] ); ?></td>
				</tr>
				<tr>
					<td><strong><?php _e( 'Recent Errors (24h)', 'wp-image-optimizer' ); ?></strong></td>
					<td><?php echo esc_html( $error_stats['recent_errors'] ); ?></td>
				</tr>
				<tr>
					<td><strong><?php _e( 'Last Error', 'wp-image-optimizer' ); ?></strong></td>
					<td>
						<?php 
						if ( $error_stats['last_error_time'] > 0 ) {
							echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $error_stats['last_error_time'] ) );
						} else {
							_e( 'No errors recorded', 'wp-image-optimizer' );
						}
						?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Error Breakdown -->
	<div class="card">
		<h2><?php _e( 'Error Breakdown', 'wp-image-optimizer' ); ?></h2>
		
		<h3><?php _e( 'By Severity', 'wp-image-optimizer' ); ?></h3>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php _e( 'Severity', 'wp-image-optimizer' ); ?></th>
					<th><?php _e( 'Count', 'wp-image-optimizer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $error_stats['errors_by_severity'] as $severity => $count ) : ?>
					<?php if ( $count > 0 ) : ?>
						<tr>
							<td>
								<span class="error-severity error-severity-<?php echo esc_attr( $severity ); ?>">
									<?php echo esc_html( ucfirst( $severity ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $count ); ?></td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h3><?php _e( 'By Category', 'wp-image-optimizer' ); ?></h3>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php _e( 'Category', 'wp-image-optimizer' ); ?></th>
					<th><?php _e( 'Count', 'wp-image-optimizer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $error_stats['errors_by_category'] as $category => $count ) : ?>
					<?php if ( $count > 0 ) : ?>
						<tr>
							<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $category ) ) ); ?></td>
							<td><?php echo esc_html( $count ); ?></td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- Clear Logs Form -->
	<div class="card">
		<h2><?php _e( 'Clear Error Logs', 'wp-image-optimizer' ); ?></h2>
		<form method="post" action="">
			<?php wp_nonce_field( 'clear_error_logs' ); ?>
			
			<table class="form-table">
				<tr>
					<th scope="row"><?php _e( 'Filter by Severity', 'wp-image-optimizer' ); ?></th>
					<td>
						<select name="severity">
							<option value=""><?php _e( 'All Severities', 'wp-image-optimizer' ); ?></option>
							<option value="critical"><?php _e( 'Critical', 'wp-image-optimizer' ); ?></option>
							<option value="error"><?php _e( 'Error', 'wp-image-optimizer' ); ?></option>
							<option value="warning"><?php _e( 'Warning', 'wp-image-optimizer' ); ?></option>
							<option value="notice"><?php _e( 'Notice', 'wp-image-optimizer' ); ?></option>
							<option value="info"><?php _e( 'Info', 'wp-image-optimizer' ); ?></option>
							<option value="debug"><?php _e( 'Debug', 'wp-image-optimizer' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e( 'Filter by Category', 'wp-image-optimizer' ); ?></th>
					<td>
						<select name="category">
							<option value=""><?php _e( 'All Categories', 'wp-image-optimizer' ); ?></option>
							<option value="system"><?php _e( 'System', 'wp-image-optimizer' ); ?></option>
							<option value="conversion"><?php _e( 'Conversion', 'wp-image-optimizer' ); ?></option>
							<option value="configuration"><?php _e( 'Configuration', 'wp-image-optimizer' ); ?></option>
							<option value="runtime"><?php _e( 'Runtime', 'wp-image-optimizer' ); ?></option>
							<option value="security"><?php _e( 'Security', 'wp-image-optimizer' ); ?></option>
							<option value="file_system"><?php _e( 'File System', 'wp-image-optimizer' ); ?></option>
							<option value="validation"><?php _e( 'Validation', 'wp-image-optimizer' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
			
			<?php submit_button( __( 'Clear Selected Logs', 'wp-image-optimizer' ), 'secondary', 'clear_logs' ); ?>
		</form>
	</div>

	<!-- Recent Error Logs -->
	<div class="card">
		<h2><?php _e( 'Recent Error Logs', 'wp-image-optimizer' ); ?></h2>
		
		<?php if ( empty( $recent_errors ) ) : ?>
			<p><?php _e( 'No errors recorded.', 'wp-image-optimizer' ); ?></p>
		<?php else : ?>
			<div class="error-logs-container">
				<?php foreach ( $recent_errors as $error ) : ?>
					<div class="error-log-entry error-severity-<?php echo esc_attr( $error['severity'] ); ?>">
						<div class="error-log-header">
							<span class="error-severity"><?php echo esc_html( ucfirst( $error['severity'] ) ); ?></span>
							<span class="error-category"><?php echo esc_html( ucwords( str_replace( '_', ' ', $error['category'] ) ) ); ?></span>
							<span class="error-timestamp"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $error['timestamp'] ) ); ?></span>
							<span class="error-id"><?php echo esc_html( $error['id'] ); ?></span>
						</div>
						
						<div class="error-log-message">
							<?php if ( $error['user_friendly'] ) : ?>
								<div class="user-friendly-message">
									<strong><?php _e( 'User Message:', 'wp-image-optimizer' ); ?></strong>
									<?php echo esc_html( $error_handler->get_user_friendly_message( $error['message'] ) ); ?>
								</div>
							<?php endif; ?>
							
							<div class="technical-message">
								<strong><?php _e( 'Technical Details:', 'wp-image-optimizer' ); ?></strong>
								<code><?php echo esc_html( $error['message'] ); ?></code>
								<?php if ( ! empty( $error['code'] ) && $error['code'] !== 'generic_error' ) : ?>
									<br><strong><?php _e( 'Error Code:', 'wp-image-optimizer' ); ?></strong> 
									<code><?php echo esc_html( $error['code'] ); ?></code>
								<?php endif; ?>
							</div>
						</div>
						
						<?php if ( ! empty( $error['context'] ) ) : ?>
							<div class="error-log-context">
								<strong><?php _e( 'Context:', 'wp-image-optimizer' ); ?></strong>
								<ul>
									<?php foreach ( $error['context'] as $key => $value ) : ?>
										<li>
											<strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?>:</strong>
											<?php if ( is_array( $value ) || is_object( $value ) ) : ?>
												<pre><?php echo esc_html( wp_json_encode( $value, JSON_PRETTY_PRINT ) ); ?></pre>
											<?php else : ?>
												<code><?php echo esc_html( $value ); ?></code>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
						
						<?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $error['stack_trace'] ) ) : ?>
							<div class="error-log-trace">
								<details>
									<summary><strong><?php _e( 'Stack Trace (Debug Mode)', 'wp-image-optimizer' ); ?></strong></summary>
									<ol>
										<?php foreach ( $error['stack_trace'] as $frame ) : ?>
											<li>
												<code>
													<?php if ( $frame['class'] ) : ?>
														<?php echo esc_html( $frame['class'] ); ?>::
													<?php endif; ?>
													<?php echo esc_html( $frame['function'] ); ?>()
													<br>
													<small>
														<?php echo esc_html( $frame['file'] ); ?>:<?php echo esc_html( $frame['line'] ); ?>
													</small>
												</code>
											</li>
										<?php endforeach; ?>
									</ol>
								</details>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<style>
.error-logs-container {
	max-height: 600px;
	overflow-y: auto;
	border: 1px solid #ddd;
	padding: 10px;
}

.error-log-entry {
	margin-bottom: 20px;
	padding: 15px;
	border-left: 4px solid #ddd;
	background: #f9f9f9;
}

.error-log-entry.error-severity-critical {
	border-left-color: #dc3232;
	background: #ffeaea;
}

.error-log-entry.error-severity-error {
	border-left-color: #dc3232;
	background: #fff2f2;
}

.error-log-entry.error-severity-warning {
	border-left-color: #ffb900;
	background: #fffbf0;
}

.error-log-entry.error-severity-notice {
	border-left-color: #00a0d2;
	background: #f0f8ff;
}

.error-log-entry.error-severity-info {
	border-left-color: #00a0d2;
	background: #f0f8ff;
}

.error-log-entry.error-severity-debug {
	border-left-color: #666;
	background: #f5f5f5;
}

.error-log-header {
	display: flex;
	gap: 15px;
	margin-bottom: 10px;
	font-size: 12px;
	color: #666;
}

.error-severity {
	font-weight: bold;
	text-transform: uppercase;
}

.error-severity-critical,
.error-severity-error {
	color: #dc3232;
}

.error-severity-warning {
	color: #ffb900;
}

.error-severity-notice,
.error-severity-info {
	color: #00a0d2;
}

.error-severity-debug {
	color: #666;
}

.error-log-message {
	margin-bottom: 10px;
}

.user-friendly-message {
	padding: 10px;
	background: #e7f3ff;
	border: 1px solid #b3d9ff;
	border-radius: 3px;
	margin-bottom: 10px;
}

.technical-message code {
	background: #f1f1f1;
	padding: 2px 4px;
	border-radius: 2px;
}

.error-log-context ul {
	margin: 5px 0 0 20px;
}

.error-log-context pre {
	background: #f1f1f1;
	padding: 5px;
	border-radius: 2px;
	font-size: 11px;
	max-height: 100px;
	overflow-y: auto;
}

.error-log-trace details {
	margin-top: 10px;
}

.error-log-trace ol {
	margin: 10px 0 0 20px;
	font-size: 11px;
}

.error-log-trace code {
	background: #f1f1f1;
	padding: 2px 4px;
	border-radius: 2px;
	display: block;
	margin: 2px 0;
}

.error-id {
	font-family: monospace;
	font-size: 10px;
	color: #999;
}
</style>