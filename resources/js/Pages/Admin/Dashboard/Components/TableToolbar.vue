<script setup>
const props = defineProps({
    searchValue: { type: String, default: '' },
    sortValue: { type: String, default: '' },
    searchPlaceholder: { type: String, required: true },
    sortLabel: { type: String, required: true },
    options: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:searchValue', 'update:sortValue']);
</script>

<template>
    <div class="table-toolbar">
        <label class="toolbar-search">
            <input
                :value="searchValue"
                type="search"
                :placeholder="searchPlaceholder"
                @input="emit('update:searchValue', $event.target.value)"
            >
        </label>

        <label class="toolbar-sort">
            <span>{{ sortLabel }}</span>
            <select :value="sortValue" @change="emit('update:sortValue', $event.target.value)">
                <option v-for="option in options" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
        </label>
    </div>
</template>

<style scoped>
.table-toolbar{display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;margin-bottom:16px}
.toolbar-search,.toolbar-sort{display:flex;align-items:center;gap:10px}
.toolbar-search{flex:1 1 320px}
.toolbar-search input,.toolbar-sort select{width:100%;min-height:44px;padding:0 14px;border:1px solid rgba(4,21,31,.12);border-radius: 10px;background:#fff;color:#0f172a;font-size:.9rem;font-weight:500}
.toolbar-sort{flex:0 0 auto}
.toolbar-sort span{color:#64748b;font-size:.82rem;font-weight:600}
@media (max-width: 720px){.table-toolbar{flex-direction:column}.toolbar-search,.toolbar-sort{width:100%}.toolbar-sort{align-items:flex-start;flex-direction:column}}
</style>

