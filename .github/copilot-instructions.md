# OZone Framework — Developer Copilot Instructions

> All paths are relative to the repository root. Namespace root: `OZONE\Core` -> `oz/`.
>
> This file holds the rules, and what one read of the code does not tell you. Signatures, option
> lists and settings keys are documented where they live (phpdoc, `oz/oz_settings/`, `--help`):
> read them there rather than here.

---

## IMPORTANT

- **No hallucination or invention.** Read the actual source files before generating code, docs, or this file. Focus only on what can be directly observed in the codebase.
- **Keep this file current, and small.** When a change alters an API, a behaviour, a convention or a path described here, update this file in the same change (`CLAUDE.md` and `AGENTS.md` are symlinks to it). Refresh the phpdoc and OpenAPI texts the change touches too. Stale instructions are worse than none. Add rules and non-obvious behaviour; never copy here what the code already says (method lists, option tables, settings keys).
- **When a bug or issue is found, do not fix it directly** — ask for feedback and approval first. Open findings live in `ISSUES.md`; a fixed item leaves it, and its breaking changes go in `CHANGELOG.md` (Unreleased).
- No too verbose comment blocks on obvious code. Keep comments concise and focused on non-obvious insights or rationale.
- **Symlinks:** If `AGENTS.md` or `CLAUDE.md` do not exist, symlink them to `.github/copilot-instructions.md`.
- **Direct dependencies only.** Only use packages listed in `require` or `require-dev` of `composer.json`. Do not rely on transitive dependencies — they are not guaranteed to be present and can change without notice. An optional integration may use what `suggest` declares (`ext-redis`, `ext-memcached`, `ext-curl`, the server bridges' `ext-swoole` / `spiral/roadrunner-http`, and `phpunit/phpunit` for the test kit's `IntegrationTestCase` and traits), and must not need it until it is used; psalm reads the bridges' server APIs from `stubs/`.
- **Strict types.** Every PHP file starts with `declare(strict_types=1);`.
- **Indentation — IMPORTANT.** Use **tabs** (never spaces) for all PHP indentation. This applies to every generated or edited PHP file without exception.
- **"oz" casing.** When uppercasing "oz", always use `OZ`, `OZone`, or `ozone` — never `Oz` or `Ozone`.
- **Assertions.** Use snapshot assertions where they best convey expected output; use `assertEquals()` / `assertSame()` with inline values for simple or small structures. Never use third-party snapshot packages — only project-owned helpers.
- **Real services, never fakes.** Tests talk to real MySQL, PostgreSQL, Redis, MinIO, ClamAV and worker servers, run by `docker/compose.yaml`; never a hand-written stand-in for one.
- **Everything runs through `make`, in Docker** (section 20). Never create a `phpunit.xml`.
- **Static analysis.** psalm runs at level 4 with no baseline and `make lint` has no error: never add a baseline, or a suppression, to get there. Level 4 does not ask whether a PHP function's `false` / `null` return reaches a typed parameter -- a `TypeError` under `strict_types` -- so check such returns in new code; the level-3 checks the code passes stay errors (`psalm.xml`). `make cs` has 0 errors, and no `oz/` line exceeds 120 characters. Its warnings are accepted and not to be re-audited: long rows in the data files (`oz_schema.php`, the `lang/` catalogs) and in tests, the `@` of a call whose result is checked right after, and two scripts that define constants or helpers (`scope_build.php`, `run_benchmarks.php`).
- **Request-scoped state belongs on the `Context`, never in a `static`**: a worker process serves many requests (section 2).
- **No Unicode shortcut characters in PHP comments or docblocks.** Use plain ASCII equivalents:

| use      | don't use   |
| -------- | ----------- |
| `->`     | `→`         |
| `<-`     | `←`         |
| `<->`    | `↔`         |
| `-->`    | `───▶`      |
| `>=`     | `≥`         |
| `<=`     | `≤`         |
| `!=`     | `≠`         |
| `*`      | `×`         |
| `/`      | `÷`         |
| `-`      | ` —` or `–` |
| `IN`     | `∈`         |
| `NOT IN` | `∉`         |
| `...`    | `…`         |

---

## 1. Architecture

OZone is a PHP 8.1+ service-oriented REST API and web framework.

| Layer         | Location                                 | Purpose                                                            |
| ------------- | ---------------------------------------- | ------------------------------------------------------------------ |
| Bootstrap     | `oz/OZone.php`                           | Static facade — entry points, router creation, installation checks |
| App contract  | `oz/App/AbstractApp.php`                 | Directory resolution, settings/templates sourcing                  |
| Request cycle | `oz/App/Context.php`                     | Per-request container — auth state, request, response, route info  |
| Runtime       | `oz/Runtime/`                            | How a request ends, where its response goes, server bridges        |
| Routing       | `oz/Router/`                             | HTTP router with guards, middlewares, rate limiting, forms         |
| Services      | `oz/App/Service.php`, `oz/Services/`     | Base controller class + built-in services                          |
| Auth          | `oz/Auth/`                               | Authentication methods, providers, services, events                |
| ORM/DB        | `oz/App/Db.php`, `oz/Columns/`           | Gobl ORM integration, custom column types                          |
| Forms         | `oz/Forms/`                              | Validation engine for route inputs                                 |
| CRUD          | `oz/CRUD/`                               | Access-controlled Gobl ORM event listeners                         |
| Settings      | `oz/App/Settings.php`, `oz/oz_settings/` | Layered PHP-file config system                                     |
| Hooks/Events  | `oz/Hooks/`                              | Lifecycle events (boot, request, response, finish, DB)             |
| Plugins       | `oz/Plugins/`                            | Plugin system with scoped source/settings/data directories         |
| Migrations    | `oz/Migrations/`                         | Schema versioning with diff-based migration generation             |
| REST          | `oz/REST/`                               | RESTful CRUD trait, OpenAPI doc generation                         |
| CLI           | `oz/Cli/`                                | Command-line tools built on `silassare/kli`                        |
| Sessions      | `oz/Sessions/Session.php`                | Cookie-based session management tied to `OZSession` DB entity      |

### Request lifecycle

```
OZone::run($app)  /  OZone::handleRequest($request, $sink)   # one-shot  /  worker loop
  +-- bootstrap($app)              # once per process: error handlers, plugins, BootHookReceivers,
  |                                # CRUD listeners, InitHook
  +-- Context::handle()
  |   +-- RequestHook::dispatch()     # MainBootHookReceiver rejects disallowed origins here
  |   +-- Router::handle()
  |   |   +-- Route matching (static/dynamic, priority-ordered)
  |   |   +-- RouteInfo construction
  |   |   |   +-- $authenticator($routeInfo)
  |   |   |   +-- callGuards()
  |   |   |   +-- runMiddlewares()
  |   |   |   +-- selectInterceptor()   # first interceptor where shouldIntercept() == true
  |   |   +-- $ri->checkRouteForm()     # skipped when an interceptor is active
  |   |   +-- RouteBeforeRun::dispatch()
  |   |   +-- Router::runRoute()        # effective handler -> Response
  |   |       +-- $ri->finalize($response)   # runs RouteInfo::onSuccess() callbacks on success
  |   +-- exceptions -> BaseException::tryConvert()->informClient()
  +-- Context::respond()
      +-- ResponseHook::dispatch()  # CORS and security headers added here
      +-- ResponseEmitter::prepare(), then emit() -- or the worker's response sink
      +-- FinishHook::dispatch()    # GarbageCollector, on 1 request in OZ_GC_PROBABILITY
      +-- Runtime::current()->terminate()   # exit, or unwind to the worker loop
```

`Context` is the request's facade: the context tree (root / current / parent / sub-request), router,
request, response and `handle()`. Its other concerns live in `Http\ClientInfo` (`client()`: IP,
host, trusted-proxy check, origin, CORS headers), `App\ContextAuth` (`authState()`), `App\Navigator`
(`navigator()`: redirects, sub-requests, the redirect history per root context) and
`Http\ResponseEmitter`. The `Context` methods that delegate to them (`getUserIP()`, `auth()`,
`redirect()`, `callRoute()`, ...) are permanent, not deprecated. `Request` delegates body parsing to
`Http\RequestBodyParser` (`Request::registerMediaTypeParser()`) and the method override to
`Http\MethodOverride`.

---

## 2. Bootstrapping, Runtimes and Workers

**Entry points**: `OZone::run(AppInterface $app): never` in a scope's `public/{scope}/index.php`;
`OZone::handleRequest()` in a worker loop; `oz/index.php` -> `Cli::run($argv)` for the CLI. The
project's app class (extends `AbstractApp`) is `app/app.php`, which `Utils::isProjectFolder()` looks for.

**Boot hook receivers** (`oz.boot`, `BootHookReceiverInterface::boot()`) run early — **the database
and context are NOT available yet**: only register event listeners there. `InitHook` fires at the end
of `OZone::bootstrap()`, with the database available.

**The database is initialized when first used**: `db()`, or a generated ORM class being loaded
(`Db::initOnFirstUse()` makes each ORM namespace a lazy `ClassLoader` namespace: its first class
initializes the database, then loads from where Gobl generated it). A request that never touches the
database never builds the schema. **Never load an ORM class — a constant counts — while registering
routes or in `boot()`**: it initializes the database for every request (`AuthorizationService` names its
route parameter `REF_PARAM` for that reason). On the command line `OZone::bootstrap()` initializes it,
and a schema that fails to prepare is recorded in `OZone::getDbInitError()` instead of being fatal, so
`oz doctor check` and `oz migrations rollback` still run on a broken project; anything that then touches the
database throws it (`Utils::assertDatabaseAccess()`).

`OZ_OZONE_IS_CLI` is `PHP_SAPI === 'cli'`: ask `OZone::isCliMode()` instead, which means "there is
no HTTP client to answer" and comes from the runtime — a worker runs under the `cli` SAPI and is not
a command line.

### How a request ends: the runtime

How a request ends is the runtime's decision (`oz/Runtime/`, `Runtime::current()`). `Context::finish()`
flushes, dispatches `FinishHook`, then asks the runtime to stop:

| Runtime          | When                                                 | `terminate()`            | `isConsole()` |
| ---------------- | ---------------------------------------------------- | ------------------------ | ------------- |
| `CgiRuntime`     | PHP-FPM, mod_php, the built-in server                | `exit`                   | false         |
| `ConsoleRuntime` | the `oz` command line                                | `exit`                   | true          |
| `WorkerRuntime`  | FrankenPHP worker mode, RoadRunner, Swoole, ReactPHP | throws `RequestFinished` | false         |

`terminate()` is `: never` under all three, and that is a **published contract**: `respond()` and
`finish()` are `: never`, so a handler, guard or view — in an application as much as in the
framework — may respond and then fall through to a throw or a `return` it expects to be unreachable
(`MainBootHookReceiver` throws right after the welcome page; `Navigator::redirectRoute()` ends in
`respond()`). A worker therefore **unwinds** rather than skipping the exit, which would run every one
of those lines. `Context::handle()` re-throws `RequestFinished` ahead of its generic
`catch (Throwable)`. **Never catch `Throwable` around a `respond()` without re-throwing
`RequestFinished`**, or a worker turns an answered request into an error.

`Runtime::detect()` asks about the worker loop **first** — `frankenphp_handle_request()`, `RR_MODE`,
or `OZ_RUNTIME=worker` — and only then about `PHP_SAPI`: RoadRunner, Swoole and ReactPHP run under
the `cli` SAPI, and a console runtime answers a 404 by printing it and killing the worker. An
installed extension is never evidence; an unrecognised loop declares itself with
`Runtime::set(new WorkerRuntime('name'))`.

### Where the response goes: worker servers

`OZone::handleRequest(HTTPEnvironment|Request|null $request = null, ?ResponseSinkInterface $sink = null)`
is the worker entry point: it gives the request its own context tree, answers it, and releases what
belongs to it whatever happened (`OZone::endRequest()`), keeping process-wide configuration (routers,
settings, database, registries).

**Per-request state kept outside the `Context`** — a static, a singleton, a memoized value — releases
itself on `Hooks\Events\EndRequestHook`, from a listener registered once in a boot hook receiver's
`boot()`: the framework's own (`ApiDoc`, `BaseException`'s already-handled flag, the runtime cache)
in `MainBootHookReceiver::boot()`, an application's in its own. `endRequest()` dispatches it (before
and after each request under a worker: listeners must be idempotent), then releases the context tree
last, even if a listener throws. Better still, keep no context at all: a long-lived object reads the
current request with `context()` when it needs it (`ApiDoc`, the CRUD listeners). Per-request
memoization goes through `CacheRegistry::runtime()`, which is released that way — never a function
`static`, which lives as long as the process.

A server that hands requests over as objects (RoadRunner, Swoole) passes the `Request` it built and a
**response sink** (`Runtime\Interfaces\ResponseSinkInterface`): the finished `Response` object goes
to the sink instead of `header()` / `echo`. The sink is called exactly where the response would be
written — after `ResponseHook` and `ResponseEmitter::prepare()`, before `FinishHook` — and a
sub-request answers through its root's (`Context::getResponseSink()`). With a sink, `finish()` does
not flush PHP's output (under RoadRunner that output is the protocol pipe), and the last-resort error
page of `BaseException::criticalDie()` goes to the sink too. A sink reads the body with
`ResponseEmitter::bodyChunks()`, which honours a byte range and never loads the body whole.

Building the request: `HTTPEnvironment::fromParts($method, $uri, $headers, $server)` derives the CGI
variables (`$server` wins: `REMOTE_ADDR` must be the TCP peer, since the trusted-proxy check reads
it), then `Request::createFromHTTPEnvironment($env, $body, $parsed_body, $uploaded_files)`. **Given a
body, the request did not come through PHP: `php://input`, `$_POST` and `$_FILES` are never read.**
Uploads a server parsed are not PHP's, so they are built with `sapi: false`
(`UploadedFile::fromFilesArray($files, false)`), which moves them with `rename()`.

Bridges (`oz/Runtime/Bridges/`), each declaring its `WorkerRuntime`:

| Bridge                           | Needs                    | Notes                                                                                  |
| -------------------------------- | ------------------------ | -------------------------------------------------------------------------------------- |
| `FrankenPhpBridge::serve($app)`  | FrankenPHP worker mode   | FrankenPHP resets the superglobals per request: no sink                                |
| `RoadRunnerBridge::serve($app)`  | `spiral/roadrunner-http` | Is the sink; streams bodies above 1 MiB through the worker                             |
| `SwooleBridge::attach($s, $app)` | `ext-swoole`             | Bootstraps in each worker's `workerStart`, never before the fork; turns coroutines off |

`make test-runtimes` serves OZone from real FrankenPHP, RoadRunner and Swoole containers and tests it
over HTTP (`tests/Runtime/Servers/`); `tests/Runtime/WorkerLoopTest` runs `tests/Support/worker_loop.php`
as its own process, with and without a sink.

Anything request-scoped belongs on the `Context`: `Router::runRoute()`'s recursion trail lives on the
root context's `Navigator`, and `BaseException::$just_die` needed `release()`, for that reason. The
same goes for a context captured at boot: `bootstrap()` builds its context from a mock environment
under a worker (there is no request yet) and `handleRequest()` releases it, so nothing may keep it:
CRUD listeners read the request being handled when an event fires (section 11).

A throwable that escapes the loop itself — bootstrap, the loop, a bridge; `handleRequest()` answers
every request's — is written to stderr by the global exception handler, which then exits 1, so the
server reports why its worker died.

---

## 3. State Directories: `data/` and `.ozone/`

**`data/` must be consistent across instances, persist, and be backed up. `.ozone/` is per instance
and may be deleted at any time** (caches, logs, `oz db build` output). `Scopes\StateLayout` owns the
layout inside `data/`: **kind first, then scope** — the level a backup rule, a volume and a retention
policy are written at.

| Path                    | Accessor                   | Holds                                                |
| ----------------------- | -------------------------- | ---------------------------------------------------- |
| `data/settings/{scope}` | `getStatefulSettingsDir()` | stateful settings (`Settings::set()`)                |
| `data/files/{scope}`    | `getPrivateFilesDir()`     | private files (`PrivateLocalStorage`)                |
| `data/static/{scope}`   | `getPublicFilesDir()`      | public files, reached through the `static` symlink   |
| `data/tmp-fs/{scope}`   | `getTempDir()`             | `TempFS`: chunked uploads, accepted `ValidatedFile`s |
| `data/state/{scope}`    | `getStateStoreDir()`       | file-backed durable state                            |

`{scope}` is `ScopeInterface::getStateSlug()`: `root` for the application, the scope name, or
`plugins/{name}`. Logs are `.ozone/logs/ozone.{scope}.log`, rotated (`OZ_LOG_MAX_FILES`); caches are
`.ozone/cache/scopes/{scope}/`.

- **`data/` is never created automatically** — it is the volume a deployment mounts, so a node whose
  disk did not come up says so. Everything _inside_ it is created on demand.
- **Public files go through a symlink**: `{document root}/static` -> `data/static/{scope}`, created by
  `oz project create`, `oz scopes add` and `oz project link` (after a clone or a deploy: the links are
  not version-controlled). PHP never depends on it; `StateLayout::link()` is idempotent and reports
  `blocked` rather than deleting real content in the way.
- `example.com/static/x` and `scope.example.com/static/x` are different files, and either may hold what
  no DB row tracks. But **`OZFile` rows resolve against the root pool** (a row has a storage name and a
  `Y/m/name` ref, no scope), so the local drivers use `app()->getPublicFilesDir()` /
  `getPrivateFilesDir()`: a scope's own pool is never an `OZFile` storage target.
- **`FilesManager::cd()` moves the manager in place**: never walk one that was passed in
  (`StateLayout::dirAt()` builds a fresh one for that reason).

---

## 4. Settings

`OZONE\Core\App\Settings` (final, static). Groups are PHP files returning arrays, loaded from
`oz/oz_settings/` then the app's sources; **the last source wins per key**. Group `foo.bar.baz` is
`foo.bar.baz.php` (dots are literal); `foo/bar.baz` is `foo/bar.baz.php`. Merging
(`Settings::applyMergeStrategy()`): a group's own keys merge, so overriding one in `{app}/settings/`
keeps the others, and by default a **list is one value: a source that declares one replaces it whole**.
A key a source does not mention keeps the value another gave it, so a rule is emptied with `[]`, never
by leaving it out.

**A key may say how it is merged**, in the file that owns it, under `Settings::MERGE_KEY` (`@merge`),
which never reaches the values:

```php
return [
    Settings::MERGE_KEY => ['OZ_CORS_ALLOWED_HEADERS' => Settings::MERGE_APPEND],
    'OZ_CORS_ALLOWED_HEADERS' => ['accept', 'content-type'],
];
```

- `default` — a map merges key by key, anything else is replaced.
- `append` — lists are appended: for what every source adds to (CORS headers).
- `replace` — always the later value, whatever its shape.
- `lock` — **no source after this one may change the key**, and one that tries fails loudly;
  `Settings::set()` refuses it too. What an allow-list wants (`OZ_REDIRECT_ALLOWED_HOSTS`): a plugin
  loaded after the application cannot widen it. A later source may **tighten** a strategy (add a lock)
  and never loosen one, which is what makes the lock worth anything.
Each file in `oz/oz_settings/` documents its keys.

- `Settings::set()` / `unset()` write the **stateful** directory by default (`data/settings/{scope}/`),
  for runtime overrides that must not touch version-controlled files; `$stateful = false` writes the
  **source** directory (`app/settings/`, `scopes/{name}/settings/`), for dev-time scaffolding
  (`oz services generate`, `oz settings set --source`).
- In production outside the console, each **source** directory is read from a compiled bundle
  (`.ozone/cache/settings/`) named after the directory, the release, OZone's version, the `.env`
  signature and the directory's mtime; stateful ones (`Settings::addSource($dir, true)`) are always
  read file by file. A settings file returns plain data (one holding an object leaves its directory
  unbundled) and may read `env()`: `.env` is read through a compiled copy (`.ozone/cache/env/`, named
  after its content), and editing it gives new bundles.
- **`oz.config` is blacklisted**: no runtime edit.
- `SettingsGroup` is `@internal`: never use it directly.
- Every message code must be in both `lang/oz.en` and `lang/oz.fr` (`tests/Lang/CatalogTest`).
- **The API sends message codes, never text**: a client translates them from `oz lang export --json`
  (`Polyglot::exportCatalogs()`), the catalogs of the enabled languages merged as `Polyglot` reads
  them, the texts raw (`{var}`, `{var | filter}` and `{{KEY}}` are the client's to resolve), with the
  default language and the declared filter names, each of which the client must implement too.
  Placeholders are filled **in one pass** (a value is never read for placeholders); a missing text, or
  a language without a catalog, falls back to the default language, then to the key itself.
- **One message syntax for every catalog** (`Lang\Message\`, described in `MessageParser`'s
  docblock): a client of the API implements the same parser, so a change to it is a change to a
  contract. Plural categories come from ext-intl only (`PluralRules`); without it a category never
  matches, by design, and the browser, which always has rules, still agrees on `=N`, the comparisons
  and `other`. Compare numbers with casts, not `==` (the fixer's `strict_comparison`).
- `oz.proxies`: IP or CIDR => bool, `false` wins. Forwarding headers are only read from these peers.
- `OZ_REDACT_SENSITIVE_DATA` (`oz.logs`) is a safety net, on in production (`ENV_MODE=production`) —
  never put passwords, tokens or raw payloads in exception data.
- In production, schedule `oz cron run` every minute or run `oz cron work` (section 16): requests then
  leave both the due tasks and the garbage collection to it, by themselves.

---

## 5. Routing

Routes are registered by `RouteProviderInterface::registerRoutes(Router $router)`, enabled in
`oz.routes` (both routers), `oz.routes.api` or `oz.routes.web`. Route options (`name()`,
`withAuthentication()`, the guard shortcuts of `Router\Traits\RouteGuardShortcutsTrait`,
`middleware()`, `guard()`, `interceptor()`, `form()`, `withCSRF()` / `withoutCSRF()`, `resumable()`,
`param()`, `priority()`, `rateLimit()`) chain on the route or on a `group()`. `Router::map()` takes an
optional `$parent` (`RouteSharedOptions`): any route can then act as the parent of another, sharing
path, name, guards and middlewares without a group block.

- Params: `/users/:id` captures `[^/]+` unless constrained with `param()`; read with `$ri->param('id')`.
  Named routes build URIs: `$context->buildRouteUri('route:name', ['id' => 42])`.
- Not found -> `RouteNotFound` -> `NotFoundException`; wrong method -> `RouteMethodNotAllowed` ->
  `MethodNotAllowedException`, except an OPTIONS preflight, which passes.
- **Interceptors** (`RouteInterceptorInterface`) run after guards and middlewares; the first, by
  priority descending, whose `shouldIntercept()` is true replaces the handler, and **form validation
  is then skipped** (`getCleanFormData()` is empty). Two are always injected:
  `FormResumeRouteInterceptor` (priority 1: a resumable route with `X-OZONE-Form-Resume: ?1`) and
  `FormDiscoveryRouteInterceptor` (priority 0: `X-OZONE-Form-Discovery: ?1`, when
  `OZ_FORM_DISCOVERY_HEADER_ALLOWED`). Those checks are on `Request`, not `Context`.
- `Route::key()` is a route's stable identity across requests; per-route state (route-bound form
  sessions, the `Form::resumable()` cache, per-route rate limits) is keyed by it. A route not named
  explicitly is auto named from its methods and path (`route_{xxh3}`), never from registration order.
- **Route table** (production, outside the console): `OZone::createRouter()` routes through a
  `Router\RouteTable` kept in the scope's cache dir, its file named by the release (project dir), the
  providers and the mtimes of the application's and the scope's stateful settings dirs
  (`Settings::set()` touches the one it writes). The first router of a
  release registers every provider and saves the table (`Router::compileTable()`); the next ones
  register at once only the providers that do more than map routes (no route, or a global parameter),
  then per request the provider of the matched route or of a route looked up by name
  (`Router::registerProviders()`); `getRoutes()` registers them all. A table whose route disagrees with
  the code is deleted and the router registers everything. So a provider's `registerRoutes()` maps
  routes and nothing else a request could depend on, whatever other provider ran before it.
- **Per-request reactions use `RouteInfo::onSuccess()`** (`@internal`; run by `finalize()` once the
  handler succeeded), never a `ResponseHook::listen()` registered during a request: listeners live for
  the process and would pile up in a worker (`tests/Hooks/ListenerAccumulationTest`).
- `RouteSharedOptions::resolved()` merges a route's options with its groups' (lists outermost first;
  params and interceptors by name; CSRF, resume and form declaration from the innermost level that
  sets them). It is cached, and any option change anywhere invalidates every cache.
- Route names: OZone's use the `oz:` prefix; projects use their own (`myapp:resource.action`).

---

## 6. Services, Views and Templates

`App\Service` (abstract) implements `RouteProviderInterface` and `ApiDocProviderInterface`; its
constructor takes `Context|RouteInfo`. `respond()` builds the JSON envelope from `$this->json()`:
`{error, msg, data, utime, stime}` (`stime`: session expiry, when applicable). **Every answer has that
shape**: an error built from an exception (`BaseException::getJSONResponse()`), a route guard, an
authentication challenge, the cron endpoint and a command's `--json` output too.

**Never write a JSON body by hand.** `Response::withJson()` takes what `JSONResponse::toEnvelope(?Context)`
built, which is the one place `utime` and `stime` are added: anything answering `withJson(['ok' => true])`
invents a shape no client reads.

`Web\WebView` renders HTML: `setTemplate('oz://path/to.blate')->inject([...])`; the context reaches
templates through `BlatePlugin::CONTEXT_INJECT_KEY`.

`Web\BlatePlugin` is **`@internal`** — never reference it from application or plugin code. Its
`register()` runs from `.blate.php` at the project root (picked up by `Blate::autoLoad()`, generated
once per project, and must call `BlatePlugin::register()`) and registers the template helpers
(`setting`, `env`, `log`, `t`, `uri`, `route`) and globals (`request_uri`, `base_url`, `lang`,
`oz_version`, `oz_version_name`); its `boot()` defers to Blate's first use (`Blate::onFirstUse()`:
a template compiled or rendered) loading the framework's and the project's `.blate.php` and setting the
cache directory, so a request that renders no template does no Blate work -- Blate's own built-ins are
registered on first use too.

`FS\Assets` owns the `oz://` protocol: `Assets::localize()` resolves `~core~` (framework) and
`~project~` prefixes, then scans the sources `Assets::addSource()` registered (a plugin adds its
templates there).

---

## 7. Forms & Validation

Forms attach to routes with `->form(...)`: a `Form` instance, a factory callable, a
`RouteFormDeclaration`, or a resumable form provider class (**not** a `Form` subclass name).
`RouteInfo::checkRouteForm()` validates after guards and middlewares, only when no interceptor
handles the request; the result is `$ri->getCleanFormData()`.

Field helpers (`FieldContainerHelpersTrait`: `string()`, `int()`, `email()`, `file()`, `enum()`, ...)
take `required` as second argument and return the `Field`, so field options chain; type options go
through `Field::configureType(static fn (TypeString $t) => $t->min(2))`, whose callback must be typed
for the field's type class (`TypeError` otherwise).

- **Raw and clean data never mix.** `Form::validate()` threads a `FormValidationContext`
  (`getUnsafeFormData(): FormData`, `getCleanFormData(): FormDataClean`). `FormDataClean` does not
  extend `FormData`; each `RuleSet` declares the side it reads (`RuleSetDataType`): `expect()` reads
  UNSAFE data before fields are validated; `ensure()`, `Field::if()`, `Fieldset::if()` and
  `TypesSwitcher::when()` read CLEANED data.
- **Declaration order is load-bearing, and enforced.** Cleaned values are written as they are
  produced — root fields in declaration order, then fieldsets in declaration order — so a condition
  can only read fields validated before it; reading a later field of the same pass throws
  (`FormValidationContext::assertReadable()`). Fields from outside the pass (an earlier wizard step)
  or of a skipped fieldset are fine.
- **Refs are identity.** Fields and fieldsets are keyed by ref (`address.street`), stable across
  requests; rule sets have refs too (`checkout.@expect[0]`, `checkout.promo@if`). `Form::merge()`
  clones and keeps the original parent, and **throws on a colliding ref**: name forms whose refs
  would collide.
- **Every rule set is sent to the client**: a form's `expect()` and `ensure()`, a static
  fieldset's own, the conditions. A server-only one, holding an `AsyncValue::secret()`, serializes as
  `{ref, $secret: true}` and is resolved through the form session `evaluate` endpoint (a switcher's
  secret branches too, in `switchers`); a literal
  operand is sent as it is, so a value the client must not see belongs in an `AsyncValue::secret()`.
- **A refused form names what refused it**: `InvalidFormException` data carries `field` (the
  ref) for a missing or refused value and `rule` (the set's ref) for a failed rule set, which a client
  maps onto what it rendered from the bundle. What caused it (`_suspect`, `_rule`) stays private.
- **Two numbers are the same when they are equal**: `eq`, `neq`, `in` and `not_in` are strict,
  except between an int and a float (`Rule::same()`), so a rule's literal need not match the PHP type of
  the field's clean value, and a client reading the rule from JSON (which writes `3.0` as `3`) agrees.
- **A rule on a cleaned value uses an operator its field's type allows**, as a Gobl filter does
  (`TypeInterface::getAllowedFilterOperators()`: a boolean has no order). `Form::validate()` and
  `Form::toArray()` refuse a violation with a `RuntimeException` (`RuleSet::assertOperatorsFit()`).
  `is_null` / `is_not_null` are always allowed (an absent optional field reads as null); `expect()`
  reads the raw payload and is not checked; a field whose type a `TypesSwitcher` picks, or one the
  form does not know (a dynamic fieldset's), is skipped.
- **Never pass `$this->ensure()->...` as a fieldset condition**: `ensure()` registers a form-level
  assertion, so the form is rejected instead of the fieldset skipped. Use the fieldset's `->if()`.
- Fieldsets: `fieldset()` (static, populated at definition time) or `dynamicFieldset()` (factory
  called with the validation context).
- **`AsyncValue`: a value the server resolves on validation, whose disclosure its constructor
  decides**. `AsyncValue::secret($factory)` never leaves the server and withholds its rule set;
  `AsyncValue::public($factory, $preview)` sends the preview (`{$preview: {value}}`) and the client
  checks the rule itself. The constructor is private so that a secret cannot become public by adding
  an argument: turning one into the other is a visible change of method, and
  `ResumableFormServiceTest::testASecretValueReachesNoResponse` fails on any leak. Previews are
  computed only in `Form::toClientArray()`, which every path sending a form to a client uses (the
  envelope's `form`, the OpenAPI `x-oz-form` `init_form`); `AsyncValue::withPreview()` is internal.
- **A form's version** is `Form::version()` when its author sets one (a generator: its definition's
  hash), else a fingerprint of the whole bundle a client is sent, **previews left out**
  (`AsyncValue::withoutPreview()`), since they vary per user and per moment. The server never checks it
  on resume (unknown refs are dropped and replayed values validated again): it tells a client whether
  a saved draft still fits, and keys the resume cache of a form with neither id nor name.
- **Run `make fix` knowing its `strict_comparison` rule turns `==` into `===`**: where a loose
  comparison is meant (`Rule::same()`), write it so the fixer cannot rewrite it.

### CSRF

A route guard (`CSRFRouteGuard`, `->withCSRF(RequestScope::STATE)`), not a form property. Tokens are
`id.issued_at.mac` (HMAC with the app secret over id, issue time and scope ID), sent in `_csrf` or
`X-XSRF-TOKEN`, valid `OZ_CSRF_TOKEN_LIFETIME` seconds. By default (`OZ_CSRF_SESSION_DEFAULT`), unsafe
requests authenticated by the session cookie need a STATE-scoped token even without `withCSRF()`;
bearer / API-key requests, requests without the session cookie and sub-requests are not checked. Opt
out with `->withoutCSRF()` (webhooks, cross-site posts on purpose); a child can opt back in. Sessions
expose the token in the readable `XSRF-TOKEN` cookie (axios and Angular send it back); pages use the
`csrf_token` template global.

### Discovery

`X-OZONE-Form-Discovery: ?1` makes a route answer its form bundle instead of running its handler
(`FormDiscoveryRouteInterceptor`). The bundle is a **top-level `form` key next to `data`** in the
envelope, not inside it. Its `action` is the **absolute path** of `submitTo()` (`Uri::getAbsolutePath()`:
no scheme, no authority), so an answer is not tied to the host it was asked on, and `null` when the form
declares none. Header booleans are RFC 8941: `Headers::getBool()` reads `?1` and `?0` and
**nothing else** (any other value silently falls back to the default), which holds for every boolean
header (`X-OZONE-Form-Resume`, ...).

A field's type is sent through `Field::frontendType()`, which is **enough for a client to check a value
the way the server will**: an enum adds its `enum_cases`, and a type implementing
`FrontendTypeOptionsInterface` adds the rules it takes from the settings, resolved at discovery (a
password's effective `min` / `max`, a username's `min`, `max` and portable `pattern`, a gender's
`allowed` values). A type whose rules depend on a setting implements it rather than leaving the client
to copy the setting.

A route whose form is declared through a provider (`RouteFormDeclaration::provider()`) **discovers no
form**: that declaration holds neither a form nor a factory, so `resolve()` gives null and the answer
carries no `form` key. Its forms are the provider's steps, read through the resume flow below. Only a
form or a factory declaration is discoverable.

### Resume

- **`init` always opens a new session**, it never picks up one under way: a client that wants to come
  back to a filling keeps the `resume_ref` and reads `state` with it. `next` after the **init step**
  keeps no history, so a `back` there answers `OZ_FORM_SESSION_NO_HISTORY`: history starts once a
  step of the STEPS phase was submitted.
- **`Form::resumable()`** (opt-in per form; route-level `->resumable()` does not enable it): a failed
  attempt saves what validated before the failure, the next attempt replays it
  (`Field::revalidateStored()`), and the entry is cleared through `onSuccess()`. Entries (store
  `oz:form:resume`) are keyed by form identity (id, else name, else version — **set an id**), the
  `Route::key()` partition and the resume scope. A replayed value that no longer validates is dropped.
- **Resumable form sessions** (`Forms\Resume\`): multi-step, server-side sessions driven by a provider
  (`ResumableFormProviderInterface`, registered by name in `oz.forms.providers`) through the
  `FormSessionManager` state machine (INIT -> STEPS -> DONE). Driven either by the standalone
  endpoints (`/form/:provider/init|next|back|cancel|evaluate|state`; the `resume_ref` travels in
  `X-OZONE-Form-Resume-Ref`), which **reject providers with `requiresRealContext()`** (the default),
  or on the route itself (`->form(MyProvider::class)` or `->resumable()`, with
  `X-OZONE-Form-Resume: ?1` and `X-OZONE-Form-Resume-Action`), where the route's auth, guards and
  middlewares apply. The completed session is consumed by submitting the route with its ref, or by
  `FormSessionStore::requireCompletion()`.
- **Binding** (`FormSession::assertUsableBy()`): a session is usable only by its provider, a
  route-bound one only on its route, and an unbound session of a provider requiring real context is
  never trusted.

---

## 8. Authentication, Authorization and Request Security

Methods (`oz.auth.methods`, per router in `OZ_AUTH_API_AUTH_METHODS` / `OZ_AUTH_WEB_AUTH_METHODS`,
per route with `withAuthentication()`): `SESSION` (cookie, always satisfied, stateful), `BEARER` and
`API_KEY_HEADER` (resolve an `OZAuth`), `BASIC`, `DIGEST`. The active one is `$context->auth()`;
guards store results for `$ri->getGuardStoredResults(GuardClass::class)`.

**OZAuth** providers drive multi-step authorizations (email / phone ownership, account recovery, file
access): `Auth::provider($context, $auth)`; `AuthorizationService` exposes
`/auth/:ref/authorize|refresh|state|cancel`; `->withAuthorization('auth:provider:...')` guards a
route with one.

- An authorization carries a **label shown to the user**: set it on the scope
  (`AuthorizationScope::setLabel()`), otherwise `generate()` names it after the provider, since the
  column requires one.
- **What a client is told**: opening one answers `{auth_ref, auth_refresh_key}` (`generate()` puts
  them there), plus what the provider adds: `first` for a message just sent, `two_fa_required` and
  `channel` for a 2FA challenge. A provider **adds** its keys with `setDataKey()`; `setData()` would
  drop the reference and leave the client with nothing to answer. The token travels only in the
  message, as the link `GET /auth/link/:ref/:token`. Starting a verification again opens a new
  authorization rather than refreshing the one open.
- **What must be proven before an account exists or is handed back** is configurable per user type in
  `oz.auth.verification`: a map of user type to accepted provider names, `*` being the fallback.
  An **empty list** means nothing is proven (the identifier is taken as given, verified later), and a
  project's own provider is named here. `VerificationPolicy` reads it; `/signup` and
  `/account-recovery` guard their door only when every type requires something, and the handler always
  enforces the rule of the type it was given, since the type is a form field a guard cannot see.
- **Two-factor authentication** (`Auth2FA`, booted by default): a user with `2fa.enabled` in their data
  store has their login interrupted, the user is detached, an authorization is opened through
  `TwoFactorAuthorizationProvider` on the channel `oz.auth.2fa` selects (`totp`, `email`, `sms`), and
  `OZ_2FA_REQUIRED` carries the reference. The client answers `POST /auth/:ref/authorize` with the
  code; the provider re-attaches the user and the login goes through.

- **Passwords** are checked through `AuthUsers::checkPassword()` only: failures are counted per
  account (or per submitted identifier when unknown) by `LoginThrottle`, unknown accounts are checked
  against a dummy hash, and clients get one generic `OZ_AUTH_INVALID_CREDENTIALS`. `POST /login` is
  also rate-limited per IP. `DigestAuth` counts its failures in the same throttle (per auth key); its
  nonces are HMAC-signed, expire (`stale=true` challenge) and are single-use (per increasing `nc`),
  tracked in `oz:auth:digest:nonces`.
- **Never put passwords, tokens or raw payloads in exception data**: exceptions are logged, and
  `ErrorUtils::redactSensitiveData()` is only a safety net.
- **Never authorize or change state on a GET.** Auth links (`GET /auth/link/:ref/:token`) only render a
  confirmation page whose POST consumes the token, so mail scanners and previews cannot. Web logout
  (`GET /logout`) needs `_csrf`.
- **Redirect targets**: `next` only accepts local paths, the current host and
  `OZ_REDIRECT_ALLOWED_HOSTS`; validate any user-supplied target with
  `TypeUrl::allowAbsolutePath()->allowedHosts(...)`.
- **Client IP: always `Context::getUserIP()`.** Forwarding headers are ignored unless the TCP peer is
  in `oz.proxies`; then `OZ_CLIENT_IP_HEADER` (e.g. `CF-Connecting-IP`) or else the right-most
  untrusted hop of `Forwarded` / `X-Forwarded-For`. Scope IDs never include the port. Behind a
  trusted proxy only, host and scheme (then port) come from `Forwarded` / `X-Forwarded-Host` /
  `X-Forwarded-Proto` / `X-Forwarded-Port`, so URLs, links and the cookie `Secure` flag follow what the
  client used (`OZ_COOKIE_SECURE` can force it).
- CORS (`OZ_CORS_ALLOWED_ORIGIN`, `Http\CorsPolicy`): `'self'` by default, an origin, a list, or `'*'`
  without credentials. Disallowed origins are rejected at `RequestHook`
  (`OZ_CROSS_SITE_REQUEST_NOT_ALLOWED`): a front end served from another origin (a dev server, a
  separate app host) is added here.
- **Cookies are host-only by default** (`OZ_COOKIE_DOMAIN` = `self`: no `Domain` attribute). Naming a
  domain sends the cookie, the session included, to every subdomain of it (RFC 6265), and naming the
  request host breaks behind a proxy that rewrites `Host`, where the browser refuses a cookie for a host
  it never saw. Set a domain only to share cookies across subdomains, on purpose.

Sessions (`Sessions\Session`, table `oz_sessions`) are a boot receiver. They start lazily: an
anonymous request gets no session row and no cookie. A new session is kept only once used — its ID
handed out (`Session::id()`: CSRF token, rate-limit key, form-resume scope), a user attached, or its
store data changed — so never write per-request noise into the auth store. Until then it has no
`OZSession` entity and no ID, so an anonymous request does not touch the database at all. Framework code adds cookies
to a response with `Cookies::applyTo()`, never `withHeader('Set-Cookie', ...)`, which would drop the
handler's own; `$context->requireAuthStore()`
is the stateful store. Force a logout everywhere with
`AuthUsers::forceUserLogoutOnAllActiveSessions($user)`. User repositories implement
`AuthUsersRepositoryInterface` (`oz.auth.users.repositories`).

**Garbage collection** (`App\GarbageCollector`): modules register collectors from `boot()`
(`GarbageCollector::register()`; built in: `oz:sessions`, `oz:auths`, `oz:temp-fs`, `oz:cache`). They run
from the hourly `oz:gc` cron task and, only while no scheduler runs cron (none for an hour:
`CronRunner::isScheduled()`; requests running the due tasks do not count), after the response on 1
request in `OZ_GC_PROBABILITY`; a failing collector is logged and does not stop the others.

---

## 9. Database & ORM

ORM: [Gobl](https://github.com/silassare/gobl) — read `vendor/silassare/gobl/.github/copilot-instructions.md`
before using it. `App\Db` (final, static) holds the instance (`db()`).

`Db::init()` runs lazily (section 2). Its order: column type provider; migration state checked (the schema matching the installed
version is loaded, see section 14); `DbSchemaCollectHook` (plugins add tables); project schema
(`oz.db.schema`); `DbSchemaReadyHook`; ORM classes enabled for the OZone and project namespaces;
`db()->lock()`; `DbReadyHook`, on which the CRUD listeners and entity collections register once,
gated on the recorded migration version (a settings read, never a query).

The installed migration's schema is loaded lazily (`Db::new()` calls Gobl's `setLazySchema()`): each
table is built when first used -- asked for, listed, or reached through a relation or foreign key -- so
a request pays for the tables it touches; `getTables()` builds them all.

- OZone's schema is `oz/oz_default/oz_schema.php`; its entities (`OZONE\Core\Db`) and the project's
  (`{OZ_PROJECT_NAMESPACE}\Db`) are generated by `oz db build`.
- **Always bound a query**: `$qb->find(ORMOptions::makePaginated($max, $page))`; `find()` with no
  options returns every row. Cursor pagination: `ORMOptions::makeCursorBased()` then
  `getItemsWithCursorMeta()`. `RESTFulAPIRequest` extends `ORMOptions` and passes as-is.
- `App\Keys`: `salt()` / `secret()` (from `.env`), session IDs, auth tokens and codes, `id32()` /
  `id64()` refs.
- `Crypt\SignedSerializer` prepends and verifies an HMAC, so only what the app wrote is ever
  unserialized; `DbStore` and `FileStore` store through it (an unsigned entry reads as a miss).

---

## 10. Column Types and Files

Custom column types extend `Gobl\DBAL\Types\Type`, registered in `oz.db.columns.types`: `TypePhone`
(E.164 without spaces), `TypeEmail`, `TypeUrl`, `TypeUsername`, `TypePassword` (hashed in `phpToDb()`
unless already a hash), `TypeCC2`, `TypeGender`, `TypeFile`. `registered()` / `notRegistered()` /
`authorized()` restrict to known values.

- **A `->temp()` file column holds a TempFS `{ref}/{name}` reference, never a path.**
  `ValidatedFile::getPath()` resolves it through `TempFS::path()` at each call, so it follows the
  project directory across instances. Build one with `ValidatedFile::forTempFile()`, read a stored
  value with `forTempValue()`, check it with `isAvailable()` (ref not expired **and** file present),
  never `is_file(getPath())`. `TempFS` rejects a ref or name that is not a single safe path segment.
- **Uploading** (`FS\Services\UploadFiles`, registered by default in `oz.routes.api`): `POST /upload`
  takes the files of one request and wants a signed-in user; a big file goes through
  `POST /upload/chunk/start` (`name`, `size`, `type`, answering a `ref`) then
  `POST /upload/chunk/add` (`ref`, `chunk_index`, `chunk`), and the chunk that completes the declared
  size answers the assembled file. A chunk is at most `CHUNK_MAX_SIZE` (1 MB, the form refuses a bigger
  one) and sending the same index twice is ignored, so a client may retry one. **What was received
  lives in the session** (`requireAuthStore()`), so every chunk comes from the client that started the
  upload, with its CSRF token; `DELETE /upload/chunk/{ref}` drops it. The stored name is never the one
  that was sent: `FS::sanitizeFilename()` slugifies it, adds a random suffix and takes the extension
  from the type.
- **Virus scan** (`FS\Scan\FileScan`, `oz.files.scan`, off by default): every new `OZFile` goes
  through it from `FileEntityTrait::save()`, clones excepted. `sync` scans before the insert and throws
  `FileScanRejectedException` after deleting the content; `async` inserts `pending` and queues a
  `FileScanWorker`. The verdict (`file_scan_state`) is saved on every record sharing the content, and
  `GetFilesView` refuses what `FileScan::isServable()` rejects.
- **Object storage never holds a whole object in memory** (`FS\S3\`). `S3Client` streams through
  `CurlTransport` when `ext-curl` is loaded, else `StreamWrapperTransport`, which cannot stream a
  request body and so uploads in `OZ_MINIO_PART_SIZE` parts. Use `putObjectFromStream()` /
  `getObjectTo()` for anything not known to be small. The payload hash comes from reading a seekable
  body once; `UNSIGNED-PAYLOAD` only for a body that cannot be read twice.

---

## 11. CRUD

`CRUD\TableCRUDListener` subclasses (registered in `oz.gobl.crud`) wrap Gobl ORM events with access
control through `allow(): AllowRuleBuilder` (`ifRole()`, `ifRoles()`, `when()`, `onlyIfIs()`). By
default create is allowed and delete, delete-all and update-all are denied.

`register()` runs once per process and takes no context: a listener reads the request being handled
when an event fires, with `$this->context()` (`Context::current()`) — never one kept from
registration, which under a worker is the boot context, with no user.

---

## 12. Hooks & Events

`Hooks\Hook` extends `PHPUtils\Events\Event` and carries the `Context`; `::listen()` takes a priority
(`Event::RUN_FIRST`, `RUN_LAST`, or a number). **Register listeners from `boot()` only** — once per
process; a second `bootstrap()` does not re-notify the boot receivers, whose `boot()` methods are not
individually idempotent.

| Event                                                       | When                                                                       |
| ----------------------------------------------------------- | -------------------------------------------------------------------------- |
| `InitHook`                                                  | end of bootstrap, database available                                       |
| `RequestHook`                                               | before each request, sub-requests included (origins rejected here)         |
| `ResponseHook`                                              | before the response is sent (CORS and `OZ_SECURITY_HEADERS` here)          |
| `FinishHook`                                                | after the response was sent, whichever way; not for sub-requests           |
| `EndRequestHook`                                            | a request's state is released (`OZone::endRequest()`): per-request cleanup |
| `RedirectHook`                                              | before a redirect (`Uri $to`)                                              |
| `DbSchemaCollectHook` / `DbSchemaReadyHook` / `DbReadyHook` | see `Db::init()`                                                           |

Other families: migrations (`MigrationBeforeRun`, `MigrationAfterRun`, `MigrationCreated`), router
(`RouteNotFound`, `RouteMethodNotAllowed`, `RouteBeforeRun`, `RouterCreated`), auth
(`AuthUserLoggedIn`, `AuthUserLoggedOut`, `AuthUserLogInFailed`, `AuthUserUnknown`), cron
(`CronCollect`), queue (`JobBeforeStart`, `BatchFinished`).

---

## 13. Plugins and Scopes

Plugins (`Plugins\AbstractPlugin`, enabled in `oz.plugins`, booted by `OZone::bootstrap()`) add their
source settings in `boot()` with `Settings::addSource($this->getScope()->getSettingsDir()->getRoot())`;
the stateful settings directory is registered by `AbstractScope`. A plugin's state is under
`data/{kind}/plugins/{name}/`, and its public files inside the application's pool
(`data/static/root/plugins/{name}/`) so one symlink exposes them. `Plugins::ozone()` is the core plugin.

A **scope** is an entry point (`api`, `www`, ...): `scopes/{name}/` (settings and templates overrides,
denied to the web), `public/{name}/` (`index.php`, the `static` symlink) and `data/{kind}/{name}/`.
`OZone::bootstrap()` builds the current scope first, so its stateful settings are a source before any
group loads (the last source, so they win). An
`api` scope loads `oz.routes.api`, others `oz.routes.web`. `oz project create` makes an `api` scope;
`oz scopes add` adds more.

---

## 14. Migrations

`Migrations::getSourceCodeDbVersion()` is the latest migration **file**'s version;
`getInstalledDbVersion()` reads `OZ_MIGRATION_VERSION` (`oz.db.migrations`), the version the
**database** is at — written when a migration runs, and what `Db::init()` uses to load the matching
schema before any connection exists. `getState()` compares the two, so a created-but-unrun migration
is `PENDING`; `hasPendingMigrations()` is also true for a database not installed yet. Migration files
(`{project}/migrations/`) return a `MigrationInterface` generated by Gobl's diff, with unique versions.

`oz migrations check | create --label=... | run [--skip-backup] | rollback --to-version=N` — `run`
backs the database up first unless told not to, and so does `rollback`.

---

## 15. Job Queue

`Queue::get(name)->push(new MyWorker(...))->dispatch()`. A `Job` is a value object; `JobContract` binds
it to a store (`DbJobStore`, or `RedisJobStore` via `oz.redis`); workers implement `WorkerInterface`
and are registered with `JobsManager::registerWorker()`.

```
PENDING -> RUNNING -> DONE
                   -> FAILED -> PENDING (retries left; run_after = now + retry_delay)
                             -> DEAD_LETTER (retries exhausted)
CANCELLED (terminal)
```

- `lock()` is atomic (`UPDATE ... WHERE locked = false`); stores skip jobs whose `run_after` is ahead.
- **Async workers** (`isAsync()`): `runJob()` locks, sets RUNNING and spawns
  `oz jobs run --store=X --job=<ref> --force` **without releasing the lock** — the subprocess owns it
  and releases it through `finish()`. If spawning throws, `runJob()`'s catch finishes (and unlocks)
  exactly once.
- `Queue::setMaxConcurrent()` demotes async workers to synchronous runs above the limit. Chained jobs
  (`Job::setChain()`) use default retry settings.
- **Batches** (`BatchManager::create()`): each job holds the integer `oz_job_batches.id`;
  `BatchFinished` fires once when every job is terminal, dispatched from `JobsManager::finish()`
  through the owning store's `countByBatch()` — never by querying `oz_jobs` directly.

---

## 16. Cron

`CronCollect::listen(fn () => Cron::call($callable, 'name')->everyHour())` (also `Cron::command()`,
`Cron::work()`); these return the `Schedule`, so task options (`inBackground()`, `oneAtATime()`,
`setTimeout()`) need `Cron::addTask()` + `$task->schedule()`. A task without a schedule runs every
minute; with several, any due one fires it. Window predicates (`between()`, `notBetween()`,
`timezone()`) are evaluated lazily at `shouldRun()`.

- A tick (`CronRunner::tick()`) dispatches due tasks as `CronTaskWorker` jobs to `cron:sync` /
  `cron:async`, then runs both queues. **`CronTaskWorker` calls `Cron::collect()` in its constructor**
  (idempotent), because an async task runs in a subprocess that has not collected the tasks.
- **What runs the ticks** (`OZ_CRON_RUNNER`, `oz.cron`): a scheduler -- `oz cron run` every minute
  (crontab, a systemd timer, a host's cron panel) or `oz cron work`, a long-running process; the
  `oz:cron` route (`/oz-cron`, `CronEndpoint`, mapped only with `OZ_CRON_WEB_KEY`, the key in a header
  or the body, never the URL) for an external service; and, by default (`auto`) while no scheduler has checked in for
  `OZ_CRON_SCHEDULER_TIMEOUT` seconds, the requests: the first request of a minute on a server runs a
  tick once its response is sent (`CronRunner`, a `FinishHook` listener; the other requests of the
  minute read one small file, `.ozone/cache/cron.minute`). Ticks from requests and the route run the
  background tasks in-process (no subprocess from a web process).
- **A task runs once per scheduled minute, whoever ticks**: its job's ref is the task's and the minute's
  (`Cron::jobRef()`) and a job store refuses a ref it has (`JobStoreInterface::add()`), so two servers'
  schedulers, requests and the route never run it twice. A tick also dispatches the minutes no tick ran,
  an hour back at most (`Cron::CATCH_UP`), each task once.
- Every tick checks in (the `oz:cron` state store, shared by the servers): `CronRunner::lastTick()`,
  `lastSchedulerTick()`, `isScheduled()`; `oz doctor check` reports it.
- **`oneAtATime()`** is enforced twice through the shared `oz_jobs` table — at dispatch (a PENDING or
  RUNNING job of the task skips it) and at execution (another RUNNING one marks this one done,
  `skipped: true`) — so it holds across servers sharing the database.
- `skipIfLate()` only looks 24 hours back: no effect on monthly or yearly schedules.
- `CallableTask::$callable` has no PHP type (`callable` is not a valid property type): use a
  `@var callable(JSONResult):void` docblock.

---

## 17. Cache and State Stores

**Pick by what losing an entry costs.** Losing a cache entry costs a recomputation; losing a state
entry is visible to a user (a half-finished wizard, a reset rate limit, a re-opened replay window). The
call site says which by the registry it asks; both return a `KeyValueStore`.

|                | Cache                                    | State                                                                                     |
| -------------- | ---------------------------------------- | ----------------------------------------------------------------------------------------- |
| Read with      | `CacheRegistry::store()`                 | `StateRegistry::store()`                                                                  |
| Declared in    | `oz.stores.cache`                        | `oz.stores.state`                                                                         |
| Driver must be | anything                                 | `StoreCapabilities::$durable`                                                             |
| Shipped        | none (image filter renditions are files) | `oz:form:sessions`, `oz:form:resume`, `oz:rate_limit`, `oz:auth:digest:nonces`, `oz:cron` |

- The boundary is enforced both ways: `StateRegistry` refuses an undeclared store and a non-durable
  driver; `CacheRegistry::store()` throws on a state store name (instead of quietly handing out a
  second provider).
- **`durable` is not `persistent`**: `persistent` is "survives the process", `durable` is "losing it
  is not allowed", which depends on where the instance writes. `DbStore` and `RedisStore` are durable;
  `MemoryStore` and `MemcachedStore` never; `FileStore` only with `options: {'root' => FileStore::ROOT_STATE}`
  (files in `data/state/{scope}`, not `.ozone/cache/`).
- Obtain stores from the registries, never by constructing a driver: `store(name)`, or
  `CacheRegistry::runtime(__METHOD__)` (per-request memoization) and `persistent(self::class)`.
- Expiry listeners (`StoreEntryExpiryListenerInterface`, the `expiry_listener` of a store) are called
  by `StoresGarbageCollector`, which scans both groups on drivers with `expiryCallbacks` (`DbStore`) and
  deletes each entry even if its listener throws.

---

## 18. REST

`REST\RESTFulService` turns a service into a CRUD controller for a table. Its actions are the cases of
`REST\Enums\RESTFulAction` (`get_one`, `get_all`, `get_relation`, `update_one`, `update_all`,
`delete_one`, `delete_all`, `create_one`), which drive both the routes and the docs
(`REST\RESTFulApiDoc`; table meta `api.doc.<action>.enabled` turns one doc off).

- Customize an action with `beforeAction()` / `afterAction()` (success only) and
  `static configureRoute()` (once per action at registration), never by re-registering routes.
- **Disable an action by redeclaring `protected static array $available_actions`** in the subclass
  (changing the inherited array would change every service): no route, no docs.
- `KEY_COLUMN` is written as the table names it, short (`id`) or full (`article_id`): it is resolved
  through the table wherever a column is needed, and it also names the route parameter
  (`/articles/:id`).
- Pagination: offset (`max`, `page`) or cursor (`cursor`, `cursor_column`, `cursor_dir`); any cursor
  parameter switches to cursor mode, and mixing it with `page` is an error. Private relations throw
  `ForbiddenException`.
- **A paginated (to-many) relation is not loaded with `relations`**: asking for one answers
  `OZ_RELATION_IS_PAGINATED_AND_SHOULD_BE_RETRIEVED_WITH_DEDICATED_ENDPOINT`. It is read through
  `get_relation` (`{path}/{id}/{relation}`). What `relations` carries is keyed by relation name, then
  by the id of the entity holding it.
- Of the CRUD actions, **`delete_all` alone is refused by default** (`CRUD::assertDeleteAll()`
  authorizes with `false`): a project allows it with a CRUD listener.
- OpenAPI: implement `ApiDocProviderInterface::apiDoc(ApiDoc $doc)`. `ApiDoc` is a facade over the
  `REST\ApiDoc\` builders (`schemas()`, `gobl()`, `parameters()`, `responses()`, `operations()`); its
  own methods delegate to them and stay. Served at `/api-doc-spec.json` and `/api-doc-view.html`
  (`oz.api.doc`).
- **Every public route is documented**: a route the API router serves has an operation in the
  OpenAPI spec (its provider implements `apiDoc()`), a web-only route a page on the OZone
  documentation site (`docs/`). Internal routes (`OZone::INTERNAL_PATH_PREFIX`, sub-requests only)
  are exempt.

---

## 19. CLI and Tooling

`bin/oz` (or `bin/ozone`) runs `oz/index.php`, which loads the project when run inside one. Commands
extend `OZONE\Core\Cli\Command` (`silassare/kli`) and start with `Utils::assertProjectLoaded()` when
they need a project. Each command documents its options (`oz <cmd> --help`): `project`
(`create`, `serve`, `link`, `backup`, `build`), `scopes`, `db`, `migrations`, `services`, `settings`,
`users`, `doctor`, `server`, `deploy`, `cron`, `jobs`.

- **`oz project build`** compiles for production what requests would compile at first use: per
  scope, in a process set up as the scope's `index.php` (`Cli\Build\ScopeBuilder`), the compiled
  `.env`, the settings bundles and the route tables; then the class map (`ClassLoader::useClassMap()`)
  and, in classic mode, a preload list (`--no-preload`; Docker: `OZ_PRELOAD=0`): every class of OZone
  and of its first-party packages (`silassare/*`, `psr/*`) that preloading can compile, plus what
  booting and building the routers declared. The list never holds the command line (`Cli\`, Kli),
  tests, the generated ORM classes (their first use initializes the database, through a lazy
  `ClassLoader` namespace), Composer's files, or what extends them. Outside production it only generates the ORM
  classes. **Everything is named after the release**, since `.ozone/` is shared by the releases of an
  `oz deploy` root: `opcache.preload` points to `.ozone/preload.php`, the same script for every
  release, which preloads, when PHP starts, the list of the release `current` points to (of the
  project, outside a deploy root) -- never a release that failed before going live. Preloaded code
  only changes when PHP restarts.

- **`install`** (repository root) puts `oz` / `ozone` on `PATH` and nothing else: it only _checks_ PHP,
  the extensions, Composer and git unless `--with-php`. Preparing a server is `oz server provision`.
- **`oz project create`** generates a `composer.json` requiring `silassare/ozone`; every package in its
  graph is on Packagist, so it needs no `repositories` and no credentials.
- **JSON mode** (`--json`, for tools such as the O'Web Builder): `Cli::run()` sees the flag before bootstrap,
  so every output goes through `Cli`: `write()` / `writeLn()` print nothing, `info()` / `warn()` /
  `success()` / `error()` are collected as `messages`, and one with an exit code ends the command in JSON.
  A command answers with `$cli->writeJson([...], $ok, $exit, $msg)`, which writes the envelope of every
  OZone answer (`JSONResponse`: `{error, msg, data, utime}`, the result under `data`, the reported
  messages under `data.messages`). Never `echo` in a command, and a new command a tool drives gets
  `--json` through `withJsonSupport()`.
- **`oz users grant`** gives a role to an existing user (`Roles::assign()`, restoring a revoked one): the
  way to make the first super admin, never a web installer. `oz doctor check` warns while an installed
  project has none.
- **`oz doctor check [--json]`** reports what the machine and project miss and exits non-zero on a
  failure, including a schema that failed to load at bootstrap. In production it warns (never fails)
  when this release has no class map or one the classes no longer match, and when its preload list
  names files changed or gone since the build.
- **`oz server provision`**: `Cli\Server\Provisioner::plan()` is a pure function of the options and the
  detected `PackageManager` (so `--dry-run` is honest and every distribution is asserted from one
  machine), and everything that reads or runs on the target goes through `Cli\Server\Interfaces\HostInterface`:
  `LocalHost` (this machine) or `SshHost` (`--host=user@host[:port]`, `--identity`, on `oz server provision|status`
  and `oz deploy run|rollback|releases`). Detection, the `ProvisionStep` checks (`fn (HostInterface $host)`), the
  manifest, the root check and `ReleaseLayout` all take the host: **never read the target with PHP's file
  functions**, or `--host` acts on the wrong machine. `SshHost` uses the system client (agent, one reused
  connection, host keys accepted on first sight), plain POSIX shell (BusyBox answers too) and standard input
  for files; `OZ_SSH_COMMAND` replaces the client, as `GIT_SSH_COMMAND` does. `oz deploy run --host` copies a
  local archive or bundle file to `{root}/uploads/` first.
  `/etc/ozone/provision.json` records each applied step: a second run is a diff, and nothing the
  manifest does not claim is touched. **The firewall step allows SSH before it denies anything, and
  it is the last step.** `phpExtensionPackage()` returns null for extensions built into PHP. Debian
  (apt) and Alpine (apk) are verified against real containers (`isVerified()`); dnf and pacman are
  written from their documented package splits and warn; Homebrew is refused as a server target.
- **`oz deploy`**: `init` is a pure plan of `DeployFile`s (`--dry-run` lists them) and never overwrites
  without `--force`; `--target=docker` copies `conf/docker/`, `--target=bare` generates nginx vhosts
  (each scope's `server_name` read from its own `oz.request.php`), PHP-FPM pools, systemd and
  logrotate files, and a PHP-FPM `conf.d` file turning preloading on (`--no-preload`): read once by
  the PHP-FPM master for every pool, so one project per PHP-FPM server can preload. Cron is a systemd
  timer running `oz cron run` (`--cron=timer`, the default) or `oz cron work` as a service
  (`--cron=daemon`); the Docker sample runs `oz cron work` in its `cron` container. `Cli\Deploy\DeployTemplates` renders `oz_templates/gen/deploy/` by `__TOKEN__`
  substitution, not Blate (nginx and GitHub `${{ }}` braces collide with it), and **throws on a token
  it did not replace**. `run` is an atomic release from one source -- a git repository, URL, path or bundle file, at a branch, tag or
  full commit hash (fetched, then checked out: `git clone --branch` takes no commit), or a tar archive
  (`--archive`) -- whose `prepare`, `checkout`, `link-shared`,
  `dependencies`, `link-public`, `orm`, `migrations` and `build` all run **before** `go-live` (a temporary
  symlink plus `mv -T`), so a failure leaves the live release serving; a failed health check moves
  `current` back; `prune` never removes what `current` resolves to. `.env`, `data/` and `.ozone/` live
  in the deploy root and are linked into each release. A rollback does **not** undo a migration.

---

## 20. Testing

PHPUnit 9, run **in Docker through `make`** (`docker/compose.yaml`: PHP with OZone's extensions and
tools — including `mysqldump`, `pg_dump` and `ext-pcntl`, which the CLI shells out to or needs —
plus MySQL, PostgreSQL, Redis, MinIO, ClamAV and the worker servers). The host needs only Docker and
`make`. Each target is described by its `##` comment in the `Makefile`: `test` (every suite),
`test-unit`, `test-services`, `test-runtimes`, `test-provision`, `test-integration`, `ci` (what CI
runs), `lint`, `cs`, `fix`, `shell`, `down`, ...

- **Never create a `phpunit.xml`**: PHPUnit prefers it over `phpunit.xml.dist`, it is git-ignored, and
  it would silently decide which servers the suites see. The targets pass `-c phpunit.xml.dist`;
  server addresses come from the environment compose exports.
- Groups needing a server are excluded by default and **fail instead of skipping** when their target
  runs them without the server: `redis`, `minio`, `clamav` (`make test-services`), `frankenphp`,
  `roadrunner`, `swoole` (`make test-runtimes`, which recreates the server containers so they run the
  current code), `provision` (`make test-provision`, on the host: it drives Docker itself, and the system
  `ssh` client for the sshd containers of `ServerOverSshTest`).
- **The test kit is part of OZone** (`oz/Testing/`, `OZONE\Core\Testing`), so a plugin or an app tests
  the way OZone does: `Sandbox`, `SandboxApp`, `OZTestProject`, `DbTestConfig`, `ServiceEnv`,
  `IntegrationTestCase` and the `Requires*Trait`s. It is never preloaded (`ScopeBuilder`), and only
  `IntegrationTestCase` and the traits need PHPUnit. It finds the running Composer autoloader and root
  package through Composer's runtime API (`ClassLoader`, `InstalledVersions`), since OZone may be the
  root package or a dependency.
- **Sandbox**: `tests/autoload.php` (the one bootstrap of both suites) creates a throwaway project
  (`Sandbox::create()`: `.env` with fresh keys, `data/`, the ORM classes of every enabled namespace
  and the SQLite schema, built by `oz/Testing/sandbox_build.php` in a separate process) and
  bootstraps `SandboxApp` on it (`Sandbox::bootstrap()`), so tests never touch the repository's
  `data/` or `.ozone/`. **Pass the same settings sources to both** (the suite's `tests/settings/`):
  the build and the suite must see one schema. The worker servers serve the same kind of sandbox
  (`tests/Support/Servers/`).
- Unit tests live under `tests/` by namespace (`OZONE\Tests`), extend `TestCase`, and declare
  `@covers` (the fixer adds `@coversNothing` otherwise). `TestUtils::router()` is a pre-populated router.
- `make lint` regenerates OZone's ORM classes in the git-ignored `.ozone/plugins/` first
  (`tests/orm_build.php`), which psalm scans for types without analysing.

### Integration tests (`tests/Integration/`)

They run `bin/oz` subprocesses in real projects (`OZTestProject`, in `/tmp/_oz_tests_/projects/`), on
SQLite, MySQL **and** PostgreSQL: `DbTestConfig::allConfigured('<class tag>')` throws when MySQL or
PostgreSQL is not configured (`OZ_TEST_ALLOW_PARTIAL=1` to run on what is). The tag keeps each class's
SQLite file apart.

- `OZTestProject::create($name, $deps, $shared, $fresh, $repositories)` pins every package of the
  running repository's graph (OZone's, or the plugin's) to the version its `composer.lock` records (a
  branch to its commit; a package the lock took from a path repository is resolved from that directory
  again), resolves `silassare/ozone` from the repository through a path repository when OZone is the
  root package, adds `$repositories` as path repositories, and caches `vendor/` by a hash of that
  dependency set, so `composer install` runs only when it changes. A project directory reused from
  an earlier run (or left by a killed one) is relinked to the current set's `vendor/`: after a
  `composer update` in OZone, no test project runs on the dependencies it was created with. Inject DB and other
  values with `writeEnv()`, settings with `setSetting()`, stubs with `writeFileFromStub()` (from the
  directory `useStubsDir()` set, `tests/Integration/Stubs/` here), run commands with `oz(...)`, and
  `destroy()` in `tearDownAfterClass()`.
- **Never combine `@depends` with `@dataProvider`** (PHPUnit 9 cannot pass values across data sets):
  every method takes the provider, and projects are shared through a `private static array $projects`
  keyed by RDBMS, created by the first method in declaration order.
- **Setup order**: `oz db build --build-all --class-only`, then **`$proj->cleanDb()`**, then the first
  migration. Every class shares one database on MySQL and PostgreSQL, and MySQL scopes foreign key
  constraint names to the schema, so a class that skips `cleanDb()` passes alone and fails in a full
  run. Table names are already apart (each project gets a random `OZ_DB_TABLE_PREFIX`).
- One focused class per feature: `OZONE\Tests\Integration\{Feature}\{Class}Test`.

---

## 21. Conventions

- Layout: `OZONE\Core\{Module}\{Class}` -> `oz/{Module}/{Class}.php`, with `Interfaces\`, `Events\`,
  `Traits\`, `Enums\` sub-namespaces. Classes split out of a large one are grouped in one
  sub-namespace, and the original keeps its methods as delegates.
- Global helpers: `app()`, `db()`, `env()`, `oz_logger()`, `oz_trace()`.
- HTTP errors extend `Exceptions\BaseException` (`BadRequestException` 400, `InvalidFormException`
  400, `UnauthorizedException` 401, `ForbiddenException` 403, `NotFoundException` 404,
  `MethodNotAllowedException` 405); throw them from guards, middlewares and handlers.
- `UserEntityTrait` is applied to tables `UsersRepository::isTableSupported()` recognizes, and
  `FileEntityTrait` to `oz_files`, when the ORM classes are generated.

---

## 22. Dependencies

PHP 8.1+; MySQL by default (SQLite and PostgreSQL supported). Required extensions are in
`composer.json` (`oz doctor check` checks them). Packages: `silassare/gobl` (ORM, DBAL, code generation),
`silassare/kli` (CLI), `silassare/blate` (templates), `silassare/php-utils` (`Event`, `Store`,
`PathUtils`, ...), `claviska/simpleimage`, `symfony/process`, `zircote/swagger-php`, `psr/http-message`,
`psr/log`.

The four `silassare/*` packages are first-party: **read their instructions before using them** —
`vendor/silassare/{gobl,kli,php-utils,blate}/.github/copilot-instructions.md` — and never guess at
their APIs.

---

## 23. Benchmarks

`tests/Benchmark.php` is a fluent harness; `tests/run_benchmarks.php` (`make benchmark`) compares each
run with `tests/benchmark-baseline.json` and prints only regressions or improvements.

`make benchmark-http` (`docker/bench/`, on the host) measures HTTP throughput with wrk: OZone, Laravel
and Symfony served identically (FrankenPHP, PHP 8.4, OPcache, production settings), once per request
and in worker mode, each server alone on CPUs 0-3 and wrk on 4-7. OZone is served as deployed: a
copy installed with `--no-dev`, its schema installed through a migration (`OZ_TEST_SERVER_INSTALLED`).
Numbers are only comparable within one run on one machine. Add an entry
when you introduce or change a hot path — routing, per-request validators or column types, cache
access, crypto helpers, URI / HTTP message handling, anything O(n) per request or per row — labelled
`area_operation[_variant]` (`router_find_static`, `hasher_hash64`).
