<script setup lang="ts">
import { Link, useHttp } from '@inertiajs/vue3';
import { MessageSquare } from '@lucide/vue';
import { computed, ref } from 'vue';
import CommentComposer from '@/components/comments/CommentComposer.vue';
import CommentItem from '@/components/comments/CommentItem.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { index as commentIndex } from '@/routes/comments';
import type {
    Comment,
    CommentableType,
    Paginated,
    ReportCategory,
    ReportReason,
    SelectOption,
} from '@/types';

/**
 * A commentable's discussion.
 *
 * The page ships the newest `petconnect.pets.detail_comment_page_size` roots,
 * each with a bounded reply preview, and `comments_count` is the true total.
 * Everything past that first slice comes from `comments.index`, which answers
 * plain JSON rather than a page object — hence `useHttp` rather than a visit.
 *
 * Roots are newest first, which is the order both the page payload and the
 * endpoint deliver; replies read downwards inside each root.
 *
 * ## "Load more" compares roots with roots
 *
 * `commentsCount` is `withCount('comments')` over the **whole** morphMany —
 * replies included — while `thread` holds **roots**, because `comments.index`
 * pages `rootComments()`. Comparing the two lit the button up on almost every
 * listing that had a single reply: it fetched a page, deduplicated to nothing,
 * and disappeared.
 *
 * `rootCommentsCount` is `PetDetail.root_comments_count`, a second
 * `withCount()` that counts only top-level comments and exists for exactly this
 * comparison. `thread.length` is the roots in hand, so the two are the same
 * unit and `thread.length < rootCommentsCount` is exact. `commentsCount` stays
 * for the heading, which is about comments and not about roots.
 *
 * ## The first request is not page one
 *
 * The page payload **is** the first slice, so asking for page one on the first
 * click re-fetched roots already on screen: 25 roots, 20 duplicates, nothing
 * added, and a second click needed before anything moved.
 *
 * "Start at page two" would not have been safe either —
 * `pets.detail_comment_page_size` bounds what the page ships and
 * `comments.thread_per_page` bounds what the endpoint returns, and they are
 * independent env vars, so the shipped slice is not one endpoint page.
 * `floor(rootsInHand / threadPerPage)` is safe: it counts the endpoint pages
 * the shipped roots cover *completely*, so the next request is the first that
 * can hold a root the reader has not got. Where a page is only partly covered
 * the overlap is left to `thread`, which deduplicates by comment id.
 */
const {
    comments = [],
    commentsCount,
    commentableType,
    commentableId,
    rootCommentsCount,
    threadPerPage,
} = defineProps<{
    comments?: Comment[];
    /** Roots *and* replies — the number the heading shows. */
    commentsCount: number;
    /** Top-level comments only — the number "load more" is decided from. */
    rootCommentsCount: number;
    commentableType: CommentableType;
    commentableId: number;
    /**
     * `petconnect.comments.thread_per_page` — the *endpoint's* page size, which
     * is a different env var from the one that bounded `comments`.
     */
    threadPerPage: number;
    maxLength: number;
    canInteract: boolean;
    reportCategories: SelectOption<ReportCategory>[];
    reportReasons: SelectOption<ReportReason>[];
}>();

const fetched = ref<Comment[]>([]);

/**
 * The last endpoint page the reader already holds — see the note above for why
 * it does not start at zero.
 *
 * Guarded against a zero page size only so that a misconfigured
 * `COMMENTS_THREAD_PER_PAGE` cannot divide by it; the fallback is to ask for
 * page one, which is the old behaviour and loses nothing but a click.
 */
const loadedPage = ref(
    threadPerPage > 0 ? Math.floor(comments.length / threadPerPage) : 0,
);
const lastPage = ref<number | null>(null);
const loading = ref(false);

const http = useHttp<Record<string, never>, Paginated<Comment>>();

const thread = computed<Comment[]>(() => {
    const byId = new Map<number, Comment>();

    for (const comment of [...comments, ...fetched.value]) {
        byId.set(comment.id, comment);
    }

    return [...byId.values()].sort(
        (a, b) => b.created_at.localeCompare(a.created_at) || b.id - a.id,
    );
});

const hasMore = computed(() =>
    lastPage.value === null
        ? thread.value.length < rootCommentsCount
        : loadedPage.value < lastPage.value,
);

async function loadMore(): Promise<void> {
    if (loading.value || !hasMore.value) {
        return;
    }

    loading.value = true;

    try {
        const page = await http.get(
            commentIndex.url(
                {
                    commentable_type: commentableType,
                    commentable_id: commentableId,
                },
                { query: { page: loadedPage.value + 1 } },
            ),
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
    <section class="space-y-6">
        <h2 class="text-lg font-semibold">
            Comments
            <span class="text-muted-foreground font-normal">
                ({{ commentsCount }})
            </span>
        </h2>

        <CommentComposer
            v-if="canInteract"
            :commentable-type="commentableType"
            :commentable-id="commentableId"
            :max-length="maxLength"
        />
        <p v-else class="text-muted-foreground text-sm">
            <Link :href="login()" class="underline">Sign in</Link>
            to join the discussion.
        </p>

        <EmptyState
            v-if="thread.length === 0"
            :icon="MessageSquare"
            title="No comments yet"
            description="Be the first to say something about this listing."
        />

        <ul v-else class="space-y-6">
            <CommentItem
                v-for="comment in thread"
                :key="comment.id"
                :comment="comment"
                :commentable-type="commentableType"
                :commentable-id="commentableId"
                :max-length="maxLength"
                :can-interact="canInteract"
                :report-categories="reportCategories"
                :report-reasons="reportReasons"
            />
        </ul>

        <Button
            v-if="hasMore"
            variant="outline"
            :disabled="loading"
            @click="loadMore"
        >
            <Spinner v-if="loading" />
            Load more comments
        </Button>
    </section>
</template>
