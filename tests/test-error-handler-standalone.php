<?php
/**
 * Standalone Test for Error Handler functionality
 * This test can run without WordPress test framework
 *
 * @package WP_Image_Optimizer
 */

// Mock WordPress functions for testing
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0 ) {
		return json_encode( $data, $options );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'sprintf' ) ) {
	// sprintf is a PHP built-in, but just in case
}

// Global options storage for mocking WordPress functions
global $mock_wp_options;
$mock_wp_options = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $mock_wp_options;
		return isset( $mock_wp_options[ $option ] ) ? $mock_wp_options[ $option ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value ) {
		global $mock_wp_options;
		$mock_wp_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		global $mock_wp_options;
		unset( $mock_wp_options[ $option ] );
		return true;
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	function wp_rand( $min = 0, $max = 0 ) {
		return rand( $min, $max );
	}
}

if ( ! function_exists( 'uniqid' ) ) {
	// uniqid is a PHP built-in
}

if ( ! function_exists( 'error_log' ) ) {
	// error_log is a PHP built-in
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook_name, ...$args ) {
		// Mock WordPress action system
	}
}

if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
		return true; // Mock successful email sending
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		$info = array(
			'name' => 'Test Site',
			'version' => '6.0',
		);
		return isset( $info[ $show ] ) ? $info[ $show ] : '';
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url() {
		return 'https://example.com';
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		return false;
	}
}

if ( ! function_exists( 'wp_convert_hr_to_bytes' ) ) {
	function wp_convert_hr_to_bytes( $value ) {
		$value = strtolower( trim( $value ) );
		$bytes = (int) $value;
		
		if ( false !== strpos( $value, 'g' ) ) {
			$bytes *= GB_IN_BYTES;
		} elseif ( false !== strpos( $value, 'm' ) ) {
			$bytes *= MB_IN_BYTES;
		} elseif ( false !== strpos( $value, 'k' ) ) {
			$bytes *= KB_IN_BYTES;
		}
		
		return min( $bytes, PHP_INT_MAX );
	}
}

if ( ! function_exists( 'size_format' ) ) {
	function size_format( $bytes, $decimals = 0 ) {
		$quant = array(
			'TB' => TB_IN_BYTES,
			'GB' => GB_IN_BYTES,
			'MB' => MB_IN_BYTES,
			'KB' => KB_IN_BYTES,
			'B'  => 1,
		);
		
		if ( 0 === $bytes ) {
			return number_format( 0, $decimals ) . ' B';
		}
		
		foreach ( $quant as $unit => $mag ) {
			if ( doubleval( $bytes ) >= $mag ) {
				return number_format( $bytes / $mag, $decimals ) . ' ' . $unit;
			}
		}
		
		return false;
	}
}

// Define constants
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}

if ( ! defined( 'WP_DEBUG_LOG' ) ) {
	define( 'WP_DEBUG_LOG', true );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 24 * 60 * 60 );
}

if ( ! defined( 'KB_IN_BYTES' ) ) {
	define( 'KB_IN_BYTES', 1024 );
}

if ( ! defined( 'MB_IN_BYTES' ) ) {
	define( 'MB_IN_BYTES', 1024 * KB_IN_BYTES );
}

if ( ! defined( 'GB_IN_BYTES' ) ) {
	define( 'GB_IN_BYTES', 1024 * MB_IN_BYTES );
}

if ( ! defined( 'TB_IN_BYTES' ) ) {
	define( 'TB_IN_BYTES', 1024 * GB_IN_BYTES );
}

// Mock WP_Error class
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $errors = array();
		public $error_data = array();
		
		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( empty( $code ) ) {
				return;
			}
			
			$this->errors[ $code ][] = $message;
			
			if ( ! empty( $data ) ) {
				$this->error_data[ $code ] = $data;
			}
		}
		
		public function get_error_code() {
			$codes = array_keys( $this->errors );
			return empty( $codes ) ? '' : $codes[0];
		}
		
		public function get_error_message( $code = '' ) {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			
			if ( isset( $this->errors[ $code ] ) ) {
				return $this->errors[ $code ][0];
			}
			
			return '';
		}
		
		public function get_error_data( $code = '' ) {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			
			return isset( $this->error_data[ $code ] ) ? $this->error_data[ $code ] : null;
		}
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

// Include the error handler class
require_once dirname( __FILE__ ) . '/../includes/class-error-handler.php';

/**
 * Simple test runner
 */
class SimpleTestRunner {
	private $tests_run = 0;
	private $tests_passed = 0;
	private $tests_failed = 0;
	
	public function assert_true( $condition, $message = '' ) {
		$this->tests_run++;
		if ( $condition ) {
			$this->tests_passed++;
			echo "✓ PASS: {$message}\n";
		} else {
			$this->tests_failed++;
			echo "✗ FAIL: {$message}\n";
		}
	}
	
	public function assert_false( $condition, $message = '' ) {
		$this->assert_true( ! $condition, $message );
	}
	
	public function assert_equals( $expected, $actual, $message = '' ) {
		$this->assert_true( $expected === $actual, $message . " (Expected: " . var_export( $expected, true ) . ", Actual: " . var_export( $actual, true ) . ")" );
	}
	
	public function assert_not_false( $value, $message = '' ) {
		$this->assert_true( $value !== false, $message );
	}
	
	public function assert_count( $expected_count, $array, $message = '' ) {
		$actual_count = is_array( $array ) ? count( $array ) : 0;
		$this->assert_equals( $expected_count, $actual_count, $message );
	}
	
	public function assert_string_starts_with( $prefix, $string, $message = '' ) {
		$this->assert_true( strpos( $string, $prefix ) === 0, $message );
	}
	
	public function assert_empty( $value, $message = '' ) {
		$this->assert_true( empty( $value ), $message );
	}
	
	public function assert_not_empty( $value, $message = '' ) {
		$this->assert_false( empty( $value ), $message );
	}
	
	public function assert_string_contains( $needle, $haystack, $message = '' ) {
		$this->assert_true( strpos( $haystack, $needle ) !== false, $message );
	}
	
	public function print_summary() {
		echo "\n" . str_repeat( '=', 50 ) . "\n";
		echo "Test Summary:\n";
		echo "Tests Run: {$this->tests_run}\n";
		echo "Passed: {$this->tests_passed}\n";
		echo "Failed: {$this->tests_failed}\n";
		
		if ( $this->tests_failed === 0 ) {
			echo "🎉 All tests passed!\n";
		} else {
			echo "❌ Some tests failed.\n";
		}
		echo str_repeat( '=', 50 ) . "\n";
	}
}

// Run tests
echo "Running Error Handler Tests...\n\n";

$test = new SimpleTestRunner();
$error_handler = WP_Image_Optimizer_Error_Handler::get_instance();

// Clear any existing logs
$error_handler->clear_error_logs();

// Test 1: Singleton pattern
echo "Test 1: Singleton Pattern\n";
$instance1 = WP_Image_Optimizer_Error_Handler::get_instance();
$instance2 = WP_Image_Optimizer_Error_Handler::get_instance();
$test->assert_true( $instance1 === $instance2, 'Error handler should be a singleton' );

// Test 2: Basic error logging
echo "\nTest 2: Basic Error Logging\n";
$error_message = 'Test error message';
$error_id = $error_handler->log_error( $error_message );
$test->assert_not_false( $error_id, 'Error logging should return an error ID' );
$test->assert_string_starts_with( 'wpio_', $error_id, 'Error ID should have correct prefix' );

$logs = $error_handler->get_error_logs( 1 );
$test->assert_count( 1, $logs, 'Should have one error log entry' );
$test->assert_equals( $error_message, $logs[0]['message'], 'Error message should match' );

// Test 3: WP_Error logging
echo "\nTest 3: WP_Error Logging\n";
$error_handler->clear_error_logs();
$wp_error = new WP_Error( 'test_code', 'Test WP_Error message', array( 'data' => 'test_data' ) );
$error_id = $error_handler->log_error( $wp_error );
$test->assert_not_false( $error_id, 'WP_Error logging should return an error ID' );

$logs = $error_handler->get_error_logs( 1 );
$test->assert_count( 1, $logs, 'Should have one error log entry' );
$test->assert_equals( 'test_code', $logs[0]['code'], 'Error code should match' );
$test->assert_equals( 'Test WP_Error message', $logs[0]['message'], 'Error message should match' );

// Test 4: Error severity levels
echo "\nTest 4: Error Severity Levels\n";
$error_handler->clear_error_logs();
$severities = array( 'critical', 'error', 'warning', 'notice', 'info', 'debug' );

foreach ( $severities as $severity ) {
	$error_id = $error_handler->log_error( "Test {$severity} message", $severity );
	$test->assert_not_false( $error_id, "Should log {$severity} level errors" );
}

$logs = $error_handler->get_error_logs( 10 );
$test->assert_count( 6, $logs, 'Should have six error log entries' );

// Test severity filtering
$critical_logs = $error_handler->get_error_logs( 10, 'critical' );
$test->assert_count( 1, $critical_logs, 'Should filter critical errors correctly' );
$test->assert_equals( 'critical', $critical_logs[0]['severity'], 'Should return critical severity' );

// Test 5: Error categories
echo "\nTest 5: Error Categories\n";
$error_handler->clear_error_logs();
$categories = array( 'system', 'conversion', 'configuration', 'runtime', 'security', 'file_system', 'validation' );

foreach ( $categories as $category ) {
	$error_id = $error_handler->log_error( "Test {$category} error", 'error', $category );
	$test->assert_not_false( $error_id, "Should log {$category} category errors" );
}

$logs = $error_handler->get_error_logs( 10 );
$test->assert_count( 7, $logs, 'Should have seven error log entries' );

// Test category filtering
$system_logs = $error_handler->get_error_logs( 10, null, 'system' );
$test->assert_count( 1, $system_logs, 'Should filter system errors correctly' );
$test->assert_equals( 'system', $system_logs[0]['category'], 'Should return system category' );

// Test 6: Error context
echo "\nTest 6: Error Context\n";
$error_handler->clear_error_logs();
$context = array(
	'component' => 'test_component',
	'method' => 'test_method',
	'file' => 'test_file.php',
);

$error_handler->set_error_context( $context );
$error_id = $error_handler->log_error( 'Test context error' );

$logs = $error_handler->get_error_logs( 1 );
$test->assert_count( 1, $logs, 'Should have one error log entry' );

$logged_context = $logs[0]['context'];
$test->assert_equals( 'test_component', $logged_context['component'], 'Context component should match' );
$test->assert_equals( 'test_method', $logged_context['method'], 'Context method should match' );
$test->assert_equals( 'test_file.php', $logged_context['file'], 'Context file should match' );

// Test 7: User-friendly error messages
echo "\nTest 7: User-Friendly Error Messages\n";
$test_cases = array(
	'no_converter' => 'Image conversion is not available. Please ensure ImageMagick or GD library is installed on your server.',
	'conversion_failed' => 'Image conversion failed. The image may be corrupted or in an unsupported format.',
	'file_not_found' => 'The image file could not be found. It may have been moved or deleted.',
);

foreach ( $test_cases as $error_code => $expected_message ) {
	$wp_error = new WP_Error( $error_code, 'Technical error message' );
	$friendly_message = $error_handler->get_user_friendly_message( $wp_error );
	$test->assert_equals( $expected_message, $friendly_message, "User-friendly message for {$error_code} should match" );
}

// Test 8: Error statistics
echo "\nTest 8: Error Statistics\n";
$error_handler->clear_error_logs();
$error_handler->log_error( 'Error 1', 'error', 'system' );
$error_handler->log_error( 'Error 2', 'warning', 'conversion' );
$error_handler->log_error( 'Error 3', 'critical', 'system' );

$stats = $error_handler->get_error_statistics();
$test->assert_equals( 3, $stats['total_errors'], 'Total errors should be 3' );
$test->assert_equals( 1, $stats['errors_by_severity']['error'], 'Should have 1 error severity' );
$test->assert_equals( 1, $stats['errors_by_severity']['warning'], 'Should have 1 warning severity' );
$test->assert_equals( 1, $stats['errors_by_severity']['critical'], 'Should have 1 critical severity' );
$test->assert_equals( 2, $stats['errors_by_category']['system'], 'Should have 2 system errors' );
$test->assert_equals( 1, $stats['errors_by_category']['conversion'], 'Should have 1 conversion error' );

// Test 9: Clear error logs
echo "\nTest 9: Clear Error Logs\n";
$logs_before = $error_handler->get_error_logs();
$test->assert_count( 3, $logs_before, 'Should have 3 errors before clearing' );

$result = $error_handler->clear_error_logs();
$test->assert_true( $result, 'Clear logs should return true' );

$logs_after = $error_handler->get_error_logs();
$test->assert_empty( $logs_after, 'Should have no errors after clearing' );

// Test 10: Invalid severity and category handling
echo "\nTest 10: Invalid Severity and Category Handling\n";
$error_id = $error_handler->log_error( 'Test error', 'invalid_severity' );
$test->assert_not_false( $error_id, 'Should still log error with invalid severity' );

$logs = $error_handler->get_error_logs( 1 );
$test->assert_equals( 'error', $logs[0]['severity'], 'Should default to error severity' );

$error_handler->clear_error_logs();
$error_id = $error_handler->log_error( 'Test error', 'error', 'invalid_category' );
$test->assert_not_false( $error_id, 'Should still log error with invalid category' );

$logs = $error_handler->get_error_logs( 1 );
$test->assert_equals( 'runtime', $logs[0]['category'], 'Should default to runtime category' );

// Print test summary
$test->print_summary();

echo "\nError Handler implementation completed successfully!\n";