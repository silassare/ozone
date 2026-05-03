# REST API — Deferred Tasks

## CLEAN-2: Long-running process singleton management

**Context:** Several REST-related classes hold static singleton or memoized state that is
safe in a traditional PHP-FPM (one process per request) model, but becomes a correctness
hazard under long-running process runtimes (Swoole, ReactPHP, RoadRunner, etc.) where the
same process handles multiple requests.

**Affected locations (identified, not yet fixed):**

- `ApiDoc::$instance` static singleton - stores the OpenAPI spec object; must be reset
  between requests when a single worker handles multiple requests.
- Any other static caches populated during the request lifecycle that are not cleared
  between requests.

**Proposed resolution (deferred to the long-running process support task):**

- Tie singleton/memoized state to the request `Context` lifecycle rather than static fields.
- Leverage `RuntimeCache` (per-request, non-persistent) to hold computed values so they are
  naturally discarded when the request ends.
- Audit all `static` properties in `oz/REST/` and `oz/App/` for lifetime correctness.

**Dependency:** Requires the long-running process support infrastructure to be in place
before this can be addressed cleanly. Track under the "long-running process" work session.
