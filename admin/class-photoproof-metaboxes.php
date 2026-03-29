<?php
/**
 * Gestion de l'interface d'édition de la galerie PhotoProof
 */
class PhotoProof_Metaboxes {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_gallery_settings_metabox' ) );
        add_action( 'save_post', array( $this, 'save_gallery_settings' ) );
    }

    public function add_gallery_settings_metabox() {
        add_meta_box(
            'pp_gallery_settings',
            'Configuration de la Galerie PhotoProof',
            array( $this, 'render_metabox' ),
            'pp_gallery',
            'normal',
            'high'
        );
    }

    public function render_metabox( $post ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'photoproof_galleries';
        
        // Récupération des données
        $data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE post_id = %d", $post->ID ) );
        
        // On récupère la sélection (vide ou non, on s'en fiche pour l'affichage du bouton)
        $selected_photos = get_post_meta( $post->ID, '_pp_selected_photos', true );
        $count_selection = (!empty($selected_photos) && is_array($selected_photos)) ? count($selected_photos) : 0;
        
        // URL de la galerie (même en brouillon)
        $gallery_url = get_permalink( $post->ID );

        wp_nonce_field( 'pp_save_gallery_settings', 'pp_gallery_nonce' );
        ?>

        <div class="pp-admin-wrapper">

            <div class="pp-share-banner">
                <label><strong>Lien de la galerie client :</strong></label>
                <div class="pp-url-group">
                    <input type="text" id="pp-direct-url" value="<?php echo esc_url($gallery_url); ?>" readonly>
                    <button type="button" class="button button-secondary" onclick="ppCopyLink()">Copier le lien</button>
                </div>

                <div class="pp-alert-guard">
                    <span class="dashicons dashicons-warning"></span>
                    <strong>Note de sécurité :</strong> Si la galerie est "Publiée", évitez de supprimer des photos pour ne pas casser la sélection du client.
                </div>
            </div>

            <div class="pp-status-bar">
                <div class="pp-field-group">
                    <label>Attribuer à un client</label>
                    <select name="pp_client_id" class="pp-select">
                        <option value="">-- Aucun client --</option>
                        <?php 
                        $users = get_users( array( 'role__in' => array( 'subscriber', 'customer', 'author', 'editor' ) ) );
                        foreach ( $users as $user ) : 
                            $selected = ( $data && $data->client_id == $user->ID ) ? 'selected' : '';
                            echo "<option value='{$user->ID}' $selected>{$user->display_name}</option>";
                        endforeach; ?>
                    </select>
                </div>
                
                <div class="pp-field-group">
                    <label>État de la galerie</label>
                    <select name="pp_status" class="pp-select">
                        <?php
                        $status_list = array(
                            'brouillon' => '📝 Brouillon (Privé)', 
                            'publie'    => '🌐 Publiée (Ouverte)', 
                            'valide'    => '✅ Sélection terminée', 
                            'ferme'     => '🔒 Archivée'
                        );
                        foreach ( $status_list as $key => $label ) :
                            $selected = ( $data && $data->status == $key ) ? 'selected' : '';
                            echo "<option value='$key' $selected>$label</option>";
                        endforeach; ?>
                    </select>
                </div>
                <div class="pp-field-group">
                    <label>Préfixe de renommage (Optionnel)</label>
                    <input type="text" name="pp_custom_rename" 
                        value="<?php echo esc_attr(get_post_meta($post->ID, '_pp_custom_rename', true)); ?>" 
                        placeholder="Ex: Bapteme-Leo">
                    <p class="description">Remplace le nom par défaut pour cette galerie uniquement.</p>
                </div>
                </div>

            <div class="pp-selection-card">
                <div class="pp-selection-header">
                    <div>
                        <strong>Sélection actuelle :</strong> <span id="pp-selection-count"><?php echo $count_selection; ?></span> photo(s).
                    </div>
                    <a href="<?php echo admin_url('admin-ajax.php?action=pp_export_selection&post_id='.$post->ID); ?>" class="button button-primary" id="pp-btn-export">
                        <span class="dashicons dashicons-download"></span> Exporter la liste (CSV/TXT)
                    </a>
                </div>
            </div>

            <div class="pp-watermark-toggle">
                <label class="pp-switch">
                    <?php $wm_active = ( $data && isset($data->watermark_settings) && $data->watermark_settings === 'yes' ) ? 'checked' : ''; ?>
                    <input type="checkbox" name="pp_watermark_active" value="yes" <?php echo $wm_active; ?>>
                    <span class="pp-slider"></span>
                </label>
                <span><strong>Protection Filigrane :</strong> Activer le logo sur les images de cette galerie.</span>
            </div>

            <div id="pp_upload_btn" class="pp-upload-zone">
                <span class="dashicons dashicons-cloud-upload"></span>
                <h3>Ajouter des photos à la galerie</h3>
                <p class="description">Glissez-déposez vos fichiers ici ou cliquez pour parcourir.</p>
            </div>
            
            <div id="pp-gallery-preview" class="pp-gallery-preview-grid">
                </div>
        </div>

        <script>
        function ppCopyLink() {
            var copyText = document.getElementById("pp-direct-url");
            copyText.select();
            document.execCommand("copy");
            
            // Petit feedback visuel rapide
            var btn = document.querySelector('.pp-url-group button');
            var originalText = btn.innerHTML;
            btn.innerHTML = "Copié !";
            btn.classList.add('button-primary');
            
            setTimeout(function(){
                btn.innerHTML = originalText;
                btn.classList.remove('button-primary');
            }, 2000);
        }
        </script>
        <?php
    }

    public function save_gallery_settings( $post_id ) {
        if ( ! isset( $_POST['pp_gallery_nonce'] ) || ! wp_verify_nonce( $_POST['pp_gallery_nonce'], 'pp_save_gallery_settings' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        global $wpdb;
        $table_name = $wpdb->prefix . 'photoproof_galleries';
        $watermark_val = isset($_POST['pp_watermark_active']) ? 'yes' : 'no';

        $wpdb->replace( $table_name, array(
            'post_id'            => $post_id,
            'client_id'          => intval( $_POST['pp_client_id'] ),
            'status'             => sanitize_text_field( $_POST['pp_status'] ),
            'watermark_settings' => $watermark_val,
            'folder_path'        => 'photoproof/gallery-' . $post_id
        ), array( '%d', '%d', '%s', '%s', '%s' ) );
    }
}