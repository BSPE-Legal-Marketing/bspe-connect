# BSPE Connect — WordPress Plugin Build Spec

**Plugin name:** BSPE Connect
**Slug:** `bspe-connect`
**Text domain:** `bspe-connect`
**Version:** 1.0.0
**Target:** WordPress 6.0+, PHP 8.0+

A mobile-only contact bar plugin for WordPress, built for BSPE Legal Marketing's law firm clients. Adds a bottom-fixed bar with up to 4 contact buttons (Connect, Call, Text, Email), an optional welcome bubble, and a built-in lead capture form. Designed to be installed across multiple client sites with self-hosted updates from a private GitHub repo.

---

## 1. High-level behavior

- Mobile-only widget. Hidden on viewports above 768px.
- Bottom-fixed bar with 4 buttons in a single row. Each button is independently enable-able.
- Welcome bubble appears 3 seconds after the bar first becomes visible. Dismissible. Dismissal persists for the session only.
- Bar appears after the user scrolls down 200px. Hides when scrolling up. Reappears when scrolling down.
- Forms (Text and Email) open as bottom-sheet modals.
- All form submissions stored in the WordPress database AND emailed via `wp_mail`.
- Self-updates from a private GitHub repo using the `plugin-update-checker` library.

---

## 2. File structure

```
bspe-connect/
├── bspe-connect.php
├── readme.txt
├── uninstall.php
├── composer.json
├── vendor/
│   └── plugin-update-checker/        (committed)
│
├── includes/
│   ├── class-plugin.php
│   ├── class-activator.php
│   ├── class-deactivator.php
│   ├── class-settings.php
│   ├── class-frontend.php
│   ├── class-form-handler.php
│   ├── class-mailer.php
│   ├── class-submissions.php
│   ├── class-analytics.php
│   ├── class-updater.php
│   └── class-rest.php
│
├── admin/
│   ├── class-admin.php
│   ├── views/
│   │   ├── settings-general.php
│   │   ├── settings-buttons.php
│   │   ├── settings-form.php
│   │   ├── settings-design.php
│   │   ├── settings-display.php
│   │   ├── submissions-list.php
│   │   └── analytics.php
│   └── assets/
│       ├── admin.css
│       └── admin.js
│
├── public/
│   ├── assets/
│   │   ├── bspe-connect.css
│   │   ├── bspe-connect.js
│   │   └── icons/
│   └── templates/
│       ├── bar.php
│       ├── welcome-bubble.php
│       ├── form-modal.php
│       └── success-state.php
│
└── languages/
```

All output CSS classes namespaced under `.bspe-connect` to prevent theme collisions. JavaScript in vanilla JS, no jQuery dependency.

---

## 3. Database schema

Two custom tables, prefixed `{$wpdb->prefix}bspe_connect_`.

### `wp_bspe_connect_submissions`

| Column         | Type                       | Notes                                       |
|----------------|----------------------------|---------------------------------------------|
| id             | BIGINT UNSIGNED AUTO_INC PK |                                             |
| submitted_at   | DATETIME NOT NULL          |                                             |
| source_button  | VARCHAR(20)                | 'text' or 'email'                           |
| name           | VARCHAR(255)               |                                             |
| phone          | VARCHAR(20)                | Digits only, no formatting                  |
| email          | VARCHAR(255)               |                                             |
| message        | TEXT                       |                                             |
| contact_pref   | VARCHAR(20)                | Nullable. 'phone' / 'text' / 'email' / null |
| page_url       | VARCHAR(500)               | Page where submission happened              |
| user_agent     | VARCHAR(500)               |                                             |
| ip_hash        | VARCHAR(64)                | SHA-256 of IP. Never store raw IP           |
| mail_status    | VARCHAR(20)                | 'sent' / 'failed' / 'pending'               |

Indexes: `submitted_at`, `source_button`.

### `wp_bspe_connect_events`

| Column      | Type                       | Notes                       |
|-------------|----------------------------|-----------------------------|
| id          | BIGINT UNSIGNED AUTO_INC PK |                             |
| event_type  | VARCHAR(50)                | See event types below       |
| occurred_at | DATETIME NOT NULL          |                             |
| page_url    | VARCHAR(500)               |                             |
| session_id  | VARCHAR(64)                | Client-generated, anonymous |

Indexes: `(event_type, occurred_at)`, `occurred_at`.

**Event types tracked:**
`bar_shown`, `bubble_shown`, `bubble_dismissed`, `connect_click`, `call_click`, `text_click`, `email_click`, `form_open`, `form_submit`, `form_success`, `form_error`.

Tables created in `class-activator.php` using `dbDelta()`. Tables NOT removed on deactivation. Removed only via `uninstall.php` for a clean uninstall.

### Settings storage

A single `wp_options` row under key `bspe_connect_settings`. Value is a serialized PHP array with all settings. Autoloaded.

---

## 4. Settings page

Top-level admin menu item: **BSPE Connect**. Six tabs.

### Tab 1: General

- **Master enable** (checkbox): on/off for the entire bar
- **Welcome bubble**
  - Show welcome bubble (checkbox)
  - Heading text input. Default: `Welcome to {firm_name}`
  - Message text input. Default: `I'm here if you have any questions or need help!`
  - Show avatar (checkbox)
  - Avatar selector (WordPress Media Library button)
  - Trigger: radio (Auto-show after delay / Click Connect to open)
  - Delay (number, seconds). Default: 3
  - Repeat: radio (Once per session / Once ever / Always). Default: Once per session
- **Display behavior**
  - Show after scroll (number, px). Default: 200
  - Hide on scroll up (checkbox). Default: on
  - Mobile breakpoint (number, px). Default: 768

### Tab 2: Buttons

For each of 4 buttons, a settings card.

**Connect button:**
- Enable (checkbox)
- Mode: radio (Text label / Image)
- Label text input. Default: `Connect`
- Image selector (Media Library)
- Icon: radio with 4 SVG previews

**Call button:**
- Enable (checkbox)
- Phone number input with mask `(xxx) xxx-xxxx`
- Label text input. Default: `Call`
- Icon: radio with 4 SVG previews

**Text button:**
- Enable (checkbox)
- Mode: radio (SMS link / Inline form)
- Phone number input (only shown when SMS mode selected)
- Label text input. Default: `Text`
- Icon: radio with 4 SVG previews
- SMS body prefill: leave empty for MVP. No setting.

**Email button:**
- Enable (checkbox)
- Label text input. Default: `Email`
- Icon: radio with 4 SVG previews

Icon SVG library lives in `public/assets/icons/`. Provide 4 variants per button type (connect, call, text, email): clean line icons, ~24x24px, single-color (currentColor for theming).

### Tab 3: Form

**Form fields configuration:**

| Field             | Visible | Required | Notes                          |
|-------------------|---------|----------|--------------------------------|
| Name              | toggle  | toggle   | Default: visible + required    |
| Phone (masked)    | toggle  | toggle   | Default: visible + required    |
| Email             | toggle  | toggle   | Default: visible + required    |
| Preferred contact | toggle  | toggle   | Default: HIDDEN, not required  |
| Message           | toggle  | toggle   | Default: visible + required    |

Each field has two checkboxes: "Show field" and "Required". If "Show field" is unchecked, "Required" is grayed out.

Field order in rendered form (fixed):
1. Name
2. Phone
3. Email
4. Preferred contact
5. Message

Phone mask: `(xxx) xxx-xxxx`. US-only. Live mask via JS, sanitized to digits server-side. Server validates length is exactly 10 digits.

Preferred contact dropdown options when enabled: `Any`, `Phone`, `Text`, `Email`. Default: `Any`.

**Headings and copy:**
- Text form heading. Default: `Send a text`
- Email form heading. Default: `Send an email`
- Submit button label. Default: `Send`
- Success message. Default: `Thanks. We'll be in touch shortly.`

**Email delivery:**
- Send to (text input, comma-separated for multiple recipients)
- Subject. Default: `New lead from {site_name}: {source}`
- From email
- From name. Default: `{site_name}`

Available template variables: `{site_name}`, `{firm_name}`, `{source}` (text/email), `{page_url}`, `{name}`, `{phone}`, `{email}`, `{message}`, `{contact_pref}`.

**Anti-spam:**
- Honeypot field (checkbox, on by default)
- Reject submissions under N seconds (number, default 2)
- Rate limit per IP (number per hour, default 5)
- Cloudflare Turnstile (checkbox)
  - Site key (text)
  - Secret key (text)

### Tab 4: Design

- Firm name (text input). Used in welcome bubble and templates as `{firm_name}`
- **Colors** (6 color pickers):
  - Bar background
  - Bar button background
  - Button text/icon color
  - Bubble background
  - Bubble text color
  - Accent color (focus rings, active states)
- **Typography:**
  - Font: radio (Inherit from theme / Google Font)
  - Google Font dropdown (only shown when Google Font selected). Curated list of 15 to 20 web-safe options including DM Sans, Inter, Lato, Roboto, Open Sans, Source Sans 3, Poppins, Manrope, Nunito, Work Sans, Plus Jakarta Sans, IBM Plex Sans, Figtree, Montserrat, Public Sans

Default: Inherit from theme.

### Tab 5: Display rules

Where the bar appears. Radio options:

- Site-wide (default)
- Pages only
- Posts only
- Pages only, except: (textarea, comma-separated slugs)
- Posts only, except: (textarea, comma-separated slugs)
- Site-wide, exclude pages: (textarea)
- Site-wide, exclude posts: (textarea)

Slug input accepts page slugs separated by commas or newlines. Whitespace tolerant.

### Tab 6: Submissions + Analytics

Two sub-pages.

**Submissions:**
- Paginated table (25 per page)
- Columns: Date, Source, Name, Phone, Email, Page, Mail status
- Filters: date range, source button (text/email), mail status
- Click row to expand and see full message + contact preference
- CSV export button (exports current filtered view)

**Analytics:**
- Time window selector: 7 / 30 / 60 / 90 days
- Counts displayed for each event type
- Conversion funnel visualization:
  ```
  Bar shown -> Button click (any) -> Form opened -> Form submitted -> Form success
  ```
- Top 10 pages by submission count
- Top 10 pages by bar impressions

---

## 5. Frontend behavior

### Bar

- `position: fixed; bottom: 0; left: 0; right: 0; z-index: 9998;`
- Min-height: 56px
- Buttons distributed equally (flexbox, equal flex-grow)
- Hidden by default (translateY(100%)); revealed by adding active class
- Show/hide logic:
  - On scroll: if scrollY > setting.scrollThreshold AND scrolling down -> show
  - If scrolling up -> hide
  - Debounce scroll handler at 100ms
  - Slide-up transition 250ms ease-out
- Mobile-only: hidden via CSS media query above breakpoint setting

### Welcome bubble

- `position: fixed; bottom: 56px + 8px; left: 8px; right: 8px;` (sits directly above bar)
- Z-index: 9999 (above bar)
- Hidden by default
- Appears 3 seconds (or configured delay) after `bar_shown` event fires
- Animation: fade-in + 8px slide-up, 200ms
- Close button (X) in top-right, 32px tap target
- On close: add bubble dismissal flag to `sessionStorage` under key `bspe_connect_bubble_dismissed`
- Bubble respects "Once per session" / "Once ever" / "Always" repeat setting
  - Once per session: sessionStorage check
  - Once ever: localStorage check
  - Always: shown every page load 3s after bar appears
- Optional avatar: 40px circle, left of text, `border-radius: 50%`
- Heading: bold, 14px
- Message: regular, 13px
- Triangle pointer at bottom of bubble pointing to Connect button

### Forms (Text and Email)

Open as bottom-sheet modals.

- Backdrop: `position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10000;`
- Click backdrop closes modal
- Modal: `position: fixed; bottom: 0; left: 0; right: 0; max-height: 85vh; z-index: 10001;`
- Border-radius: 16px 16px 0 0
- Slides up from bottom on open (200ms ease-out)
- Slides down on close
- Inside the modal:
  - Form heading at top
  - Close button (X) top-right
  - Form fields rendered per settings
  - Submit button full-width at bottom

**Field rendering:**
- Phone input: `inputmode="tel"`, live mask `(xxx) xxx-xxxx` via JS
- Email input: `type="email" inputmode="email"`
- Message: `<textarea>`, 4 rows
- Required fields marked with `*` after label
- Validation errors shown inline, in red, below the field

**Hidden fields included in every form:**
- `bspe_connect_nonce` (WP nonce)
- `bspe_connect_form_ts` (timestamp at render)
- `bspe_website` (honeypot, hidden via CSS, must be empty)
- `bspe_source` (text/email)

**Submit flow:**
1. Client-side validate
2. POST to `admin-ajax.php?action=bspe_connect_submit` (FormData)
3. Submit button disabled, shows spinner
4. On success: replace form with success state, auto-close modal after 3 seconds
5. On error: re-enable button, show error above form, preserve user input

**Success state:**
- Green checkmark icon
- Success message text from settings
- Modal auto-closes after 3 seconds

### SMS button

`<a href="sms:+1XXXXXXXXXX">` (no body prefill).

Phone number formatted as E.164 (`+1` prefix + 10 digits) on the server side when rendered.

JS click handler fires `text_click` analytics event before navigation.

### Call button

`<a href="tel:+1XXXXXXXXXX">`.

JS click handler fires `call_click` analytics event before navigation.

---

## 6. AJAX and REST endpoints

### Form submission

- Action: `bspe_connect_submit`
- Method: POST
- Nonce-protected via `wp_create_nonce('bspe_connect_form')` and `check_ajax_referer`
- Public action (`wp_ajax_nopriv_bspe_connect_submit` AND `wp_ajax_bspe_connect_submit`)

Response JSON:
```json
{ "success": true, "message": "Thanks. We'll be in touch shortly." }
```
or
```json
{ "success": false, "errors": { "email": "Invalid email address" } }
```

### Analytics

REST namespace: `bspe-connect/v1`

`POST /event`
- No authentication
- Public, rate-limited per IP (60/minute)
- Body: `{ event_type: string, page_url: string, session_id: string }`
- Validates event_type against whitelist
- Returns `{ ok: true }` always (no info leakage)

No other public REST endpoints. Analytics retrieval and submission management is server-rendered only, behind capability checks.

---

## 7. Security requirements

Strict enforcement at build time.

**Every PHP file:**
```php
defined('ABSPATH') || exit;
```

**Input handling:**
- All `$_POST` values sanitized with appropriate function:
  - `sanitize_text_field()` for short strings
  - `sanitize_email()` for email
  - `sanitize_textarea_field()` for message
  - Phone: strip all non-digits with `preg_replace('/\D/', '', ...)`, then validate length === 10
- Validate types and lengths before storing
- Never trust client-side validation

**Output escaping:**
- `esc_html()` for text content
- `esc_attr()` for attributes
- `esc_url()` for URLs
- `esc_textarea()` for textarea values
- No raw user input rendered without escaping, ever

**Database:**
- All queries use `$wpdb->prepare()` with placeholders
- Never concatenate user input into SQL
- Use `$wpdb->insert()` and `$wpdb->update()` where possible (auto-prepares)

**Capability checks:**
- Every admin page: `current_user_can('manage_options')`
- Every settings save handler: nonce verified + capability checked
- Every submission view/export: capability checked

**wp_mail header safety:**
- From email and From name come from settings only, never from user input
- Subject built from template, but template variables that include user input are sanitized
- `\r` and `\n` stripped from any header value to prevent header injection

**Anti-spam stack (in order, at form submission):**
1. Nonce check. Fail = reject.
2. Honeypot check. If `bspe_website` is non-empty = silently reject (return success to bot).
3. Time check. If `now() - form_ts < 2 seconds` = silently reject.
4. Rate limit. Hash IP, look up transient `bspe_connect_rl_{hash}`. If count >= limit = reject with generic error.
5. Turnstile (if enabled). Verify token server-side. Fail = reject.
6. Field validation. Required fields present, formats valid.
7. Save to DB.
8. Send email.
9. Increment rate limit counter as transient with 1-hour TTL.

**IP handling:**
- Capture from `$_SERVER['REMOTE_ADDR']`, account for trusted proxy headers if WP is configured to trust them
- Hash with `hash('sha256', $ip)` before any storage
- Raw IP held in memory only for rate-limit-key generation, never written

**File-level safety:**
- No file uploads in this plugin
- No `eval()`, `extract()`, `create_function()`
- No `unserialize()` of user-controlled data
- No `include`/`require` paths built from user input

---

## 8. Self-update mechanism

Library: [`YahnisElsts/plugin-update-checker`](https://github.com/YahnisElsts/plugin-update-checker) v5.x. Vendored under `vendor/`.

**Setup in `class-updater.php`:**

- Repo: **private GitHub repo** under BSPE's organization
- GitHub Personal Access Token: NOT bundled in the plugin code. Read from a constant defined in `wp-config.php` per site:
  ```php
  define('BSPE_CONNECT_GITHUB_TOKEN', '...');
  ```
- If constant is undefined, plugin still works but skips update checks (logs admin notice)
- Update check interval: 12 hours (default)
- Two release branches: `stable` and `beta`
- Channel selectable via constant or hidden setting:
  ```php
  define('BSPE_CONNECT_UPDATE_CHANNEL', 'stable'); // or 'beta'
  ```
  Default: `stable` if undefined.

**Auto-update behavior:**

Releases include a header comment marker in `bspe-connect.php` or in the GitHub release notes that signals auto-update preference:

- Releases tagged with `Auto-Update: yes` in release notes -> silent auto-update via `auto_update_plugin` filter
- Releases without the tag -> standard "Update available" notice in admin, manual one-click update

Implementation: filter `auto_update_plugin` hooks into the WP core auto-update flow. When checking the BSPE Connect plugin, parse the latest release metadata; if it has the auto-update flag, return `true`. Otherwise return the default WP behavior.

**Setup steps for distribution (documented in readme.txt):**

1. Add `define('BSPE_CONNECT_GITHUB_TOKEN', 'ghp_...');` to client site's `wp-config.php`
2. Optional: `define('BSPE_CONNECT_UPDATE_CHANNEL', 'beta');` to opt into beta channel
3. Install plugin from zip
4. Activate

---

## 9. Default settings on activation

```php
[
  'enabled' => false,  // Off until configured
  'welcome_bubble' => [
    'enabled' => true,
    'heading' => 'Welcome to {firm_name}',
    'message' => "I'm here if you have any questions or need help!",
    'show_avatar' => false,
    'avatar_id' => 0,
    'trigger' => 'auto',          // 'auto' | 'click'
    'delay' => 3,
    'repeat' => 'session',         // 'session' | 'once' | 'always'
  ],
  'display' => [
    'scroll_threshold' => 200,
    'hide_on_scroll_up' => true,
    'mobile_breakpoint' => 768,
  ],
  'buttons' => [
    'connect' => [ 'enabled' => true,  'mode' => 'text', 'label' => 'Connect', 'image_id' => 0, 'icon' => 'connect-1' ],
    'call'    => [ 'enabled' => true,  'phone' => '',    'label' => 'Call',    'icon' => 'call-1' ],
    'text'    => [ 'enabled' => true,  'mode' => 'sms',  'phone' => '',        'label' => 'Text', 'icon' => 'text-1' ],
    'email'   => [ 'enabled' => true,  'label' => 'Email', 'icon' => 'email-1' ],
  ],
  'form' => [
    'fields' => [
      'name'         => [ 'visible' => true,  'required' => true ],
      'phone'        => [ 'visible' => true,  'required' => true ],
      'email'        => [ 'visible' => true,  'required' => true ],
      'contact_pref' => [ 'visible' => false, 'required' => false ],
      'message'      => [ 'visible' => true,  'required' => true ],
    ],
    'text_heading'  => 'Send a text',
    'email_heading' => 'Send an email',
    'submit_label'  => 'Send',
    'success_msg'   => "Thanks. We'll be in touch shortly.",
    'mail_to'       => '',
    'mail_subject'  => 'New lead from {site_name}: {source}',
    'mail_from'     => '',
    'mail_from_name' => '{site_name}',
    'antispam' => [
      'honeypot' => true,
      'min_seconds' => 2,
      'rate_limit' => 5,
      'turnstile_enabled' => false,
      'turnstile_site_key' => '',
      'turnstile_secret_key' => '',
    ],
  ],
  'design' => [
    'firm_name' => '',
    'colors' => [
      'bar_bg'      => '#351E28',
      'button_bg'   => '#351E28',
      'button_fg'   => '#FAF7F2',
      'bubble_bg'   => '#FAF7F2',
      'bubble_fg'   => '#351E28',
      'accent'      => '#3AAFB9',
    ],
    'font_mode' => 'inherit',  // 'inherit' | 'google'
    'google_font' => 'DM Sans',
  ],
  'display_rules' => [
    'mode' => 'sitewide',  // 'sitewide' | 'pages_only' | 'posts_only' |
                            // 'pages_except' | 'posts_except' |
                            // 'sitewide_except_pages' | 'sitewide_except_posts'
    'slugs' => '',
  ],
]
```

---

## 10. Build phases (recommended order for Claude Code)

Hand each phase as a separate task. Validate each phase end-to-end before starting the next.

### Phase 1: Skeleton

- Plugin header in `bspe-connect.php`
- Activation/deactivation hooks in `class-activator.php` / `class-deactivator.php`
- DB tables created via `dbDelta()`
- Default settings written on first activation
- Settings class with all options registered (settings stored, but no UI yet)
- Empty admin pages with all 6 tabs and tab navigation
- Plugin-update-checker library integrated with placeholder repo URL
- `uninstall.php` removes all options + tables

### Phase 2: Frontend rendering

- Bar HTML/CSS (positioning, layout, button rendering per settings)
- Welcome bubble HTML/CSS
- Vanilla JS for:
  - Scroll trigger (show after threshold)
  - Hide on scroll up, show on scroll down (debounced)
  - Welcome bubble timing and dismissal (sessionStorage / localStorage)
  - Mobile breakpoint enforcement
- Display rules logic (`should_show_bar()` server-side method)
- Inline CSS variables driven by settings (colors, font)
- Google Font enqueueing when font_mode === 'google'

### Phase 3: Forms

- Bottom-sheet modal HTML/CSS/JS
- Form rendering per settings (visibility + required state per field)
- Phone live mask
- Honeypot field
- Form timestamp hidden field
- Nonce in form
- AJAX submission handler in `class-form-handler.php`
- Server-side validation, sanitization, anti-spam pipeline
- DB write to submissions table
- Email send via `class-mailer.php` with template variable substitution
- Success state UI
- Inline error display

### Phase 4: Settings UI

- All 6 tabs functional
- Color pickers (use WP's `wp_color_picker`)
- Google Font dropdown
- SVG icon radio selectors with previews
- Media Library integration for avatar and Connect image
- Submissions table with pagination, filters, expand-row, CSV export
- All saves nonce-protected and capability-checked
- Settings sanitization in `class-settings.php`

### Phase 5: Analytics

- Frontend event firing in JS for all 11 event types
- Anonymous session_id generation client-side (random UUID, sessionStorage)
- REST endpoint to receive events (`POST /event`)
- Rate limiting on the endpoint (transient-based, 60/min per hashed IP)
- Analytics dashboard page with:
  - Date range selector
  - Per-event-type counts
  - Conversion funnel visualization
  - Top pages tables

### Phase 6: Polish and release

- Test on a staging site end-to-end
- Verify update mechanism works:
  - Push v1.0.1 to private repo `stable` branch
  - Confirm WP detects update
  - Confirm one-click update succeeds
  - Push v1.0.2 with `Auto-Update: yes` flag
  - Confirm silent auto-update fires
- Set up GitHub Actions to auto-create release zips on tag
- Document `wp-config.php` constants in readme.txt
- Tag v1.0.0 on GitHub
- Install on first BSPE client site

---

## 11. Coding standards

- WordPress Coding Standards (WPCS) for PHP
- All strings translatable: `__()`, `_e()`, `esc_html__()`, `esc_html_e()`
- Text domain: `bspe-connect`
- No global functions; everything in classes under namespace `BSPE\Connect\`
- PSR-4 autoloading via Composer (or manual `class-` file loader if avoiding Composer at runtime)
- All public methods documented with PHPDoc
- No use of jQuery in frontend JS (vanilla only)
- CSS: BEM-style class names, all under `.bspe-connect-` prefix
- All assets enqueued via `wp_enqueue_script` / `wp_enqueue_style` with version strings
- Frontend assets only enqueued when bar would actually render (check display rules first)

---

## 12. Out of scope for v1

Document these as future work, do not build:

- Multilingual support (i18n strings exist but only English shipped)
- A/B testing of bar variants
- Drag-and-drop visual builder
- Block editor (Gutenberg) integration
- Multiple bar layouts/positions
- Desktop variant
- Custom icon uploads (only the 4 curated SVGs per button)
- Webhook delivery of submissions
- Integration with Ninja Forms / Gravity Forms
- Custom CSS field
- Per-page bar variant overrides
- Import/export settings
- International phone formats (US only)

---

## 13. Acceptance checklist (run before tagging v1.0.0)

- [ ] Plugin activates without errors on PHP 8.0, 8.1, 8.2, 8.3
- [ ] Plugin activates without errors on WP 6.0, 6.4, 6.6, latest
- [ ] Default settings written on activation
- [ ] DB tables created on activation
- [ ] Tables and options removed via uninstall.php
- [ ] Bar appears after 200px scroll on mobile only
- [ ] Bar hides on scroll up, shows on scroll down
- [ ] Welcome bubble appears 3s after bar, dismisses, persists per session
- [ ] All 4 buttons render and function (call, sms, form, form)
- [ ] Connect button toggles between text mode and image mode
- [ ] Form submits via AJAX, validates, sanitizes, sends email, stores in DB
- [ ] Honeypot blocks submissions with filled field
- [ ] Time check blocks submissions under 2 seconds
- [ ] Rate limit blocks after 5 submissions per hour per IP
- [ ] Turnstile verifies when enabled
- [ ] All admin pages require `manage_options` capability
- [ ] All form/settings POSTs verify nonces
- [ ] All output is escaped
- [ ] CSV export works on submissions page
- [ ] Analytics records events and dashboard renders correctly
- [ ] Plugin-update-checker connects to private GitHub repo with token
- [ ] Standard update flow: push tag, WP detects, one-click update succeeds
- [ ] Auto-update flow: tagged release with `Auto-Update: yes` updates silently
- [ ] No PHP notices/warnings in debug log
- [ ] No JS console errors
- [ ] No CSS conflicts with major themes (test on Astra, GeneratePress, Divi, Elementor's Hello)

---

## 14. Notes for the developer

- Ship Phase 1 fully working before starting Phase 2. Each phase ends with a testable, deployable plugin.
- Use `WP_DEBUG` and `WP_DEBUG_LOG` during development; the plugin must produce zero warnings under debug.
- All admin styling should match WP core admin patterns. Don't reinvent admin UI.
- The frontend JS bundle size target: under 8KB minified + gzipped. CSS under 6KB. This is a mobile widget; weight matters.
- When in doubt about a feature scope, default to the smaller version. We are shipping v1.0, not v3.0.
- Test on a real iPhone (Safari) and a real Android (Chrome). Mobile emulators lie about touch behavior and viewport math.
