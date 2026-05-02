# PhotoProof — WordPress Proofing Plugin

Photography proofing plugin for WordPress. Create secure client galleries with watermark protection, selection workflow, automated file renaming and email notifications.

**Author:** Cédric Stoecklin
**Version:** 1.0.0
**License:** GPL-2.0-or-later

---

## Features

- **Standalone gallery template** — distraction-free page, fully isolated from your theme (compatible with Barba.js, page builders, etc.)
- **Client selection workflow** — click to select, auto-saved every 1.5s, confirm with a single button
- **Watermark protection** — automatic logo overlay on all images (GD or Imagick), adjustable opacity, originals untouched
- **Automatic file renaming** — configurable pattern (`{gallery_title}-{index}`), with optional custom prefix per gallery
- **Private UUID links** — replace gallery slugs with impossible-to-guess UUIDs, no permalink flush needed
- **Access expiration** — auto-archive galleries after 30 days
- **Email notifications** — customizable templates for photographer and client, sent on selection validation
- **Photographer recommendations** — mark your favorites to guide the client (configurable icon)
- **CSV export** — download the validated selection as a spreadsheet
- **Client dashboard** — `[photoproof_galleries_client]` shortcode + PHP template tags
- **Multilingual** — ships with French, German, Spanish and Italian translations
- **Customizable design** — background, accent, text colors, custom logo, rounded corners option

---

## File Structure

```
photoproof/
├── photoproof.php                          ← Main entry point
├── readme.txt                              ← WordPress.org readme
├── uninstall.php                           ← Clean uninstall
├── admin/
│   ├── class-photoproof-settings.php       ← Settings page
│   ├── class-photoproof-metaboxes.php      ← Gallery editor metabox
│   ├── class-photoproof-assets.php         ← Admin scripts/styles
│   ├── class-photoproof-admin-columns.php  ← Admin columns + auto-publish
│   ├── css/admin-settings.css
│   └── js/
│       ├── admin-gallery.js                ← Drag & drop upload + recommendations
│       ├── admin-settings.js               ← Settings UI
│       └── vendor/anime.min.js
├── includes/
│   ├── class-photoproof-uploader.php       ← Custom upload + AJAX
│   ├── class-photoproof-renamer.php        ← Deferred renaming on save
│   ├── class-photoproof-watermark.php      ← Watermark generation (GD/Imagick)
│   ├── class-photoproof-router.php         ← UUID routing (parse_request, no rewrite rules)
│   ├── class-photoproof-export.php         ← CSV export
│   ├── class-photoproof-expiration.php     ← Auto-expiration + cron
│   ├── class-photoproof-mailer.php         ← Email notifications
│   └── class-photoproof-helpers.php        ← Template tags + shortcode
├── public/
│   ├── class-photoproof-public.php         ← Front-end AJAX + assets
│   ├── css/photoproof-public.css           ← Client gallery styles
│   └── js/
│       ├── photoproof-public.js            ← Selection, lightbox, auto-save
│       └── photoproof-selection-anim.js    ← Selection tray animations
├── templates/
│   └── single-photoproof_gallery.php              ← Standalone gallery template
└── languages/
    ├── photoproof.pot
    ├── photoproof-fr_FR.po / .mo / .l10n.php
    ├── photoproof-de_DE.po / .mo / .l10n.php
    ├── photoproof-es_ES.po / .mo / .l10n.php
    └── photoproof-it_IT.po / .mo / .l10n.php
```

---

## Installation

1. Copy the `photoproof/` folder to `wp-content/plugins/`
2. Activate the plugin in **Plugins**
3. Go to **PhotoProof → Settings** to configure watermark, renaming, colors and emails
4. Create your first gallery under **PhotoProof → Add New**

---

## Database Table

The plugin creates `wp_photoproof_galleries` on activation:

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Auto-increment |
| `post_id` | bigint | Gallery post ID |
| `client_id` | bigint | Assigned WP user ID (nullable) |
| `folder_path` | varchar | Relative folder path |
| `status` | varchar | `brouillon` / `publie` / `valide` / `ferme` |
| `watermark_settings` | text | Watermark active flag |
| `selection_data` | longtext | Reserved |
| `created_at` | datetime | Creation date |

---

## Gallery Statuses

```
brouillon → publie → valide → ferme
               ↑________↓
            (reopen)
```

| Status | Description | Client can select |
|--------|-------------|-------------------|
| `brouillon` | Draft — admin only | No |
| `publie` | Published — waiting for selection | Yes |
| `valide` | Selection confirmed by client | No (locked) |
| `ferme` | Archived (expired or manual) | No |

---

## Workflow

### 1. Create a gallery

1. **PhotoProof → Add New**, give it a title
2. Save as draft (required before uploading)
3. Upload photos via drag & drop in the metabox
4. Optionally mark photos as recommended
5. Assign a client (WordPress user)
6. **Publish** → triggers renaming + watermark generation + status update

### 2. Share with the client

- Copy the URL from the metabox
- Standard URL: `https://example.com/galerie-epreuve/gallery-title/`
- With UUID enabled: `https://example.com/galerie-epreuve/550e8400-e29b-...`

### 3. Client selection

1. Client browses the gallery (5-column grid, no crop)
2. Clicks photos to select (circle indicator, bottom-right)
3. Selection auto-saves every 1.5 seconds
4. Clicks **Validate selection** → irreversible confirmation
5. Two emails sent automatically (photographer + client)

### 4. After validation

- Gallery is locked for the client
- Unselected photos are dimmed
- Photographer sees the recap with thumbnails in the metabox
- Photographer can **reopen** (keep or reset selection)

---

## Settings

Accessible via **PhotoProof → Settings**.

### General
- **Random URLs (UUID)** — hide gallery slugs in public URLs
- **Automatic renaming** — rename files on publish with pattern `{gallery_title}-{index}`
- **Recommendations** — photographer favorite badges (dot / star / diamond / heart)
- **Expiration** — auto-archive galleries after 30 days

### Security & Watermark
- **Watermark logo** — PNG with transparency recommended
- **Opacity** — 10% to 100%
- **Login page** — custom login URL or default `wp-login.php`
- **File deletion** — delete photos when gallery is trashed

### Theme Design
- **Header title & logo** — displayed in the client gallery header
- **Colors** — background, active, text (CSS custom properties)
- **Rounded corners** — toggle for photo grid

### Emails
- **Photographer email** — sent on client validation, with file list
- **Client email** — confirmation of reception
- **Customizable** — subject and body templates with variables: `{client_name}`, `{gallery_title}`, `{count}`, `{file_list}`, `{gallery_url}`, `{studio_name}`

---

## Shortcode

```
[photoproof_galleries_client]
```

Displays all galleries assigned to the logged-in client.

| Attribute | Default | Description |
|-----------|---------|-------------|
| `columns` | `1` | Number of columns |
| `show_status` | `true` | Show status badge |
| `show_count` | `true` | Show photo count |
| `show_date` | `true` | Show date |

```
[photoproof_galleries_client columns="3" show_date="false"]
```

---

## Template Tags

PHP functions for theme developers:

```php
$galleries = photoproof_get_client_galleries( $user_id );
$status    = photoproof_get_gallery_status( $post_id );
$count     = photoproof_get_gallery_photo_count( $post_id );
$url       = photoproof_get_gallery_thumbnail( $post_id, 'medium' );
$ids       = photoproof_get_gallery_selection( $post_id );
$locked    = photoproof_is_gallery_locked( $post_id );
```

---

## Hooks

```php
// Client confirms selection
add_action( 'pp_gallery_selection_confirmed', function( $post_id, $client_id ) {
    // ...
}, 10, 2 );

// Photo uploaded
add_action( 'pp_attachment_uploaded', function( $attachment_id, $post_id ) {
    // ...
}, 10, 2 );
```

---

## AJAX Endpoints

| Action | Access | Description |
|--------|--------|-------------|
| `pp_upload_photo` | Admin | Upload a photo |
| `pp_detach_photo` | Admin | Remove a photo |
| `pp_toggle_recommendation` | Admin | Toggle recommendation badge |
| `pp_get_gallery_photos` | Admin | List gallery photos |
| `pp_save_selection` | Public | Save client selection |
| `pp_get_selection` | Public | Get current selection |
| `pp_reopen_gallery` | Admin | Reopen a validated gallery |
| `pp_export_selection` | Admin | Export selection as CSV |

---

## CSS Custom Properties

Injected dynamically based on settings:

```css
:root {
    --pp-bg:         #f5f4f2;
    --pp-active:     #2271b1;
    --pp-text:       #1e293b;
    --pp-img-radius: 0px;
}
```

---

## Dependencies

| Library | Version | Source |
|---------|---------|--------|
| jQuery | 3.7+ | WP bundled |
| ImagesLoaded | 5.0+ | WP bundled |
| GSAP | 3.12.2 | Local vendor |
| GD or Imagick | — | PHP server extension |

---

## Local Development

### Email testing

Install **WP Mail SMTP** + **MailHog** for local email testing:

1. Download [MailHog](https://github.com/mailhog/MailHog/releases) and run it
2. Configure WP Mail SMTP: SMTP host = `localhost`, port = `1025`, no auth
3. Open `http://localhost:8025` to see captured emails

---

## Changelog

### 1.0.0
- First stable release
- Multilingual: French, German, Spanish, Italian
- Fixed: UUID private links (parse_request approach, no rewrite rules)
- Fixed: expiration cron scheduling
- Fixed: custom prefix renaming on publish and update
- Full PHPCS compliance

### 0.2.0
- Animated selection tray
- Recap panel with expand animation
- Full i18n support

### 0.1.0
- Initial release