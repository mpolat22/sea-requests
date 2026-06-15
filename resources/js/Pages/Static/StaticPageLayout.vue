<script setup>
const props = defineProps({
    eyebrow: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    intro: {
        type: String,
        required: true,
    },
    updated: {
        type: String,
        default: '',
    },
    sections: {
        type: Array,
        default: () => [],
    },
    facts: {
        type: Array,
        default: () => [],
    },
    panelTitle: {
        type: String,
        default: '',
    },
    panelItems: {
        type: Array,
        default: () => [],
    },
    cta: {
        type: Object,
        default: null,
    },
    backHref: {
        type: String,
        default: '/',
    },
    backLabel: {
        type: String,
        default: 'Back to Home',
    },
});
</script>

<template>
    <section class="static-shell">
        <div class="static-block">
            <div class="static-surface static-hero-card">
                <div class="static-hero-copy">
                    <p class="eyebrow">{{ eyebrow }}</p>
                    <h1 class="directory-page-title static-title">{{ title }}</h1>
                    <p class="static-intro">{{ intro }}</p>
                    <p v-if="updated" class="static-updated">{{ updated }}</p>
                </div>

                <div class="static-hero-actions">
                    <slot name="hero-actions">
                        <a class="hero-back-link" :href="backHref">{{ backLabel }}</a>
                    </slot>
                </div>
            </div>
        </div>

        <div class="static-block">
            <div class="static-surface">
                <slot>
                    <div class="static-flow">
                        <section
                            v-for="section in sections"
                            :key="section.title"
                            class="static-entry"
                        >
                            <h2>{{ section.title }}</h2>
                            <p v-for="paragraph in section.paragraphs" :key="paragraph">{{ paragraph }}</p>
                        </section>

                        <section v-if="facts.length" class="static-inline-panel">
                            <strong class="static-panel-title">At a glance</strong>
                            <div class="static-line-list">
                                <div v-for="fact in facts" :key="fact" class="static-line">
                                    <span class="fact-dot"></span>
                                    <span>{{ fact }}</span>
                                </div>
                            </div>
                        </section>

                        <section v-if="panelTitle && panelItems.length" class="static-inline-panel">
                            <strong class="static-panel-title">{{ panelTitle }}</strong>
                            <div class="static-line-list">
                                <div v-for="item in panelItems" :key="item" class="static-line">
                                    <span class="fact-dot alt"></span>
                                    <span>{{ item }}</span>
                                </div>
                            </div>
                        </section>
                    </div>
                </slot>
            </div>
        </div>

        <div v-if="cta" class="static-block">
            <section class="static-surface page-cta">
                <div class="page-cta-copy">
                    <p class="side-kicker">Next step</p>
                    <strong>{{ cta.title }}</strong>
                    <p>{{ cta.text }}</p>
                </div>

                <div class="page-cta-actions">
                    <a class="page-cta-button primary" :href="cta.primaryHref">
                        {{ cta.primaryLabel }}
                    </a>
                    <a class="page-cta-button secondary" :href="cta.secondaryHref">
                        {{ cta.secondaryLabel }}
                    </a>
                </div>
            </section>
        </div>
    </section>
</template>

<style scoped>
.static-shell {
    display: grid;
    gap: 24px;
    padding: 16px 0 56px;
}

.static-block {
    padding: 32px 36px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.78);
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.08);
}

.static-surface {
    padding: 24px;
    border-radius: 10px;
    background: #f8fafb;
}

.static-hero-card,
.page-cta {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 22px;
    align-items: start;
}

.static-title {
    margin: 0;
}

.eyebrow {
    margin: 0 0 10px;
    font-size: 0.8rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--color-ocean);
    font-weight: 700;
}

.static-intro {
    margin: 16px 0 0;
    max-width: 76ch;
    color: rgba(4, 21, 31, 0.72);
    line-height: 1.75;
    font-size: 1rem;
}

.static-updated {
    margin: 16px 0 0;
    color: rgba(4, 21, 31, 0.72);
    line-height: 1.75;
    font-size: 1rem;
}

.hero-back-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 16px;
    border-radius: 10px;
    background: rgba(4, 21, 31, 0.08);
    color: var(--color-ink);
    text-decoration: none;
    font-size: 0.92rem;
    font-weight: 600;
    white-space: nowrap;
}

.static-flow {
    display: grid;
    gap: 20px;
}

.static-entry {
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(4, 21, 31, 0.08);
}

.static-entry:last-of-type {
    padding-bottom: 0;
    border-bottom: 0;
}

.static-entry h2 {
    margin: 0 0 10px;
    color: #04151f;
    font-size: 1.14rem;
    font-weight: 700;
    line-height: 1.28;
}

.static-entry p {
    margin: 0;
    color: rgba(4, 21, 31, 0.8);
    line-height: 1.8;
}

.static-entry p + p {
    margin-top: 12px;
}

.static-inline-panel {
    display: grid;
    gap: 14px;
    padding-top: 4px;
}

.static-panel-title {
    color: #04151f;
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.25;
}

.static-line-list {
    display: grid;
    gap: 12px;
}

.static-line {
    display: grid;
    grid-template-columns: 12px minmax(0, 1fr);
    gap: 10px;
    align-items: start;
    color: rgba(4, 21, 31, 0.78);
    line-height: 1.7;
}

.fact-dot {
    width: 12px;
    height: 12px;
    border-radius: 999px;
    background: rgba(14, 116, 144, 0.18);
    border: 1px solid rgba(14, 116, 144, 0.28);
    margin-top: 7px;
}

.fact-dot.alt {
    background: rgba(4, 21, 31, 0.12);
    border-color: rgba(4, 21, 31, 0.16);
}

.side-kicker {
    margin: 0 0 12px;
    color: #0e7490;
    font-size: 0.78rem;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    font-weight: 700;
}

.page-cta-copy {
    display: grid;
    gap: 10px;
}

.page-cta-copy strong {
    color: #04151f;
    font-size: 1.18rem;
    line-height: 1.3;
}

.page-cta-copy p {
    margin: 0;
    color: rgba(4, 21, 31, 0.74);
    line-height: 1.8;
}

.page-cta-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.page-cta-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 18px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 0.92rem;
    font-weight: 700;
}

.page-cta-button.primary {
    background: #04151f;
    color: #fff;
}

.page-cta-button.secondary {
    background: rgba(4, 21, 31, 0.08);
    color: #04151f;
}

@media (max-width: 900px) {
    .static-hero-card,
    .page-cta {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 720px) {
    .static-shell {
        gap: 18px;
    }

    .static-block {
        padding: 20px;
    }

    .static-surface {
        padding: 20px;
    }

    .page-cta-actions {
        flex-direction: column;
    }

    .page-cta-button {
        width: 100%;
    }
}
</style>
