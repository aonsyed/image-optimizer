<?php
/**
 * Unit tests for Settings Manager
 *
 * @package WP_Image_Optimizer
 */

/**
 * Test class for WP_Image_Optimizer_Settings_Manager
 */
class Test_WP_Image_Optimizer_Settings_Manager extends WP_UnitTestCase {

	/**
	 * Settings Manager class instance
	 *
	 * @var WP_Image_Optimizer_Settings_Manager
	 */
	private $settings_manager;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Include the settings manager class
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-settings-manager.php';
		
		// Clear any existing settings
		delete_option( 'wp_image_optimizer_settings' );
		
		// Clear cached settings
		$reflection = new ReflectionClass( 'WP_Image_Optimizer_Settings_Manager' );
		$cached_settings_property = $reflection->getProperty( 'cached_settings' );
		$cached_settings_property->setAccessible( true );
		$cached_settings_property->setValue( null );
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		// Clean up settings
		delete_option( 'wp_image_optimizer_settings' );
		
		// Clear cached settings
		$reflection = new ReflectionClass( 'WP_Image_Optimizer_Settings_Manager' );
		$cached_settings_property = $reflection->getProperty( 'cached_settings' );
		$cached_settings_property->setAccessible( true );
		$cached_settings_property->setValue( null );
		
		parent::tearDown();
	}

	/**
	 * Test getting default settings
	 */
	public function test_get_default_settings() {
		$defaults = WP_Image_Optimizer_Settings_Manager::get_default_settings();
		
		$this->assertIsArray( $defaults );
		$this->assertTrue( $defaults['enabled'] );
		$this->assertEquals( 'auto', $defaults['conversion_mode'] );
		$this->assertEquals( 80, $defaults['formats']['webp']['quality'] );
		$this->assertEquals( 75, $defaults['formats']['avif']['quality'] );
	}

	/**
	 * Test getting settings returns defaults when no settings exist
	 */
	public function test_get_settings_returns_defaults() {
		$settings = WP_Image_Optimizer_Settings_Manager::get_settings();
		$defaults = WP_Image_Optimizer_Settings_Manager::get_default_settings();
		
		$this->assertEquals( $defaults, $settings );
	}

	/**
	 * Test getting specific setting with dot notation
	 */
	public function test_get_setting_with_dot_notation() {
		$webp_quality = WP_Image_Optimizer_Settings_Manager::get_setting( 'formats.webp.quality' );
		$this->assertEquals( 80, $webp_quality );
		
		$avif_enabled = WP_Image_Optimizer_Settings_Manager::get_setting( 'formats.avif.enabled' );
		$this->assertTrue( $avif_enabled );
	}

	/**
	 * Test getting non-existent setting returns default
	 */
	public function test_get_setting_returns_default_for_nonexistent() {
		$value = WP_Image_Optimizer_Settings_Manager::get_setting( 'nonexistent.setting', 'default_value' );
		$this->assertEquals( 'default_value', $value );
	}

	/**
	 * Test updating settings
	 */
	public function test_update_settings() {
		$new_settings = array(
			'enabled' => false,
			'conversion_mode' => 'manual',
			'formats' => array(
				'webp' => array(
					'quality' => 90,
				),
			),
		);

		$result = WP_Image_Optimizer_Settings_Manager::update_settings( $new_settings );
		$this->assertTrue( $result );

		// Verify settings were updated
		$this->assertFalse( WP_Image_Optimizer_Settings_Manager::get_setting( 'enabled' ) );
		$this->assertEquals( 'manual', WP_Image_Optimizer_Settings_Manager::get_setting( 'conversion_mode' ) );
		$this->assertEquals( 90, WP_Image_Optimizer_Settings_Manager::get_setting( 'formats.webp.quality' ) );
		
		// Verify merge behavior - avif settings should remain default
		$this->assertEquals( 75, WP_Image_Optimizer_Settings_Manager::get_setting( 'formats.avif.quality' ) );
	}

	/**
	 * Test updating settings without merge
	 */
	public function test_update_settings_without_merge() {
		$new_settings = array(
			'enabled' => false,
			'conversion_mode' => 'manual',
		);

		$result = WP_Image_Optimizer_Settings_Manager::update_settings( $new_settings, false );
		$this->assertTrue( $result );

		// Only the provided settings should exist in database
		$db_option = get_option( 'wp_image_optimizer_settings' );
		$this->assertEquals( $new_settings, $db_option['settings'] );
	}

	/**
	 * Test CLI settings update
	 */
	public function test_update_cli_settings() {
		$cli_settings = array(
			'enabled' => false,
			'max_file_size' => 5242880, // 5MB
		);

		$result = WP_Image_Optimizer_Settings_Manager::update_cli_settings( $cli_settings );
		$this->assertTrue( $result );

		// Verify CLI settings are stored separately
		$option_data = get_option( 'wp_image_optimizer_settings' );
		$this->assertEquals( $cli_settings, $option_data['cli_settings'] );
	}

	/**
	 * Test settings precedence: database > CLI > config > defaults
	 */
	public function test_settings_precedence() {
		// Set CLI settings
		$cli_settings = array( 'enabled' => false );
		WP_Image_Optimizer_Settings_Manager::update_cli_settings( $cli_settings );

		// Set database (UI) settings
		$ui_settings = array( 'conversion_mode' => 'manual' );
		WP_Image_Optimizer_Settings_Manager::update_settings( $ui_settings );

		$settings = WP_Image_Optimizer_Settings_Manager::get_settings( true );

		// UI setting should override CLI
		$this->assertEquals( 'manual', $settings['conversion_mode'] );
		
		// CLI setting should be applied where UI doesn't override
		$this->assertFalse( $settings['enabled'] );
		
		// Default should be used where neither CLI nor UI override
		$this->assertEquals( 80, $settings['formats']['webp']['quality'] );
	}

	/**
	 * Test boolean validation
	 */
	public function test_validate_boolean_setting() {
		// Test valid boolean values
		$this->assertTrue( WP_Image_Optimizer_Settings_Manager::validate_setting( 'enabled', true ) );
		$this->assertFalse( WP_Image_Optimizer_Settings_Manager::validate_setting( 'enabled', false ) );
		
		// Test string representations
		$this->assertTrue( WP_Image_Optimizer_Settings_Manager::validate_setting( 'enabled', 'true' ) );
		$this->assertTrue( WP_Image_Optimizer_Settings_Manager::validate_setting( 'enabled', '1' ) );
		$this->assertFalse( WP_Image_Optimizer_Settings_Manager::validate_setting( 'enabled', 'false' ) );
		$this->assertFalse( WP_Image_Optimizer_Settings_Manager::validate_setting( 'enabled', '0' ) );
		
		// Test numeric representations
		$this->assertTrue( WP_Image_Optimizer_Settings_Manager::validate_setting( 'enabled', 1 ) );
		$this->assertFalse( WP_Image_Optimizer_Settings_Manager::validate_setting( 'enabled', 0 ) );
	}

	/**
	 * Test integer validation
	 */
	public function test_validate_integer_setting() {
		// Test valid integer
		$this->assertEquals( 85, WP_Image_Optimizer_Settings_Manager::validate_setting( 'formats.webp.quality', 85 ) );
		
		// Test string number
		$this->assertEquals( 90, WP_Image_Optimizer_Settings_Manager::validate_setting( 'formats.webp.quality', '90' ) );
		
		// Test minimum validation
		$result = WP_Image_Optimizer_Settings_Manager::validate_setting( 'formats.webp.quality', 0 );
		$this->assertInstanceOf( 'WP_Error', $result );
		
		// Test maximum validation
		$result = WP_Image_Optimizer_Settings_Manager::validate_setting( 'formats.webp.quality', 101 );
		$this->assertInstanceOf( 'WP_Error', $result );
		
		// Test non-numeric value
		$result = WP_Image_Optimizer_Settings_Manager::validate_setting( 'formats.webp.quality', 'invalid' );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Test string validation
	 */
	public function test_validate_string_setting() {
		// Test valid string
		$this->assertEquals( 'manual', WP_Image_Optimizer_Settings_Manager::validate_setting( 'conversion_mode', 'manual' ) );
		
		// Test invalid string
		$result = WP_Image_Optimizer_Settings_Manager::validate_setting( 'conversion_mode', 'invalid_mode' );
		$this->assertInstanceOf( 'WP_Error', $result );
		
		// Test numeric string
		$this->assertEquals( '123', WP_Image_Optimizer_Settings_Manager::validate_setting( 'conversion_mode', 123 ) );
	}

	/**
	 * Test array validation
	 */
	public function test_validate_array_setting() {
		// Test valid array with allowed values
		$valid_types = array( 'image/jpeg', 'image/png' );
		$result = WP_Image_Optimizer_Settings_Manager::validate_setting( 'allowed_mime_types', $valid_types );
		$this->assertEquals( $valid_types, $result );
		
		// Test array with invalid value
		$invalid_types = array( 'image/jpeg', 'invalid/type' );
		$result = WP_Image_Optimizer_Settings_Manager::validate_setting( 'allowed_mime_types', $invalid_types );
		$this->assertInstanceOf( 'WP_Error', $result );
		
		// Test non-array value
		$result = WP_Image_Optimizer_Settings_Manager::validate_setting( 'allowed_mime_types', 'not_an_array' );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Test nested array validation (formats)
	 */
	public function test_validate_nested_array_setting() {
		$valid_formats = array(
			'webp' => array(
				'enabled' => true,
				'quality' => 85,
			),
			'avif' => array(
				'enabled' => false,
				'quality' => 70,
			),
		);

		$result = WP_Image_Optimizer_Settings_Manager::validate_setting( 'formats', $valid_formats );
		$this->assertEquals( $valid_formats, $result );
		
		// Test with invalid nested value
		$invalid_formats = array(
			'webp' => array(
				'enabled' => true,
				'quality' => 150, // Invalid - too high
			),
		);

		$result = WP_Image_Optimizer_Settings_Manager::validate_setting( 'formats', $invalid_formats );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Test complete settings validation
	 */
	public function test_validate_complete_settings() {
		$valid_settings = array(
			'enabled' => true,
			'conversion_mode' => 'auto',
			'max_file_size' => 5242880,
			'formats' => array(
				'webp' => array(
					'enabled' => true,
					'quality' => 85,
				),
			),
		);

		$result = WP_Image_Optimizer_Settings_Manager::validate_settings( $valid_settings );
		$this->assertIsArray( $result );
		$this->assertEquals( $valid_settings, $result );
	}

	/**
	 * Test settings validation with errors
	 */
	public function test_validate_settings_with_errors() {
		$invalid_settings = array(
			'enabled' => 'invalid_boolean',
			'conversion_mode' => 'invalid_mode',
			'max_file_size' => -1,
		);

		$result = WP_Image_Optimizer_Settings_Manager::validate_settings( $invalid_settings );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Test unknown setting validation
	 */
	public function test_validate_unknown_setting() {
		$result = WP_Image_Optimizer_Settings_Manager::validate_setting( 'unknown_setting', 'value' );
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'unknown_setting', $result->get_error_code() );
	}

	/**
	 * Test settings reset
	 */
	public function test_reset_settings() {
		// Set some custom settings
		$custom_settings = array( 'enabled' => false );
		WP_Image_Optimizer_Settings_Manager::update_settings( $custom_settings );
		
		// Verify custom settings are applied
		$this->assertFalse( WP_Image_Optimizer_Settings_Manager::get_setting( 'enabled' ) );
		
		// Reset settings
		$result = WP_Image_Optimizer_Settings_Manager::reset_settings();
		$this->assertTrue( $result );
		
		// Verify settings are back to defaults
		$this->assertTrue( WP_Image_Optimizer_Settings_Manager::get_setting( 'enabled' ) );
	}

	/**
	 * Test complete settings reset
	 */
	public function test_reset_all_settings() {
		// Set both UI and CLI settings
		WP_Image_Optimizer_Settings_Manager::update_settings( array( 'enabled' => false ) );
		WP_Image_Optimizer_Settings_Manager::update_cli_settings( array( 'conversion_mode' => 'manual' ) );
		
		// Reset all settings
		$result = WP_Image_Optimizer_Settings_Manager::reset_settings( true );
		$this->assertTrue( $result );
		
		// Verify option is completely removed
		$this->assertFalse( get_option( 'wp_image_optimizer_settings' ) );
	}

	/**
	 * Test settings caching
	 */
	public function test_settings_caching() {
		// Get settings twice - should use cache on second call
		$settings1 = WP_Image_Optimizer_Settings_Manager::get_settings();
		$settings2 = WP_Image_Optimizer_Settings_Manager::get_settings();
		
		$this->assertEquals( $settings1, $settings2 );
		
		// Force refresh should reload settings
		$settings3 = WP_Image_Optimizer_Settings_Manager::get_settings( true );
		$this->assertEquals( $settings1, $settings3 );
	}

	/**
	 * Test wp-config.php settings loading (mocked)
	 */
	public function test_config_settings_loading() {
		// This test would require mocking defined() function
		// For now, we'll test the method exists and returns array
		$reflection = new ReflectionClass( 'WP_Image_Optimizer_Settings_Manager' );
		$method = $reflection->getMethod( 'load_config_settings' );
		$method->setAccessible( true );
		
		$result = $method->invoke( null );
		$this->assertIsArray( $result );
	}

	/**
	 * Test settings source determination
	 */
	public function test_settings_sources() {
		// Set CLI settings
		WP_Image_Optimizer_Settings_Manager::update_cli_settings( array( 'enabled' => false ) );
		
		// Set UI settings
		WP_Image_Optimizer_Settings_Manager::update_settings( array( 'conversion_mode' => 'manual' ) );
		
		$sources = WP_Image_Optimizer_Settings_Manager::get_settings_sources();
		
		$this->assertIsArray( $sources );
		$this->assertArrayHasKey( 'enabled', $sources );
		$this->assertArrayHasKey( 'conversion_mode', $sources );
	}

	/**
	 * Test validation rules retrieval
	 */
	public function test_get_validation_rules() {
		$rules = WP_Image_Optimizer_Settings_Manager::get_validation_rules();
		
		$this->assertIsArray( $rules );
		$this->assertArrayHasKey( 'enabled', $rules );
		$this->assertArrayHasKey( 'formats', $rules );
		$this->assertEquals( 'boolean', $rules['enabled']['type'] );
	}

	/**
	 * Test invalid settings update
	 */
	public function test_invalid_settings_update() {
		// Test with non-array
		$result = WP_Image_Optimizer_Settings_Manager::update_settings( 'not_an_array' );
		$this->assertFalse( $result );
		
		// Test with invalid settings
		$invalid_settings = array( 'enabled' => 'invalid_value' );
		$result = WP_Image_Optimizer_Settings_Manager::update_settings( $invalid_settings );
		$this->assertFalse( $result );
	}

	/**
	 * Test invalid CLI settings update
	 */
	public function test_invalid_cli_settings_update() {
		// Test with non-array
		$result = WP_Image_Optimizer_Settings_Manager::update_cli_settings( 'not_an_array' );
		$this->assertFalse( $result );
		
		// Test with invalid settings
		$invalid_settings = array( 'max_file_size' => -1 );
		$result = WP_Image_Optimizer_Settings_Manager::update_cli_settings( $invalid_settings );
		$this->assertFalse( $result );
	}
}