<script setup>
import MainLayout from '../../Layouts/MainLayout.vue';
import PublicMetaHead from '../../Components/PublicMetaHead.vue';
import StaticPageLayout from './StaticPageLayout.vue';

const props = defineProps({
    meta: {
        type: Object,
        default: () => ({
            title: 'Terms & Conditions | Sea Requests',
            description: '',
            canonical: '',
            robots: 'index, follow',
            ogImage: '',
            twitterCard: 'summary_large_image',
        }),
    },
});

const sections = [
    {
        title: 'Platform Use',
        paragraphs: [
            'Sea Requests is a maritime marketplace where buyers and suppliers create accounts, publish requests, list services and communicate through the platform.',
            'Users are responsible for keeping their account details accurate and for using the platform only for lawful commercial activity.',
        ],
    },
    {
        title: 'Accounts and Approval',
        paragraphs: [
            'Account creation may require email verification and administrative approval before full access is granted.',
            'Sea Requests may suspend, reject or remove accounts that contain false information, misuse the platform or create operational risk.',
        ],
    },
    {
        title: 'Listings and Requests',
        paragraphs: [
            'Suppliers are responsible for the accuracy of their company details, service descriptions, coverage information and contact details.',
            'Buyers are responsible for submitting requests that are clear, accurate and relevant to genuine operational or procurement needs.',
        ],
    },
    {
        title: 'Liability',
        paragraphs: [
            'Sea Requests provides the platform infrastructure and does not become a party to transactions, quotations or service delivery between users.',
            'Each party remains responsible for its own commercial decisions, technical review, compliance checks and contractual commitments.',
        ],
    },
];
</script>

<template>
    <PublicMetaHead :meta="props.meta" />

    <MainLayout>
        <StaticPageLayout
            eyebrow="Legal"
            title="Terms & Conditions"
            intro="These terms govern how buyers and suppliers use Sea Requests and interact through the marketplace."
            updated="Last updated: May 13, 2026"
            :sections="sections"
        />
    </MainLayout>
</template>
