<?php

namespace App\Pipelines\Pets\Shared;

use App\Pipelines\Pets\PetAttributeContext;
use Closure;

/**
 * Flatten the nested `location` group, including its coordinate pair, onto the
 * flat columns the pets table actually has.
 *
 * Coordinates are optional: a listing without them simply never appears in a
 * nearby search, because Pet::nearby() requires both to be non-null.
 */
class NormalizeLocation
{
    public function handle(PetAttributeContext $context, Closure $next): mixed
    {
        $context->merge([
            'address' => $context->input('location.address'),
            'detailed_address' => $context->input('location.detailedAddress'),
            'city' => $context->input('location.city'),
            'state' => $context->input('location.state'),
            'postal_code' => $context->input('location.postalCode'),
            'country' => $context->input('location.country'),
            'latitude' => $context->input('location.coordinates.lat'),
            'longitude' => $context->input('location.coordinates.lng'),
        ]);

        return $next($context);
    }
}
