<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * Gestion du routage et des URLs UUID — PhotoProof
 *
 * Approche sans rewrite rules :
 * - parse_request intercepte l'URL UUID AVANT que WP ne cherche un post
 * - Zéro flush nécessaire, activation/désactivation de l'option instantanée
 * - Compatible avec tous les plugins de cache et de permaliens
 */
class PhotoProof_Router {

    public function __construct() {
        // 1. Génération de l'UUID à la création d'une galerie
        add_action( 'save_post_pp_gallery', array( $this, 'generate_gallery_uuid' ), 10, 3 );

        // 2. Modification du lien public (get_permalink)
        add_filter( 'post_type_link', array( $this, 'use_uuid_in_permalink' ), 10, 2 );

        // 3. Résolution UUID → post (sans rewrite rules)
        add_action( 'parse_request', array( $this, 'resolve_uuid_request' ) );
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
     * Pas de guard is_admin() — l'URL UUID doit être visible partout
     * (metabox, colonnes admin, front-end)
     */
    public function use_uuid_in_permalink( $post_link, $post ) {
        if ( 'pp_gallery' !== $post->post_type ) {
            return $post_link;
        }

        if ( ! get_option( 'pp_use_random_urls' ) ) {
            return $post_link;
        }

        $uuid = get_post_meta( $post->ID, '_pp_uuid', true );

        if ( empty( $uuid ) || empty( $post->post_name ) ) {
            return $post_link;
        }

        $post_link = str_replace( $post->post_name, $uuid, $post_link );

        return user_trailingslashit( $post_link );
    }

    /**
     * Intercepte la requête AVANT que WP ne cherche un post
     *
     * Détecte le pattern /galerie-epreuve/{uuid}/ dans l'URL,
     * résout le post correspondant, et injecte son ID dans la query.
     *
     * Avantage : aucune rewrite rule, aucun flush nécessaire.
     */
    public function resolve_uuid_request( $wp ) {
        // Seulement en front
        if ( is_admin() ) {
            return;
        }

        // Pas d'option UUID → rien à faire
        if ( ! get_option( 'pp_use_random_urls' ) ) {
            return;
        }

        // Récupérer le chemin demandé
        $request = trim( $wp->request, '/' );

        if ( empty( $request ) ) {
            return;
        }

        $slug = defined( 'PHOTOPROOF_GALLERY_SLUG' ) ? PHOTOPROOF_GALLERY_SLUG : 'galerie-epreuve';

        // Pattern : slug/uuid-v4
        $pattern = '#^' . preg_quote( $slug, '#' ) . '/([0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})$#';

        if ( ! preg_match( $pattern, $request, $matches ) ) {
            return;
        }

        $uuid = $matches[1];

        // Recherche du post par UUID
        $posts = get_posts( array(
            'post_type'   => 'pp_gallery',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'meta_key'    => '_pp_uuid',
            'meta_value'  => $uuid,
            'numberposts' => 1,
            'post_status' => 'publish',
        ) );

        if ( empty( $posts ) ) {
            return; // Laisser WP gérer la 404 normalement
        }

        $post = $posts[0];

        // Injecter le post dans la query WP
        $wp->query_vars = array(
            'p'         => $post->ID,
            'post_type' => 'pp_gallery',
        );
    }
}

// L'instanciation est faite une seule fois dans PhotoProof::load_dependencies()
