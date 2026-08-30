<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConversationSeeder extends Seeder
{
    /**
     * How many direct threads the demo dataset should end up with.
     */
    public const TARGET_COUNT = 15;

    /**
     * How many of them the demo account takes part in.
     */
    public const DEMO_THREAD_COUNT = 5;

    /**
     * Guard against an unlucky run of already-paired users.
     */
    protected const MAX_ATTEMPTS = 200;

    /**
     * Open direct threads between distinct pairs of users.
     *
     * conversation_user is unique on (conversation_id, user_id), and a pair
     * should only ever share one direct thread, so every candidate pair is
     * checked with the betweenParticipants() scope before it is created.
     * The total is topped up to TARGET_COUNT, so a second run adds nothing.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $missing = max(0, self::TARGET_COUNT - Conversation::query()->count());

            if ($missing === 0) {
                return;
            }

            $demo = User::query()->where('email', UserSeeder::DEMO_EMAIL)->firstOrFail();

            /** @var Collection<int, User> $others */
            $others = User::query()->whereKeyNot($demo->getKey())->select(['id'])->get();

            if ($others->count() < 2) {
                throw new RuntimeException('At least three users are needed to open threads; run UserSeeder first.');
            }

            $opened = $this->openDemoThreads($demo, $others, min(self::DEMO_THREAD_COUNT, $missing));

            $this->openMemberThreads($others, $missing - $opened);
        });
    }

    /**
     * Give the demo account its own inbox.
     *
     * @param  Collection<int, User>  $others
     * @return int Threads actually opened.
     */
    protected function openDemoThreads(User $demo, Collection $others, int $count): int
    {
        $opened = 0;

        foreach ($others->shuffle()->take($count) as $participant) {
            if ($this->open($demo, $participant)) {
                $opened++;
            }
        }

        return $opened;
    }

    /**
     * Fill the rest of the inbox with threads between other members.
     *
     * Every pair may already be connected, in which case the loop runs out of
     * attempts and the dataset ends up short of TARGET_COUNT; say so rather
     * than leave the missing threads a mystery.
     *
     * @param  Collection<int, User>  $others
     */
    protected function openMemberThreads(Collection $others, int $count): void
    {
        $opened = 0;
        $attempts = 0;

        while ($opened < $count && $attempts < self::MAX_ATTEMPTS) {
            $attempts++;

            [$first, $second] = $others->random(2)->all();

            if ($this->open($first, $second)) {
                $opened++;
            }
        }

        if ($opened < $count) {
            $this->command?->warn(sprintf(
                'ConversationSeeder gave up after %d attempts with %d of %d member threads opened; too few unpaired users remain to reach %d conversations.',
                $attempts,
                $opened,
                $count,
                self::TARGET_COUNT,
            ));
        }
    }

    /**
     * Open a direct thread unless the pair already share one.
     */
    protected function open(User $first, User $second): bool
    {
        if (Conversation::query()->betweenParticipants($first, $second)->exists()) {
            return false;
        }

        Conversation::factory()
            ->direct()
            ->withParticipants($first, $second)
            ->create();

        return true;
    }
}
