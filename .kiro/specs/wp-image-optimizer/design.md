# Design Document

## Overview

The WordPress Image Optimizer plugin is designed as a modular, extensible system that automatically converts images to modern formats (WebP, AVIF) while maintaining WordPress coding standards and providing multiple interfaces for configuration and management. The plugin follows a service-oriented architecture with clear separation of concerns.

## Architecture

### Plugin Structure
```
wp-image-optimizer/
├── wp-image-optimizer.php          # Main plugin file
├── includes/
│   ├── class-wp-image-optimizer.php # Main plugin class
│   ├── class-image-converter.php    # Core conversion logic
│   ├── class-settings-manager.php   # Configuration management
│   ├── class-file-handler.php       # File system operations
│   ├── class-server-config.php      # Web server config generation
│   └── interfaces/
│       └── interface-converter.php  # Converter interface
├── admin/
│   ├── class-admin-interface.php    # Admin UI controller
│   ├── partials/
│   │   ├── settings-page.php        # Settings page template
│   │   └── dashboard.php            # Dashboard template
│   └── assets/
│       ├── css/admin.css
│       └── js/admin.js
├── cli/
│   └── class-cli-commands.php       # WP-CLI integration
├── converters/
│   ├── class-imagemagick-converter.php
│   ├── class-gd-converter.php
│   └── class-converter-factory.php
└── public/
    ├── class-image-handler.php      # Frontend image serving
    └── endpoint.php                 # Image conversion endpoint
```

### Core Components

#### 1. Image Converter Service
- **Purpose**: Handles image format conversion using available libraries
- **Responsibilities**: 
  - Detect available image processing libraries
  - Convert images to WebP/AVIF formats
  - Manage conversion quality and settings
  - Handle conversion errors gracefully

#### 2. Settings Manager
- **Purpose**: Manages plugin configuration from multiple sources
- **Responsibilities**:
  - Load settings from wp-config.php, database, and CLI
  - Apply configuration precedence rules
  - Validate and sanitize settings
  - Provide settings API for other components

#### 3. File Handler
- **Purpose**: Manages file system operations securely
- **Responsibilities**:
  - Generate converted image file paths
  - Check file existence and permissions
  - Handle file creation and cleanup
  - Manage upload directory structure

#### 4. Admin Interface
- **Purpose**: Provides WordPress admin dashboard integration
- **Responsibilities**:
  - Render settings pages
  - Display conversion statistics
  - Handle bulk operations
  - Generate server configuration snippets

#### 5. CLI Commands
- **Purpose**: Provides WP-CLI integration
- **Responsibilities**:
  - Execute batch conversions
  - Configure plugin settings
  - Display conversion status and statistics
  - Handle command-line arguments and validation

## Components and Interfaces

### Image Converter Interface
```php
interface Converter_Interface {
    public function is_available(): bool;
    public function convert_to_webp(string $source, string $destination, int $quality): bool;
    public function convert_to_avif(string $source, string $destination, int $quality): bool;
    public function get_supported_formats(): array;
}
```

### Settings Configuration Schema
```php
$default_settings = [
    'enabled' => true,
    'formats' => [
        'webp' => ['enabled' => true, 'quality' => 80],
        'avif' => ['enabled' => true, 'quality' => 75]
    ],
    'conversion_mode' => 'auto', // auto, manual, cli_only
    'preserve_originals' => true,
    'max_file_size' => 10485760, // 10MB
    'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/gif'],
    'server_config_type' => 'nginx' // nginx, apache, none
];
```

### Hook Integration Points
- `wp_handle_upload`: Trigger conversion on upload
- `wp_generate_attachment_metadata`: Add converted image metadata
- `wp_get_attachment_image_src`: Modify image URLs for modern formats
- `template_redirect`: Handle on-demand conversion requests

## Data Models

### Plugin Options Structure
```php
// Stored as single option: wp_image_optimizer_settings
[
    'version' => '1.0.0',
    'settings' => [...], // User settings
    'stats' => [
        'total_conversions' => 0,
        'space_saved' => 0,
        'last_batch_run' => null
    ],
    'server_capabilities' => [
        'imagemagick' => false,
        'gd' => false,
        'webp_support' => false,
        'avif_support' => false
    ]
]
```

### File Path Structure
```
/wp-content/uploads/
├── 2024/01/
│   ├── image.jpg
│   ├── image.webp          # Generated WebP
│   ├── image.avif          # Generated AVIF
│   └── image-150x150.jpg   # WordPress thumbnails
│       ├── image-150x150.webp
│       └── image-150x150.avif
```

### Conversion Metadata
- Stored as attachment metadata in `wp_postmeta`
- Tracks conversion status, file sizes, and generation timestamps
- Used for cleanup and regeneration operations

## Error Handling

### Error Categories
1. **System Errors**: Missing libraries, permission issues
2. **Conversion Errors**: Invalid files, unsupported formats
3. **Configuration Errors**: Invalid settings, missing requirements
4. **Runtime Errors**: File system issues, memory limits

### Error Handling Strategy
- Use WordPress `WP_Error` class for consistent error handling
- Log errors using WordPress debug logging system
- Provide user-friendly error messages in admin interface
- Implement graceful degradation when conversion fails
- Store error logs in WordPress debug.log when WP_DEBUG is enabled

### Fallback Mechanisms
- Fall back to original images when conversion fails
- Use GD when ImageMagick is unavailable
- Disable problematic formats automatically
- Provide manual retry mechanisms for failed conversions

## Testing Strategy

### Unit Testing
- Test each converter class independently
- Mock file system operations for consistent testing
- Test settings validation and precedence logic
- Verify error handling for various failure scenarios

### Integration Testing
- Test WordPress hook integration
- Verify admin interface functionality
- Test CLI command execution
- Validate server configuration generation

### Performance Testing
- Benchmark conversion speeds with different libraries
- Test memory usage with large images
- Verify caching effectiveness
- Test concurrent conversion handling

### Security Testing
- Validate file upload security
- Test permission checks
- Verify nonce implementation
- Test input sanitization

## Server Configuration Generation

### Nginx Configuration Template
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

### Apache .htaccess Template
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

## Security Considerations

### File Validation
- Validate file extensions and MIME types
- Check file headers to prevent malicious uploads
- Implement file size limits
- Sanitize file names and paths

### Permission Management
- Use WordPress capability system (`manage_options`)
- Implement nonce verification for all forms
- Validate user permissions for CLI commands
- Restrict file system access to upload directories

### Input Sanitization
- Sanitize all user inputs using WordPress functions
- Validate numeric inputs and ranges
- Escape output in admin templates
- Use prepared statements for any database queries

## Performance Optimization

### Caching Strategy
- Cache server capability detection results
- Use WordPress transients for temporary data
- Implement file existence caching
- Cache conversion settings to avoid repeated database queries

### Background Processing
- Use WordPress cron for batch conversions
- Implement queue system for large batch operations
- Provide progress tracking for long-running operations
- Allow cancellation of running batch jobs

### Memory Management
- Process images in chunks for large files
- Implement memory limit checks
- Clean up temporary files promptly
- Use streaming for large file operations where possible