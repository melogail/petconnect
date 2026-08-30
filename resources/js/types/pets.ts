export type PetListingType = 'adoption' | 'sale' | 'mating';
export type PetStatus = 'available' | 'unavailable';

export type SelectOption = {
    value: string;
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

export type PetComment = {
    id: number;
    content: string;
    parent_id: number | null;
    user?: PetOwner;
    has_reported: boolean;
    replies?: PetComment[];
    created_at: string;
};

/** A listing as the home feed renders it. Public payload. */
export type PetCard = {
    id: number;
    name: string;
    age: number | null;
    gender: string | null;
    color: string | null;
    description: string | null;
    status: PetStatus;
    listing_type: PetListingType;
    price: number | null;
    vaccinated: boolean;
    spayed_neutered: boolean;
    city: string | null;
    state: string | null;
    country: string | null;
    category?: PetCategoryOption;
    breed?: PetBreedOption;
    user?: PetOwner;
    is_owner: boolean;
    image: string | null;
    likes_count: number;
    comments_count: number;
    is_liked: boolean;
    comments?: PetComment[];
    /** Only present when the feed query ran with a distance calculation. */
    distance?: number;
    created_at: string;
};

/**
 * A listing as its own page renders it.
 *
 * The owner-only leaves are absent — not null — for a viewer who cannot update
 * the listing, so every one of them is optional here.
 */
export type PetDetail = {
    id: number;
    name: string;
    age: number | null;
    gender: string | null;
    color: string | null;
    weight: number | null;
    description: string | null;
    listing_type: PetListingType;
    price: number | null;
    status: PetStatus;
    views: number;
    category?: PetCategoryOption;
    breed?: PetBreedOption;
    user?: PetOwner;
    is_owner: boolean;
    location: {
        city: string | null;
        state: string | null;
        country: string | null;
        postal_code: string | null;
        address?: string | null;
        detailed_address?: string | null;
        coordinates?: { lat: number | null; lng: number | null };
    };
    health: {
        status: string | null;
        vaccinated: boolean;
        spayed_neutered: boolean;
        special_needs: boolean;
        last_vet_visit: string | null;
        vaccinations: string[] | null;
        medications?: string[] | null;
        allergies?: string[] | null;
        vet_name?: string | null;
        vet_phone?: string | null;
    };
    traits: string[] | null;
    additional_info: string | null;
    featured_image: string | null;
    images?: PetMedia[];
    likes_count: number;
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
