/**
 * PhotoProof - Administration JS
 * Gère l'uploader massif et les animations GSAP
 */
jQuery(document).ready(function($) {
    
    var frame;
    var $previewContainer = $('#pp-gallery-preview');

    // 1. GESTION DE L'UPLOADER (MEDIA FRAME)
    $('#pp_upload_btn').on('click', function(e) {
        e.preventDefault();

        // Si la fenêtre existe déjà, on l'ouvre directement
        if (frame) {
            frame.open();
            return;
        }

        // Création de la fenêtre de média
        frame = wp.media({
            title: 'Ajouter des photos à la galerie PhotoProof',
            button: { text: 'Importer dans la galerie' },
            multiple: true // Autorise la sélection multiple
        });

        // Lors de la validation de la sélection
        frame.on('select', function() {
            var selection = frame.state().get('selection');
            
            selection.map(function(attachment) {
                attachment = attachment.toJSON();

                // On crée le HTML de la miniature (cachée par défaut pour GSAP)
                var thumbHtml = `
                    <div class="pp-thumb" style="opacity:0; position:relative; cursor:pointer;">
                        <div style="border-radius:10px; overflow:hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); background:#fff;">
                            <img src="${attachment.url}" style="width:100%; height:120px; object-fit:cover; display:block;">
                            <div class="pp-thumb-info" style="padding:8px; font-size:10px; color:#666; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                ${attachment.filename}
                            </div>
                        </div>
                    </div>
                `;

                var $thumb = $(thumbHtml);
                $previewContainer.append($thumb);
            });

            // ANIMATION GSAP : Apparition en cascade (Stagger)
            gsap.fromTo(".pp-thumb", 
                { 
                    opacity: 0, 
                    y: 30, 
                    scale: 0.8 
                }, 
                { 
                    duration: 0.6, 
                    opacity: 1, 
                    y: 0, 
                    scale: 1, 
                    stagger: 0.08, 
                    ease: "back.out(1.7)" 
                }
            );
        });

        frame.open();
    });

    // 2. MICRO-INTERACTIONS (HOVER EFFECTS)
    // On utilise la délégation d'événement car les éléments sont ajoutés dynamiquement
    $(document).on('mouseenter', '.pp-thumb', function() {
        gsap.to($(this), { 
            scale: 1.05, 
            y: -5,
            duration: 0.3, 
            ease: "power2.out",
            boxShadow: "0 15px 30px rgba(0,0,0,0.2)"
        });
    }).on('mouseleave', '.pp-thumb', function() {
        gsap.to($(this), { 
            scale: 1, 
            y: 0,
            duration: 0.3, 
            ease: "power2.inOut",
            boxShadow: "0 4px 10px rgba(0,0,0,0.1)"
        });
    });

    // 3. EFFET SUR LA ZONE D'UPLOAD (DRAG OVER SIMULATION)
    $('#pp_upload_btn').on('mouseenter', function() {
        gsap.to(this, { backgroundColor: '#ffffff', borderColor: '#2271b1', duration: 0.2 });
    }).on('mouseleave', function() {
        gsap.to(this, { backgroundColor: '#f0f6fb', borderColor: '#b5bfc9', duration: 0.2 });
    });

});