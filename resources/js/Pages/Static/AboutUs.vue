<script setup>
import MainLayout from '../../Layouts/MainLayout.vue';
import PublicMetaHead from '../../Components/PublicMetaHead.vue';
import StaticPageLayout from './StaticPageLayout.vue';

const props = defineProps({
    meta: {
        type: Object,
        default: () => ({
            title: 'About Sea Requests | Maritime Procurement Platform',
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
            'Sea Requests is a maritime procurement platform designed for teams that need a faster and more structured way to run marine spare parts RFQ and ship spare parts RFQ workflows.',
            'The platform also supports marine service request, maritime service RFQ, and vessel service request workflows, giving buyers one place to manage sourcing decisions across different marine needs.',
        ],
    },
    {
        title: 'Who it serves',
        paragraphs: [
            'The marketplace is relevant for ship owners, ship managers, operators, fleet teams, technical purchasers, port agents, and maritime suppliers who need a more controlled ship spares procurement environment.',
        ],
    },
    {
        title: 'How supplier visibility works',
        paragraphs: [
            'Suppliers do not build public product shelves. Instead, they present their service coverage, categories, ports, and company profile so they can be matched with relevant buyer demand inside a marine suppliers platform.',
            'This structure is especially useful for ship service suppliers and marine technical service suppliers that want to be discovered through real RFQ and service demand rather than through a static catalogue model.',
        ],
    },
];

const facts = [
    'Marine spare parts RFQ and marine service request workflows in one system',
    'Support for multi-supplier award decisions in ship spares procurement',
    'Verified supplier visibility for maritime buyers and suppliers',
];

const panelItems = [
    'It reduces the gap between open maritime demand and the right supplier response.',
    'It keeps request creation, offer review, and award decisions easier to follow across both spare parts and service flows.',
    'It creates a stronger foundation for invoice, payment, and review workflows later in the process.',
];

const cta = {
    title: 'Explore the marketplace',
    text: 'Open supplier profiles or published requests to see how this maritime procurement platform works in practice.',
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
            intro="Sea Requests is a maritime procurement platform where buyers publish marine spare parts RFQ, ship spare parts RFQ, and marine service request opportunities, while suppliers respond through a structured workflow."
            :sections="sections"
            :facts="facts"
            panel-title="Why this matters"
            :panel-items="panelItems"
            :cta="cta"
        />
    </MainLayout>
</template>
