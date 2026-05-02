<?php
/**
 * PhotoProof — Désinstallation complète
 *
 * WordPress demande à l'utilisateur "Are you sure you want to delete this plugin
 * and all of its data?". Cette promesse doit être tenue : à la désinstallation,
 * on supprime tout ce que le plugin a créé — galeries, photos, options, fichiers,
 * tables.
 *
 * Si l'utilisateur veut conserver ses données, il doit simplement désactiver
 * le plugin (sans le supprimer).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Sécurité : ne s'exécute que via WP_UNINSTALL_PLUGIN
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// ─────────────────────────────────────────────────────────────────────────
// 1. Suppression de toutes les galeries (CPT) + leurs attachments + fichiers
// ─────────────────────────────────────────────────────────────────────────

$photoproof_galleries = get_posts( array(
    'post_type'   => 'photoproof_gallery',
    'post_status' => 'any',
    'numberposts' => -1,
    'fields'      => 'ids',
) );

foreach ( $photoproof_galleries as $photoproof_gallery_id ) {

    // Récupérer tous les attachments rattachés à cette galerie
    $photoproof_attachments = get_posts( array(
        'post_type'   => 'attachment',
        'post_status' => 'any',
        'numberposts' => -1,
        'post_parent' => $photoproof_gallery_id,
        'fields'      => 'ids',
    ) );

    foreach ( $photoproof_attachments as $photoproof_attachment_id ) {
        // Supprimer la version watermarkée si elle existe
        $photoproof_wm_url = get_post_meta( $photoproof_attachment_id, '_photoproof_watermarked_url', true );
        if ( $photoproof_wm_url ) {
            $photoproof_uploads = wp_upload_dir();
            $photoproof_wm_path = str_replace(
                $photoproof_uploads['baseurl'],
                $photoproof_uploads['basedir'],
                $photoproof_wm_url
            );
            if ( file_exists( $photoproof_wm_path ) ) {
                wp_delete_file( $photoproof_wm_path );
            }
        }

        // wp_delete_attachment supprime le fichier original + thumbnails + meta + entrée DB
        wp_delete_attachment( $photoproof_attachment_id, true );
    }

    // Supprimer le CPT lui-même (et ses post_meta)
    wp_delete_post( $photoproof_gallery_id, true );
}

// ─────────────────────────────────────────────────────────────────────────
// 2. Suppression du dossier /uploads/photoproof/ et tout son contenu
// ─────────────────────────────────────────────────────────────────────────

$photoproof_uploads = wp_upload_dir();
$photoproof_root    = trailingslashit( $photoproof_uploads['basedir'] ) . 'photoproof';

if ( is_dir( $photoproof_root ) ) {
    // Initialiser WP_Filesystem (API d'abstraction filesystem de WordPress)
    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php'; // phpcs:ignore PEAR.Files.IncludingFile.UseRequire -- needed for WP_Filesystem()
    }
    WP_Filesystem();
    global $wp_filesystem;

    if ( $wp_filesystem ) {
        // Suppression récursive du dossier et de tout son contenu
        $wp_filesystem->rmdir( $photoproof_root, true );
    }
}

// ─────────────────────────────────────────────────────────────────────────
// 3. Suppression de la table custom
// ─────────────────────────────────────────────────────────────────────────

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}photoproof_galleries" );

// ─────────────────────────────────────────────────────────────────────────
// 4. Suppression de toutes les options
// ─────────────────────────────────────────────────────────────────────────

$photoproof_options = array(
    'photoproof_version',
    'photoproof_use_random_urls',
    'photoproof_enable_expiration',
    'photoproof_enable_rename',
    'photoproof_rename_pattern',
    'photoproof_enable_recommendations',
    'photoproof_global_recommendation_icon',
    'photoproof_global_watermark',
    'photoproof_watermark_opacity',
    'photoproof_custom_logo',
    'photoproof_custom_title',
    'photoproof_color_bg',
    'photoproof_color_active',
    'photoproof_color_text',
    'photoproof_photo_rounded',
    'photoproof_login_url',
    'photoproof_delete_files_on_delete',
    'photoproof_email_photographer_subject',
    'photoproof_email_photographer_body',
    'photoproof_email_client_subject',
    'photoproof_email_client_body',
);

foreach ( $photoproof_options as $photoproof_option ) {
    delete_option( $photoproof_option );
}

// ─────────────────────────────────────────────────────────────────────────
// 5. Suppression du cron
// ─────────────────────────────────────────────────────────────────────────

$photoproof_timestamp = wp_next_scheduled( 'photoproof_daily_expiration_check' );
if ( $photoproof_timestamp ) {
    wp_unschedule_event( $photoproof_timestamp, 'photoproof_daily_expiration_check' );
}

// ─────────────────────────────────────────────────────────────────────────
// 6. Suppression des post_meta orphelines (au cas où certaines auraient
//    survécu à wp_delete_post — par exemple liées à des attachments d'autres
//    types qui auraient été marqués _photoproof_ par le plugin)
// ─────────────────────────────────────────────────────────────────────────

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_photoproof\\_%'" );
