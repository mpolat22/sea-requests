<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '../../../Layouts/MainLayout.vue';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
    title: {
        type: String,
        default: 'Admin Dashboard',
    },
    activeTab: {
        type: String,
        default: 'businesses',
    },
    showTabs: {
        type: Boolean,
        default: true,
    },
    showIntro: {
        type: Boolean,
        default: true,
    },
});

const copy = computed(() => ({
    eyebrow: 'Control center',
    subtitle: 'Monitor users, supplier verification, RFQs, and order workflow from one admin workspace.',
    usersTab: 'Users',
    businessesTab: 'Supplier Companies',
    rfqsTab: 'RFQs',
    ordersTab: 'Orders',
    outreachTab: 'Outreach',
}));

const usersTabLabel = computed(() => `${copy.value.usersTab}(${Number(props.dashboard.navigation?.users_count ?? 0)})`);
const businessesTabLabel = computed(() => `${copy.value.businessesTab}(${Number(props.dashboard.navigation?.businesses_count ?? 0)})`);
const rfqsTabLabel = computed(() => `${copy.value.rfqsTab}(${Number(props.dashboard.navigation?.rfqs_count ?? 0)})`);
const ordersTabLabel = computed(() => `${copy.value.ordersTab}(${Number(props.dashboard.navigation?.orders_count ?? 0)})`);
const outreachTabLabel = computed(() => `${copy.value.outreachTab}(${Number(props.dashboard.navigation?.outreach_count ?? 0)})`);
</script>

<template>
    <Head :title="`${title} | Sea Requests`" />

    <MainLayout>
        <section class="admin-dashboard-shell">
            <header v-if="showIntro" class="dashboard-intro surface-panel">
                <div>
                    <p class="directory-eyebrow">{{ copy.eyebrow }}</p>
                    <h1 class="directory-page-title">{{ title }}</h1>
                    <p class="directory-intro-copy">{{ copy.subtitle }}</p>
                </div>
            </header>

            <section v-if="showTabs" class="dashboard-tabs-shell surface-panel">
                <div class="dashboard-tabs">
                    <Link
                        class="dashboard-tab"
                        :class="{ active: activeTab === 'businesses' }"
                        :href="dashboard.navigation.businesses_url"
                    >
                        {{ businessesTabLabel }}
                    </Link>
                    <Link
                        class="dashboard-tab"
                        :class="{ active: activeTab === 'users' }"
                        :href="dashboard.navigation.users_url"
                    >
                        {{ usersTabLabel }}
                    </Link>
                    <Link
                        class="dashboard-tab"
                        :class="{ active: activeTab === 'rfqs' }"
                        :href="dashboard.navigation.rfqs_url"
                    >
                        {{ rfqsTabLabel }}
                    </Link>
                    <Link
                        class="dashboard-tab"
                        :class="{ active: activeTab === 'orders' }"
                        :href="dashboard.navigation.orders_url"
                    >
                        {{ ordersTabLabel }}
                    </Link>
                    <Link
                        class="dashboard-tab dashboard-tab-outreach"
                        :class="{ active: activeTab === 'outreach' }"
                        :href="dashboard.navigation.outreach_url"
                    >
                        {{ outreachTabLabel }}
                    </Link>
                </div>
            </section>

            <slot />
        </section>
    </MainLayout>
</template>

<style scoped>
.admin-dashboard-shell{padding:16px 0 56px;display:grid;gap:20px}
.surface-panel{border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.94);box-shadow:0 20px 42px rgba(15,23,42,.06)}
.dashboard-intro{padding:24px 26px}
.dashboard-intro :deep(.directory-eyebrow){margin-bottom:12px}
.dashboard-intro :deep(.directory-intro-copy){margin-top:16px;max-width:72ch}
.dashboard-tabs-shell{padding:12px 14px;border-radius:10px}
.dashboard-tabs{display:flex;flex-wrap:wrap;gap:12px}
.dashboard-tab{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border:1px solid transparent;border-radius:8px;background:transparent;color:#64748b;font-size:.86rem;font-weight:600;cursor:pointer;text-decoration:none;min-width:0}
.dashboard-tab-outreach{margin-left:auto}
.dashboard-tab.active{background:#0f172a;border-color:#0f172a;color:#fff;box-shadow:0 12px 24px rgba(15,23,42,.14)}
@media (max-width: 720px){
    .admin-dashboard-shell{padding:12px 0 40px}
    .dashboard-intro{padding:20px}
    .dashboard-tabs-shell{padding:10px 12px}
    .dashboard-tab-outreach{margin-left:0}
    .dashboard-tab{width:100%;justify-content:flex-start;white-space:normal;word-break:break-word}
}
</style>
