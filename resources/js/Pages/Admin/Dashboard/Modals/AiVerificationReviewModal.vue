<script setup>
const props = defineProps({
    show: { type: Boolean, default: false },
    user: { type: Object, default: null },
    copy: { type: Object, required: true },
    payload: {
        type: Object,
        default: () => ({
            summary: [],
            details: [],
            reasoning: [],
        }),
    },
});

const emit = defineEmits(['close']);
</script>

<template>
    <div v-if="show && user" class="admin-modal-backdrop" @click="emit('close')">
        <div class="admin-modal ai-modal" @click.stop>
            <button type="button" class="admin-modal-close" @click="emit('close')">&times;</button>

            <header class="modal-header">
                <p class="directory-eyebrow">{{ copy.aiReviewTitle }}</p>
                <h2 class="directory-section-title">{{ user.company_name || user.name }}</h2>
                <p class="modal-copy">{{ copy.aiReviewIntro }}</p>
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

                <section class="detail-grid">
                    <article
                        v-for="item in payload.details"
                        :key="item.label"
                        class="detail-card"
                        :class="{ 'is-wide': item.wide }"
                    >
                        <span class="detail-label">{{ item.label }}</span>
                        <strong class="detail-value">{{ item.value }}</strong>
                    </article>
                </section>

                <section v-if="payload.reasoning?.length" class="reasoning-card">
                    <span class="detail-label">{{ copy.aiReasoning }}</span>
                    <ul class="reasoning-list">
                        <li v-for="(line, index) in payload.reasoning" :key="`${user.id}-ai-review-${index}`">{{ line }}</li>
                    </ul>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
.admin-modal-backdrop{position:fixed;inset:0;z-index:1500;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(4,21,31,.58);backdrop-filter:blur(10px)}
.admin-modal{position:relative;width:min(920px,100%);max-height:min(90vh,860px);display:grid;grid-template-rows:auto minmax(0,1fr);border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff;box-shadow:0 30px 60px rgba(15,23,42,.16);overflow:hidden}
.ai-modal{background:linear-gradient(180deg,#ffffff 0%,#fbfdff 100%)}
.admin-modal-close{position:absolute;top:16px;right:16px;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff;color:#0f172a;font-size:1.45rem;line-height:1}
.modal-header{padding:24px 24px 16px;border-bottom:1px solid rgba(4,21,31,.06)}
.modal-copy{margin:10px 0 0;color:#64748b;font-size:.94rem;line-height:1.7;max-width:72ch}
.modal-body{display:grid;gap:16px;padding:20px 24px 24px;overflow:auto}
.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.summary-card,.detail-card,.reasoning-card{display:grid;gap:8px;padding:16px 18px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff}
.detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.detail-card.is-wide{grid-column:1 / -1}
.summary-label,.detail-label{color:#64748b;font-size:.78rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.summary-value,.detail-value{color:#0f172a;font-size:.96rem;font-weight:600;line-height:1.6;white-space:pre-wrap}
.reasoning-list{margin:0;padding-left:18px;color:#0f172a;font-size:.94rem;font-weight:500;line-height:1.7}
@media (max-width: 760px){.summary-grid,.detail-grid{grid-template-columns:1fr}.admin-modal{width:min(100%,100%);max-height:calc(100vh - 20px)}}
</style>
