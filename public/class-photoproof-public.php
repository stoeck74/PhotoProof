<?php
/**
 * Gestion de l'affichage public (Front-end) — PhotoProof
 *
 * NOUVEAU :
 * - save_client_selection() passe le statut à 'valide' après confirmation
 * - Action AJAX pp_reopen_gallery pour le photographe (reset ou conserver)
 * - is_gallery_locked() vérifie si la galerie est verrouillée
 */
class PhotoProof_Public {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );

        // Sélection client
        add_action( 'wp_ajax_pp_save_selection',        array( $this, 'save_client_selection' ) );
        add_action( 'wp_ajax_nopriv_pp_save_selection', array( $this, 'save_client_selection' ) );

        // Récupération sélection courante
        add_action( 'wp_ajax_pp_get_selection',         array( $this, 'get_client_selection' ) );
        add_action( 'wp_ajax_nopriv_pp_get_selection',  array( $this, 'get_client_selection' ) );

        // NOUVEAU : réouverture galerie par le photographe
        add_action( 'wp_ajax_pp_reopen_gallery', array( $this, 'reopen_gallery' ) );
    }

    /**
     * Assets front-end
     */
    public function enqueue_public_assets() {
        if ( ! is_singular( 'pp_gallery' ) ) {
            return;
        }

        wp_enqueue_style(
            'pp-public-style',
            PHOTOPROOF_URL . 'public/css/photoproof-public.css',
            array(),
            PHOTOPROOF_VERSION
        );

        wp_enqueue_script(
            'pp-public-js',
            PHOTOPROOF_URL . 'public/js/photoproof-public.js',
            array( 'jquery' ),
            PHOTOPROOF_VERSION,
            true
        );

        // Récupération du statut pour le JS
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
            get_the_ID()
        ) );
        $current_status = $row ? $row->status : 'publie';

        wp_localize_script( 'pp-public-js', 'pp_public', array(
            'ajax_url'  => admin_url( 'admin-ajax.php' ),
            'post_id'   => get_the_ID(),
            'nonce'     => wp_create_nonce( 'pp_client_selection_' . get_the_ID() ),
            // NOUVEAU : statut passé au JS pour verrouiller la grille si besoin
            'status'    => $current_status,
            'is_locked' => ( $current_status === 'valide' || $current_status === 'ferme' ) ? 1 : 0,
        ) );

        $bg_color     = sanitize_hex_color( get_option( 'pp_color_bg',     '#ffffff' ) ) ?: '#ffffff';
        $active_color = sanitize_hex_color( get_option( 'pp_color_active', '#2271b1' ) ) ?: '#2271b1';
        $text_color   = sanitize_hex_color( get_option( 'pp_color_text',   '#1e293b' ) ) ?: '#1e293b';
        $photo_rounded = get_option( 'pp_photo_rounded' );
        $img_radius    = $photo_rounded ? '8px' : '0px';

        $custom_css = "
            :root {
                --pp-bg:         {$bg_color};
                --pp-active:     {$active_color};
                --pp-text:       {$text_color};
                --pp-img-radius: {$img_radius};
            }";

        wp_add_inline_style( 'pp-public-style', $custom_css );
    }

    /**
     * AJAX : sauvegarde la sélection du client
     *
     * NOUVEAU : si $confirm = true, passe le statut à 'valide' (irréversible côté client)
     */
    public function save_client_selection() {
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => 'Galerie invalide.' ) );
        }

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'pp_client_selection_' . $post_id ) ) {
            wp_send_json_error( array( 'message' => 'Requête non autorisée.' ) );
        }

        if ( get_post_type( $post_id ) !== 'pp_gallery' ) {
            wp_send_json_error( array( 'message' => 'Type invalide.' ) );
        }

        // NOUVEAU : bloquer si déjà verrouillée
        if ( $this->is_gallery_locked( $post_id ) ) {
            wp_send_json_error( array(
                'message' => 'Cette galerie est verrouillée. Contactez votre photographe.',
                'locked'  => true,
            ) );
        }

        if ( ! $this->is_gallery_accessible( $post_id ) ) {
            wp_send_json_error( array( 'message' => 'Cette galerie n\'est plus accessible.' ) );
        }

        $raw_ids      = isset( $_POST['selected_ids'] ) ? (array) $_POST['selected_ids'] : array();
        $selected_ids = array_values( array_unique( array_map( 'intval', $raw_ids ) ) );
        $selected_ids = array_filter( $selected_ids );

        update_post_meta( $post_id, '_pp_selected_photos', $selected_ids );

        // NOUVEAU : si c'est une confirmation finale, on verrouille la galerie
        $is_confirm = isset( $_POST['confirm'] ) && $_POST['confirm'] === '1';

        if ( $is_confirm ) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'photoproof_galleries',
                array( 'status' => 'valide' ),
                array( 'post_id' => $post_id ),
                array( '%s' ),
                array( '%d' )
            );

            wp_send_json_success( array(
                'count'    => count( $selected_ids ),
                'locked'   => true,
                'message'  => 'Sélection confirmée et enregistrée.',
            ) );
        }

        wp_send_json_success( array(
            'count'   => count( $selected_ids ),
            'locked'  => false,
            'message' => 'Sélection enregistrée.',
        ) );
    }

    /**
     * AJAX : retourne la sélection actuelle
     */
    public function get_client_selection() {
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => 'Galerie invalide.' ) );
        }

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'pp_client_selection_' . $post_id ) ) {
            wp_send_json_error( array( 'message' => 'Requête non autorisée.' ) );
        }

        $selected = get_post_meta( $post_id, '_pp_selected_photos', true );
        $selected = is_array( $selected ) ? array_map( 'intval', $selected ) : array();

        wp_send_json_success( array(
            'selected_ids' => $selected,
            'count'        => count( $selected ),
            'is_locked'    => $this->is_gallery_locked( $post_id ) ? 1 : 0,
        ) );
    }

    /**
     * AJAX : réouverture d'une galerie par le photographe (admin uniquement)
     *
     * NOUVEAU :
     * mode = 'reset'   → status = 'publie' + vide _pp_selected_photos
     * mode = 'keep'    → status = 'publie' + conserve la sélection existante
     */
    public function reopen_gallery() {
        // Admin uniquement
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => 'Galerie invalide.' ) );
        }

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'pp_reopen_' . $post_id ) ) {
            wp_send_json_error( array( 'message' => 'Requête non autorisée.' ) );
        }

        if ( get_post_type( $post_id ) !== 'pp_gallery' ) {
            wp_send_json_error( array( 'message' => 'Type invalide.' ) );
        }

        $mode = isset( $_POST['mode'] ) && $_POST['mode'] === 'reset' ? 'reset' : 'keep';

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'photoproof_galleries',
            array( 'status' => 'publie' ),
            array( 'post_id' => $post_id ),
            array( '%s' ),
            array( '%d' )
        );

        if ( $mode === 'reset' ) {
            update_post_meta( $post_id, '_pp_selected_photos', array() );
        }

        wp_send_json_success( array(
            'message' => $mode === 'reset'
                ? 'Galerie réouverte avec sélection réinitialisée.'
                : 'Galerie réouverte avec sélection conservée.',
            'mode'    => $mode,
        ) );
    }

    /**
     * Vérifie si une galerie est verrouillée (statut valide ou ferme)
     */
    public function is_gallery_locked( $post_id ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
            $post_id
        ) );
        return $row && in_array( $row->status, array( 'valide', 'ferme' ), true );
    }

    /**
     * Vérifie qu'une galerie est publiquement accessible
     */
    private function is_gallery_accessible( $post_id ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
            $post_id
        ) );

        if ( ! $row ) return false;

        if ( ! in_array( $row->status, array( 'publie', 'valide' ), true ) ) return false;

        if ( get_option( 'pp_enable_expiration' ) ) {
            $publish_timestamp = get_post_timestamp( $post_id );
            if ( time() > $publish_timestamp + ( 30 * DAY_IN_SECONDS ) ) return false;
        }

        return true;
    }
}