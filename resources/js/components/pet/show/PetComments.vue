<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    AlertTriangle,
    Edit2,
    MessageSquare,
    MoreVertical,
    Reply,
    Send,
    Trash2,
} from 'lucide-vue-next';

interface CommentUser {
    id?: number | null;
    name?: string | null;
    avatar?: string | null;
}

interface CommentNode {
    id: number;
    user: CommentUser | null;
    content: string;
    parent_id: number | null;
    created_at?: string | null;
    replies?: CommentNode[];
}

interface CurrentUser {
    id: number;
    name: string;
    avatar?: string | null;
    email_verified_at?: string | null;
}

const props = withDefaults(
    defineProps<{
        initialComments: CommentNode[];
        currentUser?: CurrentUser | null;
        commentableId: number;
        commentableType?: string;
    }>(),
    { commentableType: 'pet' },
);

const comments = computed<CommentNode[]>(() => props.initialComments ?? []);
const newComment = ref('');
const activeReplyId = ref<number | null>(null);
const replyContent = ref('');
const submitting = ref(false);

const editingComment = ref<CommentNode | null>(null);
const editContent = ref('');
const deletingComment = ref<CommentNode | null>(null);

const isVerifiedOwner = (comment: CommentNode): boolean => {
    const user = props.currentUser;
    if (!user || !comment.user?.id) {
        return false;
    }
    return user.id === comment.user.id && !!user.email_verified_at;
};

const totalCount = computed(() =>
    comments.value.reduce((sum, c) => sum + 1 + (c.replies?.length ?? 0), 0),
);

const formatRelativeTime = (iso?: string | null): string => {
    if (!iso) return '';
    const then = new Date(iso).getTime();
    if (Number.isNaN(then)) return '';
    const diff = Math.max(0, Date.now() - then) / 1000;
    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return new Date(iso).toLocaleDateString();
};

const initial = (name?: string | null) => (name?.charAt(0) ?? 'U').toUpperCase();

const handleReply = (comment: CommentNode) => {
    activeReplyId.value = comment.id === activeReplyId.value ? null : comment.id;
    replyContent.value = '';
};

const postComment = (content: string, parentId: number | null, onDone: () => void) => {
    if (!content.trim() || submitting.value) return;
    submitting.value = true;
    router.post(
        route('comments.store', {
            commentable_type: props.commentableType,
            commentable_id: props.commentableId,
        }),
        { content, parent_id: parentId },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['pet', 'flash'],
            onSuccess: () => onDone(),
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
};

const submitComment = () =>
    postComment(newComment.value, null, () => {
        newComment.value = '';
        activeReplyId.value = null;
    });

const submitReply = (commentId: number) =>
    postComment(replyContent.value, commentId, () => {
        replyContent.value = '';
        activeReplyId.value = null;
    });

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
    if (!comment || !content.trim() || submitting.value) return;

    submitting.value = true;
    router.put(
        route('comments.update', { comment: comment.id }),
        { content },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['pet', 'flash'],
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
    if (!comment || submitting.value) return;

    submitting.value = true;
    router.delete(route('comments.delete', { comment: comment.id }), {
        preserveScroll: true,
        preserveState: true,
        only: ['pet', 'flash'],
        onSuccess: () => closeDeleteDialog(),
        onFinish: () => {
            submitting.value = false;
        },
    });
};
</script>

<template>
    <div class="mb-8 rounded-2xl border border-border/50 bg-card shadow-sm overflow-hidden">
        <div class="flex items-center justify-between border-b border-border/50 px-6 py-4">
            <div class="flex items-center gap-2.5">
                <MessageSquare class="h-5 w-5 text-primary" />
                <h2 class="text-lg font-semibold">Comments</h2>
            </div>
            <span class="rounded-full bg-muted px-3 py-0.5 text-sm text-muted-foreground font-medium">
                {{ totalCount }} {{ totalCount === 1 ? 'comment' : 'comments' }}
            </span>
        </div>

        <div class="p-6">
            <div v-if="currentUser" class="mb-6 flex gap-3">
                <Avatar class="h-9 w-9 shrink-0 border-2 border-white shadow-sm dark:border-gray-800">
                    <AvatarImage :src="currentUser?.avatar ?? undefined" />
                    <AvatarFallback class="bg-gradient-to-br from-primary to-blue-500 text-sm font-semibold text-white">
                        {{ initial(currentUser?.name) }}
                    </AvatarFallback>
                </Avatar>
                <div class="flex-1">
                    <div class="relative">
                        <Input
                            v-model="newComment"
                            placeholder="Share your thoughts about this pet..."
                            class="bg-muted/30 pr-12 transition-colors focus:bg-background"
                            :disabled="submitting"
                            @keyup.enter="submitComment"
                        />
                        <Button
                            variant="ghost"
                            size="icon"
                            class="absolute right-1 top-1/2 h-8 w-8 -translate-y-1/2 text-muted-foreground hover:text-primary"
                            :disabled="!newComment.trim() || submitting"
                            @click="submitComment"
                        >
                            <Send class="h-4 w-4" />
                        </Button>
                    </div>
                    <p class="mt-1.5 ml-1 text-xs text-muted-foreground">Press Enter to post</p>
                </div>
            </div>

            <div class="space-y-5">
                <div v-for="comment in comments" :key="comment.id">
                    <div class="flex gap-3">
                        <Avatar class="h-9 w-9 shrink-0">
                            <AvatarImage :src="comment.user?.avatar ?? undefined" />
                            <AvatarFallback>{{ initial(comment.user?.name) }}</AvatarFallback>
                        </Avatar>
                        <div class="flex-1 min-w-0">
                            <div class="rounded-2xl rounded-tl-sm bg-muted/40 px-4 py-3">
                                <div class="mb-1 flex items-start justify-between gap-2">
                                    <span class="font-semibold text-sm">{{ comment.user?.name ?? 'Unknown' }}</span>
                                    <div class="flex items-center gap-1 shrink-0">
                                        <span class="text-xs text-muted-foreground">
                                            {{ formatRelativeTime(comment.created_at) }}
                                        </span>
                                        <DropdownMenu v-if="isVerifiedOwner(comment)">
                                            <DropdownMenuTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    class="h-6 w-6 rounded-full text-muted-foreground hover:text-foreground"
                                                >
                                                    <MoreVertical class="h-3.5 w-3.5" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem @click="openEditDialog(comment)">
                                                    <Edit2 class="mr-2 h-4 w-4" />
                                                    Edit
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                                    @click="openDeleteDialog(comment)"
                                                >
                                                    <Trash2 class="mr-2 h-4 w-4" />
                                                    Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                                <p class="text-sm leading-relaxed whitespace-pre-line">{{ comment.content }}</p>
                            </div>

                            <div v-if="currentUser" class="mt-1.5 ml-2 flex items-center gap-3 text-xs text-muted-foreground">
                                <button
                                    class="flex items-center gap-1 transition-colors hover:text-primary"
                                    @click="handleReply(comment)"
                                >
                                    <Reply class="h-3.5 w-3.5" />
                                    Reply
                                </button>
                            </div>

                            <div v-if="activeReplyId === comment.id" class="mt-3 flex gap-2">
                                <Avatar class="h-7 w-7 shrink-0">
                                    <AvatarImage :src="currentUser?.avatar ?? undefined" />
                                    <AvatarFallback class="text-xs">{{ initial(currentUser?.name) }}</AvatarFallback>
                                </Avatar>
                                <div class="flex-1 relative">
                                    <Input
                                        v-model="replyContent"
                                        :placeholder="`Reply to ${comment.user?.name ?? 'user'}...`"
                                        class="h-8 pr-10 text-sm"
                                        :disabled="submitting"
                                        @keyup.enter="submitReply(comment.id)"
                                    />
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="absolute right-1 top-1/2 h-6 w-6 -translate-y-1/2"
                                        :disabled="!replyContent.trim() || submitting"
                                        @click="submitReply(comment.id)"
                                    >
                                        <Send class="h-3 w-3" />
                                    </Button>
                                </div>
                            </div>

                            <div v-if="comment.replies && comment.replies.length > 0" class="mt-3 space-y-3 border-l-2 border-muted/60 pl-4">
                                <div v-for="reply in comment.replies" :key="reply.id" class="flex gap-2">
                                    <Avatar class="h-7 w-7 shrink-0">
                                        <AvatarImage :src="reply.user?.avatar ?? undefined" />
                                        <AvatarFallback class="text-xs">{{ initial(reply.user?.name) }}</AvatarFallback>
                                    </Avatar>
                                    <div class="flex-1 min-w-0">
                                        <div class="rounded-2xl rounded-tl-sm bg-muted/30 px-3 py-2">
                                            <div class="mb-0.5 flex items-center justify-between gap-2">
                                                <span class="text-xs font-semibold">{{ reply.user?.name ?? 'Unknown' }}</span>
                                                <div class="flex items-center gap-1 shrink-0">
                                                    <span class="text-xs text-muted-foreground">
                                                        {{ formatRelativeTime(reply.created_at) }}
                                                    </span>
                                                    <DropdownMenu v-if="isVerifiedOwner(reply)">
                                                        <DropdownMenuTrigger as-child>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                class="h-5 w-5 rounded-full text-muted-foreground hover:text-foreground"
                                                            >
                                                                <MoreVertical class="h-3 w-3" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem @click="openEditDialog(reply)">
                                                                <Edit2 class="mr-2 h-4 w-4" />
                                                                Edit
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                                                @click="openDeleteDialog(reply)"
                                                            >
                                                                <Trash2 class="mr-2 h-4 w-4" />
                                                                Delete
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                            </div>
                                            <p class="text-sm leading-relaxed whitespace-pre-line">{{ reply.content }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="comments.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                    No comments yet. Be the first to share your thoughts!
                </div>
            </div>
        </div>

        <Dialog :open="!!editingComment" @update:open="(open) => !open && closeEditDialog()">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle>Edit comment</DialogTitle>
                    <DialogDescription>Update your comment and save your changes.</DialogDescription>
                </DialogHeader>
                <Textarea
                    v-model="editContent"
                    placeholder="Edit your comment..."
                    class="min-h-[120px]"
                    :disabled="submitting"
                    maxlength="500"
                />
                <DialogFooter class="gap-2 sm:justify-end">
                    <Button variant="outline" :disabled="submitting" @click="closeEditDialog">
                        Cancel
                    </Button>
                    <Button :disabled="!editContent.trim() || submitting" @click="submitEdit">
                        {{ submitting ? 'Saving...' : 'Save changes' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="!!deletingComment" @update:open="(open) => !open && closeDeleteDialog()">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <div class="flex items-center gap-2 text-red-600 dark:text-red-400">
                        <AlertTriangle class="h-5 w-5" />
                        <DialogTitle>Delete comment</DialogTitle>
                    </div>
                    <DialogDescription>
                        Are you sure you want to delete this comment? This action cannot be undone and any replies will also be removed.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:justify-end">
                    <Button variant="outline" :disabled="submitting" @click="closeDeleteDialog">
                        Cancel
                    </Button>
                    <Button variant="destructive" :disabled="submitting" @click="confirmDelete">
                        {{ submitting ? 'Deleting...' : 'Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
