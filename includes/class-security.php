<?php
/**
 * Security Class
 *
 * Handles security validation, input sanitization, and protection measures
 *
 * @package SmartImageOptimizer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Class
 */
class SIO_Security {
    
    /**
     * Instance
     *
     * @var SIO_Security
     */
    private static $instance = null;
    
    /**
     * Allowed MIME types
     *
     * @var array
     */
    private $allowed_mime_types = array(
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif'
    );
    
    /**
     * Allowed file extensions
     *
     * @var array
     */
    private $allowed_extensions = array(
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'
    );
    
    /**
     * Maximum file size (in bytes)
     *
     * @var int
     */
    private $max_file_size = 50 * 1024 * 1024; // 50MB
    
    /**
     * Rate limiting data
     *
     * @var array
     */
    private $rate_limits = array();
    
    /**
     * Get instance
     *
     * @return SIO_Security
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
        // Set up security hooks
        add_action('wp_ajax_sio_process_batch', array($this, 'verify_ajax_nonce'));
        add_action('wp_ajax_sio_get_status', array($this, 'verify_ajax_nonce'));
        
        // Filter allowed MIME types based on settings
        add_filter('sio_allowed_mime_types', array($this, 'filter_allowed_mime_types'));
        
        // Set maximum file size from settings or PHP limits
        $this->set_max_file_size();
    }
    
    /**
     * Validate image upload
     *
     * @param array $upload Upload data
     * @return bool
     */
    public function validate_image_upload($upload) {
        // Check if file exists
        if (!isset($upload['file']) || !file_exists($upload['file'])) {
            return false;
        }
        
        // Validate file path
        if (!$this->validate_file_path($upload['file'])) {
            return false;
        }
        
        // Validate MIME type
        if (!$this->validate_mime_type($upload['type'])) {
            return false;
        }
        
        // Validate file extension
        if (!$this->validate_file_extension($upload['file'])) {
            return false;
        }
        
        // Validate file size
        if (!$this->validate_file_size($upload['file'])) {
            return false;
        }
        
        // Validate image content
        if (!$this->validate_image_content($upload['file'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate image file
     *
     * @param string $file_path File path
     * @return bool
     */
    public function validate_image_file($file_path) {
        // Check if file exists
        if (!file_exists($file_path)) {
            return false;
        }
        
        // Validate file path
        if (!$this->validate_file_path($file_path)) {
            return false;
        }
        
        // Get MIME type
        $mime_type = wp_check_filetype($file_path)['type'];
        if (!$this->validate_mime_type($mime_type)) {
            return false;
        }
        
        // Validate file extension
        if (!$this->validate_file_extension($file_path)) {
            return false;
        }
        
        // Validate file size
        if (!$this->validate_file_size($file_path)) {
            return false;
        }
        
        // Validate image content
        if (!$this->validate_image_content($file_path)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate file path for security
     *
     * @param string $file_path File path
     * @return bool
     */
    private function validate_file_path($file_path) {
        // Prevent path traversal attacks
        if (strpos($file_path, '..') !== false) {
            return false;
        }
        
        // Ensure file is within WordPress upload directory
        $upload_dir = wp_upload_dir();
        $real_path = realpath($file_path);
        $upload_path = realpath($upload_dir['basedir']);
        
        if (!$real_path || !$upload_path) {
            return false;
        }
        
        // Check if file is within upload directory
        if (strpos($real_path, $upload_path) !== 0) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate MIME type
     *
     * @param string $mime_type MIME type
     * @return bool
     */
    private function validate_mime_type($mime_type) {
        $allowed_types = apply_filters('sio_allowed_mime_types', $this->allowed_mime_types);
        return in_array($mime_type, $allowed_types, true);
    }
    
    /**
     * Validate file extension
     *
     * @param string $file_path File path
     * @return bool
     */
    private function validate_file_extension($file_path) {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        return in_array($extension, $this->allowed_extensions, true);
    }
    
    /**
     * Validate file size
     *
     * @param string $file_path File path
     * @return bool
     */
    private function validate_file_size($file_path) {
        $file_size = filesize($file_path);
        return $file_size <= $this->max_file_size;
    }
    
    /**
     * Validate image content
     *
     * @param string $file_path File path
     * @return bool
     */
    private function validate_image_content($file_path) {
        // Use getimagesize to validate image content
        $image_info = @getimagesize($file_path);
        
        if (!$image_info) {
            return false;
        }
        
        // Check for valid image dimensions
        if ($image_info[0] <= 0 || $image_info[1] <= 0) {
            return false;
        }
        
        // Check for reasonable dimensions (prevent memory exhaustion)
        $max_dimension = 10000; // 10,000 pixels
        if ($image_info[0] > $max_dimension || $image_info[1] > $max_dimension) {
            return false;
        }
        
        // Validate MIME type matches file content
        $content_mime = $image_info['mime'];
        $file_mime = wp_check_filetype($file_path)['type'];
        
        if ($content_mime !== $file_mime) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Verify AJAX nonce
     */
    public function verify_ajax_nonce() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sio_ajax_nonce')) {
            wp_die(__('Security check failed.', 'smart-image-optimizer'), 403);
        }
    }
    
    /**
     * Generate AJAX nonce
     *
     * @return string
     */
    public function get_ajax_nonce() {
        return wp_create_nonce('sio_ajax_nonce');
    }
    
    /**
     * Check user capabilities
     *
     * @param string $capability Required capability
     * @return bool
     */
    public function check_user_capability($capability = 'manage_options') {
        return current_user_can($capability);
    }
    
    /**
     * Sanitize settings input
     *
     * @param array $input Input data
     * @return array
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        foreach ($input as $key => $value) {
            switch ($key) {
                case 'webp_quality':
                case 'avif_quality':
                case 'max_width':
                case 'max_height':
                case 'compression_level':
                case 'batch_size':
                case 'max_execution_time':
                case 'cleanup_after_days':
                case 'log_retention_days':
                    $sanitized[$key] = absint($value);
                    break;
                    
                case 'auto_process':
                case 'batch_mode':
                case 'enable_webp':
                case 'enable_avif':
                case 'enable_resize':
                case 'preserve_metadata':
                case 'cleanup_originals':
                case 'enable_logging':
                case 'backup_originals':
                case 'progressive_jpeg':
                case 'strip_metadata':
                    $sanitized[$key] = (bool) $value;
                    break;
                    
                case 'allowed_mime_types':
                case 'exclude_sizes':
                    $sanitized[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : array();
                    break;
                    
                default:
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Rate limiting for batch processing
     *
     * @param string $action Action being performed
     * @param int $limit Number of actions allowed
     * @param int $window Time window in seconds
     * @return bool
     */
    public function check_rate_limit($action, $limit = 10, $window = 60) {
        $user_id = get_current_user_id();
        $key = $action . '_' . $user_id;
        $current_time = time();
        
        // Initialize if not exists
        if (!isset($this->rate_limits[$key])) {
            $this->rate_limits[$key] = array();
        }
        
        // Clean old entries
        $this->rate_limits[$key] = array_filter(
            $this->rate_limits[$key],
            function($timestamp) use ($current_time, $window) {
                return ($current_time - $timestamp) < $window;
            }
        );
        
        // Check if limit exceeded
        if (count($this->rate_limits[$key]) >= $limit) {
            return false;
        }
        
        // Add current request
        $this->rate_limits[$key][] = $current_time;
        
        return true;
    }
    
    /**
     * Validate CLI arguments
     *
     * @param array $args CLI arguments
     * @return array|WP_Error
     */
    public function validate_cli_args($args) {
        $validated = array();
        $errors = array();
        
        foreach ($args as $key => $value) {
            switch ($key) {
                case 'quality':
                    $quality = intval($value);
                    if ($quality < 1 || $quality > 100) {
                        $errors[] = sprintf(__('Invalid quality value: %d. Must be between 1 and 100.', 'smart-image-optimizer'), $quality);
                    } else {
                        $validated[$key] = $quality;
                    }
                    break;
                    
                case 'format':
                    $format = strtolower(sanitize_text_field($value));
                    if (!in_array($format, array('webp', 'avif', 'both'))) {
                        $errors[] = sprintf(__('Invalid format: %s. Must be webp, avif, or both.', 'smart-image-optimizer'), $format);
                    } else {
                        $validated[$key] = $format;
                    }
                    break;
                    
                case 'limit':
                    $limit = intval($value);
                    if ($limit < 1 || $limit > 1000) {
                        $errors[] = sprintf(__('Invalid limit: %d. Must be between 1 and 1000.', 'smart-image-optimizer'), $limit);
                    } else {
                        $validated[$key] = $limit;
                    }
                    break;
                    
                case 'background':
                    $validated[$key] = (bool) $value;
                    break;
                    
                case 'force':
                    $validated[$key] = (bool) $value;
                    break;
                    
                case 'older-than':
                    $days = intval($value);
                    if ($days < 1 || $days > 365) {
                        $errors[] = sprintf(__('Invalid older-than value: %d. Must be between 1 and 365 days.', 'smart-image-optimizer'), $days);
                    } else {
                        $validated[$key] = $days;
                    }
                    break;
                    
                default:
                    $validated[$key] = sanitize_text_field($value);
                    break;
            }
        }
        
        if (!empty($errors)) {
            return new WP_Error('invalid_cli_args', implode(' ', $errors));
        }
        
        return $validated;
    }
    
    /**
     * Log security events
     *
     * @param string $event Event type
     * @param string $message Event message
     * @param array $context Additional context
     */
    public function log_security_event($event, $message, $context = array()) {
        if (!SIO_Settings_Manager::instance()->get_setting('enable_logging')) {
            return;
        }
        
        $log_data = array(
            'event' => $event,
            'message' => $message,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'context' => $context,
            'timestamp' => current_time('mysql')
        );
        
        // Log to WordPress error log
        error_log('SIO Security Event: ' . wp_json_encode($log_data));
        
        // Log to plugin monitor if available
        if (class_exists('SIO_Monitor')) {
            SIO_Monitor::instance()->log_action(0, 'security_event', 'warning', $message);
        }
    }
    
    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Filter allowed MIME types based on settings
     *
     * @param array $mime_types Current MIME types
     * @return array
     */
    public function filter_allowed_mime_types($mime_types) {
        $settings = SIO_Settings_Manager::instance()->get_settings();
        
        if (isset($settings['allowed_mime_types']) && is_array($settings['allowed_mime_types'])) {
            return $settings['allowed_mime_types'];
        }
        
        return $mime_types;
    }
    
    /**
     * Set maximum file size
     */
    private function set_max_file_size() {
        // Get PHP limits
        $php_max_upload = $this->parse_size(ini_get('upload_max_filesize'));
        $php_max_post = $this->parse_size(ini_get('post_max_size'));
        $php_memory_limit = $this->parse_size(ini_get('memory_limit'));
        
        // Use the smallest limit
        $limits = array_filter(array($php_max_upload, $php_max_post, $php_memory_limit));
        $php_limit = min($limits);
        
        // Use plugin setting or PHP limit, whichever is smaller
        $plugin_limit = SIO_Settings_Manager::instance()->get_setting('max_file_size', $this->max_file_size);
        $this->max_file_size = min($plugin_limit, $php_limit);
    }
    
    /**
     * Parse size string to bytes
     *
     * @param string $size Size string (e.g., '2M', '512K')
     * @return int
     */
    private function parse_size($size) {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }
        
        return round($size);
    }
    
    /**
     * Get maximum file size
     *
     * @return int
     */
    public function get_max_file_size() {
        return $this->max_file_size;
    }
    
    /**
     * Validate batch processing request
     *
     * @param array $request Request data
     * @return bool|WP_Error
     */
    public function validate_batch_request($request) {
        // Check user capability
        if (!$this->check_user_capability('upload_files')) {
            return new WP_Error('insufficient_capability', __('Insufficient permissions for batch processing.', 'smart-image-optimizer'));
        }
        
        // Check rate limiting
        if (!$this->check_rate_limit('batch_process', 5, 300)) { // 5 requests per 5 minutes
            return new WP_Error('rate_limit_exceeded', __('Rate limit exceeded. Please wait before starting another batch process.', 'smart-image-optimizer'));
        }
        
        // Validate batch size
        $batch_size = isset($request['batch_size']) ? intval($request['batch_size']) : 10;
        if ($batch_size < 1 || $batch_size > 100) {
            return new WP_Error('invalid_batch_size', __('Invalid batch size. Must be between 1 and 100.', 'smart-image-optimizer'));
        }
        
        return true;
    }
    
    /**
     * Clean up security data
     */
    public function cleanup() {
        // Clear rate limiting data older than 1 hour
        $cutoff_time = time() - 3600;
        
        foreach ($this->rate_limits as $key => $timestamps) {
            $this->rate_limits[$key] = array_filter(
                $timestamps,
                function($timestamp) use ($cutoff_time) {
                    return $timestamp > $cutoff_time;
                }
            );
            
            // Remove empty entries
            if (empty($this->rate_limits[$key])) {
                unset($this->rate_limits[$key]);
            }
        }
    }
}