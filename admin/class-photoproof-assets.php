<?php
/**
 * Gestion des scripts et styles pour l'administration
 */
class PhotoProof_Assets {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets( $hook ) {
        global $post;

        $is_settings_page = ( strpos( $hook, 'photoproof-settings' ) !== false );
        $is_gallery_post  = ( is_object($post) && $post->post_type === 'pp_gallery' );

        // Si on n'est ni sur les réglages ni sur une galerie, on ne charge rien
        if ( ! $is_settings_page && ! $is_gallery_post ) return;

        // --- 1. CSS COMMUN (Indispensable pour la Metabox ET les réglages) ---
        wp_enqueue_style(
            'pp-admin-css',
            PHOTOPROOF_URL . 'admin/css/admin-settings.css',
            array(),
            PHOTOPROOF_VERSION
        );

        // --- 2. DÉPENDANCES COMMUNES ---
        wp_enqueue_media();
        wp_enqueue_script( 'gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js', array(), '3.12.2', true );

        // --- 3. CAS : PAGE DE RÉGLAGES ---
        if ( $is_settings_page ) {
            // Sélecteur de couleur natif
            wp_enqueue_style( 'wp-color-picker' );

            wp_enqueue_script(
                'pp-settings-js',
                PHOTOPROOF_URL . 'admin/js/admin-settings.js',
                array( 'jquery', 'gsap', 'wp-color-picker' ), 
                PHOTOPROOF_VERSION,
                true
            );
        }

        // --- 4. CAS : ÉDITION D'UNE GALERIE ---
        if ( $is_gallery_post ) {
            wp_enqueue_script(
                'pp-gallery-js',
                PHOTOPROOF_URL . 'admin/js/admin-gallery.js',
                array( 'jquery', 'gsap' ),
                PHOTOPROOF_VERSION,
                true
            );

            wp_localize_script( 'pp-gallery-js', 'pp_vars', array(
                'post_id' => $post->ID,
                'nonce'   => wp_create_nonce( 'pp_upload_nonce' )
            ));
        }
    }
}