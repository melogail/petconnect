<?php

namespace App\Actions\Users;

use App\Exceptions\Users\MediaDirectoryUnavailable;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Create the user row for a new registration, retrying a colliding media
 * directory name a bounded number of times.
 *
 * ## Why this is an Action and not a pipeline
 *
 * It was scoped as `Pipelines\Users\RegisterUser` with the steps
 * `AssignMediaDirectoryName -> CreateUserRecord -> DispatchRegisteredEvent ->
 * SendVerificationEmail`. Three of those four turn out to be somebody else's
 * work already, and building them would have duplicated it rather than moved
 * it:
 *
 * - **AssignMediaDirectoryName.** UserObserver::creating already assigns the
 *   column, and it is the only assignment site in the application — factories,
 *   seeders and Nova all go through it, none of which would ever run a pipeline
 *   step. Adding a second assignment would have made "assigned in exactly one
 *   place" false rather than true. The reconciliation is that the observer
 *   keeps the assignment, User::freshMediaDirectoryName() holds the draw, and
 *   this Action owns only the *retry* — which works precisely because a retried
 *   `User::create()` builds a fresh model and re-enters the observer.
 * - **DispatchRegisteredEvent.** Fortify's own RegisteredUserController does
 *   `event(new Registered($user = $creator->create($request->all())))`. This
 *   Action is called from inside `$creator->create()`, so dispatching here
 *   would fire Registered twice for one registration.
 * - **SendVerificationEmail.** Laravel's SendEmailVerificationNotification
 *   listener is registered for Registered out of the box and is what sends the
 *   mail. A step doing it directly would send two verification emails.
 *
 * What is left is one INSERT with a retry loop, which .ai/rules/pipelines.md is
 * explicit about: default to inline work, and a pipeline whose steps are one
 * real operation and three no-ops is a file count, not a design. If a later
 * phase adds genuine sequence — a referral code, a welcome campaign, a default
 * workspace — this becomes a pipeline then, and the retry becomes its
 * PersistUser step.
 *
 * ## The retry, and what it is allowed to catch
 *
 * The catch is on Illuminate\Database\UniqueConstraintViolationException and
 * then narrowed to the message naming `media_directory_name`, which is the one
 * unique index on `users` this Action can do anything about: `email` and
 * `username` collisions are validation failures the Form Request has already
 * answered, and swallowing them here would turn "this email is taken" into
 * three silent retries and a confusing 500. Anything that is not a
 * media-directory collision is rethrown untouched.
 *
 * Compare the legacy version, which caught QueryException, tested
 * `getCode() == '23000'` plus a substring, and recursed into itself with no
 * bound — see App\Exceptions\Users\MediaDirectoryUnavailable.
 *
 * ## What it deliberately does not do
 *
 * It does not hash the password: `password` is cast `hashed` on User, so
 * assigning the plain value hashes it once and hashing here would double-hash.
 * It does not validate: Actions\Fortify\CreateNewUser owns that, because
 * Fortify's contract hands it the raw input array.
 */
class RegisterUser
{
    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     *
     * @throws MediaDirectoryUnavailable When every draw collided.
     */
    public function handle(array $attributes): User
    {
        $attempts = max(1, (int) config('petconnect.profiles.media_directory_attempts', 3));

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return User::create($attributes);
            } catch (UniqueConstraintViolationException $exception) {
                if (! $this->isMediaDirectoryCollision($exception)) {
                    throw $exception;
                }
            }
        }

        throw MediaDirectoryUnavailable::afterAttempts($attempts);
    }

    /**
     * Whether the index that refused the insert was the media directory one.
     *
     * The driver names the offending index or column in the message and nowhere
     * else structured, so this is a substring test — but it is a substring test
     * on an exception the connection has *already* classified as a unique
     * violation, not on every QueryException, which is what makes it safe
     * enough to retry on. A false negative rethrows, which is the correct
     * failure direction.
     */
    protected function isMediaDirectoryCollision(UniqueConstraintViolationException $exception): bool
    {
        return str_contains($exception->getMessage(), 'media_directory_name');
    }
}
