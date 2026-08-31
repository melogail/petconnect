import { fromCoordinateInput, toCoordinateInput } from '@/lib/coordinates';
import type {
    PetDetail,
    PetGender,
    PetHealthStatus,
    PetListingType,
    PetStatus,
} from '@/types';

/** One row of the vaccination repeater, as the inputs bind to it. */
export type PetVaccinationRow = {
    name: string;
    date: string;
};

/** One row of the medication repeater, as the inputs bind to it. */
export type PetMedicationRow = {
    name: string;
    usage: string;
};

/**
 * One row of the extras editor.
 *
 * The wire shape is a **keyed map**, not a repeater — the legacy form posted
 * `[{key, value}]` and then string-matched the keys against hardcoded English
 * labels ("Good with Kids"), which broke the moment a listing was written in
 * Arabic. Rows exist only so the UI can keep order and let a label be retyped;
 * `toPetPayload()` folds them into the map the backend validates.
 */
export type PetExtraRow = {
    label: string;
    value: string;
};

/**
 * Everything the pet form holds, in the shape the inputs bind to.
 *
 * Deliberately **not** the wire shape. Every scalar is a string here because
 * that is what an `<input>` produces, and the repeaters are arrays of complete
 * rows because that is what a repeater renders. `toPetPayload()` is the single
 * boundary where this becomes what `App\Concerns\PetValidationRules` accepts.
 *
 * The naming split is the backend's and is preserved exactly: top-level scalars
 * are snake_case column names, the nested `location` / `health` groups and
 * `additionalInfo` are camelCase, and the media keys are write names
 * (`featuredImage`, `images`, `deletedMediaIds`) rather than the read names the
 * detail payload uses (`featured_image`, `photos`).
 */
export type PetFormState = {
    name: string;
    category_id: number | null;
    breed_id: number | null;
    age: string;
    gender: PetGender | '';
    color: string;
    weight: string;
    description: string;
    listing_type: PetListingType | '';
    price: string;
    status: PetStatus | '';
    location: {
        address: string;
        detailedAddress: string;
        city: string;
        state: string;
        postalCode: string;
        country: string;
        lat: string;
        lng: string;
    };
    health: {
        status: PetHealthStatus | '';
        vaccinated: boolean;
        spayedNeutered: boolean;
        specialNeeds: string;
        lastVetVisit: string;
        vetName: string;
        vetPhone: string;
        vaccinations: PetVaccinationRow[];
        medications: PetMedicationRow[];
        allergies: string[];
    };
    traits: string[];
    additionalInfo: PetExtraRow[];
    featuredImage: File | null;
    images: File[];
    deletedMediaIds: number[];
};

/** A blank listing. `status` defaults to the first option the backend offers. */
export function blankPetForm(defaultStatus: PetStatus | '' = ''): PetFormState {
    return {
        name: '',
        category_id: null,
        breed_id: null,
        age: '',
        gender: '',
        color: '',
        weight: '',
        description: '',
        listing_type: '',
        price: '',
        status: defaultStatus,
        location: {
            address: '',
            detailedAddress: '',
            city: '',
            state: '',
            postalCode: '',
            country: '',
            lat: '',
            lng: '',
        },
        health: {
            status: '',
            vaccinated: false,
            spayedNeutered: false,
            specialNeeds: '',
            lastVetVisit: '',
            vetName: '',
            vetPhone: '',
            vaccinations: [],
            medications: [],
            allergies: [],
        },
        traits: [],
        additionalInfo: [],
        featuredImage: null,
        images: [],
        deletedMediaIds: [],
    };
}

/**
 * Seed the form from what `PetDetailResource` handed over.
 *
 * Four keys are read shapes and are not a straight assignment: `category` and
 * `breed` are objects behind `category_id` / `breed_id`, `featured_image` is a
 * URL behind the `featuredImage` upload, and `photos` are media rows that the
 * write side never echoes back. The owner-only leaves are *absent* rather than
 * null for a non-owner, so each is coalesced.
 */
export function petFormFromDetail(pet: PetDetail): PetFormState {
    return {
        name: pet.name,
        category_id: pet.category?.id ?? null,
        breed_id: pet.breed?.id ?? null,
        age: pet.age ?? '',
        gender: pet.gender,
        color: pet.color ?? '',
        weight: pet.weight === null ? '' : String(pet.weight),
        description: pet.description ?? '',
        listing_type: pet.listing_type,
        price: pet.price === null ? '' : String(pet.price),
        status: pet.status,
        location: {
            address: pet.location.address ?? '',
            detailedAddress: pet.location.detailedAddress ?? '',
            city: pet.location.city ?? '',
            state: pet.location.state ?? '',
            postalCode: pet.location.postalCode ?? '',
            country: pet.location.country ?? '',
            lat: toCoordinateInput(pet.location.coordinates?.lat ?? null),
            lng: toCoordinateInput(pet.location.coordinates?.lng ?? null),
        },
        health: {
            status: pet.health.status,
            vaccinated: pet.health.vaccinated,
            spayedNeutered: pet.health.spayedNeutered,
            specialNeeds: pet.health.specialNeeds ?? '',
            lastVetVisit: pet.health.lastVetVisit ?? '',
            vetName: pet.health.vetName ?? '',
            vetPhone: pet.health.vetPhone ?? '',
            vaccinations: (pet.health.vaccinations ?? []).map((row) => ({
                name: row.name ?? '',
                date: row.date ?? '',
            })),
            medications: (pet.health.medications ?? []).map((row) => ({
                name: row.name ?? '',
                usage: row.usage ?? '',
            })),
            allergies: [...(pet.health.allergies ?? [])],
        },
        traits: [...(pet.traits ?? [])],
        additionalInfo: Object.entries(pet.additionalInfo ?? {}).map(
            ([label, value]) => ({ label, value }),
        ),
        featuredImage: null,
        images: [],
        deletedMediaIds: [],
    };
}

/** A trimmed string, or null when the field was left blank. */
function text(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

/**
 * The two coordinate boxes as the validator should see them.
 *
 * Both blank is an unpinned listing, and that goes out as `null` rather than
 * `{}` — a create always carries a cover photo and is therefore always
 * multipart, where `{}` serialises to nothing at all.
 *
 * Anything else goes out as a pair, **including a half-filled or unparseable
 * one**. `location.coordinates.{lat,lng}` carry `required_with` in both
 * directions and `numeric`, so the server is what says "a pin needs both
 * boxes" and says it on the offending box. Coercing a bad pair to null here
 * instead saved the listing happily with the pin thrown away and nothing on
 * screen to say so. A value that will not parse is passed through as the typed
 * string precisely so `numeric` can reject it.
 */
function coordinatePair(
    latInput: string,
    lngInput: string,
): { lat: number | string | null; lng: number | string | null } | null {
    if (latInput.trim() === '' && lngInput.trim() === '') {
        return null;
    }

    return {
        lat: fromCoordinateInput(latInput) ?? text(latInput),
        lng: fromCoordinateInput(lngInput) ?? text(lngInput),
    };
}

/** A number for `numeric` validation, or null when the field was left blank. */
function numeric(value: string): number | null {
    const trimmed = value.trim();

    if (trimmed === '') {
        return null;
    }

    const parsed = Number(trimmed);

    return Number.isFinite(parsed) ? parsed : null;
}

/**
 * A collection key's value, with the one rule that keeps multipart honest.
 *
 * Inertia's `objectToFormData` appends **nothing at all** for `[]` or `{}`
 * while `null` is appended as `''` and comes back as null through
 * `ConvertEmptyStringsToNull`. Every collection key on this form is therefore
 * sent as `null` when empty rather than as an empty collection: the backend
 * treats absent and empty as the same thing for these keys (none of them
 * carries `present`, precisely because `present` is not expressible over
 * multipart), and null makes the JSON and the multipart transports behave
 * identically instead of differing by six keys. See .ai/rules/js.md.
 */
function collection<T>(rows: T[]): T[] | null {
    return rows.length === 0 ? null : rows;
}

/**
 * What `pets.store` / `pets.update` accept.
 *
 * ## The `present`-versus-omit split, which has bitten before
 *
 * A PUT on a pet is a **full replacement**: the pipeline writes a value for
 * every column the form owns, so a key the request omits is written as null.
 * Every **scalar** key therefore carries `present` in `PetValidationRules`,
 * which allows null and rejects absence — and this function honours that by
 * always emitting all of them, null-valued when empty:
 *
 * - top level: `breed_id`, `weight`, `price`
 * - `location`: `address`, `detailedAddress`, `postalCode`
 * - `health`: `status`, `vaccinated`, `spayedNeutered`, `specialNeeds`,
 *   `lastVetVisit`, `vetName`, `vetPhone`
 *
 * `health` itself is `present|array` and is never null, which is expressible
 * only because its seven scalar leaves are always on the wire.
 *
 * The **collection** keys — `traits`, `additionalInfo`, `health.vaccinations`,
 * `health.medications`, `health.allergies`, `location.coordinates` — carry no
 * `present` and are emitted as `null` when empty. See `collection()` above.
 *
 * A repeater *row*, once present, carries all of its leaves: `name`/`date` and
 * `name`/`usage` are each `present|nullable`, so a half-built row is a 422
 * rather than a partial write. Rows with nothing in the name are dropped here
 * as well as by the pipeline, so a half-typed row does not become a record.
 *
 * ## The media keys are write names
 *
 * `featuredImage` is omitted entirely when no new cover was picked, because on
 * an edit that means "keep the one already attached" — and on a create the
 * `required` rule turns the omission into the right 422. `images` are new
 * uploads only; `photos` from the read payload are never posted back.
 */
export function toPetPayload(state: PetFormState): Record<string, unknown> {
    const coordinates = coordinatePair(state.location.lat, state.location.lng);

    const vaccinations = state.health.vaccinations
        .filter((row) => row.name.trim() !== '')
        .map((row) => ({ name: row.name.trim(), date: text(row.date) }));

    const medications = state.health.medications
        .filter((row) => row.name.trim() !== '')
        .map((row) => ({ name: row.name.trim(), usage: text(row.usage) }));

    const allergies = state.health.allergies
        .map((allergy) => allergy.trim())
        .filter((allergy) => allergy !== '');

    const traits = state.traits
        .map((trait) => trait.trim())
        .filter((trait) => trait !== '');

    const additionalInfo: Record<string, string> = {};

    for (const row of state.additionalInfo) {
        const label = row.label.trim();
        const value = row.value.trim();

        if (label !== '' && value !== '') {
            additionalInfo[label] = value;
        }
    }

    const payload: Record<string, unknown> = {
        name: state.name.trim(),
        category_id: state.category_id,
        breed_id: state.breed_id,
        age: state.age.trim(),
        gender: state.gender,
        color: state.color.trim(),
        weight: numeric(state.weight),
        description: state.description.trim(),
        listing_type: state.listing_type,
        price: numeric(state.price),
        status: state.status,

        location: {
            address: text(state.location.address),
            detailedAddress: text(state.location.detailedAddress),
            city: state.location.city.trim(),
            state: state.location.state.trim(),
            postalCode: text(state.location.postalCode),
            country: state.location.country.trim(),
            coordinates,
        },

        health: {
            status: state.health.status === '' ? null : state.health.status,
            vaccinated: state.health.vaccinated,
            spayedNeutered: state.health.spayedNeutered,
            specialNeeds: text(state.health.specialNeeds),
            lastVetVisit: text(state.health.lastVetVisit),
            vetName: text(state.health.vetName),
            vetPhone: text(state.health.vetPhone),
            vaccinations: collection(vaccinations),
            medications: collection(medications),
            allergies: collection(allergies),
        },

        traits: collection(traits),
        additionalInfo:
            Object.keys(additionalInfo).length === 0 ? null : additionalInfo,

        images: collection(state.images),
        deletedMediaIds: collection(state.deletedMediaIds),
    };

    if (state.featuredImage !== null) {
        payload.featuredImage = state.featuredImage;
    }

    return payload;
}

/**
 * How many more gallery photos may be attached.
 *
 * The backend enforces `attached − deleted + uploaded ≤ cap` over the listing's
 * lifetime, with the cover photo excluded from both sides, so the picker has to
 * count the same way rather than just capping the file input at the cap.
 */
export function remainingGallerySlots(
    cap: number,
    attachedGalleryCount: number,
    deletedCount: number,
    uploadedCount: number,
): number {
    return Math.max(
        0,
        cap - (attachedGalleryCount - deletedCount + uploadedCount),
    );
}

/**
 * `form.errors` as a flat dot-path map.
 *
 * Laravel keys nested errors by path (`location.city`,
 * `health.vaccinations.0.name`) and `FormDataErrors` types them as template
 * literals, which a Vue template cannot index without fighting the compiler.
 * One cast here beats a cast in every step component.
 */
export function petFormErrors(
    errors: object,
): Record<string, string | undefined> {
    return errors as Record<string, string | undefined>;
}
