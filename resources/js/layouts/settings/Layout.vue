<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import { Palette, ShieldCheck, User } from '@lucide/vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useTranslations } from '@/composables/useTranslations';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

/**
 * The settings shell: the legacy profile edit screen, with routes where legacy
 * had tabs.
 *
 * `app.ts` nests this inside `PublicLayout` for every `settings/*` page, so it
 * sits under the same navbar and above the same footer as the feed. It is a
 * port of legacy's `pages/profile/Edit.vue` — the gradient banner from
 * `components/web/profile/edit/EditHeader.vue` (its `bg-violet-*` literals
 * written as the `primary-*` ramp `resources/css/app.css` registers for exactly
 * this purpose), the card pulled up over it with `-mt-24`, and the section list
 * down the start edge with an icon, a label and, on the active row, its
 * description. The starter kit's own settings layout — a plain heading and a
 * column of ghost buttons inside the sidebar shell — is what this replaced, on
 * the user's instruction (2026-09-06).
 *
 * ## Links, not tabs, and the reason is the middleware
 *
 * Legacy's three sections were `v-show` panels of **one** form, so switching
 * between them cost nothing and posted everything. Here they are three pages:
 * the profile form is a PATCH edited a panel at a time (`pages/settings/
 * Profile.vue`), the security page is behind `RequirePassword` so it may ask
 * for a password before it renders, and appearance is a cookie the browser
 * writes. One page could not carry all three without either dropping the
 * password gate or making the profile form confirm a password to change a
 * bio. So the section list is `<Link>`s, the active one is decided from the
 * URL, and each page keeps its own props and its own controller.
 *
 * The banner's `<h1>` is the document's heading — the pages under it render
 * their titles as `h2` through `Heading`, which is why they carry no `sr-only`
 * h1 of their own the way the top-level pages do.
 *
 * Every string goes through `t()`; the banner reuses the `profile.*` keys the
 * legacy header was ported with, and the section rows read `settings.*`, all
 * present in both catalogues.
 */
type SettingsSection = {
    title: string;
    description: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
};

const { t } = useTranslations();

const { isCurrentOrParentUrl } = useCurrentUrl();

const sections = computed<SettingsSection[]>(() => [
    {
        title: t('settings.profile'),
        description: t('settings.profile_description'),
        href: editProfile(),
        icon: User,
    },
    {
        title: t('settings.security'),
        description: t('settings.security_description'),
        href: editSecurity(),
        icon: ShieldCheck,
    },
    {
        title: t('settings.appearance'),
        description: t('settings.appearance_description'),
        href: editAppearance(),
        icon: Palette,
    },
]);

function isActive(section: SettingsSection): boolean {
    return isCurrentOrParentUrl(section.href);
}
</script>

<template>
    <div class="bg-muted/40 dark:bg-background">
        <div
            class="bg-primary-600 relative overflow-hidden pt-16 pb-32 shadow-xl lg:pt-24 dark:bg-gray-900"
        >
            <div class="absolute inset-0" aria-hidden="true">
                <div
                    class="from-primary-500 dark:from-primary-900 absolute inset-0 bg-gradient-to-br via-purple-100 to-pink-100 opacity-90 mix-blend-multiply dark:via-gray-900 dark:to-gray-950 dark:mix-blend-normal"
                ></div>
                <div
                    class="absolute -start-20 -top-20 h-96 w-96 rounded-full bg-white/20 blur-3xl"
                ></div>
                <div
                    class="absolute -end-20 top-20 h-80 w-80 rounded-full bg-purple-300/20 blur-3xl"
                ></div>
                <div
                    class="absolute bottom-0 left-1/2 h-64 w-64 -translate-x-1/2 rounded-full bg-white/10 blur-2xl"
                ></div>
            </div>

            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <div
                        class="text-primary-100 mb-4 flex items-center gap-x-3"
                    >
                        <span
                            class="inline-flex items-center rounded-md bg-white/20 px-2 py-1 text-xs font-medium text-white ring-1 ring-white/30 ring-inset"
                        >
                            {{ t('profile.settings') }}
                        </span>
                        <span
                            class="bg-primary-200 h-1 w-1 rounded-full"
                            aria-hidden="true"
                        ></span>
                        <span class="text-sm font-medium">
                            {{ t('profile.profile_management') }}
                        </span>
                    </div>

                    <h1
                        class="text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-6xl"
                    >
                        {{ t('profile.edit_profile') }}
                    </h1>

                    <p
                        class="text-primary-50 mt-4 max-w-2xl text-lg leading-relaxed"
                    >
                        {{ t('profile.edit_header_description') }}
                    </p>
                </div>
            </div>
        </div>

        <div
            class="relative z-10 mx-auto -mt-24 max-w-7xl px-4 pb-12 sm:px-6 lg:px-8"
        >
            <div class="bg-card ring-border rounded-2xl shadow-xl ring-1">
                <div class="flex flex-col lg:flex-row">
                    <aside
                        class="border-border w-full border-b px-4 py-6 lg:w-72 lg:shrink-0 lg:border-e lg:border-b-0 lg:px-6 lg:py-8"
                    >
                        <nav
                            class="space-y-1"
                            :aria-label="t('profile.settings')"
                        >
                            <Link
                                v-for="section in sections"
                                :key="toUrl(section.href)"
                                :href="section.href"
                                :aria-current="
                                    isActive(section) ? 'page' : undefined
                                "
                                class="group flex w-full items-center rounded-xl px-3 py-3 text-sm font-medium transition-colors"
                                :class="
                                    isActive(section)
                                        ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                "
                            >
                                <component
                                    :is="section.icon"
                                    class="me-3 size-5 shrink-0"
                                    :class="
                                        isActive(section)
                                            ? 'text-primary-600 dark:text-primary-400'
                                            : 'text-muted-foreground group-hover:text-foreground'
                                    "
                                    aria-hidden="true"
                                />

                                <span class="min-w-0 text-start">
                                    <span class="block">
                                        {{ section.title }}
                                    </span>
                                    <span
                                        v-if="isActive(section)"
                                        class="mt-0.5 block text-xs font-normal opacity-80"
                                    >
                                        {{ section.description }}
                                    </span>
                                </span>

                                <span
                                    v-if="isActive(section)"
                                    class="bg-primary-500 ms-auto size-1 shrink-0 rounded-full"
                                    aria-hidden="true"
                                ></span>
                            </Link>
                        </nav>
                    </aside>

                    <div class="min-w-0 flex-1 px-4 py-6 lg:px-8 lg:py-8">
                        <div class="max-w-2xl space-y-12">
                            <slot />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
