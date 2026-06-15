<script setup>
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
    items: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <section class="home-section">
        <div class="trust-shell">
            <div class="trust-surface">
                <HomeSectionHeader :eyebrow="eyebrow" :title="title" :text="text" />

                <div class="trust-grid">
                    <article
                        v-for="item in items"
                        :key="item.key ?? item.title"
                        class="trust-card"
                        :class="`is-${item.key}`"
                    >
                        <div class="trust-card-top">
                            <div class="trust-icon-box">
                                <svg v-if="item.key === 'verified'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m12 3 7 4v5c0 5-3.4 8.6-7 9-3.6-.4-7-4-7-9V7l7-4Z" />
                                    <path d="m9.5 12 1.7 1.7L14.8 10" />
                                </svg>
                                <svg v-else-if="item.key === 'controlled-contact'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M16 8a6 6 0 0 1 0 8" />
                                    <path d="M19.5 4.5a11 11 0 0 1 0 15" />
                                    <path d="M8 16a6 6 0 0 1 0-8" />
                                    <path d="M4.5 19.5a11 11 0 0 1 0-15" />
                                    <path d="m10 11 2 2 2-2" />
                                    <path d="M12 13V7" />
                                </svg>
                                <svg v-else-if="item.key === 'workflow'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M8 6h10" />
                                    <path d="M8 12h8" />
                                    <path d="M8 18h10" />
                                    <path d="m5 6 1 1 2-2" />
                                    <path d="m5 12 1 1 2-2" />
                                    <path d="m5 18 1 1 2-2" />
                                </svg>
                                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 17.25 6.12 20l1.12-6.08L3 9.5l6.06-.83L12 3l2.94 5.67 6.06.83-4.24 4.42L17.88 20 12 17.25Z" />
                                </svg>
                            </div>
                        </div>

                        <strong class="trust-title">{{ item.title }}</strong>
                        <p class="trust-text">{{ item.text }}</p>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.trust-shell {
    padding: 32px 36px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.78);
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.08);
}

.trust-surface {
    padding: 24px;
    border-radius: 10px;
    background: #f8fafb;
}

.trust-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
}

.trust-card {
    display: grid;
    gap: 14px;
    min-height: 100%;
    padding: 20px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 18px 34px rgba(15, 23, 42, 0.06);
    transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}

.trust-card:hover {
    transform: translateY(-3px);
    border-color: rgba(14, 116, 144, 0.16);
    box-shadow: 0 24px 40px rgba(15, 23, 42, 0.1);
}

.trust-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.trust-icon-box {
    width: 44px;
    height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: rgba(14, 116, 144, 0.1);
    color: #0e7490;
}

.trust-icon-box svg {
    width: 18px;
    height: 18px;
}

.trust-card.is-verified .trust-icon-box {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.trust-card.is-controlled-contact .trust-icon-box {
    background: rgba(37, 99, 235, 0.1);
    color: #2563eb;
}

.trust-card.is-workflow .trust-icon-box {
    background: rgba(249, 115, 22, 0.12);
    color: #ea580c;
}

.trust-card.is-reviews .trust-icon-box {
    background: rgba(15, 118, 110, 0.12);
    color: #0f766e;
}

.trust-title {
    font-size: 1rem;
    line-height: 1.34;
    color: #04151f;
}

.trust-text {
    margin: 0;
    color: rgba(4, 21, 31, 0.72);
    line-height: 1.7;
}

@media (max-width: 1120px) {
    .trust-shell {
        padding: 28px;
    }

    .trust-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    .trust-grid {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .trust-card {
        padding: 18px;
    }
}

@media (max-width: 720px) {
    .trust-shell,
    .trust-surface {
        padding: 20px;
    }
}
</style>
