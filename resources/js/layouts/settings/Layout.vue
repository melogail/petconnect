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
 * port of legacy's `pages/profile/Edit.vue` — the banner from
 * `components/web/profile/edit/EditHeader.vue`, the card pulled up over it with
 * `-mt-24`, and the section list down the start edge with an icon, a label
 * and, on the active row, its description. The starter kit's own settings
 * layout — a plain heading and a column of ghost buttons inside the sidebar
 * shell — is what this replaced, on the user's instruction (2026-09-06).
 *
 * The banner was legacy's violet→purple→pink gradient with three blurred orbs
 * over it (the `bg-violet-*` literals written as the `primary-*` ramp). The
 * "Aubergine & Lavender" restyle replaced it with the lavender
 * `bg-gradient-wash` (primary-100 into the card colour, a single hue) over a
 * `bg-secondary` fallback — same height, same pull-up, no orbs — with the
 * heading in Newsreader and the copy in the
 * `--foreground` / `--soft-foreground` / `--secondary-foreground` tokens, all of
 * which clear AA on `--secondary` (#4A32C4 on #EEEAFB is 7.4:1 light; #CDBDFF
 * on #261D45 is 9.2:1 dark). The card is the guide's card frame
 * (`rounded-[20px] border-border shadow-card`) and the section rows take the
 * `bg-accent` / `text-accent-foreground` active treatment.
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
    <div class="bg-background">
        <div
            class="bg-secondary bg-gradient-wash relative overflow-hidden pt-16 pb-32 lg:pt-24"
        >
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <div
                        class="text-secondary-foreground mb-4 flex items-center gap-x-3"
                    >
                        <span
                            class="bg-card text-secondary-foreground ring-border inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset"
                        >
                            {{ t('profile.settings') }}
                        </span>
                        <span
                            class="bg-primary h-1 w-1 rounded-full"
                            aria-hidden="true"
                        ></span>
                        <span class="text-sm font-medium">
                            {{ t('profile.profile_management') }}
                        </span>
                    </div>

                    <h1
                        class="text-foreground font-serif text-4xl leading-[1.05] font-medium tracking-[-0.015em] sm:text-5xl lg:text-6xl"
                    >
                        {{ t('profile.edit_profile') }}
                    </h1>

                    <p
                        class="text-soft-foreground mt-4 max-w-2xl text-lg leading-relaxed"
                    >
                        {{ t('profile.edit_header_description') }}
                    </p>
                </div>
            </div>
        </div>

        <div
            class="relative z-10 mx-auto -mt-24 max-w-7xl px-4 pb-12 sm:px-6 lg:px-8"
        >
            <div
                class="bg-card border-border shadow-card rounded-[20px] border"
            >
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
                                class="group focus-visible:ring-ring focus-visible:ring-offset-background flex w-full items-center rounded-[14px] px-3 py-3 text-sm font-medium transition-[background-color,border-color,box-shadow,transform] duration-200 ease-out outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                                :class="
                                    isActive(section)
                                        ? 'bg-accent text-accent-foreground'
                                        : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                                "
                            >
                                <component
                                    :is="section.icon"
                                    class="me-3 size-5 shrink-0"
                                    :class="
                                        isActive(section)
                                            ? 'text-accent-foreground'
                                            : 'text-muted-foreground group-hover:text-accent-foreground'
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
                                    class="bg-primary ms-auto size-1 shrink-0 rounded-full"
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
