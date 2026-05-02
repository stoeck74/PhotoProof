<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * Gestion de l'affichage public (Front-end) — PhotoProof
 */
class PhotoProof_Public {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );

        add_action( 'wp_ajax_photoproof_save_selection',        array( $this, 'save_client_selection' ) );
        add_action( 'wp_ajax_nopriv_photoproof_save_selection', array( $this, 'save_client_selection' ) );

        add_action( 'wp_ajax_photoproof_get_selection',         array( $this, 'get_client_selection' ) );
        add_action( 'wp_ajax_nopriv_photoproof_get_selection',  array( $this, 'get_client_selection' ) );

        add_action( 'wp_ajax_photoproof_reopen_gallery', array( $this, 'reopen_gallery' ) );
    }

    public function enqueue_public_assets() {
        if ( ! is_singular( 'photoproof_gallery' ) ) {
            return;
        }

        // ── Désactiver scripts/styles du thème — template standalone ──
        add_action( 'wp_enqueue_scripts', function () {
            global $wp_scripts, $wp_styles;

            foreach ( $wp_scripts->queue as $handle ) {
                if (
                    strpos( $handle, 'photoproof-' ) === false &&
                    strpos( $handle, 'jquery' ) === false &&
                    strpos( $handle, 'wp-' ) === false &&
                    $handle !== 'imagesloaded'
                ) {
                    wp_dequeue_script( $handle );
                }
            }

            foreach ( $wp_styles->queue as $handle ) {
                if ( strpos( $handle, 'photoproof-' ) === false && strpos( $handle, 'admin-bar' ) === false && strpos( $handle, 'dashicons' ) === false ) {
                    wp_dequeue_style( $handle );
                }
            }
        }, 100 );

        // ── CSS ───────────────────────────────────────────────────────
        wp_enqueue_style(
            'photoproof-public-style',
            PHOTOPROOF_URL . 'public/css/photoproof-public.css',
            array(),
            PHOTOPROOF_VERSION
        );

        // ── ImagesLoaded — WP le bundle nativement dans wp-includes ──
        // Pas besoin de Masonry — grille CSS Grid pure
        wp_enqueue_script( 'imagesloaded' );

        // ── JS public ─────────────────────────────────────────────────
        wp_enqueue_script(
            'photoproof-public-js',
            PHOTOPROOF_URL . 'public/js/photoproof-public.js',
            array( 'jquery', 'imagesloaded' ),
            PHOTOPROOF_VERSION,
            true
        );

        // ── Animations panier visuel ──────────────────────────────────
        wp_enqueue_script(
            'photoproof-animejs',
            PHOTOPROOF_URL . 'admin/js/vendor/anime.min.js',
            array(),
            '3.2.2',
            true
        );
        wp_enqueue_script(
            'photoproof-selection-anim',
            PHOTOPROOF_URL . 'public/js/photoproof-selection-anim.js',
            array( 'jquery', 'photoproof-public-js', 'photoproof-animejs' ),
            PHOTOPROOF_VERSION,
            true
        );

        // Statut pour le JS
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT status FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
            get_the_ID()
        ) );
        $current_status = $row ? $row->status : 'publie';

        wp_localize_script( 'photoproof-public-js', 'photoproof_public', array(
            'ajax_url'  => admin_url( 'admin-ajax.php' ),
            'post_id'   => get_the_ID(),
            'nonce'     => wp_create_nonce( 'photoproof_client_selection_' . get_the_ID() ),
            'status'    => $current_status,
            'is_locked' => ( $current_status === 'valide' || $current_status === 'ferme' ) ? 1 : 0,
        ) );

        // ── CSS variables thème ───────────────────────────────────────
        $bg_color      = sanitize_hex_color( get_option( 'photoproof_color_bg',     '#f5f4f2' ) ) ?: '#f5f4f2';
        $active_color  = sanitize_hex_color( get_option( 'photoproof_color_active', '#2271b1' ) ) ?: '#2271b1';
        $text_color    = sanitize_hex_color( get_option( 'photoproof_color_text',   '#1e293b' ) ) ?: '#1e293b';
        $photo_rounded = get_option( 'photoproof_photo_rounded' );
        $img_radius    = $photo_rounded ? '8px' : '0px';

        wp_add_inline_style( 'photoproof-public-style', "
            :root {
                --pp-bg:         {$bg_color};
                --pp-active:     {$active_color};
                --pp-text:       {$text_color};
                --pp-img-radius: {$img_radius};
            }
        " );
    }

    /**
     * AJAX : sauvegarde la sélection du client
     */
    public function save_client_selection() {
        $post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => 'Galerie invalide.' ) );
        }

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'photoproof_client_selection_' . $post_id ) ) {
            wp_send_json_error( array( 'message' => 'Requête non autorisée.' ) );
        }

        if ( get_post_type( $post_id ) !== 'photoproof_gallery' ) {
            wp_send_json_error( array( 'message' => 'Type invalide.' ) );
        }

        if ( $this->is_gallery_locked( $post_id ) ) {
            wp_send_json_error( array( 'message' => 'Cette galerie est verrouillée.', 'locked' => true ) );
        }

        if ( ! $this->is_gallery_accessible( $post_id ) ) {
            wp_send_json_error( array( 'message' => 'Cette galerie n\'est plus accessible.' ) );
        }

        // ── VÉRIFICATION IDENTITÉ AVANT TOUTE ÉCRITURE ──────────────
        global $wpdb;
        $gallery_row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT client_id FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
            $post_id
        ) );

        $custom_login = get_option( 'photoproof_login_url' );
        $login_url    = $custom_login
            ? add_query_arg( 'redirect_to', urlencode( get_permalink( $post_id ) ), esc_url_raw( $custom_login ) )
            : wp_login_url( get_permalink( $post_id ) );

        // Si un client est assigné, seul ce client (ou un admin) peut écrire
        if ( $gallery_row && $gallery_row->client_id ) {
            if ( ! is_user_logged_in() ) {
                wp_send_json_error( array(
                    'message'       => 'Vous devez être connecté pour modifier cette sélection.',
                    'auth_required' => true,
                    'login_url'     => $login_url,
                ) );
            }
            if ( intval( $gallery_row->client_id ) !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( array(
                    'message'       => 'Vous n\'avez pas les autorisations nécessaires pour modifier cette galerie.',
                    'auth_required' => true,
                    'login_url'     => $login_url,
                ) );
            }
        }

        // ── TRAITEMENT DES IDs ────────────────────────────────────────
        $raw_ids      = isset( $_POST['selected_ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['selected_ids'] ) ) : array();
        $selected_ids = array_values( array_unique( array_filter( array_map( 'intval', $raw_ids ) ) ) );

        // Vérifier que chaque attachement appartient bien à cette galerie
        $selected_ids = array_values( array_filter( $selected_ids, function( $att_id ) use ( $post_id ) {
            return get_post_field( 'post_parent', $att_id ) == $post_id;
        } ) );

        update_post_meta( $post_id, '_photoproof_selected_photos', $selected_ids );

        $is_confirm = isset( $_POST['confirm'] ) && sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) === '1';

        if ( $is_confirm ) {

            // Connexion obligatoire pour la confirmation finale
            if ( ! is_user_logged_in() ) {
                wp_send_json_error( array(
                    'message'       => 'Vous devez être connecté pour valider votre sélection.',
                    'auth_required' => true,
                    'login_url'     => $login_url,
                ) );
            }

            if ( $gallery_row && $gallery_row->client_id
                && intval( $gallery_row->client_id ) !== get_current_user_id()
                && ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( array(
                    'message'       => 'Vous n\'avez pas les autorisations nécessaires pour valider cette galerie. Merci de vous connecter avec le bon compte.',
                    'auth_required' => true,
                    'login_url'     => $login_url,
                ) );
            }

            // ── VALIDATION ────────────────────────────────────────────
            $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prefix . 'photoproof_galleries',
                array( 'status' => 'valide' ),
                array( 'post_id' => $post_id ),
                array( '%s' ), array( '%d' )
            );

            $client_id = $gallery_row ? intval( $gallery_row->client_id ) : get_current_user_id();

            do_action( 'photoproof_gallery_selection_confirmed', $post_id, $client_id );

            wp_send_json_success( array( 'count' => count( $selected_ids ), 'locked' => true, 'message' => 'Sélection confirmée.' ) );
        }

        wp_send_json_success( array( 'count' => count( $selected_ids ), 'locked' => false, 'message' => 'Sélection enregistrée.' ) );
    }

    /**
     * AJAX : retourne la sélection actuelle
     */
    public function get_client_selection() {
        $post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;

        if ( ! $post_id ) wp_send_json_error( array( 'message' => 'Galerie invalide.' ) );

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'photoproof_client_selection_' . $post_id ) ) {
            wp_send_json_error( array( 'message' => 'Requête non autorisée.' ) );
        }

        $selected = get_post_meta( $post_id, '_photoproof_selected_photos', true );
        $selected = is_array( $selected ) ? array_map( 'intval', $selected ) : array();

        wp_send_json_success( array(
            'selected_ids' => $selected,
            'count'        => count( $selected ),
            'is_locked'    => $this->is_gallery_locked( $post_id ) ? 1 : 0,
        ) );
    }

    /**
     * AJAX : réouverture galerie (admin)
     */
    public function reopen_gallery() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Accès refusé.' ) );

        $post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;

        if ( ! $post_id ) wp_send_json_error( array( 'message' => 'Galerie invalide.' ) );

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'photoproof_reopen_' . $post_id ) ) {
            wp_send_json_error( array( 'message' => 'Requête non autorisée.' ) );
        }

        if ( get_post_type( $post_id ) !== 'photoproof_gallery' ) wp_send_json_error( array( 'message' => 'Type invalide.' ) );

        $mode = ( isset( $_POST['mode'] ) && sanitize_text_field( wp_unslash( $_POST['mode'] ) ) === 'reset' ) ? 'reset' : 'keep';

        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'photoproof_galleries', array( 'status' => 'publie' ), array( 'post_id' => $post_id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        if ( $mode === 'reset' ) update_post_meta( $post_id, '_photoproof_selected_photos', array() );

        wp_send_json_success( array( 'message' => $mode === 'reset' ? 'Galerie réouverte, sélection réinitialisée.' : 'Galerie réouverte, sélection conservée.', 'mode' => $mode ) );
    }

    /**
     * Vérifie si une galerie est verrouillée
     */
    public function is_gallery_locked( $post_id ) {
        global $wpdb;
        $cache_key = 'photoproof_gallery_status_' . $post_id;
        $row       = wp_cache_get( $cache_key, 'photoproof' );
        if ( false === $row ) {
            $row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT status FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
                $post_id
            ) );
            wp_cache_set( $cache_key, $row, 'photoproof', 60 );
        }
        return $row && in_array( $row->status, array( 'valide', 'ferme' ), true );
    }

    /**
     * Vérifie qu'une galerie est accessible
     */
    private function is_gallery_accessible( $post_id ) {
        global $wpdb;
        $cache_key = 'photoproof_gallery_status_' . $post_id;
        $row       = wp_cache_get( $cache_key, 'photoproof' );
        if ( false === $row ) {
            $row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT status FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
                $post_id
            ) );
            wp_cache_set( $cache_key, $row, 'photoproof', 60 );
        }

        if ( ! $row ) return false;
        if ( ! in_array( $row->status, array( 'publie', 'valide' ), true ) ) return false;

        if ( get_option( 'photoproof_enable_expiration' ) ) {
            $publish_timestamp = get_post_timestamp( $post_id );
            if ( time() > $publish_timestamp + ( 30 * DAY_IN_SECONDS ) ) return false;
        }

        return true;
    }
}