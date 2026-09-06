<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Flag,
    Heart,
    MoreHorizontal,
    Pencil,
    Reply,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CommentDeleteDialog from '@/components/comments/CommentDeleteDialog.vue';
import CommentEditDialog from '@/components/comments/CommentEditDialog.vue';
import ReportDialog from '@/components/reports/ReportDialog.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import UserAvatar from '@/components/UserAvatar.vue';
import { useLocale } from '@/composables/useLocale';
import { useMutationSurface } from '@/composables/useMutationSurface';
import { useTranslations } from '@/composables/useTranslations';
import { formatRelative } from '@/lib/datetime';
import { like as likeComment } from '@/routes/comments';
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
 * ## The arrangement is legacy's
 *
 * A tinted bubble with the author, the relative time and an **overflow menu**
 * in its header, the text under them, and the interactions on a bare row
 * underneath. Edit, delete and report live in that menu — not as three visible
 * ghost buttons, which is what this rendered before — because that is what
 * legacy's `web/CommentItem` and `pet/show/PetComments` both do, and because
 * three destructive-ish controls per comment on a thread of forty is a wall.
 * The bubble's flattened top corner (`rounded-ss-sm`) points at the avatar and
 * is legacy's too; it is the logical corner, so it flips in Arabic.
 *
 * `is_author` is what the menu's edit and delete entries are gated on —
 * `CommentResource` carries no `can_edit` / `can_delete` — so `CommentPolicy`
 * has the last word and a listing owner moderating somebody else's comment goes
 * through the same refusal path as anyone else rather than through a flag
 * invented here.
 *
 * ## The like control is ours, and stays
 *
 * Legacy renders no like on a comment at all. `comments.like` is a real route
 * here with a real counter on the payload, so removing it to match legacy would
 * delete a working feature to reproduce a gap. It keeps the bare row legacy
 * uses for "Reply".
 *
 * ## Guests
 *
 * `comments.like`, `comments.update`, `comments.destroy` and `reports.store`
 * are all `auth` + `verified`. A guest gets no menu, no reply and a static
 * like counter rather than a button, so nothing here can fire an authenticated
 * request for them.
 *
 * The report entry is also gated on the page having shipped the two report
 * vocabularies, because a report form with two empty selects cannot be
 * submitted. Every page that mounts this ships them: `pets.show`,
 * `profile.show` and — since the parity fix — `Home`, so the entry renders on
 * a dialog opened from a feed card as it does on the listing page. Established
 * 2026-09-06 by reading the `Inertia::render()` payloads of
 * `PetController::show`, `ProfileController::show` and `HomeController::index`,
 * all three of which ship `reportCategories` and `reportReasons`; separately,
 * the control on a feed-mounted dialog was observed working end to end this
 * phase, through to a `reports` row (observed by the agent that made the fix,
 * not re-run here).
 *
 * The `= []` defaults stay, and so does the mechanism they express: **each
 * missing prop turns off exactly one control**, quietly, rather than the
 * component throwing. That makes the two props live consumers of those three
 * controller payloads — a page that stops shipping them loses this entry with
 * no type error and no failing test, so they are not spare keys to delete.
 *
 * The entry is absent again once this viewer has reported the comment:
 * `EnsureNotAlreadyReported` answers a second report with a 422, so the entry
 * would open a form that cannot succeed. The amber flag beside the menu is what
 * states that instead, and `ReportDialog` refuses the form on `reported` as a
 * second line in case something else opens it.
 *
 * ## The reported flag is a named image, and it is focusable on purpose
 *
 * The flag carries its whole meaning in an `aria-label`, and a bare `<span>`
 * cannot take an accessible name — it has no role that accepts one, so the
 * label was being dropped rather than announced. `role="img"` is what makes the
 * name valid, and it also makes the element a leaf, which is right: the `Flag`
 * inside is `aria-hidden` decoration.
 *
 * `tabindex="0"` is the deliberate half, and the trade is real, so here is the
 * reasoning rather than the conclusion. The tooltip is the only place the fuller
 * sentence lives, and reka-ui opens it from `focus` as well as `pointermove`
 * (read from `reka-ui/dist/Tooltip/TooltipTrigger.js` on 2026-09-06: with
 * `as-child` it merges its own handlers onto this span and adds **no**
 * `tabindex`, so without one the `focus` handler can never fire and the tooltip
 * is mouse-only). The cost of a tab stop is what settled it, and it is much
 * smaller here than "one per reported comment" suggests: `has_reported` is a
 * per-viewer `withExists` scoped to `user_id` (`app/Concerns/HasReport.php:41`),
 * and `EnsureNotAlreadyReported` caps it at one report per viewer per comment,
 * so this badge renders **only on comments this reader reported themselves**. A
 * keyboard user who has reported nothing pays nothing on a forty-comment
 * thread; the stops are bounded by their own report count, not by thread
 * length. Given that, withholding the explanation to save a stop nobody
 * typically pays is the worse side of the trade.
 *
 * What this does **not** fix, stated so it is not mistaken for solved: touch.
 * reka's trigger returns early on `pointerType === 'touch'`, and a tap sets
 * `isPointerDown` before focus arrives, which makes its `focus` handler bail —
 * so a touch reader still gets only the `aria-label`, never the tooltip. Fixing
 * that means giving the badge visible text or a press-to-open control, which is
 * a design change and not one made here.
 */
const {
    comment,
    canInteract,
    canReply = false,
    maxLength = null,
    reportCategories = [],
    reportReasons = [],
} = defineProps<{
    comment: CommentPreview;
    /** `petconnect.comments.max_length`, when the page was shipped it. */
    maxLength?: number | null;
    /** A signed-in viewer. Comment writes need a verified account. */
    canInteract: boolean;
    canReply?: boolean;
    reportCategories?: SelectOption<ReportCategory>[];
    reportReasons?: SelectOption<ReportReason>[];
}>();

const emit = defineEmits<{
    reply: [];
    /**
     * How many comments the delete removed. Deleting a root takes its replies
     * with it in one transaction, so that is `1 + replies_count` — the row
     * already carries the true total, not the length of any loaded preview.
     */
    deleted: [count: number];
}>();

const { t } = useTranslations();
const { tag } = useLocale();
const surface = useMutationSurface();

const editing = ref(false);
const deleting = ref(false);
const reporting = ref(false);

const author = computed(() => comment.author);
const writtenAt = computed(() => formatRelative(comment.created_at, tag.value));

/**
 * `has_reported` is read with `??` server-side and ships `false` when a loader
 * omits it, so an absent value reads as "not reported" here too and the
 * affordance stays available rather than disappearing on a payload that simply
 * did not answer the question.
 */
const canReport = computed(
    () =>
        canInteract &&
        !comment.is_author &&
        !comment.has_reported &&
        reportCategories.length > 0 &&
        reportReasons.length > 0,
);

const hasMenu = computed(() => comment.is_author || canReport.value);
</script>

<template>
    <article class="flex gap-3">
        <UserAvatar
            :name="author?.name ?? t('comments.unknown')"
            :avatar="author?.avatar ?? null"
            class="size-9 shrink-0"
        />

        <div class="min-w-0 flex-1">
            <div class="bg-muted/40 rounded-2xl rounded-ss-sm px-4 py-3">
                <div class="mb-1 flex items-start justify-between gap-2">
                    <div class="flex min-w-0 flex-wrap items-center gap-x-2">
                        <Link
                            v-if="author"
                            :href="showProfile(author.id)"
                            class="truncate text-sm font-semibold hover:underline"
                        >
                            {{ author.name }}
                        </Link>
                        <span v-else class="text-sm font-semibold">
                            {{ t('comments.unknown') }}
                        </span>
                        <span class="text-muted-foreground text-xs">
                            {{ writtenAt }}
                        </span>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <TooltipProvider v-if="comment.has_reported">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <span
                                        role="img"
                                        tabindex="0"
                                        class="focus-visible:ring-ring/50 inline-flex size-6 items-center justify-center rounded-full text-amber-500 focus-visible:ring-[3px] focus-visible:outline-none"
                                        :aria-label="
                                            t('comments.already_reported')
                                        "
                                    >
                                        <Flag
                                            class="size-3.5 fill-current"
                                            aria-hidden="true"
                                        />
                                    </span>
                                </TooltipTrigger>
                                <TooltipContent>
                                    {{ t('comments.already_reported_tooltip') }}
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                        <DropdownMenu v-if="hasMenu">
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="text-muted-foreground hover:text-foreground size-6 rounded-full"
                                    :aria-label="t('pets.actions')"
                                >
                                    <MoreHorizontal class="size-3.5" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    v-if="comment.is_author"
                                    @select="editing = true"
                                >
                                    <Pencil class="size-4" aria-hidden="true" />
                                    {{ t('comments.edit') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="comment.is_author"
                                    variant="destructive"
                                    @select="deleting = true"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                    {{ t('comments.delete') }}
                                </DropdownMenuItem>
                                <DropdownMenuSeparator
                                    v-if="comment.is_author && canReport"
                                />
                                <DropdownMenuItem
                                    v-if="canReport"
                                    @select="reporting = true"
                                >
                                    <Flag class="size-4" aria-hidden="true" />
                                    {{ t('comments.report') }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <p class="text-sm leading-relaxed whitespace-pre-line">
                    {{ comment.content }}
                </p>
            </div>

            <div
                class="text-muted-foreground ms-2 mt-1.5 flex flex-wrap items-center gap-3 text-xs"
            >
                <Link
                    v-if="canInteract"
                    :href="likeComment(comment.id)"
                    as="button"
                    preserve-scroll
                    preserve-state
                    v-bind="surface.visit"
                    class="hover:text-primary flex items-center gap-1 transition-colors"
                    :aria-pressed="comment.is_liked"
                    @success="surface.onMutated()"
                >
                    <Heart
                        class="size-3.5"
                        :class="comment.is_liked ? 'fill-current' : ''"
                        aria-hidden="true"
                    />
                    {{ comment.likes_count }}
                </Link>
                <span v-else class="flex items-center gap-1">
                    <Heart class="size-3.5" aria-hidden="true" />
                    {{ comment.likes_count }}
                </span>

                <button
                    v-if="canReply && canInteract"
                    type="button"
                    class="hover:text-primary flex items-center gap-1 transition-colors"
                    @click="emit('reply')"
                >
                    <Reply
                        class="size-3.5 rtl:-scale-x-100"
                        aria-hidden="true"
                    />
                    {{ t('comments.reply') }}
                </button>
            </div>
        </div>

        <CommentEditDialog
            v-if="comment.is_author"
            v-model:open="editing"
            :comment="comment"
            :max-length="maxLength"
        />

        <CommentDeleteDialog
            v-if="comment.is_author"
            v-model:open="deleting"
            :comment-id="comment.id"
            @deleted="emit('deleted', 1 + comment.replies_count)"
        />

        <ReportDialog
            v-if="canReport"
            v-model:open="reporting"
            reportable-type="comment"
            :reportable-id="comment.id"
            :categories="reportCategories"
            :reasons="reportReasons"
            :reported="comment.has_reported"
            :show-trigger="false"
        />
    </article>
</template>
