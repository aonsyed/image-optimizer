/**
 * WP Image Optimizer Admin JavaScript
 *
 * @package WP_Image_Optimizer
 */

(function($) {
    'use strict';

    /**
     * Admin object
     */
    var WPImageOptimizerAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initQualitySliders();
            this.initProgressBars();
            this.initServerConfig();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Settings form submission
            $(document).on('submit', '#wp-image-optimizer-settings-form', this.handleSettingsSubmit);
            
            // Regenerate images button
            $(document).on('click', '.wp-image-optimizer-regenerate', this.handleRegenerateImages);
            
            // Bulk regeneration buttons
            $(document).on('click', '.wp-image-optimizer-start-bulk', this.handleStartBulkRegeneration);
            $(document).on('click', '.wp-image-optimizer-stop-bulk', this.handleStopBulkRegeneration);
            
            // Test conversion button
            $(document).on('click', '.wp-image-optimizer-test-conversion', this.handleTestConversion);
            
            // Clear cache button
            $(document).on('click', '.wp-image-optimizer-clear-cache', this.handleClearCache);
            
            // Format enable/disable toggles
            $(document).on('change', '.wp-image-optimizer-format-enabled', this.handleFormatToggle);
            
            // Tab navigation
            $(document).on('click', '.wp-image-optimizer-nav .nav-tab', this.handleTabClick);
            
            // Server configuration tabs
            $(document).on('click', '.wp-image-optimizer-config-tab', this.handleConfigTabClick);
            
            // Copy configuration buttons
            $(document).on('click', '.wp-image-optimizer-copy-config', this.handleCopyConfig);
            
            // Server configuration type change
            $(document).on('change', '#wp_image_optimizer_server_config_type', this.handleServerConfigTypeChange);
        },

        /**
         * Initialize quality sliders
         */
        initQualitySliders: function() {
            $('.wp-image-optimizer-quality-slider').each(function() {
                var $slider = $(this);
                var $valueDisplay = $slider.siblings('.wp-image-optimizer-quality-value');
                
                // Update value display when slider changes
                $slider.on('input', function() {
                    $valueDisplay.text($(this).val());
                });
                
                // Initialize display
                $valueDisplay.text($slider.val());
            });
        },

        /**
         * Initialize progress bars
         */
        initProgressBars: function() {
            $('.wp-image-optimizer-progress-bar').each(function() {
                var $bar = $(this);
                var targetWidth = $bar.data('progress') || 0;
                
                // Animate progress bar
                setTimeout(function() {
                    $bar.css('width', targetWidth + '%');
                }, 100);
            });
        },

        /**
         * Handle settings form submission
         */
        handleSettingsSubmit: function() {
            var $form = $(this);
            var $submitButton = $form.find('input[type="submit"]');
            
            // Show loading state
            $submitButton.prop('disabled', true).addClass('wp-image-optimizer-loading');
            
            // Add spinner
            if (!$submitButton.siblings('.wp-image-optimizer-spinner').length) {
                $submitButton.after('<span class="wp-image-optimizer-spinner spinner is-active"></span>');
            }
        },

        /**
         * Handle regenerate images button click
         */
        handleRegenerateImages: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            
            // Confirm action
            if (!confirm(window.wpImageOptimizerAdmin.strings.confirmRegenerate)) {
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true).addClass('wp-image-optimizer-loading');
            $button.text(window.wpImageOptimizerAdmin.strings.processing);
            
            // AJAX request
            $.ajax({
                url: window.wpImageOptimizerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_image_optimizer_regenerate_images',
                    nonce: window.wpImageOptimizerAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPImageOptimizerAdmin.showNotice('success', window.wpImageOptimizerAdmin.strings.success);
                        // Refresh page to show updated stats
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        WPImageOptimizerAdmin.showNotice('error', response.data || window.wpImageOptimizerAdmin.strings.error);
                    }
                },
                error: function() {
                    WPImageOptimizerAdmin.showNotice('error', window.wpImageOptimizerAdmin.strings.error);
                },
                complete: function() {
                    // Reset button state
                    $button.prop('disabled', false).removeClass('wp-image-optimizer-loading');
                    $button.text($button.data('original-text') || 'Regenerate Images');
                }
            });
        },

        /**
         * Handle test conversion button click
         */
        handleTestConversion: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $results = $('.wp-image-optimizer-test-results');
            
            // Show loading state
            $button.prop('disabled', true).addClass('wp-image-optimizer-loading');
            $button.text(window.wpImageOptimizerAdmin.strings.processing);
            
            // Clear previous results
            $results.empty();
            
            // AJAX request
            $.ajax({
                url: window.wpImageOptimizerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_image_optimizer_test_conversion',
                    nonce: window.wpImageOptimizerAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $results.html(response.data.html);
                        WPImageOptimizerAdmin.showNotice('success', 'Test completed successfully');
                    } else {
                        WPImageOptimizerAdmin.showNotice('error', response.data || window.wpImageOptimizerAdmin.strings.error);
                    }
                },
                error: function() {
                    WPImageOptimizerAdmin.showNotice('error', window.wpImageOptimizerAdmin.strings.error);
                },
                complete: function() {
                    // Reset button state
                    $button.prop('disabled', false).removeClass('wp-image-optimizer-loading');
                    $button.text($button.data('original-text') || 'Test Conversion');
                }
            });
        },

        /**
         * Handle clear cache button click
         */
        handleClearCache: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            
            // Show loading state
            $button.prop('disabled', true).addClass('wp-image-optimizer-loading');
            $button.text(window.wpImageOptimizerAdmin.strings.processing);
            
            // AJAX request
            $.ajax({
                url: window.wpImageOptimizerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_image_optimizer_clear_cache',
                    nonce: window.wpImageOptimizerAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPImageOptimizerAdmin.showNotice('success', 'Cache cleared successfully');
                    } else {
                        WPImageOptimizerAdmin.showNotice('error', response.data || window.wpImageOptimizerAdmin.strings.error);
                    }
                },
                error: function() {
                    WPImageOptimizerAdmin.showNotice('error', window.wpImageOptimizerAdmin.strings.error);
                },
                complete: function() {
                    // Reset button state
                    $button.prop('disabled', false).removeClass('wp-image-optimizer-loading');
                    $button.text($button.data('original-text') || 'Clear Cache');
                }
            });
        },

        /**
         * Handle start bulk regeneration button click
         */
        handleStartBulkRegeneration: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $stopButton = $('.wp-image-optimizer-stop-bulk');
            var $progressContainer = $('.wp-image-optimizer-bulk-progress');
            var $progressBar = $('.wp-image-optimizer-progress-bar');
            var $progressText = $('.wp-image-optimizer-progress-text');
            var $progressStatus = $('.wp-image-optimizer-progress-status');
            
            // Confirm action
            if (!confirm(window.wpImageOptimizerAdmin.strings.confirmRegenerate)) {
                return;
            }
            
            // Show progress container and hide start button
            $progressContainer.show();
            $button.hide();
            $stopButton.show();
            
            // Reset progress
            $progressBar.css('width', '0%');
            $('.wp-image-optimizer-progress-current').text('0');
            $('.wp-image-optimizer-progress-total').text('0');
            $progressStatus.text('Initializing...');
            
            // Start bulk regeneration
            this.startBulkProcess();
        },

        /**
         * Handle stop bulk regeneration button click
         */
        handleStopBulkRegeneration: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $startButton = $('.wp-image-optimizer-start-bulk');
            var $progressContainer = $('.wp-image-optimizer-bulk-progress');
            var $progressStatus = $('.wp-image-optimizer-progress-status');
            
            // Stop the bulk process
            this.stopBulkProcess();
            
            // Update UI
            $progressStatus.text('Process stopped by user.');
            $button.hide();
            $startButton.show();
            
            // Hide progress after delay
            setTimeout(function() {
                $progressContainer.hide();
            }, 3000);
        },

        /**
         * Start bulk regeneration process
         */
        startBulkProcess: function() {
            var self = this;
            
            // Initialize bulk process
            $.ajax({
                url: window.wpImageOptimizerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_image_optimizer_start_bulk_regeneration',
                    nonce: window.wpImageOptimizerAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Start polling for progress
                        self.bulkProcessInterval = setInterval(function() {
                            self.checkBulkProgress();
                        }, 2000);
                        
                        // Update total count
                        if (response.data.total) {
                            $('.wp-image-optimizer-progress-total').text(response.data.total);
                        }
                    } else {
                        self.showNotice('error', response.data || 'Failed to start bulk regeneration');
                        self.resetBulkUI();
                    }
                },
                error: function() {
                    self.showNotice('error', 'Failed to start bulk regeneration');
                    self.resetBulkUI();
                }
            });
        },

        /**
         * Stop bulk regeneration process
         */
        stopBulkProcess: function() {
            // Clear polling interval
            if (this.bulkProcessInterval) {
                clearInterval(this.bulkProcessInterval);
                this.bulkProcessInterval = null;
            }
            
            // Send stop request
            $.ajax({
                url: window.wpImageOptimizerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_image_optimizer_stop_bulk_regeneration',
                    nonce: window.wpImageOptimizerAdmin.nonce
                }
            });
        },

        /**
         * Check bulk regeneration progress
         */
        checkBulkProgress: function() {
            var self = this;
            
            $.ajax({
                url: window.wpImageOptimizerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_image_optimizer_bulk_progress',
                    nonce: window.wpImageOptimizerAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        // Update progress bar
                        var percentage = data.total > 0 ? Math.round((data.processed / data.total) * 100) : 0;
                        $('.wp-image-optimizer-progress-bar').css('width', percentage + '%');
                        
                        // Update progress text
                        $('.wp-image-optimizer-progress-current').text(data.processed);
                        $('.wp-image-optimizer-progress-total').text(data.total);
                        
                        // Update status
                        $('.wp-image-optimizer-progress-status').text(data.status || 'Processing...');
                        
                        // Check if completed
                        if (data.completed) {
                            self.completeBulkProcess(data);
                        }
                    }
                },
                error: function() {
                    // Continue polling on error, but show warning
                    console.warn('Failed to check bulk progress');
                }
            });
        },

        /**
         * Complete bulk regeneration process
         */
        completeBulkProcess: function(data) {
            // Stop polling
            if (this.bulkProcessInterval) {
                clearInterval(this.bulkProcessInterval);
                this.bulkProcessInterval = null;
            }
            
            // Update UI
            $('.wp-image-optimizer-progress-status').text('Process completed successfully!');
            $('.wp-image-optimizer-stop-bulk').hide();
            $('.wp-image-optimizer-start-bulk').show();
            
            // Show completion notice
            this.showNotice('success', 'Bulk regeneration completed. Processed ' + data.processed + ' images.');
            
            // Hide progress after delay and refresh stats
            setTimeout(function() {
                $('.wp-image-optimizer-bulk-progress').hide();
                location.reload(); // Refresh to show updated stats
            }, 5000);
        },

        /**
         * Reset bulk regeneration UI
         */
        resetBulkUI: function() {
            $('.wp-image-optimizer-bulk-progress').hide();
            $('.wp-image-optimizer-stop-bulk').hide();
            $('.wp-image-optimizer-start-bulk').show();
            
            if (this.bulkProcessInterval) {
                clearInterval(this.bulkProcessInterval);
                this.bulkProcessInterval = null;
            }
        },

        /**
         * Handle server configuration tab click
         */
        handleConfigTabClick: function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var targetTab = $tab.data('tab');
            
            // Update active tab
            $tab.siblings().removeClass('active');
            $tab.addClass('active');
            
            // Show/hide tab content
            $('.wp-image-optimizer-config-panel').removeClass('active');
            $('#' + targetTab + '-config').addClass('active');
        },

        /**
         * Handle copy configuration button click
         */
        handleCopyConfig: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var configType = $button.data('config');
            var $codeBlock = $('#' + configType + '-config .wp-image-optimizer-config-code code');
            
            if ($codeBlock.length) {
                var configText = $codeBlock.text();
                
                // Try to use modern clipboard API
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(configText).then(function() {
                        WPImageOptimizerAdmin.showCopySuccess($button);
                    }).catch(function() {
                        WPImageOptimizerAdmin.fallbackCopyToClipboard(configText, $button);
                    });
                } else {
                    // Fallback for older browsers
                    this.fallbackCopyToClipboard(configText, $button);
                }
            }
        },

        /**
         * Fallback copy to clipboard method
         */
        fallbackCopyToClipboard: function(text, $button) {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                var successful = document.execCommand('copy');
                if (successful) {
                    this.showCopySuccess($button);
                } else {
                    this.showCopyError($button);
                }
            } catch (err) {
                this.showCopyError($button);
            }
            
            document.body.removeChild(textArea);
        },

        /**
         * Show copy success feedback
         */
        showCopySuccess: function($button) {
            var originalText = $button.text();
            $button.text('Copied!').addClass('wp-image-optimizer-copy-success');
            
            setTimeout(function() {
                $button.text(originalText).removeClass('wp-image-optimizer-copy-success');
            }, 2000);
        },

        /**
         * Show copy error feedback
         */
        showCopyError: function($button) {
            var originalText = $button.text();
            $button.text('Copy Failed').addClass('wp-image-optimizer-copy-error');
            
            setTimeout(function() {
                $button.text(originalText).removeClass('wp-image-optimizer-copy-error');
            }, 2000);
            
            this.showNotice('error', 'Failed to copy configuration. Please copy manually.');
        },

        /**
         * Handle format toggle change
         */
        handleFormatToggle: function() {
            var $checkbox = $(this);
            var $qualityRow = $checkbox.closest('tr').next('.wp-image-optimizer-quality-row');
            
            if ($checkbox.is(':checked')) {
                $qualityRow.show();
            } else {
                $qualityRow.hide();
            }
        },

        /**
         * Handle tab navigation click
         */
        handleTabClick: function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var targetTab = $tab.attr('href').substring(1);
            
            // Update active tab
            $tab.siblings().removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show/hide tab content
            $('.wp-image-optimizer-tab-content').hide();
            $('#' + targetTab).show();
            
            // Update URL hash
            if (history.pushState) {
                history.pushState(null, null, '#' + targetTab);
            } else {
                location.hash = '#' + targetTab;
            }
        },

        /**
         * Show admin notice
         */
        showNotice: function(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Insert notice after page title
            $('.wrap h1').after($notice);
            
            // Auto-dismiss success notices
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 5000);
            }
            
            // Make notice dismissible
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut();
            });
        },

        /**
         * Update statistics display
         */
        updateStats: function(stats) {
            if (stats.total_conversions !== undefined) {
                $('.wp-image-optimizer-stat[data-stat="conversions"] .wp-image-optimizer-stat-value')
                    .text(stats.total_conversions);
            }
            
            if (stats.space_saved !== undefined) {
                $('.wp-image-optimizer-stat[data-stat="space-saved"] .wp-image-optimizer-stat-value')
                    .text(this.formatBytes(stats.space_saved));
            }
        },

        /**
         * Format bytes for display
         */
        formatBytes: function(bytes) {
            if (bytes === 0) return '0 B';
            
            var k = 1024;
            var sizes = ['B', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        },

        /**
         * Initialize server configuration
         */
        initServerConfig: function() {
            // Load initial server configuration if server type is selected
            var $serverTypeSelect = $('#wp_image_optimizer_server_config_type');
            if ($serverTypeSelect.length && $serverTypeSelect.val() !== 'none') {
                this.loadServerConfig($serverTypeSelect.val());
            }
        },

        /**
         * Handle server configuration type change
         */
        handleServerConfigTypeChange: function() {
            var $select = $(this);
            var serverType = $select.val();
            var $configSection = $('.wp-image-optimizer-server-config-section');
            
            if (serverType === 'none') {
                $configSection.hide();
            } else {
                $configSection.show();
                WPImageOptimizerAdmin.loadServerConfig(serverType);
            }
        },

        /**
         * Load server configuration
         */
        loadServerConfig: function(serverType) {
            var self = this;
            var $textarea = $('#wp-image-optimizer-server-config');
            var $validation = $('#wp-image-optimizer-config-validation');
            var $copyButton = $('.wp-image-optimizer-copy-config');
            
            // Show loading state
            $textarea.val('Loading configuration...');
            $copyButton.prop('disabled', true);
            
            // AJAX request to get server configuration
            $.ajax({
                url: window.wpImageOptimizerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_image_optimizer_get_server_config',
                    server_type: serverType,
                    nonce: window.wpImageOptimizerAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        // Update textarea with configuration
                        $textarea.val(data.config);
                        
                        // Update copy button
                        $copyButton.prop('disabled', false).data('config-type', data.server_type);
                        
                        // Show validation results
                        self.displayConfigValidation(data.validation, $validation);
                        
                    } else {
                        $textarea.val('Error loading configuration: ' + (response.data || 'Unknown error'));
                        self.showNotice('error', 'Failed to load server configuration');
                    }
                },
                error: function() {
                    $textarea.val('Error loading configuration');
                    self.showNotice('error', 'Failed to load server configuration');
                },
                complete: function() {
                    $copyButton.prop('disabled', false);
                }
            });
        },

        /**
         * Display configuration validation results
         */
        displayConfigValidation: function(validation, $container) {
            $container.empty();
            
            if (validation.valid) {
                $container.html('<div class="wp-image-optimizer-validation-success">' +
                    '<span class="dashicons dashicons-yes"></span> Configuration is valid' +
                    '</div>');
            } else {
                var errorsHtml = '<div class="wp-image-optimizer-validation-error">' +
                    '<span class="dashicons dashicons-warning"></span> Configuration has issues:' +
                    '<ul>';
                
                for (var i = 0; i < validation.errors.length; i++) {
                    errorsHtml += '<li>' + validation.errors[i] + '</li>';
                }
                
                errorsHtml += '</ul></div>';
                $container.html(errorsHtml);
            }
        },

        /**
         * Handle copy configuration button click (updated)
         */
        handleCopyConfig: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $textarea = $('#wp-image-optimizer-server-config');
            
            if ($textarea.length) {
                var configText = $textarea.val();
                
                // Try to use modern clipboard API
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(configText).then(function() {
                        WPImageOptimizerAdmin.showCopySuccess($button);
                    }).catch(function() {
                        WPImageOptimizerAdmin.fallbackCopyToClipboard(configText, $button);
                    });
                } else {
                    // Fallback for older browsers
                    WPImageOptimizerAdmin.fallbackCopyToClipboard(configText, $button);
                }
            }
        },

        /**
         * Initialize tab navigation from URL hash
         */
        initTabNavigation: function() {
            var hash = window.location.hash.substring(1);
            
            if (hash) {
                var $tab = $('.wp-image-optimizer-nav .nav-tab[href="#' + hash + '"]');
                if ($tab.length) {
                    $tab.trigger('click');
                }
            }
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        WPImageOptimizerAdmin.init();
        WPImageOptimizerAdmin.initTabNavigation();
    });

    /**
     * Handle window hash change
     */
    $(window).on('hashchange', function() {
        WPImageOptimizerAdmin.initTabNavigation();
    });

    // Make admin object globally available
    window.WPImageOptimizerAdmin = WPImageOptimizerAdmin;

})(jQuery);