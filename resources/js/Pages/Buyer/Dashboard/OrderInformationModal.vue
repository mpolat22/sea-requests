<script setup>
import { computed, nextTick, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    order: {
        type: Object,
        default: null,
    },
    canEdit: {
        type: Boolean,
        default: false,
    },
    updateUrl: {
        type: String,
        default: '',
    },
    returnTo: {
        type: String,
        default: 'orders',
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
    loadError: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'retry']);

const copy = {
    title: 'Order Information',
    intro: 'Complete buyer-side billing and delivery or service instructions before invoice upload starts.',
    save: 'Save Order Information',
    update: 'Update Order Information',
    cancel: 'Cancel',
    retry: 'Retry',
    billing: 'Billing Information',
    billingIntro: 'Share the invoice party and billing contact details the supplier should rely on for invoicing and payment paperwork.',
    invoiceCompany: 'Invoice Company Name',
    invoiceAddress: 'Invoice Address',
    taxId: 'Tax / VAT / Company ID',
    billingContactName: 'Billing Contact Name',
    billingContactEmail: 'Billing Contact Email',
    billingContactPhone: 'Billing Contact Phone',
    deliveryInstructions: 'Delivery Instructions',
    deliveryIntro: 'Confirm the delivery destination and the receiving contact the supplier should use for the awarded spare parts order.',
    deliveryType: 'Delivery Type',
    deliveryCountry: 'Delivery Country',
    deliveryPort: 'Delivery Port',
    deliveryAddress: 'Delivery Address',
    receiverName: 'Receiver Name',
    receiverEmail: 'Receiver Email',
    receiverPhone: 'Receiver Phone',
    requiredDeliveryDate: 'Required Delivery Date',
    serviceInstructions: 'Service Instructions',
    serviceIntro: 'Confirm the attendance location and operational contact details the supplier should use before mobilization.',
    serviceLocationType: 'Service Location Type',
    serviceLocation: 'Attendance Location',
    serviceContactName: 'Contact Name',
    serviceContactEmail: 'Contact Email',
    serviceContactPhone: 'Contact Phone',
    serviceRequiredDate: 'Preferred Attendance Date',
    serviceNotes: 'Access / Technical Notes',
    hints: {
        billing_company_name: 'Use the legal company name exactly as it should appear on the supplier invoice.',
        billing_tax_id: 'Add the tax, VAT, or company registration number the supplier should print on the invoice.',
        billing_address: 'Provide the invoice address the supplier should use on billing and official documents.',
        billing_contact_name: 'Name the person the supplier should contact for invoice or payment questions.',
        billing_contact_email: 'Use the email address that should receive invoices and payment-related communication.',
        billing_contact_phone: 'Use the direct phone number the supplier can call about invoice or payment matters.',
        delivery_target_type: 'Choose the destination type the supplier should plan the delivery against.',
        delivery_country: 'Confirm the country where the supplier should deliver the awarded spare parts.',
        delivery_port: 'State the delivery port the supplier should reference on delivery paperwork.',
        delivery_required_date: 'Tell the supplier the target date the awarded items should be delivered by.',
        delivery_address: 'Provide the full delivery address, vessel note, warehouse note, or agent instruction.',
        delivery_contact_name: 'Name the person who will receive or coordinate the delivered goods.',
        delivery_contact_email: 'Use the email address the supplier should use for delivery coordination.',
        delivery_contact_phone: 'Use the phone number the supplier should use for delivery coordination.',
        service_location_type: 'Choose the type of attendance location the supplier team should prepare for.',
        service_location: 'Specify the exact place where the supplier team should attend for service.',
        service_required_date: 'Tell the supplier the preferred attendance date for the awarded service scope.',
        service_contact_name: 'Name the operational contact the supplier team should coordinate with on arrival.',
        service_contact_email: 'Use the email address the supplier should contact for service attendance coordination.',
        service_contact_phone: 'Use the phone number the supplier should call for service attendance coordination.',
        service_instruction_notes: 'Add any boarding, permit, access, safety, or technical notes the supplier should know in advance.',
    },
    placeholders: {
        billing_company_name: 'Enter the legal company name the supplier should invoice.',
        billing_tax_id: 'Enter the tax, VAT, or company ID the supplier should print on the invoice.',
        billing_address: 'Enter the full invoice address the supplier should use on billing documents.',
        billing_contact_name: 'Enter the billing contact person the supplier should communicate with.',
        billing_contact_email: 'Enter the billing contact email the supplier should send invoices to.',
        billing_contact_phone: 'Enter the billing contact phone number the supplier should use.',
        delivery_country: 'Enter the country where the supplier should deliver the awarded items.',
        delivery_port: 'Enter the port the supplier should use for delivery coordination.',
        delivery_address: 'Enter the full delivery address, vessel note, warehouse note, or agent instruction.',
        delivery_contact_name: 'Enter the receiving contact name the supplier should coordinate with.',
        delivery_contact_email: 'Enter the receiving contact email the supplier should use for delivery updates.',
        delivery_contact_phone: 'Enter the receiving contact phone number the supplier should use.',
        service_location: 'Enter the exact attendance location the supplier team should go to.',
        service_contact_name: 'Enter the operational contact name the supplier team should coordinate with.',
        service_contact_email: 'Enter the operational contact email the supplier should use for attendance planning.',
        service_contact_phone: 'Enter the operational contact phone number the supplier should use.',
        service_instruction_notes: 'Enter access, permit, safety, technical, or attendance notes the supplier should know.',
    },
    options: {
        delivery_target_type: {
            vessel: 'Vessel',
            warehouse: 'Warehouse',
            office: 'Office',
            agent: 'Agent',
            other: 'Other',
        },
        service_location_type: {
            on_board: 'On board',
            port: 'Port',
            yard: 'Yard',
            other: 'Other',
        },
    },
};

const currentOrder = computed(() => props.order ?? {});
const isSpareParts = computed(() => currentOrder.value.request_type === 'spare_parts');
const fieldRefs = {};

const formatRequiredLabel = (label) => String(label ?? '').replace(/\*/g, '<span class="required-star">*</span>');

const portGroups = computed(() => (currentOrder.value.ports_by_country ?? [])
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

    return (currentOrder.value.country_names ?? []).filter(Boolean);
});

const firstSelectedPort = computed(() => portGroups.value
    .flatMap((group) => group.ports)
    .find((port) => (port?.name ?? '').trim() !== '')?.name ?? '');

const buildInitialForm = (order) => ({
    billing_company_name: order?.billing_company_name || order?.company_name || '',
    billing_address: order?.billing_address || '',
    billing_tax_id: order?.billing_tax_id || '',
    billing_contact_name: order?.billing_contact_name || '',
    billing_contact_email: order?.billing_contact_email || '',
    billing_contact_phone: order?.billing_contact_phone || '',
    delivery_target_type: order?.delivery_target_type || 'vessel',
    delivery_country: order?.delivery_country || selectedCountries.value[0] || '',
    delivery_port: order?.delivery_port || firstSelectedPort.value || '',
    delivery_address: order?.delivery_address || '',
    delivery_contact_name: order?.delivery_contact_name || '',
    delivery_contact_email: order?.delivery_contact_email || '',
    delivery_contact_phone: order?.delivery_contact_phone || '',
    delivery_required_date: order?.delivery_required_date || '',
    service_location_type: order?.service_location_type || 'port',
    service_location: order?.service_location || firstSelectedPort.value || '',
    service_contact_name: order?.service_contact_name || '',
    service_contact_email: order?.service_contact_email || '',
    service_contact_phone: order?.service_contact_phone || '',
    service_required_date: order?.service_required_date || '',
    service_instruction_notes: order?.service_instruction_notes || '',
});

const form = useForm(buildInitialForm(currentOrder.value));

const resetForm = () => {
    form.defaults(buildInitialForm(currentOrder.value));
    form.reset();
    form.clearErrors();
};

watch(
    () => [props.isOpen, currentOrder.value?.id],
    ([isOpen]) => {
        if (isOpen) {
            resetForm();
        }
    },
    { immediate: true }
);

const textValue = (value) => String(value ?? '').trim();
const hasValue = (value) => textValue(value).length > 0;
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const validateTextField = (value, { label, required = false, max = null }) => {
    const text = textValue(value);

    if (required && !text) {
        return `${label} is required.`;
    }

    if (!text) {
        return '';
    }

    if (max && text.length > max) {
        return `${label} must be ${max} characters or less.`;
    }

    return '';
};

const validateEmailField = (value, { label, required = false, max = 255 }) => {
    const text = textValue(value);

    if (required && !text) {
        return `${label} is required.`;
    }

    if (!text) {
        return '';
    }

    if (text.length > max) {
        return `${label} must be ${max} characters or less.`;
    }

    if (!emailPattern.test(text)) {
        return `Enter a valid ${label.toLowerCase()}.`;
    }

    return '';
};

const validateSelectField = (value, { label, allowedValues }) => {
    const text = textValue(value);

    if (!text) {
        return `${label} is required.`;
    }

    if (!allowedValues.includes(text)) {
        return `Select a valid ${label.toLowerCase()}.`;
    }

    return '';
};

const validateDateField = (value, { label }) => {
    const text = textValue(value);

    if (!text) {
        return `${label} is required.`;
    }

    if (Number.isNaN(Date.parse(text))) {
        return `Enter a valid ${label.toLowerCase()}.`;
    }

    return '';
};

const validationRules = computed(() => ({
    billing_company_name: () => validateTextField(form.billing_company_name, { label: copy.invoiceCompany, required: true, max: 255 }),
    billing_address: () => validateTextField(form.billing_address, { label: copy.invoiceAddress, required: true, max: 2000 }),
    billing_tax_id: () => validateTextField(form.billing_tax_id, { label: copy.taxId, max: 120 }),
    billing_contact_name: () => validateTextField(form.billing_contact_name, { label: copy.billingContactName, required: true, max: 120 }),
    billing_contact_email: () => validateEmailField(form.billing_contact_email, { label: copy.billingContactEmail, required: true, max: 255 }),
    billing_contact_phone: () => validateTextField(form.billing_contact_phone, { label: copy.billingContactPhone, required: true, max: 60 }),
    ...(isSpareParts.value
        ? {
            delivery_target_type: () => validateSelectField(form.delivery_target_type, {
                label: copy.deliveryType,
                allowedValues: Object.keys(copy.options.delivery_target_type),
            }),
            delivery_country: () => validateTextField(form.delivery_country, { label: copy.deliveryCountry, required: true, max: 120 }),
            delivery_port: () => validateTextField(form.delivery_port, { label: copy.deliveryPort, required: true, max: 120 }),
            delivery_required_date: () => validateDateField(form.delivery_required_date, { label: copy.requiredDeliveryDate }),
            delivery_address: () => validateTextField(form.delivery_address, { label: copy.deliveryAddress, required: true, max: 2000 }),
            delivery_contact_name: () => validateTextField(form.delivery_contact_name, { label: copy.receiverName, required: true, max: 120 }),
            delivery_contact_email: () => validateEmailField(form.delivery_contact_email, { label: copy.receiverEmail, required: true, max: 255 }),
            delivery_contact_phone: () => validateTextField(form.delivery_contact_phone, { label: copy.receiverPhone, required: true, max: 60 }),
        }
        : {
            service_location_type: () => validateSelectField(form.service_location_type, {
                label: copy.serviceLocationType,
                allowedValues: Object.keys(copy.options.service_location_type),
            }),
            service_location: () => validateTextField(form.service_location, { label: copy.serviceLocation, required: true, max: 255 }),
            service_required_date: () => validateDateField(form.service_required_date, { label: copy.serviceRequiredDate }),
            service_contact_name: () => validateTextField(form.service_contact_name, { label: copy.serviceContactName, required: true, max: 120 }),
            service_contact_email: () => validateEmailField(form.service_contact_email, { label: copy.serviceContactEmail, required: true, max: 255 }),
            service_contact_phone: () => validateTextField(form.service_contact_phone, { label: copy.serviceContactPhone, required: true, max: 60 }),
            service_instruction_notes: () => validateTextField(form.service_instruction_notes, { label: copy.serviceNotes, max: 2000 }),
        }),
}));

const validateField = (field) => {
    const rule = validationRules.value[field];
    return typeof rule === 'function' ? rule() : '';
};

const hasVisualInvalid = (field) => {
    const errorText = String(form.errors[field] ?? '').trim();

    if (!errorText) {
        return false;
    }

    return Boolean(validateField(field));
};

const visibleFieldError = (field) => {
    const errorText = String(form.errors[field] ?? '').trim();

    if (!errorText) {
        return '';
    }

    return validateField(field) ? errorText : '';
};

const clearFieldErrorIfValid = (field) => {
    if (validateField(field) === '') {
        form.clearErrors(field);
    }
};

const registerFieldRef = (field, element) => {
    if (element) {
        fieldRefs[field] = element;
    }
};

const focusFirstInvalidField = async (errors) => {
    const firstField = Object.keys(validationRules.value).find((field) => Boolean(errors[field]));

    if (!firstField) {
        return;
    }

    await nextTick();
    fieldRefs[firstField]?.focus?.();
};

const closeModal = () => {
    if (form.processing) {
        return;
    }

    emit('close');
};

const submitOrderInformation = async () => {
    if (!props.canEdit || !props.updateUrl) {
        return;
    }

    form.clearErrors();

    const errors = Object.fromEntries(
        Object.entries(validationRules.value)
            .map(([field, rule]) => [field, rule()])
            .filter(([, message]) => message)
    );

    if (Object.keys(errors).length) {
        form.setError(errors);
        await focusFirstInvalidField(errors);
        return;
    }

    form
        .transform((data) => ({
            ...data,
            return_to: props.returnTo,
        }))
        .put(props.updateUrl, {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
};
</script>

<template>
    <div v-if="isOpen" class="detail-modal-backdrop" @click.self="closeModal">
        <div class="detail-modal">
            <div class="detail-modal-head">
                <div class="detail-modal-copy">
                    <h3 class="detail-modal-title">{{ copy.title }}</h3>
                    <p class="detail-modal-intro">{{ copy.intro }}</p>
                </div>

                <button type="button" class="detail-modal-close" @click="closeModal">
                    {{ copy.cancel }}
                </button>
            </div>

            <div class="detail-modal-body">
                <div v-if="isLoading" class="modal-state-card">
                    <strong>Loading order details...</strong>
                    <p>Please wait while the latest order information is prepared.</p>
                </div>

                <div v-else-if="loadError" class="modal-state-card modal-state-card-error">
                    <strong>Order details could not be loaded.</strong>
                    <p>{{ loadError }}</p>
                    <div class="form-actions">
                        <button type="button" class="primary-action" @click="emit('retry')">
                            {{ copy.retry }}
                        </button>
                    </div>
                </div>

                <form v-else class="verification-card" @submit.prevent="submitOrderInformation">
                    <section class="section-card">
                        <div class="section-head">
                            <p class="directory-eyebrow section-card-eyebrow">{{ copy.billing }}</p>
                        </div>

                        <div class="identity-surface">
                            <div class="section-form section-form-narrow">
                                <p class="helper-copy">{{ copy.billingIntro }}</p>

                                <div class="form-grid">
                                    <label class="field form-field-wide">
                                        <span v-html="formatRequiredLabel(`${copy.invoiceCompany} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.billing_company_name }}</span>
                                        <div class="input-shell" :class="{ invalid: hasVisualInvalid('billing_company_name') }">
                                            <span class="input-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><path d="M3 7.5h14v8A1.5 1.5 0 0 1 15.5 17h-11A1.5 1.5 0 0 1 3 15.5v-8Z" stroke="currentColor" stroke-width="1.5"/><path d="m4.5 7.5 1.4-2.8A1.5 1.5 0 0 1 7.24 4h5.52c.57 0 1.08.32 1.34.83l1.4 2.67" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            </span>
                                            <input :ref="(el) => registerFieldRef('billing_company_name', el)" v-model="form.billing_company_name" type="text" :placeholder="copy.placeholders.billing_company_name" @input="clearFieldErrorIfValid('billing_company_name')">
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('billing_company_name') }}</span>
                                    </label>

                                    <label class="field">
                                        <span>{{ copy.taxId }}</span>
                                        <span class="field-hint">{{ copy.hints.billing_tax_id }}</span>
                                        <div class="input-shell" :class="{ invalid: hasVisualInvalid('billing_tax_id') }">
                                            <span class="input-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><path d="M4.5 6.5h11v7h-11z" stroke="currentColor" stroke-width="1.5"/><path d="M7 9.5h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            </span>
                                            <input :ref="(el) => registerFieldRef('billing_tax_id', el)" v-model="form.billing_tax_id" type="text" :placeholder="copy.placeholders.billing_tax_id" @input="clearFieldErrorIfValid('billing_tax_id')">
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('billing_tax_id') }}</span>
                                    </label>

                                    <label class="field form-field-wide">
                                        <span v-html="formatRequiredLabel(`${copy.invoiceAddress} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.billing_address }}</span>
                                        <div class="input-shell input-shell-textarea" :class="{ invalid: hasVisualInvalid('billing_address') }">
                                            <span class="input-icon input-icon-textarea" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><path d="M10 16.5s4.5-3.5 4.5-7a4.5 4.5 0 1 0-9 0c0 3.5 4.5 7 4.5 7Z" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="9.5" r="1.5" stroke="currentColor" stroke-width="1.5"/></svg>
                                            </span>
                                            <textarea :ref="(el) => registerFieldRef('billing_address', el)" v-model="form.billing_address" rows="4" :placeholder="copy.placeholders.billing_address" @input="clearFieldErrorIfValid('billing_address')"></textarea>
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('billing_address') }}</span>
                                    </label>

                                    <label class="field">
                                        <span v-html="formatRequiredLabel(`${copy.billingContactName} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.billing_contact_name }}</span>
                                        <div class="input-shell" :class="{ invalid: hasVisualInvalid('billing_contact_name') }">
                                            <span class="input-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><circle cx="10" cy="6.5" r="2.5" stroke="currentColor" stroke-width="1.5"/><path d="M5.5 16a4.5 4.5 0 0 1 9 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            </span>
                                            <input :ref="(el) => registerFieldRef('billing_contact_name', el)" v-model="form.billing_contact_name" type="text" :placeholder="copy.placeholders.billing_contact_name" @input="clearFieldErrorIfValid('billing_contact_name')">
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('billing_contact_name') }}</span>
                                    </label>

                                    <label class="field">
                                        <span v-html="formatRequiredLabel(`${copy.billingContactEmail} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.billing_contact_email }}</span>
                                        <div class="input-shell" :class="{ invalid: hasVisualInvalid('billing_contact_email') }">
                                            <span class="input-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><rect x="3.75" y="5" width="12.5" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="m5.5 7 4.5 3.75L14.5 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </span>
                                            <input :ref="(el) => registerFieldRef('billing_contact_email', el)" v-model="form.billing_contact_email" type="email" :placeholder="copy.placeholders.billing_contact_email" @input="clearFieldErrorIfValid('billing_contact_email')">
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('billing_contact_email') }}</span>
                                    </label>

                                    <label class="field">
                                        <span v-html="formatRequiredLabel(`${copy.billingContactPhone} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.billing_contact_phone }}</span>
                                        <div class="input-shell" :class="{ invalid: hasVisualInvalid('billing_contact_phone') }">
                                            <span class="input-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><path d="M6.75 4.75h1.5a1 1 0 0 1 1 .8l.4 2a1 1 0 0 1-.29.9L8.3 9.51a10 10 0 0 0 2.2 2.2l1.06-1.06a1 1 0 0 1 .9-.29l2 .4a1 1 0 0 1 .8 1v1.5a1 1 0 0 1-.9 1A9.5 9.5 0 0 1 5.75 5.65a1 1 0 0 1 1-.9Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                                            </span>
                                            <input :ref="(el) => registerFieldRef('billing_contact_phone', el)" v-model="form.billing_contact_phone" type="text" :placeholder="copy.placeholders.billing_contact_phone" @input="clearFieldErrorIfValid('billing_contact_phone')">
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('billing_contact_phone') }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="section-card">
                        <div class="section-head">
                            <p class="directory-eyebrow section-card-eyebrow">{{ isSpareParts ? copy.deliveryInstructions : copy.serviceInstructions }}</p>
                        </div>

                        <div class="identity-surface">
                            <div class="section-form section-form-narrow">
                                <p class="helper-copy">{{ isSpareParts ? copy.deliveryIntro : copy.serviceIntro }}</p>

                                <div v-if="isSpareParts" class="form-grid">
                                    <label class="field">
                                        <span v-html="formatRequiredLabel(`${copy.deliveryType} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.delivery_target_type }}</span>
                                        <div class="input-shell input-shell-select" :class="{ invalid: hasVisualInvalid('delivery_target_type') }">
                                            <span class="input-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><path d="M5 5.5h10M5 10h10M5 14.5h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            </span>
                                            <select :ref="(el) => registerFieldRef('delivery_target_type', el)" v-model="form.delivery_target_type" class="field-select" @change="clearFieldErrorIfValid('delivery_target_type')">
                                                <option value="">Select delivery type</option>
                                                <option v-for="(label, value) in copy.options.delivery_target_type" :key="value" :value="value">{{ label }}</option>
                                            </select>
                                            <span class="select-caret" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </span>
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('delivery_target_type') }}</span>
                                    </label>

                                    <label class="field">
                                        <span v-html="formatRequiredLabel(`${copy.deliveryCountry} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.delivery_country }}</span>
                                        <div class="input-shell" :class="{ invalid: hasVisualInvalid('delivery_country') }">
                                            <span class="input-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><path d="M10 16.5s4.5-3.5 4.5-7a4.5 4.5 0 1 0-9 0c0 3.5 4.5 7 4.5 7Z" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="9.5" r="1.5" stroke="currentColor" stroke-width="1.5"/></svg>
                                            </span>
                                            <input :ref="(el) => registerFieldRef('delivery_country', el)" v-model="form.delivery_country" type="text" :placeholder="copy.placeholders.delivery_country" @input="clearFieldErrorIfValid('delivery_country')">
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('delivery_country') }}</span>
                                    </label>

                                    <label class="field">
                                        <span v-html="formatRequiredLabel(`${copy.deliveryPort} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.delivery_port }}</span>
                                        <div class="input-shell" :class="{ invalid: hasVisualInvalid('delivery_port') }">
                                            <span class="input-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><path d="M3.75 11.5h12.5M5 14.5h10M6.5 8.5h7l1 3H5.5l1-3Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </span>
                                            <input :ref="(el) => registerFieldRef('delivery_port', el)" v-model="form.delivery_port" type="text" :placeholder="copy.placeholders.delivery_port" @input="clearFieldErrorIfValid('delivery_port')">
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('delivery_port') }}</span>
                                    </label>

                                    <label class="field">
                                        <span v-html="formatRequiredLabel(`${copy.requiredDeliveryDate} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.delivery_required_date }}</span>
                                        <div class="input-shell" :class="{ invalid: hasVisualInvalid('delivery_required_date') }">
                                            <span class="input-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><rect x="3.75" y="4.75" width="12.5" height="11.5" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M6.5 3.75v3M13.5 3.75v3M3.75 8.25h12.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            </span>
                                            <input :ref="(el) => registerFieldRef('delivery_required_date', el)" v-model="form.delivery_required_date" type="date" @input="clearFieldErrorIfValid('delivery_required_date')" @change="clearFieldErrorIfValid('delivery_required_date')">
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('delivery_required_date') }}</span>
                                    </label>

                                    <label class="field form-field-wide">
                                        <span v-html="formatRequiredLabel(`${copy.deliveryAddress} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.delivery_address }}</span>
                                        <div class="input-shell input-shell-textarea" :class="{ invalid: hasVisualInvalid('delivery_address') }">
                                            <span class="input-icon input-icon-textarea" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><path d="M10 16.5s4.5-3.5 4.5-7a4.5 4.5 0 1 0-9 0c0 3.5 4.5 7 4.5 7Z" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="9.5" r="1.5" stroke="currentColor" stroke-width="1.5"/></svg>
                                            </span>
                                            <textarea :ref="(el) => registerFieldRef('delivery_address', el)" v-model="form.delivery_address" rows="4" :placeholder="copy.placeholders.delivery_address" @input="clearFieldErrorIfValid('delivery_address')"></textarea>
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('delivery_address') }}</span>
                                    </label>

                                    <label class="field">
                                        <span v-html="formatRequiredLabel(`${copy.receiverName} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.delivery_contact_name }}</span>
                                        <div class="input-shell" :class="{ invalid: hasVisualInvalid('delivery_contact_name') }">
                                            <span class="input-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><circle cx="10" cy="6.5" r="2.5" stroke="currentColor" stroke-width="1.5"/><path d="M5.5 16a4.5 4.5 0 0 1 9 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            </span>
                                            <input :ref="(el) => registerFieldRef('delivery_contact_name', el)" v-model="form.delivery_contact_name" type="text" :placeholder="copy.placeholders.delivery_contact_name" @input="clearFieldErrorIfValid('delivery_contact_name')">
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('delivery_contact_name') }}</span>
                                    </label>

                                    <label class="field">
                                        <span v-html="formatRequiredLabel(`${copy.receiverEmail} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.delivery_contact_email }}</span>
                                        <div class="input-shell" :class="{ invalid: hasVisualInvalid('delivery_contact_email') }">
                                            <span class="input-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><rect x="3.75" y="5" width="12.5" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="m5.5 7 4.5 3.75L14.5 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </span>
                                            <input :ref="(el) => registerFieldRef('delivery_contact_email', el)" v-model="form.delivery_contact_email" type="email" :placeholder="copy.placeholders.delivery_contact_email" @input="clearFieldErrorIfValid('delivery_contact_email')">
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('delivery_contact_email') }}</span>
                                    </label>

                                    <label class="field">
                                        <span v-html="formatRequiredLabel(`${copy.receiverPhone} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.delivery_contact_phone }}</span>
                                        <div class="input-shell" :class="{ invalid: hasVisualInvalid('delivery_contact_phone') }">
                                            <span class="input-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><path d="M6.75 4.75h1.5a1 1 0 0 1 1 .8l.4 2a1 1 0 0 1-.29.9L8.3 9.51a10 10 0 0 0 2.2 2.2l1.06-1.06a1 1 0 0 1 .9-.29l2 .4a1 1 0 0 1 .8 1v1.5a1 1 0 0 1-.9 1A9.5 9.5 0 0 1 5.75 5.65a1 1 0 0 1 1-.9Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                                            </span>
                                            <input :ref="(el) => registerFieldRef('delivery_contact_phone', el)" v-model="form.delivery_contact_phone" type="text" :placeholder="copy.placeholders.delivery_contact_phone" @input="clearFieldErrorIfValid('delivery_contact_phone')">
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('delivery_contact_phone') }}</span>
                                    </label>
                                </div>

                                <div v-else class="form-grid">
                                    <label class="field">
                                        <span v-html="formatRequiredLabel(`${copy.serviceLocationType} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.service_location_type }}</span>
                                        <div class="input-shell input-shell-select" :class="{ invalid: hasVisualInvalid('service_location_type') }">
                                            <span class="input-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><path d="M5 5.5h10M5 10h10M5 14.5h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            </span>
                                            <select :ref="(el) => registerFieldRef('service_location_type', el)" v-model="form.service_location_type" class="field-select" @change="clearFieldErrorIfValid('service_location_type')">
                                                <option value="">Select service location type</option>
                                                <option v-for="(label, value) in copy.options.service_location_type" :key="value" :value="value">{{ label }}</option>
                                            </select>
                                            <span class="select-caret" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </span>
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('service_location_type') }}</span>
                                    </label>

                                    <label class="field form-field-wide">
                                        <span v-html="formatRequiredLabel(`${copy.serviceLocation} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.service_location }}</span>
                                        <div class="input-shell" :class="{ invalid: hasVisualInvalid('service_location') }">
                                            <span class="input-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><path d="M10 16.5s4.5-3.5 4.5-7a4.5 4.5 0 1 0-9 0c0 3.5 4.5 7 4.5 7Z" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="9.5" r="1.5" stroke="currentColor" stroke-width="1.5"/></svg>
                                            </span>
                                            <input :ref="(el) => registerFieldRef('service_location', el)" v-model="form.service_location" type="text" :placeholder="copy.placeholders.service_location" @input="clearFieldErrorIfValid('service_location')">
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('service_location') }}</span>
                                    </label>

                                    <label class="field">
                                        <span v-html="formatRequiredLabel(`${copy.serviceRequiredDate} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.service_required_date }}</span>
                                        <div class="input-shell" :class="{ invalid: hasVisualInvalid('service_required_date') }">
                                            <span class="input-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><rect x="3.75" y="4.75" width="12.5" height="11.5" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M6.5 3.75v3M13.5 3.75v3M3.75 8.25h12.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            </span>
                                            <input :ref="(el) => registerFieldRef('service_required_date', el)" v-model="form.service_required_date" type="date" @input="clearFieldErrorIfValid('service_required_date')" @change="clearFieldErrorIfValid('service_required_date')">
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('service_required_date') }}</span>
                                    </label>

                                    <label class="field">
                                        <span v-html="formatRequiredLabel(`${copy.serviceContactName} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.service_contact_name }}</span>
                                        <div class="input-shell" :class="{ invalid: hasVisualInvalid('service_contact_name') }">
                                            <span class="input-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><circle cx="10" cy="6.5" r="2.5" stroke="currentColor" stroke-width="1.5"/><path d="M5.5 16a4.5 4.5 0 0 1 9 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            </span>
                                            <input :ref="(el) => registerFieldRef('service_contact_name', el)" v-model="form.service_contact_name" type="text" :placeholder="copy.placeholders.service_contact_name" @input="clearFieldErrorIfValid('service_contact_name')">
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('service_contact_name') }}</span>
                                    </label>

                                    <label class="field">
                                        <span v-html="formatRequiredLabel(`${copy.serviceContactEmail} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.service_contact_email }}</span>
                                        <div class="input-shell" :class="{ invalid: hasVisualInvalid('service_contact_email') }">
                                            <span class="input-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><rect x="3.75" y="5" width="12.5" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="m5.5 7 4.5 3.75L14.5 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </span>
                                            <input :ref="(el) => registerFieldRef('service_contact_email', el)" v-model="form.service_contact_email" type="email" :placeholder="copy.placeholders.service_contact_email" @input="clearFieldErrorIfValid('service_contact_email')">
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('service_contact_email') }}</span>
                                    </label>

                                    <label class="field">
                                        <span v-html="formatRequiredLabel(`${copy.serviceContactPhone} *`)"></span>
                                        <span class="field-hint">{{ copy.hints.service_contact_phone }}</span>
                                        <div class="input-shell" :class="{ invalid: hasVisualInvalid('service_contact_phone') }">
                                            <span class="input-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><path d="M6.75 4.75h1.5a1 1 0 0 1 1 .8l.4 2a1 1 0 0 1-.29.9L8.3 9.51a10 10 0 0 0 2.2 2.2l1.06-1.06a1 1 0 0 1 .9-.29l2 .4a1 1 0 0 1 .8 1v1.5a1 1 0 0 1-.9 1A9.5 9.5 0 0 1 5.75 5.65a1 1 0 0 1 1-.9Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                                            </span>
                                            <input :ref="(el) => registerFieldRef('service_contact_phone', el)" v-model="form.service_contact_phone" type="text" :placeholder="copy.placeholders.service_contact_phone" @input="clearFieldErrorIfValid('service_contact_phone')">
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('service_contact_phone') }}</span>
                                    </label>

                                    <label class="field form-field-wide">
                                        <span>{{ copy.serviceNotes }}</span>
                                        <span class="field-hint">{{ copy.hints.service_instruction_notes }}</span>
                                        <div class="input-shell input-shell-textarea" :class="{ invalid: hasVisualInvalid('service_instruction_notes') }">
                                            <span class="input-icon input-icon-textarea" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none"><path d="M5 4.75h10A1.25 1.25 0 0 1 16.25 6v8A1.25 1.25 0 0 1 15 15.25H5A1.25 1.25 0 0 1 3.75 14V6A1.25 1.25 0 0 1 5 4.75Z" stroke="currentColor" stroke-width="1.5"/><path d="M6.75 8h6.5M6.75 10.75h6.5M6.75 13.5h4.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            </span>
                                            <textarea :ref="(el) => registerFieldRef('service_instruction_notes', el)" v-model="form.service_instruction_notes" rows="4" :placeholder="copy.placeholders.service_instruction_notes" @input="clearFieldErrorIfValid('service_instruction_notes')"></textarea>
                                        </div>
                                        <span class="field-feedback">{{ visibleFieldError('service_instruction_notes') }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="form-actions">
                        <button type="button" class="secondary-action" :disabled="form.processing" @click="closeModal">
                            {{ copy.cancel }}
                        </button>
                        <button type="submit" class="primary-action" :disabled="form.processing || !canEdit">
                            {{ currentOrder.order_workflow_status === 'order_information_pending' ? copy.save : copy.update }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.detail-modal-backdrop{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(15,23,42,.55);backdrop-filter:blur(10px);z-index:2200}
.detail-modal{width:min(960px,calc(100vw - 32px));max-height:min(90vh,980px);display:grid;grid-template-rows:auto minmax(0,1fr);background:#fff;border:1px solid rgba(148,163,184,.35);border-radius:24px;box-shadow:0 32px 80px rgba(15,23,42,.24);overflow:hidden}
.detail-modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:24px 28px 18px;border-bottom:1px solid rgba(226,232,240,.9)}
.detail-modal-copy{display:grid;gap:8px}
.detail-modal-title{margin:0;color:#0f172a;font-size:1.1rem;font-weight:700}
.detail-modal-intro{margin:0;color:#64748b;font-size:.92rem;line-height:1.7;max-width:66ch}
.detail-modal-close{border:0;background:transparent;color:#2563eb;font-size:.88rem;font-weight:700;cursor:pointer}
.detail-modal-body{padding:22px 28px 28px;overflow:auto}
.modal-state-card{display:grid;gap:12px;padding:24px 26px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.94);box-shadow:0 20px 42px rgba(15,23,42,.06)}
.modal-state-card strong{color:#0f172a;font-size:1rem;font-weight:700}
.modal-state-card p{margin:0;color:#64748b;font-size:.92rem;line-height:1.7}
.modal-state-card-error{border-color:rgba(217,45,32,.18)}
.verification-card,.section-form,.section-form-narrow{display:grid;gap:16px}
.section-card{display:grid;gap:18px;padding:24px 26px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.94);box-shadow:0 20px 42px rgba(15,23,42,.06)}
.section-head{display:grid;gap:8px}
.section-card-eyebrow{margin:0}
.identity-surface{padding:24px;border-radius:10px;background:#f8fafb;min-width:0}
.helper-copy{margin:0;color:#64748b;font-size:.92rem;line-height:1.6}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px 18px}
.field{display:grid;gap:8px}
.form-field-wide{grid-column:span 2}
.field-hint{color:#64748b;font-size:.88rem;line-height:1.55}
.field>span:not(.field-hint):not(.field-feedback){display:block;color:#0f172a;font-size:.95rem;font-weight:620;line-height:1.45}
.field>span:not(.field-hint):not(.field-feedback)>span{font:inherit;line-height:inherit}
.required-star{color:#d92d20}
.input-shell{display:flex;align-items:center;min-height:52px;overflow:hidden;border:1px solid rgba(4,21,31,.12);border-radius:10px;background:#fff}
.input-shell.invalid{border-color:rgba(217,45,32,.5);box-shadow:0 0 0 3px rgba(217,45,32,.08)}
.input-icon{display:inline-flex;align-items:center;justify-content:center;width:46px;color:#365cff;flex:0 0 46px}
.input-icon svg{width:18px;height:18px}
.input-shell input,.input-shell select,.input-shell textarea{width:100%;border:0;outline:0;background:transparent;color:#0f172a;font:inherit}
.input-shell input,.input-shell select{min-height:50px;padding:0 16px 0 0}
.input-shell input::placeholder,.input-shell textarea::placeholder{color:#94a3b8}
.input-shell textarea{padding:14px 16px 14px 0;resize:vertical;min-height:112px}
.input-shell-select{position:relative}
.field-select{appearance:none;padding-left:8px !important;padding-right:42px !important;cursor:pointer}
.select-caret{position:absolute;right:14px;top:50%;transform:translateY(-50%);display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;color:#64748b;pointer-events:none}
.select-caret svg{width:18px;height:18px}
.input-shell-textarea{align-items:stretch}
.input-icon-textarea{align-items:flex-start;padding-top:15px}
.field-feedback{min-height:20px;color:#d92d20;font-size:.82rem;line-height:1.45}
.form-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px}
.primary-action,.secondary-action{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 18px;border-radius:10px;font-size:.9rem;font-weight:700}
.primary-action{border:0;background:#2563eb;color:#fff;box-shadow:0 12px 24px rgba(37,99,235,.2)}
.secondary-action{border:1px solid #cbd5e1;background:#fff;color:#0f172a}
.primary-action:disabled,.secondary-action:disabled{opacity:.6;cursor:not-allowed}
@media (max-width: 960px){
    .form-grid{grid-template-columns:1fr}
    .form-field-wide{grid-column:span 1}
}
@media (max-width: 720px){
    .detail-modal{width:min(100vw - 20px,960px);max-height:min(92vh,980px)}
    .detail-modal-head{padding:20px 20px 16px;flex-direction:column}
    .detail-modal-body{padding:18px 20px 20px}
    .identity-surface,.section-card{padding:20px}
    .form-actions{justify-content:stretch;flex-direction:column-reverse}
    .primary-action,.secondary-action{width:100%}
}
</style>
