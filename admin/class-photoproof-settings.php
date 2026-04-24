<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * Gestion des réglages globaux avec interface moderne et pédagogique.
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
         register_setting( 'photoproof_settings_group', 'photoproof_gallery_layout', array(
            'sanitize_callback' => array( $this, 'sanitize_layout' ),
            'default'           => 'grid',
        ) 
        );
        

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

    public function sanitize_layout( $value ) {
        $allowed = array( 'grid', 'masonry' );
        return in_array( $value, $allowed, true ) ? $value : 'grid';
    }

    public function render_settings_page() {
        ?>
        <div class="wrap pp-settings-page pp-loading">
            <h1><?php esc_html_e( 'Configuration PhotoProof', 'photoproof' ); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'photoproof_settings_group' ); ?>

                <div class="pp-settings-container">

                    <div class="pp-settings-sidebar">
                        <div class="pp-nav-item active" data-target="general">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <?php esc_html_e( 'General', 'photoproof' ); ?>
                        </div>
                        <div class="pp-nav-item" data-target="securite">
                            <span class="dashicons dashicons-shield"></span>
                            <?php esc_html_e( 'Security & Watermark', 'photoproof' ); ?>
                        </div>
                        <div class="pp-nav-item" data-target="design">
                            <span class="dashicons dashicons-admin-appearance"></span>
                            <?php esc_html_e( 'Theme Design', 'photoproof' ); ?>
                        </div>
                        <div class="pp-nav-item" data-target="emails">
                            <span class="dashicons dashicons-email-alt"></span>
                            <?php esc_html_e( 'Emails', 'photoproof' ); ?>
                        </div>
                    </div>

                    <div class="pp-settings-content">

                        <!-- ========================
                             SECTION : GÉNÉRAL
                        ======================== -->
                        <div id="section-general" class="pp-section-content active">
                            <div class="pp-card">

                                <h3><?php esc_html_e( 'Random URL (UUID)', 'photoproof' ); ?></h3>
                                <div class="pp-option-row">
                                    <label class="pp-switch">
                                        <input type="checkbox" name="photoproof_use_random_urls" value="1"
                                            <?php checked( 1, get_option( 'photoproof_use_random_urls' ), true ); ?>>
                                        <span class="pp-slider"></span>
                                    </label>
                                    <span class="pp-label-text">
                                        <?php esc_html_e( 'Hide gallery names in the URL ', 'photoproof' ); ?>
                                    </span>
                                </div>
                                <p class="pp-explanation">
                                    <?php
                                    printf(
                                        // translators: %d: Transforme vos liens en codes impossibles à deviner                                
                                        esc_html__( 'Transforms your links (e.g., %1$s) into impossible-to-guess codes (e.g., %2$s).', 'photoproof' ),
                                        '<code>/galerie-epreuve/shooting-annecy</code>',
                                        '<code>/galerie-epreuve/550e8400...</code>'
                                    );
                                    ?>
                                </p>

                                <hr class="pp-separator">

                                <h3><?php esc_html_e( 'Organization & Automatic Renaming', 'photoproof' ); ?></h3>
                                <div class="pp-option-row">
                                    <label class="pp-switch">
                                        <input type="checkbox" name="photoproof_enable_rename" id="photoproof_enable_rename" value="1"
                                            <?php checked( 1, get_option( 'photoproof_enable_rename' ), true ); ?>>
                                        <span class="pp-slider"></span>
                                    </label>
                                    <span class="pp-label-text">
                                        <?php esc_html_e( 'Enable automatic renaming on upload', 'photoproof' ); ?>
                                    </span>
                                </div>

                                <div id="rename-details" class="pp-sub-panel" style="display: <?php echo get_option( 'photoproof_enable_rename' ) ? 'block' : 'none'; ?>; margin-top: 20px;">
                                    <div class="pp-option-row" style="flex-direction: column; align-items: flex-start; gap: 15px;">
                                        <label class="pp-main-label">
                                            <?php esc_html_e( 'File name structure', 'photoproof' ); ?>
                                        </label>
                                        <input type="text" name="photoproof_rename_pattern"
                                            value="<?php echo esc_attr( get_option( 'photoproof_rename_pattern', '{gallery_title}-{index}' ) ); ?>"
                                            class="regular-text"
                                            placeholder="{gallery_title}-{index}">

                                        <div class="pp-help-box" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 4px; font-size: 13px; color: #475569; width: 100%; box-sizing: border-box;">
                                            <p style="margin-top:0; font-weight: 600; color: #1e293b;">
                                                <?php esc_html_e( 'Global renaming structure:', 'photoproof' ); ?>
                                            </p>
                                            <p>
                                                <?php esc_html_e( 'This setting defines the "template" used to name your files. You can include your brand name permanently.', 'photoproof' ); ?>
                                            </p>
                                            <ul style="margin: 10px 0; padding-left: 20px; list-style: disc;">
                                                <li style="margin-bottom: 10px;">
                                                    <code>{gallery_title}</code> : <strong><?php esc_html_e( 'Variable content.', 'photoproof' ); ?></strong>
                                                    <br><?php esc_html_e( 'By default, it uses your PhotoProof gallery title.', 'photoproof' ); ?>
                                                    <br><span style="color: #2271b1; font-weight: 500;">
                                                        <?php esc_html_e( '👉 If you fill in the "Custom Name" in a gallery, it will be used to fill this tag.', 'photoproof' ); ?>
                                                    </span>
                                                </li>
                                                <li>
                                                    <code>{index}</code> : <strong><?php esc_html_e( 'The counter.', 'photoproof' ); ?></strong>
                                                    <?php esc_html_e( 'Automatic numbering (0001, 0002...).', 'photoproof' ); ?>
                                                </li>
                                            </ul>
                                            <div style="background: #fff; padding: 12px; border: 1px solid #cbd5e1; margin-top: 10px;">
                                                <p style="margin: 0 0 5px 0; font-size: 11px; text-transform: uppercase; color: #94a3b8;">
                                                    <?php esc_html_e( 'Example with brand:', 'photoproof' ); ?>
                                                </p>
                                                <code style="background: #f1f5f9; padding: 2px 4px; border-radius: 3px;">MonStudio-{gallery_title}-{index}</code>
                                                <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed #eee; font-size: 12px; color: #1e293b;">
                                                    <?php
                                                    printf(
                                                        // translators: %d: Galerie - Gallery
                                                        esc_html__( 'Gallery %1$s → %2$s', 'photoproof' ),
                                                        '<strong>"Shooting Annecy"</strong>',
                                                        '<code style="color: #059669;">MonStudio-shooting-annecy-0001.jpg</code>'
                                                    );
                                                    ?>
                                                </div>
                                            </div>
                                            <p style="margin-top: 15px; font-size: 11px; border-top: 1px solid #e2e8f0; padding-top: 10px; color: #64748b; line-height: 1.4;">
                                                <span class="dashicons dashicons-info" style="color: #2271b1; vertical-align: text-bottom; margin-right: 5px; font-size: 16px;"></span>
                                                <?php
                                                printf(
                                                    // translators: %d: Si vous omettez PhotoProof l'ajoutera automatiquement à la fin
                                                    esc_html__( 'Note: If you omit %s, PhotoProof will automatically add it at the end.', 'photoproof' ),
                                                    '<code>{index}</code>'
                                                );
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <hr class="pp-separator">

                                <h3><?php esc_html_e( "Photographer's Selection (Recommendations)", 'photoproof' ); ?></h3>
                                <div class="pp-option-row">
                                    <label class="pp-switch">
                                        <input type="checkbox" name="photoproof_enable_recommendations" id="photoproof_enable_recommendations" value="1"
                                            <?php checked( 1, get_option( 'photoproof_enable_recommendations' ), true ); ?>>
                                        <span class="pp-slider"></span>
                                    </label>
                                    <span class="pp-label-text">
                                        <?php esc_html_e( 'Enable recommendation mentions', 'photoproof' ); ?>
                                    </span>
                                </div>
                                <p class="pp-explanation">
                                    <?php esc_html_e( 'Highlight your favorite photos to your clients to guide their final selection.', 'photoproof' ); ?>
                                </p>

                                <div id="recommendation-details" class="pp-sub-panel" style="display: <?php echo get_option( 'photoproof_enable_recommendations' ) ? 'block' : 'none'; ?>; margin-top: 15px;">
                                    <label class="pp-main-label">
                                        <?php esc_html_e( 'Favorite icon:', 'photoproof' ); ?>
                                    </label>
                                    <select name="photoproof_global_recommendation_icon" style="width:100%; max-width:250px;">
                                        <option value="dot"    <?php selected( get_option( 'photoproof_global_recommendation_icon' ), 'dot' ); ?>>
                                            <?php esc_html_e( '■ Dot', 'photoproof' ); ?>
                                        </option>
                                        <option value="star"   <?php selected( get_option( 'photoproof_global_recommendation_icon' ), 'star' ); ?>>
                                            <?php esc_html_e( '★ Star', 'photoproof' ); ?>
                                        </option>
                                        <option value="square" <?php selected( get_option( 'photoproof_global_recommendation_icon' ), 'square' ); ?>>
                                            <?php esc_html_e( '◆ Diamond', 'photoproof' ); ?>
                                        </option>
                                        <option value="heart"  <?php selected( get_option( 'photoproof_global_recommendation_icon' ), 'heart' ); ?>>
                                            <?php esc_html_e( '❤ Heart', 'photoproof' ); ?>
                                        </option>
                                    </select>
                                </div>

                                <hr class="pp-separator">

                                <h3><?php esc_html_e( 'Access Expiration', 'photoproof' ); ?></h3>
                                <div class="pp-option-row">
                                    <label class="pp-switch">
                                        <input type="checkbox" name="photoproof_enable_expiration" id="photoproof_enable_expiration" value="1"
                                            <?php checked( 1, get_option( 'photoproof_enable_expiration' ), true ); ?>>
                                        <span class="pp-slider"></span>
                                    </label>
                                    <span class="pp-label-text">
                                        <?php esc_html_e( 'Auto-archiving', 'photoproof' ); ?>
                                    </span>
                                </div>

                                <div id="expiration-details" class="pp-sub-panel" style="display: <?php echo get_option( 'photoproof_enable_expiration' ) ? 'block' : 'none'; ?>; margin-top: 15px;">
                                    <p class="pp-explanation" style="margin:0;">
                                        <span class="dashicons dashicons-clock" style="color: #2271b1; vertical-align: middle;"></span>
                                        <?php
                                        printf(
                                            // translators: %d: Locked acces after 30 days
                                            esc_html__( 'Fixed setting: Client access will be automatically cut off 30 days after the publication date of each gallery.', 'photoproof' ),
                                            '<strong>', '</strong>', '<strong>', '</strong>'
                                        );
                                        ?>
                                        <br><small><i><?php esc_html_e( 'You can still reactivate a gallery manually if needed.', 'photoproof' ); ?></i></small>
                                    </p>
                                </div>

                            </div><!-- /.pp-card -->
                        </div><!-- /#section-general -->

                        <!-- ========================
                             SECTION : DESIGN
                        ======================== -->
                        <div id="section-design" class="pp-section-content">
                            <div class="pp-card">

                                <h3><?php esc_html_e( 'Visual Identity (Front End)', 'photoproof' ); ?></h3>
                                <div class="pp-branding-grid">
                                    <div class="pp-branding-controls">
                                        <label class="pp-main-label">
                                            <?php esc_html_e( 'Gallery main header Title', 'photoproof' ); ?>
                                        </label>
                                        <input type="text" name="photoproof_custom_title"
                                            value="<?php echo esc_attr( get_option( 'photoproof_custom_title', get_bloginfo( 'name' ) ) ); ?>"
                                            class="regular-text" style="width:100%;">
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 12px;">
                                        <div class="pp-branding-preview" style="margin: 0;">
                                            <div id="custom-logo-preview-container">
                                                <?php
                                                $custom_logo_id = get_option( 'photoproof_custom_logo' );
                                                if ( $custom_logo_id ) {
                                                    echo wp_get_attachment_image( $custom_logo_id, 'medium', false, array( 'style' => 'max-width:150px; height:auto;' ) );
                                                } else {
                                                    echo '<p style="color:#94a3b8; font-size:13px; margin:0;">' . esc_html__( 'Default website logo', 'photoproof' ) . '</p>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                            <input type="hidden" name="photoproof_custom_logo" id="photoproof_custom_logo"
                                                value="<?php echo esc_attr( get_option( 'photoproof_custom_logo' ) ); ?>">
                                            <button type="button" class="button button-secondary" id="pp_upload_custom_logo_btn">
                                                <?php esc_html_e( 'Upload a logo', 'photoproof' ); ?>
                                            </button>
                                            <button type="button" class="button button-link-delete" id="pp_remove_custom_logo_btn"
                                                style="display: <?php echo get_option( 'photoproof_custom_logo' ) ? 'inline-block' : 'none'; ?>;">
                                                <?php esc_html_e( 'Remove', 'photoproof' ); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <hr class="pp-separator">

                                <h3><?php esc_html_e( 'Theme Colors Scheme', 'photoproof' ); ?></h3>
                                <div class="pp-color-grid" style="display: flex; gap: 20px; flex-wrap: wrap;">
                                    <div class="pp-color-item">
                                        <label><?php esc_html_e( 'Background', 'photoproof' ); ?></label><br>
                                        <input type="text" name="photoproof_color_bg" class="pp-color-picker"
                                            value="<?php echo esc_attr( get_option( 'photoproof_color_bg', '#ffffff' ) ); ?>">
                                    </div>
                                    <div class="pp-color-item">
                                        <label><?php esc_html_e( 'Active color', 'photoproof' ); ?></label><br>
                                        <input type="text" name="photoproof_color_active" class="pp-color-picker"
                                            value="<?php echo esc_attr( get_option( 'photoproof_color_active', '#2271b1' ) ); ?>">
                                    </div>
                                    <div class="pp-color-item">
                                        <label><?php esc_html_e( 'Text color', 'photoproof' ); ?></label><br>
                                        <input type="text" name="photoproof_color_text" class="pp-color-picker"
                                            value="<?php echo esc_attr( get_option( 'photoproof_color_text', '#1e293b' ) ); ?>">
                                    </div>
                                </div>

                                <hr class="pp-separator">

                                <h3><?php esc_html_e( 'Photo Format (Client Gallery)', 'photoproof' ); ?></h3>
                                <div class="pp-option-row">
                                    <label class="pp-switch">
                                        <input type="checkbox" name="photoproof_photo_rounded" id="photoproof_photo_rounded" value="1"
                                            <?php checked( 1, get_option( 'photoproof_photo_rounded' ), true ); ?>>
                                        <span class="pp-slider"></span>
                                    </label>
                                    <span class="pp-label-text">
                                        <?php esc_html_e( 'Round corners', 'photoproof' ); ?>
                                    </span>
                                </div>
                                <p class="pp-explanation">
                                    <?php esc_html_e( 'By default, photos are displayed with square corners (reportage style).', 'photoproof' ); ?>
                                    <br>
                                    <?php esc_html_e( 'Enable this option for slightly rounded corners (modern style).', 'photoproof' ); ?>
                                </p>

                                <hr class="pp-separator">

                                                                <h3><?php esc_html_e( 'Masonry Layout', 'photoproof' ); ?></h3>
                                                                <div class="pp-option-row">
                                                                    <label class="pp-switch">
                                                                        <input type="checkbox" name="photoproof_gallery_layout" id="photoproof_gallery_layout" value="masonry"
                                                                            <?php checked( 'masonry', get_option( 'photoproof_gallery_layout', 'grid' ), true ); ?>>
                                                                        <span class="pp-slider"></span>
                                                                    </label>
                                                                    <span class="pp-label-text">
                                                                        <?php esc_html_e( 'Enable Pinterest-style layout', 'photoproof' ); ?>
                                                                    </span>
                                                                </div>
                                                                <p class="pp-explanation">
                                                                    <?php esc_html_e( 'By default, photos are displayed in a uniform grid. Enable this option to arrange photos by their original aspect ratio — rows will have variable heights based on each photo.', 'photoproof' ); ?>
                                                                </p>

                            </div><!-- /.pp-card -->
                        </div><!-- /#section-design -->

                        <!-- ========================
                             SECTION : SÉCURITÉ
                        ======================== -->
                        <div id="section-securite" class="pp-section-content">
                            <div class="pp-card">

                                <h3><?php esc_html_e( 'Watermark Protection', 'photoproof' ); ?></h3>
                                <p class="pp-explanation">
                                    <?php esc_html_e( 'Automatically applies your logo to uploaded images to prevent screenshots.', 'photoproof' ); ?>
                                </p>
                                <div class="pp-branding-grid">
                                    <div class="pp-branding-controls">
                                        <input type="hidden" name="photoproof_global_watermark" id="photoproof_global_watermark"
                                            value="<?php echo esc_attr( get_option( 'photoproof_global_watermark' ) ); ?>">
                                        <button type="button" class="button button-secondary" id="pp_upload_watermark_btn">
                                            <?php esc_html_e( 'Select logo', 'photoproof' ); ?>
                                        </button>
                                        <button type="button" class="button button-link-delete" id="pp_remove_watermark_btn"
                                            style="display: <?php echo get_option( 'photoproof_global_watermark' ) ? 'inline-block' : 'none'; ?>; margin-left: 10px;">
                                            <?php esc_html_e( 'Delete', 'photoproof' ); ?>
                                        </button>
                                        <div class="pp-range-group" style="margin-top:20px;">
                                            <label>
                                                <?php esc_html_e( 'Opacity :', 'photoproof' ); ?>
                                                <span id="opacity-val"><?php echo esc_html( get_option( 'photoproof_watermark_opacity', 50 ) ); ?></span>%
                                            </label>
                                            <input type="range" name="photoproof_watermark_opacity" id="pp_watermark_opacity_range"
                                                min="10" max="100" step="5"
                                                value="<?php echo esc_attr( get_option( 'photoproof_watermark_opacity', 50 ) ); ?>"
                                                style="width:100%;"
                                                <?php echo get_option( 'photoproof_global_watermark' ) ? '' : 'disabled'; ?>>
                                        </div>
                                    </div>
                                    <div class="pp-branding-preview">
                                        <div id="wm-preview-container" style="border: 1px dashed #ccc; padding: 10px; text-align: center; min-height: 100px; display: flex; align-items: center; justify-content: center; background: #f0f0f0;">
                                            <?php
                                            $wm_id = get_option( 'photoproof_global_watermark' );
                                            if ( $wm_id ) :
                                                $url     = wp_get_attachment_url( $wm_id );
                                                $opacity = (int) get_option( 'photoproof_watermark_opacity', 50 ) / 100;
                                                echo '<img id="wm-live-preview" src="' . esc_url( $url ) . '" style="opacity:' . esc_attr( $opacity ) . '; max-width:150px; height:auto;">';
                                            else :
                                                echo '<p id="wm-placeholder" style="color:#94a3b8;">' . esc_html__( 'No logo configured', 'photoproof' ) . '</p>';
                                            endif;
                                            ?>
                                        </div>
                                    </div>
                                </div>

                            </div><!-- /.pp-card -->

                            <div class="pp-card">

                                <h3><?php esc_html_e( 'Access & Authentication', 'photoproof' ); ?></h3>
                                <div class="pp-option-row" style="flex-direction: column; align-items: flex-start; gap: 10px;">
                                    <label class="pp-main-label">
                                        <?php esc_html_e( 'Login Page', 'photoproof' ); ?>
                                    </label>
                                    <input type="url" name="photoproof_login_url"
                                        value="<?php echo esc_attr( get_option( 'photoproof_login_url', '' ) ); ?>"
                                        placeholder="https://example.com/login"
                                        class="regular-text" style="width: 100%;">
                                    <p class="pp-explanation" style="margin: 0;">
                                        <?php esc_html_e( 'URL of your custom login page. If empty, WordPress will use its default login page.', 'photoproof' ); ?>
                                        <br>
                                        <?php
                                        printf(
                                            // translators: %d: Exemple
                                            esc_html__( 'Example: %s', 'photoproof' ),
                                            '<code>https://example.com/my-custom-login-page</code>'
                                        );
                                        ?>
                                    </p>
                                </div>

                                <hr class="pp-separator">

                                <h3><?php esc_html_e( 'File Deletion', 'photoproof' ); ?></h3>
                                <div class="pp-option-row">
                                    <label class="pp-switch">
                                        <input type="checkbox" name="photoproof_delete_files_on_delete" value="1"
                                            <?php checked( 1, get_option( 'photoproof_delete_files_on_delete' ), true ); ?>>
                                        <span class="pp-slider"></span>
                                    </label>
                                    <span class="pp-label-text">
                                        <?php esc_html_e( 'Delete photos when a gallery is deleted', 'photoproof' ); ?>
                                    </span>
                                </div>
                                <p class="pp-explanation">
                                    <?php esc_html_e( 'If enabled, physical files and attachments are permanently deleted along with the gallery.', 'photoproof' ); ?>
                                    <br>
                                    <?php
                                    printf(
                                        // translators: If unchecked img folder are not deleted with the post - Gallery
                                        esc_html__( 'If disabled, %s folders are kept on the server.', 'photoproof' ),
                                        '<code>photoproof/gallery-{id}/</code>'
                                    );
                                    ?>
                                </p>

                                <hr class="pp-separator">

                                <h3><?php esc_html_e( 'Data Protection', 'photoproof' ); ?></h3>
                                <p style="padding: 15px; background: #fff8e5; border-left: 4px solid #ffb900; border-radius: 4px;">
                                    <?php esc_html_e( 'Advanced automatic deletion features are currently under development.', 'photoproof' ); ?>
                                </p>

                            </div><!-- /.pp-card -->
                        </div><!-- /#section-securite -->

                        <!-- ========================
                             SECTION : EMAILS
                        ======================== -->
                        <div id="section-emails" class="pp-section-content">

                            <div class="pp-card">
                                <h3><?php esc_html_e( 'Email variables', 'photoproof' ); ?></h3>
                                <p class="pp-explanation" style="margin:0;">
                                    <?php esc_html_e( 'Use these variables in your email templates:', 'photoproof' ); ?>
                                    <br><br>
                                    <code>{client_name}</code> — <?php esc_html_e( 'Client name', 'photoproof' ); ?><br>
                                    <code>{gallery_title}</code> — <?php esc_html_e( 'Gallery title', 'photoproof' ); ?><br>
                                    <code>{count}</code> — <?php esc_html_e( 'Number of selected photos', 'photoproof' ); ?><br>
                                    <code>{file_list}</code> — <?php esc_html_e( 'List of selected filenames', 'photoproof' ); ?><br>
                                    <code>{gallery_url}</code> — <?php esc_html_e( 'Gallery URL', 'photoproof' ); ?><br>
                                    <code>{studio_name}</code> — <?php esc_html_e( 'Your studio name (from settings)', 'photoproof' ); ?>
                                </p>
                            </div>

                            <div class="pp-card">
                                <h3><?php esc_html_e( 'Email to photographer', 'photoproof' ); ?></h3>
                                <p class="pp-explanation">
                                    <?php esc_html_e( 'Sent to you when a client confirms their selection.', 'photoproof' ); ?>
                                </p>

                                <div class="pp-option-row" style="flex-direction: column; align-items: flex-start; gap: 10px; margin-bottom: 16px;">
                                    <label class="pp-main-label" for="photoproof_email_photographer_subject">
                                        <?php esc_html_e( 'Subject', 'photoproof' ); ?>
                                    </label>
                                    <input type="text"
                                        name="photoproof_email_photographer_subject"
                                        id="photoproof_email_photographer_subject"
                                        class="regular-text"
                                        style="width:100%;"
                                        value="<?php echo esc_attr( get_option( 'photoproof_email_photographer_subject', '[PhotoProof] {client_name} validated the gallery "{gallery_title}"' ) ); ?>">
                                </div>

                                <div class="pp-option-row" style="flex-direction: column; align-items: flex-start; gap: 10px;">
                                    <label class="pp-main-label" for="photoproof_email_photographer_body">
                                        <?php esc_html_e( 'Body', 'photoproof' ); ?>
                                    </label>
                                    <textarea
                                        name="photoproof_email_photographer_body"
                                        id="photoproof_email_photographer_body"
                                        rows="10"
                                        style="width:100%; font-family: monospace; font-size: 13px;"
                                    ><?php echo esc_textarea( get_option( 'photoproof_email_photographer_body',
                                        "Hello,

{client_name} has confirmed their selection for the gallery '{gallery_title}'.

{count} photo(s) selected:
--------------------------------------
{file_list}--------------------------------------

View gallery: {gallery_url}

— PhotoProof"
                                    ) ); ?></textarea>
                                </div>
                            </div>

                            <div class="pp-card">
                                <h3><?php esc_html_e( 'Email to client', 'photoproof' ); ?></h3>
                                <p class="pp-explanation">
                                    <?php esc_html_e( 'Sent to the client after they confirm their selection.', 'photoproof' ); ?>
                                </p>

                                <div class="pp-option-row" style="flex-direction: column; align-items: flex-start; gap: 10px; margin-bottom: 16px;">
                                    <label class="pp-main-label" for="photoproof_email_client_subject">
                                        <?php esc_html_e( 'Subject', 'photoproof' ); ?>
                                    </label>
                                    <input type="text"
                                        name="photoproof_email_client_subject"
                                        id="photoproof_email_client_subject"
                                        class="regular-text"
                                        style="width:100%;"
                                        value="<?php echo esc_attr( get_option( 'photoproof_email_client_subject', 'Your selection for "{gallery_title}" has been received' ) ); ?>">
                                </div>

                                <div class="pp-option-row" style="flex-direction: column; align-items: flex-start; gap: 10px;">
                                    <label class="pp-main-label" for="photoproof_email_client_body">
                                        <?php esc_html_e( 'Body', 'photoproof' ); ?>
                                    </label>
                                    <textarea
                                        name="photoproof_email_client_body"
                                        id="photoproof_email_client_body"
                                        rows="10"
                                        style="width:100%; font-family: monospace; font-size: 13px;"
                                    ><?php echo esc_textarea( get_option( 'photoproof_email_client_body',
                                        "Hello {client_name},

We have received your selection of {count} photo(s) for the gallery '{gallery_title}'.

We will now handle the final processing of your selected images and will get back to you very soon.

Thank you for your trust.

— {studio_name}"
                                    ) ); ?></textarea>
                                </div>
                            </div>

                        </div><!-- /#section-emails -->

                        <div class="pp-save-bar">
                            <?php submit_button( __( 'Save Preferences', 'photoproof' ), 'primary', 'submit', false ); ?>
                        </div>

                    </div><!-- /.pp-settings-content -->
                </div><!-- /.pp-settings-container -->
            </form>
        </div><!-- /.wrap -->
        <?php
    }
}