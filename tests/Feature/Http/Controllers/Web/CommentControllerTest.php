<?php

use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The URL of a listing's thread. `pet` is the morph alias the enum is backed
 * by and the value the column stores; a class name never travels here.
 *
 * @return array{commentable_type: string, commentable_id: int}
 */
function threadRouteParameters(Pet $pet): array
{
    return ['commentable_type' => 'pet', 'commentable_id' => $pet->getKey()];
}

describe('index', function () {
    test('a guest reads the thread of a listing', function () {
        $pet = Pet::factory()->create();
        $comment = Comment::factory()->for($pet, 'commentable')->create();

        $this->get(route('comments.index', threadRouteParameters($pet)))
            ->assertOk()
            ->assertJsonPath('data.0.id', $comment->getKey())
            ->assertJsonPath('data.0.content', $comment->content);
    });

    test('returns 404 for a commentable type that is not on the whitelist', function () {
        $this->get('/comments/dragon/1')->assertNotFound();
    });

    test('returns 404 for the thread of a soft deleted listing', function () {
        $pet = Pet::factory()->create();
        Comment::factory()->for($pet, 'commentable')->create();
        $pet->delete();

        $this->get(route('comments.index', threadRouteParameters($pet)))->assertNotFound();
    });
});

describe('replies', function () {
    test('a guest reads the replies of a comment', function () {
        $parent = Comment::factory()->create();
        $reply = Comment::factory()->reply($parent)->create();

        $this->get(route('comments.replies', $parent))
            ->assertOk()
            ->assertJsonPath('data.0.id', $reply->getKey())
            ->assertJsonPath('data.0.parent_id', $parent->getKey());
    });
});

describe('store', function () {
    test('redirects a guest to the login page and writes nothing', function () {
        $pet = Pet::factory()->create();

        $this->post(route('comments.store', threadRouteParameters($pet)), ['content' => 'Is she still available?'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseEmpty('comments');
    });

    test('redirects an unverified user to the verification notice and writes nothing', function () {
        $pet = Pet::factory()->create();

        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('comments.store', threadRouteParameters($pet)), ['content' => 'Is she still available?'])
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseEmpty('comments');
    });

    test('publishes a top level comment authored by the acting user', function () {
        $author = User::factory()->create();
        $pet = Pet::factory()->create();

        $this->actingAs($author)
            ->from(route('pets.show', $pet))
            ->post(route('comments.store', threadRouteParameters($pet)), ['content' => 'Is she still available?'])
            ->assertRedirect(route('pets.show', $pet));

        $this->assertDatabaseHas('comments', [
            'user_id' => $author->getKey(),
            'content' => 'Is she still available?',
            'parent_id' => null,
            'commentable_type' => 'pet',
            'commentable_id' => $pet->getKey(),
        ]);
    });

    test('stamps the author from the session rather than from the payload', function () {
        $author = User::factory()->create();
        $stranger = User::factory()->create();
        $pet = Pet::factory()->create();

        $this->actingAs($author)
            ->from(route('pets.show', $pet))
            ->post(route('comments.store', threadRouteParameters($pet)), [
                'content' => 'Is she still available?',
                'user_id' => $stranger->getKey(),
            ])
            ->assertRedirect(route('pets.show', $pet));

        $this->assertDatabaseHas('comments', ['user_id' => $author->getKey()]);
        $this->assertDatabaseMissing('comments', ['user_id' => $stranger->getKey()]);
    });

    test('publishes a reply against the parent comment and its listing', function () {
        $author = User::factory()->create();
        $pet = Pet::factory()->create();
        $parent = Comment::factory()->for($pet, 'commentable')->create();

        $this->actingAs($author)
            ->from(route('pets.show', $pet))
            ->post(route('comments.store', threadRouteParameters($pet)), [
                'content' => 'She is, message me.',
                'parent_id' => $parent->getKey(),
            ])
            ->assertRedirect(route('pets.show', $pet));

        $this->assertDatabaseHas('comments', [
            'user_id' => $author->getKey(),
            'content' => 'She is, message me.',
            'parent_id' => $parent->getKey(),
            'commentable_type' => 'pet',
            'commentable_id' => $pet->getKey(),
        ]);
    });

    test('returns 404 for a soft deleted listing and writes nothing', function () {
        $pet = Pet::factory()->create();
        $pet->delete();

        $this->actingAs(User::factory()->create())
            ->post(route('comments.store', threadRouteParameters($pet)), ['content' => 'Is she still available?'])
            ->assertNotFound();

        $this->assertDatabaseEmpty('comments');
    });

    test('returns 404 for a commentable type that is not on the whitelist', function () {
        $this->actingAs(User::factory()->create())
            ->post('/comments/dragon/1', ['content' => 'Is she still available?'])
            ->assertNotFound();

        $this->assertDatabaseEmpty('comments');
    });

    test('rejects a comment with no content and writes nothing', function () {
        $pet = Pet::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('comments.store', threadRouteParameters($pet)), ['content' => ''])
            ->assertInvalid(['content' => 'The content field is required.']);

        $this->assertDatabaseEmpty('comments');
    });

    test('rejects a comment longer than the configured ceiling and writes nothing', function () {
        $pet = Pet::factory()->create();
        $maxLength = config('petconnect.comments.max_length');

        $this->actingAs(User::factory()->create())
            ->post(route('comments.store', threadRouteParameters($pet)), [
                'content' => Str::repeat('a', $maxLength + 1),
            ])
            ->assertInvalid(['content' => 'must not be greater than '.$maxLength]);

        $this->assertDatabaseEmpty('comments');
    });

    test('stores a comment of the full configured length intact', function () {
        $author = User::factory()->create();
        $pet = Pet::factory()->create();
        $content = Str::repeat('a', config('petconnect.comments.max_length'));

        $this->actingAs($author)
            ->from(route('pets.show', $pet))
            ->post(route('comments.store', threadRouteParameters($pet)), ['content' => $content])
            ->assertValid();

        expect(Comment::query()->sole()->content)->toBe($content);
    });

    test('rejects a reply whose parent belongs to another listing, and writes nothing', function () {
        $pet = Pet::factory()->create();
        $parentOnAnotherListing = Comment::factory()->for(Pet::factory()->create(), 'commentable')->create();

        $this->actingAs(User::factory()->create())
            ->post(route('comments.store', threadRouteParameters($pet)), [
                'content' => 'Sneaking in under another listing.',
                'parent_id' => $parentOnAnotherListing->getKey(),
            ])
            ->assertInvalid(['parent_id' => 'The comment you are replying to is no longer part of this discussion.']);

        $this->assertDatabaseMissing('comments', ['content' => 'Sneaking in under another listing.']);
    });

    test('rejects a reply whose parent does not exist, and writes nothing', function () {
        $pet = Pet::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('comments.store', threadRouteParameters($pet)), [
                'content' => 'Answering a comment that is gone.',
                'parent_id' => 9999,
            ])
            ->assertInvalid(['parent_id' => 'The comment you are replying to is no longer part of this discussion.']);

        $this->assertDatabaseEmpty('comments');
    });

    test('rejects a reply to a reply, because threads are two levels deep', function () {
        $pet = Pet::factory()->create();
        $parent = Comment::factory()->for($pet, 'commentable')->create();
        $reply = Comment::factory()->reply($parent)->create();

        $this->actingAs(User::factory()->create())
            ->post(route('comments.store', threadRouteParameters($pet)), [
                'content' => 'A third level.',
                'parent_id' => $reply->getKey(),
            ])
            ->assertInvalid(['parent_id' => 'Replies cannot be replied to; answer the original comment instead.']);

        $this->assertDatabaseMissing('comments', ['content' => 'A third level.']);
    });

    test('returns 429 once the acting user passes 10 comments in a minute', function () {
        $author = User::factory()->create();
        $pet = Pet::factory()->create();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->actingAs($author)
                ->post(route('comments.store', threadRouteParameters($pet)), ['content' => 'Comment '.$attempt])
                ->assertRedirect();
        }

        $this->actingAs($author)
            ->post(route('comments.store', threadRouteParameters($pet)), ['content' => 'One too many'])
            ->assertTooManyRequests();

        $this->assertDatabaseMissing('comments', ['content' => 'One too many']);
    });
});

describe('update', function () {
    test('redirects a guest to the login page and leaves the comment unchanged', function () {
        $comment = Comment::factory()->create(['content' => 'Original']);

        $this->put(route('comments.update', $comment), ['content' => 'Edited'])
            ->assertRedirect(route('login'));

        expect($comment->fresh()->content)->toBe('Original');
    });

    test('redirects an unverified user to the verification notice and leaves the comment unchanged', function () {
        $author = User::factory()->unverified()->create();
        $comment = Comment::factory()->for($author)->create(['content' => 'Original']);

        $this->actingAs($author)
            ->put(route('comments.update', $comment), ['content' => 'Edited'])
            ->assertRedirect(route('verification.notice'));

        expect($comment->fresh()->content)->toBe('Original');
    });

    test('returns 403 for a user who did not write the comment and leaves it unchanged', function () {
        $comment = Comment::factory()->create(['content' => 'Original']);

        $this->actingAs(User::factory()->create())
            ->put(route('comments.update', $comment), ['content' => 'Edited'])
            ->assertForbidden();

        expect($comment->fresh()->content)->toBe('Original');
    });

    test('applies the edit for the author', function () {
        $author = User::factory()->create();
        $comment = Comment::factory()->for($author)->create(['content' => 'Original']);

        $this->actingAs($author)
            ->from(route('home'))
            ->put(route('comments.update', $comment), ['content' => 'Edited'])
            ->assertRedirect(route('home'));

        $this->assertDatabaseHas('comments', [
            'id' => $comment->getKey(),
            'content' => 'Edited',
        ]);
    });

    test('cleans the edited text, so a comment cannot be edited around the filter', function () {
        $author = User::factory()->create();
        $comment = Comment::factory()->for($author)->create(['content' => 'Original']);

        $this->actingAs($author)
            ->from(route('home'))
            ->put(route('comments.update', $comment), ['content' => '  What a   bitch  '])
            ->assertValid();

        expect($comment->fresh()->content)->toBe('What a ****');
    });

    test('rejects an edit with no content and leaves the comment unchanged', function () {
        $author = User::factory()->create();
        $comment = Comment::factory()->for($author)->create(['content' => 'Original']);

        $this->actingAs($author)
            ->put(route('comments.update', $comment), ['content' => ''])
            ->assertInvalid(['content' => 'The content field is required.']);

        expect($comment->fresh()->content)->toBe('Original');
    });
});

describe('destroy', function () {
    test('redirects a guest to the login page and leaves the comment in place', function () {
        $comment = Comment::factory()->create();

        $this->delete(route('comments.destroy', $comment))->assertRedirect(route('login'));

        $this->assertModelExists($comment);
    });

    test('redirects an unverified user to the verification notice and leaves the comment in place', function () {
        $author = User::factory()->unverified()->create();
        $comment = Comment::factory()->for($author)->create();

        $this->actingAs($author)
            ->delete(route('comments.destroy', $comment))
            ->assertRedirect(route('verification.notice'));

        $this->assertModelExists($comment);
    });

    test('returns 403 for a user who did not write the comment and leaves it in place', function () {
        $comment = Comment::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('comments.destroy', $comment))
            ->assertForbidden();

        $this->assertModelExists($comment);
    });

    test('removes the comment for its author', function () {
        $author = User::factory()->create();
        $comment = Comment::factory()->for($author)->create();

        $this->actingAs($author)
            ->from(route('home'))
            ->delete(route('comments.destroy', $comment))
            ->assertRedirect(route('home'));

        $this->assertModelMissing($comment);
    });
});

describe('like', function () {
    test('redirects a guest to the login page and records no like', function () {
        $comment = Comment::factory()->create();

        $this->post(route('comments.like', $comment))->assertRedirect(route('login'));

        $this->assertDatabaseEmpty('likes');
    });

    test('redirects an unverified user to the verification notice and records no like', function () {
        $comment = Comment::factory()->create();

        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('comments.like', $comment))
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseEmpty('likes');
    });

    test('records a like for a verified user', function () {
        $liker = User::factory()->create();
        $comment = Comment::factory()->create();

        $this->actingAs($liker)
            ->from(route('home'))
            ->post(route('comments.like', $comment))
            ->assertRedirect(route('home'));

        $this->assertDatabaseHas('likes', [
            'user_id' => $liker->getKey(),
            'likeable_type' => 'comment',
            'likeable_id' => $comment->getKey(),
        ]);
    });

    test('returns 429 once the acting user passes 30 comment likes in a minute', function () {
        $liker = User::factory()->create();
        $comment = Comment::factory()->create();

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->actingAs($liker)->post(route('comments.like', $comment))->assertRedirect();
        }

        $this->actingAs($liker)->post(route('comments.like', $comment))->assertTooManyRequests();
    });
});

/**
 * The whole `{comment}` binding surface, grouped by the binding rather than by
 * controller action because one rule decides all four routes: Comment's route
 * binding refuses to bind a comment whose commentable is hidden, so a soft
 * deleted listing's discussion 404s from every route that addresses a comment
 * by its id and never names the listing.
 *
 * Each 404 below is paired with the live-listing case, so a failure separates
 * "the trashed listing is hidden" from "the route is broken for everyone".
 *
 * Every write case acts as the comment's own author: `Authenticate` sorts ahead
 * of `SubstituteBindings`, so a guest is redirected to the login page before
 * the binding is ever resolved and could never have seen the leak.
 */
describe('route binding', function () {
    test('returns 404 from the replies of a comment on a soft deleted listing, and leaks neither the text nor its author', function () {
        $author = User::factory()->create(['username' => 'nadia-hidden']);
        $pet = Pet::factory()->create();
        $parent = Comment::factory()->for($pet, 'commentable')->create();
        Comment::factory()->for($author)->reply($parent)->create(['content' => 'Ring me on the number in my bio.']);
        $pet->delete();

        $this->actingAs($author)
            ->get(route('comments.replies', $parent))
            ->assertNotFound()
            ->assertDontSee('Ring me on the number in my bio.')
            ->assertDontSee('nadia-hidden');
    });

    test('returns 404 from the like of a comment on a soft deleted listing, and records no like', function () {
        $author = User::factory()->create();
        $pet = Pet::factory()->create();
        $comment = Comment::factory()->for($author)->for($pet, 'commentable')->create();
        $pet->delete();

        $this->actingAs($author)
            ->post(route('comments.like', $comment))
            ->assertNotFound();

        $this->assertDatabaseEmpty('likes');
    });

    test('returns 404 from the update of a comment on a soft deleted listing, and leaves the text unchanged', function () {
        $author = User::factory()->create();
        $pet = Pet::factory()->create();
        $comment = Comment::factory()->for($author)->for($pet, 'commentable')->create(['content' => 'Original']);
        $pet->delete();

        $this->actingAs($author)
            ->put(route('comments.update', $comment), ['content' => 'Edited'])
            ->assertNotFound();

        expect($comment->fresh()->content)->toBe('Original');
    });

    test('returns 404 from the destroy of a comment on a soft deleted listing, and leaves the comment in place', function () {
        $author = User::factory()->create();
        $pet = Pet::factory()->create();
        $comment = Comment::factory()->for($author)->for($pet, 'commentable')->create();
        $pet->delete();

        $this->actingAs($author)
            ->delete(route('comments.destroy', $comment))
            ->assertNotFound();

        $this->assertModelExists($comment);
    });

    test('serves a comment on a live listing', function (string $method, string $routeName, array $payload, int $status) {
        $author = User::factory()->create();
        $comment = Comment::factory()->for($author)->create();

        $this->actingAs($author)
            ->from(route('home'))
            ->call($method, route($routeName, $comment), $payload)
            ->assertStatus($status);
    })->with([
        'replies' => ['get', 'comments.replies', [], 200],
        'like' => ['post', 'comments.like', [], 302],
        'update' => ['put', 'comments.update', ['content' => 'Edited'], 302],
        'destroy' => ['delete', 'comments.destroy', [], 302],
    ]);

    test('returns 404 for a comment id that does not exist', function (string $method, string $routeName, array $payload) {
        $this->actingAs(User::factory()->create())
            ->call($method, route($routeName, 9999), $payload)
            ->assertNotFound();
    })->with([
        'replies' => ['get', 'comments.replies', []],
        'like' => ['post', 'comments.like', []],
        'update' => ['put', 'comments.update', ['content' => 'Edited']],
        'destroy' => ['delete', 'comments.destroy', []],
    ]);
});
