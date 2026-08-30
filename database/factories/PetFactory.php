<?php

namespace Database\Factories;

use App\Enums\ListingType;
use App\Enums\PetStatus;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Pet;
use App\Models\User;
use Database\Seeders\Concerns\ReadsSeedData;
use Illuminate\Database\Eloquent\Factories\Factory;
use JsonException;

/**
 * @extends Factory<Pet>
 */
class PetFactory extends Factory
{
    use ReadsSeedData;

    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Pet>
     */
    protected $model = Pet::class;

    /**
     * Define the model's default state.
     *
     * `category_id` is never null (the column is NOT NULL and restricts
     * deletes) and the breed is always created under the pet's own category,
     * so a factory-built pet is internally consistent. City, state and country
     * are NOT NULL too, so all three are always supplied, drawn from the same
     * real cities the seeders use rather than from Faker's American defaults;
     * the street line and the building detail follow the same rule.
     *
     * The optional() records are wrapped in closures so the helper only runs
     * when the value is kept: passthrough() takes an already-evaluated argument,
     * and Factory::expandAttributes() resolves the closure afterwards.
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function definition(): array
    {
        $listingType = fake()->randomElement(ListingType::cases());
        $location = fake()->randomElement($this->locations());

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'breed_id' => fn (array $attributes): Factory => Breed::factory()->state([
                'category_id' => $attributes['category_id'],
            ]),

            'name' => fake()->firstName(),
            'age' => (string) fake()->randomElement([0.5, 1, 2, 3, 4, 5, 7, 10]),
            'gender' => fake()->randomElement(['male', 'female']),
            'color' => fake()->randomElement(['Black', 'White', 'Brown', 'Golden', 'Gray', 'Mixed', 'Spotted']),
            'weight' => fake()->randomFloat(2, 1, 50),
            'description' => fake()->paragraph(3),

            'listing_type' => $listingType,
            'price' => $listingType === ListingType::Sale ? fake()->randomFloat(2, 50, 2000) : null,
            'status' => fake()->boolean(80) ? PetStatus::Available : PetStatus::Unavailable,
            'views' => fake()->numberBetween(0, 500),

            'address' => $this->streetAddress(),
            'detailed_address' => fake()->optional(0.6)->passthrough(
                fn (array $attributes): string => $this->buildingDetail(),
            ),
            'city' => $location['city'],
            'state' => $location['state'],
            'postal_code' => $location['postal_code'],
            'country' => $location['country'],
            'latitude' => $this->jitter($location['latitude']),
            'longitude' => $this->jitter($location['longitude']),

            'health_status' => fake()->randomElement(['healthy', 'minor_issues', 'chronic_condition']),
            'vaccinated' => fake()->boolean(80),
            'spayed_neutered' => fake()->boolean(60),
            'special_needs' => fake()->optional(0.2)->sentence(),
            'last_vet_visit' => fake()->optional(0.7)->dateTimeBetween('-1 year', 'now'),

            'vaccinations' => fake()->optional(0.7)->passthrough(
                fn (array $attributes): array => $this->vaccinationRecords(),
            ),
            'medications' => fake()->optional(0.3)->passthrough(
                fn (array $attributes): array => $this->medicationRecords(),
            ),
            'allergies' => fake()->optional(0.2)->randomElements(
                ['Chicken', 'Beef', 'Dust', 'Pollen'],
                fake()->numberBetween(1, 2),
            ),
            'vet_name' => fake()->optional(0.6)->name(),
            'vet_phone' => fake()->optional(0.6)->numerify('+20-1##-###-####'),

            'traits' => fake()->optional(0.8)->randomElements(
                ['Friendly', 'Energetic', 'Calm', 'Playful', 'Shy', 'Loyal', 'Smart', 'Gentle'],
                fake()->numberBetween(2, 5),
            ),
            'additional_info' => fake()->optional(0.6)->passthrough(
                fn (array $attributes): array => [
                    'house_trained' => fake()->boolean(),
                    'good_with_kids' => fake()->boolean(),
                    'good_with_pets' => fake()->boolean(),
                ],
            ),
        ];
    }

    /**
     * The line under the street address: a building and flat number, which is
     * how an address is written in the cities this dataset covers. Faker's
     * secondaryAddress() returns "Suite 297".
     */
    protected function buildingDetail(): string
    {
        return sprintf(
            'Building %d, Apartment %d',
            fake()->numberBetween(1, 40),
            fake()->numberBetween(1, 30),
        );
    }

    /**
     * Vaccination records in the shape the pet form submits: a name plus the
     * date it was given, which the owner may not remember.
     *
     * @return list<array{name: string, date: string|null}>
     */
    protected function vaccinationRecords(): array
    {
        return array_map(
            fn (string $name): array => [
                'name' => $name,
                'date' => fake()->optional(0.8)->dateTimeBetween('-2 years', 'now')?->format('Y-m-d'),
            ],
            fake()->randomElements(
                ['Rabies', 'Distemper', 'Parvovirus', 'Leptospirosis'],
                fake()->numberBetween(1, 3),
            ),
        );
    }

    /**
     * Medication records in the shape the pet form submits: a name plus a free
     * text dosage note.
     *
     * @return list<array{name: string, usage: string|null}>
     */
    protected function medicationRecords(): array
    {
        return array_map(
            fn (string $name): array => [
                'name' => $name,
                'usage' => fake()->optional(0.8)->randomElement(['Daily', 'Weekly', 'Monthly', 'Twice daily', 'As needed']),
            ],
            fake()->randomElements(
                ['Heartworm prevention', 'Flea treatment', 'Joint supplement'],
                fake()->numberBetween(1, 2),
            ),
        );
    }

    /**
     * Place the listing in one of the cities from database/data/locations.json,
     * scattered a few kilometres around the centre so listings in one city do
     * not stack on a single point.
     *
     * @param  array{city: string, state: string, country: string, postal_code: string, latitude: float, longitude: float, timezone: string}  $location
     */
    public function inCity(array $location): static
    {
        return $this->state(fn (array $attributes): array => [
            'city' => $location['city'],
            'state' => $location['state'],
            'country' => $location['country'],
            'postal_code' => $location['postal_code'],
            'latitude' => $this->jitter($location['latitude']),
            'longitude' => $this->jitter($location['longitude']),
        ]);
    }

    /**
     * Offer the pet for adoption, which is never priced.
     */
    public function adoption(): static
    {
        return $this->state(fn (array $attributes): array => [
            'listing_type' => ListingType::Adoption,
            'price' => null,
        ]);
    }

    /**
     * Offer the pet for sale at a price.
     */
    public function forSale(): static
    {
        return $this->state(fn (array $attributes): array => [
            'listing_type' => ListingType::Sale,
            'price' => fake()->randomFloat(2, 100, 2000),
        ]);
    }

    /**
     * Offer the pet for mating, which is never priced.
     */
    public function mating(): static
    {
        return $this->state(fn (array $attributes): array => [
            'listing_type' => ListingType::Mating,
            'price' => null,
        ]);
    }

    /**
     * Place the pet at exact coordinates, for the nearby() scope.
     */
    public function at(float $latitude, float $longitude): static
    {
        return $this->state(fn (array $attributes): array => [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    /**
     * Mark the listing as still available.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PetStatus::Available,
        ]);
    }

    /**
     * Mark the listing as no longer available.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PetStatus::Unavailable,
        ]);
    }
}
