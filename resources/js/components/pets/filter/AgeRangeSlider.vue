<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

/**
 * A two-thumb age range, built the way a dual-handle slider has to be built out
 * of native controls: two `<input type="range">` stacked on one track, the
 * inputs `pointer-events: none` so the track underneath stays visible, and only
 * the thumbs clickable.
 *
 * ## Why the fill is not a plain percentage
 *
 * A range input's thumb does not travel the full width of the input — it
 * travels `width - thumb`, so its **centre** at fraction `f` sits at
 * `f * (width - thumb) + thumb / 2`, not at `f * width`. The legacy filter
 * positioned the filled bar at the plain percentage, which is correct only at
 * the midpoint and drifts to half a thumb's width at either end.
 *
 * Measured in headless Chrome against **this** component, at an 852px track
 * with the 20px thumb and `ceiling = 15`, at `(min, max)` of (0,0), (0,15),
 * (7,7), (15,15) and (3,11). The thumbs were located by walking
 * `document.elementFromPoint` across the track in 0.25px steps: the inputs are
 * `pointer-events: none` and only the thumbs are `auto`, so a hit that lands on
 * an `<input>` is inside a thumb box — which measures the same border box the
 * paint uses, and incidentally proves the pointer-events arrangement works.
 *
 * A baseline copy carrying the legacy formula was measured in the same page and
 * the same pass. It is out by **exactly half a thumb** at each end and by
 * nothing at the midpoint — probe readings -9.88px at `min = 0`, +10.50px at
 * `max = 15`, ±5.2/5.4px at (3,11), -0.16px at (7,7). With the correction the
 * fill edge lands on the analytic thumb centre to the hundredth of a pixel
 * (200.39 vs 200.4 at value 3; 422.27 vs 422.27 at 7; 644.14 vs 644.13 at 11;
 * 866 vs 866 at 15), and the largest disagreement with the *probed* centre is
 * 0.64px — that residual is the probe's resolution, since Chrome resolves
 * `elementFromPoint` on whole device pixels, not a leftover offset.
 *
 * `THUMB_SIZE_PX` and the `size-5` in the thumb's classes are the same number
 * written twice; they must stay equal or the correction is calculated against a
 * thumb that is not there.
 *
 * ## Direction
 *
 * The offsets are `inset-inline-*`, and Chrome flips a range input's own travel
 * under `dir="rtl"`, so the fill and the thumbs flip together with no direction
 * check anywhere in this file. Confirmed on the same five cases with `dir` set
 * to `rtl`: the `min = 0` thumb moves to the right-hand end (centre 865.5px
 * instead of 33.88px) and every error stays within 0.5px.
 *
 * ## Accessibility
 *
 * `<input type="range">` carries `role="slider"`, `aria-valuemin/max/now` and
 * arrow-key handling for free. What it does not carry is a *name*: two
 * identical range inputs on one track are indistinguishable, and pointing
 * `aria-labelledby` at the visible readout — which this file used to do — is
 * worse than nothing, because the handle then announces as its own value and
 * renames itself on every drag. So each input gets a fixed `aria-label` and an
 * `aria-valuetext` carrying the years: a stable name, a spoken value, which is
 * the split the ARIA slider pattern asks for.
 *
 * Measured 2026-09-03 in headless Chrome 152 via CDP
 * `Accessibility.getPartialAXTree`, on this component with the sheet open. The
 * accessible names are now `"Minimum age"` and `"Maximum age"`; reproducing the
 * previous wiring in place on the same page (drop `aria-label`, point
 * `aria-labelledby` back at the readout span) returns them to `"0 years"` and
 * `"15 years"` — the value standing in for the name, which is the defect.
 *
 * The `aria-valuetext` half is **not** measured, and no claim here says it is.
 * Chrome's CDP AX tree does not surface it: with `aria-valuetext="7 years"` on
 * the max thumb it still reports `valuetext: "7"`, and an isolated
 * `role="slider"` div with the same attribute reports `valuetext: ""`. That is
 * a limit of what CDP exposes, not evidence the attribute is ignored — but it
 * means the years reaching a screen reader rests on the ARIA spec here, not on
 * an observation.
 *
 * Every id in the file is built from `idPrefix`, like the sibling filter
 * components, so a second slider on one page cannot collide; the two ids were
 * previously hardcoded and document-global.
 *
 * ## Why this is not `reka-ui`'s `SliderRoot`
 *
 * A deliberate choice, not an oversight. reka 2.10.4 ships a two-thumb slider
 * with `role="slider"`, full ARIA and `dir`-aware travel, and it is already a
 * dependency — adopting it would be a new file under `components/ui/slider`,
 * not a dependency change, and it would delete the fill geometry, the
 * `THUMB_SIZE_PX` / `size-5` duplication hazard and the naming problem above
 * outright.
 *
 * It is not adopted because it would move pixels that were measured. reka
 * positions its own thumbs and range, so every number in the section above —
 * the analytic `start·(W−T) + T/2` correction and the probe readings that
 * confirmed it — would describe a component that no longer exists, and the
 * measurement would have to be redone to say anything about the result. That is
 * a swap worth making on its own, with its own before/after, rather than
 * folded into a review fix. Anyone picking it up: `components/ui/slider` is the
 * home, and this component's whole public surface is `min`, `max`, `ceiling`
 * and `idPrefix`.
 */
const { ceiling, idPrefix } = defineProps<{
    /** The oldest age the feed will filter to — `bounds.max_age_years`. */
    ceiling: number;
    /** Distinguishes this slider's ids from a second instance's. */
    idPrefix: string;
}>();

const min = defineModel<number>('min', { required: true });
const max = defineModel<number>('max', { required: true });

const { t } = useTranslations();

/** Diameter of the thumb. Kept in step with `size-5` below. */
const THUMB_SIZE_PX = 20;

/**
 * The thumb is a shadow-DOM pseudo-element, so its appearance cannot be
 * expressed as an ordinary utility on the input and is written as arbitrary
 * variants instead. Shared by both inputs, hence the constant — Tailwind scans
 * this file as text, so the classes are found here just as they would be in the
 * template.
 */
const RANGE_INPUT_CLASSES = [
    'pointer-events-none absolute inset-0 m-0 w-full appearance-none bg-transparent focus:outline-none',
    '[&::-webkit-slider-runnable-track]:appearance-none [&::-webkit-slider-runnable-track]:bg-transparent',
    '[&::-moz-range-track]:appearance-none [&::-moz-range-track]:bg-transparent',
    '[&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:size-5 [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-[3px] [&::-webkit-slider-thumb]:border-primary [&::-webkit-slider-thumb]:bg-background [&::-webkit-slider-thumb]:shadow-sm',
    '[&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:size-5 [&::-moz-range-thumb]:cursor-pointer [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-[3px] [&::-moz-range-thumb]:border-primary [&::-moz-range-thumb]:bg-background',
    '[&:focus-visible::-webkit-slider-thumb]:outline-ring [&:focus-visible::-webkit-slider-thumb]:outline-2 [&:focus-visible::-webkit-slider-thumb]:outline-offset-2',
    '[&:focus-visible::-moz-range-thumb]:outline-ring [&:focus-visible::-moz-range-thumb]:outline-2 [&:focus-visible::-moz-range-thumb]:outline-offset-2',
].join(' ');

function fraction(value: number): number {
    if (ceiling <= 0) {
        return 0;
    }

    return Math.min(Math.max(value / ceiling, 0), 1);
}

/** `calc(50% + 3px)` / `calc(50% - 3px)`, never `calc(50% + -3px)`. */
function offset(percent: number, pixels: number): string {
    const rounded = Math.round(pixels * 100) / 100;
    const sign = rounded < 0 ? '-' : '+';

    return `calc(${Math.round(percent * 1e4) / 1e4}% ${sign} ${Math.abs(rounded)}px)`;
}

const fillStyle = computed(() => {
    const start = fraction(min.value);
    const end = fraction(max.value);

    return {
        insetInlineStart: offset(start * 100, (0.5 - start) * THUMB_SIZE_PX),
        insetInlineEnd: offset((1 - end) * 100, (end - 0.5) * THUMB_SIZE_PX),
    };
});

function readValue(event: Event): number {
    return Number((event.target as HTMLInputElement).value);
}

/**
 * The thumbs clamp against each other rather than swapping, so a drag past the
 * other handle parks on it. An inverted range is a 422 from the feed's own
 * request (`age_max` must be `gte:age_min`), so it must not be reachable.
 */
function onMinInput(event: Event): void {
    min.value = Math.min(readValue(event), max.value);
}

function onMaxInput(event: Event): void {
    max.value = Math.max(readValue(event), min.value);
}

function yearsLabel(value: number): string {
    return `${value} ${t(value === 1 ? 'home.year' : 'home.years')}`;
}
</script>

<template>
    <div class="px-2">
        <div
            class="relative h-5 w-full"
            role="group"
            :aria-label="t('home.age_range')"
        >
            <div
                class="bg-muted absolute inset-x-0 top-1/2 h-2 -translate-y-1/2 rounded-full"
            ></div>
            <div
                class="bg-primary absolute top-1/2 h-2 -translate-y-1/2 rounded-full"
                :style="fillStyle"
            ></div>

            <input
                :id="`${idPrefix}-min`"
                :value="min"
                type="range"
                :min="0"
                :max="ceiling"
                step="1"
                :class="RANGE_INPUT_CLASSES"
                :aria-label="t('home.age_minimum')"
                :aria-valuetext="yearsLabel(min)"
                @input="onMinInput"
            />

            <!-- Stacked last and lifted, so the two thumbs stay separable when
                 they land on the same value. -->
            <input
                :id="`${idPrefix}-max`"
                :value="max"
                type="range"
                :min="0"
                :max="ceiling"
                step="1"
                :class="[
                    RANGE_INPUT_CLASSES,
                    '[&::-webkit-slider-thumb]:relative [&::-webkit-slider-thumb]:z-10',
                ]"
                :aria-label="t('home.age_maximum')"
                :aria-valuetext="yearsLabel(max)"
                @input="onMaxInput"
            />
        </div>

        <!-- The visible readout. Not wired to either input: `aria-valuetext`
             already carries the same words as the handle's value, and pointing
             `aria-describedby` here would announce them twice. -->
        <div class="text-muted-foreground mt-3 flex justify-between text-xs">
            <span :id="`${idPrefix}-min-value`">{{ yearsLabel(min) }}</span>
            <span :id="`${idPrefix}-max-value`">{{ yearsLabel(max) }}</span>
        </div>
    </div>
</template>
