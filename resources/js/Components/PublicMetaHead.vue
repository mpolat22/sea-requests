<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    meta: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head :title="meta.title">
        <meta v-if="meta.description" head-key="description" name="description" :content="meta.description" />
        <meta v-if="meta.robots" head-key="robots" name="robots" :content="meta.robots" />
        <meta v-if="meta.title" head-key="og:title" property="og:title" :content="meta.title" />
        <meta v-if="meta.description" head-key="og:description" property="og:description" :content="meta.description" />
        <meta v-if="meta.canonical" head-key="og:url" property="og:url" :content="meta.canonical" />
        <meta v-if="meta.ogImage" head-key="og:image" property="og:image" :content="meta.ogImage" />
        <meta v-if="meta.twitterCard" head-key="twitter:card" name="twitter:card" :content="meta.twitterCard" />
        <meta v-if="meta.title" head-key="twitter:title" name="twitter:title" :content="meta.title" />
        <meta v-if="meta.description" head-key="twitter:description" name="twitter:description" :content="meta.description" />
        <meta v-if="meta.ogImage" head-key="twitter:image" name="twitter:image" :content="meta.ogImage" />
        <link v-if="meta.canonical" head-key="canonical" rel="canonical" :href="meta.canonical" />
    </Head>
</template>
