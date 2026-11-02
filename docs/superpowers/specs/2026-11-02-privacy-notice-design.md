# Privacy notice and opt-out (BSPE Connect 3.8.0) — design

Date: 2026-11-02 · Status: approved in brainstorming, awaiting spec review

## Why

A Coalition cyber-insurance scan of btattorneys.com flagged: no cookie banner,
cookies firing before consent, risky tracking, no Global Privacy Control (GPC)
support, no "Do Not Sell" link. Across 31 fleet sites, Clarity runs on 29,
GA/GTM on 31, Meta Pixel on 6, CallRail on 3, and none has a consent banner.

The firm's decision: **US sites use an opt-out model.** Tracking keeps running
from the first page view so no analytics data is lost. This module adds notice,
opt-out, GPC and cheap wiretap-risk reducers. The insurer's "cookies fire before
consent" finding is marked Accepted Risk with these compensating controls.
Strict opt-in (nothing tracks before consent) applies only to the Strict profile
(EU, UK, Turkey, Moldova sites such as kazaavukat.com).

## Scope

In: front-end privacy module in BSPE Connect, configured in its WP admin.

Out (decided): compliance checks (policy page age, footer links, tracker
mentions), Fleet/connector settings and reporting. Settings are WP-admin only.

## Settings — new "Privacy" tab

Stored under `privacy` in `bspe_connect_settings`.

| Key | Type | Default |
|---|---|---|
| `privacy.enabled` | bool | `false` |
| `privacy.profile` | `us` \| `us_ca` \| `strict` | `us` |
| `privacy.policy_url` | url | `get_privacy_policy_url()` when empty |
| `privacy.pixel_off_paths` | textarea, one glob per line | `/contact*`, `/free-consultation*`, `/case-evaluation*` |
| `privacy.auto_link` | bool | `true` |

Notice texts go through gettext so WPML can translate them.

## Architecture

The privacy module works on its own, outside the contact bar:
`should_show_bar()` does not gate it, and it runs on desktop too.

| Unit | Job |
|---|---|
| `includes/class-privacy.php` | Reads settings; prints the inline guard first in `<head>`; enqueues `privacy.js` / `privacy.css`; registers the `[bspe_privacy_choices]` shortcode; prints the auto link. |
| Inline guard (printed by `class-privacy.php`, source in `public/assets/privacy-guard.js`) | Runs before any tracker. Decides blocked vs allowed, sets Consent Mode defaults, intercepts tracker scripts, applies the reducers. |
| `public/assets/privacy.js` | Notice card UI: timing, the open-overlay wait, choice storage, the "Your Privacy Choices" panel. |
| `public/assets/privacy.css` | Card styles; reuses the Design tab's CSS variables. |
| `admin/views/settings-privacy.php` | The Privacy tab. |

### Cache safety (NitroPack, LiteSpeed)

All HTML is identical for every visitor, and every decision is made in the
browser. The guard is printed at `wp_head` priority 1 as an inline `<script>`
with `data-nitro-exclude`, `data-no-optimize="1"`, `data-no-defer="1"` and
`data-cfasync="false"`, so optimizers never delay, combine or defer it. Config
is inlined as JSON in the same tag.

## Visitor state

`localStorage["bspe_privacy"] = {"v":1,"choice":"ok"|"optout"|"accept"|"reject","ts":<ms>}`.
A stored choice expires after 12 months and is then ignored. Any read or write
error is treated as "no choice".

GPC (`navigator.globalPrivacyControl === true`) counts as `optout` without
being stored, so turning GPC off restores the default.

| Profile | No choice | ok / accept | optout / reject / GPC |
|---|---|---|---|
| `us`, `us_ca` | allowed | allowed | **blocked** |
| `strict` | **blocked** | allowed | **blocked** |

## Guard behaviour

**Always, when enabled:**
- Set the Consent Mode v2 default via `dataLayer` / `gtag('consent','default',…)`
  for `analytics_storage`, `ad_storage`, `ad_user_data` and `ad_personalization`:
  `granted` when allowed, `denied` when blocked.
- Apply the reducers below.

**When blocked:**
- Run `window.clarity('consent', false)`; queue it if Clarity is not loaded yet.
- Intercept tracker scripts and turn them into `type="text/plain"` before they
  execute:
  - parser-inserted scripts, through a `MutationObserver` on `document`;
  - dynamically inserted scripts (e.g. GTM custom HTML), by wrapping
    `Node.prototype.appendChild` / `insertBefore` for `<script>` elements.
- Tracker match list (by `src` host or path, plus inline-snippet signatures):
  `clarity.ms`; `cdn.callrail.com` / `swap.js`; `connect.facebook.net` /
  `fbevents.js`; direct `googletagmanager.com/gtag/js`. GTM's own `gtm.js`
  keeps loading, and GTM's GA4 tags honour Consent Mode.

**Live opt-out (no reload):** call `gtag('consent','update',…denied)` and
`clarity('consent', false)` straight away. Scripts already running are blocked
from the next page view.

**Live accept in strict:** call `gtag('consent','update',…granted)` and re-insert
the held scripts as real scripts.

**Known limitation:** a tracker hard-coded above `wp_head` runs before the
guard. For opt-out that is still covered by the consent APIs from the next
page; for GPC and Strict it is not. Check for this per site during rollout.

## Wiretap-risk reducers (whenever enabled, whatever the choice)

- **Clarity masking:** add `data-clarity-mask="true"` to every `form`, `input`,
  `textarea`, `select` and known chat container (Intaker `icw` and the
  configured chat selectors), including ones added later via the observer.
- **Meta Pixel:**
  - On paths matching `privacy.pixel_off_paths`, block `fbevents.js`.
  - Elsewhere, insert `fbq('set','autoConfig',false,<id>)` ahead of each
    `fbq('init',<id>)` by wrapping the `fbq` queue, so Meta's automatic events
    do not read button or form text. This hook must be confirmed in testing; if
    it cannot run reliably before `init`, block the Pixel on all pages that
    contain a form instead.

## Notice card

- **Timing:** shown after 20 s of visible time, counted across pages in
  `sessionStorage`; never on arrival.
- **Waits for open overlays:** chat (`icw--open`), `html.bspe-menu-open`,
  `.elementor-popup-modal`, `.pum-overlay`, `.off-canvas.is-open`, the BSPE
  Connect form sheet. It shows when they close.
- **Frequency:** at most once per browser session until a choice is made.
- **Position:** a small card, bottom-left on desktop and above the contact bar
  on mobile (bar-height variable). No overlay, no scroll lock. `role="region"`,
  labelled, keyboard reachable, `Esc` closes. Equal-weight buttons in Strict.

| Profile | Text | Buttons |
|---|---|---|
| `us` | "We use cookies and analytics to improve this site. Privacy Policy · Opt out" | OK / Opt out |
| `us_ca` | same + "Do Not Sell or Share My Personal Information" | OK / Opt out |
| `strict` | "We'd like to use cookies and analytics to improve this site. Privacy Policy" | Accept / Reject |

- **Opt out:** the card confirms "You've opted out."
- **GPC on:** the card says "Your browser's privacy signal is honoured." and
  the Opt out button is replaced by a confirmation.
- **Closing the card** (✕) in the `us` and `us_ca` profiles counts as OK.

## "Your Privacy Choices"

- Any `a[href$="#privacy-choices"]` (e.g. added to an Elementor footer) and the
  `[bspe_privacy_choices]` shortcode open the choice card at any time.
- **`us_ca`:** the link carries the California opt-out icon (inline SVG).
- **`auto_link` on:** a small text link is printed at the end of `<body>` for
  sites whose footer has not been edited yet.

## Testing

- **Node tests (`tests/privacy/*.test.mjs`, `node --test`, no dependencies):**
  the state table, 12-month expiry, the 20 s cross-page timer, the overlay
  wait, tracker matching, path globbing.
- **Local harness page** with fake GTM, Clarity, CallRail and Pixel tags, run
  in the in-app browser. For each of: no choice, OK, opt-out (same page and next
  page), GPC (injected in the harness), Strict (before and after Accept), check
  the network log.
- **Simulated optimizer** on the harness: confirm the guard still runs first
  with its attributes.

## Rollout

1. Release 3.8.0 with the feature off by default. The plugin's updater never
   installs silently, so nothing changes until a site is updated.
2. Pick one low-traffic site with GA, Clarity and CallRail. Update it, turn the
   notice on (the user flips it in WP admin), and purge the cache.
3. Verify on the live site:
   - With no choice, GA collect, Clarity and CallRail fire as today.
   - After opt-out, none fire from the next page.
   - With GPC (Firefox or Brave), none ever fire.
4. User approval, then the rest of the fleet. Strict profile for
   kazaavukat.com.

## Done when

The module is tested on one low-traffic client site with the three results in
Rollout step 3, and the user has approved the design and the rollout.
