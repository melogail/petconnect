<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Send } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useMutationSurface } from '@/composables/useMutationSurface';
import { useTranslations } from '@/composables/useTranslations';
import { store as storeComment } from '@/routes/comments';
import type { CommentableType } from '@/types';

/**
 * Write a comment, or a reply to one.
 *
 * `parent_id` is what makes it a reply, and it is the only difference between
 * the two — threads are two levels deep, so a reply's parent is always a
 * top-level comment.
 *
 * `maxLength` comes from the page's `commentBounds` prop rather than a literal:
 * the bound is built from the same accessor the `max:` rule is, so the counter
 * cannot drift from the validator. Every page that mounts this composer ships
 * `commentBounds` today — `pets.show` for the inline thread, `Home` and
 * `profile.show` for the copy inside the feed's comments dialog — so the
 * counter and the `maxlength` attribute render wherever this is mounted.
 * Established 2026-09-06 by reading the `Inertia::render()` payloads of
 * `PetController::show`, `HomeController::index` and `ProfileController::show`,
 * all three of which send `commentBounds` off `CommentValidationRules`, and by
 * following the feed path `PetListingCard` → `PetCardActions` →
 * `PetCardCommentButton` → `CommentsDialog` → here; not by rendering it.
 *
 * The prop stays **optional**, and the mechanism it expresses is unchanged:
 * each missing prop turns off exactly one control. Without the bound this
 * composer loses its counter and its `maxlength` attribute and nothing else —
 * the server's `max:` rule still refuses an over-long comment, surfaced as
 * `errors.content` by the same `<Form>`. That degradation is the fallback now,
 * not the state of any current page, and a page that stops sending
 * `commentBounds` re-enters it with no type error to say so.
 */
const {
    commentableType,
    commentableId,
    maxLength = null,
    parentId = null,
    placeholder,
    autofocus = false,
} = defineProps<{
    commentableType: CommentableType;
    commentableId: number;
    /** `petconnect.comments.max_length`, when the page was shipped it. */
    maxLength?: number | null;
    parentId?: number | null;
    placeholder?: string;
    autofocus?: boolean;
}>();

const emit = defineEmits<{ posted: [] }>();

const { t } = useTranslations();
const surface = useMutationSurface();

const content = ref('');

const options = computed(() => ({ preserveScroll: true, ...surface.visit }));
</script>

<template>
    <Form
        v-bind="
            storeComment.form({
                commentable_type: commentableType,
                commentable_id: commentableId,
            })
        "
        reset-on-success
        :options="options"
        class="space-y-2"
        v-slot="{ errors, processing }"
        @success="
            content = '';
            emit('posted');
            surface.onMutated();
        "
    >
        <input
            v-if="parentId !== null"
            type="hidden"
            name="parent_id"
            :value="parentId"
        />

        <Textarea
            v-model="content"
            name="content"
            rows="3"
            required
            :maxlength="maxLength ?? undefined"
            :autofocus="autofocus"
            :placeholder="placeholder ?? t('comments.write_placeholder')"
            :aria-label="t('comments.comment')"
        />
        <InputError :message="errors.content" />
        <InputError :message="errors.parent_id" />

        <div class="flex items-center justify-between gap-2">
            <span v-if="maxLength" class="text-muted-foreground text-xs">
                {{ content.length }} / {{ maxLength }}
            </span>
            <Button
                type="submit"
                size="sm"
                class="ms-auto"
                :disabled="processing"
            >
                <Spinner v-if="processing" />
                <Send v-else class="size-4" aria-hidden="true" />
                {{ processing ? t('comments.posting') : t('comments.comment') }}
            </Button>
        </div>
    </Form>
</template>
