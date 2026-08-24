<script setup>
defineProps({
    direction: {
        type: String,
        required: true,
        validator: (value) => ['previous', 'next'].includes(value),
    },
});
</script>

<template>
    <svg class="pagination-arrow-icon" viewBox="0 0 24 24" aria-hidden="true">
        <path
            :d="direction === 'previous' ? 'M15 18l-6-6 6-6' : 'M9 6l6 6-6 6'"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
</template>

<style scoped>
.pagination-arrow-icon{width:17px;height:17px;display:block;flex:0 0 17px}
</style>