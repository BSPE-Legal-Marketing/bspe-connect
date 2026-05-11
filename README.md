# BSPE Connect

Bottom-fixed bar with up to four contact buttons (Connect, Call, Text, Email), a welcome bubble, and a built-in lead capture form. Self-updates from this repo.

## Install

**Always install from the [Releases page](https://github.com/BSPE-Legal-Marketing/bspe-connect/releases/latest) — never from the green "Code → Download ZIP" button.**

The "Code → Download ZIP" button packages the source archive as `bspe-connect-main.zip` (folder name `bspe-connect-main/`). That folder name doesn't match what the plugin expects, so updates and the View Details modal misbehave. The auto-built **`bspe-connect.zip` asset** on each release is the canonical zip — it strips dev files via `.distignore` and uses the right folder name.

### Steps (every client site)

1. Go to https://github.com/BSPE-Legal-Marketing/bspe-connect/releases/latest
2. Under **Assets**, download `bspe-connect.zip`
3. WP admin → **Plugins → Add New → Upload Plugin** → upload the zip
4. **Install Now** → **Activate**
5. Go to **BSPE Connect** in the admin sidebar and configure the tabs

After this initial install, every future release surfaces in WP admin → Updates within 12 hours with one-click in-place update. No re-download needed unless you're rolling out a brand-new install.

## What it does

- **Mobile-only contact bar** at the bottom of every page (configurable display rules per page / post / slug)
- **Welcome bubble** with optional avatar, configurable copy, dismissal persistence
- **Bottom-sheet lead capture form** with full anti-spam pipeline (nonce + honeypot + time trap + per-IP rate limit + optional Cloudflare Turnstile)
- **Submissions table** in the admin with date / source / status filters and CSV export
- **Analytics dashboard** with conversion funnel and top-pages tables
- **Self-updates** from this GitHub repo via [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker)

## Releasing a new version

For maintainers:

1. Bump `BSPE_CONNECT_VERSION` constant + `Version:` plugin header in `bspe-connect.php`
2. Bump `Stable tag:` in `readme.txt` and add a `= X.Y.Z =` changelog block
3. `git commit && git push origin main`
4. `git tag -a vX.Y.Z -m "..." && git push origin vX.Y.Z`
5. The GitHub Actions workflow at `.github/workflows/release.yml` automatically builds `bspe-connect.zip` and attaches it to the release
6. Optional: `gh release edit vX.Y.Z --notes "..."` for the release notes body

To roll out a release **silently** to every client site (use sparingly — emergency fixes only), include the literal string `Auto-Update: yes` anywhere in the release notes body or in the matching `= X.Y.Z =` block in `readme.txt`. WP's `auto_update_plugin` filter picks up the marker and installs without admin action.

## License

Proprietary. Built by [BSPE Legal Marketing](https://bsplegalmarketing.com/).
