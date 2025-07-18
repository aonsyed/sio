# Installation Guide - Smart Image Optimizer

This guide provides detailed installation instructions for the Smart Image Optimizer WordPress plugin.

## System Requirements

### Minimum Requirements
- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher (or MariaDB 10.1+)
- **Memory**: 256MB PHP memory limit (512MB+ recommended)
- **Disk Space**: Sufficient space for converted images (typically 1.5x original size)

### Required PHP Extensions
At least one of the following image processing libraries:
- **ImageMagick** (recommended) - `php-imagick` extension
- **GD Library** - `php-gd` extension (usually included with PHP)

### Optional but Recommended
- **WP CLI** - For command-line operations
- **Cron jobs** - For background processing (WordPress Cron or system cron)

## Pre-Installation Checklist

### 1. Check PHP Version
```bash
php -v
```
Ensure you're running PHP 7.4 or higher.

### 2. Verify Image Processing Libraries
```bash
# Check for ImageMagick
php -m | grep imagick

# Check for GD
php -m | grep gd

# Or check both with detailed info
php -r "phpinfo();" | grep -E "(imagick|gd)"
```

### 3. Check WordPress Version
In your WordPress admin, go to **Dashboard > Updates** to verify your WordPress version.

### 4. Verify File Permissions
Ensure your WordPress uploads directory is writable:
```bash
ls -la wp-content/uploads/
```
The directory should be writable by the web server user.

## Installation Methods

### Method 1: Manual Installation

1. **Download the Plugin**
   - Download the latest release from the repository
   - Extract the ZIP file to get the `smart-image-optimizer` folder

2. **Upload to WordPress**
   ```bash
   # Via FTP/SFTP
   scp -r smart-image-optimizer/ user@yoursite.com:/path/to/wp-content/plugins/
   
   # Or via file manager in hosting control panel
   ```

3. **Set Permissions**
   ```bash
   # Set appropriate permissions
   chmod -R 755 wp-content/plugins/smart-image-optimizer/
   ```

4. **Activate the Plugin**
   - Log in to your WordPress admin
   - Go to **Plugins > Installed Plugins**
   - Find "Smart Image Optimizer" and click **Activate**

### Method 2: WP CLI Installation

If you have WP CLI installed:

```bash
# Navigate to your WordPress directory
cd /path/to/your/wordpress/

# Install and activate the plugin
wp plugin install smart-image-optimizer --activate

# Verify installation
wp plugin list | grep smart-image-optimizer
```

### Method 3: WordPress Admin Upload

1. **Prepare the ZIP File**
   - Ensure you have the plugin as a ZIP file
   - The ZIP should contain the `smart-image-optimizer` folder

2. **Upload via Admin**
   - Go to **Plugins > Add New**
   - Click **Upload Plugin**
   - Choose your ZIP file and click **Install Now**
   - Click **Activate Plugin**

## Post-Installation Setup

### 1. Initial Configuration

After activation, you'll see a notice to configure the plugin:

1. Go to **Image Optimizer** in your WordPress admin menu
2. Click on **Settings** to configure basic options
3. Review the **System Info** page to ensure everything is working

### 2. Verify System Status

Check the **System Info** page for:
- ✅ Available image processing library
- ✅ WebP support status
- ✅ AVIF support status
- ✅ Memory and execution time limits

### 3. Configure Basic Settings

Recommended initial settings:
```
General:
- Auto Process: Disabled (until you test)
- Batch Mode: Enabled
- Enable Logging: Enabled

Formats:
- Enable WebP: Enabled
- WebP Quality: 85
- Enable AVIF: Disabled (test WebP first)

Processing:
- Enable Resize: As needed
- Batch Size: 5 (start small)
```

### 4. Test with Sample Images

1. Upload a test image to your media library
2. Go to **Image Optimizer > Batch Processing**
3. Click **Add All Images** then **Start Batch Processing**
4. Monitor the progress and check results

## Troubleshooting Installation Issues

### Common Installation Problems

#### 1. Plugin Activation Fails
**Error**: "Plugin could not be activated because it triggered a fatal error"

**Solutions**:
- Check PHP error logs: `tail -f /path/to/php/error.log`
- Verify PHP version compatibility
- Ensure all required files are uploaded
- Check file permissions

#### 2. Missing Image Processing Library
**Error**: "No image processing library available"

**Solutions**:
```bash
# Install ImageMagick (Ubuntu/Debian)
sudo apt-get install php-imagick

# Install ImageMagick (CentOS/RHEL)
sudo yum install php-imagick

# Restart web server
sudo service apache2 restart
# or
sudo service nginx restart
```

#### 3. Memory Limit Issues
**Error**: "Fatal error: Allowed memory size exhausted"

**Solutions**:
- Increase PHP memory limit in `php.ini`:
  ```ini
  memory_limit = 512M
  ```
- Or in `wp-config.php`:
  ```php
  ini_set('memory_limit', '512M');
  ```
- Or via `.htaccess`:
  ```apache
  php_value memory_limit 512M
  ```

#### 4. File Permission Issues
**Error**: "Unable to create directory" or "Permission denied"

**Solutions**:
```bash
# Fix WordPress uploads permissions
sudo chown -R www-data:www-data wp-content/uploads/
sudo chmod -R 755 wp-content/uploads/

# Fix plugin permissions
sudo chown -R www-data:www-data wp-content/plugins/smart-image-optimizer/
sudo chmod -R 755 wp-content/plugins/smart-image-optimizer/
```

### Debugging Installation

#### Enable WordPress Debug Mode
Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

#### Check Error Logs
```bash
# WordPress debug log
tail -f wp-content/debug.log

# PHP error log (location varies)
tail -f /var/log/php/error.log
```

#### Use WP CLI for Diagnostics
```bash
# Check plugin status
wp plugin status smart-image-optimizer

# Check system info
wp sio info

# Test image processing
wp sio convert /path/to/test-image.jpg --dry-run
```

## Server-Specific Installation Notes

### Shared Hosting

- Contact your hosting provider if you need PHP extensions installed
- Some shared hosts may have restrictions on image processing
- Consider using smaller batch sizes to avoid timeout issues

### VPS/Dedicated Servers

- You have full control over PHP extensions and configuration
- Consider setting up system cron for better background processing
- Monitor server resources during batch processing

### WordPress Multisite

- Install the plugin network-wide or per-site as needed
- Each site will have its own settings and processing queue
- Monitor resource usage across all sites

### Docker/Containerized Environments

Ensure your Docker image includes the necessary PHP extensions:
```dockerfile
# Add to your Dockerfile
RUN apt-get update && apt-get install -y \
    libmagickwand-dev \
    && docker-php-ext-install gd \
    && pecl install imagick \
    && docker-php-ext-enable imagick
```

## Performance Optimization

### Server Configuration

#### PHP Settings
```ini
# Recommended php.ini settings
memory_limit = 512M
max_execution_time = 300
upload_max_filesize = 64M
post_max_size = 64M
max_input_vars = 3000
```

#### MySQL/MariaDB Settings
```ini
# Recommended database settings
innodb_buffer_pool_size = 256M
max_connections = 200
query_cache_size = 64M
```

### WordPress Configuration

#### wp-config.php Optimizations
```php
// Increase WordPress memory limit
define('WP_MEMORY_LIMIT', '512M');

// Enable object caching if available
define('WP_CACHE', true);

// Optimize database queries
define('WP_DEBUG_DISPLAY', false);
```

## Security Considerations

### File Permissions
```bash
# Secure file permissions
find wp-content/plugins/smart-image-optimizer/ -type f -exec chmod 644 {} \;
find wp-content/plugins/smart-image-optimizer/ -type d -exec chmod 755 {} \;
```

### Web Server Configuration

#### Apache (.htaccess)
```apache
# Prevent direct access to plugin files
<Files "*.php">
    Order Deny,Allow
    Deny from all
</Files>

# Allow only index.php
<Files "index.php">
    Order Allow,Deny
    Allow from all
</Files>
```

#### Nginx
```nginx
# Block direct access to plugin files
location ~* /wp-content/plugins/smart-image-optimizer/.*\.php$ {
    deny all;
}
```

## Uninstallation

### Complete Removal

1. **Deactivate the Plugin**
   - Go to **Plugins > Installed Plugins**
   - Click **Deactivate** for Smart Image Optimizer

2. **Delete Plugin Data** (Optional)
   - The plugin will automatically clean up its data when deleted
   - This includes database tables, options, and log files

3. **Remove Plugin Files**
   - Click **Delete** in the plugins list
   - Or manually remove the plugin directory:
     ```bash
     rm -rf wp-content/plugins/smart-image-optimizer/
     ```

### Preserve Converted Images

The plugin's uninstall process will:
- ✅ Keep all converted WebP/AVIF images
- ✅ Preserve your media library
- ❌ Remove plugin settings and logs
- ❌ Remove processing queue data

## Getting Help

If you encounter issues during installation:

1. **Check System Requirements**: Ensure your server meets all requirements
2. **Review Error Logs**: Check WordPress and PHP error logs
3. **Test Basic Functionality**: Use the System Info page to diagnose issues
4. **Use WP CLI**: Run diagnostic commands if available
5. **Check Documentation**: Review this guide and the main README
6. **Contact Support**: Provide system info and error logs when seeking help

## Next Steps

After successful installation:

1. **Configure Settings**: Set up the plugin according to your needs
2. **Test Processing**: Start with a few test images
3. **Monitor Performance**: Watch server resources during processing
4. **Set Up Automation**: Configure automatic processing if desired
5. **Regular Maintenance**: Monitor logs and clean up as needed

Congratulations! Your Smart Image Optimizer plugin is now installed and ready to use.