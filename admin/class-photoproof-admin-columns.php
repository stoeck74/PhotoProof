<?php
/**
 * Gestion des colonnes admin et publication automatique — PhotoProof
 *
 * - Publication WP → statut PhotoProof passe automatiquement à 'publie'
 * - Colonnes custom dans la liste des galeries : statut + nb photos
 */
class PhotoProof_Admin_Columns {

    public function __construct() {
        // Publication auto
        add_action( 'publish_pp_gallery', array( $this, 'auto_set_publie_on_publish' ), 10, 2 );

        // Colonnes custom
        add_filter( 'manage_pp_gallery_posts_columns',       array( $this, 'add_columns' ) );
        add_action( 'manage_pp_gallery_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
        add_filter( 'manage_edit-pp_gallery_sortable_columns', array( $this, 'sortable_columns' ) );
    }

    /**
     * Quand une galerie est publiée dans WP,
     * on passe automatiquement son statut PhotoProof à 'publie'
     * sauf si elle est déjà à 'valide' ou 'ferme' (on ne rétrograde pas)
     */
    public function auto_set_publie_on_publish( $post_id, $post ) {
        global $wpdb;
        $table = $wpdb->prefix . 'photoproof_galleries';

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, status FROM $table WHERE post_id = %d",
            $post_id
        ) );

        // Statuts qu'on ne touche pas (déjà avancés)
        $protected = array( 'valide', 'ferme' );

        if ( $existing ) {
            if ( ! in_array( $existing->status, $protected, true ) ) {
                $wpdb->update(
                    $table,
                    array( 'status' => 'publie' ),
                    array( 'post_id' => $post_id ),
                    array( '%s' ),
                    array( '%d' )
                );
            }
        } else {
            // Pas encore de ligne → on la crée
            $wpdb->insert(
                $table,
                array(
                    'post_id'     => $post_id,
                    'status'      => 'publie',
                    'folder_path' => 'photoproof/gallery-' . $post_id,
                ),
                array( '%d', '%s', '%s' )
            );
        }
    }

    /**
     * Ajoute les colonnes custom dans la liste WP admin
     */
    public function add_columns( $columns ) {
        // On réorganise pour mettre nos colonnes après le titre
        $new = array();
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'title' ) {
                $new['pp_status'] = 'État';
                $new['pp_photos'] = 'Photos';
                $new['pp_client'] = 'Client';
            }
        }
        return $new;
    }

    /**
     * Rendu des colonnes custom
     */
    public function render_column( $column, $post_id ) {
        global $wpdb;

        if ( $column === 'pp_status' ) {
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT status FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
                $post_id
            ) );

            $status = $row ? $row->status : null;

            $map = array(
                'brouillon' => array( 'icon' => '📝', 'label' => 'Brouillon',  'color' => '#94a3b8' ),
                'publie'    => array( 'icon' => '🌐', 'label' => 'Publiée',    'color' => '#3b82f6' ),
                'valide'    => array( 'icon' => '✅', 'label' => 'Validée',    'color' => '#22c55e' ),
                'ferme'     => array( 'icon' => '🔒', 'label' => 'Archivée',   'color' => '#6b7280' ),
            );

            if ( $status && isset( $map[ $status ] ) ) {
                $s = $map[ $status ];
                echo '<span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:500;color:' . esc_attr( $s['color'] ) . ';">'
                    . $s['icon'] . ' ' . esc_html( $s['label'] )
                    . '</span>';
            } else {
                echo '<span style="color:#94a3b8;font-size:12px;">—</span>';
            }
        }

        if ( $column === 'pp_photos' ) {
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_type = 'attachment'
                 AND post_status = 'inherit'
                 AND post_parent = %d",
                $post_id
            ) );

            $selected = get_post_meta( $post_id, '_pp_selected_photos', true );
            $nb_selected = is_array( $selected ) ? count( $selected ) : 0;

            echo '<span style="font-size:12px;color:#1e293b;font-weight:500;">'
                . intval( $count )
                . '</span>';

            if ( $nb_selected > 0 ) {
                echo '<span style="font-size:11px;color:#22c55e;margin-left:5px;">('
                    . $nb_selected . ' sél.)</span>';
            }
        }

        if ( $column === 'pp_client' ) {
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT client_id FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
                $post_id
            ) );

            if ( $row && $row->client_id ) {
                $user = get_userdata( $row->client_id );
                if ( $user ) {
                    echo '<span style="font-size:12px;color:#475569;">'
                        . esc_html( $user->display_name )
                        . '</span>';
                } else {
                    echo '<span style="color:#94a3b8;font-size:12px;">—</span>';
                }
            } else {
                echo '<span style="color:#94a3b8;font-size:12px;">—</span>';
            }
        }
    }

    /**
     * Rend la colonne statut triable
     */
    public function sortable_columns( $columns ) {
        $columns['pp_status'] = 'pp_status';
        return $columns;
    }
}