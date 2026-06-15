<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    user: { type: Object, default: null },
    copy: { type: Object, required: true },
    reason: { type: String, default: '' },
    note: { type: String, default: '' },
    form: { type: Object, required: true },
});

const emit = defineEmits(['close', 'approve', 'reject']);
const showRejectNote = ref(false);
const modalBody = ref(null);

const beginReject = async () => {
    showRejectNote.value = true;
    await nextTick();
    modalBody.value?.querySelector('textarea')?.focus?.();
};

watch(() => props.show, (value) => {
    if (value) {
        showRejectNote.value = false;
    }
});

watch(
    () => props.form.errors,
    async (errors) => {
        if (!props.show || !errors?.note) {
            return;
        }

        showRejectNote.value = true;
        await nextTick();
        modalBody.value?.querySelector('textarea')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        modalBody.value?.querySelector('textarea')?.focus?.();
    },
    { deep: true },
);
</script>

<template>
    <div v-if="show && user" class="admin-modal-backdrop" @click="emit('close')">
        <div class="admin-modal feedback-modal" @click.stop>
            <button type="button" class="admin-modal-close" @click="emit('close')">&times;</button>

            <header class="modal-header">
                <p class="directory-eyebrow">{{ copy.removalRequest }}</p>
                <h2 class="directory-section-title">{{ user.company_name || user.name }}</h2>
                <p class="admin-modal-copy">
                    {{ copy.removalRequestDecisionText }}
                </p>
            </header>

            <div ref="modalBody" class="modal-body">
                <section v-if="reason" class="feedback-card">
                    <span class="feedback-label">{{ copy.rejectionReason }}</span>
                    <p class="feedback-text">{{ reason }}</p>
                </section>

                <section v-if="note" class="feedback-card">
                    <span class="feedback-label">{{ copy.rejectionNote }}</span>
                    <p class="feedback-text">{{ note }}</p>
                </section>

                <section v-if="showRejectNote" class="feedback-card">
                    <span class="feedback-label">{{ copy.rejectionNote }}</span>
                    <textarea
                        v-model="form.note"
                        class="feedback-textarea"
                        rows="4"
                        :placeholder="copy.removalRejectPlaceholder"
                    />
                    <small v-if="form.errors.note" class="admin-error">{{ form.errors.note }}</small>
                </section>
            </div>

            <footer class="modal-footer">
                <button type="button" class="action-secondary fixed-action" @click="emit('close')">{{ copy.cancel }}</button>
                <button
                    v-if="!showRejectNote"
                    type="button"
                    class="action-warning fixed-action"
                    @click="beginReject"
                >
                    {{ copy.reject }}
                </button>
                <button
                    v-else
                    type="button"
                    class="action-warning fixed-action"
                    :disabled="form.processing"
                    @click="emit('reject')"
                >
                    {{ form.processing ? copy.saving : copy.reject }}
                </button>
                <button
                    type="button"
                    class="action-danger fixed-action"
                    :disabled="form.processing"
                    @click="emit('approve')"
                >
                    {{ form.processing ? copy.saving : copy.approve }}
                </button>
            </footer>
        </div>
    </div>
</template>

<style scoped>
.admin-modal-backdrop{position:fixed;inset:0;z-index:1500;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(4,21,31,.58);backdrop-filter:blur(10px)}
.admin-modal{position:relative;width:min(760px,100%);max-height:min(90vh,820px);display:grid;grid-template-rows:auto minmax(0,1fr) auto;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff;box-shadow:0 30px 60px rgba(15,23,42,.16);overflow:hidden}
.admin-modal-close{position:absolute;top:16px;right:16px;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff;color:#0f172a;font-size:1.45rem;line-height:1}
.modal-header{padding:24px 24px 16px;border-bottom:1px solid rgba(4,21,31,.06)}
.admin-modal-copy{margin:12px 0 0;color:#64748b;font-size:.95rem;line-height:1.7}
.modal-body{display:grid;gap:14px;padding:20px 24px 16px;overflow:auto}
.feedback-card{display:grid;gap:8px;padding:16px 18px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff}
.feedback-label{color:#64748b;font-size:.78rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.feedback-text{margin:0;color:#0f172a;font-size:.94rem;font-weight:500;line-height:1.7;white-space:pre-wrap}
.feedback-textarea{width:100%;min-height:120px;padding:14px;border:1px solid rgba(4,21,31,.12);border-radius: 10px;background:#fff;color:#0f172a;font-size:.94rem;font-weight:500;line-height:1.65;resize:vertical}
.admin-error{color:#b42318;font-size:.85rem}
.modal-footer{display:flex;justify-content:flex-end;gap:12px;flex-wrap:wrap;padding:18px 24px 24px;border-top:1px solid rgba(4,21,31,.06);background:#fff}
.action-secondary,.action-warning,.action-danger{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:0 14px;border-radius: 10px;border:1px solid rgba(4,21,31,.08);background:#fff;color:#0f172a;font-size:.84rem;font-weight:600}
.fixed-action{min-width:156px}
.action-warning{border-color:rgba(194,65,12,.16);color:#c2410c;background:#fff7ed}
.action-danger{border-color:rgba(180,35,24,.16);color:#b42318;background:#fff7f7}
@media (max-width: 720px){.admin-modal{width:min(100%,100%);max-height:calc(100vh - 20px)}.modal-header{padding:20px 20px 14px}.modal-body{padding:18px 20px 14px}.modal-footer{padding:16px 20px 20px;flex-direction:column}.fixed-action{width:100%;min-width:0}}
</style>

