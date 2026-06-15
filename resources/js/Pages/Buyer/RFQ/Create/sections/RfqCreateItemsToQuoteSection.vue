<script setup>
const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    unitOptions: {
        type: Array,
        default: () => [],
    },
    qualityOptions: {
        type: Array,
        default: () => [],
    },
    isEditMode: {
        type: Boolean,
        default: false,
    },
    isServiceRequest: {
        type: Boolean,
        default: false,
    },
    requestTypeLocked: {
        type: Boolean,
        default: false,
    },
    canEditRequestContent: {
        type: Boolean,
        default: true,
    },
    isGeneralOnlyEdit: {
        type: Boolean,
        default: false,
    },
    generalOnlyEditMessage: {
        type: String,
        default: '',
    },
    importParsing: {
        type: Boolean,
        default: false,
    },
    importError: {
        type: String,
        default: '',
    },
    importPreview: {
        type: Object,
        default: null,
    },
    serviceTitleCharacterCount: {
        type: Number,
        default: 0,
    },
    serviceDescriptionCharacterCount: {
        type: Number,
        default: 0,
    },
    serviceFileTriggerLabel: {
        type: String,
        required: true,
    },
    serviceTitleMaxCharacters: {
        type: Number,
        required: true,
    },
    serviceDescriptionMinCharacters: {
        type: Number,
        required: true,
    },
    hasError: {
        type: Function,
        required: true,
    },
    getError: {
        type: Function,
        required: true,
    },
    clearFieldErrorIfValid: {
        type: Function,
        required: true,
    },
    itemKey: {
        type: Function,
        required: true,
    },
    hasItemError: {
        type: Function,
        required: true,
    },
    getItemError: {
        type: Function,
        required: true,
    },
    validateRequiredText: {
        type: Function,
        required: true,
    },
    validateQuantity: {
        type: Function,
        required: true,
    },
    validateRequiredSelect: {
        type: Function,
        required: true,
    },
    validateServiceTitle: {
        type: Function,
        required: true,
    },
    validateServiceDescription: {
        type: Function,
        required: true,
    },
    formatOptionLabel: {
        type: Function,
        required: true,
    },
    setRequestType: {
        type: Function,
        required: true,
    },
    openImportPicker: {
        type: Function,
        required: true,
    },
    handleImportSelection: {
        type: Function,
        required: true,
    },
    clearImportPreview: {
        type: Function,
        required: true,
    },
    openImportPreview: {
        type: Function,
        required: true,
    },
    addItem: {
        type: Function,
        required: true,
    },
    removeItem: {
        type: Function,
        required: true,
    },
    setFileInputRef: {
        type: Function,
        required: true,
    },
    openFilePicker: {
        type: Function,
        required: true,
    },
    handleFiles: {
        type: Function,
        required: true,
    },
    removeFile: {
        type: Function,
        required: true,
    },
    canPreviewAttachment: {
        type: Function,
        required: true,
    },
    openAttachmentViewer: {
        type: Function,
        required: true,
    },
    setImportFileInputRef: {
        type: Function,
        required: true,
    },
    setServiceFileInputRef: {
        type: Function,
        required: true,
    },
    openServiceFilePicker: {
        type: Function,
        required: true,
    },
    handleServiceFiles: {
        type: Function,
        required: true,
    },
    removeServiceFile: {
        type: Function,
        required: true,
    },
});
</script>

<template>
    <div class="subsection-surface">
        <div class="items-head">
            <h2 class="directory-section-title">Items to Quote</h2>
            <div class="items-head-actions">
                <div v-if="!props.isEditMode && !props.requestTypeLocked" class="request-mode-toggle" role="tablist" aria-label="Request type">
                    <button
                        type="button"
                        class="request-mode-button"
                        :class="{ active: !props.isServiceRequest }"
                        @click="props.setRequestType('spare_parts')"
                    >
                        Spare Parts
                    </button>
                    <button
                        type="button"
                        class="request-mode-button"
                        :class="{ active: props.isServiceRequest }"
                        @click="props.setRequestType('service_request')"
                    >
                        Service Request
                    </button>
                </div>
                <template v-if="!props.isServiceRequest && props.canEditRequestContent">
                    <p class="import-helper-copy">Upload your own RFQ file in any usual company format. We will try to map it into this form automatically.</p>
                    <input
                        :ref="props.setImportFileInputRef"
                        class="import-file-input"
                        type="file"
                        accept=".pdf,.png,.jpg,.jpeg,.webp,.csv,.xlsx,.xls"
                        @change="props.handleImportSelection"
                    />
                    <button type="button" class="secondary-button import-button unified-head-control" :disabled="props.importParsing" @click="props.openImportPicker">
                        <span v-if="props.importParsing" class="import-button-content">
                            <svg class="import-spinner" viewBox="0 0 20 20" aria-hidden="true">
                                <circle cx="10" cy="10" r="7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-dasharray="28 18" />
                            </svg>
                            <span>Reading file...</span>
                        </span>
                        <span v-else>Upload PDF / Image / Excel / CSV</span>
                    </button>
                </template>
            </div>
        </div>

        <p v-if="props.isGeneralOnlyEdit" class="section-lock-copy">
            {{ props.generalOnlyEditMessage }}
        </p>

        <fieldset class="section-lock-fieldset" :disabled="props.isGeneralOnlyEdit">
            <p v-if="props.importError" class="import-error">{{ props.importError }}</p>

            <section v-if="!props.isServiceRequest && props.importPreview" class="import-preview-banner">
                <div>
                    <h3 class="import-preview-banner-title">Import Preview</h3>
                    <p class="import-preview-banner-copy">
                        Import file is ready for review.
                        {{ props.importPreview.summary?.items_count ?? 0 }} imported items.
                    </p>
                </div>
                <div class="import-preview-actions">
                    <button type="button" class="secondary-button compact-button" @click="props.clearImportPreview">
                        Discard
                    </button>
                    <button type="button" class="primary-button compact-button" @click="props.openImportPreview">
                        Open Preview
                    </button>
                </div>
            </section>

            <div v-if="!props.isServiceRequest" class="items-scroll-shell">
                <div class="items-scroll-track">
                    <div class="item-table-head" aria-hidden="true">
                        <span>#</span>
                        <span>Product<span class="required-star">*</span></span>
                        <span>Part No</span>
                        <span>Manufacturer</span>
                        <span>MFG Model / Type</span>
                        <span>Catalog Code</span>
                        <span>Serial Number</span>
                        <span>Drawing Number</span>
                        <span>Qty<span class="required-star">*</span></span>
                        <span>Unit<span class="required-star">*</span></span>
                        <span>ROB</span>
                        <span>Quality</span>
                        <span>Comments</span>
                        <span>Files</span>
                        <span class="item-action-head">Action</span>
                    </div>

                    <article v-for="(item, index) in props.form.items" :key="index" class="item-card">
                        <div class="item-row-grid">
                            <div class="item-row-index" aria-label="Item number">{{ index + 1 }}</div>

                            <label class="field item-mobile-field">
                                <span>Product <span class="required-star">*</span></span>
                                <input
                                    v-model="item.product_name"
                                    :class="{ 'has-error': props.hasItemError(index, 'product_name') }"
                                    type="text"
                                    placeholder="Product"
                                    @input="props.clearFieldErrorIfValid(props.itemKey(index, 'product_name'), props.validateRequiredText(item.product_name))"
                                />
                                <small v-if="props.hasItemError(index, 'product_name')" class="field-error">{{ props.getItemError(index, 'product_name') }}</small>
                            </label>

                            <label class="field item-mobile-field">
                                <span>Part No</span>
                                <input v-model="item.part_no" type="text" placeholder="Part No" />
                            </label>

                            <label class="field item-mobile-field">
                                <span>Manufacturer</span>
                                <input v-model="item.manufacturer" type="text" placeholder="Manufacturer" />
                            </label>

                            <label class="field item-mobile-field">
                                <span>MFG Model / Type</span>
                                <input v-model="item.model_type" type="text" placeholder="MFG Model / Type" />
                            </label>

                            <label class="field item-mobile-field">
                                <span>Catalog Code</span>
                                <input v-model="item.catalog_code" type="text" placeholder="IMPA / ISSA / Unitor / Nitor code" />
                            </label>

                            <label class="field item-mobile-field">
                                <span>Serial Number</span>
                                <input v-model="item.serial_number" type="text" placeholder="Serial Number" />
                            </label>

                            <label class="field item-mobile-field">
                                <span>Drawing Number</span>
                                <input v-model="item.drawing_number" type="text" placeholder="Drawing Number" />
                            </label>

                            <label class="field item-mobile-field">
                                <span>Qty <span class="required-star">*</span></span>
                                <input
                                    v-model="item.quantity"
                                    :class="{ 'has-error': props.hasItemError(index, 'quantity') }"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    placeholder="Qty"
                                    @input="props.clearFieldErrorIfValid(props.itemKey(index, 'quantity'), props.validateQuantity(item.quantity))"
                                />
                                <small v-if="props.hasItemError(index, 'quantity')" class="field-error">{{ props.getItemError(index, 'quantity') }}</small>
                            </label>

                            <label class="field item-mobile-field">
                                <span>Unit <span class="required-star">*</span></span>
                                <select
                                    v-model="item.unit"
                                    :class="{ 'has-error': props.hasItemError(index, 'unit') }"
                                    @change="props.clearFieldErrorIfValid(props.itemKey(index, 'unit'), props.validateRequiredSelect(item.unit))"
                                >
                                    <option disabled value="">Select</option>
                                    <option v-for="unit in props.unitOptions" :key="unit" :value="unit">{{ unit }}</option>
                                </select>
                                <small v-if="props.hasItemError(index, 'unit')" class="field-error">{{ props.getItemError(index, 'unit') }}</small>
                            </label>

                            <label class="field item-mobile-field">
                                <span>ROB</span>
                                <input v-model="item.rob" type="number" min="0" step="0.01" placeholder="ROB" />
                            </label>

                            <label class="field item-mobile-field">
                                <span>Quality</span>
                                <select v-model="item.quality" :class="{ 'has-error': props.hasItemError(index, 'quality') }">
                                    <option disabled value="">Select</option>
                                    <option v-for="quality in props.qualityOptions" :key="quality" :value="quality">
                                        {{ props.formatOptionLabel(quality) }}
                                    </option>
                                </select>
                                <small v-if="props.hasItemError(index, 'quality')" class="field-error">{{ props.getItemError(index, 'quality') }}</small>
                            </label>

                            <label class="field item-mobile-field">
                                <span>Comments</span>
                                <input v-model="item.comments" type="text" placeholder="Comments" />
                            </label>

                            <label class="field item-mobile-field">
                                <span>Files</span>
                                <div class="file-upload-field">
                                    <button type="button" class="file-upload-trigger" :class="{ 'has-error': props.hasItemError(index, 'files') }" @click="props.openFilePicker(index)">
                                        {{ Array.isArray(item.files) && item.files.length ? `${item.files.length} file${item.files.length === 1 ? '' : 's'} selected` : 'Upload Files' }}
                                    </button>
                                    <input
                                        :ref="props.setFileInputRef(index)"
                                        class="file-upload-input"
                                        type="file"
                                        multiple
                                        @change="props.handleFiles($event, index)"
                                    />
                                    <div v-if="item.files?.length" class="file-chip-list">
                                        <div v-for="(file, fileIndex) in item.files" :key="`${file.id ?? file.name}-${fileIndex}`" class="file-chip">
                                            <button
                                                v-if="props.canPreviewAttachment(file)"
                                                type="button"
                                                class="file-chip-name file-chip-link-button"
                                                @click="props.openAttachmentViewer(item.files, fileIndex)"
                                            >
                                                {{ file.name }}
                                            </button>
                                            <span v-else class="file-chip-name">{{ file.name }}</span>
                                            <button type="button" class="file-chip-remove" @click="props.removeFile(index, fileIndex)">&times;</button>
                                        </div>
                                    </div>
                                </div>
                            </label>

                            <div class="item-action-cell">
                                <button
                                    type="button"
                                    class="item-icon-button item-remove-button"
                                    title="Remove item"
                                    aria-label="Remove item"
                                    @click="props.removeItem(index)"
                                >
                                    <svg viewBox="0 0 20 20" aria-hidden="true">
                                        <path d="M7.5 3.5h5l.5 1.5H16a1 1 0 1 1 0 2h-.6l-.6 8.1A2 2 0 0 1 12.8 17H7.2a2 2 0 0 1-1.99-1.9L4.6 7H4a1 1 0 1 1 0-2h3l.5-1.5Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                                        <path d="M8.5 8.5v5m3-5v5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                    </svg>
                                </button>
                                <button
                                    v-if="index === props.form.items.length - 1"
                                    type="button"
                                    class="item-icon-button item-add-button"
                                    title="Add item"
                                    aria-label="Add item"
                                    @click="props.addItem"
                                >
                                    <svg viewBox="0 0 20 20" aria-hidden="true">
                                        <path d="M10 4.5v11m-5.5-5.5h11" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <div v-else class="service-request-shell">
                <div class="service-request-card">
                    <div class="service-request-stack">
                        <label class="field field-span-2">
                            <span>Title <span class="required-star">*</span></span>
                            <input
                                v-model="props.form.service_title"
                                :class="{ 'has-error': props.hasError('service_title') }"
                                type="text"
                                :maxlength="props.serviceTitleMaxCharacters"
                                placeholder="e.g. Drydock maintenance support"
                                @input="props.clearFieldErrorIfValid('service_title', props.validateServiceTitle(props.form.service_title))"
                            />
                            <small class="field-note">
                                Keep it within {{ props.serviceTitleMaxCharacters }} characters. {{ props.serviceTitleCharacterCount }}/{{ props.serviceTitleMaxCharacters }} used.
                            </small>
                            <small v-if="props.hasError('service_title')" class="field-error">{{ props.getError('service_title') }}</small>
                        </label>

                        <label class="field field-span-2 service-request-description">
                            <span>Description <span class="required-star">*</span></span>
                            <textarea
                                v-model="props.form.service_description"
                                :class="{ 'has-error': props.hasError('service_description') }"
                                rows="8"
                                placeholder="Describe the scope, preferred timeline, and key requirements"
                                @input="props.clearFieldErrorIfValid('service_description', props.validateServiceDescription(props.form.service_description))"
                            ></textarea>
                            <small class="field-note">
                                Use at least {{ props.serviceDescriptionMinCharacters }} characters. {{ props.serviceDescriptionCharacterCount }}/{{ props.serviceDescriptionMinCharacters }} characters.
                            </small>
                            <small v-if="props.hasError('service_description')" class="field-error">{{ props.getError('service_description') }}</small>
                        </label>

                        <label class="field field-span-2">
                            <span>Files</span>
                            <div class="file-upload-field service-request-files">
                                <button type="button" class="file-upload-trigger" :class="{ 'has-error': props.hasError('service_files') }" @click="props.openServiceFilePicker">
                                    {{ props.serviceFileTriggerLabel }}
                                </button>
                                <input
                                    :ref="props.setServiceFileInputRef"
                                    class="file-upload-input"
                                    type="file"
                                    multiple
                                    @change="props.handleServiceFiles"
                                />
                                <div v-if="props.form.service_files?.length" class="file-chip-list">
                                    <div v-for="(file, fileIndex) in props.form.service_files" :key="`${file.id ?? file.name}-${fileIndex}`" class="file-chip">
                                        <button
                                            v-if="props.canPreviewAttachment(file)"
                                            type="button"
                                            class="file-chip-name file-chip-link-button"
                                            @click="props.openAttachmentViewer(props.form.service_files, fileIndex)"
                                        >
                                            {{ file.name }}
                                        </button>
                                        <span v-else class="file-chip-name">{{ file.name }}</span>
                                        <button type="button" class="file-chip-remove" @click="props.removeServiceFile(fileIndex)">&times;</button>
                                    </div>
                                </div>
                            </div>
                            <small v-if="props.hasError('service_files')" class="field-error">{{ props.getError('service_files') }}</small>
                        </label>
                    </div>
                </div>
            </div>
        </fieldset>
    </div>
</template>

<style scoped>
.subsection-surface {
    padding: 24px;
    border-radius: 10px;
    background: #f8fafb;
    min-width: 0;
}

.items-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.items-head .directory-section-title {
    margin: 0;
    font-size: 1.42rem;
    line-height: 1.2;
}

.items-head-actions,
.import-preview-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.items-head-actions {
    flex-direction: column;
    align-items: flex-end;
}

.request-mode-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px;
    border-radius: 999px;
    background: rgba(7, 112, 170, 0.08);
    width: min(100%, 304px);
    justify-content: space-between;
}

.request-mode-button {
    flex: 1 1 0;
    min-height: 36px;
    padding: 0 14px;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: rgba(4, 21, 31, 0.72);
    font-size: 0.86rem;
    font-weight: 600;
    line-height: 1;
    cursor: pointer;
    transition: background-color 160ms ease, color 160ms ease, box-shadow 160ms ease;
}

.request-mode-button.active {
    background: #fff;
    color: #04151f;
    box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
}

.import-helper-copy {
    margin: 0;
    max-width: 420px;
    color: rgba(4, 21, 31, 0.62);
    font-size: 0.86rem;
    line-height: 1.5;
    font-style: italic;
    text-align: right;
}

.import-file-input,
.file-upload-input {
    display: none;
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

.compact-button,
.import-button {
    min-height: 40px;
    padding: 0 14px;
    font-size: 0.86rem;
}

.unified-head-control {
    width: 280px;
    max-width: 100%;
}

.import-button-content {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.import-spinner {
    width: 15px;
    height: 15px;
    animation: import-spin 0.9s linear infinite;
}

.section-lock-copy {
    margin: 12px 0 0;
    color: #9a3412;
    font-size: 0.88rem;
    font-weight: 600;
    line-height: 1.5;
}

.section-lock-fieldset {
    margin: 0;
    padding: 0;
    border: 0;
    min-width: 0;
}

.section-lock-fieldset:disabled {
    opacity: 0.82;
}

.import-error {
    margin: 12px 0 0;
    color: #be123c;
    font-size: 0.9rem;
    font-weight: 600;
}

.import-preview-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-top: 14px;
    padding: 16px 18px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: #fff;
}

.import-preview-banner-title {
    margin: 0;
    color: #04151f;
    font-size: 0.98rem;
    font-weight: 700;
}

.import-preview-banner-copy {
    margin: 6px 0 0;
    color: rgba(4, 21, 31, 0.68);
    font-size: 0.88rem;
    line-height: 1.5;
}

.items-scroll-shell {
    margin-top: 16px;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    overflow-x: auto;
    overflow-y: hidden;
    padding-bottom: 14px;
}

.items-scroll-track {
    width: max-content;
    min-width: 100%;
}

.item-table-head,
.item-row-grid {
    display: grid;
    grid-template-columns:
        30px
        175px
        150px
        150px
        150px
        150px
        150px
        150px
        85px
        85px
        85px
        150px
        150px
        150px
        84px;
    gap: 8px;
    align-items: start;
}

.item-table-head {
    margin-top: 10px;
    padding-bottom: 4px;
    align-items: center;
}

.item-table-head span {
    color: #04151f;
    font-size: 0.82rem;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 0;
}

.item-table-head span:first-child {
    padding-left: 8px;
}

.item-table-head span:not(:first-child) {
    padding-left: 12px;
}

.item-action-head,
.item-action-cell {
    position: sticky;
    right: 0;
    z-index: 2;
}

.item-action-head {
    justify-content: center;
    background: #f8fafb;
}

.item-card {
    margin-top: 0;
    padding: 10px 12px;
    border: 1px solid rgba(4, 21, 31, 0.1);
    border-radius: 10px;
    background: #fff;
}

.item-card:first-of-type {
    margin-top: 10px;
}

.item-card:last-of-type {
    margin-bottom: 10px;
}

.field {
    display: grid;
    gap: 5px;
    position: relative;
    padding-bottom: 18px;
    min-width: 0;
}

.field-span-2 {
    grid-column: span 2;
}

.field span {
    color: #04151f;
    font-size: 0.86rem;
    font-weight: 600;
}

.field span.required-star,
.required-star {
    color: #be123c;
}

.field input,
.field select,
.field textarea {
    width: 100%;
    border: 1px solid rgba(4, 21, 31, 0.14);
    border-radius: 10px;
    background: #fff;
    color: #04151f;
    font-size: 0.93rem;
    font-weight: 400;
    line-height: 1.2;
}

.field input,
.field select {
    height: 48px;
    padding: 0 16px;
}

.field textarea {
    padding: 14px 16px;
}

.field input::placeholder,
.field textarea::placeholder {
    color: rgba(4, 21, 31, 0.42);
    font-weight: 400;
}

.field-error {
    position: absolute;
    left: 0;
    bottom: 0;
    color: #be123c;
    font-size: 0.8rem;
    font-weight: 600;
    line-height: 1.1;
}

.has-error {
    border-color: #be123c !important;
    box-shadow: 0 0 0 1px rgba(190, 18, 60, 0.08);
}

.item-mobile-field > span {
    display: none;
}

.item-row-index {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    min-height: 36px;
    color: #04151f;
    font-size: 0.84rem;
    font-weight: 600;
    padding-left: 0;
}

.item-row-grid .field,
.item-mobile-field {
    padding-bottom: 0;
}

.item-row-grid .field input,
.item-row-grid .field select,
.item-row-grid .field textarea {
    min-height: 36px;
    padding-top: 7px;
    padding-bottom: 7px;
    font-size: 0.86rem;
}

.item-row-grid .field select {
    padding-left: 10px;
    padding-right: 26px;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-color: #fff;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M5 7.5 10 12.5l5-5' stroke='%2304151f' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-position: calc(100% - 10px) 50%;
    background-size: 12px 12px;
    background-repeat: no-repeat;
}

.item-row-grid .field input::placeholder,
.item-row-grid .field textarea::placeholder {
    font-size: 0.82rem;
}

.item-row-grid .field .field-error,
.item-mobile-field .field-error {
    bottom: 0;
}

.item-row-grid .field:has(.field-error),
.item-mobile-field:has(.field-error) {
    padding-bottom: 22px;
}

.item-action-cell {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    align-self: center;
    gap: 6px;
    min-height: 36px;
    background: #fff;
}

.item-icon-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    border: 1px solid rgba(4, 21, 31, 0.12);
    background: #fff;
    padding: 0;
}

.item-icon-button svg {
    width: 15px;
    height: 15px;
}

.item-remove-button {
    color: #be123c;
    border-color: rgba(190, 24, 93, 0.18);
    background: rgba(255, 241, 242, 0.92);
}

.item-add-button {
    color: #15803d;
    border-color: rgba(21, 128, 61, 0.18);
    background: rgba(240, 253, 244, 0.96);
}

.file-upload-field {
    display: grid;
    gap: 6px;
}

.file-upload-trigger {
    width: 100%;
    min-height: 44px;
    padding: 11px 10px;
    border: 1px solid rgba(4, 21, 31, 0.14);
    border-radius: 10px;
    background: #fff;
    color: rgba(4, 21, 31, 0.7);
    font-size: 0.86rem;
    font-weight: 400;
    line-height: 1.2;
    text-align: left;
}

.file-chip-list {
    display: flex;
    gap: 6px;
    flex-wrap: nowrap;
    overflow-x: auto;
    overflow-y: hidden;
    padding-bottom: 2px;
    scrollbar-width: thin;
}

.file-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex: 0 0 auto;
    max-width: 180px;
    padding: 4px 8px;
    border-radius: 8px;
    background: rgba(4, 21, 31, 0.04);
}

.file-chip-name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #04151f;
    font-size: 0.78rem;
    line-height: 1.2;
}

.file-chip-link-button {
    border: 0;
    background: transparent;
    padding: 0;
    text-align: left;
    text-decoration: underline;
    text-decoration-color: rgba(7, 112, 170, 0.28);
    text-underline-offset: 2px;
    cursor: pointer;
}

.file-chip-remove {
    border: 0;
    background: transparent;
    color: rgba(4, 21, 31, 0.62);
    font-size: 0.92rem;
    line-height: 1;
    padding: 0;
}

.service-request-shell {
    margin-top: 8px;
}

.service-request-card {
    padding: 22px 22px 20px;
    border-radius: 18px;
    background: #f8fafb;
}

.service-request-stack {
    display: grid;
    gap: 16px;
}

.service-request-description textarea {
    min-height: 180px;
    resize: vertical;
}

.service-request-files {
    min-height: 50px;
}

.field-note {
    margin-top: 6px;
    color: rgba(4, 21, 31, 0.6);
    font-size: 0.82rem;
    line-height: 1.5;
}

@keyframes import-spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 960px) {
    .items-scroll-shell {
        overflow: visible;
        padding-bottom: 0;
    }

    .items-scroll-track {
        min-width: 0;
    }

    .item-row-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .item-table-head {
        display: none;
    }

    .item-mobile-field > span {
        display: inline;
    }

    .item-row-index {
        display: none;
    }
}

@media (max-width: 720px) {
    .subsection-surface {
        padding: 20px;
    }

    .items-head,
    .items-head-actions,
    .import-preview-banner,
    .import-preview-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .request-mode-toggle {
        justify-content: space-between;
    }

    .import-helper-copy {
        max-width: none;
        text-align: left;
    }

    .item-row-grid {
        grid-template-columns: 1fr;
    }

    .field-span-2 {
        grid-column: span 1;
    }
}
</style>
