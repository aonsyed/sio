<?php
/**
 * Monitor Class
 *
 * Handles logging, monitoring, and status tracking
 *
 * @package SmartImageOptimizer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Monitor Class
 */
class SIO_Monitor {
    
    /**
     * Instance
     *
     * @var SIO_Monitor
     */
    private static $instance = null;
    
    /**
     * Logs table name
     *
     * @var string
     */
    private $logs_table;
    
    /**
     * Log levels
     *
     * @var array
     */
    private $log_levels = array(
        'debug' => 0,
        'info' => 1,
        'success' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5
    );
    
    /**
     * Statistics cache
     *
     * @var array
     */
    private $stats_cache = null;
    
    /**
     * Get instance
     *
     * @return SIO_Monitor
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
        global $wpdb;
        $this->logs_table = $wpdb->prefix . 'sio_logs';
        
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Schedule log cleanup
        add_action('sio_cleanup_cron', array($this, 'cleanup_old_logs'));
        
        // AJAX handlers for monitoring dashboard
        add_action('wp_ajax_sio_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_sio_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_sio_clear_logs', array($this, 'ajax_clear_logs'));
        
        // Hook into WordPress actions for automatic logging
        add_action('sio_image_processed', array($this, 'log_image_processed'), 10, 3);
        add_action('sio_batch_completed', array($this, 'log_batch_completed'), 10, 2);
        add_action('sio_error_occurred', array($this, 'log_error'), 10, 3);
    }
    
    /**
     * Log an action
     *
     * @param int $attachment_id Attachment ID (0 for system actions)
     * @param string $action Action type
     * @param string $status Status (success, error, warning, info)
     * @param string $message Log message
     * @param float $execution_time Execution time in seconds (optional)
     * @param int $memory_usage Memory usage in bytes (optional)
     * @return int|false Log ID or false on failure
     */
    public function log_action($attachment_id, $action, $status, $message, $execution_time = null, $memory_usage = null) {
        global $wpdb;
        
        // Check if logging is enabled
        if (!SIO_Settings_Manager::instance()->get_setting('enable_logging', true)) {
            return false;
        }
        
        // Validate status
        if (!array_key_exists($status, $this->log_levels)) {
            $status = 'info';
        }
        
        // Prepare log data
        $log_data = array(
            'attachment_id' => $attachment_id > 0 ? $attachment_id : null,
            'action' => sanitize_text_field($action),
            'status' => $status,
            'message' => sanitize_textarea_field($message),
            'execution_time' => $execution_time,
            'memory_usage' => $memory_usage,
            'created_at' => current_time('mysql')
        );
        
        // Insert log entry
        $result = $wpdb->insert(
            $this->logs_table,
            $log_data,
            array('%d', '%s', '%s', '%s', '%f', '%d', '%s')
        );
        
        if ($result === false) {
            // Fallback to WordPress error log
            error_log(sprintf(
                'SIO Log: [%s] %s - %s (Attachment: %d)',
                strtoupper($status),
                $action,
                $message,
                $attachment_id
            ));
            return false;
        }
        
        $log_id = $wpdb->insert_id;
        
        // Update statistics
        $this->update_statistics($action, $status);
        
        // Clear stats cache
        $this->stats_cache = null;
        
        return $log_id;
    }
    
    /**
     * Get logs
     *
     * @param array $args Query arguments
     * @return array
     */
    public function get_logs($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'status' => '',
            'action' => '',
            'attachment_id' => 0,
            'date_from' => '',
            'date_to' => '',
            'order_by' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where_conditions = array();
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['action'])) {
            $where_conditions[] = 'action = %s';
            $where_values[] = $args['action'];
        }
        
        if ($args['attachment_id'] > 0) {
            $where_conditions[] = 'attachment_id = %d';
            $where_values[] = $args['attachment_id'];
        }
        
        if (!empty($args['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Build ORDER BY clause
        $allowed_order_by = array('created_at', 'status', 'action', 'execution_time');
        $order_by = in_array($args['order_by'], $allowed_order_by) ? $args['order_by'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Add limit and offset
        $where_values[] = intval($args['limit']);
        $where_values[] = intval($args['offset']);
        
        // Build query
        $query = "SELECT l.*, p.post_title 
                  FROM {$this->logs_table} l 
                  LEFT JOIN {$wpdb->posts} p ON l.attachment_id = p.ID 
                  {$where_clause} 
                  ORDER BY l.{$order_by} {$order} 
                  LIMIT %d OFFSET %d";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get log count
     *
     * @param array $args Query arguments
     * @return int
     */
    public function get_log_count($args = array()) {
        global $wpdb;
        
        // Build WHERE clause (same as get_logs)
        $where_conditions = array();
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['action'])) {
            $where_conditions[] = 'action = %s';
            $where_values[] = $args['action'];
        }
        
        if (!empty($args['attachment_id'])) {
            $where_conditions[] = 'attachment_id = %d';
            $where_values[] = $args['attachment_id'];
        }
        
        if (!empty($args['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $query = "SELECT COUNT(*) FROM {$this->logs_table} {$where_clause}";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return intval($wpdb->get_var($query));
    }
    
    /**
     * Get statistics
     *
     * @param bool $force_refresh Force refresh of cached stats
     * @return array
     */
    public function get_statistics($force_refresh = false) {
        if (!$force_refresh && $this->stats_cache !== null) {
            return $this->stats_cache;
        }
        
        global $wpdb;
        
        $stats = array();
        
        // Get basic counts
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->logs_table} GROUP BY status",
            ARRAY_A
        );
        
        $stats['status_counts'] = array();
        foreach ($status_counts as $row) {
            $stats['status_counts'][$row['status']] = intval($row['count']);
        }
        
        // Get action counts
        $action_counts = $wpdb->get_results(
            "SELECT action, COUNT(*) as count FROM {$this->logs_table} GROUP BY action ORDER BY count DESC LIMIT 10",
            ARRAY_A
        );
        
        $stats['action_counts'] = array();
        foreach ($action_counts as $row) {
            $stats['action_counts'][$row['action']] = intval($row['count']);
        }
        
        // Get recent activity (last 24 hours)
        $recent_activity = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->logs_table} WHERE created_at >= %s",
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));
        $stats['recent_activity'] = intval($recent_activity);
        
        // Get average execution time
        $avg_execution_time = $wpdb->get_var(
            "SELECT AVG(execution_time) FROM {$this->logs_table} WHERE execution_time IS NOT NULL"
        );
        $stats['avg_execution_time'] = $avg_execution_time ? round($avg_execution_time, 3) : 0;
        
        // Get total processing time
        $total_execution_time = $wpdb->get_var(
            "SELECT SUM(execution_time) FROM {$this->logs_table} WHERE execution_time IS NOT NULL"
        );
        $stats['total_execution_time'] = $total_execution_time ? round($total_execution_time, 2) : 0;
        
        // Get memory usage stats
        $memory_stats = $wpdb->get_row(
            "SELECT AVG(memory_usage) as avg_memory, MAX(memory_usage) as max_memory 
             FROM {$this->logs_table} WHERE memory_usage IS NOT NULL",
            ARRAY_A
        );
        $stats['avg_memory_usage'] = $memory_stats['avg_memory'] ? intval($memory_stats['avg_memory']) : 0;
        $stats['max_memory_usage'] = $memory_stats['max_memory'] ? intval($memory_stats['max_memory']) : 0;
        
        // Get daily activity for the last 7 days
        $daily_activity = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM {$this->logs_table} 
             WHERE created_at >= %s 
             GROUP BY DATE(created_at) 
             ORDER BY date DESC",
            date('Y-m-d', strtotime('-7 days'))
        ), ARRAY_A);
        
        $stats['daily_activity'] = array();
        foreach ($daily_activity as $row) {
            $stats['daily_activity'][$row['date']] = intval($row['count']);
        }
        
        // Get plugin statistics from options
        $plugin_stats = get_option('sio_stats', array());
        $stats['plugin_stats'] = $plugin_stats;
        
        // Cache the results
        $this->stats_cache = $stats;
        
        return $stats;
    }
    
    /**
     * Update plugin statistics
     *
     * @param string $action Action type
     * @param string $status Status
     */
    private function update_statistics($action, $status) {
        $stats = get_option('sio_stats', array(
            'total_processed' => 0,
            'total_saved_bytes' => 0,
            'webp_converted' => 0,
            'avif_converted' => 0,
            'errors' => 0,
            'last_updated' => time()
        ));
        
        // Update based on action and status
        switch ($action) {
            case 'image_processing':
                if ($status === 'success') {
                    $stats['total_processed']++;
                } elseif ($status === 'error') {
                    $stats['errors']++;
                }
                break;
                
            case 'webp_conversion':
                if ($status === 'success') {
                    $stats['webp_converted']++;
                }
                break;
                
            case 'avif_conversion':
                if ($status === 'success') {
                    $stats['avif_converted']++;
                }
                break;
        }
        
        $stats['last_updated'] = time();
        update_option('sio_stats', $stats);
    }
    
    /**
     * Clear logs
     *
     * @param array $args Clear arguments
     * @return int Number of logs cleared
     */
    public function clear_logs($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'older_than_days' => 0,
            'status' => '',
            'action' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array();
        $where_values = array();
        
        if ($args['older_than_days'] > 0) {
            $where_conditions[] = 'created_at < %s';
            $where_values[] = date('Y-m-d H:i:s', strtotime("-{$args['older_than_days']} days"));
        }
        
        if (!empty($args['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['action'])) {
            $where_conditions[] = 'action = %s';
            $where_values[] = $args['action'];
        }
        
        if (empty($where_conditions)) {
            // Clear all logs
            return $wpdb->query("TRUNCATE TABLE {$this->logs_table}");
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        $query = "DELETE FROM {$this->logs_table} {$where_clause}";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->query($query);
    }
    
    /**
     * Cleanup old logs
     */
    public function cleanup_old_logs() {
        $settings = SIO_Settings_Manager::instance()->get_settings();
        $retention_days = $settings['log_retention_days'];
        
        if ($retention_days > 0) {
            $cleared = $this->clear_logs(array('older_than_days' => $retention_days));
            
            if ($cleared > 0) {
                $this->log_action(
                    0,
                    'log_cleanup',
                    'success',
                    sprintf('Cleaned up %d old log entries', $cleared)
                );
            }
        }
    }
    
    /**
     * Get system information
     *
     * @return array
     */
    public function get_system_info() {
        $info = array();
        
        // WordPress info
        $info['wordpress'] = array(
            'version' => get_bloginfo('version'),
            'multisite' => is_multisite(),
            'memory_limit' => WP_MEMORY_LIMIT,
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        );
        
        // PHP info
        $info['php'] = array(
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'extensions' => array(
                'gd' => extension_loaded('gd'),
                'imagick' => extension_loaded('imagick'),
                'exif' => extension_loaded('exif')
            )
        );
        
        // Server info
        $info['server'] = array(
            'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'php_sapi' => php_sapi_name(),
            'os' => PHP_OS
        );
        
        // Plugin info
        $info['plugin'] = array(
            'version' => SIO_VERSION,
            'settings' => SIO_Settings_Manager::instance()->get_settings(),
            'image_libraries' => SIO_Image_Processor::instance()->get_available_libraries(),
            'current_library' => SIO_Image_Processor::instance()->get_current_library()
        );
        
        return $info;
    }
    
    /**
     * AJAX: Get logs
     */
    public function ajax_get_logs() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('manage_options')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $args = array(
            'limit' => isset($_POST['limit']) ? intval($_POST['limit']) : 50,
            'offset' => isset($_POST['offset']) ? intval($_POST['offset']) : 0,
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '',
            'action' => isset($_POST['action']) ? sanitize_text_field($_POST['action']) : ''
        );
        
        $logs = $this->get_logs($args);
        $total = $this->get_log_count($args);
        
        wp_send_json_success(array(
            'logs' => $logs,
            'total' => $total
        ));
    }
    
    /**
     * AJAX: Get statistics
     */
    public function ajax_get_stats() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('manage_options')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $force_refresh = isset($_POST['force_refresh']) && $_POST['force_refresh'];
        $stats = $this->get_statistics($force_refresh);
        
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX: Clear logs
     */
    public function ajax_clear_logs() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('manage_options')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $args = array(
            'older_than_days' => isset($_POST['older_than_days']) ? intval($_POST['older_than_days']) : 0,
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '',
            'action' => isset($_POST['action']) ? sanitize_text_field($_POST['action']) : ''
        );
        
        $cleared = $this->clear_logs($args);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Cleared %d log entries.', 'smart-image-optimizer'), $cleared),
            'cleared' => $cleared
        ));
    }
    
    /**
     * Log image processed event
     *
     * @param int $attachment_id Attachment ID
     * @param array $result Processing result
     * @param float $execution_time Execution time
     */
    public function log_image_processed($attachment_id, $result, $execution_time) {
        $formats = array();
        if (isset($result['processed_files']['webp'])) {
            $formats[] = 'WebP';
        }
        if (isset($result['processed_files']['avif'])) {
            $formats[] = 'AVIF';
        }
        
        $message = sprintf(
            'Image processed successfully. Formats: %s. Saved: %s bytes.',
            implode(', ', $formats),
            number_format($result['total_saved'])
        );
        
        $this->log_action(
            $attachment_id,
            'image_processing',
            'success',
            $message,
            $execution_time,
            $result['memory_usage'] ?? null
        );
    }
    
    /**
     * Log batch completed event
     *
     * @param array $result Batch result
     * @param float $execution_time Execution time
     */
    public function log_batch_completed($result, $execution_time) {
        $message = sprintf(
            'Batch processing completed. Processed: %d, Errors: %d.',
            $result['processed'],
            $result['errors']
        );
        
        $this->log_action(
            0,
            'batch_process',
            $result['errors'] > 0 ? 'warning' : 'success',
            $message,
            $execution_time
        );
    }
    
    /**
     * Log error event
     *
     * @param string $action Action that caused the error
     * @param string $message Error message
     * @param int $attachment_id Attachment ID (optional)
     */
    public function log_error($action, $message, $attachment_id = 0) {
        $this->log_action($attachment_id, $action, 'error', $message);
    }
    
    /**
     * Export logs to CSV
     *
     * @param array $args Export arguments
     * @return string CSV content
     */
    public function export_logs_csv($args = array()) {
        $logs = $this->get_logs(array_merge($args, array('limit' => 10000))); // Large limit for export
        
        $csv_content = "ID,Attachment ID,Action,Status,Message,Execution Time,Memory Usage,Created At\n";
        
        foreach ($logs as $log) {
            $csv_content .= sprintf(
                "%d,%s,%s,%s,\"%s\",%s,%s,%s\n",
                $log['id'],
                $log['attachment_id'] ?: '',
                $log['action'],
                $log['status'],
                str_replace('"', '""', $log['message']), // Escape quotes
                $log['execution_time'] ?: '',
                $log['memory_usage'] ?: '',
                $log['created_at']
            );
        }
        
        return $csv_content;
    }
}