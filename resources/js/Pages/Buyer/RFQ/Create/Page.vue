<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainLayout from '../../../../Layouts/MainLayout.vue';
import RfqCreateGeneralInformationSection from './sections/RfqCreateGeneralInformationSection.vue';
import RfqCreateItemsToQuoteSection from './sections/RfqCreateItemsToQuoteSection.vue';
import RfqCreateSuppliersToSendRfqSection from './sections/RfqCreateSuppliersToSendRfqSection.vue';
import RfqCreateImportTemplateModal from './modals/RfqCreateImportTemplateModal.vue';
import RfqCreateImportPreviewModal from './modals/RfqCreateImportPreviewModal.vue';
import { extractDocumentRows, isStructuredDocumentImport } from '../../../../lib/rfqDocumentImport.js';

const props = defineProps({
    defaults: {
        type: Object,
        required: true,
    },
    actionUrl: {
        type: String,
        required: true,
    },
    unitOptions: {
        type: Array,
        default: () => [],
    },
    qualityOptions: {
        type: Array,
        default: () => [],
    },
    countryOptions: {
        type: Array,
        default: () => [],
    },
    portsByCountry: {
        type: Object,
        default: () => ({}),
    },
    supplierCategories: {
        type: Array,
        default: () => [],
    },
    supplierMatchesUrl: {
        type: String,
        required: true,
    },
    supplierSuggestionsUrl: {
        type: String,
        required: true,
    },
    supplierBrandsUrl: {
        type: String,
        required: true,
    },
    supplierSubcategoriesUrl: {
        type: String,
        required: true,
    },
    importTemplate: {
        type: Object,
        default: () => ({
            name: 'My RFQ Import Template',
            general: {},
            items: {},
            hasSavedTemplate: false,
        }),
    },
    mode: {
        type: String,
        default: 'create',
    },
    submitMethod: {
        type: String,
        default: 'post',
    },
    backUrl: {
        type: String,
        default: '/dashboard/buyer/requests',
    },
    editPolicy: {
        type: Object,
        default: () => ({
            can_edit: true,
            general_only: false,
            can_delete: false,
            reason: null,
        }),
    },
    supplierTarget: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    request_type: props.defaults.request_type ?? 'spare_parts',
    reference_no: props.defaults.reference_no,
    company_name: props.defaults.company_name,
    ship_name: props.defaults.ship_name,
    country_names: props.defaults.country_names,
    ports_by_country: props.defaults.ports_by_country,
    category_ids: props.defaults.category_ids ?? [],
    subcategory_ids: props.defaults.subcategory_ids ?? [],
    brand_ids: props.defaults.brand_ids ?? [],
    requisition_date: props.defaults.requisition_date,
    due_date: props.defaults.due_date,
    currency: props.defaults.currency,
    priority: props.defaults.priority,
    status: props.defaults.status,
    general_notes: props.defaults.general_notes,
    service_title: props.defaults.service_title ?? '',
    service_description: props.defaults.service_description ?? '',
    service_files: props.defaults.service_files ?? [],
    supplier_recipient_ids: props.defaults.supplier_recipient_ids ?? [],
    items: props.defaults.items,
});

const isEditMode = computed(() => props.mode === 'edit');
const supplierTarget = computed(() => props.supplierTarget ?? null);
const isSupplierTargetedRequest = computed(() => Boolean(supplierTarget.value?.company_name));
const supplierTargetCandidateIds = computed(() => (
    Array.isArray(supplierTarget.value?.candidate_listing_ids)
        ? supplierTarget.value.candidate_listing_ids
            .map((id) => Number.parseInt(id, 10))
            .filter((id) => Number.isInteger(id) && id > 0)
        : []
));
const requestTypeLocked = computed(() => Boolean(supplierTarget.value?.request_type_locked));
const requestScopeNote = computed(() => supplierTarget.value?.scope_note ?? '');
const isGeneralOnlyEdit = computed(() => isEditMode.value && Boolean(props.editPolicy?.general_only));
const canEditRequestContent = computed(() => !isGeneralOnlyEdit.value);
const generalOnlyReason = computed(() => props.editPolicy?.reason ?? null);
const itemsToQuoteLockMessage = computed(() => {
    if (!isGeneralOnlyEdit.value) {
        return '';
    }

    if (generalOnlyReason.value === 'overdue_extendable') {
        return 'The due date has passed. Only the General Information section remains editable so you can extend the timeline.';
    }

    return 'Offers have started to arrive for this RFQ. Items to Quote and supplier targeting are now locked.';
});
const submitIntent = ref('submit');
const primarySubmitLabel = computed(() => {
    if (!isEditMode.value) {
        return null;
    }

    return 'Save Changes';
});
const pageHeading = computed(() => (isEditMode.value
    ? 'Edit RFQ'
    : copy.title));
const pageIntro = computed(() => {
    if (!isEditMode.value) {
        return heroIntroCopy;
    }

    if (isGeneralOnlyEdit.value) {
        if (generalOnlyReason.value === 'overdue_extendable') {
            return 'The due date has passed. Only the General Information section can be updated now so you can extend the timeline.';
        }

        return 'Offers have started to arrive for this RFQ. Only the General Information section can be updated now.';
    }

    return 'Update the RFQ content, line items, and supplier targeting from this screen.';
});

const portsByCountryState = ref({ ...(props.portsByCountry ?? {}) });
const loadingCountries = ref([]);
const fileInputRefs = ref({});
const serviceFileInputRef = ref(null);
const importFileInputRef = ref(null);
const importPreview = ref(null);
const importPreviewDraft = ref(null);
const importError = ref('');
const importParsing = ref(false);
const importPreviewOpen = ref(false);
const importPreviewEditing = ref(false);
const importTemplateOpen = ref(false);
const importTemplateSaving = ref(false);
const importTemplateSaved = ref(Boolean(props.importTemplate?.hasSavedTemplate));
const importTemplateError = ref('');
const supplierMatches = ref([]);
const supplierMatchesLoading = ref(false);
const supplierMatchesLoaded = ref(false);
const supplierMatchesError = ref('');
const supplierSuggestions = ref(null);
const supplierSuggestionsLoading = ref(false);
const supplierSuggestionsError = ref('');
const supplierSuggestionsApplied = ref(false);
const supplierSuggestionsOpen = ref(false);
const supplierSelectionMode = ref('manual');
const pendingSuggestedSupplierSubcategoryIds = ref([]);
const supplierPortOptionsState = ref({});
const supplierBrandOptionsState = ref([]);
const supplierBrandOptionsLoading = ref(false);
const supplierBrandOptionsLoaded = ref(false);
const supplierBrandOptionsError = ref('');
const supplierSubcategoryOptionsState = ref({});
const supplierSubcategoryLoadingKeys = ref({});
const supplierSubcategoryErrorKeys = ref({});
const supplierScopeCustomized = ref(false);
const supplierFilters = ref({
    country_names: [],
    ports_by_country: {},
    category_ids: [...(props.defaults.category_ids ?? [])],
    subcategory_ids: [...(props.defaults.subcategory_ids ?? [])],
    brand_ids: [...(props.defaults.brand_ids ?? [])],
});

const countryMenuOpen = ref(false);
const portMenuOpen = ref(false);
const countryMenuRef = ref(null);
const portMenuRef = ref(null);
const countryMenuListRef = ref(null);
const portMenuListRef = ref(null);
const supplierCountryMenuOpen = ref(false);
const supplierPortMenuOpen = ref(false);
const supplierCategoryMenuOpen = ref(false);
const supplierSubcategoryMenuOpen = ref(false);
const supplierBrandMenuOpen = ref(false);
const supplierCountryMenuRef = ref(null);
const supplierPortMenuRef = ref(null);
const supplierCategoryMenuRef = ref(null);
const supplierSubcategoryMenuRef = ref(null);
const supplierBrandMenuRef = ref(null);
const supplierCountryMenuListRef = ref(null);
const supplierPortMenuListRef = ref(null);
const supplierCategoryMenuListRef = ref(null);
const supplierSubcategoryMenuListRef = ref(null);
const supplierBrandMenuListRef = ref(null);
const setCountryMenuRef = (element) => {
    countryMenuRef.value = element;
};
const setCountryMenuListRef = (element) => {
    countryMenuListRef.value = element;
};
const setPortMenuRef = (element) => {
    portMenuRef.value = element;
};
const setPortMenuListRef = (element) => {
    portMenuListRef.value = element;
};
const setSupplierCategoryMenuRef = (element) => {
    supplierCategoryMenuRef.value = element;
};
const setSupplierCategoryMenuListRef = (element) => {
    supplierCategoryMenuListRef.value = element;
};
const setSupplierSubcategoryMenuRef = (element) => {
    supplierSubcategoryMenuRef.value = element;
};
const setSupplierSubcategoryMenuListRef = (element) => {
    supplierSubcategoryMenuListRef.value = element;
};
const setSupplierBrandMenuRef = (element) => {
    supplierBrandMenuRef.value = element;
};
const setSupplierBrandMenuListRef = (element) => {
    supplierBrandMenuListRef.value = element;
};
const setImportFileInputRef = (element) => {
    importFileInputRef.value = element;
};
const setServiceFileInputRef = (element) => {
    serviceFileInputRef.value = element;
};
const countrySearch = ref('');
const portSearch = ref('');
const supplierCategorySearch = ref('');
const supplierSubcategorySearch = ref('');
const supplierBrandSearch = ref('');

let countryTypeaheadBuffer = '';
let portTypeaheadBuffer = '';
let countryTypeaheadTimeout = null;
let portTypeaheadTimeout = null;
let supplierMatchesRequestId = 0;

const copy = {
    eyebrow: 'Create RFQ',
    title: 'New Procurement Request',
    text: 'In this first phase we are opening the manual entry flow. In the next phase we will add PDF, image, and Excel uploads with AI-assisted item extraction.',
    submitDraft: 'Save Draft',
    submitSend: 'Submit RFQ',
    submitDraftLoading: 'Saving Draft...',
    submitSendLoading: 'Submitting RFQ...',
    submitSaveLoading: 'Saving Changes...',
};

const secondarySubmitLabel = computed(() => {
    if (form.processing && submitIntent.value === 'draft') {
        return copy.submitDraftLoading;
    }

    return copy.submitDraft;
});

const resolvedPrimarySubmitLabel = computed(() => {
    if (form.processing) {
        if (isEditMode.value) {
            return copy.submitSaveLoading;
        }

        return copy.submitSendLoading;
    }

    return isEditMode.value
        ? primarySubmitLabel.value
        : copy.submitSend;
});

const heroIntroCopy = 'Create spare parts RFQs or service requests here. You can fill the form manually or upload your usual PDF, image, Excel, or CSV file; we map the content into this form and let you review it before sending to the selected suppliers.';

const importTemplateGeneralFields = [
    ['reference_no', 'Reference No'],
    ['company_name', 'Company'],
    ['ship_name', 'Ship'],
    ['country', 'Country'],
    ['port', 'Port'],
    ['requisition_date', 'Requisition Date'],
    ['due_date', 'Due Date'],
    ['currency', 'Currency'],
    ['priority', 'Priority'],
    ['general_notes', 'General Notes'],
];

const importTemplateItemFields = [
    ['product_name', 'Product'],
    ['part_no', 'Part No'],
    ['manufacturer', 'Manufacturer'],
    ['model_type', 'MFG Model / Type'],
    ['catalog_code', 'Catalog Code'],
    ['serial_number', 'Serial Number'],
    ['drawing_number', 'Drawing Number'],
    ['quantity', 'Qty'],
    ['unit', 'Unit'],
    ['rob', 'ROB'],
    ['quality', 'Quality'],
    ['comments', 'Comments'],
];

const importTemplateForm = ref({
    name: props.importTemplate?.name ?? 'My RFQ Import Template',
    general: Object.fromEntries(importTemplateGeneralFields.map(([key]) => [key, props.importTemplate?.general?.[key] ?? ''])),
    items: Object.fromEntries(importTemplateItemFields.map(([key]) => [key, props.importTemplate?.items?.[key] ?? ''])),
});

const normalizeSupplierPickerSearch = (value) => String(value ?? '')
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase('en')
    .trim();

const readCookieValue = (name) => {
    const prefix = `${name}=`;
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith(prefix));

    if (!cookie) {
        return '';
    }

    return decodeURIComponent(cookie.slice(prefix.length));
};

const getRequestCsrfToken = () => (
    readCookieValue('XSRF-TOKEN')
    || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    || ''
);

const getRequestCsrfHeaders = () => {
    const cookieToken = readCookieValue('XSRF-TOKEN');
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    if (cookieToken) {
        return {
            'X-XSRF-TOKEN': cookieToken,
        };
    }

    if (metaToken) {
        return {
            'X-CSRF-TOKEN': metaToken,
        };
    }

    return {};
};

const getCsrfMismatchMessage = (fallback) => (
    fallback
        ? `Your session expired while processing this request. Refresh the page and try again. ${fallback}`
        : 'Your session expired while processing this request. Refresh the page and try again.'
);

const rankSupplierPickerOptions = (items, query, getLabel) => {
    const needle = normalizeSupplierPickerSearch(query);

    if (!needle) {
        return [...items];
    }

    return [...items]
        .map((item) => {
            const label = normalizeSupplierPickerSearch(getLabel(item));
            let score = 0;

            if (label === needle) {
                score = 400;
            } else if (label.startsWith(needle)) {
                score = 300;
            } else if (label.split(' ').some((word) => word.startsWith(needle))) {
                score = 200;
            } else if (label.includes(needle)) {
                score = 100;
            }

            return { item, label, score };
        })
        .filter((entry) => entry.score > 0)
        .sort((left, right) => right.score - left.score || left.label.localeCompare(right.label, 'en', { sensitivity: 'base' }))
        .map((entry) => entry.item);
};

const prioritizeSelectedSupplierOptions = (items, getId, selectedIds = []) => {
    const selected = new Set((selectedIds ?? []).map((id) => String(id)));

    if (!selected.size) {
        return items;
    }

    return [...items].sort((left, right) => {
        const leftSelected = selected.has(String(getId(left))) ? 1 : 0;
        const rightSelected = selected.has(String(getId(right))) ? 1 : 0;

        return rightSelected - leftSelected;
    });
};

const supplierCategoryOptions = computed(() => [...(props.supplierCategories ?? [])]
    .sort((left, right) => String(left?.name ?? '').localeCompare(String(right?.name ?? ''), 'en', { sensitivity: 'base' })));
const supplierBrandOptions = computed(() => supplierBrandOptionsState.value ?? []);
const selectedSupplierCategoryIds = computed(() => (supplierFilters.value.category_ids ?? []).map((id) => Number(id)));
const selectedSupplierCategories = computed(() => supplierCategoryOptions.value
    .filter((category) => selectedSupplierCategoryIds.value.includes(Number(category.id))));
const selectedSupplierCategorySlugs = computed(() => selectedSupplierCategories.value
    .map((category) => category.slug)
    .filter(Boolean));
const filteredSupplierCategoryOptions = computed(() => prioritizeSelectedSupplierOptions(
    rankSupplierPickerOptions(
        supplierCategoryOptions.value,
        supplierCategorySearch.value,
        (category) => category.name,
    ),
    (category) => category.id,
    selectedSupplierCategoryIds.value,
));
const currentSupplierSubcategoryKey = computed(() => [...selectedSupplierCategorySlugs.value].sort().join(','));
const supplierSubcategoryGroups = computed(() => {
    if (!currentSupplierSubcategoryKey.value) {
        return [];
    }

    return supplierSubcategoryOptionsState.value[currentSupplierSubcategoryKey.value] ?? [];
});
const supplierSubcategoryOptions = computed(() => {
    return supplierSubcategoryGroups.value
        .flatMap((group) => group.subcategories ?? [])
        .filter((subcategory, index, list) => list.findIndex((item) => Number(item.id) === Number(subcategory.id)) === index);
});
const supplierSubcategoryOptionsLoading = computed(() => (
    currentSupplierSubcategoryKey.value
        ? Boolean(supplierSubcategoryLoadingKeys.value[currentSupplierSubcategoryKey.value])
        : false
));
const supplierSubcategoryOptionsError = computed(() => (
    currentSupplierSubcategoryKey.value
        ? (supplierSubcategoryErrorKeys.value[currentSupplierSubcategoryKey.value] ?? '')
        : ''
));

const supplierPortOptions = computed(() => {
    const countries = supplierFilters.value.country_names ?? [];

    return countries.flatMap((country) => supplierPortOptionsState.value[country] ?? []);
});

const selectedSupplierCountries = computed(() => supplierFilters.value.country_names ?? []);

const selectedSupplierCountryPortGroups = computed(() => selectedSupplierCountries.value.map((country) => ({
    country,
    ports: supplierPortOptionsState.value[country] ?? [],
})));

const selectedSupplierCountriesLabel = computed(() => {
    if (!selectedSupplierCountries.value.length) {
        return 'Select Countries';
    }

    if (selectedSupplierCountries.value.length === 1) {
        return selectedSupplierCountries.value[0];
    }

    return `${selectedSupplierCountries.value.length} countries selected`;
});

const selectedSupplierPortsByCountry = computed(() => supplierFilters.value.ports_by_country ?? {});
const selectedSupplierPortsCount = computed(() => selectedSupplierCountries.value
    .reduce((total, country) => total + ((selectedSupplierPortsByCountry.value[country] ?? []).length), 0));

const hasSupplierPortsForEverySelectedCountry = (countries = [], portsByCountry = {}) => countries.length > 0
    && countries.every((country) => Array.isArray(portsByCountry[country]) && portsByCountry[country].length > 0);

const selectedSupplierPortsLabel = computed(() => {
    if (!selectedSupplierCountries.value.length) {
        return 'Select Countries First';
    }

    if (!selectedSupplierPortsCount.value) {
        return 'Select Ports';
    }

    return `${selectedSupplierPortsCount.value} ports selected`;
});

const hasSupplierRequestScope = computed(() => hasSupplierPortsForEverySelectedCountry(
    selectedSupplierCountries.value,
    selectedSupplierPortsByCountry.value,
));
const selectedSupplierSubcategoryIds = computed(() => (supplierFilters.value.subcategory_ids ?? []).map((id) => Number(id)));
const selectedSupplierSubcategories = computed(() => supplierSubcategoryOptions.value
    .filter((subcategory) => selectedSupplierSubcategoryIds.value.includes(Number(subcategory.id))));
const orderedSupplierSubcategoryGroups = computed(() => {
    const groups = supplierSubcategoryGroups.value ?? [];

    if (!groups.length) {
        return [];
    }

    const groupMap = new Map(groups.map((group) => [group.slug, group]));
    const orderedGroups = selectedSupplierCategories.value
        .map((category) => groupMap.get(category.slug))
        .filter(Boolean);
    const orderedGroupSlugs = new Set(orderedGroups.map((group) => group.slug));

    return [
        ...orderedGroups,
        ...groups.filter((group) => !orderedGroupSlugs.has(group.slug)),
    ];
});
const filteredSupplierSubcategoryGroups = computed(() => {
    const needle = normalizeSupplierPickerSearch(supplierSubcategorySearch.value);
    const prioritizeGroupSubcategories = (subcategories = []) => prioritizeSelectedSupplierOptions(
        subcategories,
        (subcategory) => subcategory.id,
        selectedSupplierSubcategoryIds.value,
    );

    if (!needle) {
        return orderedSupplierSubcategoryGroups.value
            .map((group) => ({
                ...group,
                subcategories: prioritizeGroupSubcategories(group.subcategories ?? []),
            }))
            .filter((group) => group.subcategories.length > 0);
    }

    return orderedSupplierSubcategoryGroups.value
        .map((group) => {
            const groupMatches = normalizeSupplierPickerSearch(group.name ?? '').includes(needle);

            if (groupMatches) {
                return {
                    ...group,
                    subcategories: prioritizeGroupSubcategories(group.subcategories ?? []),
                };
            }

            return {
                ...group,
                subcategories: prioritizeGroupSubcategories(
                    rankSupplierPickerOptions(
                        group.subcategories ?? [],
                        supplierSubcategorySearch.value,
                        (subcategory) => `${group.name ?? ''} ${subcategory.name ?? ''} ${subcategory.slug ?? ''}`,
                    ),
                ),
            };
        })
        .filter((group) => group.subcategories.length > 0);
});
const filteredSupplierSubcategoryOptions = computed(() => filteredSupplierSubcategoryGroups.value
    .flatMap((group) => group.subcategories ?? []));
const selectedSupplierBrandIds = computed(() => (supplierFilters.value.brand_ids ?? []).map((id) => Number(id)));
const selectedSupplierBrands = computed(() => supplierBrandOptions.value
    .filter((brand) => selectedSupplierBrandIds.value.includes(Number(brand.id))));
const filteredSupplierBrandOptions = computed(() => prioritizeSelectedSupplierOptions(
    rankSupplierPickerOptions(
        supplierBrandOptions.value,
        supplierBrandSearch.value,
        (brand) => brand.name,
    ),
    (brand) => brand.id,
    selectedSupplierBrandIds.value,
));

const hasSupplierManualFilters = computed(() => (
    selectedSupplierCategoryIds.value.length > 0
    || selectedSupplierSubcategoryIds.value.length > 0
    || selectedSupplierBrandIds.value.length > 0
));
const canRequestSupplierSuggestions = computed(() => {
    if (form.request_type === 'service_request') {
        return String(form.service_title ?? '').trim().length > 0
            || String(form.service_description ?? '').trim().length > 0;
    }

    return (form.items ?? []).some((item) => (
        String(item?.product_name ?? '').trim().length > 0
        || String(item?.manufacturer ?? '').trim().length > 0
        || String(item?.model_type ?? '').trim().length > 0
        || String(item?.catalog_code ?? '').trim().length > 0
        || String(item?.part_no ?? '').trim().length > 0
        || String(item?.comments ?? '').trim().length > 0
    ));
});

const selectedSupplierCategoriesLabel = computed(() => {
    if (!selectedSupplierCategories.value.length) {
        return 'All Categories';
    }

    if (selectedSupplierCategories.value.length === 1) {
        return selectedSupplierCategories.value[0].name;
    }

    return `${selectedSupplierCategories.value.length} categories selected`;
});

const selectedSupplierSubcategoriesLabel = computed(() => {
    if (!selectedSupplierCategoryIds.value.length) {
        return 'Select Category First';
    }

    if (!selectedSupplierSubcategories.value.length && selectedSupplierSubcategoryIds.value.length === 1) {
        return '1 subcategory selected';
    }

    if (!selectedSupplierSubcategories.value.length && selectedSupplierSubcategoryIds.value.length > 1) {
        return `${selectedSupplierSubcategoryIds.value.length} subcategories selected`;
    }

    if (!selectedSupplierSubcategories.value.length) {
        return 'All Subcategories';
    }

    if (selectedSupplierSubcategories.value.length === 1) {
        return selectedSupplierSubcategories.value[0].name;
    }

    return `${selectedSupplierSubcategories.value.length} subcategories selected`;
});

const selectedSupplierBrandsLabel = computed(() => {
    if (!selectedSupplierBrandIds.value.length) {
        return 'All Brands';
    }

    if (selectedSupplierBrandIds.value.length === 1) {
        return selectedSupplierBrands.value[0]?.name ?? '1 brand selected';
    }

    return `${selectedSupplierBrandIds.value.length} brands selected`;
});

const supplierSuggestionCellEntries = (entries = []) => (entries ?? []).map((entry) => ({
    label: entry?.confidence_label ? `${entry.name} - ${entry.confidence_label}` : entry?.name,
    reason: entry?.reason ?? '',
}));

const supplierSuggestionPreviewRows = computed(() => {
    if (!supplierSuggestions.value) {
        return [];
    }

    if (Array.isArray(supplierSuggestions.value.row_suggestions) && supplierSuggestions.value.row_suggestions.length) {
        return supplierSuggestions.value.row_suggestions
            .filter((row) => (row.brands?.length || row.categories?.length || row.subcategories?.length))
            .map((row) => ({
                source: row.source || 'Detected request line',
                confidence: row.confidence?.score ? `${row.confidence.label} ${row.confidence.score}%` : '',
                brands: supplierSuggestionCellEntries(row.brands),
                categories: supplierSuggestionCellEntries(row.categories),
                subcategories: supplierSuggestionCellEntries(row.subcategories),
            }));
    }

    const sourceSeeds = form.request_type === 'service_request'
        ? [String(form.service_title ?? '').trim() || 'Service request']
        : (form.items ?? [])
            .map((item) => String(item?.product_name ?? '').trim() || String(item?.manufacturer ?? '').trim())
            .filter(Boolean);

    const groups = new Map();
    const ensureGroup = (sourceLabel) => {
        const key = String(sourceLabel || 'Detected request line').trim() || 'Detected request line';

        if (!groups.has(key)) {
            groups.set(key, {
                source: key,
                brands: [],
                categories: [],
                subcategories: [],
            });
        }

        return groups.get(key);
    };

    sourceSeeds.forEach((source) => {
        ensureGroup(source);
    });

    const appendEntries = (entries, key) => {
        (entries ?? []).forEach((entry) => {
            const group = ensureGroup(entry.source);

            if (!group[key].some((item) => item.id === entry.id)) {
                group[key].push(entry);
            }
        });
    };

    appendEntries(supplierSuggestions.value.brands, 'brands');
    appendEntries(supplierSuggestions.value.categories, 'categories');
    appendEntries(supplierSuggestions.value.subcategories, 'subcategories');

    return [...groups.values()]
        .filter((group) => group.brands.length || group.categories.length || group.subcategories.length)
        .map((group) => ({
            source: group.source,
            confidence: '',
            brands: supplierSuggestionCellEntries(group.brands),
            categories: supplierSuggestionCellEntries(group.categories),
            subcategories: supplierSuggestionCellEntries(group.subcategories),
        }));
});

const supplierSuggestionsHasFilters = computed(() => Boolean(
    supplierSuggestions.value?.filters?.category_ids?.length
    || supplierSuggestions.value?.filters?.subcategory_ids?.length
    || supplierSuggestions.value?.filters?.brand_ids?.length
));

const supplierSuggestionScopeWarning = computed(() => {
    const hasCountries = (form.country_names ?? []).length > 0;
    const hasPorts = hasSupplierPortsForEverySelectedCountry(
        form.country_names ?? [],
        form.ports_by_country ?? {},
    );

    return !hasCountries || !hasPorts
        ? 'Suggested filters will still be applied, but supplier results will load after you choose request country and ports in General Information.'
        : '';
});

const createEmptyPreviewItem = () => ({
    product_name: '',
    part_no: '',
    manufacturer: '',
    model_type: '',
    catalog_code: '',
    serial_number: '',
    drawing_number: '',
    quantity: '',
    unit: '',
    rob: '',
    quality: '',
    comments: '',
});

const seedImportPreviewDraft = (preview) => {
    if (!preview) {
        importPreviewDraft.value = null;
        importPreviewEditing.value = false;
        return;
    }

    const canonicalCountry = canonicalOptionMatch(props.countryOptions, preview.general?.country ?? '');
    const canonicalCurrency = canonicalOptionMatch(currencyOptions, preview.general?.currency ?? '');
    const canonicalPriority = canonicalOptionMatch(priorityOptions, preview.general?.priority ?? '');
    const canonicalStatus = canonicalOptionMatch(statusOptions, preview.general?.status ?? '');

    importPreviewDraft.value = {
        general: {
            reference_no: preview.general?.reference_no ?? '',
            company_name: preview.general?.company_name ?? '',
            ship_name: preview.general?.ship_name ?? '',
            status: canonicalStatus || preview.general?.status || '',
            country: canonicalCountry || preview.general?.country || '',
            port: preview.general?.port ?? '',
            requisition_date: preview.general?.requisition_date ?? '',
            due_date: preview.general?.due_date ?? '',
            currency: canonicalCurrency || preview.general?.currency || '',
            priority: canonicalPriority || preview.general?.priority || '',
            general_notes: preview.general?.general_notes ?? '',
        },
        items: Array.isArray(preview.items) && preview.items.length
            ? preview.items.map((item) => ({
                ...createEmptyPreviewItem(),
                ...item,
            }))
            : [createEmptyPreviewItem()],
    };
    importPreviewEditing.value = false;
};

const spreadsheetExtensions = new Set(['csv', 'xlsx', 'xls']);
const importExtension = (file) => String(file?.name ?? '').split('.').pop()?.toLowerCase() ?? '';
const currencyOptions = ['USD', 'EUR', 'CNY', 'AED'];
const priorityOptions = ['low', 'normal', 'high', 'critical'];
const statusOptions = ['open', 'closed'];

const formatOptionLabel = (value) => String(value ?? '')
    .split(/[_\s-]+/)
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');

const todayIso = () => new Date().toISOString().slice(0, 10);
const hasError = (key) => Boolean(form.errors[key]);
const getError = (key) => form.errors[key] ?? '';
const itemKey = (index, field) => `items.${index}.${field}`;
const hasItemError = (index, field) => hasError(itemKey(index, field));

const itemErrorMessages = {
    product_name: 'Product is required.',
    quantity: 'Qty required.',
    unit: 'Unit required.',
};

const SERVICE_TITLE_MAX_CHARACTERS = 120;
const SERVICE_DESCRIPTION_MIN_CHARACTERS = 200;

const fallbackItemErrorMessage = (field, error) => {
    if (!error) {
        return '';
    }

    if (!error.startsWith('The items.')) {
        return error;
    }

    if (field === 'quantity' && error.includes('must be greater than 0')) {
        return 'Qty must be greater than 0.';
    }

    if (field === 'quantity' && error.includes('must be a number')) {
        return 'Qty must be a number.';
    }

    return itemErrorMessages[field] ?? error;
};

const getItemError = (index, field) => fallbackItemErrorMessage(field, getError(itemKey(index, field)));

const clearFieldErrorIfValid = (key, isValid) => {
    if (isValid) {
        form.clearErrors(key);
    }
};

const validateRequiredText = (value) => String(value ?? '').trim().length > 0;
const validateRequiredSelect = (value) => String(value ?? '').trim().length > 0;
const validateQuantity = (value) => Number(value) > 0;
const validateDateNotPast = (value) => Boolean(value) && String(value) >= todayIso();
const validateFiles = (files) => Array.isArray(files) && files.length > 0;
const validatePortsSelection = (portsByCountry) => Object.values(portsByCountry ?? {}).some((ids) => Array.isArray(ids) && ids.length > 0);
const countCharacters = (value) => String(value ?? '').trim().length;
const validateServiceTitle = (value) => validateRequiredText(value) && countCharacters(value) <= SERVICE_TITLE_MAX_CHARACTERS;
const validateServiceDescription = (value) => {
    const characters = countCharacters(value);
    return characters >= SERVICE_DESCRIPTION_MIN_CHARACTERS;
};
const normalizeImportLookup = (value) => String(value ?? '')
    .toLowerCase()
    .replace(/[^\p{L}\p{N}]+/gu, ' ')
    .replace(/\s+/g, ' ')
    .trim();

const matchesImportedValue = (candidate, imported) => {
    const left = normalizeImportLookup(candidate);
    const right = normalizeImportLookup(imported);

    if (!left || !right) {
        return false;
    }

    return left === right
        || left.includes(right)
        || right.includes(left);
};

const matchesImportedCountry = (candidate, imported) => {
    const left = normalizeImportLookup(candidate);
    const right = normalizeImportLookup(imported);

    if (!left || !right) {
        return false;
    }

    if (left === right) {
        return true;
    }

    const leftTokens = left.split(' ');
    const rightTokens = right.split(' ');

    return leftTokens.length === rightTokens.length
        && leftTokens.every((token, index) => token === rightTokens[index]);
};

const canonicalOptionMatch = (options, imported) => options.find((option) => matchesImportedValue(option, imported)) ?? '';
const isServiceRequest = computed(() => form.request_type === 'service_request');
const serviceTitleCharacterCount = computed(() => countCharacters(form.service_title));
const serviceDescriptionCharacterCount = computed(() => countCharacters(form.service_description));

const createEmptyItem = () => ({
    id: null,
    product_name: '',
    part_no: '',
    quantity: '',
    unit: '',
    manufacturer: '',
    model_type: '',
    serial_number: '',
    catalog_code: '',
    rob: '',
    drawing_number: '',
    quality: '',
    comments: '',
    files: [],
});

const isBrowserFile = (file) => typeof File !== 'undefined' && file instanceof File;
const isPersistedAttachment = (file) => Boolean(
    file
    && typeof file === 'object'
    && !Array.isArray(file)
    && !isBrowserFile(file)
    && Number.isInteger(Number(file.id))
);
const serializeAttachmentSelection = (files = []) => {
    const collection = Array.isArray(files) ? files : [];

    return {
        newFiles: collection.filter((file) => isBrowserFile(file)),
        existingAttachmentIds: collection
            .filter((file) => isPersistedAttachment(file))
            .map((file) => Number(file.id))
            .filter((id) => Number.isInteger(id) && id > 0),
    };
};

const addItem = () => {
    form.items.push(createEmptyItem());
};

const removeItem = (index) => {
    if (form.items.length === 1) {
        return;
    }

    form.items.splice(index, 1);
    delete fileInputRefs.value[index];
};

const setFileInputRef = (index) => (element) => {
    if (element) {
        fileInputRefs.value[index] = element;
    }
};

const openFilePicker = (index) => {
    fileInputRefs.value[index]?.click();
};

const handleFiles = (event, index) => {
    const incomingFiles = Array.from(event.target.files ?? []);
    const currentFiles = Array.isArray(form.items[index].files) ? form.items[index].files : [];
    const mergedFiles = [...currentFiles];

    incomingFiles.forEach((file) => {
        const exists = mergedFiles.some((existing) => (
            existing.name === file.name
            && Number(existing.size ?? -1) === file.size
            && (
                (typeof existing.lastModified === 'number' && existing.lastModified === file.lastModified)
                || isPersistedAttachment(existing)
            )
        ));

        if (!exists) {
            mergedFiles.push(file);
        }
    });

    form.items[index].files = mergedFiles;
    event.target.value = '';
    clearFieldErrorIfValid(itemKey(index, 'files'), validateFiles(form.items[index].files));
};

const removeFile = (itemIndex, fileIndex) => {
    form.items[itemIndex].files = (form.items[itemIndex].files ?? []).filter((_, index) => index !== fileIndex);
};

const openServiceFilePicker = () => {
    serviceFileInputRef.value?.click();
};

const handleServiceFiles = (event) => {
    const incomingFiles = Array.from(event.target.files ?? []);
    const currentFiles = Array.isArray(form.service_files) ? form.service_files : [];
    const mergedFiles = [...currentFiles];

    incomingFiles.forEach((file) => {
        const exists = mergedFiles.some((existing) => (
            existing.name === file.name
            && Number(existing.size ?? -1) === file.size
            && (
                (typeof existing.lastModified === 'number' && existing.lastModified === file.lastModified)
                || isPersistedAttachment(existing)
            )
        ));

        if (!exists) {
            mergedFiles.push(file);
        }
    });

    form.service_files = mergedFiles;
    event.target.value = '';
    clearFieldErrorIfValid('service_files', validateFiles(form.service_files));
};

const removeServiceFile = (fileIndex) => {
    form.service_files = (form.service_files ?? []).filter((_, index) => index !== fileIndex);
};

const serviceFileTriggerLabel = computed(() => {
    const count = Array.isArray(form.service_files) ? form.service_files.length : 0;

    if (count === 0) {
        return 'Upload Files';
    }

    return `${count} file${count === 1 ? '' : 's'} selected`;
});

const attachmentViewer = ref(null);
const attachmentIndex = ref(0);
const attachmentObjectUrls = new Map();

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

const attachmentPreviewUrl = (attachment) => {
    if (!attachment) {
        return null;
    }

    if (attachment.url) {
        return normalizeAttachmentUrl(attachment.url);
    }

    if (!isBrowserFile(attachment) || typeof URL === 'undefined') {
        return null;
    }

    if (!attachmentObjectUrls.has(attachment)) {
        attachmentObjectUrls.set(attachment, URL.createObjectURL(attachment));
    }

    return attachmentObjectUrls.get(attachment);
};

const canPreviewAttachment = (attachment) => Boolean(
    attachment?.url
    || isBrowserFile(attachment)
);

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

    const previewableAttachments = attachments.filter((attachment) => canPreviewAttachment(attachment));

    if (previewableAttachments.length === 0) {
        return;
    }

    const targetAttachment = attachments[startIndex] ?? previewableAttachments[0];
    const resolvedStartIndex = Math.max(
        0,
        previewableAttachments.findIndex((attachment) => attachment === targetAttachment)
    );

    attachmentViewer.value = previewableAttachments;
    attachmentIndex.value = resolvedStartIndex >= 0 ? resolvedStartIndex : 0;
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

const revokeAttachmentObjectUrls = () => {
    if (typeof URL === 'undefined') {
        return;
    }

    attachmentObjectUrls.forEach((objectUrl) => URL.revokeObjectURL(objectUrl));
    attachmentObjectUrls.clear();
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

const setRequestType = (nextType) => {
    if (requestTypeLocked.value) {
        return;
    }

    if (nextType === form.request_type) {
        return;
    }

    form.request_type = nextType;

    if (nextType === 'service_request') {
        form.items = [createEmptyItem()];
        fileInputRefs.value = {};
    } else {
        form.service_title = '';
        form.service_description = '';
        form.service_files = [];
    }

    form.clearErrors('request_type', 'service_title', 'service_description', 'service_files', 'items');
};

const syncSupplierScopeFromGeneral = () => {
    supplierFilters.value = {
        ...supplierFilters.value,
        country_names: [...(form.country_names ?? [])],
        ports_by_country: Object.fromEntries(
            Object.entries(form.ports_by_country ?? {}).map(([country, ids]) => [country, [...ids]])
        ),
    };
};

const isSupplierCountrySelected = (country) => selectedSupplierCountries.value.includes(country);

const toggleSupplierCountry = async (country) => {
    supplierScopeCustomized.value = true;

    if (isSupplierCountrySelected(country)) {
        supplierFilters.value.country_names = supplierFilters.value.country_names.filter((item) => item !== country);
        const nextPorts = { ...(supplierFilters.value.ports_by_country ?? {}) };
        delete nextPorts[country];
        supplierFilters.value.ports_by_country = nextPorts;
        return;
    }

    supplierFilters.value.country_names = [...supplierFilters.value.country_names, country];
    supplierFilters.value.ports_by_country = {
        ...(supplierFilters.value.ports_by_country ?? {}),
        [country]: supplierFilters.value.ports_by_country?.[country] ?? [],
    };

    await loadSupplierPortsForCountry(country);
};

const isSupplierPortSelected = (country, portId) => (supplierFilters.value.ports_by_country?.[country] ?? []).includes(portId);

const toggleSupplierPort = (country, portId) => {
    supplierScopeCustomized.value = true;
    const current = [...(supplierFilters.value.ports_by_country?.[country] ?? [])];
    const next = current.includes(portId)
        ? current.filter((item) => item !== portId)
        : [...current, portId];

    supplierFilters.value.ports_by_country = {
        ...(supplierFilters.value.ports_by_country ?? {}),
        [country]: next,
    };
};

const selectAllSupplierPortsForCountry = (country) => {
    supplierScopeCustomized.value = true;
    const ports = supplierPortOptionsState.value[country] ?? [];

    supplierFilters.value.ports_by_country = {
        ...(supplierFilters.value.ports_by_country ?? {}),
        [country]: ports.map((port) => port.id),
    };
};

const selectAllSupplierPorts = () => {
    supplierScopeCustomized.value = true;
    const next = { ...(supplierFilters.value.ports_by_country ?? {}) };

    selectedSupplierCountryPortGroups.value.forEach((group) => {
        next[group.country] = group.ports.map((port) => port.id);
    });

    supplierFilters.value.ports_by_country = next;
};

const clearAllSupplierPorts = () => {
    supplierScopeCustomized.value = true;
    const next = { ...(supplierFilters.value.ports_by_country ?? {}) };

    selectedSupplierCountryPortGroups.value.forEach((group) => {
        next[group.country] = [];
    });

    supplierFilters.value.ports_by_country = next;
};

const isSupplierCategorySelected = (categoryId) => selectedSupplierCategoryIds.value.includes(Number(categoryId));

const resolveSupplierCategorySlugsFromIds = (categoryIds = []) => supplierCategoryOptions.value
    .filter((category) => categoryIds.includes(Number(category.id)))
    .map((category) => category.slug)
    .filter(Boolean);

const toggleSupplierCategory = async (categoryId) => {
    supplierScopeCustomized.value = true;
    const normalizedId = Number(categoryId);
    const currentIds = [...selectedSupplierCategoryIds.value];
    const nextCategoryIds = currentIds.includes(normalizedId)
        ? currentIds.filter((id) => id !== normalizedId)
        : [...currentIds, normalizedId];

    supplierFilters.value.category_ids = nextCategoryIds;

    const nextCategorySlugs = resolveSupplierCategorySlugsFromIds(nextCategoryIds);

    if (!nextCategorySlugs.length) {
        pendingSuggestedSupplierSubcategoryIds.value = [];
        supplierFilters.value.subcategory_ids = [];
        return;
    }

    await loadSupplierSubcategories(nextCategorySlugs);
};

const isSupplierSubcategorySelected = (subcategoryId) => selectedSupplierSubcategoryIds.value.includes(Number(subcategoryId));

const toggleSupplierSubcategory = (subcategoryId) => {
    if (!selectedSupplierCategoryIds.value.length) {
        return;
    }

    supplierScopeCustomized.value = true;
    const normalizedId = Number(subcategoryId);
    const currentIds = [...selectedSupplierSubcategoryIds.value];

    supplierFilters.value.subcategory_ids = currentIds.includes(normalizedId)
        ? currentIds.filter((id) => id !== normalizedId)
        : [...currentIds, normalizedId];
};

const selectAllSupplierSubcategories = () => {
    if (!selectedSupplierCategoryIds.value.length) {
        return;
    }

    supplierScopeCustomized.value = true;
    supplierFilters.value.subcategory_ids = supplierSubcategoryOptions.value.map((subcategory) => Number(subcategory.id));
};

const clearAllSupplierSubcategories = () => {
    supplierScopeCustomized.value = true;
    supplierFilters.value.subcategory_ids = [];
};

const isSupplierBrandSelected = (brandId) => selectedSupplierBrandIds.value.includes(Number(brandId));

const toggleSupplierBrand = (brandId) => {
    supplierScopeCustomized.value = true;
    const normalizedId = Number(brandId);
    const currentIds = [...selectedSupplierBrandIds.value];

    supplierFilters.value.brand_ids = currentIds.includes(normalizedId)
        ? currentIds.filter((id) => id !== normalizedId)
        : [...currentIds, normalizedId];
};

const openImportPicker = () => {
    importFileInputRef.value?.click();
};

const clearImportPreview = () => {
    importPreview.value = null;
    importPreviewDraft.value = null;
    importError.value = '';
    importPreviewOpen.value = false;
    importPreviewEditing.value = false;

    if (importFileInputRef.value) {
        importFileInputRef.value.value = '';
    }
};

const openImportTemplate = () => {
    importTemplateError.value = '';
    importTemplateOpen.value = true;
};

const closeImportTemplate = () => {
    importTemplateOpen.value = false;
};

const saveImportTemplate = async () => {
    importTemplateSaving.value = true;
    importTemplateError.value = '';

    try {
        const response = await fetch('/requests/import-template', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...getRequestCsrfHeaders(),
            },
            body: JSON.stringify({
                name: importTemplateForm.value.name,
                general: importTemplateForm.value.general,
                items: importTemplateForm.value.items,
            }),
        });

        const result = await response.json();

        if (!response.ok) {
            importTemplateError.value = response.status === 419
                ? getCsrfMismatchMessage('We could not save this import template.')
                : (result?.message ?? 'We could not save this import template.');
            return;
        }

        importTemplateSaved.value = true;
        importTemplateOpen.value = false;
    } catch (error) {
        importTemplateError.value = 'We could not save this import template.';
    } finally {
        importTemplateSaving.value = false;
    }
};

const loadSupplierPortsForCountry = async (country) => {
    if (!country || supplierPortOptionsState.value[country]) {
        return;
    }

    try {
        const params = new URLSearchParams();
        params.append('countries[]', country);
        const response = await fetch(`/requests/ports?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Failed to load supplier ports.');
        }

        const result = await response.json();
        supplierPortOptionsState.value = {
            ...supplierPortOptionsState.value,
            ...(result.portsByCountry ?? {}),
        };
    } catch (error) {
        supplierMatchesError.value = 'We could not load supplier ports.';
    }
};

const loadSupplierBrands = async () => {
    if (supplierBrandOptionsLoaded.value || supplierBrandOptionsLoading.value) {
        return;
    }

    supplierBrandOptionsLoading.value = true;
    supplierBrandOptionsError.value = '';

    try {
        const response = await fetch(props.supplierBrandsUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result?.message ?? 'We could not load supplier brands.');
        }

        supplierBrandOptionsState.value = result.brands ?? [];
        supplierBrandOptionsLoaded.value = true;
    } catch (error) {
        supplierBrandOptionsError.value = 'We could not load supplier brands.';
    } finally {
        supplierBrandOptionsLoading.value = false;
    }
};

const loadSupplierSubcategories = async (categorySlugs = selectedSupplierCategorySlugs.value) => {
    const normalizedSlugs = [...new Set((categorySlugs ?? []).map((slug) => String(slug).trim()).filter(Boolean))].sort();
    const cacheKey = normalizedSlugs.join(',');

    if (!cacheKey || supplierSubcategoryOptionsState.value[cacheKey] || supplierSubcategoryLoadingKeys.value[cacheKey]) {
        return;
    }

    supplierSubcategoryLoadingKeys.value = {
        ...supplierSubcategoryLoadingKeys.value,
        [cacheKey]: true,
    };
    supplierSubcategoryErrorKeys.value = {
        ...supplierSubcategoryErrorKeys.value,
        [cacheKey]: '',
    };

    try {
        const params = new URLSearchParams();
        normalizedSlugs.forEach((slug) => {
            params.append('categories[]', slug);
        });

        const response = await fetch(`${props.supplierSubcategoriesUrl}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result?.message ?? 'We could not load supplier subcategories.');
        }

        supplierSubcategoryOptionsState.value = {
            ...supplierSubcategoryOptionsState.value,
            [cacheKey]: result.groups ?? [],
        };
    } catch (error) {
        supplierSubcategoryErrorKeys.value = {
            ...supplierSubcategoryErrorKeys.value,
            [cacheKey]: 'We could not load supplier subcategories.',
        };
    } finally {
        supplierSubcategoryLoadingKeys.value = {
            ...supplierSubcategoryLoadingKeys.value,
            [cacheKey]: false,
        };
    }
};

const requestSupplierSuggestions = async () => {
    if (!canRequestSupplierSuggestions.value || supplierSuggestionsLoading.value) {
        return;
    }

    supplierSelectionMode.value = 'suggested';
    supplierSuggestionsOpen.value = true;
    supplierSuggestionsLoading.value = true;
    supplierSuggestionsError.value = '';
    supplierSuggestionsApplied.value = false;

    try {
        const response = await fetch(props.supplierSuggestionsUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...getRequestCsrfHeaders(),
            },
            body: JSON.stringify({
                request_type: form.request_type,
                service_title: form.service_title,
                service_description: form.service_description,
                items: (form.items ?? []).map((item) => ({
                    product_name: item.product_name,
                    part_no: item.part_no,
                    manufacturer: item.manufacturer,
                    model_type: item.model_type,
                    catalog_code: item.catalog_code,
                    comments: item.comments,
                })),
            }),
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(
                response.status === 419
                    ? getCsrfMismatchMessage('We could not build suggested supplier filters.')
                    : (result?.message ?? 'We could not build suggested supplier filters.')
            );
        }

        supplierSuggestions.value = result;
    } catch (error) {
        supplierSuggestions.value = null;
        supplierSuggestionsError.value = error instanceof Error
            ? error.message
            : 'We could not build suggested supplier filters.';
    } finally {
        supplierSuggestionsLoading.value = false;
    }
};

const closeSupplierSuggestions = () => {
    supplierSuggestionsOpen.value = false;
    supplierSelectionMode.value = 'manual';
};

const setSupplierSelectionMode = (nextMode) => {
    if (nextMode === 'suggested') {
        requestSupplierSuggestions();
        return;
    }

    supplierSelectionMode.value = 'manual';
};

const applySupplierSuggestions = async () => {
    const filters = supplierSuggestions.value?.filters;

    if (!filters) {
        return;
    }

    const suggestedCategoryIds = [...(filters.category_ids ?? [])].map((id) => Number(id));
    const suggestedSubcategoryIds = [...(filters.subcategory_ids ?? [])].map((id) => Number(id));
    const suggestedBrandIds = [...(filters.brand_ids ?? [])].map((id) => Number(id));
    const suggestedCategorySlugs = supplierCategoryOptions.value
        .filter((category) => suggestedCategoryIds.includes(Number(category.id)))
        .map((category) => category.slug)
        .filter(Boolean);

    pendingSuggestedSupplierSubcategoryIds.value = [...suggestedSubcategoryIds];
    supplierScopeCustomized.value = true;
    supplierFilters.value = {
        ...supplierFilters.value,
        category_ids: suggestedCategoryIds,
        subcategory_ids: suggestedSubcategoryIds,
        brand_ids: suggestedBrandIds,
    };
    supplierSuggestionsApplied.value = true;

    if (suggestedBrandIds.length > 0) {
        await loadSupplierBrands();
    }

    if (suggestedCategorySlugs.length > 0) {
        await loadSupplierSubcategories(suggestedCategorySlugs);
    }

    await nextTick();

    if (suggestedSubcategoryIds.length > 0) {
        supplierFilters.value = {
            ...supplierFilters.value,
            subcategory_ids: suggestedSubcategoryIds,
        };
        pendingSuggestedSupplierSubcategoryIds.value = [];
    }

    supplierSuggestionsOpen.value = false;
    supplierSelectionMode.value = 'manual';
};

const findSupplierMatches = async () => {
    const requestId = ++supplierMatchesRequestId;
    const selectedCountries = (supplierFilters.value.country_names?.length
        ? supplierFilters.value.country_names
        : (form.country_names ?? []));
    const selectedPortsByCountry = Object.keys(supplierFilters.value.ports_by_country ?? {}).length
        ? (supplierFilters.value.ports_by_country ?? {})
        : (form.ports_by_country ?? {});
    const selectedPortIds = Object.values(
        selectedPortsByCountry
    ).flatMap((ids) => ids ?? []);
    const hasSupplierScope = Array.isArray(selectedCountries)
        && hasSupplierPortsForEverySelectedCountry(selectedCountries, selectedPortsByCountry);

    if (!hasSupplierScope) {
        supplierMatches.value = [];
        supplierMatchesLoaded.value = false;
        supplierMatchesError.value = '';
        if (!isSupplierTargetedRequest.value) {
            form.supplier_recipient_ids = [];
        }
        return;
    }

    supplierMatchesLoading.value = true;
    supplierMatchesError.value = '';

    try {
        const params = new URLSearchParams();

        selectedCountries.forEach((country) => {
            params.append('country_names[]', country);
        });

        selectedPortIds.forEach((id) => {
            params.append('port_ids[]', String(id));
        });

        (supplierFilters.value.category_ids ?? []).forEach((id) => {
            params.append('category_ids[]', String(id));
        });

        (supplierFilters.value.subcategory_ids ?? []).forEach((id) => {
            params.append('subcategory_ids[]', String(id));
        });

        (supplierFilters.value.brand_ids ?? []).forEach((id) => {
            params.append('brand_ids[]', String(id));
        });

        supplierTargetCandidateIds.value.forEach((id) => {
            params.append('candidate_ids[]', String(id));
        });

        const query = params.toString();
        const response = await fetch(`${props.supplierMatchesUrl}${query ? `?${query}` : ''}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result?.message ?? 'We could not load supplier matches.');
        }

        if (requestId !== supplierMatchesRequestId) {
            return;
        }

        supplierMatches.value = result.suppliers ?? [];
        form.supplier_recipient_ids = supplierMatches.value.map((supplier) => supplier.id);
        supplierMatchesLoaded.value = true;
        clearFieldErrorIfValid('supplier_recipient_ids', form.supplier_recipient_ids.length > 0);
    } catch (error) {
        if (requestId !== supplierMatchesRequestId) {
            return;
        }

        supplierMatches.value = [];
        supplierMatchesLoaded.value = true;
        supplierMatchesError.value = 'We could not load supplier matches.';
        form.supplier_recipient_ids = [];
    } finally {
        if (requestId === supplierMatchesRequestId) {
            supplierMatchesLoading.value = false;
        }
    }
};

const clearSupplierFilters = async () => {
    syncSupplierScopeFromGeneral();
    supplierSuggestionsApplied.value = false;
    supplierFilters.value = {
        ...supplierFilters.value,
        category_ids: [],
        subcategory_ids: [],
        brand_ids: [],
    };

    await findSupplierMatches();
};

const handleImportSelection = async (event) => {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    importParsing.value = true;
    importError.value = '';
    importPreview.value = null;

    try {
        let response;

        if (spreadsheetExtensions.has(importExtension(file))) {
            const payload = new FormData();
            payload.append('file', file);

            response = await fetch('/requests/import-preview', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...getRequestCsrfHeaders(),
                },
                body: payload,
            });
        } else if (importExtension(file) === 'pdf' || isStructuredDocumentImport(file)) {
            const extension = importExtension(file);
            const sourceType = extension === 'pdf' ? 'pdf' : 'image';
            let extracted = {
                rows: [],
                ocrLines: [],
                sheetName: file.name,
            };

            try {
                extracted = await extractDocumentRows(file);
            } catch (extractError) {
                console.warn('Structured RFQ import extraction failed before upload.', extractError);
            }

            const payload = new FormData();
            payload.append('file', file);
            payload.append('rows_payload', JSON.stringify(extracted.rows ?? []));
            payload.append('ocr_lines_payload', JSON.stringify(extracted.ocrLines ?? []));
            payload.append('sheet_name', extracted.sheetName ?? file.name);
            payload.append('source_type', sourceType);

            response = await fetch('/requests/import-preview', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...getRequestCsrfHeaders(),
                },
                body: payload,
            });
        } else {
            throw new Error('Unsupported file type.');
        }

        let result = {};

        try {
            result = await response.json();
        } catch {
            result = {};
        }

        if (!response.ok) {
            importError.value = response.status === 419
                ? getCsrfMismatchMessage('We could not read this file. Please check the format and try again.')
                : (result?.errors?.file?.[0]
                    ?? result?.message
                    ?? 'We could not read this file. Please check the format and try again.');
            return;
        }

        importPreview.value = result;
        seedImportPreviewDraft(result);
        importPreviewOpen.value = false;
    } catch (error) {
        importError.value = error?.message === 'Unsupported file type.'
            ? 'Upload a PDF, image, CSV, XLSX, or XLS file.'
            : (error?.message || 'We could not read this file. Please check the format and try again.');
    } finally {
        importParsing.value = false;
        event.target.value = '';
    }
};

const tryApplyImportedCountryAndPort = async (general = {}) => {
    const importedCountry = String(general.country ?? '').trim();
    const importedPort = String(general.port ?? '').trim();

    if (!importedCountry) {
        return;
    }

    const matchedCountry = props.countryOptions.find((option) => matchesImportedCountry(option, importedCountry));

    if (!matchedCountry) {
        return;
    }

    form.country_names = [matchedCountry];
    await loadPortsForCountries([matchedCountry]);
    clearFieldErrorIfValid('country_names', form.country_names.length > 0);

    if (!importedPort) {
        form.ports_by_country = { [matchedCountry]: [] };
        return;
    }

    const ports = portsByCountryState.value[matchedCountry] ?? [];
    const matchedPort = ports.find((port) => {
        const name = String(port.name ?? '');
        const code = String(port.unlocode ?? '');

        return matchesImportedValue(name, importedPort)
            || matchesImportedValue(code, importedPort);
    });

    form.ports_by_country = {
        [matchedCountry]: matchedPort ? [matchedPort.id] : [],
    };
    clearFieldErrorIfValid('ports_by_country', validatePortsSelection(form.ports_by_country));
};

const applyImportPreview = async () => {
    if (!importPreviewDraft.value) {
        return;
    }

    const { general = {}, items = [] } = importPreviewDraft.value;

    form.reference_no = general.reference_no || form.reference_no;
    form.company_name = general.company_name || form.company_name;
    form.ship_name = general.ship_name || form.ship_name;
    form.requisition_date = general.requisition_date || form.requisition_date;
    form.due_date = general.due_date || form.due_date;
    form.currency = general.currency || form.currency;
    form.priority = general.priority || form.priority;
    form.status = general.status || form.status;
    form.general_notes = general.general_notes || form.general_notes;

    await tryApplyImportedCountryAndPort(general);

    form.items = items.length
        ? items.map((item) => ({
            product_name: item.product_name ?? '',
            part_no: item.part_no ?? '',
            quantity: item.quantity ?? '',
            unit: item.unit ?? '',
            manufacturer: item.manufacturer ?? '',
            model_type: item.model_type ?? '',
            serial_number: item.serial_number ?? '',
            catalog_code: item.catalog_code ?? '',
            rob: item.rob ?? '',
            drawing_number: item.drawing_number ?? '',
            quality: item.quality ?? '',
            comments: item.comments ?? '',
            files: [],
        }))
        : form.items;

    form.clearErrors();
    fileInputRefs.value = {};
    clearImportPreview();
};

const toggleImportPreviewEditing = () => {
    importPreviewEditing.value = !importPreviewEditing.value;
};

const resetImportPreviewDraft = () => {
    seedImportPreviewDraft(importPreview.value);
};

const addPreviewItemRow = () => {
    if (!importPreviewDraft.value) {
        return;
    }

    importPreviewDraft.value.items.push(createEmptyPreviewItem());
};

const removePreviewItemRow = (index) => {
    if (!importPreviewDraft.value || importPreviewDraft.value.items.length === 1) {
        return;
    }

    importPreviewDraft.value.items.splice(index, 1);
};

const openImportPreview = () => {
    if (importPreview.value) {
        importPreviewOpen.value = true;
    }
};

const closeImportPreview = () => {
    importPreviewOpen.value = false;
    importPreviewEditing.value = false;
};

const fileTriggerLabel = (item) => {
    const count = Array.isArray(item.files) ? item.files.length : 0;

    if (count === 0) {
        return 'Upload Files';
    }

    return `${count} file${count === 1 ? '' : 's'} selected`;
};

const previewItemColumns = [
    { key: 'product_name', label: 'Product' },
    { key: 'part_no', label: 'Part No' },
    { key: 'manufacturer', label: 'Manufacturer' },
    { key: 'model_type', label: 'MFG Model / Type' },
    { key: 'catalog_code', label: 'Catalog Code' },
    { key: 'serial_number', label: 'Serial Number' },
    { key: 'drawing_number', label: 'Drawing Number' },
    { key: 'quantity', label: 'Qty' },
    { key: 'unit', label: 'Unit' },
    { key: 'rob', label: 'ROB' },
    { key: 'quality', label: 'Quality' },
    { key: 'comments', label: 'Comments' },
];
const previewGeneralColumns = [
    { key: 'reference_no', label: 'Reference No' },
    { key: 'company_name', label: 'Company' },
    { key: 'ship_name', label: 'Ship' },
    { key: 'status', label: 'RFQ Status' },
    { key: 'country', label: 'Country' },
    { key: 'port', label: 'Ports' },
    { key: 'requisition_date', label: 'Requisition Date' },
    { key: 'due_date', label: 'Due Date' },
    { key: 'currency', label: 'Currency' },
    { key: 'priority', label: 'Priority' },
    { key: 'general_notes', label: 'General Notes', span: 2 },
];
const previewDraftItems = computed(() => importPreviewDraft.value?.items ?? []);
const previewGeneralDisplay = computed(() => {
    const preview = importPreviewDraft.value?.general ?? importPreview.value?.general ?? {};
    const selectedPortNames = Object.entries(form.ports_by_country ?? {})
        .flatMap(([country, ids]) => {
            const ports = portsByCountryState.value[country] ?? [];

            return ids
                .map((id) => ports.find((port) => port.id === id)?.name)
                .filter(Boolean);
        })
        .join(', ');

    return {
        reference_no: preview.reference_no || form.reference_no || '',
        company_name: preview.company_name || form.company_name || '',
        ship_name: preview.ship_name || form.ship_name || '',
        status: preview.status ? formatOptionLabel(preview.status) : (form.status ? formatOptionLabel(form.status) : ''),
        country: preview.country || form.country_names?.join(', ') || '',
        port: preview.port || selectedPortNames || '',
        requisition_date: preview.requisition_date || form.requisition_date || '',
        due_date: preview.due_date || form.due_date || '',
        currency: preview.currency || form.currency || '',
        priority: preview.priority ? formatOptionLabel(preview.priority) : (form.priority ? formatOptionLabel(form.priority) : ''),
        general_notes: preview.general_notes || form.general_notes || '',
    };
});

const selectedCountries = computed(() => form.country_names ?? []);

const selectedCountryPortGroups = computed(() => selectedCountries.value.map((country) => ({
    country,
    ports: portsByCountryState.value[country] ?? [],
})));

const filteredCountryOptions = computed(() => prioritizeSelectedSupplierOptions(
    rankSupplierPickerOptions(
        props.countryOptions ?? [],
        countrySearch.value,
        (country) => country,
    ),
    (country) => country,
    selectedCountries.value,
));

const selectedCountriesLabel = computed(() => {
    if (!selectedCountries.value.length) {
        return 'Select Countries';
    }

    if (selectedCountries.value.length === 1) {
        return selectedCountries.value[0];
    }

    return `${selectedCountries.value.length} countries selected`;
});

const selectedPortsCount = computed(() => Object.values(form.ports_by_country ?? {})
    .reduce((total, ids) => total + ids.length, 0));

const selectedPortsLabel = computed(() => {
    if (!selectedCountries.value.length) {
        return 'Select Countries First';
    }

    if (!selectedPortsCount.value) {
        return 'Select Ports';
    }

    return `${selectedPortsCount.value} ports selected`;
});

const filteredSelectedCountryPortGroups = computed(() => {
    const needle = normalizeSupplierPickerSearch(portSearch.value);

    if (!needle) {
        return selectedCountryPortGroups.value.map((group) => ({
            ...group,
            ports: prioritizeSelectedSupplierOptions(
                group.ports ?? [],
                (port) => port.id,
                form.ports_by_country?.[group.country] ?? [],
            ),
        }));
    }

    return selectedCountryPortGroups.value
        .map((group) => {
            const countryMatches = normalizeSupplierPickerSearch(group.country).includes(needle);

            if (countryMatches) {
                return {
                    ...group,
                    ports: prioritizeSelectedSupplierOptions(
                        group.ports ?? [],
                        (port) => port.id,
                        form.ports_by_country?.[group.country] ?? [],
                    ),
                };
            }

            return {
                ...group,
                ports: prioritizeSelectedSupplierOptions(
                    rankSupplierPickerOptions(
                        group.ports ?? [],
                        portSearch.value,
                        (port) => `${group.country} ${port.name} ${port.unlocode ?? ''}`,
                    ),
                    (port) => port.id,
                    form.ports_by_country?.[group.country] ?? [],
                ),
            };
        })
        .filter((group) => group.ports.length > 0);
});

const generalInformationSectionProps = computed(() => ({
    form,
    importTemplateSaved: importTemplateSaved.value,
    scopeNote: requestScopeNote.value,
    hasError,
    getError,
    clearFieldErrorIfValid,
    validateRequiredText,
    validateDateNotPast,
    validateRequiredSelect,
    openImportTemplate,
    selectedCountries: selectedCountries.value,
    selectedCountriesLabel: selectedCountriesLabel.value,
    selectedPortsCount: selectedPortsCount.value,
    selectedPortsLabel: selectedPortsLabel.value,
    countryMenuOpen: countryMenuOpen.value,
    portMenuOpen: portMenuOpen.value,
    countrySearch: countrySearch.value,
    portSearch: portSearch.value,
    filteredCountryOptions: filteredCountryOptions.value,
    filteredSelectedCountryPortGroups: filteredSelectedCountryPortGroups.value,
    selectedCountryPortGroups: selectedCountryPortGroups.value,
    toggleCountryMenu,
    handleCountryTriggerKeydown,
    handleCountryMenuKeydown,
    isCountrySelected,
    toggleCountry,
    togglePortMenu,
    handlePortTriggerKeydown,
    handlePortMenuKeydown,
    selectAllPorts,
    clearAllPorts,
    selectAllPortsForCountry,
    isPortSelected,
    togglePort,
    todayIso,
    setCountryMenuRef,
    setCountryMenuListRef,
    setPortMenuRef,
    setPortMenuListRef,
}));

const itemsToQuoteSectionProps = computed(() => ({
    form,
    unitOptions: props.unitOptions,
    qualityOptions: props.qualityOptions,
    isEditMode: isEditMode.value,
    isServiceRequest: isServiceRequest.value,
    requestTypeLocked: requestTypeLocked.value,
    canEditRequestContent: canEditRequestContent.value,
    isGeneralOnlyEdit: isGeneralOnlyEdit.value,
    generalOnlyEditMessage: itemsToQuoteLockMessage.value,
    importParsing: importParsing.value,
    importError: importError.value,
    importPreview: importPreview.value,
    serviceTitleCharacterCount: serviceTitleCharacterCount.value,
    serviceDescriptionCharacterCount: serviceDescriptionCharacterCount.value,
    serviceFileTriggerLabel: serviceFileTriggerLabel.value,
    serviceTitleMaxCharacters: SERVICE_TITLE_MAX_CHARACTERS,
    serviceDescriptionMinCharacters: SERVICE_DESCRIPTION_MIN_CHARACTERS,
    hasError,
    getError,
    clearFieldErrorIfValid,
    itemKey,
    hasItemError,
    getItemError,
    validateRequiredText,
    validateQuantity,
    validateRequiredSelect,
    validateServiceTitle,
    validateServiceDescription,
    formatOptionLabel,
    setRequestType,
    openImportPicker,
    handleImportSelection,
    clearImportPreview,
    openImportPreview,
    addItem,
    removeItem,
    setFileInputRef,
    openFilePicker,
    handleFiles,
    removeFile,
    canPreviewAttachment,
    openAttachmentViewer,
    setImportFileInputRef,
    setServiceFileInputRef,
    openServiceFilePicker,
    handleServiceFiles,
    removeServiceFile,
}));

const importTemplateModalProps = computed(() => ({
    importTemplateOpen: importTemplateOpen.value,
    importTemplateSaving: importTemplateSaving.value,
    importTemplateError: importTemplateError.value,
    importTemplateForm: importTemplateForm.value,
    importTemplateGeneralFields,
    importTemplateItemFields,
    closeImportTemplate,
    saveImportTemplate,
}));

const importPreviewModalProps = computed(() => ({
    importPreview: importPreview.value,
    importPreviewOpen: importPreviewOpen.value,
    importPreviewEditing: importPreviewEditing.value,
    previewDraftItems: previewDraftItems.value,
    previewItemColumns,
    previewGeneralColumns,
    previewGeneralDisplay: previewGeneralDisplay.value,
    unitOptions: props.unitOptions,
    qualityOptions: props.qualityOptions,
    formatOptionLabel,
    toggleImportPreviewEditing,
    resetImportPreviewDraft,
    clearImportPreview,
    applyImportPreview,
    addPreviewItemRow,
    removePreviewItemRow,
    closeImportPreview,
}));

const suppliersToSendRfqSectionProps = computed(() => ({
    isGeneralOnlyEdit: isGeneralOnlyEdit.value,
    supplierTarget: supplierTarget.value,
    supplierSelectionMode: supplierSelectionMode.value,
    supplierSuggestionsApplied: supplierSuggestionsApplied.value,
    hasSupplierManualFilters: hasSupplierManualFilters.value,
    selectedCountriesLabel: selectedCountriesLabel.value,
    selectedPortsCount: selectedPortsCount.value,
    selectedPortsLabel: selectedPortsLabel.value,
    selectedSupplierCategoryIds: selectedSupplierCategoryIds.value,
    selectedSupplierCategories: selectedSupplierCategories.value,
    selectedSupplierCategoriesLabel: selectedSupplierCategoriesLabel.value,
    selectedSupplierSubcategories: selectedSupplierSubcategories.value,
    selectedSupplierSubcategoriesLabel: selectedSupplierSubcategoriesLabel.value,
    selectedSupplierBrandIds: selectedSupplierBrandIds.value,
    selectedSupplierBrandsLabel: selectedSupplierBrandsLabel.value,
    supplierCategoryMenuOpen: supplierCategoryMenuOpen.value,
    supplierSubcategoryMenuOpen: supplierSubcategoryMenuOpen.value,
    supplierBrandMenuOpen: supplierBrandMenuOpen.value,
    supplierCategorySearch: supplierCategorySearch.value,
    supplierSubcategorySearch: supplierSubcategorySearch.value,
    supplierBrandSearch: supplierBrandSearch.value,
    filteredSupplierCategoryOptions: filteredSupplierCategoryOptions.value,
    filteredSupplierSubcategoryOptions: filteredSupplierSubcategoryOptions.value,
    filteredSupplierSubcategoryGroups: filteredSupplierSubcategoryGroups.value,
    filteredSupplierBrandOptions: filteredSupplierBrandOptions.value,
    supplierSubcategoryOptionsLoading: supplierSubcategoryOptionsLoading.value,
    supplierSubcategoryOptionsError: supplierSubcategoryOptionsError.value,
    supplierBrandOptionsLoading: supplierBrandOptionsLoading.value,
    supplierBrandOptionsError: supplierBrandOptionsError.value,
    canRequestSupplierSuggestions: canRequestSupplierSuggestions.value,
    supplierSuggestionsLoading: supplierSuggestionsLoading.value,
    supplierSuggestionsError: supplierSuggestionsError.value,
    supplierSuggestions: supplierSuggestions.value,
    supplierSuggestionsOpen: supplierSuggestionsOpen.value,
    supplierSuggestionsHasFilters: supplierSuggestionsHasFilters.value,
    supplierSuggestionScopeWarning: supplierSuggestionScopeWarning.value,
    supplierSuggestionPreviewRows: supplierSuggestionPreviewRows.value,
    supplierMatchesCount: supplierMatches.value.length,
    supplierMatchesLoaded: supplierMatchesLoaded.value,
    supplierMatchesLoading: supplierMatchesLoading.value,
    supplierMatchesError: supplierMatchesError.value,
    hasSupplierRequestScope: hasSupplierRequestScope.value,
    hasSupplierRecipientError: hasError('supplier_recipient_ids'),
    supplierRecipientError: getError('supplier_recipient_ids') || 'No approved suppliers match this private request scope. Change country, ports, category, subcategory, or brand before submitting.',
    setSupplierSelectionMode,
    requestSupplierSuggestions,
    applySupplierSuggestions,
    closeSupplierSuggestions,
    clearSupplierFilters,
    toggleSupplierCategoryMenu,
    toggleSupplierSubcategoryMenu,
    toggleSupplierBrandMenu,
    isSupplierCategorySelected,
    toggleSupplierCategory,
    selectAllSupplierSubcategories,
    clearAllSupplierSubcategories,
    isSupplierSubcategorySelected,
    toggleSupplierSubcategory,
    isSupplierBrandSelected,
    toggleSupplierBrand,
    setSupplierCategoryMenuRef,
    setSupplierCategoryMenuListRef,
    setSupplierSubcategoryMenuRef,
    setSupplierSubcategoryMenuListRef,
    setSupplierBrandMenuRef,
    setSupplierBrandMenuListRef,
}));

const isCountrySelected = (country) => selectedCountries.value.includes(country);

const toggleCountry = (country) => {
    if (isCountrySelected(country)) {
        form.country_names = form.country_names.filter((item) => item !== country);
        const nextPorts = { ...(form.ports_by_country ?? {}) };
        delete nextPorts[country];
        form.ports_by_country = nextPorts;
        clearFieldErrorIfValid('country_names', form.country_names.length > 0);
        clearFieldErrorIfValid('ports_by_country', validatePortsSelection(form.ports_by_country));
        return;
    }

    form.country_names = [...form.country_names, country];
    form.ports_by_country = {
        ...(form.ports_by_country ?? {}),
        [country]: form.ports_by_country?.[country] ?? [],
    };
    clearFieldErrorIfValid('country_names', form.country_names.length > 0);
};

const isPortSelected = (country, portId) => (form.ports_by_country?.[country] ?? []).includes(portId);

const togglePort = (country, portId) => {
    const current = [...(form.ports_by_country?.[country] ?? [])];
    const next = current.includes(portId)
        ? current.filter((item) => item !== portId)
        : [...current, portId];

    form.ports_by_country = {
        ...(form.ports_by_country ?? {}),
        [country]: next,
    };
    clearFieldErrorIfValid('ports_by_country', validatePortsSelection(form.ports_by_country));
};

const selectAllPortsForCountry = (country) => {
    const ports = portsByCountryState.value[country] ?? [];

    form.ports_by_country = {
        ...(form.ports_by_country ?? {}),
        [country]: ports.map((port) => port.id),
    };
    clearFieldErrorIfValid('ports_by_country', validatePortsSelection(form.ports_by_country));
};

const selectAllPorts = () => {
    const next = { ...(form.ports_by_country ?? {}) };

    selectedCountryPortGroups.value.forEach((group) => {
        next[group.country] = group.ports.map((port) => port.id);
    });

    form.ports_by_country = next;
    clearFieldErrorIfValid('ports_by_country', validatePortsSelection(form.ports_by_country));
};

const clearAllPorts = () => {
    const next = { ...(form.ports_by_country ?? {}) };

    selectedCountryPortGroups.value.forEach((group) => {
        next[group.country] = [];
    });

    form.ports_by_country = next;
};

const loadPortsForCountries = async (countries) => {
    const missingCountries = countries.filter((country) => !portsByCountryState.value[country] && !loadingCountries.value.includes(country));

    if (!missingCountries.length) {
        return;
    }

    loadingCountries.value = [...loadingCountries.value, ...missingCountries];

    try {
        const params = new URLSearchParams();
        missingCountries.forEach((country) => params.append('countries[]', country));

        const response = await fetch(`/requests/ports?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Failed to load ports');
        }

        const payload = await response.json();
        portsByCountryState.value = {
            ...portsByCountryState.value,
            ...(payload.portsByCountry ?? {}),
        };
    } finally {
        loadingCountries.value = loadingCountries.value.filter((country) => !missingCountries.includes(country));
    }
};

const focusMenu = async (menuRef) => {
    await nextTick();
    menuRef.value?.focus();
};

const setTypeaheadBuffer = (type, value) => {
    if (type === 'country') {
        countryTypeaheadBuffer = value;
        if (countryTypeaheadTimeout) {
            clearTimeout(countryTypeaheadTimeout);
        }

        countryTypeaheadTimeout = setTimeout(() => {
            countryTypeaheadBuffer = '';
        }, 600);

        return countryTypeaheadBuffer;
    }

    portTypeaheadBuffer = value;
    if (portTypeaheadTimeout) {
        clearTimeout(portTypeaheadTimeout);
    }

    portTypeaheadTimeout = setTimeout(() => {
        portTypeaheadBuffer = '';
    }, 600);

    return portTypeaheadBuffer;
};

const scrollToOption = (container, selector) => {
    const element = container?.querySelector(selector);
    element?.scrollIntoView({ block: 'nearest' });
};

const openCountryMenu = async () => {
    countryMenuOpen.value = true;
    await focusMenu(countryMenuListRef);
};

const openPortMenu = async () => {
    if (!selectedCountries.value.length) {
        return;
    }

    portMenuOpen.value = true;
    await focusMenu(portMenuListRef);
};

const toggleCountryMenu = async () => {
    if (countryMenuOpen.value) {
        countryMenuOpen.value = false;
        countrySearch.value = '';
        return;
    }

    await openCountryMenu();
};

const togglePortMenu = async () => {
    if (portMenuOpen.value) {
        portMenuOpen.value = false;
        portSearch.value = '';
        return;
    }

    await openPortMenu();
};

const openSupplierCountryMenu = async () => {
    supplierCountryMenuOpen.value = true;
    await focusMenu(supplierCountryMenuListRef);
};

const openSupplierPortMenu = async () => {
    if (!selectedSupplierCountries.value.length) {
        return;
    }

    supplierPortMenuOpen.value = true;
    await focusMenu(supplierPortMenuListRef);
};

const toggleSupplierCountryMenu = async () => {
    if (supplierCountryMenuOpen.value) {
        supplierCountryMenuOpen.value = false;
        return;
    }

    await openSupplierCountryMenu();
};

const toggleSupplierPortMenu = async () => {
    if (supplierPortMenuOpen.value) {
        supplierPortMenuOpen.value = false;
        return;
    }

    await openSupplierPortMenu();
};

const openSupplierCategoryMenu = async () => {
    supplierCategoryMenuOpen.value = true;
    await focusMenu(supplierCategoryMenuListRef);
};

const openSupplierSubcategoryMenu = async () => {
    if (!selectedSupplierCategoryIds.value.length) {
        return;
    }

    await loadSupplierSubcategories();
    supplierSubcategoryMenuOpen.value = true;
    await focusMenu(supplierSubcategoryMenuListRef);
};

const openSupplierBrandMenu = async () => {
    supplierBrandMenuOpen.value = true;
    await loadSupplierBrands();
    await focusMenu(supplierBrandMenuListRef);
};

const toggleSupplierCategoryMenu = async () => {
    if (supplierCategoryMenuOpen.value) {
        supplierCategoryMenuOpen.value = false;
        supplierCategorySearch.value = '';
        return;
    }

    await openSupplierCategoryMenu();
};

const toggleSupplierSubcategoryMenu = async () => {
    if (supplierSubcategoryMenuOpen.value) {
        supplierSubcategoryMenuOpen.value = false;
        supplierSubcategorySearch.value = '';
        return;
    }

    await openSupplierSubcategoryMenu();
};

const toggleSupplierBrandMenu = async () => {
    if (supplierBrandMenuOpen.value) {
        supplierBrandMenuOpen.value = false;
        supplierBrandSearch.value = '';
        return;
    }

    await openSupplierBrandMenu();
};

const handleCountryTriggerKeydown = async (event) => {
    if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        await openCountryMenu();
    }
};

const handlePortTriggerKeydown = async (event) => {
    if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        await openPortMenu();
    }
};

const handleCountryMenuKeydown = (event) => {
    if (event.key === 'Escape') {
        countryMenuOpen.value = false;
        countrySearch.value = '';
        countryMenuRef.value?.querySelector('.dropdown-trigger')?.focus();
        return;
    }

    if (event.target instanceof HTMLElement && event.target.closest('.dropdown-search-input')) {
        return;
    }

    if (event.key.length !== 1 || event.ctrlKey || event.metaKey || event.altKey) {
        return;
    }

    const search = setTypeaheadBuffer('country', `${countryTypeaheadBuffer}${event.key}`.toLowerCase());
    const match = filteredCountryOptions.value.find((country) => country.toLowerCase().startsWith(search));

    if (match) {
        scrollToOption(countryMenuListRef.value, `[data-country-option="${match}"]`);
    }
};

const handlePortMenuKeydown = (event) => {
    if (event.key === 'Escape') {
        portMenuOpen.value = false;
        portSearch.value = '';
        portMenuRef.value?.querySelector('.dropdown-trigger')?.focus();
        return;
    }

    if (event.target instanceof HTMLElement && event.target.closest('.dropdown-search-input')) {
        return;
    }

    if (event.key.length !== 1 || event.ctrlKey || event.metaKey || event.altKey) {
        return;
    }

    const search = setTypeaheadBuffer('port', `${portTypeaheadBuffer}${event.key}`.toLowerCase());
    const match = filteredSelectedCountryPortGroups.value
        .flatMap((group) => group.ports)
        .find((port) => port.name.toLowerCase().startsWith(search));

    if (match) {
        scrollToOption(portMenuListRef.value, `[data-port-option="${match.id}"]`);
    }
};

const handleDocumentClick = (event) => {
    if (countryMenuRef.value && !countryMenuRef.value.contains(event.target)) {
        countryMenuOpen.value = false;
        countrySearch.value = '';
    }

    if (portMenuRef.value && !portMenuRef.value.contains(event.target)) {
        portMenuOpen.value = false;
        portSearch.value = '';
    }

    if (supplierCountryMenuRef.value && !supplierCountryMenuRef.value.contains(event.target)) {
        supplierCountryMenuOpen.value = false;
    }

    if (supplierPortMenuRef.value && !supplierPortMenuRef.value.contains(event.target)) {
        supplierPortMenuOpen.value = false;
    }

    if (supplierCategoryMenuRef.value && !supplierCategoryMenuRef.value.contains(event.target)) {
        supplierCategoryMenuOpen.value = false;
        supplierCategorySearch.value = '';
    }

    if (supplierSubcategoryMenuRef.value && !supplierSubcategoryMenuRef.value.contains(event.target)) {
        supplierSubcategoryMenuOpen.value = false;
        supplierSubcategorySearch.value = '';
    }

    if (supplierBrandMenuRef.value && !supplierBrandMenuRef.value.contains(event.target)) {
        supplierBrandMenuOpen.value = false;
        supplierBrandSearch.value = '';
    }

};

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
    window.addEventListener('keydown', handleGlobalKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
    window.removeEventListener('keydown', handleGlobalKeydown);
    revokeAttachmentObjectUrls();
    if (countryTypeaheadTimeout) {
        clearTimeout(countryTypeaheadTimeout);
    }
    if (portTypeaheadTimeout) {
        clearTimeout(portTypeaheadTimeout);
    }
});

watch(
    selectedCountries,
    (countries) => {
        if (!countries.length) {
            return;
        }

        loadPortsForCountries(countries);
    },
    { immediate: true }
);

watch(
    () => form.ports_by_country,
    (portsByCountry) => {
        clearFieldErrorIfValid('ports_by_country', validatePortsSelection(portsByCountry));
    },
    { deep: true }
);

watch(
    () => supplierFilters.value.category_ids,
    (value) => {
        form.category_ids = [...(value ?? [])];
    },
    { deep: true, immediate: true }
);

watch(
    () => supplierFilters.value.subcategory_ids,
    (value) => {
        form.subcategory_ids = [...(value ?? [])];
    },
    { deep: true, immediate: true }
);

watch(
    () => supplierFilters.value.brand_ids,
    (value) => {
        form.brand_ids = [...(value ?? [])];
    },
    { deep: true, immediate: true }
);

watch(
    () => supplierFilters.value.category_ids,
    async () => {
        if (!selectedSupplierCategorySlugs.value.length) {
            pendingSuggestedSupplierSubcategoryIds.value = [];
            supplierFilters.value.subcategory_ids = [];
            return;
        }

        const currentKey = [...selectedSupplierCategorySlugs.value].sort().join(',');
        await loadSupplierSubcategories(selectedSupplierCategorySlugs.value);

        const currentGroups = supplierSubcategoryOptionsState.value[currentKey] ?? [];
        const availableSubcategories = currentGroups
            .flatMap((group) => group.subcategories ?? []);

        if (!availableSubcategories.length) {
            pendingSuggestedSupplierSubcategoryIds.value = [];
            supplierFilters.value.subcategory_ids = [];
            return;
        }

        const validSubcategoryIds = new Set(availableSubcategories.map((item) => Number(item.id)));
        const preferredSubcategoryIds = pendingSuggestedSupplierSubcategoryIds.value.length
            ? pendingSuggestedSupplierSubcategoryIds.value
            : selectedSupplierSubcategoryIds.value;

        supplierFilters.value.subcategory_ids = preferredSubcategoryIds
            .filter((id) => validSubcategoryIds.has(Number(id)));
        pendingSuggestedSupplierSubcategoryIds.value = [];
    },
    { deep: true }
);

watch(
    hasSupplierRequestScope,
    (hasScope) => {
        if (!hasScope) {
            supplierCategoryMenuOpen.value = false;
            supplierSubcategoryMenuOpen.value = false;
            supplierBrandMenuOpen.value = false;
        }
    },
    { immediate: true }
);

watch(
    [selectedCountries, () => form.ports_by_country],
    async () => {
        syncSupplierScopeFromGeneral();

        const countriesToLoad = supplierFilters.value.country_names?.length
            ? supplierFilters.value.country_names
            : (form.country_names ?? []);

        if (countriesToLoad.length) {
            for (const country of countriesToLoad) {
                await loadSupplierPortsForCountry(country);
            }
        }
    },
    { deep: true, immediate: true }
);

watch(
    [selectedCountries, () => form.ports_by_country, () => supplierFilters.value.country_names, () => supplierFilters.value.ports_by_country, () => supplierFilters.value.category_ids, () => supplierFilters.value.subcategory_ids, () => supplierFilters.value.brand_ids],
    () => {
        findSupplierMatches();
    },
    { deep: true }
);

const submit = (statusOverride = null) => {
    submitIntent.value = statusOverride === 'draft' ? 'draft' : 'submit';
    form.status = statusOverride ?? form.status;

    if (submitIntent.value !== 'draft' && isSupplierTargetedRequest.value && form.supplier_recipient_ids.length === 0) {
        form.setError('supplier_recipient_ids', 'No approved suppliers match this private request scope. Change country, ports, category, subcategory, or brand before submitting.');
        return;
    }

    const options = {
        forceFormData: true,
        onFinish: () => {
            submitIntent.value = 'submit';
        },
    };

    const serializePayload = (data) => {
        const serviceAttachments = serializeAttachmentSelection(data.service_files);

        return {
            ...data,
            service_files: serviceAttachments.newFiles,
            existing_service_attachment_ids: serviceAttachments.existingAttachmentIds,
            items: (data.items ?? []).map((item) => {
                const itemAttachments = serializeAttachmentSelection(item.files);

                return {
                    ...item,
                    files: itemAttachments.newFiles,
                    existing_attachment_ids: itemAttachments.existingAttachmentIds,
                };
            }),
        };
    };

    if (props.submitMethod === 'put') {
        form.transform((data) => ({
            ...serializePayload(data),
            _method: 'put',
        })).post(props.actionUrl, options);
        return;
    }

    form.transform((data) => serializePayload(data)).post(props.actionUrl, options);
};

const preventAccidentalSubmit = (event) => {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
        return;
    }

    if (target.tagName === 'TEXTAREA') {
        return;
    }

    const isItemField = !isServiceRequest.value && Boolean(target.closest('.item-row-grid'));

    if (isItemField) {
        event.preventDefault();
        addItem();
        return;
    }

    event.preventDefault();
};
</script>

<template>
    <Head :title="`${isEditMode ? 'Edit' : 'Create'} RFQ | Sea Requests`" />

    <MainLayout>
        <section class="rfq-shell">
            <header class="section-header intro-card">
                <p class="eyebrow">{{ copy.eyebrow }}</p>
                <h1 class="directory-page-title">{{ pageHeading }}</h1>
                <p class="directory-intro-copy">{{ pageIntro }}</p>
            </header>

            <form class="rfq-form" @submit.prevent="submit()" @keydown.enter="preventAccidentalSubmit">
                <section class="surface-card form-section combined-form-section">
                    <RfqCreateGeneralInformationSection
                        v-bind="generalInformationSectionProps"
                        @update:country-search="countrySearch = $event"
                        @update:port-search="portSearch = $event"
                    />

                    <div class="section-divider"></div>

                    <RfqCreateItemsToQuoteSection v-bind="itemsToQuoteSectionProps" />

                </section>

                <RfqCreateSuppliersToSendRfqSection
                    v-bind="suppliersToSendRfqSectionProps"
                    @update:supplier-category-search="supplierCategorySearch = $event"
                    @update:supplier-subcategory-search="supplierSubcategorySearch = $event"
                    @update:supplier-brand-search="supplierBrandSearch = $event"
                />

                <div class="form-actions">
                    <Link :href="backUrl" class="ghost-link">Back</Link>
                    <div class="action-group">
                        <button v-if="!isEditMode" type="button" class="secondary-button" :disabled="form.processing" @click="submit('draft')">
                            {{ secondarySubmitLabel }}
                        </button>
                        <button type="submit" class="primary-button" :disabled="form.processing">
                            {{ resolvedPrimarySubmitLabel }}
                        </button>
                    </div>
                </div>
            </form>

            <RfqCreateImportTemplateModal v-bind="importTemplateModalProps" />

            <RfqCreateImportPreviewModal v-bind="importPreviewModalProps" />

            <div v-if="attachmentViewer" class="gallery-modal-backdrop" @click.self="closeAttachmentViewer">
                <div class="gallery-modal">
                    <div class="detail-modal-head">
                        <div class="gallery-modal-title-group">
                            <h3 class="detail-modal-title">Files</h3>
                            <p class="gallery-modal-counter">{{ attachmentIndex + 1 }} / {{ attachmentViewer.length }}</p>
                        </div>
                        <button type="button" class="detail-modal-close" @click="closeAttachmentViewer">
                            Close
                        </button>
                    </div>

                    <div class="gallery-modal-body">
                        <button
                            v-if="hasAttachmentGallery"
                            type="button"
                            class="gallery-nav-button is-left"
                            aria-label="Previous file"
                            @click="goToPreviousAttachment"
                        >
                            <svg viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M12.5 4.5 7 10l5.5 5.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>

                        <div class="gallery-stage">
                            <img
                                v-if="isImageAttachment(currentAttachment)"
                                :src="currentAttachmentUrl"
                                :alt="`File ${attachmentIndex + 1}`"
                                class="gallery-image"
                            />
                            <div v-else-if="isPdfAttachment(currentAttachment)" class="gallery-pdf-shell">
                                <iframe
                                    :src="currentAttachmentViewerUrl"
                                    class="gallery-pdf-frame"
                                    title="PDF preview"
                                ></iframe>
                            </div>
                            <div v-else class="gallery-file-fallback">
                                <p class="gallery-file-name">{{ currentAttachment?.name ?? 'File preview' }}</p>
                                <p class="directory-intro-copy">Preview is not available for this file type.</p>
                                <a
                                    v-if="currentAttachmentUrl"
                                    :href="currentAttachmentUrl"
                                    class="primary-button gallery-open-link"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Open file
                                </a>
                            </div>
                        </div>

                        <button
                            v-if="hasAttachmentGallery"
                            type="button"
                            class="gallery-nav-button is-right"
                            aria-label="Next file"
                            @click="goToNextAttachment"
                        >
                            <svg viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M7.5 4.5 13 10l-5.5 5.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

        </section>
    </MainLayout>
</template>

<style scoped>
.rfq-shell {
    padding: 16px 0 56px;
}

.intro-card,
.surface-card {
    padding: 32px 36px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.08);
}

.form-section {
    margin-top: 0;
}

.combined-form-section {
    display: grid;
    gap: 0;
    min-width: 0;
}

.section-divider {
    margin: 28px 0 0;
}

.directory-intro-copy {
    color: rgba(4, 21, 31, 0.72);
    line-height: 1.7;
}

.eyebrow {
    margin: 0 0 12px;
    font-size: 0.82rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--color-ocean);
    font-weight: 700;
}

.rfq-form {
    display: grid;
    gap: 16px;
    margin-top: 16px;
    min-width: 0;
}

.form-actions,
.action-group {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.secondary-button,
.primary-button,
.ghost-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 18px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 600;
}

.secondary-button,
.ghost-link {
    border: 1px solid rgba(4, 21, 31, 0.1);
    background: #fff;
    color: #04151f;
}

.primary-button {
    border: 1px solid #0f172a;
    background: #0f172a;
    color: #fff;
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
    overflow: hidden;
    border-radius: 18px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    background: #fff;
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.22);
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
    cursor: pointer;
}

.gallery-modal-title-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.gallery-modal-counter {
    margin: 0;
    color: rgba(4, 21, 31, 0.62);
    font-size: 0.84rem;
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

.gallery-pdf-shell {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.gallery-pdf-frame {
    width: 100%;
    height: 520px;
    display: block;
    background: #fff;
    border: 0;
    border-radius: 14px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
}

.gallery-file-fallback {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    text-align: center;
}

.gallery-file-name {
    margin: 0;
    color: #04151f;
    font-size: 0.95rem;
    font-weight: 700;
}

.gallery-open-link {
    text-decoration: none;
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

.gallery-nav-button svg {
    width: 20px;
    height: 20px;
}

.gallery-nav-button.is-left {
    left: 20px;
}

.gallery-nav-button.is-right {
    right: 20px;
}

@media (max-width: 720px) {
    .intro-card,
    .surface-card {
        padding: 24px;
    }

    .form-actions,
    .action-group {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

