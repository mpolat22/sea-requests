<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminDashboardShell from './Shell.vue';
import OfferCommercialSummary from '../../../Components/OfferCommercialSummary.vue';
import RfqGeneralInformationSection from '../../../Components/RfqGeneralInformationSection.vue';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
    rfq: {
        type: Object,
        required: true,
    },
    backUrl: {
        type: String,
        required: true,
    },
});

const rfq = props.rfq;

const copy = {
    title: 'RFQ Detail',
    eyebrow: 'Admin RFQ Detail',
    heroSparePartsTitle: 'Spare Parts RFQ',
    heroServiceTitle: 'Service Request RFQ',
    intro: 'Review the RFQ, intervene in compare or edit when allowed, and remove the full workflow if the case needs an admin reset.',
    back: 'Back to RFQs',
    compare: 'Compare Offers',
    edit: 'Edit RFQ',
    delete: 'Delete RFQ',
    compareLocked: 'No submitted offers are available to compare yet.',
    compareCompleted: 'This RFQ is completed. Continue from Orders or RFQ detail only.',
    editLocked: 'This RFQ cannot be edited now.',
    editLockedAwardStarted: 'Offer evaluation has already started. Once award selection begins, this RFQ can no longer be edited.',
    editLockedCancelled: 'Cancelled RFQs cannot be edited.',
    editLockedOverdue: 'This RFQ can no longer be edited because the due date has passed.',
    editLockedConfirmed: 'Confirmed orders already exist for this RFQ. Continue from Orders or delete the RFQ for a full reset.',
    editLockedCompleted: 'Completed orders already exist for this RFQ. Continue from Orders or delete the RFQ for a full reset.',
    deleteTitle: 'Delete RFQ',
    deleteBody: 'This will permanently remove the RFQ, its supplier targeting, offers, awards, invoices, messages, and related files from the system.',
    deleteCancel: 'Cancel',
    deleteConfirm: 'Delete RFQ',
    general: 'General Information',
    items: 'Items to Quote',
    requestFiles: 'Request Files',
    service: 'Service Request',
    supplierScope: 'Supplier Scope',
    recipients: 'Selected Supplier Recipients',
    recipientIntro: 'These suppliers were targeted when the RFQ was sent.',
    supplierOffers: 'Supplier Offers',
    supplierOffersIntro: 'Review every submitted supplier quotation here supplier by supplier.',
    noOffersYet: 'No submitted supplier offers yet.',
    noRecipients: 'No suppliers were targeted for this RFQ.',
    files: 'Files',
    noFiles: 'No files',
    noNotes: 'No notes added',
    noDescription: 'No description added',
    countriesSelected: 'countries selected',
    portsSelected: 'ports selected',
    categoriesSelected: 'categories selected',
    subcategoriesSelected: 'subcategories selected',
    brandsSelected: 'brands selected',
    allCategories: 'All Categories',
    allSubcategories: 'All Subcategories',
    allBrands: 'All Brands',
    titleLabel: 'Title',
    descriptionLabel: 'Description',
    statusLabel: 'RFQ Status',
    requestTypeLabel: 'Request Type',
    visibilityLabel: 'Visibility',
    privateRequest: 'Private Request',
    publishedRequest: 'Published Request',
    selectedCountries: 'Selected Countries',
    selectedPorts: 'Selected Ports',
    selectedCategories: 'Selected Categories',
    selectedSubcategories: 'Selected Subcategories',
    selectedBrands: 'Selected Brands',
    allListedPortsIn: 'All listed ports in',
    portsSelectedSuffix: 'ports selected',
    close: 'Close',
    previous: 'Previous',
    next: 'Next',
    openFile: 'Open file',
    previewUnavailable: 'Preview unavailable for this file type.',
    fileAddedSingular: 'file added',
    fileAddedPlural: 'files added',
    view: 'View',
    submittedOn: 'Submitted On',
    offerFiles: 'Offer Files',
    customerRequest: 'Customer',
    offerResponse: 'Offer',
    commentsAndRemarks: 'Comments / Remarks',
    unitPrice: 'Unit Price',
    total: 'Total',
    partialAwardAccepted: 'Partial award accepted',
    fullQuotedScopeRequired: 'Full quoted scope required',
    table: {
        line: '#',
        product: 'Product',
        partNo: 'Part No',
        manufacturer: 'Manufacturer',
        modelType: 'Model/Type',
        catalogCode: 'Catalog Code',
        serialNumber: 'Serial No',
        drawingNumber: 'Drawing No',
        qtyUnit: 'Qty / Unit',
        rob: 'ROB',
        quality: 'Quality',
        comments: 'Comments',
        files: 'Files',
    },
    offerTable: {
        line: '#',
        product: 'Product',
        partNo: 'Part No',
        manufacturer: 'Manufacturer',
        qtyUnit: 'Qty / Unit',
        quality: 'Quality',
        deliveryTime: 'Delivery Time',
        remarks: 'Comments / Remarks',
        unitPrice: 'Unit Price',
        total: 'Total',
        files: 'Files',
    },
    recipientTable: {
        company: 'Company',
        category: 'Category',
        subcategory: 'Subcategory',
        country: 'Country',
        port: 'Port',
    },
    labels: {
        referenceNo: 'Reference No',
        company: 'Buyer Company',
        ship: 'Ship',
        status: 'RFQ Status',
        requestType: 'Request Type',
        visibility: 'Visibility',
        country: 'Country',
        ports: 'Ports',
        requisitionDate: 'Requisition Date',
        dueDate: 'Due Date',
        currency: 'Currency',
        priority: 'Priority',
        generalNotes: 'General Notes',
    },
    statuses: {
        draft: 'Draft',
        open: 'Open',
        closed: 'Closed',
        award_confirmed: 'Award Confirmed',
        completed: 'Completed',
        cancelled: 'Cancelled',
    },
    priority: {
        low: 'Low',
        normal: 'Normal',
        high: 'High',
        critical: 'Critical',
    },
    requestType: {
        spare_parts: 'Spare Parts',
        service_request: 'Service Request',
    },
};

const isSpareParts = computed(() => rfq.request_type === 'spare_parts');
const deleteModalOpen = ref(false);
const heroTitle = computed(() => {
    const serviceTitle = `${rfq.service_title ?? ''}`.trim();

    if (!isSpareParts.value && serviceTitle) {
        return serviceTitle;
    }

    return isSpareParts.value ? copy.heroSparePartsTitle : copy.heroServiceTitle;
});

const formatDate = (value) => {
    if (!value) return '-';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
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

const textOrDash = (value) => {
    const text = `${value ?? ''}`.trim();
    return text !== '' ? text : '-';
};

const offerAwardScopeLabel = (value) => {
    if (value === 'full_scope_required') {
        return copy.fullQuotedScopeRequired;
    }

    return copy.partialAwardAccepted;
};

const canCompareOffers = computed(() => Boolean(rfq.compare_url));

const compareTitle = computed(() => {
    if (canCompareOffers.value) {
        return copy.compare;
    }

    return rfq.status === 'completed'
        ? copy.compareCompleted
        : copy.compareLocked;
});

const editTitle = computed(() => {
    if (rfq.can_edit) {
        return copy.edit;
    }

    if (rfq.edit_reason === 'completed_orders') {
        return copy.editLockedCompleted;
    }

    if (rfq.edit_reason === 'confirmed_orders') {
        return copy.editLockedConfirmed;
    }

    if (rfq.edit_reason === 'award_started') {
        return copy.editLockedAwardStarted;
    }

    if (rfq.edit_reason === 'cancelled') {
        return copy.editLockedCancelled;
    }

    if (rfq.edit_reason === 'overdue') {
        return copy.editLockedOverdue;
    }

    return copy.editLocked;
});

const statusTone = (status) => {
    if (status === 'open') return 'is-open';
    if (status === 'award_confirmed') return 'is-awarded';
    if (status === 'completed') return 'is-completed';
    if (status === 'closed' || status === 'cancelled') return 'is-closed';
    return 'is-draft';
};

const generalInformationFields = computed(() => [
    { key: 'reference_no', label: copy.labels.referenceNo, value: rfq.reference_no || '-' },
    { key: 'company', label: copy.labels.company, value: rfq.company_name || '-' },
    { key: 'ship', label: copy.labels.ship, value: rfq.ship_name || '-' },
    { key: 'imo_number', label: 'IMO Number', value: rfq.imo_number || '-' },
    { key: 'status', label: copy.labels.status, value: copy.statuses[rfq.status] || rfq.status || '-' },
    { key: 'request_type', label: copy.labels.requestType, value: copy.requestType[rfq.request_type] || rfq.request_type || '-' },
    { key: 'visibility', label: copy.labels.visibility, value: rfq.is_private_request ? copy.privateRequest : copy.publishedRequest },
    { key: 'country', label: copy.labels.country, value: `${selectedCountryCount.value} ${copy.countriesSelected}`, clickable: true, action: 'countries' },
    { key: 'ports', label: copy.labels.ports, value: `${selectedPortCount.value} ${copy.portsSelected}`, clickable: true, action: 'ports' },
    { key: 'requisition_date', label: copy.labels.requisitionDate, value: formatDate(rfq.requisition_date) },
    { key: 'due_date', label: copy.labels.dueDate, value: formatDate(rfq.due_date) },
    { key: 'currency', label: copy.labels.currency, value: rfq.currency || '-' },
    { key: 'priority', label: copy.labels.priority, value: copy.priority[rfq.priority] || rfq.priority || '-' },
    { key: 'general_notes', label: copy.labels.generalNotes, value: rfq.general_notes || copy.noNotes, wide: true, long: true },
]);

const supplierScopeFields = computed(() => [
    { key: 'countries_scope', label: 'Countries', value: `${selectedCountryCount.value} ${copy.countriesSelected}`, clickable: true, action: 'countries' },
    { key: 'ports_scope', label: 'Ports', value: `${selectedPortCount.value} ${copy.portsSelected}`, clickable: true, action: 'ports' },
    { key: 'categories_scope', label: 'Categories', value: selectedCategoriesSummary.value, clickable: selectedCategoryNames.value.length > 0, action: 'categories' },
    { key: 'subcategories_scope', label: 'Subcategory', value: selectedSubcategoriesSummary.value, clickable: selectedSubcategoryNames.value.length > 0, action: 'subcategories' },
    { key: 'brands_scope', label: 'Brands', value: selectedBrandsSummary.value, clickable: selectedBrandNames.value.length > 0, action: 'brands' },
]);

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

const fileButtonLabel = (attachments = []) => {
    const count = Array.isArray(attachments) ? attachments.length : 0;

    if (count === 1) {
        return `1 ${copy.fileAddedSingular}`;
    }

    return `${count} ${copy.fileAddedPlural}`;
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

const handleAttachmentViewerKeydown = (event) => {
    if (!attachmentViewer.value) {
        return;
    }

    if (event.key === 'Escape') {
        closeAttachmentViewer();
        return;
    }

    if (!hasAttachmentGallery.value) {
        return;
    }

    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        goToPreviousAttachment();
    } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        goToNextAttachment();
    }
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('keydown', handleAttachmentViewerKeydown);
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handleAttachmentViewerKeydown);
    }
});

const detailModal = ref(null);

const portGroups = computed(() => (rfq.ports_by_country ?? [])
    .map((group) => ({
        country: group.country,
        ports: (group.ports ?? []).filter((port) => (port?.name ?? '').trim() !== ''),
    }))
    .filter((group) => (group.country ?? '').trim() !== '' || group.ports.length > 0));

const selectedCountries = computed(() => {
    if (portGroups.value.length) {
        return portGroups.value
            .map((group) => group.country)
            .filter(Boolean);
    }

    return (rfq.country_names ?? []).filter(Boolean);
});

const selectedCategoryNames = computed(() => Array.from(new Set((rfq.selected_categories ?? []).filter(Boolean))));
const selectedSubcategoryNames = computed(() => Array.from(new Set((rfq.selected_subcategories ?? []).filter(Boolean))));
const selectedBrandNames = computed(() => Array.from(new Set((rfq.selected_brands ?? []).filter(Boolean))));

const selectedCategoriesSummary = computed(() => (
    selectedCategoryNames.value.length > 0
        ? `${selectedCategoryNames.value.length} ${copy.categoriesSelected}`
        : copy.allCategories
));

const selectedSubcategoriesSummary = computed(() => (
    selectedSubcategoryNames.value.length > 0
        ? `${selectedSubcategoryNames.value.length} ${copy.subcategoriesSelected}`
        : copy.allSubcategories
));

const selectedBrandsSummary = computed(() => (
    selectedBrandNames.value.length > 0
        ? `${selectedBrandNames.value.length} ${copy.brandsSelected}`
        : copy.allBrands
));

const selectedCountryCount = computed(() => selectedCountries.value.length);
const selectedPortCount = computed(() => portGroups.value.reduce((total, entry) => total + (entry.ports?.length ?? 0), 0));
const portSelectionThreshold = 10;

const openDetailModal = (type) => {
    detailModal.value = type;
};

const closeDetailModal = () => {
    detailModal.value = null;
};

const openDeleteModal = () => {
    if (!rfq.delete_url) {
        return;
    }

    deleteModalOpen.value = true;
};

const closeDeleteModal = () => {
    deleteModalOpen.value = false;
};

const confirmDeleteRfq = () => {
    if (!rfq.delete_url) {
        return;
    }

    router.delete(rfq.delete_url, {
        preserveScroll: true,
        onFinish: closeDeleteModal,
    });
};

const portGroupSummary = (group) => {
    const selectedCount = group?.ports?.length ?? 0;
    const totalCount = Number(rfq.port_totals_by_country?.[group.country] ?? 0);

    if (totalCount > 0 && selectedCount === totalCount) {
        return `${copy.allListedPortsIn} ${group.country}`;
    }

    if (selectedCount > portSelectionThreshold) {
        return `${selectedCount} ${copy.portsSelectedSuffix}`;
    }

    return null;
};

const detailModalTitle = computed(() => {
    if (detailModal.value === 'countries') return copy.selectedCountries;
    if (detailModal.value === 'ports') return copy.selectedPorts;
    if (detailModal.value === 'categories') return copy.selectedCategories;
    if (detailModal.value === 'subcategories') return copy.selectedSubcategories;
    return copy.selectedBrands;
});

const offerRequestItemMap = computed(() => new Map((rfq.items ?? []).map((item) => [Number(item.id), item])));

const detailListItems = computed(() => {
    if (detailModal.value === 'countries') return selectedCountries.value;
    if (detailModal.value === 'categories') return selectedCategoryNames.value;
    if (detailModal.value === 'subcategories') return selectedSubcategoryNames.value;
    if (detailModal.value === 'brands') return selectedBrandNames.value;
    return [];
});
</script>

<template>
    <AdminDashboardShell :dashboard="dashboard" :title="copy.title" active-tab="rfqs" :show-tabs="false" :show-intro="false">
        <section class="surface-panel hero-panel">
            <div class="hero-copy">
                <p class="directory-eyebrow">{{ copy.eyebrow }}</p>
                <h1 class="directory-page-title">{{ heroTitle }}</h1>
                <p class="directory-intro-copy">{{ copy.intro }}</p>

                <div class="hero-pills">
                    <span class="pill request-type-pill">
                        {{ copy.requestType[rfq.request_type] || rfq.request_type }}
                    </span>
                    <span class="pill" :class="rfq.is_private_request ? 'private-pill' : 'public-pill'">
                        {{ rfq.is_private_request ? copy.privateRequest : copy.publishedRequest }}
                    </span>
                    <span class="status-pill" :class="statusTone(rfq.status)">
                        {{ copy.statuses[rfq.status] || rfq.status }}
                    </span>
                </div>
            </div>

            <div class="hero-actions">
                <Link :href="backUrl" class="back-button">
                    {{ copy.back }}
                </Link>
                <Link
                    v-if="canCompareOffers"
                    :href="rfq.compare_url"
                    class="back-button back-button-secondary"
                    :title="compareTitle"
                >
                    {{ copy.compare }}
                </Link>
                <button
                    v-else
                    type="button"
                    class="back-button back-button-secondary action-disabled"
                    :title="compareTitle"
                    disabled
                >
                    {{ copy.compare }}
                </button>
                <Link
                    v-if="rfq.can_edit"
                    :href="rfq.edit_url"
                    class="back-button back-button-secondary"
                    :title="editTitle"
                >
                    {{ copy.edit }}
                </Link>
                <button
                    v-else
                    type="button"
                    class="back-button back-button-secondary action-disabled"
                    :title="editTitle"
                    disabled
                >
                    {{ copy.edit }}
                </button>
                <button
                    v-if="rfq.can_delete"
                    type="button"
                    class="back-button delete-button"
                    @click="openDeleteModal"
                >
                    {{ copy.delete }}
                </button>
            </div>
        </section>

        <section class="surface-card section-card">
            <RfqGeneralInformationSection
                :title="copy.general"
                :fields="generalInformationFields"
                @action="openDetailModal"
            />
        </section>

        <section class="surface-card section-card">
            <div class="section-heading">
                <h2 class="directory-section-title">{{ isSpareParts ? copy.items : copy.service }}</h2>
            </div>

            <template v-if="isSpareParts">
                <div class="detail-table-wrap">
                    <table class="detail-table">
                        <thead>
                            <tr>
                                <th>{{ copy.table.line }}</th>
                                <th>{{ copy.table.product }}</th>
                                <th>{{ copy.table.partNo }}</th>
                                <th>{{ copy.table.manufacturer }}</th>
                                <th>{{ copy.table.modelType }}</th>
                                <th>{{ copy.table.catalogCode }}</th>
                                <th>{{ copy.table.serialNumber }}</th>
                                <th>{{ copy.table.drawingNumber }}</th>
                                <th>{{ copy.table.qtyUnit }}</th>
                                <th>{{ copy.table.rob }}</th>
                                <th>{{ copy.table.quality }}</th>
                                <th>{{ copy.table.comments }}</th>
                                <th>{{ copy.table.files }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in rfq.items" :key="item.id">
                                <td>{{ item.line_no }}</td>
                                <td>{{ textOrDash(item.product_name) }}</td>
                                <td>{{ textOrDash(item.part_no) }}</td>
                                <td>{{ textOrDash(item.manufacturer) }}</td>
                                <td>{{ textOrDash(item.model_type) }}</td>
                                <td>{{ textOrDash(item.catalog_code) }}</td>
                                <td>{{ textOrDash(item.serial_number) }}</td>
                                <td>{{ textOrDash(item.drawing_number) }}</td>
                                <td>{{ [textOrDash(item.quantity), textOrDash(item.unit)].join(' ') }}</td>
                                <td>{{ textOrDash(item.rob) }}</td>
                                <td>{{ textOrDash(item.quality) }}</td>
                                <td>{{ textOrDash(item.comments) }}</td>
                                <td>
                                    <button
                                        v-if="item.attachments?.length"
                                        type="button"
                                        class="file-preview-button"
                                        @click="openAttachmentViewer(item.attachments)"
                                    >
                                        {{ fileButtonLabel(item.attachments) }}
                                    </button>
                                    <span v-else>{{ copy.noFiles }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <template v-else>
                <div class="service-summary-grid">
                    <div class="service-summary-card">
                        <strong>{{ copy.titleLabel }}</strong>
                        <p>{{ textOrDash(rfq.service_title) }}</p>
                    </div>
                    <div class="service-summary-card service-summary-card-wide">
                        <strong>{{ copy.descriptionLabel }}</strong>
                        <p>{{ rfq.service_description || copy.noDescription }}</p>
                    </div>
                </div>
            </template>

            <div class="request-files-block">
                <div class="section-heading section-heading-tight">
                    <h3 class="subsection-title">{{ copy.requestFiles }}</h3>
                </div>
                <div v-if="rfq.attachments?.length" class="attachment-chip-list">
                    <button
                        v-for="attachment in rfq.attachments"
                        :key="attachment.id"
                        type="button"
                        class="attachment-chip"
                        @click="openAttachmentViewer([attachment])"
                    >
                        {{ attachment.name }}
                    </button>
                </div>
                <p v-else class="notes-text">{{ copy.noFiles }}</p>
            </div>
        </section>

        <section class="surface-card section-card">
            <RfqGeneralInformationSection
                :title="copy.supplierScope"
                :fields="supplierScopeFields"
                @action="openDetailModal"
            />

            <div class="section-divider"></div>

            <div class="section-heading">
                <h2 class="directory-section-title">{{ copy.recipients }}</h2>
                <p class="section-copy">{{ copy.recipientIntro }}</p>
            </div>

            <div v-if="rfq.recipients?.length" class="detail-table-wrap">
                <table class="detail-table recipients-table">
                    <thead>
                        <tr>
                            <th>{{ copy.recipientTable.company }}</th>
                            <th>{{ copy.recipientTable.category }}</th>
                            <th>{{ copy.recipientTable.subcategory }}</th>
                            <th>{{ copy.recipientTable.country }}</th>
                            <th>{{ copy.recipientTable.port }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="recipient in rfq.recipients" :key="recipient.id">
                            <td>{{ textOrDash(recipient.company_name) }}</td>
                            <td>{{ textOrDash(recipient.category_name) }}</td>
                            <td>{{ textOrDash(recipient.subcategory_name) }}</td>
                            <td>{{ textOrDash(recipient.country_name) }}</td>
                            <td>{{ textOrDash(recipient.port_name) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="notes-text">{{ copy.noRecipients }}</p>
        </section>

        <section class="surface-card section-card">
            <div class="section-heading">
                <h2 class="directory-section-title">{{ copy.supplierOffers }}</h2>
                <p class="section-copy">{{ copy.supplierOffersIntro }}</p>
            </div>

            <div v-if="rfq.offers?.length" class="offer-stack">
                <article v-for="offer in rfq.offers" :key="offer.id" class="subsection-surface offer-surface">
                    <div class="offer-header">
                        <div>
                            <h3 class="offer-title">{{ offer.seller?.company_name || offer.seller?.name || copy.noData }}</h3>
                            <p class="offer-meta">{{ copy.submittedOn }}: {{ formatDate(offer.submitted_at) }}</p>
                        </div>
                    </div>

                    <div v-if="isSpareParts" class="detail-table-wrap">
                        <table class="detail-table offer-detail-table">
                            <thead>
                                <tr>
                                    <th>{{ copy.offerTable.line }}</th>
                                    <th>{{ copy.offerTable.product }}</th>
                                    <th>{{ copy.offerTable.partNo }}</th>
                                    <th>{{ copy.offerTable.manufacturer }}</th>
                                    <th>{{ copy.offerTable.qtyUnit }}</th>
                                    <th>{{ copy.offerTable.quality }}</th>
                                    <th>{{ copy.offerTable.deliveryTime }}</th>
                                    <th>{{ copy.offerTable.remarks }}</th>
                                    <th>{{ copy.offerTable.unitPrice }}</th>
                                    <th>{{ copy.offerTable.total }}</th>
                                    <th>{{ copy.offerTable.files }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in offer.items" :key="item.id">
                                    <td>{{ offerRequestItemMap.get(Number(item.rfq_item_id))?.line_no ?? item.line_no ?? '-' }}</td>
                                    <td>{{ textOrDash(offerRequestItemMap.get(Number(item.rfq_item_id))?.product_name) }}</td>
                                    <td>{{ textOrDash(offerRequestItemMap.get(Number(item.rfq_item_id))?.part_no) }}</td>
                                    <td>{{ textOrDash(item.manufacturer) }}</td>
                                    <td>{{ [textOrDash(item.offer_qty), textOrDash(offerRequestItemMap.get(Number(item.rfq_item_id))?.unit)].join(' ') }}</td>
                                    <td>{{ textOrDash(item.quality) }}</td>
                                    <td>{{ textOrDash(item.delivery_time) }}</td>
                                    <td>{{ textOrDash(item.remarks) }}</td>
                                    <td>{{ formatMoney(item.unit_price, offer.currency) }}</td>
                                    <td>{{ formatMoney(item.line_total, offer.currency) }}</td>
                                    <td>
                                        <button
                                            v-if="item.attachments?.length"
                                            type="button"
                                            class="file-preview-button"
                                            @click="openAttachmentViewer(item.attachments)"
                                        >
                                            {{ fileButtonLabel(item.attachments) }}
                                        </button>
                                        <span v-else>{{ copy.noFiles }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="offer.attachments?.length" class="offer-file-stack">
                        <strong class="offer-file-label">{{ copy.offerFiles }}</strong>
                        <div class="attachment-chip-list">
                            <button
                                v-for="attachment in offer.attachments"
                                :key="attachment.id"
                                type="button"
                                class="attachment-chip"
                                @click="openAttachmentViewer([attachment])"
                            >
                                {{ attachment.name }}
                            </button>
                        </div>
                    </div>

                    <OfferCommercialSummary
                        :offer="offer"
                        :request-type="rfq.request_type"
                        :award-scope-label="isSpareParts ? offerAwardScopeLabel(offer.award_scope_policy) : ''"
                    />
                </article>
            </div>
            <p v-else class="notes-text">{{ copy.noOffersYet }}</p>
        </section>

        <div v-if="detailModal" class="detail-modal-backdrop" @click.self="closeDetailModal">
            <div class="detail-modal">
                <div class="detail-modal-head">
                    <h3 class="detail-modal-title">{{ detailModalTitle }}</h3>
                    <button type="button" class="detail-modal-close" @click="closeDetailModal">
                        {{ copy.close }}
                    </button>
                </div>

                <div v-if="detailModal === 'ports'" class="detail-modal-body">
                    <div class="modal-port-stack">
                        <div v-for="group in portGroups" :key="group.country" class="modal-port-group">
                            <strong>{{ group.country }}</strong>
                            <p v-if="portGroupSummary(group)" class="notes-text port-summary-text">
                                {{ portGroupSummary(group) }}
                            </p>
                            <div v-else class="modal-pill-list">
                                <span v-for="port in group.ports" :key="`${group.country}-${port.id ?? port.name}`" class="modal-pill">
                                    {{ port.unlocode ? `${port.name} (${port.unlocode})` : port.name }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="detail-modal-body">
                    <div class="modal-pill-list">
                        <span v-for="item in detailListItems" :key="item" class="modal-pill">
                            {{ item }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="deleteModalOpen" class="detail-modal-backdrop" @click.self="closeDeleteModal">
            <div class="confirm-modal">
                <div class="confirm-modal-head">
                    <h3 class="detail-modal-title">{{ copy.deleteTitle }}</h3>
                </div>
                <div class="confirm-modal-body">
                    <p>{{ copy.deleteBody }}</p>
                </div>
                <div class="confirm-modal-actions">
                    <button type="button" class="detail-modal-close" @click="closeDeleteModal">
                        {{ copy.deleteCancel }}
                    </button>
                    <button type="button" class="confirm-delete-button" @click="confirmDeleteRfq">
                        {{ copy.deleteConfirm }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="currentAttachment" class="detail-modal-backdrop attachment-modal-backdrop" @click.self="closeAttachmentViewer">
            <div class="attachment-modal">
                <div class="attachment-modal-head">
                    <div>
                        <h3 class="detail-modal-title">{{ currentAttachment.name }}</h3>
                    </div>
                    <button type="button" class="detail-modal-close" @click="closeAttachmentViewer">
                        {{ copy.close }}
                    </button>
                </div>

                <div class="attachment-modal-body">
                    <template v-if="isImageAttachment(currentAttachment)">
                        <img :src="attachmentPreviewUrl(currentAttachment)" :alt="currentAttachment.name" class="attachment-preview-image">
                    </template>
                    <template v-else-if="isPdfAttachment(currentAttachment)">
                        <iframe :src="attachmentPreviewUrl(currentAttachment)" class="attachment-preview-frame" title="Attachment preview"></iframe>
                    </template>
                    <div v-else class="attachment-preview-fallback">
                        <p>{{ copy.previewUnavailable }}</p>
                        <a :href="attachmentPreviewUrl(currentAttachment)" target="_blank" rel="noopener noreferrer" class="attachment-open-link">
                            {{ copy.openFile }}
                        </a>
                    </div>
                </div>

                <div v-if="hasAttachmentGallery" class="attachment-modal-footer">
                    <button type="button" class="attachment-nav-button" @click="goToPreviousAttachment">
                        {{ copy.previous }}
                    </button>
                    <button type="button" class="attachment-nav-button" @click="goToNextAttachment">
                        {{ copy.next }}
                    </button>
                </div>
            </div>
        </div>
    </AdminDashboardShell>
</template>

<style scoped>
.surface-panel,.surface-card{padding:32px 36px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff;box-shadow:0 24px 44px rgba(15,23,42,.08)}
.section-card{display:grid;gap:24px}
.hero-panel{padding:24px 26px;display:flex;align-items:flex-start;justify-content:space-between;gap:24px}
.hero-copy{display:grid;gap:14px}
.hero-copy :deep(.directory-page-title){margin:0}
.hero-copy :deep(.directory-intro-copy){max-width:74ch}
.hero-pills{display:flex;flex-wrap:wrap;gap:10px}
.hero-actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end}
.back-button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border-radius:10px;background:#0f172a;color:#fff;text-decoration:none;font-size:.88rem;font-weight:600}
.back-button-secondary{background:#fff;border:1px solid #e2e8f0;color:#0f172a}
.action-disabled{opacity:.6;cursor:not-allowed}
.delete-button{border:1px solid #fecaca;background:#fff1f2;color:#b91c1c;cursor:pointer}
.pill{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 12px;border-radius:10px;font-size:.82rem;font-weight:600}
.request-type-pill{background:#f8fafc;border:1px solid #e2e8f0;color:#0f172a}
.private-pill{background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8}
.public-pill{background:#f8fafc;border:1px solid #e2e8f0;color:#475569}
.status-pill{background:#f8fafc;border:1px solid #e2e8f0;color:#0f172a}
.status-pill.is-open{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}
.status-pill.is-awarded{background:#ecfeff;border-color:#a5f3fc;color:#0f766e}
.status-pill.is-completed{background:#f0fdf4;border-color:#bbf7d0;color:#0b7a52}
.status-pill.is-closed,.status-pill.is-draft{background:#fff7ed;border-color:#fed7aa;color:#c2410c}
.section-heading{display:grid;gap:6px}
.section-heading :deep(.directory-section-title){margin:0;font-size:1.04rem;font-weight:700;line-height:1.25;color:#0f172a}
.section-copy,.notes-text{margin:0;color:#64748b;font-size:.92rem;line-height:1.7}
.section-heading-tight{gap:0}
.subsection-title{margin:0;color:#0f172a;font-size:.98rem;font-weight:700}
.detail-table-wrap{overflow:auto}
.detail-table{width:100%;min-width:1100px;border-collapse:separate;border-spacing:0}
.detail-table thead th{padding:14px 16px;background:#f8fafc;color:#64748b;font-size:.76rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;text-align:left;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0}
.detail-table thead th:first-child{border-left:1px solid #e2e8f0;border-top-left-radius:10px}
.detail-table thead th:last-child{border-right:1px solid #e2e8f0;border-top-right-radius:10px}
.detail-table tbody td{padding:16px;border-bottom:1px solid #e2e8f0;background:#fff;color:#334155;font-size:.92rem;vertical-align:top}
.detail-table tbody tr td:first-child{border-left:1px solid #e2e8f0}
.detail-table tbody tr td:last-child{border-right:1px solid #e2e8f0}
.detail-table tbody tr:last-child td:first-child{border-bottom-left-radius:10px}
.detail-table tbody tr:last-child td:last-child{border-bottom-right-radius:10px}
.service-summary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.service-summary-card{display:grid;gap:8px;padding:20px;border:1px solid rgba(148,163,184,.16);border-radius:10px;background:#f8fafb}
.service-summary-card strong{color:#0f172a;font-size:.88rem}
.service-summary-card p{margin:0;color:#475569;font-size:.92rem;line-height:1.7}
.service-summary-card-wide{grid-column:1 / -1}
.request-files-block{display:grid;gap:12px}
.attachment-chip-list{display:flex;flex-wrap:wrap;gap:10px}
.attachment-chip,.file-preview-button{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 14px;border-radius:999px;border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8;font-size:.84rem;font-weight:600;cursor:pointer}
.section-divider{height:1px;background:rgba(226,232,240,.9)}
.offer-stack{display:grid;gap:18px}
.offer-surface{padding:24px;border-radius:10px;background:#f8fafb;display:grid;gap:18px}
.offer-header{display:flex;align-items:flex-start;justify-content:space-between;gap:14px}
.offer-title{margin:0;color:#0f172a;font-size:1rem;font-weight:700}
.offer-meta{margin:6px 0 0;color:#64748b;font-size:.88rem}
.offer-file-stack{display:grid;gap:10px}
.offer-file-label{color:#0f172a;font-size:.88rem}
.recipients-table{min-width:760px}
.offer-detail-table{min-width:1040px}
.detail-modal-backdrop{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(15,23,42,.55);backdrop-filter:blur(10px);z-index:2200}
.detail-modal,.attachment-modal{width:min(760px,calc(100vw - 32px));max-height:min(76vh,720px);display:grid;grid-template-rows:auto minmax(0,1fr) auto;background:#fff;border:1px solid rgba(148,163,184,.35);border-radius:24px;box-shadow:0 32px 80px rgba(15,23,42,.24);overflow:hidden}
.attachment-modal{width:min(980px,calc(100vw - 32px))}
.detail-modal-head,.attachment-modal-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:24px 28px 18px;border-bottom:1px solid rgba(226,232,240,.9)}
.detail-modal-title{margin:0;color:#04151f;font-size:1.02rem;font-weight:700}
.detail-modal-close{appearance:none;border:1px solid rgba(148,163,184,.32);background:#fff;color:#04151f;min-height:38px;padding:0 14px;border-radius:999px;font-size:.82rem;font-weight:600;cursor:pointer}
.detail-modal-body,.attachment-modal-body{overflow:auto;padding:22px 28px 28px}
.confirm-modal{width:min(520px,calc(100vw - 32px));display:grid;grid-template-rows:auto minmax(0,1fr) auto;background:#fff;border:1px solid rgba(148,163,184,.35);border-radius:24px;box-shadow:0 32px 80px rgba(15,23,42,.24);overflow:hidden}
.confirm-modal-head{padding:24px 28px 0}
.confirm-modal-body{padding:18px 28px 0}
.confirm-modal-body p{margin:0;color:#475569;font-size:.95rem;line-height:1.7}
.confirm-modal-actions{display:flex;justify-content:flex-end;gap:10px;padding:24px 28px 28px}
.confirm-delete-button{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 16px;border:0;border-radius:999px;background:#b91c1c;color:#fff;font-size:.82rem;font-weight:700;cursor:pointer}
.modal-pill-list{display:flex;flex-wrap:wrap;gap:10px}
.modal-pill{display:inline-flex;align-items:center;min-height:38px;padding:0 14px;border-radius:999px;background:#f8fafc;border:1px solid rgba(148,163,184,.24);color:#04151f;font-size:.86rem;font-weight:600}
.modal-port-stack{display:grid;gap:18px}
.modal-port-group{display:grid;gap:10px}
.modal-port-group strong{color:#04151f;font-size:.95rem}
.port-summary-text{margin:0;color:#64748b}
.attachment-preview-image{display:block;max-width:100%;height:auto;border-radius:18px}
.attachment-preview-frame{width:100%;min-height:68vh;border:0;border-radius:18px;background:#fff}
.attachment-preview-fallback{display:grid;gap:12px;justify-items:start}
.attachment-preview-fallback p{margin:0;color:#475569;font-size:.95rem;line-height:1.7}
.attachment-open-link{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 16px;border-radius:999px;background:#0f172a;color:#fff;text-decoration:none;font-size:.88rem;font-weight:600}
.attachment-modal-footer{display:flex;justify-content:flex-end;gap:10px;padding:0 28px 24px}
.attachment-nav-button{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 16px;border-radius:999px;border:1px solid rgba(148,163,184,.32);background:#fff;color:#04151f;font-size:.84rem;font-weight:600;cursor:pointer}
@media (max-width: 900px){
    .hero-panel{flex-direction:column;align-items:stretch}
    .hero-actions{justify-content:flex-start}
    .service-summary-grid{grid-template-columns:1fr}
}
@media (max-width: 720px){
    .surface-panel,.surface-card{padding:20px}
    .hero-panel{padding:20px}
    .offer-surface{padding:20px}
    .hero-actions{width:100%;flex-direction:column;align-items:stretch}
    .back-button{width:100%}
}
</style>
