<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';
import { home } from '@/routes';

/**
 * The wordmark in the public header: a violet rounded square with a white
 * circle punched out of it, and the brand name beside it.
 *
 * `AppLogo` is the member-shell equivalent and paints itself with
 * `--sidebar-primary`; this one is the public face and pins the brand step
 * directly, because it is the same violet in both schemes.
 */
const { t } = useTranslations();
</script>

<template>
    <Link :href="home()" class="flex items-center gap-2.5">
        <span
            class="bg-primary-500 flex size-10 items-center justify-center rounded-2xl"
            aria-hidden="true"
        >
            <span class="size-6 rounded-full bg-white" />
        </span>
        <span class="text-foreground text-xl font-bold max-sm:sr-only">
            {{ t('nav.brand') }}
        </span>
    </Link>
</template>
