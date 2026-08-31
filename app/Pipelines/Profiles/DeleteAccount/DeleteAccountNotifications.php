<?php

namespace App\Pipelines\Profiles\DeleteAccount;

use Closure;

/**
 * Remove the notifications addressed to the account.
 *
 * `notifications.notifiable_type` / `notifiable_id` is a morph pair, so the
 * table has no foreign key to the user and nothing cascades. Left behind, the
 * rows are unreachable — every read goes through `$user->notifications()` and
 * there is no user — but they are rows, they carry an excerpt of whatever was
 * said, and they accumulate for the lifetime of the application.
 *
 * Deleted through the relation rather than by a hand-written
 * `where('notifiable_type', 'user')`, which is both shorter and safe from the
 * morph-alias trap: the relation fills the morph columns from the model.
 *
 * Notifications the account *caused* — a like it gave, a comment it wrote —
 * belong to their recipients and are deliberately left alone. Their payloads
 * are self-contained pointers (ids plus a translation key), so a recipient
 * still sees a coherent row; the deep link 404s, which is the honest outcome.
 */
class DeleteAccountNotifications
{
    public function handle(DeleteAccountContext $context, Closure $next): mixed
    {
        $context->user->notifications()->delete();

        return $next($context);
    }
}
