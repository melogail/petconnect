<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Camera,
    CircleAlert,
    ClipboardCheck,
    Heart,
    Info,
    MapPin,
    PawPrint,
    Smile,
    Stethoscope,
} from '@lucide/vue';
import type { AsyncComponentLoader, Component } from 'vue';
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import PetFormStepper from '@/components/pets/form/PetFormStepper.vue';
import type { WizardStep } from '@/components/pets/form/PetFormStepper.vue';
import PetFormStepSkeleton from '@/components/pets/form/PetFormStepSkeleton.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { petFormErrors, type PetFormState } from '@/lib/petForm';
import { BASIC_STEP_FIELDS, validateBasicStep } from '@/lib/petFormValidation';
import type { PetFormOptions, PetMedia, PetPhotoBounds } from '@/types';

/**
 * The pet form, in eight steps, shared by create and edit.
 *
 * It is **one form and one request**, not eight: the whole listing is posted at
 * once, because `pets.update` is a full replacement and every scalar key is
 * `present`. The steps are pagination over a long form, so navigation is free
 * backwards and the server validates once, on submit.
 *
 * Moving **forward** off the basics step is the one exception: it runs
 * `validateBasicStep()` first and stays put with the messages on screen if
 * anything fails. That step holds the fields every later one hangs off — the
 * listing type decides whether there is a price, the quantity whether there
 * are sex counts — and being bounced back to it from the review step is the
 * worst outcome the form has. The check is a client mirror of the server
 * rules, not a replacement; the same keys light up either way. Both the Next
 * button and a forward jump on the stepper go through `goTo()`, so neither
 * can skip it.
 *
 * A rejected save still re-opens the earliest step that owns a bad field: the
 * message would otherwise land on a screen the user is not looking at.
 * `STEP_KEYS` is the mapping, matched by prefix so `health.vaccinations.0`
 * lands on the healthcare step.
 *
 * The wizard owns navigation and nothing else. The form object belongs to the
 * page, which is what knows whether it is posting to `pets.store` or to
 * `pets.update`.
 */
const { form, submitLabel } = defineProps<{
    form: InertiaForm<PetFormState>;
    options: PetFormOptions;
    photoBounds: PetPhotoBounds;
    submitLabel: string;
    /** Attached media rows, on an edit. */
    photos?: PetMedia[];
    /** The cover photo already attached, on an edit. */
    currentFeaturedUrl?: string | null;
}>();

const emit = defineEmits<{ submit: [] }>();

/**
 * One step is on screen at a time, so all eight are fetched on demand.
 *
 * Statically imported, the eight steps built one 32.5 kB gzip chunk that every
 * visit to create *and* edit paid for up front. `PhotosStep` alone is 23 kB of
 * that — it carries `browser-image-compression` — and it is the third screen,
 * not the first. Split, `/pets/create` starts on the wizard shell plus
 * `BasicStep` and picks the rest up as the user walks the form.
 *
 * `delay: 0` shows the skeleton the moment a chunk starts loading rather than
 * leaving the card blank for 200 ms; there is no flash on a step already
 * fetched, because a resolved async component renders synchronously from then
 * on. The loader is only ever entered once per step per page load.
 *
 * This is safe for the error jump below: sending `current` at a step whose
 * chunk has not been fetched simply renders the skeleton until it arrives. No
 * form state rides on it either — the form object lives on the page, and the
 * steps have always been unmounted on every swap.
 */
function step<T extends Component>(loader: AsyncComponentLoader<T>): T {
    return defineAsyncComponent<T>({
        loader,
        loadingComponent: PetFormStepSkeleton,
        delay: 0,
    });
}

const BasicStep = step(() => import('./steps/BasicStep.vue'));
const LocationStep = step(() => import('./steps/LocationStep.vue'));
const PhotosStep = step(() => import('./steps/PhotosStep.vue'));
const HealthStep = step(() => import('./steps/HealthStep.vue'));
const PersonalityStep = step(() => import('./steps/PersonalityStep.vue'));
const ExtrasStep = step(() => import('./steps/ExtrasStep.vue'));
const HealthcareStep = step(() => import('./steps/HealthcareStep.vue'));
const ReviewStep = step(() => import('./steps/ReviewStep.vue'));

const { t } = useTranslations();

const steps = computed<WizardStep[]>(() => [
    { id: 1, title: t('wizard.step_basics'), icon: PawPrint },
    { id: 2, title: t('wizard.step_location'), icon: MapPin },
    { id: 3, title: t('wizard.step_photos'), icon: Camera },
    { id: 4, title: t('wizard.step_health'), icon: Stethoscope },
    { id: 5, title: t('wizard.step_personality'), icon: Smile },
    { id: 6, title: t('wizard.step_extras'), icon: Info },
    { id: 7, title: t('wizard.step_healthcare'), icon: Heart },
    { id: 8, title: t('wizard.step_review'), icon: ClipboardCheck },
]);

/** Which step owns which error keys, matched by prefix. */
const STEP_KEYS: Record<number, string[]> = {
    1: [
        'name',
        'category_id',
        'breed_id',
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
    ],
    2: ['location'],
    3: ['featuredImage', 'images', 'deletedMediaIds'],
    // Every `health.*` leaf is listed explicitly rather than under a bare
    // `health` prefix, because the repeaters live under the same group on the
    // healthcare step and a prefix match would light both steps up for one bad
    // vaccination row.
    4: [
        'health.status',
        'health.vaccinated',
        'health.spayedNeutered',
        'health.lastVetVisit',
        'health.specialNeeds',
    ],
    5: ['description', 'traits'],
    6: ['additionalInfo'],
    7: [
        'health.vaccinations',
        'health.medications',
        'health.allergies',
        'health.vetName',
        'health.vetPhone',
    ],
};

const current = ref(1);
const visited = ref<number[]>([1]);

const errorKeys = computed(() => Object.keys(petFormErrors(form.errors)));

function ownsError(step: number): boolean {
    const prefixes = STEP_KEYS[step] ?? [];

    return errorKeys.value.some((key) =>
        prefixes.some(
            (prefix) => key === prefix || key.startsWith(`${prefix}.`),
        ),
    );
}

const invalidSteps = computed(() =>
    steps.value.map((step) => step.id).filter((id) => ownsError(id)),
);

const isLast = computed(() => current.value === steps.value.length);

/**
 * Whether the step on screen may be left in the forward direction.
 *
 * Only the basics step has a client check today. Stale messages from an
 * earlier pass are cleared first, so a field that was fixed stops showing
 * the old complaint, and the fresh set is handed to the form under the same
 * keys the server uses.
 */
function mayLeaveForward(step: number): boolean {
    if (step !== 1) {
        return true;
    }

    form.clearErrors(...BASIC_STEP_FIELDS);

    const errors = validateBasicStep(form);

    if (Object.keys(errors).length === 0) {
        return true;
    }

    // The client check hands back catalogue keys; the server's messages
    // arrive already translated, so only this side is resolved here.
    form.setError(
        Object.fromEntries(
            Object.entries(errors).map(([field, key]) => [field, t(key)]),
        ),
    );

    return false;
}

function goTo(step: number): void {
    const target = Math.min(Math.max(step, 1), steps.value.length);

    if (target > current.value && !mayLeaveForward(current.value)) {
        return;
    }

    current.value = target;

    if (!visited.value.includes(current.value)) {
        visited.value = [...visited.value, current.value];
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/** A rejected save re-opens the first step holding a bad field. */
watch(errorKeys, () => {
    const first = invalidSteps.value[0];

    if (first !== undefined && first !== current.value) {
        goTo(first);
    }
});
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <PetFormStepper
            :steps="steps"
            :current="current"
            :visited="visited"
            :invalid="invalidSteps"
            @select="goTo"
        />

        <Alert v-if="form.hasErrors" variant="destructive">
            <CircleAlert class="size-4" />
            <AlertTitle>{{ t('wizard.errors_title') }}</AlertTitle>
            <AlertDescription>{{ t('wizard.errors_desc') }}</AlertDescription>
        </Alert>

        <Card>
            <CardContent class="pt-6">
                <BasicStep
                    v-if="current === 1"
                    :form="form"
                    :options="options"
                />
                <LocationStep v-else-if="current === 2" :form="form" />
                <PhotosStep
                    v-else-if="current === 3"
                    :form="form"
                    :photo-bounds="photoBounds"
                    :photos="photos"
                    :current-featured-url="currentFeaturedUrl"
                />
                <HealthStep
                    v-else-if="current === 4"
                    :form="form"
                    :options="options"
                />
                <PersonalityStep v-else-if="current === 5" :form="form" />
                <ExtrasStep v-else-if="current === 6" :form="form" />
                <HealthcareStep v-else-if="current === 7" :form="form" />
                <ReviewStep
                    v-else
                    :form="form"
                    :options="options"
                    :current-featured-url="currentFeaturedUrl"
                />
            </CardContent>
        </Card>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <Button
                type="button"
                variant="outline"
                :disabled="current === 1"
                @click="goTo(current - 1)"
            >
                <ArrowLeft class="size-4 rtl:rotate-180" />
                {{ t('wizard.back') }}
            </Button>

            <div class="flex items-center gap-3">
                <span
                    v-if="form.progress"
                    class="text-muted-foreground text-sm"
                >
                    {{
                        t('wizard.uploading', {
                            percent: form.progress.percentage ?? 0,
                        })
                    }}
                </span>

                <Button v-if="!isLast" type="button" @click="goTo(current + 1)">
                    {{ t('wizard.next') }}
                    <ArrowRight class="size-4 rtl:rotate-180" />
                </Button>

                <Button v-else type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    {{ submitLabel }}
                </Button>
            </div>
        </div>
    </form>
</template>
