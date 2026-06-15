<script setup>
import ServiceListingCard from '../ServiceListingCard.vue';
import HomeSectionHeader from './HomeSectionHeader.vue';

defineProps({
    eyebrow: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        required: true,
    },
    text: {
        type: String,
        default: '',
    },
    suppliers: {
        type: Array,
        default: () => [],
    },
    actionLabel: {
        type: String,
        default: '',
    },
    actionHref: {
        type: String,
        default: '',
    },
    cardLabel: {
        type: String,
        required: true,
    },
    noDescription: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <section class="home-section">
        <div class="supplier-showcase-shell">
            <div class="supplier-showcase-surface">
                <HomeSectionHeader
                    :eyebrow="eyebrow"
                    :title="title"
                    :text="text"
                    :action-label="actionLabel"
                    :action-href="actionHref"
                />

                <div class="supplier-grid">
                    <ServiceListingCard
                        v-for="item in suppliers"
                        :key="item.id"
                        :item="item"
                        :label="cardLabel"
                        :no-description="noDescription"
                    />
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.supplier-showcase-shell {
    padding: 32px 36px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.78);
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.08);
}

.supplier-showcase-surface {
    padding: 24px;
    border-radius: 10px;
    background: #f8fafb;
}

.supplier-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
}

@media (max-width: 1120px) {
    .supplier-showcase-shell {
        padding: 28px;
    }

    .supplier-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 960px) {
    .supplier-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 720px) {
    .supplier-showcase-shell,
    .supplier-showcase-surface {
        padding: 20px;
    }

    .supplier-grid {
        grid-template-columns: 1fr;
        gap: 14px;
    }
}
</style>
