<?php
/**
 * Template standalone de la galerie PhotoProof
 * Hero header avec première photo en fond + grille 5 colonnes
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
$icons          = array( 'dot' => '●', 'star' => '★', 'heart' => '❤', 'square' => '◆' );
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

// ── 4. HERO — post thumbnail ──────────────────────────────────────────────
$hero_url = '';
if ( has_post_thumbnail( $post->ID ) ) {
    $hero_url = get_the_post_thumbnail_url( $post->ID, 'full' );
}
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

    <!-- ── HERO HEADER ── -->
    <header class="pp-hero" <?php if ( $hero_url ) : ?>style="--pp-hero-img: url('<?php echo esc_url( $hero_url ); ?>');"<?php endif; ?>>

        <?php if ( $hero_url ) : ?>
            <div class="pp-hero-bg"></div>
        <?php endif; ?>

        <div class="pp-hero-gradient"></div>

        <div class="pp-hero-content">
            <div class="pp-hero-left">
                <?php if ( $custom_logo_id || $site_title ) : ?>
                <div class="pp-logo-wrap">
                    <a href="<?php echo esc_url( get_home_url() ); ?>">
                        <?php if ( $custom_logo_id ) : ?>
                            <?php echo wp_get_attachment_image( $custom_logo_id, 'medium', false, array( 'class' => 'pp-logo-img' ) ); ?>
                        <?php endif; ?>
                    </a>
                </div>
                <?php endif; ?>

                <div class="pp-hero-text">
                    <?php if ( $site_title ) : ?>
                        <span class="pp-site-name"><?php echo esc_html( $site_title ); ?></span>
                    <?php endif; ?>
                    <h1 class="pp-gallery-title"><?php the_title(); ?></h1>
                </div>
            </div>

            <div class="pp-hero-right">
                <?php echo $query_images->found_posts; ?> photographie<?php echo $query_images->found_posts > 1 ? 's' : ''; ?>
            </div>
        </div>
    </header>

    <?php if ( get_the_content() ) : ?>
    <div class="pp-content-section">
        <p><?php the_content(); ?></p>
    </div>
    <?php endif; ?>

    <?php if ( $row && $row->status === 'valide' ) : ?>
    <div id="pp-locked-banner" class="pp-locked-banner">
        <span class="pp-locked-icon">✓</span>
        Sélection confirmée — contactez votre photographe pour toute modification.
    </div>
    <?php endif; ?>

    <!-- ── GRILLE ── -->
    <?php if ( $query_images->have_posts() ) : ?>
    <div class="pp-grid" id="pp-grid">
        <?php while ( $query_images->have_posts() ) : $query_images->the_post();
            $img_id      = get_the_ID();
            $is_reco     = get_post_meta( $img_id, '_pp_recommended', true );
            $is_selected = in_array( $img_id, $selected_ids, true );

            $has_watermark = get_option( 'pp_global_watermark' );
            $img_src       = $has_watermark
                ? PhotoProof_Watermark::get_watermarked_url( $img_id )
                : wp_get_attachment_image_url( $img_id, 'large' );
            $img_srcset    = $has_watermark ? '' : wp_get_attachment_image_srcset( $img_id, 'large' );
            $img_full      = $has_watermark
                ? PhotoProof_Watermark::get_watermarked_url( $img_id )
                : wp_get_attachment_url( $img_id );
            $img_title     = get_the_title();
            $filename      = get_post_meta( $img_id, '_pp_target_filename', true ) ?: basename( get_attached_file( $img_id ) );
            ?>
            <div class="pp-card <?php echo $is_selected ? 'pp-selected' : ''; ?>"
                 data-id="<?php echo esc_attr( $img_id ); ?>">

                <div class="pp-card-img-wrap"
                     data-full="<?php echo esc_url( $img_full ); ?>">
                    <img
                        class="pp-card-img"
                        src="<?php echo esc_url( $img_src ); ?>"
                        <?php if ( $img_srcset ) : ?>
                        srcset="<?php echo esc_attr( $img_srcset ); ?>"
                        sizes="20vw"
                        <?php endif; ?>
                        alt="<?php echo esc_attr( $img_title ); ?>"
                        loading="lazy"
                        decoding="async">

                    <?php if ( $reco_enabled && $is_reco ) : ?>
                        <div class="pp-reco-badge"><?php echo esc_html( $icon ); ?></div>
                    <?php endif; ?>
                </div>

                <div class="pp-card-footer">
                    <span class="pp-card-name"><?php echo esc_html( $filename ); ?></span>
                    <button class="pp-select-btn" type="button"
                        data-id="<?php echo esc_attr( $img_id ); ?>"
                        aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>"
                        aria-label="Sélectionner cette photo">
                        <span class="pp-check-dot"></span>
                    </button>
                </div>

            </div>
        <?php endwhile; wp_reset_postdata(); ?>
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

</div><!-- /#pp-page -->

<?php wp_footer(); ?>
</body>
</html>