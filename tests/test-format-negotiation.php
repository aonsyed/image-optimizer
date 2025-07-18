<?php
/**
 * Tests for format negotiation
 *
 * @package WP_Image_Optimizer
 */

class Test_Format_Negotiation extends WP_UnitTestCase {

	/**
	 * Test format negotiation based on Accept header
	 */
	public function test_format_negotiation_from_accept_header() {
		// Create image handler
		$image_handler = new WP_Image_Optimizer_Image_Handler();
		
		// Test with AVIF support
		$accept_header = 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8';
		$best_format = $image_handler->negotiate_best_format( $accept_header );
		$this->assertEquals( 'avif', $best_format );
		
		// Test with WebP support but no AVIF
		$accept_header = 'image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8';
		$best_format = $image_handler->negotiate_best_format( $accept_header );
		$this->assertEquals( 'webp', $best_format );
		
		// Test with no modern format support
		$accept_header = 'image/jpeg,image/png,image/gif,*/*;q=0.8';
		$best_format = $image_handler->negotiate_best_format( $accept_header );
		$this->assertEquals( 'original', $best_format );
		
		// Test with empty header
		$best_format = $image_handler->negotiate_best_format( '' );
		$this->assertEquals( 'original', $best_format );
	}

	/**
	 * Test format negotiation with quality parameters
	 */
	public function test_format_negotiation_with_quality() {
		// Create image handler
		$image_handler = new WP_Image_Optimizer_Image_Handler();
		
		// Test with quality parameters
		$accept_header = 'image/avif;q=0.5,image/webp;q=0.8,image/jpeg;q=1.0';
		$best_format = $image_handler->negotiate_best_format( $accept_header );
		
		// WebP should be preferred over AVIF due to higher quality parameter
		$this->assertEquals( 'webp', $best_format );
	}

	/**
	 * Test format negotiation with disabled formats
	 */
	public function test_format_negotiation_with_disabled_formats() {
		// Create image handler
		$image_handler = new WP_Image_Optimizer_Image_Handler();
		
		// Disable AVIF format
		WP_Image_Optimizer_Settings_Manager::update_settings( array(
			'formats' => array(
				'avif' => array( 'enabled' => false ),
				'webp' => array( 'enabled' => true ),
			),
		) );
		
		// Test with AVIF and WebP support
		$accept_header = 'image/avif,image/webp,image/jpeg';
		$best_format = $image_handler->negotiate_best_format( $accept_header );
		
		// WebP should be preferred since AVIF is disabled
		$this->assertEquals( 'webp', $best_format );
		
		// Disable WebP format as well
		WP_Image_Optimizer_Settings_Manager::update_settings( array(
			'formats' => array(
				'avif' => array( 'enabled' => false ),
				'webp' => array( 'enabled' => false ),
			),
		) );
		
		// Test with AVIF and WebP support
		$best_format = $image_handler->negotiate_best_format( $accept_header );
		
		// Original should be returned since both formats are disabled
		$this->assertEquals( 'original', $best_format );
		
		// Reset settings
		WP_Image_Optimizer_Settings_Manager::reset_settings();
	}

	/**
	 * Test format negotiation with server capabilities
	 */
	public function test_format_negotiation_with_server_capabilities() {
		// Create image handler
		$image_handler = new WP_Image_Optimizer_Image_Handler();
		
		// Get server capabilities
		$capabilities = Converter_Factory::get_server_capabilities();
		
		// Test with AVIF and WebP support
		$accept_header = 'image/avif,image/webp,image/jpeg';
		$best_format = $image_handler->negotiate_best_format( $accept_header );
		
		// If server supports AVIF, it should be preferred
		if ( $capabilities['avif_support'] ) {
			$this->assertEquals( 'avif', $best_format );
		}
		// If server supports WebP but not AVIF, WebP should be preferred
		elseif ( $capabilities['webp_support'] ) {
			$this->assertEquals( 'webp', $best_format );
		}
		// If server doesn't support either, original should be returned
		else {
			$this->assertEquals( 'original', $best_format );
		}
	}

	/**
	 * Test format negotiation with file existence
	 */
	public function test_format_negotiation_with_file_existence() {
		// Create image handler
		$image_handler = new WP_Image_Optimizer_Image_Handler();
		
		// Create test file paths
		$upload_dir = wp_upload_dir();
		$original_path = $upload_dir['basedir'] . '/test-negotiation.jpg';
		$webp_path = $upload_dir['basedir'] . '/test-negotiation.webp';
		
		// Create original and WebP files
		touch( $original_path );
		touch( $webp_path );
		
		// Test with WebP support and existing WebP file
		$accept_header = 'image/webp,image/jpeg';
		$best_format = $image_handler->negotiate_best_format_for_file( $accept_header, $original_path );
		
		// WebP should be preferred since it exists
		$this->assertEquals( 'webp', $best_format );
		
		// Remove WebP file
		unlink( $webp_path );
		
		// Test again with WebP support but no WebP file
		$best_format = $image_handler->negotiate_best_format_for_file( $accept_header, $original_path );
		
		// Original should be returned since WebP file doesn't exist
		// and on-demand conversion is not enabled in this test
		$this->assertEquals( 'original', $best_format );
		
		// Clean up original file
		unlink( $original_path );
	}

	/**
	 * Test format negotiation with on-demand conversion
	 */
	public function test_format_negotiation_with_on_demand_conversion() {
		// Create image handler with on-demand conversion enabled
		$image_handler = new WP_Image_Optimizer_Image_Handler( true );
		
		// Create test file paths
		$upload_dir = wp_upload_dir();
		$original_path = $upload_dir['basedir'] . '/test-on-demand.jpg';
		
		// Create original file
		touch( $original_path );
		
		// Test with WebP support but no WebP file
		$accept_header = 'image/webp,image/jpeg';
		$best_format = $image_handler->negotiate_best_format_for_file( $accept_header, $original_path );
		
		// WebP should be preferred since on-demand conversion is enabled
		$this->assertEquals( 'webp', $best_format );
		
		// Clean up original file
		unlink( $original_path );
	}

	/**
	 * Test format negotiation with browser user agent
	 */
	public function test_format_negotiation_with_user_agent() {
		// Create image handler
		$image_handler = new WP_Image_Optimizer_Image_Handler();
		
		// Test with Chrome user agent (supports WebP and AVIF)
		$user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
		$accept_header = 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8';
		$best_format = $image_handler->negotiate_best_format( $accept_header, $user_agent );
		$this->assertEquals( 'avif', $best_format );
		
		// Test with old Safari user agent (doesn't support WebP or AVIF)
		$user_agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0.3 Safari/605.1.15';
		$accept_header = 'image/png,image/svg+xml,image/*;q=0.8,video/*;q=0.8,*/*;q=0.5';
		$best_format = $image_handler->negotiate_best_format( $accept_header, $user_agent );
		$this->assertEquals( 'original', $best_format );
	}
}