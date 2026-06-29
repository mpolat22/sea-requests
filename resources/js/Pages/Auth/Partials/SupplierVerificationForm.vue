<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    ui: { type: Object, required: true },
    form: { type: Object, required: true },
    dialCodeOptions: { type: Array, required: true },
    categoryOptions: { type: Array, required: true },
    brandOptions: { type: Array, required: true },
    serviceCountries: { type: Array, required: true },
    portsByCountry: { type: Object, required: true },
    servicePortGroups: { type: Array, required: true },
    countryOptions: { type: Array, required: true },
    categoryGroups: { type: Array, required: true },
    fieldRefs: { type: Object, required: true },
    existingDocuments: { type: Object, required: true },
    newDocuments: { type: Object, required: true },
    singleMedia: { type: Object, required: true },
    newSingles: { type: Object, required: true },
    documentConfigs: { type: Array, required: true },
    triggerFileInput: { type: Function, required: true },
    triggerSingleInput: { type: Function, required: true },
    appendFiles: { type: Function, required: true },
    assignSingleFile: { type: Function, required: true },
    removeExistingDocument: { type: Function, required: true },
    removeNewDocument: { type: Function, required: true },
    removeSingleMedia: { type: Function, required: true },
    submit: { type: Function, required: true },
});

const BRAND_SELECTED_FILTER = '__selected__';

const categoryModalOpen = ref(false);
const categorySearch = ref('');
const categoryLetter = ref('');
const categoryDraftSelections = ref({});
const expandedCategoryId = ref(null);
const categoryDraftValidationRequested = ref(false);

const brandModalOpen = ref(false);
const brandSearch = ref('');
const brandLetter = ref('');
const brandDraftIds = ref([]);

const servicePortModalOpen = ref(false);
const servicePortSearch = ref('');
const servicePortLetter = ref('');
const servicePortDraftSelections = ref({});
const expandedServiceCountryCode = ref(null);
const servicePortDraftValidationRequested = ref(false);
const servicePortDraftLimitRequested = ref(false);
const documentViewer = ref(null);
const documentViewerIndex = ref(0);

const serviceCoverageSectionHelper = computed(() => 'Choose the countries you serve and add at least 1 port for each country.');

const formatRequiredLabel = (label) => String(label ?? '').replace(/\*/g, '<span class="required-star">*</span>');

const clearFieldError = (...fields) => {
    fields.forEach((field) => {
        if (props.form.errors[field]) {
            props.form.clearErrors(field);
        }
    });
};

const resolveErrorFields = (field) => {
    if (field === 'service_category_ids') return ['service_category_ids', 'service_category_ids.0'];
    if (field === 'service_subcategory_ids') return ['service_subcategory_ids', 'service_subcategory_ids.0'];
    if (field === 'service_brand_ids') return ['service_brand_ids', 'service_brand_ids.0'];
    if (field === 'service_country_codes') return ['service_country_codes', 'service_country_codes.0'];
    if (field === 'service_ports_by_country') return ['service_ports_by_country', 'service_ports_by_country.0'];
    return [field];
};

const isValidUrl = (value) => {
    const input = String(value ?? '').trim();
    if (!input) return true;

    try {
        const url = new URL(input);
        return ['http:', 'https:'].includes(url.protocol);
    } catch {
        return false;
    }
};

const isValidEmail = (value) => {
    const input = String(value ?? '').trim();
    if (!input) return false;
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input);
};

const isFieldValid = (field) => {
    switch (field) {
        case 'company_name':
            return String(props.form.company_name ?? '').trim().length > 0;
        case 'service_category_ids': {
            const groups = props.categoryGroups.filter((group) => String(group.category_id ?? '').trim() !== '');
            return groups.length > 0;
        }
        case 'service_subcategory_ids': {
            const groups = props.categoryGroups.filter((group) => String(group.category_id ?? '').trim() !== '');
            return groups.length > 0 && groups.every((group) => Array.isArray(group.subcategory_ids) && group.subcategory_ids.length > 0);
        }
        case 'service_brand_ids':
            return Array.isArray(props.form.service_brand_ids);
        case 'service_country_codes': {
            const groups = props.servicePortGroups.filter((group) => String(group.country_code ?? '').trim() !== '');
            return groups.length > 0 && groups.length <= 10;
        }
        case 'service_ports_by_country': {
            const groups = props.servicePortGroups.filter((group) => String(group.country_code ?? '').trim() !== '');
            return groups.length > 0 && groups.every((group) => Array.isArray(group.port_ids) && group.port_ids.length > 0);
        }
        case 'country':
            return String(props.form.country ?? '').trim().length > 0;
        case 'company_city':
            return String(props.form.company_city ?? '').trim().length > 0;
        case 'company_postal_code':
            return String(props.form.company_postal_code ?? '').trim().length > 0;
        case 'company_address_line':
            return String(props.form.company_address_line ?? '').trim().length > 0;
        case 'company_overview':
            return String(props.form.company_overview ?? '').trim().length >= 200 && String(props.form.company_overview ?? '').length <= 4000;
        case 'phone':
            return /^\+\d{1,4}\s[0-9]{6,15}$/.test(`${props.form.phone_country_code} ${String(props.form.phone_local_number ?? '').replace(/\D+/g, '')}`.trim());
        case 'landline_phone': {
            const value = String(props.form.landline_phone ?? '').trim();
            return value === '' || /^\+?[0-9\s\-()]{6,20}$/.test(value);
        }
        case 'website_url':
            return isValidUrl(props.form.website_url);
        case 'contact_email':
            return isValidEmail(props.form.contact_email);
        case 'whatsapp_number': {
            const digits = String(props.form.whatsapp_local_number ?? '').replace(/\D+/g, '');
            return digits === '' || /^\+\d{1,4}\s[0-9]{6,15}$/.test(`${props.form.whatsapp_country_code} ${digits}`.trim());
        }
        case 'telegram_url':
            return isValidUrl(props.form.telegram_url);
        case 'instagram_url':
            return isValidUrl(props.form.instagram_url);
        case 'linkedin_url':
            return isValidUrl(props.form.linkedin_url);
        case 'facebook_url':
            return isValidUrl(props.form.facebook_url);
        case 'twitter_url':
            return isValidUrl(props.form.twitter_url);
        case 'registration_number':
            return String(props.form.registration_number ?? '').trim().length > 0;
        default:
            return false;
    }
};

const liveErrorFields = new Set([
    'company_name',
    'company_logo',
    'company_overview',
    'service_category_ids',
    'service_subcategory_ids',
    'service_brand_ids',
    'service_country_codes',
    'service_ports_by_country',
    'country',
    'company_city',
    'company_postal_code',
    'company_address_line',
    'phone',
    'landline_phone',
    'contact_email',
    'website_url',
    'whatsapp_number',
    'telegram_url',
    'instagram_url',
    'linkedin_url',
    'facebook_url',
    'twitter_url',
    'registration_number',
]);

const hasFieldError = (field) => resolveErrorFields(field).some((key) => Boolean(props.form.errors[key]));

const hasVisualInvalid = (field, currentValid = null) => {
    if (!hasFieldError(field)) {
        return false;
    }

    if (typeof currentValid === 'boolean') {
        return !currentValid;
    }

    if (!liveErrorFields.has(field)) {
        return true;
    }

    return !isFieldValid(field);
};

const clearFieldErrorIfValid = (field) => {
    if (isFieldValid(field)) {
        clearFieldError(...resolveErrorFields(field));
    }
};

const firstFieldError = (field) => (
    resolveErrorFields(field)
        .map((key) => String(props.form.errors[key] ?? '').trim())
        .find(Boolean) || ''
);

const visibleFieldError = (field, currentValid = null) => {
    const errorText = firstFieldError(field);

    if (!errorText) {
        return '';
    }

    if (typeof currentValid === 'boolean') {
        return currentValid ? '' : errorText;
    }

    if (liveErrorFields.has(field) && isFieldValid(field)) {
        return '';
    }

    return errorText;
};

const visibleCombinedError = (fields, isStillInvalid) => {
    const errorText = fields
        .flatMap((field) => resolveErrorFields(field))
        .map((key) => String(props.form.errors[key] ?? '').trim())
        .find(Boolean) || '';

    if (!errorText) {
        return '';
    }

    return isStillInvalid ? errorText : '';
};

const resolveAlphaLetter = (name) => {
    const letter = String(name ?? '').trim().charAt(0).toUpperCase();
    return /^[A-Z]$/.test(letter) ? letter : '';
};

const setBodyLock = (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : '';
};

const subcategoryOptionsForCategoryId = (categoryId) => {
    const category = props.categoryOptions.find((item) => Number(item.id) === Number(categoryId));
    return category?.subcategories ?? [];
};

const selectedCategoryCounts = computed(() => {
    const groups = props.categoryGroups.filter((group) => String(group.category_id ?? '').trim() !== '');

    return {
        categoryCount: groups.length,
        subcategoryCount: groups.reduce(
            (total, group) => total + [...new Set((group.subcategory_ids ?? []).map((value) => Number(value)).filter(Boolean))].length,
            0,
        ),
    };
});

const buildCategoryDraftSelections = () => Object.fromEntries(
    props.categoryGroups
        .filter((group) => String(group.category_id ?? '').trim() !== '')
        .map((group) => [
            String(Number(group.category_id)),
            [...new Set((group.subcategory_ids ?? []).map((value) => Number(value)).filter(Boolean))],
        ])
);

const missingDraftSubcategoryCategoryIds = computed(() => (
    Object.entries(categoryDraftSelections.value)
        .filter(([, subcategoryIds]) => !Array.isArray(subcategoryIds) || subcategoryIds.length === 0)
        .map(([categoryId]) => Number(categoryId))
));

const categoryLetters = computed(() => {
    const letters = new Set(
        props.categoryOptions
            .map((category) => resolveAlphaLetter(category.name))
            .filter(Boolean)
    );

    return Array.from(letters).sort((left, right) => left.localeCompare(right));
});

const categoryResults = computed(() => {
    const query = categorySearch.value.trim().toLowerCase();
    const hasQuery = query.length > 0;
    const selectedIds = new Set(Object.keys(categoryDraftSelections.value).map((value) => Number(value)));

    return props.categoryOptions
        .filter((category) => {
            const resolvedLetter = resolveAlphaLetter(category.name);
            const numericCategoryId = Number(category.id);
            const subcategoryMatch = category.subcategories?.some((subcategory) => String(subcategory.name ?? '').toLowerCase().includes(query));

            if (!hasQuery && categoryLetter.value === BRAND_SELECTED_FILTER && !selectedIds.has(numericCategoryId)) {
                return false;
            }

            if (!hasQuery && categoryLetter.value && categoryLetter.value !== BRAND_SELECTED_FILTER && resolvedLetter !== categoryLetter.value) {
                return false;
            }

            if (query && !`${category.name ?? ''}`.toLowerCase().includes(query) && !subcategoryMatch) {
                return false;
            }

            return true;
        })
        .map((category) => ({
            ...category,
            checked: selectedIds.has(Number(category.id)),
        }))
        .sort((left, right) => String(left.name ?? '').localeCompare(String(right.name ?? '')));
});

const filteredSubcategoriesForCategory = (categoryId) => {
    const subcategories = subcategoryOptionsForCategoryId(categoryId);
    const query = categorySearch.value.trim().toLowerCase();

    if (!query) {
        return subcategories;
    }

    return subcategories.filter((subcategory) => String(subcategory.name ?? '').toLowerCase().includes(query));
};

const categoryDraftErrorMessage = computed(() => {
    if (!categoryDraftValidationRequested.value || !missingDraftSubcategoryCategoryIds.value.length) {
        return '';
    }

    return 'Please select at least 1 subcategory for every primary category you choose.';
});

const openCategoryPicker = () => {
    categoryDraftSelections.value = buildCategoryDraftSelections();
    expandedCategoryId.value = null;
    categoryDraftValidationRequested.value = false;
    categorySearch.value = '';
    categoryLetter.value = categoryLetters.value[0] ?? '';
    categoryModalOpen.value = true;
    setBodyLock(true);
};

const closeCategoryPicker = () => {
    categoryModalOpen.value = false;
    categoryDraftSelections.value = {};
    expandedCategoryId.value = null;
    categoryDraftValidationRequested.value = false;
    categorySearch.value = '';
    categoryLetter.value = '';
    setBodyLock(false);
};

const inlineSubcategoryCountForCategory = (categoryId) => categoryDraftSelections.value[String(Number(categoryId))]?.length ?? 0;
const isInlineSubcategoryChecked = (categoryId, subcategoryId) => (
    (categoryDraftSelections.value[String(Number(categoryId))] ?? []).includes(Number(subcategoryId))
);

const toggleCategoryDraftSelection = (categoryId) => {
    const key = String(Number(categoryId));
    const next = { ...categoryDraftSelections.value };

    if (next[key]) {
        delete next[key];
        if (Number(expandedCategoryId.value) === Number(categoryId)) {
            expandedCategoryId.value = null;
        }
    } else {
        next[key] = [];
        expandedCategoryId.value = Number(categoryId);
    }

    categoryDraftSelections.value = next;
};

const toggleCategoryExpansion = (categoryId) => {
    const numericCategoryId = Number(categoryId);
    expandedCategoryId.value = Number(expandedCategoryId.value) === numericCategoryId ? null : numericCategoryId;
};

const toggleInlineSubcategorySelection = (categoryId, subcategoryId) => {
    const categoryKey = String(Number(categoryId));
    const numericSubcategoryId = Number(subcategoryId);
    const next = { ...categoryDraftSelections.value };
    const current = new Set((next[categoryKey] ?? []).map((id) => Number(id)));

    if (!next[categoryKey]) {
        next[categoryKey] = [];
    }

    if (current.has(numericSubcategoryId)) {
        current.delete(numericSubcategoryId);
    } else {
        current.add(numericSubcategoryId);
    }

    next[categoryKey] = Array.from(current);
    categoryDraftSelections.value = next;
    expandedCategoryId.value = Number(categoryId);
    clearFieldError(...resolveErrorFields('service_subcategory_ids'));
};

const saveCategorySelection = () => {
    categoryDraftValidationRequested.value = true;

    if (missingDraftSubcategoryCategoryIds.value.length > 0) {
        return;
    }

    const selectedCategoryIds = new Set(Object.keys(categoryDraftSelections.value));
    const nextGroups = props.categoryOptions
        .filter((category) => selectedCategoryIds.has(String(Number(category.id))))
        .map((category) => ({
            category_id: String(category.id),
            subcategory_ids: [...new Set((categoryDraftSelections.value[String(Number(category.id))] ?? []).map((value) => Number(value)).filter(Boolean))],
        }));

    props.categoryGroups.splice(
        0,
        props.categoryGroups.length,
        ...(nextGroups.length ? nextGroups : [{ category_id: '', subcategory_ids: [] }]),
    );

    clearFieldError(...resolveErrorFields('service_category_ids'));
    clearFieldError(...resolveErrorFields('service_subcategory_ids'));
    closeCategoryPicker();
};

const selectedBrandCount = computed(() => (props.form.service_brand_ids ?? []).length);

const brandLetters = computed(() => {
    const letters = new Set(
        props.brandOptions
            .map((brand) => resolveAlphaLetter(brand.name))
            .filter(Boolean)
    );

    return Array.from(letters).sort((left, right) => left.localeCompare(right));
});

const brandResults = computed(() => {
    const query = brandSearch.value.trim().toLowerCase();
    const hasQuery = query.length > 0;
    const selectedIds = new Set((brandDraftIds.value ?? []).map((id) => Number(id)));

    return props.brandOptions
        .filter((brand) => {
            const resolvedLetter = resolveAlphaLetter(brand.name);
            const numericBrandId = Number(brand.id);

            if (!hasQuery && brandLetter.value === BRAND_SELECTED_FILTER && !selectedIds.has(numericBrandId)) {
                return false;
            }

            if (!hasQuery && brandLetter.value && brandLetter.value !== BRAND_SELECTED_FILTER && resolvedLetter !== brandLetter.value) {
                return false;
            }

            if (query && !`${brand.name ?? ''}`.toLowerCase().includes(query)) {
                return false;
            }

            return true;
        })
        .map((brand) => ({
            ...brand,
            checked: selectedIds.has(Number(brand.id)),
        }))
        .sort((left, right) => String(left.name ?? '').localeCompare(String(right.name ?? '')));
});

const openBrandPicker = () => {
    brandDraftIds.value = [...new Set((props.form.service_brand_ids ?? []).map((id) => Number(id)).filter(Boolean))];
    brandSearch.value = '';
    brandLetter.value = brandLetters.value[0] ?? '';
    brandModalOpen.value = true;
    setBodyLock(true);
};

const closeBrandPicker = () => {
    brandModalOpen.value = false;
    brandSearch.value = '';
    brandLetter.value = '';
    setBodyLock(false);
};

const toggleBrandDraftSelection = (brandId) => {
    const numericBrandId = Number(brandId);
    const current = new Set((brandDraftIds.value ?? []).map((id) => Number(id)));

    if (current.has(numericBrandId)) {
        current.delete(numericBrandId);
    } else {
        current.add(numericBrandId);
    }

    brandDraftIds.value = Array.from(current);
};

const saveBrandSelection = () => {
    props.form.service_brand_ids = [...brandDraftIds.value].sort((left, right) => left - right);
    clearFieldError(...resolveErrorFields('service_brand_ids'));
    closeBrandPicker();
};

const selectedServicePortCounts = computed(() => {
    const groups = props.servicePortGroups.filter((group) => String(group.country_code ?? '').trim() !== '');

    return {
        countryCount: groups.length,
        portCount: groups.reduce(
            (total, group) => total + [...new Set((group.port_ids ?? []).map((value) => Number(value)).filter(Boolean))].length,
            0,
        ),
    };
});

const buildServicePortDraftSelections = () => Object.fromEntries(
    props.servicePortGroups
        .filter((group) => String(group.country_code ?? '').trim() !== '')
        .map((group) => [
            String(group.country_code).toUpperCase(),
            [...new Set((group.port_ids ?? []).map((value) => Number(value)).filter(Boolean))],
        ])
);

const normalizedServiceCountries = computed(() => (
    [...props.serviceCountries]
        .map((country) => ({
            code: String(country.code ?? '').toUpperCase(),
            name: String(country.name ?? country.code ?? '').trim(),
        }))
        .sort((left, right) => left.name.localeCompare(right.name))
));

const serviceCountryLetters = computed(() => {
    const letters = new Set(
        normalizedServiceCountries.value
            .map((country) => resolveAlphaLetter(country.name))
            .filter(Boolean)
    );

    return Array.from(letters).sort((left, right) => left.localeCompare(right));
});

const serviceCountryResults = computed(() => {
    const query = servicePortSearch.value.trim().toLowerCase();
    const hasQuery = query.length > 0;
    const selectedCodes = new Set(Object.keys(servicePortDraftSelections.value).map((value) => String(value).toUpperCase()));

    return normalizedServiceCountries.value
        .filter((country) => {
            const resolvedLetter = resolveAlphaLetter(country.name);
            const ports = props.portsByCountry[country.code] ?? [];
            const portMatch = ports.some((port) => String(port.port_name ?? '').toLowerCase().includes(query));

            if (!hasQuery && servicePortLetter.value === BRAND_SELECTED_FILTER && !selectedCodes.has(country.code)) {
                return false;
            }

            if (!hasQuery && servicePortLetter.value && servicePortLetter.value !== BRAND_SELECTED_FILTER && resolvedLetter !== servicePortLetter.value) {
                return false;
            }

            if (query && !country.name.toLowerCase().includes(query) && !portMatch) {
                return false;
            }

            return true;
        })
        .map((country) => ({
            ...country,
            checked: selectedCodes.has(country.code),
        }));
});

const matchesServiceCountrySearch = (countryCode) => {
    const query = servicePortSearch.value.trim().toLowerCase();

    if (!query) {
        return false;
    }

    const normalizedCode = String(countryCode).toUpperCase();
    const country = normalizedServiceCountries.value.find((item) => item.code === normalizedCode);

    if (!country) {
        return false;
    }

    return country.name.toLowerCase().includes(query) || country.code.toLowerCase().includes(query);
};

const filteredPortsForCountry = (countryCode) => {
    const ports = portsForCountry(countryCode);
    const query = servicePortSearch.value.trim().toLowerCase();

    if (!query) {
        return ports;
    }

    if (matchesServiceCountrySearch(countryCode)) {
        return ports;
    }

    return ports.filter((port) => (
        String(port.port_name ?? '').toLowerCase().includes(query)
        || String(port.unlocode ?? '').toLowerCase().includes(query)
    ));
};

const missingDraftPortCountryCodes = computed(() => (
    Object.entries(servicePortDraftSelections.value)
        .filter(([, portIds]) => !Array.isArray(portIds) || portIds.length === 0)
        .map(([countryCode]) => String(countryCode).toUpperCase())
));

const servicePortDraftErrorMessage = computed(() => {
    if (servicePortDraftLimitRequested.value && Object.keys(servicePortDraftSelections.value).length > 10) {
        return 'You can select up to 10 countries.';
    }

    if (!servicePortDraftValidationRequested.value || !missingDraftPortCountryCodes.value.length) {
        return '';
    }

    return 'Please select at least one port for every selected country.';
});

const openServicePortPicker = () => {
    servicePortDraftSelections.value = buildServicePortDraftSelections();
    expandedServiceCountryCode.value = null;
    servicePortDraftValidationRequested.value = false;
    servicePortDraftLimitRequested.value = false;
    servicePortSearch.value = '';
    servicePortLetter.value = serviceCountryLetters.value[0] ?? '';
    servicePortModalOpen.value = true;
    setBodyLock(true);
};

const closeServicePortPicker = () => {
    servicePortModalOpen.value = false;
    servicePortDraftSelections.value = {};
    expandedServiceCountryCode.value = null;
    servicePortDraftValidationRequested.value = false;
    servicePortDraftLimitRequested.value = false;
    servicePortSearch.value = '';
    servicePortLetter.value = '';
    setBodyLock(false);
};

const portsForCountry = (countryCode) => (
    [...(props.portsByCountry[String(countryCode).toUpperCase()] ?? [])]
        .map((port) => ({
            id: Number(port.id),
            port_name: String(port.port_name ?? ''),
            unlocode: String(port.unlocode ?? ''),
        }))
        .sort((left, right) => left.port_name.localeCompare(right.port_name))
);

const inlinePortCountForCountry = (countryCode) => servicePortDraftSelections.value[String(countryCode).toUpperCase()]?.length ?? 0;
const isInlinePortChecked = (countryCode, portId) => (
    (servicePortDraftSelections.value[String(countryCode).toUpperCase()] ?? []).includes(Number(portId))
);

const toggleServiceCountryDraftSelection = (countryCode) => {
    const code = String(countryCode).toUpperCase();
    const next = { ...servicePortDraftSelections.value };

    if (next[code]) {
        delete next[code];
        if (expandedServiceCountryCode.value === code) {
            expandedServiceCountryCode.value = null;
        }
    } else {
        if (Object.keys(next).length >= 10) {
            servicePortDraftLimitRequested.value = true;
            return;
        }

        next[code] = [];
        expandedServiceCountryCode.value = code;
    }

    servicePortDraftSelections.value = next;
};

const toggleServiceCountryExpansion = (countryCode) => {
    const code = String(countryCode).toUpperCase();
    expandedServiceCountryCode.value = expandedServiceCountryCode.value === code ? null : code;
};

const toggleInlinePortSelection = (countryCode, portId) => {
    const code = String(countryCode).toUpperCase();
    const numericPortId = Number(portId);
    const next = { ...servicePortDraftSelections.value };

    if (!next[code]) {
        if (Object.keys(next).length >= 10) {
            servicePortDraftLimitRequested.value = true;
            return;
        }

        next[code] = [];
    }

    const current = new Set((next[code] ?? []).map((value) => Number(value)));

    if (current.has(numericPortId)) {
        current.delete(numericPortId);
    } else {
        current.add(numericPortId);
    }

    next[code] = Array.from(current);
    servicePortDraftSelections.value = next;
    expandedServiceCountryCode.value = code;
    clearFieldError(...resolveErrorFields('service_ports_by_country'));
};

const saveServicePortSelection = () => {
    servicePortDraftValidationRequested.value = true;
    servicePortDraftLimitRequested.value = true;

    if (Object.keys(servicePortDraftSelections.value).length > 10 || missingDraftPortCountryCodes.value.length > 0) {
        return;
    }

    const nextGroups = normalizedServiceCountries.value
        .filter((country) => Object.prototype.hasOwnProperty.call(servicePortDraftSelections.value, country.code))
        .map((country) => ({
            country_code: country.code,
            port_ids: [...new Set((servicePortDraftSelections.value[country.code] ?? []).map((value) => Number(value)).filter(Boolean))],
        }));

    props.servicePortGroups.splice(
        0,
        props.servicePortGroups.length,
        ...(nextGroups.length ? nextGroups : [{ country_code: '', port_ids: [] }]),
    );

    clearFieldError(...resolveErrorFields('service_country_codes'));
    clearFieldError(...resolveErrorFields('service_ports_by_country'));
    closeServicePortPicker();
};

const documentLabel = (label) => String(label ?? '').replace(/\s*\*/g, '');
const documentGuide = (key) => {
    switch (key) {
        case 'company_registration_documents':
            return 'Upload the official company registration files that confirm your legal business entity.';
        case 'tax_certificate_documents':
            return 'Upload the tax documents buyers and admins can use to verify your active tax status.';
        case 'service_authorization_documents':
            return 'Upload any authorization, dealership, class, or service approval documents relevant to your operation.';
        default:
            return 'Upload the files needed to verify this document set.';
    }
};

const documentFileCount = (group) => (group?.existing?.length ?? 0) + (group?.fresh?.length ?? 0);

const hasCompanyLogoSelected = computed(() => Boolean(props.newSingles.company_logo || props.singleMedia.company_logo));

const hasAnyOfficialDocument = computed(() => props.documentConfigs.some((group) => documentFileCount(group) > 0));

const hasDocumentGroupVisualInvalid = (group) => {
    const errorText = String(group?.error ?? group?.itemError ?? '');

    if (!errorText) {
        return false;
    }

    if (/at least one official document/i.test(errorText)) {
        return !hasAnyOfficialDocument.value;
    }

    return true;
};

const sharedOfficialDocumentError = computed(() => {
    if (hasAnyOfficialDocument.value) {
        return '';
    }

    const firstMessage = props.documentConfigs
        .map((group) => String(group?.error ?? group?.itemError ?? '').trim())
        .find((message) => /at least one official document/i.test(message));

    return firstMessage || '';
});

const documentGroupErrorText = (group) => {
    const errorText = String(group?.error ?? group?.itemError ?? '').trim();

    if (!errorText) {
        return '';
    }

    if (/at least one official document/i.test(errorText)) {
        return '';
    }

    return errorText;
};

const documentAttachmentsForGroup = (group) => [
    ...(group?.fresh ?? []),
    ...(group?.existing ?? []),
]
    .filter((item) => item?.url)
    .map((item) => ({
        id: item.id ?? item.path ?? item.name ?? item.url,
        name: item.name ?? 'File',
        url: item.url,
        type: item.type ?? item.file?.type ?? '',
    }));

const documentSummary = (group) => {
    const names = [...(group?.fresh ?? []), ...(group?.existing ?? [])]
        .map((item) => String(item?.name ?? '').trim())
        .filter(Boolean);

    if (!names.length) {
        return 'Select one or more files for this document set.';
    }

    if (names.length === 1) {
        return names[0];
    }

    return `${names[0]} + ${names.length - 1} more`;
};

const openDocumentViewer = (group, startIndex = 0) => {
    const attachments = documentAttachmentsForGroup(group);
    if (!attachments.length) return;

    documentViewer.value = attachments;
    documentViewerIndex.value = Math.min(Math.max(startIndex, 0), attachments.length - 1);
};

const closeDocumentViewer = () => {
    documentViewer.value = null;
    documentViewerIndex.value = 0;
};

const currentDocumentAttachment = computed(() => {
    if (!documentViewer.value?.length) {
        return null;
    }

    return documentViewer.value[documentViewerIndex.value] ?? null;
});

const hasDocumentGallery = computed(() => (documentViewer.value?.length ?? 0) > 1);

const goToPreviousDocument = () => {
    if (!documentViewer.value?.length) return;

    documentViewerIndex.value = documentViewerIndex.value === 0
        ? documentViewer.value.length - 1
        : documentViewerIndex.value - 1;
};

const goToNextDocument = () => {
    if (!documentViewer.value?.length) return;

    documentViewerIndex.value = documentViewerIndex.value === documentViewer.value.length - 1
        ? 0
        : documentViewerIndex.value + 1;
};

const isImageDocument = (attachment) => {
    const type = String(attachment?.type ?? '').toLowerCase();
    if (type.startsWith('image/')) {
        return true;
    }

    const name = String(attachment?.name ?? '');
    const url = String(attachment?.url ?? '');

    return [name, url].some((value) => /\.(png|jpe?g|gif|webp|bmp|svg)(\?|$)/i.test(value));
};

const isPdfDocument = (attachment) => {
    const type = String(attachment?.type ?? '').toLowerCase();
    if (type === 'application/pdf') {
        return true;
    }

    const name = String(attachment?.name ?? '');
    const url = String(attachment?.url ?? '');

    return [name, url].some((value) => /\.pdf(\?|$)/i.test(value));
};

const documentPreviewUrl = (attachment) => {
    const url = String(attachment?.url ?? '');
    if (!url) {
        return '';
    }

    if (!isPdfDocument(attachment)) {
        return url;
    }

    const viewerParams = 'toolbar=0&navpanes=0&scrollbar=0&view=FitH';
    return url.includes('#') ? `${url}&${viewerParams}` : `${url}#${viewerParams}`;
};

const clearDocumentGroup = (group) => {
    for (const item of [...(group?.existing ?? [])]) {
        if (item?.path) {
            props.removeExistingDocument(group.key, item.path);
        }
    }

    for (const item of [...(group?.fresh ?? [])]) {
        if (item?.id) {
            props.removeNewDocument(group.key, item.id);
        }
    }
};

const handleDigitsInput = (field) => {
    props.form[field] = String(props.form[field] ?? '').replace(/\D+/g, '').slice(0, 15);
};

const handleLandlineInput = () => {
    props.form.landline_phone = String(props.form.landline_phone ?? '').replace(/[^0-9+\-()\s]/g, '').slice(0, 20);
};

const dialCodeDisplay = (value) => String(value ?? '').trim() || '+';

const submitForm = () => {
    props.submit();
};

onBeforeUnmount(() => {
    document.body.style.overflow = '';
});
</script>

<template>
    <div class="verification-card">
        <form class="form-grid" @submit.prevent="submitForm">
            <section class="section-card">
                <div class="section-head">
                    <p class="directory-eyebrow section-card-eyebrow">{{ ui.identityHeading }}</p>
                </div>

                <div class="identity-surface">
                    <div class="section-form section-form-narrow">
                        <label class="field" data-section-field="company_name">
                            <span v-html="formatRequiredLabel(ui.businessName)"></span>
                            <span class="field-hint">Enter the business name exactly as buyers should see it on your profile and offers.</span>
                            <div class="input-shell" :class="{ invalid: hasVisualInvalid('company_name') }">
                                <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M3 7.5h14v8A1.5 1.5 0 0 1 15.5 17h-11A1.5 1.5 0 0 1 3 15.5v-8Z" stroke="currentColor" stroke-width="1.5"/><path d="m4.5 7.5 1.4-2.8A1.5 1.5 0 0 1 7.24 4h5.52c.57 0 1.08.32 1.34.83l1.4 2.67" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                                <input :ref="fieldRefs.company_name" v-model="form.company_name" type="text" placeholder="MaritimeSOFT" @input="clearFieldErrorIfValid('company_name')" />
                            </div>
                            <span class="field-feedback">{{ visibleFieldError('company_name') }}</span>
                        </label>

                        <label class="field" data-section-field="company_logo">
                            <span v-html="formatRequiredLabel(ui.logo)"></span>
                            <span class="field-hint">Upload a clean company logo that will represent your business on the supplier profile.</span>
                            <input id="single-file-input-company_logo" type="file" accept=".jpg,.jpeg,.png,.webp" class="hidden-input" @change="assignSingleFile('company_logo', $event)" />
                            <div class="identity-logo-shell" :class="{ invalid: hasVisualInvalid('company_logo', hasCompanyLogoSelected) }">
                                <button type="button" class="identity-logo-trigger" @click="triggerSingleInput('company_logo')">
                                    <div class="identity-logo-preview" :class="{ empty: !(newSingles.company_logo || singleMedia.company_logo) }">
                                        <img v-if="newSingles.company_logo || singleMedia.company_logo" :src="newSingles.company_logo?.url || singleMedia.company_logo?.url" alt="" />
                                        <svg v-else viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                            <rect x="3.75" y="3.75" width="12.5" height="12.5" rx="2.25" stroke="currentColor" stroke-width="1.5"/>
                                            <path d="M7 10.75 8.75 9l1.75 1.75 2.5-2.5L15 10.25v2.5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <circle cx="8" cy="7.5" r="1" fill="currentColor"/>
                                        </svg>
                                    </div>
                                    <div class="identity-logo-copy">
                                        <strong>{{ newSingles.company_logo || singleMedia.company_logo ? 'Logo ready' : 'Upload company logo' }}</strong>
                                        <span>{{ newSingles.company_logo?.name || singleMedia.company_logo?.name || 'JPG, JPEG, PNG or WEBP' }}</span>
                                    </div>
                                </button>
                                <div class="identity-logo-actions">
                                    <button type="button" class="secondary-button identity-logo-action" @click="triggerSingleInput('company_logo')">
                                        {{ newSingles.company_logo || singleMedia.company_logo ? 'Change' : 'Select' }}
                                    </button>
                                    <button
                                        v-if="newSingles.company_logo || singleMedia.company_logo"
                                        type="button"
                                        class="media-link-button is-danger identity-logo-remove"
                                        @click="removeSingleMedia('company_logo')"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                            <span class="field-feedback">{{ visibleFieldError('company_logo', hasCompanyLogoSelected) }}</span>
                        </label>

                        <label class="field" data-section-field="company_overview">
                            <span v-html="formatRequiredLabel('Company Overview *')"></span>
                            <span class="field-hint">Describe your services, specialties, coverage, and the strengths buyers should know first.</span>
                            <div class="input-shell input-shell-textarea" :class="{ invalid: hasVisualInvalid('company_overview') }">
                                <span class="input-icon input-icon-textarea" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M5 4.75h10A1.25 1.25 0 0 1 16.25 6v8A1.25 1.25 0 0 1 15 15.25H5A1.25 1.25 0 0 1 3.75 14V6A1.25 1.25 0 0 1 5 4.75Z" stroke="currentColor" stroke-width="1.5"/><path d="M6.75 8h6.5M6.75 10.75h6.5M6.75 13.5h4.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                                <textarea
                                    :ref="fieldRefs.company_overview"
                                    v-model="form.company_overview"
                                    rows="6"
                                    placeholder="Describe your expertise, services, coverage and what makes your operation stand out."
                                    @input="clearFieldErrorIfValid('company_overview')"
                                ></textarea>
                            </div>
                            <div class="field-meta">
                                <span class="field-meta-copy"></span>
                                <span class="field-meta-count">{{ form.company_overview?.length || 0 }} / 4000</span>
                            </div>
                            <span class="field-feedback">{{ visibleFieldError('company_overview') }}</span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="section-card capability-cluster-card">
                <div class="capability-cluster">
                    <div class="capability-cluster-item">
                        <div class="section-head">
                            <p class="directory-eyebrow section-card-eyebrow">Category and Subcategory</p>
                        </div>

                        <div class="identity-surface">
                            <div class="section-form section-form-narrow">
                                <div class="brand-selector" data-section-field="service_category_ids">
                                    <span class="brand-selector-label" v-html="formatRequiredLabel('Category and Subcategory *')"></span>
                                    <span class="field-hint">Choose the main service categories you cover and add the matching subcategories below them.</span>
                                    <div class="brand-selector-shell" :class="{ invalid: hasVisualInvalid('service_category_ids') || hasVisualInvalid('service_subcategory_ids') }">
                                        <button
                                            :ref="(el) => { if (fieldRefs.service_category_ids) fieldRefs.service_category_ids.value = el; if (fieldRefs.service_subcategory_ids) fieldRefs.service_subcategory_ids.value = el; }"
                                            type="button"
                                            class="brand-open-button"
                                            @click="openCategoryPicker"
                                        >
                                            <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="m12.5 5 2 2M8 9l2 2M3.5 3.5 7 7M3.5 16.5 7 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="m10.7 4.8 1.6-1.6a1 1 0 0 1 1.4 0l2.1 2.1a1 1 0 0 1 0 1.4l-1.6 1.6M6.3 15.2l-1.6 1.6a1 1 0 0 1-1.4 0l-2.1-2.1a1 1 0 0 1 0-1.4l1.6-1.6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                                            <span>Select categories and subcategories</span>
                                        </button>

                                        <div
                                            v-if="selectedCategoryCounts.categoryCount || selectedCategoryCounts.subcategoryCount"
                                            class="category-summary-count"
                                        >
                                            <span>({{ selectedCategoryCounts.categoryCount }}) categories selected, ({{ selectedCategoryCounts.subcategoryCount }}) subcategories selected</span>
                                        </div>
                                    </div>
                                    <span class="field-feedback">{{ visibleCombinedError(['service_category_ids', 'service_subcategory_ids'], hasVisualInvalid('service_category_ids') || hasVisualInvalid('service_subcategory_ids')) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="capability-cluster-item">
                        <div class="section-head">
                            <p class="directory-eyebrow section-card-eyebrow">{{ ui.brands }}</p>
                        </div>

                        <div class="identity-surface">
                            <div class="section-form section-form-narrow">
                                <div class="brand-selector" data-section-field="service_brand_ids">
                                    <span class="brand-selector-label">{{ ui.brands }}</span>
                                    <span class="field-hint">Select the brands you actively supply or service so matching can become more precise later.</span>
                                    <div class="brand-selector-shell" :class="{ invalid: hasVisualInvalid('service_brand_ids') }">
                                        <button
                                            :ref="fieldRefs.service_brand_ids"
                                            type="button"
                                            class="brand-open-button"
                                            @click="openBrandPicker"
                                        >
                                            <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M4 14V6.75A1.75 1.75 0 0 1 5.75 5h8.5A1.75 1.75 0 0 1 16 6.75V14M4 14h12M6.5 8.25h7M6.5 11h4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                                            <span>Select brands</span>
                                        </button>

                                        <div v-if="selectedBrandCount" class="category-summary-count">
                                            <span>({{ selectedBrandCount }}) brands selected</span>
                                        </div>
                                    </div>
                                    <span class="field-feedback">{{ visibleFieldError('service_brand_ids') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="capability-cluster-item">
                        <div class="section-head">
                            <p class="directory-eyebrow section-card-eyebrow">{{ ui.serviceCoverageHeading }}</p>
                        </div>

                        <div class="identity-surface">
                            <div class="section-form section-form-narrow">
                                <div class="brand-selector" data-section-field="service_country_codes">
                                    <span class="brand-selector-label" v-html="formatRequiredLabel('Service Countries and Ports *')"></span>
                                    <span class="field-hint">{{ serviceCoverageSectionHelper }}</span>
                                    <div class="brand-selector-shell" :class="{ invalid: hasVisualInvalid('service_country_codes') || hasVisualInvalid('service_ports_by_country') }">
                                        <button
                                            :ref="(el) => { if (fieldRefs.service_country_codes) fieldRefs.service_country_codes.value = el; if (fieldRefs.service_ports_by_country) fieldRefs.service_ports_by_country.value = el; }"
                                            type="button"
                                            class="brand-open-button"
                                            @click="openServicePortPicker"
                                        >
                                            <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M10 16.5s4.5-3.5 4.5-7a4.5 4.5 0 1 0-9 0c0 3.5 4.5 7 4.5 7Z" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="9.5" r="1.5" stroke="currentColor" stroke-width="1.5"/></svg></span>
                                            <span>Select countries and ports</span>
                                        </button>

                                        <div
                                            v-if="selectedServicePortCounts.countryCount || selectedServicePortCounts.portCount"
                                            class="category-summary-count"
                                        >
                                            <span>({{ selectedServicePortCounts.countryCount }}) countries selected, ({{ selectedServicePortCounts.portCount }}) ports selected</span>
                                        </div>
                                    </div>
                                    <span class="field-feedback">{{ visibleCombinedError(['service_country_codes', 'service_ports_by_country'], hasVisualInvalid('service_country_codes') || hasVisualInvalid('service_ports_by_country')) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section-card">
                <div class="section-head">
                    <p class="directory-eyebrow section-card-eyebrow">{{ ui.locationHeading }}</p>
                </div>

                <div class="identity-surface">
                    <div class="section-form section-form-narrow">
                        <p class="helper-copy">Add the registered business location buyers and admins should rely on for profile visibility and verification.</p>

                        <div class="grid-two top-aligned">
                            <label class="field" data-section-field="country">
                                <span v-html="formatRequiredLabel(ui.country)"></span>
                                <div class="input-shell input-shell-select" :class="{ invalid: hasVisualInvalid('country') }">
                                    <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M10 16.5s4.5-3.5 4.5-7a4.5 4.5 0 1 0-9 0c0 3.5 4.5 7 4.5 7Z" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="9.5" r="1.5" stroke="currentColor" stroke-width="1.5"/></svg></span>
                                    <select :ref="fieldRefs.country" v-model="form.country" class="field-select" @change="clearFieldErrorIfValid('country')">
                                        <option value="">Select country</option>
                                        <option v-for="option in countryOptions" :key="option" :value="option">{{ option }}</option>
                                    </select>
                                    <span class="select-caret" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" fill="none"><path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </span>
                                </div>
                                <span class="field-feedback">{{ visibleFieldError('country') }}</span>
                            </label>

                            <label class="field">
                                <span v-html="formatRequiredLabel(ui.city)"></span>
                                <div class="input-shell" :class="{ invalid: hasVisualInvalid('company_city') }">
                                    <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M5.5 16.5h9M6.5 16.5V5a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v11.5M8.5 7h3M8.5 10h3M8.5 13h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                                    <input v-model="form.company_city" type="text" placeholder="Istanbul" @input="clearFieldErrorIfValid('company_city')" />
                                </div>
                                <span class="field-feedback">{{ visibleFieldError('company_city') }}</span>
                            </label>

                            <label class="field">
                                <span>{{ ui.neighborhood }}</span>
                                <div class="input-shell" :class="{ invalid: form.errors.company_neighborhood }">
                                    <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M4.5 14.5h11M4.5 10h11M4.5 5.5h11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                                    <input v-model="form.company_neighborhood" type="text" placeholder="Icmeler" @input="clearFieldErrorIfValid('company_neighborhood')" />
                                </div>
                                <span class="field-feedback">{{ visibleFieldError('company_neighborhood') }}</span>
                            </label>

                            <label class="field">
                                <span v-html="formatRequiredLabel(ui.postalCode)"></span>
                                <div class="input-shell" :class="{ invalid: hasVisualInvalid('company_postal_code') }">
                                    <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M4.5 6.5h11v7h-11z" stroke="currentColor" stroke-width="1.5"/><path d="M7 9.5h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                                    <input v-model="form.company_postal_code" type="text" placeholder="34947" @input="clearFieldErrorIfValid('company_postal_code')" />
                                </div>
                                <span class="field-feedback">{{ visibleFieldError('company_postal_code') }}</span>
                            </label>
                        </div>

                        <label class="field" data-section-field="company_address_line">
                            <span v-html="formatRequiredLabel(ui.fullAddress)"></span>
                            <div class="input-shell input-shell-textarea" :class="{ invalid: hasVisualInvalid('company_address_line') }">
                                <span class="input-icon input-icon-textarea" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M10 16.5s4.5-3.5 4.5-7a4.5 4.5 0 1 0-9 0c0 3.5 4.5 7 4.5 7Z" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="9.5" r="1.5" stroke="currentColor" stroke-width="1.5"/></svg></span>
                                <textarea :ref="fieldRefs.company_address_line" v-model="form.company_address_line" rows="4" :placeholder="ui.fullAddressPlaceholder" @input="clearFieldErrorIfValid('company_address_line')"></textarea>
                            </div>
                                <span class="field-feedback">{{ visibleFieldError('company_address_line') }}</span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="section-card">
                <div class="section-head">
                    <p class="directory-eyebrow section-card-eyebrow">{{ ui.contactHeading }}</p>
                </div>

                <div class="identity-surface">
                    <div class="contact-stack">
                        <p class="helper-copy">Add the direct business contact channels buyers and admins can use to verify and reach your company.</p>

                        <div class="grid-two top-aligned">
                            <label class="field field-inline" data-section-field="phone">
                                <span v-html="formatRequiredLabel(ui.mobilePhone)"></span>
                                <div class="phone-combo" :class="{ invalid: hasVisualInvalid('phone') }">
                                    <div class="phone-code-shell">
                                        <select v-model="form.phone_country_code" class="phone-code-select" @change="clearFieldErrorIfValid('phone')">
                                            <option v-for="item in dialCodeOptions" :key="`phone-${item.value}`" :value="item.value">{{ item.label }}</option>
                                        </select>
                                        <span class="phone-code-display" aria-hidden="true">{{ dialCodeDisplay(form.phone_country_code) }}</span>
                                    </div>
                                    <div class="input-shell">
                                        <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M5.5 2.5h3l1 3-2 1.5a11 11 0 0 0 5 5L14 10.5l3 1v3A1.5 1.5 0 0 1 15.5 16 13.5 13.5 0 0 1 4 4.5 1.5 1.5 0 0 1 5.5 2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                                        <input :ref="fieldRefs.phone" v-model="form.phone_local_number" type="text" inputmode="numeric" placeholder="5550000000" @input="handleDigitsInput('phone_local_number'); clearFieldErrorIfValid('phone')" />
                                    </div>
                                </div>
                                <span class="field-feedback">{{ visibleFieldError('phone') }}</span>
                            </label>

                            <label class="field field-inline">
                                <span>{{ ui.landlinePhone }}</span>
                                <div class="input-shell" :class="{ invalid: hasVisualInvalid('landline_phone') }">
                                    <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M6 3.5h8A1.5 1.5 0 0 1 15.5 5v10A1.5 1.5 0 0 1 14 16.5H6A1.5 1.5 0 0 1 4.5 15V5A1.5 1.5 0 0 1 6 3.5Z" stroke="currentColor" stroke-width="1.5"/><path d="M7 6.5h6M7 9.5h6M8.5 13h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                                    <input v-model="form.landline_phone" type="text" :placeholder="ui.landlinePlaceholder" @input="handleLandlineInput(); clearFieldErrorIfValid('landline_phone')" />
                                </div>
                                <span class="field-feedback">{{ visibleFieldError('landline_phone') }}</span>
                            </label>
                        </div>

                        <div class="grid-two top-aligned">
                            <label class="field field-inline">
                                <span>{{ ui.whatsapp }}</span>
                                <div class="phone-combo" :class="{ invalid: hasVisualInvalid('whatsapp_number') }">
                                    <div class="phone-code-shell">
                                        <select v-model="form.whatsapp_country_code" class="phone-code-select" @change="clearFieldErrorIfValid('whatsapp_number')">
                                            <option v-for="item in dialCodeOptions" :key="`wa-${item.value}`" :value="item.value">{{ item.label }}</option>
                                        </select>
                                        <span class="phone-code-display" aria-hidden="true">{{ dialCodeDisplay(form.whatsapp_country_code) }}</span>
                                    </div>
                                    <div class="input-shell">
                                        <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M10 3a6.5 6.5 0 0 0-5.52 9.95L4 17l4.2-1.27A6.5 6.5 0 1 0 10 3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M7.8 7.6c.2-.4.35-.42.53-.43h.45c.15 0 .38.06.58.5.2.45.67 1.55.73 1.67.06.12.1.27.02.43-.08.16-.12.26-.24.4-.12.14-.24.3-.34.4-.12.12-.24.25-.1.49.13.24.58.96 1.25 1.55.86.77 1.58 1.01 1.82 1.12.24.11.38.09.52-.05.14-.14.58-.67.73-.9.15-.23.3-.19.52-.11.22.08 1.39.66 1.63.78.24.12.4.18.46.28.05.1.05.6-.14 1.18-.19.58-1.1 1.14-1.52 1.2-.39.06-.89.09-1.43-.08-.33-.1-.76-.25-1.31-.49-2.3-.99-3.8-3.44-3.91-3.6-.1-.16-.93-1.24-.93-2.37 0-1.14.6-1.69.8-1.92Z" fill="currentColor"/></svg></span>
                                        <input v-model="form.whatsapp_local_number" type="text" inputmode="numeric" placeholder="5550000000" @input="handleDigitsInput('whatsapp_local_number'); clearFieldErrorIfValid('whatsapp_number')" />
                                    </div>
                                </div>
                                <span class="field-feedback">{{ visibleFieldError('whatsapp_number') }}</span>
                            </label>

                            <label class="field field-inline">
                                <span>{{ ui.telegram }}</span>
                                <div class="input-shell" :class="{ invalid: hasVisualInvalid('telegram_url') }">
                                    <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="m16.5 4.5-2 10.5c-.15.74-.54.92-1.1.57l-3.04-2.24-1.47 1.41c-.16.16-.3.3-.61.3l.22-3.11 5.66-5.11c.25-.22-.05-.35-.38-.13L6.78 11.1 3.8 10.17c-.65-.2-.66-.65.14-.96l11.65-4.49c.54-.2 1.01.13.84.78Z" fill="currentColor"/></svg></span>
                                    <input v-model="form.telegram_url" type="url" :placeholder="ui.socialPlaceholder" @input="clearFieldErrorIfValid('telegram_url')" />
                                </div>
                                <span class="field-feedback">{{ visibleFieldError('telegram_url') }}</span>
                            </label>
                        </div>

                        <div class="grid-two top-aligned">
                            <label class="field field-inline" data-section-field="contact_email">
                                <span v-html="formatRequiredLabel(ui.email)"></span>
                                <div class="input-shell" :class="{ invalid: hasVisualInvalid('contact_email') }">
                                    <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><rect x="3" y="5" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="m4.5 6.5 5.5 4 5.5-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                                    <input :ref="fieldRefs.contact_email" v-model="form.contact_email" type="email" :placeholder="ui.emailPlaceholder" @input="clearFieldErrorIfValid('contact_email')" />
                                </div>
                                <span class="field-feedback">{{ visibleFieldError('contact_email') }}</span>
                            </label>

                            <label class="field field-inline">
                                <span>{{ ui.website }}</span>
                                <div class="input-shell" :class="{ invalid: hasVisualInvalid('website_url') }">
                                    <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M3 10h14M10 3c1.8 2 2.7 4.333 2.7 7S11.8 15 10 17c-1.8-2-2.7-4.333-2.7-7S8.2 5 10 3Z" stroke="currentColor" stroke-width="1.5"/></svg></span>
                                    <input v-model="form.website_url" type="url" :placeholder="ui.websitePlaceholder" @input="clearFieldErrorIfValid('website_url')" />
                                </div>
                                <span class="field-feedback">{{ visibleFieldError('website_url') }}</span>
                            </label>
                        </div>

                        <div class="grid-two top-aligned">
                            <label class="field field-inline">
                                <span>{{ ui.instagram }}</span>
                                <div class="input-shell" :class="{ invalid: hasVisualInvalid('instagram_url') }">
                                    <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><rect x="4" y="4" width="12" height="12" rx="3" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="10" r="2.75" stroke="currentColor" stroke-width="1.5"/><circle cx="13.4" cy="6.6" r=".8" fill="currentColor"/></svg></span>
                                    <input v-model="form.instagram_url" type="url" :placeholder="ui.socialPlaceholder" @input="clearFieldErrorIfValid('instagram_url')" />
                                </div>
                                <span class="field-feedback">{{ visibleFieldError('instagram_url') }}</span>
                            </label>

                            <label class="field field-inline">
                                <span>{{ ui.linkedin }}</span>
                                <div class="input-shell" :class="{ invalid: hasVisualInvalid('linkedin_url') }">
                                    <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M6 8.25V14M6 6a.75.75 0 1 0 0-1.5A.75.75 0 0 0 6 6ZM9 14V8.25h2.75c1.24 0 2.25 1 2.25 2.25V14M9 10.25c0-1.1.9-2 2-2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                                    <input v-model="form.linkedin_url" type="url" :placeholder="ui.socialPlaceholder" @input="clearFieldErrorIfValid('linkedin_url')" />
                                </div>
                                <span class="field-feedback">{{ visibleFieldError('linkedin_url') }}</span>
                            </label>
                        </div>

                        <div class="grid-two top-aligned">
                            <label class="field field-inline">
                                <span>{{ ui.facebook }}</span>
                                <div class="input-shell" :class="{ invalid: hasVisualInvalid('facebook_url') }">
                                    <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M11.25 16V10.5H13l.25-2h-2V7.25c0-.58.16-.97 1-.97H13.4V4.5c-.2-.03-.88-.08-1.68-.08-1.67 0-2.82 1.02-2.82 2.9V8.5H7.25v2h1.65V16h2.35Z" fill="currentColor"/></svg></span>
                                    <input v-model="form.facebook_url" type="url" :placeholder="ui.socialPlaceholder" @input="clearFieldErrorIfValid('facebook_url')" />
                                </div>
                                <span class="field-feedback">{{ visibleFieldError('facebook_url') }}</span>
                            </label>

                            <label class="field field-inline">
                                <span>{{ ui.twitter }}</span>
                                <div class="input-shell" :class="{ invalid: hasVisualInvalid('twitter_url') }">
                                    <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M4.5 4h2.76l2.17 3.1L12.2 4h3.3l-4.34 4.96L16 16h-2.77l-2.45-3.5L7.7 16H4.4l4.61-5.27L4.5 4Z" fill="currentColor"/></svg></span>
                                    <input v-model="form.twitter_url" type="url" :placeholder="ui.socialPlaceholder" @input="clearFieldErrorIfValid('twitter_url')" />
                                </div>
                                <span class="field-feedback">{{ visibleFieldError('twitter_url') }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section-card">
                <div class="section-head">
                    <p class="directory-eyebrow section-card-eyebrow">{{ ui.officialHeading }}</p>
                </div>

                <div class="identity-surface official-documents-surface">
                    <div class="section-form section-form-narrow official-documents-stack">
                        <p class="helper-copy">You can submit any one of these document sets, but uploading all three is recommended for a faster and smoother approval review.</p>

                        <label class="field" data-section-field="registration_number">
                            <span v-html="formatRequiredLabel(ui.registrationNumber)"></span>
                            <span class="field-hint">Enter the official registration number exactly as it appears on your legal company records.</span>
                            <div class="input-shell" :class="{ invalid: hasVisualInvalid('registration_number') }">
                                <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M5 3.75h7.25l2.75 2.75V15A1.25 1.25 0 0 1 13.75 16.25H5A1.25 1.25 0 0 1 3.75 15V5A1.25 1.25 0 0 1 5 3.75Z" stroke="currentColor" stroke-width="1.5"/><path d="M12 3.75V6.5h2.75M6.75 10h6.5M6.75 12.75h4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                                <input :ref="fieldRefs.registration_number" v-model="form.registration_number" type="text" placeholder="Trade registry or company registration number" @input="clearFieldErrorIfValid('registration_number')" />
                            </div>
                            <span class="field-feedback">{{ visibleFieldError('registration_number') }}</span>
                        </label>
                        <div class="document-groups document-grid">
                        <div v-for="group in documentConfigs" :key="group.key" class="field document-group" :data-section-field="group.key">
                            <div class="document-head-copy">
                                <span class="document-label" v-html="formatRequiredLabel(`${documentLabel(group.label)} *`)"></span>
                                <span class="field-hint">{{ documentGuide(group.key) }}</span>
                            </div>

                            <input :id="`file-input-${group.key}`" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" class="hidden-input" @change="appendFiles(group.key, $event)" />

                            <div class="document-upload-shell" :class="{ 'document-card-invalid': hasDocumentGroupVisualInvalid(group) }">
                                <button
                                    type="button"
                                    class="document-upload-trigger"
                                    @click="triggerFileInput(group.key)"
                                >
                                    <div class="document-upload-preview">
                                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                            <path d="M5 3.75h7.25l2.75 2.75V15A1.25 1.25 0 0 1 13.75 16.25H5A1.25 1.25 0 0 1 3.75 15V5A1.25 1.25 0 0 1 5 3.75Z" stroke="currentColor" stroke-width="1.5"/>
                                            <path d="M12 3.75V6.5h2.75M6.75 10h6.5M6.75 12.75h4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                    </div>
                                    <div class="identity-logo-copy">
                                        <strong>{{ documentFileCount(group) ? 'Documents ready' : `Upload ${documentLabel(group.label).toLowerCase()}` }}</strong>
                                        <span>{{ documentSummary(group) }}</span>
                                    </div>
                                </button>
                                <div class="identity-logo-actions">
                                    <button
                                        v-if="documentFileCount(group)"
                                        type="button"
                                        class="secondary-button identity-logo-action"
                                        @click="openDocumentViewer(group)"
                                    >
                                        Open
                                    </button>
                                    <button type="button" class="secondary-button identity-logo-action" @click="triggerFileInput(group.key)">
                                        {{ documentFileCount(group) ? 'Change' : 'Select' }}
                                    </button>
                                    <button
                                        v-if="documentFileCount(group)"
                                        type="button"
                                        class="media-link-button is-danger identity-logo-remove"
                                        @click="clearDocumentGroup(group)"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>

                            <span class="field-feedback">{{ documentGroupErrorText(group) }}</span>
                        </div>
                    </div>
                        <span class="field-feedback">{{ sharedOfficialDocumentError }}</span>
                    </div>
                </div>
            </section>

            <div class="form-actions">
                <button type="submit" class="primary-button" :disabled="form.processing">{{ form.processing ? ui.submitting : ui.submit }}</button>
            </div>
        </form>

        <Transition name="fade">
            <div v-if="documentViewer" class="gallery-modal-backdrop" @click.self="closeDocumentViewer">
                <div class="gallery-modal">
                <div class="detail-modal-head">
                    <div class="gallery-modal-title-group">
                        <h3 class="detail-modal-title">Files</h3>
                        <p class="gallery-modal-counter">{{ documentViewerIndex + 1 }} / {{ documentViewer.length }}</p>
                    </div>
                    <button type="button" class="detail-modal-close" @click="closeDocumentViewer">Close</button>
                </div>

                <div class="gallery-modal-body">
                        <button
                            v-if="hasDocumentGallery"
                            type="button"
                            class="gallery-nav-button is-left"
                            aria-label="Previous file"
                            @click="goToPreviousDocument"
                        >
                            ‹
                        </button>

                        <div class="gallery-stage">
                            <img
                                v-if="isImageDocument(currentDocumentAttachment)"
                                :src="currentDocumentAttachment?.url"
                                :alt="currentDocumentAttachment?.name || 'File preview'"
                                class="gallery-image"
                            />
                            <div v-else-if="isPdfDocument(currentDocumentAttachment)" class="gallery-pdf-shell">
                                <iframe
                                    :src="documentPreviewUrl(currentDocumentAttachment)"
                                    class="gallery-pdf-frame"
                                    title="PDF preview"
                                ></iframe>
                            </div>
                            <div v-else class="gallery-file-fallback">
                                <p class="detail-inline-text detail-inline-text-long">Preview is not available for this file type.</p>
                                <a
                                    :href="currentDocumentAttachment?.url"
                                    class="secondary-button gallery-file-open"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Open file
                                </a>
                            </div>
                        </div>

                        <button
                            v-if="hasDocumentGallery"
                            type="button"
                            class="gallery-nav-button is-right"
                            aria-label="Next file"
                            @click="goToNextDocument"
                        >
                            ›
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <Transition name="fade">
            <div v-if="categoryModalOpen" class="picker-modal-backdrop" @click="closeCategoryPicker">
                <div class="picker-modal" @click.stop>
                    <button type="button" class="picker-close" @click="closeCategoryPicker">&times;</button>
                    <div class="picker-head">
                        <p class="capability-panel-kicker">Category and Subcategory</p>
                        <h3 class="picker-title">Select Category and Subcategories</h3>
                        <p class="picker-copy">Choose a category and select its matching subcategories below.</p>
                    </div>

                    <div class="picker-toolbar">
                        <div class="picker-search-row">
                            <input v-model="categorySearch" type="text" :placeholder="ui.selectCategory" />
                            <button type="button" class="picker-search-clear" :disabled="!categorySearch" @click="categorySearch = ''">Clear</button>
                        </div>
                        <div class="picker-letters">
                            <button type="button" class="picker-letter" :class="{ active: categoryLetter === BRAND_SELECTED_FILTER }" @click="categoryLetter = BRAND_SELECTED_FILTER">Selected ({{ selectedCategoryCounts.categoryCount }})</button>
                            <button v-for="letter in categoryLetters" :key="`category-${letter}`" type="button" class="picker-letter" :class="{ active: categoryLetter === letter }" @click="categoryLetter = letter">{{ letter }}</button>
                        </div>
                    </div>

                    <p v-if="categoryDraftErrorMessage" class="picker-error">{{ categoryDraftErrorMessage }}</p>

                    <div class="picker-results">
                        <div v-for="category in categoryResults" :key="category.id" class="picker-block">
                            <div class="picker-row">
                                <button type="button" class="picker-expand" @click="toggleCategoryExpansion(category.id)">{{ expandedCategoryId === Number(category.id) ? 'v' : '>' }}</button>
                                <div class="picker-copy-block">
                                    <strong>{{ category.name }}</strong>
                                    <span v-if="inlineSubcategoryCountForCategory(category.id)">{{ inlineSubcategoryCountForCategory(category.id) }} selected</span>
                                </div>
                                <label class="picker-check">
                                    <input type="checkbox" :checked="category.checked" @change="toggleCategoryDraftSelection(category.id)" />
                                    <span></span>
                                </label>
                            </div>

                            <div v-if="expandedCategoryId === Number(category.id) || (categorySearch.trim() && filteredSubcategoriesForCategory(category.id).length)" class="picker-children">
                                <label v-for="subcategory in filteredSubcategoriesForCategory(category.id)" :key="subcategory.id" class="picker-child">
                                    <input type="checkbox" :checked="isInlineSubcategoryChecked(category.id, subcategory.id)" @change="toggleInlineSubcategorySelection(category.id, subcategory.id)" />
                                    <span>{{ subcategory.name }}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="picker-actions">
                        <button type="button" class="secondary-button" @click="closeCategoryPicker">Cancel</button>
                        <button type="button" class="primary-button small" @click="saveCategorySelection">Save Selection</button>
                    </div>
                </div>
            </div>
        </Transition>

        <Transition name="fade">
            <div v-if="brandModalOpen" class="picker-modal-backdrop" @click="closeBrandPicker">
                <div class="picker-modal" @click.stop>
                    <button type="button" class="picker-close" @click="closeBrandPicker">&times;</button>
                    <div class="picker-head">
                        <p class="capability-panel-kicker">Brands</p>
                        <h3 class="picker-title">Select Brands</h3>
                        <p class="picker-copy">Choose the brands you actively supply or service.</p>
                    </div>

                    <div class="picker-toolbar">
                        <div class="picker-search-row">
                            <input v-model="brandSearch" type="text" :placeholder="ui.brandSearchPlaceholder" />
                            <button type="button" class="picker-search-clear" :disabled="!brandSearch" @click="brandSearch = ''">Clear</button>
                        </div>
                        <div class="picker-letters">
                            <button type="button" class="picker-letter" :class="{ active: brandLetter === BRAND_SELECTED_FILTER }" @click="brandLetter = BRAND_SELECTED_FILTER">Selected ({{ brandDraftIds.length }})</button>
                            <button v-for="letter in brandLetters" :key="`brand-${letter}`" type="button" class="picker-letter" :class="{ active: brandLetter === letter }" @click="brandLetter = letter">{{ letter }}</button>
                        </div>
                    </div>

                    <div class="picker-results">
                        <label v-for="brand in brandResults" :key="brand.id" class="picker-simple-row">
                            <span>{{ brand.name }}</span>
                            <input type="checkbox" :checked="brand.checked" @change="toggleBrandDraftSelection(brand.id)" />
                        </label>
                    </div>

                    <div class="picker-actions">
                        <button type="button" class="secondary-button" @click="closeBrandPicker">Cancel</button>
                        <button type="button" class="primary-button small" @click="saveBrandSelection">Save Brands</button>
                    </div>
                </div>
            </div>
        </Transition>

        <Transition name="fade">
            <div v-if="servicePortModalOpen" class="picker-modal-backdrop" @click="closeServicePortPicker">
                <div class="picker-modal" @click.stop>
                    <button type="button" class="picker-close" @click="closeServicePortPicker">&times;</button>
                    <div class="picker-head">
                        <p class="capability-panel-kicker">Service Countries and Ports</p>
                        <h3 class="picker-title">Select Service Countries and Ports</h3>
                        <p class="picker-copy">Choose the countries you serve and add at least 1 port for each country.</p>
                    </div>

                    <div class="picker-toolbar">
                        <div class="picker-search-row">
                            <input v-model="servicePortSearch" type="text" :placeholder="ui.selectServiceCountries" />
                            <button type="button" class="picker-search-clear" :disabled="!servicePortSearch" @click="servicePortSearch = ''">Clear</button>
                        </div>
                        <div class="picker-letters">
                            <button type="button" class="picker-letter" :class="{ active: servicePortLetter === BRAND_SELECTED_FILTER }" @click="servicePortLetter = BRAND_SELECTED_FILTER">Selected ({{ selectedServicePortCounts.countryCount }})</button>
                            <button v-for="letter in serviceCountryLetters" :key="`country-${letter}`" type="button" class="picker-letter" :class="{ active: servicePortLetter === letter }" @click="servicePortLetter = letter">{{ letter }}</button>
                        </div>
                    </div>

                    <p v-if="servicePortDraftErrorMessage" class="picker-error">{{ servicePortDraftErrorMessage }}</p>

                    <div class="picker-results">
                        <div v-for="country in serviceCountryResults" :key="country.code" class="picker-block">
                            <div class="picker-row">
                                <button type="button" class="picker-expand" @click="toggleServiceCountryExpansion(country.code)">{{ expandedServiceCountryCode === country.code ? 'v' : '>' }}</button>
                                <div class="picker-copy-block">
                                    <strong>{{ country.name }}</strong>
                                    <span v-if="inlinePortCountForCountry(country.code)">{{ inlinePortCountForCountry(country.code) }} selected</span>
                                </div>
                                <label class="picker-check">
                                    <input type="checkbox" :checked="country.checked" @change="toggleServiceCountryDraftSelection(country.code)" />
                                    <span></span>
                                </label>
                            </div>

                            <div v-if="expandedServiceCountryCode === country.code || (servicePortSearch.trim() && filteredPortsForCountry(country.code).length)" class="picker-children">
                                <label v-for="port in filteredPortsForCountry(country.code)" :key="port.id" class="picker-child">
                                    <input type="checkbox" :checked="isInlinePortChecked(country.code, port.id)" @change="toggleInlinePortSelection(country.code, port.id)" />
                                    <span>{{ port.port_name }}<small v-if="port.unlocode"> - {{ port.unlocode }}</small></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="picker-actions">
                        <button type="button" class="secondary-button" @click="closeServicePortPicker">Cancel</button>
                        <button type="button" class="primary-button small" @click="saveServicePortSelection">Save Selection</button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.verification-card,
.form-grid,
.section-form,
.section-form-narrow,
.contact-stack,
.category-stack,
.capability-panel,
.document-groups,
.document-group,
.document-list {
    display: grid;
    gap: 16px;
}

.section-card {
    display: grid;
    gap: 18px;
    padding: 24px 26px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 20px 42px rgba(15, 23, 42, 0.06);
}

.section-head {
    display: grid;
    gap: 8px;
}

.identity-surface {
    padding: 24px;
    border-radius: 10px;
    background: #f8fafb;
    min-width: 0;
}

.official-documents-surface {
    display: grid;
    gap: 16px;
}

.capability-cluster {
    display: grid;
    gap: 18px;
}

.capability-cluster-item {
    display: grid;
    gap: 10px;
}

.document-groups {
    gap: 16px;
}

.document-group {
    gap: 8px;
}

.document-head-copy {
    display: grid;
    gap: 8px;
}

.section-card-eyebrow {
    margin: 0;
}

.helper-copy,
.capability-panel-copy,
.picker-copy,
.document-copy,
.upload-showcase-subtitle,
.field-meta-copy {
    margin: 0;
    color: #64748b;
    font-size: 0.92rem;
    line-height: 1.6;
}

.field {
    display: grid;
    gap: 8px;
}

.brand-selector {
    display: grid;
    gap: 8px;
}

.field-hint {
    color: #64748b;
    font-size: 0.88rem;
    line-height: 1.55;
}

.field > span:not(.field-hint):not(.field-feedback),
.brand-selector-label,
.document-label,
.upload-showcase-label {
    display: block;
    color: #0f172a;
    font-size: 0.95rem;
    font-weight: 620;
    line-height: 1.45;
    letter-spacing: 0;
}

.field > span:not(.field-hint):not(.field-feedback) > span,
.brand-selector-label > span,
.document-label > span {
    font: inherit;
    line-height: inherit;
    letter-spacing: inherit;
}

.field > span:first-of-type,
.field.field-inline > span:first-of-type,
.contact-stack .field > span:first-of-type,
.section-form-narrow .field > span:first-of-type {
    font-size: 0.95rem !important;
    font-weight: 620 !important;
    line-height: 1.45 !important;
    letter-spacing: 0 !important;
}

.required-star {
    color: #d92d20;
}

.input-shell,
.phone-combo,
.brand-selector-shell,
.identity-logo-shell,
.upload-showcase-empty,
.document-list-showcase {
    border: 1px solid rgba(4, 21, 31, 0.12);
    border-radius: 10px;
    background: #fff;
}

.input-shell,
.phone-combo {
    display: flex;
    align-items: center;
    min-height: 52px;
    overflow: hidden;
}

.input-shell.invalid,
.phone-combo.invalid,
.brand-selector-shell.invalid,
.identity-logo-shell.invalid,
.document-card-invalid {
    border-color: rgba(217, 45, 32, 0.5);
    box-shadow: 0 0 0 3px rgba(217, 45, 32, 0.08);
}

.input-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 46px;
    color: #365cff;
    flex: 0 0 46px;
}

.input-icon svg {
    width: 18px;
    height: 18px;
}

.input-shell input,
.input-shell select,
.input-shell textarea,
.picker-search-row input {
    width: 100%;
    border: 0;
    outline: 0;
    background: transparent;
    color: #0f172a;
    font: inherit;
}

.input-shell input,
.input-shell select {
    min-height: 50px;
    padding: 0 16px 0 0;
}

.input-shell select {
    appearance: none;
}

.input-shell-select {
    position: relative;
}

.field-select {
    padding-left: 8px !important;
    padding-right: 42px !important;
    cursor: pointer;
}

.field-select option {
    padding: 10px 12px;
}

.select-caret {
    position: absolute;
    right: 14px;
    top: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    color: #64748b;
    pointer-events: none;
    transform: translateY(-50%);
}

.select-caret svg {
    width: 18px;
    height: 18px;
}

.input-shell textarea {
    min-height: 120px;
    resize: vertical;
    padding: 14px 16px 14px 0;
}

.input-shell-textarea {
    align-items: flex-start;
}

.input-icon-textarea {
    padding-top: 14px;
}

.identity-logo-shell {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    min-height: 92px;
    padding: 14px 16px;
}

.identity-logo-trigger {
    display: flex;
    align-items: center;
    gap: 14px;
    flex: 1;
    min-width: 0;
    padding: 0;
    border: 0;
    background: transparent;
    text-align: left;
    cursor: pointer;
}

.identity-logo-preview {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 14px;
    overflow: hidden;
    background: rgba(247, 248, 250, 0.92);
    color: #365cff;
    flex: 0 0 60px;
}

.identity-logo-preview.empty {
    width: auto;
    height: auto;
    flex: 0 0 auto;
    border: 0;
    border-radius: 0;
    background: transparent;
}

.identity-logo-preview img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.identity-logo-preview svg {
    width: 24px;
    height: 24px;
}

.identity-logo-preview.empty svg {
    width: 18px;
    height: 18px;
}

.identity-logo-copy {
    display: grid;
    gap: 4px;
    min-width: 0;
}

.identity-logo-copy strong {
    color: #0f172a;
    font-size: 0.95rem;
    font-weight: 620;
}

.identity-logo-copy span {
    color: #64748b;
    font-size: 0.84rem;
    line-height: 1.5;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.identity-logo-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.identity-logo-action,
.identity-logo-remove {
    min-height: 36px;
    padding: 0 14px;
    font-size: 0.9rem;
}

.document-upload-shell {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    min-height: 92px;
    padding: 14px 16px;
    border: 1px solid rgba(4, 21, 31, 0.12);
    border-radius: 10px;
    background: #fff;
}

.document-upload-trigger {
    display: flex;
    align-items: center;
    gap: 14px;
    flex: 1;
    min-width: 0;
    padding: 0;
    border: 0;
    background: transparent;
    text-align: left;
    cursor: pointer;
}

.document-upload-preview {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 46px;
    height: 46px;
    flex: 0 0 46px;
    border-radius: 14px;
    background: rgba(54, 92, 255, 0.06);
    color: #365cff;
}

.document-upload-preview svg {
    width: 18px;
    height: 18px;
}

.document-upload-list {
    display: grid;
    gap: 0;
    padding-top: 8px;
}

.phone-code-select {
    flex: 0 0 118px;
    width: 118px;
    min-height: 50px;
    padding: 0 12px;
    border: 0;
    border-right: 1px solid rgba(4, 21, 31, 0.08);
    background: transparent;
    color: transparent;
    font: inherit;
    outline: 0;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    position: relative;
    z-index: 2;
}

.phone-code-select option {
    color: #0f172a;
    background: #fff;
}

.phone-code-shell {
    position: relative;
    flex: 0 0 118px;
    width: 118px;
    min-height: 50px;
    background: #fff;
}

.phone-code-display {
    position: absolute;
    inset: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 28px 0 12px;
    color: #0f172a;
    font: inherit;
    font-weight: 400;
    pointer-events: none;
    z-index: 1;
}

.phone-combo .input-shell {
    flex: 1;
    min-width: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
}

.brand-selector-shell {
    display: grid;
    gap: 10px;
    padding: 10px 12px;
}

.brand-open-button {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    min-height: 44px;
    padding: 0;
    border: 0;
    background: transparent;
    color: #0f172a;
    font: inherit;
    font-weight: 520;
    text-align: left;
    cursor: pointer;
}

.category-summary-count,
.field-meta-count,
.document-status-badge {
    display: inline-flex;
    align-items: center;
    color: #365cff;
    font-size: 0.84rem;
    font-weight: 600;
}

.field-feedback,
.picker-error {
    display: block;
    margin: 0;
    color: #b42318 !important;
    font-family: inherit !important;
    font-size: 0.82rem !important;
    font-weight: 400 !important;
    font-style: normal !important;
    line-height: 1.5 !important;
    letter-spacing: 0 !important;
}

.document-group .field-feedback {
    color: #b42318 !important;
    font-family: inherit !important;
    font-size: 0.82rem !important;
    font-weight: 400 !important;
    font-style: normal !important;
    line-height: 1.5 !important;
    letter-spacing: 0 !important;
}

.capability-panel {
    gap: 14px;
}

.capability-panel-head {
    display: grid;
    gap: 6px;
}

.capability-panel-kicker {
    margin: 0;
    color: #365cff;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
}

.grid-two {
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.upload-showcase-card,
.document-list-showcase {
    display: grid;
    gap: 14px;
    padding: 16px;
}

.upload-showcase-preview-logo {
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid rgba(4, 21, 31, 0.08);
    background: rgba(247, 248, 250, 0.7);
}

.upload-showcase-preview-logo img {
    display: block;
    width: 100%;
    max-height: 220px;
    object-fit: contain;
}

.upload-showcase-empty {
    display: grid;
    gap: 6px;
    justify-items: start;
    padding: 18px;
    color: #0f172a;
    cursor: pointer;
}

.upload-showcase-title {
    font-weight: 620;
}

.upload-showcase-actions,
.document-list-actions,
.document-row-actions,
.picker-actions,
.form-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.secondary-button,
.primary-button,
.media-link-button,
.picker-search-clear {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 0 16px;
    border-radius: 10px;
    border: 1px solid rgba(4, 21, 31, 0.12);
    background: #fff;
    color: #0f172a;
    font: inherit;
    font-weight: 520;
    cursor: pointer;
}

.primary-button {
    border-color: #365cff;
    background: #365cff;
    color: #fff;
}

.primary-button.small {
    min-height: 38px;
}

.primary-button:disabled,
.picker-search-clear:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.media-link-button.is-danger {
    color: #b42318;
    border-color: rgba(217, 45, 32, 0.24);
}

.document-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 12px 0;
    border-top: 1px solid rgba(4, 21, 31, 0.08);
}

.document-row:first-of-type {
    border-top: 0;
}

.document-meta {
    min-width: 0;
}

.document-name {
    color: #0f172a;
    font-size: 0.92rem;
    font-weight: 520;
    word-break: break-word;
}

.picker-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 70;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: rgba(15, 23, 42, 0.58);
    backdrop-filter: blur(8px);
}

.picker-modal {
    width: min(980px, 100%);
    min-height: 680px;
    max-height: min(86vh, 860px);
    display: grid;
    grid-template-rows: auto auto auto minmax(0, 1fr) auto;
    gap: 16px;
    padding: 22px;
    overflow: hidden;
    border-radius: 18px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    background: #fff;
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.22);
}

.picker-actions .secondary-button,
.picker-actions .primary-button {
    flex: 0 0 auto;
    width: auto;
    min-height: 38px;
    padding: 0 14px;
    font-size: 0.9rem;
}

.picker-actions {
    align-items: center;
    justify-content: flex-end;
}

.picker-close {
    justify-self: end;
    border: 0;
    background: transparent;
    color: #64748b;
    font-size: 1.8rem;
    line-height: 1;
    cursor: pointer;
}

.picker-head {
    display: grid;
    gap: 8px;
}

.picker-title {
    margin: 0;
    color: #0f172a;
    font-size: 1.25rem;
    font-weight: 650;
}

.picker-toolbar {
    display: grid;
    gap: 12px;
}

.picker-search-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.picker-search-row input {
    min-height: 44px;
    padding: 0 14px;
    border: 1px solid rgba(4, 21, 31, 0.12);
    border-radius: 10px;
}

.hidden-input {
    display: none;
}

.picker-letters {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
}

.picker-letter {
    border: 0;
    background: transparent;
    color: #64748b;
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

.picker-letter.active {
    color: #365cff;
}

.picker-results {
    min-height: 0;
    overflow: auto;
    display: grid;
    align-content: start;
    grid-auto-rows: max-content;
    gap: 12px;
    padding-right: 4px;
}

.picker-block {
    display: grid;
    gap: 10px;
    padding: 12px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(248, 250, 252, 0.8);
}

.picker-row,
.picker-simple-row {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
}

.picker-expand {
    width: 28px;
    height: 28px;
    border: 1px solid rgba(4, 21, 31, 0.12);
    border-radius: 999px;
    background: #fff;
    color: #0f172a;
    cursor: pointer;
}

.picker-copy-block {
    display: grid;
    gap: 4px;
    min-width: 0;
}

.picker-copy-block strong,
.picker-simple-row span,
.picker-child span {
    color: #0f172a;
    font-size: 0.94rem;
}

.picker-copy-block strong {
    font-weight: 400;
}

.picker-copy-block span {
    color: #64748b;
    font-size: 0.82rem;
}

.picker-check {
    display: inline-flex;
    align-items: center;
}

.picker-check input,
.picker-child input,
.picker-simple-row input {
    width: 18px;
    height: 18px;
}

.picker-children {
    display: grid;
    gap: 10px;
    padding-left: 42px;
}

.picker-child {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.picker-simple-row {
    grid-template-columns: minmax(0, 1fr) auto;
    padding: 10px 12px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(248, 250, 252, 0.8);
}

.gallery-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 70;
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

.gallery-modal-title-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail-modal-title {
    margin: 0;
    color: #0f172a;
    font-size: 1.04rem;
    font-weight: 700;
}

.gallery-modal-counter {
    margin: 0;
    color: rgba(4, 21, 31, 0.62);
    font-size: 0.86rem;
    font-weight: 600;
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
    font: inherit;
    font-weight: 600;
    cursor: pointer;
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

.gallery-nav-button {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 44px;
    height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
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
    background: #fff;
    display: block;
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

.gallery-file-open {
    text-decoration: none;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.18s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

@media (max-width: 920px) {
    .grid-two {
        grid-template-columns: 1fr;
    }

    .picker-modal {
        width: min(100%, 100%);
        min-height: 0;
        max-height: 90vh;
        padding: 18px;
    }

    .gallery-modal {
        width: min(100%, 100%);
    }
}

@media (max-width: 640px) {
    .section-card {
        padding: 20px 18px;
    }

    .identity-surface {
        padding: 20px;
    }

    .phone-combo {
        flex-direction: column;
        align-items: stretch;
    }

    .identity-logo-shell,
    .identity-logo-trigger,
    .document-upload-shell,
    .document-upload-trigger,
    .identity-logo-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .identity-logo-actions {
        width: 100%;
    }

    .phone-code-select {
        width: 100%;
        flex: none;
        border-right: 0;
        border-bottom: 1px solid rgba(4, 21, 31, 0.08);
    }

    .phone-code-shell {
        width: 100%;
        flex: none;
    }

    .picker-search-row,
    .picker-actions,
    .identity-logo-actions,
    .upload-showcase-actions,
    .document-list-actions,
    .document-row-actions,
    .form-actions {
        flex-direction: column;
    }

    .secondary-button,
    .primary-button,
    .media-link-button,
    .picker-search-clear {
        width: 100%;
    }
}
</style>
