# Smart Image Optimizer

A comprehensive WordPress plugin for automatic image optimization with WebP and AVIF conversion, batch processing, and advanced monitoring capabilities.

## Features

### Core Functionality
- **Automatic Image Conversion**: Convert JPEG, PNG, and GIF images to WebP and AVIF formats
- **Multiple Processing Libraries**: Supports both ImageMagick and GD libraries with automatic detection
- **Batch Processing**: Process existing images in bulk with queue management
- **Background Processing**: Automatic processing using WordPress Cron
- **Real-time Monitoring**: Track processing status and view detailed logs

### Advanced Features
- **Quality Control**: Configurable quality settings for WebP and AVIF formats
- **Image Resizing**: Optional resizing with maximum width/height limits
- **Metadata Stripping**: Remove EXIF and other metadata to reduce file size
- **Format Fallbacks**: Automatic fallback to original format if conversion fails
- **Cleanup System**: Automatic cleanup of original files after specified days
- **Security First**: Comprehensive input validation and user capability checks

### Management Options
- **Multiple Configuration Sources**: Settings via WordPress admin, WP CLI, or wp-config.php
- **WP CLI Integration**: Complete command-line interface for all operations
- **Comprehensive Logging**: Detailed activity logs with filtering and export
- **System Information**: View server capabilities and plugin status
- **Statistics Tracking**: Monitor conversion rates and performance metrics

### Server Configuration & Automatic Serving
- **Apache .htaccess Rules**: Automatic generation of server rules for optimal image serving
- **Nginx Configuration**: Generate Nginx configuration snippets for manual server setup
- **Browser Detection**: Serve WebP/AVIF images based on browser support via Accept headers
- **On-the-fly Conversion**: WordPress fallback for dynamic image conversion when needed
- **Hybrid Approach**: Combines server-level performance with WordPress compatibility
- **Cache Control**: Configurable cache duration for converted images (default: 24 hours)

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- One of the following image processing libraries:
  - ImageMagick (recommended)
  - GD Library
- Sufficient server memory for image processing
- Write permissions for WordPress uploads directory

## Installation

### Manual Installation

1. Download the plugin files
2. Upload the `smart-image-optimizer` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure settings via **Image Optimizer > Settings**

### WP CLI Installation

```bash
wp plugin install smart-image-optimizer --activate
```

## Configuration

### WordPress Admin Interface

Navigate to **Image Optimizer** in your WordPress admin menu to access:

- **Dashboard**: Overview of processing statistics and system status
- **Settings**: Configure all plugin options with tabbed interface
- **Batch Processing**: Manage bulk image processing operations
- **Monitor**: View logs and activity history
- **System Info**: Check server capabilities and plugin status

### WP CLI Commands

The plugin provides comprehensive WP CLI integration:

```bash
# Convert a single image
wp sio convert /path/to/image.jpg

# Start batch processing
wp sio batch start

# Check processing status
wp sio status

# View system information
wp sio info

# Update settings
wp sio settings update --webp-quality=85 --enable-avif=true

# Cleanup old files
wp sio cleanup --days=30

# Server configuration commands
wp sio server generate-htaccess    # Generate .htaccess rules
wp sio server view-htaccess        # View current .htaccess rules
wp sio server generate-nginx       # Generate Nginx configuration
wp sio server view-nginx           # View Nginx configuration
wp sio server status               # Show server configuration status
```

### wp-config.php Configuration

You can define settings in your `wp-config.php` file for environment-specific configuration:

```php
// Enable/disable features
define('SIO_AUTO_PROCESS', true);
define('SIO_ENABLE_WEBP', true);
define('SIO_ENABLE_AVIF', true);
define('SIO_ENABLE_RESIZE', false);

// Quality settings
define('SIO_WEBP_QUALITY', 85);
define('SIO_AVIF_QUALITY', 80);

// Processing settings
define('SIO_BATCH_SIZE', 10);
define('SIO_MAX_EXECUTION_TIME', 300);

// Advanced settings
define('SIO_COMPRESSION_LEVEL', 6);
define('SIO_STRIP_METADATA', true);
define('SIO_CLEANUP_ORIGINALS', false);
define('SIO_CLEANUP_AFTER_DAYS', 30);

// Server configuration settings
define('SIO_ENABLE_AUTO_SERVE', true);
define('SIO_AUTO_HTACCESS', true);
define('SIO_FALLBACK_CONVERSION', true);
define('SIO_CACHE_DURATION', 86400); // 24 hours in seconds

// Logging
define('SIO_ENABLE_LOGGING', true);
define('SIO_LOG_RETENTION_DAYS', 30);
```

## Usage

### Automatic Processing

Once configured, the plugin can automatically process images as they are uploaded:

1. Enable **Auto Process** in settings
2. Choose between immediate processing or batch mode
3. New uploads will be automatically converted based on your settings

### Manual Batch Processing

Process existing images in your media library:

1. Go to **Image Optimizer > Batch Processing**
2. Click **Add All Images** to queue existing images
3. Click **Start Batch Processing** to begin
4. Monitor progress in real-time

### WP CLI Batch Processing

For server environments, use WP CLI for efficient batch processing:

```bash
# Add all images to queue
wp sio batch add-all

# Start processing with progress bar
wp sio batch start --progress

# Process specific images
wp sio convert /wp-content/uploads/2023/01/*.jpg
```

## Settings Reference

### General Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Auto Process | Automatically process uploaded images | `false` |
| Batch Mode | Use batch processing for uploads | `false` |
| Enable Logging | Enable activity logging | `true` |

### Format Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Enable WebP | Convert images to WebP format | `true` |
| WebP Quality | Quality for WebP images (1-100) | `85` |
| Enable AVIF | Convert images to AVIF format | `false` |
| AVIF Quality | Quality for AVIF images (1-100) | `80` |

### Processing Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Enable Resize | Enable image resizing | `false` |
| Max Width | Maximum image width in pixels | `1920` |
| Max Height | Maximum image height in pixels | `1080` |
| Batch Size | Images to process per batch | `10` |

### Advanced Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Compression Level | Compression level (0-9) | `6` |
| Strip Metadata | Remove image metadata | `true` |
| Cleanup Originals | Auto-cleanup original files | `false` |
| Cleanup After Days | Days before cleanup | `30` |
| Max Execution Time | Maximum execution time (seconds) | `300` |
| Log Retention | Days to keep log entries | `30` |

### Server Configuration Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Enable Auto Serve | Automatically serve WebP/AVIF images | `false` |
| Auto .htaccess | Automatically update .htaccess rules | `false` |
| Fallback Conversion | Enable on-the-fly conversion fallback | `true` |
| Cache Duration | Cache duration for converted images (seconds) | `86400` |

## Monitoring and Logs

### Dashboard Statistics

The main dashboard provides:
- Total images processed
- WebP and AVIF conversion counts
- Current queue status
- System status indicators

### Activity Logs

Comprehensive logging includes:
- Processing success/failure events
- Settings changes
- Batch processing activities
- System errors and warnings
- Performance metrics

### Log Management

- **Filtering**: Filter logs by status, action, and date range
- **Export**: Export logs to CSV format
- **Cleanup**: Automatic log cleanup based on retention settings

## Troubleshooting

### Common Issues

**No image library available**
- Install ImageMagick or ensure GD is enabled
- Check PHP extensions: `php -m | grep -E "(imagick|gd)"`

**WebP/AVIF not supported**
- Verify library support in **System Info** page
- Update ImageMagick or PHP version if needed

**Memory limit errors**
- Increase PHP memory limit
- Reduce batch size in settings
- Process smaller images first

**Permission errors**
- Ensure write permissions on uploads directory
- Check WordPress file permissions

### Debug Mode

Enable WordPress debug mode for detailed error information:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### WP CLI Diagnostics

```bash
# Check system information
wp sio info

# Test image conversion
wp sio convert /path/to/test-image.jpg --dry-run

# View recent logs
wp sio logs --limit=10
```

## Performance Considerations

### Server Resources

- **Memory**: Ensure adequate PHP memory limit (512MB+ recommended)
- **Execution Time**: Set appropriate max execution time
- **Disk Space**: Monitor available disk space for converted images

### Optimization Tips

1. **Batch Size**: Start with smaller batch sizes and increase gradually
2. **Processing Time**: Use background processing for large batches
3. **Quality Settings**: Balance quality vs. file size for your needs
4. **Cleanup**: Enable automatic cleanup to manage disk space

## Security

The plugin implements comprehensive security measures:

- **Input Validation**: All user inputs are validated and sanitized
- **Capability Checks**: Proper WordPress capability verification
- **Nonce Verification**: CSRF protection for all AJAX requests
- **File Path Validation**: Prevention of directory traversal attacks
- **Rate Limiting**: Protection against abuse of batch operations

## Hooks and Filters

### Action Hooks

```php
// Before image processing
do_action('sio_before_process_image', $file_path, $settings);

// After successful processing
do_action('sio_after_process_image', $file_path, $results);

// Before batch processing starts
do_action('sio_before_batch_process', $queue_items);

// After batch processing completes
do_action('sio_after_batch_process', $results);
```

### Filter Hooks

```php
// Modify processing settings
$settings = apply_filters('sio_processing_settings', $settings, $file_path);

// Modify supported file types
$types = apply_filters('sio_supported_file_types', $types);

// Modify conversion quality
$quality = apply_filters('sio_conversion_quality', $quality, $format, $file_path);

// Modify cleanup behavior
$should_cleanup = apply_filters('sio_should_cleanup_original', $should_cleanup, $file_path);
```

## Contributing

We welcome contributions to improve the Smart Image Optimizer plugin:

1. Fork the repository
2. Create a feature branch
3. Make your changes with proper documentation
4. Add tests for new functionality
5. Submit a pull request

### Development Setup

```bash
# Clone the repository
git clone https://github.com/your-repo/smart-image-optimizer.git

# Install development dependencies
composer install --dev

# Run tests
composer test

# Run code standards check
composer phpcs
```

## Changelog

### Version 1.0.0
- Initial release
- WebP and AVIF conversion support
- Batch processing with queue management
- WP CLI integration
- Comprehensive admin interface
- Activity logging and monitoring
- Multiple configuration options
- Security-first implementation

## Support

For support and questions:

- **Documentation**: Check this README and inline code documentation
- **System Info**: Use the System Info page to diagnose issues
- **Logs**: Check the Monitor page for error details
- **WP CLI**: Use `wp sio info` for system diagnostics

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## Credits

Developed with ❤️ for the WordPress community.

Special thanks to:
- WordPress core team for the excellent plugin architecture
- ImageMagick and GD library developers
- The open-source community for inspiration and feedback