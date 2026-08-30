<?php

namespace App\Pipelines\Pets\Shared;

use App\Pipelines\Pets\PetAttributeContext;
use Closure;

/**
 * Reduce the free-form extras to a key/value map, dropping any pair missing
 * either half.
 *
 * `additional_info` is a map, not the legacy [{key, value}] repeater: the
 * legacy detail page matched user-typed keys against hardcoded English labels,
 * which broke the moment somebody typed Arabic or made a typo. A pair with a
 * blank key or a blank value carries no information either way, so it is
 * dropped rather than stored; an empty map is stored as null.
 */
class NormalizeAdditionalInfo
{
    public function handle(PetAttributeContext $context, Closure $next): mixed
    {
        $additionalInfo = $context->input('additionalInfo');

        if (! is_array($additionalInfo)) {
            $context->set('additional_info', null);

            return $next($context);
        }

        $normalized = [];

        foreach ($additionalInfo as $key => $value) {
            if (blank($key) || blank($value)) {
                continue;
            }

            $normalized[(string) $key] = $value;
        }

        $context->set('additional_info', $normalized === [] ? null : $normalized);

        return $next($context);
    }
}
