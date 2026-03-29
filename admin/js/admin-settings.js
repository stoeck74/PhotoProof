jQuery(document).ready(function($) {
    // Initialisation Color Picker
    $('.pp-color-picker').wpColorPicker();

    // --- 1. NAVIGATION PAR ONGLETS (GSAP) ---
    $('.pp-nav-item').on('click', function() {
        var target = $(this).data('target');
        if ($(this).hasClass('active')) return;

        $('.pp-nav-item').removeClass('active');
        $(this).addClass('active');

        var $current = $('.pp-section-content.active');
        var $next = $('#section-' + target);

        gsap.to($current, {
            duration: 0.2, opacity: 0, x: -15, onComplete: function() {
                $current.removeClass('active').hide();
                $next.show().addClass('active');
                gsap.fromTo($next, { opacity: 0, x: 15 }, { opacity: 1, x: 0, duration: 0.4 });
            }
        });
    });

    // --- 2. FONCTION UNIFIÉE POUR LES TOGGLES ---
    function animateToggle(checkboxId, detailsId) {
        $(checkboxId).on('change', function() {
            var $details = $(detailsId);
            if ($(this).is(':checked')) {
                $details.show();
                gsap.fromTo($details, 
                    { opacity: 0, height: 0, y: -10, overflow: 'hidden' }, 
                    { opacity: 1, height: 'auto', y: 0, duration: 0.4, ease: "power2.out" }
                );
            } else {
                gsap.to($details, { 
                    opacity: 0, height: 0, y: -10, duration: 0.3, 
                    onComplete: function() { $details.hide(); } 
                });
            }
        });
        
        // État initial au chargement
        if($(checkboxId).is(':checked')) $(detailsId).show();
    }

    animateToggle('#pp_enable_expiration', '#expiration-details');
    animateToggle('#pp_enable_rename', '#rename-details');
    animateToggle('#pp_enable_recommendations', '#recommendation-details');

    // --- 3. SLIDER D'OPACITÉ DU WATERMARK ---
    $('#pp_watermark_opacity_range').on('input', function() {
        var val = $(this).val();
        $('#opacity-val').text(val);
        gsap.to('#wm-live-preview', { opacity: val / 100, duration: 0.2 });
    });

    // --- 4. MEDIA UPLOADER POUR LE WATERMARK ---
    var wm_frame;
    $('#pp_upload_watermark_btn').on('click', function(e) {
        e.preventDefault();
        if (wm_frame) { wm_frame.open(); return; }
        
        wm_frame = wp.media({
            title: 'Logo du Watermark',
            button: { text: 'Utiliser ce logo' },
            multiple: false
        });

        wm_frame.on('select', function() {
            var attachment = wm_frame.state().get('selection').first().toJSON();
            $('#pp_global_watermark').val(attachment.id);
            var opacity = $('#pp_watermark_opacity_range').val() / 100;
            
            $('#wm-preview-container').html('<img id="wm-live-preview" src="'+attachment.url+'" style="max-width:150px; opacity:'+opacity+';">');
            gsap.from('#wm-live-preview', { scale: 0.8, opacity: 0, duration: 0.5, ease: "back.out" });
            $('#pp_remove_watermark_btn').show();
        });
        wm_frame.open();
    });

    $('#pp_remove_watermark_btn').on('click', function() {
        $('#pp_global_watermark').val('');
        $('#wm-preview-container').html('<p id="wm-placeholder">Aucun logo sélectionné</p>');
        $(this).hide();
    });
});