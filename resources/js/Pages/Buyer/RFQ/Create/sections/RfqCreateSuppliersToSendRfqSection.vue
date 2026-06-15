<script setup>
import { computed } from 'vue';

const props = defineProps({
    isGeneralOnlyEdit: {
        type: Boolean,
        default: false,
    },
    supplierTarget: {
        type: Object,
        default: null,
    },
    supplierSelectionMode: {
        type: String,
        default: 'manual',
    },
    supplierSuggestionsApplied: {
        type: Boolean,
        default: false,
    },
    hasSupplierManualFilters: {
        type: Boolean,
        default: false,
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
    selectedSupplierCategoryIds: {
        type: Array,
        default: () => [],
    },
    selectedSupplierCategories: {
        type: Array,
        default: () => [],
    },
    selectedSupplierCategoriesLabel: {
        type: String,
        required: true,
    },
    selectedSupplierSubcategories: {
        type: Array,
        default: () => [],
    },
    selectedSupplierSubcategoriesLabel: {
        type: String,
        required: true,
    },
    selectedSupplierBrandIds: {
        type: Array,
        default: () => [],
    },
    selectedSupplierBrandsLabel: {
        type: String,
        required: true,
    },
    supplierCategoryMenuOpen: {
        type: Boolean,
        default: false,
    },
    supplierSubcategoryMenuOpen: {
        type: Boolean,
        default: false,
    },
    supplierBrandMenuOpen: {
        type: Boolean,
        default: false,
    },
    supplierCategorySearch: {
        type: String,
        default: '',
    },
    supplierSubcategorySearch: {
        type: String,
        default: '',
    },
    supplierBrandSearch: {
        type: String,
        default: '',
    },
    filteredSupplierCategoryOptions: {
        type: Array,
        default: () => [],
    },
    filteredSupplierSubcategoryOptions: {
        type: Array,
        default: () => [],
    },
    filteredSupplierSubcategoryGroups: {
        type: Array,
        default: () => [],
    },
    filteredSupplierBrandOptions: {
        type: Array,
        default: () => [],
    },
    supplierSubcategoryOptionsLoading: {
        type: Boolean,
        default: false,
    },
    supplierSubcategoryOptionsError: {
        type: String,
        default: '',
    },
    supplierBrandOptionsLoading: {
        type: Boolean,
        default: false,
    },
    supplierBrandOptionsError: {
        type: String,
        default: '',
    },
    canRequestSupplierSuggestions: {
        type: Boolean,
        default: false,
    },
    supplierSuggestionsLoading: {
        type: Boolean,
        default: false,
    },
    supplierSuggestionsError: {
        type: String,
        default: '',
    },
    supplierSuggestions: {
        type: Object,
        default: null,
    },
    supplierSuggestionsOpen: {
        type: Boolean,
        default: false,
    },
    supplierSuggestionsHasFilters: {
        type: Boolean,
        default: false,
    },
    supplierSuggestionScopeWarning: {
        type: String,
        default: '',
    },
    supplierSuggestionPreviewRows: {
        type: Array,
        default: () => [],
    },
    supplierMatchesCount: {
        type: Number,
        default: 0,
    },
    supplierMatchesLoaded: {
        type: Boolean,
        default: false,
    },
    supplierMatchesLoading: {
        type: Boolean,
        default: false,
    },
    supplierMatchesError: {
        type: String,
        default: '',
    },
    hasSupplierRequestScope: {
        type: Boolean,
        default: false,
    },
    hasSupplierRecipientError: {
        type: Boolean,
        default: false,
    },
    supplierRecipientError: {
        type: String,
        default: '',
    },
    setSupplierSelectionMode: {
        type: Function,
        required: true,
    },
    requestSupplierSuggestions: {
        type: Function,
        required: true,
    },
    applySupplierSuggestions: {
        type: Function,
        required: true,
    },
    closeSupplierSuggestions: {
        type: Function,
        required: true,
    },
    clearSupplierFilters: {
        type: Function,
        required: true,
    },
    toggleSupplierCategoryMenu: {
        type: Function,
        required: true,
    },
    toggleSupplierSubcategoryMenu: {
        type: Function,
        required: true,
    },
    toggleSupplierBrandMenu: {
        type: Function,
        required: true,
    },
    isSupplierCategorySelected: {
        type: Function,
        required: true,
    },
    toggleSupplierCategory: {
        type: Function,
        required: true,
    },
    selectAllSupplierSubcategories: {
        type: Function,
        required: true,
    },
    clearAllSupplierSubcategories: {
        type: Function,
        required: true,
    },
    isSupplierSubcategorySelected: {
        type: Function,
        required: true,
    },
    toggleSupplierSubcategory: {
        type: Function,
        required: true,
    },
    isSupplierBrandSelected: {
        type: Function,
        required: true,
    },
    toggleSupplierBrand: {
        type: Function,
        required: true,
    },
    setSupplierCategoryMenuRef: {
        type: Function,
        required: true,
    },
    setSupplierCategoryMenuListRef: {
        type: Function,
        required: true,
    },
    setSupplierSubcategoryMenuRef: {
        type: Function,
        required: true,
    },
    setSupplierSubcategoryMenuListRef: {
        type: Function,
        required: true,
    },
    setSupplierBrandMenuRef: {
        type: Function,
        required: true,
    },
    setSupplierBrandMenuListRef: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits([
    'update:supplierCategorySearch',
    'update:supplierSubcategorySearch',
    'update:supplierBrandSearch',
]);

const supplierCategorySearchModel = computed({
    get: () => props.supplierCategorySearch,
    set: (value) => emit('update:supplierCategorySearch', value),
});

const supplierSubcategorySearchModel = computed({
    get: () => props.supplierSubcategorySearch,
    set: (value) => emit('update:supplierSubcategorySearch', value),
});

const supplierBrandSearchModel = computed({
    get: () => props.supplierBrandSearch,
    set: (value) => emit('update:supplierBrandSearch', value),
});

const isSupplierTargetedRequest = computed(() => Boolean(props.supplierTarget?.company_name));
</script>

<template>
    <section class="surface-card form-section combined-form-section">
        <div class="subsection-surface">
            <div class="section-heading supplier-section-heading">
                <div class="supplier-title-wrap">
                    <h2 class="directory-section-title supplier-section-title">Suppliers to Send RFQ</h2>
                </div>
                <div class="items-head-actions supplier-head-actions">
                    <div v-if="!isSupplierTargetedRequest" class="request-mode-toggle supplier-mode-toggle" role="tablist" aria-label="Supplier selection mode">
                        <button
                            type="button"
                            class="request-mode-button"
                            :class="{ active: props.supplierSelectionMode === 'manual' }"
                            :disabled="props.isGeneralOnlyEdit"
                            @click="props.setSupplierSelectionMode('manual')"
                        >
                            Manual
                        </button>
                        <button
                            type="button"
                            class="request-mode-button"
                            :class="{ active: props.supplierSelectionMode === 'suggested' }"
                            :disabled="props.isGeneralOnlyEdit"
                            @click="props.setSupplierSelectionMode('suggested')"
                        >
                            Suggested
                        </button>
                    </div>
                    <p class="import-helper-copy supplier-helper-copy">
                        {{ isSupplierTargetedRequest ? (props.supplierTarget?.scope_note || props.supplierTarget?.message) : 'The request country and ports selected in General Information flow here automatically. Use category, subcategory, and brand only to narrow the supplier match.' }}
                    </p>
                </div>
            </div>

            <fieldset class="section-lock-fieldset" :disabled="props.isGeneralOnlyEdit">
                <div class="supplier-filter-panel">
                    <p v-if="!isSupplierTargetedRequest && props.supplierSuggestionsApplied" class="supplier-suggestion-applied">
                        Suggested filters applied.
                    </p>

                    <div
                        v-if="!isSupplierTargetedRequest && props.supplierMatchesLoading"
                        class="supplier-match-feedback supplier-match-feedback-neutral"
                    >
                        Checking approved supplier coverage for the current request scope...
                    </div>

                    <div
                        v-else-if="!isSupplierTargetedRequest && props.supplierMatchesError"
                        class="supplier-match-feedback supplier-match-feedback-error"
                    >
                        {{ props.supplierMatchesError }}
                    </div>

                    <div
                        v-else-if="!isSupplierTargetedRequest && props.hasSupplierRequestScope && props.supplierMatchesLoaded && props.supplierMatchesCount === 0"
                        class="supplier-match-feedback supplier-match-feedback-warning"
                    >
                        No approved suppliers match the current RFQ scope yet. You can still submit this RFQ publicly so suppliers can discover it and respond later.
                    </div>

                    <div
                        v-else-if="!isSupplierTargetedRequest && props.hasSupplierRequestScope && props.supplierMatchesLoaded && props.supplierMatchesCount > 0"
                        class="supplier-match-feedback supplier-match-feedback-success"
                    >
                        {{ props.supplierMatchesCount }} approved {{ props.supplierMatchesCount === 1 ? 'supplier matches' : 'suppliers match' }} the current RFQ scope.
                    </div>

                    <div v-if="isSupplierTargetedRequest" class="supplier-target-panel">
                        <div class="supplier-target-summary">
                            <span class="supplier-target-label">Selected Supplier</span>
                            <strong class="supplier-target-name">{{ props.supplierTarget.company_name }}</strong>
                            <p v-if="props.supplierTarget?.message" class="supplier-target-message">{{ props.supplierTarget.message }}</p>
                        </div>

                        <div class="form-grid supplier-filter-grid supplier-target-grid">
                            <div class="field selection-field">
                                <span>Supplier</span>
                                <div class="choice-control supplier-scope-summary">
                                    <span>{{ props.supplierTarget.company_name }}</span>
                                </div>
                            </div>

                            <div class="field selection-field">
                                <span>Request Country</span>
                                <div class="choice-control supplier-scope-summary" :class="{ 'is-placeholder': !props.selectedCountriesLabel || props.selectedCountriesLabel === 'Select Countries' }">
                                    <span>{{ props.selectedCountriesLabel }}</span>
                                </div>
                            </div>

                            <div class="field selection-field">
                                <span>Request Ports</span>
                                <div class="choice-control supplier-scope-summary" :class="{ 'is-placeholder': !props.selectedPortsCount }">
                                    <span>{{ props.selectedPortsLabel }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="supplier-manual-panel">
                        <div class="supplier-manual-actions">
                            <button
                                v-if="props.hasSupplierManualFilters"
                                type="button"
                                class="secondary-button compact-button"
                                @click="props.clearSupplierFilters"
                            >
                                Clear Filters
                            </button>
                        </div>

                        <div class="form-grid supplier-filter-grid">
                            <div class="field selection-field">
                                <span>Request Country</span>
                                <div class="choice-control supplier-scope-summary">
                                    <span>{{ props.selectedCountriesLabel }}</span>
                                </div>
                            </div>

                            <div class="field selection-field">
                                <span>Request Ports</span>
                                <div class="choice-control supplier-scope-summary" :class="{ 'is-placeholder': !props.selectedPortsCount }">
                                    <span>{{ props.selectedPortsLabel }}</span>
                                </div>
                            </div>

                            <div :ref="props.setSupplierCategoryMenuRef" class="field selection-field">
                                <span>Category</span>
                                <div class="dropdown-shell">
                                    <button
                                        type="button"
                                        class="choice-control dropdown-trigger"
                                        :class="{ 'is-placeholder': !props.selectedSupplierCategories.length }"
                                        @click="props.toggleSupplierCategoryMenu"
                                    >
                                        <span>{{ props.selectedSupplierCategoriesLabel }}</span>
                                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                            <path d="M5 7.5 10 12.5l5-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>

                                    <div v-if="props.supplierCategoryMenuOpen" :ref="props.setSupplierCategoryMenuListRef" class="dropdown-menu dropdown-menu-wide" tabindex="-1">
                                        <div class="dropdown-search-wrap">
                                            <input
                                                v-model="supplierCategorySearchModel"
                                                type="text"
                                                class="dropdown-search-input"
                                                placeholder="Search..."
                                            />
                                        </div>
                                        <label
                                            v-for="category in props.filteredSupplierCategoryOptions"
                                            :key="`supplier-category-${category.id}`"
                                            class="dropdown-option"
                                        >
                                            <input
                                                type="checkbox"
                                                :checked="props.isSupplierCategorySelected(category.id)"
                                                @change="props.toggleSupplierCategory(category.id)"
                                            />
                                            <span>{{ category.name }}</span>
                                        </label>
                                        <p v-if="!props.filteredSupplierCategoryOptions.length" class="selector-empty">No categories found.</p>
                                    </div>
                                </div>
                            </div>

                            <div :ref="props.setSupplierSubcategoryMenuRef" class="field selection-field">
                                <span>Subcategory</span>
                                <div class="dropdown-shell">
                                    <button
                                        type="button"
                                        class="choice-control dropdown-trigger"
                                        :class="{ 'is-placeholder': !props.selectedSupplierSubcategories.length }"
                                        :disabled="!props.selectedSupplierCategoryIds.length"
                                        @click="props.toggleSupplierSubcategoryMenu"
                                    >
                                        <span>{{ props.selectedSupplierSubcategoriesLabel }}</span>
                                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                            <path d="M5 7.5 10 12.5l5-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>

                                    <div v-if="props.supplierSubcategoryMenuOpen" :ref="props.setSupplierSubcategoryMenuListRef" class="dropdown-menu dropdown-menu-wide" tabindex="-1">
                                        <div class="dropdown-search-wrap">
                                            <input
                                                v-model="supplierSubcategorySearchModel"
                                                type="text"
                                                class="dropdown-search-input"
                                                placeholder="Search..."
                                                :disabled="props.supplierSubcategoryOptionsLoading"
                                            />
                                        </div>
                                        <div v-if="props.filteredSupplierSubcategoryOptions.length" class="ports-menu-actions">
                                            <button type="button" class="ports-menu-action" @click="props.selectAllSupplierSubcategories">
                                                Select all
                                            </button>
                                            <button type="button" class="ports-menu-action" @click="props.clearAllSupplierSubcategories">
                                                Clear all
                                            </button>
                                        </div>
                                        <p v-if="props.supplierSubcategoryOptionsLoading" class="selector-empty">
                                            Loading subcategories...
                                        </p>
                                        <p v-else-if="props.supplierSubcategoryOptionsError" class="selector-empty">
                                            {{ props.supplierSubcategoryOptionsError }}
                                        </p>
                                        <div v-else-if="props.filteredSupplierSubcategoryGroups.length" class="country-port-groups">
                                            <section
                                                v-for="group in props.filteredSupplierSubcategoryGroups"
                                                :key="group.slug"
                                                class="country-port-group"
                                            >
                                                <div class="country-port-group-head">
                                                    <span class="country-port-group-title static-group-title">
                                                        {{ group.name }}
                                                    </span>
                                                </div>
                                                <div class="port-option-grid">
                                                    <label
                                                        v-for="subcategory in group.subcategories"
                                                        :key="`supplier-subcategory-${group.slug}-${subcategory.id}`"
                                                        class="port-option supplier-subcategory-option"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            :checked="props.isSupplierSubcategorySelected(subcategory.id)"
                                                            @change="props.toggleSupplierSubcategory(subcategory.id)"
                                                        />
                                                        <span class="port-option-name supplier-subcategory-option-name">{{ subcategory.name }}</span>
                                                    </label>
                                                </div>
                                            </section>
                                        </div>
                                        <p v-if="!props.supplierSubcategoryOptionsLoading && !props.supplierSubcategoryOptionsError && !props.filteredSupplierSubcategoryOptions.length" class="selector-empty">No subcategories found for the selected categories.</p>
                                    </div>
                                </div>
                            </div>

                            <div :ref="props.setSupplierBrandMenuRef" class="field selection-field">
                                <span>Brands</span>
                                <div class="dropdown-shell">
                                    <button
                                        type="button"
                                        class="choice-control dropdown-trigger"
                                        :class="{ 'is-placeholder': !props.selectedSupplierBrandIds.length }"
                                        @click="props.toggleSupplierBrandMenu"
                                    >
                                        <span>{{ props.selectedSupplierBrandsLabel }}</span>
                                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                            <path d="M5 7.5 10 12.5l5-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>

                                    <div v-if="props.supplierBrandMenuOpen" :ref="props.setSupplierBrandMenuListRef" class="dropdown-menu dropdown-menu-wide" tabindex="-1">
                                        <div class="dropdown-search-wrap">
                                            <input
                                                v-model="supplierBrandSearchModel"
                                                type="text"
                                                class="dropdown-search-input"
                                                placeholder="Search..."
                                                :disabled="props.supplierBrandOptionsLoading"
                                            />
                                        </div>
                                        <p v-if="props.supplierBrandOptionsLoading" class="selector-empty">
                                            Loading brands...
                                        </p>
                                        <p v-else-if="props.supplierBrandOptionsError" class="selector-empty">
                                            {{ props.supplierBrandOptionsError }}
                                        </p>
                                        <template v-else>
                                            <label
                                                v-for="brand in props.filteredSupplierBrandOptions"
                                                :key="`supplier-brand-${brand.id}`"
                                                class="dropdown-option"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :checked="props.isSupplierBrandSelected(brand.id)"
                                                    @change="props.toggleSupplierBrand(brand.id)"
                                                />
                                                <span>{{ brand.name }}</span>
                                            </label>
                                        </template>
                                        <p v-if="!props.supplierBrandOptionsLoading && !props.supplierBrandOptionsError && !props.filteredSupplierBrandOptions.length" class="selector-empty">No brands found.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </fieldset>

            <p v-if="props.hasSupplierRecipientError" class="field-error supplier-selection-error">{{ props.supplierRecipientError }}</p>

            <div v-if="!isSupplierTargetedRequest && props.supplierSuggestionsOpen" class="import-preview-modal" @click.self="props.closeSupplierSuggestions">
                <div class="import-preview-dialog supplier-suggestion-dialog">
                    <div class="import-preview-card">
                        <div class="import-preview-head">
                            <div>
                                <h3 class="directory-section-title import-preview-main-title">Select Suggested Supplier Filters</h3>
                                <p class="import-preview-copy">
                                    {{ props.supplierSuggestions?.summary || props.supplierSuggestions?.empty_message || 'Review the detected brand, category, and subcategory filters item by item before applying them.' }}
                                </p>
                            </div>
                            <div class="import-preview-actions">
                                <button type="button" class="secondary-button compact-button" @click="props.closeSupplierSuggestions">
                                    Cancel
                                </button>
                                <button
                                    v-if="props.supplierSuggestionsHasFilters"
                                    type="button"
                                    class="primary-button compact-button"
                                    @click="props.applySupplierSuggestions"
                                >
                                    Apply Suggested Filters
                                </button>
                            </div>
                        </div>

                        <div v-if="props.supplierSuggestionScopeWarning" class="import-preview-surface supplier-suggestion-surface">
                            <p class="supplier-suggestion-scope-note">{{ props.supplierSuggestionScopeWarning }}</p>
                        </div>

                        <div class="import-preview-surface supplier-suggestion-surface">
                            <div v-if="props.supplierSuggestionsLoading" class="selector-empty">
                                Checking...
                            </div>
                            <p v-else-if="props.supplierSuggestionsError" class="import-error">{{ props.supplierSuggestionsError }}</p>
                            <p v-else-if="props.supplierSuggestions?.empty_message" class="selector-empty">
                                {{ props.supplierSuggestions.empty_message }}
                            </p>
                            <div v-else class="import-preview-scroll">
                                <table class="import-preview-grid-table supplier-suggestion-table">
                                    <colgroup>
                                        <col style="width: 280px">
                                        <col style="width: 220px">
                                        <col style="width: 220px">
                                        <col style="width: 260px">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>Detected item</th>
                                            <th>Detected brands</th>
                                            <th>Suggested categories</th>
                                            <th>Suggested subcategories</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, suggestionIndex) in props.supplierSuggestionPreviewRows" :key="`supplier-suggestion-row-${suggestionIndex}`">
                                            <td>
                                                <div class="supplier-suggestion-cell">
                                                    <span class="supplier-suggestion-cell-main">{{ row.source || '-' }}</span>
                                                    <small v-if="row.confidence" class="supplier-suggestion-cell-confidence">{{ row.confidence }}</small>
                                                </div>
                                            </td>
                                            <td>
                                                <div v-if="row.brands?.length" class="supplier-suggestion-cell">
                                                    <div v-for="(entry, entryIndex) in row.brands" :key="`suggested-brand-cell-${suggestionIndex}-${entryIndex}`" class="supplier-suggestion-cell-line">
                                                        <span class="supplier-suggestion-cell-main">{{ entry.label }}</span>
                                                        <small v-if="entry.reason" class="supplier-suggestion-cell-reason">{{ entry.reason }}</small>
                                                    </div>
                                                </div>
                                                <span v-else>-</span>
                                            </td>
                                            <td>
                                                <div v-if="row.categories?.length" class="supplier-suggestion-cell">
                                                    <div v-for="(entry, entryIndex) in row.categories" :key="`suggested-category-cell-${suggestionIndex}-${entryIndex}`" class="supplier-suggestion-cell-line">
                                                        <span class="supplier-suggestion-cell-main">{{ entry.label }}</span>
                                                        <small v-if="entry.reason" class="supplier-suggestion-cell-reason">{{ entry.reason }}</small>
                                                    </div>
                                                </div>
                                                <span v-else>-</span>
                                            </td>
                                            <td>
                                                <div v-if="row.subcategories?.length" class="supplier-suggestion-cell">
                                                    <div v-for="(entry, entryIndex) in row.subcategories" :key="`suggested-subcategory-cell-${suggestionIndex}-${entryIndex}`" class="supplier-suggestion-cell-line">
                                                        <span class="supplier-suggestion-cell-main">{{ entry.label }}</span>
                                                        <small v-if="entry.reason" class="supplier-suggestion-cell-reason">{{ entry.reason }}</small>
                                                    </div>
                                                </div>
                                                <span v-else>-</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.surface-card {
    padding: 28px;
    border: 1px solid rgba(4, 21, 31, 0.06);
    border-radius: 14px;
    background: #fff;
}

.subsection-surface {
    padding: 24px;
    border-radius: 10px;
    background: #f8fafb;
    min-width: 0;
}

.section-heading,
.items-head-actions,
.import-preview-actions,
.supplier-manual-actions {
    display: flex;
    gap: 16px;
}

.section-heading {
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 0;
}

.supplier-section-heading {
    padding-right: 0;
}

.supplier-title-wrap {
    padding-top: 4px;
}

.supplier-section-title {
    margin: 0;
    font-size: 1.42rem;
    line-height: 1.2;
}

.items-head-actions {
    flex-direction: column;
    align-items: stretch;
}

.supplier-head-actions {
    width: min(100%, 304px);
}

.request-mode-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px;
    border-radius: 999px;
    background: rgba(7, 112, 170, 0.08);
}

.supplier-mode-toggle {
    flex-shrink: 0;
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
    text-align: left;
}

.supplier-helper-copy {
    max-width: none;
    width: 100%;
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

.supplier-filter-panel {
    border-radius: 18px;
    background: #f8fafb;
    padding: 0 18px 18px;
}

.supplier-suggestion-applied {
    margin: 0 0 14px;
    color: #166534;
    font-size: 0.88rem;
    font-weight: 600;
    line-height: 1.5;
}

.supplier-match-feedback {
    margin: 0 0 14px;
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 0.9rem;
    line-height: 1.5;
}

.supplier-match-feedback-neutral {
    background: rgba(7, 112, 170, 0.08);
    color: #0b4f6c;
}

.supplier-match-feedback-success {
    background: rgba(21, 128, 61, 0.08);
    color: #166534;
}

.supplier-match-feedback-warning {
    background: rgba(180, 83, 9, 0.1);
    color: #9a3412;
}

.supplier-match-feedback-error {
    background: rgba(185, 28, 28, 0.08);
    color: #b91c1c;
}

.supplier-manual-panel {
    display: grid;
    gap: 14px;
}

.supplier-target-panel {
    display: grid;
    gap: 14px;
}

.supplier-target-summary {
    display: grid;
    gap: 4px;
}

.supplier-target-label {
    color: rgba(4, 21, 31, 0.56);
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.supplier-target-name {
    color: #04151f;
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.3;
}

.supplier-target-message {
    margin: 0;
    color: rgba(4, 21, 31, 0.64);
    font-size: 0.9rem;
    line-height: 1.5;
}

.supplier-manual-actions {
    justify-content: flex-end;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}

.supplier-filter-grid {
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 16px 18px;
}

.supplier-target-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.field {
    display: grid;
    gap: 5px;
    position: relative;
    padding-bottom: 18px;
    min-width: 0;
}

.field span {
    color: #04151f;
    font-size: 0.86rem;
    font-weight: 600;
}

.selection-field {
    align-content: start;
    min-width: 0;
}

.choice-control,
.dropdown-search-input {
    width: 100%;
    height: 48px;
    min-height: 48px;
    box-sizing: border-box;
    border-radius: 10px;
    background: #fff;
    color: #04151f;
    font-size: 0.93rem;
    font-weight: 400;
    line-height: 1.2;
}

.choice-control {
    border: 1px solid rgba(4, 21, 31, 0.12);
    padding: 0 44px 0 16px;
}

.supplier-scope-summary {
    display: flex;
    align-items: center;
    padding-right: 16px;
    background: #f8fafc;
    color: rgba(4, 21, 31, 0.78);
    cursor: default;
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
    text-align: left;
    min-width: 0;
}

.dropdown-trigger:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.dropdown-trigger.is-placeholder,
.supplier-scope-summary.is-placeholder {
    color: rgba(4, 21, 31, 0.72);
}

.dropdown-trigger span,
.supplier-scope-summary span {
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
    padding: 0 12px;
    border: 1px solid rgba(4, 21, 31, 0.12);
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

.dropdown-option:hover,
.port-option:hover {
    background: rgba(4, 21, 31, 0.04);
}

.dropdown-option input,
.port-option input {
    width: 16px;
    height: 16px;
}

.selector-empty {
    margin: 0;
    padding: 4px 2px;
    color: rgba(4, 21, 31, 0.68);
    font-size: 0.9rem;
    line-height: 1.6;
}

.ports-menu-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 2px 8px 0;
}

.ports-menu-action {
    border: 0;
    background: transparent;
    color: #04151f;
    cursor: pointer;
    font-size: 0.88rem;
    font-weight: 600;
    line-height: 1.3;
}

.country-port-groups {
    display: grid;
    gap: 12px;
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

.static-group-title {
    cursor: default;
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

.port-option-name {
    color: #04151f;
    font-size: 0.94rem;
    font-weight: 400;
    line-height: 1.4;
    min-width: 0;
}

.supplier-subcategory-option,
.supplier-subcategory-option-name {
    font-weight: 400 !important;
}

.field-error {
    color: #be123c;
    font-size: 0.8rem;
    font-weight: 600;
    line-height: 1.1;
}

.supplier-selection-error {
    display: block;
    margin-top: 12px;
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

.supplier-suggestion-dialog {
    width: min(1180px, calc(100vw - 56px));
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

.import-preview-main-title {
    margin: 0;
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

.supplier-suggestion-scope-note {
    margin: 0;
    color: #9a3412;
    font-size: 0.88rem;
    font-weight: 600;
    line-height: 1.55;
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

.supplier-suggestion-cell {
    display: grid;
    gap: 6px;
}

.supplier-suggestion-cell-line {
    display: grid;
    gap: 2px;
}

.supplier-suggestion-cell-main {
    color: #04151f;
    font-size: 0.88rem;
    font-weight: 600;
    line-height: 1.35;
}

.supplier-suggestion-cell-reason,
.supplier-suggestion-cell-confidence {
    color: rgba(4, 21, 31, 0.62);
    font-size: 0.78rem;
    line-height: 1.4;
}

.import-error {
    margin: 0;
    color: #be123c;
    font-size: 0.9rem;
    font-weight: 600;
}

@media (max-width: 960px) {
    .form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .port-option-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 720px) {
    .surface-card {
        padding: 24px;
    }

    .subsection-surface {
        padding: 20px;
    }

    .section-heading,
    .items-head-actions,
    .import-preview-head,
    .import-preview-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .supplier-head-actions,
    .supplier-helper-copy {
        width: 100%;
        max-width: none;
    }

    .import-preview-modal {
        padding: 16px;
    }

    .import-preview-dialog {
        width: 100%;
        max-height: calc(100vh - 32px);
    }
}
</style>
