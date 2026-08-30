<?php

namespace App\Policies;

use App\Models\Pet;
use App\Models\User;

/**
 * Authorization for pet listings.
 *
 * Every pet route runs through this policy via $this->authorize() in the
 * controller. It is the single place ownership is decided: the legacy app split
 * the same decision across a Form Request that returned true, a controller that
 * sometimes called the policy, and an edit action that checked nothing at all.
 *
 * Publishing, editing and liking all require a verified email address, because
 * each of them either creates public content or notifies another user.
 *
 * The methods type hint User rather than Admin|User on purpose: Nova
 * authenticates App\Models\Admin on its own guard, and Laravel's gate refuses a
 * policy method whose first parameter does not accept the acting user, so an
 * Admin cannot slip through this policy by accident. Nova authorization is
 * Phase 3 and belongs on the Nova resource.
 */
class PetPolicy
{
    /**
     * Browsing listings is public — the home feed serves guests.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * A listing page is public; the payload itself hides the owner-only fields.
     */
    public function view(?User $user, Pet $pet): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isVerified();
    }

    public function update(User $user, Pet $pet): bool
    {
        return $user->isVerified() && $user->getKey() === $pet->user_id;
    }

    public function delete(User $user, Pet $pet): bool
    {
        return $user->isVerified() && $user->getKey() === $pet->user_id;
    }

    public function restore(User $user, Pet $pet): bool
    {
        return $user->isVerified() && $user->getKey() === $pet->user_id;
    }

    public function forceDelete(User $user, Pet $pet): bool
    {
        return $user->isVerified() && $user->getKey() === $pet->user_id;
    }

    /**
     * Liking notifies the owner, so it is gated on a verified account.
     */
    public function like(User $user, Pet $pet): bool
    {
        return $user->isVerified();
    }
}
