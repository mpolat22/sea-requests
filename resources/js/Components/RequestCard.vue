<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
});

const copy = {
    live: 'LIVE',
    close: 'CLOSE',
    submitted: 'Submitted',
    awardConfirmed: 'Award Confirmed',
    award: 'Award Confirmed',
    received: 'Received',
    privateRequest: 'Private Request',
    spareParts: 'Spare Parts',
    serviceRequest: 'Service Request',
    sparePartsFallbackTitle: 'Spare Parts Request',
    serviceFallbackTitle: 'Service request',
    sparePartsDescription: 'A spare parts request for {count} products has been published by {company}. Review the details to submit your offer.',
    serviceDescription: '{company} has published a service request.',
};

const currentCopy = computed(() => copy);

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
    const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

    if (absSeconds < 60) {
        return rtf.format(future ? absSeconds : -absSeconds, 'second');
    }

    if (absMinutes < 60) {
        return rtf.format(future ? absMinutes : -absMinutes, 'minute');
    }

    if (absHours < 24) {
        return rtf.format(future ? absHours : -absHours, 'hour');
    }

    return rtf.format(future ? absDays : -absDays, 'day');
};

const requestTypeLabel = computed(() => (props.item.request_type === 'service_request'
    ? currentCopy.value.serviceRequest
    : currentCopy.value.spareParts));
const showRequestTypeChip = computed(() => !(
    props.item.is_private_request && props.item.request_type === 'service_request'
));

const requestTitle = computed(() => {
    if (props.item.request_type === 'service_request') {
        return props.item.service_title || currentCopy.value.serviceFallbackTitle;
    }

    return currentCopy.value.sparePartsFallbackTitle;
});

const requestDescription = computed(() => {
    if (props.item.request_type === 'service_request') {
        return props.item.service_description
            || currentCopy.value.serviceDescription.replace('{company}', props.item.company_mask || 'REQ***');
    }

    return currentCopy.value.sparePartsDescription
        .replace('{company}', props.item.company_mask || 'REQ***')
        .replace('{count}', Number(props.item.items_count ?? 0));
});

const requestCountries = computed(() => {
    const countries = Array.isArray(props.item.country_names) ? props.item.country_names.filter(Boolean) : [];

    if (countries.length) {
        return countries.join(', ');
    }

    return props.item.country_name || '-';
});

const cardStatusKey = computed(() => props.item.card_status_key || props.item.status || 'close');

const cardStatusLabel = computed(() => {
    if (cardStatusKey.value === 'received') {
        return `${Number(props.item.card_status_count ?? 0)} ${currentCopy.value.received}`;
    }

    const labels = {
        live: currentCopy.value.live,
        close: currentCopy.value.close,
        submitted: currentCopy.value.submitted,
        award_confirmed: currentCopy.value.awardConfirmed,
        award: currentCopy.value.award,
    };

    return labels[cardStatusKey.value] ?? cardStatusKey.value;
});

const cardStatusTone = computed(() => {
    if (cardStatusKey.value === 'live') return 'is-live';
    if (cardStatusKey.value === 'submitted') return 'is-submitted';
    if (cardStatusKey.value === 'received') return 'is-received';
    if (cardStatusKey.value === 'award_confirmed' || cardStatusKey.value === 'award') return 'is-awarded';
    return 'is-close';
});
</script>

<template>
    <Link :href="item.show_url" class="request-card">
        <div class="request-card-head">
            <div class="request-chip-row">
                <span v-if="showRequestTypeChip" class="request-type-chip">{{ requestTypeLabel }}</span>
                <span v-if="item.is_private_request" class="request-visibility-chip">
                    {{ item.visibility_badge || currentCopy.privateRequest }}
                </span>
            </div>
            <span class="request-status-chip" :class="cardStatusTone">
                <span class="status-dot"></span>
                {{ cardStatusLabel }}
            </span>
        </div>

        <div class="request-card-body">
            <strong class="request-title">{{ requestTitle }}</strong>
            <p class="request-description">{{ requestDescription }}</p>

            <div class="request-divider"></div>

            <div class="request-footer">
                <div class="request-countries">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 21s-6-5.2-6-11a6 6 0 0 1 12 0c0 5.8-6 11-6 11Z" />
                        <circle cx="12" cy="10" r="2.5" />
                    </svg>
                    <span>{{ requestCountries }}</span>
                </div>
                <div class="request-footer-meta">
                    <span class="footer-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 21h18" />
                            <path d="M5 21V7l7-4 7 4v14" />
                            <path d="M9 9h.01" />
                            <path d="M9 13h.01" />
                            <path d="M9 17h.01" />
                        </svg>
                        <span>{{ item.company_mask }}</span>
                    </span>
                    <span class="footer-item footer-time">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 7v5l3 3" />
                        </svg>
                        <span>{{ relativeTime(item.updated_at || item.submitted_at || item.requisition_date) }}</span>
                    </span>
                </div>
            </div>
        </div>
    </Link>
</template>

<style scoped>
.request-card {
    display: grid;
    gap: 14px;
    min-height: 100%;
    min-width: 0;
    padding: 16px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 18px 34px rgba(15, 23, 42, 0.08);
    text-decoration: none;
    transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}

.request-card:hover {
    transform: translateY(-3px) scale(1.01);
    border-color: rgba(14, 116, 144, 0.18);
    box-shadow: 0 28px 44px rgba(15, 23, 42, 0.12);
}

.request-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.request-chip-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.request-type-chip,
.request-visibility-chip,
.request-status-chip {
    display: inline-flex;
    align-items: center;
    min-height: 32px;
    padding: 0 12px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
}

.request-type-chip {
    background: rgba(14, 116, 144, 0.08);
    color: #0e7490;
}

.request-visibility-chip {
    background: rgba(15, 23, 42, 0.06);
    color: #334155;
}

.request-status-chip {
    gap: 8px;
    background: rgba(248, 250, 252, 0.95);
    color: #64748b;
    max-width: 100%;
}

.request-status-chip.is-live {
    background: rgba(240, 253, 244, 0.95);
    color: #15803d;
}

.request-status-chip.is-submitted {
    background: rgba(239, 246, 255, 0.95);
    color: #2563eb;
}

.request-status-chip.is-received {
    background: rgba(255, 247, 237, 0.95);
    color: #c2410c;
}

.request-status-chip.is-awarded {
    background: rgba(236, 254, 255, 0.95);
    color: #0f766e;
}

.request-status-chip.is-close {
    background: rgba(248, 250, 252, 0.95);
    color: #64748b;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: currentColor;
}

.request-card-body {
    display: grid;
    gap: 14px;
    flex: 1;
}

.request-title {
    color: #1e293b;
    font-size: 1.02rem;
    line-height: 1.35;
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.request-description {
    margin: 0;
    color: #475569;
    font-size: 0.96rem;
    line-height: 1.7;
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 4;
    min-height: 108px;
}

.request-divider {
    height: 1px;
    background: rgba(4, 21, 31, 0.08);
}

.request-footer {
    display: grid;
    gap: 10px;
}

.request-countries,
.footer-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    color: #475569;
    font-size: 0.92rem;
    font-weight: 600;
}

.request-countries svg,
.footer-item svg {
    width: 15px;
    height: 15px;
    flex: 0 0 15px;
    color: #64748b;
}

.request-countries span,
.footer-item span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.request-footer-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.footer-time {
    font-size: 0.82rem;
    color: #64748b;
    font-weight: 500;
}

@media (max-width: 640px) {
    .request-card-head {
        flex-direction: column;
        align-items: stretch;
    }

    .request-status-chip {
        align-self: flex-start;
        white-space: normal;
    }

    .request-footer-meta {
        flex-direction: column;
        align-items: flex-start;
    }

    .request-countries span,
    .footer-item span {
        white-space: normal;
        overflow: visible;
        text-overflow: clip;
        word-break: break-word;
    }

    .footer-time {
        width: 100%;
    }
}
</style>
