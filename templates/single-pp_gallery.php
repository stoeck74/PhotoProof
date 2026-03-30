<?php
/**
 * Template standalone de la galerie PhotoProof
 * Masonry.js + ImagesLoaded — ratio réel préservé, zéro crop
 */

global $post, $wpdb;

// ── 1. VÉRIFICATION D'ACCÈS ───────────────────────────────────────────────
$row = $wpdb->get_row( $wpdb->prepare(
    "SELECT status FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
    $post->ID
) );

$is_admin_user = current_user_can( 'manage_options' );

if ( $row ) {
    if ( $row->status === 'brouillon' && ! $is_admin_user ) {
        wp_die( '<h1>Galerie non disponible</h1><p>Cette galerie n\'est pas encore publiée.</p>', 'Accès refusé', array( 'response' => 403 ) );
    }
    if ( $row->status === 'ferme' && ! $is_admin_user ) {
        wp_die( '<h1>Galerie archivée</h1><p>Contactez votre photographe.</p>', 'Galerie archivée', array( 'response' => 403 ) );
    }
}

// ── 2. RÉGLAGES ───────────────────────────────────────────────────────────
$reco_enabled   = get_option( 'pp_enable_recommendations' );
$reco_icon_type = get_option( 'pp_global_recommendation_icon', 'star' );
$icons          = array( 'dot' => '●', 'star' => '★', 'heart' => '❤' );
$icon           = isset( $icons[ $reco_icon_type ] ) ? $icons[ $reco_icon_type ] : '★';

$selected_photos = get_post_meta( $post->ID, '_pp_selected_photos', true );
$selected_ids    = is_array( $selected_photos ) ? array_map( 'intval', $selected_photos ) : array();
$count_selected  = count( $selected_ids );

$custom_logo_id = get_option( 'pp_custom_logo' );
$site_title     = get_option( 'pp_custom_title', get_bloginfo( 'name' ) );

// ── 3. PHOTOS ─────────────────────────────────────────────────────────────
$query_images = new WP_Query( array(
    'post_type'      => 'attachment',
    'post_mime_type' => 'image',
    'post_status'    => 'inherit',
    'posts_per_page' => -1,
    'post_parent'    => $post->ID,
    'orderby'        => 'menu_order date',
    'order'          => 'ASC',
) );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php the_title(); ?> — <?php echo esc_html( $site_title ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="pp-standalone">
<?php wp_body_open(); ?>

<div class="pp-page" id="pp-page">

    <!-- ── HEADER ── -->
    <header class="pp-header">
        <div class="pp-header-left">
            <div class="pp-logo-wrap">
                <?php if ( $custom_logo_id ) : ?>
                    <?php echo wp_get_attachment_image( $custom_logo_id, 'medium', false, array( 'class' => 'pp-logo-img' ) ); ?>
                <?php else : ?>
                    <span class="pp-site-name"><?php echo esc_html( $site_title ); ?></span>
                <?php endif; ?>
            </div>
            <div class="pp-header-meta">
                <h1 class="pp-gallery-title"><?php the_title(); ?></h1>
            </div>
        </div>
        <div class="pp-header-right">
            <span><?php echo $query_images->found_posts; ?> photographie<?php echo $query_images->found_posts > 1 ? 's' : ''; ?></span>
        </div>
    </header>

    <!-- ── GRILLE MASONRY ── -->
    <?php if ( $query_images->have_posts() ) : ?>

        <?php if ( $row && $row->status === 'valide' ) : ?>
        <div id="pp-locked-banner" class="pp-locked-banner">
            <span class="pp-locked-icon">✓</span>
            Sélection confirmée — contactez votre photographe pour toute modification.
        </div>
        <?php endif; ?>

        <div class="pp-masonry-wrap">
            <div class="pp-masonry-grid" id="pp-masonry-grid">
                <div class="pp-gutter-sizer"></div>
                <?php while ( $query_images->have_posts() ) : $query_images->the_post();
                    $img_id      = get_the_ID();
                    $is_reco     = get_post_meta( $img_id, '_pp_recommended', true );
                    $is_selected = in_array( $img_id, $selected_ids, true );

                    // Dimensions pour classe orientation
                    $meta        = wp_get_attachment_metadata( $img_id );
                    $img_width   = $meta && isset( $meta['width'] )  ? intval( $meta['width'] )  : 3;
                    $img_height  = $meta && isset( $meta['height'] ) ? intval( $meta['height'] ) : 2;
                    $is_landscape = $img_width > $img_height;
                    $orient_class = $is_landscape ? 'pp-landscape' : 'pp-portrait';

                    // Servir 'large' (max ~1024px WP) plutôt que l'original
                    // pour éviter de charger des fichiers de plusieurs Mo
                    $img_src    = wp_get_attachment_image_url( $img_id, 'large' );
                    $img_srcset = wp_get_attachment_image_srcset( $img_id, 'large' );
                    $img_full   = wp_get_attachment_url( $img_id ); // original pour lightbox
                    $img_title  = get_the_title();
                    ?>
                    <div class="pp-photo-item grid-item <?php echo $orient_class; ?> <?php echo $is_selected ? 'pp-selected' : ''; ?>"
                         data-id="<?php echo esc_attr( $img_id ); ?>"
                         data-full="<?php echo esc_url( $img_full ); ?>">

                        <img
                            class="pp-photo-img"
                            src="<?php echo esc_url( $img_src ); ?>"
                            <?php if ( $img_srcset ) : ?>
                            srcset="<?php echo esc_attr( $img_srcset ); ?>"
                            sizes="<?php echo $is_landscape ? '40vw' : '20vw'; ?>"
                            <?php endif; ?>
                            alt="<?php echo esc_attr( $img_title ); ?>"
                            loading="lazy"
                            decoding="async">

                        <div class="pp-photo-overlay">
                            <button class="pp-select-btn" type="button"
                                data-id="<?php echo esc_attr( $img_id ); ?>"
                                aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>"
                                aria-label="Sélectionner cette photo">
                                <span class="pp-check-dot"></span>
                            </button>
                        </div>

                        <?php if ( $reco_enabled && $is_reco ) : ?>
                            <div class="pp-reco-badge"><?php echo esc_html( $icon ); ?></div>
                        <?php endif; ?>

                    </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        </div>

    <?php else : ?>
        <div class="pp-empty"><p>Aucune photo dans cette galerie pour le moment.</p></div>
    <?php endif; ?>

    <!-- ── BARRE DE SÉLECTION ── -->
    <div class="pp-selection-bar" id="pp-selection-bar">
        <div class="pp-bar-inner">
            <span class="pp-bar-info">
                <strong id="pp-count-display"><?php echo intval( $count_selected ); ?></strong> sélectionnée(s)
            </span>
            <div class="pp-bar-right">
                <span class="pp-save-status" id="pp-save-status"></span>
                <button type="button" class="pp-btn-validate" id="pp-btn-validate"
                    <?php echo $count_selected === 0 ? 'disabled' : ''; ?>>
                    Valider la sélection
                </button>
            </div>
        </div>
    </div>

    <!-- ── MODAL CONFIRMATION ── -->
    <div class="pp-confirm-overlay" id="pp-confirm-overlay" style="display:none;">
        <div class="pp-confirm-box">
            <div class="pp-confirm-eyebrow">Confirmation</div>
            <h2>Valider votre sélection ?</h2>
            <p>Vous avez retenu <strong id="pp-confirm-count"><?php echo intval( $count_selected ); ?></strong> photographie(s). Cette action est définitive de votre côté.</p>
            <div class="pp-confirm-actions">
                <button type="button" class="pp-btn-cancel" id="pp-btn-cancel">Annuler</button>
                <button type="button" class="pp-btn-confirm" id="pp-btn-confirm">Confirmer</button>
            </div>
        </div>
    </div>

    <!-- ── LIGHTBOX ── -->
    <div class="pp-lightbox" id="pp-lightbox" style="display:none;">
        <button class="pp-lb-close" id="pp-lb-close" aria-label="Fermer">×</button>
        <button class="pp-lb-prev" id="pp-lb-prev" aria-label="Précédent">‹</button>
        <button class="pp-lb-next" id="pp-lb-next" aria-label="Suivant">›</button>
        <div class="pp-lb-img-wrap">
            <img src="" alt="" id="pp-lb-img" class="pp-lb-img">
        </div>
        <div class="pp-lb-footer">
            <span id="pp-lb-counter"></span>
            <button type="button" class="pp-lb-select" id="pp-lb-select">Sélectionner</button>
        </div>
    </div>

</div>

<?php wp_footer(); ?>
</body>
</html>