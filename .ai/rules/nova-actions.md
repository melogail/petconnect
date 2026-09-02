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
