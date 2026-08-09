<?php

use App\Http\Resources\BreedResource;
use App\Http\Resources\CategoryResource;
use App\Models\Breed;
use App\Models\Category;
use App\Models\User;
use App\Support\LocaleManager;
use Illuminate\Support\Facades\App;

it('allows a guest to switch locale via cookie and session', function () {
    $response = $this->from(route('home'))
        ->post(route('locale.update'), ['locale' => 'ar']);

    $response->assertRedirect(route('home'));
    $response->assertPlainCookie(LocaleManager::COOKIE_NAME, 'ar');
    expect(session('locale'))->toBe('ar');
});

it('updates the authenticated user locale when switching', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->from(route('home'))
        ->post(route('locale.update'), ['locale' => 'ar'])
        ->assertRedirect(route('home'))
        ->assertPlainCookie(LocaleManager::COOKIE_NAME, 'ar');

    expect($user->fresh()->locale)->toBe('ar');
});

it('rejects invalid locales', function () {
    $this->from(route('home'))
        ->post(route('locale.update'), ['locale' => 'fr'])
        ->assertSessionHasErrors('locale');
});

it('shares locale dir and translations on inertia responses', function () {
    $version = file_exists($manifest = public_path('build/manifest.json'))
        ? hash_file('xxh128', $manifest)
        : '';

    $response = $this->withUnencryptedCookie(LocaleManager::COOKIE_NAME, 'ar')
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
        ])
        ->get(route('home'));

    $response->assertOk();
    expect($response->json('props.locale'))->toBe('ar')
        ->and($response->json('props.dir'))->toBe('rtl')
        ->and($response->json('props.translations'))->toBeArray()
        ->and($response->json('props.translations'))->toHaveKey('nav.brand');
});

it('forces english locale for nova paths', function () {
    $this->withUnencryptedCookie(LocaleManager::COOKIE_NAME, 'ar')
        ->get('/nova');

    expect(App::getLocale())->toBe('en');
});

it('returns arabic category names for the active locale including guests', function () {
    $category = Category::factory()->create([
        'name' => 'Dogs',
        'name_ar' => 'كلاب',
        'description' => 'English description',
        'description_ar' => 'وصف عربي',
    ]);

    App::setLocale('ar');

    $resource = CategoryResource::make($category)->resolve();

    expect($resource['name'])->toBe('كلاب')
        ->and($resource['description'])->toBe('وصف عربي');
});

it('falls back to english category names when arabic is missing', function () {
    $category = Category::factory()->create([
        'name' => 'Cats',
        'name_ar' => null,
        'description' => 'English cats',
        'description_ar' => null,
    ]);

    App::setLocale('ar');

    $resource = CategoryResource::make($category)->resolve();

    expect($resource['name'])->toBe('Cats')
        ->and($resource['description'])->toBe('English cats');
});

it('returns arabic breed names for the active locale', function () {
    $category = Category::factory()->create();
    $breed = Breed::factory()->create([
        'category_id' => $category->id,
        'name' => 'Husky',
        'name_ar' => 'هاسكي',
        'description' => 'English breed',
        'description_ar' => 'سلالة عربية',
    ]);

    App::setLocale('ar');

    $resource = BreedResource::make($breed->load('category'))->resolve();

    expect($resource['name'])->toBe('هاسكي')
        ->and($resource['description'])->toBe('سلالة عربية');
});
