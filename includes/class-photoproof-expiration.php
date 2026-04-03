<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * Logique d'expiration automatique des galeries — PhotoProof
 *
 * CORRECTIONS :
 * - get_post_timestamp() au lieu de get_the_date('U') (timestamp fiable)
 * - Bannière admin via hook tardif (wp_body_open / admin_bar) au lieu de echo avant DOCTYPE
 * - Vérification du statut en base (brouillon, ferme) en plus de la date
 * - Cron WP quotidien pour l'auto-archivage en base
 * - register_activation_hook et deactivation_hook pour le cron
 */
class PhotoProof_Expiration {

    // Durée fixe : 30 jours
    const EXPIRATION_DAYS = 30;

    public function __construct() {
      // Vérification d'accès front-end
        add_action( 'template_redirect', array( $this, 'check_gallery_expiration' ) );

        // Bannière admin (affichée dans le contenu, pas avant le DOCTYPE)
        add_action( 'wp_before_admin_bar_render', array( $this, 'maybe_show_expired_banner' ) );

        // Cron quotidien d'auto-archivage
        add_action( 'pp_daily_expiration_check', array( $this, 'auto_archive_expired_galleries' ) );

        // Enregistrement du cron à l'activation (via photoproof.php activate())
        add_action( 'pp_schedule_cron', array( $this, 'schedule_cron' ) );

        // Nettoyage du cron à la désactivation
        register_deactivation_hook(
            defined( 'PHOTOPROOF_PATH' ) ? PHOTOPROOF_PATH . '../photoproof.php' : __FILE__,
            array( $this, 'clear_cron' )
        );
    }

    /**
     * Planifie le cron quotidien si pas déjà actif
     * Appelé depuis PhotoProof::activate()
     */
    public function schedule_cron() {
        if ( ! wp_next_scheduled( 'pp_daily_expiration_check' ) ) {
            wp_schedule_event( time(), 'daily', 'pp_daily_expiration_check' );
        }
    }

    /**
     * Supprime le cron à la désactivation du plugin
     */
    public function clear_cron() {
        $timestamp = wp_next_scheduled( 'pp_daily_expiration_check' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'pp_daily_expiration_check' );
        }
    }

    /**
     * Retourne le timestamp d'expiration d'un post
     */
    private function get_expiration_timestamp( $post_id ) {
        // CORRECTION : get_post_timestamp() (WP 5.3+) retourne un timestamp UTC fiable
        // Contrairement à get_the_date('U') qui passe par des filtres et peut dériver
        $publish_timestamp = get_post_timestamp( $post_id );
        return $publish_timestamp + ( self::EXPIRATION_DAYS * DAY_IN_SECONDS );
    }

    /**
     * Vérifie si une galerie est expirée ou fermée et bloque l'accès
     *
     * CORRECTION : vérifie aussi le statut en base (brouillon, ferme)
     * CORRECTION : la bannière admin n'est plus affichée ici (echo avant DOCTYPE)
     *              → gérée via maybe_show_expired_banner() sur wp_before_admin_bar_render
     */
    public function check_gallery_expiration() {
        if ( ! is_singular( 'pp_gallery' ) ) {
            return;
        }

        global $post, $wpdb;

        // ── 1. VÉRIFICATION DU STATUT EN BASE ────────────────────────
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}photoproof_galleries WHERE post_id = %d",
            $post->ID
        ) );

        if ( $row ) {
            if ( $row->status === 'brouillon' ) {
                // Brouillon : seul l'admin peut voir
                if ( ! current_user_can( 'manage_options' ) ) {
                    wp_die(
                        '<h1>Galerie non disponible</h1><p>Cette galerie n\'est pas encore publiée.</p>',
                        'Accès refusé',
                        array( 'response' => 403 )
                    );
                }
                return;
            }

            if ( $row->status === 'ferme' ) {
                if ( current_user_can( 'manage_options' ) ) {
                    // Admin : on stocke un flag pour la bannière
                    set_transient( 'pp_admin_notice_' . get_current_user_id(), 'ferme', 60 );
                    return;
                }
                wp_die(
                    '<h1>Galerie archivée</h1><p>Cette galerie a été fermée. Veuillez contacter votre photographe.</p>',
                    'Galerie archivée',
                    array( 'response' => 403 )
                );
            }
        }

        // ── 2. VÉRIFICATION DE L'EXPIRATION PAR DATE ─────────────────
        if ( ! get_option( 'pp_enable_expiration' ) ) {
            return;
        }

        $expiration_date = $this->get_expiration_timestamp( $post->ID );

        if ( time() <= $expiration_date ) {
            return; // Pas encore expirée
        }

        // Expirée — admin peut voir avec bannière
        if ( current_user_can( 'manage_options' ) ) {
            set_transient( 'pp_admin_notice_' . get_current_user_id(), 'expired', 60 );
            return;
        }

        // Client : accès coupé
        wp_die(
            wp_kses(
                '<h1>Accès expiré</h1><p>L\'accès à cette galerie est expiré (limite de '
                    . self::EXPIRATION_DAYS
                    . ' jours dépassée). Veuillez contacter votre photographe pour renouveler l\'accès.</p>',
                array(
                    'h1' => array(),
                    'p'  => array(),
                )
            ),
            'Galerie expirée',
            array( 'response' => 403 )
        );
    }

    /**
     * Affiche la bannière admin APRÈS le rendu de l'admin bar
     *
     * CORRECTION : on n'est plus dans template_redirect → pas de echo avant DOCTYPE
     * On utilise un transient pour passer l'info entre les deux hooks
     */
    public function maybe_show_expired_banner() {
        if ( ! is_singular( 'pp_gallery' ) ) {
            return;
        }

        $notice_key = 'pp_admin_notice_' . get_current_user_id();
        $notice     = get_transient( $notice_key );

        if ( ! $notice ) {
            return;
        }

        delete_transient( $notice_key );

        $messages = array(
            'expired' => 'Cette galerie est <strong>expirée</strong> pour le client (plus de '
                . self::EXPIRATION_DAYS . ' jours). Vous la voyez car vous êtes Administrateur.',
            'ferme'   => 'Cette galerie est <strong>archivée</strong>. Le client n\'y a plus accès.',
        );

        $message = isset( $messages[ $notice ] ) ? $messages[ $notice ] : '';

        if ( $message ) {
            // Ce hook s'exécute dans le <head> de l'admin bar — on injecte du CSS inline
            echo '<style>
                #pp-expiration-banner {
                    position: fixed; top: 32px; left: 0; right: 0; z-index: 99999;
                    background: #ffb900; color: #1e1e1e;
                    padding: 10px 20px; text-align: center;
                    font-size: 13px; font-weight: 500;
                    border-bottom: 2px solid #e09800;
                }
            </style>
            <div id="pp-expiration-banner">' . wp_kses( $message, array( 'strong' => array() ) ) . '</div>';
        }
    }

    /**
     * Cron quotidien : archive automatiquement les galeries expirées
     *
     * CORRECTION : met à jour le statut en base au lieu de bloquer uniquement à la volée
     */
    public function auto_archive_expired_galleries() {
        if ( ! get_option( 'pp_enable_expiration' ) ) {
            return;
        }

        global $wpdb;

        // Récupère toutes les galeries publiées
        $galleries = $wpdb->get_results(
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
                // Archivage en base
                $wpdb->update(
                    $wpdb->prefix . 'photoproof_galleries',
                    array( 'status' => 'ferme' ),
                    array( 'post_id' => $post_id ),
                    array( '%s' ),
                    array( '%d' )
                );
            }
        }
    }
}