<script setup>
import MainLayout from '../../Layouts/MainLayout.vue';
import PublicMetaHead from '../../Components/PublicMetaHead.vue';
import StaticPageLayout from './StaticPageLayout.vue';

const props = defineProps({
    meta: {
        type: Object,
        default: () => ({
            title: 'Disclaimer | Sea Requests',
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
        title: 'Marketplace role',
        paragraphs: [
            'Sea Requests does not guarantee supplier performance, product quality, delivery execution, or off-platform commercial outcomes unless a separate and explicit commitment says otherwise.',
        ],
    },
    {
        title: 'Independent parties',
        paragraphs: [
            'Buyers and suppliers remain independent commercial parties and are responsible for the agreements, documents, pricing, performance, and execution connected to their own transactions.',
        ],
    },
];

const facts = [
    'Marketplace visibility does not replace independent due diligence',
    'Commercial execution remains between the parties',
    'Platform transparency does not equal operational guarantee',
];

const panelItems = [
    'Review awarded scope carefully.',
    'Check supporting documents before payment or delivery decisions.',
    'Use the platform for clarity, not as a replacement for commercial judgment.',
];

const cta = {
    title: 'Review platform structure',
    text: 'If you need product context before reading policy language, start from the live marketplace pages.',
    primaryLabel: 'View Services',
    primaryHref: '/services',
    secondaryLabel: 'View Requests',
    secondaryHref: '/requests',
};
</script>

<template>
    <PublicMetaHead :meta="props.meta" />

    <MainLayout>
        <StaticPageLayout
            eyebrow="Support"
            title="Disclaimer"
            intro="Sea Requests provides a marketplace environment for demand discovery, supplier visibility, and structured commercial coordination."
            :sections="sections"
            :facts="facts"
            panel-title="Reminder"
            :panel-items="panelItems"
            :cta="cta"
        />
    </MainLayout>
</template>
