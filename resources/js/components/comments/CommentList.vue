<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { MessageSquare } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import CommentItem from '@/components/comments/CommentItem.vue';
import CommentSkeleton from '@/components/comments/CommentSkeleton.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
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
 * The roots of a discussion, newest first, with the rest paged in.
 *
 * Split out of `CommentThread` so that the listing page and the feed's
 * comments dialog share one list: they differ in their frame and in where the
 * composer sits (above the list on the page, pinned below it in the dialog),
 * not in how a thread is rendered or paged.
 *
 * Roots are newest first, which is the order both the page payload and the
 * endpoint deliver; replies read downwards inside each root.
 *
 * ## "Load more" compares roots with roots
 *
 * `comments.index` pages `rootComments()`, so the number to compare against is
 * `rootCommentsCount` — `PetDetail.root_comments_count`, a `withCount()` that
 * counts top-level comments alone. Comparing against `comments_count`, which
 * counts replies too, lit the button up on almost every listing with a single
 * reply: it fetched a page, deduplicated to nothing, and disappeared.
 *
 * ## The first request is not always page one
 *
 * When a caller ships both a slice and `threadPerPage`, the slice **is** the
 * first roots, so asking for page one on the first click re-fetches what is
 * already on screen. `floor(rootsInHand / threadPerPage)` counts the endpoint
 * pages the slice covers *completely*, so the next request is the first that
 * can hold a root the reader has not got; a partly covered page overlaps, and
 * the merge deduplicates by id.
 *
 * `pets.detail_comment_page_size` bounds what the page ships and
 * `comments.thread_per_page` bounds what the endpoint returns, and they are
 * independent env vars — which is why the page size has to be shipped and
 * cannot be inferred from the size of the slice.
 *
 * ## Both bounds are optional, and the endpoint is the fallback
 *
 * Mounted from `CommentThread` on the listing page, this gets both. Mounted
 * inside `CommentsDialog` it gets neither, and the reason is the dialog's own
 * shape rather than a gap in the page payload: `CommentsDialog` declares no
 * `threadPerPage` and no `rootCommentsCount` prop at all, so it forwards only
 * `maxLength`, and a feed card has no root count to forward in the first place
 * — `root_comments_count` is a `PetDetailResource` key and `PetCardResource`
 * sends `comments_count` alone.
 *
 * Do not read that as "the feed page ships nothing": `Home` and `profile.show`
 * both ship `commentBounds` today, exactly as `pets.show` does, and its
 * `max_length` half reaches the composer through this dialog. Its
 * `thread_per_page` half simply has no wire to travel on. Established
 * 2026-09-06 by reading the three `Inertia::render()` payloads, the props
 * `CommentsDialog` declares and passes, and `PetCardResource`; not by
 * rendering any of them.
 *
 * Without the two bounds the first request is page one and `hasMore` is
 * answered by the paginator's own `meta.last_page` from that request onwards —
 * exact from the first fetch, and the reason the dialog fetches as it opens
 * instead of waiting for a click. Each absent bound costs exactly one thing:
 * no slice-aware first page without `threadPerPage`, no click-to-load without
 * a root count.
 *
 * `reload()` asks for page one again and replaces everything on screen with the
 * answer, seeded slice included. It is what a surface calls after a write, and
 * dropping the seed is the point: the slice came from a page prop that the
 * write did not refresh.
 *
 * ## A failed fetch is not an empty thread
 *
 * The endpoint can fail, and what a reader saw when it did was "No comments
 * yet" on a listing with forty comments — because `reload()` cleared the seed
 * and the fetched rows *before* asking, so a rejected request left nothing on
 * screen and nothing to say about it. Rows are now replaced only once a
 * response is in hand, and a failure keeps whatever was already rendered and
 * offers a retry that replays the exact request that failed.
 *
 * The shape is `composables/useMessagingPreviews.ts`'s, written in the same
 * phase: try, `catch` into a failure ref, `finally` clear `loading`.
 */
const {
    comments = [],
    commentableType,
    commentableId,
    rootCommentsCount = null,
    threadPerPage = null,
} = defineProps<{
    comments?: Comment[];
    /** Top-level comments only — the number "load more" is decided from. */
    rootCommentsCount?: number | null;
    commentableType: CommentableType;
    commentableId: number;
    /** `petconnect.comments.thread_per_page` — the *endpoint's* page size. */
    threadPerPage?: number | null;
    /** `petconnect.comments.max_length` — the composer's ceiling. */
    maxLength?: number | null;
    canInteract: boolean;
    reportCategories?: SelectOption<ReportCategory>[];
    reportReasons?: SelectOption<ReportReason>[];
}>();

/**
 * Forwarded from the rows, for a container that shows a comment count it cannot
 * reload — see `CommentsDialog`. Nothing here changes on them: the surface's
 * `onMutated` is what refreshes this list.
 */
const emit = defineEmits<{
    posted: [];
    deleted: [count: number];
}>();

const { t } = useTranslations();

const fetched = ref<Comment[]>([]);
const loading = ref(false);
const lastPage = ref<number | null>(null);

/**
 * The request that failed, kept so the retry button can replay exactly it —
 * a failed "load more" asks for the next page again, a failed `reload()` asks
 * for page one and still means to replace what is on screen.
 */
const failedRequest = ref<{ page: number; replace: boolean } | null>(null);

/**
 * Whether the `comments` prop is still part of the list. `reload()` clears it,
 * because after a write the slice a page prop shipped is the stale copy.
 */
const seeded = ref(true);

/**
 * The last endpoint page the reader already holds. Guarded against a zero page
 * size only so that a misconfigured `COMMENTS_THREAD_PER_PAGE` cannot divide by
 * it; the fallback is to ask for page one, which loses nothing but a click.
 */
function initialPage(): number {
    return threadPerPage && threadPerPage > 0
        ? Math.floor(comments.length / threadPerPage)
        : 0;
}

const loadedPage = ref(initialPage());

const http = useHttp<Record<string, never>, Paginated<Comment>>();

const thread = computed<Comment[]>(() => {
    const byId = new Map<number, Comment>();

    for (const comment of [
        ...(seeded.value ? comments : []),
        ...fetched.value,
    ]) {
        byId.set(comment.id, comment);
    }

    return [...byId.values()].sort(
        (a, b) => b.created_at.localeCompare(a.created_at) || b.id - a.id,
    );
});

const hasMore = computed(() => {
    if (lastPage.value !== null) {
        return loadedPage.value < lastPage.value;
    }

    return rootCommentsCount === null
        ? false
        : thread.value.length < rootCommentsCount;
});

/** No slice, nothing fetched yet, and a request in flight: show the skeleton. */
const pending = computed(() => loading.value && thread.value.length === 0);

/**
 * A `reload()` that arrived while a fetch was already in flight, drained once
 * that fetch settles. Not a `ref`: nothing renders it, so it needs no
 * reactivity — see `fetchPage` for what it is for.
 */
let pendingReload = false;

/**
 * One request for one page of roots. `replace` is what `reload()` needs and
 * `loadMore()` does not: the rows on screen are swapped for the response rather
 * than extended by it, and only after it arrives.
 *
 * Never call this directly — `fetchPage` is the entry point, and it owns the
 * in-flight rule this deliberately does not know about.
 */
async function requestPage(page: number, replace: boolean): Promise<void> {
    loading.value = true;
    failedRequest.value = null;

    try {
        const response = await http.get(
            commentIndex.url(
                {
                    commentable_type: commentableType,
                    commentable_id: commentableId,
                },
                { query: { page } },
            ),
        );

        if (replace) {
            seeded.value = false;
            fetched.value = response.data;
        } else {
            fetched.value = [...fetched.value, ...response.data];
        }

        loadedPage.value = response.meta.current_page;
        lastPage.value = response.meta.last_page;
    } catch {
        failedRequest.value = { page, replace };
    } finally {
        loading.value = false;
    }
}

/**
 * One page of roots, plus the rule that only one request is in flight at a
 * time.
 *
 * A second *append* while one is in flight is still dropped — two overlapping
 * "load more" clicks would append the same page twice. A **`replace`** is not
 * dropped, and that is the whole of this function: `reload()` is what the
 * surface calls after a write, so dropping it loses the row that was just
 * written until the dialog is closed and reopened, which remounts this list.
 * Writes and fetches genuinely overlap — the composer sits under an open
 * "load more", and a reply posts from a row while the next page is arriving.
 *
 * Queued rather than allowed to supersede the request in flight, because
 * superseding needs a request-generation guard as well: the append it cut off
 * would still resolve, and its `fetched.value = [...fetched.value, ...]` would
 * paste a stale page on top of the page one that replaced it. Waiting costs one
 * request's latency and needs nothing to discard.
 *
 * One drain covers any number of writes that land during the same fetch — the
 * queued reload asks for page one fresh, so it answers all of them.
 *
 * **Observed, both directions, 2026-09-06** — a built copy of this tree served
 * against a throwaway sqlite and driven over CDP, holding the dialog's mount
 * fetch at the **response** stage (`Fetch.requestStage: 'Response'`, so the
 * server answers with pre-write rows and only the delivery waits), posting a
 * uniquely-marked comment while it was held, then releasing it and never
 * reopening the dialog. Without the queue the mark **never appeared**; with it
 * the drained page-one request fires on release and the mark is on screen. The
 * counterfactual is the evidence — an earlier run of the same test held the
 * request *before* it reached the server, which made the held fetch itself come
 * back carrying the new row and both versions pass, so the stage is the whole
 * experiment and not a detail of it.
 */
async function fetchPage(page: number, replace: boolean): Promise<void> {
    if (loading.value) {
        pendingReload = pendingReload || replace;

        return;
    }

    await requestPage(page, replace);

    while (pendingReload) {
        pendingReload = false;

        await requestPage(1, true);
    }
}

function loadMore(): Promise<void> {
    return fetchPage(loadedPage.value + 1, false);
}

function retry(): Promise<void> {
    const request = failedRequest.value;

    return request
        ? fetchPage(request.page, request.replace)
        : Promise.resolve();
}

function reload(): Promise<void> {
    return fetchPage(1, true);
}

/**
 * A caller with no root count has nothing to page against and nothing
 * trustworthy on screen, so the first page is fetched as this mounts rather
 * than waiting for a click. That is the comments dialog: reka-ui unmounts a
 * closed `DialogContent`, so "mounted" is "opened", and re-opening re-fetches.
 * The listing page ships a count and a slice and fetches nothing here.
 */
onMounted(() => {
    if (rootCommentsCount === null) {
        void loadMore();
    }
});

defineExpose({ reload });
</script>

<template>
    <div class="space-y-6">
        <div v-if="pending" class="space-y-6" aria-busy="true">
            <CommentSkeleton v-for="index in 3" :key="index" />
        </div>

        <EmptyState
            v-else-if="thread.length === 0 && failedRequest === null"
            :icon="MessageSquare"
            :title="t('comments.empty')"
        />

        <ul v-else-if="thread.length > 0" class="space-y-6">
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
                @posted="emit('posted')"
                @deleted="emit('deleted', $event)"
            />
        </ul>

        <div v-if="failedRequest !== null" class="space-y-3 text-center">
            <p class="text-muted-foreground text-sm">
                {{ t('comments.could_not_load') }}
            </p>
            <Button
                variant="outline"
                size="sm"
                :disabled="loading"
                @click="retry"
            >
                <Spinner v-if="loading" />
                {{ t('common.try_again') }}
            </Button>
        </div>

        <Button
            v-else-if="hasMore"
            variant="outline"
            :disabled="loading"
            @click="loadMore"
        >
            <Spinner v-if="loading" />
            {{ t('comments.load_more') }}
        </Button>
    </div>
</template>
