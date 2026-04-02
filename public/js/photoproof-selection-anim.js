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
    var barInitialHeight = 0;

    function openRecap(selectedIds) {
        if (recapOpen) return;
        recapOpen = true;

        barInitialHeight = $bar[0].getBoundingClientRect().height;

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

        // Bloquer le scroll du body pendant le récap
        var scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
        $('body').css({ 'overflow': 'hidden', 'padding-right': scrollbarWidth + 'px' });

        var adminBarH = $('#wpadminbar').length ? $('#wpadminbar').outerHeight() : 0;
        gsap.to($bar[0], {
            duration: 0.55,
            ease:     'power3.inOut',
            top:      adminBarH,
            height:   window.innerHeight - adminBarH,
            onComplete: function () {

                // 4. Contenu récap — structure depuis le PHP, JS remplit uniquement les données
                $barInner.hide();

                var $recapContent = $('<div class="pp-recap-content" id="pp-recap-content"></div>');

                // Header — cloné depuis le PHP
                var $header = $('<div class="pp-recap-bar-header"></div>');
                $header.append($('#pp-recap-anim-header').children().clone());
                $header.append($('<h2 class="pp-recap-title"></h2>').text(selectedIds.length + ' photos'));
                $recapContent.append($header);

                // Grille — items générés dynamiquement (données uniquement)
                var $recapGrid = $('<div class="pp-recap-bar-grid" id="pp-recap-bar-grid"></div>');
                selectedIds.forEach(function (id) {
                    var $card  = $('.pp-card[data-id="' + id + '"]');
                    var imgSrc = $card.find('.pp-card-img-wrap').data('full') || $card.find('.pp-card-img').attr('src');
                    var name   = $card.find('.pp-card-name').text();
                    var $item  = $('<div class="pp-recap-bar-item" data-id="' + id + '"></div>');
                    var $wrap  = $('<div class="pp-recap-bar-img-wrap"></div>');
                    $wrap.append($('<img class="pp-recap-bar-img" style="opacity:0" />').attr('src', imgSrc));
                    $wrap.append($('<button class="pp-recap-bar-remove" type="button">×</button>'));
                    $item.append($wrap).append($('<span class="pp-recap-bar-name"></span>').text(name));
                    $recapGrid.append($item);
                });
                $recapContent.append($recapGrid);

                // Footer — cloné depuis le PHP
                var $footer = $('<div class="pp-recap-bar-footer"></div>');
                $footer.append($('#pp-recap-anim-footer').children().clone(true));
                $recapContent.append($footer);

                $bar.append($recapContent);
                gsap.fromTo($recapContent[0], { opacity: 0 }, { opacity: 1, duration: 0.25 });

                // 5. Fade in des images — simple et performant
                setTimeout(function () {
                    $recapGrid.find('.pp-recap-bar-img').each(function (i) {
                        gsap.to(this, {
                            opacity: 1,
                            duration: 0.25,
                            delay: i * 0.02,
                            ease: 'power2.out',
                        });
                    });
                }, 80);

                // Événements récap animé
                $recapContent.on('click', '.pp-recap-bar-remove', function () {
                    var id = parseInt($(this).closest('.pp-recap-bar-item').data('id'), 10);
                    $(document).trigger('pp:requestDeselect', [id]);
                    $(this).closest('.pp-recap-bar-item').remove();
                    if ($recapGrid.find('.pp-recap-bar-item').length === 0) closeRecap();
                });
                $recapContent.on('click', '.pp-btn-recap-back', function () { closeRecap(); });
                $recapContent.on('click', '.pp-btn-recap-confirm', function () {
                    closeRecap(function () {
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
                $('body').css({ 'overflow': '', 'padding-right': '' });

                gsap.to($bar[0], {
                    duration: 0.45, ease: 'power3.inOut',
                    height:   barInitialHeight,
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