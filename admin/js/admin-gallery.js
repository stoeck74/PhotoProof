/**
 * PhotoProof — admin-gallery.js
 * Gère : upload média, association AJAX, preview grille, suppression
 *
 * CORRECTIONS :
 * - Appel AJAX pp_attach_photos implémenté (était totalement absent)
 * - XSS corrigés (construction DOM au lieu d'injection HTML)
 * - Animation GSAP ciblée sur les nouveaux thumbs uniquement
 * - Guard pp_vars avant tout usage
 * - Blocage upload si post_id = 0 (nouvelle galerie non sauvegardée)
 * - Bouton suppression par thumb
 * - Feedback utilisateur (loader, erreur, succès)
 */

jQuery(document).ready(function ($) {

    // ── 0. GARDES ─────────────────────────────────────────────────────
    if (typeof pp_vars === 'undefined') {
        console.warn('PhotoProof : pp_vars non défini, gallery JS inactif.');
        return;
    }

    if (typeof gsap === 'undefined') {
        console.warn('PhotoProof : GSAP non chargé, animations désactivées.');
    }

    var frame;
    var $grid = $('#pp-gallery-preview');

    // ── 1. BLOCAGE SUR NOUVELLE GALERIE ──────────────────────────────
    // Sur post-new.php, post_id = 0 → on ne peut pas attacher de médias.
    if (parseInt(pp_vars.is_new_post, 10) === 1) {
        $('#pp_upload_btn').html(
            '<span class="dashicons dashicons-warning" style="font-size:28px;color:#d97706;margin-bottom:8px;display:block;"></span>' +
            '<div class="pp-upload-title" style="color:#92400e;">Sauvegardez d\'abord la galerie</div>' +
            '<div class="pp-upload-sub">Cliquez sur "Publier" ou "Enregistrer le brouillon" avant d\'ajouter des photos.</div>'
        );
        return; // On s'arrête ici pour les nouvelles galeries
    }

    // ── 2. UPLOADER — OUVERTURE DU MEDIA FRAME ───────────────────────
    $('#pp_upload_btn').on('click', function (e) {
        e.preventDefault();

        if (frame) { frame.open(); return; }

        frame = wp.media({
            title:    'Ajouter des photos à la galerie PhotoProof',
            button:   { text: 'Importer dans la galerie' },
            multiple: true
        });

        frame.on('select', function () {
            var selection  = frame.state().get('selection');
            var newThumbs  = [];
            var newIds     = [];

            selection.each(function (attachment) {
                attachment = attachment.toJSON();
                newIds.push(attachment.id);

                // CORRECTION : construction DOM propre, pas d'injection HTML
                var $thumb = $('<div>').addClass('pp-thumb').css('opacity', 0);

                var $img = $('<img>').attr({
                    src: attachment.sizes && attachment.sizes.thumbnail
                        ? attachment.sizes.thumbnail.url
                        : attachment.url
                });

                var $info = $('<div>').addClass('pp-thumb-info').text(attachment.filename);

                // Bouton suppression
                var $del = $('<button>')
                    .attr('type', 'button')
                    .addClass('pp-thumb-delete')
                    .attr('title', 'Retirer de la galerie')
                    .attr('data-id', attachment.id)
                    .html('&times;');

                $thumb.append($img).append($info).append($del);
                $grid.append($thumb);
                newThumbs.push($thumb[0]);
            });

            // CORRECTION : animation GSAP sur les NOUVEAUX thumbs uniquement
            if (newThumbs.length && typeof gsap !== 'undefined') {
                gsap.fromTo(newThumbs,
                    { opacity: 0, y: 20, scale: 0.85 },
                    { opacity: 1, y: 0, scale: 1, duration: 0.5, stagger: 0.07, ease: 'back.out(1.5)' }
                );
            } else if (newThumbs.length) {
                $(newThumbs).css('opacity', 1);
            }

            // CORRECTION : appel AJAX pour associer les photos à la galerie
            if (newIds.length) {
                ppAttachPhotos(newIds);
            }
        });

        frame.open();
    });

    // ── 3. ASSOCIATION AJAX ───────────────────────────────────────────
    function ppAttachPhotos(ids) {
        ppSetUploadState('loading');

        $.post(pp_vars.ajax_url, {
            action:          'pp_attach_photos',
            post_id:         pp_vars.post_id,
            attachment_ids:  ids,
            nonce:           pp_vars.nonce
        })
        .done(function (response) {
            if (response.success) {
                ppSetUploadState('success');
                // Mise à jour du compteur de photos (pas de la sélection client)
                var $count = $('#pp-photo-count');
                if ($count.length && response.data && response.data.count) {
                    $count.text(response.data.count);
                }
            } else {
                ppSetUploadState('error', response.data && response.data.message
                    ? response.data.message
                    : 'Erreur lors de l\'association des photos.');
            }
        })
        .fail(function () {
            ppSetUploadState('error', 'Erreur réseau. Veuillez réessayer.');
        });
    }

    // ── 4. FEEDBACK UTILISATEUR ───────────────────────────────────────
    // CORRECTION : état de chargement / succès / erreur (était totalement absent)
    var $feedback = $('<div>').attr('id', 'pp-upload-feedback').css({
        fontSize:   '12px',
        marginTop:  '10px',
        padding:    '8px 12px',
        borderRadius: '6px',
        display:    'none'
    });
    $('#pp_upload_btn').after($feedback);

    function ppSetUploadState(state, message) {
        $feedback.removeClass('pp-feedback-loading pp-feedback-success pp-feedback-error');

        if (state === 'loading') {
            $feedback
                .addClass('pp-feedback-loading')
                .css({ background: '#f0f6fb', color: '#2271b1', border: '1px solid #c2d7eb' })
                .text('Association des photos en cours…')
                .show();
        } else if (state === 'success') {
            $feedback
                .addClass('pp-feedback-success')
                .css({ background: '#f0fdf4', color: '#166534', border: '1px solid #bbf7d0' })
                .text('Photos ajoutées à la galerie.')
                .show();
            setTimeout(function () { $feedback.fadeOut(400); }, 3000);
        } else if (state === 'error') {
            $feedback
                .addClass('pp-feedback-error')
                .css({ background: '#fef2f2', color: '#991b1b', border: '1px solid #fecaca' })
                .text(message || 'Une erreur est survenue.')
                .show();
        }
    }

    // ── 5. SUPPRESSION D'UN THUMB ─────────────────────────────────────
    // CORRECTION : bouton suppression par miniature (fonctionnalité manquante)
    $(document).on('click', '.pp-thumb-delete', function () {
        var $thumb      = $(this).closest('.pp-thumb');
        var attachmentId = parseInt($(this).data('id'), 10);

        if (typeof gsap !== 'undefined') {
            gsap.to($thumb[0], {
                opacity: 0,
                scale: 0.8,
                duration: 0.25,
                onComplete: function () {
                    $thumb.remove();
                    ppDetachPhoto(attachmentId);
                }
            });
        } else {
            $thumb.remove();
            ppDetachPhoto(attachmentId);
        }
    });

    function ppDetachPhoto(attachmentId) {
        $.post(pp_vars.ajax_url, {
            action:        'pp_detach_photo',
            post_id:       pp_vars.post_id,
            attachment_id: attachmentId,
            nonce:         pp_vars.nonce
        });
        // Pas de feedback bloquant sur la suppression — l'animation suffit
    }

    // ── 6. MICRO-INTERACTIONS HOVER ───────────────────────────────────
    $(document).on('mouseenter', '.pp-thumb', function () {
        if (typeof gsap === 'undefined') return;
        gsap.to(this, { scale: 1.04, y: -4, duration: 0.25, ease: 'power2.out' });
    }).on('mouseleave', '.pp-thumb', function () {
        if (typeof gsap === 'undefined') return;
        gsap.to(this, { scale: 1, y: 0, duration: 0.25, ease: 'power2.inOut' });
    });

    // ── 7. HOVER ZONE D'UPLOAD ────────────────────────────────────────
    $('#pp_upload_btn').on('mouseenter', function () {
        if (typeof gsap === 'undefined') return;
        gsap.to(this, { duration: 0.2 });
    });

});