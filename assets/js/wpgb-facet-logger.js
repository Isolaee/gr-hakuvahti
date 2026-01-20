/**
 * Hakuvahti Frontend - User Search Options based search builder
 *
 * This script handles the hakuvahti popup modal that allows users to
 * create saved searches using admin-defined User Search Options.
 *
 * @package ACF_Analyzer
 * @since 2.0.0
 */

(function($) {
    'use strict';

    // ============================================
    // STATE
    // ============================================

    var lastCollectedCriteria = null;
    var lastCollectedCategory = '';

    // ============================================
    // CATEGORY DETECTION
    // ============================================

    /**
     * Detect current category from URL path or body classes
     * @returns {string} Category name or empty string
     */
    function detectCategory() {
        var path = window.location.pathname.toLowerCase();

        if (path.indexOf('/osakeannit') !== -1) return 'Osakeannit';
        if (path.indexOf('/velkakirjat') !== -1) return 'Velkakirjat';
        if (path.indexOf('/osaketori') !== -1) return 'Osaketori';

        // Fallback: check body classes
        try {
            var bodyCls = document.body.className.toLowerCase();
            if (bodyCls.indexOf('category-osakeannit') !== -1) return 'Osakeannit';
            if (bodyCls.indexOf('category-velkakirjat') !== -1) return 'Velkakirjat';
            if (bodyCls.indexOf('category-osaketori') !== -1) return 'Osaketori';
        } catch (e) {}

        return '';
    }

    // ============================================
    // USER SEARCH OPTIONS HANDLING
    // ============================================

    /**
     * Get User Search Options for a specific category
     * @param {string} category
     * @returns {Array} Options for that category
     */
    function getOptionsForCategory(category) {
        var allOptions = window.acfWpgbLogger && window.acfWpgbLogger.userSearchOptions || [];
        if (!category) return [];
        var lc = (category || '').toString().toLowerCase();
        return allOptions.filter(function(opt) {
            var optCat = (opt.category || '').toString().toLowerCase();
            return optCat === lc;
        });
    }

    /**
     * Build the search form from User Search Options
     * @param {string} category
     */
    function buildSearchForm(category) {
        var options = getOptionsForCategory(category);
        var $preview = $('#hakuvahti-criteria-preview');

        $preview.empty().addClass('hakuvahti-search-builder');

        if (!options.length) {
            $preview.html('<p class="no-options">Ei hakuvaihtoehtoja kategorialle ' + category + '</p>');
            return;
        }

        var $container = $('<div id="search-criteria-fields"></div>');

        options.forEach(function(opt) {
            var $field = buildFieldInput(opt);
            $container.append($field);
        });

        $preview.append($container);
    }

    /**
     * Build input field based on option type
     * @param {Object} opt - User Search Option
     * @returns {jQuery} Field element
     */
    function buildFieldInput(opt) {
        // Require explicit ACF field identifier (acf_field) — frontend relies on exact ACF meta key
        var acfField = opt.acf_field || '';
        var $wrapper = $('<div class="search-field-wrapper" data-acf="' + acfField + '" data-key="' + (opt.key || '') + '"></div>');
        var $label = $('<label class="search-field-label">' + (opt.name || acfField) + '</label>');

        $wrapper.append($label);

        // Determine field type based on values
        // opt.values may be:
        // - array of choices -> multiple_choice
        // - associative with min/max -> range (prefill)
        // - null/undefined -> assume range by default
        if (!acfField) {
            // If admin mapping is missing acf_field, indicate and skip rendering inputs
            $wrapper.append($('<div class="muted">ACF field not configured for this option.</div>'));
            // mark as disabled so collectSearchCriteria ignores it
            $wrapper.attr('data-type', 'missing_acf');
            return $wrapper;
        }

        if (opt.values && Array.isArray(opt.values) && opt.values.length > 0) {
            // Multiple choice: render checkboxes
            var $checkboxes = $('<div class="search-field-checkboxes"></div>');

            opt.values.forEach(function(val) {
                var escapedVal = $('<div>').text(val).html();
                var $cb = $('<label class="search-checkbox">' +
                    '<input type="checkbox" value="' + escapedVal + '"> ' +
                    '<span>' + escapedVal + '</span>' +
                    '</label>');
                $checkboxes.append($cb);
            });

            $wrapper.append($checkboxes).attr('data-type', 'multiple_choice');
        } else if (opt.values && typeof opt.values === 'object' && (opt.values.min !== undefined || opt.values.max !== undefined)) {
            // associative min/max
            var $range = $('<div class="search-field-range"></div>');
            var minVal = opt.values.min || '';
            var maxVal = opt.values.max || '';

            $range.append('<input type="number" class="range-min" placeholder="Min" value="' + minVal + '">');
            $range.append('<span class="range-separator"> - </span>');
            $range.append('<input type="number" class="range-max" placeholder="Max" value="' + maxVal + '">');

            $wrapper.append($range).attr('data-type', 'range');
        } else {
            // Range: render min/max inputs
            var $range = $('<div class="search-field-range"></div>');

            $range.append('<input type="number" class="range-min" placeholder="Min">');
            $range.append('<span class="range-separator"> - </span>');
            $range.append('<input type="number" class="range-max" placeholder="Max">');

            $wrapper.append($range).attr('data-type', 'range');
        }

        return $wrapper;
    }

    /**
     * Collect search criteria from the form
     * @returns {Array} Criteria array in format [{name, label, values}]
     */
    function collectSearchCriteria() {
        var criteria = [];

        $('#search-criteria-fields .search-field-wrapper').each(function() {
            var $field = $(this);
            var acfField = $field.data('acf') || $field.data('key') || ($field.find('.search-field-label').text() || '').trim().replace(/\s+/g, '_');
            var fieldType = $field.data('type');
            if (fieldType === 'missing_acf') return;
            var values = [];

            if (!acfField) return;

            if (fieldType === 'multiple_choice') {
                $field.find('input:checked').each(function() {
                    values.push($(this).val());
                });
            } else if (fieldType === 'range') {
                var min = $field.find('.range-min').val();
                var max = $field.find('.range-max').val();
                if (min) values.push(min);
                if (max) values.push(max);
            } else {
                // fallback: check for any text inputs inside the field wrapper
                $field.find('input[type="text"]').each(function() {
                    var v = $(this).val();
                    if (v) values.push(v);
                });
            }

            if (values.length > 0) {
                criteria.push({
                    name: acfField,
                    label: fieldType,
                    values: values
                });
            }
        });

        return criteria;
    }

    // Popup/modal behavior: open, close and save handlers

    function openModal() {
        var $modal = $('#hakuvahti-modal');
        if (!$modal.length) return;

        var category = detectCategory() || '';
        lastCollectedCategory = category;

        // Build the search form for this category
        buildSearchForm(category);

        // Clear name and status
        $('#hakuvahti-save-name').val('');
        $modal.find('.hakuvahti-save-status').text('');

        $modal.show();
        $('body').addClass('hakuvahti-modal-open');
    }

    function closeModal() {
        var $modal = $('#hakuvahti-modal');
        $modal.hide();
        $('body').removeClass('hakuvahti-modal-open');
    }

    // Open modal when button clicked
    $(document).on('click', '.hakuvahti-open-popup', function(e) {
        e.preventDefault();
        openModal();
    });

    // Close by overlay, close button or cancel
    $(document).on('click', '#hakuvahti-modal .hakuvahti-modal-overlay, #hakuvahti-modal .hakuvahti-modal-close, .hakuvahti-cancel-popup', function(e) {
        e.preventDefault();
        closeModal();
    });

    // Handle save
    $(document).on('click', '.hakuvahti-save-popup', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var name = $('#hakuvahti-save-name').val().trim();
        var category = lastCollectedCategory || detectCategory() || '';

        var $status = $('.hakuvahti-save-status');

        if (!name) {
            $status.text('Anna nimi.');
            return;
        }

        var criteria = collectSearchCriteria();
        if (!criteria || !criteria.length) {
            $status.text('Valitse vähintään yksi hakuehto.');
            return;
        }

        $btn.prop('disabled', true);
        $status.text('Tallennetaan...');

        // Build form-style data so jQuery posts criteria as array entries
        var postData = {
            action: 'hakuvahti_save',
            nonce: acfWpgbLogger.hakuvahtiNonce,
            name: name,
            category: category
        };

        criteria.forEach(function(c, i) {
            postData['criteria[' + i + '][name]'] = c.name;
            postData['criteria[' + i + '][label]'] = c.label;
            if (Array.isArray(c.values)) {
                c.values.forEach(function(v, j) {
                    postData['criteria[' + i + '][values][' + j + ']'] = v;
                });
            } else {
                postData['criteria[' + i + '][values][0]'] = c.values;
            }
        });

        // Debug: log outgoing criteria structure
        if ( window.console && console.debug ) console.debug('Posting hakuvahti save', postData);

        $.post(acfWpgbLogger.ajaxUrl, postData).done(function(resp) {
            if (resp && resp.success) {
                $status.text(resp.data && resp.data.message ? resp.data.message : 'Tallennettu.');
                // Optionally redirect if myPageUrl provided
                if (acfWpgbLogger.myPageUrl) {
                    window.location.href = acfWpgbLogger.myPageUrl;
                } else {
                    setTimeout(function() { closeModal(); }, 800);
                }
            } else {
                var msg = resp && resp.data && resp.data.message ? resp.data.message : 'Tallennus epäonnistui.';
                $status.text(msg);
            }
        }).fail(function() {
            $status.text('Verkkovirhe. Yritä uudelleen.');
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

})(jQuery);
