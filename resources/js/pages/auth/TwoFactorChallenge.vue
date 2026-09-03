<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import TwoFactorCodeForm from '@/components/auth/TwoFactorCodeForm.vue';
import TwoFactorRecoveryForm from '@/components/auth/TwoFactorRecoveryForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { TwoFactorConfigContent } from '@/types';

/**
 * Two-factor login, in one of two modes.
 *
 * The heading, the sub-heading and the toggle's label all change with the mode,
 * so unlike the other five card pages this one cannot set its layout props once
 * in `setup`: `setLayoutProps` takes a snapshot, not a reactive source, so the
 * call is wrapped in `watchEffect` and re-runs whenever the mode — or the
 * catalogue behind `t()` — changes.
 *
 * Each mode is a whole component rather than a branch, so that swapping modes
 * unmounts the form that was on screen. That is what drops the half-typed code
 * and the errors from the previous attempt; nothing here clears them.
 */
const { t } = useTranslations();

const showRecoveryInput = ref<boolean>(false);

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: t('auth.recovery_code'),
            description: t('auth.recovery_code_description'),
            buttonText: t('auth.login_using_authentication_code'),
        };
    }

    return {
        title: t('auth.authentication_code'),
        description: t('auth.authentication_code_description'),
        buttonText: t('auth.login_using_recovery_code'),
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});
</script>

<template>
    <div class="space-y-6">
        <Head :title="t('auth.two_factor_authentication')" />

        <TwoFactorRecoveryForm
            v-if="showRecoveryInput"
            :toggle-label="authConfigContent.buttonText"
            @toggle="showRecoveryInput = false"
        />
        <TwoFactorCodeForm
            v-else
            :toggle-label="authConfigContent.buttonText"
            @toggle="showRecoveryInput = true"
        />
    </div>
</template>
