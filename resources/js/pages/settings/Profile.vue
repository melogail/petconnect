<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import ProfileAccountPanel from '@/components/settings/ProfileAccountPanel.vue';
import ProfileAvatarPanel from '@/components/settings/ProfileAvatarPanel.vue';
import ProfileLanguagePanel from '@/components/settings/ProfileLanguagePanel.vue';
import ProfileLocationPanel from '@/components/settings/ProfileLocationPanel.vue';
import { Separator } from '@/components/ui/separator';
import { edit } from '@/routes/profile';
import type { ProfileFormData, SelectOption } from '@/types';

/**
 * The settings profile form, panel by panel.
 *
 * `profile.update` is a **PATCH**: every optional rule is `sometimes|nullable`
 * and the pipeline fills only the keys the request sent. One big form posting
 * the whole bag would write a null over every field it happened to render
 * empty, so this page is five independent forms, each posting the fields the
 * user actually changed plus the identity pair the rules make `required`.
 *
 * There is deliberately **no deactivation control**: `is_active` is outside
 * `#[Fillable]` and outside every Form Request, and only Nova on the `admins`
 * guard writes it.
 *
 * There is deliberately **no password panel** either. `UpdateProfileRequest`
 * used to accept `current_password` and `password` beside the avatar and the
 * address, duplicating Fortify's `user-password.update`; both keys are gone
 * from its rules, so posting them here would validate nothing and change
 * nothing. Changing a password is `settings/Security` and only that. This is a
 * deliberate divergence from the legacy app, which had the pair on both pages.
 */
defineProps<{
    profile: ProfileFormData;
    locales: SelectOption[];
    mustVerifyEmail: boolean;
    status: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Profile settings', href: edit() }],
    },
});

/**
 * The IANA list the browser already knows, so the time-zone field can offer
 * completions without the backend shipping 400 strings on every page load.
 */
const timezones = computed<string[]>(() => {
    const intl = Intl as typeof Intl & {
        supportedValuesOf?: (key: string) => string[];
    };

    return intl.supportedValuesOf?.('timeZone') ?? [];
});
</script>

<template>
    <div class="flex flex-col space-y-12">
        <Head title="Profile settings" />

        <h1 class="sr-only">Profile settings</h1>

        <Heading
            variant="small"
            title="Profile"
            description="How you appear to other members."
        />

        <ProfileAccountPanel
            :profile="profile"
            :must-verify-email="mustVerifyEmail"
            :email-verified="profile.is_verified"
            :status="status"
        />

        <Separator />

        <ProfileAvatarPanel :profile="profile" />

        <Separator />

        <ProfileLocationPanel :profile="profile" :timezones="timezones" />

        <Separator />

        <ProfileLanguagePanel :profile="profile" :locales="locales" />

        <Separator />

        <DeleteUser />
    </div>
</template>
