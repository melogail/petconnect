<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import Heading from "@/components/Heading.vue";
import ProfilePrivacyPanel from "@/components/settings/ProfilePrivacyPanel.vue";
import type { ProfileFormData } from "@/types";

/**
 * The privacy screen. Its panel posts to `profile.update`, which answers
 * `back()`, so a save lands here rather than on the profile page.
 */
defineProps<{
    profile: ProfileFormData;
}>();
</script>

<template>
    <div class="flex flex-col space-y-12">
        <Head title="Privacy settings" />

        <!-- The document's h1 is the settings banner in `layouts/settings/Layout.vue`. -->
        <Heading
            variant="small"
            title="Privacy"
            description="What other members can see and how they may reach you."
        />

        <ProfilePrivacyPanel :profile="profile" />
    </div>
</template>
