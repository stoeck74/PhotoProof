<?php
/**
 * Logique de renommage intelligente et hiérarchique
 * Priorité : Nom Custom (Metabox) > Titre Galerie > Réglage Global
 */
class PhotoProof_Renamer {

    public function __construct() {
        // On intercepte l'upload avant que WordPress ne crée le fichier sur le serveur
        add_filter( 'wp_handle_upload_prefilter', array( $this, 'rename_photo_on_upload' ) );
    }

    public function rename_photo_on_upload( $file ) {
        // 1. On récupère l'ID de la galerie depuis la requête d'upload
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        // Sécurité : Si on n'est pas dans une galerie PhotoProof, on ne touche à rien
        if ( ! $post_id || get_post_type($post_id) !== 'pp_gallery' ) {
            return $file;
        }

        // 2. RÉCUPÉRATION DE LA BASE DU NOM
        // On regarde si un nom spécifique a été saisi dans la Metabox de CETTE galerie
        $custom_prefix = get_post_meta( $post_id, '_pp_custom_rename', true );
        
        if ( ! empty( $custom_prefix ) ) {
            $base_name = sanitize_title( $custom_prefix );
        } else {
            // Sinon, on prend le titre de la galerie (nettoyé pour les noms de fichiers)
            $base_name = sanitize_title( get_the_title( $post_id ) );
        }

        // 3. RÉCUPÉRATION DU PATTERN (Structure)
        // On prend le réglage global, ou la valeur par défaut si vide
        $pattern = get_option( 'pp_rename_pattern', '{gallery_title}-{index}' );
        if ( empty( $pattern ) ) { 
            $pattern = '{gallery_title}-{index}'; 
        }

        // 4. SÉCURITÉ INDEXATION
        // Si le photographe a oublié de mettre {index} dans son réglage, on le force à la fin
        // Cela garantit que chaque fichier aura son numéro unique 0001, 0002...
        if ( stripos( $pattern, '{index}' ) === false ) {
            $pattern .= '-{index}';
        }

        // 5. CALCUL DE L'INDEX 0001
        // On compte combien d'images sont déjà liées à cette galerie précise
        $attachments = get_posts( array(
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            'post_parent'    => $post_id,
            'post_status'    => 'inherit'
        ) );

        $next_number = count( $attachments ) + 1;
        
        // Formatage sur 4 chiffres (0001)
        $formatted_number = str_pad( $next_number, 4, '0', STR_PAD_LEFT );

        // 6. REMPLACEMENT DES BALISES (Insensible à la casse)
        $new_name = str_ireplace( '{gallery_title}', $base_name, $pattern );
        $new_name = str_ireplace( '{index}', $formatted_number, $new_name );

        // 7. NETTOYAGE FINAL DU NOM
        // On enlève les derniers caractères interdits pour la compatibilité serveur
        $new_name = sanitize_file_name( $new_name );

        // 8. RECONSTITUTION DU FICHIER
        $info = pathinfo( $file['name'] );
        $ext  = empty( $info['extension'] ) ? '' : '.' . $info['extension'];

        $file['name'] = $new_name . $ext;

        return $file;
    }
}