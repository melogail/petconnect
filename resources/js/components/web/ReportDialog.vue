<template>
    <Dialog :open="isOpen" @update:open="updateOpen">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>{{ t('reports.report_content') }}</DialogTitle>
                <DialogDescription>
                    {{ t('reports.description') }}
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitReport">
                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="reason">{{ t('reports.reason') }}</Label>
                        <Select v-model="reportReason">
                            <SelectTrigger>
                                <SelectValue
                                    :placeholder="t('reports.select_reason')"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="reason in reportReasons"
                                    :key="reason.value"
                                    :value="reason.value"
                                    >{{ reason.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">{{
                            t('reports.additional_details')
                        }}</Label>
                        <Textarea
                            id="description"
                            v-model="reportDescription"
                            :placeholder="
                                t('reports.additional_details_placeholder')
                            "
                            class="resize-none"
                            rows="4"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="cancel">{{
                        t('reports.cancel')
                    }}</Button>
                    <Button
                        type="submit"
                        :disabled="!reportReason || isSubmitting"
                        class="bg-gradient-to-r from-violet-500 to-fuchsia-500 text-white hover:opacity-90"
                    >
                        {{
                            isSubmitting
                                ? t('reports.submitting')
                                : t('reports.submit_report')
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const props = defineProps<{
    isOpen: boolean;
    contentId: string | number;
    reportableType: string;
    reportReasons: Array<{ value: string; label: string }>;
}>();

const emit = defineEmits(['close', 'submit']);

const { t } = useTranslations();

const reportReason = ref('');
const reportDescription = ref('');
const isSubmitting = ref(false);

const updateOpen = (value: boolean) => {
    if (!value) {
        emit('close');
    }
};

const reset = () => {
    reportReason.value = '';
    reportDescription.value = '';
};

const submitReport = () => {
    if (!reportReason.value) {
        return;
    }

    isSubmitting.value = true;

    router.post(
        route('reports.store'),
        {
            reportable_type: props.reportableType,
            reportable_id: props.contentId,
            reason: reportReason.value,
            description: reportDescription.value || null,
        },
        {
            onSuccess: () => {
                emit('submit');
                emit('close');
                reset();
            },
            onError: (errors) => {
                const reportableError = errors.reportable_id;
                const message = Array.isArray(reportableError)
                    ? reportableError[0]
                    : (reportableError ?? t('reports.failed'));
                toast.error(message);
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

const cancel = () => {
    reset();
    emit('close');
};
</script>
