<script setup>
import { Link } from '@inertiajs/vue3';
import BuyerDashboardShell from './Shell.vue';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
    createUrl: {
        type: String,
        required: true,
    },
});

const copy = {
    title: 'Buyer Dashboard',
    requestsTitle: 'RFQs',
    requestsText: 'Open your full RFQ list to manage drafts, compare incoming offers, and update active requests.',
    ordersTitle: 'Orders',
    ordersText: 'Track supplier-based orders created after confirmed awards and prepare for the next invoice and payment workflow.',
    reviewsTitle: 'Reviews',
    reviewsText: 'Track the supplier reviews you can still publish or update after confirmed awards.',
    createTitle: 'Create a new RFQ',
    createText: 'Start a new spare parts or service request and send it to matched suppliers in minutes.',
    openRequests: 'Open Requests',
    openOrders: 'Open Orders',
    openReviews: 'Open Reviews',
    newRfq: '+ New RFQ',
};
</script>

<template>
    <BuyerDashboardShell :dashboard="dashboard" :title="copy.title" active-tab="dashboard">
        <section class="cards-grid">
            <article class="surface-panel action-card">
                <h2 class="directory-section-title">{{ copy.requestsTitle }}</h2>
                <p class="section-copy">{{ copy.requestsText }}</p>
                <Link :href="dashboard.navigation.requests_url" class="toolbar-button toolbar-button-primary">
                    {{ copy.openRequests }}
                </Link>
            </article>

            <article class="surface-panel action-card">
                <h2 class="directory-section-title">{{ copy.reviewsTitle }}</h2>
                <p class="section-copy">{{ copy.reviewsText }}</p>
                <Link :href="dashboard.navigation.reviews_url" class="toolbar-button toolbar-button-primary">
                    {{ copy.openReviews }}
                </Link>
            </article>

            <article class="surface-panel action-card">
                <h2 class="directory-section-title">{{ copy.ordersTitle }}</h2>
                <p class="section-copy">{{ copy.ordersText }}</p>
                <Link :href="dashboard.navigation.orders_url" class="toolbar-button toolbar-button-primary">
                    {{ copy.openOrders }}
                </Link>
            </article>

            <article class="surface-panel action-card">
                <h2 class="directory-section-title">{{ copy.createTitle }}</h2>
                <p class="section-copy">{{ copy.createText }}</p>
                <Link :href="createUrl" class="toolbar-button toolbar-button-primary">
                    {{ copy.newRfq }}
                </Link>
            </article>
        </section>
    </BuyerDashboardShell>
</template>

<style scoped>
.surface-panel{padding:32px 36px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.78);box-shadow:0 24px 44px rgba(15,23,42,.08)}
.section-copy{margin:0;color:#64748b;line-height:1.7}
.cards-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:20px}
.action-card{display:grid;gap:16px;align-content:start}
.toolbar-button{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border-radius:10px;border:1px solid transparent;font-size:.92rem;font-weight:600;text-decoration:none}
.toolbar-button-primary{background:#2563eb;border-color:#2563eb;color:#fff;box-shadow:0 12px 24px rgba(37,99,235,.18)}
@media (max-width: 1320px){
    .cards-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media (max-width: 1080px){
    .cards-grid{grid-template-columns:1fr}
}
@media (max-width: 760px){
    .surface-panel{padding:24px}
}
</style>
