<?php

use App\Enums\Reportable;
use App\Enums\ReportCategory;
use App\Enums\ReportReason;
use App\Models\Admin;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A reportable of the given whitelisted type, authored by the given user.
 *
 * A `match` over the enum rather than a lookup table on purpose: adding a case
 * to App\Enums\Reportable without teaching this helper how to build one is an
 * UnhandledMatchError here, so the datasets below cannot silently stop covering
 * a type.
 */
function reportableTargetAuthoredBy(Reportable $type, User $author): Model
{
    return match ($type) {
        Reportable::Comment => Comment::factory()->for($author)->create(),
        Reportable::Review => Review::factory()->for($author)->create(),
    };
}

/**
 * @return array<string, mixed>
 */
function reportPayload(): array
{
    return [
        'category' => ReportCategory::Abuse->value,
        'reason' => ReportReason::HateSpeech->value,
        'description' => 'Abusive towards the seller.',
    ];
}

/**
 * A URL segment that is not a case on App\Enums\Reportable.
 *
 * The legacy StoreReportRequest took `reportable_type` as `['required',
 * 'string']` in the request body and wrote whatever arrived into the morph
 * column. `user`, `pet` and `admin` are the dangerous half: they are real morph
 * aliases, so a whitelist derived from the morph map instead of from the enum
 * would accept them, and the self-report and duplicate guards were the two
 * things the legacy code skipped for every type but two.
 *
 * @var array<string, list<string>>
 */
const REPORTABLE_TYPES_OFF_THE_WHITELIST = [
    'a url encoded class name' => ['App%5CModels%5CComment'],
    'a morph alias for a profile' => ['user'],
    'a morph alias for a listing' => ['pet'],
    'a morph alias for a moderator' => ['admin'],
    'a value on no whitelist at all' => ['dragon'],
];

/**
 * A row that really exists under the given segment, and its id.
 *
 * Load bearing, and the reason this test does not simply post a made-up id: a
 * 404 raised because nothing of that type happens to hold that id proves
 * nothing about the whitelist. Seeding the row first leaves the router refusing
 * the type as the only thing that can still produce the 404.
 *
 * A segment that names no morph type at all gets a comment, which is the id
 * that *would* resolve if the segment were ever used as a class name.
 */
function existingReportableIdFor(string $segment): int
{
    return match ($segment) {
        'user' => User::factory()->create()->getKey(),
        'pet' => Pet::factory()->create()->getKey(),
        'admin' => Admin::factory()->create()->getKey(),
        default => Comment::factory()->create()->getKey(),
    };
}

test('redirects a guest to the login page and writes nothing', function () {
    $target = Review::factory()->create();

    $this->post(route('reports.store', ['reportable_type' => 'review', 'reportable_id' => $target->getKey()]), reportPayload())
        ->assertRedirect(route('login'));

    $this->assertDatabaseEmpty('reports');
});

test('redirects an unverified user to the verification notice and writes nothing', function () {
    $target = Review::factory()->create();

    $this->actingAs(User::factory()->unverified()->create())
        ->post(route('reports.store', ['reportable_type' => 'review', 'reportable_id' => $target->getKey()]), reportPayload())
        ->assertRedirect(route('verification.notice'));

    $this->assertDatabaseEmpty('reports');
});

test('files the report against a whitelisted target', function (Reportable $type) {
    $reporter = User::factory()->create();
    $target = reportableTargetAuthoredBy($type, User::factory()->create());

    $this->actingAs($reporter)
        ->from(route('home'))
        ->post(route('reports.store', ['reportable_type' => $type->value, 'reportable_id' => $target->getKey()]), reportPayload())
        ->assertRedirect(route('home'));

    $this->assertDatabaseHas('reports', [
        'user_id' => $reporter->getKey(),
        'reportable_type' => $type->value,
        'reportable_id' => $target->getKey(),
        'category' => ReportCategory::Abuse->value,
        'reason' => ReportReason::HateSpeech->value,
        'status' => 'pending',
    ]);
})->with(Reportable::cases());

/**
 * The POST binding, which is the only binding this route has: the legacy hole
 * was a class name in the request *body* of a write, and closing it at the
 * router is what makes the whitelist unavoidable. The acting user is
 * authenticated on purpose — a 404 that was really a redirect to the login page
 * would prove nothing about the binding.
 */
test('returns 404 for a reportable type that is not on the whitelist and writes nothing', function (string $type) {
    $id = existingReportableIdFor($type);

    $this->actingAs(User::factory()->create())
        ->post('/reports/'.$type.'/'.$id, reportPayload())
        ->assertNotFound();

    $this->assertDatabaseEmpty('reports');
})->with(REPORTABLE_TYPES_OFF_THE_WHITELIST);

/**
 * The alias in the URL has to name the row that is actually reported. A pet id
 * dressed as a `review` must not file a report against whatever review happens
 * to hold that id.
 */
test('returns 404 for a target that does not exist and writes nothing', function (Reportable $type) {
    Pet::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('reports.store', ['reportable_type' => $type->value, 'reportable_id' => 9999]), reportPayload())
        ->assertNotFound();

    $this->assertDatabaseEmpty('reports');
})->with(Reportable::cases());

test('returns 404 for a reportable id that is not a number', function () {
    $this->actingAs(User::factory()->create())
        ->post('/reports/review/abc', reportPayload())
        ->assertNotFound();

    $this->assertDatabaseEmpty('reports');
});

/**
 * The legacy self-report guard fired only for two types and silently skipped
 * every other. Running it over every case is what keeps the guard honest when
 * the whitelist grows.
 */
test('refuses a report of the reporter own content on the flow level key and writes nothing', function (Reportable $type) {
    $author = User::factory()->create();
    $target = reportableTargetAuthoredBy($type, $author);

    $this->actingAs($author)
        ->post(route('reports.store', ['reportable_type' => $type->value, 'reportable_id' => $target->getKey()]), reportPayload())
        ->assertInvalid(['report' => 'You cannot report your own content.'])
        ->assertValid(['category', 'reason', 'description']);

    $this->assertDatabaseEmpty('reports');
})->with(Reportable::cases());

test('refuses a second report of the same target on the flow level key and leaves one row', function (Reportable $type) {
    $reporter = User::factory()->create();
    $target = reportableTargetAuthoredBy($type, User::factory()->create());
    Report::factory()->for($reporter)->forReportable($target)->create();

    $this->actingAs($reporter)
        ->post(route('reports.store', ['reportable_type' => $type->value, 'reportable_id' => $target->getKey()]), reportPayload())
        ->assertInvalid(['report' => 'You have already reported this.'])
        ->assertValid(['category', 'reason', 'description']);

    expect(Report::query()->count())->toBe(1);
})->with(Reportable::cases());

test('rejects a category that is not a ReportCategory case and writes nothing', function () {
    $target = Review::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('reports.store', ['reportable_type' => 'review', 'reportable_id' => $target->getKey()]), [
            ...reportPayload(),
            'category' => 'nuisance',
        ])
        ->assertInvalid(['category']);

    $this->assertDatabaseEmpty('reports');
});

test('rejects a reason that is not a ReportReason case and writes nothing', function () {
    $target = Review::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('reports.store', ['reportable_type' => 'review', 'reportable_id' => $target->getKey()]), [
            ...reportPayload(),
            'reason' => 'because',
        ])
        ->assertInvalid(['reason']);

    $this->assertDatabaseEmpty('reports');
});

test('rejects a report with no category and no reason and writes nothing', function () {
    $target = Review::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('reports.store', ['reportable_type' => 'review', 'reportable_id' => $target->getKey()]), [])
        ->assertInvalid([
            'category' => 'The category field is required.',
            'reason' => 'The reason field is required.',
        ]);

    $this->assertDatabaseEmpty('reports');
});

test('rejects a description longer than the configured ceiling and writes nothing', function () {
    $target = Review::factory()->create();
    $maxLength = config('petconnect.reports.max_description_length');

    $this->actingAs(User::factory()->create())
        ->post(route('reports.store', ['reportable_type' => 'review', 'reportable_id' => $target->getKey()]), [
            ...reportPayload(),
            'description' => Str::repeat('a', $maxLength + 1),
        ])
        ->assertInvalid(['description' => 'must not be greater than '.$maxLength]);

    $this->assertDatabaseEmpty('reports');
});

/**
 * Reports reach a comment through a morph column, so a comment on a trashed
 * listing is still addressable by its sequential id. Comment's route binding is
 * what re-derives the listing's visibility, and the enum resolves the target
 * through exactly that binding.
 */
test('returns 404 for a comment on a soft deleted listing and writes nothing', function () {
    $pet = Pet::factory()->create();
    $comment = Comment::factory()->for($pet, 'commentable')->create();
    $pet->delete();

    $this->actingAs(User::factory()->create())
        ->post(route('reports.store', ['reportable_type' => 'comment', 'reportable_id' => $comment->getKey()]), reportPayload())
        ->assertNotFound();

    $this->assertDatabaseEmpty('reports');
});

test('returns 429 once the acting user passes 5 reports in a minute', function () {
    $reporter = User::factory()->create();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->actingAs($reporter)
            ->from(route('home'))
            ->post(route('reports.store', [
                'reportable_type' => 'review',
                'reportable_id' => Review::factory()->create()->getKey(),
            ]), reportPayload())
            ->assertRedirect();
    }

    $this->actingAs($reporter)
        ->post(route('reports.store', [
            'reportable_type' => 'review',
            'reportable_id' => Review::factory()->create()->getKey(),
        ]), reportPayload())
        ->assertTooManyRequests();

    expect(Report::query()->count())->toBe(5);
});
