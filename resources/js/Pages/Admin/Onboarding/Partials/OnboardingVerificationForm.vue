<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import SupplierVerificationForm from '../../../Auth/Partials/SupplierVerificationForm.vue';

const props = defineProps({
    options: { type: Object, required: true },
    submitUrl: { type: String, required: true },
});

const emit = defineEmits(['close', 'saved']);

const displayLocale = 'en';

const form = useForm({
    audience: 'seller',
    company_name: '',
    service_category_ids: [],
    service_subcategory_ids: [],
    service_subcategories_by_category: {},
    service_brand_ids: [],
    service_country_codes: [],
    service_ports_by_country: {},
    country: '',
    company_city: '',
    company_district: '',
    company_neighborhood: '',
    company_postal_code: '',
    company_address_line: '',
    company_overview: '',
    port_coverage: '',
    phone_country_code: '+90',
    phone_local_number: '',
    phone: '',
    landline_phone: '',
    website_url: '',
    contact_email: '',
    whatsapp_country_code: '+90',
    whatsapp_local_number: '',
    whatsapp_number: '',
    telegram_url: '',
    instagram_url: '',
    linkedin_url: '',
    facebook_url: '',
    twitter_url: '',
    registration_number: '',
    keep_company_logo_path: '',
    existing_company_registration_documents: [],
    company_logo: null,
    company_registration_documents: [],
});

const ui = computed(() => ({
    title: `${form.audience === 'buyer' ? 'Buyer' : 'Supplier'} Onboarding Profile | Sea Requests`,
    eyebrow: 'Pre-registration',
    heading: `Create a ${form.audience === 'buyer' ? 'buyer' : 'supplier'} onboarding profile`,
    text: form.audience === 'buyer'
        ? 'Create the buyer account with the same controlled profile structure. Only company name and email are required; the buyer can update profile details later.'
        : 'Fill the same supplier verification structure before creating the platform account. Only business name and company email are required at this stage; the supplier can complete missing fields later.',
    identityHeading: 'Business Identity',
    locationHeading: 'Location',
    galleryHeading: 'Gallery',
    contactHeading: 'Contact',
    officialHeading: 'Official Details and Documents',
    businessName: 'Business Name *',
    primaryCategory: 'Business Primary Category',
    subcategory: 'Business Subcategory',
    brands: 'Brands',
    selectCategory: 'Select category',
    selectSubcategory: 'Select subcategory',
    brandSearchPlaceholder: 'Search and add brands',
    categoryHelper: 'Select any known categories and subcategories from the source profile. The supplier can confirm or update them during verification.',
    brandHelper: 'Select known brands if the source profile clearly mentions them. Leave empty when the information is not reliable.',
    serviceCoverageHelper: 'Select known service countries and ports from the source profile. These fields help prefill the supplier verification form later.',
    brandEmpty: 'No matching brands were found.',
    serviceCoverageHeading: 'Service Countries and Ports',
    serviceCountries: 'Service Countries',
    servicePorts: 'Service Ports',
    selectServiceCountries: 'Select countries',
    selectPorts: 'Select ports',
    serviceCountryLimit: 'You can select up to 10 countries.',
    servicePortRequired: 'Select at least one port for each country when service countries are selected.',
    noPortsForCountry: 'No ports available for this country.',
    country: 'Country',
    city: 'City',
    district: 'District',
    neighborhood: 'Neighborhood',
    postalCode: 'Postal Code',
    fullAddress: 'Full Address',
    fullAddressPlaceholder: 'Street, building number, floor, office or company address',
    logo: 'Logo',
    addFiles: 'Add Files',
    openFile: 'Open',
    removeFile: 'Remove',
    noFiles: 'No files added yet.',
    mobilePhone: 'Mobile / GSM Line',
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
    registrationNumber: 'Company Registration Number',
    registrationDocuments: 'Company Registration Documents',
    fileRules: 'PDF, JPG, JPEG, PNG or WEBP. Each file can be up to 10 MB.',
    submit: 'Create Account & Send Email',
    submitting: 'Creating Account...',
}));

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

const categoryOptions = computed(() => props.options.categories ?? []);
const brandOptions = computed(() => props.options.brands ?? []);

const serviceCountries = computed(() => {
    const displayNames = typeof Intl !== 'undefined' && typeof Intl.DisplayNames === 'function'
        ? new Intl.DisplayNames([displayLocale], { type: 'region' })
        : null;

    return [...(props.options.serviceCountries ?? [])]
        .map((country) => {
            const code = String(country.code ?? '').toUpperCase();
            const fallbackName = String(country.name ?? '').trim();
            const localizedName = displayNames?.of(code);
            const resolvedName = localizedName && localizedName !== code ? localizedName : (fallbackName || code);

            return {
                code,
                name: code === 'TR' ? 'Turkey' : resolvedName,
            };
        })
        .sort((left, right) => left.name.localeCompare(right.name, displayLocale));
});

const serviceCountryNameMap = computed(() => new Map(
    serviceCountries.value.map((country) => [country.code, country.name])
));

const portsByCountry = computed(() => Object.fromEntries(
    Object.entries(props.options.portsByCountry ?? {}).map(([countryCode, ports]) => [
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

const countryOptions = computed(() => serviceCountries.value.map((country) => country.name));
const dialCodeOptions = computed(() => Array.isArray(props.options.dialCodeOptions) ? props.options.dialCodeOptions : []);
const categoryGroups = reactive([{ category_id: '', subcategory_ids: [] }]);
const servicePortGroups = reactive([{ country_code: '', port_ids: [] }]);

watch(categoryGroups, () => {
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
}, { deep: true, immediate: true });

watch(servicePortGroups, () => {
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
}, { deep: true, immediate: true });

const existingDocuments = reactive({
    company_registration_documents: [],
});

const singleMedia = reactive({
    company_logo: null,
});

const newDocuments = reactive({
    company_registration_documents: [],
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

const submit = () => {
    form.phone = String(form.phone_local_number ?? '').trim()
        ? `${form.phone_country_code} ${String(form.phone_local_number ?? '').replace(/\D+/g, '')}`.trim()
        : '';
    form.whatsapp_number = String(form.whatsapp_local_number ?? '').trim()
        ? `${form.whatsapp_country_code} ${String(form.whatsapp_local_number ?? '').replace(/\D+/g, '')}`.trim()
        : '';
    form.company_registration_documents = newDocuments.company_registration_documents.map((item) => item.file);

    form.post(props.submitUrl, {
        forceFormData: true,
        preserveScroll: true,
        onError: focusFirstError,
        onSuccess: () => {
            emit('saved');
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
    <section class="onboarding-verification-shell">
        <header class="onboarding-verification-header">
            <div>
                <p class="directory-eyebrow">Pre-registration</p>
                <h3>Add Manual Onboarding Profile</h3>
                <span>Select the account type, complete the known company details, and save. The platform account and completion email are created automatically.</span>
            </div>
            <button type="button" class="secondary-action" @click="emit('close')">Close</button>
        </header>

        <section class="account-type-panel">
            <div>
                <p class="directory-eyebrow">Account Type</p>
                <h4>Who are we creating this profile for?</h4>
                <span>Supplier profiles continue to verification after login. Buyer profiles open the buyer dashboard after account completion.</span>
            </div>
            <div class="account-type-options" role="radiogroup" aria-label="Onboarding account type">
                <label :class="['account-type-option', { active: form.audience === 'seller' }]">
                    <input v-model="form.audience" type="radio" value="seller" />
                    <strong>Supplier</strong>
                    <span>For companies that will complete verification and submit offers.</span>
                </label>
                <label :class="['account-type-option', { active: form.audience === 'buyer' }]">
                    <input v-model="form.audience" type="radio" value="buyer" />
                    <strong>Buyer</strong>
                    <span>For companies that will create RFQs and manage orders.</span>
                </label>
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
    </section>
</template>

<style scoped>
.onboarding-verification-shell {
    display: grid;
    gap: 1rem;
}

.onboarding-verification-header {
    align-items: flex-start;
    display: flex;
    gap: 1rem;
    justify-content: space-between;
}

.onboarding-verification-header h3 {
    color: #0f1f33;
    font-size: 1.2rem;
    margin: 0.15rem 0 0.25rem;
}

.onboarding-verification-header span {
    color: #64748b;
    display: block;
    font-size: 0.86rem;
    line-height: 1.55;
    max-width: 760px;
}

.account-type-panel {
    background: #f8fbfc;
    border-radius: 1.1rem;
    display: grid;
    gap: 1rem;
    padding: 1.1rem;
}

.account-type-panel h4 {
    color: #0f1f33;
    font-size: 1rem;
    margin: 0.1rem 0 0.25rem;
}

.account-type-panel span {
    color: #64748b;
    font-size: 0.84rem;
    line-height: 1.5;
}

.account-type-options {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.account-type-option {
    align-items: flex-start;
    background: #fff;
    border: 1px solid #dce7ea;
    border-radius: 0.95rem;
    cursor: pointer;
    display: grid;
    gap: 0.3rem;
    padding: 0.9rem;
    transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}

.account-type-option input {
    accent-color: #0f766e;
    margin: 0;
}

.account-type-option strong {
    color: #0f1f33;
    font-size: 0.95rem;
}

.account-type-option.active {
    border-color: #0f766e;
    box-shadow: 0 12px 28px rgba(15, 118, 110, 0.12);
}

:deep(.form-actions) {
    justify-content: flex-end;
}

@media (max-width: 640px) {
    .onboarding-verification-header {
        flex-direction: column;
    }

    .account-type-options {
        grid-template-columns: 1fr;
    }
}
</style>
