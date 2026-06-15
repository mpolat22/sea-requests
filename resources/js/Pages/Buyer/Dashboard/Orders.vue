<script setup>
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import BuyerDashboardShell from './Shell.vue';
import { useMessengerStore } from '../../../lib/messengerStore';

const OrderInformationModal = defineAsyncComponent(() => import('./OrderInformationModal.vue'));
const PaymentProofModal = defineAsyncComponent(() => import('./PaymentProofModal.vue'));
const messenger = useMessengerStore();

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
    orders: {
        type: Array,
        default: () => [],
    },
});

const searchQuery = ref('');
const rowsPerPage = ref(10);
const currentPage = ref(1);
const activeModalType = ref(null);
const activeOrderSummary = ref(null);
const activeOrder = ref(null);
const isModalLoading = ref(false);
const modalLoadError = ref('');
const modalRequestToken = ref(0);

const copy = {
    title: 'Buyer Dashboard',
    tabTitle: 'Orders',
    tabText: 'Track supplier-based orders created after your confirmed awards. This is where invoice and payment workflow will continue next.',
    searchPlaceholder: 'Search ref / supplier / ship / title',
    search: 'Search',
    emptyTitle: 'No supplier orders have been created yet.',
    emptySearchTitle: 'No supplier order matched your search.',
    emptySearchText: 'Try a different keyword or clear the search and try again.',
    recordsPerPage: 'Records per page:',
    showing: 'Showing',
    of: 'of',
    records: 'records',
    prev: 'Prev',
    next: 'Next',
    viewOrder: 'Order Detail',
    manageOrderInformation: 'Order Information',
    managePaymentProof: 'Invoices & Payment Proof',
    message: 'Message',
    newMessage: 'New message',
    table: {
        order: '#',
        statusMini: 'Status',
        referenceNo: 'Reference No',
        supplier: 'Supplier',
        ship: 'Ship',
        selectedItems: 'Selected Items',
        confirmedAt: 'Confirmed At',
        selectedTotal: 'Selected Total',
        actions: 'Actions',
    },
    noData: '-',
};

const formatDateTime = (value) => {
    if (!value) return copy.noData;

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return value;

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

const textOrDash = (value) => {
    const text = `${value ?? ''}`.trim();
    return text || copy.noData;
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

const filteredOrders = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (!query) {
        return props.orders;
    }

    return props.orders.filter((order) => {
        const haystack = [
            order.reference_no,
            order.supplier_name,
            order.ship_name,
            order.service_title,
            order.payment_terms_summary,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(query);
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredOrders.value.length / rowsPerPage.value)));

const paginatedOrders = computed(() => {
    const start = (currentPage.value - 1) * rowsPerPage.value;
    return filteredOrders.value.slice(start, start + rowsPerPage.value);
});

const showingFrom = computed(() => {
    if (!filteredOrders.value.length) return 0;
    return ((currentPage.value - 1) * rowsPerPage.value) + 1;
});

const showingTo = computed(() => {
    if (!filteredOrders.value.length) return 0;
    return Math.min(currentPage.value * rowsPerPage.value, filteredOrders.value.length);
});

const hasSearchQuery = computed(() => searchQuery.value.trim().length > 0);

const unreadCountsByOffer = computed(() => new Map(
    (messenger.state.conversations ?? []).map((conversation) => [
        Number(conversation.offer_id),
        Number(conversation.unread_count ?? 0),
    ]),
));

const unreadMessageCount = (offerId) => unreadCountsByOffer.value.get(Number(offerId)) ?? 0;

const hasUnreadMessages = (offerId) => unreadMessageCount(offerId) > 0;

const unreadBadgeLabel = (offerId) => {
    const count = unreadMessageCount(offerId);

    if (count <= 0) {
        return '';
    }

    return count > 99 ? '99+' : `${count}`;
};

const rowNumber = (index) => filteredOrders.value.length - (((currentPage.value - 1) * rowsPerPage.value) + index);

const changeRowsPerPage = (event) => {
    rowsPerPage.value = Number(event.target.value) || 10;
    currentPage.value = 1;
};

watch(searchQuery, () => {
    currentPage.value = 1;
});

watch(totalPages, (nextTotalPages) => {
    if (currentPage.value > nextTotalPages) {
        currentPage.value = nextTotalPages;
    }
});

const goPrev = () => {
    currentPage.value = Math.max(1, currentPage.value - 1);
};

const goNext = () => {
    currentPage.value = Math.min(totalPages.value, currentPage.value + 1);
};

const isOrderInformationModalOpen = computed(() => activeModalType.value === 'order-information');
const isPaymentProofModalOpen = computed(() => activeModalType.value === 'payment-proof');

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

const openOrderInformationModal = async (order) => {
    await openModal('order-information', order);
};

const openPaymentProofModal = async (order) => {
    await openModal('payment-proof', order);
};

const openMessenger = async (order) => {
    if (!order?.offer_id) {
        return;
    }

    await messenger.openForOffer(order.offer_id);
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
</script>

<template>
    <BuyerDashboardShell :dashboard="dashboard" :title="copy.title" active-tab="orders">
        <section class="surface-panel table-panel">
            <div class="table-toolbar">
                <div class="table-intro">
                    <h2 class="directory-section-title">{{ copy.tabTitle }}</h2>
                    <p class="section-copy">{{ copy.tabText }}</p>
                </div>

                <div class="toolbar-search">
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="copy.searchPlaceholder"
                    >
                    <button type="button" class="toolbar-button toolbar-button-primary">
                        {{ copy.search }}
                    </button>
                </div>
            </div>

            <div v-if="paginatedOrders.length" class="dashboard-table-wrap">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>{{ copy.table.order }}</th>
                            <th>{{ copy.table.statusMini }}</th>
                            <th>{{ copy.table.referenceNo }}</th>
                            <th>{{ copy.table.supplier }}</th>
                            <th>{{ copy.table.ship }}</th>
                            <th>{{ copy.table.selectedItems }}</th>
                            <th>{{ copy.table.confirmedAt }}</th>
                            <th>{{ copy.table.selectedTotal }}</th>
                            <th>{{ copy.table.actions }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(order, index) in paginatedOrders" :key="order.id">
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
                                    <span class="reference-text">{{ order.reference_no }}</span>
                                    <span v-if="hasUnreadMessages(order.offer_id)" class="row-unread-pill">{{ copy.newMessage }}</span>
                                </div>
                            </td>
                            <td>
                                <Link
                                    v-if="order.supplier_profile_url"
                                    :href="order.supplier_profile_url"
                                    class="supplier-link"
                                >
                                    {{ textOrDash(order.supplier_name) }}
                                </Link>
                                <template v-else>
                                    {{ textOrDash(order.supplier_name) }}
                                </template>
                            </td>
                            <td>{{ textOrDash(order.ship_name) }}</td>
                            <td>{{ Number(order.selected_items_count ?? 0) }}</td>
                            <td>{{ formatDateTime(order.confirmed_at) }}</td>
                            <td>{{ formatMoney(order.agreed_invoice_total ?? order.selected_total, order.currency) }}</td>
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
                                        @click="openOrderInformationModal(order)"
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
                                        v-if="order.has_invoices"
                                        type="button"
                                        class="action-button action-payment"
                                        :title="copy.managePaymentProof"
                                        @click="openPaymentProofModal(order)"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M3 7h18" />
                                            <path d="M7 3v8" />
                                            <path d="M17 3v8" />
                                            <rect x="4" y="5" width="16" height="15" rx="2" />
                                            <path d="m9 15 2 2 4-4" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        class="action-button action-message"
                                        :title="copy.message"
                                        @click="openMessenger(order)"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M7 10h10" />
                                            <path d="M7 14h6" />
                                            <path d="M5 5h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H9l-4 3v-3H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" />
                                        </svg>
                                        <span v-if="hasUnreadMessages(order.offer_id)" class="action-badge">{{ unreadBadgeLabel(order.offer_id) }}</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="paginatedOrders.length" class="mobile-card-stack">
                <article v-for="(order, index) in paginatedOrders" :key="`mobile-${order.id}`" class="mobile-record-card">
                    <div class="mobile-card-head">
                        <div class="mobile-card-title-group">
                            <span class="mobile-card-kicker">#{{ rowNumber(index) }}</span>
                            <Link :href="order.show_url" class="mobile-card-title">
                                {{ order.reference_no }}
                            </Link>
                            <span v-if="hasUnreadMessages(order.offer_id)" class="row-unread-pill">{{ copy.newMessage }}</span>
                        </div>
                        <span class="mobile-status-pill">
                            <span class="status-dot" :class="statusTone(order.order_workflow_status)"></span>
                            {{ order.order_workflow_status_label }}
                        </span>
                    </div>

                    <div class="mobile-card-grid">
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.supplier }}</span>
                            <span class="mobile-field-value">
                                <Link
                                    v-if="order.supplier_profile_url"
                                    :href="order.supplier_profile_url"
                                    class="supplier-link"
                                >
                                    {{ textOrDash(order.supplier_name) }}
                                </Link>
                                <template v-else>
                                    {{ textOrDash(order.supplier_name) }}
                                </template>
                            </span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.ship }}</span>
                            <span class="mobile-field-value">{{ textOrDash(order.ship_name) }}</span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.selectedItems }}</span>
                            <span class="mobile-field-value">{{ Number(order.selected_items_count ?? 0) }}</span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.confirmedAt }}</span>
                            <span class="mobile-field-value">{{ formatDateTime(order.confirmed_at) }}</span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.selectedTotal }}</span>
                            <span class="mobile-field-value">{{ formatMoney(order.agreed_invoice_total ?? order.selected_total, order.currency) }}</span>
                        </div>
                    </div>

                    <div class="mobile-card-footer">
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
                                @click="openOrderInformationModal(order)"
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
                                v-if="order.has_invoices"
                                type="button"
                                class="action-button action-payment"
                                :title="copy.managePaymentProof"
                                @click="openPaymentProofModal(order)"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 7h18" />
                                    <path d="M7 3v8" />
                                    <path d="M17 3v8" />
                                    <rect x="4" y="5" width="16" height="15" rx="2" />
                                    <path d="m9 15 2 2 4-4" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="action-button action-message"
                                :title="copy.message"
                                @click="openMessenger(order)"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M7 10h10" />
                                    <path d="M7 14h6" />
                                    <path d="M5 5h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H9l-4 3v-3H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" />
                                </svg>
                                <span v-if="hasUnreadMessages(order.offer_id)" class="action-badge">{{ unreadBadgeLabel(order.offer_id) }}</span>
                            </button>
                        </div>
                    </div>
                </article>
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
                    {{ copy.showing }} {{ showingFrom }} - {{ showingTo }} {{ copy.of }} {{ filteredOrders.length }} {{ copy.records }}
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

        <PaymentProofModal
            :is-open="isPaymentProofModalOpen"
            :order="activeOrder"
            :is-loading="isModalLoading"
            :load-error="modalLoadError"
            @retry="retryActiveModal"
            @close="closeActiveModal"
        />
    </BuyerDashboardShell>
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
.mobile-card-stack{display:none}
.dashboard-table{width:100%;border-collapse:collapse;min-width:980px}
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
.reference-text{color:#0f172a;font-weight:400}
.row-unread-pill{display:inline-flex;align-items:center;justify-content:center;width:max-content;padding:3px 8px;border-radius:999px;background:#eff6ff;color:#2563eb;font-size:.72rem;font-weight:700;line-height:1.2}
.supplier-link{color:#2563eb;text-decoration:underline;text-underline-offset:3px;font-weight:500}
.actions-cell{display:flex;align-items:center;gap:12px}
.action-button{position:relative;display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:0;background:transparent;padding:0;text-decoration:none}
.action-button svg{width:17px;height:17px}
.action-badge{position:absolute;top:-5px;right:-7px;min-width:16px;height:16px;padding:0 4px;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:#2563eb;color:#fff;font-size:.63rem;font-weight:700;line-height:1}
.action-manage{color:#0f766e}
.action-view{color:#2563eb}
.action-payment{color:#7c3aed}
.action-message{color:#0f172a}
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
    .table-footer{grid-template-columns:1fr}
    .footer-center{text-align:left}
}
@media (max-width: 720px){
    .surface-panel,.empty-card{padding:20px}
    .dashboard-table-wrap{display:none}
    .mobile-card-stack{display:grid;gap:16px;margin-top:16px}
    .mobile-record-card{display:grid;gap:16px;padding:18px;border:1px solid rgba(4,21,31,.08);border-radius:12px;background:#fff}
    .mobile-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
    .mobile-card-title-group{display:grid;gap:6px;min-width:0}
    .mobile-card-kicker{color:#64748b;font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
    .mobile-card-title{color:#0f172a;font-size:.98rem;font-weight:700;text-decoration:none;word-break:break-word}
    .mobile-status-pill{display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border-radius:999px;background:#f8fafc;color:#475569;font-size:.78rem;font-weight:700}
    .mobile-card-grid{display:grid;grid-template-columns:1fr;gap:12px}
    .mobile-card-field{display:grid;gap:5px}
    .mobile-field-label{color:#64748b;font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em}
    .mobile-field-value{color:#0f172a;font-size:.92rem;line-height:1.55;word-break:break-word}
    .mobile-card-footer{padding-top:4px;border-top:1px solid rgba(226,232,240,.9)}
    .actions-cell{flex-wrap:wrap}
    .toolbar-search{flex-direction:column;align-items:stretch}
    .toolbar-search input{width:100%}
}
</style>
