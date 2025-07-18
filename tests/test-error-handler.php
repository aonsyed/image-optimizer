<?php
/**
 * Test Error Handler functionality
 *
 * @package WP_Image_Optimizer
 */

// Include the error handler class
require_once dirname( __FILE__ ) . '/../includes/class-error-handler.php';

/**
 * Test Error Handler class
 */
class Test_WP_Image_Optimizer_Error_Handler extends WP_UnitTestCase {

	/**
	 * Error handler instance
	 *
	 * @var WP_Image_Optimizer_Error_Handler
	 */
	private $error_handler;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		$this->error_handler = WP_Image_Optimizer_Error_Handler::get_instance();
		
		// Clear any existing error logs
		$this->error_handler->clear_error_logs();
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		// Clear error logs after each test
		$this->error_handler->clear_error_logs();
		parent::tearDown();
	}

	/**
	 * Test singleton pattern
	 */
	public function test_singleton_pattern() {
		$instance1 = WP_Image_Optimizer_Error_Handler::get_instance();
		$instance2 = WP_Image_Optimizer_Error_Handler::get_instance();
		
		$this->assertSame( $instance1, $instance2, 'Error handler should be a singleton' );
	}

	/**
	 * Test basic error logging
	 */
	public function test_basic_error_logging() {
		$error_message = 'Test error message';
		$error_id = $this->error_handler->log_error( $error_message );
		
		$this->assertNotFalse( $error_id, 'Error logging should return an error ID' );
		$this->assertStringStartsWith( 'wpio_', $error_id, 'Error ID should have correct prefix' );
		
		// Check if error was logged
		$logs = $this->error_handler->get_error_logs( 1 );
		$this->assertCount( 1, $logs, 'Should have one error log entry' );
		$this->assertEquals( $error_message, $logs[0]['message'], 'Error message should match' );
	}

	/**
	 * Test WP_Error logging
	 */
	public function test_wp_error_logging() {
		$wp_error = new WP_Error( 'test_code', 'Test WP_Error message', array( 'data' => 'test_data' ) );
		$error_id = $this->error_handler->log_error( $wp_error );
		
		$this->assertNotFalse( $error_id, 'WP_Error logging should return an error ID' );
		
		$logs = $this->error_handler->get_error_logs( 1 );
		$this->assertCount( 1, $logs, 'Should have one error log entry' );
		$this->assertEquals( 'test_code', $logs[0]['code'], 'Error code should match' );
		$this->assertEquals( 'Test WP_Error message', $logs[0]['message'], 'Error message should match' );
		$this->assertEquals( array( 'data' => 'test_data' ), $logs[0]['data'], 'Error data should match' );
	}

	/**
	 * Test error severity levels
	 */
	public function test_error_severity_levels() {
		$severities = array( 'critical', 'error', 'warning', 'notice', 'info', 'debug' );
		
		foreach ( $severities as $severity ) {
			$error_id = $this->error_handler->log_error( "Test {$severity} message", $severity );
			$this->assertNotFalse( $error_id, "Should log {$severity} level errors" );
		}
		
		$logs = $this->error_handler->get_error_logs( 10 );
		$this->assertCount( 6, $logs, 'Should have six error log entries' );
		
		// Test severity filtering
		$critical_logs = $this->error_handler->get_error_logs( 10, 'critical' );
		$this->assertCount( 1, $critical_logs, 'Should filter critical errors correctly' );
		$this->assertEquals( 'critical', $critical_logs[0]['severity'], 'Should return critical severity' );
	}

	/**
	 * Test error categories
	 */
	public function test_error_categories() {
		$categories = array( 'system', 'conversion', 'configuration', 'runtime', 'security', 'file_system', 'validation' );
		
		foreach ( $categories as $category ) {
			$error_id = $this->error_handler->log_error( "Test {$category} error", 'error', $category );
			$this->assertNotFalse( $error_id, "Should log {$category} category errors" );
		}
		
		$logs = $this->error_handler->get_error_logs( 10 );
		$this->assertCount( 7, $logs, 'Should have seven error log entries' );
		
		// Test category filtering
		$system_logs = $this->error_handler->get_error_logs( 10, null, 'system' );
		$this->assertCount( 1, $system_logs, 'Should filter system errors correctly' );
		$this->assertEquals( 'system', $system_logs[0]['category'], 'Should return system category' );
	}

	/**
	 * Test error context
	 */
	public function test_error_context() {
		$context = array(
			'component' => 'test_component',
			'method' => 'test_method',
			'file' => 'test_file.php',
		);
		
		$this->error_handler->set_error_context( $context );
		$error_id = $this->error_handler->log_error( 'Test context error' );
		
		$logs = $this->error_handler->get_error_logs( 1 );
		$this->assertCount( 1, $logs, 'Should have one error log entry' );
		
		$logged_context = $logs[0]['context'];
		$this->assertEquals( 'test_component', $logged_context['component'], 'Context component should match' );
		$this->assertEquals( 'test_method', $logged_context['method'], 'Context method should match' );
		$this->assertEquals( 'test_file.php', $logged_context['file'], 'Context file should match' );
	}

	/**
	 * Test adding to error context
	 */
	public function test_add_error_context() {
		$this->error_handler->set_error_context( array( 'component' => 'test' ) );
		$this->error_handler->add_error_context( 'method', 'test_method' );
		$this->error_handler->add_error_context( 'line', 123 );
		
		$error_id = $this->error_handler->log_error( 'Test add context error' );
		
		$logs = $this->error_handler->get_error_logs( 1 );
		$logged_context = $logs[0]['context'];
		
		$this->assertEquals( 'test', $logged_context['component'], 'Original context should be preserved' );
		$this->assertEquals( 'test_method', $logged_context['method'], 'Added method context should be present' );
		$this->assertEquals( 123, $logged_context['line'], 'Added line context should be present' );
	}

	/**
	 * Test clearing error context
	 */
	public function test_clear_error_context() {
		$this->error_handler->set_error_context( array( 'component' => 'test' ) );
		$this->error_handler->clear_error_context();
		
		$error_id = $this->error_handler->log_error( 'Test clear context error' );
		
		$logs = $this->error_handler->get_error_logs( 1 );
		$logged_context = $logs[0]['context'];
		
		$this->assertEmpty( $logged_context, 'Context should be empty after clearing' );
	}

	/**
	 * Test conversion error logging
	 */
	public function test_conversion_error_logging() {
		$error = new WP_Error( 'conversion_failed', 'Conversion failed message' );
		$original_file = '/path/to/image.jpg';
		$target_format = 'webp';
		$conversion_context = array( 'converter' => 'ImageMagick', 'quality' => 80 );
		
		$error_id = $this->error_handler->log_conversion_error( 
			$error, 
			$original_file, 
			$target_format, 
			$conversion_context 
		);
		
		$this->assertNotFalse( $error_id, 'Conversion error logging should return an error ID' );
		
		$logs = $this->error_handler->get_error_logs( 1 );
		$this->assertCount( 1, $logs, 'Should have one error log entry' );
		$this->assertEquals( 'conversion', $logs[0]['category'], 'Should be categorized as conversion error' );
		$this->assertEquals( $original_file, $logs[0]['context']['original_file'], 'Original file should be in context' );
		$this->assertEquals( $target_format, $logs[0]['context']['target_format'], 'Target format should be in context' );
		$this->assertEquals( 'ImageMagick', $logs[0]['context']['converter'], 'Converter should be in context' );
	}

	/**
	 * Test critical error logging
	 */
	public function test_critical_error_logging() {
		$error = new WP_Error( 'system_failure', 'Critical system failure' );
		$error_id = $this->error_handler->log_critical_error( $error, 'system' );
		
		$this->assertNotFalse( $error_id, 'Critical error logging should return an error ID' );
		
		$logs = $this->error_handler->get_error_logs( 1 );
		$this->assertCount( 1, $logs, 'Should have one error log entry' );
		$this->assertEquals( 'critical', $logs[0]['severity'], 'Should be critical severity' );
		$this->assertEquals( 'system', $logs[0]['category'], 'Should be system category' );
		$this->assertTrue( $logs[0]['user_friendly'], 'Should be marked as user friendly' );
	}

	/**
	 * Test error statistics
	 */
	public function test_error_statistics() {
		// Log various errors
		$this->error_handler->log_error( 'Error 1', 'error', 'system' );
		$this->error_handler->log_error( 'Error 2', 'warning', 'conversion' );
		$this->error_handler->log_error( 'Error 3', 'critical', 'system' );
		
		$stats = $this->error_handler->get_error_statistics();
		
		$this->assertEquals( 3, $stats['total_errors'], 'Total errors should be 3' );
		$this->assertEquals( 1, $stats['errors_by_severity']['error'], 'Should have 1 error severity' );
		$this->assertEquals( 1, $stats['errors_by_severity']['warning'], 'Should have 1 warning severity' );
		$this->assertEquals( 1, $stats['errors_by_severity']['critical'], 'Should have 1 critical severity' );
		$this->assertEquals( 2, $stats['errors_by_category']['system'], 'Should have 2 system errors' );
		$this->assertEquals( 1, $stats['errors_by_category']['conversion'], 'Should have 1 conversion error' );
	}

	/**
	 * Test clearing error logs
	 */
	public function test_clear_error_logs() {
		// Log some errors
		$this->error_handler->log_error( 'Error 1', 'error', 'system' );
		$this->error_handler->log_error( 'Error 2', 'warning', 'conversion' );
		
		$logs_before = $this->error_handler->get_error_logs();
		$this->assertCount( 2, $logs_before, 'Should have 2 errors before clearing' );
		
		// Clear all logs
		$result = $this->error_handler->clear_error_logs();
		$this->assertTrue( $result, 'Clear logs should return true' );
		
		$logs_after = $this->error_handler->get_error_logs();
		$this->assertEmpty( $logs_after, 'Should have no errors after clearing' );
	}

	/**
	 * Test clearing error logs by severity
	 */
	public function test_clear_error_logs_by_severity() {
		// Log errors with different severities
		$this->error_handler->log_error( 'Error 1', 'error', 'system' );
		$this->error_handler->log_error( 'Warning 1', 'warning', 'system' );
		$this->error_handler->log_error( 'Critical 1', 'critical', 'system' );
		
		// Clear only warning logs
		$result = $this->error_handler->clear_error_logs( 'warning' );
		$this->assertTrue( $result, 'Clear warning logs should return true' );
		
		$remaining_logs = $this->error_handler->get_error_logs();
		$this->assertCount( 2, $remaining_logs, 'Should have 2 remaining errors' );
		
		// Verify no warning logs remain
		foreach ( $remaining_logs as $log ) {
			$this->assertNotEquals( 'warning', $log['severity'], 'No warning logs should remain' );
		}
	}

	/**
	 * Test clearing error logs by category
	 */
	public function test_clear_error_logs_by_category() {
		// Log errors with different categories
		$this->error_handler->log_error( 'System Error', 'error', 'system' );
		$this->error_handler->log_error( 'Conversion Error', 'error', 'conversion' );
		$this->error_handler->log_error( 'Runtime Error', 'error', 'runtime' );
		
		// Clear only system logs
		$result = $this->error_handler->clear_error_logs( null, 'system' );
		$this->assertTrue( $result, 'Clear system logs should return true' );
		
		$remaining_logs = $this->error_handler->get_error_logs();
		$this->assertCount( 2, $remaining_logs, 'Should have 2 remaining errors' );
		
		// Verify no system logs remain
		foreach ( $remaining_logs as $log ) {
			$this->assertNotEquals( 'system', $log['category'], 'No system logs should remain' );
		}
	}

	/**
	 * Test user-friendly error messages
	 */
	public function test_user_friendly_messages() {
		$test_cases = array(
			'no_converter' => 'Image conversion is not available. Please ensure ImageMagick or GD library is installed on your server.',
			'conversion_failed' => 'Image conversion failed. The image may be corrupted or in an unsupported format.',
			'file_not_found' => 'The image file could not be found. It may have been moved or deleted.',
			'file_too_large' => 'The image file is too large to process. Please try with a smaller image.',
			'invalid_format' => 'The requested image format is not supported.',
			'permission_denied' => 'Permission denied. Please check file and directory permissions.',
		);
		
		foreach ( $test_cases as $error_code => $expected_message ) {
			$wp_error = new WP_Error( $error_code, 'Technical error message' );
			$friendly_message = $this->error_handler->get_user_friendly_message( $wp_error );
			
			$this->assertEquals( $expected_message, $friendly_message, "User-friendly message for {$error_code} should match" );
		}
	}

	/**
	 * Test generic user-friendly message
	 */
	public function test_generic_user_friendly_message() {
		$wp_error = new WP_Error( 'unknown_error_code', 'Some technical error' );
		$friendly_message = $this->error_handler->get_user_friendly_message( $wp_error, 'image conversion' );
		
		$this->assertStringContainsString( 'image conversion', $friendly_message, 'Should include context in generic message' );
		$this->assertStringContainsString( 'try again', $friendly_message, 'Should suggest trying again' );
	}

	/**
	 * Test error recovery callback registration
	 */
	public function test_recovery_callback_registration() {
		$callback_called = false;
		$recovery_callback = function( $error_entry ) use ( &$callback_called ) {
			$callback_called = true;
			return true; // Simulate successful recovery
		};
		
		$this->error_handler->register_recovery_callback( 'test_error', $recovery_callback );
		
		// Log an error that should trigger recovery
		$wp_error = new WP_Error( 'test_error', 'Test error for recovery' );
		$this->error_handler->log_error( $wp_error );
		
		$this->assertTrue( $callback_called, 'Recovery callback should have been called' );
	}

	/**
	 * Test maximum log entries limit
	 */
	public function test_max_log_entries_limit() {
		// Log more than the maximum number of entries
		$max_entries = 100; // From ERROR_HANDLER constant
		
		for ( $i = 0; $i < $max_entries + 10; $i++ ) {
			$this->error_handler->log_error( "Error {$i}" );
		}
		
		$logs = $this->error_handler->get_error_logs( $max_entries + 20 );
		$this->assertLessThanOrEqual( $max_entries, count( $logs ), 'Should not exceed maximum log entries' );
	}

	/**
	 * Test error log ordering
	 */
	public function test_error_log_ordering() {
		// Log errors with slight delays to ensure different timestamps
		$this->error_handler->log_error( 'First error' );
		sleep( 1 );
		$this->error_handler->log_error( 'Second error' );
		sleep( 1 );
		$this->error_handler->log_error( 'Third error' );
		
		$logs = $this->error_handler->get_error_logs( 3 );
		
		// Should be ordered newest first
		$this->assertEquals( 'Third error', $logs[0]['message'], 'Newest error should be first' );
		$this->assertEquals( 'Second error', $logs[1]['message'], 'Second newest should be second' );
		$this->assertEquals( 'First error', $logs[2]['message'], 'Oldest error should be last' );
	}

	/**
	 * Test invalid severity level handling
	 */
	public function test_invalid_severity_level() {
		$error_id = $this->error_handler->log_error( 'Test error', 'invalid_severity' );
		
		$this->assertNotFalse( $error_id, 'Should still log error with invalid severity' );
		
		$logs = $this->error_handler->get_error_logs( 1 );
		$this->assertEquals( 'error', $logs[0]['severity'], 'Should default to error severity' );
	}

	/**
	 * Test invalid category handling
	 */
	public function test_invalid_category() {
		$error_id = $this->error_handler->log_error( 'Test error', 'error', 'invalid_category' );
		
		$this->assertNotFalse( $error_id, 'Should still log error with invalid category' );
		
		$logs = $this->error_handler->get_error_logs( 1 );
		$this->assertEquals( 'runtime', $logs[0]['category'], 'Should default to runtime category' );
	}
}