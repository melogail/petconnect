<?php

namespace App\Pipelines\Pets\Shared;

use App\Enums\HealthStatus;
use App\Pipelines\Pets\PetAttributeContext;
use Closure;

/**
 * Flatten the nested `health` group and reduce its three repeaters to the JSON
 * shapes the pets table documents.
 *
 * `health.status` is optional on the form, so it is resolved with tryFrom() and
 * falls back to Healthy; from() would fatal on the empty value a blank select
 * submits.
 *
 * Each repeater is rebuilt key by key rather than stored as submitted, so a row
 * the form left half filled in cannot widen the stored shape: a vaccination is
 * always {name, date}, a medication always {name, usage}, and an allergy always
 * a plain string. Rows with no name are dropped, and an entirely empty repeater
 * is stored as null rather than as an empty array.
 */
class NormalizeHealthData
{
    public function handle(PetAttributeContext $context, Closure $next): mixed
    {
        $context->merge([
            'health_status' => HealthStatus::tryFrom((string) $context->input('health.status'))
                ?? HealthStatus::Healthy,
            'vaccinated' => (bool) $context->input('health.vaccinated', false),
            'spayed_neutered' => (bool) $context->input('health.spayedNeutered', false),
            'special_needs' => $context->input('health.specialNeeds'),
            'last_vet_visit' => filled($context->input('health.lastVetVisit'))
                ? $context->input('health.lastVetVisit')
                : null,
            'vaccinations' => $this->records($context->input('health.vaccinations'), 'date'),
            'medications' => $this->records($context->input('health.medications'), 'usage'),
            'allergies' => $this->allergies($context->input('health.allergies')),
            'vet_name' => $context->input('health.vetName'),
            'vet_phone' => $context->input('health.vetPhone'),
        ]);

        return $next($context);
    }

    /**
     * Named clinical records, each reduced to its name plus one detail column.
     *
     * @return list<array{name: string, date?: string|null, usage?: string|null}>|null
     */
    private function records(mixed $records, string $detailKey): ?array
    {
        if (! is_array($records)) {
            return null;
        }

        $normalized = [];

        foreach ($records as $record) {
            if (! is_array($record) || blank($record['name'] ?? null)) {
                continue;
            }

            $detail = $record[$detailKey] ?? null;

            $normalized[] = [
                'name' => (string) $record['name'],
                $detailKey => blank($detail) ? null : (string) $detail,
            ];
        }

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @return list<string>|null
     */
    private function allergies(mixed $allergies): ?array
    {
        if (! is_array($allergies)) {
            return null;
        }

        $normalized = array_values(array_map(
            fn (mixed $allergy): string => (string) $allergy,
            array_filter($allergies, fn (mixed $allergy): bool => filled($allergy) && is_scalar($allergy)),
        ));

        return $normalized === [] ? null : $normalized;
    }
}
