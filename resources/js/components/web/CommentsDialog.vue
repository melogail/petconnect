<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import ReportDialog from '@/components/web/ReportDialog.vue';
import CommentItem from '@/components/web/CommentItem.vue';
import { AlertTriangle } from 'lucide-vue-next';
import { useTranslations } from '@/composables/useTranslations';

interface CommentUser {
    id?: number | null;
    name?: string | null;
    avatar?: string | null;
    email_verified_at?: string | null;
}

interface CommentNode {
    id: number;
    user: CommentUser | null;
    content: string;
    parent_id?: number | null;
    created_at?: string | null;
    replies?: CommentNode[];
}

interface CurrentUser {
    id: number;
    name?: string | null;
    avatar?: string | null;
    email_verified_at?: string | null;
}

interface ReportReasonOption {
    value: string;
    label: string;
}

const props = withDefaults(
    defineProps<{
        petId: number | string;
        petName: string;
        commentsCount?: number;
        initialComments?: CommentNode[];
        currentUser?: CurrentUser | null;
        reportReasons?: ReportReasonOption[];
    }>(),
    {
        commentsCount: 0,
        initialComments: () => [],
        currentUser: null,
        reportReasons: () => [],
    },
);

const emit = defineEmits(['comment-added', 'comment-deleted']);

const { t } = useTranslations();

const isOpen = ref(false);
const newComment = ref('');
const activeReply = ref<number | null>(null);
const submitting = ref(false);

const editingComment = ref<CommentNode | null>(null);
const editContent = ref('');
const deletingComment = ref<CommentNode | null>(null);

const reportDialogOpen = ref(false);
const reportContentId = ref<number | null>(null);
const locallyReportedIds = ref<Set<number>>(new Set());

const comments = computed<CommentNode[]>(() => props.initialComments ?? []);

const canComment = computed(() => !!props.currentUser?.email_verified_at);

const open = () => {
    isOpen.value = true;
};

const inertiaOptions = {
    preserveScroll: true,
    preserveState: true,
    only: ['pets', 'flash'] as string[],
};

const postComment = (
    content: string,
    parentId: number | null,
    onDone: () => void,
) => {
    if (!content.trim() || submitting.value) {
        return;
    }

    submitting.value = true;

    router.post(
        route('comments.store', {
            commentable_type: 'pet',
            commentable_id: props.petId,
        }),
        { content, parent_id: parentId },
        {
            ...inertiaOptions,
            onSuccess: () => {
                onDone();
                emit('comment-added');
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
};

const addComment = () =>
    postComment(newComment.value, null, () => {
        newComment.value = '';
        activeReply.value = null;
    });

const addReply = (comment: CommentNode, content: string) =>
    postComment(content, comment.id, () => {
        activeReply.value = null;
    });

const toggleReply = (commentId: number) => {
    activeReply.value = activeReply.value === commentId ? null : commentId;
};

const openEditDialog = (comment: CommentNode) => {
    editingComment.value = comment;
    editContent.value = comment.content;
};

const closeEditDialog = () => {
    editingComment.value = null;
    editContent.value = '';
};

const submitEdit = () => {
    const comment = editingComment.value;
    const content = editContent.value;

    if (!comment || !content.trim() || submitting.value) {
        return;
    }

    submitting.value = true;

    router.put(
        route('comments.update', { comment: comment.id }),
        { content },
        {
            ...inertiaOptions,
            onSuccess: () => closeEditDialog(),
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
};

const openDeleteDialog = (comment: CommentNode) => {
    deletingComment.value = comment;
};

const closeDeleteDialog = () => {
    deletingComment.value = null;
};

const confirmDelete = () => {
    const comment = deletingComment.value;

    if (!comment || submitting.value) {
        return;
    }

    submitting.value = true;

    router.delete(route('comments.delete', { comment: comment.id }), {
        ...inertiaOptions,
        onSuccess: () => {
            closeDeleteDialog();
            emit('comment-deleted');
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
};

const openReportDialog = (_type: string, id: number) => {
    reportContentId.value = id;
    reportDialogOpen.value = true;
};

const closeReportDialog = () => {
    reportDialogOpen.value = false;
    reportContentId.value = null;
};

const handleReportSubmit = () => {
    if (reportContentId.value !== null) {
        locallyReportedIds.value = new Set([
            ...locallyReportedIds.value,
            reportContentId.value,
        ]);
    }
    closeReportDialog();
};

defineExpose({ open });
</script>

<template>
    <slot name="trigger" :open="open" />

    <Dialog v-model:open="isOpen">
        <DialogContent
            class="flex max-h-[80vh] flex-col bg-white text-gray-900 dark:bg-gray-900 dark:text-gray-100 sm:max-w-[600px]"
        >
            <DialogHeader>
                <DialogTitle class="text-xl">{{
                    t('comments.comments_for', { name: petName })
                }}</DialogTitle>
                <DialogDescription>
                    {{ t('comments.share_thoughts', { name: petName }) }}
                </DialogDescription>
            </DialogHeader>

            <div class="-me-2 flex-1 overflow-y-auto py-4 pe-2">
                <div
                    v-if="comments.length === 0"
                    class="py-8 text-center text-gray-500 dark:text-gray-400"
                >
                    {{ t('comments.empty') }}
                </div>

                <div v-else class="space-y-6">
                    <div
                        v-for="comment in comments"
                        :key="comment.id"
                        class="space-y-3"
                    >
                        <CommentItem
                            :comment="comment"
                            :current-user="currentUser"
                            :active-reply="activeReply"
                            :locally-reported-ids="locallyReportedIds"
                            @toggle-reply="toggleReply"
                            @add-reply="addReply"
                            @edit-comment="openEditDialog"
                            @delete-comment="openDeleteDialog"
                            @report-content="openReportDialog"
                        />
                    </div>
                </div>
            </div>

            <form
                v-if="canComment"
                class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700"
                @submit.prevent="addComment"
            >
                <div class="flex gap-2">
                    <Input
                        v-model="newComment"
                        :placeholder="t('comments.write_placeholder')"
                        class="flex-1"
                        required
                        :disabled="submitting"
                    />
                    <Button
                        type="submit"
                        size="sm"
                        class="h-9 bg-gradient-to-r from-violet-500 to-fuchsia-500 text-white hover:opacity-90"
                        :disabled="!newComment.trim() || submitting"
                    >
                        {{
                            submitting
                                ? t('comments.posting')
                                : t('comments.comment')
                        }}
                    </Button>
                </div>
            </form>

            <p
                v-else
                class="mt-4 border-t border-gray-200 pt-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400"
            >
                {{ t('comments.sign_in_to_comment') }}
            </p>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="!!editingComment"
        @update:open="(open) => !open && closeEditDialog()"
    >
        <DialogContent class="sm:max-w-[480px]">
            <DialogHeader>
                <DialogTitle>{{ t('comments.edit_comment') }}</DialogTitle>
                <DialogDescription>{{
                    t('comments.edit_description')
                }}</DialogDescription>
            </DialogHeader>
            <Textarea
                v-model="editContent"
                :placeholder="t('comments.edit_placeholder')"
                class="min-h-[120px]"
                :disabled="submitting"
                maxlength="500"
            />
            <DialogFooter class="gap-2 sm:justify-end">
                <Button
                    variant="outline"
                    :disabled="submitting"
                    @click="closeEditDialog"
                >
                    {{ t('comments.cancel') }}
                </Button>
                <Button
                    :disabled="!editContent.trim() || submitting"
                    @click="submitEdit"
                >
                    {{
                        submitting
                            ? t('comments.saving')
                            : t('comments.save_changes')
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="!!deletingComment"
        @update:open="(open) => !open && closeDeleteDialog()"
    >
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <div
                    class="flex items-center gap-2 text-red-600 dark:text-red-400"
                >
                    <AlertTriangle class="h-5 w-5" />
                    <DialogTitle>{{
                        t('comments.delete_comment')
                    }}</DialogTitle>
                </div>
                <DialogDescription>
                    {{ t('comments.delete_confirm') }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:justify-end">
                <Button
                    variant="outline"
                    :disabled="submitting"
                    @click="closeDeleteDialog"
                >
                    {{ t('comments.cancel') }}
                </Button>
                <Button
                    variant="destructive"
                    :disabled="submitting"
                    @click="confirmDelete"
                >
                    {{
                        submitting
                            ? t('comments.deleting')
                            : t('comments.delete')
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <ReportDialog
        v-if="reportContentId"
        :is-open="reportDialogOpen"
        reportable-type="App\Models\Comment"
        :content-id="reportContentId"
        :report-reasons="reportReasons"
        @close="closeReportDialog"
        @submit="handleReportSubmit"
    />
</template>
