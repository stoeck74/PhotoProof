<?php
// Sécurité — ne s'exécute que via WP
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

// Supprimer la table
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}photoproof_galleries" );

// Supprimer toutes les options
$options = array(
    'pp_use_random_urls', 'pp_enable_expiration', 'pp_enable_rename',
    'pp_rename_pattern', 'pp_enable_recommendations', 'pp_global_recommendation_icon',
    'pp_global_watermark', 'pp_watermark_opacity', 'pp_custom_logo', 'pp_custom_title',
    'pp_color_bg', 'pp_color_active', 'pp_color_text', 'pp_photo_rounded',
    'pp_login_url', 'pp_delete_files_on_delete',
);
foreach ( $options as $option ) {
    delete_option( $option );
}

// Supprimer le cron
$timestamp = wp_next_scheduled( 'pp_daily_expiration_check' );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, 'pp_daily_expiration_check' );
}

// Supprimer les post metas orphelines
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_pp_%'" );