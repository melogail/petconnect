<?php

namespace Database\Factories;

use App\Enums\ListingType;
use App\Models\Breed;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pet>
 */
class PetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $listingType = fake()->randomElement([ListingType::Adoption, ListingType::Sale, ListingType::Mating]);
        $latitude = fake()->latitude(24, 49);
        $longitude = fake()->longitude(-99, -66); // Fixed for database precision

        $petNames = [
            'Max', 'Bella', 'Charlie', 'Lucy', 'Cooper', 'Luna', 'Buddy', 'Daisy',
            'Rocky', 'Molly', 'Tucker', 'Sadie', 'Bear', 'Chloe', 'Duke', 'Bailey',
            'Oliver', 'Lily', 'Jack', 'Sophie', 'Zeus', 'Zoe', 'Toby', 'Lola',
        ];

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'breed_id' => Breed::factory(),

            // Basic Information
            'name' => fake()->randomElement($petNames),
            'age' => (string) fake()->randomElement([0.5, 1, 2, 3, 5, 7, 10]),
            'gender' => fake()->randomElement(['male', 'female']),
            'color' => fake()->randomElement(['Black', 'White', 'Brown', 'Golden', 'Gray', 'Mixed', 'Spotted']),
            'weight' => fake()->randomFloat(2, 1, 50),
            'description' => fake()->paragraph(3),

            // Listing Information
            'listing_type' => $listingType->value,
            'price' => $listingType === ListingType::Sale ? fake()->randomFloat(2, 50, 2000) : null,
            'status' => fake()->randomElement(['available', 'pending', 'adopted']),

            // Location Information
            'address' => fake()->streetAddress(),
            'detailed_address' => fake()->optional(0.6)->secondaryAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => fake()->country(),
            'latitude' => $latitude,
            'longitude' => $longitude,

            // Health Information
            'health_status' => fake()->randomElement(['healthy', 'minor_issues', 'chronic_condition']),
            'vaccinated' => fake()->boolean(80),
            'spayed_neutered' => fake()->boolean(60),
            'special_needs' => fake()->optional(0.3)->sentence(),
            'last_vet_visit' => fake()->optional(0.7)->dateTimeBetween('-1 year', 'now'),

            // Healthcare Information (JSON)
            'vaccinations' => fake()->optional(0.7)->passthrough([
                ['date' => fake()->date(), 'name' => 'Rabies'],
                ['date' => fake()->date(), 'name' => 'Distemper'],
            ]),
            'medications' => fake()->optional(0.4)->passthrough([
                ['name' => 'Heartworm Prevention', 'usage' => 'Monthly'],
            ]),
            'allergies' => fake()->optional(0.3)->passthrough(['Chicken', 'Pollen']),
            'vet_name' => fake()->optional(0.6)->name(),
            'vet_phone' => fake()->optional(0.6)->phoneNumber(),

            // Personality & Traits (JSON)
            'traits' => fake()->optional(0.8)->passthrough(
                fake()->randomElements(
                    ['Friendly', 'Energetic', 'Calm', 'Playful', 'Shy', 'Loyal', 'Smart', 'Gentle'],
                    fake()->numberBetween(2, 5)
                )
            ),

            // Additional Information (JSON)
            'additional_info' => fake()->optional(0.6)->passthrough([
                ['key' => 'House Trained', 'value' => 'Yes'],
                ['key' => 'Good with Kids', 'value' => fake()->randomElement(['Yes', 'No', 'Unknown'])],
                ['key' => 'Good with Other Pets', 'value' => fake()->randomElement(['Yes', 'No', 'Unknown'])],
            ]),

            'views' => fake()->numberBetween(0, 500),
        ];
    }

    /**
     * Indicate that the pet is available for adoption.
     */
    public function adoption(): static
    {
        return $this->state(fn (array $attributes) => [
            'listing_type' => ListingType::Adoption->value,
            'price' => null,
        ]);
    }

    /**
     * Indicate that the pet is for sale.
     */
    public function forSale(): static
    {
        return $this->state(fn (array $attributes) => [
            'listing_type' => ListingType::Sale->value,
            'price' => fake()->randomFloat(2, 100, 2000),
        ]);
    }

    /**
     * Indicate that the pet is available for mating.
     */
    public function mating(): static
    {
        return $this->state(fn (array $attributes) => [
            'listing_type' => ListingType::Mating->value,
            'price' => null,
        ]);
    }

    /**
     * Place the pet at specific coordinates.
     */
    public function at(float $latitude, float $longitude): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }
}
