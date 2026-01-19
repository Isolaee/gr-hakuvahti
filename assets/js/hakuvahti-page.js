(function($) {
    'use strict';

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

    // Edit hakuvahti name
    $(document).on('click', '.hakuvahti-edit-btn', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var $card = $btn.closest('.hakuvahti-card');
        var id = $btn.data('id');
        var currentName = $card.find('.hakuvahti-name').text().trim();

        var newName = prompt(hakuvahtiConfig.i18n.enterNewName || 'Anna uusi nimi hakuvahdille', currentName);
        if ( newName === null ) {
            return;
        }

        newName = newName.trim();
        if ( newName.length === 0 ) {
            alert(hakuvahtiConfig.i18n.saveFailed || 'Päivitys epäonnistui. Nimi ei voi olla tyhjä.');
            return;
        }

        $btn.prop('disabled', true);

        $.post(hakuvahtiConfig.ajaxUrl, {
            action: 'hakuvahti_save',
            nonce: hakuvahtiConfig.nonce,
            id: id,
            name: newName
        }).done(function(resp) {
            if ( resp && resp.success ) {
                $card.find('.hakuvahti-name').text(newName);
            } else {
                var msg = resp && resp.data && resp.data.message ? resp.data.message : (hakuvahtiConfig.i18n.saveFailed || 'Päivitys epäonnistui.');
                alert(msg);
            }
        }).fail(function() {
            alert(hakuvahtiConfig.i18n.networkError);
        }).always(function() {
            $btn.prop('disabled', false);
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
