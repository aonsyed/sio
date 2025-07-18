<?php
/**
 * Settings Manager Tests
 *
 * @package SmartImageOptimizer
 */

/**
 * Settings Manager Test Class
 */
class SIO_Settings_Manager_Test extends WP_UnitTestCase {
    
    /**
     * Settings manager instance
     *
     * @var SIO_Settings_Manager
     */
    private $settings_manager;
    
    /**
     * Set up test
     */
    public function setUp(): void {
        parent::setUp();
        $this->settings_manager = SIO_Settings_Manager::instance();
        
        // Clear any existing settings
        delete_option('sio_settings');
        $this->settings_manager->clear_cache();
    }
    
    /**
     * Tear down test
     */
    public function tearDown(): void {
        delete_option('sio_settings');
        $this->settings_manager->clear_cache();
        parent::tearDown();
    }
    
    /**
     * Test singleton instance
     */
    public function test_singleton_instance() {
        $instance1 = SIO_Settings_Manager::instance();
        $instance2 = SIO_Settings_Manager::instance();
        
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf('SIO_Settings_Manager', $instance1);
    }
    
    /**
     * Test default settings
     */
    public function test_default_settings() {
        $settings = $this->settings_manager->get_settings();
        
        // Test core defaults
        $this->assertTrue($settings['auto_process']);
        $this->assertFalse($settings['batch_mode']);
        $this->assertEquals(80, $settings['webp_quality']);
        $this->assertEquals(70, $settings['avif_quality']);
        $this->assertTrue($settings['enable_webp']);
        $this->assertTrue($settings['enable_avif']);
        $this->assertFalse($settings['enable_resize']);
        $this->assertEquals(1920, $settings['max_width']);
        $this->assertEquals(1080, $settings['max_height']);
        
        // Test server configuration defaults
        $this->assertFalse($settings['enable_auto_serve']);
        $this->assertFalse($settings['auto_htaccess']);
        $this->assertTrue($settings['fallback_conversion']);
        $this->assertEquals(86400, $settings['cache_duration']);
    }
    
    /**
     * Test get specific setting
     */
    public function test_get_setting() {
        $webp_quality = $this->settings_manager->get_setting('webp_quality');
        $this->assertEquals(80, $webp_quality);
        
        $non_existent = $this->settings_manager->get_setting('non_existent', 'default_value');
        $this->assertEquals('default_value', $non_existent);
    }
    
    /**
     * Test update settings
     */
    public function test_update_settings() {
        $new_settings = array(
            'webp_quality' => 90,
            'enable_avif' => false,
            'batch_size' => 20
        );
        
        $result = $this->settings_manager->update_settings($new_settings);
        $this->assertTrue($result);
        
        $updated_settings = $this->settings_manager->get_settings();
        $this->assertEquals(90, $updated_settings['webp_quality']);
        $this->assertFalse($updated_settings['enable_avif']);
        $this->assertEquals(20, $updated_settings['batch_size']);
        
        // Ensure other settings remain unchanged
        $this->assertTrue($updated_settings['enable_webp']);
    }
    
    /**
     * Test update single setting
     */
    public function test_update_single_setting() {
        $result = $this->settings_manager->update_setting('webp_quality', 95);
        $this->assertTrue($result);
        
        $quality = $this->settings_manager->get_setting('webp_quality');
        $this->assertEquals(95, $quality);
    }
    
    /**
     * Test settings validation
     */
    public function test_settings_validation() {
        // Test valid quality
        $valid_settings = array('webp_quality' => 85);
        $result = $this->settings_manager->update_settings($valid_settings);
        $this->assertTrue($result);
        
        // Test invalid quality (too high)
        $invalid_settings = array('webp_quality' => 150);
        $result = $this->settings_manager->update_settings($invalid_settings);
        $this->assertInstanceOf('WP_Error', $result);
        
        // Test invalid quality (too low)
        $invalid_settings = array('webp_quality' => 0);
        $result = $this->settings_manager->update_settings($invalid_settings);
        $this->assertInstanceOf('WP_Error', $result);
    }
    
    /**
     * Test dimension validation
     */
    public function test_dimension_validation() {
        // Test valid dimensions
        $valid_settings = array(
            'max_width' => 1920,
            'max_height' => 1080
        );
        $result = $this->settings_manager->update_settings($valid_settings);
        $this->assertTrue($result);
        
        // Test invalid dimensions (too small)
        $invalid_settings = array('max_width' => 50);
        $result = $this->settings_manager->update_settings($invalid_settings);
        $this->assertInstanceOf('WP_Error', $result);
        
        // Test invalid dimensions (too large)
        $invalid_settings = array('max_height' => 10000);
        $result = $this->settings_manager->update_settings($invalid_settings);
        $this->assertInstanceOf('WP_Error', $result);
    }
    
    /**
     * Test compression level validation
     */
    public function test_compression_level_validation() {
        // Test valid compression level
        $valid_settings = array('compression_level' => 6);
        $result = $this->settings_manager->update_settings($valid_settings);
        $this->assertTrue($result);
        
        // Test invalid compression level (too high)
        $invalid_settings = array('compression_level' => 15);
        $result = $this->settings_manager->update_settings($invalid_settings);
        $this->assertInstanceOf('WP_Error', $result);
        
        // Test invalid compression level (negative)
        $invalid_settings = array('compression_level' => -1);
        $result = $this->settings_manager->update_settings($invalid_settings);
        $this->assertInstanceOf('WP_Error', $result);
    }
    
    /**
     * Test cache duration validation
     */
    public function test_cache_duration_validation() {
        // Test valid cache duration
        $valid_settings = array('cache_duration' => 3600);
        $result = $this->settings_manager->update_settings($valid_settings);
        $this->assertTrue($result);
        
        // Test invalid cache duration (too short)
        $invalid_settings = array('cache_duration' => 100);
        $result = $this->settings_manager->update_settings($invalid_settings);
        $this->assertInstanceOf('WP_Error', $result);
        
        // Test invalid cache duration (too long)
        $invalid_settings = array('cache_duration' => 1000000);
        $result = $this->settings_manager->update_settings($invalid_settings);
        $this->assertInstanceOf('WP_Error', $result);
    }
    
    /**
     * Test boolean settings validation
     */
    public function test_boolean_settings_validation() {
        $boolean_settings = array(
            'auto_process' => '1',
            'enable_webp' => 'true',
            'enable_avif' => 1,
            'enable_auto_serve' => 'false',
            'auto_htaccess' => 0
        );
        
        $result = $this->settings_manager->update_settings($boolean_settings);
        $this->assertTrue($result);
        
        $settings = $this->settings_manager->get_settings();
        $this->assertTrue($settings['auto_process']);
        $this->assertTrue($settings['enable_webp']);
        $this->assertTrue($settings['enable_avif']);
        $this->assertFalse($settings['enable_auto_serve']);
        $this->assertFalse($settings['auto_htaccess']);
    }
    
    /**
     * Test reset settings
     */
    public function test_reset_settings() {
        // Update some settings
        $this->settings_manager->update_settings(array(
            'webp_quality' => 95,
            'enable_avif' => false
        ));
        
        // Reset settings
        $result = $this->settings_manager->reset_settings();
        $this->assertTrue($result);
        
        // Check that settings are back to defaults
        $settings = $this->settings_manager->get_settings();
        $this->assertEquals(80, $settings['webp_quality']);
        $this->assertTrue($settings['enable_avif']);
    }
    
    /**
     * Test settings schema
     */
    public function test_settings_schema() {
        $schema = $this->settings_manager->get_settings_schema();
        
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('webp_quality', $schema);
        $this->assertArrayHasKey('enable_auto_serve', $schema);
        $this->assertArrayHasKey('cache_duration', $schema);
        
        // Test schema structure
        $webp_schema = $schema['webp_quality'];
        $this->assertEquals('integer', $webp_schema['type']);
        $this->assertEquals(1, $webp_schema['minimum']);
        $this->assertEquals(100, $webp_schema['maximum']);
        $this->assertEquals(80, $webp_schema['default']);
    }
    
    /**
     * Test cache functionality
     */
    public function test_cache_functionality() {
        // Get settings (should cache them)
        $settings1 = $this->settings_manager->get_settings();
        
        // Directly update database without using settings manager
        update_option('sio_settings', array('webp_quality' => 95));
        
        // Get settings again (should return cached version)
        $settings2 = $this->settings_manager->get_settings();
        $this->assertEquals($settings1['webp_quality'], $settings2['webp_quality']);
        
        // Clear cache and get settings again
        $this->settings_manager->clear_cache();
        $settings3 = $this->settings_manager->get_settings();
        $this->assertEquals(95, $settings3['webp_quality']);
    }
}