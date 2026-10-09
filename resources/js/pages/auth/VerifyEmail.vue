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
 * It owns its whole viewport — `min-h-svh`, the lavender `bg-background` page
 * with no decoration on it, its own brand lockup — so `app.ts` gives
 * `auth/VerifyEmail` an empty shell the
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
 *
 * The "Aubergine & Lavender" restyle removed the radial violet/fuchsia wash and
 * the two blurred orbs that used to float behind the card: violet is the one
 * accent, and cards lift on a neutral shadow, not on tinted glows. The card is
 * therefore opaque `bg-card` with a hairline `border-border` and
 * `shadow-popover` — no `backdrop-blur`, because there is nothing behind it to
 * blur any more.
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

        <div class="relative z-10 w-full max-w-lg">
            <AuthBrandLockup class="mb-6" />

            <div
                class="bg-card border-border shadow-popover overflow-hidden rounded-[20px] border"
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
