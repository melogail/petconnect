<?php

namespace App\Pipelines\Pets\Shared;

use App\Enums\ListingType;
use App\Enums\PetGender;
use App\Enums\PetStatus;
use App\Pipelines\Pets\PetAttributeContext;
use Closure;

/**
 * Map the identity and listing fields of the form onto their columns.
 *
 * A price only survives on a sale listing: adoption and mating listings are
 * free by definition, and letting a stale price through would show a price tag
 * on a give-away.
 *
 * The three closed sets are resolved to their enum cases here rather than
 * passed through as strings, so an unmapped value fails loudly at the boundary
 * instead of reaching a cast.
 */
class NormalizeBasicAttributes
{
    public function handle(PetAttributeContext $context, Closure $next): mixed
    {
        $listingType = ListingType::from((string) $context->input('listing_type', ListingType::Adoption->value));

        $context->merge([
            'name' => $context->input('name'),
            'age' => (string) $context->input('age'),
            'gender' => PetGender::from((string) $context->input('gender', PetGender::Male->value)),
            'color' => $context->input('color'),
            'weight' => $context->input('weight'),
            'description' => $context->input('description'),
            'listing_type' => $listingType,
            'price' => $listingType === ListingType::Sale ? $context->input('price') : null,
            'status' => PetStatus::from((string) $context->input('status', PetStatus::Available->value)),
        ]);

        return $next($context);
    }
}
