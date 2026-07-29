<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import MainLayout from '../../Layouts/MainLayout.vue';
import { useI18n } from '../../lib/i18n';

const props = defineProps({
    isSeller: {
        type: Boolean,
        default: false,
    },
    hasSubmittedSellerVerification: {
        type: Boolean,
        default: false,
    },
});

const { section } = useI18n();
const copy = section('auth.approvalPending');

const ui = computed(() => (props.isSeller && props.hasSubmittedSellerVerification
    ? {
        title: copy.value.sellerSubmittedTitle,
        text: copy.value.sellerSubmittedText,
    }
    : {
        title: copy.value.defaultTitle,
        text: copy.value.defaultText,
    }));
</script>

<template>
    <Head :title="copy.headTitle" />

    <MainLayout>
        <section class="notice-shell">
            <div class="notice-card">
                <p class="directory-eyebrow">{{ copy.eyebrow }}</p>
                <h1 class="directory-page-title">{{ ui.title }}</h1>
                <p class="directory-intro-copy">{{ ui.text }}</p>
            </div>
        </section>
    </MainLayout>
</template>

<style scoped>
.notice-shell{display:grid;place-items:center;padding:16px 0 56px}
.notice-card{width:min(760px,100%);padding:34px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.82);box-shadow:0 24px 44px rgba(15,23,42,.08)}
</style>
