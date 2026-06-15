<script setup>
import MainLayout from '../../Layouts/MainLayout.vue';
import PublicMetaHead from '../../Components/PublicMetaHead.vue';
import StaticPageLayout from './StaticPageLayout.vue';

const props = defineProps({
    meta: {
        type: Object,
        default: () => ({
            title: 'FAQ | Sea Requests',
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
        title: 'Do suppliers upload products to the platform?',
        paragraphs: [
            'No. Sea Requests is not built like a classic e-commerce catalog. Suppliers define their capabilities, categories, ports, and company profile instead of maintaining public product shelves.',
        ],
    },
    {
        title: 'How do buyers use the platform?',
        paragraphs: [
            'Buyers create spare parts RFQs or service requests, receive supplier offers, compare responses, and confirm the right award decision.',
        ],
    },
    {
        title: 'Can a request be split between multiple suppliers?',
        paragraphs: [
            'Yes. The platform supports the real operational case where one buyer may select different suppliers for different items or scopes.',
        ],
    },
    {
        title: 'When do direct contact details become visible?',
        paragraphs: [
            'Direct contact details are controlled and become visible only when the confirmed award rules for that buyer-supplier relationship are met.',
        ],
    },
];

const facts = [
    'Suppliers define categories, ports, and service scope',
    'Buyers can compare and split awards across multiple suppliers',
    'Reviews are tied to confirmed work relationships',
];

const panelItems = [
    'Sea Requests is request-led, not catalog-led.',
    'Supplier matching depends on the right capability setup.',
    'Trust increases through award, document, and review logic.',
];

const cta = {
    title: 'See the model in action',
    text: 'You can browse real supplier profiles and published requests directly from the live marketplace.',
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
            title="FAQ"
            intro="These are the most important questions for understanding how Sea Requests works for buyers and suppliers."
            :sections="sections"
            :facts="facts"
            panel-title="Quick takeaway"
            :panel-items="panelItems"
            :cta="cta"
        />
    </MainLayout>
</template>
