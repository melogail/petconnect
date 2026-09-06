import {
    Bird,
    Cat,
    Dog,
    Fish,
    PawPrint,
    Rabbit,
    Rat,
    Turtle,
} from '@lucide/vue';
import type { Component } from 'vue';

/**
 * Slug → icon. `slug` is the stable half of a category (the name is editable
 * and localised), so it is what the mapping keys on.
 *
 * Every slug `database/data/categories.json` seeds has an entry. The legacy
 * filter mapped four — `dogs`, `cats`, `birds` and a `horses` that names no
 * seeded category — and fell back to `Dog` for everything else, so fish,
 * rabbits, reptiles and small pets all drew a dog. The fallback is `PawPrint`
 * here for the same reason `components/pets/card/PetCardMedia.vue` draws it
 * over a listing with no photo: an unknown category should read as "a pet", not
 * as the wrong animal.
 */
const CATEGORY_ICONS: Record<string, Component> = {
    birds: Bird,
    cats: Cat,
    dogs: Dog,
    fish: Fish,
    rabbits: Rabbit,
    reptiles: Turtle,
    'small-pets': Rat,
};

export function categoryIcon(slug: string): Component {
    return CATEGORY_ICONS[slug] ?? PawPrint;
}
