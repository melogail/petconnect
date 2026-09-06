<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\File;
use JsonException;
use RuntimeException;

/**
 * Reads the checked-in JSON fixtures under database/data, which are the source
 * of truth for the taxonomy and the locations the demo dataset is spread over.
 */
trait ReadsSeedData
{
    /**
     * The cities the demo dataset is spread over, memoised so building a batch
     * of models reads database/data/locations.json once instead of once per
     * model. A trait's static property is per using class, so each factory and
     * seeder keeps its own copy.
     *
     * @var list<array{city: string, state: string, country: string, postal_code: string, latitude: float, longitude: float, timezone: string}>|null
     */
    protected static ?array $seedLocations = null;

    /**
     * The street names addresses are built from, memoised so building a batch
     * of models reads database/data/streets.json once instead of once per model.
     *
     * @var list<string>|null
     */
    protected static ?array $seedStreets = null;

    /**
     * Decode a JSON file from database/data.
     *
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    protected function readSeedData(string $file): array
    {
        $path = database_path("data/{$file}");

        if (! File::exists($path)) {
            throw new RuntimeException("Seed data not found at [{$path}].");
        }

        /** @var array<array-key, mixed> $data */
        $data = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    /**
     * The cities the demo dataset is spread over, with real coordinates.
     *
     * @return list<array{city: string, state: string, country: string, postal_code: string, latitude: float, longitude: float, timezone: string}>
     *
     * @throws JsonException
     */
    protected function locations(): array
    {
        /** @var list<array{city: string, state: string, country: string, postal_code: string, latitude: float, longitude: float, timezone: string}> $locations */
        $locations = static::$seedLocations ??= $this->readSeedData('locations.json');

        return $locations;
    }

    /**
     * The street names the demo dataset builds its addresses from.
     *
     * @return list<string>
     *
     * @throws JsonException
     */
    protected function streets(): array
    {
        /** @var list<string> $streets */
        $streets = static::$seedStreets ??= $this->readSeedData('streets.json');

        return $streets;
    }

    /**
     * A street line that belongs in the Egypt/Gulf cities of locations.json,
     * instead of Faker's American streetAddress() ("46054 Irma Villages").
     *
     * @throws JsonException
     */
    protected function streetAddress(): string
    {
        $street = fake()->randomElement($this->streets());

        return fake()->numberBetween(1, 180).' '.$street;
    }

    /**
     * Scatter a coordinate a few kilometres around the city centre so listings
     * in one city are not stacked on a single point.
     */
    protected function jitter(float $coordinate): float
    {
        return round($coordinate + fake()->randomFloat(4, -0.08, 0.08), 6);
    }
}
