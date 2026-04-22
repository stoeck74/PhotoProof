<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
// Sécurité — ne s'exécute que via WP
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

// Supprimer la table
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}photoproof_galleries" );

// Supprimer toutes les options
$photoproof_options = array(
    'photoproof_use_random_urls', 'photoproof_enable_expiration', 'photoproof_enable_rename',
    'photoproof_rename_pattern', 'photoproof_enable_recommendations', 'photoproof_global_recommendation_icon',
    'photoproof_global_watermark', 'photoproof_watermark_opacity', 'photoproof_custom_logo', 'photoproof_custom_title',
    'photoproof_color_bg', 'photoproof_color_active', 'photoproof_color_text', 'photoproof_photo_rounded',
    'photoproof_login_url', 'photoproof_delete_files_on_delete',
    'photoproof_email_photographer_subject', 'photoproof_email_photographer_body',
    'photoproof_email_client_subject', 'photoproof_email_client_body',
);
foreach ( $photoproof_options as $photoproof_option ) {
    delete_option( $photoproof_option );
}

// Supprimer le cron
$photoproof_timestamp = wp_next_scheduled( 'photoproof_daily_expiration_check' );
if ( $photoproof_timestamp ) {
    wp_unschedule_event( $photoproof_timestamp, 'photoproof_daily_expiration_check' );
}

// Supprimer les post metas orphelines
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_photoproof\\_%'" );
