<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * Gestion de l'interface d'édition de la galerie PhotoProof
 * Metabox redesignée — états contextuels, hiérarchie visuelle
 */

// Protection accès direct
if ( ! defined( 'ABSPATH' ) ) exit;

class PhotoProof_Metaboxes {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_gallery_settings_metabox' ) );
        add_action( 'save_post',      array( $this, 'save_gallery_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_metabox_inline_script' ), 20 );
    }

    /**
     * Enqueue inline JS for the metabox — localized strings + logic
     */
    public function enqueue_metabox_inline_script( $hook ) {
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'photoproof_gallery' ) {
            return;
        }

        wp_localize_script( 'pp-gallery-js', 'pp_metabox_i18n', array(
            'copied'          => __( 'Copied !', 'photoproof' ),
            'reset_selection' => __( 'Reset selection', 'photoproof' ),
            'keep_selection'  => __( 'Keep selection', 'photoproof' ),
            'reopen_and'      => __( 'Reopen gallery and', 'photoproof' ),
            'client_select'   => __( 'Client will be able to select again', 'photoproof' ),
            'in_progress'     => __( 'In progress…', 'photoproof' ),
            'error'           => __( 'Error :', 'photoproof' ),
            'unknown'         => __( 'unknown', 'photoproof' ),
            'reopen_reset'    => __( 'Reopen — reset', 'photoproof' ),
            'reopen_keep'     => __( 'Reopen — Keep selection', 'photoproof' ),
        ) );

        $inline_js = "document.addEventListener('DOMContentLoaded', function() {
    function setupCopy(btnId) {
        var btn = document.getElementById(btnId);
        if (!btn) return;
        btn.addEventListener('click', function() {
            var url  = btn.dataset.url;
            var orig = btn.textContent;
            function feedback() {
                btn.textContent = pp_metabox_i18n.copied;
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

    document.querySelectorAll('.pp-btn-reopen').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var mode  = btn.dataset.mode;
            var label = mode === 'reset' ? pp_metabox_i18n.reset_selection : pp_metabox_i18n.keep_selection;
            if (!confirm(pp_metabox_i18n.reopen_and + ' \"' + label + '\" ?\\n' + pp_metabox_i18n.client_select)) return;
            btn.textContent = pp_metabox_i18n.in_progress;
            btn.disabled = true;
            var fd = new FormData();
            fd.append('action',  'photoproof_reopen_gallery');
            fd.append('post_id', btn.dataset.postId);
            fd.append('nonce',   btn.dataset.nonce);
            fd.append('mode',    mode);
            fetch(ajaxurl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(pp_metabox_i18n.error + ' ' + (data.data && data.data.message ? data.data.message : pp_metabox_i18n.unknown));
                        btn.disabled = false;
                        btn.textContent = mode === 'reset' ? pp_metabox_i18n.reopen_reset : pp_metabox_i18n.reopen_keep;
                    }
                });
        });
    });
});";

        wp_add_inline_script( 'pp-gallery-js', $inline_js );
    }

    public function add_gallery_settings_metabox() {
        add_meta_box(
            'photoproof_gallery_settings',
            'PhotoProof',
            array( $this, 'render_metabox' ),
            'photoproof_gallery',
            'normal',
            'high'
        );
    }

    public function render_metabox( $post ) {
        global $wpdb;

        // ── Requête avec cache ──
        $cache_key = 'photoproof_gallery_' . $post->ID;
        $data      = wp_cache_get( $cache_key, 'photoproof' );
        if ( false === $data ) {
            $data = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
                    $post->ID
                )
            );
            wp_cache_set( $cache_key, $data, 'photoproof', 300 );
        }

        $current_status = $data ? $data->status : 'brouillon';
        $is_new         = ( $post->post_status === 'auto-draft' || ! $data );
        $is_brouillon   = ( $current_status === 'brouillon' );
        $is_publie      = ( $current_status === 'publie' );
        $is_validated   = ( $current_status === 'valide' );
        $is_ferme       = ( $current_status === 'ferme' );
        $is_locked      = ( $is_validated || $is_ferme );

        $selected_photos = get_post_meta( $post->ID, '_photoproof_selected_photos', true );
        $selected_ids    = is_array( $selected_photos ) ? array_map( 'intval', $selected_photos ) : array();
        $count_selection = count( $selected_ids );

        $gallery_url  = get_permalink( $post->ID );
        $export_nonce = wp_create_nonce( 'photoproof_export_' . $post->ID );
        $reopen_nonce = wp_create_nonce( 'photoproof_reopen_' . $post->ID );

        // Config bande statut
        $status_config = array(
            'brouillon' => array(
                'label' => __( 'Draft — Private', 'photoproof' ),
                'sub'   => __( 'Not visible to the client', 'photoproof' ),
                'class' => 'pp-state-brouillon',
            ),
            'publie'    => array(
                'label' => __( 'Published — Open', 'photoproof' ),
                'sub'   => __( 'Waiting for client selection', 'photoproof' ),
                'class' => 'pp-state-publie',
            ),
            'valide'    => array(
                'label' => __( 'Selection Confirmed', 'photoproof' ),
                'sub'   => __( 'The client has validated his selection', 'photoproof' ),
                'class' => 'pp-state-valide',
            ),
            'ferme'     => array(
                'label' => __( 'Archived', 'photoproof' ),
                'sub'   => __( 'Client access disabled', 'photoproof' ),
                'class' => 'pp-state-ferme',
            ),
        );
        $sc = $status_config[ $current_status ] ?? $status_config['brouillon'];

        wp_nonce_field( 'photoproof_save_gallery_settings', 'photoproof_gallery_nonce' );
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
                        <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=photoproof_export_selection&post_id=' . $post->ID . '&_wpnonce=' . $export_nonce ) ); ?>"
                           class="pp-btn pp-btn-sm pp-btn-state-action">
                            <?php esc_html_e( '↓ Export CSV', 'photoproof' ); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ( ! $is_new && ! $is_brouillon ) : ?>
                        <a href="<?php echo esc_url( $gallery_url ); ?>" target="_blank"
                           class="pp-btn pp-btn-sm pp-btn-ghost">
                            <?php esc_html_e( 'View Gallery ↗', 'photoproof' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── URL ── -->
            <div class="pp-url-row">
                <span class="pp-url-label"><?php esc_html_e( 'URL', 'photoproof' ); ?></span>
                <div class="pp-url-value" title="<?php echo esc_attr( $gallery_url ); ?>">
                    <?php echo esc_html( $gallery_url ); ?>
                </div>
                <button type="button" class="pp-btn pp-btn-sm pp-btn-ghost" id="pp-copy-link-btn-2"
                    data-url="<?php echo esc_attr( $gallery_url ); ?>">
                    <?php esc_html_e( 'URL / Copy', 'photoproof' ); ?>
                </button>
            </div>

            <div class="pp-meta-body">

                <!-- ── CONFIGURATION ── -->
                <div class="pp-meta-section">
                    <div class="pp-meta-section-title">
                        <?php esc_html_e( 'Configuration', 'photoproof' ); ?>
                    </div>
                    <div class="pp-fields-row">

                        <div class="pp-field">
                            <label for="pp_client_id">
                                <?php esc_html_e( 'Assigned Client', 'photoproof' ); ?>
                            </label>
                            <select name="pp_client_id" id="pp_client_id" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                <option value=""><?php esc_html_e( '— No client selected —', 'photoproof' ); ?></option>
                                <?php
                                $users = get_users( array( 'role__in' => array( 'subscriber', 'customer', 'author', 'editor', 'administrator' ) ) );
                                foreach ( $users as $user ) :
                                    $is_selected = ( $data && (int) $data->client_id === (int) $user->ID );
                                    $name        = trim( $user->last_name . ' ' . $user->first_name );
                                    $name        = $name ?: $user->display_name;
                                    ?>
                                    <option value="<?php echo esc_attr( $user->ID ); ?>"
                                        <?php selected( $is_selected, true ); ?>>
                                        <?php echo esc_html( $name . ' (' . $user->user_login . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="pp-field">
                            <label for="pp_status">
                                <?php esc_html_e( 'Status', 'photoproof' ); ?>
                            </label>
                            <select name="pp_status" id="pp_status">
                                <?php
                                $status_list = array(
                                    'brouillon' => __( '📝 Draft (Private)', 'photoproof' ),
                                    'publie'    => __( '🌐 Published (Open)', 'photoproof' ),
                                    'valide'    => __( '✅ Proofed by client (closed)', 'photoproof' ),
                                    'ferme'     => __( '🔒 Archived (hiden)', 'photoproof' ),
                                );
                                foreach ( $status_list as $key => $label ) :
                                    ?>
                                    <option value="<?php echo esc_attr( $key ); ?>"
                                        <?php selected( $data && $data->status === $key, true ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="pp-field">
                            <label for="pp_custom_rename">
                                <?php esc_html_e( 'Custom Prefix', 'photoproof' ); ?>
                            </label>
                            <input type="text"
                                name="pp_custom_rename"
                                id="pp_custom_rename"
                                value="<?php echo esc_attr( get_post_meta( $post->ID, '_photoproof_custom_rename', true ) ); ?>"
                                placeholder="<?php esc_attr_e( 'e.g.: Smith-Wedding', 'photoproof' ); ?>"
                                <?php echo $is_locked ? 'disabled' : ''; ?>>
                        </div>

                    </div>
                </div>

                <!-- ── SÉLECTION CLIENT ── -->
                <?php if ( ! $is_brouillon ) : ?>
                <div class="pp-meta-section">
                    <div class="pp-meta-section-title">
                        <?php esc_html_e( 'CLIENT SELECTION', 'photoproof' ); ?>
                    </div>

                    <?php if ( $is_validated ) : ?>

                        <div class="pp-validated-banner">
                            <span class="pp-validated-icon">✓</span>
                            <div>
                                <strong><?php esc_html_e( 'Approved selection by client', 'photoproof' ); ?></strong>
                                <span>
                                    <?php
                                        printf(
                                            esc_html(
                                                // translators: %d: number of photos selected by the client
                                                _n(
                                                    '%d photo selected',
                                                    '%d photos selected',
                                                    $count_selection,
                                                    'photoproof'
                                                )
                                            ),
                                            intval( $count_selection )
                                        );
                                    ?>
                               </span>
                            </div>
                        </div>

                        <?php if ( ! empty( $selected_ids ) ) : ?>
                        <div class="pp-selection-recap">
                            <?php foreach ( $selected_ids as $att_id ) :
                                $target   = get_post_meta( $att_id, '_photoproof_target_filename', true );
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
                            <p class="pp-meta-section-title" style="margin-bottom:10px;">
                                <?php esc_html_e( 'Reopen gallery', 'photoproof' ); ?>
                            </p>
                            <div class="pp-reopen-btns">
                                <button type="button" class="pp-btn pp-btn-sm pp-btn-reopen"
                                    data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                                    data-nonce="<?php echo esc_attr( $reopen_nonce ); ?>"
                                    data-mode="keep">
                                    <?php esc_html_e( 'Reopen — with client previous selection', 'photoproof' ); ?>
                                </button>
                                <button type="button" class="pp-btn pp-btn-sm pp-btn-reopen pp-btn-danger"
                                    data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                                    data-nonce="<?php echo esc_attr( $reopen_nonce ); ?>"
                                    data-mode="reset">
                                    <?php esc_html_e( 'Reopen — with deleted previous selection', 'photoproof' ); ?>
                                </button>
                            </div>
                        </div>

                    <?php else : ?>

                        <div class="pp-sel-card">
                            <div class="pp-sel-count">
                                <strong id="pp-selection-count"><?php echo intval( $count_selection ); ?></strong>
                                <span><?php esc_html_e( 'Selected photography', 'photoproof' ); ?></span>
                            </div>
                            <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=photoproof_export_selection&post_id=' . $post->ID . '&_wpnonce=' . $export_nonce ) ); ?>"
                               class="pp-btn pp-btn-sm pp-btn-primary">
                                <?php esc_html_e( '↓ Export CSV', 'photoproof' ); ?>
                            </a>
                        </div>

                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- ── OPTIONS FILIGRANE ── -->
                <?php if ( ! $is_locked ) : ?>
                <div class="pp-meta-section">
                    <div class="pp-meta-section-title">
                        <?php esc_html_e( 'Options', 'photoproof' ); ?>
                    </div>
                    <div class="pp-toggle-row">
                        <label class="pp-switch">
                            <?php $wm_active = ( $data && isset( $data->watermark_settings ) && $data->watermark_settings === 'yes' ); ?>
                            <input type="checkbox" name="pp_watermark_active" value="yes"
                                <?php checked( $wm_active, true ); ?>>
                            <span class="pp-slider"></span>
                        </label>
                        <div class="pp-toggle-text">
                            <span class="pp-toggle-title">
                                <?php esc_html_e( 'Watermark protection', 'photoproof' ); ?>
                            </span>
                            <span class="pp-toggle-sub">
                                <?php esc_html_e( 'Applies the watermark on all images uploaded', 'photoproof' ); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ── MÉDIAS ── -->
                <?php if ( ! $is_locked ) : ?>
                <div class="pp-meta-section">
                    <div class="pp-meta-section-title">
                        <?php esc_html_e( 'Files', 'photoproof' ); ?>
                    </div>
                    <div class="pp-upload-zone" id="pp_upload_btn">
                        <svg class="pp-upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                        </svg>
                        <div class="pp-upload-title">
                            <?php echo $is_new
                                ? esc_html__( 'Upload media', 'photoproof' )
                                : esc_html__( 'Add images', 'photoproof' );
                            ?>
                        </div>
                        <div class="pp-upload-sub">
                            <?php echo $is_new
                                ? esc_html__( 'Publish or save the draft before uploading to see images name updated', 'photoproof' )
                                : esc_html__( 'Click to browse or drag and drop your files here', 'photoproof' );
                            ?>
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
                        <?php esc_html_e( "Gallery published — avoid deleting photos to prevent invalidating the client's selection.", 'photoproof' ); ?>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /.pp-meta-body -->
        </div><!-- /.pp-meta -->


        <?php
    }

    public function save_gallery_settings( $post_id ) {
        if ( ! isset( $_POST['photoproof_gallery_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['photoproof_gallery_nonce'] ) ), 'photoproof_save_gallery_settings' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        global $wpdb;
        $table_name = $wpdb->prefix . 'photoproof_galleries';

        $allowed_statuses = array( 'brouillon', 'publie', 'valide', 'ferme' );
        $status_raw       = isset( $_POST['pp_status'] ) ? sanitize_text_field( wp_unslash( $_POST['pp_status'] ) ) : 'brouillon';
        $status           = in_array( $status_raw, $allowed_statuses, true ) ? $status_raw : 'brouillon';
        $watermark_val    = isset( $_POST['pp_watermark_active'] ) ? 'yes' : 'no';
        $client_id        = ( isset( $_POST['pp_client_id'] ) && $_POST['pp_client_id'] !== '' ) ? intval( $_POST['pp_client_id'] ) : null;

        // ── Requête avec cache ──
        $cache_key = 'photoproof_gallery_' . $post_id;
        $existing  = wp_cache_get( $cache_key . '_id', 'photoproof' );
        if ( false === $existing ) {
            $existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
                    $post_id
                )
            );
            wp_cache_set( $cache_key . '_id', $existing, 'photoproof', 300 );
        }

        $row_data   = array(
            'client_id'          => $client_id,
            'status'             => $status,
            'watermark_settings' => $watermark_val,
            'folder_path'        => 'photoproof/gallery-' . $post_id,
        );
        $row_format = array( '%d', '%s', '%s', '%s' );

        if ( $existing ) {
            $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $table_name,
                $row_data,
                array( 'post_id' => $post_id ),
                $row_format,
                array( '%d' )
            );
        } else {
            $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $table_name,
                array_merge( array( 'post_id' => $post_id ), $row_data ),
                array_merge( array( '%d' ), $row_format )
            );
        }

        // Invalider le cache après écriture
        wp_cache_delete( $cache_key, 'photoproof' );
        wp_cache_delete( $cache_key . '_id', 'photoproof' );

        update_post_meta( $post_id, '_photoproof_custom_rename', sanitize_text_field( wp_unslash( $_POST['pp_custom_rename'] ?? '' ) ) );
    }
}