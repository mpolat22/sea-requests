<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminDashboardShell from '../Dashboard/Shell.vue';
import PaginationControls from '../Dashboard/Components/PaginationControls.vue';
import OnboardingVerificationForm from './Partials/OnboardingVerificationForm.vue';

const props = defineProps({
    dashboard: { type: Object, required: true },
    activeTab: { type: String, default: 'onboarding' },
    records: { type: Object, required: true },
    summary: { type: Object, required: true },
    filters: { type: Object, required: true },
    urls: { type: Object, required: true },
    sellerVerificationOptions: { type: Object, required: true },
});

const activeModal = ref(null);
const activeRecordId = ref(null);
const isImportSubmitting = ref(false);
const search = ref(props.filters.search ?? '');
const audience = ref(props.filters.audience ?? 'seller');
const status = ref(props.filters.status ?? 'all');

const records = computed(() => props.records.data ?? []);
const meta = computed(() => ({
    current_page: props.records.current_page ?? 1,
    last_page: props.records.last_page ?? 1,
    from: props.records.from ?? 0,
    to: props.records.to ?? 0,
    total: props.records.total ?? records.value.length,
}));
const activeRecord = computed(() => records.value.find((record) => record.id === activeRecordId.value) ?? null);
const activeServicedPorts = computed(() => activeRecord.value?.parsed?.serviced_ports ?? []);

const editForm = useForm({
    audience: 'seller',
    company_name: '',
    email: '',
    contact_name: '',
    phone: '',
    website_url: '',
    country: '',
    city: '',
    postal_code: '',
    address: '',
    business_activity: '',
    company_overview: '',
});

const accountForm = useForm({});
const emailForm = useForm({});
const deleteForm = useForm({});
const importForm = useForm({
    audience: 'seller',
    file: null,
});
const importButtonLabel = computed(() => isImportSubmitting.value ? 'Creating Accounts & Queueing Emails...' : 'Create Accounts & Queue Emails');

const summaryCards = computed(() => [
    { label: 'Supplier Records', value: props.summary.seller_total ?? 0 },
    { label: 'Buyer Records', value: props.summary.buyer_total ?? 0 },
    { label: 'Accounts Created', value: props.summary.account_created ?? 0 },
    { label: 'Emails Queued', value: props.summary.email_queued ?? 0 },
    { label: 'Completion Emails Sent', value: props.summary.email_sent ?? 0 },
]);

const statusOptions = [
    { value: 'all', label: 'All statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'ready', label: 'Ready' },
    { value: 'account_created', label: 'Account created' },
    { value: 'email_queued', label: 'Email queued' },
    { value: 'email_sent', label: 'Email sent' },
];

const audienceOptions = [
    { value: 'seller', label: 'Suppliers' },
    { value: 'buyer', label: 'Buyers' },
];

let filterTimer = null;
watch([search, audience, status], () => {
    window.clearTimeout(filterTimer);
    filterTimer = window.setTimeout(() => {
        router.get(props.urls.index, {
            search: search.value,
            audience: audience.value,
            status: status.value,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 250);
});

function openManualModal() {
    activeModal.value = 'manual';
}

function openImportModal() {
    importForm.clearErrors();
    importForm.reset();
    importForm.audience = audience.value === 'buyer' ? 'buyer' : 'seller';
    activeModal.value = 'import';
}

function handleImportFile(event) {
    importForm.file = event.target.files?.[0] ?? null;
    importForm.clearErrors('file');
}

function submitImport() {
    if (isImportSubmitting.value) return;

    if (!importForm.file) {
        importForm.setError('file', 'Please select an Excel or CSV file before creating accounts.');
        return;
    }

    isImportSubmitting.value = true;

    importForm.post(props.urls.bulk_import_store, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            activeModal.value = null;
            importForm.reset();
        },
        onFinish: () => {
            isImportSubmitting.value = false;
        },
    });
}

function openEditModal(record) {
    activeRecordId.value = record.id;
    editForm.clearErrors();
    editForm.audience = record.audience ?? 'seller';
    editForm.company_name = record.parsed?.company_name ?? record.company_name ?? '';
    editForm.email = record.parsed?.email ?? record.email ?? '';
    editForm.contact_name = record.parsed?.contact_name ?? '';
    editForm.phone = record.parsed?.phone ?? '';
    editForm.website_url = record.parsed?.website_url ?? '';
    editForm.country = record.parsed?.country ?? '';
    editForm.city = record.parsed?.city ?? '';
    editForm.postal_code = record.parsed?.postal_code ?? '';
    editForm.address = record.parsed?.address ?? '';
    editForm.business_activity = record.parsed?.business_activity ?? '';
    editForm.company_overview = record.parsed?.company_overview ?? '';
    activeModal.value = 'edit';
}

function submitEdit() {
    if (!activeRecord.value) return;

    editForm.put(activeRecord.value.urls.update, {
        preserveScroll: true,
        onSuccess: () => {
            activeModal.value = null;
        },
    });
}

function createAccount(record) {
    accountForm.post(record.urls.create_account, { preserveScroll: true });
}

function sendCompletionEmail(record) {
    emailForm.post(record.urls.send_completion_email, { preserveScroll: true });
}

function openDeleteModal(record) {
    activeRecordId.value = record.id;
    activeModal.value = 'delete';
}

function submitDelete() {
    if (!activeRecord.value) return;

    deleteForm.delete(activeRecord.value.urls.delete, {
        preserveScroll: true,
        onSuccess: () => {
            activeModal.value = null;
        },
    });
}

function formatDate(value) {
    if (!value) return '-';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function statusLabel(value) {
    return statusOptions.find((option) => option.value === value)?.label ?? value ?? 'Draft';
}
</script>

<template>
    <Head title="Onboarding | Sea Requests" />

    <AdminDashboardShell :dashboard="dashboard" title="Admin Dashboard" active-tab="onboarding">
        <section class="onboarding-page">
            <div class="toolbar surface-panel">
                <div>
                    <p class="directory-eyebrow">Pre-registration</p>
                    <h2>Supplier & Buyer Onboarding</h2>
                    <p>
                        Create supplier or buyer onboarding profiles manually. Saving a profile now creates the platform account and sends the secure completion email automatically.
                    </p>
                </div>
                <div class="toolbar-actions">
                    <button class="secondary-action" type="button" @click="openImportModal">Import Excel / CSV</button>
                    <button class="primary-action" type="button" @click="openManualModal">Add Manual Profile</button>
                </div>
            </div>

            <section class="summary-grid">
                <article v-for="card in summaryCards" :key="card.label" class="summary-card surface-panel">
                    <span>{{ card.label }}</span>
                    <strong>{{ card.value }}</strong>
                </article>
            </section>

            <section class="table-panel surface-panel">
                <div class="table-toolbar">
                    <input v-model="search" type="search" placeholder="Search company or email" />
                    <select v-model="audience">
                        <option v-for="option in audienceOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <select v-model="status">
                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>

                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Company</th>
                                <th>Email</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Last Email</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="record in records" :key="record.id">
                                <td>{{ record.id }}</td>
                                <td>
                                    <strong>{{ record.company_name || '-' }}</strong>
                                    <span>{{ record.parsed?.country || '-' }}</span>
                                </td>
                                <td>{{ record.email }}</td>
                                <td>{{ record.audience === 'buyer' ? 'Buyer' : 'Supplier' }}</td>
                                <td>
                                    <span class="status-pill">{{ statusLabel(record.status) }}</span>
                                </td>
                                <td>{{ formatDate(record.last_sent_at) }}</td>
                                <td>
                                    <div class="actions">
                                        <button type="button" title="Edit" @click="openEditModal(record)">Edit</button>
                                        <button type="button" title="Create account" :disabled="accountForm.processing || record.user" @click="createAccount(record)">Create</button>
                                        <button type="button" title="Send completion email" :disabled="emailForm.processing || !record.user" @click="sendCompletionEmail(record)">Send</button>
                                        <button type="button" class="danger" title="Delete" @click="openDeleteModal(record)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="records.length === 0">
                                <td colspan="7" class="empty-state">No onboarding records matched the current filters.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <PaginationControls
                    v-if="meta.last_page > 1"
                    :current-page="meta.current_page"
                    :last-page="meta.last_page"
                    :from="meta.from"
                    :to="meta.to"
                    :total="meta.total"
                    :base-url="urls.index"
                    :query="{ search, audience, status }"
                />
            </section>
        </section>

        <div v-if="activeModal" class="modal-backdrop">
            <section v-if="activeModal === 'import'" class="modal-card">
                <header>
                    <h3>Import Company List</h3>
                    <p>Upload an Excel or CSV file with exactly two columns: Company Name and Email. Accounts are created and secure completion emails are sent automatically.</p>
                </header>
                <div class="import-format-box">
                    <strong>Required format</strong>
                    <code>Company Name | Email</code>
                    <span>Existing platform accounts are skipped safely. New rows are created first, then completion emails are queued one by one every 2 minutes; duplicate or invalid rows are reported in the import result.</span>
                </div>
                <div class="form-grid">
                    <label>
                        Account Type *
                        <select v-model="importForm.audience">
                            <option value="seller">Supplier</option>
                            <option value="buyer">Buyer</option>
                        </select>
                        <span v-if="importForm.errors.audience" class="field-error">{{ importForm.errors.audience }}</span>
                    </label>
                    <label>
                        Company List File *
                        <input type="file" accept=".csv,.txt,.xlsx,.xls" @change="handleImportFile" />
                        <span v-if="importForm.file" class="file-name">{{ importForm.file.name }}</span>
                        <span v-if="importForm.errors.file" class="field-error">{{ importForm.errors.file }}</span>
                    </label>
                </div>
                <footer>
                    <button type="button" class="secondary-action" :disabled="isImportSubmitting" @click="activeModal = null">Close</button>
                    <button type="button" class="primary-action" :disabled="isImportSubmitting" :aria-busy="isImportSubmitting ? 'true' : 'false'" @click="submitImport">{{ importButtonLabel }}</button>
                </footer>
            </section>

            <section v-if="activeModal === 'manual'" class="modal-card verification-modal-card">
                <OnboardingVerificationForm
                    :options="sellerVerificationOptions"
                    :submit-url="urls.manual_store"
                    @close="activeModal = null"
                    @saved="activeModal = null"
                />
            </section>

            <section v-if="activeModal === 'edit'" class="modal-card large">
                <header>
                    <h3>Review Onboarding Profile</h3>
                    <p>Review the pre-registration profile in the same structure suppliers will later complete. Required verification documents and final approval still remain inside the supplier verification flow.</p>
                </header>
                <div v-if="activeRecord?.parsed?.logo_url" class="logo-preview">
                    <img :src="activeRecord.parsed.logo_url" alt="Parsed company logo" />
                    <span>Logo detected from the public company profile. Supplier can still upload the official logo during verification.</span>
                </div>
                <div class="verification-preview-grid">
                    <section class="verification-section">
                        <div class="section-heading">
                            <p class="directory-eyebrow">Business Identity</p>
                            <h4>Company and Account Details</h4>
                            <span>Only account type, company name, and email are required before account creation.</span>
                        </div>
                        <div class="form-grid">
                            <label>
                                Account Type *
                                <select v-model="editForm.audience">
                                    <option value="seller">Supplier</option>
                                    <option value="buyer">Buyer</option>
                                </select>
                            </label>
                            <label>
                                Company Name *
                                <input v-model="editForm.company_name" type="text" />
                                <span v-if="editForm.errors.company_name" class="field-error">{{ editForm.errors.company_name }}</span>
                            </label>
                            <label>
                                Email *
                                <input v-model="editForm.email" type="email" />
                                <span v-if="editForm.errors.email" class="field-error">{{ editForm.errors.email }}</span>
                            </label>
                            <label>
                                Contact Name
                                <input v-model="editForm.contact_name" type="text" placeholder="Supplier may complete this later" />
                            </label>
                            <label>
                                Phone
                                <input v-model="editForm.phone" type="text" placeholder="Supplier may complete this later" />
                            </label>
                            <label>
                                Website
                                <input v-model="editForm.website_url" type="text" placeholder="https://example.com" />
                            </label>
                        </div>
                    </section>

                    <section class="verification-section">
                        <div class="section-heading">
                            <p class="directory-eyebrow">Category and Service Scope</p>
                            <h4>Business Activity and Parsed Ports</h4>
                            <span>These values help pre-fill context only. Supplier will still confirm exact categories, brands, countries, and ports during verification.</span>
                        </div>
                        <label>
                            Business Activity
                            <textarea v-model="editForm.business_activity" rows="4" placeholder="Parsed activity or service scope from the external profile"></textarea>
                        </label>
                        <div class="parsed-port-panel">
                            <div>
                                <strong>Parsed Serviced Ports</strong>
                                <span>{{ activeServicedPorts.length ? `${activeServicedPorts.length} ports detected` : 'No ports detected from pasted profile' }}</span>
                            </div>
                            <ul v-if="activeServicedPorts.length">
                                <li v-for="port in activeServicedPorts" :key="`${port.port}-${port.country}-${port.unlocode || port.raw_port}`" :class="{ unmatched: port.matched === false }">
                                    {{ port.port }}<span v-if="port.country"> / {{ port.country }}</span><span v-if="port.unlocode"> ({{ port.unlocode }})</span><span v-if="port.matched === false"> - not matched</span>
                                </li>
                            </ul>
                            <p v-else>
                                Supplier will select service countries and ports during verification before becoming publicly listed.
                            </p>
                        </div>
                    </section>

                    <section class="verification-section">
                        <div class="section-heading">
                            <p class="directory-eyebrow">Location</p>
                            <h4>Registered Business Location</h4>
                            <span>These fields are optional for pre-registration and can be completed by the supplier later.</span>
                        </div>
                        <div class="form-grid">
                            <label>
                                Country
                                <input v-model="editForm.country" type="text" />
                            </label>
                            <label>
                                City
                                <input v-model="editForm.city" type="text" />
                            </label>
                            <label>
                                Postal Code
                                <input v-model="editForm.postal_code" type="text" />
                            </label>
                            <label class="span-2">
                                Address
                                <input v-model="editForm.address" type="text" />
                            </label>
                        </div>
                    </section>

                    <section class="verification-section">
                        <div class="section-heading">
                            <p class="directory-eyebrow">Company Overview</p>
                            <h4>Public Profile Summary</h4>
                            <span>This can be pre-filled from the external profile, but supplier may edit it before submitting verification.</span>
                        </div>
                        <label>
                            Company Overview
                            <textarea v-model="editForm.company_overview" rows="6"></textarea>
                        </label>
                    </section>

                    <section class="verification-section muted">
                        <div class="section-heading">
                            <p class="directory-eyebrow">Official Details and Documents</p>
                            <h4>Supplier Verification Next Step</h4>
                            <span>Company Registration Number and Company Registration Documents are intentionally not required in this admin onboarding modal. The supplier will complete them after opening the secure account completion link.</span>
                        </div>
                    </section>
                </div>
                <footer>
                    <button type="button" class="secondary-action" :disabled="isImportSubmitting" @click="activeModal = null">Close</button>
                    <button type="button" class="primary-action" :disabled="editForm.processing" @click="submitEdit">Save Profile</button>
                </footer>
            </section>

            <section v-if="activeModal === 'delete'" class="modal-card">
                <header>
                    <h3>Delete Onboarding Record</h3>
                    <p>This removes only the onboarding record. It does not delete an already created user account.</p>
                </header>
                <div class="confirm-box">
                    <strong>{{ activeRecord?.company_name || '-' }}</strong>
                    <span>{{ activeRecord?.email || '-' }}</span>
                </div>
                <footer>
                    <button type="button" class="secondary-action" @click="activeModal = null">Cancel</button>
                    <button type="button" class="danger-action" :disabled="deleteForm.processing" @click="submitDelete">Confirm Delete</button>
                </footer>
            </section>
        </div>
    </AdminDashboardShell>
</template>

<style scoped>
.onboarding-page{display:grid;gap:18px}
.surface-panel{border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.94);box-shadow:0 20px 42px rgba(15,23,42,.06)}
.toolbar{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:22px 24px}.toolbar-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
.toolbar h2{margin:0;color:#0f172a;font-size:1.25rem}
.toolbar p{margin:8px 0 0;max-width:78ch;color:#64748b;font-size:.92rem;line-height:1.55}
.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.summary-card{display:grid;gap:8px;padding:18px}
.summary-card span{color:#64748b;font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em}
.summary-card strong{color:#0f172a;font-size:1.6rem}
.table-panel{padding:18px;display:grid;gap:16px}.panel-heading{display:flex;justify-content:space-between;gap:16px}.panel-heading h3{margin:0;color:#0f172a;font-size:1rem}.panel-heading p{margin:6px 0 0;color:#64748b;font-size:.86rem}.compact-table{min-width:980px}
.table-toolbar{display:grid;grid-template-columns:1fr 180px 180px;gap:12px}
input,select,textarea{width:100%;border:1px solid rgba(15,23,42,.12);border-radius:8px;background:#fff;color:#0f172a;font:inherit;padding:10px 12px;outline:none}
textarea{resize:vertical}
.table-scroll{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:880px}
th,td{padding:12px 10px;border-bottom:1px solid rgba(15,23,42,.08);text-align:left;font-size:.86rem;vertical-align:middle}
th{color:#334155;font-weight:700;background:#f8fafc}
td strong,td span{display:block}
td span{margin-top:3px;color:#64748b;font-size:.78rem}
.status-pill{display:inline-flex!important;margin:0;padding:5px 9px;border-radius:999px;background:#eef6ff;color:#075985;font-size:.76rem;font-weight:700}
.actions{display:flex;flex-wrap:wrap;gap:6px}
.actions button,.primary-action,.secondary-action,.danger-action{border:0;border-radius:8px;padding:9px 12px;font-size:.82rem;font-weight:700;cursor:pointer}
.actions button{background:#eef2f7;color:#0f172a}
.actions button:disabled,.primary-action:disabled,.secondary-action:disabled{opacity:.45;cursor:not-allowed}
.actions .danger,.danger-action{background:#fee2e2;color:#991b1b}
.primary-action{background:#0f172a;color:#fff}
.secondary-action{background:#eef2f7;color:#334155}
.empty-state{text-align:center;color:#64748b;padding:26px!important}
.modal-backdrop{position:fixed;inset:0;z-index:2500;display:flex;align-items:center;justify-content:center;padding:18px;background:rgba(15,23,42,.46);backdrop-filter:blur(8px)}
.modal-card{width:min(720px,100%);max-height:calc(100vh - 36px);overflow:auto;border-radius:12px;background:#fff;padding:22px;box-shadow:0 28px 70px rgba(15,23,42,.28);display:grid;gap:16px}
.modal-card.large{width:min(980px,100%)}
.modal-card header h3{margin:0;color:#0f172a;font-size:1.15rem}
.modal-card header p{margin:8px 0 0;color:#64748b;font-size:.9rem;line-height:1.5}
.modal-card label{display:grid;gap:7px;color:#334155;font-size:.82rem;font-weight:700}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.logo-preview{display:flex;align-items:center;gap:12px;padding:14px;border-radius:10px;background:#f8fafc}.logo-preview img{width:58px;height:58px;object-fit:contain;border-radius:10px;background:#fff;border:1px solid rgba(15,23,42,.08)}.logo-preview span{color:#64748b;font-size:.84rem;line-height:1.45}.verification-preview-grid{display:grid;gap:14px}
.verification-section{display:grid;gap:14px;padding:18px;border-radius:10px;background:#f8fafc}
.verification-section.muted{background:#eef6f6}
.section-heading{display:grid;gap:6px}
.section-heading h4{margin:0;color:#0f172a;font-size:1rem}
.section-heading span{color:#64748b;font-size:.84rem;line-height:1.45}
.parsed-port-panel{display:grid;gap:12px;padding:14px;border-radius:10px;background:#fff;border:1px solid rgba(15,23,42,.08)}
.parsed-port-panel>div{display:flex;justify-content:space-between;gap:12px;align-items:center}
.parsed-port-panel strong{color:#0f172a;font-size:.9rem}
.parsed-port-panel span,.parsed-port-panel p{color:#64748b;font-size:.82rem;margin:0}
.parsed-port-panel ul{display:flex;flex-wrap:wrap;gap:8px;margin:0;padding:0;list-style:none}
.parsed-port-panel li{display:inline-flex;gap:4px;padding:7px 9px;border-radius:999px;background:#eef6ff;color:#075985;font-size:.78rem;font-weight:700}.parsed-port-panel li.unmatched{background:#fff7ed;color:#9a3412}
.span-2{grid-column:span 2}
.field-error{color:#b91c1c;font-size:.78rem;font-weight:700}
.confirm-box{display:grid;gap:4px;padding:14px;border-radius:10px;background:#f8fafc}
.confirm-box span{color:#64748b}
.import-format-box{display:grid;gap:8px;padding:14px;border-radius:10px;background:#f8fafc;border:1px solid rgba(15,23,42,.08)}
.import-format-box strong{color:#0f172a;font-size:.9rem}
.import-format-box code{display:inline-flex;width:max-content;max-width:100%;padding:7px 9px;border-radius:8px;background:#fff;color:#0f172a;font-size:.84rem;overflow:auto}
.import-format-box span,.file-name{color:#64748b;font-size:.82rem;line-height:1.45}
footer{display:flex;justify-content:flex-end;gap:10px}
@media (max-width: 820px){
    .toolbar{align-items:stretch;flex-direction:column}
    .summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .table-toolbar{grid-template-columns:1fr}
    .form-grid{grid-template-columns:1fr}
    .span-2{grid-column:auto}
}
@media (max-width: 560px){
    .summary-grid{grid-template-columns:1fr}
    .modal-backdrop{align-items:flex-end;padding:0}
    .modal-card{width:100%;max-height:92vh;border-radius:14px 14px 0 0}
    footer{flex-direction:column-reverse}
    footer button{width:100%}
}

.verification-modal-card {
    max-width: min(1180px, calc(100vw - 2rem));
    width: 100%;
}
</style>
