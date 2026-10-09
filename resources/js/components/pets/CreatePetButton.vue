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
 * button and the publish CTA belongs on Home alone — so no chrome links
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
 * ## The treatment is the `default` button variant, and the label is not pinned
 *
 * This used to carry the legacy violet→fuchsia gradient with a pinned
 * `text-white` label, and a long measurement of why that pair failed AA at
 * rest (white on `violet-500` 4.40:1, on `fuchsia-500` 3.53:1, with the worse
 * stop under the end of the word) — shipped anyway on a phase-3 ruling for
 * exact legacy parity. The "Aubergine & Lavender" restyle
 * (`.claude/PETCONNECT-STYLE-GUIDE.md`, §1 and §5.1) retires every gradient:
 * violet is the single brand colour, and the primary action on a surface is
 * one solid `--primary` button under `shadow-cta`. So this is now a bare
 * `variant="default"`, and that ruling is superseded rather than re-opened.
 *
 * The label is no longer pinned because the variant's `text-primary-foreground`
 * is now correct in both schemes over a background that itself re-tones: white
 * on `#5A3FE0` in light (**6.2:1**), `#15102A` on `#A78BFF` in dark
 * (**9.6:1**), both from the guide's token table. Hover moves one ramp step
 * (`primary-700` light, `primary-500` dark) rather than fading with alpha, so
 * the hover colour is a measured token too. The contrast exception this file
 * used to be — "the one place in the port where the class string wins over the
 * rendered result" — no longer exists; `PetFeed` and `resources/css/app.css`
 * record the standing position ("A contrast failure is a defect, not a design
 * decision"), and this component now agrees with it.
 */
const page = usePage();

const { t } = useTranslations();

/** A signed-in reader who has verified their email. `pets.create` needs both. */
const canPublish = computed(
    () => (page.props.auth.user ?? null)?.email_verified_at != null,
);
</script>

<template>
    <Button v-if="canPublish" as-child variant="default">
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
