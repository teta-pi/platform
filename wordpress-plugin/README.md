# TETA+PI WordPress Plugin — Plan

Task 12.1 (direction 12, wordpress). Connects a WordPress site to a TETA+PI
entity (`api.tetapi.dev`), proves domain ownership, and displays the resulting
trust badge. Free tier fully functional, standalone; premium ($25 one-time
pack) is stubbed behind a license-key field for a later task.

Server-side (`api/`, `web/`, `mcp/`) is untouched — this plugin is a pure API
client using existing endpoints (`docs/api.md`, `docs/verification-rework.md` §2).

## Why WordPress

Cheapest, fastest distribution channel for "prove your site is who it says it
is" — WP still runs ~40% of the web. A free plugin that gets a business
domain-verified in two clicks is a GTM wedge for direction 13 (autonomous GTM),
which starts once the plugin + MCP listings are live.

## Architecture

```
wordpress-plugin/
├── teta-pi.php                 # main plugin file, bootstrap, headers
├── uninstall.php                # removes options on uninstall
├── readme.txt                   # wp.org listing (Plugin Check requires this)
├── includes/
│   ├── class-tetapi-plugin.php  # singleton bootstrap, hooks
│   ├── class-tetapi-api.php     # thin HTTP client for api.tetapi.dev
│   ├── class-tetapi-settings.php # Settings > TETA+PI admin page
│   ├── class-tetapi-domain.php  # .well-known rewrite + verify start/check
│   ├── class-tetapi-badge.php   # shortcode + widget, cached public payload
│   └── class-tetapi-premium.php # license-key field + locked stub sections
└── assets/
    ├── admin.css / admin.js     # settings page only, no external libs
    └── badge.css                 # minimal badge styling, no external libs
```

No Composer, no build step, no JS framework — WP core APIs only
(`WP_Http`/`wp_remote_*`, Settings API, Shortcode API, Widgets API, transients
for caching). PHP 7.4+ compatible (no enums, no readonly props, no `match`).

## Data flow

1. **Connect** — owner pastes their `pk_live_…` personal API key
   (`docs/api.md` Auth). Plugin calls `GET /businesses` with that key as
   Bearer to list the user's owned entities, owner picks one, we store
   `entity_id` + `entity_slug` + the key (encrypted at rest via WP's
   `AUTH_KEY` salt, not plaintext) in `wp_options`.
2. **Domain ownership** — plugin adds a rewrite rule so
   `https://yoursite.com/.well-known/tetapi-verify.txt` serves a token.
   Admin clicks "Verify" → `POST /businesses/{id}/verify/domain/start`
   (site's own domain, auto-detected from `home_url()`) → get back
   `{token, dns_txt, file}` → plugin already serves the file route → click
   "Check" → `POST /businesses/{id}/verify/domain/check` → store
   `verified_at` + `method` in options. Re-checked on demand only (no cron —
   server-capacity constraint, no new sustained load).
3. **Badge** — `[tetapi_badge]` shortcode and a matching widget fetch
   `GET /businesses/by-slug/{slug}/public` (no auth needed, it's the public
   endpoint), cache the response in a transient (15 min TTL) to avoid hitting
   the API on every page view, and render trust_level + legal_entity
   disclosure + a link to the public profile (`https://app.tetapi.dev/e/{slug}`).

## Settings page fields (free tier)

| Field | Notes |
|---|---|
| API Key (`pk_live_…`) | password field, validated against `GET /businesses` on save |
| Entity picker | populated from `GET /businesses` once key is valid |
| Domain verification status | none / pending / verified, with Start/Check buttons |
| Badge preview | live preview of the shortcode output |
| License key (premium) | optional field, unlocks premium sections when valid — validation stubbed (§ Premium) |

## $25 Premium Pack — proposed contents (stub only in this task)

One-time $25 purchase (Gumroad/Lemon Squeezy license key, no payment code in
this repo — the plugin only ever *checks* a license key against a stub
function that returns "not implemented" for now). Sold separately from the
TETA+PI platform itself; distribution/checkout mechanics are a direction-13
GTM decision, not built here.

1. **Badge style pack** — 5 additional badge layouts (compact pill, card with
   description, footer strip, floating corner ribbon) instead of the one free
   default style; a color/theme picker instead of auto light/dark.
2. **Auto-insert placement** — inject the badge automatically into
   header/footer/post-end via a hook, instead of requiring manual shortcode
   placement (free tier: shortcode + widget only).
3. **Multi-entity sites** — connect more than one TETA+PI entity to a single
   WP install (e.g. an agency site listing several verified brands) — free
   tier is single-entity.
4. **WooCommerce integration** — show the badge on product/checkout pages and
   in order emails, for stores that want to display verified-seller status
   at the point of purchase.
5. **Verification email/document nudges** — admin-dashboard widget reminding
   the owner to also complete Business Email Control / registry verification
   (raises trust_level beyond domain-only), with deep links back to
   app.tetapi.dev.
6. **Priority badge refresh** — shorter transient TTL (near-live trust_level)
   instead of the free tier's 15-minute cache.

All six are represented in this task only as disabled UI ("🔒 Coming with
Premium — enter a license key to unlock") behind `class-tetapi-premium.php`,
which exposes a single `Tetapi_Premium::is_licensed()` stub returning `false`.
No license-server calls, no payment/checkout code, nothing that phones home
beyond the existing `api.tetapi.dev` client.

## Plugin Check / wp.org readiness

- All output escaped (`esc_html`, `esc_attr`, `esc_url`), all input sanitized
  (`sanitize_text_field`, `sanitize_key`) and nonce-checked on every POST.
- Text domain `tetapi`, `load_plugin_textdomain()`, all strings wrapped in
  `__()`/`esc_html__()`.
- No direct file access (`ABSPATH` guard in every file).
- Settings stored via Settings API (`register_setting`), capability-checked
  (`manage_options`).
- `uninstall.php` deletes all `tetapi_*` options — nothing orphaned.
- No `eval`, no remote file inclusion, no external JS/CSS from a CDN — badge
  and admin assets are enqueued from the plugin's own `assets/`.

## Out of scope (this task)

- Any change to `api/`, `web/`, `mcp/`.
- Actual premium license validation / payment processing.
- Document-upload verification (not implemented server-side yet, per
  `docs/verification-rework.md` §2).
- Cron-based auto re-verification (server capacity constraint).
