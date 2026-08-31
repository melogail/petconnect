<?php

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The paginator's own COUNT and SELECT, plus the separate COUNT behind the
 * unread badge. Nothing is eager loaded: `notifications.notifiable` is a morph
 * pair that no part of the payload dereferences, because every notification
 * writes self-contained identifiers into `data` precisely so a row renders
 * without loading anything it points at.
 *
 * Flat whether the account holds 5 notifications or 500, which is what the
 * second budget test asserts.
 */
const NOTIFICATION_INBOX_QUERY_CEILING = 3;

/**
 * Put a notification row in a user's inbox.
 *
 * Written directly rather than through a notification class: the shape of
 * `data` is each notification's own contract and is tested where that
 * notification is, and going through `notify()` would also fire the
 * notification's other channels. Only the row matters here.
 */
function inboxRow(User $user, array $data = [], ?CarbonInterface $createdAt = null, bool $read = false): DatabaseNotification
{
    /** @var DatabaseNotification $notification */
    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\ModelLikedNotification',
        'data' => [
            'type' => 'like',
            'message_key' => 'notifications.liked_pet',
            'message_replace' => ['name' => 'Luna'],
            ...$data,
        ],
        'read_at' => $read ? now() : null,
        'created_at' => $createdAt ?? now(),
    ]);

    return $notification;
}

/**
 * The SQL one request issues, so a test can say "one UPDATE" rather than "some
 * number of queries".
 *
 * @return list<string>
 */
function inboxStatements(Closure $request): array
{
    $statements = [];

    DB::listen(function ($query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    $request();

    return $statements;
}

/**
 * @param  list<string>  $statements
 * @return list<string>
 */
function statementsStartingWith(array $statements, string $verb): array
{
    return array_values(array_filter(
        $statements,
        fn (string $sql): bool => Str::startsWith(Str::lower(ltrim($sql)), $verb)
    ));
}

describe('index', function () {
    test('redirects a guest to the login page', function () {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    });

    test('redirects an unverified user to the verification notice', function () {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('notifications.index'))
            ->assertRedirect(route('verification.notice'));
    });

    /**
     * The bell menu is a panel on whatever page the user is already on, so it
     * fetches its list with XHR rather than making the user leave. The paginator
     * keeps its data/links/meta envelope even though `withoutWrapping()` is on
     * application-wide.
     */
    test('returns the viewer own notifications as a paginated json envelope', function () {
        $reader = User::factory()->create();
        $mine = inboxRow($reader);
        $theirs = inboxRow(User::factory()->create());

        $this->actingAs($reader)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->getKey())
            ->assertJsonPath('data.0.type', 'like')
            ->assertJsonPath('data.0.read', false)
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->assertModelExists($theirs);
    });

    /**
     * Notification payloads store translation keys rather than rendered text,
     * because a row outlives the reader's locale: a user who switches to Arabic
     * must see their whole history in Arabic, not sentences frozen in the
     * language they were signed in with when each arrived. The resource calls
     * `__()` on neither.
     */
    test('ships the translation key rather than a rendered sentence', function () {
        $reader = User::factory()->create();
        inboxRow($reader);

        $this->actingAs($reader)
            ->getJson(route('notifications.index'))
            ->assertJsonPath('data.0.message_key', 'notifications.liked_pet')
            ->assertJsonPath('data.0.message_replace.name', 'Luna');
    });

    test('orders the inbox newest first', function () {
        $reader = User::factory()->create();
        $older = inboxRow($reader, createdAt: now()->subDay());
        $newer = inboxRow($reader, createdAt: now());

        $this->actingAs($reader)
            ->getJson(route('notifications.index'))
            ->assertJsonPath('data.0.id', $newer->getKey())
            ->assertJsonPath('data.1.id', $older->getKey());
    });

    /**
     * The badge is about the mailbox, not about the slice being rendered.
     * Counting the page would report "2 unread" to a reader who has five.
     */
    test('counts the unread total across the mailbox and not across the page', function () {
        config(['petconnect.notifications.inbox_per_page' => 2]);
        $reader = User::factory()->create();
        foreach (range(1, 5) as $ignored) {
            inboxRow($reader);
        }
        inboxRow($reader, read: true);

        $this->actingAs($reader)
            ->getJson(route('notifications.index'))
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.unread_count', 5);
    });

    test('issues no write, so opening the bell does not clear the badge', function () {
        $reader = User::factory()->create();
        inboxRow($reader);

        $statements = inboxStatements(fn () => $this->actingAs($reader)
            ->getJson(route('notifications.index'))
            ->assertOk());

        expect(statementsStartingWith($statements, 'update'))->toBe([])
            ->and($reader->unreadNotifications()->count())->toBe(1);
    });

    test('builds the inbox in a constant number of queries however full it is', function () {
        $reader = User::factory()->create();
        foreach (range(1, 2) as $ignored) {
            inboxRow($reader);
        }

        $atTwo = count(inboxStatements(fn () => $this->actingAs($reader)
            ->getJson(route('notifications.index'))->assertOk()));

        foreach (range(1, 20) as $ignored) {
            inboxRow($reader);
        }

        $atTwentyTwo = count(inboxStatements(fn () => $this->actingAs($reader)
            ->getJson(route('notifications.index'))->assertOk()));

        expect($atTwo)->toBe($atTwentyTwo)
            ->and($atTwentyTwo)->toBe(NOTIFICATION_INBOX_QUERY_CEILING);
    });
});

describe('read', function () {
    test('marks the notification read and returns to the page the reader was on', function () {
        $this->freezeTime();
        $reader = User::factory()->create();
        $notification = inboxRow($reader);

        $this->actingAs($reader)
            ->from(route('dashboard'))
            ->post(route('notifications.read', $notification->getKey()))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->getKey(),
            'read_at' => now()->toDateTimeString(),
        ]);
    });

    /**
     * Ownership is the query, not a check after it: the row is resolved through
     * `$user->notifications()`, so somebody else's id is a 404 rather than a 403
     * that would confirm the notification exists.
     */
    test('returns 404 for another user notification and leaves it unread', function () {
        $notification = inboxRow(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->post(route('notifications.read', $notification->getKey()))
            ->assertNotFound();

        expect($notification->fresh()->read_at)->toBeNull();
    });

    test('returns 404 for a notification id that does not exist', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('notifications.read', (string) Str::uuid()))
            ->assertNotFound();
    });

    /**
     * `markAsRead()` early-returns on a row whose `read_at` is set, so a double
     * tap costs one SELECT and no write — and does not move the timestamp.
     */
    test('writes nothing when the notification was already read', function () {
        $reader = User::factory()->create();
        $notification = inboxRow($reader, read: true);
        $readAt = $notification->fresh()->read_at;

        $statements = inboxStatements(fn () => $this->actingAs($reader)
            ->post(route('notifications.read', $notification->getKey()))
            ->assertRedirect());

        expect(statementsStartingWith($statements, 'update'))->toBe([])
            ->and($notification->fresh()->read_at->equalTo($readAt))->toBeTrue();
    });
});

describe('read-all', function () {
    /**
     * One UPDATE against the relation, not a fetch-then-loop. The legacy
     * controller did `$request->user()->unreadNotifications->markAsRead()` —
     * the property, not the method — which hydrated every unread row and issued
     * one UPDATE per row: 301 queries for 300 notifications.
     */
    test('clears the whole badge in a single update', function () {
        $reader = User::factory()->create();
        foreach (range(1, 5) as $ignored) {
            inboxRow($reader);
        }

        $statements = inboxStatements(fn () => $this->actingAs($reader)
            ->post(route('notifications.read-all'))
            ->assertRedirect());

        expect(statementsStartingWith($statements, 'update'))->toHaveCount(1)
            ->and($reader->unreadNotifications()->count())->toBe(0);
    });

    test('leaves another user unread notifications alone', function () {
        $other = User::factory()->create();
        inboxRow($other);

        $this->actingAs(User::factory()->create())
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        expect($other->unreadNotifications()->count())->toBe(1);
    });
});

describe('destroy-all', function () {
    /**
     * One DELETE, and it clears read *and* unread: a "delete all" that quietly
     * kept the unread ones would leave the badge lit after the user emptied the
     * list.
     */
    test('empties the inbox in a single delete, read and unread alike', function () {
        $reader = User::factory()->create();
        inboxRow($reader);
        inboxRow($reader, read: true);

        $statements = inboxStatements(fn () => $this->actingAs($reader)
            ->delete(route('notifications.destroy-all'))
            ->assertRedirect());

        expect(statementsStartingWith($statements, 'delete'))->toHaveCount(1)
            ->and($reader->notifications()->count())->toBe(0);
    });

    test('leaves another user inbox alone', function () {
        $other = User::factory()->create();
        inboxRow($other);

        $this->actingAs(User::factory()->create())
            ->delete(route('notifications.destroy-all'))
            ->assertRedirect();

        expect($other->notifications()->count())->toBe(1);
    });
});
