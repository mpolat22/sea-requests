<script setup>
const props = defineProps({
    show: { type: Boolean, default: false },
    user: { type: Object, default: null },
    copy: { type: Object, required: true },
    payload: {
        type: Object,
        default: () => ({
            summary: [],
            sequence: [],
        }),
    },
});

const emit = defineEmits(['close']);
</script>

<template>
    <div v-if="show && user" class="admin-modal-backdrop" @click="emit('close')">
        <div class="admin-modal history-modal" @click.stop>
            <button type="button" class="admin-modal-close" @click="emit('close')">&times;</button>

            <header class="modal-header">
                <p class="directory-eyebrow">{{ copy.verificationMailHistory }}</p>
                <h2 class="directory-section-title">{{ user.company_name || user.name }}</h2>
                <p class="modal-copy">{{ copy.verificationMailHistoryIntro }}</p>
            </header>

            <div class="modal-body">
                <section class="summary-grid">
                    <article
                        v-for="item in payload.summary"
                        :key="item.label"
                        class="summary-card"
                    >
                        <span class="summary-label">{{ item.label }}</span>
                        <strong class="summary-value">{{ item.value }}</strong>
                    </article>
                </section>

                <section class="history-grid">
                    <article
                        v-for="item in payload.sequence"
                        :key="item.key"
                        class="history-card"
                        :class="{ 'is-sent': item.is_sent, 'is-pending': !item.is_sent }"
                    >
                        <div class="history-card-head">
                            <div class="history-title-stack">
                                <strong class="history-title">{{ item.title }}</strong>
                                <span class="history-text">{{ item.description }}</span>
                            </div>
                            <span class="history-status" :class="{ 'is-sent': item.is_sent, 'is-pending': !item.is_sent }">
                                {{ item.status }}
                            </span>
                        </div>
                        <div class="history-meta">
                            <span class="history-meta-label">{{ copy.sentAt }}</span>
                            <strong class="history-meta-value">{{ item.sent_at }}</strong>
                        </div>
                    </article>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
.admin-modal-backdrop{position:fixed;inset:0;z-index:1500;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(4,21,31,.58);backdrop-filter:blur(10px)}
.admin-modal{position:relative;width:min(820px,100%);max-height:min(90vh,860px);display:grid;grid-template-rows:auto minmax(0,1fr);border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff;box-shadow:0 30px 60px rgba(15,23,42,.16);overflow:hidden}
.history-modal{width:min(860px,100%)}
.admin-modal-close{position:absolute;top:16px;right:16px;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff;color:#0f172a;font-size:1.45rem;line-height:1}
.modal-header{padding:24px 24px 16px;border-bottom:1px solid rgba(4,21,31,.06)}
.modal-copy{margin:10px 0 0;color:#64748b;font-size:.94rem;line-height:1.7;max-width:72ch}
.modal-body{display:grid;gap:16px;padding:20px 24px 24px;overflow:auto}
.summary-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.summary-card{display:grid;gap:8px;padding:16px 18px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff}
.summary-label{color:#64748b;font-size:.78rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.summary-value{color:#0f172a;font-size:.96rem;font-weight:600;line-height:1.6}
.history-grid{display:grid;gap:12px}
.history-card{display:grid;gap:12px;padding:16px 18px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff}
.history-card.is-sent{border-color:rgba(37,99,235,.14);background:#f8fbff}
.history-card.is-pending{border-color:rgba(148,163,184,.18);background:#fcfdff}
.history-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
.history-title-stack{display:grid;gap:6px}
.history-title{color:#0f172a;font-size:.96rem;font-weight:600;line-height:1.5}
.history-text{color:#64748b;font-size:.9rem;line-height:1.7}
.history-status{display:inline-flex;align-items:center;justify-content:center;min-height:32px;padding:0 12px;border-radius:10px;font-size:.78rem;font-weight:700;white-space:nowrap}
.history-status.is-sent{background:rgba(34,197,94,.12);color:#15803d}
.history-status.is-pending{background:rgba(148,163,184,.14);color:#475569}
.history-meta{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:12px;border-top:1px solid rgba(4,21,31,.06)}
.history-meta-label{color:#64748b;font-size:.8rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.history-meta-value{color:#0f172a;font-size:.9rem;font-weight:600}
@media (max-width: 760px){
    .summary-grid{grid-template-columns:1fr}
    .history-card-head,.history-meta{flex-direction:column;align-items:flex-start}
}
</style>
