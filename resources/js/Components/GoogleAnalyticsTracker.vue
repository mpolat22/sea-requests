<script setup>
import { usePage } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { hasCookieConsent } from '../lib/cookieConsent';

const measurementId = 'G-WN96CSXFYW';
const analyticsScriptId = 'sea-requests-google-analytics';
const consentAcceptedEvent = 'sea-requests:cookie-consent-accepted';

const page = usePage();
const hasInitialized = ref(false);
const lastTrackedPath = ref('');

let scriptLoadPromise = null;

const trackPageView = () => {
    if (!hasInitialized.value || typeof window === 'undefined' || typeof window.gtag !== 'function') {
        return;
    }

    const pagePath = page.url || `${window.location.pathname}${window.location.search}`;

    if (pagePath === lastTrackedPath.value) {
        return;
    }

    window.gtag('event', 'page_view', {
        page_title: document.title,
        page_location: window.location.href,
        page_path: pagePath,
    });

    lastTrackedPath.value = pagePath;
};

const ensureAnalyticsScript = async () => {
    if (typeof window === 'undefined') {
        return;
    }

    if (typeof window.gtag === 'function') {
        return;
    }

    if (scriptLoadPromise) {
        await scriptLoadPromise;
        return;
    }

    scriptLoadPromise = new Promise((resolve, reject) => {
        const existingScript = document.getElementById(analyticsScriptId);

        if (existingScript) {
            existingScript.addEventListener('load', resolve, { once: true });
            existingScript.addEventListener('error', reject, { once: true });
            return;
        }

        const script = document.createElement('script');
        script.id = analyticsScriptId;
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtag/js?id=${measurementId}`;
        script.addEventListener('load', resolve, { once: true });
        script.addEventListener('error', reject, { once: true });
        document.head.appendChild(script);
    });

    await scriptLoadPromise;
};

const initializeAnalytics = async () => {
    if (
        hasInitialized.value
        || typeof window === 'undefined'
        || !measurementId
        || !hasCookieConsent()
    ) {
        return;
    }

    try {
        await ensureAnalyticsScript();
    } catch {
        return;
    }

    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function gtag() {
        window.dataLayer.push(arguments);
    };

    window.gtag('js', new Date());
    window.gtag('config', measurementId, {
        send_page_view: false,
    });

    hasInitialized.value = true;
    trackPageView();
};

const handleConsentAccepted = () => {
    initializeAnalytics();
};

onMounted(() => {
    initializeAnalytics();
    window.addEventListener(consentAcceptedEvent, handleConsentAccepted);
});

onBeforeUnmount(() => {
    window.removeEventListener(consentAcceptedEvent, handleConsentAccepted);
});

watch(
    () => page.url,
    async (nextUrl, previousUrl) => {
        if (!nextUrl || nextUrl === previousUrl) {
            return;
        }

        await nextTick();
        trackPageView();
    },
);
</script>

<template />
