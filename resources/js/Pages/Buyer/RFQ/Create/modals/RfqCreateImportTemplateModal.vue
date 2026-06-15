<script setup>
const props = defineProps({
    importTemplateOpen: {
        type: Boolean,
        default: false,
    },
    importTemplateSaving: {
        type: Boolean,
        default: false,
    },
    importTemplateError: {
        type: String,
        default: '',
    },
    importTemplateForm: {
        type: Object,
        required: true,
    },
    importTemplateGeneralFields: {
        type: Array,
        default: () => [],
    },
    importTemplateItemFields: {
        type: Array,
        default: () => [],
    },
    closeImportTemplate: {
        type: Function,
        required: true,
    },
    saveImportTemplate: {
        type: Function,
        required: true,
    },
});
</script>

<template>
    <div v-if="props.importTemplateOpen" class="import-preview-modal" @click.self="props.closeImportTemplate">
        <div class="import-preview-dialog import-template-dialog">
            <div class="import-preview-card">
                <div class="import-preview-head">
                    <div>
                        <h3 class="directory-section-title import-preview-main-title">Save Your Import Template</h3>
                        <p class="import-preview-copy">Enter the column names your company usually uses. This is optional and only needs to be done once.</p>
                    </div>
                    <div class="import-preview-actions">
                        <button type="button" class="secondary-button compact-button" @click="props.closeImportTemplate">
                            Cancel
                        </button>
                        <button type="button" class="primary-button compact-button" :disabled="props.importTemplateSaving" @click="props.saveImportTemplate">
                            {{ props.importTemplateSaving ? 'Saving...' : 'Save Template' }}
                        </button>
                    </div>
                </div>

                <div class="import-template-surface">
                    <label class="field import-template-name-field">
                        <span>Template Name</span>
                        <input v-model="props.importTemplateForm.name" type="text" placeholder="My RFQ Import Template" />
                    </label>

                    <p v-if="props.importTemplateError" class="import-error">{{ props.importTemplateError }}</p>

                    <div class="import-template-grid">
                        <section class="import-template-column">
                            <h4 class="directory-section-title import-preview-subtitle">General Information</h4>
                            <div class="import-template-fields">
                                <label v-for="[key, label] in props.importTemplateGeneralFields" :key="`general-template-${key}`" class="field import-template-field">
                                    <span>{{ label }}</span>
                                    <input
                                        v-model="props.importTemplateForm.general[key]"
                                        type="text"
                                        :placeholder="`${label} aliases, comma separated`"
                                    />
                                </label>
                            </div>
                        </section>

                        <section class="import-template-column">
                            <h4 class="directory-section-title import-preview-subtitle">Items to Quote</h4>
                            <div class="import-template-fields">
                                <label v-for="[key, label] in props.importTemplateItemFields" :key="`item-template-${key}`" class="field import-template-field">
                                    <span>{{ label }}</span>
                                    <input
                                        v-model="props.importTemplateForm.items[key]"
                                        type="text"
                                        :placeholder="`${label} aliases, comma separated`"
                                    />
                                </label>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.field {
    display: grid;
    gap: 5px;
    position: relative;
    min-width: 0;
}

.field span {
    color: #04151f;
    font-size: 0.86rem;
    font-weight: 600;
}

.field input {
    width: 100%;
    height: 48px;
    padding: 0 16px;
    border: 1px solid rgba(4, 21, 31, 0.14);
    border-radius: 10px;
    background: #fff;
    color: #04151f;
    font-size: 0.93rem;
    font-weight: 400;
    line-height: 1.2;
    box-sizing: border-box;
}

.field input::placeholder {
    color: rgba(4, 21, 31, 0.42);
    font-weight: 400;
}

.secondary-button,
.primary-button {
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

.secondary-button {
    border: 1px solid rgba(4, 21, 31, 0.1);
    background: #fff;
    color: #04151f;
}

.primary-button {
    border: 1px solid #0f172a;
    background: #0f172a;
    color: #fff;
}

.compact-button {
    min-height: 40px;
    padding: 0 14px;
    font-size: 0.86rem;
}

.import-error {
    margin: 0;
    color: #be123c;
    font-size: 0.9rem;
    font-weight: 600;
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

.import-template-dialog {
    width: min(1160px, calc(100vw - 56px));
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

.import-template-surface {
    display: grid;
    gap: 16px;
}

.import-template-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.import-template-column {
    padding: 20px 22px;
    border-radius: 10px;
    background: #f8fafb;
}

.import-template-fields {
    display: grid;
    gap: 12px;
    margin-top: 14px;
}

.import-template-field,
.import-template-name-field {
    padding-bottom: 0;
}

.import-template-name-field {
    max-width: 420px;
}

@media (max-width: 720px) {
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

    .import-template-grid {
        grid-template-columns: 1fr;
    }
}
</style>
