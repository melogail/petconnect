<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import { Camera, Upload } from "@lucide/vue";
import { computed, ref } from "vue";
import InputError from "@/components/InputError.vue";
import SaveButton from "@/components/settings/SaveButton.vue";
import { Button } from "@/components/ui/button";
import UserAvatar from "@/components/UserAvatar.vue";
import { profileIdentity } from "@/lib/profileForm";
import { update as updateProfile } from "@/routes/profile";
import type { ProfileFormData } from "@/types";

/**
 * The photo card at the head of the profile settings page: a large avatar in
 * a brand-gradient ring with a camera badge that opens the picker, the
 * member's name and handle beside it, and the upload controls only once a file
 * is chosen. Posts `image` plus the identity pair; the preview is a local
 * object URL until the redirect brings back the stored `avatar`.
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

function cancel(): void {
    form.reset();
    form.clearErrors();

    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }

    preview.value = null;
}

function submit(): void {
    form.transform((data) => ({
        ...profileIdentity(profile),
        image: data.image,
    })).submit(updateProfile(), {
        preserveScroll: true,
        onSuccess: cancel,
    });
}
</script>

<template>
    <section
        class="border-border bg-card shadow-card relative overflow-hidden rounded-[20px] border"
    >
        <div class="bg-primary bg-gradient-brand h-1.5" aria-hidden="true" />

        <form
            class="flex flex-col items-center gap-6 p-6 text-center sm:flex-row sm:items-center sm:text-start"
            @submit.prevent="submit"
        >
            <label
                for="avatar"
                class="group focus-within:ring-ring focus-within:ring-offset-background relative shrink-0 cursor-pointer rounded-full outline-none focus-within:ring-2 focus-within:ring-offset-2"
            >
                <span
                    class="bg-primary bg-gradient-brand block rounded-full p-1 transition-transform duration-200 ease-out group-hover:scale-[1.02]"
                >
                    <UserAvatar
                        :name="profile.name"
                        :avatar="shownAvatar"
                        class="ring-card size-28 text-3xl ring-4"
                    />
                </span>
                <span
                    class="bg-primary text-primary-foreground border-card shadow-card absolute end-1 bottom-1 grid size-9 place-content-center rounded-full border-2 transition-transform duration-200 ease-out group-hover:scale-105"
                >
                    <Camera class="size-4" aria-hidden="true" />
                </span>
                <span class="sr-only">Change photo</span>
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
            </label>

            <div class="min-w-0 flex-1 space-y-1">
                <h2
                    class="truncate font-serif text-[24px] leading-[1.15] font-medium tracking-[-0.01em]"
                >
                    {{ profile.name }}
                </h2>
                <p class="text-muted-foreground truncate text-sm">
                    <template v-if="profile.username">
                        @{{ profile.username }}
                    </template>
                    <template v-else>{{ profile.email }}</template>
                </p>
                <p class="text-soft-foreground pt-2 text-xs leading-relaxed">
                    A square image works best. JPG, PNG, GIF or WEBP.
                </p>
                <InputError :message="form.errors.image" />

                <div
                    class="flex flex-wrap items-center justify-center gap-2 pt-3 sm:justify-start"
                >
                    <template v-if="form.image">
                        <SaveButton
                            :processing="form.processing"
                            :recently-successful="form.recentlySuccessful"
                            label="Save photo"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            :disabled="form.processing"
                            @click="cancel"
                        >
                            Cancel
                        </Button>
                    </template>
                    <Button
                        v-else
                        as="label"
                        for="avatar"
                        variant="outline"
                        class="cursor-pointer"
                    >
                        <Upload class="size-4" aria-hidden="true" />
                        Choose a photo
                    </Button>
                </div>
            </div>
        </form>
    </section>
</template>
