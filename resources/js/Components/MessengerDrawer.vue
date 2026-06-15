<script setup>
import MessengerConversationList from './MessengerConversationList.vue';
import MessengerThread from './MessengerThread.vue';
import { useMessengerStore } from '../lib/messengerStore';

const messenger = useMessengerStore();

const copy = {
    close: 'Close',
};
</script>

<template>
    <transition name="messenger-fade">
        <div v-if="messenger.state.isOpen" class="messenger-overlay" @click.self="messenger.closeMessenger()">
            <section class="messenger-drawer">
                <header class="messenger-drawer-head">
                    <button type="button" class="messenger-close" @click="messenger.closeMessenger()">
                        {{ copy.close }}
                    </button>
                </header>

                <div class="messenger-drawer-body">
                    <MessengerConversationList
                        :conversations="messenger.state.conversations"
                        :active-offer-id="messenger.state.activeOfferId"
                        :loading="messenger.state.loadingList"
                        :error="messenger.state.listError"
                        @select="messenger.selectConversation"
                    />

                    <MessengerThread
                        :conversation="messenger.state.activeConversation"
                        :loading="messenger.state.loadingConversation"
                        :error="messenger.state.conversationError"
                        :sending="messenger.state.sending"
                        :composer-error="messenger.state.composerError"
                        :send-action="messenger.sendMessage"
                    />
                </div>
            </section>
        </div>
    </transition>
</template>

<style scoped>
.messenger-overlay{position:fixed;inset:0;z-index:90;display:flex;align-items:flex-end;justify-content:flex-end;padding:20px;background:rgba(15,23,42,.18)}
.messenger-drawer{width:min(1080px,calc(100vw - 24px));height:min(78vh,760px);display:grid;grid-template-rows:auto minmax(0,1fr);border:1px solid rgba(148,163,184,.22);border-radius:20px;background:#fff;box-shadow:0 32px 80px rgba(15,23,42,.22);overflow:hidden}
.messenger-drawer-head{display:flex;justify-content:flex-end;align-items:center;padding:12px 16px;border-bottom:1px solid rgba(148,163,184,.18);background:#fff}
.messenger-close{border:0;background:transparent;color:#2563eb;font-size:.88rem;font-weight:700;cursor:pointer}
.messenger-drawer-body{display:grid;grid-template-columns:320px minmax(0,1fr);min-height:0;overflow:hidden}
.messenger-fade-enter-active,.messenger-fade-leave-active{transition:opacity .18s ease,transform .18s ease}
.messenger-fade-enter-from,.messenger-fade-leave-to{opacity:0;transform:translate3d(0,8px,0)}
@media (max-width: 980px){
    .messenger-overlay{padding:12px}
    .messenger-drawer{width:100%;height:min(92vh,900px)}
    .messenger-drawer-body{grid-template-columns:1fr;grid-template-rows:auto minmax(0,1fr)}
}
</style>
