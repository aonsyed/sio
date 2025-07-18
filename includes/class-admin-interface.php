<?php
/**
 * Admin Interface Class
 *
 * Handles WordPress admin interface, settings pages, and dashboard
 *
 * @package SmartImageOptimizer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Interface Class
 */
class SIO_Admin_Interface {
    
    /**
     * Instance
     *
     * @var SIO_Admin_Interface
     */
    private static $instance = null;
    
    /**
     * Admin page hook suffixes
     *
     * @var array
     */
    private $page_hooks = array();
    
    /**
     * Get instance
     *
     * @return SIO_Admin_Interface
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_admin'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_sio_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_sio_reset_settings', array($this, 'ajax_reset_settings'));
        add_action('wp_ajax_sio_refresh_stats', array($this, 'ajax_refresh_stats'));
        add_action('wp_ajax_sio_start_batch', array($this, 'ajax_start_batch'));
        add_action('wp_ajax_sio_stop_batch', array($this, 'ajax_stop_batch'));
        add_action('wp_ajax_sio_clear_queue', array($this, 'ajax_clear_queue'));
        add_action('wp_ajax_sio_add_all_images', array($this, 'ajax_add_all_images'));
        add_action('wp_ajax_sio_get_queue', array($this, 'ajax_get_queue'));
        add_action('wp_ajax_sio_get_queue_stats', array($this, 'ajax_get_queue_stats'));
        add_action('wp_ajax_sio_get_processing_status', array($this, 'ajax_get_processing_status'));
        add_action('wp_ajax_sio_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_sio_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_sio_export_logs', array($this, 'ajax_export_logs'));
        add_action('wp_ajax_sio_export_system_info', array($this, 'ajax_export_system_info'));
        add_action('wp_ajax_sio_generate_htaccess', array($this, 'ajax_generate_htaccess'));
        add_action('wp_ajax_sio_view_htaccess', array($this, 'ajax_view_htaccess'));
        add_action('wp_ajax_sio_generate_nginx', array($this, 'ajax_generate_nginx'));
        add_action('wp_ajax_sio_view_nginx', array($this, 'ajax_view_nginx'));
        add_action('wp_ajax_sio_refresh_libraries', array($this, 'ajax_refresh_libraries'));
        add_action('wp_ajax_sio_test_libraries', array($this, 'ajax_test_libraries'));
        add_action('wp_ajax_sio_debug_libraries', array($this, 'ajax_debug_libraries'));
    }
    
    /**
     * Initialize admin
     */
    public function init_admin() {
        // Register settings
        register_setting('sio_settings_group', 'sio_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
        
        // Add settings sections and fields
        $this->add_settings_sections();
        
        // Add admin notices
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        $this->page_hooks['main'] = add_menu_page(
            __('Smart Image Optimizer', 'smart-image-optimizer'),
            __('Image Optimizer', 'smart-image-optimizer'),
            'manage_options',
            'smart-image-optimizer',
            array($this, 'render_main_page'),
            'dashicons-format-image',
            30
        );
        
        // Settings submenu
        $this->page_hooks['settings'] = add_submenu_page(
            'smart-image-optimizer',
            __('Settings', 'smart-image-optimizer'),
            __('Settings', 'smart-image-optimizer'),
            'manage_options',
            'sio-settings',
            array($this, 'render_settings_page')
        );
        
        // Batch Processing submenu
        $this->page_hooks['batch'] = add_submenu_page(
            'smart-image-optimizer',
            __('Batch Processing', 'smart-image-optimizer'),
            __('Batch Processing', 'smart-image-optimizer'),
            'upload_files',
            'sio-batch',
            array($this, 'render_batch_page')
        );
        
        // Monitor submenu
        $this->page_hooks['monitor'] = add_submenu_page(
            'smart-image-optimizer',
            __('Monitor', 'smart-image-optimizer'),
            __('Monitor', 'smart-image-optimizer'),
            'manage_options',
            'sio-monitor',
            array($this, 'render_monitor_page')
        );
        
        // System Info submenu
        $this->page_hooks['info'] = add_submenu_page(
            'smart-image-optimizer',
            __('System Info', 'smart-image-optimizer'),
            __('System Info', 'smart-image-optimizer'),
            'manage_options',
            'sio-info',
            array($this, 'render_info_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook_suffix Current admin page hook suffix
     */
    public function enqueue_admin_scripts($hook_suffix) {
        // Only load on our admin pages
        if (!in_array($hook_suffix, $this->page_hooks)) {
            return;
        }
        
        // Enqueue WordPress media scripts for file handling
        wp_enqueue_media();
        
        // Enqueue admin styles
        wp_enqueue_style(
            'sio-admin-style',
            SIO_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            SIO_VERSION
        );
        
        // Enqueue admin scripts
        wp_enqueue_script(
            'sio-admin-script',
            SIO_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery', 'wp-util'),
            SIO_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('sio-admin-script', 'sioAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => SIO_Security::instance()->get_ajax_nonce(),
            'strings' => array(
                'processing' => __('Processing...', 'smart-image-optimizer'),
                'completed' => __('Completed', 'smart-image-optimizer'),
                'error' => __('Error', 'smart-image-optimizer'),
                'confirm_reset' => __('Are you sure you want to reset all settings to defaults?', 'smart-image-optimizer'),
                'confirm_clear_queue' => __('Are you sure you want to clear the processing queue?', 'smart-image-optimizer'),
                'confirm_clear_logs' => __('Are you sure you want to clear all logs?', 'smart-image-optimizer')
            )
        ));
    }
    
    /**
     * Render main dashboard page
     */
    public function render_main_page() {
        $stats = SIO_Monitor::instance()->get_statistics();
        $queue_status = SIO_Batch_Processor::instance()->get_queue_status();
        $processing_status = SIO_Batch_Processor::instance()->get_processing_status();
        $system_info = SIO_Monitor::instance()->get_system_info();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Smart Image Optimizer', 'smart-image-optimizer'); ?></h1>
            
            <div class="sio-dashboard">
                <!-- Overview Cards -->
                <div class="sio-cards-grid">
                    <div class="sio-card">
                        <h3><?php _e('Total Processed', 'smart-image-optimizer'); ?></h3>
                        <div class="sio-stat-number"><?php echo number_format($stats['plugin_stats']['total_processed'] ?? 0); ?></div>
                    </div>
                    
                    <div class="sio-card">
                        <h3><?php _e('WebP Converted', 'smart-image-optimizer'); ?></h3>
                        <div class="sio-stat-number"><?php echo number_format($stats['plugin_stats']['webp_converted'] ?? 0); ?></div>
                    </div>
                    
                    <div class="sio-card">
                        <h3><?php _e('AVIF Converted', 'smart-image-optimizer'); ?></h3>
                        <div class="sio-stat-number"><?php echo number_format($stats['plugin_stats']['avif_converted'] ?? 0); ?></div>
                    </div>
                    
                    <div class="sio-card">
                        <h3><?php _e('Queue Status', 'smart-image-optimizer'); ?></h3>
                        <div class="sio-queue-status">
                            <div><?php printf(__('Pending: %d', 'smart-image-optimizer'), $queue_status['pending']); ?></div>
                            <div><?php printf(__('Processing: %d', 'smart-image-optimizer'), $queue_status['processing']); ?></div>
                            <div><?php printf(__('Completed: %d', 'smart-image-optimizer'), $queue_status['completed']); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Processing Status -->
                <?php if ($processing_status['status'] === 'running'): ?>
                <div class="sio-processing-status">
                    <h3><?php _e('Current Processing Status', 'smart-image-optimizer'); ?></h3>
                    <div class="sio-progress-bar">
                        <div class="sio-progress-fill" style="width: <?php echo $processing_status['percentage']; ?>%"></div>
                    </div>
                    <p><?php printf(__('Processing %d of %d images (%d%%)', 'smart-image-optimizer'), 
                        $processing_status['current'], $processing_status['total'], $processing_status['percentage']); ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Quick Actions -->
                <div class="sio-quick-actions">
                    <h3><?php _e('Quick Actions', 'smart-image-optimizer'); ?></h3>
                    <div class="sio-actions-grid">
                        <a href="<?php echo admin_url('admin.php?page=sio-batch'); ?>" class="button button-primary">
                            <?php _e('Start Batch Processing', 'smart-image-optimizer'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=sio-settings'); ?>" class="button">
                            <?php _e('Configure Settings', 'smart-image-optimizer'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=sio-monitor'); ?>" class="button">
                            <?php _e('View Logs', 'smart-image-optimizer'); ?>
                        </a>
                        <button type="button" class="button" id="sio-refresh-stats">
                            <?php _e('Refresh Statistics', 'smart-image-optimizer'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- System Status -->
                <div class="sio-system-status">
                    <h3><?php _e('System Status', 'smart-image-optimizer'); ?></h3>
                    
                    <?php
                    $libraries = SIO_Image_Processor::instance()->get_available_libraries();
                    $current_library = SIO_Image_Processor::instance()->get_current_library();
                    ?>
                    
                    <!-- Image Libraries Status -->
                    <div class="sio-libraries-section">
                        <h4><?php _e('Image Processing Libraries', 'smart-image-optimizer'); ?></h4>
                        
                        <?php if (empty($libraries)): ?>
                            <div class="notice notice-error inline">
                                <p><?php _e('No image processing libraries detected. Please install ImageMagick or ensure GD is enabled.', 'smart-image-optimizer'); ?></p>
                                
                                <?php
                                // Show diagnostic information when no libraries are detected
                                $debug_info = SIO_Image_Processor::instance()->get_library_debug_info();
                                if (!empty($debug_info['diagnostic_info'])):
                                ?>
                                <div class="sio-diagnostic-info">
                                    <h5><?php _e('Diagnostic Information:', 'smart-image-optimizer'); ?></h5>
                                    <ul>
                                        <?php foreach ($debug_info['diagnostic_info'] as $info): ?>
                                            <li><?php echo esc_html($info); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <table class="widefat">
                                <thead>
                                    <tr>
                                        <th><?php _e('Library', 'smart-image-optimizer'); ?></th>
                                        <th><?php _e('Version', 'smart-image-optimizer'); ?></th>
                                        <th><?php _e('WebP Support', 'smart-image-optimizer'); ?></th>
                                        <th><?php _e('AVIF Support', 'smart-image-optimizer'); ?></th>
                                        <th><?php _e('Detection Method', 'smart-image-optimizer'); ?></th>
                                        <th><?php _e('Status', 'smart-image-optimizer'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($libraries as $lib_name => $lib_info): ?>
                                        <?php
                                        $is_current = ($lib_name === $current_library);
                                        $status_class = $is_current ? 'active' : 'available';
                                        $status_text = $is_current ? __('Active', 'smart-image-optimizer') : __('Available', 'smart-image-optimizer');
                                        ?>
                                        <tr class="<?php echo esc_attr($status_class); ?>">
                                            <td><strong><?php echo esc_html($lib_info['name']); ?></strong></td>
                                            <td><?php echo esc_html($lib_info['version']); ?></td>
                                            <td>
                                                <?php echo $lib_info['supports_webp'] ?
                                                    '<span class="sio-status-success">✓</span>' :
                                                    '<span class="sio-status-error">✗</span>'; ?>
                                            </td>
                                            <td>
                                                <?php echo $lib_info['supports_avif'] ?
                                                    '<span class="sio-status-success">✓</span>' :
                                                    '<span class="sio-status-error">✗</span>'; ?>
                                            </td>
                                            <td><?php echo esc_html($lib_info['detection_method'] ?? __('Standard', 'smart-image-optimizer')); ?></td>
                                            <td><span class="status-<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                        
                        <!-- Library Management Buttons -->
                        <div class="sio-library-actions" style="margin-top: 10px;">
                            <button type="button" class="button" id="sio-refresh-libraries">
                                <?php _e('Refresh Libraries', 'smart-image-optimizer'); ?>
                            </button>
                            <button type="button" class="button" id="sio-test-libraries">
                                <?php _e('Test Functionality', 'smart-image-optimizer'); ?>
                            </button>
                            <button type="button" class="button" id="sio-debug-libraries">
                                <?php _e('Show Debug Info', 'smart-image-optimizer'); ?>
                            </button>
                        </div>
                        
                        <!-- AJAX Result Containers -->
                        <div id="sio-library-refresh-result" class="sio-ajax-result" style="display: none;"></div>
                        <div id="sio-library-test-result" class="sio-ajax-result" style="display: none;"></div>
                        <div id="sio-library-debug-result" class="sio-ajax-result" style="display: none;"></div>
                    </div>
                    
                    <!-- System Information -->
                    <table class="widefat" style="margin-top: 20px;">
                        <tbody>
                            <tr>
                                <td><?php _e('Memory Limit', 'smart-image-optimizer'); ?></td>
                                <td><?php echo esc_html($system_info['php']['memory_limit']); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('Max Execution Time', 'smart-image-optimizer'); ?></td>
                                <td><?php echo esc_html($system_info['php']['max_execution_time']) . 's'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $settings = SIO_Settings_Manager::instance()->get_settings();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Smart Image Optimizer Settings', 'smart-image-optimizer'); ?></h1>
            
            <form method="post" action="options.php" id="sio-settings-form">
                <?php
                settings_fields('sio_settings_group');
                do_settings_sections('sio_settings_page');
                ?>
                
                <div class="sio-settings-tabs">
                    <nav class="nav-tab-wrapper">
                        <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', 'smart-image-optimizer'); ?></a>
                        <a href="#formats" class="nav-tab"><?php _e('Formats', 'smart-image-optimizer'); ?></a>
                        <a href="#processing" class="nav-tab"><?php _e('Processing', 'smart-image-optimizer'); ?></a>
                        <a href="#server" class="nav-tab"><?php _e('Server Config', 'smart-image-optimizer'); ?></a>
                        <a href="#advanced" class="nav-tab"><?php _e('Advanced', 'smart-image-optimizer'); ?></a>
                    </nav>
                    
                    <!-- General Settings -->
                    <div id="general" class="tab-content active">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Auto Process', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sio_settings[auto_process]" value="1" <?php checked($settings['auto_process']); ?> />
                                        <?php _e('Automatically process uploaded images', 'smart-image-optimizer'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Batch Mode', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sio_settings[batch_mode]" value="1" <?php checked($settings['batch_mode']); ?> />
                                        <?php _e('Use batch processing for uploaded images', 'smart-image-optimizer'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Enable Logging', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sio_settings[enable_logging]" value="1" <?php checked($settings['enable_logging']); ?> />
                                        <?php _e('Enable activity logging', 'smart-image-optimizer'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Format Settings -->
                    <div id="formats" class="tab-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('WebP Conversion', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sio_settings[enable_webp]" value="1" <?php checked($settings['enable_webp']); ?> />
                                        <?php _e('Enable WebP conversion', 'smart-image-optimizer'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('WebP Quality', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <input type="number" name="sio_settings[webp_quality]" value="<?php echo esc_attr($settings['webp_quality']); ?>" min="1" max="100" class="small-text" />
                                    <p class="description"><?php _e('Quality for WebP images (1-100)', 'smart-image-optimizer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('AVIF Conversion', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sio_settings[enable_avif]" value="1" <?php checked($settings['enable_avif']); ?> />
                                        <?php _e('Enable AVIF conversion', 'smart-image-optimizer'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('AVIF Quality', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <input type="number" name="sio_settings[avif_quality]" value="<?php echo esc_attr($settings['avif_quality']); ?>" min="1" max="100" class="small-text" />
                                    <p class="description"><?php _e('Quality for AVIF images (1-100)', 'smart-image-optimizer'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Processing Settings -->
                    <div id="processing" class="tab-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Enable Resize', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sio_settings[enable_resize]" value="1" <?php checked($settings['enable_resize']); ?> />
                                        <?php _e('Enable image resizing', 'smart-image-optimizer'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Max Width', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <input type="number" name="sio_settings[max_width]" value="<?php echo esc_attr($settings['max_width']); ?>" min="100" max="5000" class="small-text" />
                                    <p class="description"><?php _e('Maximum image width in pixels', 'smart-image-optimizer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Max Height', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <input type="number" name="sio_settings[max_height]" value="<?php echo esc_attr($settings['max_height']); ?>" min="100" max="5000" class="small-text" />
                                    <p class="description"><?php _e('Maximum image height in pixels', 'smart-image-optimizer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Batch Size', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <input type="number" name="sio_settings[batch_size]" value="<?php echo esc_attr($settings['batch_size']); ?>" min="1" max="100" class="small-text" />
                                    <p class="description"><?php _e('Number of images to process in each batch', 'smart-image-optimizer'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Server Configuration Settings -->
                    <div id="server" class="tab-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Automatic Serving', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sio_settings[enable_auto_serve]" value="1" <?php checked($settings['enable_auto_serve']); ?> />
                                        <?php _e('Enable automatic serving of optimized images', 'smart-image-optimizer'); ?>
                                    </label>
                                    <p class="description"><?php _e('Automatically serve WebP/AVIF images when supported by the browser', 'smart-image-optimizer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Auto .htaccess', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sio_settings[auto_htaccess]" value="1" <?php checked($settings['auto_htaccess']); ?> />
                                        <?php _e('Automatically update .htaccess rules', 'smart-image-optimizer'); ?>
                                    </label>
                                    <p class="description"><?php _e('Automatically add/update Apache .htaccess rules for optimized image serving', 'smart-image-optimizer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Fallback Conversion', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sio_settings[fallback_conversion]" value="1" <?php checked($settings['fallback_conversion']); ?> />
                                        <?php _e('Enable on-the-fly conversion fallback', 'smart-image-optimizer'); ?>
                                    </label>
                                    <p class="description"><?php _e('Convert images on-the-fly when optimized versions are not available', 'smart-image-optimizer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Cache Duration', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <input type="number" name="sio_settings[cache_duration]" value="<?php echo esc_attr($settings['cache_duration']); ?>" min="3600" max="31536000" class="regular-text" />
                                    <p class="description"><?php _e('Cache duration in seconds for served images (3600 = 1 hour, 86400 = 1 day)', 'smart-image-optimizer'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <h3><?php _e('Server Configuration Management', 'smart-image-optimizer'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Apache Configuration', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <button type="button" class="button" id="sio-generate-htaccess">
                                        <?php _e('Generate .htaccess Rules', 'smart-image-optimizer'); ?>
                                    </button>
                                    <button type="button" class="button" id="sio-view-htaccess">
                                        <?php _e('View Current Rules', 'smart-image-optimizer'); ?>
                                    </button>
                                    <p class="description"><?php _e('Generate and manage Apache .htaccess rules for optimized image serving', 'smart-image-optimizer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Nginx Configuration', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <button type="button" class="button" id="sio-generate-nginx">
                                        <?php _e('Generate Nginx Config', 'smart-image-optimizer'); ?>
                                    </button>
                                    <button type="button" class="button" id="sio-view-nginx">
                                        <?php _e('View Configuration', 'smart-image-optimizer'); ?>
                                    </button>
                                    <p class="description"><?php _e('Generate Nginx configuration snippet for optimized image serving', 'smart-image-optimizer'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <div id="sio-server-config-output" style="display: none;">
                            <h4><?php _e('Configuration Output', 'smart-image-optimizer'); ?></h4>
                            <textarea id="sio-config-content" rows="15" cols="80" readonly style="width: 100%; font-family: monospace;"></textarea>
                            <p>
                                <button type="button" class="button" id="sio-copy-config">
                                    <?php _e('Copy to Clipboard', 'smart-image-optimizer'); ?>
                                </button>
                                <button type="button" class="button" id="sio-download-config">
                                    <?php _e('Download File', 'smart-image-optimizer'); ?>
                                </button>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Advanced Settings -->
                    <div id="advanced" class="tab-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Compression Level', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <input type="number" name="sio_settings[compression_level]" value="<?php echo esc_attr($settings['compression_level']); ?>" min="0" max="9" class="small-text" />
                                    <p class="description"><?php _e('Compression level (0-9, higher = more compression)', 'smart-image-optimizer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Strip Metadata', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sio_settings[strip_metadata]" value="1" <?php checked($settings['strip_metadata']); ?> />
                                        <?php _e('Remove image metadata (EXIF, etc.)', 'smart-image-optimizer'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Cleanup Originals', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="sio_settings[cleanup_originals]" value="1" <?php checked($settings['cleanup_originals']); ?> />
                                        <?php _e('Automatically cleanup original files', 'smart-image-optimizer'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Cleanup After Days', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <input type="number" name="sio_settings[cleanup_after_days]" value="<?php echo esc_attr($settings['cleanup_after_days']); ?>" min="1" max="365" class="small-text" />
                                    <p class="description"><?php _e('Days to keep original files before cleanup', 'smart-image-optimizer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Max Execution Time', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <input type="number" name="sio_settings[max_execution_time]" value="<?php echo esc_attr($settings['max_execution_time']); ?>" min="30" max="3600" class="small-text" />
                                    <p class="description"><?php _e('Maximum execution time in seconds', 'smart-image-optimizer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Log Retention', 'smart-image-optimizer'); ?></th>
                                <td>
                                    <input type="number" name="sio_settings[log_retention_days]" value="<?php echo esc_attr($settings['log_retention_days']); ?>" min="1" max="365" class="small-text" />
                                    <p class="description"><?php _e('Days to keep log entries', 'smart-image-optimizer'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <p class="submit">
                    <?php submit_button(__('Save Settings', 'smart-image-optimizer'), 'primary', 'submit', false); ?>
                    <button type="button" class="button" id="sio-reset-settings">
                        <?php _e('Reset to Defaults', 'smart-image-optimizer'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render batch processing page
     */
    public function render_batch_page() {
        $queue_status = SIO_Batch_Processor::instance()->get_queue_status();
        $processing_status = SIO_Batch_Processor::instance()->get_processing_status();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Batch Processing', 'smart-image-optimizer'); ?></h1>
            
            <div class="sio-batch-interface">
                <!-- Queue Status -->
                <div class="sio-queue-overview">
                    <h3><?php _e('Queue Status', 'smart-image-optimizer'); ?></h3>
                    <div class="sio-queue-stats">
                        <div class="sio-stat">
                            <span class="sio-stat-label"><?php _e('Pending', 'smart-image-optimizer'); ?></span>
                            <span class="sio-stat-value" id="sio-pending-count"><?php echo $queue_status['pending']; ?></span>
                        </div>
                        <div class="sio-stat">
                            <span class="sio-stat-label"><?php _e('Processing', 'smart-image-optimizer'); ?></span>
                            <span class="sio-stat-value" id="sio-processing-count"><?php echo $queue_status['processing']; ?></span>
                        </div>
                        <div class="sio-stat">
                            <span class="sio-stat-label"><?php _e('Completed', 'smart-image-optimizer'); ?></span>
                            <span class="sio-stat-value" id="sio-completed-count"><?php echo $queue_status['completed']; ?></span>
                        </div>
                        <div class="sio-stat">
                            <span class="sio-stat-label"><?php _e('Failed', 'smart-image-optimizer'); ?></span>
                            <span class="sio-stat-value" id="sio-failed-count"><?php echo $queue_status['failed']; ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Processing Controls -->
                <div class="sio-processing-controls">
                    <h3><?php _e('Processing Controls', 'smart-image-optimizer'); ?></h3>
                    <div class="sio-controls-grid">
                        <button type="button" class="button button-primary" id="sio-start-batch" 
                                <?php echo $processing_status['status'] === 'running' ? 'disabled' : ''; ?>>
                            <?php _e('Start Batch Processing', 'smart-image-optimizer'); ?>
                        </button>
                        <button type="button" class="button" id="sio-stop-batch"
                                <?php echo $processing_status['status'] !== 'running' ? 'disabled' : ''; ?>>
                            <?php _e('Stop Processing', 'smart-image-optimizer'); ?>
                        </button>
                        <button type="button" class="button" id="sio-clear-queue">
                            <?php _e('Clear Queue', 'smart-image-optimizer'); ?>
                        </button>
                        <button type="button" class="button" id="sio-add-all-images">
                            <?php _e('Add All Images', 'smart-image-optimizer'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Progress Bar -->
                <?php if ($processing_status['status'] === 'running'): ?>
                <div class="sio-progress-section">
                    <h3><?php _e('Processing Progress', 'smart-image-optimizer'); ?></h3>
                    <div class="sio-progress-bar">
                        <div class="sio-progress-fill" id="sio-progress-fill" 
                             style="width: <?php echo $processing_status['percentage']; ?>%"></div>
                    </div>
                    <p id="sio-progress-text">
                        <?php printf(__('Processing %d of %d images (%d%%)', 'smart-image-optimizer'), 
                            $processing_status['current'], $processing_status['total'], $processing_status['percentage']); ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <!-- Queue Items -->
                <div class="sio-queue-items">
                    <h3><?php _e('Queue Items', 'smart-image-optimizer'); ?></h3>
                    <div class="sio-queue-filters">
                        <select id="sio-status-filter">
                            <option value=""><?php _e('All Statuses', 'smart-image-optimizer'); ?></option>
                            <option value="pending"><?php _e('Pending', 'smart-image-optimizer'); ?></option>
                            <option value="processing"><?php _e('Processing', 'smart-image-optimizer'); ?></option>
                            <option value="completed"><?php _e('Completed', 'smart-image-optimizer'); ?></option>
                            <option value="failed"><?php _e('Failed', 'smart-image-optimizer'); ?></option>
                        </select>
                        <button type="button" class="button" id="sio-refresh-queue">
                            <?php _e('Refresh', 'smart-image-optimizer'); ?>
                        </button>
                    </div>
                    <div id="sio-queue-list">
                        <!-- Queue items will be loaded via AJAX -->
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render monitor page
     */
    public function render_monitor_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Monitor & Logs', 'smart-image-optimizer'); ?></h1>
            
            <div class="sio-monitor-interface">
                <!-- Log Filters -->
                <div class="sio-log-filters">
                    <div class="sio-filter-row">
                        <select id="sio-log-status-filter">
                            <option value=""><?php _e('All Statuses', 'smart-image-optimizer'); ?></option>
                            <option value="success"><?php _e('Success', 'smart-image-optimizer'); ?></option>
                            <option value="error"><?php _e('Error', 'smart-image-optimizer'); ?></option>
                            <option value="warning"><?php _e('Warning', 'smart-image-optimizer'); ?></option>
                            <option value="info"><?php _e('Info', 'smart-image-optimizer'); ?></option>
                        </select>
                        
filter">
                            <option value=""><?php _e('All Actions', 'smart-image-optimizer'); ?></option>
                            <option value="image_processing"><?php _e('Image Processing', 'smart-image-optimizer'); ?></option>
                            <option value="batch_process"><?php _e('Batch Process', 'smart-image-optimizer'); ?></option>
                            <option value="settings_update"><?php _e('Settings Update', 'smart-image-optimizer'); ?></option>
                            <option value="cleanup"><?php _e('Cleanup', 'smart-image-optimizer'); ?></option>
                        </select>
                        
                        <input type="date" id="sio-log-date-from" placeholder="<?php _e('From Date', 'smart-image-optimizer'); ?>" />
                        <input type="date" id="sio-log-date-to" placeholder="<?php _e('To Date', 'smart-image-optimizer'); ?>" />
                        
                        <button type="button" class="button" id="sio-filter-logs">
                            <?php _e('Filter', 'smart-image-optimizer'); ?>
                        </button>
                        <button type="button" class="button" id="sio-clear-log-filters">
                            <?php _e('Clear Filters', 'smart-image-optimizer'); ?>
                        </button>
                        <button type="button" class="button" id="sio-export-logs">
                            <?php _e('Export CSV', 'smart-image-optimizer'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="sio-clear-logs">
                            <?php _e('Clear Logs', 'smart-image-optimizer'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Logs Table -->
                <div class="sio-logs-table">
                    <div id="sio-logs-container">
                        <!-- Logs will be loaded via AJAX -->
                    </div>
                    <div class="sio-pagination" id="sio-logs-pagination">
                        <!-- Pagination will be loaded via AJAX -->
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render system info page
     */
    public function render_info_page() {
        $system_info = SIO_Monitor::instance()->get_system_info();
        $stats = SIO_Monitor::instance()->get_statistics();
        
        ?>
        <div class="wrap">
            <h1><?php _e('System Information', 'smart-image-optimizer'); ?></h1>
            
            <div class="sio-system-info">
                <!-- Plugin Information -->
                <div class="sio-info-section">
                    <h3><?php _e('Plugin Information', 'smart-image-optimizer'); ?></h3>
                    <table class="widefat">
                        <tbody>
                            <tr>
                                <td><?php _e('Version', 'smart-image-optimizer'); ?></td>
                                <td><?php echo esc_html($system_info['plugin']['version']); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('Current Image Library', 'smart-image-optimizer'); ?></td>
                                <td><?php echo esc_html($system_info['plugin']['current_library'] ?: __('None', 'smart-image-optimizer')); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Image Libraries -->
                <div class="sio-info-section">
                    <h3><?php _e('Available Image Libraries', 'smart-image-optimizer'); ?></h3>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('Library', 'smart-image-optimizer'); ?></th>
                                <th><?php _e('Version', 'smart-image-optimizer'); ?></th>
                                <th><?php _e('WebP Support', 'smart-image-optimizer'); ?></th>
                                <th><?php _e('AVIF Support', 'smart-image-optimizer'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($system_info['plugin']['image_libraries'] as $lib => $details): ?>
                            <tr>
                                <td><?php echo esc_html($details['name']); ?></td>
                                <td><?php echo esc_html($details['version']); ?></td>
                                <td>
                                    <?php echo $details['supports_webp'] ? 
                                        '<span class="sio-status-success">✓</span>' : 
                                        '<span class="sio-status-error">✗</span>'; ?>
                                </td>
                                <td>
                                    <?php echo $details['supports_avif'] ? 
                                        '<span class="sio-status-success">✓</span>' : 
                                        '<span class="sio-status-error">✗</span>'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Server Information -->
                <div class="sio-info-section">
                    <h3><?php _e('Server Information', 'smart-image-optimizer'); ?></h3>
                    <table class="widefat">
                        <tbody>
                            <tr>
                                <td><?php _e('PHP Version', 'smart-image-optimizer'); ?></td>
                                <td><?php echo esc_html($system_info['php']['version']); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('Memory Limit', 'smart-image-optimizer'); ?></td>
                                <td><?php echo esc_html($system_info['php']['memory_limit']); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('Max Execution Time', 'smart-image-optimizer'); ?></td>
                                <td><?php echo esc_html($system_info['php']['max_execution_time']) . 's'; ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('Server Software', 'smart-image-optimizer'); ?></td>
                                <td><?php echo esc_html($system_info['server']['software']); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('Operating System', 'smart-image-optimizer'); ?></td>
                                <td><?php echo esc_html($system_info['server']['os']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- WordPress Information -->
                <div class="sio-info-section">
                    <h3><?php _e('WordPress Information', 'smart-image-optimizer'); ?></h3>
                    <table class="widefat">
                        <tbody>
                            <tr>
                                <td><?php _e('Version', 'smart-image-optimizer'); ?></td>
                                <td><?php echo esc_html($system_info['wordpress']['version']); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('Multisite', 'smart-image-optimizer'); ?></td>
                                <td><?php echo $system_info['wordpress']['multisite'] ? __('Yes', 'smart-image-optimizer') : __('No', 'smart-image-optimizer'); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('Memory Limit', 'smart-image-optimizer'); ?></td>
                                <td><?php echo esc_html($system_info['wordpress']['memory_limit']); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('Upload Max Filesize', 'smart-image-optimizer'); ?></td>
                                <td><?php echo esc_html($system_info['wordpress']['upload_max_filesize']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Statistics -->
                <div class="sio-info-section">
                    <h3><?php _e('Processing Statistics', 'smart-image-optimizer'); ?></h3>
                    <table class="widefat">
                        <tbody>
                            <tr>
                                <td><?php _e('Total Processed', 'smart-image-optimizer'); ?></td>
                                <td><?php echo number_format($stats['plugin_stats']['total_processed'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('WebP Converted', 'smart-image-optimizer'); ?></td>
                                <td><?php echo number_format($stats['plugin_stats']['webp_converted'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('AVIF Converted', 'smart-image-optimizer'); ?></td>
                                <td><?php echo number_format($stats['plugin_stats']['avif_converted'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('Total Errors', 'smart-image-optimizer'); ?></td>
                                <td><?php echo number_format($stats['plugin_stats']['errors'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('Average Execution Time', 'smart-image-optimizer'); ?></td>
                                <td><?php echo number_format($stats['avg_execution_time'], 3) . 's'; ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('Total Execution Time', 'smart-image-optimizer'); ?></td>
                                <td><?php echo number_format($stats['total_execution_time'], 2) . 's'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Export System Info -->
                <div class="sio-info-section">
                    <h3><?php _e('Export Information', 'smart-image-optimizer'); ?></h3>
                    <p><?php _e('Export system information for debugging or support purposes.', 'smart-image-optimizer'); ?></p>
                    <button type="button" class="button" id="sio-export-system-info">
                        <?php _e('Export System Info', 'smart-image-optimizer'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add settings sections and fields
     */
    private function add_settings_sections() {
        // Settings are handled in the render_settings_page method
        // This method can be used for additional WordPress Settings API integration if needed
    }
    
    /**
     * Sanitize settings
     *
     * @param array $input Input settings
     * @return array Sanitized settings
     */
    public function sanitize_settings($input) {
        return SIO_Security::instance()->sanitize_settings($input);
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // Check for settings updates
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Settings saved successfully.', 'smart-image-optimizer'); ?></p>
            </div>
            <?php
        }
        
        // Check for system issues
        $current_library = SIO_Image_Processor::instance()->get_current_library();
        if (!$current_library) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php _e('Smart Image Optimizer: No image processing library (ImageMagick or GD) is available. The plugin will not function properly.', 'smart-image-optimizer'); ?>
                    <a href="<?php echo admin_url('admin.php?page=sio-info'); ?>"><?php _e('View System Info', 'smart-image-optimizer'); ?></a>
                </p>
            </div>
            <?php
        }
        
        // Check for WebP/AVIF support
        $settings = SIO_Settings_Manager::instance()->get_settings();
        if ($settings['enable_webp'] && !SIO_Image_Processor::instance()->supports_format('webp')) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php _e('Smart Image Optimizer: WebP conversion is enabled but not supported by your current image library.', 'smart-image-optimizer'); ?>
                    <a href="<?php echo admin_url('admin.php?page=sio-info'); ?>"><?php _e('View System Info', 'smart-image-optimizer'); ?></a>
                </p>
            </div>
            <?php
        }
        
        if ($settings['enable_avif'] && !SIO_Image_Processor::instance()->supports_format('avif')) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php _e('Smart Image Optimizer: AVIF conversion is enabled but not supported by your current image library.', 'smart-image-optimizer'); ?>
                    <a href="<?php echo admin_url('admin.php?page=sio-info'); ?>"><?php _e('View System Info', 'smart-image-optimizer'); ?></a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('manage_options')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        
        $result = SIO_Settings_Manager::instance()->update_settings($settings, 'ui');
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Settings saved successfully.', 'smart-image-optimizer')
            ));
        } else {
            wp_send_json_error(__('Failed to save settings.', 'smart-image-optimizer'));
        }
    }
    /**
     * AJAX: Reset settings
     */
    public function ajax_reset_settings() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('manage_options')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $result = SIO_Settings_Manager::instance()->reset_settings();
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Settings reset successfully.', 'smart-image-optimizer')
            ));
        } else {
            wp_send_json_error(__('Failed to reset settings.', 'smart-image-optimizer'));
        }
    }
    
    /**
     * AJAX: Refresh statistics
     */
    public function ajax_refresh_stats() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('manage_options')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $stats = SIO_Monitor::instance()->get_statistics();
        
        wp_send_json_success($stats);
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
        
        $result = SIO_Batch_Processor::instance()->start_processing();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => __('Batch processing started.', 'smart-image-optimizer')
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
        
        $result = SIO_Batch_Processor::instance()->stop_processing();
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Batch processing stopped.', 'smart-image-optimizer')
            ));
        } else {
            wp_send_json_error(__('Failed to stop batch processing.', 'smart-image-optimizer'));
        }
    }
    
    /**
     * AJAX: Clear processing queue
     */
    public function ajax_clear_queue() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('upload_files')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $result = SIO_Batch_Processor::instance()->clear_queue();
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Queue cleared successfully.', 'smart-image-optimizer')
            ));
        } else {
            wp_send_json_error(__('Failed to clear queue.', 'smart-image-optimizer'));
        }
    }
    
    /**
     * AJAX: Add all images to queue
     */
    public function ajax_add_all_images() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('upload_files')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $result = SIO_Batch_Processor::instance()->add_all_images();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Added %d images to queue.', 'smart-image-optimizer'), $result)
        ));
    }
    
    /**
     * AJAX: Get queue items
     */
    public function ajax_get_queue() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('upload_files')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 20;
        
        $items = SIO_Batch_Processor::instance()->get_queue_items($status, $page, $per_page);
        
        $html = '';
        if (!empty($items)) {
            foreach ($items as $item) {
                $html .= '<div class="sio-queue-item">';
                $html .= '<div class="sio-queue-item-info">';
                $html .= '<div class="sio-queue-item-title">' . esc_html(basename($item->file_path)) . '</div>';
                $html .= '<div class="sio-queue-item-meta">';
                $html .= sprintf(__('Added: %s', 'smart-image-optimizer'), esc_html($item->created_at));
                if ($item->processed_at) {
                    $html .= ' | ' . sprintf(__('Processed: %s', 'smart-image-optimizer'), esc_html($item->processed_at));
                }
                $html .= '</div>';
                $html .= '</div>';
                $html .= '<div class="sio-queue-item-status ' . esc_attr($item->status) . '">' . esc_html($item->status) . '</div>';
                $html .= '</div>';
            }
        } else {
            $html = '<div class="sio-queue-item"><p>' . __('No items found.', 'smart-image-optimizer') . '</p></div>';
        }
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX: Get queue statistics
     */
    public function ajax_get_queue_stats() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('upload_files')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $stats = SIO_Batch_Processor::instance()->get_queue_status();
        
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX: Get processing status
     */
    public function ajax_get_processing_status() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('upload_files')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $status = SIO_Batch_Processor::instance()->get_processing_status();
        
        wp_send_json_success($status);
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
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 20;
        $filters = array(
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '',
            'action' => isset($_POST['action_filter']) ? sanitize_text_field($_POST['action_filter']) : '',
            'date_from' => isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '',
            'date_to' => isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : ''
        );
        
        $logs = SIO_Monitor::instance()->get_logs($filters, $page, $per_page);
        $total = SIO_Monitor::instance()->get_logs_count($filters);
        
        $html = '';
        if (!empty($logs)) {
            foreach ($logs as $log) {
                $html .= '<div class="sio-log-entry">';
                $html .= '<div class="sio-log-header">';
                $html .= '<span class="sio-log-timestamp">' . esc_html($log->created_at) . '</span>';
                $html .= '<span class="sio-log-status ' . esc_attr($log->status) . '">' . esc_html($log->status) . '</span>';
                $html .= '</div>';
                $html .= '<div class="sio-log-message">' . esc_html($log->message) . '</div>';
                if ($log->details) {
                    $html .= '<div class="sio-log-details">' . esc_html($log->details) . '</div>';
                }
                $html .= '</div>';
            }
        } else {
            $html = '<div class="sio-log-entry"><p>' . __('No logs found.', 'smart-image-optimizer') . '</p></div>';
        }
        
        // Generate pagination
        $total_pages = ceil($total / $per_page);
        $pagination = '';
        if ($total_pages > 1) {
            $pagination .= '<div class="sio-pagination">';
            for ($i = 1; $i <= $total_pages; $i++) {
                if ($i == $page) {
                    $pagination .= '<span class="current">' . $i . '</span>';
                } else {
                    $pagination .= '<a href="#" data-page="' . $i . '">' . $i . '</a>';
                }
            }
            $pagination .= '</div>';
        }
        
        wp_send_json_success(array(
            'html' => $html,
            'pagination' => $pagination
        ));
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
        
        $result = SIO_Monitor::instance()->clear_logs();
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Logs cleared successfully.', 'smart-image-optimizer')
            ));
        } else {
            wp_send_json_error(__('Failed to clear logs.', 'smart-image-optimizer'));
        }
    }
    
    /**
     * AJAX: Export logs
     */
    public function ajax_export_logs() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('manage_options')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $filters = array(
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
            'action' => isset($_GET['action_filter']) ? sanitize_text_field($_GET['action_filter']) : '',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : ''
        );
        
        $logs = SIO_Monitor::instance()->get_logs($filters, 1, 10000); // Get all logs
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="sio-logs-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, array('Date', 'Status', 'Action', 'Message', 'Details'));
        
        // CSV data
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log->created_at,
                $log->status,
                $log->action,
                $log->message,
                $log->details
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * AJAX: Export system info
     */
    public function ajax_export_system_info() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('manage_options')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $system_info = SIO_Monitor::instance()->get_system_info();
        $stats = SIO_Monitor::instance()->get_statistics();
        
        $export_data = array(
            'system_info' => $system_info,
            'statistics' => $stats,
            'export_date' => current_time('mysql')
        );
        
        // Set headers for JSON download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="sio-system-info-' . date('Y-m-d') . '.json"');
        
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * AJAX: Generate .htaccess rules
     */
    public function ajax_generate_htaccess() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('manage_options')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $result = SIO_Server_Config::instance()->generate_htaccess_rules();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => __('.htaccess rules generated successfully.', 'smart-image-optimizer'),
            'config' => $result
        ));
    }
    
    /**
     * AJAX: View current .htaccess rules
     */
    public function ajax_view_htaccess() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('manage_options')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $config = SIO_Server_Config::instance()->get_htaccess_rules();
        
        wp_send_json_success(array(
            'config' => $config,
            'filename' => '.htaccess'
        ));
    }
    
    /**
     * AJAX: Generate Nginx configuration
     */
    public function ajax_generate_nginx() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('manage_options')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $config = SIO_Server_Config::instance()->get_nginx_config();
        
        wp_send_json_success(array(
            'config' => $config,
            'filename' => 'nginx-sio.conf'
        ));
    }
    
    /**
     * AJAX: View Nginx configuration
     */
    public function ajax_view_nginx() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('manage_options')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        $config = SIO_Server_Config::instance()->get_nginx_config();
        
        wp_send_json_success(array(
            'config' => $config,
            'filename' => 'nginx-sio.conf'
        ));
    }
    
    /**
     * AJAX: Refresh image libraries
     */
    public function ajax_refresh_libraries() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('manage_options')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        // Force library re-detection
        SIO_Image_Processor::instance()->force_library_detection();
        
        // Get updated library information
        $libraries = SIO_Image_Processor::instance()->get_available_libraries();
        $current_library = SIO_Image_Processor::instance()->get_current_library();
        
        $html = '';
        if (empty($libraries)) {
            $html .= '<div class="notice notice-error inline">';
            $html .= '<p>' . __('No image processing libraries detected after refresh.', 'smart-image-optimizer') . '</p>';
            
            // Show diagnostic information
            $debug_info = SIO_Image_Processor::instance()->get_library_debug_info();
            if (!empty($debug_info['diagnostic_info'])) {
                $html .= '<div class="sio-diagnostic-info">';
                $html .= '<h5>' . __('Diagnostic Information:', 'smart-image-optimizer') . '</h5>';
                $html .= '<ul>';
                foreach ($debug_info['diagnostic_info'] as $info) {
                    $html .= '<li>' . esc_html($info) . '</li>';
                }
                $html .= '</ul>';
                $html .= '</div>';
            }
            $html .= '</div>';
        } else {
            $html .= '<div class="notice notice-success inline">';
            $html .= '<p>' . sprintf(__('Found %d image processing libraries.', 'smart-image-optimizer'), count($libraries)) . '</p>';
            $html .= '</div>';
            
            $html .= '<table class="widefat">';
            $html .= '<thead><tr>';
            $html .= '<th>' . __('Library', 'smart-image-optimizer') . '</th>';
            $html .= '<th>' . __('Version', 'smart-image-optimizer') . '</th>';
            $html .= '<th>' . __('WebP Support', 'smart-image-optimizer') . '</th>';
            $html .= '<th>' . __('AVIF Support', 'smart-image-optimizer') . '</th>';
            $html .= '<th>' . __('Detection Method', 'smart-image-optimizer') . '</th>';
            $html .= '<th>' . __('Status', 'smart-image-optimizer') . '</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';
            
            foreach ($libraries as $lib_name => $lib_info) {
                $is_current = ($lib_name === $current_library);
                $status_class = $is_current ? 'active' : 'available';
                $status_text = $is_current ? __('Active', 'smart-image-optimizer') : __('Available', 'smart-image-optimizer');
                
                $html .= '<tr class="' . esc_attr($status_class) . '">';
                $html .= '<td><strong>' . esc_html($lib_info['name']) . '</strong></td>';
                $html .= '<td>' . esc_html($lib_info['version']) . '</td>';
                $html .= '<td>' . ($lib_info['supports_webp'] ? '<span class="sio-status-success">✓</span>' : '<span class="sio-status-error">✗</span>') . '</td>';
                $html .= '<td>' . ($lib_info['supports_avif'] ? '<span class="sio-status-success">✓</span>' : '<span class="sio-status-error">✗</span>') . '</td>';
                $html .= '<td>' . esc_html($lib_info['detection_method'] ?? __('Standard', 'smart-image-optimizer')) . '</td>';
                $html .= '<td><span class="status-' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span></td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table>';
        }
        
        wp_send_json_success(array(
            'message' => __('Libraries refreshed successfully.', 'smart-image-optimizer'),
            'html' => $html
        ));
    }
    
    /**
     * AJAX: Test library functionality
     */
    public function ajax_test_libraries() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('manage_options')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        // Test library functionality
        $test_results = SIO_Image_Processor::instance()->test_library_functionality();
        
        $html = '<div class="sio-test-results">';
        $html .= '<h4>' . __('Library Functionality Test Results', 'smart-image-optimizer') . '</h4>';
        
        if (empty($test_results)) {
            $html .= '<div class="notice notice-error inline">';
            $html .= '<p>' . __('No libraries available for testing.', 'smart-image-optimizer') . '</p>';
            $html .= '</div>';
        } else {
            foreach ($test_results as $library => $results) {
                $html .= '<div class="sio-test-library">';
                $html .= '<h5>' . esc_html($library) . '</h5>';
                
                if ($results['success']) {
                    $html .= '<div class="notice notice-success inline">';
                    $html .= '<p>' . __('Library is functioning correctly.', 'smart-image-optimizer') . '</p>';
                    $html .= '</div>';
                    
                    if (!empty($results['formats_tested'])) {
                        $html .= '<p><strong>' . __('Formats tested:', 'smart-image-optimizer') . '</strong> ' . implode(', ', $results['formats_tested']) . '</p>';
                    }
                    
                    if (!empty($results['test_details'])) {
                        $html .= '<ul>';
                        foreach ($results['test_details'] as $detail) {
                            $html .= '<li>' . esc_html($detail) . '</li>';
                        }
                        $html .= '</ul>';
                    }
                } else {
                    $html .= '<div class="notice notice-error inline">';
                    $html .= '<p>' . __('Library test failed:', 'smart-image-optimizer') . ' ' . esc_html($results['error']) . '</p>';
                    $html .= '</div>';
                }
                
                $html .= '</div>';
            }
        }
        
        $html .= '</div>';
        
        wp_send_json_success(array(
            'message' => __('Library functionality test completed.', 'smart-image-optimizer'),
            'html' => $html
        ));
    }
    
    /**
     * AJAX: Show debug information
     */
    public function ajax_debug_libraries() {
        // Security check
        if (!SIO_Security::instance()->check_user_capability('manage_options')) {
            wp_die(__('Insufficient permissions.', 'smart-image-optimizer'), 403);
        }
        
        check_ajax_referer('sio_ajax_nonce', 'nonce');
        
        // Get comprehensive debug information
        $debug_info = SIO_Image_Processor::instance()->get_library_debug_info();
        
        $html = '<div class="sio-debug-info">';
        $html .= '<h4>' . __('Library Debug Information', 'smart-image-optimizer') . '</h4>';
        
        // PHP Extensions
        if (!empty($debug_info['php_extensions'])) {
            $html .= '<div class="sio-debug-section">';
            $html .= '<h5>' . __('PHP Extensions', 'smart-image-optimizer') . '</h5>';
            $html .= '<ul>';
            foreach ($debug_info['php_extensions'] as $ext => $status) {
                $status_icon = $status ? '✓' : '✗';
                $status_class = $status ? 'sio-status-success' : 'sio-status-error';
                $html .= '<li><span class="' . $status_class . '">' . $status_icon . '</span> ' . esc_html($ext) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        // WordPress Image Editors
        if (!empty($debug_info['wp_image_editors'])) {
            $html .= '<div class="sio-debug-section">';
            $html .= '<h5>' . __('WordPress Image Editors', 'smart-image-optimizer') . '</h5>';
            $html .= '<ul>';
            foreach ($debug_info['wp_image_editors'] as $editor => $status) {
                $status_icon = $status ? '✓' : '✗';
                $status_class = $status ? 'sio-status-success' : 'sio-status-error';
                $html .= '<li><span class="' . $status_class . '">' . $status_icon . '</span> ' . esc_html($editor) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        // Detection Methods
        if (!empty($debug_info['detection_methods'])) {
            $html .= '<div class="sio-debug-section">';
            $html .= '<h5>' . __('Detection Methods Tried', 'smart-image-optimizer') . '</h5>';
            $html .= '<ul>';
            foreach ($debug_info['detection_methods'] as $method => $result) {
                $status_icon = $result['success'] ? '✓' : '✗';
                $status_class = $result['success'] ? 'sio-status-success' : 'sio-status-error';
                $html .= '<li><span class="' . $status_class . '">' . $status_icon . '</span> ' . esc_html($method);
                if (!empty($result['details'])) {
                    $html .= ' - ' . esc_html($result['details']);
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        // Diagnostic Information
        if (!empty($debug_info['diagnostic_info'])) {
            $html .= '<div class="sio-debug-section">';
            $html .= '<h5>' . __('Diagnostic Information', 'smart-image-optimizer') . '</h5>';
            $html .= '<ul>';
            foreach ($debug_info['diagnostic_info'] as $info) {
                $html .= '<li>' . esc_html($info) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        // Error Messages
        if (!empty($debug_info['errors'])) {
            $html .= '<div class="sio-debug-section">';
            $html .= '<h5>' . __('Error Messages', 'smart-image-optimizer') . '</h5>';
            $html .= '<ul>';
            foreach ($debug_info['errors'] as $error) {
                $html .= '<li class="sio-status-error">' . esc_html($error) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        wp_send_json_success(array(
            'message' => __('Debug information retrieved.', 'smart-image-optimizer'),
            'html' => $html
        ));
    }
}