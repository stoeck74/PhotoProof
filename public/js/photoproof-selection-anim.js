/**
 * PhotoProof — Selection Animation
 * Panier visuel avec animations GSAP
 *
 * Chargé uniquement si pp_enable_animations est activé dans les settings.
 * Dépend de photoproof-public.js (selectedIds, toggleSelect, openLightbox)
 */

(function ($) {
    'use strict';

    if (typeof gsap === 'undefined') {
        console.warn('PhotoProof Anim: GSAP non disponible.');
        return;
    }

    // ── Zone vignettes dans la barre ─────────────────────────────────
    var $bar        = $('#pp-selection-bar');
    var $barInner   = $bar.find('.pp-bar-inner');
    var $barInfo    = $bar.find('.pp-bar-info');
    var $barRight   = $bar.find('.pp-bar-right');

    // Injecter la zone vignettes entre pp-bar-info et pp-bar-right
    var $tray = $('<div class="pp-tray" id="pp-tray"></div>');
    $barInfo.after($tray);

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Retourne la vignette existante pour un attachment_id
     */
    function getTrayThumb(id) {
        return $tray.find('.pp-tray-thumb[data-id="' + id + '"]');
    }

    /**
     * Retourne le rect centre d'une card
     */
    function getCardRect($card) {
        var rect = $card[0].getBoundingClientRect();
        return {
            x: rect.left + rect.width / 2,
            y: rect.top  + rect.height / 2,
        };
    }

    /**
     * Retourne le rect de la zone de dépôt dans la barre
     */
    // Calcule la destination dans le tray — tient compte des vignettes déjà présentes
    function getTrayRect() {
        var rect       = $tray[0].getBoundingClientRect();
        var thumbCount = $tray.find('.pp-tray-thumb').length;
        var offset     = thumbCount * (52 + 6); // largeur vignette + gap
        return {
            x: rect.left + offset + 26, // centre de la prochaine vignette
            y: rect.top  + rect.height / 2,
        };
    }

    // ── Ajout d'une vignette — effet vol ─────────────────────────────
    // Le clone démarre depuis l'image elle-même (pas la card entière)
    function animateAddToTray(id, imgSrc, $card) {
        // Rect de l'image réelle (pas du wrapper card)
        var $img    = $card.find('.pp-card-img');
        var imgEl   = $img[0].getBoundingClientRect();

        var $clone = $('<img class="pp-flying-clone" src="' + imgSrc + '" />');
        $('body').append($clone);

        // Départ : position et taille exactes de l'image
        gsap.set($clone[0], {
            position:      'fixed',
            left:          imgEl.left,
            top:           imgEl.top,
            width:         imgEl.width,
            height:        imgEl.height,
            objectFit:     'cover',
            borderRadius:  'var(--pp-img-radius, 0px)',
            zIndex:        9999,
            opacity:       0.92,
            pointerEvents: 'none',
        });

        // Lire la destination APRÈS que la barre est visible
        setTimeout(function () {
            var trayRect = getTrayRect();

            // Insérer la vignette en avance (invisible) pour éviter le blink
            insertTrayThumb(id, imgSrc);
            var $thumb = getTrayThumb(id);
            gsap.set($thumb[0], { opacity: 0, scale: 0 });

            // Vol : rétrécit vers la taille finale tout en se déplaçant
            gsap.to($clone[0], {
                duration:     0.45,
                ease:         'power3.inOut',
                left:         trayRect.x - 26,
                top:          trayRect.y - 26,
                width:        52,
                height:       52,
                borderRadius: '6px',
                opacity:      0,
                onComplete: function () {
                    $clone.remove();
                    // Révéler la vignette à sa place
                    gsap.to($thumb[0], {
                        opacity:  1,
                        scale:    1,
                        duration: 0.18,
                        ease:     'power2.out',
                    });
                },
            });
        }, 50);
    }

    // Alias — plus de version séparée, une seule fonction suffit
    function animateAddToTraySimple(id, imgSrc, $card) {
        animateAddToTray(id, imgSrc, $card);
    }

    /**
     * Insère la vignette dans le tray — sans animation propre
     * L'animation d'apparition est gérée par animateAddToTray via le clone
     */
    function insertTrayThumb(id, imgSrc) {
        if (getTrayThumb(id).length) return; // déjà là

        var $thumb = $(
            '<div class="pp-tray-thumb" data-id="' + id + '">' +
                '<img src="' + imgSrc + '" class="pp-tray-img" />' +
                '<button class="pp-tray-remove" title="Retirer">×</button>' +
                '<button class="pp-tray-zoom" title="Voir en grand">⤢</button>' +
            '</div>'
        );

        $tray.append($thumb);
        $tray.scrollLeft($tray[0].scrollWidth);
    }

    // ── Suppression d'une vignette ───────────────────────────────────
    function animateRemoveFromTray(id) {
        var $thumb = getTrayThumb(id);
        if (!$thumb.length) return;

        gsap.to($thumb[0], {
            duration: 0.25,
            ease:     'power2.in',
            scale:    0,
            opacity:  0,
            onComplete: function () {
                $thumb.remove();
            },
        });
    }

    // ── Initialisation au chargement ─────────────────────────────────
    function initTrayFromSelection() {
        if (typeof pp_public === 'undefined') return;

        // Récupérer la sélection initiale via AJAX (déjà chargée par photoproof-public.js)
        // On écoute l'événement custom dispatché par photoproof-public.js
        $(document).on('pp:selectionLoaded', function (e, selectedIds) {
            selectedIds.forEach(function (id) {
                var $card = $('.pp-card[data-id="' + id + '"]');
                var imgSrc = $card.find('.pp-card-img').attr('src');
                if (imgSrc) insertTrayThumb(id, imgSrc);
            });
        });

        // Si la galerie est déjà verrouillée au chargement → pas de tray
        if (parseInt(pp_public.is_locked, 10) === 1) {
            $tray.hide();
        }
    }

    // ── Écoute des événements de sélection ───────────────────────────
    $(document).on('pp:photoSelected', function (e, data) {
        // data = { id, imgSrc, $card }
        var tryMotionPath = true;
        try {
            if (typeof gsap.plugins === 'undefined' || !gsap.plugins.motionPath) {
                tryMotionPath = false;
            }
        } catch (err) {
            tryMotionPath = false;
        }

        if (tryMotionPath) {
            animateAddToTray(data.id, data.imgSrc, data.$card);
        } else {
            animateAddToTraySimple(data.id, data.imgSrc, data.$card);
        }
    });

    $(document).on('pp:photoDeselected', function (e, data) {
        animateRemoveFromTray(data.id);
    });

    $(document).on('pp:galleryLocked', function () {
        // Galerie validée — masquer le tray proprement
        gsap.to($tray[0], {
            duration: 0.4,
            opacity:  0,
            y:        10,
            onComplete: function () { $tray.hide(); },
        });
    });

    // ── Interactions sur les vignettes ───────────────────────────────
    $tray.on('click', '.pp-tray-remove', function () {
        var id = parseInt($(this).closest('.pp-tray-thumb').data('id'), 10);
        // Déclencher la déselection dans photoproof-public.js
        $(document).trigger('pp:requestDeselect', [id]);
    });

    $tray.on('click', '.pp-tray-zoom', function () {
        var id = parseInt($(this).closest('.pp-tray-thumb').data('id'), 10);
        // Ouvrir la lightbox
        $(document).trigger('pp:requestLightbox', [id]);
    });

    // ── Init ─────────────────────────────────────────────────────────
    initTrayFromSelection();

})(jQuery);