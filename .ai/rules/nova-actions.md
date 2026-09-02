---
paths:
  - 'app/Nova/Actions/**'
---

# Nova Actions

## Every Nova bulk action wraps the whole selection in a transaction and catches Throwable
Third and fourth instance of the same fix: `DeleteCommentThread` and `DeleteReview` did a bare `$models->each(...)`, so a throw on the third of five left two deleted, three intact and an unreadable 500 — exactly what `.ai/rules/nova-policies.md` records as already fixed on `DeleteUserAccount`. The per-model Action's own transaction covers one row; Nova hands `handle()` the whole selection, which is a second, wider unit of work.

Shape, non-negotiable for any new bulk action: `DB::transaction` around `$models->each(...)`, `catch (Throwable) { report($e); return ActionResponse::danger('Nothing was deleted. ... rolled back. The failure has been logged.'); }`. Say "nothing happened" in a sentence rather than leaving the admin to guess which half did.
