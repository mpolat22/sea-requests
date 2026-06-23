<script setup>
import { computed } from 'vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    importTemplateSaved: {
        type: Boolean,
        default: false,
    },
    scopeNote: {
        type: String,
        default: '',
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
    validateRequiredText: {
        type: Function,
        required: true,
    },
    validateImoNumber: {
        type: Function,
        required: true,
    },
    normalizeImoNumber: {
        type: Function,
        required: true,
    },
    validateDateNotPast: {
        type: Function,
        required: true,
    },
    validateRequiredSelect: {
        type: Function,
        required: true,
    },
    openImportTemplate: {
        type: Function,
        required: true,
    },
    selectedCountries: {
        type: Array,
        default: () => [],
    },
    selectedCountriesLabel: {
        type: String,
        required: true,
    },
    selectedPortsCount: {
        type: Number,
        default: 0,
    },
    selectedPortsLabel: {
        type: String,
        required: true,
    },
    countryMenuOpen: {
        type: Boolean,
        default: false,
    },
    portMenuOpen: {
        type: Boolean,
        default: false,
    },
    countrySearch: {
        type: String,
        default: '',
    },
    portSearch: {
        type: String,
        default: '',
    },
    filteredCountryOptions: {
        type: Array,
        default: () => [],
    },
    filteredSelectedCountryPortGroups: {
        type: Array,
        default: () => [],
    },
    selectedCountryPortGroups: {
        type: Array,
        default: () => [],
    },
    toggleCountryMenu: {
        type: Function,
        required: true,
    },
    handleCountryTriggerKeydown: {
        type: Function,
        required: true,
    },
    handleCountryMenuKeydown: {
        type: Function,
        required: true,
    },
    isCountrySelected: {
        type: Function,
        required: true,
    },
    toggleCountry: {
        type: Function,
        required: true,
    },
    togglePortMenu: {
        type: Function,
        required: true,
    },
    handlePortTriggerKeydown: {
        type: Function,
        required: true,
    },
    handlePortMenuKeydown: {
        type: Function,
        required: true,
    },
    selectAllPorts: {
        type: Function,
        required: true,
    },
    clearAllPorts: {
        type: Function,
        required: true,
    },
    selectAllPortsForCountry: {
        type: Function,
        required: true,
    },
    isPortSelected: {
        type: Function,
        required: true,
    },
    togglePort: {
        type: Function,
        required: true,
    },
    todayIso: {
        type: Function,
        required: true,
    },
    setCountryMenuRef: {
        type: Function,
        required: true,
    },
    setCountryMenuListRef: {
        type: Function,
        required: true,
    },
    setPortMenuRef: {
        type: Function,
        required: true,
    },
    setPortMenuListRef: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(['update:countrySearch', 'update:portSearch']);

const countrySearchModel = computed({
    get: () => props.countrySearch,
    set: (value) => emit('update:countrySearch', value),
});

const portSearchModel = computed({
    get: () => props.portSearch,
    set: (value) => emit('update:portSearch', value),
});
</script>

<template>
    <div class="subsection-surface">
        <div class="section-heading">
            <div>
                <h2 class="directory-section-title">General Information</h2>
                <p v-if="props.scopeNote" class="general-scope-note">{{ props.scopeNote }}</p>
            </div>
            <div class="items-head-actions general-head-actions">
                <p class="import-template-copy">Use the same vendor or internal form often? Save your usual column names once and we will detect them more accurately next time.</p>
                <div class="general-template-actions">
                    <button type="button" class="secondary-button compact-button general-template-button unified-head-control" @click="props.openImportTemplate">
                        {{ props.importTemplateSaved ? 'Saved import template ready' : 'Set up my import template' }}
                    </button>
                </div>
            </div>
        </div>

        <div class="form-grid">
            <label class="field">
                <span>Reference No <span class="required-star">*</span></span>
                <input
                    v-model="props.form.reference_no"
                    :class="{ 'has-error': props.hasError('reference_no') }"
                    type="text"
                    placeholder="Enter Reference Number"
                    @input="props.clearFieldErrorIfValid('reference_no', props.validateRequiredText(props.form.reference_no))"
                />
                <small v-if="props.hasError('reference_no')" class="field-error">{{ props.getError('reference_no') }}</small>
            </label>

            <label class="field">
                <span>Company <span class="required-star">*</span></span>
                <input
                    v-model="props.form.company_name"
                    :class="{ 'has-error': props.hasError('company_name') }"
                    type="text"
                    placeholder="Enter Company Name"
                    @input="props.clearFieldErrorIfValid('company_name', props.validateRequiredText(props.form.company_name))"
                />
                <small v-if="props.hasError('company_name')" class="field-error">{{ props.getError('company_name') }}</small>
            </label>

            <label class="field">
                <span>Ship <span class="required-star">*</span></span>
                <input
                    v-model="props.form.ship_name"
                    :class="{ 'has-error': props.hasError('ship_name') }"
                    type="text"
                    placeholder="Enter Ship Name"
                    @input="props.clearFieldErrorIfValid('ship_name', props.validateRequiredText(props.form.ship_name))"
                />
                <small v-if="props.hasError('ship_name')" class="field-error">{{ props.getError('ship_name') }}</small>
            </label>

            <label class="field">
                <span>IMO Number <span class="required-star">*</span></span>
                <input
                    v-model="props.form.imo_number"
                    :class="{ 'has-error': props.hasError('imo_number') }"
                    type="text"
                    inputmode="numeric"
                    maxlength="7"
                    placeholder="Enter IMO Number"
                    @input="props.form.imo_number = props.normalizeImoNumber(props.form.imo_number); props.clearFieldErrorIfValid('imo_number', props.validateImoNumber(props.form.imo_number))"
                />
                <small v-if="props.hasError('imo_number')" class="field-error">{{ props.getError('imo_number') }}</small>
            </label>

            <label class="field">
                <span>RFQ Status <span class="required-star">*</span></span>
                <select v-model="props.form.status" class="choice-control">
                    <option value="open">Open</option>
                    <option value="closed">Closed</option>
                </select>
            </label>

            <label class="field selection-field">
                <span>Request Country <span class="required-star">*</span></span>
                <div :ref="props.setCountryMenuRef" class="dropdown-shell">
                    <button
                        type="button"
                        class="dropdown-trigger choice-control"
                        :class="{ 'is-placeholder': !props.selectedCountries.length, 'has-error': props.hasError('country_names') }"
                        @click="props.toggleCountryMenu"
                        @keydown="props.handleCountryTriggerKeydown"
                    >
                        <span>{{ props.selectedCountriesLabel }}</span>
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M5 7.5 10 12.5l5-5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>

                    <div
                        v-if="props.countryMenuOpen"
                        :ref="props.setCountryMenuListRef"
                        class="dropdown-menu"
                        tabindex="0"
                        @keydown="props.handleCountryMenuKeydown"
                    >
                        <div class="dropdown-search-wrap">
                            <input
                                v-model="countrySearchModel"
                                type="search"
                                class="dropdown-search-input"
                                placeholder="Search countries..."
                                @keydown.stop
                            />
                        </div>

                        <label
                            v-for="country in props.filteredCountryOptions"
                            :key="country"
                            class="dropdown-option"
                            :data-country-option="country"
                        >
                            <input
                                type="checkbox"
                                :checked="props.isCountrySelected(country)"
                                @change="props.toggleCountry(country)"
                            />
                            <span>{{ country }}</span>
                        </label>
                        <p v-if="!props.filteredCountryOptions.length" class="selector-empty">No countries found.</p>
                    </div>
                </div>
                <small v-if="props.form.errors.country_names" class="field-error">{{ props.form.errors.country_names }}</small>
            </label>

            <div class="field selection-field">
                <span>Request Ports <span class="required-star">*</span></span>
                <div :ref="props.setPortMenuRef" class="dropdown-shell">
                    <button
                        type="button"
                        class="dropdown-trigger choice-control"
                        :class="{ 'is-placeholder': !props.selectedPortsCount, 'has-error': props.hasError('ports_by_country') }"
                        :disabled="!props.selectedCountries.length"
                        @click="props.togglePortMenu"
                        @keydown="props.handlePortTriggerKeydown"
                    >
                        <span>{{ props.selectedPortsLabel }}</span>
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M5 7.5 10 12.5l5-5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>

                    <div
                        v-if="props.portMenuOpen"
                        :ref="props.setPortMenuListRef"
                        class="dropdown-menu dropdown-menu-wide"
                        tabindex="0"
                        @keydown="props.handlePortMenuKeydown"
                    >
                        <div class="dropdown-search-wrap">
                            <input
                                v-model="portSearchModel"
                                type="search"
                                class="dropdown-search-input"
                                placeholder="Search countries or ports..."
                                @keydown.stop
                            />
                        </div>

                        <div v-if="props.filteredSelectedCountryPortGroups.length" class="country-port-groups">
                            <div class="ports-menu-actions">
                                <button type="button" class="ports-menu-action" @click="props.selectAllPorts">
                                    All
                                </button>
                                <button type="button" class="ports-menu-action" @click="props.clearAllPorts">
                                    Clear
                                </button>
                            </div>

                            <section
                                v-for="group in props.filteredSelectedCountryPortGroups"
                                :key="group.country"
                                class="country-port-group"
                            >
                                <div class="country-port-group-head">
                                    <button
                                        type="button"
                                        class="country-port-group-title"
                                        @click="props.selectAllPortsForCountry(group.country)"
                                    >
                                        {{ group.country }}
                                    </button>
                                </div>
                                <div class="port-option-grid">
                                    <label
                                        v-for="port in group.ports"
                                        :key="port.id"
                                        class="port-option"
                                        :data-port-option="port.id"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="props.isPortSelected(group.country, port.id)"
                                            @change="props.togglePort(group.country, port.id)"
                                        />
                                        <span class="port-option-name">{{ port.name }}</span>
                                        <small v-if="port.unlocode" class="port-option-code">{{ port.unlocode }}</small>
                                    </label>
                                </div>
                            </section>
                        </div>
                        <p v-else class="selector-empty">
                            {{ props.selectedCountryPortGroups.length ? 'No ports found.' : 'Select one or more countries to choose ports.' }}
                        </p>
                    </div>
                </div>
                <small v-if="props.form.errors.ports_by_country" class="field-error">{{ props.form.errors.ports_by_country }}</small>
            </div>

            <label class="field">
                <span>Requisition Date <span class="required-star">*</span></span>
                <input
                    v-model="props.form.requisition_date"
                    :class="{ 'has-error': props.hasError('requisition_date') }"
                    type="date"
                    @input="props.clearFieldErrorIfValid('requisition_date', props.validateDateNotPast(props.form.requisition_date))"
                />
                <small v-if="props.hasError('requisition_date')" class="field-error">{{ props.getError('requisition_date') }}</small>
            </label>

            <label class="field">
                <span>Due Date <span class="required-star">*</span></span>
                <input
                    v-model="props.form.due_date"
                    :class="{ 'has-error': props.hasError('due_date') }"
                    type="date"
                    :min="props.todayIso()"
                    @input="props.clearFieldErrorIfValid('due_date', props.validateDateNotPast(props.form.due_date))"
                />
                <small v-if="props.hasError('due_date')" class="field-error">{{ props.getError('due_date') }}</small>
            </label>

            <label class="field">
                <span>Currency <span class="required-star">*</span></span>
                <select
                    v-model="props.form.currency"
                    class="choice-control"
                    :class="{ 'has-error': props.hasError('currency') }"
                    @change="props.clearFieldErrorIfValid('currency', props.validateRequiredSelect(props.form.currency))"
                >
                    <option disabled value="">Select Currency</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                    <option value="CNY">CNY</option>
                    <option value="AED">AED</option>
                </select>
                <small v-if="props.hasError('currency')" class="field-error">{{ props.getError('currency') }}</small>
            </label>

            <label class="field">
                <span>Priority <span class="required-star">*</span></span>
                <select
                    v-model="props.form.priority"
                    class="choice-control"
                    :class="{ 'has-error': props.hasError('priority') }"
                    @change="props.clearFieldErrorIfValid('priority', props.validateRequiredSelect(props.form.priority))"
                >
                    <option disabled value="">Select Priority</option>
                    <option value="low">Low</option>
                    <option value="normal">Normal</option>
                    <option value="high">High</option>
                    <option value="critical">Critical</option>
                </select>
                <small v-if="props.hasError('priority')" class="field-error">{{ props.getError('priority') }}</small>
            </label>

            <label class="field field-span-2 notes-field">
                <span>General Notes</span>
                <textarea v-model="props.form.general_notes" rows="1" placeholder="Enter General Notes"></textarea>
            </label>
        </div>
    </div>
</template>

<style scoped>
.subsection-surface {
    padding: 24px;
    border-radius: 10px;
    background: #f8fafb;
    min-width: 0;
}

.section-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}

.section-heading :deep(.directory-section-title) {
    margin: 0;
    font-size: 1.42rem;
    line-height: 1.2;
}

.items-head-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.general-head-actions {
    flex-direction: column;
    align-items: flex-end;
}

.import-template-copy {
    margin: 0;
    max-width: 420px;
    color: rgba(4, 21, 31, 0.62);
    font-size: 0.86rem;
    line-height: 1.5;
    font-style: italic;
    text-align: right;
}

.general-scope-note {
    margin: 8px 0 0;
    max-width: 540px;
    color: rgba(4, 21, 31, 0.64);
    font-size: 0.9rem;
    line-height: 1.5;
}

.general-template-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}

.secondary-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 18px;
    border-radius: 10px;
    border: 1px solid rgba(4, 21, 31, 0.1);
    background: #fff;
    color: #04151f;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 600;
}

.compact-button {
    min-height: 40px;
    padding: 0 14px;
    font-size: 0.86rem;
}

.general-template-button {
    background: #fff;
}

.unified-head-control {
    width: 280px;
    max-width: 100%;
}

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

.choice-control {
    width: 100%;
    height: 48px;
    min-height: 48px;
    padding: 0 44px 0 16px;
    border: 1px solid rgba(4, 21, 31, 0.12);
    border-radius: 10px;
    background-color: #fff;
    color: #04151f;
    font-size: 0.93rem;
    font-weight: 400;
    line-height: 1.2;
    box-sizing: border-box;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
}

.field select.choice-control {
    display: block;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M5 7.5 10 12.5l5-5' stroke='%2304151f' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-position: calc(100% - 16px) 50%;
    background-size: 14px 14px;
    background-repeat: no-repeat;
}

.field input::placeholder,
.field textarea::placeholder {
    color: rgba(4, 21, 31, 0.42);
    font-weight: 400;
}

.dropdown-trigger.is-placeholder {
    color: rgba(4, 21, 31, 0.72);
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

.notes-field textarea {
    min-height: 48px;
    resize: vertical;
}

.selection-field {
    align-content: start;
    min-width: 0;
}

.dropdown-shell {
    position: relative;
    min-width: 0;
}

.dropdown-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-right: 14px;
    text-align: left;
    background-color: #fff;
    min-width: 0;
}

.dropdown-trigger:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.dropdown-trigger span {
    font-size: 0.93rem;
    font-weight: 400;
    line-height: 1.2;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dropdown-trigger svg {
    display: block;
    width: 14px;
    height: 14px;
    flex: 0 0 14px;
    color: #04151f;
}

.dropdown-menu {
    position: absolute;
    top: calc(100% + 10px);
    left: 0;
    width: 100%;
    z-index: 20;
    max-height: 320px;
    overflow-y: auto;
    padding: 8px;
    border: 1px solid rgba(4, 21, 31, 0.12);
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 18px 34px rgba(15, 23, 42, 0.12);
    backdrop-filter: blur(16px);
}

.dropdown-menu-wide {
    width: 100%;
}

.dropdown-search-wrap {
    padding: 2px 0 8px;
}

.dropdown-search-input {
    width: 100%;
    height: 40px;
    padding: 0 12px;
    border: 1px solid rgba(4, 21, 31, 0.12);
    border-radius: 8px;
    background: #fff;
    color: #04151f;
    font-size: 0.9rem;
    font-weight: 400;
    line-height: 1.2;
    box-sizing: border-box;
}

.dropdown-search-input::placeholder {
    color: rgba(4, 21, 31, 0.46);
}

.dropdown-option {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 40px;
    padding: 0 8px;
    border-radius: 8px;
    color: #04151f;
    font-size: 0.94rem;
    font-weight: 400;
    line-height: 1.4;
}

.dropdown-option span {
    color: #04151f;
    font-size: 0.94rem;
    font-weight: 400;
    line-height: 1.4;
}

.dropdown-option:hover {
    background: rgba(4, 21, 31, 0.04);
}

.dropdown-option input {
    width: 16px;
    height: 16px;
}

.country-port-groups {
    display: grid;
    gap: 12px;
}

.ports-menu-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 2px 8px 0;
}

.ports-menu-action,
.country-port-group-title {
    border: 0;
    background: transparent;
    color: #04151f;
    cursor: pointer;
}

.ports-menu-action {
    font-size: 0.88rem;
    font-weight: 600;
    line-height: 1.3;
}

.country-port-group {
    display: grid;
    gap: 8px;
}

.country-port-group-head {
    padding: 2px 8px 0;
}

.country-port-group-title {
    padding: 0;
    color: rgba(4, 21, 31, 0.82);
    font-size: 0.94rem;
    font-weight: 500;
    line-height: 1.4;
}

.port-option-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.port-option {
    display: grid;
    grid-template-columns: 18px minmax(0, 1fr) auto;
    gap: 8px;
    align-items: center;
    min-height: 40px;
    padding: 4px 8px;
    border-radius: 8px;
}

.port-option:hover {
    background: rgba(4, 21, 31, 0.04);
}

.port-option input {
    width: 16px;
    height: 16px;
    margin-top: 2px;
}

.port-option-name {
    color: #04151f;
    font-size: 0.94rem;
    font-weight: 400;
    line-height: 1.4;
    min-width: 0;
}

.port-option-code {
    color: rgba(4, 21, 31, 0.62);
    font-size: 0.82rem;
    font-weight: 400;
    line-height: 1.4;
    white-space: nowrap;
    text-align: right;
}

.selector-empty {
    margin: 0;
    padding: 4px 2px;
    color: rgba(4, 21, 31, 0.68);
    font-size: 0.9rem;
    line-height: 1.6;
}

@media (max-width: 1200px) {
    .form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 860px) {
    .section-heading {
        flex-direction: column;
        align-items: stretch;
    }

    .general-head-actions,
    .general-template-actions {
        align-items: stretch;
    }

    .import-template-copy {
        max-width: none;
        text-align: left;
    }

    .unified-head-control {
        width: 100%;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .field-span-2 {
        grid-column: span 1;
    }
}
</style>
