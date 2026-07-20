=== BSPE Connect ===
Contributors: bspelegalmarketing
Tags: contact, lead-capture, mobile, law-firm
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 3.6.5
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

= 3.6.5 = Fix the Chat button doing nothing on phones (iOS Safari): delay-JS optimizers (NitroPack) hold every script until the first user interaction — on a phone that first interaction is the Chat tap itself, so the provider only started loading then and the old 2.5s retry gave up before its launcher mounted. The printed Intaker snippet now carries the optimizer opt-out attributes, the launcher retry polls for ~15s, hidden launchers (e.g. the hidden green Call button sharing .widget-button) are skipped, and the synthetic click now fires the full pointerdown/mousedown/up/click sequence for widgets that bind pointer events.
= 3.6.4 = WPML / Polylang compatibility: new wpml-config.xml registers all settings-stored frontend strings (button labels, welcome bubble heading/message, chat button label, form headings/subheadings/submit/success message, mail subject/from name, firm name) as translatable admin texts in WPML String Translation. Template strings were already gettext ('bspe-connect' domain); the two hardcoded JS form-error strings are now localized too.
= 3.6.3 = Exclude the bar's CSS/JS from page optimizers and harden the anti-flash guard. The plugin now stamps opt-out attributes (nitro-exclude, nowprocket, data-no-optimize, data-noptimize, data-no-defer) on its own asset tags so NitroPack / WP Rocket / LiteSpeed / Autoptimize leave them alone. The bar div also ships with the `hidden` attribute (restored by the stylesheet, stripped by JS once the sheet is proven live), so even a partial critical-CSS extraction can never flash unstyled markup or reserve phantom footer padding.
= 3.6.2 = Actually hide Intaker's green "CALL US" button: it is the compact contact widget `<button id="icw--call--button">` inside `#icw--call--content`, which Intaker appends directly to <body> as a sibling of #icw — an ID (not a class) and outside the container we were hiding. Both the hide-call rule and the menu-yield rule now target it. Also fix the unstyled flash of the bar on page load under CSS-deferring optimizers (NitroPack): inline visibility guard in wp_head + higher-specificity reveal in the stylesheet.
= 3.6.1 = Fix menu-yield getting stuck: Elementor adds a `dialog-lightbox-body` class to the page on the first popup open and never removes it, so the bar + widgets stayed hidden after the menu closed. Detection now keys off only transient signals (scroll-lock class + the actually-visible modal), so everything reappears when the menu closes.
= 3.6.0 = Yield to the site's mobile menu: when the hamburger menu is open, the bar and the third-party chat (Intaker) + accessibility (UserWay) launchers are hidden so they no longer float on top of the full-screen menu. Theme-agnostic detection covers both nav-toggle menus (Elementor nav, aria-expanded/active toggles) and popup/off-canvas menus (Elementor popups, Popup Maker, scroll-lock body classes, full-screen fixed overlays).
= 3.5.9 = Fix Intaker "Call us" hide — target the button by ID (icw--multiContact-call) + the standalone call launcher; the class selector never matched.
= 3.5.8 = Hide Intaker's "Call us" on all viewports with a wider selector.
= 3.5.7 = Chat close now clears Intaker's page-dim blur reliably; new toggle hides Intaker's redundant floating "Call us" button (on by default).
= 3.5.6 = Intaker launcher default distance from bottom set to 36px.
= 3.5.5 = Chat button now toggles the chat open/closed (closing clears the page-dim backdrop); our launcher position/size no longer distorts the open Intaker chat panel.
= 3.5.4 = Intaker launcher position is now a direct distance-from-bottom (defaults to the corner, level with the accessibility icon); welcome bubble reliably sits above the UserWay icon (max z-index + pinned last in the DOM).
= 3.5.3 = Bar + welcome bubble now sit above third-party chat / accessibility widgets (raised the stacking order), so the welcome message is never buried.
= 3.5.2 = Intaker launcher position controls — nudge the floating chat launcher up above the bar and shrink it (Chat tab), so it stops overlapping the bar on mobile.
= 3.5.1 = Chat button now uses the same visual icon-library picker as the other buttons; Account ID placeholder relabeled "client id".
= 3.5.0 = New Chat feature — load Intaker or a custom chat provider, with an optional Chat button on the bar that opens it. Also fixes a double-unslash that could mangle backslashes in the In-Post Widget shortcode.
= 3.4.3 = Footer clearance gap set to 0 — footer sits flush above the bar, no gap.
= 3.4.2 = Footer clearance now measured precisely in JS (snug 5px gap) instead of an oversized estimate — removes the empty band above the bar.
= 3.4.1 = Webhook: removed the optional signing secret — just toggle + URL now.
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
