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
        
        // Enregistrement du hook d'activation (doit être fait ici)
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
    }

    /**
     * Définition des constantes de chemin et URL
     */
    private function define_constants() {
        define( 'PHOTOPROOF_VERSION', '0.1.0' );
        define( 'PHOTOPROOF_PATH', plugin_dir_path( __FILE__ ) );
        define( 'PHOTOPROOF_URL', plugin_dir_url( __FILE__ ) );
    }

    /**
     * Code exécuté à l'activation du plugin
     */
    public function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'photoproof_galleries';
        $charset_collate = $wpdb->get_charset_collate();

        // SQL pour notre table personnalisée
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

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        // Création du dossier d'upload spécifique dans wp-content/uploads/photoproof
        $upload_dir = wp_upload_dir();
        $pp_dir = $upload_dir['basedir'] . '/photoproof';
        if ( ! file_exists( $pp_dir ) ) {
            wp_mkdir_p( $pp_dir );
            file_put_contents( $pp_dir . '/index.php', '<?php // Silence is golden' );
        }

        flush_rewrite_rules();
    }

    /**
     * Chargement des fichiers de classes (Séparation des fonctionnalités)
     */
    private function load_dependencies() {
        // 1. Gestion de l'interface (Metaboxes)
        require_once PHOTOPROOF_PATH . 'admin/class-photoproof-metaboxes.php';
        new PhotoProof_Metaboxes();
        // Ajouter l'export
        require_once PHOTOPROOF_PATH . 'includes/class-photoproof-export.php';
        new PhotoProof_Export();

        // 2. Gestion de la logique d'Upload
        require_once PHOTOPROOF_PATH . 'admin/class-photoproof-uploader.php';
        new PhotoProof_Uploader();

        // 3. Gestion des scripts et styles
        require_once PHOTOPROOF_PATH . 'admin/class-photoproof-assets.php';
        new PhotoProof_Assets();
        require_once PHOTOPROOF_PATH . 'admin/class-photoproof-settings.php';
        new PhotoProof_Settings();
    }

    /**
     * Hooks pour l'administration
     */
    private function define_admin_hooks() {
        add_action('init', array($this, 'register_gallery_post_type'));
    }

    /**
     * Hooks pour la partie publique (Front-end)
     */
    private function define_public_hooks() {
        // Sera utilisé plus tard pour afficher la galerie au client
    }

    /**
     * Déclaration du Custom Post Type "pp_gallery"
     */
    public function register_gallery_post_type() {
        $labels = array(
            'name'                  => 'Galeries PhotoProof', 
            'singular_name'         => 'Galerie',
            'menu_name'             => 'PhotoProof',
            'add_new'               => 'Ajouter une Galerie',
            'add_new_item'          => 'Ajouter une nouvelle Galerie',
            'edit_item'             => 'Modifier la Galerie',
            'new_item'              => 'Nouvelle Galerie',
            'view_item'             => 'Voir la Galerie',
            'search_items'          => 'Rechercher une Galerie',
            'not_found'             => 'Aucune galerie trouvée',
            'not_found_in_trash'    => 'Aucune galerie trouvée dans la corbeille'
        );
        
        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'galerie-epreuve' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-format-gallery',
            'supports'           => array( 'title', 'editor', 'thumbnail' ),
            'show_in_rest'       => true, // Important pour l'éditeur de blocs
        );
        
        register_post_type( 'pp_gallery', $args );
    }
}

// Initialisation globale
new PhotoProof();