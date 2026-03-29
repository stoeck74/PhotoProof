<?php
/**
 * Template front-end de la galerie PhotoProof
 *
 * CORRECTIONS :
 * - Vérification du statut (brouillon, ferme) en début de template
 * - XSS corrigés (esc_html, esc_attr, esc_url sur toutes les sorties)
 * - Bouton de sélection client sur chaque photo
 * - Compteur de sélection live
 * - Styles inline déplacés vers photoproof-public.css (inline minimal ici)
 * - Note watermark : les images sont servies sans watermark pour l'instant
 *   (feature à implémenter via endpoint PHP ou pre-génération à l'upload)
 */


// Pas de get_header() — template autonome
// Le plugin ne dépend pas du thème installé
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php the_title(); ?> — <?php bloginfo('name'); ?></title>
    <?php wp_head(); // charge quand même les scripts WP dont jQuery et nos assets ?>
</head>
<body <?php body_class('pp-standalone'); ?>>
<?php wp_body_open(); 


global $post, $wpdb;

// ── 1. VÉRIFICATION D'ACCÈS ───────────────────────────────────────────────
$row = $wpdb->get_row( $wpdb->prepare(
    "SELECT status FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
    $post->ID
) );

$is_admin_user = current_user_can( 'manage_options' );

if ( $row ) {
    if ( $row->status === 'brouillon' && ! $is_admin_user ) {
        wp_die(
            '<h1>Galerie non disponible</h1><p>Cette galerie n\'est pas encore publiée. Contactez votre photographe.</p>',
            'Accès refusé',
            array( 'response' => 403 )
        );
    }
    if ( $row->status === 'ferme' && ! $is_admin_user ) {
        wp_die(
            '<h1>Galerie archivée</h1><p>Cette galerie est fermée. Contactez votre photographe.</p>',
            'Galerie archivée',
            array( 'response' => 403 )
        );
    }
}

// ── 2. RÉCUPÉRATION DES RÉGLAGES ─────────────────────────────────────────
$reco_enabled   = get_option( 'pp_enable_recommendations' );
$reco_icon_type = get_option( 'pp_global_recommendation_icon', 'star' );
$icons          = array( 'dot' => '●', 'star' => '★', 'heart' => '❤' );
$icon           = isset( $icons[ $reco_icon_type ] ) ? $icons[ $reco_icon_type ] : '★';

// Sélection existante (pour restaurer l'état au chargement)
$selected_photos = get_post_meta( $post->ID, '_pp_selected_photos', true );
$selected_ids    = is_array( $selected_photos ) ? array_map( 'intval', $selected_photos ) : array();
$count_selected  = count( $selected_ids );

// ── 3. PHOTOS DE LA GALERIE ───────────────────────────────────────────────
$query_images = new WP_Query( array(
    'post_type'      => 'attachment',
    'post_mime_type' => 'image',
    'post_status'    => 'inherit',
    'posts_per_page' => -1,
    'post_parent'    => $post->ID,
    'orderby'        => 'menu_order',
    'order'          => 'ASC',
) );
?>

<div class="pp-gallery-wrapper">

    <!-- ── EN-TÊTE ── -->
    <header class="pp-gallery-header">
        <div class="pp-logo-container">
            <?php
            $custom_logo_id = get_option( 'pp_custom_logo' );
            if ( $custom_logo_id ) {
                echo wp_get_attachment_image(
                    $custom_logo_id,
                    'medium',
                    false,
                    array( 'class' => 'pp-custom-logo' )
                );
            } else {
                // CORRECTION : esc_html sur la sortie
                echo '<span class="pp-site-title">'
                    . esc_html( get_option( 'pp_custom_title', get_bloginfo( 'name' ) ) )
                    . '</span>';
            }
            ?>
        </div>

        <h1 class="pp-gallery-title"><?php the_title(); ?></h1>

        <?php if ( has_excerpt() || get_the_content() ) : ?>
        <div class="pp-gallery-description">
            <?php the_content(); ?>
        </div>
        <?php endif; ?>
    </header>

    <!-- ── BARRE DE SÉLECTION (sticky) ── -->
    <div class="pp-selection-bar" id="pp-selection-bar">
        <div class="pp-selection-bar-inner">
            <span class="pp-selection-info">
                <strong id="pp-count-display"><?php echo intval( $count_selected ); ?></strong>
                photo(s) sélectionnée(s)
            </span>
            <button type="button" class="pp-btn-validate" id="pp-btn-validate"
                <?php echo $count_selected === 0 ? 'disabled' : ''; ?>>
                ✓ Valider ma sélection
            </button>
            <span class="pp-save-status" id="pp-save-status" style="display:none;"></span>
        </div>
    </div>

    <!-- ── GRILLE PHOTOS ── -->
    <div class="pp-grid" id="pp-photo-grid">
        <?php
        if ( $query_images->have_posts() ) :
            while ( $query_images->have_posts() ) :
                $query_images->the_post();
                $img_id     = get_the_ID();
                $is_reco    = get_post_meta( $img_id, '_pp_recommended', true );
                $is_selected = in_array( $img_id, $selected_ids, true );
                $img_title  = get_the_title();
                ?>
                <div class="pp-photo-item <?php echo $is_selected ? 'pp-selected' : ''; ?>"
                     data-id="<?php echo esc_attr( $img_id ); ?>">

                    <!-- Image -->
                    <?php
                    echo wp_get_attachment_image(
                        $img_id,
                        'large',
                        false,
                        array(
                            'class'   => 'pp-photo-img',
                            'loading' => 'lazy',
                            'alt'     => esc_attr( $img_title ),
                        )
                    );
                    ?>

                    <!-- Badge recommandation photographe -->
                    <?php if ( $reco_enabled && $is_reco ) : ?>
                        <div class="pp-reco-badge" title="Recommandée par le photographe">
                            <?php echo esc_html( $icon ); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Overlay de sélection -->
                    <div class="pp-photo-overlay">
                        <button type="button"
                            class="pp-select-btn"
                            data-id="<?php echo esc_attr( $img_id ); ?>"
                            aria-label="<?php echo $is_selected ? 'Désélectionner' : 'Sélectionner'; ?> cette photo"
                            aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>">
                            <span class="pp-select-check">✓</span>
                        </button>
                    </div>

                    <!-- Titre -->
                    <div class="pp-photo-footer">
                        <?php echo esc_html( $img_title ); ?>
                    </div>

                </div>
            <?php
            endwhile;
            wp_reset_postdata();
        else :
            ?>
            <div class="pp-empty-state">
                <p>Aucune photo dans cette galerie pour le moment.</p>
            </div>
        <?php endif; ?>
    </div><!-- /.pp-grid -->

    <!-- ── CONFIRMATION (modal légère) ── -->
    <div class="pp-confirm-overlay" id="pp-confirm-overlay" style="display:none;" aria-modal="true" role="dialog">
        <div class="pp-confirm-box">
            <h2>Confirmer votre sélection ?</h2>
            <p>Vous avez sélectionné <strong id="pp-confirm-count"><?php echo intval( $count_selected ); ?></strong> photo(s).
            Cette action est modifiable tant que la galerie est ouverte.</p>
            <div class="pp-confirm-actions">
                <button type="button" class="pp-btn-cancel" id="pp-btn-cancel">Annuler</button>
                <button type="button" class="pp-btn-confirm" id="pp-btn-confirm">Confirmer</button>
            </div>
        </div>
    </div>

</div><!-- /.pp-gallery-wrapper -->

<?php wp_footer(); ?>
</body>
</html>