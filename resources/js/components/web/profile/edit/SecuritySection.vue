<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { AlertCircle, Eye, EyeOff, TriangleAlert } from 'lucide-vue-next';
import { ref } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps<{
    form: Record<string, any>;
    userId: number;
}>();

const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

const isDeleteDialogOpen = ref(false);
const isDeleting = ref(false);

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route('profile.destroy', { user: props.userId }), {
        onFinish: () => {
            isDeleting.value = false;
            isDeleteDialogOpen.value = false;
        },
    });
};
</script>

<template>
    <div class="animate-in fade-in slide-in-from-end-4 space-y-6 duration-300">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ t('profile.security_settings') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ t('profile.security_settings_desc') }}
            </p>
        </div>
        <Separator />

        <div
            class="space-y-4 rounded-lg bg-yellow-50 p-4 text-sm text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300"
        >
            <div class="flex items-start">
                <AlertCircle class="me-2 h-5 w-5 flex-shrink-0" />
                <p>
                    {{ t('profile.password_requirements') }}
                </p>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div class="col-span-2 space-y-2">
                <Label
                    for="current_password"
                    :class="{ 'text-red-500': form.errors.current_password }"
                    >{{ t('profile.current_password') }}</Label
                >
                <div class="relative">
                    <Input
                        id="current_password"
                        v-model="form.current_password"
                        :type="showCurrentPassword ? 'text' : 'password'"
                        class="bg-gray-50/50 pe-10 dark:bg-gray-900/20"
                    />
                    <button
                        type="button"
                        @click="showCurrentPassword = !showCurrentPassword"
                        class="absolute end-3 top-1/2 -translate-y-1/2 cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <Eye v-if="!showCurrentPassword" class="h-4 w-4" />
                        <EyeOff v-else class="h-4 w-4" />
                    </button>
                </div>
                <p v-if="form.errors.current_password" class="text-red-500">
                    {{ form.errors.current_password }}
                </p>
            </div>
            <div class="space-y-2">
                <Label
                    for="new_password"
                    :class="{ 'text-red-500': form.errors.new_password }"
                    >{{ t('profile.new_password') }}</Label
                >
                <div class="relative">
                    <Input
                        id="new_password"
                        v-model="form.new_password"
                        :type="showNewPassword ? 'text' : 'password'"
                        class="bg-gray-50/50 pe-10 dark:bg-gray-900/20"
                        :class="{ 'border-red-500': form.errors.new_password }"
                    />
                    <button
                        type="button"
                        @click="showNewPassword = !showNewPassword"
                        class="absolute end-3 top-1/2 -translate-y-1/2 cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <Eye v-if="!showNewPassword" class="h-4 w-4" />
                        <EyeOff v-else class="h-4 w-4" />
                    </button>
                </div>
                <p v-if="form.errors.new_password" class="text-red-500">
                    {{ form.errors.new_password }}
                </p>
            </div>
            <div class="space-y-2">
                <Label
                    for="confirm_password"
                    :class="{ 'text-red-500': form.errors.confirm_password }"
                    >{{ t('profile.confirm_new_password') }}</Label
                >
                <div class="relative">
                    <Input
                        id="confirm_password"
                        v-model="form.confirm_password"
                        :type="showConfirmPassword ? 'text' : 'password'"
                        class="bg-gray-50/50 pe-10 dark:bg-gray-900/20"
                        :class="{
                            'border-red-500': form.errors.confirm_password,
                        }"
                    />
                    <button
                        type="button"
                        @click="showConfirmPassword = !showConfirmPassword"
                        class="absolute end-3 top-1/2 -translate-y-1/2 cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <Eye v-if="!showConfirmPassword" class="h-4 w-4" />
                        <EyeOff v-else class="h-4 w-4" />
                    </button>
                </div>
            </div>
            <p v-if="form.errors.confirm_password" class="text-red-500">
                {{ form.errors.confirm_password }}
            </p>
        </div>

        <Separator class="my-6" />

        <div class="flex items-center justify-between">
            <div class="space-y-0.5">
                <div class="flex items-center">
                    <Label class="text-base font-semibold">{{
                        t('profile.two_factor_authentication')
                    }}</Label>
                    <span
                        class="ms-2 inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-900/30 dark:text-green-400"
                        >{{ t('profile.recommended') }}</span
                    >
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ t('profile.two_factor_desc') }}
                </p>
            </div>
            <Switch v-model="form.two_factor_enabled" />
        </div>

        <Separator class="my-6" />

        <div
            class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-900/20"
        >
            <div class="flex items-start justify-between">
                <div class="space-y-1">
                    <h3
                        class="text-sm font-medium text-red-800 dark:text-red-300"
                    >
                        {{ t('profile.account_status') }}
                    </h3>
                    <p class="text-sm text-red-600 dark:text-red-400">
                        {{ t('profile.account_status_desc') }}
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <Label
                        for="is_active"
                        class="text-sm font-medium text-red-800 dark:text-red-300"
                    >
                        {{ t('profile.active') }}
                    </Label>
                    <Switch
                        id="is_active"
                        v-model="form.is_active"
                        class="data-[state=checked]:bg-green-600 data-[state=unchecked]:bg-red-600"
                    />
                </div>
            </div>
        </div>

        <!-- Danger Zone -->
        <Separator class="my-6" />

        <div
            class="rounded-xl border-2 border-red-300 bg-red-50 p-6 dark:border-red-800 dark:bg-red-950/30"
        >
            <div class="mb-4 flex items-center gap-2">
                <TriangleAlert class="h-5 w-5 text-red-600 dark:text-red-400" />
                <h3
                    class="text-base font-semibold text-red-700 dark:text-red-400"
                >
                    {{ t('profile.danger_zone') }}
                </h3>
            </div>

            <div class="flex items-start justify-between gap-4">
                <div class="space-y-1">
                    <p
                        class="text-sm font-medium text-red-800 dark:text-red-300"
                    >
                        {{ t('profile.delete_account') }}
                    </p>
                    <p class="text-sm text-red-600 dark:text-red-400">
                        {{ t('profile.delete_account_desc') }}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="destructive"
                    class="shrink-0"
                    @click="isDeleteDialogOpen = true"
                >
                    {{ t('profile.delete_account') }}
                </Button>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle
                        class="flex items-center gap-2 text-red-600 dark:text-red-400"
                    >
                        <TriangleAlert class="h-5 w-5" />
                        {{ t('profile.delete_account') }}
                    </DialogTitle>
                    <DialogDescription>
                        This will permanently delete your account, all your pet
                        listings, messages, reviews, and any other data
                        associated with your profile. This action
                        <strong>cannot be undone</strong>.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isDeleteDialogOpen = false"
                    >
                        {{ t('profile.cancel') }}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="isDeleting"
                        @click="confirmDelete"
                    >
                        {{
                            isDeleting
                                ? t('profile.deleting')
                                : t('profile.yes_delete_my_account')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
