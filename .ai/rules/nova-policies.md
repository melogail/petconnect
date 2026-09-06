---
paths:
  - 'app/Nova/Policies/**'
---

# Nova Policies

## An irreversible delete is a DestructiveAction behind a false policy method, never a true one
`authorizedToDelete: true` (or `forceDelete: true`) draws the control on the detail page, in the row menu **and in the index's bulk bar**, where "select all" is one click and there is no undo. It is never the right way to enable a narrow, checked deletion.

The pattern, now used three times: the policy returns `false` for `delete`/`forceDelete`, returns `true` for `runDestructiveAction`, and a `DestructiveAction` does the work behind a guard that produces a sentence instead of a driver error or a silent partial. Nova's authorization order is canRun, then runAction/runDestructiveAction, then update/delete — which is exactly what makes this work.

- `CategoryPolicy` + `DeleteCategory` — refuses a category with listings, counted `withTrashed()`.
- `UserPolicy` + `DeleteUserAccount` — routes every account delete through the cleanup Action.
- `ReportPolicy` + `PurgeOrphanedReports` — `delete` was `true` for the narrow job of clearing a report whose `reportable` is null; that gave an admin the ability to bulk-destroy the whole moderation queue, the evidence `update: false` exists to protect. Now `delete: false` and the action refuses any selected report whose target still resolves.
- `PetPolicy` + `PurgePetListing` — `forceDelete` stays `false` because Nova's built-in is `$model->forceDelete()` and nothing else.

A bulk action is atomic and reports a sentence on failure: `DB::transaction` around the whole selection, `catch (Throwable)` returning `ActionResponse::danger(...)`. `DeleteUserAccount` iterated with neither, so a throw on the third of five left two accounts deleted, three intact and a 500 the admin could not read.
