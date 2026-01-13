/**
 * Apprenticeship Connect Admin JavaScript
 * @version 2.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    // Manual sync functionality
    $('#apprco-manual-sync').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var originalText = $button.text();

        $button.prop('disabled', true).text(apprcoAjax.strings.syncing);

        $.ajax({
            url: apprcoAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'apprco_manual_sync',
                nonce: apprcoAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotice(response.data, 'error');
                }
            },
            error: function() {
                showNotice(apprcoAjax.strings.error, 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Dashboard sync button
    $('#apprco-dashboard-sync').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var originalText = $button.text();
        var $result = $('#apprco-dashboard-result');

        $button.prop('disabled', true).text(apprcoAjax.strings.syncing);
        $result.html('<span class="apprco-loading">' + apprcoAjax.strings.loading + '</span>');

        $.ajax({
            url: apprcoAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'apprco_manual_sync',
                nonce: apprcoAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('error').addClass('success').html(response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $result.removeClass('success').addClass('error').html(response.data);
                }
            },
            error: function() {
                $result.removeClass('success').addClass('error').html(apprcoAjax.strings.error);
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Test API functionality
    $('#apprco-test-api').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var originalText = $button.text();

        $button.prop('disabled', true).text(apprcoAjax.strings.testing);

        $.ajax({
            url: apprcoAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'apprco_test_api',
                nonce: apprcoAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data, 'success');
                } else {
                    showNotice(response.data, 'error');
                }
            },
            error: function() {
                showNotice(apprcoAjax.strings.error, 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Test & Sync API functionality
    $(document).on('click', '#apprco-test-and-sync', function(e) {
        e.preventDefault();

        var $button = $(this);
        var originalText = $button.text();
        var $result = $('#apprco-test-sync-result');

        var apiBaseUrl = $('#api_base_url').val();
        var apiKey = $('#api_subscription_key').val();
        var ukprn = $('#api_ukprn').val();

        $button.prop('disabled', true).text('Testing & Syncing...');
        $result.html('<p style="color: #2271b1;">Testing API connection and syncing vacancies...</p>');

        $.ajax({
            url: apprcoAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'apprco_test_and_sync',
                nonce: apprcoAjax.nonce,
                api_base_url: apiBaseUrl,
                api_subscription_key: apiKey,
                api_ukprn: ukprn
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('error').addClass('success').html('<p>' + response.data.message + '</p>');
                    $('#apprco-last-sync').text(response.data.last_sync);
                    $('#apprco-total-vacancies').text(response.data.total_vacancies);

                    // Save API settings
                    $.ajax({
                        url: apprcoAjax.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'apprco_save_api_settings',
                            nonce: apprcoAjax.nonce,
                            api_base_url: apiBaseUrl,
                            api_subscription_key: apiKey,
                            api_ukprn: ukprn
                        }
                    });
                } else {
                    $result.removeClass('success').addClass('error').html('<p>Error: ' + response.data + '</p>');
                }
            },
            error: function() {
                $result.removeClass('success').addClass('error').html('<p>Error: ' + apprcoAjax.strings.error + '</p>');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Clear cache button
    $('#apprco-clear-cache').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var originalText = $button.text();

        $button.prop('disabled', true).text('Clearing...');

        $.ajax({
            url: apprcoAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'apprco_clear_cache',
                nonce: apprcoAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data, 'success');
                } else {
                    showNotice(response.data, 'error');
                }
            },
            error: function() {
                showNotice(apprcoAjax.strings.error, 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Logs page functionality
    if ($('.apprco-logs').length) {
        // View logs for import
        $(document).on('click', '.apprco-view-logs', function(e) {
            e.preventDefault();

            var importId = $(this).data('import-id');
            var $details = $('#apprco-log-details');
            var $entries = $('#apprco-log-entries');

            $entries.html('<p>' + apprcoAjax.strings.loading + '</p>');
            $details.show();
            $('#apprco-log-import-id').text(importId);

            $.ajax({
                url: apprcoAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'apprco_get_logs',
                    nonce: apprcoAjax.nonce,
                    import_id: importId
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        var html = '';
                        $.each(response.data, function(i, log) {
                            html += '<div class="apprco-log-entry ' + log.log_level + '">';
                            html += '<span class="log-time">' + log.created_at + '</span>';
                            html += '<span class="log-level">' + log.log_level + '</span>';
                            html += '<span class="log-message">' + log.message + '</span>';
                            html += '</div>';
                        });
                        $entries.html(html);
                    } else {
                        $entries.html('<p>No log entries found for this import.</p>');
                    }
                },
                error: function() {
                    $entries.html('<p>Error loading logs.</p>');
                }
            });
        });

        // Close logs panel
        $('#apprco-close-logs').on('click', function() {
            $('#apprco-log-details').hide();
        });

        // Refresh logs
        $('#apprco-refresh-logs').on('click', function() {
            location.reload();
        });

        // Export logs
        $('#apprco-export-logs').on('click', function(e) {
            e.preventDefault();

            $.ajax({
                url: apprcoAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'apprco_export_logs',
                    nonce: apprcoAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Create download link
                        var blob = new Blob([response.data.csv], { type: 'text/csv' });
                        var url = window.URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = 'apprco-logs-' + new Date().toISOString().slice(0, 10) + '.csv';
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                    }
                }
            });
        });

        // Clear all logs
        $('#apprco-clear-logs').on('click', function(e) {
            e.preventDefault();

            if (!confirm(apprcoAjax.strings.confirm_clear)) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true);

            $.ajax({
                url: apprcoAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'apprco_clear_logs',
                    nonce: apprcoAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotice(response.data, 'error');
                    }
                },
                error: function() {
                    showNotice(apprcoAjax.strings.error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
    }

    // Show notice function
    function showNotice(message, type) {
        var noticeClass = 'apprco-notice apprco-notice-' + type;
        var $notice = $('<div class="' + noticeClass + '">' + message + '</div>');

        $('.apprco-notice').remove();
        $('.wrap h1').first().after($notice);

        // Auto-hide after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Setup wizard functionality
    if ($('.apprco-setup-progress').length) {
        // Auto-save form data
        $('.apprco-setup-step input, .apprco-setup-step select, .apprco-setup-step textarea').on('change', function() {
            var $form = $(this).closest('form');
            var formData = $form.serialize();
            localStorage.setItem('apprco_setup_form_data', formData);
        });

        // Restore form data
        var savedData = localStorage.getItem('apprco_setup_form_data');
        if (savedData) {
            var $form = $('.apprco-setup-step form');
            var params = new URLSearchParams(savedData);

            params.forEach(function(value, key) {
                if (key === 'step' || key === 'apprco_setup_nonce' || key === '_wp_http_referer') {
                    return;
                }

                var $field = $form.find('[name="' + key + '"]');
                if ($field.length) {
                    if ($field.attr('type') === 'checkbox') {
                        $field.prop('checked', value === '1');
                    } else {
                        $field.val(value);
                    }
                }
            });
        }

        // Clear saved data when complete
        if (window.location.search.includes('step=5')) {
            localStorage.removeItem('apprco_setup_form_data');
        }
    }

    // Form validation
    $('form').on('submit', function(e) {
        var $form = $(this);
        var $requiredFields = $form.find('[required]');
        var isValid = true;

        $requiredFields.each(function() {
            var $field = $(this);
            var value = $field.val();

            if (!value || value.trim() === '') {
                $field.addClass('error');
                isValid = false;
            } else {
                $field.removeClass('error');
            }
        });

        if (!isValid) {
            e.preventDefault();
            showNotice('Please fill in all required fields.', 'error');
        }
    });

    $('input, select, textarea').on('input change', function() {
        $(this).removeClass('error');
    });

    // Copy shortcode to clipboard
    $('.apprco-shortcode-inline code').on('click', function() {
        var text = $(this).text();

        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                showCopiedFeedback(text);
            });
        } else {
            var $textarea = $('<textarea>').val(text).appendTo('body');
            $textarea.select();
            document.execCommand('copy');
            $textarea.remove();
            showCopiedFeedback(text);
        }

        function showCopiedFeedback(code) {
            var $code = $('.apprco-shortcode-inline code');
            var originalText = $code.text();
            $code.text('Copied!').css('background', '#46b450').css('color', '#fff');

            setTimeout(function() {
                $code.text(originalText).css('background', '').css('color', '');
            }, 2000);
        }
    });

    // Setup wizard: toggle page fields
    $(document).on('change', '#create_page', function() {
        var checked = $(this).is(':checked');
        $('#page_title, #page_slug').prop('disabled', !checked);
    });

    if ($('#create_page').length) {
        var checked = $('#create_page').is(':checked');
        $('#page_title, #page_slug').prop('disabled', !checked);
    }

    // Media uploader for no vacancy image
    if ($('#no_vacancy_image_button').length) {
        $('#no_vacancy_image_button').on('click', function(e) {
            e.preventDefault();

            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert('Media uploader not available');
                return;
            }

            var $input = $('#no_vacancy_image');

            var mediaFrame = wp.media({
                title: 'Select No Vacancy Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });

            mediaFrame.on('select', function() {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                $input.val(attachment.url);
            });

            mediaFrame.open();
        });
    }

    // Toggle no vacancy image fields
    function toggleNoVacancyImageFields() {
        var $checkbox = $('#show_no_vacancy_image');
        var $input = $('#no_vacancy_image');
        var $button = $('#no_vacancy_image_button');

        if ($checkbox.length) {
            if ($checkbox.is(':checked')) {
                $input.prop('disabled', false);
                $button.prop('disabled', false);
            } else {
                $input.prop('disabled', true);
                $button.prop('disabled', true);
            }
        }
    }

    $('#show_no_vacancy_image').on('change', toggleNoVacancyImageFields);
    toggleNoVacancyImageFields();

    // Confirm before leaving setup wizard
    if ($('.apprco-setup-step form').length) {
        window.onbeforeunload = function() {
            return 'Are you sure you want to leave? Your progress will be saved.';
        };

        $('form').on('submit', function() {
            window.onbeforeunload = null;
        });

        $('.apprco-setup-actions a').on('click', function() {
            window.onbeforeunload = null;
        });
    }
});
