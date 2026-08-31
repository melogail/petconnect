<?php

use App\Actions\Comments\CreateComment;
use App\Enums\Commentable;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;
use App\Notifications\ModelCommentedNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;

/**
 * Publish a comment the way the controller does: the enum case and the id, never
 * a class name.
 */
function publishComment(User $author, Pet $pet, string $content, ?int $parentId = null): Comment
{
    return app(CreateComment::class)->handle(
        author: $author,
        commentableType: Commentable::Pet,
        commentableId: $pet->getKey(),
        content: $content,
        parentId: $parentId,
    );
}

test('cleans the text with the configured word list before storing it', function () {
    $pet = Pet::factory()->create();

    $comment = publishComment(User::factory()->create(), $pet, '  What a   bitch  ');

    expect($comment->content)->toBe('What a ****');
    $this->assertDatabaseHas('comments', [
        'id' => $comment->getKey(),
        'content' => 'What a ****',
    ]);
});

test('raises a model not found error for a soft deleted listing and writes nothing', function () {
    $pet = Pet::factory()->create();
    $pet->delete();

    expect(fn (): Comment => publishComment(User::factory()->create(), $pet, 'Is she still available?'))
        ->toThrow(ModelNotFoundException::class);

    $this->assertDatabaseEmpty('comments');
});

test('notifies the listing owner of a top level comment, storing the translation key rather than rendered text', function () {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['name' => 'Luna']);
    $commenter = User::factory()->create();

    $comment = publishComment($commenter, $pet, 'Is she still available?');

    $notification = $owner->notifications()->sole();
    expect($notification->type)->toBe(ModelCommentedNotification::class)
        ->and($notification->data['message_key'])->toBe('notifications.commented_on_pet')
        ->and($notification->data['comment_id'])->toBe($comment->getKey())
        ->and($notification->data['commenter_id'])->toBe($commenter->getKey())
        ->and($notification->data['message_replace'])->toBe([
            'name' => $commenter->name,
            'subject' => 'Luna',
        ]);
});

test('notifies the author of the comment being answered rather than the listing owner', function () {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $parentAuthor = User::factory()->create();
    $parent = Comment::factory()->for($parentAuthor)->for($pet, 'commentable')->create();
    Notification::fake();

    publishComment(User::factory()->create(), $pet, 'She is, message me.', $parent->getKey());

    Notification::assertSentTo($parentAuthor, ModelCommentedNotification::class);
    Notification::assertNotSentTo($owner, ModelCommentedNotification::class);
});

test('sends no notification when an owner comments on their own listing', function () {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    Notification::fake();

    publishComment($owner, $pet, 'Still looking for a home for her.');

    Notification::assertNothingSent();
});

test('sends no notification when an author replies to their own comment', function () {
    $author = User::factory()->create();
    $pet = Pet::factory()->create();
    $parent = Comment::factory()->for($author)->for($pet, 'commentable')->create();
    Notification::fake();

    publishComment($author, $pet, 'Adding to my own question.', $parent->getKey());

    Notification::assertNothingSent();
});
