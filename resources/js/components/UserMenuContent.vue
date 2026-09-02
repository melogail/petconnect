<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LifeBuoy, LogOut, Settings } from '@lucide/vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { useTranslations } from '@/composables/useTranslations';
import { help, logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

/**
 * The body of the signed-in menu: who you are, then settings, help and logout.
 *
 * Every string goes through `t()` against keys that already exist in both
 * catalogues — `nav.help_center` is the same key `PublicFooter` puts on the
 * same route, so the two never drift. Spacing is logical (`me-`, `text-start`),
 * not physical: this menu renders in Arabic directly above
 * `ShellPreferenceItems`, and an `mr-` here put every icon on the wrong side of
 * its label under `dir="rtl"`.
 */
type Props = {
    user: User;
};

const { t } = useTranslations();

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();
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
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="me-2 h-4 w-4" />
                {{ t('profile.settings') }}
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="help()">
                <LifeBuoy class="me-2 h-4 w-4" />
                {{ t('nav.help_center') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="me-2 h-4 w-4" />
            {{ t('auth.log_out') }}
        </Link>
    </DropdownMenuItem>
</template>
