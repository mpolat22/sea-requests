<script setup>
const props = defineProps({
    show: { type: Boolean, default: false },
    user: { type: Object, default: null },
    copy: { type: Object, required: true },
    context: { type: String, default: 'user' },
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <div v-if="show && user" class="admin-modal-backdrop" @click="emit('close')">
        <div class="admin-modal compact-modal" @click.stop>
            <button type="button" class="admin-modal-close" @click="emit('close')">&times;</button>
            <p class="directory-eyebrow">{{ copy.delete }}</p>
            <h2 class="directory-section-title">{{ context === 'business' ? copy.deleteBusinessTitle : copy.deleteUserTitle }}</h2>
            <p class="admin-modal-copy">{{ context === 'business' ? copy.deleteBusinessText : copy.deleteUserText }}</p>

            <div class="detail-grid compact">
                <div class="detail-item">
                    <span>{{ copy.userName }}</span>
                    <strong>{{ user.name || copy.noValue }}</strong>
                </div>
                <div class="detail-item">
                    <span>{{ copy.company }}</span>
                    <strong>{{ user.company_name || copy.noValue }}</strong>
                </div>
            </div>

            <div class="admin-actions">
                <button type="button" class="action-secondary" @click="emit('close')">{{ copy.cancel }}</button>
                <button type="button" class="action-danger" @click="emit('confirm')">{{ copy.confirmDelete }}</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.admin-modal-backdrop{position:fixed;inset:0;z-index:1500;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(4,21,31,.58);backdrop-filter:blur(10px)}
.admin-modal{position:relative;width:min(760px,100%);padding:24px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff;box-shadow:0 30px 60px rgba(15,23,42,.16)}
.compact-modal{width:min(640px,100%)}
.admin-modal-close{position:absolute;top:16px;right:16px;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff;color:#0f172a;font-size:1.45rem;line-height:1}
.admin-modal-copy{margin:14px 0 0;color:#64748b;font-size:.95rem;line-height:1.7}
.detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:18px}
.detail-grid.compact{grid-template-columns:repeat(2,minmax(0,1fr))}
.detail-item{display:grid;gap:6px;padding:14px 16px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#f8fafc}
.detail-item span{color:#64748b;font-size:.78rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase}
.detail-item strong{color:#020617;font-size:.94rem;font-weight:560;line-height:1.6}
.admin-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:18px}
.action-secondary,.action-danger{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:40px;padding:0 12px;border-radius: 10px;border:1px solid rgba(4,21,31,.08);background:#fff;color:#0f172a;font-size:.82rem;font-weight:600}
.action-danger{border-color:rgba(180,35,24,.16);color:#b42318;background:#fff7f7}
@media (max-width: 640px){.detail-grid,.detail-grid.compact{grid-template-columns:1fr}.admin-actions{flex-direction:column}.action-secondary,.action-danger{width:100%}}
</style>

