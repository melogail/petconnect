<?php

namespace App\Policies;

use App\Models\User;

/**
 * Authorization for reports.
 *
 * One method, because reports have exactly one user-facing action: filing one.
 * ReportController::store calls it with $this->authorize() per
 * .ai/rules/controllers.md.
 *
 * ## Why there is no viewAny, view, update or delete
 *
 * A report is a message to moderation, not content its author owns. There is no
 * route that reads a report back, none that edits one, and none that withdraws
 * one, so a policy method for any of them would be a rule with no call site —
 * which is exactly the shape that made the legacy ReviewPolicy misleading (it
 * inherited a full CRUD set from an abstract base while its controller called
 * none of it). Adding the method when the route arrives keeps the file an
 * accurate description of what the application actually decides.
 *
 * Triage — reading the queue, changing `status` — happens in Nova on the
 * `admins` guard and is Phase 3's, on the Nova resource. It cannot be expressed
 * here in any case: these methods type hint App\Models\User, so an Admin cannot
 * be authorised by this class. The hint is a tripwire rather than a gate —
 * Gate::canBeCalledWithUser() short-circuits to true for any non-null user and
 * only reads the signature for guests, so an Admin reaching one of these raises
 * a TypeError rather than returning false, and the `admin` guard is what keeps
 * them apart in practice. That separation is deliberate — the legacy
 * abstract App\Policies\Policy took `Admin|User` and returned true for any
 * Admin, which put moderation on the same gate as the web app's own
 * authorization.
 *
 * The rest of the decision is not about the acting user and is not here.
 * Whether the target exists and is still visible, whether the reporter is
 * answerable for it, and whether they have already reported it are
 * Pipelines\Reports\SubmitReport's — they depend on a model resolved from the
 * URL, which a policy asked before resolution cannot see.
 */
class ReportPolicy
{
    /**
     * Filing a report puts an item in a moderator's queue and names another
     * user's content, so it needs a verified account. Requiring verification is
     * also the cheapest available brake on someone creating accounts to bury
     * the queue; `throttle:reports` on the route is the other.
     */
    public function create(User $user): bool
    {
        return $user->isVerified();
    }
}
