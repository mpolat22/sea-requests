<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'mark',
    },
    size: {
        type: [Number, String],
        default: null,
    },
    alt: {
        type: String,
        default: 'Sea Requests',
    },
});

const src = computed(() => (props.variant === 'wordmark' ? '/brand/sea-requests-wordmark.png' : '/brand/sea-requests-mark.png'));

const resolvedStyle = computed(() => {
    if (!props.size) {
        return null;
    }

    const value = typeof props.size === 'number' ? `${props.size}px` : props.size;

    return props.variant === 'wordmark'
        ? { height: value, width: 'auto' }
        : { width: value, height: value };
});
</script>

<template>
    <img
        class="brand-logo-image"
        :src="src"
        :alt="alt"
        :style="resolvedStyle"
        decoding="async"
        loading="eager"
    >
</template>

<style scoped>
.brand-logo-image {
    display: block;
    max-width: 100%;
    object-fit: contain;
}
</style>
