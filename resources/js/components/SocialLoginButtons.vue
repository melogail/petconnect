<script setup lang="ts">
import { Separator } from '@/components/ui/separator';
import { useTranslations } from '@/composables/useTranslations';
import { redirect } from '@/routes/social';
import type { SocialProviderOption } from '@/types/auth';

/**
 * "Continue with Google" / "Continue with Facebook".
 *
 * `providers` is whatever the server found credentials for
 * (App\Enums\SocialProvider::configuredOptions()); an empty list renders
 * nothing, so an environment with no OAuth app configured shows the plain
 * email form and nothing else.
 *
 * Each button is a plain `<a>`, deliberately not an Inertia `<Link>`: the
 * target leaves the site for the provider's consent screen, which has to be
 * a top-level navigation, and an Inertia visit would XHR the redirect and
 * choke on the cross-origin answer. A plain anchor is also what keeps the
 * route out of Inertia's prefetching, which is the reason the server side
 * can tolerate a GET callback that writes (Web\SocialAuthController).
 */
const props = defineProps<{
    providers: SocialProviderOption[];
    separator?: string;
}>();

const { t } = useTranslations();

/**
 * The `outline` button treatment from `components/ui/button`, restated on an
 * anchor: `<Button as="a">` would route through Reka's primitive, and a plain
 * anchor is the whole point here.
 */
const buttonClass =
    'border-border bg-card text-foreground hover:bg-accent hover:text-accent-foreground focus-visible:ring-ring focus-visible:ring-offset-background inline-flex h-11 w-full items-center justify-center gap-2 rounded-[14px] border px-4 text-sm font-semibold outline-none transition-[background-color,border-color,box-shadow,color] duration-200 ease-out focus-visible:ring-2 focus-visible:ring-offset-2';
</script>

<template>
    <div v-if="props.providers.length > 0">
        <div class="grid gap-3">
            <a
                v-for="provider in props.providers"
                :key="provider.value"
                :href="redirect({ provider: provider.value }).url"
                :class="buttonClass"
                :data-test="`social-login-${provider.value}`"
            >
                <svg
                    v-if="provider.value === 'google'"
                    class="size-4 shrink-0"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        fill="#4285F4"
                        d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47a5.53 5.53 0 0 1-2.4 3.63v3h3.87c2.27-2.09 3.55-5.17 3.55-8.87z"
                    />
                    <path
                        fill="#34A853"
                        d="M12 24c3.24 0 5.95-1.08 7.94-2.91l-3.87-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96H1.29v3.09A12 12 0 0 0 12 24z"
                    />
                    <path
                        fill="#FBBC05"
                        d="M5.27 14.29A7.2 7.2 0 0 1 4.89 12c0-.8.14-1.57.38-2.29V6.62H1.29A12 12 0 0 0 0 12c0 1.94.46 3.77 1.29 5.38l3.98-3.09z"
                    />
                    <path
                        fill="#EA4335"
                        d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.43-3.43C17.95 1.19 15.24 0 12 0A12 12 0 0 0 1.29 6.62l3.98 3.09C6.22 6.86 8.87 4.75 12 4.75z"
                    />
                </svg>
                <svg
                    v-else-if="provider.value === 'facebook'"
                    class="size-4 shrink-0"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        fill="#1877F2"
                        d="M24 12a12 12 0 1 0-13.88 11.85v-8.38H7.08V12h3.04V9.36c0-3.01 1.79-4.67 4.53-4.67 1.31 0 2.69.23 2.69.23v2.96h-1.52c-1.49 0-1.96.93-1.96 1.88V12h3.33l-.53 3.47h-2.8v8.38A12 12 0 0 0 24 12z"
                    />
                </svg>
                {{ t('auth.continue_with', { provider: provider.label }) }}
            </a>
        </div>

        <div class="relative my-6">
            <div class="absolute inset-0 flex items-center">
                <Separator class="w-full" />
            </div>
            <div class="relative flex justify-center text-xs uppercase">
                <span class="bg-background text-muted-foreground px-2">
                    {{ props.separator ?? t('auth.or_continue_with_email') }}
                </span>
            </div>
        </div>
    </div>
</template>
