<?php

namespace App\Policies;

use App\Models\User;

/**
 * Authorization for user profiles.
 *
 * Four methods, one per route that decides anything about a profile:
 * `view` for the public page (Web\ProfileController::show), `like` for the
 * like button on it (Web\ProfileController::toggleLike), `update` and
 * `delete` for the account form (Settings\ProfileController). Nothing else is
 * declared — a method with no call site is the shape .ai/rules/policies.md and
 * ReportPolicy both argue against, and adding one when its route arrives keeps
 * this file an accurate description of what the application decides.
 *
 * There is no `viewAny`: nothing lists users. A directory of accounts is a
 * feature, not a missing policy method.
 *
 * ## The public read is a decision, not the absence of a check
 *
 * `view` takes a nullable User and returns true for a guest, because a profile
 * is a public page — a shared listing links to its owner, and a review is about
 * a named person. .ai/rules/controllers.md is explicit that a guest-visible
 * page is recorded here rather than by omitting `$this->authorize()`, so
 * ProfileController::show calls it with a possibly-null viewer.
 *
 * The legacy app got the opposite of this wrong in a way worth naming, because
 * it looked right: `profile.show` was declared with `->withoutMiddleware('auth')`
 * inside a `['auth', 'verified']` group. Dropping `auth` while keeping
 * `verified` means EnsureEmailIsVerified runs for a guest, finds no user, and
 * redirects to `verification.notice` — so the one route explicitly marked
 * public was unreachable to the public. Here the route simply sits outside the
 * group.
 *
 * ## Deactivation is enforced here for reads
 *
 * A deactivated account's profile is not readable, by anyone. That is one of
 * the things `is_active` now means — see Http\Middleware\EnsureAccountIsActive
 * for the whole definition — and it lives in a policy rather than in a route
 * binding on purpose: .ai/rules/app.md draws the line at "hidden by a global
 * scope goes in resolveRouteBinding, hidden by state or ownership goes in a
 * policy", and `is_active` is state. Putting it in User::resolveRouteBinding()
 * would also have reached App\Enums\Reviewable::findVisibleOrFail(), silently
 * changing which users can be reviewed and which reviews bind.
 *
 * There is deliberately **no "except the owner" carve-out**, and that is a
 * measured decision rather than an omission. EnsureAccountIsActive logs a
 * deactivated account out on its very next request, so it can never be the
 * viewer: a `$user?->getKey() === $profile->getKey()` clause here would be a
 * branch nothing on the web guard can reach — exactly the call-site-less rule
 * this file's opening paragraph argues against.
 *
 * Verified by request, and the mechanism is worth stating exactly, because the
 * obvious guess is wrong: asking for its own profile as a deactivated account
 * does not reach this method at all. EnsureAccountIsActive runs in the `web`
 * group, ahead of the route, and ends the session — a browser request is
 * redirected to `login` with a status, a JSON one is aborted 403. Either way
 * the controller that would call `authorize()` never runs. So the carve-out is
 * not merely a branch that always evaluates false; it is a branch in a method
 * no deactivated viewer can reach. `view` returning false for that pair is
 * still the correct answer — Gate::allows() asked directly agrees, and that is
 * what the policy test pins — but the 403 a client sees comes from the
 * middleware, not from here.
 *
 * ## Query-free by construction
 *
 * Every method decides from `is_active` or the primary key, both already on
 * the models in hand. Nothing here loads a relation, so these are safe to ask
 * per row if a payload ever needs to — Http\Resources\Profile\ProfileResource
 * asks `update` once per profile, and would still be free if it asked per row.
 */
class UserPolicy
{
    /**
     * Anyone may read an active profile, including guests. A deactivated one is
     * readable by nobody — see the note above on why there is no owner
     * exception.
     */
    public function view(?User $user, User $profile): bool
    {
        return $profile->isActive();
    }

    /**
     * Liking a profile notifies the person it is about, so it needs a verified
     * account — the same bar PetPolicy::like and CommentPolicy::like set, and
     * for the same reason.
     *
     * The second clause has no counterpart on those two, and is not decoration:
     * `like` is the one write in the application that names a *user* as its
     * target, so it is the one that has to re-derive what `view` already
     * decided about a deactivated account. Without it, a profile whose page is
     * a 403 for everybody would still be likeable at a guessable sequential id
     * — the same shape as a comment on a retired listing, decided in a policy
     * here rather than in a route binding because `is_active` is state
     * (.ai/rules/app.md).
     *
     * There is deliberately no self-like clause. Neither of the other two like
     * policies has one, and a self-like is already silent: LikeObserver filters
     * the acting user out of the recipients, and User::likeNotificationRecipients()
     * is only ever that one person. Refusing it would be a rule with no
     * consequence, and the page already ships `is_self` for a client that would
     * rather not draw the control.
     *
     * Query-free like the rest of this file: `email_verified_at` and
     * `is_active` are columns on models already in hand.
     */
    public function like(User $user, User $profile): bool
    {
        return $user->isVerified() && $profile->isActive();
    }

    /**
     * Only the account holder edits their own profile.
     *
     * Moderator-side edits live on the Nova resource and the `admins` guard.
     * They cannot be expressed here: this method type hints App\Models\User, so
     * an Admin cannot be authorised by it. The hint is a tripwire rather than a
     * gate — Gate::canBeCalledWithUser() short-circuits to true for any non-null
     * user and only reads the signature for guests, so an Admin reaching this
     * method raises a TypeError rather than returning false — and the guard is
     * what keeps them apart.
     */
    public function update(User $user, User $profile): bool
    {
        return $user->getKey() === $profile->getKey();
    }

    /**
     * Only the account holder deletes their own account.
     *
     * Verification is deliberately *not* required. The delete route sits behind
     * `verified` today, which is the starter kit's arrangement, but the rule
     * that matters is here: somebody who never confirmed their email must still
     * be able to remove the account they created, and making deletion the one
     * thing an unverified user cannot do would be the wrong way round.
     *
     * Password confirmation is a separate question and is not a policy's: the
     * password is a field on the request, and Http\Requests\Profile\
     * DeleteProfileRequest validates it with `current_password`.
     */
    public function delete(User $user, User $profile): bool
    {
        return $user->getKey() === $profile->getKey();
    }
}
