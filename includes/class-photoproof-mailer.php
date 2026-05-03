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
 *
 * Templates éditables via Settings > Emails
 * Variables : {client_name}, {gallery_title}, {count}, {file_list}, {gallery_url}, {studio_name}
 */
class PhotoProof_Mailer {

    public function __construct() {
        add_action( 'photoproof_gallery_selection_confirmed', array( $this, 'send_emails' ), 10, 2 );
    }

    /**
     * Remplace les variables {placeholder} dans un template
     */
    private function parse_template( $template, $vars ) {
        foreach ( $vars as $key => $value ) {
            $template = str_replace( '{' . $key . '}', $value, $template );
        }
        return $template;
    }

    /**
     * Retourne les headers HTML pour wp_mail
     */
    private function get_html_headers() {
        return array( 'Content-Type: text/html; charset=UTF-8' );
    }

    /**
     * Génère le wrapper HTML commun (header + footer)
     */
    private function wrap_html( $body_content, $accent_color = '#2271b1' ) {
        $logo_id    = get_option( 'photoproof_custom_logo' );
        $studio     = esc_html( get_option( 'photoproof_custom_title', get_option( 'blogname' ) ) );
        $logo_html  = '';

        if ( $logo_id ) {
            $logo_url  = wp_get_attachment_image_url( $logo_id, 'medium' );
            $logo_html = '<img src="' . esc_url( $logo_url ) . '" alt="' . $studio . '" style="max-height:50px; max-width:160px;">';
        } else {
            $logo_html = '<span style="color:#ffffff; font-size:18px; font-weight:600;">' . $studio . '</span>';
        }

        return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0; padding:0; background:#f5f4f2; font-family:-apple-system,BlinkMacSystemFont,arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f4f2; padding:40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; overflow:hidden; max-width:600px;">

    <!-- HEADER -->
    <tr><td style="background:#1e293b; padding:28px 40px; text-align:center;">' . $logo_html . '</td></tr>
    <tr><td style="background:' . esc_attr( $accent_color ) . '; height:3px;"></td></tr>

    <!-- BODY -->
    <tr><td style="padding:40px;">' . $body_content . '</td></tr>

    <!-- FOOTER -->
    <tr><td style="padding:20px 40px; border-top:1px solid #e2e8f0; text-align:center;">
        <p style="margin:0; font-size:11px; color:#94a3b8;">' . $studio . ' — PhotoProof</p>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>';
    }

/**
     * Génère la liste des fichiers en HTML (avec commentaire éventuel sous le filename)
     */
    private function build_file_list_html( $att_ids ) {
        $comments_enabled = (bool) get_option( 'photoproof_enable_comments' );
        $lines = '';
        foreach ( $att_ids as $att_id ) {
            $target   = get_post_meta( $att_id, '_photoproof_target_filename', true );
            $filename = $target ?: basename( get_attached_file( $att_id ) );
            $comment  = $comments_enabled ? trim( (string) get_post_meta( $att_id, '_photoproof_comment', true ) ) : '';

            $lines .= '<tr><td style="font-size:13px; color:#475569; font-family:monospace; padding:4px 0;">' . esc_html( $filename );
            if ( '' !== $comment ) {
                $lines .= '<div style="font-family:inherit; font-size:12px; color:#1e293b; margin-top:4px; padding:6px 10px; background:#fff; border-left:3px solid #2271b1; border-radius:2px;">' . esc_html( $comment ) . '</div>';
            }
            $lines .= '</td></tr>';
        }
        return $lines;
    }

/**
     * Génère la liste des fichiers en texte brut (pour {file_list})
     */
    private function build_file_list_text( $att_ids ) {
        $comments_enabled = (bool) get_option( 'photoproof_enable_comments' );
        $list = '';
        foreach ( $att_ids as $att_id ) {
            $target   = get_post_meta( $att_id, '_photoproof_target_filename', true );
            $filename = $target ?: basename( get_attached_file( $att_id ) );
            $comment  = $comments_enabled ? trim( (string) get_post_meta( $att_id, '_photoproof_comment', true ) ) : '';

            $list .= '- ' . $filename;
            if ( '' !== $comment ) {
                $list .= "\n  → " . $comment;
            }
            $list .= "\n";
        }
        return $list;
    }

    /**
     * Récupère les IDs des photos commentées MAIS NON sélectionnées.
     *
     * @param int   $gallery_id   ID de la galerie
     * @param array $selected_ids IDs des photos sélectionnées
     * @return array
     */
    private function get_commented_not_selected( $gallery_id, $selected_ids ) {
        if ( ! get_option( 'photoproof_enable_comments' ) ) {
            return array();
        }

        $all_photos = get_post_meta( $gallery_id, '_photoproof_gallery_photos', true );
        $all_photos = is_array( $all_photos ) ? array_map( 'intval', $all_photos ) : array();
        $selected   = array_map( 'intval', $selected_ids );

        $result = array();
        foreach ( $all_photos as $att_id ) {
            if ( in_array( $att_id, $selected, true ) ) {
                continue;
            }
            $comment = trim( (string) get_post_meta( $att_id, '_photoproof_comment', true ) );
            if ( '' !== $comment ) {
                $result[] = $att_id;
            }
        }
        return $result;
    }

    /**
     * Envoie les deux emails de confirmation
     *
     * @param int $post_id   ID de la galerie
     * @param int $client_id ID de l'utilisateur client
     */
    public function send_emails( $post_id, $client_id ) {

        $gallery_title = get_the_title( $post_id );
        $selected_ids  = get_post_meta( $post_id, '_photoproof_selected_photos', true );
        $selected_ids  = is_array( $selected_ids ) ? $selected_ids : array();
        $count         = count( $selected_ids );
        $studio_name   = get_option( 'photoproof_custom_title', get_option( 'blogname' ) );
        $accent_color  = sanitize_hex_color( get_option( 'photoproof_color_active', '#2271b1' ) ) ?: '#2271b1';

        $photographer_email = get_option( 'admin_email' );

        $client_name  = __( 'Client', 'photoproof' );
        $client_email = '';

        if ( $client_id ) {
            $client = get_userdata( $client_id );
            if ( $client ) {
                $client_name  = $client->display_name ?: $client->user_login;
                $client_email = $client->user_email;
            }
        }

$file_list_text = $this->build_file_list_text( $selected_ids );
        $file_list_html = $this->build_file_list_html( $selected_ids );
        $gallery_url    = get_permalink( $post_id );

        // Photos commentées mais non sélectionnées (le client a un doute)
        $commented_unselected_ids = $this->get_commented_not_selected( $post_id, $selected_ids );
        $commented_text = '';
        if ( ! empty( $commented_unselected_ids ) ) {
            $commented_text  = "\n" . __( 'Photos with comments but not selected:', 'photoproof' ) . "\n";
            $commented_text .= $this->build_file_list_text( $commented_unselected_ids );
        }

        $vars = array(
            'client_name'            => esc_html( $client_name ),
            'gallery_title'          => esc_html( $gallery_title ),
            'count'                  => $count,
            'file_list'              => $file_list_text,
            'commented_not_selected' => $commented_text,
            'gallery_url'            => esc_url( $gallery_url ),
            'studio_name'            => esc_html( $studio_name ),
        );

        // ── MAIL PHOTOGRAPHE ──────────────────────────────────────────

        $default_subject_photo = '[PhotoProof] {client_name} validated the gallery "{gallery_title}"';

        $body_photo_html =
            '<p style="margin:0 0 20px; font-size:15px; color:#1e293b; line-height:1.6;">' .
            esc_html__( 'Hello,', 'photoproof' ) .
            '</p><p style="margin:0 0 20px; font-size:15px; color:#1e293b; line-height:1.6;">' .
            '<strong>' . esc_html( $client_name ) . '</strong> ' .
            esc_html__( 'has confirmed their selection for the gallery', 'photoproof' ) .
            ' <strong>"' . esc_html( $gallery_title ) . '"</strong>.</p>' .
            '<p style="margin:0 0 12px; font-size:15px; color:#1e293b;"><strong>' .
            sprintf(
                /* translators: %d: number of selected photos */
                esc_html( _n( '%d photo selected:', '%d photos selected:', $count, 'photoproof' ) ),
                $count
            ) .
'</strong></p>' .
            '<table width="100%" cellpadding="12" cellspacing="0" style="background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:28px;">' .
            $file_list_html .
            '</table>';

        // Section optionnelle : photos commentées non sélectionnées
        if ( ! empty( $commented_unselected_ids ) ) {
            $commented_unselected_count = count( $commented_unselected_ids );
            $commented_unselected_html  = $this->build_file_list_html( $commented_unselected_ids );

            $body_photo_html .=
                '<p style="margin:0 0 12px; font-size:15px; color:#1e293b;"><strong>' .
                sprintf(
                    /* translators: %d: number of photos with comments but not selected */
                    esc_html( _n( '%d photo with a comment but not selected:', '%d photos with comments but not selected:', $commented_unselected_count, 'photoproof' ) ),
                    $commented_unselected_count
                ) .
                '</strong></p>' .
                '<p style="margin:0 0 12px; font-size:13px; color:#64748b; font-style:italic;">' .
                esc_html__( 'The client left a note on these photos but did not include them in the final selection — they may want your input.', 'photoproof' ) .
                '</p>' .
                '<table width="100%" cellpadding="12" cellspacing="0" style="background:#fef3c7; border-radius:8px; border:1px solid #fde68a; margin-bottom:28px;">' .
                $commented_unselected_html .
                '</table>';
        }

        $body_photo_html .=
            '<p style="text-align:center;">' .
            '<a href="' . esc_url( $gallery_url ) . '" style="display:inline-block; background:' . esc_attr( $accent_color ) . '; color:#ffffff; text-decoration:none; padding:14px 32px; border-radius:8px; font-size:13px; font-weight:600; letter-spacing:.06em; text-transform:uppercase;">' .
            esc_html__( 'View gallery', 'photoproof' ) .
            '</a></p>';

        $subject_photo = apply_filters( 'photoproof_email_photographer_subject',
            $this->parse_template( get_option( 'photoproof_email_photographer_subject', $default_subject_photo ), $vars ),
            $post_id, $client_id
        );
        $body_photo = apply_filters( 'photoproof_email_photographer_body',
            $this->wrap_html( $body_photo_html, $accent_color ),
            $post_id, $client_id
        );

        wp_mail( $photographer_email, $subject_photo, $body_photo, $this->get_html_headers() );

        // ── MAIL CLIENT ───────────────────────────────────────────────

        if ( ! $client_email ) {
            return;
        }

        $default_subject_client = 'Your selection for "{gallery_title}" has been received';

        $default_body_content =
            esc_html__( 'Hello', 'photoproof' ) . ' ' . esc_html( $client_name ) . ',<br><br>' .
            sprintf(
                /* translators: %1$d: count, %2$s: gallery title */
                esc_html__( 'We have received your selection of %1$d photo(s) for the gallery "%2$s".', 'photoproof' ),
                $count,
                esc_html( $gallery_title )
            ) . '<br><br>' .
            esc_html__( 'We will now handle the final processing of your selected images and will get back to you very soon.', 'photoproof' ) . '<br><br>' .
            esc_html__( 'Thank you for your trust.', 'photoproof' ) . '<br><br>' .
            '— ' . esc_html( $studio_name );

        $custom_body = get_option( 'photoproof_email_client_body', '' );
        if ( $custom_body ) {
            $body_content = nl2br( esc_html( $this->parse_template( $custom_body, $vars ) ) );
        } else {
            $body_content = $default_body_content;
        }

        $body_client_html = '<p style="margin:0; font-size:15px; color:#1e293b; line-height:1.8;">' . $body_content . '</p>';

        $subject_client = apply_filters( 'photoproof_email_client_subject',
            $this->parse_template( get_option( 'photoproof_email_client_subject', $default_subject_client ), $vars ),
            $post_id, $client_id
        );
        $body_client = apply_filters( 'photoproof_email_client_body',
            $this->wrap_html( $body_client_html, $accent_color ),
            $post_id, $client_id
        );

        wp_mail( $client_email, $subject_client, $body_client, $this->get_html_headers() );
    }
}