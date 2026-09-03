# Claude auto-translation for WPML pages

Date: 2026-09-03. Target release: v3.8.0.

## Goal

Give a few BSPE client sites a "Translate" tab in BSPE Connect that does what
WPML's translation editor does for one page, but with Claude doing the words
instead of WPML's paid per-word credits. The admin pastes a page URL, picks a
target language (Spanish only for now, list extensible), presses Translate, and
gets a published WPML-linked translation with every field WPML would translate:
title, slug, excerpt, post content, Elementor widget text, SEOPress title and
description, and any WPML-declared translatable custom fields.

## Non-goals

- No frontend runtime translation. Translations are ordinary WP posts.
- No bulk / whole-site translation. One page per run.
- No translation memory or glossary UI (a fixed legal-marketing system prompt
  and the firm name are enough for v1).
- No WPML String Translation (theme strings, menus, widgets).

## Gating

The tab renders its workflow only when all three hold:

1. `Licensing::is_functional()` is true (same as every other utility).
2. WPML is active (`WPML_Status::wpml_active()`).
3. A Claude API key is saved in settings.

Otherwise the tab shows which condition is missing and, for the key, the
settings card so it can be entered.

## Settings

New top-level `translate` key in `bspe_connect_settings`:

```
'translate' => [
    'api_key'         => '',              // Claude API key, never printed back in full
    'model'           => 'claude-opus-5', // allow list: claude-opus-5, claude-sonnet-5
    'default_target'  => 'es',
    'firm_name_hint'  => '',              // optional, passed to the prompt as "do not translate"
]
```

Saved through the existing Settings_Saver (`_tab = translate`). The key field is
a password input; an empty submit keeps the stored key, the literal `CLEAR`
removes it. The view shows only the last 4 characters of the stored key.

## Components

### `includes/class-claude-client.php` (namespace BSPE\Connect)

Thin wrapper over `wp_remote_post` to `https://api.anthropic.com/v1/messages`.
The PHP SDK is not vendored; the plugin already talks HTTP the same way for
licensing and Turnstile. One method:

`translate_batch(array<string,string> $segments, string $target, string $source, array $opts): array|WP_Error`

- Request: `model`, `max_tokens` 16000, `system` (legal-marketing translator
  prompt: preserve HTML tags/attributes, shortcodes, placeholders like
  `{firm_name}`, URLs, emails, phone numbers; keep proper nouns and the firm
  name; return only JSON), `output_config.effort = "medium"`, thinking left at
  the model default (adaptive), `messages` = one user turn containing a JSON
  object `{ "id": "text", ... }`.
- Response: first text block parsed as JSON. Every input id must come back as
  a non-empty string, else WP_Error `bad_response` with the missing ids.
  JSON is requested with `output_config.format` (structured outputs, JSON
  schema with `additionalProperties: {type: string}`), so no prefill.
- Errors: `stop_reason == refusal` → WP_Error `refused`; HTTP 429/529 →
  retried twice with backoff inside the call; other non-2xx → WP_Error with the
  API's `error.message`. Timeout 120s.

### `includes/class-translate-extractor.php`

Pure functions (no WP calls beyond `get_post_meta`) that turn a source post
into an ordered list of segments and, later, write translated segments back.

`extract(int $post_id): array{segments: array<string,string>, map: array}`

Segment ids are stable strings that encode where the text lives:

| id prefix            | source                                                              |
|----------------------|---------------------------------------------------------------------|
| `title`              | post_title                                                          |
| `slug`               | post_name (decoded, spaces)                                         |
| `excerpt`            | post_excerpt                                                        |
| `content:N`          | Nth text run of post_content (see HTML splitting)                   |
| `el:<widgetId>:<key>`| Elementor widget setting `key` inside `_elementor_data`             |
| `meta:<key>[:N]`     | translatable postmeta (SEOPress + wpml-config `translate` fields)  |

Elementor walk: recursive over `elements[]`; for each element with
`settings`, collect the keys in a text allow list (`title`, `text`,
`editor`, `description`, `button_text`, `alt`, `caption`, `tab_title`,
`tab_content`, `title_text`, `description_text`, `heading`, `sub_heading`,
`label`, `placeholder`, `link_text`, `testimonial_content`,
`testimonial_name`, `testimonial_job`, `before_text`, `highlighted_text`,
`rotating_text`, `after_text`, `field_label`, `field_html`, `field_options`,
`button_text_prev`, `button_text_next`, `success_message`, `error_message`,
`required_field_message`, `invalid_message`) plus any string under a
repeater item (`settings[*][]` lists of objects) whose key is in the allow
list. Values that are empty, numeric, a URL, or a hex color are skipped.
`html`-widget `html` setting is included (Elementor's own WPML config
translates it). Unknown keys are left untouched.

HTML splitting for `post_content` and any HTML-looking Elementor value: if the
value is under 6000 characters it is one segment. Longer values are split at
top-level block boundaries (`</p>`, `</li>`, `</h1-6>`, `</div>`, `</section>`,
`<!-- /wp:` ) into runs no longer than ~6000 characters; runs are re-joined in
order on apply. Splitting never happens inside a tag.

SEOPress meta: `_seopress_titles_title`, `_seopress_titles_desc`,
`_seopress_social_fb_title`, `_seopress_social_fb_desc`,
`_seopress_social_twitter_title`, `_seopress_social_twitter_desc`.
WPML custom fields: every key WPML reports as `translate` via
`wpml_get_setting('translation-management')['custom_fields_translation']`
(value 2) that has a non-empty scalar string value on the source post.

`apply(int $source_id, int $target_id, array $translated, array $map): void`

Rebuilds title/slug/excerpt/content and each meta value with the translated
runs, writes them with `wp_update_post` / `update_post_meta`, and copies every
other postmeta key verbatim except WPML/Elementor cache keys
(`_elementor_css`, `_elementor_inline_svg`, `_edit_lock`, `_edit_last`,
`_wp_old_slug`, `_icl_*`). Elementor: the translated `_elementor_data` is
saved as a JSON string (Elementor stores it slashed, so `wp_slash` before
`update_post_meta`) and `_elementor_css` is deleted so Elementor regenerates.
`_elementor_edit_mode`, `_elementor_template_type`, `_elementor_version`,
`_elementor_page_settings`, `_wp_page_template`, `_thumbnail_id` are copied.

### `includes/class-translate-job.php`

Owns the chunked job state in a transient `bspe_connect_tjob_<jobId>`, keyed
per admin user, TTL 1 hour:

```
[ 'source_id', 'target_lang', 'source_lang', 'overwrite' (bool),
  'segments' => id => text, 'map', 'batches' => list of id lists,
  'done' => id => translated, 'next' => int, 'created' ]
```

Batches are built greedily up to 12000 characters of source text each, one
segment per batch when a single segment exceeds that.

### `admin/class-translate-controller.php`

`admin-ajax.php` handlers (`wp_ajax_bspe_connect_translate_*`), capability
`manage_options` + nonce `bspe_connect_translate`. All return
`wp_send_json_success/error`.

- `lookup` (url) → resolve via `url_to_postid` then a fallback that strips the
  WPML language prefix / `?lang=`. Returns id, title, type, status, language,
  edit link, translation status per configured target (`wpml_object_id`), and
  the segment count + character total from a dry extract.
- `start` (post_id, target, overwrite) → validates (post exists, is in the
  default language, target differs, translation absent or overwrite=1),
  extracts, builds the job, returns jobId, batch count, character total.
- `step` (jobId) → translates batch `next`, stores results, returns progress
  `{next, total, chars_done}`. Idempotent per batch.
- `apply` (jobId) → creates or updates the translation post, links it in WPML,
  applies fields, deletes the job, returns the new id, edit URL, view URL.
- `cancel` (jobId) → deletes the job transient.

WPML linking on create:

```
$trid = apply_filters('wpml_element_trid', null, $source_id, 'post_' . $type);
wp_insert_post([... post_status => source status, post_type => type, post_parent => translated parent if any ...]);
do_action('wpml_set_element_language_details', [
  'element_id' => $new_id, 'element_type' => 'post_' . $type,
  'trid' => $trid, 'language_code' => $target, 'source_language_code' => $source ]);
```

Status: the new post takes the source post's status (the user chose "publish
immediately"; a draft source yields a draft translation). Term relationships
are copied through WPML's translated term ids when they exist.

Every completed job logs one `info` line through Logger with post ids, batch
count, input/output token totals and the model.

### `admin/views/settings-translate.php`

One view, three cards:

1. **Claude API** settings card (key, model, default language, firm name).
   Standard Components form, saved by Settings_Saver.
2. **Translate a page** card (only when gated conditions hold): URL input +
   Add button, result panel (title, type, language, existing-translation
   warning with an Overwrite checkbox), language select, Translate button,
   progress bar with "batch 3 of 9, 41 000 characters", result line with
   Edit / View links, error line.
3. **How it works** card: what is translated, cost note, reminder to review.

### `admin/assets/admin.js`

New `initTranslate()` block: drives lookup → start → step loop → apply with
`fetch` against `ajaxurl`, renders progress, disables buttons while running,
surfaces errors from `data.message`. Nonce and strings come from
`wp_localize_script` (`bspeTranslate`).

## Error handling

- Any WP_Error from a step is shown verbatim, the job stays in the transient so
  Retry re-runs the same batch; nothing is written to the database before
  `apply`.
- `apply` runs `wp_insert_post` first; if any later write fails the post is
  deleted again (`wp_delete_post(..., true)`) when it was newly created.
- Responses with missing ids fail the batch rather than silently dropping text.

## Testing

No PHPUnit harness exists in the repo. Pure logic (Elementor walk, HTML
splitting and rejoin, batch building, response validation) is covered by a
standalone script `tests/translate-extractor-test.php` that stubs the handful
of WP functions used and asserts round-trips. Run with `php tests/...`.
The `tests` folder is already excluded from the release zip by `.distignore`.

Manual verification on a WPML + Elementor site: translate a long Elementor
page, confirm the Spanish page opens from the language switcher with Elementor
layout intact, slug and SEOPress title translated, and Elementor CSS
regenerated.

## Version

Bump to 3.8.0 in `bspe-connect.php` (header + constant) and `readme.txt`
(stable tag + changelog line).
