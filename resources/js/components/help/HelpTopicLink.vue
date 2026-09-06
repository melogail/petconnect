<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import type { Component } from 'vue';

/**
 * One "quick link" tile on the help centre: an icon, a heading, a line of
 * explanation, and somewhere to go.
 *
 * The legacy page rendered these as `<button>` elements with no handler, so
 * they looked clickable and did nothing. The whole tile is the link here, which
 * is also why the heading is an `h3` and not a second link inside one.
 */
defineProps<{
    icon: Component;
    title: string;
    description: string;
    /** A Wayfinder route definition, same as `NavItem['href']`. */
    href: NonNullable<InertiaLinkProps['href']>;
}>();
</script>

<template>
    <Link
        :href="href"
        class="border-border bg-card hover:border-primary/50 hover:bg-accent/40 flex items-start gap-3 rounded-xl border p-4 transition-colors"
    >
        <span class="bg-muted rounded-lg p-2">
            <component
                :is="icon"
                class="text-muted-foreground size-5"
                aria-hidden="true"
            />
        </span>

        <!--
            A `div`, not a `span`: it wraps an `h3` and a `p`, which are flow
            content, and phrasing content may not contain them. `<a>` has a
            transparent content model, so flow content is fine directly inside
            the link.
        -->
        <div class="min-w-0 flex-1">
            <h3 class="font-medium">{{ title }}</h3>
            <p class="text-muted-foreground mt-1 text-sm">
                {{ description }}
            </p>
        </div>

        <ChevronRight
            class="text-muted-foreground mt-2 size-4 shrink-0 rtl:rotate-180"
            aria-hidden="true"
        />
    </Link>
</template>
