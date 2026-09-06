/**
 * One numbered page button — `«  Previous`, `1`, `…`, `Next »`.
 *
 * These live under **`meta.links`**, not under the top-level `links`. See
 * `Paginated` below; the distinction has bitten this codebase once already.
 */
export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/**
 * The top-level `links` key of a paginated resource collection: four URLs, not
 * a list of pages.
 *
 * `Illuminate\Http\Resources\Json\PaginatedResourceResponse::paginationLinks()`
 * builds exactly this, and `meta()` is everything else the paginator's
 * `toArray()` had — which is where the *numbered* links end up.
 */
export type PaginationCursors = {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
};

/**
 * The `meta` envelope a paginated resource collection carries.
 *
 * Split out of `Paginated` so an endpoint that adds a key of its own —
 * `notifications.index` puts `unread_count` here through `additional()` — can
 * intersect this rather than restate the eight.
 */
export type PaginationMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    /** The numbered page buttons. `Pagination` wants **this**, not `links`. */
    links: PaginationLink[];
    path: string;
    per_page: number;
    to: number | null;
    total: number;
};

/**
 * A page of an API resource collection, as `{data, links, meta}`.
 *
 * ## `links` is not the page buttons, and this type used to say it was
 *
 * `PaginatedResourceResponse` splits the paginator's `toArray()` in two: the
 * four navigation URLs (`first`, `last`, `prev`, `next`) become the top-level
 * `links` **object**, and everything else — including the numbered `links`
 * *array* — becomes `meta`. So a paginated collection has two things called
 * `links` and they are different shapes.
 *
 * This type declared `links: PaginationLink[]`, which was the array's shape
 * attached to the object's key, and three components passed it straight into
 * `Pagination`: `ProfileListings`, `ProfileReviews` and `ConversationList`.
 * `Pagination` opens with `links.filter(...)`, and an object has no `filter`,
 * so each of those threw a TypeError during render — `profile.show` and
 * `conversations.index` both, on every visit, not only past page one. Nothing
 * caught it: `vue-tsc` was being told the wrong shape, and no test asserts on
 * the key.
 *
 * A plain `LengthAwarePaginator` passed to `Inertia::render()` *does* put the
 * numbered array at the top level, which is probably where the mistake came
 * from. Everything in this application is a resource collection.
 */
export type Paginated<T> = {
    data: T[];
    /** Four navigation URLs. The page buttons are `meta.links`. */
    links: PaginationCursors;
    meta: PaginationMeta;
};
