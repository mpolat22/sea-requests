<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    records: { type: Array, required: true },
    meta: { type: Object, required: true },
    filters: { type: Object, required: true },
    copy: { type: Object, required: true },
    roleLabel: { type: Function, required: true },
    verificationLabel: { type: Function, required: true },
    statusLabel: { type: Function, required: true },
    businessStatusClass: { type: Function, required: true },
});

const emit = defineEmits(['view', 'edit', 'delete']);

const pageContext = usePage();
const search = ref(props.filters.search ?? '');
const pageNumber = ref(props.meta.current_page ?? 1);
let searchTimer = null;

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
        tab: 'users',
        user_search: search.value,
        user_page: pageNumber.value,
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
    refresh({ user_page: value });
};

watch(() => props.filters, (value) => {
    search.value = value.search ?? '';
}, { deep: true });

watch(() => props.meta.current_page, (value) => {
    pageNumber.value = value ?? 1;
});

watch(search, () => {
    pageNumber.value = 1;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        refresh({ user_page: 1 });
    }, 250);
});
</script>

<template>
    <section class="surface-panel table-panel">
        <div class="table-toolbar">
            <div class="table-intro">
                <h2 class="directory-section-title">{{ copy.usersTitle }}</h2>
                <p class="section-copy">Review and manage buyer and supplier user accounts from one admin table.</p>
            </div>

            <div class="toolbar-search">
                <input
                    v-model="search"
                    type="search"
                    :placeholder="copy.userSearchPlaceholder"
                >
                <button type="button" class="toolbar-button toolbar-button-primary" @click="refresh({ user_page: 1 })">
                    Search
                </button>
            </div>
        </div>

        <div class="dashboard-table-wrap">
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ copy.userName }}</th>
                        <th>{{ copy.email }}</th>
                        <th>Registered At</th>
                        <th>{{ copy.role }}</th>
                        <th>{{ copy.verification }}</th>
                        <th>{{ copy.status }}</th>
                        <th>{{ copy.action }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(user, index) in records" :key="`user-${user.id}`">
                        <td class="order-index-cell">{{ rowNumber(index) }}</td>
                        <td>
                            <div class="identity-stack">
                                <span class="identity-primary">{{ user.name }}</span>
                                <span v-if="user.company_name && user.company_name !== user.name" class="identity-secondary">{{ user.company_name }}</span>
                            </div>
                        </td>
                        <td>{{ user.email }}</td>
                        <td>{{ formatDate(user.created_at) }}</td>
                        <td>
                            <span class="soft-pill">{{ roleLabel(user.role) }}</span>
                        </td>
                        <td>
                            <span class="soft-pill" :class="{ 'is-warning': !user.email_verified_at }">
                                {{ verificationLabel(user) }}
                            </span>
                        </td>
                        <td>
                            <span class="status-pill" :class="businessStatusClass(user)">
                                {{ statusLabel(user.approval_status) }}
                            </span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <button type="button" class="action-button" :title="copy.view" @click="emit('view', user)">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                                </button>
                                <button type="button" class="action-button" :title="copy.edit" @click="emit('edit', user)">
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
.dashboard-table-wrap{margin-top:16px;overflow-x:auto}
.dashboard-table{width:100%;border-collapse:collapse;min-width:980px}
.dashboard-table thead th{padding:16px 14px;background:#f4f7fb;color:#0f172a;font-size:.82rem;font-weight:700;text-align:left;white-space:nowrap}
.dashboard-table tbody td{padding:16px 14px;border-top:1px solid rgba(4,21,31,.06);color:#0f172a;font-size:.94rem;line-height:1.55;vertical-align:top}
.order-index-cell{font-weight:600;color:#0f172a}
.identity-stack{display:grid;gap:5px}
.identity-primary{color:#0f172a;font-size:.94rem;font-weight:500;line-height:1.45}
.identity-secondary{color:#64748b;font-size:.88rem;line-height:1.45}
.soft-pill,.status-pill{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:0 12px;border-radius:10px;font-size:.8rem;font-weight:600;white-space:nowrap}
.soft-pill{background:rgba(15,23,42,.06);color:#475569}
.soft-pill.is-warning{background:rgba(249,115,22,.14);color:#c2410c}
.status-pill{background:rgba(34,197,94,.12);color:#15803d}
.status-pill.is-pending{background:rgba(59,130,246,.12);color:#2563eb}
.status-pill.is-rejected{background:rgba(239,68,68,.12);color:#dc2626}
.actions-cell{display:flex;align-items:center;justify-content:flex-start;gap:5px;flex-wrap:nowrap;white-space:nowrap}
.action-button{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border:0;background:transparent;padding:0;color:#0f172a;flex:0 0 24px}
.action-button svg{width:16px;height:16px;flex:0 0 16px}
.action-button-danger{color:#ef4444}
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
    .pager{justify-content:space-between}
}
</style>
