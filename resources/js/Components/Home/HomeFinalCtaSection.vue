<script setup>
import { Link } from '@inertiajs/vue3';

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
    primaryLabel: {
        type: String,
        required: true,
    },
    primaryHref: {
        type: String,
        required: true,
    },
    secondaryLabel: {
        type: String,
        required: true,
    },
    secondaryHref: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <section class="home-section">
        <div class="cta-card">
            <div class="cta-copy">
                <p v-if="eyebrow" class="cta-eyebrow">{{ eyebrow }}</p>
                <h2 class="directory-section-title">{{ title }}</h2>
                <p class="cta-text">{{ text }}</p>
            </div>

            <div class="cta-actions">
                <Link class="cta-button primary" :href="primaryHref">{{ primaryLabel }}</Link>
                <Link class="cta-button secondary" :href="secondaryHref">{{ secondaryLabel }}</Link>
            </div>
        </div>
    </section>
</template>

<style scoped>
.cta-card {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 24px;
    align-items: center;
    padding: 30px 32px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.96)),
        radial-gradient(circle at right top, rgba(14, 116, 144, 0.1), transparent 28%);
    box-shadow: 0 22px 38px rgba(15, 23, 42, 0.08);
}

.cta-eyebrow {
    margin: 0 0 10px;
    font-size: 0.8rem;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--color-ocean);
    font-weight: 700;
}

.cta-text {
    margin: 14px 0 0;
    max-width: 72ch;
    color: rgba(4, 21, 31, 0.72);
    line-height: 1.7;
}

.cta-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 12px;
}

.cta-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 18px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 0.92rem;
    font-weight: 600;
    transition: transform 180ms ease, background-color 180ms ease, color 180ms ease, border-color 180ms ease;
}

.cta-button:hover {
    transform: translateY(-1px);
}

.cta-button.primary {
    background: #0e7490;
    color: #ffffff;
}

.cta-button.secondary {
    border: 1px solid rgba(4, 21, 31, 0.08);
    background: #ffffff;
    color: #04151f;
}

@media (max-width: 900px) {
    .cta-card {
        grid-template-columns: 1fr;
    }

    .cta-actions {
        justify-content: flex-start;
    }
}

@media (max-width: 640px) {
    .cta-card {
        padding: 22px 20px;
        gap: 18px;
    }

    .cta-actions {
        display: grid;
    }

    .cta-button {
        width: 100%;
    }
}
</style>
