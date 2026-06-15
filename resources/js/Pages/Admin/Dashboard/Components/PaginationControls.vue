<script setup>
const props = defineProps({
    page: { type: Number, required: true },
    totalPages: { type: Number, required: true },
    label: { type: String, required: true },
});

const emit = defineEmits(['update:page']);

const goTo = (page) => {
    if (page < 1 || page > props.totalPages || page === props.page) {
        return;
    }

    emit('update:page', page);
};
</script>

<template>
    <div v-if="totalPages > 1" class="pagination-shell">
        <p class="pagination-label">{{ label }}</p>
        <div class="pagination-actions">
            <button type="button" class="pagination-button" :disabled="page === 1" @click="goTo(page - 1)">
                &lsaquo;
            </button>
            <span class="pagination-state">{{ page }} / {{ totalPages }}</span>
            <button type="button" class="pagination-button" :disabled="page === totalPages" @click="goTo(page + 1)">
                &rsaquo;
            </button>
        </div>
    </div>
</template>

<style scoped>
.pagination-shell{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:16px}
.pagination-label{margin:0;color:#64748b;font-size:.82rem;font-weight:500}
.pagination-actions{display:flex;align-items:center;gap:10px}
.pagination-button{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid rgba(4,21,31,.1);border-radius: 10px;background:#fff;color:#0f172a;font-size:1rem;font-weight:700}
.pagination-button:disabled{opacity:.45;cursor:not-allowed}
.pagination-state{color:#0f172a;font-size:.85rem;font-weight:600}
@media (max-width: 720px){.pagination-shell{flex-direction:column;align-items:flex-start}}
</style>

