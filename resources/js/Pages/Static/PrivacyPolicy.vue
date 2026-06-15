<script setup>
import MainLayout from '../../Layouts/MainLayout.vue';
import PublicMetaHead from '../../Components/PublicMetaHead.vue';
import StaticPageLayout from './StaticPageLayout.vue';

const props = defineProps({
    meta: {
        type: Object,
        default: () => ({
            title: 'Privacy Policy | Sea Requests',
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
        title: 'Information We Collect',
        paragraphs: [
            'We collect account information such as name, email address, company details, country, phone number and optional WhatsApp number when you register.',
            'We may also collect operational data related to account verification, login activity and actions taken inside the platform.',
        ],
    },
    {
        title: 'How We Use Information',
        paragraphs: [
            'Your information is used to create accounts, verify identities, review supplier registrations, support communication and operate the marketplace safely.',
            'We may use account data to contact users about verification, approvals, access issues or important platform updates.',
        ],
    },
    {
        title: 'Data Sharing',
        paragraphs: [
            'Public supplier information may be displayed on platform pages when a supplier account is approved and published.',
            'We do not sell personal data. Information may be shared only when required for platform operation, legal compliance or security review.',
        ],
    },
    {
        title: 'Data Retention and Security',
        paragraphs: [
            'We keep account information only as long as needed for platform operation, legal obligations and reasonable audit or security requirements.',
            'Sea Requests applies reasonable technical and administrative measures to protect account data against unauthorized access or misuse.',
        ],
    },
];
</script>

<template>
    <PublicMetaHead :meta="props.meta" />

    <MainLayout>
        <StaticPageLayout
            eyebrow="Legal"
            title="Privacy Policy"
            intro="This policy explains how Sea Requests handles personal and operational information collected through the platform."
            updated="Last updated: May 13, 2026"
            :sections="sections"
        />
    </MainLayout>
</template>
