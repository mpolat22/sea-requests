<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from '../lib/i18n';
import HomeFinalCtaSection from '../Components/Home/HomeFinalCtaSection.vue';
import HomeHowItWorksSection from '../Components/Home/HomeHowItWorksSection.vue';
import HomeRequestShowcaseSection from '../Components/Home/HomeRequestShowcaseSection.vue';
import HomeSupplierShowcaseSection from '../Components/Home/HomeSupplierShowcaseSection.vue';
import HomeTrustSection from '../Components/Home/HomeTrustSection.vue';
import HomeValueTableSection from '../Components/Home/HomeValueTableSection.vue';
import PublicMetaHead from '../Components/PublicMetaHead.vue';
import MainLayout from '../Layouts/MainLayout.vue';

const props = defineProps({
    hero: {
        type: Object,
        required: true,
    },
    featured_requests: {
        type: Array,
        default: () => [],
    },
    featured_suppliers: {
        type: Array,
        default: () => [],
    },
    home_links: {
        type: Object,
        default: () => ({
            requests_url: '/requests',
            services_url: '/services',
            register_url: '/register',
        }),
    },
    meta: {
        type: Object,
        default: () => ({
            title: 'Sea Requests | Marine supplier marketplace',
            description: '',
            canonical: '',
            robots: 'index, follow',
            ogImage: '',
            twitterCard: 'summary_large_image',
        }),
    },
});

const { locale, section, t: translate } = useI18n();
const baseRegisterUrl = computed(() => props.home_links?.register_url ?? props.hero?.register_url ?? '/register');
const sellerRegisterUrl = computed(() => `${baseRegisterUrl.value}?role=seller`);
const buyerRegisterUrl = computed(() => `${baseRegisterUrl.value}?role=buyer`);
const featuredRequests = computed(() => {
    if (Array.isArray(props.featured_requests) && props.featured_requests.length) {
        return props.featured_requests;
    }

    return Array.isArray(props.hero?.latest_requests) ? props.hero.latest_requests : [];
});
const featuredSuppliers = computed(() => Array.isArray(props.featured_suppliers) ? props.featured_suppliers : []);

const heroCopy = section('home.hero');
const homeCopy = section('home.sections');

const formattedStats = computed(() => (props.hero?.stats ?? []).map((item) => ({
    ...item,
    label: translate(`home.stats.${item.key}`, item.label ?? item.label_en ?? ''),
    valueText: new Intl.NumberFormat('en-US').format(Number(item.value ?? 0)),
})));

const tickerRequests = computed(() => {
    const items = props.hero?.latest_requests ?? [];
    return items.length > 1 ? [...items, ...items] : items;
});

const requestStatusLabel = (status) => status === 'live'
    ? heroCopy.value.live
    : heroCopy.value.close;

const requestTypeLabel = (type) => type === 'service_request'
    ? heroCopy.value.serviceRequest
    : heroCopy.value.sparePartsRequest;

const requestTitle = (card) => {
    if (card.request_type === 'service_request') {
        return card.service_title || heroCopy.value.serviceFallbackTitle;
    }

    return heroCopy.value.sparePartsRequest;
};

const requestDescription = (card) => {
    if (card.request_type === 'service_request') {
        return card.service_description
            || heroCopy.value.serviceFallbackDescription.replace('{company}', card.company_mask || 'REQ***');
    }

    return heroCopy.value.sparePartsDescription
        .replace('{company}', card.company_mask || 'REQ***')
        .replace('{count}', String(card.items_count ?? 0));
};

const relativeTime = (value) => {
    if (!value) return '-';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';

    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const future = diffMs < 0;
    const absSeconds = Math.max(1, Math.round(Math.abs(diffMs) / 1000));
    const absMinutes = Math.round(absSeconds / 60);
    const absHours = Math.round(absMinutes / 60);
    const absDays = Math.round(absHours / 24);
    const rtf = new Intl.RelativeTimeFormat(locale.value === 'zh' ? 'zh-CN' : 'en-US', { numeric: 'auto' });

    if (absSeconds < 60) return rtf.format(future ? absSeconds : -absSeconds, 'second');
    if (absMinutes < 60) return rtf.format(future ? absMinutes : -absMinutes, 'minute');
    if (absHours < 24) return rtf.format(future ? absHours : -absHours, 'hour');

    return rtf.format(future ? absDays : -absDays, 'day');
};
</script>

<template>
    <PublicMetaHead :meta="props.meta" />

    <MainLayout>
        <main id="home">
            <section class="hero">
                <div class="hero-copy">
                    <p class="eyebrow">{{ heroCopy.eyebrow }}</p>
                    <h1 class="directory-page-title">{{ heroCopy.title }}</h1>
                    <p class="hero-text">{{ heroCopy.text }}</p>

                    <div class="hero-actions">
                        <Link class="primary-button" :href="sellerRegisterUrl">{{ heroCopy.sellerCta }}</Link>
                        <Link class="secondary-button ghost-button" :href="buyerRegisterUrl">{{ heroCopy.buyerCta }}</Link>
                    </div>

                    <div class="summary-panel">
                        <div class="summary-head">
                            <span>{{ heroCopy.marketSummary }}</span>
                        </div>

                        <div class="hero-stats">
                            <article v-for="item in formattedStats" :key="item.key" class="stat-card">
                                <div class="stat-icon-box">
                                    <svg v-if="item.key === 'sellers'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M7 20v-2a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v2" />
                                        <circle cx="12" cy="8" r="3" />
                                        <path d="M4 20v-1a2.5 2.5 0 0 1 2-2.45" />
                                        <path d="M20 20v-1a2.5 2.5 0 0 0-2-2.45" />
                                    </svg>
                                    <svg v-else-if="item.key === 'buyers'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="9" cy="9" r="2.5" />
                                        <circle cx="16" cy="8" r="2" />
                                        <path d="M5 18a4 4 0 0 1 8 0" />
                                        <path d="M14 18a3.5 3.5 0 0 1 5 0" />
                                    </svg>
                                    <svg v-else-if="item.key === 'rfqs'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M7 4.5h7l3 3V19a1.5 1.5 0 0 1-1.5 1.5h-8A1.5 1.5 0 0 1 6 19V6a1.5 1.5 0 0 1 1-1.5Z" />
                                        <path d="M14 4.5V8h3" />
                                        <path d="M9 12h6" />
                                        <path d="M9 15.5h6" />
                                    </svg>
                                    <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="4" y="4" width="6" height="6" rx="1.5" />
                                        <rect x="14" y="4" width="6" height="6" rx="1.5" />
                                        <rect x="4" y="14" width="6" height="6" rx="1.5" />
                                        <rect x="14" y="14" width="6" height="6" rx="1.5" />
                                    </svg>
                                </div>

                                <span class="stat-label">{{ item.label }}</span>
                                <strong class="stat-value">{{ item.valueText }}</strong>
                            </article>
                        </div>
                    </div>
                </div>

                <div class="hero-stream">
                    <div class="hero-card-head">
                        <Link class="stream-link" :href="hero.requests_url">{{ heroCopy.viewMore }}</Link>
                    </div>

                    <div class="ticker-shell">
                        <div class="ticker-track">
                            <Link
                                v-for="(card, index) in tickerRequests"
                                :key="`${card.id}-${index}`"
                                :href="card.show_url"
                                class="rfq-item"
                            >
                                <div class="rfq-item-head">
                                    <span class="request-type-pill">{{ requestTypeLabel(card.request_type) }}</span>
                                    <div class="rfq-item-actions">
                                        <span class="status-pill" :class="card.status === 'live' ? 'is-live' : 'is-close'">
                                            <span class="status-dot"></span>
                                            {{ requestStatusLabel(card.status) }}
                                        </span>
                                        <span class="card-open-link">{{ heroCopy.openRequest }}</span>
                                    </div>
                                </div>

                                <strong>{{ requestTitle(card) }}</strong>
                                <p class="rfq-item-text">{{ requestDescription(card) }}</p>

                                <div class="request-divider"></div>

                                <div class="request-country-line">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M12 21s-6-5.2-6-11a6 6 0 0 1 12 0c0 5.8-6 11-6 11Z" />
                                        <circle cx="12" cy="10" r="2.5" />
                                    </svg>
                                    <span>{{ card.country_summary }}</span>
                                </div>

                                <div class="rfq-item-meta">
                                    <span class="meta-line company-line">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M3 21h18" />
                                            <path d="M5 21V7l7-4 7 4v14" />
                                            <path d="M9 9h.01" />
                                            <path d="M9 13h.01" />
                                            <path d="M9 17h.01" />
                                        </svg>
                                        <span>{{ card.company_mask }}</span>
                                    </span>
                                    <span class="meta-line time-line">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <circle cx="12" cy="12" r="9" />
                                            <path d="M12 7v5l3 3" />
                                        </svg>
                                        <span>{{ relativeTime(card.updated_at || card.submitted_at) }}</span>
                                    </span>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            <div class="home-sections">
                <HomeHowItWorksSection
                    :eyebrow="homeCopy.howEyebrow"
                    :tabs="homeCopy.howTabs"
                />

                <HomeRequestShowcaseSection
                    v-if="featuredRequests.length"
                    :eyebrow="homeCopy.requestsEyebrow"
                    :title="homeCopy.requestsTitle"
                    :text="homeCopy.requestsText"
                    :requests="featuredRequests"
                    :action-label="homeCopy.requestsAction"
                    :action-href="home_links.requests_url"
                />

                <HomeSupplierShowcaseSection
                    v-if="featuredSuppliers.length"
                    :eyebrow="homeCopy.suppliersEyebrow"
                    :title="homeCopy.suppliersTitle"
                    :text="homeCopy.suppliersText"
                    :suppliers="featuredSuppliers"
                    :action-label="homeCopy.suppliersAction"
                    :action-href="home_links.services_url"
                    :card-label="homeCopy.supplierCardLabel"
                    :no-description="homeCopy.supplierNoDescription"
                />

                <HomeValueTableSection
                    :eyebrow="homeCopy.valueEyebrow"
                    :title="homeCopy.valueTitle"
                    :text="homeCopy.valueText"
                    :scope-label="homeCopy.valueScope"
                    :buyer-label="homeCopy.valueBuyer"
                    :supplier-label="homeCopy.valueSupplier"
                    :rows="homeCopy.valueRows"
                />

                <HomeTrustSection
                    :eyebrow="homeCopy.trustEyebrow"
                    :title="homeCopy.trustTitle"
                    :text="homeCopy.trustText"
                    :items="homeCopy.trustItems"
                />

                <HomeFinalCtaSection
                    :eyebrow="homeCopy.ctaEyebrow"
                    :title="homeCopy.ctaTitle"
                    :text="homeCopy.ctaText"
                    :primary-label="homeCopy.ctaPrimary"
                    :primary-href="sellerRegisterUrl"
                    :secondary-label="homeCopy.ctaSecondary"
                    :secondary-href="buyerRegisterUrl"
                />
            </div>
        </main>
    </MainLayout>
</template>

<style scoped>
#home {
    padding-top: 0;
}

.home-sections {
    display: grid;
    gap: 56px;
    padding: 4px 0 0;
}

.hero {
    animation: rise 700ms ease both;
}

.hero {
    display: grid;
    grid-template-columns: minmax(0, 1.08fr) minmax(380px, 0.92fr);
    gap: 30px;
    align-items: stretch;
    padding: 15px 0 44px;
    border: none;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    overflow: hidden;
}

.hero-copy {
    padding: 46px 18px 46px 18px;
}

.eyebrow {
    margin: 0 0 16px;
    font-size: 0.85rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--color-ocean);
    font-weight: 700;
}

.hero h1 {
    max-width: 14ch;
    font-size: clamp(3.35rem, 5.4vw, 5.15rem);
    line-height: 0.95;
}

.hero-text {
    color: rgba(4, 21, 31, 0.72);
    margin: 16px 0 0;
    max-width: 86ch;
    line-height: 1.7;
}

.hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    margin-top: 30px;
}

.primary-button,
.secondary-button {
    padding: 13px 16px;
    border-radius: 12px;
    font-size: 1rem;
    line-height: 1.7;
    font-weight: 500;
    letter-spacing: 0;
    transition: background-color 180ms ease, color 180ms ease, transform 180ms ease;
    text-decoration: none;
    text-align: center;
}

.primary-button {
    background: rgba(14, 116, 144, 0.1);
    color: #0e7490;
    box-shadow: none;
}

.secondary-button {
    background: rgba(14, 116, 144, 0.1);
    color: #0e7490;
    border: none;
}

.ghost-button {
    background: rgba(14, 116, 144, 0.1);
    color: #0e7490;
}

.summary-panel {
    margin-top: 34px;
    border-radius: 10px;
    padding: 0;
    border: none;
    background: transparent;
}

.summary-head {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    margin-bottom: 18px;
    color: var(--color-ocean);
    font-size: 0.84rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.hero-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
}

.stat-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 14px;
    aspect-ratio: 1 / 1;
    min-height: 0;
    padding: 18px 16px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(219, 234, 254, 0.9);
    box-shadow: 0 18px 36px rgba(37, 99, 235, 0.08);
}

.stat-icon-box {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(219, 234, 254, 0.72);
    color: #2563eb;
}

.stat-icon-box svg {
    width: 18px;
    height: 18px;
}

.stat-label {
    color: #0f172a;
    font-size: 0.82rem;
    line-height: 1.2;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 2rem;
    line-height: 1.05;
    margin: 0;
    color: #0f172a;
    text-align: center;
    font-weight: 700;
}

.hero-stream {
    padding: 24px 18px 24px 18px;
    display: flex;
    flex-direction: column;
    color: inherit;
    overflow: hidden;
    background: transparent;
}

.hero-card-head {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 16px;
    margin-bottom: 18px;
    color: #0f172a;
    font-size: 0.92rem;
    font-weight: 700;
}

.stream-link {
    color: rgba(4, 21, 31, 0.58);
    font-size: 0.82rem;
    text-decoration: none;
    font-weight: 700;
}

.ticker-shell {
    position: relative;
    overflow: hidden;
    min-height: 560px;
    max-height: 560px;
    background: transparent;
    -webkit-mask-image: linear-gradient(180deg, transparent 0%, black 11%, black 89%, transparent 100%);
    mask-image: linear-gradient(180deg, transparent 0%, black 11%, black 89%, transparent 100%);
}

.ticker-track {
    display: grid;
    gap: 12px;
    animation: ticker-rise 56s linear infinite;
    background: transparent;
}

.ticker-shell:hover .ticker-track {
    animation-play-state: paused;
}

.rfq-item {
    display: flex;
    flex-direction: column;
    gap: 12px;
    border-radius: 10px;
    padding: 16px;
    background: rgba(255, 255, 255, 1);
    border: 1px solid rgba(4, 21, 31, 0.08);
    text-decoration: none;
    color: inherit;
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
    transition: transform 180ms ease, border-color 180ms ease, background 180ms ease, box-shadow 180ms ease;
}

.rfq-item:hover {
    transform: translateY(-3px) scale(1.01);
    border-color: rgba(14, 116, 144, 0.18);
    background: rgba(255, 255, 255, 1);
    box-shadow: 0 18px 30px rgba(15, 23, 42, 0.08);
}

.rfq-item-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.rfq-item-actions {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.request-type-pill {
    display: inline-flex;
    align-items: center;
    min-height: 32px;
    padding: 0 12px;
    border-radius: 999px;
    background: rgba(14, 116, 144, 0.08);
    color: #0e7490;
    font-size: 0.72rem;
    font-weight: 700;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 32px;
    padding: 0 12px;
    border-radius: 999px;
    background: rgba(255, 241, 242, 0.9);
    color: #dc2626;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
}

.status-pill.is-close {
    background: rgba(248, 250, 252, 0.95);
    color: #64748b;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: currentColor;
}

.rfq-item strong {
    font-size: 1rem;
    line-height: 1.3;
    color: #04151f;
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.rfq-item-text {
    margin: 0;
    color: rgba(4, 21, 31, 0.86);
    line-height: 1.55;
    min-height: 72px;
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    font-size: 0.92rem;
}

.request-divider {
    height: 1px;
    background: rgba(203, 213, 225, 0.7);
}

.request-country-line,
.meta-line {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    min-width: 0;
    line-height: 1.3;
}

.request-country-line {
    color: rgba(4, 21, 31, 0.74);
    font-size: 0.86rem;
}

.request-country-line span {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.request-country-line svg,
.meta-line svg {
    width: 14px;
    height: 14px;
    flex-shrink: 0;
    color: rgba(4, 21, 31, 0.68);
    margin-top: 1px;
}

.rfq-item-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.meta-line {
    font-size: 0.86rem;
}

.company-line {
    color: rgba(4, 21, 31, 0.8);
    font-weight: 600;
}

.time-line {
    color: #475569;
    font-size: 0.78rem;
}

.card-open-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 32px;
    width: fit-content;
    padding: 0 12px;
    border-radius: 999px;
    border: 1px solid rgba(14, 116, 144, 0.12);
    background: rgba(14, 116, 144, 0.08);
    color: #0e7490;
    font-size: 0.72rem;
    font-weight: 700;
}

.primary-button:hover,
.secondary-button:hover {
    transform: translateY(-1px);
    background: #0e7490;
    color: white;
}

@keyframes rise {
    from {
        opacity: 0;
        transform: translateY(16px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes ticker-rise {
    0% {
        transform: translateY(0);
    }

    100% {
        transform: translateY(calc(-50% - 7px));
    }
}

@media (max-width: 1100px) {
    .hero {
        grid-template-columns: 1fr;
    }

    .hero-copy,
    .hero-stream {
        padding: 28px;
    }

    .hero-stream {
        padding-top: 0;
    }

    .hero-stats {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .ticker-shell {
        min-height: 520px;
        max-height: 520px;
    }
}

@media (max-width: 960px) {
    .hero-copy,
    .hero-stream {
        padding: 24px;
    }

    .hero-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .hero-card-head {
        justify-content: flex-start;
    }

    .ticker-shell {
        min-height: 460px;
        max-height: 460px;
    }
}

@media (max-width: 640px) {
    .hero {
        padding-top: 12px;
    }

    .home-sections {
        gap: 32px;
        padding-inline: 0;
    }

    .hero-copy,
    .hero-stream {
        padding: 20px;
    }

    .hero h1 {
        max-width: none;
    }

    .hero-actions {
        display: grid;
        gap: 10px;
    }

    .primary-button,
    .secondary-button {
        width: 100%;
    }

    .hero-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .stat-card {
        aspect-ratio: auto;
        min-height: 132px;
        padding: 16px 14px;
        gap: 10px;
    }

    .rfq-item-meta {
        display: grid;
        justify-content: stretch;
    }

    .rfq-item-head {
        flex-direction: column;
        align-items: flex-start;
    }

    .rfq-item-actions {
        width: 100%;
        justify-content: space-between;
        flex-wrap: wrap;
    }

    .request-country-line span {
        white-space: normal;
    }

    .ticker-shell {
        min-height: 400px;
        max-height: 400px;
    }
}
</style>
