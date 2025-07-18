<?php
/**
 * Plugin Name: Smart Image Optimizer
 * Plugin URI: https://github.com/your-username/smart-image-optimizer
 * Description: Advanced WordPress image optimization plugin that converts images to WebP and AVIF formats with batch processing, WP CLI support, and comprehensive monitoring.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: smart-image-optimizer
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 *
 * @package SmartImageOptimizer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SIO_VERSION', '1.0.0');
define('SIO_PLUGIN_FILE', __FILE__);
define('SIO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SIO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SIO_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Define configuration constants (can be overridden in wp-config.php)
if (!defined('SIO_MAX_EXECUTION_TIME')) {
    define('SIO_MAX_EXECUTION_TIME', 300);
}

if (!defined('SIO_BATCH_SIZE')) {
    define('SIO_BATCH_SIZE', 10);
}

if (!defined('SIO_DEFAULT_WEBP_QUALITY')) {
    define('SIO_DEFAULT_WEBP_QUALITY', 80);
}

if (!defined('SIO_DEFAULT_AVIF_QUALITY')) {
    define('SIO_DEFAULT_AVIF_QUALITY', 70);
}

if (!defined('SIO_ENABLE_CLEANUP')) {
    define('SIO_ENABLE_CLEANUP', false);
}

/**
 * Main plugin class
 */
final class Smart_Image_Optimizer {
    
    /**
     * Plugin instance
     *
     * @var Smart_Image_Optimizer
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     *
     * @return Smart_Image_Optimizer
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
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Activation and deactivation hooks
        register_activation_hook(SIO_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(SIO_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Uninstall hook
        register_uninstall_hook(SIO_PLUGIN_FILE, array('Smart_Image_Optimizer', 'uninstall'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once SIO_PLUGIN_DIR . 'includes/class-settings-manager.php';
        require_once SIO_PLUGIN_DIR . 'includes/class-image-processor.php';
        require_once SIO_PLUGIN_DIR . 'includes/class-batch-processor.php';
        require_once SIO_PLUGIN_DIR . 'includes/class-monitor.php';
        require_once SIO_PLUGIN_DIR . 'includes/class-security.php';
        require_once SIO_PLUGIN_DIR . 'includes/class-server-config.php';
        require_once SIO_PLUGIN_DIR . 'includes/class-performance-optimizer.php';
        require_once SIO_PLUGIN_DIR . 'includes/class-hooks-manager.php';
        
        // Admin interface
        if (is_admin()) {
            require_once SIO_PLUGIN_DIR . 'includes/class-admin-interface.php';
        }
        
        // WP CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            require_once SIO_PLUGIN_DIR . 'includes/class-cli-commands.php';
        }
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Fire before init hook
        do_action('sio_before_init');
        
        // Initialize hooks manager first
        SIO_Hooks_Manager::instance();
        
        // Initialize core components
        SIO_Settings_Manager::instance();
        SIO_Image_Processor::instance();
        SIO_Batch_Processor::instance();
        SIO_Monitor::instance();
        SIO_Security::instance();
        SIO_Server_Config::instance();
        SIO_Performance_Optimizer::instance();
        
        // Initialize admin interface
        if (is_admin()) {
            SIO_Admin_Interface::instance();
        }
        
        // Initialize WP CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            SIO_CLI_Commands::instance();
        }
        
        // Hook into WordPress media upload
        add_filter('wp_handle_upload', array($this, 'handle_upload'), 10, 2);
        add_action('add_attachment', array($this, 'process_new_attachment'));
        
        // Schedule cleanup cron if enabled
        if (!wp_next_scheduled('sio_cleanup_cron')) {
            wp_schedule_event(time(), 'daily', 'sio_cleanup_cron');
        }
        add_action('sio_cleanup_cron', array($this, 'run_cleanup'));
        
        // Fire after init and components loaded hooks
        do_action('sio_after_init');
        do_action('sio_components_loaded');
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'smart-image-optimizer',
            false,
            dirname(SIO_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Handle file upload
     *
     * @param array $upload Upload data
     * @param string $context Upload context
     * @return array
     */
    public function handle_upload($upload, $context = 'upload') {
        if (!isset($upload['file']) || !isset($upload['type'])) {
            return $upload;
        }
        
        // Check if it's an image
        if (strpos($upload['type'], 'image/') !== 0) {
            return $upload;
        }
        
        // Security check
        if (!SIO_Security::instance()->validate_image_upload($upload)) {
            return $upload;
        }
        
        // Process image if auto-processing is enabled
        $settings = SIO_Settings_Manager::instance()->get_settings();
        if ($settings['auto_process']) {
            SIO_Image_Processor::instance()->process_image($upload['file']);
        }
        
        return $upload;
    }
    
    /**
     * Process new attachment
     *
     * @param int $attachment_id Attachment ID
     */
    public function process_new_attachment($attachment_id) {
        $settings = SIO_Settings_Manager::instance()->get_settings();
        
        if ($settings['auto_process'] && $settings['batch_mode']) {
            SIO_Batch_Processor::instance()->add_to_queue($attachment_id);
        }
    }
    
    /**
     * Run cleanup process
     */
    public function run_cleanup() {
        if (SIO_ENABLE_CLEANUP) {
            SIO_Image_Processor::instance()->cleanup_old_files();
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Fire activation hook
        do_action('sio_plugin_activated');
        
        // Create database tables
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule cron events
        if (!wp_next_scheduled('sio_batch_process')) {
            wp_schedule_event(time(), 'hourly', 'sio_batch_process');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log activation
        error_log('Smart Image Optimizer activated');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Fire deactivation hook
        do_action('sio_plugin_deactivated');
        
        // Clear scheduled events
        wp_clear_scheduled_hook('sio_batch_process');
        wp_clear_scheduled_hook('sio_cleanup_cron');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        error_log('Smart Image Optimizer deactivated');
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        // Fire uninstall hook
        do_action('sio_plugin_uninstalled');
        
        // Remove database tables
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sio_queue");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sio_logs");
        
        // Remove options
        delete_option('sio_settings');
        delete_option('sio_version');
        delete_option('sio_stats');
        
        // Clear scheduled events
        wp_clear_scheduled_hook('sio_batch_process');
        wp_clear_scheduled_hook('sio_cleanup_cron');
        
        // Remove transients
        delete_transient('sio_processing_status');
        delete_transient('sio_library_check');
        
        // Log uninstall
        error_log('Smart Image Optimizer uninstalled');
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Queue table
        $queue_table = $wpdb->prefix . 'sio_queue';
        $queue_sql = "CREATE TABLE $queue_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            attachment_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            priority int(11) NOT NULL DEFAULT 0,
            attempts int(11) NOT NULL DEFAULT 0,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY attachment_id (attachment_id),
            KEY status (status),
            KEY priority (priority)
        ) $charset_collate;";
        
        // Logs table
        $logs_table = $wpdb->prefix . 'sio_logs';
        $logs_sql = "CREATE TABLE $logs_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            attachment_id bigint(20) unsigned,
            action varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            message text,
            execution_time float,
            memory_usage bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY attachment_id (attachment_id),
            KEY action (action),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($queue_sql);
        dbDelta($logs_sql);
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        $default_settings = array(
            'auto_process' => true,
            'batch_mode' => false,
            'webp_quality' => SIO_DEFAULT_WEBP_QUALITY,
            'avif_quality' => SIO_DEFAULT_AVIF_QUALITY,
            'enable_webp' => true,
            'enable_avif' => true,
            'enable_resize' => false,
            'max_width' => 1920,
            'max_height' => 1080,
            'compression_level' => 6,
            'preserve_metadata' => false,
            'cleanup_originals' => SIO_ENABLE_CLEANUP,
            'cleanup_after_days' => 30,
            'batch_size' => SIO_BATCH_SIZE,
            'max_execution_time' => SIO_MAX_EXECUTION_TIME,
            'enable_logging' => true,
            'log_retention_days' => 30,
            'enable_auto_serve' => false,
            'auto_htaccess' => false,
            'fallback_conversion' => true,
            'cache_duration' => 86400
        );
        
        add_option('sio_settings', $default_settings);
        add_option('sio_version', SIO_VERSION);
        add_option('sio_stats', array(
            'total_processed' => 0,
            'total_saved_bytes' => 0,
            'webp_converted' => 0,
            'avif_converted' => 0,
            'errors' => 0
        ));
    }
}

/**
 * Initialize the plugin
 */
function smart_image_optimizer() {
    return Smart_Image_Optimizer::instance();
}

// Start the plugin
smart_image_optimizer();