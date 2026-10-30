<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A member's own support tickets, most recently active first, as the headers
 * `settings/Tickets.vue` lists.
 *
 * Scoped by `user_id`, never by the address on the row. `messages_count`
 * rides along as a subquery so the list can show the thread length without
 * loading a thread.
 */
class ListUserTickets
{
    /**
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function handle(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Ticket::query()
            ->whereBelongsTo($user)
            ->withCount('messages')
            ->orderByDesc('last_activity_at')
            ->paginate($perPage, pageName: 'tickets');
    }
}
