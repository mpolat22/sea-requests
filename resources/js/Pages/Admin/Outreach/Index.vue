<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminDashboardShell from '../Dashboard/Shell.vue';
import PaginationControls from '../Dashboard/Components/PaginationControls.vue';

const props = defineProps({
    dashboard: { type: Object, required: true },
    activeTab: { type: String, default: 'outreach' },
    summary: { type: Object, required: true },
    regions: { type: Array, required: true },
    regionOptions: { type: Array, required: true },
    senderAccounts: { type: Array, required: true },
    templates: { type: Array, required: true },
    logs: { type: Array, required: true },
    contactsPage: { type: Object, required: true },
    imports: { type: Array, required: true },
    urls: { type: Object, required: true },
    weekdayOptions: { type: Array, required: true },
});

const todayDate = new Date().toISOString().slice(0, 10);
const importInputKey = ref(0);
const activeModal = ref(null);
const activeRegionKey = ref('');
const activeSenderId = ref(null);
const activeTemplateId = ref(null);
const activeContactId = ref(null);
const manualContactFieldRefs = {};
const createSenderFieldRefs = {};
const createTemplateFieldRefs = {};
const senderEditFieldRefs = {};
const templateEditFieldRefs = {};
const manualContactEmailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const contactSearch = ref(props.contactsPage.filters?.search ?? '');
const contactStatus = ref(props.contactsPage.filters?.status ?? 'all');
const contactRegion = ref(props.contactsPage.filters?.region ?? '');
const isRefreshingContacts = ref(false);
const isRefreshingLogs = ref(false);
const contactsLastSyncedAt = ref(new Date().toISOString());
const logsLastSyncedAt = ref(new Date().toISOString());
const logSearch = ref('');
const logStatus = ref('all');
let deliveryMonitorRefreshTimer = null;

const contactStatusOptions = [
    { value: 'all', label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'registered', label: 'Registered' },
    { value: 'unsubscribed', label: 'Unsubscribed' },
    { value: 'replied', label: 'Replied' },
    { value: 'paused', label: 'Paused' },
];

const logStatusOptions = [
    { value: 'all', label: 'All statuses' },
    { value: 'queued', label: 'Queued' },
    { value: 'sent', label: 'Sent' },
    { value: 'failed', label: 'Failed' },
    { value: 'skipped', label: 'Skipped' },
];

const summaryCards = computed(() => [
    {
        label: 'Imported Contacts',
        value: formatNumber(props.summary.total_contacts),
        actionLabel: 'Open list',
        actionType: 'contacts',
        actionPayload: { status: 'all', region: '', search: '' },
    },
    {
        label: 'Registered Sellers',
        value: formatNumber(props.summary.registered_contacts),
        actionLabel: 'View registered',
        actionType: 'contacts',
        actionPayload: { status: 'registered', region: '', search: '' },
    },
    {
        label: 'Unsubscribed',
        value: formatNumber(props.summary.unsubscribed_contacts),
        actionLabel: 'View unsubscribed',
        actionType: 'contacts',
        actionPayload: { status: 'unsubscribed', region: '', search: '' },
    },
    {
        label: 'Sent Today',
        value: formatNumber(props.summary.sent_today),
        actionLabel: 'Open monitor',
        actionType: 'logs',
        actionPayload: { status: 'sent', search: '' },
    },
    {
        label: 'Queued Now',
        value: formatNumber(props.summary.queued_now),
        actionLabel: 'View queued',
        actionType: 'logs',
        actionPayload: { status: 'queued', search: '' },
    },
]);

const nextSortOrder = computed(() => {
    const sortOrders = props.templates.map((template) => Number(template.sort_order ?? 0));
    return sortOrders.length ? Math.max(...sortOrders) + 1 : 1;
});

const activeRegion = computed(() => props.regions.find((region) => region.key === activeRegionKey.value) ?? null);
const activeSender = computed(() => props.senderAccounts.find((sender) => sender.id === activeSenderId.value) ?? null);
const activeTemplate = computed(() => props.templates.find((template) => template.id === activeTemplateId.value) ?? null);
const activeContact = computed(() => contactRows.value.find((contact) => contact.id === activeContactId.value) ?? null);
const contactRows = computed(() => props.contactsPage.data ?? []);
const contactMeta = computed(() => props.contactsPage.meta ?? {});
const contactCurrentPage = computed(() => Number(contactMeta.value.current_page ?? 1));
const contactTotalPages = computed(() => Number(contactMeta.value.last_page ?? 1));
const contactTotal = computed(() => Number(contactMeta.value.total ?? contactRows.value.length));
const contactFrom = computed(() => Number(contactMeta.value.from ?? 0));
const contactTo = computed(() => Number(contactMeta.value.to ?? 0));
const hasContactFilters = computed(() => contactStatus.value !== 'all' || contactRegion.value !== '' || contactSearch.value.trim() !== '');
const contactSummaryLabel = computed(() => {
    if (!contactTotal.value) {
        return hasContactFilters.value
            ? 'No supplier contact matched the current filters.'
            : 'No supplier contact has been imported yet.';
    }

    return `Showing ${contactFrom.value}-${contactTo.value} of ${formatNumber(contactTotal.value)} supplier contacts.`;
});
const contactPaginationLabel = computed(() => `Page ${contactCurrentPage.value} of ${contactTotalPages.value}`);
const filteredLogs = computed(() => {
    const statusFilter = String(logStatus.value ?? 'all');
    const searchTerm = String(logSearch.value ?? '').trim().toLowerCase();

    return (props.logs ?? []).filter((log) => {
        if (statusFilter !== 'all' && String(log.status ?? '') !== statusFilter) {
            return false;
        }

        if (!searchTerm) {
            return true;
        }

        const haystack = [
            log.recipient_email,
            log.recipient_organization,
            log.segment_name,
            log.sender_name,
            log.sender_email,
            log.template_name,
            log.subject,
            log.error_message,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(searchTerm);
    });
});
const filteredLogCounts = computed(() => ({
    queued: filteredLogs.value.filter((log) => log.status === 'queued').length,
    sent: filteredLogs.value.filter((log) => log.status === 'sent').length,
    failed: filteredLogs.value.filter((log) => log.status === 'failed').length,
    skipped: filteredLogs.value.filter((log) => log.status === 'skipped').length,
}));
const hasLogFilters = computed(() => logStatus.value !== 'all' || logSearch.value.trim() !== '');

const importForm = useForm({
    file: null,
});

const createSenderForm = useForm({
    from_name: '',
    from_email: '',
    smtp_host: '',
    smtp_port: 587,
    smtp_encryption: 'tls',
    smtp_username: '',
    smtp_password: '',
    is_active: true,
    is_default: props.senderAccounts.length === 0,
});

const createTemplateForm = useForm({
    name: '',
    subject: '',
    body_text: '',
    is_active: true,
    sort_order: nextSortOrder.value,
});

const senderForms = Object.fromEntries(
    props.senderAccounts.map((sender) => [
        sender.id,
        useForm({
            from_name: sender.from_name ?? '',
            from_email: sender.from_email ?? '',
            smtp_host: sender.smtp_host ?? '',
            smtp_port: Number(sender.smtp_port ?? 587),
            smtp_encryption: sender.smtp_encryption ?? 'tls',
            smtp_username: sender.smtp_username ?? '',
            smtp_password: '',
            is_active: Boolean(sender.is_active),
            is_default: Boolean(sender.is_default),
        }),
    ]),
);

const senderTestForms = Object.fromEntries(
    props.senderAccounts.map((sender) => [
        sender.id,
        useForm({}),
    ]),
);

const templateForms = Object.fromEntries(
    props.templates.map((template) => [
        template.id,
        useForm({
            name: template.name ?? '',
            subject: template.subject ?? '',
            body_text: template.body_text ?? '',
            is_active: Boolean(template.is_active),
            sort_order: Number(template.sort_order ?? 0),
        }),
    ]),
);

const regionForms = Object.fromEntries(
    props.regions.map((region) => [
        region.key,
        useForm({
            starts_on: region.schedule?.starts_on ?? todayDate,
            send_interval_minutes: Number(region.schedule?.send_interval_minutes ?? 1),
            is_active: Boolean(region.schedule?.is_active),
            weekly_plan: normalizeWeeklyPlan(region.schedule?.weekly_plan ?? []),
        }),
    ]),
);

const manualContactForm = useForm({
    email: '',
    organization_name: '',
    source_name: '',
    region_key: props.regionOptions[0]?.key ?? '',
    notes: '',
});

const deletePlanForm = useForm({});
const deleteSenderForm = useForm({});
const deleteTemplateForm = useForm({});
const deleteContactForm = useForm({});

function normalizeWeeklyPlan(weeklyPlan = []) {
    return props.weekdayOptions.map((option) => {
        const day = weeklyPlan.find((entry) => Number(entry.weekday) === Number(option.value)) ?? {};

        return {
            weekday: Number(option.value),
            label: option.label,
            enabled: Boolean(day.enabled),
            start_time: day.start_time ?? '',
            end_time: day.end_time ?? '',
            daily_limit: day.daily_limit ?? '',
        };
    });
}

function formatNumber(value) {
    return new Intl.NumberFormat('en-GB').format(Number(value ?? 0));
}

function formatDate(value) {
    if (!value) return '-';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
}

function formatDateTime(value) {
    if (!value) return '-';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function statusClass(status) {
    if (['completed', 'sent', 'active'].includes(status)) return 'is-good';
    if (['queued', 'processing'].includes(status)) return 'is-pending';
    if (['skipped', 'registered', 'unsubscribed'].includes(status)) return 'is-neutral';
    if (status === 'failed') return 'is-bad';

    return 'is-neutral';
}

function statusLabel(status) {
    if (status === 'queued') return 'Queued';
    if (status === 'processing') return 'Processing';
    if (status === 'completed') return 'Completed';
    if (status === 'failed') return 'Failed';
    if (status === 'sent') return 'Sent';
    if (status === 'skipped') return 'Skipped';
    if (status === 'active') return 'Active';
    if (status === 'unsubscribed') return 'Unsubscribed';
    if (status === 'registered') return 'Registered';

    return status ? String(status).replace(/_/g, ' ') : '-';
}

function templateStatusLabel(template) {
    return template?.is_active ? 'Active' : 'Paused';
}

function senderStatusLabel(sender) {
    return sender?.is_active ? 'Active' : 'Paused';
}

function encryptionLabel(value) {
    if (value === 'tls') return 'TLS';
    if (value === 'ssl') return 'SSL';

    return 'None';
}

function planSummary(region) {
    const activeDays = (region.schedule?.weekly_plan ?? []).filter((day) => day.enabled);

    if (!region.schedule?.is_active) {
        return 'Plan paused';
    }

    if (!activeDays.length) {
        return 'No weekdays active';
    }

    if (activeDays.length === 1) {
        const day = activeDays[0];
        return `${day.label} / ${day.start_time || '-'} - ${day.end_time || '-'}`;
    }

    return `${activeDays.length} weekdays active`;
}

function regionStatusLabel(region) {
    return region.schedule?.is_active ? 'Active Plan' : 'Paused';
}

function resetManualContactForm(region = null) {
    manualContactForm.email = '';
    manualContactForm.organization_name = '';
    manualContactForm.source_name = '';
    manualContactForm.notes = '';
    manualContactForm.region_key = region?.key ?? props.regionOptions[0]?.key ?? '';
    manualContactForm.clearErrors();
}

function manualContactInputClass(field) {
    return {
        'has-error': Boolean(manualContactForm.errors[field]),
    };
}

function registerManualContactField(field, element) {
    if (element) {
        manualContactFieldRefs[field] = element;
    }
}

function validateManualContactField(field) {
    const value = manualContactForm[field];

    if (field === 'region_key') {
        return value ? '' : 'Region is required.';
    }

    if (field === 'organization_name') {
        return String(value ?? '').trim() ? '' : 'Company name is required.';
    }

    if (field === 'email') {
        const email = String(value ?? '').trim();

        if (!email) {
            return 'Email address is required.';
        }

        if (!manualContactEmailPattern.test(email)) {
            return 'Enter a valid email address.';
        }
    }

    return '';
}

function clearManualContactFieldError(field) {
    if (validateManualContactField(field) === '') {
        manualContactForm.clearErrors(field);
    }
}

async function focusFirstManualContactError(errors = manualContactForm.errors) {
    const fieldOrder = ['region_key', 'organization_name', 'email'];
    const firstField = fieldOrder.find((field) => Boolean(errors[field]));

    if (!firstField) {
        return;
    }

    await nextTick();

    const element = manualContactFieldRefs[firstField];

    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        element.focus?.();
    }
}

function resetCreateSenderForm() {
    createSenderForm.reset();
    createSenderForm.clearErrors();
    createSenderForm.from_name = '';
    createSenderForm.from_email = '';
    createSenderForm.smtp_host = '';
    createSenderForm.smtp_port = 587;
    createSenderForm.smtp_encryption = 'tls';
    createSenderForm.smtp_username = '';
    createSenderForm.smtp_password = '';
    createSenderForm.is_active = true;
    createSenderForm.is_default = props.senderAccounts.length === 0;
}

function resetCreateTemplateForm() {
    createTemplateForm.reset();
    createTemplateForm.clearErrors();
    createTemplateForm.name = '';
    createTemplateForm.subject = '';
    createTemplateForm.body_text = '';
    createTemplateForm.is_active = true;
    createTemplateForm.sort_order = nextSortOrder.value;
}

function resetSenderEditForm(sender) {
    if (!sender?.id || !senderForms[sender.id]) {
        return;
    }

    const form = senderForms[sender.id];
    form.clearErrors();
    form.from_name = sender.from_name ?? '';
    form.from_email = sender.from_email ?? '';
    form.smtp_host = sender.smtp_host ?? '';
    form.smtp_port = Number(sender.smtp_port ?? 587);
    form.smtp_encryption = sender.smtp_encryption ?? 'tls';
    form.smtp_username = sender.smtp_username ?? '';
    form.smtp_password = '';
    form.is_active = Boolean(sender.is_active);
    form.is_default = Boolean(sender.is_default);
}

function resetTemplateEditForm(template) {
    if (!template?.id || !templateForms[template.id]) {
        return;
    }

    const form = templateForms[template.id];
    form.clearErrors();
    form.name = template.name ?? '';
    form.subject = template.subject ?? '';
    form.body_text = template.body_text ?? '';
    form.is_active = Boolean(template.is_active);
    form.sort_order = Number(template.sort_order ?? 0);
}

function createSenderInputClass(field) {
    return {
        'has-error': Boolean(createSenderForm.errors[field]),
    };
}

function createTemplateInputClass(field) {
    return {
        'has-error': Boolean(createTemplateForm.errors[field]),
    };
}

function senderEditInputClass(senderId, field) {
    return {
        'has-error': Boolean(senderForms[senderId]?.errors?.[field]),
    };
}

function templateEditInputClass(templateId, field) {
    return {
        'has-error': Boolean(templateForms[templateId]?.errors?.[field]),
    };
}

function registerCreateSenderField(field, element) {
    if (element) {
        createSenderFieldRefs[field] = element;
    }
}

function registerCreateTemplateField(field, element) {
    if (element) {
        createTemplateFieldRefs[field] = element;
    }
}

function registerSenderEditField(senderId, field, element) {
    if (!senderId) {
        return;
    }

    if (!senderEditFieldRefs[senderId]) {
        senderEditFieldRefs[senderId] = {};
    }

    if (element) {
        senderEditFieldRefs[senderId][field] = element;
    }
}

function registerTemplateEditField(templateId, field, element) {
    if (!templateId) {
        return;
    }

    if (!templateEditFieldRefs[templateId]) {
        templateEditFieldRefs[templateId] = {};
    }

    if (element) {
        templateEditFieldRefs[templateId][field] = element;
    }
}

function validateCreateSenderField(field) {
    const value = createSenderForm[field];

    if (field === 'from_name') {
        return String(value ?? '').trim() ? '' : 'From name is required.';
    }

    if (field === 'from_email') {
        const email = String(value ?? '').trim();

        if (!email) {
            return 'From email is required.';
        }

        if (!manualContactEmailPattern.test(email)) {
            return 'Enter a valid email address.';
        }
    }

    if (field === 'smtp_host') {
        return String(value ?? '').trim() ? '' : 'SMTP host is required.';
    }

    if (field === 'smtp_port') {
        const port = Number(value);

        if (!Number.isInteger(port)) {
            return 'SMTP port is required.';
        }

        if (port < 1 || port > 65535) {
            return 'SMTP port must be between 1 and 65535.';
        }
    }

    if (field === 'smtp_encryption') {
        return value === '' || ['tls', 'ssl'].includes(String(value)) ? '' : 'Choose a valid connection security option.';
    }

    if (field === 'smtp_username') {
        return String(value ?? '').trim() ? '' : 'SMTP username is required.';
    }

    if (field === 'smtp_password') {
        return String(value ?? '').trim() ? '' : 'SMTP password is required.';
    }

    return '';
}

function validateCreateTemplateField(field) {
    const value = createTemplateForm[field];

    if (field === 'name') {
        return String(value ?? '').trim() ? '' : 'Template name is required.';
    }

    if (field === 'subject') {
        return String(value ?? '').trim() ? '' : 'Subject is required.';
    }

    if (field === 'body_text') {
        const body = String(value ?? '').trim();

        if (!body) {
            return 'Body is required.';
        }

        if (body.length < 20) {
            return 'Body must be at least 20 characters.';
        }
    }

    if (field === 'sort_order') {
        const sortOrder = Number(value);

        if (!Number.isInteger(sortOrder)) {
            return 'Sort order is required.';
        }

        if (sortOrder < 0 || sortOrder > 999) {
            return 'Sort order must be between 0 and 999.';
        }
    }

    return '';
}

function validateSenderEditField(senderId, field) {
    const form = senderForms[senderId];

    if (!form) {
        return '';
    }

    const value = form[field];

    if (field === 'from_name') {
        return String(value ?? '').trim() ? '' : 'From name is required.';
    }

    if (field === 'from_email') {
        const email = String(value ?? '').trim();

        if (!email) {
            return 'From email is required.';
        }

        if (!manualContactEmailPattern.test(email)) {
            return 'Enter a valid email address.';
        }
    }

    if (field === 'smtp_host') {
        return String(value ?? '').trim() ? '' : 'SMTP host is required.';
    }

    if (field === 'smtp_port') {
        const port = Number(value);

        if (!Number.isInteger(port)) {
            return 'SMTP port is required.';
        }

        if (port < 1 || port > 65535) {
            return 'SMTP port must be between 1 and 65535.';
        }
    }

    if (field === 'smtp_encryption') {
        return value === '' || ['tls', 'ssl'].includes(String(value)) ? '' : 'Choose a valid connection security option.';
    }

    if (field === 'smtp_username') {
        return String(value ?? '').trim() ? '' : 'SMTP username is required.';
    }

    return '';
}

function validateTemplateEditField(templateId, field) {
    const form = templateForms[templateId];

    if (!form) {
        return '';
    }

    const value = form[field];

    if (field === 'name') {
        return String(value ?? '').trim() ? '' : 'Template name is required.';
    }

    if (field === 'subject') {
        return String(value ?? '').trim() ? '' : 'Subject is required.';
    }

    if (field === 'body_text') {
        const body = String(value ?? '').trim();

        if (!body) {
            return 'Body is required.';
        }

        if (body.length < 20) {
            return 'Body must be at least 20 characters.';
        }
    }

    if (field === 'sort_order') {
        const sortOrder = Number(value);

        if (!Number.isInteger(sortOrder)) {
            return 'Sort order is required.';
        }

        if (sortOrder < 0 || sortOrder > 999) {
            return 'Sort order must be between 0 and 999.';
        }
    }

    return '';
}

function clearCreateSenderFieldError(field) {
    if (validateCreateSenderField(field) === '') {
        createSenderForm.clearErrors(field, 'sender_test');
    }
}

function clearCreateTemplateFieldError(field) {
    if (validateCreateTemplateField(field) === '') {
        createTemplateForm.clearErrors(field);
    }
}

function clearSenderEditFieldError(senderId, field) {
    const form = senderForms[senderId];

    if (form && validateSenderEditField(senderId, field) === '') {
        form.clearErrors(field);
    }
}

function clearTemplateEditFieldError(templateId, field) {
    const form = templateForms[templateId];

    if (form && validateTemplateEditField(templateId, field) === '') {
        form.clearErrors(field);
    }
}

async function focusFirstCreateSenderError(errors = createSenderForm.errors) {
    const fieldOrder = ['from_name', 'from_email', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password'];
    const firstField = fieldOrder.find((field) => Boolean(errors[field]));

    if (!firstField) {
        return;
    }

    await nextTick();

    const element = createSenderFieldRefs[firstField];

    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        element.focus?.();
    }
}

async function focusFirstCreateTemplateError(errors = createTemplateForm.errors) {
    const fieldOrder = ['name', 'sort_order', 'subject', 'body_text'];
    const firstField = fieldOrder.find((field) => Boolean(errors[field]));

    if (!firstField) {
        return;
    }

    await nextTick();

    const element = createTemplateFieldRefs[firstField];

    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        element.focus?.();
    }
}

async function focusFirstSenderEditError(senderId, errors = senderForms[senderId]?.errors ?? {}) {
    const fieldOrder = ['from_name', 'from_email', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username'];
    const firstField = fieldOrder.find((field) => Boolean(errors[field]));

    if (!firstField) {
        return;
    }

    await nextTick();

    const element = senderEditFieldRefs[senderId]?.[firstField];

    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        element.focus?.();
    }
}

async function focusFirstTemplateEditError(templateId, errors = templateForms[templateId]?.errors ?? {}) {
    const fieldOrder = ['name', 'sort_order', 'subject', 'body_text'];
    const firstField = fieldOrder.find((field) => Boolean(errors[field]));

    if (!firstField) {
        return;
    }

    await nextTick();

    const element = templateEditFieldRefs[templateId]?.[firstField];

    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        element.focus?.();
    }
}

function collectCreateSenderErrors() {
    return ['from_name', 'from_email', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password']
        .map((field) => [field, validateCreateSenderField(field)])
        .filter(([, message]) => message)
        .reduce((carry, [field, message]) => ({ ...carry, [field]: message }), {});
}

function collectCreateTemplateErrors() {
    return ['name', 'sort_order', 'subject', 'body_text']
        .map((field) => [field, validateCreateTemplateField(field)])
        .filter(([, message]) => message)
        .reduce((carry, [field, message]) => ({ ...carry, [field]: message }), {});
}

function collectSenderEditErrors(senderId) {
    return ['from_name', 'from_email', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username']
        .map((field) => [field, validateSenderEditField(senderId, field)])
        .filter(([, message]) => message)
        .reduce((carry, [field, message]) => ({ ...carry, [field]: message }), {});
}

function collectTemplateEditErrors(templateId) {
    return ['name', 'sort_order', 'subject', 'body_text']
        .map((field) => [field, validateTemplateEditField(templateId, field)])
        .filter(([, message]) => message)
        .reduce((carry, [field, message]) => ({ ...carry, [field]: message }), {});
}

function syncContactFiltersFromProps(value = props.contactsPage) {
    contactSearch.value = value?.filters?.search ?? '';
    contactStatus.value = value?.filters?.status ?? 'all';
    contactRegion.value = value?.filters?.region ?? '';
}

function buildContactsQuery(page = 1) {
    return {
        contacts_page: page > 1 ? page : undefined,
        contacts_status: contactStatus.value !== 'all' ? contactStatus.value : undefined,
        contacts_region: contactRegion.value || undefined,
        contacts_search: contactSearch.value.trim() || undefined,
    };
}

function fetchContacts(page = 1) {
    activeModal.value = 'contacts';

    router.get(props.urls.index, buildContactsQuery(page), {
        only: ['contactsPage'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => {
            isRefreshingContacts.value = true;
        },
        onFinish: () => {
            isRefreshingContacts.value = false;
        },
        onSuccess: () => {
            contactsLastSyncedAt.value = new Date().toISOString();
        },
    });
}

function applyContactFilters() {
    fetchContacts(1);
}

function clearContactFilters() {
    contactSearch.value = '';
    contactStatus.value = 'all';
    contactRegion.value = '';
    fetchContacts(1);
}

function openContactsModal(filters = {}) {
    const nextStatus = contactStatusOptions.some((option) => option.value === filters.status)
        ? filters.status
        : contactStatus.value;
    const nextRegion = typeof filters.region === 'string' ? filters.region : contactRegion.value;
    const nextSearch = typeof filters.search === 'string' ? filters.search : contactSearch.value;

    const currentFilters = props.contactsPage.filters ?? {};
    const matchesCurrentPage = (currentFilters.status ?? 'all') === nextStatus
        && (currentFilters.region ?? '') === nextRegion
        && (currentFilters.search ?? '') === nextSearch;

    contactStatus.value = nextStatus;
    contactRegion.value = nextRegion;
    contactSearch.value = nextSearch;
    activeModal.value = 'contacts';

    if (!matchesCurrentPage) {
        fetchContacts(1);
        return;
    }

    contactsLastSyncedAt.value = new Date().toISOString();
}

function openLogsModal(filters = {}) {
    logStatus.value = logStatusOptions.some((option) => option.value === filters.status)
        ? filters.status
        : 'all';
    logSearch.value = typeof filters.search === 'string' ? filters.search : '';
    activeModal.value = 'logs';
    refreshLogs(true);
}

function handleSummaryCardAction(card) {
    if (card.actionType === 'contacts') {
        openContactsModal(card.actionPayload ?? {});
        return;
    }

    if (card.actionType === 'logs') {
        openLogsModal(card.actionPayload ?? {});
    }
}

function refreshLogs(silent = false) {
    if (isRefreshingLogs.value) {
        return;
    }

    router.reload({
        only: ['logs', 'summary', 'regions'],
        preserveState: true,
        preserveScroll: true,
        onStart: () => {
            isRefreshingLogs.value = true;
        },
        onFinish: () => {
            isRefreshingLogs.value = false;
        },
        onSuccess: () => {
            logsLastSyncedAt.value = new Date().toISOString();
        },
    });
}

function stopDeliveryMonitorRefresh() {
    if (deliveryMonitorRefreshTimer) {
        window.clearInterval(deliveryMonitorRefreshTimer);
        deliveryMonitorRefreshTimer = null;
    }
}

function startDeliveryMonitorRefresh() {
    stopDeliveryMonitorRefresh();

    deliveryMonitorRefreshTimer = window.setInterval(() => {
        refreshLogs(true);
    }, 30000);
}

function openModal(type, payload = null) {
    activeModal.value = type;
    activeRegionKey.value = payload?.key ?? '';
    activeSenderId.value = payload?.id ?? null;
    activeTemplateId.value = payload?.id ?? null;
    activeContactId.value = payload?.id ?? null;
    deletePlanForm.clearErrors();
    deleteSenderForm.clearErrors();
    deleteTemplateForm.clearErrors();
    deleteContactForm.clearErrors();

    if (type === 'contact') {
        resetManualContactForm(payload);
    }

    if (type === 'template-create') {
        resetCreateTemplateForm();
    }

    if (type === 'sender-create') {
        resetCreateSenderForm();
    }

    if (type === 'sender-edit') {
        resetSenderEditForm(payload);
    }

    if (type === 'template-edit') {
        resetTemplateEditForm(payload);
    }

    if (type === 'contacts') {
        contactsLastSyncedAt.value = new Date().toISOString();
    }

    if (type === 'logs') {
        logsLastSyncedAt.value = new Date().toISOString();
    }
}

function closeModal() {
    const wasManualContactModal = activeModal.value === 'contact';

    activeModal.value = null;
    activeRegionKey.value = '';
    activeSenderId.value = null;
    activeTemplateId.value = null;
    activeContactId.value = null;

    if (wasManualContactModal) {
        resetManualContactForm();
    } else {
        manualContactForm.clearErrors();
    }

    deletePlanForm.clearErrors();
    deleteSenderForm.clearErrors();
    deleteTemplateForm.clearErrors();
    deleteContactForm.clearErrors();
}

watch(
    () => props.contactsPage,
    (value) => {
        syncContactFiltersFromProps(value);
        contactsLastSyncedAt.value = new Date().toISOString();
    },
    { deep: true },
);

watch(
    () => props.logs,
    () => {
        logsLastSyncedAt.value = new Date().toISOString();
    },
    { deep: true },
);

watch(
    activeModal,
    (value) => {
        if (value === 'logs') {
            startDeliveryMonitorRefresh();
            return;
        }

        stopDeliveryMonitorRefresh();
    },
);

onBeforeUnmount(() => {
    stopDeliveryMonitorRefresh();
});

function onImportFileChange(event) {
    importForm.file = event.target.files?.[0] ?? null;
}

function submitImport() {
    importForm.post(props.urls.import, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            importForm.reset();
            importInputKey.value += 1;
            activeModal.value = null;
        },
    });
}

async function submitCreateTemplate() {
    createTemplateForm.clearErrors();

    const clientErrors = collectCreateTemplateErrors();

    if (Object.keys(clientErrors).length) {
        createTemplateForm.setError(clientErrors);
        await focusFirstCreateTemplateError(clientErrors);
        return;
    }

    createTemplateForm.post(props.urls.template_store, {
        preserveScroll: true,
        onSuccess: () => {
            resetCreateTemplateForm();
            activeModal.value = 'templates';
        },
        onError: focusFirstCreateTemplateError,
    });
}

async function submitCreateSender() {
    createSenderForm.clearErrors();

    const clientErrors = collectCreateSenderErrors();

    if (Object.keys(clientErrors).length) {
        createSenderForm.setError(clientErrors);
        await focusFirstCreateSenderError(clientErrors);
        return;
    }

    createSenderForm.post(props.urls.sender_store, {
        preserveScroll: true,
        onSuccess: () => {
            resetCreateSenderForm();
            activeModal.value = 'senders';
        },
        onError: focusFirstCreateSenderError,
    });
}

async function sendCreateSenderTest() {
    createSenderForm.clearErrors();

    const clientErrors = collectCreateSenderErrors();

    if (Object.keys(clientErrors).length) {
        createSenderForm.setError(clientErrors);
        await focusFirstCreateSenderError(clientErrors);
        return;
    }

    createSenderForm.post(props.urls.sender_test_draft, {
        preserveScroll: true,
        onError: focusFirstCreateSenderError,
    });
}

async function saveSender(senderId) {
    const form = senderForms[senderId];
    const sender = props.senderAccounts.find((item) => item.id === senderId);

    form.clearErrors();

    const clientErrors = collectSenderEditErrors(senderId);

    if (Object.keys(clientErrors).length) {
        form.setError(clientErrors);
        await focusFirstSenderEditError(senderId, clientErrors);
        return;
    }

    form.put(sender?.update_url, {
        preserveScroll: true,
        onSuccess: () => {
            activeModal.value = 'senders';
            activeSenderId.value = null;
        },
        onError: (errors) => focusFirstSenderEditError(senderId, errors),
    });
}

function sendSavedSenderTest(senderId) {
    const form = senderTestForms[senderId];
    const sender = props.senderAccounts.find((item) => item.id === senderId);

    form.post(sender?.test_url, {
        preserveScroll: true,
    });
}

async function saveTemplate(templateId) {
    const form = templateForms[templateId];
    const template = props.templates.find((item) => item.id === templateId);

    form.clearErrors();

    const clientErrors = collectTemplateEditErrors(templateId);

    if (Object.keys(clientErrors).length) {
        form.setError(clientErrors);
        await focusFirstTemplateEditError(templateId, clientErrors);
        return;
    }

    form.put(template?.update_url, {
        preserveScroll: true,
        onSuccess: () => {
            activeModal.value = 'templates';
            activeTemplateId.value = null;
        },
        onError: (errors) => focusFirstTemplateEditError(templateId, errors),
    });
}

function saveRegionPlan(regionKey) {
    const form = regionForms[regionKey];
    const region = props.regions.find((item) => item.key === regionKey);

    form.put(region?.plan_update_url, {
        preserveScroll: true,
    });
}

async function submitManualContact() {
    manualContactForm.clearErrors();

    const clientErrors = ['region_key', 'organization_name', 'email']
        .map((field) => [field, validateManualContactField(field)])
        .filter(([, message]) => message)
        .reduce((carry, [field, message]) => ({ ...carry, [field]: message }), {});

    if (Object.keys(clientErrors).length) {
        manualContactForm.setError(clientErrors);
        await focusFirstManualContactError(clientErrors);
        return;
    }

    manualContactForm.post(props.urls.contact_store, {
        preserveScroll: true,
        onSuccess: () => {
            resetManualContactForm();
            closeModal();
        },
        onError: focusFirstManualContactError,
    });
}

function deleteRegionPlan() {
    if (!activeRegion?.value?.plan_delete_url) {
        return;
    }

    deletePlanForm.delete(activeRegion.value.plan_delete_url, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
        },
    });
}

function deleteTemplate() {
    if (!activeTemplate?.value?.delete_url) {
        return;
    }

    deleteTemplateForm.delete(activeTemplate.value.delete_url, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            activeModal.value = 'templates';
        },
    });
}

function deleteSender() {
    if (!activeSender?.value?.delete_url) {
        return;
    }

    deleteSenderForm.delete(activeSender.value.delete_url, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            activeModal.value = 'senders';
        },
    });
}

function deleteContact() {
    if (!activeContact?.value?.delete_url) {
        return;
    }

    const fallbackPage = contactRows.value.length === 1 && contactCurrentPage.value > 1
        ? contactCurrentPage.value - 1
        : contactCurrentPage.value;

    deleteContactForm.delete(activeContact.value.delete_url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            activeContactId.value = null;
            activeModal.value = 'contacts';

            if (fallbackPage !== contactCurrentPage.value) {
                router.get(props.urls.index, buildContactsQuery(fallbackPage), {
                    only: ['summary', 'regions', 'contactsPage'],
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    onStart: () => {
                        isRefreshingContacts.value = true;
                    },
                    onFinish: () => {
                        isRefreshingContacts.value = false;
                    },
                    onSuccess: () => {
                        contactsLastSyncedAt.value = new Date().toISOString();
                    },
                });

                return;
            }

            contactsLastSyncedAt.value = new Date().toISOString();
        },
    });
}
</script>

<template>
    <AdminDashboardShell :dashboard="dashboard" title="Admin Dashboard" :active-tab="activeTab">
        <section class="summary-grid">
            <article
                v-for="card in summaryCards"
                :key="card.label"
                class="surface-panel summary-card"
                :class="{ 'summary-card-actionable': Boolean(card.actionLabel) }"
            >
                <p class="summary-label">{{ card.label }}</p>
                <p class="summary-value">{{ card.value }}</p>
                <div v-if="card.actionLabel" class="summary-card-foot">
                    <button type="button" class="summary-card-action" @click="handleSummaryCardAction(card)">
                        {{ card.actionLabel }}
                    </button>
                </div>
            </article>
        </section>

        <section class="surface-panel table-panel">
            <div class="table-toolbar">
                <div class="table-intro">
                    <h2 class="directory-section-title">Outreach Regions</h2>
                    <p class="section-copy">
                        Imported supplier lists are grouped under continent-based outreach regions here. Use the action column to review, edit, or clear one region plan at a time.
                    </p>
                </div>

                <div class="toolbar-actions">
                    <button type="button" class="toolbar-button toolbar-button-secondary" @click="openModal('import')">
                        Import CSV
                    </button>
                    <button type="button" class="toolbar-button toolbar-button-secondary" @click="openModal('senders')">
                        Sender Accounts
                    </button>
                    <button type="button" class="toolbar-button toolbar-button-secondary" @click="openModal('templates')">
                        Templates
                    </button>
                    <button type="button" class="toolbar-button toolbar-button-secondary" @click="openContactsModal()">
                        Contacts
                    </button>
                    <button type="button" class="toolbar-button toolbar-button-secondary" @click="openLogsModal()">
                        Delivery Monitor
                    </button>
                    <button type="button" class="toolbar-button toolbar-button-primary" @click="openModal('contact')">
                        Add Contact
                    </button>
                </div>
            </div>

            <div class="dashboard-table-wrap">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Status</th>
                            <th>Region</th>
                            <th>Contacts</th>
                            <th>Plan</th>
                            <th>Last Dispatch</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(region, index) in regions" :key="region.key">
                            <td class="order-index-cell">{{ index + 1 }}</td>
                            <td class="status-dot-cell">
                                <span
                                    class="status-dot"
                                    :class="region.schedule?.is_active ? 'is-good' : 'is-neutral'"
                                    :title="regionStatusLabel(region)"
                                    :aria-label="regionStatusLabel(region)"
                                />
                            </td>
                            <td>
                                <div class="identity-stack">
                                    <span class="identity-primary">{{ region.label }}</span>
                                    <span class="identity-secondary">{{ region.canonical_segment_name }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="metric-stack">
                                    <span class="metric-primary">{{ formatNumber(region.contacts_count) }}</span>
                                    <span class="metric-secondary">
                                        Registered {{ formatNumber(region.registered_contacts) }} / Unsubscribed {{ formatNumber(region.unsubscribed_contacts) }}
                                    </span>
                                </div>
                            </td>
                            <td>{{ planSummary(region) }}</td>
                            <td>{{ formatDateTime(region.schedule?.last_dispatched_at) }}</td>
                            <td>
                                <div class="actions-cell">
                                    <button type="button" class="action-button" title="View" @click="openModal('view-region', region)">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                                    </button>
                                    <button type="button" class="action-button" title="Edit" @click="openModal('edit-region', region)">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10.5-10.5-4-4L4 16v4Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m13.5 6.5 4 4" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                                    </button>
                                    <button type="button" class="action-button action-button-danger" title="Delete" @click="openModal('delete-region', region)">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M9 7V4h6v3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M7 7l1 13h8l1-13" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div v-if="activeModal" class="admin-modal-backdrop" @click.self="closeModal">
            <div class="admin-modal admin-modal-wide" @click.stop>
                <button type="button" class="admin-modal-close" @click="closeModal">&times;</button>

                <template v-if="activeModal === 'import'">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">Supplier CSV Import</h3>
                            <p class="modal-copy">
                                Import external supplier email lists here. These records stay inside the outreach workspace and do not create platform login accounts.
                            </p>
                        </div>
                    </div>

                    <div class="modal-stack">
                        <form class="modal-card" @submit.prevent="submitImport">
                            <div class="form-grid single-column-grid">
                                <label class="field-group">
                                    <span class="field-label">Supplier contact CSV <span class="required-mark">*</span></span>
                                    <input
                                        :key="importInputKey"
                                        type="file"
                                        accept=".csv,text/csv"
                                        class="field-input"
                                        @change="onImportFileChange"
                                    >
                                </label>
                                <p class="field-help">{{ importForm.file?.name || 'No file selected yet.' }}</p>
                                <p v-if="importForm.errors.file" class="field-error">{{ importForm.errors.file }}</p>
                            </div>

                            <div class="action-row">
                                <button type="button" class="toolbar-button toolbar-button-secondary" @click="closeModal">
                                    Close
                                </button>
                                <button type="submit" class="toolbar-button toolbar-button-primary" :disabled="importForm.processing || !importForm.file">
                                    {{ importForm.processing ? 'Queueing...' : 'Queue Supplier Import' }}
                                </button>
                            </div>
                        </form>

                        <div class="modal-card">
                            <div class="modal-card-head">
                                <h4 class="card-title">Recent Imports</h4>
                            </div>

                            <div v-if="imports.length" class="data-table-wrap">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>File</th>
                                            <th>Status</th>
                                            <th>Rows</th>
                                            <th>Processed</th>
                                            <th>New</th>
                                            <th>Updated</th>
                                            <th>Duplicates</th>
                                            <th>Completed</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="run in imports" :key="run.id">
                                            <td>
                                                <span class="table-strong">{{ run.file_name }}</span>
                                                <span class="table-sub">{{ run.message || '-' }}</span>
                                            </td>
                                            <td>
                                                <span class="status-pill" :class="statusClass(run.status)">
                                                    {{ statusLabel(run.status) }}
                                                </span>
                                            </td>
                                            <td>{{ formatNumber(run.row_count) }}</td>
                                            <td>{{ formatNumber(run.processed_count) }}</td>
                                            <td>{{ formatNumber(run.new_contacts_count) }}</td>
                                            <td>{{ formatNumber(run.updated_contacts_count) }}</td>
                                            <td>{{ formatNumber(run.duplicate_emails_count) }}</td>
                                            <td>{{ formatDateTime(run.completed_at || run.created_at) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="empty-state">No outreach imports run yet.</div>
                        </div>
                    </div>
                </template>

                <template v-else-if="activeModal === 'senders'">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">Sender Accounts</h3>
                            <p class="modal-copy">Manage the mail accounts used for supplier outreach delivery. One active default sender is used first.</p>
                        </div>
                        <div class="toolbar-actions">
                            <button type="button" class="toolbar-button toolbar-button-primary" @click="openModal('sender-create')">
                                Add Sender
                            </button>
                        </div>
                    </div>

                    <div v-if="senderAccounts.length" class="data-table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Status</th>
                                    <th>From</th>
                                    <th>SMTP</th>
                                    <th>Default</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(sender, index) in senderAccounts" :key="sender.id">
                                    <td class="order-index-cell">{{ index + 1 }}</td>
                                    <td class="status-dot-cell">
                                        <span
                                            class="status-dot"
                                            :class="sender.is_active ? 'is-good' : 'is-neutral'"
                                            :title="senderStatusLabel(sender)"
                                            :aria-label="senderStatusLabel(sender)"
                                        />
                                    </td>
                                    <td>
                                        <div class="identity-stack">
                                            <span class="identity-primary">{{ sender.from_name }}</span>
                                            <span class="identity-secondary">{{ sender.from_email }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="identity-stack">
                                            <span class="identity-primary">{{ sender.smtp_host }}:{{ sender.smtp_port }}</span>
                                            <span class="identity-secondary">{{ sender.smtp_username }} / {{ encryptionLabel(sender.smtp_encryption) }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-pill" :class="sender.is_default ? 'is-good' : 'is-neutral'">
                                            {{ sender.is_default ? 'Default' : 'Optional' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions-cell">
                                            <button type="button" class="action-button" title="View" @click="openModal('sender-view', sender)">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                                            </button>
                                            <button
                                                type="button"
                                                class="action-button"
                                                :class="{ 'is-loading': senderTestForms[sender.id]?.processing }"
                                                :title="senderTestForms[sender.id]?.processing ? 'Sending Test Email...' : 'Send Test Email'"
                                                :aria-label="senderTestForms[sender.id]?.processing ? 'Sending Test Email...' : 'Send Test Email'"
                                                :disabled="senderTestForms[sender.id]?.processing"
                                                @click="sendSavedSenderTest(sender.id)"
                                            >
                                                <svg v-if="senderTestForms[sender.id]?.processing" viewBox="0 0 24 24" aria-hidden="true" class="spin-icon"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-dasharray="32 18"/></svg>
                                                <svg v-else viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.5 21 4l-7 16-2.7-6.3L3 11.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M11.3 13.7 21 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                            </button>
                                            <button type="button" class="action-button" title="Edit" @click="openModal('sender-edit', sender)">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10.5-10.5-4-4L4 16v4Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m13.5 6.5 4 4" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                                            </button>
                                            <button type="button" class="action-button action-button-danger" title="Delete" @click="openModal('sender-delete', sender)">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M9 7V4h6v3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M7 7l1 13h8l1-13" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="empty-state">No sender accounts saved yet. Outreach currently falls back to the default app mail configuration.</div>
                </template>

                <template v-else-if="activeModal === 'sender-create'">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">Add Sender Account</h3>
                            <p class="modal-copy">Save a new sender mailbox for supplier outreach delivery. Replies will return to the same sender email automatically.</p>
                        </div>
                    </div>

                    <form class="modal-card" @submit.prevent="submitCreateSender">
                        <div class="form-grid">
                            <label class="field-group">
                                <span class="field-label">From Name <span class="required-mark">*</span></span>
                                <input :ref="(element) => registerCreateSenderField('from_name', element)" v-model="createSenderForm.from_name" type="text" class="field-input" :class="createSenderInputClass('from_name')" placeholder="Sea Requests" @input="clearCreateSenderFieldError('from_name')">
                                <span v-if="createSenderForm.errors.from_name" class="field-error">{{ createSenderForm.errors.from_name }}</span>
                            </label>
                            <label class="field-group">
                                <span class="field-label">From Email <span class="required-mark">*</span></span>
                                <input :ref="(element) => registerCreateSenderField('from_email', element)" v-model="createSenderForm.from_email" type="email" class="field-input" :class="createSenderInputClass('from_email')" placeholder="request@example.com" @input="clearCreateSenderFieldError('from_email')">
                                <span v-if="createSenderForm.errors.from_email" class="field-error">{{ createSenderForm.errors.from_email }}</span>
                            </label>
                            <label class="field-group">
                                <span class="field-label">SMTP Host <span class="required-mark">*</span></span>
                                <input :ref="(element) => registerCreateSenderField('smtp_host', element)" v-model="createSenderForm.smtp_host" type="text" class="field-input" :class="createSenderInputClass('smtp_host')" placeholder="smtp.googlemail.com" @input="clearCreateSenderFieldError('smtp_host')">
                                <span v-if="createSenderForm.errors.smtp_host" class="field-error">{{ createSenderForm.errors.smtp_host }}</span>
                            </label>
                            <label class="field-group">
                                <span class="field-label">SMTP Port <span class="required-mark">*</span></span>
                                <input :ref="(element) => registerCreateSenderField('smtp_port', element)" v-model="createSenderForm.smtp_port" type="number" min="1" max="65535" class="field-input" :class="createSenderInputClass('smtp_port')" @input="clearCreateSenderFieldError('smtp_port')">
                                <span v-if="createSenderForm.errors.smtp_port" class="field-error">{{ createSenderForm.errors.smtp_port }}</span>
                            </label>
                            <label class="field-group">
                                <span class="field-label">Connection Security</span>
                                <select :ref="(element) => registerCreateSenderField('smtp_encryption', element)" v-model="createSenderForm.smtp_encryption" class="field-input" :class="createSenderInputClass('smtp_encryption')" @change="clearCreateSenderFieldError('smtp_encryption')">
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="">None</option>
                                </select>
                                <span v-if="createSenderForm.errors.smtp_encryption" class="field-error">{{ createSenderForm.errors.smtp_encryption }}</span>
                            </label>
                            <label class="field-group">
                                <span class="field-label">SMTP Username <span class="required-mark">*</span></span>
                                <input :ref="(element) => registerCreateSenderField('smtp_username', element)" v-model="createSenderForm.smtp_username" type="text" class="field-input" :class="createSenderInputClass('smtp_username')" placeholder="request@example.com" @input="clearCreateSenderFieldError('smtp_username')">
                                <span v-if="createSenderForm.errors.smtp_username" class="field-error">{{ createSenderForm.errors.smtp_username }}</span>
                            </label>
                            <label class="field-group field-group-full">
                                <span class="field-label">SMTP Password / App Password <span class="required-mark">*</span></span>
                                <input :ref="(element) => registerCreateSenderField('smtp_password', element)" v-model="createSenderForm.smtp_password" type="password" class="field-input" :class="createSenderInputClass('smtp_password')" placeholder="Enter app password" @input="clearCreateSenderFieldError('smtp_password')">
                                <span v-if="createSenderForm.errors.smtp_password" class="field-error">{{ createSenderForm.errors.smtp_password }}</span>
                            </label>
                            <label class="checkbox-row">
                                <input v-model="createSenderForm.is_active" type="checkbox">
                                <span>Sender is active</span>
                            </label>
                            <label class="checkbox-row">
                                <input v-model="createSenderForm.is_default" type="checkbox">
                                <span>Use as default sender</span>
                            </label>
                        </div>

                        <div v-if="Object.keys(createSenderForm.errors).some((key) => key !== 'sender_test')" class="field-error-list">
                            <p v-for="(error, errorKey) in createSenderForm.errors" v-show="errorKey !== 'sender_test'" :key="errorKey" class="field-error">
                                {{ error }}
                            </p>
                        </div>

                        <p class="field-help">Test email is sent to the sender mailbox you entered above.</p>
                        <p v-if="createSenderForm.errors.sender_test" class="field-error">{{ createSenderForm.errors.sender_test }}</p>

                        <div class="action-row">
                            <button type="button" class="toolbar-button toolbar-button-secondary" @click="openModal('senders')">
                                Back
                            </button>
                            <button type="button" class="toolbar-button toolbar-button-secondary" :disabled="createSenderForm.processing" @click="sendCreateSenderTest">
                                {{ createSenderForm.processing ? 'Sending...' : 'Send Test Email' }}
                            </button>
                            <button type="submit" class="toolbar-button toolbar-button-primary" :disabled="createSenderForm.processing">
                                {{ createSenderForm.processing ? 'Saving...' : 'Create Sender' }}
                            </button>
                        </div>
                    </form>
                </template>

                <template v-else-if="activeModal === 'sender-view' && activeSender">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">{{ activeSender.from_name }}</h3>
                            <p class="modal-copy">Review the saved sender mailbox settings used for outreach delivery.</p>
                        </div>
                    </div>

                    <div class="modal-stack">
                        <div class="region-overview-grid region-overview-grid-tight">
                            <div class="overview-card">
                                <span>From Name</span>
                                <strong>{{ activeSender.from_name }}</strong>
                            </div>
                            <div class="overview-card">
                                <span>From Email</span>
                                <strong>{{ activeSender.from_email }}</strong>
                            </div>
                            <div class="overview-card">
                                <span>Status</span>
                                <strong>{{ senderStatusLabel(activeSender) }}</strong>
                            </div>
                            <div class="overview-card">
                                <span>Default Sender</span>
                                <strong>{{ activeSender.is_default ? 'Yes' : 'No' }}</strong>
                            </div>
                        </div>

                        <div class="modal-card">
                            <div class="region-overview-grid region-overview-grid-tight">
                                <div class="overview-card">
                                    <span>SMTP Host</span>
                                    <strong>{{ activeSender.smtp_host }}</strong>
                                </div>
                                <div class="overview-card">
                                    <span>SMTP Port</span>
                                    <strong>{{ activeSender.smtp_port }}</strong>
                                </div>
                                <div class="overview-card">
                                    <span>Security</span>
                                    <strong>{{ encryptionLabel(activeSender.smtp_encryption) }}</strong>
                                </div>
                                <div class="overview-card">
                                    <span>SMTP Username</span>
                                    <strong>{{ activeSender.smtp_username }}</strong>
                                </div>
                                <div class="overview-card">
                                    <span>Password Saved</span>
                                    <strong>{{ activeSender.has_password ? 'Yes' : 'No' }}</strong>
                                </div>
                                <div class="overview-card">
                                    <span>Created</span>
                                    <strong>{{ formatDateTime(activeSender.created_at) }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="action-row">
                            <button type="button" class="toolbar-button toolbar-button-secondary" @click="openModal('senders')">
                                Back
                            </button>
                        </div>
                    </div>
                </template>

                <template v-else-if="activeModal === 'sender-edit' && activeSender">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">Edit Sender Account</h3>
                            <p class="modal-copy">Update the selected sender mailbox. Leave the password blank to keep the current saved password.</p>
                        </div>
                    </div>

                    <form class="modal-card" @submit.prevent="saveSender(activeSender.id)">
                        <div class="form-grid">
                            <label class="field-group">
                                <span class="field-label">From Name <span class="required-mark">*</span></span>
                                <input
                                    :ref="(element) => registerSenderEditField(activeSender.id, 'from_name', element)"
                                    v-model="senderForms[activeSender.id].from_name"
                                    type="text"
                                    class="field-input"
                                    :class="senderEditInputClass(activeSender.id, 'from_name')"
                                    @input="clearSenderEditFieldError(activeSender.id, 'from_name')"
                                >
                                <span v-if="senderForms[activeSender.id].errors.from_name" class="field-error">{{ senderForms[activeSender.id].errors.from_name }}</span>
                            </label>
                            <label class="field-group">
                                <span class="field-label">From Email <span class="required-mark">*</span></span>
                                <input
                                    :ref="(element) => registerSenderEditField(activeSender.id, 'from_email', element)"
                                    v-model="senderForms[activeSender.id].from_email"
                                    type="email"
                                    class="field-input"
                                    :class="senderEditInputClass(activeSender.id, 'from_email')"
                                    @input="clearSenderEditFieldError(activeSender.id, 'from_email')"
                                >
                                <span v-if="senderForms[activeSender.id].errors.from_email" class="field-error">{{ senderForms[activeSender.id].errors.from_email }}</span>
                            </label>
                            <label class="field-group">
                                <span class="field-label">SMTP Host <span class="required-mark">*</span></span>
                                <input
                                    :ref="(element) => registerSenderEditField(activeSender.id, 'smtp_host', element)"
                                    v-model="senderForms[activeSender.id].smtp_host"
                                    type="text"
                                    class="field-input"
                                    :class="senderEditInputClass(activeSender.id, 'smtp_host')"
                                    @input="clearSenderEditFieldError(activeSender.id, 'smtp_host')"
                                >
                                <span v-if="senderForms[activeSender.id].errors.smtp_host" class="field-error">{{ senderForms[activeSender.id].errors.smtp_host }}</span>
                            </label>
                            <label class="field-group">
                                <span class="field-label">SMTP Port <span class="required-mark">*</span></span>
                                <input
                                    :ref="(element) => registerSenderEditField(activeSender.id, 'smtp_port', element)"
                                    v-model="senderForms[activeSender.id].smtp_port"
                                    type="number"
                                    min="1"
                                    max="65535"
                                    class="field-input"
                                    :class="senderEditInputClass(activeSender.id, 'smtp_port')"
                                    @input="clearSenderEditFieldError(activeSender.id, 'smtp_port')"
                                >
                                <span v-if="senderForms[activeSender.id].errors.smtp_port" class="field-error">{{ senderForms[activeSender.id].errors.smtp_port }}</span>
                            </label>
                            <label class="field-group">
                                <span class="field-label">Connection Security</span>
                                <select
                                    :ref="(element) => registerSenderEditField(activeSender.id, 'smtp_encryption', element)"
                                    v-model="senderForms[activeSender.id].smtp_encryption"
                                    class="field-input"
                                    :class="senderEditInputClass(activeSender.id, 'smtp_encryption')"
                                    @change="clearSenderEditFieldError(activeSender.id, 'smtp_encryption')"
                                >
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="">None</option>
                                </select>
                                <span v-if="senderForms[activeSender.id].errors.smtp_encryption" class="field-error">{{ senderForms[activeSender.id].errors.smtp_encryption }}</span>
                            </label>
                            <label class="field-group">
                                <span class="field-label">SMTP Username <span class="required-mark">*</span></span>
                                <input
                                    :ref="(element) => registerSenderEditField(activeSender.id, 'smtp_username', element)"
                                    v-model="senderForms[activeSender.id].smtp_username"
                                    type="text"
                                    class="field-input"
                                    :class="senderEditInputClass(activeSender.id, 'smtp_username')"
                                    @input="clearSenderEditFieldError(activeSender.id, 'smtp_username')"
                                >
                                <span v-if="senderForms[activeSender.id].errors.smtp_username" class="field-error">{{ senderForms[activeSender.id].errors.smtp_username }}</span>
                            </label>
                            <label class="field-group field-group-full">
                                <span class="field-label">SMTP Password / App Password</span>
                                <input
                                    v-model="senderForms[activeSender.id].smtp_password"
                                    type="password"
                                    class="field-input"
                                    placeholder="Leave blank to keep current password"
                                >
                            </label>
                            <label class="checkbox-row">
                                <input v-model="senderForms[activeSender.id].is_active" type="checkbox">
                                <span>Sender is active</span>
                            </label>
                            <label class="checkbox-row">
                                <input v-model="senderForms[activeSender.id].is_default" type="checkbox">
                                <span>Use as default sender</span>
                            </label>
                        </div>

                        <div v-if="Object.keys(senderForms[activeSender.id].errors).length" class="field-error-list">
                            <p v-for="(error, errorKey) in senderForms[activeSender.id].errors" :key="errorKey" class="field-error">
                                {{ error }}
                            </p>
                        </div>

                        <div class="action-row">
                            <button type="button" class="toolbar-button toolbar-button-secondary" @click="openModal('senders')">
                                Back
                            </button>
                            <button type="submit" class="toolbar-button toolbar-button-primary" :disabled="senderForms[activeSender.id].processing">
                                {{ senderForms[activeSender.id].processing ? 'Saving...' : 'Save Sender' }}
                            </button>
                        </div>
                    </form>
                </template>

                <template v-else-if="activeModal === 'sender-delete' && activeSender">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">Delete Sender Account</h3>
                            <p class="modal-copy">Delete this sender only if it is no longer needed for future outreach emails.</p>
                        </div>
                    </div>

                    <div class="modal-card">
                        <div class="region-overview-grid region-overview-grid-tight">
                            <div class="overview-card">
                                <span>Sender</span>
                                <strong>{{ activeSender.from_name }}</strong>
                            </div>
                            <div class="overview-card">
                                <span>From Email</span>
                                <strong>{{ activeSender.from_email }}</strong>
                            </div>
                            <div class="overview-card">
                                <span>Status</span>
                                <strong>{{ senderStatusLabel(activeSender) }}</strong>
                            </div>
                            <div class="overview-card">
                                <span>Default</span>
                                <strong>{{ activeSender.is_default ? 'Yes' : 'No' }}</strong>
                            </div>
                        </div>

                        <p v-if="deleteSenderForm.errors.sender" class="field-error">{{ deleteSenderForm.errors.sender }}</p>

                        <div class="action-row">
                            <button type="button" class="toolbar-button toolbar-button-secondary" @click="openModal('senders')">
                                Cancel
                            </button>
                            <button type="button" class="toolbar-button toolbar-button-danger" :disabled="deleteSenderForm.processing" @click="deleteSender">
                                {{ deleteSenderForm.processing ? 'Deleting...' : 'Delete Sender' }}
                            </button>
                        </div>
                    </div>
                </template>

                <template v-else-if="activeModal === 'templates'">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">Mail Templates</h3>
                            <p class="modal-copy">Manage outreach mail templates from one clean table and open create, view, edit, or delete only when needed.</p>
                        </div>
                        <div class="toolbar-actions">
                            <button type="button" class="toolbar-button toolbar-button-primary" @click="openModal('template-create')">
                                Add Template
                            </button>
                        </div>
                    </div>

                    <div v-if="templates.length" class="data-table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Status</th>
                                    <th>Template</th>
                                    <th>Subject</th>
                                    <th>Sort Order</th>
                                    <th>Created</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(template, index) in templates" :key="template.id">
                                    <td class="order-index-cell">{{ index + 1 }}</td>
                                    <td class="status-dot-cell">
                                        <span
                                            class="status-dot"
                                            :class="template.is_active ? 'is-good' : 'is-neutral'"
                                            :title="templateStatusLabel(template)"
                                            :aria-label="templateStatusLabel(template)"
                                        />
                                    </td>
                                    <td>
                                        <div class="identity-stack">
                                            <span class="identity-primary">{{ template.name }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="table-subject-preview">{{ template.subject }}</span>
                                    </td>
                                    <td>{{ formatNumber(template.sort_order) }}</td>
                                    <td>{{ formatDateTime(template.created_at) }}</td>
                                    <td>
                                        <div class="actions-cell">
                                            <button type="button" class="action-button" title="View" @click="openModal('template-view', template)">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                                            </button>
                                            <button type="button" class="action-button" title="Edit" @click="openModal('template-edit', template)">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10.5-10.5-4-4L4 16v4Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m13.5 6.5 4 4" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                                            </button>
                                            <button type="button" class="action-button action-button-danger" title="Delete" @click="openModal('template-delete', template)">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M9 7V4h6v3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M7 7l1 13h8l1-13" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="empty-state">No outreach templates created yet.</div>
                </template>

                <template v-else-if="activeModal === 'template-create'">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">Add Template</h3>
                            <p class="modal-copy">Create a supplier outreach mail template. Active templates are sent automatically in sort-order rotation.</p>
                        </div>
                    </div>

                    <form class="modal-card" @submit.prevent="submitCreateTemplate">
                        <div class="form-grid">
                            <label class="field-group">
                                <span class="field-label">Template Name <span class="required-mark">*</span></span>
                                <input :ref="(element) => registerCreateTemplateField('name', element)" v-model="createTemplateForm.name" type="text" class="field-input" :class="createTemplateInputClass('name')" placeholder="Supplier Invitation 01" @input="clearCreateTemplateFieldError('name')">
                                <span v-if="createTemplateForm.errors.name" class="field-error">{{ createTemplateForm.errors.name }}</span>
                            </label>
                            <label class="field-group">
                                <span class="field-label">Sort Order <span class="required-mark">*</span></span>
                                <input :ref="(element) => registerCreateTemplateField('sort_order', element)" v-model="createTemplateForm.sort_order" type="number" min="0" max="999" class="field-input" :class="createTemplateInputClass('sort_order')" @input="clearCreateTemplateFieldError('sort_order')">
                                <span v-if="createTemplateForm.errors.sort_order" class="field-error">{{ createTemplateForm.errors.sort_order }}</span>
                            </label>
                            <label class="field-group field-group-full">
                                <span class="field-label">Subject <span class="required-mark">*</span></span>
                                <input :ref="(element) => registerCreateTemplateField('subject', element)" v-model="createTemplateForm.subject" type="text" class="field-input" :class="createTemplateInputClass('subject')" @input="clearCreateTemplateFieldError('subject')">
                                <span v-if="createTemplateForm.errors.subject" class="field-error">{{ createTemplateForm.errors.subject }}</span>
                            </label>
                            <label class="field-group field-group-full">
                                <span class="field-label">Body <span class="required-mark">*</span></span>
                                <textarea :ref="(element) => registerCreateTemplateField('body_text', element)" v-model="createTemplateForm.body_text" class="field-textarea" :class="createTemplateInputClass('body_text')" rows="10" @input="clearCreateTemplateFieldError('body_text')" />
                                <span v-if="createTemplateForm.errors.body_text" class="field-error">{{ createTemplateForm.errors.body_text }}</span>
                            </label>
                            <label class="checkbox-row field-group-full">
                                <input v-model="createTemplateForm.is_active" type="checkbox">
                                <span>Template is active</span>
                            </label>
                        </div>

                        <div v-if="Object.keys(createTemplateForm.errors).length" class="field-error-list">
                            <p v-for="(error, errorKey) in createTemplateForm.errors" :key="errorKey" class="field-error">
                                {{ error }}
                            </p>
                        </div>

                        <div class="action-row">
                            <button type="button" class="toolbar-button toolbar-button-secondary" @click="openModal('templates')">
                                Back
                            </button>
                            <button type="submit" class="toolbar-button toolbar-button-primary" :disabled="createTemplateForm.processing">
                                {{ createTemplateForm.processing ? 'Saving...' : 'Create Template' }}
                            </button>
                        </div>
                    </form>
                </template>

                <template v-else-if="activeModal === 'template-view' && activeTemplate">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">{{ activeTemplate.name }}</h3>
                            <p class="modal-copy">Review the saved template content exactly as it will be managed inside the outreach workspace.</p>
                        </div>
                    </div>

                    <div class="modal-stack">
                        <div class="region-overview-grid region-overview-grid-tight">
                            <div class="overview-card">
                                <span>Template Name</span>
                                <strong>{{ activeTemplate.name }}</strong>
                            </div>
                            <div class="overview-card">
                                <span>Sort Order</span>
                                <strong>{{ formatNumber(activeTemplate.sort_order) }}</strong>
                            </div>
                            <div class="overview-card">
                                <span>Status</span>
                                <strong>{{ templateStatusLabel(activeTemplate) }}</strong>
                            </div>
                            <div class="overview-card">
                                <span>Created</span>
                                <strong>{{ formatDateTime(activeTemplate.created_at) }}</strong>
                            </div>
                        </div>

                        <div class="modal-card">
                            <div class="field-group">
                                <span class="field-label">Subject</span>
                                <div class="template-preview-box">{{ activeTemplate.subject }}</div>
                            </div>
                            <div class="field-group">
                                <span class="field-label">Body</span>
                                <div class="template-preview-box template-preview-body">{{ activeTemplate.body_text }}</div>
                            </div>
                        </div>

                        <div class="action-row">
                            <button type="button" class="toolbar-button toolbar-button-secondary" @click="openModal('templates')">
                                Back
                            </button>
                        </div>
                    </div>
                </template>

                <template v-else-if="activeModal === 'template-edit' && activeTemplate">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">Edit Template</h3>
                            <p class="modal-copy">Update the selected outreach template from one focused form.</p>
                        </div>
                    </div>

                    <form class="modal-card" @submit.prevent="saveTemplate(activeTemplate.id)">
                        <div class="form-grid">
                            <label class="field-group">
                                <span class="field-label">Template Name <span class="required-mark">*</span></span>
                                <input
                                    :ref="(element) => registerTemplateEditField(activeTemplate.id, 'name', element)"
                                    v-model="templateForms[activeTemplate.id].name"
                                    type="text"
                                    class="field-input"
                                    :class="templateEditInputClass(activeTemplate.id, 'name')"
                                    @input="clearTemplateEditFieldError(activeTemplate.id, 'name')"
                                >
                                <span v-if="templateForms[activeTemplate.id].errors.name" class="field-error">{{ templateForms[activeTemplate.id].errors.name }}</span>
                            </label>
                            <label class="field-group">
                                <span class="field-label">Sort Order <span class="required-mark">*</span></span>
                                <input
                                    :ref="(element) => registerTemplateEditField(activeTemplate.id, 'sort_order', element)"
                                    v-model="templateForms[activeTemplate.id].sort_order"
                                    type="number"
                                    min="0"
                                    max="999"
                                    class="field-input"
                                    :class="templateEditInputClass(activeTemplate.id, 'sort_order')"
                                    @input="clearTemplateEditFieldError(activeTemplate.id, 'sort_order')"
                                >
                                <span v-if="templateForms[activeTemplate.id].errors.sort_order" class="field-error">{{ templateForms[activeTemplate.id].errors.sort_order }}</span>
                            </label>
                            <label class="field-group field-group-full">
                                <span class="field-label">Subject <span class="required-mark">*</span></span>
                                <input
                                    :ref="(element) => registerTemplateEditField(activeTemplate.id, 'subject', element)"
                                    v-model="templateForms[activeTemplate.id].subject"
                                    type="text"
                                    class="field-input"
                                    :class="templateEditInputClass(activeTemplate.id, 'subject')"
                                    @input="clearTemplateEditFieldError(activeTemplate.id, 'subject')"
                                >
                                <span v-if="templateForms[activeTemplate.id].errors.subject" class="field-error">{{ templateForms[activeTemplate.id].errors.subject }}</span>
                            </label>
                            <label class="field-group field-group-full">
                                <span class="field-label">Body <span class="required-mark">*</span></span>
                                <textarea
                                    :ref="(element) => registerTemplateEditField(activeTemplate.id, 'body_text', element)"
                                    v-model="templateForms[activeTemplate.id].body_text"
                                    class="field-textarea"
                                    :class="templateEditInputClass(activeTemplate.id, 'body_text')"
                                    rows="10"
                                    @input="clearTemplateEditFieldError(activeTemplate.id, 'body_text')"
                                />
                                <span v-if="templateForms[activeTemplate.id].errors.body_text" class="field-error">{{ templateForms[activeTemplate.id].errors.body_text }}</span>
                            </label>
                            <label class="checkbox-row field-group-full">
                                <input v-model="templateForms[activeTemplate.id].is_active" type="checkbox">
                                <span>Template is active</span>
                            </label>
                        </div>

                        <div v-if="Object.keys(templateForms[activeTemplate.id].errors).length" class="field-error-list">
                            <p v-for="(error, errorKey) in templateForms[activeTemplate.id].errors" :key="errorKey" class="field-error">
                                {{ error }}
                            </p>
                        </div>

                        <div class="action-row">
                            <button type="button" class="toolbar-button toolbar-button-secondary" @click="openModal('templates')">
                                Back
                            </button>
                            <button type="submit" class="toolbar-button toolbar-button-primary" :disabled="templateForms[activeTemplate.id].processing">
                                {{ templateForms[activeTemplate.id].processing ? 'Saving...' : 'Save Template' }}
                            </button>
                        </div>
                    </form>
                </template>

                <template v-else-if="activeModal === 'template-delete' && activeTemplate">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">Delete Template</h3>
                            <p class="modal-copy">Delete this template only if you no longer want it included in the automatic rotation.</p>
                        </div>
                    </div>

                    <div class="modal-card">
                        <div class="region-overview-grid region-overview-grid-tight">
                            <div class="overview-card">
                                <span>Template Name</span>
                                <strong>{{ activeTemplate.name }}</strong>
                            </div>
                            <div class="overview-card">
                                <span>Subject</span>
                                <strong>{{ activeTemplate.subject }}</strong>
                            </div>
                            <div class="overview-card">
                                <span>Sort Order</span>
                                <strong>{{ formatNumber(activeTemplate.sort_order) }}</strong>
                            </div>
                            <div class="overview-card">
                                <span>Status</span>
                                <strong>{{ templateStatusLabel(activeTemplate) }}</strong>
                            </div>
                        </div>

                        <p v-if="deleteTemplateForm.errors.template" class="field-error">{{ deleteTemplateForm.errors.template }}</p>

                        <div class="action-row">
                            <button type="button" class="toolbar-button toolbar-button-secondary" @click="openModal('templates')">
                                Cancel
                            </button>
                            <button type="button" class="toolbar-button toolbar-button-danger" :disabled="deleteTemplateForm.processing" @click="deleteTemplate">
                                {{ deleteTemplateForm.processing ? 'Deleting...' : 'Delete Template' }}
                            </button>
                        </div>
                    </div>
                </template>

                <template v-else-if="activeModal === 'logs'">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">Delivery Monitor</h3>
                            <p class="modal-copy">Review the latest background outreach attempts, their region, template, and any worker error returned by the queue. This monitor refreshes automatically every 30 seconds while it stays open.</p>
                        </div>
                        <div class="toolbar-actions">
                            <button type="button" class="toolbar-button toolbar-button-secondary" :disabled="isRefreshingLogs" @click="refreshLogs()">
                                {{ isRefreshingLogs ? 'Refreshing...' : 'Refresh' }}
                            </button>
                        </div>
                    </div>

                    <div class="modal-stack">
                        <div class="modal-card">
                            <form class="filter-toolbar-grid filter-toolbar-grid-monitor" @submit.prevent>
                                <label class="field-group">
                                    <span class="field-label">Search</span>
                                    <input v-model="logSearch" type="search" class="field-input" placeholder="Search recipient / company / sender / template">
                                </label>
                                <label class="field-group">
                                    <span class="field-label">Status</span>
                                    <select v-model="logStatus" class="field-input">
                                        <option v-for="option in logStatusOptions" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </label>
                            </form>

                            <div class="monitor-stats-row">
                                <div class="monitor-stat-chip">
                                    <span>Queued</span>
                                    <strong>{{ formatNumber(filteredLogCounts.queued) }}</strong>
                                </div>
                                <div class="monitor-stat-chip">
                                    <span>Sent</span>
                                    <strong>{{ formatNumber(filteredLogCounts.sent) }}</strong>
                                </div>
                                <div class="monitor-stat-chip">
                                    <span>Failed</span>
                                    <strong>{{ formatNumber(filteredLogCounts.failed) }}</strong>
                                </div>
                                <div class="monitor-stat-chip">
                                    <span>Skipped</span>
                                    <strong>{{ formatNumber(filteredLogCounts.skipped) }}</strong>
                                </div>
                            </div>

                            <p class="filter-meta">
                                Showing {{ formatNumber(filteredLogs.length) }} of the latest {{ formatNumber(props.logs.length) }} delivery log rows.
                                Last synced {{ formatDateTime(logsLastSyncedAt) }}.
                            </p>
                        </div>

                        <div v-if="filteredLogs.length" class="data-table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Recipient</th>
                                        <th>Region</th>
                                        <th>Sender</th>
                                        <th>Template</th>
                                        <th>Queued</th>
                                        <th>Attempted</th>
                                        <th>Sent</th>
                                        <th>Error</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="log in filteredLogs" :key="log.id">
                                        <td>
                                            <span class="status-pill" :class="statusClass(log.status)">
                                                {{ statusLabel(log.status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="table-strong">{{ log.recipient_email }}</span>
                                            <span class="table-sub">{{ log.recipient_organization || '-' }}</span>
                                        </td>
                                        <td>{{ log.segment_name || '-' }}</td>
                                        <td>
                                            <span class="table-strong">{{ log.sender_name || log.sender_email || '-' }}</span>
                                            <span class="table-sub">{{ log.sender_email || '-' }}</span>
                                        </td>
                                        <td>{{ log.template_name || '-' }}</td>
                                        <td>{{ formatDateTime(log.queued_at) }}</td>
                                        <td>{{ formatDateTime(log.attempted_at) }}</td>
                                        <td>{{ formatDateTime(log.sent_at) }}</td>
                                        <td class="error-cell">{{ log.error_message || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="empty-state">
                            {{ hasLogFilters ? 'No delivery log matched the current filters.' : 'No outreach sends queued yet.' }}
                        </div>

                        <div class="action-row">
                            <button type="button" class="toolbar-button toolbar-button-secondary" @click="closeModal">
                                Close
                            </button>
                        </div>
                    </div>
                </template>

                <template v-else-if="activeModal === 'contacts'">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">Supplier Contacts</h3>
                            <p class="modal-copy">Review every imported supplier contact here, filter by region or status, and quickly inspect which companies are active, registered, or unsubscribed.</p>
                        </div>
                    </div>

                    <div class="modal-stack">
                        <div class="modal-card">
                            <form class="filter-toolbar-grid filter-toolbar-grid-contacts" @submit.prevent="applyContactFilters">
                                <label class="field-group">
                                    <span class="field-label">Search</span>
                                    <input v-model="contactSearch" type="search" class="field-input" placeholder="Search company / email / contact name">
                                </label>
                                <label class="field-group">
                                    <span class="field-label">Region</span>
                                    <select v-model="contactRegion" class="field-input">
                                        <option value="">All regions</option>
                                        <option v-for="region in regionOptions" :key="region.key" :value="region.key">
                                            {{ region.label }}
                                        </option>
                                    </select>
                                </label>
                                <label class="field-group">
                                    <span class="field-label">Status</span>
                                    <select v-model="contactStatus" class="field-input">
                                        <option v-for="option in contactStatusOptions" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </label>
                                <div class="filter-toolbar-actions">
                                    <button type="submit" class="toolbar-button toolbar-button-primary" :disabled="isRefreshingContacts">
                                        {{ isRefreshingContacts ? 'Applying...' : 'Apply Filters' }}
                                    </button>
                                    <button type="button" class="toolbar-button toolbar-button-secondary" :disabled="isRefreshingContacts" @click="clearContactFilters">
                                        Clear
                                    </button>
                                </div>
                            </form>

                            <p class="filter-meta">
                                {{ contactSummaryLabel }}
                                Last synced {{ formatDateTime(contactsLastSyncedAt) }}.
                            </p>
                        </div>

                        <div v-if="contactRows.length" class="data-table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Company</th>
                                        <th>Email</th>
                                        <th>Region</th>
                                        <th>Contact Name</th>
                                        <th>Last Sent</th>
                                        <th>Sent</th>
                                        <th>Last Result</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="contact in contactRows" :key="contact.id">
                                        <td>
                                            <span class="status-pill" :class="statusClass(contact.status)">
                                                {{ statusLabel(contact.status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="table-strong">{{ contact.organization_name || '-' }}</span>
                                            <span class="table-sub">{{ contact.source_type === 'manual' ? 'Manual contact' : 'Imported contact' }}</span>
                                        </td>
                                        <td>
                                            <span class="table-strong">{{ contact.email }}</span>
                                        </td>
                                        <td>
                                            <span class="table-strong">{{ contact.region_label || '-' }}</span>
                                            <span class="table-sub">{{ contact.segment_name || '-' }}</span>
                                        </td>
                                        <td>{{ contact.source_name || '-' }}</td>
                                        <td>{{ formatDateTime(contact.last_sent_at) }}</td>
                                        <td>{{ formatNumber(contact.sent_count) }}</td>
                                        <td>{{ contact.last_result || '-' }}</td>
                                        <td>
                                            <div class="actions-cell">
                                                <button type="button" class="action-button action-button-danger" title="Delete" @click="openModal('contact-delete', contact)">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M9 7V4h6v3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M7 7l1 13h8l1-13" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="empty-state">
                            {{ hasContactFilters ? 'No supplier contact matched the current filters.' : 'No supplier contact has been imported yet.' }}
                        </div>

                        <PaginationControls
                            :page="contactCurrentPage"
                            :total-pages="contactTotalPages"
                            :label="contactPaginationLabel"
                            @update:page="fetchContacts"
                        />

                        <div class="action-row">
                            <button type="button" class="toolbar-button toolbar-button-secondary" @click="closeModal">
                                Close
                            </button>
                        </div>
                    </div>
                </template>

                <template v-else-if="activeModal === 'contact'">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">Add Supplier Contact</h3>
                            <p class="modal-copy">Add a single supplier company into one outreach region without uploading a new CSV file.</p>
                        </div>
                    </div>

                    <form class="modal-card" @submit.prevent="submitManualContact">
                        <div class="form-grid">
                            <label class="field-group">
                                <span class="field-label">Region <span class="required-mark">*</span></span>
                                <select
                                    :ref="(element) => registerManualContactField('region_key', element)"
                                    v-model="manualContactForm.region_key"
                                    class="field-input"
                                    :class="manualContactInputClass('region_key')"
                                    required
                                    @change="clearManualContactFieldError('region_key')"
                                >
                                    <option value="">Select region</option>
                                    <option v-for="region in regionOptions" :key="region.key" :value="region.key">
                                        {{ region.label }}
                                    </option>
                                </select>
                                <span v-if="manualContactForm.errors.region_key" class="field-error">{{ manualContactForm.errors.region_key }}</span>
                            </label>
                            <label class="field-group">
                                <span class="field-label">Company Name <span class="required-mark">*</span></span>
                                <input
                                    :ref="(element) => registerManualContactField('organization_name', element)"
                                    v-model="manualContactForm.organization_name"
                                    type="text"
                                    class="field-input"
                                    :class="manualContactInputClass('organization_name')"
                                    placeholder="Ocean Marine Supplies"
                                    required
                                    @input="clearManualContactFieldError('organization_name')"
                                >
                                <span v-if="manualContactForm.errors.organization_name" class="field-error">{{ manualContactForm.errors.organization_name }}</span>
                            </label>
                            <label class="field-group">
                                <span class="field-label">Email Address <span class="required-mark">*</span></span>
                                <input
                                    :ref="(element) => registerManualContactField('email', element)"
                                    v-model="manualContactForm.email"
                                    type="email"
                                    class="field-input"
                                    :class="manualContactInputClass('email')"
                                    placeholder="sales@example.com"
                                    required
                                    @input="clearManualContactFieldError('email')"
                                >
                                <span v-if="manualContactForm.errors.email" class="field-error">{{ manualContactForm.errors.email }}</span>
                            </label>
                            <label class="field-group">
                                <span class="field-label">Contact Name</span>
                                <input v-model="manualContactForm.source_name" type="text" class="field-input" placeholder="Optional">
                                <span v-if="manualContactForm.errors.source_name" class="field-error">{{ manualContactForm.errors.source_name }}</span>
                            </label>
                            <label class="field-group field-group-full">
                                <span class="field-label">Notes</span>
                                <textarea v-model="manualContactForm.notes" class="field-textarea" rows="5" placeholder="Optional internal note about where this supplier came from." />
                                <span v-if="manualContactForm.errors.notes" class="field-error">{{ manualContactForm.errors.notes }}</span>
                            </label>
                        </div>

                        <div v-if="Object.keys(manualContactForm.errors).length" class="field-error-list">
                            <p v-for="(error, errorKey) in manualContactForm.errors" :key="errorKey" class="field-error">
                                {{ error }}
                            </p>
                        </div>

                        <div class="action-row">
                            <button type="button" class="toolbar-button toolbar-button-secondary" @click="closeModal">
                                Close
                            </button>
                            <button type="submit" class="toolbar-button toolbar-button-primary" :disabled="manualContactForm.processing">
                                {{ manualContactForm.processing ? 'Saving...' : 'Add Contact' }}
                            </button>
                        </div>
                    </form>
                </template>

                <template v-else-if="activeModal === 'contact-delete' && activeContact">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">Delete Supplier Contact</h3>
                            <p class="modal-copy">Delete this supplier only if you want to remove it from the outreach database and stop it from appearing in future campaign planning.</p>
                        </div>
                    </div>

                    <div class="modal-card">
                        <div class="region-overview-grid region-overview-grid-tight">
                            <div class="overview-card">
                                <span>Company</span>
                                <strong>{{ activeContact.organization_name || '-' }}</strong>
                            </div>
                            <div class="overview-card">
                                <span>Email</span>
                                <strong>{{ activeContact.email }}</strong>
                            </div>
                            <div class="overview-card">
                                <span>Region</span>
                                <strong>{{ activeContact.region_label || '-' }}</strong>
                            </div>
                            <div class="overview-card">
                                <span>Status</span>
                                <strong>{{ statusLabel(activeContact.status) }}</strong>
                            </div>
                        </div>

                        <p v-if="deleteContactForm.errors.contact" class="field-error">{{ deleteContactForm.errors.contact }}</p>

                        <div class="action-row">
                            <button type="button" class="toolbar-button toolbar-button-secondary" @click="openModal('contacts')">
                                Cancel
                            </button>
                            <button type="button" class="toolbar-button toolbar-button-danger" :disabled="deleteContactForm.processing" @click="deleteContact">
                                {{ deleteContactForm.processing ? 'Deleting...' : 'Delete Contact' }}
                            </button>
                        </div>
                    </div>
                </template>

                <template v-else-if="activeModal === 'view-region' && activeRegion">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">{{ activeRegion.label }} Overview</h3>
                            <p class="modal-copy">Review the saved campaign timing plan for this region in a simpler read-only view.</p>
                        </div>
                    </div>

                    <div class="modal-stack">
                        <div class="modal-card">
                            <div class="modal-card-head">
                                <h4 class="card-title">General Settings</h4>
                            </div>

                            <div class="region-overview-grid region-overview-grid-tight">
                                <div class="overview-card">
                                    <span>Start Campaign On</span>
                                    <strong>{{ formatDate(activeRegion.schedule?.starts_on) }}</strong>
                                </div>
                                <div class="overview-card">
                                    <span>Interval Between Sends</span>
                                    <strong>{{ formatNumber(activeRegion.schedule?.send_interval_minutes) }} min</strong>
                                </div>
                                <div class="overview-card">
                                    <span>Plan Status</span>
                                    <strong>{{ regionStatusLabel(activeRegion) }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="modal-card">
                            <div class="modal-card-head">
                                <h4 class="card-title">Weekly Delivery Plan</h4>
                            </div>

                            <div class="data-table-wrap">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Day</th>
                                            <th>Send</th>
                                            <th>Start</th>
                                            <th>End</th>
                                            <th>Max Sends</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="day in activeRegion.schedule?.weekly_plan ?? []" :key="`${activeRegion.key}-${day.weekday}`">
                                            <td>{{ day.label }}</td>
                                            <td>{{ day.enabled ? 'Yes' : 'No' }}</td>
                                            <td>{{ day.enabled ? (day.start_time || '-') : '-' }}</td>
                                            <td>{{ day.enabled ? (day.end_time || '-') : '-' }}</td>
                                            <td>{{ day.enabled ? formatNumber(day.daily_limit || 0) : '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="action-row">
                            <button type="button" class="toolbar-button toolbar-button-secondary" @click="openContactsModal({ region: activeRegion.key })">
                                Open Contacts
                            </button>
                            <button type="button" class="toolbar-button toolbar-button-secondary" @click="closeModal">
                                Close
                            </button>
                        </div>
                    </div>
                </template>

                <template v-else-if="activeModal === 'edit-region' && activeRegion">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">{{ activeRegion.label }} Plan</h3>
                            <p class="modal-copy">Manage general settings and weekly delivery windows here. Active templates are rotated automatically by system sort order.</p>
                        </div>
                    </div>

                    <form class="modal-stack" @submit.prevent="saveRegionPlan(activeRegion.key)">
                        <div class="modal-card">
                            <div class="modal-card-head">
                                <h4 class="card-title">General Settings</h4>
                            </div>

                            <div class="form-grid">
                                <label class="field-group">
                                    <span class="field-label">Start Campaign On</span>
                                    <input v-model="regionForms[activeRegion.key].starts_on" type="date" class="field-input">
                                </label>
                                <label class="field-group">
                                    <span class="field-label">Interval Between Sends (Minutes)</span>
                                    <input v-model="regionForms[activeRegion.key].send_interval_minutes" type="number" min="1" max="120" class="field-input">
                                </label>
                                <label class="checkbox-row field-group-full">
                                    <input v-model="regionForms[activeRegion.key].is_active" type="checkbox">
                                    <span>Region plan is active</span>
                                </label>
                            </div>
                        </div>

                        <div class="modal-card">
                            <div class="modal-card-head">
                                <h4 class="card-title">Weekly Delivery Plan</h4>
                            </div>

                            <div class="data-table-wrap">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Day</th>
                                            <th>Send</th>
                                            <th>Start</th>
                                            <th>End</th>
                                            <th>Max Sends</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="day in regionForms[activeRegion.key].weekly_plan" :key="`${activeRegion.key}-${day.weekday}`">
                                            <td>{{ day.label }}</td>
                                            <td>
                                                <label class="table-checkbox">
                                                    <input v-model="day.enabled" type="checkbox">
                                                </label>
                                            </td>
                                            <td>
                                                <input v-model="day.start_time" type="time" class="field-input table-field-input" :disabled="!day.enabled">
                                            </td>
                                            <td>
                                                <input v-model="day.end_time" type="time" class="field-input table-field-input" :disabled="!day.enabled">
                                            </td>
                                            <td>
                                                <input v-model="day.daily_limit" type="number" min="1" max="5000" class="field-input table-field-input" :disabled="!day.enabled">
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div v-if="Object.keys(regionForms[activeRegion.key].errors).length" class="field-error-list">
                            <p v-for="(error, errorKey) in regionForms[activeRegion.key].errors" :key="errorKey" class="field-error">
                                {{ error }}
                            </p>
                        </div>

                        <div class="action-row">
                            <button type="button" class="toolbar-button toolbar-button-secondary" @click="closeModal">
                                Close
                            </button>
                            <button type="submit" class="toolbar-button toolbar-button-primary" :disabled="regionForms[activeRegion.key].processing">
                                {{ regionForms[activeRegion.key].processing ? 'Saving...' : 'Save Region Plan' }}
                            </button>
                        </div>
                    </form>
                </template>

                <template v-else-if="activeModal === 'delete-region' && activeRegion">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">Clear {{ activeRegion.label }} Plan</h3>
                            <p class="modal-copy">
                                This delete action only clears the saved outreach plan for this region. Imported contacts stay in the system.
                            </p>
                        </div>
                    </div>

                    <div class="modal-card">
                        <div class="region-overview-grid region-overview-grid-tight">
                            <div class="overview-card">
                                <span>Region</span>
                                <strong>{{ activeRegion.label }}</strong>
                            </div>
                            <div class="overview-card">
                                <span>Contacts Kept</span>
                                <strong>{{ formatNumber(activeRegion.contacts_count) }}</strong>
                            </div>
                            <div class="overview-card">
                                <span>Current Status</span>
                                <strong>{{ regionStatusLabel(activeRegion) }}</strong>
                            </div>
                        </div>

                        <div class="action-row">
                            <button type="button" class="toolbar-button toolbar-button-secondary" @click="closeModal">
                                Cancel
                            </button>
                            <button type="button" class="toolbar-button toolbar-button-danger" :disabled="deletePlanForm.processing" @click="deleteRegionPlan">
                                {{ deletePlanForm.processing ? 'Clearing...' : 'Delete Region Plan' }}
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AdminDashboardShell>
</template>

<style scoped>
.surface-panel{padding:32px 36px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.78);box-shadow:0 24px 44px rgba(15,23,42,.08)}
.summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px}
.summary-card{display:grid;gap:10px;padding:18px 20px}
.summary-card-actionable{align-content:space-between}
.summary-label{margin:0 0 8px;color:#64748b;font-size:.84rem;font-weight:600}
.summary-value{margin:0;color:#0f172a;font-size:1.7rem;font-weight:800;letter-spacing:-.03em}
.summary-card-foot{display:flex;justify-content:flex-start}
.summary-card-action{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:0 12px;border:1px solid rgba(37,99,235,.16);border-radius:999px;background:#eff6ff;color:#2563eb;font-size:.8rem;font-weight:700}
.table-panel{display:grid;gap:18px}
.table-toolbar{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}
.table-intro{display:grid;gap:8px}
.table-intro :deep(.directory-section-title){margin:0}
.section-copy{margin:0;color:#64748b;font-size:.9rem;line-height:1.7;max-width:72ch}
.toolbar-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end}
.toolbar-button{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border-radius:10px;border:1px solid transparent;font-size:.92rem;font-weight:600;cursor:pointer;text-decoration:none}
.toolbar-button-primary{background:#2563eb;border-color:#2563eb;color:#fff;box-shadow:0 12px 24px rgba(37,99,235,.18)}
.toolbar-button-secondary{background:#fff;border-color:rgba(148,163,184,.38);color:#0f172a}
.toolbar-button-danger{background:#ef4444;border-color:#ef4444;color:#fff;box-shadow:0 12px 24px rgba(239,68,68,.18)}
.toolbar-button:disabled{opacity:.65;cursor:not-allowed}
.dashboard-table-wrap{margin-top:16px;overflow-x:auto}
.dashboard-table{width:100%;border-collapse:collapse;min-width:1080px}
.dashboard-table thead th{padding:16px 14px;background:#f4f7fb;color:#0f172a;font-size:.82rem;font-weight:700;text-align:left;white-space:nowrap}
.dashboard-table tbody td{padding:16px 14px;border-top:1px solid rgba(4,21,31,.06);color:#0f172a;font-size:.94rem;line-height:1.55;vertical-align:top}
.order-index-cell{font-weight:600;color:#0f172a}
.identity-stack,.metric-stack{display:grid;gap:5px}
.identity-primary,.metric-primary{color:#0f172a;font-size:.94rem;font-weight:500;line-height:1.45}
.identity-secondary,.metric-secondary{color:#64748b;font-size:.88rem;line-height:1.45}
.status-dot-cell{vertical-align:middle !important;text-align:center !important}
.status-dot{display:inline-block;width:12px;height:12px;border-radius:999px;background:#cbd5e1}
.status-dot.is-good{background:#22c55e;box-shadow:0 0 0 4px rgba(34,197,94,.14)}
.status-dot.is-neutral{background:#94a3b8;box-shadow:0 0 0 4px rgba(148,163,184,.18)}
.status-pill{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:0 12px;border-radius:10px;font-size:.8rem;font-weight:600;white-space:nowrap}
.status-pill.is-good{background:rgba(34,197,94,.12);color:#15803d}
.status-pill.is-pending{background:rgba(59,130,246,.12);color:#2563eb}
.status-pill.is-neutral{background:rgba(15,23,42,.06);color:#475569}
.status-pill.is-bad{background:rgba(239,68,68,.12);color:#dc2626}
.actions-cell{display:flex;align-items:center;gap:8px}
.action-button{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:0;background:transparent;padding:0;color:#0f172a;transition:color .2s ease, background-color .2s ease, opacity .2s ease}
.action-button svg{width:17px;height:17px;flex:0 0 17px}
.action-button.is-loading{color:#2563eb;background:#dbeafe;border-radius:999px}
.action-button-danger{color:#ef4444}
.spin-icon{animation:spin 0.85s linear infinite}
@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
.admin-modal-backdrop{position:fixed;inset:0;z-index:1600;display:flex;align-items:flex-start;justify-content:center;padding:24px 20px;background:rgba(4,21,31,.58);backdrop-filter:blur(10px);overflow-y:auto}
.admin-modal{position:relative;width:min(760px,100%);max-height:calc(100vh - 48px);overflow:auto;padding:24px;border:1px solid rgba(4,21,31,.08);border-radius:16px;background:#fff;box-shadow:0 30px 60px rgba(15,23,42,.16)}
.admin-modal-wide{width:min(1120px,100%)}
.admin-modal-close{position:absolute;top:16px;right:16px;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff;color:#0f172a;font-size:1.45rem;line-height:1}
.modal-head,.modal-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding-right:56px}
.modal-title{margin:0;color:#0f172a;font-size:1.18rem;font-weight:800}
.modal-copy{margin:8px 0 0;color:#64748b;font-size:.94rem;line-height:1.7;max-width:72ch}
.modal-stack,.field-error-list{display:grid;gap:16px}
.modal-card{display:grid;gap:16px;padding:18px;border:1px solid rgba(15,23,42,.08);border-radius:18px;background:#f8fbfc}
.filter-toolbar-grid{display:grid;gap:14px;align-items:end}
.filter-toolbar-grid-contacts{grid-template-columns:minmax(220px,2fr) minmax(180px,1fr) minmax(180px,1fr) auto}
.filter-toolbar-grid-monitor{grid-template-columns:minmax(220px,2fr) minmax(180px,1fr)}
.filter-toolbar-actions{display:flex;align-items:center;gap:10px;justify-content:flex-end;flex-wrap:wrap}
.filter-meta{margin:0;color:#64748b;font-size:.84rem;line-height:1.7}
.monitor-stats-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.monitor-stat-chip{display:grid;gap:6px;padding:12px 14px;border:1px solid rgba(15,23,42,.08);border-radius:14px;background:#fff}
.monitor-stat-chip span{color:#64748b;font-size:.8rem;font-weight:600}
.monitor-stat-chip strong{color:#0f172a;font-size:1rem;font-weight:800}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.single-column-grid{grid-template-columns:1fr}
.field-group{display:grid;gap:8px}
.field-group-full{grid-column:1 / -1}
.field-label{font-size:.82rem;font-weight:700;color:#334155}
.required-mark{color:#dc2626;font-weight:800}
.field-input,.field-textarea{width:100%;border:1px solid rgba(15,23,42,.12);border-radius:12px;background:#fff;color:#0f172a;font:inherit;padding:11px 13px}
.field-input.has-error,.field-textarea.has-error{border-color:#dc2626;background:#fff7f7;box-shadow:0 0 0 1px rgba(220,38,38,.08)}
.field-textarea{resize:vertical;min-height:140px}
.field-help{margin:0;color:#64748b;font-size:.84rem}
.field-error{margin:0;color:#b91c1c;font-size:.82rem}
.checkbox-row{display:flex;align-items:center;gap:10px;color:#334155;font-size:.92rem}
.compact-checkbox{justify-content:flex-end;font-size:.84rem}
.action-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.data-table-wrap{overflow:auto;border:1px solid rgba(15,23,42,.08);border-radius:14px;background:#fff}
.data-table{width:100%;border-collapse:collapse;min-width:880px}
.data-table th,.data-table td{padding:14px 16px;border-bottom:1px solid rgba(15,23,42,.08);text-align:left;vertical-align:top;font-size:.9rem}
.data-table th{background:#f8fbfc;color:#475569;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em}
.table-strong{display:block;color:#0f172a;font-weight:700}
.table-sub{display:block;margin-top:4px;color:#64748b;font-size:.82rem}
.table-subject-preview{display:block;max-width:360px;color:#0f172a;line-height:1.5}
.table-field-input{min-width:120px}
.table-checkbox{display:inline-flex;align-items:center;justify-content:center;width:100%}
.error-cell{max-width:280px;color:#7f1d1d}
.overview-card,.weekday-card,.weekday-preview-item{padding:14px 16px;border-radius:16px;border:1px solid rgba(15,23,42,.08);background:#fff}
.overview-card,.weekday-preview-item{display:grid;gap:6px}
.overview-card strong,.weekday-preview-item strong{color:#0f172a;font-weight:700}
.overview-card span,.weekday-preview-item span{color:#64748b;font-size:.88rem}
.template-preview-box{padding:14px 16px;border:1px solid rgba(15,23,42,.08);border-radius:14px;background:#fff;color:#0f172a;line-height:1.7}
.template-preview-body{white-space:pre-wrap}
.region-overview-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.region-overview-grid-tight{grid-template-columns:repeat(4,minmax(0,1fr))}
.weekday-preview-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.empty-state{padding:26px 18px;border:1px dashed rgba(15,23,42,.12);border-radius:16px;background:#f8fbfc;color:#64748b;text-align:center}
@media (max-width: 1080px){
    .region-overview-grid,.region-overview-grid-tight,.weekday-preview-grid,.monitor-stats-row{grid-template-columns:1fr 1fr}
    .filter-toolbar-grid-contacts{grid-template-columns:1fr 1fr}
}
@media (max-width: 900px){
    .table-toolbar,.modal-head,.modal-card-head{flex-direction:column;align-items:stretch}
    .toolbar-actions{justify-content:flex-start}
    .filter-toolbar-grid-contacts,.filter-toolbar-grid-monitor{grid-template-columns:1fr}
    .filter-toolbar-actions{justify-content:flex-start}
}
@media (max-width: 720px){
    .surface-panel{padding:24px}
    .summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .toolbar-actions,.action-row{width:100%}
    .toolbar-button{width:100%}
    .admin-modal-backdrop{padding:16px}
    .admin-modal{width:100%;max-height:calc(100vh - 32px);padding:20px}
    .form-grid,.region-overview-grid,.region-overview-grid-tight,.weekday-preview-grid,.monitor-stats-row{grid-template-columns:1fr}
}
</style>
