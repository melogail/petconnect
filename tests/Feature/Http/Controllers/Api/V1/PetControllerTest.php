<?php

use App\Enums\PetStatus;
use App\Models\Category;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * A complete, valid listing payload for the multipart create endpoint.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function apiPetPayload(Category $category, array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Luna',
        'category_id' => $category->getKey(),
        'breed_id' => null,
        'age' => '2',
        'gender' => 'female',
        'color' => 'Black',
        'weight' => '4.2',
        'description' => 'A calm indoor cat looking for a quiet home.',
        'listing_type' => 'adoption',
        'price' => null,
        'status' => 'available',
        'location' => [
            'address' => '12 Nile Street',
            'detailedAddress' => null,
            'city' => 'Cairo',
            'state' => 'Cairo',
            'postalCode' => null,
            'country' => 'Egypt',
        ],
        'health' => [
            'status' => 'healthy',
            'vaccinated' => true,
            'spayedNeutered' => false,
            'specialNeeds' => null,
            'lastVetVisit' => null,
            'vetName' => null,
            'vetPhone' => null,
        ],
    ], $overrides);
}

describe('index', function () {
    test('a guest receives the paginated feed envelope', function () {
        Pet::factory()->available()->count(3)->create();

        $this->getJson(route('api.v1.pets.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'last_page', 'per_page', 'total']])
            ->assertJsonPath('data.0.is_liked', false);
    });

    test('a token bearer sees their own likes on the cards', function () {
        $user = User::factory()->create();
        $pet = Pet::factory()->available()->create();
        Pet::factory()->available()->create();
        $pet->like($user);
        Sanctum::actingAs($user);

        $response = $this->getJson(route('api.v1.pets.index'))->assertOk();

        $liked = collect($response->json('data'))->firstWhere('id', $pet->getKey());

        expect($liked['is_liked'])->toBeTrue()->and($liked['likes_count'])->toBe(1);
    });

    test('filters through the same request as the web feed', function () {
        $wanted = Category::factory()->create();
        Pet::factory()->available()->count(2)->create(['category_id' => $wanted->getKey()]);
        Pet::factory()->available()->create();

        $this->getJson(route('api.v1.pets.index', ['category_ids' => [$wanted->getKey()]]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    test('hides unavailable listings', function () {
        Pet::factory()->unavailable()->create();

        $this->getJson(route('api.v1.pets.index'))->assertOk()->assertJsonCount(0, 'data');
    });
});

describe('show', function () {
    test('a guest can read a listing and it counts a view', function () {
        $pet = Pet::factory()->create(['views' => 4]);

        $this->getJson(route('api.v1.pets.show', $pet))
            ->assertOk()
            ->assertJsonPath('id', $pet->getKey())
            ->assertJsonPath('is_owner', false)
            ->assertJsonMissingPath('location.address');

        expect($pet->fresh()->views)->toBe(5);
    });

    test('the owner sees the owner-only fields', function () {
        $pet = Pet::factory()->create(['address' => '12 Nile Street']);
        Sanctum::actingAs($pet->user);

        $this->getJson(route('api.v1.pets.show', $pet))
            ->assertOk()
            ->assertJsonPath('is_owner', true)
            ->assertJsonPath('location.address', '12 Nile Street');
    });

    test('returns 404 for a soft deleted listing', function () {
        $pet = Pet::factory()->create();
        $pet->delete();

        $this->getJson(route('api.v1.pets.show', $pet))->assertNotFound();
    });
});

describe('store', function () {
    test('requires a token', function () {
        $this->postJson(route('api.v1.pets.store'), [])->assertUnauthorized();
    });

    test('requires a verified account', function () {
        Sanctum::actingAs(User::factory()->unverified()->create());

        $this->postJson(route('api.v1.pets.store'), [])->assertForbidden();
    });

    test('publishes a listing with its featured image', function () {
        Storage::fake(config('media-library.disk_name'));
        $user = User::factory()->create();
        $category = Category::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post(route('api.v1.pets.store'), [
            ...apiPetPayload($category),
            'featuredImage' => UploadedFile::fake()->image('luna.jpg', 600, 600),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('name', 'Luna')
            ->assertJsonPath('is_owner', true)
            ->assertJsonPath('category.id', $category->getKey());

        expect($response->json('featured_image'))->toBeString()
            ->and(Pet::query()->where('user_id', $user->getKey())->count())->toBe(1);
    });

    test('validates through the shared pet rules', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.pets.store'), ['name' => 'Luna'])
            ->assertInvalid(['category_id', 'featuredImage', 'location.city']);
    });
});

describe('update and destroy', function () {
    test('a stranger cannot change or remove a listing', function () {
        $pet = Pet::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson(route('api.v1.pets.status.toggle', $pet))->assertForbidden();
        $this->deleteJson(route('api.v1.pets.destroy', $pet))->assertForbidden();
    });

    test('the owner toggles the status and gets the new one back', function () {
        $pet = Pet::factory()->available()->create();
        Sanctum::actingAs($pet->user);

        $this->patchJson(route('api.v1.pets.status.toggle', $pet))
            ->assertOk()
            ->assertJsonPath('status', PetStatus::Unavailable->value);

        expect($pet->fresh()->status)->toBe(PetStatus::Unavailable);
    });

    test('the owner removes a listing', function () {
        $pet = Pet::factory()->create();
        Sanctum::actingAs($pet->user);

        $this->deleteJson(route('api.v1.pets.destroy', $pet))->assertNoContent();

        $this->assertSoftDeleted($pet);
    });
});

describe('like', function () {
    test('toggles and reports the count', function () {
        $pet = Pet::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.pets.like', $pet))
            ->assertOk()
            ->assertExactJson(['is_liked' => true, 'likes_count' => 1]);

        $this->postJson(route('api.v1.pets.like', $pet))
            ->assertOk()
            ->assertExactJson(['is_liked' => false, 'likes_count' => 0]);
    });

    test('requires a verified account', function () {
        Sanctum::actingAs(User::factory()->unverified()->create());

        $this->postJson(route('api.v1.pets.like', Pet::factory()->create()))->assertForbidden();
    });
});

describe('taxonomy and options', function () {
    test('lists categories with their breeds', function () {
        $category = Category::factory()->hasBreeds(2)->create();

        $this->getJson(route('api.v1.categories.index'))
            ->assertOk()
            ->assertJsonPath('0.id', $category->getKey())
            ->assertJsonCount(2, '0.breeds');
    });

    test('ships the enum options and validation bounds', function () {
        config(['petconnect.comments.max_length' => 140]);

        $this->getJson(route('api.v1.options'))
            ->assertOk()
            ->assertJsonPath('bounds.comments.max_length', 140)
            ->assertJsonPath('locales.supported', ['en', 'ar'])
            ->assertJsonCount(3, 'listing_types');
    });
});
