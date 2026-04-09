=== PhotoProof ===
Contributors: cedric-stoecklin
Tags: photography, proofing, gallery, watermark, client
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A proofing gallery plugin for photographers. Let your clients browse, select and validate their favorite photos — all from a beautiful, standalone gallery page.

== Description ==

PhotoProof turns WordPress into a professional proofing platform for photographers. Create secure galleries, share them with your clients, and let them pick their favorite shots — no account needed for browsing, login required only for validation.

**Key features:**

* **Standalone gallery template** — a clean, distraction-free gallery page, fully isolated from your theme
* **Client selection workflow** — clients click to select photos, auto-saved in real-time, then confirm with a single button
* **Watermark protection** — automatically overlay your logo on every uploaded image (GD or Imagick), with adjustable opacity
* **Automatic file renaming** — organize your deliverables with configurable naming patterns (studio name, gallery title, sequential number)
* **Private UUID links** — optionally replace gallery slugs with impossible-to-guess UUIDs for extra privacy
* **Access expiration** — auto-archive galleries after 30 days to keep your workspace clean
* **Email notifications** — photographer and client both receive confirmation emails when a selection is validated, with customizable templates
* **Photographer recommendations** — mark your favorite shots to guide the client's selection
* **CSV export** — export the validated selection as a spreadsheet
* **Client dashboard shortcode** — `[pp_galleries_client]` displays all galleries assigned to the logged-in client
* **Multilingual** — fully translatable, ships with French, (partial IA translations for German, Spanish and Italian) 
* **Customizable design** — choose background, accent and text colors, upload your logo, toggle rounded corners

PhotoProof stores all gallery photos in a dedicated, protected folder (`/uploads/photoproof/`) and keeps them out of the standard Media Library.

== Installation ==

1. Upload the `photoproof` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. Go to **PhotoProof → Settings** to configure watermark, renaming, colors and email templates.
4. Create your first gallery under **PhotoProof → Add New**.
5. Upload photos, assign a client, and publish.

== Frequently Asked Questions ==

= Do my clients need a WordPress account? =

Browsing the gallery works without any account. A WordPress account (subscriber role is enough) is only required when the client wants to validate their final selection.

= Can I use my own login page? =

Yes. In **PhotoProof → Settings → Security**, you can set a custom login URL. If left empty, WordPress uses its default login page.

= How does the watermark work? =

Upload a PNG logo in **Settings → Security**. When a gallery is published, PhotoProof generates watermarked copies in a `/watermarked/` subfolder. The originals are never modified. Clients always see the watermarked version.

= Can I reopen a validated gallery? =

Yes. From the gallery editor, you can reopen a validated gallery — either keeping the previous client selection or resetting it entirely.

= What happens when a gallery expires? =

If auto-archiving is enabled, galleries are automatically set to "archived" status 30 days after publication. The client sees a friendly message asking them to contact their photographer. Admins can always view and reactivate archived galleries.

= Does PhotoProof work with page builders? =

The gallery uses its own standalone template, completely independent from your theme. This avoids conflicts with page builders, Barba.js, or any JavaScript framework your theme may use.

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
* Multilingual: full translations for French, partial for German, Spanish, Italian
* Fixed: UUID private links now work without manual permalink flush
* Fixed: auto-expiration cron is now properly scheduled on activation
* Fixed: custom prefix renaming applies correctly on publish and update
* Improved: admin banner for expired/archived galleries displayed in template
* Improved: centralized access checks (no duplicate logic)
* Code quality: full PHPCS compliance, proper escaping and phpcs:ignore annotations

= 0.2.0 =
* Animated selection tray
* Recap panel with expand animation
* Full i18n support

= 0.1.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
First stable release. If upgrading from a previous beta, deactivate and reactivate the plugin to register the expiration cron job.