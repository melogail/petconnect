<?php

namespace App\Pipelines\Profiles\DeleteAccount;

use Closure;

/**
 * Delete the user row, and let the remaining foreign keys cascade.
 *
 * Runs last. Every polymorphic child that would have been stranded is already
 * gone, so what the cascade takes from here — `conversation_user`, the
 * account's own `messages`, the `likes`, `saves` and `reports` it filed, and
 * anything left of `pets`, `comments` and `reviews` — has nothing hanging off
 * it. The Action holds the whole flow in one transaction, so there is no window
 * in which a child points at a user that no longer exists.
 *
 * The delete goes through the model rather than a query builder, which is what
 * runs medialibrary's `deleting` hook and removes the account's avatar and its
 * conversions from the disk. That hook is the reason this is `$user->delete()`
 * and not `User::whereKey(...)->delete()`.
 *
 * `messages.pinned_by` is `nullOnDelete` rather than a cascade, so a message
 * this account pinned stays pinned in somebody else's thread with no pinner
 * recorded. That is the schema's decision and the right one: removing the pin
 * would silently edit a conversation two other people are still having.
 */
class DeleteAccountRecord
{
    public function handle(DeleteAccountContext $context, Closure $next): mixed
    {
        $context->markDeleted((bool) $context->user->delete());

        return $next($context);
    }
}
