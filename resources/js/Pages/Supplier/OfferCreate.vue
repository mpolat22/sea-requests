<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainLayout from '../../Layouts/MainLayout.vue';
import RfqGeneralInformationSection from '../../Components/RfqGeneralInformationSection.vue';

const props = defineProps({
    rfq: {
        type: Object,
        required: true,
    },
    qualityOptions: {
        type: Array,
        default: () => [],
    },
    offer: {
        type: Object,
        default: () => ({}),
    },
    saveUrl: {
        type: String,
        required: true,
    },
    backUrl: {
        type: String,
        required: true,
    },
});

const copy = {
    eyebrow: 'Submit Offer',
    heroSparePartsTitle: 'Spare Parts Offer',
    heroServiceTitle: 'Service Request Offer',
    text: 'Review the published RFQ scope and prepare your offer details from this page.',
    back: 'Back to Request',
    general: 'General Information',
    items: 'Items to Quote',
    files: 'Files',
    titleLabel: 'Title',
    descriptionLabel: 'Description',
    fileAddedSingular: 'file added',
    fileAddedPlural: 'files added',
    previous: 'Previous',
    next: 'Next',
    openFile: 'Open file',
    previewUnavailable: 'Preview unavailable for this file type.',
    pricing: 'Pricing Summary',
    details: 'Main Items & Other Details',
    offerSummary: 'Offer Summary',
    offerQty: 'Offer Qty',
    unitPrice: 'Unit Price',
    totalPrice: 'Total Price',
    total: 'Total',
    leadTime: 'Delivery Time',
    completionTime: 'Completion Time',
    offerValidity: 'Offer Validity',
    offerQuality: 'Quality',
    brandNote: 'Manufacturer',
    remarks: 'Remarks',
    offerFiles: 'Files',
    serviceFiles: 'Files',
    requestFiles: 'Request Files',
    tax: 'Including Tax',
    taxAmount: 'Tax Amount',
    mobilization: 'Including Mobilization',
    mobilizationCost: 'Mobilization Cost',
    packing: 'Including Packing',
    packingCost: 'Packing Cost',
    freight: 'Including Freight',
    freightCost: 'Freight Cost',
    totalOfferAmount: 'Total Offer Amount',
    grandTotal: 'Grand Total',
    deliveryTerms: 'Delivery Terms',
    otherDeliveryTerms: 'Other Delivery Terms',
    awardScope: 'Award Scope',
    awardScopeHelper: 'Choose whether the buyer may split this spare parts quotation or must accept it as a full quoted scope.',
    partialAwardAccepted: 'Partial award accepted',
    partialAwardAcceptedHint: 'Buyer may select only some quoted lines or quantities from this offer.',
    fullQuotedScopeRequired: 'Full quoted scope required',
    fullQuotedScopeRequiredHint: 'Buyer must accept all quoted lines and quoted quantities together.',
    paymentTerms: 'Payment Terms',
    paymentOrderConfirmation: '% when order confirmation',
    paymentBeforeShipment: '% before shipment',
    paymentInvoiceDays: 'days from Invoice Date',
    otherPaymentTerms: 'Other Payment Terms',
    serviceClarification: 'Service Clarification',
    generalNote: 'General Note',
    select: 'Select',
    uploadFiles: 'Upload Files',
    generalNotePlaceholder: 'General notes for your offer...',
    serviceClarificationPlaceholder: 'Clarify scope, exclusions, attendance, or service method here...',
    offerValidityPlaceholder: 'Example: 7 days from quotation date',
    completionTimePlaceholder: 'Example: Within 12 hours after confirmation',
    additionalPlaceholder: 'Provide any additional information here...',
    draft: 'Save Draft',
    submit: 'Submit Offer',
    previewNotice: 'Review your offer details carefully before submission and make sure pricing, delivery, and payment terms are entered exactly as intended.',
    validationAtLeastOneItem: 'At least one RFQ item must be quoted before submission.',
    validationQtyRequired: 'Offer quantity is required.',
    validationQty: 'Offer quantity cannot exceed requested quantity.',
    validationPriceRequired: 'Unit price is required when offer quantity is entered.',
    validationPrice: 'Unit price must be greater than 0.',
    validationLeadTime: 'Lead time is required when quoting an item.',
    validationLeadTimePositive: 'Lead time must be at least 1 day.',
    validationQuality: 'Quality is required when quoting an item.',
    validationTotalPriceRequired: 'Total price is required before submission.',
    validationTotalPricePositive: 'Total price must be greater than 0.',
    validationCompletionTimeRequired: 'Completion time is required before submission.',
    validationOfferValidityRequired: 'Offer validity is required before submission.',
    validationCostRequired: 'This amount is required when not included.',
    validationCostPositive: 'This amount must be greater than 0.',
    validationPaymentTermsRequired: 'At least one payment term is required before submission.',
    validationPaymentPercentRange: 'This percentage must be between 0 and 100.',
    validationPaymentPercentTotal: 'Payment percentages cannot exceed 100 in total.',
    validationPaymentBalanceDetailsRequired: 'Explain the remaining balance with days from Invoice Date or Other Payment Terms.',
    selectedPorts: 'Selected Ports',
    selectedCountries: 'Selected Countries',
    allListedPortsIn: 'All listed ports in',
    portsSelectedSuffix: 'ports selected',
    close: 'Close',
    labels: {
        referenceNo: 'Reference No',
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
        open: 'Open',
        close: 'Close',
    },
    priority: {
        low: 'Low',
        normal: 'Normal',
        high: 'High',
        critical: 'Critical',
    },
    countriesSelected: 'countries selected',
    portsSelected: 'ports selected',
};

const currentCopy = computed(() => copy);
const formatOptionLabel = (value) => String(value ?? '')
    .split(/[_\s-]+/)
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
const isSpareParts = computed(() => props.rfq.request_type === 'spare_parts');
const heroTitle = computed(() => isSpareParts.value ? currentCopy.value.heroSparePartsTitle : currentCopy.value.heroServiceTitle);
const tableViewClass = 'is-compact';

const normalizedWholeNumberString = (value) => {
    const raw = `${value ?? ''}`.trim();

    if (raw === '') {
        return '';
    }

    const match = raw.match(/(\d+)/);

    if (!match) {
        return '';
    }

    return `${Math.max(parseInt(match[1], 10), 0)}`;
};

const generalInformationFields = computed(() => [
    {
        key: 'reference_no',
        label: currentCopy.value.labels.referenceNo,
        value: props.rfq.reference_no || '-',
    },
    {
        key: 'country',
        label: currentCopy.value.labels.country,
        value: countrySummary.value,
        clickable: true,
        action: 'countries',
    },
    {
        key: 'ports',
        label: currentCopy.value.labels.ports,
        value: portsSummary.value,
        clickable: true,
        action: 'ports',
    },
    {
        key: 'status',
        label: currentCopy.value.labels.status,
        value: currentCopy.value.statuses[props.rfq.status] || props.rfq.status || '-',
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
        value: props.rfq.general_notes || '-',
        wide: true,
        long: true,
    },
]);

const form = useForm({
    intent: 'draft',
    items: (props.rfq.items ?? []).map((item) => ({
        id: item.id,
        offer_qty: item.offer?.offer_qty ?? '',
        unit_price: item.offer?.unit_price ?? '',
        lead_time: normalizedWholeNumberString(item.offer?.delivery_time),
        quality: item.offer?.quality ?? '',
        brand_note: item.offer?.manufacturer ?? '',
        remarks: item.offer?.remarks ?? '',
        files: item.offer?.attachments ?? [],
    })),
    service_total_price: props.offer?.service_total_price ?? '',
    completion_time: props.offer?.completion_time ?? '',
    offer_validity: props.offer?.offer_validity ?? '',
    service_files: props.offer?.attachments ?? [],
    including_tax: props.offer?.including_tax ?? true,
    tax_amount: props.offer?.tax_amount ?? '',
    including_mobilization: props.offer?.including_mobilization ?? true,
    mobilization_cost: props.offer?.mobilization_cost ?? '',
    including_packing: props.offer?.including_packing ?? true,
    packing_cost: props.offer?.packing_cost ?? '',
    including_freight: props.offer?.including_freight ?? true,
    freight_cost: props.offer?.freight_cost ?? '',
    delivery_terms: props.offer?.delivery_terms ?? '',
    other_delivery_terms: props.offer?.other_delivery_terms ?? '',
    award_scope_policy: props.offer?.award_scope_policy ?? (props.rfq.request_type === 'spare_parts' ? 'partial_allowed' : 'full_scope_required'),
    payment_order_confirmation: props.offer?.payment_order_confirmation ?? '',
    payment_before_shipment: props.offer?.payment_before_shipment ?? '',
    payment_invoice_days: props.offer?.payment_invoice_days ?? '',
    other_payment_terms: props.offer?.other_payment_terms ?? '',
    service_clarification: props.offer?.service_clarification ?? '',
    general_note: props.offer?.general_note ?? '',
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

const numericValue = (value) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

const normalizedDecimalString = (value) => {
    const raw = `${value ?? ''}`.trim();

    if (raw === '') {
        return '';
    }

    const parsed = Number(raw.replace(',', '.'));

    if (!Number.isFinite(parsed)) {
        return raw;
    }

    const rounded = Math.round((parsed + Number.EPSILON) * 100) / 100;
    return `${rounded}`;
};

const normalizeFormDecimalField = (field) => {
    form[field] = normalizedDecimalString(form[field]);
};

const normalizeFormWholeNumberField = (field) => {
    form[field] = normalizedWholeNumberString(form[field]);
};

const normalizeItemDecimalField = (index, field) => {
    form.items[index][field] = normalizedDecimalString(form.items[index][field]);
};

const normalizeItemWholeNumberField = (index, field) => {
    form.items[index][field] = normalizedWholeNumberString(form.items[index][field]);
};

const offerLineTotal = (index) => numericValue(form.items[index]?.offer_qty) * numericValue(form.items[index]?.unit_price);
const totalOfferAmount = computed(() => (
    isSpareParts.value
        ? form.items.reduce((sum, _, index) => sum + offerLineTotal(index), 0)
        : numericValue(form.service_total_price)
));
const taxAmountValue = computed(() => form.including_tax ? 0 : numericValue(form.tax_amount));
const mobilizationCostValue = computed(() => form.including_mobilization ? 0 : numericValue(form.mobilization_cost));
const packingCostValue = computed(() => form.including_packing ? 0 : numericValue(form.packing_cost));
const freightCostValue = computed(() => form.including_freight ? 0 : numericValue(form.freight_cost));
const grandTotal = computed(() => (
    isSpareParts.value
        ? totalOfferAmount.value + taxAmountValue.value + packingCostValue.value + freightCostValue.value
        : totalOfferAmount.value + taxAmountValue.value + mobilizationCostValue.value
));
const detailModal = ref(null);
const attachmentViewer = ref(null);
const attachmentIndex = ref(0);
const expandedItems = ref({});
const touchedItemFields = ref({});
const fileInputRefs = ref({});
const offerQtyInputRefs = ref({});
const serviceFileInputRef = ref(null);
const pricingValidationActive = ref(false);
const statusTone = (status) => {
    if (status === 'open') return 'is-open';
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

const countrySummary = computed(() => {
    return `${selectedCountryCount.value} ${currentCopy.value.countriesSelected}`;
});

const portsSummary = computed(() => {
    return `${selectedPortCount.value} ${currentCopy.value.portsSelected}`;
});

const openDetailModal = (type) => {
    detailModal.value = type;
};

const closeDetailModal = () => {
    detailModal.value = null;
};

const detailModalTitle = computed(() => (
    detailModal.value === 'countries'
        ? currentCopy.value.selectedCountries
        : currentCopy.value.selectedPorts
));

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

const formatTitleCaseValue = (value) => {
    const normalized = `${value ?? ''}`.trim();
    if (!normalized) return '-';
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
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

const toggleItemDetails = (itemId) => {
    expandedItems.value = {
        ...expandedItems.value,
        [itemId]: !expandedItems.value[itemId],
    };
};

const isItemExpanded = (itemId) => !!expandedItems.value[itemId];
const setFileInputRef = (index) => (el) => {
    if (el) {
        fileInputRefs.value[index] = el;
    } else {
        delete fileInputRefs.value[index];
    }
};
const setOfferQtyInputRef = (index) => (el) => {
    if (el) {
        offerQtyInputRefs.value[index] = el;
    } else {
        delete offerQtyInputRefs.value[index];
    }
};
const openFilePicker = (index) => {
    fileInputRefs.value[index]?.click();
};
const setServiceFileInputRef = (el) => {
    serviceFileInputRef.value = el;
};
const openServiceFilePicker = () => {
    serviceFileInputRef.value?.click();
};
const handleFiles = (event, index) => {
    const incomingFiles = Array.from(event.target.files ?? []);
    const currentFiles = Array.isArray(form.items[index].files) ? form.items[index].files : [];
    const mergedFiles = [...currentFiles];

    incomingFiles.forEach((file) => {
        const exists = mergedFiles.some((existing) => {
            if (existing instanceof File) {
                return existing.name === file.name
                    && existing.size === file.size
                    && existing.lastModified === file.lastModified;
            }

            return Number(existing?.id ?? 0) === 0
                && existing?.name === file.name
                && Number(existing?.size ?? 0) === file.size;
        });

        if (!exists) {
            mergedFiles.push(file);
        }
    });

    form.items[index].files = mergedFiles;
    event.target.value = '';
};
const removeFile = (itemIndex, fileIndex) => {
    form.items[itemIndex].files = (form.items[itemIndex].files ?? []).filter((_, index) => index !== fileIndex);
};
const handleServiceFiles = (event) => {
    const incomingFiles = Array.from(event.target.files ?? []);
    const currentFiles = Array.isArray(form.service_files) ? form.service_files : [];
    const mergedFiles = [...currentFiles];

    incomingFiles.forEach((file) => {
        const exists = mergedFiles.some((existing) => {
            if (existing instanceof File) {
                return existing.name === file.name
                    && existing.size === file.size
                    && existing.lastModified === file.lastModified;
            }

            return Number(existing?.id ?? 0) === 0
                && existing?.name === file.name
                && Number(existing?.size ?? 0) === file.size;
        });

        if (!exists) {
            mergedFiles.push(file);
        }
    });

    form.service_files = mergedFiles;
    event.target.value = '';
};
const removeServiceFile = (fileIndex) => {
    form.service_files = (form.service_files ?? []).filter((_, index) => index !== fileIndex);
};
const fileTriggerLabel = (item) => {
    const count = Array.isArray(item.files) ? item.files.length : 0;

    if (count === 0) {
        return currentCopy.value.uploadFiles;
    }

    return `${count} file${count === 1 ? '' : 's'} selected`;
};
const serviceFileTriggerLabel = computed(() => {
    const count = Array.isArray(form.service_files) ? form.service_files.length : 0;

    if (count === 0) {
        return currentCopy.value.uploadFiles;
    }

    return `${count} file${count === 1 ? '' : 's'} selected`;
});
const markItemFieldTouched = (index, field) => {
    touchedItemFields.value = {
        ...touchedItemFields.value,
        [`${index}.${field}`]: true,
    };
};
const isItemFieldTouched = (index, field) => Boolean(touchedItemFields.value[`${index}.${field}`]);
const serverItemError = (index, field) => form.errors[`items.${index}.${field}`] ?? '';
const visibleItemError = (index, field) => itemError(index, field) || serverItemError(index, field);
const serviceTotalPriceError = computed(() => {
    const clientError = pricingValidationActive.value ? serviceTotalPriceFieldError() : '';
    return clientError || form.errors.service_total_price || '';
});
const serviceCompletionTimeError = computed(() => {
    const clientError = pricingValidationActive.value ? serviceCompletionTimeFieldError() : '';
    return clientError || form.errors.completion_time || '';
});
const serviceOfferValidityError = computed(() => {
    const clientError = pricingValidationActive.value ? serviceOfferValidityFieldError() : '';
    return clientError || form.errors.offer_validity || '';
});
const pricingFieldMessage = (included, value, field) => {
    const clientError = pricingValidationActive.value ? pricingFieldError(included, value) : '';
    return clientError || form.errors[field] || '';
};
const paymentPercentValue = (field) => {
    const raw = `${form[field] ?? ''}`.trim();

    if (raw === '') {
        return 0;
    }

    return Math.max(numericValue(raw), 0);
};
const paymentInvoiceDaysValue = () => {
    const raw = `${form.payment_invoice_days ?? ''}`.trim();

    if (raw === '') {
        return 0;
    }

    return Math.max(Number.parseInt(raw, 10) || 0, 0);
};
const paymentPercentFieldError = (field) => {
    const raw = `${form[field] ?? ''}`.trim();

    if (raw === '') {
        return '';
    }

    const value = paymentPercentValue(field);
    return value > 100 ? currentCopy.value.validationPaymentPercentRange : '';
};
const paymentTermsSectionClientError = () => {
    const orderConfirmation = paymentPercentValue('payment_order_confirmation');
    const beforeShipment = paymentPercentValue('payment_before_shipment');
    const invoiceDays = paymentInvoiceDaysValue();
    const otherTerms = `${form.other_payment_terms ?? ''}`.trim();
    const hasPercentTerm = orderConfirmation > 0 || beforeShipment > 0;
    const hasInvoiceDays = invoiceDays > 0;
    const hasOtherTerms = otherTerms !== '';

    if (!hasPercentTerm && !hasInvoiceDays && !hasOtherTerms) {
        return currentCopy.value.validationPaymentTermsRequired;
    }

    if (orderConfirmation + beforeShipment > 100) {
        return currentCopy.value.validationPaymentPercentTotal;
    }

    if (orderConfirmation + beforeShipment > 0 && orderConfirmation + beforeShipment < 100 && !hasInvoiceDays && !hasOtherTerms) {
        return currentCopy.value.validationPaymentBalanceDetailsRequired;
    }

    return '';
};
const paymentOrderConfirmationError = computed(() => {
    const clientError = pricingValidationActive.value ? paymentPercentFieldError('payment_order_confirmation') : '';
    return clientError || form.errors.payment_order_confirmation || '';
});
const paymentBeforeShipmentError = computed(() => {
    const clientError = pricingValidationActive.value ? paymentPercentFieldError('payment_before_shipment') : '';
    return clientError || form.errors.payment_before_shipment || '';
});
const paymentInvoiceDaysError = computed(() => form.errors.payment_invoice_days || '');
const otherPaymentTermsError = computed(() => form.errors.other_payment_terms || '');
const paymentTermsSectionError = computed(() => {
    const clientError = pricingValidationActive.value ? paymentTermsSectionClientError() : '';
    return clientError || form.errors.payment_terms || '';
});

const hasOfferRowDetail = (offerItem) => {
    if (!offerItem) return false;

    return [
        offerItem.lead_time,
        offerItem.quality,
        offerItem.brand_note,
        offerItem.remarks,
    ].some((value) => `${value ?? ''}`.trim() !== '')
        || ((offerItem.files ?? []).length > 0);
};

const hasOfferRowStarted = (offerItem) => {
    if (!offerItem) return false;

    return `${offerItem.offer_qty ?? ''}`.trim() !== ''
        || `${offerItem.unit_price ?? ''}`.trim() !== ''
        || hasOfferRowDetail(offerItem);
};
const hasServiceOfferStarted = computed(() => {
    return `${form.service_total_price ?? ''}`.trim() !== ''
        || `${form.completion_time ?? ''}`.trim() !== ''
        || `${form.offer_validity ?? ''}`.trim() !== ''
        || `${form.service_clarification ?? ''}`.trim() !== ''
        || `${form.general_note ?? ''}`.trim() !== ''
        || ((form.service_files ?? []).length > 0);
});

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

const itemError = (index, field) => {
    const requestItem = props.rfq.items[index];
    const offerItem = form.items[index];

    if (!requestItem || !offerItem) return '';

    const offerQtyValue = `${offerItem.offer_qty}`.trim();
    const unitPriceValue = `${offerItem.unit_price}`.trim();
    const hasExtraDetail = hasOfferRowDetail(offerItem);

    if (field === 'offer_qty') {
        if (offerQtyValue === '' && (unitPriceValue !== '' || hasExtraDetail)) {
            return currentCopy.value.validationQtyRequired;
        }

        const requestedQty = numericValue(requestItem.quantity);
        const offeredQty = numericValue(offerItem.offer_qty);

        if (offerQtyValue !== '' && offeredQty > requestedQty) {
            return currentCopy.value.validationQty;
        }
    }

    if (field === 'unit_price') {
        if ((offerQtyValue !== '' || hasExtraDetail) && unitPriceValue === '' && (isItemFieldTouched(index, 'offer_qty') || isItemFieldTouched(index, 'unit_price') || hasExtraDetail)) {
            return currentCopy.value.validationPriceRequired;
        }

        if (unitPriceValue !== '' && numericValue(offerItem.unit_price) <= 0) {
            return currentCopy.value.validationPrice;
        }
    }

    if (field === 'lead_time') {
        const leadTimeValue = `${offerItem.lead_time ?? ''}`.trim();

        if ((offerQtyValue !== '' || unitPriceValue !== '' || hasExtraDetail) && leadTimeValue === '' && (isItemFieldTouched(index, 'offer_qty') || isItemFieldTouched(index, 'unit_price') || isItemFieldTouched(index, 'lead_time') || hasExtraDetail)) {
            return currentCopy.value.validationLeadTime;
        }

        if (leadTimeValue !== '' && Number.parseInt(leadTimeValue, 10) <= 0) {
            return currentCopy.value.validationLeadTimePositive;
        }
    }

    return '';
};

const pricingFieldError = (included, value) => {
    if (included) {
        return '';
    }

    const normalized = `${value}`.trim();

    if (normalized === '') {
        return currentCopy.value.validationCostRequired;
    }

    if (numericValue(value) <= 0) {
        return currentCopy.value.validationCostPositive;
    }

    return '';
};

const serviceTotalPriceFieldError = () => {
    const normalized = `${form.service_total_price ?? ''}`.trim();

    if (normalized === '') {
        return currentCopy.value.validationTotalPriceRequired;
    }

    if (numericValue(form.service_total_price) <= 0) {
        return currentCopy.value.validationTotalPricePositive;
    }

    return '';
};

const serviceCompletionTimeFieldError = () => {
    if (`${form.completion_time ?? ''}`.trim() === '') {
        return currentCopy.value.validationCompletionTimeRequired;
    }

    return '';
};

const serviceOfferValidityFieldError = () => {
    if (`${form.offer_validity ?? ''}`.trim() === '') {
        return currentCopy.value.validationOfferValidityRequired;
    }

    return '';
};

const syncPricingFieldError = (includedField, amountField) => {
    const included = Boolean(form[includedField]);
    const value = form[amountField];
    const clientError = pricingValidationActive.value ? pricingFieldError(included, value) : '';

    if (clientError) {
        form.setError(amountField, clientError);
        return;
    }

    form.clearErrors(amountField);
};

const handlePricingToggle = (includedField, amountField) => {
    if (form[includedField]) {
        form[amountField] = '';
        form.clearErrors(amountField);
        return;
    }

    syncPricingFieldError(includedField, amountField);
};

const handlePricingInput = (includedField, amountField) => {
    syncPricingFieldError(includedField, amountField);
};

const handlePaymentTermInput = () => {
    form.clearErrors(
        'payment_terms',
        'payment_order_confirmation',
        'payment_before_shipment',
        'payment_invoice_days',
        'other_payment_terms',
    );
};

const markPricingFieldsTouchedForSubmit = () => {
    pricingValidationActive.value = true;
    if (!form.including_tax && `${form.tax_amount}`.trim() === '') {
        form.setError('tax_amount', currentCopy.value.validationCostRequired);
    }
    if (isSpareParts.value) {
        if (!form.including_packing && `${form.packing_cost}`.trim() === '') {
            form.setError('packing_cost', currentCopy.value.validationCostRequired);
        }
        if (!form.including_freight && `${form.freight_cost}`.trim() === '') {
            form.setError('freight_cost', currentCopy.value.validationCostRequired);
        }
    } else if (!form.including_mobilization && `${form.mobilization_cost}`.trim() === '') {
        form.setError('mobilization_cost', currentCopy.value.validationCostRequired);
    }
};

const scrollToFirstError = async () => {
    await nextTick();

    if (form.errors.items && isSpareParts.value && props.rfq.items?.length) {
        const firstItemId = props.rfq.items[0]?.id;

        if (firstItemId && !isItemExpanded(firstItemId)) {
            expandedItems.value = {
                ...expandedItems.value,
                [firstItemId]: true,
            };
            await nextTick();
        }

        const firstOfferQtyInput = offerQtyInputRefs.value[0];
        if (firstOfferQtyInput) {
            firstOfferQtyInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            try {
                firstOfferQtyInput.focus({ preventScroll: true });
            } catch {
                firstOfferQtyInput.focus();
            }
            return;
        }
    }

    const selectors = [
        '.preview-error',
        '.offer-input.is-error',
        '.offer-select.is-error',
        '.offer-textarea.is-error',
        '.field-error:not(.pricing-field-error.is-hidden)',
    ];

    for (const selector of selectors) {
        const target = document.querySelector(selector);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if ('focus' in target && typeof target.focus === 'function') {
                try {
                    target.focus({ preventScroll: true });
                } catch {
                    target.focus();
                }
            }
            return;
        }
    }
};

const submitOfferForm = (intent) => {
    form.clearErrors();
    form.intent = intent;
    pricingValidationActive.value = intent === 'submit';

    if (isSpareParts.value) {
        form.items.forEach((_, index) => {
            normalizeItemDecimalField(index, 'offer_qty');
            normalizeItemDecimalField(index, 'unit_price');
            normalizeItemWholeNumberField(index, 'lead_time');
        });
        normalizeFormDecimalField('tax_amount');
        normalizeFormDecimalField('packing_cost');
        normalizeFormDecimalField('freight_cost');
    } else {
        normalizeFormDecimalField('service_total_price');
        normalizeFormDecimalField('tax_amount');
        normalizeFormDecimalField('mobilization_cost');
    }
    normalizeFormDecimalField('payment_order_confirmation');
    normalizeFormDecimalField('payment_before_shipment');
    normalizeFormWholeNumberField('payment_invoice_days');

    if (intent === 'submit') {
        let hasClientItemError = false;
        let hasClientPricingError = false;
        let hasClientPaymentError = false;

        if (isSpareParts.value) {
            form.items.forEach((_, index) => {
                markItemFieldTouched(index, 'offer_qty');
                markItemFieldTouched(index, 'unit_price');
                markItemFieldTouched(index, 'lead_time');
            });

            const quotedRows = form.items.filter((item) => `${item.offer_qty}`.trim() !== '' && `${item.unit_price}`.trim() !== '');
            const startedRows = form.items.filter((item) => hasOfferRowStarted(item));

            if (!quotedRows.length && !startedRows.length) {
                form.setError('items', currentCopy.value.validationAtLeastOneItem);
            }

            hasClientItemError = form.items.some((_, index) => visibleItemError(index, 'offer_qty') || visibleItemError(index, 'unit_price'));
            hasClientPricingError = Boolean(
                pricingFieldMessage(form.including_tax, form.tax_amount, 'tax_amount')
                || pricingFieldMessage(form.including_packing, form.packing_cost, 'packing_cost')
                || pricingFieldMessage(form.including_freight, form.freight_cost, 'freight_cost')
            );
        } else {
            const serviceTotalError = serviceTotalPriceFieldError();
            const completionTimeError = serviceCompletionTimeFieldError();
            const offerValidityError = serviceOfferValidityFieldError();

            if (serviceTotalError) {
                form.setError('service_total_price', serviceTotalError);
            }

            if (completionTimeError) {
                form.setError('completion_time', completionTimeError);
            }

            if (offerValidityError) {
                form.setError('offer_validity', offerValidityError);
            }

            hasClientItemError = Boolean(
                serviceTotalPriceError.value
                || serviceCompletionTimeError.value
                || serviceOfferValidityError.value
            );
            hasClientPricingError = Boolean(
                pricingFieldMessage(form.including_tax, form.tax_amount, 'tax_amount')
                || pricingFieldMessage(form.including_mobilization, form.mobilization_cost, 'mobilization_cost')
            );
        }

        hasClientPaymentError = Boolean(
            paymentOrderConfirmationError.value
            || paymentBeforeShipmentError.value
            || paymentInvoiceDaysError.value
            || otherPaymentTermsError.value
            || paymentTermsSectionError.value
        );

        markPricingFieldsTouchedForSubmit();

        if (form.errors.items || hasClientItemError || hasClientPricingError || hasClientPaymentError) {
            scrollToFirstError();
            return;
        }
    }

    form
        .transform((data) => ({
            ...data,
            items: data.items.map((item) => ({
                ...item,
                existing_attachment_ids: (item.files ?? [])
                    .filter((file) => !(file instanceof File) && Number(file?.id ?? 0) > 0)
                    .map((file) => file.id),
                files: (item.files ?? []).filter((file) => file instanceof File),
            })),
            existing_offer_attachment_ids: (data.service_files ?? [])
                .filter((file) => !(file instanceof File) && Number(file?.id ?? 0) > 0)
                .map((file) => file.id),
            service_files: (data.service_files ?? []).filter((file) => file instanceof File),
        }))
        .post(props.saveUrl, {
            preserveScroll: true,
            forceFormData: true,
            onError: () => {
                scrollToFirstError();
            },
        });
};

const saveDraft = () => submitOfferForm('draft');
const submitOffer = () => submitOfferForm('submit');

const money = (value) => `${props.rfq.currency || 'USD'} ${Number(value || 0).toFixed(2)}`;
const amountOnly = (value) => Number(value || 0).toFixed(2);
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
                            {{ currentCopy.statuses[rfq.status] || rfq.status || '-' }}
                        </span>
                    </div>
                </div>

                <div class="hero-actions">
                    <Link :href="backUrl" class="back-button">{{ currentCopy.back }}</Link>
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
                        <table :class="['detail-table', 'offer-table', tableViewClass]">
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
                                <template v-for="(item, index) in rfq.items" :key="item.id">
                                    <tr :class="{ 'item-master-row-expanded': isItemExpanded(item.id) }">
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
                                        <td colspan="11" class="item-detail-expanded-cell">
                                            <div class="item-detail-context-band">
                                                <div :class="['item-context-row', tableViewClass]">
                                                    <div class="detail-inline-main detail-inline-main-wide">
                                                        <strong class="detail-inline-label">{{ currentCopy.table.comments }}:</strong>
                                                        <div class="detail-inline-text">{{ item.comments || '-' }}</div>
                                                    </div>
                                                    <div class="detail-inline-main detail-inline-main-wide">
                                                        <strong class="detail-inline-label">{{ currentCopy.requestFiles }}:</strong>
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
                                            <div class="item-offer-fields-shell">
                                            <div :class="['item-detail-grid', 'item-detail-grid-offer', tableViewClass]">
                                                <div class="item-detail-block item-detail-block-offer">
                                                    <div class="item-detail-form-field">
                                                        <strong class="detail-inline-label">{{ currentCopy.offerQty }}:</strong>
                                                        <div class="item-detail-input-wrap">
                                                            <input :ref="setOfferQtyInputRef(index)" v-model="form.items[index].offer_qty" type="number" min="0" step="0.01" class="offer-input compact-offer-input offer-qty-input" :class="{ 'is-error': visibleItemError(index, 'offer_qty') }" @blur="normalizeItemDecimalField(index, 'offer_qty'); markItemFieldTouched(index, 'offer_qty')">
                                                            <p v-if="visibleItemError(index, 'offer_qty')" class="field-error">{{ visibleItemError(index, 'offer_qty') }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="item-detail-block item-detail-block-offer">
                                                    <div class="item-detail-form-field">
                                                        <strong class="detail-inline-label">{{ currentCopy.unitPrice }}:</strong>
                                                        <div class="item-detail-input-wrap">
                                                            <input v-model="form.items[index].unit_price" type="number" min="0" step="0.01" class="offer-input compact-offer-input unit-price-input" :class="{ 'is-error': visibleItemError(index, 'unit_price') }" @blur="normalizeItemDecimalField(index, 'unit_price'); markItemFieldTouched(index, 'unit_price')">
                                                            <p v-if="visibleItemError(index, 'unit_price')" class="field-error">{{ visibleItemError(index, 'unit_price') }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="item-detail-block item-detail-block-offer">
                                                    <div class="item-detail-form-field item-detail-static-field">
                                                        <strong class="detail-inline-label">{{ currentCopy.total }}:</strong>
                                                        <input
                                                            :value="amountOnly(offerLineTotal(index))"
                                                            type="text"
                                                            class="offer-input compact-offer-input offer-readonly-input"
                                                            readonly
                                                        >
                                                    </div>
                                                </div>
                                                <div class="item-detail-block item-detail-block-offer">
                                                    <div class="item-detail-form-field item-detail-static-field">
                                                        <strong class="detail-inline-label">{{ currentCopy.labels.currency }}:</strong>
                                                        <input
                                                            :value="rfq.currency || '-'"
                                                            type="text"
                                                            class="offer-input compact-offer-input offer-readonly-input"
                                                            readonly
                                                        >
                                                    </div>
                                                </div>
                                                <div class="item-detail-block item-detail-block-offer">
                                                    <div class="item-detail-form-field">
                                                        <strong class="detail-inline-label">{{ currentCopy.leadTime }}:</strong>
                                                        <div class="item-detail-input-wrap">
                                                            <input
                                                                v-model="form.items[index].lead_time"
                                                                type="number"
                                                                min="1"
                                                                step="1"
                                                                inputmode="numeric"
                                                                class="offer-input compact-offer-input lead-time-input"
                                                                :class="{ 'is-error': visibleItemError(index, 'lead_time') }"
                                                                placeholder="Days"
                                                                @blur="normalizeItemWholeNumberField(index, 'lead_time'); markItemFieldTouched(index, 'lead_time')"
                                                            >
                                                            <p v-if="visibleItemError(index, 'lead_time')" class="field-error">{{ visibleItemError(index, 'lead_time') }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="item-detail-block item-detail-block-offer">
                                                    <div class="item-detail-form-field">
                                                        <strong class="detail-inline-label">{{ currentCopy.offerQuality }}:</strong>
                                                        <div class="item-detail-input-wrap">
                                                            <select v-model="form.items[index].quality" class="offer-select compact-offer-input quality-offer-select">
                                                                <option value="">{{ currentCopy.select }}</option>
                                                                <option v-for="quality in props.qualityOptions" :key="`offer-quality-${quality}`" :value="quality">
                                                                    {{ formatOptionLabel(quality) }}
                                                                </option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="item-detail-block item-detail-block-offer">
                                                    <div class="item-detail-form-field">
                                                        <strong class="detail-inline-label">{{ currentCopy.brandNote }}:</strong>
                                                        <div class="item-detail-input-wrap">
                                                            <input v-model="form.items[index].brand_note" type="text" class="offer-input compact-offer-input wide-offer-input brand-note-input">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="item-detail-block item-detail-block-offer">
                                                    <div class="item-detail-form-field">
                                                        <strong class="detail-inline-label">{{ currentCopy.remarks }}:</strong>
                                                        <div class="item-detail-input-wrap">
                                                            <input v-model="form.items[index].remarks" type="text" class="offer-input compact-offer-input wide-offer-input remarks-input">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="item-detail-block item-detail-block-offer">
                                                    <div class="item-detail-form-field">
                                                        <strong class="detail-inline-label">{{ currentCopy.offerFiles }}:</strong>
                                                        <div class="file-upload-field">
                                                            <button type="button" class="file-upload-trigger compact-offer-input" @click="openFilePicker(index)">
                                                                {{ fileTriggerLabel(form.items[index]) }}
                                                            </button>
                                                            <input
                                                                :ref="setFileInputRef(index)"
                                                                class="file-upload-input"
                                                                type="file"
                                                                multiple
                                                                @change="handleFiles($event, index)"
                                                            />
                                                            <div v-if="form.items[index].files?.length" class="file-chip-list">
                                                                <div v-for="(file, fileIndex) in form.items[index].files" :key="`${file.id ?? file.name}-${fileIndex}`" class="file-chip">
                                                                    <span class="file-chip-name">{{ file.name }}</span>
                                                                    <button type="button" class="file-chip-remove" @click="removeFile(index, fileIndex)">&times;</button>
                                                                </div>
                                                            </div>
                                                        </div>
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
                                    <div class="detail-inline-text detail-inline-text-long">{{ rfq.service_description || '-' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="service-block">
                            <div class="detail-inline-value">
                                <div class="detail-inline-main detail-inline-main-wide">
                                    <strong class="detail-inline-label">{{ currentCopy.requestFiles }}:</strong>
                                    <div class="detail-inline-text detail-inline-text-long">
                                        <button
                                            v-if="rfq.attachments?.length"
                                            type="button"
                                            class="file-preview-button"
                                            @click="openAttachmentViewer(rfq.attachments)"
                                        >
                                            {{ fileButtonLabel(rfq.attachments) }}
                                        </button>
                                        <span v-else>-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="!isSpareParts" class="surface-card section-card combined-detail-section">
                <div class="subsection-surface">
                    <div class="section-heading">
                        <h2 class="directory-section-title">{{ currentCopy.offerSummary }}</h2>
                    </div>

                    <div class="service-offer-grid">
                        <div class="form-block">
                            <label class="detail-inline-label">{{ currentCopy.totalPrice }}</label>
                            <input v-model="form.service_total_price" type="number" min="0" step="0.01" class="offer-input service-summary-input" :class="{ 'is-error': serviceTotalPriceError }" @input="form.clearErrors('service_total_price')" @blur="normalizeFormDecimalField('service_total_price')">
                            <p class="field-error service-summary-error" :class="{ 'is-hidden': !serviceTotalPriceError }">{{ serviceTotalPriceError || ' ' }}</p>
                        </div>
                        <div class="form-block">
                            <label class="detail-inline-label">{{ currentCopy.labels.currency }}</label>
                            <input :value="rfq.currency || '-'" type="text" class="offer-input service-summary-input offer-readonly-input" readonly>
                            <p class="field-error service-summary-error is-hidden"> </p>
                        </div>
                        <div class="form-block">
                            <label class="detail-inline-label">{{ currentCopy.completionTime }}</label>
                            <input v-model="form.completion_time" type="text" class="offer-input service-summary-input" :class="{ 'is-error': serviceCompletionTimeError }" :placeholder="currentCopy.completionTimePlaceholder" @input="form.clearErrors('completion_time')">
                            <p class="field-error service-summary-error" :class="{ 'is-hidden': !serviceCompletionTimeError }">{{ serviceCompletionTimeError || ' ' }}</p>
                        </div>
                        <div class="form-block">
                            <label class="detail-inline-label">{{ currentCopy.offerValidity }}</label>
                            <input v-model="form.offer_validity" type="text" class="offer-input service-summary-input" :class="{ 'is-error': serviceOfferValidityError }" :placeholder="currentCopy.offerValidityPlaceholder" @input="form.clearErrors('offer_validity')">
                            <p class="field-error service-summary-error" :class="{ 'is-hidden': !serviceOfferValidityError }">{{ serviceOfferValidityError || ' ' }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="surface-card section-card combined-detail-section">
                <div class="subsection-surface">
                    <div class="section-heading">
                        <h2 class="directory-section-title">{{ currentCopy.pricing }}</h2>
                    </div>

                    <div class="pricing-grid">
                        <div class="pricing-field">
                            <label class="toggle-row"><input v-model="form.including_tax" type="checkbox" @change="handlePricingToggle('including_tax', 'tax_amount')"> <span>{{ currentCopy.tax }}</span></label>
                            <input v-model="form.tax_amount" type="number" min="0" step="0.01" class="offer-input" :class="{ 'is-error': pricingFieldMessage(form.including_tax, form.tax_amount, 'tax_amount') }" :disabled="form.including_tax" :placeholder="currentCopy.taxAmount" @input="handlePricingInput('including_tax', 'tax_amount')" @blur="normalizeFormDecimalField('tax_amount')">
                            <p class="field-error pricing-field-error" :class="{ 'is-hidden': !pricingFieldMessage(form.including_tax, form.tax_amount, 'tax_amount') }">{{ pricingFieldMessage(form.including_tax, form.tax_amount, 'tax_amount') || ' ' }}</p>
                        </div>
                        <div v-if="isSpareParts" class="pricing-field">
                            <label class="toggle-row"><input v-model="form.including_packing" type="checkbox" @change="handlePricingToggle('including_packing', 'packing_cost')"> <span>{{ currentCopy.packing }}</span></label>
                            <input v-model="form.packing_cost" type="number" min="0" step="0.01" class="offer-input" :class="{ 'is-error': pricingFieldMessage(form.including_packing, form.packing_cost, 'packing_cost') }" :disabled="form.including_packing" :placeholder="currentCopy.packingCost" @input="handlePricingInput('including_packing', 'packing_cost')" @blur="normalizeFormDecimalField('packing_cost')">
                            <p class="field-error pricing-field-error" :class="{ 'is-hidden': !pricingFieldMessage(form.including_packing, form.packing_cost, 'packing_cost') }">{{ pricingFieldMessage(form.including_packing, form.packing_cost, 'packing_cost') || ' ' }}</p>
                        </div>
                        <div v-if="isSpareParts" class="pricing-field">
                            <label class="toggle-row"><input v-model="form.including_freight" type="checkbox" @change="handlePricingToggle('including_freight', 'freight_cost')"> <span>{{ currentCopy.freight }}</span></label>
                            <input v-model="form.freight_cost" type="number" min="0" step="0.01" class="offer-input" :class="{ 'is-error': pricingFieldMessage(form.including_freight, form.freight_cost, 'freight_cost') }" :disabled="form.including_freight" :placeholder="currentCopy.freightCost" @input="handlePricingInput('including_freight', 'freight_cost')" @blur="normalizeFormDecimalField('freight_cost')">
                            <p class="field-error pricing-field-error" :class="{ 'is-hidden': !pricingFieldMessage(form.including_freight, form.freight_cost, 'freight_cost') }">{{ pricingFieldMessage(form.including_freight, form.freight_cost, 'freight_cost') || ' ' }}</p>
                        </div>
                        <div v-else class="pricing-field">
                            <label class="toggle-row"><input v-model="form.including_mobilization" type="checkbox" @change="handlePricingToggle('including_mobilization', 'mobilization_cost')"> <span>{{ currentCopy.mobilization }}</span></label>
                            <input v-model="form.mobilization_cost" type="number" min="0" step="0.01" class="offer-input" :class="{ 'is-error': pricingFieldMessage(form.including_mobilization, form.mobilization_cost, 'mobilization_cost') }" :disabled="form.including_mobilization" :placeholder="currentCopy.mobilizationCost" @input="handlePricingInput('including_mobilization', 'mobilization_cost')" @blur="normalizeFormDecimalField('mobilization_cost')">
                            <p class="field-error pricing-field-error" :class="{ 'is-hidden': !pricingFieldMessage(form.including_mobilization, form.mobilization_cost, 'mobilization_cost') }">{{ pricingFieldMessage(form.including_mobilization, form.mobilization_cost, 'mobilization_cost') || ' ' }}</p>
                        </div>
                        <div class="totals-card">
                            <div class="total-row">
                                <span>{{ currentCopy.totalOfferAmount }}</span>
                                <strong>{{ money(totalOfferAmount) }}</strong>
                            </div>
                            <div class="total-row total-row-muted">
                                <span>{{ currentCopy.tax }}</span>
                                <strong>{{ form.including_tax ? 'Included' : money(taxAmountValue) }}</strong>
                            </div>
                            <div v-if="isSpareParts" class="total-row total-row-muted">
                                <span>{{ currentCopy.packing }}</span>
                                <strong>{{ form.including_packing ? 'Included' : money(packingCostValue) }}</strong>
                            </div>
                            <div v-if="isSpareParts" class="total-row total-row-muted">
                                <span>{{ currentCopy.freight }}</span>
                                <strong>{{ form.including_freight ? 'Included' : money(freightCostValue) }}</strong>
                            </div>
                            <div v-else class="total-row total-row-muted">
                                <span>{{ currentCopy.mobilization }}</span>
                                <strong>{{ form.including_mobilization ? 'Included' : money(mobilizationCostValue) }}</strong>
                            </div>
                            <div class="total-row total-row-grand">
                                <span>{{ currentCopy.grandTotal }}</span>
                                <strong>{{ money(grandTotal) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="surface-card section-card combined-detail-section">
                <div class="subsection-surface">
                    <div class="section-heading">
                        <h2 class="directory-section-title">{{ currentCopy.details }}</h2>
                    </div>

                    <div class="details-grid">
                        <div class="form-block form-block-full">
                            <label class="detail-inline-label">{{ currentCopy.deliveryTerms }}</label>
                            <div class="delivery-terms-row">
                                <select v-model="form.delivery_terms" class="offer-select details-control">
                                    <option value="">{{ currentCopy.select }}</option>
                                    <option value="EXW">EXW</option>
                                    <option value="FOB">FOB</option>
                                    <option value="CIF">CIF</option>
                                    <option value="DAP">DAP</option>
                                    <option value="DDP">DDP</option>
                                </select>
                                <input
                                    v-model="form.other_delivery_terms"
                                    type="text"
                                    class="offer-input delivery-other-input"
                                    :placeholder="currentCopy.otherDeliveryTerms"
                                >
                            </div>
                        </div>
                        <div class="form-block form-block-full">
                            <label class="detail-inline-label">{{ currentCopy.paymentTerms }}</label>
                            <p class="field-error payment-term-section-error" :class="{ 'is-hidden': !paymentTermsSectionError }">{{ paymentTermsSectionError || ' ' }}</p>
                            <div class="payment-terms-grid">
                                <div class="payment-term-field">
                                    <input
                                        v-model="form.payment_order_confirmation"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="offer-input payment-term-input"
                                        :class="{ 'is-error': paymentOrderConfirmationError || paymentTermsSectionError }"
                                        @input="handlePaymentTermInput"
                                        @blur="normalizeFormDecimalField('payment_order_confirmation')"
                                    >
                                    <label class="payment-term-label">{{ currentCopy.paymentOrderConfirmation }}</label>
                                    <p class="field-error payment-term-error" :class="{ 'is-hidden': !paymentOrderConfirmationError }">{{ paymentOrderConfirmationError || ' ' }}</p>
                                </div>
                                <div class="payment-term-field">
                                    <input
                                        v-model="form.payment_before_shipment"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="offer-input payment-term-input"
                                        :class="{ 'is-error': paymentBeforeShipmentError || paymentTermsSectionError }"
                                        @input="handlePaymentTermInput"
                                        @blur="normalizeFormDecimalField('payment_before_shipment')"
                                    >
                                    <label class="payment-term-label">{{ currentCopy.paymentBeforeShipment }}</label>
                                    <p class="field-error payment-term-error" :class="{ 'is-hidden': !paymentBeforeShipmentError }">{{ paymentBeforeShipmentError || ' ' }}</p>
                                </div>
                                <div class="payment-term-field">
                                    <input
                                        v-model="form.payment_invoice_days"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="offer-input payment-term-input"
                                        :class="{ 'is-error': paymentInvoiceDaysError || paymentTermsSectionError }"
                                        @input="handlePaymentTermInput"
                                        @blur="normalizeFormWholeNumberField('payment_invoice_days')"
                                    >
                                    <label class="payment-term-label">{{ currentCopy.paymentInvoiceDays }}</label>
                                    <p class="field-error payment-term-error" :class="{ 'is-hidden': !paymentInvoiceDaysError }">{{ paymentInvoiceDaysError || ' ' }}</p>
                                </div>
                            </div>
                            <input
                                v-model="form.other_payment_terms"
                                type="text"
                                class="offer-input other-payment-input"
                                :class="{ 'is-error': otherPaymentTermsError || paymentTermsSectionError }"
                                :placeholder="currentCopy.otherPaymentTerms"
                                @input="handlePaymentTermInput"
                            >
                            <p class="field-error payment-term-section-error" :class="{ 'is-hidden': !otherPaymentTermsError }">{{ otherPaymentTermsError || ' ' }}</p>
                        </div>
                        <div v-if="!isSpareParts" class="form-block form-block-full">
                            <label class="detail-inline-label">{{ currentCopy.serviceClarification }}</label>
                            <textarea v-model="form.service_clarification" class="offer-textarea details-textarea" :placeholder="currentCopy.serviceClarificationPlaceholder"></textarea>
                        </div>
                        <div class="form-block form-block-full">
                            <label class="detail-inline-label">{{ currentCopy.generalNote }}</label>
                            <textarea v-model="form.general_note" class="offer-textarea details-textarea general-note-textarea" :placeholder="currentCopy.generalNotePlaceholder"></textarea>
                        </div>
                        <div v-if="isSpareParts" class="form-block form-block-full">
                            <label class="detail-inline-label">{{ currentCopy.awardScope }}</label>
                            <p class="award-scope-helper">{{ currentCopy.awardScopeHelper }}</p>
                            <div class="award-scope-grid">
                                <label class="award-scope-option" :class="{ 'is-active': form.award_scope_policy === 'partial_allowed' }">
                                    <input
                                        v-model="form.award_scope_policy"
                                        type="radio"
                                        value="partial_allowed"
                                    >
                                    <div class="award-scope-copy">
                                        <strong>{{ currentCopy.partialAwardAccepted }}</strong>
                                        <span>{{ currentCopy.partialAwardAcceptedHint }}</span>
                                    </div>
                                </label>
                                <label class="award-scope-option" :class="{ 'is-active': form.award_scope_policy === 'full_scope_required' }">
                                    <input
                                        v-model="form.award_scope_policy"
                                        type="radio"
                                        value="full_scope_required"
                                    >
                                    <div class="award-scope-copy">
                                        <strong>{{ currentCopy.fullQuotedScopeRequired }}</strong>
                                        <span>{{ currentCopy.fullQuotedScopeRequiredHint }}</span>
                                    </div>
                                </label>
                            </div>
                            <p v-if="form.errors.award_scope_policy" class="field-error">{{ form.errors.award_scope_policy }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="surface-card section-card combined-detail-section">
                <div class="subsection-surface offer-actions-panel">
                    <p class="detail-inline-text detail-inline-text-long preview-note">{{ currentCopy.previewNotice }}</p>
                    <p v-if="form.errors.items" class="field-error preview-error">{{ form.errors.items }}</p>
                    <div class="offer-form-actions">
                        <button type="button" class="offer-button offer-button-secondary" :disabled="form.processing" @click="saveDraft">{{ currentCopy.draft }}</button>
                        <button type="button" class="offer-button offer-button-primary" :disabled="form.processing" @click="submitOffer">{{ currentCopy.submit }}</button>
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
                        <span aria-hidden="true"><</span>
                    </button>

                    <div v-if="currentAttachment" class="gallery-preview">
                        <img
                            v-if="isImageAttachment(currentAttachment)"
                            :src="normalizeAttachmentUrl(currentAttachment.url)"
                            :alt="currentAttachment.name"
                            class="gallery-preview-image"
                        >
                        <div v-else class="gallery-preview-fallback">
                            <p>{{ currentAttachment.name }}</p>
                            <p>{{ currentCopy.previewUnavailable }}</p>
                        </div>

                        <div class="gallery-meta">
                            <div>
                                <strong>{{ currentAttachment.name }}</strong>
                                <p v-if="formatFileSize(currentAttachment.size)">{{ formatFileSize(currentAttachment.size) }}</p>
                            </div>
                            <a class="gallery-open-link" :href="normalizeAttachmentUrl(currentAttachment.url)" target="_blank" rel="noopener">
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
                        <span aria-hidden="true">></span>
                    </button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.detail-shell{padding:16px 0 56px}
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
.eyebrow{margin:0 0 12px;font-size:.82rem;letter-spacing:.18em;text-transform:uppercase;color:var(--color-ocean);font-weight:700}
.hero-pills{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}
.pill,.status-pill,.back-button{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 14px;border-radius:10px;font-size:.85rem;font-weight:600;white-space:nowrap}
.request-type-pill{background:rgba(15,23,42,.06);color:#334155}
.priority-pill.is-critical{background:rgba(239,68,68,.12);color:#dc2626}
.priority-pill.is-high{background:rgba(249,115,22,.14);color:#c2410c}
.priority-pill.is-normal{background:rgba(20,184,166,.1);color:#0f766e}
.priority-pill.is-low{background:rgba(15,23,42,.06);color:#475569}
.status-pill{gap:8px;background:rgba(255,255,255,.92);color:#334155;border:1px solid rgba(148,163,184,.22)}
.status-dot{width:10px;height:10px;border-radius:999px;display:inline-block;box-shadow:0 0 0 3px transparent}
.status-dot.is-open{background:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.16)}
.status-dot.is-closed{background:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.16)}
.hero-actions{display:flex;align-items:flex-start}
.back-button{text-decoration:none;background:#2563eb;color:#fff;box-shadow:0 12px 24px rgba(37,99,235,.18)}
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
.info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 28px}
.detail-inline-value{min-width:0}
.detail-inline-main{display:flex;align-items:flex-start;gap:8px}
.detail-inline-main-wide{align-items:flex-start}
.detail-inline-label{color:#04151f;font-size:14px;font-weight:700;line-height:1.6;white-space:nowrap}
.detail-inline-text{color:rgba(4,21,31,.82);font-size:15px;font-weight:400;line-height:1.6}
.detail-inline-text-long{white-space:normal}
.detail-inline-value-full{grid-column:1 / -1}
.detail-table-wrap{overflow-x:auto;padding-bottom:14px}
.detail-table{width:100%;min-width:1180px;border-collapse:collapse;table-layout:fixed}
.detail-table.offer-table{min-width:1180px}
.detail-table .col-line{width:58px}
.detail-table .col-product{width:220px}
.detail-table .col-part,
.detail-table .col-manufacturer,
.detail-table .col-model,
.detail-table .col-catalog,
.detail-table .col-serial,
.detail-table .col-drawing,
.detail-table .col-quality{width:150px}
.detail-table .col-qty{width:130px}
.detail-table .col-rob{width:100px}
.detail-table thead th{padding:16px 14px;background:#f4f7fb;color:#04151f;font-size:14px;font-weight:700;line-height:1.2;text-align:left;white-space:nowrap}
.detail-table thead th:first-child{text-align:center}
.detail-table tbody td{padding:16px 14px;border-top:1px solid rgba(4,21,31,.06);color:rgba(4,21,31,.82);font-size:15px;font-weight:400;line-height:1.6;vertical-align:top}
.detail-table.offer-table.is-compact{min-width:920px}
.detail-table.offer-table.is-compact .col-line{width:48px}
.detail-table.offer-table.is-compact .col-product{width:145px}
.detail-table.offer-table.is-compact .col-part,
.detail-table.offer-table.is-compact .col-manufacturer,
.detail-table.offer-table.is-compact .col-model,
.detail-table.offer-table.is-compact .col-catalog,
.detail-table.offer-table.is-compact .col-serial,
.detail-table.offer-table.is-compact .col-drawing,
.detail-table.offer-table.is-compact .col-quality{width:100px}
.detail-table.offer-table.is-compact .col-qty{width:90px}
.detail-table.offer-table.is-compact .col-rob{width:70px}
.detail-table.offer-table.is-compact thead th{padding:10px 8px;font-size:12px}
.detail-table.offer-table.is-compact tbody td{padding:10px 8px;font-size:13px;line-height:1.45}
.detail-table.offer-table.is-compact .line-cell{gap:6px}
.detail-table.offer-table.is-compact .expand-row-button{width:20px;height:20px}
.detail-table.offer-table.is-compact .expand-row-icon{font-size:12px}
.line-cell{display:flex;align-items:center;justify-content:center;gap:10px;white-space:nowrap;width:100%}
.expand-row-button{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border:0;border-radius:999px;background:rgba(15,23,42,.06);color:#334155;cursor:pointer;padding:0}
.expand-row-icon{display:inline-block;font-size:14px;line-height:1;transform:rotate(0deg);transition:transform 160ms ease}
.expand-row-icon.is-open{transform:rotate(90deg)}
.product-cell{text-align:left}
.detail-table tbody tr.item-master-row-expanded td{background:#f8fafb}
.item-detail-row td{padding-top:0;background:#f8fafb;border-top:0}
.item-detail-expanded-cell{padding:0;background:#f8fafb;border-top:0}
.item-detail-context-band{
    background:#f8fafb;
}
.item-offer-fields-shell{
    background:#fff;
    padding:14px 0 18px;
}
.item-detail-grid{display:grid;grid-template-columns:minmax(0,1.8fr) minmax(220px,.7fr);gap:18px;padding:0 0 0 64px;align-items:start}
.item-detail-block{min-width:0}
.item-detail-grid-offer{grid-template-columns:85px 100px 85px 85px 150px 150px 150px 150px 150px;justify-content:start;row-gap:16px}
.item-detail-grid-offer.is-compact{padding-left:54px}
.item-context-row{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:18px 24px;
    padding:10px 12px 10px 64px;
    background:transparent;
    align-items:start;
}
.item-context-row .detail-inline-main{min-height:100%}
.item-context-row.is-compact{
    gap:12px 18px;
    padding:8px 10px 8px 54px;
}
.item-context-row.is-compact .detail-inline-label{
    font-size:12px;
    line-height:1.25;
}
.item-context-row.is-compact .detail-inline-text,
.item-context-row.is-compact .file-preview-button{
    font-size:12px;
    line-height:1.25;
}
.item-detail-block-offer .item-detail-form-field{display:grid;gap:8px;align-items:start}
.item-detail-block-offer .item-detail-input-wrap{min-width:0}
.item-detail-block-offer .detail-inline-label{white-space:normal;line-height:1.3}
.item-detail-static-field .detail-inline-text{line-height:1.3}
.item-detail-block-offer .offer-input{
    min-height:36px;
    height:36px;
    padding:7px 10px;
    font-size:.86rem;
    line-height:1.2;
    box-sizing:border-box;
}
.offer-static-box{
    display:flex;
    align-items:center;
    width:100%;
    min-height:36px;
    padding:7px 10px;
    border:1px solid rgba(148,163,184,.32);
    border-radius:10px;
    background:#fff;
    color:rgba(4,21,31,.82);
    font-size:15px;
    font-weight:400;
    line-height:1.6;
    box-sizing:border-box;
}
.offer-static-box-multiline{
    align-items:flex-start;
    min-height:72px;
    white-space:normal;
}
.offer-static-box-files{
    justify-content:flex-start;
}
.offer-readonly-input{background:#f3f6fa;color:#334155;border-color:rgba(148,163,184,.26)}
.item-detail-block-offer .compact-offer-input{max-width:100%}
.item-detail-block-offer .wide-offer-input{min-width:0;max-width:100%}
.offer-qty-input{width:100%;min-width:0;max-width:none}
.lead-time-input{width:100%;min-width:0;max-width:none}
.quality-offer-select,.brand-note-input,.remarks-input{width:100%;min-width:0;max-width:none}
.unit-price-input{width:100%;min-width:0;max-width:none}
.item-detail-grid-offer.is-compact{grid-template-columns:72px 88px 72px 72px 120px 120px 120px 120px 120px;row-gap:12px}
.item-detail-grid-offer.is-compact .detail-inline-label{font-size:12px;line-height:1.25}
.item-detail-grid-offer.is-compact .offer-input,
.item-detail-grid-offer.is-compact .offer-select,
.item-detail-grid-offer.is-compact .offer-readonly-input,
.item-detail-grid-offer.is-compact .offer-static-box{min-height:32px;height:32px;padding:6px 8px;font-size:12px}
.item-detail-grid-offer.is-compact .detail-inline-text{font-size:12px;line-height:1.25}
.item-detail-grid-offer.is-compact .offer-static-box-multiline{min-height:58px;height:auto}
.item-detail-grid-offer.is-compact .file-upload-trigger{min-height:32px;height:32px;padding:6px 8px;font-size:12px}
.item-detail-grid-offer.is-compact .file-chip{max-width:140px;padding:3px 7px}
.item-detail-grid-offer.is-compact .file-chip-name{font-size:.72rem}
.item-detail-grid-offer.is-compact .file-chip-remove{font-size:.82rem}
.item-detail-inline-center{align-items:center}
.item-detail-block .detail-inline-main{align-items:center}
.file-preview-button{border:0;background:transparent;color:inherit;cursor:pointer;min-height:auto;padding:0;border-radius:0;font-size:inherit;font-weight:400;line-height:1.2;text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:3px}
.compact-offer-input{min-width:0}
.wide-offer-input{min-width:160px}
.file-upload-field{display:grid;gap:6px}
.file-upload-trigger{width:100%;min-height:36px;height:36px;padding:7px 10px;border:1px solid rgba(4,21,31,.14);border-radius:10px;background:#fff;color:rgba(4,21,31,.7);font-size:.86rem;font-weight:400;line-height:1.2;text-align:left}
.file-upload-input{display:none}
.file-chip-list{display:flex;gap:6px;flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;padding-bottom:2px;scrollbar-width:thin}
.file-chip{display:inline-flex;align-items:center;gap:8px;flex:0 0 auto;max-width:180px;padding:4px 8px;border-radius:8px;background:rgba(4,21,31,.04)}
.file-chip-name{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#04151f;font-size:.78rem;line-height:1.2}
.file-chip-remove{border:0;background:transparent;color:rgba(4,21,31,.62);font-size:.92rem;line-height:1;padding:0}
.offer-input,.offer-select,.offer-textarea{box-sizing:border-box;width:100%;min-height:42px;padding:0 12px;border:1px solid rgba(148,163,184,.32);border-radius:10px;background:#fff;color:#0f172a;font-size:.9rem}
.offer-input,.offer-select{height:42px;min-height:42px;line-height:1.2}
.offer-table .offer-input{background:#fff;border-color:rgba(148,163,184,.32);padding:0 12px;height:42px;min-height:42px}
.offer-input.is-error,.offer-select.is-error,.offer-textarea.is-error{border-color:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.08)}
.offer-textarea{min-height:120px;padding:12px;resize:vertical}
.field-error{margin:6px 0 0;color:#dc2626;font-size:.78rem;line-height:1.4;white-space:normal}
.pricing-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px 24px}
.pricing-field{display:grid;gap:10px}
.pricing-field-error{min-height:2.2em}
.pricing-field-error.is-hidden{visibility:hidden}
.toggle-row{display:flex;align-items:center;gap:10px;color:#0f172a;font-size:.92rem;font-weight:600}
.toggle-row span{white-space:nowrap}
.totals-card{grid-column:1 / -1;padding:20px 22px;border:1px solid rgba(148,163,184,.18);border-radius:14px;background:#f8fafc;display:grid;gap:10px}
.total-row{display:flex;align-items:center;justify-content:space-between;gap:16px;color:#0f172a;font-size:.94rem}
.total-row strong{font-weight:700}
.total-row-muted{color:#475569}
.total-row-grand{padding-top:10px;border-top:1px solid rgba(148,163,184,.18);font-size:1rem}
.service-offer-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}
.service-summary-input{width:100%;min-width:0}
.service-summary-file-trigger{width:min(360px,100%)}
.service-summary-error{min-height:2.2em}
.service-summary-error.is-hidden{visibility:hidden}
.details-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 24px}
.form-block{display:grid;gap:10px}
.form-block-full{grid-column:1 / -1}
.form-block > .detail-inline-label{font-size:.92rem;font-weight:600;line-height:1.4}
.delivery-terms-row{display:grid;grid-template-columns:minmax(0,360px) minmax(260px,1fr);gap:12px;align-items:center}
.details-control{width:100%;min-width:0}
.delivery-other-input{width:min(420px,100%)}
.payment-terms-grid{display:grid;grid-template-columns:1fr;gap:10px}
.payment-term-field{display:grid;grid-template-columns:100px 1fr;grid-template-areas:"input label" "error error";align-items:center;column-gap:12px;row-gap:6px}
.payment-term-input{grid-area:input;width:100px;min-width:100px;height:38px;min-height:38px;appearance:textfield;-moz-appearance:textfield}
.payment-term-label{grid-area:label;display:flex;align-items:center;color:#0f172a;font-size:.94rem;font-weight:400;line-height:1.2;white-space:nowrap;min-height:38px}
.payment-term-error{grid-area:error;min-height:1.2em;margin-top:0}
.payment-term-error.is-hidden,.payment-term-section-error.is-hidden{visibility:hidden}
.payment-term-section-error{min-height:1.2em}
.other-payment-input{width:min(360px,100%)}
.award-scope-helper{margin:0;color:#475569;font-size:.88rem;line-height:1.5}
.award-scope-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.award-scope-option{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border:1px solid rgba(148,163,184,.24);border-radius:12px;background:#fff;cursor:pointer;transition:border-color .16s ease,box-shadow .16s ease,background-color .16s ease}
.award-scope-option input{margin-top:3px}
.award-scope-option.is-active{border-color:rgba(37,99,235,.48);box-shadow:0 0 0 3px rgba(37,99,235,.08);background:#f8fbff}
.award-scope-copy{display:grid;gap:4px;min-width:0}
.award-scope-copy strong{color:#0f172a;font-size:.92rem;font-weight:700;line-height:1.35}
.award-scope-copy span{color:#475569;font-size:.84rem;line-height:1.5}
.details-textarea{width:min(760px,100%);min-height:96px}
.general-note-textarea{min-height:72px}
.offer-actions-panel{display:grid;gap:16px}
.preview-note{margin:0}
.preview-error{margin-top:-6px;font-size:.84rem}
.offer-form-actions{display:flex;align-items:center;justify-content:flex-end;gap:12px}
.offer-button{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border-radius:10px;border:1px solid transparent;font-size:.92rem;font-weight:600}
.offer-button-primary{background:#0f8aa6;color:#fff;border-color:#0f8aa6;cursor:pointer}
.offer-button-primary:disabled{opacity:.65;cursor:not-allowed}
.offer-button-secondary{background:#fff;color:#0f172a;border-color:#cbd5e1;cursor:pointer}
.offer-button-secondary:disabled{opacity:.65;cursor:not-allowed}
.offer-button-disabled{background:#f8fafc;border-color:#e2e8f0;color:#94a3b8;cursor:not-allowed}
.detail-modal-backdrop{position:fixed;inset:0;z-index:1000;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(4,21,31,.52);backdrop-filter:blur(10px)}
.detail-modal{width:min(720px,100%);max-height:min(80vh,720px);overflow:hidden;border-radius:18px;border:1px solid rgba(148,163,184,.25);background:rgba(255,255,255,.98);box-shadow:0 30px 70px rgba(15,23,42,.18);display:flex;flex-direction:column}
.detail-modal-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 22px;border-bottom:1px solid rgba(148,163,184,.18)}
.detail-modal-title{margin:0;font-size:1.05rem;font-weight:700;color:#04151f}
.detail-modal-close{border:1px solid rgba(148,163,184,.28);background:#fff;color:#0f172a;border-radius:999px;padding:8px 14px;font-size:.82rem;font-weight:600;cursor:pointer}
.detail-modal-body{padding:20px 22px;overflow:auto}
.modal-pill-list{display:flex;flex-wrap:wrap;gap:10px}
.modal-pill{display:inline-flex;align-items:center;min-height:34px;padding:0 12px;border-radius:999px;background:#eff4fb;color:#0f172a;font-size:.84rem;font-weight:600}
.modal-port-stack{display:grid;gap:16px}
.modal-port-group{display:grid;gap:10px}
.modal-port-group strong{font-size:.92rem;color:#04151f}
.notes-text{margin:0;color:rgba(4,21,31,.72);font-size:.88rem;line-height:1.5}
.port-summary-text{font-weight:500}
.gallery-modal-backdrop{position:fixed;inset:0;z-index:1100;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(4,21,31,.62);backdrop-filter:blur(12px)}
.gallery-modal{width:min(960px,100%);max-height:min(86vh,820px);overflow:hidden;border-radius:20px;border:1px solid rgba(148,163,184,.22);background:rgba(255,255,255,.99);box-shadow:0 36px 80px rgba(15,23,42,.22);display:flex;flex-direction:column}
.gallery-modal-title-group{display:flex;flex-direction:column;gap:4px}
.gallery-modal-counter{margin:0;color:#64748b;font-size:.84rem}
.gallery-modal-body{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:14px;padding:24px}
.gallery-nav-button{display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:999px;border:1px solid rgba(148,163,184,.24);background:#fff;color:#0f172a;cursor:pointer;box-shadow:0 10px 20px rgba(15,23,42,.08)}
.gallery-preview{display:grid;gap:16px;min-width:0}
.gallery-preview-image{display:block;width:100%;max-height:60vh;object-fit:contain;border-radius:16px;background:#f8fafc;border:1px solid rgba(148,163,184,.14)}
.gallery-preview-fallback{display:grid;gap:8px;place-items:center;min-height:320px;padding:24px;border:1px dashed rgba(148,163,184,.34);border-radius:16px;background:#f8fafc;color:#475569;text-align:center}
.gallery-preview-fallback p{margin:0}
.gallery-meta{display:flex;align-items:center;justify-content:space-between;gap:16px}
.gallery-meta strong{display:block;color:#04151f;font-size:.94rem}
.gallery-meta p{margin:4px 0 0;color:#64748b;font-size:.84rem}
.gallery-open-link{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 14px;border-radius:10px;background:#0f172a;color:#fff;text-decoration:none;font-size:.84rem;font-weight:600}
@media (max-width: 1100px){
    .pricing-grid{grid-template-columns:1fr}
}
@media (max-width: 900px){
    .info-grid,.pricing-grid,.details-grid,.service-offer-grid{grid-template-columns:1fr}
    .item-detail-grid{grid-template-columns:1fr;padding-left:0}
    .item-detail-context-band{background:#f8fafb}
    .item-offer-fields-shell{padding:12px 0 16px}
    .item-context-row{grid-template-columns:1fr;padding:8px 10px}
    .item-detail-block-offer .item-detail-form-field{grid-template-columns:1fr;row-gap:8px}
    .delivery-terms-row{grid-template-columns:1fr}
    .details-control,.details-textarea,.delivery-other-input,.other-payment-input{width:100%;min-width:0}
    .payment-term-field{grid-template-columns:1fr;grid-template-areas:"label" "input" "error";gap:8px}
    .payment-term-input{width:100%;min-width:0}
    .award-scope-grid{grid-template-columns:1fr}
    .gallery-modal-body{grid-template-columns:1fr}
}

.payment-term-input::-webkit-outer-spin-button,
.payment-term-input::-webkit-inner-spin-button{
    -webkit-appearance:none;
    margin:0;
}
@media (max-width: 720px){
    .hero-panel{flex-direction:column}
    .surface-panel,.surface-card .subsection-surface{padding:20px}
}
</style>
