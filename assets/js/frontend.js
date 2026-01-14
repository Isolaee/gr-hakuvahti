/**
 * ACF Analyzer Frontend JavaScript - Pop-up Search
 */

(function($) {
    'use strict';

    // Pop-up controller
    const ACFPopup = {
        init: function() {
            this.bindEvents();
            this.criteriaCount = 0;
        },

        bindEvents: function() {
            // Open popup
            $(document).on('click', '.acf-popup-trigger', this.openPopup.bind(this));
            
            // Close popup
            $(document).on('click', '.acf-popup-close', this.closePopup.bind(this));
            $(document).on('click', '.acf-popup-overlay', function(e) {
                if ($(e.target).hasClass('acf-popup-overlay')) {
                    ACFPopup.closePopup();
                }
            });
            
            // ESC key to close
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('.acf-popup-overlay').hasClass('active')) {
                    ACFPopup.closePopup();
                }
            });

            // Add criteria
            $(document).on('click', '.acf-add-criteria', this.addCriteria.bind(this));
            
            // Remove criteria
            $(document).on('click', '.acf-criteria-remove', this.removeCriteria.bind(this));
            
            // Search button
            $(document).on('click', '.acf-popup-search-btn', this.performSearch.bind(this));
        },

        openPopup: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const popupId = $button.data('popup');
            const $popup = $('#' + popupId);

            if ($popup.length) {
                $popup.addClass('active');
                this.loadFieldsForPopup($popup);
            }
        },

        closePopup: function() {
            $('.acf-popup-overlay').removeClass('active');
        },

        loadFieldsForPopup: function($popup) {
            const category = $popup.data('category');
            const $container = $popup.find('.acf-criteria-list');
            const self = this;

            // Check if fields are already loaded
            if ($container.data('fields-loaded') === true) {
                return;
            }

            // Load fields via AJAX
            $.ajax({
                url: acfAnalyzer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'acf_popup_get_fields',
                    nonce: acfAnalyzer.nonce,
                    category: category
                },
                success: function(response) {
                    if (response.success && response.data.fields) {
                        $container.data('fields', response.data.fields);
                        $container.data('fields-loaded', true);
                        
                        // Add first criteria row automatically
                        if ($container.find('.acf-criteria-row').length === 0) {
                            const $button = $popup.find('.acf-add-criteria');
                            self.addCriteriaRow($popup);
                        }
                    }
                },
                error: function() {
                    ACFPopup.showError($popup, 'Failed to load fields. Please try again.');
                }
            });
        },

        addCriteria: function(e) {
            if (e && e.preventDefault) {
                e.preventDefault();
            }
            const $button = $(e.currentTarget);
            const $popup = $button.closest('.acf-popup-overlay');
            
            this.addCriteriaRow($popup);
        },

        addCriteriaRow: function($popup) {
            const $container = $popup.find('.acf-criteria-list');
            const fields = $container.data('fields') || [];

            if (fields.length === 0) {
                ACFPopup.showError($popup, 'No fields available. Please wait for fields to load.');
                return;
            }

            this.criteriaCount++;
            const rowId = 'criteria-' + this.criteriaCount;

            // Build options for field select
            let optionsHTML = '<option value="">Select a field...</option>';
            fields.forEach(function(field) {
                optionsHTML += '<option value="' + field + '">' + field + '</option>';
            });

            const rowHTML = `
                <div class="acf-criteria-row" data-row-id="${rowId}">
                    <select class="acf-field-select" name="field[]" required>
                        ${optionsHTML}
                    </select>
                    <input type="text" class="acf-value-input" name="value[]" placeholder="Search value..." required>
                    <button type="button" class="acf-criteria-remove">Remove</button>
                </div>
            `;

            $container.append(rowHTML);
        },

        removeCriteria: function(e) {
            e.preventDefault();
            const $row = $(e.currentTarget).closest('.acf-criteria-row');
            $row.fadeOut(200, function() {
                $(this).remove();
            });
        },

        performSearch: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const $popup = $button.closest('.acf-popup-overlay');
            const $resultsSection = $popup.find('.acf-results-section');
            const category = $popup.data('category');

            // Collect criteria
            const criteria = [];
            $popup.find('.acf-criteria-row').each(function() {
                const $row = $(this);
                const field = $row.find('.acf-field-select').val();
                const value = $row.find('.acf-value-input').val();

                if (field && value) {
                    criteria.push({
                        field: field,
                        value: value
                    });
                }
            });

            if (criteria.length === 0) {
                ACFPopup.showError($popup, 'Please add at least one search criterion.');
                return;
            }

            // Get match logic
            const matchLogic = $popup.find('input[name="match_logic"]:checked').val() || 'AND';

            // Disable button and show loading
            $button.prop('disabled', true).text('Searching...');
            $resultsSection.html('<div class="acf-loading">Loading results</div>');

            // Perform AJAX search
            $.ajax({
                url: acfAnalyzer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'acf_popup_search',
                    nonce: acfAnalyzer.nonce,
                    category: category,
                    criteria: criteria,
                    match_logic: matchLogic
                },
                success: function(response) {
                    $button.prop('disabled', false).text('Search');
                    
                    if (response.success) {
                        ACFPopup.displayResults($popup, response.data);
                    } else {
                        ACFPopup.showError($popup, response.data.message || 'Search failed.');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text('Search');
                    ACFPopup.showError($popup, 'An error occurred. Please try again.');
                }
            });
        },

        displayResults: function($popup, data) {
            const $resultsSection = $popup.find('.acf-results-section');
            let html = '';

            if (data.total > 0) {
                html += '<div class="acf-results-count">' + data.message + '</div>';
                html += '<ul class="acf-results-list">';
                
                data.posts.forEach(function(post) {
                    html += '<li><a href="' + post.url + '" target="_blank">' + post.title + '</a></li>';
                });
                
                html += '</ul>';
            } else {
                html += '<div class="acf-results-count no-results">' + data.message + '</div>';
                html += '<div class="acf-empty-state"><p>Try adjusting your search criteria.</p></div>';
            }

            $resultsSection.html(html);
        },

        showError: function($popup, message) {
            const $resultsSection = $popup.find('.acf-results-section');
            $resultsSection.html('<div class="acf-error-message">' + message + '</div>');
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        ACFPopup.init();
    });

})(jQuery);
