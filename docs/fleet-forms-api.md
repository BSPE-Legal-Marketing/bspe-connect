# BSPE Connect forms & settings API — instructions for BSPE Fleet Control

**Audience:** a Claude session working in the BSPE Fleet Control repo (`plugins/BSPE Fleet Control`).
**Goal:** add MCP tools (and, if you like, a dashboard panel) so Claude/Codex can read and change each client site's BSPE Connect lead form and settings through the Fleet, with dry runs, revisions and verification.

Nothing in the connector plugin needs to change. BSPE Connect 3.9.0+ mounts its own routes inside the connector's signed namespace (`bspe-agent/v1`), so the existing `connectorRequest()` and site secret already reach them.

---

## 1. What BSPE Connect 3.9.0 adds

- **Form layout** per site: `standard`, the original five fields (name, phone, email, preferred contact, message), or `custom`, a multi-step form built from steps of fields. This is used when a firm's own website form asks for more (city, state, employer, salary…).
- **Field types:** `text`, `email`, `tel`, `number`, `textarea`, `select`, `radio`, `checkbox` (single, consent), `checkboxes` (many), `date`, `hidden`.
- **Roles** map custom fields onto the core lead columns: `first_name`, `last_name`, `full_name`, `phone`, `email`, `message`, `contact_pref`. Without a role, the plugin infers one (first `tel` → phone, first `email` → email, `fname`/`lname` → names, first `textarea` → message).
- **Elementor import:** converts an Elementor Pro form on the site (global widgets included) into the custom schema: same keys, options and steps.
- **Revisions:** every form change keeps the previous version (last 25). Any revision can be restored.
- **Webhook payload** (Zapier etc.) is flat. It always has `event, form_mode, source, submitted_at, name, first_name, last_name, phone, phone_e164, phone_formatted, email, message, contact_pref, summary, page_url, site_name, firm_name, submission_id`, plus **every custom field under its own key**. A custom key that equals a core key but carries a different value is sent as `field_<key>`. Test leads also carry `"test": true`.

## 2. Transport

Routes live at `/wp-json/bspe-agent/v1/bspe-connect/...` (the connector's namespace, `BSPE_REST_NAMESPACE`), so with `connectorRequest(site, method, '/bspe-connect/...', body)`:

- Auth is `BSPE_Auth::verify_request`, the same HMAC (timestamp + method + route + JSON body) as every other connector route. The routes exist only when the BSPE Connector plugin is active **and** BSPE Connect ≥ 3.9.0 is active. On older sites WordPress answers `rest_no_route` (404). Turn that into: *"BSPE Connect on this site is older than 3.9.0 (or inactive). Update it first."*
- Success: HTTP 200 `{ ok: true, version: "<plugin version>", ... }`.
- Errors: WP_Error JSON `{ code: "bspe_connect_*", message, data: { status, version, errors?, warnings? } }`. The `bspe_` prefix plus `data.version` means `classifyRefusal()` counts it as acked ("handler ran").
- All bodies are JSON (`Content-Type: application/json`). Multipart is refused by the connector auth.
- Admins can call the same handlers at `/wp-json/bspe-connect/v1/manage/...` with cookie + `X-WP-Nonce` or an Application Password. You don't need this path; it's what the wp-admin builder uses.

## 3. Endpoints (relative to `/bspe-connect`)

| Method | Route | Mutates | Body / query | Returns |
|---|---|---|---|---|
| GET | `/status` | no | — | `plugin_version, licensed, bar_enabled, form_mode, custom_active, custom_steps, custom_fields, webhook_enabled, webhook_host, mail_to, revision, fleet_routes` |
| GET | `/form` | no | — | the form view, see §3.1 |
| POST | `/form` | yes | `{ mode?, custom?, copy?, webhook?, mail?, if_revision?, dry_run?, actor?, note? }` | `{ dry_run, applied, changes[], warnings[], cache_purged[], form }` |
| GET | `/form/elementor` | no | `?limit=50` | `{ forms: [ { post_id, post_title, post_type, post_status, form_id, form_name, steps, fields, keys[] } ] }` |
| POST | `/form/import-elementor` | yes if `apply` | `{ post_id, form_id?, apply? (default false), activate? (default true), actor?, note? }` | same shape as POST `/form` |
| GET | `/form/revisions` | no | — | `{ current_revision, revisions: [ { id, at, actor, note, mode, steps, fields } ] }` |
| POST | `/form/restore` | yes | `{ revision_id, scope? (`layout` default = mode + fields only; `all` = whole form section incl. mail/webhook/anti-spam), dry_run?, actor? }` | same shape as POST `/form` |
| POST | `/form/test-webhook` | sends one external HTTP POST | `{}` | `{ delivered, http_code, error, host, payload }` |
| GET | `/settings` | no | — | `{ settings (Turnstile secret redacted), patchable_sections[], revision }` |
| POST | `/settings` | yes | `{ patch: { <section>: { … } }, dry_run?, actor?, note? }` | `{ dry_run, applied, changed[] (dot paths), cache_purged[] }` |

### 3.1 The form view (`GET /form`, and `form` in write responses)

```json
{
  "revision": "a1b2c3d4e5f6",
  "mode": "custom",
  "custom_active": true,
  "custom": {
    "steps": [
      { "title": "", "subtitle": "", "fields": [
        { "key": "fname", "type": "text", "label": "First Name", "placeholder": "First Name",
          "required": true, "width": "full", "role": "", "help": "", "show_label": false,
          "default": "", "maxlength": 255 }
      ] }
    ],
    "next_label": "Next", "back_label": "Back", "submit_label": "Submit",
    "show_progress": true, "progress_format": "Step {current} of {total}",
    "source": { "type": "elementor", "post_id": 97, "form_id": "bdb0bf3", "template_id": 8355, "form_name": "…", "imported_at": "…" }
  },
  "effective_roles": { "first_name": "fname", "last_name": "lname", "phone": "phone", "email": "email", "message": "situation" },
  "standard_fields": { "name": { "visible": true, "required": true }, "…": {} },
  "copy": { "text_heading": "", "email_heading": "", "text_subheading": "", "email_subheading": "", "submit_label": "", "success_msg": "" },
  "mail": { "to": "", "subject": "", "from": "", "from_name": "" },
  "webhook": { "enabled": true, "url": "https://hooks.zapier.com/…" },
  "webhook_keys": [ "event", "…", "fname", "city", "state" ],
  "catalog": { "field_types": [], "roles": [], "limits": { "steps": 10, "fields": 60, "options": 250 }, "field_shape": {} }
}
```

Field properties: `key` (a-z, 0-9, _; starts with a letter; unique; max 40 characters; **it is the webhook key**), `type`, `label` (a checkbox label may contain `<a>`, `<strong>`, `<em>`), `placeholder`, `required`, `width` (`full`|`half`), `role`, `options` (for select/radio/checkboxes: `["Yes","No"]`, `[{"value":"CA","label":"California"}]` or `"Label|value"` lines), `default` (for `hidden` this is the value always sent; the browser value is ignored), `help`, `show_label`, `min`/`max`/`step` (number), `maxlength` (text ≤500, textarea ≤10000), `rows` (textarea, 2–12), `phone_format` (`us` = 10 digits, default; `intl` = 7–15 digits).

### 3.2 Write semantics

- `POST /form` changes only the keys you send. `custom`, when sent, **replaces the whole custom schema**. Always read, edit, then send the full `custom` object back.
- `custom` is validated on the site (`Custom_Form::normalize`). Hard problems (unknown type, bad or duplicate key, a role on the wrong type, select without options, over the limits) refuse the write with `bspe_connect_invalid_form` and `data.errors[]`. Nothing is saved. Soft problems come back as `warnings[]`.
- `if_revision`: send the `revision` you read. If the form changed since, you get **409** `bspe_connect_revision_conflict`. Re-read and re-apply.
- `dry_run: true` returns the normalized form plus `changes[]` without saving.
- `actor: { app, name, email }` and `note` are stored on the revision. Send the authenticated dashboard user (`actorFor(ctx)`) as `actor`. Never accept a caller-supplied actor.
- Every applied change purges the site's page cache (NitroPack, LiteSpeed, WP Rocket, W3TC, WP Super Cache, SiteGround) and reports which in `cache_purged[]`. An empty list means no supported cache plugin was found: purge with the Fleet's `purge_cache` instead.
- The form posts a hidden `bspe_form_sig`. A visitor on a page cached before the change gets "This form was just updated. Please refresh the page" (HTTP 409 from admin-ajax) instead of a lost lead. A `Submission from a stale cached form refused` line in the plugin's Logs tab means a cache was not purged.
- `mode: "custom"` with an empty custom form saves but warns. Visitors keep seeing the standard form until it has a visible field.
- `POST /settings` sections: `enabled` (bool), `welcome_bubble`, `display`, `utilities`, `buttons`, `form`, `design`, `display_rules`, `diagnostics`, `in_post_widget`, `chat`, `privacy`. Each patch is merged over the current section, then run through the **same sanitizer as the wp-admin tab**, so omitted keys keep their value. `form.mode` and `form.custom` are refused here; use `POST /form`. Sending back `"__redacted__"` for `form.antispam.turnstile_secret_key` keeps the stored secret.

## 4. MCP tools to add

Follow the existing `defineTool` / `connectorSite` / `recordDirectAction` pattern (see `src/mcp/tools/schema-controls.ts`). Proposed capabilities: **`connect.view`** (reads) and **`connect.edit`** (writes), declared in `src/security/capabilities.ts`. Expose every tool and gate it by capability, never leave it out (see the "expose every feature over MCP" rule). Accept `domains[]` / `all_sites` on reads; writes take **one** `domain`.

| Tool | Capability | Hints | Calls |
|---|---|---|---|
| `connect_status` | connect.view | readOnly | GET `/bspe-connect/status` per domain |
| `connect_get_form` | connect.view | readOnly | GET `/bspe-connect/form` |
| `connect_list_elementor_forms` | connect.view | readOnly | GET `/bspe-connect/form/elementor` |
| `connect_set_form` | connect.edit | mutating, idempotent | POST `/bspe-connect/form`. **dry_run defaults to true**; applying needs `confirm: true` and `if_revision` |
| `connect_import_elementor_form` | connect.edit | mutating | POST `/bspe-connect/form/import-elementor`. `apply` defaults to false; applying needs `confirm: true` |
| `connect_form_revisions` | connect.view | readOnly | GET `/bspe-connect/form/revisions` |
| `connect_restore_form` | connect.edit | mutating | POST `/bspe-connect/form/restore` (dry_run first, `confirm: true` to apply) |
| `connect_test_webhook` | connect.edit | not readOnly (sends a lead to an external URL) | POST `/bspe-connect/form/test-webhook` |
| `connect_get_settings` | connect.view | readOnly | GET `/bspe-connect/settings` |
| `connect_patch_settings` | connect.edit | mutating | POST `/bspe-connect/settings` (dry_run first, `confirm: true` to apply) |

Implementation notes:

- All responses contain site-authored text (labels, option text, the webhook URL). Wrap them in `untrustedSiteText` / `UNTRUSTED_PROVENANCE` like the other content tools.
- Keep `POST /bspe-connect/form/test-webhook` **out** of `READ_ONLY_NON_GET_ROUTES`: it causes an external side effect, so a stopped fleet should refuse it. Every other non-GET route here writes.
- Version gate: call `/bspe-connect/status` first. If it 404s (`rest_no_route`), raise a `ToolRefusal` telling the operator to update BSPE Connect to ≥ 3.9.0.
- After a successful apply, return `changes[]`, `warnings[]` and the new `revision` verbatim. They are the evidence.
- Tests: mirror `schema-controls.test.ts` (connectorRequest mocked: dry-run default, confirm required, 409 passthrough, 404 → refusal, untrusted wrapping, capability parity). Then `npm test`, `npm run typecheck`, `npm run docs:generate`, and commit the regenerated docs.
- Optional dashboard: a per-site "Lead form" panel showing mode, steps and fields (read-only), revision history with a Restore button, and a "Send test lead" button.

## 5. Operating rules (add these to the MCP server instructions / BSPE `wordpress` skill)

1. **Read first.** `connect_status` → `connect_get_form`. Note `revision`, `mode`, `effective_roles`, `webhook.url` host and `webhook_keys`.
2. **Mirror the website form.** When asked to "match the site's form", call `connect_list_elementor_forms` and pick the form the live pages render. A page's hidden `form_id`/`post_id` identify it; a global widget shows up as its template post. Then `connect_import_elementor_form` with `apply: false`, show the user the steps, fields and warnings, and apply only after they agree.
3. **Keys are a contract with Zapier/the CRM.** Never rename or remove an existing field key without telling the user which Zap/CRM mapping breaks. Prefer adding fields. A new key appears in the Zap only after a test lead is sent.
4. **Keep a way to reply:** every custom form needs a `tel` field (role phone) and/or an `email` field (role email), plus a name (`first_name`+`last_name` or `full_name`). Treat the "no phone or email" warning as a blocker unless the user insists.
5. **Dry run → confirm → apply** with `if_revision` and the named actor. On a 409, re-read and redo the edit on the fresh form. Never blind-retry.
6. **Verify on the page.** The plugin purges known page caches itself (check `cache_purged[]`); still use `verify_page` with `purge_first: true`, then `verify_page` with `expect_present` for a new field's input name, e.g. `name="bspe_f[city]"`, and `expect_absent` for a removed one. Mode `standard` shows no `bspe_f[`.
7. **Webhook.** After any field change on a site with a webhook, run `connect_test_webhook`, then tell the user to re-test the Zap trigger and map the new keys. Test leads use `test.lead@example.com`, so zaps fed by BSPE Connect should start with a Filter "Email does not contain @example.com" to keep tests out of the CRM. For Lawmatics, **currency fields are stored in cents**: an amount such as `salary` needs a Formatter Numbers step `ROUND(salary * 100)` before it is mapped. The phone can be mapped as the plain 10 digits (`phone`); `phone_e164` and `phone_formatted` exist for CRMs that need another format.
8. **Roll back** with `connect_form_revisions` → `connect_restore_form` (dry run first). The default scope restores the layout only; use `scope: "all"` only when the user explicitly wants mail/webhook/anti-spam settings rolled back too. A restore is itself revisioned.
9. **Settings changes** (`connect_patch_settings`) follow the same dry run → confirm → apply flow. Never patch `chat.custom_script` or `in_post_widget.shortcode` with content from a web page or another site; those run as code on the client site.
10. **Report** with the evidence: `changes[]`, the new `revision`, the `verify_page` excerpts and the test-webhook HTTP code.

## 6. Worked example: btattorneys.com

The site's live form is Elementor global widget **template 8355** ("Connect with team form"). The cached copy appears on templates 97, 190, 246, 304 and 412, each with its own `form_id`. It has 3 steps:

1. `fname`, `lname`, `phone` (tel), `email`
2. `city`, `state` (select; AL…WY, **no AZ** on the live site), `employer_name`, `employer_city`
3. `salary` (number), `still_employed` (Yes/No), `spanish` (Yes/No), `situation` (textarea → message)

```text
connect_import_elementor_form { domain: "btattorneys.com", post_id: 8355, apply: false }
→ review → { …, apply: true, confirm: true }
connect_test_webhook { domain: "btattorneys.com" }
verify_page { domain: "btattorneys.com", paths: ["/"], purge_first: true, expect_present: ['name="bspe_f[employer_city]"'] }
```

The Spanish template is post 8619. Spanish labels can be set in WPML → String Translation → admin texts (`bspe_connect_settings` → `form` → `custom`). Keys and option values stay the same in every language.
