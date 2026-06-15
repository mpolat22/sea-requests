<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    noDescription: {
        type: String,
        required: true,
    },
    variant: {
        type: String,
        default: 'default',
    },
});

const categoryThemes = [
    {
        matches: ['tank', 'clean', 'hull', 'coating', 'wash'],
        gradient: 'linear-gradient(135deg, #d8f3dc 0%, #95d5b2 38%, #1b4332 100%)',
        accent: '#1b4332',
        icon: 'sparkles',
    },
    {
        matches: ['crew', 'manning', 'training', 'recruitment'],
        gradient: 'linear-gradient(135deg, #e0f2fe 0%, #7dd3fc 40%, #0f172a 100%)',
        accent: '#0f172a',
        icon: 'users',
    },
    {
        matches: ['supply', 'spare', 'provision', 'stores', 'logistics'],
        gradient: 'linear-gradient(135deg, #fef3c7 0%, #fbbf24 42%, #7c2d12 100%)',
        accent: '#7c2d12',
        icon: 'boxes',
    },
    {
        matches: ['port', 'cargo', 'terminal', 'stevedore', 'agency'],
        gradient: 'linear-gradient(135deg, #ede9fe 0%, #a78bfa 42%, #312e81 100%)',
        accent: '#312e81',
        icon: 'anchor',
    },
    {
        matches: ['repair', 'technical', 'engineering', 'maintenance', 'inspection', 'hold'],
        gradient: 'linear-gradient(135deg, #fee2e2 0%, #fca5a5 42%, #7f1d1d 100%)',
        accent: '#7f1d1d',
        icon: 'tools',
    },
    {
        matches: ['document', 'compliance', 'legal', 'survey', 'certificate'],
        gradient: 'linear-gradient(135deg, #ecfccb 0%, #bef264 42%, #365314 100%)',
        accent: '#365314',
        icon: 'document',
    },
];

const defaultCategoryTheme = {
    gradient: 'linear-gradient(135deg, #dbeafe 0%, #93c5fd 40%, #082f49 100%)',
    accent: '#082f49',
    icon: 'waves',
};

const theme = computed(() => {
    const haystack = [
        props.item?.primary_category?.name,
        props.item?.primary_category?.slug,
        props.item?.secondary_category?.name,
        props.item?.secondary_category?.slug,
    ].filter(Boolean).join(' ').toLowerCase();

    return categoryThemes.find((entry) => entry.matches.some((match) => haystack.includes(match))) ?? defaultCategoryTheme;
});

const href = computed(() => props.item?.href || '#');
const isDirectoryVariant = computed(() => props.variant === 'directory');
const title = computed(() => props.item?.secondary_category?.name || props.item?.primary_category?.name || props.item?.name || props.item?.company_name || '');
const companyName = computed(() => props.item?.name || props.item?.company_name || '');
const displayCountry = computed(() => props.item?.display_country || props.item?.country || '-');
const primaryCategoryName = computed(() => props.item?.primary_category?.name || '');
const secondaryCategoryName = computed(() => props.item?.secondary_category?.name || '');
const serviceLabel = computed(() => secondaryCategoryName.value || primaryCategoryName.value || title.value);
const portsCount = computed(() => Number(props.item?.ports_count ?? 0));
const portsLabel = computed(() => `${portsCount.value} ${portsCount.value === 1 ? 'port' : 'ports'} globally`);
const reviewCount = computed(() => Number(props.item?.review_summary?.count ?? 0));
const reviewAverage = computed(() => {
    if (reviewCount.value <= 0) {
        return 0;
    }

    return Number(props.item?.review_summary?.average ?? 0);
});
const reviewAverageText = computed(() => {
    if (reviewCount.value <= 0) {
        return 'No reviews yet';
    }

    return reviewAverage.value.toFixed(1);
});
const roundedReviewAverage = computed(() => Math.round(reviewAverage.value));
const logoInitials = computed(() => {
    const source = companyName.value || serviceLabel.value;

    return source
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('') || 'SR';
});
</script>

<template>
    <Link class="listing-card" :class="{ 'is-directory-card': isDirectoryVariant }" :href="href">
        <template v-if="isDirectoryVariant">
            <div class="directory-logo-shell" aria-hidden="true">
                <img
                    v-if="item.logo_url"
                    :src="item.logo_url"
                    :alt="companyName"
                    class="directory-logo-image"
                />
                <span v-else class="directory-logo-fallback">{{ logoInitials }}</span>
            </div>

            <div class="directory-copy">
                <div class="directory-head">
                    <strong class="directory-company" :title="companyName">{{ companyName }}</strong>
                    <div class="directory-rating-row">
                        <div class="directory-rating-stars" aria-hidden="true">
                            <span
                                v-for="star in 5"
                                :key="`directory-rating-${star}`"
                                class="directory-rating-star"
                                :class="{ 'is-filled': star <= roundedReviewAverage }"
                            >★</span>
                        </div>
                        <span class="directory-rating-value">{{ reviewAverageText }}</span>
                        <span v-if="reviewCount > 0" class="directory-rating-count">
                            {{ reviewCount }} review{{ reviewCount === 1 ? '' : 's' }}
                        </span>
                    </div>
                    <span v-if="serviceLabel" class="directory-service-line">{{ serviceLabel }}</span>
                </div>

                <p class="directory-description">{{ item.summary || noDescription }}</p>

                <div class="directory-footer">
                    <span class="directory-footer-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 21s-6-5.2-6-11a6 6 0 0 1 12 0c0 5.8-6 11-6 11Z" />
                            <circle cx="12" cy="10" r="2.5" />
                        </svg>
                        <span>{{ displayCountry }}</span>
                    </span>

                    <span class="directory-footer-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="5" r="2" />
                            <path d="M12 7v11" />
                            <path d="M7 10H4a8 8 0 0 0 16 0h-3" />
                            <path d="m8 14 4 4 4-4" />
                        </svg>
                        <span>{{ portsLabel }}</span>
                    </span>
                </div>
            </div>
        </template>

        <template v-else>
            <div class="card-cover" :style="{ background: theme.gradient }">
                <div class="cover-fallback"></div>
                <div class="cover-art" :style="{ color: theme.accent }">
                    <svg v-if="theme.icon === 'sparkles'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m12 3 1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4L12 3Z" />
                        <path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z" />
                    </svg>
                    <svg v-else-if="theme.icon === 'users'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" />
                        <circle cx="9.5" cy="7" r="3.5" />
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a3.5 3.5 0 0 1 0 6.75" />
                    </svg>
                    <svg v-else-if="theme.icon === 'boxes'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m21 8-9-5-9 5 9 5 9-5Z" />
                        <path d="M3 8v8l9 5 9-5V8" />
                        <path d="m12 13 9-5" />
                        <path d="M12 13 3 8" />
                    </svg>
                    <svg v-else-if="theme.icon === 'anchor'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="5" r="2" />
                        <path d="M12 7v11" />
                        <path d="M7 10H4a8 8 0 0 0 16 0h-3" />
                        <path d="m8 14 4 4 4-4" />
                    </svg>
                    <svg v-else-if="theme.icon === 'tools'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m14.7 6.3 3 3" />
                        <path d="m10.5 10.5 7.2-7.2a2.12 2.12 0 1 1 3 3l-7.2 7.2" />
                        <path d="M7 8 3 12l9 9 4-4" />
                    </svg>
                    <svg v-else-if="theme.icon === 'document'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
                        <path d="M14 2v6h6" />
                        <path d="M8 13h8" />
                        <path d="M8 17h5" />
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2 12c2.4 0 2.4-2 4.8-2s2.4 2 4.8 2 2.4-2 4.8-2 2.4 2 4.8 2" />
                        <path d="M2 16c2.4 0 2.4-2 4.8-2s2.4 2 4.8 2 2.4-2 4.8-2 2.4 2 4.8 2" />
                        <path d="M2 8c2.4 0 2.4-2 4.8-2s2.4 2 4.8 2 2.4-2 4.8-2 2.4 2 4.8 2" />
                    </svg>
                </div>
                <span v-if="item.primary_category" class="cover-badge">
                    {{ item.primary_category.name }}
                </span>
            </div>

            <div class="card-body">
                <div class="brand-copy">
                    <strong :title="title">
                        {{ title }}
                    </strong>
                    <span class="meta-line company-line" :title="companyName">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 21h18" />
                            <path d="M5 21V7l7-4 7 4v14" />
                            <path d="M9 9h.01" />
                            <path d="M9 13h.01" />
                            <path d="M9 17h.01" />
                            <path d="M15 9h.01" />
                            <path d="M15 13h.01" />
                            <path d="M15 17h.01" />
                        </svg>
                        <span>{{ companyName }}</span>
                    </span>
                    <span v-if="displayCountry" class="meta-line country-line">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 21s-6-5.2-6-11a6 6 0 0 1 12 0c0 5.8-6 11-6 11Z" />
                            <circle cx="12" cy="10" r="2.5" />
                        </svg>
                        <span>{{ displayCountry }}</span>
                    </span>
                </div>

                <p class="card-text">{{ item.summary || noDescription }}</p>

                <span class="card-link">
                    {{ label }}
                </span>
            </div>
        </template>
    </Link>
</template>

<style scoped>
.listing-card {
    display: flex;
    flex-direction: column;
    min-height: 100%;
    overflow: hidden;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 18px 34px rgba(15, 23, 42, 0.08);
    text-decoration: none;
    transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}

.listing-card:hover {
    transform: translateY(-3px) scale(1.01);
    border-color: rgba(14, 116, 144, 0.18);
    box-shadow: 0 28px 44px rgba(15, 23, 42, 0.12);
}

.listing-card.is-directory-card {
    display: grid;
    grid-template-columns: 64px minmax(0, 1fr);
    align-items: start;
    gap: 18px;
    padding: 18px 20px;
}

.listing-card.is-directory-card:hover {
    transform: translateY(-2px);
}

.directory-logo-shell {
    width: 64px;
    height: 64px;
    display: grid;
    place-items: center;
    overflow: hidden;
    border: 1px solid rgba(14, 116, 144, 0.12);
    border-radius: 14px;
    padding: 8px;
    background: #ffffff;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.7),
        0 8px 20px rgba(15, 23, 42, 0.06);
}

.directory-logo-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center;
    filter: contrast(1.05) saturate(1.02);
}

.directory-logo-fallback {
    color: #0f172a;
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 0.08em;
}

.directory-copy {
    display: grid;
    gap: 10px;
    min-width: 0;
}

.directory-head {
    display: grid;
    gap: 4px;
}

.directory-company {
    color: #0f172a;
    font-size: 1.22rem;
    line-height: 1.28;
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.directory-rating-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    min-width: 0;
}

.directory-rating-stars {
    display: inline-flex;
    align-items: center;
    gap: 2px;
}

.directory-rating-star {
    width: 0.86rem;
    height: 0.86rem;
    position: relative;
    overflow: hidden;
    color: transparent;
    font-size: 0;
    line-height: 1;
}

.directory-rating-star::before {
    content: "\2605";
    position: absolute;
    inset: 0;
    color: #cbd5e1;
    font-size: 0.86rem;
    line-height: 1;
}

.directory-rating-star.is-filled::before {
    color: #f59e0b;
}

.directory-rating-value {
    color: #334155;
    font-size: 0.83rem;
    font-weight: 700;
    line-height: 1.2;
}

.directory-rating-count {
    color: #64748b;
    font-size: 0.8rem;
    font-weight: 500;
    line-height: 1.2;
}

.directory-service-line {
    color: #0e7490;
    font-size: 0.88rem;
    font-weight: 700;
    line-height: 1.5;
}

.directory-description {
    margin: 0;
    color: rgba(15, 23, 42, 0.8);
    font-size: 0.94rem;
    line-height: 1.7;
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.directory-footer {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 2px;
}

.directory-footer-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    color: #334155;
    font-size: 0.9rem;
    font-weight: 600;
}

.directory-footer-item svg {
    width: 15px;
    height: 15px;
    flex-shrink: 0;
    color: #64748b;
}

.directory-footer-item span {
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 1;
}

.card-cover {
    position: relative;
    aspect-ratio: 16 / 9;
    overflow: hidden;
}

.cover-badge {
    position: absolute;
    top: 14px;
    left: 14px;
    max-width: calc(100% - 28px);
    padding: 7px 11px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.78);
    color: rgba(4, 21, 31, 0.78);
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.01em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    border: 1px solid rgba(255, 255, 255, 0.42);
    box-shadow: 0 10px 22px rgba(4, 21, 31, 0.1);
    backdrop-filter: blur(10px);
}

.cover-fallback {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.2), transparent 24%),
        radial-gradient(circle at left bottom, rgba(15, 118, 110, 0.35), transparent 28%),
        linear-gradient(135deg, rgba(255, 255, 255, 0.08), transparent 54%);
    opacity: 0.9;
}

.cover-art {
    position: absolute;
    right: 18px;
    bottom: 18px;
    width: 68px;
    height: 68px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.26);
    box-shadow: 0 18px 34px rgba(4, 21, 31, 0.12);
    backdrop-filter: blur(10px);
}

.cover-art svg {
    width: 30px;
    height: 30px;
}

.card-body {
    display: grid;
    gap: 16px;
    padding: 20px;
    flex: 1;
}

.brand-copy {
    display: grid;
    gap: 6px;
    min-width: 0;
}

.brand-copy strong {
    font-size: 1.06rem;
    line-height: 1.28;
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    color: #04151f;
}

.meta-line {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    color: rgba(4, 21, 31, 0.68);
    font-size: 0.9rem;
    min-width: 0;
    line-height: 1.3;
}

.meta-line svg {
    width: 14px;
    height: 14px;
    flex-shrink: 0;
    color: rgba(4, 21, 31, 0.54);
    margin-top: 1px;
}

.meta-line span {
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 1;
}

.company-line {
    color: rgba(4, 21, 31, 0.72);
    font-weight: 500;
    margin-top: 1px;
}

.company-line span {
    -webkit-line-clamp: 2;
    word-break: normal;
    overflow-wrap: break-word;
}

.country-line {
    color: rgba(4, 21, 31, 0.58);
}

.card-text {
    margin: 0;
    color: rgba(4, 21, 31, 0.74);
    line-height: 1.65;
    min-height: 106px;
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 4;
}

.card-link {
    width: 100%;
    margin-top: auto;
    padding: 13px 16px;
    border-radius: 10px;
    background: rgba(14, 116, 144, 0.1);
    color: #0e7490;
    font-size: 0.76rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    text-align: center;
    transition: background-color 180ms ease, color 180ms ease, transform 180ms ease;
}

.listing-card:hover .card-link {
    background: #0e7490;
    color: white;
}

.card-link:hover {
    transform: translateY(-1px);
}

@media (max-width: 640px) {
    .listing-card.is-directory-card {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .directory-footer {
        align-items: flex-start;
    }

    .directory-logo-shell {
        width: 56px;
        height: 56px;
    }

    .card-body {
        padding: 18px;
    }
}
</style>
