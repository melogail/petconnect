<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthBrandLockup from '@/components/auth/AuthBrandLockup.vue';
import VerificationLinkSentNotice from '@/components/auth/VerificationLinkSentNotice.vue';
import VerifyEmailAddress from '@/components/auth/VerifyEmailAddress.vue';
import VerifyEmailHero from '@/components/auth/VerifyEmailHero.vue';
import VerifyEmailHints from '@/components/auth/VerifyEmailHints.vue';
import VerifyEmailResendForm from '@/components/auth/VerifyEmailResendForm.vue';
import { useTranslations } from '@/composables/useTranslations';

/**
 * The verification notice, and the one auth screen that is not a card in
 * `AuthLayout`.
 *
 * It owns its whole viewport — `min-h-svh`, its own radial-gradient wash, its
 * own brand lockup — so `app.ts` gives `auth/VerifyEmail` an empty shell the
 * way `Welcome` gets one, rather than the `auth/*` fallthrough that wraps every
 * other page here in `AuthSimpleLayout`. Wrapped, it rendered a heading and a
 * logo above a second, larger heading and a second logo. `RootLayout` still
 * wraps it, so the single `<Toaster />` is not lost.
 *
 * The address comes from `auth.user`, guarded with `?? null` even though this
 * route sits behind the `auth` middleware and a user is therefore present.
 * `.ai/rules/types.md`: `auth.user` is typed non-nullable and is null for a
 * guest, and `vue-tsc` will not catch the difference — the chip is simply
 * omitted rather than throwing if that ever changes.
 *
 * The emerald panel is keyed to the exact string Fortify flashes,
 * `'verification-link-sent'`, not to the mere presence of `status`.
 */
const page = usePage();

const { t } = useTranslations();

defineProps<{
    status?: string;
}>();

const email = computed(() => (page.props.auth.user ?? null)?.email ?? null);
</script>

<template>
    <div
        class="relative flex min-h-svh items-center justify-center overflow-hidden px-4 py-10 sm:px-6"
    >
        <Head :title="t('auth.email_verification')" />

        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(124,58,237,0.14),_transparent_55%),radial-gradient(ellipse_at_bottom_right,_rgba(217,70,239,0.12),_transparent_45%)] dark:bg-[radial-gradient(ellipse_at_top,_rgba(167,139,250,0.18),_transparent_55%),radial-gradient(ellipse_at_bottom_right,_rgba(192,132,252,0.12),_transparent_45%)]"
        />
        <div
            class="pointer-events-none absolute -start-24 top-16 size-64 rounded-full bg-violet-400/20 blur-3xl dark:bg-violet-500/10"
        />
        <div
            class="pointer-events-none absolute -end-16 bottom-10 size-72 rounded-full bg-fuchsia-400/20 blur-3xl dark:bg-fuchsia-500/10"
        />

        <div class="relative z-10 w-full max-w-lg">
            <AuthBrandLockup class="mb-6" />

            <div
                class="bg-card/90 overflow-hidden rounded-2xl border border-violet-100/80 shadow-xl shadow-violet-500/10 backdrop-blur-sm dark:border-violet-900/50 dark:shadow-violet-950/40"
            >
                <VerifyEmailHero />

                <div class="space-y-6 px-6 py-7 sm:px-8">
                    <VerifyEmailAddress v-if="email" :email="email" />

                    <VerificationLinkSentNotice
                        v-if="status === 'verification-link-sent'"
                    />

                    <VerifyEmailHints />

                    <VerifyEmailResendForm />
                </div>
            </div>
        </div>
    </div>
</template>
