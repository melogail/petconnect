<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import SaveButton from '@/components/settings/SaveButton.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { changedProfileFields, profileIdentity } from '@/lib/profileForm';
import { update as updateProfile } from '@/routes/profile';
import type { ProfileFormData, SelectOption } from '@/types';

/**
 * The language preference.
 *
 * Saving it runs `Pipelines\Profiles\UpdateProfile\ApplyLocalePreference`, which
 * writes `users.locale` and re-queues the plaintext `locale` cookie. The `dir`
 * on `<html>` follows the redirect's `locale` shared prop — see
 * `lib/localeDirection.ts`.
 */
const { profile, locales } = defineProps<{
    profile: ProfileFormData;
    locales: SelectOption[];
}>();

const original = { locale: profile.locale };

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
        title="Language"
        description="The language PetConnect speaks to you in."
    >
        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="locale">Language</Label>
                <Select v-model="form.locale">
                    <SelectTrigger id="locale" class="w-full">
                        <SelectValue placeholder="Pick a language" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in locales"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.locale" />
            </div>

            <SaveButton
                :processing="form.processing"
                :recently-successful="form.recentlySuccessful"
            />
        </form>
    </SettingsPanel>
</template>
