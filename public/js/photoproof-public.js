/**
 * PhotoProof — photoproof-public.js
 * Gère la sélection client, la sauvegarde AJAX, le verrouillage post-confirmation
 */

(function ($) {
    'use strict';

    if (typeof pp_public === 'undefined') {
        console.warn('PhotoProof : pp_public non défini.');
        return;
    }

    var selectedIds = [];
    var saveTimer   = null;
    var isLocked    = parseInt(pp_public.is_locked, 10) === 1;

    // ── 1. INIT ───────────────────────────────────────────────────────
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

            // Si déjà verrouillée au chargement, on applique l'état immédiatement
            if (isLocked) {
                applyLockedState();
            } else {
                showSelectionBar();
            }
        }
    });

    // ── 2. SÉLECTION / DÉSÉLECTION ───────────────────────────────────
    $(document).on('click', '.pp-photo-item', function () {
        if (isLocked) return; // NOUVEAU : bloqué si confirmé

        var $item = $(this);
        var id    = parseInt($item.data('id'), 10);

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
    });

    $(document).on('click', '.pp-select-btn', function (e) {
        e.stopPropagation();
        if (isLocked) return;
        $(this).closest('.pp-photo-item').trigger('click');
    });

    // ── 3. COMPTEUR ───────────────────────────────────────────────────
    function updateCounter() {
        var count = selectedIds.length;
        $('#pp-count-display').text(count);
        $('#pp-confirm-count').text(count);
        $('#pp-btn-validate').prop('disabled', count === 0);
    }

    // ── 4. BARRE STICKY ───────────────────────────────────────────────
    function showSelectionBar() {
        $('#pp-selection-bar').addClass('pp-bar-visible');
    }

    function hideSelectionBar() {
        $('#pp-selection-bar').removeClass('pp-bar-visible');
    }

    // ── 5. SAUVEGARDE AUTO (debounce 1.5s) ───────────────────────────
    function scheduleSave() {
        if (isLocked) return;
        clearTimeout(saveTimer);
        setStatus('saving');
        saveTimer = setTimeout(function () {
            saveSelection(false);
        }, 1500);
    }

    function saveSelection(isConfirm) {
        $.post(pp_public.ajax_url, {
            action:       'pp_save_selection',
            post_id:      pp_public.post_id,
            nonce:        pp_public.nonce,
            selected_ids: selectedIds,
            confirm:      isConfirm ? '1' : '0'  // NOUVEAU : flag de confirmation finale
        })
        .done(function (response) {
            if (response.success) {
                if (isConfirm) {
                    // NOUVEAU : confirmation finale → verrouillage
                    isLocked = true;
                    closeConfirmModal();
                    applyLockedState();
                    setStatus('confirmed');
                } else {
                    setStatus('saved');
                }
            } else {
                // NOUVEAU : si le serveur répond "locked", on synchronise l'état
                if (response.data && response.data.locked) {
                    isLocked = true;
                    applyLockedState();
                }
                setStatus('error');
            }
        })
        .fail(function () {
            setStatus('error');
        });
    }

    // ── 6. VERROUILLAGE DE LA GRILLE ─────────────────────────────────
    /**
     * NOUVEAU : applique l'état verrouillé
     * - Désactive les clics sur les photos
     * - Remplace la barre de sélection par un message de confirmation
     * - Ajoute un bandeau sur la grille
     */
    function applyLockedState() {
        // Désactiver visuellement les photos non sélectionnées
        $('.pp-photo-item:not(.pp-selected)').addClass('pp-photo-dimmed');
        $('.pp-photo-item').css('cursor', 'default');

        // Remplacer la barre par un message de confirmation
        $('#pp-selection-bar').addClass('pp-bar-visible pp-bar-locked');
        $('#pp-selection-bar .pp-selection-info').html(
            '<strong id="pp-count-display">' + selectedIds.length + '</strong> photo(s) confirmée(s)'
        );
        $('#pp-btn-validate')
            .text('✓ Sélection confirmée')
            .prop('disabled', true)
            .addClass('pp-btn-confirmed');

        $('#pp-save-status')
            .text('Votre photographe a été notifié')
            .show();

        // Bandeau sur la grille
        if (!$('#pp-locked-banner').length) {
            $('#pp-photo-grid').before(
                '<div id="pp-locked-banner" class="pp-locked-banner">' +
                    '<span class="pp-locked-icon">✓</span>' +
                    'Sélection confirmée — contactez votre photographe pour toute modification.' +
                '</div>'
            );
        }
    }

    // ── 7. STATUT DE SAUVEGARDE ───────────────────────────────────────
    var statusTimer = null;
    function setStatus(state) {
        var $status = $('#pp-save-status');
        clearTimeout(statusTimer);

        var cfg = {
            saving:    { text: 'Enregistrement…',         color: '#64748b' },
            saved:     { text: '✓ Sélection enregistrée', color: '#166534' },
            confirmed: { text: '✓ Sélection confirmée',   color: '#166534' },
            error:     { text: '⚠ Erreur — réessayez',    color: '#991b1b' }
        };

        var c = cfg[state] || cfg.saved;
        $status.text(c.text).css('color', c.color).show();

        if (state === 'saved') {
            statusTimer = setTimeout(function () { $status.fadeOut(400); }, 3000);
        }
    }

    // ── 8. MODAL DE CONFIRMATION ──────────────────────────────────────
    $('#pp-btn-validate').on('click', function () {
        if (selectedIds.length === 0 || isLocked) return;
        $('#pp-confirm-overlay').fadeIn(200);
    });

    $('#pp-btn-cancel').on('click', function () {
        closeConfirmModal();
    });

    // NOUVEAU : bouton "Confirmer" déclenche une sauvegarde avec flag confirm=1
    $('#pp-btn-confirm').on('click', function () {
        clearTimeout(saveTimer);
        saveSelection(true);
    });

    $('#pp-confirm-overlay').on('click', function (e) {
        if ($(e.target).is('#pp-confirm-overlay')) closeConfirmModal();
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') closeConfirmModal();
    });

    function closeConfirmModal() {
        $('#pp-confirm-overlay').fadeOut(200);
    }

})(jQuery);