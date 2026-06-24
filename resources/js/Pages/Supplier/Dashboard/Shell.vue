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
        default: 'Supplier Dashboard',
    },
    activeTab: {
        type: String,
        default: 'business',
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
    eyebrow: 'Management area',
    subtitle: 'Manage your business profile and track the requests that reach you from this screen.',
    rejectionTitle: 'Your application needs updates',
    rejectionText: 'You can revise your business details based on the admin notes and submit again.',
    updatePending: 'Update pending',
    updateRequestText: 'Your current live profile stays visible until the admin team approves the new information you submitted.',
    businessTab: 'My Business',
    incomingTab: 'Incoming Requests',
    ordersTab: 'Orders',
    reviewsTab: 'Reviews',
}));

const fieldLabels = computed(() => ({
    company_name: 'Business name',
    service_category_ids: 'Category and subcategory',
    service_brand_ids: 'Brands',
    service_country_codes: 'Service countries',
    service_ports_by_country: 'Service ports',
    country: 'Country',
    company_city: 'City',
    company_district: 'District',
    company_neighborhood: 'Neighborhood',
    company_postal_code: 'Postal code',
    company_address_line: 'Address',
    phone: 'Phone',
    landline_phone: 'Landline',
    contact_email: 'Contact email',
    website_url: 'Website',
    whatsapp_number: 'WhatsApp',
    telegram_url: 'Telegram',
    instagram_url: 'Instagram',
    linkedin_url: 'LinkedIn',
    facebook_url: 'Facebook',
    twitter_url: 'X / Twitter',
    company_overview: 'Company overview',
    port_coverage: 'Port coverage',
    registration_number: 'Registration number',
    company_logo: 'Logo',
    company_registration_documents: 'Company registration documents',
    tax_certificate_documents: 'Tax documents',
    service_authorization_documents: 'Authorization documents',
    official_documents: 'Official documents',
}));

const formatFieldLabel = (field) => {
    const mappedLabel = fieldLabels.value[field];

    if (mappedLabel) {
        return mappedLabel;
    }

    return String(field ?? '')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
};

const rejectionFieldText = computed(() => (props.dashboard.rejection_feedback?.fields ?? [])
    .map((field) => formatFieldLabel(field))
    .join(', '));

const updateFieldText = computed(() => (props.dashboard.update_request?.changed_fields ?? [])
    .map((field) => formatFieldLabel(field))
    .join(', '));

const incomingTabLabel = computed(() => `${copy.value.incomingTab}(${Number(props.dashboard.navigation?.incoming_count ?? 0)})`);
const ordersTabLabel = computed(() => `${copy.value.ordersTab}(${Number(props.dashboard.navigation?.orders_count ?? 0)})`);
const reviewsTabLabel = computed(() => `${copy.value.reviewsTab}(${Number(props.dashboard.navigation?.reviews_count ?? 0)})`);
</script>

<template>
    <Head :title="`${title} | Sea Requests`" />

    <MainLayout>
        <section class="seller-dashboard-shell">
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
                        :class="{ active: activeTab === 'business' }"
                        :href="dashboard.navigation.business_url"
                    >
                        {{ copy.businessTab }}
                    </Link>
                    <Link
                        class="dashboard-tab"
                        :class="{ active: activeTab === 'incoming' }"
                        :href="dashboard.navigation.incoming_url"
                    >
                        {{ incomingTabLabel }}
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

            <section
                v-if="dashboard.approval_status === 'rejected' && dashboard.rejection_feedback?.reason"
                class="surface-panel rejection-panel"
            >
                <p class="directory-eyebrow">{{ copy.rejectionTitle }}</p>
                <p class="rejection-copy">{{ copy.rejectionText }}</p>
                <p v-if="dashboard.rejection_feedback?.note" class="rejection-note">{{ dashboard.rejection_feedback.note }}</p>
                <p v-if="rejectionFieldText" class="rejection-fields">{{ rejectionFieldText }}</p>
            </section>

            <section
                v-if="dashboard.update_request?.status === 'pending'"
                class="surface-panel rejection-panel"
            >
                <p class="directory-eyebrow">{{ copy.updatePending }}</p>
                <p class="rejection-copy">{{ copy.updateRequestText }}</p>
                <p v-if="updateFieldText" class="rejection-fields">{{ updateFieldText }}</p>
            </section>

            <slot />
        </section>
    </MainLayout>
</template>

<style scoped>
.seller-dashboard-shell{padding:16px 0 56px;display:grid;gap:20px}
.surface-panel{border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.94);box-shadow:0 20px 42px rgba(15,23,42,.06)}
.dashboard-intro,.rejection-panel{padding:24px 26px}
.dashboard-intro :deep(.directory-eyebrow){margin-bottom:12px}
.dashboard-intro :deep(.directory-intro-copy){margin-top:16px;max-width:72ch}
.dashboard-tabs-shell{padding:12px 14px;border-radius:10px}
.dashboard-tabs{display:flex;flex-wrap:wrap;gap:12px}
.dashboard-tab{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border:1px solid transparent;border-radius:8px;background:transparent;color:#64748b;font-size:.86rem;font-weight:600;cursor:pointer;text-decoration:none;min-width:0}
.dashboard-tab-right{margin-left:auto}
.dashboard-tab.active{background:#0f172a;border-color:#0f172a;color:#fff;box-shadow:0 12px 24px rgba(15,23,42,.14)}
.rejection-copy,.rejection-note,.rejection-fields{margin:0;color:#475569;font-size:.94rem;line-height:1.7}
.rejection-note{margin-top:12px}
.rejection-fields{margin-top:8px}
@media (max-width: 720px){
    .seller-dashboard-shell{padding:12px 0 40px}
    .dashboard-intro,.rejection-panel{padding:20px}
    .dashboard-tabs-shell{padding:10px 12px}
    .dashboard-tab-right{margin-left:0}
    .dashboard-tab{width:100%;justify-content:flex-start;white-space:normal;word-break:break-word}
}
</style>
