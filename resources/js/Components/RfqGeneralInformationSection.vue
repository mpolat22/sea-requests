<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    columns: {
        type: Number,
        default: 4,
    },
    fields: {
        type: Array,
        default: () => [],
    },
    compact: {
        type: Boolean,
        default: false,
    },
    labelWidth: {
        type: [Number, String],
        default: 122,
    },
    wrapLabels: {
        type: Boolean,
        default: false,
    },
    framed: {
        type: Boolean,
        default: false,
    },
    empty: {
        type: Boolean,
        default: false,
    },
    emptyTitle: {
        type: String,
        default: '',
    },
    emptyText: {
        type: String,
        default: '',
    },
    smallText: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['action']);

const labelWidthStyle = computed(() => (
    typeof props.labelWidth === 'number'
        ? `${props.labelWidth}px`
        : props.labelWidth
));
</script>

<template>
    <div
        class="subsection-surface"
        :class="{
            'subsection-surface-framed': framed,
            'subsection-surface-small': smallText,
        }"
    >
        <div class="section-heading">
            <h2 class="directory-section-title">{{ title }}</h2>
        </div>

        <div v-if="empty" class="info-empty-state">
            <strong class="info-empty-title">{{ emptyTitle }}</strong>
            <p v-if="emptyText" class="info-empty-copy">{{ emptyText }}</p>
        </div>

        <div
            v-else
            class="info-grid"
            :class="{ 'info-grid-compact': compact }"
            :style="{ '--info-grid-columns': String(columns), '--info-label-width': labelWidthStyle }"
        >
            <div
                v-for="field in fields"
                :key="field.key"
                class="info-field"
                :class="{ 'info-field-wide': field.wide && Number(columns) > 1 }"
            >
                <div class="detail-inline-value">
                    <div class="detail-inline-main" :class="{ 'detail-inline-main-wide': field.wide }">
                        <strong class="detail-inline-label" :class="{ 'detail-inline-label-wrap': wrapLabels }">{{ field.label }}:</strong>
                        <div class="detail-inline-text" :class="{ 'detail-inline-text-long': field.long }">
                            <button
                                v-if="field.clickable"
                                type="button"
                                class="detail-value-link"
                                @click="emit('action', field.action)"
                            >
                                {{ field.value }}
                            </button>
                            <Link
                                v-else-if="field.href"
                                :href="field.href"
                                class="detail-value-link"
                            >
                                {{ field.value }}
                            </Link>
                            <template v-else>
                                {{ field.value }}
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.subsection-surface{padding:24px;border-radius:10px;background:#f8fafb;min-width:0}
.section-heading{margin-bottom:18px}
.section-heading :deep(.directory-section-title){margin:0;font-size:1.04rem;font-weight:700;line-height:1.25;color:#0f172a}
.subsection-surface-framed{border:1px solid rgba(148,163,184,.16);box-shadow:inset 0 1px 0 rgba(255,255,255,.6)}
.info-empty-state{display:grid;gap:8px;min-height:112px;align-content:center}
.info-empty-title{color:#0f172a;font-size:.98rem;font-weight:700;line-height:1.35}
.info-empty-copy{margin:0;color:#64748b;font-size:.92rem;line-height:1.65;max-width:60ch}
.info-grid{display:grid;grid-template-columns:repeat(var(--info-grid-columns,4),minmax(0,1fr));gap:14px 18px}
.info-field{display:flex;flex-direction:column;gap:0}
.info-field-wide{grid-column:span 2}
.detail-inline-value{display:grid;align-items:start;row-gap:4px;min-height:0;padding:0;line-height:1.25;min-width:0}
.detail-inline-main{display:grid;grid-template-columns:var(--info-label-width,122px) minmax(0,1fr);align-items:start;column-gap:10px}
.detail-inline-main-wide{grid-template-columns:var(--info-label-width,122px) minmax(0,1fr)}
.detail-inline-label{color:#04151f;font-size:14px;font-weight:700;line-height:1.35;white-space:nowrap}
.detail-inline-label-wrap{white-space:normal}
.detail-inline-text{color:rgba(4,21,31,.82);font-size:15px;font-weight:400;display:block;min-width:0;line-height:1.35;white-space:normal;overflow:visible;text-overflow:clip;word-break:normal;overflow-wrap:anywhere}
.detail-inline-text-long{line-height:1.6}
.detail-value-link{appearance:none;border:0;background:transparent;color:inherit;text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:3px;padding:0;font:inherit;cursor:pointer}
.subsection-surface-small .detail-inline-label{font-size:12px;line-height:1.2}
.subsection-surface-small .detail-inline-text{font-size:13px;line-height:1.45}
.subsection-surface-small .detail-inline-text-long{line-height:1.45}
.subsection-surface-small .info-empty-title{font-size:.94rem}
.subsection-surface-small .info-empty-copy{font-size:.86rem;line-height:1.6}
.info-grid-compact .detail-inline-main{grid-template-columns:auto minmax(0,1fr);column-gap:4px}
.info-grid-compact .detail-inline-label,
.info-grid-compact .detail-inline-text{font-size:13px;line-height:1.15}
.info-grid-compact .detail-inline-text{white-space:nowrap}
@media (max-width: 1080px){
    .info-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media (max-width: 860px){
    .info-grid{grid-template-columns:1fr}
    .info-field-wide{grid-column:span 1}
}
@media (max-width: 720px){
    .subsection-surface{padding:20px}
    .detail-inline-main,
    .detail-inline-main-wide,
    .info-grid-compact .detail-inline-main{grid-template-columns:1fr;row-gap:6px}
    .detail-inline-label,
    .info-grid-compact .detail-inline-text{white-space:normal}
}
</style>
