<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
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
    returnTo: {
        type: String,
        default: 'orders',
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
    title: 'Invoice & Payment Proof',
    intro: 'Review supplier invoices here and upload the matching payment proof invoice by invoice once payment is completed.',
    overview: 'Order Overview',
    close: 'Close',
    uploadProof: 'Upload Payment Proof',
    updateProof: 'Update Payment Proof',
    cancel: 'Cancel',
    save: 'Save Payment Proof',
    update: 'Update Payment Proof',
    invoiceList: 'Invoice List',
    invoiceListIntro: 'Every supplier invoice and its current payment confirmation status appears here.',
    retry: 'Retry',
    formTitle: 'Payment Proof Form',
    formIntro: 'Attach the bank slip or payment confirmation file that belongs to the selected invoice.',
    referenceNo: 'Reference No',
    buyerCompany: 'Buyer Company',
    supplier: 'Supplier',
    ship: 'Ship',
    agreedTotal: 'Agreed Total',
    invoicedTotal: 'Already Invoiced',
    remainingTotal: 'Remaining Amount',
    orderTotal: 'Order Total',
    emptyInvoicesTitle: 'No invoice has been added yet.',
    emptyInvoicesText: 'The supplier invoice list will appear here once the supplier uploads the first invoice.',
    paymentDate: 'Payment Date',
    paymentReference: 'Payment Reference',
    paymentFile: 'Payment Proof File',
    paymentNotes: 'Payment Notes',
    currentFile: 'Current payment proof file',
    replaceFile: 'Choose a new file only if you want to replace the current payment proof document.',
    noFileYet: 'No payment proof file uploaded yet.',
    hints: {
        payment_proof_date: 'Choose the date the payment was completed or the bank slip was issued.',
        payment_reference: 'Add the transfer number, transaction reference, or any traceable payment code if available.',
        payment_proof_document: 'Upload the bank slip, payment confirmation PDF, or image proof the supplier should review.',
        payment_notes: 'Add any note the supplier should read together with the payment confirmation.',
    },
    placeholders: {
        payment_reference: 'Enter the payment reference or transfer number.',
        payment_notes: 'Enter payment notes, remittance notes, or supplier instructions.',
    },
    browse: 'Choose File',
    fileTypes: 'Accepted: PDF, JPG, PNG, WEBP up to 15 MB.',
};

const currentOrder = computed(() => props.order ?? {});
const invoices = computed(() => currentOrder.value.invoices ?? []);
const activeInvoiceId = ref(null);
const isFormVisible = ref(false);
const fieldRefs = {};

const toNumber = (value) => {
    const numeric = Number(value ?? 0);
    return Number.isFinite(numeric) ? numeric : 0;
};

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

const orderTotal = computed(() => (
    `${currentOrder.value.agreed_invoice_total ?? ''}`.trim() !== ''
        ? currentOrder.value.agreed_invoice_total
        : currentOrder.value.selected_total
));

const isSpareParts = computed(() => currentOrder.value.request_type === 'spare_parts');

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

const invoicedTotal = computed(() => {
    const fromPayload = `${currentOrder.value.invoiced_total ?? ''}`.trim();

    if (fromPayload !== '') {
        return toNumber(fromPayload);
    }

    return invoices.value.reduce((total, invoice) => total + toNumber(invoice.invoice_amount), 0);
});

const remainingInvoiceAmount = computed(() => (
    Math.max(0, Number((agreedInvoiceAmount.value - invoicedTotal.value).toFixed(2)))
));

const overviewFields = computed(() => ([
    { key: 'reference_no', label: copy.referenceNo, value: currentOrder.value.reference_no || '-' },
    { key: 'agreed_total', label: copy.agreedTotal, value: formatMoney(agreedInvoiceAmount.value, currentOrder.value.currency || 'USD') },
    { key: 'supplier', label: copy.supplier, value: currentOrder.value.supplier_name || '-', href: currentOrder.value.supplier_profile_url || '' },
    { key: 'invoiced_total', label: copy.invoicedTotal, value: formatMoney(invoicedTotal.value, currentOrder.value.currency || 'USD') },
    { key: 'ship', label: copy.ship, value: currentOrder.value.ship_name || '-' },
    { key: 'remaining_total', label: copy.remainingTotal, value: formatMoney(remainingInvoiceAmount.value, currentOrder.value.currency || 'USD') },
]));

const currentInvoice = computed(() => invoices.value.find((invoice) => invoice.id === activeInvoiceId.value) ?? null);

const buildInitialForm = (invoice = null) => ({
    payment_proof_date: invoice?.payment_proof_date || '',
    payment_reference: invoice?.payment_reference || '',
    payment_proof_document: null,
    payment_notes: invoice?.payment_notes || '',
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

const textValue = (value) => String(value ?? '').trim();

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

const validateFileField = (value) => {
    if (!value && !currentInvoice.value?.payment_proof_document?.url) {
        return `${copy.paymentFile} is required.`;
    }

    if (!value) {
        return '';
    }

    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    if (value.type && !allowedTypes.includes(value.type)) {
        return 'Upload a PDF, JPG, PNG, or WEBP file.';
    }

    if ((value.size ?? 0) > 15 * 1024 * 1024) {
        return 'Payment proof file must be 15 MB or smaller.';
    }

    return '';
};

const validationRules = computed(() => ({
    payment_proof_date: () => validateDateField(form.payment_proof_date, { label: copy.paymentDate }),
    payment_reference: () => validateTextField(form.payment_reference, { label: copy.paymentReference, max: 120 }),
    payment_proof_document: () => validateFileField(form.payment_proof_document),
    payment_notes: () => validateTextField(form.payment_notes, { label: copy.paymentNotes, max: 2000 }),
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
    form.payment_proof_document = event.target.files?.[0] ?? null;
    clearFieldErrorIfValid('payment_proof_document');
};

const openForm = (invoice) => {
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

const submitPaymentProof = async () => {
    const submitUrl = currentInvoice.value?.update_payment_proof_url || '';

    if (!submitUrl) {
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
                    <p>Please wait while the invoice and payment proof workflow is prepared.</p>
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
                        <div class="section-head">
                            <p class="directory-eyebrow section-card-eyebrow">{{ copy.invoiceList }}</p>
                            <p class="section-copy">{{ copy.invoiceListIntro }}</p>
                        </div>

                        <OrderInvoicesSection
                            :title="copy.invoiceList"
                            :intro="''"
                            :invoices="invoices"
                            :empty-title="copy.emptyInvoicesTitle"
                            :empty-text="copy.emptyInvoicesText"
                            :show-heading="false"
                            :buyer-label="copy.buyerCompany"
                            :buyer-name="currentOrder.company_name || ''"
                            :supplier-label="copy.supplier"
                            :supplier-name="currentOrder.supplier_name || ''"
                            :supplier-href="currentOrder.supplier_profile_url || ''"
                        >
                            <template #actions="{ invoice }">
                                <button
                                    v-if="invoice.can_upload_payment_proof"
                                    type="button"
                                    class="primary-action"
                                    @click="openForm(invoice)"
                                >
                                    {{ invoice.payment_proof_document?.url ? copy.updateProof : copy.uploadProof }}
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
                                                    <span>{{ copy.paymentDate }} *</span>
                                                    <span class="field-hint">{{ copy.hints.payment_proof_date }}</span>
                                                    <div class="input-shell" :class="{ invalid: hasVisualInvalid('payment_proof_date') }">
                                                        <input :ref="(el) => registerFieldRef('payment_proof_date', el)" v-model="form.payment_proof_date" type="date" @input="clearFieldErrorIfValid('payment_proof_date')" @change="clearFieldErrorIfValid('payment_proof_date')">
                                                    </div>
                                                    <span class="field-feedback">{{ visibleFieldError('payment_proof_date') }}</span>
                                                </label>

                                                <label class="field">
                                                    <span>{{ copy.paymentReference }}</span>
                                                    <span class="field-hint">{{ copy.hints.payment_reference }}</span>
                                                    <div class="input-shell" :class="{ invalid: hasVisualInvalid('payment_reference') }">
                                                        <input :ref="(el) => registerFieldRef('payment_reference', el)" v-model="form.payment_reference" type="text" :placeholder="copy.placeholders.payment_reference" @input="clearFieldErrorIfValid('payment_reference')">
                                                    </div>
                                                    <span class="field-feedback">{{ visibleFieldError('payment_reference') }}</span>
                                                </label>

                                                <label class="field">
                                                    <span>{{ copy.paymentFile }} *</span>
                                                    <span class="field-hint">{{ copy.hints.payment_proof_document }}</span>
                                                    <div class="file-shell" :class="{ invalid: hasVisualInvalid('payment_proof_document') }">
                                                        <input :ref="(el) => registerFieldRef('payment_proof_document', el)" class="file-input" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" @change="handleFileChange">
                                                        <span class="file-button">{{ copy.browse }}</span>
                                                        <span class="file-name">{{ form.payment_proof_document?.name || copy.noFileYet }}</span>
                                                    </div>
                                                    <span class="field-hint field-hint-compact">{{ copy.fileTypes }}</span>
                                                    <span v-if="currentInvoice?.payment_proof_document?.name" class="field-hint field-hint-compact">
                                                        {{ copy.currentFile }}: {{ currentInvoice.payment_proof_document.name }}
                                                    </span>
                                                    <span v-if="currentInvoice?.payment_proof_document?.name" class="field-hint field-hint-compact">
                                                        {{ copy.replaceFile }}
                                                    </span>
                                                    <span class="field-feedback">{{ visibleFieldError('payment_proof_document') }}</span>
                                                </label>

                                                <label class="field form-field-wide">
                                                    <span>{{ copy.paymentNotes }}</span>
                                                    <span class="field-hint">{{ copy.hints.payment_notes }}</span>
                                                    <div class="input-shell input-shell-textarea" :class="{ invalid: hasVisualInvalid('payment_notes') }">
                                                        <textarea :ref="(el) => registerFieldRef('payment_notes', el)" v-model="form.payment_notes" rows="4" :placeholder="copy.placeholders.payment_notes" @input="clearFieldErrorIfValid('payment_notes')"></textarea>
                                                    </div>
                                                    <span class="field-feedback">{{ visibleFieldError('payment_notes') }}</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-actions">
                                        <button type="button" class="secondary-action" :disabled="form.processing" @click="closeForm">
                                            {{ copy.cancel }}
                                        </button>
                                        <button type="button" class="primary-action" :disabled="form.processing" @click="submitPaymentProof">
                                            {{ currentInvoice?.payment_proof_document?.url ? copy.update : copy.save }}
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </OrderInvoicesSection>
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
.primary-action:disabled,.secondary-action:disabled{opacity:.6;cursor:not-allowed}
@media (max-width: 960px){
    .form-grid{grid-template-columns:1fr}
    .form-field-wide{grid-column:span 1}
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
