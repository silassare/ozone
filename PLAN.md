# ResumableFormService — Remaining Tasks / Backlog

## A. Unit Tests (HTTP flow not yet covered)

`tests/Forms/ResumableFormServiceTest.php` currently only covers `FormResumeProgress` and
route constants. The following HTTP flow scenarios are pending:

- `initSession`: no `initForm` -> phase=STEPS, returns first step form
- `initSession`: with `initForm` -> phase=INIT, returns init form + `resume_ref`
- `initSession`: unknown provider -> throws `NotFoundException`
- `initSession`: `requiresRealContext=true` provider -> throws `BadRequestException`
- `nextStep` on INIT session: validates init form, transitions to STEPS, step index stays 0
- `nextStep` on INIT session: invalid data -> throws `InvalidFormException`
- `nextStep` on STEPS: advances progress, returns next form
- `nextStep`: final step -> `done=true`
- `nextStep`: already done -> throws `BadRequestException`
- `nextStep`: wrong `scope_id` -> throws `ForbiddenException`
- `nextStep`: unknown `resume_ref` -> throws `NotFoundException`
- `getState`: returns correct form at INIT, STEPS, DONE phases
- `cancelSession`: deletes session, returns `done=true`
- `requireCompletion`: done session -> returns `FormData`
- `requireCompletion`: not-done session -> throws `ForbiddenException`
- `isReversible=true`: `backStep` restores previous state
- `isReversible=false`: `backStep` throws `BadRequestException`
- `notBefore()` in future -> throws `FormResumeNotYetActiveException`
- `deadline()` in past -> throws `FormResumeExpiredException`
- `dropSession`: after `checkRouteForm` loads session, ref is invalidated

## B. Integration Test

Create `tests/Integration/Forms/ResumableFormServiceTest.php`:

- Multi-step provider (>= 3 steps), `DynamicValue` in `if()` conditions
- At least one server-only `expect()` rule to exercise `/evaluate`
- Known `totalSteps()` to verify progress counter
- Full flow: init -> next (init form) -> multiple nexts -> done
- Cancel mid-flow: subsequent `getState` throws `NotFoundException`

## C. Rate Limiting

Add `rateLimit(new IPRateLimit(...))` to the `POST /form/:provider/init` route declaration.

## D. Session Abandonment/Expiry Hooks (deferred)

Requires cache-layer expiry callbacks — not yet available.
