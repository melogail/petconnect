<script setup lang="ts">
import { ref, computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Separator } from '@/components/ui/separator';
import { Button } from '@/components/ui/button';
import { Camera, X } from 'lucide-vue-next';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const { t } = useTranslations();

const props = defineProps({
    form: Object,
    profileImage: {
        type: String,
        default: null,
    },
});

const imagePreview = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const displayAvatar = computed(() => {
    return imagePreview.value || props.profileImage;
});

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        props.form.profile_image = file;
        imagePreview.value = URL.createObjectURL(file);
    }
};

const removeImage = () => {
    props.form.profile_image = null;
    imagePreview.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const triggerFileInput = () => {
    fileInput.value?.click();
};
</script>

<template>
    <div class="animate-in fade-in slide-in-from-end-4 space-y-6 duration-300">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ t('profile.basic_information') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ t('profile.update_personal_details') }}
            </p>
        </div>
        <Separator />

        <!-- Profile Image Upload -->
        <div class="flex items-center gap-6">
            <div class="relative">
                <div
                    class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-full border-2 border-dashed border-gray-300 bg-gray-100 dark:border-gray-600 dark:bg-gray-700"
                >
                    <img
                        v-if="displayAvatar"
                        :src="displayAvatar"
                        :alt="t('profile.profile_preview_alt')"
                        class="h-full w-full object-cover"
                    />
                    <Camera
                        v-else
                        class="h-8 w-8 text-gray-400 dark:text-gray-500"
                    />
                </div>
                <button
                    v-if="imagePreview"
                    type="button"
                    @click="removeImage"
                    class="absolute -end-1 -top-1 rounded-full bg-red-500 p-1 text-white shadow-md hover:bg-red-600"
                >
                    <X class="h-3 w-3" />
                </button>
            </div>
            <div class="space-y-2">
                <Label
                    for="feature_image"
                    :class="{ 'text-red-500': form.errors.feature_image }"
                    >{{ t('profile.profile_photo') }}</Label
                >
                <input
                    ref="fileInput"
                    id="feature_image"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    @change="handleFileChange"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="triggerFileInput"
                >
                    <Camera class="me-2 h-4 w-4" />
                    {{
                        displayAvatar
                            ? t('profile.change_photo')
                            : t('profile.upload_photo')
                    }}
                </Button>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ t('profile.photo_requirements') }}
                </p>
                <p
                    v-if="form.errors.feature_image"
                    class="text-sm text-red-500"
                >
                    {{ form.errors.feature_image }}
                </p>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
                <Label
                    for="name"
                    :class="{ 'text-red-500': form.errors.name }"
                    >{{ t('profile.full_name') }}</Label
                >
                <Input
                    id="name"
                    v-model="form.name"
                    class="bg-gray-50/50 dark:bg-gray-900/20"
                    :class="form.errors.name ? 'border-red-500' : ''"
                />
                <p v-if="form.errors.name" class="text-sm text-red-500">
                    {{ form.errors.name }}
                </p>
            </div>
            <div class="space-y-2">
                <Label
                    for="email"
                    :class="{ 'text-red-500': form.errors.email }"
                    >{{ t('profile.email_address') }}</Label
                >
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="bg-gray-50/50 dark:bg-gray-900/20"
                    :class="form.errors.email ? 'border-red-500' : ''"
                />
                <p v-if="form.errors.email" class="text-sm text-red-500">
                    {{ form.errors.email }}
                </p>
            </div>
            <div class="space-y-2">
                <Label
                    for="phone"
                    :class="{ 'text-red-500': form.errors.phone }"
                    >{{ t('profile.phone_number') }}</Label
                >
                <Input
                    id="phone"
                    v-model="form.phone"
                    type="tel"
                    class="bg-gray-50/50 dark:bg-gray-900/20"
                    :class="form.errors.phone ? 'border-red-500' : ''"
                />
                <p v-if="form.errors.phone" class="text-sm text-red-500">
                    {{ form.errors.phone }}
                </p>
            </div>
            <div class="col-span-2 space-y-2">
                <div class="space-y-2">
                    <Label
                        for="locale"
                        :class="{ 'text-red-500': form.errors.locale }"
                        >{{ t('profile.language_preference') }}</Label
                    >
                    <Select
                        v-model="form.locale"
                        :class="{ 'border-red-500': form.errors.locale }"
                    >
                        <SelectTrigger
                            class="bg-gray-50/50 dark:bg-gray-900/20"
                        >
                            <SelectValue
                                :placeholder="t('profile.select_language')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="en">{{
                                t('profile.english')
                            }}</SelectItem>
                            <SelectItem value="ar">{{
                                t('profile.arabic')
                            }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.locale" class="text-sm text-red-500">
                        {{ form.errors.locale }}
                    </p>
                </div>
            </div>

            <div class="col-span-2 space-y-2">
                <div class="flex items-center justify-between">
                    <Label
                        for="bio"
                        :class="{ 'text-red-500': form.errors.bio }"
                        >{{ t('profile.bio') }}</Label
                    >
                    <span class="text-xs text-gray-500">
                        {{ form.bio?.length ?? 0 }} / 500
                    </span>
                </div>
                <Textarea
                    id="bio"
                    v-model="form.bio"
                    rows="4"
                    maxlength="500"
                    :class="{ 'border-red-500': form.errors.bio }"
                    class="resize-none bg-gray-50/50 dark:bg-gray-900/20"
                />
                <p class="text-[0.8rem] text-gray-500 dark:text-gray-400">
                    {{ t('profile.bio_help') }}
                </p>
                <p v-if="form.errors.bio" class="text-sm text-red-500">
                    {{ form.errors.bio }}
                </p>
            </div>
        </div>
    </div>
</template>
