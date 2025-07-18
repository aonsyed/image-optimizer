<?php
/**
 * Tests for GD_Converter class.
 *
 * @package WP_Image_Optimizer
 * @since   1.0.0
 */

/**
 * Test class for GD_Converter.
 */
class Test_GD_Converter extends WP_UnitTestCase {

	/**
	 * GD converter instance.
	 *
	 * @var GD_Converter
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
		require_once ABSPATH . 'wp-content/plugins/wp-image-optimizer/includes/converters/class-gd-converter.php';

		$this->converter = new GD_Converter();

		// Set up test image paths.
		$upload_dir = wp_upload_dir();
		$this->test_images = array(
			'source_jpg' => $upload_dir['basedir'] . '/test-gd-image.jpg',
			'source_png' => $upload_dir['basedir'] . '/test-gd-image.png',
			'source_gif' => $upload_dir['basedir'] . '/test-gd-image.gif',
			'source_webp' => $upload_dir['basedir'] . '/test-gd-image.webp',
			'dest_webp' => $upload_dir['basedir'] . '/test-gd-converted.webp',
			'dest_avif' => $upload_dir['basedir'] . '/test-gd-converted.avif',
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
	}	/**

	 * Test GD converter availability detection.
	 */
	public function test_is_available() {
		$is_available = $this->converter->is_available();

		// The result depends on server configuration, but should be boolean.
		$this->assertIsBool( $is_available );

		// If GD is available, extension should be loaded.
		if ( $is_available ) {
			$this->assertTrue( extension_loaded( 'gd' ) );
			$this->assertTrue( function_exists( 'gd_info' ) );
		}
	}

	/**
	 * Test converter name.
	 */
	public function test_get_name() {
		$this->assertEquals( 'GD', $this->converter->get_name() );
	}

	/**
	 * Test converter priority.
	 */
	public function test_get_priority() {
		$priority = $this->converter->get_priority();
		$this->assertIsInt( $priority );
		$this->assertEquals( 20, $priority );
	}

	/**
	 * Test supported formats detection.
	 */
	public function test_get_supported_formats() {
		$formats = $this->converter->get_supported_formats();
		$this->assertIsArray( $formats );

		// If GD is not available, should return empty array.
		if ( ! $this->converter->is_available() ) {
			$this->assertEmpty( $formats );
			return;
		}

		// GD should only support WebP, not AVIF.
		foreach ( $formats as $format ) {
			$this->assertIsString( $format );
			$this->assertEquals( 'webp', $format );
		}

		// AVIF should never be in supported formats for GD.
		$this->assertNotContains( 'avif', $formats );
	}

	/**
	 * Test WebP conversion with JPEG source.
	 */
	public function test_convert_jpeg_to_webp_success() {
		// Skip if GD is not available.
		if ( ! $this->converter->is_available() ) {
			$this->markTestSkipped( 'GD not available' );
		}

		// Skip if WebP is not supported.
		$formats = $this->converter->get_supported_formats();
		if ( ! in_array( 'webp', $formats, true ) ) {
			$this->markTestSkipped( 'WebP not supported by GD' );
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
	 * Test WebP conversion with PNG source.
	 */
	public function test_convert_png_to_webp_success() {
		// Skip if GD is not available.
		if ( ! $this->converter->is_available() ) {
			$this->markTestSkipped( 'GD not available' );
		}

		// Skip if WebP is not supported.
		$formats = $this->converter->get_supported_formats();
		if ( ! in_array( 'webp', $formats, true ) ) {
			$this->markTestSkipped( 'WebP not supported by GD' );
		}

		$result = $this->converter->convert_to_webp(
			$this->test_images['source_png'],
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
	 * Test AVIF conversion always fails with GD.
	 */
	public function test_convert_to_avif_always_fails() {
		$result = $this->converter->convert_to_avif(
			$this->test_images['source_jpg'],
			$this->test_images['dest_avif'],
			75
		);

		// GD doesn't support AVIF, so this should always fail.
		$this->assertFalse( $result );
		$this->assertFileDoesNotExist( $this->test_images['dest_avif'] );
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
		$invalid_qualities = array( 0, -1, 101, 150, 'invalid', null );

		foreach ( $invalid_qualities as $quality ) {
			$result = $this->converter->convert_to_webp(
				$this->test_images['source_jpg'],
				$this->test_images['dest_webp'],
				$quality
			);

			$this->assertFalse( $result, "Quality {$quality} should be invalid" );
		}
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
		// Skip if GD is not available.
		if ( ! $this->converter->is_available() ) {
			$this->markTestSkipped( 'GD not available' );
		}

		$conversions = array(
			array(
				'source' => $this->test_images['source_jpg'],
				'destination' => $this->test_images['dest_webp'] . '_batch1',
				'format' => 'webp',
				'quality' => 80,
			),
			array(
				'source' => $this->test_images['source_png'],
				'destination' => $this->test_images['dest_webp'] . '_batch2',
				'format' => 'webp',
				'quality' => 75,
			),
		);

		// Track progress callback calls.
		$progress_calls = array();
		$progress_callback = function( $current, $total, $success ) use ( &$progress_calls ) {
			$progress_calls[] = array( $current, $total, $success );
		};

		$results = $this->converter->batch_convert( $conversions, $progress_callback );

		$this->assertIsArray( $results );
		$this->assertCount( 2, $results );

		// Check first result.
		$result1 = $results[0];
		$this->assertEquals( $this->test_images['source_jpg'], $result1['source'] );
		$this->assertEquals( $this->test_images['dest_webp'] . '_batch1', $result1['destination'] );
		$this->assertEquals( 'webp', $result1['format'] );
		$this->assertIsBool( $result1['success'] );

		// Check second result.
		$result2 = $results[1];
		$this->assertEquals( $this->test_images['source_png'], $result2['source'] );
		$this->assertEquals( $this->test_images['dest_webp'] . '_batch2', $result2['destination'] );
		$this->assertEquals( 'webp', $result2['format'] );
		$this->assertIsBool( $result2['success'] );

		// Check progress callback was called.
		$this->assertCount( 2, $progress_calls );
		$this->assertEquals( array( 1, 2, $result1['success'] ), $progress_calls[0] );
		$this->assertEquals( array( 2, 2, $result2['success'] ), $progress_calls[1] );

		// Clean up batch files.
		foreach ( array( '_batch1', '_batch2' ) as $suffix ) {
			$file = $this->test_images['dest_webp'] . $suffix;
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}
	}

	/**
	 * Test batch conversion with AVIF format (should fail).
	 */
	public function test_batch_convert_with_avif() {
		$conversions = array(
			array(
				'source' => $this->test_images['source_jpg'],
				'destination' => $this->test_images['dest_avif'],
				'format' => 'avif',
				'quality' => 75,
			),
		);

		$results = $this->converter->batch_convert( $conversions );

		$this->assertCount( 1, $results );
		$result = $results[0];
		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'avif', $result['format'] );
	}

	/**
	 * Test conversion when GD is not available.
	 */
	public function test_convert_when_not_available() {
		// Create a mock converter that reports as not available.
		$mock_converter = $this->getMockBuilder( 'GD_Converter' )
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
		$blue = imagecolorallocate( $image, 0, 0, 255 );
		imagefill( $image, 0, 0, $white );
		imagestring( $image, 5, 10, 40, 'GD', $blue );
		imagejpeg( $image, $this->test_images['source_jpg'], 90 );
		imagedestroy( $image );

		// Create a simple test PNG image with transparency.
		$image = imagecreatetruecolor( 100, 100 );
		$transparent = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
		$green = imagecolorallocate( $image, 0, 255, 0 );
		imagefill( $image, 0, 0, $transparent );
		imagestring( $image, 5, 10, 40, 'PNG', $green );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		imagepng( $image, $this->test_images['source_png'] );
		imagedestroy( $image );

		// Create a simple test GIF image.
		$image = imagecreate( 100, 100 );
		$white = imagecolorallocate( $image, 255, 255, 255 );
		$red = imagecolorallocate( $image, 255, 0, 0 );
		imagefill( $image, 0, 0, $white );
		imagestring( $image, 5, 10, 40, 'GIF', $red );
		imagegif( $image, $this->test_images['source_gif'] );
		imagedestroy( $image );
	}
}