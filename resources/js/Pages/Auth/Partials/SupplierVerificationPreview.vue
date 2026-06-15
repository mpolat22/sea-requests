<script setup>
import { computed } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    ui: { type: Object, required: true },
    servicePortGroups: { type: Array, required: true },
    newDocuments: { type: Object, required: true },
    existingDocuments: { type: Object, required: true },
    singleMedia: { type: Object, required: true },
    newSingles: { type: Object, required: true },
    goToField: { type: Function, required: true },
});

const copy = computed(() => ({
    eyebrow: 'Application Status',
    title: 'Readiness Check',
    subtitle: 'See what is completed and what is still missing before you submit.',
    completed: 'Completed Items',
    missing: 'Missing Items',
    noCompleted: 'No completed items yet.',
    noMissing: 'No critical missing items right now.',
    overview: 'Company Overview',
    officialDocuments: 'Official Documents',
}));

const hasLogo = computed(() => Boolean(props.newSingles.company_logo || props.singleMedia.company_logo));

const hasOfficialDocument = computed(() => (
    (props.existingDocuments.company_registration_documents?.length ?? 0) + (props.newDocuments.company_registration_documents?.length ?? 0) > 0
    || (props.existingDocuments.tax_certificate_documents?.length ?? 0) + (props.newDocuments.tax_certificate_documents?.length ?? 0) > 0
    || (props.existingDocuments.service_authorization_documents?.length ?? 0) + (props.newDocuments.service_authorization_documents?.length ?? 0) > 0
));

const checks = computed(() => {
    const countryPortCoverageOk = props.servicePortGroups.length > 0
        && props.servicePortGroups.every((group) => String(group.country_code ?? '').trim() !== '' && (group.port_ids ?? []).length > 0);

    const items = [
        { key: 'company_name', label: props.ui.businessName.replace(' *', ''), done: String(props.form.company_name ?? '').trim().length > 0 },
        { key: 'service_category_ids', label: props.ui.primaryCategory.replace(' *', ''), done: Array.isArray(props.form.service_category_ids) && props.form.service_category_ids.length > 0 },
        { key: 'service_coverage', label: props.ui.serviceCoverageHeading, done: countryPortCoverageOk },
        { key: 'country', label: props.ui.country.replace(' *', ''), done: String(props.form.country ?? '').trim().length > 0 },
        { key: 'city', label: props.ui.city.replace(' *', ''), done: String(props.form.company_city ?? '').trim().length > 0 },
        { key: 'address', label: props.ui.fullAddress.replace(' *', ''), done: String(props.form.company_address_line ?? '').trim().length > 0 },
        { key: 'phone', label: props.ui.mobilePhone.replace(' *', ''), done: String(props.form.phone_local_number ?? '').trim().length >= 6 },
        { key: 'contact_email', label: props.ui.email.replace(' *', ''), done: String(props.form.contact_email ?? '').trim().length > 0 },
        { key: 'overview', label: copy.value.overview, done: String(props.form.company_overview ?? '').trim().length >= 200 },
        { key: 'registration_number', label: props.ui.registrationNumber.replace(' *', ''), done: String(props.form.registration_number ?? '').trim().length > 0 },
        { key: 'logo', label: props.ui.logo.replace(' *', ''), done: hasLogo.value },
        { key: 'official_documents', label: copy.value.officialDocuments, done: hasOfficialDocument.value },
    ];

    const optionalCompleted = [];

    if (Array.isArray(props.form.service_brand_ids) && props.form.service_brand_ids.length > 0) {
        optionalCompleted.push({
            key: 'service_brand_ids',
            label: props.ui.brands,
            done: true,
        });
    }

    return {
        completed: items.filter((item) => item.done).concat(optionalCompleted),
        missing: items.filter((item) => !item.done),
    };
});
</script>

<template>
    <aside class="status-card">
        <div class="status-head">
            <p class="status-eyebrow">{{ copy.eyebrow }}</p>
            <h2 class="status-title">{{ copy.title }}</h2>
            <p class="status-copy">{{ copy.subtitle }}</p>
        </div>

        <section class="status-section">
            <div class="status-section-head">
                <h3>{{ copy.missing }}</h3>
                <span class="status-count is-danger">{{ checks.missing.length }}</span>
            </div>
            <div v-if="checks.missing.length" class="status-list">
                <button v-for="item in checks.missing" :key="item.key" type="button" class="status-item is-pending" @click="goToField(item.key)">
                    <span class="status-item-icon">!</span>
                    <span>{{ item.label }}</span>
                </button>
            </div>
            <p v-else class="status-line muted">{{ copy.noMissing }}</p>
        </section>

        <section class="status-section">
            <div class="status-section-head">
                <h3>{{ copy.completed }}</h3>
                <span class="status-count">{{ checks.completed.length }}</span>
            </div>
            <div v-if="checks.completed.length" class="status-list">
                <button v-for="item in checks.completed" :key="item.key" type="button" class="status-item is-ready" @click="goToField(item.key)">
                    <span class="status-item-icon">✓</span>
                    <span>{{ item.label }}</span>
                </button>
            </div>
            <p v-else class="status-line muted">{{ copy.noCompleted }}</p>
        </section>
    </aside>
</template>

<style scoped>
.status-card{display:grid;gap:18px;padding:24px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(247,241,232,.98));box-shadow:0 24px 48px rgba(15,23,42,.08)}
.status-head,.status-section,.status-list{display:grid;gap:10px}
.status-eyebrow{margin:0;color:#365cff;font-size:.82rem;font-weight:600;letter-spacing:.22em;text-transform:uppercase}
.status-title{margin:0;color:var(--color-ink);font-size:clamp(1.5rem,2vw,2rem);font-weight:620;line-height:1}
.status-copy,.status-line{margin:0;color:rgba(4,21,31,.66);line-height:1.6}
.status-count{color:rgba(4,21,31,.56);font-size:.82rem;font-weight:500}
.status-section{padding:16px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:rgba(255,255,255,.88)}
.status-section-head{display:flex;align-items:center;justify-content:space-between;gap:12px}
.status-section-head h3{margin:0;color:#020617;font-size:1rem;font-weight:600;line-height:1.5}
.status-count.is-danger{color:#b42318}
.status-list{gap:8px}
.status-item{display:flex;align-items:center;gap:10px;width:100%;padding:10px 12px;border:0;border-radius: 10px;font-size:.92rem;font-weight:460;text-align:left;cursor:pointer;transition:transform .16s ease, box-shadow .16s ease}
.status-item.is-ready{background:rgba(15,118,110,.08);color:var(--color-ink)}
.status-item.is-pending{background:rgba(217,45,32,.08);color:var(--color-ink)}
.status-item:hover{transform:translateY(-1px);box-shadow:0 10px 22px rgba(15,23,42,.08)}
.status-item-icon{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:999px;font-size:.82rem;font-weight:650;flex:0 0 22px}
.status-item.is-ready .status-item-icon{background:rgba(15,118,110,.16);color:var(--color-ocean)}
.status-item.is-pending .status-item-icon{background:rgba(217,45,32,.16);color:#b42318}
.muted{color:rgba(4,21,31,.54)}
</style>
