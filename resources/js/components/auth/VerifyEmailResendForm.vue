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
 * ## The gradient button's rest state fails AA, and ships anyway
 *
 * This is the application's second violet→fuchsia CTA. Contrast computed the
 * same way as the first — from Tailwind v4's own token values
 * (`node_modules/tailwindcss/theme.css`), converting the `oklch()` stops to
 * linear sRGB and applying the WCAG relative-luminance formula — each figure
 * naming the pair it was measured against:
 *
 * - rest, white on `violet-500` **4.40:1**, on `fuchsia-500` **3.53:1**
 * - hover, white on `violet-600` **5.88:1**, on `fuchsia-600` **4.66:1**
 *
 * The label is 14px/500, so the floor is 4.5:1 and the rest state is under it
 * at both ends. The gradient runs left→right, so the worse stop sits under the
 * *end* of the label rather than at an edge nobody reads.
 *
 * **This ships anyway, and that is a ruling, not an omission.** The user
 * decided to carry the legacy gradient over exactly as it is: the rest state
 * does not move to the 600 stops and the colours do not change.
 * `components/pets/CreatePetButton.vue` carries the long form of this note —
 * including what the numbers depend on (both stops clip out of the sRGB gamut,
 * so a different out-of-gamut strategy lands on different ratios) and why the
 * decision must not be generalised to anything else in the port. Read it
 * before touching either; if the ruling is ever revisited, both change
 * together.
 *
 * `text-white` is pinned for the reason recorded there too: the default
 * variant's `text-primary-foreground` flips between schemes while this
 * background, being two fixed colour-scale stops, does not.
 */
const { t } = useTranslations();
</script>

<template>
    <Form v-bind="send.form()" class="space-y-4" v-slot="{ processing }">
        <Button
            type="submit"
            :disabled="processing"
            data-test="resend-verification-email"
            class="h-11 w-full gap-2 bg-linear-to-r from-violet-500 to-fuchsia-500 text-white shadow-md shadow-violet-500/25 hover:from-violet-600 hover:to-fuchsia-600"
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
