<?php
/**
 * PHPUnit bootstrap file
 *
 * @package WP_Image_Optimizer
 */

// Require composer dependencies.
if ( file_exists( dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php' ) ) {
	require_once dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php';
}

// If we're running in WP's build directory, ensure that WP knows that, too.
if ( 'build' === getenv( 'LOCAL_DIR' ) ) {
	define( 'WP_RUN_CORE_TESTS', true );
}

// Determine the tests directory (from a WP dev checkout).
// Try the WP_TESTS_DIR environment variable first.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

// Next, try the WP_PHPUNIT composer package.
if ( ! $_tests_dir ) {
	$_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
}

// See if we're installed inside an existing WP dev instance.
if ( ! $_tests_dir ) {
	$_try_tests_dir = dirname( __FILE__ ) . '/../../../../../tests/phpunit';
	if ( file_exists( $_try_tests_dir . '/includes/functions.php' ) ) {
		$_tests_dir = $_try_tests_dir;
	}
}

// Fallback.
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/wp-image-optimizer.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Load mock classes
require_once dirname( __FILE__ ) . '/mocks/class-mock-converter.php';

/**
 * Helper function to create test images for testing
 *
 * @param string $path Path to create the image
 * @param string $type Image type (jpeg, png, gif)
 * @param int    $width Image width
 * @param int    $height Image height
 * @return bool True on success, false on failure
 */
function wp_image_optimizer_create_test_image( $path, $type = 'jpeg', $width = 100, $height = 100 ) {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return false;
	}
	
	$image = imagecreatetruecolor( $width, $height );
	
	// Create a colored rectangle
	$color = imagecolorallocate( $image, 255, 0, 0 );
	imagefilledrectangle( $image, 0, 0, $width, $height, $color );
	
	// Create directory if it doesn't exist
	$dir = dirname( $path );
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	
	// Save image based on type
	$result = false;
	switch ( $type ) {
		case 'jpeg':
			$result = imagejpeg( $image, $path, 90 );
			break;
		case 'png':
			$result = imagepng( $image, $path, 9 );
			break;
		case 'gif':
			$result = imagegif( $image, $path );
			break;
		case 'webp':
			if ( function_exists( 'imagewebp' ) ) {
				$result = imagewebp( $image, $path, 80 );
			}
			break;
	}
	
	// Clean up
	imagedestroy( $image );
	
	return $result;
}

/**
 * Helper function to create a mock attachment
 *
 * @param string $file_path Path to the file
 * @param string $mime_type MIME type of the file
 * @return int|WP_Error Attachment ID or WP_Error on failure
 */
function wp_image_optimizer_create_test_attachment( $file_path, $mime_type = 'image/jpeg' ) {
	// Check if file exists
	if ( ! file_exists( $file_path ) ) {
		return new WP_Error( 'file_not_found', 'File not found' );
	}
	
	// Get file data
	$file_name = basename( $file_path );
	$file_type = wp_check_filetype( $file_name, null );
	$attachment_title = preg_replace( '/\.[^.]+$/', '', $file_name );
	
	// Prepare attachment data
	$attachment = array(
		'guid'           => $file_path,
		'post_mime_type' => $mime_type,
		'post_title'     => $attachment_title,
		'post_content'   => '',
		'post_status'    => 'inherit',
	);
	
	// Insert attachment
	$attachment_id = wp_insert_attachment( $attachment, $file_path );
	
	// Generate metadata
	if ( ! is_wp_error( $attachment_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );
	}
	
	return $attachment_id;
}

/**
 * Helper function to clean up test images
 *
 * @param string $path Path to the image
 * @return bool True on success, false on failure
 */
function wp_image_optimizer_cleanup_test_image( $path ) {
	if ( file_exists( $path ) ) {
		return unlink( $path );
	}
	return true;
}