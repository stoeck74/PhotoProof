<?php
/**
 * Plugin Name:       PhotoProof
 * Description:       Galerie d'épreuve pour photographe avec gestion de watermark et dossiers sécurisés.
 * Version:           0.1.0
 * Author:            Cédric Stoecklin
 * License:           GPL-2.0-or-later
 * Text Domain:       photoproof
 */

// Sécurité : Empêcher l'accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Initialisation du Plugin
 */
class PhotoProof {

    public function __construct() {
        $this->define_constants();
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();

        // Enregistrement du hook d'activation
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
    }

    /**
     * Définition des constantes de chemin et URL
     */
    private function define_constants() {
        define( 'PHOTOPROOF_VERSION', '0.1.0' );
        define( 'PHOTOPROOF_PATH', plugin_dir_path( __FILE__ ) );
        define( 'PHOTOPROOF_URL', plugin_dir_url( __FILE__ ) );
        // Slug partagé entre le CPT et le Router — source unique de vérité
        define( 'PHOTOPROOF_GALLERY_SLUG', 'galerie-epreuve' );
    }

    /**
     * Code exécuté à l'activation du plugin
     *
     * CORRECTIONS :
     * - Suppression de "new PhotoProof_Router()" ici (double instanciation inutile à l'activation)
     * - flush_rewrite_rules() conservé pour que le CPT soit immédiatement accessible
     */
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

        // Création du dossier racine protégé
        $upload_dir = wp_upload_dir();
        $pp_dir     = $upload_dir['basedir'] . '/photoproof';
        if ( ! file_exists( $pp_dir ) ) {
            wp_mkdir_p( $pp_dir );
            file_put_contents( $pp_dir . '/index.php', '<?php // Silence is golden' );
        }

        // On enregistre le CPT avant de flusher pour éviter les 404
        $this->register_gallery_post_type();
        flush_rewrite_rules();
    }

    /**
     * Chargement des fichiers de classes
     *
     * CORRECTIONS :
     * - Chaque classe est instanciée UNE seule fois ici
     * - PhotoProof_Router n'est plus instancié dans activate()
     */
    private function load_dependencies() {
        // --- 1. ADMIN & INTERFACE ---
        require_once PHOTOPROOF_PATH . 'admin/class-photoproof-settings.php';
        new PhotoProof_Settings();

        require_once PHOTOPROOF_PATH . 'admin/class-photoproof-metaboxes.php';
        new PhotoProof_Metaboxes();

        require_once PHOTOPROOF_PATH . 'admin/class-photoproof-assets.php';
        new PhotoProof_Assets();

        require_once PHOTOPROOF_PATH . 'admin/class-photoproof-admin-columns.php';
        new PhotoProof_Admin_Columns();

        // --- 2. LOGIQUE MÉTIER (INCLUDES) ---
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

        // --- 3. PUBLIC ---
        require_once PHOTOPROOF_PATH . 'public/class-photoproof-public.php';
        new PhotoProof_Public();
    }

    private function define_admin_hooks() {
        add_action( 'init', array( $this, 'register_gallery_post_type' ) );

        // Flush les rewrite rules quand l'option UUID change
        // (évite les 404 inexpliqués après activation/désactivation)
        add_action( 'update_option_pp_use_random_urls', 'flush_rewrite_rules' );
    }

    private function define_public_hooks() {
        add_filter( 'single_template', array( $this, 'load_gallery_template' ) );
    }

    /**
     * Charge le template personnalisé pour le CPT pp_gallery
     */
    public function load_gallery_template( $template ) {
        if ( is_singular( 'pp_gallery' ) ) {
            $custom_template = PHOTOPROOF_PATH . 'templates/single-pp_gallery.php';
            if ( file_exists( $custom_template ) ) {
                return $custom_template;
            }
        }
        return $template;
    }

    /**
     * Enregistrement du Custom Post Type
     *
     * CORRECTION :
     * - Le slug utilise la constante PHOTOPROOF_GALLERY_SLUG
     *   pour rester cohérent avec le Router (qui l'utilise aussi)
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

// Initialisation globale — une seule instance
new PhotoProof();