<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * Logique d'expiration automatique des galeries — PhotoProof
 *
 * - Vérification d'accès front-end (template_redirect)
 * - Cron WP quotidien pour l'auto-archivage en base
 * - Bannière admin gérée directement dans le template (single-photoproof_gallery.php)
 *
 * Le cron est planifié dans PhotoProof::activate()
 * et supprimé dans PhotoProof::deactivate()
 */
class PhotoProof_Expiration {

    // Durée fixe : 30 jours
    const EXPIRATION_DAYS = 30;

    public function __construct() {
        // Vérification d'accès front-end
        add_action( 'template_redirect', array( $this, 'check_gallery_expiration' ) );

        // Cron quotidien d'auto-archivage
        add_action( 'photoproof_daily_expiration_check', array( $this, 'auto_archive_expired_galleries' ) );
    }

    /**
     * Retourne le timestamp d'expiration d'un post
     */
    private function get_expiration_timestamp( $post_id ) {
        $publish_timestamp = get_post_timestamp( $post_id );
        return $publish_timestamp + ( self::EXPIRATION_DAYS * DAY_IN_SECONDS );
    }

    /**
     * Vérifie si une galerie est expirée ou fermée et bloque l'accès
     *
     * Centralise toutes les vérifications d'accès front-end :
     * - Statut brouillon → admin only
     * - Statut fermé → admin only
     * - Expiration par date → admin only
     *
     * L'admin peut toujours voir — la bannière d'avertissement est
     * gérée dans le template (single-photoproof_gallery.php) via photoproof_get_gallery_access_notice()
     */
    public function check_gallery_expiration() {
        if ( ! is_singular( 'photoproof_gallery' ) ) {
            return;
        }

        global $post, $wpdb;

        // ── 1. VÉRIFICATION DU STATUT EN BASE ────────────────────────
        $row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT status FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
            $post->ID
        ) );

        if ( $row ) {
            if ( $row->status === 'brouillon' ) {
                if ( ! current_user_can( 'manage_options' ) ) {
                    wp_die(
                        '<h1>' . esc_html__( 'Gallery not available', 'photoproof' ) . '</h1>'
                        . '<p>' . esc_html__( 'This gallery is not published yet.', 'photoproof' ) . '</p>',
                        esc_html__( 'Access Denied', 'photoproof' ),
                        array( 'response' => 403 )
                    );
                }
                return; // Admin peut voir
            }

            if ( $row->status === 'ferme' ) {
                if ( ! current_user_can( 'manage_options' ) ) {
                    wp_die(
                        '<h1>' . esc_html__( 'Archived Gallery', 'photoproof' ) . '</h1>'
                        . '<p>' . esc_html__( 'Please contact your photographer.', 'photoproof' ) . '</p>',
                        esc_html__( 'Archived Gallery', 'photoproof' ),
                        array( 'response' => 403 )
                    );
                }
                return; // Admin peut voir
            }
        }

        // ── 2. VÉRIFICATION DE L'EXPIRATION PAR DATE ─────────────────
        if ( ! get_option( 'photoproof_enable_expiration' ) ) {
            return;
        }

        $expiration_date = $this->get_expiration_timestamp( $post->ID );

        if ( time() <= $expiration_date ) {
            return; // Pas encore expirée
        }

        // Expirée — mettre à jour le statut en base (filet de sécurité si le cron n'a pas tourné)
        if ( $row && $row->status === 'publie' ) {
            $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prefix . 'photoproof_galleries',
                array( 'status' => 'ferme' ),
                array( 'post_id' => $post->ID ),
                array( '%s' ),
                array( '%d' )
            );
        }

        // Admin peut voir
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }

        // Client : accès coupé
        wp_die(
            '<h1>' . esc_html__( 'Access Expired', 'photoproof' ) . '</h1>'
            . '<p>' . sprintf(
                /* translators: %d: number of days */
                esc_html__( 'Access to this gallery has expired (limit of %d days exceeded). Please contact your photographer to renew access.', 'photoproof' ),
                absint( self::EXPIRATION_DAYS )
            ) . '</p>',
            esc_html__( 'Gallery Expired', 'photoproof' ),
            array( 'response' => 403 )
        );
    }

    /**
     * Cron quotidien : archive automatiquement les galeries expirées
     */
    public function auto_archive_expired_galleries() {
        if ( ! get_option( 'photoproof_enable_expiration' ) ) {
            return;
        }

        global $wpdb;

        $galleries = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT post_id FROM {$wpdb->prefix}photoproof_galleries WHERE status = 'publie'"
        );

        if ( empty( $galleries ) ) {
            return;
        }

        $expiration_delay = self::EXPIRATION_DAYS * DAY_IN_SECONDS;

        foreach ( $galleries as $gallery ) {
            $post_id           = intval( $gallery->post_id );
            $publish_timestamp = get_post_timestamp( $post_id );

            if ( ! $publish_timestamp ) {
                continue;
            }

            if ( time() > ( $publish_timestamp + $expiration_delay ) ) {
                $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->prefix . 'photoproof_galleries',
                    array( 'status' => 'ferme' ),
                    array( 'post_id' => $post_id ),
                    array( '%s' ),
                    array( '%d' )
                );
            }
        }
    }

    /**
     * Helper statique : retourne le message de bannière admin à afficher
     * dans le template, ou null si rien à afficher.
     *
     * Appelé depuis single-photoproof_gallery.php
     *
     * @param int $post_id
     * @return string|null Message HTML ou null
     */
    public static function get_admin_notice( $post_id ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT status FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
            $post_id
        ) );

        if ( ! $row ) {
            return null;
        }

        if ( $row->status === 'ferme' ) {
            return sprintf(
                /* translators: admin notice for archived gallery */
                __( 'This gallery is <strong>archived</strong>. The client no longer has access.', 'photoproof' )
            );
        }

        if ( $row->status === 'brouillon' ) {
            return sprintf(
                /* translators: admin notice for draft gallery */
                __( 'This gallery is a <strong>draft</strong>. Only you can see it.', 'photoproof' )
            );
        }

        // Vérifier expiration par date
        if ( get_option( 'photoproof_enable_expiration' ) ) {
            $publish_timestamp = get_post_timestamp( $post_id );
            $expiration        = $publish_timestamp + ( self::EXPIRATION_DAYS * DAY_IN_SECONDS );

            if ( time() > $expiration ) {
                return sprintf(
                    /* translators: %d: number of days, admin notice for expired gallery */
                    __( 'This gallery is <strong>expired</strong> for the client (more than %d days). You can see it because you are an Administrator.', 'photoproof' ),
                    absint( self::EXPIRATION_DAYS )
                );
            }
        }

        return null;
    }
}
