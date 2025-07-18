<?php
/**
 * Performance Optimizer Class
 *
 * Handles performance optimization, memory management, and resource monitoring
 *
 * @package SmartImageOptimizer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Performance Optimizer Class
 */
class SIO_Performance_Optimizer {
    
    /**
     * Instance
     *
     * @var SIO_Performance_Optimizer
     */
    private static $instance = null;
    
    /**
     * Memory usage tracking
     *
     * @var array
     */
    private $memory_tracking = array();
    
    /**
     * Performance metrics
     *
     * @var array
     */
    private $metrics = array();
    
    /**
     * Cache groups
     *
     * @var array
     */
    private $cache_groups = array(
        'settings' => 'sio_settings',
        'image_info' => 'sio_image_info',
        'conversion_results' => 'sio_conversion',
        'system_info' => 'sio_system'
    );
    
    /**
     * Get instance
     *
     * @return SIO_Performance_Optimizer
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
        $this->init();
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Initialize performance tracking
        $this->start_performance_tracking();
        
        // Add WordPress hooks
        add_action('init', array($this, 'setup_object_cache'));
        add_action('wp_loaded', array($this, 'optimize_wp_queries'));
        add_action('shutdown', array($this, 'log_performance_metrics'));
        
        // Memory management hooks
        add_action('sio_before_batch_process', array($this, 'prepare_for_batch_processing'));
        add_action('sio_after_batch_process', array($this, 'cleanup_after_batch_processing'));
        add_action('sio_before_process_image', array($this, 'monitor_memory_before_processing'));
        add_action('sio_after_process_image', array($this, 'monitor_memory_after_processing'));
        
        // Database optimization
        add_filter('sio_batch_size', array($this, 'optimize_batch_size'));
        add_filter('sio_processing_settings', array($this, 'optimize_processing_settings'));
    }
    
    /**
     * Start performance tracking
     */
    public function start_performance_tracking() {
        $this->metrics['start_time'] = microtime(true);
        $this->metrics['start_memory'] = memory_get_usage(true);
        $this->metrics['start_peak_memory'] = memory_get_peak_usage(true);
    }
    
    /**
     * Setup object cache optimization
     */
    public function setup_object_cache() {
        // Add cache groups for better organization
        foreach ($this->cache_groups as $group) {
            wp_cache_add_global_groups($group);
        }
        
        // Preload frequently accessed data
        $this->preload_cache();
    }
    
    /**
     * Preload frequently accessed cache data
     */
    private function preload_cache() {
        // Preload settings
        $settings = SIO_Settings_Manager::instance()->get_settings();
        wp_cache_set('current_settings', $settings, $this->cache_groups['settings'], 3600);
        
        // Preload system information
        $system_info = $this->get_system_capabilities();
        wp_cache_set('system_capabilities', $system_info, $this->cache_groups['system_info'], 7200);
    }
    
    /**
     * Optimize WordPress queries
     */
    public function optimize_wp_queries() {
        // Optimize database queries for batch processing
        add_filter('posts_clauses', array($this, 'optimize_attachment_queries'), 10, 2);
        
        // Reduce autoload options impact
        $this->optimize_autoload_options();
    }
    
    /**
     * Optimize attachment queries
     *
     * @param array $clauses Query clauses
     * @param WP_Query $query Query object
     * @return array
     */
    public function optimize_attachment_queries($clauses, $query) {
        if (!$query->is_main_query() || !isset($query->query_vars['post_type']) || $query->query_vars['post_type'] !== 'attachment') {
            return $clauses;
        }
        
        // Add indexes hint for better performance
        global $wpdb;
        $clauses['join'] .= " USE INDEX (type_status_date)";
        
        return $clauses;
    }
    
    /**
     * Optimize autoload options
     */
    private function optimize_autoload_options() {
        // Ensure plugin options are not autoloaded unnecessarily
        $options_to_optimize = array(
            'sio_batch_queue',
            'sio_processing_stats',
            'sio_conversion_cache'
        );
        
        foreach ($options_to_optimize as $option) {
            if (get_option($option) !== false) {
                update_option($option, get_option($option), 'no');
            }
        }
    }
    
    /**
     * Prepare for batch processing
     *
     * @param array $batch_items Batch items to process
     */
    public function prepare_for_batch_processing($batch_items) {
        // Increase memory limit if needed
        $this->optimize_memory_limit();
        
        // Disable unnecessary WordPress features during batch processing
        $this->disable_unnecessary_features();
        
        // Clear object cache to free memory
        wp_cache_flush();
        
        // Start memory tracking
        $this->memory_tracking['batch_start'] = memory_get_usage(true);
        
        // Log batch preparation
        SIO_Monitor::instance()->log_action(
            0,
            'batch_preparation',
            'info',
            sprintf('Prepared for batch processing of %d items', count($batch_items))
        );
    }
    
    /**
     * Cleanup after batch processing
     *
     * @param array $results Processing results
     */
    public function cleanup_after_batch_processing($results) {
        // Re-enable WordPress features
        $this->enable_features();
        
        // Clear temporary caches
        $this->clear_temporary_caches();
        
        // Log memory usage
        $memory_used = memory_get_usage(true) - $this->memory_tracking['batch_start'];
        $peak_memory = memory_get_peak_usage(true);
        
        SIO_Monitor::instance()->log_action(
            0,
            'batch_cleanup',
            'info',
            sprintf('Batch processing completed. Memory used: %s MB, Peak: %s MB', 
                number_format($memory_used / 1024 / 1024, 2),
                number_format($peak_memory / 1024 / 1024, 2)
            )
        );
        
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
    
    /**
     * Monitor memory before image processing
     *
     * @param string $file_path Image file path
     */
    public function monitor_memory_before_processing($file_path) {
        $this->memory_tracking[$file_path] = array(
            'start' => memory_get_usage(true),
            'start_peak' => memory_get_peak_usage(true)
        );
    }
    
    /**
     * Monitor memory after image processing
     *
     * @param string $file_path Image file path
     */
    public function monitor_memory_after_processing($file_path) {
        if (!isset($this->memory_tracking[$file_path])) {
            return;
        }
        
        $current_memory = memory_get_usage(true);
        $current_peak = memory_get_peak_usage(true);
        
        $memory_used = $current_memory - $this->memory_tracking[$file_path]['start'];
        $peak_increase = $current_peak - $this->memory_tracking[$file_path]['start_peak'];
        
        // Log excessive memory usage
        if ($memory_used > 50 * 1024 * 1024) { // 50MB threshold
            SIO_Monitor::instance()->log_action(
                0,
                'high_memory_usage',
                'warning',
                sprintf('High memory usage detected for %s: %s MB used, %s MB peak increase',
                    basename($file_path),
                    number_format($memory_used / 1024 / 1024, 2),
                    number_format($peak_increase / 1024 / 1024, 2)
                )
            );
        }
        
        // Clean up tracking data
        unset($this->memory_tracking[$file_path]);
    }
    
    /**
     * Optimize memory limit
     */
    private function optimize_memory_limit() {
        $current_limit = ini_get('memory_limit');
        $current_bytes = $this->parse_memory_limit($current_limit);
        $recommended_bytes = 512 * 1024 * 1024; // 512MB
        
        if ($current_bytes < $recommended_bytes) {
            $new_limit = '512M';
            if (ini_set('memory_limit', $new_limit) !== false) {
                SIO_Monitor::instance()->log_action(
                    0,
                    'memory_optimization',
                    'info',
                    sprintf('Memory limit increased from %s to %s', $current_limit, $new_limit)
                );
            }
        }
    }
    
    /**
     * Parse memory limit string to bytes
     *
     * @param string $limit Memory limit string
     * @return int Bytes
     */
    private function parse_memory_limit($limit) {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
                // fall through
            case 'm':
                $value *= 1024;
                // fall through
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Disable unnecessary WordPress features during processing
     */
    private function disable_unnecessary_features() {
        // Disable post revisions
        add_filter('wp_revisions_to_keep', '__return_zero');
        
        // Disable autosave
        add_action('wp_print_scripts', function() {
            wp_dequeue_script('autosave');
        });
        
        // Reduce heartbeat frequency
        add_filter('heartbeat_settings', function($settings) {
            $settings['interval'] = 60; // 60 seconds
            return $settings;
        });
        
        // Disable unnecessary queries
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'rsd_link');
    }
    
    /**
     * Re-enable WordPress features
     */
    private function enable_features() {
        // Remove filters that were added during processing
        remove_filter('wp_revisions_to_keep', '__return_zero');
        remove_filter('heartbeat_settings', function($settings) {
            $settings['interval'] = 15;
            return $settings;
        });
    }
    
    /**
     * Clear temporary caches
     */
    private function clear_temporary_caches() {
        // Clear conversion result cache
        wp_cache_flush_group($this->cache_groups['conversion_results']);
        
        // Clear image info cache
        wp_cache_flush_group($this->cache_groups['image_info']);
        
        // Clear any temporary transients
        delete_transient('sio_temp_processing_data');
    }
    
    /**
     * Optimize batch size based on available resources
     *
     * @param int $batch_size Current batch size
     * @return int Optimized batch size
     */
    public function optimize_batch_size($batch_size) {
        $memory_limit = $this->parse_memory_limit(ini_get('memory_limit'));
        $available_memory = $memory_limit - memory_get_usage(true);
        
        // Estimate memory per image (rough calculation)
        $estimated_memory_per_image = 20 * 1024 * 1024; // 20MB per image
        
        $max_batch_size = floor($available_memory / $estimated_memory_per_image);
        
        // Ensure minimum batch size of 1 and maximum of original setting
        $optimized_size = max(1, min($batch_size, $max_batch_size));
        
        if ($optimized_size !== $batch_size) {
            SIO_Monitor::instance()->log_action(
                0,
                'batch_size_optimization',
                'info',
                sprintf('Batch size optimized from %d to %d based on available memory', $batch_size, $optimized_size)
            );
        }
        
        return $optimized_size;
    }
    
    /**
     * Optimize processing settings based on system capabilities
     *
     * @param array $settings Processing settings
     * @return array Optimized settings
     */
    public function optimize_processing_settings($settings) {
        $system_info = $this->get_system_capabilities();
        
        // Optimize based on available memory
        if ($system_info['memory_limit'] < 256 * 1024 * 1024) { // Less than 256MB
            $settings['webp_quality'] = min($settings['webp_quality'], 75);
            $settings['avif_quality'] = min($settings['avif_quality'], 65);
            $settings['compression_level'] = min($settings['compression_level'], 4);
        }
        
        // Optimize based on CPU cores
        if ($system_info['cpu_cores'] > 4) {
            // Can handle higher compression levels
            $settings['compression_level'] = min($settings['compression_level'] + 1, 9);
        }
        
        return $settings;
    }
    
    /**
     * Get system capabilities
     *
     * @return array System information
     */
    private function get_system_capabilities() {
        $cached = wp_cache_get('system_capabilities', $this->cache_groups['system_info']);
        if ($cached !== false) {
            return $cached;
        }
        
        $info = array(
            'memory_limit' => $this->parse_memory_limit(ini_get('memory_limit')),
            'max_execution_time' => (int) ini_get('max_execution_time'),
            'cpu_cores' => $this->get_cpu_cores(),
            'disk_free_space' => disk_free_space(ABSPATH),
            'php_version' => PHP_VERSION,
            'image_library' => SIO_Image_Processor::instance()->get_available_library()
        );
        
        wp_cache_set('system_capabilities', $info, $this->cache_groups['system_info'], 7200);
        
        return $info;
    }
    
    /**
     * Get number of CPU cores
     *
     * @return int Number of CPU cores
     */
    private function get_cpu_cores() {
        $cores = 1;
        
        if (function_exists('shell_exec')) {
            if (PHP_OS_FAMILY === 'Windows') {
                $cores = (int) shell_exec('echo %NUMBER_OF_PROCESSORS%');
            } else {
                $cores = (int) shell_exec('nproc 2>/dev/null || echo 1');
            }
        }
        
        return max(1, $cores);
    }
    
    /**
     * Log performance metrics
     */
    public function log_performance_metrics() {
        if (!isset($this->metrics['start_time'])) {
            return;
        }
        
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        $peak_memory = memory_get_peak_usage(true);
        
        $execution_time = $end_time - $this->metrics['start_time'];
        $memory_used = $end_memory - $this->metrics['start_memory'];
        $peak_increase = $peak_memory - $this->metrics['start_peak_memory'];
        
        // Only log if significant resource usage
        if ($execution_time > 1 || $memory_used > 10 * 1024 * 1024) { // 1 second or 10MB
            SIO_Monitor::instance()->log_action(
                0,
                'performance_metrics',
                'info',
                sprintf('Execution time: %.2fs, Memory used: %.2fMB, Peak increase: %.2fMB',
                    $execution_time,
                    $memory_used / 1024 / 1024,
                    $peak_increase / 1024 / 1024
                )
            );
        }
    }
    
    /**
     * Get performance statistics
     *
     * @return array Performance statistics
     */
    public function get_performance_stats() {
        return array(
            'current_memory_usage' => memory_get_usage(true),
            'peak_memory_usage' => memory_get_peak_usage(true),
            'memory_limit' => $this->parse_memory_limit(ini_get('memory_limit')),
            'execution_time' => microtime(true) - $this->metrics['start_time'],
            'system_capabilities' => $this->get_system_capabilities(),
            'cache_stats' => $this->get_cache_statistics()
        );
    }
    
    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    private function get_cache_statistics() {
        $stats = array();
        
        foreach ($this->cache_groups as $name => $group) {
            $stats[$name] = array(
                'group' => $group,
                'hits' => wp_cache_get_stats($group)['hits'] ?? 0,
                'misses' => wp_cache_get_stats($group)['misses'] ?? 0
            );
        }
        
        return $stats;
    }
    
    /**
     * Clear all performance caches
     */
    public function clear_all_caches() {
        foreach ($this->cache_groups as $group) {
            wp_cache_flush_group($group);
        }
        
        // Clear transients
        delete_transient('sio_performance_stats');
        delete_transient('sio_system_info');
        
        SIO_Monitor::instance()->log_action(0, 'cache_cleared', 'info', 'All performance caches cleared');
    }
}