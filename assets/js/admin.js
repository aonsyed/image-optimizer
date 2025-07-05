/**
 * Image Optimizer Admin JavaScript
 *
 * @package ImageOptimizer
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        ImageOptimizerAdmin.init();
    });

    /**
     * Image Optimizer Admin object
     */
    var ImageOptimizerAdmin = {
        
        /**
         * Initialize the admin interface
         */
        init: function() {
            this.bindEvents();
            this.initTooltips();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Convert single image
            $(document).on('click', '.convert-to-webp-avif', this.handleSingleConversion);
            
            // Bulk actions
            $('#schedule-bulk-conversion').on('click', this.handleBulkConversion);
            $('#clean-up-optimized-images').on('click', this.handleCleanup);
            
            // Toggle switches
            $('#toggle-scheduler').on('change', this.handleToggleScheduler);
            $('#toggle-conversion-on-upload').on('change', this.handleToggleConversionOnUpload);
            $('#toggle-remove-originals').on('change', this.handleToggleRemoveOriginals);
            $('#set-conversion-format').on('change', this.handleSetConversionFormat);
            
            // Settings form submission
            $('.image-optimizer-admin form').on('submit', this.handleFormSubmit);
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            $('.image-optimizer-admin [title]').tooltip({
                position: { my: 'left+5 center', at: 'right center' }
            });
        },

        /**
         * Handle single image conversion
         */
        handleSingleConversion: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var attachmentId = $button.data('id');
            
            if (!$button.hasClass('converting')) {
                $button.addClass('converting').text(imageOptimizerAjax.strings.converting);
                
                $.ajax({
                    url: imageOptimizerAjax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'convert_image',
                        nonce: imageOptimizerAjax.nonce,
                        attachment_id: attachmentId
                    },
                    success: function(response) {
                        if (response.success) {
                            ImageOptimizerAdmin.showNotice(response.data, 'success');
                            $button.text('Converted').removeClass('converting').addClass('converted');
                            
                            // Refresh the page to update the media library
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            ImageOptimizerAdmin.showNotice(response.data, 'error');
                            $button.text('Error').removeClass('converting').addClass('error');
                        }
                    },
                    error: function() {
                        ImageOptimizerAdmin.showNotice('Network error occurred', 'error');
                        $button.text('Error').removeClass('converting').addClass('error');
                    }
                });
            }
        },

        /**
         * Handle bulk conversion
         */
        handleBulkConversion: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            
            if (!$button.hasClass('processing')) {
                $button.addClass('processing').text('Scheduling...');
                
                $.ajax({
                    url: imageOptimizerAjax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'schedule_bulk_conversion',
                        nonce: imageOptimizerAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            ImageOptimizerAdmin.showNotice(response.data, 'success');
                        } else {
                            ImageOptimizerAdmin.showNotice(response.data, 'error');
                        }
                        $button.removeClass('processing').text('Schedule Bulk Conversion');
                    },
                    error: function() {
                        ImageOptimizerAdmin.showNotice('Network error occurred', 'error');
                        $button.removeClass('processing').text('Schedule Bulk Conversion');
                    }
                });
            }
        },

        /**
         * Handle cleanup
         */
        handleCleanup: function(e) {
            e.preventDefault();
            
            if (confirm('Are you sure you want to clean up all optimized images? This action cannot be undone.')) {
                var $button = $(this);
                
                if (!$button.hasClass('processing')) {
                    $button.addClass('processing').text('Cleaning...');
                    
                    $.ajax({
                        url: imageOptimizerAjax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'clean_up_optimized_images',
                            nonce: imageOptimizerAjax.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                ImageOptimizerAdmin.showNotice(response.data, 'success');
                            } else {
                                ImageOptimizerAdmin.showNotice(response.data, 'error');
                            }
                            $button.removeClass('processing').text('Clean Up Optimized Images');
                        },
                        error: function() {
                            ImageOptimizerAdmin.showNotice('Network error occurred', 'error');
                            $button.removeClass('processing').text('Clean Up Optimized Images');
                        }
                    });
                }
            }
        },

        /**
         * Handle toggle scheduler
         */
        handleToggleScheduler: function() {
            var enabled = $(this).is(':checked');
            var $checkbox = $(this);
            
            $.ajax({
                url: imageOptimizerAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'toggle_scheduler',
                    nonce: imageOptimizerAjax.nonce,
                    enabled: enabled
                },
                success: function(response) {
                    if (response.success) {
                        ImageOptimizerAdmin.showNotice(response.data, 'success');
                    } else {
                        ImageOptimizerAdmin.showNotice(response.data, 'error');
                        $checkbox.prop('checked', !enabled);
                    }
                },
                error: function() {
                    ImageOptimizerAdmin.showNotice('Network error occurred', 'error');
                    $checkbox.prop('checked', !enabled);
                }
            });
        },

        /**
         * Handle toggle conversion on upload
         */
        handleToggleConversionOnUpload: function() {
            var enabled = $(this).is(':checked');
            var $checkbox = $(this);
            
            $.ajax({
                url: imageOptimizerAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'toggle_conversion_on_upload',
                    nonce: imageOptimizerAjax.nonce,
                    enabled: enabled
                },
                success: function(response) {
                    if (response.success) {
                        ImageOptimizerAdmin.showNotice(response.data, 'success');
                    } else {
                        ImageOptimizerAdmin.showNotice(response.data, 'error');
                        $checkbox.prop('checked', !enabled);
                    }
                },
                error: function() {
                    ImageOptimizerAdmin.showNotice('Network error occurred', 'error');
                    $checkbox.prop('checked', !enabled);
                }
            });
        },

        /**
         * Handle toggle remove originals
         */
        handleToggleRemoveOriginals: function() {
            var enabled = $(this).is(':checked');
            var $checkbox = $(this);
            
            if (enabled) {
                if (!confirm('Warning: This will permanently delete original images after conversion. Are you sure?')) {
                    $checkbox.prop('checked', false);
                    return;
                }
            }
            
            $.ajax({
                url: imageOptimizerAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'toggle_remove_originals',
                    nonce: imageOptimizerAjax.nonce,
                    enabled: enabled
                },
                success: function(response) {
                    if (response.success) {
                        ImageOptimizerAdmin.showNotice(response.data, 'success');
                    } else {
                        ImageOptimizerAdmin.showNotice(response.data, 'error');
                        $checkbox.prop('checked', !enabled);
                    }
                },
                error: function() {
                    ImageOptimizerAdmin.showNotice('Network error occurred', 'error');
                    $checkbox.prop('checked', !enabled);
                }
            });
        },

        /**
         * Handle set conversion format
         */
        handleSetConversionFormat: function() {
            var format = $(this).val();
            var $select = $(this);
            
            $.ajax({
                url: imageOptimizerAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'set_conversion_format',
                    nonce: imageOptimizerAjax.nonce,
                    format: format
                },
                success: function(response) {
                    if (response.success) {
                        ImageOptimizerAdmin.showNotice(response.data, 'success');
                    } else {
                        ImageOptimizerAdmin.showNotice(response.data, 'error');
                    }
                },
                error: function() {
                    ImageOptimizerAdmin.showNotice('Network error occurred', 'error');
                }
            });
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function() {
            // Add loading state
            var $form = $(this);
            var $submitButton = $form.find('input[type="submit"]');
            
            $submitButton.prop('disabled', true).val('Saving...');
            
            // Re-enable after a short delay
            setTimeout(function() {
                $submitButton.prop('disabled', false).val('Save Changes');
            }, 2000);
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            var noticeClass = 'notice-' + type;
            var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Remove existing notices
            $('.image-optimizer-admin .notice').remove();
            
            // Add new notice
            $('.image-optimizer-admin').prepend($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Show toast notification
         */
        showToast: function(title, message, type) {
            // Create toast container if it doesn't exist
            if ($('#toast-container').length === 0) {
                $('body').append('<div id="toast-container"></div>');
            }
            
            var toastClass = 'toast-' + type;
            var $toast = $('<div class="toast ' + toastClass + '"><div class="toast-header"><strong>' + title + '</strong></div><div class="toast-body">' + message + '</div></div>');
            
            $('#toast-container').append($toast);
            
            // Show toast
            $toast.fadeIn();
            
            // Auto-hide after 3 seconds
            setTimeout(function() {
                $toast.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

})(jQuery);
