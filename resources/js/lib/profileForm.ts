import type { ProfileFormData } from '@/types';

/**
 * A blank text input and a null column mean the same thing here, because
 * `ConvertEmptyStringsToNull` rewrites `''` to `null` before validation. Both
 * are folded together so that "I never touched this empty field" does not read
 * as a change.
 */
function normalize(value: unknown): unknown {
    return value === '' || value === undefined ? null : value;
}

function isSame(a: unknown, b: unknown): boolean {
    const left = normalize(a);
    const right = normalize(b);

    if (left instanceof File || right instanceof File) {
        return false;
    }

    return left === right;
}

/**
 * The keys the user actually edited.
 *
 * `profile.update` is a PATCH and
 * `Pipelines\Profiles\UpdateProfile\PersistProfileAttributes` fills only what
 * the request sent, so posting the whole bag would write every field the panel
 * happens to render — including nulls for the ones it renders empty. Sending
 * the difference is what keeps one panel from wiping another panel's columns.
 *
 * `groups` names keys that have to travel together. `lat` and `lng` carry
 * `required_with` in both directions, so sending one alone is a 422; changing
 * either sends both.
 */
export function changedProfileFields<T extends Record<string, unknown>>(
    current: T,
    original: T,
    groups: (keyof T & string)[][] = [],
): Record<string, unknown> {
    const changed: Record<string, unknown> = {};

    for (const key of Object.keys(current)) {
        if (!isSame(current[key], original[key])) {
            changed[key] = current[key];
        }
    }

    for (const group of groups) {
        if (group.some((key) => key in changed)) {
            for (const key of group) {
                changed[key] = current[key];
            }
        }
    }

    return changed;
}

/**
 * The identity pair every save has to carry.
 *
 * `name` and `email` are `required` in `ProfileValidationRules::profileRules()`
 * — they are not `sometimes` like the rest of the form — so a PATCH that omits
 * them 422s even when it only meant to change a phone number. Every panel
 * therefore posts them at their current values.
 */
export function profileIdentity(profile: ProfileFormData): {
    name: string;
    email: string;
} {
    return { name: profile.name, email: profile.email };
}
