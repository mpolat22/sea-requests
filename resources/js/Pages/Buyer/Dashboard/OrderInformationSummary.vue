<script setup>
import OrderInformationPanels from '../../../Components/OrderInformationPanels.vue';

defineProps({
    order: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <OrderInformationPanels :order="order" viewer="buyer" />
</template>
