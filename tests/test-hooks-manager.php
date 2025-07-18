<?php
/**
 * Hooks Manager Tests
 *
 * @package SmartImageOptimizer
 */

class SIO_Hooks_Manager_Test extends WP_UnitTestCase {
    
    /**
     * Hooks manager instance
     *
     * @var SIO_Hooks_Manager
     */
    private $hooks_manager;
    
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
        $this->hooks_manager = SIO_Hooks_Manager::instance();
        $this->test_utils = new SIO_Test_Utilities();
    }
    
    /**
     * Test singleton pattern
     */
    public function test_singleton_pattern() {
        $instance1 = SIO_Hooks_Manager::instance();
        $instance2 = SIO_Hooks_Manager::instance();
        
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf('SIO_Hooks_Manager', $instance1);
    }
    
    /**
     * Test hooks registration
     */
    public function test_hooks_registration() {
        $registered_hooks = $this->hooks_manager->get_registered_hooks();
        
        $this->assertIsArray($registered_hooks);
        $this->assertNotEmpty($registered_hooks);
        
        // Test that core hooks are registered
        $this->assertArrayHasKey('sio_plugin_activated', $registered_hooks);
        $this->assertArrayHasKey('sio_before_process_image', $registered_hooks);
        $this->assertArrayHasKey('sio_batch_size', $registered_hooks);
        $this->assertArrayHasKey('sio_settings_loaded', $registered_hooks);
    }
    
    /**
     * Test hook documentation structure
     */
    public function test_hook_documentation_structure() {
        $registered_hooks = $this->hooks_manager->get_registered_hooks();
        
        foreach ($registered_hooks as $hook_name => $hook_data) {
            $this->assertIsString($hook_name);
            $this->assertIsArray($hook_data);
            
            // Check required fields
            $this->assertArrayHasKey('type', $hook_data);
            $this->assertArrayHasKey('description', $hook_data);
            $this->assertArrayHasKey('parameters', $hook_data);
            $this->assertArrayHasKey('registered_at', $hook_data);
            
            // Validate types
            $this->assertContains($hook_data['type'], array('action', 'filter'));
            $this->assertIsString($hook_data['description']);
            $this->assertIsArray($hook_data['parameters']);
            $this->assertIsString($hook_data['registered_at']);
        }
    }
    
    /**
     * Test hooks by type filtering
     */
    public function test_get_hooks_by_type() {
        $action_hooks = $this->hooks_manager->get_hooks_by_type('action');
        $filter_hooks = $this->hooks_manager->get_hooks_by_type('filter');
        
        $this->assertIsArray($action_hooks);
        $this->assertIsArray($filter_hooks);
        $this->assertNotEmpty($action_hooks);
        $this->assertNotEmpty($filter_hooks);
        
        // Verify all returned hooks are of correct type
        foreach ($action_hooks as $hook_data) {
            $this->assertEquals('action', $hook_data['type']);
        }
        
        foreach ($filter_hooks as $hook_data) {
            $this->assertEquals('filter', $hook_data['type']);
        }
    }
    
    /**
     * Test individual hook documentation
     */
    public function test_get_hook_documentation() {
        $hook_doc = $this->hooks_manager->get_hook_documentation('sio_plugin_activated');
        
        $this->assertIsArray($hook_doc);
        $this->assertEquals('action', $hook_doc['type']);
        $this->assertStringContains('activated', $hook_doc['description']);
        
        // Test non-existent hook
        $non_existent = $this->hooks_manager->get_hook_documentation('non_existent_hook');
        $this->assertNull($non_existent);
    }
    
    /**
     * Test custom action firing
     */
    public function test_fire_action() {
        $test_value = false;
        
        // Add a test action
        add_action('sio_test_action', function() use (&$test_value) {
            $test_value = true;
        });
        
        // Fire the action through hooks manager
        $this->hooks_manager->fire_action('sio_test_action');
        
        $this->assertTrue($test_value);
        
        // Test with parameters
        $test_param = '';
        add_action('sio_test_action_with_param', function($param) use (&$test_param) {
            $test_param = $param;
        });
        
        $this->hooks_manager->fire_action('sio_test_action_with_param', 'test_value');
        $this->assertEquals('test_value', $test_param);
    }
    
    /**
     * Test custom filter application
     */
    public function test_apply_filter() {
        // Add a test filter
        add_filter('sio_test_filter', function($value) {
            return $value . '_filtered';
        });
        
        $result = $this->hooks_manager->apply_filter('sio_test_filter', 'original');
        $this->assertEquals('original_filtered', $result);
        
        // Test with multiple parameters
        add_filter('sio_test_filter_multi', function($value, $multiplier) {
            return $value * $multiplier;
        }, 10, 2);
        
        $result = $this->hooks_manager->apply_filter('sio_test_filter_multi', 5, 3);
        $this->assertEquals(15, $result);
    }
    
    /**
     * Test hook validation
     */
    public function test_validate_hook_usage() {
        // Test existing hook
        $this->assertTrue($this->hooks_manager->validate_hook_usage('sio_plugin_activated'));
        
        // Test non-existent hook
        $this->assertFalse($this->hooks_manager->validate_hook_usage('non_existent_hook'));
        
        // Test dynamic registration
        $this->hooks_manager->fire_action('sio_dynamic_test_hook');
        $this->assertTrue($this->hooks_manager->validate_hook_usage('sio_dynamic_test_hook'));
    }
    
    /**
     * Test hooks documentation generation
     */
    public function test_generate_hooks_documentation() {
        $documentation = $this->hooks_manager->generate_hooks_documentation();
        
        $this->assertIsString($documentation);
        $this->assertStringContains('Smart Image Optimizer - Hooks and Filters Reference', $documentation);
        $this->assertStringContains('## Core Hooks', $documentation);
        $this->assertStringContains('## Image Processing Hooks', $documentation);
        $this->assertStringContains('sio_plugin_activated', $documentation);
        $this->assertStringContains('sio_before_process_image', $documentation);
    }
    
    /**
     * Test JSON export
     */
    public function test_export_hooks_json() {
        $json = $this->hooks_manager->export_hooks_json();
        
        $this->assertIsString($json);
        $this->assertJson($json);
        
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('sio_plugin_activated', $decoded);
    }
    
    /**
     * Test core plugin hooks integration
     */
    public function test_core_plugin_hooks() {
        $hook_fired = false;
        
        // Test plugin activation hook
        add_action('sio_plugin_activated', function() use (&$hook_fired) {
            $hook_fired = true;
        });
        
        do_action('sio_plugin_activated');
        $this->assertTrue($hook_fired);
        
        // Reset for next test
        $hook_fired = false;
        
        // Test plugin deactivation hook
        add_action('sio_plugin_deactivated', function() use (&$hook_fired) {
            $hook_fired = true;
        });
        
        do_action('sio_plugin_deactivated');
        $this->assertTrue($hook_fired);
    }
    
    /**
     * Test image processing hooks
     */
    public function test_image_processing_hooks() {
        $before_fired = false;
        $after_fired = false;
        $test_file_path = '/test/path/image.jpg';
        
        // Add test hooks
        add_action('sio_before_process_image', function($file_path) use (&$before_fired, $test_file_path) {
            if ($file_path === $test_file_path) {
                $before_fired = true;
            }
        });
        
        add_action('sio_after_process_image', function($file_path) use (&$after_fired, $test_file_path) {
            if ($file_path === $test_file_path) {
                $after_fired = true;
            }
        });
        
        // Fire the hooks
        do_action('sio_before_process_image', $test_file_path);
        do_action('sio_after_process_image', $test_file_path);
        
        $this->assertTrue($before_fired);
        $this->assertTrue($after_fired);
    }
    
    /**
     * Test batch processing hooks
     */
    public function test_batch_processing_hooks() {
        $before_fired = false;
        $after_fired = false;
        
        $test_items = array(
            array('id' => 1, 'attachment_id' => 123),
            array('id' => 2, 'attachment_id' => 124)
        );
        
        $test_results = array(
            'processed' => 2,
            'errors' => 0,
            'execution_time' => 1.5
        );
        
        // Add test hooks
        add_action('sio_before_batch_process', function($items) use (&$before_fired, $test_items) {
            if (count($items) === count($test_items)) {
                $before_fired = true;
            }
        });
        
        add_action('sio_after_batch_process', function($results) use (&$after_fired, $test_results) {
            if ($results['processed'] === $test_results['processed']) {
                $after_fired = true;
            }
        });
        
        // Fire the hooks
        do_action('sio_before_batch_process', $test_items);
        do_action('sio_after_batch_process', $test_results);
        
        $this->assertTrue($before_fired);
        $this->assertTrue($after_fired);
    }
    
    /**
     * Test settings hooks
     */
    public function test_settings_hooks() {
        $settings_loaded = false;
        $settings_saved = false;
        
        $test_settings = array(
            'webp_quality' => 80,
            'avif_quality' => 70,
            'enable_webp' => true
        );
        
        // Add test hooks
        add_action('sio_settings_loaded', function($settings) use (&$settings_loaded) {
            $settings_loaded = true;
        });
        
        add_action('sio_settings_saved', function($settings, $old_settings) use (&$settings_saved) {
            $settings_saved = true;
        }, 10, 2);
        
        // Fire the hooks
        do_action('sio_settings_loaded', $test_settings);
        do_action('sio_settings_saved', $test_settings, array());
        
        $this->assertTrue($settings_loaded);
        $this->assertTrue($settings_saved);
    }
    
    /**
     * Test filter hooks functionality
     */
    public function test_filter_hooks() {
        // Test batch size filter
        add_filter('sio_batch_size', function($size) {
            return $size * 2;
        });
        
        $original_size = 10;
        $filtered_size = apply_filters('sio_batch_size', $original_size);
        $this->assertEquals(20, $filtered_size);
        
        // Test WebP quality filter
        add_filter('sio_webp_quality', function($quality, $file_path) {
            if (strpos($file_path, 'large') !== false) {
                return min($quality + 10, 100);
            }
            return $quality;
        }, 10, 2);
        
        $quality = apply_filters('sio_webp_quality', 80, '/path/to/large_image.jpg');
        $this->assertEquals(90, $quality);
        
        $quality = apply_filters('sio_webp_quality', 80, '/path/to/small_image.jpg');
        $this->assertEquals(80, $quality);
    }
    
    /**
     * Test performance hooks
     */
    public function test_performance_hooks() {
        $tracking_started = false;
        $metrics_logged = false;
        
        // Add test hooks
        add_action('sio_performance_tracking_started', function() use (&$tracking_started) {
            $tracking_started = true;
        });
        
        add_action('sio_performance_metrics_logged', function($metrics) use (&$metrics_logged) {
            $metrics_logged = true;
        });
        
        // Fire the hooks
        do_action('sio_performance_tracking_started');
        do_action('sio_performance_metrics_logged', array('execution_time' => 1.5));
        
        $this->assertTrue($tracking_started);
        $this->assertTrue($metrics_logged);
    }
    
    /**
     * Test security hooks
     */
    public function test_security_hooks() {
        $check_passed = false;
        $check_failed = false;
        
        // Add test hooks
        add_action('sio_security_check_passed', function($check_type, $context) use (&$check_passed) {
            if ($check_type === 'file_validation') {
                $check_passed = true;
            }
        }, 10, 2);
        
        add_action('sio_security_check_failed', function($check_type, $reason, $context) use (&$check_failed) {
            if ($check_type === 'file_validation') {
                $check_failed = true;
            }
        }, 10, 3);
        
        // Fire the hooks
        do_action('sio_security_check_passed', 'file_validation', array());
        do_action('sio_security_check_failed', 'file_validation', 'Invalid file type', array());
        
        $this->assertTrue($check_passed);
        $this->assertTrue($check_failed);
    }
    
    /**
     * Test hook categorization
     */
    public function test_hook_categorization() {
        $documentation = $this->hooks_manager->generate_hooks_documentation();
        
        // Check that different categories are present
        $this->assertStringContains('## Core Hooks', $documentation);
        $this->assertStringContains('## Image Processing Hooks', $documentation);
        $this->assertStringContains('## Batch Processing Hooks', $documentation);
        $this->assertStringContains('## Settings Hooks', $documentation);
        $this->assertStringContains('## Admin Interface Hooks', $documentation);
        $this->assertStringContains('## Server Configuration Hooks', $documentation);
        $this->assertStringContains('## Performance Hooks', $documentation);
        $this->assertStringContains('## Security Hooks', $documentation);
        $this->assertStringContains('## Utility Hooks', $documentation);
    }
    
    /**
     * Test error handling in hooks
     */
    public function test_error_handling() {
        // Test firing non-existent action (should not cause errors)
        $this->hooks_manager->fire_action('sio_non_existent_action');
        $this->assertTrue(true); // If we get here, no fatal error occurred
        
        // Test applying non-existent filter (should return original value)
        $result = $this->hooks_manager->apply_filter('sio_non_existent_filter', 'test_value');
        $this->assertEquals('test_value', $result);
    }
    
    /**
     * Clean up after tests
     */
    public function tearDown(): void {
        // Remove any test hooks that were added
        remove_all_actions('sio_test_action');
        remove_all_actions('sio_test_action_with_param');
        remove_all_filters('sio_test_filter');
        remove_all_filters('sio_test_filter_multi');
        
        parent::tearDown();
    }
}