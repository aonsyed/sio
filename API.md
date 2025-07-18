# API Documentation - Smart Image Optimizer

This document provides comprehensive API documentation for developers who want to extend or integrate with the Smart Image Optimizer plugin.

## Table of Contents

- [Core Classes](#core-classes)
- [Action Hooks](#action-hooks)
- [Filter Hooks](#filter-hooks)
- [WP CLI Commands](#wp-cli-commands)
- [Database Schema](#database-schema)
- [JavaScript API](#javascript-api)
- [Examples](#examples)

## Core Classes

### SIO_Image_Processor

The main image processing engine.

#### Methods

##### `instance()`
```php
/**
 * Get singleton instance
 * @return SIO_Image_Processor
 */
public static function instance()
```

##### `convert_image()`
```php
/**
 * Convert image to specified formats
 * @param string $file_path Path to source image
 * @param array $options Processing options
 * @return array|WP_Error Results or error
 */
public function convert_image($file_path, $options = array())
```

**Options Array:**
```php
$options = array(
    'formats' => array('webp', 'avif'),
    'quality' => array(
        'webp' => 85,
        'avif' => 80
    ),
    'resize' => array(
        'enabled' => false,
        'max_width' => 1920,
        'max_height' => 1080
    ),
    'strip_metadata' => true,
    'compression_level' => 6
);
```

##### `supports_format()`
```php
/**
 * Check if format is supported
 * @param string $format Format to check (webp, avif)
 * @return bool
 */
public function supports_format($format)
```

##### `get_current_library()`
```php
/**
 * Get current image processing library
 * @return string|null Library name or null
 */
public function get_current_library()
```

### SIO_Settings_Manager

Manages plugin settings with multiple configuration sources.

#### Methods

##### `get_settings()`
```php
/**
 * Get all settings with priority: wp-config > CLI > UI
 * @return array Complete settings array
 */
public function get_settings()
```

##### `update_settings()`
```php
/**
 * Update settings from specific source
 * @param array $settings Settings to update
 * @param string $source Source: 'ui', 'cli', 'wp-config'
 * @return bool|WP_Error Success or error
 */
public function update_settings($settings, $source = 'ui')
```

##### `get_setting()`
```php
/**
 * Get single setting value
 * @param string $key Setting key
 * @param mixed $default Default value
 * @return mixed Setting value
 */
public function get_setting($key, $default = null)
```

### SIO_Batch_Processor

Handles batch processing operations.

#### Methods

##### `add_to_queue()`
```php
/**
 * Add image to processing queue
 * @param string $file_path Path to image file
 * @param array $options Processing options
 * @return int|WP_Error Queue item ID or error
 */
public function add_to_queue($file_path, $options = array())
```

##### `start_processing()`
```php
/**
 * Start batch processing
 * @return bool|WP_Error Success or error
 */
public function start_processing()
```

##### `get_queue_status()`
```php
/**
 * Get queue statistics
 * @return array Queue status counts
 */
public function get_queue_status()
```

##### `get_processing_status()`
```php
/**
 * Get current processing status
 * @return array Processing status information
 */
public function get_processing_status()
```

### SIO_Monitor

Handles logging and monitoring.

#### Methods

##### `log()`
```php
/**
 * Add log entry
 * @param string $message Log message
 * @param string $status Log status (success, error, warning, info)
 * @param string $action Action type
 * @param array $details Additional details
 * @return bool Success
 */
public function log($message, $status = 'info', $action = 'general', $details = array())
```

##### `get_statistics()`
```php
/**
 * Get processing statistics
 * @return array Statistics data
 */
public function get_statistics()
```

##### `get_system_info()`
```php
/**
 * Get system information
 * @return array System information
 */
public function get_system_info()
```

### SIO_Security

Handles security validation and sanitization.

#### Methods

##### `validate_file_path()`
```php
/**
 * Validate file path for security
 * @param string $file_path Path to validate
 * @return bool|WP_Error Valid or error
 */
public function validate_file_path($file_path)
```

##### `sanitize_settings()`
```php
/**
 * Sanitize settings array
 * @param array $settings Raw settings
 * @return array Sanitized settings
 */
public function sanitize_settings($settings)
```

##### `check_user_capability()`
```php
/**
 * Check user capability
 * @param string $capability Required capability
 * @return bool Has capability
 */
public function check_user_capability($capability)
```

## Action Hooks

### Image Processing Hooks

#### `sio_before_process_image`
Fired before processing an image.

```php
/**
 * @param string $file_path Path to image file
 * @param array $settings Processing settings
 */
do_action('sio_before_process_image', $file_path, $settings);
```

#### `sio_after_process_image`
Fired after successfully processing an image.

```php
/**
 * @param string $file_path Path to image file
 * @param array $results Processing results
 */
do_action('sio_after_process_image', $file_path, $results);
```

#### `sio_process_image_failed`
Fired when image processing fails.

```php
/**
 * @param string $file_path Path to image file
 * @param WP_Error $error Error object
 */
do_action('sio_process_image_failed', $file_path, $error);
```

### Batch Processing Hooks

#### `sio_before_batch_process`
Fired before starting batch processing.

```php
/**
 * @param array $queue_items Array of queue items
 */
do_action('sio_before_batch_process', $queue_items);
```

#### `sio_after_batch_process`
Fired after batch processing completes.

```php
/**
 * @param array $results Processing results
 */
do_action('sio_after_batch_process', $results);
```

#### `sio_batch_item_processed`
Fired after each item in batch is processed.

```php
/**
 * @param object $queue_item Queue item object
 * @param array $result Processing result
 */
do_action('sio_batch_item_processed', $queue_item, $result);
```

### Settings Hooks

#### `sio_settings_updated`
Fired when settings are updated.

```php
/**
 * @param array $old_settings Previous settings
 * @param array $new_settings New settings
 * @param string $source Update source (ui, cli, wp-config)
 */
do_action('sio_settings_updated', $old_settings, $new_settings, $source);
```

### Cleanup Hooks

#### `sio_before_cleanup`
Fired before cleanup operation.

```php
/**
 * @param array $files Files to be cleaned up
 */
do_action('sio_before_cleanup', $files);
```

#### `sio_after_cleanup`
Fired after cleanup operation.

```php
/**
 * @param array $results Cleanup results
 */
do_action('sio_after_cleanup', $results);
```

## Filter Hooks

### Processing Filters

#### `sio_processing_settings`
Modify processing settings for specific image.

```php
/**
 * @param array $settings Current settings
 * @param string $file_path Path to image file
 * @return array Modified settings
 */
$settings = apply_filters('sio_processing_settings', $settings, $file_path);
```

#### `sio_supported_file_types`
Modify supported file types.

```php
/**
 * @param array $types Supported MIME types
 * @return array Modified types
 */
$types = apply_filters('sio_supported_file_types', $types);
```

#### `sio_conversion_quality`
Modify conversion quality for specific format.

```php
/**
 * @param int $quality Current quality setting
 * @param string $format Target format (webp, avif)
 * @param string $file_path Source file path
 * @return int Modified quality
 */
$quality = apply_filters('sio_conversion_quality', $quality, $format, $file_path);
```

#### `sio_output_filename`
Modify output filename for converted images.

```php
/**
 * @param string $filename Generated filename
 * @param string $source_path Source file path
 * @param string $format Target format
 * @return string Modified filename
 */
$filename = apply_filters('sio_output_filename', $filename, $source_path, $format);
```

### Batch Processing Filters

#### `sio_batch_size`
Modify batch processing size.

```php
/**
 * @param int $batch_size Current batch size
 * @return int Modified batch size
 */
$batch_size = apply_filters('sio_batch_size', $batch_size);
```

#### `sio_queue_query_args`
Modify queue query arguments.

```php
/**
 * @param array $args Query arguments
 * @return array Modified arguments
 */
$args = apply_filters('sio_queue_query_args', $args);
```

### Cleanup Filters

#### `sio_should_cleanup_original`
Determine if original file should be cleaned up.

```php
/**
 * @param bool $should_cleanup Current cleanup decision
 * @param string $file_path Path to original file
 * @return bool Modified decision
 */
$should_cleanup = apply_filters('sio_should_cleanup_original', $should_cleanup, $file_path);
```

#### `sio_cleanup_file_age`
Modify file age threshold for cleanup.

```php
/**
 * @param int $days Days threshold
 * @param string $file_path File path
 * @return int Modified days
 */
$days = apply_filters('sio_cleanup_file_age', $days, $file_path);
```

### Admin Interface Filters

#### `sio_admin_menu_capability`
Modify required capability for admin menu.

```php
/**
 * @param string $capability Required capability
 * @return string Modified capability
 */
$capability = apply_filters('sio_admin_menu_capability', $capability);
```

#### `sio_settings_sections`
Modify settings sections in admin.

```php
/**
 * @param array $sections Settings sections
 * @return array Modified sections
 */
$sections = apply_filters('sio_settings_sections', $sections);
```

## WP CLI Commands

### Main Commands

#### `wp sio convert`
Convert single image or batch of images.

```bash
# Convert single image
wp sio convert /path/to/image.jpg

# Convert with options
wp sio convert /path/to/image.jpg --webp-quality=90 --enable-avif

# Convert multiple images
wp sio convert /path/to/images/*.jpg

# Dry run (test without converting)
wp sio convert /path/to/image.jpg --dry-run
```

#### `wp sio batch`
Manage batch processing operations.

```bash
# Add all images to queue
wp sio batch add-all

# Add specific images
wp sio batch add /path/to/images/*.jpg

# Start processing
wp sio batch start

# Start with progress bar
wp sio batch start --progress

# Stop processing
wp sio batch stop

# Clear queue
wp sio batch clear

# Get status
wp sio batch status
```

#### `wp sio settings`
Manage plugin settings.

```bash
# View all settings
wp sio settings list

# Get specific setting
wp sio settings get webp_quality

# Update settings
wp sio settings update --webp-quality=85 --enable-avif=true

# Reset to defaults
wp sio settings reset
```

#### `wp sio cleanup`
Cleanup operations.

```bash
# Cleanup old files
wp sio cleanup --days=30

# Cleanup logs
wp sio cleanup --logs

# Cleanup queue
wp sio cleanup --queue

# Dry run
wp sio cleanup --days=30 --dry-run
```

#### `wp sio info`
Display system information.

```bash
# Full system info
wp sio info

# Specific sections
wp sio info --section=php
wp sio info --section=libraries
wp sio info --section=wordpress
```

#### `wp sio logs`
View and manage logs.

```bash
# View recent logs
wp sio logs

# Filter by status
wp sio logs --status=error

# Filter by action
wp sio logs --action=image_processing

# Limit results
wp sio logs --limit=50

# Export to CSV
wp sio logs --format=csv > logs.csv
```

## Database Schema

### Queue Table (`{prefix}_sio_queue`)

```sql
CREATE TABLE {prefix}_sio_queue (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    file_path varchar(500) NOT NULL,
    status enum('pending','processing','completed','failed') DEFAULT 'pending',
    options text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    processed_at datetime DEFAULT NULL,
    error_message text,
    PRIMARY KEY (id),
    KEY status (status),
    KEY created_at (created_at)
);
```

### Logs Table (`{prefix}_sio_logs`)

```sql
CREATE TABLE {prefix}_sio_logs (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    message text NOT NULL,
    status enum('success','error','warning','info') DEFAULT 'info',
    action varchar(100) DEFAULT 'general',
    details longtext,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY status (status),
    KEY action (action),
    KEY created_at (created_at)
);
```

## JavaScript API

### Admin JavaScript Events

#### Custom Events

```javascript
// Processing started
$(document).trigger('sio:processing:started', [data]);

// Processing completed
$(document).trigger('sio:processing:completed', [results]);

// Settings updated
$(document).trigger('sio:settings:updated', [settings]);

// Queue updated
$(document).trigger('sio:queue:updated', [status]);
```

#### Event Listeners

```javascript
// Listen for processing events
$(document).on('sio:processing:started', function(event, data) {
    console.log('Processing started:', data);
});

$(document).on('sio:processing:completed', function(event, results) {
    console.log('Processing completed:', results);
});
```

### AJAX Endpoints

All AJAX endpoints require proper nonce verification.

#### `sio_save_settings`
Save plugin settings.

```javascript
$.post(ajaxurl, {
    action: 'sio_save_settings',
    nonce: sioAdmin.nonce,
    settings: settingsObject
}, function(response) {
    if (response.success) {
        // Settings saved
    }
});
```

#### `sio_start_batch`
Start batch processing.

```javascript
$.post(ajaxurl, {
    action: 'sio_start_batch',
    nonce: sioAdmin.nonce
}, function(response) {
    if (response.success) {
        // Batch started
    }
});
```

## Examples

### Custom Image Processing

```php
// Hook into before processing
add_action('sio_before_process_image', function($file_path, $settings) {
    // Custom logic before processing
    error_log("Processing: " . $file_path);
});

// Modify processing settings
add_filter('sio_processing_settings', function($settings, $file_path) {
    // Use higher quality for important images
    if (strpos($file_path, 'hero-images') !== false) {
        $settings['quality']['webp'] = 95;
        $settings['quality']['avif'] = 90;
    }
    return $settings;
}, 10, 2);

// Custom filename generation
add_filter('sio_output_filename', function($filename, $source_path, $format) {
    // Add timestamp to filename
    $info = pathinfo($filename);
    return $info['filename'] . '-' . time() . '.' . $format;
}, 10, 3);
```

### Custom Batch Processing

```php
// Add custom images to queue
function add_custom_images_to_queue() {
    $processor = SIO_Batch_Processor::instance();
    
    // Get images from custom directory
    $images = glob('/path/to/custom/images/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
    
    foreach ($images as $image) {
        $processor->add_to_queue($image, array(
            'formats' => array('webp'),
            'quality' => array('webp' => 90)
        ));
    }
}

// Hook into batch processing
add_action('sio_before_batch_process', function($queue_items) {
    // Send notification before batch starts
    wp_mail('admin@site.com', 'Batch Processing Started', 
        'Processing ' . count($queue_items) . ' images');
});
```

### Custom Settings Management

```php
// Add custom settings section
add_filter('sio_settings_sections', function($sections) {
    $sections['custom'] = array(
        'title' => 'Custom Settings',
        'fields' => array(
            'custom_watermark' => array(
                'type' => 'checkbox',
                'label' => 'Add Watermark',
                'default' => false
            )
        )
    );
    return $sections;
});

// Process custom settings
add_filter('sio_processing_settings', function($settings, $file_path) {
    $custom_settings = SIO_Settings_Manager::instance()->get_settings();
    
    if ($custom_settings['custom_watermark']) {
        $settings['watermark'] = true;
    }
    
    return $settings;
}, 10, 2);
```

### WP CLI Extension

```php
// Add custom WP CLI command
if (defined('WP_CLI') && WP_CLI) {
    class Custom_SIO_Commands {
        /**
         * Convert images with watermark
         */
        public function convert_with_watermark($args, $assoc_args) {
            $file_path = $args[0];
            
            // Custom processing logic
            $processor = SIO_Image_Processor::instance();
            $result = $processor->convert_image($file_path, array(
                'watermark' => true,
                'formats' => array('webp', 'avif')
            ));
            
            if (is_wp_error($result)) {
                WP_CLI::error($result->get_error_message());
            }
            
            WP_CLI::success('Image converted with watermark');
        }
    }
    
    WP_CLI::add_command('sio custom', 'Custom_SIO_Commands');
}
```

### JavaScript Integration

```javascript
// Custom admin interface integration
(function($) {
    'use strict';
    
    // Custom processing function
    function customProcessImage(imageId) {
        $.post(ajaxurl, {
            action: 'custom_process_image',
            image_id: imageId,
            nonce: sioAdmin.nonce
        }, function(response) {
            if (response.success) {
                // Update UI
                $('#image-' + imageId).addClass('processed');
            }
        });
    }
    
    // Listen for SIO events
    $(document).on('sio:processing:completed', function(event, results) {
        // Custom logic after processing
        console.log('Custom handler:', results);
    });
    
    // Initialize custom functionality
    $(document).ready(function() {
        $('.custom-process-btn').on('click', function() {
            var imageId = $(this).data('image-id');
            customProcessImage(imageId);
        });
    });
    
})(jQuery);
```

This API documentation provides comprehensive information for developers to extend and integrate with the Smart Image Optimizer plugin. All methods include proper error handling and follow WordPress coding standards.