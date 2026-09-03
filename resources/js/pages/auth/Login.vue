<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import StatusMessage from '@/components/StatusMessage.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

/**
 * The heading and sub-heading belong to `AuthLayout`, not to this template, so
 * they travel as layout props rather than as markup.
 *
 * They are set with `setLayoutProps` rather than the `defineOptions({ layout:
 * {...} })` object the scaffold shipped, for one reason: `defineOptions` is
 * hoisted out of `setup` at compile time, so nothing inside it can call `t()`.
 * A dynamic prop outranks a static one — the layout is rendered with
 * `{ ...page.props, ...layout.props, ...dynamicProps.shared }`, dynamic last
 * (`@inertiajs/vue3/dist/index.js:636-643`) — and dynamic props are reset on
 * every non-`preserveState` visit (`:573-575`), so this is the same lifetime
 * as the static object it replaces.
 *
 * What this does **not** survive is a locale switch, and nothing on these
 * screens needs it to. Measured by running it, not by reading it: stubbing
 * `router.visit` and calling `router.post(url, data, { preserveScroll: true })`
 * — exactly `LocaleSwitcher.vue:52` — yields a visit whose `preserveState` is
 * `true`, because `post()` defaults it to `true` and `preserveScroll` does not
 * override it. On a preserving visit the Vue adapter skips
 * `resetLayoutProps()` and keeps the component `key`
 * (`@inertiajs/vue3/dist/index.js:573-579`), so `setup` does not re-run and
 * these headings would keep the strings from the catalogue they were built
 * with. That is unreachable today: `grep -rn 'LocaleSwitcher' resources/js`
 * finds one mount, `PublicHeader.vue:122`, and neither `AuthLayout` nor
 * `RootLayout` renders one. The day a language control lands on the auth
 * shell, the fix is `preserveState: false` on that control's own visit — a
 * page cannot enforce it from here.
 */
const { t } = useTranslations();

setLayoutProps({
    title: t('auth.log_in_title'),
    description: t('auth.log_in_description'),
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head :title="t('auth.log_in')" />

    <StatusMessage :status="status" />

    <PasskeyVerify
        :label="t('auth.sign_in_with_passkey')"
        :loading-label="t('auth.authenticating')"
        :separator="t('auth.or_continue_with_email')"
    />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">{{ t('auth.email_address') }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    :placeholder="t('auth.email_placeholder')"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">{{ t('auth.password') }}</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        {{ t('auth.forgot_password_link') }}
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    :placeholder="t('auth.password_placeholder')"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>{{ t('auth.remember_me') }}</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ t('auth.log_in') }}
            </Button>
        </div>

        <div class="text-muted-foreground text-center text-sm">
            {{ t('auth.no_account') }}
            <TextLink :href="register()" :tabindex="5">
                {{ t('auth.sign_up') }}
            </TextLink>
        </div>
    </Form>
</template>
