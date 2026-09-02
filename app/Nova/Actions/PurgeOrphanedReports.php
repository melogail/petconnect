<?php

namespace App\Nova\Actions;

use App\Models\Report;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\DestructiveAction;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Throwable;

/**
 * Clear reports whose target no longer exists.
 *
 * ## The narrow job this exists for
 *
 * `reports.reportable_id` is a morph column, so it carries no foreign key and
 * nothing cascades it. When a comment subtree or a review disappears without
 * going through the flow that collects its polymorphic children
 * (.ai/rules/actions.md), the report about it survives with `reportable`
 * resolving to null: a row in the moderation queue that cannot be opened,
 * cannot be judged, and cannot be dismissed, because every decision
 * ChangeReportStatus offers is a decision *about* something.
 *
 * ## Why it is an action and not `delete: true` on the policy
 *
 * ReportPolicy::update is false because a report is the reporter's own words
 * and is the evidence. `authorizedToDelete` returning true on every row gave
 * that back with the other hand: Nova draws the delete control on the detail
 * page, in the row menu **and in the index's bulk bar**, where "select all" is
 * one click and there is no undo — an admin could destroy the entire queue,
 * which is exactly the evidence `update: false` is protecting.
 *
 * So the policy refuses delete outright and this action is the only route, with
 * the check the policy could not express: a selected report whose `reportable`
 * still resolves is refused by name, and the whole run stops without writing
 * anything. Nothing partially succeeds. That is DeleteCategory's shape, for the
 * same reason — a bulk destructive action needs a sentence, not a stack trace
 * and not a silent partial.
 *
 * `runDestructiveAction` in the policy is what lets this past the same `delete`
 * refusal it relies on.
 *
 * ## The selection is one transaction, and a failure is a sentence
 *
 * The guard is not the only way this can fail — a report can acquire a target
 * again between the check and the delete, and `reports` is read and written by
 * observers — so the delete itself needs the shape .ai/rules/nova-actions.md
 * makes non-negotiable: DB::transaction around the whole selection and a
 * `catch (Throwable)` returning ActionResponse::danger(), so the admin is told
 * nothing happened rather than being left guessing which half did.
 *
 * The guard stays **outside and before** the try, exactly as DeleteCategory's
 * does. That is a choice, not an oversight: a refusal is a decision this action
 * has made and reported by name, and folding it into the try would let a throw
 * from the guard's own `loadMissing('reportable')` be reported as "the whole
 * selection was rolled back" when nothing had been attempted yet. The trade-off
 * accepted here is the reverse — a failure inside `stillTargeted()` is not
 * caught and still surfaces as a 500. It writes nothing, so there is no partial
 * state to explain; the sentence exists for the half that writes.
 */
class PurgeOrphanedReports extends DestructiveAction
{
    /**
     * The displayable name of the action.
     *
     * @var \Stringable|string
     */
    public $name = 'Purge Orphaned Reports';

    /**
     * Perform the action on the given models.
     *
     * @param  Collection<int, Report>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $stillTargeted = $this->stillTargeted($models);

        if ($stillTargeted !== []) {
            return ActionResponse::danger($this->refusal($stillTargeted));
        }

        try {
            DB::transaction(function () use ($models): void {
                $models->each(function (Report $report): void {
                    $report->delete();
                });
            });
        } catch (Throwable $exception) {
            report($exception);

            return ActionResponse::danger(
                'Nothing was deleted. One of the selected reports could not be cleared, so the whole selection was rolled back. The failure has been logged.',
            );
        }

        return ActionResponse::message($models->count() === 1
            ? '1 orphaned report cleared.'
            : sprintf('%d orphaned reports cleared.', $models->count()));
    }

    /**
     * The selected report ids whose target is still there.
     *
     * `loadMissing` rather than a read per row: the whole selection is one
     * MorphTo eager load per morph type, and `$report->reportable` on an
     * unloaded model would be a lazy load per report.
     *
     * Sorted, because Nova hands the selection back in whatever order the
     * client sent it and "Reports #2, #1" reads like a bug.
     *
     * @param  Collection<int, Report>  $models
     * @return array<int, int>
     */
    protected function stillTargeted(Collection $models): array
    {
        $models->loadMissing('reportable');

        return $models
            ->filter(fn (Report $report): bool => $report->reportable !== null)
            ->map(fn (Report $report): int => $report->getKey())
            ->sort()
            ->values()
            ->all();
    }

    /**
     * The message an admin reads instead of losing live evidence.
     *
     * @param  array<int, int>  $stillTargeted
     */
    protected function refusal(array $stillTargeted): string
    {
        $one = count($stillTargeted) === 1;

        return sprintf(
            'Nothing was deleted. %s %s still %s a comment or review that exists, so %s evidence rather than %s. Open %s and decide with Change Status instead.',
            $one ? 'Report' : 'Reports',
            implode(', ', array_map(fn (int $id): string => '#'.$id, $stillTargeted)),
            $one ? 'points at' : 'point at',
            $one ? 'it is' : 'they are',
            $one ? 'an orphan' : 'orphans',
            $one ? 'it' : 'them',
        );
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [];
    }
}
