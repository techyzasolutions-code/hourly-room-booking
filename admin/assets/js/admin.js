/**
 * Hourly Room Booking - Admin JavaScript
 * Global functions and utilities for admin area
 */

(function($) {
    'use strict';

    // Get localized strings
    var i18n = (typeof hrbAdmin !== 'undefined' && hrbAdmin.i18n) ? hrbAdmin.i18n : {
        confirmAction: 'Confirm Action',
        confirmTimeSlotChange: 'Confirm Time Slot Change',
        originalTime: 'Original time:',
        newTime: 'New time:',
        confirm: 'Confirm',
        cancel: 'Cancel',
        sureChangeTimeSlot: 'Are you sure you want to change the time slot?'
    };

    /**
     * Custom confirmation dialog - replaces browser confirm()
     * Can be used anywhere in the admin area
     * 
     * @param {string} message - Main confirmation message
     * @param {string|object} options - Either a string for simple confirm, or object with:
     *   - title: Dialog title
     *   - message: Confirmation message
     *   - details: Array of {label, value, class} objects for additional info
     *   - confirmText: Text for confirm button (default: "Confirm")
     *   - cancelText: Text for cancel button (default: "Cancel")
     *   - icon: Icon emoji or HTML (default: "⚠️")
     * @param {function} onConfirm - Callback when user confirms
     * @param {function} onCancel - Callback when user cancels
     */
    window.hrbShowConfirmDialog = function(message, options, onConfirm, onCancel) {
        // Ensure jQuery is available
        if (typeof jQuery === 'undefined') {
            return false;
        }
        
        var $ = jQuery;
        
        // Handle backward compatibility: if options is a string, treat it as old-style (originalTime, newTime)
        if (typeof options === 'string') {
            var originalTime = options;
            var newTime = arguments[2];
            onConfirm = arguments[3];
            onCancel = arguments[4];
            
            // Convert to new format
            options = {
                title: i18n.confirmTimeSlotChange,
                message: message,
                details: [
                    {
                        label: i18n.originalTime,
                        value: originalTime,
                        class: 'original'
                    },
                    {
                        label: i18n.newTime,
                        value: newTime,
                        class: 'new'
                    }
                ]
            };
        }
        
        // Ensure options is an object
        if (typeof options === 'function') {
            onConfirm = options;
            onCancel = arguments[2];
            options = {
                message: message
            };
        }
        
        // Default options
        options = $.extend({
            title: i18n.confirmAction,
            message: message || '',
            details: [],
            confirmText: i18n.confirm,
            cancelText: i18n.cancel,
            icon: '⚠️'
        }, options || {});
        
        var $modal = $('#hrb-confirm-modal');
        
        // Ensure modal HTML exists
        if ($modal.length === 0) {
            // Create modal dynamically if it doesn't exist
            // var modalHtml = '<div id="hrb-confirm-modal">' +
            //     '<div class="hrb-confirm-content">' +
            //     '<div class="hrb-confirm-header">' +
            //     '<div class="hrb-confirm-icon"></div>' +
            //     '<h3 id="hrb-confirm-title"></h3>' +
            //     '</div>' +
            //     '<div class="hrb-confirm-body">' +
            //     '<div class="hrb-confirm-message" id="hrb-confirm-message"></div>' +
            //     '<div class="hrb-confirm-details 1" id="hrb-confirm-details"></div>' +
            //     '</div>' +
            //     '<div class="hrb-confirm-footer">' +
            //     '<button type="button" class="hrb-confirm-btn hrb-confirm-btn-cancel" id="hrb-confirm-cancel"></button>' +
            //     '<button type="button" class="hrb-confirm-btn hrb-confirm-btn-confirm" id="hrb-confirm-ok"></button>' +
            //     '</div>' +
            //     '</div>' +
            //     '</div>';
            // $('body').append(modalHtml);
            // $modal = $('#hrb-confirm-modal');
        }
        
        // Set content
        $('#hrb-confirm-title').text(options.title);
        $('#hrb-confirm-message').text(options.message);
        $('.hrb-confirm-icon').text(options.icon);
        $('#hrb-confirm-ok').text(options.confirmText);
        $('#hrb-confirm-cancel').text(options.cancelText);
        
        // Build details HTML
        var detailsHtml = '';
        if (options.details && options.details.length > 0) {
            detailsHtml = '<div class="hrb-confirm-details">';
            options.details.forEach(function(detail) {
                var detailClass = detail.class || '';
                detailsHtml += '<div class="hrb-confirm-detail-row">' +
                    '<span class="hrb-confirm-detail-label">' + detail.label + '</span>' +
                    '<span class="hrb-confirm-detail-value ' + detailClass + '">' + detail.value + '</span>' +
                    '</div>';
            });
            detailsHtml += '</div>';
        }
        $('#hrb-confirm-details').html(detailsHtml);
        
        
        // Show modal
        $modal.addClass('show');
        
        // Handle confirm button
        $('#hrb-confirm-ok').off('click.confirm').on('click.confirm', function() {
            $modal.removeClass('show');
            $(document).off('keydown.confirm-modal');
            if (typeof onConfirm === 'function') {
                onConfirm();
            }
        });
        
        // Handle cancel button
        $('#hrb-confirm-cancel').off('click.confirm').on('click.confirm', function() {
            $modal.removeClass('show');
            $(document).off('keydown.confirm-modal');
            if (typeof onCancel === 'function') {
                onCancel();
            }
        });
        
        // Close on overlay click
        $modal.off('click.confirm').on('click.confirm', function(e) {
            if ($(e.target).is('#hrb-confirm-modal')) {
                $modal.removeClass('show');
                $(document).off('keydown.confirm-modal');
                if (typeof onCancel === 'function') {
                    onCancel();
                }
            }
        });
        
        // Close on ESC key
        $(document).off('keydown.confirm-modal').on('keydown.confirm-modal', function(e) {
            if (e.key === 'Escape' && $modal.hasClass('show')) {
                $modal.removeClass('show');
                $(document).off('keydown.confirm-modal');
                if (typeof onCancel === 'function') {
                    onCancel();
                }
            }
        });
    };
    
    /**
     * Backward compatibility - old function name
     */
    window.showConfirmDialog = function(message, originalTime, newTime, onConfirm, onCancel) {
        window.hrbShowConfirmDialog(message, originalTime, newTime, onConfirm, onCancel);
    };

    /**
     * Generic alert/message dialog with different types (success, danger, warning)
     * Uses the same modal structure and classes as hrbShowConfirmDialog
     * 
     * @param {string} message - Main message
     * @param {string|object} options - Either a warning message string, or object with:
     *   - warningMessage: Secondary warning/notice message
     *   - type: 'success', 'danger', 'warning' (default: 'warning')
     *   - title: Dialog title
     *   - details: Array of {label, value, class} objects for additional info
     *   - confirmText: Text for confirm button (default based on type)
     *   - cancelText: Text for cancel button (default: "Cancel")
     *   - icon: Icon emoji or HTML (default based on type)
     * @param {function} onConfirm - Callback when user confirms
     * @param {function} onCancel - Callback when user cancels
     */
    window.hrbShowAlertDialog = function(message, options, onConfirm, onCancel) {
        // Ensure jQuery is available
        if (typeof jQuery === 'undefined') {
            return false;
        }
        
        var $ = jQuery;
        
        // Handle backward compatibility: if options is a string, treat it as warningMessage
        if (typeof options === 'string') {
            options = {
                warningMessage: options
            };
        }
        
        // Ensure options is an object
        if (typeof options === 'function') {
            onConfirm = options;
            onCancel = arguments[2];
            options = {};
        }
        
        // Default options based on type
        var type = options.type || 'warning';
        var typeDefaults = {
            success: {
                icon: '✅',
                confirmText: i18n.confirm || 'Confirm',
                type: 'success'
            },
            danger: {
                icon: '🗑️',
                confirmText: 'Delete',
                type: 'danger'
            },
            warning: {
                icon: '⚠️',
                confirmText: i18n.confirm || 'Confirm',
                type: 'warning'
            }
        };
        
        var defaults = $.extend({
            warningMessage: '',
            title: i18n.confirmAction || 'Confirm Action',
            details: [],
            cancelText: i18n.cancel || 'Cancel',
            type: 'warning'
        }, typeDefaults[type] || {}, options);
        
        var $modal = $('#hrb-confirm-modal');
        
        // Ensure modal HTML exists
        if ($modal.length === 0) {
            // Create modal dynamically if it doesn't exist
            // var modalHtml = '<div id="hrb-confirm-modal">' +
            //     '<div class="hrb-confirm-content">' +
            //     '<div class="hrb-confirm-header">' +
            //     '<div class="hrb-confirm-icon"></div>' +
            //     '<h3 id="hrb-confirm-title"></h3>' +
            //     '</div>' +
            //     '<div class="hrb-confirm-body">' +
            //     '<div class="hrb-confirm-message" id="hrb-confirm-message"></div>' +
            //     '<div class="hrb-confirm-warning-message" id="hrb-confirm-warning-message"></div>' +
            //     '<div class="hrb-confirm-details 1" id="hrb-confirm-details"></div>' +
            //     '</div>' +
            //     '<div class="hrb-confirm-footer">' +
            //     '<button type="button" class="hrb-confirm-btn hrb-confirm-btn-cancel" id="hrb-confirm-cancel"></button>' +
            //     '<button type="button" class="hrb-confirm-btn hrb-confirm-btn-confirm" id="hrb-confirm-ok"></button>' +
            //     '</div>' +
            //     '</div>' +
            //     '</div>';
            // $('body').append(modalHtml);
            // $modal = $('#hrb-confirm-modal');
        }
        
        // Remove any existing type classes
        $modal.removeClass('type-success type-danger type-warning');
        
        // Add type class
        if (defaults.type) {
            $modal.addClass('type-' + defaults.type);
        }
        
        // Set content
        $('#hrb-confirm-title').text(defaults.title);
        $('#hrb-confirm-message').text(message || '');
        $('#hrb-confirm-warning-message').text(defaults.warningMessage || '').toggle(defaults.warningMessage.length > 0);
        $('.hrb-confirm-icon').text(defaults.icon);
        $('#hrb-confirm-ok').text(defaults.confirmText);
        $('#hrb-confirm-cancel').text(defaults.cancelText);
        
        // Build details HTML
        var detailsHtml = '';
        if (defaults.details && defaults.details.length > 0) {
            detailsHtml = '<div class="hrb-confirm-details">';
            defaults.details.forEach(function(detail) {
                var detailClass = detail.class || '';
                detailsHtml += '<div class="hrb-confirm-detail-row">' +
                    '<span class="hrb-confirm-detail-label">' + detail.label + '</span>' +
                    '<span class="hrb-confirm-detail-value ' + detailClass + '">' + detail.value + '</span>' +
                    '</div>';
            });
            detailsHtml += '</div>';
        }
        $('#hrb-confirm-details').html(detailsHtml);
        
        // Show modal
        $modal.addClass('show');
        
        // Handle confirm button
        $('#hrb-confirm-ok').off('click.alert').on('click.alert', function() {
            $modal.removeClass('show');
            $(document).off('keydown.alert-modal');
            if (typeof onConfirm === 'function') {
                onConfirm();
            }
        });
        
        // Handle cancel button
        $('#hrb-confirm-cancel').off('click.alert').on('click.alert', function() {
            $modal.removeClass('show');
            $(document).off('keydown.alert-modal');
            if (typeof onCancel === 'function') {
                onCancel();
            }
        });
        
        // Close on overlay click
        $modal.off('click.alert').on('click.alert', function(e) {
            if ($(e.target).is('#hrb-confirm-modal')) {
                $modal.removeClass('show');
                $(document).off('keydown.alert-modal');
                if (typeof onCancel === 'function') {
                    onCancel();
                }
            }
        });
        
        // Close on ESC key
        $(document).off('keydown.alert-modal').on('keydown.alert-modal', function(e) {
            if (e.key === 'Escape' && $modal.hasClass('show')) {
                $modal.removeClass('show');
                $(document).off('keydown.alert-modal');
                if (typeof onCancel === 'function') {
                    onCancel();
                }
            }
        });
    };
    
    /**
     * Backward compatibility - old function name for delete dialogs
     */
    window.hrbShowDeleteDialog = function(mainMessage, warningMessage, title, details, confirmText, cancelText, icon, onConfirm, onCancel) {
        window.hrbShowAlertDialog(mainMessage, {
            warningMessage: warningMessage,
            title: title,
            details: details,
            confirmText: confirmText,
            cancelText: cancelText,
            icon: icon,
            type: 'danger'
        }, onConfirm, onCancel);
    };

})(jQuery);
