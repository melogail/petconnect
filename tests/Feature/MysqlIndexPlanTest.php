<?php

use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What InnoDB actually does with the indexes tests/Feature/DatabaseIndexShapeTest
 * declares, for the four queries those indexes were added for.
 *
 * ## Why this file skips almost every time you run it
 *
 * `phpunit.xml` runs SQLite in memory; `.env` is MySQL 8.0.46 and so is
 * whatever production becomes (.ai/rules/migrations.md). An
 * `EXPLAIN QUERY PLAN` measured on SQLite proves nothing about the driver the
 * application runs on — the two optimisers differ in exactly the place index
 * work lives, SQLite reaching for `USE TEMP B-TREE FOR ORDER BY` where InnoDB
 * uses a second index and a backward scan. So this file states the InnoDB
 * expectation and refuses to pretend on any other driver: it skips unless
 * `DB_CONNECTION` points at MySQL. Seeing it skip in the normal suite is the
 * intended outcome, not a broken test.
 *
 * ## How to run it for real, without dropping the dev database
 *
 *     DB_CONNECTION=mysql DB_DATABASE=petconnect_index_plan vendor/bin/pest --filter=MysqlIndexPlan
 *
 * **Both overrides are mandatory and the database must already exist and must
 * never be `petconnect`.** `LazilyRefreshDatabase` runs `migrate:fresh`, which
 * drops every table it finds. `phpunit.xml`'s `<env>` entries are non-forcing,
 * so `DB_CONNECTION=mysql` on its own falls back to `DB_DATABASE=:memory:` and
 * fails safely — but any invocation that also supplies `DB_DATABASE`, or a
 * shell that exports it, drops whatever it points at. `.ai/rules/migrations.md`
 * records an orchestrator issuing exactly this class of command against the
 * live `petconnect` dev database; read that note before running anything here.
 *
 * The plans below were measured on **MySQL 8.0.46** (`php artisan db:show` on
 * the dev connection). MariaDB is excluded in `beforeEach()`: its optimiser and
 * its EXPLAIN columns differ, so it would produce noise rather than a verdict.
 *
 * ## What it asserts, and why those three things
 *
 * For each query: the index InnoDB chose (`key`), that the access is an
 * indexed equality lookup rather than a scan (`type`), and that the ordering
 * came out of the index rather than out of a sort of the whole matching set
 * (`Extra` has no `Using filesort`). Those are the three ways an index
 * silently stops being used while every functional test stays green.
 *
 * ## The query shapes
 *
 * Each query is built from the same relations and scopes the application uses,
 * so none of them is invented here:
 *
 * - the bell's page — `BuildNotificationInbox`, `$user->notifications()->paginate()`.
 *   No `->latest()` here: `HasDatabaseNotifications::notifications()` already
 *   ends in one, and the Action's docblock is explicit that the second call was
 *   removed and must not come back. Adding it to this query would pin a
 *   `order by created_at desc, created_at desc` the application never emits.
 * - the bell's badge — `BuildNotificationInbox`, `$user->unreadNotifications()->count()`
 * - the root thread — `ListCommentThread`, read off `$commentable->rootComments()->latest()->paginate()`
 * - the profile listings — `LoadProfileForDisplay::listings()`, `$profile->pets()->latest('created_at')->latest('id')->paginate()`
 *
 * The `withCount()` / `withExists()` subquery columns two of those Actions add
 * are deliberately left off. They select from `likes`, `comments` and `saves`,
 * so they add EXPLAIN rows about other tables' indexes and change nothing about
 * how the outer table is reached — which is what this file is pinning. That
 * omission is also load-bearing for `assertPlanUsesIndex()`, which reads the
 * outer access path only: `ListCommentThread`'s `withCount('replies')` is a
 * correlated subquery on `comments` itself, so a plan built with it produces
 * two rows naming that table.
 */
beforeEach(function (): void {
    $connection = DB::connection();

    if ($connection->getDriverName() !== 'mysql') {
        $this->markTestSkipped(sprintf(
            'EXPLAIN plans are InnoDB-specific and this suite runs %s (phpunit.xml), while dev and '
            .'production are MySQL 8.0.46. A plan measured on another driver would say nothing about '
            .'the driver the application runs on, so this file only runs when the test connection is '
            .'MySQL. To run it: DB_CONNECTION=mysql DB_DATABASE=petconnect_index_plan vendor/bin/pest '
            .'--filter=MysqlIndexPlan — never against the petconnect dev database, migrate:fresh drops it.',
            $connection->getDriverName(),
        ));
    }

    $serverVersion = (string) $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);

    if (str_contains(strtolower($serverVersion), 'mariadb')) {
        $this->markTestSkipped(sprintf(
            'These plans were measured on MySQL 8.0.46 and this connection reports [%s]. MariaDB '
            .'forked the optimiser and reports different EXPLAIN columns, so running them here would '
            .'produce noise rather than a verdict on the indexes.',
            $serverVersion,
        ));
    }
});

/**
 * Enough rows, skewed enough, that the optimiser has a reason to prefer an
 * index over a scan: the row the queries below pin owns roughly 5% of each
 * table, and the rest is spread over other owners.
 *
 * Written with bulk inserts off one factory-built template rather than a
 * thousand factory calls, because the shape of the rows is irrelevant here and
 * only the volume and the skew are not.
 *
 * ## The statistics caveat — read this before calling a failure a regression
 *
 * `ANALYZE TABLE` is deliberately not called: it implicitly commits, which
 * would leave this fixture behind in the scratch database instead of rolling
 * back with `LazilyRefreshDatabase`'s transaction. The consequence is that
 * InnoDB's *persistent* statistics never see these rows at all — they are
 * inserted inside an uncommitted transaction. The plans below come out right
 * because the optimiser's index dives read the uncommitted pages at optimize
 * time, which is a dependency on a tunable (`eq_range_index_dive_limit`) rather
 * than on the indexes themselves.
 *
 * So a failure in this file is not automatically an index regression. Check the
 * server version and that tunable first; the declared index shapes are pinned
 * driver-independently in tests/Feature/DatabaseIndexShapeTest.php, and if that
 * file is green the indexes are still declared as intended and what changed is
 * the optimiser's estimate.
 *
 * @return array{owner: User, pet: Pet, rootComment: Comment}
 */
function seedIndexPlanFixture(): array
{
    $owner = User::factory()->create();
    $others = User::factory()->count(4)->create();

    $pet = Pet::factory()->for($owner)->create();

    $petTemplate = $pet->getAttributes();
    unset($petTemplate['id']);

    $petRows = [];

    foreach (range(1, 999) as $offset) {
        $petRows[] = array_merge($petTemplate, [
            'user_id' => $offset % 20 === 0 ? $owner->getKey() : $others[$offset % 4]->getKey(),
            'created_at' => now()->subMinutes($offset),
            'updated_at' => now()->subMinutes($offset),
        ]);
    }

    foreach (array_chunk($petRows, 250) as $chunk) {
        DB::table('pets')->insert($chunk);
    }

    $petIds = Pet::query()->pluck('id')->all();

    $rootComment = Comment::factory()->forPet($pet)->for($owner)->create();

    $commentTemplate = $rootComment->getAttributes();
    unset($commentTemplate['id']);

    $commentRows = [];

    foreach (range(1, 999) as $offset) {
        $onOurPet = $offset % 20 === 0;

        $commentRows[] = array_merge($commentTemplate, [
            'commentable_id' => $onOurPet ? $pet->getKey() : $petIds[$offset % count($petIds)],
            'parent_id' => $onOurPet && $offset % 40 === 0 ? $rootComment->getKey() : null,
            'created_at' => now()->subMinutes($offset),
            'updated_at' => now()->subMinutes($offset),
        ]);
    }

    foreach (array_chunk($commentRows, 250) as $chunk) {
        DB::table('comments')->insert($chunk);
    }

    $notifiableType = $owner->getMorphClass();
    $notificationRows = [];

    foreach (range(1, 1000) as $offset) {
        $mine = $offset % 20 === 0;

        $notificationRows[] = [
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\CommentPosted',
            'notifiable_type' => $notifiableType,
            'notifiable_id' => $mine ? $owner->getKey() : $others[$offset % 4]->getKey(),
            'data' => '{"message":"index plan fixture"}',
            'read_at' => $offset % 3 === 0 ? now() : null,
            'created_at' => now()->subMinutes($offset),
            'updated_at' => now()->subMinutes($offset),
        ];
    }

    foreach (array_chunk($notificationRows, 250) as $chunk) {
        DB::table('notifications')->insert($chunk);
    }

    return ['owner' => $owner, 'pet' => $pet, 'rootComment' => $rootComment];
}

/**
 * The EXPLAIN row for the outermost access path, and the three expectations
 * that make an index worth its write cost.
 *
 * Every failure message names the query, the index it was supposed to use and
 * what InnoDB did instead, so a failure reads as a plan regression rather than
 * as a mismatched string.
 *
 * The row is picked by `id = 1` — the outer `SELECT` — rather than by table
 * name. Filtering on the name breaks the moment a query carries a subquery on
 * the same table (see the `withCount('replies')` note at the top of this file):
 * two rows would then match and the count assertion would trip on a plan that
 * is perfectly fine. The table is still asserted, just as a check rather than
 * as the filter.
 *
 * Accepted access types, all of them indexed lookups: `const` and `eq_ref` are
 * strictly better than `ref` and must not read as regressions; `ref_or_null` is
 * `ref` plus a null pass, which MySQL reports for `key_col = expr OR key_col IS
 * NULL` — *not*, as this comment once claimed, for a plain `deleted_at IS NULL`
 * or `parent_id IS NULL`, which is ordinary `ref` with `ref: const`. No query
 * here emits that OR shape today; the allowance is forward latitude for one
 * that does. `ALL` is a table scan and `index` is a full index scan, and
 * `range` gave up the equality lookup; none of the three is accepted.
 *
 * @param  Builder<*>|Relation<*, *, *>  $query
 * @param  string|list<string>  $expectedIndexes  the index, or the set of indexes any of which is correct
 */
function assertPlanUsesIndex(string $label, Builder|Relation $query, string $table, string|array $expectedIndexes): void
{
    $acceptableIndexes = (array) $expectedIndexes;

    $outerRows = $query->explain()
        ->filter(fn (object $row): bool => (int) $row->id === 1)
        ->values();

    test()->assertCount(1, $outerRows, sprintf(
        '%s: expected EXPLAIN to report exactly one outermost access path, got %d.',
        $label,
        $outerRows->count(),
    ));

    $plan = $outerRows->first();

    test()->assertSame($table, $plan->table, sprintf(
        '%s: expected the outermost access path to be on `%s`, EXPLAIN reported `%s`.',
        $label,
        $table,
        $plan->table ?? 'none',
    ));

    test()->assertContains($plan->key, $acceptableIndexes, sprintf(
        '%s: expected InnoDB to read `%s` through [%s], it chose [%s]. possible_keys: [%s].',
        $label,
        $table,
        implode(' or ', $acceptableIndexes),
        $plan->key ?? 'none',
        $plan->possible_keys ?? 'none',
    ));

    test()->assertContains($plan->type, ['const', 'eq_ref', 'ref', 'ref_or_null'], sprintf(
        '%s: expected an indexed equality lookup on `%s` (access type ref), got [%s]. '
        .'ALL is a table scan, index is a full index scan and range dropped the equality lookup.',
        $label,
        $table,
        $plan->type,
    ));

    test()->assertStringNotContainsString('Using filesort', (string) $plan->Extra, sprintf(
        '%s: InnoDB sorted the whole matching set instead of reading the ordering column out of [%s]. Extra: [%s].',
        $label,
        implode(' or ', $acceptableIndexes),
        $plan->Extra,
    ));
}

test('the notification inbox page reads the morph and created_at composite without sorting', function () {
    ['owner' => $owner] = seedIndexPlanFixture();

    $page = $owner->notifications()->limit(15);

    assertPlanUsesIndex(
        'notification inbox page',
        $page,
        'notifications',
        'notifications_notifiable_created_at_index',
    );
});

test('the unread notification badge reads one of the notifiable composites', function () {
    ['owner' => $owner] = seedIndexPlanFixture();

    $badge = $owner->unreadNotifications()->reorder()->selectRaw('count(*) as aggregate');

    /**
     * Either composite is a pass here, and that is not a hedge. The two share
     * the same `(notifiable_type, notifiable_id)` prefix, so which one the
     * optimiser picks for this count is a near-tie broken by row estimates —
     * the assertion in this file most likely to flip on a different 8.x for
     * reasons that have nothing to do with the schema. What the badge must
     * never do is scan, and that is what the index set and the access type
     * assert. `notifications_notifiable_read_at_index` is the one measured on
     * MySQL 8.0.46, and it is the one that covers the count outright.
     */
    assertPlanUsesIndex(
        'unread notification badge count',
        $badge,
        'notifications',
        ['notifications_notifiable_read_at_index', 'notifications_notifiable_created_at_index'],
    );
});

test('the root comment listing reads the commentable, parent_id and created_at composite without sorting', function () {
    ['pet' => $pet] = seedIndexPlanFixture();

    $thread = $pet->rootComments()->latest()->limit(10);

    assertPlanUsesIndex(
        'root comment listing',
        $thread,
        'comments',
        'comments_commentable_parent_created_at_index',
    );
});

test('the profile listings read the user_id, deleted_at and created_at composite without sorting', function () {
    ['owner' => $owner] = seedIndexPlanFixture();

    $listings = $owner->pets()->latest('created_at')->latest('id')->limit(12);

    assertPlanUsesIndex(
        'profile listings page',
        $listings,
        'pets',
        'pets_user_id_deleted_at_created_at_index',
    );
});
