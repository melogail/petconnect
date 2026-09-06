<?php

namespace App\Pipelines\Pets\Shared;

use App\Pipelines\Pets\PetAttributeContext;
use Closure;

/**
 * Store personality traits as a re-indexed list of capitalised strings.
 *
 * ucfirst() is multibyte safe here only for the Latin alphabet; Arabic input
 * passes through unchanged, which is the intended behaviour.
 */
class NormalizeTraits
{
    public function handle(PetAttributeContext $context, Closure $next): mixed
    {
        $traits = $context->input('traits');

        if (! is_array($traits)) {
            $context->set('traits', null);

            return $next($context);
        }

        $normalized = array_values(array_map(
            fn (mixed $trait): string => ucfirst(trim((string) $trait)),
            array_filter($traits, fn (mixed $trait): bool => filled($trait) && is_scalar($trait)),
        ));

        $context->set('traits', $normalized === [] ? null : $normalized);

        return $next($context);
    }
}
