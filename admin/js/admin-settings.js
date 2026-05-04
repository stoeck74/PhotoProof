/**
 * PhotoProof — admin-settings.js
 *
 * Gère :
 *  - Navigation par tabs horizontaux (avec persistance sessionStorage)
 *  - Toggle SVG des cards (+/-/∞) avec tracé de bordure animé
 *  - Synchronisation avec les hidden inputs (compatible Settings API)
 *  - Color picker, slider opacité, uploaders watermark & logo (inchangés)
 */

jQuery(document).ready(function ($) {

    // ════════════════════════════════════════════════════════════════
    // 0. RESTAURATION DE L'ONGLET ACTIF APRÈS SAVE
    //    (WP vire le hash après redirection post-save)
    // ════════════════════════════════════════════════════════════════
    var STORAGE_KEY = 'photoproof_active_tab';

    var savedTab = sessionStorage.getItem(STORAGE_KEY);
    if (savedTab) {
        var $tabFromStorage = $('.pp-tab[data-target="' + savedTab + '"]');
        var $panelFromStorage = $('#section-' + savedTab);
        if ($tabFromStorage.length && $panelFromStorage.length) {
            $('.pp-tab').removeClass('is-active');
            $('.pp-tab-panel').removeClass('is-active');
            $tabFromStorage.addClass('is-active');
            $panelFromStorage.addClass('is-active');
        }
    }

    // Retire le voile anti-flash
    $('.pp-settings-page').removeClass('pp-loading');

    // ════════════════════════════════════════════════════════════════
    // 1. COLOR PICKER (avec hook dirty pour la save bar)
    // ════════════════════════════════════════════════════════════════
    if ($.fn.wpColorPicker) {
        $('.pp-color-picker').wpColorPicker({
            change: function () {
                // setDirty est défini en section 8 mais le code y arrive avant l'event change
                if (typeof setDirty === 'function') setDirty();
            }
        });
    }

    // ════════════════════════════════════════════════════════════════
    // 2. NAVIGATION PAR TABS
    // ════════════════════════════════════════════════════════════════
    $('.pp-tab').on('click', function () {
        var target = $(this).data('target');
        if ($(this).hasClass('is-active')) return;

        var $nextPanel = $('#section-' + target);
        if (!$nextPanel.length) return;

        sessionStorage.setItem(STORAGE_KEY, target);

        $('.pp-tab').removeClass('is-active');
        $(this).addClass('is-active');

        var $current = $('.pp-tab-panel.is-active');

        if (typeof anime !== 'undefined') {
            anime({
                targets: $current[0],
                opacity: 0,
                translateX: -15,
                duration: 200,
                easing: 'easeInQuad',
                complete: function () {
                    $current.removeClass('is-active').css({ opacity: '', transform: '' });
                    $nextPanel.addClass('is-active');
                    anime({
                        targets: $nextPanel[0],
                        opacity: [0, 1],
                        translateX: [15, 0],
                        duration: 350,
                        easing: 'easeOutQuad',
                        complete: function () {
                            // Refresh stroke perimeters of newly visible cards
                            // (their dimensions were unknown while display:none)
                            $nextPanel.find('.pp-card').each(function () {
                                var setup = $(this).data('pp-setup-stroke');
                                if (typeof setup === 'function') setup();
                            });
                        }
                    });
                }
            });
        } else {
            $current.removeClass('is-active');
            $nextPanel.addClass('is-active');
            $nextPanel.find('.pp-card').each(function () {
                var setup = $(this).data('pp-setup-stroke');
                if (typeof setup === 'function') setup();
            });
        }
    });

    // ════════════════════════════════════════════════════════════════
    // 3. CARDS — TOGGLE + TRACÉ BORDURE ANIMÉ
    // ════════════════════════════════════════════════════════════════
    $('.pp-card').each(function () {
        var $card  = $(this);
        var $rect  = $card.find('.pp-card-stroke rect').first();
        var $toggle = $card.find('.pp-card-toggle').first();

        if (!$rect.length || !$toggle.length) return;

        var rect      = $rect[0];
        var perimeter = 0;

        function setupStroke() {
            // Skip if card is hidden (display:none in inactive panel)
            if (!$card.is(':visible')) return;
            var len = rect.getTotalLength();
            if (!len) return;
            perimeter = len;
            rect.style.strokeDasharray = perimeter;
            rect.style.strokeDashoffset = $card.hasClass('is-active') ? 0 : perimeter;
        }

        // Expose setup for tab-switch refresh
        $card.data('pp-setup-stroke', setupStroke);

        // Initial setup once dimensions are stable
        requestAnimationFrame(setupStroke);

        // Recompute on resize
        $(window).on('resize.ppCard', setupStroke);

        // Locked cards (always-active) : pas d'interaction
        var mode = $card.data('toggle-mode');
        if (mode === 'locked' || $toggle.hasClass('is-locked')) return;

        // Mode "fake" : Watermark / Custom Login → l'état est piloté par
        // la présence du logo/URL, pas par le toggle. Le clic est ignoré
        // mais l'état visuel reste cohérent (mis à jour par les uploaders).
        if (mode === 'fake') {
            $toggle.css('cursor', 'default');
            return;
        }

        // Mode "layout" : Masonry — bascule entre 'grid' et 'masonry'
        // Mode par défaut (data-toggle-target) : bascule entre '0' et '1'
        $toggle.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var willActivate = !$card.hasClass('is-active');
            $card.toggleClass('is-active');

            // Anime la bordure
            if (perimeter) {
                rect.style.strokeDashoffset = willActivate ? 0 : perimeter;
            }

            // Met à jour le hidden input lié
            var $hidden;
            if (mode === 'layout') {
                $hidden = $card.find('input[type="hidden"][name="photoproof_gallery_layout"]');
                $hidden.val(willActivate ? 'masonry' : 'grid');
            } else {
                var optionName = $card.data('toggle-target');
                if (optionName) {
                    $hidden = $card.find('input[type="hidden"][name="' + optionName + '"]');
                    $hidden.val(willActivate ? '1' : '0');
                }
            }

            // Met à jour le label de status (Active / Inactive)
            // (les always-active gardent leur label "Always active" en dur)
            var $status = $card.find('.pp-card-status').not('.is-locked').first();
            if ($status.length) {
                $status.text(willActivate ? ppGetActiveLabel() : ppGetInactiveLabel());
            }

            // Marque le formulaire comme modifié pour la save bar
            if (typeof setDirty === 'function') setDirty();
        });
    });

    // Helpers de label (avec fallback i18n WP si dispo)
    function ppGetActiveLabel() {
        if (typeof window.wp !== 'undefined' && wp.i18n && typeof wp.i18n.__ === 'function') {
            return wp.i18n.__('Active', 'photoproof');
        }
        return 'Active';
    }
    function ppGetInactiveLabel() {
        if (typeof window.wp !== 'undefined' && wp.i18n && typeof wp.i18n.__ === 'function') {
            return wp.i18n.__('Inactive', 'photoproof');
        }
        return 'Inactive';
    }

    // ════════════════════════════════════════════════════════════════
    // 4. SLIDER OPACITÉ WATERMARK (inchangé)
    // ════════════════════════════════════════════════════════════════
    $('#photoproof_watermark_opacity_range').on('input', function () {
        var val     = parseInt($(this).val(), 10);
        var opacity = val / 100;
        $('#opacity-val').text(val);

        var $preview = $('#wm-live-preview');
        if ($preview.length && typeof anime !== 'undefined') {
            anime({ targets: $preview[0], opacity: opacity, duration: 200, easing: 'linear' });
        } else if ($preview.length) {
            $preview.css('opacity', opacity);
        }
    });

    // ════════════════════════════════════════════════════════════════
    // 5. UPLOADER WATERMARK (inchangé, juste sync de la card "fake")
    // ════════════════════════════════════════════════════════════════
    var wm_frame;

    $('#photoproof_upload_watermark_btn').on('click', function (e) {
        e.preventDefault();
        if (wm_frame) { wm_frame.open(); return; }

        wm_frame = wp.media({
            title:    'Logo du Watermark',
            button:   { text: 'Utiliser ce logo' },
            multiple: false
        });

        wm_frame.on('select', function () {
            var attachment = wm_frame.state().get('selection').first().toJSON();
            var opacity    = parseInt($('#photoproof_watermark_opacity_range').val(), 10) / 100;

            $('#photoproof_global_watermark').val(attachment.id);

            var $img = $('<img>', {
                id:    'wm-live-preview',
                src:   attachment.url,
                style: 'max-width:150px; opacity:' + opacity + ';'
            });
            $('#wm-preview-container').empty().append($img);

            if (typeof anime !== 'undefined') {
                anime({ targets: $img[0], scale: [0.85, 1], opacity: [0, opacity], duration: 400, easing: 'easeOutBack' });
            }

            $('#photoproof_remove_watermark_btn').show();
            $('#photoproof_watermark_opacity_range').prop('disabled', false);

            // Sync la card "fake" Watermark → active visuelle
            ppSyncFakeCard($('#photoproof_global_watermark').closest('.pp-card'), true);
        });

        wm_frame.open();
    });

    $('#photoproof_remove_watermark_btn').on('click', function () {
        $('#photoproof_global_watermark').val('');
        $('#wm-preview-container').html('<p id="wm-placeholder" style="color:#94a3b8;">Aucun logo sélectionné</p>');
        $('#photoproof_watermark_opacity_range').prop('disabled', true);
        $(this).hide();
        wm_frame = null;

        // Sync la card "fake" Watermark → inactive visuelle
        ppSyncFakeCard($('#photoproof_global_watermark').closest('.pp-card'), false);
    });

    // ════════════════════════════════════════════════════════════════
    // 6. UPLOADER LOGO CUSTOM (inchangé)
    // ════════════════════════════════════════════════════════════════
    var logo_frame;

    $('#photoproof_upload_custom_logo_btn').on('click', function (e) {
        e.preventDefault();
        if (logo_frame) { logo_frame.open(); return; }

        logo_frame = wp.media({
            title:    'Logo personnalisé',
            button:   { text: 'Utiliser ce logo' },
            multiple: false
        });

        logo_frame.on('select', function () {
            var attachment = logo_frame.state().get('selection').first().toJSON();

            $('#photoproof_custom_logo').val(attachment.id);

            var $img = $('<img>', {
                id:    'custom-logo-live-preview',
                src:   attachment.url,
                style: 'max-width:150px; height:auto;'
            });
            $('#custom-logo-preview-container').empty().append($img);

            if (typeof anime !== 'undefined') {
                anime({ targets: $img[0], scale: [0.85, 1], opacity: [0, 1], duration: 400, easing: 'easeOutBack' });
            }

            $('#photoproof_remove_custom_logo_btn').show();
            if (typeof setDirty === 'function') setDirty();
        });

        logo_frame.open();
    });

    $('#photoproof_remove_custom_logo_btn').on('click', function () {
        $('#photoproof_custom_logo').val('');
        $('#custom-logo-preview-container').html('<p style="color:#94a3b8;">Logo du site par défaut</p>');
        $(this).hide();
        logo_frame = null;
        if (typeof setDirty === 'function') setDirty();
    });

    // ════════════════════════════════════════════════════════════════
    // 7. (Custom Login URL est maintenant always-active, pas de sync visuel)
    // ════════════════════════════════════════════════════════════════

    // ════════════════════════════════════════════════════════════════
    // HELPER : sync l'état visuel d'une card "fake" sans clic toggle
    // ════════════════════════════════════════════════════════════════
    function ppSyncFakeCard($card, isActive) {
        if (!$card || !$card.length) return;
        var wasActive = $card.hasClass('is-active');
        if (wasActive === isActive) return;

        $card.toggleClass('is-active', isActive);

        var $rect = $card.find('.pp-card-stroke rect').first();
        if ($rect.length) {
            var rect = $rect[0];
            var len  = rect.getTotalLength();
            if (len) {
                rect.style.strokeDasharray  = len;
                rect.style.strokeDashoffset = isActive ? 0 : len;
            }
        }

        var $status = $card.find('.pp-card-status').not('.is-locked').first();
        if ($status.length) {
            $status.text(isActive ? ppGetActiveLabel() : ppGetInactiveLabel());
        }

        // Any change to a fake card is a real form change
        if (typeof setDirty === 'function') setDirty();
    }

    // ════════════════════════════════════════════════════════════════
    // 8. SAVE BAR — clean/dirty states + discard + beforeunload
    // ════════════════════════════════════════════════════════════════
    var formIsDirty = false;
    var $savebar    = $('#pp-save-bar');
    var $savebarTxt = $('.pp-save-bar-text');
    var $form       = $('#pp-settings-form');

    function setDirty() {
        if (formIsDirty) return;
        formIsDirty = true;
        $savebar.removeClass('is-clean').addClass('is-dirty');
        $savebarTxt.text($savebarTxt.data('dirty'));
    }

    function setClean() {
        formIsDirty = false;
        $savebar.removeClass('is-dirty').addClass('is-clean');
        $savebarTxt.text($savebarTxt.data('clean'));
    }

    // Tout changement dans le form passe en "dirty"
    $form.on('change input', 'input, select, textarea', setDirty);

    // Note : les toggles de cards appellent setDirty() directement dans leur
    // handler (section 3) car stopPropagation() empêche la délégation ici.

    // wpColorPicker → la liaison dirty est faite dès l'init en section 1

    // Submit → on sort de l'état dirty pour éviter le warning beforeunload
    $form.on('submit', function () {
        formIsDirty = false;
    });

    // Discard → reload (drop les modifs non sauvegardées)
    $('#pp-save-bar-discard').on('click', function () {
        var msg = (typeof window.wp !== 'undefined' && wp.i18n && typeof wp.i18n.__ === 'function')
            ? wp.i18n.__('Discard unsaved changes?', 'photoproof')
            : 'Discard unsaved changes?';
        if (confirm(msg)) {
            formIsDirty = false;
            window.location.reload();
        }
    });

    // beforeunload → warn si modifs non sauvegardées
    $(window).on('beforeunload', function () {
        if (formIsDirty) {
            return 'You have unsaved changes. Are you sure you want to leave?';
        }
    });

});