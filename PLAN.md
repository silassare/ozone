# TODO Resolution Plan

Items are ordered **easiest / safest first, riskiest last** — implementation proceeds top-to-bottom.
Each item must be **explicitly approved** before any code change is made.
Completed items are marked ✅.

---

## Pending

---

### [x] L. `REST/ApiDoc.php` — Complete API doc for all OZone built-in services

**Current behaviour:** `ApiDoc::loadProviders()` iterates over route providers and calls `apiDoc()` only on
those that implement `ApiDocProviderInterface`. Several built-in services have stale, missing, or incomplete
`apiDoc()` implementations — e.g. `SignUp` still hardcodes the request body instead of auto-detecting it
from the registered route's declared form.

**Proposed solution:**
Implement or update `ApiDocProviderInterface::apiDoc()` on every built-in service/view that exposes a public
HTTP API: auth routes (login, logout, password, sign-up), file serving, auth link, API doc spec, QR code, etc.

- Where a route has a declared form, call `requestBodyFromForm()` (already implemented) so the OpenAPI body
  is always derived from the live form definition — no manual field lists.
- `requestBodyFromForm()` must use `toHumanReadable()` as the fallback field label/description when
  `field.label` / `api.doc.description` metadata is absent.
- Where `addOperationFromRoute()` can be called directly (route name is known), use it.
- Audit every existing `apiDoc()` for stale request bodies or parameters that can be auto-derived and replace them.
- Ensure the CSRF endpoint and any other service that currently has no `apiDoc()` is covered.

**Complexity:** Low | **Risk:** None (purely additive — doc generation only)

---

### [x] E. `Services/QRCode.php` — QR code generation

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

## Completed

| ID  | File                                          | Notes                                                                                                                           |
| --- | --------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| D   | `oz/oz_templates/...access.grant.form.blate`  | Access-grant form rendered; `grant_form_ref` hidden; i18n keys added                                                            |
| C   | `Auth/Views/LogoutAndRedirectView.php`        | 200 intermediate page + `Clear-Site-Data` instead of 3xx; `"executionContexts"` dropped                                         |
| F   | `Hooks/MainBootHookReceiver.php`              | Welcome page via `oz.welcome.blate`; `OZ_SHOW_WELCOME_PAGE` config; i18n keys added                                             |
| B   | `Auth/Methods/SessionAuth.php`                | `SessionHijackingDetected` event dispatched; default warning log listener; i18n key added                                       |
| N   | `oz_schema.php` + `entitySchema()`            | `api.doc.description` on all tables/columns; `entitySchema()` reads and sets `$schema->description`                             |
| O   | `ApiDocManipulationTrait` requestBodyFromForm | `requestBodyFromForm(Form)` implemented; hidden fields get `x-oz-hidden`; per-field description from metadata                   |
| P\* | `RouteSharedOptions` guard descriptors        | `$guard_descriptors` + `getGuardDescriptors()`; semantic guard methods append descriptors; `addOperationFromRoute()` reads them |
| Q   | `RESTFulService` bug fixes + filters docs     | `delete_one` operationId fixed; `$rr_options` -> `$r_relatives_options`; per-column filter operators; distinct relation paths   |
| A   | `Cli/Cron/Workers/CronWorker.php`             | Stdout/stderr captured from process, returned in `getResult()['output']`, truncated at 64 KB                                    |
| G   | `Queue/JobsManager.php`                       | `workAsync()` spawns subprocess via `symfony/process`; falls back to in-process on failure                                      |
| H   | `Cache/Drivers/RedisCache.php`                | Fully implemented with `ext-redis`; SCAN+DEL clear; namespaced keys; native TTL                                                 |
| I   | `FS/Views/GetFilesView.php`                   | `applyFilters()` delegates to `FileFilters` + `ImageFileFilterHandler`; resize/crop/quality/format                              |
| J   | `FS/FilesServer.php`                          | Removed; file serving via `GetFilesView` + `RangeResponse::apply()` in `Context::fixResponse()`                                 |

---

## Summary table

| ID  | File                                          | Complexity | Risk       | Status      |
| --- | --------------------------------------------- | ---------- | ---------- | ----------- |
| L   | `REST/ApiDoc.php`                             | Low        | None       | ✅ done     |
| E   | `Services/QRCode.php`                         | Low        | Low-Medium | ✅ done     |
| 0   | Integration test suite                        | Low-Medium | Low        | in progress |
| K   | `Auth/Auth2FA.php`                            | **High**   | **High**   | pending     |
| D   | `oz/oz_templates/...access.grant.form.blate`  | Low        | None       | ✅ done     |
| C   | `Auth/Views/LogoutAndRedirectView.php`        | Low        | Low        | ✅ done     |
| F   | `Hooks/MainBootHookReceiver.php`              | Low        | Low        | ✅ done     |
| B   | `Auth/Methods/SessionAuth.php`                | Low        | Low        | ✅ done     |
| N   | `oz_schema.php` + `entitySchema()`            | Low        | None       | ✅ done     |
| O   | `ApiDocManipulationTrait` requestBodyFromForm | Low-Medium | Low        | ✅ done     |
| P\* | `RouteSharedOptions` guard descriptors        | Low-Medium | Low        | ✅ done     |
| Q   | `RESTFulService` bug fixes + filters docs     | Low        | Low        | ✅ done     |
| A   | `Cli/Cron/Workers/CronWorker.php`             | Low        | Low        | ✅ done     |
| G   | `Queue/JobsManager.php`                       | Medium     | Low        | ✅ done     |
| H   | `Cache/Drivers/RedisCache.php`                | Medium     | Low        | ✅ done     |
| I   | `FS/Views/GetFilesView.php`                   | Medium     | Low        | ✅ done     |
| J   | `FS/FilesServer.php`                          | Medium     | Medium     | ✅ done     |
