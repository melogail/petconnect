<template>
    <div
        class="comment-item group relative"
        :class="{
            'ml-8 mt-3': isReply,
            'border-l-2 border-gray-200 pl-3 dark:border-gray-700':
                isReply && !isNestedReply,
            'ml-6': isNestedReply,
        }"
    >
        <div class="flex items-start gap-3">
            <Avatar :class="isReply ? 'h-8 w-8' : 'h-10 w-10'">
                <AvatarImage
                    :src="comment.user?.avatar ?? ''"
                    :alt="comment.user?.name ?? 'User'"
                />
                <AvatarFallback
                    class="bg-gradient-to-br from-violet-500 to-fuchsia-500 font-medium text-white"
                    :class="isReply ? 'text-xs' : 'text-sm'"
                >
                    {{ comment.user?.name?.charAt(0).toUpperCase() ?? 'U' }}
                </AvatarFallback>
            </Avatar>

            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-medium">
                            {{ comment.user?.name ?? 'Unknown' }}
                        </p>
                        <span class="text-xs text-gray-500 dark:text-gray-400"
                            >• {{ formatDate(comment.created_at) }}</span
                        >
                    </div>

                    <div class="flex items-center gap-1">
                        <TooltipProvider v-if="isReported(comment)">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <span
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-full text-amber-600 dark:text-amber-400"
                                        aria-label="Already reported"
                                    >
                                        <Flag class="h-4 w-4 fill-current" />
                                    </span>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>
                                        You have already reported this comment
                                    </p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                        <DropdownMenu v-if="showActionsMenu(comment)">
                            <DropdownMenuTrigger
                                class="flex h-8 w-8 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                            >
                                <MoreHorizontal class="h-4 w-4" />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    v-if="canReportComment(comment)"
                                    @click="
                                        $emit(
                                            'report-content',
                                            'comment',
                                            comment.id,
                                        )
                                    "
                                >
                                    <Flag class="mr-2 h-4 w-4" />
                                    <span>Report</span>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="isVerifiedOwner(comment)"
                                    @click="$emit('edit-comment', comment)"
                                >
                                    <Edit2 class="mr-2 h-4 w-4" />
                                    <span>Edit</span>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="isVerifiedOwner(comment)"
                                    @click="$emit('delete-comment', comment)"
                                    class="text-red-500 focus:text-red-500"
                                >
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    <span>Delete</span>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <p class="mt-1 text-gray-800 dark:text-gray-200">
                    {{ comment.content }}
                </p>

                <div
                    v-if="canComment"
                    class="mt-1.5 flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400"
                >
                    <button
                        @click="$emit('toggle-reply', comment.id)"
                        class="hover:text-blue-500 dark:hover:text-blue-400"
                    >
                        Reply
                    </button>
                </div>

                <div
                    v-if="activeReply === comment.id && canComment"
                    class="ml-12 mt-2"
                >
                    <form
                        @submit.prevent="
                            $emit('add-reply', comment, localReplyText)
                        "
                        class="flex gap-2"
                    >
                        <Input
                            v-model="localReplyText"
                            placeholder="Write a reply..."
                            class="h-9 flex-1 border-gray-300 bg-white text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                            required
                        />
                        <Button
                            type="submit"
                            size="sm"
                            class="h-9 bg-gradient-to-r from-violet-500 to-fuchsia-500 text-white hover:opacity-90"
                            :disabled="!localReplyText.trim()"
                        >
                            <span>Reply</span>
                        </Button>
                    </form>
                </div>

                <div
                    v-if="comment.replies && comment.replies.length > 0"
                    class="mt-3 space-y-3"
                >
                    <CommentItem
                        v-for="reply in comment.replies"
                        :key="reply.id"
                        :comment="reply"
                        :current-user="currentUser"
                        :is-reply="true"
                        :is-nested-reply="isReply"
                        :active-reply="activeReply"
                        :locally-reported-ids="locallyReportedIds"
                        @toggle-reply="$emit('toggle-reply', $event)"
                        @add-reply="
                            (replyComment, text) =>
                                $emit('add-reply', replyComment, text)
                        "
                        @edit-comment="$emit('edit-comment', $event)"
                        @delete-comment="$emit('delete-comment', $event)"
                        @report-content="
                            (type, id) => $emit('report-content', type, id)
                        "
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { MoreHorizontal, Edit2, Trash2, Flag } from 'lucide-vue-next';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import CommentItem from '@/components/web/CommentItem.vue';

interface CommentUser {
    id?: number | null;
    name?: string | null;
    avatar?: string | null;
    avatar_url?: string | null;
    email_verified_at?: string | null;
}

interface CommentNode {
    id: number;
    user: CommentUser | null;
    content: string;
    created_at?: string | null;
    has_reported_by_current_user?: boolean;
    replies?: CommentNode[];
}

interface CurrentUser {
    id: number;
    name?: string | null;
    avatar?: string | null;
    email_verified_at?: string | null;
}

defineEmits([
    'toggle-reply',
    'add-reply',
    'edit-comment',
    'delete-comment',
    'report-content',
]);

const props = defineProps<{
    comment: CommentNode;
    currentUser?: CurrentUser | null;
    isReply?: boolean;
    isNestedReply?: boolean;
    activeReply?: number | null;
    locallyReportedIds?: Set<number>;
}>();

const localReplyText = ref('');

const canComment = computed(() => !!props.currentUser?.email_verified_at);

const isReported = (comment: CommentNode): boolean => {
    return !!(
        comment.has_reported_by_current_user ||
        props.locallyReportedIds?.has(comment.id)
    );
};

const isVerifiedOwner = (comment: CommentNode): boolean => {
    const user = props.currentUser;

    if (!user || !comment.user?.id) {
        return false;
    }

    return user.id === comment.user.id && !!user.email_verified_at;
};

const canReportComment = (comment: CommentNode): boolean => {
    return (
        !!props.currentUser && !isVerifiedOwner(comment) && !isReported(comment)
    );
};

const showActionsMenu = (comment: CommentNode): boolean => {
    return isVerifiedOwner(comment) || canReportComment(comment);
};

const formatDate = (dateString?: string | null) => {
    if (!dateString) {
        return '';
    }

    const now = new Date();
    const date = new Date(dateString);
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (diffInSeconds < 60) {
        return 'Just now';
    }

    if (diffInSeconds < 3600) {
        return `${Math.floor(diffInSeconds / 60)}m ago`;
    }

    if (diffInSeconds < 86400) {
        return `${Math.floor(diffInSeconds / 3600)}h ago`;
    }

    if (diffInSeconds < 604800) {
        return `${Math.floor(diffInSeconds / 86400)}d ago`;
    }

    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>
