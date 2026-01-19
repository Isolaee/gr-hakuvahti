(function($) {
    'use strict';

    // Cache for available fields per category
    var fieldsCache = {};

    // Fetch available fields for a category
    function fetchFieldsForCategory(category, callback) {
        // Return from cache if available
        if (fieldsCache[category]) {
            callback(fieldsCache[category]);
            return;
        }

        $.post(hakuvahtiConfig.ajaxUrl, {
            action: 'acf_popup_get_fields',
            nonce: hakuvahtiConfig.fieldNonce,
            category: category
        }).done(function(resp) {
            if (resp && resp.success && resp.data && resp.data.fields) {
                fieldsCache[category] = resp.data.fields;
                callback(resp.data.fields);
            } else {
                callback([]);
            }
        }).fail(function() {
            callback([]);
        });
    }

    // Build field name dropdown HTML
    function buildFieldDropdown(fields, selectedValue) {
        var html = '<select class="hakuvahti-edit-crit-name" style="width:30%; margin-right:6px;">';
        html += '<option value="">' + (hakuvahtiConfig.i18n.selectField || 'Valitse kenttä') + '</option>';
        fields.forEach(function(field) {
            var selected = (field === selectedValue) ? ' selected' : '';
            html += '<option value="' + field + '"' + selected + '>' + field + '</option>';
        });
        html += '</select>';
        return html;
    }

    // Run hakuvahti search
    $(document).on('click', '.hakuvahti-run-btn', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var $card = $btn.closest('.hakuvahti-card');
        var $results = $card.find('.hakuvahti-results');
        var id = $btn.data('id');

        // Disable button and show loading
        $btn.prop('disabled', true).text(hakuvahtiConfig.i18n.running);
        $results.hide().html('');

        $.post(hakuvahtiConfig.ajaxUrl, {
            action: 'hakuvahti_run',
            nonce: hakuvahtiConfig.nonce,
            id: id
        }).done(function(resp) {
            if (resp && resp.success) {
                var data = resp.data;
                var posts = data.posts || [];
                var html = '';

                if (posts.length === 0) {
                    html = '<p class="hakuvahti-no-results">' + hakuvahtiConfig.i18n.noNewResults + '</p>';
                } else {
                    html = '<p class="hakuvahti-results-count"><strong>' + posts.length + '</strong> ' + hakuvahtiConfig.i18n.newResults + '</p>';
                    html += '<ul class="hakuvahti-results-list">';
                    posts.forEach(function(post) {
                        html += '<li><a href="' + post.url + '" target="_blank">' + post.title + '</a></li>';
                    });
                    html += '</ul>';
                }

                $results.html(html).slideDown();
            } else {
                var message = resp.data && resp.data.message ? resp.data.message : 'Error';
                $results.html('<p class="hakuvahti-error">' + message + '</p>').slideDown();
            }
        }).fail(function() {
            $results.html('<p class="hakuvahti-error">' + hakuvahtiConfig.i18n.networkError + '</p>').slideDown();
        }).always(function() {
            $btn.prop('disabled', false).text($btn.data('original-text') || 'Hae uudet');
        });

        // Store original text
        if (!$btn.data('original-text')) {
            $btn.data('original-text', $btn.text());
        }
    });

    // Edit hakuvahti (name + criteria) - inline form
    $(document).on('click', '.hakuvahti-edit-btn', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var $card = $btn.closest('.hakuvahti-card');
        var id = $btn.data('id');
        var category = $card.attr('data-category') || '';
        var criteriaData = [];

        try {
            var raw = $card.attr('data-criteria') || '[]';
            criteriaData = JSON.parse(raw);
        } catch (err) {
            criteriaData = [];
        }

        // If form already visible, toggle off
        var $form = $card.find('.hakuvahti-edit-form');
        if ( $form.is(':visible') ) {
            $form.slideUp();
            return;
        }

        // Show loading state
        $form.html('<div class="hakuvahti-edit-inner"><p>' + (hakuvahtiConfig.i18n.loadingFields || 'Ladataan kenttiä...') + '</p></div>').slideDown();

        // Fetch available fields for this category, then build the form
        fetchFieldsForCategory(category, function(fields) {
            // Build form HTML
            var html = '';
            html += '<div class="hakuvahti-edit-inner">';
            html += '<label>' + (hakuvahtiConfig.i18n.nameLabel || 'Nimi') + '</label>';
            html += '<input type="text" class="hakuvahti-edit-name" value="' + $card.find('.hakuvahti-name').text().trim().replace(/"/g,'&quot;') + '" style="width:100%; margin-bottom:8px;" />';

            html += '<div class="hakuvahti-edit-criteria-list" data-fields="' + encodeURIComponent(JSON.stringify(fields)) + '">';

            if ( criteriaData && criteriaData.length ) {
                criteriaData.forEach(function(c, idx) {
                    var vals = Array.isArray(c.values) ? c.values.join(', ') : (c.values || '');
                    html += '<div class="hakuvahti-edit-criterion" data-index="' + idx + '" style="margin-bottom:6px;">';
                    html += buildFieldDropdown(fields, c.name || '');
                    html += '<select class="hakuvahti-edit-crit-label" style="width:20%; margin-right:6px;"><option value="multiple_choice">Multiple</option><option value="range">Range</option></select>';
                    html += '<input type="text" class="hakuvahti-edit-crit-values" placeholder="Arvot (pilkulla eroteltu)" value="' + (vals) + '" style="width:40%; margin-right:6px;" />';
                    html += '<button class="hakuvahti-edit-crit-remove button" type="button">' + (hakuvahtiConfig.i18n.remove || 'Poista') + '</button>';
                    html += '</div>';
                });
            }

            html += '</div>'; // list
            html += '<div style="margin-top:8px;">';
            html += '<button class="hakuvahti-add-criterion button" type="button">' + (hakuvahtiConfig.i18n.addCriterion || 'Lisää ehto') + '</button> ';
            html += '<button class="hakuvahti-save-edit button button-primary" type="button">' + (hakuvahtiConfig.i18n.save || 'Tallenna') + '</button> ';
            html += '<button class="hakuvahti-cancel-edit button" type="button">' + (hakuvahtiConfig.i18n.cancel || 'Peruuta') + '</button>';
            html += '</div>';
            html += '</div>';

            $form.html(html);

            // set label selects according to data
            $form.find('.hakuvahti-edit-criterion').each(function() {
                var idx = $(this).data('index');
                var c = criteriaData[idx] || {};
                $(this).find('.hakuvahti-edit-crit-label').val(c.label || 'multiple_choice');
            });
        });
    });

    // Add new criterion
    $(document).on('click', '.hakuvahti-add-criterion', function(e) {
        e.preventDefault();
        var $list = $(this).closest('.hakuvahti-edit-inner').find('.hakuvahti-edit-criteria-list');
        var idx = $list.find('.hakuvahti-edit-criterion').length;

        // Get available fields from data attribute
        var fields = [];
        try {
            var fieldsJson = $list.attr('data-fields');
            if (fieldsJson) {
                fields = JSON.parse(decodeURIComponent(fieldsJson));
            }
        } catch (err) {
            fields = [];
        }

        var html = '<div class="hakuvahti-edit-criterion" data-index="' + idx + '" style="margin-bottom:6px;">';
        html += buildFieldDropdown(fields, '');
        html += '<select class="hakuvahti-edit-crit-label" style="width:20%; margin-right:6px;"><option value="multiple_choice">Multiple</option><option value="range">Range</option></select>';
        html += '<input type="text" class="hakuvahti-edit-crit-values" placeholder="Arvot (pilkulla eroteltu)" value="" style="width:40%; margin-right:6px;" />';
        html += '<button class="hakuvahti-edit-crit-remove button" type="button">' + (hakuvahtiConfig.i18n.remove || 'Poista') + '</button>';
        html += '</div>';
        $list.append(html);
    });

    // Remove a criterion
    $(document).on('click', '.hakuvahti-edit-crit-remove', function(e) {
        e.preventDefault();
        $(this).closest('.hakuvahti-edit-criterion').slideUp(200, function() { $(this).remove(); });
    });

    // Cancel edit
    $(document).on('click', '.hakuvahti-cancel-edit', function(e) {
        e.preventDefault();
        var $form = $(this).closest('.hakuvahti-edit-form');
        $form.slideUp();
    });

    // Save edit
    $(document).on('click', '.hakuvahti-save-edit', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $form = $btn.closest('.hakuvahti-edit-form');
        var $card = $btn.closest('.hakuvahti-card');
        var id = $card.data('id');
        var newName = $form.find('.hakuvahti-edit-name').val().trim();

        if ( ! newName ) {
            alert(hakuvahtiConfig.i18n.saveFailed || 'Nimi ei voi olla tyhjä');
            return;
        }

        // Build criteria array
        var crits = [];
        $form.find('.hakuvahti-edit-criterion').each(function() {
            var name = $(this).find('.hakuvahti-edit-crit-name').val().trim();
            var label = $(this).find('.hakuvahti-edit-crit-label').val();
            var rawvals = $(this).find('.hakuvahti-edit-crit-values').val().trim();
            if ( ! name ) return;
            var values = [];
            if ( label === 'range' ) {
                // Expect comma separated two numbers, or single value
                var parts = rawvals.split(',').map(function(s){ return s.trim(); }).filter(Boolean);
                values = parts;
            } else {
                // multiple_choice - comma separated
                var parts = rawvals.split(',').map(function(s){ return s.trim(); }).filter(Boolean);
                values = parts;
            }
            crits.push({ name: name, label: label, values: values });
        });

        $btn.prop('disabled', true).text(hakuvahtiConfig.i18n.saving || 'Tallennetaan...');

        $.post(hakuvahtiConfig.ajaxUrl, {
            action: 'hakuvahti_save',
            nonce: hakuvahtiConfig.nonce,
            id: id,
            name: newName,
            criteria: JSON.stringify(crits)
        }).done(function(resp) {
            if ( resp && resp.success ) {
                // Update UI
                $card.find('.hakuvahti-name').text(newName);
                // Update criteria summary and data attribute
                var summary = (window.hakuvahtiFormatCriteria && typeof window.hakuvahtiFormatCriteria === 'function') ? window.hakuvahtiFormatCriteria(crits) : (newName); // fallback
                // Try to render simple summary: name: v1, v2 | ...
                var parts = [];
                crits.forEach(function(c){ if (c.values && c.values.length) parts.push(c.name + ': ' + c.values.join(', ')); });
                $card.find('.hakuvahti-criteria').text(parts.join(' | ') || hakuvahtiConfig.i18n.noCriteria || 'Ei hakuehtoja');
                $card.attr('data-criteria', JSON.stringify(crits));
                $form.slideUp();
            } else {
                var msg = resp && resp.data && resp.data.message ? resp.data.message : (hakuvahtiConfig.i18n.saveFailed || 'Päivitys epäonnistui.');
                alert(msg);
            }
        }).fail(function() {
            alert(hakuvahtiConfig.i18n.networkError);
        }).always(function() {
            $btn.prop('disabled', false).text(hakuvahtiConfig.i18n.save || 'Tallenna');
        });
    });

    // Delete hakuvahti
    $(document).on('click', '.hakuvahti-delete-btn', function(e) {
        e.preventDefault();

        if (!confirm(hakuvahtiConfig.i18n.confirmDelete)) {
            return;
        }

        var $btn = $(this);
        var $card = $btn.closest('.hakuvahti-card');
        var id = $btn.data('id');

        $btn.prop('disabled', true);

        $.post(hakuvahtiConfig.ajaxUrl, {
            action: 'hakuvahti_delete',
            nonce: hakuvahtiConfig.nonce,
            id: id
        }).done(function(resp) {
            if (resp && resp.success) {
                $card.slideUp(300, function() {
                    $(this).remove();

                    // Check if list is empty now
                    if ($('#hakuvahti-list .hakuvahti-card').length === 0) {
                        location.reload();
                    }
                });
            } else {
                alert(hakuvahtiConfig.i18n.deleteFailed);
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            alert(hakuvahtiConfig.i18n.networkError);
            $btn.prop('disabled', false);
        });
    });

})(jQuery);
