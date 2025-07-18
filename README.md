# WP Image Optimizer

A WordPress plugin that automatically converts images to modern formats (WebP, AVIF) using server-side ImageMagick or GD libraries for improved website performance.

## Description

WP Image Optimizer is a powerful WordPress plugin designed to automatically convert your images to modern formats like WebP and AVIF. These formats provide superior compression and quality compared to traditional formats like JPEG and PNG, resulting in faster page loads and improved user experience.

### Key Features

- **Automatic Image Conversion**: Automatically converts uploaded images to WebP and AVIF formats
- **Browser Detection**: Serves the most optimized format based on browser capabilities
- **Multiple Conversion Methods**: Uses ImageMagick (preferred) or GD library based on server capabilities
- **Flexible Configuration**: Configure via WordPress admin, WP-CLI, or wp-config.php
- **Server Integration**: Generates Nginx and Apache configuration for direct image serving
- **Performance Optimization**: Minimal database usage and optimized for high-traffic sites
- **Comprehensive Error Handling**: Detailed error logging and recovery mechanisms
- **Batch Processing**: Convert existing images in batches with progress tracking
- **WP-CLI Support**: Manage conversions and settings via command line

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- ImageMagick extension (recommended) or GD library (minimum)
- Write permissions on the uploads directory

## Installation

1. Upload the `wp-image-optimizer` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin settings under 'Settings > WP Image Optimizer'

## Configuration

### WordPress Admin

Navigate to 'Settings > WP Image Optimizer' to configure:

- Enable/disable WebP and AVIF conversion
- Set quality levels for each format
- View server capabilities
- Generate server configuration snippets
- Configure batch processing options

### WP-CLI

The plugin provides several WP-CLI commands:

```
# Convert all images
wp image-optimizer convert all

# Convert a specific image
wp image-optimizer convert 123

# Update plugin settings
wp image-optimizer settings update --webp-quality=85 --avif-quality=70

# View plugin statistics
wp image-optimizer stats
```

### wp-config.php

You can define constants in your wp-config.php file to override settings:

```php
// Enable or disable the plugin
define('WP_IMAGE_OPTIMIZER_ENABLED', true);

// Configure format settings
define('WP_IMAGE_OPTIMIZER_WEBP_ENABLED', true);
define('WP_IMAGE_OPTIMIZER_WEBP_QUALITY', 80);
define('WP_IMAGE_OPTIMIZER_AVIF_ENABLED', true);
define('WP_IMAGE_OPTIMIZER_AVIF_QUALITY', 75);
```

## Server Configuration

For optimal performance, configure your web server to serve converted images directly:

### Nginx Configuration

```nginx
# WebP/AVIF serving with fallback
location ~* \.(jpe?g|png|gif)$ {
    set $webp_suffix "";
    set $avif_suffix "";
    
    if ($http_accept ~* "image/avif") {
        set $avif_suffix ".avif";
    }
    if ($http_accept ~* "image/webp") {
        set $webp_suffix ".webp";
    }
    
    # Try AVIF first, then WebP, then original
    try_files $uri$avif_suffix $uri$webp_suffix $uri @wp_image_optimizer;
    
    expires 1y;
    add_header Cache-Control "public, immutable";
    add_header Vary "Accept";
}

location @wp_image_optimizer {
    rewrite ^(.+)$ /wp-content/plugins/wp-image-optimizer/public/endpoint.php?file=$1 last;
}
```

### Apache Configuration

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Check for AVIF support
    RewriteCond %{HTTP_ACCEPT} image/avif
    RewriteCond %{REQUEST_FILENAME} \.(jpe?g|png|gif)$
    RewriteCond %{REQUEST_FILENAME}\.avif -f
    RewriteRule ^(.+)$ $1.avif [T=image/avif,E=accept:1,L]
    
    # Check for WebP support
    RewriteCond %{HTTP_ACCEPT} image/webp
    RewriteCond %{REQUEST_FILENAME} \.(jpe?g|png|gif)$
    RewriteCond %{REQUEST_FILENAME}\.webp -f
    RewriteRule ^(.+)$ $1.webp [T=image/webp,E=accept:1,L]
    
    # Fallback to PHP handler for on-demand conversion
    RewriteCond %{REQUEST_FILENAME} \.(jpe?g|png|gif)$
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.+)$ /wp-content/plugins/wp-image-optimizer/public/endpoint.php?file=$1 [QSA,L]
</IfModule>

<IfModule mod_headers.c>
    Header append Vary Accept env=accept
</IfModule>

<IfModule mod_expires.c>
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/avif "access plus 1 year"
</IfModule>
```

## Frequently Asked Questions

### Which image formats are supported for conversion?

The plugin can convert JPEG, PNG, and GIF images to WebP and AVIF formats.

### Will this plugin work without ImageMagick?

Yes, the plugin will fall back to using the GD library if ImageMagick is not available. However, ImageMagick generally provides better quality and performance.

### How can I check if my server supports WebP or AVIF conversion?

Navigate to the plugin settings page, and you'll see a "Server Capabilities" section that shows which libraries and formats are supported on your server.

### Will existing images be converted?

The plugin only automatically converts newly uploaded images. For existing images, you can use the bulk conversion tool in the admin dashboard or the WP-CLI commands.

### How much disk space will the converted images use?

While the plugin creates additional image files, WebP and AVIF formats typically result in smaller file sizes compared to the original formats. The plugin includes statistics to show how much space you're saving overall.

## Changelog

### 1.0.0
- Initial release

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by Your Name