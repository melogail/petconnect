<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <slot></slot>
        </DialogTrigger>
        <DialogContent class="sm:max-w-[500px]">
            <DialogHeader>
                <DialogTitle>Send a message to {{ ownerName }}</DialogTitle>
                <DialogDescription>
                    Send a quick message to the owner about {{ petName }}.
                </DialogDescription>
            </DialogHeader>
            <DialogClose class="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none">
                <span class="sr-only">Close</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                    <path d="M18 6 6 18"/>
                    <path d="m6 6 12 12"/>
                </svg>
            </DialogClose>

            <form @submit.prevent="sendMessage" class="space-y-4 py-2">
                <div class="space-y-2">
                    <Label for="message">Your Message</Label>
                    <Textarea
                        id="message"
                        v-model="message"
                        placeholder="Write your message here..."
                        class="min-h-[120px]"
                        :disabled="isSubmitting"
                        required
                    />
                    <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="isSubmitting"
                        @click="closeDialog"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="!message.trim() || isSubmitting"
                        :class="[
              'bg-gradient-to-r from-violet-500 to-fuchsia-500 hover:opacity-90',
              { 'opacity-75 cursor-not-allowed': !message.trim() || isSubmitting }
            ]"
                    >
                        <span v-if="!isSubmitting">Send Message</span>
                        <Loader2 v-else class="h-4 w-4 animate-spin" />
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Loader2 } from 'lucide-vue-next';
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
import { toast } from "vue-sonner"

interface Props {
    ownerName: string;
    petName: string;
    petId: string | number;
}

const props = withDefaults(defineProps<Props>(), {
  open: false
});

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'message-sent'): void;
}>();

const message = ref('');
const isSubmitting = ref(false);
const error = ref('');

// Create a computed property to handle v-model binding
const isDialogOpen = computed({
  get() {
    return props.open;
  },
  set(value: boolean) {
    emit('update:open', value);
  }
});

const closeDialog = () => {
  isDialogOpen.value = false;
};

const sendMessage = async () => {
    if (!message.value.trim() || isSubmitting.value) return;

    isSubmitting.value = true;
    error.value = '';

    try {
        // In a real app, you would send this to your API
        // const response = await axios.post(`/api/pets/${props.petId}/messages`, {
        //   message: message.value.trim(),
        //   petId: props.petId,
        // });

        // Simulate API call
        await new Promise((resolve) => setTimeout(resolve, 1000));

        // Show success toast
        toast.success('Message sent!', {
            description: 'Your message has been sent successfully.',
        });

        // Emit event
        emit('message-sent');

        // Reset form and close dialog
        message.value = '';
        closeDialog();

    } catch (err: unknown) {
        console.error('Error sending message:', err);

        // Handle different error types
        if (err instanceof Error) {
            error.value = err.message;
        } else {
            error.value = 'Failed to send message. Please try again.';
        }

        toast.error('Error', {
            description: error.value,
        });

    } finally {
        isSubmitting.value = false;
    }
};
</script>
