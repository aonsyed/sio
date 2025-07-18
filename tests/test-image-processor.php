<?php
/**
 * Image Processor Tests
 *
 * @package SmartImageOptimizer
 */

/**
 * Image Processor Test Class
 */
class SIO_Image_Processor_Test extends WP_UnitTestCase {
    
    /**
     * Image processor instance
     *
     * @var SIO_Image_Processor
     */
    private $image_processor;
    
    /**
     * Test files to clean up
     *
     * @var array
     */
    private $test_files = array();
    
    /**
     * Set up test
     */
    public function setUp(): void {
        parent::setUp();
        $this->image_processor = SIO_Image_Processor::instance();
        $this->test_files = array();
    }
    
    /**
     * Tear down test
     */
    public function tearDown(): void {
        SIO_Test_Utilities::cleanup_test_files($this->test_files);
        parent::tearDown();
    }
    
    /**
     * Test singleton instance
     */
    public function test_singleton_instance() {
        $instance1 = SIO_Image_Processor::instance();
        $instance2 = SIO_Image_Processor::instance();
        
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf('SIO_Image_Processor', $instance1);
    }
    
    /**
     * Test library detection
     */
    public function test_library_detection() {
        $library = $this->image_processor->get_available_library();
        
        $this->assertContains($library, array('imagick', 'gd', 'none'));
        
        if ($library !== 'none') {
            $this->assertTrue($this->image_processor->is_library_available($library));
        }
    }
    
    /**
     * Test WebP support detection
     */
    public function test_webp_support_detection() {
        $library = $this->image_processor->get_available_library();
        
        if ($library === 'imagick') {
            $supports_webp = $this->image_processor->supports_format('webp');
            $this->assertIsBool($supports_webp);
        } elseif ($library === 'gd') {
            $supports_webp = function_exists('imagewebp');
            $this->assertEquals($supports_webp, $this->image_processor->supports_format('webp'));
        }
    }
    
    /**
     * Test AVIF support detection
     */
    public function test_avif_support_detection() {
        $library = $this->image_processor->get_available_library();
        
        if ($library === 'imagick') {
            $supports_avif = $this->image_processor->supports_format('avif');
            $this->assertIsBool($supports_avif);
        } elseif ($library === 'gd') {
            // GD doesn't support AVIF yet
            $this->assertFalse($this->image_processor->supports_format('avif'));
        }
    }
    
    /**
     * Test image conversion to WebP
     */
    public function test_webp_conversion() {
        if (!$this->image_processor->supports_format('webp')) {
            $this->markTestSkipped('WebP not supported by current image library');
        }
        
        // Create test image
        $test_image = SIO_Test_Utilities::create_test_image('jpg', 200, 200);
        $this->assertNotFalse($test_image);
        $this->test_files[] = $test_image;
        
        // Convert to WebP
        $webp_path = str_replace('.jpg', '.webp', $test_image);
        $result = $this->image_processor->convert_image($test_image, $webp_path, 'webp', 80);
        
        $this->assertTrue($result);
        $this->assertFileExists($webp_path);
        $this->test_files[] = $webp_path;
        
        // Check file size (WebP should be smaller)
        $original_size = filesize($test_image);
        $webp_size = filesize($webp_path);
        $this->assertLessThan($original_size, $webp_size);
    }
    
    /**
     * Test image conversion with quality settings
     */
    public function test_conversion_with_quality() {
        if (!$this->image_processor->supports_format('webp')) {
            $this->markTestSkipped('WebP not supported by current image library');
        }
        
        // Create test image
        $test_image = SIO_Test_Utilities::create_test_image('jpg', 200, 200);
        $this->assertNotFalse($test_image);
        $this->test_files[] = $test_image;
        
        // Convert with high quality
        $webp_high = str_replace('.jpg', '_high.webp', $test_image);
        $result_high = $this->image_processor->convert_image($test_image, $webp_high, 'webp', 95);
        $this->assertTrue($result_high);
        $this->test_files[] = $webp_high;
        
        // Convert with low quality
        $webp_low = str_replace('.jpg', '_low.webp', $test_image);
        $result_low = $this->image_processor->convert_image($test_image, $webp_low, 'webp', 30);
        $this->assertTrue($result_low);
        $this->test_files[] = $webp_low;
        
        // High quality should be larger than low quality
        $high_size = filesize($webp_high);
        $low_size = filesize($webp_low);
        $this->assertGreaterThan($low_size, $high_size);
    }
    
    /**
     * Test image resizing
     */
    public function test_image_resizing() {
        // Create large test image
        $test_image = SIO_Test_Utilities::create_test_image('jpg', 1000, 800);
        $this->assertNotFalse($test_image);
        $this->test_files[] = $test_image;
        
        // Resize image
        $resized_path = str_replace('.jpg', '_resized.jpg', $test_image);
        $result = $this->image_processor->resize_image($test_image, $resized_path, 500, 400);
        
        $this->assertTrue($result);
        $this->assertFileExists($resized_path);
        $this->test_files[] = $resized_path;
        
        // Check dimensions
        $image_info = getimagesize($resized_path);
        $this->assertEquals(500, $image_info[0]); // width
        $this->assertEquals(400, $image_info[1]); // height
    }
    
    /**
     * Test image resizing with aspect ratio preservation
     */
    public function test_resize_with_aspect_ratio() {
        // Create test image
        $test_image = SIO_Test_Utilities::create_test_image('jpg', 1000, 500);
        $this->assertNotFalse($test_image);
        $this->test_files[] = $test_image;
        
        // Resize with max dimensions (should preserve aspect ratio)
        $resized_path = str_replace('.jpg', '_aspect.jpg', $test_image);
        $result = $this->image_processor->resize_image_with_aspect_ratio($test_image, $resized_path, 600, 600);
        
        $this->assertTrue($result);
        $this->assertFileExists($resized_path);
        $this->test_files[] = $resized_path;
        
        // Check dimensions (should be 600x300 to preserve 2:1 aspect ratio)
        $image_info = getimagesize($resized_path);
        $this->assertEquals(600, $image_info[0]); // width
        $this->assertEquals(300, $image_info[1]); // height
    }
    
    /**
     * Test metadata stripping
     */
    public function test_metadata_stripping() {
        // Create test image with metadata
        $test_image = SIO_Test_Utilities::create_test_image('jpg', 200, 200);
        $this->assertNotFalse($test_image);
        $this->test_files[] = $test_image;
        
        // Process with metadata stripping
        $processed_path = str_replace('.jpg', '_no_meta.jpg', $test_image);
        $result = $this->image_processor->process_image($test_image, array(
            'strip_metadata' => true,
            'output_path' => $processed_path
        ));
        
        $this->assertTrue($result);
        $this->assertFileExists($processed_path);
        $this->test_files[] = $processed_path;
    }
    
    /**
     * Test progressive JPEG creation
     */
    public function test_progressive_jpeg() {
        // Create test image
        $test_image = SIO_Test_Utilities::create_test_image('jpg', 200, 200);
        $this->assertNotFalse($test_image);
        $this->test_files[] = $test_image;
        
        // Process with progressive JPEG
        $progressive_path = str_replace('.jpg', '_progressive.jpg', $test_image);
        $result = $this->image_processor->process_image($test_image, array(
            'progressive_jpeg' => true,
            'output_path' => $progressive_path
        ));
        
        $this->assertTrue($result);
        $this->assertFileExists($progressive_path);
        $this->test_files[] = $progressive_path;
    }
    
    /**
     * Test unsupported format handling
     */
    public function test_unsupported_format() {
        // Create test image
        $test_image = SIO_Test_Utilities::create_test_image('jpg', 100, 100);
        $this->assertNotFalse($test_image);
        $this->test_files[] = $test_image;
        
        // Try to convert to unsupported format
        $output_path = str_replace('.jpg', '.xyz', $test_image);
        $result = $this->image_processor->convert_image($test_image, $output_path, 'xyz', 80);
        
        $this->assertFalse($result);
        $this->assertFileDoesNotExist($output_path);
    }
    
    /**
     * Test invalid input file handling
     */
    public function test_invalid_input_file() {
        $non_existent_file = '/path/to/non/existent/file.jpg';
        $output_path = '/tmp/output.webp';
        
        $result = $this->image_processor->convert_image($non_existent_file, $output_path, 'webp', 80);
        
        $this->assertFalse($result);
    }
    
    /**
     * Test memory usage monitoring
     */
    public function test_memory_usage_monitoring() {
        $initial_memory = SIO_Test_Utilities::get_memory_usage();
        
        // Create and process multiple images
        for ($i = 0; $i < 3; $i++) {
            $test_image = SIO_Test_Utilities::create_test_image('jpg', 500, 500);
            $this->test_files[] = $test_image;
            
            if ($this->image_processor->supports_format('webp')) {
                $webp_path = str_replace('.jpg', "_$i.webp", $test_image);
                $this->image_processor->convert_image($test_image, $webp_path, 'webp', 80);
                $this->test_files[] = $webp_path;
            }
        }
        
        $final_memory = SIO_Test_Utilities::get_memory_usage();
        $memory_increase = $final_memory - $initial_memory;
        
        // Memory increase should be reasonable (less than 50MB for test images)
        $this->assertLessThan(50, $memory_increase);
    }
    
    /**
     * Test batch processing capability
     */
    public function test_batch_processing() {
        $test_images = array();
        $converted_images = array();
        
        // Create multiple test images
        for ($i = 0; $i < 5; $i++) {
            $test_image = SIO_Test_Utilities::create_test_image('jpg', 100, 100);
            $test_images[] = $test_image;
            $this->test_files[] = $test_image;
        }
        
        // Process batch
        foreach ($test_images as $image) {
            if ($this->image_processor->supports_format('webp')) {
                $webp_path = str_replace('.jpg', '.webp', $image);
                $result = $this->image_processor->convert_image($image, $webp_path, 'webp', 80);
                
                if ($result) {
                    $converted_images[] = $webp_path;
                    $this->test_files[] = $webp_path;
                }
            }
        }
        
        // All images should be converted successfully
        if ($this->image_processor->supports_format('webp')) {
            $this->assertCount(5, $converted_images);
            
            foreach ($converted_images as $converted) {
                $this->assertFileExists($converted);
            }
        }
    }
}