/**
 * PhotoProof — photoproof-public.js
 * Grille card stricte — sélection, lightbox, sauvegarde AJAX
 * Clic card → lightbox | Clic bouton cercle → sélection
 */

(function ($) {
    'use strict';

    if (typeof pp_public === 'undefined') {
        console.warn('PhotoProof : pp_public non défini.');
        return;
    }

    var selectedIds = [];
    var isLocked    = parseInt(pp_public.is_locked, 10) === 1;
    var saveTimer   = null;
    var allItems    = [];
    var lbIndex     = 0;

    // Cache des cards dans l'ordre DOM
    function buildCache() {
        allItems = Array.from(document.querySelectorAll('.pp-card'));
    }
    $(window).on('load', buildCache);

    // ── RESTAURATION SÉLECTION ────────────────────────────────────────
    $.post(pp_public.ajax_url, {
        action:  'pp_get_selection',
        post_id: pp_public.post_id,
        nonce:   pp_public.nonce
    }, function (response) {
        var wasLocked = isLocked;
        if (response.success) {
            selectedIds = (response.data.selected_ids || []).map(Number);
            isLocked    = parseInt(response.data.is_locked, 10) === 1;

            if (wasLocked && !isLocked) { window.location.reload(); return; }

            selectedIds.forEach(function (id) {
                var $card = $('.pp-card[data-id="' + id + '"]');
                $card.addClass('pp-selected');
                $card.find('.pp-select-btn').attr('aria-pressed', 'true');
            });

            updateCounter();
            if (isLocked) applyLockedState();
            else if (selectedIds.length > 0) showBar();

            // Notifier le module d'animation de la sélection initiale
            $(document).trigger('pp:selectionLoaded', [selectedIds]);
        }
    });

    // ── INTERACTIONS ──────────────────────────────────────────────────

    // Clic card → lightbox (sauf si clic sur le bouton sélection)
        $(document).on('click', '.pp-card-img-wrap', function (e) {
        if ($(e.target).closest('.pp-select-btn').length) return;
        var idx = allItems.indexOf(this);
        if (idx !== -1) openLightbox(idx);
    });

    // Clic bouton cercle → sélection uniquement
    $(document).on('click', '.pp-select-btn', function (e) {
        e.stopPropagation();
        if (isLocked) return;
        var $card = $(this).closest('.pp-card');
        toggleSelection($card, parseInt($card.data('id'), 10));
    });

    $(document).on('click', '.pp-check-dot', function (e) {
        e.stopPropagation();
        $(this).closest('.pp-select-btn').trigger('click');
    });

    function toggleSelection($card, id) {
        if ($card.hasClass('pp-selected')) {
            $card.removeClass('pp-selected');
            $card.find('.pp-select-btn').attr('aria-pressed', 'false');
            selectedIds = selectedIds.filter(function (i) { return i !== id; });
            // Notifier le module d'animation — suppression
            $(document).trigger('pp:photoDeselected', [{ id: id }]);
        } else {
            $card.addClass('pp-selected');
            $card.find('.pp-select-btn').attr('aria-pressed', 'true');
            if (selectedIds.indexOf(id) === -1) selectedIds.push(id);
            // Notifier le module d'animation — ajout
            var imgSrc = $card.find('.pp-card-img').attr('src');
            $(document).trigger('pp:photoSelected', [{ id: id, imgSrc: imgSrc, $card: $card }]);
        }
        updateCounter();
        scheduleSave();
    }

    // ── LIGHTBOX ──────────────────────────────────────────────────────
    function openLightbox(idx) {
        lbIndex = idx;
        updateLightbox();
        $('#pp-lightbox').fadeIn(180);
        $('body').css('overflow', 'hidden');
    }

    function closeLightbox() {
        $('#pp-lightbox').fadeOut(180);
        $('body').css('overflow', '');
    }

    function updateLightbox() {
        var $card   = $(allItems[lbIndex]);
        var fullSrc = $card.data('full') || $card.find('.pp-card-img').attr('src');
        var id      = parseInt($card.data('id'), 10);
        var isSel   = selectedIds.indexOf(id) !== -1;

        $('#pp-lb-img').attr('src', fullSrc);
        $('#pp-lb-counter').text((lbIndex + 1) + ' / ' + allItems.length);
        $('#pp-lb-select').text(isSel ? '✓ Sélectionnée' : 'Sélectionner').toggleClass('pp-lb-selected', isSel);
        $('#pp-lb-prev').toggle(lbIndex > 0);
        $('#pp-lb-next').toggle(lbIndex < allItems.length - 1);
    }

    $('#pp-lb-close').on('click', closeLightbox);
    $('#pp-lb-prev').on('click', function () { if (lbIndex > 0) { lbIndex--; updateLightbox(); } });
    $('#pp-lb-next').on('click', function () { if (lbIndex < allItems.length - 1) { lbIndex++; updateLightbox(); } });

    $('#pp-lb-select').on('click', function () {
        if (isLocked) return;
        var $card = $(allItems[lbIndex]);
        toggleSelection($card, parseInt($card.data('id'), 10));
        updateLightbox();
    });

    $('#pp-lightbox').on('click', function (e) {
        if ($(e.target).is('#pp-lightbox, .pp-lb-img-wrap')) closeLightbox();
    });

    $(document).on('keydown', function (e) {
        if (!$('#pp-lightbox').is(':visible')) return;
        if (e.key === 'Escape')     closeLightbox();
        if (e.key === 'ArrowLeft')  $('#pp-lb-prev').trigger('click');
        if (e.key === 'ArrowRight') $('#pp-lb-next').trigger('click');
    });

    // ── BARRE ─────────────────────────────────────────────────────────
    function showBar() { $('#pp-selection-bar').addClass('pp-bar-visible'); }

    function updateCounter() {
        var count = selectedIds.length;
        $('#pp-count-display, #pp-confirm-count').text(count);
        $('#pp-btn-validate').prop('disabled', count === 0 || isLocked);
        if (count > 0) showBar();
    }

    // ── SAUVEGARDE AUTO ───────────────────────────────────────────────
    function scheduleSave() {
        if (isLocked) return;
        clearTimeout(saveTimer);
        setStatus('saving');
        saveTimer = setTimeout(function () { saveSelection(false); }, 1500);
    }

    function saveSelection(isConfirm) {
        $.post(pp_public.ajax_url, {
            action:       'pp_save_selection',
            post_id:      pp_public.post_id,
            nonce:        pp_public.nonce,
            selected_ids: selectedIds,
            confirm:      isConfirm ? '1' : '0'
        })
        .done(function (r) {
            if (r.success) {
                if (isConfirm) {
                    isLocked = true;
                    closeConfirmModal();
                    applyLockedState();
                    setStatus('confirmed');
                } else {
                    setStatus('saved');
                }
            } else {
                if (r.data && r.data.locked) {
                    isLocked = true;
                    applyLockedState();
                } else if (r.data && r.data.auth_required) {
                    closeConfirmModal();
                    if (confirm(r.data.message + '\n\nCliquer OK pour aller à la page de connexion.')) {
                        window.location.href = r.data.login_url;
                    }
                } else {
                    setStatus('error');
                }
            }
        })
        .fail(function () { setStatus('error'); });
    }

    // ── ÉTAT VERROUILLÉ ───────────────────────────────────────────────
    function applyLockedState() {
        showBar();
        // Notifier le module d'animation
        $(document).trigger('pp:galleryLocked');
        $('.pp-card:not(.pp-selected)').addClass('pp-photo-dimmed');
        $('.pp-card').css('cursor', 'default');
        $('#pp-selection-bar').addClass('pp-bar-locked');
        $('#pp-btn-validate').text('✓ Sélection confirmée').prop('disabled', true).addClass('pp-btn-confirmed');
        $('#pp-save-status').text('Votre photographe a été notifié').show();

        if (!$('#pp-locked-banner').length) {
            $('#pp-grid').before(
                '<div class="pp-locked-banner">' +
                '<span class="pp-locked-icon">✓</span>' +
                'Sélection confirmée — contactez votre photographe pour toute modification.' +
                '</div>'
            );
        }
    }

    // ── STATUT ────────────────────────────────────────────────────────
    var statusTimer = null;
    function setStatus(state) {
        var $s = $('#pp-save-status');
        clearTimeout(statusTimer);
        var cfg = {
            saving:    { text: 'Enregistrement…',       color: '#64748b' },
            saved:     { text: '✓ Enregistré',           color: '#166534' },
            confirmed: { text: '✓ Sélection confirmée', color: '#166534' },
            error:     { text: '⚠ Erreur — réessayez',  color: '#991b1b' }
        };
        var c = cfg[state] || cfg.saved;
        $s.text(c.text).css('color', c.color).show();
        if (state === 'saved') statusTimer = setTimeout(function () { $s.fadeOut(400); }, 3000);
    }

    // ── MODAL ─────────────────────────────────────────────────────────
    $('#pp-btn-validate').on('click', function () {
        if (!selectedIds.length || isLocked) return;
        $('#pp-confirm-overlay').fadeIn(200);
    });

    $('#pp-btn-cancel').on('click', function () { closeConfirmModal(); });
    $('#pp-btn-confirm').on('click', function () { clearTimeout(saveTimer); saveSelection(true); });

    $('#pp-confirm-overlay').on('click', function (e) {
        if ($(e.target).is('#pp-confirm-overlay')) closeConfirmModal();
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#pp-confirm-overlay').is(':visible')) closeConfirmModal();
    });

    function closeConfirmModal() { $('#pp-confirm-overlay').fadeOut(200); }

    // ── BRIDGE MODULE ANIMATION ───────────────────────────────────────
    // Écouter les requêtes du module d'animation (deselect + lightbox)
    $(document).on('pp:requestDeselect', function (e, id) {
        var $card = $('.pp-card[data-id="' + id + '"]');
        if ($card.hasClass('pp-selected')) toggleSelection($card, id);
    });

    $(document).on('pp:requestLightbox', function (e, id) {
        var idx = allItems.findIndex(function (el) {
            return parseInt($(el).data('id'), 10) === id;
        });
        if (idx !== -1) openLightbox(idx);
    });

})(jQuery);