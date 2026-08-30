<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * The order is dependency driven: taxonomy, then accounts, then listings,
     * then everything that hangs off a listing or a user.
     *
     * Model events are deliberately left enabled. UserObserver assigns the
     * unique media directory name, MessageObserver maintains
     * conversations.last_message_at, and LikeObserver writes the like
     * notifications the dashboard reads, so muting them would leave the
     * dataset inconsistent.
     *
     * Each seeder wraps its own run in DB::transaction() instead of the whole
     * chain being wrapped here: on SQLite every firstOrCreate and every
     * notification would otherwise be its own implicit transaction and its own
     * fsync, and batching per seeder is what makes the run quick while keeping
     * each step all-or-nothing.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            BreedSeeder::class,

            UserSeeder::class,
            AdminSeeder::class,

            PetSeeder::class,

            CommentSeeder::class,
            LikeSeeder::class,
            SaveSeeder::class,
            ReviewSeeder::class,
            ReportSeeder::class,

            ConversationSeeder::class,
            MessageSeeder::class,
        ]);
    }
}
