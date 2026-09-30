<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

/**
 * `$request->user()` is typed `Admin|User|null` because the application has
 * two guards, and every Action takes a `User`. The API controllers resolve
 * the caller through these two accessors so the type is narrowed once, in
 * one place, and a route that is mis-grouped (a write left outside
 * `auth:sanctum`) fails loudly here rather than as a null deref in an Action.
 *
 * `viewer()` is for public reads, where a guest is a legitimate caller.
 * `actor()` is for everything behind `auth:sanctum`, where the middleware
 * has already guaranteed a `User` and anything else is a programming error.
 */
trait ResolvesRequestUser
{
    protected function viewer(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    protected function actor(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
