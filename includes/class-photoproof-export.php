<?php
/**
 * Gestion de l'export des sélections clients
 */
class PhotoProof_Export {

    public function __construct() {
        // On écoute l'action AJAX pour les utilisateurs connectés (admin)
        add_action( 'wp_ajax_pp_export_selection', array( $this, 'generate_csv_export' ) );
    }

    public function generate_csv_export() {
        // 1. Vérification des droits
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Accès refusé' );
        }

        $post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_die( 'ID de galerie invalide' );
        }

        // 2. Récupération des photos sélectionnées (stockées en post_meta)
        // On s'attend à un tableau d'IDs d'attachments WordPress
        $selected_ids = get_post_meta( $post_id, '_pp_selected_photos', true );

        if ( empty( $selected_ids ) || ! is_array( $selected_ids ) ) {
            // Pour le test, si c'est vide, on peut mettre un message
            wp_die( "Aucune photo n'a été sélectionnée par le client pour le moment." );
        }

        // 3. Préparation des données (Noms de fichiers)
        $filenames = array();
        foreach ( $selected_ids as $attachment_id ) {
            $file_path = get_attached_file( $attachment_id );
            if ( $file_path ) {
                $filenames[] = basename( $file_path );
            }
        }

        // 4. Configuration des headers pour le téléchargement
        $gallery_title = sanitize_title( get_the_title( $post_id ) );
        $filename = 'selection-' . $gallery_title . '-' . date('Y-m-d') . '.txt';

        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // 5. Génération du contenu
        // Format : une liste séparée par des virgules (le plus pratique pour Lightroom)
        echo implode( ', ', $filenames );

        exit;
    }
}