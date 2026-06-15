<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SupplierDashboardShell from './Shell.vue';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
});

const copy = {
    title: 'Supplier Dashboard',
    countries: 'Countries',
    ports: 'Ports',
    categories: 'Categories',
    subcategories: 'Subcategories',
    brands: 'Brands',
    registrationDate: 'Registration Date',
    status: 'Status',
    action: 'Action',
    business: 'My Business',
    active: 'Active',
    pending: 'Pending',
    rejected: 'Revision required',
    open: 'Open',
    edit: 'Edit',
    waitingReviewText: 'You cannot submit a new edit until this update request is reviewed.',
};

const statusLabel = computed(() => {
    if (props.dashboard.approval_status === 'rejected') return copy.rejected;
    if (props.dashboard.approval_status === 'pending') return copy.pending;
    return copy.active;
});

const statusClass = computed(() => {
    if (props.dashboard.approval_status === 'rejected') return 'is-rejected';
    if (props.dashboard.approval_status === 'pending') return 'is-pending';
    return 'is-approved';
});

const canEdit = computed(() => props.dashboard.update_request?.status !== 'pending');

const coverageSummary = computed(() => [
    {
        label: copy.countries,
        value: Number(props.dashboard.stats?.countries ?? 0),
    },
    {
        label: copy.ports,
        value: Number(props.dashboard.stats?.ports ?? 0),
    },
    {
        label: copy.categories,
        value: Number(props.dashboard.stats?.categories ?? 0),
    },
    {
        label: copy.subcategories,
        value: Number(props.dashboard.stats?.subcategories ?? 0),
    },
    {
        label: copy.brands,
        value: Number(props.dashboard.stats?.brands ?? 0),
    },
]);

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
</script>

<template>
    <SupplierDashboardShell :dashboard="dashboard" :title="copy.title" active-tab="business">
        <section class="surface-panel table-panel">
            <div class="dashboard-table-wrap">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>{{ copy.business }}</th>
                            <th>Coverage</th>
                            <th>{{ copy.registrationDate }}</th>
                            <th>{{ copy.status }}</th>
                            <th>{{ copy.action }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="module-cell">
                                    <strong>{{ dashboard.company_name || '-' }}</strong>
                                </div>
                            </td>
                            <td>
                                <div class="coverage-cell">
                                    <div v-for="item in coverageSummary" :key="item.label" class="coverage-pill">
                                        <span class="coverage-label">{{ item.label }}</span>
                                        <strong class="coverage-value">{{ item.value }}</strong>
                                    </div>
                                </div>
                            </td>
                            <td>{{ formatDate(dashboard.registered_at) }}</td>
                            <td>
                                <span class="pill pill-status" :class="statusClass">{{ statusLabel }}</span>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <Link class="action-button action-view" :href="dashboard.public_profile_url" :title="copy.open">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </Link>
                                    <Link v-if="canEdit && dashboard.edit_url" class="action-button action-edit" :href="dashboard.edit_url" :title="copy.edit">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 20h9" />
                                            <path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                                        </svg>
                                    </Link>
                                    <button v-else type="button" class="action-button action-disabled" :title="copy.waitingReviewText" disabled>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 20h9" />
                                            <path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <article class="mobile-business-card">
                <div class="mobile-card-head">
                    <div class="mobile-card-title-group">
                        <span class="mobile-card-kicker">{{ copy.business }}</span>
                        <strong class="mobile-card-title">{{ dashboard.company_name || '-' }}</strong>
                    </div>
                    <span class="pill pill-status" :class="statusClass">{{ statusLabel }}</span>
                </div>

                <div class="mobile-card-grid">
                    <div class="mobile-card-field">
                        <span class="mobile-field-label">Coverage</span>
                        <div class="coverage-cell">
                            <div v-for="item in coverageSummary" :key="item.label" class="coverage-pill">
                                <span class="coverage-label">{{ item.label }}</span>
                                <strong class="coverage-value">{{ item.value }}</strong>
                            </div>
                        </div>
                    </div>
                    <div class="mobile-card-field">
                        <span class="mobile-field-label">{{ copy.registrationDate }}</span>
                        <span class="mobile-field-value">{{ formatDate(dashboard.registered_at) }}</span>
                    </div>
                </div>

                <div class="mobile-card-footer">
                    <div class="actions-cell">
                        <Link class="action-button action-view" :href="dashboard.public_profile_url" :title="copy.open">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </Link>
                        <Link v-if="canEdit && dashboard.edit_url" class="action-button action-edit" :href="dashboard.edit_url" :title="copy.edit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 20h9" />
                                <path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                            </svg>
                        </Link>
                        <button v-else type="button" class="action-button action-disabled" :title="copy.waitingReviewText" disabled>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 20h9" />
                                <path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </article>
        </section>
    </SupplierDashboardShell>
</template>

<style scoped>
.surface-panel{padding:40px 36px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.78);box-shadow:0 24px 44px rgba(15,23,42,.08)}
.dashboard-table-wrap{overflow-x:auto}
.mobile-business-card{display:none}
.dashboard-table{width:100%;border-collapse:collapse;min-width:1080px}
.dashboard-table thead th{padding:16px 14px;background:#f4f7fb;color:#0f172a;font-size:.82rem;font-weight:700;text-align:left;white-space:nowrap}
.dashboard-table tbody td{padding:24px 14px;border-top:1px solid rgba(4,21,31,.06);color:#0f172a;font-size:.94rem;line-height:1.55;vertical-align:middle;white-space:nowrap}
.module-cell{display:grid;gap:4px}
.module-cell strong{color:#0f172a;font-size:.94rem;font-weight:400;line-height:1.55}
.coverage-cell{display:flex;flex-wrap:wrap;align-items:center;gap:10px 12px;white-space:normal}
.coverage-pill{display:inline-flex;align-items:center;gap:8px;min-height:34px;padding:0 12px;border-radius:10px;background:#f8fafc;border:1px solid rgba(148,163,184,.22)}
.coverage-label{color:#64748b;font-size:.82rem;font-weight:600}
.coverage-value{color:#0f172a;font-size:.9rem;font-weight:700;line-height:1}
.pill{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:0 12px;border-radius:10px;font-size:.82rem;font-weight:600;white-space:nowrap}
.pill-status.is-approved{background:#eefaf3;color:#0b7a52}
.pill-status.is-pending{background:#eff6ff;color:#2563eb}
.pill-status.is-rejected{background:#fff7ed;color:#c2410c}
.actions-cell{display:flex;align-items:center;gap:10px;white-space:nowrap}
.action-button{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:0;background:transparent;padding:0;text-decoration:none}
.action-button svg{width:17px;height:17px}
.action-view{color:#2563eb}
.action-edit{color:#16a34a}
.action-disabled{color:#94a3b8;cursor:not-allowed}
@media (max-width: 720px){
    .surface-panel{padding:24px 20px}
    .dashboard-table-wrap{display:none}
    .mobile-business-card{display:grid;gap:16px;padding:18px;border:1px solid rgba(4,21,31,.08);border-radius:12px;background:#fff}
    .mobile-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
    .mobile-card-title-group{display:grid;gap:6px;min-width:0}
    .mobile-card-kicker{color:#64748b;font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
    .mobile-card-title{color:#0f172a;font-size:.98rem;line-height:1.45;word-break:break-word}
    .mobile-card-grid{display:grid;grid-template-columns:1fr;gap:12px}
    .mobile-card-field{display:grid;gap:8px}
    .mobile-field-label{color:#64748b;font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em}
    .mobile-field-value{color:#0f172a;font-size:.92rem;line-height:1.55}
    .mobile-card-footer{padding-top:4px;border-top:1px solid rgba(226,232,240,.9)}
    .actions-cell{flex-wrap:wrap}
}
</style>
