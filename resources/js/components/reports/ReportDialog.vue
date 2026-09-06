<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Flag } from '@lucide/vue';
import { computed } from 'vue';
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
import { useMutationSurface } from '@/composables/useMutationSurface';
import { useTranslations } from '@/composables/useTranslations';
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
 * and forward them. A surface that has neither does not render this control at
 * all rather than offering two empty selects — see `CommentBody`.
 *
 * `CannotReportOwnContent` surfaces under the flow-level key `report`, not
 * under a field, so that message is rendered above the fields rather than
 * beside one.
 *
 * ## It can be driven from outside
 *
 * `open` is a `defineModel`, and `showTrigger` turns off the built-in flag
 * button. That is how legacy arranges it — its `ReportDialog` takes `isOpen`
 * and is opened from the "Report" entry in a comment's overflow menu, with no
 * trigger of its own — and it is what a menu item needs: a `DialogTrigger`
 * inside a `DropdownMenuItem` is unmounted by the menu closing before the
 * dialog can open. Left alone, both default to the standalone behaviour the
 * profile page's review cards already use.
 *
 * ## `reported` is honoured in both arrangements
 *
 * `EnsureNotAlreadyReported` answers a second report of the same target with a
 * 422, so a form that can only end that way is not offered. With the trigger
 * on, the trigger is disabled and says so; with the trigger off — where the
 * dialog is opened by something outside it that may not have checked — the body
 * is the same statement instead of the form. The caller is still expected to
 * hide its own affordance (`CommentBody.canReport` does), so this is the second
 * line, not the first.
 */
const {
    reportableType,
    reportableId,
    categories,
    reasons,
    reported = false,
    showTrigger = true,
} = defineProps<{
    reportableType: ReportableType;
    reportableId: number;
    categories: SelectOption<ReportCategory>[];
    reasons: SelectOption<ReportReason>[];
    /** Already reported by this viewer — no form is offered either way. */
    reported?: boolean;
    /** Render the built-in flag button. Off when a menu item opens this. */
    showTrigger?: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });

const { t } = useTranslations();
const surface = useMutationSurface();

const options = computed(() => ({ preserveScroll: true, ...surface.visit }));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger v-if="showTrigger" as-child>
            <Button
                variant="ghost"
                size="sm"
                :disabled="reported"
                class="text-muted-foreground"
            >
                <Flag class="size-4" aria-hidden="true" />
                {{
                    reported
                        ? t('comments.already_reported')
                        : t('common.report')
                }}
            </Button>
        </DialogTrigger>

        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>{{ t('reports.report_content') }}</DialogTitle>
                <DialogDescription>
                    {{
                        reported
                            ? t('comments.already_reported')
                            : t('reports.description')
                    }}
                </DialogDescription>
            </DialogHeader>

            <DialogFooter v-if="reported">
                <Button type="button" variant="outline" @click="open = false">
                    {{ t('common.close') }}
                </Button>
            </DialogFooter>

            <Form
                v-else
                v-bind="
                    storeReport.form({
                        reportable_type: reportableType,
                        reportable_id: reportableId,
                    })
                "
                reset-on-success
                :options="options"
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="
                    open = false;
                    surface.onMutated();
                "
            >
                <InputError :message="errors.report" />

                <div class="grid gap-2">
                    <Label for="report-category">
                        {{ t('reports.category') }}
                    </Label>
                    <Select name="category" required>
                        <SelectTrigger id="report-category" class="w-full">
                            <SelectValue
                                :placeholder="t('reports.select_category')"
                            />
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
                    <Label for="report-reason">
                        {{ t('reports.reason') }}
                    </Label>
                    <Select name="reason" required>
                        <SelectTrigger id="report-reason" class="w-full">
                            <SelectValue
                                :placeholder="t('reports.select_reason')"
                            />
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
                        {{ t('reports.additional_details') }}
                        {{ t('common.optional') }}
                    </Label>
                    <Textarea
                        id="report-description"
                        name="description"
                        rows="4"
                        class="resize-none"
                        :placeholder="
                            t('reports.additional_details_placeholder')
                        "
                    />
                    <InputError :message="errors.description" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="processing"
                        @click="open = false"
                    >
                        {{ t('reports.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{
                            processing
                                ? t('reports.submitting')
                                : t('reports.submit_report')
                        }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
