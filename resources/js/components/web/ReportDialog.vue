<template>
    <Dialog :open="isOpen" @update:open="updateOpen">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Report Content</DialogTitle>
                <DialogDescription>
                    Please provide a reason for reporting this content. Our moderation team will review it.
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitReport">
                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="reason">Reason</Label>
                        <Select v-model="reportReason">
                            <SelectTrigger>
                                <SelectValue placeholder="Select a reason" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="inappropriate">Inappropriate Content</SelectItem>
                                <SelectItem value="spam">Spam</SelectItem>
                                <SelectItem value="harassment">Harassment</SelectItem>
                                <SelectItem value="misinformation">Misinformation</SelectItem>
                                <SelectItem value="other">Other</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="details">Additional Details</Label>
                        <Textarea
                            id="details"
                            v-model="reportDetails"
                            placeholder="Please provide more details about your report"
                            class="resize-none"
                            rows="4"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="cancel">Cancel</Button>
                    <Button
                        type="submit"
                        :disabled="!reportReason || isSubmitting"
                        class="bg-gradient-to-r from-violet-500 to-fuchsia-500 hover:opacity-90 text-white"
                    >
                        {{ isSubmitting ? 'Submitting...' : 'Submit Report' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue
} from '@/components/ui/select';

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true
    },
    contentType: {
        type: String,
        required: true
    },
    contentId: {
        type: [String, Number],
        required: true
    }
});

const emit = defineEmits(['close', 'submit']);

const reportReason = ref('');
const reportDetails = ref('');
const isSubmitting = ref(false);

const updateOpen = (value) => {
    if (!value) {
        emit('close');
    }
};

const submitReport = async () => {
    if (!reportReason.value) return;

    isSubmitting.value = true;

    try {
        // In a real app, you would send this to your backend
        // await axios.post('/api/reports', {
        //   content_type: props.contentType,
        //   content_id: props.contentId,
        //   reason: reportReason.value,
        //   details: reportDetails.value,
        // });

        // For now, we'll just simulate a successful submission
        console.log(`Reported ${props.contentType} ${props.contentId} for:`, {
            reason: reportReason.value,
            details: reportDetails.value
        });

        emit('submit', {
            contentType: props.contentType,
            contentId: props.contentId,
            reason: reportReason.value,
            details: reportDetails.value
        });

        // Reset form
        reportReason.value = '';
        reportDetails.value = '';
    } catch (error) {
        console.error('Error submitting report:', error);
    } finally {
        isSubmitting.value = false;
    }
};

const cancel = () => {
    reportReason.value = '';
    reportDetails.value = '';
    emit('close');
};
</script>
