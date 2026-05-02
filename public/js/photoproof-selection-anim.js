/**
 * PhotoProof — Selection Animation
 * Panier visuel + récap animé (expand footer + vol vignettes)
 * Migrated from GSAP to anime.js v3 (MIT license)
 */

(function ($) {
    'use strict';

    if (typeof anime === 'undefined') {
        console.warn('PhotoProof Anim: anime.js non disponible.');
        return;
    }

    // ── Helpers anime.js — wrappers proches de l'API GSAP ────────────

    function animeSet(el, props) {
        if (props.clearProps) {
            props.clearProps.split(',').forEach(function(p) {
                el.style[p.trim()] = '';
            });
            return;
        }
        Object.keys(props).forEach(function(key) {
            el.style[key] = props[key];
        });
    }

    function animeTo(el, props) {
        var duration   = (props.duration || 0.3) * 1000;
        var delay      = (props.delay    || 0)   * 1000;
        var ease       = convertEase(props.ease);
        var onComplete = props.onComplete;

        var animeProps = { targets: el, duration: duration, delay: delay, easing: ease };

        var skip = ['duration','delay','ease','onComplete'];
        Object.keys(props).forEach(function(key) {
            if (skip.indexOf(key) === -1) animeProps[key] = props[key];
        });

        if (onComplete) animeProps.complete = onComplete;
        return anime(animeProps);
    }

    function animeFromTo(el, from, to) {
        Object.keys(from).forEach(function(key) {
            el.style[key] = from[key];
        });
        return animeTo(el, to);
    }

    function convertEase(gsapEase) {
        var map = {
            'power3.inOut': 'cubicBezier(0.645, 0.045, 0.355, 1.000)',
            'power3.out':   'easeOutCubic',
            'power2.out':   'easeOutQuad',
            'power2.in':    'easeInQuad',
            'power2.inOut': 'easeInOutQuad',
        };
        return (gsapEase && map[gsapEase]) ? map[gsapEase] : 'easeInOutQuad';
    }

    // ── Zone vignettes dans la barre ─────────────────────────────────
    var $bar     = $('#pp-selection-bar');
    var $barInfo = $bar.find('.pp-bar-info');
    var $tray    = $('<div class="pp-tray" id="pp-tray"></div>');
    $barInfo.after($tray);

    var recapOpen = false;

    function getTrayThumb(id) {
        return $tray.find('.pp-tray-thumb[data-id="' + id + '"]');
    }

    function getTrayRect() {
        var rect       = $tray[0].getBoundingClientRect();
        var thumbCount = $tray.find('.pp-tray-thumb').length;
        var offset     = thumbCount * (52 + 6);
        return { x: rect.left + offset + 26, y: rect.top + rect.height / 2 };
    }

    // ── Ajout vignette — vol depuis la card ──────────────────────────
    function animateAddToTray(id, imgSrc, $card) {
        var imgEl  = $card.find('.pp-card-img')[0].getBoundingClientRect();
        var $clone = $('<img class="pp-flying-clone" src="' + imgSrc + '" />');
        $('body').append($clone);

        animeSet($clone[0], {
            position: 'fixed', left: imgEl.left + 'px', top: imgEl.top + 'px',
            width: imgEl.width + 'px', height: imgEl.height + 'px',
            objectFit: 'cover', borderRadius: 'var(--pp-img-radius, 0px)',
            zIndex: '9999', opacity: '0.92', pointerEvents: 'none',
        });

        setTimeout(function () {
            var trayRect = getTrayRect();
            insertTrayThumb(id, imgSrc);
            var $thumb = getTrayThumb(id);
            animeSet($thumb[0], { opacity: '0', transform: 'scale(0)' });

            animeTo($clone[0], {
                duration: 0.45, ease: 'power3.inOut',
                left: (trayRect.x - 26) + 'px', top: (trayRect.y - 26) + 'px',
                width: '52px', height: '52px', borderRadius: '6px', opacity: 0,
                onComplete: function () {
                    $clone.remove();
                    animeTo($thumb[0], { opacity: 1, scale: 1, duration: 0.18, ease: 'power2.out' });
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
        animeTo($thumb[0], {
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
        var $barInner    = $bar.find('.pp-bar-inner');

        animeTo('#pp-grid',   { opacity: 0, duration: 0.25, ease: 'power2.in' });
        animeTo($barInner[0], { opacity: 0, duration: 0.2  });

        var scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
        $('body').css({ overflow: 'hidden', 'padding-right': scrollbarWidth + 'px' });

        var adminBarH = $('#wpadminbar').length ? $('#wpadminbar').outerHeight() : 0;

        animeTo($bar[0], {
            duration: 0.55, ease: 'power3.inOut',
            top:    adminBarH + 'px',
            height: (window.innerHeight - adminBarH) + 'px',
            onComplete: function () {

                $barInner.hide();
                var $recapContent = $('<div class="pp-recap-content" id="pp-recap-content"></div>');

                var $header = $('<div class="pp-recap-bar-header"></div>');
                $header.append($('#pp-recap-anim-header').children().clone());
                $header.append($('<h2 class="pp-recap-title"></h2>').text(selectedIds.length + ' photos'));
                $recapContent.append($header);

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

                var $footer = $('<div class="pp-recap-bar-footer"></div>');
                $footer.append($('#pp-recap-anim-footer').children().clone(true));
                $recapContent.append($footer);

                $bar.append($recapContent);
                animeFromTo($recapContent[0], { opacity: '0' }, { opacity: 1, duration: 0.25 });

                setTimeout(function () {
                    $recapGrid.find('.pp-recap-bar-img').each(function (i) {
                        animeTo(this, { opacity: 1, duration: 0.25, delay: i * 0.02, ease: 'power2.out' });
                    });
                }, 80);

                $recapContent.on('click', '.pp-recap-bar-remove', function () {
                    var id = parseInt($(this).closest('.pp-recap-bar-item').data('id'), 10);
                    $(document).trigger('pp:requestDeselect', [id]);
                    $(this).closest('.pp-recap-bar-item').remove();
                    if ($recapGrid.find('.pp-recap-bar-item').length === 0) closeRecap();
                });
                $recapContent.on('click', '.pp-btn-recap-back',    function () { closeRecap(); });
                $recapContent.on('click', '.pp-btn-recap-confirm', function () {
                    closeRecap(function () { $(document).trigger('pp:confirmSelection'); });
                });
            },
        });
    }

    function closeRecap(callback) {
        if (!recapOpen) return;
        var $recapContent = $('#pp-recap-content');
        var $barInner     = $bar.find('.pp-bar-inner');

        animeTo($recapContent[0], {
            opacity: 0, duration: 0.2,
            onComplete: function () {
                $recapContent.remove();
                $barInner.show();
                animeSet($barInner[0], { opacity: '0' });
                $('body').css({ overflow: '', 'padding-right': '' });

                animeTo($bar[0], {
                    duration: 0.45, ease: 'power3.inOut',
                    height: barInitialHeight + 'px',
                    onComplete: function () {
                        recapOpen = false;
                        $bar[0].style.top    = '';
                        $bar[0].style.height = '';
                        animeTo($barInner[0], { opacity: 1, duration: 0.2 });
                        animeTo('#pp-grid',   { opacity: 1, duration: 0.3 });
                        if (typeof callback === 'function') callback();
                    },
                });
            },
        });
    }

    // ── Écoute depuis photoproof-public.js ───────────────────────────
    $(document).on('pp:openRecap',  function (e, ids) { openRecap(ids); });
    $(document).on('pp:closeRecap', function ()       { closeRecap(); });

    // ── Événements sélection ─────────────────────────────────────────
    $(document).on('pp:selectionLoaded', function (e, ids) {
        ids.forEach(function (id) {
            var $card  = $('.pp-card[data-id="' + id + '"]');
            var imgSrc = $card.find('.pp-card-img').attr('src');
            if (imgSrc) insertTrayThumb(id, imgSrc);
        });
        if (parseInt(photoproof_public.is_locked, 10) === 1) $tray.hide();
    });

    $(document).on('pp:photoSelected',   function (e, data) { animateAddToTray(data.id, data.imgSrc, data.$card); });
    $(document).on('pp:photoDeselected', function (e, data) { animateRemoveFromTray(data.id); });

    $(document).on('pp:galleryLocked', function () {
        animeTo($tray[0], {
            duration: 0.4, opacity: 0, translateY: 10,
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