<?php

namespace App\Nova\Policies;

use App\Models\Admin;
use App\Nova\Pet as PetResource;

/**
 * Authorization for the Pet resource, on the `admin` guard.
 *
 * Moderating a listing means editing it (a misleading title, an address that
 * should not be public) and retiring it. Both are allowed. Two things are not:
 *
 * - **create** — a listing belongs to a member and is published through
 *   Actions\Pets\CreatePet, which does the media and featured-photo work Nova's
 *   create form knows nothing about. An admin-authored listing would also be
 *   attributed to whichever owner was picked, with no record that it was not
 *   theirs.
 * - **forceDelete** — `pets` soft deletes, so the ordinary delete retires the
 *   listing and keeps its photos and comment thread available for moderation.
 *   Nova's built-in force delete is `$model->forceDelete()` and nothing else,
 *   which reaches none of the listing's polymorphic children: comments, likes
 *   and saves hang off morph columns that carry no foreign key, the reports
 *   against those comments are stranded twice over, and the media *files* are
 *   removed from an Eloquent `deleting` hook a database cascade never fires.
 *
 * There is now a purge, and it is deliberately not this button.
 * App\Nova\Actions\PurgePetListing runs Actions\Pets\PurgePet, which collects
 * the comment subtree and clears each child explicitly in one transaction, and
 * `runDestructiveAction` below is what lets it past the `forceDelete` refusal —
 * the same arrangement UserPolicy and DeleteUserAccount use for accounts. The
 * built-in stays off so there is exactly one route to an irreversible delete.
 *
 * (Pipelines\Profiles\DeleteAccount\PurgeOwnedListings is still not it: its own
 * docblock records that the listings' comments, likes and saves are already
 * gone by the time it runs, so on its own it would strand every one of them.)
 */
class PetPolicy
{
    /**
     * Determine whether the admin can list listings.
     */
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can view a listing.
     */
    public function view(Admin $admin, PetResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can create listings.
     */
    public function create(Admin $admin): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can update a listing.
     */
    public function update(Admin $admin, PetResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can retire a listing.
     *
     * `pets` soft deletes, so this hides the listing rather than destroying it.
     */
    public function delete(Admin $admin, PetResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can restore a retired listing.
     */
    public function restore(Admin $admin, PetResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin may run a destructive action.
     *
     * True, and load-bearing: without it PurgePetListing would fall through to
     * `forceDelete` below and be refused along with Nova's own button, leaving
     * no route to a permanent delete — which is the dead end DeleteCategory's
     * "move or permanently delete those listings first" used to walk into.
     */
    public function runDestructiveAction(Admin $admin, PetResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can permanently delete a listing.
     *
     * No — see the class docblock. The purge is PurgePetListing.
     */
    public function forceDelete(Admin $admin, PetResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can replicate a listing.
     */
    public function replicate(Admin $admin, PetResource $resource): bool
    {
        return false;
    }
}
