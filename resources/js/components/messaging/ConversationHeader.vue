<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { ArrowLeft, MessageCircle } from 'lucide-vue-next';

defineProps<{
    peerName: string;
    peerAvatar: string;
    subtitle?: string;
}>();
</script>

<template>
    <div
        class="border-border bg-card mb-3 flex items-center gap-3 rounded-2xl border px-4 py-3 shadow-sm"
    >
        <Link
            :href="route('conversations.index')"
            class="text-muted-foreground hover:bg-muted hover:text-foreground flex h-9 w-9 shrink-0 items-center justify-center rounded-full transition-colors"
        >
            <ArrowLeft class="h-4 w-4" />
            <span class="sr-only">Back to inbox</span>
        </Link>

        <div class="relative shrink-0">
            <img
                :src="peerAvatar"
                :alt="peerName"
                class="ring-background h-10 w-10 rounded-full object-cover ring-2"
            />
            <span
                class="border-background absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full border-2 bg-emerald-400"
            />
        </div>

        <div class="min-w-0 flex-1">
            <h1 class="text-foreground truncate text-base font-semibold">
                {{ peerName }}
            </h1>
            <div class="flex items-center gap-1.5">
                <MessageCircle class="text-muted-foreground h-3 w-3" />
                <p class="text-muted-foreground text-xs">
                    {{ subtitle ?? 'Direct message' }}
                </p>
            </div>
        </div>
    </div>
</template>
