<?php
/**
 * Tests for ImageMagick_Converter class.
 *
 * @package WP_Image_Optimizer
 * @since   1.0.0
 */

/**
 * Test class for ImageMagick_Converter.
 */
class Test_ImageMagick_Converter extends WP_UnitTestCase {

	/**
	 * ImageMagick converter instance.
	 *
	 * @var ImageMagick_Converter
	 */
	private $converter;

	/**
	 * Test image paths.
	 *
	 * @var array
	 */
	private $test_images;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Include the converter interface and class.
		require_once ABSPATH . 'wp-content/plugins/wp-image-optimizer/includes/interfaces/interface-converter.php';
		require_once ABSPATH . 'wp-content/plugins/wp-image-optimizer/includes/converters/class-imagemagick-converter.php';

		$this->converter = new ImageMagick_Converter();

		// Set up test image paths.
		$upload_dir = wp_upload_dir();
		$this->test_images = array(
			'source_jpg' => $upload_dir['basedir'] . '/test-image.jpg',
			'source_png' => $upload_dir['basedir'] . '/test-image.png',
			'dest_webp' => $upload_dir['basedir'] . '/test-image.webp',
			'dest_avif' => $upload_dir['basedir'] . '/test-image.avif',
		);

		// Create test images.
		$this->create_test_images();
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		// Clean up test files.
		foreach ( $this->test_images as $path ) {
			if ( file_exists( $path ) ) {
				unlink( $path );
			}
		}

		parent::tearDown();
	}

	/**
	 * Test converter availability detection.
	 */
	public function test_is_available() {
		$is_available = $this->converter->is_available();

		// The result depends on server configuration, but should be boolean.
		$this->assertIsBool( $is_available );

		// If ImageMagick is available, extension should be loaded.
		if ( $is_available ) {
			$this->assertTrue( extension_loaded( 'imagick' ) );
			$this->assertTrue( class_exists( 'Imagick' ) );
		}
	}

	/**
	 * Test converter name.
	 */
	public function test_get_name() {
		$this->assertEquals( 'ImageMagick', $this->converter->get_name() );
	}

	/**
	 * Test converter priority.
	 */
	public function test_get_priority() {
		$priority = $this->converter->get_priority();
		$this->assertIsInt( $priority );
		$this->assertEquals( 10, $priority );
	}

	/**
	 * Test supported formats detection.
	 */
	public function test_get_supported_formats() {
		$formats = $this->converter->get_supported_formats();
		$this->assertIsArray( $formats );

		// If ImageMagick is not available, should return empty array.
		if ( ! $this->converter->is_available() ) {
			$this->assertEmpty( $formats );
			return;
		}

		// If available, should contain valid format strings.
		foreach ( $formats as $format ) {
			$this->assertIsString( $format );
			$this->assertContains( $format, array( 'webp', 'avif' ) );
		}
	}

	/**
	 * Test WebP conversion with valid parameters.
	 */
	public function test_convert_to_webp_success() {
		// Skip if ImageMagick is not available.
		if ( ! $this->converter->is_available() ) {
			$this->markTestSkipped( 'ImageMagick not available' );
		}

		// Skip if WebP is not supported.
		$formats = $this->converter->get_supported_formats();
		if ( ! in_array( 'webp', $formats, true ) ) {
			$this->markTestSkipped( 'WebP not supported by ImageMagick' );
		}

		$result = $this->converter->convert_to_webp(
			$this->test_images['source_jpg'],
			$this->test_images['dest_webp'],
			80
		);

		$this->assertTrue( $result );
		$this->assertFileExists( $this->test_images['dest_webp'] );

		// Verify the converted file is actually WebP.
		$image_info = getimagesize( $this->test_images['dest_webp'] );
		$this->assertEquals( 'image/webp', $image_info['mime'] );
	}

	/**
	 * Test AVIF conversion with valid parameters.
	 */
	public function test_convert_to_avif_success() {
		// Skip if ImageMagick is not available.
		if ( ! $this->converter->is_available() ) {
			$this->markTestSkipped( 'ImageMagick not available' );
		}

		// Skip if AVIF is not supported.
		$formats = $this->converter->get_supported_formats();
		if ( ! in_array( 'avif', $formats, true ) ) {
			$this->markTestSkipped( 'AVIF not supported by ImageMagick' );
		}

		$result = $this->converter->convert_to_avif(
			$this->test_images['source_jpg'],
			$this->test_images['dest_avif'],
			75
		);

		$this->assertTrue( $result );
		$this->assertFileExists( $this->test_images['dest_avif'] );
	}

	/**
	 * Test conversion with non-existent source file.
	 */
	public function test_convert_with_missing_source() {
		$result = $this->converter->convert_to_webp(
			'/non/existent/file.jpg',
			$this->test_images['dest_webp'],
			80
		);

		$this->assertFalse( $result );
		$this->assertFileDoesNotExist( $this->test_images['dest_webp'] );
	}

	/**
	 * Test conversion with invalid quality parameter.
	 */
	public function test_convert_with_invalid_quality() {
		$result = $this->converter->convert_to_webp(
			$this->test_images['source_jpg'],
			$this->test_images['dest_webp'],
			150 // Invalid quality > 100
		);

		$this->assertFalse( $result );
		$this->assertFileDoesNotExist( $this->test_images['dest_webp'] );
	}

	/**
	 * Test conversion with unwritable destination.
	 */
	public function test_convert_with_unwritable_destination() {
		$result = $this->converter->convert_to_webp(
			$this->test_images['source_jpg'],
			'/root/unwritable/path/test.webp',
			80
		);

		$this->assertFalse( $result );
	}

	/**
	 * Test batch conversion functionality.
	 */
	public function test_batch_convert() {
		// Skip if ImageMagick is not available.
		if ( ! $this->converter->is_available() ) {
			$this->markTestSkipped( 'ImageMagick not available' );
		}

		$conversions = array(
			array(
				'source' => $this->test_images['source_jpg'],
				'destination' => $this->test_images['dest_webp'],
				'format' => 'webp',
				'quality' => 80,
			),
		);

		// Track progress callback calls.
		$progress_calls = array();
		$progress_callback = function( $current, $total, $success ) use ( &$progress_calls ) {
			$progress_calls[] = array( $current, $total, $success );
		};

		$results = $this->converter->batch_convert( $conversions, $progress_callback );

		$this->assertIsArray( $results );
		$this->assertCount( 1, $results );

		$result = $results[0];
		$this->assertEquals( $this->test_images['source_jpg'], $result['source'] );
		$this->assertEquals( $this->test_images['dest_webp'], $result['destination'] );
		$this->assertEquals( 'webp', $result['format'] );
		$this->assertIsBool( $result['success'] );

		// Check progress callback was called.
		$this->assertCount( 1, $progress_calls );
		$this->assertEquals( array( 1, 1, $result['success'] ), $progress_calls[0] );
	}

	/**
	 * Test batch conversion with mixed results.
	 */
	public function test_batch_convert_mixed_results() {
		// Skip if ImageMagick is not available.
		if ( ! $this->converter->is_available() ) {
			$this->markTestSkipped( 'ImageMagick not available' );
		}

		$conversions = array(
			array(
				'source' => $this->test_images['source_jpg'],
				'destination' => $this->test_images['dest_webp'],
				'format' => 'webp',
			),
			array(
				'source' => '/non/existent/file.jpg',
				'destination' => $this->test_images['dest_avif'],
				'format' => 'avif',
			),
		);

		$results = $this->converter->batch_convert( $conversions );

		$this->assertCount( 2, $results );

		// First conversion might succeed (depending on WebP support).
		$this->assertIsBool( $results[0]['success'] );

		// Second conversion should fail (non-existent source).
		$this->assertFalse( $results[1]['success'] );
	}

	/**
	 * Test conversion when ImageMagick is not available.
	 */
	public function test_convert_when_not_available() {
		// Create a mock converter that reports as not available.
		$mock_converter = $this->getMockBuilder( 'ImageMagick_Converter' )
			->onlyMethods( array( 'is_available' ) )
			->getMock();

		$mock_converter->method( 'is_available' )
			->willReturn( false );

		$result = $mock_converter->convert_to_webp(
			$this->test_images['source_jpg'],
			$this->test_images['dest_webp'],
			80
		);

		$this->assertFalse( $result );
	}

	/**
	 * Create test images for testing.
	 */
	private function create_test_images() {
		// Create a simple test JPEG image.
		$image = imagecreate( 100, 100 );
		$white = imagecolorallocate( $image, 255, 255, 255 );
		$black = imagecolorallocate( $image, 0, 0, 0 );
		imagefill( $image, 0, 0, $white );
		imagestring( $image, 5, 10, 40, 'TEST', $black );
		imagejpeg( $image, $this->test_images['source_jpg'], 90 );
		imagedestroy( $image );

		// Create a simple test PNG image.
		$image = imagecreate( 100, 100 );
		$white = imagecolorallocate( $image, 255, 255, 255 );
		$red = imagecolorallocate( $image, 255, 0, 0 );
		imagefill( $image, 0, 0, $white );
		imagestring( $image, 5, 10, 40, 'PNG', $red );
		imagepng( $image, $this->test_images['source_png'] );
		imagedestroy( $image );
	}

	/**
	 * Test format checking to skip unnecessary conversions.
	 */
	public function test_skip_conversion_for_same_format() {
		// Skip if ImageMagick is not available.
		if ( ! $this->converter->is_available() ) {
			$this->markTestSkipped( 'ImageMagick not available' );
		}

		// Create a WebP test image (mock by copying and renaming).
		$webp_source = $this->test_images['source_jpg'] . '.webp';
		copy( $this->test_images['source_jpg'], $webp_source );

		// Mock getimagesize to return WebP mime type.
		$original_function = 'getimagesize';
		if ( function_exists( 'runkit_function_rename' ) ) {
			runkit_function_rename( $original_function, $original_function . '_original' );
			runkit_function_add( $original_function, '$filename', 'return array("mime" => "image/webp");' );
		}

		$result = $this->converter->convert_to_webp(
			$webp_source,
			$this->test_images['dest_webp'],
			80
		);

		// Should succeed by copying instead of converting.
		$this->assertTrue( $result );
		$this->assertFileExists( $this->test_images['dest_webp'] );

		// Restore original function if we mocked it.
		if ( function_exists( 'runkit_function_rename' ) ) {
			runkit_function_remove( $original_function );
			runkit_function_rename( $original_function . '_original', $original_function );
		}

		// Clean up.
		if ( file_exists( $webp_source ) ) {
			unlink( $webp_source );
		}
	}
}