<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="text-destructive text-[13px]">
            {{ message }}
        </p>
    </div>
</template>
