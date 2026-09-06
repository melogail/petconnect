<?php

namespace App\Pipelines\Profiles\UpdateProfile;

use Closure;
use Illuminate\Support\Arr;

/**
 * Write the edited fields to the user row.
 *
 * ## A profile save is a PATCH
 *
 * Only the keys the request actually sent are filled, so an omitted field means
 * "I did not touch that" rather than "set it to null". That is the opposite of
 * the pet update flow, which writes a complete attribute bag because its form
 * posts the whole listing and "I cleared the vet's phone number" has to be
 * distinguishable from "I did not send that field". A profile form is edited a
 * section at a time — the account panel, the location panel, the language
 * control — and full-replacement semantics would let a form that renders half
 * the fields wipe the other half.
 *
 * The cost of the choice is that a key renamed in
 * App\Concerns\ProfileValidationRules and not in
 * Http\Resources\Profile\ProfileFormResource stops saving silently instead of
 * 422ing. That trade is stated in the Concern; the key-parity test is the
 * guard.
 *
 * ## What is stripped, and why it is stripped here
 *
 * `image` never reaches the model: it is a file, consumed by
 * UploadProfileImage. It is the only such key left. `current_password` and
 * `password` used to be stripped here too, and are not because the form no
 * longer accepts them at all — a password change is Fortify's
 * `user-password.update` and nothing else (see
 * App\Concerns\ProfileValidationRules). Nothing credential-shaped can reach
 * this bag now, so the guard against it is the absent rule rather than this
 * list.
 *
 * `is_active` is not stripped either, because it was never accepted: it is
 * absent from User's #[Fillable] and from the Form Request, so a forged field
 * is discarded by validation and would throw at the model if it somehow
 * arrived (preventSilentlyDiscardingAttributes is on).
 *
 * ## Changing the email un-verifies the account
 *
 * The starter kit's ProfileController did this inline; it moves here so the
 * rule holds for every caller of the flow, not only the HTTP one. Without it a
 * user could reach a verified state on an address they do not control by
 * verifying one email and then editing the field.
 *
 * No transaction around this single UPDATE — the Action opens one around the
 * whole run, because the avatar rows and the attributes have to land together.
 */
class PersistProfileAttributes
{
    /**
     * Keys that are consumed by other steps and must never be mass assigned.
     *
     * @var list<string>
     */
    protected const NON_ATTRIBUTE_KEYS = ['image'];

    public function handle(UpdateProfileContext $context, Closure $next): mixed
    {
        $user = $context->user;

        $user->fill(Arr::except($context->attributes, self::NON_ATTRIBUTE_KEYS));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $next($context);
    }
}
