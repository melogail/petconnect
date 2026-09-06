<?php

namespace App\Exceptions\Users;

use RuntimeException;

/**
 * Registration could not find a free `media_directory_name` within its budget.
 *
 * A plain domain exception, not a ValidationException, because nothing the
 * registrant typed caused it and there is no field to attach it to. Per
 * .ai/rules/pipelines.md, ValidationException is borrowed only when the abort
 * really is a field-level input problem; this one is an infrastructure
 * condition and gets the 500 it deserves so it shows up in the logs.
 *
 * ## Why this class exists at all
 *
 * `users.media_directory_name` is unique and drawn at random from 10^15..10^18
 * by UserObserver::creating. A collision is possible and has to be retried.
 * The legacy RegisterUserAction retried by calling **itself**:
 *
 *     catch (QueryException $e) {
 *         if ($this->isMediaDirectoryCollision($e)) {
 *             return $this->execute($request);
 *         }
 *         throw $e;
 *     }
 *
 * with no depth limit, and its collision test was
 * `$e->getCode() == '23000' && str_contains($e->getMessage(), 'media_directory_name')`
 * — a substring match on the driver's message. Any integrity error whose text
 * mentioned that column recursed forever and ended in a stack overflow rather
 * than an error anybody could read. Actions\Users\RegisterUser retries a bounded
 * number of times (`petconnect.profiles.media_directory_attempts`) and throws
 * this when the budget runs out, so the failure is finite, named and logged.
 */
class MediaDirectoryUnavailable extends RuntimeException
{
    public static function afterAttempts(int $attempts): self
    {
        return new self(
            "Could not allocate a unique media_directory_name after {$attempts} attempts."
        );
    }
}
