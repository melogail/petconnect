<?php

use App\Http\Requests\Pet\UpdatePetRequest;
use App\Models\Comment;
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

/**
 * The two counters are different sums and a fixture where they agree hides the
 * defect the second one exists to fix: `comments_count` is `withCount(['comments'])`
 * over the whole morphMany — roots and replies together — while
 * `root_comments_count` counts only `whereNull('parent_id')`. The thread ships
 * roots, so it is the root total the client compares what it holds against;
 * paging on `comments_count` asks for pages that are not there.
 *
 * Two roots with two replies each therefore has to read 2 and 6, not 6 and 6.
 */
test('counts roots separately from the whole thread', function () {
    $pet = Pet::factory()->create();

    Comment::factory()
        ->count(2)
        ->forPet($pet)
        ->create()
        ->each(fn (Comment $root) => Comment::factory()->count(2)->reply($root)->create());

    $this->get(route('pets.show', $pet))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pet.root_comments_count', 2)
            ->where('pet.comments_count', 6)
            ->etc());
});

/**
 * The reconciliation that caught the camelCase/snake_case split which was
 * silently nulling `vet_name`, the coordinates and `postal_code` on every edit:
 * a key the resource emits is either something the edit request accepts back
 * under the same name, or something deliberately declared read-only below.
 *
 * `$readShapes` is a declaration, not a suppression list. A key belongs on it
 * only after confirming the edit form never round-trips it — for a counter such
 * as `comments_count` or `root_comments_count`, that means no rule of that name
 * on UpdatePetRequest and no mention in `resources/js/lib/petForm.ts`. If the
 * form does send the key, the mismatch is on the request's side and this test
 * failing is the point.
 */
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
        'featured_image', 'photos', 'likes_count', 'comments_count',
        'root_comments_count', 'is_liked',
        'comments', 'created_at', 'updated_at',
    ];

    $rules = (new UpdatePetRequest)->rules();
    $unmatched = array_values(array_diff(array_keys($payload), array_keys($rules), $readShapes));

    expect($unmatched)->toBe([]);
});
