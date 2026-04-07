# OZone Framework — Developer Copilot Instructions

> All paths are relative to the repository root. Namespace root: `OZONE\Core` -> `oz/`.

---

## IMPORTANT

- **No hallucination or invention.** Read the actual source files before generating code, docs, or this file. Focus only on what can be directly observed in the codebase.
- **When a bug or issue is found, do not fix it directly** — ask for feedback and approval first.
- No too verbose comment blocks on obvious code. Keep comments concise and focused on non-obvious insights or rationale.
- **Symlinks:** If `AGENTS.md`, `CLAUDE.md`, or `GEMINI.md` do not exist, symlink them to `.github/copilot-instructions.md`.
- **Direct dependencies only.** Only use packages listed in `require` or `require-dev` of `composer.json`. Do not rely on transitive dependencies — they are not guaranteed to be present and can change without notice.
- **Strict types.** Every PHP file starts with `declare(strict_types=1);`.
- **Indentation — IMPORTANT.** Use **tabs** (never spaces) for all PHP indentation. This applies to every generated or edited PHP file without exception.
- **"oz" casing.** When uppercasing "oz", always use `OZ`, `OZone`, or `ozone` — never `Oz` or `Ozone`.
- **Assertions.** Use snapshot assertions where they best convey expected output; use `assertEquals()` / `assertSame()` with inline values for simple or small structures. Never use third-party snapshot packages — only project-owned helpers.
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

## 1. Framework Overview & Architecture

OZone is a PHP 8.1+ service-oriented REST API and web framework. Its core components are:

| Layer         | Location                                 | Purpose                                                           |
| ------------- | ---------------------------------------- | ----------------------------------------------------------------- |
| Bootstrap     | `oz/OZone.php`                           | Static facade — entry point, router creation, installation checks |
| App contract  | `oz/App/AbstractApp.php`                 | Directory resolution, settings/templates sourcing                 |
| Request cycle | `oz/App/Context.php`                     | Per-request container — auth state, request, response, route info |
| Routing       | `oz/Router/`                             | HTTP router with guards, middlewares, rate limiting, forms        |
| Services      | `oz/App/Service.php`, `oz/Services/`     | Base controller class + built-in services                         |
| Auth          | `oz/Auth/`                               | Authentication methods, providers, services, events               |
| ORM/DB        | `oz/App/Db.php`, `oz/Columns/`           | Gobl ORM integration, custom column types                         |
| Forms         | `oz/Forms/`                              | Validation engine for route inputs                                |
| CRUD          | `oz/CRUD/`                               | Access-controlled Gobl ORM event listeners                        |
| Settings      | `oz/App/Settings.php`, `oz/oz_settings/` | Layered PHP-file config system                                    |
| Hooks/Events  | `oz/Hooks/`                              | Lifecycle events (boot, request, response, finish, DB)            |
| Plugins       | `oz/Plugins/`                            | Plugin system with scoped source/settings/data directories        |
| Migrations    | `oz/Migrations/`                         | Schema versioning with diff-based migration generation            |
| REST          | `oz/REST/`                               | RESTful CRUD trait, OpenAPI doc generation                        |
| CLI           | `oz/Cli/`                                | Command-line tools built on `silassare/kli`                       |
| Sessions      | `oz/Sessions/Session.php`                | Cookie-based session management tied to `OZSession` DB entity     |

### Request Lifecycle

```
OZone::run($app)
  +-- bootstrap($app)              # registers error handlers, boots plugins, notifies BootHookReceivers,
  |                                # registers CRUD listeners, dispatches InitHook
  +-- Context::handle()
  |   +-- RequestHook::dispatch()
  |   +-- Router::handle()
  |   |   +-- Route matching (static/dynamic, priority-ordered)
  |   |   +-- RouteInfo construction
  |   |   |   +-- $authenticator($routeInfo)
  |   |   |   +-- callGuards()
  |   |   |   +-- runMiddlewares()
  |   |   |   +-- selectInterceptor()   # first interceptor where shouldIntercept() == true
  |   |   |   +-- checkRouteForm()      # skipped when an interceptor is active
  |   |   +-- RouteBeforeRun::dispatch() -> route handler callable
  |   +-- exceptions -> BaseException::tryConvert()->informClient()
  +-- Context::respond()
      +-- ResponseHook::dispatch()  # CORS headers added here
      +-- send response
      +-- FinishHook::dispatch()    # GC: sessions, auth tokens
```

---

## 2. Bootstrapping & Lifecycle

**Entry points:**

- **Web**: `OZone::run(AppInterface $app): never` in a scope's `index.php`
- **CLI**: `oz/index.php` -> `Cli::run($argv)`

**App class**: Extend `AbstractApp` (or implement `AppInterface`). Place in `app/app.php` relative to project root. The CLI auto-discovers this file via `Utils::isProjectFolder()`.

**Boot hook receivers** (`oz.boot` settings group): Classes implementing `BootHookReceiverInterface::boot()`. Registered in `oz/oz_settings/oz.boot.php`. Their `boot()` is called early — **the database and context are NOT yet available** at this point. Use `boot()` only to register event listeners.

```php
// oz/oz_settings/oz.boot.php
return [
    MyBootHookReceiver::class => true,
    Session::class            => true,
    Auth::class               => true,
];
```

**`InitHook`** fires at the end of `OZone::bootstrap()` — database IS available at this point.

**Key constants (set in `oz_default/oz_bootstrap.php`)**:

- `OZ_APP_DIR` — app source directory
- `OZ_PROJECT_DIR` — project root
- `OZ_OZONE_DIR` — framework source root (`oz/`)
- `OZ_OZONE_IS_CLI` — indicates CLI mode

**App directories** (resolved by `AbstractApp`):

- `getSettingsDir()` — `{app}/settings/`
- `getTemplatesDir()` — `{app}/templates/`
- `getSourcesDir()` — `{app}/` (PSR-4 source root)
- `getMigrationsDir()` — `{project}/migrations/`
- `getCacheDir()` — `{project}/.cache/`
- `getPublicDir()` — `{project}/public/`
- `getDataDir()` — `{project}/data/`

---

## 3. Settings System

**Class**: `OZONE\Core\App\Settings` (final, static methods only)

Settings are layered PHP files returning arrays. The framework loads from `oz/oz_settings/` first, then app-level settings override — the last source wins per key.

**Setting group name format**: `foo.bar.baz` (maps to `foo.bar.baz.php` in the settings dir, dots are literal). Slashes create subdirectories: `foo/bar.baz` maps to `foo/bar.baz.php`.

**Core API**:

```php
Settings::get('oz.config', 'OZ_PROJECT_NAME');              // read one key, optional default
Settings::load('oz.db');                                     // load entire group as array
Settings::set('oz.auth', 'OZ_AUTH_CODE_LENGTH', 8);              // stateful (data/settings/) — default
Settings::set('oz.routes.api', MyService::class, true, null, false); // source (app/settings/) — $stateful=false
Settings::unset('oz.auth', 'OZ_AUTH_CODE_LENGTH');               // stateful — default
Settings::unset('oz.auth', 'OZ_AUTH_CODE_LENGTH', null, false);  // source — $stateful=false
Settings::has('oz.db', 'OZ_DB_HOST');                       // check existence
Settings::addSource(string $path);                          // add settings directory source
Settings::applyMergeStrategy(array $a, array $b): array;    // merge strategy: indexed -> array_merge, assoc -> array_replace_recursive
```

**`Settings::set/unset`** accept an optional `bool $stateful = true` parameter:

- `$stateful = true` (default) — writes to the stateful directory (`data/settings/`, `data/scopes/{name}/settings/`, `data/plugins/{name}/settings/`). Use for runtime overrides that must not touch version-controlled files.
- `$stateful = false` — writes to the source directory (`app/settings/`, `scopes/{name}/settings/`). Use for dev-time scaffolding (e.g. `oz services generate`) where the change should be committed. The `--source` flag on `oz settings set/unset` exposes this option in the CLI.

Source settings files (`app/settings/`, `scopes/{name}/settings/`) are the version-controlled defaults, edited manually or via dev-time tooling.

**`oz.config` is blacklisted** — runtime edits via `Settings::set/unset` are disallowed for it.

**`SettingsGroup`** (`OZONE\Core\App\SettingsGroup`, `@internal`) — the per-group backing `Store<array>` used internally by `Settings`. Its `merge()` calls `Settings::applyMergeStrategy()` before delegating to the parent store. Do not use it directly.

**All built-in settings groups** (in `oz/oz_settings/`):

| File                         | Key settings                                                                                                                                                                                             |
| ---------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `oz.config`                  | `OZ_PROJECT_NAME`, `OZ_PROJECT_NAMESPACE`, `OZ_PROJECT_APP_CLASS_NAME`, `OZ_PROJECT_PREFIX`                                                                                                              |
| `oz.db`                      | `OZ_DB_RDBMS`, `OZ_DB_HOST`, `OZ_DB_PORT`, `OZ_DB_NAME`, `OZ_DB_USER`, `OZ_DB_PASS`, `OZ_DB_TABLE_PREFIX`, `OZ_DB_CHARSET`, `OZ_DB_COLLATE`                                                              |
| `oz.db.schema`               | Project table definitions (Gobl schema array)                                                                                                                                                            |
| `oz.db.migrations`           | `OZ_MIGRATION_VERSION`                                                                                                                                                                                   |
| `oz.db.columns.types`        | `TypeName::NAME => TypeName::class` map                                                                                                                                                                  |
| `oz.boot`                    | Boot hook receiver class map                                                                                                                                                                             |
| `oz.plugins`                 | Plugin class map                                                                                                                                                                                         |
| `oz.routes`                  | Shared route provider class map (both API + web)                                                                                                                                                         |
| `oz.routes.api`              | API router route provider class map                                                                                                                                                                      |
| `oz.routes.web`              | Web router route provider class map                                                                                                                                                                      |
| `oz.auth`                    | Auth code config, API key header name, auth methods for API/web                                                                                                                                          |
| `oz.auth.users.repositories` | User type -> `AuthUsersRepositoryInterface` class map                                                                                                                                                    |
| `oz.auth.providers`          | Provider name -> `AuthorizationProviderInterface` class map                                                                                                                                              |
| `oz.auth.methods`            | Scheme -> method class map                                                                                                                                                                               |
| `oz.middlewares`             | Named middleware registry                                                                                                                                                                                |
| `oz.guards`                  | Named guard registry                                                                                                                                                                                     |
| `oz.guards.providers`        | Guard provider class map                                                                                                                                                                                 |
| `oz.gobl.crud`               | CRUD listener class map                                                                                                                                                                                  |
| `oz.gobl.collections`        | Entity collection class map                                                                                                                                                                              |
| `oz.request`                 | CORS settings, `OZ_DEFAULT_ORIGIN`, `OZ_REAL_METHOD_HEADER_ALLOWED`, `OZ_FORM_DISCOVERY_HEADER_ALLOWED`, `OZ_FORM_DISCOVERY_HEADER_NAME`, `OZ_FORM_RESUME_HEADER_NAME`, `OZ_FORM_RESUME_REF_HEADER_NAME` |
| `oz.sessions`                | `OZ_SESSION_LIFE_TIME`, `OZ_SESSION_COOKIE_NAME`                                                                                                                                                         |
| `oz.cookie`                  | `OZ_COOKIE_DOMAIN`, `OZ_COOKIE_PATH`, `OZ_COOKIE_LIFETIME`, `OZ_COOKIE_SAMESITE`, `OZ_COOKIE_PARTITIONED`                                                                                                |
| `oz.users`                   | Age range, password/name lengths, gender list, email/phone requirements                                                                                                                                  |
| `oz.paths`                   | Service URL path settings (QR code, captcha, link-to routes)                                                                                                                                             |
| `oz.api.doc`                 | `OZ_API_DOC_ENABLED`, `OZ_API_DOC_SHOW_ON_INDEX`                                                                                                                                                         |
| `oz.lang`                    | i18n source files                                                                                                                                                                                        |
| `oz.cache`                   | `OZ_CACHE_DEFAULT_RUNTIME`, `OZ_CACHE_DEFAULT_PERSISTENT`, `OZ_CLEAR_SITE_DATA_HEADER_ON_LOGOUT`, `OZ_CLEAR_SITE_DATA_HEADER_VALUE`                                                                      |
| `oz.cache.stores`            | Named store definitions: store-name -> `{driver, options, expiry_listener}` map                                                                                                                          |
| `oz.logs`                    | `OZ_LOG_WRITER`, `OZ_LOG_MAX_FILE_SIZE`, `OZ_LOG_EXECUTION_TIME_ENABLED`                                                                                                                                 |
| `oz.files`                   | File URI path format with placeholders (`oz_file_id`, `oz_file_auth_key`, etc.)                                                                                                                          |
| `oz.files.storages`          | Storage driver map: `FS::DEFAULT_STORAGE`, `FS::PUBLIC_STORAGE`, `FS::PRIVATE_STORAGE`                                                                                                                   |
| `oz.senders`                 | Sender class map: `sms`, `mail`, `notification`                                                                                                                                                          |
| `oz.roles`                   | `OZ_ROLE_ENUM_CLASS` -> roles enum class                                                                                                                                                                 |
| `oz.proxies`                 | Trusted proxy configuration                                                                                                                                                                              |
| `oz.redis`                   | `OZ_REDIS_ENABLED`, `OZ_REDIS_HOST`, `OZ_REDIS_PORT`, `OZ_REDIS_PASSWORD`, `OZ_REDIS_DATABASE`, `OZ_REDIS_PREFIX`, `OZ_REDIS_TIMEOUT`, `OZ_REDIS_PERSISTENT`                                             |

---

## 4. Routing

**Router class**: `OZONE\Core\Router\Router` (final)

### Registering Routes

Routes are registered by classes implementing `RouteProviderInterface::registerRoutes(Router $router)`. Enable them in the settings groups `oz.routes`, `oz.routes.api`, or `oz.routes.web`.

```php
// oz_settings/oz.routes.api.php
return [
    MyService::class => true,
];

// In MyService:
use OZONE\Core\App\Service;
use OZONE\Core\Router\Router;
use OZONE\Core\Router\RouteInfo;

class MyService extends Service
{
    public static function registerRoutes(Router $router): void
    {
        $router->get('/items', static function (RouteInfo $ri) {
            $s = new self($ri);
            // ... populate $s->json()
            return $s->respond();
        })->name('my:items.list');

        $router->post('/items', static function (RouteInfo $ri) {
            $s = new self($ri);
            return $s->respond();
        })->name('my:items.create')
          ->form(new MyCreateForm());
    }
}
```

### Route Configuration (Fluent API on `RouteOptions` / `RouteSharedOptions`)

```php
$router->get('/path', $handler)
    ->name('route:name')                                          // dot-notation name
    ->withAuthentication(AuthenticationMethodScheme::SESSION)     // allowed auth scheme(s)
    ->withAuthenticatedUser('admin')                              // guard: requires logged-in user (type)
    ->withRole(MyRole::instance())                                // guard: requires role
    ->withRoleOrAdmin(MyRole::instance())                         // guard: role OR admin/super-admin
    ->withAdminRole()                                             // guard: admin or super-admin role
    ->withSuperAdminRole()                                        // guard: super-admin role only
    ->withAccessRights('resource.action')                         // guard: requires access right
    ->withAccessRightsOrRoles(['resource.action'], [MyRole::instance()]) // guard: right OR role
    ->withAuthorization('auth:provider:name')                     // guard: OZAuth-based
    ->middleware(MyMiddleware::class)                              // middleware by class FQN
    ->guard(MyGuard::class)                                       // guard by class FQN
    ->interceptor(MyInterceptor::class)                           // route interceptor by class FQN
    ->form(new MyForm())                                          // attach form instance (or callable) for validation
    ->param('id', '[0-9]+')                                       // path param constraint
    ->priority(10)                                                // higher = matched first
    ->rateLimit(new IPRateLimit($ri, 60, 3600));                  // rate limiting
```

### Route Groups

```php
$router->group('/api/v1', function (RouteGroup $group) {
    $group->withAuthentication(AuthenticationMethodScheme::BEARER)
          ->withAuthenticatedUser();

    $group->get('/users', ...)->name('users.list');
    $group->post('/users', ...)->name('users.create');
});
```

### Explicit `$parent` on `map()`

`Router::map()` accepts an optional fourth argument `?RouteSharedOptions $parent`. When provided it is used as the route parent instead of the current open group:

```php
// Register a base route and keep its RouteOptions.
$parent = $router->get('/items', $handler)->name('items');

// Register a child route that inherits path, name, guards, and middlewares from $parent.
$child = $router->map(['get'], '/:id', $handler, $parent)->name('get_by_id');
// full path  => /items/:id
// full name  => items.get_by_id
```

This allows any `RouteOptions` (not just a `RouteGroup`) to act as a parent, which is useful when building sibling routes that share configuration without an explicit group block.

### Route Parameters

Dynamic segments: `/users/:id` where `id` captures by default `[^/]+`. Constrain with `.param('id', '[0-9]+')`. Access in handler via `$ri->param('id')`.

Named routes support URI building: `$context->buildRouteUri('route:name', ['id' => 42])`.

### Route Search Statuses

- `RouteSearchStatus::FOUND` — match found, handler executes
- `RouteSearchStatus::NOT_FOUND` — triggers `RouteNotFound` event -> default: throws `NotFoundException`
- `RouteSearchStatus::METHOD_NOT_ALLOWED` — triggers `RouteMethodNotAllowed` event -> default: throws `MethodNotAllowedException` (OPTIONS preflight passes silently)

### Route Interceptors

**Interface**: `OZONE\Core\Router\Interfaces\RouteInterceptorInterface`
**Built-in**: `OZONE\Core\Router\RouteFormDiscoveryInterceptor`

Interceptors short-circuit the normal route handler. They are evaluated after guards and middlewares. The first interceptor (sorted by priority descending) whose `shouldIntercept()` returns `true` wins; its `handle()` replaces the route handler. When an interceptor is active, form validation is **skipped** and `getCleanFormData()` returns an empty `FormData`.

`RouteFormDiscoveryInterceptor` is always present on every route (injected by `getInterceptors()`) and activates when `Request::isFormDiscoveryRequest()` returns `true`.

```php
interface RouteInterceptorInterface
{
    public static function getName(): string;   // unique name, used as map key
    public static function getPriority(): int;  // higher = runs first (default interceptor = 0)
    public function shouldIntercept(): bool;
    public function handle(): Response;
    public static function instance(RouteInfo $ri): static;
}
```

```php
// Register on a route or group:
$router->get('/items', $handler)->interceptor(MyInterceptor::class);

// In RouteInfo:
$ri->isIntercepted();              // bool
$ri->getInterceptor();             // ?RouteInterceptorInterface
$ri->getEffectiveHandler();        // interceptor->handle(...) when intercepted, route handler otherwise
```

**`Request::isFormDiscoveryRequest(): bool`** — returns `true` when `OZ_FORM_DISCOVERY_HEADER_ALLOWED` is enabled (default) and the request carries the `X-OZONE-Form-Discovery: ?1` header (RFC 8941 bool).

**`Request::isFormResumeRequest(): bool`** — returns `true` when the request carries the `X-OZONE-Form-Resume: ?1` header.

Both methods live on `Request`, not `Context`. Access via `$context->getRequest()->isFormDiscoveryRequest()`.

---

## 5. Services (Controllers)

**Base class**: `OZONE\Core\App\Service` (abstract)

Services implement `RouteProviderInterface` and `ApiDocProviderInterface`. The constructor accepts `Context|RouteInfo`.

```php
class MyService extends Service
{
    public static function registerRoutes(Router $router): void
    {
        $router->get('/items/:id', static function (RouteInfo $ri) {
            $s = new self($ri);
            $s->actionGetItem();
            return $s->respond();
        });
    }

    private function actionGetItem(): void
    {
        $id = $this->ri->param('id');          // access route params via $this->ri
        // ...
        $this->json()->setData(['item' => ...]);
    }
}
```

`$s->respond()` builds a `Response` from `JSONResponse`, automatically adding:

- `utime` — current Unix timestamp
- `stime` — session expiry time (when applicable)
- All data set via `$this->json()->setData(...)`

**WebView**: extend `OZONE\Core\Web\WebView` for HTML responses; call `$v->setTemplate('oz://path/to.blate')->inject([...])`. The context is automatically available inside templates via `BlatePlugin::CONTEXT_INJECT_KEY`.

### BlatePlugin (`OZONE\Core\Web\BlatePlugin`) — internal

A `BootHookReceiverInterface` that wires OZone into the Blate template engine. Registered in `oz.boot`. Marked `@internal` — do not reference it directly from application or plugin code.

**Two distinct responsibilities:**

1. **`BlatePlugin::register()`** — called from `.blate.php` at project root (picked up by `Blate::autoLoad()`). Registers all helpers and global vars so they are available in every template:

   | Type            | Blate name        | Delegates to                      |
   | --------------- | ----------------- | --------------------------------- |
   | helper          | `setting`         | `Settings::get()`                 |
   | helper          | `env`             | `env()`                           |
   | helper          | `log`             | `oz_logger()`                     |
   | helper          | `t`               | `BlatePlugin::t()` -> `I18n::t()` |
   | helper          | `uri`             | `Context::buildUri()`             |
   | helper          | `route`           | `Context::buildRouteUri()`        |
   | computed global | `request_uri`     | current request `Uri`             |
   | computed global | `base_url`        | `Context::getBaseUrl()`           |
   | computed global | `lang`            | `Polyglot::getLanguage()`         |
   | global var      | `oz_version`      | `OZ_OZONE_VERSION`                |
   | global var      | `oz_version_name` | `OZ_OZONE_VERSION_NAME`           |

2. **`BlatePlugin::boot()`** — called via the boot hook. Runs `Blate::autoLoad()` for `OZ_OZONE_DIR` and `OZ_PROJECT_DIR`, and sets the Blate cache directory.

**Context access in templates:** `BlatePlugin::getContext()` reads the context injected by `WebView::render()` from the Blate scope (`CONTEXT_INJECT_KEY = '__oz_web_blate_inject_context'`), falling back to the global `context()` helper if not set.

**`.blate.php`** — Blate auto-config file at project root. Must call `BlatePlugin::register()` so all helpers and global vars are available. Generated once per project.

```php
// .blate.php (project root)
use OZONE\Core\Web\BlatePlugin;

BlatePlugin::register();
```

### Assets (`OZONE\Core\FS\Assets`) — `oz://` protocol registry

`Assets` is a `BootHookReceiverInterface` (registered in `oz.boot`) that owns the `oz://` URI protocol and the multi-source asset registry.
**`Assets::boot()`** — registers `oz://` as a `PathUtils` resolver pointing at `Assets::localize()`.

**Key static methods:**

| Method                                  | Description                                                                                                                                      |
| --------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| `getSources(): PathSources`             | Static singleton; initially seeded with `Templates::OZ_TEMPLATE_DIR`                                                                             |
| `addSource(string $path)`               | Validates dir is readable, then adds to sources for `oz://` lookup                                                                               |
| `localize(string $path): false\|string` | Resolves `oz://` paths; supports `~core~` (ozone dir) and `~project~` (project dir) prefixes, then falls back to scanning all registered sources |

**Usage in templates / views:**

```php
// Referencing an ozone built-in template:
$view->setTemplate('oz://welcome.blate');

// Referencing a core-only asset (bypass sources scan):
Assets::localize('oz://~core~/foo.blate');

// Adding a plugin's template directory:
Assets::addSource('/path/to/my-plugin/templates');
```

All `oz://` resolution and source registration is handled exclusively by `Assets::boot()`.

---

## 6. Forms & Validation

**Main class**: `OZONE\Core\Forms\Form`

Forms are attached to routes via `.form(new MyForm())` (pass a `Form` instance or a callable factory — class name strings are not accepted) and auto-validated in `RouteInfo` construction. Clean data is accessible via `$ri->getCleanFormData()`.

### Defining Forms

```php
use OZONE\Core\Forms\Form;

class MyForm extends Form
{
    public function __construct()
    {
        parent::__construct();
        // typed field helpers from FieldContainerHelpersTrait:
        $this->string('name')->required(true);
        $this->email('email')->required(true);
        $this->int('age');
        $this->file('avatar');
        $this->enum('role', MyEnum::class);
    }
}
```

**Available field helpers** (via `FieldContainerHelpersTrait`):
`string()`, `int()`, `bigint()`, `bool()`, `date()`, `timestamp()`, `email()`, `phone()`, `password()`, `url()`, `username()`, `cc2()`, `file()`, `gender()`, `enum()`, `list()`, `map()`, `switcher()`

### Field Configuration

```php
$field = $this->string('name')
    ->required(true)
    ->multiple(false)
    ->hidden(false)
    ->type(new TypeString(2, 60))
    ->validator(function (mixed $value, FormData $fd): mixed {
        // return cleaned value or throw on invalid
        return $value;
    });
```

### Pre- and Post-Validation Rules

`expect()` — pre-validation, runs on **raw (unsafe)** data before field validation begins. Non-server-only entries are included in `toArray()` so the client can pre-check before submitting.

`ensure()` — post-validation, runs on **cleaned** data after all fields are validated. Server-side only, never sent to the client.

```php
// Reject the form before touching fields if the plan is wrong:
$this->expect()->eq('plan', 'enterprise', 'PLAN_REQUIRED');

// After validation: passwords must match (cross-field check on clean values):
$this->ensure()
    ->eq('password', 'password_confirm')   // password must equal password_confirm
    ->isNotNull('email');
```

### Fieldsets (Conditional Field Groups)

Fieldsets group related fields within a single form validation pass. Created with `$form->fieldset()` (static, callback mutates the `Fieldset` in-place at definition time) or `$form->dynamicFieldset()` (factory called with the accumulated cleaned `FormData` at validation time). An optional `RuleSet` condition controls whether the fieldset is applied.

```php
$this->string('type')->required(true);

// Static fieldset — callback populates the fieldset immediately at definition time:
$this->fieldset('details', function (Fieldset $fs): void {
    $fs->string('extra_field')->required(true);
});

// Conditional static fieldset — only applied when condition passes:
$this->fieldset('advanced', static function (Fieldset $fs): void {
    $fs->string('extra_field')->required(true);
}, $this->ensure()->eq('type', 'advanced'));

// Dynamic fieldset — factory receives accumulated cleaned FormData at validation time:
$this->dynamicFieldset('extra', function (FormData $fd): Fieldset {
    return Fieldset::static($this, 'extra', function (Fieldset $fs) use ($fd): void {
        if ('advanced' === $fd->get('type')) {
            $fs->string('extra_field')->required(true);
        }
    });
});
```

**`Fieldset`** (`OZONE\Core\Forms\Fieldset`) — represents a named conditional group of fields. Key methods:

- `isStatic() / isDynamic()` — fieldset type
- `legend(I18nMessage|string $legend): static` — sets the display label for the group
- `if(): RuleSet` — returns (or lazy-inits) the condition `RuleSet` evaluated against accumulated cleaned data
- `isEnabled(FormData $fd): bool` — returns true when the condition passes (or no condition is set)
- `build(FormData $fd): ?static` — evaluates condition; returns null when disabled, `$this` for static when enabled, factory result for dynamic
- `toArray()` — serializes: `ref`, `name`, `legend`, `type` (`static`|`dynamic`), `fields` (null for dynamic), `if` (serialized `RuleSet` or null)

**`DynamicValue`** (`OZONE\Core\Forms\DynamicValue<T>`) — wraps a server-side callable for `FormRule` comparisons. `toArray()` returns `['$dynamic' => true]` — tells the client the value is evaluated server-side only.

```php
// Rule value evaluated lazily at validation time:
$dv = new DynamicValue(fn (FormData $fd) => $fd->get('computed'));
$this->ensure()->eq('status', $dv);
```

**`FormUtils::assertValidFieldName(string $name): void`** — validates that a field name is a valid dot-path (e.g. `user.name`). Throws `InvalidArgumentException` on failure.

### FormRule operators

`eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `in`, `not_in`, `is_null`, `is_not_null`

The second argument to each operator may be a scalar, a `Field` (cross-field comparison), or a `DynamicValue` (server-side resolution). When a `DynamicValue` is used, the rule is marked `server_only: true` in `toArray()`.

`FormRule::toArray()` format per entry:

```php
// Scalar / DynamicValue:
['field_ref' => 'type', 'value' => 'admin', 'rule' => 'eq', 'server_only' => false, 'message' => null]
// Cross-field:
['field_ref' => 'password', 'target_ref' => 'password_confirm', 'rule' => 'eq', 'server_only' => false, 'message' => null]
```

### CSRF

```php
$form = new Form();
$form->t_csrf = new CSRF($context);   // enable CSRF protection
```

---

## 7. Authentication & Authorization

### Authentication Methods

Configured per-router in `oz.auth` (`OZ_AUTH_API_AUTH_METHODS` / `OZ_AUTH_WEB_AUTH_METHODS`) and per-route via `.withAuthentication(...)`.

| Scheme Enum      | Class                              | Mechanism                                                    |
| ---------------- | ---------------------------------- | ------------------------------------------------------------ |
| `SESSION`        | `SessionAuth`                      | Cookie-based, always satisfied, stateful                     |
| `BEARER`         | `BearerAuth`                       | `Authorization: Bearer <token>` -> resolves `OZAuth`         |
| `API_KEY_HEADER` | `ApiKeyHeaderAuth`                 | Header `x-ozone-api-key` (configurable) -> resolves `OZAuth` |
| `BASIC`          | `BasicAuth`                        | `Authorization: Basic base64(type\|name\|val:password)`      |
| `DIGEST`         | `DigestAuth` / `DigestRFC2617Auth` | HTTP Digest                                                  |

Access the active auth method in handlers via `$context->auth()` (returns `AuthenticationMethodInterface`).

### Route Guards

Apply guards to require conditions before the handler fires. All return stored results accessible via `$ri->getGuardStoredResults(GuardClass::class)`.

| Guard                             | Applied via                                  | Checks                                                |
| --------------------------------- | -------------------------------------------- | ----------------------------------------------------- |
| `AuthenticatedUserRouteGuard`     | `.withAuthenticatedUser(?string ...$types)`  | `$context->hasAuthenticatedUser()`                    |
| `UserRoleRouteGuard`              | `.withRole(RoleInterface ...$roles)`         | `Roles::hasOneOfRoles($user, $roles)`                 |
| `UserAccessRightsRouteGuard`      | `.withAccessRights(string ...$rights)`       | `auth()->getAccessRights()->can($right)`              |
| `AuthorizationProviderRouteGuard` | `.withAuthorization(string ...$providers)`   | OZAuth state = AUTHORIZED; stored: `{provider, auth}` |
| `CredentialsRouteGuard`           | `.guard(CredentialsRouteGuard::class)`       | username+password prompt                              |
| `PasswordProtectedRouteGuard`     | `.guard(PasswordProtectedRouteGuard::class)` | single password                                       |

### OZAuth (Authorization Provider System)

Used for multi-step authorization flows (email verification, phone ownership, account recovery, file access grants, etc.).

```php
// Start an authorization flow:
$provider = Auth::provider($context, $auth_entity); // AuthorizationProviderInterface

// In a route guarded with .withAuthorization('auth:provider:email:verify'):
$stored = $ri->getGuardStoredResults(AuthorizationProviderRouteGuard::class);
$provider = $stored['provider']; // AuthorizationProviderInterface
$auth     = $stored['auth'];     // OZAuth entity

// Authorization routes auto-registered by AuthorizationService:
// POST /auth/:ref/authorize   -> provider->authorize()
// POST /auth/:ref/refresh     -> provider->refresh()
// GET  /auth/:ref/state       -> provider->getPayload()
// POST /auth/:ref/cancel      -> provider->cancel()
```

**Built-in provider names:**

- `auth:provider:user` — `AuthUserAuthorizationProvider`
- `auth:provider:file` — `FileAccessAuthorizationProvider`
- `auth:provider:email:verify` — `EmailOwnershipVerificationProvider` (sends code via email)
- `auth:provider:phone:verify` — `PhoneOwnershipVerificationProvider` (sends code via SMS)

### User Auth Flows

```php
// Login (POST /login):  Auth\Services\Login
// Logout (POST /logout): Auth\Services\Logout
// Password change: Auth\Services\Password

// Access logged-in user:
$users = $context->getAuthUsers();
$user  = $users->current('my_user_type');   // AuthUserInterface

// Force logout all sessions:
AuthUsers::forceUserLogoutOnAllActiveSessions($user);
```

**User repositories**: implement `AuthUsersRepositoryInterface` and register in `oz.auth.users.repositories` settings.

### Sessions

Sessions are managed by `OZONE\Core\Sessions\Session`, backed by the `oz_sessions` DB table (`OZSession` entity). The session cookie name and lifetime come from `oz.sessions` settings. `Session` is a `BootHookReceiverInterface` — GC runs on `FinishHook`.

Access stateful auth store: `$context->requireAuthStore()`.

---

## 8. Database & ORM

**ORM Library**: [Gobl](https://github.com/silassare/gobl) (`silassare/gobl`)

The `OZONE\Core\App\Db` class (final, static) manages the RDBMS instance.

```php
// Get the global DB instance (auto-initializes):
$db = Db::get();          // or use the global helper: db()

// DB init order (in Db::init()):
//   1. TypeProvider registered (custom column types)
//   2. Migrations state checked -> migration schema loaded (production)
//   3. DbSchemaCollectHook dispatched (plugins add tables)
//   4. Project schema loaded from oz.db.schema
//   5. DbSchemaReadyHook dispatched
//   6. ORM classes enabled for ozone NS + project NS
//   7. db()->lock()
//   8. DbReadyHook dispatched
```

**Schema definition**: Gobl NamespaceBuilder API. OZone schema in `oz/oz_default/oz_schema.php`. Project schema in `oz.db.schema` settings.

**DB namespaces:**

- OZone entities: `Db::getOZoneDbNamespace()` -> `OZONE\Core\Db` (generated in `oz/Db/`)
- Project entities: `Db::getProjectDbNamespace()` -> `{OZ_PROJECT_NAMESPACE}\Db`

**Built-in OZone DB entities** (generated into `oz/Db/` via `oz db build`):
`OZAuth`, `OZCountry`, `OZDbStore`, `OZFile`, `OZJob`, `OZJobBatch`, `OZMigration`, `OZRole`, `OZSession`, `OZUser`, `OZUsername` (and their query classes)

**Custom column types** (registered via `oz.db.columns.types` -> `TypeProvider`):
`TypePhone`, `TypeEmail`, `TypeUrl`, `TypeUsername`, `TypePassword`, `TypeCC2`, `TypeGender`, `TypeFile`

**DB configuration** (`oz.db` settings group):

- `OZ_DB_RDBMS` — RDBMS driver type (default: `mysql`)
- `OZ_DB_HOST`, `OZ_DB_NAME`, `OZ_DB_USER`, `OZ_DB_PASS`
- `OZ_DB_TABLE_PREFIX`, `OZ_DB_CHARSET` (`utf8mb4`), `OZ_DB_COLLATE`

**Keys & secrets** (`OZONE\Core\App\Keys`):

```php
Keys::salt()           // OZ_APP_SALT from env (base64 decoded)
Keys::secret()         // OZ_APP_SECRET from env (base64 decoded)
Keys::newSessionID()   // 64-char hash
Keys::newAuthToken()   // 64-char hash
Keys::newAuthCode(6)   // numeric or alphanumeric code
```

---

## 9. Column Type System

Custom column types extend `Gobl\DBAL\Types\Type` and are registered in `oz.db.columns.types`.

All types implement:

- `getName(): string` — the type identifier
- `static getInstance(array $options): static` — factory for Gobl schema loading
- `runValidation(ValidationSubjectInterface $subject): void` — validation logic
- `phpToDb(mixed $value, RDBMSInterface $rdbms): mixed` — pre-persist transformation

**TypePassword** hashes with `Password::hash()` on `phpToDb()` if value is not already a hash.

**TypeFile** handles `UploadedFile` input, stores the file in temp storage (`TempFS`), and saves the `OZFile` reference as a JSON string.

**TypePhone** — format: `+\d{6,15}` (E.164 without space). Supports `.registered(string $as)` to reject unregistered numbers.

**TypeEmail** — supports `.registered(string $as)`.

**TypeCC2** — 2-letter ISO country code. Supports `.authorized(bool)` to restrict to approved countries.

**TypeGender** — validates against `oz.users` `OZ_USER_ALLOWED_GENDERS` list.

**TypeUsername** — validates username format and length from `oz.users` (`OZ_USER_NAME_MIN_LENGTH`, `OZ_USER_NAME_MAX_LENGTH`, `OZ_USER_NAME_PATTERN`). Supports `.registered(string $as)` to require the username to exist in `oz_usernames`, and `.notRegistered()` to require it not to exist. See also `UsernameUtils::exists(string $name): bool` and `UsernameUtils::get(string $name): ?OZUsername`.

---

## 10. CRUD System

**Classes**: `OZONE\Core\CRUD\TableCRUD`, `TableCRUDListener`, `BaseHandler`

The CRUD system wraps Gobl ORM lifecycle hooks with OZone access control.

### Registering Listeners

```php
// oz.gobl.crud settings:
return [
    MyTableCRUDListener::class => true,
];
```

### Implementing a CRUD Listener

```php
use OZONE\Core\CRUD\TableCRUDListener;
use OZONE\Core\CRUD\AllowCheckResult;
use OZONE\Core\CRUD\AllowRuleBuilder;

class MyTableCRUDListener extends TableCRUDListener
{
    public function register(Context $context): void
    {
        // attach Gobl ORM event listeners
    }

    public function allow(string $action): AllowRuleBuilder
    {
        return (new AllowRuleBuilder())
            ->ifRole(AdminRole::instance())
            ->when(fn() => $this->context->hasAuthenticatedUser());
    }
}
```

**Default `TableCRUDListenerTrait` behavior:**

- `onBeforeCreate()` -> `true` (allow)
- `onBeforeDelete()` -> `false` (deny)
- `onBeforeDeleteAll()` -> `false` (deny)
- `onBeforeUpdateAll()` -> `false` (deny)

**`AllowRuleBuilder` methods**: `ifRole()`, `ifRoles(array, ?atLeast)`, `when(callable)`, `onlyIfIs(array $user_types)`

---

## 11. Hooks & Events

**Base class**: `OZONE\Core\Hooks\Hook extends PHPUtils\Events\Event` (carries `Context`)

Events use `PHPUtils\Events\Event::listen()` static method with priority constants:
`Event::RUN_FIRST`, `Event::RUN_LAST`, or a numeric priority.

### Built-in Hook Events

| Event                 | When triggered                                   | Carries                                                 |
| --------------------- | ------------------------------------------------ | ------------------------------------------------------- |
| `InitHook`            | After full bootstrap                             | `Context`                                               |
| `RequestHook`         | Before each request handled (incl. sub-requests) | `Context`                                               |
| `ResponseHook`        | Before response sent                             | `Context` (MainBootHookReceiver adds CORS headers here) |
| `FinishHook`          | After response sent (not for sub-requests)       | `Context`                                               |
| `RedirectHook`        | Before URL redirect                              | `Context`, `Uri $to`                                    |
| `DbSchemaCollectHook` | Collect DB schema from plugins                   | `RDBMSInterface $db`                                    |
| `DbSchemaReadyHook`   | All tables loaded in DB                          | `RDBMSInterface $db`                                    |
| `DbReadyHook`         | DB locked and ORM enabled                        | `RDBMSInterface $db`                                    |

### Migration Events

`MigrationBeforeRun`, `MigrationAfterRun` — carry `MigrationInterface` + `MigrationMode`.
`MigrationCreated` — carries new migration `int $version`.

### Router Events

`RouteNotFound`, `RouteMethodNotAllowed`, `RouteBeforeRun`, `RouterCreated`

### Auth Events

`AuthUserLoggedIn`, `AuthUserLoggedOut`, `AuthUserLogInFailed`, `AuthUserUnknown`

### Registering Listeners

```php
// In a BootHookReceiverInterface::boot() method:
InitHook::listen(static function (InitHook $hook) {
    // access $hook->context
}, Event::RUN_LAST);

DbReadyHook::listen(static function (DbReadyHook $hook) {
    // $hook->db is RDBMSInterface, fully initialized
});
```

### Cron Events

`CronCollect` — dispatched by `Cron::runDues()` for registering project cron tasks.

---

## 12. Plugins

**Interface**: `OZONE\Core\Plugins\Interfaces\PluginInterface`
**Base class**: `OZONE\Core\Plugins\AbstractPlugin`
**Registry**: `OZONE\Core\Plugins\Plugins` (static)

Plugins are enabled in `oz.plugins` settings. `Plugins::boot()` is called during `OZone::bootstrap()`.

```php
use OZONE\Core\Plugins\AbstractPlugin;

class MyPlugin extends AbstractPlugin
{
    public function __construct()
    {
        parent::__construct('my-plugin', 'MyVendor\MyPlugin', __DIR__ . '/..');
    }

    public static function instance(): PluginInterface
    {
        return new self();
    }

    public function boot(): void
    {
        // Add the plugin's version-controlled source settings dir.
        // The stateful settings dir (data/plugins/{name}/settings/) is auto-registered
        // by AbstractScope::__construct() when the scope is first accessed.
        Settings::addSource($this->getScope()->getSettingsDir()->getRoot());
    }
}
```

**Plugin scope** (`PluginScope extends AbstractScope`):

- `getSourcesDir()` -> `{app}/src/plugins/{Namespace/Dir}/`
- `getDataDir()` -> `{project}/data/plugins/{plugin-name}/`
- `getSettingsDir()` -> `{app}/settings/plugins/{plugin-name}/` (via AbstractScope)
- `getDbNamespace()` -> e.g. `MyVendor\MyPlugin\Db`

**Access a plugin**: `Plugins::getPlugin(MyPlugin::class)`
**OZone core plugin**: `Plugins::ozone()` -> `CorePlugin` instance

---

## 13. Migrations

**Class**: `OZONE\Core\Migrations\Migrations`

Migrations version-control the DB schema. Each migration file returns a `MigrationInterface` instance (generated by Gobl's `Diff` algorithm).

### States (`MigrationsState` enum)

| State           | Meaning                          |
| --------------- | -------------------------------- |
| `NOT_INSTALLED` | `OZ_MIGRATION_VERSION = 0`       |
| `INSTALLED`     | DB version = source code version |
| `PENDING`       | DB version < source code version |
| `ROLLBACK`      | DB version > source code version |

### Workflow

```bash
# 1. Check current state:
oz migrations check

# 2. Generate a new migration after schema changes:
oz migrations create --label="Add users birthdate column"

# 3. Apply pending migrations (--skip-backup skips the pre-run DB backup, not recommended for production):
oz migrations run --skip-backup

# 3b. Apply with backup (default, for production):
oz migrations run

# 4. Rollback to a previous version:
oz migrations rollback --to-version=2
```

**`OZ_MIGRATION_VERSION`** in `oz.db.migrations` settings must match the latest migration file's version.

Migration files are PHP files in `{project}/migrations/` returning `MigrationInterface`. They are sorted by version and must be unique.

---

## 14. CLI Tools

**Binary**: `bin/oz` (PHP CLI) or `bin/ozone` — runs `oz/index.php` -> `Cli::run($argv)`

**CLI framework**: `silassare/kli`. Commands extend `OZONE\Core\Cli\Command::describe()`.

### Built-in Commands

| Command         | Class           | Actions                                                                                                   |
| --------------- | --------------- | --------------------------------------------------------------------------------------------------------- |
| `oz project`    | `ProjectCmd`    | `create` — scaffold new project                                                                           |
| `oz db`         | `DbCmd`         | `build` — generate ORM classes; `backup`; code-gen for Dart/TS                                            |
| `oz migrations` | `MigrationsCmd` | `create`, `check`, `run`, `rollback`                                                                      |
| `oz services`   | `ServicesCmd`   | `generate` — scaffold RESTful service for a table                                                         |
| `oz users`      | `UsersCmd`      | `add` — add a new user                                                                                    |
| `oz scopes`     | `ScopesCmd`     | add scopes (multi-tenant)                                                                                 |
| `oz settings`   | `SettingsCmd`   | `set [--source]`, `unset [--source]` — write to stateful (default) or source settings dir                 |
| `oz cron`       | `CronCmd`       | `run` — run due cron tasks; `start <name>`                                                                |
| `oz jobs`       | `JobsCmd`       | `run`, `finish`, `prune`, `dead-letter`, `cancel`, `work` (daemon), `supervisor` (supervisord config gen) |

**`Utils::assertProjectLoaded()`** — throws if no `app/app.php` found in CWD or ancestor; always the first line in commands that require a project.

**`Utils::isProjectFolder()`** — checks for `app/app.php` in a directory.

### Cron System

```php
use OZONE\Core\Cli\Cron\Cron;
use OZONE\Core\Utils\JSONResult;

// Register tasks in CronCollect listener:
CronCollect::listen(static function () {
    Cron::call(static function (JSONResult $result) {
        $result->setDone()->setData(['processed' => 42]);
    }, 'my-task')->everyHour();
});
```

Task types: `CallableTask` (PHP callable), `CommandTask` (shell command).
Tasks can run `inBackground()` or `oneAtATime()`. Due tasks are queued via `OZONE\Core\Queue\Queue`.
See **sections 24 and 25** for full Queue and Cron API reference.

**Helper scripts** (project root — via `Makefile`):

- `make test` — runs the full test suite (`./vendor/bin/phpunit --testdox`)
- `make test-unit` — runs only the Unit suite
- `make test-integration` — runs only the Integration suite
- `make benchmark` — runs `tests/run_benchmarks.php`
- `make fix` — runs psalm + code style fixer (`oliup-cs fix`)
- `make cs` — checks code style only
- `make lint` — runs psalm static analysis only
- `make clean` — removes Blate caches and temp test artifacts

---

## 24. Job Queue System

**Classes**: `Queue`, `Job`, `JobContract`, `JobsManager`, `JobState`
**Stores**: `DbJobStore` (default), `RedisJobStore` (opt-in via `oz.redis`)
**Workers**: implement `WorkerInterface`, register via `JobsManager::registerWorker()`

### Core Concepts

| Class / Interface   | Role                                                                             |
| ------------------- | -------------------------------------------------------------------------------- |
| `Queue`             | Named channel with error-tolerance config; `push()` creates a `Job`              |
| `Job`               | Pure value object: worker class name, payload, state, retry settings             |
| `JobContract`       | `Job` + store binding; has `save()`, `lock()`, `unlock()`, `isLocked()`          |
| `JobStoreInterface` | Persistence contract: `add`, `get`, `update`, `delete`, `iterator`, `lock`, etc. |
| `WorkerInterface`   | Execution unit: `work()`, `getResult()`, `isAsync()`, `getPayload()`             |
| `JobsManager`       | Static engine: `run()`, `runJob()`, `forceRunJob()`, `finish()`                  |

### State Machine

```
PENDING -> RUNNING -> DONE
                   -> FAILED -> PENDING (if retries remain; run_after enforces retry_delay)
                             -> DEAD_LETTER (retries exhausted)
CANCELLED (terminal, no programmatic cancel API yet)
```

**Lock**: `lock()` is atomic (`UPDATE WHERE locked = false`); only one worker can acquire a job. `isLocked()` checks current state from the store.

**`run_after`**: when a failed job is reset to PENDING, `finish()` sets `run_after = time() + retry_delay`. Both stores' `iterator()` skip jobs where `run_after > now()`, enforcing the delay window.

### Async Jobs

When `WorkerInterface::isAsync()` returns `true`, `JobsManager::runJob()` hands the job off to a background subprocess **without** releasing the lock:

```
runJob() acquires lock -> sets RUNNING -> workAsync()
  -> spawns: oz jobs run --store=X --job=<ref> --force
  -> subprocess: forceRunJob() -> increments try count -> work() -> finish() -> unlock()
```

The parent process never calls `unlock()` — the subprocess owns it via `finish()`. If `Process::start()` throws, the exception propagates to `runJob()`'s `catch` block which calls `finish()` -> `unlock()` exactly once.

`forceRunJob()` is also reachable via `oz jobs run --store=X --job=<ref> --force` (manual forced takeover).

### Additional Job Features

- **Job chaining**: `Job::setChain([['worker', 'payload', 'queue'], ...])`. When a job finishes successfully, `finish()` dispatches the next entry in the chain as a new job with the remaining chain attached. The new job uses the default retry settings (chain entries do not inherit the parent's `retry_max`/`retry_delay`).
- **Max concurrency**: `Queue::setMaxConcurrent(int $n)`. When the RUNNING count exceeds the limit, async workers are demoted to synchronous execution in the same process instead of spawning a subprocess.
- **`JobBeforeStart` hook**: fired by `runJob()` before any job starts. Carries the `JobContractInterface`.
- **Dead-letter**: when `try_count >= retry_max` a FAILED job transitions to `DEAD_LETTER`. Use `oz jobs dead-letter --action=retry` to reset and re-queue.

### `oz jobs` CLI

| Invocation                                                                 | Behaviour                                                                     |
| -------------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| `oz jobs run`                                                              | Process all PENDING jobs from all stores                                      |
| `oz jobs run --store=X --queue=Y --worker=W`                               | Filter by store / queue / worker                                              |
| `oz jobs run --store=X --job=<ref>`                                        | Run one specific job; fail with error if already locked                       |
| `oz jobs run --store=X --job=<ref> --force`                                | Locked -> `forceRunJob()`; unlocked -> `runJob()` (acquire first)             |
| `oz jobs finish --store=X --ref=<ref>`                                     | Mark an orphaned RUNNING job as finished/failed (releases lock in DbJobStore) |
| `oz jobs prune [--store=X] [--queue=Y] [--state=S] [--older-than=N]`       | Delete terminal-state jobs older than N seconds (default 86400)               |
| `oz jobs dead-letter [--store=X] [--queue=Y] --action=list\|retry\|delete` | List, re-queue, or delete dead-letter jobs                                    |

### Implementing a Worker

```php
use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Queue\Interfaces\WorkerInterface;
use OZONE\Core\Utils\JSONResult;

class MyWorker implements WorkerInterface
{
    private JSONResult $result;

    public function __construct(private readonly string $item_id) {}

    public static function getName(): string { return self::class; }

    public function isAsync(): bool { return false; }

    public function work(JobContractInterface $job_contract): self
    {
        $this->result = new JSONResult();
        // ... do work ...
        $this->result->setDone()->setData(['item_id' => $this->item_id]);
        return $this;
    }

    public function getResult(): JSONResult { return $this->result; }

    public static function fromPayload(array $payload): self
    {
        return new self($payload['item_id']);
    }

    public function getPayload(): array { return ['item_id' => $this->item_id]; }
}

// Register in a BootHookReceiver:
JobsManager::registerWorker(MyWorker::class);

// Dispatch a job:
Queue::get(Queue::DEFAULT)
    ->push(new MyWorker('abc123'))
    ->dispatch();        // -> JobContractInterface
```

### Batch Jobs

**Class**: `OZONE\Core\Queue\BatchManager` (final, static)
**Event**: `OZONE\Core\Queue\Hooks\BatchFinished`
**Entity**: `OZONE\Core\Db\OZJobBatch` — backed by `oz_job_batches` table (`id` bigint auto-inc PK, `name` nullable string, `finished_at` nullable timestamp)

A batch groups N workers dispatched as a unit. When every job reaches a terminal state, `BatchFinished` fires once.

```php
// Create and dispatch a batch:
$batch = BatchManager::create(
    [new ResizeWorker('img-1'), new ResizeWorker('img-2')],
    Queue::DEFAULT,
    'resize-images',   // optional human-readable name
);

$batch_id = $batch->getID();   // integer PK -- use this for progress/isFinished queries

// Listen for completion:
BatchFinished::listen(static function (BatchFinished $e) {
    $e->batch;      // OZJobBatch entity
    $e->has_error;  // true if any job ended in FAILED / DEAD_LETTER / CANCELLED
});

// Check progress (aggregates across all registered stores):
$progress = BatchManager::progress($batch_id);
// shape: ['total'=>N, 'pending'=>N, 'running'=>N, 'done'=>N, 'failed'=>N, 'cancelled'=>N, 'dead_letter'=>N]

// Check if fully finished:
$done = BatchManager::isFinished($batch_id);
```

**Key design points:**

- `BatchManager::create()` stores the integer `oz_job_batches.id` in each job's `batch_id` FK column — not a string ref.
- `progress()` and `isFinished()` accept `string $batch_id` (the stringified integer PK).
- `BatchFinished` is dispatched by `JobsManager::finish()` via `BatchManager::onJobSettled()` — store-agnostic: uses `JobStoreInterface::countByBatch()` on the owning store, never queries `oz_jobs` directly.
- Empty batches (`$workers = []`) are immediately marked finished.
- `JobStoreInterface` additions: `countByBatch(string $batch_id, ?JobState $state = null): int` and `listByBatch(string $batch_id): array` — both `DbJobStore` and `RedisJobStore` implement them.

---

## 25. Cron System

**Classes**: `Cron`, `Schedule`, `AbstractTask`, `CallableTask`, `CommandTask`, `CronTaskWorker`
**Queue channels**: `Queue::CRON_SYNC` (foreground), `Queue::CRON_ASYNC` (background subprocess)

### How It Works

`oz cron run` dispatches due tasks as `CronTaskWorker` jobs and then processes both cron queues:

```
Cron::runDues()  -- dispatches due tasks to cron:sync or cron:async
JobsManager::run(null, Queue::CRON_SYNC)   -- runs sync tasks in-process
JobsManager::run(null, Queue::CRON_ASYNC)  -- spawns subprocesses for async tasks
```

For **async** tasks the subprocess is `oz jobs run --store=X --job=<ref> --force`. In that subprocess OZone bootstraps and registers CronCollect listeners, but `Cron::collect()` must be called before executing the task. **`CronTaskWorker` calls `Cron::collect()` in its constructor** — it is idempotent (guarded by the `$collected` flag) so calling it multiple times is safe.

`Cron::collect()` is also called by:

- `Cron::runDues()` (at the start of the cron runner)
- `Cron::start(string $name)` (direct single-task invocation)

```php
// Register in CronCollect listener (typically in a BootHookReceiver):
CronCollect::listen(static function () {
    // Callable task:
    Cron::call(static function (JSONResult $result) {
        $result->setDone()->setData(['processed' => 42]);
    }, 'my-task')->everyHour();

    // Shell command task (runs in background subprocess by default):
    Cron::command('php artisan cache:clear', 'cache-clear')->daily();

    // Worker job task (dispatches another queue job):
    Cron::work(new MyWorker('x'), Queue::DEFAULT)->everyMinute();
});
```

### Task Configuration (fluent on `AbstractTask`)

`Cron::call()`, `Cron::command()`, and `Cron::work()` return a `Schedule`. Task-level config (`inBackground()`, etc.) is on `AbstractTask` and cannot be chained from those helpers. To combine both, use `Cron::addTask()` + `$task->schedule()` directly.

```php
$task->inBackground()          // route to cron:async queue (background subprocess)
     ->oneAtATime(timeout: 300) // skip if previous instance is still running (enforced via DB job state check)
     ->setTimeout(60);          // max execution seconds
```

### Schedule API (fluent on `Schedule`)

`Cron::call/command/work()` all return the `Schedule` created internally by `AbstractTask::schedule()`. A task with no schedule method call runs every minute (default expression `* * * * *`). A task can hold multiple schedules via `addSchedule()`; it fires when **any** schedule is due.

**Representative frequency methods:**

| Method                             | Expression                    | Notes                                                                   |
| ---------------------------------- | ----------------------------- | ----------------------------------------------------------------------- |
| `everyMinute()`                    | `* * * * *`                   | default                                                                 |
| `everyFiveMinutes()`               | `*/5 * * * *`                 |                                                                         |
| `everyHour()`                      | `0 * * * *`                   | minute 0                                                                |
| `everyHourAt(int\|int[] $offset)`  | e.g. `15 * * * *`             | minute offset(s)                                                        |
| `daily()`                          | `0 0 * * *`                   | midnight                                                                |
| `dailyAt('10:30')`                 | `30 10 * * *`                 |                                                                         |
| `twiceDaily(int $h1, int $h2)`     | e.g. `0 1,13 * * *`           |                                                                         |
| `weekdays()` / `weekends()`        | `* * * * 1-5` / `* * * * 6,0` | day-of-week filter                                                      |
| `weekly()`                         | `0 0 * * 0`                   | Sunday midnight                                                         |
| `weeklyOn(int $day, string $time)` |                               | `Schedule::MONDAY` etc.                                                 |
| `monthly()`                        | `0 0 1 * *`                   |                                                                         |
| `lastDayOfMonth(string $time)`     | e.g. `0 0 28-31 * *`          | lazy predicate ensures it runs only on the actual last day of the month |
| `quarterly()`                      | `0 0 1 1-12/3 *`              |                                                                         |
| `yearly()`                         | `0 0 1 1 *`                   |                                                                         |

**Window / conditional methods** (evaluated **lazily** at `shouldRun()` time, not at registration time):

```php
$schedule->between('08:00', '18:00')    // only run when current time is in the window
         ->notBetween('02:00', '04:00') // skip when current time is in the window
         ->timezone('America/New_York'); // evaluate window checks in this timezone
```

`between()` and `notBetween()` can be chained; all predicates must pass (AND). `isDue()` checks the cron expression; `shouldRun()` checks all window predicates.

### `oneAtATime` Enforcement

Two-layer guard using the shared `oz_jobs` DB table (`OZJobsQuery`):

1. **Dispatch time** (`Cron::runDues()`): queries the DB for existing `PENDING` or `RUNNING` jobs with the same task name and `CronTaskWorker` worker class. If any exist, the task is skipped entirely — no new job is queued.
2. **Execution time** (`CronTaskWorker::work()`): queries the DB for `RUNNING` jobs with the same name/worker, excluding the current job ref. If found, marks the job DONE with `skipped: true`.

This mechanism is multi-server safe: the `oz_jobs` table is visible to all nodes sharing the same database, so cross-host concurrent dispatch is prevented.

### `skipIfLate(int $grace_minutes)`

Call on a `Schedule` to skip execution when the cron daemon was offline past the grace window:

```php
Cron::call(fn ($r) => ..., 'my-task')
    ->daily()
    ->skipIfLate(15); // skip if more than 15 min late
```

**Note**: the backward scan only looks 1,440 minutes (24 h) back, so `skipIfLate()` has no practical effect on monthly/yearly schedules.

### `CallableTask` Signature

```php
// Callable receives a JSONResult to fill:
Cron::call(static function (JSONResult $result) {
    $result->setDone()->setData(['rows' => 10]);
}, 'my-task')->daily();
```

`CallableTask::$callable` is declared without a PHP type (`callable` is not a valid property type in PHP 8.x). Use a `@var callable(JSONResult):void` docblock.

### Built-in Queue Channels for Cron

| Channel      | Constant            | Used for                              |
| ------------ | ------------------- | ------------------------------------- |
| `cron:sync`  | `Queue::CRON_SYNC`  | Tasks that must run in the foreground |
| `cron:async` | `Queue::CRON_ASYNC` | Tasks with `inBackground()` set       |

---

## 26. Cache System

**Classes**: `CacheRegistry`, `CacheStore`, `CacheEntry`, `CacheCapabilities`, `CacheGarbageCollector`
**Interface**: `CacheProviderInterface`, `CacheEntryExpiryListenerInterface`
**Drivers**: `RuntimeCache`, `DbCache`, `PHPCache`, `RedisCache`, `MemcachedCache`

### Access Patterns

Obtain a `CacheStore` via `CacheRegistry` — never construct drivers directly:

```php
// 1. Named store — configured in `oz.cache.stores`, overridable per project:
CacheRegistry::store('oz:form:sessions')     // -> CacheStore

// 2. Per-request in-memory memoization (uses OZ_CACHE_DEFAULT_RUNTIME driver):
CacheRegistry::runtime(__METHOD__)           // -> CacheStore

// 3. Persistent across requests (uses OZ_CACHE_DEFAULT_PERSISTENT driver):
CacheRegistry::persistent(self::class)       // -> CacheStore
```

### `CacheStore` API

```php
$store = CacheRegistry::store('oz:form:sessions');

$store->get(string $key, mixed $default = null): mixed
$store->entry(string $key): ?CacheEntry          // includes expiry metadata
$store->set(string $key, mixed $value, ?int $ttl = null): bool
$store->has(string $key): bool
$store->delete(string $key): bool
$store->deleteMultiple(array $keys): bool
$store->clear(): bool
$store->increment(string $key, float|int $by = 1): bool
$store->decrement(string $key, float|int $by = 1): bool
$store->remember(string $key, callable $factory, ?int $ttl = null): mixed
```

**`CacheEntry`** (immutable value object): `$entry->key`, `$entry->value`, `$entry->expiresAt` (float microtime or null), `$entry->isExpired(): bool`, `CacheEntry::forTTL(key, value, ttlSeconds): static`.

### Named Stores (`oz.cache.stores`)

Each key is a colon-separated store name. Each value is an optional config array:

```php
// oz/oz_settings/oz.cache.stores.php
return [
    'oz:form:sessions' => [
        'driver'          => DbCache::class,    // defaults to OZ_CACHE_DEFAULT_PERSISTENT
        'options'         => [],                // driver-specific options
        'expiry_listener' => ResumableFormService::class,  // optional
    ],
];
```

Projects override individual entries in `app/settings/oz.cache.stores.php`; the `array_replace_recursive` merge strategy means overriding one store does not affect the others.

**Built-in named stores:**

| Store name            | Driver    | Expiry listener                            |
| --------------------- | --------- | ------------------------------------------ |
| `oz:rate_limit`       | `DbCache` | —                                          |
| `oz:form:resume`      | `DbCache` | —                                          |
| `oz:form:sessions`    | `DbCache` | `ResumableFormService::onCacheEntryExpiry` |
| `oz:fs:image:filters` | `DbCache` | —                                          |

### Expiry Callbacks (`CacheEntryExpiryListenerInterface`)

Register an expiry listener on a named store to be notified when entries expire:

```php
// In oz.cache.stores:
'oz:form:sessions' => [
    'driver'          => DbCache::class,
    'expiry_listener' => MyListener::class,
],

// MyListener must implement:
interface CacheEntryExpiryListenerInterface {
    public static function onCacheEntryExpiry(string $key, mixed $value, string $store_name): void;
}
```

`CacheGarbageCollector` (registered in `oz.boot`) runs on `FinishHook` and calls the listener for each expired entry found, then hard-deletes the entry (even if the listener throws). Only drivers where `capabilities()->expiryCallbacks = true` are scanned (currently `DbCache`).

### `CacheCapabilities`

```php
new CacheCapabilities(
    perEntryTTL: bool,      // true when the driver honors per-entry TTL
    persistent: bool,       // true when data survives process restart
    expiryCallbacks: bool,  // true when getExpiredEntries() is implemented
    atomic: bool,           // true when increment/decrement is atomic
);
```

| Driver           | perEntryTTL | persistent | expiryCallbacks | atomic |
| ---------------- | ----------- | ---------- | --------------- | ------ |
| `RuntimeCache`   | true        | false      | false           | false  |
| `DbCache`        | true        | true       | true            | false  |
| `PHPCache`       | true        | true       | false           | false  |
| `RedisCache`     | true        | true       | false           | true   |
| `MemcachedCache` | true        | true       | false           | true   |

### Implementing a Custom Driver

```php
class MyDriver implements CacheProviderInterface {
    public function capabilities(): CacheCapabilities { ... }
    public function get(string $key): ?CacheEntry { ... }
    public function getMultiple(array $keys): array { ... }
    public function set(CacheEntry $entry): bool { ... }
    public function increment(string $key, float $factor = 1): bool { ... }
    public function decrement(string $key, float $factor = 1): bool { ... }
    public function delete(string $key): bool { ... }
    public function deleteMultiple(array $keys): bool { ... }
    public function clear(): bool { ... }
    public function getExpiredEntries(int $limit = 100): array { ... }
    public static function fromConfig(string $namespace, array $options = []): static { ... }
}
```

Register in `oz.cache` / `oz.cache.stores` by setting the `driver` key to the FQN of the implementing class.

---

## 15. REST Conventions

**Trait**: `OZONE\Core\REST\Traits\RESTFulService`
**Request wrapper**: `OZONE\Core\REST\RESTFulAPIRequest extends GoblORMRequest`

The `RESTFulService` trait makes a `Service` class into a full CRUD REST controller for a Gobl ORM table. It auto-registers 8 standard routes:

| Action         | HTTP   | Path                   | Route Name                  |
| -------------- | ------ | ---------------------- | --------------------------- |
| `get_one`      | GET    | `/items/:id`           | `SERVICE_PATH.get_one`      |
| `get_all`      | GET    | `/items`               | `SERVICE_PATH.get_all`      |
| `get_relation` | GET    | `/items/:id/:relation` | `SERVICE_PATH.get_relation` |
| `update_one`   | PATCH  | `/items/:id`           | `SERVICE_PATH.update_one`   |
| `update_all`   | PATCH  | `/items`               | `SERVICE_PATH.update_all`   |
| `delete_one`   | DELETE | `/items/:id`           | `SERVICE_PATH.delete_one`   |
| `delete_all`   | DELETE | `/items`               | `SERVICE_PATH.delete_all`   |
| `create_one`   | POST   | `/items`               | `SERVICE_PATH.create_one`   |

Disable individual actions via `static::$available_actions['delete_all'] = false`.

**Relations**: `RESTFullRelationsHelper` loads non-paginated and paginated relations; private relations throw `ForbiddenException`.

### OpenAPI Documentation

Implement `ApiDocProviderInterface::apiDoc(ApiDoc $doc): void` in any Service class. Enable in `oz.api.doc`.

```php
public static function apiDoc(ApiDoc $doc): void
{
    $tag = $doc->addTag('Items', 'Item management.');
    $doc->addOperationFromRoute('my:items.list', 'GET', 'List Items', [
        $doc->success(['items' => [...]])
    ], ['tags' => [$tag->name], 'operationId' => 'Items.getAll']);
}
```

OpenAPI spec served at `GET /api-doc-spec.json` (`ApiDocService`).
Swagger UI at `GET /api-doc-view.html` (`ApiDocView`).

---

## 16. Testing

**Framework**: PHPUnit 9.x
**Test App**: `tests/App.php` extends `AbstractApp`

**Run tests**: `make test` (or `./vendor/bin/phpunit --testdox`)

**Test suites**:

| Suite         | Directory                                 | Bootstrap                         |
| ------------- | ----------------------------------------- | --------------------------------- |
| `Unit`        | `tests/` (excluding `tests/Integration/`) | `tests/autoload.php`              |
| `Integration` | `tests/Integration/`                      | `tests/Integration/bootstrap.php` |

> **Note:** `tests/autoload.php` calls `OZone::bootstrap(new App())` as a unit-test workaround so that
> ORM entities, settings, and context are available in unit tests without running a real HTTP request.
> Integration tests do **not** call `OZone::bootstrap()` — they run `bin/oz` subprocesses in real project
> directories via `OZTestProject`. See section 23 for the full integration test conventions.

**Unit test namespace**: `OZONE\Tests` -> `tests/`

**Unit test directories** (under `tests/`):

| Directory   | Covers                                                                      |
| ----------- | --------------------------------------------------------------------------- |
| `Access/`   | `AccessRights`, `AtomicAction`, role resolution                             |
| `App/`      | `Settings`, `Context`, `JSONResponse`, `Keys`                               |
| `Auth/`     | Authentication methods, authorization, session mgmt                         |
| `Cache/`    | `CacheRegistry`, `CacheStore`, `CacheEntry`, runtime and persistent drivers |
| `Columns/`  | Custom DB column types (`TypePhone`, `TypeEmail`, …)                        |
| `Cron/`     | `Schedule`, cron expressions                                                |
| `CRUD/`     | `TableCRUD`, `AllowRuleBuilder`                                             |
| `Forms/`    | Form definition, field validation, multi-step                               |
| `FS/`       | File-system helpers, `TempFS`, `FS` drivers                                 |
| `Http/`     | `Uri`, `Request`, `Response`                                                |
| `Crypt/`    | `DoCrypt`, `Hasher`, `Random`                                               |
| `Lang/`     | i18n loading                                                                |
| `Queue/`    | `Job`, `Queue`, job store drivers                                           |
| `Router/`   | Route matching, guards, middlewares                                         |
| `Services/` | Built-in services (e.g. QR code)                                            |
| `Utils/`    | Utility helpers                                                             |

Shared helpers:

- `tests/TestUtils.php` — `TestUtils::router()` returns a pre-populated `Router` (static + dynamic routes) for use in any test or benchmark.

When writing unit tests:

- Extend PHPUnit `TestCase`
- Place in `tests/` under a matching namespace subdirectory
- Full bootstrap (DB, settings, context) is available since `OZone::bootstrap()` runs in `tests/autoload.php`

---

## 17. Key Patterns & Conventions

### Namespace & File Layout

```
OZONE\Core\{Module}\{ClassName}  ->  oz/{Module}/{ClassName}.php
OZONE\Core\{Module}\Interfaces\  ->  oz/{Module}/Interfaces/
OZONE\Core\{Module}\Events\      ->  oz/{Module}/Events/
OZONE\Core\{Module}\Traits\      ->  oz/{Module}/Traits/
OZONE\Core\{Module}\Enums\       ->  oz/{Module}/Enums/
```

### Global Helpers

```php
app()       // returns AppInterface instance
db()        // returns RDBMSInterface (Gobl)
env(string $key)  // reads .env value
oz_trace(...)     // debug tracing helper
```

### Exception Hierarchy

All HTTP exceptions extend `OZONE\Core\Exceptions\BaseException`:

- `ForbiddenException` — 403
- `NotFoundException` — 404
- `MethodNotAllowedException` — 405
- `BadRequestException` — 400
- `InvalidFormException` — 400 (form validation failure; `BaseException::INVALID_FORM` maps to HTTP 400)
- `UnauthorizedException` — 401

Throw these in guards, middlewares, or handlers; they are caught by `Context::handle()` and serialized via `informClient()`.

### JSON Response Structure

All API responses from `Service::respond()`:

```json
{
  "error": 0|1,
  "msg": "OK",
  "data": { ... },
  "utime": 1700000000,
  "stime": 1700003600
}
```

### Entity Traits

- `UserEntityTrait` — auto-applied by `MainBootHookReceiver::onTableFilesGenerated()` to tables recognized by `UsersRepository::isTableSupported()`
- `FileEntityTrait` — auto-applied to `oz_files` table

### Settings Override Pattern

App-level settings override framework defaults. To customize a setting group in your app:

```
{app}/settings/oz.auth.php   # overrides oz/oz_settings/oz.auth.php keys
```

Keys are merged with `array_replace_recursive` (or `array_merge` for indexed arrays).

### Route Name Conventions

OZone built-in routes use `oz:` prefix: `oz:login`, `oz:logout`, `oz:account-recovery`, etc.
Project routes should use a project-specific prefix: `myapp:resource.action`.

### Scopes

Multi-tenant/multi-origin support via scopes. A project can have multiple scopes (each with its own `index.php`, settings overrides, origin, and `AbstractApp` subclass). Scopes live in `{project}/scopes/{name}/`. Generate with `oz scopes add`.

---

## 18. Dependency Summary

| Package                | Role                                                                                                |
| ---------------------- | --------------------------------------------------------------------------------------------------- |
| `silassare/gobl`       | ORM, DBAL, schema management, CRUD queries, code gen                                                |
| `silassare/kli`        | CLI framework                                                                                       |
| `silassare/blate`      | Template engine for web views, settings files, and code-gen templates; integrated via `BlatePlugin` |
| `silassare/php-utils`  | `Event`, `Store`, `PathUtils`, `Str`, `ArrayCapableTrait`                                           |
| `claviska/simpleimage` | Image processing (captcha, profile picture resizing)                                                |
| `symfony/process`      | Shell process execution (CLI tasks)                                                                 |
| `zircote/swagger-php`  | OpenAPI annotation and generation                                                                   |
| `psr/http-message`     | PSR-7 HTTP message interfaces                                                                       |
| `psr/log`              | PSR-3 logging interface                                                                             |
| `ext-gd`               | Required for captcha image generation                                                               |
| `ext-pdo`              | Required for database access                                                                        |
| `ext-openssl`          | Required for cryptographic operations                                                               |
| `ext-json`             | Required for JSON encoding/decoding                                                                 |
| `ext-libxml`           | Required for XML processing                                                                         |
| `ext-simplexml`        | Required for XML parsing                                                                            |
| `ext-posix`            | Required for process management (POSIX signals)                                                     |
| `ext-bcmath`           | Required for arbitrary-precision arithmetic                                                         |
| `ext-fileinfo`         | Required for MIME type detection                                                                    |

**Minimum PHP**: 8.1
**Default RDBMS**: MySQL (`ext-pdo`, `Gobl\DBAL\Drivers\MySQL\MySQL`)

---

## 19. In-House Package APIs

The packages below are first-party dependencies. Read their source under `vendor/` before using; do **not** guess at APIs.

Each package ships its own detailed agent/copilot instructions. **Always read these files before writing code that uses the package.**

| Package               | Instructions / Docs                                          |
| --------------------- | ------------------------------------------------------------ |
| `silassare/gobl`      | `vendor/silassare/gobl/.github/copilot-instructions.md`      |
| `silassare/kli`       | `vendor/silassare/kli/.github/copilot-instructions.md`       |
| `silassare/php-utils` | `vendor/silassare/php-utils/.github/copilot-instructions.md` |
| `silassare/blate`     | `vendor/silassare/blate/.github/copilot-instructions.md`     |

---

## 20. Benchmark System

**Class**: `OZONE\Tests\Benchmark` — `tests/Benchmark.php`

A fluent harness for measuring execution speed, detecting regressions, and
tracking performance over time. Results are rendered via `KliTable` and can
be persisted to JSON for baseline comparison in future runs.

### Running benchmarks

Benchmarks live in `tests/run_benchmarks.php` (a standalone PHP script, not a PHPUnit test). Run with:

```sh
make benchmark
```

Output is suppressed unless at least one callable is classified as REGRESSION or IMPROVEMENT — silent when all results are STABLE. Baseline results are stored in `tests/benchmark-baseline.json` and updated after every run.

### Basic usage

```php
use OZONE\Tests\Benchmark;

$bm = Benchmark::create()
    ->warmup(5)          // unmeasured warmup calls (default 3)
    ->maxDuration(1.0)   // run each callable for up to 1 second
    ->run([
        'sha256' => fn() => hash('sha256', 'test'),
        'sha512' => fn() => hash('sha512', 'test'),
    ]);

$bm->orderByFastest()->prettyPrint();

// Persist baseline:
file_put_contents('baseline.json', $bm->exportJson());

// Later: compare against baseline:
$baseline = Benchmark::fromJson(file_get_contents('baseline.json'));
Benchmark::create()->maxDuration(1)->warmup(5)
    ->run([...])          // same labels
    ->compareWith($baseline);
```

### Configuration (fluent)

| Method                            | Default | Description                                                       |
| --------------------------------- | ------- | ----------------------------------------------------------------- |
| `warmup(int $iterations)`         | `3`     | Unmeasured calls before timing begins (prime caches/JIT)          |
| `maxDuration(float $seconds)`     | `null`  | Stop after this many wall-clock seconds. Required if no max iter. |
| `maxIterations(int $n)`           | `null`  | Stop after this many calls. Required if no max duration.          |
| `checkDuplicate(bool $check)`     | `false` | Track repeated return values; populates `dup_count`/`dup_rate`    |
| `trackMemory(bool $track)`        | `false` | Record peak memory delta per callable in `memory_kb`              |
| `regressionThreshold(float $pct)` | `5.0`   | % band classifying a change as STABLE instead of REGRESSION       |

At least one of `maxDuration()` or `maxIterations()` must be set before `run()`.

### Execution & results

```php
->run(array $callables): self    // run and store results
->reset(): self                  // clear results for reuse

->getResults(): array            // all records keyed by label
->getResult(string $ref): ?array // single record

->orderByFastest(): self         // sort by avg_ns ascending
->orderBySlowest(): self         // sort by avg_ns descending
->orderByBestEntropy(): self     // sort by dup_rate ascending
```

### Result record keys

Each entry returned by `getResults()` / `getResult()` contains:

| Key           | Type     | Description                                           |
| ------------- | -------- | ----------------------------------------------------- |
| `ref`         | `string` | Callable label                                        |
| `iterations`  | `int`    | Number of measured calls                              |
| `ops_per_sec` | `float`  | Theoretical ops/sec derived from `avg_ns`             |
| `avg_ns`      | `float`  | Mean call duration in nanoseconds                     |
| `min_ns`      | `float`  | Fastest call in nanoseconds                           |
| `max_ns`      | `float`  | Slowest call in nanoseconds                           |
| `median_ns`   | `float`  | Median call duration in nanoseconds                   |
| `p95_ns`      | `float`  | 95th-percentile call duration (nearest-rank)          |
| `stddev_ns`   | `float`  | Sample standard deviation in nanoseconds (Bessel n-1) |
| `total_s`     | `float`  | Sum of measured durations in seconds (pure CPU time)  |
| `wall_s`      | `float`  | Total wall-clock seconds (includes loop overhead)     |
| `dup_count`   | `int`    | Duplicate return-value count (0 if disabled)          |
| `dup_rate`    | `float`  | Duplicate percentage (0.0 if disabled)                |
| `memory_kb`   | `?float` | Peak memory delta in KB (`null` if disabled)          |

### Output methods

| Method                         | Description                                                             |
| ------------------------------ | ----------------------------------------------------------------------- |
| `prettyPrint(): self`          | Full KliTable — all columns plus a Relative (1.00x) column              |
| `printSummary(): self`         | Compact 5-column KliTable: Ref, Iterations, Ops/sec, Avg (ns), Relative |
| `compareWith(Benchmark): self` | Regression table: Current, Baseline, Delta, Change %, Status            |

### Comparison status labels (`compareWith`)

| Status        | Meaning                                         | Color        |
| ------------- | ----------------------------------------------- | ------------ |
| `REGRESSION`  | `avg_ns` increased beyond `regressionThreshold` | red + bold   |
| `IMPROVEMENT` | `avg_ns` decreased beyond `regressionThreshold` | green + bold |
| `STABLE`      | Change within the threshold band                | yellow       |
| `NEW`         | Present in current run but absent from baseline | cyan         |
| `REMOVED`     | Present in baseline but absent from current run | dark gray    |

### Baseline persistence

```php
// Export after a run:
$json = $bm->exportJson();   // JSON_PRETTY_PRINT string
file_put_contents($path, $json);

// Reload later as a read-only baseline (no re-run):
$baseline = Benchmark::fromJson(file_get_contents($path));
$current->compareWith($baseline);
```

### When to add new benchmarks

Add an entry to `tests/run_benchmarks.php` whenever you introduce or modify a
performance-sensitive code path — especially:

- New routing features (guards, middlewares, path parsers)
- New column types or form validators that run on every request
- New cache drivers or cache-access patterns
- New cryptographic helpers (`DoCrypt`, `Hasher`, `Random`)
- New URI handling or HTTP message transformations
- Any hot path that runs O(n) per request or per DB row

Use the label format `area_operation[_variant]` (e.g. `router_find_static`,
`hasher_hash64`, `docrypt_encrypt_256`) so the baseline JSON stays consistent
across commits.

---

## 21. CLI Command Reference

**Binary**: `bin/oz` (or `bin/ozone`) — detects project automatically.

**Auto-detection logic** (in `oz/index.php`):

- If `app/boot.php` exists in CWD -> load project (requires `vendor/autoload.php`)
- Otherwise -> load ozone's own `vendor/autoload.php` and run CLI as global tool

### `oz project`

| Action   | Options                                                                                                                                                                                       | Description                           |
| -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------- |
| `create` | `-r/--root-dir` (default `.`), `-n/--name` (required), `--namespace` (required, default `_Default_`), `-c/--class-name` (default `SampleApp`), `-p/--prefix` (required, 2-char, default `SA`) | Scaffold a new project                |
| `serve`  | `-h/--host`, `-p/--port`, `-r/--doc-root`, `-s/--scope` (default `api`)                                                                                                                       | Serve a scope via PHP built-in server |
| `backup` | `-d/--dir`, `-f/--full`                                                                                                                                                                       | Back up the project                   |

After `project create`, one default scope named `api` is automatically created.
The generated `composer.json` requires `silassare/ozone` at the current version.
Run `composer update` or `composer install` in the new project dir to complete setup.

### `oz scopes`

| Action | Options                                                                                                          | Description                      |
| ------ | ---------------------------------------------------------------------------------------------------------------- | -------------------------------- |
| `add`  | `-n/--name` (required), `-o/--origin` (required, default `http://localhost`), `-a/--api` (bool, default `false`) | Add a named scope to the project |

Each scope creates:

- Private dir: `scopes/{name}/` (settings, templates, `.htaccess`)
- Public dir: `public/{name}/` (`index.php`, `robots.txt`, `favicon.ico`, `.htaccess`)

### `oz db`

| Action     | Options                                   | Description                          |
| ---------- | ----------------------------------------- | ------------------------------------ |
| `build`    | `-a` all, `-c` class-only, `-n` namespace | Generate ORM PHP classes from schema |
| `generate` | `-d/--dir`, `-n/--namespace`              | Generate SQL DDL file                |
| `source`   | `-f/--file`                               | Execute SQL file against DB          |
| `backup`   | `-d/--dir`                                | Dump DB to file                      |

### `oz migrations`

| Action     | Options                               | Description                                           |
| ---------- | ------------------------------------- | ----------------------------------------------------- |
| `create`   | `-f/--force`, `-l/--label` (required) | Generate migration from schema diff                   |
| `check`    | —                                     | Show current migration state                          |
| `run`      | `--skip-backup`                       | Apply all pending migrations (skip pre-run DB backup) |
| `rollback` | `--to-version=N`                      | Rollback to version N                                 |

### `oz services`

`generate` — scaffold a RESTful service class for a Gobl ORM table.

### `oz settings`

| Action  | Options                                                                                          | Description                        |
| ------- | ------------------------------------------------------------------------------------------------ | ---------------------------------- |
| `set`   | `-s/--scope` (optional), `-g/--group` (required), `-k/--key` (required), `-v/--value` (required) | Persist a key in a settings group  |
| `unset` | `-s/--scope` (optional), `-g/--group` (required), `-k/--key` (required)                          | Remove a key from a settings group |

Value parsing (auto-detected from the string argument): `null` -> null, `true`/`false` -> bool, numeric strings -> int/float, `{...}`/`[...]` -> JSON object/array, anything else -> string.

### `oz users`

| Action | Options                                                                                     | Description    |
| ------ | ------------------------------------------------------------------------------------------- | -------------- |
| `add`  | `-e/--email`, `-p/--phone`, `-n/--name`, `-s/--pass` (password), and other user-field flags | Add a new user |

### `oz cron`

| Action  | Options     | Description                    |
| ------- | ----------- | ------------------------------ |
| `run`   | —           | Run all due cron tasks         |
| `start` | `-n/--name` | Run a specific named cron task |

### `oz jobs`

| Action        | Options                                                                          | Description                                     |
| ------------- | -------------------------------------------------------------------------------- | ----------------------------------------------- |
| `run`         | `--store`, `--queue`, `--worker`, `--job`, `--force`                             | Process jobs (all stores, or filtered)          |
| `finish`      | `--store`, `--ref`                                                               | Mark an orphaned RUNNING job as finished/failed |
| `prune`       | `--store`, `--queue`, `--state`, `--older-than`                                  | Delete terminal-state jobs older than N seconds |
| `dead-letter` | `--store`, `--queue`, `--action=list\|retry\|delete`                             | List, re-queue, or delete dead-letter jobs      |
| `cancel`      | `--store`, `--ref`                                                               | Cancel a PENDING job                            |
| `work`        | `--store`, `--queue`, `--worker`, `--sleep`, `--max-time`                        | Run a persistent queue worker daemon            |
| `supervisor`  | `--queue`, `--workers`, `--sleep`, `--memory`, `--log-dir`, `--user`, `--output` | Generate a supervisord config section           |

---

## 22. Scope System

A **scope** is a named entry point into the project (e.g. `api`, `www`, custom). Each scope has:

- A name (lowercase slug)
- An origin URL (used for CORS and `OZ_DEFAULT_ORIGIN`)
- An `api` flag — when true, routes from `oz.routes.api` are loaded; when false, `oz.routes.web`

**Directory layout** after `oz scopes add -n www -o http://example.com`:

```
scopes/
  www/
    settings/
      oz.request.php    # OZ_DEFAULT_ORIGIN for this scope
    templates/
    .htaccess           # deny from all
public/
  www/
    index.php           # calls OZone::run(new AppClass())
    robots.txt
    favicon.ico
    .htaccess
```

**`project serve`** launches PHP's built-in server pointed at `public/{scope}/`:

```bash
oz project serve -s api -h localhost -p 8080
```

Writes a `server.json` to the scope's cache dir with host/port/protocol.

**Multi-scope projects**: Each `public/{scope}/index.php` bootstraps its own `Context` independently. Scopes share the same app class and settings hierarchy but can override any settings group in `scopes/{name}/settings/`.

---

## 23. Integration Test Conventions

### Overview

Integration tests exercise the CLI toolchain (project creation, scope management, DB migrations, etc.) against a real filesystem and database. They live separately from unit tests.

| Suite       | Directory                                 | Bootstrap                         |
| ----------- | ----------------------------------------- | --------------------------------- |
| Unit        | `tests/` (excluding `tests/Integration/`) | `tests/autoload.php`              |
| Integration | `tests/Integration/`                      | `tests/Integration/bootstrap.php` |

### Project directories

Each test that needs a project creates it in `/tmp/_oz_tests_/projects/{name}/` (auto-created, `.gitignore`'d). Directories are created and destroyed per test class via `OZTestProject` helper.

### `OZTestProject` helper (`tests/Integration/Support/OZTestProject.php`)

Wraps `bin/oz` subprocess calls in a project directory.

**Vendor caching — `composer install` runs only when dependencies actually change.**

`OZTestProject::create(string $name, array $deps = [], array $deps_dev = [], bool $shared = true)`

1. Creates `/tmp/_oz_tests_/projects/{name}/` and runs `oz project create` inside it.
2. Patches the generated `composer.json` with `$deps`/`$deps_dev` and adds a path
   repository pointing at the ozone root (so `silassare/ozone` is resolved locally
   without downloading).
3. Computes a SHA-256 hash of the final `require` + `require-dev` maps (includes
   `$name` when `$shared = false` so the vendor dir is not shared with other projects
   that have the same dep set).
4. If `/tmp/_oz_tests_/_vendors_cache_/{hash}/` already exists, symlinks `vendor/` there
   — nothing is installed.
   Otherwise runs `composer install`, moves `vendor/` to the cache dir, then
   symlinks back in.

Settings override — DB credentials and other per-test values should be injected
via `writeEnv()` (appends/updates `.env`). The generated `app/settings/oz.db.php`
reads all DB config from `.env` via `env()`, so no PHP settings file needs
touching for typical DB tests.
For other settings overrides use `setSetting()`.

```php
// Create project — composer install only runs if dep hash not yet cached:
$proj = OZTestProject::create('my-test');

// Inject DB config into .env:
$proj->writeEnv((new DbTestConfig())->toEnvArray());

// Override any settings key (writes/updates app/settings/{group}.php):
$proj->setSetting('oz.request', 'OZ_DEFAULT_ORIGIN', 'http://localhost:8080');

// Run any oz command inside the project dir:
$proc = $proj->oz('migrations', 'run');
$proc->mustRun();

// Write a file from a stub with placeholder substitution:
$proj->writeFileFromStub('MyStub', 'app/MyClass.php', [
    'namespace' => $ns,
    'flag_file' => '/tmp/my-flag',
]);

// Tear down (unlinks vendor symlink, removes project dir):
$proj->destroy();
```

`OZTestProject::create()` never corrupts the ozone root `vendor/` — the path
repository only places a symlink inside the cached vendor as `vendor/silassare/ozone`.

### Multi-DB support

`DbTestConfig` reads per-driver env vars. SQLite is always enabled (no env vars required). MySQL and PostgreSQL are enabled when their respective host variable is set.

| Env var                       | Default      | Description              |
| ----------------------------- | ------------ | ------------------------ |
| `OZ_TEST_MYSQL_HOST`          | —            | Enable MySQL (required)  |
| `OZ_TEST_MYSQL_DB`            | `ozone_test` | MySQL database name      |
| `OZ_TEST_MYSQL_USER`          | `root`       | MySQL username           |
| `OZ_TEST_MYSQL_PASSWORD`      | `""`         | MySQL password           |
| `OZ_TEST_POSTGRESQL_HOST`     | —            | Enable PostgreSQL (req.) |
| `OZ_TEST_POSTGRESQL_DB`       | `ozone_test` | PostgreSQL database name |
| `OZ_TEST_POSTGRESQL_USER`     | `postgres`   | PostgreSQL username      |
| `OZ_TEST_POSTGRESQL_PASSWORD` | `""`         | PostgreSQL password      |

**`DbTestConfig::allConfigured(string $tag = ''): array`** returns one dataset entry per enabled DB type. The `$tag` string is appended to the SQLite filename to avoid cross-test-class file conflicts — always pass a short class-specific slug (e.g. `'migrations'`, `'dbbuild'`, `'svcgen'`, `'backup'`).

### Static project store pattern (required for multi-DB tests)

**Never combine `@depends` with `@dataProvider`** — PHPUnit 9 cannot propagate dependency return values across data sets (dataset name suffix prevents the lookup). Use `@dataProvider` only on every test method.

Projects are shared across test methods via a `private static array $projects` keyed by RDBMS name. The **first** test in declaration order creates the project; all subsequent tests call `self::getProject($config)` to retrieve it.

**Setup order requirement**: `oz db build --build-all --class-only` MUST be called before any migration command so ORM classes are available. Follow this sequence in the first test method:

```php
$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
$proj->oz('migrations', 'run', '--skip-backup')->mustRun();
```

**Example pattern** (also see `MigrationsTest`, `DbBuildTest`, `ServicesGenerateTest`, `DbBackupTest`):

```php
final class MyFeatureTest extends TestCase
{
    /** @var array<string, OZTestProject> */
    private static array $projects = [];
    /** @var array<string, null|string> */
    private static array $dbFiles = [];

    public static function tearDownAfterClass(): void
    {
        foreach (self::$projects as $key => $proj) {
            $proj->destroy();
            $file = self::$dbFiles[$key] ?? null;
            if (null !== $file && \is_file($file)) { \unlink($file); }
        }
        self::$projects = [];
        self::$dbFiles  = [];
        parent::tearDownAfterClass();
    }

    /** @dataProvider provideDbConfig */
    public function testFirst(DbTestConfig $config): void
    {
        $rdbms = $config->rdbms;
        $proj  = OZTestProject::create('myfeature-' . $rdbms, shared: false);
        $proj->writeEnv($config->toEnvArray());
        self::$projects[$rdbms] = $proj;
        self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;
        // Required setup order:
        $proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
        $proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
        $proj->oz('migrations', 'run', '--skip-backup')->mustRun();
        // ... first assertion ...
    }

    /** @dataProvider provideDbConfig */
    public function testSecond(DbTestConfig $config): void
    {
        $proj = self::getProject($config);
        // ... next assertion using $proj ...
    }

    public static function provideDbConfig(): array
    {
        return DbTestConfig::allConfigured('myfeature');
    }

    private static function getProject(DbTestConfig $config): OZTestProject
    {
        $rdbms = $config->rdbms;
        if (!isset(self::$projects[$rdbms])) {
            self::fail(sprintf('Project for %s not initialized. Did testFirst pass?', $rdbms));
        }
        return self::$projects[$rdbms];
    }
}
```

### Test class guidelines

- One focused test class per feature. Keep classes small.
- Namespace: `OZONE\Tests\Integration\{Feature}\{ClassName}Test`
- File path: `tests/Integration/{Feature}/{ClassName}Test.php`
- Extend `PHPUnit\Framework\TestCase` for CLI/filesystem tests
- All DB-related test classes must use `@dataProvider provideDbConfig` + `DbTestConfig::allConfigured('<tag>')` to exercise every configured RDBMS.
- **Never combine `@depends` with `@dataProvider`** — use the static project store pattern instead.

### Running integration tests

```bash
./vendor/bin/phpunit --testsuite Integration
```
