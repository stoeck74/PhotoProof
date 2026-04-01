<?php
/**
 * Gestion de l'interface d'édition de la galerie PhotoProof
 * Metabox redesignée — états contextuels, hiérarchie visuelle
 */
class PhotoProof_Metaboxes {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_gallery_settings_metabox' ) );
        add_action( 'save_post',      array( $this, 'save_gallery_settings' ) );
    }

    public function add_gallery_settings_metabox() {
        add_meta_box(
            'pp_gallery_settings',
            'PhotoProof',
            array( $this, 'render_metabox' ),
            'pp_gallery',
            'normal',
            'high'
        );
    }

    public function render_metabox( $post ) {
        global $wpdb;

        $data = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
            $post->ID
        ) );

        $current_status = $data ? $data->status : 'brouillon';
        $is_new         = ( $post->post_status === 'auto-draft' || ! $data );
        $is_brouillon   = ( $current_status === 'brouillon' );
        $is_publie      = ( $current_status === 'publie' );
        $is_validated   = ( $current_status === 'valide' );
        $is_ferme       = ( $current_status === 'ferme' );
        $is_locked      = ( $is_validated || $is_ferme );

        $selected_photos = get_post_meta( $post->ID, '_pp_selected_photos', true );
        $selected_ids    = is_array( $selected_photos ) ? array_map( 'intval', $selected_photos ) : array();
        $count_selection = count( $selected_ids );

        $gallery_url  = get_permalink( $post->ID );
        $export_nonce = wp_create_nonce( 'pp_export_' . $post->ID );
        $reopen_nonce = wp_create_nonce( 'pp_reopen_' . $post->ID );

        // Config bande statut
        $status_config = array(
            'brouillon' => array( 'label' => 'Brouillon — Privé',        'sub' => 'Non visible par le client',            'class' => 'pp-state-brouillon' ),
            'publie'    => array( 'label' => 'Publiée — Ouverte',         'sub' => 'En attente de la sélection client',    'class' => 'pp-state-publie' ),
            'valide'    => array( 'label' => 'Sélection confirmée',       'sub' => 'Le client a validé sa sélection',      'class' => 'pp-state-valide' ),
            'ferme'     => array( 'label' => 'Archivée',                  'sub' => 'Accès client désactivé',               'class' => 'pp-state-ferme' ),
        );
        $sc = $status_config[ $current_status ] ?? $status_config['brouillon'];

        wp_nonce_field( 'pp_save_gallery_settings', 'pp_gallery_nonce' );
        ?>

        <div class="pp-meta" id="pp-wrapper">

            <!-- ── BANDE STATUT ── -->
            <div class="pp-state-bar <?php echo esc_attr( $sc['class'] ); ?>">
                <div class="pp-state-left">
                    <div class="pp-state-dot"></div>
                    <div>
                        <div class="pp-state-label"><?php echo esc_html( $sc['label'] ); ?></div>
                        <div class="pp-state-sub"><?php echo esc_html( $sc['sub'] ); ?></div>
                    </div>
                </div>
                <div class="pp-state-actions">
                    <?php if ( $is_validated ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=pp_export_selection&post_id=' . $post->ID . '&_wpnonce=' . $export_nonce ) ); ?>"
                           class="pp-btn pp-btn-sm pp-btn-state-action">↓ Exporter CSV</a>
                    <?php endif; ?>
                        <?php if ( ! $is_new && ! $is_brouillon ) : ?>
                            <a href="<?php echo esc_url( $gallery_url ); ?>" target="_blank"
                            class="pp-btn pp-btn-sm pp-btn-ghost">
                                Voir la galerie ↗
                            </a>
                        <?php endif; ?>
                </div>
            </div>

            <!-- ── URL — toujours visible ── -->
            <div class="pp-url-row">
                <span class="pp-url-label">URL</span>
                <div class="pp-url-value" title="<?php echo esc_attr( $gallery_url ); ?>">
                    <?php echo esc_html( $gallery_url ); ?>
                </div>
                <button type="button" class="pp-btn pp-btn-sm pp-btn-ghost" id="pp-copy-link-btn-2"
                    data-url="<?php echo esc_attr( $gallery_url ); ?>">
                    Copier
                </button>
            </div>

            <div class="pp-meta-body">

                <!-- ── CONFIGURATION ── -->
                <div class="pp-meta-section">
                    <div class="pp-meta-section-title">Configuration</div>
                    <div class="pp-fields-row">

                        <div class="pp-field">
                            <label for="pp_client_id">Client assigné</label>
                            <select name="pp_client_id" id="pp_client_id" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                <option value="">— Aucun client —</option>
                                <?php
                                $users = get_users( array( 'role__in' => array( 'subscriber', 'customer', 'author', 'editor', 'administrator' ) ) );
                                foreach ( $users as $user ) :
                                    $sel  = ( $data && $data->client_id == $user->ID ) ? 'selected' : '';
                                    $name = trim( $user->last_name . ' ' . $user->first_name );
                                    $name = $name ?: $user->display_name;
                                    echo '<option value="' . esc_attr( $user->ID ) . '" ' . $sel . '>'
                                        . esc_html( $name ) . ' (' . esc_html( $user->user_login ) . ')</option>';
                                endforeach;
                                ?>
                            </select>
                        </div>

                        <div class="pp-field">
                            <label for="pp_status">État</label>
                            <select name="pp_status" id="pp_status">
                                <?php
                                $status_list = array(
                                    'brouillon' => '📝 Brouillon (Privé)',
                                    'publie'    => '🌐 Publiée (Ouverte)',
                                    'valide'    => '✅ Sélection terminée',
                                    'ferme'     => '🔒 Archivée',
                                );
                                foreach ( $status_list as $key => $label ) :
                                    $sel = ( $data && $data->status === $key ) ? 'selected' : '';
                                    echo '<option value="' . esc_attr( $key ) . '" ' . $sel . '>'
                                        . esc_html( $label ) . '</option>';
                                endforeach;
                                ?>
                            </select>
                        </div>

                        <div class="pp-field">
                            <label for="pp_custom_rename">Préfixe personnalisé</label>
                            <input type="text"
                                name="pp_custom_rename"
                                id="pp_custom_rename"
                                value="<?php echo esc_attr( get_post_meta( $post->ID, '_pp_custom_rename', true ) ); ?>"
                                placeholder="Ex : Mariage-Dupont"
                                <?php echo $is_locked ? 'disabled' : ''; ?>>
                        </div>

                    </div>
                </div>

                <!-- ── SÉLECTION CLIENT (publie / valide / ferme) ── -->
                <?php if ( ! $is_brouillon ) : ?>
                <div class="pp-meta-section">
                    <div class="pp-meta-section-title">Sélection client</div>

                    <?php if ( $is_validated ) : ?>

                        <!-- État validé : bandeau + récap + réouverture -->
                        <div class="pp-validated-banner">
                            <span class="pp-validated-icon">✓</span>
                            <div>
                                <strong>Sélection confirmée par le client</strong>
                                <span><?php echo intval( $count_selection ); ?> photo(s) retenue(s)</span>
                            </div>
                        </div>

                        <?php if ( ! empty( $selected_ids ) ) : ?>
                        <div class="pp-selection-recap">
                            <?php foreach ( $selected_ids as $att_id ) :
                                $target   = get_post_meta( $att_id, '_pp_target_filename', true );
                                $filename = $target ?: basename( get_attached_file( $att_id ) );
                                ?>
                                <div class="pp-recap-thumb">
                                    <?php echo wp_get_attachment_image( $att_id, 'thumbnail', false, array( 'class' => 'pp-recap-img' ) ); ?>
                                    <span class="pp-recap-name"><?php echo esc_html( $filename ); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <div class="pp-reopen-actions">
                            <p class="pp-meta-section-title" style="margin-bottom:10px;">Rouvrir la galerie</p>
                            <div class="pp-reopen-btns">
                                <button type="button" class="pp-btn pp-btn-sm pp-btn-reopen"
                                    data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                                    data-nonce="<?php echo esc_attr( $reopen_nonce ); ?>"
                                    data-mode="keep">
                                    Rouvrir — conserver la sélection
                                </button>
                                <button type="button" class="pp-btn pp-btn-sm pp-btn-reopen pp-btn-danger"
                                    data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                                    data-nonce="<?php echo esc_attr( $reopen_nonce ); ?>"
                                    data-mode="reset">
                                    Rouvrir — remettre à zéro
                                </button>
                            </div>
                        </div>

                    <?php else : ?>

                        <!-- État publie / ferme : compteur + CSV -->
                        <div class="pp-sel-card">
                            <div class="pp-sel-count">
                                <strong id="pp-selection-count"><?php echo intval( $count_selection ); ?></strong>
                                <span>photo(s) sélectionnée(s)</span>
                            </div>
                            <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=pp_export_selection&post_id=' . $post->ID . '&_wpnonce=' . $export_nonce ) ); ?>"
                               class="pp-btn pp-btn-sm pp-btn-primary">
                                ↓ Exporter CSV
                            </a>
                        </div>

                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- ── OPTIONS FILIGRANE (brouillon + publie uniquement) ── -->
                <?php if ( ! $is_locked ) : ?>
                <div class="pp-meta-section">
                    <div class="pp-meta-section-title">Options</div>
                    <div class="pp-toggle-row">
                        <label class="pp-switch">
                            <?php $wm_active = ( $data && isset( $data->watermark_settings ) && $data->watermark_settings === 'yes' ); ?>
                            <input type="checkbox" name="pp_watermark_active" value="yes" <?php checked( $wm_active, true ); ?>>
                            <span class="pp-slider"></span>
                        </label>
                        <div class="pp-toggle-text">
                            <span class="pp-toggle-title">Protection filigrane</span>
                            <span class="pp-toggle-sub">Applique votre logo sur toutes les images de cette galerie</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ── MÉDIAS (brouillon + publie uniquement) ── -->
                <?php if ( ! $is_locked ) : ?>
                <div class="pp-meta-section">
                    <div class="pp-meta-section-title">Médias</div>
                    <div class="pp-upload-zone" id="pp_upload_btn">
                        <svg class="pp-upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                        </svg>
                        <div class="pp-upload-title">
                            <?php echo $is_new ? 'Enregistrez d\'abord la galerie' : 'Ajouter des photos'; ?>
                        </div>
                        <div class="pp-upload-sub">
                            <?php echo $is_new ? 'Publiez ou enregistrez en brouillon avant d\'uploader' : 'Cliquez pour parcourir ou glissez-déposez vos fichiers ici'; ?>
                        </div>
                    </div>
                    <div id="pp-gallery-preview" class="pp-gallery-grid"></div>
                </div>
                <?php endif; ?>

                <!-- ── ALERTE (publie uniquement) ── -->
                <?php if ( $is_publie ) : ?>
                <div class="pp-alert">
                    <div class="pp-alert-dot"></div>
                    <div class="pp-alert-text">
                        Galerie publiée — évitez de supprimer des photos pour ne pas invalider la sélection du client.
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /.pp-meta-body -->
        </div><!-- /.pp-meta -->

        <script>
        (function() {
            // Copie du lien (deux boutons)
            function setupCopy(btnId) {
                var btn = document.getElementById(btnId);
                if (!btn) return;
                btn.addEventListener('click', function() {
                    var url  = btn.dataset.url;
                    var orig = btn.textContent;
                    function feedback() {
                        btn.textContent = 'Copié !';
                        setTimeout(function() { btn.textContent = orig; }, 2000);
                    }
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(url).then(feedback);
                    } else {
                        var tmp = document.createElement('input');
                        tmp.value = url; document.body.appendChild(tmp); tmp.select();
                        document.execCommand('copy'); document.body.removeChild(tmp);
                        feedback();
                    }
                });
            }
            setupCopy('pp-copy-link-btn');
            setupCopy('pp-copy-link-btn-2');

            // Boutons réouverture
            document.querySelectorAll('.pp-btn-reopen').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var mode  = btn.dataset.mode;
                    var label = mode === 'reset' ? 'remettre à zéro la sélection' : 'conserver la sélection';
                    if (!confirm('Rouvrir la galerie en mode "' + label + '" ?\nLe client pourra à nouveau modifier sa sélection.')) return;

                    btn.textContent = 'En cours…';
                    btn.disabled = true;

                    var fd = new FormData();
                    fd.append('action',  'pp_reopen_gallery');
                    fd.append('post_id', btn.dataset.postId);
                    fd.append('nonce',   btn.dataset.nonce);
                    fd.append('mode',    mode);

                    fetch(ajaxurl, { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.success) {
                                window.location.reload();
                            } else {
                                alert('Erreur : ' + (data.data && data.data.message ? data.data.message : 'inconnue'));
                                btn.disabled = false;
                                btn.textContent = mode === 'reset' ? 'Rouvrir — remettre à zéro' : 'Rouvrir — conserver la sélection';
                            }
                        });
                });
            });
        })();
        </script>
        <?php
    }

    public function save_gallery_settings( $post_id ) {
        if ( ! isset( $_POST['pp_gallery_nonce'] ) || ! wp_verify_nonce( $_POST['pp_gallery_nonce'], 'pp_save_gallery_settings' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        global $wpdb;
        $table_name = $wpdb->prefix . 'photoproof_galleries';

        $allowed_statuses = array( 'brouillon', 'publie', 'valide', 'ferme' );
        $status_raw       = isset( $_POST['pp_status'] ) ? sanitize_text_field( $_POST['pp_status'] ) : 'brouillon';
        $status           = in_array( $status_raw, $allowed_statuses, true ) ? $status_raw : 'brouillon';
        $watermark_val    = isset( $_POST['pp_watermark_active'] ) ? 'yes' : 'no';
        $client_id        = ( isset( $_POST['pp_client_id'] ) && $_POST['pp_client_id'] !== '' ) ? intval( $_POST['pp_client_id'] ) : null;

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table_name WHERE post_id = %d",
            $post_id
        ) );

        if ( $existing ) {
            $wpdb->update(
                $table_name,
                array(
                    'client_id'          => $client_id,
                    'status'             => $status,
                    'watermark_settings' => $watermark_val,
                    'folder_path'        => 'photoproof/gallery-' . $post_id,
                ),
                array( 'post_id' => $post_id ),
                array( '%d', '%s', '%s', '%s' ),
                array( '%d' )
            );
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'post_id'            => $post_id,
                    'client_id'          => $client_id,
                    'status'             => $status,
                    'watermark_settings' => $watermark_val,
                    'folder_path'        => 'photoproof/gallery-' . $post_id,
                ),
                array( '%d', '%d', '%s', '%s', '%s' )
            );
        }

        update_post_meta( $post_id, '_pp_custom_rename', sanitize_text_field( $_POST['pp_custom_rename'] ?? '' ) );
    }
}