<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Heart, MessageSquare, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import CommentEditDialog from '@/components/comments/CommentEditDialog.vue';
import ReportDialog from '@/components/reports/ReportDialog.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/composables/useLocale';
import { formatRelative } from '@/lib/datetime';
import {
    destroy as destroyComment,
    like as likeComment,
} from '@/routes/comments';
import { show as showProfile } from '@/routes/profile';
import type {
    CommentPreview,
    ReportCategory,
    ReportReason,
    SelectOption,
} from '@/types';

/**
 * One comment, whether it is a thread root or a reply.
 *
 * `CommentResource` carries no `can_edit` / `can_delete`, only `is_author`, so
 * that is what the controls are gated on and `CommentPolicy` has the last word
 * — a listing owner moderating somebody else's comment goes through the same
 * refusal path as anyone else rather than through a flag invented here.
 *
 * `author` is absent when the loader did not eager load it, which is why the
 * byline falls back rather than assuming a name.
 */
const {
    comment,
    canInteract,
    canReply = false,
} = defineProps<{
    comment: CommentPreview;
    maxLength: number;
    /** A signed-in viewer. Comment writes need a verified account. */
    canInteract: boolean;
    canReply?: boolean;
    reportCategories: SelectOption<ReportCategory>[];
    reportReasons: SelectOption<ReportReason>[];
}>();

const emit = defineEmits<{ reply: [] }>();

const { tag } = useLocale();

const author = computed(() => comment.author);
const writtenAt = computed(() => formatRelative(comment.created_at, tag.value));
</script>

<template>
    <article class="flex gap-3">
        <UserAvatar
            :name="author?.name ?? 'Someone'"
            :avatar="author?.avatar ?? null"
            class="size-9 shrink-0"
        />

        <div class="min-w-0 flex-1 space-y-1.5">
            <div class="flex flex-wrap items-center gap-x-2">
                <Link
                    v-if="author"
                    :href="showProfile(author.id)"
                    class="text-sm font-medium hover:underline"
                >
                    {{ author.name }}
                </Link>
                <span v-else class="text-sm font-medium">Someone</span>
                <span class="text-muted-foreground text-xs">
                    {{ writtenAt }}
                </span>
            </div>

            <p class="text-sm leading-relaxed whitespace-pre-line">
                {{ comment.content }}
            </p>

            <div class="flex flex-wrap items-center gap-1">
                <Button
                    v-if="canInteract"
                    as-child
                    variant="ghost"
                    size="sm"
                    class="text-muted-foreground"
                    :aria-pressed="comment.is_liked"
                >
                    <Link
                        :href="likeComment(comment.id)"
                        as="button"
                        preserve-scroll
                        preserve-state
                    >
                        <Heart
                            class="size-4"
                            :class="comment.is_liked ? 'fill-current' : ''"
                        />
                        {{ comment.likes_count }}
                    </Link>
                </Button>
                <span
                    v-else
                    class="text-muted-foreground flex items-center gap-1 px-3 text-sm"
                >
                    <Heart class="size-4" />
                    {{ comment.likes_count }}
                </span>

                <Button
                    v-if="canReply && canInteract"
                    variant="ghost"
                    size="sm"
                    class="text-muted-foreground"
                    @click="emit('reply')"
                >
                    <MessageSquare class="size-4" />
                    Reply
                </Button>

                <CommentEditDialog
                    v-if="comment.is_author"
                    :comment="comment"
                    :max-length="maxLength"
                />

                <Button
                    v-if="comment.is_author"
                    as-child
                    variant="ghost"
                    size="sm"
                    class="text-muted-foreground"
                >
                    <Link
                        :href="destroyComment(comment.id)"
                        as="button"
                        preserve-scroll
                    >
                        <Trash2 class="size-4" />
                        Delete
                    </Link>
                </Button>

                <ReportDialog
                    v-if="canInteract && !comment.is_author"
                    reportable-type="comment"
                    :reportable-id="comment.id"
                    :categories="reportCategories"
                    :reasons="reportReasons"
                    :reported="comment.has_reported"
                />
            </div>
        </div>
    </article>
</template>
