<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    user: { type: Object, default: null },
    copy: { type: Object, required: true },
    form: { type: Object, required: true },
    feedbackFields: { type: Array, required: true },
});

const emit = defineEmits(['close', 'submit']);
const modalBody = ref(null);

const scrollToFirstError = async () => {
    await nextTick();

    const firstErrorField = modalBody.value?.querySelector('.has-error select, .has-error textarea, .has-error input');

    if (!firstErrorField) {
        return;
    }

    firstErrorField.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
    });

    firstErrorField.focus?.({
        preventScroll: true,
    });
};

watch(
    () => props.form.errors,
    (errors) => {
        if (!props.show || !errors || Object.keys(errors).length === 0) {
            return;
        }

        scrollToFirstError();
    },
    { deep: true },
);
</script>

<template>
    <div v-if="show && user" class="admin-modal-backdrop" @click="emit('close')">
        <div class="admin-modal reject-modal" @click.stop>
            <button type="button" class="admin-modal-close" @click="emit('close')">&times;</button>

            <header class="modal-header">
                <p class="directory-eyebrow">{{ copy.reject }}</p>
                <h2 class="directory-section-title">{{ copy.rejectTitle }}</h2>
                <p class="company-name">{{ user.company_name || user.name }}</p>
                <p class="admin-modal-copy">{{ copy.rejectText }}</p>
            </header>

            <form ref="modalBody" class="modal-body" @submit.prevent="emit('submit')">
                <label class="admin-field" :class="{ 'has-error': !!form.errors.rejection_reason }">
                    <span>{{ copy.rejectionReason }}</span>
                    <select v-model="form.rejection_reason">
                        <option value="">{{ copy.placeholderReason }}</option>
                        <option v-for="(label, key) in copy.reasons" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <small v-if="form.errors.rejection_reason" class="admin-error">{{ form.errors.rejection_reason }}</small>
                </label>

                <label class="admin-field" :class="{ 'has-error': !!form.errors.rejection_note }">
                    <span>{{ copy.rejectionNote }}</span>
                    <textarea v-model="form.rejection_note" rows="5" />
                    <small v-if="form.errors.rejection_note" class="admin-error">{{ form.errors.rejection_note }}</small>
                </label>

                <div class="admin-field" :class="{ 'has-error': !!form.errors.rejection_fields }">
                    <span>{{ copy.rejectionFields }}</span>
                    <div class="field-grid">
                        <label v-for="field in feedbackFields" :key="field.key" class="field-chip">
                            <input v-model="form.rejection_fields" type="checkbox" :value="field.key">
                            <span>{{ field.label }}</span>
                        </label>
                    </div>
                    <small v-if="form.errors.rejection_fields" class="admin-error">{{ form.errors.rejection_fields }}</small>
                </div>

                <footer class="modal-footer">
                    <button type="button" class="action-secondary fixed-action" @click="emit('close')">{{ copy.cancel }}</button>
                    <button type="submit" class="action-warning fixed-action" :disabled="form.processing">
                        {{ form.processing ? copy.saving : copy.reject }}
                    </button>
                </footer>
            </form>
        </div>
    </div>
</template>

<style scoped>
.admin-modal-backdrop{position:fixed;inset:0;z-index:1500;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(4,21,31,.58);backdrop-filter:blur(10px)}
.admin-modal{position:relative;width:min(920px,100%);max-height:min(90vh,920px);display:grid;grid-template-rows:auto minmax(0,1fr);border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff;box-shadow:0 30px 60px rgba(15,23,42,.16);overflow:hidden}
.reject-modal{background:linear-gradient(180deg,#ffffff 0%,#fbfdff 100%)}
.admin-modal-close{position:absolute;top:18px;right:18px;display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff;color:#0f172a;font-size:1.5rem;line-height:1;z-index:2;box-shadow:0 10px 24px rgba(15,23,42,.08)}
.modal-header{padding:28px 28px 18px;border-bottom:1px solid rgba(4,21,31,.06)}
.company-name{margin:10px 0 0;color:#0f172a;font-size:1rem;font-weight:600;line-height:1.5}
.admin-modal-copy{margin:10px 0 0;color:#64748b;font-size:.95rem;line-height:1.7;max-width:72ch}
.modal-body{display:grid;gap:18px;padding:22px 28px 0;overflow:auto}
.admin-field{display:grid;gap:8px}
.admin-field span{color:rgba(4,21,31,.78);font-size:.88rem;font-weight:500}
.admin-field select,.admin-field textarea{width:100%;border:1px solid rgba(4,21,31,.12);border-radius: 10px;background:#fff;color:#0f172a;font-size:.94rem;font-weight:500;box-shadow:inset 0 1px 0 rgba(255,255,255,.9)}
.admin-field select{min-height:50px;padding:0 14px}
.admin-field textarea{min-height:148px;padding:14px;line-height:1.65;resize:vertical}
.field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.field-chip{display:flex;align-items:flex-start;gap:10px;padding:14px 16px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#f8fafc;transition:border-color .18s ease,background-color .18s ease}
.field-chip:hover{border-color:rgba(194,65,12,.24);background:#fffaf5}
.field-chip input{margin-top:2px}
.field-chip span{font-size:.88rem;font-weight:500;line-height:1.5;color:#0f172a;text-transform:none;letter-spacing:0}
.modal-footer{position:sticky;bottom:0;display:flex;justify-content:flex-end;gap:12px;flex-wrap:wrap;padding:18px 0 22px;background:linear-gradient(180deg,rgba(251,253,255,0) 0%,#fbfdff 24%,#fbfdff 100%)}
.action-secondary,.action-warning{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:0 14px;border-radius: 10px;border:1px solid rgba(4,21,31,.08);background:#fff;color:#0f172a;font-size:.84rem;font-weight:600}
.fixed-action{min-width:156px}
.action-warning{border-color:rgba(194,65,12,.16);color:#c2410c;background:#fff7ed}
.admin-error{color:#b42318;font-size:.85rem}
@media (max-width: 720px){.admin-modal{width:min(100%,100%);max-height:calc(100vh - 20px)}.modal-header{padding:24px 20px 16px}.modal-body{padding:18px 20px 0}.field-grid{grid-template-columns:1fr}.modal-footer{flex-direction:column;padding-bottom:20px}.fixed-action{width:100%;min-width:0}}
</style>

