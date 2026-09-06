<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowRight, Mail, MessageSquare } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import SupportChannel from '@/components/support/SupportChannel.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { SUPPORT_EMAIL, SUPPORT_MAILTO } from '@/lib/support';

/**
 * How to reach a human. `Route::inertia('support', 'Support')`, so **no props
 * at all** beyond the shared ones.
 *
 * Public, and therefore mapped to `PublicLayout` in `app.ts` — "how do I reach
 * somebody" is a question a guest is more likely to have than a member.
 *
 * ## The copy
 *
 * All ten `support.*` keys go through `t()`, so this page renders
 * `lang/ar.json` when the locale is `ar`. The two explanatory lines under the
 * channel buttons have no key in either catalogue and stay English until
 * `support.live_chat_unavailable` exists; the address is not copy at all.
 *
 * ## Live chat is not connected
 *
 * The legacy page's "Start chat" was a `<button>` with no handler, and there is
 * still no chat backend — `conversations.*` is member-to-member messaging about
 * a listing, not a support desk. The card stays, because the channel is
 * genuinely planned, but the control is disabled and says so rather than
 * pretending.
 *
 * A disabled button is not focusable, so the sentence explaining *why* it is
 * disabled was unreachable in tab order: a screen reader user tabbed from
 * "Ways to reach us" straight past the control to "Email us" and never met the
 * explanation. `aria-describedby` attaches it to the button, which is enough —
 * an accessible description is announced with the control whether or not the
 * control can take focus.
 *
 * ## The address
 *
 * `SUPPORT_EMAIL` in `lib/support.ts`, not a prop — see that file for why a
 * `Route::inertia()` route cannot carry one.
 */
const { t } = useTranslations();
</script>

<template>
    <div class="mx-auto w-full max-w-3xl space-y-10 p-4 sm:p-6 lg:p-8">
        <Head :title="t('support.title')" />

        <!-- `Heading` renders an h2, so the page still owes the document an h1. -->
        <h1 class="sr-only">{{ t('support.title') }}</h1>

        <Heading
            :title="t('support.contact_support')"
            :description="t('support.intro')"
        />

        <section class="space-y-4">
            <h2 class="text-lg font-semibold">
                {{ t('support.ways_to_reach_us') }}
            </h2>

            <div class="grid gap-4">
                <SupportChannel
                    :icon="MessageSquare"
                    :title="t('support.live_chat')"
                    :description="t('support.live_chat_desc')"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        disabled
                        aria-describedby="support-live-chat-note"
                    >
                        {{ t('support.start_chat') }}
                        <ArrowRight class="size-4 rtl:rotate-180" />
                    </Button>
                    <p
                        id="support-live-chat-note"
                        class="text-muted-foreground mt-2 text-xs"
                    >
                        Live chat is not open yet. Email us and we will pick it
                        up from there.
                    </p>
                </SupportChannel>

                <SupportChannel
                    :icon="Mail"
                    :title="t('support.email_support')"
                    :description="t('support.email_support_desc')"
                >
                    <Button as-child variant="outline" size="sm">
                        <a :href="SUPPORT_MAILTO">
                            {{ t('support.email_us') }}
                            <ArrowRight class="size-4 rtl:rotate-180" />
                        </a>
                    </Button>
                    <p class="text-muted-foreground mt-2 text-xs">
                        {{ SUPPORT_EMAIL }}
                    </p>
                </SupportChannel>
            </div>
        </section>
    </div>
</template>
