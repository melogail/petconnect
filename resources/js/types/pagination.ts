export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/**
 * An Inertia page of an API resource collection.
 */
export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
};
