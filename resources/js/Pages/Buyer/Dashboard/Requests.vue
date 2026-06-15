<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import BuyerDashboardShell from './Shell.vue';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
    rfqsPage: {
        type: Object,
        required: true,
    },
    createUrl: {
        type: String,
        required: true,
    },
    indexUrl: {
        type: String,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            per_page: 10,
        }),
    },
});

const searchQuery = ref(props.filters.search ?? '');
const rowsPerPage = ref(Number(props.filters.per_page ?? props.rfqsPage.per_page ?? 10));
const deleteModalRfq = ref(null);

const copy = {
    title: 'Buyer Dashboard',
    tabTitle: 'My RFQs',
    tabText: 'Manage your RFQ records, compare supplier offers, and keep your request pipeline under control.',
    create: '+ New RFQ',
    searchPlaceholder: 'Search ref / title / port / company / ship',
    search: 'Search',
    table: {
        order: '#',
        statusMini: 'Status',
        referenceNo: 'Reference No',
        company: 'Company',
        ship: 'Ship',
        requisitionDate: 'Requisition Date',
        dueDate: 'Due Date',
        priority: 'Priority',
        offers: 'Offers',
        actions: 'Actions',
    },
    recordsPerPage: 'Records per page:',
    showing: 'Showing',
    of: 'of',
    records: 'records',
    prev: 'Prev',
    next: 'Next',
    priority: {
        low: 'low',
        normal: 'normal',
        high: 'high',
        critical: 'urgent',
    },
    emptyTitle: 'No RFQs have been created yet.',
    emptyText: 'Your RFQ records will appear here once you create the first one.',
    emptySearchTitle: 'No RFQs matched your search.',
    emptySearchText: 'Try a different keyword or clear the search and try again.',
    compareOffers: 'Compare Offers',
    compareLocked: 'No submitted offers are available to compare yet.',
    compareCompleted: 'This RFQ is completed. Offers are now view only.',
    editLocked: 'This RFQ cannot be edited now.',
    editLockedOffers: 'Offers have already started to arrive. Only General Information can be updated now.',
    editLockedAwards: 'Offer evaluation has already started. Once an award draft or confirmation begins, this RFQ can no longer be edited.',
    editLockedCancelled: 'Cancelled RFQs cannot be edited.',
    editReopenable: 'This RFQ is closed. You can edit it and reopen it.',
    editGeneralOnlyOverdue: 'The due date has passed. Only General Information can be updated so you can extend the timeline.',
    editOverdueHardLocked: 'This RFQ can no longer be edited because the due date has passed.',
    deleteTitle: 'Delete draft RFQ',
    deleteTitleClosed: 'Delete RFQ',
    deleteLocked: 'This RFQ cannot be deleted right now.',
    deleteBodyDraft: 'This RFQ is still a draft. If you delete it, its line items, files, and supplier targeting details will be removed permanently.',
    deleteBodyClosed: 'This RFQ is closed and has not received any offers yet. If you delete it, its line items, files, and supplier targeting details will be removed permanently.',
    deleteCancel: 'Cancel',
    deleteConfirmDraftButton: 'Delete Draft',
    deleteConfirmClosedButton: 'Delete RFQ',
};

const offerText = (count) => `${Number(count ?? 0)} Received`;
const rfqs = computed(() => props.rfqsPage.data ?? []);
const currentPage = computed(() => props.rfqsPage.current_page ?? 1);
const totalPages = computed(() => props.rfqsPage.last_page ?? 1);
const showingFrom = computed(() => props.rfqsPage.from ?? 0);
const showingTo = computed(() => props.rfqsPage.to ?? 0);
const totalRecords = computed(() => props.rfqsPage.total ?? rfqs.value.length);
const hasSearchQuery = computed(() => searchQuery.value.trim().length > 0);
const rowNumber = (index) => totalRecords.value - (((currentPage.value - 1) * rowsPerPage.value) + index);

const formatDate = (value) => {
    if (!value) return '-';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
};

const statusTone = (status) => {
    if (status === 'open') return 'is-open';
    if (status === 'award_in_progress') return 'is-review';
    if (status === 'award_confirmed') return 'is-awarded';
    if (status === 'completed') return 'is-completed';
    if (status === 'closed' || status === 'cancelled') return 'is-closed';
    return 'is-draft';
};

const statusLabel = (status) => {
    if (status === 'open') return 'Open';
    if (status === 'award_in_progress') return 'Award In Progress';
    if (status === 'award_confirmed') return 'Award Confirmed';
    if (status === 'completed') return 'Completed';
    if (status === 'closed') return 'Closed';
    if (status === 'cancelled') return 'Cancelled';
    return 'Draft';
};

const canCompareOffers = (rfq) => Boolean(rfq?.compare_url);

const compareTitle = (rfq) => {
    if (canCompareOffers(rfq)) {
        return copy.compareOffers;
    }

    return rfq?.status === 'completed'
        ? copy.compareCompleted
        : copy.compareLocked;
};

const urgencyTone = (priority) => {
    if (priority === 'critical') return 'is-critical';
    if (priority === 'high') return 'is-high';
    if (priority === 'low') return 'is-low';
    return 'is-normal';
};

const capitalizeLabel = (value) => {
    if (!value) return '-';

    return String(value).charAt(0).toUpperCase() + String(value).slice(1);
};

const changeRowsPerPage = (event) => {
    rowsPerPage.value = Number(event.target.value) || 10;
    submitSearch(1);
};

const goPrev = () => {
    submitSearch(Math.max(1, currentPage.value - 1));
};

const goNext = () => {
    submitSearch(Math.min(totalPages.value, currentPage.value + 1));
};

const submitSearch = (page = 1) => {
    router.get(props.indexUrl, {
        search: searchQuery.value.trim() || undefined,
        per_page: rowsPerPage.value,
        page,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const editTitle = (rfq) => {
    if (rfq?.can_edit) {
        if (rfq?.edit_reason === 'overdue_extendable') {
            return copy.editGeneralOnlyOverdue;
        }

        if (rfq?.general_only_edit) {
            return copy.editLockedOffers;
        }

        if (rfq?.edit_reason === 'reopenable_closed') {
            return copy.editReopenable;
        }

        return 'Edit';
    }

    if (rfq?.edit_reason === 'overdue') {
        return copy.editOverdueHardLocked;
    }

    if (rfq?.edit_reason === 'award_started') {
        return copy.editLockedAwards;
    }

    if (rfq?.edit_reason === 'cancelled') {
        return copy.editLockedCancelled;
    }

    return copy.editLocked;
};

const deleteActionTitle = (rfq) => {
    if (!rfq?.can_delete) {
        return copy.deleteLocked;
    }

    return rfq?.status === 'closed'
        ? copy.deleteTitleClosed
        : copy.deleteTitle;
};

const openDeleteModal = (rfq) => {
    if (!rfq?.can_delete || !rfq?.delete_url) {
        return;
    }

    deleteModalRfq.value = rfq;
};

const closeDeleteModal = () => {
    deleteModalRfq.value = null;
};

const deleteModalBody = computed(() => {
    if (!deleteModalRfq.value) return '';

    return deleteModalRfq.value.status === 'closed'
        ? copy.deleteBodyClosed
        : copy.deleteBodyDraft;
});

const deleteModalConfirmLabel = computed(() => {
    if (!deleteModalRfq.value) return copy.deleteConfirmDraftButton;

    return deleteModalRfq.value.status === 'closed'
        ? copy.deleteConfirmClosedButton
        : copy.deleteConfirmDraftButton;
});

const confirmDeleteRfq = () => {
    if (!deleteModalRfq.value?.delete_url) {
        return;
    }

    router.delete(deleteModalRfq.value.delete_url, {
        preserveScroll: true,
        onFinish: () => {
            closeDeleteModal();
        },
    });
};

watch(() => props.filters, (value) => {
    searchQuery.value = value?.search ?? '';
    rowsPerPage.value = Number(value?.per_page ?? props.rfqsPage.per_page ?? 10);
}, { deep: true });
</script>

<template>
    <BuyerDashboardShell :dashboard="dashboard" :title="copy.title" active-tab="requests">
        <section class="surface-panel table-panel">
            <div class="table-toolbar">
                <div class="table-intro">
                    <h2 class="directory-section-title">{{ copy.tabTitle }}</h2>
                    <p class="section-copy">{{ copy.tabText }}</p>
                </div>

                <div class="toolbar-actions">
                    <Link :href="createUrl" class="toolbar-button toolbar-button-primary">
                        {{ copy.create }}
                    </Link>

                    <form class="toolbar-search" @submit.prevent="submitSearch">
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
            </div>

            <div v-if="rfqs.length" class="dashboard-table-wrap">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>
                                <div class="order-head">
                                    <span>{{ copy.table.order }}</span>
                                    <span>{{ copy.table.statusMini }}</span>
                                </div>
                            </th>
                            <th>{{ copy.table.referenceNo }}</th>
                            <th>{{ copy.table.company }}</th>
                            <th>{{ copy.table.ship }}</th>
                            <th>{{ copy.table.requisitionDate }}</th>
                            <th>{{ copy.table.dueDate }}</th>
                            <th>{{ copy.table.priority }}</th>
                            <th>{{ copy.table.offers }}</th>
                            <th>{{ copy.table.actions }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(rfq, index) in rfqs" :key="rfq.id">
                            <td>
                                <div class="order-cell">
                                    <span class="order-index">{{ rowNumber(index) }}</span>
                                    <span class="status-dot" :class="statusTone(rfq.status)" :title="statusLabel(rfq.status)" :aria-label="statusLabel(rfq.status)"></span>
                                </div>
                            </td>
                            <td>{{ rfq.reference_no }}</td>
                            <td>{{ rfq.company_name || '-' }}</td>
                            <td>{{ rfq.ship_name || '-' }}</td>
                            <td>{{ formatDate(rfq.requisition_date) }}</td>
                            <td>{{ formatDate(rfq.due_date) }}</td>
                            <td>
                                <span class="pill pill-urgency" :class="urgencyTone(rfq.priority)">
                                    {{ capitalizeLabel(copy.priority[rfq.priority] || rfq.priority || '-') }}
                                </span>
                            </td>
                            <td>
                                <Link
                                    v-if="canCompareOffers(rfq)"
                                    :href="rfq.compare_url"
                                    class="offer-link"
                                    :title="compareTitle(rfq)"
                                >
                                    {{ offerText(rfq.offers_count) }}
                                </Link>
                                <button
                                    v-else
                                    type="button"
                                    class="offer-link"
                                    :title="compareTitle(rfq)"
                                    disabled
                                >
                                    {{ offerText(rfq.offers_count) }}
                                </button>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <Link :href="rfq.show_url" class="action-button action-view" title="View">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </Link>
                                    <Link
                                        v-if="canCompareOffers(rfq)"
                                        :href="rfq.compare_url"
                                        class="action-button action-edit"
                                        :title="compareTitle(rfq)"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M4 7h16" />
                                            <path d="M4 12h16" />
                                            <path d="M4 17h16" />
                                        </svg>
                                    </Link>
                                    <button
                                        v-else
                                        type="button"
                                        class="action-button action-disabled"
                                        :title="compareTitle(rfq)"
                                        disabled
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M4 7h16" />
                                            <path d="M4 12h16" />
                                            <path d="M4 17h16" />
                                        </svg>
                                    </button>
                                    <Link
                                        v-if="rfq.can_edit"
                                        :href="rfq.edit_url"
                                        class="action-button action-edit"
                                        :title="editTitle(rfq)"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 20h9" />
                                            <path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                                        </svg>
                                    </Link>
                                    <button
                                        v-else
                                        type="button"
                                        class="action-button action-disabled"
                                        :title="editTitle(rfq)"
                                        disabled
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 20h9" />
                                            <path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        class="action-button"
                                        :class="rfq.can_delete ? 'action-delete' : 'action-disabled'"
                                        :title="deleteActionTitle(rfq)"
                                        :disabled="!rfq.can_delete"
                                        @click="openDeleteModal(rfq)"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M3 6h18" />
                                            <path d="M8 6V4h8v2" />
                                            <path d="M19 6l-1 14H6L5 6" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="rfqs.length" class="mobile-card-stack">
                <article v-for="(rfq, index) in rfqs" :key="`mobile-${rfq.id}`" class="mobile-record-card">
                    <div class="mobile-card-head">
                        <div class="mobile-card-title-group">
                            <span class="mobile-card-kicker">#{{ rowNumber(index) }}</span>
                            <Link :href="rfq.show_url" class="mobile-card-title">
                                {{ rfq.reference_no }}
                            </Link>
                        </div>
                        <span class="mobile-status-pill">
                            <span class="status-dot" :class="statusTone(rfq.status)"></span>
                            {{ statusLabel(rfq.status) }}
                        </span>
                    </div>

                    <div class="mobile-card-grid">
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.company }}</span>
                            <span class="mobile-field-value">{{ rfq.company_name || '-' }}</span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.ship }}</span>
                            <span class="mobile-field-value">{{ rfq.ship_name || '-' }}</span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.requisitionDate }}</span>
                            <span class="mobile-field-value">{{ formatDate(rfq.requisition_date) }}</span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.dueDate }}</span>
                            <span class="mobile-field-value">{{ formatDate(rfq.due_date) }}</span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.priority }}</span>
                            <span class="mobile-field-value">
                                <span class="pill pill-urgency" :class="urgencyTone(rfq.priority)">
                                    {{ capitalizeLabel(copy.priority[rfq.priority] || rfq.priority || '-') }}
                                </span>
                            </span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.offers }}</span>
                            <span class="mobile-field-value">
                                <Link
                                    v-if="canCompareOffers(rfq)"
                                    :href="rfq.compare_url"
                                    class="offer-link"
                                    :title="compareTitle(rfq)"
                                >
                                    {{ offerText(rfq.offers_count) }}
                                </Link>
                                <button
                                    v-else
                                    type="button"
                                    class="offer-link"
                                    :title="compareTitle(rfq)"
                                    disabled
                                >
                                    {{ offerText(rfq.offers_count) }}
                                </button>
                            </span>
                        </div>
                    </div>

                    <div class="mobile-card-footer">
                        <div class="actions-cell">
                            <Link :href="rfq.show_url" class="action-button action-view" title="View">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </Link>
                            <Link
                                v-if="canCompareOffers(rfq)"
                                :href="rfq.compare_url"
                                class="action-button action-edit"
                                :title="compareTitle(rfq)"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 7h16" />
                                    <path d="M4 12h16" />
                                    <path d="M4 17h16" />
                                </svg>
                            </Link>
                            <button
                                v-else
                                type="button"
                                class="action-button action-disabled"
                                :title="compareTitle(rfq)"
                                disabled
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 7h16" />
                                    <path d="M4 12h16" />
                                    <path d="M4 17h16" />
                                </svg>
                            </button>
                            <Link
                                v-if="rfq.can_edit"
                                :href="rfq.edit_url"
                                class="action-button action-edit"
                                :title="editTitle(rfq)"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 20h9" />
                                    <path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                                </svg>
                            </Link>
                            <button
                                v-else
                                type="button"
                                class="action-button action-disabled"
                                :title="editTitle(rfq)"
                                disabled
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 20h9" />
                                    <path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="action-button"
                                :class="rfq.can_delete ? 'action-delete' : 'action-disabled'"
                                :title="deleteActionTitle(rfq)"
                                :disabled="!rfq.can_delete"
                                @click="openDeleteModal(rfq)"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 6h18" />
                                    <path d="M8 6V4h8v2" />
                                    <path d="M19 6l-1 14H6L5 6" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else class="empty-card">
                <strong>{{ hasSearchQuery ? copy.emptySearchTitle : copy.emptyTitle }}</strong>
                <p>{{ hasSearchQuery ? copy.emptySearchText : copy.emptyText }}</p>
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
                    {{ copy.showing }} {{ showingFrom }} - {{ showingTo }} {{ copy.of }} {{ totalRecords }} {{ copy.records }}
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

        <div v-if="deleteModalRfq" class="delete-modal-backdrop" @click.self="closeDeleteModal">
            <div class="delete-modal">
                <button type="button" class="delete-modal-close" @click="closeDeleteModal">&times;</button>
                <h3 class="directory-section-title">{{ deleteActionTitle(deleteModalRfq) }}</h3>
                <p class="delete-modal-copy">{{ deleteModalBody }}</p>
                <div class="delete-modal-summary">
                    <span>{{ deleteModalRfq.reference_no }}</span>
                    <span>{{ deleteModalRfq.company_name || '-' }}</span>
                    <span>{{ deleteModalRfq.ship_name || '-' }}</span>
                </div>
                <div class="delete-modal-actions">
                    <button type="button" class="toolbar-button delete-cancel-button" @click="closeDeleteModal">
                        {{ copy.deleteCancel }}
                    </button>
                    <button type="button" class="toolbar-button delete-confirm-button" @click="confirmDeleteRfq">
                        {{ deleteModalConfirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </BuyerDashboardShell>
</template>

<style scoped>
.surface-panel,.empty-card{padding:32px 36px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.78);box-shadow:0 24px 44px rgba(15,23,42,.08)}
.section-copy,.empty-card p{margin:0;color:#64748b;font-size:.9rem;line-height:1.7}
.table-panel{margin-top:0}
.table-toolbar{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}
.table-intro{display:grid;gap:8px}
.table-intro :deep(.directory-section-title){margin:0}
.toolbar-actions{display:flex;align-items:center;gap:14px;margin-left:auto}
.toolbar-button{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border-radius:10px;border:1px solid transparent;font-size:.92rem;font-weight:600;text-decoration:none}
.toolbar-button-primary{background:#2563eb;border-color:#2563eb;color:#fff;box-shadow:0 12px 24px rgba(37,99,235,.18)}
.toolbar-search{display:flex;align-items:center;gap:10px}
.toolbar-search input{width:290px;min-height:46px;padding:0 14px;border:1px solid rgba(148,163,184,.38);border-radius:8px;background:#fff;color:#0f172a;font-size:.92rem}
.dashboard-table-wrap{margin-top:16px;overflow-x:auto}
.mobile-card-stack{display:none}
.dashboard-table{width:100%;border-collapse:collapse;min-width:980px}
.dashboard-table thead th{padding:16px 14px;background:#f4f7fb;color:#0f172a;font-size:.82rem;font-weight:700;text-align:left}
.dashboard-table tbody td{padding:16px 14px;border-top:1px solid rgba(4,21,31,.06);color:#0f172a;font-size:.94rem;line-height:1.55;vertical-align:top;white-space:nowrap}
.order-cell,.order-head{display:flex;align-items:center;justify-content:space-between;gap:10px;min-width:52px}
.order-index{font-weight:600}
.status-dot{width:10px;height:10px;border-radius:999px;display:inline-block;box-shadow:0 0 0 3px transparent}
.status-dot.is-open{background:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.16)}
.status-dot.is-review{background:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.16)}
.status-dot.is-awarded{background:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,.16)}
.status-dot.is-completed{background:#16a34a;box-shadow:0 0 0 3px rgba(22,163,74,.16)}
.status-dot.is-closed{background:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.16)}
.status-dot.is-draft{background:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.16)}
.pill{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:0 12px;border-radius:10px;font-size:.8rem;font-weight:600;white-space:nowrap}
.pill-urgency.is-critical{background:rgba(239,68,68,.12);color:#dc2626}
.pill-urgency.is-high{background:rgba(249,115,22,.14);color:#c2410c}
.pill-urgency.is-normal{background:rgba(20,184,166,.1);color:#0f766e}
.pill-urgency.is-low{background:rgba(15,23,42,.06);color:#475569}
.actions-cell{display:flex;align-items:center;gap:8px}
.action-button{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:0;background:transparent;padding:0;text-decoration:none}
.action-button svg{width:17px;height:17px}
.action-view{color:#2563eb}
.action-edit{color:#16a34a}
.action-delete{color:#ef4444}
.action-disabled{color:#94a3b8;cursor:not-allowed}
.offer-link{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:0 12px;border:0;border-radius:10px;background:rgba(34,197,94,.12);color:#15803d;font-size:.8rem;font-weight:600}
.empty-card{margin-top:18px}
.table-footer{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:16px;margin-top:18px}
.footer-left,.footer-right{display:flex;align-items:center;gap:10px}
.footer-left span,.footer-center,.page-indicator{color:#475569;font-size:.92rem}
.footer-left select{min-height:38px;padding:0 12px;border:1px solid rgba(148,163,184,.42);border-radius:8px;background:#fff;color:#0f172a}
.footer-center{text-align:center}
.pager-button{min-height:38px;padding:0 14px;border:1px solid rgba(148,163,184,.42);border-radius:8px;background:#f8fafc;color:#475569;font-size:.9rem}
.pager-button:disabled{opacity:.55}
.delete-modal-backdrop{position:fixed;inset:0;z-index:1500;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(4,21,31,.58);backdrop-filter:blur(10px)}
.delete-modal{position:relative;width:min(620px,100%);padding:28px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff;box-shadow:0 30px 60px rgba(15,23,42,.16)}
.delete-modal-close{position:absolute;top:16px;right:16px;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff;color:#0f172a;font-size:1.45rem;line-height:1}
.delete-modal-copy{margin:12px 0 0;color:#64748b;font-size:.95rem;line-height:1.7}
.delete-modal-summary{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}
.delete-modal-summary span{display:inline-flex;align-items:center;min-height:34px;padding:0 12px;border-radius:10px;background:#f8fafc;color:#0f172a;font-size:.84rem;font-weight:600}
.delete-modal-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:24px}
.delete-cancel-button{background:#fff;border-color:rgba(148,163,184,.32);color:#0f172a;box-shadow:none}
.delete-confirm-button{background:#ef4444;border-color:#ef4444;color:#fff;box-shadow:0 12px 24px rgba(239,68,68,.18)}
@media (max-width: 900px){
    .table-toolbar{flex-direction:column;align-items:stretch}
    .toolbar-actions{flex-direction:column;align-items:stretch;margin-left:0}
    .toolbar-search{flex-direction:column;align-items:stretch}
    .toolbar-search input{width:100%}
    .table-footer{grid-template-columns:1fr}
    .footer-center{text-align:left}
}
@media (max-width: 720px){
    .surface-panel,.empty-card{padding:24px}
    .dashboard-table-wrap{display:none}
    .mobile-card-stack{display:grid;gap:16px;margin-top:16px}
    .mobile-record-card{display:grid;gap:16px;padding:18px;border:1px solid rgba(4,21,31,.08);border-radius:12px;background:#fff}
    .mobile-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
    .mobile-card-title-group{display:grid;gap:6px;min-width:0}
    .mobile-card-kicker{color:#64748b;font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
    .mobile-card-title{color:#0f172a;font-size:.98rem;font-weight:700;text-decoration:none;word-break:break-word}
    .mobile-status-pill{display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border-radius:999px;background:#f8fafc;color:#475569;font-size:.78rem;font-weight:700;white-space:nowrap}
    .mobile-card-grid{display:grid;grid-template-columns:1fr;gap:12px}
    .mobile-card-field{display:grid;gap:5px}
    .mobile-field-label{color:#64748b;font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em}
    .mobile-field-value{color:#0f172a;font-size:.92rem;line-height:1.55;word-break:break-word}
    .mobile-card-footer{padding-top:4px;border-top:1px solid rgba(226,232,240,.9)}
    .actions-cell{flex-wrap:wrap}
    .delete-modal{padding:24px 20px 20px}
    .delete-modal-actions{flex-direction:column}
}
</style>
