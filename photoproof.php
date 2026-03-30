<?php
/**
 * Plugin Name:       PhotoProof
 * Description:       Galerie d'épreuve pour photographe avec gestion de watermark et dossiers sécurisés.
 * Version:           0.1.0
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

        // Flush automatique si le slug PhotoProof n'est pas dans les rewrite rules
        // Se déclenche silencieusement — transparent pour l'utilisateur final
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
    }

    private function define_public_hooks() {
        // Template pour URLs slug classiques : /galerie-epreuve/mon-titre/
        add_filter( 'single_template', array( $this, 'load_gallery_template' ) );

        // Template pour URLs UUID : /galerie-epreuve/550e8400-xxxx/
        // Déclenché après que pre_get_posts a résolu l'UUID → is_singular() est vrai
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
     * Template pour URL UUID — couvre le cas où is_singular() n'est pas encore vrai
     * au moment du filtre single_template mais où la query var pp_uuid est présente
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
            'menu_icon'          => 'dashicons-format-gallery',
            'supports'           => array( 'title', 'editor', 'thumbnail' ),
            'show_in_rest'       => true,
        );

        register_post_type( 'pp_gallery', $args );
    }
}

new PhotoProof();