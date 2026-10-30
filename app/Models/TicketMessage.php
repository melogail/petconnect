<?php

namespace App\Models;

use Database\Factories\TicketMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;

/**
 * One message in a support ticket's thread.
 *
 * `author` is the `User` who wrote it or the `Admin` who answered
 * (`author_type` / `author_id` hold the morph aliases `user` / `admin` from
 * AppServiceProvider::configureMorphMap). The columns are nullable so a row
 * survives its author's account being deleted; it resolves to null then.
 * Which side a message is on is `isFromSupport()`, decided from the alias
 * rather than by loading the author — the member's page renders a thread of
 * them and must not pay a query per bubble.
 *
 * @property int $id
 * @property int $ticket_id
 * @property string|null $author_type
 * @property int|null $author_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['ticket_id', 'author_type', 'author_id', 'body'])]
class TicketMessage extends Model
{
    /** @use HasFactory<TicketMessageFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function isFromSupport(): bool
    {
        return $this->author_type === Relation::getMorphAlias(Admin::class);
    }
}
