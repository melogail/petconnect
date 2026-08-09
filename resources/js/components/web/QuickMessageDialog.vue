<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent class="sm:max-w-[500px]">
            <DialogHeader>
                <DialogTitle>{{
                    t('messaging.send_message_to', { name: ownerName })
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        t('messaging.send_quick_message', {
                            pet: displayPetName,
                        })
                    }}
                </DialogDescription>
            </DialogHeader>
            <DialogClose
                class="ring-offset-background focus:ring-ring absolute end-4 top-4 rounded-sm opacity-70 transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:pointer-events-none"
            >
                <span class="sr-only">{{ t('messaging.close') }}</span>
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="h-4 w-4"
                >
                    <path d="M18 6 6 18" />
                    <path d="m6 6 12 12" />
                </svg>
            </DialogClose>

            <form @submit.prevent="sendMessage" class="space-y-4 py-2">
                <div class="space-y-2">
                    <Label for="message">{{
                        t('messaging.your_message')
                    }}</Label>
                    <Textarea
                        id="message"
                        v-model="form.initial_message"
                        :placeholder="t('messaging.write_message_placeholder')"
                        class="min-h-[120px]"
                        :disabled="form.processing"
                        required
                    />
                    <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="closeDialog"
                    >
                        {{ t('messaging.cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :disabled="
                            !form.initial_message.trim() || form.processing
                        "
                        :class="[
                            'bg-gradient-to-r from-violet-500 to-fuchsia-500 hover:opacity-90',
                            {
                                'cursor-not-allowed opacity-75':
                                    !form.initial_message.trim() ||
                                    form.processing,
                            },
                        ]"
                    >
                        <span v-if="!form.processing">{{
                            t('messaging.send_message')
                        }}</span>
                        <Loader2 v-else class="h-4 w-4 animate-spin" />
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Loader2 } from 'lucide-vue-next';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { toast } from 'vue-sonner';
import { useTranslations } from '@/composables/useTranslations';

interface Props {
    ownerName: string;
    petName?: string;
    otherUserId?: number | null;
    open?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    petName: undefined,
    otherUserId: null,
    open: false,
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'message-sent'): void;
}>();

const { t } = useTranslations();

const displayPetName = computed(
    () => props.petName || t('messaging.this_listing'),
);

const error = ref('');
const form = useForm({
    other_user_id: props.otherUserId,
    initial_message: '',
});

const isDialogOpen = computed({
    get() {
        return props.open;
    },
    set(value: boolean) {
        emit('update:open', value);
    },
});

const closeDialog = () => {
    isDialogOpen.value = false;
};

watch(
    () => props.open,
    (open) => {
        if (open) {
            error.value = '';
        }
    },
);

const sendMessage = () => {
    if (!form.initial_message.trim()) {
        return;
    }

    if (!props.otherUserId) {
        error.value = t('messaging.unable_to_start');
        return;
    }

    error.value = '';
    form.transform((data) => ({
        ...data,
        other_user_id: props.otherUserId,
        initial_message: data.initial_message.trim(),
    })).post(route('conversations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('initial_message');
            emit('message-sent');
            closeDialog();
            toast.success(t('messaging.message_sent'));
        },
        onError: (errors) => {
            error.value = String(
                errors.initial_message ??
                    errors.other_user_id ??
                    t('messaging.failed_to_send'),
            );
            toast.error(error.value);
        },
        onFinish: () => {
            form.transform((data) => data);
        },
    });
};
</script>
