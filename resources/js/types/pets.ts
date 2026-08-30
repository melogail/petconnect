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

export type PetBreedOption = {
    id: number;
    category_id: number;
    name: string;
    name_ar: string | null;
    slug: string;
};

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

type PetCommentFields = {
    id: number;
    content: string;
    parent_id: number | null;
    /** Only present when the backend eager loaded the author. */
    user?: PetOwner;
    has_reported: boolean;
    created_at: string;
};

/**
 * A comment that carries no thread of its own.
 *
 * This is what a feed card's `comments` preview holds — the backend loads no
 * replies for a card at all, so the key is absent rather than empty — and what
 * a reply on the detail page is, replies being one level deep.
 */
export type PetCommentPreview = PetCommentFields;

/**
 * A top-level comment on the detail page, with a bounded preview of its replies.
 *
 * Both the thread and each reply list are capped by the backend
 * (`petconnect.pets.detail_comment_page_size` / `detail_reply_preview`);
 * `comments_count` on the pet carries the true total.
 */
export type PetComment = PetCommentFields & {
    replies?: PetCommentPreview[];
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
    price: number | null;
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
    comments?: PetCommentPreview[];
    /** Only present when the feed query ran with a distance calculation. */
    distance?: number;
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
    weight: number | null;
    description: string;
    listing_type: PetListingType;
    price: number | null;
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
        coordinates?: { lat: number | null; lng: number | null };
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
    is_liked: boolean;
    comments?: PetComment[];
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
