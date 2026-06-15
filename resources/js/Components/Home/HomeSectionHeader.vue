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
    <div class="home-section-header">
        <div class="header-copy">
            <p v-if="eyebrow" class="eyebrow">{{ eyebrow }}</p>
            <h2 class="directory-section-title">{{ title }}</h2>
            <p v-if="text" class="header-text">{{ text }}</p>
        </div>

        <Link v-if="actionLabel && actionHref" :href="actionHref" class="header-action">
            {{ actionLabel }}
        </Link>
    </div>
</template>

<style scoped>
.home-section-header {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 22px;
}

.header-copy {
    min-width: 0;
}

.eyebrow {
    margin: 0 0 10px;
    font-size: 0.8rem;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--color-ocean);
    font-weight: 700;
}

.header-text {
    margin: 12px 0 0;
    max-width: 76ch;
    color: rgba(4, 21, 31, 0.72);
    line-height: 1.7;
}

.header-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 0 16px;
    border-radius: 10px;
    background: rgba(14, 116, 144, 0.1);
    color: #0e7490;
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 600;
    white-space: nowrap;
    transition: transform 180ms ease, background-color 180ms ease, color 180ms ease;
}

.header-action:hover {
    transform: translateY(-1px);
    background: #0e7490;
    color: #ffffff;
}

@media (max-width: 820px) {
    .home-section-header {
        display: grid;
        align-items: start;
    }

    .header-action {
        justify-self: flex-start;
    }
}

@media (max-width: 640px) {
    .home-section-header {
        gap: 14px;
        margin-bottom: 18px;
    }

    .header-text {
        margin-top: 10px;
        font-size: 0.96rem;
        line-height: 1.65;
    }

    .header-action {
        width: 100%;
    }
}
</style>
