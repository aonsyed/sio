<?php
/**
 * Server Configuration Manager Class
 *
 * Handles server configuration generation for automatic WebP/AVIF serving
 * and on-the-fly conversion with browser detection
 *
 * @package SmartImageOptimizer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Server Configuration Manager Class
 */
class SIO_Server_Config {
    
    /**
     * Instance
     *
     * @var SIO_Server_Config
     */
    private static $instance = null;
    
    /**
     * Get instance
     *
     * @return SIO_Server_Config
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
        add_action('wp_loaded', array($this, 'maybe_handle_image_request'));
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Add rewrite rules for on-the-fly conversion
        $this->add_rewrite_rules();
        
        // Handle image conversion endpoint
        add_action('wp_ajax_nopriv_sio_convert_image', array($this, 'handle_ajax_conversion'));
        add_action('wp_ajax_sio_convert_image', array($this, 'handle_ajax_conversion'));
    }
    
    /**
     * Add WordPress rewrite rules for image conversion
     */
    public function add_rewrite_rules() {
        // Add rewrite rule for on-the-fly conversion
        add_rewrite_rule(
            '^wp-content/uploads/(.+)\.(jpg|jpeg|png|gif)\.sio\.(webp|avif)$',
            'index.php?sio_convert=1&sio_path=$matches[1]&sio_ext=$matches[2]&sio_format=$matches[3]',
            'top'
        );
        
        // Add query vars
        add_filter('query_vars', array($this, 'add_query_vars'));
    }
    
    /**
     * Add query variables
     *
     * @param array $vars Query variables
     * @return array Modified query variables
     */
    public function add_query_vars($vars) {
        $vars[] = 'sio_convert';
        $vars[] = 'sio_path';
        $vars[] = 'sio_ext';
        $vars[] = 'sio_format';
        return $vars;
    }
    
    /**
     * Maybe handle image request
     */
    public function maybe_handle_image_request() {
        if (get_query_var('sio_convert')) {
            $this->handle_image_conversion();
        }
    }
    
    /**
     * Handle image conversion request
     */
    public function handle_image_conversion() {
        $path = get_query_var('sio_path');
        $ext = get_query_var('sio_ext');
        $format = get_query_var('sio_format');
        
        if (!$path || !$ext || !$format) {
            status_header(400);
            exit('Bad Request');
        }
        
        // Security validation
        $file_path = WP_CONTENT_DIR . '/uploads/' . $path . '.' . $ext;
        
        if (!SIO_Security::instance()->validate_file_path($file_path)) {
            status_header(403);
            exit('Forbidden');
        }
        
        if (!file_exists($file_path)) {
            status_header(404);
            exit('Not Found');
        }
        
        // Check if converted version already exists
        $converted_path = $this->get_converted_path($file_path, $format);
        
        if (file_exists($converted_path)) {
            $this->serve_image($converted_path, $format);
            return;
        }
        
        // Convert on-the-fly
        $result = $this->convert_on_the_fly($file_path, $format);
        
        if (is_wp_error($result)) {
            // Log error and serve original
            SIO_Monitor::instance()->log(
                'On-the-fly conversion failed: ' . $result->get_error_message(),
                'error',
                'on_the_fly_conversion',
                array('file_path' => $file_path, 'format' => $format)
            );
            
            $this->serve_image($file_path, $ext);
            return;
        }
        
        // Serve converted image
        $this->serve_image($result, $format);
    }
    
    /**
     * Convert image on-the-fly
     *
     * @param string $file_path Source file path
     * @param string $format Target format
     * @return string|WP_Error Converted file path or error
     */
    public function convert_on_the_fly($file_path, $format) {
        $settings = SIO_Settings_Manager::instance()->get_settings();
        
        // Check if format is enabled
        if (!$settings['enable_' . $format]) {
            return new WP_Error('format_disabled', 'Format not enabled');
        }
        
        // Check if format is supported
        if (!SIO_Image_Processor::instance()->supports_format($format)) {
            return new WP_Error('format_unsupported', 'Format not supported');
        }
        
        $options = array(
            'formats' => array($format),
            'quality' => array(
                $format => $settings[$format . '_quality']
            ),
            'strip_metadata' => $settings['strip_metadata'],
            'compression_level' => $settings['compression_level']
        );
        
        // Apply filters
        $options = apply_filters('sio_on_the_fly_options', $options, $file_path, $format);
        
        // Convert image
        $result = SIO_Image_Processor::instance()->convert_image($file_path, $options);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Return path to converted image
        if (isset($result['converted'][$format])) {
            return $result['converted'][$format]['path'];
        }
        
        return new WP_Error('conversion_failed', 'Conversion failed');
    }
    
    /**
     * Serve image with appropriate headers
     *
     * @param string $file_path Path to image file
     * @param string $format Image format
     */
    public function serve_image($file_path, $format) {
        if (!file_exists($file_path)) {
            status_header(404);
            exit('Not Found');
        }
        
        // Set appropriate headers
        $mime_types = array(
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif'
        );
        
        $mime_type = isset($mime_types[$format]) ? $mime_types[$format] : 'application/octet-stream';
        
        // Set headers
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: public, max-age=31536000'); // 1 year
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($file_path)) . ' GMT');
        
        // Add Vary header for browser detection
        header('Vary: Accept');
        
        // Serve file
        readfile($file_path);
        exit;
    }
    
    /**
     * Get converted file path
     *
     * @param string $original_path Original file path
     * @param string $format Target format
     * @return string Converted file path
     */
    public function get_converted_path($original_path, $format) {
        $path_info = pathinfo($original_path);
        return $path_info['dirname'] . '/' . $path_info['filename'] . '.' . $format;
    }
    
    /**
     * Generate Apache .htaccess rules
     *
     * @return string Apache configuration
     */
    public function generate_apache_config() {
        $settings = SIO_Settings_Manager::instance()->get_settings();
        
        $config = "# Smart Image Optimizer - Automatic WebP/AVIF Serving\n";
        $config .= "# Generated on " . date('Y-m-d H:i:s') . "\n\n";
        
        $config .= "<IfModule mod_rewrite.c>\n";
        $config .= "    RewriteEngine On\n\n";
        
        // AVIF support (if enabled)
        if ($settings['enable_avif']) {
            $config .= "    # Serve AVIF images if browser supports it and file exists\n";
            $config .= "    RewriteCond %{HTTP_ACCEPT} image/avif\n";
            $config .= "    RewriteCond %{REQUEST_FILENAME} \\.(jpe?g|png|gif)$\n";
            $config .= "    RewriteCond %{REQUEST_FILENAME}\\.avif -f\n";
            $config .= "    RewriteRule ^(.+)\\.(jpe?g|png|gif)$ $1.$2.avif [T=image/avif,E=accept:1,L]\n\n";
            
            $config .= "    # On-the-fly AVIF conversion if file doesn't exist\n";
            $config .= "    RewriteCond %{HTTP_ACCEPT} image/avif\n";
            $config .= "    RewriteCond %{REQUEST_FILENAME} \\.(jpe?g|png|gif)$\n";
            $config .= "    RewriteCond %{REQUEST_FILENAME}\\.avif !-f\n";
            $config .= "    RewriteRule ^wp-content/uploads/(.+)\\.(jpe?g|png|gif)$ /wp-content/uploads/$1.$2.sio.avif [L]\n\n";
        }
        
        // WebP support (if enabled)
        if ($settings['enable_webp']) {
            $config .= "    # Serve WebP images if browser supports it and file exists\n";
            $config .= "    RewriteCond %{HTTP_ACCEPT} image/webp\n";
            $config .= "    RewriteCond %{REQUEST_FILENAME} \\.(jpe?g|png|gif)$\n";
            $config .= "    RewriteCond %{REQUEST_FILENAME}\\.webp -f\n";
            $config .= "    RewriteRule ^(.+)\\.(jpe?g|png|gif)$ $1.$2.webp [T=image/webp,E=accept:1,L]\n\n";
            
            $config .= "    # On-the-fly WebP conversion if file doesn't exist\n";
            $config .= "    RewriteCond %{HTTP_ACCEPT} image/webp\n";
            $config .= "    RewriteCond %{REQUEST_FILENAME} \\.(jpe?g|png|gif)$\n";
            $config .= "    RewriteCond %{REQUEST_FILENAME}\\.webp !-f\n";
            $config .= "    RewriteRule ^wp-content/uploads/(.+)\\.(jpe?g|png|gif)$ /wp-content/uploads/$1.$2.sio.webp [L]\n\n";
        }
        
        $config .= "</IfModule>\n\n";
        
        // Add headers for converted images
        $config .= "<IfModule mod_headers.c>\n";
        $config .= "    # Add Vary header for browser detection\n";
        $config .= "    <FilesMatch \"\\.(jpe?g|png|gif|webp|avif)$\">\n";
        $config .= "        Header append Vary Accept\n";
        $config .= "    </FilesMatch>\n\n";
        
        $config .= "    # Set proper MIME types\n";
        if ($settings['enable_webp']) {
            $config .= "    <FilesMatch \"\\.webp$\">\n";
            $config .= "        Header set Content-Type \"image/webp\"\n";
            $config .= "    </FilesMatch>\n";
        }
        
        if ($settings['enable_avif']) {
            $config .= "    <FilesMatch \"\\.avif$\">\n";
            $config .= "        Header set Content-Type \"image/avif\"\n";
            $config .= "    </FilesMatch>\n";
        }
        
        $config .= "</IfModule>\n\n";
        
        // Cache headers
        $config .= "<IfModule mod_expires.c>\n";
        $config .= "    # Set cache headers for images\n";
        $config .= "    <FilesMatch \"\\.(jpe?g|png|gif|webp|avif)$\">\n";
        $config .= "        ExpiresActive On\n";
        $config .= "        ExpiresDefault \"access plus 1 year\"\n";
        $config .= "    </FilesMatch>\n";
        $config .= "</IfModule>\n\n";
        
        $config .= "# End Smart Image Optimizer\n";
        
        return $config;
    }
    
    /**
     * Generate Nginx configuration
     *
     * @return string Nginx configuration
     */
    public function generate_nginx_config() {
        $settings = SIO_Settings_Manager::instance()->get_settings();
        
        $config = "# Smart Image Optimizer - Automatic WebP/AVIF Serving\n";
        $config .= "# Generated on " . date('Y-m-d H:i:s') . "\n";
        $config .= "# Add this to your server block\n\n";
        
        // AVIF support (if enabled)
        if ($settings['enable_avif']) {
            $config .= "# Serve AVIF images if browser supports it\n";
            $config .= "location ~* \\.(jpe?g|png|gif)$ {\n";
            $config .= "    set \$avif_suffix \"\";\n";
            $config .= "    if (\$http_accept ~* \"image/avif\") {\n";
            $config .= "        set \$avif_suffix \".avif\";\n";
            $config .= "    }\n\n";
            
            $config .= "    # Try AVIF first, then WebP, then original\n";
            $config .= "    try_files \$uri\$avif_suffix \$uri.webp \$uri =404;\n\n";
            
            $config .= "    # Set proper content type for AVIF\n";
            $config .= "    location ~ \\.avif$ {\n";
            $config .= "        add_header Content-Type image/avif;\n";
            $config .= "        add_header Vary Accept;\n";
            $config .= "        expires 1y;\n";
            $config .= "        add_header Cache-Control \"public, immutable\";\n";
            $config .= "    }\n";
            $config .= "}\n\n";
            
            $config .= "# On-the-fly AVIF conversion\n";
            $config .= "location ~ ^/wp-content/uploads/(.+)\\.(jpe?g|png|gif)\\.sio\\.avif$ {\n";
            $config .= "    try_files \$uri @sio_convert;\n";
            $config .= "}\n\n";
        }
        
        // WebP support (if enabled)  
        if ($settings['enable_webp']) {
            $config .= "# Serve WebP images if browser supports it\n";
            $config .= "location ~* \\.(jpe?g|png|gif)$ {\n";
            $config .= "    set \$webp_suffix \"\";\n";
            $config .= "    if (\$http_accept ~* \"image/webp\") {\n";
            $config .= "        set \$webp_suffix \".webp\";\n";
            $config .= "    }\n\n";
            
            $config .= "    # Try WebP first, then original\n";
            $config .= "    try_files \$uri\$webp_suffix \$uri =404;\n\n";
            
            $config .= "    # Set proper content type for WebP\n";
            $config .= "    location ~ \\.webp$ {\n";
            $config .= "        add_header Content-Type image/webp;\n";
            $config .= "        add_header Vary Accept;\n";
            $config .= "        expires 1y;\n";
            $config .= "        add_header Cache-Control \"public, immutable\";\n";
            $config .= "    }\n";
            $config .= "}\n\n";
            
            $config .= "# On-the-fly WebP conversion\n";
            $config .= "location ~ ^/wp-content/uploads/(.+)\\.(jpe?g|png|gif)\\.sio\\.webp$ {\n";
            $config .= "    try_files \$uri @sio_convert;\n";
            $config .= "}\n\n";
        }
        
        $config .= "# Fallback to WordPress for conversion\n";
        $config .= "location @sio_convert {\n";
        $config .= "    rewrite ^/wp-content/uploads/(.+)\\.(jpe?g|png|gif)\\.sio\\.(webp|avif)$ /index.php?sio_convert=1&sio_path=\$1&sio_ext=\$2&sio_format=\$3 last;\n";
        $config .= "}\n\n";
        
        $config .= "# General image caching\n";
        $config .= "location ~* \\.(jpe?g|png|gif|webp|avif)$ {\n";
        $config .= "    expires 1y;\n";
        $config .= "    add_header Cache-Control \"public, immutable\";\n";
        $config .= "    add_header Vary Accept;\n";
        $config .= "}\n\n";
        
        $config .= "# End Smart Image Optimizer\n";
        
        return $config;
    }
    
    /**
     * Write Apache configuration to .htaccess
     *
     * @return bool|WP_Error Success or error
     */
    public function write_apache_config() {
        $htaccess_path = ABSPATH . '.htaccess';
        
        if (!is_writable($htaccess_path) && !is_writable(ABSPATH)) {
            return new WP_Error('not_writable', 'Cannot write to .htaccess file');
        }
        
        $existing_content = '';
        if (file_exists($htaccess_path)) {
            $existing_content = file_get_contents($htaccess_path);
        }
        
        // Remove existing SIO configuration
        $existing_content = $this->remove_existing_config($existing_content);
        
        // Add new configuration
        $new_config = $this->generate_apache_config();
        $updated_content = $new_config . "\n" . $existing_content;
        
        $result = file_put_contents($htaccess_path, $updated_content);
        
        if ($result === false) {
            return new WP_Error('write_failed', 'Failed to write .htaccess file');
        }
        
        // Log the action
        SIO_Monitor::instance()->log(
            'Apache configuration updated in .htaccess',
            'success',
            'server_config',
            array('file' => $htaccess_path)
        );
        
        return true;
    }
    
    /**
     * Remove existing SIO configuration from content
     *
     * @param string $content File content
     * @return string Cleaned content
     */
    public function remove_existing_config($content) {
        // Remove between SIO markers
        $pattern = '/# Smart Image Optimizer.*?# End Smart Image Optimizer\s*/s';
        return preg_replace($pattern, '', $content);
    }
    
    /**
     * Export Nginx configuration to file
     *
     * @return string|WP_Error File path or error
     */
    public function export_nginx_config() {
        $upload_dir = wp_upload_dir();
        $config_path = $upload_dir['basedir'] . '/sio-nginx.conf';
        
        $config = $this->generate_nginx_config();
        
        $result = file_put_contents($config_path, $config);
        
        if ($result === false) {
            return new WP_Error('write_failed', 'Failed to write Nginx configuration file');
        }
        
        // Log the action
        SIO_Monitor::instance()->log(
            'Nginx configuration exported',
            'success',
            'server_config',
            array('file' => $config_path)
        );
        
        return $config_path;
    }
    
    /**
     * Check if server configuration is active
     *
     * @return array Status information
     */
    public function check_server_config_status() {
        $status = array(
            'apache_htaccess' => false,
            'nginx_config' => false,
            'wordpress_fallback' => true,
            'recommendations' => array()
        );
        
        // Check Apache .htaccess
        $htaccess_path = ABSPATH . '.htaccess';
        if (file_exists($htaccess_path)) {
            $content = file_get_contents($htaccess_path);
            if (strpos($content, 'Smart Image Optimizer') !== false) {
                $status['apache_htaccess'] = true;
            }
        }
        
        // Check server type
        $server_software = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '';
        
        if (stripos($server_software, 'apache') !== false) {
            if (!$status['apache_htaccess']) {
                $status['recommendations'][] = 'Enable Apache .htaccess rules for better performance';
            }
        } elseif (stripos($server_software, 'nginx') !== false) {
            $status['recommendations'][] = 'Add Nginx configuration rules to your server block';
        }
        
        return $status;
    }
    
    /**
     * Handle AJAX conversion request
     */
    public function handle_ajax_conversion() {
        // This is handled by the rewrite rules and maybe_handle_image_request
        // This method exists for compatibility
        wp_die('Direct AJAX conversion not supported');
    }
}