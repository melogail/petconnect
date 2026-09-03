<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import TwoFactorModeToggle from '@/components/auth/TwoFactorModeToggle.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import { store } from '@/routes/two-factor/login';

/**
 * The emergency recovery-code challenge.
 *
 * It posts to the same endpoint as `TwoFactorCodeForm` but stays a separate
 * form rather than a branch inside that one, because the two send different
 * fields: Fortify's `TwoFactorLoginRequest` looks at `code` first and only
 * falls back to `recovery_code`, so a single form carrying both would submit an
 * empty `code` alongside the recovery code and be judged on the empty one.
 */
defineProps<{
    /** The authenticator mode's name, for the toggle beneath. */
    toggleLabel: string;
}>();

defineEmits<{
    toggle: [];
}>();

const { t } = useTranslations();
</script>

<template>
    <Form
        v-bind="store.form()"
        class="space-y-4"
        reset-on-error
        #default="{ errors, processing }"
    >
        <Input
            name="recovery_code"
            type="text"
            :placeholder="t('auth.recovery_code_placeholder')"
            autofocus
            required
        />
        <InputError :message="errors.recovery_code" />

        <Button type="submit" class="w-full" :disabled="processing">
            {{ t('auth.continue') }}
        </Button>

        <TwoFactorModeToggle :label="toggleLabel" @toggle="$emit('toggle')" />
    </Form>
</template>
