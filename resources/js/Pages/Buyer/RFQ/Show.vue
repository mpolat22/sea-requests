<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '../../../Layouts/MainLayout.vue';
import OfferCommercialSummary from '../../../Components/OfferCommercialSummary.vue';
import RfqGeneralInformationSection from '../../../Components/RfqGeneralInformationSection.vue';

const props = defineProps({
    rfq: {
        type: Object,
        required: true,
    },
    backUrl: {
        type: String,
        required: true,
    },
});

const copy = {
        eyebrow: 'Buyer RFQ',
        title: 'RFQ Detail',
        heroSparePartsTitle: 'Spare Parts RFQ',
        heroServiceTitle: 'Service Request RFQ',
        text: 'Review the request exactly as it was saved and sent through the buyer workflow.',
        back: 'Back to Dashboard',
        general: 'General Information',
        items: 'Items to Quote',
        service: 'Service Request',
        suppliers: 'Suppliers to Send RFQ',
        files: 'Files',
        file: 'File',
        fileAddedSingular: 'file added',
        fileAddedPlural: 'files added',
        titleLabel: 'Title',
        descriptionLabel: 'Description',
        noFiles: 'No files',
        noNotes: 'No notes added',
        noRecipients: 'No suppliers selected',
        noDescription: 'No description added',
        view: 'View',
        countriesSelected: 'countries selected',
        portsSelected: 'ports selected',
        brandsSelected: 'brands selected',
        countriesScope: 'Countries',
        portsScope: 'Ports',
        categoriesScope: 'Categories',
        subcategoriesScope: 'Subcategory',
        brandsScope: 'Brands',
        categoriesSelected: 'categories selected',
        subcategoriesSelected: 'subcategories selected',
        selectedPorts: 'Selected Ports',
        selectedCountries: 'Selected Countries',
        selectedCategories: 'Selected Categories',
        selectedSubcategories: 'Selected Subcategories',
        selectedBrands: 'Selected Brands',
        allCategories: 'All Categories',
        allSubcategories: 'All Subcategories',
        allBrands: 'All Brands',
        allListedPortsIn: 'All listed ports in',
        portsSelectedSuffix: 'ports selected',
        close: 'Close',
        previous: 'Previous',
        next: 'Next',
        openFile: 'Open file',
        previewUnavailable: 'Preview unavailable for this file type.',
        labels: {
            referenceNo: 'Reference No',
            company: 'Company',
            ship: 'Ship',
            country: 'Country',
            ports: 'Ports',
            requisitionDate: 'Requisition Date',
            dueDate: 'Due Date',
            currency: 'Currency',
            priority: 'Priority',
            status: 'RFQ Status',
            generalNotes: 'General Notes',
        },
        table: {
            line: '#',
            product: 'Product',
            partNo: 'Part No',
            manufacturer: 'Manufacturer',
            modelType: 'Model/Type',
            catalogCode: 'Catalog Code',
            serialNumber: 'Serial No',
            drawingNumber: 'Drawing No',
            qty: 'Qty',
            unit: 'Unit',
            rob: 'ROB',
            quality: 'Quality',
            comments: 'Comments',
            files: 'Files',
        },
        requestType: {
            spare_parts: 'Spare Parts',
            service_request: 'Service Request',
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
        offersReceived: 'Offers Received',
        compareOffers: 'Compare Offers',
        supplierOffers: 'Supplier Offers',
        supplierOffersIntro: 'Review every submitted supplier quotation here supplier by supplier.',
        noOffersYet: 'No submitted supplier offers yet.',
        offerFiles: 'Offer Files',
        submittedOn: 'Submitted On',
        customerRequest: 'Customer',
        supplierOffer: 'Offer',
        commentsAndRemarks: 'Comments / Remarks',
        unitPrice: 'Unit Price',
        total: 'Total',
        partialAwardAccepted: 'Partial award accepted',
        fullQuotedScopeRequired: 'Full quoted scope required',
        deliveryTime: 'Delivery Time',
};

const isSpareParts = computed(() => props.rfq.request_type === 'spare_parts');
const currentCopy = computed(() => copy);
const hasSubmittedOffers = computed(() => Number(props.rfq.offers_count ?? 0) > 0);
const showCompareButton = computed(() => hasSubmittedOffers.value && Boolean(props.rfq.compare_url));
const heroTitle = computed(() => {
    const serviceTitle = `${props.rfq.service_title ?? ''}`.trim();

    if (!isSpareParts.value && serviceTitle) {
        return serviceTitle;
    }

    return isSpareParts.value
        ? currentCopy.value.heroSparePartsTitle
        : currentCopy.value.heroServiceTitle;
});

const generalInformationFields = computed(() => [
    {
        key: 'reference_no',
        label: currentCopy.value.labels.referenceNo,
        value: props.rfq.reference_no || '-',
    },
    {
        key: 'company',
        label: currentCopy.value.labels.company,
        value: props.rfq.company_name || '-',
    },
    {
        key: 'ship',
        label: currentCopy.value.labels.ship,
        value: props.rfq.ship_name || '-',
    },
    {
        key: 'status',
        label: currentCopy.value.labels.status,
        value: currentCopy.value.statuses[props.rfq.status] || props.rfq.status || '-',
    },
    {
        key: 'country',
        label: currentCopy.value.labels.country,
        value: `${selectedCountryCount.value} ${currentCopy.value.countriesSelected}`,
        clickable: true,
        action: 'countries',
    },
    {
        key: 'ports',
        label: currentCopy.value.labels.ports,
        value: `${selectedPortCount.value} ${currentCopy.value.portsSelected}`,
        clickable: true,
        action: 'ports',
    },
    {
        key: 'requisition_date',
        label: currentCopy.value.labels.requisitionDate,
        value: formatDate(props.rfq.requisition_date),
    },
    {
        key: 'due_date',
        label: currentCopy.value.labels.dueDate,
        value: formatDate(props.rfq.due_date),
    },
    {
        key: 'currency',
        label: currentCopy.value.labels.currency,
        value: props.rfq.currency || '-',
    },
    {
        key: 'priority',
        label: currentCopy.value.labels.priority,
        value: currentCopy.value.priority[props.rfq.priority] || props.rfq.priority || '-',
    },
    {
        key: 'general_notes',
        label: currentCopy.value.labels.generalNotes,
        value: props.rfq.general_notes || currentCopy.value.noNotes,
        wide: true,
        long: true,
    },
]);

const showSupplierScopeSection = computed(() => !props.rfq.selected_order_offer_id);

const supplierScopeFields = computed(() => [
    {
        key: 'countries_scope',
        label: currentCopy.value.countriesScope,
        value: `${selectedCountryCount.value} ${currentCopy.value.countriesSelected}`,
        clickable: true,
        action: 'countries',
    },
    {
        key: 'ports_scope',
        label: currentCopy.value.portsScope,
        value: `${selectedPortCount.value} ${currentCopy.value.portsSelected}`,
        clickable: true,
        action: 'ports',
    },
    {
        key: 'categories_scope',
        label: currentCopy.value.categoriesScope,
        value: selectedCategoriesSummary.value,
        clickable: selectedCategoryNames.value.length > 0,
        action: 'categories',
    },
    {
        key: 'subcategories_scope',
        label: currentCopy.value.subcategoriesScope,
        value: selectedSubcategoriesSummary.value,
        clickable: selectedSubcategoryNames.value.length > 0,
        action: 'subcategories',
    },
    {
        key: 'brands_scope',
        label: currentCopy.value.brandsScope,
        value: selectedBrandsSummary.value,
        clickable: selectedBrandNames.value.length > 0,
        action: 'brands',
    },
]);

const formatDate = (value) => {
    if (!value) return '-';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
};

const formatTitleCaseValue = (value) => {
    const normalized = `${value ?? ''}`.trim();
    if (!normalized) return '-';
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
};

const offerAwardScopeLabel = (value) => {
    if (value === 'full_scope_required') {
        return currentCopy.value.fullQuotedScopeRequired;
    }

    return currentCopy.value.partialAwardAccepted;
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

const statusTone = (status) => {
    if (status === 'open') return 'is-open';
    if (status === 'award_confirmed') return 'is-awarded';
    if (status === 'completed') return 'is-completed';
    if (status === 'closed' || status === 'cancelled') return 'is-closed';
    return 'is-draft';
};

const priorityTone = (priority) => {
    if (priority === 'critical') return 'is-critical';
    if (priority === 'high') return 'is-high';
    if (priority === 'low') return 'is-low';
    return 'is-normal';
};

const offers = computed(() => props.rfq.offers ?? []);
const hasBuyerOffers = computed(() => offers.value.length > 0);

const expandedItems = ref({});

const rfqItemsById = computed(() => new Map(
    (props.rfq.items ?? []).map((item) => [Number(item.id), item])
));

const offerItemRequestMeta = (offerItem) => rfqItemsById.value.get(Number(offerItem?.rfq_item_id ?? 0)) ?? null;

const toggleItemDetails = (itemId) => {
    expandedItems.value = {
        ...expandedItems.value,
        [itemId]: !expandedItems.value[itemId],
    };
};

const isItemExpanded = (itemId) => !!expandedItems.value[itemId];

const textOrDash = (value) => {
    const text = `${value ?? ''}`.trim();
    return text !== '' ? text : '-';
};

const decimalString = (value) => {
    const numeric = Number.parseFloat(`${value ?? ''}`);

    if (!Number.isFinite(numeric)) {
        return '';
    }

    return `${Math.round(numeric * 100) / 100}`.replace(/\.0+$/, '').replace(/(\.\d*[1-9])0+$/, '$1');
};

const detailModal = ref(null);
const attachmentViewer = ref(null);
const attachmentIndex = ref(0);

const portGroups = computed(() => (props.rfq.ports_by_country ?? [])
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

    return (props.rfq.country_names ?? []).filter(Boolean);
});

const selectedCategoryNames = computed(() => Array.from(new Set((props.rfq.selected_categories ?? [])
    .filter(Boolean))));

const selectedSubcategoryNames = computed(() => Array.from(new Set((props.rfq.selected_subcategories ?? [])
    .filter(Boolean))));

const selectedBrandNames = computed(() => Array.from(new Set((props.rfq.selected_brands ?? [])
    .filter(Boolean))));

const selectedCategoriesSummary = computed(() => (
    selectedCategoryNames.value.length > 0
        ? `${selectedCategoryNames.value.length} ${currentCopy.value.categoriesSelected}`
        : currentCopy.value.allCategories
));

const selectedSubcategoriesSummary = computed(() => (
    selectedSubcategoryNames.value.length > 0
        ? `${selectedSubcategoryNames.value.length} ${currentCopy.value.subcategoriesSelected}`
        : currentCopy.value.allSubcategories
));

const selectedBrandsSummary = computed(() => (
    selectedBrandNames.value.length > 0
        ? `${selectedBrandNames.value.length} ${currentCopy.value.brandsSelected}`
        : currentCopy.value.allBrands
));

const selectedCountryCount = computed(() => selectedCountries.value.length);
const selectedPortCount = computed(() => portGroups.value
    .reduce((total, entry) => total + (entry.ports?.length ?? 0), 0));
const portSelectionThreshold = 10;

const openDetailModal = (type) => {
    detailModal.value = type;
};

const closeDetailModal = () => {
    detailModal.value = null;
};

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

const currentAttachment = computed(() => {
    if (!attachmentViewer.value?.length) {
        return null;
    }

    return attachmentViewer.value[attachmentIndex.value] ?? null;
});

const currentAttachmentUrl = computed(() => attachmentPreviewUrl(currentAttachment.value));
const currentAttachmentViewerUrl = computed(() => {
    const url = currentAttachmentUrl.value;

    if (!url || !isPdfAttachment(currentAttachment.value)) {
        return url;
    }

    const viewerParams = 'toolbar=0&navpanes=0&scrollbar=0&view=FitH';
    return url.includes('#') ? `${url}&${viewerParams}` : `${url}#${viewerParams}`;
});

const hasAttachmentGallery = computed(() => (attachmentViewer.value?.length ?? 0) > 1);

const goToPreviousAttachment = () => {
    if (!attachmentViewer.value?.length) {
        return;
    }

    attachmentIndex.value = attachmentIndex.value === 0
        ? attachmentViewer.value.length - 1
        : attachmentIndex.value - 1;
};

const goToNextAttachment = () => {
    if (!attachmentViewer.value?.length) {
        return;
    }

    attachmentIndex.value = attachmentIndex.value === attachmentViewer.value.length - 1
        ? 0
        : attachmentIndex.value + 1;
};

const fileButtonLabel = (attachments) => {
    const count = attachments?.length ?? 0;
    return `${count} ${count === 1 ? currentCopy.value.fileAddedSingular : currentCopy.value.fileAddedPlural}`;
};

const handleGlobalKeydown = (event) => {
    if (!attachmentViewer.value) {
        return;
    }

    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        goToPreviousAttachment();
    } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        goToNextAttachment();
    } else if (event.key === 'Escape') {
        event.preventDefault();
        closeAttachmentViewer();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});

const portGroupSummary = (group) => {
    const selectedCount = group?.ports?.length ?? 0;
    const totalCount = Number(props.rfq.port_totals_by_country?.[group.country] ?? 0);

    if (totalCount > 0 && selectedCount === totalCount) {
        return `${currentCopy.value.allListedPortsIn} ${group.country}`;
    }

    if (selectedCount > portSelectionThreshold) {
        return `${selectedCount} ${currentCopy.value.portsSelectedSuffix}`;
    }

    return null;
};
</script>

<template>
    <Head :title="`${heroTitle} | ${rfq.reference_no}`" />

    <MainLayout>
        <section class="detail-shell">
            <header class="surface-panel hero-panel">
                <div class="hero-copy">
                    <p class="eyebrow">{{ currentCopy.eyebrow }}</p>
                    <h1 class="directory-page-title">{{ heroTitle }}</h1>
                    <p class="directory-intro-copy">{{ currentCopy.text }}</p>

                    <div class="hero-pills">
                        <span class="pill request-type-pill">
                            {{ currentCopy.requestType[rfq.request_type] || rfq.request_type }}
                        </span>
                        <span class="pill priority-pill" :class="priorityTone(rfq.priority)">
                            {{ currentCopy.priority[rfq.priority] || rfq.priority || '-' }}
                        </span>
                        <span class="status-pill">
                            <span class="status-dot" :class="statusTone(rfq.status)"></span>
                            {{ currentCopy.statuses[rfq.status] || rfq.status }}
                        </span>
                    </div>
                </div>

                <div class="hero-actions">
                    <Link
                        v-if="showCompareButton"
                        :href="rfq.compare_url"
                        class="back-button compare-button"
                    >
                        {{ currentCopy.compareOffers }}
                    </Link>
                    <Link :href="backUrl" class="back-button">
                        {{ currentCopy.back }}
                    </Link>
                </div>
            </header>

            <section class="surface-card section-card combined-detail-section">
                <RfqGeneralInformationSection
                    :title="currentCopy.general"
                    :fields="generalInformationFields"
                    @action="openDetailModal"
                />

                <div class="section-divider"></div>

                <div v-if="isSpareParts" class="subsection-surface">
                    <div class="section-heading">
                        <h2 class="directory-section-title">{{ currentCopy.items }}</h2>
                    </div>

                    <div class="detail-table-wrap">
                        <table class="detail-table">
                            <colgroup>
                                <col class="col-line">
                                <col class="col-product">
                                <col class="col-part">
                                <col class="col-manufacturer">
                                <col class="col-model">
                                <col class="col-catalog">
                                <col class="col-serial">
                                <col class="col-drawing">
                                <col class="col-qty">
                                <col class="col-rob">
                                <col class="col-quality">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>{{ currentCopy.table.line }}</th>
                                    <th>{{ currentCopy.table.product }}</th>
                                    <th>{{ currentCopy.table.partNo }}</th>
                                    <th>{{ currentCopy.table.manufacturer }}</th>
                                    <th>{{ currentCopy.table.modelType }}</th>
                                    <th>{{ currentCopy.table.catalogCode }}</th>
                                    <th>{{ currentCopy.table.serialNumber }}</th>
                                    <th>{{ currentCopy.table.drawingNumber }}</th>
                                    <th>{{ currentCopy.table.qty }} / {{ currentCopy.table.unit }}</th>
                                    <th>{{ currentCopy.table.rob }}</th>
                                    <th>{{ currentCopy.table.quality }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="item in rfq.items" :key="item.id">
                                    <tr>
                                        <td class="line-cell">
                                            <button
                                                type="button"
                                                class="expand-row-button"
                                                :aria-expanded="isItemExpanded(item.id)"
                                                @click="toggleItemDetails(item.id)"
                                            >
                                                <span class="expand-row-icon" :class="{ 'is-open': isItemExpanded(item.id) }">></span>
                                            </button>
                                            <span>{{ item.line_no }}</span>
                                        </td>
                                        <td class="product-cell">{{ item.product_name || '-' }}</td>
                                        <td>{{ item.part_no || '-' }}</td>
                                        <td>{{ item.manufacturer || '-' }}</td>
                                        <td>{{ item.model_type || '-' }}</td>
                                        <td>{{ item.catalog_code || '-' }}</td>
                                        <td>{{ item.serial_number || '-' }}</td>
                                        <td>{{ item.drawing_number || '-' }}</td>
                                        <td>{{ item.quantity || '-' }} {{ item.unit || '' }}</td>
                                        <td>{{ item.rob || '-' }}</td>
                                        <td>{{ formatTitleCaseValue(item.quality) }}</td>
                                    </tr>
                                    <tr v-if="isItemExpanded(item.id)" class="item-detail-row">
                                        <td colspan="11">
                                            <div class="item-detail-grid">
                                                <div class="item-detail-block">
                                                    <div class="detail-inline-main detail-inline-main-wide">
                                                        <strong class="detail-inline-label">{{ currentCopy.table.comments }}:</strong>
                                                        <div class="detail-inline-text">{{ item.comments || '-' }}</div>
                                                    </div>
                                                </div>
                                                <div class="item-detail-block">
                                                    <div class="detail-inline-main item-detail-inline-center">
                                                        <strong class="detail-inline-label">{{ currentCopy.table.files }}:</strong>
                                                        <div class="detail-inline-text">
                                                            <button
                                                                v-if="item.attachments?.length"
                                                                type="button"
                                                                class="file-preview-button"
                                                                @click="openAttachmentViewer(item.attachments)"
                                                            >
                                                                {{ fileButtonLabel(item.attachments) }}
                                                            </button>
                                                            <span v-else>-</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                </div>

                <div v-else class="subsection-surface">
                    <div class="section-heading">
                        <h2 class="directory-section-title">{{ currentCopy.service }}</h2>
                    </div>

                    <div class="service-request-card">
                        <div class="service-block">
                            <div class="detail-inline-value">
                                <div class="detail-inline-main">
                                    <strong class="detail-inline-label">{{ currentCopy.titleLabel }}:</strong>
                                    <div class="detail-inline-text">{{ rfq.service_title || '-' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="service-block">
                            <div class="detail-inline-value">
                                <div class="detail-inline-main detail-inline-main-wide">
                                    <strong class="detail-inline-label">{{ currentCopy.descriptionLabel }}:</strong>
                                    <div class="detail-inline-text detail-inline-text-long">{{ rfq.service_description || currentCopy.noDescription }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="service-block">
                            <div class="detail-inline-value">
                                <div class="detail-inline-main detail-inline-main-wide">
                                    <strong class="detail-inline-label">{{ currentCopy.files }}:</strong>
                                    <div v-if="rfq.attachments?.length" class="detail-inline-text detail-inline-text-long">
                                        <button
                                            type="button"
                                            class="file-preview-button"
                                            @click="openAttachmentViewer(rfq.attachments)"
                                        >
                                            {{ fileButtonLabel(rfq.attachments) }}
                                        </button>
                                    </div>
                                    <div v-else class="detail-inline-text detail-inline-text-long">{{ currentCopy.noFiles }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="hasBuyerOffers || rfq.status !== 'draft'" class="surface-card section-card combined-detail-section">
                <div
                    class="subsection-surface supplier-offers-surface"
                    :class="{ 'supplier-offers-surface-empty': !hasBuyerOffers }"
                >
                    <div class="section-heading">
                        <h2 class="directory-section-title">{{ currentCopy.supplierOffers }}</h2>
                        <p class="section-copy">{{ currentCopy.supplierOffersIntro }}</p>
                    </div>

                    <div v-if="hasBuyerOffers" class="buyer-offers-stack">
                        <div
                            v-for="offer in offers"
                            :key="offer.id"
                            class="buyer-offer-card"
                        >
                            <div class="buyer-offer-head">
                                <div class="buyer-offer-supplier">
                                    <h3 class="buyer-offer-supplier-title">{{ offer.seller.company_name || offer.seller.name }}</h3>
                                    <p class="buyer-offer-supplier-meta">
                                        {{ currentCopy.submittedOn }}: {{ formatDate(offer.submitted_at) }}
                                    </p>
                                </div>

                                <div v-if="offer.attachments?.length" class="buyer-offer-files">
                                    <div class="detail-inline-main">
                                        <strong class="detail-inline-label">{{ currentCopy.offerFiles }}:</strong>
                                        <div class="detail-inline-text">
                                            <button
                                                type="button"
                                                class="file-preview-button"
                                                @click="openAttachmentViewer(offer.attachments)"
                                            >
                                                {{ fileButtonLabel(offer.attachments) }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="isSpareParts" class="detail-table-wrap">
                                <table class="detail-table buyer-offer-table">
                                    <colgroup>
                                        <col class="col-line">
                                        <col class="col-product">
                                        <col class="col-part">
                                        <col class="col-manufacturer">
                                        <col class="col-qty">
                                        <col class="col-unit-price">
                                        <col class="col-line-total">
                                        <col class="col-currency">
                                        <col class="col-award-delivery">
                                        <col class="col-quality">
                                        <col class="col-award-note">
                                        <col class="col-files">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>{{ currentCopy.table.line }}</th>
                                            <th>{{ currentCopy.table.product }}</th>
                                            <th>{{ currentCopy.table.partNo }}</th>
                                            <th>{{ currentCopy.table.manufacturer }}</th>
                                            <th>{{ currentCopy.table.qty }} / {{ currentCopy.table.unit }}</th>
                                            <th>{{ currentCopy.unitPrice }}</th>
                                            <th>{{ currentCopy.total }}</th>
                                            <th>{{ currentCopy.labels.currency }}</th>
                                            <th>{{ currentCopy.deliveryTime }}</th>
                                            <th>{{ currentCopy.table.quality }}</th>
                                            <th>{{ currentCopy.commentsAndRemarks }}</th>
                                            <th>{{ currentCopy.table.files }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template v-for="offerItem in offer.items" :key="offerItem.id">
                                            <tr class="buyer-offer-request-row">
                                                <td rowspan="2" class="buyer-offer-shared-cell">
                                                    <div class="buyer-offer-line-cell">
                                                        <span>{{ offerItemRequestMeta(offerItem)?.line_no || offerItem.line_no || '-' }}</span>
                                                    </div>
                                                </td>
                                                <td rowspan="2" class="buyer-offer-shared-cell product-cell">{{ offerItemRequestMeta(offerItem)?.product_name || '-' }}</td>
                                                <td rowspan="2" class="buyer-offer-shared-cell">{{ offerItemRequestMeta(offerItem)?.part_no || '-' }}</td>
                                                <td>
                                                    <span class="offer-row-prefix">{{ currentCopy.customerRequest }}:</span>
                                                    {{ textOrDash(offerItemRequestMeta(offerItem)?.manufacturer) }}
                                                </td>
                                                <td>{{ textOrDash(offerItemRequestMeta(offerItem)?.quantity) }} {{ offerItemRequestMeta(offerItem)?.unit || '' }}</td>
                                                <td>-</td>
                                                <td>-</td>
                                                <td>-</td>
                                                <td>-</td>
                                                <td>{{ formatTitleCaseValue(offerItemRequestMeta(offerItem)?.quality) }}</td>
                                                <td>{{ textOrDash(offerItemRequestMeta(offerItem)?.comments) }}</td>
                                                <td>
                                                    <div class="buyer-offer-item-files">
                                                        <button
                                                            v-if="offerItemRequestMeta(offerItem)?.attachments?.length"
                                                            type="button"
                                                            class="file-preview-button"
                                                            @click="openAttachmentViewer(offerItemRequestMeta(offerItem).attachments)"
                                                        >
                                                            {{ fileButtonLabel(offerItemRequestMeta(offerItem).attachments) }}
                                                        </button>
                                                        <span v-else>-</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr class="buyer-offer-response-row">
                                                <td>
                                                    <span class="offer-row-prefix offer-row-prefix-strong">{{ currentCopy.supplierOffer }}:</span>
                                                    {{ textOrDash(offerItem.manufacturer) }}
                                                </td>
                                                <td>{{ textOrDash(offerItem.offer_qty) }} {{ offerItemRequestMeta(offerItem)?.unit || '' }}</td>
                                                <td>{{ textOrDash(decimalString(offerItem.unit_price)) }}</td>
                                                <td>{{ textOrDash(decimalString(offerItem.line_total)) }}</td>
                                                <td>{{ textOrDash(offer.currency || rfq.currency) }}</td>
                                                <td>{{ textOrDash(offerItem.delivery_time) }}</td>
                                                <td>{{ formatTitleCaseValue(offerItem.quality) }}</td>
                                                <td>{{ textOrDash(offerItem.remarks) }}</td>
                                                <td>
                                                    <div class="buyer-offer-item-files">
                                                        <button
                                                            v-if="offerItem.attachments?.length"
                                                            type="button"
                                                            class="file-preview-button"
                                                            @click="openAttachmentViewer(offerItem.attachments)"
                                                        >
                                                            {{ fileButtonLabel(offerItem.attachments) }}
                                                        </button>
                                                        <span v-else>-</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <div class="buyer-offer-summary">
                                <OfferCommercialSummary
                                    :offer="offer"
                                    :request-type="rfq.request_type"
                                    :summary-amount="offer.total_offer_amount"
                                    :award-scope-label="isSpareParts ? offerAwardScopeLabel(offer.award_scope_policy) : ''"
                                />
                            </div>
                        </div>
                    </div>

                    <p v-else class="detail-inline-text detail-inline-text-long offer-empty-state">{{ currentCopy.noOffersYet }}</p>
                </div>
            </section>

            <section v-if="showSupplierScopeSection" class="surface-card section-card combined-detail-section">
                <RfqGeneralInformationSection
                    :title="currentCopy.suppliers"
                    :columns="5"
                    compact
                    :fields="supplierScopeFields"
                    @action="openDetailModal"
                />
            </section>

        </section>

        <div v-if="detailModal" class="detail-modal-backdrop" @click.self="closeDetailModal">
            <div class="detail-modal">
                <div class="detail-modal-head">
                    <h3 class="detail-modal-title">
                        {{
                            detailModal === 'countries'
                                ? currentCopy.selectedCountries
                                : detailModal === 'ports'
                                    ? currentCopy.selectedPorts
                                    : detailModal === 'categories'
                                        ? currentCopy.selectedCategories
                                        : detailModal === 'brands'
                                            ? currentCopy.selectedBrands
                                            : currentCopy.selectedSubcategories
                        }}
                    </h3>
                    <button type="button" class="detail-modal-close" @click="closeDetailModal">
                        {{ currentCopy.close }}
                    </button>
                </div>

                <div v-if="detailModal === 'countries'" class="detail-modal-body">
                    <div class="modal-pill-list">
                        <span v-for="country in selectedCountries" :key="country" class="modal-pill">
                            {{ country }}
                        </span>
                    </div>
                </div>

                <div v-else-if="detailModal === 'ports'" class="detail-modal-body">
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

                <div v-else-if="detailModal === 'categories'" class="detail-modal-body">
                    <div class="modal-pill-list">
                        <span v-for="category in selectedCategoryNames" :key="category" class="modal-pill">
                            {{ category }}
                        </span>
                    </div>
                </div>

                <div v-else-if="detailModal === 'brands'" class="detail-modal-body">
                    <div class="modal-pill-list">
                        <span v-for="brand in selectedBrandNames" :key="brand" class="modal-pill">
                            {{ brand }}
                        </span>
                    </div>
                </div>

                <div v-else class="detail-modal-body">
                    <div class="modal-pill-list">
                        <span v-for="subcategory in selectedSubcategoryNames" :key="subcategory" class="modal-pill">
                            {{ subcategory }}
                        </span>
                    </div>
                </div>

            </div>
        </div>

        <div v-if="attachmentViewer" class="gallery-modal-backdrop" @click.self="closeAttachmentViewer">
            <div class="gallery-modal">
                <div class="detail-modal-head">
                    <div class="gallery-modal-title-group">
                        <h3 class="detail-modal-title">{{ currentCopy.files }}</h3>
                        <p class="gallery-modal-counter">{{ attachmentIndex + 1 }} / {{ attachmentViewer.length }}</p>
                    </div>
                    <button type="button" class="detail-modal-close" @click="closeAttachmentViewer">
                        {{ currentCopy.close }}
                    </button>
                </div>

                <div class="gallery-modal-body">
                    <button
                        v-if="hasAttachmentGallery"
                        type="button"
                        class="gallery-nav-button is-left"
                        :aria-label="currentCopy.previous"
                        @click="goToPreviousAttachment"
                    >
                        ‹
                    </button>

                    <div class="gallery-stage">
                        <img
                            v-if="isImageAttachment(currentAttachment)"
                            :src="currentAttachmentUrl"
                            :alt="`${currentCopy.file} ${attachmentIndex + 1}`"
                            class="gallery-image"
                        />
                        <iframe
                            v-else-if="isPdfAttachment(currentAttachment)"
                            :src="currentAttachmentViewerUrl"
                            class="gallery-iframe"
                            :title="`${currentCopy.file} ${attachmentIndex + 1}`"
                        ></iframe>
                        <div v-else class="gallery-file-fallback">
                            <p class="detail-inline-text detail-inline-text-long">{{ currentCopy.previewUnavailable }}</p>
                            <a
                                :href="currentAttachmentUrl"
                                class="offer-button offer-button-login"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ currentCopy.openFile }}
                            </a>
                        </div>
                    </div>

                    <button
                        v-if="hasAttachmentGallery"
                        type="button"
                        class="gallery-nav-button is-right"
                        :aria-label="currentCopy.next"
                        @click="goToNextAttachment"
                    >
                        ›
                    </button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.detail-shell {
    padding: 16px 0 56px;
}

.surface-panel,
.surface-card {
    padding: 32px 36px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.08);
}

.section-card {
    margin-top: 16px;
}

.combined-detail-section {
    display: grid;
    gap: 0;
    min-width: 0;
}

.subsection-surface {
    padding: 24px;
    border-radius: 10px;
    background: #f8fafb;
    min-width: 0;
}

.supplier-offers-surface {
    padding: 0;
    border-radius: 0;
    background: transparent;
}

.supplier-offers-surface-empty {
    padding: 24px;
    border-radius: 10px;
    background: #f8fafb;
}

.section-divider {
    margin: 28px 0 0;
}

.hero-panel {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
}

.hero-copy {
    flex: 1;
}

.eyebrow {
    margin: 0 0 12px;
    font-size: 0.82rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--color-ocean);
    font-weight: 700;
}

.hero-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 18px;
}

.pill,
.status-pill,
.count-chip,
.file-preview-button,
.back-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 0 14px;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    white-space: nowrap;
}

.request-type-pill {
    background: rgba(15, 23, 42, 0.06);
    color: #334155;
}

.priority-pill.is-critical {
    background: rgba(239, 68, 68, 0.12);
    color: #dc2626;
}

.priority-pill.is-high {
    background: rgba(249, 115, 22, 0.14);
    color: #c2410c;
}

.priority-pill.is-normal {
    background: rgba(20, 184, 166, 0.1);
    color: #0f766e;
}

.priority-pill.is-low {
    background: rgba(15, 23, 42, 0.06);
    color: #475569;
}

.status-pill {
    gap: 8px;
    background: rgba(255, 255, 255, 0.92);
    color: #334155;
    border: 1px solid rgba(148, 163, 184, 0.22);
}

.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    display: inline-block;
    box-shadow: 0 0 0 3px transparent;
}

.status-dot.is-open {
    background: #22c55e;
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.16);
}

.status-dot.is-awarded {
    background: #0f766e;
    box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.16);
}

.status-dot.is-completed {
    background: #16a34a;
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.16);
}

.status-dot.is-closed {
    background: #ef4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.16);
}

.status-dot.is-draft {
    background: #f59e0b;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.16);
}

.hero-actions {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    flex-wrap: wrap;
}

.hero-actions .back-button,
.hero-actions .compare-button {
    width: 172px;
}

.back-button {
    text-decoration: none;
    background: #2563eb;
    color: #fff;
    box-shadow: 0 12px 24px rgba(37, 99, 235, 0.18);
}

.compare-button {
    background: #0f766e;
    box-shadow: 0 12px 24px rgba(15, 118, 110, 0.2);
}

.section-head,
.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 18px;
}

.section-head h2,
.section-heading h2 {
    margin: 0;
    font-size: 1.04rem;
    font-weight: 700;
    color: #0f172a;
}

.count-chip {
    background: rgba(255, 255, 255, 0.92);
    color: #334155;
    border: 1px solid rgba(148, 163, 184, 0.22);
}

.hero-offers-chip {
    background: rgba(34, 197, 94, 0.12);
    color: #15803d;
    border: 0;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px 18px;
}

.info-field {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.info-field-wide {
    grid-column: span 2;
}

.detail-inline-value {
    display: grid;
    align-items: start;
    row-gap: 4px;
    min-height: 0;
    padding: 0;
    line-height: 1.25;
    min-width: 0;
}

.detail-inline-main {
    display: grid;
    grid-template-columns: 122px minmax(0, 1fr);
    align-items: start;
    column-gap: 8px;
}

.detail-inline-main-wide {
    grid-template-columns: 122px minmax(0, 1fr);
}

.detail-inline-label {
    color: #04151f;
    font-size: 14px;
    font-weight: 700;
    line-height: 1.2;
    white-space: nowrap;
}

.detail-inline-text {
    color: rgba(4, 21, 31, 0.82);
    font-size: 15px;
    font-weight: 400;
    display: block;
    min-width: 0;
    line-height: 1.2;
    white-space: normal;
    overflow: visible;
    text-overflow: clip;
    word-break: break-word;
}

.detail-inline-text-long {
    line-height: 1.6;
}

.notes-text,
.description-text,
.service-title-text,
.recipient-card p,
.recipient-card span {
    margin: 0;
    color: rgba(4, 21, 31, 0.82);
    font-size: 15px;
    font-weight: 400;
    line-height: 1.6;
}

.service-title-text {
    font-weight: 400;
}

.port-stack {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.detail-inline-summary {
    display: inline-flex;
    align-items: baseline;
    gap: 10px;
    flex-wrap: wrap;
}

.detail-inline-link {
    border: 0;
    background: transparent;
    color: rgba(4, 21, 31, 0.82);
    text-decoration: underline;
    text-decoration-thickness: 1px;
    text-underline-offset: 3px;
    cursor: pointer;
    text-align: left;
    padding: 0;
}

.detail-value-link {
    appearance: none;
    border: 0;
    background: transparent;
    color: inherit;
    font: inherit;
    line-height: inherit;
    text-decoration: underline;
    text-decoration-thickness: 1px;
    text-underline-offset: 3px;
    cursor: pointer;
    text-align: left;
    padding: 0;
}

.summary-view-button {
    border: 0;
    background: transparent;
    color: #2563eb;
    font-size: 14px;
    font-weight: 700;
    padding: 0;
}

.port-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail-table-wrap {
    overflow-x: auto;
    padding-bottom: 14px;
}

.detail-table {
    width: 100%;
    min-width: 920px;
    border-collapse: collapse;
    table-layout: fixed;
}

.detail-table .col-line {
    width: 48px;
}

.detail-table .col-product { width: 190px; }
.detail-table .col-part { width: 110px; }
.detail-table .col-manufacturer { width: 120px; }
.detail-table .col-model { width: 110px; }
.detail-table .col-catalog { width: 110px; }
.detail-table .col-serial { width: 110px; }
.detail-table .col-drawing { width: 110px; }
.detail-table .col-qty { width: 110px; }
.detail-table .col-rob { width: 70px; }
.detail-table .col-quality { width: 100px; }
.detail-table .col-award-note { width: 180px; }
.detail-table .col-award-delivery { width: 170px; }

.detail-table thead th {
    padding: 10px 8px;
    background: #f4f7fb;
    color: #04151f;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.2;
    text-align: left;
    white-space: nowrap;
}

.detail-table thead th:first-child {
    text-align: center;
}

.detail-table tbody td {
    padding: 10px 8px;
    border-top: 1px solid rgba(4, 21, 31, 0.06);
    color: rgba(4, 21, 31, 0.82);
    font-size: 13px;
    font-weight: 400;
    line-height: 1.45;
    vertical-align: top;
}

.line-cell {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    white-space: nowrap;
    width: 100%;
}

.expand-row-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border: 0;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.06);
    color: #334155;
    cursor: pointer;
    padding: 0;
}

.expand-row-icon {
    display: inline-block;
    font-size: 12px;
    line-height: 1;
    transform: rotate(0deg);
    transition: transform 160ms ease;
}

.expand-row-icon.is-open {
    transform: rotate(90deg);
}

.product-cell {
    color: #0f172a;
    font-weight: 600;
}

.item-detail-row td {
    padding-top: 0;
    background: #fff;
}

.item-detail-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.8fr) minmax(220px, 0.7fr);
    gap: 14px;
    padding: 0 0 16px 54px;
    align-items: start;
}

.item-detail-block {
    min-width: 0;
}

.item-detail-inline-center {
    align-items: center;
}

.item-detail-block .detail-inline-main {
    align-items: center;
}

.item-detail-grid .detail-inline-label {
    font-size: 12px;
    line-height: 1.25;
}

.item-detail-grid .detail-inline-text,
.item-detail-grid .file-preview-button {
    font-size: 12px;
    line-height: 1.25;
}

.file-preview-button {
    border: 0;
    background: transparent;
    color: inherit;
    cursor: pointer;
    min-height: auto;
    padding: 0;
    border-radius: 0;
    font-size: 15px;
    font-weight: 400;
    line-height: 1.2;
    text-decoration: underline;
    text-decoration-thickness: 1px;
    text-underline-offset: 3px;
}

.service-request-card {
    display: flex;
    flex-direction: column;
    gap: 22px;
}

.service-block {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.empty-inline {
    color: rgba(4, 21, 31, 0.62);
    font-size: 0.95rem;
}

.detail-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: rgba(15, 23, 42, 0.42);
    backdrop-filter: blur(6px);
}

.detail-modal {
    width: min(760px, 100%);
    max-height: min(80vh, 720px);
    overflow: auto;
    border-radius: 14px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    background: #fff;
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.18);
}

.detail-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 22px 24px 16px;
    border-bottom: 1px solid rgba(4, 21, 31, 0.08);
}

.detail-modal-title {
    margin: 0;
    font-size: 1.04rem;
    font-weight: 700;
    color: #0f172a;
}

.detail-modal-close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    padding: 0 14px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: #fff;
    color: #04151f;
    font-size: 0.86rem;
    font-weight: 600;
}

.detail-modal-body {
    padding: 22px 24px 24px;
}

.gallery-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 60;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: rgba(15, 23, 42, 0.58);
    backdrop-filter: blur(8px);
}

.gallery-modal {
    width: min(980px, 100%);
    border-radius: 18px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    background: #fff;
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.22);
    overflow: hidden;
}

.gallery-modal-title-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.gallery-modal-counter {
    margin: 0;
    color: rgba(4, 21, 31, 0.62);
    font-size: 0.86rem;
    font-weight: 600;
}

.gallery-modal-body {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 560px;
    padding: 32px 76px;
    background: #f8fafb;
}

.gallery-stage {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.gallery-image {
    max-width: 100%;
    max-height: 520px;
    border-radius: 14px;
    object-fit: contain;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
    background: #fff;
}

.gallery-iframe {
    width: 100%;
    height: 520px;
    border: 0;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
}

.gallery-file-fallback {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    text-align: center;
}

.offers-heading {
    align-items: flex-start;
}

.section-copy {
    margin: 6px 0 0;
    max-width: 78ch;
    color: #64748b;
    font-size: 0.92rem;
    line-height: 1.7;
}

.buyer-offers-stack {
    display: grid;
    gap: 16px;
}

.buyer-offer-card {
    display: grid;
    gap: 18px;
    padding: 24px;
    border: 0;
    border-radius: 10px;
    background: #f8fafb;
    box-shadow: none;
}

.buyer-offer-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.buyer-offer-supplier {
    display: grid;
    gap: 6px;
}

.buyer-offer-supplier-title {
    margin: 0;
    color: #0f172a;
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.3;
}

.buyer-offer-supplier-meta {
    margin: 0;
    color: #64748b;
    font-size: 0.84rem;
    line-height: 1.45;
}

.buyer-offer-files {
    min-width: 0;
}

.buyer-offer-files .detail-inline-main {
    grid-template-columns: 88px minmax(0, 1fr);
    align-items: center;
}

.buyer-offer-card .detail-table-wrap {
    padding-bottom: 0;
}

.buyer-offer-card .detail-table thead th {
    background: #f4f7fb;
}

.buyer-offer-table {
    min-width: 1270px;
}

.buyer-offer-table .col-manufacturer {
    width: 120px;
}

.buyer-offer-table .col-qty {
    width: 110px;
}

.buyer-offer-table .col-quality {
    width: 100px;
}

.buyer-offer-table .col-award-delivery {
    width: 92px;
}

.buyer-offer-table .col-award-note {
    width: 140px;
}

.buyer-offer-table .col-unit-price,
.buyer-offer-table .col-line-total {
    width: 88px;
}

.buyer-offer-table .col-currency {
    width: 72px;
}

.buyer-offer-table .col-files {
    width: 112px;
}

.buyer-offer-shared-cell {
    background: #f8fafb;
    vertical-align: middle;
}

.buyer-offer-line-cell {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100%;
    white-space: nowrap;
}

.buyer-offer-request-row td {
    background: #f8fafc;
}

.buyer-offer-response-row td {
    background: #eaf3ff;
}

.buyer-offer-request-row td,
.buyer-offer-response-row td {
    line-height: 1.4;
}

.offer-row-prefix {
    color: #64748b;
    font-size: 12px;
    font-weight: 600;
    margin-right: 4px;
}

.offer-row-prefix-strong {
    color: #15803d;
}

.buyer-offer-item-files {
    min-height: 20px;
}

.buyer-offer-summary {
    padding-top: 18px;
    border-top: 1px solid rgba(148, 163, 184, 0.18);
}

.offer-empty-state {
    margin: 16px 0 0;
}

.offer-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 18px;
    border-radius: 10px;
    background: #2563eb;
    color: #fff;
    font-size: 0.92rem;
    font-weight: 700;
    text-decoration: none;
    border: 0;
}

.offer-button-login {
    background: #2563eb;
}

.gallery-nav-button {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.96);
    color: #04151f;
    font-size: 0;
    line-height: 1;
    cursor: pointer;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
}

.gallery-nav-button::before {
    font-size: 26px;
    line-height: 1;
}

.gallery-nav-button.is-left {
    left: 20px;
}

.gallery-nav-button.is-left::before {
    content: '\2039';
}

.gallery-nav-button.is-right {
    right: 20px;
}

.gallery-nav-button.is-right::before {
    content: '\203A';
}

.modal-port-stack {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.modal-port-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.modal-pill-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.modal-pill {
    display: inline-flex;
    align-items: center;
    min-height: 34px;
    padding: 0 12px;
    border-radius: 10px;
    background: #f8fafb;
    color: #334155;
    font-size: 0.84rem;
    font-weight: 500;
}

@media (max-width: 1180px) {
    .info-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 860px) {
    .hero-panel {
        flex-direction: column;
    }

    .buyer-offer-head {
        flex-direction: column;
    }

    .info-grid {
        grid-template-columns: 1fr;
    }

    .info-field-wide {
        grid-column: span 1;
    }
}

@media (max-width: 720px) {
    .surface-panel,
    .surface-card {
        padding: 24px;
    }

    .hero-actions {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
    }

    .hero-actions .back-button,
    .hero-actions .compare-button {
        width: 100%;
    }

    .buyer-offer-card {
        padding: 16px;
    }

    .buyer-offer-files .detail-inline-main {
        grid-template-columns: 1fr;
        row-gap: 6px;
    }
}
</style>
