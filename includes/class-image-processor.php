<?php
/**
 * Image Processor Class
 *
 * Handles image conversion, optimization, and format detection
 *
 * @package SmartImageOptimizer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Image Processor Class
 */
class SIO_Image_Processor {
    
    /**
     * Instance
     *
     * @var SIO_Image_Processor
     */
    private static $instance = null;
    
    /**
     * Available image libraries
     *
     * @var array
     */
    private $available_libraries = array();
    
    /**
     * Current image library
     *
     * @var string
     */
    private $current_library = null;
    
    /**
     * Supported input formats
     *
     * @var array
     */
    private $supported_input_formats = array(
        'image/jpeg' => array('jpg', 'jpeg'),
        'image/png' => array('png'),
        'image/gif' => array('gif'),
        'image/webp' => array('webp'),
        'image/avif' => array('avif')
    );
    
    /**
     * Get instance
     *
     * @return SIO_Image_Processor
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
        $this->detect_image_libraries();
        $this->select_best_library();
        
        // Hook into WordPress image processing
        add_filter('wp_image_editors', array($this, 'add_custom_image_editor'));
        add_filter('wp_generate_attachment_metadata', array($this, 'generate_optimized_versions'), 10, 2);
    }
    
    /**
     * Detect available image libraries with robust error handling
     */
    private function detect_image_libraries() {
        // Check cache first
        $cached_libraries = get_transient('sio_library_check');
        if ($cached_libraries !== false && is_array($cached_libraries)) {
            $this->available_libraries = $cached_libraries;
            return;
        }
        
        $this->available_libraries = array();
        
        // Use WordPress's image editor detection as primary method
        $wp_editors = $this->get_wordpress_image_editors();
        
        // Detect ImageMagick with multiple fallback methods
        $imagick_info = $this->detect_imagick_library();
        if ($imagick_info) {
            $this->available_libraries['imagick'] = $imagick_info;
        }
        
        // Detect GD with multiple fallback methods
        $gd_info = $this->detect_gd_library();
        if ($gd_info) {
            $this->available_libraries['gd'] = $gd_info;
        }
        
        // Cross-reference with WordPress editors
        $this->validate_with_wordpress_editors($wp_editors);
        
        // Cache the detection results for 1 hour
        set_transient('sio_library_check', $this->available_libraries, HOUR_IN_SECONDS);
        
        // Log detection results for debugging
        $this->log_detection_results();
    }
    
    /**
     * Get WordPress image editors
     *
     * @return array
     */
    private function get_wordpress_image_editors() {
        $editors = array();
        
        try {
            // Get WordPress's available image editors
            $wp_editors = wp_image_editor_supports(array('mime_type' => 'image/jpeg'));
            
            if (is_array($wp_editors)) {
                foreach ($wp_editors as $editor_class) {
                    if (class_exists($editor_class)) {
                        $editors[] = $editor_class;
                    }
                }
            }
            
            // Also check the default editors list
            $default_editors = apply_filters('wp_image_editors', array('WP_Image_Editor_Imagick', 'WP_Image_Editor_GD'));
            foreach ($default_editors as $editor_class) {
                if (class_exists($editor_class) && !in_array($editor_class, $editors)) {
                    $editors[] = $editor_class;
                }
            }
            
        } catch (Exception $e) {
            // Fallback to default editors if WordPress detection fails
            $editors = array('WP_Image_Editor_Imagick', 'WP_Image_Editor_GD');
        }
        
        return $editors;
    }
    
    /**
     * Detect ImageMagick library with robust error handling
     *
     * @return array|false
     */
    private function detect_imagick_library() {
        // Method 1: Check if extension is loaded
        if (!extension_loaded('imagick')) {
            return false;
        }
        
        // Method 2: Check if class exists
        if (!class_exists('Imagick')) {
            return false;
        }
        
        try {
            // Method 3: Try to create Imagick instance
            $imagick = new Imagick();
            
            // Method 4: Get version information safely
            $version_info = array();
            try {
                $version_data = $imagick->getVersion();
                $version_info = array(
                    'version_string' => isset($version_data['versionString']) ? $version_data['versionString'] : 'Unknown',
                    'version_number' => isset($version_data['versionNumber']) ? $version_data['versionNumber'] : 0
                );
            } catch (Exception $e) {
                $version_info = array(
                    'version_string' => 'Unknown (detection failed)',
                    'version_number' => 0
                );
            }
            
            // Method 5: Query supported formats safely
            $formats = array();
            $webp_support = false;
            $avif_support = false;
            
            try {
                $formats = $imagick->queryFormats();
                $webp_support = in_array('WEBP', $formats);
                $avif_support = in_array('AVIF', $formats);
            } catch (Exception $e) {
                // Fallback: Test format support individually
                $webp_support = $this->test_imagick_format_support('WEBP');
                $avif_support = $this->test_imagick_format_support('AVIF');
                $formats = array('JPEG', 'PNG', 'GIF'); // Basic formats
                if ($webp_support) $formats[] = 'WEBP';
                if ($avif_support) $formats[] = 'AVIF';
            }
            
            // Clean up
            $imagick->clear();
            $imagick->destroy();
            
            return array(
                'name' => 'ImageMagick',
                'version' => $version_info['version_string'],
                'version_number' => $version_info['version_number'],
                'supports_webp' => $webp_support,
                'supports_avif' => $avif_support,
                'formats' => $formats,
                'detection_method' => 'direct_imagick',
                'available' => true
            );
            
        } catch (Exception $e) {
            // Method 6: Fallback detection using WordPress
            return $this->detect_imagick_via_wordpress();
        }
    }
    
    /**
     * Test ImageMagick format support individually
     *
     * @param string $format Format to test
     * @return bool
     */
    private function test_imagick_format_support($format) {
        try {
            $imagick = new Imagick();
            $imagick->newImage(1, 1, 'white');
            $imagick->setImageFormat($format);
            $result = $imagick->getImageFormat() === $format;
            $imagick->clear();
            $imagick->destroy();
            return $result;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Detect ImageMagick via WordPress image editor
     *
     * @return array|false
     */
    private function detect_imagick_via_wordpress() {
        if (!class_exists('WP_Image_Editor_Imagick')) {
            return false;
        }
        
        try {
            $editor = new WP_Image_Editor_Imagick();
            
            if (method_exists($editor, 'test')) {
                $test_result = $editor->test();
                if (is_wp_error($test_result)) {
                    return false;
                }
            }
            
            // Test WebP and AVIF support through WordPress editor
            $webp_support = false;
            $avif_support = false;
            
            if (method_exists($editor, 'supports_mime_type')) {
                $webp_support = $editor->supports_mime_type('image/webp');
                $avif_support = $editor->supports_mime_type('image/avif');
            }
            
            return array(
                'name' => 'ImageMagick (via WordPress)',
                'version' => 'Unknown',
                'version_number' => 0,
                'supports_webp' => $webp_support,
                'supports_avif' => $avif_support,
                'formats' => array('JPEG', 'PNG', 'GIF'),
                'detection_method' => 'wordpress_editor',
                'available' => true
            );
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Detect GD library with robust error handling
     *
     * @return array|false
     */
    private function detect_gd_library() {
        // Method 1: Check if extension is loaded
        if (!extension_loaded('gd')) {
            return false;
        }
        
        // Method 2: Check if gd_info function exists
        if (!function_exists('gd_info')) {
            return false;
        }
        
        try {
            // Method 3: Get GD information safely
            $gd_info = gd_info();
            
            if (!is_array($gd_info)) {
                return false;
            }
            
            // Method 4: Extract version information
            $version = isset($gd_info['GD Version']) ? $gd_info['GD Version'] : 'Unknown';
            
            // Method 5: Test format support with multiple methods
            $webp_support = $this->test_gd_webp_support($gd_info);
            $avif_support = $this->test_gd_avif_support();
            
            // Method 6: Get supported formats
            $formats = $this->get_gd_supported_formats($gd_info);
            
            return array(
                'name' => 'GD Library',
                'version' => $version,
                'supports_webp' => $webp_support,
                'supports_avif' => $avif_support,
                'formats' => $formats,
                'detection_method' => 'direct_gd',
                'available' => true,
                'gd_info' => $gd_info
            );
            
        } catch (Exception $e) {
            // Method 7: Fallback detection using WordPress
            return $this->detect_gd_via_wordpress();
        }
    }
    
    /**
     * Test GD WebP support with multiple methods
     *
     * @param array $gd_info GD info array
     * @return bool
     */
    private function test_gd_webp_support($gd_info = null) {
        // Method 1: Check gd_info
        if (is_array($gd_info)) {
            if (isset($gd_info['WebP Support']) && $gd_info['WebP Support']) {
                return true;
            }
        }
        
        // Method 2: Check function existence
        if (function_exists('imagewebp')) {
            // Method 3: Try to create a test WebP image
            try {
                $image = imagecreatetruecolor(1, 1);
                if ($image) {
                    $temp_file = tempnam(sys_get_temp_dir(), 'sio_webp_test');
                    $result = imagewebp($image, $temp_file, 80);
                    imagedestroy($image);
                    if ($temp_file && file_exists($temp_file)) {
                        unlink($temp_file);
                    }
                    return $result;
                }
            } catch (Exception $e) {
                // Fall through to return false
            }
        }
        
        return false;
    }
    
    /**
     * Test GD AVIF support
     *
     * @return bool
     */
    private function test_gd_avif_support() {
        // Method 1: Check function existence
        if (!function_exists('imageavif')) {
            return false;
        }
        
        // Method 2: Try to create a test AVIF image
        try {
            $image = imagecreatetruecolor(1, 1);
            if ($image) {
                $temp_file = tempnam(sys_get_temp_dir(), 'sio_avif_test');
                $result = imageavif($image, $temp_file, 80);
                imagedestroy($image);
                if ($temp_file && file_exists($temp_file)) {
                    unlink($temp_file);
                }
                return $result;
            }
        } catch (Exception $e) {
            // Fall through to return false
        }
        
        return false;
    }
    
    /**
     * Get GD supported formats
     *
     * @param array $gd_info GD info array
     * @return array
     */
    private function get_gd_supported_formats($gd_info) {
        $formats = array();
        
        // Basic formats
        if (isset($gd_info['JPEG Support']) && $gd_info['JPEG Support']) {
            $formats[] = 'JPEG';
        }
        if (isset($gd_info['PNG Support']) && $gd_info['PNG Support']) {
            $formats[] = 'PNG';
        }
        if (isset($gd_info['GIF Read Support']) && $gd_info['GIF Read Support']) {
            $formats[] = 'GIF';
        }
        
        // Modern formats
        if (isset($gd_info['WebP Support']) && $gd_info['WebP Support']) {
            $formats[] = 'WEBP';
        }
        if (function_exists('imageavif')) {
            $formats[] = 'AVIF';
        }
        
        return $formats;
    }
    
    /**
     * Detect GD via WordPress image editor
     *
     * @return array|false
     */
    private function detect_gd_via_wordpress() {
        if (!class_exists('WP_Image_Editor_GD')) {
            return false;
        }
        
        try {
            $editor = new WP_Image_Editor_GD();
            
            if (method_exists($editor, 'test')) {
                $test_result = $editor->test();
                if (is_wp_error($test_result)) {
                    return false;
                }
            }
            
            // Test format support through WordPress editor
            $webp_support = false;
            $avif_support = false;
            
            if (method_exists($editor, 'supports_mime_type')) {
                $webp_support = $editor->supports_mime_type('image/webp');
                $avif_support = $editor->supports_mime_type('image/avif');
            }
            
            return array(
                'name' => 'GD Library (via WordPress)',
                'version' => 'Unknown',
                'supports_webp' => $webp_support,
                'supports_avif' => $avif_support,
                'formats' => array('JPEG', 'PNG', 'GIF'),
                'detection_method' => 'wordpress_editor',
                'available' => true
            );
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Validate detected libraries with WordPress editors
     *
     * @param array $wp_editors WordPress image editors
     */
    private function validate_with_wordpress_editors($wp_editors) {
        foreach ($wp_editors as $editor_class) {
            if ($editor_class === 'WP_Image_Editor_Imagick' && !isset($this->available_libraries['imagick'])) {
                // WordPress thinks ImageMagick is available but we didn't detect it
                $imagick_info = $this->detect_imagick_via_wordpress();
                if ($imagick_info) {
                    $this->available_libraries['imagick'] = $imagick_info;
                }
            }
            
            if ($editor_class === 'WP_Image_Editor_GD' && !isset($this->available_libraries['gd'])) {
                // WordPress thinks GD is available but we didn't detect it
                $gd_info = $this->detect_gd_via_wordpress();
                if ($gd_info) {
                    $this->available_libraries['gd'] = $gd_info;
                }
            }
        }
    }
    
    /**
     * Log detection results for debugging
     */
    private function log_detection_results() {
        if (!SIO_Settings_Manager::instance()->get_setting('enable_logging')) {
            return;
        }
        
        $message = sprintf(
            'Image library detection completed. Found: %s',
            empty($this->available_libraries) ? 'None' : implode(', ', array_keys($this->available_libraries))
        );
        
        // Add detailed information for each library
        foreach ($this->available_libraries as $lib_name => $lib_info) {
            $message .= sprintf(
                ' | %s: %s (WebP: %s, AVIF: %s, Method: %s)',
                $lib_name,
                $lib_info['version'],
                $lib_info['supports_webp'] ? 'Yes' : 'No',
                $lib_info['supports_avif'] ? 'Yes' : 'No',
                $lib_info['detection_method']
            );
        }
        
        SIO_Monitor::instance()->log_action(0, 'library_detection', 'success', $message);
    }
    
    /**
     * Select the best available library
     */
    private function select_best_library() {
        // Prefer ImageMagick over GD for better quality and format support
        if (isset($this->available_libraries['imagick'])) {
            $this->current_library = 'imagick';
        } elseif (isset($this->available_libraries['gd'])) {
            $this->current_library = 'gd';
        } else {
            $this->current_library = null;
        }
        
        // Allow filtering of the selected library
        $this->current_library = apply_filters('sio_selected_image_library', $this->current_library, $this->available_libraries);
    }
    
    /**
     * Get available libraries
     *
     * @return array
     */
    public function get_available_libraries() {
        return $this->available_libraries;
    }
    
    /**
     * Get current library
     *
     * @return string|null
     */
    public function get_current_library() {
        return $this->current_library;
    }
    
    /**
     * Force re-detection of image libraries (clears cache)
     *
     * @return array
     */
    public function force_library_detection() {
        // Clear the cache
        delete_transient('sio_library_check');
        
        // Re-run detection
        $this->detect_image_libraries();
        $this->select_best_library();
        
        return $this->available_libraries;
    }
    
    /**
     * Get detailed library information for debugging
     *
     * @return array
     */
    public function get_library_debug_info() {
        $debug_info = array(
            'detection_timestamp' => current_time('mysql'),
            'wordpress_editors' => $this->get_wordpress_image_editors(),
            'php_extensions' => array(
                'imagick_loaded' => extension_loaded('imagick'),
                'gd_loaded' => extension_loaded('gd'),
                'imagick_class_exists' => class_exists('Imagick'),
                'gd_info_function_exists' => function_exists('gd_info')
            ),
            'function_tests' => array(
                'imagewebp_exists' => function_exists('imagewebp'),
                'imageavif_exists' => function_exists('imageavif')
            ),
            'detected_libraries' => $this->available_libraries,
            'selected_library' => $this->current_library,
            'cache_status' => get_transient('sio_library_check') !== false ? 'cached' : 'fresh'
        );
        
        // Add GD specific info if available
        if (function_exists('gd_info')) {
            try {
                $debug_info['gd_detailed_info'] = gd_info();
            } catch (Exception $e) {
                $debug_info['gd_detailed_info'] = array('error' => $e->getMessage());
            }
        }
        
        // Add ImageMagick specific info if available
        if (class_exists('Imagick')) {
            try {
                $imagick = new Imagick();
                $debug_info['imagick_detailed_info'] = array(
                    'version' => $imagick->getVersion(),
                    'formats' => $imagick->queryFormats(),
                    'configure_options' => $imagick->getConfigureOptions()
                );
                $imagick->clear();
                $imagick->destroy();
            } catch (Exception $e) {
                $debug_info['imagick_detailed_info'] = array('error' => $e->getMessage());
            }
        }
        
        return $debug_info;
    }
    
    /**
     * Test library functionality with a real image
     *
     * @return array
     */
    public function test_library_functionality() {
        $test_results = array();
        
        // Create a simple test image
        $test_image_path = $this->create_test_image();
        
        if (!$test_image_path) {
            return array('error' => 'Could not create test image');
        }
        
        // Test each available library
        foreach ($this->available_libraries as $lib_name => $lib_info) {
            $test_results[$lib_name] = $this->test_single_library($lib_name, $test_image_path);
        }
        
        // Clean up test image
        if (file_exists($test_image_path)) {
            unlink($test_image_path);
        }
        
        return $test_results;
    }
    
    /**
     * Create a test image for functionality testing
     *
     * @return string|false
     */
    private function create_test_image() {
        try {
            $test_image = imagecreatetruecolor(100, 100);
            if (!$test_image) {
                return false;
            }
            
            // Fill with a simple pattern
            $white = imagecolorallocate($test_image, 255, 255, 255);
            $blue = imagecolorallocate($test_image, 0, 100, 200);
            imagefill($test_image, 0, 0, $white);
            imagefilledrectangle($test_image, 25, 25, 75, 75, $blue);
            
            // Save as JPEG
            $temp_path = tempnam(sys_get_temp_dir(), 'sio_test_') . '.jpg';
            $result = imagejpeg($test_image, $temp_path, 90);
            imagedestroy($test_image);
            
            return $result ? $temp_path : false;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Test a single library's functionality
     *
     * @param string $lib_name Library name
     * @param string $test_image_path Test image path
     * @return array
     */
    private function test_single_library($lib_name, $test_image_path) {
        $results = array(
            'basic_load' => false,
            'webp_conversion' => false,
            'avif_conversion' => false,
            'errors' => array()
        );
        
        try {
            if ($lib_name === 'imagick' && class_exists('Imagick')) {
                $results = $this->test_imagick_functionality($test_image_path);
            } elseif ($lib_name === 'gd' && extension_loaded('gd')) {
                $results = $this->test_gd_functionality($test_image_path);
            }
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Test ImageMagick functionality
     *
     * @param string $test_image_path Test image path
     * @return array
     */
    private function test_imagick_functionality($test_image_path) {
        $results = array(
            'basic_load' => false,
            'webp_conversion' => false,
            'avif_conversion' => false,
            'errors' => array()
        );
        
        try {
            // Test basic loading
            $imagick = new Imagick($test_image_path);
            $results['basic_load'] = true;
            
            // Test WebP conversion
            if ($this->available_libraries['imagick']['supports_webp']) {
                $webp_path = tempnam(sys_get_temp_dir(), 'sio_webp_test_') . '.webp';
                $imagick->setImageFormat('WEBP');
                $imagick->writeImage($webp_path);
                $results['webp_conversion'] = file_exists($webp_path) && filesize($webp_path) > 0;
                if (file_exists($webp_path)) unlink($webp_path);
            }
            
            // Test AVIF conversion
            if ($this->available_libraries['imagick']['supports_avif']) {
                $avif_path = tempnam(sys_get_temp_dir(), 'sio_avif_test_') . '.avif';
                $imagick->setImageFormat('AVIF');
                $imagick->writeImage($avif_path);
                $results['avif_conversion'] = file_exists($avif_path) && filesize($avif_path) > 0;
                if (file_exists($avif_path)) unlink($avif_path);
            }
            
            $imagick->clear();
            $imagick->destroy();
            
        } catch (Exception $e) {
            $results['errors'][] = 'ImageMagick test failed: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Test GD functionality
     *
     * @param string $test_image_path Test image path
     * @return array
     */
    private function test_gd_functionality($test_image_path) {
        $results = array(
            'basic_load' => false,
            'webp_conversion' => false,
            'avif_conversion' => false,
            'errors' => array()
        );
        
        try {
            // Test basic loading
            $image = imagecreatefromjpeg($test_image_path);
            if ($image) {
                $results['basic_load'] = true;
                
                // Test WebP conversion
                if ($this->available_libraries['gd']['supports_webp'] && function_exists('imagewebp')) {
                    $webp_path = tempnam(sys_get_temp_dir(), 'sio_webp_test_') . '.webp';
                    $webp_result = imagewebp($image, $webp_path, 80);
                    $results['webp_conversion'] = $webp_result && file_exists($webp_path) && filesize($webp_path) > 0;
                    if (file_exists($webp_path)) unlink($webp_path);
                }
                
                // Test AVIF conversion
                if ($this->available_libraries['gd']['supports_avif'] && function_exists('imageavif')) {
                    $avif_path = tempnam(sys_get_temp_dir(), 'sio_avif_test_') . '.avif';
                    $avif_result = imageavif($image, $avif_path, 80);
                    $results['avif_conversion'] = $avif_result && file_exists($avif_path) && filesize($avif_path) > 0;
                    if (file_exists($avif_path)) unlink($avif_path);
                }
                
                imagedestroy($image);
            }
            
        } catch (Exception $e) {
            $results['errors'][] = 'GD test failed: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Check if format is supported
     *
     * @param string $format Format to check (webp, avif, etc.)
     * @return bool
     */
    public function supports_format($format) {
        if (!$this->current_library || !isset($this->available_libraries[$this->current_library])) {
            return false;
        }
        
        $library = $this->available_libraries[$this->current_library];
        
        switch (strtolower($format)) {
            case 'webp':
                return $library['supports_webp'];
            case 'avif':
                return $library['supports_avif'];
            default:
                return false;
        }
    }
    
    /**
     * Process image file
     *
     * @param string $file_path Path to image file
     * @param array $options Processing options
     * @return array|WP_Error Processing results
     */
    public function process_image($file_path, $options = array()) {
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        // Performance monitoring hook - before processing
        do_action('sio_before_process_image', $file_path);
        
        // Validate file
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('Image file not found.', 'smart-image-optimizer'));
        }
        
        // Security check
        if (!SIO_Security::instance()->validate_image_file($file_path)) {
            return new WP_Error('security_check_failed', __('Image file failed security validation.', 'smart-image-optimizer'));
        }
        
        // Get settings and apply performance optimizations
        $settings = SIO_Settings_Manager::instance()->get_settings();
        $options = wp_parse_args($options, $settings);
        
        // Apply performance optimizations to settings
        $options = apply_filters('sio_processing_settings', $options);
        
        // Get file info
        $file_info = $this->get_file_info($file_path);
        if (is_wp_error($file_info)) {
            return $file_info;
        }
        
        $results = array(
            'original_file' => $file_path,
            'original_size' => $file_info['size'],
            'original_format' => $file_info['mime_type'],
            'processed_files' => array(),
            'total_saved' => 0,
            'errors' => array()
        );
        
        // Process WebP conversion
        if ($options['enable_webp'] && $this->supports_format('webp')) {
            $webp_result = $this->convert_to_webp($file_path, $options);
            if (!is_wp_error($webp_result)) {
                $results['processed_files']['webp'] = $webp_result;
                $results['total_saved'] += $file_info['size'] - $webp_result['size'];
            } else {
                $results['errors']['webp'] = $webp_result->get_error_message();
            }
        }
        
        // Process AVIF conversion
        if ($options['enable_avif'] && $this->supports_format('avif')) {
            $avif_result = $this->convert_to_avif($file_path, $options);
            if (!is_wp_error($avif_result)) {
                $results['processed_files']['avif'] = $avif_result;
                $results['total_saved'] += $file_info['size'] - $avif_result['size'];
            } else {
                $results['errors']['avif'] = $avif_result->get_error_message();
            }
        }
        
        // Resize original if needed
        if ($options['enable_resize']) {
            $resize_result = $this->resize_image($file_path, $options);
            if (!is_wp_error($resize_result)) {
                $results['resized'] = $resize_result;
            } else {
                $results['errors']['resize'] = $resize_result->get_error_message();
            }
        }
        
        // Calculate execution metrics
        $results['execution_time'] = microtime(true) - $start_time;
        $results['memory_usage'] = memory_get_usage() - $start_memory;
        
        // Log the processing
        $this->log_processing_result($file_path, $results);
        
        // Performance monitoring hook - after processing
        do_action('sio_after_process_image', $file_path);
        
        return $results;
    }
    
    /**
     * Convert image to WebP format
     *
     * @param string $file_path Source file path
     * @param array $options Conversion options
     * @return array|WP_Error
     */
    private function convert_to_webp($file_path, $options) {
        $output_path = $this->get_output_path($file_path, 'webp');
        
        // Skip if WebP already exists and is newer
        if (file_exists($output_path) && filemtime($output_path) >= filemtime($file_path)) {
            return $this->get_file_info($output_path);
        }
        
        $quality = isset($options['webp_quality']) ? $options['webp_quality'] : 80;
        
        if ($this->current_library === 'imagick') {
            return $this->convert_with_imagick($file_path, $output_path, 'webp', $quality, $options);
        } elseif ($this->current_library === 'gd') {
            return $this->convert_with_gd($file_path, $output_path, 'webp', $quality, $options);
        }
        
        return new WP_Error('no_library', __('No suitable image library available for WebP conversion.', 'smart-image-optimizer'));
    }
    
    /**
     * Convert image to AVIF format
     *
     * @param string $file_path Source file path
     * @param array $options Conversion options
     * @return array|WP_Error
     */
    private function convert_to_avif($file_path, $options) {
        $output_path = $this->get_output_path($file_path, 'avif');
        
        // Skip if AVIF already exists and is newer
        if (file_exists($output_path) && filemtime($output_path) >= filemtime($file_path)) {
            return $this->get_file_info($output_path);
        }
        
        $quality = isset($options['avif_quality']) ? $options['avif_quality'] : 70;
        
        if ($this->current_library === 'imagick') {
            return $this->convert_with_imagick($file_path, $output_path, 'avif', $quality, $options);
        } elseif ($this->current_library === 'gd') {
            return $this->convert_with_gd($file_path, $output_path, 'avif', $quality, $options);
        }
        
        return new WP_Error('no_library', __('No suitable image library available for AVIF conversion.', 'smart-image-optimizer'));
    }
    
    /**
     * Convert image using ImageMagick
     *
     * @param string $input_path Input file path
     * @param string $output_path Output file path
     * @param string $format Output format
     * @param int $quality Image quality
     * @param array $options Additional options
     * @return array|WP_Error
     */
    private function convert_with_imagick($input_path, $output_path, $format, $quality, $options) {
        try {
            $imagick = new Imagick($input_path);
            
            // Set format
            $imagick->setImageFormat(strtoupper($format));
            
            // Set quality
            $imagick->setImageCompressionQuality($quality);
            
            // Set compression
            if (isset($options['compression_level'])) {
                $imagick->setImageCompression($options['compression_level']);
            }
            
            // Strip metadata if requested
            if (!empty($options['strip_metadata'])) {
                $imagick->stripImage();
            }
            
            // Progressive JPEG for WebP
            if ($format === 'webp' && !empty($options['progressive_jpeg'])) {
                $imagick->setImageInterlaceScheme(Imagick::INTERLACE_PLANE);
            }
            
            // Resize if needed
            if (!empty($options['enable_resize'])) {
                $this->resize_with_imagick($imagick, $options);
            }
            
            // Write the image
            $imagick->writeImage($output_path);
            $imagick->clear();
            $imagick->destroy();
            
            return $this->get_file_info($output_path);
            
        } catch (Exception $e) {
            return new WP_Error('imagick_error', sprintf(__('ImageMagick conversion failed: %s', 'smart-image-optimizer'), $e->getMessage()));
        }
    }
    
    /**
     * Convert image using GD
     *
     * @param string $input_path Input file path
     * @param string $output_path Output file path
     * @param string $format Output format
     * @param int $quality Image quality
     * @param array $options Additional options
     * @return array|WP_Error
     */
    private function convert_with_gd($input_path, $output_path, $format, $quality, $options) {
        // Get image info
        $image_info = getimagesize($input_path);
        if (!$image_info) {
            return new WP_Error('invalid_image', __('Invalid image file.', 'smart-image-optimizer'));
        }
        
        // Create image resource from source
        switch ($image_info['mime']) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($input_path);
                break;
            case 'image/png':
                $source = imagecreatefrompng($input_path);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($input_path);
                break;
            default:
                return new WP_Error('unsupported_format', __('Unsupported input format for GD conversion.', 'smart-image-optimizer'));
        }
        
        if (!$source) {
            return new WP_Error('gd_create_failed', __('Failed to create image resource.', 'smart-image-optimizer'));
        }
        
        // Resize if needed
        if (!empty($options['enable_resize'])) {
            $source = $this->resize_with_gd($source, $image_info[0], $image_info[1], $options);
            if (is_wp_error($source)) {
                return $source;
            }
        }
        
        // Convert and save
        $result = false;
        switch ($format) {
            case 'webp':
                if (function_exists('imagewebp')) {
                    $result = imagewebp($source, $output_path, $quality);
                }
                break;
            case 'avif':
                if (function_exists('imageavif')) {
                    $result = imageavif($source, $output_path, $quality);
                }
                break;
        }
        
        imagedestroy($source);
        
        if (!$result) {
            return new WP_Error('gd_conversion_failed', sprintf(__('GD %s conversion failed.', 'smart-image-optimizer'), strtoupper($format)));
        }
        
        return $this->get_file_info($output_path);
    }
    
    /**
     * Resize image
     *
     * @param string $file_path Image file path
     * @param array $options Resize options
     * @return array|WP_Error
     */
    private function resize_image($file_path, $options) {
        $max_width = isset($options['max_width']) ? $options['max_width'] : 1920;
        $max_height = isset($options['max_height']) ? $options['max_height'] : 1080;
        
        // Get current dimensions
        $image_info = getimagesize($file_path);
        if (!$image_info) {
            return new WP_Error('invalid_image', __('Invalid image file for resizing.', 'smart-image-optimizer'));
        }
        
        $current_width = $image_info[0];
        $current_height = $image_info[1];
        
        // Check if resize is needed
        if ($current_width <= $max_width && $current_height <= $max_height) {
            return array('resized' => false, 'message' => 'No resize needed');
        }
        
        // Calculate new dimensions
        $ratio = min($max_width / $current_width, $max_height / $current_height);
        $new_width = round($current_width * $ratio);
        $new_height = round($current_height * $ratio);
        
        if ($this->current_library === 'imagick') {
            return $this->resize_with_imagick_file($file_path, $new_width, $new_height);
        } elseif ($this->current_library === 'gd') {
            return $this->resize_with_gd_file($file_path, $new_width, $new_height);
        }
        
        return new WP_Error('no_library', __('No suitable image library available for resizing.', 'smart-image-optimizer'));
    }
    
    /**
     * Resize with ImageMagick (existing Imagick object)
     *
     * @param Imagick $imagick Imagick object
     * @param array $options Resize options
     */
    private function resize_with_imagick($imagick, $options) {
        $max_width = isset($options['max_width']) ? $options['max_width'] : 1920;
        $max_height = isset($options['max_height']) ? $options['max_height'] : 1080;
        
        $current_width = $imagick->getImageWidth();
        $current_height = $imagick->getImageHeight();
        
        if ($current_width > $max_width || $current_height > $max_height) {
            $imagick->resizeImage($max_width, $max_height, Imagick::FILTER_LANCZOS, 1, true);
        }
    }
    
    /**
     * Resize with ImageMagick (file path)
     *
     * @param string $file_path File path
     * @param int $new_width New width
     * @param int $new_height New height
     * @return array|WP_Error
     */
    private function resize_with_imagick_file($file_path, $new_width, $new_height) {
        try {
            $imagick = new Imagick($file_path);
            $imagick->resizeImage($new_width, $new_height, Imagick::FILTER_LANCZOS, 1);
            $imagick->writeImage($file_path);
            $imagick->clear();
            $imagick->destroy();
            
            return array(
                'resized' => true,
                'new_width' => $new_width,
                'new_height' => $new_height
            );
            
        } catch (Exception $e) {
            return new WP_Error('imagick_resize_error', sprintf(__('ImageMagick resize failed: %s', 'smart-image-optimizer'), $e->getMessage()));
        }
    }
    
    /**
     * Resize with GD (existing resource)
     *
     * @param resource $source Source image resource
     * @param int $current_width Current width
     * @param int $current_height Current height
     * @param array $options Resize options
     * @return resource|WP_Error
     */
    private function resize_with_gd($source, $current_width, $current_height, $options) {
        $max_width = isset($options['max_width']) ? $options['max_width'] : 1920;
        $max_height = isset($options['max_height']) ? $options['max_height'] : 1080;
        
        if ($current_width <= $max_width && $current_height <= $max_height) {
            return $source;
        }
        
        // Calculate new dimensions
        $ratio = min($max_width / $current_width, $max_height / $current_height);
        $new_width = round($current_width * $ratio);
        $new_height = round($current_height * $ratio);
        
        // Create new image
        $resized = imagecreatetruecolor($new_width, $new_height);
        if (!$resized) {
            return new WP_Error('gd_create_failed', __('Failed to create resized image resource.', 'smart-image-optimizer'));
        }
        
        // Preserve transparency for PNG and GIF
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        
        // Resize
        if (!imagecopyresampled($resized, $source, 0, 0, 0, 0, $new_width, $new_height, $current_width, $current_height)) {
            imagedestroy($resized);
            return new WP_Error('gd_resize_failed', __('GD resize operation failed.', 'smart-image-optimizer'));
        }
        
        imagedestroy($source);
        return $resized;
    }
    
    /**
     * Resize with GD (file path)
     *
     * @param string $file_path File path
     * @param int $new_width New width
     * @param int $new_height New height
     * @return array|WP_Error
     */
    private function resize_with_gd_file($file_path, $new_width, $new_height) {
        $image_info = getimagesize($file_path);
        if (!$image_info) {
            return new WP_Error('invalid_image', __('Invalid image file for GD resize.', 'smart-image-optimizer'));
        }
        
        // Create source image
        switch ($image_info['mime']) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($file_path);
                break;
            case 'image/png':
                $source = imagecreatefrompng($file_path);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($file_path);
                break;
            default:
                return new WP_Error('unsupported_format', __('Unsupported format for GD resize.', 'smart-image-optimizer'));
        }
        
        if (!$source) {
            return new WP_Error('gd_create_failed', __('Failed to create source image resource.', 'smart-image-optimizer'));
        }
        
        // Resize
        $resized = $this->resize_with_gd($source, $image_info[0], $image_info[1], array(
            'max_width' => $new_width,
            'max_height' => $new_height
        ));
        
        if (is_wp_error($resized)) {
            return $resized;
        }
        
        // Save resized image
        $result = false;
        switch ($image_info['mime']) {
            case 'image/jpeg':
                $result = imagejpeg($resized, $file_path, 90);
                break;
            case 'image/png':
                $result = imagepng($resized, $file_path, 6);
                break;
            case 'image/gif':
                $result = imagegif($resized, $file_path);
                break;
        }
        
        imagedestroy($resized);
        
        if (!$result) {
            return new WP_Error('gd_save_failed', __('Failed to save resized image.', 'smart-image-optimizer'));
        }
        
        return array(
            'resized' => true,
            'new_width' => $new_width,
            'new_height' => $new_height
        );
    }
    
    /**
     * Get output path for converted image
     *
     * @param string $input_path Input file path
     * @param string $format Output format
     * @return string
     */
    private function get_output_path($input_path, $format) {
        $path_info = pathinfo($input_path);
        return $path_info['dirname'] . '/' . $path_info['filename'] . '.' . $format;
    }
    
    /**
     * Get file information
     *
     * @param string $file_path File path
     * @return array|WP_Error
     */
    private function get_file_info($file_path) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('File not found.', 'smart-image-optimizer'));
        }
        
        $size = filesize($file_path);
        $mime_type = wp_check_filetype($file_path)['type'];
        $image_info = getimagesize($file_path);
        
        return array(
            'path' => $file_path,
            'size' => $size,
            'mime_type' => $mime_type,
            'width' => $image_info ? $image_info[0] : 0,
            'height' => $image_info ? $image_info[1] : 0,
            'created' => filemtime($file_path)
        );
    }
    
    /**
     * Log processing result
     *
     * @param string $file_path Processed file path
     * @param array $results Processing results
     */
    private function log_processing_result($file_path, $results) {
        if (!SIO_Settings_Manager::instance()->get_setting('enable_logging')) {
            return;
        }
        
        $attachment_id = attachment_url_to_postid($file_path);
        $status = empty($results['errors']) ? 'success' : 'partial';
        
        $message = sprintf(
            'Processed %s. Formats: %s. Saved: %s bytes. Time: %.2fs',
            basename($file_path),
            implode(', ', array_keys($results['processed_files'])),
            number_format($results['total_saved']),
            $results['execution_time']
        );
        
        SIO_Monitor::instance()->log_action(
            $attachment_id,
            'image_processing',
            $status,
            $message,
            $results['execution_time'],
            $results['memory_usage']
        );
    }
    
    /**
     * Cleanup old files
     *
     * @param int $days Days to keep files
     */
    public function cleanup_old_files($days = null) {
        $settings = SIO_Settings_Manager::instance()->get_settings();
        
        if (!$settings['cleanup_originals']) {
            return;
        }
        
        $days = $days ?: $settings['cleanup_after_days'];
        $cutoff_time = time() - ($days * DAY_IN_SECONDS);
        
        // Get upload directory
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];
        
        // Find old backup files
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base_dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        $cleaned_count = 0;
        $cleaned_size = 0;
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getMTime() < $cutoff_time) {
                $filename = $file->getFilename();
                
                // Only cleanup backup files (with .backup extension or similar pattern)
                if (strpos($filename, '.backup') !== false || strpos($filename, '.original') !== false) {
                    $size = $file->getSize();
                    if (unlink($file->getPathname())) {
                        $cleaned_count++;
                        $cleaned_size += $size;
                    }
                }
            }
        }
        
        // Log cleanup results
        if ($cleaned_count > 0) {
            $message = sprintf(
                'Cleanup completed. Removed %d files, freed %s bytes.',
                $cleaned_count,
                number_format($cleaned_size)
            );
            
            SIO_Monitor::instance()->log_action(0, 'cleanup', 'success', $message);
        }
    }
    
    /**
     * Add custom image editor to WordPress
     *
     * @param array $editors Available editors
     * @return array
     */
    public function add_custom_image_editor($editors) {
        // This would add our custom editor if we implement one
        return $editors;
    }
    
    /**
     * Generate optimized versions during attachment metadata generation
     *
     * @param array $metadata Attachment metadata
     * @param int $attachment_id Attachment ID
     * @return array
     */
    public function generate_optimized_versions($metadata, $attachment_id) {
        if (!isset($metadata['file'])) {
            return $metadata;
        }
        
        $settings = SIO_Settings_Manager::instance()->get_settings();
        
        // Skip if auto-processing is disabled
        if (!$settings['auto_process']) {
            return $metadata;
        }
        
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $metadata['file'];
        
        // Process the main image
        $result = $this->process_image($file_path);
        
        if (!is_wp_error($result)) {
            // Store processing results in metadata
            $metadata['sio_processed'] = $result;
            
            // Update attachment metadata
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
        
        return $metadata;
    }
}