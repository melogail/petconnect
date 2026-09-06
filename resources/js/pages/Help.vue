<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { MessageSquare, PawPrint, Settings } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import HelpFaq from '@/components/help/HelpFaq.vue';
import HelpTopicLink from '@/components/help/HelpTopicLink.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { support } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { create as createPet } from '@/routes/pets';

/**
 * The help centre. `Route::inertia('help', 'Help')`, so **no props at all**
 * beyond the shared ones, and nothing to guard.
 *
 * Public, and rendered in `PublicLayout` like every other page since the
 * starter kit's sidebar shell (`AppLayout`, which read `auth.user`) was
 * removed on 2026-09-06 — `auth.user` is null for the guest this page exists
 * for, so nothing here may assume it.
 *
 * ## The copy
 *
 * The thirteen `help.*` keys go through `t()`, so this page is the first one in
 * the application that actually renders `lang/ar.json`. The two FAQ **answers**
 * do not: they were written for this page in Phase 4c and have no key in either
 * catalogue, and `lang/` is not the frontend's to write. They are English until
 * `help.faq_add_pet_answer` and `help.faq_update_pet_answer` exist.
 *
 * ## What changed from the legacy page
 *
 * The two quick links and the two FAQ rows were `<button>` elements with no
 * handler: they looked pressable and did nothing. The quick links now go where
 * they say they go (both are auth-only routes, so a guest lands on login, which
 * is the honest answer to "manage your account preferences"), and the FAQs are
 * real disclosures with the answers written out.
 *
 * The "how do I add a pet" answer used to say *"use **Publish a listing** in
 * the top bar"*, which was wrong for everybody this page is shown to. That
 * control is inside `PublicHeader`'s `v-if="user"`, so a guest — the reader
 * this page was moved to `PublicLayout` for — has no such button, and its label
 * is `hidden sm:inline`, so a signed-in reader on a phone sees a bare `+`. The
 * answer now links `pets.create` directly, the same way the quick link above it
 * does, and describes the form rather than a chrome element that may not be on
 * screen.
 */
const { t } = useTranslations();
</script>

<template>
    <div class="mx-auto w-full max-w-3xl space-y-10 p-4 sm:p-6 lg:p-8">
        <Head :title="t('help.title')" />

        <!-- `Heading` renders an h2, so the page still owes the document an h1. -->
        <h1 class="sr-only">{{ t('help.title') }}</h1>

        <Heading :title="t('help.title')" :description="t('help.intro')" />

        <section class="space-y-4">
            <h2 class="text-lg font-semibold">{{ t('help.quick_links') }}</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <HelpTopicLink
                    :icon="PawPrint"
                    :title="t('help.pet_profiles')"
                    :description="t('help.pet_profiles_desc')"
                    :href="createPet()"
                />
                <HelpTopicLink
                    :icon="Settings"
                    :title="t('help.account_settings')"
                    :description="t('help.account_settings_desc')"
                    :href="editProfile()"
                />
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold">{{ t('help.faqs') }}</h2>

            <div class="space-y-3">
                <HelpFaq :question="t('help.faq_add_pet')">
                    Start from
                    <Link :href="createPet()" class="underline">
                        Publish a listing</Link
                    >. The form is eight short steps — the basics, where the pet
                    is, photos, health, personality, anything extra, veterinary
                    details, and a review of the lot. You will be asked to sign
                    in first if you have not already. Nothing is saved until you
                    submit on the last step, and you can move between steps
                    freely before then.
                </HelpFaq>

                <HelpFaq :question="t('help.faq_update_pet')">
                    Open the listing and choose <strong>Edit</strong>. You will
                    see the same eight steps with everything already filled in.
                    Saving replaces the whole listing, so leave the fields you
                    are not changing exactly as they are — including the cover
                    photo, which stays as it is unless you pick a new one.
                </HelpFaq>
            </div>
        </section>

        <section class="border-border space-y-3 border-t pt-8">
            <h2 class="text-lg font-semibold">
                {{ t('help.still_need_help') }}
            </h2>
            <p class="text-muted-foreground text-sm">
                {{ t('help.still_need_help_desc') }}
            </p>

            <Button as-child>
                <Link :href="support()">
                    <MessageSquare class="size-4" />
                    {{ t('help.contact_support') }}
                </Link>
            </Button>
        </section>
    </div>
</template>
