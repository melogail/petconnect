<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { MessageCircle, Plus } from '@lucide/vue';
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
import { create as createPet } from '@/routes/pets';

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
 * ## Why the publish button is still here
 *
 * The legacy navbar had no such button, but in this application the header is
 * the only chrome that links `pets.create` at all: `AppSidebar` does not, and
 * the only other reference in the whole tree is one card on the `Help` page.
 * Dropping it to match the legacy layout would strip the primary action.
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
 * `Log in` and `Sign up` never move behind that menu — they are the page's
 * primary actions — they only drop to `h-8`/`text-xs`, which is what buys the
 * Arabic guest variant its headroom (`تسجيل الدخول` is 60% wider than
 * `Log in`). The publish button carries an `aria-label` because its own label
 * is `hidden` at exactly these widths.
 *
 * ## The signed-in variant also overflowed between `sm` and `lg`
 *
 * Separate defect, found by sweeping widths rather than checking 320px alone:
 * the publish label used to appear at `sm`, which took the header to 704px
 * inside a 640px viewport and 842px inside 768px once the search field joined
 * it. The label now waits for `lg`, and the search wrapper carries `min-w-0`
 * so the input's intrinsic width cannot push the row wider than the flex line.
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

                    <Button as-child class="rounded-lg">
                        <Link
                            :href="createPet()"
                            :aria-label="t('home.create_post')"
                        >
                            <Plus class="size-4" />
                            <span class="hidden lg:inline">
                                {{ t('home.create_post') }}
                            </span>
                        </Link>
                    </Button>

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
