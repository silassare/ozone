# PLAN: FormService - Incremental / Resumable Form Submission

## Goals

Add server-side orchestration for multi-step, session-resumable form flows where:

- Each step form can depend on previously validated answers (QCM, surveys, wizards)
- All session state lives in the persistent cache; clients carry only an opaque `resume_ref`
- Abandonment (cancel), in-progress retrieval (state), and step submission (next) are all explicit
- After the final step the accumulated `FormData` is available for a downstream action via a static helper

---

## Files

### Create / Move

| Path                                                         | Status  | Purpose                                       |
| ------------------------------------------------------------ | ------- | --------------------------------------------- |
| `oz/Forms/Interfaces/ResumableFormProviderInterface.php`     | done    | Contract for form sequence providers          |
| `oz/Forms/Interfaces/FieldContainerInterface.php`            | pending | Shared contract for `Form` and `Fieldset`     |
| `oz/Forms/AbstractResumableFormProvider.php`                 | done    | Registry + defaults (initForm=null, ttl=3600) |
| `oz/Forms/Fieldset.php` _(renamed from `FormStep.php`)_      | pending | Conditional named group of fields             |
| `oz/Forms/Services/FormService.php` _(moved from Services/)_ | pending | REST service with 6 routes                    |
| `tests/Forms/ResumableFormProviderTest.php`                  | done    | Unit tests for registry + interface           |
| `tests/Services/FormServiceTest.php`                         | done    | Unit tests for all 6 handlers                 |
| `tests/Forms/FieldsetTest.php`                               | pending | Unit tests for Fieldset                       |

### Modify

| Path                                             | Change                                                                    |
| ------------------------------------------------ | ------------------------------------------------------------------------- |
| `oz/oz_settings/oz.routes.api.php`               | Update class reference after FormService move                             |
| `oz/Forms/Form.php`                              | step()->fieldset(), dynamicStep()->dynamicFieldset(), etc.                |
| `oz/Forms/Field.php`                             | `$t_form: Form` -> `$t_parent: FieldContainerInterface`                   |
| `oz/Forms/Traits/FieldContainerHelpersTrait.php` | No change — already delegates to `$this->field()` which both classes have |
| `tests/Forms/FormTest.php`                       | Update step/dynamicStep references to fieldset/dynamicFieldset            |

---

## Pending Renames

These are not yet applied to code. Do not implement until reviewed.

### 1. `FormService` namespace move

- **From**: `OZONE\Core\Services\FormService` (`oz/Services/FormService.php`)
- **To**: `OZONE\Core\Forms\Services\FormService` (`oz/Forms/Services/FormService.php`)

Keeps all form-related classes co-located under `oz/Forms/`. All constants (`SESSION_CACHE_NAMESPACE`,
`STEP_INDEX_KEY`, `ROUTE_*`), logic, and tests remain the same - only the namespace and file path change.

Affected files after the move: `oz/oz_settings/oz.routes.api.php`, `tests/Services/FormServiceTest.php`.

### 2. Method renames on `ResumableFormProviderInterface` (and `AbstractResumableFormProvider`)

| Current name   | New name      | Rationale                                                                                           |
| -------------- | ------------- | --------------------------------------------------------------------------------------------------- |
| `sessionScope` | `resumeScope` | "session" implies auth session; "resume" aligns with `Form::resumable()` / `Form::getResumeScope()` |
| `sessionTTL`   | `resumeTTL`   | Same alignment reason; also matches `Form::getResumeTTL()`                                          |

The associated `SESSION_CACHE_NAMESPACE` constant on `FormService` stays as-is (it names the cache bucket, not a
method). The stored session key `scope_id` in the session array also stays as-is (it is a storage detail).

### 3. `nextForm` → `nextStep` on `ResumableFormProviderInterface`

`nextForm()` is renamed to `nextStep()`. The old name was ambiguous (it returns a `Form` _for_ the next
step, but sounds like it _advances_ state). `nextStep()` better conveys the provider's role: "given what
we know so far, what form describes the next step?"

Affected files: `oz/Forms/Interfaces/ResumableFormProviderInterface.php`, `oz/Forms/AbstractResumableFormProvider.php`,
`oz/Forms/Services/FormService.php`, `tests/Services/FormServiceTest.php`.

### Relationship: `Form::resumable()` vs. provider `resumeScope()`/`resumeTTL()`

These are two distinct but related concepts operating at different levels:

| Concept                              | Level   | What it caches                                      | Scope source                    |
| ------------------------------------ | ------- | --------------------------------------------------- | ------------------------------- |
| `Form::resumable(scope, ttl)`        | field   | Per-field validated values within **one** form step | Caller explicitly sets it       |
| Provider `resumeScope()/resumeTTL()` | session | Entire FormService session across **all** steps     | Provider declares its own scope |

A `Form` can have `resumable()` enabled _inside_ a FormService step - in that case fields within that step
survive individual HTTP requests to `POST /.../next`. The provider's `resumeScope()` governs who owns the
_entire multi-step session_.

For `RouteResumableFormProvider` (see below) the provider should derive its `resumeScope()` and `resumeTTL()`
from the wrapped form's `getResumeScope()` and `getResumeTTL()` when they are set.

### 3. `FormStep` -> `Fieldset` and related renames

**Rationale**: `FormStep` conflates two unrelated concepts — a `FormService` step (one HTTP round-trip)
and a conditional field group within a single form validation pass. `Fieldset` matches the HTML
`<fieldset>`/`<legend>` semantic exactly: a named, optionally conditional group of fields inside
a single `<form>` that is validated in one pass.

#### Rename table

| Before                        | After                                |
| ----------------------------- | ------------------------------------ |
| `FormStep` (class, file)      | `Fieldset`                           |
| `Form::step()`                | `Form::fieldset()`                   |
| `Form::dynamicStep()`         | `Form::dynamicFieldset()`            |
| `Form::getStep()`             | `Form::getFieldset()`                |
| `Form::getSteps()`            | `Form::getFieldsets()`               |
| `$t_steps` (property on Form) | `$t_fieldsets`                       |
| `toArray()` key `steps`       | `fieldsets` _(breaking wire change)_ |
| `FormStep::static()`          | `Fieldset::static()`                 |
| `FormStep::dynamic()`         | `Fieldset::dynamic()`                |
| `validate(..., $skip_steps)`  | `validate(..., $shallow)`            |

#### `Fieldset` no longer wraps a `Form` — it holds `Field` objects directly

**Key change**: the wrapped `Form` inside `FormStep` is replaced. `Fieldset` owns its own fields
and `ensure()` rules. This prevents infinite nesting (a `Fieldset` cannot contain another `Fieldset`)
and maps cleanly to one level of grouping.

**`Fieldset` members:**

| Member                           | Type                       | Purpose                                              |
| -------------------------------- | -------------------------- | ---------------------------------------------------- |
| `$t_name`                        | `string`                   | Always required (unlike Form where name is optional) |
| `$t_legend`                      | `?I18nMessage`             | Display label — `->legend(string\|I18nMessage)`      |
| `$t_fields`                      | `array<string, Field>`     | Its own fields                                       |
| `$t_post_validation_rules`       | `list<RuleSet>`            | From `ensure()` — cross-field checks within fieldset |
| `$t_if`                          | `?RuleSet`                 | Condition for the whole fieldset                     |
| static/dynamic variant           | same storage as `FormStep` | Static form or dynamic factory                       |
| `use FieldContainerHelpersTrait` | —                          | All typed helpers (`email()`, `string()`, etc.)      |

**`Fieldset` does NOT have:** `expect()`, `resumable()`, `fieldset()`, `dynamicFieldset()`.

#### `FieldContainerInterface` — shared contract

Introduced so `Field::$t_parent` can be typed without a union:

```php
namespace OZONE\Core\Forms\Interfaces;

interface FieldContainerInterface
{
    public function field(string $name): Field;
    public function ensure(RuleSetCondition $condition = RuleSetCondition::AND): RuleSet;
    public function getRef(string $name): string;
}
```

Both `Form` and `Fieldset` implement this interface.

`Field::$t_form: Form` becomes `Field::$t_parent: FieldContainerInterface`.

`Field::doubleCheck()` then becomes interface-driven — calls `$this->t_parent->field()` and
`$this->t_parent->ensure()` with no knowledge of whether it's a `Form` or `Fieldset`.

**`ensure()` scope**: when `doubleCheck()` is called on a field inside a `Fieldset`, the `eq`
rule registers on the `Fieldset`, not on the parent `Form`. The `Form::validate()` loop calls
`$fieldset->validate($unsafe_fd, $cleaned_fd)` which runs the fieldset's own ensure rules after
validating its own fields. Self-contained and correct.

#### `Fieldset` callback style — Option A (approved)

`Form::fieldset()` takes a **mutate-in-place callback** `callable(Fieldset):void`. The `Fieldset` is
created internally by `Form::fieldset()` and passed to the callback; the caller never calls `new Fieldset()`
directly for static fieldsets. The fieldset name is always set by `Form::fieldset()` itself.

`Form::dynamicFieldset()` takes a **factory** `callable(FormData):Fieldset` — the caller constructs and
returns the `Fieldset` (with fields already added) because field composition depends on runtime data.

```php
// Static fieldset — mutate-in-place callback
$form->fieldset('address', function (Fieldset $fs) {
    $fs->string('street')->required(true);
    $fs->string('city')->required(true);
})->legend('Shipping Address');

// Dynamic fieldset — factory receives accumulated FormData
$form->dynamicFieldset('extras', function (FormData $fd): Fieldset {
    $fs = new Fieldset('extras');
    if ($fd->get('type') === 'company') {
        $fs->string('vat_number')->required(true);
    }
    return $fs;
});
```

---

## Interface: `ResumableFormProviderInterface`

```php
namespace OZONE\Core\Forms\Interfaces;
{
    // Unique slug identifying this provider type (used as the :provider URL segment)
    public static function providerRef(): string;

    // Optional pre-flight form shown before the main steps begin.
    // Return null to skip straight to the first nextStep() call.
    public function initForm(): ?Form;

    // Return the Form the client must fill for the next step, given accumulated progress.
    // Return null when the sequence is complete (no more steps).
    // Contract: must be deterministic - same $progress always yields the same Form structure.
    // Do NOT return Forms with internal fieldset()s that encode separate screens;
    // prefer returning flat Forms and driving branching via nextStep() itself.
    public function nextStep(FormData $progress): ?Form;

    // How long the session cache entry lives from its creation time (seconds).
    // When deadline() is also set, the effective TTL is min(resumeTTL(), deadline()-now).
    // (pending rename from sessionTTL)
    public function resumeTTL(): int;

    // Scope strategy tying session cache entries to a specific principal.
    // (pending rename from sessionScope)
    public function resumeScope(): RequestScope;

    // Known total step count (null = variable / unknown at creation time).
    // "Step" = one nextStep() + POST .../next round-trip. Does NOT count internal Form fieldsets.
    // When non-null, every FormService response includes a progress object { step, total_steps }.
    // When null, progress is omitted from the response (client knows flow is variable).
    // Default implementation returns null.
    public function totalSteps(): ?int;

    // Whether the client may go back to a previous step.
    // When true, FormService maintains a history of progress snapshots and exposes
    // POST /.../back. When false (default), the back route throws BadRequestException.
    public function isReversible(): bool;

    // Earliest moment at which a new session may be started. Return null for no restriction.
    // FormService::initSession() throws SessionNotYetActiveException when now() < notBefore().
    // Useful for exam / appointment flows where the form must not open early.
    public function notBefore(): ?\DateTimeImmutable;

    // Hard deadline: the session auto-expires at this moment regardless of resumeTTL().
    // Return null for no hard deadline (TTL is the only expiry).
    // FormService stores expires_at = min(created_at + resumeTTL(), deadline) in the session.
    // On every request: if now() > expires_at, throws FormResumeExpiredException.
    // Every response includes expires_at (Unix timestamp) so clients can show countdowns.
    // Useful for timed exams, limited-time surveys, etc.
    public function deadline(): ?\DateTimeImmutable;
}
```

**Notes on `totalSteps()` and flattening:**

FormService does NOT split a `Form`'s internal `step()` sub-steps into separate HTTP round-trips.
Internal steps are treated as part of one screen and validated in one `Form::validate()` call.
If a provider wants a genuinely separate page, it should return a separate form from `nextForm()`.

This means `totalSteps()` reflects the number of `nextStep()` round-trips, not internal fieldsets.
Providers with deterministic branching graphs should calculate and return this count; providers
with conditional branching where the count depends on user choices should return `null`.

When `totalSteps()` is `null`, the `progress` key is omitted from formService responses — the
client knows the flow is variable and no meaningful "N of M" can be reported.

**Notes on timing (`notBefore` + `deadline`):**

- `notBefore` and `deadline` are evaluated against the server clock at the moment of the request.
- Both can be null independently (e.g., a deadline-only exam with no open-from restriction).
- When both are set, `deadline` must be strictly after `notBefore`; providers are expected
  to validate this at construction time.
- `expires_at` in the session and every response is a Unix timestamp (int), or `null` when
  no deadline is configured.

---

## Abstract Base: `AbstractResumableFormProvider`

| Responsibility           | Implementation                                                                                                                   |
| ------------------------ | -------------------------------------------------------------------------------------------------------------------------------- |
| Static registry          | `private static array $registry = []` keyed by `providerRef()`                                                                   |
| Register                 | `public static function register(string $class): void` - calls `$class::providerRef()` to derive the key                         |
| Resolve                  | `public static function resolve(string $ref): ResumableFormProviderInterface` - throws `NotFoundException` for unregistered refs |
| Default `initForm()`     | returns `null`                                                                                                                   |
| Default `resumeTTL()`    | returns `3600` (pending rename from `sessionTTL()`)                                                                              |
| Default `resumeScope()`  | returns `RequestScope::STATE` (pending rename from `sessionScope()`)                                                             |
| Default `totalSteps()`   | returns `null` (variable / unknown)                                                                                              |
| Default `isReversible()` | returns `false`                                                                                                                  |
| Default `notBefore()`    | returns `null` (no restriction)                                                                                                  |
| Default `deadline()`     | returns `null` (TTL-only expiry)                                                                                                 |

---

## FormService Routes

| Method | Path                            | Handler             | Route name         |
| ------ | ------------------------------- | ------------------- | ------------------ |
| POST   | `/form/:provider/init`          | `initSession()`     | `oz:form:init`     |
| GET    | `/form/:provider/:ref/state`    | `getState()`        | `oz:form:state`    |
| POST   | `/form/:provider/:ref/next`     | `nextStep()`        | `oz:form:next`     |
| POST   | `/form/:provider/:ref/back`     | `backStep()`        | `oz:form:back`     |
| POST   | `/form/:provider/:ref/cancel`   | `cancelSession()`   | `oz:form:cancel`   |
| POST   | `/form/:provider/:ref/evaluate` | `evaluateCurrent()` | `oz:form:evaluate` |

Path parameter constraints:

- `:provider` - default `[^/]+` (slug may contain colons and hyphens)
- `:ref` - constrained to `[0-9a-f]{32}` so it can never shadow the literal `init` segment

---

## Session Cache Schema

Cache namespace: `'oz.form.sessions'`
Cache key: `$resume_ref` (32-char hex, generated by `Keys::id32('form.session')`)

```php
[
    'provider_ref' => string,   // e.g. 'quiz:geo' (cross-checked on every request)
    'provider_cls' => string,   // FQN - used by resolve() to reconstruct the provider
    'phase'        => string,   // 'init' | 'steps' | 'done'
    'progress'     => array,    // FormData::toArray() - accumulated validated fields
    'scope_id'     => string,   // provider->resumeScope()->resolveId($context) at creation time
    'created_at'   => int,      // Unix timestamp of session creation
    'expires_at'   => int|null, // Unix timestamp of hard deadline, null when TTL-only
    'history'      => array,    // list of previous progress snapshots (when isReversible())
                                // each entry: ['progress' => array] - older entries first
]
```

**Phases:**

| Phase   | Meaning                                        | Current form                     |
| ------- | ---------------------------------------------- | -------------------------------- |
| `init`  | `initForm()` is non-null and not yet submitted | `$provider->initForm()`          |
| `steps` | Stepping through `nextStep()` sequence         | `$provider->nextStep($progress)` |
| `done`  | `nextStep($progress)` returned null            | `null` (no more steps)           |

The current form is always **re-derived** from the cached `$progress` — it is never stored.
This keeps the cache lean and means the provider only needs to be deterministic.

**`history` invariants (when `isReversible()` is true):**

- After step 0 is submitted successfully, `history = [step0_snapshot]`.
- Each successful `nextStep()` pushes the pre-advance snapshot before updating `progress`.
- `backStep()` pops the last snapshot and restores `progress` to it (and decrements `STEP_INDEX_KEY`).
- `history` is always `[]` when `isReversible()` is `false`.

---

## Standard Response Shape

Every FormService response (except `cancel`) includes:

```json
{
    "done":       false,
    "resume_ref": "a3f...",
    "form":       { ... },      // null when done
    "expires_at": 1700003600,   // Unix timestamp or null
    "progress":   {             // omitted entirely when provider.totalSteps() === null
        "step":        2,       // 1-indexed (STEP_INDEX_KEY + 1)
        "total_steps": 10       // provider.totalSteps()
    }
}
```

When `done: true`, `form` is `null` and `progress.step === progress.total_steps` (when present).

---

## Handler Logic

### `POST /form/:provider/init`

```
1. resolve(:provider) -> $provider           NotFoundException if unregistered
2. if provider->notBefore() != null && now() < notBefore(): throw FormResumeNotYetActiveException
3. scope_id = provider->resumeScope()->resolveId($context)
4. $progress = new FormData()
5. $init_form = $provider->initForm()
6. if $init_form != null:
       validate request body against $init_form -> $init_data
       $progress = $init_data
7. progress->set(STEP_INDEX_KEY, 0)
8. $next = $provider->nextStep($progress)
9. $phase = ($next === null) ? 'done' : 'steps'
10. expires_at = provider->deadline() ? min(time()+resumeTTL(), deadline->timestamp) : null
11. resume_ref = Keys::id32('form.session')
12. Write session:
        provider_ref, provider_cls, phase, progress, scope_id,
        created_at, expires_at, history: []
    TTL = provider->resumeTTL()
13. Return standard response
```

### `GET /form/:provider/:ref/state`

```
1. Load session by :ref                            NotFoundException if not found / expired
2. if expires_at != null && now() > expires_at:    throw FormResumeExpiredException
3. Verify scope_id matches                         ForbiddenException on mismatch
4. Verify session['provider_ref'] == :provider     ForbiddenException on mismatch
5. Re-derive current form from phase + progress
6. Return standard response
```

### `POST /form/:provider/:ref/next`

```
1. Load session, verify ownership + provider       NotFoundException / ForbiddenException
2. if expires_at != null && now() > expires_at:    throw FormResumeExpiredException
3. Abort if phase === 'done'                       BadRequestException
4. $current_form = deriveCurrentForm(phase, provider, progress)
5. Validate request body against $current_form -> $validated
6. Increment STEP_INDEX_KEY in $validated
7. if provider->isReversible():
       push current progress snapshot onto history
8. $next = $provider->nextStep($validated)
9. $phase = ($next === null) ? 'done' : 'steps'
10. Update session cache: phase, progress=$validated, history
11. Return standard response
```

### `POST /form/:provider/:ref/back`

```
1. Load session, verify ownership + provider
2. if expires_at != null && now() > expires_at:    throw FormResumeExpiredException
3. if !provider->isReversible():                   throw BadRequestException('OZ_FORM_NOT_REVERSIBLE')
4. if history is empty:                            throw BadRequestException('OZ_FORM_NO_PREVIOUS_STEP')
5. Pop last snapshot from history -> $prev_progress
6. Restore session: progress = $prev_progress, phase = 'steps', history = history[:-1]
7. Re-derive current form from restored progress
8. Return standard response
```

### `POST /form/:provider/:ref/cancel`

```
1. Load session, verify ownership + provider
2. Delete session from cache
3. Return { done: true }
```

### `POST /form/:provider/:ref/evaluate`

```
1. Load session, verify ownership
2. if expires_at != null && now() > expires_at:    throw FormResumeExpiredException
3. Abort if phase === 'done'                       BadRequestException
4. Derive current form from phase
5. eval_data = merge(session.progress, request.body)
6. For each field where getIf() != null && getIf()->isServerOnly():
       visibility[field.ref] = field->isEnabled(eval_data)
7. For each expect rule where isServerOnly():
       passes = rule->check(eval_data)
       expect_results[] = { index, passes, message }
8. Return { visibility: {field_ref: bool}, expect: [{index, passes, message}] }
```

---

## New Exceptions (pending)

| Class                             | HTTP | When                                           |
| --------------------------------- | ---- | ---------------------------------------------- |
| `FormResumeNotYetActiveException` | 403  | `notBefore()` is set and `now() < notBefore()` |
| `FormResumeExpiredException`      | 410  | `expires_at` is set and `now() > expires_at`   |

`FormResumeExpiredException` uses 410 Gone (the resource existed but is no longer available)
rather than 404 (which would imply it never existed). Clients can distinguish expiry from a
bad ref. The `FormResume` prefix makes it clear these relate to the resumable form token
lifecycle, not to `OZONE\Core\Sessions\Session`.

---

## `FormService::requireCompletion()` (static helper)

```php
public static function requireCompletion(
    string $provider_ref,
    string $resume_ref,
    Context $context
): FormData
```

- Loads session, verifies `scope_id` ownership, verifies `provider_ref` matches
- Requires `phase === 'done'`; throws `ForbiddenException` otherwise
- Returns the accumulated `FormData`
- Intentionally does NOT delete the session (let TTL handle cleanup, or let the
  downstream action delete after consuming)

---

## Security

| Concern                                | Mitigation                                                                                  |
| -------------------------------------- | ------------------------------------------------------------------------------------------- |
| Enumerate/guess sessions               | `resume_ref = Keys::id32()` — 128-bit entropy                                               |
| Session hijacking                      | `scope_id` stored in session, validated on every request                                    |
| Arbitrary provider class instantiation | `:provider` validated against pre-registered registry                                       |
| Cross-provider session abuse           | `provider_ref` stored in session, checked every request                                     |
| Path ambiguity `init` vs `:ref`        | `:ref` constrained to `[0-9a-f]{32}`                                                        |
| Stale sessions                         | TTL enforced by cache layer                                                                 |
| Race on concurrent next submissions    | Cache set is not atomic; documented limitation — use `oneAtATime` in the provider if needed |

---

## Test Plan

### `tests/Forms/ResumableFormProviderTest.php`

- Register a concrete provider class; `resolve()` returns its instance
- Resolve unknown ref throws `NotFoundException`
- Default `initForm()` returns null
- Default `sessionTTL()` returns 3600
- Re-registering a provider ref with a different class throws `RuntimeException`

### `tests/Services/FormServiceTest.php`

- `initSession`: no initForm -> creates session phase=steps, returns first step form
- `initSession`: with initForm -> validates init body, creates session, returns first step form
- `initSession`: invalid init body -> throws `InvalidFormException`
- `initSession`: unknown provider -> throws `NotFoundException`
- `initSession`: single-step provider (nextStep returns null after init) -> done immediately
- `nextStep`: advances progress, returns next form, updates cache
- `nextStep`: final step -> phase='done', done=true in response
- `nextStep`: already done -> throws `BadRequestException`
- `nextStep`: wrong scope_id -> throws `ForbiddenException`
- `nextStep`: unknown resume_ref -> throws `NotFoundException`
- `getState`: returns current form at each phase
- `getState`: unknown ref -> throws `NotFoundException`
- `cancelSession`: deletes session, returns done=true
- `requireCompletion`: done session -> returns FormData
- `requireCompletion`: not-done session -> throws `ForbiddenException`

---

## `RouteFormDocPolicy` Redesign

Rename `DISCOVERY_ONLY` → `EXTERNAL`. The old name implies it is merely a restriction
("only available via discovery") rather than describing _what_ it is: a form whose schema
lives outside the spec and is fetched by the client at runtime.

| Case       | String value | requestBody in spec | `x-oz-form` extension       | Meaning                                                  |
| ---------- | ------------ | ------------------- | --------------------------- | -------------------------------------------------------- |
| `AUTO`     | `'auto'`     | embedded if static  | none                        | Auto-detect: static -> inline, dynamic factory -> OPAQUE |
| `OPAQUE`   | `'opaque'`   | none                | `{policy: 'opaque'}`        | Form is intentionally hidden; not for API consumers      |
| `EXTERNAL` | `'external'` | none                | `{policy: 'external', ...}` | Client fetches form at runtime (FormService / discovery) |

Factory rename on `RouteFormDeclaration`:

```php
// Before
RouteFormDeclaration::discoveryOnly(callable|Form $form): static

// After
RouteFormDeclaration::external(callable|Form $form): static
```

The `x-oz-form` extension value changes from `'discovery_only'` to `'external'` in the
generated OpenAPI spec. No other case values change.

When a route's form is resumable and auto-registered as a `RouteResumableFormProvider`,
the `x-oz-form` extension also carries `form_provider_ref`:

```json
{ "policy": "external", "form_provider_ref": "route:oz:signup" }
```

---

## Backlog

### A. `RouteResumableFormProvider` — route form bridge (proposed)

**Background**: `RouteSharedOptions::getStaticFormBundle()` collects all static form declarations on a
route into a merged `Form` for API doc generation. A separate discovery request from the client can
retrieve this form. The proposal bridges this with `FormService`: when a route's form has
`resumable()` enabled (i.e. `$form->getResumeScope() !== null`), the client is directed to
use `FormService` instead of submitting raw data.

**Proposal**:

1. Add `RouteResumableFormProvider` — a lazy `AbstractResumableFormProvider` wrapping a route's
   static form as a single-step sequence. Resolution is on-demand via the `route:` prefix —
   no bootstrap scan required.
   - Provider ref: `route:{route_name}` (e.g. `route:oz:signup`). Not pre-registered; resolved
     lazily by `AbstractResumableFormProvider::resolve()` when it sees the `route:` prefix.
   - `initForm()`: `null` (the form is the first and only step)
   - `nextStep(FormData $progress)`: returns the route's static form on step index 0, `null` on 1
   - `resumeScope()`: from `bundle->getResumeScope()` — always non-null (enforced at resolve time)
   - `resumeTTL()`: from `bundle->getResumeTTL()` (default 3600 if form used default)

2. When route form discovery is called for a resumable-form route, include a `form_provider_ref`
   in the response alongside (or instead of) the raw form array:

   ```json
   { "form_provider_ref": "route:oz:signup" }
   ```

   The client uses this ref with `POST /form/:provider/init` instead of submitting directly to
   the route. The route's own handler uses `FormService::requireCompletion()` to consume the
   validated data once the session is done.

3. API doc: when `RouteFormDocPolicy` is `EXTERNAL` or the form is resumable, the
   `x-oz-form` OpenAPI extension includes `{ policy: 'external', form_provider_ref: '...' }`
   so generated clients (and API explorers) know how to start the FormService session.

**Opt-in only**: A route is eligible only when its static form bundle has `getResumeScope() !== null`
— i.e. the developer called `Form::resumable()` on a form attached to the route:

```php
// Opt-in: form declares resumable() -> RouteResumableFormProvider is auto-registered
$router->post('/signup', $handler)
    ->form((new SignupForm())->resumable(RequestScope::STATE, 3600));

// Not opted-in: plain form -> direct-submit, no FormService involvement
$router->post('/login', $handler)
    ->form(new LoginForm());
```

Calling `->resumable()` on the form **before** passing it to `.form()` sets `t_resume_scope` on
the `Form` instance stored inside the `RouteFormDeclaration`. When `getStaticFormBundle()` later
calls `bundle->merge(form)`, it propagates `t_resume_scope` to the bundle (only if the bundle
doesn't already have one from a parent group — first-in-chain wins).

**Resolution is lazy, not a boot-time scan.** `RouteResumableFormProvider` does not iterate all
routes at `InitHook`. Instead `AbstractResumableFormProvider::resolve()` recognises the `route:`
prefix and resolves on-demand:

```
resolve('route:oz:signup')
  -> strip prefix -> route name = 'oz:signup'
  -> router->getRoute('oz:signup')
  -> getStaticFormBundle()
  -> assert bundle->getResumeScope() !== null   (NotFoundException if not resumable)
  -> return new RouteResumableFormProvider(route, bundle)
```

No pre-registration step, no scanning, no stale state at boot.

### B. Form flattening & `totalSteps()` progress indicator

**Flattening decision (final)**: FormService does **NOT** flatten `Form` internal `step()`
sub-steps into separate HTTP round-trips.

Rationale: internal `Form::step()` calls define conditional sub-sections of a single form
(e.g., "show these extra fields if X is checked"). They are validated in one `Form::validate()`
call and belong to one logical screen. Splitting them into separate HTTP exchanges would
violate `Form`'s own semantics and make the provider API harder to implement correctly.

If a developer wants separate pages, the provider must return separate flat forms from
`nextStep()`. FormService is not responsible for decomposing a Form that the developer
intentionally constructed as a unit.

**`totalSteps()` is an explicit contract** on `ResumableFormProviderInterface`:

- `null` → sequence length is variable/unknown; `progress` key is **omitted** from the response
- `int` → fixed declared length; every response includes `progress`

**Response shape** (repeated from above for clarity):

```json
{
  "resume_ref":  "...",
  "form":        { ... },
  "done":        false,
  "expires_at":  1700003600,
  "progress":    { "step": 2, "total_steps": 10 }  // key omitted entirely when totalSteps()===null
}
```

- `step` = `STEP_INDEX_KEY` value + 1 (response is 1-indexed)
- When `done: true`, `progress.step === progress.total_steps` (when key is present)

### C. Additional backlog items

- [ ] **C1. `getStaticFormBundle` API doc hint when resumable** — When `getStaticFormBundle()` returns
      null for a route because its form is resumable (or policy is `EXTERNAL`), the generated API
      doc (via `x-oz-form` extension) should reference the `form_provider_ref` and the `POST /form/:provider/init`
      route so developers know how to start the session. Use `RouteFormDocPolicy::EXTERNAL` with the
      `form_provider_ref` field (see `RouteFormDocPolicy` redesign section above).

- [ ] **C2. Remove stale/wrong PHPDoc and comments about forms** — Audit the entire codebase
      (including tests) for outdated docblocks that reference old form API (e.g. references to
      `resume()` when the method is `resumable()`, wrong parameter names, obsolete step descriptions,
      incorrect mentions of `sessionScope`/`sessionTTL` after rename). Pay special attention to
      `oz/Forms/`, `oz/Router/`, `oz/REST/`, and matching test files.

- [ ] **C3. Real integration test — QCM with DynamicValue and flow control** — Create a full
      integration test in `tests/Integration/Forms/` (style: `OZTestProject` subprocess) that exercises:
  - A multi-question provider (geography quiz or similar) with at least 3 steps
  - `DynamicValue` in field `if()` conditions (server-only visibility)
  - At least one `expect()` rule that `isServerOnly()` to trigger `/evaluate`
  - Known `totalSteps()` to verify the progress counter
  - Full flow: init -> multiple nexts -> done -> `requireCompletion()`
  - Cancel mid-flow: next `getState` must throw `NotFoundException`
  - After integration test is added, update copilot-instructions.md (section 23 or add a Forms
    subsection) to document the pattern so future agents know how to write similar tests.

- [ ] **C4. Rate limiting on `POST /init`** — Protect against session flooding by adding
      `rateLimit(new IPRateLimit(...))` to the `ROUTE_INIT` route declaration.

- [ ] **C5. Session abandonment / expiry hooks** — Dispatch an event when a FormService session
      expires from cache (useful for analytics or cleanup). Needs cache-layer support (expiry callbacks)
      which is not currently available — defer until cache layer supports it.
