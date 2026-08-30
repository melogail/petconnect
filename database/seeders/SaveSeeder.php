<?php

namespace Database\Seeders;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SaveSeeder extends Seeder
{
    /**
     * How many listings a user bookmarks.
     */
    public const MIN_PER_USER = 3;

    public const MAX_PER_USER = 10;

    /**
     * Bookmark listings for every user who has none.
     *
     * `saves` is unique on (user_id, saveable_id, saveable_type); the pets one
     * user bookmarks are a random subset of *distinct* listings, and
     * HasSaves::addSave() is a firstOrCreate, so no duplicate can be inserted.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            /** @var Collection<int, Pet> $pets */
            $pets = Pet::query()->select(['id'])->get();

            if ($pets->isEmpty()) {
                throw new RuntimeException('No listings to bookmark; run PetSeeder first.');
            }

            User::query()
                ->doesntHave('saves')
                ->lazyById()
                ->each(function (User $user) use ($pets): void {
                    $count = fake()->numberBetween(self::MIN_PER_USER, self::MAX_PER_USER);

                    foreach ($pets->shuffle()->take($count) as $pet) {
                        $pet->addSave($user);
                    }
                });
        });
    }
}
