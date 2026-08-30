<?php

namespace App\Pipelines\Pets\Update;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Write the changed listing row inside a transaction.
 *
 * The update is a full replacement, not a patch: the Normalize* steps build a
 * value for every column the form owns, so a field the request omits is written
 * as null. That is deliberate and matches the verb — the edit form posts the
 * whole listing, and a partial write would make "I cleared the vet's phone
 * number" indistinguishable from "I did not send that field". PetDetailResource
 * emits exactly the keys the form must send back, so a client that round-trips
 * that payload never loses a value.
 *
 * `user_id` is never part of the bag, so an update cannot move a listing to
 * another account.
 */
class PersistPet
{
    public function handle(UpdatePetContext $context, Closure $next): mixed
    {
        $pet = $context->pet();

        DB::transaction(fn (): bool => $pet->update($context->attributes()));

        return $next($context);
    }
}
