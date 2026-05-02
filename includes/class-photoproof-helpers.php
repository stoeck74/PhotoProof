<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * PhotoProof Helpers — Template Tags & Shortcode
 *
 * TEMPLATE TAGS (pour développeurs) :
 * - photoproof_get_client_galleries( $user_id )     → array de données galeries
 * - photoproof_get_gallery_status( $post_id )       → string statut
 * - photoproof_get_gallery_photo_count( $post_id )  → int nombre de photos
 * - photoproof_get_gallery_thumbnail( $post_id )    → string URL thumbnail
 * - photoproof_get_gallery_selection( $post_id )    → array IDs photos sélectionnées
 * - photoproof_is_gallery_locked( $post_id )        → bool
 *
 * SHORTCODE :
 * - [photoproof_galleries_client] → liste des galeries du client connecté
 */
class PhotoProof_Helpers {

    public function __construct() {
        add_shortcode( 'photoproof_galleries_client', array( $this, 'shortcode_galleries_client' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_shortcode_assets' ) );
    }

    /**
     * Enregistre (sans enqueue) la feuille de style du shortcode.
     * L'enqueue est fait dans le shortcode lui-même, uniquement s'il est affiché.
     */
    public function register_shortcode_assets() {
        wp_register_style(
            'photoproof-shortcode-css',
            PHOTOPROOF_URL . 'public/css/photoproof-shortcode.css',
            array(),
            PHOTOPROOF_VERSION
        );
    }

    // ══════════════════════════════════════════════════════════════════
    // SHORTCODE
    // ══════════════════════════════════════════════════════════════════

    /**
     * Shortcode [photoproof_galleries_client]
     * Affiche la liste des galeries du client connecté
     *
     * Attributs :
     * - columns     : nombre de colonnes (défaut: 1)
     * - show_status : afficher le statut (défaut: true)
     * - show_count  : afficher le nombre de photos (défaut: true)
     * - show_date   : afficher la date (défaut: true)
     *
     * Exemple : [photoproof_galleries_client columns="2" show_date="false"]
     */
    public function shortcode_galleries_client( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p class="pp-sc-notice">Vous devez être connecté pour voir vos galeries.</p>';
        }

        $atts = shortcode_atts( array(
            'columns'     => 1,
            'show_status' => 'true',
            'show_count'  => 'true',
            'show_date'   => 'true',
        ), $atts, 'photoproof_galleries_client' );

        $user_id  = get_current_user_id();
        $galleries = photoproof_get_client_galleries( $user_id );

        if ( empty( $galleries ) ) {
            return '<p class="pp-sc-notice">Aucune galerie disponible pour le moment.</p>';
        }

        $cols        = max( 1, intval( $atts['columns'] ) );
        $show_status = $atts['show_status'] !== 'false';
        $show_count  = $atts['show_count']  !== 'false';
        $show_date   = $atts['show_date']   !== 'false';

        // Enqueue le style du shortcode (uniquement si le shortcode est affiché)
        wp_enqueue_style( 'photoproof-shortcode-css' );

        // Injecter la valeur des colonnes via une CSS custom property scoping par instance.
        // On utilise wp_add_inline_style plutôt qu'un attribut style="..." inline pour respecter
        // les recommandations WordPress sur les styles dynamiques.
        $instance_id = 'photoproof-sc-' . wp_generate_password( 8, false, false );
        wp_add_inline_style(
            'photoproof-shortcode-css',
            '#' . $instance_id . ' { --photoproof-sc-cols: ' . absint( $cols ) . '; }'
        );

        ob_start();
        ?>
        <div id="<?php echo esc_attr( $instance_id ); ?>" class="pp-sc-grid">
        <?php foreach ( $galleries as $g ) :
            $status_label = $g['status'] === 'valide' ? esc_html__( 'Validé', 'photoproof' ) : esc_html__( 'Ouvert', 'photoproof' );
            $dot_class    = $g['status'] === 'valide' ? 'pp-sc-dot-validated' : 'pp-sc-dot-open';
            ?>
            <a href="<?php echo esc_url( $g['url'] ); ?>" class="pp-sc-card">
                <?php if ( $g['thumbnail_url'] ) : ?>
                    <img src="<?php echo esc_url( $g['thumbnail_url'] ); ?>"
                         alt="<?php echo esc_attr( $g['title'] ); ?>"
                         class="pp-sc-thumb">
                <?php else : ?>
                    <div class="pp-sc-thumb"></div>
                <?php endif; ?>

                <div class="pp-sc-body">
                    <p class="pp-sc-title"><?php echo esc_html( $g['title'] ); ?></p>
                    <div class="pp-sc-meta">
                        <?php if ( $show_status ) : ?>
                        <span class="pp-sc-status">
                            <span class="pp-sc-dot <?php echo esc_attr( $dot_class ); ?>"></span>
                            
                            <?php /* On sécurise le texte avec esc_html */ ?>
                            <?php echo esc_html( $status_label ); ?>
                        </span>
                        <?php endif; ?>
                        <?php if ( $show_count ) : ?>
                            <span class="pp-sc-info">
                            <?php
                            printf(
                                /* translators: %d: number of photos */
                                esc_html( _n( '%d photo', '%d photos', $g['photo_count'], 'photoproof' ) ),
                                absint( $g['photo_count'] )
                            );
                            ?>
                        </span>
                        <?php endif; ?>
                        <?php if ( $show_date ) : ?>
                            <span class="pp-sc-info"><?php echo esc_html( $g['date'] ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

// ══════════════════════════════════════════════════════════════════
// TEMPLATE TAGS — Fonctions globales pour les développeurs
// ══════════════════════════════════════════════════════════════════

/**
 * Retourne les galeries d'un client
 *
 * @param int $user_id ID de l'utilisateur WP
 * @return array [
 *   'id'            => int,
 *   'title'         => string,
 *   'url'           => string,
 *   'status'        => string (publie|valide|ferme),
 *   'photo_count'   => int,
 *   'thumbnail_url' => string|null,
 *   'date'          => string (d.m.Y),
 *   'selected_ids'  => array,
 * ]
 */
function photoproof_get_client_galleries( $user_id ) {
    global $wpdb;

    $post_ids = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        "SELECT post_id FROM {$wpdb->prefix}photoproof_galleries
         WHERE client_id = %d
         AND status IN ('publie', 'valide')",
        $user_id
    ) );

    if ( empty( $post_ids ) ) return array();

    $posts = get_posts( array(
        'post_type'      => 'photoproof_gallery',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'post__in'       => $post_ids,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );

    $result = array();

    foreach ( $posts as $post ) {
        $result[] = array(
            'id'            => $post->ID,
            'title'         => get_the_title( $post->ID ),
            'url'           => get_permalink( $post->ID ),
            'status'        => photoproof_get_gallery_status( $post->ID ),
            'photo_count'   => photoproof_get_gallery_photo_count( $post->ID ),
            'thumbnail_url' => photoproof_get_gallery_thumbnail( $post->ID ),
            'date'          => get_the_date( 'd.m.Y', $post->ID ),
            'selected_ids'  => photoproof_get_gallery_selection( $post->ID ),
        );
    }

    return $result;
}

/**
 * Retourne le statut PhotoProof d'une galerie
 *
 * @param int $post_id
 * @return string publie|valide|ferme|brouillon
 */
function photoproof_get_gallery_status( $post_id ) {
    global $wpdb;
    $cache_key = 'photoproof_gallery_status_' . $post_id;
    $row       = wp_cache_get( $cache_key, 'photoproof' );
    if ( false === $row ) {
        $row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT status FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
            $post_id
        ) );
        wp_cache_set( $cache_key, $row, 'photoproof', 60 );
    }
    return $row ? $row->status : 'brouillon';
}

/**
 * Retourne le nombre de photos d'une galerie
 *
 * @param int $post_id
 * @return int
 */
function photoproof_get_gallery_photo_count( $post_id ) {
    global $wpdb;
    $cache_key = 'photoproof_gallery_photo_count_' . $post_id;
    $count     = wp_cache_get( $cache_key, 'photoproof' );
    if ( false === $count ) {
        $count = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
             AND post_status = 'inherit'
             AND post_parent = %d",
            $post_id
        ) );
        wp_cache_set( $cache_key, $count, 'photoproof', 60 );
    }
    return $count;
}

/**
 * Retourne l'URL du thumbnail d'une galerie (première photo)
 *
 * @param int    $post_id
 * @param string $size     Taille WP (défaut: medium)
 * @return string|null
 */
function photoproof_get_gallery_thumbnail( $post_id, $size = 'medium' ) {
    $first = get_posts( array(
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'post_status'    => 'inherit',
        'post_parent'    => $post_id,
        'posts_per_page' => 1,
        'orderby'        => 'menu_order date',
        'order'          => 'ASC',
    ) );

    if ( empty( $first ) ) return null;

    // Retourner la version watermarkée si le watermark est actif pour cette galerie
    if ( class_exists( 'PhotoProof_Watermark' ) && PhotoProof_Watermark::is_watermark_active_for_gallery( $post_id ) ) {
        $wm_url = get_post_meta( $first[0]->ID, '_photoproof_watermarked_url', true );
        if ( $wm_url ) return $wm_url;
    }

    return wp_get_attachment_image_url( $first[0]->ID, $size );
}

/**
 * Retourne les IDs des photos sélectionnées pour une galerie
 *
 * @param int $post_id
 * @return array
 */
function photoproof_get_gallery_selection( $post_id ) {
    $selected = get_post_meta( $post_id, '_photoproof_selected_photos', true );
    return is_array( $selected ) ? array_map( 'intval', $selected ) : array();
}

/**
 * Retourne true si la galerie est verrouillée (valide ou fermée)
 *
 * @param int $post_id
 * @return bool
 */
function photoproof_is_gallery_locked( $post_id ) {
    return in_array( photoproof_get_gallery_status( $post_id ), array( 'valide', 'ferme' ), true );
}