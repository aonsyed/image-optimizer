<?php
/**
 * Image Servicer class
 *
 * @package ImageOptimizer
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Servicer class
 */
class Image_Servicer {

	/**
	 * Initialize the image servicer
	 */
	public static function init() {
		// Hook into image URL generation
		add_filter( 'wp_get_attachment_url', array( __CLASS__, 'serve_optimized_image' ), 10, 2 );
		add_filter( 'wp_get_attachment_image_src', array( __CLASS__, 'serve_optimized_image_src' ), 10, 4 );
		
		// Hook into image HTML generation
		add_filter( 'wp_get_attachment_image', array( __CLASS__, 'serve_optimized_image_html' ), 10, 5 );
		
		// Add picture element support
		add_filter( 'wp_get_attachment_image', array( __CLASS__, 'add_picture_element' ), 20, 5 );
	}

	/**
	 * Serve optimized image URL
	 *
	 * @param string $url Image URL.
	 * @param int    $attachment_id Attachment ID.
	 * @return string Optimized image URL.
	 */
	public static function serve_optimized_image( $url, $attachment_id ) {
		// Check if browser supports WebP/AVIF
		if ( ! self::browser_supports_modern_formats() ) {
			return $url;
		}

		// Check if image is optimized
		$is_optimized = get_post_meta( $attachment_id, '_image_optimizer_optimized', true );
		if ( ! $is_optimized ) {
			return $url;
		}

		// Get optimized image URL
		$optimized_url = self::get_optimized_image_url( $url, $attachment_id );
		
		return $optimized_url ? $optimized_url : $url;
	}

	/**
	 * Serve optimized image src
	 *
	 * @param array|false $image Image data.
	 * @param int         $attachment_id Attachment ID.
	 * @param string|array $size Image size.
	 * @param bool        $icon Whether the image should be treated as an icon.
	 * @return array|false Optimized image data.
	 */
	public static function serve_optimized_image_src( $image, $attachment_id, $size, $icon ) {
		// Check if browser supports WebP/AVIF
		if ( ! self::browser_supports_modern_formats() ) {
			return $image;
		}

		// Check if image is optimized
		$is_optimized = get_post_meta( $attachment_id, '_image_optimizer_optimized', true );
		if ( ! $is_optimized ) {
			return $image;
		}

		// Get optimized image URL
		$optimized_url = self::get_optimized_image_url( $image[0], $attachment_id );
		
		if ( $optimized_url ) {
			$image[0] = $optimized_url;
		}

		return $image;
	}

	/**
	 * Serve optimized image HTML
	 *
	 * @param string $html Image HTML.
	 * @param int    $attachment_id Attachment ID.
	 * @param string|array $size Image size.
	 * @param bool   $icon Whether the image should be treated as an icon.
	 * @param array  $attr Image attributes.
	 * @return string Optimized image HTML.
	 */
	public static function serve_optimized_image_html( $html, $attachment_id, $size, $icon, $attr ) {
		// Check if browser supports WebP/AVIF
		if ( ! self::browser_supports_modern_formats() ) {
			return $html;
		}

		// Check if image is optimized
		$is_optimized = get_post_meta( $attachment_id, '_image_optimizer_optimized', true );
		if ( ! $is_optimized ) {
			return $html;
		}

		// Get optimized image URL
		$original_url = wp_get_attachment_image_url( $attachment_id, $size, $icon );
		$optimized_url = self::get_optimized_image_url( $original_url, $attachment_id );
		
		if ( $optimized_url ) {
			// Replace the src attribute
			$html = preg_replace( '/src=["\']([^"\']+)["\']/', 'src="' . esc_url( $optimized_url ) . '"', $html );
		}

		return $html;
	}

	/**
	 * Add picture element for multiple formats
	 *
	 * @param string $html Image HTML.
	 * @param int    $attachment_id Attachment ID.
	 * @param string|array $size Image size.
	 * @param bool   $icon Whether the image should be treated as an icon.
	 * @param array  $attr Image attributes.
	 * @return string Picture element HTML.
	 */
	public static function add_picture_element( $html, $attachment_id, $size, $icon, $attr ) {
		// Check if picture element is enabled
		if ( ! get_option( 'image_optimizer_use_picture_element', false ) ) {
			return $html;
		}

		// Check if image is optimized
		$is_optimized = get_post_meta( $attachment_id, '_image_optimizer_optimized', true );
		if ( ! $is_optimized ) {
			return $html;
		}

		// Get image URLs
		$original_url = wp_get_attachment_image_url( $attachment_id, $size, $icon );
		$webp_url = self::get_webp_url( $original_url );
		$avif_url = self::get_avif_url( $original_url );

		// Build picture element
		$picture_html = '<picture>';
		
		// Add AVIF source if available
		if ( $avif_url && file_exists( self::url_to_path( $avif_url ) ) ) {
			$picture_html .= sprintf( '<source srcset="%s" type="image/avif">', esc_url( $avif_url ) );
		}
		
		// Add WebP source if available
		if ( $webp_url && file_exists( self::url_to_path( $webp_url ) ) ) {
			$picture_html .= sprintf( '<source srcset="%s" type="image/webp">', esc_url( $webp_url ) );
		}
		
		// Add original image as fallback
		$picture_html .= $html;
		$picture_html .= '</picture>';

		return $picture_html;
	}

	/**
	 * Get optimized image URL
	 *
	 * @param string $original_url Original image URL.
	 * @param int    $attachment_id Attachment ID.
	 * @return string|false Optimized image URL or false.
	 */
	private static function get_optimized_image_url( $original_url, $attachment_id ) {
		$format = get_option( 'image_optimizer_conversion_format', 'both' );
		
		// Prefer AVIF if available and supported
		if ( ( 'avif' === $format || 'both' === $format ) && self::browser_supports_avif() ) {
			$avif_url = self::get_avif_url( $original_url );
			if ( $avif_url && file_exists( self::url_to_path( $avif_url ) ) ) {
				return $avif_url;
			}
		}
		
		// Fall back to WebP if available and supported
		if ( ( 'webp' === $format || 'both' === $format ) && self::browser_supports_webp() ) {
			$webp_url = self::get_webp_url( $original_url );
			if ( $webp_url && file_exists( self::url_to_path( $webp_url ) ) ) {
				return $webp_url;
			}
		}
		
		return false;
	}

	/**
	 * Get WebP URL
	 *
	 * @param string $original_url Original image URL.
	 * @return string WebP URL.
	 */
	private static function get_webp_url( $original_url ) {
		return preg_replace( '/\.(jpe?g|png|gif)$/i', '.webp', $original_url );
	}

	/**
	 * Get AVIF URL
	 *
	 * @param string $original_url Original image URL.
	 * @return string AVIF URL.
	 */
	private static function get_avif_url( $original_url ) {
		return preg_replace( '/\.(jpe?g|png|gif)$/i', '.avif', $original_url );
	}

	/**
	 * Convert URL to file path
	 *
	 * @param string $url Image URL.
	 * @return string File path.
	 */
	private static function url_to_path( $url ) {
		$upload_dir = wp_upload_dir();
		$url_parts = parse_url( $url );
		$path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
		return $path;
	}

	/**
	 * Check if browser supports modern formats
	 *
	 * @return bool True if browser supports WebP or AVIF.
	 */
	private static function browser_supports_modern_formats() {
		return self::browser_supports_webp() || self::browser_supports_avif();
	}

	/**
	 * Check if browser supports WebP
	 *
	 * @return bool True if browser supports WebP.
	 */
	private static function browser_supports_webp() {
		if ( ! isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			return false;
		}
		
		return strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) !== false;
	}

	/**
	 * Check if browser supports AVIF
	 *
	 * @return bool True if browser supports AVIF.
	 */
	private static function browser_supports_avif() {
		if ( ! isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			return false;
		}
		
		return strpos( $_SERVER['HTTP_ACCEPT'], 'image/avif' ) !== false;
	}

	/**
	 * Get image format preference
	 *
	 * @return string Preferred format (avif, webp, original).
	 */
	private static function get_format_preference() {
		if ( self::browser_supports_avif() ) {
			return 'avif';
		} elseif ( self::browser_supports_webp() ) {
			return 'webp';
		}
		
		return 'original';
	}
}
