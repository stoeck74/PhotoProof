<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * Template standalone de la galerie PhotoProof
 * Hero header avec première photo en fond + grille 5 colonnes
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template file, variables are locally scoped
global $post, $wpdb;

// ── 1. STATUT & BANNIÈRE ADMIN ───────────────────────────────────────────
// L'accès est déjà vérifié par PhotoProof_Expiration::check_gallery_expiration()
// sur template_redirect — ici on récupère juste le statut pour la logique du template
$row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    "SELECT status FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
    $post->ID
) );

$is_admin_user   = current_user_can( 'manage_options' );
$pp_admin_notice = PhotoProof_Expiration::get_admin_notice( $post->ID );

// ── 2. RÉGLAGES ───────────────────────────────────────────────────────────
$reco_enabled   = get_option( 'photoproof_enable_recommendations' );
$reco_icon_type = get_option( 'photoproof_global_recommendation_icon', 'star' );
$icons          = array( 'dot' => '●', 'star' => '★', 'heart' => '❤', 'square' => '◆' );
$icon           = isset( $icons[ $reco_icon_type ] ) ? $icons[ $reco_icon_type ] : '★';

$selected_photos = get_post_meta( $post->ID, '_photoproof_selected_photos', true );
$selected_ids    = is_array( $selected_photos ) ? array_map( 'intval', $selected_photos ) : array();
$count_selected  = count( $selected_ids );

$custom_logo_id = get_option( 'photoproof_custom_logo' );
$site_title     = get_option( 'photoproof_custom_title', get_bloginfo( 'name' ) );

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

<?php if ( $pp_admin_notice ) : ?>
<div id="pp-admin-banner" style="position:fixed; top:32px; left:0; right:0; z-index:99999; background:#ffb900; color:#1e1e1e; padding:10px 20px; text-align:center; font-size:13px; font-weight:500; border-bottom:2px solid #e09800;">
    <?php echo wp_kses( $pp_admin_notice, array( 'strong' => array() ) ); ?>
</div>
<?php endif; ?>

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
                <?php
                /* translators: %d: number of photographs in the gallery */
                printf( esc_html( _n( '%d photograph', '%d photographs', $query_images->found_posts, 'photoproof' ) ), absint( $query_images->found_posts ) ); ?>
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
        <?php esc_html_e( 'Selection confirmed — please contact your photographer for any modification.', 'photoproof' ); ?>

    </div>
    <?php endif; ?>

    <!-- ── GRILLE ── -->
    <?php if ( $query_images->have_posts() ) : ?>
    <?php
        // Watermark actif pour CETTE galerie ? (lu une seule fois avant la boucle)
        $watermark_active = PhotoProof_Watermark::is_watermark_active_for_gallery( $post->ID );
    ?>
    <div class="pp-grid" id="pp-grid">
        <?php while ( $query_images->have_posts() ) : $query_images->the_post();
            $img_id      = get_the_ID();
            $is_reco     = get_post_meta( $img_id, '_photoproof_recommended', true );
            $is_selected = in_array( $img_id, $selected_ids, true );

            $img_src       = $watermark_active
                ? PhotoProof_Watermark::get_watermarked_url( $img_id )
                : wp_get_attachment_image_url( $img_id, 'large' );
            $img_srcset    = $watermark_active ? '' : wp_get_attachment_image_srcset( $img_id, 'large' );
            $img_full      = $watermark_active
                ? PhotoProof_Watermark::get_watermarked_url( $img_id )
                : wp_get_attachment_url( $img_id );
            $img_title     = get_the_title();
            $filename      = get_post_meta( $img_id, '_photoproof_target_filename', true ) ?: basename( get_attached_file( $img_id ) );
            ?>
            <div class="pp-card <?php echo esc_attr( $is_selected ? 'pp-selected' : '' ); ?>"
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
                        aria-pressed="<?php echo esc_attr( $is_selected ? 'true' : 'false' ); ?>"
                        aria-label="<?php esc_attr_e( 'Select this photo', 'photoproof' ); ?>">
                        <span class="pp-check-dot"></span>
                    </button>
                </div>

            </div>
        <?php endwhile; wp_reset_postdata(); ?>
    </div>
    <?php else : ?>
        <div class="pp-empty"><p><?php esc_html_e( 'No photos in the gallery yet.', 'photoproof' ); ?></p></div>
    <?php endif; ?>

    <!-- ── BARRE DE SÉLECTION ── -->
    <div class="pp-selection-bar" id="pp-selection-bar">
        <div class="pp-bar-inner">
            <span class="pp-bar-info">
                <strong id="pp-count-display"><?php echo intval( $count_selected ); ?></strong> <?php esc_html_e( 'selected', 'photoproof' ); ?>
            </span>
            <div class="pp-bar-right">
                <span class="pp-save-status" id="pp-save-status"></span>
                <button type="button" class="pp-btn-validate" id="pp-btn-validate"
                    data-confirmed="<?php esc_attr_e( 'Selection confirmed', 'photoproof' ); ?>"
                    <?php echo $count_selected === 0 ? 'disabled' : ''; ?>>
                    <?php esc_html_e( 'Validate selection', 'photoproof' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ── MODAL CONFIRMATION ── -->
<div class="pp-confirm-overlay" id="pp-confirm-overlay" style="display:none;">
    <div class="pp-confirm-box">
        <div class="pp-confirm-eyebrow"><?php esc_html_e( 'Confirmation', 'photoproof' ); ?></div>
        <h2><?php esc_html_e( 'Validate your selection?', 'photoproof' ); ?></h2>
        <p><?php
        /* translators: %s: number of photographs, wrapped in a <strong> tag */
        echo wp_kses_post( sprintf( __( 'You have selected %s photograph(s). This action is final on your end.', 'photoproof' ), '<strong id="pp-confirm-count">' . intval( $count_selected ) . '</strong>' ) ); ?></p>
        <div class="pp-confirm-actions">
            <button type="button" class="pp-btn-cancel" id="pp-btn-cancel"><?php esc_html_e( 'Cancel', 'photoproof' ); ?></button>
            <button type="button" class="pp-btn-confirm" id="pp-btn-confirm"><?php esc_html_e( 'Confirm', 'photoproof' ); ?></button>
        </div>
    </div>
</div>

    <!-- ── RÉCAP FALLBACK (sans animations) ── -->
<div class="pp-recap-view" id="pp-recap-view" style="display:none;">
    <div class="pp-recap-header">
        <button class="pp-recap-back" id="pp-recap-back" type="button">
            <?php esc_html_e( '← Back to edit', 'photoproof' ); ?>
        </button>
        
        <div class="pp-recap-title-wrap">
            <p class="pp-recap-eyebrow">
                <?php esc_html_e( 'Summary', 'photoproof' ); ?>
            </p>
            <h2 class="pp-recap-title">
                <?php
                /* translators: %s: number of selected photos, wrapped in a <span> tag */
                echo wp_kses_post( sprintf( __( 'Your selection — %s photos', 'photoproof' ), '<span id="pp-recap-count">0</span>' ) ); ?>
            </h2>
        </div>

        <button type="button" class="pp-btn-recap-confirm-top" id="pp-recap-confirm">
            <?php esc_html_e( 'Confirm selection', 'photoproof' ); ?>
        </button>
    </div>
    
    <div class="pp-recap-grid" id="pp-recap-grid"></div>
</div>

    <!-- ── RÉCAP ANIMÉ — éléments statiques clonés par le JS ── -->
    <div id="pp-recap-anim-header" style="display:none;">
        <p class="pp-recap-eyebrow"><?php esc_html_e( 'Summary', 'photoproof' ); ?></p>
    </div>
    <div id="pp-recap-anim-footer" style="display:none;">
        <button class="pp-btn-recap-back" id="pp-recap-anim-back" type="button"><?php esc_html_e( '← Back to edit', 'photoproof' ); ?></button>
        <button class="pp-btn-recap-confirm" id="pp-recap-anim-confirm" type="button"><?php esc_html_e( 'Confirm selection', 'photoproof' ); ?></button>
    </div>
        <!-- ── MODAL FIN ── -->
    <div class="pp-end-overlay" id="pp-end-overlay" style="display:none;">
        <div class="pp-end-box">
            <div class="pp-end-icon">✓</div>
            <h2 class="pp-end-title"><?php esc_html_e( 'Selection confirmed', 'photoproof' ); ?></h2>
            <p class="pp-end-message"><?php esc_html_e( 'Your photographer has been notified. Contact me for any changes in your choice.', 'photoproof' ); ?></p>
            <div class="pp-end-actions">
                <a href="#" class="pp-end-link" id="pp-end-back"><?php esc_html_e( 'Back to gallery', 'photoproof' ); ?></a>
                <a href="<?php echo esc_url( home_url() ); ?>" class="pp-end-link"><?php esc_html_e( 'Home', 'photoproof' ); ?></a>
                <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="pp-end-link pp-end-logout"><?php esc_html_e( 'Log out', 'photoproof' ); ?></a>
            </div>
        </div>
    </div>

    <!-- ── LIGHTBOX ── -->
    <div class="pp-lightbox" id="pp-lightbox" style="display:none;">
        <button class="pp-lb-close" id="pp-lb-close" aria-label="<?php esc_attr_e( 'Close', 'photoproof' ); ?>">×</button>
        <button class="pp-lb-prev" id="pp-lb-prev" aria-label="<?php esc_attr_e( 'Previous', 'photoproof' ); ?>">‹</button>
        <button class="pp-lb-next" id="pp-lb-next" aria-label="<?php esc_attr_e( 'Next', 'photoproof' ); ?>">›</button>
        <div class="pp-lb-img-wrap">
            <img src="" alt="" id="pp-lb-img" class="pp-lb-img">
        </div>
        <div class="pp-lb-footer">
            <span id="pp-lb-counter"></span>
            <button type="button" class="pp-lb-select" id="pp-lb-select"><?php esc_html_e( 'Select', 'photoproof' ); ?></button>
        </div>
    </div>

</div><!-- /#pp-page -->

<?php wp_footer(); ?>
</body>
</html>