<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10"
    >
        <div class="flex w-full max-w-md flex-col gap-6">
            <Link
                :href="home()"
                class="flex items-center gap-2 self-center font-medium"
            >
                <div class="flex h-9 w-9 items-center justify-center">
                    <AppLogoIcon class="size-9" />
                </div>
            </Link>

            <div class="flex flex-col gap-6">
                <Card
                    class="bg-card border-border shadow-popover rounded-[20px] border"
                >
                    <CardHeader class="px-10 pt-8 pb-0 text-center">
                        <CardTitle
                            class="font-serif text-[26px] leading-[1.15] font-medium tracking-[-0.01em]"
                        >
                            {{ title }}
                        </CardTitle>
                        <CardDescription class="text-muted-foreground text-sm">
                            {{ description }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="px-10 py-8">
                        <slot />
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
