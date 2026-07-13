# Security — Threat Model & Standing Red-Team

Owner: Direction 15 (Security). This is the living threat model for TETA+PI and
the source of truth for the recurring authorized red-team. It is paired with the
findings backlog in `docs/known-issues.md` (the 6.1 audit + anything added here).

> **Rules of engagement (authorized, own infrastructure only).** Read and reason
> over the code. Prod probing is limited to **benign, non-destructive** single
> requests to confirm a finding. **No** destructive tests, DoS/load, data
> exfiltration, brute force, or touching other people's data. Find and report —
> do not exploit or persist access. The prod droplet is at capacity
> (`docs/known-issues.md`, "Server capacity"): **nothing in the audit loop or CI
> may add sustained server load.**

## 1. Assets (what we are protecting)

| Asset | Where | Why it matters |
|---|---|---|
| Entity + PII data | `users` (`full_name` Fernet-encrypted), `businesses` | GDPR; identity of verified people/orgs |
| Personal / device API keys (`pk_live_…`) | `users.api_key`, `devices.api_key` | Bearer creds; full account / device-upload authority |
| Admin / back-office routes | `routes/admin.py` (`require_admin`) | User export, anonymize, entity validate, audit log |
| Append-only ledgers | `verification_events`, `admin_audit_log` | The Temporal Moat — trust history must be immutable |
| C2PA + OpenTimestamps proofs | `media.c2pa_manifest`, `media.bitcoin_proof`, `api/certs/*.key.pem` | Provenance integrity; signing key = ability to forge proofs |
| Media store | `UPLOAD_DIR` (`/opt/tetapi/uploads`), served at `/media/local/{id}/{name}` | Uploaded files; path is an FS gateway |
| Server secrets | `/opt/tetapi/api/.env` (`SECRET_KEY`, `PII_ENCRYPTION_KEY`, `DATABASE_URL`, `RESEND_API_KEY`, registry keys), C2PA key | Compromise = forge JWTs, decrypt PII, forge proofs |
| Verification integrity | `registry_status`, `*_verified` flags, `verification_level` | The product *is* the trust signal; a fakeable badge is a core-value breach |

## 2. Trust boundaries

```
   Untrusted                          │ Boundary crossing            │ Trusted side
 ─────────────────────────────────────┼──────────────────────────────┼───────────────────────
   AI agent                           │ agent ↔ MCP (mcp.tetapi.dev) │ MCP TS server (stateless)
   MCP server                         │ MCP ↔ API (HTTP, pk_live_)   │ FastAPI
   Browser (app.tetapi.dev)           │ browser ↔ API (JWT/CORS)     │ FastAPI
   Pi CAM device                      │ device ↔ /media/device-upload│ FastAPI (X-Device-Api-Key)
   WordPress plugin (customer site)   │ WP plugin ↔ /verify/domain   │ FastAPI
   GitHub Actions runner              │ CI ↔ server (SSH/rsync)      │ droplet 164.90.235.66
   Arbitrary internet caller          │ → /verify-endpoint, /media   │ FastAPI (server-side fetch, FS)
```

Key properties to defend at each boundary:
- **agent ↔ MCP / MCP ↔ API** — MCP is stateless and forwards a `pk_live_` key; the
  API is the only authz authority. MCP must not become a confused deputy (no
  privilege it holds that an agent shouldn't reach directly).
- **browser ↔ API** — CORS allowlist + JWT (`ver` claim). No cookie session; XSS
  in `web/` would expose `localStorage` tokens.
- **device ↔ /media/device-upload** — only `X-Device-Api-Key`; a leaked device key
  can post media into that entity's "Pi CAM Captures" block.
- **CI ↔ server** — SSH deploy key + secrets in Actions. Runner compromise = prod
  RCE. C2PA private key is `rsync`-excluded and must never enter CI artifacts.

## 3. Attacker classes

1. **Anonymous internet** — hits public endpoints (`/search`, `/media/local/*`,
   `/verify-endpoint`, `/claim`, `/docs`). Goal: read files, SSRF, abuse limits,
   enumerate.
2. **Authenticated user (self-serve)** — owns entities; wants to forge a higher
   trust level (keep a `*_verified` flag after mutating the underlying claim),
   IDOR into other entities/blocks/media.
3. **Malicious agent** — drives MCP tools at scale; wants trust signals it can
   game, or to turn a tool into an SSRF/enumeration primitive.
4. **Compromised device / leaked `pk_live_` key** — posts forged captures, or
   (personal key) acts as the user.
5. **Supply chain / CI** — poisoned dependency or runner; wants secrets or a
   deploy-time backdoor.
6. **Insider / stolen server access** — reads `.env`, forges JWTs/proofs. Out of
   scope for app hardening; mitigated by secret hygiene + append-only ledgers.

## 4. Per-surface security checklist

Run this list against every route/tool touched by a change. `[verified]` = checked
in this audit and OK; `[OPEN]` = has a filed finding (see §5 / known-issues).

### authn / authz / IDOR
- [ ] Every mutating route depends on `get_current_user`; admin on `require_admin`.
- [ ] Owner check compares `owner_id == current_user.id` (business/block/media). `[verified]`
- [ ] Read routes don't leak private rows (blocks `is_public=false`). `[verified — fixed]`
- [ ] `/verify-endpoint` is unauthenticated. `[OPEN — #7 / 1.7]`
- [ ] `/media/device-upload` and personal keys share the `pk_live_` prefix but
      different tables; keep the two key namespaces from ever cross-authenticating. `[OPEN — N4]`

### SSRF
- [ ] Any server-side fetch of a caller-supplied URL blocks private/link-local
      ranges + requires auth/rate-limit. `[OPEN — #7 /verify-endpoint; also /verify/domain/check]`
- [ ] Responses from fetched URLs are never reflected to the caller (blind only).

### path traversal / file handling
- [ ] File paths built from URL segments are `resolve()`d and confined with
      `is_relative_to(base)`. `[OPEN — #1 /media/local/{id}/{name} / 1.6]`
- [ ] Upload writes use `Path(name).name` before joining. `[verified — _save_local]`

### injection
- [ ] All DB access via SQLAlchemy params / ORM (no string SQL). `[verified — incl. `Business.id.cast("text")`]`
- [ ] Registry verifiers treat scraped/DE-portal HTML as untrusted data.
- [ ] Trust decisions never made client-side. `[OPEN — #11 fake claim verify / 1.8]`

### rate-limit / abuse
- [ ] Client IP derived from a **trusted** hop, not the first `X-Forwarded-For`
      value. `[OPEN — N1 / 1.9]`
- [ ] Public unauth endpoints (`/claim`, `/auth/email-code`) throttled; verify-code
      has per-email attempt lockout. `[verified — verify-code; OPEN — /claim IP spoof]`
- [ ] In-memory limiters acknowledged single-worker-only (move to Redis before scale). `[documented]`

### secrets exposure
- [ ] `SECRET_KEY`/`PII_ENCRYPTION_KEY` never fall back to the dev default in
      production (startup guard). `[OPEN — N5]`
- [ ] C2PA key excluded from rsync + git. `[verified]`
- [ ] No secrets in logs, error bodies, or `/openapi.json`.
- [ ] `/docs` + `/openapi.json` not publicly reachable in prod. `[OPEN — N2]`

### CORS / transport
- [ ] `allow_origins` is prod-scoped (no localhost in production). `[OPEN — N3]`
- [ ] `allow_credentials=True` paired only with an explicit allowlist (never `*`). `[verified — explicit list]`
- [ ] HTTPS-only; nginx sets `X-Real-IP`/`X-Forwarded-Proto`. `[verified]`

## 5. Findings backlog (seed = 6.1 audit, triaged + extended)

Severity: 🔴 blocker · 🟠 important · 🟡 minor. Status of each 6.1 item lives in
`docs/known-issues.md`; this table is the security-priority view + the fix-task map.
The three starred items were re-verified this session with non-destructive checks.

| # | Sev | Finding | file:line | Verified | Fix task |
|---|---|---|---|---|---|
| 1 ★ | 🔴 | Unauth path traversal in `serve_local_media` — path built from URL segments, no confinement | `api/app/api/routes/media.py:221-227` | **Confirmed**: code + prod returns 404 (not 401) → no auth gate | **1.6** |
| 7 ★ | 🟠 | `/verify-endpoint` fully unauthenticated, does server-side GET to any URL (blind SSRF) | `api/app/api/routes/endpoint_verification.py:73-97` | **Confirmed live**: prod fetched `https://example.com` with no token → `is_active:true` | **1.7** |
| 11 ★ | 🟠 | `/claim` "Registry domain email" step is client-side fake — "Send code" is a no-op, any 3+ char string sets `proven` | `web/src/app/claim/page.tsx:748,769` | **Confirmed**: code review; no network call issued | **1.8** |
| N1 | 🟠 | **NEW** — Rate-limit bypass via spoofed `X-Forwarded-For`. `_rate_limit` keys on `xff.split(",")[0]` (client-controlled; nginx *appends* via `$proxy_add_x_forwarded_for`), so `/claim`'s 5/min/IP is trivially evaded by rotating the header | `api/app/api/routes/claims.py:23` (+ nginx `deploy/nginx/api.tetapi.dev.conf:13`) | **Confirmed** by code + nginx config | **1.9** |
| N2 | 🟡 | **NEW** — `/docs` + `/openapi.json` publicly reachable in prod (both HTTP 200), disclosing the full route surface incl. admin paths | `api/app/main.py:19-24` (no `docs_url=None` in prod) | **Confirmed live**: `docs=200 openapi=200` | 1.9 |
| N3 | 🟡 | **NEW** — CORS default allowlist bakes in `localhost:3000/3001` with `allow_credentials=True`; `cors_origins` is not in the `.env` secret set, so prod may run the dev default | `api/app/core/config.py:44-50`, `main.py:26-32` | Confirmed in code (prod value not inspectable from here — flag to scope) | 1.9 |
| N4 | 🟡 | **NEW** — `pk_live_` prefix reused for both personal user keys and device keys (`f"pk_live_{token}"`), different tables. Not currently cross-authable (`get_current_user` reads `User.api_key`, `_get_device` reads `Device.api_key`) but a namespace collision that invites a future confused-deputy bug | `api/app/api/deps.py:22-27`, `api/app/api/routes/media.py:349` | Confirmed in code (no live exploit) | backlog |
| N5 | 🟡 | **NEW** — No fail-fast guard that `secret_key`/`pii_encryption_key` differ from the dev default when `environment=="production"`. Prod `.env` sets them (mitigated), but a misdeploy silently ships forgeable JWTs | `api/app/core/config.py:8,22` | Defense-in-depth (not a live vuln) | 1.9 |

Related, already-tracked SSRF/abuse surface to fold into 1.7's design: `/verify/domain/check` issues a blind GET to an arbitrary user domain (`docs/known-issues.md`, 1.5 note b) — same private-range-block mitigation applies.

### Fix-task assignments (handed to backend/frontend — no code in this session)
- **1.6** — `serve_local_media`: `resolve()` + `is_relative_to(_UPLOAD_DIR.resolve())`, reject otherwise (mirror `_save_local`'s sanitization). *(backend)*
- **1.7** — `/verify-endpoint`: require auth or per-IP rate-limit before the fetch; block private/link-local/loopback targets (shared SSRF guard, reused by `/verify/domain/check`). *(backend)*
- **1.8** — `/claim` domain-email step: wire the real `/auth/email-code` + business `/verify/email/*` endpoints, or hide the method until backed (remove the fake `setProven`). *(frontend)*
- **1.9** — Transport/abuse hardening bundle: trust `X-Real-IP` / last XFF hop for rate-limit keys (N1); set `docs_url=None,redoc_url=None,openapi_url=None` in prod (N2); prod-scope `cors_origins` (N3); add a production startup guard on default secrets (N5). *(backend/devops)*

## 6. The recurring loop (standing red-team)

### Cadence — authorized read-only audit
- **Per-PR (blocking, automated):** the §4 checklist is the reviewer's gate for any
  PR that adds/edits a route, MCP tool, or verifier. CI scanners (below) run on every
  PR and `main` push — **runner-side only, zero server load**.
- **Monthly (manual, ~1 session):** re-run this read-only audit against `main` —
  re-verify open findings didn't regress, sweep new routes/tools since last pass,
  refresh §5. Use benign prod probes only (RoE §top).
- **On trigger:** any new external boundary (new server-side fetch, new upload
  path, new unauth route, new `pk_live_` consumer) forces an out-of-cycle review of
  just that surface.
- **Never:** load testing, fuzzing against prod, or anything sustained — the droplet
  is at capacity. Load-shaped tests wait for the capacity upgrade and run against a
  staging box.

### CI security scanners to add in 15.2 (all runner-side, zero prod load)
| Scanner | Target | Trigger |
|---|---|---|
| **CodeQL (JavaScript/TypeScript)** | `web/`, `mcp/` | PR + push to `main` |
| **CodeQL (Python)** | `api/` | PR + push to `main` |
| **`npm audit`** (`--omit=dev`, fail on high) | `web/`, `mcp/` lockfiles | PR + weekly cron |
| **Bandit** | `api/app/` | PR + push to `main` |

Runner-side notes: all four execute on the GitHub Actions runner and never touch
`164.90.235.66` — safe under the capacity constraint. Add as a separate
`security.yml` workflow (do not couple to `deploy.yml`, which is prod-facing). Gate
merges on CodeQL + Bandit; treat `npm audit` high/critical as a required review, not
an auto-block (transitive-dep noise). Full workflow implementation is **15.2's** job;
this section is the spec.
</content>
</invoke>
