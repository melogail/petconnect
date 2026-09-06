<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { MessageSquareMore } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import MessagePreviewRow from '@/components/messaging/MessagePreviewRow.vue';
import MessagePreviewSkeleton from '@/components/messaging/MessagePreviewSkeleton.vue';
import UnreadBadge from '@/components/shell/UnreadBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverClose,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useMessagingPreviews } from '@/composables/useMessagingPreviews';
import { useTranslations } from '@/composables/useTranslations';
import { index as conversationsIndex } from '@/routes/conversations';

/**
 * The messages menu in the header: an unread badge, and the newest handful of
 * conversations under it.
 *
 * ## It is only ever rendered for a signed-in, verified reader
 *
 * **This component enforces that itself, so it is safe to mount anywhere.**
 * `conversations.previews` is behind `auth` **+ `verified`**, so "signed in"
 * is not the predicate; `canRead` below is, in one expression: the optional
 * chain in `viewer?.email_verified_at != null` is what makes a **guest** fail
 * it too, so do not "simplify" it away on the grounds that a caller checks
 * `user`. It gates the whole `Popover` and the mount fetch alike, so the
 * `v-if="user"` in `PublicHeader` and `AppSidebarHeader` is layout grouping,
 * not what prevents the fetch. `NotificationBell` carries the identical gate.
 *
 * The predicate is decided here because the request is this component's own.
 * An unverified account used to mount the menu, fire the two doomed XHRs the
 * endpoint answers with a redirect to `verification.notice`, and then be shown
 * a **retry button that can never succeed**. `email_verified_at` is on the
 * shared prop and typed, so the predicate is available without a request.
 *
 * Hidden rather than explained, as `components/pets/CreatePetButton.vue` does
 * on `pets.create`: every destination behind this control is inside the same
 * `verified` group — the rows, the "view all messages" link and
 * `conversations.store` — so there is no inbox to show, nothing to count and
 * nowhere to go. A verify-your-email line would need its own catalogue string
 * to explain a menu whose every entry is empty; `CommentComposerGate` carries
 * one because it stands in for a control on a page the reader is already
 * reading, which this is not. `useMessagingPreviews` documents the backstop
 * for the one case `canRead` cannot cover — a session that expires under an
 * open document.
 *
 * ## Fetched on mount, not on open
 *
 * A badge that only appears once you have clicked the thing it is meant to be
 * telling you about is not a badge. `ensureLoaded()` is gated on module-scoped
 * state, so the request happens **once per full document load and reader**:
 * opening the menu afterwards costs nothing, every Inertia visit costs nothing,
 * and moving between the public shell and the member shell remounts this
 * component without re-querying — while a different `auth.user.id` mounting
 * this component does refetch, because signing in and out are Inertia visits
 * inside one document. `NotificationBell` makes the same trade for the same
 * reason.
 *
 * ## A popover, not a dropdown menu
 *
 * The legacy component hand-rolled dismissal — a `v-click-outside` directive
 * over a plain `<div>` — which gets outside-click right and gets focus
 * management, escape handling and portalling wrong. Reka UI does all four.
 *
 * Of the two disclosure primitives wrapped in `components/ui`, `Popover` — the
 * wrapper these imports come from — is the one whose behaviour is what legacy
 * built: a non-modal disclosure anchored to its trigger, dismissed by an
 * outside click or by Escape, with focus moving in on open and back to the
 * trigger on close. `DropdownMenu` is a **menu** — its content carries
 * `role="menu"` and every child is expected to be a `menuitem`. This panel is a heading, a scrolling list of links, and a footer
 * link; putting that inside `role="menu"` publishes an ARIA structure that does
 * not describe it, and the roving-focus model would fight a scroll region.
 *
 * Direction is not passed. `app.ts` mounts a single `<ConfigProvider :dir>`
 * above the app and every Reka primitive inherits it through `useDirection()`,
 * portalled content included — which is what makes `align="end"` flip sides
 * under `dir="rtl"` without an `isRtl` check here.
 *
 * ## Geometry, and where each number comes from
 *
 * Read off `components/web/MessagesDropdown.vue` in petconnect-old: a 40px
 * round trigger (`p-2` around a 24px glyph, which is `size-icon-lg` + `size-6`
 * here), a `w-80` panel `mt-2` below it (the `:side-offset="8"`), `rounded-lg`
 * with `shadow-xl ring-1`, a `max-h-96` scroll region, and `px-4 py-3` rows.
 * The enter/leave transition is legacy's too — 100ms scale-and-fade in, 75ms
 * out — expressed through Reka's `data-[state]` attributes instead of a
 * `<Transition>` wrapper, because the portalled content is mounted and
 * unmounted by the primitive.
 *
 * Three numbers depart from legacy's, all knowingly: `rounded-lg` is 12px
 * under Tailwind v4's scale and was 8px in legacy's v3 (a repo-wide,
 * deliberate difference); the row list's `translate-x-8` is 32px against
 * legacy's hand-written `translateX(30px)`, which no utility step reaches; and that list's enter and leave are `ease-out` where legacy's scoped
 * CSS said plain `ease` (the 300ms is legacy's `0.3s`).
 *
 * What changed is colour, and only where legacy reached past a token: the
 * panel, the header strip, the dividers and the two greys become
 * `bg-popover` / `bg-muted/50` / `border-border` / `text-muted-foreground`, so
 * dark mode works, while the violet accents keep their exact legacy step
 * through the `primary-*` ramp and the unread badge stays literally red. Phase
 * 3a established the distinction: matching legacy's hex does not match legacy's
 * appearance under this app's blue-tinted dark scheme.
 */
const page = usePage();

const { t } = useTranslations();

const {
    previews,
    unreadCount,
    hasUnread,
    loading,
    failed,
    load,
    ensureLoaded,
    markConversationRead,
} = useMessagingPreviews();

const open = ref(false);

/** Null for a guest, whatever `types/auth.ts` says (.ai/rules/types.md). */
const viewer = computed(() => page.props.auth.user ?? null);

const viewerId = computed(() => viewer.value?.id ?? null);

/** A signed-in reader who has verified their email. The inbox needs both. */
const canRead = computed(() => viewer.value?.email_verified_at != null);

/**
 * The fetch is gated as well as the markup: `v-if` keeps the control off the
 * header, but this component is still mounted by the header that owns it, so
 * `onMounted` would otherwise still fire the request the account cannot
 * complete.
 */
onMounted(() => {
    if (canRead.value) {
        void ensureLoaded();
    }
});

/**
 * Following a row does two things beyond the navigation itself: it closes the
 * panel, and it clears that row.
 *
 * The clearing belongs here rather than in `MessagePreviewRow`, which reads no
 * shared state by design. It costs no request — the thread page posts
 * `conversations.read` on mount — and without it the badge and the tint would
 * keep the pre-click number for the rest of the SPA session, because nothing
 * re-renders module state. `useMessagingPreviews.markConversationRead` records
 * the rest.
 */
function handleNavigate(conversationId: number): void {
    markConversationRead(conversationId);
    open.value = false;
}

/**
 * The unread count reaches assistive technology here and nowhere else — the
 * badge itself is `aria-hidden`, because "3" announced beside "Messages" reads
 * as a second control rather than as a property of this one. Legacy also
 * carried an `sr-only` span repeating the label inside the button; an
 * `aria-label` overrides the element's contents, so that span could never be
 * announced and is not reproduced.
 */
const triggerLabel = computed(() =>
    hasUnread.value
        ? `${t('nav.messages')} (${t('messaging.unread', { count: unreadCount.value })})`
        : t('nav.messages'),
);
</script>

<template>
    <Popover v-if="canRead" v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                variant="ghost"
                size="icon-lg"
                class="relative rounded-full"
                :aria-label="triggerLabel"
            >
                <MessageSquareMore
                    class="text-muted-foreground size-6 transition-colors duration-200"
                    :class="{ 'text-primary-600 dark:text-primary-400': open }"
                    aria-hidden="true"
                />
                <UnreadBadge :count="unreadCount" />
            </Button>
        </PopoverTrigger>

        <PopoverContent
            align="end"
            :side-offset="8"
            class="ring-border w-80 overflow-hidden rounded-lg shadow-xl ring-1 outline-none data-[state=closed]:duration-75 data-[state=open]:duration-100"
            :aria-label="t('messaging.messages')"
        >
            <div
                class="border-border bg-muted/50 flex items-center justify-between border-b px-4 py-3"
            >
                <h3
                    class="text-foreground flex items-center gap-2 text-sm font-semibold"
                >
                    <span>{{ t('messaging.messages') }}</span>
                    <span
                        v-if="hasUnread"
                        class="bg-primary-100 text-primary-800 dark:bg-primary-900/50 dark:text-primary-200 rounded-full px-2 py-0.5 text-xs"
                    >
                        {{ t('messaging.unread', { count: unreadCount }) }}
                    </span>
                </h3>

                <PopoverClose
                    class="text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 focus-visible:ring-ring/50 rounded-sm text-xs font-medium transition-colors outline-none focus-visible:ring-[3px]"
                >
                    {{ t('messaging.close') }}
                </PopoverClose>
            </div>

            <div class="max-h-96 overflow-y-auto">
                <MessagePreviewSkeleton v-if="loading" />

                <div v-else-if="failed" class="px-4 py-6 text-center">
                    <p class="text-muted-foreground text-sm">
                        {{ t('messaging.could_not_load') }}
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        class="mt-3"
                        @click="load()"
                    >
                        {{ t('common.try_again') }}
                    </Button>
                </div>

                <TransitionGroup
                    v-else-if="previews.length"
                    tag="div"
                    enter-active-class="transition duration-300 ease-out"
                    leave-active-class="transition duration-300 ease-out"
                    enter-from-class="opacity-0 translate-x-8 rtl:-translate-x-8"
                    leave-to-class="opacity-0 translate-x-8 rtl:-translate-x-8"
                >
                    <MessagePreviewRow
                        v-for="preview in previews"
                        :key="preview.id"
                        :preview="preview"
                        :viewer-id="viewerId"
                        @navigate="handleNavigate(preview.id)"
                    />
                </TransitionGroup>

                <div v-else class="px-4 py-6 text-center">
                    <MessageSquareMore
                        class="text-muted-foreground mx-auto mb-3 size-12"
                        :stroke-width="1.5"
                        aria-hidden="true"
                    />
                    <p class="text-muted-foreground text-sm">
                        {{ t('messaging.no_conversations_yet') }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs">
                        {{ t('messaging.reach_out_hint') }}
                    </p>
                </div>
            </div>

            <div
                v-if="previews.length > 0"
                class="border-border bg-muted/50 border-t px-4 py-2 text-center"
            >
                <Link
                    :href="conversationsIndex()"
                    class="text-primary-600 dark:text-primary-400 text-xs font-medium hover:underline"
                    @click="open = false"
                >
                    {{ t('messaging.view_all_messages') }}
                </Link>
            </div>
        </PopoverContent>
    </Popover>
</template>
