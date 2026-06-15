<script setup>
const props = defineProps({
    show: { type: Boolean, default: false },
    user: { type: Object, default: null },
    copy: { type: Object, required: true },
    payload: {
        type: Object,
        default: () => ({
            title: '',
            reason: '',
            note: '',
            fields: '',
        }),
    },
});

const emit = defineEmits(['close']);
</script>

<template>
    <div v-if="show && user" class="admin-modal-backdrop" @click="emit('close')">
        <div class="admin-modal feedback-modal" @click.stop>
            <button type="button" class="admin-modal-close" @click="emit('close')">&times;</button>

            <header class="modal-header">
                <p class="directory-eyebrow">{{ payload.title }}</p>
                <h2 class="directory-section-title">{{ user.company_name || user.name }}</h2>
            </header>

            <div class="modal-body">
                <section v-if="payload.reason" class="feedback-card">
                    <span class="feedback-label">{{ copy.rejectionReason }}</span>
                    <p class="feedback-text">{{ payload.reason }}</p>
                </section>

                <section v-if="payload.note" class="feedback-card">
                    <span class="feedback-label">{{ copy.rejectionNote }}</span>
                    <p class="feedback-text">{{ payload.note }}</p>
                </section>

                <section v-if="payload.fields" class="feedback-card">
                    <span class="feedback-label">{{ copy.rejectionFields }}</span>
                    <p class="feedback-text">{{ payload.fields }}</p>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
.admin-modal-backdrop{position:fixed;inset:0;z-index:1500;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(4,21,31,.58);backdrop-filter:blur(10px)}
.admin-modal{position:relative;width:min(760px,100%);max-height:min(90vh,820px);display:grid;grid-template-rows:auto minmax(0,1fr);border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff;box-shadow:0 30px 60px rgba(15,23,42,.16);overflow:hidden}
.admin-modal-close{position:absolute;top:16px;right:16px;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff;color:#0f172a;font-size:1.45rem;line-height:1}
.modal-header{padding:24px 24px 16px;border-bottom:1px solid rgba(4,21,31,.06)}
.modal-body{display:grid;gap:14px;padding:20px 24px 24px;overflow:auto}
.feedback-card{display:grid;gap:8px;padding:16px 18px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff}
.feedback-label{color:#64748b;font-size:.78rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.feedback-text{margin:0;color:#0f172a;font-size:.94rem;font-weight:500;line-height:1.7;white-space:pre-wrap}
@media (max-width: 720px){.admin-modal{width:min(100%,100%);max-height:calc(100vh - 20px)}.modal-header{padding:20px 20px 14px}.modal-body{padding:18px 20px 20px}}
</style>

