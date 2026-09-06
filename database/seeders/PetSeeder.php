<?php

namespace Database\Seeders;

use App\Enums\ListingType;
use App\Models\Category;
use App\Models\Pet;
use App\Models\User;
use Database\Seeders\Concerns\ReadsSeedData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

class PetSeeder extends Seeder
{
    use ReadsSeedData;

    /**
     * How many listings each category should end up with.
     */
    public const MIN_PER_CATEGORY = 8;

    public const MAX_PER_CATEGORY = 12;

    /**
     * Share of listings owned by the demo account, so its own listings page
     * has something on it.
     */
    protected const DEMO_OWNER_CHANCE = 15;

    /**
     * Fill every category with listings spread over real cities.
     *
     * Each category is topped up to its target, so a second run adds nothing.
     *
     * @throws JsonException
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            /** @var Collection<int, User> $owners */
            $owners = User::query()->select(['id'])->get();

            if ($owners->isEmpty()) {
                throw new RuntimeException('No users to own listings; run UserSeeder first.');
            }

            $demoOwner = User::query()->where('email', UserSeeder::DEMO_EMAIL)->firstOrFail();
            $locations = $this->locations();

            Category::query()
                ->with('breeds:id,category_id')
                ->each(function (Category $category) use ($owners, $demoOwner, $locations): void {
                    $missing = max(0, $this->targetFor($category) - $category->pets()->count());

                    for ($created = 0; $created < $missing; $created++) {
                        $owner = fake()->boolean(self::DEMO_OWNER_CHANCE) ? $demoOwner : $owners->random();

                        $this->createListing($category, $owner, fake()->randomElement($locations));
                    }
                });
        });
    }

    /**
     * How many listings a category should hold.
     *
     * Derived from the category id rather than drawn at random so the target
     * is stable: re-running the seeder tops the category back up to the same
     * number instead of growing it.
     */
    protected function targetFor(Category $category): int
    {
        $spread = self::MAX_PER_CATEGORY - self::MIN_PER_CATEGORY + 1;

        return self::MIN_PER_CATEGORY + ((int) $category->getKey() % $spread);
    }

    /**
     * Create one listing for a category, in a city, owned by a user.
     *
     * @param  array{city: string, state: string, country: string, postal_code: string, latitude: float, longitude: float, timezone: string}  $location
     */
    protected function createListing(Category $category, User $owner, array $location): void
    {
        $breed = $category->breeds->isNotEmpty() && fake()->boolean(90)
            ? $category->breeds->random()
            : null;

        $createdAt = fake()->dateTimeBetween('-90 days', 'now');

        $factory = Pet::factory()
            ->for($owner)
            ->for($category)
            ->inCity($location)
            ->state([
                'breed_id' => $breed?->getKey(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

        $this->withStatus($this->withListingType($factory))->create();
    }

    /**
     * @param  Factory<Pet>  $factory
     * @return Factory<Pet>
     */
    protected function withListingType(Factory $factory): Factory
    {
        return match (fake()->randomElement(ListingType::cases())) {
            ListingType::Adoption => $factory->adoption(),
            ListingType::Sale => $factory->forSale(),
            ListingType::Mating => $factory->mating(),
        };
    }

    /**
     * @param  Factory<Pet>  $factory
     * @return Factory<Pet>
     */
    protected function withStatus(Factory $factory): Factory
    {
        return fake()->boolean(80) ? $factory->available() : $factory->unavailable();
    }
}
