import { reactive } from 'vue';

const state = reactive({
    userId: null,
    isOpen: false,
    conversations: [],
    activeOfferId: null,
    activeConversation: null,
    unreadCount: 0,
    loadingList: false,
    loadingConversation: false,
    sending: false,
    listError: '',
    conversationError: '',
    composerError: '',
});

let previewTimer = null;
let liveTimer = null;

const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const requestJson = async (url, { method = 'GET', formData = null } = {}) => {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (method !== 'GET') {
        headers['X-CSRF-TOKEN'] = getCsrfToken();
    }

    const response = await window.fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        body: formData,
    });

    const isJson = (response.headers.get('content-type') || '').includes('application/json');
    const payload = isJson ? await response.json() : null;

    if (!response.ok) {
        const error = new Error(payload?.message || `Request failed with status ${response.status}.`);
        error.status = response.status;
        error.payload = payload;
        throw error;
    }

    return payload;
};

const syncUnreadCount = (value) => {
    const numeric = Number(value ?? 0);
    state.unreadCount = Number.isFinite(numeric) ? numeric : 0;
};

const applyConversationList = (payload) => {
    state.conversations = Array.isArray(payload?.items) ? payload.items : [];
    syncUnreadCount(payload?.unread_count ?? 0);
};

const conversationUrl = (offerId) => `/messenger/conversations/${offerId}`;

const stopPreviewPolling = () => {
    if (previewTimer) {
        window.clearInterval(previewTimer);
        previewTimer = null;
    }
};

const stopLivePolling = () => {
    if (liveTimer) {
        window.clearInterval(liveTimer);
        liveTimer = null;
    }
};

const loadConversations = async ({ preserveSelection = true, silent = false } = {}) => {
    if (!state.userId) {
        return;
    }

    if (!silent) {
        state.loadingList = true;
        state.listError = '';
    }

    try {
        const payload = await requestJson('/messenger/conversations');
        applyConversationList(payload);

        if (!preserveSelection || !state.activeOfferId) {
            state.activeOfferId = state.conversations[0]?.offer_id ?? null;
            return;
        }

        const hasActiveConversation = state.conversations.some((conversation) => conversation.offer_id === state.activeOfferId);

        if (!hasActiveConversation) {
            state.activeOfferId = state.conversations[0]?.offer_id ?? null;
        }
    } catch (error) {
        if (!silent) {
            state.listError = error?.message || 'Conversation list could not be loaded right now.';
        }
    } finally {
        if (!silent) {
            state.loadingList = false;
        }
    }
};

const markConversationAsRead = async (offerId) => {
    try {
        const payload = await requestJson(`${conversationUrl(offerId)}/read`, {
            method: 'POST',
        });

        applyConversationList(payload);

        if (state.activeConversation?.offer_id === offerId) {
            state.activeConversation = {
                ...state.activeConversation,
                unread_count: 0,
            };
        }
    } catch {
        // Keep the chat usable even if the read marker fails silently.
    }
};

const loadConversation = async (offerId, { silent = false, markRead = true } = {}) => {
    if (!offerId) {
        state.activeConversation = null;
        state.activeOfferId = null;
        return;
    }

    if (!silent) {
        state.loadingConversation = true;
        state.conversationError = '';
    }

    try {
        const payload = await requestJson(conversationUrl(offerId));
        state.activeConversation = payload?.conversation ?? null;
        state.activeOfferId = offerId;

        if (markRead && state.activeConversation?.unread_count) {
            await markConversationAsRead(offerId);
        }
    } catch (error) {
        if (!silent) {
            state.conversationError = error?.message || 'Conversation could not be loaded right now.';
        }
        state.activeConversation = null;
    } finally {
        if (!silent) {
            state.loadingConversation = false;
        }
    }
};

const startPreviewPolling = () => {
    stopPreviewPolling();

    if (!state.userId) {
        return;
    }

    previewTimer = window.setInterval(() => {
        if (!state.isOpen) {
            void loadConversations({ preserveSelection: true, silent: true });
        }
    }, 30000);
};

const startLivePolling = () => {
    stopLivePolling();

    if (!state.userId || !state.isOpen) {
        return;
    }

    liveTimer = window.setInterval(() => {
        if (state.sending) {
            return;
        }

        void loadConversations({ preserveSelection: true, silent: true });

        if (state.activeOfferId) {
            void loadConversation(state.activeOfferId, {
                silent: true,
                markRead: true,
            });
        }
    }, 2500);
};

const openDirectory = async () => {
    state.isOpen = true;
    state.composerError = '';
    await loadConversations({ preserveSelection: true });

    if (state.activeOfferId) {
        await loadConversation(state.activeOfferId);
    }

    startLivePolling();
};

const openForOffer = async (offerId) => {
    state.isOpen = true;
    state.composerError = '';
    await loadConversations({ preserveSelection: true });
    await loadConversation(offerId);
    startLivePolling();
};

const selectConversation = async (offerId) => {
    state.composerError = '';
    await loadConversation(offerId);
};

const closeMessenger = () => {
    state.isOpen = false;
    state.composerError = '';
    stopLivePolling();
};

const sendMessage = async ({ body = '', attachment = null } = {}) => {
    if (!state.activeOfferId) {
        return false;
    }

    const trimmedBody = `${body ?? ''}`.trim();
    const formData = new FormData();

    if (trimmedBody) {
        formData.append('body', trimmedBody);
    }

    if (attachment) {
        formData.append('attachment', attachment);
    }

    state.sending = true;
    state.composerError = '';

    try {
        const payload = await requestJson(`${conversationUrl(state.activeOfferId)}/messages`, {
            method: 'POST',
            formData,
        });

        state.activeConversation = payload?.conversation ?? state.activeConversation;
        await loadConversations({ preserveSelection: true, silent: true });

        return true;
    } catch (error) {
        const fieldError = error?.payload?.errors?.body?.[0]
            || error?.payload?.errors?.attachment?.[0]
            || error?.message
            || 'Message could not be sent right now.';

        state.composerError = fieldError;

        return false;
    } finally {
        state.sending = false;
    }
};

const bootstrapMessenger = async (user) => {
    state.userId = user?.id ?? null;

    stopPreviewPolling();
    stopLivePolling();

    if (!state.userId) {
        state.isOpen = false;
        state.conversations = [];
        state.activeOfferId = null;
        state.activeConversation = null;
        state.unreadCount = 0;
        state.listError = '';
        state.conversationError = '';
        state.composerError = '';
        return;
    }

    await loadConversations({ preserveSelection: true, silent: true });
    startPreviewPolling();

    if (state.isOpen) {
        startLivePolling();
    }
};

const teardownMessenger = () => {
    stopPreviewPolling();
    stopLivePolling();
};

export const useMessengerStore = () => ({
    state,
    openDirectory,
    openForOffer,
    closeMessenger,
    selectConversation,
    sendMessage,
    bootstrapMessenger,
    teardownMessenger,
    reloadConversations: loadConversations,
});
