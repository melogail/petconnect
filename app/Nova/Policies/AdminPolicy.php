<?php

namespace App\Nova\Policies;

use App\Models\Admin;
use App\Nova\Admin as AdminResource;

/**
 * Authorization for the Admin resource, on the `admin` guard.
 *
 * Registered against App\Nova\Admin rather than App\Models\Admin (Nova 5's
 * `$policy` property, resolved by Laravel\Nova\Util::resolveResourceOrModel-
 * ForAuthorization), so it applies to Nova operations and to nothing else.
 * That separation is the whole reason these live in App\Nova\Policies: the
 * application's own policies in App\Policies type-hint App\Models\User, so an
 * Admin can never be authorised by them — by design. Note what that hint is and
 * is not: Gate::canBeCalledWithUser() short-circuits to true for any non-null
 * user and only reads the signature for guests, so an Admin reaching one of
 * those methods is a TypeError rather than a `false`. It is a tripwire, not a
 * gate. What actually keeps an Admin out of them is the `admin` guard.
 *
 * Back-office accounts are peers: any admin may create, edit and remove
 * another. The one restriction is that an admin cannot delete themselves,
 * which is not a permission question but a "do not lock the last person out
 * mid-session" one.
 */
class AdminPolicy
{
    /**
     * Determine whether the admin can list back-office accounts.
     */
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can view a back-office account.
     */
    public function view(Admin $admin, AdminResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can create back-office accounts.
     */
    public function create(Admin $admin): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can update a back-office account.
     */
    public function update(Admin $admin, AdminResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can delete a back-office account.
     *
     * Never your own: deleting the row you are authenticated as leaves the
     * session pointing at a user that no longer exists, and if you are the
     * only admin it locks the back office for good.
     */
    public function delete(Admin $admin, AdminResource $resource): bool
    {
        return $admin->getKey() !== $resource->model()?->getKey();
    }

    /**
     * Determine whether the admin can restore a back-office account.
     *
     * `admins` does not soft delete, so there is nothing to restore.
     */
    public function restore(Admin $admin, AdminResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can permanently delete a back-office account.
     *
     * `admins` does not soft delete; delete() above is already permanent.
     */
    public function forceDelete(Admin $admin, AdminResource $resource): bool
    {
        return false;
    }
}
