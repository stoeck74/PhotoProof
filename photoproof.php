<?php
/**
 * Plugin Name:       PhotoProof
 * Description:       Galerie d'épreuve pour photographe avec gestion de watermark et dossiers sécurisés.
 * Version:           1.0.0
 * Author:            Cédric Stoecklin
 * License:           GPL-2.0-or-later
 * Text Domain:       photoproof
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class PhotoProof {

    public function __construct() {
        $this->define_constants();
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function define_constants() {
        define( 'PHOTOPROOF_VERSION', '0.2.0' );
        define( 'PHOTOPROOF_PATH', plugin_dir_path( __FILE__ ) );
        define( 'PHOTOPROOF_URL', plugin_dir_url( __FILE__ ) );
        define( 'PHOTOPROOF_GALLERY_SLUG', 'galerie-epreuve' );
    }

    public function activate() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'photoproof_galleries';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            client_id bigint(20) DEFAULT NULL,
            folder_path varchar(255) NOT NULL,
            status varchar(50) DEFAULT 'brouillon' NOT NULL,
            watermark_settings text DEFAULT NULL,
            selection_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY post_id (post_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Dossier racine protégé
        $upload_dir = wp_upload_dir();
        $pp_dir     = $upload_dir['basedir'] . '/photoproof';
        if ( ! file_exists( $pp_dir ) ) {
            wp_mkdir_p( $pp_dir );
            file_put_contents( $pp_dir . '/index.php', '<?php // Silence is golden' );
        }

        // Planifier le cron d'expiration
        if ( ! wp_next_scheduled( 'pp_daily_expiration_check' ) ) {
            wp_schedule_event( time(), 'daily', 'pp_daily_expiration_check' );
        }

        $this->register_gallery_post_type();
        flush_rewrite_rules();
    }

    public function deactivate() {
        $timestamp = wp_next_scheduled( 'pp_daily_expiration_check' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'pp_daily_expiration_check' );
        }
    }

    private function load_dependencies() {
        require_once PHOTOPROOF_PATH . 'admin/class-photoproof-settings.php';
        new PhotoProof_Settings();
        require_once PHOTOPROOF_PATH . 'admin/class-photoproof-metaboxes.php';
        new PhotoProof_Metaboxes();
        require_once PHOTOPROOF_PATH . 'admin/class-photoproof-assets.php';
        new PhotoProof_Assets();
        require_once PHOTOPROOF_PATH . 'admin/class-photoproof-admin-columns.php';
        new PhotoProof_Admin_Columns();
        require_once PHOTOPROOF_PATH . 'includes/class-photoproof-uploader.php';
        new PhotoProof_Uploader();
        require_once PHOTOPROOF_PATH . 'includes/class-photoproof-export.php';
        new PhotoProof_Export();
        require_once PHOTOPROOF_PATH . 'includes/class-photoproof-renamer.php';
        new PhotoProof_Renamer();
        require_once PHOTOPROOF_PATH . 'includes/class-photoproof-router.php';
        new PhotoProof_Router();
        require_once PHOTOPROOF_PATH . 'includes/class-photoproof-expiration.php';
        new PhotoProof_Expiration();
        require_once PHOTOPROOF_PATH . 'includes/class-photoproof-watermark.php';
        new PhotoProof_Watermark();
        require_once PHOTOPROOF_PATH . 'includes/class-photoproof-mailer.php';
        new PhotoProof_Mailer();
        require_once PHOTOPROOF_PATH . 'public/class-photoproof-public.php';
        new PhotoProof_Public();
        require_once PHOTOPROOF_PATH . 'includes/class-photoproof-helpers.php';
        new PhotoProof_Helpers();
    }

    private function define_admin_hooks() {
        add_action( 'init', array( $this, 'register_gallery_post_type' ) );

        // Filtre médiathèque standard
        add_action( 'pre_get_posts', function( $query ) {
            if ( ! is_admin() ) return;
            if ( $query->get( 'post_type' ) !== 'attachment' ) return;
            if ( ! $query->is_main_query() ) return;
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            $query->set( 'meta_query', array(
                array(
                    'key'     => '_pp_gallery_photo',
                    'compare' => 'NOT EXISTS',
                ),
            ) );
        } );

        // Filtre médiathèque AJAX (Pop-up)
        add_action( 'wp_ajax_query-attachments', function() {
            add_filter( 'ajax_query_attachments_args', function( $args ) {
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                $args['meta_query'] = array(
                    array(
                        'key'     => '_pp_gallery_photo',
                        'compare' => 'NOT EXISTS',
                    ),
                );
                return $args;
            });
        }, 0 );

        // Nettoyage suppression
        add_action( 'before_delete_post', function( $post_id ) {
            if ( get_post_type( $post_id ) !== 'pp_gallery' ) return;
            global $wpdb;
            $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prefix . 'photoproof_galleries',
                array( 'post_id' => $post_id ),
                array( '%d' )
            );
            if ( ! get_option( 'pp_delete_files_on_delete' ) ) return;
            $upload_dir  = wp_upload_dir();
            $gallery_dir = $upload_dir['basedir'] . '/photoproof/gallery-' . $post_id;
            if ( file_exists( $gallery_dir ) ) {
                $this->delete_directory( $gallery_dir );
            }
        } );
    }

    private function define_public_hooks() {
        add_filter( 'single_template', array( $this, 'load_gallery_template' ) );
    }

    public function load_gallery_template( $template ) {
        if ( is_singular( 'pp_gallery' ) ) {
            $custom = PHOTOPROOF_PATH . 'templates/single-pp_gallery.php';
            if ( file_exists( $custom ) ) return $custom;
        }
        return $template;
    }

public function register_gallery_post_type() {
        $labels = array(
            'name'               => __( 'PhotoProof Galleries', 'photoproof' ),
            'singular_name'      => __( 'Gallery', 'photoproof' ),
            'menu_name'          => __( 'PhotoProof', 'photoproof' ),
            'add_new'            => __( 'Add New', 'photoproof' ),
            'add_new_item'       => __( 'Add New Gallery', 'photoproof' ),
            'edit_item'          => __( 'Edit Gallery', 'photoproof' ),
            'new_item'           => __( 'New Gallery', 'photoproof' ),
            'view_item'          => __( 'View Gallery', 'photoproof' ),
            'search_items'       => __( 'Search Galleries', 'photoproof' ),
            'not_found'          => __( 'No galleries found', 'photoproof' ),
            'not_found_in_trash' => __( 'No galleries found in Trash', 'photoproof' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'rewrite'            => array( 'slug' => PHOTOPROOF_GALLERY_SLUG ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'menu_position'      => 20,
            'menu_icon'          => PHOTOPROOF_URL . 'admin/img/photoproof-logo.svg',
            'supports'           => array( 'title', 'editor', 'thumbnail' ),
            'show_in_rest'       => true,
        );
        register_post_type( 'pp_gallery', $args );
    }

    private function delete_directory( $dir ) {
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        if ( ! $wp_filesystem->is_dir( $dir ) ) return false;
        return $wp_filesystem->delete( $dir, true );
    }
}

$photoproof_instance = new PhotoProof();
register_activation_hook( __FILE__, array( $photoproof_instance, 'activate' ) );
register_deactivation_hook( __FILE__, array( $photoproof_instance, 'deactivate' ) );