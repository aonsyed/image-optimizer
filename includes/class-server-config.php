<?php
/**
 * Server Configuration Generator
 *
 * Generates web server configuration snippets for automatic image serving.
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Server_Config
 *
 * Handles generation of web server configuration snippets for Nginx and Apache.
 */
class Server_Config {

	/**
	 * Plugin upload path relative to WordPress root.
	 *
	 * @var string
	 */
	private $upload_path;

	/**
	 * Plugin endpoint path.
	 *
	 * @var string
	 */
	private $endpoint_path;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$upload_dir = wp_upload_dir();
		$this->upload_path = str_replace( ABSPATH, '', $upload_dir['basedir'] );
		$this->endpoint_path = '/wp-content/plugins/wp-image-optimizer/public/endpoint.php';
	}

	/**
	 * Generate Nginx configuration snippet.
	 *
	 * @return string Nginx configuration.
	 */
	public function generate_nginx_config() {
		$config = "# WordPress Image Optimizer - Nginx Configuration\n";
		$config .= "# Add this to your server block\n\n";
		
		$config .= "# Serve optimized images with fallback\n";
		$config .= "location ~* \\.(jpe?g|png|gif)\$ {\n";
		$config .= "    set \$webp_suffix \"\";\n";
		$config .= "    set \$avif_suffix \"\";\n\n";
		
		$config .= "    # Check for AVIF support\n";
		$config .= "    if (\$http_accept ~* \"image/avif\") {\n";
		$config .= "        set \$avif_suffix \".avif\";\n";
		$config .= "    }\n\n";
		
		$config .= "    # Check for WebP support\n";
		$config .= "    if (\$http_accept ~* \"image/webp\") {\n";
		$config .= "        set \$webp_suffix \".webp\";\n";
		$config .= "    }\n\n";
		
		$config .= "    # Try AVIF first, then WebP, then original, then PHP handler\n";
		$config .= "    try_files \$uri\$avif_suffix \$uri\$webp_suffix \$uri @wp_image_optimizer;\n\n";
		
		$config .= "    # Cache headers for images\n";
		$config .= "    expires 1y;\n";
		$config .= "    add_header Cache-Control \"public, immutable\";\n";
		$config .= "    add_header Vary \"Accept\";\n";
		$config .= "}\n\n";
		
		$config .= "# Fallback handler for on-demand conversion\n";
		$config .= "location @wp_image_optimizer {\n";
		$config .= "    rewrite ^(.+)\$ {$this->endpoint_path}?file=\$1 last;\n";
		$config .= "}\n";

		return $config;
	}

	/**
	 * Generate Apache .htaccess configuration snippet.
	 *
	 * @return string Apache configuration.
	 */
	public function generate_apache_config() {
		$config = "# WordPress Image Optimizer - Apache Configuration\n";
		$config .= "# Add this to your .htaccess file or virtual host configuration\n\n";
		
		$config .= "<IfModule mod_rewrite.c>\n";
		$config .= "    RewriteEngine On\n\n";
		
		$config .= "    # Check for AVIF support and serve if available\n";
		$config .= "    RewriteCond %{HTTP_ACCEPT} image/avif\n";
		$config .= "    RewriteCond %{REQUEST_FILENAME} \\.(jpe?g|png|gif)\$\n";
		$config .= "    RewriteCond %{REQUEST_FILENAME}\\.avif -f\n";
		$config .= "    RewriteRule ^(.+)\$ \$1.avif [T=image/avif,E=accept:1,L]\n\n";
		
		$config .= "    # Check for WebP support and serve if available\n";
		$config .= "    RewriteCond %{HTTP_ACCEPT} image/webp\n";
		$config .= "    RewriteCond %{REQUEST_FILENAME} \\.(jpe?g|png|gif)\$\n";
		$config .= "    RewriteCond %{REQUEST_FILENAME}\\.webp -f\n";
		$config .= "    RewriteRule ^(.+)\$ \$1.webp [T=image/webp,E=accept:1,L]\n\n";
		
		$config .= "    # Fallback to PHP handler for on-demand conversion\n";
		$config .= "    RewriteCond %{REQUEST_FILENAME} \\.(jpe?g|png|gif)\$\n";
		$config .= "    RewriteCond %{REQUEST_FILENAME} !-f\n";
		$config .= "    RewriteRule ^(.+)\$ {$this->endpoint_path}?file=\$1 [QSA,L]\n";
		$config .= "</IfModule>\n\n";
		
		$config .= "# Set proper headers for optimized images\n";
		$config .= "<IfModule mod_headers.c>\n";
		$config .= "    # Add Vary header for content negotiation\n";
		$config .= "    Header append Vary Accept env=accept\n\n";
		
		$config .= "    # Set proper MIME types\n";
		$config .= "    <FilesMatch \"\\.(webp)\$\">\n";
		$config .= "        Header set Content-Type \"image/webp\"\n";
		$config .= "    </FilesMatch>\n";
		$config .= "    <FilesMatch \"\\.(avif)\$\">\n";
		$config .= "        Header set Content-Type \"image/avif\"\n";
		$config .= "    </FilesMatch>\n";
		$config .= "</IfModule>\n\n";
		
		$config .= "# Set cache expiration for optimized images\n";
		$config .= "<IfModule mod_expires.c>\n";
		$config .= "    ExpiresActive On\n";
		$config .= "    ExpiresByType image/webp \"access plus 1 year\"\n";
		$config .= "    ExpiresByType image/avif \"access plus 1 year\"\n";
		$config .= "</IfModule>\n";

		return $config;
	}

	/**
	 * Validate Nginx configuration syntax.
	 *
	 * @param string $config Configuration to validate.
	 * @return array Validation result with 'valid' boolean and 'errors' array.
	 */
	public function validate_nginx_config( $config ) {
		$errors = array();
		$valid = true;

		// Check for required directives
		if ( ! preg_match('/location\s+~\*\s+.*\{/', $config) ) {
			$errors[] = 'Missing location directive for image files';
			$valid = false;
		}

		if ( ! preg_match('/try_files/', $config) ) {
			$errors[] = 'Missing try_files directive';
			$valid = false;
		}

		// Check for balanced braces
		$open_braces = substr_count( $config, '{' );
		$close_braces = substr_count( $config, '}' );
		if ( $open_braces !== $close_braces ) {
			$errors[] = 'Unbalanced braces in configuration';
			$valid = false;
		}

		// Check for proper variable syntax
		if ( preg_match('/\$[a-zA-Z_][a-zA-Z0-9_]*[^a-zA-Z0-9_]/', $config) ) {
			if ( ! preg_match('/\$[a-zA-Z_][a-zA-Z0-9_]*/', $config) ) {
				$errors[] = 'Invalid variable syntax';
				$valid = false;
			}
		}

		return array(
			'valid' => $valid,
			'errors' => $errors,
		);
	}

	/**
	 * Validate Apache configuration syntax.
	 *
	 * @param string $config Configuration to validate.
	 * @return array Validation result with 'valid' boolean and 'errors' array.
	 */
	public function validate_apache_config( $config ) {
		$errors = array();
		$valid = true;

		// Check for required modules
		if ( ! preg_match('/<IfModule\s+mod_rewrite\.c>/', $config) ) {
			$errors[] = 'Missing mod_rewrite module check';
			$valid = false;
		}

		// Check for RewriteEngine directive
		if ( preg_match('/<IfModule\s+mod_rewrite\.c>/', $config) && ! preg_match('/RewriteEngine\s+On/', $config) ) {
			$errors[] = 'Missing RewriteEngine On directive';
			$valid = false;
		}

		// Check for balanced module tags
		$open_modules = preg_match_all('/<IfModule/', $config);
		$close_modules = preg_match_all('/<\/IfModule>/', $config);
		if ( $open_modules !== $close_modules ) {
			$errors[] = 'Unbalanced IfModule tags';
			$valid = false;
		}

		// Check for proper RewriteRule syntax
		if ( preg_match_all('/RewriteRule\s+(.+)/', $config, $matches) ) {
			foreach ( $matches[1] as $rule ) {
				$parts = preg_split('/\s+/', trim( $rule ));
				if ( count( $parts ) < 2 ) {
					$errors[] = 'Invalid RewriteRule syntax: ' . $rule;
					$valid = false;
				}
			}
		}

		return array(
			'valid' => $valid,
			'errors' => $errors,
		);
	}

	/**
	 * Get configuration for specified server type.
	 *
	 * @param string $server_type Server type ('nginx' or 'apache').
	 * @return string|WP_Error Configuration string or error.
	 */
	public function get_config( $server_type ) {
		switch ( $server_type ) {
			case 'nginx':
				return $this->generate_nginx_config();
			case 'apache':
				return $this->generate_apache_config();
			default:
				return new WP_Error( 'invalid_server_type', 'Invalid server type. Use "nginx" or "apache".' );
		}
	}

	/**
	 * Validate configuration for specified server type.
	 *
	 * @param string $config Configuration to validate.
	 * @param string $server_type Server type ('nginx' or 'apache').
	 * @return array|WP_Error Validation result or error.
	 */
	public function validate_config( $config, $server_type ) {
		switch ( $server_type ) {
			case 'nginx':
				return $this->validate_nginx_config( $config );
			case 'apache':
				return $this->validate_apache_config( $config );
			default:
				return new WP_Error( 'invalid_server_type', 'Invalid server type. Use "nginx" or "apache".' );
		}
	}
}