# TODO Resolution Plan

Items are ordered **easiest / safest first, riskiest last** — implementation proceeds top-to-bottom.
Each item must be **explicitly approved** before any code change is made.
Completed items are marked ✅.

---

## Pending

---

### [ ] D. `oz/oz_templates/oz.route.access.grant.form.blate` — Render access-grant form

**Current behaviour:** Template contains only `<!-- TODO - add render the form -->`.

**Proposed solution:**
Render a minimal HTML form that posts the authorization credentials back to
`POST /auth/:ref/authorize`. Fields: CSRF token + any dynamic fields injected by the authorization
provider (e.g. code input for email/phone verification). Inline CSS only — no external assets.

**Complexity:** Low | **Risk:** None (template-only, no PHP changes)

---

### [ ] C. `Auth/Views/LogoutAndRedirectView.php` — Chrome freeze on `Clear-Site-Data` header

**Current behaviour:** Setting the `Clear-Site-Data` response header can cause Chrome to freeze.

**Proposed solution:**

1. Change the default `OZ_CLEAR_SITE_DATA_HEADER_VALUE` to `"cookies"` only (minimum needed for
   logout) — avoids the Chrome freeze immediately.
2. Add a note in the settings description about the Chrome limitation.
3. Long-term fix: send the header on a dedicated intermediate response (no redirect body), then redirect.

**Complexity:** Low | **Risk:** Low (settings default change + comment)

---

### [ ] F. `Hooks/MainBootHookReceiver.php` — Welcome page for web context root

**Current behaviour:** Accessing the root URL in web context throws `ForbiddenException`.

**Proposed solution:**
Add a minimal Blate template `oz://oz_templates/oz.welcome.blate` rendered as a `WebView` — shows
the project name, OZone version, and (if API doc is enabled) a link to the Swagger UI.
Served only when `OZ_APP_ENV !== 'production'`; production keeps the `ForbiddenException`.
Add `OZ_SHOW_WELCOME_PAGE` boolean to `oz.config` (default `true` in dev, `false` in prod).

**Complexity:** Low | **Risk:** Low (new template + route + settings key, isolated path)

---

### [ ] B. `Auth/Methods/SessionAuth.php` — Inform user on session hijacking detection

**Current behaviour:** When a session source-key mismatch is detected for an authenticated user,
a `ForbiddenException` is thrown with no user-visible message beyond the HTTP 403.

**Proposed solution:**
Dispatch a new `SessionHijackingDetected` event (carries `Context` + `Session`) before throwing.
A default listener logs the event at warning level. Application code can listen to send a security
alert email/notification to the user. Add a translatable `OZ_SESSION_HIJACKING_DETECTED` i18n key
so the API response body is human-readable.

**Complexity:** Low | **Risk:** Low (new event class + small SessionAuth change + i18n key)

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

| ID | File                                               | Notes                                                                  |
| -- | -------------------------------------------------- | ---------------------------------------------------------------------- |
| A  | `Cli/Cron/Workers/CronWorker.php`                  | Stdout/stderr captured from process, returned in `getResult()['output']`, truncated at 64 KB |
| G  | `Queue/JobsManager.php`                            | `workAsync()` spawns subprocess via `symfony/process`; falls back to in-process on failure |
| H  | `Cache/Drivers/RedisCache.php`                     | Fully implemented with `ext-redis`; SCAN+DEL clear; namespaced keys; native TTL |
| I  | `FS/Views/GetFilesView.php`                        | `applyFilters()` delegates to `FileFilters` + `ImageFileFilterHandler`; resize/crop/quality/format |
| J  | `FS/FilesServer.php`                               | Removed; file serving via `GetFilesView` + `RangeResponse::apply()` in `Context::fixResponse()` |

---

## Summary table

| ID  | File                                               | Complexity | Risk                | Status      |
| --- | -------------------------------------------------- | ---------- | ------------------- | ----------- |
| D   | `oz/oz_templates/oz.route.access.grant.form.blate` | Low        | None                | pending     |
| C   | `Auth/Views/LogoutAndRedirectView.php`             | Low        | Low                 | pending     |
| F   | `Hooks/MainBootHookReceiver.php`                   | Low        | Low                 | pending     |
| B   | `Auth/Methods/SessionAuth.php`                     | Low        | Low                 | pending     |
| E   | `Services/QRCode.php`                              | Low        | Low-Medium          | pending     |
| 0   | Integration test suite                             | Low-Medium | Low                 | in progress |
| K   | `Auth/Auth2FA.php`                                 | **High**   | **High**            | pending     |
| A   | `Cli/Cron/Workers/CronWorker.php`                  | Low        | Low                 | ✅ done     |
| G   | `Queue/JobsManager.php`                            | Medium     | Low                 | ✅ done     |
| H   | `Cache/Drivers/RedisCache.php`                     | Medium     | Low                 | ✅ done     |
| I   | `FS/Views/GetFilesView.php`                        | Medium     | Low                 | ✅ done     |
| J   | `FS/FilesServer.php`                               | Medium     | Medium              | ✅ done     |
