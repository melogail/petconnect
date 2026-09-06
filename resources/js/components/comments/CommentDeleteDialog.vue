<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useMutationSurface } from '@/composables/useMutationSurface';
import { useTranslations } from '@/composables/useTranslations';
import { destroy as destroyComment } from '@/routes/comments';

/**
 * Confirm before deleting a comment.
 *
 * Deleting a **root** takes its replies with it — the delete flow collects the
 * subtree and removes it in one transaction — which is why the copy says so and
 * why this is a confirmation at all. The control it replaces posted the DELETE
 * straight from the overflow menu with nothing in between.
 *
 * Driven from outside (`v-model:open`), because it is opened from a
 * `DropdownMenuItem` and a trigger inside a menu is unmounted by the menu
 * closing. Same reason `ReportDialog` grew the same model.
 *
 * A `<Form>` rather than a `<Link as="button">`: the button has to report
 * `processing` while the request is in flight and the dialog has to close on
 * success, and `<Form>` gives both without a hand-rolled handler.
 */
const { commentId } = defineProps<{ commentId: number }>();

/**
 * Fired once the delete has succeeded. What it removed — a root takes its
 * replies with it — is counted by the caller, which holds the row.
 */
const emit = defineEmits<{ deleted: [] }>();

const open = defineModel<boolean>('open', { default: false });

const { t } = useTranslations();
const surface = useMutationSurface();

const options = computed(() => ({ preserveScroll: true, ...surface.visit }));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <div class="text-destructive flex items-center gap-2">
                    <AlertTriangle class="size-5" aria-hidden="true" />
                    <DialogTitle>{{
                        t('comments.delete_comment')
                    }}</DialogTitle>
                </div>
                <DialogDescription>
                    {{ t('comments.delete_confirm') }}
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="destroyComment.form(commentId)"
                :options="options"
                v-slot="{ processing }"
                @success="
                    open = false;
                    emit('deleted');
                    surface.onMutated();
                "
            >
                <DialogFooter class="gap-2 sm:justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="processing"
                        @click="open = false"
                    >
                        {{ t('comments.cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" />
                        {{
                            processing
                                ? t('comments.deleting')
                                : t('comments.delete')
                        }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
