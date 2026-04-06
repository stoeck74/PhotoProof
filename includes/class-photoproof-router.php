<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * Gestion du routage et des URLs UUID — PhotoProof
 *
 * CORRECTIONS :
 * - Suppression du "new PhotoProof_Router()" en bas de fichier (triple instanciation)
 * - Slug aligné sur la constante PHOTOPROOF_GALLERY_SLUG (définie dans photoproof.php)
 * - Rewrite rule toujours enregistrée, option vérifiée dans find_gallery_by_uuid()
 * - str_replace remplacé par preg_replace (plus précis, évite les faux remplacements)
 * - UUID sanitisé dans find_gallery_by_uuid()
 * - flush_rewrite_rules sur changement d'option géré dans photoproof.php (define_admin_hooks)
 * - is_admin() guard sur use_uuid_in_permalink (évite corruption URL dans l'admin WP)
 */
class PhotoProof_Router {

    public function __construct() {
        // 1. Génération de l'UUID à la création d'une galerie
        add_action( 'save_post_pp_gallery', array( $this, 'generate_gallery_uuid' ), 10, 3 );

        // 2. Modification du lien public
        add_filter( 'post_type_link', array( $this, 'use_uuid_in_permalink' ), 10, 2 );

        // 3. Règles de lecture
        add_action( 'init',          array( $this, 'add_uuid_rewrite_rules' ) );
        add_filter( 'query_vars',    array( $this, 'register_uuid_query_var' ) );
        add_action( 'pre_get_posts', array( $this, 'find_gallery_by_uuid' ) );
    }

    /**
     * Génère un UUID v4 lors de la première sauvegarde d'une galerie
     */
    public function generate_gallery_uuid( $post_id, $post, $update ) {
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        $existing_uuid = get_post_meta( $post_id, '_pp_uuid', true );

        if ( empty( $existing_uuid ) ) {
            update_post_meta( $post_id, '_pp_uuid', wp_generate_uuid4() );
        }
    }

    /**
     * Remplace le slug par l'UUID dans le permalink si l'option est active
     *
     * CORRECTION : is_admin() guard — évite de corrompre les liens dans l'admin WP
     * (lien "Voir la Galerie", get_permalink() dans la metabox, etc.)
     */
public function use_uuid_in_permalink( $post_link, $post ) {
        // 1. On ne change rien si on est dans l'administration
        if ( is_admin() ) {
            return $post_link;
        }

        // 2. On ne change rien si ce n'est pas une galerie PhotoProof
        if ( 'pp_gallery' !== $post->post_type ) {
            return $post_link;
        }

        // 3. On ne change rien si l'option "URLs aléatoires" est décochée
        if ( ! get_option( 'pp_use_random_urls' ) ) {
            return $post_link;
        }

        // 4. On récupère l'UUID (le code secret) de la galerie
        $uuid = get_post_meta( $post->ID, '_pp_uuid', true );

        // 5. Si pas d'UUID ou pas de nom de galerie, on ne touche à rien
        if ( empty( $uuid ) || empty( $post->post_name ) ) {
            return $post_link;
        }

        // 6. MAGIE : On remplace le nom de la galerie par l'UUID dans l'adresse
        $post_link = str_replace( $post->post_name, $uuid, $post_link );

        // 7. On s'assure que l'adresse finit bien par un "/" si besoin
        return user_trailingslashit( $post_link );
    }

    /**
     * Enregistre la rewrite rule pour les URLs UUID
     *
     * CORRECTION : la règle est TOUJOURS enregistrée (indépendamment de l'option).
     * C'est find_gallery_by_uuid() qui décide d'agir ou non selon l'option.
     * Cela évite les 404 au moment où l'option change (avant le prochain flush).
     */
    public function add_uuid_rewrite_rules() {
        $slug = defined( 'PHOTOPROOF_GALLERY_SLUG' ) ? PHOTOPROOF_GALLERY_SLUG : 'galerie-epreuve';

        add_rewrite_rule(
            '^' . preg_quote( $slug, '#' ) . '/([0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})/?$',
            'index.php?post_type=pp_gallery&pp_uuid=$matches[1]',
            'top'
        );
    }

    /**
     * Autorise WordPress à utiliser la variable de requête "pp_uuid"
     */
    public function register_uuid_query_var( $vars ) {
        $vars[] = 'pp_uuid';
        return $vars;
    }

    /**
     * Résout l'UUID vers la galerie correspondante
     */
public function find_gallery_by_uuid( $query ) {

    // 1. Sécurité : uniquement front + requête principale
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }

    // 2. Récupération de l'UUID
    $uuid = $query->get( 'pp_uuid' );

    if ( empty( $uuid ) ) {
        return;
    }

    // 3. Option désactivée
    if ( ! get_option( 'pp_use_random_urls' ) ) {
        return;
    }

    // 4. Validation stricte UUID v4
    if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid ) ) {
        return;
    }

    // 5. Recherche du post correspondant
    $posts = get_posts([
        'post_type'   => 'pp_gallery',
        'meta_key'    => '_pp_uuid',
        'meta_value'  => $uuid,
        'numberposts' => 1,
        'post_status' => 'publish',
    ]);

    // 6. Si aucun post → vraie 404
    if ( empty( $posts ) ) {
        $query->set_404();
        return;
    }

    $post = $posts[0];

    // 7. Injection propre dans WP_Query
    $query->set( 'p', $post->ID );
    $query->set( 'post_type', 'pp_gallery' );

    // 8. Nettoyage léger pour éviter conflits de slug
    $query->set( 'name', null );

    // ⚠️ IMPORTANT :
    // On ne touche PAS à :
    // - $query->is_single
    // - $query->is_singular
    // - $query->is_archive
    // WordPress va recalculer tout seul correctement
}
}

// L'instanciation est faite une seule fois dans PhotoProof::load_dependencies()