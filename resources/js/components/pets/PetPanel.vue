<script setup lang="ts">
import type { Component } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

/** A titled card. Every fact panel on the listing page is one of these. */
defineProps<{
    title: string;
    icon?: Component;
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-base">
                <component
                    :is="icon"
                    v-if="icon"
                    class="text-muted-foreground size-4"
                    aria-hidden="true"
                />
                {{ title }}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <slot />
        </CardContent>
    </Card>
</template>
