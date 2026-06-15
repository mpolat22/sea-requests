<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
});

const order = computed(() => props.order);

const copy = {
    noData: '-',
    noSelectedItems: 'No selected line items.',
    customerRequest: 'Customer',
    acceptedOffer: 'Accepted',
    product: 'Product',
    partNo: 'Part No',
    manufacturer: 'Manufacturer',
    qtyUnit: 'Qty / Unit',
    quality: 'Quality',
    deliveryTime: 'Delivery Time',
    unitPrice: 'Unit Price',
    total: 'Total',
    files: 'Files',
    requestComments: 'Request Comments',
    supplierRemarks: 'Supplier Remarks',
    selectionNote: 'Buyer Selection Note',
    offeredQty: 'Offered',
    requestedServiceScope: 'Requested Service Scope',
    acceptedServiceScope: 'Accepted Supplier Scope',
    selectedService: 'Selected Service',
    requestDescription: 'Request Description',
    requestFiles: 'Request Files',
    offerFiles: 'Offer Files',
    serviceClarification: 'Service Clarification',
    buyerNote: 'Buyer Note',
    close: 'Close',
    previous: 'Previous',
    next: 'Next',
    openFile: 'Open file',
    previewUnavailable: 'Preview unavailable for this file type.',
    fileAddedSingular: 'file added',
    fileAddedPlural: 'files added',
};

const isSpareParts = computed(() => order.value.request_type === 'spare_parts');
const selectedItems = computed(() => order.value.selected_items ?? []);

const textOrDash = (value) => {
    const text = `${value ?? ''}`.trim();
    return text || copy.noData;
};

const formatMoney = (value, currency = 'USD') => {
    if (value === null || value === undefined || `${value}`.trim() === '') {
        return copy.noData;
    }

    const numeric = Number(value);

    if (!Number.isFinite(numeric)) {
        return `${currency} ${value}`;
    }

    return `${currency} ${new Intl.NumberFormat('en-GB', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(numeric)}`;
};

const formatTitleCaseValue = (value) => {
    const normalized = `${value ?? ''}`.trim();

    if (!normalized) {
        return copy.noData;
    }

    return normalized
        .split('_')
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
};

const itemRowKey = (item, index) => item.offer_item_id ?? item.rfq_item_id ?? index;

const expandedItems = ref({});

const isItemExpanded = (item, index) => Boolean(expandedItems.value[itemRowKey(item, index)]);

const toggleItemDetails = (item, index) => {
    const key = itemRowKey(item, index);

    expandedItems.value = {
        ...expandedItems.value,
        [key]: !expandedItems.value[key],
    };
};

const lineLabel = (item, index) => textOrDash(item.line_no ?? index + 1);

const requestedQuantityLabel = (item) => {
    const quantity = textOrDash(item.requested_quantity);
    const unit = `${item.unit ?? ''}`.trim();

    return unit ? `${quantity} ${unit}` : quantity;
};

const selectedQuantityLabel = (item) => {
    const quantity = textOrDash(item.selected_qty);
    const unit = `${item.unit ?? ''}`.trim();

    return unit ? `${quantity} ${unit}` : quantity;
};

const offeredQuantityLabel = (item) => {
    const quantity = textOrDash(item.offered_qty);
    const unit = `${item.unit ?? ''}`.trim();

    return unit ? `${quantity} ${unit}` : quantity;
};

const fileButtonLabel = (attachments = []) => {
    const count = Array.isArray(attachments) ? attachments.length : 0;

    if (count === 1) {
        return `1 ${copy.fileAddedSingular}`;
    }

    return `${count} ${copy.fileAddedPlural}`;
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
    <div class="subsection-surface">
        <div class="section-heading">
            <h2 class="directory-section-title">{{ title }}</h2>
        </div>

        <template v-if="isSpareParts">
            <div v-if="selectedItems.length" class="detail-table-wrap">
                <table class="detail-table order-items-table">
                    <colgroup>
                        <col class="col-line">
                        <col class="col-product">
                        <col class="col-part">
                        <col class="col-manufacturer">
                        <col class="col-qty">
                        <col class="col-quality">
                        <col class="col-delivery">
                        <col class="col-unit-price">
                        <col class="col-total">
                        <col class="col-files">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ copy.product }}</th>
                            <th>{{ copy.partNo }}</th>
                            <th>{{ copy.manufacturer }}</th>
                            <th>{{ copy.qtyUnit }}</th>
                            <th>{{ copy.quality }}</th>
                            <th>{{ copy.deliveryTime }}</th>
                            <th>{{ copy.unitPrice }}</th>
                            <th>{{ copy.total }}</th>
                            <th>{{ copy.files }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(item, index) in selectedItems" :key="itemRowKey(item, index)">
                            <tr class="order-item-request-row">
                                <td rowspan="2" class="order-item-shared-cell">
                                    <div class="line-cell">
                                        <button
                                            type="button"
                                            class="expand-row-button"
                                            :aria-expanded="isItemExpanded(item, index)"
                                            @click="toggleItemDetails(item, index)"
                                        >
                                            <span class="expand-row-icon" :class="{ 'is-open': isItemExpanded(item, index) }">></span>
                                        </button>
                                        <span>{{ lineLabel(item, index) }}</span>
                                    </div>
                                </td>
                                <td rowspan="2" class="order-item-shared-cell product-cell">{{ textOrDash(item.product_name) }}</td>
                                <td rowspan="2" class="order-item-shared-cell">{{ textOrDash(item.part_no) }}</td>
                                <td>
                                    <span class="row-prefix">{{ copy.customerRequest }}:</span>
                                    {{ textOrDash(item.requested_manufacturer) }}
                                </td>
                                <td>{{ requestedQuantityLabel(item) }}</td>
                                <td>{{ formatTitleCaseValue(item.requested_quality) }}</td>
                                <td>{{ copy.noData }}</td>
                                <td>{{ copy.noData }}</td>
                                <td>{{ copy.noData }}</td>
                                <td>
                                    <button
                                        v-if="item.request_attachments?.length"
                                        type="button"
                                        class="file-preview-button"
                                        @click="openAttachmentViewer(item.request_attachments)"
                                    >
                                        {{ fileButtonLabel(item.request_attachments) }}
                                    </button>
                                    <span v-else>{{ copy.noData }}</span>
                                </td>
                            </tr>
                            <tr class="order-item-accepted-row">
                                <td>
                                    <span class="row-prefix row-prefix-strong">{{ copy.acceptedOffer }}:</span>
                                    {{ textOrDash(item.offered_manufacturer) }}
                                </td>
                                <td>
                                    <div class="qty-stack">
                                        <span>{{ selectedQuantityLabel(item) }}</span>
                                        <span class="qty-helper">{{ copy.offeredQty }}: {{ offeredQuantityLabel(item) }}</span>
                                    </div>
                                </td>
                                <td>{{ formatTitleCaseValue(item.offered_quality) }}</td>
                                <td>{{ textOrDash(item.delivery_time) }}</td>
                                <td>{{ formatMoney(item.unit_price, order.currency) }}</td>
                                <td>{{ formatMoney(item.line_total, order.currency) }}</td>
                                <td>
                                    <button
                                        v-if="item.offer_attachments?.length"
                                        type="button"
                                        class="file-preview-button"
                                        @click="openAttachmentViewer(item.offer_attachments)"
                                    >
                                        {{ fileButtonLabel(item.offer_attachments) }}
                                    </button>
                                    <span v-else>{{ copy.noData }}</span>
                                </td>
                            </tr>
                            <tr v-if="isItemExpanded(item, index)" class="item-detail-row">
                                <td colspan="10">
                                    <div class="item-detail-grid">
                                        <div class="item-detail-block">
                                            <div class="detail-inline-main detail-inline-main-wide">
                                                <strong class="detail-inline-label">{{ copy.requestComments }}:</strong>
                                                <div class="detail-inline-text detail-inline-text-long">{{ textOrDash(item.requested_comments) }}</div>
                                            </div>
                                        </div>
                                        <div class="item-detail-block">
                                            <div class="detail-inline-main detail-inline-main-wide">
                                                <strong class="detail-inline-label">{{ copy.supplierRemarks }}:</strong>
                                                <div class="detail-inline-text detail-inline-text-long">{{ textOrDash(item.offer_remarks) }}</div>
                                            </div>
                                        </div>
                                        <div class="item-detail-block item-detail-block-span">
                                            <div class="detail-inline-main detail-inline-main-wide">
                                                <strong class="detail-inline-label">{{ copy.selectionNote }}:</strong>
                                                <div class="detail-inline-text detail-inline-text-long">{{ textOrDash(item.buyer_note) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p v-else class="empty-state-copy">{{ copy.noSelectedItems }}</p>

        </template>

        <template v-else>
            <div class="service-scope-grid">
                <div class="service-scope-card">
                    <h3 class="scope-card-title">{{ copy.requestedServiceScope }}</h3>
                    <div class="service-scope-stack">
                        <div class="detail-inline-main detail-inline-main-wide">
                            <strong class="detail-inline-label">{{ copy.selectedService }}:</strong>
                            <div class="detail-inline-text">{{ textOrDash(order.service_title) }}</div>
                        </div>
                        <div class="detail-inline-main detail-inline-main-wide">
                            <strong class="detail-inline-label">{{ copy.requestDescription }}:</strong>
                            <div class="detail-inline-text detail-inline-text-long">{{ textOrDash(order.service_description) }}</div>
                        </div>
                        <div class="detail-inline-main detail-inline-main-wide">
                            <strong class="detail-inline-label">{{ copy.requestFiles }}:</strong>
                            <div class="detail-inline-text">
                                <button
                                    v-if="order.request_attachments?.length"
                                    type="button"
                                    class="file-preview-button"
                                    @click="openAttachmentViewer(order.request_attachments)"
                                >
                                    {{ fileButtonLabel(order.request_attachments) }}
                                </button>
                                <span v-else>{{ copy.noData }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="service-scope-card">
                    <h3 class="scope-card-title">{{ copy.acceptedServiceScope }}</h3>
                    <div class="service-scope-stack">
                        <div class="detail-inline-main detail-inline-main-wide">
                            <strong class="detail-inline-label">{{ copy.serviceClarification }}:</strong>
                            <div class="detail-inline-text detail-inline-text-long">{{ textOrDash(order.service_clarification) }}</div>
                        </div>
                        <div class="detail-inline-main detail-inline-main-wide">
                            <strong class="detail-inline-label">{{ copy.buyerNote }}:</strong>
                            <div class="detail-inline-text detail-inline-text-long">{{ textOrDash(order.buyer_note) }}</div>
                        </div>
                        <div class="detail-inline-main detail-inline-main-wide">
                            <strong class="detail-inline-label">{{ copy.offerFiles }}:</strong>
                            <div class="detail-inline-text">
                                <button
                                    v-if="order.offer_attachments?.length"
                                    type="button"
                                    class="file-preview-button"
                                    @click="openAttachmentViewer(order.offer_attachments)"
                                >
                                    {{ fileButtonLabel(order.offer_attachments) }}
                                </button>
                                <span v-else>{{ copy.noData }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
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
</template>

<style scoped>
.subsection-surface{padding:24px;border-radius:10px;background:#f8fafb;min-width:0}
.section-heading{margin-bottom:16px}
.directory-section-title{margin:0;font-size:1.04rem;font-weight:700;line-height:1.25;color:#0f172a}
.detail-table-wrap{overflow-x:auto;padding-bottom:14px}
.detail-table{width:100%;min-width:1100px;border-collapse:collapse;table-layout:fixed}
.detail-table .col-line{width:48px}
.detail-table .col-product{width:200px}
.detail-table .col-part{width:120px}
.detail-table .col-manufacturer{width:140px}
.detail-table .col-qty{width:138px}
.detail-table .col-quality{width:110px}
.detail-table .col-delivery{width:110px}
.detail-table .col-unit-price{width:110px}
.detail-table .col-total{width:110px}
.detail-table .col-files{width:116px}
.detail-table thead th{padding:10px 8px;background:#f4f7fb;color:#04151f;font-size:12px;font-weight:700;line-height:1.2;text-align:left;white-space:nowrap}
.detail-table thead th:first-child{text-align:center}
.detail-table tbody td{padding:10px 8px;border-top:1px solid rgba(4,21,31,.06);color:rgba(4,21,31,.82);font-size:13px;font-weight:400;line-height:1.45;vertical-align:top}
.order-item-shared-cell{background:#f8fafb;vertical-align:middle}
.order-item-request-row td{background:#f8fafc}
.order-item-accepted-row td{background:#eaf3ff}
.line-cell{display:flex;align-items:center;justify-content:center;gap:6px;white-space:nowrap;width:100%}
.expand-row-button{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border:0;border-radius:999px;background:rgba(15,23,42,.06);color:#334155;cursor:pointer;padding:0}
.expand-row-icon{display:inline-block;font-size:12px;line-height:1;transform:rotate(0deg);transition:transform 160ms ease}
.expand-row-icon.is-open{transform:rotate(90deg)}
.product-cell{color:#0f172a;font-weight:600}
.row-prefix{color:#64748b;font-size:12px;font-weight:600;margin-right:4px}
.row-prefix-strong{color:#15803d}
.qty-stack{display:grid;gap:4px}
.qty-helper{color:#475569;font-size:12px;line-height:1.35}
.item-detail-row td{padding-top:0;background:#fff}
.item-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 18px;padding:0 0 16px 54px;align-items:start}
.item-detail-block{min-width:0}
.item-detail-block-span{grid-column:1 / -1}
.detail-inline-main{display:grid;grid-template-columns:146px minmax(0,1fr);align-items:start;column-gap:10px}
.detail-inline-main-wide{grid-template-columns:146px minmax(0,1fr)}
.detail-inline-label{color:#04151f;font-size:12px;font-weight:700;line-height:1.2;white-space:normal}
.detail-inline-text{color:rgba(4,21,31,.82);font-size:13px;font-weight:400;display:block;min-width:0;line-height:1.45;white-space:normal;overflow:visible;text-overflow:clip;word-break:break-word}
.detail-inline-text-long{line-height:1.5}
.file-preview-button{border:0;background:transparent;color:inherit;cursor:pointer;padding:0;font-size:13px;font-weight:400;line-height:1.3;text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:3px}
.service-scope-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.service-scope-card{display:grid;gap:14px;padding:18px;border-radius:12px;background:rgba(255,255,255,.72)}
.scope-card-title{margin:0;color:#0f172a;font-size:1rem;font-weight:700;line-height:1.3}
.service-scope-stack{display:grid;gap:12px}
.empty-state-copy{margin:0;color:#64748b;font-size:.95rem;line-height:1.7}
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
@media (max-width: 980px){
    .service-scope-grid{grid-template-columns:1fr}
}
@media (max-width: 720px){
    .subsection-surface{padding:20px}
    .item-detail-grid{grid-template-columns:1fr;padding-left:0}
    .detail-inline-main,.detail-inline-main-wide{grid-template-columns:1fr;row-gap:6px}
}
</style>
