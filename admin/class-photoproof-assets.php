<?php
/**
 * Gestion des scripts et styles pour l'administration
 *
 * CORRECTIONS :
 * - time() remplacé par PHOTOPROOF_VERSION
 * - GSAP servi en local (vendor) plutôt que CDN externe
 * - export_nonce ajouté dans pp_vars
 * - Commentaire sur le cas post_id = 0 (nouvelle galerie)
 */
class PhotoProof_Assets {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets( $hook ) {
        global $post, $pagenow;

        // 1. Détection de la page de réglages
        $is_settings_page = ( strpos( $hook, 'photoproof-settings' ) !== false );

        // 2. Détection robuste du Post Type (fonctionne sur edit ET post-new)
        $current_post_type = '';
        if ( is_object( $post ) && isset( $post->post_type ) ) {
            $current_post_type = $post->post_type;
        } elseif ( isset( $_GET['post_type'] ) ) {
            $current_post_type = sanitize_text_field( $_GET['post_type'] );
        } elseif ( isset( $_GET['post'] ) ) {
            $current_post_type = get_post_type( intval( $_GET['post'] ) );
        }

        $is_gallery_page = ( $current_post_type === 'pp_gallery' );

        // Si on n'est pas sur une page PhotoProof, on s'arrête
        if ( ! $is_settings_page && ! $is_gallery_page ) {
            return;
        }

        // ── 1. CSS PRINCIPAL ──────────────────────────────────────────
        // CORRECTION : PHOTOPROOF_VERSION au lieu de time()
        wp_enqueue_style(
            'pp-admin-css',
            PHOTOPROOF_URL . 'admin/css/admin-settings.css',
            array(),
            PHOTOPROOF_VERSION
        );

        // ── 2. DÉPENDANCES ────────────────────────────────────────────
        wp_enqueue_media();

        // CORRECTION : GSAP servi en local pour éviter dépendance CDN externe
        // Déposer gsap.min.js dans admin/js/vendor/gsap.min.js
        wp_register_script(
            'gsap',
            PHOTOPROOF_URL . 'admin/js/vendor/gsap.min.js',
            array(),
            '3.12.2',
            true
        );

        // ── 3. LOGIQUE RÉGLAGES ───────────────────────────────────────
        if ( $is_settings_page ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'gsap' );
            wp_enqueue_script(
                'pp-settings-js',
                PHOTOPROOF_URL . 'admin/js/admin-settings.js',
                array( 'jquery', 'gsap', 'wp-color-picker' ),
                PHOTOPROOF_VERSION,
                true
            );
        }

        // ── 4. LOGIQUE METABOXE ───────────────────────────────────────
        if ( $is_gallery_page ) {
            wp_enqueue_script( 'gsap' );
            wp_enqueue_script(
                'pp-gallery-js',
                PHOTOPROOF_URL . 'admin/js/admin-gallery.js',
                array( 'jquery', 'gsap' ),
                PHOTOPROOF_VERSION,
                true
            );

            // Sur post-new.php, $post->ID vaut 0.
            // L'upload est bloqué côté JS tant que post_id === 0.
            // Le JS devra déclencher une sauvegarde automatique avant d'uploader.
            $post_id = ( is_object( $post ) && $post->ID ) ? $post->ID : 0;

            wp_localize_script( 'pp-gallery-js', 'pp_vars', array(
                'ajax_url'     => admin_url( 'admin-ajax.php' ),
                'post_id'      => $post_id,
                'nonce'        => wp_create_nonce( 'pp_upload_nonce' ),
                // CORRECTION : nonce export disponible dans le JS si besoin
                'export_nonce' => $post_id ? wp_create_nonce( 'pp_export_' . $post_id ) : '',
                'is_new_post'  => ( $post_id === 0 ) ? 1 : 0,
            ) );
        }
    }
}