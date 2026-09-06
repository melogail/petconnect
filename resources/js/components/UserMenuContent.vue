<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    LifeBuoy,
    LogOut,
    MessageSquareMore,
    Settings,
    User as UserIcon,
} from '@lucide/vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { useTranslations } from '@/composables/useTranslations';
import { help, logout } from '@/routes';
import { index as conversationsIndex } from '@/routes/conversations';
import { edit, show as showProfile } from '@/routes/profile';
import type { User } from '@/types';

/**
 * The body of the signed-in menu: who you are, then where you can go, then out.
 *
 * ## The four groups are legacy's
 *
 * `components/web/UserDropdown.vue` divided this menu into four rules-separated
 * blocks: identity, then Messages + Your Profile, then help, then a red Sign
 * out. The phase 5 sweep restored the two that were missing.
 *
 * - **Messages** and **Your Profile** were simply absent. `conversations.index`
 *   was reachable only from the header icon, and `profile.show` from nothing in
 *   the chrome at all, so a reader had no route to their own public profile
 *   without finding their name on a listing.
 * - **Settings** is an addition to legacy, kept. Legacy had no settings page to
 *   link; this application does, and this menu plus `AppSidebar` are the only
 *   two things that link `profile.edit`.
 * - Legacy's help block held two entries: `nav.help_and_support` pointing at
 *   `/help`, and `nav.help_center` pointing at a dead `href="#"`. One route,
 *   one entry — and it keeps the label legacy put on the live route,
 *   `nav.help_and_support`, rather than the one legacy put on the dead one.
 *   Both keys exist in both catalogues. `PublicFooter` reaches the same route
 *   under `nav.help_center`, which is the label a footer link wants; the two
 *   are the same destination and deliberately not the same wording.
 * - **Sign out** is `variant="destructive"`, which is this app's spelling of
 *   legacy's `text-red-600 hover:bg-red-50`. It was an ordinary item, which
 *   made the one irreversible thing in the menu look like the two navigations
 *   above it. Its label is `nav.sign_out` ("Sign out"), legacy's key and
 *   legacy's wording, not the `auth.log_out` ("Log out") this menu carried.
 *   `auth.log_out` stays on `VerifyEmailResendForm`, which is a different
 *   screen with its own copy; legacy split the two the same way.
 *
 * ## Everything resolves through `t()`, and the spacing is logical
 *
 * Every string goes through `t()` against keys that already exist in both
 * catalogues — `nav.help_and_support` is legacy's label for this entry, and
 * `nav.messages` is the key `MessagesDropdown` puts on its trigger. Spacing is
 * logical (`me-`, `text-start`), not physical: this menu renders in Arabic
 * directly above `ShellPreferenceItems`, and an `mr-` here put every icon on
 * the wrong side of its label under `dir="rtl"`.
 *
 * The messages glyph is `MessageSquareMore`, the same one the header menu
 * carries, because the two are the same destination and legacy drew them with
 * the same path.
 */
type Props = {
    user: User;
};

const { t } = useTranslations();

const handleLogout = () => {
    router.flushAll();
};

const { user } = defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="conversationsIndex()"
            >
                <MessageSquareMore class="me-2 h-4 w-4" />
                {{ t('nav.messages') }}
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="showProfile(user.id)"
            >
                <UserIcon class="me-2 h-4 w-4" />
                {{ t('nav.your_profile') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="me-2 h-4 w-4" />
                {{ t('profile.settings') }}
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="help()">
                <LifeBuoy class="me-2 h-4 w-4" />
                {{ t('nav.help_and_support') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true" variant="destructive">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="me-2 h-4 w-4" />
            {{ t('nav.sign_out') }}
        </Link>
    </DropdownMenuItem>
</template>
