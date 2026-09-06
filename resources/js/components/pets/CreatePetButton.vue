<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { create as createPet } from '@/routes/pets';

/**
 * "Create Post" — the feed's own publish control, ported from the legacy Home.
 *
 * ## It is the only publish control in the application's chrome
 *
 * `PublicHeader` used to render a second one. It was removed in phase 3 on the
 * user's ruling for exact legacy parity — the legacy navbar had no publish
 * button and the violet→fuchsia CTA belongs on Home alone — so no chrome links
 * `pets.create`; that consequence was accepted knowingly, see the docblock on
 * `components/PublicHeader.vue`. It is mounted on two pages: `Home`, and since
 * 2026-09-06 the owner's own `profile/Show`, in the heading above the listings
 * table, where legacy's `ProfilePetsTable` had its "Create New Pet Post"
 * link. The only other links to `pets.create` anywhere are two on
 * `pages/Help.vue`.
 *
 * ## The gate is verification, not merely a session
 *
 * `pets.create` is declared inside the `Route::middleware(['auth',
 * 'verified'])` group in `routes/web.php`, so an unverified account following
 * this link is bounced to the verification notice rather than reaching the
 * form. `email_verified_at` is therefore the correct predicate, and it is what
 * the legacy page used. `auth.user` is null for a guest whatever
 * `types/auth.ts` says, hence the `?? null` before the property read.
 *
 * The gate is derived here rather than passed in because `Home.vue` receives
 * no auth props at all — the same reasoning `PetListingCard` records for
 * `canInteract`.
 *
 * ## Why the label is pinned to `text-white`
 *
 * The background is a fixed pair of colour-scale stops, so it does not change
 * between light and dark; the default button variant's `text-primary-foreground`
 * does — `hsl(0 0% 100%)` light, `hsl(0 0% 9%)` dark (`resources/css/app.css`).
 * Pinning the label keeps one legible colour over one background.
 *
 * White is also the better of the two. Contrast computed from Tailwind v4's
 * own token values (`node_modules/tailwindcss/theme.css`), converting the
 * `oklch()` stops to linear sRGB and applying the WCAG relative-luminance
 * formula — each number names the pair it was measured against:
 *
 * - rest, white on `violet-500` **4.40:1**, on `fuchsia-500` **3.53:1**
 * - rest, `hsl(0 0% 9%)` on the same two: 4.07:1 and 5.07:1
 * - hover, white on `violet-600` **5.88:1**, on `fuchsia-600` **4.66:1**
 *
 * Two things the numbers depend on, so that a re-measurement that disagrees
 * knows where to look. **`violet-500` and `fuchsia-600` are outside the sRGB
 * gamut** at those oklch coordinates; the figures above clip the converted
 * channels to [0, 1] (giving `#8E51FF` and `#C800DE`), and a different
 * out-of-gamut strategy — chroma reduction, or a wide-gamut display that does
 * not clip at all — lands on different colours and therefore different ratios.
 * And **the gradient runs left→right**, `from-violet-500 to-fuchsia-500`, so
 * the label's *final* characters sit over the 3.53:1 end. The worse stop is
 * under the end of the word, not at some edge nobody reads.
 *
 * So the rest state is below the 4.5:1 AA floor for this text (14px/500)
 * across both ends of the gradient whichever label colour is used, while the
 * hover stops clear it with white.
 *
 * **This ships anyway, and that is a ruling, not an omission.** The user
 * decided in phase 3 to carry the legacy gradient over exactly as it is:
 * contrast failure accepted, recorded here, not fixed. It is the one place in
 * the port where the class string wins over the rendered result, and it is
 * deliberately narrow — `PetFeed` records the opposite call for its two greys,
 * and `resources/css/app.css` records the opposite call for `--destructive`
 * ("A contrast failure is a defect, not a design decision"). Do not generalise
 * from this component; do not re-open it either.
 */
const page = usePage();

const { t } = useTranslations();

/** A signed-in reader who has verified their email. `pets.create` needs both. */
const canPublish = computed(
    () => (page.props.auth.user ?? null)?.email_verified_at != null,
);
</script>

<template>
    <Button
        v-if="canPublish"
        as-child
        class="bg-linear-to-r from-violet-500 to-fuchsia-500 text-white hover:from-violet-600 hover:to-fuchsia-600"
    >
        <Link :href="createPet()">
            <!--
              20px, as legacy's `h-5 w-5` was. The variant only supplies its
              own 16px when the icon carries no `size-` class at all
              (`[&_svg:not([class*='size-'])]:size-4`), so this replaces it
              rather than fighting it.
            -->
            <Plus class="size-5" />
            {{ t('home.create_post') }}
        </Link>
    </Button>
</template>
