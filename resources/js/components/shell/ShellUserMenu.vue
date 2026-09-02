<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import ShellPreferenceItems from '@/components/shell/ShellPreferenceItems.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserAvatar from '@/components/UserAvatar.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { User } from '@/types';

/**
 * The signed-in trigger in the header: avatar with a brand ring, first name
 * from `sm` up, and a chevron that turns when the menu opens.
 *
 * There is no presence dot. The legacy navbar had none either, and nothing in
 * this application tracks presence — `ProfileResource` dropped `last_seen_at`
 * because nothing writes the column. A dot here would paint a state that does
 * not exist. Add one when presence does.
 *
 * Only the trigger is ours. The menu body is `UserMenuContent`, which already
 * owns the profile header, the settings and help items and the logout POST —
 * rebuilding it here would fork the logout path. Below `sm` it is followed by
 * `ShellPreferenceItems`, because the header hides the locale pill and the
 * appearance knob at those widths and this is the menu that already exists —
 * a guest gets the same items from `ShellPreferenceMenu`. Dismissal is
 * `DropdownMenu`'s
 * (Reka UI), which is why there is no click-outside listener: the legacy
 * component wired `document.addEventListener('click', …)` by hand and lost
 * focus management, escape handling and portalling with it.
 */
const { user } = defineProps<{
    user: User;
}>();

const { t } = useTranslations();

const open = ref(false);

const firstName = computed(() => user.name.split(' ')[0]);
</script>

<template>
    <DropdownMenu v-model:open="open">
        <DropdownMenuTrigger
            class="hover:bg-accent focus-visible:ring-ring/50 data-[state=open]:bg-accent flex items-center gap-2 rounded-full p-1 transition-colors outline-none focus-visible:ring-[3px]"
            :aria-label="t('nav.user_menu_for', { name: user.name })"
        >
            <UserAvatar
                :name="user.name"
                :avatar="user.avatar"
                class="border-primary-500 dark:border-primary-400 size-8 border-2"
            />

            <span class="text-foreground hidden text-sm font-medium sm:block">
                {{ firstName }}
            </span>

            <ChevronDown
                class="text-muted-foreground size-3.5 transition-transform duration-200"
                :class="{ 'rotate-180': open }"
                aria-hidden="true"
            />
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-56">
            <UserMenuContent :user="user" />

            <DropdownMenuSeparator class="sm:hidden" />
            <ShellPreferenceItems class="sm:hidden" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
