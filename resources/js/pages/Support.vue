<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { LifeBuoy, Mail } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import ContactDialog from '@/components/support/ContactDialog.vue';
import NewTicketDialog from '@/components/support/NewTicketDialog.vue';
import SupportChannel from '@/components/support/SupportChannel.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { SUPPORT_EMAIL, SUPPORT_MAILTO } from '@/lib/support';
import { login } from '@/routes';
import { index as ticketsIndex } from '@/routes/tickets';

/**
 * How to reach a human. `Route::inertia('support', 'Support')`, so **no props
 * at all** beyond the shared ones.
 *
 * Public, and therefore mapped to `PublicLayout` in `app.ts` — "how do I reach
 * somebody" is a question a guest is more likely to have than a member.
 *
 * ## One channel each, decided by who is looking
 *
 * A signed-in member gets **support tickets**: `NewTicketDialog` posts to
 * `tickets.store`, which lands them on the ticket's own page under their
 * settings, where the conversation continues. A guest gets the **contact
 * form**: `ContactDialog` posts to `contact.store`, a one-off message the team
 * answers by email, with a nudge to sign in for the ticket experience.
 *
 * `auth.user` is typed non-nullable and is null for guests (.ai/rules/types.md);
 * this is a public page, so it is guarded at runtime and is the one switch.
 *
 * The address underneath is a plain `mailto:` for anyone who would rather use
 * their own mail client — `SUPPORT_EMAIL` in `lib/support.ts`, not a prop; see
 * that file for why a `Route::inertia()` route cannot carry one. Every
 * `support.*` key goes through `t()`.
 */
const page = usePage();
const { t } = useTranslations();

const isSignedIn = computed(() => Boolean(page.props.auth.user));
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
            <h2
                class="font-serif text-[26px] leading-[1.15] font-medium tracking-[-0.01em]"
            >
                {{ t('support.ways_to_reach_us') }}
            </h2>

            <div class="grid gap-4">
                <SupportChannel
                    v-if="isSignedIn"
                    :icon="LifeBuoy"
                    :title="t('support.tickets_channel')"
                    :description="t('support.tickets_channel_desc')"
                >
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <NewTicketDialog />
                        <Button as-child variant="secondary">
                            <Link :href="ticketsIndex()">
                                {{ t('support.my_tickets') }}
                            </Link>
                        </Button>
                    </div>
                </SupportChannel>

                <SupportChannel
                    v-else
                    :icon="Mail"
                    :title="t('support.contact_channel')"
                    :description="t('support.contact_channel_desc')"
                >
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <ContactDialog />
                        <Button as-child variant="secondary">
                            <Link :href="login()">
                                {{ t('support.sign_in_for_tickets') }}
                            </Link>
                        </Button>
                    </div>
                </SupportChannel>

                <p class="text-muted-foreground px-1 text-xs">
                    <a
                        :href="SUPPORT_MAILTO"
                        class="hover:text-foreground underline-offset-4 hover:underline"
                    >
                        {{ SUPPORT_EMAIL }}
                    </a>
                </p>
            </div>
        </section>
    </div>
</template>
