<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import SupplierDashboardShell from './Shell.vue';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
    incomingRequests: {
        type: Array,
        default: () => [],
    },
});

const searchQuery = ref('');
const rowsPerPage = ref(10);
const currentPage = ref(1);

const copy = {
    title: 'Supplier Dashboard',
    tabTitle: 'Incoming Requests',
    tabText: 'RFQs and service requests sent to you appear here.',
    searchPlaceholder: 'Search ref / port / country / service',
    search: 'Search',
    emptyTitle: 'No requests have reached you yet.',
    emptySearchTitle: 'No requests matched your search.',
    emptySearchText: 'Try a different keyword or clear the search and try again.',
    recordsPerPage: 'Records per page:',
    showing: 'Showing',
    of: 'of',
    records: 'records',
    prev: 'Prev',
    next: 'Next',
    offer: 'Submit Offer',
    continueDraft: 'Continue Draft',
    editOffer: 'Edit Offer',
    offerLocked: 'Offer unavailable',
    myOfferNotStarted: 'Not started',
    myOfferDraft: 'Draft saved',
    myOfferSubmitted: 'Submitted',
    table: {
        order: '#',
        statusMini: 'Status',
        referenceNo: 'Reference No',
        requestType: 'Request Type',
        country: 'Country',
        port: 'Port',
        requisitionDate: 'Requisition Date',
        dueDate: 'Due Date',
        priority: 'Priority',
        myOffer: 'My Offer',
        actions: 'Actions',
    },
    modal: {
        selectedCountries: 'Selected Countries',
        selectedPorts: 'Selected Ports',
        coverageCountries: 'Matched Service Countries',
        coveragePorts: 'Matched Service Ports',
        allListedPortsIn: 'All listed ports in',
        portsSelectedSuffix: 'ports selected',
        noCountries: 'No countries selected.',
        noPorts: 'No ports selected.',
        noCoverage: 'No matched service coverage found for this request.',
        close: 'Close',
    },
    spareParts: 'Spare Parts',
    serviceRequest: 'Service Request',
    privateRequest: 'Private Request',
    openStatus: 'Open',
    awardConfirmedStatus: 'Award Confirmed',
    draftStatus: 'Draft',
    closedStatus: 'Closed',
    cancelledStatus: 'Cancelled',
    critical: 'urgent',
    high: 'high',
    medium: 'normal',
    low: 'low',
};

const detailModal = ref(null);
const detailModalRequest = ref(null);
const portSelectionThreshold = 10;

const requestTypeLabel = (request) => {
    if (request?.is_private_request) {
        return copy.privateRequest;
    }

    return request?.request_type === 'service_request'
        ? copy.serviceRequest
        : copy.spareParts;
};

const requestStatusLabel = (status) => {
    const labels = {
        open: copy.openStatus,
        award_confirmed: copy.awardConfirmedStatus,
        draft: copy.draftStatus,
        closed: copy.closedStatus,
        cancelled: copy.cancelledStatus,
    };

    return labels[status] ?? status;
};

const priorityLabel = (priority) => {
    const labels = {
        critical: copy.critical,
        high: copy.high,
        medium: copy.medium,
        low: copy.low,
    };

    return labels[priority] ?? priority ?? '-';
};

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
    if (status === 'award_confirmed') return 'is-awarded';
    if (status === 'closed' || status === 'cancelled') return 'is-closed';
    return 'is-draft';
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

const flattenPortNames = (groups = []) => groups
    .flatMap((group) => (group?.ports ?? []).map((port) => port?.name).filter(Boolean));

const openDetailModal = (request, type) => {
    detailModalRequest.value = request;
    detailModal.value = type;
};

const closeDetailModal = () => {
    detailModalRequest.value = null;
    detailModal.value = null;
};

const detailModalTitle = computed(() => (
    detailModal.value === 'countries'
        ? copy.modal.selectedCountries
        : copy.modal.selectedPorts
));

const selectedRequestCountries = computed(() => detailModalRequest.value?.request_countries ?? []);
const selectedRequestPortGroups = computed(() => detailModalRequest.value?.request_ports_by_country ?? []);
const selectedRequestPortTotalsByCountry = computed(() => detailModalRequest.value?.request_port_totals_by_country ?? {});
const selectedCoverageCountries = computed(() => detailModalRequest.value?.matched_coverage_countries ?? []);
const selectedCoveragePortGroups = computed(() => detailModalRequest.value?.matched_coverage_ports_by_country ?? []);
const populatedCoveragePortGroups = computed(() => selectedCoveragePortGroups.value
    .filter((group) => (group?.ports ?? []).length > 0));

const canOpenCountryModal = (request) => (
    (request?.request_countries?.length ?? 0) > 0
    || (request?.matched_coverage_countries?.length ?? 0) > 0
);

const canOpenPortModal = (request) => (
    (request?.request_ports_by_country?.length ?? 0) > 0
    || (request?.matched_coverage_ports_by_country ?? []).some((group) => (group?.ports ?? []).length > 0)
);

const requestPortGroupSummary = (group) => {
    const selectedCount = group?.ports?.length ?? 0;
    const totalCount = Number(selectedRequestPortTotalsByCountry.value?.[group.country] ?? 0);

    if (totalCount > 0 && selectedCount === totalCount) {
        return `${copy.modal.allListedPortsIn} ${group.country}`;
    }

    if (selectedCount > portSelectionThreshold) {
        return `${selectedCount} ${copy.modal.portsSelectedSuffix}`;
    }

    return null;
};

const filteredRequests = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (!query) {
        return props.incomingRequests;
    }

    return props.incomingRequests.filter((request) => {
        const haystack = [
            request.reference_no,
            request.country_name,
            request.port_name,
            request.service_title,
            requestTypeLabel(request),
            ...(request.request_countries ?? []),
            ...flattenPortNames(request.request_ports_by_country ?? []),
            ...(request.matched_coverage_countries ?? []),
            ...flattenPortNames(request.matched_coverage_ports_by_country ?? []),
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(query);
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRequests.value.length / rowsPerPage.value)));

const paginatedRequests = computed(() => {
    const start = (currentPage.value - 1) * rowsPerPage.value;
    return filteredRequests.value.slice(start, start + rowsPerPage.value);
});

const showingFrom = computed(() => {
    if (!filteredRequests.value.length) return 0;
    return ((currentPage.value - 1) * rowsPerPage.value) + 1;
});

const showingTo = computed(() => {
    if (!filteredRequests.value.length) return 0;
    return Math.min(currentPage.value * rowsPerPage.value, filteredRequests.value.length);
});

const hasSearchQuery = computed(() => searchQuery.value.trim().length > 0);

const rowNumber = (index) => filteredRequests.value.length - (((currentPage.value - 1) * rowsPerPage.value) + index);

const changeRowsPerPage = (event) => {
    rowsPerPage.value = Number(event.target.value) || 10;
    currentPage.value = 1;
};

const goPrev = () => {
    currentPage.value = Math.max(1, currentPage.value - 1);
};

const goNext = () => {
    currentPage.value = Math.min(totalPages.value, currentPage.value + 1);
};

const myOfferLabel = (status) => {
    if (status === 'submitted') return copy.myOfferSubmitted;
    if (status === 'draft') return copy.myOfferDraft;
    return copy.myOfferNotStarted;
};

const offerActionLabel = (request) => {
    if (!request.response_allowed) return copy.offerLocked;
    if (request.my_offer_status === 'submitted') return copy.editOffer;
    if (request.my_offer_status === 'draft') return copy.continueDraft;
    return copy.offer;
};

const offerStatusTone = (status) => {
    if (status === 'submitted') return 'is-submitted';
    if (status === 'draft') return 'is-draft';
    return 'is-not-started';
};
</script>

<template>
    <SupplierDashboardShell :dashboard="dashboard" :title="copy.title" active-tab="incoming">
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

            <div v-if="paginatedRequests.length" class="dashboard-table-wrap">
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
                            <th>{{ copy.table.requestType }}</th>
                            <th>{{ copy.table.country }}</th>
                            <th>{{ copy.table.port }}</th>
                            <th>{{ copy.table.requisitionDate }}</th>
                            <th>{{ copy.table.dueDate }}</th>
                            <th>{{ copy.table.priority }}</th>
                            <th>{{ copy.table.myOffer }}</th>
                            <th>{{ copy.table.actions }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(request, index) in paginatedRequests" :key="request.id">
                            <td>
                                <div class="order-cell">
                                    <span class="order-index">{{ rowNumber(index) }}</span>
                                    <span class="status-dot" :class="statusTone(request.status)" :title="requestStatusLabel(request.status)"></span>
                                </div>
                            </td>
                            <td>
                                <Link :href="request.show_url" class="reference-link">
                                    {{ request.reference_no }}
                                </Link>
                            </td>
                            <td>{{ requestTypeLabel(request) }}</td>
                            <td class="scope-cell">
                                <button
                                    v-if="canOpenCountryModal(request)"
                                    type="button"
                                    class="scope-trigger"
                                    @click="openDetailModal(request, 'countries')"
                                >
                                    {{ request.country_name || '-' }}
                                </button>
                                <span v-else class="scope-main">{{ request.country_name || '-' }}</span>
                            </td>
                            <td class="scope-cell">
                                <button
                                    v-if="canOpenPortModal(request)"
                                    type="button"
                                    class="scope-trigger"
                                    @click="openDetailModal(request, 'ports')"
                                >
                                    {{ request.port_name || '-' }}
                                </button>
                                <span v-else class="scope-main">{{ request.port_name || '-' }}</span>
                            </td>
                            <td>{{ formatDate(request.requisition_date) }}</td>
                            <td>{{ formatDate(request.due_date) }}</td>
                            <td>
                                <span class="pill pill-urgency" :class="urgencyTone(request.priority)">
                                    {{ capitalizeLabel(priorityLabel(request.priority)) }}
                                </span>
                            </td>
                            <td>
                                <span class="pill my-offer-pill" :class="offerStatusTone(request.my_offer_status)">
                                    {{ myOfferLabel(request.my_offer_status) }}
                                </span>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <Link :href="request.show_url" class="action-button action-view" title="View">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </Link>
                                    <Link
                                        v-if="request.response_allowed"
                                        :href="request.offer_url"
                                        class="action-button action-offer"
                                        :title="offerActionLabel(request)"
                                    >
                                        <svg v-if="request.my_offer_status === 'not_started'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M22 2 11 13" />
                                            <path d="m22 2-7 20-4-9-9-4Z" />
                                        </svg>
                                        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 20h9" />
                                            <path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4Z" />
                                        </svg>
                                    </Link>
                                    <button
                                        v-else
                                        type="button"
                                        class="action-button action-disabled"
                                        :title="copy.offerLocked"
                                        disabled
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M22 2 11 13" />
                                            <path d="m22 2-7 20-4-9-9-4Z" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="paginatedRequests.length" class="mobile-card-stack">
                <article v-for="(request, index) in paginatedRequests" :key="`mobile-${request.id}`" class="mobile-record-card">
                    <div class="mobile-card-head">
                        <div class="mobile-card-title-group">
                            <span class="mobile-card-kicker">#{{ rowNumber(index) }}</span>
                            <Link :href="request.show_url" class="mobile-card-title">
                                {{ request.reference_no }}
                            </Link>
                        </div>
                        <span class="mobile-status-pill">
                            <span class="status-dot" :class="statusTone(request.status)"></span>
                            {{ requestStatusLabel(request.status) }}
                        </span>
                    </div>

                    <div class="mobile-card-grid">
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.requestType }}</span>
                            <span class="mobile-field-value">{{ requestTypeLabel(request) }}</span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.country }}</span>
                            <span class="mobile-field-value">
                                <button
                                    v-if="canOpenCountryModal(request)"
                                    type="button"
                                    class="scope-trigger"
                                    @click="openDetailModal(request, 'countries')"
                                >
                                    {{ request.country_name || '-' }}
                                </button>
                                <span v-else class="scope-main">{{ request.country_name || '-' }}</span>
                            </span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.port }}</span>
                            <span class="mobile-field-value">
                                <button
                                    v-if="canOpenPortModal(request)"
                                    type="button"
                                    class="scope-trigger"
                                    @click="openDetailModal(request, 'ports')"
                                >
                                    {{ request.port_name || '-' }}
                                </button>
                                <span v-else class="scope-main">{{ request.port_name || '-' }}</span>
                            </span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.requisitionDate }}</span>
                            <span class="mobile-field-value">{{ formatDate(request.requisition_date) }}</span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.dueDate }}</span>
                            <span class="mobile-field-value">{{ formatDate(request.due_date) }}</span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.priority }}</span>
                            <span class="mobile-field-value">
                                <span class="pill pill-urgency" :class="urgencyTone(request.priority)">
                                    {{ capitalizeLabel(priorityLabel(request.priority)) }}
                                </span>
                            </span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.myOffer }}</span>
                            <span class="mobile-field-value">
                                <span class="pill my-offer-pill" :class="offerStatusTone(request.my_offer_status)">
                                    {{ myOfferLabel(request.my_offer_status) }}
                                </span>
                            </span>
                        </div>
                    </div>

                    <div class="mobile-card-footer">
                        <div class="actions-cell">
                            <Link :href="request.show_url" class="action-button action-view" title="View">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </Link>
                            <Link
                                v-if="request.response_allowed"
                                :href="request.offer_url"
                                class="action-button action-offer"
                                :title="offerActionLabel(request)"
                            >
                                <svg v-if="request.my_offer_status === 'not_started'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 2 11 13" />
                                    <path d="m22 2-7 20-4-9-9-4Z" />
                                </svg>
                                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 20h9" />
                                    <path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4Z" />
                                </svg>
                            </Link>
                            <button
                                v-else
                                type="button"
                                class="action-button action-disabled"
                                :title="copy.offerLocked"
                                disabled
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 2 11 13" />
                                    <path d="m22 2-7 20-4-9-9-4Z" />
                                </svg>
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
                    {{ copy.showing }} {{ showingFrom }} - {{ showingTo }} {{ copy.of }} {{ filteredRequests.length }} {{ copy.records }}
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

        <div v-if="detailModal && detailModalRequest" class="detail-modal-backdrop" @click.self="closeDetailModal">
            <div class="detail-modal">
                <div class="detail-modal-head">
                    <h3 class="detail-modal-title">{{ detailModalTitle }}</h3>
                    <button type="button" class="detail-modal-close" @click="closeDetailModal">
                        {{ copy.modal.close }}
                    </button>
                </div>

                <div class="detail-modal-body">
                    <section class="detail-section">
                        <h4 class="detail-section-title">
                            {{ detailModal === 'countries' ? copy.modal.selectedCountries : copy.modal.selectedPorts }}
                        </h4>

                        <div v-if="detailModal === 'countries'">
                            <div v-if="selectedRequestCountries.length" class="modal-pill-list">
                                <span v-for="country in selectedRequestCountries" :key="country" class="modal-pill">
                                    {{ country }}
                                </span>
                            </div>
                            <p v-else class="detail-empty-copy">{{ copy.modal.noCountries }}</p>
                        </div>

                        <div v-else>
                            <div v-if="selectedRequestPortGroups.length" class="modal-port-stack">
                                <div v-for="group in selectedRequestPortGroups" :key="group.country" class="modal-port-group">
                                    <strong>{{ group.country }}</strong>
                                    <p v-if="requestPortGroupSummary(group)" class="detail-empty-copy">
                                        {{ requestPortGroupSummary(group) }}
                                    </p>
                                    <div v-else-if="(group.ports ?? []).length" class="modal-pill-list">
                                        <span v-for="port in group.ports" :key="`${group.country}-${port.id ?? port.name}`" class="modal-pill">
                                            {{ port.unlocode ? `${port.name} (${port.unlocode})` : port.name }}
                                        </span>
                                    </div>
                                    <p v-else class="detail-empty-copy">{{ copy.modal.noPorts }}</p>
                                </div>
                            </div>
                            <p v-else class="detail-empty-copy">{{ copy.modal.noPorts }}</p>
                        </div>
                    </section>

                    <section class="detail-section detail-section-secondary">
                        <h4 class="detail-section-title">
                            {{ detailModal === 'countries' ? copy.modal.coverageCountries : copy.modal.coveragePorts }}
                        </h4>

                        <div v-if="detailModal === 'countries'">
                            <div v-if="selectedCoverageCountries.length" class="modal-pill-list">
                                <span v-for="country in selectedCoverageCountries" :key="country" class="modal-pill modal-pill-secondary">
                                    {{ country }}
                                </span>
                            </div>
                            <p v-else class="detail-empty-copy">{{ copy.modal.noCoverage }}</p>
                        </div>

                        <div v-else>
                            <div v-if="populatedCoveragePortGroups.length" class="modal-port-stack">
                                <div v-for="group in populatedCoveragePortGroups" :key="group.country" class="modal-port-group">
                                    <strong>{{ group.country }}</strong>
                                    <div v-if="(group.ports ?? []).length" class="modal-pill-list">
                                        <span v-for="port in group.ports" :key="`${group.country}-${port.id ?? port.name}`" class="modal-pill modal-pill-secondary">
                                            {{ port.unlocode ? `${port.name} (${port.unlocode})` : port.name }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="detail-empty-copy">{{ copy.modal.noCoverage }}</p>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </SupplierDashboardShell>
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
.dashboard-table{width:100%;border-collapse:collapse;min-width:1120px}
.dashboard-table thead th{padding:16px 14px;background:#f4f7fb;color:#0f172a;font-size:.82rem;font-weight:700;text-align:left;white-space:nowrap}
.dashboard-table tbody td{padding:16px 14px;border-top:1px solid rgba(4,21,31,.06);color:#0f172a;font-size:.94rem;line-height:1.55;vertical-align:top;white-space:nowrap}
.scope-cell{white-space:normal;min-width:140px}
.scope-main{display:block;color:#0f172a;font-weight:400;line-height:1.45}
.scope-trigger{appearance:none;border:0;background:transparent;padding:0;color:#0f172a;font:inherit;font-weight:400;line-height:1.45;text-align:left;cursor:pointer;text-decoration:underline;text-decoration-color:rgba(15,23,42,.22);text-underline-offset:4px}
.scope-trigger:hover{text-decoration-color:rgba(15,23,42,.55)}
.reference-link{color:inherit;text-decoration:none}
.reference-link:hover{text-decoration:underline;text-underline-offset:3px}
.order-cell,.order-head{display:flex;align-items:center;justify-content:space-between;gap:10px;min-width:52px}
.order-cell{font-weight:600}
.order-index{color:#0f172a}
.status-dot{width:10px;height:10px;border-radius:999px;display:inline-block;box-shadow:0 0 0 3px transparent}
.status-dot.is-open{background:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.16)}
.status-dot.is-awarded{background:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,.16)}
.status-dot.is-closed{background:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.16)}
.status-dot.is-draft{background:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.16)}
.pill{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:0 12px;border-radius:10px;font-size:.82rem;font-weight:600;white-space:nowrap}
.pill-urgency.is-critical{background:rgba(239,68,68,.12);color:#dc2626}
.pill-urgency.is-high{background:rgba(249,115,22,.14);color:#c2410c}
.pill-urgency.is-normal{background:rgba(20,184,166,.1);color:#0f766e}
.pill-urgency.is-low{background:rgba(15,23,42,.06);color:#475569}
.my-offer-pill.is-not-started{background:rgba(15,23,42,.06);color:#475569}
.my-offer-pill.is-draft{background:rgba(245,158,11,.14);color:#b45309}
.my-offer-pill.is-submitted{background:rgba(34,197,94,.12);color:#15803d}
.actions-cell{display:flex;align-items:center;gap:12px}
.action-button{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:0;background:transparent;padding:0;text-decoration:none}
.action-button svg{width:17px;height:17px}
.action-view{color:#2563eb}
.action-offer{color:#0f766e}
.action-disabled{color:#94a3b8;cursor:not-allowed}
.empty-card{margin-top:18px;padding:32px 36px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.78);box-shadow:0 24px 44px rgba(15,23,42,.08)}
.empty-card strong{color:#0f172a;font-size:1rem;font-weight:600}
.table-footer{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:16px;margin-top:18px}
.footer-left,.footer-right{display:flex;align-items:center;gap:12px;color:#64748b;font-size:.9rem}
.footer-left select{min-height:38px;padding:0 12px;border:1px solid rgba(148,163,184,.32);border-radius:10px;background:#fff;color:#0f172a;font-size:.9rem}
.footer-center{color:#64748b;font-size:.9rem;text-align:center}
.pager-button{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 14px;border:1px solid rgba(148,163,184,.3);border-radius:10px;background:#fff;color:#0f172a;font-size:.88rem;font-weight:600}
.pager-button:disabled{color:#94a3b8;background:#f8fafc;cursor:not-allowed}
.page-indicator{color:#0f172a;font-size:.9rem;font-weight:600}
.detail-modal-backdrop{position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(15,23,42,.42);backdrop-filter:blur(6px)}
.detail-modal{width:min(760px,100%);max-height:min(80vh,720px);overflow:auto;border-radius:14px;border:1px solid rgba(4,21,31,.08);background:#fff;box-shadow:0 24px 44px rgba(15,23,42,.18)}
.detail-modal-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:22px 24px 16px;border-bottom:1px solid rgba(4,21,31,.08)}
.detail-modal-title{margin:0;font-size:1.04rem;font-weight:700;color:#0f172a}
.detail-modal-close{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 14px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff;color:#04151f;font-size:.86rem;font-weight:600}
.detail-modal-body{padding:22px 24px 24px;display:grid;gap:18px}
.detail-section{display:grid;gap:12px}
.detail-section-secondary{padding-top:18px;border-top:1px solid rgba(226,232,240,.9)}
.detail-section-title{margin:0;color:#04151f;font-size:.95rem;font-weight:700}
.detail-empty-copy{margin:0;color:#64748b;font-size:.88rem;line-height:1.7}
.modal-pill-list{display:flex;flex-wrap:wrap;gap:10px}
.modal-pill{display:inline-flex;align-items:center;min-height:34px;padding:0 12px;border-radius:10px;background:#f8fafc;color:#334155;font-size:.84rem;font-weight:500}
.modal-pill-secondary{background:#eff6ff;border-color:rgba(37,99,235,.18);color:#1d4ed8}
.modal-port-stack{display:flex;flex-direction:column;gap:18px}
.modal-port-group{display:flex;flex-direction:column;gap:10px}
.modal-port-group strong{color:#04151f;font-size:.95rem}
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
    .detail-modal-head{padding:20px 20px 16px}
    .detail-modal-body{padding:18px 20px 20px}
}
</style>
