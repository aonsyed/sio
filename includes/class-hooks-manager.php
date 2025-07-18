<?php
/**
 * Hooks Manager Class
 *
 * Manages all action hooks and filters for plugin extensibility
 *
 * @package SmartImageOptimizer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hooks Manager Class
 */
class SIO_Hooks_Manager {
    
    /**
     * Instance
     *
     * @var SIO_Hooks_Manager
     */
    private static $instance = null;
    
    /**
     * Registered hooks
     *
     * @var array
     */
    private $registered_hooks = array();
    
    /**
     * Get instance
     *
     * @return SIO_Hooks_Manager
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
     * Initialize hooks
     */
    public function init() {
        $this->register_core_hooks();
        $this->register_image_processing_hooks();
        $this->register_batch_processing_hooks();
        $this->register_settings_hooks();
        $this->register_admin_hooks();
        $this->register_server_config_hooks();
        $this->register_performance_hooks();
        $this->register_security_hooks();
        $this->register_utility_hooks();
    }
    
    /**
     * Register core plugin hooks
     */
    private function register_core_hooks() {
        // Plugin lifecycle hooks
        $this->register_hook('sio_plugin_activated', 'action', 'Fired when the plugin is activated');
        $this->register_hook('sio_plugin_deactivated', 'action', 'Fired when the plugin is deactivated');
        $this->register_hook('sio_plugin_uninstalled', 'action', 'Fired when the plugin is uninstalled');
        
        // Plugin initialization hooks
        $this->register_hook('sio_before_init', 'action', 'Fired before plugin initialization');
        $this->register_hook('sio_after_init', 'action', 'Fired after plugin initialization');
        $this->register_hook('sio_components_loaded', 'action', 'Fired when all components are loaded');
        
        // Core filters
        $this->register_hook('sio_plugin_settings', 'filter', 'Filter plugin settings array');
        $this->register_hook('sio_plugin_capabilities', 'filter', 'Filter required user capabilities');
        $this->register_hook('sio_plugin_version', 'filter', 'Filter plugin version string');
        
        // Error handling hooks
        $this->register_hook('sio_error_occurred', 'action', 'Fired when an error occurs');
        $this->register_hook('sio_warning_occurred', 'action', 'Fired when a warning occurs');
        $this->register_hook('sio_debug_message', 'action', 'Fired for debug messages');
    }
    
    /**
     * Register image processing hooks
     */
    private function register_image_processing_hooks() {
        // Image processing lifecycle
        $this->register_hook('sio_before_process_image', 'action', 'Fired before processing an image');
        $this->register_hook('sio_after_process_image', 'action', 'Fired after processing an image');
        $this->register_hook('sio_image_processing_failed', 'action', 'Fired when image processing fails');
        $this->register_hook('sio_image_processing_completed', 'action', 'Fired when image processing completes successfully');
        
        // Format conversion hooks
        $this->register_hook('sio_before_webp_conversion', 'action', 'Fired before WebP conversion');
        $this->register_hook('sio_after_webp_conversion', 'action', 'Fired after WebP conversion');
        $this->register_hook('sio_before_avif_conversion', 'action', 'Fired before AVIF conversion');
        $this->register_hook('sio_after_avif_conversion', 'action', 'Fired after AVIF conversion');
        
        // Image processing filters
        $this->register_hook('sio_processing_settings', 'filter', 'Filter image processing settings');
        $this->register_hook('sio_supported_formats', 'filter', 'Filter supported image formats');
        $this->register_hook('sio_webp_quality', 'filter', 'Filter WebP quality setting');
        $this->register_hook('sio_avif_quality', 'filter', 'Filter AVIF quality setting');
        $this->register_hook('sio_compression_level', 'filter', 'Filter compression level');
        $this->register_hook('sio_resize_dimensions', 'filter', 'Filter resize dimensions');
        $this->register_hook('sio_output_path', 'filter', 'Filter output file path');
        
        // Library selection hooks
        $this->register_hook('sio_selected_image_library', 'filter', 'Filter selected image library');
        $this->register_hook('sio_available_libraries', 'filter', 'Filter available image libraries');
        $this->register_hook('sio_library_capabilities', 'filter', 'Filter library capabilities');
        
        // Metadata hooks
        $this->register_hook('sio_preserve_metadata', 'filter', 'Filter whether to preserve image metadata');
        $this->register_hook('sio_metadata_fields', 'filter', 'Filter which metadata fields to preserve');
        $this->register_hook('sio_processed_metadata', 'filter', 'Filter processed image metadata');
    }
    
    /**
     * Register batch processing hooks
     */
    private function register_batch_processing_hooks() {
        // Batch lifecycle hooks
        $this->register_hook('sio_before_batch_process', 'action', 'Fired before batch processing starts');
        $this->register_hook('sio_after_batch_process', 'action', 'Fired after batch processing completes');
        $this->register_hook('sio_batch_item_processed', 'action', 'Fired when a batch item is processed');
        $this->register_hook('sio_batch_item_failed', 'action', 'Fired when a batch item fails');
        
        // Queue management hooks
        $this->register_hook('sio_item_added_to_queue', 'action', 'Fired when an item is added to the queue');
        $this->register_hook('sio_item_removed_from_queue', 'action', 'Fired when an item is removed from the queue');
        $this->register_hook('sio_queue_cleared', 'action', 'Fired when the queue is cleared');
        
        // Batch processing filters
        $this->register_hook('sio_batch_size', 'filter', 'Filter batch processing size');
        $this->register_hook('sio_max_execution_time', 'filter', 'Filter maximum execution time');
        $this->register_hook('sio_batch_priority', 'filter', 'Filter batch item priority');
        $this->register_hook('sio_retry_attempts', 'filter', 'Filter number of retry attempts');
        $this->register_hook('sio_queue_query_args', 'filter', 'Filter queue database query arguments');
        
        // Status and progress hooks
        $this->register_hook('sio_batch_status_changed', 'action', 'Fired when batch status changes');
        $this->register_hook('sio_progress_updated', 'action', 'Fired when processing progress is updated');
        $this->register_hook('sio_batch_statistics', 'filter', 'Filter batch processing statistics');
    }
    
    /**
     * Register settings hooks
     */
    private function register_settings_hooks() {
        // Settings lifecycle hooks
        $this->register_hook('sio_settings_loaded', 'action', 'Fired when settings are loaded');
        $this->register_hook('sio_settings_saved', 'action', 'Fired when settings are saved');
        $this->register_hook('sio_settings_reset', 'action', 'Fired when settings are reset');
        $this->register_hook('sio_setting_changed', 'action', 'Fired when a specific setting changes');
        
        // Settings validation hooks
        $this->register_hook('sio_validate_settings', 'filter', 'Filter and validate settings');
        $this->register_hook('sio_sanitize_setting', 'filter', 'Filter and sanitize individual setting');
        $this->register_hook('sio_default_settings', 'filter', 'Filter default settings');
        $this->register_hook('sio_settings_schema', 'filter', 'Filter settings schema definition');
        
        // Settings source hooks
        $this->register_hook('sio_wp_config_settings', 'filter', 'Filter wp-config.php settings');
        $this->register_hook('sio_cli_settings', 'filter', 'Filter CLI settings');
        $this->register_hook('sio_ui_settings', 'filter', 'Filter UI settings');
        $this->register_hook('sio_settings_priority', 'filter', 'Filter settings source priority');
    }
    
    /**
     * Register admin interface hooks
     */
    private function register_admin_hooks() {
        // Admin page hooks
        $this->register_hook('sio_admin_page_loaded', 'action', 'Fired when admin page is loaded');
        $this->register_hook('sio_before_admin_render', 'action', 'Fired before admin page render');
        $this->register_hook('sio_after_admin_render', 'action', 'Fired after admin page render');
        
        // Admin menu hooks
        $this->register_hook('sio_admin_menu_items', 'filter', 'Filter admin menu items');
        $this->register_hook('sio_admin_page_capability', 'filter', 'Filter admin page capability requirement');
        $this->register_hook('sio_admin_page_title', 'filter', 'Filter admin page title');
        
        // Admin form hooks
        $this->register_hook('sio_admin_form_fields', 'filter', 'Filter admin form fields');
        $this->register_hook('sio_admin_form_sections', 'filter', 'Filter admin form sections');
        $this->register_hook('sio_admin_form_validation', 'filter', 'Filter admin form validation rules');
        
        // AJAX hooks
        $this->register_hook('sio_ajax_request_received', 'action', 'Fired when AJAX request is received');
        $this->register_hook('sio_ajax_response_data', 'filter', 'Filter AJAX response data');
        $this->register_hook('sio_ajax_error_message', 'filter', 'Filter AJAX error messages');
        
        // Dashboard widget hooks
        $this->register_hook('sio_dashboard_widget_content', 'filter', 'Filter dashboard widget content');
        $this->register_hook('sio_dashboard_statistics', 'filter', 'Filter dashboard statistics');
    }
    
    /**
     * Register server configuration hooks
     */
    private function register_server_config_hooks() {
        // Server config generation hooks
        $this->register_hook('sio_before_generate_htaccess', 'action', 'Fired before .htaccess generation');
        $this->register_hook('sio_after_generate_htaccess', 'action', 'Fired after .htaccess generation');
        $this->register_hook('sio_before_generate_nginx_config', 'action', 'Fired before Nginx config generation');
        $this->register_hook('sio_after_generate_nginx_config', 'action', 'Fired after Nginx config generation');
        
        // Server config filters
        $this->register_hook('sio_htaccess_rules', 'filter', 'Filter Apache .htaccess rules');
        $this->register_hook('sio_nginx_rules', 'filter', 'Filter Nginx configuration rules');
        $this->register_hook('sio_server_config_template', 'filter', 'Filter server configuration template');
        $this->register_hook('sio_mime_types', 'filter', 'Filter MIME type definitions');
        
        // Browser detection hooks
        $this->register_hook('sio_browser_supports_webp', 'filter', 'Filter WebP browser support detection');
        $this->register_hook('sio_browser_supports_avif', 'filter', 'Filter AVIF browser support detection');
        $this->register_hook('sio_user_agent_detection', 'filter', 'Filter user agent detection logic');
        
        // On-the-fly conversion hooks
        $this->register_hook('sio_before_serve_image', 'action', 'Fired before serving converted image');
        $this->register_hook('sio_after_serve_image', 'action', 'Fired after serving converted image');
        $this->register_hook('sio_image_serve_fallback', 'action', 'Fired when falling back to original image');
        $this->register_hook('sio_serve_image_headers', 'filter', 'Filter HTTP headers for served images');
    }
    
    /**
     * Register performance optimization hooks
     */
    private function register_performance_hooks() {
        // Performance monitoring hooks
        $this->register_hook('sio_performance_tracking_started', 'action', 'Fired when performance tracking starts');
        $this->register_hook('sio_performance_metrics_logged', 'action', 'Fired when performance metrics are logged');
        $this->register_hook('sio_memory_usage_high', 'action', 'Fired when high memory usage is detected');
        $this->register_hook('sio_execution_time_exceeded', 'action', 'Fired when execution time limit is exceeded');
        
        // Performance optimization filters
        $this->register_hook('sio_memory_limit_optimization', 'filter', 'Filter memory limit optimization');
        $this->register_hook('sio_cache_duration', 'filter', 'Filter cache duration settings');
        $this->register_hook('sio_cache_groups', 'filter', 'Filter cache group definitions');
        $this->register_hook('sio_performance_thresholds', 'filter', 'Filter performance monitoring thresholds');
        
        // Cache management hooks
        $this->register_hook('sio_cache_cleared', 'action', 'Fired when cache is cleared');
        $this->register_hook('sio_cache_warmed', 'action', 'Fired when cache is warmed up');
        $this->register_hook('sio_cache_key', 'filter', 'Filter cache key generation');
        $this->register_hook('sio_cache_data', 'filter', 'Filter data before caching');
    }
    
    /**
     * Register security hooks
     */
    private function register_security_hooks() {
        // Security validation hooks
        $this->register_hook('sio_security_check_passed', 'action', 'Fired when security check passes');
        $this->register_hook('sio_security_check_failed', 'action', 'Fired when security check fails');
        $this->register_hook('sio_suspicious_activity_detected', 'action', 'Fired when suspicious activity is detected');
        
        // File validation hooks
        $this->register_hook('sio_file_validation_rules', 'filter', 'Filter file validation rules');
        $this->register_hook('sio_allowed_file_types', 'filter', 'Filter allowed file types');
        $this->register_hook('sio_max_file_size', 'filter', 'Filter maximum file size');
        $this->register_hook('sio_file_path_validation', 'filter', 'Filter file path validation logic');
        
        // User capability hooks
        $this->register_hook('sio_user_can_process_images', 'filter', 'Filter user image processing capability');
        $this->register_hook('sio_user_can_batch_process', 'filter', 'Filter user batch processing capability');
        $this->register_hook('sio_user_can_manage_settings', 'filter', 'Filter user settings management capability');
        
        // Rate limiting hooks
        $this->register_hook('sio_rate_limit_exceeded', 'action', 'Fired when rate limit is exceeded');
        $this->register_hook('sio_rate_limit_settings', 'filter', 'Filter rate limiting settings');
    }
    
    /**
     * Register utility hooks
     */
    private function register_utility_hooks() {
        // Logging hooks
        $this->register_hook('sio_log_entry_created', 'action', 'Fired when a log entry is created');
        $this->register_hook('sio_log_level', 'filter', 'Filter log level for entries');
        $this->register_hook('sio_log_message', 'filter', 'Filter log message content');
        $this->register_hook('sio_log_retention_period', 'filter', 'Filter log retention period');
        
        // Cleanup hooks
        $this->register_hook('sio_cleanup_started', 'action', 'Fired when cleanup process starts');
        $this->register_hook('sio_cleanup_completed', 'action', 'Fired when cleanup process completes');
        $this->register_hook('sio_cleanup_rules', 'filter', 'Filter cleanup rules');
        $this->register_hook('sio_cleanup_file_patterns', 'filter', 'Filter file patterns for cleanup');
        
        // Statistics hooks
        $this->register_hook('sio_statistics_updated', 'action', 'Fired when statistics are updated');
        $this->register_hook('sio_statistics_data', 'filter', 'Filter statistics data');
        $this->register_hook('sio_statistics_calculation', 'filter', 'Filter statistics calculation methods');
        
        // Notification hooks
        $this->register_hook('sio_notification_sent', 'action', 'Fired when a notification is sent');
        $this->register_hook('sio_notification_content', 'filter', 'Filter notification content');
        $this->register_hook('sio_notification_recipients', 'filter', 'Filter notification recipients');
    }
    
    /**
     * Register a hook for documentation
     *
     * @param string $hook_name Hook name
     * @param string $type Hook type (action or filter)
     * @param string $description Hook description
     * @param array $parameters Hook parameters
     */
    private function register_hook($hook_name, $type, $description, $parameters = array()) {
        $this->registered_hooks[$hook_name] = array(
            'type' => $type,
            'description' => $description,
            'parameters' => $parameters,
            'registered_at' => current_time('mysql')
        );
    }
    
    /**
     * Get all registered hooks
     *
     * @return array
     */
    public function get_registered_hooks() {
        return $this->registered_hooks;
    }
    
    /**
     * Get hooks by type
     *
     * @param string $type Hook type (action or filter)
     * @return array
     */
    public function get_hooks_by_type($type) {
        return array_filter($this->registered_hooks, function($hook) use ($type) {
            return $hook['type'] === $type;
        });
    }
    
    /**
     * Get hook documentation
     *
     * @param string $hook_name Hook name
     * @return array|null
     */
    public function get_hook_documentation($hook_name) {
        return isset($this->registered_hooks[$hook_name]) ? $this->registered_hooks[$hook_name] : null;
    }
    
    /**
     * Fire a custom action hook with validation
     *
     * @param string $hook_name Hook name
     * @param mixed ...$args Hook arguments
     */
    public function fire_action($hook_name, ...$args) {
        if (!isset($this->registered_hooks[$hook_name])) {
            $this->register_hook($hook_name, 'action', 'Dynamically registered action hook');
        }
        
        do_action($hook_name, ...$args);
    }
    
    /**
     * Apply a custom filter hook with validation
     *
     * @param string $hook_name Hook name
     * @param mixed $value Value to filter
     * @param mixed ...$args Additional arguments
     * @return mixed
     */
    public function apply_filter($hook_name, $value, ...$args) {
        if (!isset($this->registered_hooks[$hook_name])) {
            $this->register_hook($hook_name, 'filter', 'Dynamically registered filter hook');
        }
        
        return apply_filters($hook_name, $value, ...$args);
    }
    
    /**
     * Generate hooks documentation
     *
     * @return string
     */
    public function generate_hooks_documentation() {
        $documentation = "# Smart Image Optimizer - Hooks and Filters Reference\n\n";
        
        // Group hooks by category
        $categories = array(
            'Core' => array(),
            'Image Processing' => array(),
            'Batch Processing' => array(),
            'Settings' => array(),
            'Admin' => array(),
            'Server Config' => array(),
            'Performance' => array(),
            'Security' => array(),
            'Utility' => array()
        );
        
        foreach ($this->registered_hooks as $hook_name => $hook_data) {
            $category = $this->categorize_hook($hook_name);
            $categories[$category][] = array('name' => $hook_name, 'data' => $hook_data);
        }
        
        foreach ($categories as $category => $hooks) {
            if (empty($hooks)) continue;
            
            $documentation .= "## {$category} Hooks\n\n";
            
            foreach ($hooks as $hook) {
                $documentation .= "### `{$hook['name']}`\n";
                $documentation .= "**Type:** " . ucfirst($hook['data']['type']) . "\n";
                $documentation .= "**Description:** {$hook['data']['description']}\n\n";
                
                if (!empty($hook['data']['parameters'])) {
                    $documentation .= "**Parameters:**\n";
                    foreach ($hook['data']['parameters'] as $param) {
                        $documentation .= "- `{$param['name']}` ({$param['type']}): {$param['description']}\n";
                    }
                    $documentation .= "\n";
                }
                
                $documentation .= "---\n\n";
            }
        }
        
        return $documentation;
    }
    
    /**
     * Categorize hook by name
     *
     * @param string $hook_name Hook name
     * @return string
     */
    private function categorize_hook($hook_name) {
        if (strpos($hook_name, 'sio_plugin_') === 0 || strpos($hook_name, 'sio_error_') === 0) {
            return 'Core';
        } elseif (strpos($hook_name, 'sio_process_') === 0 || strpos($hook_name, 'sio_webp_') === 0 || strpos($hook_name, 'sio_avif_') === 0) {
            return 'Image Processing';
        } elseif (strpos($hook_name, 'sio_batch_') === 0 || strpos($hook_name, 'sio_queue_') === 0) {
            return 'Batch Processing';
        } elseif (strpos($hook_name, 'sio_settings_') === 0 || strpos($hook_name, 'sio_setting_') === 0) {
            return 'Settings';
        } elseif (strpos($hook_name, 'sio_admin_') === 0 || strpos($hook_name, 'sio_ajax_') === 0) {
            return 'Admin';
        } elseif (strpos($hook_name, 'sio_htaccess_') === 0 || strpos($hook_name, 'sio_nginx_') === 0 || strpos($hook_name, 'sio_serve_') === 0) {
            return 'Server Config';
        } elseif (strpos($hook_name, 'sio_performance_') === 0 || strpos($hook_name, 'sio_cache_') === 0) {
            return 'Performance';
        } elseif (strpos($hook_name, 'sio_security_') === 0 || strpos($hook_name, 'sio_user_can_') === 0) {
            return 'Security';
        } else {
            return 'Utility';
        }
    }
    
    /**
     * Export hooks as JSON
     *
     * @return string
     */
    public function export_hooks_json() {
        return json_encode($this->registered_hooks, JSON_PRETTY_PRINT);
    }
    
    /**
     * Validate hook usage
     *
     * @param string $hook_name Hook name
     * @return bool
     */
    public function validate_hook_usage($hook_name) {
        return isset($this->registered_hooks[$hook_name]);
    }
}