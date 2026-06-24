<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import MainLayout from '../../Layouts/MainLayout.vue';
import SupplierVerificationForm from './Partials/SupplierVerificationForm.vue';
import SupplierVerificationPreview from './Partials/SupplierVerificationPreview.vue';

const props = defineProps({
    categories: { type: Array, required: true },
    brands: { type: Array, required: true },
    serviceCountries: { type: Array, required: true },
    portsByCountry: { type: Object, required: true },
    verification: { type: Object, required: true },
    actionUrls: {
        type: Object,
        default: () => ({
            submit: '/seller-verification',
            removalRequest: '/seller-verification/removal-request',
        }),
    },
    adminContext: {
        type: Object,
        default: () => ({
            enabled: false,
            targetUserId: null,
            targetUserName: '',
            returnUrl: '',
        }),
    },
});

const parseDialPhone = (value, fallback = '+90') => {
    const match = String(value ?? '').trim().match(/^(\+\d{1,4})\s*(.+)$/);

    if (!match) {
        return { code: fallback, number: '' };
    }

    return {
        code: match[1],
        number: match[2].replace(/\D+/g, ''),
    };
};

const toStorageUrl = (path) => {
    const value = String(path ?? '').trim();

    if (!value) return null;
    if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('/storage/')) {
        return value;
    }

    return `/storage/${value.replace(/^\/+/, '')}`;
};

const normalizeSingleFile = (file) => {
    if (!file) return null;

    if (typeof file === 'string') {
        return {
            path: file,
            name: file.split('/').pop() ?? 'file',
            url: toStorageUrl(file),
        };
    }

    const path = file.path ?? '';

    return {
        ...file,
        path,
        name: file.name ?? (path ? path.split('/').pop() : 'file'),
        url: file.url ?? toStorageUrl(path),
    };
};

const normalizeDocumentFile = (file) => {
    if (!file) return null;

    if (typeof file === 'string') {
        return {
            path: file,
            name: file.split('/').pop() ?? 'file',
            url: toStorageUrl(file),
        };
    }

    const path = file.path ?? '';

    return {
        ...file,
        path,
        name: file.name ?? (path ? path.split('/').pop() : 'file'),
        url: file.url ?? toStorageUrl(path),
    };
};

const formatRequiredLabel = (label) => String(label ?? '').replace(/\*/g, '<span class="required-star">*</span>');

const parsedPhone = parseDialPhone(props.verification.phone, '+90');
const parsedWhatsapp = parseDialPhone(props.verification.whatsapp_number, parsedPhone.code || '+90');


const displayLocale = 'en';
const removalModalOpen = ref(false);
const removalForm = useForm({
    reason: props.verification.removal_request?.reason ?? '',
    note: props.verification.removal_request?.note ?? '',
});


const ui = computed(() => ({
    title: 'Supplier Application | Sea Requests',
    eyebrow: 'Corporate application',
    heading: 'Submit your business in one complete application',
    text: 'Complete your business identity, location, contact details and official documents. Your profile goes live after approval.',
    identityHeading: 'Business Identity',
    locationHeading: 'Location',
    galleryHeading: 'Gallery',
    contactHeading: 'Contact',
    officialHeading: 'Official Details and Documents',
    businessName: 'Business Name *',
    primaryCategory: 'Business Primary Category *',
    subcategory: 'Business Subcategory',
    brands: 'Brands',
    selectCategory: 'Select category',
    selectSubcategory: 'Select subcategory',
    brandSearchPlaceholder: 'Search and add brands',
    brandHelper: 'Add the brands you actively supply or service. This will later improve RFQ matching quality.',
    brandEmpty: 'No matching brands were found.',
    serviceCoverageHeading: 'Service Countries and Ports',
    serviceCountries: 'Service Countries *',
    servicePorts: 'Service Ports *',
    selectServiceCountries: 'Select countries',
    selectPorts: 'Select ports',
    serviceCountryLimit: 'You can select up to 10 countries.',
    servicePortRequired: 'Select at least one port for each country.',
    noPortsForCountry: 'No ports available for this country.',
    country: 'Country *',
    city: 'City *',
    district: 'District',
    neighborhood: 'Neighborhood',
    postalCode: 'Postal Code *',
    fullAddress: 'Full Address *',
    fullAddressPlaceholder: 'Street, building number, floor, office or company address',
    logo: 'Logo *',
    addFiles: 'Add Files',
    openFile: 'Open',
    removeFile: 'Remove',
    noFiles: 'No files added yet.',
    mobilePhone: 'Mobile / GSM Line *',
    landlinePhone: 'Landline Business Phone',
    website: 'Website',
    email: 'Company Email *',
    whatsapp: 'WhatsApp',
    telegram: 'Telegram',
    instagram: 'Instagram',
    linkedin: 'LinkedIn',
    facebook: 'Facebook',
    twitter: 'Twitter',
    phonePlaceholder: '+90 555 000 00 00',
    landlinePlaceholder: '+90 212 000 00 00',
    websitePlaceholder: 'https://www.example.com',
    emailPlaceholder: 'contact@example.com',
    socialPlaceholder: 'https://',
    registrationNumber: 'Company Registration Number *',
    registrationDocuments: 'Company Registration Documents *',
    taxDocuments: 'Tax Documents *',
    authorizationDocuments: 'Authorization Documents',
    fileRules: 'PDF, JPG, JPEG, PNG or WEBP. Each file can be up to 10 MB.',
    submit: 'Submit Application',
    submitting: 'Submitting Application...',
}));

const removalUi = computed(() => ({
    eyebrow: 'Remove business',
    title: 'Do you want to remove your business from the platform?',
    text: 'Your business will not be deleted immediately. The request will be sent to the admin panel for review.',
    button: 'Remove Business',
    modalTitle: 'Business removal request',
    modalText: 'Please select why you want to remove your business. This will be shared with the admin team.',
    reason: 'Removal reason *',
    note: 'Explanation *',
    notePlaceholder: 'Please briefly explain why you want to remove your business.',
    placeholder: 'Select a reason',
    reasons: {
        business_closed: 'My business has closed',
        duplicate_listing: 'I created a duplicate listing',
        wrong_account: 'I applied with the wrong account',
        not_needed: 'I no longer want to be listed',
        other: 'Other',
    },
    cancel: 'Cancel',
    submit: 'Submit Request',
    submitting: 'Submitting Request...',
    pending: 'Your removal request is waiting for admin review.',
}));

const rejectionUi = computed(() => ({
    eyebrow: 'Revision required',
    title: 'Your application needs updates',
    text: 'The admin team did not approve your application at this time. Please update your details based on the notes below and submit again.',
    reason: 'Rejection reason',
    note: 'Admin note',
    reasons: {
        documents_incomplete: 'Documents are incomplete or insufficient',
        information_mismatch: 'The submitted information does not match',
        service_scope_unclear: 'The service scope is unclear',
        compliance_issue: 'There is a compliance or verification issue',
        other: 'Other',
    },
}));

const rejectionFeedback = computed(() => props.verification.rejection_feedback ?? {});

const form = useForm({
    company_name: props.verification.company_name ?? '',
    service_category_ids: props.verification.service_category_ids ?? [],
    service_subcategory_ids: props.verification.service_subcategory_ids ?? [],
    service_subcategories_by_category: props.verification.service_subcategories_by_category ?? {},
    service_brand_ids: props.verification.service_brand_ids ?? [],
    service_country_codes: props.verification.service_country_codes ?? [],
    service_ports_by_country: props.verification.service_ports_by_country ?? {},
    country: props.verification.country ?? '',
    company_city: props.verification.company_city ?? '',
    company_district: props.verification.company_district ?? '',
    company_neighborhood: props.verification.company_neighborhood ?? '',
    company_postal_code: props.verification.company_postal_code ?? '',
    company_address_line: props.verification.company_address_line ?? '',
    company_overview: props.verification.company_overview ?? props.verification.company_description ?? '',
    phone_country_code: parsedPhone.code,
    phone_local_number: parsedPhone.number,
    phone: props.verification.phone ?? '',
    landline_phone: props.verification.landline_phone ?? '',
    website_url: props.verification.website_url ?? '',
    contact_email: props.verification.contact_email ?? '',
    whatsapp_country_code: parsedWhatsapp.code,
    whatsapp_local_number: parsedWhatsapp.number,
    whatsapp_number: props.verification.whatsapp_number ?? '',
    telegram_url: props.verification.telegram_url ?? '',
    instagram_url: props.verification.instagram_url ?? '',
    linkedin_url: props.verification.linkedin_url ?? '',
    facebook_url: props.verification.facebook_url ?? '',
    twitter_url: props.verification.twitter_url ?? '',
    registration_number: props.verification.registration_number ?? '',
    keep_company_logo_path: props.verification.company_logo?.path ?? '',
    existing_company_registration_documents: (props.verification.company_registration_documents ?? []).map((item) => item.path),
    existing_tax_certificate_documents: (props.verification.tax_certificate_documents ?? []).map((item) => item.path),
    existing_service_authorization_documents: (props.verification.service_authorization_documents ?? []).map((item) => item.path),
    company_logo: null,
    company_registration_documents: [],
    tax_certificate_documents: [],
    service_authorization_documents: [],
});

const fieldRefs = {
    company_name: ref(null),
    service_category_ids: ref(null),
    service_subcategory_ids: ref(null),
    service_brand_ids: ref(null),
    service_country_codes: ref(null),
    service_ports_by_country: ref(null),
    country: ref(null),
    company_city: ref(null),
    company_address_line: ref(null),
    company_overview: ref(null),
    phone: ref(null),
    contact_email: ref(null),
    registration_number: ref(null),
};

const categoryOptions = computed(() => props.categories);
const brandOptions = computed(() => props.brands);

const serviceCountries = computed(() => {
    const displayNames = typeof Intl !== 'undefined' && typeof Intl.DisplayNames === 'function'
        ? new Intl.DisplayNames([displayLocale], { type: 'region' })
        : null;

    return [...props.serviceCountries]
        .map((country) => {
            const code = String(country.code ?? '').toUpperCase();
            const fallbackName = String(country.name ?? '').trim();
            const localizedName = displayNames?.of(code);
            const resolvedName = localizedName && localizedName !== code ? localizedName : (fallbackName || code);
            const name = code === 'TR' ? 'Turkey' : resolvedName;

            return {
                code,
                name,
            };
        })
        .sort((left, right) => left.name.localeCompare(right.name, displayLocale));
});

const serviceCountryNameMap = computed(() => new Map(
    serviceCountries.value.map((country) => [country.code, country.name])
));

const portsByCountry = computed(() => Object.fromEntries(
    Object.entries(props.portsByCountry ?? {}).map(([countryCode, ports]) => [
        countryCode,
        (ports ?? []).map((port) => ({
            id: Number(port.id),
            country_code: String(port.country_code ?? countryCode).toUpperCase(),
            country_name: serviceCountryNameMap.value.get(String(port.country_code ?? countryCode).toUpperCase()) ?? String(port.country_name ?? port.country_code ?? countryCode),
            port_name: String(port.port_name ?? ''),
            unlocode: String(port.unlocode ?? ''),
        })),
    ])
));

const buildServicePortGroups = () => {
    const countryCodes = props.verification.service_country_codes ?? [];
    const portMap = props.verification.service_ports_by_country ?? {};

    if (!countryCodes.length) {
        return [{ country_code: '', port_ids: [] }];
    }

    return countryCodes.map((countryCode) => ({
        country_code: String(countryCode).toUpperCase(),
        port_ids: (portMap[countryCode] ?? []).map((value) => Number(value)),
    }));
};

const servicePortGroups = reactive(buildServicePortGroups());

const syncServicePortForm = () => {
    form.service_country_codes = servicePortGroups
        .map((group) => String(group.country_code ?? '').toUpperCase())
        .filter(Boolean);

    form.service_ports_by_country = Object.fromEntries(
        servicePortGroups
            .filter((group) => String(group.country_code ?? '').trim() !== '')
            .map((group) => [
                String(group.country_code).toUpperCase(),
                [...new Set((group.port_ids ?? []).map((value) => Number(value)).filter(Boolean))],
            ])
    );
};

watch(servicePortGroups, syncServicePortForm, { deep: true, immediate: true });

const dialCodeOptions = [
    { label: 'Afghanistan (+93)', value: '+93' }, { label: 'Albania (+355)', value: '+355' },
    { label: 'Algeria (+213)', value: '+213' }, { label: 'Andorra (+376)', value: '+376' },
    { label: 'Angola (+244)', value: '+244' }, { label: 'Argentina (+54)', value: '+54' },
    { label: 'Armenia (+374)', value: '+374' }, { label: 'Australia (+61)', value: '+61' },
    { label: 'Austria (+43)', value: '+43' }, { label: 'Azerbaijan (+994)', value: '+994' },
    { label: 'Bahrain (+973)', value: '+973' }, { label: 'Bangladesh (+880)', value: '+880' },
    { label: 'Belarus (+375)', value: '+375' }, { label: 'Belgium (+32)', value: '+32' },
    { label: 'Bosnia and Herzegovina (+387)', value: '+387' }, { label: 'Brazil (+55)', value: '+55' },
    { label: 'Bulgaria (+359)', value: '+359' }, { label: 'Canada (+1)', value: '+1' },
    { label: 'Chile (+56)', value: '+56' }, { label: 'China (+86)', value: '+86' },
    { label: 'Colombia (+57)', value: '+57' }, { label: 'Croatia (+385)', value: '+385' },
    { label: 'Cyprus (+357)', value: '+357' }, { label: 'Czech Republic (+420)', value: '+420' },
    { label: 'Denmark (+45)', value: '+45' }, { label: 'Egypt (+20)', value: '+20' },
    { label: 'Estonia (+372)', value: '+372' }, { label: 'Finland (+358)', value: '+358' },
    { label: 'France (+33)', value: '+33' }, { label: 'Georgia (+995)', value: '+995' },
    { label: 'Germany (+49)', value: '+49' }, { label: 'Greece (+30)', value: '+30' },
    { label: 'Hong Kong (+852)', value: '+852' }, { label: 'Hungary (+36)', value: '+36' },
    { label: 'India (+91)', value: '+91' }, { label: 'Indonesia (+62)', value: '+62' },
    { label: 'Iran (+98)', value: '+98' }, { label: 'Iraq (+964)', value: '+964' },
    { label: 'Ireland (+353)', value: '+353' }, { label: 'Israel (+972)', value: '+972' },
    { label: 'Italy (+39)', value: '+39' }, { label: 'Japan (+81)', value: '+81' },
    { label: 'Jordan (+962)', value: '+962' }, { label: 'Russia (+7)', value: '+7' },
    { label: 'Kuwait (+965)', value: '+965' }, { label: 'Latvia (+371)', value: '+371' },
    { label: 'Lebanon (+961)', value: '+961' }, { label: 'Libya (+218)', value: '+218' },
    { label: 'Lithuania (+370)', value: '+370' }, { label: 'Luxembourg (+352)', value: '+352' },
    { label: 'Malaysia (+60)', value: '+60' }, { label: 'Malta (+356)', value: '+356' },
    { label: 'Mexico (+52)', value: '+52' }, { label: 'Moldova (+373)', value: '+373' },
    { label: 'Montenegro (+382)', value: '+382' }, { label: 'Morocco (+212)', value: '+212' },
    { label: 'Netherlands (+31)', value: '+31' }, { label: 'New Zealand (+64)', value: '+64' },
    { label: 'Nigeria (+234)', value: '+234' }, { label: 'North Macedonia (+389)', value: '+389' },
    { label: 'Norway (+47)', value: '+47' }, { label: 'Oman (+968)', value: '+968' },
    { label: 'Pakistan (+92)', value: '+92' }, { label: 'Philippines (+63)', value: '+63' },
    { label: 'Poland (+48)', value: '+48' }, { label: 'Portugal (+351)', value: '+351' },
    { label: 'Qatar (+974)', value: '+974' }, { label: 'Romania (+40)', value: '+40' },
    { label: 'Kazakhstan (+7)', value: '+7' }, { label: 'Saudi Arabia (+966)', value: '+966' },
    { label: 'Serbia (+381)', value: '+381' }, { label: 'Singapore (+65)', value: '+65' },
    { label: 'Slovakia (+421)', value: '+421' }, { label: 'Slovenia (+386)', value: '+386' },
    { label: 'South Africa (+27)', value: '+27' }, { label: 'South Korea (+82)', value: '+82' },
    { label: 'Spain (+34)', value: '+34' }, { label: 'Sri Lanka (+94)', value: '+94' },
    { label: 'Sweden (+46)', value: '+46' }, { label: 'Switzerland (+41)', value: '+41' },
    { label: 'Syria (+963)', value: '+963' }, { label: 'Taiwan (+886)', value: '+886' },
    { label: 'Thailand (+66)', value: '+66' }, { label: 'Tunisia (+216)', value: '+216' },
    { label: 'Turkey (+90)', value: '+90' }, { label: 'Ukraine (+380)', value: '+380' },
    { label: 'United Arab Emirates (+971)', value: '+971' }, { label: 'United Kingdom (+44)', value: '+44' },
    { label: 'United States (+1)', value: '+1' }, { label: 'Uruguay (+598)', value: '+598' },
    { label: 'Uzbekistan (+998)', value: '+998' }, { label: 'Vietnam (+84)', value: '+84' },
    { label: 'Yemen (+967)', value: '+967' },
];

const countryOptions = [
    'Albania', 'Algeria', 'Argentina', 'Australia', 'Austria', 'Azerbaijan', 'Bahrain', 'Bangladesh', 'Belgium',
    'Brazil', 'Bulgaria', 'Canada', 'Chile', 'China', 'Colombia', 'Croatia', 'Cyprus', 'Czech Republic', 'Denmark',
    'Egypt', 'Estonia', 'Finland', 'France', 'Georgia', 'Germany', 'Greece', 'Hong Kong', 'Hungary', 'India',
    'Indonesia', 'Iraq', 'Ireland', 'Israel', 'Italy', 'Japan', 'Jordan', 'Kazakhstan', 'Kuwait', 'Latvia',
    'Lebanon', 'Libya', 'Lithuania', 'Luxembourg', 'Malaysia', 'Malta', 'Mexico', 'Moldova', 'Montenegro', 'Morocco',
    'Netherlands', 'New Zealand', 'Nigeria', 'Norway', 'Oman', 'Pakistan', 'Panama', 'Philippines', 'Poland',
    'Portugal', 'Qatar', 'Romania', 'Russia', 'Saudi Arabia', 'Serbia', 'Singapore', 'Slovakia', 'Slovenia',
    'South Africa', 'South Korea', 'Spain', 'Sri Lanka', 'Sweden', 'Switzerland', 'Tunisia', 'Turkey', 'Ukraine',
    'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay', 'Vietnam', 'Yemen',
];

const buildCategoryGroups = () => {
    const categories = props.verification.service_category_ids ?? [];
    const subcategoriesByCategory = props.verification.service_subcategories_by_category ?? {};
    const fallbackSubcategories = props.verification.service_subcategory_ids ?? [];

    if (!categories.length) {
        return [{ category_id: '', subcategory_ids: [] }];
    }

    return categories.map((categoryId, index) => ({
        category_id: String(categoryId),
        subcategory_ids: ((subcategoriesByCategory[String(categoryId)] ?? subcategoriesByCategory[Number(categoryId)] ?? [])
            .map((value) => Number(value))
            .filter(Boolean)).length
            ? (subcategoriesByCategory[String(categoryId)] ?? subcategoriesByCategory[Number(categoryId)] ?? [])
                .map((value) => Number(value))
                .filter(Boolean)
            : (fallbackSubcategories[index] ? [Number(fallbackSubcategories[index])] : []),
    }));
};

const categoryGroups = reactive(buildCategoryGroups());

const syncCategoryForm = () => {
    form.service_category_ids = categoryGroups.map((group) => group.category_id).filter(Boolean).map((value) => Number(value));
    form.service_subcategory_ids = categoryGroups.flatMap((group) => group.subcategory_ids).filter(Boolean).map((value) => Number(value));
    form.service_subcategories_by_category = Object.fromEntries(
        categoryGroups
            .filter((group) => String(group.category_id ?? '').trim() !== '')
            .map((group) => [
                String(group.category_id),
                [...new Set((group.subcategory_ids ?? []).map((value) => Number(value)).filter(Boolean))],
            ])
    );
};

watch(categoryGroups, syncCategoryForm, { deep: true, immediate: true });

const existingDocuments = reactive({
    company_registration_documents: (props.verification.company_registration_documents ?? []).map(normalizeDocumentFile).filter(Boolean),
    tax_certificate_documents: (props.verification.tax_certificate_documents ?? []).map(normalizeDocumentFile).filter(Boolean),
    service_authorization_documents: (props.verification.service_authorization_documents ?? []).map(normalizeDocumentFile).filter(Boolean),
});

const singleMedia = reactive({
    company_logo: normalizeSingleFile(props.verification.company_logo),
});

const newDocuments = reactive({
    company_registration_documents: [],
    tax_certificate_documents: [],
    service_authorization_documents: [],
});

const newSingles = reactive({
    company_logo: null,
});

const documentConfigs = computed(() => [
    {
        key: 'company_registration_documents',
        label: ui.value.registrationDocuments,
        existing: existingDocuments.company_registration_documents,
        fresh: newDocuments.company_registration_documents,
        error: form.errors.company_registration_documents,
        itemError: form.errors['company_registration_documents.0'],
    },
    {
        key: 'tax_certificate_documents',
        label: ui.value.taxDocuments,
        existing: existingDocuments.tax_certificate_documents,
        fresh: newDocuments.tax_certificate_documents,
        error: form.errors.tax_certificate_documents,
        itemError: form.errors['tax_certificate_documents.0'],
    },
    {
        key: 'service_authorization_documents',
        label: ui.value.authorizationDocuments,
        existing: existingDocuments.service_authorization_documents,
        fresh: newDocuments.service_authorization_documents,
        error: form.errors.service_authorization_documents,
        itemError: form.errors['service_authorization_documents.0'],
    },
]);

const triggerFileInput = (key) => document.getElementById(`file-input-${key}`)?.click();
const triggerSingleInput = (key) => document.getElementById(`single-file-input-${key}`)?.click();

const appendFiles = (key, event) => {
    const files = Array.from(event.target.files ?? []);
    if (!files.length) return;

    newDocuments[key].push(...files.map((file) => ({
        id: `${file.name}-${file.size}-${Date.now()}-${Math.random()}`,
        file,
        name: file.name,
        url: URL.createObjectURL(file),
    })));

    form[key] = newDocuments[key].map((item) => item.file);
    form.clearErrors(key);
    event.target.value = '';
};

const assignSingleFile = (key, event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    newSingles[key] = {
        id: `${file.name}-${file.size}-${Date.now()}`,
        file,
        name: file.name,
        url: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
    };

    form[key] = file;
    form[`keep_${key}_path`] = '';
    singleMedia[key] = null;
    form.clearErrors(key);
    event.target.value = '';
};

const removeExistingDocument = (key, path) => {
    existingDocuments[key] = existingDocuments[key].filter((item) => item.path !== path);
    form[`existing_${key}`] = existingDocuments[key].map((item) => item.path);
};

const removeNewDocument = (key, id) => {
    const current = newDocuments[key].find((item) => item.id === id);
    if (current?.url) URL.revokeObjectURL(current.url);
    newDocuments[key] = newDocuments[key].filter((item) => item.id !== id);
    form[key] = newDocuments[key].map((item) => item.file);
};

const removeSingleMedia = (key) => {
    if (newSingles[key]?.url) URL.revokeObjectURL(newSingles[key].url);
    newSingles[key] = null;
    singleMedia[key] = null;
    form[key] = null;
    form[`keep_${key}_path`] = '';
};

const focusFirstError = async (errors = form.errors) => {
    const firstField = Object.keys(errors)[0];
    if (!firstField) return;

    const rootField = firstField.split('.')[0];

    await nextTick();

    if (fieldRefs[rootField]?.value) {
        fieldRefs[rootField].value.scrollIntoView({ behavior: 'smooth', block: 'center' });
        fieldRefs[rootField].value.focus?.();
        return;
    }

    document.querySelector(`[data-section-field="${rootField}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

const goToField = async (key) => {
    const fieldMap = {
        company_name: 'company_name',
        service_category_ids: 'service_category_ids',
        service_brand_ids: 'service_brand_ids',
        service_coverage: 'service_country_codes',
        country: 'country',
        city: 'country',
        address: 'company_address_line',
        phone: 'phone',
        contact_email: 'contact_email',
        overview: 'company_overview',
        registration_number: 'registration_number',
        logo: 'company_logo',
        official_documents: 'company_registration_documents',
    };

    const target = fieldMap[key] ?? key;

    await nextTick();

    if (fieldRefs[target]?.value) {
        fieldRefs[target].value.scrollIntoView({ behavior: 'smooth', block: 'center' });
        fieldRefs[target].value.focus?.();
        return;
    }

    document.querySelector(`[data-section-field="${target}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

const submit = () => {
    form.phone = `${form.phone_country_code} ${String(form.phone_local_number ?? '').replace(/\D+/g, '')}`.trim();
    form.whatsapp_number = String(form.whatsapp_local_number ?? '').trim()
        ? `${form.whatsapp_country_code} ${String(form.whatsapp_local_number ?? '').replace(/\D+/g, '')}`.trim()
        : '';
    form.company_registration_documents = newDocuments.company_registration_documents.map((item) => item.file);
    form.tax_certificate_documents = newDocuments.tax_certificate_documents.map((item) => item.file);
    form.service_authorization_documents = newDocuments.service_authorization_documents.map((item) => item.file);

    form.post(props.actionUrls.submit, {
        forceFormData: true,
        onError: focusFirstError,
    });
};

const submitRemovalRequest = () => {
    removalForm.post(props.actionUrls.removalRequest, {
        preserveScroll: true,
        onSuccess: () => {
            removalModalOpen.value = false;
        },
    });
};

onBeforeUnmount(() => {
    Object.values(newDocuments).flat().forEach((item) => {
        if (item.url) URL.revokeObjectURL(item.url);
    });
    Object.values(newSingles).forEach((item) => {
        if (item?.url) URL.revokeObjectURL(item.url);
    });
});
</script>

<template>
    <Head :title="ui.title" />

    <MainLayout>
        <section class="verification-shell">
            <header class="directory-intro-card verification-intro">
                <p class="directory-eyebrow">{{ ui.eyebrow }}</p>
                <h1 class="directory-page-title">{{ ui.heading }}</h1>
                <p class="directory-intro-copy">{{ ui.text }}</p>
            </header>

            <div class="verification-layout">
                <div class="verification-main">
                    <section v-if="adminContext.enabled" class="admin-edit-card">
                        <p class="directory-eyebrow">Admin Edit</p>
                        <h2 class="directory-section-title">{{ adminContext.targetUserName }}</h2>
                    </section>

                    <section v-if="props.verification.approval_status === 'rejected' && rejectionFeedback.reason" class="rejection-card">
                        <p class="directory-eyebrow">{{ rejectionUi.eyebrow }}</p>
                        <h2 class="directory-section-title">{{ rejectionUi.title }}</h2>
                        <p class="rejection-copy">{{ rejectionUi.text }}</p>

                        <div class="rejection-grid">
                            <div class="rejection-block">
                                <span>{{ rejectionUi.reason }}</span>
                                <strong>{{ rejectionUi.reasons[rejectionFeedback.reason] ?? rejectionFeedback.reason }}</strong>
                            </div>
                        </div>

                        <div v-if="rejectionFeedback.note" class="rejection-note">
                            <span>{{ rejectionUi.note }}</span>
                            <p>{{ rejectionFeedback.note }}</p>
                        </div>
                    </section>

                    <SupplierVerificationForm
                        :ui="ui"
                        :form="form"
                        :dial-code-options="dialCodeOptions"
                        :category-options="categoryOptions"
                        :brand-options="brandOptions"
                        :service-countries="serviceCountries"
                        :ports-by-country="portsByCountry"
                        :service-port-groups="servicePortGroups"
                        :country-options="countryOptions"
                        :category-groups="categoryGroups"
                        :field-refs="fieldRefs"
                        :existing-documents="existingDocuments"
                        :new-documents="newDocuments"
                        :single-media="singleMedia"
                        :new-singles="newSingles"
                        :document-configs="documentConfigs"
                        :trigger-file-input="triggerFileInput"
                        :trigger-single-input="triggerSingleInput"
                        :append-files="appendFiles"
                        :assign-single-file="assignSingleFile"
                        :remove-existing-document="removeExistingDocument"
                        :remove-new-document="removeNewDocument"
                        :remove-single-media="removeSingleMedia"
                        :submit="submit"
                    />

                    <section v-if="!adminContext.enabled && props.verification.approval_status === 'approved'" class="removal-card">
                        <p class="directory-eyebrow">{{ removalUi.eyebrow }}</p>
                        <h2 class="directory-section-title">{{ removalUi.title }}</h2>
                        <p class="removal-copy">{{ removalUi.text }}</p>

                        <div v-if="props.verification.removal_request?.status === 'pending'" class="removal-pending">
                            {{ removalUi.pending }}
                        </div>

                        <button type="button" class="removal-button" @click="removalModalOpen = true">
                            {{ removalUi.button }}
                        </button>
                    </section>
                </div>

                <div class="verification-preview">
                    <SupplierVerificationPreview
                        :form="form"
                        :ui="ui"
                        :service-port-groups="servicePortGroups"
                        :existing-documents="existingDocuments"
                        :new-documents="newDocuments"
                        :single-media="singleMedia"
                        :new-singles="newSingles"
                        :go-to-field="goToField"
                    />
                </div>
            </div>
        </section>

        <Transition name="removal-fade">
            <div v-if="removalModalOpen" class="removal-modal-backdrop" @click="removalModalOpen = false">
                <div class="removal-modal" @click.stop>
                    <button type="button" class="removal-close" @click="removalModalOpen = false">&times;</button>
                    <p class="directory-eyebrow">{{ removalUi.eyebrow }}</p>
                    <h2 class="directory-section-title">{{ removalUi.modalTitle }}</h2>
                    <p class="removal-copy">{{ removalUi.modalText }}</p>

                    <form class="removal-form" @submit.prevent="submitRemovalRequest">
                        <label class="removal-field">
                            <span v-html="formatRequiredLabel(removalUi.reason)"></span>
                            <select v-model="removalForm.reason">
                                <option value="">{{ removalUi.placeholder }}</option>
                                <option v-for="(label, key) in removalUi.reasons" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <small v-if="removalForm.errors.reason" class="removal-error">{{ removalForm.errors.reason }}</small>
                        </label>

                        <label class="removal-field">
                            <span v-html="formatRequiredLabel(removalUi.note)"></span>
                            <textarea
                                v-model="removalForm.note"
                                rows="4"
                                :placeholder="removalUi.notePlaceholder"
                            />
                            <small v-if="removalForm.errors.note" class="removal-error">{{ removalForm.errors.note }}</small>
                        </label>

                        <div class="removal-actions">
                            <button type="button" class="removal-secondary" @click="removalModalOpen = false">
                                {{ removalUi.cancel }}
                            </button>
                            <button type="submit" class="removal-primary" :disabled="removalForm.processing">
                                {{ removalForm.processing ? removalUi.submitting : removalUi.submit }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </MainLayout>
</template>

<style scoped>
.verification-shell { padding: 16px 0 56px; }
.verification-intro { margin-bottom: 22px; }
.verification-layout { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(300px, 0.65fr); gap: 24px; align-items: start; }
.verification-main,.verification-preview { min-width: 0; }
.verification-main { display: grid; gap: 18px; }
.verification-preview { position: sticky; top: 24px; }
.admin-edit-card{padding:24px 26px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:rgba(255,255,255,.94);box-shadow:0 20px 42px rgba(15,23,42,.06)}
.rejection-card{padding:24px 26px;border:1px solid rgba(180,35,24,.12);border-radius: 10px;background:rgba(255,255,255,.94);box-shadow:0 20px 42px rgba(15,23,42,.06)}
.rejection-copy{margin:14px 0 0;color:#64748b;font-size:.95rem;line-height:1.7;max-width:68ch}
.rejection-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:18px}
.rejection-block,.rejection-note{display:grid;gap:8px;padding:16px 18px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:rgba(255,255,255,.88)}
.rejection-block span,.rejection-note span{color:#64748b;font-size:.8rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase}
.rejection-block strong{color:#020617;font-size:.94rem;font-weight:560;line-height:1.6}
.rejection-field-list{display:flex;flex-wrap:wrap;gap:10px}
.rejection-field-chip{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 14px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff;color:#0f172a;font-size:.84rem;font-weight:560}
.rejection-note p{margin:0;color:#334155;font-size:.94rem;font-weight:460;line-height:1.7}
.removal-card{padding:24px 26px;border:1px solid rgba(244,63,94,.12);border-radius: 10px;background:rgba(255,255,255,.94);box-shadow:0 20px 42px rgba(15,23,42,.06)}
.removal-copy{margin:14px 0 0;color:#64748b;font-size:.95rem;line-height:1.7;max-width:68ch}
.removal-button,.removal-primary,.removal-secondary{display:inline-flex;align-items:center;justify-content:center;min-height:46px;min-width:168px;padding:0 18px;border-radius: 10px;font-size:.88rem;font-weight:600;white-space:nowrap}
.removal-button{margin-top:18px}
.removal-button,.removal-primary{border:1px solid #7f1d1d;background:#7f1d1d;color:#fff}
.removal-secondary{border:1px solid rgba(4,21,31,.1);background:#fff;color:#0f172a}
.removal-pending{display:inline-flex;align-items:center;justify-content:center;min-height:38px;margin-top:16px;padding:0 14px;border:1px solid rgba(180,83,9,.12);border-radius: 10px;background:rgba(254,243,199,.72);color:#b45309;font-size:.84rem;font-weight:600}
.removal-fade-enter-active,.removal-fade-leave-active{transition:opacity .18s ease}
.removal-fade-enter-from,.removal-fade-leave-to{opacity:0}
.removal-modal-backdrop{position:fixed;inset:0;z-index:1300;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(4,21,31,.58);backdrop-filter:blur(10px)}
.removal-modal{position:relative;width:min(540px,100%);padding:24px 24px 22px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff;box-shadow:0 30px 60px rgba(15,23,42,.16)}
.removal-close{position:absolute;top:16px;right:16px;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff;color:#0f172a;font-size:1.45rem;line-height:1}
.removal-form{display:grid;gap:16px;margin-top:18px}
.removal-field{display:grid;gap:8px}
.removal-field span{color:rgba(4,21,31,.78);font-size:.88rem;font-weight:500}
:deep(.required-star){color:#be123c}
.removal-field select{width:100%;min-height:48px;border:1px solid rgba(4,21,31,.12);border-radius: 10px;padding:0 14px;background:#fff;color:#0f172a;font-size:.94rem;font-weight:500}
.removal-field textarea{width:100%;min-height:128px;border:1px solid rgba(4,21,31,.12);border-radius: 10px;padding:14px;background:#fff;color:#0f172a;font-size:.94rem;font-weight:500;line-height:1.6;resize:vertical}
.removal-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}
.removal-actions > .removal-primary,.removal-actions > .removal-secondary{flex:0 0 168px}
.removal-error{color:#b42318;font-size:.85rem}
@media (max-width: 1100px) {
    .verification-layout { grid-template-columns: 1fr; }
    .verification-preview { position: static; }
    .rejection-grid { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .removal-actions > .removal-primary,.removal-actions > .removal-secondary,.removal-button{width:100%;min-width:0}
}
</style>




