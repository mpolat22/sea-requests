<script setup>
import MainLayout from '../../Layouts/MainLayout.vue';
import PublicMetaHead from '../../Components/PublicMetaHead.vue';
import StaticPageLayout from './StaticPageLayout.vue';

const props = defineProps({
    meta: {
        type: Object,
        default: () => ({
            title: 'About Us | Sea Requests',
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
        title: 'What the platform does',
        paragraphs: [
            'Sea Requests is designed for maritime procurement teams that need a faster way to create requests, collect supplier offers, and evaluate the best commercial response.',
            'The platform supports both spare parts workflows and service request workflows, giving buyers one place to manage sourcing decisions across different marine needs.',
        ],
    },
    {
        title: 'Who it serves',
        paragraphs: [
            'The marketplace is relevant for ship owners, ship managers, operators, fleet teams, port agents, and maritime suppliers who need a more structured demand-and-offer environment.',
        ],
    },
    {
        title: 'How supplier visibility works',
        paragraphs: [
            'Suppliers do not build public product shelves. Instead, they present their service coverage, categories, ports, and company profile so they can be matched with relevant buyer demand.',
        ],
    },
];

const facts = [
    'Spare parts RFQs and service requests in one system',
    'Support for multi-supplier award decisions',
    'Verified supplier visibility and buyer reviews',
];

const panelItems = [
    'It reduces the gap between open demand and the right supplier response.',
    'It keeps request creation, offer review, and award decisions easier to follow.',
    'It creates a stronger foundation for invoice, payment, and review workflows later in the process.',
];

const cta = {
    title: 'Explore the marketplace',
    text: 'Open supplier profiles or published requests to see how the marketplace works in practice.',
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
            eyebrow="Company"
            title="About Sea Requests"
            intro="Sea Requests is a marine procurement marketplace where buyers publish spare parts RFQs and service requests, and suppliers respond to the right commercial opportunities."
            :sections="sections"
            :facts="facts"
            panel-title="Why this matters"
            :panel-items="panelItems"
            :cta="cta"
        />
    </MainLayout>
</template>
