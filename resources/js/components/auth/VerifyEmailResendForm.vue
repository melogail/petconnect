<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { LogOut, Mail } from '@lucide/vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

/**
 * "Resend verification email", plus the way out for someone signed in as the
 * wrong person.
 *
 * The log-out link sits inside the form element but is not a submit control —
 * `TextLink` renders an Inertia `<Link as="button">`, which is `type="button"`,
 * so it posts `logout` itself rather than triggering this form. It is kept in
 * the same box because the footer row is part of the same bordered block in the
 * design.
 *
 * ## The button is the plain `default` variant, and it clears AA at rest
 *
 * This used to be the application's second violet→fuchsia gradient CTA, and
 * it shipped with a rest state under the 4.5:1 floor (white on `violet-500`
 * 4.40:1, on `fuchsia-500` 3.53:1) as a deliberate carry-over from the legacy
 * screen. The "Aubergine & Lavender" restyle revisited that ruling for the
 * whole port at once — one accent, no gradients, one solid button per surface
 * — and its §5.1 retires this and `components/pets/CreatePetButton.vue` in the
 * same breath, as the old note said they must go together. This one is now
 * `variant="default"`, the single solid violet with `shadow-cta`.
 *
 * Contrast from the hex values in `resources/css/app.css`, WCAG relative-
 * luminance formula, each figure naming its pair:
 *
 * - light rest, white on `--primary` `#5A3FE0` **6.52:1**; hover
 *   `--primary-700` `#4A32C4` **8.25:1**
 * - dark rest, `--primary-foreground` `#15102A` on `--primary` `#A78BFF`
 *   **6.83:1**; hover `--primary-500` `#7C5CF2` **4.10:1** — under the floor
 *   for the 14px label, but hover is a transient state and the variant is
 *   owned by `components/ui/button`, not pinned here
 *
 * The label is 14px/600. `text-white` is no longer pinned: the variant's
 * `text-primary-foreground` flips with the scheme, and so does the background
 * it sits on, which is what the pin used to work around.
 */
const { t } = useTranslations();
</script>

<template>
    <Form v-bind="send.form()" class="space-y-4" v-slot="{ processing }">
        <Button
            type="submit"
            variant="default"
            :disabled="processing"
            data-test="resend-verification-email"
            class="h-11 w-full gap-2"
        >
            <Spinner v-if="processing" />
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

            <p class="text-muted-foreground text-center text-xs">
                {{ t('auth.verify_email_wrong_account') }}
            </p>
        </div>
    </Form>
</template>
