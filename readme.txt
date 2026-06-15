=== BSPE Connect ===
Contributors: bspelegalmarketing
Tags: contact, lead-capture, mobile, law-firm
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 3.4.0
License: Proprietary

Mobile contact bar with lead capture for BSPE Legal Marketing client sites.

== Description ==

BSPE Connect adds a bottom-fixed contact bar to mobile visitors with up to
four configurable buttons (Connect, Call, Text, Email), an optional welcome
bubble, and a built-in lead capture form. Submissions are stored in the
WordPress database and emailed to the firm.

Configured under the **BSPE Connect** admin menu.

== Installation ==

1. Download the latest release zip from the BSPE Legal Marketing GitHub
   Releases page (or receive it from BSPE).
2. Install via **Plugins → Add New → Upload Plugin** in WordPress.
3. Activate from the Plugins screen.
4. Configure under the new **BSPE Connect** admin menu.

Updates appear in WordPress as standard "Update available" notices — click
**Update** to install. Every update is verified before installation.

== Frequently Asked Questions ==

= Where do form submissions go? =

Submissions are emailed to the address configured under **Form → Mail
delivery**, and also kept inside WordPress so you can browse them under
the **Submissions** tab in the BSPE Connect admin menu.

= How do I update the plugin? =

When BSPE publishes a new version, WordPress shows an "Update available"
notice on your Plugins page. Click **Update** to install.

= How do I contact support? =

Reach out to BSPE Legal Marketing through your usual channel.

== Changelog ==

= 3.4.0 = Form webhook — POST each submission as JSON to a CRM / Zapier / Make endpoint, with optional HMAC signing. On/off toggle.
= 3.3.3 = In-Post Widget: target Google Map embeds specifically (ignore YouTube/other iframes above the first heading).
= 3.3.2 = In-Post Widget: paragraph fallback when the post has no headings — after paragraph N (default 1).
= 3.3.1 = In-Post Widget: smarter placement (before first heading, or before iframe if one sits above); posts-only hardcoded; margin setting.
= 3.3.0 = New "In-Post Widget" tab — inject any shortcode after the Nth paragraph of post / page content.
= 3.2.4 = Hover flyout in the WP admin sidebar with quick-jump links to every plugin tab.
= 3.2.3 = Fix Elementor (and other page builders) injecting margins into the bubble / modal text.
= 3.2.2 = Loading spinner on the Save button while the AJAX save is in flight.
= 3.2.1 = Gate Site utilities (QR, external links, REST users hide) on the license.
= 3.2.0 = AJAX settings save — no more page reload when clicking Save.
= 3.1.5 = Defaults: Connect weight 600 + simpler mail subject/from; mobile breakpoint step 1; body padding so bar doesn't cover footer.
= 3.1.4 = QR: drop loading="lazy" to avoid conflicts with image-optimization plugins.
= 3.1.3 = QR: use quickchart.io image (rock-solid scanning); keep anchor + size + max-width settings.
= 3.1.2 = Phone tel:/sms: digits-only; QR larger quiet zone + hover/click link.
= 3.1.1 = QR code: fix format-info placement (codes weren't scanning); align left.
= 3.1.0 = Site utilities (page QR codes, external links in new tab, hide REST users).
= 3.0.3 = License tab header description softened.
= 3.0.2 = License tab moved to last position in the sidebar.
= 3.0.1 = License server URL locked to production endpoint.
= 3.0.0 = License activation required. New License tab; plugin is dormant until activated.
= 2.4.3 = Documentation trimmed.
= 2.4.2 = Reset all settings to defaults (typed confirm).
= 2.4.1 = Manual submission delete (per-row + delete-all).
= 2.4.0 = Submissions retention setting.
= 2.3.1 = Color palette mapping: inline color chips.
= 2.3.0 = Color palette presets (plugin defaults + website palette mapping).
= 2.2.x = Bug fixes, mobile improvements, security hardening.
= 2.1.x = Bar styling and form-handling improvements.
= 2.0.0 = First stable release.
