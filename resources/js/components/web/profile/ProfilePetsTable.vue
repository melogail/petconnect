<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Plus, Edit2, Trash2, FileText, Eye, EyeOff } from 'lucide-vue-next';
import { route } from 'ziggy-js';

const props = defineProps({
    pets: Object,
    userCanCreate: Boolean,
});

const toggleStatus = (petId: number) => {
    router.patch(
        route('pets.toggle-status', { pet: petId }),
        {},
        {
            preserveScroll: true,
        },
    );
};

const deletePet = (petId: number) => {
    router.delete(route('pets.destroy', { pet: petId }), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="mb-6 flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
            Your Pets
        </h2>
        <Link
            v-if="userCanCreate"
            :href="route('pets.create')"
            class="inline-flex items-center rounded-lg border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
        >
            <Plus class="mr-2 h-4 w-4" />
            Create New Pet Post
        </Link>
    </div>

    <div
        class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm transition-shadow duration-200 hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
    >
        <div class="overflow-x-auto">
            <table
                class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
            >
                <thead
                    class="bg-gray-50/80 backdrop-blur-sm dark:bg-gray-700/80"
                >
                    <tr>
                        <th
                            scope="col"
                            class="w-16 px-4 py-4 text-center font-sans text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                        ></th>
                        <th
                            scope="col"
                            class="px-4 py-4 text-center font-sans text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                        >
                            Pet Name
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-4 text-center font-sans text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                        >
                            Status
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-4 text-center font-sans text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                        >
                            Created at
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-4 text-center font-sans text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                        >
                            Views
                        </th>
                        <th scope="col" class="px-6 py-4 text-center">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody
                    class="divide-y divide-gray-100 bg-white text-center dark:divide-gray-700/50 dark:bg-gray-800"
                >
                    <tr
                        v-for="pet in pets"
                        :key="pet.id"
                        class="group transition-colors duration-150 hover:bg-gray-50/80 dark:hover:bg-gray-700/50"
                    >
                        <td class="px-4 py-4">
                            <div class="flex items-center justify-center">
                                <div
                                    class="relative h-10 w-10 flex-shrink-0 overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-700"
                                >
                                    <img
                                        v-if="pet.feature_image"
                                        :src="pet.feature_image"
                                        :alt="pet.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center bg-gray-200 dark:bg-gray-600"
                                    >
                                        <FileText
                                            class="h-5 w-5 text-gray-400"
                                        />
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-center">
                                <div
                                    class="text-sm font-medium text-gray-900 transition-colors duration-150 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400"
                                >
                                    {{ pet.name }}
                                </div>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">
                            <span
                                :class="[
                                    'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
                                    'transform transition-all duration-200 group-hover:scale-105',
                                    pet.status === 'available'
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                        : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
                                ]"
                            >
                                <span
                                    :class="[
                                        'mr-1.5 h-1.5 w-1.5 rounded-full',
                                        pet.status === 'available'
                                            ? 'bg-green-500 dark:bg-green-400'
                                            : 'bg-yellow-500 dark:bg-yellow-400',
                                    ]"
                                ></span>
                                {{
                                    pet.status === 'available'
                                        ? 'Available'
                                        : 'Unavailable'
                                }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">
                            <div
                                class="text-sm text-gray-600 dark:text-gray-300"
                            >
                                {{ pet.created_at }}
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">
                            <div
                                class="text-sm text-gray-600 dark:text-gray-300"
                            >
                                {{ pet.views }}
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <div
                                class="flex items-center justify-end space-x-3"
                            >
                                <Link
                                    v-if="pet.can?.update"
                                    :href="route('pets.edit', { pet: pet.id })"
                                    class="rounded-full p-1.5 text-gray-400 transition-colors duration-150 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-gray-700"
                                    title="Edit pet"
                                >
                                    <Edit2 class="h-4 w-4" />
                                </Link>
                                <button
                                    v-if="pet.can?.update"
                                    class="rounded-full p-1.5 text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                                    :title="
                                        pet.status === 'available'
                                            ? 'Mark as unavailable'
                                            : 'Mark as available'
                                    "
                                    @click="toggleStatus(pet.id)"
                                >
                                    <EyeOff
                                        v-if="pet.status === 'available'"
                                        class="h-4 w-4"
                                    />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="pet.can?.delete"
                                    class="rounded-full p-1.5 text-gray-400 transition-colors duration-150 hover:bg-red-50 hover:text-red-600 dark:hover:bg-gray-700"
                                    title="Delete pet"
                                    @click="deletePet(pet.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="pets.length === 0" class="py-12 text-center">
            <FileText class="mx-auto h-12 w-12 text-gray-400" />
            <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-white">
                No pets yet
            </h3>
            <p class="mt-1 text-gray-500 dark:text-gray-400">
                Get started by creating a new post
            </p>
        </div>
    </div>
</template>

<style scoped></style>
