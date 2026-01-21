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

        // Wire tab handlers
        $('.user-search-tabs .tab-btn').off('click').on('click', function() {
            $('.user-search-tabs .tab-btn').removeClass('active');
            $(this).addClass('active');
            currentOptionsTab = $(this).data('category');
            renderSearchOptionsEditor();
        });

        // Filter options for current tab
        var tabOptions = userSearchOptions.filter(function(opt) {
            return (opt.category || '') === currentOptionsTab;
        });

        // Build table
        var $table = $('<table class="search-options-table"></table>');

        // Table header
        var $thead = $('<thead><tr>' +
            '<th class="col-name">Nimi</th>' +
            '<th class="col-type">Tyyppi</th>' +
            '<th class="col-acf">ACF-kenttä</th>' +
            '<th class="col-values">Arvot / Asetukset</th>' +
            '<th class="col-actions"></th>' +
            '</tr></thead>');
        $table.append($thead);

        // Table body
        var $tbody = $('<tbody></tbody>');

        if (tabOptions.length === 0) {
            $tbody.append('<tr><td colspan="5" class="search-options-empty">Ei hakuehtoja tälle kategorialle. Lisää uusi painamalla "+ Lisää hakuehto".</td></tr>');
        } else {
            tabOptions.forEach(function(opt) {
                // Find the original index in userSearchOptions
                var originalIndex = userSearchOptions.indexOf(opt);
                $tbody.append(renderTableRow(opt, originalIndex));
            });
        }

        $table.append($tbody);
        $editor.append($table);

        // Actions bar
        var $actions = $('<div class="search-options-actions"></div>');

        var $addBtn = $('<button type="button" class="button">+ Lisää hakuehto</button>');
        $addBtn.on('click', function() {
            userSearchOptions.push({
                name: '',
                category: currentOptionsTab,
                option_type: 'acf_field',
                acf_field: '',
                values: null
            });
            renderSearchOptionsEditor();
        });

        var $saveBtn = $('<button type="button" class="button button-primary">Tallenna</button>');
        $saveBtn.on('click', function() {
            collectUserOptions();
            saveUserOptions();
        });

        $actions.append($addBtn).append($saveBtn);
        $editor.append($actions);
    }

    function renderTableRow(opt, index) {
        var $row = $('<tr data-index="' + index + '"></tr>');

        // Name column
        var $nameCell = $('<td class="col-name"></td>');
        var $nameInput = $('<input type="text" class="option-name" placeholder="Näyttönimi" />').val(opt.name || '');
        $nameCell.append($nameInput);
        $row.append($nameCell);

        // Type column
        var $typeCell = $('<td class="col-type"></td>');
        var $typeSelect = $('<select class="option-type"></select>');
        $typeSelect.append($('<option value="acf_field">ACF-kenttä</option>'));
        $typeSelect.append($('<option value="word_search">Sanahaku</option>'));
        if (opt.option_type === 'word_search') {
            $typeSelect.val('word_search');
        }
        $typeCell.append($typeSelect);
        $row.append($typeCell);

        // ACF Field column
        var $acfCell = $('<td class="col-acf"></td>');
        var $acfSelect = $('<select class="option-acf-field"><option value="">-- Valitse --</option></select>');
        $acfCell.append($acfSelect);
        $row.append($acfCell);

        // Values column
        var $valuesCell = $('<td class="col-values"></td>');
        var $valuesContainer = $('<div class="option-values"></div>');
        $valuesCell.append($valuesContainer);
        $row.append($valuesCell);

        // Actions column
        var $actionsCell = $('<td class="col-actions"></td>');
        var $removeBtn = $('<button type="button" class="remove-option" title="Poista">&times;</button>');
        $removeBtn.on('click', function() {
            userSearchOptions.splice(index, 1);
            renderSearchOptionsEditor();
        });
        $actionsCell.append($removeBtn);
        $row.append($actionsCell);

        // Function to update visibility based on option type
        function updateTypeVisibility() {
            var type = $typeSelect.val();
            if (type === 'word_search') {
                $acfSelect.hide();
                $acfCell.find('.acf-hidden-note').remove();
                $acfCell.append('<span class="acf-hidden-note" style="color:#666; font-style:italic;">—</span>');
                $valuesContainer.html(
                    '<span class="values-description">Käyttäjä syöttää hakusanat itse. Tukee jokerimerkkiä (*) esim. talo* = talo, talot, talossa.</span>'
                );
            } else {
                $acfSelect.show();
                $acfCell.find('.acf-hidden-note').remove();
                renderValuesAreaFor($acfSelect, $valuesContainer, opt);
            }
        }

        // Type change handler
        $typeSelect.on('change', function() {
            updateTypeVisibility();
        });

        // ACF change handler
        $acfSelect.on('change', function() {
            renderValuesAreaFor($(this), $valuesContainer, opt);
        });

        // Initialize ACF select and values
        (function initPopulate() {
            var category = currentOptionsTab;
            fetchFieldsForCategory(category, function(fields) {
                fieldMetaCache[category] = fieldMetaCache[category] || {};
                fields.forEach(function(f) { fieldMetaCache[category][f.key] = f; });

                $acfSelect.empty();
                $acfSelect.append($('<option value="">-- Valitse --</option>'));
                fields.forEach(function(f) {
                    $acfSelect.append($('<option></option>').attr('value', f.key).text(f.label || f.key));
                });

                if (opt.acf_field && opt.acf_field !== '__word_search') {
                    $acfSelect.val(opt.acf_field);
                }

                updateTypeVisibility();
            });
        })();

        return $row;
    }

    function renderValuesAreaFor($acfSelect, $container, opt) {
        $container.empty();
        var category = currentOptionsTab;
        var fieldKey = $acfSelect.val();

        if (!fieldKey) {
            $container.append('<span class="values-description">Valitse ensin ACF-kenttä</span>');
            return;
        }

        var meta = (fieldMetaCache[category] && fieldMetaCache[category][fieldKey]) ? fieldMetaCache[category][fieldKey] : null;
        if (!meta) {
            fetchFieldsForCategory(category, function(fields) {
                fieldMetaCache[category] = fieldMetaCache[category] || {};
                fields.forEach(function(f) { fieldMetaCache[category][f.key] = f; });
                renderValuesAreaFor($acfSelect, $container, opt);
            });
            return;
        }

        if (meta.has_choices && meta.choices && Object.keys(meta.choices).length > 0) {
            // Multiple choice field
            var $select = $('<select multiple class="option-values-choices values-choices"></select>');
            Object.keys(meta.choices).forEach(function(k) {
                var label = meta.choices[k];
                var $o = $('<option></option>').attr('value', k).text(label);
                if (opt && opt.values && Array.isArray(opt.values) && opt.values.indexOf(k) !== -1) {
                    $o.attr('selected', 'selected');
                }
                $select.append($o);
            });
            $container.append($select);
        } else {
            // Range field (min/max)
            var min = (opt && opt.values && typeof opt.values === 'object') ? (opt.values.min || '') : '';
            var max = (opt && opt.values && typeof opt.values === 'object') ? (opt.values.max || '') : '';
            var postfix = (opt && opt.values && typeof opt.values === 'object') ? (opt.values.postfix || '') : '';

            var $rangeDiv = $('<div class="values-range"></div>');
            $rangeDiv.append($('<input type="text" class="option-values-min" placeholder="Min" />').val(min));
            $rangeDiv.append('<span class="range-sep">–</span>');
            $rangeDiv.append($('<input type="text" class="option-values-max" placeholder="Max" />').val(max));
            $rangeDiv.append($('<input type="text" class="option-values-postfix postfix-input" placeholder="€, %" />').val(postfix));

            $container.append($rangeDiv);
        }
    }

    // ============================================
    // DATA COLLECTION
    // ============================================

    function collectUserOptions() {
        var $rows = $('#search-options-editor tbody tr[data-index]');
        var collected = [];

        $rows.each(function() {
            var $r = $(this);
            var name = $r.find('.option-name').val().trim();
            var optionType = $r.find('.option-type').val() || 'acf_field';
            var acf = $r.find('.option-acf-field').val();
            var values = null;

            if (optionType === 'word_search') {
                if (name) {
                    collected.push({
                        name: name,
                        category: currentOptionsTab,
                        option_type: 'word_search',
                        acf_field: '__word_search',
                        values: null
                    });
                }
            } else {
                var $choices = $r.find('.option-values-choices');
                if ($choices.length) {
                    values = $choices.val() || [];
                } else {
                    var min = $r.find('.option-values-min').val();
                    var max = $r.find('.option-values-max').val();
                    var postfix = $r.find('.option-values-postfix').val() || '';
                    values = { min: min, max: max, postfix: postfix };
                }

                if (name) {
                    collected.push({
                        name: name,
                        category: currentOptionsTab,
                        option_type: 'acf_field',
                        acf_field: acf,
                        values: values
                    });
                }
            }
        });

        // Merge: keep existing options from other categories
        var others = userSearchOptions.filter(function(o) {
            return (o.category || '') !== currentOptionsTab;
        });
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
        // Validate that every ACF option includes an acf_field (word_search is exempt)
        for (var i = 0; i < userSearchOptions.length; i++) {
            var o = userSearchOptions[i];
            if (o.option_type === 'word_search') {
                continue;
            }
            if (!o.acf_field || o.acf_field.toString().trim() === '') {
                alert('Jokaisella ACF-hakuehdolla täytyy olla ACF-kenttä valittuna.');
                return;
            }
        }

        $.post(acfAnalyzerAdmin.ajaxUrl, {
            action: 'acf_analyzer_save_user_options',
            nonce: acfAnalyzerAdmin.nonce,
            options: userSearchOptions,
            options_json: JSON.stringify(userSearchOptions)
        }, function(resp) {
            if (resp && resp.success) {
                alert('Hakuehdot tallennettu!');
                userSearchOptions = resp.data && resp.data.sanitized ? resp.data.sanitized : (resp.data && resp.data.raw ? resp.data.raw : userSearchOptions);
                renderSearchOptionsEditor();
            } else {
                alert('Tallennus epäonnistui: ' + (resp && resp.data ? JSON.stringify(resp.data) : 'tuntematon virhe'));
            }
        }).fail(function(xhr) {
            console.error('AJAX error saving options', xhr);
            alert('Verkkovirhe tallennuksessa');
        });
    }

})(jQuery);
