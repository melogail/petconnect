<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import SaveButton from '@/components/settings/SaveButton.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { changedProfileFields, profileIdentity } from '@/lib/profileForm';
import { update as updateProfile } from '@/routes/profile';
import { send as sendVerification } from '@/routes/verification';
import type { ProfileFormData } from '@/types';

/**
 * Name, handle and contact details.
 *
 * The identity pair is `required` on every PATCH, so it is posted here whether
 * it changed or not; everything else is sent only when it actually differs.
 */
const { profile, mustVerifyEmail, status } = defineProps<{
    profile: ProfileFormData;
    mustVerifyEmail: boolean;
    emailVerified: boolean;
    status: string | null;
}>();

const original = {
    name: profile.name,
    email: profile.email,
    username: profile.username ?? '',
    phone: profile.phone ?? '',
    bio: profile.bio ?? '',
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
        title="Account"
        description="Your name, handle and how people reach you."
    >
        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    required
                    autocomplete="name"
                    placeholder="Full name"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="username"
                    placeholder="Email address"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && !emailVerified" class="space-y-1">
                <p class="text-muted-foreground text-sm">
                    Your email address is unverified.
                    <Link
                        :href="sendVerification()"
                        as="button"
                        class="text-foreground underline underline-offset-4"
                    >
                        Re-send the verification email.
                    </Link>
                </p>
                <p
                    v-if="status === 'verification-link-sent'"
                    class="text-sm font-medium text-green-600"
                >
                    A new verification link has been sent to your email address.
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="username">Username</Label>
                <Input
                    id="username"
                    v-model="form.username"
                    autocomplete="off"
                    placeholder="handle"
                />
                <p class="text-muted-foreground text-xs">
                    Letters, numbers, dashes and underscores. 3–50 characters.
                </p>
                <InputError :message="form.errors.username" />
            </div>

            <div class="grid gap-2">
                <Label for="phone">Phone</Label>
                <Input
                    id="phone"
                    v-model="form.phone"
                    autocomplete="tel"
                    placeholder="+20 100 000 0000"
                />
                <InputError :message="form.errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="bio">Bio</Label>
                <Textarea
                    id="bio"
                    v-model="form.bio"
                    rows="4"
                    placeholder="Tell people a little about yourself."
                />
                <InputError :message="form.errors.bio" />
            </div>

            <SaveButton
                :processing="form.processing"
                :recently-successful="form.recentlySuccessful"
            />
        </form>
    </SettingsPanel>
</template>
