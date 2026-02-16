<script setup lang="ts">
import { ref } from 'vue';
import {
    MoreVertical,
    Star,
    Edit2,
    Trash2,
    Flag,
    AlertTriangle,
} from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import Button from '@/components/ui/button/Button.vue';
import ReviewForm from './ReviewForm.vue';

const props = defineProps({
    review: {
        type: Object,
        required: true,
    },
    currentUser: {
        type: Object,
        required: true,
    },
    updateUrl: {
        type: String,
        default: '#',
    },
    deleteUrl: {
        type: String,
        default: '#',
    },
});

const emit = defineEmits(['update', 'delete', 'report']);

const isEditOpen = ref(false);
const isDeleteOpen = ref(false);

const handleEditSubmit = (data) => {
    emit('update', { ...props.review, ...data });
    isEditOpen.value = false;
};

const handleDeleteConfirm = () => {
    emit('delete', props.review.id);
    isDeleteOpen.value = false;
};

const handleReport = () => {
    emit('report', props.review.id);
};
</script>

<template>
    <div
        class="h-full rounded-xl border border-gray-100 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
    >
        <div class="flex items-start justify-between">
            <div class="flex items-center space-x-3 text-start">
                <div
                    class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full border border-gray-200 dark:border-gray-700"
                >
                    <img
                        v-if="review.user.profile_image"
                        :src="review.user.profile_image"
                        :alt="review.user.name"
                        class="h-full w-full object-cover"
                    />
                    <span
                        v-else
                        class="text-sm font-semibold uppercase text-gray-600 dark:text-gray-300"
                    >
                        {{ review.user.name.substring(0, 2) }}
                    </span>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 dark:text-white">
                        {{ review.user.name }}
                    </h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ review.created_at }}
                    </p>
                </div>
            </div>

            <!-- Actions Dropdown -->
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8 rounded-full"
                    >
                        <MoreVertical class="h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <template v-if="review.is_owner">
                        <DropdownMenuItem @click="isEditOpen = true">
                            <Edit2 class="mr-2 h-4 w-4" />
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            @click="isDeleteOpen = true"
                            class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                        >
                            <Trash2 class="mr-2 h-4 w-4" />
                            Delete
                        </DropdownMenuItem>
                    </template>
                    <DropdownMenuItem v-else @click="handleReport">
                        <Flag class="mr-2 h-4 w-4" />
                        Report Abuse
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <div class="mt-4 flex items-center">
            <Star
                v-for="i in 5"
                :key="i"
                class="h-4 w-4"
                :class="[
                    i <= review.rating
                        ? 'fill-current text-yellow-400'
                        : 'text-gray-300 dark:text-gray-600',
                ]"
            />
        </div>

        <p
            class="mt-3 text-start text-sm leading-relaxed text-gray-600 dark:text-gray-300"
        >
            {{ review.comment }}
        </p>

        <!-- Edit Dialog -->
        <Dialog v-model:open="isEditOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Edit Review</DialogTitle>
                    <DialogDescription>
                        Update your review for this profile.
                    </DialogDescription>
                </DialogHeader>
                <ReviewForm
                    :initial-rating="review.rating"
                    :initial-comment="review.comment"
                    :action="updateUrl"
                    @submit="handleEditSubmit"
                />
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="isDeleteOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <div
                        class="flex items-center gap-2 text-red-600 dark:text-red-400"
                    >
                        <AlertTriangle class="h-5 w-5" />
                        <DialogTitle>Delete Review</DialogTitle>
                    </div>
                    <DialogDescription>
                        Are you sure you want to delete this review? This action
                        cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex gap-2 sm:justify-end">
                    <Button variant="outline" @click="isDeleteOpen = false">
                        Cancel
                    </Button>
                    <Button variant="destructive" @click="handleDeleteConfirm">
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
