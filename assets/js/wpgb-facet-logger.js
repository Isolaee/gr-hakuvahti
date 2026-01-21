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
    var msdGlobalBound = false; // ensure only one document click handler for msd panels

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
        var optionType = opt.option_type || 'acf_field';
        var $wrapper = $('<div class="search-field-wrapper" data-acf="' + acfField + '"></div>');
        var $label = $('<label class="search-field-label">' + (opt.name || acfField) + '</label>');

        $wrapper.append($label);

        // Handle word_search type - user enters their own search terms
        if (optionType === 'word_search') {
            var $input = $('<input type="text" class="word-search-input" placeholder="esim. Tekoäly" style="width:100%;">');
            var $hint = $('<p class="description" style="margin:4px 0 0; font-size:12px; color:#666;">Erota sanat välilyönnillä. * = jokerimerkki (esim. tekoäl* löytää tekoäly, tekoälyä jne)</p>');
            $wrapper.append($input).append($hint).attr('data-type', 'word_search');
            return $wrapper;
        }

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
            // Multiple choice: render a compact multi-select dropdown (checkboxes inside panel)
            var $dropdown = $('<div class="search-field-dropdown"></div>');
            var $toggle = $('<button type="button" class="msd-toggle">Valitse...</button>');
            var $panel = $('<div class="msd-panel" style="display:none;"></div>');
            var $optionsList = $('<div class="msd-options"></div>');

            opt.values.forEach(function(val) {
                var escapedVal = $('<div>').text(val).html();
                var $opt = $('<label class="msd-option">' +
                    '<input type="checkbox" value="' + escapedVal + '"> ' +
                    '<span class="msd-option-label">' + escapedVal + '</span>' +
                    '</label>');
                $optionsList.append($opt);
            });

            $panel.append($optionsList);
            $dropdown.append($toggle).append($panel);
            $wrapper.append($dropdown).attr('data-type', 'multiple_choice');

            // helper to update toggle text based on selections
            function updateToggleText() {
                var selected = [];
                $optionsList.find('input:checked').each(function() { selected.push($(this).val()); });
                if (!selected.length) {
                    $toggle.text('Valitse...').removeClass('has-value');
                } else if (selected.length <= 3) {
                    $toggle.text(selected.join(', ')).addClass('has-value');
                } else {
                    $toggle.text(selected.length + ' valittua').addClass('has-value');
                }
            }

            // Wire interactions
            $toggle.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('.msd-panel').not($panel).hide();
                $panel.toggle();
            });

            $optionsList.on('click', 'input[type="checkbox"]', function(e) {
                e.stopPropagation();
                updateToggleText();
            });

            // Initialize toggle text
            updateToggleText();

            // Close panels when clicking outside (bind only once globally)
            if (!msdGlobalBound) {
                $(document).on('click.hakuvahti-msd', function() { $('.msd-panel').hide(); });
                msdGlobalBound = true;
            }
            // Stop propagation for clicks inside the panel so document handler doesn't immediately close it
            $panel.on('click', function(e) { e.stopPropagation(); });

        } else if (opt.values && typeof opt.values === 'object' && (opt.values.min !== undefined || opt.values.max !== undefined)) {
            // associative min/max
            var $range = $('<div class="search-field-range"></div>');
            var minVal = opt.values.min || '';
            var maxVal = opt.values.max || '';
            var postfix = opt.values.postfix || '';

            $range.append('<input type="number" class="range-min" placeholder="Min" value="' + minVal + '">');
            $range.append('<span class="range-separator"> - </span>');
            $range.append('<input type="number" class="range-max" placeholder="Max" value="' + maxVal + '">');
            if (postfix) {
                $range.append('<span class="range-postfix" style="margin-left:6px; color:#333;">' + postfix + '</span>');
            }

            $wrapper.append($range).attr('data-type', 'range');
        } else {
            // Range: render min/max inputs
            var $range = $('<div class="search-field-range"></div>');
            var postfix = (opt && opt.values && opt.values.postfix) ? opt.values.postfix : (opt && opt.postfix ? opt.postfix : '');

            $range.append('<input type="number" class="range-min" placeholder="Min">');
            $range.append('<span class="range-separator"> - </span>');
            $range.append('<input type="number" class="range-max" placeholder="Max">');
            if (postfix) {
                $range.append('<span class="range-postfix" style="margin-left:6px; color:#333;">' + postfix + '</span>');
            }

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
            var acfField = $field.data('acf');
            var fieldType = $field.data('type');
            if (fieldType === 'missing_acf') return;
            var values = [];

            if (!acfField) return;

            if (fieldType === 'word_search') {
                // Word search: get text input value, split into words
                var rawText = $field.find('.word-search-input').val() || '';
                var words = rawText.trim().split(/\s+/).filter(function(w) { return w.length > 0; });
                if (words.length > 0) {
                    criteria.push({
                        name: '__word_search',
                        label: 'word_search',
                        values: words
                    });
                }
                return; // Continue to next field
            } else if (fieldType === 'multiple_choice') {
                $field.find('input:checked').each(function() {
                    values.push($(this).val());
                });
            } else if (fieldType === 'range') {
                var min = $field.find('.range-min').val();
                var max = $field.find('.range-max').val();
                // Label single values so backend knows if it's min or max
                if (min && max) {
                    values.push(min);
                    values.push(max);
                } else if (min) {
                    values.push('min:' + min);
                } else if (max) {
                    values.push('max:' + max);
                }
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
