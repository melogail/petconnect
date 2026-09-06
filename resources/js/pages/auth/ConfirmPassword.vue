<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { confirm as confirmStore, confirmOptions } from '@/routes/passkey';
import { store } from '@/routes/password/confirm';

/**
 * Layout heading and sub-heading, snapshotted once in `setup`. See
 * `Login.vue` for why `setLayoutProps` rather than `defineOptions`, and for
 * why a locale switch would not re-run this (it is a `preserveState` visit)
 * on the day the auth shell gains a language control.
 */
const { t } = useTranslations();

setLayoutProps({
    title: t('auth.confirm_your_password'),
    description: t('auth.confirm_password_description'),
});
</script>

<template>
    <Head :title="t('auth.confirm_password')" />

    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        :label="t('auth.confirm_with_passkey')"
        :loading-label="t('auth.confirming')"
        :separator="t('auth.or_confirm_with_password')"
    />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label htmlFor="password">{{ t('auth.password') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                />

                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ t('auth.confirm_password_button') }}
                </Button>
            </div>
        </div>
    </Form>
</template>
