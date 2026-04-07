# ResumableFormService — Remaining Tasks / Backlog

## A. Integration Tests — Remaining Scenarios

`tests/Integration/Forms/ResumableFormServiceTest.php` exists and covers the happy path and
basic error cases. The following scenarios are not yet tested:

- `nextStep` on INIT session: invalid init-form data -> response `error=1` (form validation failure)
- `nextStep`: wrong `scope_id` (spoofed resume_ref from a different host/user) -> `error=1` (`ForbiddenException`)
- `nextStep`: unknown `resume_ref` (never existed or already dropped) -> `error=1` (`NotFoundException`)
- `requireCompletion`: done session -> returns `FormData` with all collected values
- `requireCompletion`: not-done session -> `error=1` (`ForbiddenException`)
- `isReversible=false`: `POST /back` -> `error=1` (`BadRequestException`)
- `notBefore()` in future -> `POST /init` returns `error=1` (`FormResumeNotYetActiveException`)
- `deadline()` in past -> any handler returns `error=1` (`FormResumeExpiredException`)
- `dropSession`: after `checkRouteForm` loads session, ref is invalidated and subsequent
  `GET /state` returns `error=1` (`NotFoundException`)

A second stub provider (`TestFormIrreversibleProvider`) with `isReversible=false`,
`notBefore()`, and `deadline()` support will be needed for these cases.

## D. Session Abandonment / Expiry Hooks

**Blocked on: cache system expiry-callback support (upcoming task).**

**Problem:**
When a resumable form session is abandoned (no request ever completes or cancels it), the
cache entry silently disappears after its TTL. There is currently no mechanism to notify the
provider (`onAbandon()`) when this happens — to release resources, update upstream state, or
log the abandonment.

The `deadline()` / `notBefore()` methods and `FormResumeExpiredException` already handle the
_active-request_ expiry path (request arrives after deadline -> throws). D is specifically
about the _passive_ path: the session dies in cache with no incoming request.

**What is needed from the cache system (to be provided):**

- An expiry callback / eviction hook so that when a persistent cache key expires, a
  registered callable is invoked with the key and the last-known value.
- OR: a queryable TTL API so a cron/GC task can identify sessions approaching expiry
  and call `provider->onAbandon($session)` before the key is gone.

**Plan (once cache work is done):**

1. Add `onAbandon(array $session): void` to `ResumableFormProviderInterface` (default no-op
   in `AbstractResumableFormProvider`).
2. Wire the cache expiry hook in `ResumableFormService` to call `onAbandon` when a session
   entry expires without being completed or cancelled.
3. Add integration tests:
   - `onAbandon` is called when session TTL elapses (requires cache driver that supports
     expiry callbacks in tests, or a forced-expire helper).
   - `onAbandon` is NOT called when session is explicitly cancelled (cancel calls
     `dropSession` -> immediate delete, not TTL expiry).
