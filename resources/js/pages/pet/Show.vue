<script setup lang="ts">
import MainLayout from '@/layouts/MainLayout.vue';
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import { ArrowLeft, CheckCircle2 } from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

import PetGallery from '@/components/pet/show/PetGallery.vue';
import PetHeader from '@/components/pet/show/PetHeader.vue';
import PetStats from '@/components/pet/show/PetStats.vue';
import PetListingInfo from '@/components/pet/show/PetListingInfo.vue';
import PetAbout from '@/components/pet/show/PetAbout.vue';
import PetLocation from '@/components/pet/show/PetLocation.vue';
import PetHealthInfo from '@/components/pet/show/PetHealthInfo.vue';
import PetComments from '@/components/pet/show/PetComments.vue';
import PetOwnerCard from '@/components/pet/show/PetOwnerCard.vue';
import QuickMessageDialog from '@/components/web/QuickMessageDialog.vue';
import { useAuthUser } from '@/composables/useAuthUser';

interface ReportReasonOption {
    value: string;
    label: string;
}

const props = defineProps<{
    pet: Record<string, unknown>;
    reportReasons?: ReportReasonOption[];
}>();

const petData = computed(() => props.pet.data || props.pet);
const user = useAuthUser();

const showMessageDialog = ref(false);
const ownerUserId = computed(
    () => petData.value.user?.id as number | undefined,
);

const showContact = computed(
    () =>
        !!user.value &&
        !!ownerUserId.value &&
        user.value.id !== ownerUserId.value,
);

// ─── Computed Data ──────────────────────────────────────────
const listingTypeLabels: Record<number, string> = {
    1: 'Adoption',
    2: 'Sale',
    3: 'Mating',
};

const getAdditionalInfoValue = (key: string): string | null => {
    const info = petData.value.additional_info || [];
    if (!Array.isArray(info)) return null;
    const item = info.find(
        (i: { key?: string }) =>
            i?.key && String(i.key).toLowerCase() === key.toLowerCase(),
    );
    return item?.value ?? null;
};

const petDetails = computed(() => {
    const data = petData.value;
    const additionalInfo = Array.isArray(data.additional_info)
        ? data.additional_info
        : [];
    return {
        name: data.name || 'Unknown Pet',
        breed: data.breed?.name || 'Unknown Breed',
        age: data.age || 'Unknown Age',
        gender: data.gender || 'Unknown',
        vaccinated: data.vaccinated ?? false,
        spayedNeutered: data.spayed_neutered ?? false,
        description:
            data.description || 'No description available for this pet.',
        listing_type: data.listing_type,
        listing_type_label:
            listingTypeLabels[data.listing_type] ||
            (typeof data.listing_type === 'string'
                ? data.listing_type
                : 'Adoption'),
        city: data.city,
        state: data.state,
        price: data.price,
        category: data.category?.name || 'Other',
        weight: data.weight,
        color: data.color,
        health_status: data.health_status || 'healthy',
        status: data.status || 'available',
        views: data.views || 0,
        special_needs: data.special_needs,
        last_vet_visit: data.last_vet_visit,
        vaccinations: data.vaccinations || [],
        medications: data.medications || [],
        allergies: data.allergies || [],
        vet_name: data.vet_name,
        vet_phone: data.vet_phone,
        traits: data.traits || [],
        additional_info: additionalInfo,
        address: data.address,
        detailed_address: data.detailed_address,
        postal_code: data.postal_code,
        country: data.country,
        latitude: data.latitude,
        longitude: data.longitude,
        goodWithKids: getAdditionalInfoValue('Good with Kids') ?? null,
        goodWithPets: getAdditionalInfoValue('Good with Other Pets') ?? null,
        size: getAdditionalInfoValue('Size') ?? null,
    };
});

const filteredAdditionalInfo = computed(() => {
    return (petDetails.value.additional_info || []).filter(
        (i: { key?: string; value?: string }) => i?.key && i?.value,
    );
});

const carouselImages = computed(() => {
    const data = petData.value;
    const images = data.images?.map((img: any) => img.url) || [];
    if (images.length === 0 && data.feature_image)
        images.push(data.feature_image);
    return images;
});

const owner = computed(() => {
    const user = petData.value.user || {};
    return {
        id: user.id,
        name: user.name || 'Unknown User',
        avatar:
            user.avatar ||
            `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name || 'U')}`,
        location:
            user.city && user.state
                ? `${user.city}, ${user.state}`
                : user.country || 'Location unknown',
        memberSince: user.created_at
            ? new Date(user.created_at).getFullYear().toString()
            : '2023',
        rating: user.rating ? Number(parseFloat(user.rating).toFixed(1)) : 5.0,
        verified: true,
        phone: user.phone || null,
    };
});

const hasLocation = computed(
    () =>
        petDetails.value.address ||
        petDetails.value.city ||
        petDetails.value.state ||
        petDetails.value.country,
);

const hasHealthInfo = computed(
    () =>
        petDetails.value.health_status ||
        petDetails.value.special_needs ||
        petDetails.value.last_vet_visit ||
        petDetails.value.vet_name ||
        petDetails.value.vet_phone ||
        petDetails.value.vaccinations?.length > 0 ||
        petDetails.value.medications?.length > 0 ||
        petDetails.value.allergies?.length > 0,
);
</script>

<template>
    <MainLayout>
        <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6">
            <!-- Back Link -->
            <div class="mb-6">
                <Link
                    :href="route('home')"
                    class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm transition-colors"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Pets
                </Link>
            </div>

            <div class="flex flex-col gap-8 lg:flex-row">
                <!-- ══ Main Content ══════════════════════════════════════ -->
                <div class="min-w-0 flex-1">
                    <!-- Gallery -->
                    <PetGallery
                        :images="carouselImages"
                        :pet-name="petDetails.name"
                    />

                    <!-- Pet Header (name, breed, CTAs) -->
                    <PetHeader
                        :pet-details="petDetails"
                        :pet-id="petData.id"
                        :is-owner="!!(user && petData.user?.id === user.id)"
                        :show-contact="showContact"
                        @contact="showMessageDialog = true"
                    />

                    <!-- Main Details Card -->
                    <div
                        class="border-border/50 bg-card mb-6 overflow-hidden rounded-2xl border shadow-sm"
                    >
                        <div class="p-6">
                            <!-- Stats Grid -->
                            <PetStats :pet-details="petDetails" />

                            <!-- Listing & Price -->
                            <PetListingInfo :pet-details="petDetails" />

                            <!-- Divider -->
                            <div class="border-border/50 mb-6 border-t" />

                            <!-- About -->
                            <div class="mb-6">
                                <PetAbout :pet-details="petDetails" />
                            </div>

                            <!-- Location -->
                            <div v-if="hasLocation" class="mb-6">
                                <PetLocation :pet-details="petDetails" />
                            </div>

                            <!-- Health Info -->
                            <div v-if="hasHealthInfo" class="mb-6">
                                <PetHealthInfo :pet-details="petDetails" />
                            </div>

                            <!-- Additional Info -->
                            <div v-if="filteredAdditionalInfo.length > 0">
                                <h4
                                    class="text-muted-foreground mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest"
                                >
                                    Additional Information
                                </h4>
                                <dl
                                    class="border-border/50 bg-muted/10 grid gap-2 rounded-xl border p-4 sm:grid-cols-2"
                                >
                                    <div
                                        v-for="(
                                            item, idx
                                        ) in filteredAdditionalInfo"
                                        :key="idx"
                                        class="flex flex-col"
                                    >
                                        <dt
                                            class="text-muted-foreground text-xs"
                                        >
                                            {{ item.key }}
                                        </dt>
                                        <dd class="mt-0.5 text-sm font-medium">
                                            {{ item.value }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <PetComments
                        :initial-comments="petData.comments || []"
                        :current-user="user"
                        :commentable-id="petData.id"
                        commentable-type="pet"
                        :report-reasons="reportReasons ?? []"
                    />
                </div>

                <!-- ══ Right Sidebar ══════════════════════════════════════ -->
                <div class="w-full lg:w-80 xl:w-96">
                    <div class="sticky top-20 space-y-5">
                        <!-- Owner Card -->
                        <PetOwnerCard
                            :owner="owner"
                            :show-contact="showContact"
                            @message="showMessageDialog = true"
                        />

                        <!-- Safety Tips -->
                        <Card class="border-border/50">
                            <CardHeader class="pb-3">
                                <CardTitle class="text-base"
                                    >Safety Tips</CardTitle
                                >
                            </CardHeader>
                            <CardContent>
                                <ul class="space-y-2.5">
                                    <li
                                        v-for="tip in [
                                            'Meet in a public place',
                                            'Never send money in advance',
                                            'Check the pet\'s health records',
                                            'Ask for ownership documents',
                                        ]"
                                        :key="tip"
                                        class="flex items-start gap-2.5"
                                    >
                                        <CheckCircle2
                                            class="mt-0.5 h-4 w-4 shrink-0 text-primary"
                                        />
                                        <span class="text-sm">{{ tip }}</span>
                                    </li>
                                </ul>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>

        <QuickMessageDialog
            v-model:open="showMessageDialog"
            :owner-name="owner.name"
            :pet-name="petDetails.name"
            :other-user-id="ownerUserId ?? null"
        />
    </MainLayout>
</template>
