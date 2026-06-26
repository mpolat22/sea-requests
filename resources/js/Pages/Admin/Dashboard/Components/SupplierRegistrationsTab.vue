<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    records: { type: Array, required: true },
    meta: { type: Object, required: true },
    filters: { type: Object, required: true },
    counts: { type: Object, required: true },
    copy: { type: Object, required: true },
    documentLabel: { type: Function, required: true },
    businessStatusLabel: { type: Function, required: true },
    businessStatusClass: { type: Function, required: true },
    removalReasonLabel: { type: Function, required: true },
    updateFieldText: { type: Function, required: true },
});

const emit = defineEmits([
    'open-status',
    'open-removal',
    'open-feedback',
    'open-update-diff',
    'open-mail-history',
    'view',
    'edit',
    'delete',
]);

const pageContext = usePage();
const activeFilter = ref(props.filters.filter ?? 'all');
const search = ref(props.filters.search ?? '');
const pageNumber = ref(props.meta.current_page ?? 1);
let searchTimer = null;

const filterOptions = computed(() => [
    { key: 'all', label: props.copy.filterAll, count: props.counts.all ?? 0 },
    { key: 'pending', label: props.copy.filterPending, count: props.counts.pending ?? 0 },
    { key: 'approved', label: props.copy.filterApproved, count: props.counts.approved ?? 0 },
    { key: 'rejected', label: props.copy.filterRejected, count: props.counts.rejected ?? 0 },
    { key: 'update-pending', label: props.copy.filterUpdatePending, count: props.counts['update-pending'] ?? 0 },
    { key: 'removal', label: props.copy.filterRemoval, count: props.counts.removal ?? 0 },
]);

const currentPath = computed(() => pageContext.url.split('?')[0] || '/dashboard/admin');
const currentQuery = computed(() => {
    const params = new URLSearchParams(pageContext.url.split('?')[1] ?? '');
    return Object.fromEntries(params.entries());
});

const totalRecords = computed(() => Number(props.meta.total ?? props.records.length ?? 0));
const paginationLabel = computed(() => {
    const start = props.meta.from ?? 0;
    const end = props.meta.to ?? 0;

    return `${start}-${end} / ${totalRecords.value}`;
});

const rowNumber = (index) => totalRecords.value - (((pageNumber.value - 1) * 10) + index);

const canShowVerificationMailHistory = (user) => (
    user?.role === 'seller'
    && user?.approval_status !== 'approved'
);

const formatDate = (value) => {
    if (!value) return '-';

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

const refresh = (overrides = {}) => {
    router.get(currentPath.value, {
        ...currentQuery.value,
        tab: 'businesses',
        business_filter: activeFilter.value,
        business_search: search.value,
        business_page: pageNumber.value,
        ...overrides,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        only: ['activeTab', 'userTable', 'businessTable'],
    });
};

const changePage = (value) => {
    if (value < 1 || value > Number(props.meta.last_page ?? 1)) {
        return;
    }

    pageNumber.value = value;
    refresh({ business_page: value });
};

watch(() => props.filters, (value) => {
    activeFilter.value = value.filter ?? 'all';
    search.value = value.search ?? '';
}, { deep: true });

watch(() => props.meta.current_page, (value) => {
    pageNumber.value = value ?? 1;
});

watch(activeFilter, () => {
    pageNumber.value = 1;
    refresh({ business_filter: activeFilter.value, business_page: 1 });
});

watch(search, () => {
    pageNumber.value = 1;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        refresh({ business_page: 1 });
    }, 250);
});
</script>

<template>
    <section class="surface-panel table-panel">
        <div class="table-toolbar">
            <div class="table-intro">
                <h2 class="directory-section-title">{{ copy.businessesTitle }}</h2>
                <p class="section-copy">Track supplier verification, update requests, removals, and profile actions from one admin table.</p>
            </div>

            <div class="toolbar-search">
                <input
                    v-model="search"
                    type="search"
                    :placeholder="copy.businessSearchPlaceholder"
                >
                <button type="button" class="toolbar-button toolbar-button-primary" @click="refresh({ business_page: 1 })">
                    Search
                </button>
            </div>
        </div>

        <div class="subfilter-grid">
            <button
                v-for="filter in filterOptions"
                :key="filter.key"
                type="button"
                class="subfilter-card"
                :class="{ active: activeFilter === filter.key }"
                @click="activeFilter = filter.key"
            >
                <span class="subfilter-label">{{ filter.label }}</span>
                <strong class="subfilter-count">{{ filter.count }}</strong>
            </button>
        </div>

        <div class="dashboard-table-wrap">
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ copy.company }}</th>
                        <th>Registered At</th>
                        <th>{{ copy.documents }}</th>
                        <th>{{ copy.status }}</th>
                        <th>{{ copy.action }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(user, index) in records" :key="`business-${user.id}`">
                        <td class="order-index-cell">{{ rowNumber(index) }}</td>
                        <td>
                            <div class="identity-stack">
                                <button type="button" class="identity-primary-button" @click="emit('view', user)">
                                    <span class="identity-primary">{{ user.company_name || user.name }}</span>
                                </button>
                                <span v-if="user.company_name && user.company_name !== user.name" class="identity-secondary">{{ user.name }}</span>
                                <small v-if="user.seller_removal_requested_at" class="meta-note is-removal">
                                    {{ copy.removalRequest }}: {{ removalReasonLabel(user) }}
                                </small>
                                <small v-if="user.seller_rejected_at" class="meta-note is-rejected">
                                    {{ copy.feedbackAvailable }}
                                </small>
                                <small v-if="user.has_pending_update_request" class="meta-note is-pending">
                                    {{ copy.updateRequest }}: {{ updateFieldText(user) }}
                                </small>
                                <small v-if="user.seller_update_request_status === 'rejected'" class="meta-note is-rejected">
                                    {{ copy.updateRejected }}
                                </small>
                            </div>
                        </td>
                        <td>{{ formatDate(user.created_at) }}</td>
                        <td>
                            <span class="soft-pill" :class="{ 'is-warning': user.role === 'seller' && !user.seller_verification_submitted_at }">
                                {{ documentLabel(user) }}
                            </span>
                        </td>
                        <td>
                            <span class="status-pill" :class="businessStatusClass(user)">
                                {{ businessStatusLabel(user) }}
                            </span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <button type="button" class="action-button action-button-status" :title="copy.status" @click="emit('open-status', user)">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11.5 11 13.5 15 9.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                                </button>
                                <button
                                    v-if="user.seller_removal_requested_at"
                                    type="button"
                                    class="action-button action-button-danger-soft"
                                    :title="copy.removalRequest"
                                    @click="emit('open-removal', user)"
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 7h12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M9 7V4h6v3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8 10v7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 10v7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M16 10v7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M7 7l1 13h8l1-13" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                                </button>
                                <button
                                    v-if="user.seller_rejected_at || user.seller_update_request_status === 'rejected'"
                                    type="button"
                                    class="action-button action-button-warning"
                                    :title="copy.reviewFeedback"
                                    @click="emit('open-feedback', user)"
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="16.5" r="1" fill="currentColor"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                                </button>
                                <button
                                    v-if="(user.update_changed_fields?.length ?? 0) > 0"
                                    type="button"
                                    class="action-button action-button-info"
                                    :title="copy.reviewUpdate"
                                    @click="emit('open-update-diff', user)"
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M4 12h7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M4 17h10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="m15 13 2 2 4-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                                <button
                                    v-if="canShowVerificationMailHistory(user)"
                                    type="button"
                                    class="action-button action-button-info"
                                    :title="copy.reviewVerificationMailHistory"
                                    @click="emit('open-mail-history', user)"
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m4 7 8 6 8-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 10v7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 7.5h.01" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                                </button>
                                <Link v-if="user.edit_url" class="action-button" :title="copy.edit" :href="user.edit_url">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10.5-10.5-4-4L4 16v4Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m13.5 6.5 4 4" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                                </Link>
                                <button v-else type="button" class="action-button" :title="copy.edit" @click="emit('edit', user)">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10.5-10.5-4-4L4 16v4Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m13.5 6.5 4 4" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                                </button>
                                <button type="button" class="action-button action-button-danger" :title="copy.delete" @click="emit('delete', user)">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M9 7V4h6v3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M7 7l1 13h8l1-13" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <p class="table-meta">{{ paginationLabel }}</p>
            <div class="pager">
                <button type="button" class="pager-button" :disabled="pageNumber === 1" @click="changePage(pageNumber - 1)">
                    Prev
                </button>
                <span class="page-indicator">{{ pageNumber }} / {{ meta.last_page ?? 1 }}</span>
                <button type="button" class="pager-button" :disabled="pageNumber >= (meta.last_page ?? 1)" @click="changePage(pageNumber + 1)">
                    Next
                </button>
            </div>
        </div>
    </section>
</template>

<style scoped>
.surface-panel{padding:32px 36px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.78);box-shadow:0 24px 44px rgba(15,23,42,.08)}
.table-panel{display:grid;gap:18px}
.table-toolbar{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}
.table-intro{display:grid;gap:8px}
.table-intro :deep(.directory-section-title){margin:0}
.section-copy{margin:0;color:#64748b;font-size:.9rem;line-height:1.7;max-width:72ch}
.toolbar-search{display:flex;align-items:center;gap:10px;margin-left:auto;flex-wrap:wrap}
.toolbar-search input{min-height:46px;padding:0 14px;border:1px solid rgba(148,163,184,.38);border-radius:8px;background:#fff;color:#0f172a;font-size:.92rem}
.toolbar-search input{width:290px;max-width:100%}
.toolbar-button{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border-radius:10px;border:1px solid transparent;font-size:.92rem;font-weight:600;cursor:pointer}
.toolbar-button-primary{background:#2563eb;border-color:#2563eb;color:#fff;box-shadow:0 12px 24px rgba(37,99,235,.18)}
.subfilter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
.subfilter-card{display:grid;gap:8px;padding:18px;border:1px solid rgba(4,21,31,.08);border-radius:12px;background:#fff;text-align:left;transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease}
.subfilter-card:hover{border-color:rgba(37,99,235,.18);box-shadow:0 16px 30px rgba(15,23,42,.06);transform:translateY(-1px)}
.subfilter-card.active{border-color:#2563eb;background:#2563eb;box-shadow:0 12px 24px rgba(37,99,235,.18)}
.subfilter-label{color:#64748b;font-size:.8rem;font-weight:600;line-height:1.4}
.subfilter-count{color:#0f172a;font-size:1.35rem;font-weight:700;line-height:1}
.subfilter-card.active .subfilter-label,.subfilter-card.active .subfilter-count{color:#fff}
.dashboard-table-wrap{margin-top:16px;overflow-x:auto}
.dashboard-table{width:100%;border-collapse:collapse;min-width:1080px}
.dashboard-table thead th{padding:16px 14px;background:#f4f7fb;color:#0f172a;font-size:.82rem;font-weight:700;text-align:left;white-space:nowrap}
.dashboard-table tbody td{padding:16px 14px;border-top:1px solid rgba(4,21,31,.06);color:#0f172a;font-size:.94rem;line-height:1.55;vertical-align:top}
.order-index-cell{font-weight:600;color:#0f172a}
.identity-stack{display:grid;gap:5px}
.identity-primary-button{display:inline-flex;align-items:flex-start;justify-content:flex-start;width:fit-content;padding:0;border:0;background:transparent;cursor:pointer;text-align:left}
.identity-primary{color:#0f172a;font-size:.94rem;font-weight:500;line-height:1.45}
.identity-secondary{color:#64748b;font-size:.88rem;line-height:1.45}
.identity-stack small{color:#475569}
.soft-pill,.status-pill{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:0 12px;border-radius:10px;font-size:.8rem;font-weight:600;white-space:nowrap}
.soft-pill{background:rgba(15,23,42,.06);color:#475569}
.soft-pill.is-warning{background:rgba(249,115,22,.14);color:#c2410c}
.status-pill{background:rgba(34,197,94,.12);color:#15803d}
.status-pill.is-pending{background:rgba(59,130,246,.12);color:#2563eb}
.status-pill.is-rejected{background:rgba(239,68,68,.12);color:#dc2626}
.actions-cell{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.action-button{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:0;background:transparent;padding:0;color:#0f172a;text-decoration:none}
.action-button svg{width:17px;height:17px;flex:0 0 17px}
.action-button-danger{color:#ef4444}
.action-button-danger-soft{color:#b91c1c}
.action-button-status{color:#2563eb}
.action-button-info{color:#4f46e5}
.action-button-warning{color:#c2410c}
.meta-note{display:inline-flex;align-items:center;width:fit-content;padding:5px 10px;border-radius:10px;font-weight:600;font-size:.78rem}
.meta-note.is-pending{background:rgba(59,130,246,.12);color:#1d4ed8 !important}
.meta-note.is-removal{background:rgba(239,68,68,.12);color:#b91c1c !important}
.meta-note.is-rejected{background:rgba(249,115,22,.14);color:#c2410c !important}
.table-footer{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-top:18px}
.table-meta{margin:0;color:#475569;font-size:.92rem}
.pager{display:flex;align-items:center;gap:10px}
.pager-button{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 14px;border:1px solid rgba(148,163,184,.42);border-radius:8px;background:#f8fafc;color:#475569;font-size:.9rem;font-weight:600;cursor:pointer}
.pager-button:disabled{opacity:.55;cursor:not-allowed}
.page-indicator{min-width:64px;text-align:center;color:#0f172a;font-size:.9rem;font-weight:600}
@media (max-width: 900px){
    .table-toolbar{flex-direction:column;align-items:stretch}
    .toolbar-search{margin-left:0}
    .table-footer{align-items:stretch}
}
@media (max-width: 720px){
    .surface-panel{padding:24px}
    .toolbar-search{width:100%}
    .toolbar-search input,.toolbar-button{width:100%}
    .subfilter-grid{grid-template-columns:1fr 1fr}
    .pager{justify-content:space-between}
}
</style>
