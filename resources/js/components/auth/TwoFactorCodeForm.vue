<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import TwoFactorModeToggle from '@/components/auth/TwoFactorModeToggle.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { useTranslations } from '@/composables/useTranslations';
import { store } from '@/routes/two-factor/login';

/**
 * The six-digit authenticator challenge.
 *
 * The typed digits are local state, and deliberately so: the page swaps this
 * form out for the recovery one with `v-if`, which unmounts it, so switching
 * modes and switching back starts from an empty field and an empty error bag
 * without anything having to clear them. The legacy page kept the code in the
 * page and reset it by hand in the toggle handler, and had to thread the form's
 * `clearErrors` through that handler to do it.
 *
 * `InputOTP` is bound to a hidden `code` field rather than being the field
 * itself, because the visible control is six separate slots and the request
 * takes one string.
 */
defineProps<{
    /** The recovery mode's name, for the toggle beneath. */
    toggleLabel: string;
}>();

defineEmits<{
    toggle: [];
}>();

const { t } = useTranslations();

const code = ref<string>('');
</script>

<template>
    <Form
        v-bind="store.form()"
        class="space-y-4"
        reset-on-error
        @error="code = ''"
        #default="{ errors, processing }"
    >
        <input type="hidden" name="code" :value="code" />
        <div
            class="flex flex-col items-center justify-center space-y-3 text-center"
        >
            <div class="flex w-full items-center justify-center">
                <InputOTP
                    id="otp"
                    v-model="code"
                    :maxlength="6"
                    :disabled="processing"
                    autofocus
                >
                    <InputOTPGroup>
                        <InputOTPSlot
                            v-for="index in 6"
                            :key="index"
                            :index="index - 1"
                        />
                    </InputOTPGroup>
                </InputOTP>
            </div>
            <InputError :message="errors.code" />
        </div>

        <Button type="submit" class="w-full" :disabled="processing">
            {{ t('auth.continue') }}
        </Button>

        <TwoFactorModeToggle :label="toggleLabel" @toggle="$emit('toggle')" />
    </Form>
</template>
