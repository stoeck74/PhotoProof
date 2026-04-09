<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * Logique de renommage différé — PhotoProof
 *
 * FLOW :
 * 1. À l'upload (pp_attachment_uploaded) → calcule le nom cible temporaire
 *    et le stocke en _pp_target_filename (affiché dans la grille admin)
 * 2. À la sauvegarde du post (save_post_pp_gallery, priorité 30)
 *    → si le post est publié ET que le base_name a changé → renommage physique
 *
 * Priorité 30 = après save_gallery_settings (priorité 10) qui sauvegarde
 * le meta _pp_custom_rename dont on a besoin.
 *
 * Priorité nom : Préfixe custom (metabox) > Titre galerie > fallback 'photo'
 */
class PhotoProof_Renamer {

    public function __construct() {
        // Hook dédié déclenché par l'uploader custom — nom temporaire
        add_action( 'pp_attachment_uploaded', array( $this, 'store_target_filename' ), 10, 2 );

        // Renommage physique au save (publish + update)
        add_action( 'save_post_pp_gallery', array( $this, 'maybe_rename_on_save' ), 30, 2 );
    }

    /**
     * Calcule et stocke le nom cible temporaire sur l'attachement
     * Appelé juste après la création de l'attachement en base
     */
    public function store_target_filename( $attachment_id, $post_id ) {
        if ( ! get_option( 'pp_enable_rename' ) ) {
            return;
        }

        $base_name = $this->get_base_name( $post_id );
        $pattern   = $this->get_pattern();
        $number    = $this->get_next_number( $post_id );
        $formatted = str_pad( $number, 4, '0', STR_PAD_LEFT );

        $new_name = str_ireplace( '{gallery_title}', $base_name, $pattern );
        $new_name = str_ireplace( '{index}', $formatted, $new_name );
        $new_name = sanitize_file_name( $new_name );

        // Extension originale en minuscules
        $file_path = get_attached_file( $attachment_id );
        $ext       = $file_path ? '.' . strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) ) : '';

        // Stocker le nom cible (avec extension)
        update_post_meta( $attachment_id, '_pp_target_filename', $new_name . $ext );
        // Stocker aussi le post_id de la galerie pour le renommage différé
        update_post_meta( $attachment_id, '_pp_gallery_post_id', $post_id );
    }

    /**
     * Au save : renommage physique SI le post est publié ET le base_name a changé
     *
     * Hookée sur save_post_pp_gallery avec priorité 30
     * (après save_gallery_settings à priorité 10 qui sauvegarde _pp_custom_rename)
     */
    public function maybe_rename_on_save( $post_id, $post ) {
        if ( ! get_option( 'pp_enable_rename' ) ) {
            return;
        }

        // Ne renommer que si le post est publié
        if ( $post->post_status !== 'publish' ) {
            return;
        }

        // Éviter les autosaves et révisions
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Calculer le base_name actuel
        $current_base = $this->get_base_name( $post_id );

        // Comparer avec le dernier base_name utilisé
        $last_base = get_post_meta( $post_id, '_pp_last_rename_base', true );

        if ( $last_base === $current_base ) {
            return; // Rien n'a changé, pas de renommage inutile
        }

        // Renommage complet
        $this->rename_all_photos( $post_id, $current_base );

        // Stocker le base_name utilisé
        update_post_meta( $post_id, '_pp_last_rename_base', $current_base );
    }

    /**
     * Renomme toutes les photos d'une galerie
     */
    private function rename_all_photos( $post_id, $base_name ) {
        $attachments = get_posts( array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'post_parent'    => $post_id,
            'posts_per_page' => -1,
            'orderby'        => 'menu_order date',
            'order'          => 'ASC',
        ) );

        if ( empty( $attachments ) ) {
            return;
        }

        $pattern = $this->get_pattern();
        $counter = 0;

        foreach ( $attachments as $attachment ) {
            $counter++;
            $formatted = str_pad( $counter, 4, '0', STR_PAD_LEFT );

            $new_name = str_ireplace( '{gallery_title}', $base_name, $pattern );
            $new_name = str_ireplace( '{index}', $formatted, $new_name );
            $new_name = sanitize_file_name( $new_name );

            $this->do_rename( $attachment->ID, $new_name );
        }

        // Mettre à jour le compteur final
        update_post_meta( $post_id, '_pp_rename_counter', $counter );
    }

    /**
     * Renommage physique d'un fichier + mise à jour des metas WP
     */
    private function do_rename( $attachment_id, $new_basename ) {
        $old_file = get_attached_file( $attachment_id );

        if ( ! $old_file || ! file_exists( $old_file ) ) {
            return false;
        }

        $dir      = dirname( $old_file );
        $ext      = '.' . strtolower( pathinfo( $old_file, PATHINFO_EXTENSION ) );
        $new_file = $dir . '/' . $new_basename . $ext;

        // Éviter d'écraser un fichier existant avec un autre nom
        if ( $old_file === $new_file ) {
            // Même fichier, juste mettre à jour le meta target
            update_post_meta( $attachment_id, '_pp_target_filename', $new_basename . $ext );
            return true;
        }

        // Si le nom cible existe déjà (doublon), ajouter un suffixe
        if ( file_exists( $new_file ) ) {
            $new_file = $dir . '/' . $new_basename . '-' . $attachment_id . $ext;
            $new_basename = $new_basename . '-' . $attachment_id;
        }

        // Renommage physique
        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
        if ( ! @rename( $old_file, $new_file ) ) {
            return false;
        }

        // Mise à jour du chemin en base WP
        $upload_dir  = wp_upload_dir();
        $relative    = str_replace( $upload_dir['basedir'] . '/', '', $new_file );

        update_post_meta( $attachment_id, '_wp_attached_file', $relative );
        update_post_meta( $attachment_id, '_pp_target_filename', $new_basename . $ext );

        // Mettre à jour le guid (URL publique)
        global $wpdb;
        $new_url = $upload_dir['baseurl'] . '/' . $relative;
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->posts,
            array( 'guid' => $new_url ),
            array( 'ID'   => $attachment_id ),
            array( '%s' ),
            array( '%d' )
        );

        // Charger les fonctions media — pas disponibles dans le contexte REST API
        if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        // Régénérer les métadonnées (thumbnails, dimensions)
        $metadata = wp_generate_attachment_metadata( $attachment_id, $new_file );
        if ( $metadata ) {
            wp_update_attachment_metadata( $attachment_id, $metadata );
        }

        return true;
    }

    /**
     * Retourne le nom de base à utiliser pour le renommage
     * Priorité : préfixe custom > titre galerie > fallback
     */
    private function get_base_name( $post_id ) {
        $custom = get_post_meta( $post_id, '_pp_custom_rename', true );

        if ( ! empty( $custom ) ) {
            return sanitize_title( $custom );
        }

        $title = get_post_field( 'post_title', $post_id, 'raw' );

        if ( ! empty( $title ) ) {
            return sanitize_title( $title );
        }

        return 'photo';
    }

    /**
     * Retourne le pattern de renommage depuis les settings
     */
    private function get_pattern() {
        $pattern = get_option( 'pp_rename_pattern', '{gallery_title}-{index}' );

        if ( empty( $pattern ) ) {
            $pattern = '{gallery_title}-{index}';
        }

        if ( stripos( $pattern, '{index}' ) === false ) {
            $pattern .= '-{index}';
        }

        return $pattern;
    }

    /**
     * Retourne le prochain numéro pour le compteur atomique
     * Utilisé uniquement à l'upload (store_target_filename)
     */
    private function get_next_number( $post_id ) {
        $next = (int) get_post_meta( $post_id, '_pp_rename_counter', true ) + 1;
        update_post_meta( $post_id, '_pp_rename_counter', $next );
        return $next;
    }
}
