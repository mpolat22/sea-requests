<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    user: { type: Object, default: null },
    copy: { type: Object, required: true },
    items: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const listDiffKeys = ['service_category_ids', 'service_brand_ids', 'service_country_codes', 'service_ports_by_country', 'company_registration_documents'];

const normalizeList = (value) => String(value ?? '')
    .split(/\s*\|\s*|\s*,\s*|\n+/)
    .map((item) => item.trim())
    .filter(Boolean);

const processedItems = computed(() => props.items.map((item) => {
    if (!listDiffKeys.includes(item.key)) {
        return {
            ...item,
            mode: 'text',
        };
    }

    const fromItems = normalizeList(item.from);
    const toItems = normalizeList(item.to);

    return {
        ...item,
        mode: 'list',
        fromItems,
        toItems,
    };
}));

const itemClass = (item, source) => {
    const compareSet = new Set(source === 'from' ? item.toItems : item.fromItems);

    return (entry) => ({
        'is-added': source === 'to' && !compareSet.has(entry),
        'is-removed': source === 'from' && !compareSet.has(entry),
    });
};
</script>

<template>
    <div v-if="show && user" class="admin-modal-backdrop" @click="emit('close')">
        <div class="admin-modal diff-modal" @click.stop>
            <button type="button" class="admin-modal-close" @click="emit('close')">&times;</button>

            <header class="modal-header">
                <p class="directory-eyebrow">{{ copy.updateRequest }}</p>
                <h2 class="directory-section-title">{{ user.company_name || user.name }}</h2>
                <p class="admin-modal-copy">{{ copy.updateDiffIntro }}</p>
            </header>

            <div class="modal-body">
                <article v-for="item in processedItems" :key="item.key" class="diff-card">
                    <div class="diff-card-head">
                        <h3>{{ item.label }}</h3>
                    </div>

                    <div class="diff-columns">
                        <section class="diff-column">
                            <span class="diff-label">{{ copy.previousValue }}</span>
                            <pre v-if="item.mode === 'text'" class="diff-value">{{ item.from }}</pre>
                            <ul v-else class="diff-list">
                                <li
                                    v-for="entry in item.fromItems"
                                    :key="`from-${item.key}-${entry}`"
                                    class="diff-list-item"
                                    :class="itemClass(item, 'from')(entry)"
                                >
                                    {{ entry }}
                                </li>
                            </ul>
                        </section>

                        <section class="diff-column is-next">
                            <span class="diff-label">{{ copy.newValue }}</span>
                            <pre v-if="item.mode === 'text'" class="diff-value">{{ item.to }}</pre>
                            <ul v-else class="diff-list">
                                <li
                                    v-for="entry in item.toItems"
                                    :key="`to-${item.key}-${entry}`"
                                    class="diff-list-item"
                                    :class="itemClass(item, 'to')(entry)"
                                >
                                    {{ entry }}
                                </li>
                            </ul>
                        </section>
                    </div>
                </article>
            </div>
        </div>
    </div>
</template>

<style scoped>
.admin-modal-backdrop{position:fixed;inset:0;z-index:1500;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(4,21,31,.58);backdrop-filter:blur(10px)}
.admin-modal{position:relative;width:min(980px,100%);max-height:min(90vh,920px);display:grid;grid-template-rows:auto minmax(0,1fr);border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff;box-shadow:0 30px 60px rgba(15,23,42,.16);overflow:hidden}
.diff-modal{background:linear-gradient(180deg,#ffffff 0%,#fbfdff 100%)}
.admin-modal-close{position:absolute;top:18px;right:18px;display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff;color:#0f172a;font-size:1.5rem;line-height:1;z-index:2;box-shadow:0 10px 24px rgba(15,23,42,.08)}
.modal-header{padding:28px 28px 18px;border-bottom:1px solid rgba(4,21,31,.06)}
.admin-modal-copy{margin:12px 0 0;color:#64748b;font-size:.95rem;line-height:1.7;max-width:72ch}
.modal-body{display:grid;gap:14px;padding:22px 28px 28px;overflow:auto}
.diff-card{display:grid;gap:14px;padding:18px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff}
.diff-card-head h3{margin:0;color:#020617;font-size:1rem;font-weight:600;line-height:1.5}
.diff-columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.diff-column{display:grid;gap:8px;padding:14px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#f8fafc}
.diff-column.is-next{background:#f8fbff;border-color:rgba(37,99,235,.12)}
.diff-label{color:#64748b;font-size:.78rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.diff-value{margin:0;color:#0f172a;font:500 .92rem/1.65 "Segoe UI",system-ui,sans-serif;white-space:pre-wrap;word-break:break-word}
.diff-list{display:grid;gap:8px;margin:0;padding:0;list-style:none}
.diff-list-item{padding:10px 12px;border-radius: 10px;background:#fff;color:#0f172a;font-size:.9rem;font-weight:500;line-height:1.55;border:1px solid rgba(4,21,31,.06)}
.diff-list-item.is-added{border-color:rgba(11,122,82,.18);background:#f1fcf6;color:#0b7a52}
.diff-list-item.is-removed{border-color:rgba(194,65,12,.18);background:#fff7ed;color:#c2410c}
@media (max-width: 720px){.admin-modal{width:min(100%,100%);max-height:calc(100vh - 20px)}.modal-header{padding:24px 20px 16px}.modal-body{padding:18px 20px 20px}.diff-columns{grid-template-columns:1fr}}
</style>

