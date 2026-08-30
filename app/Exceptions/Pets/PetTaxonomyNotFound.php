<?php

namespace App\Exceptions\Pets;

use Illuminate\Validation\ValidationException;

/**
 * A pet pipeline aborted because a taxonomy row named by the form is gone.
 *
 * Categories and breeds are administered in Nova and are never created on the
 * fly, so a request naming one that no longer exists is a stale form rather
 * than a new taxonomy entry. The Form Request already rejects unknown ids;
 * this exception covers the race where the row is deleted between validation
 * and persistence, and turns it back into a field error on the control the
 * user can actually fix instead of the 500 the legacy service produced.
 *
 * It extends ValidationException rather than rendering itself: Laravel already
 * turns that into a 422 with `message`/`errors` for an API client and a
 * redirect back with the errors in the session for a form post. The hand-rolled
 * render() this replaced also flashed `$request->input()` wholesale, where
 * Laravel flashes everything except the fields on `dontFlash`.
 *
 * That inheritance does mean the class is not HTTP-free: ValidationException
 * carries `$response`, a 422 `$status` and `$redirectTo`. That is accepted
 * deliberately — a pure domain exception plus a `render` mapping would be two
 * more moving parts for byte-identical behaviour, and this abort genuinely is a
 * field-level input problem. A step still throws it without knowing any of
 * that. See .ai/rules/pipelines.md for when a domain abort may use
 * ValidationException as its base and when it may not.
 */
abstract class PetTaxonomyNotFound extends ValidationException
{
    /**
     * Diagnostic detail for the log. The user-facing text is the field message.
     *
     * @var array<string, mixed>
     */
    protected array $diagnostics = [];

    /**
     * Present the abort as a validation failure on the offending field.
     *
     * @param  array<string, mixed>  $diagnostics
     */
    protected static function forField(string $field, string $message, array $diagnostics): static
    {
        /** @var static $exception */
        $exception = static::withMessages([$field => $message]);

        $exception->diagnostics = $diagnostics;

        return $exception;
    }

    /**
     * Extra context Laravel's logger attaches to the record.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->diagnostics;
    }
}
