<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Management of admin columns and automatic publication — PhotoProof
 *
 * - WP Publication → PhotoProof status automatically switches to 'publie'
 * - Custom columns in the gallery list: status + number of photos
 */
class PhotoProof_Admin_Columns {

    public function __construct() {
        // Auto publication
        add_action( 'publish_photoproof_gallery', array( $this, 'auto_set_publie_on_publish' ), 10, 2 );

        // Custom columns
        add_filter( 'manage_photoproof_gallery_posts_columns',       array( $this, 'add_columns' ) );
        add_action( 'manage_photoproof_gallery_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
        add_filter( 'manage_edit-photoproof_gallery_sortable_columns', array( $this, 'sortable_columns' ) );
    }

    /**
     * When a gallery is published in WP,
     * we automatically switch its PhotoProof status to 'publie'
     * unless it's already 'valide' or 'ferme' (we don't downgrade)
     */
    public function auto_set_publie_on_publish( $post_id, $post ) {
        global $wpdb;
        $existing = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT id, status FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
            $post_id
        ) );

        // Technical status - DO NOT TRANSLATE (DB Keys)
        $protected = array( 'valide', 'ferme' );

        if ( $existing ) {
            if ( ! in_array( $existing->status, $protected, true ) ) {
                $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    $wpdb->prefix . 'photoproof_galleries',
                    array( 'status' => 'publie' ),
                    array( 'post_id' => $post_id ),
                    array( '%s' ),
                    array( '%d' )
                );
            }
        } else {
            // No row yet → create it
            $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prefix . 'photoproof_galleries',
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
     * Adds custom columns to the WP admin list
     */
    public function add_columns( $columns ) {
        $new = array();
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'title' ) {
                $new['photoproof_status'] = __( 'Status', 'photoproof' );
                $new['photoproof_photos'] = __( 'Photos', 'photoproof' );
                $new['photoproof_client'] = __( 'Client', 'photoproof' );
            }
        }
        return $new;
    }

    /**
     * Custom columns rendering
     */
    public function render_column( $column, $post_id ) {
        global $wpdb;

        if ( $column === 'photoproof_status' ) {
            $row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT status FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
                $post_id
            ) );

            $status = $row ? $row->status : null;

            // Mapping: Technical Key => Visual Label (to be translated)
            $map = array(
                'brouillon' => array( 'icon' => '📝', 'label' => __( 'Draft', 'photoproof' ),     'color' => '#94a3b8' ),
                'publie'    => array( 'icon' => '🌐', 'label' => __( 'Published', 'photoproof' ), 'color' => '#3b82f6' ),
                'valide'    => array( 'icon' => '✅', 'label' => __( 'Validated', 'photoproof' ), 'color' => '#22c55e' ),
                'ferme'     => array( 'icon' => '🔒', 'label' => __( 'Archived', 'photoproof' ),  'color' => '#6b7280' ),
            );

            if ( $status && isset( $map[ $status ] ) ) {
                $s = $map[ $status ];
                echo '<span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:500;color:' . esc_attr( $s['color'] ) . ';">'
                    . esc_html($s['icon'] ). ' ' . esc_html( $s['label'] )
                    . '</span>';
            } else {
                echo '<span style="color:#94a3b8;font-size:12px;">—</span>';
            }
        }

        if ( $column === 'photoproof_photos' ) {
            $count = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_type = 'attachment'
                 AND post_status = 'inherit'
                 AND post_parent = %d",
                $post_id
            ) );

            $selected = get_post_meta( $post_id, '_photoproof_selected_photos', true );
            $nb_selected = is_array( $selected ) ? count( $selected ) : 0;

            echo '<span style="font-size:12px;color:#1e293b;font-weight:500;">'
                . intval( $count )
                . '</span>';

            if ( $nb_selected > 0 ) {
                echo '<span style="font-size:11px;color:#22c55e;margin-left:5px;">('
                    . esc_html( $nb_selected ) . ' ' . esc_html__( 'sel.', 'photoproof' ) . ')</span>';
            }
        }

        if ( $column === 'photoproof_client' ) {
            $row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
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
     * Make status column sortable
     */
    public function sortable_columns( $columns ) {
        $columns['photoproof_status'] = 'photoproof_status';
        return $columns;
    }
}