<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Send } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
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
 * cannot drift from the validator.
 */
const {
    commentableType,
    commentableId,
    maxLength,
    parentId = null,
    placeholder = 'Write a comment…',
    autofocus = false,
} = defineProps<{
    commentableType: CommentableType;
    commentableId: number;
    maxLength: number;
    parentId?: number | null;
    placeholder?: string;
    autofocus?: boolean;
}>();

const emit = defineEmits<{ posted: [] }>();

const content = ref('');
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
        :options="{ preserveScroll: true }"
        class="space-y-2"
        v-slot="{ errors, processing }"
        @success="
            content = '';
            emit('posted');
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
            :maxlength="maxLength"
            :autofocus="autofocus"
            :placeholder="placeholder"
            aria-label="Comment"
        />
        <InputError :message="errors.content" />
        <InputError :message="errors.parent_id" />

        <div class="flex items-center justify-between gap-2">
            <span class="text-muted-foreground text-xs">
                {{ content.length }} / {{ maxLength }}
            </span>
            <Button type="submit" size="sm" :disabled="processing">
                <Spinner v-if="processing" />
                <Send v-else class="size-4" />
                Post
            </Button>
        </div>
    </Form>
</template>
