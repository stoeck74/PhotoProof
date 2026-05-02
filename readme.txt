=== PhotoProof ===
Contributors: stoeck
Tags: photography, proofing, gallery, watermark, client
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Proofing gallery plugin for photographers. Let clients browse, select and validate their favorite photos from a beautiful standalone gallery page.

== Description ==

PhotoProof turns WordPress into a professional proofing platform for photographers. Create secure galleries, share them with your clients, and let them pick their favorite shots — no account needed for browsing, login required only for validation.

**Key features:**

* **Standalone gallery template** — a clean, distraction-free gallery page, fully isolated from your theme
* **Client selection workflow** — clients click to select photos, auto-saved in real-time, then confirm with a single button
* **Watermark protection** — overlay your logo on gallery images (GD or Imagick), with adjustable opacity. Configured globally, applied per-gallery via a simple toggle.
* **Automatic file renaming** — organize your deliverables with a configurable naming pattern. Optional per-gallery custom name to override the default.
* **Private UUID links** — optionally replace gallery slugs with impossible-to-guess UUIDs for extra privacy
* **Access expiration** — auto-archive galleries after 30 days to keep your workspace clean
* **Email notifications** — photographer and client both receive confirmation emails when a selection is validated, with customizable templates
* **Photographer recommendations** — mark your favorite shots to guide the client's selection
* **CSV export** — export the validated selection as a spreadsheet
* **Client dashboard shortcode** — `[photoproof_galleries_client]` displays all galleries assigned to the logged-in client
* **Multilingual** — fully translatable, ships with French (partial AI translations for German, Spanish and Italian)
* **Customizable design** — choose background, accent and text colors, upload your logo, toggle rounded corners

PhotoProof stores all gallery photos in a dedicated folder (`/uploads/photoproof/`), separate from the standard Media Library.

== Installation ==

1. Upload the `photoproof` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. Go to **Galleries → Settings** to configure watermark, renaming, colors and email templates.
4. Create your first gallery under **Galleries → Add New**.
5. Upload photos, assign a client, and publish.

== Frequently Asked Questions ==

= Do my clients need a WordPress account? =

Browsing the gallery works without any account. A WordPress account (subscriber role is enough) is only required when the client wants to validate their final selection.

= Can I use my own login page? =

Yes. In **Settings → Security & Watermark**, you can set a custom login URL. If left empty, WordPress uses its default login page.

= How does the watermark work? =

In **Settings**, upload a PNG logo and set the desired opacity. This makes the watermark *available*. Then, on each gallery, a "Watermark protection" toggle lets you decide whether to apply it or not. When enabled, PhotoProof generates watermarked copies in a `/watermarked/` subfolder; the originals are never modified. Clients see the watermarked version, you keep the originals safe.

The toggle can be changed at any time — even after publication. The frontend always reflects the current setting.

= How does the file renaming work? =

In **Settings**, enable automatic renaming and set your global pattern (for example: `MyStudio-{gallery_title}`). Once enabled, every gallery's files are automatically renamed using the pattern, with a counter (`-0001`, `-0002`...) appended to each file.

On a per-gallery basis, you can fill in a "Custom file name" field in the metabox. When set, this name replaces the global pattern entirely (useful if the gallery title doesn't make a good file name — too long, special characters, internal codes...).

= Can I reopen a validated gallery? =

Yes. From the gallery editor, you can reopen a validated gallery — either keeping the previous client selection or resetting it entirely.

= What happens when a gallery expires? =

If auto-archiving is enabled, galleries are automatically set to "archived" status 30 days after publication. The client sees a friendly message asking them to contact their photographer. Admins can always view and reactivate archived galleries.

= Does PhotoProof work with page builders? =

The gallery uses its own standalone template, completely independent from your theme. This avoids conflicts with page builders, animation libraries (Barba.js, GSAP...), or any JavaScript framework your theme may use.

= What happens when I uninstall the plugin? =

WordPress asks "Are you sure you want to delete this plugin and all of its data?" — PhotoProof respects that promise. Uninstalling the plugin permanently removes:

* All galleries (custom post type)
* All photos uploaded through PhotoProof (originals + watermarked copies)
* The `/uploads/photoproof/` folder and its contents
* All plugin settings, options and the custom database table
* Scheduled cron jobs

If you want to keep your data, **deactivate** the plugin instead of deleting it. Deactivation preserves everything.

== Screenshots ==

1. Client gallery view — clean, standalone page with photo grid and selection bar
2. Gallery editor — metabox with status, upload zone, client assignment and selection recap
3. Settings page — general options, renaming patterns, recommendation icons
4. Watermark & security settings — logo upload, opacity slider, login page, file deletion
5. Design settings — colors, logo, rounded corners
6. Email settings — customizable templates with variables

== Changelog ==

= 1.0.0 =
* First stable release
* Watermark and file renaming logic redesigned: settings provide the tools, gallery metabox decides whether to use them. Both can be toggled at any time, even after publication — the frontend reflects the current state immediately.
* Custom file name field in the gallery metabox (overrides the global pattern when needed)
* Live preview of the resulting file name in the metabox
* Complete uninstall: deleting the plugin now removes all galleries, photos, settings and database tables — as WordPress promises in its confirmation dialog
* UUID private links: fixed edge case on subdirectory installs where the slug could collide with the WP path
* Auto-expiration cron is properly scheduled on activation
* Multilingual: full translations for French, partial for German, Spanish, Italian
* Code quality: full WordPress.org plugin guidelines compliance, all identifiers properly prefixed

= 0.2.0 =
* Animated selection tray
* Recap panel with expand animation
* Full i18n support

= 0.1.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
First stable release. If upgrading from a previous beta, deactivate and reactivate the plugin to register the expiration cron job and refresh the rewrite rules.
