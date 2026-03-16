# TODO Resolution Plan

Items are ordered **low priority first, high priority last** — implementation proceeds top-to-bottom.
Each item must be **explicitly approved** before any code change is made.
Completed items are marked ✅.

---

## LOW PRIORITY

---

### [ ] A. `Cli/Cron/Workers/CronWorker.php:98` — Read output for command-line tasks

**Current behaviour:** `getResult()` returns a hardcoded `__UNIMPLEMENTED__` string for `output`.

**Proposed solution:**
Capture stdout/stderr of the underlying `symfony/process` run inside `CronWorker` and store it as a
property. Return it from `getResult()['output']`. Truncate at a safe limit (e.g. 64 KB) to avoid
bloating result storage.

**Complexity:** Low | **Risk:** Low

---

### [ ] B. `Auth/Methods/SessionAuth.php:259` — Inform user on session hijacking detection

**Current behaviour:** When a session source-key mismatch is detected for an authenticated user,
a `ForbiddenException` is thrown with no user-visible message beyond the HTTP 403.

**Proposed solution:**
Dispatch a new `SessionHijackingDetected` event (carries `Context` + `Session`) before throwing.
A default listener logs the event at warning level. Application code can listen to send a security
alert email/notification to the user. The exception message already carries a `_reason` field;
add a translatable `OZ_SESSION_HIJACKING_DETECTED` i18n key so the API response body is human-readable.

**Complexity:** Low | **Risk:** Low

---

### [ ] C. `Auth/Views/LogoutAndRedirectView.php:52` — Chrome freeze on `Clear-Site-Data` header

**Current behaviour:** Setting the `Clear-Site-Data` response header can cause Chrome to freeze.

**Proposed solution:**

1. Investigate: the freeze is a known Chrome bug triggered when `Clear-Site-Data: "cache", "cookies", "storage"`
   is sent together with a redirect. Fix by sending the header on a dedicated intermediate response (no redirect body)
   and only then redirecting.
2. Short-term: change the default `OZ_CLEAR_SITE_DATA_HEADER_VALUE` to `"cookies"` only (the minimum needed for
   logout) which avoids the Chrome freeze.
3. Add a note in the settings description about the Chrome limitation.

**Complexity:** Low | **Risk:** Low

---

### [ ] D. `oz_templates/oz.route.access.grant.form.blate` — Render access-grant form

**Current behaviour:** Template is empty (`<!-- TODO - add render the form -->`).

**Proposed solution:**
Render a minimal HTML form that posts the authorization credentials back to
`POST /auth/:ref/authorize`. Fields to include: CSRF token, any dynamic fields
injected by the authorization provider (e.g. code input for email/phone verification).
Style with inline CSS only (no external assets) so it works out-of-the-box without any theme.

**Complexity:** Low | **Risk:** Low

---

### [ ] E. `Services/QRCode.php:89` — QR code generation

**Current behaviour:** QR generation is commented out; the route returns an empty PNG body.

**Proposed solution:**
Add `endroid/qr-code` (pure PHP, no `ext-imagick` required) to `composer.json` and generate the
PNG in-memory, writing it to the `$file` tmpfile handle already created. Alternatively, if adding a
dependency is undesirable, use a bundled pure-PHP QR encoder. **Confirm preferred approach before proceeding.**

**Complexity:** Low | **Risk:** Low (dependency question pending)

---

### [ ] F. `Hooks/MainBootHookReceiver.php:75` — Welcome page for web context root

**Current behaviour:** Accessing the root URL in web context throws `ForbiddenException`.

**Proposed solution:**
Add a minimal Blate template `oz://oz_templates/oz.welcome.blate` rendered as a `WebView` — shows
the project name, OZone version, and (if API doc is enabled) a link to the Swagger UI.
The template is only served when the project is in non-production mode
(`OZ_APP_ENV !== 'production'`); production keeps the `ForbiddenException`.
Add `OZ_SHOW_WELCOME_PAGE` boolean to `oz.config` (default `true` in dev, `false` in prod).

**Complexity:** Low | **Risk:** Low

---

## MEDIUM PRIORITY

---

### [ ] G. `Queue/JobsManager.php:262` — Async job via background process

**Current behaviour:** `workAsync()` calls `$worker->work($job_contract)` in-process, blocking the caller.

**Proposed solution:**
Dispatch a detached `symfony/process` subprocess that runs `oz queue:work --job=<id>` (a new CLI command),
passing the serialised job contract ID. The main process returns immediately.
Fall back to the current in-process call if the project is not CLI-capable or the subprocess fails to start.
Add a `oz.queue` settings key `OZ_QUEUE_ASYNC_ENABLED` (default `false`) so the behaviour is opt-in.

**Complexity:** Medium | **Risk:** Low (opt-in)

---

### [ ] H. `Cache/Drivers/RedisCache.php` — Implement Redis cache driver

**Current behaviour:** Stub class — all methods are no-ops returning `null`/`false`/`[]`.

**Proposed solution:**
Implement using the native `ext-redis` PHP extension (`Redis` class), with a fallback check and a clear
error if the extension is absent. The constructor accepts `string $namespace` and optionally a DSN/config
pulled from `oz.cache` settings (`OZ_REDIS_DSN`, `OZ_REDIS_PREFIX`). Follow the exact same pattern as
`MemcachedCache.php` (shared instance via `getSharedInstance()`, TTL from `CacheItem::getExpiresAt()`).
No new Composer dependency needed since `ext-redis` is a PECL extension.

**Complexity:** Medium | **Risk:** Low (new driver, no existing code changes)

---

### [ ] I. `FS/Views/GetFilesView.php:158` — Finish `applyFilters()` implementation

**Current behaviour:** `applyFilters()` returns the response unchanged; filter logic is commented out.

**Proposed solution:**
Parse the filter string (split on `FS::FILTERS_SEPARATOR`), apply each recognised filter in order:

- `resize:WxH` — resize image via `claviska/simpleimage`.
- `crop:WxH` — crop to dimensions.
- `quality:N` — JPEG quality 1–100.
- `format:jpeg|png|webp` — transcode.

Unknown filters are silently ignored. Non-image files skip filtering entirely.
Return the modified response body with an updated `Content-Type` where applicable.

**Complexity:** Medium | **Risk:** Low

---

### [ ] J. `FS/FilesServer.php` — Full rewrite (TODOs at lines 62 & 125)

**Current behaviour:** `FilesServer` is already marked `@deprecated`. It uses raw `fread` loops,
manual header manipulation, and `ob_*` calls. `startDownloadServer()` is a legacy `never`-returning
static function.

**Proposed solution:**
Replace both methods with a new `FileStream`-based approach already partly in place in
`FS/Views/GetFilesView.php`:

- `serve()` -> delegate to `GetFilesView` (or a new `FS::serve()` static helper).
- `startDownloadServer()` -> emit a proper PSR-7 `Response` with a `StreamInterface` body
  (`Body::fromPath()`) and `Content-Disposition: attachment` / range headers, then remove the
  `never` return type.
- Add optional nginx `X-Accel-Redirect` / Apache `X-Sendfile` support behind a settings flag.
- Keep the deprecated class as a thin shim delegating to the new API for backwards compatibility.

**Complexity:** Medium | **Risk:** Medium

---

## HIGH PRIORITY

---

### [ ] K. `Auth/Auth2FA.php:48` — Implement two-factor authentication flow

**Current behaviour:** `check2FAAuthProcess()` throws `RuntimeException('User 2FA not yet implemented.')`.

**Proposed solution (outline — full design needs separate discussion):**

1. After successful credential verification, if `$user->has2FAEnabled()`, start an OZAuth authorization flow
   using a new `TwoFactorAuthorizationProvider` (sends code via email or TOTP app).
2. Attach the partially-authenticated state to the session (user ID + awaiting-2FA flag) without fully logging in.
3. The login response returns a `202 Accepted` with the `OZAuth` ref instead of a session token.
4. The client completes the flow via `POST /auth/:ref/authorize` (existing auth endpoint).
5. On successful authorization the provider attaches the user to the auth method and fires `AuthUserLoggedIn`.

> **Note:** This is a significant feature. Approve the outline before we design the implementation in detail.

**Complexity:** High | **Risk:** High

---

## Summary table

| ID  | File                                            | Complexity | Risk               | Status  |
| --- | ----------------------------------------------- | ---------- | ------------------ | ------- |
| A   | `Cli/Cron/Workers/CronWorker.php`               | Low        | Low                | pending |
| B   | `Auth/Methods/SessionAuth.php`                  | Low        | Low                | pending |
| C   | `Auth/Views/LogoutAndRedirectView.php`          | Low        | Low                | pending |
| D   | `oz_templates/oz.route.access.grant.form.blate` | Low        | Low                | pending |
| E   | `Services/QRCode.php`                           | Low        | Low (dep question) | pending |
| F   | `Hooks/MainBootHookReceiver.php`                | Low        | Low                | pending |
| G   | `Queue/JobsManager.php`                         | Medium     | Low (opt-in)       | pending |
| H   | `Cache/Drivers/RedisCache.php`                  | Medium     | Low (new driver)   | pending |
| I   | `FS/Views/GetFilesView.php`                     | Medium     | Low                | pending |
| J   | `FS/FilesServer.php`                            | Medium     | Medium             | pending |
| K   | `Auth/Auth2FA.php`                              | **High**   | **High**           | pending |
