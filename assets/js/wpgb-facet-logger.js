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
        return allOptions.filter(function(opt) {
            return opt.category === category;
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
        var acfField = opt.acf_field || '';
        var $wrapper = $('<div class="search-field-wrapper" data-acf="' + acfField + '"></div>');
        var $label = $('<label class="search-field-label">' + (opt.name || acfField) + '</label>');

        $wrapper.append($label);

        // Determine field type based on values
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
            var acfField = $field.data('acf');
            var fieldType = $field.data('type');
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

    // ============================================
    // MODAL HANDLING
    // ============================================

    // Ensure modal is at body level on DOM ready
    $(function() {
        var $modal = $('#hakuvahti-save-modal');
        if ($modal.length && !$modal.parent().is('body')) {
            $modal.appendTo('body');
        }
        $modal.hide();
    });

    // Open modal on button click
    $(document).on('click', '.acf-hakuvahti-save', function(e) {
        e.preventDefault();

        // Check if user is logged in
        if (!window.acfWpgbLogger || !window.acfWpgbLogger.isLoggedIn) {
            alert('Sinun täytyy olla kirjautunut sisään luodaksesi hakuvahdin.');
            return;
        }

        var category = detectCategory();
        if (!category) {
            alert('Kategoriaa ei voitu tunnistaa. Varmista, että olet kategoriasivulla.');
            return;
        }

        lastCollectedCategory = category;

        // Reset form
        var $modal = $('#hakuvahti-save-modal');
        $('#hakuvahti-save-message').html('');
        $('#hakuvahti-name').val('');

        // Build the search form
        buildSearchForm(category);

        // Show modal
        $modal.stop(true, true).fadeIn(150);
        setTimeout(function() {
            $('#hakuvahti-name').focus();
        }, 120);
    });

    // Close modal on X button click
    $(document).on('click', '.hakuvahti-modal-close', function() {
        $('#hakuvahti-save-modal').stop(true, true).fadeOut(120);
    });

    // Close modal on backdrop click
    $(document).on('click', '.hakuvahti-modal', function(e) {
        if (e.target === this) {
            $(this).stop(true, true).fadeOut(120);
        }
    });

    // Close modal on Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#hakuvahti-save-modal').is(':visible')) {
            $('#hakuvahti-save-modal').stop(true, true).fadeOut(120);
        }
    });

    // ============================================
    // FORM SUBMISSION
    // ============================================

    $(document).on('submit', '#hakuvahti-save-form', function(e) {
        e.preventDefault();

        var name = $('#hakuvahti-name').val().trim();
        if (!name) {
            $('#hakuvahti-save-message').html('<p class="error">Anna hakuvahdille nimi.</p>');
            return;
        }

        lastCollectedCriteria = collectSearchCriteria();

        if (!lastCollectedCriteria || lastCollectedCriteria.length === 0) {
            $('#hakuvahti-save-message').html('<p class="error">Valitse vähintään yksi hakuehto.</p>');
            return;
        }

        var $submitBtn = $(this).find('.hakuvahti-submit');
        $submitBtn.prop('disabled', true).text('Tallennetaan...');

        $.post(window.acfWpgbLogger.ajaxUrl, {
            action: 'hakuvahti_save',
            nonce: window.acfWpgbLogger.hakuvahtiNonce,
            name: name,
            category: lastCollectedCategory,
            criteria: lastCollectedCriteria
        }).done(function(resp) {
            if (resp && resp.success) {
                $('#hakuvahti-save-message').html('<p class="success">' + (resp.data.message || 'Hakuvahti tallennettu!') + '</p>');
                setTimeout(function() {
                    $('#hakuvahti-save-modal').stop(true, true).fadeOut(120);
                }, 1500);
            } else {
                var errorMsg = resp.data && resp.data.message ? resp.data.message : 'Tallennus epäonnistui.';
                $('#hakuvahti-save-message').html('<p class="error">' + errorMsg + '</p>');
            }
        }).fail(function() {
            $('#hakuvahti-save-message').html('<p class="error">Verkkovirhe. Yritä uudelleen.</p>');
        }).always(function() {
            $submitBtn.prop('disabled', false).text('Tallenna');
        });
    });

})(jQuery);
