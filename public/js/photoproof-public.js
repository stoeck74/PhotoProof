/**
 * PhotoProof — photoproof-public.js
 * VERSION PACKERY — Zero gap, comblement des trous automatique
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
    var pckry       = null; // Changé de msnry à pckry
    var $grid       = $('#pp-masonry-grid');


    // ── PACKERY — Tout en %, sans trous ────────────────────────────────
    function initPackery() {
        if (!$grid.length) return;

        // Utilisation de imagesLoaded pour garantir que les dimensions sont calculées après le chargement des images
        imagesLoaded($grid[0], function () {
            // Initialisation de Packery
            pckry = new Packery($grid[0], {
                itemSelector: '.grid-item',
                percentPosition: true,
                gutter: 0, // Force le zéro gap
                // Packery va tenter de remplir les trous si des items de 20% arrivent plus tard
            });

            // On récupère les items pour la lightbox (conserve votre logique actuelle)
            allItems = Array.from($grid[0].querySelectorAll('.pp-photo-item'));
            console.log('Packery init — items:', allItems.length, 'grid width:', $grid[0].offsetWidth);
        });
    }

    // On lance l'initialisation au chargement de la fenêtre
    $(window).on('load', initPackery);

    // ── RESTAURATION SÉLECTION ────────────────────────────────────────
    // (Inchangé)
    $.post(pp_public.ajax_url, {
        action:  'pp_get_selection',
        post_id: pp_public.post_id,
        nonce:   pp_public.nonce
    }, function (response) {
        if (response.success) {
            selectedIds = (response.data.selected_ids || []).map(Number);
            isLocked    = parseInt(response.data.is_locked, 10) === 1;
            selectedIds.forEach(function (id) {
                var $item = $('.pp-photo-item[data-id="' + id + '"]');
                $item.addClass('pp-selected');
                $item.find('.pp-select-btn').attr('aria-pressed', 'true');
            });
            updateCounter();
            if (isLocked) applyLockedState();
            else if (selectedIds.length > 0) showBar();
        }
    });

    // ── INTERACTIONS ──────────────────────────────────────────────────
    // (Inchangé - Les clics sur .pp-photo-item et .pp-select-btn restent identiques)
    $(document).on('click', '.pp-photo-item', function (e) {
        if ($(e.target).closest('.pp-select-btn').length) return;
        var idx = allItems.indexOf(this);
        if (idx !== -1) openLightbox(idx);
    });

    $(document).on('click', '.pp-select-btn', function (e) {
        e.stopPropagation();
        if (isLocked) return;
        var $item = $(this).closest('.pp-photo-item');
        toggleSelection($item, parseInt($item.data('id'), 10));
    });

    $(document).on('click', '.pp-check-dot', function (e) {
        e.stopPropagation();
        $(this).closest('.pp-select-btn').trigger('click');
    });

    function toggleSelection($item, id) {
        if ($item.hasClass('pp-selected')) {
            $item.removeClass('pp-selected');
            $item.find('.pp-select-btn').attr('aria-pressed', 'false');
            selectedIds = selectedIds.filter(function (i) { return i !== id; });
        } else {
            $item.addClass('pp-selected');
            $item.find('.pp-select-btn').attr('aria-pressed', 'true');
            if (selectedIds.indexOf(id) === -1) selectedIds.push(id);
        }
        updateCounter();
        scheduleSave();
    }

    // ── LIGHTBOX ──────────────────────────────────────────────────────
    // (Inchangé - Utilise le tableau allItems défini dans initPackery)
    function openLightbox(idx) {
        lbIndex = idx;
        updateLightbox();
        $('#pp-lightbox').fadeIn(200);
        $('body').css('overflow', 'hidden');
    }

    function closeLightbox() {
        $('#pp-lightbox').fadeOut(200);
        $('body').css('overflow', '');
    }

    function updateLightbox() {
        var $item   = $(allItems[lbIndex]);
        var fullSrc = $item.data('full') || $item.find('.pp-photo-img').attr('src');
        var id      = parseInt($item.data('id'), 10);
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
        var $item = $(allItems[lbIndex]);
        toggleSelection($item, parseInt($item.data('id'), 10));
        updateLightbox();
    });
    $('#pp-lightbox').on('click', function (e) { if ($(e.target).is('#pp-lightbox, .pp-lb-img-wrap')) closeLightbox(); });
    $(document).on('keydown', function (e) {
        if (!$('#pp-lightbox').is(':visible')) return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') $('#pp-lb-prev').trigger('click');
        if (e.key === 'ArrowRight') $('#pp-lb-next').trigger('click');
    });

    // ── BARRE & SAUVEGARDE ────────────────────────────────────────────
    // (Inchangé)
    function showBar() { $('#pp-selection-bar').addClass('pp-bar-visible'); }

    function updateCounter() {
        var count = selectedIds.length;
        $('#pp-count-display, #pp-confirm-count').text(count);
        $('#pp-btn-validate').prop('disabled', count === 0 || isLocked);
        if (count > 0) showBar();
    }

    function scheduleSave() {
        if (isLocked) return;
        clearTimeout(saveTimer);
        setStatus('saving');
        saveTimer = setTimeout(function () { saveSelection(false); }, 1500);
    }

    function saveSelection(isConfirm) {
        $.post(pp_public.ajax_url, {
            action: 'pp_save_selection', post_id: pp_public.post_id,
            nonce: pp_public.nonce, selected_ids: selectedIds,
            confirm: isConfirm ? '1' : '0'
        })
        .done(function (r) {
            if (r.success) {
                if (isConfirm) { isLocked = true; closeConfirmModal(); applyLockedState(); setStatus('confirmed'); }
                else setStatus('saved');
            } else {
                if (r.data && r.data.locked) { isLocked = true; applyLockedState(); }
                setStatus('error');
            }
        })
        .fail(function () { setStatus('error'); });
    }

    function applyLockedState() {
        showBar();
        $('.pp-photo-item:not(.pp-selected)').addClass('pp-photo-dimmed');
        $('.pp-photo-item').css('cursor', 'default');
        $('#pp-selection-bar').addClass('pp-bar-locked');
        $('#pp-btn-validate').text('✓ Sélection confirmée').prop('disabled', true).addClass('pp-btn-confirmed');
        $('#pp-save-status').text('Votre photographe a été notifié').show();
        if (!$('#pp-locked-banner').length) {
            $('.pp-masonry-wrap').before('<div id="pp-locked-banner" class="pp-locked-banner"><span class="pp-locked-icon">✓</span>Sélection confirmée — contactez votre photographe pour toute modification.</div>');
        }
    }

    var statusTimer = null;
    function setStatus(state) {
        var $s = $('#pp-save-status');
        clearTimeout(statusTimer);
        var cfg = { saving: { text: 'Enregistrement…', color: '#64748b' }, saved: { text: '✓ Enregistré', color: '#166534' }, confirmed: { text: '✓ Confirmée', color: '#166534' }, error: { text: '⚠ Erreur', color: '#991b1b' } };
        var c = cfg[state] || cfg.saved;
        $s.text(c.text).css('color', c.color).show();
        if (state === 'saved') statusTimer = setTimeout(function () { $s.fadeOut(400); }, 3000);
    }

    // ── MODAL ─────────────────────────────────────────────────────────
    $('#pp-btn-validate').on('click', function () { if (!selectedIds.length || isLocked) return; $('#pp-confirm-overlay').fadeIn(200); });
    $('#pp-btn-cancel').on('click', function () { closeConfirmModal(); });
    $('#pp-btn-confirm').on('click', function () { clearTimeout(saveTimer); saveSelection(true); });
    $('#pp-confirm-overlay').on('click', function (e) { if ($(e.target).is('#pp-confirm-overlay')) closeConfirmModal(); });
    $(document).on('keydown', function (e) { if (e.key === 'Escape' && $('#pp-confirm-overlay').is(':visible')) closeConfirmModal(); });
    function closeConfirmModal() { $('#pp-confirm-overlay').fadeOut(200); }

})(jQuery);