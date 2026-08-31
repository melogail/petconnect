<?php

use App\Actions\Reports\CreateReport;
use App\Enums\Reportable;
use App\Enums\ReportCategory;
use App\Enums\ReportReason;
use App\Exceptions\Reports\AlreadyReported;
use App\Exceptions\Reports\CannotReportOwnContent;
use App\Exceptions\Reports\ReportingNotSupported;
use App\Models\Admin;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ReportFiledNotification;
use App\Pipelines\Reports\SubmitReport\PersistReport;
use App\Pipelines\Reports\SubmitReport\SubmitReportContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * A reportable of the given whitelisted type, authored by the given user.
 *
 * A `match` over the enum rather than a lookup table on purpose: adding a case
 * to App\Enums\Reportable without teaching this helper how to build one is an
 * UnhandledMatchError here, so the datasets below cannot silently stop covering
 * a type. That is the same failure the legacy app shipped — guards written for
 * two types while the whitelist was open — expressed as a test failure.
 */
function reportableAuthoredBy(Reportable $type, User $author): Model
{
    return match ($type) {
        Reportable::Comment => Comment::factory()->for($author)->create(),
        Reportable::Review => Review::factory()->for($author)->create(),
    };
}

function fileReport(User $reporter, Reportable $type, int $id): Report
{
    return app(CreateReport::class)->handle(
        reporter: $reporter,
        reportableType: $type,
        reportableId: $id,
        category: ReportCategory::Abuse,
        reason: ReportReason::HateSpeech,
        description: 'Abusive towards the seller.',
    );
}

test('writes the report against the resolved target with the pending status', function (Reportable $type) {
    $reporter = User::factory()->create();
    $target = reportableAuthoredBy($type, User::factory()->create());

    $report = fileReport($reporter, $type, $target->getKey());

    $this->assertDatabaseHas('reports', [
        'id' => $report->getKey(),
        'user_id' => $reporter->getKey(),
        'reportable_type' => Relation::getMorphAlias($target::class),
        'reportable_id' => $target->getKey(),
        'category' => ReportCategory::Abuse->value,
        'reason' => ReportReason::HateSpeech->value,
        'status' => 'pending',
        'description' => 'Abusive towards the seller.',
    ]);
})->with(Reportable::cases());

/**
 * The second security fix in this vertical.
 *
 * The legacy guard ran inside `if (! in_array($type, [Review::class,
 * Comment::class]))`, so every other reportable skipped it — and
 * `reportable_type` was validated as a bare string, so every other value was
 * reachable. Here the guard is written against App\Contracts\Reportable, so it
 * covers whatever is on the whitelist. Running it over every case is what keeps
 * that true when the whitelist grows.
 */
test('refuses a report of the reporter own content and writes nothing', function (Reportable $type) {
    $author = User::factory()->create();
    $target = reportableAuthoredBy($type, $author);
    Notification::fake();

    expect(fn () => fileReport($author, $type, $target->getKey()))
        ->toThrow(CannotReportOwnContent::class);

    $this->assertDatabaseEmpty('reports');
    Notification::assertNothingSent();
})->with(Reportable::cases());

test('refuses a second report of the same target by the same reporter and leaves one row', function (Reportable $type) {
    $reporter = User::factory()->create();
    $target = reportableAuthoredBy($type, User::factory()->create());
    Report::factory()->for($reporter)->forReportable($target)->create();

    expect(fn () => fileReport($reporter, $type, $target->getKey()))
        ->toThrow(AlreadyReported::class);

    expect(Report::query()->count())->toBe(1);
})->with(Reportable::cases());

test('lets a second reporter file against the same target', function (Reportable $type) {
    $target = reportableAuthoredBy($type, User::factory()->create());
    Report::factory()->for(User::factory()->create())->forReportable($target)->create();

    fileReport(User::factory()->create(), $type, $target->getKey());

    expect(Report::query()->count())->toBe(2);
})->with(Reportable::cases());

/**
 * The guard step answers before the insert, but the unique index is the
 * guarantee. Bypassing the step leaves the index as the only thing between two
 * writes, and PersistReport has to turn its refusal into the same error rather
 * than a 500 — the race two clients hit when they submit at once.
 */
test('turns the unique index violation into the same refusal when the guard step is bypassed', function () {
    $reporter = User::factory()->create();
    $target = Review::factory()->create();
    Report::factory()->for($reporter)->forReportable($target)->create();

    $context = new SubmitReportContext(
        reporter: $reporter,
        reportableType: Reportable::Review,
        reportableId: $target->getKey(),
        category: ReportCategory::Abuse,
        reason: ReportReason::Spam,
    );
    $context->setReportable($target);

    expect(fn () => app(PersistReport::class)->handle($context, fn ($passed) => $passed))
        ->toThrow(AlreadyReported::class);

    expect(Report::query()->count())->toBe(1);
});

test('raises a model not found exception for a target that does not exist and writes nothing', function (Reportable $type) {
    expect(fn () => fileReport(User::factory()->create(), $type, 9999))
        ->toThrow(ModelNotFoundException::class);

    $this->assertDatabaseEmpty('reports');
})->with(Reportable::cases());

/**
 * The model-side half of the whitelist.
 *
 * App\Enums\Reportable maps a wire value to a class; App\Contracts\Reportable is
 * what the guards are written against. A case added for a model that never
 * opted in must abort rather than skip both guards and file the row anyway,
 * which is precisely what the legacy `in_array` early return did.
 *
 * Driven through the context directly because the enum cannot express the
 * state: every case on it today does implement the contract, which is the
 * invariant this pins.
 */
test('raises ReportingNotSupported for a resolved target that does not implement the contract', function () {
    $context = new SubmitReportContext(
        reporter: User::factory()->create(),
        reportableType: Reportable::Review,
        reportableId: 1,
        category: ReportCategory::Abuse,
        reason: ReportReason::Spam,
    );
    $context->setReportable(Pet::factory()->create());

    expect(fn () => $context->reportableAsTarget())->toThrow(ReportingNotSupported::class);
});

describe('notifying the moderators', function () {
    test('writes a database notification to every admin', function () {
        $admins = Admin::factory()->count(3)->create();
        $target = Review::factory()->create();

        $report = fileReport(User::factory()->create(), Reportable::Review, $target->getKey());

        foreach ($admins as $admin) {
            $this->assertDatabaseHas('notifications', [
                'type' => ReportFiledNotification::class,
                'notifiable_type' => Relation::getMorphAlias(Admin::class),
                'notifiable_id' => $admin->getKey(),
            ]);
        }
        expect($report->getKey())->not->toBeNull();
    });

    /**
     * Notification payloads carry translation keys, not rendered text, so the
     * moderator's own locale decides the wording at read time.
     */
    test('carries the report identifiers and the message key rather than rendered text', function () {
        $admin = Admin::factory()->create();
        $reporter = User::factory()->create();
        $target = Review::factory()->create();

        $report = fileReport($reporter, Reportable::Review, $target->getKey());

        expect($admin->notifications()->sole()->data)->toMatchArray([
            'report_id' => $report->getKey(),
            'reporter_id' => $reporter->getKey(),
            'reportable_type' => 'review',
            'reportable_id' => $target->getKey(),
            'status' => 'pending',
            'message_key' => 'notifications.report_filed',
            'type' => 'report',
        ]);
    });

    /**
     * The legacy failure mode: a report accepted, a queue nobody reads, and no
     * trace of either. The row is still written — dropping the report because
     * the back office is empty would be worse — but it is not allowed to happen
     * silently.
     */
    test('logs a warning when there is no admin to notify, and still files the report', function () {
        Log::spy();
        $target = Review::factory()->create();

        $report = fileReport(User::factory()->create(), Reportable::Review, $target->getKey());

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $context['report_id'] === $report->getKey()
                && $context['reportable_type'] === 'review');

        $this->assertModelExists($report);
        $this->assertDatabaseEmpty('notifications');
    });

    test('logs nothing when at least one admin was notified', function () {
        Log::spy();
        Admin::factory()->create();
        $target = Review::factory()->create();

        fileReport(User::factory()->create(), Reportable::Review, $target->getKey());

        Log::shouldNotHaveReceived('warning');
    });
});
