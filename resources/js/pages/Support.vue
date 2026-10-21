<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Mail } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import ContactDialog from '@/components/support/ContactDialog.vue';
import SupportChannel from '@/components/support/SupportChannel.vue';
import { useTranslations } from '@/composables/useTranslations';
import { SUPPORT_EMAIL, SUPPORT_MAILTO } from '@/lib/support';

/**
 * How to reach a human. `Route::inertia('support', 'Support')`, so **no props
 * at all** beyond the shared ones.
 *
 * Public, and therefore mapped to `PublicLayout` in `app.ts` — "how do I reach
 * somebody" is a question a guest is more likely to have than a member.
 *
 * ## One channel: the contact form
 *
 * "Email us" opens `ContactDialog`, which posts to `contact.store`; the
 * address underneath is a plain `mailto:` for anyone who would rather use
 * their own mail client. The live chat card that used to sit above this one
 * is gone: there was never a chat backend behind it (`conversations.*` is
 * member-to-member messaging about a listing, not a support desk), and a
 * permanently disabled button is a promise the page cannot keep.
 *
 * ## The copy
 *
 * Every `support.*` key goes through `t()`, so this page renders
 * `lang/ar.json` when the locale is `ar`; the address is not copy at all.
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
            <h2
                class="font-serif text-[26px] leading-[1.15] font-medium tracking-[-0.01em]"
            >
                {{ t('support.ways_to_reach_us') }}
            </h2>

            <div class="grid gap-4">
                <SupportChannel
                    :icon="Mail"
                    :title="t('support.email_support')"
                    :description="t('support.email_support_desc')"
                >
                    <ContactDialog />
                    <p class="text-muted-foreground mt-2 text-xs">
                        <a
                            :href="SUPPORT_MAILTO"
                            class="hover:text-foreground underline-offset-4 hover:underline"
                        >
                            {{ SUPPORT_EMAIL }}
                        </a>
                    </p>
                </SupportChannel>
            </div>
        </section>
    </div>
</template>
