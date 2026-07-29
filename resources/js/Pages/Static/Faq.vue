<script setup>
import { computed } from 'vue';
import MainLayout from '../../Layouts/MainLayout.vue';
import PublicMetaHead from '../../Components/PublicMetaHead.vue';
import StaticPageLayout from './StaticPageLayout.vue';
import { useI18n } from '../../lib/i18n';

const props = defineProps({
    meta: {
        type: Object,
        default: () => ({
            title: '',
            description: '',
            canonical: '',
            robots: 'index, follow',
            ogImage: '',
            twitterCard: 'summary_large_image',
        }),
    },
});

const { section } = useI18n();
const page = computed(() => section('staticPages').value.faq);
</script>

<template>
    <PublicMetaHead :meta="props.meta" />

    <MainLayout>
        <StaticPageLayout
            :eyebrow="page.eyebrow"
            :title="page.title"
            :intro="page.intro"
            :updated="page.updated"
            :sections="page.sections"
            :facts="page.facts"
            :panel-title="page.panelTitle"
            :panel-items="page.panelItems"
            :cta="page.cta"
        />
    </MainLayout>
</template>