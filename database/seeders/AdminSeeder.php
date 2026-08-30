<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminSeeder extends Seeder
{
    /**
     * The fixed back-office login for Nova; password "password".
     */
    public const DEMO_EMAIL = 'admin@petconnect.com';

    /**
     * How many admins the demo dataset should end up with.
     */
    public const TARGET_COUNT = 3;

    /**
     * Seed the known Nova login plus a couple of colleagues.
     *
     * `password` is cast to `hashed` on the model, so the plain string is
     * hashed once on write rather than hashed here as well.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            Admin::query()->updateOrCreate(
                ['email' => self::DEMO_EMAIL],
                [
                    'name' => 'Admin User',
                    'password' => 'password',
                    'email_verified_at' => now(),
                ],
            );

            $missing = max(0, self::TARGET_COUNT - Admin::query()->count());

            if ($missing === 0) {
                return;
            }

            Admin::factory()->count($missing)->create();
        });
    }
}
