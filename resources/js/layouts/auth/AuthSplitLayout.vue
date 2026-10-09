<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

const page = usePage();
const name = page.props.name;

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0"
    >
        <div
            class="bg-secondary text-secondary-foreground border-border relative hidden h-full flex-col border-e p-10 lg:flex"
        >
            <div class="bg-secondary absolute inset-0" />
            <Link
                :href="home()"
                class="relative z-20 flex items-center font-serif text-[21px] font-medium tracking-[-0.015em]"
            >
                <AppLogoIcon class="mr-2 size-8" />
                {{ name }}
            </Link>
        </div>
        <div class="lg:p-8">
            <div
                class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]"
            >
                <div class="flex flex-col space-y-2 text-center">
                    <h1
                        class="font-serif text-[26px] leading-[1.15] font-medium tracking-[-0.01em]"
                        v-if="title"
                    >
                        {{ title }}
                    </h1>
                    <p class="text-muted-foreground text-sm" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
