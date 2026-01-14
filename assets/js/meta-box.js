/**
 * Apprenticeship Connect - Meta Box JavaScript
 * @version 2.0.0
 */

(function($) {
    'use strict';

    var ApprcoMetaBox = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initTabs();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Tab switching
            $(document).on('click', '.apprco-meta-tab', this.handleTabClick.bind(this));

            // Auto-save on blur (optional enhancement)
            $(document).on('change', '.apprco-meta-content input, .apprco-meta-content select, .apprco-meta-content textarea', this.handleFieldChange.bind(this));

            // Date field validation
            $(document).on('change', 'input[type="date"]', this.validateDate.bind(this));

            // URL field validation
            $(document).on('blur', 'input[type="url"]', this.validateUrl.bind(this));

            // Postcode lookup (if coordinates empty)
            $(document).on('blur', '#apprco_field__apprco_postcode', this.handlePostcodeLookup.bind(this));
        },

        /**
         * Initialize tabs on load
         */
        initTabs: function() {
            var $wrapper = $('.apprco-meta-box-wrapper');
            if (!$wrapper.length) return;

            // Restore last active tab from localStorage
            var savedTab = localStorage.getItem('apprco_active_tab');
            if (savedTab) {
                var $tab = $wrapper.find('.apprco-meta-tab[data-tab="' + savedTab + '"]');
                if ($tab.length) {
                    this.activateTab($tab);
                }
            }
        },

        /**
         * Handle tab click
         */
        handleTabClick: function(e) {
            e.preventDefault();
            var $tab = $(e.currentTarget);
            this.activateTab($tab);
        },

        /**
         * Activate a tab
         */
        activateTab: function($tab) {
            var tabId = $tab.data('tab');
            var $wrapper = $tab.closest('.apprco-meta-box-wrapper');

            // Update tab states
            $wrapper.find('.apprco-meta-tab').removeClass('active');
            $tab.addClass('active');

            // Update panel states
            $wrapper.find('.apprco-meta-panel').removeClass('active');
            $wrapper.find('.apprco-meta-panel[data-panel="' + tabId + '"]').addClass('active');

            // Save to localStorage
            localStorage.setItem('apprco_active_tab', tabId);

            // Re-init any WYSIWYG editors in the newly visible panel
            this.reinitEditors($wrapper.find('.apprco-meta-panel[data-panel="' + tabId + '"]'));
        },

        /**
         * Reinitialize TinyMCE editors
         */
        reinitEditors: function($panel) {
            $panel.find('.wp-editor-area').each(function() {
                var editorId = $(this).attr('id');
                if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                    tinymce.get(editorId).show();
                }
            });
        },

        /**
         * Handle field change
         */
        handleFieldChange: function(e) {
            var $field = $(e.currentTarget);
            var $wrapper = $field.closest('.apprco-field');

            // Remove any previous error state
            $wrapper.removeClass('error');

            // Mark field as modified
            $field.addClass('modified');
        },

        /**
         * Validate date field
         */
        validateDate: function(e) {
            var $field = $(e.currentTarget);
            var $wrapper = $field.closest('.apprco-field');
            var value = $field.val();

            if (value) {
                var date = new Date(value);
                if (isNaN(date.getTime())) {
                    $wrapper.addClass('error');
                    return false;
                }
            }

            $wrapper.removeClass('error');
            return true;
        },

        /**
         * Validate URL field
         */
        validateUrl: function(e) {
            var $field = $(e.currentTarget);
            var $wrapper = $field.closest('.apprco-field');
            var value = $field.val();

            if (value && !this.isValidUrl(value)) {
                $wrapper.addClass('error');
                return false;
            }

            $wrapper.removeClass('error');
            return true;
        },

        /**
         * Check if URL is valid
         */
        isValidUrl: function(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        },

        /**
         * Handle postcode lookup for geocoding
         */
        handlePostcodeLookup: function(e) {
            var $field = $(e.currentTarget);
            var postcode = $field.val().trim();

            if (!postcode) return;

            var $latField = $('#apprco_field__apprco_latitude');
            var $lngField = $('#apprco_field__apprco_longitude');

            // Only lookup if lat/lng are empty
            if ($latField.val() || $lngField.val()) return;

            // Use postcodes.io API (free, no auth required)
            var $wrapper = $field.closest('.apprco-field');
            $wrapper.addClass('loading');

            $.ajax({
                url: 'https://api.postcodes.io/postcodes/' + encodeURIComponent(postcode),
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 200 && response.result) {
                        $latField.val(response.result.latitude);
                        $lngField.val(response.result.longitude);

                        // Also populate town if empty
                        var $townField = $('#apprco_field__apprco_town');
                        if (!$townField.val() && response.result.admin_district) {
                            $townField.val(response.result.admin_district);
                        }

                        // Populate county if empty
                        var $countyField = $('#apprco_field__apprco_county');
                        if (!$countyField.val() && response.result.admin_county) {
                            $countyField.val(response.result.admin_county);
                        }
                    }
                },
                error: function() {
                    // Silently fail - postcode lookup is optional
                },
                complete: function() {
                    $wrapper.removeClass('loading');
                }
            });
        },

        /**
         * Show notification
         */
        showNotice: function(message, type) {
            type = type || 'info';
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

            $('.apprco-meta-box-wrapper').before($notice);

            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        ApprcoMetaBox.init();
    });

    // Expose for external use
    window.ApprcoMetaBox = ApprcoMetaBox;

})(jQuery);
