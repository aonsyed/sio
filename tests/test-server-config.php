<?php
/**
 * Server Configuration Tests
 *
 * @package SmartImageOptimizer
 */

/**
 * Server Configuration Test Class
 */
class SIO_Server_Config_Test extends WP_UnitTestCase {
    
    /**
     * Server config instance
     *
     * @var SIO_Server_Config
     */
    private $server_config;
    
    /**
     * Set up test
     */
    public function setUp(): void {
        parent::setUp();
        $this->server_config = SIO_Server_Config::instance();
        
        // Reset HTTP Accept header
        SIO_Test_Utilities::reset_http_accept();
    }
    
    /**
     * Tear down test
     */
    public function tearDown(): void {
        SIO_Test_Utilities::reset_http_accept();
        parent::tearDown();
    }
    
    /**
     * Test singleton instance
     */
    public function test_singleton_instance() {
        $instance1 = SIO_Server_Config::instance();
        $instance2 = SIO_Server_Config::instance();
        
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf('SIO_Server_Config', $instance1);
    }
    
    /**
     * Test browser WebP support detection
     */
    public function test_browser_webp_support() {
        // Test WebP support
        SIO_Test_Utilities::mock_http_accept('text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8');
        $this->assertTrue($this->server_config->browser_supports_webp());
        
        // Test no WebP support
        SIO_Test_Utilities::mock_http_accept('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8');
        $this->assertFalse($this->server_config->browser_supports_webp());
        
        // Test no Accept header
        SIO_Test_Utilities::reset_http_accept();
        $this->assertFalse($this->server_config->browser_supports_webp());
    }
    
    /**
     * Test browser AVIF support detection
     */
    public function test_browser_avif_support() {
        // Test AVIF support
        SIO_Test_Utilities::mock_http_accept('text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8');
        $this->assertTrue($this->server_config->browser_supports_avif());
        
        // Test no AVIF support
        SIO_Test_Utilities::mock_http_accept('text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8');
        $this->assertFalse($this->server_config->browser_supports_avif());
        
        // Test no Accept header
        SIO_Test_Utilities::reset_http_accept();
        $this->assertFalse($this->server_config->browser_supports_avif());
    }
    
    /**
     * Test optimal format selection
     */
    public function test_get_optimal_format() {
        // Test AVIF preference
        SIO_Test_Utilities::mock_http_accept('text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8');
        $this->assertEquals('avif', $this->server_config->get_optimal_format());
        
        // Test WebP fallback
        SIO_Test_Utilities::mock_http_accept('text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8');
        $this->assertEquals('webp', $this->server_config->get_optimal_format());
        
        // Test original format fallback
        SIO_Test_Utilities::mock_http_accept('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8');
        $this->assertEquals('original', $this->server_config->get_optimal_format());
    }
    
    /**
     * Test .htaccess rules generation
     */
    public function test_htaccess_rules_generation() {
        $rules = $this->server_config->generate_htaccess_rules();
        
        $this->assertIsString($rules);
        $this->assertStringContainsString('Smart Image Optimizer', $rules);
        $this->assertStringContainsString('RewriteEngine On', $rules);
        $this->assertStringContainsString('image/webp', $rules);
        $this->assertStringContainsString('image/avif', $rules);
        $this->assertStringContainsString('HTTP_ACCEPT', $rules);
    }
    
    /**
     * Test .htaccess rules structure
     */
    public function test_htaccess_rules_structure() {
        $rules = $this->server_config->generate_htaccess_rules();
        
        // Should contain proper RewriteCond and RewriteRule statements
        $this->assertStringContainsString('RewriteCond', $rules);
        $this->assertStringContainsString('RewriteRule', $rules);
        
        // Should handle both AVIF and WebP
        $this->assertStringContainsString('.avif', $rules);
        $this->assertStringContainsString('.webp', $rules);
        
        // Should include cache headers
        $this->assertStringContainsString('Cache-Control', $rules);
        $this->assertStringContainsString('Expires', $rules);
    }
    
    /**
     * Test Nginx configuration generation
     */
    public function test_nginx_config_generation() {
        $config = $this->server_config->get_nginx_config();
        
        $this->assertIsString($config);
        $this->assertStringContainsString('Smart Image Optimizer', $config);
        $this->assertStringContainsString('location ~', $config);
        $this->assertStringContainsString('image/webp', $config);
        $this->assertStringContainsString('image/avif', $config);
        $this->assertStringContainsString('$http_accept', $config);
    }
    
    /**
     * Test Nginx configuration structure
     */
    public function test_nginx_config_structure() {
        $config = $this->server_config->get_nginx_config();
        
        // Should contain proper location blocks
        $this->assertStringContainsString('location', $config);
        $this->assertStringContainsString('try_files', $config);
        
        // Should handle both AVIF and WebP
        $this->assertStringContainsString('avif', $config);
        $this->assertStringContainsString('webp', $config);
        
        // Should include cache headers
        $this->assertStringContainsString('expires', $config);
        $this->assertStringContainsString('add_header', $config);
    }
    
    /**
     * Test MIME type detection
     */
    public function test_mime_type_detection() {
        $this->assertEquals('image/webp', $this->server_config->get_mime_type('webp'));
        $this->assertEquals('image/avif', $this->server_config->get_mime_type('avif'));
        $this->assertEquals('image/jpeg', $this->server_config->get_mime_type('jpg'));
        $this->assertEquals('image/jpeg', $this->server_config->get_mime_type('jpeg'));
        $this->assertEquals('image/png', $this->server_config->get_mime_type('png'));
        $this->assertEquals('image/gif', $this->server_config->get_mime_type('gif'));
        
        // Test unknown format
        $this->assertEquals('application/octet-stream', $this->server_config->get_mime_type('unknown'));
    }
    
    /**
     * Test cache headers generation
     */
    public function test_cache_headers() {
        $headers = $this->server_config->get_cache_headers(3600);
        
        $this->assertIsArray($headers);
        $this->assertArrayHasKey('Cache-Control', $headers);
        $this->assertArrayHasKey('Expires', $headers);
        
        $this->assertStringContainsString('max-age=3600', $headers['Cache-Control']);
        $this->assertStringContainsString('public', $headers['Cache-Control']);
    }
    
    /**
     * Test image path conversion
     */
    public function test_image_path_conversion() {
        $original_path = '/wp-content/uploads/2024/01/image.jpg';
        
        $webp_path = $this->server_config->get_converted_path($original_path, 'webp');
        $this->assertEquals('/wp-content/uploads/2024/01/image.webp', $webp_path);
        
        $avif_path = $this->server_config->get_converted_path($original_path, 'avif');
        $this->assertEquals('/wp-content/uploads/2024/01/image.avif', $avif_path);
    }
    
    /**
     * Test file existence checking
     */
    public function test_converted_file_exists() {
        // Create test image
        $test_image = SIO_Test_Utilities::create_test_image('jpg', 100, 100);
        $this->assertNotFalse($test_image);
        
        // Test original file exists
        $this->assertTrue($this->server_config->converted_file_exists($test_image, 'original'));
        
        // Test converted files don't exist yet
        $this->assertFalse($this->server_config->converted_file_exists($test_image, 'webp'));
        $this->assertFalse($this->server_config->converted_file_exists($test_image, 'avif'));
        
        // Clean up
        unlink($test_image);
    }
    
    /**
     * Test WordPress rewrite rules
     */
    public function test_wordpress_rewrite_rules() {
        $rules = $this->server_config->get_rewrite_rules();
        
        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
        
        // Should contain rules for image conversion
        $found_image_rule = false;
        foreach ($rules as $pattern => $rewrite) {
            if (strpos($pattern, 'uploads') !== false && strpos($rewrite, 'sio_convert') !== false) {
                $found_image_rule = true;
                break;
            }
        }
        
        $this->assertTrue($found_image_rule);
    }
    
    /**
     * Test query variables
     */
    public function test_query_variables() {
        $query_vars = $this->server_config->get_query_vars();
        
        $this->assertIsArray($query_vars);
        $this->assertContains('sio_convert', $query_vars);
        $this->assertContains('sio_file', $query_vars);
    }
    
    /**
     * Test on-the-fly conversion handling
     */
    public function test_on_the_fly_conversion() {
        // Mock WordPress query
        global $wp_query;
        $wp_query = new WP_Query();
        
        // Set query variables
        set_query_var('sio_convert', 'webp');
        set_query_var('sio_file', 'test-image.jpg');
        
        // Test conversion handling
        $handled = $this->server_config->maybe_handle_image_request();
        
        // Should return true if handling the request
        $this->assertIsBool($handled);
    }
    
    /**
     * Test security validation
     */
    public function test_security_validation() {
        // Test valid image path
        $valid_path = '/wp-content/uploads/2024/01/image.jpg';
        $this->assertTrue($this->server_config->is_valid_image_path($valid_path));
        
        // Test invalid paths (directory traversal attempts)
        $invalid_paths = array(
            '../../../etc/passwd',
            '/wp-content/uploads/../../../etc/passwd',
            '..\\..\\..\\windows\\system32\\config\\sam',
            '/wp-content/uploads/2024/01/../../../etc/passwd'
        );
        
        foreach ($invalid_paths as $invalid_path) {
            $this->assertFalse($this->server_config->is_valid_image_path($invalid_path));
        }
    }
    
    /**
     * Test format validation
     */
    public function test_format_validation() {
        // Test valid formats
        $valid_formats = array('webp', 'avif', 'original');
        foreach ($valid_formats as $format) {
            $this->assertTrue($this->server_config->is_valid_format($format));
        }
        
        // Test invalid formats
        $invalid_formats = array('exe', 'php', 'js', 'html', '');
        foreach ($invalid_formats as $format) {
            $this->assertFalse($this->server_config->is_valid_format($format));
        }
    }
    
    /**
     * Test configuration status
     */
    public function test_configuration_status() {
        $status = $this->server_config->get_configuration_status();
        
        $this->assertIsArray($status);
        $this->assertArrayHasKey('server_type', $status);
        $this->assertArrayHasKey('htaccess_writable', $status);
        $this->assertArrayHasKey('rewrite_enabled', $status);
        
        $this->assertIsBool($status['htaccess_writable']);
        $this->assertIsBool($status['rewrite_enabled']);
    }
}