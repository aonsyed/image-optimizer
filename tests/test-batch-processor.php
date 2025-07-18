<?php
/**
 * Test Batch Processor functionality
 *
 * @package WP_Image_Optimizer
 */

class Test_Batch_Processor extends WP_UnitTestCase {

	/**
	 * Batch processor instance
	 *
	 * @var WP_Image_Optimizer_Batch_Processor
	 */
	private $batch_processor;

	/**
	 * Test attachment IDs
	 *
	 * @var array
	 */
	private $test_attachments = array();

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Load required classes
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-settings-manager.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-error-handler.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-file-handler.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/interfaces/interface-converter.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/converters/class-converter-factory.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-image-converter.php';
		require_once WP_IMAGE_OPTIMIZER_PLUGIN_DIR . 'includes/class-batch-processor.php';
		
		$this->batch_processor = new WP_Image_Optimizer_Batch_Processor();
		
		// Create test attachments
		$this->create_test_attachments();
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		// Cancel any running batch
		$this->batch_processor->cancel_batch();
		
		// Clean up test attachments
		foreach ( $this->test_attachments as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
		
		// Clear options
		delete_option( 'wp_image_optimizer_batch_queue' );
		delete_option( 'wp_image_optimizer_batch_progress' );
		
		// Clear scheduled events
		wp_clear_scheduled_hook( 'wp_image_optimizer_batch_process' );
		
		parent::tearDown();
	}

	/**
	 * Create test attachments
	 */
	private function create_test_attachments() {
		// Create test image files
		$upload_dir = wp_upload_dir();
		$test_images = array(
			'test-image-1.jpg',
			'test-image-2.png',
			'test-image-3.gif',
		);

		foreach ( $test_images as $filename ) {
			$file_path = $upload_dir['path'] . '/' . $filename;
			
			// Create a simple test image (1x1 pixel)
			$image = imagecreate( 1, 1 );
			$white = imagecolorallocate( $image, 255, 255, 255 );
			
			$extension = pathinfo( $filename, PATHINFO_EXTENSION );
			switch ( $extension ) {
				case 'jpg':
				case 'jpeg':
					imagejpeg( $image, $file_path );
					break;
				case 'png':
					imagepng( $image, $file_path );
					break;
				case 'gif':
					imagegif( $image, $file_path );
					break;
			}
			
			imagedestroy( $image );
			
			// Create attachment
			$attachment_id = $this->factory->attachment->create_upload_object( $file_path );
			$this->test_attachments[] = $attachment_id;
		}
	}

	/**
	 * Test batch processor initialization
	 */
	public function test_batch_processor_initialization() {
		$this->assertInstanceOf( 'WP_Image_Optimizer_Batch_Processor', $this->batch_processor );
		$this->assertFalse( $this->batch_processor->is_batch_running() );
	}

	/**
	 * Test starting batch conversion
	 */
	public function test_start_batch_conversion() {
		$options = array(
			'format' => 'webp',
			'force' => false,
			'limit' => 2,
			'offset' => 0,
			'attachment_ids' => array(),
		);

		$result = $this->batch_processor->start_batch_conversion( $options );
		$this->assertTrue( $result );
		$this->assertTrue( $this->batch_processor->is_batch_running() );

		// Check that cron is scheduled
		$this->assertNotFalse( wp_next_scheduled( 'wp_image_optimizer_batch_process' ) );
	}

	/**
	 * Test starting batch conversion with specific attachment IDs
	 */
	public function test_start_batch_conversion_with_specific_ids() {
		$options = array(
			'format' => null,
			'force' => false,
			'limit' => 0,
			'offset' => 0,
			'attachment_ids' => array( $this->test_attachments[0], $this->test_attachments[1] ),
		);

		$result = $this->batch_processor->start_batch_conversion( $options );
		$this->assertTrue( $result );
		
		$progress = $this->batch_processor->get_batch_progress();
		$this->assertEquals( 2, $progress['total'] );
	}

	/**
	 * Test preventing multiple batch runs
	 */
	public function test_prevent_multiple_batch_runs() {
		// Start first batch
		$result1 = $this->batch_processor->start_batch_conversion();
		$this->assertTrue( $result1 );

		// Try to start second batch
		$result2 = $this->batch_processor->start_batch_conversion();
		$this->assertWPError( $result2 );
		$this->assertEquals( 'batch_already_running', $result2->get_error_code() );
	}

	/**
	 * Test batch progress tracking
	 */
	public function test_batch_progress_tracking() {
		$options = array(
			'attachment_ids' => array( $this->test_attachments[0] ),
		);

		$this->batch_processor->start_batch_conversion( $options );
		
		$progress = $this->batch_processor->get_batch_progress();
		
		$this->assertIsArray( $progress );
		$this->assertEquals( 'running', $progress['status'] );
		$this->assertEquals( 1, $progress['total'] );
		$this->assertEquals( 0, $progress['processed'] );
		$this->assertEquals( 0, $progress['successful'] );
		$this->assertEquals( 0, $progress['failed'] );
		$this->assertEquals( 0, $progress['skipped'] );
		$this->assertArrayHasKey( 'start_time', $progress );
		$this->assertArrayHasKey( 'percentage', $progress );
	}

	/**
	 * Test batch cancellation
	 */
	public function test_batch_cancellation() {
		// Start batch
		$this->batch_processor->start_batch_conversion();
		$this->assertTrue( $this->batch_processor->is_batch_running() );

		// Cancel batch
		$result = $this->batch_processor->cancel_batch();
		$this->assertTrue( $result );
		$this->assertFalse( $this->batch_processor->is_batch_running() );

		// Check progress status
		$progress = $this->batch_processor->get_batch_progress();
		$this->assertEquals( 'cancelled', $progress['status'] );
		$this->assertNotNull( $progress['end_time'] );

		// Check that cron is cleared
		$this->assertFalse( wp_next_scheduled( 'wp_image_optimizer_batch_process' ) );
	}

	/**
	 * Test batch processing with mock converter
	 */
	public function test_batch_processing_with_mock_converter() {
		// Mock the converter to avoid actual image processing
		$mock_converter = $this->createMock( 'Converter_Interface' );
		$mock_converter->method( 'is_available' )->willReturn( true );
		$mock_converter->method( 'get_supported_formats' )->willReturn( array( 'webp', 'avif' ) );
		$mock_converter->method( 'get_name' )->willReturn( 'Mock Converter' );
		$mock_converter->method( 'convert_to_webp' )->willReturn( true );
		$mock_converter->method( 'convert_to_avif' )->willReturn( true );

		// Start batch with single attachment
		$options = array(
			'attachment_ids' => array( $this->test_attachments[0] ),
			'format' => 'webp',
		);

		$this->batch_processor->start_batch_conversion( $options );
		
		// Force process batch (simulate cron execution)
		$this->batch_processor->force_process_batch();
		
		$progress = $this->batch_processor->get_batch_progress();
		
		// Check that processing occurred
		$this->assertGreaterThan( 0, $progress['processed'] );
	}

	/**
	 * Test queue status
	 */
	public function test_queue_status() {
		$options = array(
			'attachment_ids' => $this->test_attachments,
		);

		$this->batch_processor->start_batch_conversion( $options );
		
		$queue_status = $this->batch_processor->get_queue_status();
		
		$this->assertIsArray( $queue_status );
		$this->assertArrayHasKey( 'queue_size', $queue_status );
		$this->assertArrayHasKey( 'is_running', $queue_status );
		$this->assertArrayHasKey( 'progress', $queue_status );
		$this->assertArrayHasKey( 'next_scheduled', $queue_status );
		
		$this->assertEquals( count( $this->test_attachments ), $queue_status['queue_size'] );
		$this->assertTrue( $queue_status['is_running'] );
		$this->assertNotFalse( $queue_status['next_scheduled'] );
	}

	/**
	 * Test cleanup temporary files
	 */
	public function test_cleanup_temporary_files() {
		// Create some temporary files
		$upload_dir = wp_upload_dir();
		$temp_files = array(
			$upload_dir['path'] . '/wp-image-optimizer-temp-123.tmp',
			$upload_dir['path'] . '/test-file.tmp',
		);

		foreach ( $temp_files as $temp_file ) {
			file_put_contents( $temp_file, 'test content' );
			// Set file time to 2 hours ago to ensure cleanup
			touch( $temp_file, time() - ( 2 * HOUR_IN_SECONDS ) );
		}

		$result = $this->batch_processor->cleanup_temporary_files();
		
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'temp_files_deleted', $result );
		$this->assertArrayHasKey( 'failed_conversions_cleaned', $result );
		$this->assertArrayHasKey( 'orphaned_files_deleted', $result );
		$this->assertArrayHasKey( 'errors', $result );
		
		// Check that temp files were deleted
		foreach ( $temp_files as $temp_file ) {
			$this->assertFileDoesNotExist( $temp_file );
		}
	}

	/**
	 * Test memory limit detection
	 */
	public function test_memory_limit_detection() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->batch_processor );
		$method = $reflection->getMethod( 'get_memory_limit' );
		$method->setAccessible( true );
		
		$memory_limit = $method->invoke( $this->batch_processor );
		
		// Should return a positive integer or 0 for unlimited
		$this->assertIsInt( $memory_limit );
		$this->assertGreaterThanOrEqual( 0, $memory_limit );
	}

	/**
	 * Test cron schedule addition
	 */
	public function test_cron_schedule_addition() {
		$schedules = array();
		$schedules = $this->batch_processor->add_cron_schedules( $schedules );
		
		$this->assertArrayHasKey( 'wp_image_optimizer_batch', $schedules );
		$this->assertEquals( 60, $schedules['wp_image_optimizer_batch']['interval'] );
		$this->assertNotEmpty( $schedules['wp_image_optimizer_batch']['display'] );
	}

	/**
	 * Test batch completion
	 */
	public function test_batch_completion() {
		// Start batch with no attachments (should complete immediately)
		$options = array(
			'attachment_ids' => array(),
		);

		$result = $this->batch_processor->start_batch_conversion( $options );
		$this->assertWPError( $result );
		$this->assertEquals( 'empty_queue', $result->get_error_code() );
	}

	/**
	 * Test error handling in batch processing
	 */
	public function test_error_handling_in_batch_processing() {
		// Create an attachment with invalid file path
		$invalid_attachment_id = $this->factory->attachment->create( array(
			'post_mime_type' => 'image/jpeg',
		) );
		
		$options = array(
			'attachment_ids' => array( $invalid_attachment_id ),
		);

		$this->batch_processor->start_batch_conversion( $options );
		
		// Force process batch
		$this->batch_processor->force_process_batch();
		
		$progress = $this->batch_processor->get_batch_progress();
		
		// Should have processed the item but with errors
		$this->assertEquals( 1, $progress['processed'] );
		$this->assertGreaterThan( 0, $progress['failed'] );
		$this->assertNotEmpty( $progress['errors'] );
		
		// Clean up
		wp_delete_attachment( $invalid_attachment_id, true );
	}

	/**
	 * Test batch processing with format validation
	 */
	public function test_batch_processing_with_format_validation() {
		$options = array(
			'format' => 'invalid_format',
			'attachment_ids' => $this->test_attachments,
		);

		// This should work as format validation happens in CLI, not batch processor
		$result = $this->batch_processor->start_batch_conversion( $options );
		$this->assertTrue( $result );
		
		$progress = $this->batch_processor->get_batch_progress();
		$this->assertEquals( 'invalid_format', $progress['options']['format'] );
	}

	/**
	 * Test batch processing limits
	 */
	public function test_batch_processing_limits() {
		$options = array(
			'limit' => 2,
			'offset' => 1,
		);

		$this->batch_processor->start_batch_conversion( $options );
		
		$progress = $this->batch_processor->get_batch_progress();
		
		// Should respect the limit
		$this->assertLessThanOrEqual( 2, $progress['total'] );
	}

	/**
	 * Test cleanup on deactivation
	 */
	public function test_cleanup_on_deactivation() {
		// Start a batch
		$this->batch_processor->start_batch_conversion();
		$this->assertTrue( $this->batch_processor->is_batch_running() );
		
		// Simulate plugin deactivation
		$this->batch_processor->cleanup_on_deactivation();
		
		// Should cancel batch and clear cron
		$this->assertFalse( $this->batch_processor->is_batch_running() );
		$this->assertFalse( wp_next_scheduled( 'wp_image_optimizer_batch_process' ) );
	}

	/**
	 * Test batch processing with force option
	 */
	public function test_batch_processing_with_force_option() {
		$options = array(
			'force' => true,
			'attachment_ids' => array( $this->test_attachments[0] ),
		);

		$result = $this->batch_processor->start_batch_conversion( $options );
		$this->assertTrue( $result );
		
		$progress = $this->batch_processor->get_batch_progress();
		$this->assertTrue( $progress['options']['force'] );
	}

	/**
	 * Test progress percentage calculation
	 */
	public function test_progress_percentage_calculation() {
		$options = array(
			'attachment_ids' => $this->test_attachments,
		);

		$this->batch_processor->start_batch_conversion( $options );
		
		$progress = $this->batch_processor->get_batch_progress();
		
		// Initially should be 0%
		$this->assertEquals( 0, $progress['percentage'] );
		
		// Simulate some processing by manually updating progress
		$progress_data = get_option( 'wp_image_optimizer_batch_progress' );
		$progress_data['processed'] = 1;
		update_option( 'wp_image_optimizer_batch_progress', $progress_data );
		
		$updated_progress = $this->batch_processor->get_batch_progress();
		$expected_percentage = round( ( 1 / count( $this->test_attachments ) ) * 100, 2 );
		$this->assertEquals( $expected_percentage, $updated_progress['percentage'] );
	}

	/**
	 * Test estimated time remaining calculation
	 */
	public function test_estimated_time_remaining_calculation() {
		$options = array(
			'attachment_ids' => $this->test_attachments,
		);

		$this->batch_processor->start_batch_conversion( $options );
		
		// Simulate some processing time
		$progress_data = get_option( 'wp_image_optimizer_batch_progress' );
		$progress_data['processed'] = 1;
		$progress_data['start_time'] = time() - 60; // Started 1 minute ago
		update_option( 'wp_image_optimizer_batch_progress', $progress_data );
		
		$progress = $this->batch_processor->get_batch_progress();
		
		// Should have estimated time remaining
		$this->assertArrayHasKey( 'estimated_time_remaining', $progress );
		$this->assertIsInt( $progress['estimated_time_remaining'] );
		$this->assertGreaterThan( 0, $progress['estimated_time_remaining'] );
	}

	/**
	 * Test priority queue system
	 */
	public function test_priority_queue_system() {
		// Create attachments with different file sizes to test priority
		$upload_dir = wp_upload_dir();
		
		// Create small file (high priority)
		$small_file = $upload_dir['path'] . '/small-test.jpg';
		$small_image = imagecreate( 10, 10 );
		imagejpeg( $small_image, $small_file );
		imagedestroy( $small_image );
		$small_attachment = $this->factory->attachment->create_upload_object( $small_file );
		
		// Create large file (low priority) - simulate by creating larger image
		$large_file = $upload_dir['path'] . '/large-test.jpg';
		$large_image = imagecreate( 1000, 1000 );
		imagejpeg( $large_image, $large_file );
		imagedestroy( $large_image );
		$large_attachment = $this->factory->attachment->create_upload_object( $large_file );
		
		$options = array(
			'attachment_ids' => array( $large_attachment, $small_attachment ), // Large first
		);

		$this->batch_processor->start_batch_conversion( $options );
		
		// Get queue to check priority ordering
		$queue = get_option( 'wp_image_optimizer_batch_queue' );
		
		// First item should be the small file (higher priority)
		$this->assertEquals( $small_attachment, $queue[0]['attachment_id'] );
		$this->assertEquals( 1, $queue[0]['priority'] ); // HIGH priority
		
		// Clean up
		wp_delete_attachment( $small_attachment, true );
		wp_delete_attachment( $large_attachment, true );
	}

	/**
	 * Test retry mechanism constants
	 */
	public function test_retry_mechanism_constants() {
		$reflection = new ReflectionClass( $this->batch_processor );
		
		$this->assertTrue( $reflection->hasConstant( 'MAX_RETRY_ATTEMPTS' ) );
		$this->assertTrue( $reflection->hasConstant( 'PRIORITY_HIGH' ) );
		$this->assertTrue( $reflection->hasConstant( 'PRIORITY_NORMAL' ) );
		$this->assertTrue( $reflection->hasConstant( 'PRIORITY_LOW' ) );
		
		$this->assertEquals( 3, $reflection->getConstant( 'MAX_RETRY_ATTEMPTS' ) );
		$this->assertEquals( 1, $reflection->getConstant( 'PRIORITY_HIGH' ) );
		$this->assertEquals( 2, $reflection->getConstant( 'PRIORITY_NORMAL' ) );
		$this->assertEquals( 3, $reflection->getConstant( 'PRIORITY_LOW' ) );
	}

	/**
	 * Test queue item structure with new fields
	 */
	public function test_queue_item_structure() {
		$options = array(
			'attachment_ids' => array( $this->test_attachments[0] ),
		);

		$this->batch_processor->start_batch_conversion( $options );
		
		$queue = get_option( 'wp_image_optimizer_batch_queue' );
		$item = $queue[0];
		
		// Check that new fields are present
		$this->assertArrayHasKey( 'priority', $item );
		$this->assertArrayHasKey( 'retry_count', $item );
		$this->assertArrayHasKey( 'created_time', $item );
		
		// Check default values
		$this->assertEquals( 0, $item['retry_count'] );
		$this->assertIsInt( $item['priority'] );
		$this->assertIsInt( $item['created_time'] );
		$this->assertLessThanOrEqual( time(), $item['created_time'] );
	}

	/**
	 * Test priority determination method
	 */
	public function test_priority_determination() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->batch_processor );
		$method = $reflection->getMethod( 'determine_item_priority' );
		$method->setAccessible( true );
		
		// Test with existing attachment
		$priority = $method->invoke( $this->batch_processor, $this->test_attachments[0] );
		
		// Should return a valid priority level
		$this->assertIsInt( $priority );
		$this->assertGreaterThanOrEqual( 1, $priority );
		$this->assertLessThanOrEqual( 3, $priority );
	}

	/**
	 * Test retry logic with should_retry_item method
	 */
	public function test_should_retry_item_logic() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->batch_processor );
		$method = $reflection->getMethod( 'should_retry_item' );
		$method->setAccessible( true );
		
		// Test item with no retries yet
		$item = array(
			'attachment_id' => $this->test_attachments[0],
			'retry_count' => 0,
		);
		
		$retryable_error = new WP_Error( 'conversion_failed', 'Temporary failure' );
		$this->assertTrue( $method->invoke( $this->batch_processor, $item, $retryable_error ) );
		
		// Test item that has reached max retries
		$item['retry_count'] = 3;
		$this->assertFalse( $method->invoke( $this->batch_processor, $item, $retryable_error ) );
		
		// Test non-retryable error
		$item['retry_count'] = 0;
		$non_retryable_error = new WP_Error( 'file_not_found', 'File not found' );
		$this->assertFalse( $method->invoke( $this->batch_processor, $item, $non_retryable_error ) );
	}

	/**
	 * Test batch processing with retry delays
	 */
	public function test_batch_processing_with_retry_delays() {
		// Create a queue item with retry_after in the future
		$queue = array(
			array(
				'attachment_id' => $this->test_attachments[0],
				'format' => 'webp',
				'force' => false,
				'priority' => 2,
				'retry_count' => 1,
				'created_time' => time(),
				'retry_after' => time() + 3600, // 1 hour in future
			),
		);
		
		// Manually set queue and progress
		update_option( 'wp_image_optimizer_batch_queue', $queue );
		update_option( 'wp_image_optimizer_batch_progress', array(
			'status' => 'running',
			'total' => 1,
			'processed' => 0,
			'successful' => 0,
			'failed' => 0,
			'skipped' => 0,
			'space_saved' => 0,
			'start_time' => time(),
			'end_time' => null,
			'options' => array(),
			'errors' => array(),
		) );
		
		// Force process batch
		$this->batch_processor->force_process_batch();
		
		// Queue should still have the item (not processed due to retry delay)
		$updated_queue = get_option( 'wp_image_optimizer_batch_queue' );
		$this->assertCount( 1, $updated_queue );
		$this->assertEquals( $this->test_attachments[0], $updated_queue[0]['attachment_id'] );
	}

	/**
	 * Test enhanced memory management
	 */
	public function test_enhanced_memory_management() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->batch_processor );
		$method = $reflection->getMethod( 'can_continue_processing' );
		$method->setAccessible( true );
		
		// Set batch start time
		$start_time_property = $reflection->getProperty( 'batch_start_time' );
		$start_time_property->setAccessible( true );
		$start_time_property->setValue( $this->batch_processor, microtime( true ) );
		
		// Should be able to continue initially
		$this->assertTrue( $method->invoke( $this->batch_processor ) );
		
		// Test with old start time (should stop due to time limit)
		$start_time_property->setValue( $this->batch_processor, microtime( true ) - 30 );
		$this->assertFalse( $method->invoke( $this->batch_processor ) );
	}

	/**
	 * Test queue sorting by priority and creation time
	 */
	public function test_queue_sorting() {
		// Create multiple attachments to test sorting
		$options = array(
			'attachment_ids' => $this->test_attachments,
		);

		$this->batch_processor->start_batch_conversion( $options );
		
		$queue = get_option( 'wp_image_optimizer_batch_queue' );
		
		// Verify queue is sorted by priority (lower number = higher priority)
		for ( $i = 1; $i < count( $queue ); $i++ ) {
			$this->assertLessThanOrEqual( $queue[$i]['priority'], $queue[$i-1]['priority'] );
		}
		
		// Verify items have creation time
		foreach ( $queue as $item ) {
			$this->assertArrayHasKey( 'created_time', $item );
			$this->assertIsInt( $item['created_time'] );
		}
	}

	/**
	 * Test batch completion with enhanced progress data
	 */
	public function test_batch_completion_with_enhanced_progress() {
		// Start batch with single attachment
		$options = array(
			'attachment_ids' => array( $this->test_attachments[0] ),
		);

		$this->batch_processor->start_batch_conversion( $options );
		
		// Manually complete the batch by emptying queue
		update_option( 'wp_image_optimizer_batch_queue', array() );
		
		// Force process batch (should complete)
		$this->batch_processor->force_process_batch();
		
		$progress = $this->batch_processor->get_batch_progress();
		
		// Should be completed
		$this->assertEquals( 'completed', $progress['status'] );
		$this->assertNotNull( $progress['end_time'] );
		
		// Should have percentage calculation
		$this->assertArrayHasKey( 'percentage', $progress );
	}
}