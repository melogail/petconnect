<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import NotificationInboxActions from '@/components/notifications/NotificationInboxActions.vue';
import NotificationPanel from '@/components/notifications/NotificationPanel.vue';
import UnreadBadge from '@/components/shell/UnreadBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useLocale } from '@/composables/useLocale';
import { useNotificationInbox } from '@/composables/useNotificationInbox';
import { useTranslations } from '@/composables/useTranslations';

/**
 * The bell in the header: the unread badge, and the sheet the inbox opens in.
 *
 * ## It is only ever rendered for a signed-in, verified reader
 *
 * **This component enforces that itself, so it is safe to mount anywhere.**
 * `notifications.*` is behind `auth` **+ `verified`**, so "signed in" is not the
 * predicate; `canRead` below is, and it is one expression covering both halves.
 * `viewer?.email_verified_at != null` is false for a **null** viewer as well as
 * for an unverified one — `auth.user` is typed non-nullable while being null for
 * guests (.ai/rules/types.md), which is what the optional chain is there for —
 * and it gates the whole `Sheet` and the mount fetch alike. Mount this on a
 * guest-reachable surface and it renders nothing and requests nothing.
 *
 * The `v-if="user"` both callers wrap it in (`PublicHeader`, `AppSidebarHeader`)
 * groups the signed-in cluster for layout; it is not what prevents the fetch,
 * and nothing here depends on it. `MessagesDropdown` carries the same gate.
 *
 * The predicate is decided here rather than on a caller because it is this
 * component's own request that it governs. An unverified account used to mount
 * the bell, fire the doomed XHR the endpoint answers with a redirect to
 * `verification.notice`, and then be shown a **retry button that can never
 * succeed** — an offer of a control for a request the account cannot complete.
 * `email_verified_at` is on the shared prop and typed, so the whole question is
 * answerable without a request.
 *
 * Hidden rather than shown-with-an-explanation, the same call `MessagesDropdown`
 * makes on its own panel and `components/pets/CreatePetButton.vue` makes on
 * `pets.create`: every destination behind this control is inside the same
 * `verified` group — the rows, the paginator and the bulk actions — so there is
 * no inbox to show, nothing to count and nowhere to go, and no new catalogue
 * string is needed to say so. `useNotificationInbox` documents the backstop for
 * the one case `canRead` cannot cover — a session that expires under an open
 * document, where the reader passed the gate and then stopped qualifying.
 *
 * ## Why the count is fetched on mount and not on open
 *
 * A badge that only appears after you click the thing it is supposed to be
 * telling you about is not a badge. `ensureLoaded()` is gated on module-scoped
 * state, so the fetch happens **once per full document load and reader** rather
 * than once per mount: navigating between the public shell and the member shell
 * remounts this component and costs nothing, and every Inertia visit in between
 * costs nothing either. A different `auth.user.id` mounting this component does
 * refetch, because signing in and out are Inertia visits inside one document —
 * `useNotificationInbox` records why the gate is keyed to the reader.
 *
 * That is a different thing from what `BuildNotificationInbox` rejected. The
 * legacy app ran an inbox query inside every page render — every feed filter,
 * every settings screen — server-side and synchronously, ahead of the first
 * byte. This is one asynchronous request per session that blocks no paint.
 *
 * ## A sheet, not a dropdown menu
 *
 * Legacy used a sheet here too, and it is the right primitive for a different
 * reason than the messages menu next door needs a popover. The panel is a list
 * with its own bulk actions, its own paginator and its own scroll region.
 * `DropdownMenu` gives every child menu-item semantics and closes on any
 * activation inside it, so "mark all as read" would shut the inbox it had just
 * changed. A sheet is a plain dialog: it stays open, it traps focus properly,
 * and on a phone it is the full-height surface a list of notifications wants
 * anyway.
 *
 * Staying open is the default and following a row's deep link is the single
 * exception, which is what `@navigate` below is for. This header is mounted by
 * the layout resolver and is not remounted by a visit that keeps the same
 * layout, so nothing resets `open` on its own: without that handler the reader
 * arrives at the notification's destination with a modal overlay still over it.
 * `MessagesDropdown` closes on row activation for the same reason.
 *
 * ## The trigger, and where its numbers come from
 *
 * All of it read off legacy's `components/web/NotificationsSheet.vue`: a 40px
 * round control (`p-2` around a 24px glyph, which is `size-icon-lg` +
 * `size-6` here) that tints violet while the sheet is open, with the shared
 * red `UnreadBadge` on its corner. It was a 36px `size="icon"` with a `size-5`
 * glyph and a violet `bg-primary` badge capped at `99+`; the badge component's
 * docblock records why red and why `9+`.
 *
 * ## The sheet opens from the reading edge
 *
 * `side` follows `locale.direction`, which legacy also did: a panel that always
 * flies in from the physical right is on the wrong side of an Arabic reader,
 * and `SheetContent`'s slide animation is per-side so it cannot be corrected
 * with a logical utility afterwards. `isRtl` comes from the `locale` shared
 * prop, built from `petconnect.locales.rtl` — never a comparison against `ar`
 * (.ai/rules/lang.md).
 */
const page = usePage();

const { unreadCount, hasUnread, ensureLoaded } = useNotificationInbox();

const { t } = useTranslations();
const { isRtl } = useLocale();

const open = ref(false);

/** Null for a guest, whatever `types/auth.ts` says (.ai/rules/types.md). */
const viewer = computed(() => page.props.auth.user ?? null);

/**
 * A signed-in reader who has verified their email. The inbox needs both, and
 * this one expression is both: the optional chain is what makes a guest fail
 * it, so do not "simplify" it away on the grounds that a caller checks `user`.
 */
const canRead = computed(() => viewer.value?.email_verified_at != null);

/**
 * The fetch is gated as well as the markup: the `v-if="canRead"` on the `Sheet`
 * below keeps the control off the header, but this component is mounted by the
 * header either way, so `onMounted` would otherwise still fire the request the
 * reader cannot complete.
 */
onMounted(() => {
    if (canRead.value) {
        void ensureLoaded();
    }
});

const sheetSide = computed(() => (isRtl.value ? 'left' : 'right'));

const unreadLabel = computed(() =>
    t(
        unreadCount.value === 1
            ? 'notifications.unread_one'
            : 'notifications.unread_many',
        { count: unreadCount.value },
    ),
);

/**
 * The count reaches assistive technology through this name and nowhere else —
 * `UnreadBadge` is `aria-hidden`, because a bare "3" announced beside
 * "Notifications" reads as a second control rather than as a property of this
 * one.
 *
 * Both forms come out of the catalogue, and out of the same keys the sheet this
 * trigger opens uses: `notifications.notifications` for its title,
 * `notifications.unread_one` / `_many` for the sentence its description shows.
 *
 * Two things this used to do, and why it stopped. It read `nav.notifications`
 * here while the `SheetTitle` read `notifications.notifications` — one control
 * naming itself out of two keys that hold the same string in both catalogues,
 * for no recorded reason; `nav.notifications` has no other reader anywhere in
 * `resources/js`, so the domain key is the one that survives. And the unread
 * form was assembled here as `Notifications (…)`, with the brackets as a string
 * literal — the one piece of user-facing copy on this component that never went
 * through `t()`, and the one piece no Arabic translator could reach. It is now
 * the catalogue sentence on its own: ":count unread notifications" /
 * ":count إشعارات غير مقروءة" already names the thing it counts, in both
 * catalogues, so there is nothing left to glue around it and no new key to add.
 *
 * That is a deliberate divergence from `MessagesDropdown`, which still builds
 * "Messages (2 unread)" by hand — `messaging.unread` is a bare count there and
 * cannot stand alone the way this one can.
 */
const triggerLabel = computed(() =>
    hasUnread.value ? unreadLabel.value : t('notifications.notifications'),
);
</script>

<template>
    <Sheet v-if="canRead" v-model:open="open">
        <SheetTrigger as-child>
            <Button
                variant="ghost"
                size="icon-lg"
                class="relative rounded-full"
                :aria-label="triggerLabel"
            >
                <Bell
                    class="text-muted-foreground size-6 transition-colors duration-200"
                    :class="{ 'text-primary-600 dark:text-primary-400': open }"
                    aria-hidden="true"
                />
                <UnreadBadge :count="unreadCount" />
            </Button>
        </SheetTrigger>

        <SheetContent
            :side="sheetSide"
            class="flex w-full flex-col gap-0 p-0 sm:max-w-md"
        >
            <SheetHeader class="border-border border-b px-6 py-4 text-start">
                <!--
                    `pr-6` is the one physical-direction utility in a header
                    that is otherwise logical (`px-6`, `text-start`), and it is
                    deliberate: it reserves room for `ui/sheet`'s close button,
                    which `SheetContent` pins at physical `top-4 right-4` in
                    **both** directions. The reservation has to use the
                    coordinate system of the thing it reserves against, not the
                    one the text reads in — a logical property is not
                    automatically right next to a physically-pinned element.
                    Stacked on the header's own `px-6` this clears the 16px
                    glyph at `right-4` with 48px, the same gap
                    `PetFilterSheet` reserves with a physical `pr-12` over its
                    `p-4` header; the two spellings differ only because the
                    base padding does.

                    `ui/sheet` is shared, so the button is not ours to move.
                    This was a logical `pe-6`, which agrees with the physical
                    pin in LTR and flips away from it in RTL — where
                    `sheetSide` puts the sheet on the left, the title starts at
                    the right and ran under the X. A symmetric `px-6` also
                    clears the button, but by indenting 24px on the side the
                    button is never on, which is a new regression for an old
                    one.
                -->
                <div class="space-y-1 pr-6">
                    <SheetTitle>
                        {{ t('notifications.notifications') }}
                    </SheetTitle>
                    <SheetDescription>
                        {{
                            hasUnread
                                ? unreadLabel
                                : t('notifications.all_caught_up')
                        }}
                    </SheetDescription>
                </div>

                <NotificationInboxActions class="mt-3" />
            </SheetHeader>

            <div class="flex min-h-0 flex-1 flex-col">
                <NotificationPanel @navigate="open = false" />
            </div>
        </SheetContent>
    </Sheet>
</template>
