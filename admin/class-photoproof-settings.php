<?php
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
            'edit.php?post_type=pp_gallery',
            'Réglages PhotoProof',
            'Réglages',
            'manage_options',
            'photoproof-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'pp_settings_group', 'pp_use_random_urls' );
        register_setting( 'pp_settings_group', 'pp_enable_expiration' );
        register_setting( 'pp_settings_group', 'pp_enable_rename' );
        register_setting( 'pp_settings_group', 'pp_rename_pattern' );
        register_setting( 'pp_settings_group', 'pp_enable_recommendations' );
        register_setting( 'pp_settings_group', 'pp_global_recommendation_icon' );
        register_setting( 'pp_settings_group', 'pp_global_watermark' );
        register_setting( 'pp_settings_group', 'pp_watermark_opacity' );
        register_setting( 'pp_settings_group', 'pp_custom_logo' );
        register_setting( 'pp_settings_group', 'pp_custom_title' );
        register_setting( 'pp_settings_group', 'pp_color_bg',     array( 'sanitize_callback' => 'sanitize_hex_color' ) );
        register_setting( 'pp_settings_group', 'pp_color_active', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
        register_setting( 'pp_settings_group', 'pp_color_text',   array( 'sanitize_callback' => 'sanitize_hex_color' ) );
        register_setting( 'pp_settings_group', 'pp_photo_rounded' );
        register_setting( 'pp_settings_group', 'pp_login_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
        register_setting( 'pp_settings_group', 'pp_delete_files_on_delete' );

    }

    public function render_settings_page() {
        ?>
        <div class="wrap pp-settings-page">
            <h1>Configuration PhotoProof</h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'pp_settings_group' ); ?>

                <div class="pp-settings-container">

                    <!-- CORRECTION : sidebar navigation -->
                    <div class="pp-settings-sidebar">
                        <div class="pp-nav-item active" data-target="general">
                            <span class="dashicons dashicons-admin-generic"></span> Général
                        </div>
                        <div class="pp-nav-item" data-target="apparence">
                            <span class="dashicons dashicons-shield"></span> Filigrane
                        </div>
                        <div class="pp-nav-item" data-target="design">
                            <span class="dashicons dashicons-admin-appearance"></span> Design Thème
                        </div>
                        <div class="pp-nav-item" data-target="securite">
                            <span class="dashicons dashicons-lock"></span> Sécurité
                        </div>
                    </div>

                    <div class="pp-settings-content">

                        <!-- ========================
                             SECTION : GÉNÉRAL
                             CORRECTION : div correctement fermée avant les sections suivantes
                        ======================== -->
                        <div id="section-general" class="pp-section-content active">
                            <div class="pp-card">

                                <h3>URLs Aléatoires (UUID)</h3>
                                <div class="pp-option-row">
                                    <label class="pp-switch">
                                        <input type="checkbox" name="pp_use_random_urls" value="1" <?php checked( 1, get_option( 'pp_use_random_urls' ), true ); ?>>
                                        <span class="pp-slider"></span>
                                    </label>
                                    <span class="pp-label-text">Masquer le nom des galeries dans l'adresse</span>
                                </div>
                                <p class="pp-explanation">
                                    Transforme vos liens (ex: <code>/galerie-epreuve/mariage-annecy</code>) en codes impossibles à deviner (ex: <code>/galerie-epreuve/550e8400...</code>).
                                </p>

                                <hr class="pp-separator">

                                <h3>Organisation & Renommage automatique</h3>
                                <div class="pp-option-row">
                                    <label class="pp-switch">
                                        <input type="checkbox" name="pp_enable_rename" id="pp_enable_rename" value="1" <?php checked( 1, get_option( 'pp_enable_rename' ), true ); ?>>
                                        <span class="pp-slider"></span>
                                    </label>
                                    <span class="pp-label-text">Activer le renommage automatique à l'upload</span>
                                </div>

                                <div id="rename-details" class="pp-sub-panel" style="display: <?php echo get_option( 'pp_enable_rename' ) ? 'block' : 'none'; ?>; margin-top: 20px;">
                                    <div class="pp-option-row" style="flex-direction: column; align-items: flex-start; gap: 15px;">
                                        <label class="pp-main-label">Structure du nom de fichier</label>
                                        <input type="text" name="pp_rename_pattern" value="<?php echo esc_attr( get_option( 'pp_rename_pattern', '{gallery_title}-{index}' ) ); ?>" class="regular-text" placeholder="{gallery_title}-{index}">

                                        <div class="pp-help-box" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 4px; font-size: 13px; color: #475569; width: 100%; box-sizing: border-box;">
                                            <p style="margin-top:0; font-weight: 600; color: #1e293b;">Structure de renommage globale :</p>
                                            <p>Ce réglage définit le "moule" utilisé pour nommer vos fichiers. Vous pouvez y inclure votre nom de marque de façon permanente.</p>
                                            <ul style="margin: 10px 0; padding-left: 20px; list-style: disc;">
                                                <li style="margin-bottom: 10px;">
                                                    <code>{gallery_title}</code> : <strong>Le contenu variable.</strong>
                                                    <br>Par défaut, il utilise le titre de votre galerie PhotoProof.
                                                    <br><span style="color: #2271b1; font-weight: 500;">👉 Si vous remplissez le "Nom personnalisé" dans une galerie, c'est ce dernier qui viendra remplir cette balise.</span>
                                                </li>
                                                <li>
                                                    <code>{index}</code> : <strong>Le compteur.</strong> Numérotation automatique (0001, 0002...).
                                                </li>
                                            </ul>
                                            <div style="background: #fff; padding: 12px; border: 1px solid #cbd5e1; margin-top: 10px;">
                                                <p style="margin: 0 0 5px 0; font-size: 11px; text-transform: uppercase; color: #94a3b8;">Exemple avec marque :</p>
                                                <code style="background: #f1f5f9; padding: 2px 4px; border-radius: 3px;">MonStudio-{gallery_title}-{index}</code>
                                                <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed #eee; font-size: 12px; color: #1e293b;">
                                                    Galerie <strong>"Reportage Annecy"</strong> → <code style="color: #059669;">MonStudio-reportage-annecy-0001.jpg</code>
                                                </div>
                                            </div>
                                            <p style="margin-top: 15px; font-size: 11px; border-top: 1px solid #e2e8f0; padding-top: 10px; color: #64748b; line-height: 1.4;">
                                                <span class="dashicons dashicons-info" style="color: #2271b1; vertical-align: text-bottom; margin-right: 5px; font-size: 16px;"></span>
                                                <strong>Note :</strong> Si vous omettez <code>{index}</code>, PhotoProof l'ajoutera automatiquement à la fin.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <hr class="pp-separator">

                                <h3>Sélection du Photographe (Recommandations)</h3>
                                <div class="pp-option-row">
                                    <label class="pp-switch">
                                        <input type="checkbox" name="pp_enable_recommendations" id="pp_enable_recommendations" value="1" <?php checked( 1, get_option( 'pp_enable_recommendations' ), true ); ?>>
                                        <span class="pp-slider"></span>
                                    </label>
                                    <span class="pp-label-text">Activer les mentions de recommandation</span>
                                </div>
                                <p class="pp-explanation">Indiquez vos photos favorites à vos clients pour orienter leur sélection finale.</p>

                                <div id="recommendation-details" class="pp-sub-panel" style="display: <?php echo get_option( 'pp_enable_recommendations' ) ? 'block' : 'none'; ?>; margin-top: 15px;">
                                    <label class="pp-main-label">Icône de favori :</label>
                                    <select name="pp_global_recommendation_icon" style="width:100%; max-width:250px;">
                                        <option value="dot"  <?php selected( get_option( 'pp_global_recommendation_icon' ), 'dot' ); ?>>■ Point</option>
                                        <option value="star" <?php selected( get_option( 'pp_global_recommendation_icon' ), 'star' ); ?>>★ Etoile</option>
                                        <option value="square"<?php selected( get_option( 'pp_global_recommendation_icon' ), 'square' ); ?>>■ carré</option>
                                        <option value="heart"<?php selected( get_option( 'pp_global_recommendation_icon' ), 'heart' ); ?>>❤ Coeur</option>
                                    </select>
                                </div>

                                <hr class="pp-separator">

                                <h3>Expiration des Accès</h3>
                                <div class="pp-option-row">
                                    <label class="pp-switch">
                                        <input type="checkbox" name="pp_enable_expiration" id="pp_enable_expiration" value="1" <?php checked( 1, get_option( 'pp_enable_expiration' ), true ); ?>>
                                        <span class="pp-slider"></span>
                                    </label>
                                    <span class="pp-label-text">Protection contre l'oubli (Auto-archivage)</span>
                                </div>

                                <div id="expiration-details" class="pp-sub-panel" style="display: <?php echo get_option( 'pp_enable_expiration' ) ? 'block' : 'none'; ?>; margin-top: 15px;">
                                    <p class="pp-explanation" style="margin:0;">
                                        <span class="dashicons dashicons-clock" style="color: #2271b1; vertical-align: middle;"></span>
                                        <strong>Réglage fixe :</strong> L'accès client sera automatiquement coupé <strong>30 jours</strong> après la date de publication de chaque galerie.
                                        <br><small><i>(Vous pourrez toujours réactiver une galerie manuellement si besoin).</i></small>
                                    </p>
                                </div>

                            </div><!-- /.pp-card -->
                        </div><!-- /#section-general  ← CORRECTION : fermé ici, au bon niveau -->

                        <!-- ========================
                             SECTION : FILIGRANE
                             CORRECTION : div sœur de section-general, plus imbriquée dedans
                        ======================== -->
                        <div id="section-apparence" class="pp-section-content">
                            <div class="pp-card">
                                <h3>Protection Filigrane (Watermark)</h3>
                                <p class="pp-explanation">Applique automatiquement votre logo sur les images envoyées pour empêcher les captures d'écran.</p>
                                <div class="pp-branding-grid">
                                    <div class="pp-branding-controls">
                                        <input type="hidden" name="pp_global_watermark" id="pp_global_watermark" value="<?php echo esc_attr( get_option( 'pp_global_watermark' ) ); ?>">
                                        <button type="button" class="button button-secondary" id="pp_upload_watermark_btn">Choisir le logo</button>

                                        <!-- CORRECTION : bouton suppression watermark ajouté -->
                                        <button type="button" class="button button-link-delete" id="pp_remove_watermark_btn" style="display: <?php echo get_option( 'pp_global_watermark' ) ? 'inline-block' : 'none'; ?>; margin-left: 10px;">Supprimer</button>

                                        <div class="pp-range-group" style="margin-top:20px;">
                                            <label>Opacité : <span id="opacity-val"><?php echo esc_attr( get_option( 'pp_watermark_opacity', 50 ) ); ?></span>%</label>
                                            <input type="range" name="pp_watermark_opacity" id="pp_watermark_opacity_range" min="10" max="100" step="5"
                                                value="<?php echo esc_attr( get_option( 'pp_watermark_opacity', 50 ) ); ?>"
                                                style="width:100%;"
                                                <?php echo get_option( 'pp_global_watermark' ) ? '' : 'disabled'; ?>>
                                        </div>
                                    </div>
                                    <div class="pp-branding-preview">
                                        <div id="wm-preview-container" style="border: 1px dashed #ccc; padding: 10px; text-align: center; min-height: 100px; display: flex; align-items: center; justify-content: center; background: #f0f0f0;">
                                            <?php
                                            $wm_id = get_option( 'pp_global_watermark' );
                                            if ( $wm_id ) :
                                                $url     = wp_get_attachment_url( $wm_id );
                                                $opacity = (int) get_option( 'pp_watermark_opacity', 50 ) / 100; // CORRECTION : cast (int)
                                                echo '<img id="wm-live-preview" src="' . esc_url( $url ) . '" style="opacity:' . esc_attr( $opacity ) . '; max-width:150px; height:auto;">';
                                            else :
                                                echo '<p id="wm-placeholder" style="color:#94a3b8;">Aucun logo configuré</p>';
                                            endif;
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div><!-- /.pp-card -->
                        </div><!-- /#section-apparence -->

                        <!-- ========================
                             SECTION : DESIGN
                        ======================== -->
                        <div id="section-design" class="pp-section-content">
                            <div class="pp-card">
                                <h3>Identité Visuelle (Espace Client)</h3>
                                <div class="pp-option-row" style="flex-direction: column; align-items: flex-start; gap: 10px;">
                                    <label class="pp-main-label">Titre de l'en-tête</label>
                                    <input type="text" name="pp_custom_title" value="<?php echo esc_attr( get_option( 'pp_custom_title', get_bloginfo( 'name' ) ) ); ?>" class="regular-text">
                                </div>
                                <hr class="pp-separator">
                                <div class="pp-branding-grid">
                                    <div class="pp-branding-controls">
                                        <label class="pp-main-label">Logo spécifique</label>
                                        <input type="hidden" name="pp_custom_logo" id="pp_custom_logo" value="<?php echo esc_attr( get_option( 'pp_custom_logo' ) ); ?>">
                                        <button type="button" class="button button-secondary" id="pp_upload_custom_logo_btn">Téléverser</button>
                                        <!-- CORRECTION : bouton suppression logo custom ajouté -->
                                        <button type="button" class="button button-link-delete" id="pp_remove_custom_logo_btn" style="display: <?php echo get_option( 'pp_custom_logo' ) ? 'inline-block' : 'none'; ?>; margin-left: 10px;">Supprimer</button>
                                    </div>
                                    <div class="pp-branding-preview">
                                        <div id="custom-logo-preview-container">
                                            <?php
                                            $custom_logo_id = get_option( 'pp_custom_logo' );
                                            if ( $custom_logo_id ) {
                                                echo wp_get_attachment_image( $custom_logo_id, 'medium', false, array( 'style' => 'max-width:150px; height:auto;' ) );
                                            } else {
                                                echo '<p style="color:#94a3b8;">Logo du site par défaut</p>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <hr class="pp-separator">
                                <h3>Couleurs du Thème</h3>
                                <div class="pp-color-grid" style="display: flex; gap: 20px; flex-wrap: wrap;">
                                    <div class="pp-color-item">
                                        <label>Arrière-plan</label><br>
                                        <input type="text" name="pp_color_bg" class="pp-color-picker" value="<?php echo esc_attr( get_option( 'pp_color_bg', '#ffffff' ) ); ?>">
                                    </div>
                                    <div class="pp-color-item">
                                        <label>Accentuation (Active)</label><br>
                                        <input type="text" name="pp_color_active" class="pp-color-picker" value="<?php echo esc_attr( get_option( 'pp_color_active', '#2271b1' ) ); ?>">
                                    </div>
                                    <div class="pp-color-item">
                                        <label>Texte & Contrastes</label><br>
                                        <input type="text" name="pp_color_text" class="pp-color-picker" value="<?php echo esc_attr( get_option( 'pp_color_text', '#1e293b' ) ); ?>">
                                    </div>
                                </div>
                                <hr class="pp-separator">
                                <h3>Format des photos (Galerie client)</h3>
                                <div class="pp-option-row">
                                    <label class="pp-switch">
                                        <input type="checkbox" name="pp_photo_rounded" id="pp_photo_rounded" value="1"
                                            <?php checked( 1, get_option( 'pp_photo_rounded' ), true ); ?>>
                                        <span class="pp-slider"></span>
                                    </label>
                                    <span class="pp-label-text">Coins arrondis sur les photos</span>
                                </div>
                                <p class="pp-explanation">
                                    Par défaut les photos sont affichées avec des coins carrés (style reportage).<br>
                                    Activez cette option pour des coins légèrement arrondis (style moderne).
                                </p>
                            </div><!-- /.pp-card -->
                        </div><!-- /#section-design -->

                        <!-- ========================
                             SECTION : SÉCURITÉ
                        ======================== -->
<div id="section-securite" class="pp-section-content">
    <div class="pp-card">
        <h3>Accès & Authentification</h3>
        <div class="pp-option-row" style="flex-direction: column; align-items: flex-start; gap: 10px;">
            <label class="pp-main-label">Page de connexion</label>
            <input type="url" name="pp_login_url"
                value="<?php echo esc_attr( get_option( 'pp_login_url', '' ) ); ?>"
                placeholder="<?php echo esc_attr( wp_login_url() ); ?>"
                class="regular-text"
                style="width: 100%;">
            <p class="pp-explanation" style="margin: 0;">
                URL de votre page de connexion personnalisée. Si vide, WordPress utilisera sa page de login par défaut.<br>
                Exemple : https://example.com/login</code>
            </p>
        </div>
        <hr class="pp-separator">
<h3>Suppression des fichiers</h3>
<div class="pp-option-row">
    <label class="pp-switch">
        <input type="checkbox" name="pp_delete_files_on_delete" value="1"
            <?php checked( 1, get_option( 'pp_delete_files_on_delete' ), true ); ?>>
        <span class="pp-slider"></span>
    </label>
    <span class="pp-label-text">Supprimer les photos à la suppression d'une galerie</span>
</div>
<p class="pp-explanation">
    Si activé, les fichiers physiques et les attachements sont supprimés définitivement avec la galerie.<br>
    Si désactivé, les dossiers <code>photoproof/gallery-{id}/</code> sont conservés sur le serveur.
</p>
        <hr class="pp-separator">
        <h3>Protection des données</h3>
        <p style="padding: 15px; background: #fff8e5; border-left: 4px solid #ffb900; border-radius: 4px;">Les fonctions avancées de suppression automatique sont en cours de développement.</p>
    </div>
</div>

                        <!-- Barre de sauvegarde — toujours visible, hors des sections -->
                        <div class="pp-save-bar">
                            <?php submit_button( 'Enregistrer les préférences', 'primary', 'submit', false ); ?>
                        </div>

                    </div><!-- /.pp-settings-content -->
                </div><!-- /.pp-settings-container -->
            </form>
        </div><!-- /.wrap -->
        <?php
    }
}