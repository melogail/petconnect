<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Upload } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import SaveButton from '@/components/settings/SaveButton.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { profileIdentity } from '@/lib/profileForm';
import { update as updateProfile } from '@/routes/profile';
import type { ProfileFormData } from '@/types';

/**
 * The avatar.
 *
 * Read and write names differ on purpose: the payload's `avatar` is a URL and
 * is read-only, while the upload key is `image`. Posting `avatar` back would
 * hand a string to a file rule.
 *
 * A file makes the request multipart, so Inertia spoofs the PATCH as a POST
 * with `_method`. Only the chosen file and the required identity pair are sent
 * — nothing else in this panel is a field.
 */
const { profile } = defineProps<{ profile: ProfileFormData }>();

const form = useForm<{ image: File | null }>({ image: null });

const preview = ref<string | null>(null);

const shownAvatar = computed(() => preview.value ?? profile.avatar);

function pick(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    form.image = file;

    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }

    preview.value = file ? URL.createObjectURL(file) : null;
}

function submit(): void {
    form.transform((data) => ({
        ...profileIdentity(profile),
        image: data.image,
    })).submit(updateProfile(), { preserveScroll: true });
}
</script>

<template>
    <SettingsPanel
        title="Photo"
        description="A square image works best. JPG, PNG, GIF or WEBP."
    >
        <form class="space-y-6" @submit.prevent="submit">
            <div class="flex items-center gap-4">
                <UserAvatar
                    :name="profile.name"
                    :avatar="shownAvatar"
                    class="size-16"
                />

                <div class="grid gap-2">
                    <Button
                        as="label"
                        for="avatar"
                        variant="outline"
                        class="cursor-pointer"
                    >
                        <Upload class="size-4" />
                        Choose a photo
                    </Button>
                    <!--
                        A native input: the ui `Input` binds `v-model`, which
                        Vue refuses on a file input.
                    -->
                    <input
                        id="avatar"
                        type="file"
                        accept="image/jpeg,image/png,image/gif,image/webp"
                        class="sr-only"
                        @change="pick"
                    />
                    <p v-if="form.image" class="text-muted-foreground text-xs">
                        {{ form.image.name }}
                    </p>
                </div>
            </div>

            <InputError :message="form.errors.image" />

            <SaveButton
                :processing="form.processing"
                :recently-successful="form.recentlySuccessful"
                :disabled="form.image === null"
                label="Upload photo"
            />
        </form>
    </SettingsPanel>
</template>
