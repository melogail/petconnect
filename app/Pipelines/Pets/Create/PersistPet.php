<?php

namespace App\Pipelines\Pets\Create;

use App\Models\Pet;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Write the listing row inside a transaction.
 *
 * The owner is stamped here rather than in a Normalize* step, so nothing that
 * reads the submitted payload can ever influence which account a listing is
 * filed under.
 */
class PersistPet
{
    public function handle(CreatePetContext $context, Closure $next): mixed
    {
        $pet = DB::transaction(fn (): Pet => Pet::create([
            ...$context->attributes(),
            'user_id' => $context->owner->getKey(),
        ]));

        $context->setPet($pet);

        return $next($context);
    }
}
