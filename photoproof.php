<?php
/**
 * Plugin Name:       PhotoProof
 * Description:       Galerie d'épreuve pour photographe avec gestion de watermark et dossiers sécurisés.
 * Version:           0.2.0
 * Author:            Cédric Stoecklin
 * License:           GPL-2.0-or-later
 * Text Domain:       photoproof
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PhotoProof {

    public function __construct() {
        $this->define_constants();
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();

        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }

    private function define_constants() {
        define( 'PHOTOPROOF_VERSION', '0.1.0' );
        define( 'PHOTOPROOF_PATH', plugin_dir_path( __FILE__ ) );
        define( 'PHOTOPROOF_URL', plugin_dir_url( __FILE__ ) );
        define( 'PHOTOPROOF_GALLERY_SLUG', 'galerie-epreuve' );
    }

    public function activate() {
        global $wpdb;
        $table_name      = $wpdb->prefix . 'photoproof_galleries';
        $charset_collate = $wpdb->get_charset_collate();

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

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Dossier racine protégé
        $upload_dir = wp_upload_dir();
        $pp_dir     = $upload_dir['basedir'] . '/photoproof';
        if ( ! file_exists( $pp_dir ) ) {
            wp_mkdir_p( $pp_dir );
            file_put_contents( $pp_dir . '/index.php', '<?php // Silence is golden' );
        }

        // Migration : passer toutes les galeries WP publiées à 'publie' si brouillon ou absentes
        $published_ids = get_posts( array(
            'post_type'      => 'pp_gallery',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        foreach ( $published_ids as $pid ) {
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, status FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
                $pid
            ) );

            if ( ! $existing ) {
                $wpdb->insert(
                    $wpdb->prefix . 'photoproof_galleries',
                    array(
                        'post_id'     => $pid,
                        'status'      => 'publie',
                        'folder_path' => 'photoproof/gallery-' . $pid,
                    ),
                    array( '%d', '%s', '%s' )
                );
            } elseif ( $existing->status === 'brouillon' ) {
                $wpdb->update(
                    $wpdb->prefix . 'photoproof_galleries',
                    array( 'status' => 'publie' ),
                    array( 'post_id' => $pid ),
                    array( '%s' ),
                    array( '%d' )
                );
            }
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
        // ── ADMIN ─────────────────────────────────────────────────────
        require_once PHOTOPROOF_PATH . 'admin/class-photoproof-settings.php';
        new PhotoProof_Settings();

        require_once PHOTOPROOF_PATH . 'admin/class-photoproof-metaboxes.php';
        new PhotoProof_Metaboxes();

        require_once PHOTOPROOF_PATH . 'admin/class-photoproof-assets.php';
        new PhotoProof_Assets();

        require_once PHOTOPROOF_PATH . 'admin/class-photoproof-admin-columns.php';
        new PhotoProof_Admin_Columns();

        // ── LOGIQUE MÉTIER ────────────────────────────────────────────
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

        // ── PUBLIC ────────────────────────────────────────────────────
        require_once PHOTOPROOF_PATH . 'public/class-photoproof-public.php';
        new PhotoProof_Public();

        require_once PHOTOPROOF_PATH . 'includes/class-photoproof-helpers.php';
        new PhotoProof_Helpers();
    }

    private function define_admin_hooks() {
        add_action( 'init', array( $this, 'register_gallery_post_type' ) );

        // Flush quand l'option UUID change
        add_action( 'update_option_pp_use_random_urls', 'flush_rewrite_rules' );

        // Exclure les photos PhotoProof de la médiathèque standard
        add_action( 'pre_get_posts', function( $query ) {
            if ( ! is_admin() ) return;
            if ( $query->get( 'post_type' ) !== 'attachment' ) return;
            if ( ! $query->is_main_query() ) return;
            $query->set( 'meta_query', array(
                array(
                    'key'     => '_pp_gallery_photo',
                    'compare' => 'NOT EXISTS',
                ),
            ) );
        } );


// Exclure les photos PhotoProof de la médiathèque — toutes les méthodes
add_action( 'wp_ajax_query-attachments', function() {
    add_filter( 'ajax_query_attachments_args', function( $args ) {
        error_log( 'PP media filter applied' );
        $args['meta_query'] = array(
            array(
                'key'     => '_pp_gallery_photo',
                'compare' => 'NOT EXISTS',
            ),
        );
        return $args;
    });
}, 0 );

        // Flush automatique si le slug PhotoProof n'est pas dans les rewrite rules
        add_action( 'wp', function () {
            $rules = get_option( 'rewrite_rules' );
            $slug  = PHOTOPROOF_GALLERY_SLUG;
            $found = false;

            if ( is_array( $rules ) ) {
                foreach ( array_keys( $rules ) as $rule ) {
                    if ( strpos( $rule, $slug ) !== false ) {
                        $found = true;
                        break;
                    }
                }
            }

            if ( ! $found ) {
                flush_rewrite_rules();
            }
        } );

        // Nettoyage à la suppression définitive d'une galerie
        add_action( 'before_delete_post', function( $post_id ) {
            if ( get_post_type( $post_id ) !== 'pp_gallery' ) return;

            // Toujours nettoyer la ligne en base
            global $wpdb;
            $wpdb->delete(
                $wpdb->prefix . 'photoproof_galleries',
                array( 'post_id' => $post_id ),
                array( '%d' )
            );

            // Nettoyer les fichiers uniquement si option activée
            if ( ! get_option( 'pp_delete_files_on_delete' ) ) return;

            $attachments = get_posts( array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'post_parent'    => $post_id,
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ) );

            foreach ( $attachments as $att_id ) {
                wp_delete_attachment( $att_id, true );
            }

            $upload_dir  = wp_upload_dir();
            $gallery_dir = $upload_dir['basedir'] . '/photoproof/gallery-' . $post_id;
            if ( file_exists( $gallery_dir ) ) {
                $this->delete_directory( $gallery_dir );
            }
        } );
    }

    private function define_public_hooks() {
        // Template pour URLs slug classiques : /galerie-epreuve/mon-titre/
        add_filter( 'single_template', array( $this, 'load_gallery_template' ) );

        // Template pour URLs UUID : /galerie-epreuve/550e8400-xxxx/
        add_filter( 'template_include', array( $this, 'load_gallery_template_uuid' ) );
    }

    /**
     * Template pour URL slug classique
     */
    public function load_gallery_template( $template ) {
        if ( is_singular( 'pp_gallery' ) ) {
            $custom = PHOTOPROOF_PATH . 'templates/single-pp_gallery.php';
            if ( file_exists( $custom ) ) {
                return $custom;
            }
        }
        return $template;
    }

    /**
     * Template pour URL UUID
     */
    public function load_gallery_template_uuid( $template ) {
        global $wp_query;

        if (
            $wp_query->get( 'pp_uuid' ) ||
            ( $wp_query->is_main_query() && $wp_query->get( 'post_type' ) === 'pp_gallery' && is_singular() )
        ) {
            $custom = PHOTOPROOF_PATH . 'templates/single-pp_gallery.php';
            if ( file_exists( $custom ) ) {
                return $custom;
            }
        }

        return $template;
    }

    /**
     * Enregistrement du Custom Post Type pp_gallery
     */
    public function register_gallery_post_type() {
        $labels = array(
            'name'               => 'Galeries PhotoProof',
            'singular_name'      => 'Galerie',
            'menu_name'          => 'PhotoProof',
            'add_new'            => 'Ajouter une Galerie',
            'add_new_item'       => 'Ajouter une nouvelle Galerie',
            'edit_item'          => 'Modifier la Galerie',
            'new_item'           => 'Nouvelle Galerie',
            'view_item'          => 'Voir la Galerie',
            'search_items'       => 'Rechercher une Galerie',
            'not_found'          => 'Aucune galerie trouvée',
            'not_found_in_trash' => 'Aucune galerie trouvée dans la corbeille',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => PHOTOPROOF_GALLERY_SLUG ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'menu_icon'          => plugin_dir_url(__FILE__) . 'admin/img/photoproof-logo.svg',
            'supports'           => array( 'title', 'editor', 'thumbnail' ),
            'show_in_rest'       => true,
        );

        register_post_type( 'pp_gallery', $args );
    }

    /**
     * Supprime récursivement un dossier et son contenu
     */
    private function delete_directory( $dir ) {
        if ( ! is_dir( $dir ) ) return;
        $files = array_diff( scandir( $dir ), array( '.', '..' ) );
        foreach ( $files as $file ) {
            $path = $dir . '/' . $file;
            is_dir( $path ) ? $this->delete_directory( $path ) : unlink( $path );
        }
        rmdir( $dir );
    }
}

new PhotoProof();