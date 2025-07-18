<?php
/**
 * Tests for error handling integration
 *
 * @package WP_Image_Optimizer
 */

class Test_Error_Handling_Integration extends WP_UnitTestCase {

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
		
		// Create error handler
		$this->error_handler = new WP_Image_Optimizer_Error_Handler();
		
		// Clear error logs
		$this->error_handler->clear_error_logs();
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		// Clear error logs
		$this->error_handler->clear_error_logs();
		
		parent::tearDown();
	}

	/**
	 * Test error logging
	 */
	public function test_error_logging() {
		// Log an error
		$error_code = 'test_error';
		$error_message = 'Test error message';
		$this->error_handler->log_error( $error_code, $error_message );
		
		// Get error logs
		$error_logs = $this->error_handler->get_error_logs();
		
		// Check if error is logged
		$this->assertIsArray( $error_logs );
		$this->assertNotEmpty( $error_logs );
		
		// Get the latest error
		$latest_error = end( $error_logs );
		$this->assertIsArray( $latest_error );
		$this->assertArrayHasKey( 'code', $latest_error );
		$this->assertArrayHasKey( 'message', $latest_error );
		$this->assertArrayHasKey( 'timestamp', $latest_error );
		$this->assertEquals( $error_code, $latest_error['code'] );
		$this->assertEquals( $error_message, $latest_error['message'] );
	}

	/**
	 * Test error logging with context
	 */
	public function test_error_logging_with_context() {
		// Log an error with context
		$error_code = 'test_error_context';
		$error_message = 'Test error message with context';
		$context = array(
			'file' => 'test-file.jpg',
			'format' => 'webp',
			'size' => 1024,
		);
		$this->error_handler->log_error( $error_code, $error_message, $context );
		
		// Get error logs
		$error_logs = $this->error_handler->get_error_logs();
		
		// Check if error is logged with context
		$this->assertIsArray( $error_logs );
		$this->assertNotEmpty( $error_logs );
		
		// Get the latest error
		$latest_error = end( $error_logs );
		$this->assertIsArray( $latest_error );
		$this->assertArrayHasKey( 'code', $latest_error );
		$this->assertArrayHasKey( 'message', $latest_error );
		$this->assertArrayHasKey( 'timestamp', $latest_error );
		$this->assertArrayHasKey( 'context', $latest_error );
		$this->assertEquals( $error_code, $latest_error['code'] );
		$this->assertEquals( $error_message, $latest_error['message'] );
		$this->assertEquals( $context, $latest_error['context'] );
	}

	/**
	 * Test error limit
	 */
	public function test_error_limit() {
		// Get max error count
		$reflection = new ReflectionClass( $this->error_handler );
		$property = $reflection->getProperty( 'max_error_count' );
		$property->setAccessible( true );
		$max_error_count = $property->getValue( $this->error_handler );
		
		// Log more errors than the limit
		for ( $i = 0; $i < $max_error_count + 5; $i++ ) {
			$this->error_handler->log_error( "test_error_$i", "Test error message $i" );
		}
		
		// Get error logs
		$error_logs = $this->error_handler->get_error_logs();
		
		// Check if error count is limited
		$this->assertIsArray( $error_logs );
		$this->assertCount( $max_error_count, $error_logs );
		
		// Check if oldest errors are removed
		$first_error = reset( $error_logs );
		$this->assertEquals( "test_error_5", $first_error['code'] );
	}

	/**
	 * Test error clearing
	 */
	public function test_error_clearing() {
		// Log some errors
		$this->error_handler->log_error( 'test_error_1', 'Test error message 1' );
		$this->error_handler->log_error( 'test_error_2', 'Test error message 2' );
		
		// Clear error logs
		$this->error_handler->clear_error_logs();
		
		// Get error logs
		$error_logs = $this->error_handler->get_error_logs();
		
		// Check if error logs are cleared
		$this->assertIsArray( $error_logs );
		$this->assertEmpty( $error_logs );
	}

	/**
	 * Test error handling in image converter
	 */
	public function test_error_handling_in_image_converter() {
		// Create image converter with error handler
		$image_converter = new WP_Image_Optimizer_Image_Converter( null, $this->error_handler );
		
		// Attempt to convert non-existent file
		$result = $image_converter->convert_image( '/non-existent-file.jpg' );
		
		// Check if result is WP_Error
		$this->assertWPError( $result );
		
		// Get error logs
		$error_logs = $this->error_handler->get_error_logs();
		
		// Check if error is logged
		$this->assertIsArray( $error_logs );
		$this->assertNotEmpty( $error_logs );
		
		// Get the latest error
		$latest_error = end( $error_logs );
		$this->assertIsArray( $latest_error );
		$this->assertArrayHasKey( 'code', $latest_error );
		$this->assertEquals( 'file_not_found', $latest_error['code'] );
	}

	/**
	 * Test error handling in file handler
	 */
	public function test_error_handling_in_file_handler() {
		// Create file handler with error handler
		$file_handler = new WP_Image_Optimizer_File_Handler( $this->error_handler );
		
		// Attempt to check non-existent file
		$result = $file_handler->file_exists_and_readable( '/non-existent-file.jpg' );
		
		// Check if result is WP_Error
		$this->assertWPError( $result );
		
		// Get error logs
		$error_logs = $this->error_handler->get_error_logs();
		
		// Check if error is logged
		$this->assertIsArray( $error_logs );
		$this->assertNotEmpty( $error_logs );
		
		// Get the latest error
		$latest_error = end( $error_logs );
		$this->assertIsArray( $latest_error );
		$this->assertArrayHasKey( 'code', $latest_error );
		$this->assertEquals( 'file_not_found', $latest_error['code'] );
	}

	/**
	 * Test error handling in converter factory
	 */
	public function test_error_handling_in_converter_factory() {
		// Set error handler in converter factory
		$reflection = new ReflectionClass( 'Converter_Factory' );
		$property = $reflection->getProperty( 'error_handler' );
		$property->setAccessible( true );
		$property->setValue( $this->error_handler );
		
		// Attempt to get non-existent converter
		$converter = Converter_Factory::get_converter_by_name( 'NonExistentConverter' );
		
		// Check if converter is null
		$this->assertNull( $converter );
		
		// Get error logs
		$error_logs = $this->error_handler->get_error_logs();
		
		// Check if error is logged
		$this->assertIsArray( $error_logs );
		$this->assertNotEmpty( $error_logs );
		
		// Get the latest error
		$latest_error = end( $error_logs );
		$this->assertIsArray( $latest_error );
		$this->assertArrayHasKey( 'code', $latest_error );
		$this->assertEquals( 'converter_not_found', $latest_error['code'] );
	}

	/**
	 * Test error handling in batch processor
	 */
	public function test_error_handling_in_batch_processor() {
		// Create batch processor with error handler
		$batch_processor = new WP_Image_Optimizer_Batch_Processor( $this->error_handler );
		
		// Attempt to process non-existent attachment
		$result = $batch_processor->process_attachment( 999999 );
		
		// Check if result is false
		$this->assertFalse( $result );
		
		// Get error logs
		$error_logs = $this->error_handler->get_error_logs();
		
		// Check if error is logged
		$this->assertIsArray( $error_logs );
		$this->assertNotEmpty( $error_logs );
		
		// Get the latest error
		$latest_error = end( $error_logs );
		$this->assertIsArray( $latest_error );
		$this->assertArrayHasKey( 'code', $latest_error );
		$this->assertEquals( 'invalid_attachment', $latest_error['code'] );
	}

	/**
	 * Test error handling in settings manager
	 */
	public function test_error_handling_in_settings_manager() {
		// Set error handler in settings manager
		$reflection = new ReflectionClass( 'WP_Image_Optimizer_Settings_Manager' );
		$property = $reflection->getProperty( 'error_handler' );
		$property->setAccessible( true );
		$property->setValue( $this->error_handler );
		
		// Attempt to update settings with invalid value
		$result = WP_Image_Optimizer_Settings_Manager::update_settings( array(
			'formats' => 'invalid', // Should be an array
		) );
		
		// Check if result is false
		$this->assertFalse( $result );
		
		// Get error logs
		$error_logs = $this->error_handler->get_error_logs();
		
		// Check if error is logged
		$this->assertIsArray( $error_logs );
		$this->assertNotEmpty( $error_logs );
		
		// Get the latest error
		$latest_error = end( $error_logs );
		$this->assertIsArray( $latest_error );
		$this->assertArrayHasKey( 'code', $latest_error );
		$this->assertEquals( 'invalid_settings', $latest_error['code'] );
	}

	/**
	 * Test error statistics
	 */
	public function test_error_statistics() {
		// Log some errors
		$this->error_handler->log_error( 'error_type_1', 'Error message 1' );
		$this->error_handler->log_error( 'error_type_1', 'Error message 2' );
		$this->error_handler->log_error( 'error_type_2', 'Error message 3' );
		
		// Get error statistics
		$stats = $this->error_handler->get_error_statistics();
		
		// Check statistics
		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'total_errors', $stats );
		$this->assertArrayHasKey( 'error_types', $stats );
		$this->assertEquals( 3, $stats['total_errors'] );
		$this->assertIsArray( $stats['error_types'] );
		$this->assertArrayHasKey( 'error_type_1', $stats['error_types'] );
		$this->assertArrayHasKey( 'error_type_2', $stats['error_types'] );
		$this->assertEquals( 2, $stats['error_types']['error_type_1'] );
		$this->assertEquals( 1, $stats['error_types']['error_type_2'] );
	}

	/**
	 * Test error notification
	 */
	public function test_error_notification() {
		// Enable error notifications
		$this->error_handler->enable_notifications( true );
		
		// Log a critical error
		$this->error_handler->log_error( 'critical_error', 'Critical error message', array(), true );
		
		// Check if notification is triggered
		$notifications = $this->error_handler->get_pending_notifications();
		
		// Check notifications
		$this->assertIsArray( $notifications );
		$this->assertNotEmpty( $notifications );
		$this->assertArrayHasKey( 'critical_error', $notifications );
	}
}