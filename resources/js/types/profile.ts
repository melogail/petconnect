/**
 * An uncast `decimal` column as the backend hands it over.
 *
 * `users.lat` / `users.lng`, `pets.latitude` / `pets.longitude`, `pets.weight`
 * and `pets.price` carry no cast, so the PHP type is whichever the driver
 * returns: a float on SQLite, a string on MySQL. Typing one of these as `number`
 * is a lie that only shows up in production — `price.toFixed(2)` type-checks
 * and then throws on MySQL — so every one of them is typed with this and
 * coerced at a boundary. `Intl.NumberFormat.format()` and a template literal
 * both take the union as it stands; anything arithmetic goes through
 * `@/lib/coordinates` or `Number()`.
 */
export type DecimalColumn = number | string;

/**
 * A latitude or longitude as the backend hands it over, or null when unset.
 *
 * Coerced exactly once, at the boundary — see `toCoordinateInput()` in
 * `@/lib/coordinates`.
 */
export type Coordinate = DecimalColumn | null;

/**
 * A user as their own public page renders them —
 * `App\Http\Resources\Profile\ProfileResource`, unwrapped.
 *
 * Public by construction: `profile.show` is reachable by guests, so there is no
 * email, phone, street address or exact coordinate here. `ProfileFormData` is
 * the private payload and carries all four.
 */
export type ProfileSummary = {
    id: number;
    name: string;
    username: string | null;
    bio: string | null;
    /** The coarse "City, State, Country" accessor. */
    location: string;
    avatar: string | null;
    is_verified: boolean;
    is_self: boolean;
    is_liked: boolean;
    pets_count: number;
    reviews_count: number;
    /** Null when nobody has reviewed this person yet. */
    reviews_avg_rate: number | null;
    can_update: boolean;
    /**
     * The viewer has already reviewed this member.
     *
     * A second review is refused by a unique index and by
     * `SubmitReview\EnsureNotAlreadyReviewed`; this is what lets the page stop
     * offering the form instead of explaining afterwards through
     * `errors.review`. False for a guest, which is the honest answer.
     */
    has_reviewed: boolean;
    created_at: string;
};

/**
 * The account holder's own record as the settings form reads it back —
 * `App\Http\Resources\Profile\ProfileFormResource`, unwrapped. Private payload.
 *
 * Read and write names differ where the shapes differ: `avatar` is a URL and is
 * read-only, while the upload key the form posts is `image`, a file. Posting
 * `avatar` back would 422 against the file rule.
 */
export type ProfileFormData = {
    id: number;
    name: string;
    username: string | null;
    email: string;
    phone: string | null;
    bio: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    lat: Coordinate;
    lng: Coordinate;
    timezone: string | null;
    locale: string;
    /** Read-only URL. The write side is `image`, an upload. */
    avatar: string | null;
    is_verified: boolean;
};

/** The `locale` shared prop, present on every page. */
export type LocaleState = {
    current: string;
    direction: 'ltr' | 'rtl';
    supported: string[];
};
