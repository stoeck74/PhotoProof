<?php
/**
 * Gestion de l'interface d'édition de la galerie PhotoProof
 *
 * NOUVEAU :
 * - Boutons "Rouvrir (reset)" et "Rouvrir (conserver)" quand statut = valide
 * - Récap de la sélection client avec miniatures et noms de fichiers
 * - Nonce pp_reopen pour les actions de réouverture
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
        $table_name = $wpdb->prefix . 'photoproof_galleries';

        $data = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d",
            $post->ID
        ) );

        $current_status  = $data ? $data->status : 'brouillon';
        $is_validated    = ( $current_status === 'valide' );

        $selected_photos = get_post_meta( $post->ID, '_pp_selected_photos', true );
        $selected_ids    = ( ! empty( $selected_photos ) && is_array( $selected_photos ) )
            ? array_map( 'intval', $selected_photos )
            : array();
        $count_selection = count( $selected_ids );

        $gallery_url = get_permalink( $post->ID );
        $export_nonce = wp_create_nonce( 'pp_export_' . $post->ID );
        $reopen_nonce = wp_create_nonce( 'pp_reopen_' . $post->ID );

        wp_nonce_field( 'pp_save_gallery_settings', 'pp_gallery_nonce' );
        ?>

        <div class="pp-meta" id="pp-wrapper">

            <!-- ── LIEN CLIENT ── -->
            <p class="pp-section-label">Lien client</p>
            <div class="pp-share">
                <span class="pp-share-label">URL</span>
                <div class="pp-share-url" title="<?php echo esc_attr( $gallery_url ); ?>">
                    <?php echo esc_html( $gallery_url ); ?>
                </div>
                <button type="button" class="pp-btn pp-btn-sm" id="pp-copy-link-btn"
                    data-url="<?php echo esc_attr( $gallery_url ); ?>">
                    Copier
                </button>
            </div>

            <hr class="pp-divider">

            <!-- ── CONFIGURATION ── -->
            <p class="pp-section-label">Configuration</p>
            <div class="pp-fields-row">

                <div class="pp-field">
                    <label for="pp_client_id">Client assigné</label>
                    <select name="pp_client_id" id="pp_client_id" <?php echo $is_validated ? 'disabled' : ''; ?>>
                        <option value="">— Aucun client —</option>
                            <?php
                            $users = get_users( array( 'role__in' => array( 'subscriber', 'customer', 'author', 'editor' ) ) );

                            foreach ( $users as $user ) :
                                $sel = ( $data && $data->client_id == $user->ID ) ? 'selected' : '';
                                
                                // On prépare le texte d'affichage
                                $display_text = esc_html( $user->last_name ) . ' ' . esc_html( $user->first_name ) . ' - (' . esc_html( $user->nickname ) . ')';
                                
                                echo '<option value="' . esc_attr( $user->ID ) . '" ' . $sel . '>' . $display_text . '</option>';
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
                        <?php echo $is_validated ? 'disabled' : ''; ?>>
                </div>

            </div>

            <hr class="pp-divider">

            <!-- ── SÉLECTION CLIENT ── -->
            <p class="pp-section-label">Sélection client</p>

            <?php if ( $is_validated ) : ?>

                <!-- NOUVEAU : bandeau "sélection confirmée" -->
                <div class="pp-validated-banner">
                    <span class="pp-validated-icon">✓</span>
                    <div>
                        <strong>Sélection confirmée par le client</strong>
                        <span><?php echo intval( $count_selection ); ?> photo(s) retenue(s)</span>
                    </div>
                    <a href="<?php echo esc_url( admin_url(
                        'admin-ajax.php?action=pp_export_selection&post_id=' . $post->ID . '&_wpnonce=' . $export_nonce
                    ) ); ?>" class="pp-btn pp-btn-primary pp-btn-sm" id="pp-btn-export">
                        ↓ Exporter CSV
                    </a>
                </div>

                <?php if ( ! empty( $selected_ids ) ) : ?>
                <!-- NOUVEAU : récap miniatures de la sélection -->
                <div class="pp-selection-recap">
                    <?php foreach ( $selected_ids as $att_id ) :
                        $file_path = get_attached_file( $att_id );
                        $filename  = $file_path ? basename( $file_path ) : '—';
                        ?>
                        <div class="pp-recap-thumb">
                            <?php echo wp_get_attachment_image( $att_id, 'thumbnail', false, array( 'class' => 'pp-recap-img' ) ); ?>
                            <span class="pp-recap-name"><?php echo esc_html( $filename ); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- NOUVEAU : boutons de réouverture -->
                <div class="pp-reopen-actions">
                    <p class="pp-section-label" style="margin-bottom: 10px;">Rouvrir la galerie</p>
                    <div class="pp-reopen-btns">
                        <button type="button" class="pp-btn pp-btn-sm pp-btn-reopen"
                            id="pp-btn-reopen-keep"
                            data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                            data-nonce="<?php echo esc_attr( $reopen_nonce ); ?>"
                            data-mode="keep">
                            Rouvrir — conserver la sélection
                        </button>
                        <button type="button" class="pp-btn pp-btn-sm pp-btn-reopen pp-btn-danger"
                            id="pp-btn-reopen-reset"
                            data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                            data-nonce="<?php echo esc_attr( $reopen_nonce ); ?>"
                            data-mode="reset">
                            Rouvrir — remettre à zéro
                        </button>
                    </div>
                    <p class="description" style="margin-top: 8px;">Le client pourra à nouveau modifier et reconfirmer sa sélection.</p>
                </div>

            <?php else : ?>

                <!-- Statut normal -->
                <div class="pp-sel-card">
                    <div class="pp-sel-count">
                        <strong id="pp-selection-count"><?php echo intval( $count_selection ); ?></strong>
                        photo(s) sélectionnée(s)
                    </div>
                    <a href="<?php echo esc_url( admin_url(
                        'admin-ajax.php?action=pp_export_selection&post_id=' . $post->ID . '&_wpnonce=' . $export_nonce
                    ) ); ?>" class="pp-btn pp-btn-primary pp-btn-sm" id="pp-btn-export">
                        ↓ Exporter CSV
                    </a>
                </div>

            <?php endif; ?>

            <hr class="pp-divider">

            <!-- ── OPTIONS ── -->
            <p class="pp-section-label">Options</p>
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

            <?php if ( ! $is_validated ) : ?>
            <hr class="pp-divider">

            <!-- ── MÉDIAS ── -->
            <p class="pp-section-label">Médias</p>
            <div class="pp-upload-zone" id="pp_upload_btn">
                <svg class="pp-upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                </svg>
                <div class="pp-upload-title">Ajouter des photos</div>
                <div class="pp-upload-sub">Cliquez pour parcourir ou glissez-déposez vos fichiers ici</div>
            </div>

            <div id="pp-gallery-preview" class="pp-gallery-grid"></div>
            <?php endif; ?>

            <?php if ( $data && $data->status === 'publie' ) : ?>
            <div class="pp-alert">
                <div class="pp-alert-dot"></div>
                <div class="pp-alert-text">
                    Galerie publiée — évitez de supprimer des photos pour ne pas invalider la sélection du client.
                </div>
            </div>
            <?php endif; ?>

        </div>

        <script>
        (function() {
            // Copie du lien
            var btn = document.getElementById('pp-copy-link-btn');
            if (btn) {
                btn.addEventListener('click', function() {
                    var url = btn.dataset.url;
                    var orig = btn.textContent;
                    function feedback() {
                        btn.textContent = 'Copié !';
                        btn.classList.add('pp-btn-success');
                        setTimeout(function() { btn.textContent = orig; btn.classList.remove('pp-btn-success'); }, 2000);
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

            // NOUVEAU : boutons de réouverture
            document.querySelectorAll('.pp-btn-reopen').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var mode    = btn.dataset.mode;
                    var postId  = btn.dataset.postId;
                    var nonce   = btn.dataset.nonce;
                    var label   = mode === 'reset' ? 'remettre à zéro la sélection' : 'conserver la sélection';

                    if (!confirm('Rouvrir la galerie en mode "' + label + '" ?\nLe client pourra à nouveau modifier sa sélection.')) {
                        return;
                    }

                    btn.textContent = 'En cours…';
                    btn.disabled = true;

                    var formData = new FormData();
                    formData.append('action',  'pp_reopen_gallery');
                    formData.append('post_id', postId);
                    formData.append('nonce',   nonce);
                    formData.append('mode',    mode);

                    fetch(ajaxurl, { method: 'POST', body: formData })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.success) {
                                // Recharger la page pour refléter le nouveau statut
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

// Vérifier si une ligne existe déjà
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

        update_post_meta(
            $post_id,
            '_pp_custom_rename',
            sanitize_text_field( $_POST['pp_custom_rename'] ?? '' )
        );
    }
}