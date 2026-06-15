<script setup>
import RequestCard from '../RequestCard.vue';
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
    requests: {
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
});
</script>

<template>
    <section class="home-section">
        <div class="request-showcase-shell">
            <div class="request-showcase-surface">
                <HomeSectionHeader
                    :eyebrow="eyebrow"
                    :title="title"
                    :text="text"
                    :action-label="actionLabel"
                    :action-href="actionHref"
                />

                <div class="request-grid">
                    <RequestCard v-for="item in requests" :key="item.id" :item="item" />
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.request-showcase-shell {
    padding: 32px 36px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.78);
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.08);
}

.request-showcase-surface {
    padding: 24px;
    border-radius: 10px;
    background: #f8fafb;
}

.request-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
}

@media (max-width: 1120px) {
    .request-showcase-shell {
        padding: 28px;
    }

    .request-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 960px) {
    .request-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 720px) {
    .request-showcase-shell,
    .request-showcase-surface {
        padding: 20px;
    }

    .request-grid {
        grid-template-columns: 1fr;
        gap: 14px;
    }
}
</style>
