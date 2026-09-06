<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { update as updateMessage } from '@/routes/messages';
import type { Message } from '@/types';

/**
 * Rewrite a message inside the edit window.
 *
 * The save writes `edited_at`, which is what `is_edited` reads — the bubble
 * picks up the "edited" marker on the next render without any client bookkeeping.
 */
const { message } = defineProps<{ message: Message }>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="ghost" size="icon-sm" aria-label="Edit message">
                <Pencil class="size-3.5" />
            </Button>
        </DialogTrigger>

        <DialogContent>
            <DialogHeader>
                <DialogTitle>Edit message</DialogTitle>
                <DialogDescription>
                    The thread will show that this message was edited.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="updateMessage.form(message.id)"
                :options="{ preserveScroll: true, preserveState: true }"
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <div class="grid gap-2">
                    <Label for="message-content-edit">Message</Label>
                    <Textarea
                        id="message-content-edit"
                        name="content"
                        rows="4"
                        required
                        :default-value="message.content"
                    />
                    <InputError :message="errors.content" />
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Save
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
