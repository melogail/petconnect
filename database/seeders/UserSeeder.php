<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\ReadsSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use JsonException;

class UserSeeder extends Seeder
{
    use ReadsSeedData;

    /**
     * The fixed account the whole dataset is built around; password "password".
     */
    public const DEMO_EMAIL = 'test@example.com';

    /**
     * How many users the demo dataset should end up with, the demo account
     * included.
     */
    public const TARGET_COUNT = 30;

    /**
     * Seed the demo account plus enough members to fill the feed.
     *
     * The member count is topped up rather than appended to, so running the
     * seeder twice leaves exactly TARGET_COUNT users.
     *
     * @throws JsonException
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedDemoUser();

            $missing = max(0, self::TARGET_COUNT - User::query()->count());

            if ($missing === 0) {
                return;
            }

            $locations = $this->locations();

            for ($index = 0; $index < $missing; $index++) {
                User::factory()
                    ->inCity($locations[$index % count($locations)])
                    ->create($this->memberAttributes($index));
            }
        });
    }

    /**
     * The known login every other seeder hangs its demo data off.
     *
     * The row is matched on email and its attributes are rewritten every run,
     * so the counts stay identical but the demo user is UPDATEd each time: the
     * `hashed` cast re-bcrypts 'password' into a fresh digest and
     * `last_seen_at` moves to now(). That is deliberate — this is the account
     * a developer logs in with — and it is a no-op at row level only, not at
     * value level.
     */
    protected function seedDemoUser(): User
    {
        $cairo = $this->locations()[0];

        return User::query()->updateOrCreate(
            ['email' => self::DEMO_EMAIL],
            [
                'name' => 'Test User',
                'username' => 'testuser',
                'password' => 'password',
                'email_verified_at' => now(),
                'bio' => 'PetConnect demo account.',
                'phone' => '+20-100-000-0000',
                'city' => $cairo['city'],
                'state' => $cairo['state'],
                'country' => $cairo['country'],
                'address' => '1 Tahrir Square',
                'lat' => $cairo['latitude'],
                'lng' => $cairo['longitude'],
                'timezone' => $cairo['timezone'],
                'locale' => 'en',
                'is_active' => true,
                'last_seen_at' => now(),
            ],
        );
    }

    /**
     * Give a few members an Arabic locale and, for a few more, an unverified or
     * deactivated account so those paths have data.
     *
     * The city, the coordinates, the timezone and the street line come from
     * UserFactory::inCity() and its definition(), which read the same
     * database/data/locations.json this seeder does.
     *
     * @return array<string, mixed>
     */
    protected function memberAttributes(int $index): array
    {
        return [
            'locale' => $index % 3 === 0 ? 'ar' : 'en',
            'is_active' => $index % 10 !== 9,
            'email_verified_at' => $index % 7 === 6 ? null : now()->subDays(fake()->numberBetween(1, 200)),
        ];
    }
}
