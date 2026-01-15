/**
 * Import Wizard JavaScript
 *
 * Multi-step import wizard with connection test, preview, and execution.
 *
 * @package ApprenticeshipConnect
 * @since   2.1.0
 */

(function($) {
    'use strict';

    // Wizard state
    const state = {
        currentStep: 'connect',
        providerId: '',
        config: {},
        params: {},
        importId: null,
        polling: null,
    };

    // DOM elements
    let $wizard, $steps, $content, $prevBtn, $nextBtn, $statusBar;

    /**
     * Initialize wizard
     */
    function init() {
        $wizard = $('#apprco-import-wizard');
        if (!$wizard.length) return;

        $steps = $wizard.find('.wizard-steps');
        $content = $wizard.find('.wizard-content');
        $prevBtn = $wizard.find('.wizard-prev');
        $nextBtn = $wizard.find('.wizard-next');
        $statusBar = $wizard.find('.wizard-status');

        bindEvents();
        renderStep('connect');
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Navigation buttons
        $prevBtn.on('click', goToPreviousStep);
        $nextBtn.on('click', goToNextStep);

        // Provider selection
        $content.on('change', '#wizard-provider', function() {
            state.providerId = $(this).val();
            updateProviderConfig();
        });

        // Test connection button
        $content.on('click', '#btn-test-connection', testConnection);

        // Execute import button
        $content.on('click', '#btn-execute-import', executeImport);

        // Cancel import button
        $content.on('click', '#btn-cancel-import', cancelImport);
    }

    /**
     * Render current step
     */
    function renderStep(step) {
        state.currentStep = step;

        // Update step indicators
        $steps.find('.step').removeClass('active completed');
        let found = false;
        $steps.find('.step').each(function() {
            if ($(this).data('step') === step) {
                $(this).addClass('active');
                found = true;
            } else if (!found) {
                $(this).addClass('completed');
            }
        });

        // Render step content
        switch (step) {
            case 'connect':
                renderConnectStep();
                break;
            case 'configure':
                renderConfigureStep();
                break;
            case 'preview':
                renderPreviewStep();
                break;
            case 'execute':
                renderExecuteStep();
                break;
        }

        updateNavButtons();
    }

    /**
     * Render connection step
     */
    function renderConnectStep() {
        const providers = apprcoWizard.providers || {};
        let html = `
            <div class="wizard-step-content">
                <h3>${apprcoWizard.strings.selectProvider || 'Select Provider'}</h3>
                <p>Choose a data provider and enter your API credentials to test the connection.</p>

                <div class="form-group">
                    <label for="wizard-provider">Provider</label>
                    <select id="wizard-provider" class="regular-text">
                        <option value="">-- Select Provider --</option>
        `;

        for (const [id, info] of Object.entries(providers)) {
            html += `<option value="${id}">${info.name}</option>`;
        }

        html += `
                    </select>
                </div>

                <div id="provider-config"></div>

                <div class="form-actions">
                    <button type="button" id="btn-test-connection" class="button button-primary" disabled>
                        ${apprcoWizard.strings.testing || 'Test Connection'}
                    </button>
                    <span id="connection-status"></span>
                </div>
            </div>
        `;

        $content.html(html);
    }

    /**
     * Update provider configuration form
     */
    function updateProviderConfig() {
        const $config = $('#provider-config');
        const $btn = $('#btn-test-connection');
        const provider = apprcoWizard.providers[state.providerId];

        if (!provider) {
            $config.empty();
            $btn.prop('disabled', true);
            return;
        }

        // Get current options from WordPress
        let html = '<div class="provider-config-fields">';

        // Subscription key
        html += `
            <div class="form-group">
                <label for="config-subscription-key">Subscription Key *</label>
                <input type="text" id="config-subscription-key" name="subscription_key"
                       class="regular-text" placeholder="Ocp-Apim-Subscription-Key"
                       value="${state.config.subscription_key || ''}">
                <p class="description">Your API subscription key from the management portal.</p>
            </div>
        `;

        // Base URL
        html += `
            <div class="form-group">
                <label for="config-base-url">Base URL</label>
                <input type="url" id="config-base-url" name="base_url"
                       class="regular-text"
                       value="${state.config.base_url || provider.base_url || ''}">
            </div>
        `;

        // UKPRN
        html += `
            <div class="form-group">
                <label for="config-ukprn">UKPRN (optional)</label>
                <input type="text" id="config-ukprn" name="ukprn"
                       class="regular-text" placeholder="e.g., 10000001"
                       value="${state.config.ukprn || ''}">
                <p class="description">UK Provider Reference Number for filtering results.</p>
            </div>
        `;

        html += '</div>';
        $config.html(html);
        $btn.prop('disabled', false);

        // Bind config input changes
        $config.find('input').on('change', function() {
            state.config[$(this).attr('name')] = $(this).val();
        });
    }

    /**
     * Test connection
     */
    function testConnection() {
        const $btn = $('#btn-test-connection');
        const $status = $('#connection-status');

        // Gather config
        state.config = {
            subscription_key: $('#config-subscription-key').val(),
            base_url: $('#config-base-url').val(),
            ukprn: $('#config-ukprn').val(),
        };

        $btn.prop('disabled', true).text(apprcoWizard.strings.testing);
        $status.removeClass('success error').text('');

        $.ajax({
            url: apprcoWizard.ajaxUrl,
            method: 'POST',
            data: {
                action: 'apprco_wizard_test_connection',
                nonce: apprcoWizard.nonce,
                provider_id: state.providerId,
                config: state.config,
            },
            success: function(response) {
                if (response.success) {
                    $status.addClass('success').html(
                        '<span class="dashicons dashicons-yes-alt"></span> ' +
                        (response.data.message || apprcoWizard.strings.connected)
                    );
                    $nextBtn.prop('disabled', false);
                } else {
                    $status.addClass('error').html(
                        '<span class="dashicons dashicons-warning"></span> ' +
                        (response.data || apprcoWizard.strings.failed)
                    );
                }
            },
            error: function() {
                $status.addClass('error').text(apprcoWizard.strings.error);
            },
            complete: function() {
                $btn.prop('disabled', false).text('Test Connection');
            }
        });
    }

    /**
     * Render configure step
     */
    function renderConfigureStep() {
        const html = `
            <div class="wizard-step-content">
                <h3>Configure Import</h3>
                <p>Set import parameters and filters.</p>

                <div class="form-group">
                    <label for="param-page-size">Page Size</label>
                    <input type="number" id="param-page-size" name="PageSize"
                           class="small-text" value="100" min="10" max="100">
                    <p class="description">Number of vacancies to fetch per page (max 100).</p>
                </div>

                <div class="form-group">
                    <label for="param-sort">Sort Order</label>
                    <select id="param-sort" name="Sort" class="regular-text">
                        <option value="AgeDesc">Newest First</option>
                        <option value="AgeAsc">Oldest First</option>
                        <option value="DistanceAsc">Distance (requires postcode)</option>
                        <option value="ClosingDateAsc">Closing Soon First</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="param-geocode" checked>
                        Enable geocoding (lookup coordinates for postcodes)
                    </label>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="param-store-employers" checked>
                        Store employer data for future use
                    </label>
                </div>
            </div>
        `;

        $content.html(html);
    }

    /**
     * Render preview step
     */
    function renderPreviewStep() {
        // Gather params
        state.params = {
            PageSize: $('#param-page-size').val() || 100,
            Sort: $('#param-sort').val() || 'AgeDesc',
        };

        $content.html(`
            <div class="wizard-step-content">
                <h3>Preview Vacancies</h3>
                <p>Showing first 10 vacancies from the API.</p>
                <div id="preview-loading" class="loading">
                    <span class="spinner is-active"></span>
                    ${apprcoWizard.strings.loading}
                </div>
                <div id="preview-results"></div>
            </div>
        `);

        loadPreview();
    }

    /**
     * Load preview data
     */
    function loadPreview() {
        $.ajax({
            url: apprcoWizard.ajaxUrl,
            method: 'POST',
            data: {
                action: 'apprco_wizard_preview',
                nonce: apprcoWizard.nonce,
                provider_id: state.providerId,
                params: state.params,
                limit: 10,
            },
            success: function(response) {
                $('#preview-loading').hide();

                if (response.success && response.data.vacancies) {
                    renderPreviewResults(response.data);
                } else {
                    $('#preview-results').html(
                        '<div class="notice notice-error"><p>' +
                        (response.data || apprcoWizard.strings.noVacancies) +
                        '</p></div>'
                    );
                }
            },
            error: function() {
                $('#preview-loading').hide();
                $('#preview-results').html(
                    '<div class="notice notice-error"><p>' +
                    apprcoWizard.strings.error +
                    '</p></div>'
                );
            }
        });
    }

    /**
     * Render preview results
     */
    function renderPreviewResults(data) {
        let html = `
            <div class="preview-summary">
                <strong>Total Available:</strong> ${data.total.toLocaleString()} vacancies
                <br>
                <strong>Showing:</strong> ${data.preview_count} samples
            </div>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Employer</th>
                        <th>Location</th>
                        <th>Level</th>
                        <th>Closing</th>
                    </tr>
                </thead>
                <tbody>
        `;

        data.vacancies.forEach(v => {
            html += `
                <tr>
                    <td><strong>${escapeHtml(v.title)}</strong></td>
                    <td>${escapeHtml(v.employer)}</td>
                    <td>
                        ${escapeHtml(v.location)}
                        ${v.has_coordinates ? '<span class="dashicons dashicons-location" title="Has coordinates"></span>' : ''}
                    </td>
                    <td>${escapeHtml(v.level)}</td>
                    <td>${v.closing_date ? formatDate(v.closing_date) : '-'}</td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        $('#preview-results').html(html);
    }

    /**
     * Render execute step
     */
    function renderExecuteStep() {
        $content.html(`
            <div class="wizard-step-content">
                <h3>Execute Import</h3>
                <p>Ready to import vacancies. This may take several minutes for large datasets.</p>

                <div class="import-summary">
                    <p><strong>Provider:</strong> ${apprcoWizard.providers[state.providerId]?.name || state.providerId}</p>
                    <p><strong>Configuration:</strong> Page size ${state.params.PageSize}, Sort: ${state.params.Sort}</p>
                </div>

                <div class="form-actions">
                    <button type="button" id="btn-execute-import" class="button button-primary button-hero">
                        <span class="dashicons dashicons-download"></span>
                        Start Import
                    </button>
                    <button type="button" id="btn-cancel-import" class="button" style="display:none;">
                        Cancel Import
                    </button>
                </div>

                <div id="import-progress" style="display:none;">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="progress-text"></div>
                </div>

                <div id="import-result" style="display:none;"></div>
            </div>
        `);
    }

    /**
     * Execute import
     */
    function executeImport() {
        if (!confirm(apprcoWizard.strings.confirmStart)) {
            return;
        }

        const $btn = $('#btn-execute-import');
        const $cancel = $('#btn-cancel-import');
        const $progress = $('#import-progress');
        const $result = $('#import-result');

        $btn.prop('disabled', true).html(
            '<span class="spinner is-active"></span> ' + apprcoWizard.strings.importing
        );
        $cancel.show();
        $progress.show();
        $result.hide();

        $.ajax({
            url: apprcoWizard.ajaxUrl,
            method: 'POST',
            timeout: 600000, // 10 minutes
            data: {
                action: 'apprco_wizard_execute',
                nonce: apprcoWizard.nonce,
                provider_id: state.providerId,
                params: state.params,
            },
            success: function(response) {
                stopPolling();
                $cancel.hide();

                if (response.success) {
                    updateProgress(100, 'Complete!');
                    showResult(response.data);
                } else {
                    showError(response.data || apprcoWizard.strings.error);
                }
            },
            error: function(xhr, status) {
                stopPolling();
                $cancel.hide();

                if (status === 'timeout') {
                    showError('Import timed out. Check the logs for details.');
                } else {
                    showError(apprcoWizard.strings.error);
                }
            },
            complete: function() {
                $btn.prop('disabled', false).html(
                    '<span class="dashicons dashicons-download"></span> Start Import'
                );
            }
        });

        // Start polling for status
        if (state.importId) {
            startPolling();
        }
    }

    /**
     * Cancel import
     */
    function cancelImport() {
        if (!confirm(apprcoWizard.strings.confirmCancel)) {
            return;
        }

        stopPolling();

        $.ajax({
            url: apprcoWizard.ajaxUrl,
            method: 'POST',
            data: {
                action: 'apprco_wizard_cancel',
                nonce: apprcoWizard.nonce,
                import_id: state.importId,
            }
        });

        $('#btn-cancel-import').hide();
        showError(apprcoWizard.strings.cancelled);
    }

    /**
     * Start polling for import status
     */
    function startPolling() {
        state.polling = setInterval(pollStatus, 2000);
    }

    /**
     * Stop polling
     */
    function stopPolling() {
        if (state.polling) {
            clearInterval(state.polling);
            state.polling = null;
        }
    }

    /**
     * Poll import status
     */
    function pollStatus() {
        if (!state.importId) return;

        $.ajax({
            url: apprcoWizard.ajaxUrl,
            method: 'POST',
            data: {
                action: 'apprco_wizard_get_status',
                nonce: apprcoWizard.nonce,
                import_id: state.importId,
            },
            success: function(response) {
                if (response.success && response.data) {
                    const data = response.data;
                    const total = data.total || 0;
                    const current = data.current || 0;
                    const percent = total > 0 ? Math.round((current / total) * 100) : 0;

                    updateProgress(percent, `${current} / ${total} processed`);

                    if (data.status === 'completed' || data.status === 'failed' || data.status === 'cancelled') {
                        stopPolling();
                    }
                }
            }
        });
    }

    /**
     * Update progress bar
     */
    function updateProgress(percent, text) {
        $('#import-progress .progress-fill').css('width', percent + '%');
        $('#import-progress .progress-text').text(text);
    }

    /**
     * Show import result
     */
    function showResult(data) {
        let html = `
            <div class="notice notice-success">
                <p><strong>${apprcoWizard.strings.complete}</strong></p>
                <ul>
                    <li><strong>Imported:</strong> ${data.imported}</li>
                    <li><strong>Skipped:</strong> ${data.skipped}</li>
                    <li><strong>Errors:</strong> ${data.errors}</li>
                    <li><strong>Total Processed:</strong> ${data.total}</li>
                </ul>
                <p>
                    <a href="${adminUrl}edit.php?post_type=apprco_vacancy" class="button">
                        View Vacancies
                    </a>
                </p>
            </div>
        `;
        $('#import-result').html(html).show();
    }

    /**
     * Show error
     */
    function showError(message) {
        $('#import-result').html(
            '<div class="notice notice-error"><p>' + escapeHtml(message) + '</p></div>'
        ).show();
    }

    /**
     * Navigation
     */
    function goToPreviousStep() {
        const steps = Object.keys(apprcoWizard.steps);
        const currentIndex = steps.indexOf(state.currentStep);
        if (currentIndex > 0) {
            renderStep(steps[currentIndex - 1]);
        }
    }

    function goToNextStep() {
        const steps = Object.keys(apprcoWizard.steps);
        const currentIndex = steps.indexOf(state.currentStep);
        if (currentIndex < steps.length - 1) {
            renderStep(steps[currentIndex + 1]);
        }
    }

    function updateNavButtons() {
        const steps = Object.keys(apprcoWizard.steps);
        const currentIndex = steps.indexOf(state.currentStep);

        $prevBtn.prop('disabled', currentIndex === 0);
        $nextBtn.prop('disabled', currentIndex === steps.length - 1);

        // Disable next until connection tested on first step
        if (state.currentStep === 'connect') {
            $nextBtn.prop('disabled', !state.providerId);
        }
    }

    /**
     * Utility functions
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        return date.toLocaleDateString();
    }

    // Admin URL
    const adminUrl = apprcoWizard.ajaxUrl.replace('admin-ajax.php', '');

    // Initialize on DOM ready
    $(document).ready(init);

})(jQuery);
