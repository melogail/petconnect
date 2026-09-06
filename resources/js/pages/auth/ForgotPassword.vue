<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import StatusMessage from '@/components/StatusMessage.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { login } from '@/routes';
import { email } from '@/routes/password';

/**
 * Layout heading and sub-heading, snapshotted once in `setup`. See
 * `Login.vue` for why `setLayoutProps` rather than `defineOptions`, and for
 * why a locale switch would not re-run this (it is a `preserveState` visit)
 * on the day the auth shell gains a language control.
 */
const { t } = useTranslations();

setLayoutProps({
    title: t('auth.forgot_password'),
    description: t('auth.forgot_password_description'),
});

/**
 * `status` goes through `StatusMessage`, not the legacy page's bare
 * `text-green-600` paragraph. Two reasons, and the first is already settled in
 * this repo: `Login.vue` made the same substitution for the same prop before
 * this phase, and `StatusMessage.vue` carries the reasoning. Rendering the
 * pair differently would be the odd result.
 *
 * The second is measured. `green-600` is pinned in both schemes by the legacy
 * markup, and the scheme it fails is the light one, which is the opposite of
 * what a "dark mode was forgotten" reading would predict. Contrast computed
 * from Tailwind v4's own `oklch()` stops
 * (`node_modules/tailwindcss/theme.css`) converted to linear sRGB, each figure
 * against the surface `AuthSimpleLayout` actually paints — `bg-background`,
 * which `resources/css/app.css` sets to `#FFFFFF` light and `#0F1729` dark.
 * Not the `oklch(0.145 0 0)` in `app.blade.php`'s inline `html.dark` rule:
 * that paints the document behind the layout and is a different colour from
 * the token, so measuring against it would answer a question nobody asked.
 *
 * - `green-600` on `#FFFFFF` **3.22:1** — below the 4.5:1 AA floor for 14px text
 * - `green-600` on `#0F1729` **5.56:1** — passes
 *
 * So the obvious repair, adding a `dark:` variant, would have "fixed" the half
 * that already worked. `StatusMessage` sidesteps the choice: its `Alert` is
 * token-painted, so both schemes follow `--foreground`.
 */
defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="t('auth.forgot_password')" />

    <StatusMessage :status="status" />

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email">{{ t('auth.email_address') }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    autofocus
                    :placeholder="t('auth.email_placeholder')"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    {{ t('auth.email_password_reset_link') }}
                </Button>
            </div>
        </Form>

        <div class="text-muted-foreground space-x-1 text-center text-sm">
            <span>{{ t('auth.or_return_to') }}</span>
            <TextLink :href="login()">{{
                t('auth.log_in_lowercase')
            }}</TextLink>
        </div>
    </div>
</template>
