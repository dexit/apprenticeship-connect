/**
 * Apprenticeship Connect - Frontend JavaScript
 * Handles search forms, edit forms, and AJAX interactions
 * @version 2.0.0
 */

(function($) {
    'use strict';

    var ApprcoFrontend = {
        /**
         * Configuration
         */
        config: {
            restBase: '/wp-json/apprco/v1',
            debounceDelay: 300
        },

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initSearchForms();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Search form submissions
            $(document).on('submit', '.apprco-search-form', this.handleSearchSubmit.bind(this));

            // Edit form submissions
            $(document).on('submit', '.apprco-edit-form', this.handleEditSubmit.bind(this));

            // Live search (with debounce)
            $(document).on('input', '.apprco-search-form input[type="text"]', this.debounce(this.handleLiveSearch.bind(this), this.config.debounceDelay));

            // Filter changes
            $(document).on('change', '.apprco-search-form select', this.handleFilterChange.bind(this));

            // Load more pagination
            $(document).on('click', '.apprco-load-more', this.handleLoadMore.bind(this));

            // Vacancy card interactions
            $(document).on('click', '.apprco-vacancy-item', this.handleVacancyClick.bind(this));
        },

        /**
         * Initialize search forms
         */
        initSearchForms: function() {
            $('.apprco-search-form').each(function() {
                var $form = $(this);

                // Check for URL parameters and populate form
                var urlParams = new URLSearchParams(window.location.search);
                urlParams.forEach(function(value, key) {
                    var $field = $form.find('[name="' + key + '"]');
                    if ($field.length) {
                        $field.val(value);
                    }
                });
            });
        },

        /**
         * Handle search form submission
         */
        handleSearchSubmit: function(e) {
            var $form = $(e.currentTarget);
            var useAjax = $form.data('ajax') === 'true';

            if (!useAjax) return true;

            e.preventDefault();
            this.performSearch($form);
        },

        /**
         * Handle live search
         */
        handleLiveSearch: function(e) {
            var $form = $(e.currentTarget).closest('.apprco-search-form');
            if ($form.data('ajax') !== 'true') return;

            this.performSearch($form);
        },

        /**
         * Handle filter change
         */
        handleFilterChange: function(e) {
            var $form = $(e.currentTarget).closest('.apprco-search-form');
            if ($form.data('ajax') !== 'true') return;

            this.performSearch($form);
        },

        /**
         * Perform AJAX search
         */
        performSearch: function($form) {
            var self = this;
            var resultsId = $form.data('results');
            var $results = resultsId ? $('#' + resultsId) : $form.siblings('.apprco-vacancy-list').first();

            if (!$results.length) {
                $results = $('.apprco-vacancy-list').first();
            }

            // Collect form data
            var formData = {};
            $form.serializeArray().forEach(function(item) {
                if (item.value) {
                    formData[item.name] = item.value;
                }
            });

            // Show loading state
            $form.addClass('loading');
            $results.addClass('loading');

            // Make API request
            $.ajax({
                url: apprcoFrontend.restUrl + 'vacancies/search',
                method: 'GET',
                data: formData,
                beforeSend: function(xhr) {
                    if (apprcoFrontend.nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', apprcoFrontend.nonce);
                    }
                },
                success: function(response) {
                    self.renderResults($results, response);
                    self.updateUrl(formData);
                },
                error: function(xhr) {
                    self.showError($results, xhr.responseJSON?.message || 'Search failed. Please try again.');
                },
                complete: function() {
                    $form.removeClass('loading');
                    $results.removeClass('loading');
                }
            });
        },

        /**
         * Render search results
         */
        renderResults: function($container, response) {
            var self = this;
            var vacancies = response.vacancies || [];

            if (vacancies.length === 0) {
                $container.html('<p class="apprco-no-results">' + (apprcoFrontend.strings?.noResults || 'No vacancies found.') + '</p>');
                return;
            }

            var html = '';
            vacancies.forEach(function(vacancy) {
                html += self.renderVacancyCard(vacancy);
            });

            // Add pagination info
            if (response.total_pages > 1) {
                html += '<div class="apprco-pagination">';
                html += '<span class="apprco-page-info">Page ' + response.page + ' of ' + response.total_pages + ' (' + response.total + ' results)</span>';
                if (response.page < response.total_pages) {
                    html += '<button type="button" class="apprco-load-more" data-page="' + (response.page + 1) + '">Load More</button>';
                }
                html += '</div>';
            }

            $container.html(html);
        },

        /**
         * Render a single vacancy card
         */
        renderVacancyCard: function(vacancy) {
            var meta = vacancy.meta || {};
            var html = '<div class="apprco-vacancy-item apprco-template-card" data-vacancy-id="' + vacancy.id + '">';

            html += '<div class="apprco-card-header">';
            if (meta.apprenticeship_level) {
                html += '<span class="apprco-level-badge">' + this.escapeHtml(meta.apprenticeship_level) + '</span>';
            }
            html += '<h3 class="apprco-card-title"><a href="' + vacancy.permalink + '">' + this.escapeHtml(vacancy.title) + '</a></h3>';
            html += '</div>';

            html += '<div class="apprco-card-body">';
            if (meta.employer_name) {
                html += '<p class="apprco-employer"><i class="dashicons dashicons-building"></i> ' + this.escapeHtml(meta.employer_name) + '</p>';
            }
            if (meta.postcode) {
                html += '<p class="apprco-location"><i class="dashicons dashicons-location"></i> ' + this.escapeHtml(meta.postcode) + '</p>';
            }
            if (meta.wage_amount) {
                html += '<p class="apprco-wage"><i class="dashicons dashicons-money-alt"></i> &pound;' + parseFloat(meta.wage_amount).toLocaleString('en-GB', {minimumFractionDigits: 2}) + '</p>';
            } else if (meta.wage_type) {
                html += '<p class="apprco-wage"><i class="dashicons dashicons-money-alt"></i> ' + this.escapeHtml(meta.wage_type) + '</p>';
            }
            html += '</div>';

            html += '<div class="apprco-card-footer">';
            if (meta.closing_date) {
                var closingDate = new Date(meta.closing_date);
                html += '<span class="apprco-closing">Closes: ' + closingDate.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'}) + '</span>';
            }
            html += '<a href="' + (meta.vacancy_url || vacancy.permalink) + '" class="apprco-card-link"' + (meta.vacancy_url ? ' target="_blank" rel="noopener"' : '') + '>View Details &raquo;</a>';
            html += '</div>';

            html += '</div>';

            return html;
        },

        /**
         * Handle load more button
         */
        handleLoadMore: function(e) {
            e.preventDefault();
            var $button = $(e.currentTarget);
            var page = $button.data('page');
            var $container = $button.closest('.apprco-vacancy-list');
            var $form = $('.apprco-search-form').first();

            // Add page to form data
            var formData = {};
            $form.serializeArray().forEach(function(item) {
                if (item.value) {
                    formData[item.name] = item.value;
                }
            });
            formData.page = page;

            var self = this;
            $button.prop('disabled', true).text('Loading...');

            $.ajax({
                url: apprcoFrontend.restUrl + 'vacancies/search',
                method: 'GET',
                data: formData,
                beforeSend: function(xhr) {
                    if (apprcoFrontend.nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', apprcoFrontend.nonce);
                    }
                },
                success: function(response) {
                    var vacancies = response.vacancies || [];
                    var html = '';

                    vacancies.forEach(function(vacancy) {
                        html += self.renderVacancyCard(vacancy);
                    });

                    // Remove old pagination
                    $container.find('.apprco-pagination').remove();

                    // Append new results
                    $container.append(html);

                    // Add new pagination if needed
                    if (response.page < response.total_pages) {
                        var paginationHtml = '<div class="apprco-pagination">';
                        paginationHtml += '<span class="apprco-page-info">Page ' + response.page + ' of ' + response.total_pages + '</span>';
                        paginationHtml += '<button type="button" class="apprco-load-more" data-page="' + (response.page + 1) + '">Load More</button>';
                        paginationHtml += '</div>';
                        $container.append(paginationHtml);
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text('Load More');
                }
            });
        },

        /**
         * Handle edit form submission
         */
        handleEditSubmit: function(e) {
            e.preventDefault();

            var self = this;
            var $form = $(e.currentTarget);
            var $status = $form.find('.apprco-form-status');
            var $submit = $form.find('.apprco-submit-button');
            var postId = $form.data('post-id');
            var redirect = $form.data('redirect');

            // Collect form data
            var formData = {
                title: $form.find('[name="post_title"]').val(),
                meta: {}
            };

            $form.find('[name^="meta["]').each(function() {
                var name = $(this).attr('name').match(/meta\[([^\]]+)\]/)[1];
                var value = $(this).attr('type') === 'checkbox' ? ($(this).is(':checked') ? '1' : '0') : $(this).val();
                formData.meta[name] = value;
            });

            // Show loading state
            $submit.prop('disabled', true);
            $status.text(apprcoFrontend.strings?.saving || 'Saving...').removeClass('error success');

            // Determine endpoint and method
            var endpoint = postId ? 'vacancy/' + postId : 'vacancy';
            var method = postId ? 'PUT' : 'POST';

            $.ajax({
                url: apprcoFrontend.restUrl + endpoint,
                method: method,
                data: JSON.stringify(formData),
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', apprcoFrontend.nonce);
                },
                success: function(response) {
                    $status.text(apprcoFrontend.strings?.saved || 'Saved!').addClass('success');

                    // Update form with new post ID if created
                    if (response.id && !postId) {
                        $form.data('post-id', response.id);
                        $form.find('[name="vacancy_id"]').val(response.id);
                    }

                    // Redirect if specified
                    if (redirect) {
                        setTimeout(function() {
                            window.location.href = redirect.replace('{id}', response.id || postId);
                        }, 1000);
                    }
                },
                error: function(xhr) {
                    var message = xhr.responseJSON?.message || 'Failed to save. Please try again.';
                    $status.text(message).addClass('error');
                },
                complete: function() {
                    $submit.prop('disabled', false);
                }
            });
        },

        /**
         * Handle vacancy card click
         */
        handleVacancyClick: function(e) {
            // Don't trigger if clicking a link
            if ($(e.target).is('a') || $(e.target).closest('a').length) {
                return;
            }

            var $card = $(e.currentTarget);
            var $link = $card.find('.apprco-card-title a').first();

            if ($link.length) {
                window.location.href = $link.attr('href');
            }
        },

        /**
         * Update URL with search parameters
         */
        updateUrl: function(params) {
            var url = new URL(window.location);

            Object.keys(params).forEach(function(key) {
                if (params[key]) {
                    url.searchParams.set(key, params[key]);
                } else {
                    url.searchParams.delete(key);
                }
            });

            window.history.replaceState({}, '', url);
        },

        /**
         * Show error message
         */
        showError: function($container, message) {
            $container.html('<p class="apprco-error">' + this.escapeHtml(message) + '</p>');
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Debounce function
         */
        debounce: function(func, wait) {
            var timeout;
            return function() {
                var context = this;
                var args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    func.apply(context, args);
                }, wait);
            };
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        ApprcoFrontend.init();
    });

    // Expose for external use
    window.ApprcoFrontend = ApprcoFrontend;

})(jQuery);
