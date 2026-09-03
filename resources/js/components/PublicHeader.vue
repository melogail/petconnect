<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { MessageCircle } from '@lucide/vue';
import { computed } from 'vue';
import NotificationBell from '@/components/notifications/NotificationBell.vue';
import AppearanceToggle from '@/components/shell/AppearanceToggle.vue';
import BrandMark from '@/components/shell/BrandMark.vue';
import LocaleSwitcher from '@/components/shell/LocaleSwitcher.vue';
import ShellPreferenceMenu from '@/components/shell/ShellPreferenceMenu.vue';
import ShellSearchField from '@/components/shell/ShellSearchField.vue';
import ShellUserMenu from '@/components/shell/ShellUserMenu.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { login, register } from '@/routes';
import { index as conversationsIndex } from '@/routes/conversations';

/**
 * The bar above every page a guest can reach.
 *
 * `AppHeader` is the signed-in variant and assumes `auth.user`; the five pages
 * `app.ts` maps to `PublicLayout` are all reachable without an account, so
 * everything below is guarded on the shared prop rather than assumed
 * (.ai/rules/types.md: `auth.user` is typed non-nullable and is null here).
 *
 * The notification bell sits inside that same `v-if="user"` and not outside
 * it: every `notifications.*` route is behind `auth` + `verified`, so a bell
 * shown to a guest would fetch its badge straight into a redirect to login.
 *
 * ## The messages slot
 *
 * The legacy shell had an unread-count dropdown between the bell and the
 * account menu. It needs a summary endpoint that does not exist yet — there is
 * no `messaging` shared prop and notifications are deliberately not shared —
 * so this keeps the plain link to `conversations.index`. Replace the button,
 * not the layout around it, when the endpoint lands.
 *
 * ## The publish button was removed in phase 3
 *
 * There used to be a `pets.create` button here, added in phase 1 on the
 * grounds that this header was the only chrome linking that route at all. The
 * user ruled in phase 3 for exact legacy parity instead: the legacy navbar had
 * no publish button, and the violet→fuchsia CTA lives **only** on Home
 * (`components/pets/CreatePetButton.vue`).
 *
 * The consequence is real and was accepted knowingly. Pet creation is now
 * linked from no persistent chrome anywhere in the application — not this
 * header, and `AppSidebar` never linked it either. Grepping `@/routes/pets`
 * for `create` across `resources/js` leaves exactly three entry points: the
 * Home CTA in `components/pets/CreatePetButton.vue` and two links on
 * `pages/Help.vue` (a card and an inline one). The fourth hit,
 * `pages/pets/Create.vue`, is that page's own breadcrumb pointing at itself.
 * If this is ever revisited it is a product decision about where the action
 * belongs, not a bug in this file. Do not re-add the button here to "fix" it.
 *
 * ## What happens to the cluster below `sm`
 *
 * Measured at a 320px viewport, the content box is 288px and the cluster came
 * to 324px (guest/en), 377px (guest/ar), 370px (auth/en) and 359px (auth/ar) —
 * two-dimensional scrolling on every public page, which WCAG 1.4.10 does not
 * allow. Shrinking first (wordmark to `sr-only`, tighter gap) took it to
 * 284/337/322/311, still wider than the 240px the brand leaves, so the two
 * secondary controls collapse into a menu below `sm`: `ShellUserMenu` for a
 * signed-in reader, `ShellPreferenceMenu` for a guest.
 *
 * Both sets of figures above describe **superseded layouts** — the first the
 * un-collapsed row, the second the shrink-only attempt — and the two auth ones
 * are stale by a further control now that the publish button is gone. What the
 * shipped header measures was re-measured after the removal, same instrument,
 * same 320px viewport, same 288px content box, `scrollWidth` of the control
 * cluster (the header row's last child):
 *
 * - guest/en **168px**, guest/ar **222px** (unchanged by the removal — the
 *   button was inside `v-if="user"`)
 * - auth/en **150px**, auth/ar **150px**; identical because none of the three
 *   controls left visible below `sm` carries any text
 *
 * All four are inside the ~240px the brand leaves, and
 * `document.documentElement.scrollWidth` is **320px** in every one of the four
 * — no second scroll axis, which is the WCAG 1.4.10 criterion this section
 * exists for. The auth cluster has five children, two of them `max-sm:hidden`;
 * before the removal it had six.
 *
 * `Log in` and `Sign up` never move behind that menu — they are the page's
 * primary actions — they only drop to `h-8`/`text-xs`, which is what buys the
 * Arabic guest variant its headroom (`تسجيل الدخول` is 60% wider than
 * `Log in`).
 *
 * ## The signed-in variant also overflowed between `sm` and `lg`
 *
 * Historical, and now moot. A separate defect found by sweeping widths rather
 * than checking 320px alone: the publish label used to appear at `sm`, which
 * took the header to 704px inside a 640px viewport and 842px inside 768px once
 * the search field joined it. That was first fixed by holding the label back
 * to `lg`; the button is gone entirely as of phase 3, so nothing in the
 * signed-in cluster is width-gated any more.
 *
 * The other half of that fix is still live and still needed: the search
 * wrapper carries `min-w-0`, without which the input's intrinsic width pushes
 * the row wider than the flex line at every width the field is visible.
 */
const page = usePage();

const { t } = useTranslations();

const user = computed(() => page.props.auth.user ?? null);
</script>

<template>
    <header
        class="border-border bg-background/80 sticky top-0 z-50 border-b shadow-sm backdrop-blur-md"
    >
        <div
            class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6"
        >
            <BrandMark />

            <div class="mx-8 hidden max-w-xl min-w-0 flex-1 md:block">
                <ShellSearchField />
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                <LocaleSwitcher class="max-sm:hidden" />
                <AppearanceToggle class="max-sm:hidden" />

                <template v-if="user">
                    <Button
                        as-child
                        variant="ghost"
                        size="icon"
                        class="rounded-full"
                    >
                        <Link
                            :href="conversationsIndex()"
                            :aria-label="t('nav.messages')"
                        >
                            <MessageCircle class="size-5" />
                        </Link>
                    </Button>

                    <NotificationBell />

                    <ShellUserMenu :user="user" />
                </template>

                <template v-else>
                    <ShellPreferenceMenu />

                    <Button
                        as-child
                        variant="ghost"
                        class="max-sm:h-8 max-sm:px-2.5 max-sm:text-xs"
                    >
                        <Link :href="login()">{{ t('nav.log_in') }}</Link>
                    </Button>
                    <Button
                        as-child
                        class="max-sm:h-8 max-sm:px-2.5 max-sm:text-xs"
                    >
                        <Link :href="register()">{{ t('nav.sign_up') }}</Link>
                    </Button>
                </template>
            </div>
        </div>
    </header>
</template>
