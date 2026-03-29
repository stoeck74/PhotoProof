<?php
/**
 * Logique de gestion des fichiers et dossiers — PhotoProof
 *
 * CORRECTIONS :
 * - Actions AJAX pp_attach_photos et pp_detach_photo implémentées (étaient absentes)
 * - Vérification nonce sur intercept_upload
 * - Filtre upload_dir retiré après usage (évite la contamination des uploads suivants)
 * - index.php de protection créé dans chaque sous-dossier galerie
 * - sanitize sur post_id avant get_post_type
 * - Méthode handle_attach_photos complète avec post_meta
 * - Méthode handle_detach_photo pour la suppression
 */
class PhotoProof_Uploader {

    public function __construct() {
        // Redirection du dossier d'upload
        add_filter( 'wp_handle_upload_prefilter', array( $this, 'intercept_upload' ), 9 );

        // CORRECTION : actions AJAX déclarées
        add_action( 'wp_ajax_pp_attach_photos', array( $this, 'handle_attach_photos' ) );
        add_action( 'wp_ajax_pp_detach_photo',  array( $this, 'handle_detach_photo' ) );
    }

    /**
     * Intercepte l'upload pour rediriger vers le bon dossier
     * Priorité 9 : s'exécute AVANT le renommage (priorité 10 dans PhotoProof_Renamer)
     */
    public function intercept_upload( $file ) {
        // CORRECTION : vérification nonce avant toute action
        if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'pp_upload_nonce' ) ) {
            return $file;
        }

        // CORRECTION : intval() avant get_post_type()
        $post_id = isset( $_REQUEST['post_id'] ) ? intval( $_REQUEST['post_id'] ) : 0;

        if ( ! $post_id || get_post_type( $post_id ) !== 'pp_gallery' ) {
            return $file;
        }

        add_filter( 'upload_dir', array( $this, 'set_custom_directory' ) );

        return $file;
    }

    /**
     * Redirige le dossier d'upload vers photoproof/gallery-{id}
     *
     * CORRECTION : filtre retiré immédiatement après usage pour ne pas
     * contaminer les uploads suivants dans la même requête
     */
    public function set_custom_directory( $path ) {
        // On retire le filtre dès la première exécution
        remove_filter( 'upload_dir', array( $this, 'set_custom_directory' ) );

        $post_id = isset( $_REQUEST['post_id'] ) ? intval( $_REQUEST['post_id'] ) : 0;
        $subdir  = '/photoproof/gallery-' . $post_id;

        $path['path']   = $path['basedir'] . $subdir;
        $path['url']    = $path['baseurl'] . $subdir;
        $path['subdir'] = $subdir;

        // Créer le dossier s'il n'existe pas
        if ( ! file_exists( $path['path'] ) ) {
            wp_mkdir_p( $path['path'] );
            // CORRECTION : fichier index.php de protection dans chaque sous-dossier
            file_put_contents( $path['path'] . '/index.php', '<?php // Silence is golden' );
        }

        return $path;
    }

    /**
     * AJAX : associe une liste d'attachements à une galerie
     *
     * Appelé par admin-gallery.js après sélection dans la médiathèque
     * Action : pp_attach_photos
     */
    public function handle_attach_photos() {
        // Sécurité
        check_ajax_referer( 'pp_upload_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Droits insuffisants.' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

        if ( ! $post_id || get_post_type( $post_id ) !== 'pp_gallery' ) {
            wp_send_json_error( array( 'message' => 'Galerie invalide.' ) );
        }

        $raw_ids = isset( $_POST['attachment_ids'] ) ? (array) $_POST['attachment_ids'] : array();
        $ids     = array_map( 'intval', $raw_ids );
        $ids     = array_filter( $ids ); // retire les 0

        if ( empty( $ids ) ) {
            wp_send_json_error( array( 'message' => 'Aucun identifiant valide.' ) );
        }

        // Rattacher chaque attachement au post parent
        foreach ( $ids as $att_id ) {
            // Vérifie que c'est bien un attachement image
            if ( ! wp_attachment_is_image( $att_id ) ) {
                continue;
            }
            wp_update_post( array(
                'ID'          => $att_id,
                'post_parent' => $post_id,
            ) );
        }

        // Fusionner avec la liste existante et dédoublonner
        $existing = get_post_meta( $post_id, '_pp_gallery_photos', true );
        $existing = is_array( $existing ) ? $existing : array();
        $merged   = array_values( array_unique( array_merge( $existing, $ids ) ) );

        update_post_meta( $post_id, '_pp_gallery_photos', $merged );

        wp_send_json_success( array(
            'count'   => count( $merged ),
            'message' => count( $ids ) . ' photo(s) ajoutée(s).',
        ) );
    }

    /**
     * AJAX : retire un attachement d'une galerie
     *
     * Appelé par admin-gallery.js au clic sur le bouton suppression d'un thumb
     * Action : pp_detach_photo
     */
    public function handle_detach_photo() {
        // Sécurité
        check_ajax_referer( 'pp_upload_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Droits insuffisants.' ) );
        }

        $post_id       = isset( $_POST['post_id'] )       ? intval( $_POST['post_id'] )       : 0;
        $attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;

        if ( ! $post_id || ! $attachment_id ) {
            wp_send_json_error( array( 'message' => 'Paramètres invalides.' ) );
        }

        if ( get_post_type( $post_id ) !== 'pp_gallery' ) {
            wp_send_json_error( array( 'message' => 'Galerie invalide.' ) );
        }

        // Détacher du post parent
        wp_update_post( array(
            'ID'          => $attachment_id,
            'post_parent' => 0,
        ) );

        // Retirer de la meta liste
        $existing = get_post_meta( $post_id, '_pp_gallery_photos', true );
        $existing = is_array( $existing ) ? $existing : array();
        $updated  = array_values( array_diff( $existing, array( $attachment_id ) ) );

        update_post_meta( $post_id, '_pp_gallery_photos', $updated );

        // Retirer aussi de la sélection client si présent
        $selection = get_post_meta( $post_id, '_pp_selected_photos', true );
        if ( is_array( $selection ) && in_array( $attachment_id, $selection, true ) ) {
            $selection = array_values( array_diff( $selection, array( $attachment_id ) ) );
            update_post_meta( $post_id, '_pp_selected_photos', $selection );
        }

        wp_send_json_success( array(
            'count'   => count( $updated ),
            'message' => 'Photo retirée de la galerie.',
        ) );
    }
}