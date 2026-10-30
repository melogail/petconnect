---
paths:
  - 'app/Nova/Actions/**'
---

# Nova Actions

## Every Nova bulk action wraps the whole selection in a transaction and catches Throwable
Third and fourth instance of the same fix: `DeleteCommentThread` and `DeleteReview` did a bare `$models->each(...)`, so a throw on the third of five left two deleted, three intact and an unreadable 500 — exactly what `.ai/rules/nova-policies.md` records as already fixed on `DeleteUserAccount`. The per-model Action's own transaction covers one row; Nova hands `handle()` the whole selection, which is a second, wider unit of work.

Shape, non-negotiable for any new bulk action: `DB::transaction` around `$models->each(...)`, `catch (Throwable) { report($e); return ActionResponse::danger('Nothing was deleted. ... rolled back. The failure has been logged.'); }`. Say "nothing happened" in a sentence rather than leaving the admin to guess which half did.

## The report() call in a Nova action is untestable — "The failure has been logged" is unverified
Nova swaps the container's `ExceptionHandler` for `NovaExceptionHandler` during a Nova request, so `Exceptions::fake()` never observes a `report($e)` made inside a Nova action's `catch`. `Exceptions::reported()` comes back empty while the danger response asserts correctly. Measured, not reasoned.

Consequence, stated plainly: all five hardened Nova bulk actions return a message promising "The failure has been logged", and **nothing in the suite verifies the logging still happens**. Delete the `report($e)` line from any of them and the tests stay green while the message becomes a lie. Keep the `report()` call; treat it as unprotected code and do not remove or refactor it on the strength of a green run.

Declined alternative, so it is not rediscovered: `Log::spy()` does observe it, and was rejected — it couples the test to how the framework's handler happens to log, not to the behaviour we care about.

## An action's URI key is the slug of its `$name`, not of its class
`Action::uriKey()` is `Str::slug($this->name())`, so a test posting `/nova-api/{resource}/action?action=...` must use the slug of the **display name**: `ChangeReportStatus` with `$name = 'Change Status'` answers at `change-status`, not `change-report-status`. The symptom is a bare **404** from the action endpoint with the resource, admin guard and policy all correct. Measured 2026-10-30 on the first cut of the ticket reply action (then `RespondToTicket`, since rebuilt as `ReplyToTicket`): `$name = 'Respond'` 404'd at `?action=respond-to-ticket` until the name was changed to `'Respond to Ticket'`. Name the action so its slug reads like the class, or override `uriKey()` — do not reach for the policy when the endpoint 404s.

## An action has no constructor injection: `make()` is `new static`, not the container
`Laravel\Nova\Makeable::make()` does `new static(...$arguments)`, and a resource's `actions()` calls `SomeAction::make()` with no arguments, so a promoted constructor dependency is never supplied. The symptom is a **500** on every request that resolves the resource's actions — the index, the detail page and the action endpoint alike — with `ArgumentCountError: Too few arguments to function App\Nova\Actions\X::__construct(), 0 passed in vendor/laravel/nova/src/Makeable.php`. Measured 2026-10-30 on `ReplyToTicket` and `ChangeTicketStatus`, which each injected an `App\Actions\Tickets\*` class. Resolve collaborators inside `handle()` with `app(...)` instead; a one-line comment at the call site says why, so the next reader does not "tidy" it back into the constructor.
