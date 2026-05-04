<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gestion des réglages globaux PhotoProof.
 *
 * Le rendu HTML utilise le système "cards" introduit avec la refonte UI :
 *  - chaque option (ou groupe d'options liées) est rendue dans une .pp-card
 *  - le toggle +/- (sceau au-dessus de la carte) reflète l'état active/inactive
 *  - les cards "always-active" affichent une icône ∞ à la place du toggle
 *
 * Toutes les options et sanitize_callback sont inchangés par rapport à la
 * version précédente : seul le markup de la page change.
 */
class PhotoProof_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=photoproof_gallery',
            esc_html__( 'Settings PhotoProof', 'photoproof' ),
            esc_html__( 'Settings', 'photoproof' ),
            'manage_options',
            'photoproof-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {

        $bool_sanitize = function( $value ) {
            return $value ? 1 : 0;
        };

        // Booléens
        register_setting( 'photoproof_settings_group', 'photoproof_use_random_urls',       array( 'sanitize_callback' => $bool_sanitize ) );
        register_setting( 'photoproof_settings_group', 'photoproof_enable_expiration',      array( 'sanitize_callback' => $bool_sanitize ) );
        register_setting( 'photoproof_settings_group', 'photoproof_enable_rename',          array( 'sanitize_callback' => $bool_sanitize ) );
        register_setting( 'photoproof_settings_group', 'photoproof_enable_recommendations', array( 'sanitize_callback' => $bool_sanitize ) );
        register_setting( 'photoproof_settings_group', 'photoproof_photo_rounded',          array( 'sanitize_callback' => $bool_sanitize ) );
        register_setting( 'photoproof_settings_group', 'photoproof_delete_files_on_delete', array( 'sanitize_callback' => $bool_sanitize ) );
        register_setting( 'photoproof_settings_group', 'photoproof_enable_comments',        array( 'sanitize_callback' => $bool_sanitize ) );

        // ── Layout (grid / masonry) ──
        register_setting( 'photoproof_settings_group', 'photoproof_gallery_layout', array(
            'sanitize_callback' => array( $this, 'sanitize_layout' ),
            'default'           => 'grid',
        ) );

        // Texte libre
        register_setting( 'photoproof_settings_group', 'photoproof_rename_pattern', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'photoproof_settings_group', 'photoproof_custom_title',   array( 'sanitize_callback' => 'sanitize_text_field' ) );

        // Select — whitelist
        register_setting( 'photoproof_settings_group', 'photoproof_global_recommendation_icon', array(
            'sanitize_callback' => function( $value ) {
                $allowed = array( 'dot', 'star', 'square', 'heart' );
                return in_array( $value, $allowed, true ) ? $value : 'star';
            }
        ) );

        // Entier borné (opacité)
        register_setting( 'photoproof_settings_group', 'photoproof_watermark_opacity', array(
            'sanitize_callback' => function( $value ) {
                $int = absint( $value );
                return ( $int >= 10 && $int <= 100 ) ? $int : 50;
            }
        ) );

        // IDs d'attachements
        $attachment_sanitize = function( $value ) {
            $int = absint( $value );
            return $int > 0 ? $int : '';
        };

        register_setting( 'photoproof_settings_group', 'photoproof_custom_logo',      array( 'sanitize_callback' => $attachment_sanitize ) );
        register_setting( 'photoproof_settings_group', 'photoproof_global_watermark', array( 'sanitize_callback' => $attachment_sanitize ) );

        // Couleurs
        register_setting( 'photoproof_settings_group', 'photoproof_color_bg',     array( 'sanitize_callback' => 'sanitize_hex_color' ) );
        register_setting( 'photoproof_settings_group', 'photoproof_color_active', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
        register_setting( 'photoproof_settings_group', 'photoproof_color_text',   array( 'sanitize_callback' => 'sanitize_hex_color' ) );

        // URL
        register_setting( 'photoproof_settings_group', 'photoproof_login_url', array( 'sanitize_callback' => 'esc_url_raw' ) );

        // Emails
        register_setting( 'photoproof_settings_group', 'photoproof_email_photographer_subject', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'photoproof_settings_group', 'photoproof_email_photographer_body',    array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
        register_setting( 'photoproof_settings_group', 'photoproof_email_client_subject',       array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'photoproof_settings_group', 'photoproof_email_client_body',          array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
    }

    /**
     * Sanitize callback pour le layout : accepte uniquement 'grid' ou 'masonry'.
     */
    public function sanitize_layout( $value ) {
        $allowed = array( 'grid', 'masonry' );
        return in_array( $value, $allowed, true ) ? $value : 'grid';
    }

    public function render_settings_page() {

        // Pré-calcul des états "active" pour le rendu visuel
        $is_watermark_active = ! empty( get_option( 'photoproof_global_watermark' ) );

        ?>
        <div class="wrap pp-settings-page pp-loading">

            <h1 class="pp-settings-title">
                <?php esc_html_e( 'PhotoProof Configuration', 'photoproof' ); ?>
            </h1>

            <?php
            // Mini-icons inline (echoed as-is, content is fully static — no user input)
            $icon_plus     = '<svg class="pp-intro-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>';
            $icon_minus    = '<svg class="pp-intro-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>';
            $icon_infinity = '<svg class="pp-intro-icon pp-intro-icon-infinity" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>';

            // The translatable string uses %1$s, %2$s, %3$s placeholders that translators
            // can move around freely (different word orders across languages).
            $intro_template = __(
                'Click %1$s on a card to enable a feature, %2$s to disable it. Cards marked with %3$s are always active and cannot be turned off.',
                'photoproof'
            );
            ?>
            <p class="pp-settings-intro">
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVGs are static
                echo sprintf( $intro_template, $icon_plus, $icon_minus, $icon_infinity );
                ?>
            </p>

            <form method="post" action="options.php" class="pp-settings-form" id="pp-settings-form">
                <?php settings_fields( 'photoproof_settings_group' ); ?>

                <!-- ════════════════════════════════════════════════════════
                     TABS HORIZONTAUX
                ════════════════════════════════════════════════════════ -->
                <nav class="pp-tabs" role="tablist">
                    <button type="button" class="pp-tab is-active" data-target="general" role="tab">
                        <svg class="pp-tab-icon" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                        </svg>
                        <?php esc_html_e( 'General', 'photoproof' ); ?>
                    </button>

                    <button type="button" class="pp-tab" data-target="securite" role="tab">
                        <svg class="pp-tab-icon" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        <?php esc_html_e( 'Security & Watermark', 'photoproof' ); ?>
                    </button>

                    <button type="button" class="pp-tab" data-target="design" role="tab">
                        <svg class="pp-tab-icon" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="13.5" cy="6.5"  r="1.5"/>
                            <circle cx="17.5" cy="10.5" r="1.5"/>
                            <circle cx="8.5"  cy="7.5"  r="1.5"/>
                            <circle cx="6.5"  cy="12.5" r="1.5"/>
                            <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c1.66 0 3-1.34 3-3 0-.78-.29-1.48-.78-2.02-.46-.51-.74-1.2-.74-1.96 0-1.66 1.34-3 3-3h1.5c2.76 0 5-2.24 5-5C22 5.86 17.5 2 12 2z"/>
                        </svg>
                        <?php esc_html_e( 'Theme Design', 'photoproof' ); ?>
                    </button>

                    <button type="button" class="pp-tab" data-target="emails" role="tab">
                        <svg class="pp-tab-icon" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <?php esc_html_e( 'Emails', 'photoproof' ); ?>
                    </button>
                </nav>

                <!-- ════════════════════════════════════════════════════════
                     TAB 1 — GENERAL
                ════════════════════════════════════════════════════════ -->
                <div id="section-general" class="pp-tab-panel is-active" role="tabpanel">
                    <div class="pp-cards-grid">

                        <!-- Random URLs (UUID) -->
                        <div class="pp-card-wrap">
                            <h3 class="pp-card-heading"><?php esc_html_e( 'Random URLs (UUID)', 'photoproof' ); ?></h3>
                            <span class="pp-card-bar" aria-hidden="true"></span>
                            <div class="pp-card<?php echo get_option( 'photoproof_use_random_urls' ) ? ' is-active' : ''; ?>" data-toggle-target="photoproof_use_random_urls">
                            <svg class="pp-card-stroke" preserveAspectRatio="none"><rect x="1" y="1" width="calc(100% - 2px)" height="calc(100% - 2px)"></rect></svg>

                            <button type="button" class="pp-card-toggle" aria-label="Toggle">
                                <svg class="icon-plus"     viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                <svg class="icon-minus"    viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
                                <svg class="icon-infinity" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                            </button>

                            <input type="hidden" name="photoproof_use_random_urls" value="<?php echo get_option( 'photoproof_use_random_urls' ) ? '1' : '0'; ?>">

                            <div class="pp-card-header">
                                <span class="pp-card-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </span>
                                <span class="pp-card-title"><?php esc_html_e( 'Random URLs (UUID)', 'photoproof' ); ?></span>
                            </div>

                            <p class="pp-card-desc">
                                <?php esc_html_e( 'Hide gallery names in client URLs. Transforms readable links into impossible-to-guess codes.', 'photoproof' ); ?>
                            </p>

                            <div class="pp-card-content">
                                <p class="pp-card-example">
                                    <code>/galerie-epreuve/shooting-annecy</code><br>
                                    <span class="pp-arrow">↓</span><br>
                                    <code>/galerie-epreuve/550e8400-e29b-41d4...</code>
                                </p>
                            </div>

                            <div class="pp-card-footer">
                                <span class="pp-card-status"><?php echo get_option( 'photoproof_use_random_urls' ) ? esc_html__( 'Active', 'photoproof' ) : esc_html__( 'Inactive', 'photoproof' ); ?></span>
                            </div>
                        </div>
                        </div>

                        <!-- File renaming -->
                        <div class="pp-card-wrap">
                            <h3 class="pp-card-heading"><?php esc_html_e( 'File renaming', 'photoproof' ); ?></h3>
                            <span class="pp-card-bar" aria-hidden="true"></span>
                            <div class="pp-card<?php echo get_option( 'photoproof_enable_rename' ) ? ' is-active' : ''; ?>" data-toggle-target="photoproof_enable_rename">
                            <svg class="pp-card-stroke" preserveAspectRatio="none"><rect x="1" y="1" width="calc(100% - 2px)" height="calc(100% - 2px)"></rect></svg>

                            <button type="button" class="pp-card-toggle" aria-label="Toggle">
                                <svg class="icon-plus"     viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                <svg class="icon-minus"    viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
                                <svg class="icon-infinity" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                            </button>

                            <input type="hidden" name="photoproof_enable_rename" id="photoproof_enable_rename" value="<?php echo get_option( 'photoproof_enable_rename' ) ? '1' : '0'; ?>">

                            <div class="pp-card-header">
                                <span class="pp-card-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M14 2v6h6"/><path d="M18.5 13.5l3 3L17 21h-3v-3z"/></svg>
                                </span>
                                <span class="pp-card-title"><?php esc_html_e( 'File renaming', 'photoproof' ); ?></span>
                            </div>

                            <p class="pp-card-desc">
                                <?php esc_html_e( 'Automatically rename photos on upload. Counter (0001, 0002…) is appended automatically.', 'photoproof' ); ?>
                            </p>

                            <div class="pp-card-content">
                                <div>
                                    <label class="pp-field-label" for="photoproof_rename_pattern"><?php esc_html_e( 'Pattern', 'photoproof' ); ?></label>
                                    <input type="text" id="photoproof_rename_pattern" name="photoproof_rename_pattern"
                                        value="<?php echo esc_attr( get_option( 'photoproof_rename_pattern', '{gallery_title}' ) ); ?>"
                                        class="pp-input"
                                        placeholder="{gallery_title}">
                                </div>
                                <p class="pp-card-help">
                                    <?php
                                    printf(
                                        // translators: %s: variable name in code tags
                                        esc_html__( 'Use %s as a placeholder for the gallery name. Custom name in metabox overrides it.', 'photoproof' ),
                                        '<code>{gallery_title}</code>'
                                    );
                                    ?>
                                </p>
                            </div>

                            <div class="pp-card-footer">
                                <span class="pp-card-status"><?php echo get_option( 'photoproof_enable_rename' ) ? esc_html__( 'Active', 'photoproof' ) : esc_html__( 'Inactive', 'photoproof' ); ?></span>
                            </div>
                        </div>
                        </div>

                        <!-- Recommendations -->
                        <div class="pp-card-wrap">
                            <h3 class="pp-card-heading"><?php esc_html_e( 'Recommendations', 'photoproof' ); ?></h3>
                            <span class="pp-card-bar" aria-hidden="true"></span>
                            <div class="pp-card<?php echo get_option( 'photoproof_enable_recommendations' ) ? ' is-active' : ''; ?>" data-toggle-target="photoproof_enable_recommendations">
                            <svg class="pp-card-stroke" preserveAspectRatio="none"><rect x="1" y="1" width="calc(100% - 2px)" height="calc(100% - 2px)"></rect></svg>

                            <button type="button" class="pp-card-toggle" aria-label="Toggle">
                                <svg class="icon-plus"     viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                <svg class="icon-minus"    viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
                                <svg class="icon-infinity" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                            </button>

                            <input type="hidden" name="photoproof_enable_recommendations" id="photoproof_enable_recommendations" value="<?php echo get_option( 'photoproof_enable_recommendations' ) ? '1' : '0'; ?>">

                            <div class="pp-card-header">
                                <span class="pp-card-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                </span>
                                <span class="pp-card-title"><?php esc_html_e( 'Recommendations', 'photoproof' ); ?></span>
                            </div>

                            <p class="pp-card-desc">
                                <?php esc_html_e( "Highlight your favorite photos to guide your client's final selection.", 'photoproof' ); ?>
                            </p>

                            <div class="pp-card-content">
                                <div>
                                    <label class="pp-field-label" for="photoproof_global_recommendation_icon"><?php esc_html_e( 'Favorite icon', 'photoproof' ); ?></label>
                                    <select id="photoproof_global_recommendation_icon" name="photoproof_global_recommendation_icon" class="pp-select">
                                        <option value="dot"    <?php selected( get_option( 'photoproof_global_recommendation_icon' ), 'dot' ); ?>><?php esc_html_e( '■ Dot', 'photoproof' ); ?></option>
                                        <option value="star"   <?php selected( get_option( 'photoproof_global_recommendation_icon' ), 'star' ); ?>><?php esc_html_e( '★ Star', 'photoproof' ); ?></option>
                                        <option value="square" <?php selected( get_option( 'photoproof_global_recommendation_icon' ), 'square' ); ?>><?php esc_html_e( '◆ Diamond', 'photoproof' ); ?></option>
                                        <option value="heart"  <?php selected( get_option( 'photoproof_global_recommendation_icon' ), 'heart' ); ?>><?php esc_html_e( '❤ Heart', 'photoproof' ); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="pp-card-footer">
                                <span class="pp-card-status"><?php echo get_option( 'photoproof_enable_recommendations' ) ? esc_html__( 'Active', 'photoproof' ) : esc_html__( 'Inactive', 'photoproof' ); ?></span>
                            </div>
                        </div>
                        </div>

                        <!-- Auto-archiving -->
                        <div class="pp-card-wrap">
                            <h3 class="pp-card-heading"><?php esc_html_e( 'Auto-archiving', 'photoproof' ); ?></h3>
                            <span class="pp-card-bar" aria-hidden="true"></span>
                            <div class="pp-card<?php echo get_option( 'photoproof_enable_expiration' ) ? ' is-active' : ''; ?>" data-toggle-target="photoproof_enable_expiration">
                            <svg class="pp-card-stroke" preserveAspectRatio="none"><rect x="1" y="1" width="calc(100% - 2px)" height="calc(100% - 2px)"></rect></svg>

                            <button type="button" class="pp-card-toggle" aria-label="Toggle">
                                <svg class="icon-plus"     viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                <svg class="icon-minus"    viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
                                <svg class="icon-infinity" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                            </button>

                            <input type="hidden" name="photoproof_enable_expiration" id="photoproof_enable_expiration" value="<?php echo get_option( 'photoproof_enable_expiration' ) ? '1' : '0'; ?>">

                            <div class="pp-card-header">
                                <span class="pp-card-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                </span>
                                <span class="pp-card-title"><?php esc_html_e( 'Auto-archiving', 'photoproof' ); ?></span>
                            </div>

                            <p class="pp-card-desc">
                                <?php esc_html_e( 'Client access is automatically cut off 30 days after the gallery publication date. Galleries can still be reactivated manually.', 'photoproof' ); ?>
                            </p>

                            <div class="pp-card-content">
                                <p class="pp-card-help">
                                    <?php esc_html_e( 'Fixed lifetime: 30 days from publication.', 'photoproof' ); ?>
                                </p>
                            </div>

                            <div class="pp-card-footer">
                                <span class="pp-card-status"><?php echo get_option( 'photoproof_enable_expiration' ) ? esc_html__( 'Active', 'photoproof' ) : esc_html__( 'Inactive', 'photoproof' ); ?></span>
                            </div>
                        </div>
                        </div>

                        <!-- Client comments -->
                        <div class="pp-card-wrap is-half">
                            <h3 class="pp-card-heading"><?php esc_html_e( 'Client comments', 'photoproof' ); ?></h3>
                            <span class="pp-card-bar" aria-hidden="true"></span>
                            <div class="pp-card<?php echo get_option( 'photoproof_enable_comments' ) ? ' is-active' : ''; ?>" data-toggle-target="photoproof_enable_comments">
                            <svg class="pp-card-stroke" preserveAspectRatio="none"><rect x="1" y="1" width="calc(100% - 2px)" height="calc(100% - 2px)"></rect></svg>

                            <button type="button" class="pp-card-toggle" aria-label="Toggle">
                                <svg class="icon-plus"     viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                <svg class="icon-minus"    viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
                                <svg class="icon-infinity" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                            </button>

                            <input type="hidden" name="photoproof_enable_comments" id="photoproof_enable_comments" value="<?php echo get_option( 'photoproof_enable_comments' ) ? '1' : '0'; ?>">

                            <div class="pp-card-header">
                                <span class="pp-card-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                                </span>
                                <span class="pp-card-title"><?php esc_html_e( 'Client comments', 'photoproof' ); ?></span>
                            </div>

                            <p class="pp-card-desc">
                                <?php esc_html_e( 'Allow clients to leave a short note (max 500 chars) on each photo. Comments are included in the validation email and CSV export.', 'photoproof' ); ?>
                            </p>

                            <div class="pp-card-content">
                                <p class="pp-card-help">
                                    <?php esc_html_e( 'Maximum length: 500 characters per photo.', 'photoproof' ); ?>
                                </p>
                            </div>

                            <div class="pp-card-footer">
                                <span class="pp-card-status"><?php echo get_option( 'photoproof_enable_comments' ) ? esc_html__( 'Active', 'photoproof' ) : esc_html__( 'Inactive', 'photoproof' ); ?></span>
                            </div>
                        </div>
                        </div>

                    </div><!-- /.pp-cards-grid -->
                </div><!-- /#section-general -->

                <!-- ════════════════════════════════════════════════════════
                     TAB 2 — SECURITY & WATERMARK
                ════════════════════════════════════════════════════════ -->
                <div id="section-securite" class="pp-tab-panel" role="tabpanel">
                    <div class="pp-cards-grid">

                        <!-- Watermark -->
                        <div class="pp-card-wrap is-half">
                            <h3 class="pp-card-heading"><?php esc_html_e( 'Watermark', 'photoproof' ); ?></h3>
                            <span class="pp-card-bar" aria-hidden="true"></span>
                            <div class="pp-card<?php echo $is_watermark_active ? ' is-active' : ''; ?>" data-toggle-mode="fake">
                            <svg class="pp-card-stroke" preserveAspectRatio="none"><rect x="1" y="1" width="calc(100% - 2px)" height="calc(100% - 2px)"></rect></svg>

                            <button type="button" class="pp-card-toggle" aria-label="Toggle">
                                <svg class="icon-plus"     viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                <svg class="icon-minus"    viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
                                <svg class="icon-infinity" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                            </button>

                            <div class="pp-card-header">
                                <span class="pp-card-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                                </span>
                                <span class="pp-card-title"><?php esc_html_e( 'Watermark', 'photoproof' ); ?></span>
                            </div>

                            <p class="pp-card-desc">
                                <?php esc_html_e( 'Apply your logo as a watermark on uploaded photos. Discourages screenshots and asserts ownership.', 'photoproof' ); ?>
                            </p>

                            <div class="pp-card-content">
                                <input type="hidden" name="photoproof_global_watermark" id="photoproof_global_watermark"
                                    value="<?php echo esc_attr( get_option( 'photoproof_global_watermark' ) ); ?>">

                                <div id="wm-preview-container" class="pp-watermark-preview pp-preview-dark">
                                    <?php
                                    $wm_id = get_option( 'photoproof_global_watermark' );
                                    if ( $wm_id ) {
                                        $url     = wp_get_attachment_url( $wm_id );
                                        $opacity = (int) get_option( 'photoproof_watermark_opacity', 50 ) / 100;
                                        echo '<img id="wm-live-preview" src="' . esc_url( $url ) . '" style="opacity:' . esc_attr( $opacity ) . ';">';
                                    } else {
                                        echo '<p id="wm-placeholder">' . esc_html__( 'No logo configured', 'photoproof' ) . '</p>';
                                    }
                                    ?>
                                </div>

                                <div class="pp-button-row">
                                    <button type="button" class="button pp-btn-action" id="photoproof_upload_watermark_btn">
                                        <?php esc_html_e( 'Select logo', 'photoproof' ); ?>
                                    </button>
                                    <button type="button" class="button button-link-delete" id="photoproof_remove_watermark_btn"
                                        style="display: <?php echo $is_watermark_active ? 'inline-block' : 'none'; ?>;">
                                        <?php esc_html_e( 'Delete', 'photoproof' ); ?>
                                    </button>
                                </div>

                                <div>
                                    <label class="pp-field-label" for="photoproof_watermark_opacity_range">
                                        <?php esc_html_e( 'Opacity', 'photoproof' ); ?>
                                        <span class="pp-slider-value"><span id="opacity-val"><?php echo esc_html( get_option( 'photoproof_watermark_opacity', 50 ) ); ?></span>%</span>
                                    </label>
                                    <input type="range" name="photoproof_watermark_opacity" id="photoproof_watermark_opacity_range"
                                        class="pp-slider"
                                        min="10" max="100" step="5"
                                        value="<?php echo esc_attr( get_option( 'photoproof_watermark_opacity', 50 ) ); ?>"
                                        <?php echo $is_watermark_active ? '' : 'disabled'; ?>>
                                </div>
                            </div>

                            <div class="pp-card-footer">
                                <span class="pp-card-status"><?php echo $is_watermark_active ? esc_html__( 'Active', 'photoproof' ) : esc_html__( 'Inactive', 'photoproof' ); ?></span>
                            </div>
                        </div>
                        </div>

                        <!-- Custom Login URL (always-active, fallback to WP default if empty) -->
                        <div class="pp-card-wrap is-half">
                            <h3 class="pp-card-heading"><?php esc_html_e( 'Custom login URL', 'photoproof' ); ?></h3>
                            <span class="pp-card-bar" aria-hidden="true"></span>
                            <div class="pp-card is-active" data-toggle-mode="locked">
                            <svg class="pp-card-stroke" preserveAspectRatio="none"><rect x="1" y="1" width="calc(100% - 2px)" height="calc(100% - 2px)"></rect></svg>

                            <button type="button" class="pp-card-toggle is-locked" aria-label="Always active" disabled>
                                <svg class="icon-plus"     viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                <svg class="icon-minus"    viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
                                <svg class="icon-infinity" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                            </button>

                            <div class="pp-card-header">
                                <span class="pp-card-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                                </span>
                                <span class="pp-card-title"><?php esc_html_e( 'Custom login URL', 'photoproof' ); ?></span>
                            </div>

                            <p class="pp-card-desc">
                                <?php esc_html_e( 'Replace the default WordPress login screen with your own page. Leave empty to fall back to the standard WP login.', 'photoproof' ); ?>
                            </p>

                            <div class="pp-card-content">
                                <div>
                                    <label class="pp-field-label" for="photoproof_login_url"><?php esc_html_e( 'Login URL', 'photoproof' ); ?></label>
                                    <input type="url" id="photoproof_login_url" name="photoproof_login_url"
                                        value="<?php echo esc_attr( get_option( 'photoproof_login_url', '' ) ); ?>"
                                        placeholder="https://example.com/login"
                                        class="pp-input">
                                </div>
                                <p class="pp-card-help">
                                    <?php
                                    printf(
                                        // translators: %s: URL example
                                        esc_html__( 'Example: %s', 'photoproof' ),
                                        '<code>https://example.com/my-login</code>'
                                    );
                                    ?>
                                </p>
                            </div>

                            <div class="pp-card-footer">
                                <span class="pp-card-status is-locked">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                                    <?php esc_html_e( 'Always active', 'photoproof' ); ?>
                                </span>
                            </div>
                        </div>
                        </div>

                        <!-- File deletion on uninstall -->
                        <div class="pp-card-wrap">
                            <h3 class="pp-card-heading"><?php esc_html_e( 'Delete files on gallery removal', 'photoproof' ); ?></h3>
                            <span class="pp-card-bar" aria-hidden="true"></span>
                            <div class="pp-card<?php echo get_option( 'photoproof_delete_files_on_delete' ) ? ' is-active' : ''; ?>" data-toggle-target="photoproof_delete_files_on_delete">
                            <svg class="pp-card-stroke" preserveAspectRatio="none"><rect x="1" y="1" width="calc(100% - 2px)" height="calc(100% - 2px)"></rect></svg>

                            <button type="button" class="pp-card-toggle" aria-label="Toggle">
                                <svg class="icon-plus"     viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                <svg class="icon-minus"    viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
                                <svg class="icon-infinity" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                            </button>

                            <input type="hidden" name="photoproof_delete_files_on_delete" value="<?php echo get_option( 'photoproof_delete_files_on_delete' ) ? '1' : '0'; ?>">

                            <div class="pp-card-header">
                                <span class="pp-card-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                </span>
                                <span class="pp-card-title"><?php esc_html_e( 'Delete files on gallery removal', 'photoproof' ); ?></span>
                            </div>

                            <p class="pp-card-desc">
                                <?php esc_html_e( 'When a gallery is permanently deleted, also remove its physical files and attachments from the server.', 'photoproof' ); ?>
                            </p>

                            <div class="pp-card-content">
                                <p class="pp-card-help">
                                    <?php
                                    printf(
                                        // translators: %s: folder path in code tags
                                        esc_html__( 'If disabled, %s folders are kept on the server.', 'photoproof' ),
                                        '<code>photoproof/gallery-{id}/</code>'
                                    );
                                    ?>
                                </p>
                            </div>

                            <div class="pp-card-footer">
                                <span class="pp-card-status"><?php echo get_option( 'photoproof_delete_files_on_delete' ) ? esc_html__( 'Active', 'photoproof' ) : esc_html__( 'Inactive', 'photoproof' ); ?></span>
                            </div>
                        </div>
                        </div>

                    </div><!-- /.pp-cards-grid -->
                </div><!-- /#section-securite -->

                <!-- ════════════════════════════════════════════════════════
                     TAB 3 — THEME DESIGN
                ════════════════════════════════════════════════════════ -->
                <div id="section-design" class="pp-tab-panel" role="tabpanel">
                    <div class="pp-cards-grid">

                        <!-- Visual identity (always-active) -->
                        <div class="pp-card-wrap is-half">
                            <h3 class="pp-card-heading"><?php esc_html_e( 'Visual identity', 'photoproof' ); ?></h3>
                            <span class="pp-card-bar" aria-hidden="true"></span>
                            <div class="pp-card is-active" data-toggle-mode="locked">
                            <svg class="pp-card-stroke" preserveAspectRatio="none"><rect x="1" y="1" width="calc(100% - 2px)" height="calc(100% - 2px)"></rect></svg>

                            <button type="button" class="pp-card-toggle is-locked" aria-label="Always active" disabled>
                                <svg class="icon-plus"     viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                <svg class="icon-minus"    viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
                                <svg class="icon-infinity" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                            </button>

                            <div class="pp-card-header">
                                <span class="pp-card-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 22 8.5 22 15.5 12 22 2 15.5 2 8.5 12 2"/><line x1="12" y1="22" x2="12" y2="15.5"/><polyline points="22 8.5 12 15.5 2 8.5"/><line x1="2" y1="15.5" x2="12" y2="8.5"/><line x1="22" y1="15.5" x2="12" y2="8.5"/></svg>
                                </span>
                                <span class="pp-card-title"><?php esc_html_e( 'Visual identity', 'photoproof' ); ?></span>
                            </div>

                            <p class="pp-card-desc">
                                <?php esc_html_e( 'Configure the title and logo displayed in the front-end gallery header.', 'photoproof' ); ?>
                            </p>

                            <div class="pp-card-content">
                                <div>
                                    <label class="pp-field-label" for="photoproof_custom_title"><?php esc_html_e( 'Header title', 'photoproof' ); ?></label>
                                    <input type="text" id="photoproof_custom_title" name="photoproof_custom_title"
                                        value="<?php echo esc_attr( get_option( 'photoproof_custom_title', get_bloginfo( 'name' ) ) ); ?>"
                                        class="pp-input">
                                </div>

                                <div>
                                    <label class="pp-field-label"><?php esc_html_e( 'Custom logo', 'photoproof' ); ?></label>
                                    <div id="custom-logo-preview-container" class="pp-watermark-preview">
                                        <?php
                                        $custom_logo_id = get_option( 'photoproof_custom_logo' );
                                        if ( $custom_logo_id ) {
                                            $logo_url = wp_get_attachment_image_url( $custom_logo_id, 'medium' );
                                            if ( $logo_url ) {
                                                printf(
                                                    '<img id="custom-logo-live-preview" src="%s" alt="">',
                                                    esc_url( $logo_url )
                                                );
                                            }
                                        } else {
                                            echo '<p id="custom-logo-placeholder">' . esc_html__( 'Default website logo', 'photoproof' ) . '</p>';
                                        }
                                        ?>
                                    </div>
                                    <input type="hidden" name="photoproof_custom_logo" id="photoproof_custom_logo"
                                        value="<?php echo esc_attr( get_option( 'photoproof_custom_logo' ) ); ?>">
                                    <div class="pp-button-row">
                                        <button type="button" class="button pp-btn-invert" id="photoproof_upload_custom_logo_btn">
                                            <?php esc_html_e( 'Upload a logo', 'photoproof' ); ?>
                                        </button>
                                        <button type="button" class="button button-link-delete" id="photoproof_remove_custom_logo_btn"
                                            style="display: <?php echo get_option( 'photoproof_custom_logo' ) ? 'inline-block' : 'none'; ?>;">
                                            <?php esc_html_e( 'Remove', 'photoproof' ); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="pp-card-footer">
                                <span class="pp-card-status is-locked">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                                    <?php esc_html_e( 'Always active', 'photoproof' ); ?>
                                </span>
                            </div>
                        </div>
                        </div>

                        <!-- Color scheme (always-active) -->
                        <div class="pp-card-wrap is-half">
                            <h3 class="pp-card-heading"><?php esc_html_e( 'Color scheme', 'photoproof' ); ?></h3>
                            <span class="pp-card-bar" aria-hidden="true"></span>
                            <div class="pp-card is-active" data-toggle-mode="locked">
                            <svg class="pp-card-stroke" preserveAspectRatio="none"><rect x="1" y="1" width="calc(100% - 2px)" height="calc(100% - 2px)"></rect></svg>

                            <button type="button" class="pp-card-toggle is-locked" aria-label="Always active" disabled>
                                <svg class="icon-plus"     viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                <svg class="icon-minus"    viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
                                <svg class="icon-infinity" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                            </button>

                            <div class="pp-card-header">
                                <span class="pp-card-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="13.5" cy="6.5" r="1.5"/><circle cx="17.5" cy="10.5" r="1.5"/><circle cx="8.5" cy="7.5" r="1.5"/><circle cx="6.5" cy="12.5" r="1.5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c1.66 0 3-1.34 3-3 0-.78-.29-1.48-.78-2.02-.46-.51-.74-1.2-.74-1.96 0-1.66 1.34-3 3-3h1.5c2.76 0 5-2.24 5-5C22 5.86 17.5 2 12 2z"/></svg>
                                </span>
                                <span class="pp-card-title"><?php esc_html_e( 'Color scheme', 'photoproof' ); ?></span>
                            </div>

                            <p class="pp-card-desc">
                                <?php esc_html_e( 'Define the colors of the front-end gallery: background, accent, and text.', 'photoproof' ); ?>
                            </p>

                            <div class="pp-card-content">
                                <div class="pp-swatches">

                                    <div class="pp-swatch">
                                        <span class="pp-swatch-label"><?php esc_html_e( 'Background', 'photoproof' ); ?></span>
                                        <button type="button" class="pp-swatch-circle" data-target="photoproof_color_bg" aria-label="<?php esc_attr_e( 'Change background color', 'photoproof' ); ?>"></button>
                                        <span class="pp-swatch-hex" data-source="photoproof_color_bg"></span>
                                        <input type="text" id="photoproof_color_bg" name="photoproof_color_bg" class="pp-color-picker pp-color-picker-hidden"
                                            value="<?php echo esc_attr( get_option( 'photoproof_color_bg', '#ffffff' ) ); ?>">
                                    </div>

                                    <div class="pp-swatch">
                                        <span class="pp-swatch-label"><?php esc_html_e( 'Active', 'photoproof' ); ?></span>
                                        <button type="button" class="pp-swatch-circle" data-target="photoproof_color_active" aria-label="<?php esc_attr_e( 'Change active color', 'photoproof' ); ?>"></button>
                                        <span class="pp-swatch-hex" data-source="photoproof_color_active"></span>
                                        <input type="text" id="photoproof_color_active" name="photoproof_color_active" class="pp-color-picker pp-color-picker-hidden"
                                            value="<?php echo esc_attr( get_option( 'photoproof_color_active', '#2271b1' ) ); ?>">
                                    </div>

                                    <div class="pp-swatch">
                                        <span class="pp-swatch-label"><?php esc_html_e( 'Text', 'photoproof' ); ?></span>
                                        <button type="button" class="pp-swatch-circle" data-target="photoproof_color_text" aria-label="<?php esc_attr_e( 'Change text color', 'photoproof' ); ?>"></button>
                                        <span class="pp-swatch-hex" data-source="photoproof_color_text"></span>
                                        <input type="text" id="photoproof_color_text" name="photoproof_color_text" class="pp-color-picker pp-color-picker-hidden"
                                            value="<?php echo esc_attr( get_option( 'photoproof_color_text', '#1e293b' ) ); ?>">
                                    </div>

                                </div>
                            </div>

                            <div class="pp-card-footer">
                                <span class="pp-card-status is-locked">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                                    <?php esc_html_e( 'Always active', 'photoproof' ); ?>
                                </span>
                            </div>
                        </div>
                        </div>

                        <!-- Gallery layout (masonry) -->
                        <div class="pp-card-wrap">
                            <h3 class="pp-card-heading"><?php esc_html_e( 'Masonry layout', 'photoproof' ); ?></h3>
                            <span class="pp-card-bar" aria-hidden="true"></span>
                            <div class="pp-card<?php echo 'masonry' === get_option( 'photoproof_gallery_layout', 'grid' ) ? ' is-active' : ''; ?>" data-toggle-mode="layout">
                            <svg class="pp-card-stroke" preserveAspectRatio="none"><rect x="1" y="1" width="calc(100% - 2px)" height="calc(100% - 2px)"></rect></svg>

                            <button type="button" class="pp-card-toggle" aria-label="Toggle">
                                <svg class="icon-plus"     viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                <svg class="icon-minus"    viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
                                <svg class="icon-infinity" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                            </button>

                            <input type="hidden" name="photoproof_gallery_layout" id="photoproof_gallery_layout"
                                value="<?php echo esc_attr( get_option( 'photoproof_gallery_layout', 'grid' ) ); ?>">

                            <div class="pp-card-header">
                                <span class="pp-card-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                                </span>
                                <span class="pp-card-title"><?php esc_html_e( 'Masonry layout', 'photoproof' ); ?></span>
                            </div>

                            <p class="pp-card-desc">
                                <?php esc_html_e( 'Pinterest-style masonry layout that preserves each photo original aspect ratio. Inactive = uniform grid.', 'photoproof' ); ?>
                            </p>

                            <div class="pp-card-content">
                                <p class="pp-card-help">
                                    <?php esc_html_e( 'Default: uniform grid (all photos same height).', 'photoproof' ); ?>
                                </p>
                            </div>

                            <div class="pp-card-footer">
                                <span class="pp-card-status"><?php echo 'masonry' === get_option( 'photoproof_gallery_layout', 'grid' ) ? esc_html__( 'Active', 'photoproof' ) : esc_html__( 'Inactive', 'photoproof' ); ?></span>
                            </div>
                        </div>
                        </div>

                        <!-- Rounded corners -->
                        <div class="pp-card-wrap">
                            <h3 class="pp-card-heading"><?php esc_html_e( 'Rounded corners', 'photoproof' ); ?></h3>
                            <span class="pp-card-bar" aria-hidden="true"></span>
                            <div class="pp-card<?php echo get_option( 'photoproof_photo_rounded' ) ? ' is-active' : ''; ?>" data-toggle-target="photoproof_photo_rounded">
                            <svg class="pp-card-stroke" preserveAspectRatio="none"><rect x="1" y="1" width="calc(100% - 2px)" height="calc(100% - 2px)"></rect></svg>

                            <button type="button" class="pp-card-toggle" aria-label="Toggle">
                                <svg class="icon-plus"     viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                <svg class="icon-minus"    viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
                                <svg class="icon-infinity" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                            </button>

                            <input type="hidden" name="photoproof_photo_rounded" id="photoproof_photo_rounded" value="<?php echo get_option( 'photoproof_photo_rounded' ) ? '1' : '0'; ?>">

                            <div class="pp-card-header">
                                <span class="pp-card-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="6"/></svg>
                                </span>
                                <span class="pp-card-title"><?php esc_html_e( 'Rounded corners', 'photoproof' ); ?></span>
                            </div>

                            <p class="pp-card-desc">
                                <?php esc_html_e( 'Round photo corners on the front-end gallery. Inactive = sharp corners (reportage style).', 'photoproof' ); ?>
                            </p>

                            <div class="pp-card-content">
                                <p class="pp-card-help">
                                    <?php esc_html_e( 'Default: sharp corners.', 'photoproof' ); ?>
                                </p>
                            </div>

                            <div class="pp-card-footer">
                                <span class="pp-card-status"><?php echo get_option( 'photoproof_photo_rounded' ) ? esc_html__( 'Active', 'photoproof' ) : esc_html__( 'Inactive', 'photoproof' ); ?></span>
                            </div>
                        </div>
                        </div>

                    </div><!-- /.pp-cards-grid -->
                </div><!-- /#section-design -->

                <!-- ════════════════════════════════════════════════════════
                     TAB 4 — EMAILS
                ════════════════════════════════════════════════════════ -->
                <div id="section-emails" class="pp-tab-panel" role="tabpanel">
                    <div class="pp-cards-grid">

                        <!-- Variables reference (always-active info) -->
                        <div class="pp-card-wrap">
                            <h3 class="pp-card-heading"><?php esc_html_e( 'Email variables', 'photoproof' ); ?></h3>
                            <span class="pp-card-bar" aria-hidden="true"></span>
                            <div class="pp-card pp-card-neutral" data-toggle-mode="locked">
                            <svg class="pp-card-stroke" preserveAspectRatio="none"><rect x="1" y="1" width="calc(100% - 2px)" height="calc(100% - 2px)"></rect></svg>

                            <button type="button" class="pp-card-toggle is-locked" aria-label="Always active" disabled>
                                <svg class="icon-plus"     viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                <svg class="icon-minus"    viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
                                <svg class="icon-infinity" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                            </button>

                            <div class="pp-card-header">
                                <span class="pp-card-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                                </span>
                                <span class="pp-card-title"><?php esc_html_e( 'Email variables', 'photoproof' ); ?></span>
                            </div>

                            <p class="pp-card-desc">
                                <?php esc_html_e( 'Use these placeholders in your email templates. They are replaced automatically when emails are sent.', 'photoproof' ); ?>
                            </p>

                            <div class="pp-card-content">
                                <ul class="pp-vars-list">
                                    <li><code>{client_name}</code><span><?php esc_html_e( 'Client name', 'photoproof' ); ?></span></li>
                                    <li><code>{gallery_title}</code><span><?php esc_html_e( 'Gallery title', 'photoproof' ); ?></span></li>
                                    <li><code>{count}</code><span><?php esc_html_e( 'Number of selected photos', 'photoproof' ); ?></span></li>
                                    <li>
                                        <code>{file_list}</code>
                                        <span>
                                            <?php esc_html_e( 'List of selected filenames', 'photoproof' ); ?>
                                            <em class="pp-vars-note"><?php esc_html_e( 'Inline comments are appended below each filename when Client comments is enabled.', 'photoproof' ); ?></em>
                                        </span>
                                    </li>
                                    <li><code>{gallery_url}</code><span><?php esc_html_e( 'Gallery URL', 'photoproof' ); ?></span></li>
                                    <li><code>{studio_name}</code><span><?php esc_html_e( 'Your studio name', 'photoproof' ); ?></span></li>
                                    <li class="pp-vars-conditional">
                                        <code>{commented_not_selected}</code>
                                        <span>
                                            <?php esc_html_e( 'List of photos with comments but not selected', 'photoproof' ); ?>
                                            <em class="pp-vars-note pp-vars-note-required"><?php esc_html_e( 'Requires Client comments enabled', 'photoproof' ); ?></em>
                                        </span>
                                    </li>
                                </ul>
                            </div>

                            <div class="pp-card-footer">
                                <span class="pp-card-status is-locked">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                                    <?php esc_html_e( 'Always active', 'photoproof' ); ?>
                                </span>
                            </div>
                        </div>
                        </div>

                        <!-- Photographer email (always-active) -->
                        <div class="pp-card-wrap is-wide">
                            <h3 class="pp-card-heading"><?php esc_html_e( 'Email to photographer', 'photoproof' ); ?></h3>
                            <span class="pp-card-bar" aria-hidden="true"></span>
                            <div class="pp-card pp-card-neutral" data-toggle-mode="locked">
                            <svg class="pp-card-stroke" preserveAspectRatio="none"><rect x="1" y="1" width="calc(100% - 2px)" height="calc(100% - 2px)"></rect></svg>

                            <button type="button" class="pp-card-toggle is-locked" aria-label="Always active" disabled>
                                <svg class="icon-plus"     viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                <svg class="icon-minus"    viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
                                <svg class="icon-infinity" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                            </button>

                            <div class="pp-card-header">
                                <span class="pp-card-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8l11 7 11-7v11z"/><path d="M1 8l11 7 11-7"/><circle cx="18" cy="5" r="3" fill="currentColor" stroke="none"/></svg>
                                </span>
                                <span class="pp-card-title"><?php esc_html_e( 'Email to photographer', 'photoproof' ); ?></span>
                            </div>

                            <p class="pp-card-desc">
                                <?php esc_html_e( 'Sent to you when a client confirms their selection.', 'photoproof' ); ?>
                            </p>

                            <div class="pp-card-content">
                                <div>
                                    <label class="pp-field-label" for="photoproof_email_photographer_subject"><?php esc_html_e( 'Subject', 'photoproof' ); ?></label>
                                    <input type="text" id="photoproof_email_photographer_subject" name="photoproof_email_photographer_subject"
                                        class="pp-input"
                                        value="<?php echo esc_attr( get_option( 'photoproof_email_photographer_subject', '[PhotoProof] {client_name} validated the gallery "{gallery_title}"' ) ); ?>">
                                </div>
                                <div>
                                    <label class="pp-field-label" for="photoproof_email_photographer_body"><?php esc_html_e( 'Body', 'photoproof' ); ?></label>
                                    <textarea id="photoproof_email_photographer_body" name="photoproof_email_photographer_body" rows="10" class="pp-textarea"
                                    ><?php echo esc_textarea( get_option( 'photoproof_email_photographer_body',
                                        "Hello,\n\n{client_name} has confirmed their selection for the gallery '{gallery_title}'.\n\n{count} photo(s) selected:\n--------------------------------------\n{file_list}--------------------------------------\n\nView gallery: {gallery_url}\n\n— PhotoProof"
                                    ) ); ?></textarea>
                                </div>
                            </div>

                            <div class="pp-card-footer">
                                <span class="pp-card-status is-locked">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                                    <?php esc_html_e( 'Always active', 'photoproof' ); ?>
                                </span>
                            </div>
                        </div>
                        </div>

                        <!-- Client email (always-active) -->
                        <div class="pp-card-wrap is-wide">
                            <h3 class="pp-card-heading"><?php esc_html_e( 'Email to client', 'photoproof' ); ?></h3>
                            <span class="pp-card-bar" aria-hidden="true"></span>
                            <div class="pp-card pp-card-neutral" data-toggle-mode="locked">
                            <svg class="pp-card-stroke" preserveAspectRatio="none"><rect x="1" y="1" width="calc(100% - 2px)" height="calc(100% - 2px)"></rect></svg>

                            <button type="button" class="pp-card-toggle is-locked" aria-label="Always active" disabled>
                                <svg class="icon-plus"     viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                <svg class="icon-minus"    viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
                                <svg class="icon-infinity" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                            </button>

                            <div class="pp-card-header">
                                <span class="pp-card-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                </span>
                                <span class="pp-card-title"><?php esc_html_e( 'Email to client', 'photoproof' ); ?></span>
                            </div>

                            <p class="pp-card-desc">
                                <?php esc_html_e( 'Sent to the client after they confirm their selection.', 'photoproof' ); ?>
                            </p>

                            <div class="pp-card-content">
                                <div>
                                    <label class="pp-field-label" for="photoproof_email_client_subject"><?php esc_html_e( 'Subject', 'photoproof' ); ?></label>
                                    <input type="text" id="photoproof_email_client_subject" name="photoproof_email_client_subject"
                                        class="pp-input"
                                        value="<?php echo esc_attr( get_option( 'photoproof_email_client_subject', 'Your selection for "{gallery_title}" has been received' ) ); ?>">
                                </div>
                                <div>
                                    <label class="pp-field-label" for="photoproof_email_client_body"><?php esc_html_e( 'Body', 'photoproof' ); ?></label>
                                    <textarea id="photoproof_email_client_body" name="photoproof_email_client_body" rows="10" class="pp-textarea"
                                    ><?php echo esc_textarea( get_option( 'photoproof_email_client_body',
                                        "Hello {client_name},\n\nWe have received your selection of {count} photo(s) for the gallery '{gallery_title}'.\n\nWe will now handle the final processing of your selected images and will get back to you very soon.\n\nThank you for your trust.\n\n— {studio_name}"
                                    ) ); ?></textarea>
                                </div>
                            </div>

                            <div class="pp-card-footer">
                                <span class="pp-card-status is-locked">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8c-2.5 0-4.5 1.79-4.5 4s2 4 4.5 4c3.5 0 5.5-8 11-8 2.5 0 4.5 1.79 4.5 4s-2 4-4.5 4c-5.5 0-7.5-8-11-8z"/></svg>
                                    <?php esc_html_e( 'Always active', 'photoproof' ); ?>
                                </span>
                            </div>
                        </div>
                        </div>

                    </div><!-- /.pp-cards-grid -->
                </div><!-- /#section-emails -->

                <!-- Save bar (fixed bottom, clean/dirty states) -->
                <div class="pp-save-bar is-clean" id="pp-save-bar">
                    <span class="pp-save-bar-msg">
                        <span class="pp-save-bar-icon" aria-hidden="true"></span>
                        <span class="pp-save-bar-text"
                            data-clean="<?php esc_attr_e( 'All changes saved', 'photoproof' ); ?>"
                            data-dirty="<?php esc_attr_e( 'Unsaved changes', 'photoproof' ); ?>">
                            <?php esc_html_e( 'All changes saved', 'photoproof' ); ?>
                        </span>
                    </span>
                    <div class="pp-save-bar-actions">
                        <button type="button" class="pp-save-bar-discard" id="pp-save-bar-discard">
                            <?php esc_html_e( 'Discard', 'photoproof' ); ?>
                        </button>
                        <?php submit_button( __( 'Save', 'photoproof' ), 'primary pp-save-btn', 'submit', false, array( 'id' => 'pp-save-bar-submit' ) ); ?>
                    </div>
                </div>

            </form>
        </div>
        <?php
    }
}