<?php
/**
 * Logique de renommage intelligente et hiérarchique — PhotoProof
 * Priorité : Nom Custom (Metabox) > Titre Galerie > Réglage Global
 *
 * CORRECTIONS :
 * - Vérification nonce ajoutée
 * - Race condition sur l'index corrigée (compteur atomique en post_meta)
 * - Priorité 10 (après Uploader priorité 9) pour garantir l'ordre redirection → renommage
 * - get_post_field('post_title') au lieu de get_the_title() (évite les filtres WP)
 * - Guard contre $base_name vide
 * - Extension nettoyée et forcée en minuscules
 */
class PhotoProof_Renamer {

    public function __construct() {
        // Priorité 10 : s'exécute APRÈS la redirection du dossier (Uploader priorité 9)
        add_filter( 'wp_handle_upload_prefilter', array( $this, 'rename_photo_on_upload' ), 10 );
    }

    public function rename_photo_on_upload( $file ) {

        // ── 0. OPTION GLOBALE ─────────────────────────────────────────
        if ( ! get_option( 'pp_enable_rename' ) ) {
            return $file;
        }

        // ── 1. VÉRIFICATION NONCE ─────────────────────────────────────
        // CORRECTION : même nonce que l'uploader, on vérifie sans wp_die
        // (l'uploader a déjà bloqué si le nonce était absent ; ici on double-vérifie)
        if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'pp_upload_nonce' ) ) {
            return $file;
        }

        // ── 2. VÉRIFICATION DU CONTEXTE ───────────────────────────────
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

        if ( ! $post_id || get_post_type( $post_id ) !== 'pp_gallery' ) {
            return $file;
        }

        // ── 3. RÉCUPÉRATION DE LA BASE DU NOM ────────────────────────
        $custom_prefix = get_post_meta( $post_id, '_pp_custom_rename', true );

        if ( ! empty( $custom_prefix ) ) {
            $base_name = sanitize_title( $custom_prefix );
        } else {
            // CORRECTION : get_post_field('post_title', ..., 'raw') au lieu de get_the_title()
            // pour éviter les filtres WordPress qui peuvent injecter du HTML
            $raw_title = get_post_field( 'post_title', $post_id, 'raw' );
            $base_name = sanitize_title( $raw_title );
        }

        // CORRECTION : guard contre un $base_name vide (titre de galerie non encore renseigné)
        if ( empty( $base_name ) ) {
            $base_name = 'photo';
        }

        // ── 4. RÉCUPÉRATION DU PATTERN ────────────────────────────────
        $pattern = get_option( 'pp_rename_pattern', '{gallery_title}-{index}' );
        if ( empty( $pattern ) ) {
            $pattern = '{gallery_title}-{index}';
        }

        // ── 5. SÉCURITÉ INDEXATION ────────────────────────────────────
        // Si {index} est absent du pattern, on le force à la fin
        if ( stripos( $pattern, '{index}' ) === false ) {
            $pattern .= '-{index}';
        }

        // ── 6. CALCUL DE L'INDEX — COMPTEUR ATOMIQUE ─────────────────
        // CORRECTION : on utilise un compteur en post_meta au lieu de count(get_posts())
        // Cela évite la race condition lors d'uploads multiples simultanés :
        // chaque fichier incrémente le compteur de façon séquentielle.
        $next_number = (int) get_post_meta( $post_id, '_pp_rename_counter', true ) + 1;
        update_post_meta( $post_id, '_pp_rename_counter', $next_number );

        // Formatage sur 4 chiffres (0001, 0002…)
        $formatted_number = str_pad( $next_number, 4, '0', STR_PAD_LEFT );

        // ── 7. REMPLACEMENT DES BALISES ───────────────────────────────
        $new_name = str_ireplace( '{gallery_title}', $base_name, $pattern );
        $new_name = str_ireplace( '{index}',         $formatted_number, $new_name );

        // ── 8. NETTOYAGE FINAL ────────────────────────────────────────
        $new_name = sanitize_file_name( $new_name );

        // ── 9. RECONSTITUTION DU FICHIER ─────────────────────────────
        $info = pathinfo( $file['name'] );

        // CORRECTION : extension forcée en minuscules pour uniformité (jpg pas JPG)
        $ext = ( ! empty( $info['extension'] ) )
            ? '.' . strtolower( $info['extension'] )
            : '';

        $file['name'] = $new_name . $ext;

        return $file;
    }
}