<?php
/**
 * Tests for performance aspects
 *
 * @package WP_Image_Optimizer
 */

class Test_Performance extends WP_UnitTestCase {

	/**
	 * Test image paths
	 *
	 * @var array
	 */
	private $test_images = array();

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Create test images
		$upload_dir = wp_upload_dir();
		$this->test_images = array(
			'small' => $upload_dir['basedir'] . '/test-perf-small.jpg',
			'medium' => $upload_dir['basedir'] . '/test-perf-medium.jpg',
			'large' => $upload_dir['basedir'] . '/test-perf-large.jpg',
		);
		
		// Create test images of different sizes
		wp_image_optimizer_create_test_image( $this->test_images['small'], 'jpeg', 100, 100 );
		wp_image_optimizer_create_test_image( $this->test_images['medium'], 'jpeg', 500, 500 );
		wp_image_optimizer_create_test_image( $this->test_images['large'], 'jpeg', 1000, 1000 );
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		// Clean up test images
		foreach ( $this->test_images as $path ) {
			wp_image_optimizer_cleanup_test_image( $path );
			
			// Clean up converted images
			$webp_path = str_replace( '.jpg', '.webp', $path );
			$avif_path = str_replace( '.jpg', '.avif', $path );
			wp_image_optimizer_cleanup_test_image( $webp_path );
			wp_image_optimizer_cleanup_test_image( $avif_path );
		}
		
		parent::tearDown();
	}

	/**
	 * Test conversion performance
	 */
	public function test_conversion_performance() {
		// Skip if no converter is available
		if ( ! Converter_Factory::has_available_converter() ) {
			$this->markTestSkipped( 'No image converter available for testing' );
			return;
		}
		
		// Create image converter
		$image_converter = new WP_Image_Optimizer_Image_Converter();
		
		// Test conversion performance for each image size
		foreach ( $this->test_images as $size => $path ) {
			// Measure time for WebP conversion
			$start_time = microtime( true );
			$result = $image_converter->convert_on_demand( $path, 'webp' );
			$end_time = microtime( true );
			$webp_time = $end_time - $start_time;
			
			// Check if conversion was successful
			if ( is_wp_error( $result ) ) {
				$this->markTestSkipped( "WebP conversion failed for $size image: " . $result->get_error_message() );
				continue;
			}
			
			// Measure time for AVIF conversion
			$start_time = microtime( true );
			$result = $image_converter->convert_on_demand( $path, 'avif' );
			$end_time = microtime( true );
			$avif_time = $end_time - $start_time;
			
			// AVIF might not be supported, so don't fail the test
			if ( is_wp_error( $result ) ) {
				$this->markTestSkipped( "AVIF conversion failed for $size image: " . $result->get_error_message() );
				$avif_time = null;
			}
			
			// Log performance results
			error_log( sprintf(
				'Conversion performance for %s image: WebP: %.4f seconds, AVIF: %s',
				$size,
				$webp_time,
				$avif_time ? sprintf( '%.4f seconds', $avif_time ) : 'N/A'
			) );
			
			// Assert that conversion time is reasonable
			// These are very generous limits, adjust based on your environment
			switch ( $size ) {
				case 'small':
					$this->assertLessThan( 2.0, $webp_time, 'Small image WebP conversion should be fast' );
					if ( $avif_time ) {
						$this->assertLessThan( 3.0, $avif_time, 'Small image AVIF conversion should be reasonably fast' );
					}
					break;
				case 'medium':
					$this->assertLessThan( 5.0, $webp_time, 'Medium image WebP conversion should be reasonably fast' );
					if ( $avif_time ) {
						$this->assertLessThan( 7.0, $avif_time, 'Medium image AVIF conversion should be reasonably fast' );
					}
					break;
				case 'large':
					$this->assertLessThan( 10.0, $webp_time, 'Large image WebP conversion should complete within reasonable time' );
					if ( $avif_time ) {
						$this->assertLessThan( 15.0, $avif_time, 'Large image AVIF conversion should complete within reasonable time' );
					}
					break;
			}
		}
	}

	/**
	 * Test memory usage during conversion
	 */
	public function test_memory_usage_during_conversion() {
		// Skip if no converter is available
		if ( ! Converter_Factory::has_available_converter() ) {
			$this->markTestSkipped( 'No image converter available for testing' );
			return;
		}
		
		// Create image converter
		$image_converter = new WP_Image_Optimizer_Image_Converter();
		
		// Test memory usage for each image size
		foreach ( $this->test_images as $size => $path ) {
			// Get initial memory usage
			$initial_memory = memory_get_usage();
			
			// Convert image
			$result = $image_converter->convert_image( $path );
			
			// Get peak memory usage
			$peak_memory = memory_get_peak_usage() - $initial_memory;
			
			// Check if conversion was successful
			if ( is_wp_error( $result ) ) {
				$this->markTestSkipped( "Conversion failed for $size image: " . $result->get_error_message() );
				continue;
			}
			
			// Log memory usage
			error_log( sprintf(
				'Memory usage for %s image conversion: %.2f MB',
				$size,
				$peak_memory / 1024 / 1024
			) );
			
			// Assert that memory usage is reasonable
			// These are very generous limits, adjust based on your environment
			switch ( $size ) {
				case 'small':
					$this->assertLessThan( 10 * 1024 * 1024, $peak_memory, 'Small image conversion should use reasonable memory' );
					break;
				case 'medium':
					$this->assertLessThan( 50 * 1024 * 1024, $peak_memory, 'Medium image conversion should use reasonable memory' );
					break;
				case 'large':
					$this->assertLessThan( 100 * 1024 * 1024, $peak_memory, 'Large image conversion should use reasonable memory' );
					break;
			}
		}
	}

	/**
	 * Test batch processing performance
	 */
	public function test_batch_processing_performance() {
		// Skip if no converter is available
		if ( ! Converter_Factory::has_available_converter() ) {
			$this->markTestSkipped( 'No image converter available for testing' );
			return;
		}
		
		// Create batch processor
		$batch_processor = new WP_Image_Optimizer_Batch_Processor();
		
		// Create test attachments
		$attachment_ids = array();
		foreach ( $this->test_images as $path ) {
			$attachment_ids[] = wp_image_optimizer_create_test_attachment( $path );
		}
		
		// Measure time for batch processing
		$start_time = microtime( true );
		$result = $batch_processor->process_batch( $attachment_ids );
		$end_time = microtime( true );
		$batch_time = $end_time - $start_time;
		
		// Log performance results
		error_log( sprintf(
			'Batch processing performance for %d images: %.4f seconds (%.4f seconds per image)',
			count( $attachment_ids ),
			$batch_time,
			$batch_time / count( $attachment_ids )
		) );
		
		// Assert that batch processing time is reasonable
		$this->assertLessThan( 30.0, $batch_time, 'Batch processing should complete within reasonable time' );
		
		// Clean up attachments
		foreach ( $attachment_ids as $id ) {
			wp_delete_attachment( $id, true );
		}
	}

	/**
	 * Test file size reduction
	 */
	public function test_file_size_reduction() {
		// Skip if no converter is available
		if ( ! Converter_Factory::has_available_converter() ) {
			$this->markTestSkipped( 'No image converter available for testing' );
			return;
		}
		
		// Create image converter
		$image_converter = new WP_Image_Optimizer_Image_Converter();
		
		// Test file size reduction for each image size
		foreach ( $this->test_images as $size => $path ) {
			// Get original file size
			$original_size = filesize( $path );
			
			// Convert to WebP
			$webp_result = $image_converter->convert_on_demand( $path, 'webp' );
			
			// Check if conversion was successful
			if ( is_wp_error( $webp_result ) ) {
				$this->markTestSkipped( "WebP conversion failed for $size image: " . $webp_result->get_error_message() );
				continue;
			}
			
			// Get WebP file size
			$webp_size = filesize( $webp_result );
			
			// Calculate size reduction percentage
			$webp_reduction = ( $original_size - $webp_size ) / $original_size * 100;
			
			// Log file size reduction
			error_log( sprintf(
				'File size reduction for %s image: Original: %d bytes, WebP: %d bytes (%.2f%% reduction)',
				$size,
				$original_size,
				$webp_size,
				$webp_reduction
			) );
			
			// Assert that WebP file is smaller than original
			$this->assertLessThan( $original_size, $webp_size, "WebP file should be smaller than original for $size image" );
			
			// Convert to AVIF
			$avif_result = $image_converter->convert_on_demand( $path, 'avif' );
			
			// AVIF might not be supported, so don't fail the test
			if ( ! is_wp_error( $avif_result ) ) {
				// Get AVIF file size
				$avif_size = filesize( $avif_result );
				
				// Calculate size reduction percentage
				$avif_reduction = ( $original_size - $avif_size ) / $original_size * 100;
				
				// Log file size reduction
				error_log( sprintf(
					'File size reduction for %s image: Original: %d bytes, AVIF: %d bytes (%.2f%% reduction)',
					$size,
					$original_size,
					$avif_size,
					$avif_reduction
				) );
				
				// Assert that AVIF file is smaller than original
				$this->assertLessThan( $original_size, $avif_size, "AVIF file should be smaller than original for $size image" );
			}
		}
	}

	/**
	 * Test settings retrieval performance
	 */
	public function test_settings_retrieval_performance() {
		// Warm up cache
		WP_Image_Optimizer_Settings_Manager::get_settings();
		
		// Measure time for settings retrieval
		$start_time = microtime( true );
		for ( $i = 0; $i < 100; $i++ ) {
			WP_Image_Optimizer_Settings_Manager::get_settings();
		}
		$end_time = microtime( true );
		$total_time = $end_time - $start_time;
		$avg_time = $total_time / 100;
		
		// Log performance results
		error_log( sprintf(
			'Settings retrieval performance: %.6f seconds per call (100 calls in %.4f seconds)',
			$avg_time,
			$total_time
		) );
		
		// Assert that settings retrieval is fast
		$this->assertLessThan( 0.001, $avg_time, 'Settings retrieval should be very fast' );
	}

	/**
	 * Test converter detection performance
	 */
	public function test_converter_detection_performance() {
		// Clear capabilities cache
		Converter_Factory::clear_capabilities_cache();
		
		// Measure time for first detection (cold cache)
		$start_time = microtime( true );
		$capabilities1 = Converter_Factory::get_server_capabilities();
		$end_time = microtime( true );
		$cold_time = $end_time - $start_time;
		
		// Measure time for second detection (warm cache)
		$start_time = microtime( true );
		$capabilities2 = Converter_Factory::get_server_capabilities();
		$end_time = microtime( true );
		$warm_time = $end_time - $start_time;
		
		// Log performance results
		error_log( sprintf(
			'Converter detection performance: Cold: %.4f seconds, Warm: %.4f seconds',
			$cold_time,
			$warm_time
		) );
		
		// Assert that warm cache is faster than cold cache
		$this->assertLessThan( $cold_time, $warm_time, 'Warm cache should be faster than cold cache' );
		
		// Assert that capabilities are the same
		$this->assertEquals( $capabilities1, $capabilities2, 'Capabilities should be the same for cold and warm cache' );
	}
}