/**
 * PhotoProof — Selection Animation
 * Panier visuel + récap animé (expand footer + vol vignettes)
 */

(function ($) {
    'use strict';

    if (typeof gsap === 'undefined') {
        console.warn('PhotoProof Anim: GSAP non disponible.');
        return;
    }

    // ── Zone vignettes dans la barre ─────────────────────────────────
    var $bar     = $('#pp-selection-bar');
    var $barInfo = $bar.find('.pp-bar-info');
    var $tray    = $('<div class="pp-tray" id="pp-tray"></div>');
    $barInfo.after($tray);

    var recapOpen = false;

    // ── Helpers ──────────────────────────────────────────────────────
    function getTrayThumb(id) {
        return $tray.find('.pp-tray-thumb[data-id="' + id + '"]');
    }

    function getTrayRect() {
        var rect       = $tray[0].getBoundingClientRect();
        var thumbCount = $tray.find('.pp-tray-thumb').length;
        var offset     = thumbCount * (52 + 6);
        return {
            x: rect.left + offset + 26,
            y: rect.top  + rect.height / 2,
        };
    }

    // ── Ajout vignette — vol depuis la card ──────────────────────────
    function animateAddToTray(id, imgSrc, $card) {
        var $img  = $card.find('.pp-card-img');
        var imgEl = $img[0].getBoundingClientRect();

        var $clone = $('<img class="pp-flying-clone" src="' + imgSrc + '" />');
        $('body').append($clone);

        gsap.set($clone[0], {
            position: 'fixed', left: imgEl.left, top: imgEl.top,
            width: imgEl.width, height: imgEl.height,
            objectFit: 'cover', borderRadius: 'var(--pp-img-radius, 0px)',
            zIndex: 9999, opacity: 0.92, pointerEvents: 'none',
        });

        setTimeout(function () {
            var trayRect = getTrayRect();
            insertTrayThumb(id, imgSrc);
            var $thumb = getTrayThumb(id);
            gsap.set($thumb[0], { opacity: 0, scale: 0 });

            gsap.to($clone[0], {
                duration: 0.45, ease: 'power3.inOut',
                left: trayRect.x - 26, top: trayRect.y - 26,
                width: 52, height: 52, borderRadius: '6px', opacity: 0,
                onComplete: function () {
                    $clone.remove();
                    gsap.to($thumb[0], { opacity: 1, scale: 1, duration: 0.18, ease: 'power2.out' });
                },
            });
        }, 50);
    }

    function insertTrayThumb(id, imgSrc) {
        if (getTrayThumb(id).length) return;
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

    function animateRemoveFromTray(id) {
        var $thumb = getTrayThumb(id);
        if (!$thumb.length) return;
        gsap.to($thumb[0], {
            duration: 0.25, ease: 'power2.in', scale: 0, opacity: 0,
            onComplete: function () { $thumb.remove(); },
        });
    }

    // ── RÉCAP ANIMÉ ──────────────────────────────────────────────────
    function openRecap(selectedIds) {
        if (recapOpen) return;
        recapOpen = true;

        // 1. Snapshot des positions des vignettes dans le tray
        var thumbRects = {};
        $tray.find('.pp-tray-thumb').each(function () {
            var id   = parseInt($(this).data('id'), 10);
            var rect = this.getBoundingClientRect();
            thumbRects[id] = rect;
        });

        // 2. Masquer la grille
        gsap.to('#pp-grid', { opacity: 0, duration: 0.25, ease: 'power2.in' });

        // 3. Expand le footer vers le haut
        var $barInner = $bar.find('.pp-bar-inner');
        gsap.to($barInner[0], { opacity: 0, duration: 0.2 });

        gsap.to($bar[0], {
            duration: 0.55,
            ease:     'power3.inOut',
            top:      0,
            height:   window.innerHeight,
            onComplete: function () {

                // 4. Injecter le contenu récap
                $barInner.hide();
                var $recapContent = $(
                    '<div class="pp-recap-content" id="pp-recap-content">' +
                        '<div class="pp-recap-bar-header">' +
                            '<button class="pp-btn-recap-back" id="pp-recap-back">← Revenir modifier</button>' +
                            '<div>' +
                                '<p class="pp-recap-eyebrow">Récapitulatif</p>' +
                                '<h2 class="pp-recap-title">' + selectedIds.length + ' photos sélectionnées</h2>' +
                            '</div>' +
                            '<button class="pp-btn-recap-confirm" id="pp-recap-confirm">Confirmer ma sélection</button>' +
                        '</div>' +
                        '<div class="pp-recap-bar-grid" id="pp-recap-bar-grid"></div>' +
                    '</div>'
                );

                var $recapGrid = $recapContent.find('#pp-recap-bar-grid');

                selectedIds.forEach(function (id) {
                    var $card  = $('.pp-card[data-id="' + id + '"]');
                    var imgSrc = $card.find('.pp-card-img-wrap').data('full') || $card.find('.pp-card-img').attr('src');
                    var name   = $card.find('.pp-card-name').text();
                    $recapGrid.append(
                        '<div class="pp-recap-bar-item" data-id="' + id + '">' +
                            '<div class="pp-recap-bar-img-wrap">' +
                                '<img src="' + imgSrc + '" class="pp-recap-bar-img" style="opacity:0" />' +
                                '<button class="pp-recap-bar-remove">×</button>' +
                            '</div>' +
                            '<span class="pp-recap-bar-name">' + name + '</span>' +
                        '</div>'
                    );
                });

                $bar.append($recapContent);
                gsap.fromTo($recapContent[0],
                    { opacity: 0 },
                    { opacity: 1, duration: 0.25 }
                );

                // 5. Vol des vignettes vers leurs positions dans la grille
                setTimeout(function () {
                    $recapGrid.find('.pp-recap-bar-item').each(function (i) {
                        var id       = parseInt($(this).data('id'), 10);
                        var srcRect  = thumbRects[id];
                        var $img     = $(this).find('.pp-recap-bar-img');
                        var destRect = $img[0].getBoundingClientRect();

                        if (!srcRect || !destRect.width) return;

                        var $clone = $('<img class="pp-flying-clone" src="' + $img.attr('src') + '" />');
                        $('body').append($clone);

                        gsap.set($clone[0], {
                            position: 'fixed',
                            left: srcRect.left, top: srcRect.top,
                            width: srcRect.width, height: srcRect.height,
                            objectFit: 'cover', borderRadius: '6px',
                            zIndex: 9999, opacity: 1, pointerEvents: 'none',
                        });

                        gsap.to($clone[0], {
                            duration: 0.45,
                            delay:    i * 0.035,
                            ease:     'power2.inOut',
                            left:     destRect.left,
                            top:      destRect.top,
                            width:    destRect.width,
                            height:   destRect.height,
                            borderRadius: 'var(--pp-img-radius, 0px)',
                            onComplete: function () {
                                $clone.remove();
                                gsap.to($img[0], { opacity: 1, duration: 0.15 });
                            },
                        });
                    });
                }, 80);

                // Désélection depuis le récap
                $recapContent.on('click', '.pp-recap-bar-remove', function () {
                    var id = parseInt($(this).closest('.pp-recap-bar-item').data('id'), 10);
                    $(document).trigger('pp:requestDeselect', [id]);
                    $(this).closest('.pp-recap-bar-item').remove();
                    var remaining = $recapGrid.find('.pp-recap-bar-item').length;
                    if (remaining === 0) closeRecap();
                });

                $('#pp-recap-back').on('click', function () { closeRecap(); });
                $('#pp-recap-confirm').on('click', function () {
                    closeRecap(function () {
                        // Confirmer directement — pas de modal intermédiaire
                        $(document).trigger('pp:confirmSelection');
                    });
                });
            },
        });
    }

    function closeRecap(callback) {
        if (!recapOpen) return;

        var $recapContent = $('#pp-recap-content');
        var $barInner     = $bar.find('.pp-bar-inner');

        gsap.to($recapContent[0], {
            opacity: 0, duration: 0.2,
            onComplete: function () {
                $recapContent.remove();
                $barInner.show();
                gsap.set($barInner[0], { opacity: 0 });

                gsap.to($bar[0], {
                    duration: 0.45, ease: 'power3.inOut',
                    height:   'auto',
                    onComplete: function () {
                        recapOpen = false;
                        gsap.set($bar[0], { clearProps: 'top,height' });
                        gsap.to($barInner[0], { opacity: 1, duration: 0.2 });
                        gsap.to('#pp-grid', { opacity: 1, duration: 0.3 });
                        if (typeof callback === 'function') callback();
                    },
                });
            },
        });
    }

    // ── Écoute depuis photoproof-public.js ───────────────────────────
    $(document).on('pp:openRecap', function (e, ids) { openRecap(ids); });
    $(document).on('pp:closeRecap', function () { closeRecap(); });

    // ── Événements sélection ─────────────────────────────────────────
    $(document).on('pp:selectionLoaded', function (e, ids) {
        ids.forEach(function (id) {
            var $card  = $('.pp-card[data-id="' + id + '"]');
            var imgSrc = $card.find('.pp-card-img').attr('src');
            if (imgSrc) insertTrayThumb(id, imgSrc);
        });
        if (parseInt(pp_public.is_locked, 10) === 1) $tray.hide();
    });

    $(document).on('pp:photoSelected', function (e, data) {
        animateAddToTray(data.id, data.imgSrc, data.$card);
    });

    $(document).on('pp:photoDeselected', function (e, data) {
        animateRemoveFromTray(data.id);
    });

    $(document).on('pp:galleryLocked', function () {
        gsap.to($tray[0], {
            duration: 0.4, opacity: 0, y: 10,
            onComplete: function () { $tray.hide(); },
        });
    });

    // ── Interactions tray ────────────────────────────────────────────
    $tray.on('click', '.pp-tray-remove', function () {
        $(document).trigger('pp:requestDeselect', [parseInt($(this).closest('.pp-tray-thumb').data('id'), 10)]);
    });

    $tray.on('click', '.pp-tray-zoom', function () {
        $(document).trigger('pp:requestLightbox', [parseInt($(this).closest('.pp-tray-thumb').data('id'), 10)]);
    });

})(jQuery);