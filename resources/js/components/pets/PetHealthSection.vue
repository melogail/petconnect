<script setup lang="ts">
import { AlertCircle, Heart, Shield } from '@lucide/vue';
import { computed } from 'vue';
import PetSectionHeading from '@/components/pets/PetSectionHeading.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/lib/datetime';
import type { PetDetail, PetHealthStatus } from '@/types';

/**
 * The health group — legacy's `PetHealthInfo`, in two blocks.
 *
 * "Health & Veterinary" is a divided row list; "Healthcare Details" is three
 * lists with legacy's colour coding (green dot for a vaccination, blue for a
 * medication, amber pills for allergies). The first block always renders,
 * because `health.status` is a non-null enum column and is therefore always one
 * row; every other row and the whole second block drop out when empty.
 *
 * `vaccinations` is public; `medications`, `allergies`, `vetName` and
 * `vetPhone` are **absent — not null — for a viewer who cannot update the
 * listing**, which is why every one of them is read with `??` rather than
 * behind an `is_owner` branch. The payload has already decided who sees what,
 * and a second gate here would be a place for the two to disagree.
 *
 * Legacy accepted a vaccination or a medication as either an object or a bare
 * string and branched on `typeof`. Both are repeaters with a fixed shape here
 * (`PetVaccination`, `PetMedication`), so the branch is gone rather than
 * ported: a string in that array would be a payload bug, and rendering it
 * anyway would hide it.
 *
 * ## This page reads `wizard.*` keys, over an objection that was recorded and
 * then overridden
 *
 * Three of the labels here come from the form's namespace —
 * `wizard.health_status`, `wizard.last_vet_visit`, `wizard.allergies` — and two
 * more do elsewhere on the same page: `wizard.spayed_neutered` in
 * `PetQuickInfo` and `wizard.personality_traits` in `PetAboutSection`. Five in
 * total, and no other client file consumes that namespace (`grep -rn 'wizard\.'
 * resources/js`, 2026-09-06; the pet form is still hardcoded English and
 * consumes none of them).
 *
 * **The argument against doing this was made and lost.** The deleted
 * `PetAttributesCard` declined to translate its own row labels for exactly this
 * reason, calling `wizard.breed` "a key belonging to the pet form's namespace"
 * and concluding that "translating half of this pair would be worse than
 * translating none. Inventing keys is this file's later phase's job, not this
 * change's". That component is gone and the alternative it rejected is what
 * shipped: the public detail page now depends on the form's namespace. The
 * trade accepted in exchange was that these five labels are translated in both
 * catalogues today, where a new key would have been English-only until the i18n
 * pass reached it.
 *
 * **What breaks if the namespace is renamed.** `t()` returns the key itself for
 * a miss, by design, so a rename with no call-site update does not error, does
 * not fail `types:check`, and is covered by no test — it renders the literal
 * strings `wizard.health_status`, `wizard.last_vet_visit`,
 * `wizard.allergies`, `wizard.spayed_neutered` and `wizard.personality_traits`
 * to every visitor, guests included, on the busiest page after the feed. The
 * trap is that the namespace *looks* safe to rename: its 160 keys are the pet
 * form's, the form does not read them, and nothing outside these three
 * components does either.
 *
 * **Do not rename them here.** Whether `wizard.*` splits into a shared
 * namespace is the scheduled i18n pass's call (`.ai/rules/lang.md`), not this
 * vertical's. If it does split, these five call sites move with it.
 */
const { pet } = defineProps<{ pet: PetDetail }>();

const { t } = useTranslations();
const { tag } = useLocale();

/**
 * Exhaustive over `PetHealthStatus`: a fourth status fails to type-check here.
 *
 * The colour beside it in the template is **not** — it is a ternary on
 * `status === 'healthy'`, green against amber, because legacy paints one
 * positive state and one everything-else and there is no third colour to give.
 * A fourth status would take amber silently. The distinction is deliberate, so
 * do not read this map as covering that ternary: this map is the tripwire that
 * brings a reader here, and the ternary is the second thing to fix once it
 * fires.
 */
const statusKey: Record<PetHealthStatus, string> = {
    healthy: 'pets.health_status_healthy',
    minor_issues: 'pets.health_status_minor_issues',
    chronic_condition: 'pets.health_status_chronic_condition',
};

const statusLabel = computed(() => t(statusKey[pet.health.status]));

const lastVetVisit = computed(() =>
    formatDate(pet.health.lastVetVisit, tag.value),
);

const vaccinations = computed(() => pet.health.vaccinations ?? []);
const medications = computed(() => pet.health.medications ?? []);
const allergies = computed(() => pet.health.allergies ?? []);

const hasHealthcare = computed(
    () =>
        vaccinations.value.length > 0 ||
        medications.value.length > 0 ||
        allergies.value.length > 0,
);
</script>

<template>
    <section class="mb-6">
        <div class="mb-6">
            <PetSectionHeading
                :title="t('pets.health_and_veterinary')"
                :icon="Heart"
                level="h2"
            />
            <dl
                class="divide-border/50 border-border/50 bg-muted/10 divide-y overflow-hidden rounded-xl border"
            >
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <dt class="text-muted-foreground text-sm">
                        {{ t('wizard.health_status') }}
                    </dt>
                    <dd
                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                        :class="
                            pet.health.status === 'healthy'
                                ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
                        "
                    >
                        {{ statusLabel }}
                    </dd>
                </div>
                <div
                    v-if="pet.health.specialNeeds"
                    class="flex items-center justify-between gap-3 px-4 py-3"
                >
                    <dt class="text-muted-foreground text-sm">
                        {{ t('pets.special_needs') }}
                    </dt>
                    <dd class="text-end text-sm font-medium break-words">
                        {{ pet.health.specialNeeds }}
                    </dd>
                </div>
                <div
                    v-if="lastVetVisit"
                    class="flex items-center justify-between gap-3 px-4 py-3"
                >
                    <dt class="text-muted-foreground text-sm">
                        {{ t('wizard.last_vet_visit') }}
                    </dt>
                    <dd class="text-sm font-medium">{{ lastVetVisit }}</dd>
                </div>
                <div
                    v-if="pet.health.vetName"
                    class="flex items-center justify-between gap-3 px-4 py-3"
                >
                    <dt class="text-muted-foreground text-sm">
                        {{ t('pets.vet_name') }}
                    </dt>
                    <dd class="text-end text-sm font-medium break-words">
                        {{ pet.health.vetName }}
                    </dd>
                </div>
                <div
                    v-if="pet.health.vetPhone"
                    class="flex items-center justify-between gap-3 px-4 py-3"
                >
                    <dt class="text-muted-foreground text-sm">
                        {{ t('pets.vet_phone') }}
                    </dt>
                    <dd class="text-sm font-medium" dir="ltr">
                        {{ pet.health.vetPhone }}
                    </dd>
                </div>
            </dl>
        </div>

        <div v-if="hasHealthcare">
            <PetSectionHeading
                :title="t('pets.healthcare_details')"
                :icon="Shield"
            />
            <div class="space-y-4">
                <div v-if="vaccinations.length > 0">
                    <p class="text-muted-foreground mb-2 text-sm font-medium">
                        {{ t('pets.vaccinations') }}
                    </p>
                    <ul class="space-y-1.5">
                        <li
                            v-for="(record, index) in vaccinations"
                            :key="`${record.name}-${index}`"
                            class="flex items-center gap-2 text-sm"
                        >
                            <span
                                class="size-1.5 shrink-0 rounded-full bg-green-500"
                                aria-hidden="true"
                            />
                            <span>
                                {{ record.name }}
                                <span
                                    v-if="record.date"
                                    class="text-muted-foreground ms-1"
                                >
                                    ({{ formatDate(record.date, tag) }})
                                </span>
                            </span>
                        </li>
                    </ul>
                </div>

                <div v-if="medications.length > 0">
                    <p class="text-muted-foreground mb-2 text-sm font-medium">
                        {{ t('pets.medications') }}
                    </p>
                    <ul class="space-y-1.5">
                        <li
                            v-for="(record, index) in medications"
                            :key="`${record.name}-${index}`"
                            class="flex items-center gap-2 text-sm"
                        >
                            <span
                                class="size-1.5 shrink-0 rounded-full bg-blue-500"
                                aria-hidden="true"
                            />
                            <span>
                                {{ record.name }}
                                <span
                                    v-if="record.usage"
                                    class="text-muted-foreground ms-1"
                                >
                                    — {{ record.usage }}
                                </span>
                            </span>
                        </li>
                    </ul>
                </div>

                <div v-if="allergies.length > 0">
                    <p
                        class="text-muted-foreground mb-2 flex items-center gap-1.5 text-sm font-medium"
                    >
                        <AlertCircle
                            class="size-4 text-amber-500"
                            aria-hidden="true"
                        />
                        {{ t('wizard.allergies') }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="allergy in allergies"
                            :key="allergy"
                            class="rounded-full bg-amber-500/10 px-3 py-1 text-xs font-medium text-amber-700 dark:text-amber-400"
                        >
                            {{ allergy }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
