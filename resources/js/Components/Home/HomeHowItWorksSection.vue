<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    tabs: {
        type: Array,
        default: () => [],
    },
    eyebrow: {
        type: String,
        default: '',
    },
});

const activeTabKey = ref(props.tabs[0]?.key ?? null);

watch(
    () => props.tabs,
    (tabs) => {
        if (!tabs.length) {
            activeTabKey.value = null;
            return;
        }

        if (!tabs.some((tab) => tab.key === activeTabKey.value)) {
            activeTabKey.value = tabs[0].key;
        }
    },
    { immediate: true },
);

const activeTab = computed(() => props.tabs.find((tab) => tab.key === activeTabKey.value) ?? props.tabs[0] ?? null);

const flowIcon = (flowKey) => {
    if (flowKey?.includes('service')) return 'search';
    return 'document';
};

const stepIcon = (stepKey) => {
    if (stepKey?.includes('submit') || stepKey?.includes('send')) return 'send';
    if (stepKey?.includes('compare') || stepKey?.includes('review') || stepKey?.includes('receive')) return 'review';
    if (stepKey?.includes('select') || stepKey?.includes('confirm')) return 'confirm';
    return 'document';
};
</script>

<template>
    <section class="home-section">
        <div class="workflow-shell">
            <div class="workflow-surface">
                <div v-if="activeTab" class="workflow-copy">
                    <p v-if="eyebrow" class="workflow-eyebrow">{{ eyebrow }}</p>
                    <h2 class="directory-section-title">{{ activeTab.title }}</h2>
                    <p class="workflow-text">{{ activeTab.text }}</p>
                </div>

                <div class="workflow-topbar">
                    <div class="workflow-tabs" role="tablist" aria-label="Workflow personas">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            class="workflow-tab"
                            :class="{ active: tab.key === activeTab?.key }"
                            :aria-selected="tab.key === activeTab?.key"
                            @click="activeTabKey = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <div v-if="activeTab?.flows?.length" class="flows-stack">
                    <article v-for="flow in activeTab.flows" :key="flow.key" class="flow-card">
                        <div class="flow-head">
                            <div class="flow-icon-box">
                                <svg v-if="flowIcon(flow.key) === 'document'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M7 4.5h7l3 3V19a1.5 1.5 0 0 1-1.5 1.5h-8A1.5 1.5 0 0 1 6 19V6a1.5 1.5 0 0 1 1-1.5Z" />
                                    <path d="M14 4.5V8h3" />
                                </svg>
                                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="11" cy="11" r="6" />
                                    <path d="m20 20-3.5-3.5" />
                                </svg>
                            </div>

                            <div class="flow-copy">
                                <strong class="flow-title">{{ flow.title }}</strong>
                                <p class="flow-text">{{ flow.text }}</p>
                            </div>
                        </div>

                        <div class="flow-steps">
                            <article v-for="step in flow.steps" :key="step.key" class="step-card">
                                <div class="step-card-top">
                                    <div class="step-mini-icon">
                                        <svg v-if="stepIcon(step.key) === 'document'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M7 4.5h7l3 3V19a1.5 1.5 0 0 1-1.5 1.5h-8A1.5 1.5 0 0 1 6 19V6a1.5 1.5 0 0 1 1-1.5Z" />
                                            <path d="M14 4.5V8h3" />
                                        </svg>
                                        <svg v-else-if="stepIcon(step.key) === 'send'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M22 2 11 13" />
                                            <path d="m22 2-7 20-4-9-9-4 20-7Z" />
                                        </svg>
                                        <svg v-else-if="stepIcon(step.key) === 'review'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M4 6h16" />
                                            <path d="M4 12h10" />
                                            <path d="M4 18h7" />
                                            <path d="m17 16 2 2 4-4" />
                                        </svg>
                                        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M9 12.75 11.25 15 15 9.75" />
                                            <path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9Z" />
                                        </svg>
                                    </div>

                                    <span class="step-kicker">{{ step.kicker }}</span>
                                </div>

                                <strong class="step-title">{{ step.title }}</strong>
                            </article>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.workflow-shell {
    padding: 32px 36px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.78);
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.08);
}

.workflow-surface {
    padding: 24px;
    border-radius: 10px;
    background: #f8fafb;
    min-width: 0;
}

.workflow-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 18px;
}

.workflow-tabs {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.78);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
}

.workflow-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 0 18px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: rgba(4, 21, 31, 0.72);
    font-size: 0.92rem;
    font-weight: 700;
    transition: background-color 180ms ease, color 180ms ease, box-shadow 180ms ease;
}

.workflow-tab.active {
    background: linear-gradient(180deg, #5d8cff, #4f7bff);
    color: #ffffff;
    box-shadow: 0 10px 24px rgba(79, 123, 255, 0.22);
}

.workflow-copy {
    margin-bottom: 18px;
}

.workflow-eyebrow {
    margin: 0 0 10px;
    font-size: 0.8rem;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--color-ocean);
    font-weight: 700;
}

.workflow-copy :deep(.directory-section-title) {
    margin: 0;
}

.workflow-text {
    margin: 12px 0 0;
    color: rgba(4, 21, 31, 0.72);
    line-height: 1.7;
}

.flows-stack {
    display: grid;
    gap: 16px;
}

.flow-card {
    display: grid;
    gap: 16px;
    padding: 16px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 14px 28px rgba(15, 23, 42, 0.05);
}

.flow-head {
    display: flex;
    align-items: flex-start;
    gap: 14px;
}

.flow-icon-box,
.step-mini-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    overflow: hidden;
}

.flow-icon-box {
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
}

.flow-icon-box svg,
.step-mini-icon svg {
    width: 14px;
    height: 14px;
    display: block;
    flex: 0 0 auto;
}

.flow-copy {
    min-width: 0;
}

.flow-title {
    display: block;
    font-size: 1.06rem;
    line-height: 1.28;
    color: #04151f;
}

.flow-text {
    margin: 6px 0 0;
    color: rgba(4, 21, 31, 0.68);
    line-height: 1.6;
}

.flow-steps {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
}

.step-card {
    display: grid;
    gap: 8px;
    min-height: 100%;
    padding: 12px;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: 10px;
    background: rgba(248, 250, 252, 0.9);
}

.step-card-top {
    display: flex;
    align-items: center;
    gap: 8px;
}

.step-mini-icon {
    width: 22px;
    height: 22px;
    flex: 0 0 22px;
}

.step-kicker {
    color: rgba(71, 85, 105, 0.92);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.step-title {
    font-size: 0.92rem;
    line-height: 1.4;
    color: #04151f;
}

@media (max-width: 1120px) {
    .workflow-shell {
        padding: 28px;
    }

    .flow-steps {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    .workflow-topbar {
        display: grid;
        justify-content: stretch;
    }
}

@media (max-width: 720px) {
    .workflow-shell,
    .workflow-surface {
        padding: 20px;
    }

    .workflow-tabs {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .workflow-tab {
        padding-inline: 12px;
        width: 100%;
        min-width: 0;
    }

    .flow-steps {
        grid-template-columns: 1fr;
    }

    .flow-head {
        gap: 10px;
    }

    .flow-title {
        font-size: 0.98rem;
    }

    .flow-text {
        font-size: 0.92rem;
        line-height: 1.55;
    }

    .step-card {
        padding: 11px;
    }
}
</style>
