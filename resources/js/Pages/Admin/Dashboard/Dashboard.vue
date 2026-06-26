<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminDashboardShell from './Shell.vue';
import SupplierRegistrationsTab from './Components/SupplierRegistrationsTab.vue';
import UsersTab from './Components/UsersTab.vue';
import DeleteModal from './Modals/DeleteModal.vue';
import RemovalRequestModal from './Modals/RemovalRequestModal.vue';
import RejectModal from './Modals/RejectModal.vue';
import RejectionFeedbackModal from './Modals/RejectionFeedbackModal.vue';
import StatusModal from './Modals/StatusModal.vue';
import UpdateRequestDiffModal from './Modals/UpdateRequestDiffModal.vue';
import VerificationMailHistoryModal from './Modals/VerificationMailHistoryModal.vue';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
    activeTab: {
        type: String,
        required: true,
    },
    userTable: {
        type: Object,
        required: true,
    },
    businessTable: {
        type: Object,
        required: true,
    },
});

const modalType = ref(null);
const activeUser = ref(null);
const deleteContext = ref('user');
const activeTab = computed(() => props.activeTab ?? 'businesses');

const profileForm = useForm({
    name: '',
    email: '',
    role: 'buyer',
    company_name: '',
    country: '',
    phone: '',
    whatsapp_number: '',
    company_description: '',
    email_verified: false,
});

const businessForm = useForm({
    company_name: '',
    country: '',
    company_city: '',
    company_address_line: '',
    phone: '',
    contact_email: '',
    website_url: '',
});

const rejectForm = useForm({
    action: 'reject',
    rejection_reason: '',
    rejection_note: '',
    rejection_fields: [],
});
const removalReviewForm = useForm({
    action: 'reject',
    note: '',
});

const copy = computed(() => ({
    title: 'Admin Dashboard',
    eyebrow: 'Management area',
    subtitle: 'Manage users, businesses, submissions and feedback flows from this panel.',
    usersTab: 'Users',
    businessesTab: 'Supplier Company Registrations',
    usersTitle: 'Users',
    businessesTitle: 'Supplier Company Registrations',
    userName: 'User',
    company: 'Business',
    email: 'Email',
    role: 'Role',
    verification: 'Verification',
    registeredAt: 'Registered At',
    documents: 'Documents',
    status: 'Status',
    action: 'Action',
    verified: 'Verified',
    notVerified: 'Not verified',
    documentsSubmitted: 'Documents submitted',
    documentsMissing: 'Documents missing',
    notRequired: 'Not required',
    approve: 'Approve',
    reject: 'Reject',
    view: 'View',
    edit: 'Edit',
    delete: 'Delete',
    save: 'Save',
    saving: 'Saving...',
    deleting: 'Deleting...',
    removalRequest: 'Removal request',
    rejectionReason: 'Rejection reason',
    rejectionNote: 'Admin note',
    rejectionFields: 'Fields to revise',
    modalClose: 'Close',
    cancel: 'Cancel',
    confirmDelete: 'Confirm Delete',
    reasons: {
        documents_incomplete: 'Documents are incomplete or insufficient',
        information_mismatch: 'The submitted information does not match',
        service_scope_unclear: 'The service scope is unclear',
        compliance_issue: 'There is a compliance or verification issue',
        other: 'Other',
    },
    fields: {
        company_name: 'Business name',
        service_category_ids: 'Category and subcategory',
        service_brand_ids: 'Brands',
        service_country_codes: 'Service countries',
        service_ports_by_country: 'Service ports',
        country: 'Country',
        company_city: 'City',
        company_district: 'District',
        company_neighborhood: 'Neighborhood',
        company_postal_code: 'Postal code',
        company_address_line: 'Address',
        phone: 'Phone',
        landline_phone: 'Landline',
        contact_email: 'Contact email',
        website_url: 'Website',
        whatsapp_number: 'WhatsApp',
        telegram_url: 'Telegram',
        instagram_url: 'Instagram',
        linkedin_url: 'LinkedIn',
        facebook_url: 'Facebook',
        twitter_url: 'X / Twitter',
        company_overview: 'Company overview',
        port_coverage: 'Port coverage',
        registration_number: 'Registration number',
        company_logo: 'Logo',
        company_registration_documents: 'Company registration documents',
        tax_certificate_documents: 'Tax documents',
        service_authorization_documents: 'Authorization documents',
        official_documents: 'Official documents',
    },
    roles: {
        buyer: 'Buyer',
        seller: 'Supplier',
        admin: 'Admin',
    },
    statuses: {
        approved: 'Approved',
        pending: 'Pending',
        rejected: 'Rejected',
        update_pending: 'Update pending',
        update_rejected: 'Update rejected',
    },
    userViewTitle: 'User Details',
    userEditTitle: 'Edit User',
    businessViewTitle: 'Business Details',
    businessEditTitle: 'Edit Business',
    deleteUserTitle: 'Are you sure you want to delete this record?',
    deleteUserText: 'This action cannot be undone. The user and the business record will be removed from the system.',
    deleteBusinessTitle: 'Are you sure you want to remove this business record?',
    deleteBusinessText: 'This action removes the business record, service coverage and application history, while keeping the user account in place.',
    rejectTitle: 'Add rejection feedback',
    rejectText: 'Make it clear what the supplier should fix. Share a reason, an explanation and the related fields.',
    removalRequestDecisionText: 'If approved, only the business record will be removed. The user account will remain active in the system.',
    removalRejectPlaceholder: 'Explain why you are rejecting this removal request.',
    fullName: 'Full name',
    companyName: 'Company Name',
    country: 'Country',
    city: 'City',
    address: 'Address',
    phone: 'Phone',
    whatsapp: 'WhatsApp',
    companyDescription: 'Company Description',
    website: 'Website',
    contactEmail: 'Contact email',
    emailVerificationStatus: 'Email verification',
    placeholderReason: 'Select a reason',
    rolesField: 'Role',
    noValue: '-',
    userSearchPlaceholder: 'Search by user, email or business name',
    businessSearchPlaceholder: 'Search by business, contact person, country or status',
    sortLabel: 'Sort',
    sortLatest: 'Latest first',
    sortOldest: 'Oldest first',
    sortNameAsc: 'Name A-Z',
    sortNameDesc: 'Name Z-A',
    sortEmailAsc: 'Email A-Z',
    sortCompanyAsc: 'Company A-Z',
    sortCompanyDesc: 'Company Z-A',
    sortStatus: 'By status',
    filterAll: 'All',
    filterPending: 'Pending',
    filterApproved: 'Approved',
    filterRejected: 'Rejected',
    filterUpdatePending: 'Update Pending',
    filterRemoval: 'Removal Requests',
    updateRequest: 'Update request',
    updateRejected: 'Update rejected',
    updateDiffIntro: 'Review the changed fields with their previous and new values.',
    previousValue: 'Previous value',
    newValue: 'New value',
    reviewUpdate: 'Review update changes',
    reviewFeedback: 'Review rejection feedback',
    rejectionFeedbackIntro: 'Review the latest admin feedback shared for this supplier application and see exactly which areas need revision.',
    updateFeedbackIntro: 'Review the latest admin feedback shared for this supplier update request and see which submitted changes were rejected.',
    feedbackType: 'Feedback Type',
    feedbackSentAt: 'Reviewed At',
    feedbackTypeApplication: 'Initial supplier application',
    feedbackTypeUpdate: 'Supplier update request',
    noAdminNote: 'No admin note was added for this feedback.',
    noRevisionFields: 'No specific revision fields were marked for this feedback.',
    verificationMailHistory: 'Verification Mail History',
    verificationMailHistoryIntro: 'Review which supplier verification onboarding emails were already sent to this supplier account and when each step happened.',
    reviewVerificationMailHistory: 'Review verification mail history',
    emailVerifiedAt: 'Email Verified',
    latestMailSent: 'Latest Mail Sent',
    reminderFlowStatus: 'Reminder Flow Status',
    sentAt: 'Sent At',
    mailNotSentYet: 'Not sent yet',
    verificationMailSent: 'Sent',
    verificationMailPending: 'Pending',
    onboardingMailTitle: 'Initial verification mail',
    reminder24hTitle: '24-hour reminder',
    reminder72hTitle: '72-hour final reminder',
    onboardingMailDescription: 'Sent immediately after email verification so the supplier can complete business verification before public listing and RFQ offers.',
    reminder24hDescription: 'Sent 24 hours after email verification if the supplier verification form is still not submitted.',
    reminder72hDescription: 'Sent 72 hours after email verification if the supplier verification form is still not submitted. No further reminder is sent after this step.',
    reminderStatusNotVerified: 'Email not verified yet',
    reminderStatusSubmitted: 'Verification submitted, reminder flow stopped',
    reminderStatusRejected: 'Application rejected after submission',
    reminderStatusFinalSent: 'Final 72-hour reminder sent, reminder flow stopped',
    reminderStatus24hSent: '24-hour reminder sent, waiting for submission',
    reminderStatusOnboardingSent: 'Initial verification mail sent, waiting for submission',
    reminderStatusWaiting: 'Waiting to send onboarding mail',
}));

const regularUsers = computed(() => props.userTable.data ?? []);
const businessUsers = computed(() => props.businessTable.data ?? []);
const feedbackFields = computed(() => Object.entries(copy.value.fields).map(([key, label]) => ({ key, label })));

const roleLabel = (role) => copy.value.roles[role] ?? role;
const statusLabel = (status) => copy.value.statuses[status] ?? status;
const businessStatusKey = (user) => user.approval_status;
const businessStatusLabel = (user) => statusLabel(businessStatusKey(user));
const businessStatusClass = (user) => {
    const key = businessStatusKey(user);

    return `is-${key}`;
};
const verificationLabel = (user) => user.email_verified_at ? copy.value.verified : copy.value.notVerified;
const documentLabel = (user) => {
    if (user.role !== 'seller') return copy.value.notRequired;
    return user.seller_verification_submitted_at ? copy.value.documentsSubmitted : copy.value.documentsMissing;
};

const removalReasonLabel = (user) => {
    const labels = {
        business_closed: 'Business closed',
        duplicate_listing: 'Duplicate listing',
        wrong_account: 'Wrong account',
        not_needed: 'No longer needed',
        other: 'Other',
    };

    return user.seller_removal_request_reason ? labels[user.seller_removal_request_reason] ?? user.seller_removal_request_reason : null;
};
const removalRequestPayload = (user) => ({
    reason: removalReasonLabel(user) ?? '',
    note: user.seller_removal_request_note ?? '',
});

const mappedRejectionFields = (fields = []) => fields
    .map((field) => copy.value.fields[field] ?? field)
    .filter(Boolean);

const reviewFeedbackPayload = (user) => {
    if (user.seller_update_request_status === 'rejected') {
        return {
            eyebrow: copy.value.reviewFeedback,
            title: copy.value.updateRejected,
            intro: copy.value.updateFeedbackIntro,
            feedback_type: copy.value.feedbackTypeUpdate,
            reviewed_at: formatDate(user.seller_update_rejected_at),
            reason: copy.value.reasons[user.seller_update_rejection_reason] ?? user.seller_update_rejection_reason ?? '',
            note: user.seller_update_rejection_note ?? '',
            fields: mappedRejectionFields(user.seller_update_rejection_fields ?? []),
        };
    }

    return {
        eyebrow: copy.value.reviewFeedback,
        title: copy.value.rejectionReason,
        intro: copy.value.rejectionFeedbackIntro,
        feedback_type: copy.value.feedbackTypeApplication,
        reviewed_at: formatDate(user.seller_rejected_at),
        reason: copy.value.reasons[user.seller_rejection_reason] ?? user.seller_rejection_reason ?? '',
        note: user.seller_rejection_note ?? '',
        fields: mappedRejectionFields(user.seller_rejection_fields ?? []),
    };
};
const updateFieldText = (user) => {
    const count = user.update_changed_fields?.length ?? 0;

    if (count === 0) {
        return 'Pending review';
    }

    return `${count} fields changed`;
};
const formatDiffValue = (value) => {
    if (Array.isArray(value)) {
        const items = value
            .map((item) => String(item ?? '').trim())
            .filter(Boolean);

        return items.length ? items.join('\n') : copy.value.noValue;
    }

    if (value && typeof value === 'object') {
        const entries = Object.entries(value)
            .map(([key, itemValue]) => {
                const rendered = Array.isArray(itemValue)
                    ? itemValue.map((entry) => String(entry ?? '').trim()).filter(Boolean).join(', ')
                    : String(itemValue ?? '').trim();

                return rendered ? `${key}: ${rendered}` : '';
            })
            .filter(Boolean);

        return entries.length ? entries.join('\n') : copy.value.noValue;
    }

    const normalized = String(value ?? '').trim();
    return normalized || copy.value.noValue;
};
const updateDiffItems = (user) => Object.entries(user.update_diff ?? {}).map(([key, value]) => ({
    key,
    label: copy.value.fields[key] ?? key,
    from: formatDiffValue(value?.from),
    to: formatDiffValue(value?.to),
}));

const formatDate = (value) => {
    if (!value) {
        return copy.value.noValue;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
};

const formatDateTime = (value) => {
    if (!value) {
        return copy.value.noValue;
    }

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
};

const latestVerificationMailTimestamp = (user) => {
    const candidates = [
        user?.seller_verification_onboarding_sent_at,
        user?.seller_verification_24h_reminder_sent_at,
        user?.seller_verification_72h_reminder_sent_at,
    ]
        .filter(Boolean)
        .map((value) => new Date(value))
        .filter((value) => !Number.isNaN(value.getTime()))
        .sort((left, right) => right.getTime() - left.getTime());

    return candidates[0] ? candidates[0].toISOString() : null;
};

const reminderFlowStatusText = (user) => {
    if (!user?.email_verified_at) {
        return copy.value.reminderStatusNotVerified;
    }

    if (user?.seller_verification_submitted_at) {
        return user?.approval_status === 'rejected'
            ? copy.value.reminderStatusRejected
            : copy.value.reminderStatusSubmitted;
    }

    if (user?.seller_verification_72h_reminder_sent_at) {
        return copy.value.reminderStatusFinalSent;
    }

    if (user?.seller_verification_24h_reminder_sent_at) {
        return copy.value.reminderStatus24hSent;
    }

    if (user?.seller_verification_onboarding_sent_at) {
        return copy.value.reminderStatusOnboardingSent;
    }

    return copy.value.reminderStatusWaiting;
};

const verificationMailHistoryPayload = (user) => ({
    summary: [
        {
            label: copy.value.emailVerifiedAt,
            value: formatDateTime(user?.email_verified_at),
        },
        {
            label: copy.value.latestMailSent,
            value: formatDateTime(latestVerificationMailTimestamp(user)),
        },
        {
            label: copy.value.reminderFlowStatus,
            value: reminderFlowStatusText(user),
        },
    ],
    sequence: [
        {
            key: 'onboarding',
            title: copy.value.onboardingMailTitle,
            description: copy.value.onboardingMailDescription,
            status: user?.seller_verification_onboarding_sent_at ? copy.value.verificationMailSent : copy.value.verificationMailPending,
            sent_at: user?.seller_verification_onboarding_sent_at ? formatDateTime(user.seller_verification_onboarding_sent_at) : copy.value.mailNotSentYet,
            is_sent: Boolean(user?.seller_verification_onboarding_sent_at),
        },
        {
            key: '24h',
            title: copy.value.reminder24hTitle,
            description: copy.value.reminder24hDescription,
            status: user?.seller_verification_24h_reminder_sent_at ? copy.value.verificationMailSent : copy.value.verificationMailPending,
            sent_at: user?.seller_verification_24h_reminder_sent_at ? formatDateTime(user.seller_verification_24h_reminder_sent_at) : copy.value.mailNotSentYet,
            is_sent: Boolean(user?.seller_verification_24h_reminder_sent_at),
        },
        {
            key: '72h',
            title: copy.value.reminder72hTitle,
            description: copy.value.reminder72hDescription,
            status: user?.seller_verification_72h_reminder_sent_at ? copy.value.verificationMailSent : copy.value.verificationMailPending,
            sent_at: user?.seller_verification_72h_reminder_sent_at ? formatDateTime(user.seller_verification_72h_reminder_sent_at) : copy.value.mailNotSentYet,
            is_sent: Boolean(user?.seller_verification_72h_reminder_sent_at),
        },
    ],
});

const closeModal = () => {
    modalType.value = null;
    activeUser.value = null;
    deleteContext.value = 'user';
    profileForm.clearErrors();
    businessForm.clearErrors();
    rejectForm.clearErrors();
    removalReviewForm.clearErrors();
    removalReviewForm.reset();
};

const openUserView = (user) => {
    activeUser.value = user;
    modalType.value = 'view-user';
};

const openBusinessView = (user) => {
    activeUser.value = user;
    modalType.value = 'view-business';
};

const openUserEdit = (user) => {
    activeUser.value = user;
    profileForm.reset();
    profileForm.name = user.name ?? '';
    profileForm.email = user.email ?? '';
    profileForm.role = user.role ?? 'buyer';
    profileForm.company_name = user.company_name ?? '';
    profileForm.country = user.country ?? '';
    profileForm.phone = user.phone ?? '';
    profileForm.whatsapp_number = user.whatsapp_number ?? '';
    profileForm.company_description = user.company_description ?? '';
    profileForm.email_verified = Boolean(user.email_verified_at);
    modalType.value = 'edit-user';
};

const openBusinessEdit = (user) => {
    activeUser.value = user;
    businessForm.reset();
    businessForm.company_name = user.company_name ?? '';
    businessForm.country = user.country ?? '';
    businessForm.company_city = user.company_city ?? '';
    businessForm.company_address_line = user.company_address_line ?? '';
    businessForm.phone = user.phone ?? '';
    businessForm.contact_email = user.contact_email ?? '';
    businessForm.website_url = user.website_url ?? '';
    modalType.value = 'edit-business';
};

const openDeleteModal = (user, context = 'user') => {
    activeUser.value = user;
    deleteContext.value = context;
    modalType.value = 'delete-record';
};

const openRejectModal = (user) => {
    activeUser.value = user;
    rejectForm.reset();
    rejectForm.action = 'reject';
    rejectForm.rejection_reason = user.seller_rejection_reason ?? '';
    rejectForm.rejection_note = user.seller_rejection_note ?? '';
    rejectForm.rejection_fields = [...(user.seller_rejection_fields ?? [])];
    modalType.value = 'reject-business';
};

const openStatusModal = (user) => {
    activeUser.value = user;
    modalType.value = 'status-business';
};
const openRejectionFeedbackModal = (user) => {
    activeUser.value = user;
    modalType.value = 'rejection-feedback';
};
const openRemovalRequestModal = (user) => {
    activeUser.value = user;
    removalReviewForm.reset();
    removalReviewForm.action = 'reject';
    removalReviewForm.note = '';
    modalType.value = 'removal-request';
};

const openUpdateDiffModal = (user) => {
    activeUser.value = user;
    modalType.value = 'update-diff';
};

const openVerificationMailHistoryModal = (user) => {
    activeUser.value = user;
    modalType.value = 'verification-mail-history';
};

const submitUserEdit = () => {
    if (!activeUser.value) return;

    profileForm.patch(`/admin/users/${activeUser.value.id}/profile`, {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const submitBusinessEdit = () => {
    if (!activeUser.value) return;

    businessForm.patch(`/admin/users/${activeUser.value.id}/business`, {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const submitReject = () => {
    if (!activeUser.value) return;

    rejectForm.patch(`/admin/users/${activeUser.value.id}/approval`, {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const confirmDelete = () => {
    if (!activeUser.value) return;

    const url = deleteContext.value === 'business'
        ? `/admin/users/${activeUser.value.id}/business`
        : `/admin/users/${activeUser.value.id}`;

    router.delete(url, {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};
const approveRemovalRequest = () => {
    if (!activeUser.value) return;

    removalReviewForm.transform(() => ({ action: 'approve', note: '' })).patch(`/admin/users/${activeUser.value.id}/removal-request`, {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};
const rejectRemovalRequest = () => {
    if (!activeUser.value) return;

    removalReviewForm.transform((data) => ({ ...data, action: 'reject' })).patch(`/admin/users/${activeUser.value.id}/removal-request`, {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const updateApproval = (id, action) => {
    router.patch(`/admin/users/${id}/approval`, { action }, { preserveScroll: true });
};

const chooseBusinessStatus = (status) => {
    if (!activeUser.value) return;

    if (status === 'approved') {
        updateApproval(activeUser.value.id, 'approve');
        closeModal();
        return;
    }

    if (status === 'rejected') {
        openRejectModal(activeUser.value);
    }
};
</script>

<template>
    <AdminDashboardShell :dashboard="dashboard" :title="copy.title" :active-tab="activeTab">
        <section class="admin-dashboard-shell">
            <UsersTab
                v-if="activeTab === 'users'"
                :records="userTable.data"
                :meta="userTable.meta"
                :filters="userTable.filters"
                :copy="copy"
                :role-label="roleLabel"
                :verification-label="verificationLabel"
                :status-label="statusLabel"
                :business-status-class="businessStatusClass"
                @view="openUserView"
                @edit="openUserEdit"
                @delete="openDeleteModal($event, 'user')"
            />

            <SupplierRegistrationsTab
                v-else
                :records="businessTable.data"
                :meta="businessTable.meta"
                :filters="businessTable.filters"
                :counts="businessTable.counts"
                :copy="copy"
                :document-label="documentLabel"
                :business-status-label="businessStatusLabel"
                :business-status-class="businessStatusClass"
                :removal-reason-label="removalReasonLabel"
                :update-field-text="updateFieldText"
                @open-status="openStatusModal"
                @open-removal="openRemovalRequestModal"
                @open-feedback="openRejectionFeedbackModal"
                @open-update-diff="openUpdateDiffModal"
                @open-mail-history="openVerificationMailHistoryModal"
                @view="openBusinessView"
                @edit="openBusinessEdit"
                @delete="openDeleteModal($event, 'business')"
            />
        </section>

        <Transition name="admin-fade">
            <div v-if="modalType && !['delete-record', 'status-business', 'reject-business', 'update-diff', 'rejection-feedback', 'removal-request'].includes(modalType)" class="admin-modal-backdrop" @click="closeModal">
                <div class="admin-modal" @click.stop>
                    <button type="button" class="admin-modal-close" @click="closeModal">&times;</button>

                    <template v-if="modalType === 'view-user' && activeUser">
                        <p class="directory-eyebrow">{{ copy.usersTitle }}</p>
                        <h2 class="directory-section-title">{{ copy.userViewTitle }}</h2>
                        <div class="detail-grid">
                            <div class="detail-item"><span>{{ copy.fullName }}</span><strong>{{ activeUser.name || copy.noValue }}</strong></div>
                            <div class="detail-item"><span>{{ copy.email }}</span><strong>{{ activeUser.email || copy.noValue }}</strong></div>
                            <div class="detail-item"><span>{{ copy.companyName }}</span><strong>{{ activeUser.company_name || copy.noValue }}</strong></div>
                            <div class="detail-item"><span>{{ copy.country }}</span><strong>{{ activeUser.country || copy.noValue }}</strong></div>
                            <div class="detail-item"><span>{{ copy.phone }}</span><strong>{{ activeUser.phone || copy.noValue }}</strong></div>
                            <div class="detail-item"><span>{{ copy.whatsapp }}</span><strong>{{ activeUser.whatsapp_number || copy.noValue }}</strong></div>
                            <div class="detail-item"><span>{{ copy.rolesField }}</span><strong>{{ roleLabel(activeUser.role) }}</strong></div>
                            <div class="detail-item"><span>{{ copy.verification }}</span><strong>{{ verificationLabel(activeUser) }}</strong></div>
                            <div class="detail-item"><span>{{ copy.registeredAt }}</span><strong>{{ formatDate(activeUser.created_at) }}</strong></div>
                            <div class="detail-item"><span>{{ copy.status }}</span><strong>{{ statusLabel(activeUser.approval_status) }}</strong></div>
                            <div class="detail-item detail-item-wide"><span>{{ copy.companyDescription }}</span><strong>{{ activeUser.company_description || copy.noValue }}</strong></div>
                        </div>
                    </template>

                    <template v-else-if="modalType === 'edit-user' && activeUser">
                        <p class="directory-eyebrow">{{ copy.usersTitle }}</p>
                        <h2 class="directory-section-title">{{ copy.userEditTitle }}</h2>
                        <form class="admin-modal-form" @submit.prevent="submitUserEdit">
                            <label class="admin-field">
                                <span>{{ copy.fullName }}</span>
                                <input v-model="profileForm.name" type="text">
                                <small v-if="profileForm.errors.name" class="admin-error">{{ profileForm.errors.name }}</small>
                            </label>
                            <label class="admin-field">
                                <span>{{ copy.email }}</span>
                                <input v-model="profileForm.email" type="email">
                                <small v-if="profileForm.errors.email" class="admin-error">{{ profileForm.errors.email }}</small>
                            </label>
                            <label class="admin-field">
                                <span>{{ copy.companyName }}</span>
                                <input v-model="profileForm.company_name" type="text">
                                <small v-if="profileForm.errors.company_name" class="admin-error">{{ profileForm.errors.company_name }}</small>
                            </label>
                            <label class="admin-field">
                                <span>{{ copy.country }}</span>
                                <input v-model="profileForm.country" type="text">
                                <small v-if="profileForm.errors.country" class="admin-error">{{ profileForm.errors.country }}</small>
                            </label>
                            <label class="admin-field">
                                <span>{{ copy.phone }}</span>
                                <input v-model="profileForm.phone" type="text">
                                <small v-if="profileForm.errors.phone" class="admin-error">{{ profileForm.errors.phone }}</small>
                            </label>
                            <label class="admin-field">
                                <span>{{ copy.whatsapp }}</span>
                                <input v-model="profileForm.whatsapp_number" type="text">
                                <small v-if="profileForm.errors.whatsapp_number" class="admin-error">{{ profileForm.errors.whatsapp_number }}</small>
                            </label>
                            <label class="admin-field">
                                <span>{{ copy.companyDescription }}</span>
                                <textarea v-model="profileForm.company_description" rows="4"></textarea>
                                <small v-if="profileForm.errors.company_description" class="admin-error">{{ profileForm.errors.company_description }}</small>
                            </label>
                            <label class="admin-field">
                                <span>{{ copy.rolesField }}</span>
                                <select v-model="profileForm.role">
                                    <option value="buyer">{{ roleLabel('buyer') }}</option>
                                    <option value="seller">{{ roleLabel('seller') }}</option>
                                </select>
                                <small v-if="profileForm.errors.role" class="admin-error">{{ profileForm.errors.role }}</small>
                            </label>
                            <label class="admin-field">
                                <span>{{ copy.emailVerificationStatus }}</span>
                                <select v-model="profileForm.email_verified">
                                    <option :value="true">{{ copy.verified }}</option>
                                    <option :value="false">{{ copy.notVerified }}</option>
                                </select>
                                <small v-if="profileForm.errors.email_verified" class="admin-error">{{ profileForm.errors.email_verified }}</small>
                            </label>
                            <div class="admin-actions">
                                <button type="button" class="action-secondary" @click="closeModal">{{ copy.cancel }}</button>
                                <button type="submit" class="action-primary" :disabled="profileForm.processing">
                                    {{ profileForm.processing ? copy.saving : copy.save }}
                                </button>
                            </div>
                        </form>
                    </template>

                    <template v-else-if="modalType === 'view-business' && activeUser">
                        <p class="directory-eyebrow">{{ copy.businessesTitle }}</p>
                        <h2 class="directory-section-title">{{ copy.businessViewTitle }}</h2>
                        <div class="detail-grid">
                            <div class="detail-item"><span>{{ copy.company }}</span><strong>{{ activeUser.company_name || copy.noValue }}</strong></div>
                            <div class="detail-item"><span>{{ copy.email }}</span><strong>{{ activeUser.email || copy.noValue }}</strong></div>
                            <div class="detail-item"><span>{{ copy.country }}</span><strong>{{ activeUser.country || copy.noValue }}</strong></div>
                            <div class="detail-item"><span>{{ copy.city }}</span><strong>{{ activeUser.company_city || copy.noValue }}</strong></div>
                            <div class="detail-item"><span>{{ copy.phone }}</span><strong>{{ activeUser.phone || copy.noValue }}</strong></div>
                            <div class="detail-item"><span>{{ copy.contactEmail }}</span><strong>{{ activeUser.contact_email || copy.noValue }}</strong></div>
                            <div class="detail-item"><span>{{ copy.website }}</span><strong>{{ activeUser.website_url || copy.noValue }}</strong></div>
                            <div class="detail-item detail-item-wide"><span>{{ copy.address }}</span><strong>{{ activeUser.company_address_line || copy.noValue }}</strong></div>
                        </div>
                    </template>

                    <template v-else-if="modalType === 'edit-business' && activeUser">
                        <p class="directory-eyebrow">{{ copy.businessesTitle }}</p>
                        <h2 class="directory-section-title">{{ copy.businessEditTitle }}</h2>
                        <form class="admin-modal-form" @submit.prevent="submitBusinessEdit">
                            <label class="admin-field">
                                <span>{{ copy.company }}</span>
                                <input v-model="businessForm.company_name" type="text">
                                <small v-if="businessForm.errors.company_name" class="admin-error">{{ businessForm.errors.company_name }}</small>
                            </label>
                            <label class="admin-field">
                                <span>{{ copy.country }}</span>
                                <input v-model="businessForm.country" type="text">
                                <small v-if="businessForm.errors.country" class="admin-error">{{ businessForm.errors.country }}</small>
                            </label>
                            <label class="admin-field">
                                <span>{{ copy.city }}</span>
                                <input v-model="businessForm.company_city" type="text">
                                <small v-if="businessForm.errors.company_city" class="admin-error">{{ businessForm.errors.company_city }}</small>
                            </label>
                            <label class="admin-field">
                                <span>{{ copy.address }}</span>
                                <input v-model="businessForm.company_address_line" type="text">
                                <small v-if="businessForm.errors.company_address_line" class="admin-error">{{ businessForm.errors.company_address_line }}</small>
                            </label>
                            <label class="admin-field">
                                <span>{{ copy.phone }}</span>
                                <input v-model="businessForm.phone" type="text">
                                <small v-if="businessForm.errors.phone" class="admin-error">{{ businessForm.errors.phone }}</small>
                            </label>
                            <label class="admin-field">
                                <span>{{ copy.contactEmail }}</span>
                                <input v-model="businessForm.contact_email" type="email">
                                <small v-if="businessForm.errors.contact_email" class="admin-error">{{ businessForm.errors.contact_email }}</small>
                            </label>
                            <label class="admin-field">
                                <span>{{ copy.website }}</span>
                                <input v-model="businessForm.website_url" type="url">
                                <small v-if="businessForm.errors.website_url" class="admin-error">{{ businessForm.errors.website_url }}</small>
                            </label>
                            <div class="admin-actions">
                                <button type="button" class="action-secondary" @click="closeModal">{{ copy.cancel }}</button>
                                <button type="submit" class="action-primary" :disabled="businessForm.processing">
                                    {{ businessForm.processing ? copy.saving : copy.save }}
                                </button>
                            </div>
                        </form>
                    </template>


                    <template v-else-if="modalType === 'status-business' && activeUser">
                        <p class="directory-eyebrow">{{ copy.status }}</p>
                        <h2 class="directory-section-title">{{ activeUser.company_name || activeUser.name }}</h2>
                        <p class="admin-modal-copy">
                            Choose the new status for this supplier company registration.
                        </p>
                        <div class="status-choice-grid">
                            <button type="button" class="status-choice status-choice-approved" @click="chooseBusinessStatus('approved')">
                                <strong>{{ statusLabel('approved') }}</strong>
                                <span>Keep this record approved.</span>
                            </button>
                            <button type="button" class="status-choice status-choice-rejected" @click="chooseBusinessStatus('rejected')">
                                <strong>{{ statusLabel('rejected') }}</strong>
                                <span>Add a rejection reason and explanation.</span>
                            </button>
                        </div>
                        <div class="admin-actions">
                            <button type="button" class="action-secondary" @click="closeModal">{{ copy.cancel }}</button>
                        </div>
                    </template>

                    <template v-else-if="modalType === 'reject-business' && activeUser">
                        <p class="directory-eyebrow">{{ copy.reject }}</p>
                        <h2 class="directory-section-title">{{ copy.rejectTitle }}</h2>
                        <p class="admin-modal-copy">{{ copy.rejectText }}</p>

                        <form class="admin-modal-form" @submit.prevent="submitReject">
                            <label class="admin-field">
                                <span>{{ copy.rejectionReason }}</span>
                                <select v-model="rejectForm.rejection_reason">
                                    <option value="">{{ copy.placeholderReason }}</option>
                                    <option v-for="(label, key) in copy.reasons" :key="key" :value="key">{{ label }}</option>
                                </select>
                                <small v-if="rejectForm.errors.rejection_reason" class="admin-error">{{ rejectForm.errors.rejection_reason }}</small>
                            </label>

                            <label class="admin-field">
                                <span>{{ copy.rejectionNote }}</span>
                                <textarea v-model="rejectForm.rejection_note" rows="4" />
                                <small v-if="rejectForm.errors.rejection_note" class="admin-error">{{ rejectForm.errors.rejection_note }}</small>
                            </label>

                            <div class="admin-field">
                                <span>{{ copy.rejectionFields }}</span>
                                <div class="field-grid">
                                    <label v-for="field in feedbackFields" :key="field.key" class="field-chip">
                                        <input v-model="rejectForm.rejection_fields" type="checkbox" :value="field.key">
                                        <span>{{ field.label }}</span>
                                    </label>
                                </div>
                            </div>

                            <div class="admin-actions">
                                <button type="button" class="action-secondary" @click="closeModal">{{ copy.cancel }}</button>
                                <button type="submit" class="action-warning" :disabled="rejectForm.processing">
                                    {{ rejectForm.processing ? copy.saving : copy.reject }}
                                </button>
                            </div>
                        </form>
                    </template>
                </div>
            </div>
        </Transition>
        <DeleteModal
            :show="modalType === 'delete-record'"
            :user="activeUser"
            :copy="copy"
            :context="deleteContext"
            @close="closeModal"
            @confirm="confirmDelete"
        />
        <StatusModal
            :show="modalType === 'status-business'"
            :user="activeUser"
            :copy="copy"
            :status-label="statusLabel"
            @close="closeModal"
            @choose="chooseBusinessStatus"
        />
        <RejectModal
            :show="modalType === 'reject-business'"
            :user="activeUser"
            :copy="copy"
            :form="rejectForm"
            :feedback-fields="feedbackFields"
            @close="closeModal"
            @submit="submitReject"
        />
        <UpdateRequestDiffModal
            :show="modalType === 'update-diff'"
            :user="activeUser"
            :copy="copy"
            :items="activeUser ? updateDiffItems(activeUser) : []"
            @close="closeModal"
        />
        <VerificationMailHistoryModal
            :show="modalType === 'verification-mail-history'"
            :user="activeUser"
            :copy="copy"
            :payload="activeUser ? verificationMailHistoryPayload(activeUser) : null"
            @close="closeModal"
        />
        <RejectionFeedbackModal
            :show="modalType === 'rejection-feedback'"
            :user="activeUser"
            :copy="copy"
            :payload="activeUser ? reviewFeedbackPayload(activeUser) : null"
            @close="closeModal"
        />
        <RemovalRequestModal
            :show="modalType === 'removal-request'"
            :user="activeUser"
            :copy="copy"
            :reason="activeUser ? removalRequestPayload(activeUser).reason : ''"
            :note="activeUser ? removalRequestPayload(activeUser).note : ''"
            :form="removalReviewForm"
            @close="closeModal"
            @approve="approveRemovalRequest"
            @reject="rejectRemovalRequest"
        />
    </AdminDashboardShell>
</template>

<style scoped>
.admin-dashboard-shell{display:grid;gap:20px}
.table-headline{margin-bottom:14px}
.table-headline :deep(.directory-section-title){margin:0}
.subfilter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px}
.subfilter-card{display:grid;gap:8px;padding:16px 18px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff;text-align:left;transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease}
.subfilter-card:hover{border-color:rgba(15,23,42,.16);box-shadow:0 16px 30px rgba(15,23,42,.06);transform:translateY(-1px)}
.subfilter-card.active{border-color:#0f172a;background:#0f172a;box-shadow:0 14px 28px rgba(15,23,42,.14)}
.subfilter-label{color:#64748b;font-size:.8rem;font-weight:600;line-height:1.4}
.subfilter-count{color:#0f172a;font-size:1.35rem;font-weight:700;line-height:1}
.subfilter-card.active .subfilter-label,.subfilter-card.active .subfilter-count{color:#fff}
.dashboard-table{display:grid;border:1px solid #e2e8f0;border-radius: 10px;overflow:hidden}
.dashboard-row{display:grid;gap:18px;align-items:center;padding:18px 20px;background:#fff}
.dashboard-row + .dashboard-row{border-top:1px solid #e2e8f0}
.dashboard-row-head{background:#f8fafc}
.dashboard-row-head span{color:#64748b;font-size:.76rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.users-grid{grid-template-columns:1.1fr 1.2fr .75fr .85fr .75fr 1.3fr}
.businesses-grid{grid-template-columns:1.25fr .55fr .85fr .75fr 1.5fr}
.identity-cell{display:grid;gap:4px}
.identity-cell strong{color:#020617;font-size:1rem;font-weight:560}
.identity-cell span,.identity-cell small{color:rgba(4,21,31,.68)}
.dashboard-row p{margin:0;color:#475569;font-size:.92rem;line-height:1.7}
.soft-pill,.status-pill{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 14px;border-radius: 10px;font-size:.82rem;font-weight:600;width:fit-content}
.soft-pill{background:#f8fafc;border:1px solid #e2e8f0;color:#334155}
.soft-pill.is-warning{background:#fff7ed;border-color:#fed7aa;color:#c2410c}
.status-pill{background:#eefaf3;border:1px solid #d9f3df;color:#0b7a52}
.status-pill.is-pending{background:#eff6ff;border-color:#dbeafe;color:#2563eb}
.status-pill.is-rejected{background:#fff7ed;border-color:#fed7aa;color:#c2410c}
.table-actions{display:flex;flex-wrap:wrap;gap:10px}
.icon-action,.action-primary,.action-secondary,.action-danger,.action-warning{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:40px;padding:0 12px;border-radius: 10px;border:1px solid rgba(4,21,31,.08);background:#fff;color:#0f172a;font-size:.82rem;font-weight:600}
.icon-action{width:40px;min-width:40px;padding:0;border-radius:12px}
.icon-action svg{width:16px;height:16px;flex:0 0 16px}
.icon-action.is-danger,.action-danger{border-color:rgba(180,35,24,.16);color:#b42318;background:#fff7f7}
.icon-action.is-danger-soft{border-color:rgba(127,29,29,.14);color:#991b1b;background:#fff5f5}
.icon-action.is-success{border-color:rgba(11,122,82,.16);color:#0b7a52;background:#f1fcf6}
.icon-action.is-status{border-color:rgba(37,99,235,.16);color:#2563eb;background:#eff6ff}
.icon-action.is-info{border-color:rgba(99,102,241,.16);color:#4f46e5;background:#eef2ff}
.icon-action.is-warning,.action-warning{border-color:rgba(194,65,12,.16);color:#c2410c;background:#fff7ed}
.action-primary{border-color:#0f172a;background:#0f172a;color:#fff}
.meta-note{display:inline-flex;align-items:center;width:fit-content;padding:7px 10px;border-radius: 10px;font-weight:600}
.meta-note.is-pending{background:rgba(37,99,235,.08);color:#1d4ed8 !important}
.meta-note.is-removal{background:rgba(127,29,29,.08);color:#991b1b !important}
.meta-note.is-rejected{background:rgba(180,35,24,.08);color:#b42318 !important}
.meta-detail{max-width:44ch;line-height:1.55;color:rgba(4,21,31,.74) !important}
.admin-fade-enter-active,.admin-fade-leave-active{transition:opacity .18s ease}
.admin-fade-enter-from,.admin-fade-leave-to{opacity:0}
.admin-modal-backdrop{position:fixed;inset:0;z-index:1500;display:flex;align-items:flex-start;justify-content:center;padding:24px 20px;background:rgba(4,21,31,.58);backdrop-filter:blur(10px);overflow-y:auto}
.admin-modal{position:relative;width:min(720px,100%);max-height:calc(100vh - 48px);overflow:auto;padding:24px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff;box-shadow:0 30px 60px rgba(15,23,42,.16)}
.admin-modal-close{position:absolute;top:16px;right:16px;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff;color:#0f172a;font-size:1.45rem;line-height:1}
.admin-modal-copy{margin:14px 0 0;color:#64748b;font-size:.95rem;line-height:1.7}
.admin-modal-form{display:grid;gap:16px;margin-top:18px}
.admin-field{display:grid;gap:8px}
.admin-field span{color:rgba(4,21,31,.78);font-size:.88rem;font-weight:500}
.admin-field input,.admin-field select,.admin-field textarea{width:100%;border:1px solid rgba(4,21,31,.12);border-radius: 10px;background:#fff;color:#0f172a;font-size:.94rem;font-weight:500}
.admin-field input,.admin-field select{min-height:48px;padding:0 14px}
.admin-field textarea{min-height:128px;padding:14px;line-height:1.6;resize:vertical}
.detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:18px}
.status-choice-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:18px}
.status-choice{display:grid;gap:8px;padding:18px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff;text-align:left}
.status-choice strong{color:#020617;font-size:1rem;font-weight:600}
.status-choice span{color:#64748b;font-size:.9rem;line-height:1.6}
.status-choice-approved{border-color:rgba(11,122,82,.14);background:#f1fcf6}
.status-choice-rejected{border-color:rgba(194,65,12,.14);background:#fff7ed}
.detail-grid.compact{grid-template-columns:1fr}
.detail-item{display:grid;gap:8px;padding:16px 18px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff}
.detail-item-wide{grid-column:1 / -1}
.detail-item span{color:#64748b;font-size:.8rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase}
.detail-item strong{color:#020617;font-size:.94rem;font-weight:560;line-height:1.6}
.field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.field-chip{display:flex;align-items:center;gap:10px;min-height:44px;padding:0 14px;border:1px solid rgba(4,21,31,.08);border-radius: 10px;background:#fff}
.field-chip input{margin:0}
.field-chip span{font-size:.88rem;font-weight:500;color:#0f172a}
.admin-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:6px}
.admin-error{color:#b42318;font-size:.84rem}
@media (max-width: 1180px){.users-grid,.businesses-grid{grid-template-columns:1fr}.dashboard-row-head{display:none}.dashboard-row{gap:10px}.table-actions{justify-content:flex-start}.status-choice-grid{grid-template-columns:1fr}.subfilter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width: 900px){.detail-grid,.field-grid{grid-template-columns:1fr}.admin-modal{width:min(640px,100%)}}
@media (max-width: 720px){.detail-grid,.field-grid,.subfilter-grid{grid-template-columns:1fr}.admin-modal-backdrop{padding:16px}.admin-modal{width:100%;max-height:calc(100vh - 32px);padding:20px}.action-primary,.action-secondary,.action-danger,.action-warning{width:100%}}
</style>

