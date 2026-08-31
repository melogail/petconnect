<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import { cn } from '@/lib/utils';

/**
 * The avatar for anybody the backend describes with `UserSummaryResource` —
 * a conversation peer, a message sender, a review author, a profile.
 *
 * `UserInfo.vue` is the auth-user variant that also renders the name and email;
 * this is the picture on its own.
 */
const {
    name,
    avatar = null,
    class: className,
} = defineProps<{
    name: string;
    avatar?: string | null;
    class?: string;
}>();

const { getInitials } = useInitials();

const initials = computed(() => getInitials(name));
</script>

<template>
    <Avatar :class="cn('size-10', className)">
        <AvatarImage v-if="avatar" :src="avatar" :alt="name" />
        <AvatarFallback class="text-xs font-medium">
            {{ initials }}
        </AvatarFallback>
    </Avatar>
</template>
