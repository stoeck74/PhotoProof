/**
 * PhotoProof — admin-settings.js
 * Gère : navigation onglets, toggles, color picker, uploaders watermark & logo
 * Animations : anime.js v3 (MIT) — remplace GSAP
 */

jQuery(document).ready(function ($) {

    // ── 0. RESTAURATION DE L'ONGLET ACTIF APRÈS SAVE ──────────────────
    // WordPress vire le hash d'URL lors de la redirection post-save,
    // donc on persiste l'onglet actif via sessionStorage.
    var STORAGE_KEY = 'photoproof_active_tab';

    var savedTab = sessionStorage.getItem(STORAGE_KEY);
    if (savedTab) {
        var $navFromStorage = $('.pp-nav-item[data-target="' + savedTab + '"]');
        if ($navFromStorage.length) {
            $('.pp-nav-item').removeClass('active');
            $('.pp-section-content').removeClass('active');
            $navFromStorage.addClass('active');
            $('#section-' + savedTab).addClass('active');
        }
    }

    // Retirer la classe pp-loading pour révéler le contenu
    $('.pp-settings-page').removeClass('pp-loading');

    // Mémoriser l'onglet à chaque changement
    $('.pp-nav-item').on('click', function () {
        var target = $(this).data('target');
        if (target) sessionStorage.setItem(STORAGE_KEY, target);
    });

    // ── 1. COLOR PICKER ───────────────────────────────────────────────
    $('.pp-color-picker').wpColorPicker();

    // ── 2. NAVIGATION PAR ONGLETS ─────────────────────────────────────
    $('.pp-nav-item').on('click', function () {
        var target = $(this).data('target');
        if ($(this).hasClass('active')) return;

        var $current = $('.pp-section-content.active');
        var $next    = $('#section-' + target);

        if ( ! $next.length) return;

        $('.pp-nav-item').removeClass('active');
        $(this).addClass('active');

        if (typeof anime !== 'undefined') {
            anime({
                targets: $current[0],
                opacity: 0,
                translateX: -15,
                duration: 200,
                easing: 'easeInQuad',
                complete: function () {
                    $current.removeClass('active').hide().css({ opacity: '', transform: '' });
                    $next.show().addClass('active');
                    anime({
                        targets: $next[0],
                        opacity: [0, 1],
                        translateX: [15, 0],
                        duration: 350,
                        easing: 'easeOutQuad',
                    });
                }
            });
        } else {
            $current.removeClass('active').hide();
            $next.show().addClass('active');
        }
    });

    // ── 3. TOGGLES ────────────────────────────────────────────────────
    function animateToggle(checkboxId, detailsId) {
        var $checkbox = $(checkboxId);
        var $details  = $(detailsId);

        if ( ! $checkbox.length || ! $details.length) return;

        $checkbox.on('change', function () {
            if ($(this).is(':checked')) {
                $details.show();
                if (typeof anime !== 'undefined') {
                    anime({
                        targets: $details[0],
                        opacity: [0, 1],
                        translateY: [-8, 0],
                        duration: 350,
                        easing: 'easeOutQuad',
                    });
                }
            } else {
                if (typeof anime !== 'undefined') {
                    anime({
                        targets: $details[0],
                        opacity: 0,
                        translateY: -8,
                        duration: 250,
                        easing: 'easeInQuad',
                        complete: function () {
                            $details.hide().css({ opacity: '', transform: '' });
                        }
                    });
                } else {
                    $details.hide();
                }
            }
        });
    }

    animateToggle('#photoproof_enable_expiration',      '#expiration-details');
    animateToggle('#photoproof_enable_rename',          '#rename-details');
    animateToggle('#photoproof_enable_recommendations', '#recommendation-details');

    // ── 4. SLIDER OPACITÉ WATERMARK ───────────────────────────────────
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

    // ── 5. UPLOADER WATERMARK ─────────────────────────────────────────
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
        });

        wm_frame.open();
    });

    $('#photoproof_remove_watermark_btn').on('click', function () {
        $('#photoproof_global_watermark').val('');
        $('#wm-preview-container').html('<p id="wm-placeholder" style="color:#94a3b8;">Aucun logo sélectionné</p>');
        $('#photoproof_watermark_opacity_range').prop('disabled', true);
        $(this).hide();
        wm_frame = null;
    });

    // ── 6. UPLOADER LOGO CUSTOM ───────────────────────────────────────
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
                src:   attachment.url,
                style: 'max-width:150px; height:auto;'
            });
            $('#custom-logo-preview-container').empty().append($img);

            if (typeof anime !== 'undefined') {
                anime({ targets: $img[0], scale: [0.85, 1], opacity: [0, 1], duration: 400, easing: 'easeOutBack' });
            }

            $('#photoproof_remove_custom_logo_btn').show();
        });

        logo_frame.open();
    });

    $('#photoproof_remove_custom_logo_btn').on('click', function () {
        $('#photoproof_custom_logo').val('');
        $('#custom-logo-preview-container').html('<p style="color:#94a3b8;">Logo du site par défaut</p>');
        $(this).hide();
        logo_frame = null;
    });

});