/**
 * PhotoProof — admin-settings.js
 * Gère : navigation onglets, toggles, color picker, uploaders watermark & logo
 *
 * CORRECTIONS :
 * - Navigation onglets fonctionnelle (sections sont maintenant des divs sœurs)
 * - Bouton #pp_remove_watermark_btn ajouté et géré
 * - Handler upload logo custom (#pp_upload_custom_logo_btn) implémenté
 * - Bouton suppression logo custom géré
 * - XSS preview watermark corrigé (construction DOM)
 * - Slider opacité sécurisé si #wm-live-preview absent
 */

jQuery(document).ready(function ($) {

    // ── 0. GARDE : vérification GSAP ──────────────────────────────────
    if (typeof gsap === 'undefined') {
        console.warn('PhotoProof : GSAP non chargé, animations désactivées.');
    }

    // ── 1. COLOR PICKER ───────────────────────────────────────────────
    $('.pp-color-picker').wpColorPicker();

    // ── 2. NAVIGATION PAR ONGLETS ─────────────────────────────────────
    // Le HTML est maintenant correct : les 4 sections sont des divs sœurs.
    // Cette fonction peut donc trouver #section-apparence, #section-design, etc.
    $('.pp-nav-item').on('click', function () {
        var target = $(this).data('target');
        if ($(this).hasClass('active')) return;

        var $current = $('.pp-section-content.active');
        var $next    = $('#section-' + target);

        if ( ! $next.length) return; // sécurité si la section n'existe pas

        $('.pp-nav-item').removeClass('active');
        $(this).addClass('active');

        if (typeof gsap !== 'undefined') {
            gsap.to($current, {
                duration: 0.2,
                opacity: 0,
                x: -15,
                onComplete: function () {
                    $current.removeClass('active').hide();
                    $next.show().addClass('active');
                    gsap.fromTo($next,
                        { opacity: 0, x: 15 },
                        { opacity: 1, x: 0, duration: 0.35 }
                    );
                }
            });
        } else {
            // Fallback sans GSAP
            $current.removeClass('active').hide();
            $next.show().addClass('active');
        }
    });

    // ── 3. TOGGLES (ACCORDÉONS) ───────────────────────────────────────
    // CORRECTION : état initial géré uniquement par le PHP (display:none/block)
    // Le JS ne reforce pas un .show() au chargement pour éviter les conflits.
    function animateToggle(checkboxId, detailsId) {
        var $checkbox = $(checkboxId);
        var $details  = $(detailsId);

        if ( ! $checkbox.length || ! $details.length) return;

        $checkbox.on('change', function () {
            if ($(this).is(':checked')) {
                $details.show();
                if (typeof gsap !== 'undefined') {
                    gsap.fromTo($details,
                        { opacity: 0, y: -8 },
                        { opacity: 1, y: 0, duration: 0.35, ease: 'power2.out' }
                    );
                }
            } else {
                if (typeof gsap !== 'undefined') {
                    gsap.to($details, {
                        opacity: 0,
                        y: -8,
                        duration: 0.25,
                        onComplete: function () { $details.hide(); }
                    });
                } else {
                    $details.hide();
                }
            }
        });
    }

    animateToggle('#pp_enable_expiration',    '#expiration-details');
    animateToggle('#pp_enable_rename',        '#rename-details');
    animateToggle('#pp_enable_recommendations', '#recommendation-details');

    // ── 4. SLIDER OPACITÉ WATERMARK ───────────────────────────────────
    $('#pp_watermark_opacity_range').on('input', function () {
        var val     = parseInt($(this).val(), 10);
        var opacity = val / 100;
        $('#opacity-val').text(val);

        // CORRECTION : on vérifie que le preview existe avant d'animer
        var $preview = $('#wm-live-preview');
        if ($preview.length && typeof gsap !== 'undefined') {
            gsap.to($preview[0], { opacity: opacity, duration: 0.2 });
        } else if ($preview.length) {
            $preview.css('opacity', opacity);
        }
    });

    // ── 5. UPLOADER WATERMARK ─────────────────────────────────────────
    var wm_frame;

    $('#pp_upload_watermark_btn').on('click', function (e) {
        e.preventDefault();
        if (wm_frame) { wm_frame.open(); return; }

        wm_frame = wp.media({
            title:    'Logo du Watermark',
            button:   { text: 'Utiliser ce logo' },
            multiple: false
        });

        wm_frame.on('select', function () {
            var attachment = wm_frame.state().get('selection').first().toJSON();
            var opacity    = parseInt($('#pp_watermark_opacity_range').val(), 10) / 100;

            $('#pp_global_watermark').val(attachment.id);

            // CORRECTION : construction DOM au lieu d'injection HTML brute (XSS)
            var $img = $('<img>', {
                id:    'wm-live-preview',
                src:   attachment.url,
                style: 'max-width:150px; opacity:' + opacity + ';'
            });
            $('#wm-preview-container').empty().append($img);

            if (typeof gsap !== 'undefined') {
                gsap.from($img[0], { scale: 0.85, opacity: 0, duration: 0.4, ease: 'back.out' });
            }

            // Activer le bouton supprimer et le slider
            $('#pp_remove_watermark_btn').show();
            $('#pp_watermark_opacity_range').prop('disabled', false);
        });

        wm_frame.open();
    });

    // CORRECTION : bouton suppression watermark (existait en JS mais pas en HTML — ajouté dans settings.php)
    $('#pp_remove_watermark_btn').on('click', function () {
        $('#pp_global_watermark').val('');
        $('#wm-preview-container').html('<p id="wm-placeholder" style="color:#94a3b8;">Aucun logo sélectionné</p>');
        $('#pp_watermark_opacity_range').prop('disabled', true);
        $(this).hide();
        wm_frame = null; // reset frame pour forcer une nouvelle sélection
    });

    // ── 6. UPLOADER LOGO CUSTOM ───────────────────────────────────────
    // CORRECTION : ce handler était totalement absent de la version originale
    var logo_frame;

    $('#pp_upload_custom_logo_btn').on('click', function (e) {
        e.preventDefault();
        if (logo_frame) { logo_frame.open(); return; }

        logo_frame = wp.media({
            title:    'Logo personnalisé',
            button:   { text: 'Utiliser ce logo' },
            multiple: false
        });

        logo_frame.on('select', function () {
            var attachment = logo_frame.state().get('selection').first().toJSON();

            $('#pp_custom_logo').val(attachment.id);

            // Construction DOM propre
            var $img = $('<img>', {
                src:   attachment.url,
                style: 'max-width:150px; height:auto;'
            });
            $('#custom-logo-preview-container').empty().append($img);

            if (typeof gsap !== 'undefined') {
                gsap.from($img[0], { scale: 0.85, opacity: 0, duration: 0.4, ease: 'back.out' });
            }

            $('#pp_remove_custom_logo_btn').show();
        });

        logo_frame.open();
    });

    // Suppression logo custom
    $('#pp_remove_custom_logo_btn').on('click', function () {
        $('#pp_custom_logo').val('');
        $('#custom-logo-preview-container').html('<p style="color:#94a3b8;">Logo du site par défaut</p>');
        $(this).hide();
        logo_frame = null;
    });

});