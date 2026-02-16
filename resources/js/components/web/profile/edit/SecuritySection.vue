<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { AlertCircle, Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps({
    form: Object,
});

const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);
</script>

<template>
    <div
        class="animate-in fade-in slide-in-from-right-4 space-y-6 duration-300"
    >
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Security Settings
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Ensure your account is secure with a strong password.
            </p>
        </div>
        <Separator />

        <div
            class="space-y-4 rounded-lg bg-yellow-50 p-4 text-sm text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300"
        >
            <div class="flex items-start">
                <AlertCircle class="mr-2 h-5 w-5 flex-shrink-0" />
                <p>
                    Use a password at least 12 characters long, or a phrase with
                    at least 4 words.
                </p>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div class="col-span-2 space-y-2">
                <Label
                    for="current_password"
                    :class="{ 'text-red-500': form.errors.current_password }"
                    >Current Password</Label
                >
                <div class="relative">
                    <Input
                        id="current_password"
                        v-model="form.current_password"
                        :type="showCurrentPassword ? 'text' : 'password'"
                        class="bg-gray-50/50 pr-10 dark:bg-gray-900/20"
                    />
                    <button
                        type="button"
                        @click="showCurrentPassword = !showCurrentPassword"
                        class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
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
                    >New Password</Label
                >
                <div class="relative">
                    <Input
                        id="new_password"
                        v-model="form.new_password"
                        :type="showNewPassword ? 'text' : 'password'"
                        class="bg-gray-50/50 pr-10 dark:bg-gray-900/20"
                        :class="{ 'border-red-500': form.errors.new_password }"
                    />
                    <button
                        type="button"
                        @click="showNewPassword = !showNewPassword"
                        class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
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
                    >Confirm New Password</Label
                >
                <div class="relative">
                    <Input
                        id="confirm_password"
                        v-model="form.confirm_password"
                        :type="showConfirmPassword ? 'text' : 'password'"
                        class="bg-gray-50/50 pr-10 dark:bg-gray-900/20"
                        :class="{
                            'border-red-500': form.errors.confirm_password,
                        }"
                    />
                    <button
                        type="button"
                        @click="showConfirmPassword = !showConfirmPassword"
                        class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
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
                    <Label class="text-base font-semibold"
                        >Two-Factor Authentication</Label
                    >
                    <span
                        class="ml-2 inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-900/30 dark:text-green-400"
                        >Recommended</span
                    >
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Protect your account with an extra layer of security.
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
                        Account Status
                    </h3>
                    <p class="text-sm text-red-600 dark:text-red-400">
                        Deactivating your account will hide your public profile,
                        disable messaging/interactions, and prevent creating or
                        managing pets.
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <Label
                        for="is_active"
                        class="text-sm font-medium text-red-800 dark:text-red-300"
                    >
                        Active
                    </Label>
                    <Switch
                        id="is_active"
                        v-model="form.is_active"
                        class="data-[state=checked]:bg-green-600 data-[state=unchecked]:bg-red-600"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
