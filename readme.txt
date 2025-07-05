=== Image Optimizer ===
Contributors: Aon
Donate link: https://aon.sh
Tags: images, optimization, webp, avif, performance, speed
Requires at least: 5.6
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically convert and optimize images to WebP and AVIF formats for better website performance and faster loading times.

== Description ==

Image Optimizer is a powerful WordPress plugin that automatically converts your images to modern, efficient formats like WebP and AVIF. These formats provide significantly better compression than traditional JPEG and PNG files, resulting in faster page loads and reduced bandwidth usage.

**Key Features:**

* **Automatic Conversion**: Convert images to WebP and/or AVIF formats automatically on upload
* **Bulk Conversion**: Convert existing images in your media library with bulk actions
* **Smart Serving**: Automatically serve optimized images to browsers that support them
* **Quality Control**: Adjustable quality settings for both WebP and AVIF formats
* **Size Exclusion**: Exclude specific image sizes from conversion
* **Scheduled Processing**: Background processing for large image libraries
* **CLI Support**: Command-line tools for advanced users
* **Progress Tracking**: Monitor conversion progress and statistics
* **Cleanup Tools**: Remove optimized images when needed

**Performance Benefits:**

* **Smaller File Sizes**: WebP and AVIF typically provide 25-50% smaller file sizes
* **Faster Loading**: Reduced bandwidth usage means faster page loads
* **Better SEO**: Faster loading times improve search engine rankings
* **User Experience**: Faster image loading improves user engagement

**Browser Support:**

* **WebP**: Supported by all modern browsers (Chrome, Firefox, Safari, Edge)
* **AVIF**: Supported by Chrome, Firefox, and other modern browsers
* **Fallback**: Automatically serves original formats to unsupported browsers

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/image-optimizer` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to 'Settings' > 'Image Optimizer' to configure the plugin.
4. The plugin will automatically start converting new images on upload.

**Requirements:**

* PHP 7.4 or higher
* GD extension (required for WebP conversion)
* Imagick extension (optional, for AVIF conversion)
* WordPress 5.6 or higher

== Frequently Asked Questions ==

= What image formats are supported? =

The plugin supports converting JPEG, PNG, and GIF images to WebP and AVIF formats.

= Do I need to install additional software? =

No additional software is required. The plugin uses PHP's built-in GD extension for WebP conversion. For AVIF support, the Imagick extension is recommended but optional.

= Will this affect my existing images? =

No, the plugin creates new optimized versions alongside your original images. You can choose to remove originals after conversion, but this is optional and can be disabled.

= What happens if a browser doesn't support WebP/AVIF? =

The plugin automatically detects browser support and serves the appropriate format. Browsers that don't support modern formats will receive the original image.

= Can I convert images in bulk? =

Yes! You can use the bulk actions in the Media Library or the built-in scheduler to convert existing images. The plugin also includes WP-CLI commands for advanced users.

= How much space will I save? =

Typically, WebP provides 25-35% smaller file sizes, while AVIF can provide 50% or more compression. Actual savings depend on your image content and quality settings.

= Is this plugin compatible with other image optimization plugins? =

Yes, but we recommend using only one image optimization plugin at a time to avoid conflicts.

= Can I exclude certain image sizes from conversion? =

Yes, you can exclude specific image sizes from conversion in the plugin settings.

= Does the plugin work with multisite installations? =

Yes, the plugin is fully compatible with WordPress multisite installations.

= How do I monitor conversion progress? =

The plugin provides detailed statistics in the admin interface and media library. You can also use WP-CLI commands to check status.

== Screenshots ==

1. Settings page with quality controls and conversion options
2. Media library with optimization status and bulk actions
3. System status showing WebP and AVIF support
4. Conversion progress and statistics
5. WP-CLI commands for advanced users

== Changelog ==

= 1.0.0 =
* Complete rewrite with improved architecture
* Enhanced security with proper nonce verification
* Better error handling and logging
* Improved admin interface with modern design
* Enhanced WP-CLI commands with more options
* Better browser detection and format serving
* Progress tracking and statistics
* Bulk conversion improvements
* Code quality improvements and WordPress standards compliance

= 0.69 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
This is a major update with significant improvements. Please backup your site before upgrading and test thoroughly.

== WP-CLI Commands ==

The plugin includes several WP-CLI commands for advanced users:

**Convert Images:**
```bash
wp image-optimizer convert [--year=<year>] [--month=<month>] [--format=<format>] [--quality=<quality>] [--sizes=<sizes>] [--dry-run] [--limit=<limit>]
```

**Check Status:**
```bash
wp image-optimizer status [--format=<format>]
```

**Cleanup Optimized Images:**
```bash
wp image-optimizer cleanup [--dry-run]
```

**Examples:**
```bash
# Convert all unconverted images
wp image-optimizer convert

# Convert images from 2023 with WebP only
wp image-optimizer convert --year=2023 --format=webp

# Check conversion status
wp image-optimizer status

# Clean up optimized images (dry run first)
wp image-optimizer cleanup --dry-run
```

== Support ==

For support, feature requests, or bug reports, please visit our GitHub repository or contact us through our website.

**Documentation:** [Coming Soon]
**GitHub:** [Repository Link]
**Website:** https://aon.sh

== Credits ==

This plugin was developed with performance and user experience in mind. Special thanks to the WordPress community and contributors who made this possible.

== License ==

This plugin is licensed under the GPL v2 or later.

== Privacy ==

This plugin does not collect, store, or transmit any personal data. All image processing is done locally on your server.
