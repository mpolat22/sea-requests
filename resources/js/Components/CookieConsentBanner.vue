<script setup>
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { consentKey, consentTimestampKey, hasCookieConsent, persistCookieConsent } from '../lib/cookieConsent';

const isVisible = ref(false);

const copy = {
    title: 'Cookie Notice',
    text: 'Sea Requests uses cookies and similar storage for secure login, session continuity, and core marketplace features.',
    privacy: 'Privacy Policy',
    terms: 'Terms & Conditions',
    accept: 'Accept Cookies',
};

const syncVisibility = () => {
    isVisible.value = !hasCookieConsent();
};

const acceptCookies = () => {
    if (typeof window === 'undefined') {
        return;
    }

    persistCookieConsent();
    isVisible.value = false;
    window.dispatchEvent(new CustomEvent('sea-requests:cookie-consent-accepted'));
};

const handleStorage = (event) => {
    if (!event || event.key === null || event.key === consentKey || event.key === consentTimestampKey) {
        syncVisibility();
    }
};

onMounted(() => {
    syncVisibility();
    window.addEventListener('storage', handleStorage);
});

onBeforeUnmount(() => {
    window.removeEventListener('storage', handleStorage);
});
</script>

<template>
    <transition name="cookie-slide">
        <aside
            v-if="isVisible"
            class="cookie-banner"
            aria-labelledby="cookie-notice-title"
            aria-describedby="cookie-notice-text"
        >
            <div class="cookie-copy">
                <strong id="cookie-notice-title">{{ copy.title }}</strong>
                <p id="cookie-notice-text">
                    {{ copy.text }}
                    <Link class="cookie-inline-link" href="/privacy-policy">{{ copy.privacy }}</Link>
                    and
                    <Link class="cookie-inline-link" href="/terms-of-service">{{ copy.terms }}</Link>.
                </p>
            </div>

            <div class="cookie-actions">
                <button type="button" class="cookie-button" @click="acceptCookies">
                    {{ copy.accept }}
                </button>
            </div>
        </aside>
    </transition>
</template>

<style scoped>
.cookie-banner {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 90;
    width: min(560px, calc(100vw - 32px));
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 18px;
    align-items: end;
    padding: 18px 20px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 20px 44px rgba(15, 23, 42, 0.16);
    backdrop-filter: blur(18px);
}

.cookie-copy {
    display: grid;
    gap: 8px;
    min-width: 0;
}

.cookie-copy strong {
    color: #04151f;
    font-size: 0.98rem;
    font-weight: 700;
    line-height: 1.25;
}

.cookie-copy p {
    margin: 0;
    color: rgba(4, 21, 31, 0.74);
    font-size: 0.92rem;
    line-height: 1.65;
}

.cookie-inline-link {
    color: #0e7490;
    font-weight: 700;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.cookie-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
}

.cookie-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 0 18px;
    border: 0;
    border-radius: 10px;
    background: #0f172a;
    color: #fff;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
}

.cookie-slide-enter-active,
.cookie-slide-leave-active {
    transition: opacity 180ms ease, transform 180ms ease;
}

.cookie-slide-enter-from,
.cookie-slide-leave-to {
    opacity: 0;
    transform: translate3d(0, 12px, 0);
}

@media (max-width: 720px) {
    .cookie-banner {
        right: 12px;
        bottom: 12px;
        width: calc(100vw - 24px);
        grid-template-columns: 1fr;
        align-items: stretch;
        gap: 14px;
        padding: 16px;
    }

    .cookie-actions,
    .cookie-button {
        width: 100%;
    }
}
</style>
