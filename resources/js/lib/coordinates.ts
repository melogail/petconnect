import type { Coordinate } from '@/types/profile';

/**
 * Coerce a coordinate to the string a text input binds to.
 *
 * `lat` / `lng` arrive as `number | string | null` — they are uncast `decimal`
 * columns, so PDO hands back a float on SQLite and a string on MySQL. This is
 * the one place that difference is resolved; everything downstream works with
 * the string.
 */
export function toCoordinateInput(value: Coordinate): string {
    return value === null || value === '' ? '' : String(value);
}

/**
 * Coerce an input's value back to what `numeric` validation expects, or null
 * when the field was left blank.
 */
export function fromCoordinateInput(value: string): number | null {
    const trimmed = value.trim();

    if (trimmed === '') {
        return null;
    }

    const parsed = Number(trimmed);

    return Number.isFinite(parsed) ? parsed : null;
}

/**
 * A coordinate as a number, or null when there is none.
 *
 * The read payloads hand `lat` / `lng` over as `number | string | null`; a map
 * marker and a distance calculation both want a number. Routed through the two
 * functions above so the coercion still happens in exactly one place.
 */
export function toCoordinateNumber(value: Coordinate): number | null {
    return fromCoordinateInput(toCoordinateInput(value));
}
