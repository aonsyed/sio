<?php
/**
 * Test Utilities Class
 *
 * @package SmartImageOptimizer
 */

/**
 * Test Utilities Class
 */
class SIO_Test_Utilities {
    
    /**
     * Create a test image file
     *
     * @param string $format Image format (jpg, png, gif)
     * @param int $width Image width
     * @param int $height Image height
     * @return string|false Path to created image or false on failure
     */
    public static function create_test_image($format = 'jpg', $width = 100, $height = 100) {
        $upload_dir = wp_upload_dir();
        $filename = 'test-image-' . uniqid() . '.' . $format;
        $filepath = $upload_dir['path'] . '/' . $filename;
        
        // Create image resource
        $image = imagecreatetruecolor($width, $height);
        
        // Fill with a color
        $color = imagecolorallocate($image, 255, 0, 0); // Red
        imagefill($image, 0, 0, $color);
        
        // Save based on format
        $success = false;
        switch ($format) {
            case 'jpg':
            case 'jpeg':
                $success = imagejpeg($image, $filepath, 90);
                break;
            case 'png':
                $success = imagepng($image, $filepath);
                break;
            case 'gif':
                $success = imagegif($image, $filepath);
                break;
        }
        
        imagedestroy($image);
        
        return $success ? $filepath : false;
    }
    
    /**
     * Clean up test files
     *
     * @param array $files Array of file paths to delete
     */
    public static function cleanup_test_files($files) {
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get test settings
     *
     * @return array
     */
    public static function get_test_settings() {
        return array(
            'auto_process' => false,
            'batch_mode' => false,
            'webp_quality' => 80,
            'avif_quality' => 70,
            'enable_webp' => true,
            'enable_avif' => false,
            'enable_resize' => false,
            'max_width' => 1920,
            'max_height' => 1080,
            'compression_level' => 6,
            'preserve_metadata' => false,
            'cleanup_originals' => false,
            'cleanup_after_days' => 30,
            'batch_size' => 5,
            'max_execution_time' => 60,
            'enable_logging' => true,
            'log_retention_days' => 7,
            'allowed_mime_types' => array('image/jpeg', 'image/png', 'image/gif'),
            'exclude_sizes' => array(),
            'backup_originals' => true,
            'progressive_jpeg' => true,
            'strip_metadata' => true,
            'enable_auto_serve' => false,
            'auto_htaccess' => false,
            'fallback_conversion' => true,
            'cache_duration' => 3600
        );
    }
    
    /**
     * Create test attachment
     *
     * @param string $filepath Path to image file
     * @return int Attachment ID
     */
    public static function create_test_attachment($filepath) {
        $filename = basename($filepath);
        $upload_dir = wp_upload_dir();
        
        // Copy file to uploads directory if not already there
        if (strpos($filepath, $upload_dir['path']) === false) {
            $new_filepath = $upload_dir['path'] . '/' . $filename;
            copy($filepath, $new_filepath);
            $filepath = $new_filepath;
        }
        
        // Create attachment
        $attachment = array(
            'guid' => $upload_dir['url'] . '/' . $filename,
            'post_mime_type' => wp_check_filetype($filename)['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attach_id = wp_insert_attachment($attachment, $filepath);
        
        // Generate metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        return $attach_id;
    }
    
    /**
     * Mock HTTP Accept header
     *
     * @param string $accept Accept header value
     */
    public static function mock_http_accept($accept) {
        $_SERVER['HTTP_ACCEPT'] = $accept;
    }
    
    /**
     * Reset HTTP Accept header
     */
    public static function reset_http_accept() {
        unset($_SERVER['HTTP_ACCEPT']);
    }
    
    /**
     * Get memory usage in MB
     *
     * @return float
     */
    public static function get_memory_usage() {
        return memory_get_usage(true) / 1024 / 1024;
    }
    
    /**
     * Get peak memory usage in MB
     *
     * @return float
     */
    public static function get_peak_memory_usage() {
        return memory_get_peak_usage(true) / 1024 / 1024;
    }
}