/**
 * PhotoProof — photoproof-public.js
 * Grille card stricte — sélection, lightbox, sauvegarde AJAX
 * Clic card → lightbox | Clic bouton cercle → sélection
 */

(function ($) {
    'use strict';

    if (typeof photoproof_public === 'undefined') {
        console.warn('PhotoProof : photoproof_public non défini.');
        return;
    }

    var selectedIds      = [];
    var isLocked         = parseInt(photoproof_public.is_locked, 10) === 1;
    var wasAlreadyLocked = isLocked;
    var saveTimer        = null;
    var allItems         = [];
    var lbIndex          = 0;

    // Cache des cards dans l'ordre DOM
    function buildCache() {
        allItems = Array.from(document.querySelectorAll('.pp-card'));
    }
    $(window).on('load', buildCache);

    // ── RESTAURATION SÉLECTION ────────────────────────────────────────
    $.post(photoproof_public.ajax_url, {
        action:  'photoproof_get_selection',
        post_id: photoproof_public.post_id,
        nonce:   photoproof_public.nonce
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

            $(document).trigger('pp:selectionLoaded', [selectedIds]);
        }
    });

    // ── INTERACTIONS ──────────────────────────────────────────────────

    $(document).on('click', '.pp-card-img-wrap', function (e) {
        if ($(e.target).closest('.pp-select-btn').length) return;
        var $card = $(this).closest('.pp-card');
        var idx = allItems.indexOf($card[0]);
        if (idx !== -1) openLightbox(idx);
    });

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
            $(document).trigger('pp:photoDeselected', [{ id: id }]);
        } else {
            $card.addClass('pp-selected');
            $card.find('.pp-select-btn').attr('aria-pressed', 'true');
            if (selectedIds.indexOf(id) === -1) selectedIds.push(id);
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
        var scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
        $('body').css({ 'overflow': 'hidden', 'padding-right': scrollbarWidth + 'px' });
        $('#pp-lightbox').fadeIn(180);
    }

    function closeLightbox() {
        $('#pp-lightbox').fadeOut(180);
        $('body').css({ 'overflow': '', 'padding-right': '' });
    }

    function updateLightbox() {
        var $card   = $(allItems[lbIndex]);
        var fullSrc = $card.find('.pp-card-img-wrap').data('full') || $card.find('.pp-card-img').attr('src');
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
        $.post(photoproof_public.ajax_url, {
            action:       'photoproof_save_selection',
            post_id:      photoproof_public.post_id,
            nonce:        photoproof_public.nonce,
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
    $(document).trigger('pp:galleryLocked');
    $('.pp-card:not(.pp-selected)').addClass('pp-photo-dimmed');
    $('.pp-card').css('cursor', 'default');
    $('#pp-selection-bar').addClass('pp-bar-locked');

    var confirmedText = $('#pp-btn-validate').data('confirmed') || 'Selection confirmed';
    $('#pp-btn-validate').text('✓ ' + confirmedText).prop('disabled', true).addClass('pp-btn-confirmed');

    if (!wasAlreadyLocked) {
        $('#pp-end-overlay').css('display', 'flex').hide().fadeIn(300);
    }
}

    $('#pp-end-back').on('click', function (e) {
        e.preventDefault();
        $('#pp-end-overlay').fadeOut(200);
    });

    // ── STATUT ────────────────────────────────────────────────────────
    var statusTimer = null;
    function setStatus(state) {
        var $s = $('#pp-save-status');
        clearTimeout(statusTimer);
        var cfg = {
            saving:    { text: 'Saving…',       color: '#64748b' },
            saved:     { text: '✓ Saved',           color: '#166534' },
            confirmed: { text: '✓ Confirmed selection', color: '#166534' },
            error:     { text: '⚠ Error — try again',  color: '#991b1b' }
        };
        var c = cfg[state] || cfg.saved;
        $s.text(c.text).css('color', c.color).show();
        if (state === 'saved') statusTimer = setTimeout(function () { $s.fadeOut(400); }, 3000);
    }

    // ── RÉCAP FALLBACK — markup dans le PHP, JS remplit la grille ──
    function openRecapPanel() {
        var $recap = $('#pp-recap-view');
        var $grid  = $('#pp-recap-grid').empty();
        $('#pp-recap-count').text(selectedIds.length);

        selectedIds.forEach(function (id) {
            var $card  = $('.pp-card[data-id="' + id + '"]');
            var imgSrc = $card.find('.pp-card-img-wrap').data('full') || $card.find('.pp-card-img').attr('src');
            var name   = $card.find('.pp-card-name').text();
            var $item  = $('<div class="pp-recap-item" data-id="' + id + '"></div>');
            var $wrap  = $('<div class="pp-recap-img-wrap"></div>');
            $wrap.append($('<img class="pp-recap-img" />').attr('src', imgSrc));
            $wrap.append($('<button class="pp-recap-remove" type="button">×</button>'));
            $item.append($wrap).append($('<span class="pp-recap-name"></span>').text(name));
            $grid.append($item);
        });

        $('#pp-selection-bar').addClass('pp-bar-hidden');
        window.scrollTo({ top: 0 });

        $('#pp-grid').css({ transition: 'transform .35s cubic-bezier(.4,0,.2,1), opacity .35s', transform: 'translateY(40px)', opacity: '0' });

        setTimeout(function () {
            $('#pp-grid').css('display', 'none');
            $recap.css({ display: 'block', transform: 'translateY(-30px)', opacity: '0',
                transition: 'transform .35s cubic-bezier(.4,0,.2,1), opacity .35s' });
            setTimeout(function () {
                $recap.css({ transform: 'translateY(0)', opacity: '1' });
            }, 20);
        }, 350);
    }

    function closeRecapPanel() {
        var $recap = $('#pp-recap-view');
        $recap.css({ transition: 'transform .35s cubic-bezier(.4,0,.2,1), opacity .35s', transform: 'translateY(30px)', opacity: '0' });

        setTimeout(function () {
            $recap.css({ display: 'none', transition: '', transform: '', opacity: '' });
            $('#pp-grid').css({ display: 'grid', transform: 'translateY(-20px)', opacity: '0',
                transition: 'transform .35s cubic-bezier(.4,0,.2,1), opacity .35s' });
            setTimeout(function () {
                $('#pp-grid').css({ transform: 'translateY(0)', opacity: '1' });
                setTimeout(function () {
                    $('#pp-grid').css({ transition: '', transform: '', opacity: '' });
                }, 400);
            }, 20);
            $('#pp-selection-bar').removeClass('pp-bar-hidden');
        }, 350);
    }

    // Événements récap fallback — délégués sur document
    $(document).on('click', '#pp-recap-back', closeRecapPanel);
    $(document).on('click', '#pp-recap-confirm', function () {
        closeRecapPanel();
        clearTimeout(saveTimer);
        saveSelection(true);
    });
    $(document).on('click', '.pp-recap-remove', function () {
        if (!$(this).closest('#pp-recap-view').length) return;
        var id    = parseInt($(this).closest('.pp-recap-item').data('id'), 10);
        var $card = $('.pp-card[data-id="' + id + '"]');
        toggleSelection($card, id);
        $(this).closest('.pp-recap-item').remove();
        $('#pp-recap-count').text(selectedIds.length);
        if (selectedIds.length === 0) closeRecapPanel();
    });

    // ── MODAL ─────────────────────────────────────────────────────────
    $('#pp-btn-validate').on('click', function () {
        if (!selectedIds.length || isLocked) return;
        // Si le module animation est chargé, il gère le récap
        if (typeof anime !== 'undefined' && $('#pp-tray').length) {
            $(document).trigger('pp:openRecap', [selectedIds]);
        } else {
            openRecapPanel();
        }
    });

    $('#pp-btn-cancel').on('click', function () { closeConfirmModal(); });
    $('#pp-btn-confirm').on('click', function () { clearTimeout(saveTimer); saveSelection(true); });

    $('#pp-confirm-overlay').on('click', function (e) {
        if ($(e.target).is('#pp-confirm-overlay')) closeConfirmModal();
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#pp-recap-panel').hasClass('pp-recap-open')) { closeRecapPanel(); return; }
        if (e.key === 'Escape' && $('#pp-confirm-overlay').is(':visible')) closeConfirmModal();
    });

    function closeConfirmModal() { $('#pp-confirm-overlay').fadeOut(200); }

    // ── BRIDGE MODULE ANIMATION ───────────────────────────────────────
    // Confirmation directe depuis le récap animé
    $(document).on('pp:confirmSelection', function () {
        clearTimeout(saveTimer);
        saveSelection(true);
    });

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