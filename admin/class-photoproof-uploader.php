<?php
/**
 * Logique de gestion des fichiers et dossiers
 */
class PhotoProof_Uploader {

    public function __construct() {
        add_filter( 'wp_handle_upload_prefilter', array( $this, 'intercept_upload' ) );
    }

    public function intercept_upload( $file ) {
        // On n'agit que si l'upload vient de notre galerie PhotoProof
        if ( isset( $_REQUEST['post_id'] ) && get_post_type( $_REQUEST['post_id'] ) === 'pp_gallery' ) {
            add_filter( 'upload_dir', array( $this, 'set_custom_directory' ) );
        }
        return $file;
    }

    public function set_custom_directory( $path ) {
        $post_id = intval( $_REQUEST['post_id'] );
        $subdir  = '/photoproof/gallery-' . $post_id;

        $path['path']   = $path['basedir'] . $subdir;
        $path['url']    = $path['baseurl'] . $subdir;
        $path['subdir'] = $subdir;

        // Créer le dossier s'il n'existe pas
        if ( ! file_exists( $path['path'] ) ) {
            wp_mkdir_p( $path['path'] );
        }

        return $path;
    }
}