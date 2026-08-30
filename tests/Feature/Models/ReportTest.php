<?php

use App\Enums\ReportCategory;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Comment;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;

test('stores the comment morph alias rather than a class name', function () {
    $comment = Comment::factory()->create();

    $report = Report::factory()->forReportable($comment)->create();

    $this->assertDatabaseHas('reports', [
        'id' => $report->getKey(),
        'reportable_type' => 'comment',
        'reportable_id' => $comment->getKey(),
    ]);
});

test('resolves the reported comment from the stored alias', function () {
    $comment = Comment::factory()->create();
    $report = Report::factory()->forReportable($comment)->create();

    $reportable = Report::query()->findOrFail($report->getKey())->reportable;

    expect($reportable)->toBeInstanceOf(Comment::class)
        ->and($reportable->is($comment))->toBeTrue();
});

test('resolves the reported review from the stored alias', function () {
    $review = Review::factory()->create();
    $report = Report::factory()->forReportable($review)->create();

    $reportable = Report::query()->findOrFail($report->getKey())->reportable;

    expect($reportable)->toBeInstanceOf(Review::class)
        ->and($reportable->is($review))->toBeTrue();
});

test('rejects a second report by the same user against the same target', function () {
    $reporter = User::factory()->create();
    $comment = Comment::factory()->create();
    Report::factory()->for($reporter)->forReportable($comment)->create();

    expect(fn () => Report::factory()->for($reporter)->forReportable($comment)->create())
        ->toThrow(QueryException::class);

    expect(Report::query()->count())->toBe(1);
});

test('allows a second user to report the same target', function () {
    $comment = Comment::factory()->create();
    Report::factory()->forReportable($comment)->create();

    Report::factory()->forReportable($comment)->create();

    expect(Report::query()->count())->toBe(2);
});

test('rejects a mass assigned moderation status', function () {
    $comment = Comment::factory()->create();

    expect(fn () => Report::create([
        'user_id' => User::factory()->create()->getKey(),
        'reportable_type' => 'comment',
        'reportable_id' => $comment->getKey(),
        'category' => ReportCategory::Abuse,
        'reason' => ReportReason::Spam,
        'status' => ReportStatus::Resolved,
    ]))->toThrow(MassAssignmentException::class);

    $this->assertDatabaseEmpty('reports');
});

test('files a new report as pending', function () {
    $comment = Comment::factory()->create();

    $report = Report::create([
        'user_id' => User::factory()->create()->getKey(),
        'reportable_type' => 'comment',
        'reportable_id' => $comment->getKey(),
        'category' => ReportCategory::Abuse,
        'reason' => ReportReason::Spam,
    ]);

    expect($report->fresh()->status)->toBe(ReportStatus::Pending);
});
