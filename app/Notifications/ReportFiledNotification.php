<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Database notification telling a moderator that a report is waiting.
 *
 * The notifiable is an App\Models\Admin, not a User — moderation lives on the
 * `admins` guard. That works without a second mechanism because `Admin` is
 * Notifiable, `admin` is in the enforced morph map, and `notifications` is one
 * polymorphic table; see Pipelines\Reports\SubmitReport\NotifyModerators for the
 * full reasoning.
 *
 * ## The payload is a pointer, not a copy of the evidence
 *
 * It carries ids, the enum values and a short excerpt of what the reporter
 * wrote — enough to triage and to open the right record — and deliberately not
 * the reported content itself. A notification row is written once and never
 * revised, so embedding the comment or review text would leave a frozen copy of
 * it in a second table that no moderation action, edit or deletion reaches.
 * Phase 3's Nova `Report` resource reads the live row through
 * `Report::reportable()`.
 *
 * Translation keys rather than rendered text, for the same reason every other
 * notification here does it: the row outlives the reader's locale. See
 * .ai/rules/notifications.md.
 *
 * No `url`: the Nova screen this points at does not exist until Phase 3, and
 * Route::has() cannot vouch for a Nova resource path. The client resolves it
 * from `report_id` when there is somewhere to go.
 *
 * Not queued, matching every other notification in the application.
 *
 * ## Why there is no `:subject` replacement
 *
 * `message_replace` used to be `['subject' => $this->report->reportable_type]`,
 * and `reportable_type` is a **morph alias** — `review`, `comment` — so
 * `notifications.report_filed` rendered the literal string `review` into the
 * sentence. It read as English only by the accident of the alias happening to
 * be an English noun, was never translated in either language file, and would
 * have shipped the raw alias of any type added to App\Enums\Reportable later.
 *
 * The key is now subject-free — "A new report is waiting" — and the payload
 * already carries `reportable_type` and `reportable_id` for a client that wants
 * to name the target. That follows the rule the rest of these notifications
 * keep: the row stores keys and identifiers, and every user-visible word is
 * rendered at read time in the reader's own locale.
 */
class ReportFiledNotification extends Notification
{
    /**
     * How much of the reporter's description travels in the payload.
     */
    protected const EXCERPT_LENGTH = 120;

    public function __construct(
        public Report $report,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{
     *     report_id: int,
     *     reporter_id: int,
     *     reportable_type: string,
     *     reportable_id: int,
     *     category: string,
     *     reason: string,
     *     status: string,
     *     excerpt: string|null,
     *     message_key: string,
     *     message_replace: array<string, string>,
     *     type: string
     * }
     */
    public function toArray(object $notifiable): array
    {
        return [
            'report_id' => $this->report->id,
            'reporter_id' => $this->report->user_id,
            'reportable_type' => $this->report->reportable_type,
            'reportable_id' => $this->report->reportable_id,
            'category' => $this->report->category->value,
            'reason' => $this->report->reason->value,
            'status' => $this->report->status->value,
            'excerpt' => $this->report->description === null
                ? null
                : Str::limit($this->report->description, self::EXCERPT_LENGTH),
            'message_key' => 'notifications.report_filed',
            'message_replace' => [],
            'type' => 'report',
        ];
    }
}
