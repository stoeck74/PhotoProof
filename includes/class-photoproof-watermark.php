<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * Génération des watermarks — PhotoProof
 *
 * FLOW :
 * - À la publication d'une galerie → génère les copies watermarkées
 * - GD natif avec fallback Imagick
 * - Logo centré, opacité configurable
 * - Dossier : photoproof/gallery-{id}/watermarked/
 * - Si pas de watermark configuré → rien ne se passe
 * - Les galeries déjà publiées ne sont pas retouchées
 */
class PhotoProof_Watermark {

    public function __construct() {
        // Déclenché à chaque sauvegarde de galerie (priorité 40 = après le renamer en 30)
        add_action( 'save_post_photoproof_gallery', array( $this, 'generate_watermarks' ), 40, 2 );
    }

    /**
     * Génère les copies watermarkées pour toutes les photos d'une galerie.
     * Appelé à chaque save : vérifie l'état des conditions et agit.
     *
     * Modifie l'état des fichiers selon le toggle metabox :
     *   - Si le toggle vient d'être activé    → génération des copies
     *   - Si le toggle est déjà actif          → no-op (déjà à jour)
     *   - Si le toggle vient d'être désactivé  → no-op (les copies restent sur disque,
     *                                            le frontend les ignore — nettoyage à
     *                                            l'expiration ou suppression galerie)
     */
    public function generate_watermarks( $post_id, $post ) {

        // Éviter les autosaves et révisions
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Vérifier que le watermark doit être appliqué pour cette galerie
        if ( ! self::is_watermark_active_for_gallery( $post_id ) ) {
            return;
        }

        $watermark_id   = get_option( 'photoproof_global_watermark' );
        $watermark_path = get_attached_file( $watermark_id );

        if ( ! $watermark_path || ! file_exists( $watermark_path ) ) {
            return;
        }

        $opacity = intval( get_option( 'photoproof_watermark_opacity', 50 ) );
        $opacity = max( 10, min( 100, $opacity ) ); // clamp 10-100

        // Dossier de destination
        $upload_dir   = wp_upload_dir();
        $wm_dir       = $upload_dir['basedir'] . '/photoproof/gallery-' . $post_id . '/watermarked';

        if ( ! file_exists( $wm_dir ) ) {
            wp_mkdir_p( $wm_dir );
            file_put_contents( $wm_dir . '/index.php', '<?php // Silence is golden' );
        }

        // Récupérer toutes les photos de la galerie
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

        foreach ( $attachments as $attachment ) {
            $source_path = get_attached_file( $attachment->ID );

            if ( ! $source_path || ! file_exists( $source_path ) ) {
                continue;
            }

            $filename = basename( $source_path );
            $dest_path = $wm_dir . '/' . $filename;

            // Générer la version watermarkée
            $success = $this->apply_watermark( $source_path, $watermark_path, $dest_path, $opacity );

            if ( $success ) {
                // Stocker l'URL de la version watermarkée en meta
                $wm_url = $upload_dir['baseurl'] . '/photoproof/gallery-' . $post_id . '/watermarked/' . $filename;
                update_post_meta( $attachment->ID, '_photoproof_watermarked_url', $wm_url );
            }
        }
    }

    /**
     * Applique le watermark sur une image
     * GD natif avec fallback Imagick
     *
     * @param string $source_path   Chemin de l'image source
     * @param string $watermark_path Chemin du logo watermark (PNG recommandé)
     * @param string $dest_path     Chemin de destination
     * @param int    $opacity       Opacité 10-100
     * @return bool
     */
    private function apply_watermark( $source_path, $watermark_path, $dest_path, $opacity ) {
        // Essayer GD en premier
        if ( extension_loaded( 'gd' ) ) {
            return $this->apply_watermark_gd( $source_path, $watermark_path, $dest_path, $opacity );
        }

        // Fallback Imagick
        if ( extension_loaded( 'imagick' ) ) {
            return $this->apply_watermark_imagick( $source_path, $watermark_path, $dest_path, $opacity );
        }
        return false;
    }

    /**
     * Applique le watermark via GD
     */
    private function apply_watermark_gd( $source_path, $watermark_path, $dest_path, $opacity ) {
        // Charger l'image source selon son type
        $source_info = @getimagesize( $source_path );
        if ( ! $source_info ) return false;

        $source_mime = $source_info['mime'];

        switch ( $source_mime ) {
            case 'image/jpeg':
                $source = @imagecreatefromjpeg( $source_path );
                break;
            case 'image/png':
                $source = @imagecreatefrompng( $source_path );
                break;
            case 'image/webp':
                $source = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $source_path ) : false;
                break;
            default:
                return false;
        }

        if ( ! $source ) return false;

        // Charger le watermark (PNG avec transparence)
        $watermark = @imagecreatefrompng( $watermark_path );
        if ( ! $watermark ) {
            imagedestroy( $source );
            return false;
        }

        $src_w = imagesx( $source );
        $src_h = imagesy( $source );
        $wm_w  = imagesx( $watermark );
        $wm_h  = imagesy( $watermark );

        // Calculer la taille du watermark — 50% de la largeur de l'image max
        $max_wm_w = intval( $src_w * 0.5 );
        $max_wm_h = intval( $src_h * 0.5 );

        // Conserver le ratio du watermark
        $ratio = min( $max_wm_w / $wm_w, $max_wm_h / $wm_h );
        $new_wm_w = intval( $wm_w * $ratio );
        $new_wm_h = intval( $wm_h * $ratio );

        // Redimensionner le watermark
        $wm_resized = imagecreatetruecolor( $new_wm_w, $new_wm_h );
        imagealphablending( $wm_resized, false );
        imagesavealpha( $wm_resized, true );
        $transparent = imagecolorallocatealpha( $wm_resized, 0, 0, 0, 127 );
        imagefilledrectangle( $wm_resized, 0, 0, $new_wm_w, $new_wm_h, $transparent );
        imagecopyresampled( $wm_resized, $watermark, 0, 0, 0, 0, $new_wm_w, $new_wm_h, $wm_w, $wm_h );

        // Appliquer l'opacité sur le watermark redimensionné
        // GD ne gère pas l'opacité globale nativement — on l'applique pixel par pixel
        $this->apply_opacity_gd( $wm_resized, $new_wm_w, $new_wm_h, $opacity );

        // Position : centré
        $dest_x = intval( ( $src_w - $new_wm_w ) / 2 );
        $dest_y = intval( ( $src_h - $new_wm_h ) / 2 );

        // Fusionner
        imagealphablending( $source, true );
        imagecopy( $source, $wm_resized, $dest_x, $dest_y, 0, 0, $new_wm_w, $new_wm_h );

        // Sauvegarder
        $result = false;
        switch ( $source_mime ) {
            case 'image/jpeg':
                $result = imagejpeg( $source, $dest_path, 92 );
                break;
            case 'image/png':
                $result = imagepng( $source, $dest_path, 9 );
                break;
            case 'image/webp':
                $result = function_exists( 'imagewebp' ) ? imagewebp( $source, $dest_path, 92 ) : false;
                break;
        }

        imagedestroy( $source );
        imagedestroy( $watermark );
        imagedestroy( $wm_resized );

        return $result;
    }

    /**
     * Applique l'opacité sur une image GD pixel par pixel
     * (GD ne supporte pas imagefilter ALPHA globalement)
     *
     * @param resource $image   Image GD
     * @param int      $width
     * @param int      $height
     * @param int      $opacity 10-100
     */
    private function apply_opacity_gd( $image, $width, $height, $opacity ) {
        $opacity_ratio = $opacity / 100;

        imagealphablending( $image, false );
        imagesavealpha( $image, true );

        for ( $x = 0; $x < $width; $x++ ) {
            for ( $y = 0; $y < $height; $y++ ) {
                $color   = imagecolorat( $image, $x, $y );
                $alpha   = ( $color >> 24 ) & 0x7F; // 0 = opaque, 127 = transparent
                $current_opacity = 1 - ( $alpha / 127 );
                $new_opacity     = $current_opacity * $opacity_ratio;
                $new_alpha       = intval( 127 - ( $new_opacity * 127 ) );

                $r = ( $color >> 16 ) & 0xFF;
                $g = ( $color >> 8 )  & 0xFF;
                $b = $color           & 0xFF;

                $new_color = imagecolorallocatealpha( $image, $r, $g, $b, $new_alpha );
                imagesetpixel( $image, $x, $y, $new_color );
            }
        }
    }

    /**
     * Applique le watermark via Imagick (fallback)
     */
    private function apply_watermark_imagick( $source_path, $watermark_path, $dest_path, $opacity ) {
        try {
            $source    = new Imagick( $source_path );
            $watermark = new Imagick( $watermark_path );

            $src_w = $source->getImageWidth();
            $src_h = $source->getImageHeight();
            $wm_w  = $watermark->getImageWidth();
            $wm_h  = $watermark->getImageHeight();

            // Redimensionner le watermark à 50% max de l'image
            $max_wm_w = intval( $src_w * 0.5 );
            $max_wm_h = intval( $src_h * 0.5 );
            $ratio    = min( $max_wm_w / $wm_w, $max_wm_h / $wm_h );

            $new_wm_w = intval( $wm_w * $ratio );
            $new_wm_h = intval( $wm_h * $ratio );

            $watermark->resizeImage( $new_wm_w, $new_wm_h, Imagick::FILTER_LANCZOS, 1 );

            // Appliquer l'opacité
            $watermark->evaluateImage( Imagick::EVALUATE_MULTIPLY, $opacity / 100, Imagick::CHANNEL_ALPHA );

            // Position centrée
            $dest_x = intval( ( $src_w - $new_wm_w ) / 2 );
            $dest_y = intval( ( $src_h - $new_wm_h ) / 2 );

            // Composer
            $source->compositeImage( $watermark, Imagick::COMPOSITE_OVER, $dest_x, $dest_y );

            // Sauvegarder
            $source->writeImage( $dest_path );

            $source->destroy();
            $watermark->destroy();

            return true;

        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'PhotoProof Watermark Imagick : ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            return false;
        }
    }

    /**
     * Retourne l'URL de la version watermarkée d'un attachement
     * Si elle n'existe pas, retourne l'URL originale
     *
     * @param int $attachment_id
     * @return string
     */
    public static function get_watermarked_url( $attachment_id ) {
        $wm_url = get_post_meta( $attachment_id, '_photoproof_watermarked_url', true );

        if ( $wm_url ) {
            // Vérifier que le fichier existe physiquement
            $upload_dir = wp_upload_dir();
            $wm_path    = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $wm_url );

            if ( file_exists( $wm_path ) ) {
                return $wm_url;
            }
        }

        // Fallback sur l'original
        return wp_get_attachment_url( $attachment_id );
    }

    /**
     * Détermine si le watermark doit être appliqué pour une galerie donnée.
     * Retourne true uniquement si :
     *  - un watermark global est configuré dans les Réglages
     *  - ET le toggle "watermark_settings" de la galerie est à 'yes'
     *
     * @param int $post_id ID de la galerie
     * @return bool
     */
    public static function is_watermark_active_for_gallery( $post_id ) {
        // 1. Watermark global configuré ?
        if ( ! get_option( 'photoproof_global_watermark' ) ) {
            return false;
        }

        // 2. Toggle activé pour CETTE galerie ?
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT watermark_settings FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
            $post_id
        ) );

        return ( $row && $row->watermark_settings === 'yes' );
    }
}