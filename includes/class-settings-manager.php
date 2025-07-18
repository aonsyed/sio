<?php
/**
 * Settings Manager Class
 *
 * Handles plugin settings with support for wp-config.php, WP CLI, and admin UI
 *
 * @package SmartImageOptimizer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Manager Class
 */
class SIO_Settings_Manager {
    
    /**
     * Instance
     *
     * @var SIO_Settings_Manager
     */
    private static $instance = null;
    
    /**
     * Settings cache
     *
     * @var array
     */
    private $settings_cache = null;
    
    /**
     * Default settings
     *
     * @var array
     */
    private $default_settings = array(
        'auto_process' => true,
        'batch_mode' => false,
        'webp_quality' => 80,
        'avif_quality' => 70,
        'enable_webp' => true,
        'enable_avif' => true,
        'enable_resize' => false,
        'max_width' => 1920,
        'max_height' => 1080,
        'compression_level' => 6,
        'preserve_metadata' => false,
        'cleanup_originals' => false,
        'cleanup_after_days' => 30,
        'batch_size' => 10,
        'max_execution_time' => 300,
        'enable_logging' => true,
        'log_retention_days' => 30,
        'allowed_mime_types' => array('image/jpeg', 'image/png', 'image/gif'),
        'exclude_sizes' => array(),
        'backup_originals' => true,
        'progressive_jpeg' => true,
        'strip_metadata' => true,
        'enable_auto_serve' => false,
        'auto_htaccess' => false,
        'fallback_conversion' => true,
        'cache_duration' => 86400
    );
    
    /**
     * Settings that can be overridden by wp-config.php constants
     *
     * @var array
     */
    private $config_overrides = array(
        'SIO_AUTO_PROCESS' => 'auto_process',
        'SIO_BATCH_MODE' => 'batch_mode',
        'SIO_WEBP_QUALITY' => 'webp_quality',
        'SIO_AVIF_QUALITY' => 'avif_quality',
        'SIO_ENABLE_WEBP' => 'enable_webp',
        'SIO_ENABLE_AVIF' => 'enable_avif',
        'SIO_ENABLE_RESIZE' => 'enable_resize',
        'SIO_MAX_WIDTH' => 'max_width',
        'SIO_MAX_HEIGHT' => 'max_height',
        'SIO_COMPRESSION_LEVEL' => 'compression_level',
        'SIO_PRESERVE_METADATA' => 'preserve_metadata',
        'SIO_CLEANUP_ORIGINALS' => 'cleanup_originals',
        'SIO_CLEANUP_AFTER_DAYS' => 'cleanup_after_days',
        'SIO_BATCH_SIZE' => 'batch_size',
        'SIO_MAX_EXECUTION_TIME' => 'max_execution_time',
        'SIO_ENABLE_LOGGING' => 'enable_logging',
        'SIO_LOG_RETENTION_DAYS' => 'log_retention_days',
        'SIO_BACKUP_ORIGINALS' => 'backup_originals',
        'SIO_PROGRESSIVE_JPEG' => 'progressive_jpeg',
        'SIO_STRIP_METADATA' => 'strip_metadata',
        'SIO_ENABLE_AUTO_SERVE' => 'enable_auto_serve',
        'SIO_AUTO_HTACCESS' => 'auto_htaccess',
        'SIO_FALLBACK_CONVERSION' => 'fallback_conversion',
        'SIO_CACHE_DURATION' => 'cache_duration'
    );
    
    /**
     * Get instance
     *
     * @return SIO_Settings_Manager
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Clear cache when settings are updated
        add_action('update_option_sio_settings', array($this, 'clear_cache'));
        
        // Add settings validation
        add_filter('pre_update_option_sio_settings', array($this, 'validate_settings'), 10, 2);
    }
    
    /**
     * Get all settings
     *
     * @return array
     */
    public function get_settings() {
        if (null !== $this->settings_cache) {
            return $this->settings_cache;
        }
        
        // Get settings from database
        $db_settings = get_option('sio_settings', array());
        
        // Merge with defaults
        $settings = wp_parse_args($db_settings, $this->default_settings);
        
        // Apply wp-config.php overrides
        $settings = $this->apply_config_overrides($settings);
        
        // Cache the settings
        $this->settings_cache = $settings;
        
        return $settings;
    }
    
    /**
     * Get a specific setting
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    public function get_setting($key, $default = null) {
        $settings = $this->get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * Update settings
     *
     * @param array $new_settings New settings
     * @param string $source Source of update (ui, cli, config)
     * @return bool
     */
    public function update_settings($new_settings, $source = 'ui') {
        // Validate settings
        $validated_settings = $this->validate_settings($new_settings);
        
        if (is_wp_error($validated_settings)) {
            return $validated_settings;
        }
        
        // Get current settings
        $current_settings = $this->get_settings();
        
        // Merge with current settings
        $updated_settings = wp_parse_args($validated_settings, $current_settings);
        
        // Update in database
        $result = update_option('sio_settings', $updated_settings);
        
        if ($result) {
            // Clear cache
            $this->clear_cache();
            
            // Log the update
            $this->log_settings_update($source, $new_settings);
            
            // Fire action hook
            do_action('sio_settings_updated', $updated_settings, $source);
        }
        
        return $result;
    }
    
    /**
     * Update a single setting
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @param string $source Source of update
     * @return bool
     */
    public function update_setting($key, $value, $source = 'ui') {
        return $this->update_settings(array($key => $value), $source);
    }
    
    /**
     * Reset settings to defaults
     *
     * @return bool
     */
    public function reset_settings() {
        $result = update_option('sio_settings', $this->default_settings);
        
        if ($result) {
            $this->clear_cache();
            do_action('sio_settings_reset');
        }
        
        return $result;
    }
    
    /**
     * Apply wp-config.php overrides
     *
     * @param array $settings Current settings
     * @return array
     */
    private function apply_config_overrides($settings) {
        foreach ($this->config_overrides as $constant => $setting_key) {
            if (defined($constant)) {
                $settings[$setting_key] = constant($constant);
            }
        }
        
        return $settings;
    }
    
    /**
     * Validate settings
     *
     * @param array $settings Settings to validate
     * @return array|WP_Error
     */
    public function validate_settings($settings) {
        $validated = array();
        $errors = array();
        
        // Validate each setting
        foreach ($settings as $key => $value) {
            switch ($key) {
                case 'webp_quality':
                case 'avif_quality':
                    $validated[$key] = $this->validate_quality($value, $key);
                    if ($validated[$key] === false) {
                        $errors[] = sprintf(__('Invalid quality value for %s. Must be between 1 and 100.', 'smart-image-optimizer'), $key);
                    }
                    break;
                    
                case 'max_width':
                case 'max_height':
                    $validated[$key] = $this->validate_dimension($value, $key);
                    if ($validated[$key] === false) {
                        $errors[] = sprintf(__('Invalid dimension value for %s. Must be between 100 and 5000.', 'smart-image-optimizer'), $key);
                    }
                    break;
                    
                case 'compression_level':
                    $validated[$key] = $this->validate_compression_level($value);
                    if ($validated[$key] === false) {
                        $errors[] = __('Invalid compression level. Must be between 0 and 9.', 'smart-image-optimizer');
                    }
                    break;
                    
                case 'batch_size':
                    $validated[$key] = $this->validate_batch_size($value);
                    if ($validated[$key] === false) {
                        $errors[] = __('Invalid batch size. Must be between 1 and 100.', 'smart-image-optimizer');
                    }
                    break;
                    
                case 'max_execution_time':
                    $validated[$key] = $this->validate_execution_time($value);
                    if ($validated[$key] === false) {
                        $errors[] = __('Invalid execution time. Must be between 30 and 3600 seconds.', 'smart-image-optimizer');
                    }
                    break;
                    
                case 'cleanup_after_days':
                case 'log_retention_days':
                    $validated[$key] = $this->validate_days($value, $key);
                    if ($validated[$key] === false) {
                        $errors[] = sprintf(__('Invalid value for %s. Must be between 1 and 365 days.', 'smart-image-optimizer'), $key);
                    }
                    break;
                    
                case 'allowed_mime_types':
                    $validated[$key] = $this->validate_mime_types($value);
                    if ($validated[$key] === false) {
                        $errors[] = __('Invalid MIME types specified.', 'smart-image-optimizer');
                    }
                    break;
                    
                case 'exclude_sizes':
                    $validated[$key] = $this->validate_exclude_sizes($value);
                    break;
                    
                case 'cache_duration':
                    $validated[$key] = $this->validate_cache_duration($value);
                    if ($validated[$key] === false) {
                        $errors[] = __('Invalid cache duration. Must be between 300 and 604800 seconds (5 minutes to 7 days).', 'smart-image-optimizer');
                    }
                    break;
                    
                default:
                    // Boolean settings
                    if (in_array($key, array(
                        'auto_process', 'batch_mode', 'enable_webp', 'enable_avif',
                        'enable_resize', 'preserve_metadata', 'cleanup_originals',
                        'enable_logging', 'backup_originals', 'progressive_jpeg', 'strip_metadata',
                        'enable_auto_serve', 'auto_htaccess', 'fallback_conversion'
                    ))) {
                        $validated[$key] = (bool) $value;
                    } else {
                        $validated[$key] = sanitize_text_field($value);
                    }
                    break;
            }
        }
        
        if (!empty($errors)) {
            return new WP_Error('invalid_settings', implode(' ', $errors));
        }
        
        return $validated;
    }
    
    /**
     * Validate quality setting
     *
     * @param mixed $value Quality value
     * @param string $key Setting key
     * @return int|false
     */
    private function validate_quality($value, $key) {
        $quality = intval($value);
        return ($quality >= 1 && $quality <= 100) ? $quality : false;
    }
    
    /**
     * Validate dimension setting
     *
     * @param mixed $value Dimension value
     * @param string $key Setting key
     * @return int|false
     */
    private function validate_dimension($value, $key) {
        $dimension = intval($value);
        return ($dimension >= 100 && $dimension <= 5000) ? $dimension : false;
    }
    
    /**
     * Validate compression level
     *
     * @param mixed $value Compression level
     * @return int|false
     */
    private function validate_compression_level($value) {
        $level = intval($value);
        return ($level >= 0 && $level <= 9) ? $level : false;
    }
    
    /**
     * Validate batch size
     *
     * @param mixed $value Batch size
     * @return int|false
     */
    private function validate_batch_size($value) {
        $size = intval($value);
        return ($size >= 1 && $size <= 100) ? $size : false;
    }
    
    /**
     * Validate execution time
     *
     * @param mixed $value Execution time
     * @return int|false
     */
    private function validate_execution_time($value) {
        $time = intval($value);
        return ($time >= 30 && $time <= 3600) ? $time : false;
    }
    
    /**
     * Validate days setting
     *
     * @param mixed $value Days value
     * @param string $key Setting key
     * @return int|false
     */
    private function validate_days($value, $key) {
        $days = intval($value);
        return ($days >= 1 && $days <= 365) ? $days : false;
    }
    
    /**
     * Validate MIME types
     *
     * @param mixed $value MIME types
     * @return array|false
     */
    private function validate_mime_types($value) {
        if (!is_array($value)) {
            return false;
        }
        
        $valid_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif');
        $validated = array();
        
        foreach ($value as $mime_type) {
            if (in_array($mime_type, $valid_types)) {
                $validated[] = $mime_type;
            }
        }
        
        return !empty($validated) ? $validated : false;
    }
    
    /**
     * Validate exclude sizes
     *
     * @param mixed $value Exclude sizes
     * @return array
     */
    private function validate_exclude_sizes($value) {
        if (!is_array($value)) {
            return array();
        }
        
        return array_map('sanitize_text_field', $value);
    }
    
    /**
     * Validate cache duration
     *
     * @param mixed $value Cache duration in seconds
     * @return int|false
     */
    private function validate_cache_duration($value) {
        $duration = intval($value);
        return ($duration >= 300 && $duration <= 604800) ? $duration : false;
    }
    
    /**
     * Clear settings cache
     */
    public function clear_cache() {
        $this->settings_cache = null;
        wp_cache_delete('sio_settings', 'smart_image_optimizer');
    }
    
    /**
     * Log settings update
     *
     * @param string $source Update source
     * @param array $changes Changed settings
     */
    private function log_settings_update($source, $changes) {
        if (!$this->get_setting('enable_logging')) {
            return;
        }
        
        $message = sprintf(
            'Settings updated via %s. Changed: %s',
            $source,
            implode(', ', array_keys($changes))
        );
        
        SIO_Monitor::instance()->log_action(0, 'settings_update', 'success', $message);
    }
    
    /**
     * Get settings schema for REST API or CLI
     *
     * @return array
     */
    public function get_settings_schema() {
        return array(
            'auto_process' => array(
                'type' => 'boolean',
                'description' => __('Automatically process uploaded images', 'smart-image-optimizer'),
                'default' => true
            ),
            'batch_mode' => array(
                'type' => 'boolean',
                'description' => __('Use batch processing for uploaded images', 'smart-image-optimizer'),
                'default' => false
            ),
            'webp_quality' => array(
                'type' => 'integer',
                'description' => __('WebP image quality (1-100)', 'smart-image-optimizer'),
                'minimum' => 1,
                'maximum' => 100,
                'default' => 80
            ),
            'avif_quality' => array(
                'type' => 'integer',
                'description' => __('AVIF image quality (1-100)', 'smart-image-optimizer'),
                'minimum' => 1,
                'maximum' => 100,
                'default' => 70
            ),
            'enable_webp' => array(
                'type' => 'boolean',
                'description' => __('Enable WebP conversion', 'smart-image-optimizer'),
                'default' => true
            ),
            'enable_avif' => array(
                'type' => 'boolean',
                'description' => __('Enable AVIF conversion', 'smart-image-optimizer'),
                'default' => true
            ),
            'enable_resize' => array(
                'type' => 'boolean',
                'description' => __('Enable image resizing', 'smart-image-optimizer'),
                'default' => false
            ),
            'max_width' => array(
                'type' => 'integer',
                'description' => __('Maximum image width in pixels', 'smart-image-optimizer'),
                'minimum' => 100,
                'maximum' => 5000,
                'default' => 1920
            ),
            'max_height' => array(
                'type' => 'integer',
                'description' => __('Maximum image height in pixels', 'smart-image-optimizer'),
                'minimum' => 100,
                'maximum' => 5000,
                'default' => 1080
            ),
            'compression_level' => array(
                'type' => 'integer',
                'description' => __('Compression level (0-9)', 'smart-image-optimizer'),
                'minimum' => 0,
                'maximum' => 9,
                'default' => 6
            ),
            'preserve_metadata' => array(
                'type' => 'boolean',
                'description' => __('Preserve image metadata', 'smart-image-optimizer'),
                'default' => false
            ),
            'cleanup_originals' => array(
                'type' => 'boolean',
                'description' => __('Automatically cleanup original files', 'smart-image-optimizer'),
                'default' => false
            ),
            'cleanup_after_days' => array(
                'type' => 'integer',
                'description' => __('Days to keep original files before cleanup', 'smart-image-optimizer'),
                'minimum' => 1,
                'maximum' => 365,
                'default' => 30
            ),
            'batch_size' => array(
                'type' => 'integer',
                'description' => __('Number of images to process in each batch', 'smart-image-optimizer'),
                'minimum' => 1,
                'maximum' => 100,
                'default' => 10
            ),
            'max_execution_time' => array(
                'type' => 'integer',
                'description' => __('Maximum execution time in seconds', 'smart-image-optimizer'),
                'minimum' => 30,
                'maximum' => 3600,
                'default' => 300
            ),
            'enable_logging' => array(
                'type' => 'boolean',
                'description' => __('Enable activity logging', 'smart-image-optimizer'),
                'default' => true
            ),
            'log_retention_days' => array(
                'type' => 'integer',
                'description' => __('Days to keep log entries', 'smart-image-optimizer'),
                'minimum' => 1,
                'maximum' => 365,
                'default' => 30
            ),
            'enable_auto_serve' => array(
                'type' => 'boolean',
                'description' => __('Enable automatic serving of WebP/AVIF images', 'smart-image-optimizer'),
                'default' => false
            ),
            'auto_htaccess' => array(
                'type' => 'boolean',
                'description' => __('Automatically update .htaccess with serving rules', 'smart-image-optimizer'),
                'default' => false
            ),
            'fallback_conversion' => array(
                'type' => 'boolean',
                'description' => __('Enable on-the-fly conversion as fallback', 'smart-image-optimizer'),
                'default' => true
            ),
            'cache_duration' => array(
                'type' => 'integer',
                'description' => __('Cache duration for converted images in seconds', 'smart-image-optimizer'),
                'minimum' => 300,
                'maximum' => 604800,
                'default' => 86400
            )
        );
    }
}