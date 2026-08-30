<?php

use App\Enums\ListingType;
use App\Enums\PetStatus;
use App\Models\Pet;
use Illuminate\Support\Facades\DB;

/**
 * Row counts for every seeded table, used to prove a second run is a no-op.
 *
 * @return array<string, int>
 */
function seededRowCounts(): array
{
    $tables = [
        'categories', 'breeds', 'users', 'admins', 'pets', 'comments', 'likes',
        'saves', 'reviews', 'reports', 'conversations', 'conversation_user',
        'messages', 'notifications',
    ];

    return collect($tables)
        ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
        ->all();
}

test('seeds a dataset that holds every cross table invariant', function () {
    $this->seed();

    $morphColumns = [
        'comments' => 'commentable_type',
        'likes' => 'likeable_type',
        'saves' => 'saveable_type',
        'reviews' => 'reviewable_type',
        'reports' => 'reportable_type',
        'media' => 'model_type',
        'notifications' => 'notifiable_type',
    ];

    $morphValues = collect($morphColumns)
        ->flatMap(fn (string $column, string $table): array => DB::table($table)->distinct()->pluck($column)->all())
        ->filter()
        ->values();

    expect($morphValues)->not->toBeEmpty()
        ->and($morphValues->all())->each->not->toContain('\\');

    expect(DB::table('pets')->whereNull('category_id')->count())->toBe(0);

    $mismatchedBreeds = DB::table('pets')
        ->join('breeds', 'breeds.id', '=', 'pets.breed_id')
        ->whereColumn('breeds.category_id', '!=', 'pets.category_id')
        ->count();

    expect($mismatchedBreeds)->toBe(0);

    $staleCursors = DB::table('conversations')
        ->leftJoin('messages', 'messages.conversation_id', '=', 'conversations.id')
        ->groupBy('conversations.id', 'conversations.last_message_at')
        ->havingRaw('coalesce(max(messages.created_at), \'\') != coalesce(conversations.last_message_at, \'\')')
        ->count();

    expect($staleCursors)->toBe(0);

    $before = seededRowCounts();

    $this->seed();

    expect(seededRowCounts())->toBe($before);
});

test('seeds pet statuses and listing types that rehydrate as enum cases', function () {
    $this->seed();

    $statuses = DB::table('pets')->distinct()->pluck('status');
    $listingTypes = DB::table('pets')->distinct()->pluck('listing_type');

    expect($statuses->all())->each->toBeIn(['available', 'unavailable'])
        ->and($listingTypes->all())->each->toBeIn(['adoption', 'sale', 'mating']);

    $pets = Pet::query()->get();

    expect($pets)->not->toBeEmpty()
        ->and($pets->pluck('status')->unique()->values()->all())->each->toBeInstanceOf(PetStatus::class)
        ->and($pets->pluck('listing_type')->unique()->values()->all())->each->toBeInstanceOf(ListingType::class);
});
