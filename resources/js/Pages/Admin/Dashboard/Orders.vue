<script setup>
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminDashboardShell from './Shell.vue';

const OrderInformationModal = defineAsyncComponent(() => import('../../Buyer/Dashboard/OrderInformationModal.vue'));
const PaymentProofModal = defineAsyncComponent(() => import('../../Buyer/Dashboard/PaymentProofModal.vue'));
const InvoiceUploadModal = defineAsyncComponent(() => import('../../Supplier/Dashboard/InvoiceUploadModal.vue'));

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
    ordersTable: {
        type: Object,
        required: true,
    },
});

const searchQuery = ref(props.ordersTable.filters?.search ?? '');
const rowsPerPage = ref(Number(props.ordersTable.filters?.per_page ?? props.ordersTable.meta?.per_page ?? 10));
const activeModalType = ref(null);
const activeOrderSummary = ref(null);
const activeOrder = ref(null);
const isModalLoading = ref(false);
const modalLoadError = ref('');
const modalRequestToken = ref(0);

const copy = {
    title: 'Admin Dashboard',
    tabTitle: 'Orders',
    tabText: 'Manage every confirmed supplier-based order, intervene in order information, invoices, and payment workflow when a deal needs admin support.',
    searchPlaceholder: 'Search ref / buyer / supplier / ship / status',
    search: 'Search',
    recordsPerPage: 'Records per page:',
    showing: 'Showing',
    of: 'of',
    records: 'records',
    prev: 'Prev',
    next: 'Next',
    viewOrder: 'Order Detail',
    manageOrderInformation: 'Order Information',
    manageInvoices: 'Manage Invoices',
    managePaymentProof: 'Payment Proof',
    openRfq: 'Open RFQ',
    table: {
        order: '#',
        statusMini: 'Status',
        referenceNo: 'Reference No',
        buyerCompany: 'Buyer Company',
        supplier: 'Supplier',
        ship: 'Ship',
        confirmedAt: 'Award Confirmed',
        orderTotal: 'Order Total',
        invoices: 'Invoices',
        actions: 'Actions',
    },
    emptyTitle: 'No confirmed supplier order exists yet.',
    emptySearchTitle: 'No order matched your search.',
    emptySearchText: 'Try a different keyword or clear the search and try again.',
    noData: '-',
};

const orders = computed(() => props.ordersTable.data ?? []);
const meta = computed(() => props.ordersTable.meta ?? {});
const filters = computed(() => props.ordersTable.filters ?? {});
const currentPage = computed(() => Number(meta.value.current_page ?? filters.value.page ?? 1));
const totalPages = computed(() => Number(meta.value.last_page ?? 1));
const showingFrom = computed(() => Number(meta.value.from ?? 0));
const showingTo = computed(() => Number(meta.value.to ?? 0));
const totalRecords = computed(() => Number(meta.value.total ?? orders.value.length));
const hasSearchQuery = computed(() => searchQuery.value.trim().length > 0);

const isOrderInformationModalOpen = computed(() => activeModalType.value === 'order-information');
const isInvoiceUploadModalOpen = computed(() => activeModalType.value === 'invoice');
const isPaymentProofModalOpen = computed(() => activeModalType.value === 'payment-proof');

const rowNumber = (index) => totalRecords.value - (((currentPage.value - 1) * rowsPerPage.value) + index);

const formatDate = (value) => {
    if (!value) return copy.noData;

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
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

const statusTone = (status) => {
    if (status === 'completed') return 'is-completed';
    if (status === 'payment_confirmed') return 'is-payment-confirmed';
    if (status === 'payment_proof_uploaded') return 'is-payment-proof';
    if (status === 'buyer_payment_pending') return 'is-payment-pending';
    if (status === 'invoice_uploaded') return 'is-invoice-uploaded';
    if (status === 'invoice_pending') return 'is-invoice-pending';
    return 'is-order-information-pending';
};

const requestTypeLabel = (order) => {
    if (order?.is_private_request) {
        return 'Private Request';
    }

    return order?.request_type === 'service_request'
        ? 'Service Request'
        : 'Spare Parts';
};

const changeRowsPerPage = (event) => {
    rowsPerPage.value = Number(event.target.value) || 10;
    submitSearch(1);
};

const submitSearch = (page = 1) => {
    router.get(route('admin.orders'), {
        search: searchQuery.value.trim() || undefined,
        per_page: rowsPerPage.value,
        page,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const goPrev = () => {
    submitSearch(Math.max(1, currentPage.value - 1));
};

const goNext = () => {
    submitSearch(Math.min(totalPages.value, currentPage.value + 1));
};

const loadActiveOrder = async () => {
    const modalUrl = activeOrderSummary.value?.modal_url || '';

    if (!modalUrl) {
        activeOrder.value = null;
        modalLoadError.value = 'Order details could not be loaded right now.';
        return;
    }

    const requestToken = ++modalRequestToken.value;

    activeOrder.value = null;
    modalLoadError.value = '';
    isModalLoading.value = true;

    try {
        const response = await window.fetch(modalUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`Failed with status ${response.status}`);
        }

        const payload = await response.json();

        if (requestToken !== modalRequestToken.value) {
            return;
        }

        activeOrder.value = payload?.order ?? null;

        if (!activeOrder.value) {
            modalLoadError.value = 'Order details could not be loaded right now.';
        }
    } catch (error) {
        if (requestToken !== modalRequestToken.value) {
            return;
        }

        activeOrder.value = null;
        modalLoadError.value = 'Order details could not be loaded right now.';
    } finally {
        if (requestToken === modalRequestToken.value) {
            isModalLoading.value = false;
        }
    }
};

const openModal = async (type, order) => {
    activeModalType.value = type;
    activeOrderSummary.value = order;
    await loadActiveOrder();
};

const retryActiveModal = async () => {
    if (!activeModalType.value || !activeOrderSummary.value) {
        return;
    }

    await loadActiveOrder();
};

const closeActiveModal = () => {
    modalRequestToken.value += 1;
    activeModalType.value = null;
    activeOrderSummary.value = null;
    activeOrder.value = null;
    isModalLoading.value = false;
    modalLoadError.value = '';
};

watch(() => props.ordersTable.filters, (value) => {
    searchQuery.value = value?.search ?? '';
    rowsPerPage.value = Number(value?.per_page ?? props.ordersTable.meta?.per_page ?? 10);
}, { deep: true });
</script>

<template>
    <AdminDashboardShell :dashboard="dashboard" :title="copy.title" active-tab="orders">
        <section class="surface-panel table-panel">
            <div class="table-toolbar">
                <div class="table-intro">
                    <h2 class="directory-section-title">{{ copy.tabTitle }}</h2>
                    <p class="section-copy">{{ copy.tabText }}</p>
                </div>

                <form class="toolbar-search" @submit.prevent="submitSearch()">
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="copy.searchPlaceholder"
                    >
                    <button type="submit" class="toolbar-button toolbar-button-primary">
                        {{ copy.search }}
                    </button>
                </form>
            </div>

            <div v-if="orders.length" class="dashboard-table-wrap">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>{{ copy.table.order }}</th>
                            <th>{{ copy.table.statusMini }}</th>
                            <th>{{ copy.table.referenceNo }}</th>
                            <th>{{ copy.table.buyerCompany }}</th>
                            <th>{{ copy.table.supplier }}</th>
                            <th>{{ copy.table.ship }}</th>
                            <th>{{ copy.table.confirmedAt }}</th>
                            <th>{{ copy.table.orderTotal }}</th>
                            <th>{{ copy.table.invoices }}</th>
                            <th>{{ copy.table.actions }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(order, index) in orders" :key="order.id">
                            <td>
                                <span class="order-index">{{ rowNumber(index) }}</span>
                            </td>
                            <td>
                                <span class="status-chip" :title="order.order_workflow_status_label" :aria-label="order.order_workflow_status_label">
                                    <span class="status-dot" :class="statusTone(order.order_workflow_status)"></span>
                                </span>
                            </td>
                            <td>
                                <div class="primary-cell">
                                    <Link :href="order.show_url" class="reference-text">
                                        {{ order.reference_no }}
                                    </Link>
                                    <span class="reference-meta">{{ requestTypeLabel(order) }}</span>
                                </div>
                            </td>
                            <td>{{ order.buyer_company || copy.noData }}</td>
                            <td>
                                <Link
                                    v-if="order.supplier_profile_url"
                                    :href="order.supplier_profile_url"
                                    class="supplier-link"
                                >
                                    {{ order.supplier_name || copy.noData }}
                                </Link>
                                <template v-else>
                                    {{ order.supplier_name || copy.noData }}
                                </template>
                            </td>
                            <td>{{ order.ship_name || copy.noData }}</td>
                            <td>{{ formatDate(order.confirmed_at) }}</td>
                            <td>{{ formatMoney(order.agreed_invoice_total, order.currency) }}</td>
                            <td>{{ Number(order.invoices_count ?? 0) }}</td>
                            <td>
                                <div class="actions-cell">
                                    <Link :href="order.show_url" class="action-button action-view" :title="copy.viewOrder">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </Link>
                                    <button
                                        v-if="order.can_edit_order_information"
                                        type="button"
                                        class="action-button action-manage"
                                        :title="copy.manageOrderInformation"
                                        @click="openModal('order-information', order)"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                            <path d="M14 2v6h6" />
                                            <path d="M16 13H8" />
                                            <path d="M16 17H8" />
                                            <path d="M10 9H8" />
                                        </svg>
                                    </button>
                                    <button
                                        v-if="order.can_manage_invoices || order.has_invoices"
                                        type="button"
                                        class="action-button action-invoice"
                                        :title="copy.manageInvoices"
                                        @click="openModal('invoice', order)"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                            <path d="M14 2v6h6" />
                                            <path d="M12 18V10" />
                                            <path d="m8.5 13.5 3.5-3.5 3.5 3.5" />
                                        </svg>
                                    </button>
                                    <button
                                        v-if="order.has_invoices"
                                        type="button"
                                        class="action-button action-payment"
                                        :title="copy.managePaymentProof"
                                        @click="openModal('payment-proof', order)"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M3 7h18" />
                                            <path d="M7 3v8" />
                                            <path d="M17 3v8" />
                                            <rect x="4" y="5" width="16" height="15" rx="2" />
                                            <path d="m9 15 2 2 4-4" />
                                        </svg>
                                    </button>
                                    <Link
                                        v-if="order.rfq_url"
                                        :href="order.rfq_url"
                                        class="action-button action-rfq"
                                        :title="copy.openRfq"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M7 3h8l5 5v13a1 1 0 0 1-1 1H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" />
                                            <path d="M15 3v6h6" />
                                        </svg>
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="empty-card">
                <strong>{{ hasSearchQuery ? copy.emptySearchTitle : copy.emptyTitle }}</strong>
                <p v-if="hasSearchQuery">{{ copy.emptySearchText }}</p>
            </div>

            <div class="table-footer">
                <div class="footer-left">
                    <span>{{ copy.recordsPerPage }}</span>
                    <select :value="rowsPerPage" @change="changeRowsPerPage">
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                </div>

                <div class="footer-center">
                    {{ copy.showing }} {{ showingFrom }}-{{ showingTo }} {{ copy.of }} {{ totalRecords }} {{ copy.records }}
                </div>

                <div class="footer-right">
                    <button type="button" class="pager-button" :disabled="currentPage === 1" @click="goPrev">
                        {{ copy.prev }}
                    </button>
                    <span class="page-indicator">{{ currentPage }} / {{ totalPages }}</span>
                    <button type="button" class="pager-button" :disabled="currentPage === totalPages" @click="goNext">
                        {{ copy.next }}
                    </button>
                </div>
            </div>
        </section>

        <OrderInformationModal
            :is-open="isOrderInformationModalOpen"
            :order="activeOrder"
            :can-edit="Boolean(activeOrder?.can_edit_order_information)"
            :update-url="activeOrder?.update_order_information_url || ''"
            :is-loading="isModalLoading"
            :load-error="modalLoadError"
            @retry="retryActiveModal"
            @close="closeActiveModal"
        />

        <InvoiceUploadModal
            :is-open="isInvoiceUploadModalOpen"
            :order="activeOrder"
            :can-manage="Boolean(activeOrder?.can_manage_invoices)"
            :create-url="activeOrder?.create_invoice_url || ''"
            :supplier-company-name="activeOrder?.supplier_name || ''"
            :is-loading="isModalLoading"
            :load-error="modalLoadError"
            @retry="retryActiveModal"
            @close="closeActiveModal"
        />

        <PaymentProofModal
            :is-open="isPaymentProofModalOpen"
            :order="activeOrder"
            :is-loading="isModalLoading"
            :load-error="modalLoadError"
            @retry="retryActiveModal"
            @close="closeActiveModal"
        />
    </AdminDashboardShell>
</template>

<style scoped>
.surface-panel{padding:32px 36px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.78);box-shadow:0 24px 44px rgba(15,23,42,.08)}
.table-toolbar{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}
.table-intro{display:grid;gap:8px}
.table-intro :deep(.directory-section-title){margin:0}
.section-copy,.empty-card p{margin:0;color:#64748b;font-size:.9rem;line-height:1.7}
.toolbar-search{display:flex;align-items:center;gap:10px;margin-left:auto}
.toolbar-search input{width:290px;min-height:46px;padding:0 14px;border:1px solid rgba(148,163,184,.38);border-radius:8px;background:#fff;color:#0f172a;font-size:.92rem}
.toolbar-button{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border-radius:10px;border:1px solid transparent;font-size:.92rem;font-weight:600;text-decoration:none}
.toolbar-button-primary{background:#2563eb;border-color:#2563eb;color:#fff;box-shadow:0 12px 24px rgba(37,99,235,.18)}
.dashboard-table-wrap{margin-top:16px;overflow-x:auto}
.dashboard-table{width:100%;border-collapse:collapse;min-width:1180px}
.dashboard-table thead th{padding:16px 14px;background:#f4f7fb;color:#0f172a;font-size:.82rem;font-weight:700;text-align:left;white-space:nowrap}
.dashboard-table tbody td{padding:16px 14px;border-top:1px solid rgba(4,21,31,.06);color:#0f172a;font-size:.94rem;line-height:1.55;vertical-align:top;white-space:nowrap}
.order-index{color:#0f172a;font-weight:600}
.status-chip{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px}
.status-dot{width:10px;height:10px;border-radius:999px;display:inline-block}
.status-dot.is-order-information-pending{background:#d97706;box-shadow:0 0 0 3px rgba(217,119,6,.16)}
.status-dot.is-invoice-pending{background:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,.16)}
.status-dot.is-invoice-uploaded{background:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.16)}
.status-dot.is-payment-pending{background:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.16)}
.status-dot.is-payment-proof{background:#9333ea;box-shadow:0 0 0 3px rgba(147,51,234,.16)}
.status-dot.is-payment-confirmed{background:#0891b2;box-shadow:0 0 0 3px rgba(8,145,178,.16)}
.status-dot.is-completed{background:#16a34a;box-shadow:0 0 0 3px rgba(22,163,74,.16)}
.primary-cell{display:grid;gap:4px}
.reference-text{color:#0f172a;font-weight:400;text-decoration:none}
.reference-meta{color:#64748b;font-size:.8rem}
.supplier-link{color:#2563eb;text-decoration:underline;text-underline-offset:3px;font-weight:500}
.actions-cell{display:flex;align-items:center;gap:12px}
.action-button{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:0;background:transparent;padding:0;text-decoration:none}
.action-button svg{width:17px;height:17px}
.action-view{color:#2563eb}
.action-manage{color:#0f766e}
.action-invoice{color:#0891b2}
.action-payment{color:#7c3aed}
.action-rfq{color:#0f172a}
.empty-card{margin-top:18px;padding:32px 36px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.78);box-shadow:0 24px 44px rgba(15,23,42,.08)}
.empty-card strong{color:#0f172a;font-size:1rem;font-weight:600}
.table-footer{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:16px;margin-top:18px}
.footer-left,.footer-right{display:flex;align-items:center;gap:12px;color:#64748b;font-size:.9rem}
.footer-left select{min-height:38px;padding:0 12px;border:1px solid rgba(148,163,184,.32);border-radius:10px;background:#fff;color:#0f172a;font-size:.9rem}
.footer-center{color:#64748b;font-size:.9rem;text-align:center}
.pager-button{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 14px;border:1px solid rgba(148,163,184,.3);border-radius:10px;background:#fff;color:#0f172a;font-size:.88rem;font-weight:600}
.pager-button:disabled{color:#94a3b8;background:#f8fafc;cursor:not-allowed}
.page-indicator{color:#0f172a;font-size:.9rem;font-weight:600}
@media (max-width: 900px){
    .table-toolbar{flex-direction:column;align-items:stretch}
    .toolbar-search{margin-left:0}
}
@media (max-width: 720px){
    .surface-panel,.empty-card{padding:24px}
    .toolbar-search{width:100%;flex-direction:column}
    .toolbar-search input,.toolbar-button{width:100%}
    .table-footer{grid-template-columns:1fr;justify-items:stretch}
    .footer-left,.footer-right{justify-content:space-between}
    .footer-center{text-align:left}
}
</style>
