<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useTranslations } from '@/composables/useTranslations';
import { home } from '@/routes';

/**
 * The wordmark in the public header: the SniffPal mark (`AppLogoIcon`, a
 * violet chat bubble with a pet face) and the brand name beside it.
 *
 * It is the only wordmark now. `AppLogo` was the member-shell equivalent and
 * painted itself with `--sidebar-primary`; it went with the sidebar shell
 * (2026-09-06). The mark pins its own violet, because it is the same in both
 * schemes.
 */
const { t } = useTranslations();
</script>

<template>
    <Link :href="home()" class="flex items-center gap-2.5">
        <AppLogoIcon class="size-10 shrink-0" aria-hidden="true" />
        <span class="text-foreground text-xl font-bold max-sm:sr-only">
            {{ t('nav.brand') }}
        </span>
    </Link>
</template>
