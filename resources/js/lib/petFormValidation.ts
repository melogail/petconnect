import { type InputValue, trimmedInput } from '@/lib/inputValue';
import type { PetFormState } from '@/lib/petForm';

/**
 * The top-level keys the basics step owns, in the order they sit on screen.
 *
 * Used both to clear a step's stale messages before it is re-checked and as
 * the key set `validateBasicStep()` may report on, so the wizard can hand the
 * result straight to `form.setError()`.
 */
export const BASIC_STEP_FIELDS = [
    'name',
    'category_id',
    'birth_date',
    'gender',
    'quantity',
    'male_count',
    'female_count',
    'color',
    'weight',
    'listing_type',
    'price',
    'status',
] as const;

export type BasicStepField = (typeof BASIC_STEP_FIELDS)[number];

export type BasicStepErrors = Partial<Record<BasicStepField, string>>;

/** A whole number, or null when the box is blank or holds anything else. */
function integer(value: InputValue): number | null {
    const text = trimmedInput(value);

    return /^-?\d+$/.test(text) ? Number(text) : null;
}

/** A finite number, or null when the box is blank or holds anything else. */
function decimal(value: InputValue): number | null {
    const text = trimmedInput(value);

    if (text === '') {
        return null;
    }

    const parsed = Number(text);

    return Number.isFinite(parsed) ? parsed : null;
}

/** Today as `Y-m-d` in the browser's zone, matching the date input's value. */
function today(): string {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}

/**
 * The basics step, checked on the client before the wizard moves on.
 *
 * The server is still the authority — `App\Concerns\PetValidationRules` runs
 * on every save and this mirrors it rather than replacing it. The point is
 * that the first step holds the fields every other step depends on (the
 * listing type decides whether there is a price, the quantity whether there
 * are sex counts), and a user who walks seven screens only to be bounced back
 * to the first on submit has been badly served. Each message lands on the
 * same key the server would use, so the step highlights it the same way.
 *
 * Only this step is checked ahead of submit. The others are either optional
 * throughout or carry their own inline guards (the photo picker), and a
 * server round trip on save is what settles them.
 */
export function validateBasicStep(state: PetFormState): BasicStepErrors {
    const errors: BasicStepErrors = {};

    if (state.listing_type === '') {
        errors.listing_type = 'Pick a listing type.';
    }

    if (state.status === '') {
        errors.status = 'Pick a status.';
    }

    if (state.category_id === null) {
        errors.category_id = 'Pick a category.';
    }

    if (state.gender === '') {
        errors.gender = 'Pick a gender.';
    }

    const isAdoption = state.listing_type === 'adoption';
    const isSale = state.listing_type === 'sale';
    const quantity = integer(state.quantity);

    if (quantity === null || quantity < 1) {
        errors.quantity = 'Enter a whole number of 1 or more.';
    } else if (quantity > 255) {
        errors.quantity = 'The quantity must not be greater than 255.';
    } else if (quantity > 1 && !isAdoption) {
        errors.quantity =
            'Only an adoption listing can offer more than one animal.';
    }

    const isLitter = isAdoption && quantity !== null && quantity > 1;

    const name = trimmedInput(state.name);

    if (!isLitter && name === '') {
        errors.name = 'Enter a name.';
    } else if (name.length > 255) {
        errors.name = 'The name must not be greater than 255 characters.';
    }

    if (trimmedInput(state.color).length > 255) {
        errors.color = 'The colour must not be greater than 255 characters.';
    }

    const birthDate = trimmedInput(state.birth_date);

    if (
        birthDate !== '' &&
        (!/^\d{4}-\d{2}-\d{2}$/.test(birthDate) ||
            Number.isNaN(Date.parse(birthDate)) ||
            birthDate > today())
    ) {
        errors.birth_date = 'Enter a date no later than today.';
    }

    if (isLitter) {
        const male = integer(state.male_count);
        const female = integer(state.female_count);

        if (male === null || male < 0) {
            errors.male_count = 'Enter a whole number of 0 or more.';
        }

        if (female === null || female < 0) {
            errors.female_count = 'Enter a whole number of 0 or more.';
        }

        if (
            male !== null &&
            female !== null &&
            male >= 0 &&
            female >= 0 &&
            errors.quantity === undefined
        ) {
            if (male + female !== quantity) {
                errors.quantity =
                    'The male and female counts must add up to the quantity.';
            } else if (state.gender === 'male' && female > 0) {
                errors.gender = 'A male listing cannot include females.';
            } else if (state.gender === 'female' && male > 0) {
                errors.gender = 'A female listing cannot include males.';
            } else if (state.gender === 'mixed' && (male < 1 || female < 1)) {
                errors.gender =
                    'A mixed listing needs at least one male and one female.';
            }
        }
    } else if (state.gender === 'mixed') {
        errors.gender = 'A mixed listing must offer more than one animal.';
    }

    if (trimmedInput(state.weight) !== '') {
        const weight = decimal(state.weight);

        if (weight === null || weight < 0) {
            errors.weight = 'Enter a number of 0 or more.';
        }
    }

    if (isSale) {
        if (trimmedInput(state.price) === '') {
            errors.price = 'Enter a price.';
        } else {
            const price = decimal(state.price);

            if (price === null || price < 0) {
                errors.price = 'Enter a number of 0 or more.';
            }
        }
    }

    return errors;
}
