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
        default: 'Buyer Dashboard',
    },
    activeTab: {
        type: String,
        default: 'requests',
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
    eyebrow: 'Procurement center',
    subtitle: 'Manage your RFQ flow, buyer-side reviews, and supplier-facing procurement activity from one place.',
    requestsTab: 'My RFQs',
    ordersTab: 'Orders',
    reviewsTab: 'Reviews',
}));

const requestsTabLabel = computed(() => `${copy.value.requestsTab}(${Number(props.dashboard.navigation?.requests_count ?? 0)})`);
const ordersTabLabel = computed(() => `${copy.value.ordersTab}(${Number(props.dashboard.navigation?.orders_count ?? 0)})`);
const reviewsTabLabel = computed(() => `${copy.value.reviewsTab}(${Number(props.dashboard.navigation?.reviews_count ?? 0)})`);
</script>

<template>
    <Head :title="`${title} | Sea Requests`" />

    <MainLayout>
        <section class="buyer-dashboard-shell">
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
                        :class="{ active: activeTab === 'requests' }"
                        :href="dashboard.navigation.requests_url"
                    >
                        {{ requestsTabLabel }}
                    </Link>
                    <Link
                        class="dashboard-tab"
                        :class="{ active: activeTab === 'orders' }"
                        :href="dashboard.navigation.orders_url"
                    >
                        {{ ordersTabLabel }}
                    </Link>
                    <Link
                        class="dashboard-tab dashboard-tab-right"
                        :class="{ active: activeTab === 'reviews' }"
                        :href="dashboard.navigation.reviews_url"
                    >
                        {{ reviewsTabLabel }}
                    </Link>
                </div>
            </section>

            <slot />
        </section>
    </MainLayout>
</template>

<style scoped>
.buyer-dashboard-shell{padding:16px 0 56px;display:grid;gap:20px}
.surface-panel{border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.94);box-shadow:0 20px 42px rgba(15,23,42,.06)}
.dashboard-intro{padding:24px 26px}
.dashboard-intro :deep(.directory-eyebrow){margin-bottom:12px}
.dashboard-intro :deep(.directory-intro-copy){margin-top:16px;max-width:72ch}
.dashboard-tabs-shell{padding:12px 14px;border-radius:10px}
.dashboard-tabs{display:flex;flex-wrap:wrap;gap:12px}
.dashboard-tab{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border:1px solid transparent;border-radius:8px;background:transparent;color:#64748b;font-size:.86rem;font-weight:600;cursor:pointer;text-decoration:none;min-width:0}
.dashboard-tab-right{margin-left:auto}
.dashboard-tab.active{background:#0f172a;border-color:#0f172a;color:#fff;box-shadow:0 12px 24px rgba(15,23,42,.14)}
@media (max-width: 720px){
    .buyer-dashboard-shell{padding:12px 0 40px}
    .dashboard-intro{padding:20px}
    .dashboard-tabs-shell{padding:10px 12px}
    .dashboard-tab-right{margin-left:0}
    .dashboard-tab{width:100%;justify-content:flex-start;white-space:normal;word-break:break-word}
}
</style>
