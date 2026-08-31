<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { update as updateComment } from '@/routes/comments';
import type { CommentPreview } from '@/types';

/**
 * Rewrite a comment.
 *
 * `content` is the only editable column — `parent_id` is not accepted by
 * `UpdateCommentRequest`, so a comment cannot be reparented into another
 * thread.
 */
const { comment, maxLength } = defineProps<{
    comment: CommentPreview;
    maxLength: number;
}>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="ghost" size="sm" class="text-muted-foreground">
                <Pencil class="size-4" />
                Edit
            </Button>
        </DialogTrigger>

        <DialogContent>
            <DialogHeader>
                <DialogTitle>Edit comment</DialogTitle>
                <DialogDescription>
                    Everyone reading the thread sees the new text.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="updateComment.form(comment.id)"
                :options="{ preserveScroll: true }"
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <div class="grid gap-2">
                    <Label :for="`comment-content-${comment.id}`">
                        Comment
                    </Label>
                    <Textarea
                        :id="`comment-content-${comment.id}`"
                        name="content"
                        rows="4"
                        required
                        :maxlength="maxLength"
                        :default-value="comment.content"
                    />
                    <InputError :message="errors.content" />
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Save
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
