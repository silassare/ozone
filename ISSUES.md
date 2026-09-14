# Open issues

Open findings and planned work, each verified in the code. A fixed item leaves this file; the
breaking changes it caused are listed in `CHANGELOG.md` (Unreleased).

---

## Planned

### DOC-1 Documentation site

Next. OZone has no documentation beyond a nine-line `README.md`, the phpdoc and the instructions
file. Wanted: a guided, current presentation of every feature and of what it makes possible, as
Gobl has (`../gobl/docs`: VitePress, a guide, a phpDocumentor API reference).

**Build it as Gobl does, the OZone way:**

- `docs/` in this repository, VitePress (Gobl's setup: `docs/.vitepress/config.ts`, `guide/`,
  `public/`, `changelog.md` including `CHANGELOG.md`), so a feature and its page change in one commit.
- Built in Docker like everything else: `make docs` (serve) and `make docs-build` run a Node
  container, so the host still needs only Docker and `make`.
- Published by a GitHub Actions workflow on a push to `main` (GitHub Pages); Gobl has none yet, so
  this one is the template for both.
- The nav links the first-party packages' own docs (Gobl, Blate, Kli, php-utils) rather than
  copying them: OZone's pages say how OZone uses them.
- `README.md` becomes a short introduction pointing to the OZone documentation site.

**Outline** (each page: what it is for, a working example from a generated project, the settings
and commands it involves, the pitfalls, the source files it documents):

1. Introduction: what OZone is, who it is for, a first project in five minutes
   (`oz project create`, a scope, a route, `oz project serve`).
2. Architecture (DOC-4): the request lifecycle, runtimes and workers, `data/` and `.ozone/`,
   settings and their sources, scopes and plugins, boot hook receivers and events.
3. Building an API: routing (groups, params, guards, middlewares, interceptors, rate limits, the
   route table), services and responses, forms and validation (fields, rules, fieldsets, resume,
   resumable form sessions), REST services and the OpenAPI spec.
4. Web: views, Blate templates, `oz://` assets, redirects.
5. Users and security: authentication methods, sessions, CSRF, CORS, trusted proxies, access
   rights, OZAuth authorizations (email / phone ownership, recovery, file access), 2FA.
6. Data: Gobl integration, schema and migrations, generated ORM classes, CRUD listeners, entity
   collections, pagination.
7. Files: storages and drivers (local, MinIO / S3), uploads (chunked, resumable), TempFS, image
   filters, virus scan.
8. Background work: the job queue and batches, cron (the runners: scheduler, `oz cron work`, the
   `oz:cron` route, requests), garbage collection.
9. Cache and state stores, i18n.
10. Command line: every `oz` command (DOC-2).
11. Deployment: `oz project build` and preloading, Docker, bare servers (`oz deploy init`,
    `oz deploy run`, `oz server provision`), shared hosting without a shell, worker servers
    (FrankenPHP, RoadRunner, Swoole), `oz doctor`.
12. Performance: what is compiled and cached, classic vs worker mode, the benchmark and how to read
    it (`docker/bench/README.md`).
13. Security guidelines and pros and cons (DOC-4).
14. Testing a project; contributing to OZone (`CONTRIBUTING.md`).
15. Reference (DOC-2): settings, CLI, HTTP API, PHP API; changelog.

### DOC-2 Generated references, so they cannot fall behind

Written by hand, a reference drifts; generated from the code, it cannot. A `make docs-ref` target
writes them, and CI fails when the committed output differs from what the code generates:

- **Settings**: every group of `oz/oz_settings/` with each key, its `@default` and its docblock
  (they already document their keys).
- **CLI**: every `oz` command, action and option, from the Kli definitions (`describe()`).
- **HTTP API**: the OpenAPI spec (`ApiDoc::get()->toArray()`), exported at build time and rendered
  on the OZone documentation site; the same spec the app serves at `/api-doc-spec.json`.
- **PHP API**: phpDocumentor, as Gobl does (its `Makefile` downloads the phar on first use).

### DOC-3 Every public route documented

The rule (instructions, section 18): a route the API router serves has an operation in the OpenAPI
spec; a web-only route a page on the OZone documentation site (`docs/`); internal routes (`OZone::INTERNAL_PATH_PREFIX`) are
exempt. Not documented today (`oz.routes*.php`, checked 2026-09-14):

| Provider | Routes | Where |
| --- | --- | --- |
| `Forms\Resume\Services\ResumableFormService` | `/form/:provider/init`, `state`, `next`, `back`, `cancel`, `evaluate` | API router, no `apiDoc()` |
| `FS\Views\GetFilesView` | the file-serving routes | both routers (`oz.routes`) |
| `Auth\Views\AuthLinkView` | `GET` / `POST /auth/link/:ref/:token` | both routers |
| `Cli\Cron\CronEndpoint` | `GET` / `POST /oz-cron`, when `OZ_CRON_WEB_KEY` is set | both routers |
| `Auth\Views\LogoutAndRedirectView` | `GET /logout` | web router: a page on the OZone documentation site |

`RedirectView` is internal; `Polyglot` maps no path. The other API providers implement `apiDoc()`,
but whether each documents *every* route it maps is not checked. A test will: every route of the API
router but the internal ones has an operation in the generated spec (method and path), so a route
added without its documentation fails CI, in OZone and in a project that runs the same check.

### DOC-4 Architecture, security guidelines, pros and cons

Pages that say why, not only how:

- **Architecture**: the request lifecycle (the diagram of the instructions, section 1), the
  runtimes and why a worker unwinds rather than skipping `exit`, the `Context` and what may not be a
  static, the state directories and what a deployment must back up, how settings merge, what
  `oz project build` compiles.
- **Security guidelines**: the defaults and the reason for each (CORS allow-list, CSRF on
  cookie-authenticated requests, lazy sessions, trusted proxies only, login throttling and dummy
  hashes, redaction of logged data, signed serialization, `hash_equals()`, TempFS refs, virus scan,
  rate limits, no state change on a GET); a production checklist (`ENV_MODE`, keys in `.env`,
  `oz.proxies`, HTTPS and cookie flags, a scheduler, `oz doctor check`, backups of `data/`); what
  a project must not do (secrets in exception data, request state in statics, ORM classes loaded at
  boot, `Throwable` caught around `respond()`).
- **Pros and cons, honestly**: what OZone gives (a complete API stack in one package, worker mode,
  the route table and preloading, real-service tests), what it costs (a small ecosystem, the
  first-party packages it depends on, conventions to learn), when to choose it and when Laravel or
  Symfony fit better; the benchmark as it stands (ahead of Laravel on every route measured, behind
  Symfony: 1.8x on the classic text route, 1.2x in worker mode, `docker/bench/README.md`).

### DOC-5 Keeping it current

- An instructions rule, like the one for this file: a change that alters a documented behaviour
  updates its page in the same change; a new feature gets its page.
- CI: `make docs-build` (a broken build or a dead internal link fails), the DOC-2 references are
  current, the DOC-3 route test passes.
- Each guide page lists the source files it documents, so a reviewer sees which pages a change
  touches.

### WS-1 Real-time: WebSockets (and server push)

Not urgent. Wanted: pushing to clients (notifications, live updates, form-session progress) and,
later, client messages over a WebSocket, without weakening the request model or the security
defaults.

**What the codebase imposes.** A request is one `Context` tree in process state, and a worker serves
one request at a time (`SwooleBridge` turns coroutines off for exactly that reason). A WebSocket is
the opposite: many long-lived connections per process, idle most of the time. Holding them in PHP
workers would either pin one worker per connection (a handful of idle sockets exhausts the pool) or
need concurrency OZone's `Context` model does not have.

**Recommended first step: a gateway holds the connections, OZone authorizes and publishes.**

- A `Realtime\PublisherInterface` with drivers for a real-time hub run next to the app: Mercure
  (built into FrankenPHP; server push over SSE, which also covers most "live update" needs) and
  Centrifugo (WebSocket, SSE, and client-to-server messages through an HTTP proxy back to an OZone
  route, so every client message goes through the router, guards, forms and rate limits as usual).
  Publishing is one HTTP call (or Redis), from a handler, a job or a CRUD listener.
- Subscriptions are authorized by OZone: an authenticated route issues a short-lived, channel-scoped
  token (HMAC-signed with the app secret -- both hubs verify HS256 JWTs, which is a few lines on
  `hash_hmac()`, no new dependency). Channel access reuses the existing guards and access rights.
- PHP workers never hold an idle socket, the hubs scale horizontally, and the attack surface added to
  OZone is one token-issuing route.

**Security requirements, whichever transport:**

- **Origin check on the handshake** (cross-site WebSocket hijacking): a cookie-authenticated upgrade
  must pass `CorsPolicy`, since browsers send cookies on cross-site WebSocket requests and CORS does
  not apply to them.
- No long-lived credential in a URL (URLs end up in logs): a short-lived ticket from an authenticated
  POST, or the cookie plus the origin check.
- Per-connection message size limit, per-connection and per-IP rate limits (`RateLimit`), a cap on
  connections per IP, idle timeouts, and authorization per channel -- never "authenticated means
  every channel".

**Later, only if needed: a native WebSocket bridge** on Swoole (`Swoole\WebSocket\Server` extends
the HTTP server `SwooleBridge` already attaches to): the handshake as an ordinary OZone request (auth,
origin, guards), each message dispatched as a sub-request-like `Context` built from the connection's
handshake, and broadcasting across workers through Redis pub/sub. More code, a larger surface, and
Swoole-only; the gateway covers the same needs first.

**Tests**, per the rule: against real Mercure and Centrifugo containers in `docker/compose.yaml`
(a `realtime` group), including a cross-origin handshake that must be refused.
