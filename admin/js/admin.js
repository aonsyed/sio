/**
 * Smart Image Optimizer Admin JavaScript
 *
 * @package SmartImageOptimizer
 */

(function($) {
    'use strict';

    // Global variables
    var sioAdmin = {
        processing: false,
        refreshInterval: null,
        currentTab: 'general'
    };

    /**
     * Initialize admin functionality
     */
    function init() {
        // Settings tabs
        initSettingsTabs();
        
        // Dashboard functionality
        initDashboard();
        
        // Batch processing
        initBatchProcessing();
        
        // Monitor functionality
        initMonitor();
        
        // System info
        initSystemInfo();
        
        // Server configuration
        initServerConfig();
        
        // General AJAX handlers
        initAjaxHandlers();
    }

    /**
     * Initialize settings tabs
     */
    function initSettingsTabs() {
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            var target = $(this).attr('href');
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Show/hide content
            $('.tab-content').removeClass('active');
            $(target).addClass('active');
            
            sioAdmin.currentTab = target.replace('#', '');
        });
        
        // Reset settings button
        $('#sio-reset-settings').on('click', function(e) {
            e.preventDefault();
            
            if (confirm(window.sioAdmin.strings.confirm_reset)) {
                resetSettings();
            }
        });
        
        // Save settings via AJAX
        $('#sio-settings-form').on('submit', function(e) {
            e.preventDefault();
            saveSettings();
        });
    }

    /**
     * Initialize dashboard functionality
     */
    function initDashboard() {
        // Refresh statistics button
        $('#sio-refresh-stats').on('click', function(e) {
            e.preventDefault();
            refreshStatistics();
        });
        
        // Auto-refresh processing status if active
        if ($('.sio-processing-status').length > 0) {
            startProcessingStatusRefresh();
        }
    }

    /**
     * Initialize batch processing functionality
     */
    function initBatchProcessing() {
        // Start batch processing
        $('#sio-start-batch').on('click', function(e) {
            e.preventDefault();
            startBatchProcessing();
        });
        
        // Stop batch processing
        $('#sio-stop-batch').on('click', function(e) {
            e.preventDefault();
            stopBatchProcessing();
        });
        
        // Clear queue
        $('#sio-clear-queue').on('click', function(e) {
            e.preventDefault();
            
            if (confirm(window.sioAdmin.strings.confirm_clear_queue)) {
                clearQueue();
            }
        });
        
        // Add all images to queue
        $('#sio-add-all-images').on('click', function(e) {
            e.preventDefault();
            addAllImages();
        });
        
        // Refresh queue
        $('#sio-refresh-queue').on('click', function(e) {
            e.preventDefault();
            refreshQueue();
        });
        
        // Status filter
        $('#sio-status-filter').on('change', function() {
            refreshQueue();
        });
        
        // Load initial queue
        if ($('#sio-queue-list').length > 0) {
            refreshQueue();
        }
        
        // Auto-refresh if processing
        if ($('.sio-progress-section').length > 0) {
            startBatchStatusRefresh();
        }
    }

    /**
     * Initialize monitor functionality
     */
    function initMonitor() {
        // Filter logs
        $('#sio-filter-logs').on('click', function(e) {
            e.preventDefault();
            filterLogs();
        });
        
        // Clear log filters
        $('#sio-clear-log-filters').on('click', function(e) {
            e.preventDefault();
            clearLogFilters();
        });
        
        // Export logs
        $('#sio-export-logs').on('click', function(e) {
            e.preventDefault();
            exportLogs();
        });
        
        // Clear logs
        $('#sio-clear-logs').on('click', function(e) {
            e.preventDefault();
            
            if (confirm(window.sioAdmin.strings.confirm_clear_logs)) {
                clearLogs();
            }
        });
        
        // Load initial logs
        if ($('#sio-logs-container').length > 0) {
            loadLogs();
        }
        
        // Log pagination
        $(document).on('click', '.sio-pagination a', function(e) {
            e.preventDefault();
            var page = $(this).data('page');
            loadLogs(page);
        });
    }

    /**
     * Initialize system info functionality
     */
    function initSystemInfo() {
        // Export system info
        $('#sio-export-system-info').on('click', function(e) {
            e.preventDefault();
            exportSystemInfo();
        });
    }

    /**
     * Initialize server configuration functionality
     */
    function initServerConfig() {
        // Generate .htaccess rules
        $('#sio-generate-htaccess').on('click', function(e) {
            e.preventDefault();
            generateHtaccessRules();
        });
        
        // View current .htaccess rules
        $('#sio-view-htaccess').on('click', function(e) {
            e.preventDefault();
            viewHtaccessRules();
        });
        
        // Generate Nginx configuration
        $('#sio-generate-nginx').on('click', function(e) {
            e.preventDefault();
            generateNginxConfig();
        });
        
        // View Nginx configuration
        $('#sio-view-nginx').on('click', function(e) {
            e.preventDefault();
            viewNginxConfig();
        });
        
        // Copy configuration to clipboard
        $('#sio-copy-config').on('click', function(e) {
            e.preventDefault();
            copyConfigToClipboard();
        });
        
        // Download configuration file
        $('#sio-download-config').on('click', function(e) {
            e.preventDefault();
            downloadConfigFile();
        });
    }

    /**
     * Initialize AJAX handlers
     */
    function initAjaxHandlers() {
        // Global AJAX error handler
        $(document).ajaxError(function(event, xhr, settings, error) {
            if (xhr.status === 403) {
                showNotice('error', 'Insufficient permissions.');
            } else if (xhr.status === 0) {
                // Request was aborted, ignore
            } else {
                showNotice('error', 'An error occurred: ' + error);
            }
        });
    }

    /**
     * Save settings via AJAX
     */
    function saveSettings() {
        var $form = $('#sio-settings-form');
        var $submitButton = $form.find('input[type="submit"]');
        
        $submitButton.prop('disabled', true);
        showSpinner($submitButton);
        
        var formData = $form.serialize();
        formData += '&action=sio_save_settings';
        formData += '&nonce=' + window.sioAdmin.nonce;
        
        $.post(window.sioAdmin.ajaxUrl, formData)
            .done(function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                } else {
                    showNotice('error', response.data || 'Failed to save settings.');
                }
            })
            .fail(function() {
                showNotice('error', 'Failed to save settings.');
            })
            .always(function() {
                $submitButton.prop('disabled', false);
                hideSpinner($submitButton);
            });
    }

    /**
     * Reset settings to defaults
     */
    function resetSettings() {
        var data = {
            action: 'sio_reset_settings',
            nonce: window.sioAdmin.nonce
        };
        
        $.post(window.sioAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    showNotice('error', response.data || 'Failed to reset settings.');
                }
            })
            .fail(function() {
                showNotice('error', 'Failed to reset settings.');
            });
    }

    /**
     * Refresh statistics
     */
    function refreshStatistics() {
        var $button = $('#sio-refresh-stats');
        
        $button.prop('disabled', true);
        showSpinner($button);
        
        var data = {
            action: 'sio_refresh_stats',
            nonce: window.sioAdmin.nonce
        };
        
        $.post(window.sioAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    updateStatistics(response.data);
                    showNotice('success', 'Statistics refreshed.');
                } else {
                    showNotice('error', response.data || 'Failed to refresh statistics.');
                }
            })
            .fail(function() {
                showNotice('error', 'Failed to refresh statistics.');
            })
            .always(function() {
                $button.prop('disabled', false);
                hideSpinner($button);
            });
    }

    /**
     * Start batch processing
     */
    function startBatchProcessing() {
        var $button = $('#sio-start-batch');
        
        $button.prop('disabled', true);
        showSpinner($button);
        
        var data = {
            action: 'sio_start_batch',
            nonce: window.sioAdmin.nonce
        };
        
        $.post(window.sioAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    showNotice('success', 'Batch processing started.');
                    $('#sio-stop-batch').prop('disabled', false);
                    startBatchStatusRefresh();
                } else {
                    showNotice('error', response.data || 'Failed to start batch processing.');
                    $button.prop('disabled', false);
                }
            })
            .fail(function() {
                showNotice('error', 'Failed to start batch processing.');
                $button.prop('disabled', false);
            })
            .always(function() {
                hideSpinner($button);
            });
    }

    /**
     * Stop batch processing
     */
    function stopBatchProcessing() {
        var $button = $('#sio-stop-batch');
        
        $button.prop('disabled', true);
        showSpinner($button);
        
        var data = {
            action: 'sio_stop_batch',
            nonce: window.sioAdmin.nonce
        };
        
        $.post(window.sioAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    showNotice('success', 'Batch processing stopped.');
                    $('#sio-start-batch').prop('disabled', false);
                    stopBatchStatusRefresh();
                } else {
                    showNotice('error', response.data || 'Failed to stop batch processing.');
                    $button.prop('disabled', false);
                }
            })
            .fail(function() {
                showNotice('error', 'Failed to stop batch processing.');
                $button.prop('disabled', false);
            })
            .always(function() {
                hideSpinner($button);
            });
    }

    /**
     * Clear processing queue
     */
    function clearQueue() {
        var data = {
            action: 'sio_clear_queue',
            nonce: window.sioAdmin.nonce
        };
        
        $.post(window.sioAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    showNotice('success', 'Queue cleared.');
                    refreshQueue();
                    updateQueueStats();
                } else {
                    showNotice('error', response.data || 'Failed to clear queue.');
                }
            })
            .fail(function() {
                showNotice('error', 'Failed to clear queue.');
            });
    }

    /**
     * Add all images to queue
     */
    function addAllImages() {
        var $button = $('#sio-add-all-images');
        
        $button.prop('disabled', true);
        showSpinner($button);
        
        var data = {
            action: 'sio_add_all_images',
            nonce: window.sioAdmin.nonce
        };
        
        $.post(window.sioAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    refreshQueue();
                    updateQueueStats();
                } else {
                    showNotice('error', response.data || 'Failed to add images to queue.');
                }
            })
            .fail(function() {
                showNotice('error', 'Failed to add images to queue.');
            })
            .always(function() {
                $button.prop('disabled', false);
                hideSpinner($button);
            });
    }

    /**
     * Refresh queue display
     */
    function refreshQueue() {
        var status = $('#sio-status-filter').val();
        
        var data = {
            action: 'sio_get_queue',
            status: status,
            nonce: window.sioAdmin.nonce
        };
        
        $.post(window.sioAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    $('#sio-queue-list').html(response.data.html);
                } else {
                    showNotice('error', response.data || 'Failed to load queue.');
                }
            })
            .fail(function() {
                showNotice('error', 'Failed to load queue.');
            });
    }

    /**
     * Load logs
     */
    function loadLogs(page) {
        page = page || 1;
        
        var data = {
            action: 'sio_get_logs',
            page: page,
            status: $('#sio-log-status-filter').val(),
            action_filter: $('#sio-log-action-filter').val(),
            date_from: $('#sio-log-date-from').val(),
            date_to: $('#sio-log-date-to').val(),
            nonce: window.sioAdmin.nonce
        };
        
        $.post(window.sioAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    $('#sio-logs-container').html(response.data.html);
                    $('#sio-logs-pagination').html(response.data.pagination);
                } else {
                    showNotice('error', response.data || 'Failed to load logs.');
                }
            })
            .fail(function() {
                showNotice('error', 'Failed to load logs.');
            });
    }

    /**
     * Filter logs
     */
    function filterLogs() {
        loadLogs(1);
    }

    /**
     * Clear log filters
     */
    function clearLogFilters() {
        $('#sio-log-status-filter').val('');
        $('#sio-log-action-filter').val('');
        $('#sio-log-date-from').val('');
        $('#sio-log-date-to').val('');
        loadLogs(1);
    }

    /**
     * Export logs
     */
    function exportLogs() {
        var params = new URLSearchParams({
            action: 'sio_export_logs',
            status: $('#sio-log-status-filter').val(),
            action_filter: $('#sio-log-action-filter').val(),
            date_from: $('#sio-log-date-from').val(),
            date_to: $('#sio-log-date-to').val(),
            nonce: window.sioAdmin.nonce
        });
        
        window.location.href = window.sioAdmin.ajaxUrl + '?' + params.toString();
    }

    /**
     * Clear logs
     */
    function clearLogs() {
        var data = {
            action: 'sio_clear_logs',
            nonce: window.sioAdmin.nonce
        };
        
        $.post(window.sioAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    showNotice('success', 'Logs cleared.');
                    loadLogs(1);
                } else {
                    showNotice('error', response.data || 'Failed to clear logs.');
                }
            })
            .fail(function() {
                showNotice('error', 'Failed to clear logs.');
            });
    }

    /**
     * Export system info
     */
    function exportSystemInfo() {
        var params = new URLSearchParams({
            action: 'sio_export_system_info',
            nonce: window.sioAdmin.nonce
        });
        
        window.location.href = window.sioAdmin.ajaxUrl + '?' + params.toString();
    }

    /**
     * Generate .htaccess rules
     */
    function generateHtaccessRules() {
        var $button = $('#sio-generate-htaccess');
        
        $button.prop('disabled', true);
        showSpinner($button);
        
        var data = {
            action: 'sio_generate_htaccess',
            nonce: window.sioAdmin.nonce
        };
        
        $.post(window.sioAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    displayServerConfig(response.data.config, '.htaccess');
                } else {
                    showNotice('error', response.data || 'Failed to generate .htaccess rules.');
                }
            })
            .fail(function() {
                showNotice('error', 'Failed to generate .htaccess rules.');
            })
            .always(function() {
                $button.prop('disabled', false);
                hideSpinner($button);
            });
    }

    /**
     * View current .htaccess rules
     */
    function viewHtaccessRules() {
        var $button = $('#sio-view-htaccess');
        
        $button.prop('disabled', true);
        showSpinner($button);
        
        var data = {
            action: 'sio_view_htaccess',
            nonce: window.sioAdmin.nonce
        };
        
        $.post(window.sioAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    displayServerConfig(response.data.config, response.data.filename);
                } else {
                    showNotice('error', response.data || 'Failed to load .htaccess rules.');
                }
            })
            .fail(function() {
                showNotice('error', 'Failed to load .htaccess rules.');
            })
            .always(function() {
                $button.prop('disabled', false);
                hideSpinner($button);
            });
    }

    /**
     * Generate Nginx configuration
     */
    function generateNginxConfig() {
        var $button = $('#sio-generate-nginx');
        
        $button.prop('disabled', true);
        showSpinner($button);
        
        var data = {
            action: 'sio_generate_nginx',
            nonce: window.sioAdmin.nonce
        };
        
        $.post(window.sioAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    displayServerConfig(response.data.config, response.data.filename);
                } else {
                    showNotice('error', response.data || 'Failed to generate Nginx configuration.');
                }
            })
            .fail(function() {
                showNotice('error', 'Failed to generate Nginx configuration.');
            })
            .always(function() {
                $button.prop('disabled', false);
                hideSpinner($button);
            });
    }

    /**
     * View Nginx configuration
     */
    function viewNginxConfig() {
        var $button = $('#sio-view-nginx');
        
        $button.prop('disabled', true);
        showSpinner($button);
        
        var data = {
            action: 'sio_view_nginx',
            nonce: window.sioAdmin.nonce
        };
        
        $.post(window.sioAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    displayServerConfig(response.data.config, response.data.filename);
                } else {
                    showNotice('error', response.data || 'Failed to load Nginx configuration.');
                }
            })
            .fail(function() {
                showNotice('error', 'Failed to load Nginx configuration.');
            })
            .always(function() {
                $button.prop('disabled', false);
                hideSpinner($button);
            });
    }

    /**
     * Display server configuration
     */
    function displayServerConfig(config, filename) {
        $('#sio-config-content').val(config);
        $('#sio-server-config-output').show();
        
        // Store filename for download
        $('#sio-server-config-output').data('filename', filename);
        
        // Scroll to the configuration output
        $('html, body').animate({
            scrollTop: $('#sio-server-config-output').offset().top - 50
        }, 500);
    }

    /**
     * Copy configuration to clipboard
     */
    function copyConfigToClipboard() {
        var $textarea = $('#sio-config-content');
        
        if ($textarea.length && $textarea.val()) {
            $textarea.select();
            document.execCommand('copy');
            showNotice('success', 'Configuration copied to clipboard.');
        } else {
            showNotice('error', 'No configuration to copy.');
        }
    }

    /**
     * Download configuration file
     */
    function downloadConfigFile() {
        var config = $('#sio-config-content').val();
        var filename = $('#sio-server-config-output').data('filename') || 'server-config.txt';
        
        if (!config) {
            showNotice('error', 'No configuration to download.');
            return;
        }
        
        // Create download link
        var blob = new Blob([config], { type: 'text/plain' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        showNotice('success', 'Configuration file downloaded.');
    }

    /**
     * Start processing status refresh
     */
    function startProcessingStatusRefresh() {
        if (sioAdmin.refreshInterval) {
            clearInterval(sioAdmin.refreshInterval);
        }
        
        sioAdmin.refreshInterval = setInterval(function() {
            refreshProcessingStatus();
        }, 2000);
    }

    /**
     * Stop processing status refresh
     */
    function stopProcessingStatusRefresh() {
        if (sioAdmin.refreshInterval) {
            clearInterval(sioAdmin.refreshInterval);
            sioAdmin.refreshInterval = null;
        }
    }

    /**
     * Start batch status refresh
     */
    function startBatchStatusRefresh() {
        startProcessingStatusRefresh();
        
        // Also refresh queue stats
        setInterval(function() {
            updateQueueStats();
        }, 5000);
    }

    /**
     * Refresh processing status
     */
    function refreshProcessingStatus() {
        var data = {
            action: 'sio_get_processing_status',
            nonce: window.sioAdmin.nonce
        };
        
        $.post(window.sioAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    updateProcessingStatus(response.data);
                    
                    // Stop refreshing if not processing
                    if (response.data.status !== 'running') {
                        stopProcessingStatusRefresh();
                        $('#sio-start-batch').prop('disabled', false);
                        $('#sio-stop-batch').prop('disabled', true);
                    }
                }
            });
    }

    /**
     * Update queue statistics
     */
    function updateQueueStats() {
        var data = {
            action: 'sio_get_queue_stats',
            nonce: window.sioAdmin.nonce
        };
        
        $.post(window.sioAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    $('#sio-pending-count').text(response.data.pending);
                    $('#sio-processing-count').text(response.data.processing);
                    $('#sio-completed-count').text(response.data.completed);
                    $('#sio-failed-count').text(response.data.failed);
                }
            });
    }

    /**
     * Update statistics display
     */
    function updateStatistics(stats) {
        $('.sio-stat-number').each(function() {
            var $this = $(this);
            var key = $this.data('stat');
            if (stats[key] !== undefined) {
                $this.text(numberFormat(stats[key]));
            }
        });
    }

    /**
     * Update processing status display
     */
    function updateProcessingStatus(status) {
        if (status.status === 'running') {
            $('#sio-progress-fill').css('width', status.percentage + '%');
            $('#sio-progress-text').text(
                'Processing ' + status.current + ' of ' + status.total + ' images (' + status.percentage + '%)'
            );
        }
    }

    /**
     * Show admin notice
     */
    function showNotice(type, message) {
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $notice.remove();
            });
        }, 5000);
        
        // Make dismissible
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut(function() {
                $notice.remove();
            });
        });
    }

    /**
     * Show spinner next to element
     */
    function showSpinner($element) {
        if (!$element.siblings('.sio-spinner').length) {
            $element.after('<span class="sio-spinner"></span>');
        }
    }

    /**
     * Hide spinner next to element
     */
    function hideSpinner($element) {
        $element.siblings('.sio-spinner').remove();
    }

    /**
     * Format number with commas
     */
    function numberFormat(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    /**
     * Debounce function
     */
    function debounce(func, wait, immediate) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            var later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }

    // Initialize when document is ready
    $(document).ready(function() {
        init();
    });

    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        if (sioAdmin.refreshInterval) {
            clearInterval(sioAdmin.refreshInterval);
        }
    });

})(jQuery);