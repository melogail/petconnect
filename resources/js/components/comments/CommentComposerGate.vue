<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { login } from '@/routes';
import { notice as verifyEmail } from '@/routes/verification';

/**
 * A comment composer, or the reason this reader cannot use one.
 *
 * `comments.store` is `auth` **+ `verified`**, so "signed in" is not the
 * predicate: an unverified reader who submits is answered with a redirect to
 * `verification.notice`, which is a different page component. Inertia therefore
 * discards the partial, navigates away, closes the comments dialog around it,
 * and — on `Home`, whose `pets` is an `Inertia::scroll()` merge prop — drops a
 * reader who had scrolled to page 4 back to page 1. Nothing errors; the comment
 * is simply never written and the page they were reading is gone.
 *
 * So the predicate is `email_verified_at`, which is what
 * `components/pets/CreatePetButton.vue` already uses for the same reason. The
 * `canInteract` prop threaded through the comment components is
 * `Boolean(auth.user)` and pre-dates this vertical; it still gates the like and
 * reply controls, which are their own gate to fix.
 *
 * The composer is a **slot**, not a set of forwarded props: a reply composer
 * takes a parent id, a placeholder and focus that a thread composer does not,
 * and each caller already owns that wiring plus its own `posted` handler. This
 * component owns one decision.
 *
 * `auth.user` is typed non-nullable and is null for a guest (see
 * `.ai/rules/types.md`), which is why it is narrowed here rather than read
 * through.
 */
const page = usePage();

const { t } = useTranslations();

const viewer = computed(() => page.props.auth.user ?? null);

const canComment = computed(() => viewer.value?.email_verified_at != null);
</script>

<template>
    <slot v-if="canComment" />

    <p v-else class="text-muted-foreground text-sm">
        <Link
            :href="viewer ? verifyEmail() : login()"
            class="text-primary underline"
        >
            {{ viewer ? t('auth.verify_email') : t('auth.log_in') }}
        </Link>
        —
        {{
            viewer
                ? t('comments.verify_to_comment')
                : t('comments.sign_in_to_comment')
        }}
    </p>
</template>
