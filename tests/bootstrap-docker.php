<?php
/**
 * PHPUnit bootstrap file for Docker environment
 *
 * @package WP_Image_Optimizer
 */

// Set up Docker-specific environment variables
if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', '/tmp/wp-tests-config.php' );
}

// Set WordPress core directory
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/var/www/html/' );
}

// Set up test database configuration for Docker
if ( ! defined( 'DB_NAME' ) ) {
	define( 'DB_NAME', getenv( 'WP_TESTS_DB_NAME' ) ?: 'wordpress_test' );
}
if ( ! defined( 'DB_USER' ) ) {
	define( 'DB_USER', getenv( 'WP_TESTS_DB_USER' ) ?: 'wordpress' );
}
if ( ! defined( 'DB_PASSWORD' ) ) {
	define( 'DB_PASSWORD', getenv( 'WP_TESTS_DB_PASSWORD' ) ?: 'wordpress' );
}
if ( ! defined( 'DB_HOST' ) ) {
	define( 'DB_HOST', getenv( 'WP_TESTS_DB_HOST' ) ?: 'db' );
}

// Set up WordPress debug configuration for testing
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}
if ( ! defined( 'WP_DEBUG_LOG' ) ) {
	define( 'WP_DEBUG_LOG', true );
}
if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
	define( 'WP_DEBUG_DISPLAY', false );
}

// Set up test-specific constants
if ( ! defined( 'WP_TESTS_DOMAIN' ) ) {
	define( 'WP_TESTS_DOMAIN', 'localhost' );
}
if ( ! defined( 'WP_TESTS_EMAIL' ) ) {
	define( 'WP_TESTS_EMAIL', 'admin@example.org' );
}
if ( ! defined( 'WP_TESTS_TITLE' ) ) {
	define( 'WP_TESTS_TITLE', 'Test Blog' );
}

// Set up Docker-specific paths
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Ensure WordPress test library is available
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "WordPress test library not found at: $_tests_dir\n";
	echo "Please ensure the WordPress test suite is properly installed.\n";
	exit( 1 );
}

// Load the regular bootstrap file
require_once dirname( __FILE__ ) . '/bootstrap.php';

/**
 * Docker-specific test helper functions
 */

/**
 * Wait for database connection to be ready
 *
 * @param int $max_attempts Maximum number of connection attempts
 * @param int $wait_seconds Seconds to wait between attempts
 * @return bool True if connection successful, false otherwise
 */
function wp_image_optimizer_wait_for_db( $max_attempts = 30, $wait_seconds = 1 ) {
	$attempts = 0;
	
	while ( $attempts < $max_attempts ) {
		try {
			$connection = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
			
			if ( ! $connection->connect_error ) {
				$connection->close();
				return true;
			}
		} catch ( Exception $e ) {
			// Connection failed, continue trying
		}
		
		$attempts++;
		sleep( $wait_seconds );
	}
	
	return false;
}

/**
 * Set up Docker-specific test environment
 */
function wp_image_optimizer_setup_docker_test_env() {
	// Wait for database to be ready
	if ( ! wp_image_optimizer_wait_for_db() ) {
		echo "Failed to connect to test database after multiple attempts.\n";
		echo "Database Host: " . DB_HOST . "\n";
		echo "Database Name: " . DB_NAME . "\n";
		echo "Database User: " . DB_USER . "\n";
		exit( 1 );
	}
	
	// Create test uploads directory
	$upload_dir = '/var/www/html/wp-content/uploads/test-images';
	if ( ! is_dir( $upload_dir ) ) {
		wp_mkdir_p( $upload_dir );
	}
	
	// Set proper permissions for test directories
	if ( is_dir( $upload_dir ) ) {
		chmod( $upload_dir, 0755 );
	}
	
	// Create test log directory
	$log_dir = '/var/www/html/wp-content/debug.log';
	if ( ! file_exists( dirname( $log_dir ) ) ) {
		wp_mkdir_p( dirname( $log_dir ) );
	}
}

/**
 * Clean up Docker test environment
 */
function wp_image_optimizer_cleanup_docker_test_env() {
	// Clean up test images
	$upload_dir = '/var/www/html/wp-content/uploads/test-images';
	if ( is_dir( $upload_dir ) ) {
		$files = glob( $upload_dir . '/*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
	}
	
	// Clean up temporary files
	$temp_files = glob( '/tmp/wp-image-optimizer-test-*' );
	foreach ( $temp_files as $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}
}

/**
 * Get Docker container information for debugging
 *
 * @return array Container information
 */
function wp_image_optimizer_get_docker_info() {
	return array(
		'php_version'     => PHP_VERSION,
		'wordpress_path'  => ABSPATH,
		'plugin_path'     => dirname( dirname( __FILE__ ) ),
		'db_host'         => DB_HOST,
		'db_name'         => DB_NAME,
		'db_user'         => DB_USER,
		'tests_dir'       => $_tests_dir ?? '/tmp/wordpress-tests-lib',
		'upload_dir'      => '/var/www/html/wp-content/uploads',
		'memory_limit'    => ini_get( 'memory_limit' ),
		'max_execution'   => ini_get( 'max_execution_time' ),
		'extensions'      => array(
			'gd'       => extension_loaded( 'gd' ),
			'imagick'  => extension_loaded( 'imagick' ),
			'mysqli'   => extension_loaded( 'mysqli' ),
			'xdebug'   => extension_loaded( 'xdebug' ),
		),
	);
}

// Set up Docker test environment
wp_image_optimizer_setup_docker_test_env();

// Register cleanup function
register_shutdown_function( 'wp_image_optimizer_cleanup_docker_test_env' );

// Output Docker environment info for debugging
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	$docker_info = wp_image_optimizer_get_docker_info();
	error_log( 'Docker Test Environment Info: ' . print_r( $docker_info, true ) );
}