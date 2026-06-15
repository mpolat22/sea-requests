<script setup>
const props = defineProps({
    importPreview: {
        type: Object,
        default: null,
    },
    importPreviewOpen: {
        type: Boolean,
        default: false,
    },
    importPreviewEditing: {
        type: Boolean,
        default: false,
    },
    previewDraftItems: {
        type: Array,
        default: () => [],
    },
    previewItemColumns: {
        type: Array,
        default: () => [],
    },
    previewGeneralColumns: {
        type: Array,
        default: () => [],
    },
    previewGeneralDisplay: {
        type: Object,
        default: () => ({}),
    },
    unitOptions: {
        type: Array,
        default: () => [],
    },
    qualityOptions: {
        type: Array,
        default: () => [],
    },
    formatOptionLabel: {
        type: Function,
        required: true,
    },
    toggleImportPreviewEditing: {
        type: Function,
        required: true,
    },
    resetImportPreviewDraft: {
        type: Function,
        required: true,
    },
    clearImportPreview: {
        type: Function,
        required: true,
    },
    applyImportPreview: {
        type: Function,
        required: true,
    },
    addPreviewItemRow: {
        type: Function,
        required: true,
    },
    removePreviewItemRow: {
        type: Function,
        required: true,
    },
    closeImportPreview: {
        type: Function,
        required: true,
    },
});
</script>

<template>
    <div v-if="props.importPreview && props.importPreviewOpen" class="import-preview-modal" @click.self="props.closeImportPreview">
        <div class="import-preview-dialog">
            <div class="import-preview-card">
                <div class="import-preview-head">
                    <div>
                        <h3 class="directory-section-title import-preview-main-title">Import Preview</h3>
                        <p class="import-preview-copy">Review the extracted data before applying it to this RFQ.</p>
                    </div>
                    <div class="import-preview-actions">
                        <button type="button" class="ghost-button compact-button" @click="props.toggleImportPreviewEditing">
                            {{ props.importPreviewEditing ? 'Done Editing' : 'Edit Preview' }}
                        </button>
                        <button v-if="props.importPreviewEditing" type="button" class="secondary-button compact-button" @click="props.resetImportPreviewDraft">
                            Reset
                        </button>
                        <button type="button" class="secondary-button compact-button" @click="props.clearImportPreview">
                            Discard
                        </button>
                        <button type="button" class="primary-button compact-button" @click="props.applyImportPreview">
                            Apply Import
                        </button>
                    </div>
                </div>

                <div class="import-preview-meta">
                    <div class="preview-stat">
                        <span class="preview-stat-label">Imported items</span>
                        <strong class="preview-stat-value">{{ props.previewDraftItems.length || props.importPreview.summary?.items_count || 0 }}</strong>
                    </div>
                    <div class="preview-stat">
                        <span class="preview-stat-label">Detected fields</span>
                        <strong class="preview-stat-value">{{ props.importPreview.summary?.mapped_columns?.length ?? 0 }}</strong>
                    </div>
                    <div class="preview-stat">
                        <span class="preview-stat-label">Source</span>
                        <strong class="preview-stat-value">{{ props.importPreview.summary?.sheet_name ?? '-' }}</strong>
                    </div>
                </div>

                <div class="import-preview-surface">
                    <h4 class="directory-section-title import-preview-subtitle">General fields</h4>
                    <div class="form-grid import-preview-general-grid">
                        <div v-for="column in props.previewGeneralColumns" :key="`general-row-${column.key}`" class="field import-preview-general-field" :class="{ 'field-span-2': column.span === 2 }">
                            <div class="import-preview-inline-value">
                                <div class="import-preview-inline-main">
                                    <strong class="import-preview-inline-label">{{ column.label }}:</strong>
                                    <div class="import-preview-inline-text">{{ props.previewGeneralDisplay[column.key] || '-' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="import-preview-surface">
                    <div class="import-preview-subhead">
                        <h4 class="directory-section-title import-preview-subtitle">Imported items</h4>
                        <button v-if="props.importPreviewEditing" type="button" class="secondary-button compact-button" @click="props.addPreviewItemRow">
                            Add Row
                        </button>
                    </div>
                    <div class="import-preview-scroll">
                        <table class="import-preview-grid-table">
                            <colgroup>
                                <col style="width: 42px">
                                <col style="width: 175px">
                                <col style="width: 150px">
                                <col style="width: 150px">
                                <col style="width: 150px">
                                <col style="width: 150px">
                                <col style="width: 150px">
                                <col style="width: 150px">
                                <col style="width: 85px">
                                <col style="width: 85px">
                                <col style="width: 85px">
                                <col style="width: 150px">
                                <col style="width: 150px">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th v-for="column in props.previewItemColumns" :key="`head-${column.key}`">
                                        <span class="import-preview-header-label">{{ column.label }}</span>
                                    </th>
                                    <th v-if="props.importPreviewEditing">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, previewIndex) in props.previewDraftItems" :key="`preview-${previewIndex}`">
                                    <td>{{ previewIndex + 1 }}</td>
                                    <td v-for="column in props.previewItemColumns" :key="`row-${previewIndex}-${column.key}`">
                                        <template v-if="!props.importPreviewEditing">
                                            {{ item[column.key] || '-' }}
                                        </template>
                                        <template v-else>
                                            <select v-if="column.key === 'unit'" v-model="item[column.key]" class="import-preview-cell-control">
                                                <option value="">Select</option>
                                                <option v-for="unit in props.unitOptions" :key="`preview-unit-${unit}`" :value="unit">{{ unit }}</option>
                                            </select>
                                            <select v-else-if="column.key === 'quality'" v-model="item[column.key]" class="import-preview-cell-control">
                                                <option value="">Select</option>
                                                <option v-for="quality in props.qualityOptions" :key="`preview-quality-${quality}`" :value="quality">{{ props.formatOptionLabel(quality) }}</option>
                                            </select>
                                            <input
                                                v-else
                                                v-model="item[column.key]"
                                                type="text"
                                                class="import-preview-cell-control"
                                            />
                                        </template>
                                    </td>
                                    <td v-if="props.importPreviewEditing">
                                        <button type="button" class="preview-row-remove" @click="props.removePreviewItemRow(previewIndex)">Remove</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.form-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-top: 16px;
}

.field {
    display: grid;
    gap: 5px;
    position: relative;
    min-width: 0;
}

.field-span-2 {
    grid-column: span 2;
}

.secondary-button,
.primary-button,
.ghost-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 18px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 600;
}

.secondary-button,
.ghost-button {
    border: 1px solid rgba(4, 21, 31, 0.1);
    background: #fff;
    color: #04151f;
}

.primary-button {
    border: 1px solid #0f172a;
    background: #0f172a;
    color: #fff;
}

.ghost-button {
    background: transparent;
}

.ghost-button:hover {
    background: rgba(4, 21, 31, 0.04);
}

.compact-button {
    min-height: 40px;
    padding: 0 14px;
    font-size: 0.86rem;
}

.import-preview-modal {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 28px;
    background: rgba(4, 21, 31, 0.42);
    backdrop-filter: blur(6px);
}

.import-preview-dialog {
    width: min(1280px, calc(100vw - 56px));
    max-height: calc(100vh - 56px);
    overflow: auto;
    min-width: 0;
}

.import-preview-card {
    display: grid;
    gap: 16px;
    padding: 24px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.12);
    min-width: 0;
}

.import-preview-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
}

.import-preview-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.import-preview-main-title,
.import-preview-subtitle {
    margin: 0;
}

.import-preview-subtitle {
    font-size: 1.08rem;
}

.import-preview-copy {
    margin: 6px 0 0;
    color: rgba(4, 21, 31, 0.68);
    font-size: 0.88rem;
    line-height: 1.55;
}

.import-preview-surface {
    padding: 20px 22px;
    border-radius: 10px;
    background: #f8fafb;
    min-width: 0;
}

.import-preview-subhead {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.import-preview-meta {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.preview-stat {
    padding: 12px 14px;
    border-radius: 10px;
    background: #fff;
}

.preview-stat-label {
    display: block;
    color: rgba(4, 21, 31, 0.62);
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.preview-stat-value {
    display: block;
    margin-top: 4px;
    color: #04151f;
    font-size: 0.94rem;
    font-weight: 700;
}

.import-preview-general-grid {
    margin-top: 10px;
    row-gap: 8px;
    column-gap: 18px;
    align-items: start;
}

.import-preview-general-field {
    padding-bottom: 0;
}

.import-preview-inline-value {
    display: grid;
    align-items: start;
    row-gap: 4px;
    min-height: 0;
    padding: 0;
    line-height: 1.25;
    min-width: 0;
}

.import-preview-inline-main {
    display: grid;
    grid-template-columns: 122px minmax(0, 1fr);
    align-items: start;
    column-gap: 8px;
}

.import-preview-inline-label {
    color: #04151f;
    font-size: 14px;
    font-weight: 700;
    line-height: 1.2;
    white-space: nowrap;
}

.import-preview-inline-text {
    color: rgba(4, 21, 31, 0.82);
    font-size: 15px;
    font-weight: 400;
    line-height: 1.2;
    white-space: normal;
    overflow: visible;
    text-overflow: clip;
    word-break: break-word;
}

.import-preview-scroll {
    margin-top: 10px;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    overflow-x: auto;
    overflow-y: hidden;
    padding-bottom: 6px;
}

.import-preview-grid-table {
    width: max-content;
    min-width: 100%;
    border-collapse: collapse;
    background: #fff;
    border: 1px solid rgba(4, 21, 31, 0.1);
    border-radius: 10px;
    overflow: hidden;
}

.import-preview-grid-table th,
.import-preview-grid-table td {
    padding: 10px 8px;
    text-align: left;
    vertical-align: top;
    border-right: 1px solid rgba(4, 21, 31, 0.08);
    border-bottom: 1px solid rgba(4, 21, 31, 0.08);
}

.import-preview-grid-table th:last-child,
.import-preview-grid-table td:last-child {
    border-right: 0;
}

.import-preview-grid-table tbody tr:last-child td {
    border-bottom: 0;
}

.import-preview-grid-table th {
    color: #04151f;
    font-size: 0.82rem;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
    background: #fff;
}

.import-preview-header-label {
    display: inline;
}

.import-preview-grid-table td {
    color: rgba(4, 21, 31, 0.78);
    font-size: 0.86rem;
    font-weight: 500;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.import-preview-cell-control {
    width: 100%;
    min-width: 100%;
    padding: 7px 8px;
    border: 1px solid rgba(4, 21, 31, 0.14);
    border-radius: 8px;
    background: #fff;
    color: #04151f;
    font-size: 0.84rem;
    font-weight: 500;
}

.preview-row-remove {
    border: 1px solid rgba(190, 24, 93, 0.18);
    border-radius: 999px;
    background: rgba(190, 24, 93, 0.08);
    color: #be123c;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 5px 10px;
}

@media (max-width: 720px) {
    .form-grid {
        grid-template-columns: 1fr;
    }

    .import-preview-modal {
        padding: 16px;
    }

    .import-preview-dialog {
        width: 100%;
        max-height: calc(100vh - 32px);
    }

    .import-preview-head,
    .import-preview-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .import-preview-meta {
        grid-template-columns: 1fr;
    }
}
</style>
