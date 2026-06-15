<script setup>
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import MainLayout from '../../Layouts/MainLayout.vue';
import PublicMetaHead from '../../Components/PublicMetaHead.vue';
import RequestCard from '../../Components/RequestCard.vue';
import RfqGeneralInformationSection from '../../Components/RfqGeneralInformationSection.vue';

const props = defineProps({
    rfq: {
        type: Object,
        required: true,
    },
    similarRfqs: {
        type: Array,
        default: () => [],
    },
    similarUrl: {
        type: String,
        default: null,
    },
    backUrl: {
        type: String,
        required: true,
    },
    meta: {
        type: Object,
        default: () => ({
            title: 'Request Detail | Sea Requests',
            description: '',
            canonical: '',
            robots: 'index, follow',
            ogImage: '',
            twitterCard: 'summary_large_image',
        }),
    },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user ?? null);
const detailModal = ref(null);
const attachmentViewer = ref(null);
const attachmentIndex = ref(0);
const expandedItems = ref({});
const similarStartIndex = ref(0);
const similarVisibleCount = ref(4);
const similarRfqsData = ref(props.similarRfqs ?? []);

const copy = {
        eyebrow: 'Published Request',
        title: 'Request Detail',
        heroSparePartsTitle: 'Spare Parts Request',
        heroServiceTitle: 'Service Request',
        text: 'Review the request scope exactly as it was published and check whether you can submit an offer from this page.',
        privateRequest: 'Private Request',
        back: 'Back to Requests',
        general: 'General Information',
        items: 'Items to Quote',
        service: 'Service Request',
        offerCard: 'Submit Offer',
        buyerRfqCard: 'Buyer RFQ',
        supplierRfqCard: 'Supplier RFQ',
        orderDetailCard: 'Order Detail',
        continueDraft: 'Continue Draft',
        editOffer: 'Edit Offer',
        submittedOffer: 'Submitted',
        draftSaved: 'Draft saved',
        offerQty: 'Offer Qty',
        unitPrice: 'Unit Price',
        totalPrice: 'Total Price',
        total: 'Total',
        leadTime: 'Delivery Time',
        completionTime: 'Completion Time',
        offerValidity: 'Offer Validity',
        remarks: 'Remarks',
        files: 'Files',
        tax: 'Including Tax',
        packing: 'Including Packing',
        freight: 'Including Freight',
        mobilization: 'Including Mobilization',
        grandTotal: 'Grand Total',
        serviceClarification: 'Service Clarification',
        deliveryTerms: 'Delivery Terms',
        otherDeliveryTerms: 'Other Delivery Terms',
        paymentTerms: 'Payment Terms',
        otherPaymentTerms: 'Other Payment Terms',
        paymentOrderConfirmation: '% when order confirmation',
        paymentBeforeShipment: '% before shipment',
        paymentInvoiceDays: 'days from Invoice Date',
        generalNote: 'General Note',
        included: 'Included',
        file: 'File',
        fileAddedSingular: 'file added',
        fileAddedPlural: 'files added',
        titleLabel: 'Title',
        descriptionLabel: 'Description',
        noFiles: 'No files',
        noNotes: 'No notes added',
        noDescription: 'No description added',
        view: 'View',
        countriesSelected: 'countries selected',
        portsSelected: 'ports selected',
        selectedPorts: 'Selected Ports',
        selectedCountries: 'Selected Countries',
        allListedPortsIn: 'All listed ports in',
        portsSelectedSuffix: 'ports selected',
        close: 'Close',
        previous: 'Previous',
        next: 'Next',
        openFile: 'Open file',
        previewUnavailable: 'Preview unavailable for this file type.',
        offer: 'Submit Offer',
        buyerRfqAction: 'Go to Buyer RFQ',
        supplierRfqAction: 'Go to Supplier RFQ',
        awardDetail: 'Go to Order Detail',
        offerLogin: 'Sign in to continue',
        offerRegister: 'Create seller or buyer account',
        offerLocked: 'Offer unavailable',
        similarEyebrow: 'Similar RFQs',
        similarTitle: 'Other published requests you may want to review',
        openRequest: 'Open Request',
        live: 'LIVE',
        closeBadge: 'CLOSE',
        yourOffer: 'Your Offer',
        notices: {
            rfq_closed: 'This request is closed and no longer accepts new offers.',
            buyer_cannot_offer: 'Buyer accounts cannot submit offers from this area.',
            seller_only: 'A supplier account is required before you can submit an offer.',
            approval_required: 'Your supplier account must be approved before you can submit an offer.',
            scope_mismatch: 'This request is not open to your selected countries, ports, categories, or subcategories.',
            login_required: 'Sign in with your seller or buyer account to review this request and continue to the offer process.',
            eligible: 'Your supplier account matches this request scope. Review the request details and continue to submit your offer from here.',
            draft_saved: 'You have a saved draft for this request. Continue where you left off or review your entered lines below.',
            submitted: 'You have already submitted an offer for this request. Review the request details and your submitted lines below.',
            awarded_to_you: 'This request has already been awarded to your company. Continue from your Order Detail screen to review selected lines and the next workflow step.',
            buyer_rfq_owner: 'This request belongs to your buyer account. Continue in Buyer RFQ to review offers and manage the next workflow steps.',
        },
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
            awardConfirmed: 'Award Confirmed',
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
            open: 'Open',
            close: 'Close',
            award_confirmed: 'Award Confirmed',
        },
        priority: {
            low: 'Low',
            normal: 'Normal',
            high: 'High',
            critical: 'Critical',
        },
    };

const parseDeliveryDays = (value) => {
    const match = `${value ?? ''}`.match(/(\d+(?:[.,]\d+)?)/);
    return match ? Number(match[1].replace(',', '.')) : null;
};

const formatDeliveryDays = (value) => {
    const days = parseDeliveryDays(value);
    return days !== null ? `${days} days` : (value || '-');
};

const currentCopy = computed(() => copy);
const isSpareParts = computed(() => props.rfq.request_type === 'spare_parts');
const isPrivateRequest = computed(() => Boolean(props.rfq.is_private_request));
const isBuyerOwnerView = computed(() => Boolean(props.rfq.is_buyer_owner));
const isSellerWorkspaceView = computed(() => props.rfq.page_mode === 'seller_workspace');
const showSupplierWorkspace = computed(() => Boolean(props.rfq.show_supplier_workspace));
const registerUrl = '/register';
const heroTitle = computed(() => {
    const serviceTitle = `${props.rfq.service_title ?? ''}`.trim();

    if (!isSpareParts.value && serviceTitle) {
        return serviceTitle;
    }

    return isSpareParts.value
        ? currentCopy.value.heroSparePartsTitle
        : currentCopy.value.heroServiceTitle;
});
const heroEyebrow = computed(() => props.rfq.eyebrow || currentCopy.value.eyebrow);
const heroIntroCopy = computed(() => props.rfq.detail_text || currentCopy.value.text);
const heroVisibilityNotice = computed(() => props.rfq.detail_notice || '');

const isAwardedSellerView = computed(() => showSupplierWorkspace.value && props.rfq.offer_state === 'awarded');
const canViewCompanyShip = computed(() => Boolean(props.rfq.can_view_company_ship));

const generalInformationFields = computed(() => {
    const statusField = {
        key: 'status',
        label: currentCopy.value.labels.status,
        value: currentCopy.value.statuses[props.rfq.status] || props.rfq.status || '-',
    };

    const countryField = {
        key: 'country',
        label: currentCopy.value.labels.country,
        value: `${selectedCountryCount.value} ${currentCopy.value.countriesSelected}`,
        clickable: true,
        action: 'countries',
    };

    const portsField = {
        key: 'ports',
        label: currentCopy.value.labels.ports,
        value: `${selectedPortCount.value} ${currentCopy.value.portsSelected}`,
        clickable: true,
        action: 'ports',
    };

    const companyField = {
        key: 'company',
        label: currentCopy.value.labels.company,
        value: props.rfq.company_name || '-',
    };

    const shipField = {
        key: 'ship',
        label: currentCopy.value.labels.ship,
        value: props.rfq.ship_name || '-',
    };

    if (isAwardedSellerView.value) {
        return [
            {
                key: 'reference_no',
                label: currentCopy.value.labels.referenceNo,
                value: props.rfq.reference_no || '-',
            },
            companyField,
            shipField,
            statusField,
            countryField,
            portsField,
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
                key: 'award_confirmed',
                label: currentCopy.value.labels.awardConfirmed,
                value: formatDateTime(props.rfq.award_confirmed_at),
            },
            {
                key: 'general_notes',
                label: currentCopy.value.labels.generalNotes,
                value: props.rfq.general_notes || '-',
                wide: true,
                long: true,
            },
        ];
    }

    return [
        {
            key: 'reference_no',
            label: currentCopy.value.labels.referenceNo,
            value: props.rfq.reference_no || '-',
        },
        ...(canViewCompanyShip.value ? [companyField, shipField] : []),
        countryField,
        portsField,
        statusField,
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
            value: props.rfq.general_notes || '-',
            wide: true,
            long: true,
        },
    ];
});

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

const formatDateTime = (value) => {
    if (!value) return '-';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};

const formatFileSize = (value) => {
    const size = Number(value ?? 0);

    if (!Number.isFinite(size) || size <= 0) {
        return '';
    }

    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

const formatTitleCaseValue = (value) => {
    const normalized = `${value ?? ''}`.trim();
    if (!normalized) return '-';
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
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
    return 'is-closed';
};

const priorityTone = (priority) => {
    if (priority === 'critical') return 'is-critical';
    if (priority === 'high') return 'is-high';
    if (priority === 'low') return 'is-low';
    return 'is-normal';
};

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

const selectedCountryCount = computed(() => selectedCountries.value.length);
const selectedPortCount = computed(() => portGroups.value
    .reduce((total, entry) => total + (entry.ports?.length ?? 0), 0));
const portSelectionThreshold = 10;
const similarRfqs = computed(() => similarRfqsData.value ?? []);
const visibleSimilarRfqs = computed(() =>
    similarRfqs.value.slice(similarStartIndex.value, similarStartIndex.value + similarVisibleCount.value)
);
const canSlideSimilarPrev = computed(() => similarStartIndex.value > 0);
const canSlideSimilarNext = computed(() => similarStartIndex.value + similarVisibleCount.value < similarRfqs.value.length);

const openDetailModal = (type) => {
    detailModal.value = type;
};

const closeDetailModal = () => {
    detailModal.value = null;
};

const isImageAttachment = (attachment) => {
    const mimeType = `${attachment?.type ?? attachment?.mime_type ?? ''}`.toLowerCase();

    if (mimeType.startsWith('image/')) {
        return true;
    }

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

const toggleItemDetails = (itemId) => {
    expandedItems.value = {
        ...expandedItems.value,
        [itemId]: !expandedItems.value[itemId],
    };
};

const isItemExpanded = (itemId) => !!expandedItems.value[itemId];

const requestCardTitle = (item) => {
    if (item.request_type === 'service_request') {
        return item.service_title || currentCopy.value.heroServiceTitle;
    }

    return currentCopy.value.heroSparePartsTitle;
};

const requestCardDescription = (item) => {
    if (item.request_type === 'service_request') {
        return item.service_description || `${item.company_mask || 'REQ***'} has published a service request.`;
    }

    return `A spare parts request for ${Number(item.items_count ?? 0)} products has been published by ${item.company_mask || 'REQ***'}. Review the details to submit your offer.`;
};

const requestCardCountries = (item) => {
    const countries = Array.isArray(item.country_names) ? item.country_names.filter(Boolean) : [];

    if (countries.length) {
        return countries.join(', ');
    }

    return item.country_name || '-';
};

const relativeTime = (value) => {
    if (!value) return '-';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';

    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const future = diffMs < 0;
    const absSeconds = Math.max(1, Math.round(Math.abs(diffMs) / 1000));
    const absMinutes = Math.round(absSeconds / 60);
    const absHours = Math.round(absMinutes / 60);
    const absDays = Math.round(absHours / 24);
    const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

    if (absSeconds < 60) {
        return rtf.format(future ? absSeconds : -absSeconds, 'second');
    }

    if (absMinutes < 60) {
        return rtf.format(future ? absMinutes : -absMinutes, 'minute');
    }

    if (absHours < 24) {
        return rtf.format(future ? absHours : -absHours, 'hour');
    }

    return rtf.format(future ? absDays : -absDays, 'day');
};

const syncSimilarViewport = () => {
    if (typeof window === 'undefined') {
        return;
    }

    if (window.innerWidth <= 720) {
        similarVisibleCount.value = 1;
    } else if (window.innerWidth <= 960) {
        similarVisibleCount.value = 2;
    } else {
        similarVisibleCount.value = 4;
    }

    const maxStart = Math.max(0, similarRfqs.value.length - similarVisibleCount.value);
    similarStartIndex.value = Math.min(similarStartIndex.value, maxStart);
};

const slideSimilarPrev = () => {
    similarStartIndex.value = Math.max(0, similarStartIndex.value - 1);
};

const slideSimilarNext = () => {
    const maxStart = Math.max(0, similarRfqs.value.length - similarVisibleCount.value);
    similarStartIndex.value = Math.min(maxStart, similarStartIndex.value + 1);
};

const handleGlobalKeydown = (event) => {
    if (!attachmentViewer.value) {
        if (event.key === 'ArrowLeft' && canSlideSimilarPrev.value) {
            slideSimilarPrev();
        } else if (event.key === 'ArrowRight' && canSlideSimilarNext.value) {
            slideSimilarNext();
        }
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
    window.addEventListener('resize', syncSimilarViewport);
    syncSimilarViewport();

    if (props.similarUrl && !showSupplierWorkspace.value) {
        axios.get(props.similarUrl)
            .then((response) => {
                similarRfqsData.value = Array.isArray(response?.data?.data) ? response.data.data : [];
                syncSimilarViewport();
            })
            .catch(() => {
                similarRfqsData.value = [];
            });
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
    window.removeEventListener('resize', syncSimilarViewport);
});

const detailModalTitle = computed(() => (
    detailModal.value === 'countries'
        ? currentCopy.value.selectedCountries
        : currentCopy.value.selectedPorts
));

const myOffer = computed(() => props.rfq.my_offer ?? null);
const myOfferStatus = computed(() => myOffer.value?.status ?? null);
const displayedItems = computed(() => {
    const items = Array.isArray(props.rfq.items) ? props.rfq.items : [];

    if (!showSupplierWorkspace.value || props.rfq.offer_state !== 'awarded' || !isSpareParts.value) {
        return items;
    }

    return items.filter((item) => {
        const awardedQty = Number(item?.my_offer?.buyer_awarded_quantity ?? 0);
        return item?.my_offer?.buyer_award_status === 'confirmed' && awardedQty > 0;
    });
});

const heroOfferNotice = computed(() => {
    if (props.rfq.offer_state === 'awarded') {
        return currentCopy.value.notices.awarded_to_you;
    }

    if (myOfferStatus.value === 'draft') {
        return currentCopy.value.notices.draft_saved;
    }

    if (myOfferStatus.value === 'submitted') {
        return currentCopy.value.notices.submitted;
    }

    return '';
});

const offerNotice = computed(() => {
    if (isBuyerOwnerView.value) {
        return currentCopy.value.notices.buyer_rfq_owner;
    }

    if (props.rfq.offer_state === 'awarded') {
        return currentCopy.value.notices.awarded_to_you;
    }

    if (!isSellerWorkspaceView.value && myOfferStatus.value === 'draft') {
        return 'You have a saved draft for this request. Continue in Supplier RFQ to review your entered lines and finish your quotation.';
    }

    if (!isSellerWorkspaceView.value && myOfferStatus.value === 'submitted') {
        return 'You have already submitted an offer for this request. Continue in Supplier RFQ to review your submitted quotation and next supplier actions.';
    }

    if (props.rfq.offer_notice === 'rfq_closed' && !currentUser.value) {
        return 'This request is closed and no longer accepts new offers. Create a seller or buyer account to discover new service opportunities on the platform.';
    }

    if (myOfferStatus.value === 'draft') {
        return currentCopy.value.notices.draft_saved;
    }

    if (myOfferStatus.value === 'submitted') {
        return currentCopy.value.notices.submitted;
    }

    if (props.rfq.offer_state === 'eligible') {
        return currentCopy.value.notices.eligible;
    }

    return currentCopy.value.notices[props.rfq.offer_notice] ?? '';
});

const actionCardTitle = computed(() => {
    if (isBuyerOwnerView.value) {
        return currentCopy.value.buyerRfqCard;
    }

    if (props.rfq.offer_state === 'awarded') {
        return currentCopy.value.orderDetailCard;
    }

    if (!isSellerWorkspaceView.value && (myOfferStatus.value === 'draft' || myOfferStatus.value === 'submitted')) {
        return currentCopy.value.supplierRfqCard;
    }

    return currentCopy.value.offerCard;
});

const offerButtonLabel = computed(() => {
    if (isBuyerOwnerView.value) {
        return currentCopy.value.buyerRfqAction;
    }

    if (props.rfq.offer_state === 'awarded') {
        return currentCopy.value.awardDetail;
    }

    if (!isSellerWorkspaceView.value && (myOfferStatus.value === 'draft' || myOfferStatus.value === 'submitted')) {
        return currentCopy.value.supplierRfqAction;
    }

    if (myOfferStatus.value === 'draft') {
        return currentCopy.value.continueDraft;
    }

    if (myOfferStatus.value === 'submitted') {
        return currentCopy.value.editOffer;
    }

    return currentCopy.value.offer;
});

const myOfferStatusLabel = computed(() => (
    myOfferStatus.value === 'submitted'
        ? currentCopy.value.submittedOffer
        : currentCopy.value.draftSaved
));

const sparePartsPaymentSummary = computed(() => {
    if (!myOffer.value) {
        return '-';
    }

    const parts = [];

    if (myOffer.value.payment_order_confirmation) {
        parts.push(`${myOffer.value.payment_order_confirmation}% ${currentCopy.value.paymentOrderConfirmation}`);
    }

    if (myOffer.value.payment_before_shipment) {
        parts.push(`${myOffer.value.payment_before_shipment}% ${currentCopy.value.paymentBeforeShipment}`);
    }

    if (myOffer.value.payment_invoice_days) {
        parts.push(`${myOffer.value.payment_invoice_days} ${currentCopy.value.paymentInvoiceDays}`);
    }

    return parts.length ? parts.join(' / ') : '-';
});

const offerAmountDisplay = (value, currency) => {
    const normalizedValue = `${value ?? ''}`.trim();
    const normalizedCurrency = `${currency ?? ''}`.trim();

    if (!normalizedValue || normalizedValue === '-') {
        return '-';
    }

    return normalizedCurrency ? `${normalizedCurrency} ${normalizedValue}` : normalizedValue;
};

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
    <PublicMetaHead :meta="props.meta" />

    <MainLayout>
        <section class="detail-shell">
            <header class="surface-panel hero-panel">
                <div class="hero-copy">
                    <p class="eyebrow">{{ heroEyebrow }}</p>
                    <h1 class="directory-page-title">{{ heroTitle }}</h1>
                    <p class="directory-intro-copy">{{ heroIntroCopy }}</p>

                    <div class="hero-pills">
                        <span class="pill request-type-pill">
                            {{ currentCopy.requestType[rfq.request_type] || rfq.request_type }}
                        </span>
                        <span v-if="isPrivateRequest" class="pill visibility-pill is-private">
                            {{ rfq.visibility_badge || currentCopy.privateRequest }}
                        </span>
                        <span class="pill priority-pill" :class="priorityTone(rfq.priority)">
                            {{ currentCopy.priority[rfq.priority] || rfq.priority || '-' }}
                        </span>
                        <span class="status-pill">
                            <span class="status-dot" :class="statusTone(rfq.status)"></span>
                            {{ currentCopy.statuses[rfq.status] || rfq.status }}
                        </span>
                        <span
                            v-if="myOfferStatus"
                            class="pill hero-offer-status-pill"
                            :class="myOfferStatus === 'submitted' ? 'is-submitted' : 'is-draft'"
                        >
                            {{ myOfferStatusLabel }}
                        </span>
                    </div>

                    <p v-if="heroVisibilityNotice" class="hero-visibility-notice">{{ heroVisibilityNotice }}</p>
                    <p v-if="heroOfferNotice" class="hero-offer-notice">{{ heroOfferNotice }}</p>
                </div>

                <div class="hero-actions">
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
                                <template v-for="item in displayedItems" :key="item.id">
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
                                            <div v-if="showSupplierWorkspace && item.my_offer" class="item-offer-band">
                                                <div class="item-offer-head">
                                                    <strong class="detail-inline-label">{{ currentCopy.yourOffer }}:</strong>
                                                </div>
                                                <div class="item-offer-grid">
                                                    <div class="item-offer-block">
                                                        <strong class="detail-inline-label">{{ currentCopy.offerQty }}:</strong>
                                                        <div class="detail-inline-text">{{ item.my_offer.offer_qty || '-' }}</div>
                                                    </div>
                                                    <div class="item-offer-block">
                                                        <strong class="detail-inline-label">{{ currentCopy.unitPrice }}:</strong>
                                                        <div class="detail-inline-text">{{ item.my_offer.unit_price || '-' }}</div>
                                                    </div>
                                                    <div class="item-offer-block">
                                                        <strong class="detail-inline-label">{{ currentCopy.total }}:</strong>
                                                        <div class="detail-inline-text">{{ item.my_offer.line_total || '-' }}</div>
                                                    </div>
                                                    <div class="item-offer-block">
                                                        <strong class="detail-inline-label">{{ currentCopy.labels.currency }}:</strong>
                                                        <div class="detail-inline-text">{{ rfq.currency || '-' }}</div>
                                                    </div>
                                                    <div class="item-offer-block">
                                                        <strong class="detail-inline-label">{{ currentCopy.leadTime }}:</strong>
                                                        <div class="detail-inline-text">{{ formatDeliveryDays(item.my_offer.delivery_time) }}</div>
                                                    </div>
                                                    <div class="item-offer-block">
                                                        <strong class="detail-inline-label">{{ currentCopy.table.quality }}:</strong>
                                                        <div class="detail-inline-text">{{ formatTitleCaseValue(item.my_offer.quality) }}</div>
                                                    </div>
                                                    <div class="item-offer-block">
                                                        <strong class="detail-inline-label">{{ currentCopy.table.manufacturer }}:</strong>
                                                        <div class="detail-inline-text">{{ item.my_offer.manufacturer || '-' }}</div>
                                                    </div>
                                                    <div class="item-offer-block">
                                                        <strong class="detail-inline-label">{{ currentCopy.remarks }}:</strong>
                                                        <div class="detail-inline-text">{{ item.my_offer.remarks || '-' }}</div>
                                                    </div>
                                                    <div class="item-offer-block item-offer-block-files">
                                                        <strong class="detail-inline-label">{{ currentCopy.files }}:</strong>
                                                        <div class="detail-inline-text">
                                                            <button
                                                                v-if="item.my_offer.attachments?.length"
                                                                type="button"
                                                                class="file-preview-button"
                                                                @click="openAttachmentViewer(item.my_offer.attachments)"
                                                            >
                                                                {{ fileButtonLabel(item.my_offer.attachments) }}
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

            <section v-if="showSupplierWorkspace && rfq.my_offer" class="surface-card section-card combined-detail-section">
                <div class="subsection-surface">
                    <div class="section-heading">
                        <h2 class="directory-section-title">{{ currentCopy.yourOffer }}</h2>
                    </div>

                    <div v-if="rfq.request_type === 'spare_parts'" class="service-offer-card spare-parts-offer-card">
                        <div class="offer-summary-rows">
                            <div class="offer-summary-row">
                                <span>{{ currentCopy.totalPrice }}</span>
                                <strong>{{ offerAmountDisplay(rfq.my_offer.total_offer_amount, rfq.my_offer.currency) }}</strong>
                            </div>
                            <div class="offer-summary-row offer-summary-row-muted">
                                <span>{{ currentCopy.tax }}</span>
                                <strong>{{ rfq.my_offer.including_tax ? currentCopy.included : offerAmountDisplay(rfq.my_offer.tax_amount, rfq.my_offer.currency) }}</strong>
                            </div>
                            <div class="offer-summary-row offer-summary-row-muted">
                                <span>{{ currentCopy.packing }}</span>
                                <strong>{{ rfq.my_offer.including_packing ? currentCopy.included : offerAmountDisplay(rfq.my_offer.packing_cost, rfq.my_offer.currency) }}</strong>
                            </div>
                            <div class="offer-summary-row offer-summary-row-muted">
                                <span>{{ currentCopy.freight }}</span>
                                <strong>{{ rfq.my_offer.including_freight ? currentCopy.included : offerAmountDisplay(rfq.my_offer.freight_cost, rfq.my_offer.currency) }}</strong>
                            </div>
                            <div class="offer-summary-row offer-summary-row-grand">
                                <span>{{ currentCopy.grandTotal }}</span>
                                <strong>{{ offerAmountDisplay(rfq.my_offer.grand_total, rfq.my_offer.currency) }}</strong>
                            </div>
                        </div>

                        <div class="service-offer-notes">
                            <div class="service-offer-note">
                                <div class="detail-inline-main detail-inline-main-wide">
                                    <strong class="detail-inline-label">{{ currentCopy.deliveryTerms }}:</strong>
                                    <div class="detail-inline-text detail-inline-text-long">{{ rfq.my_offer.delivery_terms || '-' }}</div>
                                </div>
                            </div>
                            <div class="service-offer-note">
                                <div class="detail-inline-main detail-inline-main-wide">
                                    <strong class="detail-inline-label">{{ currentCopy.otherDeliveryTerms }}:</strong>
                                    <div class="detail-inline-text detail-inline-text-long">{{ rfq.my_offer.other_delivery_terms || '-' }}</div>
                                </div>
                            </div>
                            <div class="service-offer-note">
                                <div class="detail-inline-main detail-inline-main-wide">
                                    <strong class="detail-inline-label">{{ currentCopy.paymentTerms }}:</strong>
                                    <div class="detail-inline-text detail-inline-text-long">{{ sparePartsPaymentSummary }}</div>
                                </div>
                            </div>
                            <div class="service-offer-note">
                                <div class="detail-inline-main detail-inline-main-wide">
                                    <strong class="detail-inline-label">{{ currentCopy.otherPaymentTerms }}:</strong>
                                    <div class="detail-inline-text detail-inline-text-long">{{ rfq.my_offer.other_payment_terms || '-' }}</div>
                                </div>
                            </div>
                            <div class="service-offer-note">
                                <div class="detail-inline-main detail-inline-main-wide">
                                    <strong class="detail-inline-label">{{ currentCopy.generalNote }}:</strong>
                                    <div class="detail-inline-text detail-inline-text-long">{{ rfq.my_offer.general_note || '-' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="service-offer-card">
                        <div class="offer-summary-rows">
                            <div class="offer-summary-row">
                                <span>{{ currentCopy.totalPrice }}</span>
                                <strong>{{ offerAmountDisplay(rfq.my_offer.total_offer_amount, rfq.my_offer.currency) }}</strong>
                            </div>
                            <div class="offer-summary-row offer-summary-row-muted">
                                <span>{{ currentCopy.tax }}</span>
                                <strong>{{ rfq.my_offer.including_tax ? currentCopy.included : offerAmountDisplay(rfq.my_offer.tax_amount, rfq.my_offer.currency) }}</strong>
                            </div>
                            <div class="offer-summary-row offer-summary-row-muted">
                                <span>{{ currentCopy.mobilization }}</span>
                                <strong>{{ rfq.my_offer.including_mobilization ? currentCopy.included : offerAmountDisplay(rfq.my_offer.mobilization_cost, rfq.my_offer.currency) }}</strong>
                            </div>
                            <div class="offer-summary-row offer-summary-row-grand">
                                <span>{{ currentCopy.grandTotal }}</span>
                                <strong>{{ offerAmountDisplay(rfq.my_offer.grand_total, rfq.my_offer.currency) }}</strong>
                            </div>
                        </div>

                        <div class="service-offer-notes">
                            <div class="service-offer-note">
                                <div class="detail-inline-main detail-inline-main-wide">
                                    <strong class="detail-inline-label">{{ currentCopy.completionTime }}:</strong>
                                    <div class="detail-inline-text detail-inline-text-long">{{ rfq.my_offer.completion_time || '-' }}</div>
                                </div>
                            </div>
                            <div class="service-offer-note">
                                <div class="detail-inline-main detail-inline-main-wide">
                                    <strong class="detail-inline-label">{{ currentCopy.offerValidity }}:</strong>
                                    <div class="detail-inline-text detail-inline-text-long">{{ rfq.my_offer.offer_validity || '-' }}</div>
                                </div>
                            </div>
                            <div class="service-offer-note">
                                <div class="detail-inline-main detail-inline-main-wide">
                                    <strong class="detail-inline-label">{{ currentCopy.serviceClarification }}:</strong>
                                    <div class="detail-inline-text detail-inline-text-long">{{ rfq.my_offer.service_clarification || '-' }}</div>
                                </div>
                            </div>
                            <div class="service-offer-note">
                                <div class="detail-inline-main detail-inline-main-wide">
                                    <strong class="detail-inline-label">{{ currentCopy.generalNote }}:</strong>
                                    <div class="detail-inline-text detail-inline-text-long">{{ rfq.my_offer.general_note || '-' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="surface-card section-card combined-detail-section">
                <div class="subsection-surface">
                    <div class="section-heading">
                        <h2 class="directory-section-title">{{ actionCardTitle }}</h2>
                    </div>

                    <p class="detail-inline-text detail-inline-text-long offer-notice">{{ offerNotice }}</p>

                    <div class="offer-actions">
                        <Link
                            v-if="isBuyerOwnerView && rfq.buyer_show_url"
                            :href="rfq.buyer_show_url"
                            class="offer-button"
                        >
                            {{ offerButtonLabel }}
                        </Link>

                        <Link
                            v-else-if="rfq.offer_state !== 'awarded' && !isSellerWorkspaceView && (myOfferStatus === 'draft' || myOfferStatus === 'submitted') && rfq.supplier_rfq_url"
                            :href="rfq.supplier_rfq_url"
                            class="offer-button"
                        >
                            {{ offerButtonLabel }}
                        </Link>

                        <Link
                            v-else-if="(rfq.offer_state === 'eligible' || rfq.offer_state === 'awarded' || (isSellerWorkspaceView && (myOfferStatus === 'draft' || myOfferStatus === 'submitted'))) && rfq.offer_url"
                            :href="rfq.offer_url"
                            class="offer-button"
                        >
                            {{ offerButtonLabel }}
                        </Link>

                        <Link
                            v-else-if="rfq.offer_state === 'login'"
                            :href="rfq.offer_url"
                            class="offer-button offer-button-login"
                        >
                            {{ currentCopy.offerLogin }}
                        </Link>

                        <Link
                            v-else-if="rfq.offer_state === 'closed' && !currentUser"
                            :href="registerUrl"
                            class="offer-button offer-button-login"
                        >
                            {{ currentCopy.offerRegister }}
                        </Link>

                        <button v-else type="button" class="offer-button offer-button-disabled" disabled>
                            {{ currentCopy.offerLocked }}
                        </button>
                    </div>
                </div>
            </section>

            <section v-if="similarRfqs.length && !isSellerWorkspaceView" class="main-related-section related-full-width">
                <div class="sidebar-head main-related-head">
                    <div>
                        <span class="sidebar-kicker">{{ currentCopy.similarEyebrow }}</span>
                        <h2>{{ currentCopy.similarTitle }}</h2>
                    </div>
                </div>
                <div class="related-carousel">
                    <div v-if="similarRfqs.length > similarVisibleCount" class="related-nav">
                        <button type="button" class="related-nav-button" :disabled="!canSlideSimilarPrev" @click="slideSimilarPrev" aria-label="Previous RFQs">
                            <svg class="related-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m15 18-6-6 6-6" />
                            </svg>
                        </button>
                        <button type="button" class="related-nav-button" :disabled="!canSlideSimilarNext" @click="slideSimilarNext" aria-label="Next RFQs">
                            <svg class="related-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m9 18 6-6-6-6" />
                            </svg>
                        </button>
                    </div>

                    <div class="main-related-grid">
                        <RequestCard v-for="item in visibleSimilarRfqs" :key="item.id" :item="item" />
                    </div>
                </div>
            </section>
        </section>

        <div v-if="detailModal" class="detail-modal-backdrop" @click.self="closeDetailModal">
            <div class="detail-modal">
                <div class="detail-modal-head">
                    <h3 class="detail-modal-title">{{ detailModalTitle }}</h3>
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

                <div v-else class="detail-modal-body">
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

.hero-offer-notice {
    margin: 14px 0 0;
    max-width: 78ch;
    color: #0f172a;
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.6;
}

.hero-visibility-notice {
    margin: 14px 0 0;
    max-width: 78ch;
    color: #334155;
    font-size: 0.92rem;
    font-weight: 600;
    line-height: 1.6;
}

.hero-offer-status-pill.is-draft {
    background: rgba(245, 158, 11, 0.14);
    color: #b45309;
}

.hero-offer-status-pill.is-submitted {
    background: rgba(34, 197, 94, 0.12);
    color: #15803d;
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

.visibility-pill.is-private {
    background: rgba(14, 116, 144, 0.1);
    color: #0e7490;
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

.status-dot.is-closed {
    background: #ef4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.16);
}

.hero-actions {
    display: flex;
    align-items: flex-start;
}

.back-button {
    text-decoration: none;
    background: #2563eb;
    color: #fff;
    box-shadow: 0 12px 24px rgba(37, 99, 235, 0.18);
}

.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 18px;
}

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
.service-title-text {
    margin: 0;
    color: rgba(4, 21, 31, 0.82);
    font-size: 15px;
    font-weight: 400;
    line-height: 1.6;
}

.service-title-text {
    font-weight: 400;
}

.detail-inline-summary {
    display: inline-flex;
    align-items: baseline;
    gap: 10px;
    flex-wrap: wrap;
}

.summary-view-button {
    border: 0;
    background: transparent;
    color: #2563eb;
    font-size: 14px;
    font-weight: 700;
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

.detail-table .col-product {
    width: 145px;
}

.detail-table .col-part,
.detail-table .col-manufacturer,
.detail-table .col-model,
.detail-table .col-catalog,
.detail-table .col-serial,
.detail-table .col-drawing,
.detail-table .col-quality {
    width: 100px;
}

.detail-table .col-qty {
    width: 90px;
}

.detail-table .col-rob {
    width: 70px;
}

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

.item-offer-band {
    margin: 0 0 16px 54px;
    padding: 12px 14px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 12px;
    background: #f8fafb;
    display: grid;
    gap: 12px;
}

.item-offer-head {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 10px;
}

.item-offer-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px 14px;
}

.item-offer-block {
    display: grid;
    gap: 4px;
    min-width: 0;
}

.item-offer-block-files {
    grid-column: span 2;
}

.item-offer-block-wide {
    grid-column: 1 / -1;
}

.item-offer-band .detail-inline-label {
    font-size: 12px;
    line-height: 1.25;
}

.item-offer-band .detail-inline-text,
.item-offer-band .file-preview-button {
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

.service-offer-card {
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    gap: 16px;
}

.service-offer-head {
    display: flex;
    align-items: center;
    gap: 8px;
}

.spare-parts-offer-card {
    margin-top: 16px;
}

.service-offer-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px 14px;
}

.offer-summary-rows {
    display: grid;
    gap: 10px;
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
}

.offer-summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    color: #0f172a;
    font-size: 0.94rem;
}

.offer-summary-row strong {
    font-weight: 700;
    text-align: right;
}

.offer-summary-row-muted {
    color: #475569;
}

.offer-summary-row-grand {
    padding-top: 10px;
    border-top: 1px solid rgba(148, 163, 184, 0.18);
    font-size: 1rem;
}

.service-offer-metric,
.service-offer-note {
    display: block;
    min-width: 0;
}

.service-offer-grid .detail-inline-label,
.service-offer-notes .detail-inline-label {
    font-size: 14px;
    line-height: 1.2;
}

.service-offer-grid .detail-inline-text,
.service-offer-notes .detail-inline-text {
    font-size: 15px;
    line-height: 1.2;
}

.service-offer-notes .detail-inline-main {
    grid-template-columns: 170px minmax(0, 1fr);
    column-gap: 14px;
}

.service-offer-notes .detail-inline-label {
    white-space: normal;
}

.service-offer-notes {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    margin-top: 24px;
}

.offer-notice {
    max-width: 78ch;
    color: rgba(4, 21, 31, 0.82);
    font-size: 15px;
    font-weight: 400;
    line-height: 1.6;
}

.offer-actions {
    margin-top: 18px;
}

.offer-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 18px;
    border-radius: 10px;
    background: #0e7490;
    color: #fff;
    font-size: 0.92rem;
    font-weight: 700;
    text-decoration: none;
    border: 0;
}

.offer-button-login {
    background: #2563eb;
}

.offer-button-disabled {
    background: rgba(148, 163, 184, 0.18);
    color: #64748b;
    cursor: not-allowed;
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
    font-size: 26px;
    line-height: 1;
    cursor: pointer;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
}

.gallery-nav-button.is-left {
    left: 20px;
}

.gallery-nav-button.is-right {
    right: 20px;
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

.main-related-section {
    margin-top: 16px;
}

.sidebar-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.sidebar-kicker {
    display: inline-flex;
    margin-bottom: 8px;
    color: rgba(4, 21, 31, 0.64);
    font-size: 0.96rem;
    font-weight: 650;
}

.sidebar-head h2 {
    margin: 0;
    color: #020617;
    font-size: 1rem;
    font-weight: 650;
    letter-spacing: -0.02em;
}

.main-related-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.request-card {
    display: grid;
    gap: 14px;
    min-height: 100%;
    padding: 16px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 18px 34px rgba(15, 23, 42, 0.08);
    text-decoration: none;
    transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}

.request-card:hover {
    transform: translateY(-3px) scale(1.01);
    border-color: rgba(14, 116, 144, 0.18);
    box-shadow: 0 28px 44px rgba(15, 23, 42, 0.12);
}

.request-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.request-type-chip,
.request-status-chip {
    display: inline-flex;
    align-items: center;
    min-height: 32px;
    padding: 0 12px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
}

.request-type-chip {
    background: rgba(14, 116, 144, 0.08);
    color: #0e7490;
}

.request-status-chip {
    gap: 8px;
    background: rgba(255, 241, 242, 0.9);
    color: #dc2626;
}

.request-status-chip.is-close {
    background: rgba(248, 250, 252, 0.95);
    color: #64748b;
}

.request-card-body {
    display: grid;
    gap: 14px;
    flex: 1;
}

.request-title {
    color: #1e293b;
    font-size: 1.02rem;
    line-height: 1.35;
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.request-description {
    margin: 0;
    color: #475569;
    font-size: 0.96rem;
    line-height: 1.7;
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 4;
    min-height: 108px;
}

.request-divider {
    height: 1px;
    background: rgba(4, 21, 31, 0.08);
}

.request-footer {
    display: grid;
    gap: 10px;
}

.request-countries,
.footer-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    color: #475569;
    font-size: 0.92rem;
    font-weight: 600;
}

.request-countries svg,
.footer-item svg {
    width: 15px;
    height: 15px;
    flex: 0 0 15px;
    color: #64748b;
}

.request-countries span,
.footer-item span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.request-footer-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.footer-time {
    font-size: 0.82rem;
    color: #64748b;
    font-weight: 500;
}

.related-carousel {
    position: relative;
    margin-top: 18px;
}

.related-nav {
    position: absolute;
    top: 214px;
    left: 10px;
    right: 10px;
    z-index: 3;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    pointer-events: none;
}

.related-nav-button {
    pointer-events: auto;
    width: 46px;
    height: 46px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(15, 23, 42, 0.14);
    border-radius: 10px;
    background: rgba(15, 23, 42, 0.92);
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.28);
    backdrop-filter: blur(12px);
    color: #ffffff;
    font-size: 0;
    font-weight: 600;
    line-height: 1;
    cursor: pointer;
    transition: transform 160ms ease, background-color 160ms ease, color 160ms ease, border-color 160ms ease, opacity 160ms ease, box-shadow 160ms ease;
}

.related-nav-icon {
    width: 20px;
    height: 20px;
    flex: 0 0 20px;
}

.related-nav-button:hover:not(:disabled) {
    transform: translateY(-1px);
    background: #020617;
    color: #ffffff;
    border-color: #020617;
    box-shadow: 0 22px 42px rgba(2, 6, 23, 0.36);
}

.related-nav-button:disabled {
    opacity: 0.34;
    cursor: not-allowed;
}

@media (max-width: 1180px) {
    .info-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .main-related-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 860px) {
    .hero-panel {
        flex-direction: column;
    }

    .info-grid {
        grid-template-columns: 1fr;
    }

    .service-offer-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .service-offer-notes {
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

    .hero-actions .back-button {
        width: 100%;
    }

    .item-offer-grid {
        grid-template-columns: 1fr;
    }

    .service-offer-grid {
        grid-template-columns: 1fr;
    }

    .item-offer-block-files {
        grid-column: span 1;
    }

    .main-related-grid {
        grid-template-columns: 1fr;
    }

    .related-nav {
        top: 50%;
        left: 0;
        right: 0;
        transform: translateY(-50%);
    }
}

@media (max-width: 960px) {
    .item-offer-band {
        margin-left: 0;
        padding: 16px;
    }

    .item-offer-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .item-offer-block-files {
        grid-column: span 2;
    }

    .main-related-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .related-nav {
        top: 50%;
        left: 0;
        right: 0;
        transform: translateY(-50%);
    }
}
</style>
