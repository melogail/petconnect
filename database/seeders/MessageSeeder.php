<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MessageSeeder extends Seeder
{
    /**
     * How long a thread runs.
     */
    public const MIN_PER_CONVERSATION = 5;

    public const MAX_PER_CONVERSATION = 30;

    /**
     * Share of threads with one pinned message.
     */
    protected const PINNED_CHANCE = 30;

    /**
     * Share of participants who have read to the end of their thread.
     */
    protected const READ_CHANCE = 55;

    /**
     * Fill every empty thread with a back and forth run of messages.
     *
     * `conversations.last_message_at` is left to App\Observers\MessageObserver
     * rather than written here. Only threads with no messages are filled, so a
     * second run adds nothing.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $demoUserId = User::query()->where('email', UserSeeder::DEMO_EMAIL)->value('id');

            Conversation::query()
                ->doesntHave('messages')
                ->with('users')
                ->lazyById()
                ->each(function (Conversation $conversation) use ($demoUserId): void {
                    $participants = $conversation->users;

                    if ($participants->count() < 2) {
                        throw new RuntimeException(
                            "Conversation [{$conversation->getKey()}] has fewer than two participants; run ConversationSeeder first."
                        );
                    }

                    $this->fill($conversation, $participants, $demoUserId);
                });
        });
    }

    /**
     * Write the thread, alternating senders and moving forward in time.
     *
     * Participants are ordered with the demo account first, and the sender
     * offset makes the last message always come from the second participant of
     * the pair, so the first participant's read cursor alone decides whether
     * the thread reads as unread for them. Threads are direct, so there are
     * always exactly two participants.
     *
     * @param  Collection<int, User>  $participants
     */
    protected function fill(Conversation $conversation, Collection $participants, ?int $demoUserId): void
    {
        $ordered = $participants
            ->sortBy(fn (User $participant): int => $participant->getKey() === $demoUserId ? 0 : 1)
            ->values();

        $count = fake()->numberBetween(self::MIN_PER_CONVERSATION, self::MAX_PER_CONVERSATION);
        $pinnedIndex = fake()->boolean(self::PINNED_CHANCE) ? fake()->numberBetween(0, $count - 1) : null;
        $offset = $count % 2;

        $sentAt = now()->subDays(fake()->numberBetween(5, 30));

        for ($index = 0; $index < $count; $index++) {
            $sentAt = $sentAt->addMinutes(fake()->numberBetween(2, 180));
            $sender = $ordered[($index + $offset) % $ordered->count()];

            $factory = Message::factory()
                ->for($conversation)
                ->from($sender);

            if ($index === $pinnedIndex) {
                $factory = $factory->pinned($sender);
            }

            $factory->create([
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ]);
        }

        $this->markReadCursors($conversation, $ordered, $sentAt, $demoUserId);
    }

    /**
     * Move each participant's read cursor so some threads read as unread.
     *
     * @param  Collection<int, User>  $participants
     */
    protected function markReadCursors(
        Conversation $conversation,
        Collection $participants,
        CarbonInterface $lastSentAt,
        ?int $demoUserId,
    ): void {
        foreach ($participants as $participant) {
            $conversation->users()->updateExistingPivot($participant->getKey(), [
                'last_read_at' => $this->readCursorFor($conversation, $participant, $lastSentAt, $demoUserId),
            ]);
        }
    }

    /**
     * Where a participant has read up to: caught up, part way through, or
     * never opened the thread.
     *
     * The demo account alternates by thread id so its inbox always contains
     * both a read and an unread conversation.
     */
    protected function readCursorFor(
        Conversation $conversation,
        User $participant,
        CarbonInterface $lastSentAt,
        ?int $demoUserId,
    ): ?CarbonInterface {
        if ($participant->getKey() === $demoUserId) {
            return $conversation->getKey() % 2 === 0 ? $lastSentAt->addMinute() : null;
        }

        return match (true) {
            fake()->boolean(self::READ_CHANCE) => $lastSentAt->addMinute(),
            fake()->boolean(50) => $lastSentAt->subHours(fake()->numberBetween(1, 48)),
            default => null,
        };
    }
}
