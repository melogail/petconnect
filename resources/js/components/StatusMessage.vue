<script setup lang="ts">
import { Info } from '@lucide/vue';
import { Alert, AlertDescription } from '@/components/ui/alert';

/**
 * A `status` session flash, rendered as an explanation rather than a success
 * note.
 *
 * The login page's `status` is not always good news: `EnsureAccountIsActive`
 * ends the session of a deactivated account and redirects to `login` with
 * "Your account has been deactivated." here. Rendered in success green, that
 * read as confirmation of something the user had not done; the same slot also
 * carries Fortify's password-reset confirmations, so the tone is neutral.
 */
defineProps<{ status?: string | null }>();
</script>

<template>
    <Alert v-if="status" class="mb-6">
        <Info class="size-4" />
        <AlertDescription>{{ status }}</AlertDescription>
    </Alert>
</template>
