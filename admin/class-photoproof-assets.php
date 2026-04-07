<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * Gestion des scripts et styles pour l'administration — PhotoProof
 */
class PhotoProof_Assets {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets( $hook ) {
        global $post;

        // 1. Détection de la page de réglages
        $is_settings_page = ( strpos( $hook, 'photoproof-settings' ) !== false );

        // 2. Détection robuste du Post Type
        $current_post_type = '';
        if ( is_object( $post ) && isset( $post->post_type ) ) {
            $current_post_type = $post->post_type;
        } elseif ( isset( $_GET['post_type'] ) ) {
            $current_post_type = sanitize_text_field( wp_unslash( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule pour détection du post type
        } elseif ( isset( $_GET['post'] ) ) {
            $current_post_type = get_post_type( absint( wp_unslash( $_GET['post'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule pour détection du post type
        }

        $is_gallery_page = ( $current_post_type === 'pp_gallery' );

        if ( ! $is_settings_page && ! $is_gallery_page ) {
            return;
        }

        // ── CSS ──────────────────────────────────────────────────────
        wp_enqueue_style(
            'pp-admin-css',
            PHOTOPROOF_URL . 'admin/css/admin-settings.css',
            array(),
            PHOTOPROOF_VERSION
        );

        // ── DÉPENDANCES ───────────────────────────────────────────────
        wp_enqueue_media();

        wp_register_script(
            'gsap',
            PHOTOPROOF_URL . 'admin/js/vendor/gsap.min.js',
            array(),
            '3.12.2',
            true
        );

        // ── RÉGLAGES ──────────────────────────────────────────────────
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

        // ── METABOXE ──────────────────────────────────────────────────
        if ( $is_gallery_page ) {
            wp_enqueue_script( 'gsap' );
            wp_enqueue_script(
                'pp-gallery-js',
                PHOTOPROOF_URL . 'admin/js/admin-gallery.js',
                array( 'jquery', 'gsap' ),
                PHOTOPROOF_VERSION,
                true
            );

            $post_id = ( is_object( $post ) && $post->ID ) ? $post->ID : 0;

            $icons     = array( 'dot' => '●', 'star' => '★', 'diamond' => '◆', 'heart' => '❤' );
            $icon_key  = get_option( 'pp_global_recommendation_icon', 'star' );
            $reco_icon = isset( $icons[ $icon_key ] ) ? $icons[ $icon_key ] : '★';

            wp_localize_script( 'pp-gallery-js', 'pp_vars', array(
                'ajax_url'     => admin_url( 'admin-ajax.php' ),
                'post_id'      => $post_id,
                'nonce'        => wp_create_nonce( 'pp_upload_nonce' ),
                'export_nonce' => $post_id ? wp_create_nonce( 'pp_export_' . $post_id ) : '',
                'is_new_post'  => ( $post_id === 0 ) ? 1 : 0,
                'reco_icon'    => $reco_icon,
                'reco_enabled' => get_option( 'pp_enable_recommendations' ) ? 1 : 0,
            ) );
        }
    }
}