<?php

use App\Http\Requests\Pet\UpdatePetRequest;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Arr;
use Inertia\Testing\AssertableInertia;

/**
 * The leaves the resource emits only for a viewer who may edit the listing.
 *
 * @var list<string>
 */
const OWNER_ONLY_PATHS = [
    'pet.location.address',
    'pet.location.detailedAddress',
    'pet.location.coordinates',
    'pet.health.medications',
    'pet.health.allergies',
    'pet.health.vetName',
    'pet.health.vetPhone',
];

/**
 * A listing with every optional field populated, so the payload emits every leaf.
 */
function petWithEveryField(?User $owner = null): Pet
{
    return Pet::factory()
        ->for($owner ?? User::factory())
        ->at(30.0444, 31.2357)
        ->forSale()
        ->create([
            'weight' => '4.20',
            'address' => '12 Nile Street',
            'detailed_address' => 'Building 3, Apartment 7',
            'postal_code' => '11511',
            'special_needs' => 'Needs a quiet home',
            'last_vet_visit' => '2024-01-15',
            'vaccinations' => [['name' => 'Rabies', 'date' => '2024-01-15']],
            'medications' => [['name' => 'Flea drops', 'usage' => 'Monthly']],
            'allergies' => ['Dust'],
            'vet_name' => 'Dr. Hana',
            'vet_phone' => '+20-100-000-0000',
            'traits' => ['Friendly'],
            'additional_info' => ['house_trained' => 'yes'],
        ]);
}

test('a guest never receives the owner-only fields', function () {
    $pet = petWithEveryField();

    $this->get(route('pets.show', $pet))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pet.is_owner', false)
            ->missingAll(OWNER_ONLY_PATHS));
});

test('a user who does not own the listing never receives the owner-only fields', function () {
    $pet = petWithEveryField();

    $this->actingAs(User::factory()->create())
        ->get(route('pets.show', $pet))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pet.is_owner', false)
            ->missingAll(OWNER_ONLY_PATHS));
});

test('the owner receives the owner-only fields', function () {
    $owner = User::factory()->create();
    $pet = petWithEveryField($owner);

    $this->actingAs($owner)
        ->get(route('pets.show', $pet))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pet.is_owner', true)
            ->where('pet.location.address', '12 Nile Street')
            ->where('pet.location.detailedAddress', 'Building 3, Apartment 7')
            ->where('pet.location.coordinates.lat', 30.0444)
            ->where('pet.health.medications', [['name' => 'Flea drops', 'usage' => 'Monthly']])
            ->where('pet.health.allergies', ['Dust'])
            ->where('pet.health.vetName', 'Dr. Hana')
            ->where('pet.health.vetPhone', '+20-100-000-0000')
            ->etc());
});

test('every nested key the resource emits has a rule of the same name on the edit request', function () {
    $owner = User::factory()->create();
    $pet = petWithEveryField($owner);
    $payload = null;

    $this->actingAs($owner)
        ->get(route('pets.show', $pet))
        ->assertInertia(function (AssertableInertia $page) use (&$payload): void {
            $payload = $page->toArray()['props']['pet'];
        });

    $rules = (new UpdatePetRequest)->rules();

    $emitted = collect(Arr::dot(Arr::only($payload, ['location', 'health'])))
        ->keys()
        ->map(fn (string $key): string => (string) preg_replace('/\.\d+(?=\.|$)/', '.*', $key))
        ->unique()
        ->values()
        ->all();

    expect($emitted)->not->toBeEmpty();

    foreach ($emitted as $key) {
        expect($rules)->toHaveKey($key);
    }
});

test('every top level key the resource emits is either a rule on the edit request or a declared read shape', function () {
    $owner = User::factory()->create();
    $pet = petWithEveryField($owner);
    $payload = null;

    $this->actingAs($owner)
        ->get(route('pets.show', $pet))
        ->assertInertia(function (AssertableInertia $page) use (&$payload): void {
            $payload = $page->toArray()['props']['pet'];
        });

    $readShapes = [
        'id', 'views', 'category', 'breed', 'user', 'is_owner',
        'featured_image', 'photos', 'likes_count', 'comments_count', 'is_liked',
        'comments', 'created_at', 'updated_at',
    ];

    $rules = (new UpdatePetRequest)->rules();
    $unmatched = array_values(array_diff(array_keys($payload), array_keys($rules), $readShapes));

    expect($unmatched)->toBe([]);
});
