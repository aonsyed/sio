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
     * Detect available image libraries
     */
    private function detect_image_libraries() {
        $this->available_libraries = array();
        
        // Check for ImageMagick
        if (extension_loaded('imagick')) {
            $imagick = new Imagick();
            $formats = $imagick->queryFormats();
            
            $this->available_libraries['imagick'] = array(
                'name' => 'ImageMagick',
                'version' => $imagick->getVersion()['versionString'],
                'supports_webp' => in_array('WEBP', $formats),
                'supports_avif' => in_array('AVIF', $formats),
                'formats' => $formats
            );
        }
        
        // Check for GD
        if (extension_loaded('gd')) {
            $gd_info = gd_info();
            
            $this->available_libraries['gd'] = array(
                'name' => 'GD Library',
                'version' => $gd_info['GD Version'],
                'supports_webp' => isset($gd_info['WebP Support']) && $gd_info['WebP Support'],
                'supports_avif' => function_exists('imageavif'),
                'formats' => array_keys(array_filter($gd_info, function($key) {
                    return strpos($key, 'Support') !== false && strpos($key, 'WebP') === false;
                }, ARRAY_FILTER_USE_KEY))
            );
        }
        
        // Cache the detection results
        set_transient('sio_library_check', $this->available_libraries, HOUR_IN_SECONDS);
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