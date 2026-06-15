<script setup>
import { computed } from 'vue';
import RfqGeneralInformationSection from './RfqGeneralInformationSection.vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    viewer: {
        type: String,
        default: 'buyer',
    },
});

const copy = {
    billing: 'Billing Information',
    invoiceCompany: 'Invoice Company Name',
    invoiceAddress: 'Invoice Address',
    taxId: 'Tax / VAT / Company ID',
    billingContactName: 'Billing Contact Name',
    billingContactEmail: 'Billing Contact Email',
    billingContactPhone: 'Billing Contact Phone',
    deliveryInstructions: 'Delivery Instructions',
    deliveryType: 'Delivery Type',
    deliveryCountry: 'Delivery Country',
    deliveryPort: 'Delivery Port',
    deliveryAddress: 'Delivery Address',
    receiverName: 'Receiver Name',
    receiverEmail: 'Receiver Email',
    receiverPhone: 'Receiver Phone',
    requiredDeliveryDate: 'Required Delivery Date',
    serviceInstructions: 'Service Instructions',
    serviceLocationType: 'Service Location Type',
    serviceLocation: 'Attendance Location',
    serviceContactName: 'Contact Name',
    serviceContactEmail: 'Contact Email',
    serviceContactPhone: 'Contact Phone',
    serviceRequiredDate: 'Preferred Attendance Date',
    serviceNotes: 'Access / Technical Notes',
    billingPendingTitle: 'Billing information pending',
    buyerBillingPendingText: 'Add the invoice company and billing contact details so this order can move to the invoice stage.',
    supplierBillingPendingText: 'The buyer has not shared invoice company and billing contact details yet.',
    deliveryPendingTitle: 'Delivery instructions pending',
    buyerDeliveryPendingText: 'Add the delivery location and receiver details so the supplier can continue with this spare parts order.',
    supplierDeliveryPendingText: 'The buyer has not shared delivery location and receiver details yet.',
    servicePendingTitle: 'Service instructions pending',
    buyerServicePendingText: 'Add the attendance location and service contact details so the supplier can continue with this service order.',
    supplierServicePendingText: 'The buyer has not shared attendance location and service contact details yet.',
    noData: '-',
};

const textOrDash = (value) => {
    const text = `${value ?? ''}`.trim();
    return text || copy.noData;
};

const hasText = (value) => `${value ?? ''}`.trim() !== '';

const formatDate = (value) => {
    if (!value) return copy.noData;

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
};

const formatTitleCaseValue = (value) => {
    const normalized = `${value ?? ''}`.trim();
    if (!normalized) return copy.noData;

    return normalized
        .split('_')
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
};

const isBuyerView = computed(() => props.viewer === 'buyer');
const isAdminView = computed(() => props.viewer === 'admin');
const isSpareParts = computed(() => props.order.request_type === 'spare_parts');

const billingInformationFields = computed(() => [
    { key: 'billing_company_name', label: copy.invoiceCompany, value: textOrDash(props.order.billing_company_name) },
    { key: 'billing_contact_name', label: copy.billingContactName, value: textOrDash(props.order.billing_contact_name) },
    { key: 'billing_contact_email', label: copy.billingContactEmail, value: textOrDash(props.order.billing_contact_email) },
    { key: 'billing_contact_phone', label: copy.billingContactPhone, value: textOrDash(props.order.billing_contact_phone) },
    { key: 'billing_tax_id', label: copy.taxId, value: textOrDash(props.order.billing_tax_id) },
    { key: 'billing_address', label: copy.invoiceAddress, value: textOrDash(props.order.billing_address), wide: true, long: true },
]);

const instructionFields = computed(() => {
    if (isSpareParts.value) {
        return [
            { key: 'delivery_target_type', label: copy.deliveryType, value: formatTitleCaseValue(props.order.delivery_target_type) },
            { key: 'delivery_country', label: copy.deliveryCountry, value: textOrDash(props.order.delivery_country) },
            { key: 'delivery_port', label: copy.deliveryPort, value: textOrDash(props.order.delivery_port) },
            { key: 'delivery_required_date', label: copy.requiredDeliveryDate, value: formatDate(props.order.delivery_required_date) },
            { key: 'delivery_contact_name', label: copy.receiverName, value: textOrDash(props.order.delivery_contact_name) },
            { key: 'delivery_contact_email', label: copy.receiverEmail, value: textOrDash(props.order.delivery_contact_email) },
            { key: 'delivery_contact_phone', label: copy.receiverPhone, value: textOrDash(props.order.delivery_contact_phone) },
            { key: 'delivery_address', label: copy.deliveryAddress, value: textOrDash(props.order.delivery_address), wide: true, long: true },
        ];
    }

    return [
        { key: 'service_location_type', label: copy.serviceLocationType, value: formatTitleCaseValue(props.order.service_location_type) },
        { key: 'service_location', label: copy.serviceLocation, value: textOrDash(props.order.service_location), wide: true },
        { key: 'service_required_date', label: copy.serviceRequiredDate, value: formatDate(props.order.service_required_date) },
        { key: 'service_contact_name', label: copy.serviceContactName, value: textOrDash(props.order.service_contact_name) },
        { key: 'service_contact_email', label: copy.serviceContactEmail, value: textOrDash(props.order.service_contact_email) },
        { key: 'service_contact_phone', label: copy.serviceContactPhone, value: textOrDash(props.order.service_contact_phone) },
        { key: 'service_instruction_notes', label: copy.serviceNotes, value: textOrDash(props.order.service_instruction_notes), wide: true, long: true },
    ];
});

const hasBillingInformation = computed(() => [
    props.order.billing_company_name,
    props.order.billing_address,
    props.order.billing_contact_name,
    props.order.billing_contact_email,
    props.order.billing_contact_phone,
].some(hasText));

const hasInstructionInformation = computed(() => {
    if (isSpareParts.value) {
        return [
            props.order.delivery_target_type,
            props.order.delivery_country,
            props.order.delivery_port,
            props.order.delivery_address,
            props.order.delivery_contact_name,
            props.order.delivery_contact_email,
            props.order.delivery_contact_phone,
            props.order.delivery_required_date,
        ].some(hasText);
    }

    return [
        props.order.service_location_type,
        props.order.service_location,
        props.order.service_contact_name,
        props.order.service_contact_email,
        props.order.service_contact_phone,
        props.order.service_required_date,
        props.order.service_instruction_notes,
    ].some(hasText);
});

const billingPendingText = computed(() => (
    isBuyerView.value
        ? copy.buyerBillingPendingText
        : (
            isAdminView.value
                ? 'Buyer has not completed billing details yet.'
                : copy.supplierBillingPendingText
        )
));

const instructionPendingTitle = computed(() => (
    isSpareParts.value
        ? copy.deliveryPendingTitle
        : copy.servicePendingTitle
));

const instructionPendingText = computed(() => {
    if (isSpareParts.value) {
        return isBuyerView.value
            ? copy.buyerDeliveryPendingText
            : (
                isAdminView.value
                    ? 'Buyer has not completed delivery instructions yet.'
                    : copy.supplierDeliveryPendingText
            );
    }

    return isBuyerView.value
        ? copy.buyerServicePendingText
        : (
            isAdminView.value
                ? 'Buyer has not completed service instructions yet.'
                : copy.supplierServicePendingText
        );
});
</script>

<template>
    <div class="order-info-grid">
        <RfqGeneralInformationSection
            :title="copy.billing"
            :fields="billingInformationFields"
            :columns="1"
            :label-width="150"
            :wrap-labels="true"
            :small-text="true"
            :empty="!hasBillingInformation"
            :empty-title="copy.billingPendingTitle"
            :empty-text="billingPendingText"
        />
        <RfqGeneralInformationSection
            :title="isSpareParts ? copy.deliveryInstructions : copy.serviceInstructions"
            :fields="instructionFields"
            :columns="1"
            :label-width="150"
            :wrap-labels="true"
            :small-text="true"
            :empty="!hasInstructionInformation"
            :empty-title="instructionPendingTitle"
            :empty-text="instructionPendingText"
        />
    </div>
</template>

<style scoped>
.order-info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
@media (max-width: 960px){
    .order-info-grid{grid-template-columns:1fr}
}
</style>
