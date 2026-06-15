<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import MessengerComposer from './MessengerComposer.vue';

const props = defineProps({
    conversation: {
        type: Object,
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
    sending: {
        type: Boolean,
        default: false,
    },
    composerError: {
        type: String,
        default: '',
    },
    sendAction: {
        type: Function,
        required: true,
    },
});

const copy = {
    empty: 'Select an order conversation to start chatting.',
    loading: 'Loading conversation...',
    files: 'Attachment',
    noMessages: 'No messages yet. Start the conversation here.',
    openProfile: 'Open profile',
    openRfq: 'Open RFQ',
};

const threadBody = ref(null);

const formatTooltipTime = (value) => {
    if (!value) return '';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};

const statusLine = computed(() => {
    if (!props.conversation) {
        return '';
    }

    return `${props.conversation.reference_no} - ${props.conversation.order_workflow_status_label}`;
});

const headerLine = computed(() => {
    if (!props.conversation) {
        return '';
    }

    return `${props.conversation.counterparty_name} - ${props.conversation.counterparty_role} - ${statusLine.value}`;
});

const scrollToBottom = async () => {
    await nextTick();

    if (threadBody.value) {
        threadBody.value.scrollTop = threadBody.value.scrollHeight;
    }
};

watch(
    () => props.conversation?.messages?.length,
    () => {
        void scrollToBottom();
    }
);

watch(
    () => props.conversation?.offer_id,
    () => {
        void scrollToBottom();
    }
);
</script>

<template>
    <section class="messenger-thread">
        <div v-if="loading" class="messenger-thread-state">
            {{ copy.loading }}
        </div>

        <div v-else-if="error" class="messenger-thread-state messenger-thread-error">
            {{ error }}
        </div>

        <div v-else-if="!conversation" class="messenger-thread-state">
            {{ copy.empty }}
        </div>

        <template v-else>
            <header class="messenger-thread-head">
                <p class="messenger-thread-line" :title="headerLine">
                    <Link
                        v-if="conversation.counterparty_profile_url"
                        class="thread-link-primary"
                        :href="conversation.counterparty_profile_url"
                        :title="copy.openProfile"
                    >
                        {{ conversation.counterparty_name }}
                    </Link>
                    <strong v-else>{{ conversation.counterparty_name }}</strong>
                    <span>
                        {{ conversation.counterparty_role }} -
                        <Link
                            v-if="conversation.rfq_url"
                            class="thread-link-secondary"
                            :href="conversation.rfq_url"
                            :title="copy.openRfq"
                        >
                            {{ conversation.reference_no }}
                        </Link>
                        <template v-else>{{ conversation.reference_no }}</template>
                        - {{ conversation.order_workflow_status_label }}
                    </span>
                </p>
            </header>

            <div ref="threadBody" class="messenger-thread-body">
                <div v-if="!conversation.messages.length" class="messenger-thread-empty">
                    {{ copy.noMessages }}
                </div>

                <div
                    v-for="message in conversation.messages"
                    :key="message.id"
                    :class="['message-row', { 'is-own': message.is_own }]"
                >
                    <article
                        :class="['message-bubble', { 'is-own': message.is_own }]"
                        :title="formatTooltipTime(message.created_at)"
                    >
                        <strong class="message-author">{{ message.sender_name }}</strong>
                        <p v-if="message.body" class="message-body">{{ message.body }}</p>
                        <a
                            v-if="message.attachment?.url"
                            class="message-attachment"
                            :href="message.attachment.url"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ copy.files }}: {{ message.attachment.name }}
                        </a>
                    </article>
                </div>
            </div>

            <MessengerComposer
                :can-send="Boolean(conversation.can_send_messages)"
                :sending="sending"
                :error="composerError"
                :send-action="sendAction"
            />
        </template>
    </section>
</template>

<style scoped>
.messenger-thread{display:flex;flex-direction:column;min-width:0;min-height:0;height:100%;background:#f8fafc;overflow:hidden}
.messenger-thread-head{padding:18px 20px;border-bottom:1px solid rgba(148,163,184,.18);background:#fff}
.messenger-thread-line{margin:0;display:flex;align-items:center;gap:8px;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#64748b;font-size:.82rem;line-height:1.45}
.messenger-thread-line strong{flex:0 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;color:#0f172a;font-size:1rem;font-weight:700}
.messenger-thread-line span{flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis}
.thread-link-primary,.thread-link-secondary{color:#2563eb;text-decoration:underline;text-underline-offset:3px}
.thread-link-primary{flex:0 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;font-size:1rem;font-weight:700}
.thread-link-secondary{font-weight:600}
.messenger-thread-body{flex:1 1 auto;min-height:0;overflow:auto;padding:18px;display:grid;gap:12px;align-content:start}
.messenger-thread-state,.messenger-thread-empty{display:grid;place-items:center;padding:28px;color:#64748b;font-size:.92rem;line-height:1.6}
.messenger-thread-error{color:#9f1239}
.message-row{display:flex}
.message-row.is-own{justify-content:flex-end}
.message-bubble{max-width:min(72%,640px);display:grid;gap:8px;padding:12px 14px;border-radius:16px 16px 16px 6px;background:#fff;border:1px solid rgba(148,163,184,.2);box-shadow:0 12px 24px rgba(15,23,42,.04)}
.message-bubble.is-own{border-radius:16px 16px 6px 16px;background:#eff6ff;border-color:#bfdbfe}
.message-author{color:#0f172a;font-size:.82rem;font-weight:700}
.message-body{margin:0;color:#334155;font-size:.92rem;line-height:1.65;white-space:pre-wrap;word-break:break-word}
.message-attachment{color:#2563eb;font-size:.85rem;font-weight:600;text-decoration:underline;text-underline-offset:3px}
@media (max-width: 720px){
    .messenger-thread-line{display:block;white-space:normal}
    .messenger-thread-line strong,.messenger-thread-line span{display:block}
    .message-bubble{max-width:100%}
}
</style>
