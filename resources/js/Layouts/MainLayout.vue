<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import BrandLogo from '../Components/BrandLogo.vue';
import MessengerDrawer from '../Components/MessengerDrawer.vue';
import { copy } from '../lib/translations';
import { useMessengerStore } from '../lib/messengerStore';

const page = usePage();
const messenger = useMessengerStore();

const t = copy;
const user = computed(() => page.props.auth?.user ?? null);
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const notifications = computed(() => page.props.notifications ?? { unread_count: 0 });
const mobileMenuOpen = ref(false);
const notificationMenuOpen = ref(false);
const accountMenuOpen = ref(false);
const notificationItems = ref([]);
const notificationsLoading = ref(false);
const notificationsLoaded = ref(false);
const toast = ref(null);
const navbarVisible = ref(true);
const lastScrollY = ref(0);
let toastTimer = null;

const accountName = computed(() => user.value?.name ?? 'Account');
const messengerUnreadCount = computed(() => messenger.state.unreadCount);
const profileHref = computed(() => {
    if (!user.value) {
        return '/dashboard';
    }

    if (user.value.role === 'admin') {
        return '/dashboard/admin';
    }

    if (user.value.role === 'seller') {
        return '/seller-verification';
    }

    return '/dashboard/buyer/profile';
});

const footerBrand = {
    name: 'Sea Requests',
    text: 'Sea Requests is a marine procurement marketplace where buyers create spare parts RFQs and service requests, and suppliers submit offers for the right opportunities.',
};

const footerSections = [
    {
        title: 'Platform',
        links: [
            { label: 'Services', href: '/services' },
            { label: 'Request', href: '/requests' },
        ],
    },
    {
        title: 'Company',
        links: [
            { label: 'About Us', href: '/about-us' },
            { label: 'Contact', href: '/contact' },
            { label: 'FAQ', href: '/faq' },
        ],
    },
    {
        title: 'Support',
        links: [
            { label: 'Privacy Policy', href: '/privacy-policy' },
            { label: 'Terms & Conditions', href: '/terms-of-service' },
            { label: 'Disclaimer', href: '/disclaimer' },
        ],
    },
];

const isActive = (url) => page.url === url || page.url.startsWith(`${url}/`);

const flashCode = (message) => {
    if (typeof message === 'string') {
        return message;
    }

    if (message && typeof message === 'object') {
        return message.code ?? message.key ?? null;
    }

    return null;
};

const resolveToastMessage = (message) => {
    if (!message) {
        return null;
    }

    if (message && typeof message === 'object' && typeof message.message === 'string' && message.message.trim() !== '') {
        return message.message.trim();
    }

    const code = flashCode(message);

    if (!code) {
        return null;
    }

    const flashMap = {
        'approval-updated': 'Account approval status updated.',
        'seller-verification-submitted': 'Supplier verification submitted.',
        'seller-verification-updated-admin': 'Supplier company record updated by admin.',
        'seller-verification-required': 'Supplier documents must be submitted before approval.',
        'seller-removal-request-submitted': 'Business removal request submitted.',
        'seller-removal-request-reviewed': 'Business removal request reviewed.',
        'seller-update-request-submitted': 'Business update request submitted.',
        'seller-update-request-reviewed': 'Business update request reviewed.',
        'seller-update-request-pending-lock': 'You cannot submit a new edit until the pending update request is reviewed.',
        'admin-user-updated': 'User profile updated.',
        'buyer-profile-updated': 'Buyer profile updated successfully.',
        'buyer-email-updated': 'Buyer profile updated. Please verify your new email address before continuing.',
        'admin-business-updated': 'Business record updated.',
        'admin-business-deleted': 'Business record removed.',
        'admin-user-deleted': 'User record deleted.',
        'verification-link-sent': 'Verification link sent.',
        'email-verified': 'Email address verified.',
        'rfq-created': 'RFQ saved successfully.',
        'rfq-updated': 'RFQ updated successfully.',
        'rfq-deleted': 'RFQ draft deleted.',
        'rfq-edit-locked': 'This RFQ cannot be edited in its current state.',
        'rfq-delete-locked': 'Only draft RFQs can be deleted.',
        'offer-draft-saved': 'Offer draft saved. You can review and submit it anytime.',
        'offer-submitted': 'Your offer has been submitted successfully.',
        'award-draft-saved': 'Award draft saved successfully.',
        'award-confirmed': 'Awards confirmed successfully.',
        'order-information-saved': 'Order information saved. The supplier can now review your billing and delivery or service instructions.',
        'order-information-updated': 'Order information updated. The supplier will now see the latest billing and delivery or service instructions.',
        'invoice-added': 'Invoice added. The buyer can now review this invoice and continue with payment workflow.',
        'invoice-uploaded': 'Invoice uploaded. The buyer can now review the invoice and continue with payment workflow.',
        'invoice-updated': 'Invoice updated. The buyer will now see the latest invoice version and details.',
        'payment-proof-uploaded': 'Payment proof uploaded. The supplier can now review it and confirm receipt of payment.',
        'payment-proof-updated': 'Payment proof updated. The supplier will now see the latest payment confirmation file and notes.',
        'payment-confirmed': 'Payment receipt confirmed. This invoice has now moved to the confirmed payment stage.',
        'payment-proof-required': 'Buyer payment proof is still missing for this invoice. Add the payment confirmation first, then confirm receipt.',
        'payment-already-confirmed': 'This invoice payment has already been confirmed.',
    };

    if (flashMap[code]) {
        return flashMap[code];
    }

    return t.common?.[code] ?? code;
};
const showToast = (message, tone = 'success') => {
    const content = resolveToastMessage(message);

    if (!content) {
        return;
    }

    toast.value = {
        id: `${tone}-${Date.now()}`,
        message: content,
        tone,
    };

    if (toastTimer) {
        clearTimeout(toastTimer);
    }

    toastTimer = window.setTimeout(() => {
        toast.value = null;
    }, 5000);
};

const notificationText = {
    title: 'Notifications',
    empty: 'You do not have any notifications yet.',
    loading: 'Loading notifications...',
    allRead: 'Mark all as read',
    viewAll: 'View all notifications',
    unread: 'unread',
    open: 'Open notifications',
};

const formatNotificationTime = (value) => {
    if (!value) {
        return '';
    }

    try {
        return new Intl.DateTimeFormat('en-US', {
            dateStyle: 'short',
            timeStyle: 'short',
        }).format(new Date(value));
    } catch {
        return '';
    }
};

const notificationList = computed(() => notificationItems.value);

const loadNotifications = async ({ force = false } = {}) => {
    if (!user.value) {
        notificationItems.value = [];
        notificationsLoaded.value = false;
        return;
    }

    if (notificationsLoading.value || (notificationsLoaded.value && !force)) {
        return;
    }

    notificationsLoading.value = true;

    try {
        const response = await window.fetch('/notifications/preview', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Notification preview request failed with ${response.status}`);
        }

        const payload = await response.json();

        notificationItems.value = Array.isArray(payload?.items) ? payload.items : [];
        notificationsLoaded.value = true;
    } catch {
        if (!notificationsLoaded.value) {
            notificationItems.value = [];
        }
    } finally {
        notificationsLoading.value = false;
    }
};

const toggleNotificationMenu = async () => {
    notificationMenuOpen.value = !notificationMenuOpen.value;

    if (notificationMenuOpen.value) {
        await loadNotifications();
    }
};

const toggleMessenger = async () => {
    accountMenuOpen.value = false;
    notificationMenuOpen.value = false;
    mobileMenuOpen.value = false;

    if (messenger.state.isOpen) {
        messenger.closeMessenger();
        return;
    }

    await messenger.openDirectory();
};

const markNotificationAsRead = (notification, onDone = null) => {
    router.post(`/notifications/${notification.id}/read`, {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            notificationItems.value = notificationItems.value.map((item) => (
                item.id === notification.id
                    ? { ...item, read_at: item.read_at || new Date().toISOString() }
                    : item
            ));

            if (typeof onDone === 'function') {
                onDone();
            }
        },
    });
};

const markAllNotificationsAsRead = () => {
    router.post('/notifications/read-all', {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            const nowIso = new Date().toISOString();
            notificationItems.value = notificationItems.value.map((item) => ({
                ...item,
                read_at: item.read_at || nowIso,
            }));
        },
    });
};

const openNotification = (notification) => {
    const finish = () => {
        notificationMenuOpen.value = false;

        if (notification.action_url) {
            try {
                const targetUrl = new URL(notification.action_url, window.location.origin);

                if (targetUrl.pathname !== page.url) {
                    router.visit(targetUrl.pathname + targetUrl.search + targetUrl.hash);
                }
            } catch {
                router.visit(notification.action_url);
            }
        }
    };

    if (!notification.read_at) {
        markNotificationAsRead(notification, finish);
        return;
    }

    finish();
};

watch(() => page.url, () => {
    mobileMenuOpen.value = false;
    notificationMenuOpen.value = false;
    accountMenuOpen.value = false;
    notificationsLoaded.value = false;
    navbarVisible.value = true;
    messenger.closeMessenger();

    if (user.value) {
        void messenger.reloadConversations({ preserveSelection: true, silent: true });
    }
});

watch(user, (value) => {
    void messenger.bootstrapMessenger(value);

    if (!value) {
        notificationItems.value = [];
        notificationsLoaded.value = false;
    }
}, { immediate: true });

watch(flashSuccess, (value) => {
    if (value) {
        showToast(value, 'success');
    }
}, { immediate: true });

watch(flashError, (value) => {
    if (value) {
        showToast(value, 'error');
    }
}, { immediate: true });

const handleWindowScroll = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const currentScrollY = window.scrollY || 0;

    if (mobileMenuOpen.value || notificationMenuOpen.value || accountMenuOpen.value) {
        navbarVisible.value = true;
        lastScrollY.value = currentScrollY;
        return;
    }

    if (currentScrollY <= 16) {
        navbarVisible.value = true;
        lastScrollY.value = currentScrollY;
        return;
    }

    const delta = currentScrollY - lastScrollY.value;

    if (delta > 8) {
        navbarVisible.value = false;
    } else if (delta < -8) {
        navbarVisible.value = true;
    }

    lastScrollY.value = currentScrollY;
};

watch([mobileMenuOpen, notificationMenuOpen, accountMenuOpen], ([mobileOpen, notificationOpen, accountOpen]) => {
    if (mobileOpen || notificationOpen || accountOpen) {
        navbarVisible.value = true;
    }
});

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    lastScrollY.value = window.scrollY || 0;
    window.addEventListener('scroll', handleWindowScroll, { passive: true });
});

onBeforeUnmount(() => {
    if (typeof window === 'undefined') {
        return;
    }

    window.removeEventListener('scroll', handleWindowScroll);
    messenger.teardownMessenger();
});
</script>

<template>
    <div class="layout-shell">
        <transition name="toast-fade">
            <div v-if="toast" :key="toast.id" :class="['toast-notice', `is-${toast.tone}`]">
                {{ toast.message }}
            </div>
        </transition>

        <div class="page-shell">
            <header :class="['topbar', { 'is-hidden': !navbarVisible }]">
                <Link class="brand" href="/">
                    <BrandLogo class="brand-mark" :size="46" alt="Sea Requests logo" />
                    <div class="brand-copy">
                        <strong>{{ t.brand.name }}</strong>
                        <span>{{ t.brand.tagline }}</span>
                    </div>
                </Link>

                <button class="menu-toggle" type="button" :aria-expanded="mobileMenuOpen" :class="{ open: mobileMenuOpen }" aria-label="Toggle navigation" @click="mobileMenuOpen = !mobileMenuOpen">
                    <span class="menu-line top"></span>
                    <span class="menu-line middle"></span>
                    <span class="menu-line bottom"></span>
                </button>

                <nav :class="['nav', { 'is-open': mobileMenuOpen }]">
                    <Link :class="['nav-link', { active: isActive('/') && page.url === '/' }]" href="/">{{ t.nav.home }}</Link>
                    <Link :class="['nav-link', { active: isActive('/requests') }]" href="/requests">{{ t.nav.requests }}</Link>
                    <Link :class="['nav-link', { active: isActive('/services') }]" href="/services">{{ t.nav.services }}</Link>
                    <Link
                        v-if="user"
                        :class="['nav-link', { active: isActive('/dashboard') || isActive('/admin') }]"
                        href="/dashboard"
                    >
                        {{ t.nav.dashboard }}
                    </Link>
                    <Link v-else :class="['nav-link', { active: isActive('/register') }]" href="/register">{{ t.nav.register }}</Link>
                    <Link v-if="!user" :class="['nav-link', { active: isActive('/login') }]" href="/login">{{ t.nav.login }}</Link>

                    <div class="nav-mobile-actions">
                        <div v-if="user" class="notifications-mobile">
                            <button
                                class="notifications-mobile-trigger"
                                type="button"
                                @click="toggleMessenger"
                            >
                                Messenger
                                <span v-if="messengerUnreadCount" class="notifications-mobile-badge">{{ messengerUnreadCount }}</span>
                            </button>

                            <button
                                class="notifications-mobile-trigger"
                                type="button"
                                :aria-expanded="notificationMenuOpen"
                                @click="toggleNotificationMenu"
                            >
                                {{ notificationText.title }}
                                <span v-if="notifications.unread_count" class="notifications-mobile-badge">{{ notifications.unread_count }}</span>
                            </button>

                            <div v-if="notificationMenuOpen" class="notifications-mobile-panel">
                                <button
                                    v-if="notifications.unread_count"
                                    class="notifications-action mobile-action"
                                    type="button"
                                    @click="markAllNotificationsAsRead"
                                >
                                    {{ notificationText.allRead }}
                                </button>

                                <div v-if="notificationList.length" class="notifications-list mobile-list">
                                    <button
                                        v-for="notification in notificationList"
                                        :key="notification.id"
                                        :class="['notification-item', `is-${notification.tone}`, { unread: !notification.read_at }]"
                                        type="button"
                                        @click="openNotification(notification)"
                                    >
                                        <div class="notification-copy">
                                            <strong>{{ notification.title }}</strong>
                                            <p>{{ notification.message }}</p>
                                            <span>{{ formatNotificationTime(notification.created_at) }}</span>
                                        </div>
                                    </button>
                                </div>

                                <div v-if="notificationList.length" class="notifications-footer mobile-footer">
                                    <Link class="notifications-view-all" href="/notifications" @click="notificationMenuOpen = false">
                                        {{ notificationText.viewAll }}
                                    </Link>
                                </div>

                                <div v-else-if="notificationsLoading" class="notifications-empty mobile-empty">
                                    {{ notificationText.loading }}
                                </div>

                                <div v-else class="notifications-empty mobile-empty">
                                    {{ notificationText.empty }}
                                </div>
                            </div>
                        </div>

                        <div v-if="user" class="mobile-account-card">
                            <strong>{{ accountName }}</strong>
                            <div class="mobile-account-actions">
                                <Link class="mobile-account-link" :href="profileHref">{{ t.nav.editProfile }}</Link>
                                <Link class="mobile-account-link" href="/logout" method="post" as="button">{{ t.nav.logout }}</Link>
                            </div>
                        </div>
                    </div>
                </nav>

                <div class="topbar-side">
                    <div v-if="user" class="notifications-shell">
                        <button
                            class="notifications-trigger"
                            type="button"
                            aria-label="Open messenger"
                            @click="toggleMessenger"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M7 10h10" />
                                <path d="M7 14h6" />
                                <path d="M5 5h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H9l-4 3v-3H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" />
                            </svg>
                            <span v-if="messengerUnreadCount" class="notifications-badge">{{ messengerUnreadCount }}</span>
                        </button>
                    </div>

                    <div v-if="user" class="notifications-shell">
                        <button
                            class="notifications-trigger"
                            type="button"
                            :aria-expanded="notificationMenuOpen"
                            :aria-label="notificationText.open"
                            @click="toggleNotificationMenu"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M15 17h5l-1.4-1.4a2 2 0 0 1-.6-1.4V11a6 6 0 0 0-4-5.66V4a2 2 0 1 0-4 0v1.34A6 6 0 0 0 6 11v3.2a2 2 0 0 1-.6 1.4L4 17h5" />
                                <path d="M9.5 17a2.5 2.5 0 0 0 5 0" />
                            </svg>
                            <span v-if="notifications.unread_count" class="notifications-badge">{{ notifications.unread_count }}</span>
                        </button>

                        <div v-if="notificationMenuOpen" class="notifications-panel">
                            <div class="notifications-head">
                                <div>
                                    <strong>{{ notificationText.title }}</strong>
                                    <span v-if="notifications.unread_count">{{ notifications.unread_count }} {{ notificationText.unread }}</span>
                                </div>
                                <button
                                    v-if="notifications.unread_count"
                                    class="notifications-action"
                                    type="button"
                                    @click="markAllNotificationsAsRead"
                                >
                                    {{ notificationText.allRead }}
                                </button>
                            </div>

                            <div v-if="notificationList.length" class="notifications-list">
                                <button
                                    v-for="notification in notificationList"
                                    :key="notification.id"
                                    :class="['notification-item', `is-${notification.tone}`, { unread: !notification.read_at }]"
                                    type="button"
                                    @click="openNotification(notification)"
                                >
                                    <div class="notification-copy">
                                        <strong>{{ notification.title }}</strong>
                                        <p>{{ notification.message }}</p>
                                        <span>{{ formatNotificationTime(notification.created_at) }}</span>
                                    </div>
                                </button>
                            </div>

                            <div v-if="notificationList.length" class="notifications-footer">
                                <Link class="notifications-view-all" href="/notifications" @click="notificationMenuOpen = false">
                                    {{ notificationText.viewAll }}
                                </Link>
                            </div>

                            <div v-else-if="notificationsLoading" class="notifications-empty">
                                {{ notificationText.loading }}
                            </div>

                            <div v-else class="notifications-empty">
                                {{ notificationText.empty }}
                            </div>
                        </div>
                    </div>

                    <div v-if="user" class="account-shell">
                        <button
                            class="account-trigger"
                            type="button"
                            :aria-expanded="accountMenuOpen"
                            :aria-label="t.nav.accountMenu"
                            @click="accountMenuOpen = !accountMenuOpen"
                        >
                            <span class="account-name">{{ accountName }}</span>
                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M5 7.5 10 12.5l5-5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>

                        <div v-if="accountMenuOpen" class="account-menu">
                            <Link class="account-menu-link" :href="profileHref" @click="accountMenuOpen = false">
                                {{ t.nav.editProfile }}
                            </Link>
                            <Link class="account-menu-link danger" href="/logout" method="post" as="button" @click="accountMenuOpen = false">
                                {{ t.nav.logout }}
                            </Link>
                        </div>
                    </div>
                </div>
            </header>

        <slot :t="t" />
        <MessengerDrawer />
        </div>

        <footer class="site-footer">
            <div class="footer-inner">
                <div class="footer-grid">
                    <div class="footer-brand">
                        <BrandLogo class="footer-mark" :size="54" alt="Sea Requests logo" />
                        <div class="footer-copy footer-brand-copy">
                            <strong>{{ footerBrand.name }}</strong>
                            <p class="footer-brand-text">{{ footerBrand.text }}</p>
                        </div>
                    </div>

                    <div class="footer-section-strip">
                        <div
                            v-for="section in footerSections"
                            :key="section.title"
                            class="footer-column"
                        >
                            <strong class="footer-column-title">{{ section.title }}</strong>
                            <div class="footer-link-list">
                                <Link
                                    v-for="link in section.links"
                                    :key="link.href"
                                    :href="link.href"
                                    class="footer-link"
                                >
                                    {{ link.label }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="footer-bottom">
                    <p class="footer-meta">&#169; 2026 {{ footerBrand.name }}. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.layout-shell {
    min-height: 100vh;
}

.toast-notice {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 80;
    width: min(420px, calc(100vw - 32px));
    padding: 16px 18px;
    border-radius: 10px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    box-shadow: 0 18px 34px rgba(15, 23, 42, 0.14);
    backdrop-filter: blur(18px);
    font-weight: 600;
    line-height: 1.45;
}

.toast-notice.is-success {
    background: rgba(220, 239, 232, 0.96);
    color: #0e5c56;
    border-color: rgba(15, 118, 110, 0.18);
}

.toast-notice.is-error {
    background: rgba(254, 242, 242, 0.96);
    color: #9f1239;
    border-color: rgba(225, 29, 72, 0.18);
}

.toast-fade-enter-active,
.toast-fade-leave-active {
    transition: opacity 180ms ease, transform 180ms ease;
}

.toast-fade-enter-from,
.toast-fade-leave-to {
    opacity: 0;
    transform: translate3d(0, -8px, 0);
}

.page-shell {
    width: min(1280px, calc(100% - 32px));
    margin: 0 auto;
    padding: 0 0 48px;
}

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 14px 18px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.75);
    backdrop-filter: blur(18px);
    position: sticky;
    top: 0;
    z-index: 20;
    transition: transform 180ms ease, opacity 180ms ease;
    will-change: transform, opacity;
}

.topbar.is-hidden {
    transform: translate3d(0, calc(-100% - 12px), 0);
    opacity: 0.96;
}

.brand {
    display: flex;
    align-items: center;
    gap: 14px;
}

.brand strong,
.footer-copy strong {
    font-family: var(--font-display);
}

.brand-copy,
.footer-copy {
    display: grid;
}

.brand span:last-child,
.footer-copy span,
.footer-meta {
    color: rgba(4, 21, 31, 0.68);
}

.brand-mark,
.footer-mark {
    flex-shrink: 0;
}

.footer-mark {
    margin-top: 2px;
}

.nav {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 20px;
    min-width: 0;
}

.nav-mobile-actions,
.menu-toggle {
    display: none;
}

.nav-link {
    color: rgba(4, 21, 31, 0.76);
    font-weight: 460;
}

.nav-link.active {
    color: var(--color-ink);
}

.topbar-side {
    display: flex;
    align-items: center;
    gap: 14px;
}

.account-shell {
    position: relative;
}

.notifications-shell {
    position: relative;
}

.notifications-trigger,
.notifications-mobile-trigger {
    position: relative;
    border: 0;
    border-radius: 999px;
    background: rgba(4, 21, 31, 0.08);
    color: var(--color-ink);
    font-weight: 540;
}

.account-trigger {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-height: 44px;
    padding: 0 14px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.9);
    color: var(--color-ink);
    font-size: 0.92rem;
    font-weight: 500;
}

.account-name {
    max-width: 160px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.account-trigger svg {
    width: 16px;
    height: 16px;
    color: rgba(4, 21, 31, 0.58);
}

.notifications-trigger {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
}

.notifications-trigger svg {
    width: 20px;
    height: 20px;
}

.notifications-badge,
.notifications-mobile-badge {
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: #be123c;
    color: white;
    font-size: 0.72rem;
    font-weight: 700;
}

.notifications-badge {
    position: absolute;
    top: -4px;
    right: -4px;
}

.notifications-panel,
.notifications-mobile-panel,
.account-menu {
    background: rgba(255, 255, 255, 0.97);
    border: 1px solid rgba(4, 21, 31, 0.08);
    box-shadow: 0 18px 34px rgba(15, 23, 42, 0.12);
    backdrop-filter: blur(16px);
}

.notifications-panel {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    width: min(380px, calc(100vw - 32px));
    border-radius: 10px;
    overflow: hidden;
}

.notifications-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 18px 14px;
    border-bottom: 1px solid rgba(4, 21, 31, 0.06);
}

.account-menu {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    min-width: 220px;
    padding: 10px;
    border-radius: 10px;
    display: grid;
    gap: 6px;
}

.account-menu-link {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    min-height: 42px;
    padding: 0 12px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--color-ink);
    font-size: 0.9rem;
    font-weight: 500;
    text-align: left;
}

.account-menu-link:hover {
    background: rgba(4, 21, 31, 0.05);
}

.account-menu-link.danger {
    color: #b91c1c;
}

.notifications-head strong {
    display: block;
}

.notifications-head span {
    display: block;
    margin-top: 4px;
    color: rgba(4, 21, 31, 0.62);
    font-size: 0.82rem;
    font-weight: 500;
}

.notifications-action {
    border: 0;
    background: transparent;
    color: #0f766e;
    font-weight: 540;
    font-size: 0.85rem;
    padding: 0;
}

.notifications-list {
    display: grid;
    gap: 10px;
    padding: 14px;
    max-height: 420px;
    overflow-y: auto;
}

.notification-item {
    width: 100%;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: white;
    padding: 14px 15px;
    text-align: left;
}

.notification-item.unread {
    border-color: rgba(15, 118, 110, 0.24);
    background: rgba(240, 253, 250, 0.92);
}

.notification-item.is-success.unread {
    background: rgba(236, 253, 245, 0.98);
}

.notification-item.is-error.unread {
    background: rgba(255, 241, 242, 0.98);
}

.notification-copy {
    display: grid;
    gap: 6px;
}

.notification-copy strong {
    font-size: 0.96rem;
}

.notification-copy p {
    margin: 0;
    color: rgba(4, 21, 31, 0.72);
    line-height: 1.45;
    font-size: 0.9rem;
}

.notification-copy span {
    color: rgba(4, 21, 31, 0.5);
    font-size: 0.78rem;
    font-weight: 500;
}

.notifications-empty {
    padding: 22px 18px 24px;
    color: rgba(4, 21, 31, 0.64);
    font-weight: 500;
}

.notifications-footer {
    padding: 0 14px 14px;
}

.notifications-view-all {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 11px 14px;
    border-radius: 10px;
    background: rgba(4, 21, 31, 0.06);
    color: var(--color-ink);
    font-weight: 540;
}

.menu-toggle {
    width: 48px;
    height: 48px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.88);
    align-items: center;
    justify-content: center;
    padding: 0;
    box-shadow: 0 12px 24px rgba(4, 21, 31, 0.08);
}

.menu-line {
    position: absolute;
    width: 18px;
    height: 2px;
    border-radius: 999px;
    background: var(--color-ink);
    transition: transform 180ms ease, opacity 180ms ease, width 180ms ease;
}

.menu-line.top {
    transform: translateY(-6px);
}

.menu-line.bottom {
    transform: translateY(6px);
}

.menu-toggle.open .menu-line.top {
    transform: rotate(45deg);
}

.menu-toggle.open .menu-line.middle {
    opacity: 0;
    width: 0;
}

.menu-toggle.open .menu-line.bottom {
    transform: rotate(-45deg);
}

.site-footer {
    width: 100%;
    border-top: 1px solid rgba(4, 21, 31, 0.08);
    background:
        linear-gradient(180deg, rgba(248, 250, 252, 0.82), rgba(255, 255, 255, 0.98)),
        radial-gradient(circle at left top, rgba(14, 116, 144, 0.08), transparent 28%);
}

.footer-inner {
    width: min(1280px, calc(100% - 32px));
    margin: 0 auto;
    padding: 36px 0 26px;
}

.footer-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) repeat(3, minmax(0, 1fr));
    gap: 24px;
    align-items: start;
}

.footer-brand {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    min-width: 0;
}

.footer-brand-copy {
    gap: 10px;
}

.footer-brand-copy strong {
    font-size: 1.08rem;
    line-height: 1.15;
    color: var(--color-ink);
}

.footer-brand-text {
    margin: 0;
    max-width: 42ch;
    color: rgba(4, 21, 31, 0.72);
    font-size: 0.95rem;
    line-height: 1.7;
}

.footer-column {
    display: grid;
    gap: 14px;
}

.footer-section-strip {
    display: contents;
}

.footer-column-title {
    color: rgba(4, 21, 31, 0.92);
    font-size: 0.92rem;
    font-weight: 600;
    line-height: 1.2;
}

.footer-link-list {
    display: grid;
    gap: 11px;
}

.footer-link {
    color: rgba(4, 21, 31, 0.76);
    font-size: 0.95rem;
    font-weight: 460;
    line-height: 1.45;
    text-decoration: none;
    transition: color 180ms ease, transform 180ms ease;
}

.footer-link:hover {
    color: var(--color-ink);
    transform: translateX(2px);
}

.footer-bottom {
    margin-top: 24px;
    padding-top: 18px;
    border-top: 1px solid rgba(4, 21, 31, 0.08);
}

.footer-meta {
    margin: 0;
    color: rgba(4, 21, 31, 0.62);
    font-size: 0.88rem;
    font-weight: 460;
    line-height: 1.5;
}

@media (max-width: 1120px) {
    .topbar {
        position: static;
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: center;
    }

    .footer-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 960px) {
    .footer-inner {
        padding: 28px 0 24px;
    }
}

@media (max-width: 720px) {
    .toast-notice {
        top: 14px;
        right: 16px;
    }

    .topbar {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .topbar {
        position: sticky;
        top: 0;
        padding: 14px;
    }

    .brand {
        justify-content: flex-start;
    }

    .topbar-side {
        display: none;
    }

    .menu-toggle {
        display: inline-flex;
        position: absolute;
        top: 13px;
        right: 14px;
        overflow: hidden;
    }

    .nav {
        display: none;
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
        width: 100%;
        padding-top: 6px;
    }

    .nav.is-open {
        display: flex;
    }

    .nav-link {
        display: block;
        padding: 12px 14px;
        border-radius: 10px;
        background: rgba(4, 21, 31, 0.04);
        white-space: normal;
    }

    .nav-mobile-actions {
        display: grid;
        gap: 10px;
        padding-top: 4px;
    }

    .footer-inner {
        width: min(1280px, calc(100% - 24px));
        padding: 24px 0 20px;
    }

    .footer-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .footer-section-strip {
        display: flex;
        gap: 12px;
        min-width: 0;
        overflow-x: auto;
        padding-bottom: 4px;
        scroll-snap-type: x proximity;
        scrollbar-width: thin;
        -webkit-overflow-scrolling: touch;
    }

    .footer-brand-text {
        max-width: none;
    }

    .footer-column {
        gap: 12px;
        flex: 0 0 min(220px, calc(100vw - 84px));
        padding: 16px 18px;
        border: 1px solid rgba(4, 21, 31, 0.08);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.72);
        scroll-snap-align: start;
    }

    .footer-link-list {
        gap: 10px;
    }

    .footer-bottom {
        margin-top: 18px;
        padding-top: 16px;
    }

    .mobile-account-card {
        display: grid;
        gap: 10px;
        padding: 14px;
        border: 1px solid rgba(4, 21, 31, 0.08);
        border-radius: 10px;
        background: rgba(4, 21, 31, 0.03);
    }

    .mobile-account-card strong {
        font-size: 0.95rem;
        font-weight: 560;
        color: var(--color-ink);
    }

    .mobile-account-actions {
        display: grid;
        gap: 8px;
    }

    .mobile-account-link {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 0 12px;
        border: 1px solid rgba(4, 21, 31, 0.08);
        border-radius: 8px;
        background: #fff;
        color: var(--color-ink);
        font-size: 0.9rem;
        font-weight: 500;
    }

    .notifications-mobile {
        display: grid;
        gap: 10px;
    }

    .notifications-mobile-trigger {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 14px;
        border-radius: 10px;
    }

    .notifications-mobile-panel {
        border-radius: 10px;
        padding: 12px;
    }

    .mobile-action {
        justify-self: flex-start;
        margin-bottom: 10px;
    }

    .mobile-list {
        max-height: none;
        padding: 0;
    }

    .mobile-empty {
        padding: 8px 2px 2px;
    }

    .mobile-logout {
        justify-content: center;
    }

    
}
</style>
