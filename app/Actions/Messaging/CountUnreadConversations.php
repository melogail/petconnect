<?php

namespace App\Actions\Messaging;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * How many of a user's conversations are unread, as one aggregate.
 *
 * The number behind the header's messages badge. It is deliberately not
 * derived from a page of conversations: `conversations.previews` renders five
 * rows and the badge has to be about the whole inbox, so counting the page
 * would say "2" to somebody with forty unread threads. Same split, same
 * reason, as Actions\Notifications\BuildNotificationInbox, whose unread total
 * is its own COUNT rather than a filter over the paginator.
 *
 * ## One query, and no models are hydrated to reach it
 *
 * Measured with `DB::getRawQueryLog()` on the development MySQL 8.0.46
 * database: a flat **1 query** per call — 1 for user 1 (5 conversations), 1 for
 * user 5 (3 conversations).
 *
 * The obvious alternative is reading the inbox and filtering it in PHP:
 *
 * ```php
 * $user->conversations()->get()->filter(fn ($c) => $c->isUnreadFor($user))->count();
 * ```
 *
 * Measured against the same three users, that expression costs **11 queries for
 * 5 conversations, 7 for 3 and 3 for 1** — `1 + 2n`, unbounded in the reader's
 * inbox — because `Conversation::isUnreadFor()` opens with
 * `loadMissing(['users', 'lastMessage'])`, which is one `users` query and one
 * `lastMessage` query per hydrated model. It also materialises every row of a
 * list nobody is rendering.
 *
 * An **eager-loaded** variant of the same idea,
 * `->with(['users', 'lastMessage'])->get()->filter(...)`, is flat at **3**
 * (conversations, users, lastMessage) on all three users. That is a fair
 * comparison and it is still three times this Action for a number that fits in
 * an integer; it is quoted here because an earlier revision of this docblock
 * attached the figure 3 to the expression above, which does not cost 3. All
 * four figures agree on the answer — 2 unread for user 1 — and were taken in
 * the same run.
 *
 * The **1 query** is Action-scoped and, when the suite pins it, is measured under
 * phpunit.xml's `SESSION_DRIVER=array`; a real request pays 2-3 more for the
 * `sessions` and `cache` tables while `.env` keeps the `database` drivers. See
 * .ai/rules/app.md.
 *
 * ## The predicate is Conversation::isUnreadFor(), clause for clause
 *
 * `isUnreadFor()` is the definition of unread in this application and this is
 * SQL that has to agree with it, so the three clauses are written in its order:
 *
 * | isUnreadFor()                              | here                                                   |
 * |--------------------------------------------|--------------------------------------------------------|
 * | `lastMessage === null` → false             | `conversations.last_message_at is not null`             |
 * | last message sent by the reader → false    | the correlated `sender_id` subquery `!=` the reader     |
 * | `lastReadAt === null` → true               | `conversation_user.last_read_at is null`                |
 * | `created_at->isAfter($lastReadAt)`         | `last_message_at > conversation_user.last_read_at`      |
 *
 * The comparison is strict on both sides — `isAfter()` is `>`, not `>=` — so a
 * cursor written in the same second as the message it acknowledges reads as
 * read in both.
 *
 * Verified rather than argued: across all 20 users who are in a conversation on
 * the development database — 15 conversations and 277 rows in `messages`, of
 * which 276 are undeleted (`Message::withTrashed()->count()` and
 * `Message::count()`) — this count and
 * `$user->conversations()->get()->filter(fn ($c) => $c->isUnreadFor($user))
 * ->count()` agreed on every one, 0 mismatches.
 *
 * ## Why the time comparison reads `conversations.last_message_at`
 *
 * It stands in for the last message's `created_at`, which it equals by an
 * invariant App\Observers\MessageObserver maintains: `created` advances the
 * column and never walks it backwards, and `deleted` / `restored` /
 * `forceDeleted` recompute it from the remaining messages, so it is
 * `MAX(created_at)` over the conversation's undeleted messages or null when
 * there are none. Actions\Messaging\BuildInbox already leans on the same column
 * for the inbox ordering; this is not a new dependency on it.
 *
 * The sender cannot be answered from a denormalised column — there is none —
 * so it is a correlated subquery, and its tiebreak mirrors
 * `Conversation::lastMessage()`: `latestOfMany('created_at')` resolves ties by
 * `MAX(id)`, which is `order by created_at desc, id desc limit 1` here. Two
 * messages stored in the same second therefore pick the same one either way.
 * It reads through `Message::query()`, so the SoftDeletes scope applies and a
 * deleted last message is not the last message.
 *
 * ## Why not `whereHas('lastMessage')`, which would need no invariant at all
 *
 * Because of what it plans to. `lastMessage()` is a one-of-many relation, so
 * Eloquent answers an existence check on it by joining two **uncorrelated**
 * derived tables that group the whole `messages` table by `conversation_id`.
 *
 * Both plans below were captured in the same run, with `EXPLAIN` on MySQL
 * 8.0.46, against the development schema at **15 conversations and 277 rows in
 * `messages`**, for user 1, who is in 5 of those conversations. Both queries
 * return the same answer (2). The figures are what a toy dataset can say and no
 * more; what is worth reading is the *shape* of each plan, not the row counts.
 *
 * `whereHas('lastMessage')`:
 *
 * ```
 * PRIMARY          conversations     type=ALL    key=NULL                                    rows=15
 * PRIMARY          conversation_user type=eq_ref key=conversation_user_conversation_id_..    rows=1
 * PRIMARY          <derived3>        type=ref    key=<auto_key1>                             rows=2
 * PRIMARY          messages          type=eq_ref key=PRIMARY                                 rows=1
 * DERIVED          <derived4>        type=ALL    key=NULL                                    rows=27
 * DERIVED          messages          type=ref    key=messages_conversation_id_created_at_..  rows=1
 * DERIVED          messages          type=index  key=messages_conversation_id_created_at_..  rows=277
 * ```
 *
 * The last row is the one that decides it: the inner derived table reads
 * **every row in `messages`**, materialised once per request, and no index or
 * statistic changes that, because it has nothing from the outer query to narrow
 * it by. That is a scan of the whole table on an endpoint fetched once per
 * document load by every signed-in visitor.
 *
 * The form used here:
 *
 * ```
 * PRIMARY           conversations     type=ALL    key=NULL                                   rows=15  Using where
 * PRIMARY           conversation_user type=eq_ref key=conversation_user_conversation_id_..   rows=1   Using where
 * DEPENDENT SUBQUERY messages         type=ref    key=messages_conversation_id_created_at_.. rows=18  Using where; Using filesort
 * ```
 *
 * The subquery is an index lookup on `(conversation_id, created_at)` per
 * candidate row, with a filesort over the messages of that one conversation to
 * apply the `id` tiebreak — cheap, and bounded by a conversation's message
 * count rather than by the table's.
 *
 * **The outer half of that plan is not a property to lean on.** MySQL drives
 * from a full scan of `conversations` here, not from the pivot — the opposite
 * of the plan Actions\Messaging\BuildInbox's docblock records for its own query,
 * which drives from `conversation_user`. At 15 rows the optimiser is choosing
 * between two costs that are both ~0, and it is likely to flip as the table
 * grows. So do not read this as "the work is bounded by the reader's own
 * conversation count": an earlier revision of this docblock said exactly that,
 * quoting only the `DEPENDENT SUBQUERY` row, and the full plan does not support
 * it. What survives re-measurement is the comparison between the two forms, not
 * an absolute claim about either.
 *
 * If this ever becomes hot, the thing to re-measure is the driving table, on a
 * `conversations` table large enough for the choice to matter. The `whereHas`
 * version is the one to reach for if the `last_message_at` invariant is ever in
 * doubt, and it is worth re-measuring at that point rather than assumed.
 *
 * ## The one PHPStan error here is left standing, deliberately
 *
 * `where()` accepts a queryable on its `$column` side at runtime — that is what
 * makes the subquery above expressible in the builder at all — but its declared
 * type is `array|Closure|Expression|string`, so passing an
 * `Eloquent\Builder<Message>` is `argument.type`. It is a false positive about
 * a real gap in Laravel's own signature.
 *
 * The two ways to silence it both cost more than they save. `whereRaw()` means
 * writing the correlated subquery as a SQL string, which drops the SoftDeletes
 * global scope that `Message::query()` applies — the clause that makes "a
 * deleted last message is not the last message" true, and the one thing here
 * nothing else would notice going missing. `DB::raw()` on the `$column` side
 * has the same problem plus a binding surface that is empty today and would
 * break silently the day a clause with a binding is added. So the error stays,
 * and it is a line for whoever works the 145-error backlog
 * (.ai/rules/general.md) — not something to paper over with an ignore, a cast
 * or a baseline entry.
 *
 * @see App\Actions\Messaging\BuildInbox for the rows the badge sits above.
 */
class CountUnreadConversations
{
    public function handle(User $user): int
    {
        return $user->conversations()
            ->whereNotNull('conversations.last_message_at')
            ->where(function (Builder $query): void {
                $query->whereNull('conversation_user.last_read_at')
                    ->orWhereColumn('conversations.last_message_at', '>', 'conversation_user.last_read_at');
            })
            ->where(
                Message::query()
                    ->select('messages.sender_id')
                    ->whereColumn('messages.conversation_id', 'conversations.id')
                    ->orderByDesc('messages.created_at')
                    ->orderByDesc('messages.id')
                    ->limit(1),
                '!=',
                $user->getKey(),
            )
            ->count();
    }
}
