<script setup>
import { computed, ref, useSlots } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    title: {
        type: String,
        default: 'Invoices & Payments',
    },
    intro: {
        type: String,
        default: '',
    },
    invoices: {
        type: Array,
        default: () => [],
    },
    emptyTitle: {
        type: String,
        default: 'No invoice has been added yet.',
    },
    emptyText: {
        type: String,
        default: 'Invoice and payment steps will appear here once the workflow starts.',
    },
    showHeading: {
        type: Boolean,
        default: true,
    },
    buyerLabel: {
        type: String,
        default: 'Buyer Company',
    },
    buyerName: {
        type: String,
        default: '',
    },
    buyerHref: {
        type: String,
        default: '',
    },
    supplierLabel: {
        type: String,
        default: 'Supplier Company',
    },
    supplierName: {
        type: String,
        default: '',
    },
    supplierHref: {
        type: String,
        default: '',
    },
});

const slots = useSlots();

const hasActionsSlot = computed(() => Boolean(slots.actions));
const hasDetailsSlot = computed(() => Boolean(slots.details));
const hasBuyerCompany = computed(() => `${props.buyerName ?? ''}`.trim() !== '');
const hasSupplierCompany = computed(() => `${props.supplierName ?? ''}`.trim() !== '');

const copy = {
    company: 'Company',
    invoiceNumber: 'Invoice Number',
    invoiceDate: 'Invoice Date',
    invoiceAmount: 'Invoice Amount',
    paymentDate: 'Payment Date',
    paymentConfirmed: 'Payment Confirmed',
    paymentReference: 'Payment Reference',
    invoiceNotes: 'Invoice Notes',
    paymentNotes: 'Payment Notes',
    supplierInvoiceDocument: 'Supplier Invoice',
    buyerPaymentProofDocument: 'Buyer Payment Proof',
    confirmedOn: 'Confirmed on',
    awaitingSupplierConfirmation: 'Awaiting supplier confirmation',
    viewFile: 'View File',
    files: 'Files',
    close: 'Close',
    previous: 'Previous',
    next: 'Next',
    openFile: 'Open file',
    previewUnavailable: 'Preview unavailable for this file type.',
    noData: '-',
};

const textOrDash = (value) => {
    const text = `${value ?? ''}`.trim();
    return text || copy.noData;
};

const formatDate = (value) => {
    if (!value) return copy.noData;

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
};

const formatMoney = (value, currency = 'USD') => {
    if (`${value ?? ''}`.trim() === '') {
        return copy.noData;
    }

    const numeric = Number(value ?? 0);

    if (!Number.isFinite(numeric)) {
        return `${currency} ${value ?? '0'}`;
    }

    return `${currency} ${new Intl.NumberFormat('en-GB', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(numeric)}`;
};

const invoiceHeading = (invoice, index) => {
    if (index >= 0) {
        return `Invoice ${index + 1}`;
    }

    const number = `${invoice?.invoice_number ?? ''}`.trim();

    if (number) {
        return number;
    }

    return `Invoice ${index + 1}`;
};

const documentName = (document) => {
    const name = `${document?.name ?? ''}`.trim();
    return name || copy.viewFile;
};

const hasBuyerPaymentSubmission = (invoice) => (
    `${invoice?.payment_proof_date ?? ''}`.trim() !== ''
    || `${invoice?.payment_reference ?? ''}`.trim() !== ''
    || `${invoice?.payment_notes ?? ''}`.trim() !== ''
    || Boolean(invoice?.payment_proof_document?.url)
);

const statusMeta = (invoice) => {
    if (`${invoice?.payment_confirmed_at ?? ''}`.trim() !== '') {
        return `${copy.confirmedOn} ${formatDate(invoice.payment_confirmed_at)}`;
    }

    if (hasBuyerPaymentSubmission(invoice)) {
        return copy.awaitingSupplierConfirmation;
    }

    return '';
};

const buyerFields = (invoice) => {
    const fields = [];

    if (hasBuyerCompany.value) {
        fields.push({
            key: 'buyer_company',
            label: props.buyerLabel || 'Buyer Company',
            value: props.buyerName,
            href: props.buyerHref || '',
        });
    }

    fields.push(
        {
            key: 'payment_date',
            label: copy.paymentDate,
            value: formatDate(invoice.payment_proof_date),
        },
        {
            key: 'payment_reference',
            label: copy.paymentReference,
            value: textOrDash(invoice.payment_reference),
            long: true,
        },
        {
            key: 'buyer_payment_proof',
            label: copy.buyerPaymentProofDocument,
            document: invoice.payment_proof_document,
            value: copy.noData,
            long: true,
        },
        {
            key: 'payment_notes',
            label: copy.paymentNotes,
            value: textOrDash(invoice.payment_notes),
            long: true,
        },
    );

    return fields;
};

const supplierFields = (invoice) => {
    const fields = [];

    if (hasSupplierCompany.value) {
        fields.push({
            key: 'supplier_company',
            label: props.supplierLabel || 'Supplier Company',
            value: props.supplierName,
            href: props.supplierHref || '',
        });
    }

    fields.push({
        key: 'invoice_number',
        label: copy.invoiceNumber,
        value: textOrDash(invoice.invoice_number),
    },
    {
        key: 'invoice_date',
        label: copy.invoiceDate,
        value: formatDate(invoice.invoice_date),
    },
    {
        key: 'invoice_amount',
        label: copy.invoiceAmount,
        value: formatMoney(invoice.invoice_amount, invoice.currency),
    },
    {
        key: 'supplier_invoice',
        label: copy.supplierInvoiceDocument,
        document: invoice.invoice_document,
        value: copy.noData,
        long: true,
    },
    {
        key: 'invoice_notes',
        label: copy.invoiceNotes,
        value: textOrDash(invoice.invoice_notes),
        long: true,
    });

    return fields;
};

const normalizeAttachmentUrl = (url) => {
    if (!url || typeof window === 'undefined') {
        return url;
    }

    try {
        const parsed = new URL(url, window.location.origin);

        if (parsed.hostname === 'localhost') {
            parsed.protocol = window.location.protocol;
            parsed.hostname = window.location.hostname;
            parsed.port = window.location.port;
        }

        return parsed.toString();
    } catch {
        return url;
    }
};

const attachmentPreviewUrl = (attachment) => normalizeAttachmentUrl(attachment?.url);

const isImageAttachment = (attachment) => {
    const source = `${attachment?.name ?? ''} ${attachmentPreviewUrl(attachment) ?? ''}`.toLowerCase();

    return /\.(png|jpe?g|gif|webp|bmp|svg)(\?|$)/.test(source);
};

const isPdfAttachment = (attachment) => {
    const mimeType = `${attachment?.type ?? attachment?.mime_type ?? ''}`.toLowerCase();

    if (mimeType === 'application/pdf') {
        return true;
    }

    const source = `${attachment?.name ?? ''} ${attachmentPreviewUrl(attachment) ?? ''}`.toLowerCase();

    return /\.pdf(\?|$)/.test(source);
};

const attachmentViewer = ref(null);
const attachmentIndex = ref(0);

const openAttachmentViewer = (attachments, startIndex = 0) => {
    if (!Array.isArray(attachments) || attachments.length === 0) {
        return;
    }

    attachmentViewer.value = attachments;
    attachmentIndex.value = Math.min(Math.max(startIndex, 0), attachments.length - 1);
};

const closeAttachmentViewer = () => {
    attachmentViewer.value = null;
    attachmentIndex.value = 0;
};

const hasAttachmentGallery = computed(() => (attachmentViewer.value?.length ?? 0) > 1);
const currentAttachment = computed(() => attachmentViewer.value?.[attachmentIndex.value] ?? null);

const goToPreviousAttachment = () => {
    if (!attachmentViewer.value?.length) {
        return;
    }

    attachmentIndex.value = (attachmentIndex.value - 1 + attachmentViewer.value.length) % attachmentViewer.value.length;
};

const goToNextAttachment = () => {
    if (!attachmentViewer.value?.length) {
        return;
    }

    attachmentIndex.value = (attachmentIndex.value + 1) % attachmentViewer.value.length;
};
</script>

<template>
    <section class="invoice-workflow-section">
        <div v-if="showHeading" class="section-heading">
            <h2 class="directory-section-title">{{ title }}</h2>
            <p v-if="intro" class="section-copy">{{ intro }}</p>
        </div>

        <div v-if="invoices.length" class="invoice-stack">
            <article
                v-for="(invoice, index) in invoices"
                :key="invoice.id"
                class="subsection-surface subsection-surface-small invoice-surface"
            >
                <div class="section-heading section-heading-inline">
                    <div class="invoice-heading-stack">
                        <h2 class="directory-section-title">{{ invoiceHeading(invoice, index) }}</h2>
                        <p v-if="statusMeta(invoice)" class="invoice-status-meta">{{ statusMeta(invoice) }}</p>
                    </div>
                    <span class="status-badge">{{ invoice.status_label }}</span>
                </div>

                <div class="invoice-party-grid">
                    <section class="invoice-party-panel">
                        <div class="invoice-column">
                            <div
                                v-for="field in buyerFields(invoice)"
                                :key="field.key"
                                class="invoice-line"
                                :class="{ 'invoice-line-long': field.long }"
                            >
                                <strong class="invoice-line-label">{{ field.label }}:</strong>
                                <div class="invoice-line-value" :class="{ 'invoice-line-value-long': field.long }">
                                    <button
                                        v-if="field.document?.url"
                                        type="button"
                                        class="detail-value-link"
                                        @click="openAttachmentViewer([field.document])"
                                    >
                                        {{ documentName(field.document) }}
                                    </button>
                                    <Link
                                        v-else-if="field.href"
                                        :href="field.href"
                                        class="detail-value-link"
                                    >
                                        {{ field.value }}
                                    </Link>
                                    <template v-else>
                                        {{ field.value }}
                                    </template>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="invoice-party-panel">
                        <div class="invoice-column">
                            <div
                                v-for="field in supplierFields(invoice)"
                                :key="field.key"
                                class="invoice-line"
                                :class="{ 'invoice-line-long': field.long }"
                            >
                                <strong class="invoice-line-label">{{ field.label }}:</strong>
                                <div class="invoice-line-value" :class="{ 'invoice-line-value-long': field.long }">
                                    <button
                                        v-if="field.document?.url"
                                        type="button"
                                        class="detail-value-link"
                                        @click="openAttachmentViewer([field.document])"
                                    >
                                        {{ documentName(field.document) }}
                                    </button>
                                    <Link
                                        v-else-if="field.href"
                                        :href="field.href"
                                        class="detail-value-link"
                                    >
                                        {{ field.value }}
                                    </Link>
                                    <template v-else>
                                        {{ field.value }}
                                    </template>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div v-if="hasActionsSlot" class="invoice-actions">
                    <slot name="actions" :invoice="invoice" />
                </div>

                <div v-if="hasDetailsSlot" class="invoice-details">
                    <slot name="details" :invoice="invoice" />
                </div>
            </article>
        </div>

        <div v-else class="empty-surface">
            <strong>{{ emptyTitle }}</strong>
            <p>{{ emptyText }}</p>
        </div>

        <div v-if="attachmentViewer" class="gallery-modal-backdrop" @click.self="closeAttachmentViewer">
            <div class="gallery-modal">
                <div class="gallery-modal-head">
                    <div class="gallery-modal-title-group">
                        <h3 class="gallery-modal-title">{{ copy.files }}</h3>
                        <p class="gallery-modal-counter">{{ attachmentIndex + 1 }} / {{ attachmentViewer.length }}</p>
                    </div>
                    <button type="button" class="gallery-modal-close" @click="closeAttachmentViewer">
                        {{ copy.close }}
                    </button>
                </div>

                <div class="gallery-modal-body">
                    <button
                        v-if="hasAttachmentGallery"
                        type="button"
                        class="gallery-nav-button is-left"
                        :aria-label="copy.previous"
                        @click="goToPreviousAttachment"
                    >
                        <span aria-hidden="true">‹</span>
                    </button>

                    <div class="gallery-stage">
                        <img
                            v-if="isImageAttachment(currentAttachment)"
                            :src="attachmentPreviewUrl(currentAttachment)"
                            :alt="currentAttachment?.name ?? copy.files"
                            class="gallery-image"
                        >
                        <div v-else-if="isPdfAttachment(currentAttachment)" class="gallery-pdf-shell">
                            <iframe
                                :src="attachmentPreviewUrl(currentAttachment)"
                                class="gallery-pdf-frame"
                                title="PDF preview"
                            ></iframe>
                        </div>
                        <div v-else class="gallery-file-fallback">
                            <p class="gallery-file-name">{{ currentAttachment?.name ?? copy.files }}</p>
                            <p class="gallery-file-copy">{{ copy.previewUnavailable }}</p>
                            <a
                                v-if="currentAttachment?.url"
                                :href="attachmentPreviewUrl(currentAttachment)"
                                class="gallery-open-link"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ copy.openFile }}
                            </a>
                        </div>
                    </div>

                    <button
                        v-if="hasAttachmentGallery"
                        type="button"
                        class="gallery-nav-button is-right"
                        :aria-label="copy.next"
                        @click="goToNextAttachment"
                    >
                        <span aria-hidden="true">›</span>
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.invoice-workflow-section{display:grid;gap:16px}
.invoice-stack{display:grid;gap:16px}
.subsection-surface{padding:24px;border-radius:10px;background:#f8fafb;min-width:0}
.section-heading{display:grid;gap:6px;margin-bottom:18px}
.section-heading-inline{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
.section-heading :deep(.directory-section-title){margin:0;font-size:1.04rem;font-weight:700;line-height:1.25;color:#0f172a}
.invoice-heading-stack{display:grid;gap:4px}
.invoice-status-meta{margin:0;color:#64748b;font-size:.84rem;line-height:1.5}
.section-copy,.empty-surface p{margin:0;color:#64748b;font-size:.92rem;line-height:1.7}
.invoice-party-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px;margin-top:0}
.invoice-party-panel,.invoice-column{display:grid;gap:14px;min-width:0}
.invoice-line{display:grid;grid-template-columns:150px minmax(0,1fr);align-items:start;column-gap:10px}
.invoice-line-label{color:#04151f;font-size:12px;font-weight:700;line-height:1.2;white-space:normal}
.invoice-line-value{color:rgba(4,21,31,.82);font-size:13px;font-weight:400;display:block;min-width:0;line-height:1.45;white-space:normal;overflow:visible;text-overflow:clip;word-break:break-word}
.invoice-line-value-long{line-height:1.45}
.detail-value-link{appearance:none;border:0;background:transparent;color:#2563eb;text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:3px;padding:0;font:inherit;cursor:pointer}
.status-badge{display:inline-flex;align-items:center;justify-content:center;min-height:32px;padding:0 12px;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:.78rem;font-weight:700;white-space:nowrap}
.invoice-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px;padding-top:18px;border-top:1px solid rgba(148,163,184,.16)}
.invoice-details{display:grid;gap:0}
.empty-surface{display:grid;gap:8px;padding:22px 24px;border-radius:10px;background:#f8fafb;border:1px solid rgba(226,232,240,.95)}
.empty-surface strong{color:#0f172a;font-size:.96rem;font-weight:700}
.gallery-modal-backdrop{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(15,23,42,.42);backdrop-filter:blur(6px);z-index:2200}
.gallery-modal{width:min(1040px,100%);max-height:min(86vh,860px);display:grid;grid-template-rows:auto minmax(0,1fr);border-radius:18px;border:1px solid rgba(4,21,31,.08);background:#fff;box-shadow:0 24px 44px rgba(15,23,42,.18);overflow:hidden}
.gallery-modal-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:22px 24px 16px;border-bottom:1px solid rgba(4,21,31,.08)}
.gallery-modal-title-group{display:grid;gap:4px}
.gallery-modal-title{margin:0;font-size:1.04rem;font-weight:700;color:#0f172a}
.gallery-modal-counter{margin:0;color:#64748b;font-size:.88rem}
.gallery-modal-close{border:0;background:transparent;color:#2563eb;font-size:.88rem;font-weight:700;cursor:pointer}
.gallery-modal-body{position:relative;display:flex;align-items:center;justify-content:center;padding:22px 24px 28px;overflow:auto;background:#f8fafb}
.gallery-stage{display:flex;align-items:center;justify-content:center;width:100%;height:100%;min-height:420px}
.gallery-image{max-width:100%;max-height:70vh;object-fit:contain;border-radius:12px;box-shadow:0 18px 38px rgba(15,23,42,.12)}
.gallery-pdf-shell{width:100%;height:72vh;min-height:520px}
.gallery-pdf-frame{width:100%;height:100%;border:0;border-radius:12px;background:#fff}
.gallery-file-fallback{display:grid;gap:12px;justify-items:center;text-align:center;padding:28px}
.gallery-file-name{margin:0;color:#0f172a;font-size:1rem;font-weight:700}
.gallery-file-copy{margin:0;color:#64748b;font-size:.92rem;line-height:1.6}
.gallery-open-link{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-size:.9rem;font-weight:700}
.gallery-nav-button{position:absolute;top:50%;transform:translateY(-50%);display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border:1px solid rgba(4,21,31,.08);border-radius:999px;background:rgba(255,255,255,.96);color:#04151f;font-size:26px;line-height:1;cursor:pointer;box-shadow:0 10px 24px rgba(15,23,42,.12)}
.gallery-nav-button.is-left{left:20px}
.gallery-nav-button.is-right{right:20px}
@media (max-width: 860px){
    .invoice-party-grid{grid-template-columns:1fr}
}
@media (max-width: 720px){
    .section-heading-inline{flex-direction:column;align-items:flex-start}
    .invoice-line{grid-template-columns:1fr;row-gap:6px}
}
</style>
