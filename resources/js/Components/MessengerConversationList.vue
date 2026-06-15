<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    conversations: {
        type: Array,
        default: () => [],
    },
    activeOfferId: {
        type: Number,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['select']);

const copy = {
    title: 'Messenger',
    empty: 'No order conversation is available yet.',
    loading: 'Loading conversations...',
    openProfile: 'Open profile',
    openOrder: 'Open order',
};

const formatTime = (value) => {
    if (!value) return '';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};
</script>

<template>
    <aside class="messenger-list">
        <div class="messenger-list-head">
            <h2>{{ copy.title }}</h2>
        </div>

        <div v-if="loading" class="messenger-list-state">
            {{ copy.loading }}
        </div>

        <div v-else-if="error" class="messenger-list-state messenger-list-error">
            {{ error }}
        </div>

        <div v-else-if="!conversations.length" class="messenger-list-state">
            {{ copy.empty }}
        </div>

        <div v-else class="messenger-list-body">
            <article
                v-for="conversation in conversations"
                :key="conversation.offer_id"
                class="conversation-card"
            >
                <div v-if="conversation.counterparty_profile_url || conversation.order_url" class="conversation-item-actions">
                    <Link
                        v-if="conversation.counterparty_profile_url"
                        class="conversation-action-link"
                        :href="conversation.counterparty_profile_url"
                        :title="copy.openProfile"
                        :aria-label="copy.openProfile"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21a8 8 0 0 0-16 0" />
                            <circle cx="12" cy="8" r="4" />
                        </svg>
                    </Link>
                    <Link
                        v-if="conversation.order_url"
                        class="conversation-action-link"
                        :href="conversation.order_url"
                        :title="copy.openOrder"
                        :aria-label="copy.openOrder"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 3h6l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3Z" />
                            <path d="M9 3v5h5" />
                            <path d="M8 13h8" />
                            <path d="M8 17h6" />
                        </svg>
                    </Link>
                </div>

                <button
                    type="button"
                    :class="['conversation-item', { active: conversation.offer_id === activeOfferId }]"
                    @click="emit('select', conversation.offer_id)"
                >
                    <div class="conversation-item-head">
                        <strong>{{ conversation.counterparty_name }}</strong>
                        <span>{{ formatTime(conversation.last_message_at) }}</span>
                    </div>
                    <div class="conversation-item-meta">
                        <span>{{ conversation.counterparty_role }}</span>
                        <span>{{ conversation.reference_no }}</span>
                    </div>
                    <p class="conversation-item-text">{{ conversation.last_message_excerpt }}</p>
                    <div class="conversation-item-foot">
                        <span class="conversation-order-status">{{ conversation.order_workflow_status_label }}</span>
                        <span v-if="conversation.unread_count" class="conversation-unread">{{ conversation.unread_count }}</span>
                    </div>
                </button>
            </article>
        </div>
    </aside>
</template>

<style scoped>
.messenger-list{display:grid;grid-template-rows:auto minmax(0,1fr);border-right:1px solid rgba(148,163,184,.18);background:#fff;min-width:280px}
.messenger-list-head{padding:18px 18px 14px;border-bottom:1px solid rgba(148,163,184,.18)}
.messenger-list-head h2{margin:0;color:#0f172a;font-size:1rem;font-weight:700}
.messenger-list-body{overflow:auto;padding:10px}
.messenger-list-state{padding:20px 18px;color:#64748b;font-size:.92rem;line-height:1.6}
.messenger-list-error{color:#9f1239}
.conversation-card{position:relative}
.conversation-item-actions{position:absolute;top:12px;right:12px;z-index:1;display:flex;align-items:center;gap:6px}
.conversation-action-link{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:999px;background:#fff;border:1px solid rgba(148,163,184,.22);color:#2563eb;box-shadow:0 6px 16px rgba(15,23,42,.06)}
.conversation-action-link svg{width:14px;height:14px}
.conversation-item{width:100%;display:grid;gap:8px;padding:14px 84px 12px 14px;border:1px solid transparent;border-radius:14px;background:transparent;text-align:left;cursor:pointer;transition:background .16s ease,border-color .16s ease}
.conversation-item:hover{background:#f8fafc;border-color:rgba(148,163,184,.16)}
.conversation-item.active{background:#eff6ff;border-color:#bfdbfe}
.conversation-item-head,.conversation-item-meta,.conversation-item-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;min-width:0}
.conversation-item-head strong{color:#0f172a;font-size:.94rem;font-weight:700;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.conversation-item-head span,.conversation-item-meta span,.conversation-item-foot span{color:#64748b;font-size:.77rem;line-height:1.4}
.conversation-item-head span{white-space:nowrap;flex:0 0 auto}
.conversation-item-text{margin:0;color:#334155;font-size:.85rem;line-height:1.55;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.conversation-order-status{max-width:170px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.conversation-unread{min-width:22px;height:22px;padding:0 7px;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:#2563eb;color:#fff !important;font-size:.74rem;font-weight:700}
@media (max-width: 980px){
    .messenger-list{min-width:100%;border-right:0;border-bottom:1px solid rgba(148,163,184,.18);max-height:240px}
}
@media (max-width: 640px){
    .conversation-item{padding-right:76px}
    .conversation-item-head{align-items:flex-start}
}
</style>
