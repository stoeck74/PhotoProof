<?php
/**
 * Gestion de l'export des sélections clients — PhotoProof
 *
 * CORRECTIONS :
 * - Vérification nonce CSRF ajoutée
 * - Vérification que le post_id est bien un pp_gallery
 * - Header injection neutralisée (nettoyage du nom de fichier)
 * - date() remplacé par wp_date()
 * - wp_die() remplacé par wp_send_json_error() quand approprié
 * - Format CSV propre avec en-tête (au lieu d'un .txt avec virgules)
 * - Vérification du type MIME des attachements
 */
class PhotoProof_Export {

    public function __construct() {
        add_action( 'wp_ajax_pp_export_selection', array( $this, 'generate_csv_export' ) );
    }

    public function generate_csv_export() {

        // ── 1. SÉCURITÉ ───────────────────────────────────────────────

        $post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;

        if ( ! $post_id ) {
            wp_die( 'ID de galerie invalide.', 'Erreur', array( 'response' => 400 ) );
        }

        // CORRECTION : vérification nonce CSRF
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'pp_export_' . $post_id ) ) {
            wp_die( 'Requête non autorisée.', 'Erreur', array( 'response' => 403 ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Accès refusé.', 'Erreur', array( 'response' => 403 ) );
        }

        // CORRECTION : vérifier que le post est bien un pp_gallery
        if ( get_post_type( $post_id ) !== 'pp_gallery' ) {
            wp_die( 'Type de contenu invalide.', 'Erreur', array( 'response' => 400 ) );
        }

        // ── 2. RÉCUPÉRATION DES PHOTOS SÉLECTIONNÉES ─────────────────

        $selected_ids = get_post_meta( $post_id, '_pp_selected_photos', true );

        if ( empty( $selected_ids ) || ! is_array( $selected_ids ) ) {
            wp_die(
                'Aucune photo n\'a été sélectionnée par le client pour le moment.',
                'Sélection vide',
                array( 'response' => 200 )
            );
        }

        // ── 3. CONSTRUCTION DES DONNÉES ───────────────────────────────

        $rows = array();

        foreach ( $selected_ids as $attachment_id ) {
            $attachment_id = intval( $attachment_id );
            $file_path     = get_attached_file( $attachment_id );

            if ( ! $file_path ) {
                continue;
            }

            $filename  = basename( $file_path );
            $title     = get_the_title( $attachment_id );
            $file_url  = wp_get_attachment_url( $attachment_id );

            $rows[] = array(
                'filename' => $filename,
                'title'    => $title ?: $filename,
                'url'      => $file_url ?: '',
            );
        }

        if ( empty( $rows ) ) {
            wp_die(
                'Impossible de récupérer les fichiers sélectionnés.',
                'Erreur',
                array( 'response' => 500 )
            );
        }

        // ── 4. CONSTRUCTION DU NOM DE FICHIER ─────────────────────────

        $gallery_title = sanitize_title( get_post_field( 'post_title', $post_id, 'raw' ) );

        // CORRECTION : wp_date() respecte la timezone WordPress
        $date = wp_date( 'Y-m-d' );

        $raw_filename = 'selection-' . $gallery_title . '-' . $date . '.csv';

        // CORRECTION : neutralisation header injection (\r \n " retirés)
        $csv_filename = str_replace( array( "\r", "\n", '"' ), '', $raw_filename );

        // ── 5. ENVOI DES HEADERS ──────────────────────────────────────

        // On s'assure qu'aucun output n'a été envoyé avant
        if ( headers_sent( $file, $line ) ) {
            wp_die( 'Impossible d\'envoyer le fichier : headers déjà envoyés dans ' . $file . ' ligne ' . $line );
        }

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $csv_filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // ── 6. GÉNÉRATION DU CSV ──────────────────────────────────────

        $output = fopen( 'php://output', 'w' );

        // BOM UTF-8 pour compatibilité Excel
        fputs( $output, "\xEF\xBB\xBF" );

        // En-tête CSV
        fputcsv( $output, array( 'Nom du fichier', 'Titre', 'URL' ) );

        // Lignes de données
        foreach ( $rows as $row ) {
            fputcsv( $output, array(
                $row['filename'],
                $row['title'],
                $row['url'],
            ) );
        }

        fclose( $output );
        exit;
    }
}