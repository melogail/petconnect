<script setup lang="ts">
import { SlidersHorizontal } from '@lucide/vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import ShellPreferenceItems from '@/components/shell/ShellPreferenceItems.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';

/**
 * The guest's small-screen home for language and appearance.
 *
 * A signed-in reader already has a menu — `ShellUserMenu` — and gets the same
 * `ShellPreferenceItems` inside it, so this trigger exists only for guests.
 * It carries `sm:hidden` itself rather than taking a wrapper in the header:
 * the Reka `DropdownMenu` root renders no element, so a fall-through class on
 * this component would land nowhere.
 *
 * The accessible name is `nav.preferences` — "Preferences" at lang/en.json:28,
 * "التفضيلات" at lang/ar.json:28, both read there. Deliberately not the
 * account-settings string ("Settings" at lang/en.json:404): that one names the
 * account page linked from `UserMenuContent.vue:47-52` — the menu item whose
 * `:href="edit()"` is on :48 and whose label is on :50, read there.
 *
 * That collision is across views, not within one screen. `PublicHeader.vue:122`
 * renders `ShellUserMenu` inside the authenticated `v-if`; :126 renders this
 * component in the guest `v-else`, so the two are mutually exclusive and a guest
 * never sees the Settings link. The reader being protected is the one who meets
 * "Settings" as a preferences trigger in one session and as an account link in
 * the next.
 *
 * Direction is not passed here. `app.ts` mounts a single `<ConfigProvider :dir>`
 * above the whole app, which every Reka primitive inherits through
 * `useDirection()` — portalled content included. A local `dir` prop beside that
 * is the workaround this file used to carry, and it is gone on purpose.
 */
const { t } = useTranslations();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="rounded-full sm:hidden"
                :aria-label="t('nav.preferences')"
            >
                <SlidersHorizontal class="size-5" aria-hidden="true" />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-52">
            <ShellPreferenceItems />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
