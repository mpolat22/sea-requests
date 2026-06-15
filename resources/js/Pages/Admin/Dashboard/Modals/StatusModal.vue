<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    user: { type: Object, default: null },
    copy: { type: Object, required: true },
    statusLabel: { type: Function, required: true },
});

const emit = defineEmits(['close', 'choose']);

const isUpdateRequest = computed(() => !!props.user?.has_pending_update_request);
const modalCopy = computed(() => {
    if (isUpdateRequest.value) {
        return 'You are reviewing only the pending update request, not the already approved business profile.';
    }

    return 'Choose the new status for this supplier company registration.';
});
const approveTitle = computed(() => {
    if (isUpdateRequest.value) {
        return 'Approve update';
    }

    return props.statusLabel('approved');
});
const approveText = computed(() => {
    if (isUpdateRequest.value) {
        return 'Apply the new information to the live profile.';
    }

    return 'Keep this record approved.';
});
const rejectTitle = computed(() => {
    if (isUpdateRequest.value) {
        return 'Reject update';
    }

    return props.statusLabel('rejected');
});
const rejectText = computed(() => {
    if (isUpdateRequest.value) {
        return 'Keep the current approved profile and reject only this update request.';
    }

    return 'Add a rejection reason and explanation.';
});
</script>

<template>
    <div v-if="show && user" class="admin-modal-backdrop" @click="emit('close')">
        <div class="admin-modal compact-modal" @click.stop>
            <button type="button" class="admin-modal-close" @click="emit('close')">&times;</button>
            <p class="directory-eyebrow">{{ copy.status }}</p>
            <h2 class="directory-section-title">{{ user.company_name || user.name }}</h2>
            <p class="admin-modal-copy">{{ modalCopy }}</p>

            <div class="status-choice-grid">
                <button type="button" class="status-choice status-choice-approved" @click="emit('choose', 'approved')">
                    <strong>{{ approveTitle }}</strong>
                    <span>{{ approveText }}</span>
                </button>
                <button type="button" class="status-choice status-choice-rejected" @click="emit('choose', 'rejected')">
                    <strong>{{ rejectTitle }}</strong>
                    <span>{{ rejectText }}</span>
                </button>
            </div>

            <div class="admin-actions">
                <button type="button" class="action-secondary" @click="emit('close')">{{ copy.cancel }}</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.admin-modal-backdrop{position:fixed;inset:0;z-index:1500;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(4,21,31,.58);backdrop-filter:blur(10px)}
.admin-modal{position:relative;width:min(760px,100%);padding:24px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff;box-shadow:0 30px 60px rgba(15,23,42,.16)}
.compact-modal{width:min(680px,100%)}
.admin-modal-close{position:absolute;top:16px;right:16px;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff;color:#0f172a;font-size:1.45rem;line-height:1}
.admin-modal-copy{margin:14px 0 0;color:#64748b;font-size:.95rem;line-height:1.7}
.status-choice-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:18px}
.status-choice{display:grid;gap:8px;padding:18px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff;text-align:left}
.status-choice strong{color:#020617;font-size:1rem;font-weight:600}
.status-choice span{color:#64748b;font-size:.9rem;line-height:1.6}
.status-choice-approved{border-color:rgba(11,122,82,.14);background:#f1fcf6}
.status-choice-rejected{border-color:rgba(194,65,12,.14);background:#fff7ed}
.admin-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:18px}
.action-secondary{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:40px;padding:0 12px;border-radius:10px;border:1px solid rgba(4,21,31,.08);background:#fff;color:#0f172a;font-size:.82rem;font-weight:600}
@media (max-width: 640px){.status-choice-grid{grid-template-columns:1fr}.admin-actions{flex-direction:column}.action-secondary{width:100%}}
</style>
