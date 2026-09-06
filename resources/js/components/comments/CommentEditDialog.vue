<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useMutationSurface } from '@/composables/useMutationSurface';
import { useTranslations } from '@/composables/useTranslations';
import { update as updateComment } from '@/routes/comments';
import type { CommentPreview } from '@/types';

/**
 * Rewrite a comment.
 *
 * `content` is the only editable column — `parent_id` is not accepted by
 * `UpdateCommentRequest`, so a comment cannot be reparented into another
 * thread.
 *
 * Driven from outside (`v-model:open`) and carrying no trigger of its own: it
 * is opened from the "Edit" entry in the comment's overflow menu, and a
 * `DialogTrigger` inside a `DropdownMenuItem` is unmounted by the menu closing
 * before the dialog can open.
 *
 * Nested inside the feed's comments dialog this is a dialog within a dialog,
 * which reka-ui stacks: the inner one takes focus and Escape, and dismissing it
 * returns both to the outer one. Legacy did the same thing — its
 * `CommentsDialog` declared the edit and delete dialogs as siblings of the
 * thread dialog and drove all three from one component's state.
 */
const { comment, maxLength = null } = defineProps<{
    comment: CommentPreview;
    /** `petconnect.comments.max_length`, when the page was shipped it. */
    maxLength?: number | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const { t } = useTranslations();
const surface = useMutationSurface();

const options = computed(() => ({ preserveScroll: true, ...surface.visit }));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-[480px]">
            <DialogHeader>
                <DialogTitle>{{ t('comments.edit_comment') }}</DialogTitle>
                <DialogDescription>
                    {{ t('comments.edit_description') }}
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="updateComment.form(comment.id)"
                :options="options"
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="
                    open = false;
                    surface.onMutated();
                "
            >
                <div class="grid gap-2">
                    <Label :for="`comment-content-${comment.id}`">
                        {{ t('comments.comment') }}
                    </Label>
                    <Textarea
                        :id="`comment-content-${comment.id}`"
                        name="content"
                        rows="4"
                        required
                        :maxlength="maxLength ?? undefined"
                        :default-value="comment.content"
                        :placeholder="t('comments.edit_placeholder')"
                    />
                    <InputError :message="errors.content" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="processing"
                        @click="open = false"
                    >
                        {{ t('comments.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{
                            processing
                                ? t('comments.saving')
                                : t('comments.save_changes')
                        }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
