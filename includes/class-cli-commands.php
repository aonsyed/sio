<?php
/**
 * CLI Commands Class
 *
 * Handles WP CLI command integration
 *
 * @package SmartImageOptimizer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Only load if WP CLI is available
if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * CLI Commands Class
 */
class SIO_CLI_Commands {
    
    /**
     * Instance
     *
     * @var SIO_CLI_Commands
     */
    private static $instance = null;
    
    /**
     * Get instance
     *
     * @return SIO_CLI_Commands
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
        $this->register_commands();
    }
    
    /**
     * Register WP CLI commands
     */
    private function register_commands() {
        WP_CLI::add_command('smart-optimizer', $this);
    }
    
    /**
     * Convert images to optimized formats
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Format to convert to. Options: webp, avif, both
     * ---
     * default: both
     * options:
     *   - webp
     *   - avif
     *   - both
     * ---
     *
     * [--quality=<quality>]
     * : Image quality (1-100)
     * ---
     * default: 80
     * ---
     *
     * [--limit=<limit>]
     * : Number of images to process
     * ---
     * default: 100
     * ---
     *
     * [--background]
     * : Process in background using queue
     *
     * [--force]
     * : Force reprocessing of already converted images
     *
     * [--dry-run]
     * : Show what would be processed without actually processing
     *
     * ## EXAMPLES
     *
     *     wp smart-optimizer convert --format=webp --quality=85
     *     wp smart-optimizer convert --format=both --limit=50 --background
     *     wp smart-optimizer convert --dry-run
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function convert($args, $assoc_args) {
        // Validate arguments
        $validated_args = SIO_Security::instance()->validate_cli_args($assoc_args);
        if (is_wp_error($validated_args)) {
            WP_CLI::error($validated_args->get_error_message());
        }
        
        $defaults = array(
            'format' => 'both',
            'quality' => 80,
            'limit' => 100,
            'background' => false,
            'force' => false,
            'dry-run' => false
        );
        
        $options = wp_parse_args($validated_args, $defaults);
        
        WP_CLI::line('Smart Image Optimizer - Convert Images');
        WP_CLI::line('=====================================');
        
        // Get images to process
        $images = $this->get_images_to_process($options);
        
        if (empty($images)) {
            WP_CLI::success('No images found to process.');
            return;
        }
        
        $total_images = count($images);
        WP_CLI::line(sprintf('Found %d images to process.', $total_images));
        
        if ($options['dry-run']) {
            WP_CLI::line('DRY RUN - No images will be processed.');
            $this->show_dry_run_results($images, $options);
            return;
        }
        
        if ($options['background']) {
            $this->process_in_background($images, $options);
        } else {
            $this->process_immediately($images, $options);
        }
    }
    
    /**
     * Process batch of images
     *
     * ## OPTIONS
     *
     * [--limit=<limit>]
     * : Number of images to process in batch
     * ---
     * default: 10
     * ---
     *
     * [--background]
     * : Process in background
     *
     * ## EXAMPLES
     *
     *     wp smart-optimizer batch --limit=50
     *     wp smart-optimizer batch --background
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function batch($args, $assoc_args) {
        $defaults = array(
            'limit' => 10,
            'background' => false
        );
        
        $options = wp_parse_args($assoc_args, $defaults);
        
        WP_CLI::line('Smart Image Optimizer - Batch Processing');
        WP_CLI::line('========================================');
        
        $batch_processor = SIO_Batch_Processor::instance();
        
        if ($options['background']) {
            // Start background processing
            $result = $batch_processor->start_batch_processing($options);
            
            if (is_wp_error($result)) {
                WP_CLI::error($result->get_error_message());
            }
            
            WP_CLI::success('Background batch processing started.');
            
            // Show status updates
            $this->monitor_batch_progress();
        } else {
            // Process immediately
            $result = $batch_processor->process_batch($options['limit']);
            
            WP_CLI::line(sprintf('Processed: %d images', $result['processed']));
            WP_CLI::line(sprintf('Errors: %d', $result['errors']));
            WP_CLI::line(sprintf('Execution time: %.2f seconds', $result['execution_time']));
            
            if ($result['errors'] > 0) {
                WP_CLI::warning('Some images failed to process. Check logs for details.');
            } else {
                WP_CLI::success('Batch processing completed successfully.');
            }
        }
    }
    
    /**
     * Show processing status
     *
     * ## OPTIONS
     *
     * [--detailed]
     * : Show detailed status information
     *
     * [--watch]
     * : Watch status in real-time (updates every 2 seconds)
     *
     * ## EXAMPLES
     *
     *     wp smart-optimizer status
     *     wp smart-optimizer status --detailed
     *     wp smart-optimizer status --watch
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function status($args, $assoc_args) {
        $detailed = isset($assoc_args['detailed']);
        $watch = isset($assoc_args['watch']);
        
        if ($watch) {
            $this->watch_status($detailed);
            return;
        }
        
        $this->show_status($detailed);
    }
    
    /**
     * Cleanup old files and logs
     *
     * ## OPTIONS
     *
     * [--older-than=<days>]
     * : Remove files older than specified days
     * ---
     * default: 30
     * ---
     *
     * [--logs]
     * : Cleanup logs only
     *
     * [--files]
     * : Cleanup files only
     *
     * [--dry-run]
     * : Show what would be cleaned without actually cleaning
     *
     * ## EXAMPLES
     *
     *     wp smart-optimizer cleanup --older-than=30
     *     wp smart-optimizer cleanup --logs --older-than=7
     *     wp smart-optimizer cleanup --dry-run
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function cleanup($args, $assoc_args) {
        $defaults = array(
            'older-than' => 30,
            'logs' => false,
            'files' => false,
            'dry-run' => false
        );
        
        $options = wp_parse_args($assoc_args, $defaults);
        
        // Validate older-than parameter
        $validated_args = SIO_Security::instance()->validate_cli_args($options);
        if (is_wp_error($validated_args)) {
            WP_CLI::error($validated_args->get_error_message());
        }
        
        WP_CLI::line('Smart Image Optimizer - Cleanup');
        WP_CLI::line('===============================');
        
        $cleaned_items = 0;
        
        // Cleanup logs
        if (!$options['files'] || $options['logs']) {
            WP_CLI::line('Cleaning up logs...');
            
            if ($options['dry-run']) {
                $log_count = SIO_Monitor::instance()->get_log_count(array(
                    'date_to' => date('Y-m-d H:i:s', strtotime("-{$options['older-than']} days"))
                ));
                WP_CLI::line(sprintf('Would clean %d log entries.', $log_count));
            } else {
                $cleaned_logs = SIO_Monitor::instance()->clear_logs(array(
                    'older_than_days' => $options['older-than']
                ));
                WP_CLI::line(sprintf('Cleaned %d log entries.', $cleaned_logs));
                $cleaned_items += $cleaned_logs;
            }
        }
        
        // Cleanup files
        if (!$options['logs'] || $options['files']) {
            WP_CLI::line('Cleaning up old files...');
            
            if ($options['dry-run']) {
                WP_CLI::line('Would clean old backup and temporary files.');
            } else {
                SIO_Image_Processor::instance()->cleanup_old_files($options['older-than']);
                WP_CLI::line('Cleaned old backup and temporary files.');
            }
        }
        
        if ($options['dry-run']) {
            WP_CLI::line('DRY RUN - No actual cleanup performed.');
        } else {
            WP_CLI::success(sprintf('Cleanup completed. %d items cleaned.', $cleaned_items));
        }
    }
    
    /**
     * Manage plugin settings
     *
     * ## OPTIONS
     *
     * <action>
     * : Action to perform
     * ---
     * options:
     *   - get
     *   - set
     *   - reset
     *   - list
     * ---
     *
     * [<setting>]
     * : Setting name (required for get/set actions)
     *
     * [<value>]
     * : Setting value (required for set action)
     *
     * ## EXAMPLES
     *
     *     wp smart-optimizer settings list
     *     wp smart-optimizer settings get webp_quality
     *     wp smart-optimizer settings set webp_quality 85
     *     wp smart-optimizer settings reset
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function settings($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error('Please specify an action: get, set, reset, or list');
        }
        
        $action = $args[0];
        $settings_manager = SIO_Settings_Manager::instance();
        
        switch ($action) {
            case 'list':
                $this->list_settings();
                break;
                
            case 'get':
                if (empty($args[1])) {
                    WP_CLI::error('Please specify a setting name.');
                }
                $this->get_setting($args[1]);
                break;
                
            case 'set':
                if (empty($args[1]) || empty($args[2])) {
                    WP_CLI::error('Please specify setting name and value.');
                }
                $this->set_setting($args[1], $args[2]);
                break;
                
            case 'reset':
                $this->reset_settings();
                break;
                
            default:
                WP_CLI::error('Invalid action. Use: get, set, reset, or list');
        }
    }
    
    /**
     * Show system information
     *
     * ## EXAMPLES
     *
     *     wp smart-optimizer info
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function info($args, $assoc_args) {
        WP_CLI::line('Smart Image Optimizer - System Information');
        WP_CLI::line('==========================================');
        
        $info = SIO_Monitor::instance()->get_system_info();
        
        // Plugin info
        WP_CLI::line('Plugin Information:');
        WP_CLI::line(sprintf('  Version: %s', $info['plugin']['version']));
        WP_CLI::line(sprintf('  Current Library: %s', $info['plugin']['current_library'] ?: 'None'));
        
        // Image libraries
        WP_CLI::line('');
        WP_CLI::line('Available Image Libraries:');
        foreach ($info['plugin']['image_libraries'] as $lib => $details) {
            WP_CLI::line(sprintf('  %s: %s', $details['name'], $details['version']));
            WP_CLI::line(sprintf('    WebP Support: %s', $details['supports_webp'] ? 'Yes' : 'No'));
            WP_CLI::line(sprintf('    AVIF Support: %s', $details['supports_avif'] ? 'Yes' : 'No'));
        }
        
        // PHP info
        WP_CLI::line('');
        WP_CLI::line('PHP Information:');
        WP_CLI::line(sprintf('  Version: %s', $info['php']['version']));
        WP_CLI::line(sprintf('  Memory Limit: %s', $info['php']['memory_limit']));
        WP_CLI::line(sprintf('  Max Execution Time: %s', $info['php']['max_execution_time']));
        
        // WordPress info
        WP_CLI::line('');
        WP_CLI::line('WordPress Information:');
        WP_CLI::line(sprintf('  Version: %s', $info['wordpress']['version']));
        WP_CLI::line(sprintf('  Memory Limit: %s', $info['wordpress']['memory_limit']));
        WP_CLI::line(sprintf('  Upload Max Filesize: %s', $info['wordpress']['upload_max_filesize']));
        
        // Statistics
        $stats = SIO_Monitor::instance()->get_statistics();
        WP_CLI::line('');
        WP_CLI::line('Processing Statistics:');
        WP_CLI::line(sprintf('  Total Processed: %d', $stats['plugin_stats']['total_processed'] ?? 0));
        WP_CLI::line(sprintf('  WebP Converted: %d', $stats['plugin_stats']['webp_converted'] ?? 0));
        WP_CLI::line(sprintf('  AVIF Converted: %d', $stats['plugin_stats']['avif_converted'] ?? 0));
        WP_CLI::line(sprintf('  Errors: %d', $stats['plugin_stats']['errors'] ?? 0));
    }
    
    /**
     * Manage server configuration
     *
     * ## OPTIONS
     *
     * <action>
     * : Action to perform
     * ---
     * options:
     *   - generate-htaccess
     *   - view-htaccess
     *   - generate-nginx
     *   - view-nginx
     *   - status
     * ---
     *
     * [--output=<file>]
     * : Output configuration to file
     *
     * [--force]
     * : Force overwrite existing .htaccess rules
     *
     * ## EXAMPLES
     *
     *     wp smart-optimizer server generate-htaccess
     *     wp smart-optimizer server view-htaccess --output=current-htaccess.txt
     *     wp smart-optimizer server generate-nginx --output=nginx-sio.conf
     *     wp smart-optimizer server status
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function server($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error('Please specify an action: generate-htaccess, view-htaccess, generate-nginx, view-nginx, or status');
        }
        
        $action = $args[0];
        $server_config = SIO_Server_Config::instance();
        
        $defaults = array(
            'output' => false,
            'force' => false
        );
        
        $options = wp_parse_args($assoc_args, $defaults);
        
        switch ($action) {
            case 'generate-htaccess':
                $this->generate_htaccess_config($server_config, $options);
                break;
                
            case 'view-htaccess':
                $this->view_htaccess_config($server_config, $options);
                break;
                
            case 'generate-nginx':
                $this->generate_nginx_config($server_config, $options);
                break;
                
            case 'view-nginx':
                $this->view_nginx_config($server_config, $options);
                break;
                
            case 'status':
                $this->show_server_status($server_config);
                break;
                
            default:
                WP_CLI::error('Invalid action. Use: generate-htaccess, view-htaccess, generate-nginx, view-nginx, or status');
        }
    }
    
    /**
     * Get images to process
     *
     * @param array $options Processing options
     * @return array
     */
    private function get_images_to_process($options) {
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => array('image/jpeg', 'image/png', 'image/gif'),
            'post_status' => 'inherit',
            'posts_per_page' => $options['limit'],
            'fields' => 'ids'
        );
        
        // If not forcing, exclude already processed images
        if (!$options['force']) {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key' => '_wp_attachment_metadata',
                    'value' => 'sio_processed',
                    'compare' => 'NOT LIKE'
                ),
                array(
                    'key' => '_wp_attachment_metadata',
                    'compare' => 'NOT EXISTS'
                )
            );
        }
        
        return get_posts($args);
    }
    
    /**
     * Show dry run results
     *
     * @param array $images Images to process
     * @param array $options Processing options
     */
    private function show_dry_run_results($images, $options) {
        WP_CLI::line('');
        WP_CLI::line('Images that would be processed:');
        WP_CLI::line('==============================');
        
        foreach ($images as $image_id) {
            $file_path = get_attached_file($image_id);
            $file_size = file_exists($file_path) ? filesize($file_path) : 0;
            $title = get_the_title($image_id);
            
            WP_CLI::line(sprintf(
                'ID: %d | %s | %s | %s',
                $image_id,
                $title,
                basename($file_path),
                size_format($file_size)
            ));
        }
        
        WP_CLI::line('');
        WP_CLI::line(sprintf('Total: %d images', count($images)));
        WP_CLI::line(sprintf('Format: %s', $options['format']));
        WP_CLI::line(sprintf('Quality: %d', $options['quality']));
    }
    
    /**
     * Process images in background
     *
     * @param array $images Images to process
     * @param array $options Processing options
     */
    private function process_in_background($images, $options) {
        $batch_processor = SIO_Batch_Processor::instance();
        
        // Add images to queue
        $results = $batch_processor->add_multiple_to_queue($images, $options);
        
        WP_CLI::line(sprintf('Added %d images to processing queue.', $results['added']));
        
        if ($results['skipped'] > 0) {
            WP_CLI::warning(sprintf('Skipped %d images.', $results['skipped']));
        }
        
        // Start background processing
        $start_result = $batch_processor->start_batch_processing();
        
        if (is_wp_error($start_result)) {
            WP_CLI::error($start_result->get_error_message());
        }
        
        WP_CLI::success('Background processing started.');
        
        // Monitor progress
        $this->monitor_batch_progress();
    }
    
    /**
     * Process images immediately
     *
     * @param array $images Images to process
     * @param array $options Processing options
     */
    private function process_immediately($images, $options) {
        $progress = WP_CLI\Utils\make_progress_bar('Processing images', count($images));
        
        $processed = 0;
        $errors = 0;
        $total_saved = 0;
        
        foreach ($images as $image_id) {
            $file_path = get_attached_file($image_id);
            
            if (!$file_path || !file_exists($file_path)) {
                $errors++;
                $progress->tick();
                continue;
            }
            
            // Process the image
            $result = SIO_Image_Processor::instance()->process_image($file_path, $options);
            
            if (is_wp_error($result)) {
                $errors++;
            } else {
                $processed++;
                $total_saved += $result['total_saved'];
                
                // Update attachment metadata
                $metadata = wp_get_attachment_metadata($image_id);
                $metadata['sio_processed'] = $result;
                wp_update_attachment_metadata($image_id, $metadata);
            }
            
            $progress->tick();
        }
        
        $progress->finish();
        
        WP_CLI::line('');
        WP_CLI::line('Processing Results:');
        WP_CLI::line('==================');
        WP_CLI::line(sprintf('Processed: %d images', $processed));
        WP_CLI::line(sprintf('Errors: %d', $errors));
        WP_CLI::line(sprintf('Total saved: %s', size_format($total_saved)));
        
        if ($errors > 0) {
            WP_CLI::warning('Some images failed to process. Check logs for details.');
        } else {
            WP_CLI::success('All images processed successfully.');
        }
    }
    
    /**
     * Monitor batch progress
     */
    private function monitor_batch_progress() {
        WP_CLI::line('Monitoring batch progress (Ctrl+C to stop monitoring)...');
        WP_CLI::line('');
        
        $batch_processor = SIO_Batch_Processor::instance();
        
        while (true) {
            $status = $batch_processor->get_processing_status();
            
            if ($status['status'] === 'idle') {
                WP_CLI::success('Batch processing completed.');
                break;
            }
            
            if ($status['status'] === 'running') {
                WP_CLI::line(sprintf(
                    'Progress: %d/%d (%d%%) - Status: %s',
                    $status['current'],
                    $status['total'],
                    $status['percentage'],
                    $status['status']
                ));
            }
            
            sleep(2);
        }
    }
    
    /**
     * Show status information
     *
     * @param bool $detailed Show detailed information
     */
    private function show_status($detailed = false) {
        WP_CLI::line('Smart Image Optimizer - Status');
        WP_CLI::line('==============================');
        
        // Queue status
        $queue_status = SIO_Batch_Processor::instance()->get_queue_status();
        WP_CLI::line('Queue Status:');
        WP_CLI::line(sprintf('  Pending: %d', $queue_status['pending']));
        WP_CLI::line(sprintf('  Processing: %d', $queue_status['processing']));
        WP_CLI::line(sprintf('  Completed: %d', $queue_status['completed']));
        WP_CLI::line(sprintf('  Failed: %d', $queue_status['failed']));
        WP_CLI::line(sprintf('  Total: %d', $queue_status['total']));
        
        // Processing status
        $processing_status = SIO_Batch_Processor::instance()->get_processing_status();
        WP_CLI::line('');
        WP_CLI::line('Processing Status:');
        WP_CLI::line(sprintf('  Status: %s', $processing_status['status']));
        
        if ($processing_status['status'] === 'running') {
            WP_CLI::line(sprintf('  Progress: %d/%d (%d%%)', 
                $processing_status['current'],
                $processing_status['total'],
                $processing_status['percentage']
            ));
        }
        
        if ($detailed) {
            // Statistics
            $stats = SIO_Monitor::instance()->get_statistics();
            WP_CLI::line('');
            WP_CLI::line('Statistics:');
            WP_CLI::line(sprintf('  Total Processed: %d', $stats['plugin_stats']['total_processed'] ?? 0));
            WP_CLI::line(sprintf('  WebP Converted: %d', $stats['plugin_stats']['webp_converted'] ?? 0));
            WP_CLI::line(sprintf('  AVIF Converted: %d', $stats['plugin_stats']['avif_converted'] ?? 0));
            WP_CLI::line(sprintf('  Errors: %d', $stats['plugin_stats']['errors'] ?? 0));
            WP_CLI::line(sprintf('  Average Execution Time: %.3fs', $stats['avg_execution_time']));
            WP_CLI::line(sprintf('  Recent Activity (24h): %d', $stats['recent_activity']));
        }
    }
    
    /**
     * Watch status in real-time
     *
     * @param bool $detailed Show detailed information
     */
    private function watch_status($detailed = false) {
        WP_CLI::line('Watching status (Ctrl+C to stop)...');
        WP_CLI::line('');
        
        while (true) {
            // Clear screen (works on most terminals)
            WP_CLI::line("\033[2J\033[H");
            
            $this->show_status($detailed);
            
            WP_CLI::line('');
            WP_CLI::line('Last updated: ' . current_time('Y-m-d H:i:s'));
            
            sleep(2);
        }
    }
    
    /**
     * List all settings
     */
    private function list_settings() {
        $settings = SIO_Settings_Manager::instance()->get_settings();
        $schema = SIO_Settings_Manager::instance()->get_settings_schema();
        
        WP_CLI::line('Smart Image Optimizer - Settings');
        WP_CLI::line('================================');
        
        foreach ($settings as $key => $value) {
            $description = isset($schema[$key]['description']) ? $schema[$key]['description'] : '';
            $type = isset($schema[$key]['type']) ? $schema[$key]['type'] : 'string';
            
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $value = implode(', ', $value);
            }
            
            WP_CLI::line(sprintf('%s: %s', $key, $value));
            if ($description) {
                WP_CLI::line(sprintf('  %s', $description));
            }
            WP_CLI::line('');
        }
    }
    
    /**
     * Get a specific setting
     *
     * @param string $setting_name Setting name
     */
    private function get_setting($setting_name) {
        $value = SIO_Settings_Manager::instance()->get_setting($setting_name);
        
        if ($value === null) {
            WP_CLI::error(sprintf('Setting "%s" not found.', $setting_name));
        }
        
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_array($value)) {
            $value = implode(', ', $value);
        }
        
        WP_CLI::success(sprintf('%s: %s', $setting_name, $value));
    }
    
    /**
     * Set a specific setting
     *
     * @param string $setting_name Setting name
     * @param mixed $value Setting value
     */
    private function set_setting($setting_name, $value) {
        // Convert string values to appropriate types
        if ($value === 'true') {
            $value = true;
        } elseif ($value === 'false') {
            $value = false;
        } elseif (is_numeric($value)) {
            $value = is_float($value) ? floatval($value) : intval($value);
        }
        
        $result = SIO_Settings_Manager::instance()->update_setting($setting_name, $value, 'cli');
        
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        
        if ($result) {
            WP_CLI::success(sprintf('Setting "%s" updated to: %s', $setting_name, $value));
        } else {
            WP_CLI::error('Failed to update setting.');
        }
    }
    
    /**
     * Reset all settings to defaults
     */
    private function reset_settings() {
        WP_CLI::confirm('Are you sure you want to reset all settings to defaults?');
        
        $result = SIO_Settings_Manager::instance()->reset_settings();
        
        if ($result) {
            WP_CLI::success('All settings have been reset to defaults.');
        } else {
            WP_CLI::error('Failed to reset settings.');
        }
    }
    
    /**
     * Generate .htaccess configuration
     *
     * @param SIO_Server_Config $server_config Server config instance
     * @param array $options Command options
     */
    private function generate_htaccess_config($server_config, $options) {
        WP_CLI::line('Smart Image Optimizer - Generate .htaccess Rules');
        WP_CLI::line('===============================================');
        
        $result = $server_config->generate_htaccess_rules($options['force']);
        
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        
        WP_CLI::line('Generated .htaccess rules:');
        WP_CLI::line('');
        WP_CLI::line($result);
        
        if ($options['output']) {
            $written = file_put_contents($options['output'], $result);
            if ($written !== false) {
                WP_CLI::success(sprintf('Configuration saved to: %s', $options['output']));
            } else {
                WP_CLI::error('Failed to write configuration file.');
            }
        } else {
            WP_CLI::success('.htaccess rules generated successfully.');
        }
    }
    
    /**
     * View current .htaccess configuration
     *
     * @param SIO_Server_Config $server_config Server config instance
     * @param array $options Command options
     */
    private function view_htaccess_config($server_config, $options) {
        WP_CLI::line('Smart Image Optimizer - Current .htaccess Rules');
        WP_CLI::line('==============================================');
        
        $config = $server_config->get_htaccess_rules();
        
        if (empty($config)) {
            WP_CLI::warning('No Smart Image Optimizer rules found in .htaccess');
            return;
        }
        
        WP_CLI::line('Current .htaccess rules:');
        WP_CLI::line('');
        WP_CLI::line($config);
        
        if ($options['output']) {
            $written = file_put_contents($options['output'], $config);
            if ($written !== false) {
                WP_CLI::success(sprintf('Configuration saved to: %s', $options['output']));
            } else {
                WP_CLI::error('Failed to write configuration file.');
            }
        }
    }
    
    /**
     * Generate Nginx configuration
     *
     * @param SIO_Server_Config $server_config Server config instance
     * @param array $options Command options
     */
    private function generate_nginx_config($server_config, $options) {
        WP_CLI::line('Smart Image Optimizer - Generate Nginx Configuration');
        WP_CLI::line('==================================================');
        
        $config = $server_config->get_nginx_config();
        
        WP_CLI::line('Generated Nginx configuration:');
        WP_CLI::line('');
        WP_CLI::line($config);
        WP_CLI::line('');
        WP_CLI::line('Add this configuration to your Nginx server block.');
        
        if ($options['output']) {
            $written = file_put_contents($options['output'], $config);
            if ($written !== false) {
                WP_CLI::success(sprintf('Configuration saved to: %s', $options['output']));
            } else {
                WP_CLI::error('Failed to write configuration file.');
            }
        } else {
            WP_CLI::success('Nginx configuration generated successfully.');
        }
    }
    
    /**
     * View Nginx configuration
     *
     * @param SIO_Server_Config $server_config Server config instance
     * @param array $options Command options
     */
    private function view_nginx_config($server_config, $options) {
        WP_CLI::line('Smart Image Optimizer - Nginx Configuration');
        WP_CLI::line('==========================================');
        
        $config = $server_config->get_nginx_config();
        
        WP_CLI::line('Nginx configuration snippet:');
        WP_CLI::line('');
        WP_CLI::line($config);
        WP_CLI::line('');
        WP_CLI::line('Add this configuration to your Nginx server block.');
        
        if ($options['output']) {
            $written = file_put_contents($options['output'], $config);
            if ($written !== false) {
                WP_CLI::success(sprintf('Configuration saved to: %s', $options['output']));
            } else {
                WP_CLI::error('Failed to write configuration file.');
            }
        }
    }
    
    /**
     * Show server configuration status
     *
     * @param SIO_Server_Config $server_config Server config instance
     */
    private function show_server_status($server_config) {
        WP_CLI::line('Smart Image Optimizer - Server Configuration Status');
        WP_CLI::line('=================================================');
        
        $settings = SIO_Settings_Manager::instance()->get_settings();
        
        // Server configuration settings
        WP_CLI::line('Configuration Settings:');
        WP_CLI::line(sprintf('  Automatic Serving: %s', $settings['enable_auto_serve'] ? 'Enabled' : 'Disabled'));
        WP_CLI::line(sprintf('  Auto .htaccess: %s', $settings['auto_htaccess'] ? 'Enabled' : 'Disabled'));
        WP_CLI::line(sprintf('  Fallback Conversion: %s', $settings['fallback_conversion'] ? 'Enabled' : 'Disabled'));
        WP_CLI::line(sprintf('  Cache Duration: %d seconds', $settings['cache_duration']));
        
        // Check .htaccess status
        WP_CLI::line('');
        WP_CLI::line('.htaccess Status:');
        $htaccess_path = ABSPATH . '.htaccess';
        if (file_exists($htaccess_path)) {
            $htaccess_content = file_get_contents($htaccess_path);
            $has_sio_rules = strpos($htaccess_content, '# Smart Image Optimizer') !== false;
            WP_CLI::line(sprintf('  .htaccess exists: Yes'));
            WP_CLI::line(sprintf('  SIO rules present: %s', $has_sio_rules ? 'Yes' : 'No'));
            WP_CLI::line(sprintf('  .htaccess writable: %s', is_writable($htaccess_path) ? 'Yes' : 'No'));
        } else {
            WP_CLI::line('  .htaccess exists: No');
            WP_CLI::line(sprintf('  Directory writable: %s', is_writable(ABSPATH) ? 'Yes' : 'No'));
        }
        
        // Server detection
        WP_CLI::line('');
        WP_CLI::line('Server Information:');
        $server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
        WP_CLI::line(sprintf('  Server Software: %s', $server_software));
        
        if (stripos($server_software, 'apache') !== false) {
            WP_CLI::line('  Server Type: Apache (supports .htaccess)');
        } elseif (stripos($server_software, 'nginx') !== false) {
            WP_CLI::line('  Server Type: Nginx (requires manual configuration)');
        } else {
            WP_CLI::line('  Server Type: Unknown');
        }
        
        // Rewrite rules status
        WP_CLI::line('');
        WP_CLI::line('WordPress Rewrite Status:');
        WP_CLI::line(sprintf('  Rewrite rules enabled: %s', get_option('rewrite_rules') ? 'Yes' : 'No'));
        WP_CLI::line(sprintf('  Permalink structure: %s', get_option('permalink_structure') ?: 'Default'));
        
        // Test URLs
        WP_CLI::line('');
        WP_CLI::line('Test URLs:');
        $upload_dir = wp_upload_dir();
        $test_image = $upload_dir['baseurl'] . '/test-image.jpg';
        WP_CLI::line(sprintf('  Original: %s', $test_image));
        WP_CLI::line(sprintf('  WebP: %s', str_replace('.jpg', '.webp', $test_image)));
        WP_CLI::line(sprintf('  AVIF: %s', str_replace('.jpg', '.avif', $test_image)));
        WP_CLI::line(sprintf('  On-the-fly: %s?sio_convert=webp', $test_image));
    }
}