<?php

namespace App\Models;

use App\Enums\ReportCategory;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A user report filed against a reportable model, triaged in the back office.
 *
 * `status` is deliberately not mass assignable: it is a moderator decision, the
 * column defaults to `pending`, and a Form Request forwarding validated() must
 * never be able to file a report that is already resolved. The moderation
 * Action sets it explicitly.
 *
 * @property int $id
 * @property int $user_id
 * @property string $reportable_type
 * @property int $reportable_id
 * @property ReportCategory $category
 * @property ReportReason $reason
 * @property string|null $description
 * @property ReportStatus $status
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'reportable_type',
    'reportable_id',
    'category',
    'reason',
    'description',
    'metadata',
])]
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * `status` is out of #[Fillable] and the DB default only lands in the row,
     * never on the instance, so `Report::create([...])->status` would be null
     * in memory while `@property ReportStatus $status` promises an enum — and
     * an API Resource or Inertia prop built from that model would ship the lie.
     * The raw backing value is stored here; the cast resolves it on access.
     *
     * @var array{status: string}
     */
    protected $attributes = [
        'status' => ReportStatus::Pending->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ReportCategory::class,
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'metadata' => 'array',
        ];
    }

    /**
     * The reported model (a comment or a review).
     *
     * @return MorphTo<Model, $this>
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The user who filed the report.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function withStatus(Builder $query, ReportStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Reports still awaiting a moderator decision.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::Pending);
    }
}
