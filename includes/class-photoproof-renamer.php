<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * Logique de renommage — PhotoProof
 *
 * Le renommage est piloté par UNE SEULE option globale :
 *   - photoproof_enable_rename : ON/OFF dans Settings
 *
 * Si activé globalement :
 *   - Toutes les galeries voient leurs photos renommées automatiquement
 *   - Le nom de base = custom_rename (metabox) si défini, sinon le slug du titre WP
 *   - Format final : {base}-{index 4 digits}.{ext}
 *
 * FLOW :
 * 1. À chaque upload (photoproof_attachment_uploaded) : nom cible calculé
 *    et stocké en _photoproof_target_filename (utilisé en affichage dans la grille admin)
 * 2. À chaque save_post_photoproof_gallery : si le base_name a changé, renomme
 *    physiquement tous les fichiers de la galerie
 */
class PhotoProof_Renamer {

    public function __construct() {
        // Hook dédié déclenché par l'uploader custom — nom temporaire
        add_action( 'photoproof_attachment_uploaded', array( $this, 'store_target_filename' ), 10, 2 );

        // Renommage physique à chaque save (priorité 30 = après save_gallery_settings priorité 10)
        add_action( 'save_post_photoproof_gallery', array( $this, 'maybe_rename_on_save' ), 30, 2 );
    }

    /**
     * Calcule et stocke le nom cible temporaire sur l'attachement.
     * Appelé juste après la création de l'attachement en base.
     */
    public function store_target_filename( $attachment_id, $post_id ) {
        // Renommage activé globalement ?
        if ( ! get_option( 'photoproof_enable_rename' ) ) {
            return;
        }

        $base_name = $this->get_base_name( $post_id );
        $number    = $this->get_next_number( $post_id );
        $formatted = str_pad( $number, 4, '0', STR_PAD_LEFT );

        $new_name = sanitize_file_name( $base_name . '-' . $formatted );

        // Extension originale en minuscules
        $file_path = get_attached_file( $attachment_id );
        $ext       = $file_path ? '.' . strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) ) : '';

        // Stocker le nom cible (avec extension)
        update_post_meta( $attachment_id, '_photoproof_target_filename', $new_name . $ext );
    }

    /**
     * À chaque save_post : si le renommage est actif et que le base_name a changé,
     * renomme physiquement tous les fichiers de la galerie.
     *
     * Pas de restriction sur le post_status : peut renommer en brouillon, publié,
     * validé. Le photographe peut ajuster son naming à n'importe quel moment.
     */
    public function maybe_rename_on_save( $post_id, $post ) {
        // Renommage activé globalement ?
        if ( ! get_option( 'photoproof_enable_rename' ) ) {
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
        $last_base = get_post_meta( $post_id, '_photoproof_last_rename_base', true );

        if ( $last_base === $current_base ) {
            return; // Rien n'a changé, pas de renommage inutile
        }

        // Renommage complet de tous les fichiers de la galerie
        $this->rename_all_photos( $post_id, $current_base );

        // Stocker le base_name utilisé
        update_post_meta( $post_id, '_photoproof_last_rename_base', $current_base );
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

        $counter = 0;

        foreach ( $attachments as $attachment ) {
            $counter++;
            $formatted = str_pad( $counter, 4, '0', STR_PAD_LEFT );

            // Format simple : {base}-{index}
            $new_name = sanitize_file_name( $base_name . '-' . $formatted );

            $this->do_rename( $attachment->ID, $new_name );
        }

        // Mettre à jour le compteur final
        update_post_meta( $post_id, '_photoproof_rename_counter', $counter );
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
            update_post_meta( $attachment_id, '_photoproof_target_filename', $new_basename . $ext );
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
        update_post_meta( $attachment_id, '_photoproof_target_filename', $new_basename . $ext );

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

        // Charger image.php si nécessaire — wp_generate_attachment_metadata() en dépend
        if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php'; // phpcs:ignore PEAR.Files.IncludingFile.UseRequire -- needed for wp_generate_attachment_metadata()
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
        $custom = get_post_meta( $post_id, '_photoproof_custom_rename', true );

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
     * Retourne le prochain numéro pour le compteur atomique
     * Utilisé uniquement à l'upload (store_target_filename)
     */
    private function get_next_number( $post_id ) {
        $next = (int) get_post_meta( $post_id, '_photoproof_rename_counter', true ) + 1;
        update_post_meta( $post_id, '_photoproof_rename_counter', $next );
        return $next;
    }
}