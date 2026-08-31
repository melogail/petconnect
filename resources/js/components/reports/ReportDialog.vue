<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Flag } from '@lucide/vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { store as storeReport } from '@/routes/reports';
import type {
    ReportCategory,
    ReportReason,
    ReportableType,
    SelectOption,
} from '@/types';

/**
 * Report a comment or a review.
 *
 * The option lists are page props (`profile.show`, `pets.show` carry them);
 * there is no options endpoint, so a page that hosts this dialog has to accept
 * and forward them.
 *
 * `CannotReportOwnContent` surfaces under the flow-level key `report`, not
 * under a field, so that message is rendered above the fields rather than
 * beside one.
 */
const {
    reportableType,
    reportableId,
    categories,
    reasons,
    reported = false,
} = defineProps<{
    reportableType: ReportableType;
    reportableId: number;
    categories: SelectOption<ReportCategory>[];
    reasons: SelectOption<ReportReason>[];
    /** Already reported by this viewer — the control stays visible but inert. */
    reported?: boolean;
}>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                variant="ghost"
                size="sm"
                :disabled="reported"
                class="text-muted-foreground"
            >
                <Flag class="size-4" />
                {{ reported ? 'Reported' : 'Report' }}
            </Button>
        </DialogTrigger>

        <DialogContent>
            <DialogHeader>
                <DialogTitle>Report this {{ reportableType }}</DialogTitle>
                <DialogDescription>
                    Tell us what is wrong with it. A moderator reviews every
                    report.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="
                    storeReport.form({
                        reportable_type: reportableType,
                        reportable_id: reportableId,
                    })
                "
                reset-on-success
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <InputError :message="errors.report" />

                <div class="grid gap-2">
                    <Label for="report-category">Category</Label>
                    <Select name="category" required>
                        <SelectTrigger id="report-category" class="w-full">
                            <SelectValue placeholder="Pick a category" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in categories"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.category" />
                </div>

                <div class="grid gap-2">
                    <Label for="report-reason">Reason</Label>
                    <Select name="reason" required>
                        <SelectTrigger id="report-reason" class="w-full">
                            <SelectValue placeholder="Pick a reason" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in reasons"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.reason" />
                </div>

                <div class="grid gap-2">
                    <Label for="report-description">
                        Anything else? (optional)
                    </Label>
                    <Textarea
                        id="report-description"
                        name="description"
                        rows="4"
                        placeholder="Add any detail that helps a moderator."
                    />
                    <InputError :message="errors.description" />
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Submit report
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
