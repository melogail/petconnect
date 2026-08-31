<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { MessageCircle, Plus } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NotificationBell from '@/components/notifications/NotificationBell.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserAvatar from '@/components/UserAvatar.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { home, login, register } from '@/routes';
import { index as conversationsIndex } from '@/routes/conversations';
import { create as createPet } from '@/routes/pets';

/**
 * The bar above every page a guest can reach.
 *
 * `AppHeader` is the signed-in variant and assumes `auth.user`; the five pages
 * `app.ts` maps to `PublicLayout` are all reachable without an account, so
 * everything below is guarded on the shared prop rather than assumed.
 *
 * The notification bell sits inside that same `v-if="user"` and not outside it:
 * every `notifications.*` route is behind `auth` + `verified`, so a bell shown
 * to a guest would fetch its badge straight into a redirect to login.
 */
const page = usePage();

const user = computed(() => page.props.auth.user ?? null);
</script>

<template>
    <header
        class="border-border bg-background/95 sticky top-0 z-40 border-b backdrop-blur"
    >
        <div
            class="mx-auto flex h-16 w-full max-w-7xl items-center gap-3 px-4 sm:px-6"
        >
            <Link :href="home()" class="flex items-center">
                <AppLogo />
            </Link>

            <div class="flex-1" />

            <template v-if="user">
                <Button as-child variant="ghost" size="icon">
                    <Link :href="conversationsIndex()" aria-label="Messages">
                        <MessageCircle class="size-5" />
                    </Link>
                </Button>

                <NotificationBell />

                <Button as-child variant="outline">
                    <Link :href="createPet()">
                        <Plus class="size-4" />
                        <span class="hidden sm:inline">Publish a listing</span>
                    </Link>
                </Button>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="rounded-full"
                            aria-label="Account"
                        >
                            <UserAvatar
                                :name="user.name"
                                :avatar="user.avatar"
                                class="size-8"
                            />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>

            <template v-else>
                <Button as-child variant="ghost">
                    <Link :href="login()">Log in</Link>
                </Button>
                <Button as-child>
                    <Link :href="register()">Register</Link>
                </Button>
            </template>
        </div>
    </header>
</template>
