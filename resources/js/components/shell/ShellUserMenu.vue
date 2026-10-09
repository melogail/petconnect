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
 * The signed-in trigger in the header: a 34px `rounded-xl` avatar (solid
 * `--primary` with `--primary-foreground` initials when there is no photo —
 * the style guide's §5.3 avatar, so the former brand-coloured border ring is
 * gone), first name from `sm` up, and a chevron that turns when the menu opens.
 * The trigger itself is the header's `rounded-[14px]` ghost treatment.
 *
 * There is no presence dot, and legacy had one:
 * `components/web/UserDropdown.vue:88-91` hangs a `bg-emerald-500` "online
 * status indicator" off the corner of this avatar, unconditionally. It is
 * deliberately not ported, because nothing in this application tracks presence:
 * `ProfileResource` dropped `last_seen_at` on the finding that **no code path
 * ever writes the column** — only the factory and the seeder do — and
 * `AuthenticatedUserResource` never carried it either
 * (.ai/rules/resources.md). An always-green dot is decoration wearing the
 * clothes of a status, which is one of the twelve defects .ai/rules/general.md
 * names. Add it when presence exists.
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
 *
 * ## The avatar initials are not a Label-in-Name failure, and here is the line
 *
 * This is written down because the two badged triggers beside it in the header
 * *were* fixed for exactly the property this one appears to fail, and an
 * unexplained difference between siblings is worse than either verdict on its
 * own — the next reader adjudicates, and adjudicates toward whichever file they
 * happen to be editing.
 *
 * Measured out of Chrome's accessibility tree over CDP against an isolated
 * build, 2026-09-06: visible text `TU Test`, computed name
 * `User menu for Test User` (`قائمة المستخدم لـ Test User` under `ar`). The
 * name contains "Test"; it does not contain "TU". A containment check run over
 * the whole tree flags it on every page.
 *
 * It is still a pass, and the distinguisher is checkable rather than a matter
 * of taste: **the initials are the fallback rendering of an image, not text
 * that labels the control.** `UserAvatar` renders `AvatarImage` with
 * `alt="{name}"` when the reader has uploaded a photo and `AvatarFallback` with
 * initials when they have not, so whether these two glyphs exist at all is a
 * function of an upload. A property that appears and disappears with an image
 * upload is a property of an image. `UnreadBadge`'s "9+", by contrast, is
 * always text and is never an image, which is why that one had to be brought
 * into the name — see `shell/labels`.
 *
 * The first name *is* label text and is contained, because it is a prefix of
 * `user.name`, which `nav.user_menu_for` interpolates verbatim in every locale.
 * That is what keeps this control addressable by voice. If the trigger ever
 * renders a string that is **not** a substring of `user.name`, the exemption
 * above stops covering it and the name has to be rebuilt with `nameContaining`.
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
            class="hover:bg-accent hover:text-accent-foreground data-[state=open]:bg-accent data-[state=open]:text-accent-foreground focus-visible:ring-ring focus-visible:ring-offset-background flex items-center gap-2 rounded-[14px] p-1 transition-[background-color,border-color,box-shadow,transform] duration-200 ease-out outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
            :aria-label="t('nav.user_menu_for', { name: user.name })"
        >
            <UserAvatar
                :name="user.name"
                :avatar="user.avatar"
                class="size-[34px]"
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
