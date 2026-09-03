# Fleet translation: Claude-powered WPML page translation from the Manager

Date: 2026-09-03 (revised the same day: moved from a plugin tab to the fleet
dashboard). Plugin release: v3.8.0. Manager: v0.2.0.

## Goal

From the BSPE Connect Manager (fleet dashboard), pick a client site, paste a
page URL, choose the target language (Spanish for now) and press Translate.
The Manager reads everything WPML would translate from the site, sends the
text to Claude in chunks, and writes back a published WPML-linked translation.
Client sites see no new UI, no settings and hold no API key.

## Split of responsibilities

**Plugin (`includes/class-translate-endpoint.php`, `class-translate-extractor.php`)**

Three POST endpoints under `bspe-connect/v1/translate/`:

| route     | body                                                            | returns |
|-----------|-----------------------------------------------------------------|---------|
| `status`  | –                                                               | version, wpml flag, default language, active languages, elementor flag, site_url |
| `extract` | `url` or `post_id`, `target`                                    | page info (title, type, status, language, existing translation, char count) + `segments` (id → text) + `map` |
| `apply`   | `source_id`, `target_lang`, `source_lang`, `overwrite`, `translated` (id → text), `map` | new/updated post id, edit and view URLs |

Auth: `Authorization: Bearer <RS256 JWT>` signed with the Manager's private
key; the plugin verifies with the public key it already uses for license
responses (`Licensing::verify_token`). Claims: `iss` = expected issuer,
`aud` = the site's registrable domain, `key` = this install's license key,
`scope` = `translate`, `exp` ≤ 5 minutes. Requires `Licensing::is_functional()`
and WPML.

Extraction/apply rules are unchanged from the first design: title, slug (as
words), excerpt, post_content, Elementor widget text (allow-listed setting
keys, repeaters), SEOPress fields, WPML "translate" custom fields; HTML over
6k chars split at block boundaries into ordered runs; apply rebuilds, copies
all other postmeta, regenerates Elementor CSS, links via
`wpml_element_trid` + `wpml_set_element_language_details`, same status as the
source, terms mapped through `wpml_object_id`.

License `activate`/`check` calls now include `site_url` so the Manager knows
the real URL (with `www.`) to call; the Manager falls back to
`https://<domain>/`.

**Manager (Node/Fastify)**

- `ANTHROPIC_API_KEY` (+ optional `CLAUDE_MODEL`, default `claude-opus-5`) in
  the environment. One key for the whole fleet.
- `src/claude.js`: Messages API call per batch, JSON in / JSON out with every
  id verified, retries on 429/5xx, refusal and max_tokens handled, list
  prices, cost and pre-flight estimate.
- `src/translate.js`: signs plugin tokens, calls the plugin endpoints via
  `?rest_route=` (works without pretty permalinks), builds batches (12k chars
  or 60 segments), runs a job asynchronously in-process while writing progress
  to Postgres after every batch, resumes running jobs after a restart.
- Table `translation_jobs`: one row per job with segments/map/done as jsonb,
  batch progress, token usage, cost, error, result URLs.
- Routes: `GET /admin/licenses/:id/translate` (page), JSON `lookup`, `start`,
  `jobs/:id.json` (polled by the page), `retry`, `cancel`, and
  `GET /admin/translations` (all jobs, totals per site and overall).
- UI: same server-rendered style as the rest of the Manager. Progress bar,
  estimate before starting, exact cost after, history and totals.

## Error handling

Nothing is written on the site until every batch is back. A failed batch
leaves the job in `failed` with the API message; Retry resumes from that
batch. If `apply` fails after creating a post, the plugin deletes it.

## Testing

Pure extraction logic: `tests/translate-extractor-test.php` in the plugin.
Manager: `node --test` unit tests for batch building and response validation.
Live verification on a WPML + Elementor site remains manual.
