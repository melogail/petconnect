<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useAuthUser } from '@/composables/useAuthUser';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Clock, MapPin, Heart, MessageSquare, Edit, Eye, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { route } from 'ziggy-js';

const user = useAuthUser();

const props = withDefaults(
    defineProps<{
        petDetails: {
            name: string;
            breed: string;
            age: string;
            gender: string;
            city?: string;
            state?: string;
            views: number;
            status: string;
            category: string;
        };
        petId: string | number;
        isOwner: boolean;
        showContact: boolean;
    }>(),
    { showContact: true },
);

defineEmits(['contact', 'save']);

const deleteDialogOpen = ref(false);
const deleteProcessing = ref(false);

const confirmDelete = () => {
    deleteProcessing.value = true;
    router.delete(route('pets.destroy', { pet: props.petId }), {
        preserveScroll: true,
        onFinish: () => {
            deleteProcessing.value = false;
            deleteDialogOpen.value = false;
        },
    });
};
</script>

<template>
    <div class="mb-6 overflow-hidden rounded-2xl border border-border/50 bg-card shadow-sm">
        <!-- Accent Top Bar -->
        <div class="h-1.5 bg-gradient-to-r from-primary via-blue-500 to-primary/60" />

        <div class="p-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <!-- Left: name & meta -->
                <div class="flex-1 min-w-0">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <h1 class="text-3xl font-bold tracking-tight text-foreground">
                            {{ petDetails.name }}
                        </h1>
                        <Badge variant="secondary" class="rounded-full px-3 text-xs font-medium">
                            {{ petDetails.breed }}
                        </Badge>
                        <Badge
                            class="rounded-full px-3 text-xs font-medium capitalize"
                            :class="{
                                'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300': petDetails.status === 'available',
                                'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300': petDetails.status === 'pending',
                                'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300': petDetails.status === 'adopted',
                            }"
                        >
                            {{ petDetails.status }}
                        </Badge>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                        <span class="flex items-center gap-1.5">
                            <Clock class="h-4 w-4 text-primary/70" />
                            {{ petDetails.age }}
                        </span>
                        <span class="flex items-center gap-1.5 capitalize">
                            <span class="h-1 w-1 rounded-full bg-muted-foreground/40" />
                            {{ petDetails.gender.toLowerCase() }}
                        </span>
                        <span v-if="petDetails.city || petDetails.state" class="flex items-center gap-1.5">
                            <MapPin class="h-4 w-4 text-primary/70" />
                            <span>{{ [petDetails.city, petDetails.state].filter(Boolean).join(', ') }}</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <Eye class="h-4 w-4 text-primary/70" />
                            {{ petDetails.views.toLocaleString() }} views
                        </span>
                    </div>
                </div>

                <!-- Right: CTAs -->
                <div class="flex shrink-0 items-center gap-2">
                    <template v-if="isOwner">
                        <Button
                            as="a"
                            :href="route('pets.edit', { pet: petId })"
                            variant="outline"
                            class="group h-10 rounded-full border-primary/20 px-5 hover:border-primary/50 hover:bg-primary/5"
                        >
                            <Edit class="mr-2 h-4 w-4 transition-colors group-hover:text-primary" />
                            Edit Pet
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="group h-10 rounded-full border-destructive/25 px-4 text-destructive hover:border-destructive/50 hover:bg-destructive/10"
                            @click="deleteDialogOpen = true"
                        >
                            <Trash2 class="h-4 w-4" />
                            <span class="sr-only md:not-sr-only md:ml-2 md:inline">Remove listing</span>
                        </Button>
                    </template>
                    <template v-else>
                        <Button
                            v-if="user?.email_verified_at"
                            variant="outline"
                            class="group h-10 w-10 rounded-full p-0 hover:border-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20"
                            @click="$emit('save')"
                        >
                            <Heart class="h-4 w-4 transition-all group-hover:fill-rose-500 group-hover:text-rose-500" />
                        </Button>
                        <Button
                            v-if="showContact && user?.email_verified_at"
                            class="h-10 rounded-full bg-gradient-to-r from-primary to-blue-500 px-6 shadow-md transition-all duration-200 hover:shadow-lg hover:from-primary/90 hover:to-blue-500/90"
                            @click="$emit('contact')"
                        >
                            <MessageSquare class="mr-2 h-4 w-4" />
                            Contact Owner
                        </Button>
                    </template>
                </div>
            </div>
        </div>

        <Dialog v-model:open="deleteDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Remove this listing?</DialogTitle>
                    <DialogDescription>
                        This will remove “{{ petDetails.name }}” from PetConnect. You can contact support if you need to
                        restore it later.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button type="button" variant="outline" :disabled="deleteProcessing" @click="deleteDialogOpen = false">
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="deleteProcessing"
                        class="font-medium"
                        @click="confirmDelete"
                    >
                        {{ deleteProcessing ? 'Removing…' : 'Remove listing' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
