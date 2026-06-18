<script setup>
import { computed, defineAsyncComponent, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import SupplierDashboardShell from './Shell.vue';
import OrderSelectedItemsSection from '../../../Components/OrderSelectedItemsSection.vue';
import OrderInformationPanels from '../../../Components/OrderInformationPanels.vue';
import OfferCommercialSummary from '../../../Components/OfferCommercialSummary.vue';
import RfqGeneralInformationSection from '../../../Components/RfqGeneralInformationSection.vue';
import OrderInvoicesSection from '../../../Components/OrderInvoicesSection.vue';

const InvoiceUploadModal = defineAsyncComponent(() => import('./InvoiceUploadModal.vue'));

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
    order: {
        type: Object,
        required: true,
    },
});

const award = props.order;

const copy = {
    title: 'Order Detail',
    eyebrow: 'Order Confirmed',
    intro: 'This screen is designed to show the order confirmed to your company clearly. Review the selected lines, accepted commercial terms, and the next invoice workflow from here.',
    back: 'Back to Orders',
    viewRequest: 'View Supplier RFQ',
    requestType: {
        spare_parts: 'Spare Parts',
        service_request: 'Service Request',
    },
    general: 'General Information',
    referenceNo: 'Reference No',
    buyerCompany: 'Buyer Company',
    ship: 'Ship',
    country: 'Country',
    ports: 'Ports',
    requisitionDate: 'Requisition Date',
    dueDate: 'Due Date',
    currency: 'Currency',
    priority: 'Priority',
    orderStatus: 'Order Status',
    confirmedAt: 'Award Confirmed',
    generalNotes: 'General Notes',
    selectedItems: 'Selected Items',
    selectedService: 'Selected Service',
    acceptedTerms: 'Accepted Commercial Terms',
    invoices: 'Invoices & Payments',
    invoicesIntro: 'Review every invoice opened for this order, check buyer payment proof, and confirm payment receipt from the Orders table action when needed.',
    manageInvoices: 'Manage Invoices',
    partialAwardAccepted: 'Partial award accepted',
    fullQuotedScopeRequired: 'Full quoted scope required',
    close: 'Close',
    selectedCountries: 'Selected Countries',
    selectedPorts: 'Selected Ports',
    countriesSelected: 'countries selected',
    portsSelected: 'ports selected',
    portsSelectedSuffix: 'ports selected',
    allListedPortsIn: 'All listed ports in',
    noData: '-',
};

const orderWorkflowLabel = computed(() => award.order_workflow_status_label || 'Order Information Pending');
const isInvoiceUploadModalOpen = ref(false);

const detailModal = ref(null);

const textOrDash = (value) => {
    const text = `${value ?? ''}`.trim();
    return text || copy.noData;
};

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

const awardScopeLabel = (value) => {
    if (value === 'full_scope_required') {
        return copy.fullQuotedScopeRequired;
    }

    return copy.partialAwardAccepted;
};

const portGroups = computed(() => (award.ports_by_country ?? [])
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

    return (award.country_names ?? []).filter(Boolean);
});

const selectedCountryCount = computed(() => selectedCountries.value.length);
const selectedPortCount = computed(() => portGroups.value
    .reduce((total, entry) => total + (entry.ports?.length ?? 0), 0));
const portSelectionThreshold = 10;
const hasInvoiceManagement = computed(() => (
    Boolean(award.can_manage_invoices)
    || (Array.isArray(award.invoices) && award.invoices.length > 0)
));

const openDetailModal = (type) => {
    detailModal.value = type;
};

const openInvoiceUploadModal = () => {
    isInvoiceUploadModalOpen.value = true;
};

const closeInvoiceUploadModal = () => {
    isInvoiceUploadModalOpen.value = false;
};

const closeDetailModal = () => {
    detailModal.value = null;
};

const detailModalTitle = computed(() => (
    detailModal.value === 'countries'
        ? copy.selectedCountries
        : copy.selectedPorts
));

const portGroupSummary = (group) => {
    const selectedCount = group?.ports?.length ?? 0;
    const totalCount = Number(award.port_totals_by_country?.[group.country] ?? 0);

    if (totalCount > 0 && selectedCount === totalCount) {
        return `${copy.allListedPortsIn} ${group.country}`;
    }

    if (selectedCount > portSelectionThreshold) {
        return `${selectedCount} ${copy.portsSelectedSuffix}`;
    }

    return null;
};

const generalInformationFields = computed(() => [
    {
        key: 'reference_no',
        label: copy.referenceNo,
        value: textOrDash(award.reference_no),
    },
    {
        key: 'buyer_company',
        label: copy.buyerCompany,
        value: textOrDash(award.company_name),
    },
    {
        key: 'ship',
        label: copy.ship,
        value: textOrDash(award.ship_name),
    },
    {
        key: 'status',
        label: copy.orderStatus,
        value: orderWorkflowLabel.value,
    },
    {
        key: 'country',
        label: copy.country,
        value: `${selectedCountryCount.value} ${copy.countriesSelected}`,
        clickable: true,
        action: 'countries',
    },
    {
        key: 'ports',
        label: copy.ports,
        value: `${selectedPortCount.value} ${copy.portsSelected}`,
        clickable: true,
        action: 'ports',
        long: true,
    },
    {
        key: 'requisition_date',
        label: copy.requisitionDate,
        value: formatDate(award.requisition_date),
    },
    {
        key: 'due_date',
        label: copy.dueDate,
        value: formatDate(award.due_date),
    },
    {
        key: 'currency',
        label: copy.currency,
        value: textOrDash(award.currency),
    },
    {
        key: 'confirmed_at',
        label: copy.confirmedAt,
        value: formatDate(award.confirmed_at),
    },
    {
        key: 'general_notes',
        label: copy.generalNotes,
        value: textOrDash(award.general_notes),
        long: true,
    },
    {
        key: 'priority',
        label: copy.priority,
        value: formatTitleCaseValue(award.priority),
    },
]);

</script>

<template>
    <SupplierDashboardShell :dashboard="dashboard" :title="copy.title" active-tab="orders" :show-tabs="false" :show-intro="false">
        <section class="surface-panel hero-panel">
            <div class="hero-copy">
                <p class="directory-eyebrow">{{ copy.eyebrow }}</p>
                <h1 class="directory-page-title">{{ award.reference_no }}</h1>
                <p class="directory-intro-copy">{{ copy.intro }}</p>

                <div class="hero-pills">
                    <span class="pill request-type-pill">
                        {{ copy.requestType[award.request_type] || award.request_type }}
                    </span>
                    <span class="status-pill">
                        <span class="status-dot is-awarded"></span>
                        {{ orderWorkflowLabel }}
                    </span>
                </div>
            </div>

            <div class="hero-actions">
                <Link :href="dashboard.navigation.orders_url" class="back-button">
                    {{ copy.back }}
                </Link>
                <Link :href="award.show_url" class="back-button back-button-secondary">
                    {{ copy.viewRequest }}
                </Link>
            </div>
        </section>

        <section class="surface-card section-card combined-detail-section">
            <RfqGeneralInformationSection
                :title="copy.general"
                :fields="generalInformationFields"
                @action="openDetailModal"
            />

            <div class="section-divider"></div>

            <OrderSelectedItemsSection
                :order="award"
                :title="award.request_type === 'service_request' ? copy.selectedService : copy.selectedItems"
            />

            <div class="section-divider"></div>

            <div class="subsection-surface">
                <div class="section-heading">
                    <h2 class="directory-section-title">{{ copy.acceptedTerms }}</h2>
                </div>

                <OfferCommercialSummary
                    :offer="award"
                    :request-type="award.request_type"
                    :award-scope-label="award.request_type === 'spare_parts' ? awardScopeLabel(award.award_scope_policy) : ''"
                />
            </div>
        </section>

        <section class="surface-card section-card order-info-section">
            <OrderInformationPanels :order="award" viewer="supplier" />
        </section>

        <section class="surface-card section-card order-invoices-section">
            <div class="section-heading section-heading-with-action">
                <div class="section-heading-copy">
                    <h2 class="directory-section-title">{{ copy.invoices }}</h2>
                    <p class="section-heading-intro">{{ copy.invoicesIntro }}</p>
                </div>

                <button
                    v-if="hasInvoiceManagement"
                    type="button"
                    class="back-button invoice-action-button"
                    @click="openInvoiceUploadModal"
                >
                    {{ copy.manageInvoices }}
                </button>
            </div>

            <OrderInvoicesSection
                :title="copy.invoices"
                :show-heading="false"
                :invoices="award.invoices || []"
                :buyer-label="copy.buyerCompany"
                :buyer-name="award.company_name || ''"
                :supplier-name="dashboard.company_name || ''"
            />
        </section>

        <InvoiceUploadModal
            :is-open="isInvoiceUploadModalOpen"
            :order="award"
            :can-manage="Boolean(award.can_manage_invoices)"
            :create-url="award.create_invoice_url || ''"
            :supplier-company-name="dashboard.company_name || ''"
            return-to="detail"
            @close="closeInvoiceUploadModal"
        />

        <div v-if="detailModal" class="detail-modal-backdrop" @click.self="closeDetailModal">
            <div class="detail-modal">
                <div class="detail-modal-head">
                    <h3 class="detail-modal-title">{{ detailModalTitle }}</h3>
                    <button type="button" class="detail-modal-close" @click="closeDetailModal">
                        {{ copy.close }}
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
    </SupplierDashboardShell>
</template>

<style scoped>
.surface-panel,.surface-card{padding:32px 36px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff;box-shadow:0 24px 44px rgba(15,23,42,.08)}
.section-card{margin-top:10px}
.order-info-section{padding-bottom:20px}
.order-invoices-section{margin-top:6px;padding-top:24px}
.combined-detail-section{display:grid;gap:0;min-width:0}
.subsection-surface{padding:24px;border-radius:10px;background:#f8fafb;min-width:0}
.section-divider{margin:28px 0 0}
.hero-panel{padding:24px 26px;display:flex;align-items:flex-start;justify-content:space-between;gap:24px}
.hero-panel{display:flex;align-items:flex-start;justify-content:space-between;gap:24px}
.hero-copy{display:grid;gap:14px}
.hero-copy :deep(.directory-page-title){margin:0}
.hero-copy :deep(.directory-intro-copy){max-width:74ch}
.hero-pills{display:flex;flex-wrap:wrap;gap:10px}
.hero-actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end}
.back-button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border-radius:10px;background:#0f172a;color:#fff;text-decoration:none;font-size:.88rem;font-weight:600}
.back-button-secondary{background:#fff;border:1px solid #e2e8f0;color:#0f172a}
.status-pill,.pill{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 12px;border-radius:10px;font-size:.82rem;font-weight:600}
.request-type-pill{background:#f8fafc;border:1px solid #e2e8f0;color:#0f172a}
.status-pill{background:#ecfeff;border:1px solid #a5f3fc;color:#0f766e;gap:8px}
.status-dot{width:10px;height:10px;border-radius:999px;display:inline-block}
.status-dot.is-awarded{background:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,.16)}
.section-heading{margin-bottom:16px}
.section-heading-with-action{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
.section-heading-copy{display:grid;gap:8px}
.section-heading-intro{margin:0;color:#64748b;font-size:.9rem;line-height:1.7}
.invoice-action-button{border:0;cursor:pointer}
.section-heading :deep(.directory-section-title){margin:0;font-size:1.04rem;font-weight:700;line-height:1.25;color:#0f172a}
.section-card :deep(.order-info-grid){align-items:stretch}
.detail-modal-backdrop{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(15,23,42,.55);backdrop-filter:blur(10px);z-index:2200}
.detail-modal{width:min(760px,calc(100vw - 32px));max-height:min(76vh,720px);display:grid;grid-template-rows:auto minmax(0,1fr);background:#fff;border:1px solid rgba(148,163,184,.35);border-radius:24px;box-shadow:0 32px 80px rgba(15,23,42,.24);overflow:hidden}
.detail-modal-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:24px 28px 18px;border-bottom:1px solid rgba(226,232,240,.9)}
.detail-modal-title{margin:0;color:#04151f;font-size:1.02rem;font-weight:700}
.detail-modal-close{appearance:none;border:1px solid rgba(148,163,184,.32);background:#fff;color:#04151f;min-height:38px;padding:0 14px;border-radius:999px;font-size:.82rem;font-weight:600;cursor:pointer}
.detail-modal-body{overflow:auto;padding:22px 28px 28px}
.modal-pill-list{display:flex;flex-wrap:wrap;gap:10px}
.modal-pill{display:inline-flex;align-items:center;min-height:38px;padding:0 14px;border-radius:999px;background:#f8fafc;border:1px solid rgba(148,163,184,.24);color:#04151f;font-size:.86rem;font-weight:600}
.modal-port-stack{display:grid;gap:18px}
.modal-port-group{display:grid;gap:10px}
.modal-port-group strong{color:#04151f;font-size:.95rem}
.port-summary-text{margin:0;color:#64748b}
@media (max-width: 900px){
    .hero-panel{flex-direction:column;align-items:stretch}
    .hero-actions{justify-content:flex-start}
}
@media (max-width: 720px){
    .surface-panel,.surface-card{padding:20px}
    .hero-panel{padding:20px}
    .subsection-surface{padding:20px}
    .hero-actions{width:100%;flex-direction:column;align-items:stretch}
    .back-button{width:100%}
    .section-heading-with-action{align-items:stretch}
}
</style>
