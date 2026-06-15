<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import OrderInvoicesSection from '../../../Components/OrderInvoicesSection.vue';
import RfqGeneralInformationSection from '../../../Components/RfqGeneralInformationSection.vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    order: {
        type: Object,
        default: null,
    },
    canManage: {
        type: Boolean,
        default: false,
    },
    createUrl: {
        type: String,
        default: '',
    },
    returnTo: {
        type: String,
        default: 'orders',
    },
    supplierCompanyName: {
        type: String,
        default: '',
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
    loadError: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'retry']);

const copy = {
    title: 'Manage Invoices',
    intro: 'Add one or more invoices for this confirmed order. Review buyer payment proofs here as they arrive and confirm payment receipt invoice by invoice.',
    addInvoice: 'Add Invoice',
    editInvoice: 'Edit Invoice',
    confirmPayment: 'Confirm Payment Received',
    close: 'Close',
    cancel: 'Cancel',
    save: 'Save Invoice',
    update: 'Update Invoice',
    overview: 'Order Overview',
    existingInvoices: 'Invoice List',
    existingInvoicesIntro: 'Every invoice added for this order appears here together with buyer payment proof and payment confirmation status.',
    formTitle: 'Invoice Form',
    formIntro: 'Complete the invoice details exactly as they appear on the supplier invoice document.',
    referenceNo: 'Reference No',
    buyerCompany: 'Buyer Company',
    billTo: 'Invoice To',
    agreedTotal: 'Agreed Total',
    invoicedTotal: 'Already Invoiced',
    remainingTotal: 'Remaining Amount',
    invoiceNumber: 'Invoice Number',
    invoiceDate: 'Invoice Date',
    invoiceAmount: 'Invoice Amount',
    invoiceFile: 'Invoice File',
    invoiceNotes: 'Invoice Notes',
    currentFile: 'Current invoice file',
    replaceFile: 'Choose a new file only if you want to replace the current invoice document.',
    noFileYet: 'No invoice file uploaded yet.',
    emptyInvoicesTitle: 'No invoice has been added yet.',
    emptyInvoicesText: 'Use Add Invoice to start the supplier invoice workflow for this order.',
    invoiceLimitReached: 'The full agreed total has already been allocated across invoices. You can still review or edit existing invoices below.',
    retry: 'Retry',
    hints: {
        invoice_number: 'Use the invoice number exactly as it appears on the supplier invoice.',
        invoice_date: 'Choose the issue date printed on the invoice.',
        invoice_amount: 'Enter the invoice amount the buyer should pay for this invoice. The total of all invoices cannot exceed the agreed order total.',
        invoice_document: 'Upload the invoice document as PDF or image so the buyer can review it directly from the order workflow.',
        invoice_notes: 'Add any billing note, reference, or instruction the buyer should see together with the invoice.',
    },
    placeholders: {
        invoice_number: 'Enter the invoice number.',
        invoice_amount: 'Enter the invoice amount.',
        invoice_notes: 'Enter invoice notes, reference details, or billing instructions for the buyer.',
    },
    browse: 'Choose File',
    fileTypes: 'Accepted: PDF, JPG, PNG, WEBP up to 15 MB.',
};

const currentOrder = computed(() => props.order ?? {});
const invoices = computed(() => currentOrder.value.invoices ?? []);
const isSpareParts = computed(() => currentOrder.value.request_type === 'spare_parts');
const activeInvoiceId = ref(null);
const isFormVisible = ref(false);
const fieldRefs = {};

const currentInvoice = computed(() => invoices.value.find((invoice) => invoice.id === activeInvoiceId.value) ?? null);

const toNumber = (value) => {
    const numeric = Number(value ?? 0);
    return Number.isFinite(numeric) ? numeric : 0;
};

const textValue = (value) => String(value ?? '').trim();

const formatMoney = (value, currency = 'USD') => {
    const numeric = Number(value ?? 0);

    if (!Number.isFinite(numeric)) {
        return `${currency} ${value ?? '0'}`;
    }

    return `${currency} ${new Intl.NumberFormat('en-GB', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(numeric)}`;
};

const fallbackAgreedInvoiceAmount = computed(() => {
    const baseAmount = isSpareParts.value
        ? toNumber(currentOrder.value.selected_total)
        : toNumber(currentOrder.value.total_offer_amount);

    const visibleTaxAmount = currentOrder.value.including_tax ? 0 : toNumber(currentOrder.value.tax_amount);
    const visiblePackingAmount = currentOrder.value.including_packing ? 0 : toNumber(currentOrder.value.packing_cost);
    const visibleFreightAmount = currentOrder.value.including_freight ? 0 : toNumber(currentOrder.value.freight_cost);
    const visibleMobilizationAmount = currentOrder.value.including_mobilization ? 0 : toNumber(currentOrder.value.mobilization_cost);

    return isSpareParts.value
        ? baseAmount + visibleTaxAmount + visiblePackingAmount + visibleFreightAmount
        : baseAmount + visibleTaxAmount + visibleMobilizationAmount;
});

const agreedInvoiceAmount = computed(() => {
    const fromPayload = `${currentOrder.value.agreed_invoice_total ?? ''}`.trim();

    if (fromPayload !== '') {
        return toNumber(fromPayload);
    }

    return fallbackAgreedInvoiceAmount.value;
});

const currentInvoiceAmount = computed(() => toNumber(currentInvoice.value?.invoice_amount));

const invoicedTotal = computed(() => {
    const fromPayload = `${currentOrder.value.invoiced_total ?? ''}`.trim();

    if (fromPayload !== '' && currentInvoice.value === null) {
        return toNumber(fromPayload);
    }

    return invoices.value.reduce((total, invoice) => total + toNumber(invoice.invoice_amount), 0);
});

const invoicedTotalExcludingCurrent = computed(() => (
    invoices.value.reduce((total, invoice) => {
        if (currentInvoice.value && invoice.id === currentInvoice.value.id) {
            return total;
        }

        return total + toNumber(invoice.invoice_amount);
    }, 0)
));

const remainingInvoiceAmount = computed(() => (
    Math.max(0, Number((agreedInvoiceAmount.value - invoicedTotal.value).toFixed(2)))
));

const allowedInvoiceAmount = computed(() => (
    Math.max(0, Number((agreedInvoiceAmount.value - invoicedTotalExcludingCurrent.value).toFixed(2)))
));

const canAddInvoice = computed(() => remainingInvoiceAmount.value > 0);

const decimalFieldString = (value) => {
    const formatted = Number(value ?? 0).toFixed(2);
    return formatted.replace(/\.00$/, '').replace(/(\.\d*[1-9])0$/, '$1');
};

const agreedInvoiceAmountField = computed(() => decimalFieldString(agreedInvoiceAmount.value));
const invoicedTotalField = computed(() => decimalFieldString(invoicedTotal.value));
const remainingInvoiceAmountField = computed(() => decimalFieldString(remainingInvoiceAmount.value));
const allowedInvoiceAmountField = computed(() => decimalFieldString(allowedInvoiceAmount.value));

const overviewFields = computed(() => ([
    { key: 'reference_no', label: copy.referenceNo, value: currentOrder.value.reference_no || '-' },
    { key: 'agreed_total', label: copy.agreedTotal, value: formatMoney(agreedInvoiceAmount.value, currentOrder.value.currency || 'USD') },
    { key: 'buyer_company', label: copy.buyerCompany, value: currentOrder.value.company_name || '-' },
    { key: 'invoiced_total', label: copy.invoicedTotal, value: formatMoney(invoicedTotalField.value, currentOrder.value.currency || 'USD') },
    { key: 'bill_to', label: copy.billTo, value: currentOrder.value.billing_company_name || '-' },
    { key: 'remaining_total', label: copy.remainingTotal, value: formatMoney(remainingInvoiceAmountField.value, currentOrder.value.currency || 'USD') },
]));

const buildInitialForm = (invoice = null) => ({
    invoice_number: invoice?.invoice_number || '',
    invoice_date: invoice?.invoice_date || '',
    invoice_amount: invoice?.invoice_amount || remainingInvoiceAmountField.value,
    invoice_document: null,
    invoice_notes: invoice?.invoice_notes || '',
});

const form = useForm(buildInitialForm());

const resetForm = (invoice = null) => {
    form.defaults(buildInitialForm(invoice));
    form.reset();
    form.clearErrors();
};

watch(
    () => [props.isOpen, currentOrder.value?.id],
    ([isOpen]) => {
        if (isOpen) {
            activeInvoiceId.value = null;
            isFormVisible.value = false;
            resetForm();
        }
    },
    { immediate: true }
);

const validateTextField = (value, { label, required = false, max = null }) => {
    const text = textValue(value);

    if (required && !text) {
        return `${label} is required.`;
    }

    if (!text) {
        return '';
    }

    if (max && text.length > max) {
        return `${label} must be ${max} characters or less.`;
    }

    return '';
};

const validateDateField = (value, { label }) => {
    const text = textValue(value);

    if (!text) {
        return `${label} is required.`;
    }

    if (Number.isNaN(Date.parse(text))) {
        return `Enter a valid ${label.toLowerCase()}.`;
    }

    return '';
};

const validateAmountField = (value, { label }) => {
    const text = textValue(value);

    if (!text) {
        return `${label} is required.`;
    }

    const numeric = Number(text);

    if (!Number.isFinite(numeric) || numeric <= 0) {
        return `Enter a valid ${label.toLowerCase()} greater than 0.`;
    }

    if (allowedInvoiceAmount.value <= 0 && !currentInvoice.value) {
        return 'No remaining agreed amount is available for a new invoice.';
    }

    if (numeric > allowedInvoiceAmount.value + 0.00001) {
        return `Invoice amount cannot exceed the remaining agreed total of ${formatMoney(allowedInvoiceAmount.value, currentOrder.value.currency || 'USD')}.`;
    }

    return '';
};

const validateFileField = (value) => {
    if (!value && !currentInvoice.value?.invoice_document?.url) {
        return `${copy.invoiceFile} is required.`;
    }

    if (!value) {
        return '';
    }

    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    if (value.type && !allowedTypes.includes(value.type)) {
        return 'Upload a PDF, JPG, PNG, or WEBP file.';
    }

    if ((value.size ?? 0) > 15 * 1024 * 1024) {
        return 'Invoice file must be 15 MB or smaller.';
    }

    return '';
};

const validationRules = computed(() => ({
    invoice_number: () => validateTextField(form.invoice_number, { label: copy.invoiceNumber, required: true, max: 120 }),
    invoice_date: () => validateDateField(form.invoice_date, { label: copy.invoiceDate }),
    invoice_amount: () => validateAmountField(form.invoice_amount, { label: copy.invoiceAmount }),
    invoice_document: () => validateFileField(form.invoice_document),
    invoice_notes: () => validateTextField(form.invoice_notes, { label: copy.invoiceNotes, max: 2000 }),
}));

const validateField = (field) => {
    const rule = validationRules.value[field];
    return typeof rule === 'function' ? rule() : '';
};

const hasVisualInvalid = (field) => {
    const errorText = String(form.errors[field] ?? '').trim();

    if (!errorText) {
        return false;
    }

    return Boolean(validateField(field));
};

const visibleFieldError = (field) => {
    const errorText = String(form.errors[field] ?? '').trim();

    if (!errorText) {
        return '';
    }

    return validateField(field) ? errorText : '';
};

const clearFieldErrorIfValid = (field) => {
    if (validateField(field) === '') {
        form.clearErrors(field);
    }
};

const registerFieldRef = (field, element) => {
    if (element) {
        fieldRefs[field] = element;
    }
};

const focusFirstInvalidField = async (errors) => {
    const firstField = Object.keys(validationRules.value).find((field) => Boolean(errors[field]));

    if (!firstField) {
        return;
    }

    await nextTick();
    fieldRefs[firstField]?.focus?.();
};

const handleFileChange = (event) => {
    form.invoice_document = event.target.files?.[0] ?? null;
    clearFieldErrorIfValid('invoice_document');
};

const openCreateForm = () => {
    activeInvoiceId.value = null;
    isFormVisible.value = true;
    resetForm();
};

const openEditForm = (invoice) => {
    activeInvoiceId.value = invoice.id;
    isFormVisible.value = true;
    resetForm(invoice);
};

const closeForm = () => {
    activeInvoiceId.value = null;
    isFormVisible.value = false;
    resetForm();
};

const closeModal = () => {
    if (form.processing) {
        return;
    }

    emit('close');
};

const submitInvoice = async () => {
    const submitUrl = currentInvoice.value?.update_url || props.createUrl;

    if (!props.canManage || !submitUrl) {
        return;
    }

    form.clearErrors();

    const errors = Object.fromEntries(
        Object.entries(validationRules.value)
            .map(([field, rule]) => [field, rule()])
            .filter(([, message]) => message)
    );

    if (Object.keys(errors).length) {
        form.setError(errors);
        await focusFirstInvalidField(errors);
        return;
    }

    form
        .transform((data) => ({
            ...data,
            return_to: props.returnTo,
        }))
        .post(submitUrl, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => emit('close'),
        });
};

const confirmPayment = (invoice) => {
    if (!invoice?.confirm_payment_url) {
        return;
    }

    router.post(invoice.confirm_payment_url, {
        return_to: props.returnTo,
    }, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <div v-if="isOpen" class="detail-modal-backdrop" @click.self="closeModal">
        <div class="detail-modal">
            <div class="detail-modal-head">
                <div class="detail-modal-copy">
                    <h3 class="detail-modal-title">{{ copy.title }}</h3>
                    <p class="detail-modal-intro">{{ copy.intro }}</p>
                </div>

                <button type="button" class="detail-modal-close" @click="closeModal">
                    {{ copy.close }}
                </button>
            </div>

            <div class="detail-modal-body">
                <div v-if="isLoading" class="modal-state-card">
                    <strong>Loading order details...</strong>
                    <p>Please wait while the invoice workflow data is prepared.</p>
                </div>

                <div v-else-if="loadError" class="modal-state-card modal-state-card-error">
                    <strong>Order details could not be loaded.</strong>
                    <p>{{ loadError }}</p>
                    <div class="form-actions">
                        <button type="button" class="primary-action" @click="emit('retry')">
                            {{ copy.retry }}
                        </button>
                    </div>
                </div>

                <div v-else class="invoice-form">
                    <section class="section-card">
                        <RfqGeneralInformationSection
                            :title="copy.overview"
                            :fields="overviewFields"
                            :columns="2"
                            :label-width="150"
                            :wrap-labels="true"
                            :small-text="true"
                        />
                    </section>

                    <section class="section-card">
                        <div class="section-head section-head-inline">
                            <div>
                                <p class="directory-eyebrow section-card-eyebrow">{{ copy.existingInvoices }}</p>
                                <p class="section-copy">{{ copy.existingInvoicesIntro }}</p>
                            </div>

                            <button
                                v-if="canManage && canAddInvoice"
                                type="button"
                                class="secondary-action secondary-action-highlight"
                                @click="openCreateForm"
                            >
                                {{ copy.addInvoice }}
                            </button>
                        </div>

                        <p v-if="canManage && !canAddInvoice" class="section-copy">
                            {{ copy.invoiceLimitReached }}
                        </p>

                        <OrderInvoicesSection
                            :title="copy.existingInvoices"
                            :intro="''"
                            :invoices="invoices"
                            :empty-title="copy.emptyInvoicesTitle"
                            :empty-text="copy.emptyInvoicesText"
                            :show-heading="false"
                            :buyer-label="copy.buyerCompany"
                            :buyer-name="currentOrder.company_name || ''"
                            :supplier-name="supplierCompanyName"
                        >
                            <template #actions="{ invoice }">
                                <button
                                    v-if="invoice.can_edit_invoice"
                                    type="button"
                                    class="secondary-action"
                                    @click="openEditForm(invoice)"
                                >
                                    {{ copy.editInvoice }}
                                </button>
                                <button
                                    v-if="invoice.can_confirm_payment"
                                    type="button"
                                    class="primary-action"
                                    @click="confirmPayment(invoice)"
                                >
                                    {{ copy.confirmPayment }}
                                </button>
                            </template>

                            <template #details="{ invoice }">
                                <div v-if="isFormVisible && currentInvoice?.id === invoice.id" class="inline-editor-shell">
                                    <div class="section-head">
                                        <p class="directory-eyebrow section-card-eyebrow">{{ copy.formTitle }}</p>
                                        <p class="section-copy">{{ copy.formIntro }}</p>
                                    </div>

                                    <div class="identity-surface">
                                        <div class="section-form section-form-narrow">
                                            <div class="form-grid">
                                                <label class="field">
                                                    <span>{{ copy.invoiceNumber }} *</span>
                                                    <span class="field-hint">{{ copy.hints.invoice_number }}</span>
                                                    <div class="input-shell" :class="{ invalid: hasVisualInvalid('invoice_number') }">
                                                        <input :ref="(el) => registerFieldRef('invoice_number', el)" v-model="form.invoice_number" type="text" :placeholder="copy.placeholders.invoice_number" @input="clearFieldErrorIfValid('invoice_number')">
                                                    </div>
                                                    <span class="field-feedback">{{ visibleFieldError('invoice_number') }}</span>
                                                </label>

                                                <label class="field">
                                                    <span>{{ copy.invoiceDate }} *</span>
                                                    <span class="field-hint">{{ copy.hints.invoice_date }}</span>
                                                    <div class="input-shell" :class="{ invalid: hasVisualInvalid('invoice_date') }">
                                                        <input :ref="(el) => registerFieldRef('invoice_date', el)" v-model="form.invoice_date" type="date" @input="clearFieldErrorIfValid('invoice_date')" @change="clearFieldErrorIfValid('invoice_date')">
                                                    </div>
                                                    <span class="field-feedback">{{ visibleFieldError('invoice_date') }}</span>
                                                </label>

                                                <label class="field">
                                                    <span>{{ copy.invoiceAmount }} *</span>
                                                    <span class="field-hint">{{ copy.hints.invoice_amount }}</span>
                                                    <span class="field-hint field-hint-compact">
                                                        Maximum for this invoice: {{ formatMoney(allowedInvoiceAmountField, currentOrder.currency || 'USD') }}
                                                    </span>
                                                    <div class="input-shell" :class="{ invalid: hasVisualInvalid('invoice_amount') }">
                                                        <input :ref="(el) => registerFieldRef('invoice_amount', el)" v-model="form.invoice_amount" type="number" min="0.01" step="0.01" :placeholder="copy.placeholders.invoice_amount" @input="clearFieldErrorIfValid('invoice_amount')">
                                                    </div>
                                                    <span class="field-feedback">{{ visibleFieldError('invoice_amount') }}</span>
                                                </label>

                                                <label class="field">
                                                    <span>{{ copy.invoiceFile }} *</span>
                                                    <span class="field-hint">{{ copy.hints.invoice_document }}</span>
                                                    <div class="file-shell" :class="{ invalid: hasVisualInvalid('invoice_document') }">
                                                        <input :ref="(el) => registerFieldRef('invoice_document', el)" class="file-input" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" @change="handleFileChange">
                                                        <span class="file-button">{{ copy.browse }}</span>
                                                        <span class="file-name">{{ form.invoice_document?.name || copy.noFileYet }}</span>
                                                    </div>
                                                    <span class="field-hint field-hint-compact">{{ copy.fileTypes }}</span>
                                                    <span v-if="currentInvoice?.invoice_document?.name" class="field-hint field-hint-compact">
                                                        {{ copy.currentFile }}: {{ currentInvoice.invoice_document.name }}
                                                    </span>
                                                    <span v-if="currentInvoice?.invoice_document?.name" class="field-hint field-hint-compact">
                                                        {{ copy.replaceFile }}
                                                    </span>
                                                    <span class="field-feedback">{{ visibleFieldError('invoice_document') }}</span>
                                                </label>

                                                <label class="field form-field-wide">
                                                    <span>{{ copy.invoiceNotes }}</span>
                                                    <span class="field-hint">{{ copy.hints.invoice_notes }}</span>
                                                    <div class="input-shell input-shell-textarea" :class="{ invalid: hasVisualInvalid('invoice_notes') }">
                                                        <textarea :ref="(el) => registerFieldRef('invoice_notes', el)" v-model="form.invoice_notes" rows="4" :placeholder="copy.placeholders.invoice_notes" @input="clearFieldErrorIfValid('invoice_notes')"></textarea>
                                                    </div>
                                                    <span class="field-feedback">{{ visibleFieldError('invoice_notes') }}</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-actions">
                                        <button type="button" class="secondary-action" :disabled="form.processing" @click="closeForm">
                                            {{ copy.cancel }}
                                        </button>
                                        <button type="button" class="primary-action" :disabled="form.processing || !canManage" @click="submitInvoice">
                                            {{ currentInvoice ? copy.update : copy.save }}
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </OrderInvoicesSection>

                        <div v-if="isFormVisible && !currentInvoice" class="inline-editor-shell">
                            <div class="section-head">
                                <p class="directory-eyebrow section-card-eyebrow">{{ copy.formTitle }}</p>
                                <p class="section-copy">{{ copy.formIntro }}</p>
                            </div>

                            <div class="identity-surface">
                                <div class="section-form section-form-narrow">
                                    <div class="form-grid">
                                        <label class="field">
                                            <span>{{ copy.invoiceNumber }} *</span>
                                            <span class="field-hint">{{ copy.hints.invoice_number }}</span>
                                            <div class="input-shell" :class="{ invalid: hasVisualInvalid('invoice_number') }">
                                                <input :ref="(el) => registerFieldRef('invoice_number', el)" v-model="form.invoice_number" type="text" :placeholder="copy.placeholders.invoice_number" @input="clearFieldErrorIfValid('invoice_number')">
                                            </div>
                                            <span class="field-feedback">{{ visibleFieldError('invoice_number') }}</span>
                                        </label>

                                        <label class="field">
                                            <span>{{ copy.invoiceDate }} *</span>
                                            <span class="field-hint">{{ copy.hints.invoice_date }}</span>
                                            <div class="input-shell" :class="{ invalid: hasVisualInvalid('invoice_date') }">
                                                <input :ref="(el) => registerFieldRef('invoice_date', el)" v-model="form.invoice_date" type="date" @input="clearFieldErrorIfValid('invoice_date')" @change="clearFieldErrorIfValid('invoice_date')">
                                            </div>
                                            <span class="field-feedback">{{ visibleFieldError('invoice_date') }}</span>
                                        </label>

                                        <label class="field">
                                            <span>{{ copy.invoiceAmount }} *</span>
                                            <span class="field-hint">{{ copy.hints.invoice_amount }}</span>
                                            <span class="field-hint field-hint-compact">
                                                Maximum for this invoice: {{ formatMoney(allowedInvoiceAmountField, currentOrder.currency || 'USD') }}
                                            </span>
                                            <div class="input-shell" :class="{ invalid: hasVisualInvalid('invoice_amount') }">
                                                <input :ref="(el) => registerFieldRef('invoice_amount', el)" v-model="form.invoice_amount" type="number" min="0.01" step="0.01" :placeholder="copy.placeholders.invoice_amount" @input="clearFieldErrorIfValid('invoice_amount')">
                                            </div>
                                            <span class="field-feedback">{{ visibleFieldError('invoice_amount') }}</span>
                                        </label>

                                        <label class="field">
                                            <span>{{ copy.invoiceFile }} *</span>
                                            <span class="field-hint">{{ copy.hints.invoice_document }}</span>
                                            <div class="file-shell" :class="{ invalid: hasVisualInvalid('invoice_document') }">
                                                <input :ref="(el) => registerFieldRef('invoice_document', el)" class="file-input" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" @change="handleFileChange">
                                                <span class="file-button">{{ copy.browse }}</span>
                                                <span class="file-name">{{ form.invoice_document?.name || copy.noFileYet }}</span>
                                            </div>
                                            <span class="field-hint field-hint-compact">{{ copy.fileTypes }}</span>
                                            <span class="field-feedback">{{ visibleFieldError('invoice_document') }}</span>
                                        </label>

                                        <label class="field form-field-wide">
                                            <span>{{ copy.invoiceNotes }}</span>
                                            <span class="field-hint">{{ copy.hints.invoice_notes }}</span>
                                            <div class="input-shell input-shell-textarea" :class="{ invalid: hasVisualInvalid('invoice_notes') }">
                                                <textarea :ref="(el) => registerFieldRef('invoice_notes', el)" v-model="form.invoice_notes" rows="4" :placeholder="copy.placeholders.invoice_notes" @input="clearFieldErrorIfValid('invoice_notes')"></textarea>
                                            </div>
                                            <span class="field-feedback">{{ visibleFieldError('invoice_notes') }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="secondary-action" :disabled="form.processing" @click="closeForm">
                                    {{ copy.cancel }}
                                </button>
                                <button type="button" class="primary-action" :disabled="form.processing || !canManage" @click="submitInvoice">
                                    {{ currentInvoice ? copy.update : copy.save }}
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.detail-modal-backdrop{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(15,23,42,.55);backdrop-filter:blur(10px);z-index:2200}
.detail-modal{width:min(1040px,calc(100vw - 32px));max-height:min(92vh,980px);display:grid;grid-template-rows:auto minmax(0,1fr);background:#fff;border:1px solid rgba(148,163,184,.35);border-radius:24px;box-shadow:0 32px 80px rgba(15,23,42,.24);overflow:hidden}
.detail-modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:24px 28px 18px;border-bottom:1px solid rgba(226,232,240,.9)}
.detail-modal-copy{display:grid;gap:8px}
.detail-modal-title{margin:0;color:#0f172a;font-size:1.1rem;font-weight:700}
.detail-modal-intro{margin:0;color:#64748b;font-size:.92rem;line-height:1.7;max-width:72ch}
.detail-modal-close{border:0;background:transparent;color:#2563eb;font-size:.88rem;font-weight:700;cursor:pointer}
.detail-modal-body{padding:22px 28px 28px;overflow:auto}
.modal-state-card{display:grid;gap:12px;padding:24px 26px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.94);box-shadow:0 20px 42px rgba(15,23,42,.06)}
.modal-state-card strong{color:#0f172a;font-size:1rem;font-weight:700}
.modal-state-card p{margin:0;color:#64748b;font-size:.92rem;line-height:1.7}
.modal-state-card-error{border-color:rgba(217,45,32,.18)}
.invoice-form,.section-form,.section-form-narrow{display:grid;gap:16px}
.section-card{display:grid;gap:18px;padding:24px 26px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.94);box-shadow:0 20px 42px rgba(15,23,42,.06)}
.inline-editor-shell{display:grid;gap:18px;margin-top:18px;padding-top:18px;border-top:1px solid rgba(148,163,184,.16)}
.section-head{display:grid;gap:8px}
.section-head-inline{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
.section-card-eyebrow,.section-copy{margin:0}
.section-copy{color:#64748b;font-size:.9rem;line-height:1.7}
.identity-surface{padding:24px;border-radius:10px;background:#f8fafb;min-width:0}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px 18px}
.field{display:grid;gap:8px}
.form-field-wide{grid-column:span 2}
.field-hint{color:#64748b;font-size:.88rem;line-height:1.55}
.field-hint-compact{margin-top:-2px}
.field>span:not(.field-hint):not(.field-feedback){display:block;color:#0f172a;font-size:.95rem;font-weight:620;line-height:1.45}
.input-shell{display:flex;align-items:center;min-height:52px;overflow:hidden;border:1px solid rgba(4,21,31,.12);border-radius:10px;background:#fff}
.input-shell.invalid,.file-shell.invalid{border-color:rgba(217,45,32,.5);box-shadow:0 0 0 3px rgba(217,45,32,.08)}
.input-shell input,.input-shell textarea{width:100%;border:0;outline:0;background:transparent;color:#0f172a;font:inherit}
.input-shell input{min-height:50px;padding:0 16px}
.input-shell textarea{padding:14px 16px;resize:vertical;min-height:112px}
.input-shell input::placeholder,.input-shell textarea::placeholder{color:#94a3b8}
.input-shell-textarea{align-items:stretch}
.file-shell{position:relative;display:flex;align-items:center;min-height:52px;padding:0 14px;border:1px solid rgba(4,21,31,.12);border-radius:10px;background:#fff;gap:12px}
.file-input{position:absolute;inset:0;opacity:0;cursor:pointer}
.file-button{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:0 12px;border-radius:999px;background:#eff6ff;color:#2563eb;font-size:.82rem;font-weight:700;white-space:nowrap}
.file-name{color:#0f172a;font-size:.9rem;line-height:1.45;word-break:break-word}
.field-feedback{min-height:20px;color:#d92d20;font-size:.82rem;line-height:1.45}
.form-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px}
.primary-action,.secondary-action{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 18px;border-radius:10px;font-size:.9rem;font-weight:700}
.primary-action{border:0;background:#2563eb;color:#fff;box-shadow:0 12px 24px rgba(37,99,235,.2)}
.secondary-action{border:1px solid #cbd5e1;background:#fff;color:#0f172a}
.secondary-action-highlight{border-color:#bfdbfe;background:#eff6ff;color:#1d4ed8}
.primary-action:disabled,.secondary-action:disabled{opacity:.6;cursor:not-allowed}
@media (max-width: 960px){
    .form-grid{grid-template-columns:1fr}
    .form-field-wide{grid-column:span 1}
    .section-head-inline{flex-direction:column;align-items:stretch}
}
@media (max-width: 720px){
    .detail-modal{width:min(100vw - 20px,1040px);max-height:min(92vh,980px)}
    .detail-modal-head{padding:20px 20px 16px;flex-direction:column}
    .detail-modal-body{padding:18px 20px 20px}
    .identity-surface,.section-card{padding:20px}
    .form-actions{justify-content:stretch;flex-direction:column-reverse}
    .primary-action,.secondary-action{width:100%}
}
</style>
