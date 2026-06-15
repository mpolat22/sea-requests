<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainLayout from '../../../Layouts/MainLayout.vue';
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
    eyebrow: 'Offer Comparison',
    title: 'RFQ Detail',
    heroSparePartsTitle: 'Compare Spare Parts Offers',
    heroServiceTitle: 'Compare Service Offers',
    heroSparePartsText: 'Review submitted supplier lines, compare pricing and delivery, and save or confirm your award selections.',
    heroServiceText: 'Review submitted supplier offers, compare commercial terms, and save or confirm your supplier selections.',
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
    sentTo: 'Sent to',
    countriesScope: 'Countries',
    portsScope: 'Ports',
    categoriesScope: 'Categories',
    subcategoriesScope: 'Subcategory',
    categoriesSelected: 'categories selected',
    subcategoriesSelected: 'selected',
    selectedPorts: 'Selected Ports',
    selectedCountries: 'Selected Countries',
    selectedCategories: 'Selected Categories',
    selectedSubcategories: 'Selected Subcategories',
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
    recipientsCount: 'suppliers',
    requestedItem: 'Requested Item',
    requestedService: 'Requested Service',
    selectionSummary: 'Selection Summary',
    offerTerms: 'Offer Terms',
    categoriesCount: 'Categories',
    portsCount: 'Ports',
    offersComparison: 'Offers Received',
    splitAwardNotice: 'You can compare supplier offers here and split the requested quantity across multiple suppliers when needed.',
    smartAssist: 'Smart Assist',
    recommendBestMatches: 'Recommend Best Matches',
    showFullQuantitySuppliers: 'Show Full Quantity',
    highlightLowestPrice: 'Highlight Lowest Price',
    highlightFastestDelivery: 'Highlight Fastest Delivery',
    suggestBestSplit: 'Suggest Best Split',
    resetView: 'Reset View',
    saveAwardDraft: 'Save Award Draft',
    confirmAwards: 'Confirm Awards',
    noOffersYet: 'No submitted supplier offers yet.',
    supplier: 'Supplier',
    requestedQty: 'Requested Qty',
    selectedQtySummary: 'Selected Qty',
    aboveRequest: 'Above Request',
    selectedTotal: 'Selected Total',
    overallSelectedTotal: 'Overall Selected Total',
    offeredQty: 'Offered Qty',
    awardedQty: 'Selected Qty',
    selectionNote: 'Selection Note',
    selectionNotePlaceholder: 'Optional note for this selected product...',
    awarded: 'Awarded',
    remaining: 'Remaining',
    noServiceFiles: 'No offer files',
    noItemFiles: 'No files',
    draftAward: 'Draft Award',
    confirmedAward: 'Confirmed',
    itemStatusOpen: 'Open for award',
    itemStatusPartial: 'Partially awarded',
    itemStatusFull: 'Fully awarded',
    serviceSelection: 'Supplier Selection',
    serviceSelect: 'Select supplier',
    summaryFullyAwarded: 'Fully Awarded',
    summaryPartiallyAwarded: 'Partially Awarded',
    summaryUnawarded: 'Unawarded',
    summaryDraft: 'Draft Saved',
    quotedItems: 'Quoted Items',
    totalOfferAmount: 'Total Offer Amount',
    unitPrice: 'Unit Price',
    total: 'Total',
    remarks: 'Remarks',
    completionTime: 'Completion Time',
    offerValidity: 'Offer Validity',
    serviceClarification: 'Service Clarification',
    generalNote: 'General Note',
    awardScope: 'Award Scope',
    partialAwardAccepted: 'Partial award accepted',
    fullQuotedScopeRequired: 'Full quoted scope required',
    paymentTerms: 'Payment Terms',
    deliveryTerms: 'Delivery Terms',
    otherDeliveryTerms: 'Other Delivery Terms',
    otherPaymentTerms: 'Other Payment Terms',
    deliveryTime: 'Delivery Time',
    tax: 'Including Tax',
    packing: 'Including Packing',
    freight: 'Including Freight',
    mobilization: 'Including Mobilization',
    included: 'Included',
    grandTotal: 'Grand Total',
    totalPrice: 'Total Price',
    selectedQtyExceedsOffer: 'Selected quantity cannot exceed the supplier offer quantity.',
    reviewAwardSelections: 'Review Award Selections',
    reviewAwardSelectionsText: 'Review the selected suppliers and quantities before confirming this award.',
    goBack: 'Go Back',
    selectedSuppliers: 'Selected Suppliers',
    selectedLines: 'Selected Lines',
    selectedOffersLabel: 'Selected Offers',
    quoteCoverage: 'Quotation',
    pricedItems: 'Priced Items',
    totalAmountLabel: 'Total Amount',
    itemsAboveRequest: 'Items Above Request',
    itemsWithoutSelection: 'Items Without Selection',
    noSelectionsYet: 'No supplier selections have been made yet.',
    lineLabel: 'Line',
    selectedQtyLabel: 'Selected Qty',
    requestedQtyLabel: 'Requested Qty',
    itemStatusLabel: 'Status',
    itemStatusFullMatch: 'Full Match',
    itemStatusPartialMatch: 'Partial',
    itemStatusAboveRequest: 'Above Request',
    assistBadgeRecommended: 'Recommended',
    assistBadgeFullCoverage: 'Full Quantity',
    assistBadgeLowestPrice: 'Best Price',
    assistBadgeFastest: 'Fastest',
    assistBadgeSuggestedSplit: 'Suggested Split',
    assistReasonRecommendedStrong: 'Full quantity with strong overall value.',
    assistReasonRecommendedBalanced: 'Best overall balance of price, coverage, and delivery.',
    assistReasonFullCoverage: 'Covers the full requested quantity.',
    assistReasonLowestPrice: 'Lowest unit price for this item.',
    assistReasonFastest: 'Fastest delivery for this item.',
    assistReasonSuggestedSplit: 'Included in the suggested best split for this item.',
    splitSuggestionTitle: 'Suggested Split',
    splitSuggestionText: 'Review the strongest quantity mix before filling your selections manually or applying it directly.',
    bestOverallSplit: 'Best Overall Split',
    bestPriceSplit: 'Best Price Split',
    fastestFullSupply: 'Fastest Full Supply',
    fastestAvailableSupply: 'Fastest Available Supply',
    coverage: 'Coverage',
    estimatedTotal: 'Estimated Total',
    maxDelivery: 'Max Delivery',
    splitReasonBestOverall: 'Balanced across price, coverage, and delivery.',
    splitReasonBestPrice: 'Lowest-cost way to cover this requested item.',
    splitReasonFastest: 'Fastest path to cover this requested item.',
    applySuggestion: 'Apply Suggestion',
    appliedSuggestion: 'Applied',
};

const isSpareParts = computed(() => props.rfq.request_type === 'spare_parts');
const currentCopy = computed(() => copy);
const heroTitle = computed(() => {
    const serviceTitle = `${props.rfq.service_title ?? ''}`.trim();

    if (!isSpareParts.value && serviceTitle) {
        return `${currentCopy.value.heroServiceTitle} for ${serviceTitle}`;
    }

    return isSpareParts.value
        ? currentCopy.value.heroSparePartsTitle
        : currentCopy.value.heroServiceTitle;
});

const heroIntroText = computed(() => (
    isSpareParts.value
        ? currentCopy.value.heroSparePartsText
        : currentCopy.value.heroServiceText
));

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

const statusTone = (status) => {
    if (status === 'open') return 'is-open';
    if (status === 'award_confirmed') return 'is-awarded';
    if (status === 'completed') return 'is-completed';
    if (status === 'closed' || status === 'cancelled') return 'is-closed';
    return 'is-draft';
};

const parseDecimal = (value) => {
    const number = Number.parseFloat(`${value ?? ''}`.replace(',', '.'));
    return Number.isFinite(number) ? number : 0;
};

const normalizeDecimalInput = (value) => {
    const raw = `${value ?? ''}`.trim().replace(',', '.');

    if (raw === '') {
        return '';
    }

    const numeric = Number.parseFloat(raw);

    if (!Number.isFinite(numeric)) {
        return raw;
    }

    return Number.isInteger(numeric) ? `${numeric}` : `${numeric}`;
};

const priorityTone = (priority) => {
    if (priority === 'critical') return 'is-critical';
    if (priority === 'high') return 'is-high';
    if (priority === 'low') return 'is-low';
    return 'is-normal';
};

const awardForm = useForm({
    intent: 'draft',
    spare_item_awards: {},
    spare_item_notes: {},
    service_offer_awards: [],
    service_offer_notes: {},
});

const offers = computed(() => props.rfq.offers ?? []);
const offerItemsByOfferId = computed(() => Object.fromEntries(
    offers.value.map((offer) => [
        offer.id,
        Object.fromEntries(
            (offer.items ?? []).map((item) => [item.rfq_item_id, item]),
        ),
    ]),
));
const quotedItemCountByOfferId = computed(() => Object.fromEntries(
    offers.value.map((offer) => [
        offer.id,
        (offer.items ?? []).filter((item) => (
            parseDecimal(item.offer_qty) > 0
            || parseDecimal(item.unit_price) > 0
            || parseDecimal(item.line_total) > 0
        )).length,
    ]),
));
const awardSummary = computed(() => props.rfq.award_summary ?? {});
const confirmPreviewOpen = ref(false);

const initializeAwardForm = () => {
    const spareAwards = {};
    const spareAwardNotes = {};
    const serviceAwards = [];
    const serviceAwardNotes = {};

    offers.value.forEach((offer) => {
        if (props.rfq.request_type === 'service_request' && offer.current_award?.awarded_quantity !== '0') {
            if (offer.current_award) {
                serviceAwards.push(offer.id);
            }
        }

        serviceAwardNotes[offer.id] = offer.current_award?.buyer_note ?? '';

        (offer.items ?? []).forEach((item) => {
            spareAwards[item.id] = item.current_award?.awarded_quantity ?? '';
            spareAwardNotes[item.id] = item.current_award?.buyer_note ?? '';
        });
    });

    awardForm.spare_item_awards = spareAwards;
    awardForm.spare_item_notes = spareAwardNotes;
    awardForm.service_offer_awards = serviceAwards;
    awardForm.service_offer_notes = serviceAwardNotes;
};

initializeAwardForm();

const spareComparisonItems = computed(() => (props.rfq.items ?? []).map((item) => {
    const supplierOffers = [];
    const supplierOffersByOfferId = {};

    offers.value.forEach((offer) => {
        const offeredItem = offerItemsByOfferId.value[offer.id]?.[item.id];

        if (!offeredItem) {
            return;
        }

        const entry = {
            id: offer.id,
            currency: offer.currency,
            seller: offer.seller,
            offered_item: offeredItem,
        };

        supplierOffers.push(entry);
        supplierOffersByOfferId[offer.id] = entry;
    });

    return {
        ...item,
        supplier_offers: supplierOffers,
        supplier_offers_by_offer_id: supplierOffersByOfferId,
    };
}));
const spareComparisonItemsById = computed(() => Object.fromEntries(
    spareComparisonItems.value.map((item) => [item.id, item]),
));

const spareComparisonSuppliers = computed(() => offers.value);
const hasSpareComparisonOffers = computed(() => isSpareParts.value && spareComparisonSuppliers.value.length > 0);
const activeAssistMode = ref('none');
const dismissedSplitSuggestionItemIds = ref([]);
const totalRequestedItemsCount = computed(() => spareComparisonItems.value.length);
const supplierOfferForItem = (item, offerId) => item?.supplier_offers_by_offer_id?.[offerId] ?? null;

const setAssistMode = (mode) => {
    activeAssistMode.value = mode;

    if (mode === 'split') {
        dismissedSplitSuggestionItemIds.value = [];
    }
};

const selectedQtyError = (item, offerId) => {
    const supplierOffer = supplierOfferForItem(item, offerId);
    const offerItem = supplierOffer?.offered_item;

    if (!offerItem?.id) {
        return '';
    }

    const selectedValue = `${awardForm.spare_item_awards[offerItem.id] ?? ''}`.trim();

    if (selectedValue === '') {
        return '';
    }

    const selectedQty = parseDecimal(selectedValue);
    const offeredQty = parseDecimal(offerItem.offer_qty);

    if (selectedQty > offeredQty) {
        return currentCopy.value.selectedQtyExceedsOffer;
    }

    return '';
};

const selectionNoteValue = (offerItemId) => `${awardForm.spare_item_notes[offerItemId] ?? ''}`.trim();
const serviceSelectionNoteValue = (offerId) => `${awardForm.service_offer_notes[offerId] ?? ''}`.trim();

const shouldShowSelectionNoteField = (offerItemId) => {
    if (!offerItemId) {
        return false;
    }

    return parseDecimal(awardForm.spare_item_awards[offerItemId]) > 0 || selectionNoteValue(offerItemId) !== '';
};

const shouldShowServiceSelectionNoteField = (offerId) => {
    return awardForm.service_offer_awards.includes(offerId) || serviceSelectionNoteValue(offerId) !== '';
};

const hasSelectedQtyClientError = computed(() => spareComparisonItems.value.some((item) => (
    item.supplier_offers?.some((offer) => selectedQtyError(item, offer.id)) ?? false
)));

const textOrDash = (value) => {
    const text = `${value ?? ''}`.trim();
    return text !== '' ? text : '-';
};

const paymentTermsSummary = (offer) => {
    const parts = [];
    const orderConfirmation = parseDecimal(offer?.payment_order_confirmation);
    const beforeShipment = parseDecimal(offer?.payment_before_shipment);
    const invoiceDays = `${offer?.payment_invoice_days ?? ''}`.trim();
    const otherPaymentTerms = `${offer?.other_payment_terms ?? ''}`.trim();

    if (orderConfirmation > 0) {
        parts.push(`${orderConfirmation}% order`);
    }

    if (beforeShipment > 0) {
        parts.push(`${beforeShipment}% shipment`);
    }

    if (invoiceDays !== '') {
        parts.push(`${invoiceDays} days invoice`);
    }

    if (otherPaymentTerms !== '') {
        parts.push(`${currentCopy.value.otherPaymentTerms}: ${otherPaymentTerms}`);
    }

    return parts.length ? parts.join(' / ') : '-';
};

const formatDeliveryDays = (value) => {
    const days = parseDeliveryDays(value);
    return days !== null ? `${days} days` : textOrDash(value);
};

const awardScopeSummary = (offer) => (
    offer?.award_scope_policy === 'full_scope_required'
        ? currentCopy.value.fullQuotedScopeRequired
        : currentCopy.value.partialAwardAccepted
);

const currencyAmountOrDash = (currency, value) => {
    const normalized = `${value ?? ''}`.trim();
    return normalized !== '' ? `${currency} ${normalized}` : '-';
};

const includedAmountSummary = (isIncluded, currency, value) => (
    isIncluded ? currentCopy.value.included : currencyAmountOrDash(currency, value)
);

const parseDeliveryDays = (value) => {
    const match = `${value ?? ''}`.match(/(\d+(?:[.,]\d+)?)/);
    return match ? parseDecimal(match[1].replace(',', '.')) : null;
};

const numericSortValue = (value, { allowZero = false } = {}) => {
    if (!Number.isFinite(value)) {
        return Number.POSITIVE_INFINITY;
    }

    if (!allowZero && value <= 0) {
        return Number.POSITIVE_INFINITY;
    }

    return value;
};

const compactNumericString = (value) => {
    const numeric = parseDecimal(value);

    if (!Number.isFinite(numeric)) {
        return '0';
    }

    return Number.isInteger(numeric) ? `${numeric}` : `${numeric}`;
};

const compareByFields = (a, b, fields) => {
    for (const [field, direction] of fields) {
        const aValue = field(a);
        const bValue = field(b);

        if (aValue === bValue) {
            continue;
        }

        return direction === 'desc' ? (bValue - aValue) : (aValue - bValue);
    }

    return 0;
};

const buildSplitPlan = (offers, requestedQty, sortFields) => {
    const targetQty = Math.max(requestedQty, 0);
    const rankedOffers = [...offers]
        .filter((offer) => offer.offerItemId && offer.offeredQty > 0)
        .sort((a, b) => compareByFields(a, b, sortFields));

    if (!rankedOffers.length) {
        return null;
    }

    let remainingQty = targetQty;
    let coveredQty = 0;
    let totalCost = 0;
    let maxDeliveryDays = null;

    const lines = [];

    rankedOffers.forEach((offer) => {
        if (targetQty > 0 && remainingQty <= 0) {
            return;
        }

        const takeQty = targetQty > 0
            ? Math.min(offer.offeredQty, remainingQty)
            : offer.offeredQty;

        if (takeQty <= 0) {
            return;
        }

        lines.push({
            offerId: offer.offerId,
            offerItemId: offer.offerItemId,
            supplierName: offer.supplierName,
            selectedQty: takeQty,
            offeredQty: offer.offeredQty,
            unitPrice: offer.unitPrice,
            deliveryDays: offer.deliveryDays,
            lineTotal: offer.unitPrice > 0 ? offer.unitPrice * takeQty : 0,
        });

        coveredQty += takeQty;
        totalCost += offer.unitPrice > 0 ? offer.unitPrice * takeQty : 0;
        remainingQty -= takeQty;

        if (offer.deliveryDays !== null) {
            maxDeliveryDays = maxDeliveryDays === null
                ? offer.deliveryDays
                : Math.max(maxDeliveryDays, offer.deliveryDays);
        }
    });

    if (!lines.length) {
        return null;
    }

    return {
        lines,
        requestedQty: targetQty,
        coveredQty,
        missingQty: Math.max(targetQty - coveredQty, 0),
        isFullCoverage: targetQty > 0 ? coveredQty >= targetQty : coveredQty > 0,
        totalCost,
        maxDeliveryDays,
        supplierIds: lines.map((line) => line.offerId),
        signature: lines.map((line) => `${line.offerId}:${compactNumericString(line.selectedQty)}`).join('|'),
    };
};

const selectBestOverallSplitPlan = (plans) => {
    const validPlans = plans.filter(Boolean);

    if (!validPlans.length) {
        return null;
    }

    const fullCoveragePlans = validPlans.filter((plan) => plan.isFullCoverage);

    if (fullCoveragePlans.length) {
        return [...fullCoveragePlans].sort((a, b) => {
            if (a.lines.length !== b.lines.length) {
                return a.lines.length - b.lines.length;
            }

            if (a.totalCost !== b.totalCost) {
                return a.totalCost - b.totalCost;
            }

            return numericSortValue(a.maxDeliveryDays, { allowZero: true }) - numericSortValue(b.maxDeliveryDays, { allowZero: true });
        })[0];
    }

    return [...validPlans].sort((a, b) => {
        if (a.coveredQty !== b.coveredQty) {
            return b.coveredQty - a.coveredQty;
        }

        if (a.totalCost !== b.totalCost) {
            return a.totalCost - b.totalCost;
        }

        return numericSortValue(a.maxDeliveryDays, { allowZero: true }) - numericSortValue(b.maxDeliveryDays, { allowZero: true });
    })[0];
};

const spareAssistInsights = computed(() => {
    return Object.fromEntries(spareComparisonItems.value.map((item) => {
        const supplierOffers = item.supplier_offers ?? [];
        const requestedQty = parseDecimal(item.quantity);
        const unitPrices = supplierOffers
            .map((offer) => parseDecimal(offer.offered_item?.unit_price))
            .filter((value) => value > 0);
        const deliveryDays = supplierOffers
            .map((offer) => parseDeliveryDays(offer.offered_item?.delivery_time))
            .filter((value) => value !== null);

        const minPrice = unitPrices.length ? Math.min(...unitPrices) : null;
        const maxPrice = unitPrices.length ? Math.max(...unitPrices) : null;
        const minDelivery = deliveryDays.length ? Math.min(...deliveryDays) : null;
        const maxDelivery = deliveryDays.length ? Math.max(...deliveryDays) : null;

        const scores = {};
        let bestScore = -1;
        const scoredOffers = [];

        supplierOffers.forEach((offer) => {
            const offerItem = offer.offered_item;
            const offerQty = parseDecimal(offerItem?.offer_qty);
            const unitPrice = parseDecimal(offerItem?.unit_price);
            const delivery = parseDeliveryDays(offerItem?.delivery_time);
            const completenessFields = [
                textOrDash(offerItem?.quality) !== '-',
                textOrDash(offerItem?.manufacturer) !== '-',
                textOrDash(offerItem?.remarks) !== '-',
                (offerItem?.attachments?.length ?? 0) > 0,
            ];

            const coverageScore = requestedQty > 0
                ? Math.min(offerQty / requestedQty, 1)
                : (offerQty > 0 ? 1 : 0);
            const priceScore = minPrice !== null && maxPrice !== null && unitPrice > 0
                ? (minPrice === maxPrice ? 1 : 1 - ((unitPrice - minPrice) / (maxPrice - minPrice)))
                : 0;
            const deliveryScore = minDelivery !== null && maxDelivery !== null && delivery !== null
                ? (minDelivery === maxDelivery ? 1 : 1 - ((delivery - minDelivery) / (maxDelivery - minDelivery)))
                : 0;
            const completenessScore = completenessFields.filter(Boolean).length / completenessFields.length;
            const score = (coverageScore * 0.45) + (priceScore * 0.3) + (deliveryScore * 0.15) + (completenessScore * 0.1);

            scores[offer.id] = score;
            bestScore = Math.max(bestScore, score);
            scoredOffers.push({
                offerId: offer.id,
                offerItemId: offerItem?.id ?? null,
                supplierName: offer.seller?.company_name ?? '-',
                offeredQty: offerQty,
                unitPrice,
                deliveryDays: delivery,
                score,
            });
        });

        const bestPriceSplitPlan = buildSplitPlan(scoredOffers, requestedQty, [
            [offer => numericSortValue(offer.unitPrice), 'asc'],
            [offer => numericSortValue(offer.deliveryDays, { allowZero: true }), 'asc'],
            [offer => offer.score, 'desc'],
            [offer => offer.offeredQty, 'desc'],
        ]);

        const fastestSplitPlan = buildSplitPlan(scoredOffers, requestedQty, [
            [offer => numericSortValue(offer.deliveryDays, { allowZero: true }), 'asc'],
            [offer => numericSortValue(offer.unitPrice), 'asc'],
            [offer => offer.score, 'desc'],
            [offer => offer.offeredQty, 'desc'],
        ]);

        const balancedSplitPlan = buildSplitPlan(scoredOffers, requestedQty, [
            [offer => (requestedQty > 0 && offer.offeredQty >= requestedQty ? 1 : 0), 'desc'],
            [offer => offer.score, 'desc'],
            [offer => numericSortValue(offer.unitPrice), 'asc'],
            [offer => numericSortValue(offer.deliveryDays, { allowZero: true }), 'asc'],
            [offer => offer.offeredQty, 'desc'],
        ]);

        const bestOverallSplitPlan = selectBestOverallSplitPlan([
            balancedSplitPlan,
            bestPriceSplitPlan,
            fastestSplitPlan,
        ]);

        return [item.id, {
            fullCoverageOfferIds: supplierOffers
                .filter((offer) => requestedQty > 0 && parseDecimal(offer.offered_item?.offer_qty) >= requestedQty)
                .map((offer) => offer.id),
            lowestPriceOfferIds: supplierOffers
                .filter((offer) => minPrice !== null && parseDecimal(offer.offered_item?.unit_price) === minPrice)
                .map((offer) => offer.id),
            fastestOfferIds: supplierOffers
                .filter((offer) => minDelivery !== null && parseDeliveryDays(offer.offered_item?.delivery_time) === minDelivery)
                .map((offer) => offer.id),
            recommendedOfferIds: supplierOffers
                .filter((offer) => bestScore >= 0 && Math.abs((scores[offer.id] ?? 0) - bestScore) < 0.0001)
                .map((offer) => offer.id),
            suggestedSplitOfferIds: bestOverallSplitPlan?.supplierIds ?? [],
            splitSuggestions: {
                recommended: bestOverallSplitPlan,
                price: bestPriceSplitPlan,
                fastest: fastestSplitPlan,
            },
            scores,
        }];
    }));
});

const isAssistHighlighted = (item, offerId) => {
    const insight = spareAssistInsights.value[item.id];

    if (!insight || activeAssistMode.value === 'none') {
        return false;
    }

    if (activeAssistMode.value === 'full') {
        return insight.fullCoverageOfferIds.includes(offerId);
    }

    if (activeAssistMode.value === 'price') {
        return insight.lowestPriceOfferIds.includes(offerId);
    }

    if (activeAssistMode.value === 'fastest') {
        return insight.fastestOfferIds.includes(offerId);
    }

    if (activeAssistMode.value === 'recommended') {
        return insight.recommendedOfferIds.includes(offerId);
    }

    if (activeAssistMode.value === 'split') {
        return insight.suggestedSplitOfferIds.includes(offerId);
    }

    return false;
};

const isAssistMuted = (item, offerId) => activeAssistMode.value !== 'none' && !isAssistHighlighted(item, offerId);

const assistBadgeLabel = (item, offerId) => {
    if (!isAssistHighlighted(item, offerId)) {
        return '';
    }

    if (activeAssistMode.value === 'full') {
        return currentCopy.value.assistBadgeFullCoverage;
    }

    if (activeAssistMode.value === 'price') {
        return currentCopy.value.assistBadgeLowestPrice;
    }

    if (activeAssistMode.value === 'fastest') {
        return currentCopy.value.assistBadgeFastest;
    }

    if (activeAssistMode.value === 'recommended') {
        return currentCopy.value.assistBadgeRecommended;
    }

    if (activeAssistMode.value === 'split') {
        return currentCopy.value.assistBadgeSuggestedSplit;
    }

    return '';
};

const assistReasonText = (item, offerId) => {
    if (!isAssistHighlighted(item, offerId)) {
        return '';
    }

    if (activeAssistMode.value === 'full') {
        return currentCopy.value.assistReasonFullCoverage;
    }

    if (activeAssistMode.value === 'price') {
        return currentCopy.value.assistReasonLowestPrice;
    }

    if (activeAssistMode.value === 'fastest') {
        return currentCopy.value.assistReasonFastest;
    }

    if (activeAssistMode.value === 'recommended') {
        const insight = spareAssistInsights.value[item.id];
        const supplierOffer = supplierOfferForItem(item, offerId);
        const isFullCoverage = insight?.fullCoverageOfferIds.includes(offerId);
        const isBestPrice = insight?.lowestPriceOfferIds.includes(offerId);

        if (isFullCoverage && isBestPrice) {
            return currentCopy.value.assistReasonRecommendedStrong;
        }

        return currentCopy.value.assistReasonRecommendedBalanced;
    }

    if (activeAssistMode.value === 'split') {
        return currentCopy.value.assistReasonSuggestedSplit;
    }

    return '';
};

const formatDeliveryDaysValue = (value) => {
    if (value === null || !Number.isFinite(value)) {
        return '-';
    }

    const normalized = Number.isInteger(value) ? `${value}` : `${value}`;
    return `${normalized} ${value === 1 ? 'day' : 'days'}`;
};

const splitSuggestionEntries = (item) => {
    const insight = spareAssistInsights.value[item.id];

    if (!insight?.splitSuggestions) {
        return [];
    }

    const requestedQty = requestedQtyForItem(item.id);
    const suggestionDefinitions = [
        {
            key: 'recommended',
            label: currentCopy.value.bestOverallSplit,
            description: currentCopy.value.splitReasonBestOverall,
        },
        {
            key: 'price',
            label: currentCopy.value.bestPriceSplit,
            description: currentCopy.value.splitReasonBestPrice,
        },
        {
            key: 'fastest',
            label: insight.splitSuggestions.fastest?.isFullCoverage
                ? currentCopy.value.fastestFullSupply
                : currentCopy.value.fastestAvailableSupply,
            description: currentCopy.value.splitReasonFastest,
        },
    ];

    const seenSignatures = new Set();

    return suggestionDefinitions
        .map((definition) => {
            const plan = insight.splitSuggestions[definition.key];

            if (!plan?.lines?.length) {
                return null;
            }

            if (seenSignatures.has(plan.signature)) {
                return null;
            }

            seenSignatures.add(plan.signature);

            return {
                ...definition,
                plan,
                routeText: plan.lines
                    .map((line) => `${line.supplierName} (${formatQuantityValue(line.selectedQty, item.unit)})`)
                    .join(' + '),
                coverageText: `${formatQuantityValue(plan.coveredQty, item.unit)} / ${formatQuantityValue(requestedQty, item.unit)}`,
                totalText: formatCurrencyValue(
                    props.rfq.currency || spareComparisonSuppliers.value[0]?.currency || '',
                    formatMoneyValue(plan.totalCost),
                ),
                deliveryText: formatDeliveryDaysValue(plan.maxDeliveryDays),
            };
        })
        .filter(Boolean);
};

const showSplitSuggestionForItem = (itemId) => (
    activeAssistMode.value === 'split'
    && !dismissedSplitSuggestionItemIds.value.includes(itemId)
);

const closeSplitSuggestionForItem = (itemId) => {
    if (!dismissedSplitSuggestionItemIds.value.includes(itemId)) {
        dismissedSplitSuggestionItemIds.value = [...dismissedSplitSuggestionItemIds.value, itemId];
    }
};

const applySplitSuggestion = (itemId, suggestionKey) => {
    const item = spareComparisonItemsById.value[itemId];
    const insight = spareAssistInsights.value[itemId];
    const plan = insight?.splitSuggestions?.[suggestionKey];

    if (!item?.supplier_offers?.length || !plan?.lines?.length) {
        return;
    }

    item.supplier_offers.forEach((offer) => {
        const offerItemId = offer.offered_item?.id;

        if (offerItemId) {
            awardForm.spare_item_awards[offerItemId] = '';
        }
    });

    plan.lines.forEach((line) => {
        if (line.offerItemId) {
            awardForm.spare_item_awards[line.offerItemId] = normalizeDecimalInput(line.selectedQty);
        }
    });
};

const isSplitSuggestionApplied = (itemId, suggestionKey) => {
    const item = spareComparisonItemsById.value[itemId];
    const insight = spareAssistInsights.value[itemId];
    const plan = insight?.splitSuggestions?.[suggestionKey];

    if (!item?.supplier_offers?.length || !plan?.lines?.length) {
        return false;
    }

    return item.supplier_offers.every((offer) => {
        const offerItemId = offer.offered_item?.id;

        if (!offerItemId) {
            return true;
        }

        const selectedValue = normalizeDecimalInput(awardForm.spare_item_awards[offerItemId]);
        const plannedLine = plan.lines.find((line) => line.offerItemId === offerItemId);
        const plannedValue = plannedLine ? normalizeDecimalInput(plannedLine.selectedQty) : '';

        return selectedValue === plannedValue;
    });
};

const awardedQtyForItem = (itemId) => spareComparisonItemsById.value[itemId]?.supplier_offers
    ?.reduce((total, offer) => total + parseDecimal(awardForm.spare_item_awards[offer.offered_item.id]), 0) ?? 0;

const requestedQtyForItem = (itemId) => parseDecimal(
    spareComparisonItemsById.value[itemId]?.quantity,
);

const selectedQtyForItem = (itemId) => awardedQtyForItem(itemId);

const aboveRequestQtyForItem = (itemId) => Math.max(
    selectedQtyForItem(itemId) - requestedQtyForItem(itemId),
    0,
);

const formatQuantityValue = (value, unit = '') => {
    const numeric = parseDecimal(value);

    if (!Number.isFinite(numeric) || numeric <= 0) {
        return `0${unit ? ` ${unit}` : ''}`;
    }

    const normalized = Number.isInteger(numeric) ? `${numeric}` : `${numeric}`;
    return `${normalized}${unit ? ` ${unit}` : ''}`;
};

const formatMoneyValue = (value) => {
    const numeric = parseDecimal(value);
    return numeric.toFixed(2);
};

const formatCurrencyValue = (currency, value) => {
    const normalizedCurrency = `${currency ?? ''}`.trim();
    const normalizedValue = `${value ?? ''}`.trim();

    if (!normalizedCurrency) {
        return normalizedValue || '-';
    }

    return `${normalizedCurrency} ${normalizedValue || '0.00'}`;
};

const supplierQuotedItemsCount = (offer) => quotedItemCountByOfferId.value[offer.id] ?? 0;

const supplierCoveragePercent = (offer) => {
    const requestedCount = totalRequestedItemsCount.value;

    if (requestedCount <= 0) {
        return 0;
    }

    return (supplierQuotedItemsCount(offer) / requestedCount) * 100;
};

const highestSupplierCoveragePercent = computed(() => (
    spareComparisonSuppliers.value.reduce((highest, offer) => Math.max(highest, supplierCoveragePercent(offer)), 0)
));

const formatPercentValue = (value) => {
    if (!Number.isFinite(value) || value <= 0) {
        return '0%';
    }

    return Number.isInteger(value) ? `${value}%` : `${value.toFixed(1)}%`;
};

const supplierCoverageSummary = (offer) => {
    const quotedItemsCount = supplierQuotedItemsCount(offer);
    const requestedCount = totalRequestedItemsCount.value;
    const coveragePercent = supplierCoveragePercent(offer);
    const isFullCoverage = requestedCount > 0 && quotedItemsCount >= requestedCount;
    const isCoverageLeader = coveragePercent > 0 && Math.abs(coveragePercent - highestSupplierCoveragePercent.value) < 0.0001;
    const totalLabel = formatCurrencyValue(
        offer.currency,
        offer.grand_total || offer.total_offer_amount || '0.00',
    );

    return {
        quotedItemsCount,
        requestedCount,
        coveragePercent,
        coverageLabel: formatPercentValue(coveragePercent),
        itemsLabel: `${quotedItemsCount}/${requestedCount || 0}`,
        totalLabel,
        isFullCoverage,
        isCoverageLeader,
    };
};

const serviceSupplierCoverageSummary = (offer) => {
    const hasSubmittedServiceOffer = (
        parseDecimal(offer.grand_total || offer.total_offer_amount) > 0
        || `${offer.completion_time ?? ''}`.trim() !== ''
        || `${offer.offer_validity ?? ''}`.trim() !== ''
    );

    return {
        coverageLabel: formatPercentValue(hasSubmittedServiceOffer ? 100 : 0),
        totalLabel: formatCurrencyValue(
            offer.currency,
            offer.grand_total || offer.total_offer_amount || '0.00',
        ),
    };
};

const selectedTotalForItem = (itemId) => {
    const item = spareComparisonItemsById.value[itemId];

    if (!item?.supplier_offers?.length) {
        return 0;
    }

    return item.supplier_offers.reduce((total, offer) => {
        const offerItem = offer.offered_item;

        if (!offerItem?.id) {
            return total;
        }

        const selectedQty = parseDecimal(awardForm.spare_item_awards[offerItem.id]);
        const unitPrice = parseDecimal(offerItem.unit_price);

        if (selectedQty <= 0 || unitPrice <= 0) {
            return total;
        }

        return total + (selectedQty * unitPrice);
    }, 0);
};

const overallSelectedTotal = computed(() => spareComparisonItems.value.reduce(
    (total, item) => total + selectedTotalForItem(item.id),
    0,
));

const previewCurrency = computed(() => (
    props.rfq.currency
    || spareComparisonSuppliers.value[0]?.currency
    || offers.value[0]?.currency
    || ''
));

const rfqItemsById = computed(() => Object.fromEntries(
    (props.rfq.items ?? []).map((item) => [item.id, item]),
));

const selectedSpareAwardEntries = computed(() => offers.value.flatMap((offer) => (
    (offer.items ?? []).map((item) => {
        const selectedQty = parseDecimal(awardForm.spare_item_awards[item.id]);

        if (selectedQty <= 0) {
            return null;
        }

        const rfqItem = rfqItemsById.value[item.rfq_item_id] ?? null;
        const unitPrice = parseDecimal(item.unit_price);

        return {
            offer_id: offer.id,
            offer_item_id: item.id,
            rfq_item_id: item.rfq_item_id,
            supplier_id: offer.seller?.id ?? offer.id,
            supplier_name: offer.seller?.company_name ?? offer.seller?.name ?? '-',
            supplier_logo_url: offer.seller?.logo_url ?? null,
            currency: offer.currency,
            line_no: rfqItem?.line_no ?? item.line_no,
            item_name: rfqItem?.product_name ?? `Line ${item.line_no}`,
            unit: rfqItem?.unit ?? '',
            requested_qty: parseDecimal(rfqItem?.quantity),
            selected_qty: selectedQty,
            selected_total: selectedQty * unitPrice,
            buyer_note: selectionNoteValue(item.id),
        };
    }).filter(Boolean)
)));

const selectedSpareAwardsBySupplier = computed(() => {
    const grouped = new Map();

    selectedSpareAwardEntries.value.forEach((entry) => {
        if (!grouped.has(entry.offer_id)) {
            grouped.set(entry.offer_id, {
                offer_id: entry.offer_id,
                supplier_id: entry.supplier_id,
                supplier_name: entry.supplier_name,
                supplier_logo_url: entry.supplier_logo_url,
                currency: entry.currency,
                entries: [],
                subtotal: 0,
            });
        }

        const group = grouped.get(entry.offer_id);
        group.entries.push(entry);
        group.subtotal += entry.selected_total;
    });

    return Array.from(grouped.values());
});

const selectedServiceOffers = computed(() => {
    const selected = new Set(awardForm.service_offer_awards);

    return offers.value
        .filter((offer) => selected.has(offer.id))
        .map((offer) => ({
            offer_id: offer.id,
            supplier_name: offer.seller?.company_name ?? offer.seller?.name ?? '-',
            supplier_logo_url: offer.seller?.logo_url ?? null,
            currency: offer.currency,
            grand_total: parseDecimal(offer.grand_total || offer.total_offer_amount),
            completion_time: offer.completion_time || '-',
            buyer_note: serviceSelectionNoteValue(offer.id),
        }));
});

const previewSelectedSuppliersCount = computed(() => (
    isSpareParts.value ? selectedSpareAwardsBySupplier.value.length : selectedServiceOffers.value.length
));

const previewSelectedLinesCount = computed(() => (
    isSpareParts.value ? selectedSpareAwardEntries.value.length : selectedServiceOffers.value.length
));

const previewOverallSelectedTotal = computed(() => (
    isSpareParts.value
        ? overallSelectedTotal.value
        : selectedServiceOffers.value.reduce((total, offer) => total + offer.grand_total, 0)
));

const previewAboveRequestItems = computed(() => spareComparisonItems.value
    .map((item) => {
        const aboveQty = aboveRequestQtyForItem(item.id);

        if (aboveQty <= 0) {
            return null;
        }

        return {
            id: item.id,
            item_name: item.product_name || `Line ${item.line_no}`,
            unit: item.unit || '',
            above_qty: aboveQty,
            selections: selectedSpareAwardEntries.value
                .filter((entry) => entry.rfq_item_id === item.id)
                .map((entry) => ({
                    offer_item_id: entry.offer_item_id,
                    supplier_name: entry.supplier_name,
                    selected_qty: entry.selected_qty,
                })),
        };
    })
    .filter(Boolean));

const previewUnselectedItems = computed(() => spareComparisonItems.value
    .filter((item) => selectedQtyForItem(item.id) <= 0)
    .map((item) => ({
        id: item.id,
        item_name: item.product_name || `Line ${item.line_no}`,
        unit: item.unit || '',
        requested_qty: parseDecimal(item.quantity),
    })));

const confirmPreviewHasSelections = computed(() => (
    isSpareParts.value ? selectedSpareAwardEntries.value.length > 0 : selectedServiceOffers.value.length > 0
));

const previewEntryStatus = (entry) => {
    if ((entry.selected_qty ?? 0) > (entry.requested_qty ?? 0) && (entry.requested_qty ?? 0) > 0) {
        return currentCopy.value.itemStatusAboveRequest;
    }

    if ((entry.requested_qty ?? 0) > 0 && (entry.selected_qty ?? 0) >= (entry.requested_qty ?? 0)) {
        return currentCopy.value.itemStatusFullMatch;
    }

    return currentCopy.value.itemStatusPartialMatch;
};

const remainingQtyForItem = (itemId) => Math.max(
    requestedQtyForItem(itemId) - awardedQtyForItem(itemId),
    0,
);

const itemAwardState = (itemId) => {
    const requested = requestedQtyForItem(itemId);
    const awarded = awardedQtyForItem(itemId);

    if (requested > 0 && awarded >= requested) {
        return currentCopy.value.itemStatusFull;
    }

    if (awarded > 0) {
        return currentCopy.value.itemStatusPartial;
    }

    return currentCopy.value.itemStatusOpen;
};

const totalFullyAwardedItems = computed(() => spareComparisonItems.value
    .filter((item) => {
        const requested = parseDecimal(item.quantity);
        const awarded = awardedQtyForItem(item.id);
        return requested > 0 && awarded >= requested;
    }).length);

const totalPartiallyAwardedItems = computed(() => spareComparisonItems.value
    .filter((item) => {
        const requested = parseDecimal(item.quantity);
        const awarded = awardedQtyForItem(item.id);
        return requested > 0 && awarded > 0 && awarded < requested;
    }).length);

const totalUnawardedItems = computed(() => spareComparisonItems.value
    .filter((item) => awardedQtyForItem(item.id) <= 0).length);

const fullScopeSelectionError = computed(() => {
    if (!isSpareParts.value) {
        return '';
    }

    for (const offer of offers.value) {
        if (offer?.award_scope_policy !== 'full_scope_required') {
            continue;
        }

        const quotedItems = (offer.items ?? []).filter((item) => parseDecimal(item.offer_qty) > 0);

        if (!quotedItems.length) {
            continue;
        }

        const hasAnySelection = quotedItems.some((item) => parseDecimal(awardForm.spare_item_awards[item.id]) > 0);

        if (!hasAnySelection) {
            continue;
        }

        const hasIncompleteSelection = quotedItems.some((item) => {
            const selectedQty = parseDecimal(awardForm.spare_item_awards[item.id]);
            const offeredQty = parseDecimal(item.offer_qty);

            return Math.abs(selectedQty - offeredQty) > 0.0001;
        });

        if (!hasIncompleteSelection) {
            continue;
        }

        const supplierName = offer.seller?.company_name ?? offer.seller?.name ?? 'This supplier';
        return `${supplierName} requires full quoted scope acceptance. Select all quoted lines and quoted quantities from this supplier, or remove this supplier from the award.`;
    }

    return '';
});

const openConfirmPreview = () => {
    if (isSpareParts.value && hasSelectedQtyClientError.value) {
        awardForm.setError('spare_item_awards', currentCopy.value.selectedQtyExceedsOffer);
        return;
    }

    if (isSpareParts.value && fullScopeSelectionError.value) {
        awardForm.setError('spare_item_awards', fullScopeSelectionError.value);
        return;
    }

    awardForm.clearErrors('spare_item_awards');
    confirmPreviewOpen.value = true;
};

const closeConfirmPreview = () => {
    confirmPreviewOpen.value = false;
};

const normalizeSelectedQtyInput = (offerItemId) => {
    awardForm.spare_item_awards[offerItemId] = normalizeDecimalInput(awardForm.spare_item_awards[offerItemId]);
};

const toggleServiceOfferSelection = (offerId) => {
    const selected = new Set(awardForm.service_offer_awards);
    if (selected.has(offerId)) {
        selected.delete(offerId);
    } else {
        selected.add(offerId);
    }

    awardForm.service_offer_awards = Array.from(selected);
};

const submitAwards = (intent) => {
    if (isSpareParts.value && hasSelectedQtyClientError.value) {
        awardForm.setError('spare_item_awards', currentCopy.value.selectedQtyExceedsOffer);
        return;
    }

    if (intent === 'confirm' && isSpareParts.value && fullScopeSelectionError.value) {
        awardForm.setError('spare_item_awards', fullScopeSelectionError.value);
        return;
    }

    awardForm.clearErrors('spare_item_awards');
    awardForm.intent = intent;

    awardForm.transform((data) => ({
        intent,
        spare_item_awards: Object.entries(data.spare_item_awards ?? {}).map(([offerItemId, awardedQuantity]) => ({
            offer_item_id: Number(offerItemId),
            awarded_quantity: awardedQuantity,
            buyer_note: `${data.spare_item_notes?.[offerItemId] ?? ''}`.trim(),
        })),
        service_offer_awards: data.service_offer_awards,
        service_offer_notes: data.service_offer_notes,
    })).post(props.rfq.award_save_url, {
        preserveScroll: true,
    });
};

const confirmAwardsFromPreview = () => {
    confirmPreviewOpen.value = false;
    submitAwards('confirm');
};

const detailModal = ref(null);
const commercialOfferModal = ref(null);
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

const openCommercialOfferModal = (offer) => {
    commercialOfferModal.value = offer;
};

const closeCommercialOfferModal = () => {
    commercialOfferModal.value = null;
};

const isImageAttachment = (attachment) => {
    const source = `${attachment?.name ?? ''} ${attachment?.url ?? ''}`.toLowerCase();
    return /\.(png|jpe?g|gif|webp|bmp|svg)(\?|$)/.test(source);
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
                    <p class="directory-intro-copy">{{ heroIntroText }}</p>

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

                    <div v-if="offers.length" class="assist-bar">
                        <span class="assist-bar-label">{{ currentCopy.smartAssist }}</span>
                        <div class="assist-bar-actions">
                            <button type="button" class="assist-button" :class="{ 'is-active': activeAssistMode === 'recommended' }" @click="setAssistMode('recommended')">
                                {{ currentCopy.recommendBestMatches }}
                            </button>
                            <button type="button" class="assist-button" :class="{ 'is-active': activeAssistMode === 'full' }" @click="setAssistMode('full')">
                                {{ currentCopy.showFullQuantitySuppliers }}
                            </button>
                            <button type="button" class="assist-button" :class="{ 'is-active': activeAssistMode === 'price' }" @click="setAssistMode('price')">
                                {{ currentCopy.highlightLowestPrice }}
                            </button>
                            <button type="button" class="assist-button" :class="{ 'is-active': activeAssistMode === 'fastest' }" @click="setAssistMode('fastest')">
                                {{ currentCopy.highlightFastestDelivery }}
                            </button>
                            <button type="button" class="assist-button" :class="{ 'is-active': activeAssistMode === 'split' }" @click="setAssistMode('split')">
                                {{ currentCopy.suggestBestSplit }}
                            </button>
                            <button type="button" class="assist-button is-reset" :disabled="activeAssistMode === 'none'" @click="setAssistMode('none')">
                                {{ currentCopy.resetView }}
                            </button>
                        </div>
                    </div>

                    <div class="detail-table-wrap">
                        <table class="detail-table">
                            <colgroup>
                                <col class="col-line">
                                <col class="col-requested-item">
                                <col v-for="offer in spareComparisonSuppliers" :key="`detail-col-${offer.id}`" class="col-supplier">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>{{ currentCopy.table.line }}</th>
                                    <th>{{ currentCopy.requestedItem }}</th>
                                    <th v-for="offer in spareComparisonSuppliers" :key="`detail-supplier-head-${offer.id}`">
                                        <div class="detail-supplier-head">
                                            <div class="detail-supplier-head-top">
                                                <div v-if="offer.seller.logo_url" class="award-supplier-logo-wrap detail-supplier-logo-wrap">
                                                    <img :src="offer.seller.logo_url" :alt="offer.seller.company_name" class="award-supplier-logo">
                                                </div>
                                                <div class="detail-supplier-head-copy">
                                                    <div class="detail-supplier-head-row">
                                                        <strong class="detail-supplier-head-name">{{ offer.seller.company_name }}</strong>
                                                        <button
                                                            type="button"
                                                            class="detail-supplier-head-action"
                                                            @click="openCommercialOfferModal(offer)"
                                                        >
                                                            {{ currentCopy.offerTerms }}
                                                        </button>
                                                    </div>
                                                    <p class="detail-supplier-head-note">{{ awardScopeSummary(offer) }}</p>
                                                </div>
                                            </div>
                                            <div
                                                class="detail-supplier-head-metrics"
                                                :class="{
                                                    'is-full-coverage': supplierCoverageSummary(offer).isFullCoverage,
                                                    'is-coverage-leader': supplierCoverageSummary(offer).isCoverageLeader,
                                                }"
                                            >
                                                <div class="detail-supplier-head-metric is-total">
                                                    <span>{{ currentCopy.totalAmountLabel }}:</span>
                                                    <strong>{{ supplierCoverageSummary(offer).totalLabel }}</strong>
                                                </div>
                                                <div class="detail-supplier-head-metric is-quotation">
                                                    <span>{{ currentCopy.quoteCoverage }}:</span>
                                                    <strong>{{ supplierCoverageSummary(offer).coverageLabel }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="item in spareComparisonItems" :key="item.id">
                                    <tr class="compare-item-row">
                                        <td class="line-cell">
                                            <span>{{ item.line_no }}</span>
                                        </td>
                                        <td>
                                            <div class="requested-item-card">
                                                <strong class="requested-item-title">{{ item.product_name || '-' }}</strong>

                                                <div class="requested-item-meta">
                                                    <div class="requested-item-line">
                                                        <span>{{ currentCopy.table.partNo }}</span>
                                                        <strong>{{ item.part_no || '-' }}</strong>
                                                    </div>
                                                    <div class="requested-item-line">
                                                        <span>{{ currentCopy.table.manufacturer }}</span>
                                                        <strong>{{ item.manufacturer || '-' }}</strong>
                                                    </div>
                                                    <div class="requested-item-line">
                                                        <span>{{ currentCopy.table.modelType }}</span>
                                                        <strong>{{ item.model_type || '-' }}</strong>
                                                    </div>
                                                    <div class="requested-item-line">
                                                        <span>{{ currentCopy.table.catalogCode }}</span>
                                                        <strong>{{ item.catalog_code || '-' }}</strong>
                                                    </div>
                                                    <div class="requested-item-line">
                                                        <span>{{ currentCopy.table.serialNumber }}</span>
                                                        <strong>{{ item.serial_number || '-' }}</strong>
                                                    </div>
                                                    <div class="requested-item-line">
                                                        <span>{{ currentCopy.table.drawingNumber }}</span>
                                                        <strong>{{ item.drawing_number || '-' }}</strong>
                                                    </div>
                                                    <div class="requested-item-line">
                                                        <span>{{ currentCopy.table.qty }} / {{ currentCopy.table.unit }}</span>
                                                        <strong>{{ item.quantity || '-' }} {{ item.unit || '' }}</strong>
                                                    </div>
                                                    <div class="requested-item-line">
                                                        <span>{{ currentCopy.table.rob }}</span>
                                                        <strong>{{ item.rob || '-' }}</strong>
                                                    </div>
                                                    <div class="requested-item-line">
                                                        <span>{{ currentCopy.table.quality }}</span>
                                                        <strong>{{ formatTitleCaseValue(item.quality) }}</strong>
                                                    </div>
                                                    <div class="requested-item-line">
                                                        <span>{{ currentCopy.table.comments }}</span>
                                                        <strong>{{ item.comments || '-' }}</strong>
                                                    </div>
                                                    <div class="requested-item-line">
                                                        <span>{{ currentCopy.table.files }}</span>
                                                        <strong>
                                                            <button
                                                                v-if="item.attachments?.length"
                                                                type="button"
                                                                class="file-preview-button requested-item-file-button"
                                                                @click="openAttachmentViewer(item.attachments)"
                                                            >
                                                                {{ fileButtonLabel(item.attachments) }}
                                                            </button>
                                                            <span v-else>-</span>
                                                        </strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td v-for="(offer, offerIndex) in spareComparisonSuppliers" :key="`detail-supplier-cell-${item.id}-${offer.id}`">
                                            <div
                                                class="detail-supplier-offer-card"
                                                :class="{
                                                    'is-assist-highlighted': isAssistHighlighted(item, offer.id),
                                                    'is-assist-muted': isAssistMuted(item, offer.id),
                                                }"
                                            >
                                                <strong class="detail-supplier-offer-title">{{ item.product_name || '-' }}</strong>
                                                <div v-if="assistBadgeLabel(item, offer.id)" class="assist-card-summary">
                                                    <span class="assist-card-badge">{{ assistBadgeLabel(item, offer.id) }}</span>
                                                    <p class="assist-card-reason">{{ assistReasonText(item, offer.id) }}</p>
                                                </div>

                                                <div class="detail-supplier-offer-meta">
                                                    <div class="detail-supplier-offer-line">
                                                        <span>{{ currentCopy.offeredQty }}</span>
                                                        <strong>{{ supplierOfferForItem(item, offer.id)?.offered_item?.offer_qty ? `${supplierOfferForItem(item, offer.id).offered_item.offer_qty} ${item.unit || ''}` : '-' }}</strong>
                                                    </div>
                                                    <div class="detail-supplier-offer-line">
                                                        <span>{{ currentCopy.unitPrice }}</span>
                                                        <strong>{{ supplierOfferForItem(item, offer.id)?.offered_item?.unit_price ? `${offer.currency} ${supplierOfferForItem(item, offer.id).offered_item.unit_price}` : '-' }}</strong>
                                                    </div>
                                                    <div class="detail-supplier-offer-line">
                                                        <span>{{ currentCopy.total }}</span>
                                                        <strong>{{ supplierOfferForItem(item, offer.id)?.offered_item?.line_total ? `${offer.currency} ${supplierOfferForItem(item, offer.id).offered_item.line_total}` : '-' }}</strong>
                                                    </div>
                                                </div>

                                                <div class="detail-supplier-offer-extra">
                                                    <div class="detail-supplier-offer-extra-line">
                                                        <span>{{ currentCopy.deliveryTime }}:</span>
                                                        <strong>{{ formatDeliveryDays(supplierOfferForItem(item, offer.id)?.offered_item?.delivery_time) }}</strong>
                                                    </div>
                                                    <div class="detail-supplier-offer-extra-line">
                                                        <span>{{ currentCopy.table.quality }}:</span>
                                                        <strong>{{ formatTitleCaseValue(supplierOfferForItem(item, offer.id)?.offered_item?.quality) }}</strong>
                                                    </div>
                                                    <div class="detail-supplier-offer-extra-line">
                                                        <span>{{ currentCopy.table.manufacturer }}:</span>
                                                        <strong>{{ textOrDash(supplierOfferForItem(item, offer.id)?.offered_item?.manufacturer) }}</strong>
                                                    </div>
                                                    <div class="detail-supplier-offer-extra-line">
                                                        <span>{{ currentCopy.remarks }}:</span>
                                                        <strong>{{ textOrDash(supplierOfferForItem(item, offer.id)?.offered_item?.remarks) }}</strong>
                                                    </div>
                                                </div>

                                                <div class="detail-supplier-offer-extra-line detail-supplier-offer-files">
                                                    <span>{{ currentCopy.table.files }}:</span>
                                                    <button
                                                        v-if="supplierOfferForItem(item, offer.id)?.offered_item?.attachments?.length"
                                                        type="button"
                                                        class="file-preview-button detail-supplier-file-button"
                                                        @click="openAttachmentViewer(supplierOfferForItem(item, offer.id).offered_item.attachments)"
                                                    >
                                                        {{ fileButtonLabel(supplierOfferForItem(item, offer.id).offered_item.attachments) }}
                                                    </button>
                                                    <strong v-else>-</strong>
                                                </div>

                                                <div class="detail-supplier-award-field">
                                                    <label class="detail-inline-label detail-supplier-award-label">{{ currentCopy.awardedQty }}</label>
                                                    <template v-if="supplierOfferForItem(item, offer.id)?.offered_item?.id">
                                                        <input
                                                            v-model="awardForm.spare_item_awards[supplierOfferForItem(item, offer.id).offered_item.id]"
                                                            type="text"
                                                            inputmode="decimal"
                                                            autocomplete="off"
                                                            class="offer-input detail-supplier-award-input"
                                                            :class="{ 'is-error': selectedQtyError(item, offer.id) }"
                                                            @blur="normalizeSelectedQtyInput(supplierOfferForItem(item, offer.id).offered_item.id)"
                                                        >
                                                        <p v-if="selectedQtyError(item, offer.id)" class="award-inline-error">
                                                            {{ selectedQtyError(item, offer.id) }}
                                                        </p>
                                                        <div
                                                            v-if="shouldShowSelectionNoteField(supplierOfferForItem(item, offer.id).offered_item.id)"
                                                            class="detail-supplier-award-note"
                                                        >
                                                            <label class="detail-inline-label detail-supplier-award-label">{{ currentCopy.selectionNote }}</label>
                                                            <textarea
                                                                v-model="awardForm.spare_item_notes[supplierOfferForItem(item, offer.id).offered_item.id]"
                                                                class="offer-input detail-supplier-award-note-input"
                                                                :placeholder="currentCopy.selectionNotePlaceholder"
                                                                maxlength="2000"
                                                            ></textarea>
                                                        </div>
                                                    </template>
                                                    <div v-else class="detail-supplier-award-empty">-</div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="selection-summary-row">
                                        <td class="selection-summary-spacer"></td>
                                        <td class="selection-summary-spacer"></td>
                                        <td :colspan="Math.max(spareComparisonSuppliers.length, 1)">
                                            <div class="selection-summary-strip">
                                                <strong class="selection-summary-title">{{ currentCopy.selectionSummary }}</strong>
                                                <div class="selection-summary-strip-grid">
                                                    <div class="selection-summary-strip-item">
                                                        <span>{{ currentCopy.requestedQty }}</span>
                                                        <strong>{{ formatQuantityValue(item.quantity, item.unit) }}</strong>
                                                    </div>
                                                    <div class="selection-summary-strip-item">
                                                        <span>{{ currentCopy.selectedQtySummary }}</span>
                                                        <strong>{{ formatQuantityValue(selectedQtyForItem(item.id), item.unit) }}</strong>
                                                    </div>
                                                    <div v-if="aboveRequestQtyForItem(item.id) > 0" class="selection-summary-strip-item is-emphasis">
                                                        <span>{{ currentCopy.aboveRequest }}</span>
                                                        <strong>+{{ formatQuantityValue(aboveRequestQtyForItem(item.id), item.unit) }}</strong>
                                                    </div>
                                                    <div class="selection-summary-strip-item">
                                                        <span>{{ currentCopy.selectedTotal }}</span>
                                                        <strong>{{ formatCurrencyValue(rfq.currency || spareComparisonSuppliers[0]?.currency || '', formatMoneyValue(selectedTotalForItem(item.id))) }}</strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="showSplitSuggestionForItem(item.id) && splitSuggestionEntries(item).length"
                                        class="split-suggestion-row"
                                    >
                                        <td class="selection-summary-spacer"></td>
                                        <td class="selection-summary-spacer"></td>
                                        <td :colspan="Math.max(spareComparisonSuppliers.length, 1)">
                                            <div class="split-suggestion-panel">
                                                <div class="split-suggestion-head">
                                                    <div class="split-suggestion-head-copy">
                                                        <strong>{{ currentCopy.splitSuggestionTitle }}</strong>
                                                        <p>{{ currentCopy.splitSuggestionText }}</p>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="split-suggestion-close"
                                                        @click="closeSplitSuggestionForItem(item.id)"
                                                    >
                                                        {{ currentCopy.close }}
                                                    </button>
                                                </div>
                                                <div class="split-suggestion-list">
                                                    <article
                                                        v-for="suggestion in splitSuggestionEntries(item)"
                                                        :key="`split-suggestion-${item.id}-${suggestion.key}`"
                                                        class="split-suggestion-card"
                                                    >
                                                        <div class="split-suggestion-card-head">
                                                            <div class="split-suggestion-card-copy">
                                                                <strong>{{ suggestion.label }}</strong>
                                                                <p>{{ suggestion.description }}</p>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                class="split-suggestion-apply"
                                                                :class="{ 'is-applied': isSplitSuggestionApplied(item.id, suggestion.key) }"
                                                                :disabled="isSplitSuggestionApplied(item.id, suggestion.key)"
                                                                @click="applySplitSuggestion(item.id, suggestion.key)"
                                                            >
                                                                {{ isSplitSuggestionApplied(item.id, suggestion.key) ? currentCopy.appliedSuggestion : currentCopy.applySuggestion }}
                                                            </button>
                                                        </div>
                                                        <p class="split-suggestion-route">{{ suggestion.routeText }}</p>
                                                        <div class="split-suggestion-metrics">
                                                            <span><strong>{{ currentCopy.coverage }}:</strong> {{ suggestion.coverageText }}</span>
                                                            <span><strong>{{ currentCopy.estimatedTotal }}:</strong> {{ suggestion.totalText }}</span>
                                                            <span><strong>{{ currentCopy.maxDelivery }}:</strong> {{ suggestion.deliveryText }}</span>
                                                        </div>
                                                    </article>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="spareComparisonSuppliers.length" class="overall-selection-total">
                        <div class="overall-selection-total-card">
                            <span>{{ currentCopy.overallSelectedTotal }}</span>
                            <strong>{{ formatCurrencyValue(rfq.currency || spareComparisonSuppliers[0]?.currency || '', formatMoneyValue(overallSelectedTotal)) }}</strong>
                        </div>
                    </div>
                </div>

            </section>

            <section
                v-if="!isSpareParts || !offers.length || awardForm.errors.spare_item_awards || awardForm.errors.service_offer_awards"
                class="surface-card section-card combined-detail-section"
            >
                <div class="subsection-surface">
                    <div v-if="awardForm.errors.spare_item_awards || awardForm.errors.service_offer_awards" class="award-error-banner">
                        {{ awardForm.errors.spare_item_awards || awardForm.errors.service_offer_awards }}
                    </div>

                    <div v-if="!offers.length" class="empty-inline">
                        {{ currentCopy.noOffersYet }}
                    </div>

                    <div v-else-if="!isSpareParts" class="detail-table-wrap">
                        <table class="detail-table service-detail-table">
                            <colgroup>
                                <col class="col-line">
                                <col class="col-requested-item">
                                <col v-for="offer in offers" :key="`service-detail-col-${offer.id}`" class="col-supplier">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>{{ currentCopy.table.line }}</th>
                                    <th>{{ currentCopy.requestedService }}</th>
                                    <th v-for="offer in offers" :key="`service-detail-supplier-head-${offer.id}`">
                                        <div class="detail-supplier-head">
                                            <div class="detail-supplier-head-top">
                                                <div v-if="offer.seller.logo_url" class="award-supplier-logo-wrap detail-supplier-logo-wrap">
                                                    <img :src="offer.seller.logo_url" :alt="offer.seller.company_name" class="award-supplier-logo">
                                                </div>
                                                <div class="detail-supplier-head-copy">
                                                    <div class="detail-supplier-head-row">
                                                        <strong class="detail-supplier-head-name">{{ offer.seller.company_name }}</strong>
                                                        <button
                                                            type="button"
                                                            class="detail-supplier-head-action"
                                                            @click="openCommercialOfferModal(offer)"
                                                        >
                                                            {{ currentCopy.offerTerms }}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="detail-supplier-head-metrics">
                                                <div class="detail-supplier-head-metric is-total">
                                                    <span>{{ currentCopy.totalAmountLabel }}:</span>
                                                    <strong>{{ serviceSupplierCoverageSummary(offer).totalLabel }}</strong>
                                                </div>
                                                <div class="detail-supplier-head-metric is-quotation">
                                                    <span>{{ currentCopy.quoteCoverage }}:</span>
                                                    <strong>{{ serviceSupplierCoverageSummary(offer).coverageLabel }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="compare-item-row service-compare-item-row">
                                    <td class="line-cell">
                                        <span>1</span>
                                    </td>
                                    <td>
                                        <div class="requested-item-card">
                                            <strong class="requested-item-title">{{ rfq.service_title || '-' }}</strong>

                                            <div class="requested-item-meta">
                                                <div class="requested-item-line">
                                                    <span>{{ currentCopy.titleLabel }}</span>
                                                    <strong>{{ rfq.service_title || '-' }}</strong>
                                                </div>
                                                <div class="requested-item-line requested-item-line-wide">
                                                    <span>{{ currentCopy.descriptionLabel }}</span>
                                                    <strong>{{ rfq.service_description || currentCopy.noDescription }}</strong>
                                                </div>
                                                <div class="requested-item-line">
                                                    <span>{{ currentCopy.files }}</span>
                                                    <strong>
                                                        <button
                                                            v-if="rfq.attachments?.length"
                                                            type="button"
                                                            class="file-preview-button requested-item-file-button"
                                                            @click="openAttachmentViewer(rfq.attachments)"
                                                        >
                                                            {{ fileButtonLabel(rfq.attachments) }}
                                                        </button>
                                                        <span v-else>-</span>
                                                    </strong>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td v-for="offer in offers" :key="`service-detail-supplier-cell-${offer.id}`">
                                        <div
                                            class="detail-supplier-offer-card service-detail-supplier-card"
                                            :class="{ 'is-selected': awardForm.service_offer_awards.includes(offer.id) }"
                                        >
                                            <strong class="detail-supplier-offer-title">{{ rfq.service_title || currentCopy.service }}</strong>

                                            <div class="detail-supplier-offer-meta">
                                                <div class="detail-supplier-offer-line">
                                                    <span>{{ currentCopy.totalPrice }}</span>
                                                    <strong>{{ currencyAmountOrDash(offer.currency, offer.total_offer_amount) }}</strong>
                                                </div>
                                                <div class="detail-supplier-offer-line">
                                                    <span>{{ currentCopy.tax }}</span>
                                                    <strong>{{ includedAmountSummary(offer.including_tax, offer.currency, offer.tax_amount) }}</strong>
                                                </div>
                                                <div class="detail-supplier-offer-line">
                                                    <span>{{ currentCopy.mobilization }}</span>
                                                    <strong>{{ includedAmountSummary(offer.including_mobilization, offer.currency, offer.mobilization_cost) }}</strong>
                                                </div>
                                                <div class="detail-supplier-offer-line">
                                                    <span>{{ currentCopy.grandTotal }}</span>
                                                    <strong>{{ currencyAmountOrDash(offer.currency, offer.grand_total) }}</strong>
                                                </div>
                                            </div>

                                            <div class="detail-supplier-offer-extra">
                                                <div class="detail-supplier-offer-extra-line">
                                                    <span>{{ currentCopy.completionTime }}:</span>
                                                    <strong>{{ textOrDash(offer.completion_time) }}</strong>
                                                </div>
                                                <div class="detail-supplier-offer-extra-line">
                                                    <span>{{ currentCopy.offerValidity }}:</span>
                                                    <strong>{{ textOrDash(offer.offer_validity) }}</strong>
                                                </div>
                                                <div class="detail-supplier-offer-extra-line">
                                                    <span>{{ currentCopy.deliveryTerms }}:</span>
                                                    <strong>{{ textOrDash(offer.delivery_terms) }}</strong>
                                                </div>
                                                <div class="detail-supplier-offer-extra-line">
                                                    <span>{{ currentCopy.paymentTerms }}:</span>
                                                    <strong>{{ paymentTermsSummary(offer) }}</strong>
                                                </div>
                                                <div class="detail-supplier-offer-extra-line is-long-value">
                                                    <span>{{ currentCopy.serviceClarification }}:</span>
                                                    <strong>{{ textOrDash(offer.service_clarification) }}</strong>
                                                </div>
                                                <div class="detail-supplier-offer-extra-line is-long-value">
                                                    <span>{{ currentCopy.generalNote }}:</span>
                                                    <strong>{{ textOrDash(offer.general_note) }}</strong>
                                                </div>
                                            </div>

                                            <div class="detail-supplier-offer-extra-line detail-supplier-offer-files">
                                                <span>{{ currentCopy.files }}:</span>
                                                <button
                                                    v-if="offer.attachments?.length"
                                                    type="button"
                                                    class="file-preview-button detail-supplier-file-button"
                                                    @click="openAttachmentViewer(offer.attachments)"
                                                >
                                                    {{ fileButtonLabel(offer.attachments) }}
                                                </button>
                                                <strong v-else>-</strong>
                                            </div>

                                            <div class="detail-supplier-award-field service-detail-select-field">
                                                <label class="detail-inline-label detail-supplier-award-label">{{ currentCopy.serviceSelect }}</label>
                                                <label class="service-award-toggle-card">
                                                    <input
                                                        :checked="awardForm.service_offer_awards.includes(offer.id)"
                                                        type="checkbox"
                                                        @change="toggleServiceOfferSelection(offer.id)"
                                                    >
                                                    <span>{{ awardForm.service_offer_awards.includes(offer.id) ? currentCopy.confirmedAward : currentCopy.serviceSelect }}</span>
                                                </label>
                                                <div
                                                    v-if="shouldShowServiceSelectionNoteField(offer.id)"
                                                    class="detail-supplier-award-note"
                                                >
                                                    <label class="detail-inline-label detail-supplier-award-label">{{ currentCopy.selectionNote }}</label>
                                                    <textarea
                                                        v-model="awardForm.service_offer_notes[offer.id]"
                                                        class="offer-input detail-supplier-award-note-input"
                                                        :placeholder="currentCopy.selectionNotePlaceholder"
                                                        maxlength="2000"
                                                    ></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
            </section>

            <div v-if="offers.length" class="award-actions">
                <Link :href="backUrl" class="back-button award-actions-back">
                    {{ currentCopy.back }}
                </Link>
                <div class="award-actions-right">
                    <button type="button" class="offer-button offer-button-login" :disabled="awardForm.processing" @click="submitAwards('draft')">
                        {{ currentCopy.saveAwardDraft }}
                    </button>
                    <button type="button" class="offer-button" :disabled="awardForm.processing" @click="openConfirmPreview">
                        {{ currentCopy.confirmAwards }}
                    </button>
                </div>
            </div>

        </section>

        <div v-if="confirmPreviewOpen" class="detail-modal-backdrop" @click.self="closeConfirmPreview">
            <div class="detail-modal confirm-award-modal">
                <div class="detail-modal-head">
                    <div>
                        <h3 class="detail-modal-title">{{ currentCopy.reviewAwardSelections }}</h3>
                        <p class="confirm-award-modal-copy">{{ currentCopy.reviewAwardSelectionsText }}</p>
                    </div>
                    <button type="button" class="detail-modal-close" @click="closeConfirmPreview">
                        {{ currentCopy.close }}
                    </button>
                </div>

                <div class="detail-modal-body confirm-award-modal-body">
                    <div class="confirm-award-summary-grid">
                        <div class="confirm-award-summary-card">
                            <span>{{ currentCopy.selectedSuppliers }}</span>
                            <strong>{{ previewSelectedSuppliersCount }}</strong>
                        </div>
                        <div class="confirm-award-summary-card">
                            <span>{{ isSpareParts ? currentCopy.selectedLines : currentCopy.selectedOffersLabel }}</span>
                            <strong>{{ previewSelectedLinesCount }}</strong>
                        </div>
                        <div class="confirm-award-summary-card">
                            <span>{{ currentCopy.overallSelectedTotal }}</span>
                            <strong>{{ formatCurrencyValue(previewCurrency, formatMoneyValue(previewOverallSelectedTotal)) }}</strong>
                        </div>
                    </div>

                    <div v-if="!confirmPreviewHasSelections" class="empty-inline">
                        {{ currentCopy.noSelectionsYet }}
                    </div>

                    <template v-else-if="isSpareParts">
                        <div class="confirm-award-supplier-stack">
                            <article v-for="group in selectedSpareAwardsBySupplier" :key="group.offer_id" class="confirm-award-supplier-card">
                                <div class="confirm-award-supplier-head">
                                    <div class="award-supplier">
                                        <div v-if="group.supplier_logo_url" class="award-supplier-logo-wrap">
                                            <img :src="group.supplier_logo_url" :alt="group.supplier_name" class="award-supplier-logo">
                                        </div>
                                        <div>
                                            <strong class="award-supplier-name">{{ group.supplier_name }}</strong>
                                            <p class="award-supplier-subtitle">{{ group.entries.length }} {{ currentCopy.selectedLines.toLowerCase() }}</p>
                                        </div>
                                    </div>
                                    <strong class="confirm-award-supplier-total">
                                        {{ formatCurrencyValue(group.currency, formatMoneyValue(group.subtotal)) }}
                                    </strong>
                                </div>

                                <div class="confirm-award-line-list">
                                    <div v-for="entry in group.entries" :key="entry.offer_item_id" class="confirm-award-line-row">
                                        <div class="confirm-award-line-copy">
                                            <strong>{{ entry.item_name }}</strong>
                                            <span>{{ currentCopy.lineLabel }} {{ entry.line_no }}</span>
                                            <span v-if="entry.buyer_note" class="confirm-award-line-note">
                                                {{ currentCopy.selectionNote }}: {{ entry.buyer_note }}
                                            </span>
                                        </div>
                                        <div class="confirm-award-line-meta">
                                            <span>{{ currentCopy.requestedQtyLabel }}: {{ formatQuantityValue(entry.requested_qty, entry.unit) }}</span>
                                            <span>{{ currentCopy.selectedQtyLabel }}: {{ formatQuantityValue(entry.selected_qty, entry.unit) }}</span>
                                            <span>{{ currentCopy.itemStatusLabel }}: {{ previewEntryStatus(entry) }}</span>
                                            <strong>{{ formatCurrencyValue(entry.currency, formatMoneyValue(entry.selected_total)) }}</strong>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <div v-if="previewAboveRequestItems.length || previewUnselectedItems.length" class="confirm-award-warning-grid">
                            <div v-if="previewAboveRequestItems.length" class="confirm-award-warning-card is-warning">
                                <strong>{{ currentCopy.itemsAboveRequest }}</strong>
                                <div class="confirm-award-warning-list">
                                    <div v-for="item in previewAboveRequestItems" :key="`above-${item.id}`" class="confirm-award-warning-row">
                                        <div class="confirm-award-warning-copy">
                                            <span>{{ item.item_name }}</span>
                                            <div class="confirm-award-warning-suppliers">
                                                <span v-for="selection in item.selections" :key="`above-selection-${selection.offer_item_id}`">
                                                    {{ selection.supplier_name }}: {{ formatQuantityValue(selection.selected_qty, item.unit) }}
                                                </span>
                                            </div>
                                        </div>
                                        <strong>+{{ formatQuantityValue(item.above_qty, item.unit) }}</strong>
                                    </div>
                                </div>
                            </div>

                            <div v-if="previewUnselectedItems.length" class="confirm-award-warning-card">
                                <strong>{{ currentCopy.itemsWithoutSelection }}</strong>
                                <div class="confirm-award-warning-list">
                                    <div v-for="item in previewUnselectedItems" :key="`missing-${item.id}`" class="confirm-award-warning-row">
                                        <span>{{ item.item_name }}</span>
                                        <strong>{{ formatQuantityValue(item.requested_qty, item.unit) }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <div class="confirm-award-supplier-stack">
                            <article v-for="offer in selectedServiceOffers" :key="offer.offer_id" class="confirm-award-supplier-card">
                                <div class="confirm-award-supplier-head">
                                    <div class="award-supplier">
                                        <div v-if="offer.supplier_logo_url" class="award-supplier-logo-wrap">
                                            <img :src="offer.supplier_logo_url" :alt="offer.supplier_name" class="award-supplier-logo">
                                        </div>
                                        <div>
                                            <strong class="award-supplier-name">{{ offer.supplier_name }}</strong>
                                            <p class="award-supplier-subtitle">{{ currentCopy.serviceSelect }}</p>
                                        </div>
                                    </div>
                                    <strong class="confirm-award-supplier-total">
                                        {{ formatCurrencyValue(offer.currency, formatMoneyValue(offer.grand_total)) }}
                                    </strong>
                                </div>
                                <div class="confirm-award-line-list">
                                    <div class="confirm-award-line-row">
                                        <div class="confirm-award-line-copy">
                                            <strong>{{ currentCopy.completionTime }}</strong>
                                            <span v-if="offer.buyer_note" class="confirm-award-line-note">
                                                {{ currentCopy.selectionNote }}: {{ offer.buyer_note }}
                                            </span>
                                        </div>
                                        <div class="confirm-award-line-meta">
                                            <strong>{{ offer.completion_time }}</strong>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </template>
                </div>

                <div class="confirm-award-modal-actions">
                    <button type="button" class="offer-button offer-button-login" @click="closeConfirmPreview">
                        {{ currentCopy.goBack }}
                    </button>
                    <button
                        type="button"
                        class="offer-button"
                        :disabled="awardForm.processing || !confirmPreviewHasSelections"
                        @click="confirmAwardsFromPreview"
                    >
                        {{ currentCopy.confirmAwards }}
                    </button>
                </div>
            </div>
        </div>

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

                <div v-else class="detail-modal-body">
                    <div class="modal-pill-list">
                        <span v-for="subcategory in selectedSubcategoryNames" :key="subcategory" class="modal-pill">
                            {{ subcategory }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="commercialOfferModal" class="detail-modal-backdrop" @click.self="closeCommercialOfferModal">
            <div class="detail-modal commercial-offer-modal">
                <div class="detail-modal-head">
                    <div>
                        <h3 class="detail-modal-title">{{ commercialOfferModal.seller.company_name }}</h3>
                    </div>
                    <button type="button" class="detail-modal-close" @click="closeCommercialOfferModal">
                        {{ currentCopy.close }}
                    </button>
                </div>

                <div class="detail-modal-body commercial-offer-modal-body">
                    <div class="commercial-offer-grid">
                        <div class="commercial-offer-row">
                            <span>{{ currentCopy.totalOfferAmount }}</span>
                            <strong>{{ currencyAmountOrDash(commercialOfferModal.currency, commercialOfferModal.total_offer_amount) }}</strong>
                        </div>
                        <div class="commercial-offer-row">
                            <span>{{ currentCopy.tax }}</span>
                            <strong>{{ includedAmountSummary(commercialOfferModal.including_tax, commercialOfferModal.currency, commercialOfferModal.tax_amount) }}</strong>
                        </div>
                        <template v-if="isSpareParts">
                            <div class="commercial-offer-row">
                                <span>{{ currentCopy.packing }}</span>
                                <strong>{{ includedAmountSummary(commercialOfferModal.including_packing, commercialOfferModal.currency, commercialOfferModal.packing_cost) }}</strong>
                            </div>
                            <div class="commercial-offer-row">
                                <span>{{ currentCopy.freight }}</span>
                                <strong>{{ includedAmountSummary(commercialOfferModal.including_freight, commercialOfferModal.currency, commercialOfferModal.freight_cost) }}</strong>
                            </div>
                        </template>
                        <div v-else class="commercial-offer-row">
                            <span>{{ currentCopy.mobilization }}</span>
                            <strong>{{ includedAmountSummary(commercialOfferModal.including_mobilization, commercialOfferModal.currency, commercialOfferModal.mobilization_cost) }}</strong>
                        </div>
                        <div class="commercial-offer-row commercial-offer-row-grand">
                            <span>{{ currentCopy.grandTotal }}</span>
                            <strong>{{ currencyAmountOrDash(commercialOfferModal.currency, commercialOfferModal.grand_total) }}</strong>
                        </div>
                        <div class="commercial-offer-row commercial-offer-row-gap">
                            <span>{{ currentCopy.deliveryTerms }}</span>
                            <strong>{{ textOrDash(commercialOfferModal.delivery_terms) }}</strong>
                        </div>
                        <div class="commercial-offer-row">
                            <span>{{ currentCopy.awardScope }}</span>
                            <strong>{{ awardScopeSummary(commercialOfferModal) }}</strong>
                        </div>
                        <div class="commercial-offer-row">
                            <span>{{ currentCopy.paymentTerms }}</span>
                            <strong>{{ paymentTermsSummary(commercialOfferModal) }}</strong>
                        </div>
                        <template v-if="!isSpareParts">
                            <div class="commercial-offer-row">
                                <span>{{ currentCopy.completionTime }}</span>
                                <strong>{{ textOrDash(commercialOfferModal.completion_time) }}</strong>
                            </div>
                            <div class="commercial-offer-row">
                                <span>{{ currentCopy.offerValidity }}</span>
                                <strong>{{ textOrDash(commercialOfferModal.offer_validity) }}</strong>
                            </div>
                        </template>
                        <div class="commercial-offer-row">
                            <span>{{ currentCopy.generalNote }}</span>
                            <strong>{{ textOrDash(commercialOfferModal.general_note) }}</strong>
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
                            :src="normalizeAttachmentUrl(currentAttachment?.url)"
                            :alt="`${currentCopy.file} ${attachmentIndex + 1}`"
                            class="gallery-image"
                        />
                        <div v-else class="gallery-file-fallback">
                            <p class="detail-inline-text detail-inline-text-long">{{ currentCopy.previewUnavailable }}</p>
                            <a
                                :href="normalizeAttachmentUrl(currentAttachment?.url)"
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
}

.back-button {
    text-decoration: none;
    background: #2563eb;
    color: #fff;
    box-shadow: 0 12px 24px rgba(37, 99, 235, 0.18);
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

.detail-table .col-requested-item {
    width: 330px;
}

.detail-table .col-supplier {
    width: 288px;
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
    white-space: nowrap;
    width: 100%;
}

.detail-supplier-head {
    display: grid;
    gap: 8px;
    min-width: 0;
}

.detail-supplier-head-top {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.detail-supplier-logo-wrap {
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
}

.detail-supplier-head-copy {
    min-width: 0;
    flex: 1 1 auto;
}

.detail-supplier-head-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.detail-supplier-head-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    padding: 0 10px;
    border: 1px solid rgba(37, 99, 235, 0.16);
    border-radius: 999px;
    background: rgba(239, 246, 255, 0.9);
    color: #1d4ed8;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1;
}

.detail-supplier-head-name {
    color: #0f172a;
    font-size: 0.86rem;
    font-weight: 700;
    line-height: 1.25;
    white-space: normal;
}

.detail-supplier-head-note {
    margin: 6px 0 0;
    color: #475569;
    font-size: 0.74rem;
    font-weight: 600;
    line-height: 1.35;
}

.detail-supplier-head-metrics {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(88px, 0.65fr);
    gap: 8px;
    padding: 10px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.92);
}

.detail-supplier-head-metrics.is-full-coverage,
.detail-supplier-head-metrics.is-coverage-leader {
    border-color: rgba(15, 118, 110, 0.2);
    background: linear-gradient(180deg, rgba(240, 253, 250, 0.98), rgba(255, 255, 255, 0.98));
}

.detail-supplier-head-metric {
    display: grid;
    grid-template-columns: max-content minmax(0, 1fr);
    gap: 3px 8px;
    align-items: center;
    min-width: 0;
    padding-bottom: 2px;
}

.detail-supplier-head-metric span {
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 600;
    line-height: 1.35;
    white-space: nowrap;
}

.detail-supplier-head-metric strong {
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 700;
    line-height: 1.3;
    white-space: nowrap;
    text-align: right;
}

.detail-supplier-head-metric.is-total span,
.detail-supplier-head-metric.is-total strong {
    text-align: left;
}

.detail-supplier-head-metric.is-quotation span,
.detail-supplier-head-metric.is-quotation strong {
    text-align: right;
}

.detail-supplier-head-metric.is-quotation {
    justify-self: end;
    width: 100%;
}

.requested-item-card {
    display: grid;
    gap: 10px;
    padding: 10px 12px 12px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    border-radius: 12px;
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.98), rgba(255, 255, 255, 0.98));
}

.requested-item-title {
    color: #0f172a;
    font-size: 0.88rem;
    font-weight: 700;
    line-height: 1.35;
}

.requested-item-meta {
    display: grid;
    gap: 6px;
}

.requested-item-line {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    color: #475569;
    font-size: 0.78rem;
    line-height: 1.35;
}

.requested-item-line strong {
    color: #0f172a;
    font-size: 0.79rem;
    font-weight: 700;
    text-align: right;
}

.requested-item-line.requested-item-line-wide {
    display: grid;
    gap: 4px;
}

.requested-item-line.requested-item-line-wide strong {
    text-align: left;
    white-space: normal;
}

.requested-item-file-button {
    font-size: 0.79rem;
    line-height: 1.35;
    font-weight: 700;
}

.selection-summary-row td {
    padding-top: 0;
    border-top: 0;
}

.split-suggestion-row td {
    padding-top: 8px;
    border-top: 0;
}

.compare-item-row td {
    padding-bottom: 0;
    border-bottom: 0;
}

.selection-summary-spacer {
    padding: 0 !important;
    border-top: 0 !important;
}

.selection-summary-strip {
    display: grid;
    gap: 10px;
    padding: 10px 10px 12px;
    margin-top: -1px;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.98), rgba(255, 255, 255, 0.98));
}

.selection-summary-title {
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 700;
    line-height: 1.25;
}

.selection-summary-strip-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.selection-summary-strip-item {
    display: grid;
    gap: 4px;
    min-width: 0;
}

.selection-summary-strip-item span {
    color: #475569;
    font-size: 0.76rem;
    line-height: 1.3;
}

.selection-summary-strip-item strong {
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 700;
    line-height: 1.35;
    word-break: break-word;
}

.selection-summary-strip-item.is-emphasis span,
.selection-summary-strip-item.is-emphasis strong {
    color: #0f766e;
}

.split-suggestion-panel {
    display: grid;
    gap: 12px;
    padding: 14px 14px 16px;
    border: 1px solid rgba(59, 130, 246, 0.16);
    border-radius: 14px;
    background: linear-gradient(180deg, rgba(239, 246, 255, 0.96), rgba(248, 250, 252, 0.98));
}

.split-suggestion-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
}

.split-suggestion-head-copy {
    display: grid;
    gap: 4px;
}

.split-suggestion-head strong {
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 700;
    line-height: 1.25;
}

.split-suggestion-head p {
    margin: 0;
    color: #475569;
    font-size: 0.76rem;
    line-height: 1.45;
}

.split-suggestion-close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 30px;
    padding: 0 11px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.92);
    color: #334155;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1;
    white-space: nowrap;
}

.split-suggestion-list {
    display: grid;
    gap: 10px;
}

.split-suggestion-card {
    display: grid;
    gap: 10px;
    padding: 12px;
    border: 1px solid rgba(148, 163, 184, 0.14);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.98);
}

.split-suggestion-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.split-suggestion-card-copy {
    display: grid;
    gap: 3px;
}

.split-suggestion-card-copy strong {
    color: #0f172a;
    font-size: 0.8rem;
    font-weight: 700;
    line-height: 1.3;
}

.split-suggestion-card-copy p {
    margin: 0;
    color: #475569;
    font-size: 0.75rem;
    line-height: 1.4;
}

.split-suggestion-apply {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 30px;
    padding: 0 11px;
    border: 1px solid rgba(37, 99, 235, 0.2);
    border-radius: 999px;
    background: rgba(239, 246, 255, 0.9);
    color: #1d4ed8;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1;
    white-space: nowrap;
}

.split-suggestion-apply.is-applied,
.split-suggestion-apply:disabled {
    border-color: rgba(15, 118, 110, 0.18);
    background: rgba(240, 253, 250, 0.92);
    color: #0f766e;
    cursor: default;
}

.split-suggestion-route {
    margin: 0;
    color: #0f172a;
    font-size: 0.8rem;
    font-weight: 700;
    line-height: 1.45;
}

.split-suggestion-metrics {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 14px;
    color: #334155;
    font-size: 0.76rem;
    line-height: 1.4;
}

.split-suggestion-metrics strong {
    color: #0f172a;
}

.overall-selection-total {
    margin-top: 18px;
}

.overall-selection-total-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 14px 16px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 12px;
    background: #fff;
}

.overall-selection-total-card span {
    color: #475569;
    font-size: 0.86rem;
    font-weight: 600;
    line-height: 1.35;
}

.overall-selection-total-card strong {
    color: #0f172a;
    font-size: 1rem;
    font-weight: 700;
    text-align: right;
}

.detail-supplier-offer-card {
    display: grid;
    gap: 10px;
    padding: 10px 10px 12px;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.16);
}

.detail-supplier-offer-title {
    color: #0f172a;
    font-size: 0.86rem;
    font-weight: 700;
    line-height: 1.35;
}

.detail-supplier-offer-card.is-mint {
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.98), rgba(255, 255, 255, 0.98));
}

.detail-supplier-offer-card.is-sky {
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.98), rgba(255, 255, 255, 0.98));
}

.detail-supplier-offer-meta {
    display: grid;
    gap: 6px;
}

.detail-supplier-offer-line {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    color: #475569;
    font-size: 0.78rem;
    line-height: 1.35;
}

.detail-supplier-offer-line strong {
    color: #0f172a;
    font-size: 0.8rem;
    font-weight: 700;
    text-align: right;
}

.detail-supplier-offer-extra {
    display: grid;
    gap: 4px;
    color: #334155;
    font-size: 0.77rem;
    line-height: 1.45;
}

.detail-supplier-offer-extra-line {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
}

.detail-supplier-offer-extra-line span {
    color: #334155;
}

.detail-supplier-offer-extra-line strong {
    color: #0f172a;
    font-size: 0.8rem;
    font-weight: 700;
    text-align: right;
}

.detail-supplier-offer-extra-line.is-long-value {
    display: grid;
    gap: 4px;
}

.detail-supplier-offer-extra-line.is-long-value strong {
    text-align: left;
    white-space: normal;
}

.detail-supplier-offer-files {
    line-height: 1.35;
}

.detail-supplier-file-button {
    font-size: 0.8rem;
    line-height: 1.35;
    font-weight: 700;
    text-align: right;
}

.detail-supplier-award-field {
    display: grid;
    gap: 6px;
    padding: 12px;
    border: 1px solid rgba(37, 99, 235, 0.24);
    border-radius: 10px;
    background: rgba(239, 246, 255, 0.9);
}

.detail-supplier-award-field .detail-inline-label {
    font-size: 12px;
    line-height: 1.2;
}

.detail-supplier-award-label {
    color: #1d4ed8;
}

.detail-supplier-award-input {
    width: 100%;
    min-height: 40px;
    height: 40px;
    padding: 0 12px;
    border-color: rgba(37, 99, 235, 0.38);
    background: #fff;
    box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.08);
    color: #0f172a;
    font-size: 0.96rem;
    font-weight: 700;
    line-height: 1.2;
}

.service-detail-supplier-card.is-selected {
    border-color: rgba(37, 99, 235, 0.28);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.08);
    background: linear-gradient(180deg, rgba(239, 246, 255, 0.94), rgba(255, 255, 255, 0.98));
}

.service-award-toggle-card {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #0f172a;
    font-size: 0.88rem;
    font-weight: 600;
}

.service-award-toggle-card input {
    width: 16px;
    height: 16px;
}

.detail-supplier-award-note {
    display: grid;
    gap: 6px;
}

.detail-supplier-award-note-input {
    width: 100%;
    min-height: 74px;
    padding: 10px 12px;
    border-color: rgba(37, 99, 235, 0.38);
    background: #fff;
    box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.08);
    color: #0f172a;
    font-size: 0.88rem;
    font-weight: 500;
    line-height: 1.45;
    resize: vertical;
}

.detail-supplier-award-empty {
    color: rgba(4, 21, 31, 0.52);
    font-size: 0.82rem;
    line-height: 1.4;
    min-height: 36px;
    display: flex;
    align-items: center;
}

.award-inline-error {
    margin: 0;
    color: #b91c1c;
    font-size: 0.74rem;
    line-height: 1.3;
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

.confirm-award-modal {
    width: min(920px, 100%);
}

.confirm-award-modal-copy {
    margin: 6px 0 0;
    color: #475569;
    font-size: 0.92rem;
    line-height: 1.45;
}

.confirm-award-modal-body {
    display: grid;
    gap: 18px;
}

.confirm-award-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.confirm-award-summary-card {
    display: grid;
    gap: 6px;
    padding: 14px 16px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 12px;
    background: #f8fafb;
}

.confirm-award-summary-card span {
    color: #64748b;
    font-size: 0.78rem;
    line-height: 1.3;
}

.confirm-award-summary-card strong {
    color: #0f172a;
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.35;
}

.confirm-award-supplier-stack {
    display: grid;
    gap: 14px;
}

.confirm-award-supplier-card {
    display: grid;
    gap: 12px;
    padding: 16px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 14px;
    background: #fff;
}

.confirm-award-supplier-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
}

.confirm-award-supplier-total {
    color: #0f172a;
    font-size: 0.96rem;
    font-weight: 700;
    text-align: right;
}

.confirm-award-line-list,
.confirm-award-warning-list {
    display: grid;
    gap: 10px;
}

.confirm-award-line-row,
.confirm-award-warning-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding-top: 10px;
    border-top: 1px solid rgba(226, 232, 240, 0.9);
}

.confirm-award-line-row:first-child,
.confirm-award-warning-row:first-child {
    padding-top: 0;
    border-top: 0;
}

.confirm-award-line-copy,
.confirm-award-line-meta {
    display: grid;
    gap: 4px;
    min-width: 0;
}

.confirm-award-line-copy strong,
.confirm-award-line-meta strong,
.confirm-award-warning-row strong {
    color: #0f172a;
    font-size: 0.86rem;
    font-weight: 700;
    line-height: 1.35;
}

.confirm-award-line-note {
    margin-top: 4px;
    color: rgba(4, 21, 31, 0.72);
    font-size: 0.8rem;
    line-height: 1.45;
}

.confirm-award-line-copy span,
.confirm-award-line-meta span,
.confirm-award-warning-row span {
    color: #64748b;
    font-size: 0.78rem;
    line-height: 1.35;
}

.confirm-award-line-meta {
    text-align: right;
}

.confirm-award-warning-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.confirm-award-warning-card {
    display: grid;
    gap: 10px;
    padding: 14px 16px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 12px;
    background: #fff;
}

.confirm-award-warning-card strong {
    color: #0f172a;
    font-size: 0.9rem;
    font-weight: 700;
    line-height: 1.35;
}

.confirm-award-warning-card.is-warning {
    border-color: rgba(13, 148, 136, 0.2);
    background: rgba(240, 253, 250, 0.96);
}

.confirm-award-warning-copy {
    display: grid;
    gap: 6px;
    min-width: 0;
}

.confirm-award-warning-suppliers {
    display: grid;
    gap: 4px;
}

.confirm-award-warning-suppliers span {
    color: #475569;
    font-size: 0.76rem;
    line-height: 1.35;
}

.confirm-award-modal-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 24px 22px;
    border-top: 1px solid rgba(4, 21, 31, 0.08);
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

.commercial-offer-modal {
    width: min(520px, 100%);
}

.commercial-offer-modal-body {
    padding-top: 20px;
}

.commercial-offer-grid {
    display: grid;
    gap: 10px;
}

.commercial-offer-row {
    display: grid;
    grid-template-columns: 140px minmax(0, 1fr);
    column-gap: 10px;
    align-items: start;
    color: #64748b;
    font-size: 0.84rem;
    line-height: 1.35;
}

.commercial-offer-row strong {
    color: #0f172a;
    font-size: 0.92rem;
    font-weight: 700;
    word-break: break-word;
    text-align: right;
}

.commercial-offer-row-grand {
    padding-top: 10px;
    border-top: 1px solid rgba(148, 163, 184, 0.16);
}

.commercial-offer-row-gap {
    padding-top: 10px;
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

.compare-notice {
    margin: 6px 0 0;
    max-width: 76ch;
}

.assist-bar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    margin: 18px 0 20px;
    padding: 14px 16px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    border-radius: 12px;
    background: #f8fafc;
}

.assist-bar-label {
    color: #0f172a;
    font-size: 0.84rem;
    font-weight: 700;
    line-height: 1.3;
    white-space: nowrap;
}

.assist-bar-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
}

.assist-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 0 12px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 999px;
    background: #fff;
    color: #334155;
    font-size: 0.78rem;
    font-weight: 700;
    line-height: 1;
}

.assist-button.is-active {
    border-color: rgba(15, 118, 110, 0.24);
    background: rgba(20, 184, 166, 0.12);
    color: #0f766e;
}

.assist-button.is-reset:disabled {
    opacity: 0.48;
}

.detail-supplier-offer-card.is-assist-highlighted {
    border-color: rgba(15, 118, 110, 0.28);
    box-shadow: 0 0 0 2px rgba(20, 184, 166, 0.1);
    background: linear-gradient(180deg, rgba(240, 253, 250, 0.98), rgba(255, 255, 255, 0.98));
}

.detail-supplier-offer-card.is-assist-muted {
    opacity: 0.46;
}

.assist-card-summary {
    display: grid;
    gap: 5px;
}

.assist-card-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: fit-content;
    min-height: 24px;
    padding: 0 9px;
    border-radius: 999px;
    background: rgba(20, 184, 166, 0.14);
    color: #0f766e;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1;
}

.assist-card-reason {
    margin: 0;
    color: #0f766e;
    font-size: 0.75rem;
    line-height: 1.35;
}

.section-heading-chips {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
}

.award-error-banner {
    margin-bottom: 18px;
    padding: 12px 14px;
    border: 1px solid rgba(239, 68, 68, 0.22);
    border-radius: 12px;
    background: rgba(254, 242, 242, 0.92);
    color: #b91c1c;
    font-size: 0.92rem;
    font-weight: 600;
}

.award-item-stack,
.service-award-stack {
    display: grid;
    gap: 18px;
}

.award-item-card,
.award-offer-card {
    padding: 18px 20px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 12px;
    background: #fff;
}

.award-item-head,
.award-offer-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
}

.award-item-title {
    margin: 4px 0 0;
    color: #0f172a;
    font-size: 1rem;
    font-weight: 700;
}

.award-item-metrics {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
}

.award-offer-list {
    display: grid;
    gap: 14px;
    margin-top: 16px;
}

.award-supplier {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.award-supplier-logo-wrap {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: #f8fafc;
    flex: 0 0 48px;
}

.award-supplier-logo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.award-supplier-name {
    color: #0f172a;
    font-size: 0.98rem;
    font-weight: 700;
}

.award-supplier-subtitle {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 0.88rem;
    line-height: 1.45;
}

.award-qty-field {
    width: 112px;
    display: grid;
    gap: 8px;
}

.award-qty-input {
    width: 100%;
    min-height: 38px;
    height: 38px;
}

.award-offer-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 14px;
    margin-top: 16px;
}

.award-offer-cell {
    display: grid;
    gap: 6px;
    min-width: 0;
}

.award-offer-notes {
    display: grid;
    gap: 12px;
    margin-top: 18px;
}

.service-award-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #0f172a;
    font-size: 0.9rem;
    font-weight: 600;
}

.offer-summary-rows {
    display: grid;
    gap: 10px;
    padding: 18px 20px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 14px;
    background: #f8fafc;
    margin-top: 16px;
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

.buyer-award-notes,
.service-offer-notes {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
    margin-top: 20px;
}

.buyer-award-notes .detail-inline-main,
.award-offer-notes .detail-inline-main {
    grid-template-columns: 170px minmax(0, 1fr);
    column-gap: 14px;
}

.award-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 22px;
}

.award-actions-right {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
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

@media (max-width: 1180px) {
    .info-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .confirm-award-summary-grid,
    .confirm-award-warning-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .award-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .award-offer-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 860px) {
    .hero-panel {
        flex-direction: column;
    }

    .assist-bar {
        flex-direction: column;
    }

    .assist-bar-actions {
        justify-content: flex-start;
    }

    .section-heading-chips {
        justify-content: flex-start;
    }

    .info-grid {
        grid-template-columns: 1fr;
    }

    .detail-supplier-head-metrics {
        grid-template-columns: 1fr;
    }

    .confirm-award-summary-grid,
    .confirm-award-warning-grid {
        grid-template-columns: 1fr;
    }

    .info-field-wide {
        grid-column: span 1;
    }

    .award-item-head,
    .award-offer-top {
        flex-direction: column;
    }

    .award-item-metrics {
        justify-content: flex-start;
    }

    .confirm-award-supplier-head,
    .confirm-award-line-row,
    .confirm-award-warning-row,
    .confirm-award-modal-actions {
        flex-direction: column;
        align-items: flex-start;
    }

    .confirm-award-line-meta {
        text-align: left;
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

    .hero-actions > * {
        width: 100%;
    }

    .award-offer-grid,
    .selection-summary-strip-grid {
        grid-template-columns: 1fr;
    }

    .award-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .award-actions-right {
        flex-direction: column;
        align-items: stretch;
        justify-content: flex-start;
    }

    .split-suggestion-card-head {
        flex-direction: column;
        align-items: stretch;
    }

    .split-suggestion-head {
        flex-direction: column;
        align-items: stretch;
    }

    .split-suggestion-apply {
        width: 100%;
    }

    .split-suggestion-close {
        width: 100%;
    }
}
</style>
