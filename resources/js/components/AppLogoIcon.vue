<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();

/**
 * The SniffPal mark: a violet chat bubble carrying a pet face whose nose is a
 * heart. The same drawing as `public/brand/sniffpal-mark.svg`, inlined so the
 * header paints it with the document instead of after a second request.
 *
 * It is full colour and the same violet in both schemes, so a `fill-current`
 * or `text-*` class at a call site does nothing; size it and leave it.
 *
 * The bubble is one flat fill, `#5A3FE0` — the "Aubergine & Lavender"
 * `--primary-600`, the same violet the guide puts on the splash field and the
 * header's `BrandMark` disc. It used to be a `violet-500 → violet-700`
 * `<linearGradient>` with a `useId()`-generated id; the restyle retires every
 * gradient, which also removed the only reason this component needed an id at
 * all. It stays pinned rather than `fill-current` because the white face on
 * top needs the darker light-scheme violet underneath it in both schemes —
 * on the dark scheme's `--primary` (`#A78BFF`) white would fall under 3:1.
 */
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 64 64"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="#5A3FE0"
            d="M18 4 H46 C55.9 4 60 8.1 60 18 V40 C60 49.9 55.9 54 46 54 H27 L15.2 61.4 C13.6 62.4 12 61.4 12.2 59.6 L13 53.2 C6.6 51.6 4 47.4 4 40 V18 C4 8.1 8.1 4 18 4 Z"
        />
        <circle cx="18.6" cy="19.4" r="3" fill="#fff" />
        <circle cx="45.4" cy="19.4" r="3" fill="#fff" />
        <path
            fill="#fff"
            d="M32 33.26 C28.47 30.9 24.2 28.25 24.2 24.57 C24.2 22.29 25.97 20.6 28.25 20.6 C29.79 20.6 31.19 21.41 32 22.73 C32.81 21.41 34.21 20.6 35.75 20.6 C38.03 20.6 39.8 22.29 39.8 24.57 C39.8 28.25 35.53 30.9 32 33.26 Z"
        />
        <path
            stroke="#fff"
            stroke-width="3"
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M22.6 38.6 C24.2 44 30.4 43.8 32 39 C33.6 43.8 39.8 44 41.4 38.6 M32 32.4 V39"
        />
    </svg>
</template>
