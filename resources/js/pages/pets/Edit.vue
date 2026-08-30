<script setup lang="ts">
// Placeholder page — proves the edit-form props arrive. Replaced in Phase 4.
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { show } from '@/routes/pets';
import type {
    PetCategoryOption,
    PetDetail,
    PetGender,
    PetHealthStatus,
    PetListingType,
    PetMedication,
    PetStatus,
    PetVaccination,
    SelectOption,
} from '@/types';

const props = defineProps<{
    pet: PetDetail;
    categories: PetCategoryOption[];
    listingTypes: SelectOption<PetListingType>[];
    statuses: SelectOption<PetStatus>[];
    genders: SelectOption<PetGender>[];
    healthStatuses: SelectOption<PetHealthStatus>[];
}>();

/**
 * What PUT /pets/{pet} accepts — the write shape, not the read shape.
 *
 * The update flow is a full replacement, not a patch: the pipeline builds a
 * value for every column the form owns, so a key the request omits is written
 * as null. **Every scalar key is therefore `present` in
 * App\Concerns\PetValidationRules** — `present` allows null and rejects
 * absence, which turns a forgotten key into a 422 instead of a silent wipe.
 * Clearing a field means sending it as `null`.
 *
 * Scalars that must always be on the wire, `null` when empty: `breed_id`,
 * `weight`, `price`, `location.address`, `location.detailedAddress`,
 * `location.postalCode`, and every `health.*` scalar (`status`, `vaccinated`,
 * `spayedNeutered`, `specialNeeds`, `lastVetVisit`, `vetName`, `vetPhone`).
 * The `health` **group** is `present|array` too — never null, because its
 * scalar leaves are `present` and no payload could satisfy both.
 *
 * **Collection keys carry no `present` and may be omitted entirely**:
 * `traits`, `additionalInfo`, `health.vaccinations`, `health.medications`,
 * `health.allergies` and `location.coordinates`. Inertia's FormData serialiser
 * appends nothing at all for `[]` or `{}`, so on any multipart save those keys
 * simply vanish; `present` on them would 422 every listing with an empty
 * repeater or no map pin. Sending `null` is equally fine — for a collection,
 * absent and empty mean the same thing. See .ai/rules/js.md.
 *
 * A repeater **row**, once present, must carry all of its leaves:
 * `vaccinations.*.name` / `.date` and `medications.*.name` / `.usage` are each
 * `present|nullable`, so a half-built row is a 422 rather than a partial write.
 *
 * `featuredImage`, `images` and `deletedMediaIds` are not `present` either —
 * the media steps own them, they never reach the attribute bag, and omitting
 * them leaves the existing photos alone. Note the asymmetry with the read
 * shape: photos come back as `pet.photos`, but uploads go out as `images`
 * (File objects), so the read payload cannot be echoed back as-is. They are
 * absent here because this page does not upload anything yet.
 */
type PetUpdatePayload = {
    name: string;
    /** `required`, so null is a 422 — the real form must force a choice. */
    category_id: number | null;
    /** `present|nullable`: send null explicitly when the listing has no breed. */
    breed_id: number | null;
    age: string;
    gender: PetGender;
    color: string;
    weight: number | null;
    description: string;
    listing_type: PetListingType;
    price: number | null;
    status: PetStatus;
    location: {
        address: string | null;
        detailedAddress: string | null;
        city: string;
        state: string;
        postalCode: string | null;
        country: string;
        /** Not `present` — a listing with no map pin may omit this. */
        coordinates?: { lat: number | null; lng: number | null } | null;
    };
    /** `present|array`: the group is always sent, and is never null. */
    health: {
        status: PetHealthStatus | null;
        vaccinated: boolean | null;
        spayedNeutered: boolean | null;
        specialNeeds: string | null;
        lastVetVisit: string | null;
        /** Rows must be complete: `name` and `date` are both `present`. */
        vaccinations?: PetVaccination[] | null;
        /** Rows must be complete: `name` and `usage` are both `present`. */
        medications?: PetMedication[] | null;
        allergies?: string[] | null;
        vetName: string | null;
        vetPhone: string | null;
    };
    traits?: string[] | null;
    additionalInfo?: Record<string, string> | null;
};

/**
 * Round-tripping PetDetailResource satisfies the contract, with one translation:
 * the resource emits `category`/`breed` as objects while the form posts
 * `category_id`/`breed_id`. The owner-only leaves are absent rather than null
 * for a non-owner, so every one of them is coalesced to null here.
 */
const payload = computed<PetUpdatePayload>(() => ({
    name: props.pet.name,
    category_id: props.pet.category?.id ?? null,
    breed_id: props.pet.breed?.id ?? null,
    age: props.pet.age,
    gender: props.pet.gender,
    color: props.pet.color,
    weight: props.pet.weight,
    description: props.pet.description,
    listing_type: props.pet.listing_type,
    price: props.pet.price,
    status: props.pet.status,
    location: {
        address: props.pet.location.address ?? null,
        detailedAddress: props.pet.location.detailedAddress ?? null,
        city: props.pet.location.city,
        state: props.pet.location.state,
        postalCode: props.pet.location.postalCode ?? null,
        country: props.pet.location.country,
        coordinates: props.pet.location.coordinates ?? null,
    },
    health: {
        status: props.pet.health.status,
        vaccinated: props.pet.health.vaccinated,
        spayedNeutered: props.pet.health.spayedNeutered,
        specialNeeds: props.pet.health.specialNeeds ?? null,
        lastVetVisit: props.pet.health.lastVetVisit ?? null,
        vaccinations: props.pet.health.vaccinations ?? null,
        medications: props.pet.health.medications ?? null,
        allergies: props.pet.health.allergies ?? null,
        vetName: props.pet.health.vetName ?? null,
        vetPhone: props.pet.health.vetPhone ?? null,
    },
    traits: props.pet.traits ?? null,
    additionalInfo: props.pet.additionalInfo ?? null,
}));
</script>

<template>
    <div class="mx-auto w-full max-w-3xl space-y-6 p-6">
        <Head :title="`Edit ${props.pet.name}`" />

        <header class="space-y-1">
            <h1 class="text-2xl font-semibold">Edit {{ pet.name }}</h1>
            <p class="text-muted-foreground text-sm">
                Placeholder form page — the real form lands in Phase 4.
            </p>
            <Link :href="show(pet.id)" class="text-sm underline">
                Back to listing
            </Link>
        </header>

        <!--
            The read shape. See PetUpdatePayload above for what a save has to
            post back — every scalar it lists is `present`, so omitting one is
            a 422 rather than a silent write, while the collection keys may be
            left out entirely.
        -->
        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
            <dt>Listing type</dt>
            <dd>{{ pet.listing_type }}</dd>
            <dt>Status</dt>
            <dd>{{ pet.status }}</dd>
            <dt>Gender</dt>
            <dd>{{ pet.gender }}</dd>
            <dt>Category</dt>
            <dd>{{ pet.category?.name ?? '—' }}</dd>
            <dt>Breed</dt>
            <dd>{{ pet.breed?.name ?? '—' }}</dd>
            <dt>Postal code</dt>
            <dd>{{ pet.location.postalCode ?? '—' }}</dd>
            <dt>Address</dt>
            <dd>{{ pet.location.address ?? '—' }}</dd>
            <dt>Detailed address</dt>
            <dd>{{ pet.location.detailedAddress ?? '—' }}</dd>
            <dt>Health status</dt>
            <dd>{{ pet.health.status }}</dd>
            <dt>Special needs</dt>
            <dd>{{ pet.health.specialNeeds ?? '—' }}</dd>
            <dt>Last vet visit</dt>
            <dd>{{ pet.health.lastVetVisit ?? '—' }}</dd>
            <dt>Vet</dt>
            <dd>{{ pet.health.vetName ?? '—' }}</dd>
            <dt>Additional info</dt>
            <dd>{{ Object.keys(pet.additionalInfo ?? {}).length }} entries</dd>
            <dt>Existing photos</dt>
            <dd>{{ pet.photos?.length ?? 0 }}</dd>
        </dl>

        <section class="space-y-1 text-sm">
            <h2 class="font-medium">Options</h2>
            <p>Categories: {{ categories.length }}</p>
            <p>
                Listing types: {{ listingTypes.map((o) => o.label).join(', ') }}
            </p>
            <p>Statuses: {{ statuses.map((o) => o.label).join(', ') }}</p>
            <p>Genders: {{ genders.map((o) => o.label).join(', ') }}</p>
            <p>
                Health statuses:
                {{ healthStatuses.map((o) => o.label).join(', ') }}
            </p>
        </section>

        <section class="space-y-1 text-sm">
            <h2 class="font-medium">Update payload</h2>
            <p class="text-muted-foreground">
                The exact bag a save must post — the Phase 4 form seeds itself
                from this shape, null-valued where a field is empty.
            </p>
            <pre class="bg-muted overflow-x-auto rounded p-3 text-xs">{{
                payload
            }}</pre>
        </section>
    </div>
</template>
