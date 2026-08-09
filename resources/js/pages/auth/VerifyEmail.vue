<script setup lang="ts">
import EmailVerificationNotificationController from '@/actions/App/Http/Controllers/Auth/EmailVerificationNotificationController';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { useAuthUser } from '@/composables/useAuthUser';
import { useTranslations } from '@/composables/useTranslations';
import { cn } from '@/lib/utils';
import { home, logout } from '@/routes';
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Inbox,
    LoaderCircle,
    LogOut,
    Mail,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    status?: string;
}>();

const { t } = useTranslations();
const user = useAuthUser();

const linkSent = computed(() => props.status === 'verification-link-sent');
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
            <Link
                :href="home()"
                class="mb-6 flex items-center justify-center gap-2.5"
            >
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-2xl bg-violet-500 shadow-sm shadow-violet-500/30"
                >
                    <div class="h-6 w-6 rounded-full bg-white" />
                </div>
                <span
                    class="text-xl font-bold text-gray-800 dark:text-gray-100"
                >
                    {{ t('nav.brand') }}
                </span>
            </Link>

            <div
                class="overflow-hidden rounded-2xl border border-violet-100/80 bg-white/90 shadow-xl shadow-violet-500/10 backdrop-blur-sm dark:border-violet-900/50 dark:bg-gray-900/90 dark:shadow-violet-950/40"
            >
                <div
                    class="relative overflow-hidden bg-gradient-to-br from-violet-500 via-violet-600 to-fuchsia-500 px-6 pb-10 pt-8 text-white"
                >
                    <div
                        class="pointer-events-none absolute -end-8 -top-10 size-40 rounded-full bg-white/10"
                    />
                    <div
                        class="pointer-events-none absolute -bottom-12 start-8 size-32 rounded-full bg-fuchsia-300/20"
                    />

                    <div
                        class="relative flex flex-col items-center gap-4 text-center"
                    >
                        <div
                            class="flex size-16 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur-sm"
                        >
                            <Mail class="size-8" stroke-width="1.75" />
                        </div>
                        <div class="space-y-2">
                            <h1 class="text-2xl font-bold tracking-tight">
                                {{ t('auth.verify_email_heading') }}
                            </h1>
                            <p class="max-w-sm text-sm text-violet-50/95">
                                {{ t('auth.verify_email_description') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-6 px-6 py-7 sm:px-8">
                    <div
                        v-if="user?.email"
                        class="flex items-center gap-3 rounded-xl border border-violet-100 bg-violet-50/70 px-4 py-3 dark:border-violet-900/60 dark:bg-violet-950/40"
                    >
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-600 dark:bg-violet-900/70 dark:text-violet-300"
                        >
                            <Inbox class="size-5" />
                        </div>
                        <div class="min-w-0 text-start">
                            <p
                                class="text-xs font-medium text-violet-700 dark:text-violet-300"
                            >
                                {{ t('auth.verify_email_sent_to') }}
                            </p>
                            <p
                                class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100"
                            >
                                {{ user.email }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="linkSent"
                        class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-start dark:border-emerald-900/50 dark:bg-emerald-950/40"
                        role="status"
                    >
                        <CheckCircle2
                            class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                        />
                        <p
                            class="text-sm font-medium text-emerald-800 dark:text-emerald-200"
                        >
                            {{ t('auth.verification_link_sent') }}
                        </p>
                    </div>

                    <ul class="space-y-3">
                        <li class="flex items-start gap-3 text-start">
                            <div
                                class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-950/60 dark:text-violet-300"
                            >
                                <Inbox class="size-4" />
                            </div>
                            <p class="text-muted-foreground text-sm">
                                {{ t('auth.verify_email_check_inbox') }}
                            </p>
                        </li>
                        <li class="flex items-start gap-3 text-start">
                            <div
                                class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-fuchsia-50 text-fuchsia-600 dark:bg-fuchsia-950/60 dark:text-fuchsia-300"
                            >
                                <ShieldCheck class="size-4" />
                            </div>
                            <p class="text-muted-foreground text-sm">
                                {{ t('auth.verify_email_spam_hint') }}
                            </p>
                        </li>
                    </ul>

                    <Form
                        v-bind="
                            EmailVerificationNotificationController.store.form()
                        "
                        class="space-y-4"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            :disabled="processing"
                            data-test="resend-verification-email"
                            :class="
                                cn(
                                    'h-11 w-full gap-2 bg-gradient-to-r from-violet-500 to-fuchsia-500 text-white shadow-md shadow-violet-500/25 hover:from-violet-600 hover:to-fuchsia-600',
                                )
                            "
                        >
                            <LoaderCircle
                                v-if="processing"
                                class="size-4 animate-spin"
                            />
                            <Mail v-else class="size-4" />
                            {{ t('auth.resend_verification_email') }}
                        </Button>

                        <div
                            class="border-border flex flex-col items-center gap-3 border-t pt-4 sm:flex-row sm:justify-between"
                        >
                            <TextLink
                                :href="logout()"
                                as="button"
                                class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm no-underline"
                            >
                                <LogOut class="size-3.5" />
                                {{ t('auth.log_out') }}
                            </TextLink>

                            <p
                                class="text-muted-foreground text-center text-xs"
                            >
                                {{ t('auth.verify_email_wrong_account') }}
                            </p>
                        </div>
                    </Form>
                </div>
            </div>
        </div>
    </div>
</template>
