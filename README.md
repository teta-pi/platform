> ⚠ **Retired 2026-07-13.** This monorepo was split into separate repos —
> [`teta-pi/api`](https://github.com/teta-pi/api) ·
> [`teta-pi/web`](https://github.com/teta-pi/web) ·
> [`teta-pi/mcp`](https://github.com/teta-pi/mcp) ·
> [`teta-pi/landing`](https://github.com/teta-pi/landing) ·
> [`teta-pi/infra`](https://github.com/teta-pi/infra) (canonical docs).
> Kept read-only for history — do not open PRs here.

# TETA+PI Platform

> Verified entity registry for Agent Internet.
> Create a profile. Add blocks. TETA+PI verifies the rest.

## What it did

Any entity — business, journalist, creator, organization — registers a verified profile. TETA+PI automatically structures, categorizes, and verifies it. AI agents discover verified entities through the Intent Graph via MCP.

## Stack

- **Frontend:** Next.js 15 · TypeScript · Tailwind CSS
- **Backend:** FastAPI · PostgreSQL 16 · pgvector
- **Verification:** C2PA · Bitcoin OpenTimestamps
- **Protocol:** MCP server · Intent Graph · `/.well-known/agent.json`
- **Infrastructure:** DigitalOcean · Nginx · Cloudflare · Full (strict) SSL

## Architecture

```
web/              Next.js 15 frontend
api/
  routers/        entity · blocks · verification · mcp · endpoint_verification
  intent_graph/   schema · resolver
  models/         entity · block · verification
mcp/              TypeScript MCP server (HTTP + SSE · port 3002)
```

## License

MIT © 2026 TETA+PI · tetapi.dev
