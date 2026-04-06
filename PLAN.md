# PLAN: ResumableFormService - Incremental / Resumable Form Submission

## Goals

Add server-side orchestration for multi-step, session-resumable form flows where:

- Each step form can depend on previously validated answers (QCM, surveys, wizards)
- All session state lives in the persistent cache; clients carry only an opaque `resume_ref`
- Abandonment (cancel), in-progress retrieval (state), and step submission (next) are all explicit
- After the final step the accumulated `FormData` is available for the downstream route handler

---

## Files

### Create / Move

| Path                                                                 | Status  | Purpose                                                              |
| -------------------------------------------------------------------- | ------- | -------------------------------------------------------------------- |
| `oz/Forms/Interfaces/ResumableFormProviderInterface.php`             | done    | Contract for form sequence providers (redesign pending)              |
| `oz/Forms/Interfaces/FieldContainerInterface.php`                    | pending | Shared contract for `Form` and `Fieldset`                            |
| `oz/Forms/AbstractResumableFormProvider.php`                         | done    | Pure defaults base - registry removed (redesign pending)             |
| `oz/Forms/Enums/FormResumePhase.php`                                 | pending | `INIT \| STEPS \| DONE` enum                                         |
| `oz/Forms/FormResumeProgress.php`                                    | pending | `Store<array>` subclass - phase, step index, private provider state  |
| `oz/Forms/Fieldset.php` _(renamed from `FormStep.php`)_              | pending | Conditional named group of fields                                    |
| `oz/Forms/Services/ResumableFormService.php` _(renamed FormService)_ | pending | REST service with 6 routes; resolution via `oz.forms.providers`      |
| `oz/Router/RouteResumableFormProvider.php`                           | pending | Complete redesign in Router namespace                                |
| `oz/oz_settings/oz.forms.providers.php`                              | pending | Default provider map: `'route' => RouteResumableFormProvider::class` |
| `tests/Forms/ResumableFormProviderTest.php`                          | done    | Unit tests - will need update for new interface                      |
| `tests/Forms/FormServiceTest.php`                                    | done    | Unit tests - will need rename + update for new signatures            |
| `tests/Forms/FieldsetTest.php`                                       | pending | Unit tests for Fieldset                                              |

### Modify

| Path                                             | Change                                                                     |
| ------------------------------------------------ | -------------------------------------------------------------------------- |
| `oz/oz_settings/oz.routes.api.php`               | Replace `FormService::class` with `ResumableFormService::class`            |
| `oz/oz_settings/oz.request.php`                  | Already has `OZ_FORM_RESUME_REF_HEADER_NAME` = `'X-OZONE-Form-Resume-Ref'` |
| `oz/Forms/Form.php`                              | `step()` -> `fieldset()`, `dynamicStep()` -> `dynamicFieldset()`, etc.     |
| `oz/Forms/Field.php`                             | `$t_form: Form` -> `$t_parent: FieldContainerInterface`                    |
| `oz/Forms/Traits/FieldContainerHelpersTrait.php` | No change - already delegates to `$this->field()`                          |
| `oz/Router/RouteSharedOptions.php`               | `form()` extended to detect `class-string<ResumableFormProviderInterface>` |
| `tests/Forms/FormTest.php`                       | Update `step/dynamicStep` references to `fieldset/dynamicFieldset`         |

---

## Completed Renames (already in code)

- `sessionScope` -> `resumeScope` on `ResumableFormProviderInterface` and `AbstractResumableFormProvider`
- `sessionTTL` -> `resumeTTL` on the same
- `nextForm` -> `nextStep` on the same
- `FormService` namespace move: `OZONE\Core\Services` -> `OZONE\Core\Forms\Services\FormService`
- `DISCOVERY_ONLY` -> `EXTERNAL` on `RouteFormDocPolicy`
- `RouteFormDeclaration::discoveryOnly()` -> `RouteFormDeclaration::external()`

---

## Pending Renames

### 1. `FormService` -> `ResumableFormService`

- **From**: `OZONE\Core\Forms\Services\FormService` (`oz/Forms/Services/FormService.php`)
- **To**: `OZONE\Core\Forms\Services\ResumableFormService` (`oz/Forms/Services/ResumableFormService.php`)

No logic changes - only class name, file name, and namespace reference update.

Affected files: `oz/oz_settings/oz.routes.api.php`, `tests/Forms/FormServiceTest.php`.

### 2. Interface method renames on `ResumableFormProviderInterface`

| Current name                          | New                                                                     |
| ------------------------------------- | ----------------------------------------------------------------------- |
| `providerRef(): string`               | `getName(): string`                                                     |
| `initForm(): ?Form`                   | `static initForm(): ?Form`                                              |
| `nextStep(FormData $progress): ?Form` | `nextStep(FormData $cleaned_form, FormResumeProgress $progress): ?Form` |

Plus **new method**: `static instance(RouteInfo $ri): static`

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

interface ResumableFormProviderInterface
{
    // Unique name registered as the key in oz.forms.providers settings and used
    // as the :provider URL segment in ResumableFormService routes.
    public static function getName(): string;

    // Optional pre-flight form shown before the main steps begin.
    // STATIC so API doc generation can call it without instantiating a provider.
    // Return null to skip straight to the first nextStep() call.
    public static function initForm(): ?Form;

    // Factory: creates a provider instance aware of ResumableFormService's RouteInfo.
    // The returned instance stores $ri internally for access to context, request, etc.
    // Called once per ResumableFormService handler invocation.
    public static function instance(RouteInfo $ri): static;

    // Return the Form the client must fill for the next step, given accumulated cleaned
    // fields and the private progress state.
    // - $cleaned_form: accumulated validated fields from all previous steps (shareable
    //   with the client if needed); read the values but do NOT mutate.
    // - $progress: private provider state + phase + step index (never sent to the client);
    //   the provider may call $progress->set(key, value) to store private bookkeeping.
    // Return null when the sequence is complete (no more steps).
    // Contract: must be deterministic - same inputs always yield the same Form structure.
    public function nextStep(FormData $cleaned_form, FormResumeProgress $progress): ?Form;

    // How long the session cache entry lives from its creation time (seconds).
    public function resumeTTL(): int;

    // Scope strategy tying session cache entries to a specific principal.
    public function resumeScope(): RequestScope;

    // Known total step count (null = variable/unknown at creation time).
    // When non-null every response includes {"step": N, "total_steps": M}.
    // Default: null.
    public function totalSteps(): ?int;

    // Whether the client may go back to a previous step via POST .../back.
    // Default: false.
    public function isReversible(): bool;

    // Earliest moment a new session may be started; null = no restriction.
    public function notBefore(): ?\DateTimeImmutable;

    // Hard deadline: auto-expires at this moment regardless of resumeTTL().
    // null = TTL-only expiry.
    public function deadline(): ?\DateTimeImmutable;
}
```

---

## New Types

### `FormResumePhase` enum

```php
namespace OZONE\Core\Forms\Enums;

enum FormResumePhase: string
{
    case INIT  = 'init';   // initForm() is non-null and not yet submitted
    case STEPS = 'steps';  // stepping through nextStep() sequence
    case DONE  = 'done';   // nextStep() returned null; sequence complete
}
```

### `FormResumeProgress`

```php
namespace OZONE\Core\Forms;

/**
 * @extends Store<array>
 */
class FormResumeProgress extends Store
{
    private const STEP_INDEX_KEY = '_step_index';
    private const PHASE_KEY      = '_phase';

    public function getPhase(): FormResumePhase;

    // 0-based step index incremented by ResumableFormService after each validated next.
    public function getStepIndex(): int;

    // Provider private state - arbitrary key/value the provider uses for bookkeeping.
    // Keys must not start with '_' (reserved for framework internal keys).
    public function get(string $key): mixed;
    public function set(string $key, mixed $value): void;
}
```

`FormResumeProgress` is constructed from the cached `progress_state` array and reconstructed
on every request. The framework owns the housekeeping keys (`_step_index`, `_phase`); providers
own all other keys.

---

## Abstract Base: `AbstractResumableFormProvider`

Pure-defaults base. The static registry pattern is **removed** - provider resolution now goes
through `oz.forms.providers` settings inside `ResumableFormService`.

| Responsibility           | Implementation                                         |
| ------------------------ | ------------------------------------------------------ |
| Default `initForm()`     | returns `null` (static)                                |
| Default `instance($ri)`  | `$inst = new static(); $inst->ri = $ri; return $inst;` |
| Default `resumeTTL()`    | returns `3600`                                         |
| Default `resumeScope()`  | returns `RequestScope::STATE`                          |
| Default `totalSteps()`   | returns `null` (variable/unknown)                      |
| Default `isReversible()` | returns `false`                                        |
| Default `notBefore()`    | returns `null` (no restriction)                        |
| Default `deadline()`     | returns `null` (TTL-only expiry)                       |

The `protected ?RouteInfo $ri = null` property is set by the default `instance()`. Providers
may access it via `$this->ri`; providers that do not need it may ignore it.

**Removed** (see "Methods Pending Removal"): `$registry`, `register()`, `resolve()`, `clearRegistry()`

---

## Settings: `oz.forms.providers`

New settings file `oz/oz_settings/oz.forms.providers.php`:

```php
return [
    RouteResumableFormProvider::PROVIDER_NAME => RouteResumableFormProvider::class,
];
```

Registry format: `provider_name (string) => class FQN (string)`. The class must implement
`ResumableFormProviderInterface`.

Applications add custom providers in `app/settings/oz.forms.providers.php`:

```php
return [
    MyQuizProvider::PROVIDER_NAME => MyQuizProvider::class,
];
```

Keys are merged via `array_replace_recursive` - built-in defaults are preserved unless explicitly
overridden.

`ResumableFormService` resolves a provider at request time via:

```php
$class = Settings::get('oz.forms.providers', $provider_name);
if (!$class || !is_a($class, ResumableFormProviderInterface::class, true)) {
    throw new NotFoundException(...);
}
$provider = $class::instance($this->ri);
```

---

## Route-level Provider Declaration

A route may declare a `ResumableFormProviderInterface` class directly via `.form()`:

```php
$router->post('/signup', $handler)
    ->form(SignupProvider::class);  // class-string<ResumableFormProviderInterface> detected
```

**Behaviour when a class implementing `ResumableFormProviderInterface` is passed:**

- The route's form policy is set to `RouteFormDocPolicy::EXTERNAL`.
- The provider class is stored on `RouteFormDeclaration` as the canonical provider.
- Last defined in chain wins (group -> route).
- `ResumableFormService` becomes the **exclusive** submission path for this route.
- Before the route handler runs, the framework reads `OZ_FORM_RESUME_REF_HEADER_NAME` from
  the request header, looks up the completed session in cache, verifies ownership and
  `phase === done`, then injects the accumulated `FormData` into `$ri->getCleanFormData()`.
- The route handler simply uses `$ri->getCleanFormData()` as normal.

**Discovery response** for a route with a provider declared:

```json
{ "policy": "external", "form_provider_name": "my-quiz", "init_form": { ... } }
```

`init_form` is the serialized result of `ProviderClass::initForm()` (or `null`).

---

## `RouteResumableFormProvider` Redesign

**Namespace**: `OZONE\Core\Router` (moved from `OZONE\Core\Forms`)
**File**: `oz/Router/RouteResumableFormProvider.php`
**Registered in**: `oz.forms.providers` default settings as `'route' => RouteResumableFormProvider::class`

```php
namespace OZONE\Core\Router;

class RouteResumableFormProvider extends AbstractResumableFormProvider
{
    public const PROVIDER_NAME = 'route';

    public static function getName(): string
    {
        return self::PROVIDER_NAME;
    }

    // initForm() returns a Form with a single required 'route_name' string field.
    // The client submits the target route's name to start the session.
    public static function initForm(): ?Form;

    // nextStep():
    // - At step index 0: reads $cleaned_form->get('route_name'), calls
    //   $router->getRoute($route_name)->getStaticFormBundle() on that route,
    //   validates that bundle->getResumeScope() !== null, returns the bundle.
    // - At step index 1: returns null (done - single-step provider).
    // - If route not found, bundle not resumable, or no static form: throws RuntimeException.
    public function nextStep(FormData $cleaned_form, FormResumeProgress $progress): ?Form;

    // resumeScope() and resumeTTL() derived from the target route's static form bundle
    // (stored in $progress private state after step 0).
    public function resumeScope(): RequestScope;
    public function resumeTTL(): int;

    public function totalSteps(): ?int
    {
        return 1;
    }
}
```

---

## `ResumableFormService` Routes

| Method | Path                       | Handler             | Route name         |
| ------ | -------------------------- | ------------------- | ------------------ |
| POST   | `/form/:provider/init`     | `initSession()`     | `oz:form:init`     |
| GET    | `/form/:provider/state`    | `getState()`        | `oz:form:state`    |
| POST   | `/form/:provider/next`     | `nextStep()`        | `oz:form:next`     |
| POST   | `/form/:provider/back`     | `backStep()`        | `oz:form:back`     |
| POST   | `/form/:provider/cancel`   | `cancelSession()`   | `oz:form:cancel`   |
| POST   | `/form/:provider/evaluate` | `evaluateCurrent()` | `oz:form:evaluate` |

The `resume_ref` travels as an HTTP header on all requests
except `init` (which creates the ref and returns it in the response body). There is no `:ref`
path segment.

Path parameter constraint: `:provider` - `[^/]+`.

---

## Session Cache Schema

Cache namespace: `'oz.form.sessions'`
Cache key: `$resume_ref` (32-char hex, returned by `initSession()` and sent back by
the client in the request header)

```php
[
    'provider_name'  => string,   // e.g. 'route' -- cross-checked on every request
    'phase'          => string,   // FormResumePhase->value  ('init' | 'steps' | 'done')
    'cleaned_form'   => array,    // FormData::toArray() -- accumulated validated fields
                                  // shareable structure; NOT the private provider state
    'progress_state' => array,    // backing array for FormResumeProgress (private to provider)
                                  // includes '_step_index', '_phase', and provider keys
    'scope_id'       => string,   // provider->resumeScope()->resolveId($context)
    'created_at'     => int,      // Unix timestamp of session creation
    'expires_at'     => int|null, // Unix timestamp of hard deadline; null = TTL-only
    'history'        => array,    // list of {cleaned_form, progress_state} snapshots
                                  // (only populated when provider->isReversible())
]
```

**Phases:**

| Phase   | Meaning                                            | Current form                                         |
| ------- | -------------------------------------------------- | ---------------------------------------------------- |
| `init`  | `initForm()` returned non-null Form, not submitted | `$class::initForm()` (static call)                   |
| `steps` | Stepping through `nextStep()` sequence             | `$provider->nextStep($cleaned_form_data, $progress)` |
| `done`  | `nextStep()` returned null                         | null (no more steps)                                 |

The current form is always **re-derived** from the cached state - it is never stored. This
keeps the cache lean and means the provider only needs to be deterministic.

**`history` invariants (when `isReversible()` is true):**

- After step 0 is submitted, `history` = `[{cleaned_form snapshot, progress_state snapshot}]`.
- Each successful `nextStep()` pushes the pre-advance snapshot before updating.
- `backStep()` pops the last snapshot, restores `cleaned_form` and `progress_state`.
- `history` is always `[]` when `isReversible()` is `false`.

---

## Standard Response Shape

Every `ResumableFormService` response includes:

```json
{
    "done":       false,
    "resume_ref": "a3f...",
    "form":       { ... },
    "expires_at": 1700003600,
    "progress":   {
        "step":        2,
        "total_steps": 10
    }
}
```

`resume_ref` is included in every response (not just `init`). `form` is `null` when done.
`progress` is omitted entirely when `provider->totalSteps() === null`.
When `done: true`, `progress.step === progress.total_steps` (when present).

---

## Handler Logic

Provider resolution used in every handler:

```
$class = Settings::get('oz.forms.providers', $route_provider_name);
if (!$class) throw NotFoundException
$provider = $class::instance($this->ri);
```

`resume_ref` is read from the request header in all handlers
except `initSession()`.

### `POST /form/:provider/init`

```
1. resolve :provider from oz.forms.providers -> $class, $provider
2. if provider->notBefore() != null && now() < notBefore(): throw FormResumeNotYetActiveException
3. scope_id = provider->resumeScope()->resolveId($context)
4. $cleaned_form = new FormData()
5. $init_form = $class::initForm()                // STATIC call
6. if $init_form != null:
       validate request body against $init_form -> $init_cleaned
       $cleaned_form = $init_cleaned
       $phase = FormResumePhase::INIT
   else:
       $phase = FormResumePhase::STEPS
7. $progress = new FormResumeProgress()
8. $progress->set('_step_index', 0)
9. $progress->set('_phase', $phase->value)
10. $next = $provider->nextStep($cleaned_form, $progress)
11. $phase = ($next === null) ? FormResumePhase::DONE : FormResumePhase::STEPS
12. $progress->set('_phase', $phase->value)
13. expires_at = provider->deadline()
        ? min(time() + resumeTTL(), deadline->timestamp)
        : null
14. resume_ref = Keys::id32('form.session')
15. Write session:
        provider_name, phase, cleaned_form, progress_state, scope_id,
        created_at, expires_at, history: []
    TTL = provider->resumeTTL()
16. Return standard response (resume_ref also in response body for client to store)
```

### `GET /form/:provider/state`

```
1. Read resume_ref from request header
2. Load session by resume_ref             NotFoundException if not found / expired
3. if expires_at != null && now() > expires_at: throw FormResumeExpiredException
4. Verify scope_id matches                ForbiddenException on mismatch
5. Verify session['provider_name'] == :provider   ForbiddenException on mismatch
6. Re-derive current form from phase + cleaned_form + progress_state
7. Return standard response
```

### `POST /form/:provider/next`

```
1. Read resume_ref from header
2. Load session, verify ownership + provider
3. if expires_at != null && now() > expires_at: throw FormResumeExpiredException
4. Abort if phase === DONE                BadRequestException
5. Re-derive $current_form from phase, cleaned_form, progress_state
6. Validate request body against $current_form -> $validated
7. Merge $validated into cleaned_form
8. $progress->set('_step_index', $progress->getStepIndex() + 1)
9. if provider->isReversible():
       push {cleaned_form, progress_state} snapshot onto history
10. $next = $provider->nextStep($cleaned_form, $progress)
11. $phase = ($next === null) ? DONE : STEPS
12. $progress->set('_phase', $phase->value)
13. Update session cache: phase, cleaned_form, progress_state, history
14. Return standard response
```

### `POST /form/:provider/back`

```
1. Read resume_ref from header
2. Load session, verify ownership + provider
3. if expires_at != null && now() > expires_at: throw FormResumeExpiredException
4. if !provider->isReversible(): throw BadRequestException('OZ_FORM_NOT_REVERSIBLE')
5. if history is empty: throw BadRequestException('OZ_FORM_NO_PREVIOUS_STEP')
6. Pop last snapshot from history -> {prev_cleaned_form, prev_progress_state}
7. Restore session: cleaned_form = prev_cleaned_form, progress_state = prev_progress_state
8. phase = FormResumePhase::STEPS (always; back is only valid before done)
9. Re-derive current form from restored state
10. Update session cache
11. Return standard response
```

### `POST /form/:provider/cancel`

```
1. Read resume_ref from header
2. Load session, verify ownership + provider
3. Delete session from cache
4. Return { done: true }
```

### `POST /form/:provider/evaluate`

```
1. Read resume_ref from header
2. Load session, verify ownership
3. if expires_at != null && now() > expires_at: throw FormResumeExpiredException
4. Abort if phase === DONE               BadRequestException
5. Re-derive current form from phase, cleaned_form, progress_state
6. eval_data = merge(session.cleaned_form, request.body)
7. For each field where getIf() != null && getIf()->isServerOnly():
       visibility[field.ref] = field->isEnabled(eval_data)
8. For each expect rule where isServerOnly():
       passes = rule->check(eval_data)
       expect_results[] = { index, passes, message }
9. Return { visibility: {field_ref: bool}, expect: [{index, passes, message}] }
```

---

## New Exceptions (pending)

| Class                             | HTTP | When                                           |
| --------------------------------- | ---- | ---------------------------------------------- |
| `FormResumeNotYetActiveException` | 403  | `notBefore()` is set and `now() < notBefore()` |
| `FormResumeExpiredException`      | 410  | `expires_at` is set and `now() > expires_at`   |

`FormResumeExpiredException` uses 410 Gone rather than 404 so clients can distinguish expiry
from a bad ref.

---

## `ResumableFormService::requireCompletion()` (static helper)

```php
public static function requireCompletion(
    string $provider_name,
    string $resume_ref,
    Context $context
): FormData
```

- Loads session by `$resume_ref`, verifies `scope_id` ownership, verifies `provider_name` matches
- Requires `phase === done`; throws `ForbiddenException` otherwise
- Returns `FormData` built from `cleaned_form`
- Does NOT delete the session (TTL handles cleanup)

When a route declares a provider via `->form(MyProvider::class)`, `requireCompletion()` is called
**internally by the routing pipeline** before the handler runs - the handler receives the completed
`FormData` transparently via `$ri->getCleanFormData()` and does not call it manually.

---

## Security

| Concern                                | Mitigation                                                                  |
| -------------------------------------- | --------------------------------------------------------------------------- |
| Enumerate/guess sessions               | `resume_ref = Keys::id32()` - 128-bit entropy                               |
| Session hijacking                      | `scope_id` stored in session, validated on every request                    |
| Arbitrary provider class instantiation | `:provider` validated against `oz.forms.providers` settings registry        |
| Cross-provider session abuse           | `provider_name` stored in session, checked on every request                 |
| Path ambiguity                         | No `:ref` in path; `resume_ref` only in header                              |
| Stale sessions                         | TTL enforced by cache layer                                                 |
| Race on concurrent next submissions    | Cache set is not atomic - documented limitation; use `oneAtATime` if needed |

---

## Test Plan

### `tests/Forms/ResumableFormProviderTest.php` (needs update)

- `getName()` returns the expected string
- `static initForm()` returns null by default
- `static instance($ri)` returns a correctly typed instance with `$ri` accessible
- Default `resumeTTL()` returns 3600
- Default `resumeScope()` returns `RequestScope::STATE`
- No registry methods exist (`register`, `resolve`, `clearRegistry` are gone)

### `tests/Forms/ResumableFormServiceTest.php` (renamed from `FormServiceTest.php`)

- `initSession`: no `initForm` -> creates session `phase=steps`, returns first form
- `initSession`: with `initForm` -> validates body, creates session, returns first form
- `initSession`: invalid init body -> throws `InvalidFormException`
- `initSession`: unknown provider -> throws `NotFoundException`
- `initSession`: single-step provider (nextStep returns null immediately) -> `done=true`
- `nextStep`: advances progress, returns next form, updates cache
- `nextStep`: final step -> `phase=done`, `done=true` in response
- `nextStep`: already done -> throws `BadRequestException`
- `nextStep`: wrong scope_id -> throws `ForbiddenException`
- `nextStep`: unknown resume_ref -> throws `NotFoundException`
- `nextStep`: `resume_ref` read from request header (not body)
- `getState`: returns current form at each phase
- `getState`: unknown resume_ref -> throws `NotFoundException`
- `cancelSession`: deletes session, returns `done=true`
- `requireCompletion`: done session -> returns FormData
- `requireCompletion`: not-done session -> throws `ForbiddenException`
- `FormResumeProgress`: `getPhase()`, `getStepIndex()`, `get/set` provider state round-trip
- `isReversible=true`: `backStep()` succeeds, restores previous cleaned_form and progress_state
- `isReversible=false`: `backStep()` throws `BadRequestException`
- `notBefore()` in future: `initSession` throws `FormResumeNotYetActiveException`
- `deadline()` in past: any handler throws `FormResumeExpiredException`

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
the `x-oz-form` extension also carries `form_provider_name` and `init_form`:

```json
{ "policy": "external", "form_provider_name": "route", "init_form": null }
```

`init_form` is the serialized result of `ProviderClass::initForm()` (or `null`).

---

## Backlog

### A. Form flattening & `totalSteps()` notes

`ResumableFormService` does NOT flatten `Form` internal `fieldset()` sub-sections into separate
HTTP round-trips. Fieldsets are validated in one `Form::validate()` call. If a provider wants
separate pages, it must return separate flat forms from `nextStep()`.

`totalSteps()` contract: `null` = length variable/unknown, omit `progress` from response;
`int` = fixed length, include `progress` in every response.

### B. API doc hint when provider declared on route

When `getStaticFormBundle()` returns null because the form is delegated to a provider
(policy = `EXTERNAL`), the generated `x-oz-form` OpenAPI extension must reference the
`form_provider_name` and the `POST /form/:provider/init` route so developers know how
to start the session:

```json
{ "policy": "external", "form_provider_name": "route", "init_form": null }
```

### C. Integration test with `DynamicValue` and flow control

Create `tests/Integration/Forms/ResumableFormServiceTest.php` exercising:

- A multi-question provider (at least 3 steps)
- `DynamicValue` in field `if()` conditions (server-only visibility)
- At least one `expect()` rule that `isServerOnly()` to trigger `/evaluate`
- Known `totalSteps()` to verify progress counter
- Full flow: init -> multiple nexts -> done
- Cancel mid-flow: subsequent `getState` must throw `NotFoundException`

After writing this test, update `.github/copilot-instructions.md` section 23 (or add a
Forms subsection) to document the test pattern.

### D. Rate limiting on `POST /init`

Add `rateLimit(new IPRateLimit(...))` to the `oz:form:init` route declaration to prevent
session flooding.

### E. Session abandonment / expiry hooks

Dispatch an event when a `ResumableFormService` session expires from cache (useful for
analytics or cleanup). Requires cache-layer expiry callbacks - **defer** until that
support is available.

### F. `addOperationFromRoute` — detect resumable static form bundle

**File**: `oz/REST/Traits/ApiDocManipulationTrait.php`, inside `addOperationFromRoute()`.

**Problem**: When a route declares a static form with `.resumable()` (i.e.
`$static_bundle->getResumeScope() !== null`), the method emits a plain `requestBody` schema
with no indication that the client **may also** submit through the `RouteResumableFormProvider`
pipeline (`POST /form/route/init` → request header on the route).
Direct submission remains valid; the resumable path is an alternative the spec does not
currently expose.

**Fix**: In the `else` branch, after resolving `$static_bundle`, check
`$static_bundle->getResumeScope()`. When non-null, emit the normal `requestBody` **and**
attach an additional `x-oz-form` extension as an opt-in hint:

The required import is `OZONE\Core\Router\RouteResumableFormProvider`.

**Also update** the `addOperationFromRoute()` docblock to mention that routes with a resumable
static form bundle emit both `requestBody` and `x-oz-form`.

---

## Methods Pending Removal

The following methods/properties exist in the current code but are made **obsolete** by the
new design. No code changes until these are approved for removal.

### On `AbstractResumableFormProvider` - registry pattern removed

1. `private static array $registry` - registry replaced by `oz.forms.providers` settings
2. `public static function register(string $class): void` - replaced by settings file
3. `public static function resolve(string $ref): ResumableFormProviderInterface` - moves to `ResumableFormService`
4. `public static function clearRegistry(): void` - test helper, registry is gone

### On `RouteResumableFormProvider` - entire class being redesigned

5. `const PROVIDER_REF_PREFIX = 'route:'` - replaced by `const PROVIDER_NAME = 'route'`
6. `public static function providerRef(): string` - replaced by `getName()`
7. `public static function resolveRoute(string $route_name): self` - resolution moves to `ResumableFormService`
8. `public function getProviderRef(): string` - replaced by `getName()`
9. `public static function refForRoute(string $route_name): string` - no longer needed
10. `public function __construct(string $route_name, Form $bundle)` - replaced by `instance(RouteInfo $ri)`

### On `ResumableFormProviderInterface` - signature changes

11. `providerRef(): string` - becomes `getName(): string`
12. `initForm(): ?Form` - becomes `static initForm(): ?Form`
13. `nextStep(FormData $progress): ?Form` - becomes `nextStep(FormData $cleaned_form, FormResumeProgress $progress): ?Form`

### On `FormService` (pre-rename)

14. `const STEP_INDEX_KEY = '_oz_form_step_index'` - moves into `FormResumeProgress` as
    private `const STEP_INDEX_KEY = '_step_index'`

---

## Cleanup: Stale PHPDoc and Comments

After all code changes are applied, do a targeted audit and fix any outdated references
(wrong param names, references to removed methods, stale step descriptions, etc.).

**Files to audit:**

- `oz/Forms/` - all `.php` files
- `oz/Forms/Services/` - all `.php` files
- `oz/Router/RouteSharedOptions.php`, `RouteFormDeclaration.php`, `RouteOptions.php`
- `oz/REST/` - any file referencing form policies or providers
- `tests/Forms/` - all `.php` files
- `.github/copilot-instructions.md` - section 6 (Forms) and section 15 (REST)

**Key patterns to search for and fix:**

- `providerRef` (should be `getName`)
- `sessionTTL`, `sessionScope` (verify all renamed correctly)
- `STEP_INDEX_KEY` references outside `FormResumeProgress`
- `provider_ref` in doc/comments (now `provider_name`)
- `form_provider_ref` in doc/comments (now `form_provider_name`)
- `FormService` class name (should be `ResumableFormService`)
- `resolve(` on `AbstractResumableFormProvider` (method removed)
- `register(` on `AbstractResumableFormProvider` (method removed)
- `nextForm(` (renamed - verify all gone)
- `:ref` URL path segment references (no longer in routes)

---

## Phase: Route-Interceptor Resume (RouteFormResumeInterceptor)

### Status: DONE

### Overview

Instead of routing all form-resume requests through standalone `/form/:provider/...` endpoints,
the interceptor approach matches resume requests on the **real** route, so all route auth, guards,
and middlewares run before any resume logic fires.

### Components

#### `oz/Router/RouteFormResumeInterceptor.php` (NEW)

- Always injected via `RouteSharedOptions::getInterceptors()` at priority 1 (before discovery, priority 0).
- `shouldIntercept()`: returns true when the request carries `X-OZONE-Form-Resume: ?1` AND the
  matched route has resume support (`hasResumeSupport()` = true).
- `handle()`: reads action from `X-OZONE-Form-Resume-Action` header (default: `init`), resolves
  provider class from route options (`resolveProviderClass()`) falling back to
  `RouteResumableFormProvider::class`, and delegates to `ResumableFormService::handleFromRealContext()`.

#### `oz/Router/RouteResumableFormProvider.php` (REFACTORED)

Redesigned as the built-in fallback provider for routes that declare `->resumable()` without
an explicit provider class. It no longer requires a `route_name` init form.

- `initForm()` -> `null` (no init step; the route is already known from the URL)
- `nextStep(0)` -> `$this->ri->route()->getOptions()->getFormBundle($this->ri)` (dynamic bundle)
- `nextStep(1+)` -> `null` (done)
- `resumeScope()` -> from `$this->ri->route()->getOptions()->resolveResumeConfig()[0]`
- `resumeTTL()` -> from `$this->ri->route()->getOptions()->resolveResumeConfig()[1]`

`PROVIDER_NAME` removed (no longer registered as a standalone named provider).

#### `oz/oz_settings/oz.forms.providers.php` (UPDATED)

`RouteResumableFormProvider` removed from the registry. It is only used internally by the
interceptor, not as a named standalone provider.

#### `oz/Forms/Services/ResumableFormService.php` (UPDATED)

New public constants: `ACTION_INIT`, `ACTION_STATE`, `ACTION_NEXT`, `ACTION_BACK`,
`ACTION_CANCEL`, `ACTION_EVALUATE`.

New methods:

- `public function handleFromRealContext(string $providerClass, string $action): Response`
  Entry point for the interceptor. Dispatches to one of the 6 `*ForRoute` private methods.
- `public static function requireRouteCompletion(string $resume_ref, RouteInfo $ri): FormData`
  Validates a completed interceptor session; validates against `route_name` + `provider_class`
  stored in the session (not against a provider registry name).

New private methods: `initSessionForRoute`, `getStateForRoute`, `nextStepForRoute`,
`backStepForRoute`, `cancelSessionForRoute`, `evaluateCurrentForRoute`, `loadRouteSession`.

**Session structure for interceptor sessions** (stored in persistent cache):

```
[
  'route_name'     => string  // route's full name — used as owner key on load
  'provider_class' => string  // FQCN — used to reinstantiate provider in requireRouteCompletion
  'scope_id'       => mixed   // ownership token derived from provider->resumeScope()
  'phase'          => string  // FormResumePhase enum value
  'cleaned_form'   => array
  'progress_state' => array
  'created_at'     => int
  'expires_at'     => ?int
  'history'        => array   // non-empty only when provider->isReversible() = true
]
```

#### `oz/oz_settings/oz.request.php` (UPDATED)

New setting:

- `OZ_FORM_RESUME_ACTION_HEADER_NAME` = `'X-OZONE-Form-Resume-Action'`
  Carried by the `X-OZONE-Form-Resume-Action` CORS-exposed header; values: init/state/next/back/cancel/evaluate.

#### `oz/App/Context.php` (UPDATED)

`OZ_FORM_RESUME_ACTION_HEADER_NAME` added to the CORS-allowed headers list.

#### `oz/Router/RouteSharedOptions.php` (UPDATED)

`getInterceptors()` now always injects both `RouteFormDiscoveryInterceptor` (priority 0) AND
`RouteFormResumeInterceptor` (priority 1) as base entries.

### Route-interceptor session lifecycle

```
Client sends:  POST /my-route  +  X-OZONE-Form-Resume: ?1  +  X-OZONE-Form-Resume-Action: init
Server:        routes to POST /my-route  ->  auth/guards/middlewares  ->  resume interceptor fires
               initSessionForRoute():   creates session, returns step-0 form + resume_ref

Client sends:  POST /my-route  +  X-OZONE-Form-Resume: ?1  +  X-OZONE-Form-Resume-Action: next
               + X-OZONE-Form-Resume-Ref: <resume_ref>  +  body: step-0 form data
Server:        nextStepForRoute(): validates step-0 data, returns done: true (single-step provider)

Client sends:  POST /my-route  (no resume header)  +  X-OZONE-Form-Resume-Ref: <resume_ref>
               Route handler runs normally; calls requireRouteCompletion($resume_ref, $ri)
               to retrieve accumulated FormData.
```

### Usage example

```php
$router->post('/checkout', static fn (RouteInfo $ri) => (new CheckoutService($ri))->checkout())
    ->name('checkout')
    ->form(new CheckoutForm())
    ->resumable(RequestScope::STATE)
    ->withAuthenticatedUser();

// In CheckoutService::checkout():
// When X-OZONE-Form-Resume header is present, RouteFormResumeInterceptor handles the session.
// When resume is done, the real handler is called:
public function checkout(): Response
{
    $resume_ref  = $this->ri->getContext()->getRequest()->getHeaderLine('X-OZONE-Form-Resume-Ref');
    $form_data   = ResumableFormService::requireRouteCompletion($resume_ref, $this->ri);
    // ... process $form_data ...
}
```
