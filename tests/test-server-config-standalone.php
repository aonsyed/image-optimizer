<?php
/**
 * Tests for standalone server configuration
 *
 * @package WP_Image_Optimizer
 */

class Test_Server_Config_Standalone extends WP_UnitTestCase {

	/**
	 * Test Nginx configuration standalone functionality
	 */
	public function test_nginx_configuration_standalone() {
		// Create server config instance
		$server_config = new WP_Image_Optimizer_Server_Config();
		
		// Generate standalone Nginx configuration
		$config = $server_config->generate_standalone_nginx_config();
		
		// Check if configuration is not empty
		$this->assertNotEmpty( $config );
		
		// Check if configuration contains required directives
		$this->assertStringContainsString( 'server {', $config );
		$this->assertStringContainsString( 'location ~* \.(jpe?g|png|gif)$', $config );
		$this->assertStringContainsString( 'set $webp_suffix', $config );
		$this->assertStringContainsString( 'set $avif_suffix', $config );
		$this->assertStringContainsString( 'if ($http_accept ~* "image/avif")', $config );
		$this->assertStringContainsString( 'if ($http_accept ~* "image/webp")', $config );
		$this->assertStringContainsString( 'try_files', $config );
		$this->assertStringContainsString( 'add_header Vary "Accept"', $config );
	}

	/**
	 * Test Apache configuration standalone functionality
	 */
	public function test_apache_configuration_standalone() {
		// Create server config instance
		$server_config = new WP_Image_Optimizer_Server_Config();
		
		// Generate standalone Apache configuration
		$config = $server_config->generate_standalone_apache_config();
		
		// Check if configuration is not empty
		$this->assertNotEmpty( $config );
		
		// Check if configuration contains required directives
		$this->assertStringContainsString( '<VirtualHost *:80>', $config );
		$this->assertStringContainsString( '<IfModule mod_rewrite.c>', $config );
		$this->assertStringContainsString( 'RewriteEngine On', $config );
		$this->assertStringContainsString( 'RewriteCond %{HTTP_ACCEPT} image/avif', $config );
		$this->assertStringContainsString( 'RewriteCond %{HTTP_ACCEPT} image/webp', $config );
		$this->assertStringContainsString( 'RewriteRule', $config );
		$this->assertStringContainsString( '<IfModule mod_headers.c>', $config );
		$this->assertStringContainsString( 'Header append Vary Accept', $config );
	}

	/**
	 * Test configuration with custom domain
	 */
	public function test_configuration_with_custom_domain() {
		// Create server config instance
		$server_config = new WP_Image_Optimizer_Server_Config();
		
		// Set custom domain
		$custom_domain = 'example.com';
		
		// Generate standalone Nginx configuration with custom domain
		$nginx_config = $server_config->generate_standalone_nginx_config( $custom_domain );
		
		// Check if configuration contains custom domain
		$this->assertStringContainsString( "server_name $custom_domain", $nginx_config );
		
		// Generate standalone Apache configuration with custom domain
		$apache_config = $server_config->generate_standalone_apache_config( $custom_domain );
		
		// Check if configuration contains custom domain
		$this->assertStringContainsString( "ServerName $custom_domain", $apache_config );
	}

	/**
	 * Test configuration with custom document root
	 */
	public function test_configuration_with_custom_document_root() {
		// Create server config instance
		$server_config = new WP_Image_Optimizer_Server_Config();
		
		// Set custom document root
		$custom_document_root = '/var/www/html/custom';
		
		// Generate standalone Nginx configuration with custom document root
		$nginx_config = $server_config->generate_standalone_nginx_config( 'example.com', $custom_document_root );
		
		// Check if configuration contains custom document root
		$this->assertStringContainsString( "root $custom_document_root", $nginx_config );
		
		// Generate standalone Apache configuration with custom document root
		$apache_config = $server_config->generate_standalone_apache_config( 'example.com', $custom_document_root );
		
		// Check if configuration contains custom document root
		$this->assertStringContainsString( "DocumentRoot $custom_document_root", $apache_config );
	}

	/**
	 * Test configuration with SSL
	 */
	public function test_configuration_with_ssl() {
		// Create server config instance
		$server_config = new WP_Image_Optimizer_Server_Config();
		
		// Generate standalone Nginx configuration with SSL
		$nginx_config = $server_config->generate_standalone_nginx_config( 'example.com', null, true );
		
		// Check if configuration contains SSL directives
		$this->assertStringContainsString( 'listen 443 ssl', $nginx_config );
		$this->assertStringContainsString( 'ssl_certificate', $nginx_config );
		$this->assertStringContainsString( 'ssl_certificate_key', $nginx_config );
		
		// Generate standalone Apache configuration with SSL
		$apache_config = $server_config->generate_standalone_apache_config( 'example.com', null, true );
		
		// Check if configuration contains SSL directives
		$this->assertStringContainsString( '<VirtualHost *:443>', $apache_config );
		$this->assertStringContainsString( 'SSLEngine on', $apache_config );
		$this->assertStringContainsString( 'SSLCertificateFile', $apache_config );
		$this->assertStringContainsString( 'SSLCertificateKeyFile', $apache_config );
	}

	/**
	 * Test configuration with custom SSL paths
	 */
	public function test_configuration_with_custom_ssl_paths() {
		// Create server config instance
		$server_config = new WP_Image_Optimizer_Server_Config();
		
		// Set custom SSL paths
		$ssl_certificate = '/etc/ssl/custom/certificate.crt';
		$ssl_certificate_key = '/etc/ssl/custom/private.key';
		
		// Generate standalone Nginx configuration with custom SSL paths
		$nginx_config = $server_config->generate_standalone_nginx_config(
			'example.com',
			null,
			true,
			$ssl_certificate,
			$ssl_certificate_key
		);
		
		// Check if configuration contains custom SSL paths
		$this->assertStringContainsString( "ssl_certificate $ssl_certificate", $nginx_config );
		$this->assertStringContainsString( "ssl_certificate_key $ssl_certificate_key", $nginx_config );
		
		// Generate standalone Apache configuration with custom SSL paths
		$apache_config = $server_config->generate_standalone_apache_config(
			'example.com',
			null,
			true,
			$ssl_certificate,
			$ssl_certificate_key
		);
		
		// Check if configuration contains custom SSL paths
		$this->assertStringContainsString( "SSLCertificateFile $ssl_certificate", $apache_config );
		$this->assertStringContainsString( "SSLCertificateKeyFile $ssl_certificate_key", $apache_config );
	}

	/**
	 * Test configuration with HTTP to HTTPS redirect
	 */
	public function test_configuration_with_http_to_https_redirect() {
		// Create server config instance
		$server_config = new WP_Image_Optimizer_Server_Config();
		
		// Generate standalone Nginx configuration with SSL and redirect
		$nginx_config = $server_config->generate_standalone_nginx_config(
			'example.com',
			null,
			true,
			null,
			null,
			true
		);
		
		// Check if configuration contains redirect directives
		$this->assertStringContainsString( 'server {', $nginx_config );
		$this->assertStringContainsString( 'listen 80', $nginx_config );
		$this->assertStringContainsString( 'return 301 https://$host$request_uri', $nginx_config );
		
		// Generate standalone Apache configuration with SSL and redirect
		$apache_config = $server_config->generate_standalone_apache_config(
			'example.com',
			null,
			true,
			null,
			null,
			true
		);
		
		// Check if configuration contains redirect directives
		$this->assertStringContainsString( '<VirtualHost *:80>', $apache_config );
		$this->assertStringContainsString( 'RewriteEngine On', $apache_config );
		$this->assertStringContainsString( 'RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [L,R=301]', $apache_config );
	}

	/**
	 * Test configuration with custom PHP handler
	 */
	public function test_configuration_with_custom_php_handler() {
		// Create server config instance
		$server_config = new WP_Image_Optimizer_Server_Config();
		
		// Set custom PHP handler
		$php_handler = 'php-fpm7.4';
		
		// Generate standalone Nginx configuration with custom PHP handler
		$nginx_config = $server_config->generate_standalone_nginx_config(
			'example.com',
			null,
			false,
			null,
			null,
			false,
			$php_handler
		);
		
		// Check if configuration contains custom PHP handler
		$this->assertStringContainsString( "fastcgi_pass unix:/var/run/$php_handler.sock", $nginx_config );
		
		// Generate standalone Apache configuration with custom PHP handler
		$apache_config = $server_config->generate_standalone_apache_config(
			'example.com',
			null,
			false,
			null,
			null,
			false,
			$php_handler
		);
		
		// Check if configuration contains custom PHP handler
		$this->assertStringContainsString( "SetHandler \"proxy:unix:/var/run/$php_handler.sock|fcgi://localhost\"", $apache_config );
	}

	/**
	 * Test configuration with custom cache settings
	 */
	public function test_configuration_with_custom_cache_settings() {
		// Create server config instance
		$server_config = new WP_Image_Optimizer_Server_Config();
		
		// Set custom cache settings
		$cache_time = '30d';
		
		// Generate standalone Nginx configuration with custom cache settings
		$nginx_config = $server_config->generate_standalone_nginx_config(
			'example.com',
			null,
			false,
			null,
			null,
			false,
			null,
			$cache_time
		);
		
		// Check if configuration contains custom cache settings
		$this->assertStringContainsString( "expires $cache_time", $nginx_config );
		
		// Generate standalone Apache configuration with custom cache settings
		$apache_config = $server_config->generate_standalone_apache_config(
			'example.com',
			null,
			false,
			null,
			null,
			false,
			null,
			$cache_time
		);
		
		// Check if configuration contains custom cache settings
		$this->assertStringContainsString( "ExpiresDefault \"access plus $cache_time\"", $apache_config );
	}
}