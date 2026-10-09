<script setup lang="ts">
import { ChevronUp } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

/**
 * The listing's own words, with legacy's "Read more" reveal
 * (`components/web/PetCard.vue`, the "Enhanced description" block; restyled
 * on the user's instruction, 2026-09-06).
 *
 * Collapsed, the paragraph is **exactly** three lines tall — a fixed height
 * and not a maximum, `h-[calc(3*1.55*13px)]` over the guide's 13px/1.55
 * description scale (§3.2, §5.2; it was `h-[4.5rem]` over `text-sm
 * leading-relaxed`) — under a fade in the card's own colour (`from-card`,
 * which re-tones with the scheme), with the button sitting on the fade. The
 * text is `--soft-foreground`, the guide's reading step one below
 * `--foreground` (8.3:1 on `--card` light, per its token table). Fixed rather
 * than
 * clamped, because the user asked for cards of one size (2026-09-06): a
 * one-line description used to make its card shorter than its neighbours and
 * pull the buttons up. Now a short description simply leaves blank lines below
 * it, and every card's buttons sit on the same baseline. The reveal is offered
 * only past `EXPAND_THRESHOLD` characters — legacy's 120 — so a short
 * description carries no dead control. Expanding is per card and local:
 * nothing about it survives a visit, and it is the one thing on the card that
 * changes its height, at the reader's own request.
 *
 * `description` is a non-nullable column, but it can be an empty string, and
 * nothing upstream filters for that: `PetListingCard` mounts this with no
 * `v-if`, and both of its consumers — `PetFeed.vue` and
 * `profile/ProfileListings.vue` — go through that one call site. Legacy
 * rendered `pets.no_description` in that case and so does this, inside the
 * same three-line box.
 *
 * Both buttons' accessible names **extend** their visible text with the pet's
 * name, so a feed of twenty "Read more" controls announces twenty different
 * things while speech input still matches the words on screen.
 */
const { description, name } = defineProps<{
    description: string;
    name: string;
}>();

const { t } = useTranslations();

/** Legacy's threshold, in characters, below which the reveal is not offered. */
const EXPAND_THRESHOLD = 120;

const expanded = ref(false);

const expandable = computed(() => description.length > EXPAND_THRESHOLD);

const text = computed(() => description || t('pets.no_description'));
</script>

<template>
    <div>
        <div class="relative overflow-hidden">
            <p
                class="text-soft-foreground text-[13px] leading-[1.55] break-words transition-[max-height] duration-500 ease-in-out"
                :class="
                    expanded
                        ? 'max-h-[500px]'
                        : 'h-[calc(3*1.55*13px)] overflow-hidden'
                "
            >
                {{ text }}
            </p>

            <div
                v-if="expandable && !expanded"
                class="from-card absolute inset-x-0 bottom-0 flex h-10 items-end justify-center bg-linear-to-t to-transparent pb-1"
            >
                <button
                    type="button"
                    :aria-label="`${t('pets.read_more')}: ${name}`"
                    class="bg-card text-primary border-border hover:bg-accent hover:text-accent-foreground focus-visible:ring-ring focus-visible:ring-offset-background rounded-full border px-2 py-0.5 text-[11px] font-semibold transition-[background-color,border-color,box-shadow,transform] duration-200 ease-out focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                    @click="expanded = true"
                >
                    {{ t('pets.read_more') }}
                </button>
            </div>
        </div>

        <button
            v-if="expandable && expanded"
            type="button"
            :aria-label="`${t('pets.show_less')}: ${name}`"
            class="text-primary focus-visible:ring-ring focus-visible:ring-offset-background mx-auto mt-2 flex items-center rounded-sm text-xs font-medium transition-colors hover:underline focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
            @click="expanded = false"
        >
            {{ t('pets.show_less') }}
            <ChevronUp class="ms-1 size-3" aria-hidden="true" />
        </button>
    </div>
</template>
