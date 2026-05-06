=== BSPE Connect ===
Contributors: bspelegalmarketing
Tags: contact, lead-capture, mobile, law-firm, sticky-bar
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 1.2.0
License: Proprietary

Mobile-only contact bar with lead capture for BSPE Legal Marketing client sites.

== Description ==

BSPE Connect adds a bottom-fixed contact bar to mobile visitors with up to four
configurable buttons (Connect, Call, Text, Email), an optional welcome bubble,
and a built-in lead capture form. Submissions are stored in the WordPress
database and emailed to the firm.

The plugin is self-updating from a private GitHub repository under the BSPE
Legal Marketing organization.

== Installation ==

1. Upload the plugin zip via Plugins -> Add New -> Upload, or unpack into
   `wp-content/plugins/bspe-connect`.
2. Add the GitHub Personal Access Token to your `wp-config.php`:

   define('BSPE_CONNECT_GITHUB_TOKEN', 'ghp_...');

   The token must have `repo` scope on the private
   `BSPE-Legal-Marketing/bspe-connect` repository.

3. (Optional) opt into the beta channel:

   define('BSPE_CONNECT_UPDATE_CHANNEL', 'beta');

4. Activate the plugin from the Plugins screen.
5. Configure under the new "BSPE Connect" admin menu item.

== Phase 2 status ==

This release adds the user-facing contact bar:

* Plugin activates / deactivates cleanly
* Database tables for submissions and analytics events are created
* Default settings are seeded (master-enable defaults to OFF until configured)
* Admin shell uses a left-sidebar nav rail with the BSPE brand palette
* Mobile-only contact bar with up to 4 buttons (Connect, Call, Text, Email)
* Welcome bubble that appears 3 seconds after the bar first becomes visible
* Scroll-trigger show/hide behavior with rAF-paced scroll handler
* Display rules: site-wide, pages-only, posts-only, plus include/exclude
  slug lists
* Self-update mechanism is wired up; degrades gracefully if the GitHub token
  is not configured

Lead form handling (Phase 3), settings UI (Phase 4), and the analytics
dashboard (Phase 5) ship in subsequent releases.

== Changelog ==

= 1.2.0 =
* Phase 2: mobile contact bar with bottom-fixed positioning, scroll-trigger
  show/hide, welcome bubble with avatar/dismissal, display rules, inline
  CSS variables driven by Design settings, Google Font enqueueing, and 16
  line-icon SVG variants (4 per button type).

= 1.1.0 =
* Admin shell: replaced top tabs with a left sidebar nav rail; added
  inline-SVG section icons and a pulsing teal active indicator.

= 1.0.0 =
* Phase 1 skeleton: activation, database schema, default settings, admin
  shell with tab navigation, plugin-update-checker integration.

== Frequently Asked Questions ==

= Where is submission data stored? =

In two custom tables, `wp_bspe_connect_submissions` and
`wp_bspe_connect_events`. They are created on activation and destroyed only
when the plugin is uninstalled (not when deactivated).

= How do auto-updates work? =

The plugin checks the `BSPE-Legal-Marketing/bspe-connect` GitHub repository
every 12 hours using the bundled `plugin-update-checker` library. Releases
that include `Auto-Update: yes` in their notes are applied silently; all
other releases produce the standard "Update available" admin notice.

= Why is the GitHub token in wp-config.php instead of a settings field? =

So it is never written to the database, never appears in a backup of the
options table, and never gets exposed by a settings export.
