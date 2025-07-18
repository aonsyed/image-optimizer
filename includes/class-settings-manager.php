<?php
/**
 * Settings Manager class
 *
 * Handles plugin configuration from multiple sources with proper precedence
 *
 * @package WP_Image_Optimizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Manager class
 */
class WP_Image_Optimizer_Settings_Manager {

	/**
	 * Settings option name
	 *
	 * @var string
	 */
	const OPTION_NAME = 'wp_image_optimizer_settings';

	/**
	 * wp-config.php prefix for settings
	 *
	 * @var string
	 */
	const CONFIG_PREFIX = 'WP_IMAGE_OPTIMIZER_';

	/**
	 * Cached settings
	 *
	 * @var array|null
	 */
	private static $cached_settings = null;

	/**
	 * Error handler instance
	 *
	 * @var WP_Image_Optimizer_Error_Handler|null
	 */
	private static $error_handler = null;

	/**
	 * Database manager instance
	 *
	 * @var WP_Image_Optimizer_Database_Manager|null
	 */
	private static $database_manager = null;

	/**
	 * Default settings schema
	 *
	 * @var array
	 */
	private static $default_settings = array(
		'enabled' => true,
		'formats' => array(
			'webp' => array(
				'enabled' => true,
				'quality' => 80,
			),
			'avif' => array(
				'enabled' => true,
				'quality' => 75,
			),
		),
		'conversion_mode' => 'auto', // auto, manual, cli_only
		'preserve_originals' => true,
		'max_file_size' => 10485760, // 10MB in bytes
		'allowed_mime_types' => array( 'image/jpeg', 'image/png', 'image/gif' ),
		'server_config_type' => 'nginx', // nginx, apache, none
	);

	/**
	 * Settings validation rules
	 *
	 * @var array
	 */
	private static $validation_rules = array(
		'enabled' => array(
			'type' => 'boolean',
			'default' => true,
		),
		'formats' => array(
			'type' => 'array',
			'children' => array(
				'webp' => array(
					'type' => 'array',
					'children' => array(
						'enabled' => array( 'type' => 'boolean', 'default' => true ),
						'quality' => array( 'type' => 'integer', 'min' => 1, 'max' => 100, 'default' => 80 ),
					),
				),
				'avif' => array(
					'type' => 'array',
					'children' => array(
						'enabled' => array( 'type' => 'boolean', 'default' => true ),
						'quality' => array( 'type' => 'integer', 'min' => 1, 'max' => 100, 'default' => 75 ),
					),
				),
			),
		),
		'conversion_mode' => array(
			'type' => 'string',
			'allowed' => array( 'auto', 'manual', 'cli_only' ),
			'default' => 'auto',
		),
		'preserve_originals' => array(
			'type' => 'boolean',
			'default' => true,
		),
		'max_file_size' => array(
			'type' => 'integer',
			'min' => 1024, // 1KB minimum
			'max' => 104857600, // 100MB maximum
			'default' => 10485760,
		),
		'allowed_mime_types' => array(
			'type' => 'array',
			'allowed_values' => array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif' ),
			'default' => array( 'image/jpeg', 'image/png', 'image/gif' ),
		),
		'server_config_type' => array(
			'type' => 'string',
			'allowed' => array( 'nginx', 'apache', 'none' ),
			'default' => 'nginx',
		),
	);

	/**
	 * Get all settings with proper precedence
	 *
	 * Precedence order: UI (database) > CLI > wp-config.php > defaults
	 *
	 * @param bool $force_refresh Force refresh of cached settings
	 * @return array Complete settings array
	 */
	public static function get_settings( $force_refresh = false ) {
		if ( null === self::$cached_settings || $force_refresh ) {
			self::$cached_settings = self::load_settings();
		}

		return self::$cached_settings;
	}

	/**
	 * Get a specific setting value
	 *
	 * @param string $key Setting key (supports dot notation for nested values)
	 * @param mixed  $default Default value if setting not found
	 * @return mixed Setting value
	 */
	public static function get_setting( $key, $default = null ) {
		$settings = self::get_settings();
		
		// Handle dot notation for nested settings
		if ( strpos( $key, '.' ) !== false ) {
			$keys = explode( '.', $key );
			$value = $settings;
			
			foreach ( $keys as $nested_key ) {
				if ( ! is_array( $value ) || ! isset( $value[ $nested_key ] ) ) {
					return $default;
				}
				$value = $value[ $nested_key ];
			}
			
			return $value;
		}

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Update settings in database
	 *
	 * @param array $new_settings Settings to update
	 * @param bool  $merge Whether to merge with existing settings
	 * @return bool True on success, false on failure
	 */
	public static function update_settings( $new_settings, $merge = true ) {
		self::init_error_handler();
		
		if ( ! is_array( $new_settings ) ) {
			self::$error_handler->log_error(
				'Settings update failed: new settings must be an array',
				'error',
				'validation',
				array( 'provided_type' => gettype( $new_settings ) )
			);
			return false;
		}

		// Validate settings
		$validated_settings = self::validate_settings( $new_settings );
		if ( is_wp_error( $validated_settings ) ) {
			self::$error_handler->log_error(
				$validated_settings,
				'error',
				'validation',
				array( 'settings_data' => $new_settings )
			);
			return false;
		}

		// Get current database settings
		$current_db_settings = get_option( self::OPTION_NAME, array() );
		$current_settings = isset( $current_db_settings['settings'] ) ? $current_db_settings['settings'] : array();

		// Merge or replace settings
		if ( $merge ) {
			$updated_settings = array_replace_recursive( $current_settings, $validated_settings );
		} else {
			$updated_settings = $validated_settings;
		}

		// Update the complete option structure
		$option_data = array_merge( $current_db_settings, array(
			'settings' => $updated_settings,
			'version' => defined( 'WP_IMAGE_OPTIMIZER_VERSION' ) ? WP_IMAGE_OPTIMIZER_VERSION : '1.0.0',
		) );

		$result = update_option( self::OPTION_NAME, $option_data );

		if ( $result ) {
			// Clear cache on successful update
			self::$cached_settings = null;
			
			// Log successful update
			self::$error_handler->log_error(
				'Settings updated successfully',
				'info',
				'configuration',
				array( 
					'updated_keys' => array_keys( $validated_settings ),
					'merge_mode' => $merge,
				)
			);
		} else {
			// Log update failure
			self::$error_handler->log_error(
				'Failed to update settings in database',
				'error',
				'configuration',
				array( 'option_name' => self::OPTION_NAME )
			);
		}

		return $result;
	}

	/**
	 * Load settings from all sources with proper precedence
	 *
	 * @return array Complete settings array
	 */
	private static function load_settings() {
		// Start with defaults
		$settings = self::$default_settings;

		// Apply wp-config.php settings
		$config_settings = self::load_config_settings();
		if ( ! empty( $config_settings ) ) {
			$settings = array_replace_recursive( $settings, $config_settings );
		}

		// Apply CLI settings (stored in database with special flag)
		$cli_settings = self::load_cli_settings();
		if ( ! empty( $cli_settings ) ) {
			$settings = array_replace_recursive( $settings, $cli_settings );
		}

		// Apply database (UI) settings - highest precedence
		$db_settings = self::load_database_settings();
		if ( ! empty( $db_settings ) ) {
			$settings = array_replace_recursive( $settings, $db_settings );
		}

		return $settings;
	}

	/**
	 * Load settings from wp-config.php
	 *
	 * @return array Settings from wp-config.php
	 */
	private static function load_config_settings() {
		$config_settings = array();

		// Map of setting keys to wp-config constants
		$config_map = array(
			'enabled' => 'ENABLED',
			'conversion_mode' => 'CONVERSION_MODE',
			'preserve_originals' => 'PRESERVE_ORIGINALS',
			'max_file_size' => 'MAX_FILE_SIZE',
			'server_config_type' => 'SERVER_CONFIG_TYPE',
			'formats.webp.enabled' => 'WEBP_ENABLED',
			'formats.webp.quality' => 'WEBP_QUALITY',
			'formats.avif.enabled' => 'AVIF_ENABLED',
			'formats.avif.quality' => 'AVIF_QUALITY',
		);

		foreach ( $config_map as $setting_key => $constant_suffix ) {
			$constant_name = self::CONFIG_PREFIX . $constant_suffix;
			
			if ( defined( $constant_name ) ) {
				$value = constant( $constant_name );
				
				// Handle nested settings
				if ( strpos( $setting_key, '.' ) !== false ) {
					$keys = explode( '.', $setting_key );
					$nested_array = array();
					$current = &$nested_array;
					
					foreach ( $keys as $i => $key ) {
						if ( $i === count( $keys ) - 1 ) {
							$current[ $key ] = $value;
						} else {
							$current[ $key ] = array();
							$current = &$current[ $key ];
						}
					}
					
					$config_settings = array_replace_recursive( $config_settings, $nested_array );
				} else {
					$config_settings[ $setting_key ] = $value;
				}
			}
		}

		return $config_settings;
	}

	/**
	 * Load CLI settings from database
	 *
	 * @return array CLI settings
	 */
	private static function load_cli_settings() {
		$option_data = get_option( self::OPTION_NAME, array() );
		return isset( $option_data['cli_settings'] ) ? $option_data['cli_settings'] : array();
	}

	/**
	 * Load UI settings from database
	 *
	 * @return array Database settings
	 */
	private static function load_database_settings() {
		$option_data = get_option( self::OPTION_NAME, array() );
		return isset( $option_data['settings'] ) ? $option_data['settings'] : array();
	}

	/**
	 * Update CLI settings
	 *
	 * @param array $cli_settings CLI settings to store
	 * @return bool True on success, false on failure
	 */
	public static function update_cli_settings( $cli_settings ) {
		if ( ! is_array( $cli_settings ) ) {
			return false;
		}

		// Validate settings
		$validated_settings = self::validate_settings( $cli_settings );
		if ( is_wp_error( $validated_settings ) ) {
			return false;
		}

		// Get current option data
		$option_data = get_option( self::OPTION_NAME, array() );
		
		// Update CLI settings
		$option_data['cli_settings'] = $validated_settings;
		$option_data['version'] = WP_IMAGE_OPTIMIZER_VERSION;

		$result = update_option( self::OPTION_NAME, $option_data );

		// Clear cache on successful update
		if ( $result ) {
			self::$cached_settings = null;
		}

		return $result;
	}

	/**
	 * Validate settings array
	 *
	 * @param array $settings Settings to validate
	 * @return array|WP_Error Validated settings or WP_Error on failure
	 */
	public static function validate_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return new WP_Error( 'invalid_settings', __( 'Settings must be an array.', 'wp-image-optimizer' ) );
		}

		$validated = array();
		$errors = array();

		foreach ( $settings as $key => $value ) {
			$validation_result = self::validate_setting( $key, $value );
			
			if ( is_wp_error( $validation_result ) ) {
				$errors[ $key ] = $validation_result->get_error_message();
			} else {
				$validated[ $key ] = $validation_result;
			}
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_failed', __( 'Settings validation failed.', 'wp-image-optimizer' ), $errors );
		}

		return $validated;
	}

	/**
	 * Validate a single setting
	 *
	 * @param string $key Setting key
	 * @param mixed  $value Setting value
	 * @return mixed|WP_Error Validated value or WP_Error on failure
	 */
	public static function validate_setting( $key, $value ) {
		if ( ! isset( self::$validation_rules[ $key ] ) ) {
			return new WP_Error( 'unknown_setting', sprintf( __( 'Unknown setting: %s', 'wp-image-optimizer' ), $key ) );
		}

		$rule = self::$validation_rules[ $key ];
		
		return self::validate_value( $value, $rule, $key );
	}

	/**
	 * Validate a value against a rule
	 *
	 * @param mixed  $value Value to validate
	 * @param array  $rule Validation rule
	 * @param string $context Context for error messages
	 * @return mixed|WP_Error Validated value or WP_Error on failure
	 */
	private static function validate_value( $value, $rule, $context = '' ) {
		$type = $rule['type'];

		switch ( $type ) {
			case 'boolean':
				return self::validate_boolean( $value, $rule, $context );
			
			case 'integer':
				return self::validate_integer( $value, $rule, $context );
			
			case 'string':
				return self::validate_string( $value, $rule, $context );
			
			case 'array':
				return self::validate_array( $value, $rule, $context );
			
			default:
				return new WP_Error( 'invalid_rule', sprintf( __( 'Invalid validation rule type: %s', 'wp-image-optimizer' ), $type ) );
		}
	}

	/**
	 * Validate boolean value
	 *
	 * @param mixed $value Value to validate
	 * @param array $rule Validation rule
	 * @param string $context Context for error messages
	 * @return bool|WP_Error
	 */
	private static function validate_boolean( $value, $rule, $context ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		// Try to convert common boolean representations
		if ( is_string( $value ) ) {
			$lower = strtolower( $value );
			if ( in_array( $lower, array( 'true', '1', 'yes', 'on' ), true ) ) {
				return true;
			}
			if ( in_array( $lower, array( 'false', '0', 'no', 'off' ), true ) ) {
				return false;
			}
		}

		if ( is_numeric( $value ) ) {
			return (bool) $value;
		}

		return isset( $rule['default'] ) ? $rule['default'] : false;
	}

	/**
	 * Validate integer value
	 *
	 * @param mixed $value Value to validate
	 * @param array $rule Validation rule
	 * @param string $context Context for error messages
	 * @return int|WP_Error
	 */
	private static function validate_integer( $value, $rule, $context ) {
		if ( ! is_numeric( $value ) ) {
			return new WP_Error( 'invalid_integer', sprintf( __( '%s must be a number.', 'wp-image-optimizer' ), $context ) );
		}

		$int_value = (int) $value;

		// Check minimum value
		if ( isset( $rule['min'] ) && $int_value < $rule['min'] ) {
			return new WP_Error( 'value_too_small', sprintf( __( '%s must be at least %d.', 'wp-image-optimizer' ), $context, $rule['min'] ) );
		}

		// Check maximum value
		if ( isset( $rule['max'] ) && $int_value > $rule['max'] ) {
			return new WP_Error( 'value_too_large', sprintf( __( '%s must be no more than %d.', 'wp-image-optimizer' ), $context, $rule['max'] ) );
		}

		return $int_value;
	}

	/**
	 * Validate string value
	 *
	 * @param mixed $value Value to validate
	 * @param array $rule Validation rule
	 * @param string $context Context for error messages
	 * @return string|WP_Error
	 */
	private static function validate_string( $value, $rule, $context ) {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return new WP_Error( 'invalid_string', sprintf( __( '%s must be a string.', 'wp-image-optimizer' ), $context ) );
		}

		$string_value = (string) $value;

		// Check allowed values
		if ( isset( $rule['allowed'] ) && ! in_array( $string_value, $rule['allowed'], true ) ) {
			return new WP_Error( 'invalid_value', sprintf( 
				__( '%s must be one of: %s', 'wp-image-optimizer' ), 
				$context, 
				implode( ', ', $rule['allowed'] ) 
			) );
		}

		return sanitize_text_field( $string_value );
	}

	/**
	 * Validate array value
	 *
	 * @param mixed $value Value to validate
	 * @param array $rule Validation rule
	 * @param string $context Context for error messages
	 * @return array|WP_Error
	 */
	private static function validate_array( $value, $rule, $context ) {
		if ( ! is_array( $value ) ) {
			return new WP_Error( 'invalid_array', sprintf( __( '%s must be an array.', 'wp-image-optimizer' ), $context ) );
		}

		$validated_array = array();

		// If we have children rules, validate each child
		if ( isset( $rule['children'] ) ) {
			foreach ( $rule['children'] as $child_key => $child_rule ) {
				if ( isset( $value[ $child_key ] ) ) {
					$child_context = $context ? $context . '.' . $child_key : $child_key;
					$validated_child = self::validate_value( $value[ $child_key ], $child_rule, $child_context );
					
					if ( is_wp_error( $validated_child ) ) {
						return $validated_child;
					}
					
					$validated_array[ $child_key ] = $validated_child;
				} elseif ( isset( $child_rule['default'] ) ) {
					$validated_array[ $child_key ] = $child_rule['default'];
				}
			}
		} else {
			// If we have allowed values, validate each array element
			if ( isset( $rule['allowed_values'] ) ) {
				foreach ( $value as $array_value ) {
					if ( ! in_array( $array_value, $rule['allowed_values'], true ) ) {
						return new WP_Error( 'invalid_array_value', sprintf( 
							__( '%s contains invalid value: %s', 'wp-image-optimizer' ), 
							$context, 
							$array_value 
						) );
					}
				}
			}
			
			$validated_array = array_map( 'sanitize_text_field', $value );
		}

		return $validated_array;
	}

	/**
	 * Get settings source information
	 *
	 * @return array Information about where each setting comes from
	 */
	public static function get_settings_sources() {
		$sources = array();
		$current_settings = self::get_settings();

		// Check each setting against different sources
		foreach ( $current_settings as $key => $value ) {
			$sources[ $key ] = self::determine_setting_source( $key, $value );
		}

		return $sources;
	}

	/**
	 * Determine the source of a specific setting
	 *
	 * @param string $key Setting key
	 * @param mixed  $value Current setting value
	 * @return string Source of the setting (default, config, cli, database)
	 */
	private static function determine_setting_source( $key, $value ) {
		// Check database (UI) settings first
		$db_settings = self::load_database_settings();
		if ( isset( $db_settings[ $key ] ) && $db_settings[ $key ] === $value ) {
			return 'database';
		}

		// Check CLI settings
		$cli_settings = self::load_cli_settings();
		if ( isset( $cli_settings[ $key ] ) && $cli_settings[ $key ] === $value ) {
			return 'cli';
		}

		// Check wp-config settings
		$config_settings = self::load_config_settings();
		if ( isset( $config_settings[ $key ] ) && $config_settings[ $key ] === $value ) {
			return 'config';
		}

		// Must be default
		return 'default';
	}

	/**
	 * Reset settings to defaults
	 *
	 * @param bool $clear_all Whether to clear all settings including CLI
	 * @return bool True on success, false on failure
	 */
	public static function reset_settings( $clear_all = false ) {
		if ( $clear_all ) {
			$result = delete_option( self::OPTION_NAME );
		} else {
			// Keep CLI settings but clear UI settings
			$option_data = get_option( self::OPTION_NAME, array() );
			unset( $option_data['settings'] );
			$result = update_option( self::OPTION_NAME, $option_data );
		}

		// Clear cache
		if ( $result ) {
			self::$cached_settings = null;
		}

		return $result;
	}

	/**
	 * Get default settings
	 *
	 * @return array Default settings
	 */
	public static function get_default_settings() {
		return self::$default_settings;
	}

	/**
	 * Get validation rules
	 *
	 * @return array Validation rules
	 */
	public static function get_validation_rules() {
		return self::$validation_rules;
	}

	/**
	 * Initialize error handler if not already initialized
	 */
	private static function init_error_handler() {
		if ( null === self::$error_handler && class_exists( 'WP_Image_Optimizer_Error_Handler' ) ) {
			self::$error_handler = WP_Image_Optimizer_Error_Handler::get_instance();
			self::$error_handler->set_error_context( array( 'component' => 'settings_manager' ) );
		}
	}

	/**
	 * Initialize database manager if not already initialized
	 */
	private static function init_database_manager() {
		if ( null === self::$database_manager && class_exists( 'WP_Image_Optimizer_Database_Manager' ) ) {
			self::$database_manager = WP_Image_Optimizer_Database_Manager::get_instance();
		}
	}

	/**
	 * Get settings with caching support
	 *
	 * @param bool $force_refresh Force refresh of cached settings
	 * @return array Complete settings array
	 */
	public static function get_cached_settings( $force_refresh = false ) {
		self::init_database_manager();
		
		if ( null === self::$database_manager ) {
			return self::get_settings( $force_refresh );
		}
		
		if ( $force_refresh ) {
			self::$database_manager->delete_cached_data( 'settings' );
		}
		
		return self::$database_manager->get_cached_data( 'settings', function() {
			return self::load_settings();
		}, 300 ); // Cache for 5 minutes
	}

	/**
	 * Clear settings cache
	 */
	public static function clear_settings_cache() {
		self::$cached_settings = null;
		
		self::init_database_manager();
		if ( null !== self::$database_manager ) {
			self::$database_manager->delete_cached_data( 'settings' );
		}
	}

	/**
	 * Update settings with cache invalidation
	 *
	 * @param array $new_settings Settings to update
	 * @param bool  $merge Whether to merge with existing settings
	 * @return bool True on success, false on failure
	 */
	public static function update_settings_with_cache( $new_settings, $merge = true ) {
		$result = self::update_settings( $new_settings, $merge );
		
		if ( $result ) {
			self::clear_settings_cache();
		}
		
		return $result;
	}
}