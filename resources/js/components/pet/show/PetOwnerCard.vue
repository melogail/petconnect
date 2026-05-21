<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { MapPin, Star, Phone, MessageSquare, CheckCircle2, Clock } from 'lucide-vue-next';
import { useAuthUser } from '@/composables/useAuthUser';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const user = useAuthUser();

withDefaults(
    defineProps<{
    owner: {
        id: number;
        name: string;
        avatar: string;
        location: string;
        memberSince: string;
        rating: number;
        verified: boolean;
        phone?: string | null;
    };
    showContact: boolean;
    }>(),
    { showContact: true },
);

defineEmits(['message', 'call']);
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-border/50 bg-card shadow-sm">
        <!-- Gradient accent -->
        <div class="h-1.5 bg-gradient-to-r from-primary via-blue-500 to-primary/60" />

        <div class="p-6">
            <!-- Owner Info -->
            <div class="mb-5 flex items-start gap-4">
                <!-- Avatar with online indicator -->
                <div class="relative shrink-0">
                    <div class="absolute -right-0.5 -top-0.5 z-10 h-3.5 w-3.5 rounded-full border-2 border-white bg-green-400 dark:border-gray-900" />
                    <Avatar class="h-16 w-16 border-2 border-white shadow-md dark:border-gray-800">
                        <AvatarImage :src="owner.avatar" />
                        <AvatarFallback class="bg-gradient-to-br from-primary to-blue-500 text-lg font-bold text-white">
                            {{ owner.name.split(' ').map((n: string) => n[0]).join('') }}
                        </AvatarFallback>
                    </Avatar>
                </div>

                <div class="flex-1 min-w-0">
                    <!-- Name + verified badge -->
                    <div class="mb-1 flex items-center gap-1.5">
                        <h3 class="truncate text-lg font-bold text-foreground hover:text-primary transition-colors duration-300 ease-out">
                            <Link :href="route('profile.show', { user: owner.id })">{{ owner.name }}</Link>
                        </h3>
                        <CheckCircle2 v-if="owner.verified" class="h-4.5 w-4.5 shrink-0 text-blue-500" />
                    </div>

                    <!-- Location -->
                    <p class="mb-1 flex items-center gap-1.5 text-sm text-muted-foreground">
                        <MapPin class="h-3.5 w-3.5 shrink-0 text-blue-400" />
                        {{ owner.location }}
                    </p>

                    <!-- Member Since -->
                    <p class="flex items-center gap-1.5 text-sm text-muted-foreground">
                        <Clock class="h-3.5 w-3.5 shrink-0 text-amber-400" />
                        Member since {{ owner.memberSince }}
                    </p>

                    <!-- Star Rating -->
                    <div class="mt-2 flex items-center gap-2">
                        <div class="flex items-center gap-0.5">
                            <Star
                                v-for="i in 5"
                                :key="i"
                                class="h-4 w-4"
                                :class="i <= Math.round(owner.rating)
                                    ? 'fill-amber-400 text-amber-400'
                                    : 'fill-muted text-muted-foreground/30'"
                            />
                        </div>
                        <span class="text-sm font-semibold text-foreground">{{ owner.rating }}</span>
                        <span class="text-xs text-muted-foreground">/ 5.0</span>
                    </div>
                </div>
            </div>

            <!-- CTA Buttons -->
            <div class="grid gap-2.5" :class="showContact ? 'grid-cols-2' : 'grid-cols-1'">
                <Button
                    v-if="user?.email_verified_at"
                    class="h-11 rounded-xl bg-gradient-to-r from-primary to-blue-500 font-semibold shadow transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg"
                    @click="$emit('call')"
                >
                    <Phone class="mr-2 h-4 w-4" />
                    Call Now
                </Button>
                <Button
                    v-if="showContact && user?.email_verified_at"
                    variant="outline"
                    class="h-11 rounded-xl border-2 border-primary/20 font-semibold transition-all duration-200 hover:border-primary/50 hover:bg-primary/5"
                    @click="$emit('message')"
                >
                    <MessageSquare class="mr-2 h-4 w-4 text-primary" />
                    Message
                </Button>
            </div>

            <!-- Response Stats -->
            <div class="mt-5 divide-y divide-border/50 rounded-xl border border-border/50 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 text-sm">
                    <span class="flex items-center gap-2 text-muted-foreground">
                        <CheckCircle2 class="h-4 w-4 text-green-500" />
                        Response rate
                    </span>
                    <strong class="text-foreground">98%</strong>
                </div>
                <div class="flex items-center justify-between px-4 py-3 text-sm">
                    <span class="flex items-center gap-2 text-muted-foreground">
                        <Clock class="h-4 w-4 text-blue-400" />
                        Response time
                    </span>
                    <strong class="text-foreground">Within an hour</strong>
                </div>
            </div>
        </div>
    </div>
</template>
