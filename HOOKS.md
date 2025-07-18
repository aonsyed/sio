# Smart Image Optimizer - Hooks and Filters Reference

This document provides a comprehensive reference for all action hooks and filters available in the Smart Image Optimizer plugin. These hooks allow developers to extend and customize the plugin's functionality.

## Table of Contents

- [Core Hooks](#core-hooks)
- [Image Processing Hooks](#image-processing-hooks)
- [Batch Processing Hooks](#batch-processing-hooks)
- [Settings Hooks](#settings-hooks)
- [Admin Interface Hooks](#admin-interface-hooks)
- [Server Configuration Hooks](#server-configuration-hooks)
- [Performance Hooks](#performance-hooks)
- [Security Hooks](#security-hooks)
- [Utility Hooks](#utility-hooks)
- [Usage Examples](#usage-examples)

## Core Hooks

### Plugin Lifecycle Actions

#### `sio_plugin_activated`
**Type:** Action  
**Description:** Fired when the plugin is activated  
**Parameters:** None  

#### `sio_plugin_deactivated`
**Type:** Action  
**Description:** Fired when the plugin is deactivated  
**Parameters:** None  

#### `sio_plugin_uninstalled`
**Type:** Action  
**Description:** Fired when the plugin is uninstalled  
**Parameters:** None  

#### `sio_before_init`
**Type:** Action  
**Description:** Fired before plugin initialization  
**Parameters:** None  

#### `sio_after_init`
**Type:** Action  
**Description:** Fired after plugin initialization  
**Parameters:** None  

#### `sio_components_loaded`
**Type:** Action  
**Description:** Fired when all components are loaded  
**Parameters:** None  

### Core Filters

#### `sio_plugin_settings`
**Type:** Filter  
**Description:** Filter plugin settings array  
**Parameters:**
- `$settings` (array): Plugin settings array

#### `sio_plugin_capabilities`
**Type:** Filter  
**Description:** Filter required user capabilities  
**Parameters:**
- `$capabilities` (array): Required capabilities array

#### `sio_plugin_version`
**Type:** Filter  
**Description:** Filter plugin version string  
**Parameters:**
- `$version` (string): Plugin version

## Image Processing Hooks

### Processing Lifecycle Actions

#### `sio_before_process_image`
**Type:** Action  
**Description:** Fired before processing an image  
**Parameters:**
- `$file_path` (string): Path to the image file

#### `sio_after_process_image`
**Type:** Action  
**Description:** Fired after processing an image  
**Parameters:**
- `$file_path` (string): Path to the image file

#### `sio_image_processing_failed`
**Type:** Action  
**Description:** Fired when image processing fails  
**Parameters:**
- `$file_path` (string): Path to the image file
- `$error` (WP_Error): Error object

#### `sio_image_processing_completed`
**Type:** Action  
**Description:** Fired when image processing completes successfully  
**Parameters:**
- `$file_path` (string): Path to the image file
- `$results` (array): Processing results

### Format Conversion Actions

#### `sio_before_webp_conversion`
**Type:** Action  
**Description:** Fired before WebP conversion  
**Parameters:**
- `$file_path` (string): Source file path
- `$output_path` (string): Output file path

#### `sio_after_webp_conversion`
**Type:** Action  
**Description:** Fired after WebP conversion  
**Parameters:**
- `$file_path` (string): Source file path
- `$output_path` (string): Output file path
- `$success` (bool): Whether conversion was successful

#### `sio_before_avif_conversion`
**Type:** Action  
**Description:** Fired before AVIF conversion  
**Parameters:**
- `$file_path` (string): Source file path
- `$output_path` (string): Output file path

#### `sio_after_avif_conversion`
**Type:** Action  
**Description:** Fired after AVIF conversion  
**Parameters:**
- `$file_path` (string): Source file path
- `$output_path` (string): Output file path
- `$success` (bool): Whether conversion was successful

### Processing Filters

#### `sio_processing_settings`
**Type:** Filter  
**Description:** Filter image processing settings  
**Parameters:**
- `$settings` (array): Processing settings

#### `sio_supported_formats`
**Type:** Filter  
**Description:** Filter supported image formats  
**Parameters:**
- `$formats` (array): Supported formats array

#### `sio_webp_quality`
**Type:** Filter  
**Description:** Filter WebP quality setting  
**Parameters:**
- `$quality` (int): WebP quality (1-100)
- `$file_path` (string): Image file path

#### `sio_avif_quality`
**Type:** Filter  
**Description:** Filter AVIF quality setting  
**Parameters:**
- `$quality` (int): AVIF quality (1-100)
- `$file_path` (string): Image file path

#### `sio_compression_level`
**Type:** Filter  
**Description:** Filter compression level  
**Parameters:**
- `$level` (int): Compression level (1-9)
- `$format` (string): Output format

#### `sio_resize_dimensions`
**Type:** Filter  
**Description:** Filter resize dimensions  
**Parameters:**
- `$dimensions` (array): Array with 'width' and 'height' keys
- `$file_path` (string): Image file path

#### `sio_output_path`
**Type:** Filter  
**Description:** Filter output file path  
**Parameters:**
- `$output_path` (string): Generated output path
- `$input_path` (string): Input file path
- `$format` (string): Output format

## Batch Processing Hooks

### Batch Lifecycle Actions

#### `sio_before_batch_process`
**Type:** Action  
**Description:** Fired before batch processing starts  
**Parameters:**
- `$items` (array): Array of items to process

#### `sio_after_batch_process`
**Type:** Action  
**Description:** Fired after batch processing completes  
**Parameters:**
- `$results` (array): Processing results

#### `sio_batch_item_processed`
**Type:** Action  
**Description:** Fired when a batch item is processed  
**Parameters:**
- `$item_id` (int): Queue item ID
- `$attachment_id` (int): Attachment ID
- `$success` (bool): Whether processing was successful

#### `sio_batch_item_failed`
**Type:** Action  
**Description:** Fired when a batch item fails  
**Parameters:**
- `$item_id` (int): Queue item ID
- `$attachment_id` (int): Attachment ID
- `$error` (string): Error message

### Queue Management Actions

#### `sio_item_added_to_queue`
**Type:** Action  
**Description:** Fired when an item is added to the queue  
**Parameters:**
- `$queue_id` (int): Queue item ID
- `$attachment_id` (int): Attachment ID

#### `sio_item_removed_from_queue`
**Type:** Action  
**Description:** Fired when an item is removed from the queue  
**Parameters:**
- `$attachment_id` (int): Attachment ID

#### `sio_queue_cleared`
**Type:** Action  
**Description:** Fired when the queue is cleared  
**Parameters:**
- `$status` (string): Status of cleared items (optional)

### Batch Processing Filters

#### `sio_batch_size`
**Type:** Filter  
**Description:** Filter batch processing size  
**Parameters:**
- `$batch_size` (int): Number of items to process in batch

#### `sio_max_execution_time`
**Type:** Filter  
**Description:** Filter maximum execution time  
**Parameters:**
- `$time` (int): Maximum execution time in seconds

#### `sio_batch_priority`
**Type:** Filter  
**Description:** Filter batch item priority  
**Parameters:**
- `$priority` (int): Item priority
- `$attachment_id` (int): Attachment ID

#### `sio_retry_attempts`
**Type:** Filter  
**Description:** Filter number of retry attempts  
**Parameters:**
- `$attempts` (int): Number of retry attempts

## Settings Hooks

### Settings Lifecycle Actions

#### `sio_settings_loaded`
**Type:** Action  
**Description:** Fired when settings are loaded  
**Parameters:**
- `$settings` (array): Loaded settings

#### `sio_settings_saved`
**Type:** Action  
**Description:** Fired when settings are saved  
**Parameters:**
- `$settings` (array): Saved settings
- `$old_settings` (array): Previous settings

#### `sio_settings_reset`
**Type:** Action  
**Description:** Fired when settings are reset  
**Parameters:** None

#### `sio_setting_changed`
**Type:** Action  
**Description:** Fired when a specific setting changes  
**Parameters:**
- `$setting_name` (string): Name of the changed setting
- `$new_value` (mixed): New value
- `$old_value` (mixed): Previous value

### Settings Filters

#### `sio_validate_settings`
**Type:** Filter  
**Description:** Filter and validate settings  
**Parameters:**
- `$settings` (array): Settings to validate

#### `sio_sanitize_setting`
**Type:** Filter  
**Description:** Filter and sanitize individual setting  
**Parameters:**
- `$value` (mixed): Setting value
- `$setting_name` (string): Setting name

#### `sio_default_settings`
**Type:** Filter  
**Description:** Filter default settings  
**Parameters:**
- `$defaults` (array): Default settings array

## Admin Interface Hooks

### Admin Page Actions

#### `sio_admin_page_loaded`
**Type:** Action  
**Description:** Fired when admin page is loaded  
**Parameters:**
- `$page` (string): Page slug

#### `sio_before_admin_render`
**Type:** Action  
**Description:** Fired before admin page render  
**Parameters:**
- `$page` (string): Page slug

#### `sio_after_admin_render`
**Type:** Action  
**Description:** Fired after admin page render  
**Parameters:**
- `$page` (string): Page slug

### Admin Filters

#### `sio_admin_menu_items`
**Type:** Filter  
**Description:** Filter admin menu items  
**Parameters:**
- `$menu_items` (array): Menu items array

#### `sio_admin_form_fields`
**Type:** Filter  
**Description:** Filter admin form fields  
**Parameters:**
- `$fields` (array): Form fields array
- `$section` (string): Form section

## Server Configuration Hooks

### Server Config Actions

#### `sio_before_generate_htaccess`
**Type:** Action  
**Description:** Fired before .htaccess generation  
**Parameters:**
- `$settings` (array): Server configuration settings

#### `sio_after_generate_htaccess`
**Type:** Action  
**Description:** Fired after .htaccess generation  
**Parameters:**
- `$rules` (string): Generated .htaccess rules

#### `sio_before_serve_image`
**Type:** Action  
**Description:** Fired before serving converted image  
**Parameters:**
- `$file_path` (string): Image file path
- `$format` (string): Requested format

#### `sio_after_serve_image`
**Type:** Action  
**Description:** Fired after serving converted image  
**Parameters:**
- `$file_path` (string): Image file path
- `$format` (string): Served format

### Server Config Filters

#### `sio_htaccess_rules`
**Type:** Filter  
**Description:** Filter Apache .htaccess rules  
**Parameters:**
- `$rules` (string): Generated rules

#### `sio_nginx_rules`
**Type:** Filter  
**Description:** Filter Nginx configuration rules  
**Parameters:**
- `$rules` (string): Generated rules

#### `sio_browser_supports_webp`
**Type:** Filter  
**Description:** Filter WebP browser support detection  
**Parameters:**
- `$supports` (bool): Whether browser supports WebP
- `$user_agent` (string): User agent string

## Performance Hooks

### Performance Actions

#### `sio_performance_tracking_started`
**Type:** Action  
**Description:** Fired when performance tracking starts  
**Parameters:** None

#### `sio_performance_metrics_logged`
**Type:** Action  
**Description:** Fired when performance metrics are logged  
**Parameters:**
- `$metrics` (array): Performance metrics

#### `sio_memory_usage_high`
**Type:** Action  
**Description:** Fired when high memory usage is detected  
**Parameters:**
- `$usage` (int): Current memory usage in bytes
- `$limit` (int): Memory limit in bytes

#### `sio_cache_cleared`
**Type:** Action  
**Description:** Fired when cache is cleared  
**Parameters:**
- `$cache_group` (string): Cache group (optional)

### Performance Filters

#### `sio_memory_limit_optimization`
**Type:** Filter  
**Description:** Filter memory limit optimization  
**Parameters:**
- `$limit` (string): Memory limit string

#### `sio_cache_duration`
**Type:** Filter  
**Description:** Filter cache duration settings  
**Parameters:**
- `$duration` (int): Cache duration in seconds
- `$cache_type` (string): Type of cache

## Security Hooks

### Security Actions

#### `sio_security_check_passed`
**Type:** Action  
**Description:** Fired when security check passes  
**Parameters:**
- `$check_type` (string): Type of security check
- `$context` (array): Check context

#### `sio_security_check_failed`
**Type:** Action  
**Description:** Fired when security check fails  
**Parameters:**
- `$check_type` (string): Type of security check
- `$reason` (string): Failure reason
- `$context` (array): Check context

#### `sio_suspicious_activity_detected`
**Type:** Action  
**Description:** Fired when suspicious activity is detected  
**Parameters:**
- `$activity_type` (string): Type of suspicious activity
- `$details` (array): Activity details

### Security Filters

#### `sio_file_validation_rules`
**Type:** Filter  
**Description:** Filter file validation rules  
**Parameters:**
- `$rules` (array): Validation rules

#### `sio_allowed_file_types`
**Type:** Filter  
**Description:** Filter allowed file types  
**Parameters:**
- `$types` (array): Allowed file types

#### `sio_user_can_process_images`
**Type:** Filter  
**Description:** Filter user image processing capability  
**Parameters:**
- `$can_process` (bool): Whether user can process images
- `$user_id` (int): User ID

## Utility Hooks

### Logging Actions

#### `sio_log_entry_created`
**Type:** Action  
**Description:** Fired when a log entry is created  
**Parameters:**
- `$entry_id` (int): Log entry ID
- `$level` (string): Log level
- `$message` (string): Log message

### Cleanup Actions

#### `sio_cleanup_started`
**Type:** Action  
**Description:** Fired when cleanup process starts  
**Parameters:**
- `$cleanup_type` (string): Type of cleanup

#### `sio_cleanup_completed`
**Type:** Action  
**Description:** Fired when cleanup process completes  
**Parameters:**
- `$cleanup_type` (string): Type of cleanup
- `$results` (array): Cleanup results

### Statistics Actions

#### `sio_statistics_updated`
**Type:** Action  
**Description:** Fired when statistics are updated  
**Parameters:**
- `$stats` (array): Updated statistics

## Usage Examples

### Example 1: Customizing WebP Quality Based on Image Size

```php
add_filter('sio_webp_quality', function($quality, $file_path) {
    $image_info = getimagesize($file_path);
    if ($image_info && $image_info[0] > 2000) {
        // Use higher quality for large images
        return min($quality + 10, 100);
    }
    return $quality;
}, 10, 2);
```

### Example 2: Adding Custom Processing After Image Conversion

```php
add_action('sio_after_process_image', function($file_path) {
    // Send notification or update custom database
    error_log("Processed image: " . basename($file_path));
});
```

### Example 3: Modifying Batch Size Based on Server Load

```php
add_filter('sio_batch_size', function($batch_size) {
    $load = sys_getloadavg();
    if ($load[0] > 2.0) {
        // Reduce batch size if server load is high
        return max(1, intval($batch_size / 2));
    }
    return $batch_size;
});
```

### Example 4: Custom Security Validation

```php
add_filter('sio_file_validation_rules', function($rules) {
    $rules['custom_check'] = function($file_path) {
        // Add custom validation logic
        return true;
    };
    return $rules;
});
```

### Example 5: Logging Processing Events

```php
add_action('sio_image_processing_completed', function($file_path, $results) {
    $log_message = sprintf(
        'Successfully processed %s. Formats: %s. Saved: %d bytes.',
        basename($file_path),
        implode(', ', array_keys($results['processed_files'])),
        $results['total_saved']
    );
    error_log($log_message);
}, 10, 2);
```

### Example 6: Custom Admin Form Fields

```php
add_filter('sio_admin_form_fields', function($fields, $section) {
    if ($section === 'general') {
        $fields['custom_setting'] = array(
            'type' => 'checkbox',
            'label' => 'Enable Custom Feature',
            'description' => 'Enable custom processing feature',
            'default' => false
        );
    }
    return $fields;
}, 10, 2);
```

## Best Practices

1. **Always check if hooks exist** before using them in your code
2. **Use appropriate priority values** when adding hooks (default is 10)
3. **Validate and sanitize data** when using filter hooks
4. **Handle errors gracefully** in your hook callbacks
5. **Document your custom hooks** if you're extending the plugin
6. **Test thoroughly** when modifying core functionality through hooks
7. **Use namespaced function names** to avoid conflicts

## Hook Priority Guidelines

- **1-5**: Critical system modifications
- **10**: Default priority (most hooks)
- **15-20**: Standard customizations
- **25-50**: Theme/plugin integrations
- **100+**: Final modifications/cleanup

For more information about WordPress hooks, see the [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/hooks/).