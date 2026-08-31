<?php

namespace App\Nova\Policies;

use App\Models\Admin;
use App\Nova\User as UserResource;

/**
 * Authorization for the User resource, on the `admin` guard.
 *
 * This is App\Nova\Policies\UserPolicy and it authorises App\Nova\User. It is
 * **not** App\Policies\UserPolicy, which authorises App\Models\User for
 * members and stays exactly as it is. Moderator-side edit and delete of member
 * content is deliberately unexpressible in the application's own policies —
 * App\Policies\UserPolicy::update, ReviewPolicy::delete and MessagePolicy all
 * type-hint App\Models\User, so an Admin cannot be authorised by them. The hint
 * is a tripwire rather than a gate, though: Gate::canBeCalledWithUser()
 * short-circuits to true for any non-null user and only reads the signature for
 * guests, so an Admin reaching one of those methods raises a TypeError instead
 * of returning false. The `admin` guard is what actually keeps them apart. Nova
 * authorization is built here instead; do not widen those.
 *
 * ## create is false
 *
 * Accounts are created by registering. Nova's create form would bypass
 * Actions\Users\RegisterUser — the unique media-directory draw and its retry,
 * and the verification mail — and produce an account nobody asked for.
 *
 * ## delete is false, and that is what protects the data
 *
 * `users` has no soft deletes and eight foreign keys cascade off it. A
 * database cascade fires no Eloquent events, so Nova's built-in delete would
 * strand roughly 227 rows across likes, saves, reports, reviews, comments,
 * notifications and media, with the media files left on disk forever
 * (.ai/rules/actions.md). Returning false removes the delete button from the
 * detail page, the row menu and the index's bulk actions in one move.
 *
 * `runDestructiveAction` then returns true so that
 * App\Nova\Actions\DeleteUserAccount — which delegates to
 * Actions\Profiles\DeleteUserAccount and cleans up properly — can still run.
 * Nova's authorization order for an action is canRun, then
 * runAction/runDestructiveAction, then update/delete; without this override a
 * DestructiveAction would fall through to `delete` and be refused along with
 * the built-in.
 */
class UserPolicy
{
    /**
     * Determine whether the admin can list member accounts.
     */
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can view a member account.
     */
    public function view(Admin $admin, UserResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can create member accounts.
     */
    public function create(Admin $admin): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can edit a member's profile.
     *
     * Allowed, and bounded by the resource rather than here: App\Nova\User
     * exposes only name, username, bio, phone, address and locale on its form.
     * Email, password and is_active are not writable fields at all.
     */
    public function update(Admin $admin, UserResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can delete a member account with Nova's
     * built-in delete.
     *
     * No. See the class docblock: only App\Nova\Actions\DeleteUserAccount may.
     */
    public function delete(Admin $admin, UserResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin may run a destructive action.
     *
     * True, which is what lets DeleteUserAccount past the `delete` refusal
     * above while the built-in delete stays off.
     */
    public function runDestructiveAction(Admin $admin, UserResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can restore a member account.
     *
     * `users` does not soft delete; there is nothing to restore.
     */
    public function restore(Admin $admin, UserResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can permanently delete a member account.
     */
    public function forceDelete(Admin $admin, UserResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can replicate a member account.
     *
     * Replicating a user would copy a unique email and a unique
     * media_directory_name into a new row; it is meaningless here.
     */
    public function replicate(Admin $admin, UserResource $resource): bool
    {
        return false;
    }
}
