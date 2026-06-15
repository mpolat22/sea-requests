<script setup>
import { computed } from 'vue';

const props = defineProps({
    offer: {
        type: Object,
        required: true,
    },
    requestType: {
        type: String,
        required: true,
    },
    awardScopeLabel: {
        type: String,
        default: '',
    },
    summaryAmount: {
        type: [Number, String],
        default: null,
    },
});

const copy = {
    totalPrice: 'Total Price',
    tax: 'Including Tax',
    packing: 'Including Packing',
    freight: 'Including Freight',
    mobilization: 'Including Mobilization',
    grandTotal: 'Grand Total',
    deliveryTerms: 'Delivery Terms',
    otherDeliveryTerms: 'Other Delivery Terms',
    awardScope: 'Award Scope',
    paymentTerms: 'Payment Terms',
    otherPaymentTerms: 'Other Payment Terms',
    generalNote: 'General Note',
    completionTime: 'Completion Time',
    offerValidity: 'Offer Validity',
    serviceClarification: 'Service Clarification',
    included: 'Included',
    noData: '-',
};

const isSpareParts = computed(() => props.requestType === 'spare_parts');

const toNumber = (value) => {
    const numeric = Number(value ?? 0);
    return Number.isFinite(numeric) ? numeric : 0;
};

const textOrDash = (value) => {
    const text = `${value ?? ''}`.trim();
    return text || copy.noData;
};

const formatMoney = (value, currency = 'USD') => {
    const numeric = Number(value ?? 0);

    if (!Number.isFinite(numeric)) {
        return `${currency} ${value ?? '0'}`;
    }

    return `${currency} ${new Intl.NumberFormat('en-GB', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(numeric)}`;
};

const baseTotalAmount = computed(() => (
    props.summaryAmount !== null
        ? toNumber(props.summaryAmount)
        : (
            isSpareParts.value
                ? toNumber(props.offer.selected_total)
                : toNumber(props.offer.total_offer_amount)
        )
));

const visibleTaxAmount = computed(() => (
    props.offer.including_tax ? 0 : toNumber(props.offer.tax_amount)
));

const visiblePackingAmount = computed(() => (
    props.offer.including_packing ? 0 : toNumber(props.offer.packing_cost)
));

const visibleFreightAmount = computed(() => (
    props.offer.including_freight ? 0 : toNumber(props.offer.freight_cost)
));

const visibleMobilizationAmount = computed(() => (
    props.offer.including_mobilization ? 0 : toNumber(props.offer.mobilization_cost)
));

const grandTotalAmount = computed(() => (
    isSpareParts.value
        ? baseTotalAmount.value + visibleTaxAmount.value + visiblePackingAmount.value + visibleFreightAmount.value
        : baseTotalAmount.value + visibleTaxAmount.value + visibleMobilizationAmount.value
));

const paymentTermsSummary = computed(() => {
    const text = `${props.offer.payment_terms_summary ?? ''}`.trim();

    if (text && text !== copy.noData) {
        const parts = text
            .split(' / ')
            .map((part) => part.trim())
            .filter((part) => part !== '' && !part.startsWith('Other:'));

        if (parts.length) {
            return parts.join(' / ');
        }
    }

    const parts = [];

    if (toNumber(props.offer.payment_order_confirmation) > 0) {
        parts.push(`${toNumber(props.offer.payment_order_confirmation)}% when order confirmation`);
    }

    if (toNumber(props.offer.payment_before_shipment) > 0) {
        parts.push(`${toNumber(props.offer.payment_before_shipment)}% before shipment`);
    }

    if (toNumber(props.offer.payment_invoice_days) > 0) {
        parts.push(`${toNumber(props.offer.payment_invoice_days)} days from Invoice Date`);
    }

    return parts.length ? parts.join(' / ') : copy.noData;
});

const pricingRows = computed(() => {
    const currency = props.offer.currency || 'USD';

    if (isSpareParts.value) {
        return [
            { key: 'total', label: copy.totalPrice, value: formatMoney(baseTotalAmount.value, currency) },
            { key: 'tax', label: copy.tax, value: props.offer.including_tax ? copy.included : formatMoney(visibleTaxAmount.value, currency), muted: true },
            { key: 'packing', label: copy.packing, value: props.offer.including_packing ? copy.included : formatMoney(visiblePackingAmount.value, currency), muted: true },
            { key: 'freight', label: copy.freight, value: props.offer.including_freight ? copy.included : formatMoney(visibleFreightAmount.value, currency), muted: true },
            { key: 'grand', label: copy.grandTotal, value: formatMoney(grandTotalAmount.value, currency), grand: true },
        ];
    }

    return [
        { key: 'total', label: copy.totalPrice, value: formatMoney(baseTotalAmount.value, currency) },
        { key: 'tax', label: copy.tax, value: props.offer.including_tax ? copy.included : formatMoney(visibleTaxAmount.value, currency), muted: true },
        { key: 'mobilization', label: copy.mobilization, value: props.offer.including_mobilization ? copy.included : formatMoney(visibleMobilizationAmount.value, currency), muted: true },
        { key: 'grand', label: copy.grandTotal, value: formatMoney(grandTotalAmount.value, currency), grand: true },
    ];
});

const noteRows = computed(() => {
    if (isSpareParts.value) {
        return [
            { key: 'delivery_terms', label: copy.deliveryTerms, value: textOrDash(props.offer.delivery_terms) },
            { key: 'other_delivery_terms', label: copy.otherDeliveryTerms, value: textOrDash(props.offer.other_delivery_terms) },
            props.awardScopeLabel
                ? { key: 'award_scope', label: copy.awardScope, value: props.awardScopeLabel }
                : null,
            { key: 'payment_terms', label: copy.paymentTerms, value: paymentTermsSummary.value },
            { key: 'other_payment_terms', label: copy.otherPaymentTerms, value: textOrDash(props.offer.other_payment_terms) },
            { key: 'general_note', label: copy.generalNote, value: textOrDash(props.offer.general_note) },
        ].filter(Boolean);
    }

    return [
        { key: 'completion_time', label: copy.completionTime, value: textOrDash(props.offer.completion_time) },
        { key: 'offer_validity', label: copy.offerValidity, value: textOrDash(props.offer.offer_validity) },
        { key: 'delivery_terms', label: copy.deliveryTerms, value: textOrDash(props.offer.delivery_terms) },
        { key: 'other_delivery_terms', label: copy.otherDeliveryTerms, value: textOrDash(props.offer.other_delivery_terms) },
        { key: 'payment_terms', label: copy.paymentTerms, value: paymentTermsSummary.value },
        { key: 'other_payment_terms', label: copy.otherPaymentTerms, value: textOrDash(props.offer.other_payment_terms) },
        { key: 'service_clarification', label: copy.serviceClarification, value: textOrDash(props.offer.service_clarification) },
        { key: 'general_note', label: copy.generalNote, value: textOrDash(props.offer.general_note) },
    ];
});
</script>

<template>
    <div class="commercial-summary">
        <div class="offer-summary-rows">
            <div
                v-for="row in pricingRows"
                :key="row.key"
                class="offer-summary-row"
                :class="{
                    'offer-summary-row-muted': row.muted,
                    'offer-summary-row-grand': row.grand,
                }"
            >
                <span class="offer-summary-label">{{ row.label }}</span>
                <span class="offer-summary-value">{{ row.value }}</span>
            </div>
        </div>

        <div class="service-offer-notes">
            <div v-for="note in noteRows" :key="note.key" class="service-offer-note">
                <div class="detail-inline-main detail-inline-main-wide">
                    <strong class="detail-inline-label">{{ note.label }}:</strong>
                    <div class="detail-inline-text detail-inline-text-long">{{ note.value }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.commercial-summary{display:grid;gap:18px}
.offer-summary-rows{display:grid;gap:8px;padding:0;border:0;border-radius:0;background:transparent}
.offer-summary-row{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
.offer-summary-label{color:#04151f;font-size:12px;font-weight:700;line-height:1.2;white-space:normal}
.offer-summary-value{color:rgba(4,21,31,.82);font-size:13px;font-weight:400;line-height:1.45;text-align:right}
.offer-summary-row-muted .offer-summary-value{color:#475569}
.offer-summary-row-grand{padding-top:10px;border-top:1px solid rgba(148,163,184,.18)}
.offer-summary-row-grand .offer-summary-value{font-weight:700}
.service-offer-notes{display:grid;grid-template-columns:1fr;gap:10px}
.service-offer-note{display:block;min-width:0}
.detail-inline-main{display:grid;grid-template-columns:150px minmax(0,1fr);align-items:start;column-gap:10px}
.detail-inline-main-wide{grid-template-columns:150px minmax(0,1fr)}
.detail-inline-label{color:#04151f;font-size:12px;font-weight:700;line-height:1.2;white-space:normal}
.detail-inline-text{color:rgba(4,21,31,.82);font-size:13px;font-weight:400;display:block;min-width:0;line-height:1.45;white-space:normal;overflow:visible;text-overflow:clip;word-break:break-word}
.detail-inline-text-long{line-height:1.45}
@media (max-width: 720px){
    .detail-inline-main,.detail-inline-main-wide{grid-template-columns:1fr;row-gap:6px}
}
</style>
