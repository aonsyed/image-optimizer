<?php
/**
 * Tests for server configuration generation
 *
 * @package WP_Image_Optimizer
 */

class Test_Server_Config extends WP_UnitTestCase {

	/**
	 * Server config instance
	 *
	 * @var WP_Image_Optimizer_Server_Config
	 */
	private $server_config;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Create server config instance
		$this->server_config = new WP_Image_Optimizer_Server_Config();
	}

	/**
	 * Test Nginx configuration generation
	 */
	public function test_nginx_configuration_generation() {
		// Generate Nginx configuration
		$config = $this->server_config->generate_nginx_config();
		
		// Check if configuration is not empty
		$this->assertNotEmpty( $config );
		
		// Check if configuration contains required directives
		$this->assertStringContainsString( 'location ~* \.(jpe?g|png|gif)$', $config );
		$this->assertStringContainsString( 'set $webp_suffix', $config );
		$this->assertStringContainsString( 'set $avif_suffix', $config );
		$this->assertStringContainsString( 'if ($http_accept ~* "image/avif")', $config );
		$this->assertStringContainsString( 'if ($http_accept ~* "image/webp")', $config );
		$this->assertStringContainsString( 'try_files', $config );
		$this->assertStringContainsString( 'add_header Vary "Accept"', $config );
	}

	/**
	 * Test Apache configuration generation
	 */
	public function test_apache_configuration_generation() {
		// Generate Apache configuration
		$config = $this->server_config->generate_apache_config();
		
		// Check if configuration is not empty
		$this->assertNotEmpty( $config );
		
		// Check if configuration contains required directives
		$this->assertStringContainsString( '<IfModule mod_rewrite.c>', $config );
		$this->assertStringContainsString( 'RewriteEngine On', $config );
		$this->assertStringContainsString( 'RewriteCond %{HTTP_ACCEPT} image/avif', $config );
		$this->assertStringContainsString( 'RewriteCond %{HTTP_ACCEPT} image/webp', $config );
		$this->assertStringContainsString( 'RewriteRule', $config );
		$this->assertStringContainsString( '<IfModule mod_headers.c>', $config );
		$this->assertStringContainsString( 'Header append Vary Accept', $config );
	}

	/**
	 * Test configuration with custom paths
	 */
	public function test_configuration_with_custom_paths() {
		// Set custom paths
		$custom_paths = array(
			'plugin_url' => 'https://example.com/wp-content/plugins/custom-plugin',
			'upload_dir' => '/var/www/html/wp-content/custom-uploads',
		);
		
		// Generate Nginx configuration with custom paths
		$nginx_config = $this->server_config->generate_nginx_config( $custom_paths );
		
		// Check if configuration contains custom paths
		$this->assertStringContainsString( 'custom-plugin', $nginx_config );
		$this->assertStringContainsString( 'custom-uploads', $nginx_config );
		
		// Generate Apache configuration with custom paths
		$apache_config = $this->server_config->generate_apache_config( $custom_paths );
		
		// Check if configuration contains custom paths
		$this->assertStringContainsString( 'custom-plugin', $apache_config );
		$this->assertStringContainsString( 'custom-uploads', $apache_config );
	}

	/**
	 * Test configuration with disabled formats
	 */
	public function test_configuration_with_disabled_formats() {
		// Disable AVIF format
		WP_Image_Optimizer_Settings_Manager::update_settings( array(
			'formats' => array(
				'avif' => array( 'enabled' => false ),
				'webp' => array( 'enabled' => true ),
			),
		) );
		
		// Generate Nginx configuration
		$nginx_config = $this->server_config->generate_nginx_config();
		
		// Check if configuration doesn't contain AVIF directives
		$this->assertStringNotContainsString( 'set $avif_suffix', $nginx_config );
		$this->assertStringNotContainsString( 'if ($http_accept ~* "image/avif")', $nginx_config );
		
		// Check if configuration contains WebP directives
		$this->assertStringContainsString( 'set $webp_suffix', $nginx_config );
		$this->assertStringContainsString( 'if ($http_accept ~* "image/webp")', $nginx_config );
		
		// Generate Apache configuration
		$apache_config = $this->server_config->generate_apache_config();
		
		// Check if configuration doesn't contain AVIF directives
		$this->assertStringNotContainsString( 'RewriteCond %{HTTP_ACCEPT} image/avif', $apache_config );
		
		// Check if configuration contains WebP directives
		$this->assertStringContainsString( 'RewriteCond %{HTTP_ACCEPT} image/webp', $apache_config );
		
		// Reset settings
		WP_Image_Optimizer_Settings_Manager::reset_settings();
	}

	/**
	 * Test configuration with all formats disabled
	 */
	public function test_configuration_with_all_formats_disabled() {
		// Disable all formats
		WP_Image_Optimizer_Settings_Manager::update_settings( array(
			'formats' => array(
				'avif' => array( 'enabled' => false ),
				'webp' => array( 'enabled' => false ),
			),
		) );
		
		// Generate Nginx configuration
		$nginx_config = $this->server_config->generate_nginx_config();
		
		// Check if configuration is empty or contains a comment
		$this->assertTrue( empty( $nginx_config ) || strpos( $nginx_config, '# No formats enabled' ) !== false );
		
		// Generate Apache configuration
		$apache_config = $this->server_config->generate_apache_config();
		
		// Check if configuration is empty or contains a comment
		$this->assertTrue( empty( $apache_config ) || strpos( $apache_config, '# No formats enabled' ) !== false );
		
		// Reset settings
		WP_Image_Optimizer_Settings_Manager::reset_settings();
	}

	/**
	 * Test configuration validation
	 */
	public function test_configuration_validation() {
		// Generate Nginx configuration
		$nginx_config = $this->server_config->generate_nginx_config();
		
		// Validate Nginx configuration
		$is_valid = $this->server_config->validate_nginx_config( $nginx_config );
		
		// Should be valid
		$this->assertTrue( $is_valid );
		
		// Generate Apache configuration
		$apache_config = $this->server_config->generate_apache_config();
		
		// Validate Apache configuration
		$is_valid = $this->server_config->validate_apache_config( $apache_config );
		
		// Should be valid
		$this->assertTrue( $is_valid );
	}

	/**
	 * Test configuration validation with invalid config
	 */
	public function test_configuration_validation_with_invalid_config() {
		// Invalid Nginx configuration
		$invalid_nginx_config = "location { invalid syntax }";
		
		// Validate invalid Nginx configuration
		$is_valid = $this->server_config->validate_nginx_config( $invalid_nginx_config );
		
		// Should be invalid
		$this->assertFalse( $is_valid );
		
		// Invalid Apache configuration
		$invalid_apache_config = "<IfModule mod_rewrite.c>\nRewriteEngine On\nInvalidDirective\n</IfModule>";
		
		// Validate invalid Apache configuration
		$is_valid = $this->server_config->validate_apache_config( $invalid_apache_config );
		
		// Should be invalid
		$this->assertFalse( $is_valid );
	}

	/**
	 * Test getting server type
	 */
	public function test_get_server_type() {
		// Get server type
		$server_type = $this->server_config->get_server_type();
		
		// Should be one of the supported types or 'unknown'
		$this->assertContains( $server_type, array( 'apache', 'nginx', 'litespeed', 'iis', 'unknown' ) );
	}

	/**
	 * Test getting server info
	 */
	public function test_get_server_info() {
		// Get server info
		$server_info = $this->server_config->get_server_info();
		
		// Check if server info is an array
		$this->assertIsArray( $server_info );
		
		// Check if server info contains required keys
		$this->assertArrayHasKey( 'server_type', $server_info );
		$this->assertArrayHasKey( 'server_software', $server_info );
		$this->assertArrayHasKey( 'supports_htaccess', $server_info );
		$this->assertArrayHasKey( 'supports_nginx_conf', $server_info );
	}

	/**
	 * Test getting recommended configuration
	 */
	public function test_get_recommended_configuration() {
		// Get recommended configuration
		$recommended_config = $this->server_config->get_recommended_configuration();
		
		// Check if recommended configuration is not empty
		$this->assertNotEmpty( $recommended_config );
		
		// Check if recommended configuration is a string
		$this->assertIsString( $recommended_config );
	}

	/**
	 * Test configuration with custom endpoint
	 */
	public function test_configuration_with_custom_endpoint() {
		// Set custom endpoint
		$custom_endpoint = '/custom-endpoint.php';
		
		// Generate Nginx configuration with custom endpoint
		$nginx_config = $this->server_config->generate_nginx_config( array(), $custom_endpoint );
		
		// Check if configuration contains custom endpoint
		$this->assertStringContainsString( $custom_endpoint, $nginx_config );
		
		// Generate Apache configuration with custom endpoint
		$apache_config = $this->server_config->generate_apache_config( array(), $custom_endpoint );
		
		// Check if configuration contains custom endpoint
		$this->assertStringContainsString( $custom_endpoint, $apache_config );
	}

	/**
	 * Test configuration with custom MIME types
	 */
	public function test_configuration_with_custom_mime_types() {
		// Set custom MIME types
		$custom_mime_types = array( 'jpg', 'jpeg', 'png' );
		
		// Generate Nginx configuration with custom MIME types
		$nginx_config = $this->server_config->generate_nginx_config( array(), null, $custom_mime_types );
		
		// Check if configuration contains custom MIME types
		$this->assertStringContainsString( '(jpe?g|png)$', $nginx_config );
		$this->assertStringNotContainsString( 'gif', $nginx_config );
		
		// Generate Apache configuration with custom MIME types
		$apache_config = $this->server_config->generate_apache_config( array(), null, $custom_mime_types );
		
		// Check if configuration contains custom MIME types
		$this->assertStringContainsString( '\.(jpe?g|png)$', $apache_config );
		$this->assertStringNotContainsString( 'gif', $apache_config );
	}
}