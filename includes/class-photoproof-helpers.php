<?php
/**
 * PhotoProof Helpers — Template Tags & Shortcode
 *
 * TEMPLATE TAGS (pour développeurs) :
 * - pp_get_client_galleries( $user_id )     → array de données galeries
 * - pp_get_gallery_status( $post_id )       → string statut
 * - pp_get_gallery_photo_count( $post_id )  → int nombre de photos
 * - pp_get_gallery_thumbnail( $post_id )    → string URL thumbnail
 * - pp_get_gallery_selection( $post_id )    → array IDs photos sélectionnées
 * - pp_is_gallery_locked( $post_id )        → bool
 *
 * SHORTCODE :
 * - [pp_galleries_client] → liste des galeries du client connecté
 */
class PhotoProof_Helpers {

    public function __construct() {
        add_shortcode( 'pp_galleries_client', array( $this, 'shortcode_galleries_client' ) );
    }

    // ══════════════════════════════════════════════════════════════════
    // SHORTCODE
    // ══════════════════════════════════════════════════════════════════

    /**
     * Shortcode [pp_galleries_client]
     * Affiche la liste des galeries du client connecté
     *
     * Attributs :
     * - columns     : nombre de colonnes (défaut: 1)
     * - show_status : afficher le statut (défaut: true)
     * - show_count  : afficher le nombre de photos (défaut: true)
     * - show_date   : afficher la date (défaut: true)
     *
     * Exemple : [pp_galleries_client columns="2" show_date="false"]
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
        ), $atts, 'pp_galleries_client' );

        $user_id  = get_current_user_id();
        $galleries = pp_get_client_galleries( $user_id );

        if ( empty( $galleries ) ) {
            return '<p class="pp-sc-notice">Aucune galerie disponible pour le moment.</p>';
        }

        $cols        = max( 1, intval( $atts['columns'] ) );
        $show_status = $atts['show_status'] !== 'false';
        $show_count  = $atts['show_count']  !== 'false';
        $show_date   = $atts['show_date']   !== 'false';

        ob_start();
        ?>
        <style>
        .pp-sc-grid { display: grid; grid-template-columns: repeat(<?php echo $cols; ?>, 1fr); gap: 16px; }
        .pp-sc-card { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; text-decoration: none; color: inherit; display: flex; flex-direction: column; transition: box-shadow .2s; }
        .pp-sc-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.1); }
        .pp-sc-thumb { width: 100%; aspect-ratio: 3/2; object-fit: cover; display: block; background: #f1f5f9; }
        .pp-sc-body { padding: 14px 16px; display: flex; flex-direction: column; gap: 6px; }
        .pp-sc-title { font-size: 15px; font-weight: 600; margin: 0; }
        .pp-sc-meta { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .pp-sc-status { font-size: 11px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; display: flex; align-items: center; gap: 6px; }
        .pp-sc-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
        .pp-sc-dot-open { background: #f97316; }
        .pp-sc-dot-validated { background: #22c55e; }
        .pp-sc-info { font-size: 11px; color: #94a3b8; }
        .pp-sc-notice { font-size: 14px; color: #64748b; padding: 12px 0; }
        @media (max-width: 600px) { .pp-sc-grid { grid-template-columns: 1fr !important; } }
        </style>

        <div class="pp-sc-grid">
        <?php foreach ( $galleries as $g ) :
            $status_label = $g['status'] === 'valide' ? 'Validé' : 'Ouvert';
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
                                <span class="pp-sc-dot <?php echo $dot_class; ?>"></span>
                                <?php echo $status_label; ?>
                            </span>
                        <?php endif; ?>
                        <?php if ( $show_count ) : ?>
                            <span class="pp-sc-info"><?php echo intval( $g['photo_count'] ); ?> photo<?php echo $g['photo_count'] > 1 ? 's' : ''; ?></span>
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
function pp_get_client_galleries( $user_id ) {
    global $wpdb;

    $post_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->prefix}photoproof_galleries
         WHERE client_id = %d
         AND status IN ('publie', 'valide')",
        $user_id
    ) );

    if ( empty( $post_ids ) ) return array();

    $posts = get_posts( array(
        'post_type'      => 'pp_gallery',
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
            'status'        => pp_get_gallery_status( $post->ID ),
            'photo_count'   => pp_get_gallery_photo_count( $post->ID ),
            'thumbnail_url' => pp_get_gallery_thumbnail( $post->ID ),
            'date'          => get_the_date( 'd.m.Y', $post->ID ),
            'selected_ids'  => pp_get_gallery_selection( $post->ID ),
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
function pp_get_gallery_status( $post_id ) {
    global $wpdb;
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT status FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
        $post_id
    ) );
    return $row ? $row->status : 'brouillon';
}

/**
 * Retourne le nombre de photos d'une galerie
 *
 * @param int $post_id
 * @return int
 */
function pp_get_gallery_photo_count( $post_id ) {
    global $wpdb;
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts}
         WHERE post_type = 'attachment'
         AND post_status = 'inherit'
         AND post_parent = %d",
        $post_id
    ) );
}

/**
 * Retourne l'URL du thumbnail d'une galerie (première photo)
 *
 * @param int    $post_id
 * @param string $size     Taille WP (défaut: medium)
 * @return string|null
 */
function pp_get_gallery_thumbnail( $post_id, $size = 'medium' ) {
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

    // Retourner la version watermarkée si elle existe
    if ( function_exists( 'PhotoProof_Watermark::get_watermarked_url' ) || class_exists( 'PhotoProof_Watermark' ) ) {
        $wm_url = get_post_meta( $first[0]->ID, '_pp_watermarked_url', true );
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
function pp_get_gallery_selection( $post_id ) {
    $selected = get_post_meta( $post_id, '_pp_selected_photos', true );
    return is_array( $selected ) ? array_map( 'intval', $selected ) : array();
}

/**
 * Retourne true si la galerie est verrouillée (valide ou fermée)
 *
 * @param int $post_id
 * @return bool
 */
function pp_is_gallery_locked( $post_id ) {
    return in_array( pp_get_gallery_status( $post_id ), array( 'valide', 'ferme' ), true );
}