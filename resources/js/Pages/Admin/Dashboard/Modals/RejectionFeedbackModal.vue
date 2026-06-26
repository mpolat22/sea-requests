<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    user: { type: Object, default: null },
    copy: { type: Object, required: true },
    payload: {
        type: Object,
        default: () => ({
            eyebrow: '',
            title: '',
            intro: '',
            feedback_type: '',
            reviewed_at: '',
            reason: '',
            note: '',
            fields: [],
        }),
    },
});

const emit = defineEmits(['close']);

const revisionFields = computed(() => Array.isArray(props.payload?.fields) ? props.payload.fields : []);
</script>

<template>
    <div v-if="show && user" class="admin-modal-backdrop" @click="emit('close')">
        <div class="admin-modal feedback-modal" @click.stop>
            <button type="button" class="admin-modal-close" @click="emit('close')">&times;</button>

            <header class="modal-header">
                <p class="directory-eyebrow">{{ payload.eyebrow || copy.reviewFeedback }}</p>
                <h2 class="directory-section-title">{{ user.company_name || user.name }}</h2>
                <p v-if="payload.intro" class="modal-copy">{{ payload.intro }}</p>
            </header>

            <div class="modal-body">
                <section class="summary-grid">
                    <article class="summary-card">
                        <span class="summary-label">{{ copy.feedbackType }}</span>
                        <strong class="summary-value">{{ payload.feedback_type || payload.title }}</strong>
                    </article>
                    <article class="summary-card">
                        <span class="summary-label">{{ copy.feedbackSentAt }}</span>
                        <strong class="summary-value">{{ payload.reviewed_at || '-' }}</strong>
                    </article>
                </section>

                <section v-if="payload.reason" class="feedback-card">
                    <span class="feedback-label">{{ copy.rejectionReason }}</span>
                    <p class="feedback-text">{{ payload.reason }}</p>
                </section>

                <section class="feedback-card">
                    <span class="feedback-label">{{ copy.rejectionNote }}</span>
                    <p class="feedback-text">{{ payload.note || copy.noAdminNote }}</p>
                </section>

                <section class="feedback-card">
                    <span class="feedback-label">{{ copy.rejectionFields }}</span>
                    <div v-if="revisionFields.length" class="field-chip-grid">
                        <span v-for="field in revisionFields" :key="field" class="field-chip">{{ field }}</span>
                    </div>
                    <p v-else class="feedback-text">{{ copy.noRevisionFields }}</p>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
.admin-modal-backdrop{position:fixed;inset:0;z-index:1500;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(4,21,31,.58);backdrop-filter:blur(10px)}
.admin-modal{position:relative;width:min(820px,100%);max-height:min(90vh,860px);display:grid;grid-template-rows:auto minmax(0,1fr);border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff;box-shadow:0 30px 60px rgba(15,23,42,.16);overflow:hidden}
.feedback-modal{background:linear-gradient(180deg,#ffffff 0%,#fbfdff 100%)}
.admin-modal-close{position:absolute;top:16px;right:16px;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff;color:#0f172a;font-size:1.45rem;line-height:1}
.modal-header{padding:24px 24px 16px;border-bottom:1px solid rgba(4,21,31,.06)}
.modal-copy{margin:10px 0 0;color:#64748b;font-size:.94rem;line-height:1.7;max-width:72ch}
.modal-body{display:grid;gap:16px;padding:20px 24px 24px;overflow:auto}
.summary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.summary-card{display:grid;gap:8px;padding:16px 18px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff}
.summary-label{color:#64748b;font-size:.78rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.summary-value{color:#0f172a;font-size:.96rem;font-weight:600;line-height:1.6}
.feedback-card{display:grid;gap:10px;padding:16px 18px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff}
.feedback-label{color:#64748b;font-size:.78rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.feedback-text{margin:0;color:#0f172a;font-size:.94rem;font-weight:500;line-height:1.7;white-space:pre-wrap}
.field-chip-grid{display:flex;flex-wrap:wrap;gap:10px}
.field-chip{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:0 12px;border-radius:999px;background:#eef6ff;color:#1d4ed8;border:1px solid rgba(37,99,235,.12);font-size:.85rem;font-weight:600;line-height:1.4}
@media (max-width: 720px){.admin-modal{width:min(100%,100%);max-height:calc(100vh - 20px)}.modal-header{padding:20px 20px 14px}.modal-body{padding:18px 20px 20px}.summary-grid{grid-template-columns:1fr}}
</style>

