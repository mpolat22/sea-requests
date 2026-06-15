<script setup>
import MainLayout from '../../Layouts/MainLayout.vue';
import PublicMetaHead from '../../Components/PublicMetaHead.vue';
import StaticPageLayout from './StaticPageLayout.vue';

const props = defineProps({
    meta: {
        type: Object,
        default: () => ({
            title: 'Blog | Sea Requests',
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
        title: 'What this section will include',
        paragraphs: [
            'This area is reserved for marketplace updates, practical procurement insights, and product notes relevant to maritime buyers and suppliers.',
            'It will grow over time as Sea Requests publishes guidance that supports the live marketplace experience without replacing the main request and supplier flows.',
        ],
    },
    {
        title: 'How to use it today',
        paragraphs: [
            'For now, the strongest product experience is still inside the live supplier directory and published request pages. The blog section will support that journey rather than lead it.',
        ],
    },
];

const facts = [
    'Marketplace updates and operational insights',
    'Support content for buyers and suppliers',
    'A complement to live service and request pages',
];

const cta = {
    title: 'Start from the live marketplace',
    text: 'Browse supplier profiles or published requests while this section grows.',
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
            eyebrow="Insights"
            title="Blog"
            intro="This section is being prepared for future marketplace updates and operational content."
            :sections="sections"
            :facts="facts"
            :cta="cta"
        />
    </MainLayout>
</template>
