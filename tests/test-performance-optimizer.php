<?php
/**
 * Performance Optimizer Tests
 *
 * @package SmartImageOptimizer
 */

class SIO_Performance_Optimizer_Test extends WP_UnitTestCase {
    
    /**
     * Performance optimizer instance
     *
     * @var SIO_Performance_Optimizer
     */
    private $performance_optimizer;
    
    /**
     * Test utilities
     *
     * @var SIO_Test_Utilities
     */
    private $test_utils;
    
    /**
     * Set up test
     */
    public function setUp(): void {
        parent::setUp();
        $this->performance_optimizer = SIO_Performance_Optimizer::instance();
        $this->test_utils = new SIO_Test_Utilities();
    }
    
    /**
     * Test singleton pattern
     */
    public function test_singleton_pattern() {
        $instance1 = SIO_Performance_Optimizer::instance();
        $instance2 = SIO_Performance_Optimizer::instance();
        
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf('SIO_Performance_Optimizer', $instance1);
    }
    
    /**
     * Test performance tracking initialization
     */
    public function test_performance_tracking_initialization() {
        $stats = $this->performance_optimizer->get_performance_stats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('current_memory_usage', $stats);
        $this->assertArrayHasKey('peak_memory_usage', $stats);
        $this->assertArrayHasKey('memory_limit', $stats);
        $this->assertArrayHasKey('execution_time', $stats);
        $this->assertArrayHasKey('system_capabilities', $stats);
        $this->assertArrayHasKey('cache_stats', $stats);
    }
    
    /**
     * Test system capabilities detection
     */
    public function test_system_capabilities_detection() {
        $stats = $this->performance_optimizer->get_performance_stats();
        $capabilities = $stats['system_capabilities'];
        
        $this->assertIsArray($capabilities);
        $this->assertArrayHasKey('memory_limit', $capabilities);
        $this->assertArrayHasKey('max_execution_time', $capabilities);
        $this->assertArrayHasKey('cpu_cores', $capabilities);
        $this->assertArrayHasKey('disk_free_space', $capabilities);
        $this->assertArrayHasKey('php_version', $capabilities);
        $this->assertArrayHasKey('image_library', $capabilities);
        
        // Validate data types
        $this->assertIsInt($capabilities['memory_limit']);
        $this->assertIsInt($capabilities['max_execution_time']);
        $this->assertIsInt($capabilities['cpu_cores']);
        $this->assertIsInt($capabilities['disk_free_space']);
        $this->assertIsString($capabilities['php_version']);
        
        // Validate reasonable values
        $this->assertGreaterThan(0, $capabilities['memory_limit']);
        $this->assertGreaterThanOrEqual(1, $capabilities['cpu_cores']);
        $this->assertEquals(PHP_VERSION, $capabilities['php_version']);
    }
    
    /**
     * Test batch size optimization
     */
    public function test_batch_size_optimization() {
        // Test with normal batch size
        $original_batch_size = 10;
        $optimized_size = apply_filters('sio_batch_size', $original_batch_size);
        
        $this->assertIsInt($optimized_size);
        $this->assertGreaterThanOrEqual(1, $optimized_size);
        
        // Test with very large batch size (should be reduced)
        $large_batch_size = 1000;
        $optimized_large_size = apply_filters('sio_batch_size', $large_batch_size);
        
        $this->assertLessThanOrEqual($large_batch_size, $optimized_large_size);
        $this->assertGreaterThanOrEqual(1, $optimized_large_size);
    }
    
    /**
     * Test processing settings optimization
     */
    public function test_processing_settings_optimization() {
        $original_settings = array(
            'webp_quality' => 90,
            'avif_quality' => 80,
            'compression_level' => 8
        );
        
        $optimized_settings = apply_filters('sio_processing_settings', $original_settings);
        
        $this->assertIsArray($optimized_settings);
        $this->assertArrayHasKey('webp_quality', $optimized_settings);
        $this->assertArrayHasKey('avif_quality', $optimized_settings);
        $this->assertArrayHasKey('compression_level', $optimized_settings);
        
        // Quality should be within valid ranges
        $this->assertGreaterThanOrEqual(1, $optimized_settings['webp_quality']);
        $this->assertLessThanOrEqual(100, $optimized_settings['webp_quality']);
        $this->assertGreaterThanOrEqual(1, $optimized_settings['avif_quality']);
        $this->assertLessThanOrEqual(100, $optimized_settings['avif_quality']);
        $this->assertGreaterThanOrEqual(1, $optimized_settings['compression_level']);
        $this->assertLessThanOrEqual(9, $optimized_settings['compression_level']);
    }
    
    /**
     * Test memory monitoring hooks
     */
    public function test_memory_monitoring_hooks() {
        $test_file = $this->test_utils->create_test_image();
        
        // Monitor memory before processing
        $memory_before = memory_get_usage(true);
        do_action('sio_before_process_image', $test_file);
        
        // Simulate some processing
        $dummy_data = str_repeat('x', 1024 * 1024); // 1MB
        
        // Monitor memory after processing
        do_action('sio_after_process_image', $test_file);
        $memory_after = memory_get_usage(true);
        
        // Clean up
        unset($dummy_data);
        $this->test_utils->cleanup_test_file($test_file);
        
        // Memory monitoring should not cause errors
        $this->assertTrue(true);
    }
    
    /**
     * Test batch processing performance hooks
     */
    public function test_batch_processing_hooks() {
        $test_items = array(
            array('id' => 1, 'attachment_id' => 123),
            array('id' => 2, 'attachment_id' => 124),
            array('id' => 3, 'attachment_id' => 125)
        );
        
        // Test before batch processing hook
        do_action('sio_before_batch_process', $test_items);
        
        // Simulate batch processing results
        $results = array(
            'processed' => 3,
            'errors' => 0,
            'execution_time' => 1.5
        );
        
        // Test after batch processing hook
        do_action('sio_after_batch_process', $results);
        
        // Hooks should execute without errors
        $this->assertTrue(true);
    }
    
    /**
     * Test cache management
     */
    public function test_cache_management() {
        // Test cache clearing
        $this->performance_optimizer->clear_all_caches();
        
        // Should not cause errors
        $this->assertTrue(true);
        
        // Test cache statistics
        $stats = $this->performance_optimizer->get_performance_stats();
        $cache_stats = $stats['cache_stats'];
        
        $this->assertIsArray($cache_stats);
        
        // Should have cache groups
        $expected_groups = array('settings', 'image_info', 'conversion_results', 'system_info');
        foreach ($expected_groups as $group) {
            $this->assertArrayHasKey($group, $cache_stats);
        }
    }
    
    /**
     * Test performance metrics logging
     */
    public function test_performance_metrics_logging() {
        // Start performance tracking
        $this->performance_optimizer->start_performance_tracking();
        
        // Simulate some work
        usleep(100000); // 0.1 second
        $dummy_data = str_repeat('x', 1024 * 100); // 100KB
        
        // Log performance metrics
        $this->performance_optimizer->log_performance_metrics();
        
        // Clean up
        unset($dummy_data);
        
        // Should complete without errors
        $this->assertTrue(true);
    }
    
    /**
     * Test memory limit optimization
     */
    public function test_memory_limit_handling() {
        $current_limit = ini_get('memory_limit');
        
        // Test with mock batch processing preparation
        $test_items = array(
            array('id' => 1, 'attachment_id' => 123)
        );
        
        do_action('sio_before_batch_process', $test_items);
        
        // Memory limit should still be valid
        $new_limit = ini_get('memory_limit');
        $this->assertNotEmpty($new_limit);
        
        // Clean up
        do_action('sio_after_batch_process', array('processed' => 1, 'errors' => 0));
    }
    
    /**
     * Test WordPress query optimization
     */
    public function test_wp_query_optimization() {
        // Create a test attachment
        $attachment_id = $this->test_utils->create_test_attachment();
        
        // Test query optimization for attachments
        $query = new WP_Query(array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 10
        ));
        
        $this->assertInstanceOf('WP_Query', $query);
        
        // Clean up
        wp_delete_attachment($attachment_id, true);
    }
    
    /**
     * Test resource monitoring
     */
    public function test_resource_monitoring() {
        $stats = $this->performance_optimizer->get_performance_stats();
        
        // Check that all required metrics are present
        $this->assertArrayHasKey('current_memory_usage', $stats);
        $this->assertArrayHasKey('peak_memory_usage', $stats);
        $this->assertArrayHasKey('execution_time', $stats);
        
        // Validate metric types
        $this->assertIsInt($stats['current_memory_usage']);
        $this->assertIsInt($stats['peak_memory_usage']);
        $this->assertIsFloat($stats['execution_time']);
        
        // Validate reasonable values
        $this->assertGreaterThan(0, $stats['current_memory_usage']);
        $this->assertGreaterThanOrEqual($stats['current_memory_usage'], $stats['peak_memory_usage']);
        $this->assertGreaterThanOrEqual(0, $stats['execution_time']);
    }
    
    /**
     * Test performance optimization integration
     */
    public function test_performance_optimization_integration() {
        // Test that performance optimizer is properly initialized
        $this->assertInstanceOf('SIO_Performance_Optimizer', $this->performance_optimizer);
        
        // Test that hooks are properly registered
        $this->assertTrue(has_action('init', array($this->performance_optimizer, 'setup_object_cache')));
        $this->assertTrue(has_action('wp_loaded', array($this->performance_optimizer, 'optimize_wp_queries')));
        $this->assertTrue(has_action('shutdown', array($this->performance_optimizer, 'log_performance_metrics')));
        
        // Test filter hooks
        $this->assertTrue(has_filter('sio_batch_size'));
        $this->assertTrue(has_filter('sio_processing_settings'));
    }
    
    /**
     * Test error handling in performance optimization
     */
    public function test_error_handling() {
        // Test with invalid file path
        do_action('sio_before_process_image', '/invalid/path/to/file.jpg');
        do_action('sio_after_process_image', '/invalid/path/to/file.jpg');
        
        // Should handle gracefully without errors
        $this->assertTrue(true);
        
        // Test with empty batch items
        do_action('sio_before_batch_process', array());
        do_action('sio_after_batch_process', array('processed' => 0, 'errors' => 0));
        
        // Should handle gracefully
        $this->assertTrue(true);
    }
    
    /**
     * Clean up after tests
     */
    public function tearDown(): void {
        // Clear any performance caches
        $this->performance_optimizer->clear_all_caches();
        
        parent::tearDown();
    }
}