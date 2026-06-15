<script setup>
import MainLayout from '@/Layouts/MainLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    notificationsPage: Object,
});

const page = usePage();

const copy = {
    eyebrow: 'Notification Center',
    title: 'All your notifications',
    text: 'You can see every update related to registration, applications, approvals and account activity here.',
    emptyTitle: 'You do not have any notifications yet.',
    emptyText: 'Notifications will appear here when a new action happens.',
    allRead: 'Mark all as read',
    unread: 'Unread',
    read: 'Read',
    open: 'Open details',
    markRead: 'Mark as read',
    previous: 'Previous',
    next: 'Next',
};

const hasUnread = computed(() => (page.props.notifications?.unread_count ?? 0) > 0);

const formatNotificationTime = (value) => {
    if (!value) return '';

    try {
        return new Intl.DateTimeFormat('en-GB', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value));
    } catch {
        return '';
    }
};

const markAllAsRead = () => {
    router.post('/notifications/read-all', {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const markAsRead = (notificationId) => {
    router.post(`/notifications/${notificationId}/read`, {}, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Head title="Notifications" />

    <MainLayout>
        <section class="notifications-page">
            <header class="directory-intro-card notifications-hero">
                <div>
                    <p class="directory-eyebrow">{{ copy.eyebrow }}</p>
                    <h1 class="directory-page-title">{{ copy.title }}</h1>
                    <p class="directory-intro-copy">{{ copy.text }}</p>
                </div>

                <button
                    v-if="hasUnread"
                    class="mark-all-button"
                    type="button"
                    @click="markAllAsRead"
                >
                    {{ copy.allRead }}
                </button>
            </header>

            <div v-if="notificationsPage.data.length" class="notifications-list-page">
                <article
                    v-for="notification in notificationsPage.data"
                    :key="notification.id"
                    :class="['notification-card', `is-${notification.tone}`, { unread: !notification.read_at }]"
                >
                    <div class="notification-card-head">
                        <div>
                            <span :class="['status-pill', { unread: !notification.read_at }]">
                                {{ notification.read_at ? copy.read : copy.unread }}
                            </span>
                            <h2 class="directory-card-title">{{ notification.title }}</h2>
                        </div>

                        <time>{{ formatNotificationTime(notification.created_at) }}</time>
                    </div>

                    <p class="notification-message">{{ notification.message }}</p>

                    <div v-if="notification.details?.length" class="notification-details">
                        <div
                            v-for="(detail, index) in notification.details"
                            :key="`${notification.id}-detail-${index}`"
                            class="notification-detail"
                        >
                            <span>{{ detail.label }}</span>
                            <strong>{{ detail.value }}</strong>
                        </div>
                    </div>

                    <div class="notification-actions">
                        <button
                            v-if="!notification.read_at"
                            class="secondary-action"
                            type="button"
                            @click="markAsRead(notification.id)"
                        >
                            {{ copy.markRead }}
                        </button>

                        <Link
                            v-if="notification.action_url"
                            class="primary-action"
                            :href="notification.action_url"
                        >
                            {{ notification.action_label || copy.open }}
                        </Link>
                    </div>
                </article>
            </div>

            <div v-else class="empty-state surface-panel">
                <h2 class="directory-section-title">{{ copy.emptyTitle }}</h2>
                <p>{{ copy.emptyText }}</p>
            </div>

            <div v-if="notificationsPage.last_page > 1" class="pagination">
                <Link
                    class="pagination-link"
                    :class="{ disabled: !notificationsPage.prev_page_url }"
                    :href="notificationsPage.prev_page_url || '#'"
                    :preserve-scroll="true"
                >
                    {{ copy.previous }}
                </Link>

                <span class="pagination-meta">{{ notificationsPage.current_page }} / {{ notificationsPage.last_page }}</span>

                <Link
                    class="pagination-link"
                    :class="{ disabled: !notificationsPage.next_page_url }"
                    :href="notificationsPage.next_page_url || '#'"
                    :preserve-scroll="true"
                >
                    {{ copy.next }}
                </Link>
            </div>
        </section>
    </MainLayout>
</template>

<style scoped>
.notifications-page{padding:16px 0 56px}
.notifications-hero{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:24px}
.notifications-hero :deep(.directory-intro-copy){margin-top:16px;max-width:72ch}
.mark-all-button,.primary-action,.secondary-action,.pagination-link{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border-radius:10px;font-size:.84rem;font-weight:600}
.mark-all-button,.secondary-action,.pagination-link{border:1px solid rgba(4,21,31,.08);background:#fff;color:#0f172a}
.primary-action{border:1px solid #0f172a;background:#0f172a;color:#fff}
.notifications-list-page{display:grid;gap:14px}
.notification-card{padding:22px;border-radius:10px;border:1px solid rgba(4,21,31,.08);background:rgba(255,255,255,.94);box-shadow:0 18px 30px rgba(15,23,42,.06)}
.notification-card.unread{border-color:rgba(15,118,110,.2);background:rgba(248,255,252,.96)}
.notification-card.is-error.unread{background:rgba(255,244,246,.98)}
.notification-card-head{display:flex;align-items:start;justify-content:space-between;gap:16px}
.notification-card-head :deep(.directory-card-title){margin:12px 0 0}
.notification-card-head time{color:rgba(4,21,31,.48);font-size:.82rem;font-weight:600;white-space:nowrap}
.status-pill{display:inline-flex;align-items:center;padding:6px 10px;border-radius:10px;background:rgba(4,21,31,.08);color:rgba(4,21,31,.78);font-size:.74rem;font-weight:600}
.status-pill.unread{background:rgba(15,118,110,.12);color:#0f766e}
.notification-message{margin:14px 0 0;color:rgba(4,21,31,.72);line-height:1.7}
.notification-details{display:grid;gap:10px;margin-top:16px}
.notification-detail{display:grid;gap:6px;padding:14px 16px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(248,250,252,.84)}
.notification-detail span{color:#64748b;font-size:.76rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase}
.notification-detail strong{color:#020617;font-size:.94rem;font-weight:560;line-height:1.6}
.notification-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}
.surface-panel{border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.94);box-shadow:0 18px 30px rgba(15,23,42,.06)}
.empty-state{padding:34px 28px}
.empty-state :deep(.directory-section-title){margin:0 0 10px}
.empty-state p{margin:0;color:rgba(4,21,31,.7)}
.pagination{display:flex;align-items:center;justify-content:center;gap:12px;margin-top:22px}
.pagination-link.disabled{pointer-events:none;opacity:.45}
.pagination-meta{color:rgba(4,21,31,.56);font-weight:600}
@media (max-width: 720px){
    .notifications-hero{flex-direction:column;align-items:stretch}
    .notification-card-head{flex-direction:column}
    .notification-card-head time{white-space:normal}
    .notification-actions{flex-direction:column}
    .primary-action,.secondary-action,.mark-all-button,.pagination-link{width:100%;text-align:center}
}
</style>
