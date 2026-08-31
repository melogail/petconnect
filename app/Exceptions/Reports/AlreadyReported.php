<?php

namespace App\Exceptions\Reports;

use Illuminate\Validation\ValidationException;

/**
 * This user has already reported this target.
 *
 * Thrown from two places, and the second one is the one that matters:
 *
 * - SubmitReport\EnsureNotAlreadyReported is the friendly fast path, one
 *   `exists()` before anything is written. This is all the legacy app had, in
 *   StoreReportRequest::withValidator() — and only for two of the reportable
 *   types.
 * - SubmitReport\PersistReport catches the database's own refusal. `reports` is
 *   unique on (user_id, reportable_type, reportable_id) and that index is the
 *   guarantee. A check-then-write is a TOCTOU race by construction: two
 *   submissions both read "not reported yet" and both insert.
 *
 * It is also not merely a double-click guard. `comments.parent_id` cascades on
 * delete, a DB cascade fires no Eloquent events, and `reports` reaches a
 * comment through a morph column that can carry no foreign key — so deleting a
 * comment strands the reports of its whole subtree, and those rows sit in the
 * table pointing at nothing. That much is structural and holds on any driver.
 *
 * .ai/rules/pipelines.md goes one step further and says a stranded report
 * eventually *collides* with a genuine one because ids get recycled. That step
 * does not hold on this schema as built, and the claim should not be repeated
 * without checking: `$table->id()` emits `integer primary key autoincrement` on
 * SQLite, verified against the live `comments` and `reports` tables, and
 * AUTOINCREMENT is precisely what stops SQLite reusing a deleted row's id.
 * (The MySQL half — InnoDB losing its counter across a restart — was only ever
 * true before 8.0, which persists it.) Treat the collision as a hazard for a
 * schema that drops AUTOINCREMENT, not as today's behaviour; the race above is
 * reason enough for this catch on its own.
 *
 * See App\Exceptions\Reports\CannotReportOwnContent for the `report` key and
 * the ValidationException base.
 */
class AlreadyReported extends ValidationException
{
    public static function make(): self
    {
        return self::withMessages([
            'report' => __('You have already reported this.'),
        ]);
    }
}
