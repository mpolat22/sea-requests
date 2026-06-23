<script setup>
import { computed, defineAsyncComponent, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminDashboardShell from './Shell.vue';
import OfferCommercialSummary from '../../../Components/OfferCommercialSummary.vue';
import OrderInformationPanels from '../../../Components/OrderInformationPanels.vue';
import OrderInvoicesSection from '../../../Components/OrderInvoicesSection.vue';
import OrderSelectedItemsSection from '../../../Components/OrderSelectedItemsSection.vue';
import RfqGeneralInformationSection from '../../../Components/RfqGeneralInformationSection.vue';

const OrderInformationModal = defineAsyncComponent(() => import('../../Buyer/Dashboard/OrderInformationModal.vue'));
const PaymentProofModal = defineAsyncComponent(() => import('../../Buyer/Dashboard/PaymentProofModal.vue'));
const InvoiceUploadModal = defineAsyncComponent(() => import('../../Supplier/Dashboard/InvoiceUploadModal.vue'));

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

const order = props.order;
const isSpareParts = computed(() => order.request_type === 'spare_parts');
const hasInvoices = computed(() => (order.invoices?.length ?? 0) > 0);

const activeModalType = ref(null);
const detailModal = ref(null);

const copy = {
    title: 'Order Detail',
    eyebrow: 'Admin Order Detail',
    intro: 'Review the confirmed supplier order, intervene in order information when needed, manage invoices, and complete the payment workflow from one admin screen.',
    back: 'Back to Orders',
    viewRfq: 'View Admin RFQ',
    manageOrderInformation: 'Order Information',
    manageInvoices: 'Manage Invoices',
    managePaymentProof: 'Payment Proof',
    requestType: {
        spare_parts: 'Spare Parts',
        service_request: 'Service Request',
    },
    privateRequest: 'Private Request',
    general: 'General Information',
    referenceNo: 'Reference No',
    buyerCompany: 'Buyer Company',
    supplier: 'Supplier',
    ship: 'Ship',
    orderStatus: 'Order Status',
    country: 'Country',
    ports: 'Ports',
    requisitionDate: 'Requisition Date',
    dueDate: 'Due Date',
    currency: 'Currency',
    priority: 'Priority',
    confirmedAt: 'Award Confirmed',
    generalNotes: 'General Notes',
    selectedItems: 'Selected Items',
    selectedService: 'Selected Service',
    acceptedTerms: 'Accepted Commercial Terms',
    invoices: 'Invoices & Payments',
    invoicesIntro: 'Review every invoice and buyer payment proof linked to this order from the admin side.',
    partialAwardAccepted: 'Partial award accepted',
    fullQuotedScopeRequired: 'Full quoted scope required',
    selectedCountries: 'Selected Countries',
    selectedPorts: 'Selected Ports',
    countriesSelected: 'countries selected',
    portsSelected: 'ports selected',
    allListedPortsIn: 'All listed ports in',
    portsSelectedSuffix: 'ports selected',
    close: 'Close',
    noData: '-',
};

const isOrderInformationModalOpen = computed(() => activeModalType.value === 'order-information');
const isInvoiceUploadModalOpen = computed(() => activeModalType.value === 'invoice');
const isPaymentProofModalOpen = computed(() => activeModalType.value === 'payment-proof');
const orderWorkflowLabel = computed(() => order.order_workflow_status_label || 'Order Information Pending');

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

const textOrDash = (value) => {
    const text = `${value ?? ''}`.trim();
    return text || copy.noData;
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

const portGroups = computed(() => (order.ports_by_country ?? [])
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

    return (order.country_names ?? []).filter(Boolean);
});

const selectedCountryCount = computed(() => selectedCountries.value.length);
const selectedPortCount = computed(() => portGroups.value.reduce((total, entry) => total + (entry.ports?.length ?? 0), 0));
const portSelectionThreshold = 10;

const openDetailModal = (type) => {
    detailModal.value = type;
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
    const totalCount = Number(order.port_totals_by_country?.[group.country] ?? 0);

    if (totalCount > 0 && selectedCount === totalCount) {
        return `${copy.allListedPortsIn} ${group.country}`;
    }

    if (selectedCount > portSelectionThreshold) {
        return `${selectedCount} ${copy.portsSelectedSuffix}`;
    }

    return null;
};

const generalInformationFields = computed(() => [
    { key: 'reference_no', label: copy.referenceNo, value: textOrDash(order.reference_no) },
    { key: 'buyer_company', label: copy.buyerCompany, value: textOrDash(order.company_name) },
    { key: 'supplier', label: copy.supplier, value: textOrDash(order.supplier_name), href: order.supplier_profile_url || '' },
    { key: 'ship', label: copy.ship, value: textOrDash(order.ship_name) },
    { key: 'imo_number', label: 'IMO Number', value: textOrDash(order.imo_number) },
    { key: 'status', label: copy.orderStatus, value: orderWorkflowLabel.value },
    { key: 'country', label: copy.country, value: `${selectedCountryCount.value} ${copy.countriesSelected}`, clickable: true, action: 'countries' },
    { key: 'ports', label: copy.ports, value: `${selectedPortCount.value} ${copy.portsSelected}`, clickable: true, action: 'ports', long: true },
    { key: 'requisition_date', label: copy.requisitionDate, value: formatDate(order.requisition_date) },
    { key: 'due_date', label: copy.dueDate, value: formatDate(order.due_date) },
    { key: 'currency', label: copy.currency, value: textOrDash(order.currency) },
    { key: 'priority', label: copy.priority, value: formatTitleCaseValue(order.priority) },
    { key: 'confirmed_at', label: copy.confirmedAt, value: formatDate(order.confirmed_at) },
    { key: 'general_notes', label: copy.generalNotes, value: textOrDash(order.general_notes), long: true },
]);

const openModal = (type) => {
    activeModalType.value = type;
};

const closeActiveModal = () => {
    activeModalType.value = null;
};
</script>

<template>
    <AdminDashboardShell :dashboard="dashboard" :title="copy.title" active-tab="orders" :show-tabs="false" :show-intro="false">
        <section class="surface-panel hero-panel">
            <div class="hero-copy">
                <p class="directory-eyebrow">{{ copy.eyebrow }}</p>
                <h1 class="directory-page-title">{{ order.reference_no }}</h1>
                <p class="directory-intro-copy">{{ copy.intro }}</p>

                <div class="hero-pills">
                    <span class="pill request-type-pill">
                        {{ copy.requestType[order.request_type] || order.request_type }}
                    </span>
                    <span v-if="order.is_private_request" class="pill private-pill">
                        {{ copy.privateRequest }}
                    </span>
                    <span class="status-pill">
                        <span class="status-dot is-order-status"></span>
                        {{ orderWorkflowLabel }}
                    </span>
                </div>
            </div>

            <div class="hero-actions">
                <Link :href="dashboard.navigation.orders_url" class="back-button">
                    {{ copy.back }}
                </Link>
                <Link :href="order.rfq_show_url" class="back-button back-button-secondary">
                    {{ copy.viewRfq }}
                </Link>
                <button
                    v-if="order.can_edit_order_information"
                    type="button"
                    class="back-button back-button-secondary"
                    @click="openModal('order-information')"
                >
                    {{ copy.manageOrderInformation }}
                </button>
                <button
                    v-if="order.can_manage_invoices || hasInvoices"
                    type="button"
                    class="back-button back-button-secondary"
                    @click="openModal('invoice')"
                >
                    {{ copy.manageInvoices }}
                </button>
                <button
                    v-if="hasInvoices"
                    type="button"
                    class="back-button back-button-secondary"
                    @click="openModal('payment-proof')"
                >
                    {{ copy.managePaymentProof }}
                </button>
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
                :order="order"
                :title="isSpareParts ? copy.selectedItems : copy.selectedService"
            />

            <div class="section-divider"></div>

            <div class="subsection-surface">
                <div class="section-heading">
                    <h2 class="directory-section-title">{{ copy.acceptedTerms }}</h2>
                </div>

                <OfferCommercialSummary
                    :offer="order"
                    :request-type="order.request_type"
                    :award-scope-label="isSpareParts ? awardScopeLabel(order.award_scope_policy) : ''"
                />
            </div>
        </section>

        <section class="surface-card section-card order-info-section">
            <OrderInformationPanels :order="order" viewer="admin" />
        </section>

        <section class="surface-card section-card order-invoices-section">
            <OrderInvoicesSection
                :title="copy.invoices"
                :intro="copy.invoicesIntro"
                :invoices="order.invoices || []"
                :buyer-label="copy.buyerCompany"
                :buyer-name="order.company_name || ''"
                :supplier-label="copy.supplier"
                :supplier-name="order.supplier_name || ''"
                :supplier-href="order.supplier_profile_url || ''"
            />
        </section>

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
                            <div class="modal-pill-list">
                                <span v-for="port in group.ports" :key="`${group.country}-${port.id ?? port.name}`" class="modal-pill">
                                    {{ port.unlocode ? `${port.name} (${port.unlocode})` : port.name }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <OrderInformationModal
            :is-open="isOrderInformationModalOpen"
            :order="order"
            :can-edit="Boolean(order.can_edit_order_information)"
            :update-url="order.update_order_information_url || ''"
            return-to="detail"
            @close="closeActiveModal"
        />

        <InvoiceUploadModal
            :is-open="isInvoiceUploadModalOpen"
            :order="order"
            :can-manage="Boolean(order.can_manage_invoices)"
            :create-url="order.create_invoice_url || ''"
            :supplier-company-name="order.supplier_name || ''"
            return-to="detail"
            @close="closeActiveModal"
        />

        <PaymentProofModal
            :is-open="isPaymentProofModalOpen"
            :order="order"
            return-to="detail"
            @close="closeActiveModal"
        />
    </AdminDashboardShell>
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
.hero-copy{display:grid;gap:14px}
.hero-copy :deep(.directory-page-title){margin:0}
.hero-copy :deep(.directory-intro-copy){max-width:74ch}
.hero-pills{display:flex;flex-wrap:wrap;gap:10px}
.hero-actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end}
.back-button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border-radius:10px;background:#0f172a;color:#fff;text-decoration:none;font-size:.88rem;font-weight:600;border:1px solid transparent;cursor:pointer}
.back-button-secondary{background:#fff;border-color:#e2e8f0;color:#0f172a}
.status-pill,.pill{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 12px;border-radius:10px;font-size:.82rem;font-weight:600}
.request-type-pill{background:#f8fafc;border:1px solid #e2e8f0;color:#0f172a}
.private-pill{background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8}
.status-pill{background:#fffbeb;border:1px solid #fde68a;color:#b45309;gap:8px}
.status-dot{width:10px;height:10px;border-radius:999px;display:inline-block}
.status-dot.is-order-status{background:#d97706;box-shadow:0 0 0 3px rgba(217,119,6,.16)}
.section-heading{margin-bottom:16px}
.section-heading :deep(.directory-section-title){margin:0;font-size:1.04rem;font-weight:700;line-height:1.25;color:#0f172a}
.section-copy{margin:6px 0 0;color:#64748b;font-size:.92rem;line-height:1.7}
.section-card :deep(.order-info-grid){align-items:stretch}
.detail-modal-backdrop{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(15,23,42,.55);backdrop-filter:blur(10px);z-index:2200}
.detail-modal{width:min(760px,calc(100vw - 32px));max-height:min(76vh,720px);display:grid;grid-template-rows:auto minmax(0,1fr);background:#fff;border:1px solid rgba(148,163,184,.35);border-radius:24px;box-shadow:0 32px 80px rgba(15,23,42,.24);overflow:hidden}
.detail-modal-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:24px 28px 18px;border-bottom:1px solid rgba(226,232,240,.9)}
.detail-modal-title{margin:0;color:#0f172a;font-size:1.1rem;font-weight:700}
.detail-modal-close{border:0;background:transparent;color:#2563eb;font-size:.88rem;font-weight:700;cursor:pointer}
.detail-modal-body{padding:22px 28px 28px;overflow:auto}
.modal-pill-list{display:flex;flex-wrap:wrap;gap:10px}
.modal-pill{display:inline-flex;align-items:center;min-height:34px;padding:0 12px;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:.85rem;font-weight:600}
.modal-port-stack{display:grid;gap:18px}
.modal-port-group{display:grid;gap:10px}
.modal-port-group strong{color:#0f172a;font-size:.96rem;font-weight:700}
.notes-text{margin:0;color:#64748b;font-size:.92rem;line-height:1.7}
@media (max-width: 720px){
    .surface-panel,.surface-card{padding:20px}
    .hero-panel{padding:20px;flex-direction:column}
    .hero-actions{width:100%;flex-direction:column;align-items:stretch;justify-content:flex-start}
    .back-button{width:100%}
}
</style>
