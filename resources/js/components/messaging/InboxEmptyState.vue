<script setup lang="ts">
import { MessageSquareDashed } from 'lucide-vue-next';
</script>

<template>
    <div
        class="border-border bg-muted/30 flex flex-col items-center justify-center rounded-2xl border border-dashed py-20 text-center"
    >
        <div
            class="bg-muted mb-4 flex h-16 w-16 items-center justify-center rounded-full"
        >
            <MessageSquareDashed class="text-muted-foreground h-8 w-8" />
        </div>
        <h3 class="text-foreground text-base font-semibold">
            No conversations yet
        </h3>
        <p class="text-muted-foreground mt-1 max-w-xs text-sm">
            Message someone from a pet listing or profile to start chatting.
        </p>
    </div>
</template>
