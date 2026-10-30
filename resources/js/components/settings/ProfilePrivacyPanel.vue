<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import InputError from "@/components/InputError.vue";
import SaveButton from "@/components/settings/SaveButton.vue";
import SettingsPanel from "@/components/settings/SettingsPanel.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { changedProfileFields, profileIdentity } from "@/lib/profileForm";
import { update as updateProfile } from "@/routes/profile";
import type { ProfileFormData } from "@/types";

/**
 * What the member lets other people see or do with their phone number.
 * Both flags are off by default; the number itself is edited in the Account
 * panel.
 */
const { profile } = defineProps<{
    profile: ProfileFormData;
}>();

const original = {
    show_phone: profile.show_phone,
    whatsapp_enabled: profile.whatsapp_enabled,
};

const form = useForm({ ...original });

function submit(): void {
    form.transform((data) => ({
        ...profileIdentity(profile),
        ...changedProfileFields(data, original),
    })).submit(updateProfile(), { preserveScroll: true });
}
</script>

<template>
    <SettingsPanel
        title="Phone number"
        description="Your number is only ever shown where you allow it."
    >
        <form class="space-y-6" @submit.prevent="submit">
            <div class="flex items-start gap-3">
                <Checkbox
                    id="show_phone"
                    v-model="form.show_phone"
                    class="mt-0.5"
                />
                <div class="grid gap-1">
                    <Label for="show_phone"
                        >Show my phone number on my profile</Label
                    >
                    <p class="text-muted-foreground text-xs">
                        Anyone who opens your public profile can see it.
                    </p>
                    <InputError :message="form.errors.show_phone" />
                </div>
            </div>

            <div class="flex items-start gap-3">
                <Checkbox
                    id="whatsapp_enabled"
                    v-model="form.whatsapp_enabled"
                    class="mt-0.5"
                />
                <div class="grid gap-1">
                    <Label for="whatsapp_enabled"
                        >Let people WhatsApp me from my listings</Label
                    >
                    <p class="text-muted-foreground text-xs">
                        Adds a WhatsApp button beside Message on each of your
                        listings. Needs a phone number.
                    </p>
                    <InputError :message="form.errors.whatsapp_enabled" />
                </div>
            </div>

            <SaveButton
                :processing="form.processing"
                :recently-successful="form.recentlySuccessful"
            />
        </form>
    </SettingsPanel>
</template>
