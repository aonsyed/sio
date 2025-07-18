<?php
/**
 * Batch Processor Class
 *
 * Handles batch processing queue, background processing, and job management
 *
 * @package SmartImageOptimizer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Batch Processor Class
 */
class SIO_Batch_Processor {
    
    /**
     * Instance
     *
     * @var SIO_Batch_Processor
     */
    private static $instance = null;
    
    /**
     * Queue table name
     *
     * @var string
     */
    private $queue_table;
    
    /**
     * Processing status
     *
     * @var array
     */
    private $processing_status = array();
    
    /**
     * Maximum retry attempts
     *
     * @var int
     */
    private $max_retries = 3;
    
    /**
     * Get instance
     *
     * @return SIO_Batch_Processor
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
        $this->queue_table = $wpdb->prefix . 'sio_queue';
        
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Schedule background processing
        add_action('sio_batch_process', array($this, 'process_batch'));
        
        // AJAX handlers
        add_action('wp_ajax_sio_start_batch', array($this, 'ajax_start_batch'));
        add_action('wp_ajax_sio_stop_batch', array($this, 'ajax_stop_batch'));
        add_action('wp_ajax_sio_get_batch_status', array($this, 'ajax_get_batch_status'));
        add_action('wp_ajax_sio_process_single', array($this, 'ajax_process_single'));
        
        // Clean up completed jobs daily
        add_action('sio_cleanup_cron', array($this, 'cleanup_completed_jobs'));
        
        // Hook into attachment deletion
        add_action('delete_attachment', array($this, 'remove_from_queue'));
    }
    
    /**
     * Add item to processing queue
     *
     * @param int $attachment_id Attachment ID
     * @param array $options Processing options
     * @param int $priority Priority (higher = more important)
     * @return int|false Queue item ID or false on failure
     */
    public function add_to_queue($attachment_id, $options = array(), $priority = 0) {
        global $wpdb;
        
        // Check if already in queue
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->queue_table} WHERE attachment_id = %d AND status IN ('pending', 'processing')",
            $attachment_id
        ));
        
        if ($existing) {
            return $existing;
        }
        
        // Validate attachment
        if (!wp_attachment_is_image($attachment_id)) {
            return false;
        }
        
        // Get attachment file path
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return false;
        }
        
        // Security validation
        if (!SIO_Security::instance()->validate_image_file($file_path)) {
            return false;
        }
        
        // Insert into queue
        $result = $wpdb->insert(
            $this->queue_table,
            array(
                'attachment_id' => $attachment_id,
                'status' => 'pending',
                'priority' => $priority,
                'attempts' => 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            return false;
        }
        
        $queue_id = $wpdb->insert_id;
        
        // Log the addition
        SIO_Monitor::instance()->log_action(
            $attachment_id,
            'queue_add',
            'success',
            sprintf('Added to processing queue with ID %d', $queue_id)
        );
        
        // Trigger immediate processing if not in batch mode
        $settings = SIO_Settings_Manager::instance()->get_settings();
        if (!$settings['batch_mode']) {
            wp_schedule_single_event(time(), 'sio_process_single_item', array($queue_id));
        }
        
        return $queue_id;
    }
    
    /**
     * Remove item from queue
     *
     * @param int $attachment_id Attachment ID
     * @return bool
     */
    public function remove_from_queue($attachment_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->queue_table,
            array('attachment_id' => $attachment_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get queue status
     *
     * @return array
     */
    public function get_queue_status() {
        global $wpdb;
        
        $status = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->queue_table} GROUP BY status",
            ARRAY_A
        );
        
        $formatted_status = array(
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'total' => 0
        );
        
        foreach ($status as $row) {
            $formatted_status[$row['status']] = intval($row['count']);
            $formatted_status['total'] += intval($row['count']);
        }
        
        // Get current processing status
        $processing_status = get_transient('sio_processing_status');
        if ($processing_status) {
            $formatted_status['current_batch'] = $processing_status;
        }
        
        return $formatted_status;
    }
    
    /**
     * Get queue items
     *
     * @param string $status Status filter
     * @param int $limit Number of items to retrieve
     * @param int $offset Offset for pagination
     * @return array
     */
    public function get_queue_items($status = '', $limit = 20, $offset = 0) {
        global $wpdb;
        
        $where = '';
        $params = array();
        
        if (!empty($status)) {
            $where = 'WHERE status = %s';
            $params[] = $status;
        }
        
        $params[] = $limit;
        $params[] = $offset;
        
        $query = "SELECT q.*, p.post_title, p.post_mime_type 
                  FROM {$this->queue_table} q 
                  LEFT JOIN {$wpdb->posts} p ON q.attachment_id = p.ID 
                  {$where} 
                  ORDER BY q.priority DESC, q.created_at ASC 
                  LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
    }
    
    /**
     * Process batch of items
     *
     * @param int $batch_size Number of items to process
     * @return array Processing results
     */
    public function process_batch($batch_size = null) {
        $settings = SIO_Settings_Manager::instance()->get_settings();
        $batch_size = $batch_size ?: $settings['batch_size'];
        
        // Apply performance optimization to batch size
        $batch_size = apply_filters('sio_batch_size', $batch_size);
        
        // Set processing status
        $this->set_processing_status('running', 0, $batch_size);
        
        // Get pending items
        $items = $this->get_queue_items('pending', $batch_size);
        
        if (empty($items)) {
            $this->set_processing_status('idle');
            return array('processed' => 0, 'errors' => 0);
        }
        
        // Performance monitoring hook - before batch processing
        do_action('sio_before_batch_process', $items);
        
        $processed = 0;
        $errors = 0;
        $start_time = microtime(true);
        
        foreach ($items as $item) {
            // Check execution time limit
            if ((microtime(true) - $start_time) > $settings['max_execution_time']) {
                break;
            }
            
            // Update processing status
            $this->set_processing_status('running', $processed + 1, $batch_size);
            
            // Process item
            $result = $this->process_queue_item($item['id']);
            
            if ($result) {
                $processed++;
            } else {
                $errors++;
            }
            
            // Small delay to prevent overwhelming the server
            usleep(100000); // 0.1 second
        }
        
        // Update processing status
        $this->set_processing_status('completed', $processed, $batch_size);
        
        $results = array(
            'processed' => $processed,
            'errors' => $errors,
            'execution_time' => microtime(true) - $start_time
        );
        
        // Performance monitoring hook - after batch processing
        do_action('sio_after_batch_process', $results);
        
        // Log batch completion
        SIO_Monitor::instance()->log_action(
            0,
            'batch_process',
            'success',
            sprintf('Batch completed. Processed: %d, Errors: %d, Time: %.2fs',
                $processed, $errors, $results['execution_time']),
            $results['execution_time']
        );
        
        return $results;
    }
    
    /**
     * Process single queue item
     *
     * @param int $queue_id Queue item ID
     * @return bool
     */
    public function process_queue_item($queue_id) {
        global $wpdb;
        
        // Get queue item
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->queue_table} WHERE id = %d",
            $queue_id
        ), ARRAY_A);
        
        if (!$item) {
            return false;
        }
        
        // Update status to processing
        $this->update_queue_item_status($queue_id, 'processing');
        
        // Get attachment file
        $file_path = get_attached_file($item['attachment_id']);
        if (!$file_path || !file_exists($file_path)) {
            $this->update_queue_item_status($queue_id, 'failed', 'File not found');
            return false;
        }
        
        // Process the image
        $result = SIO_Image_Processor::instance()->process_image($file_path);
        
        if (is_wp_error($result)) {
            // Handle failure
            $attempts = intval($item['attempts']) + 1;
            
            if ($attempts >= $this->max_retries) {
                $this->update_queue_item_status($queue_id, 'failed', $result->get_error_message());
            } else {
                $this->update_queue_item_status($queue_id, 'pending', $result->get_error_message(), $attempts);
            }
            
            return false;
        }
        
        // Handle success
        $this->update_queue_item_status($queue_id, 'completed');
        
        // Update attachment metadata with processing results
        $metadata = wp_get_attachment_metadata($item['attachment_id']);
        $metadata['sio_processed'] = $result;
        wp_update_attachment_metadata($item['attachment_id'], $metadata);
        
        return true;
    }
    
    /**
     * Update queue item status
     *
     * @param int $queue_id Queue item ID
     * @param string $status New status
     * @param string $error_message Error message (optional)
     * @param int $attempts Number of attempts (optional)
     * @return bool
     */
    private function update_queue_item_status($queue_id, $status, $error_message = '', $attempts = null) {
        global $wpdb;
        
        $data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );
        
        if (!empty($error_message)) {
            $data['error_message'] = $error_message;
        }
        
        if ($attempts !== null) {
            $data['attempts'] = $attempts;
        }
        
        $result = $wpdb->update(
            $this->queue_table,
            $data,
            array('id' => $queue_id),
            array('%s', '%s', '%s', '%d'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Set processing status
     *
     * @param string $status Status
     * @param int $current Current item
     * @param int $total Total items
     */
    private function set_processing_status($status, $current = 0, $total = 0) {
        $this->processing_status = array(
            'status' => $status,
            'current' => $current,
            'total' => $total,
            'percentage' => $total > 0 ? round(($current / $total) * 100, 2) : 0,
            'timestamp' => time()
        );
        
        set_transient('sio_processing_status', $this->processing_status, 300); // 5 minutes
    }
    
    /**
     * Get processing status
     *
     * @return array
     */
    public function get_processing_status() {
        $status = get_transient('sio_processing_status');
        return $status ?: array('status' => 'idle', 'current' => 0, 'total' => 0, 'percentage' => 0);
    }
    
    /**
     * Start batch processing
     *
     * @param array $options Processing options
     * @return bool|WP_Error
     */
    public function start_batch_processing($options = array()) {
        // Security check
        $validation = SIO_Security::instance()->validate_batch_request($options);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Check if already processing
        $status = $this->get_processing_status();
        if ($status['status'] === 'running') {
            return new WP_Error('already_processing', __('Batch processing is already running.', 'smart-image-optimizer'));
        }
        
        // Get pending items count
        $pending_count = $this->get_queue_status()['pending'];
        if ($pending_count === 0) {
            return new WP_Error('no_items', __('No items in queue to process.', 'smart-image-optimizer'));
        }
        
        // Schedule immediate processing
        wp_schedule_single_event(time(), 'sio_batch_process');
        
        // Log start
        SIO_Monitor::instance()->log_action(
            0,
            'batch_start',
            'success',
            sprintf('Batch processing started with %d items', $pending_count)
        );
        
        return true;
    }
    
    /**
     * Stop batch processing
     *
     * @return bool
     */
    public function stop_batch_processing() {
        // Clear processing status
        delete_transient('sio_processing_status');
        
        // Clear scheduled events
        wp_clear_scheduled_hook('sio_batch_process');
        
        // Log stop
        SIO_Monitor::instance()->log_action(0, 'batch_stop', 'success', 'Batch processing stopped');
        
        return true;
    }
    
    /**
     * Clear queue
     *
     * @param string $status Status to clear (optional)
     * @return int Number of items cleared
     */
    public function clear_queue($status = '') {
        global $wpdb;
        
        if (empty($status)) {
            $result = $wpdb->query("TRUNCATE TABLE {$this->queue_table}");
            return $result;
        }
        
        $result = $wpdb->delete(
            $this->queue_table,
            array('status' => $status),
            array('%s')
        );
        
        return $result;
    }
    
    /**
     * Cleanup completed jobs
     *
     * @param int $days Days to keep completed jobs
     */
    public function cleanup_completed_jobs($days = 7) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $result = $wpdb->delete(
            $this->queue_table,
            array(
                'status' => 'completed',
                'updated_at' => array('operator' => '<', 'value' => $cutoff_date)
            ),
            array('%s', '%s')
        );
        
        if ($result > 0) {
            SIO_Monitor::instance()->log_action(
                0,
                'queue_cleanup',
                'success',
                sprintf('Cleaned up %d completed queue items', $result)
            );
        }
    }
    
    /**
     * AJAX: Start batch processing
     */
    public function ajax_start_batch() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('upload_files')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $options = array(
            'batch_size' => isset($_POST['batch_size']) ? intval($_POST['batch_size']) : null
        );
        
        $result = $this->start_batch_processing($options);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => __('Batch processing started successfully.', 'smart-image-optimizer'),
            'status' => $this->get_processing_status()
        ));
    }
    
    /**
     * AJAX: Stop batch processing
     */
    public function ajax_stop_batch() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('upload_files')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $this->stop_batch_processing();
        
        wp_send_json_success(array(
            'message' => __('Batch processing stopped.', 'smart-image-optimizer')
        ));
    }
    
    /**
     * AJAX: Get batch status
     */
    public function ajax_get_batch_status() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('upload_files')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $queue_status = $this->get_queue_status();
        $processing_status = $this->get_processing_status();
        
        wp_send_json_success(array(
            'queue' => $queue_status,
            'processing' => $processing_status
        ));
    }
    
    /**
     * AJAX: Process single item
     */
    public function ajax_process_single() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('upload_files')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        
        if (!$attachment_id) {
            wp_send_json_error(__('Invalid attachment ID.', 'smart-image-optimizer'));
        }
        
        // Add to queue and process immediately
        $queue_id = $this->add_to_queue($attachment_id, array(), 10); // High priority
        
        if (!$queue_id) {
            wp_send_json_error(__('Failed to add item to queue.', 'smart-image-optimizer'));
        }
        
        $result = $this->process_queue_item($queue_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Image processed successfully.', 'smart-image-optimizer')
            ));
        } else {
            wp_send_json_error(__('Failed to process image.', 'smart-image-optimizer'));
        }
    }
    
    /**
     * Add multiple attachments to queue
     *
     * @param array $attachment_ids Array of attachment IDs
     * @param array $options Processing options
     * @return array Results
     */
    public function add_multiple_to_queue($attachment_ids, $options = array()) {
        $results = array(
            'added' => 0,
            'skipped' => 0,
            'errors' => array()
        );
        
        foreach ($attachment_ids as $attachment_id) {
            $queue_id = $this->add_to_queue($attachment_id, $options);
            
            if ($queue_id) {
                $results['added']++;
            } else {
                $results['skipped']++;
                $results['errors'][] = sprintf(
                    __('Failed to add attachment %d to queue', 'smart-image-optimizer'),
                    $attachment_id
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Get queue statistics
     *
     * @return array
     */
    public function get_queue_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Basic counts
        $status_counts = $this->get_queue_status();
        $stats['counts'] = $status_counts;
        
        // Average processing time
        $avg_time = $wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) 
             FROM {$this->queue_table} 
             WHERE status = 'completed'"
        );
        $stats['avg_processing_time'] = $avg_time ? round($avg_time, 2) : 0;
        
        // Success rate
        $total_processed = $status_counts['completed'] + $status_counts['failed'];
        $stats['success_rate'] = $total_processed > 0 ? 
            round(($status_counts['completed'] / $total_processed) * 100, 2) : 0;
        
        // Items processed today
        $today_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->queue_table} 
             WHERE status = 'completed' AND DATE(updated_at) = %s",
            current_time('Y-m-d')
        ));
        $stats['processed_today'] = intval($today_count);
        
        return $stats;
    }
}