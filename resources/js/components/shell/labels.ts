/**
 * Accessible-name helpers for the header's badged controls.
 *
 * The sibling of `components/pets/card/labels.ts`, and here for the same
 * reason: a naming rule that two components have to agree on lives in one
 * place, not in two template expressions that can drift.
 */

/**
 * What `UnreadBadge` puts on screen for a given count.
 *
 * `9+` above nine is legacy's ceiling and the badge's own geometry is checked
 * against two glyphs (see that component). It is exported because the number a
 * control **shows** and the number its accessible name **states** are two
 * different strings above nine, and WCAG 2.5.3 is about the first — so the
 * trigger has to be able to ask what the badge is currently rendering rather
 * than re-deriving the cap and drifting from it.
 */
export function unreadBadgeLabel(count: number): string {
    return count > 9 ? '9+' : String(count);
}

/**
 * A name that is guaranteed to contain the control's visible text.
 *
 * WCAG 2.5.3 (Label in Name): speech input matches the words a user reads off
 * the screen, so an accessible name that drops them stops the control being
 * addressable by voice — silently, with nothing visibly wrong.
 *
 * **The containment is checked here rather than assumed of the catalogue**, and
 * that is the whole point of the function. A name built by interpolating the
 * same number the badge shows only *happens* to contain it; this one contains
 * it by construction, in every locale, for every count, and it stays true when
 * a translator rewrites the sentence. Two live cases prove the assumption
 * cannot be relied on:
 *
 * - `notifications.unread_many` is ":count unread notifications", so at 89 the
 *   sentence says "89" while the badge says "9+" — containment fails on the
 *   cap, in both catalogues.
 * - `notifications.unread_one` is ":count unread notification" in `en.json` but
 *   "إشعار غير مقروء" in `ar.json`, with **no `:count` placeholder at all**, so
 *   at a count of 1 the Arabic sentence contains no digit and the English one
 *   does. A per-locale property, broken by a translator, invisible to
 *   `types:check`.
 *
 * Case-insensitive, because the criterion is: "EN" is the visible text of the
 * language pill whose name is "English", and that pair is a pass.
 */
export function nameContaining(visible: string, name: string): string {
    return name.toLowerCase().includes(visible.toLowerCase())
        ? name
        : `${visible}, ${name}`;
}
