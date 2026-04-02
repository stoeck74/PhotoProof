<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * Gestion des emails — PhotoProof
 *
 * Déclenché quand un client valide sa sélection :
 * - Mail au photographe : liste des fichiers sélectionnés
 * - Mail au client : confirmation de réception
 */
class PhotoProof_Mailer {

    public function __construct() {
        // Déclenché depuis save_client_selection() après confirmation
        add_action( 'pp_gallery_selection_confirmed', array( $this, 'send_emails' ), 10, 2 );
    }

    /**
     * Envoie les deux emails de confirmation
     *
     * @param int $post_id      ID de la galerie
     * @param int $client_id    ID de l'utilisateur client (peut être 0 si non connecté)
     */
    public function send_emails( $post_id, $client_id ) {
        $gallery_title   = get_the_title( $post_id );
        $selected_ids    = get_post_meta( $post_id, '_pp_selected_photos', true );
        $selected_ids    = is_array( $selected_ids ) ? $selected_ids : array();
        $count           = count( $selected_ids );

        // Infos photographe
        $photographer_email = get_option( 'admin_email' );
        $photographer_name  = get_option( 'blogname' );

        // Infos client
        $client_name  = 'Client';
        $client_email = '';

        if ( $client_id ) {
            $client = get_userdata( $client_id );
            if ( $client ) {
                $client_name  = $client->display_name ?: $client->user_login;
                $client_email = $client->user_email;
            }
        }

        // Liste des fichiers sélectionnés
        $file_list = '';
        foreach ( $selected_ids as $att_id ) {
            $target   = get_post_meta( $att_id, '_pp_target_filename', true );
            $filename = $target ?: basename( get_attached_file( $att_id ) );
            $file_list .= '- ' . $filename . "\n";
        }

        // ── MAIL PHOTOGRAPHE ──────────────────────────────────────────
        $subject_photo = sprintf(
            '[PhotoProof] %s a validé la galerie "%s"',
            $client_name,
            $gallery_title
        );

        $body_photo  = sprintf( "Bonjour,\n\n" );
        $body_photo .= sprintf( "%s a confirmé sa sélection pour la galerie \"%s\".\n\n", $client_name, $gallery_title );
        $body_photo .= sprintf( "%d photo(s) sélectionnée(s) :\n", $count );
        $body_photo .= "--------------------------------------\n";
        $body_photo .= $file_list;
        $body_photo .= "--------------------------------------\n\n";
        $body_photo .= sprintf( "Voir la galerie : %s\n\n", get_permalink( $post_id ) );
        $body_photo .= "— PhotoProof";

        wp_mail( $photographer_email, $subject_photo, $body_photo );

        // ── MAIL CLIENT ───────────────────────────────────────────────
        if ( $client_email ) {
            $subject_client = sprintf(
                'Votre sélection pour "%s" a bien été reçue',
                $gallery_title
            );

            $body_client  = sprintf( "Bonjour %s,\n\n", $client_name );
            $body_client .= sprintf( "Nous avons bien reçu votre sélection de %d photo(s) pour la galerie \"%s\".\n\n", $count, $gallery_title );
            $body_client .= "Nous allons maintenant prendre en charge le traitement final de vos images retenues et reviendrons vers vous très prochainement.\n\n";
            $body_client .= "Merci pour votre confiance.\n\n";
            $body_client .= sprintf( "— %s", $photographer_name );

            wp_mail( $client_email, $subject_client, $body_client );
        }
    }
}