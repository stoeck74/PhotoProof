/**
 * PhotoProof — admin-gallery.js
 * Uploader custom drag & drop, sans wp.media()
 */

jQuery(document).ready(function ($) {

    if (typeof pp_vars === 'undefined') {
        console.warn('PhotoProof : pp_vars non défini.');
        return;
    }

    var $grid       = $('#pp-gallery-preview');
    var $uploadZone = $('#pp_upload_btn');
    var $fileInput  = null;
    var recoIcon    = pp_vars.reco_icon || '★';
    var recoEnabled = parseInt(pp_vars.reco_enabled, 10) === 1;
    var uploadQueue = [];
    var isUploading = false;
    var totalFiles  = 0;
    var doneFiles   = 0;

    // ── 1. BLOCAGE NOUVELLE GALERIE ───────────────────────────────────
    if (parseInt(pp_vars.is_new_post, 10) === 1) {
        $uploadZone.html(
            '<span class="dashicons dashicons-warning" style="font-size:28px;color:#d97706;margin-bottom:8px;display:block;"></span>' +
            '<div class="pp-upload-title" style="color:#92400e;">Sauvegardez d\'abord la galerie</div>' +
            '<div class="pp-upload-sub">Cliquez sur "Publier" ou "Enregistrer le brouillon" avant d\'ajouter des photos.</div>'
        );
        return;
    }

    // ── 2. CHARGEMENT DES PHOTOS EXISTANTES ──────────────────────────
    $.post(pp_vars.ajax_url, {
        action:  'photoproof_get_gallery_photos',
        post_id: pp_vars.post_id,
        nonce:   pp_vars.nonce
    }, function (response) {
        if (response.success && response.data.photos) {
            response.data.photos.forEach(function (photo) {
                var $thumb = buildThumb(photo.id, photo);
                $thumb.css('opacity', 1);
                $grid.append($thumb);
            });
        }
    });

    // ── 3. INPUT FILE CACHÉ ───────────────────────────────────────────
    $fileInput = $('<input>', {
        type:     'file',
        multiple: true,
        style:    'display:none'
    });
    $('body').append($fileInput);

    // ── 4. EVENTS DRAG & DROP + CLIC ─────────────────────────────────
    $uploadZone.on('click', function () {
        $fileInput.trigger('click');
    });

    $uploadZone.on('dragover dragenter', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('pp-upload-dragover');
    });

    $uploadZone.on('dragleave dragend', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('pp-upload-dragover');
    });

    $uploadZone.on('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('pp-upload-dragover');
        var files = e.originalEvent.dataTransfer.files;
        if (files.length) handleFiles(files);
    });

    $fileInput.on('change', function () {
        if (this.files.length) handleFiles(this.files);
        this.value = ''; // reset pour re-sélectionner les mêmes fichiers
    });

    // ── 5. GESTION DE LA FILE D'UPLOAD ───────────────────────────────
    function handleFiles(files) {
        var arr = Array.from(files);
        totalFiles += arr.length;
        uploadQueue = uploadQueue.concat(arr);
        showProgressBar();
        updateProgressBar();
        if (!isUploading) processQueue();
    }

    function processQueue() {
        if (!uploadQueue.length) {
            isUploading = false;
            updateProgressBar();
            setTimeout(function () { hideProgressBar(); }, 1500);
            return;
        }
        isUploading = true;
        var file = uploadQueue.shift();
        uploadFile(file, function () {
            doneFiles++;
            updateProgressBar();
            processQueue();
        });
    }

    // ── 6. UPLOAD D'UN FICHIER ────────────────────────────────────────
    function uploadFile(file, callback) {
        var formData = new FormData();
        formData.append('action',  'photoproof_upload_photo');
        formData.append('post_id', pp_vars.post_id);
        formData.append('nonce',   pp_vars.nonce);
        formData.append('file',    file);

        $.ajax({
            url:         pp_vars.ajax_url,
            type:        'POST',
            data:        formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success && response.data) {
                    var $thumb = buildThumb(response.data.id, response.data);
                    $grid.append($thumb);

                    if (typeof anime !== 'undefined') {
                        anime({ targets: $thumb[0], opacity: [0, 1], translateY: [16, 0], scale: [0.88, 1], duration: 400, easing: 'easeOutBack' });
                    } else {
                        $thumb.css('opacity', 1);
                    }
                }
                callback();
            },
            error: function () {
                console.warn('PhotoProof : échec upload ' + file.name);
                callback();
            }
        });
    }

    // ── 7. BARRE DE PROGRESSION GLOBALE ──────────────────────────────
    var $progressWrap = null;

    function showProgressBar() {
        if ($progressWrap) return;
        $progressWrap = $(
            '<div class="pp-progress-wrap">' +
                '<div class="pp-progress-track">' +
                    '<div class="pp-progress-fill"></div>' +
                '</div>' +
                '<span class="pp-progress-label"></span>' +
            '</div>'
        );
        $uploadZone.after($progressWrap);
    }

    function updateProgressBar() {
        if (!$progressWrap) return;
        var pct = totalFiles > 0 ? Math.round((doneFiles / totalFiles) * 100) : 0;
        $progressWrap.find('.pp-progress-fill').css('width', pct + '%');
        $progressWrap.find('.pp-progress-label').text(
            doneFiles + ' / ' + totalFiles + ' photo(s) importée(s)'
        );
    }

    function hideProgressBar() {
        if (!$progressWrap) return;
        $progressWrap.fadeOut(400, function () {
            $(this).remove();
            $progressWrap = null;
            totalFiles    = 0;
            doneFiles     = 0;
        });
    }

    // ── 8. CONSTRUCTION D'UN THUMB ───────────────────────────────────
    function buildThumb(id, opts) {
        var $thumb = $('<div>').addClass('pp-thumb').css('opacity', 0).attr('data-id', id);

        var $img  = $('<img>').attr('src', opts.thumb_url || opts.url || '');
        var $info = $('<div>').addClass('pp-thumb-info').text(opts.filename || '');

        var $del = $('<button>')
            .attr({ type: 'button', title: 'Retirer de la galerie', 'data-id': id })
            .addClass('pp-thumb-delete')
            .html('&times;');

        $thumb.append($img).append($info).append($del);

        // Bouton recommandation (si activé dans les settings)
        if (recoEnabled) {
            var $reco = $('<button>')
                .attr({ type: 'button', title: 'Marquer comme recommandée', 'data-id': id })
                .addClass('pp-thumb-reco')
                .text(recoIcon);

            if (opts.recommended) {
                $reco.addClass('pp-reco-active');
            }
            $thumb.append($reco);
        }

        return $thumb;
    }

    // ── 9. TOGGLE RECOMMANDATION ──────────────────────────────────────
    $(document).on('click', '.pp-thumb-reco', function (e) {
        e.stopPropagation();
        var $btn   = $(this);
        var attId  = parseInt($btn.data('id'), 10);
        var isReco = $btn.hasClass('pp-reco-active');

        $btn.toggleClass('pp-reco-active'); // feedback immédiat

        $.post(pp_vars.ajax_url, {
            action:        'photoproof_toggle_recommendation',
            post_id:       pp_vars.post_id,
            attachment_id: attId,
            recommended:   isReco ? '0' : '1',
            nonce:         pp_vars.nonce
        }).fail(function () {
            $btn.toggleClass('pp-reco-active'); // rollback
        });
    });

    // ── 10. SUPPRESSION D'UN THUMB ────────────────────────────────────
    $(document).on('click', '.pp-thumb-delete', function (e) {
        e.stopPropagation();
        var $thumb = $(this).closest('.pp-thumb');
        var attId  = parseInt($(this).data('id'), 10);

        if (typeof anime !== 'undefined') {
            anime({ targets: $thumb[0], opacity: 0, scale: 0.8, duration: 250, easing: 'easeInQuad',
                complete: function () { $thumb.remove(); ppDetachPhoto(attId); }
            });
        } else {
            $thumb.remove();
            ppDetachPhoto(attId);
        }
    });

    function ppDetachPhoto(attId) {
        $.post(pp_vars.ajax_url, {
            action:        'photoproof_detach_photo',
            post_id:       pp_vars.post_id,
            attachment_id: attId,
            nonce:         pp_vars.nonce
        });
    }

    // ── 11. MICRO-INTERACTIONS ────────────────────────────────────────
    $(document).on('mouseenter', '.pp-thumb', function () {
        if (typeof anime === 'undefined') return;
        anime({ targets: this, scale: 1.04, translateY: -4, duration: 250, easing: 'easeOutQuad' });
    }).on('mouseleave', '.pp-thumb', function () {
        if (typeof anime === 'undefined') return;
        anime({ targets: this, scale: 1, translateY: 0, duration: 250, easing: 'easeInOutQuad' });
    });

// ── 12. BRIDGE GUTENBERG ──────────────────────────────────────────
// On détecte si on est dans l'éditeur de blocs
if ( window.wp && wp.data && wp.data.subscribe ) {
    const { select, subscribe } = wp.data;
    let isSavingPost = false;

    // On s'abonne aux changements d'état de l'éditeur
    const unsubscribe = subscribe(() => {
        const saving = select('core/editor').isSavingPost();
        const success = select('core/editor').didPostSaveRequestSucceed();

        // Logique : Si on était en train de sauvegarder et que c'est fini avec succès
        if (isSavingPost && !saving && success) {
            isSavingPost = false;
            
            // OPTION A : La méthode radicale (Reload complet)
            // C'est la plus sûre pour que PHP reconstruise ta barre de statut proprement
            window.location.reload();
            
            // OPTION B : Si tu veux éviter le reload, il faudrait transformer 
            // toute ta metabox en API REST, ce qui est beaucoup plus lourd.
        }
        isSavingPost = saving;
    });
}
});