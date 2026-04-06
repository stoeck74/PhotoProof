<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * Logique de gestion des fichiers — PhotoProof
 *
 * AJAX :
 * - pp_upload_photo         : reçoit un fichier, le place dans photoproof/gallery-{id}/
 * - pp_detach_photo         : retire un attachement d'une galerie
 * - pp_toggle_recommendation: marque/démarque une photo comme recommandée
 * - pp_get_gallery_photos   : retourne les photos d'une galerie avec statut
 */
class PhotoProof_Uploader {

    public function __construct() {
        // Upload custom — le filtre upload_dir est appliqué dans handle_upload_photo
        add_action( 'wp_ajax_pp_upload_photo',          array( $this, 'handle_upload_photo' ) );
        add_action( 'wp_ajax_pp_detach_photo',          array( $this, 'handle_detach_photo' ) );
        add_action( 'wp_ajax_pp_toggle_recommendation', array( $this, 'handle_toggle_recommendation' ) );
        add_action( 'wp_ajax_pp_get_gallery_photos',    array( $this, 'handle_get_gallery_photos' ) );
    }

    /**
     * AJAX : reçoit un fichier et le place dans photoproof/gallery-{id}/
     *
     * Utilise wp_handle_upload() avec un filtre upload_dir temporaire
     * pour rediriger vers le bon dossier sans polluer la médiathèque WP standard.
     */
    public function handle_upload_photo() {
        check_ajax_referer( 'pp_upload_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => 'Droits insuffisants.' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

        if ( ! $post_id || get_post_type( $post_id ) !== 'pp_gallery' ) {
            wp_send_json_error( array( 'message' => 'Galerie invalide.' ) );
        }

        if ( empty( $_FILES['file'] ) ) {
            wp_send_json_error( array( 'message' => 'Aucun fichier reçu.' ) );
        }

        // Filtre temporaire pour rediriger l'upload
        $self = $this;
        $dir_filter = function( $path ) use ( $post_id, $self, &$dir_filter ) {
            remove_filter( 'upload_dir', $dir_filter );
            return $self->get_gallery_upload_dir( $path, $post_id );
        };
        add_filter( 'upload_dir', $dir_filter );

        // Traitement du fichier via l'API WP
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $overrides = array(
            'test_form' => false,
            'test_size' => true,
        );

        $uploaded = wp_handle_upload( $_FILES['file'], $overrides );

        if ( isset( $uploaded['error'] ) ) {
            wp_send_json_error( array( 'message' => $uploaded['error'] ) );
        }

        // Créer l'attachement WP
        $attachment = array(
            'post_mime_type' => $uploaded['type'],
            'post_title'     => sanitize_file_name( pathinfo( $uploaded['file'], PATHINFO_FILENAME ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => $post_id,
        );

        $attachment_id = wp_insert_attachment( $attachment, $uploaded['file'], $post_id );

        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
        }

        // Générer les métadonnées (thumbnails)
        $metadata = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] );
        wp_update_attachment_metadata( $attachment_id, $metadata );

        // Marquer comme photo PhotoProof — exclue de la médiathèque standard
        update_post_meta( $attachment_id, '_pp_gallery_photo', '1' );

        // Stocker le nom cible pour le renommage différé
        do_action( 'pp_attachment_uploaded', $attachment_id, $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        // Mettre à jour la liste des photos de la galerie
        $existing = get_post_meta( $post_id, '_pp_gallery_photos', true );
        $existing = is_array( $existing ) ? $existing : array();
        $existing[] = $attachment_id;
        update_post_meta( $post_id, '_pp_gallery_photos', array_unique( $existing ) );

        // Retourner les infos pour affichage dans la grille
        $thumb = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
        $file  = get_attached_file( $attachment_id );
        $target = get_post_meta( $attachment_id, '_pp_target_filename', true );

        wp_send_json_success( array(
            'id'          => $attachment_id,
            'thumb_url'   => $thumb ? $thumb[0] : $uploaded['url'],
            'filename'    => $target ?: basename( $uploaded['file'] ),
            'recommended' => false,
        ) );
    }

    /**
     * Retourne le dossier d'upload pour une galerie
     */
    public function get_gallery_upload_dir( $path, $post_id ) {
        $subdir = '/photoproof/gallery-' . $post_id;

        $path['path']   = $path['basedir'] . $subdir;
        $path['url']    = $path['baseurl'] . $subdir;
        $path['subdir'] = $subdir;

        if ( ! file_exists( $path['path'] ) ) {
            wp_mkdir_p( $path['path'] );
            file_put_contents( $path['path'] . '/index.php', '<?php // Silence is golden' );
        }

        return $path;
    }

    /**
     * AJAX : retire un attachement d'une galerie
     */
    public function handle_detach_photo() {
        check_ajax_referer( 'pp_upload_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Droits insuffisants.' ) );
        }

        $post_id       = isset( $_POST['post_id'] )       ? intval( $_POST['post_id'] )       : 0;
        $attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;

        if ( ! $post_id || ! $attachment_id ) {
            wp_send_json_error( array( 'message' => 'Paramètres invalides.' ) );
        }

        // Vérifier que l'attachement appartient bien à cette galerie
        if ( get_post_field( 'post_parent', $attachment_id ) != $post_id ) {
            wp_send_json_error( array( 'message' => 'Cet attachement n\'appartient pas à cette galerie.' ) );
        }

        // Détacher du post parent
        wp_update_post( array( 'ID' => $attachment_id, 'post_parent' => 0 ) );

        // Retirer de la liste des photos
        $existing = get_post_meta( $post_id, '_pp_gallery_photos', true );
        $existing = is_array( $existing ) ? $existing : array();
        update_post_meta( $post_id, '_pp_gallery_photos',
            array_values( array_diff( $existing, array( $attachment_id ) ) )
        );

        // Retirer de la sélection client si présent
        $selection = get_post_meta( $post_id, '_pp_selected_photos', true );
        if ( is_array( $selection ) && in_array( $attachment_id, $selection, true ) ) {
            update_post_meta( $post_id, '_pp_selected_photos',
                array_values( array_diff( $selection, array( $attachment_id ) ) )
            );
        }

        delete_post_meta( $attachment_id, '_pp_recommended' );
        delete_post_meta( $attachment_id, '_pp_target_filename' );

        wp_send_json_success();
    }

    /**
     * AJAX : toggle recommandation sur une photo
     */
    public function handle_toggle_recommendation() {
        check_ajax_referer( 'pp_upload_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Droits insuffisants.' ) );
        }

        $post_id       = isset( $_POST['post_id'] )       ? intval( $_POST['post_id'] )       : 0;
        $attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
        $recommended   = isset( $_POST['recommended'] ) && sanitize_text_field( wp_unslash( $_POST['recommended'] ) ) === '1';

        if ( ! $post_id || ! $attachment_id ) {
            wp_send_json_error( array( 'message' => 'Paramètres invalides.' ) );
        }

        // Vérifier que l'attachement appartient bien à cette galerie
        if ( get_post_field( 'post_parent', $attachment_id ) != $post_id ) {
            wp_send_json_error( array( 'message' => 'Cet attachement n\'appartient pas à cette galerie.' ) );
        }

        if ( $recommended ) {
            update_post_meta( $attachment_id, '_pp_recommended', '1' );
        } else {
            delete_post_meta( $attachment_id, '_pp_recommended' );
        }

        wp_send_json_success( array(
            'attachment_id' => $attachment_id,
            'recommended'   => $recommended,
        ) );
    }

    /**
     * AJAX : retourne les photos d'une galerie
     */
    public function handle_get_gallery_photos() {
        check_ajax_referer( 'pp_upload_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Droits insuffisants.' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

        if ( ! $post_id || get_post_type( $post_id ) !== 'pp_gallery' ) {
            wp_send_json_error( array( 'message' => 'Galerie invalide.' ) );
        }

        $attachments = get_posts( array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'post_parent'    => $post_id,
            'posts_per_page' => -1,
            'orderby'        => 'menu_order date',
            'order'          => 'ASC',
        ) );

        $photos = array();
        foreach ( $attachments as $att ) {
            $thumb  = wp_get_attachment_image_src( $att->ID, 'thumbnail' );
            $file   = get_attached_file( $att->ID );
            $target = get_post_meta( $att->ID, '_pp_target_filename', true );

            $photos[] = array(
                'id'          => $att->ID,
                'thumb_url'   => $thumb ? $thumb[0] : '',
                'filename'    => $target ?: ( $file ? basename( $file ) : $att->post_title ),
                'recommended' => (bool) get_post_meta( $att->ID, '_pp_recommended', true ),
            );
        }

        wp_send_json_success( array( 'photos' => $photos ) );
    }
}