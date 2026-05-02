<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * Gestion de l'export des sélections clients — PhotoProof
 */
class PhotoProof_Export {

    public function __construct() {
        add_action( 'wp_ajax_photoproof_export_selection', array( $this, 'generate_csv_export' ) );
    }

    public function generate_csv_export() {

        // ── 1. SÉCURITÉ ───────────────────────────────────────────────

        $post_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0;

        if ( ! $post_id ) {
            wp_die( esc_html__( 'Invalid gallery ID.', 'photoproof' ), esc_html__( 'Error', 'photoproof' ), array( 'response' => 400 ) );
        }

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'photoproof_export_' . $post_id ) ) {
            wp_die( esc_html__( 'Unauthorized request.', 'photoproof' ), esc_html__( 'Error', 'photoproof' ), array( 'response' => 403 ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'photoproof' ), esc_html__( 'Error', 'photoproof' ), array( 'response' => 403 ) );
        }

        if ( get_post_type( $post_id ) !== 'photoproof_gallery' ) {
            wp_die( esc_html__( 'Invalid content type.', 'photoproof' ), esc_html__( 'Error', 'photoproof' ), array( 'response' => 400 ) );
        }

        // ── 2. RÉCUPÉRATION DES PHOTOS SÉLECTIONNÉES ─────────────────

        $selected_ids = get_post_meta( $post_id, '_photoproof_selected_photos', true );

        if ( empty( $selected_ids ) || ! is_array( $selected_ids ) ) {
            wp_die(
                esc_html__( 'No photos have been selected by the client yet.', 'photoproof' ),
                esc_html__( 'Empty selection', 'photoproof' ),
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

            $filename = basename( $file_path );
            $title    = get_the_title( $attachment_id );
            $file_url = wp_get_attachment_url( $attachment_id );

            $rows[] = array(
                'filename' => $filename,
                'title'    => $title ?: $filename,
                'url'      => $file_url ?: '',
            );
        }

        if ( empty( $rows ) ) {
            wp_die(
                esc_html__( 'Unable to retrieve the selected files.', 'photoproof' ),
                esc_html__( 'Error', 'photoproof' ),
                array( 'response' => 500 )
            );
        }

        // ── 4. CONSTRUCTION DU NOM DE FICHIER ─────────────────────────

        $gallery_title = sanitize_title( get_post_field( 'post_title', $post_id, 'raw' ) );
        $date          = wp_date( 'Y-m-d' );
        $raw_filename  = 'selection-' . $gallery_title . '-' . $date . '.csv';
        $csv_filename  = str_replace( array( "\r", "\n", '"' ), '', $raw_filename );

        // ── 5. ENVOI DES HEADERS ──────────────────────────────────────

        if ( headers_sent( $file, $line ) ) {
            wp_die(
                sprintf(
                    /* translators: 1: File path, 2: Line number */
                    esc_html__( 'Cannot send file: headers already sent in %1$s line %2$s', 'photoproof' ),
                    esc_html( $file ),
                    absint( $line )
                )
            );
        }

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $csv_filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // ── 6. GÉNÉRATION DU CSV ──────────────────────────────────────

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $output = fopen( 'php://output', 'w' );

        // BOM UTF-8 pour compatibilité Excel
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputs
        fputs( $output, "\xEF\xBB\xBF" );

        // En-tête CSV
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
        fputcsv( $output, array( 'Nom du fichier', 'Titre', 'URL' ) );

        // Lignes de données
        foreach ( $rows as $row ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
            fputcsv( $output, array(
                $row['filename'],
                $row['title'],
                $row['url'],
            ) );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose( $output );
        exit;
    }
}