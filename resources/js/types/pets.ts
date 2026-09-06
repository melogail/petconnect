import type { Comment, CommentPreview } from './comments';
import type { Coordinate, DecimalColumn } from './profile';

export type PetListingType = 'adoption' | 'sale' | 'mating';
export type PetStatus = 'available' | 'unavailable';
export type PetGender = 'male' | 'female';
export type PetHealthStatus = 'healthy' | 'minor_issues' | 'chronic_condition';

/**
 * One `{value, label}` pair from a backing enum's `options()`.
 *
 * Parameterise it with the union the enum backs — `SelectOption<PetGender>` —
 * so a select bound to the option list narrows to the same union the payload
 * carries.
 */
export type SelectOption<TValue extends string = string> = {
    value: TValue;
    label: string;
};

/**
 * One breed, as the pet form and the filter sheet list it.
 *
 * `name` is the English source and `name_ar` its Arabic translation, `null`
 * where nobody has filled one in. **Never render either directly** — go through
 * `taxonomyName(breed, locale.current)` in `@/lib/taxonomy`, which picks the
 * column for the reader's language and falls back to `name`. Both names ship on
 * every row precisely so the client can choose without a second round trip; for
 * a while nothing read `name_ar` and every Arabic reader saw English breeds.
 *
 * `slug` is unique per category rather than globally, which is why `id` is what
 * the form submits and what the feed's `breed_ids` carries.
 */
export type PetBreedOption = {
    id: number;
    category_id: number;
    name: string;
    name_ar: string | null;
    slug: string;
};

/**
 * One category, with its breeds nested when the backend eager loaded them.
 *
 * Same naming contract as `PetBreedOption`: render through
 * `taxonomyName(category, locale.current)`, never `category.name`.
 *
 * `slug` is the stable half — the name is editable and localised — so anything
 * keyed on a category (`@/components/pets/filter/categoryIcon`) keys on `slug`.
 */
export type PetCategoryOption = {
    id: number;
    name: string;
    name_ar: string | null;
    slug: string;
    image: string | null;
    /** Only present when the backend eager loaded breeds. */
    breeds?: PetBreedOption[];
};

export type PetOwner = {
    id: number;
    name: string;
    username: string | null;
    location: string | null;
    avatar: string | null;
};

export type PetMedia = {
    id: number;
    name: string;
    url: string;
    thumb: string;
    display: string;
    featured: boolean;
};

/** One clinical record in the health group's vaccination repeater. */
export type PetVaccination = {
    name: string;
    date: string | null;
};

/** One clinical record in the health group's medication repeater. */
export type PetMedication = {
    name: string;
    usage: string | null;
};

/**
 * A listing as the home feed renders it. Public payload.
 *
 * Read shape, not the form's contract: every key here is the snake_case column
 * name. `PetDetail` is the one that mirrors what the pet form posts back.
 */
export type PetCard = {
    id: number;
    name: string;
    /** A varchar column on the backend, so a string even though it reads numeric. */
    age: string;
    gender: PetGender;
    color: string;
    description: string;
    status: PetStatus;
    listing_type: PetListingType;
    /** An uncast `decimal` column: a float on SQLite, a string on MySQL. */
    price: DecimalColumn | null;
    vaccinated: boolean;
    spayed_neutered: boolean;
    city: string;
    state: string;
    country: string;
    category?: PetCategoryOption;
    /** Null when the listing has no breed; absent when it was not loaded. */
    breed?: PetBreedOption | null;
    user?: PetOwner;
    is_owner: boolean;
    image: string | null;
    likes_count: number;
    /** The true total, not the length of the `comments` preview below. */
    comments_count: number;
    is_liked: boolean;
    /** A bounded preview of the newest top-level comments, never their replies. */
    comments?: CommentPreview[];
    /** Only present when the feed query ran with a distance calculation. */
    distance?: number;
    /** Page views, as `Actions\Pets\RecordPetView` counts them. */
    views: number;
    created_at: string;
};

/**
 * A listing as its own page renders it.
 *
 * The nested `location`, `health` and `additionalInfo` groups use camelCase
 * leaves, because those are the names the pet form posts back and the Form
 * Request validates. The top-level scalars stay snake_case — they are column
 * names.
 *
 * Four keys are read shapes rather than write shapes, so prefilling the form
 * from them is not a straight assignment:
 * - `category` / `breed` are objects; the form posts `category_id` / `breed_id`.
 * - `featured_image` is a URL; the form posts `featuredImage`, an upload.
 * - `photos` are the attached media rows you read back. The write side keeps
 *   the name `images`, which means something else entirely: newly uploaded
 *   files. A save posts `images` (new uploads) plus `deletedMediaIds` (ids to
 *   detach), and never posts `photos`. The two were once the same key, so
 *   echoing the read payload straight back 422'd on `images.*` expecting files.
 *
 * The owner-only leaves are absent — not null — for a viewer who cannot update
 * the listing, so every one of them is optional here.
 */
export type PetDetail = {
    id: number;
    name: string;
    /** A varchar column on the backend, so a string even though it reads numeric. */
    age: string;
    gender: PetGender;
    color: string;
    /** An uncast `decimal` column: a float on SQLite, a string on MySQL. */
    weight: DecimalColumn | null;
    description: string;
    listing_type: PetListingType;
    /** An uncast `decimal` column: a float on SQLite, a string on MySQL. */
    price: DecimalColumn | null;
    status: PetStatus;
    views: number;
    category?: PetCategoryOption;
    /** Null when the listing has no breed; absent when it was not loaded. */
    breed?: PetBreedOption | null;
    user?: PetOwner;
    is_owner: boolean;
    location: {
        city: string;
        state: string;
        country: string;
        postalCode: string | null;
        address?: string | null;
        detailedAddress?: string | null;
        /**
         * Uncast `decimal` columns, so each leaf is whatever the driver
         * returned — a float on SQLite, a string on MySQL. Coerce once with
         * `@/lib/coordinates`; a `decimal:8` cast is a settled refusal.
         */
        coordinates?: { lat: Coordinate; lng: Coordinate };
    };
    health: {
        status: PetHealthStatus;
        vaccinated: boolean;
        spayedNeutered: boolean;
        /** Free text, not a flag: the backing column is a `text`. */
        specialNeeds: string | null;
        lastVetVisit: string | null;
        vaccinations: PetVaccination[] | null;
        medications?: PetMedication[] | null;
        allergies?: string[] | null;
        vetName?: string | null;
        vetPhone?: string | null;
    };
    traits: string[] | null;
    /** A free-form label/value map, not a repeater and not a string. */
    additionalInfo: Record<string, string> | null;
    featured_image: string | null;
    /** Read-side media rows. The write side calls its file uploads `images`. */
    photos?: PetMedia[];
    likes_count: number;
    /** The true total, not the length of the bounded `comments` thread below. */
    comments_count: number;
    /**
     * Top-level comments only — a different number from `comments_count`, which
     * counts replies too.
     *
     * `comments.index` pages roots, so this is the one `CommentThread` compares
     * the roots it holds against. Comparing against `comments_count` instead is
     * what lit "load more" up on any thread with a single reply. The two are
     * equal only where nobody has replied to anything.
     */
    root_comments_count: number;
    is_liked: boolean;
    comments?: Comment[];
    created_at: string;
    updated_at: string;
};

/** The normalised filter bag the home feed echoes back. */
export type HomeFeedFilters = {
    category_ids: number[];
    breed_ids: number[];
    age_min: number | null;
    age_max: number | null;
    listing_types: string[];
    vaccinated: boolean | null;
};

export type HomeFeedBounds = {
    default_radius_km: number;
    min_radius_km: number;
    max_radius_km: number;
    max_age_years: number;
    default_age_min: number;
    default_age_max: number;
};

/**
 * The comment ceiling — the `commentBounds` prop on `pets.show`.
 *
 * Built by `App\Concerns\CommentValidationRules::commentBounds()` from the same
 * accessor the `max:` rule is built from, so the counter under the composer
 * and the validator cannot disagree.
 */
export type CommentBounds = {
    max_length: number;
    /**
     * `petconnect.comments.thread_per_page` — the page size `comments.index`
     * answers with, which is not the size of the slice the page shipped.
     *
     * `CommentThread` needs it to know where the shipped roots stop and the
     * endpoint's next page begins: the first page worth asking for is
     * `floor(rootsInHand / thread_per_page) + 1`. The two sizes are independent
     * env vars, so neither can be inferred from the other.
     */
    thread_per_page: number;
};

/**
 * The message ceiling — the `messageBounds` prop on `conversations.show`.
 */
export type MessageBounds = {
    max_length: number;
};

/**
 * The five option lists both pet form pages carry.
 *
 * `categories` arrives with its `breeds` eager loaded, so the breed select is
 * a filter over the chosen category rather than a second request.
 */
export type PetFormOptions = {
    categories: PetCategoryOption[];
    listingTypes: SelectOption<PetListingType>[];
    statuses: SelectOption<PetStatus>[];
    genders: SelectOption<PetGender>[];
    healthStatuses: SelectOption<PetHealthStatus>[];
};

/**
 * The upload ceilings the photo step enforces client-side.
 *
 * Shipped as the `photoBounds` prop by `Web\PetController::create` and
 * `::edit`, built by `App\Concerns\PetPhotoRules::photoBounds()` from the same
 * accessors the `max:` validation rules are built from. So the gallery cap, the
 * size the picker compresses down to and the validator cannot disagree — never
 * hardcode these numbers in a page.
 */
export type PetPhotoBounds = {
    max_gallery_images: number;
    max_image_kilobytes: number;
};
