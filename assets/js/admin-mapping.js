/**
 * Admin User Search Options Editor
 *
 * Handles the admin interface for managing User Search Options
 * which define the searchable fields shown in the frontend popup.
 *
 * @package ACF_Analyzer
 * @since 2.0.0
 */

(function($) {
    'use strict';

    // ============================================
    // STATE
    // ============================================

    var userSearchOptions = [];
    var fieldMetaCache = {}; // category -> { key: meta }
    var currentOptionsTab = (acfAnalyzerAdmin && acfAnalyzerAdmin.categories && acfAnalyzerAdmin.categories[0]) || 'Osakeannit';

    // ============================================
    // INITIALIZATION
    // ============================================

    $(document).ready(function() {
        if (typeof acfAnalyzerAdmin === 'undefined') return;
        initSearchOptionsEditor();
    });

    function initSearchOptionsEditor() {
        var $editor = $('#search-options-editor');
        if (!$editor.length) return;

        userSearchOptions = acfAnalyzerAdmin.userSearchOptions || [];
        renderSearchOptionsEditor();
    }

    // ============================================
    // RENDER FUNCTIONS
    // ============================================

    function renderSearchOptionsEditor() {
        var $editor = $('#search-options-editor');
        $editor.empty();

        // Tabs: wire handlers
        $('.user-search-tabs .tab-btn').off('click').on('click', function() {
            $('.user-search-tabs .tab-btn').removeClass('active');
            $(this).addClass('active');
            currentOptionsTab = $(this).data('category');
            renderSearchOptionsEditor();
        });

        var $list = $('<div class="search-options-list"></div>');

        // Render only options for current tab
        userSearchOptions.forEach(function(opt, index) {
            if ((opt.category || '') === currentOptionsTab) {
                $list.append(renderSearchOptionRow(opt, index));
            }
        });

        var $addBtn = $('<button type="button" class="button">+ Add Option</button>');
        $addBtn.on('click', function() {
            userSearchOptions.push({ name: '', category: currentOptionsTab, acf_field: '', values: null });
            renderSearchOptionsEditor();
        });

        var $saveBtn = $('<button type="button" class="button button-primary">Save Options</button>');
        $saveBtn.on('click', function() {
            collectUserOptions();
            saveUserOptions();
        });

        $editor.append($list).append($('<p></p>').append($addBtn).append(' ').append($saveBtn));
    }

    function renderSearchOptionRow(opt, index) {
        var $row = $('<div class="search-option-row" data-index="' + index + '"></div>');

        var $name = $('<input type="text" class="option-name regular-text" placeholder="Display name" />').val(opt.name || '');

        var $cat = $('<select class="option-category"></select>');
        (acfAnalyzerAdmin.categories || []).forEach(function(c) {
            var $o = $('<option></option>').attr('value', c).text(c);
            if (opt.category === c) $o.attr('selected', 'selected');
            $cat.append($o);
        });

        var $acf = $('<select class="option-acf-field"><option value="">-- Select ACF field --</option></select>');
        var $valuesContainer = $('<div class="option-values"></div>');

        // When category changes, fetch fields
        $cat.on('change', function() {
            var category = $(this).val();
            fetchFieldsForCategory(category, function(fields) {
                fieldMetaCache[category] = fieldMetaCache[category] || {};
                fields.forEach(function(f) { fieldMetaCache[category][f.key] = f; });

                $acf.empty();
                $acf.append($('<option value=""></option>').text('-- Select ACF field --'));
                fields.forEach(function(f) { $acf.append($('<option></option>').attr('value', f.key).text(f.label || f.key)); });
                if (opt.acf_field) { $acf.val(opt.acf_field); }
                renderValuesAreaFor($acf, $valuesContainer, opt);
            });
        });

        // When ACF selection changes
        $acf.on('change', function() { renderValuesAreaFor($(this), $valuesContainer, opt); });

        var $remove = $('<button type="button" class="button-link">Remove</button>');
        $remove.on('click', function() {
            userSearchOptions.splice(index, 1);
            renderSearchOptionsEditor();
        });

        $row.append($('<div class="field-group"></div>').append($('<label>Display Name:</label>')).append($name));
        $row.append($('<div class="field-group"></div>').append($('<label>Category:</label>')).append($cat));
        $row.append($('<div class="field-group"></div>').append($('<label>ACF Field:</label>')).append($acf));
        $row.append($('<div class="field-group"></div>').append($('<label>Values:</label>')).append($valuesContainer));
        $row.append($('<div class="field-group field-delete-group"></div>').append($remove));

        // Trigger initial population of ACF select for this category
        (function initPopulate() {
            var category = $cat.val();
            fetchFieldsForCategory(category, function(fields) {
                fieldMetaCache[category] = fieldMetaCache[category] || {};
                fields.forEach(function(f) { fieldMetaCache[category][f.key] = f; });

                $acf.empty();
                $acf.append($('<option value=""></option>').text('-- Select ACF field --'));
                fields.forEach(function(f) { $acf.append($('<option></option>').attr('value', f.key).text(f.label || f.key)); });
                if (opt.acf_field) { $acf.val(opt.acf_field); }
                renderValuesAreaFor($acf, $valuesContainer, opt);
            });
        })();

        return $row;
    }

    function renderValuesAreaFor($acfSelect, $container, opt) {
        $container.empty();
        var $row = $acfSelect.closest('.search-option-row');
        var $catSelect = $row.find('.option-category');
        var category = $catSelect.length ? $catSelect.val() : ((opt && opt.category) || currentOptionsTab);
        var fieldKey = $acfSelect.val();

        if (!fieldKey) {
            $container.append($('<span class="muted">Select an ACF field</span>'));
            return;
        }

        var meta = (fieldMetaCache[category] && fieldMetaCache[category][fieldKey]) ? fieldMetaCache[category][fieldKey] : null;
        if (!meta) {
            // Fetch and retry
            fetchFieldsForCategory(category, function(fields) {
                fieldMetaCache[category] = fieldMetaCache[category] || {};
                fields.forEach(function(f) { fieldMetaCache[category][f.key] = f; });
                renderValuesAreaFor($acfSelect, $container, opt);
            });
            return;
        }

        if (meta.has_choices && meta.choices && Object.keys(meta.choices).length > 0) {
            var $select = $('<select multiple class="option-values-choices" style="min-width:200px; max-width:400px; height:120px;"></select>');
            Object.keys(meta.choices).forEach(function(k) {
                var label = meta.choices[k];
                var $o = $('<option></option>').attr('value', k).text(label);
                if (opt && opt.values && Array.isArray(opt.values) && opt.values.indexOf(k) !== -1) $o.attr('selected', 'selected');
                $select.append($o);
            });
            $container.append($select);
        } else {
            var min = (opt && opt.values && typeof opt.values === 'object') ? (opt.values.min || '') : '';
            var max = (opt && opt.values && typeof opt.values === 'object') ? (opt.values.max || '') : '';
            var $min = $('<input type="text" class="option-values-min small-text" placeholder="min" />').val(min);
            var $max = $('<input type="text" class="option-values-max small-text" placeholder="max" />').val(max);
            $container.append($('<div></div>').append($min).append(' – ').append($max));
        }
    }

    // ============================================
    // DATA COLLECTION
    // ============================================

    function collectUserOptions() {
        var $rows = $('#search-options-editor .search-option-row');
        var collected = [];

        $rows.each(function() {
            var $r = $(this);
            var name = $r.find('.option-name').val().trim();
            var acf = $r.find('.option-acf-field').val();
            var values = null;
            var $choices = $r.find('.option-values-choices');

            if ($choices.length) {
                values = $choices.val() || [];
            } else {
                var min = $r.find('.option-values-min').val();
                var max = $r.find('.option-values-max').val();
                values = { min: min, max: max };
            }

            if (name) {
                collected.push({ name: name, category: currentOptionsTab, acf_field: acf, values: values });
            }
        });

        // Merge: keep existing options from other categories
        var others = userSearchOptions.filter(function(o) { return (o.category || '') !== currentOptionsTab; });
        userSearchOptions = others.concat(collected);
    }

    // ============================================
    // AJAX FUNCTIONS
    // ============================================

    function fetchFieldsForCategory(category, cb) {
        $.post(acfAnalyzerAdmin.ajaxUrl, {
            action: 'acf_analyzer_get_fields_by_category',
            nonce: acfAnalyzerAdmin.nonce,
            category: category
        }, function(resp) {
            if (resp && resp.success) {
                cb(resp.data || []);
            } else {
                console.error('Failed to fetch fields for', category, resp);
                cb([]);
            }
        }).fail(function(xhr) {
            console.error('AJAX error fetching fields', xhr);
            cb([]);
        });
    }

    function saveUserOptions() {
        $.post(acfAnalyzerAdmin.ajaxUrl, {
            action: 'acf_analyzer_save_user_options',
            nonce: acfAnalyzerAdmin.nonce,
            options: userSearchOptions,
            options_json: JSON.stringify(userSearchOptions)
        }, function(resp) {
            if (resp && resp.success) {
                alert('User search options saved');
                userSearchOptions = resp.data && resp.data.sanitized ? resp.data.sanitized : (resp.data && resp.data.raw ? resp.data.raw : userSearchOptions);
                renderSearchOptionsEditor();
            } else {
                alert('Failed to save options: ' + (resp && resp.data ? JSON.stringify(resp.data) : 'unknown'));
            }
        }).fail(function(xhr) {
            console.error('AJAX error saving options', xhr);
            alert('AJAX error saving options');
        });
    }

})(jQuery);
