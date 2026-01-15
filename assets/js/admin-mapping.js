(function($){
    'use strict';

    function renderEditor(mapping){
        var $root = $('#acf-wpgb-mapping-editor');
        if (!$root.length) return;
        $root.empty();

        var $table = $('<table class="widefat"><thead><tr><th>Facet slug</th><th>ACF field</th><th></th></tr></thead><tbody></tbody></table>');
        var $tbody = $table.find('tbody');

        var addRow = function(slug, field){
            var $row = $('<tr></tr>');
            var $slugTd = $('<td></td>');
            var $fieldTd = $('<td></td>');
            var $actionsTd = $('<td></td>');

            var $slugInput = $('<input type="text" class="regular-text" />').val(slug||'');
            var $fieldInput = $('<input type="text" class="regular-text" />').val(field||'');
            var $del = $('<button type="button" class="button-link">Remove</button>');

            $del.on('click', function(){ $row.remove(); });

            $slugTd.append($slugInput);
            $fieldTd.append($fieldInput);
            $actionsTd.append($del);
            $row.append($slugTd, $fieldTd, $actionsTd);
            $tbody.append($row);
        };

        // existing mapping rows
        if (mapping && typeof mapping === 'object'){
            Object.keys(mapping).forEach(function(k){ addRow(k, mapping[k]); });
        }

        // allow adding empty row
        var $addBtn = $('<button type="button" class="button">Add mapping</button>');
        $addBtn.on('click', function(){ addRow('', ''); });

        var $saveBtn = $('<button type="button" class="button button-primary">Save mapping</button>');
        $saveBtn.on('click', function(){
            var payload = {};
            $tbody.find('tr').each(function(){
                var s = $(this).find('td:nth-child(1) input').val().trim();
                var f = $(this).find('td:nth-child(2) input').val().trim();
                if (s !== '') payload[s] = f;
            });

            // AJAX save
            $.post(acfAnalyzerAdmin.ajaxUrl, {
                action: 'acf_analyzer_save_mapping',
                nonce: acfAnalyzerAdmin.nonce,
                mapping: payload
            }, function(resp){
                if (resp && resp.success){
                    alert('Mapping saved');
                    renderEditor(resp.data);
                } else {
                    alert('Save failed: ' + (resp && resp.data ? JSON.stringify(resp.data) : 'unknown'));
                }
            }).fail(function(xhr){
                alert('AJAX error saving mapping');
            });
        });

        $root.append($table);
        $root.append($('<p></p>').append($addBtn).append(' ').append($saveBtn));
    }

    $(document).ready(function(){
        if (typeof acfAnalyzerAdmin === 'undefined') return;
        renderEditor(acfAnalyzerAdmin.mapping || {});
    });

})(jQuery);
