# TODO Resolution Plan

Items are ordered **easiest / safest first, riskiest last** — implementation proceeds top-to-bottom.
Each item must be **explicitly approved** before any code change is made.
Completed items are marked ✅.

---

## Pending

---

### [x] D. `oz/oz_templates/oz.route.access.grant.form.blate` — Render access-grant form ✅

Full HTML form rendered. Changes:

- `AbstractRouteGuard`: `grant_form_ref` field marked `.hidden()` so templates treat it as a hidden input.
- `Form::toArray()`: now serializes `$fields` in-place via `array_map(fn => $f->toArray())` and exposes
  `has_steps` flag — `AccessGrantView` was simplified to just `$form->toArray()` with no manual mapping.
- Template: full HTML page using `oz.css`, supports CSRF token, hidden fields with pre-filled defaults,
  `<input type="password">` for password-type fields, `*` indicator for required fields, submit button.
- `oz.fr.php`: added `OZ_ACCESS_GRANT_FORM_TITLE` and `OZ_ACCESS_GRANT_FORM_SUBMIT_BTN`.

---

### [x] C. `Auth/Views/LogoutAndRedirectView.php` — Chrome freeze on `Clear-Site-Data` header ✅

Proper fix implemented — no more cookie-only compromise. Changes:

- `LogoutAndRedirectView`: when Clear-Site-Data is enabled, instead of calling `$context->redirect()`
  (which produces a 3xx), render a **200** intermediate page (`oz.redirect.blate`) with the
  `Clear-Site-Data` header attached to it, then call `$context->respond()`. The page redirects
  the browser via `<meta http-equiv="Refresh">` + JS — no 3xx involved, Chrome does not freeze.
- `oz.cache.php`: restored `OZ_CLEAR_SITE_DATA_HEADER_VALUE` to `"cache", "cookies", "storage"`
  (dropped deprecated `"executionContexts"`). Updated comment to explain the intermediate-page
  strategy and link to the Chromium bug.

---

### [x] F. `Hooks/MainBootHookReceiver.php` — Welcome page for web context root ✅

**Current behaviour:** Accessing the root URL in web context throws `ForbiddenException`.

**Proposed solution:**
Add a minimal Blate template `oz://oz.welcome.blate` rendered as a `WebView` — shows
the project name, OZone version, and (if API doc is enabled) a link to the Swagger UI.
Add `OZ_SHOW_WELCOME_PAGE` boolean to `oz.config` (default `true` in dev, `false` in prod).
Served only when `OZ_SHOW_WELCOME_PAGE` is enabled; otherwise keeps the `ForbiddenException`.

**Complexity:** Low | **Risk:** Low (new template + route + settings key, isolated path)

Changes:

- `oz.config.php`: added `OZ_SHOW_WELCOME_PAGE => true` (projects override to `false` in prod).
- `MainBootHookReceiver::onRouteNotFound()`: replaced TODO with `WebView($context)` rendering
  `oz://oz.welcome.blate` when `OZ_SHOW_WELCOME_PAGE` is enabled; `ForbiddenException` otherwise.
- `oz.welcome.blate`: new template — project name, OZone version, optional API doc link
  (shown only when both `OZ_API_DOC_ENABLED` and `OZ_API_DOC_SHOW_ON_INDEX` are set).
- `oz.fr.php`: added `OZ_WELCOME_PAGE_TITLE` (`'Bienvenue'`) and
  `OZ_WELCOME_PAGE_API_DOC_LINK` (`'Documentation API'`).

---

### [x] B. `Auth/Methods/SessionAuth.php` — Inform user on session hijacking detection ✅

**Current behaviour:** When a session source-key mismatch is detected for an authenticated user,
a `ForbiddenException` is thrown with no user-visible message beyond the HTTP 403.

**Proposed solution:**
Dispatch a new `SessionHijackingDetected` event (carries `Context` + `Session`) before throwing.
A default listener logs the event at warning level. Application code can listen to send a security
alert email/notification to the user. Add a translatable `OZ_SESSION_HIJACKING_DETECTED` i18n key
so the API response body is human-readable.

**Complexity:** Low | **Risk:** Low (new event class + small SessionAuth change + i18n key)

Changes:

- `Auth/Events/SessionHijackingDetected.php`: new event carrying `Context` + `Session`.
- `SessionAuth::session()`: dispatches `SessionHijackingDetected` before throwing, passes
  `'OZ_SESSION_HIJACKING_DETECTED'` as the exception message key (replaces bare `null`).
- `MainBootHookReceiver::boot()`: registers a default `RUN_LAST` listener that calls
  `oz_logger()->warning()` with session ID and user IP.
- `oz.fr.php`: added `OZ_SESSION_HIJACKING_DETECTED` (`'Votre session a été interrompue pour des raisons de sécurité.'`).

---

### [x] L. `REST/ApiDoc.php` — Add API doc for all OZone built-in services

**Current behaviour:** `ApiDoc::loadProviders()` iterates over route providers and calls `apiDoc()` only on
those that implement `ApiDocProviderInterface`. Virtually none of the built-in OZone services implement it.

**Proposed solution:**
Implement `ApiDocProviderInterface::apiDoc()` on each built-in service/view that exposes a public HTTP API:
auth routes (login, logout, password), CSRF endpoint, file serving, auth link, API doc spec, QR code, etc.
Each provider describes its routes, request parameters/forms, and response shapes using the `ApiDoc` helpers.

**Complexity:** Low | **Risk:** None (purely additive — annotations and doc registration only)

---

### [ ] E. `Services/QRCode.php` — QR code generation

**Current behaviour:** QR generation is commented out; the route returns an empty PNG body.

**Proposed solution:**
Add `endroid/qr-code` (pure PHP, no `ext-imagick` required) to `composer.json` and generate the
PNG in-memory, writing it to the `$file` tmpfile handle already created. Alternatively, use a
bundled pure-PHP QR encoder to avoid adding a dependency. **Confirm preferred approach before proceeding.**

**Complexity:** Low | **Risk:** Low-Medium (dependency question pending; isolated service)

---

### [ ] 0. Expand integration test suite

**Goal:** Full integration test coverage before adding new features or fixing bugs. Tests exercise real CLI
subprocesses against temporary scaffolded projects so regressions are caught end-to-end.

**Status: IN PROGRESS — foundational suite created, needs expansion.**

**Done so far:**

| File                                              | Covers                                                                                                  |
| ------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| `tests/Integration/Project/ProjectCreateTest.php` | `oz project create` — full directory/file structure, PHP syntax validity, idempotency                   |
| `tests/Integration/Scopes/ScopesAddTest.php`      | `oz scopes add` — API scope, web scope, multiple scopes, origin persisted, htaccess content, PHP syntax |
| `tests/Integration/Project/ProjectServeTest.php`  | PHP built-in server starts, responds to HTTP, returns OZone JSON                                        |
| `tests/Integration/Settings/SettingsCmdTest.php`  | `oz settings set/unset` — all value types, scope-scoped writes, unset removes key                       |

**Remaining test areas:**

- `oz db build` — ORM class generation from schema
- `oz migrations create/check/run/rollback` — full migration lifecycle
- `oz services generate` — service scaffolding
- Route registration, guard, middleware, form validation (via HTTP in served project)
- Template rendering via web scope
- Auth flows (login, logout, token) via HTTP
- Queue/job dispatch and processing

**Complexity:** Low-Medium | **Risk:** Low (test-only, no production code changes)

---

### [ ] M. `Forms/Form.php` — Multi-step form introspection endpoint

**Current behaviour:** `Form::toArray()` exposes a `has_steps: true` flag when a form has steps, but
cannot return the step fields because steps are dynamic — their fields and visibility depend on the
validation context built from earlier step data.

**Proposed solution:**

1. Add a dedicated route (e.g. `POST /forms/step`) that accepts validated data from the current step and
   returns the serialized next step form — so a web client can progressively reveal field groups.
   Consider a `X-OZ-Form-Step` request header as an alternative activation mechanism.
2. Document the mechanism in `ApiDoc` (item L above).
3. Decide whether deep nested steps (step-within-step) are in scope; if not, validate and throw on
   nesting so it is never accidentally introduced.

**Complexity:** Medium | **Risk:** Low (new isolated route; no changes to validation logic)

---

### [ ] K. `Auth/Auth2FA.php` — Implement two-factor authentication flow

**Current behaviour:** `check2FAAuthProcess()` throws `RuntimeException('User 2FA not yet implemented.')`.

**Proposed solution (outline — full design needs separate discussion):**

1. After successful credential verification, if `$user->has2FAEnabled()`, start an OZAuth authorization flow
   using a new `TwoFactorAuthorizationProvider` (sends code via email or TOTP app).
2. Attach the partially-authenticated state to the session (user ID + awaiting-2FA flag) without fully logging in.
3. The login response returns a `202 Accepted` with the `OZAuth` ref instead of a session token.
4. The client completes the flow via `POST /auth/:ref/authorize` (existing auth endpoint).
5. On successful authorization the provider attaches the user to the auth method and fires `AuthUserLoggedIn`.

> **Note:** This is a significant feature. Approve the outline before we design the implementation in detail.

**Complexity:** High | **Risk:** High (security-critical auth flow)

---

### [x] N. `oz_schema.php` — Schema `api.doc` + `field.*` metadata on all tables/columns ✅

**Current behaviour:** Most columns in `oz_schema.php` have no `api.doc.description` or `field.*` metadata. The `entitySchema()` builder in `ApiDocManipulationTrait` reads neither column `api.doc.description` nor `field.label` when generating OpenAPI property schemas — descriptions are always empty.

**Proposed solution:**

1. Add `field.label` and `api.doc.description` metadata to every non-trivial column in `oz_schema.php`
   (all public tables: `oz_countries`, `oz_files`; all private tables: `oz_roles`, `oz_jobs`, `oz_sessions`,
   `oz_db_stores`, `oz_auths`, `oz_migrations`). Add `api.doc.description` at table level too.
   `oz_users` columns already have metadata via `UsersRepository::makeAuthUserTable()` (staged).
   `oz_usernames.name` already has it.
2. Fix `entitySchema()` in `ApiDocManipulationTrait` to read `api.doc.description` from the column
   metadata and set it as `$schema->description`.

**Complexity:** Low | **Risk:** None (purely additive metadata + one-line fix in entitySchema)

---

### [x] O. `ApiDocManipulationTrait` — `requestBodyFromForm()` + form-aware `addOperationFromRoute()` ✅

**Current behaviour:** No way to auto-generate an OpenAPI `requestBody` from a `Form` object. `addOperationFromRoute()` ignores forms entirely. Field-level labels, descriptions, and help text from the (staged) `Field` API are never surfaced in OpenAPI output.

**Proposed solution:**

1. Add `requestBodyFromForm(Form $form): ?OA\RequestBody` to `ApiDocManipulationTrait` that:
   - Iterates all fields (including hidden ones); hidden fields get an `x-oz-hidden: true` extension.
   - Per-field description fallback: `api.doc.description` metadata → `field.description` → auto-generate from field name via `toHumanReadable()`.
   - Per-field title from `field.label` or auto-generated.
   - Returns `null` when the form has no fields.
2. `addOperationFromRoute()` gains an optional `?Form $form = null` parameter; when provided, the
   generated `requestBody` is attached to the operation automatically.
3. Update all built-in service `apiDoc()` implementations that register a request body to use
   `requestBodyFromForm()` where applicable (Login, SignUp, Password, UploadFiles, etc.).

**Complexity:** Low-Medium | **Risk:** Low (new helper, additive changes to existing methods)

---

### [x] P. `RouteSharedOptions` / `ApiDocManipulationTrait` — Guard descriptors + auth auto-discovery ✅

**Current behaviour:** Guards are stored as opaque closures in `$guards` — their semantic meaning (requires auth user, role, access rights, authorization provider) is invisible at doc-generation time. `addOperationFromRoute()` does not document security requirements or preconditions.

**Analysis:** The `guard()` array allows duplicates (no deduplication). A parallel `$guard_descriptors` array must track descriptors alongside guards without changing how guards are stored or resolved.

**Proposed solution:**

1. Add `protected array $guard_descriptors = []` to `RouteSharedOptions`.
2. Each semantic guard method appends a typed descriptor entry:
   - `withAuthentication(...)` — already stored in `$authentication_methods`; no extra descriptor needed.
   - `withAuthenticatedUser($types)` → `['type' => 'authenticated_user', 'allowed_types' => $types]`
   - `withAuthorization($providers)` → `['type' => 'authorization', 'allowed_providers' => $providers]`
   - `withAccessRights($rights)` → `['type' => 'access_rights', 'rights' => $rights]`
   - `withAccessRightsOrRoles($rights, $roles)` → `['type' => 'access_rights', 'rights' => $rights, 'roles' => $roles]`
   - `withRole($roles)` → `['type' => 'role', 'roles' => $role_names]`, `mustBeAdmin = true`
   - `withRoleOrAdmin($roles)` → `['type' => 'role', 'roles' => $role_names]`, `mustBeAdmin = false`
   - `withAdminRole()` → `['type' => 'role', 'roles' => [Role::ADMIN, Role::SUPER_ADMIN]]`
   - `withSuperAdminRole()` → `['type' => 'role', 'roles' => [Role::SUPER_ADMIN]]`
3. Add `getGuardDescriptors(): array` that merges parent + own descriptors (same as auth methods pattern).
4. In `addOperationFromRoute()`, call `$route->getGuardDescriptors()` and:
   - Add supported `securitySchemes` from `$route->getAuthenticationMethods()`.
   - Add an `x-oz-security` extension on the operation listing guard requirements in structured form.

**Complexity:** Low-Medium | **Risk:** Low (additive: new array + getter, existing guards array unchanged)

---

### [x] Q. `REST/Traits/RESTFulService.php` + `ApiDocManipulationTrait` — Bug fixes + filters docs ✅

**Current behaviour / bugs found:**

1. `delete_one` operationId is `sprintf('%s.create_one', ...)` — wrong, copy-paste error.
2. `$rr_options['relations_parameter']` in `$add_relative_operation` closure references undefined `$rr_options` — should be `$r_relatives_options['relations_parameter']`.
3. `entitySchema()` never sets `$schema->description` from column `api.doc.description`.
4. `apiFiltersParameter()` documents all `Operator` cases globally; does not show per-column available operators.
5. Relation paths all use the same shared path item — each relation should get a distinct path via `path_params_values = ['relation' => $r_name]`.

**Proposed fixes:**

1. Fix `delete_one` operationId: `'%s.delete_one'`.
2. Fix `$rr_options` reference: use `$r_relatives_options` (the correct variable in scope).
3. `entitySchema()`: after `$schema = $this->typeSchema(...)`, read `$column->getMeta()->get('api.doc.description', '')` and set `$schema->description` when non-empty.
4. `apiFiltersParameter(Table $table)`: accept an optional `Table` parameter; when provided, add a per-column section listing each column's allowed operators from `$column->getType()->getAllowedFilterOperators()`. Also document both the string shorthand expression and the nested array expression.
5. Distinct relation paths: pass `path_params_values = ['relation' => $r_name]` to `addOperationFromRoute()` for each relation operation.

**Complexity:** Low | **Risk:** Low (targeted bug fixes + backward-compatible signature extension)

---

## Completed

| ID  | File                              | Notes                                                                                              |
| --- | --------------------------------- | -------------------------------------------------------------------------------------------------- |
| A   | `Cli/Cron/Workers/CronWorker.php` | Stdout/stderr captured from process, returned in `getResult()['output']`, truncated at 64 KB       |
| G   | `Queue/JobsManager.php`           | `workAsync()` spawns subprocess via `symfony/process`; falls back to in-process on failure         |
| H   | `Cache/Drivers/RedisCache.php`    | Fully implemented with `ext-redis`; SCAN+DEL clear; namespaced keys; native TTL                    |
| I   | `FS/Views/GetFilesView.php`       | `applyFilters()` delegates to `FileFilters` + `ImageFileFilterHandler`; resize/crop/quality/format |
| J   | `FS/FilesServer.php`              | Removed; file serving via `GetFilesView` + `RangeResponse::apply()` in `Context::fixResponse()`    |

---

## Summary table

| ID  | File                                               | Complexity | Risk       | Status      |
| --- | -------------------------------------------------- | ---------- | ---------- | ----------- |
| D   | `oz/oz_templates/oz.route.access.grant.form.blate` | Low        | None       | ✅ done     |
| C   | `Auth/Views/LogoutAndRedirectView.php`             | Low        | Low        | ✅ done     |
| F   | `Hooks/MainBootHookReceiver.php`                   | Low        | Low        | pending     |
| B   | `Auth/Methods/SessionAuth.php`                     | Low        | Low        | pending     |
| L   | `REST/ApiDoc.php`                                  | Low        | None       | ✅ done     |
| N   | `oz_schema.php` + `entitySchema()`                 | Low        | None       | ✅ done     |
| O   | `ApiDocManipulationTrait` requestBodyFromForm      | Low-Medium | Low        | ✅ done     |
| P   | `RouteSharedOptions` guard descriptors             | Low-Medium | Low        | ✅ done     |
| Q   | `RESTFulService` bug fixes + filters docs          | Low        | Low        | ✅ done     |
| E   | `Services/QRCode.php`                              | Low        | Low-Medium | pending     |
| 0   | Integration test suite                             | Low-Medium | Low        | in progress |
| M   | `Forms/Form.php`                                   | Medium     | Low        | pending     |
| K   | `Auth/Auth2FA.php`                                 | **High**   | **High**   | pending     |
| A   | `Cli/Cron/Workers/CronWorker.php`                  | Low        | Low        | ✅ done     |
| G   | `Queue/JobsManager.php`                            | Medium     | Low        | ✅ done     |
| H   | `Cache/Drivers/RedisCache.php`                     | Medium     | Low        | ✅ done     |
| I   | `FS/Views/GetFilesView.php`                        | Medium     | Low        | ✅ done     |
| J   | `FS/FilesServer.php`                               | Medium     | Medium     | ✅ done     |
