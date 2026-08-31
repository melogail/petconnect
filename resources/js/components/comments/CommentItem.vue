<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CommentBody from '@/components/comments/CommentBody.vue';
import CommentComposer from '@/components/comments/CommentComposer.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { replies as commentReplies } from '@/routes/comments';
import type {
    Comment,
    CommentableType,
    CommentPreview,
    Paginated,
    ReportCategory,
    ReportReason,
    SelectOption,
} from '@/types';

/**
 * A thread root, its reply preview and the composer that adds to it.
 *
 * The page ships at most `petconnect.pets.detail_reply_preview` replies per
 * comment while `replies_count` is the true total, so anything beyond the
 * preview is paged in from `comments.replies` — a **JSON** endpoint, not a
 * page, so it goes through `useHttp` rather than a visit. Replies cannot be
 * paginated per parent inside a single eager load, which is why expanding one
 * comment is a request of its own.
 *
 * Both sources are merged by id: page 1 of the endpoint overlaps the preview by
 * design, and posting a reply shifts every page boundary by one.
 */
const { comment } = defineProps<{
    comment: Comment;
    commentableType: CommentableType;
    commentableId: number;
    maxLength: number;
    canInteract: boolean;
    reportCategories: SelectOption<ReportCategory>[];
    reportReasons: SelectOption<ReportReason>[];
}>();

const replying = ref(false);
const fetched = ref<CommentPreview[]>([]);
const loadedPage = ref(0);
const lastPage = ref<number | null>(null);
const loading = ref(false);

const http = useHttp<Record<string, never>, Paginated<CommentPreview>>();

/** Newest-first everywhere it comes from, flipped into reading order here. */
const replies = computed<CommentPreview[]>(() => {
    const byId = new Map<number, CommentPreview>();

    for (const reply of [...(comment.replies ?? []), ...fetched.value]) {
        byId.set(reply.id, reply);
    }

    return [...byId.values()].sort(
        (a, b) => a.created_at.localeCompare(b.created_at) || a.id - b.id,
    );
});

const hasMore = computed(() =>
    lastPage.value === null
        ? replies.value.length < comment.replies_count
        : loadedPage.value < lastPage.value,
);

async function loadMore(): Promise<void> {
    if (loading.value || !hasMore.value) {
        return;
    }

    loading.value = true;

    try {
        const page = await http.get(
            commentReplies.url(comment.id, {
                query: { page: loadedPage.value + 1 },
            }),
        );

        fetched.value = [...fetched.value, ...page.data];
        loadedPage.value = page.meta.current_page;
        lastPage.value = page.meta.last_page;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <li class="space-y-3">
        <CommentBody
            :comment="comment"
            :max-length="maxLength"
            :can-interact="canInteract"
            can-reply
            :report-categories="reportCategories"
            :report-reasons="reportReasons"
            @reply="replying = !replying"
        />

        <div
            v-if="replies.length > 0 || hasMore || replying"
            class="ms-12 space-y-3"
        >
            <CommentBody
                v-for="reply in replies"
                :key="reply.id"
                :comment="reply"
                :max-length="maxLength"
                :can-interact="canInteract"
                :report-categories="reportCategories"
                :report-reasons="reportReasons"
            />

            <Button
                v-if="hasMore"
                variant="ghost"
                size="sm"
                :disabled="loading"
                @click="loadMore"
            >
                <Spinner v-if="loading" />
                Show more replies ({{ comment.replies_count }})
            </Button>

            <CommentComposer
                v-if="replying && canInteract"
                :commentable-type="commentableType"
                :commentable-id="commentableId"
                :max-length="maxLength"
                :parent-id="comment.id"
                placeholder="Write a reply…"
                autofocus
                @posted="replying = false"
            />
        </div>
    </li>
</template>
