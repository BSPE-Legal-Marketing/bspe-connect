=== BSPE Connect ===
Contributors: bspelegalmarketing
Tags: contact, lead-capture, mobile, law-firm, sticky-bar
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 2.1.2
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
2. Configure the wp-config.php constants below.
3. Activate the plugin from the Plugins screen.
4. Configure under the new "BSPE Connect" admin menu item (sidebar tabs
   from General through Analytics).

== wp-config.php constants ==

All three are optional. Constants always win over settings stored in the
database — define them when you want secrets out of options exports and
out of admin UIs.

* `BSPE_CONNECT_GITHUB_TOKEN` (string, OPTIONAL since v2.1.1)
  GitHub Personal Access Token. The repo is public, so updates work
  without this token — every install is zero-touch. Set it only if you
  want to raise the GitHub API rate limit from 60/hour (anonymous) to
  5,000/hour, or if the repo is ever flipped back to private.

  define('BSPE_CONNECT_GITHUB_TOKEN', 'ghp_...');

* `BSPE_CONNECT_UPDATE_CHANNEL` (string, 'stable' or 'beta')
  Default 'stable' — discovers updates via GitHub tags + releases.
  Set to 'beta' to follow the tip of the `beta` branch instead.

  define('BSPE_CONNECT_UPDATE_CHANNEL', 'beta');

* `BSPE_CONNECT_TURNSTILE_SECRET` (string)
  Cloudflare Turnstile secret key. When defined, takes precedence over
  the value stored in plugin settings (Form -> Anti-spam -> Turnstile
  secret key) so the secret never lands in the options table.

  define('BSPE_CONNECT_TURNSTILE_SECRET', '0x4AAAAAAA...');

== Phase 6 status ==

This release is the first stable client release:

* All Phase 1-5 features (admin shell, contact bar, welcome bubble,
  form modal, anti-spam, full settings UI, submissions table, CSV export,
  analytics REST endpoint and dashboard)
* GitHub Actions release workflow auto-builds and attaches a clean
  bspe-connect.zip on every tag push (uses .distignore to strip dev files)
* Auto-update flag — releases with "Auto-Update: yes" in their notes
  install silently via WP's auto_update_plugin filter; everything else
  surfaces as a one-click manual update
* URL redaction filter for analytics page_url — sensitive query keys
  (token, password, email, etc.) are replaced with [redacted] before
  storage; extensible via the bspe_connect_redact_query_keys filter
* Turnstile secret can live in wp-config.php instead of options
* Documented wp-config.php constants and acceptance checklist in this
  readme

== Acceptance checklist (spec section 13) ==

Run through this list before installing on a new client site:

* [ ] Plugin activates without errors on PHP 8.0, 8.1, 8.2, 8.3
* [ ] Plugin activates without errors on WP 6.0, 6.4, 6.6, latest
* [ ] Default settings written on activation
* [ ] DB tables wp_bspe_connect_submissions and wp_bspe_connect_events
      created on activation
* [ ] Tables and options removed via uninstall.php
* [ ] Bar appears after 200px scroll on mobile only
* [ ] Bar hides on scroll up, shows on scroll down
* [ ] Welcome bubble appears 3s after bar, dismisses, persists per session
* [ ] All 4 buttons render and function (call dialer, sms app, two
      bottom-sheet form modes)
* [ ] Connect button toggles between text label and image mode
* [ ] Form submits via AJAX, validates, sanitizes, sends email, stores
      in DB
* [ ] Honeypot blocks submissions with the bspe_website field filled
* [ ] Time check blocks submissions under 2 seconds
* [ ] Rate limit blocks after 5 submissions per hour per IP
* [ ] Turnstile verifies when enabled (with site + secret keys)
* [ ] All admin pages require manage_options capability
* [ ] All form / settings POSTs verify nonces
* [ ] All output is escaped
* [ ] CSV export works on submissions page (filters honored)
* [ ] Analytics records events via /wp-json/bspe-connect/v1/event
* [ ] Analytics dashboard funnel + tiles + top pages render correctly
* [ ] Plugin-update-checker connects to private GitHub repo with token
* [ ] Standard update flow: tag, GitHub Actions builds zip,
      WP detects update, one-click update succeeds
* [ ] Auto-update flow: tagged release with "Auto-Update: yes"
      in the notes installs silently within 12 hours
* [ ] No PHP notices / warnings in debug.log under WP_DEBUG=true
* [ ] No JS console errors on the bar or in admin
* [ ] No CSS conflicts with major themes (Astra, GeneratePress, Divi,
      Elementor's Hello)

== Releasing a new version ==

1. Update Plugin Version + BSPE_CONNECT_VERSION in bspe-connect.php
2. Update Stable tag + Changelog block in this file
3. Commit with the conventional message format
4. git tag -a vX.Y.Z and push the tag
5. gh release create vX.Y.Z (the GitHub Actions workflow will attach
   the clean bspe-connect.zip to the release)
6. To enable silent auto-install on client sites, add the marker line
   "Auto-Update: yes" in the release notes body

== Changelog ==

= 2.1.2 =
* Connect button simplified: removed the "Custom image" mode and the
  Media Library picker (it wasn't working reliably). Connect is now
  label + optional library icon only. Default Icon library for the
  Connect button changed from "Brand SVGs" to "No icon (label only)"
  so the prominent first button defaults to a clean text-only CTA.
* Visual icon picker for every library. Choosing Font Awesome,
  Ionicons, or Dripicons in the Icon library select now shows the
  curated icons rendered live (using the library's actual CSS / web
  component) — no more typing slugs blind. Each button has its own
  curated set:
    Connect / Text — comments / chatbubbles / message variants
    Call           — phone / mobile / call variants
    Email          — envelope / mail / paper-plane / at variants
* Brand SVG library trimmed from 4 to 3 variants per button. The 4th
  variant (connect-4 / call-4 / etc.) was the noisiest of the set;
  3 keeps the picker visually balanced. Existing settings pointing
  to *-4 fall back to *-1 on the next save.
* Bar trigger switched from scroll-based to time-delay-based. Old
  behavior: bar appeared after the visitor scrolled past 200px and
  hid on scroll-up. New behavior: bar shows after a configurable
  delay (default 3 seconds) and stays visible. Removed the scroll
  threshold and "hide on scroll up" settings; added "Show after
  delay (seconds)" in General -> Display behavior.
* Admin Buttons tab: enqueues Font Awesome / Ionicons / Dripicons
  CDNs only when active so the visual picker previews render
  real glyphs.

= 2.1.1 =
* Fix: bar button labels disappeared on mobile because the form's
  visually-hidden label rule (added in 2.1.0) wasn't scoped to the
  form container — it matched every `.bspe-connect__label` on the
  page, including the ones under each bar button. Re-scoped to
  `.bspe-connect__form .bspe-connect__label` so the form fields
  stay screen-reader-accessible while the bar labels render.
* Feature: live-swap icon picker. Changing the Icon library select
  now hides / shows the matching picker (brand 4-radio vs custom
  icon-name text input) without requiring a save and reload. Powered
  by data-bspe-icon-pane attributes on each row + a small JS handler.
* Feature: "No icon (label only)" option in the Icon library select.
  Pick this on any button to skip the icon entirely and let the label
  carry the meaning — useful for "Reserve" or "Quote" style CTAs.
  Label-only buttons get bumped font-size, weight, and uppercase
  treatment so they don't look stranded.
* Updater: GitHub PAT is now OPTIONAL. The repo is public, so updates
  poll without authentication. Existing installs that have a token
  defined keep using it (which raises the GitHub API rate limit from
  60/hr anonymous to 5,000/hr). Sites without a token now also get
  one-click updates with no setup. Removed the "GitHub token not
  configured" admin notice — no longer accurate or needed.

= 2.1.0 =
* Icon libraries: each button can now render its icon from Font Awesome
  6 Free (Solid + Regular variants), Ionicons 7 (Filled + Outline), or
  Dripicons in addition to the bundled brand SVG set. CDN scripts /
  styles are only enqueued when at least one button uses that library.
  Per-button library is set under Buttons -> {button} -> Icon library;
  the icon name field accepts the kebab-case identifier from each
  library's docs (e.g. "comments" for Font Awesome, "chatbubbles" for
  Ionicons, "dripicons-message" for Dripicons).
* Connect button now uses the brand Logo Teal directly with a small
  right-pointing chevron extension that hints at the rest of the bar.
* Bubble close X moved to the absolute top-right corner so the avatar +
  message can use the full width of the bubble.
* Form modal: optional subheading line under the heading, configurable
  per source (text vs email). Default heading is now "Send us an email"
  / "Send us a text" and default subheading is "Please enter your name
  and contact info." for both.
* Form fields: visible labels replaced with screen-reader-only labels
  + placeholders for visible cue. Required fields show a "*" suffix in
  the placeholder.
* Cloudflare Turnstile widget now stretches to fill the form column
  instead of being centered awkwardly.
* New Design controls: Icon size (12-48 px, default 18) and Label size
  (8-20 px, default 11). Outputs as CSS custom properties so any future
  per-page override can hook into them.
* Connect button label color is now under the #bspe-connect specificity
  prefix so themes setting span color via class selectors (e.g.
  Elementor) don't bleed into the bar's label color.

= 2.0.2 =
* Fix: Elementor's per-kit inline CSS uses descendant selectors like
  `.elementor-kit-9604 button { ... }` (specificity 0,1,1) which beat
  our class-only `.bspe-connect__btn` rule (0,1,0) — leaving the bar's
  Connect and Email buttons rendered with the kit's light-gray fill,
  modal close buttons unstyled, and form inputs styled with Elementor's
  zero-padding underline-only treatment. Re-prefixed every <button>-
  and <input>-targeting rule with `#bspe-connect ` (the wrapper's id)
  so our specificity (1,1,0+) wins against any descendant override
  from page-builders / themes. Verified against the Hello Elementor +
  Elementor Pro stack.

= 2.0.1 =
* Fix: iOS Safari rendered Connect and Email buttons (and the bubble's
  close button) with the native UA stylesheet because -webkit-appearance
  was never reset. Added explicit appearance:none and a no-radius/no-pad
  reset to all <button>-rendered controls so they pick up the brand
  Plum / Teal styling consistently. The X icon inside the bubble close
  button now renders.
* Design: Connect button now uses the brand "Pop / CTA" color (Logo Teal)
  instead of a near-imperceptible 90/10 plum-teal mix, so it visually
  separates from Call/Text/Email as the primary action.
* Sizing: shrank icons from 22px to 20px, reduced gap between icon and
  label from 4px to 2px, tightened button padding (8/6/10 -> 6/4/7),
  and dropped the bar min-height from 60px to 52px so the bar takes
  less of the mobile viewport.
* Compat: added a @supports fallback so the hover/focus tints don't
  blank out on Safari < 16.2 (which lacks color-mix()).

= 2.0.0 =
* Phase 6: first stable release. Adds GitHub Actions workflow that builds
  and attaches a clean bspe-connect.zip to every tag release (uses
  .distignore so the install matches production exactly). Implements the
  Auto-Update: yes flag — releases marked with this string in their
  release notes (or readme changelog block) install silently via WP's
  auto_update_plugin filter; everything else stays as a manual one-click
  update. Adds a URL redaction pass on analytics page_url to strip
  sensitive query keys (token, password, email, etc.) before storage,
  extensible via the bspe_connect_redact_query_keys filter. Reads
  BSPE_CONNECT_TURNSTILE_SECRET from wp-config.php with precedence over
  the options-stored secret. Documents all three wp-config.php constants
  and the spec section 13 acceptance checklist in this readme.

= 1.5.0 =
* Phase 5: analytics. New Events class for the wp_bspe_connect_events
  table (insert + per-type counts + distinct-session counts + top-pages
  + daily histogram). New Rest class registering POST
  /wp-json/bspe-connect/v1/event with same-host page_url filter and
  per-IP rate limiting. New Analytics_Controller with funnel-stage
  resolution. Sidebar gets a 7th tab "Analytics" (Submissions tab keeps
  its inbox icon; analytics gets the bar-chart icon). Frontend JS
  generates a session_id (crypto.randomUUID with Math.random fallback)
  and POSTs every event in fire() with keepalive so unloads don't
  drop them.

= 1.4.0 =
* Phase 4: full admin settings UI. All six tabs functional with per-tab
  sanitization. Adds reusable Components helpers (toggle switch, checkbox,
  radio pills, icon radio, color picker, media library picker, number with
  suffix, select). Adds the Submissions list with filters, paginated table
  with expandable rows, and CSV export. Saved-state notice with brand-tinted
  styling. Conditional field handling (required disabled when visible off,
  avatar field shown only when "Show avatar" is on).

= 1.3.0 =
* Phase 3: bottom-sheet lead capture modal, AJAX submission handler with
  the full anti-spam pipeline (nonce, honeypot, time trap, rate limit,
  Cloudflare Turnstile), HTML mailer with template variables and header
  safety, phone live mask with 10-digit US validation, and the success
  state with auto-close after 3 seconds.

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
