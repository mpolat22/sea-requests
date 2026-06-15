<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import BuyerDashboardShell from './Shell.vue';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
    reviews: {
        type: Array,
        default: () => [],
    },
});

const reviewSearchQuery = ref('');
const reviewRowsPerPage = ref(10);
const reviewCurrentPage = ref(1);
const deleteModalReview = ref(null);

const copy = {
    title: 'Buyer Dashboard',
    tabTitle: 'Reviews',
    tabText: 'Manage the supplier reviews you can publish after confirmed award relationships are created.',
    searchPlaceholder: 'Search supplier / ref / ship',
    search: 'Search',
    reviewTable: {
        order: '#',
        supplier: 'Supplier',
        referenceNo: 'Reference No',
        ship: 'Ship',
        confirmedAt: 'Award Confirmed',
        rating: 'Rating',
        status: 'Status',
        actions: 'Actions',
    },
    recordsPerPage: 'Records per page:',
    showing: 'Showing',
    of: 'of',
    records: 'records',
    prev: 'Prev',
    next: 'Next',
    emptyReviewTitle: 'No supplier review is waiting yet.',
    emptyReviewText: 'Reviews will appear here after confirmed award relationships are created.',
    emptyReviewSearchTitle: 'No review record matched your search.',
    emptyReviewSearchText: 'Try a different keyword or clear the search and try again.',
    reviewStatus: {
        pending: 'Pending Review',
        published: 'Published',
    },
    reviewActionPending: 'Write Review',
    reviewActionPublished: 'View Review',
    deleteReviewTitle: 'Delete review',
    deleteReviewBody: 'If you delete this review, your buyer feedback will be removed from the supplier profile. You can publish a new review again later if needed.',
    deleteCancel: 'Cancel',
    deleteReviewConfirmButton: 'Delete Review',
};

const filteredReviews = computed(() => {
    const query = reviewSearchQuery.value.trim().toLowerCase();

    if (!query) {
        return props.reviews;
    }

    return props.reviews.filter((review) => {
        const haystack = [
            review.supplier_name,
            review.reference_no,
            review.ship_name,
            review.review_text,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(query);
    });
});

const reviewTotalPages = computed(() => Math.max(1, Math.ceil(filteredReviews.value.length / reviewRowsPerPage.value)));
const paginatedReviews = computed(() => {
    const start = (reviewCurrentPage.value - 1) * reviewRowsPerPage.value;
    return filteredReviews.value.slice(start, start + reviewRowsPerPage.value);
});
const reviewShowingFrom = computed(() => filteredReviews.value.length ? ((reviewCurrentPage.value - 1) * reviewRowsPerPage.value) + 1 : 0);
const reviewShowingTo = computed(() => filteredReviews.value.length ? Math.min(reviewCurrentPage.value * reviewRowsPerPage.value, filteredReviews.value.length) : 0);
const hasReviewSearchQuery = computed(() => reviewSearchQuery.value.trim().length > 0);
const reviewRowNumber = (index) => filteredReviews.value.length - (((reviewCurrentPage.value - 1) * reviewRowsPerPage.value) + index);

const formatDate = (value) => {
    if (!value) return '-';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
};

const changeReviewRowsPerPage = (event) => {
    reviewRowsPerPage.value = Number(event.target.value) || 10;
    reviewCurrentPage.value = 1;
};

const goReviewPrev = () => {
    reviewCurrentPage.value = Math.max(1, reviewCurrentPage.value - 1);
};

const goReviewNext = () => {
    reviewCurrentPage.value = Math.min(reviewTotalPages.value, reviewCurrentPage.value + 1);
};

const reviewStatusTone = (status) => status === 'published' ? 'is-open' : 'is-review';

const reviewRatingLabel = (rating) => {
    const numeric = Number(rating ?? 0);
    return numeric > 0 ? `${numeric}/5` : '-';
};

const openDeleteReviewModal = (review) => {
    if (!review?.delete_review_url) {
        return;
    }

    deleteModalReview.value = review;
};

const closeDeleteReviewModal = () => {
    deleteModalReview.value = null;
};

const confirmDeleteReview = () => {
    if (!deleteModalReview.value?.delete_review_url) {
        return;
    }

    router.delete(deleteModalReview.value.delete_review_url, {
        preserveScroll: true,
        onFinish: () => {
            closeDeleteReviewModal();
        },
    });
};
</script>

<template>
    <BuyerDashboardShell :dashboard="dashboard" :title="copy.title" active-tab="reviews">
        <section class="surface-panel table-panel">
            <div class="table-toolbar">
                <div class="table-intro">
                    <h2 class="directory-section-title">{{ copy.tabTitle }}</h2>
                    <p class="section-copy">{{ copy.tabText }}</p>
                </div>

                <div class="toolbar-search">
                    <input
                        v-model="reviewSearchQuery"
                        type="text"
                        :placeholder="copy.searchPlaceholder"
                    >
                    <button type="button" class="toolbar-button toolbar-button-primary">
                        {{ copy.search }}
                    </button>
                </div>
            </div>

            <div v-if="paginatedReviews.length" class="dashboard-table-wrap">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>
                                <div class="order-head">
                                    <span>{{ copy.reviewTable.order }}</span>
                                    <span>{{ copy.reviewTable.status }}</span>
                                </div>
                            </th>
                            <th>{{ copy.reviewTable.supplier }}</th>
                            <th>{{ copy.reviewTable.referenceNo }}</th>
                            <th>{{ copy.reviewTable.ship }}</th>
                            <th>{{ copy.reviewTable.confirmedAt }}</th>
                            <th>{{ copy.reviewTable.rating }}</th>
                            <th>{{ copy.reviewTable.actions }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(review, index) in paginatedReviews" :key="`${review.offer_id}-${review.status}`">
                            <td>
                                <div class="order-cell">
                                    <span class="order-index">{{ reviewRowNumber(index) }}</span>
                                    <span class="status-dot" :class="reviewStatusTone(review.status)" :title="copy.reviewStatus[review.status]"></span>
                                </div>
                            </td>
                            <td>{{ review.supplier_name || '-' }}</td>
                            <td>{{ review.reference_no || '-' }}</td>
                            <td>{{ review.ship_name || '-' }}</td>
                            <td>{{ formatDate(review.confirmed_at) }}</td>
                            <td>{{ reviewRatingLabel(review.rating) }}</td>
                            <td>
                                <div class="actions-cell">
                                    <Link :href="review.service_url" class="action-button action-view" :title="review.status === 'published' ? copy.reviewActionPublished : copy.reviewActionPending">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </Link>
                                    <button
                                        v-if="review.delete_review_url"
                                        type="button"
                                        class="action-button action-delete"
                                        :title="copy.deleteReviewTitle"
                                        @click="openDeleteReviewModal(review)"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M3 6h18" />
                                            <path d="M8 6V4h8v2" />
                                            <path d="M19 6l-1 14H6L5 6" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="paginatedReviews.length" class="mobile-card-stack">
                <article v-for="(review, index) in paginatedReviews" :key="`mobile-${review.offer_id}-${review.status}`" class="mobile-record-card">
                    <div class="mobile-card-head">
                        <div class="mobile-card-title-group">
                            <span class="mobile-card-kicker">#{{ reviewRowNumber(index) }}</span>
                            <span class="mobile-card-title">{{ review.supplier_name || '-' }}</span>
                        </div>
                        <span class="mobile-status-pill">
                            <span class="status-dot" :class="reviewStatusTone(review.status)"></span>
                            {{ copy.reviewStatus[review.status] }}
                        </span>
                    </div>

                    <div class="mobile-card-grid">
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.reviewTable.referenceNo }}</span>
                            <span class="mobile-field-value">{{ review.reference_no || '-' }}</span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.reviewTable.ship }}</span>
                            <span class="mobile-field-value">{{ review.ship_name || '-' }}</span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.reviewTable.confirmedAt }}</span>
                            <span class="mobile-field-value">{{ formatDate(review.confirmed_at) }}</span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.reviewTable.rating }}</span>
                            <span class="mobile-field-value">{{ reviewRatingLabel(review.rating) }}</span>
                        </div>
                    </div>

                    <div class="mobile-card-footer">
                        <div class="actions-cell">
                            <Link :href="review.service_url" class="action-button action-view" :title="review.status === 'published' ? copy.reviewActionPublished : copy.reviewActionPending">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </Link>
                            <button
                                v-if="review.delete_review_url"
                                type="button"
                                class="action-button action-delete"
                                :title="copy.deleteReviewTitle"
                                @click="openDeleteReviewModal(review)"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 6h18" />
                                    <path d="M8 6V4h8v2" />
                                    <path d="M19 6l-1 14H6L5 6" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else class="empty-card">
                <strong>{{ hasReviewSearchQuery ? copy.emptyReviewSearchTitle : copy.emptyReviewTitle }}</strong>
                <p>{{ hasReviewSearchQuery ? copy.emptyReviewSearchText : copy.emptyReviewText }}</p>
            </div>

            <div class="table-footer">
                <div class="footer-left">
                    <span>{{ copy.recordsPerPage }}</span>
                    <select :value="reviewRowsPerPage" @change="changeReviewRowsPerPage">
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                </div>

                <div class="footer-center">
                    {{ copy.showing }} {{ reviewShowingFrom }} - {{ reviewShowingTo }} {{ copy.of }} {{ filteredReviews.length }} {{ copy.records }}
                </div>

                <div class="footer-right">
                    <button type="button" class="pager-button" :disabled="reviewCurrentPage === 1" @click="goReviewPrev">
                        {{ copy.prev }}
                    </button>
                    <span class="page-indicator">{{ reviewCurrentPage }} / {{ reviewTotalPages }}</span>
                    <button type="button" class="pager-button" :disabled="reviewCurrentPage === reviewTotalPages" @click="goReviewNext">
                        {{ copy.next }}
                    </button>
                </div>
            </div>
        </section>

        <div v-if="deleteModalReview" class="delete-modal-backdrop" @click.self="closeDeleteReviewModal">
            <div class="delete-modal">
                <button type="button" class="delete-modal-close" @click="closeDeleteReviewModal">&times;</button>
                <h3 class="directory-section-title">{{ copy.deleteReviewTitle }}</h3>
                <p class="delete-modal-copy">{{ copy.deleteReviewBody }}</p>
                <div class="delete-modal-summary">
                    <span>{{ deleteModalReview.supplier_name || '-' }}</span>
                    <span>{{ deleteModalReview.reference_no || '-' }}</span>
                    <span>{{ deleteModalReview.ship_name || '-' }}</span>
                </div>
                <div class="delete-modal-actions">
                    <button type="button" class="toolbar-button delete-cancel-button" @click="closeDeleteReviewModal">
                        {{ copy.deleteCancel }}
                    </button>
                    <button type="button" class="toolbar-button delete-confirm-button" @click="confirmDeleteReview">
                        {{ copy.deleteReviewConfirmButton }}
                    </button>
                </div>
            </div>
        </div>
    </BuyerDashboardShell>
</template>

<style scoped>
.surface-panel,.empty-card{padding:32px 36px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.78);box-shadow:0 24px 44px rgba(15,23,42,.08)}
.section-copy,.empty-card p{margin:0;color:#64748b;font-size:.9rem;line-height:1.7}
.table-toolbar{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}
.table-intro{display:grid;gap:8px}
.table-intro :deep(.directory-section-title){margin:0}
.toolbar-button{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border-radius:10px;border:1px solid transparent;font-size:.92rem;font-weight:600;text-decoration:none}
.toolbar-button-primary{background:#2563eb;border-color:#2563eb;color:#fff;box-shadow:0 12px 24px rgba(37,99,235,.18)}
.toolbar-search{display:flex;align-items:center;gap:10px;margin-left:auto}
.toolbar-search input{width:290px;min-height:46px;padding:0 14px;border:1px solid rgba(148,163,184,.38);border-radius:8px;background:#fff;color:#0f172a;font-size:.92rem}
.dashboard-table-wrap{margin-top:16px;overflow-x:auto}
.mobile-card-stack{display:none}
.dashboard-table{width:100%;border-collapse:collapse;min-width:980px}
.dashboard-table thead th{padding:16px 14px;background:#f4f7fb;color:#0f172a;font-size:.82rem;font-weight:700;text-align:left}
.dashboard-table tbody td{padding:16px 14px;border-top:1px solid rgba(4,21,31,.06);color:#0f172a;font-size:.94rem;line-height:1.55;vertical-align:top;white-space:nowrap}
.order-cell,.order-head{display:flex;align-items:center;justify-content:space-between;gap:10px;min-width:52px}
.order-index{font-weight:600}
.status-dot{width:10px;height:10px;border-radius:999px;display:inline-block;box-shadow:0 0 0 3px transparent}
.status-dot.is-open{background:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.16)}
.status-dot.is-review{background:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.16)}
.actions-cell{display:flex;align-items:center;gap:8px}
.action-button{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:0;background:transparent;padding:0;text-decoration:none}
.action-button svg{width:17px;height:17px}
.action-view{color:#2563eb}
.action-delete{color:#ef4444}
.empty-card{margin-top:18px}
.table-footer{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:16px;margin-top:18px}
.footer-left,.footer-right{display:flex;align-items:center;gap:10px}
.footer-left span,.footer-center,.page-indicator{color:#475569;font-size:.92rem}
.footer-left select{min-height:38px;padding:0 12px;border:1px solid rgba(148,163,184,.42);border-radius:8px;background:#fff;color:#0f172a}
.footer-center{text-align:center}
.pager-button{min-height:38px;padding:0 14px;border:1px solid rgba(148,163,184,.42);border-radius:8px;background:#f8fafc;color:#475569;font-size:.9rem}
.pager-button:disabled{opacity:.55}
.delete-modal-backdrop{position:fixed;inset:0;z-index:1500;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(4,21,31,.58);backdrop-filter:blur(10px)}
.delete-modal{position:relative;width:min(620px,100%);padding:28px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff;box-shadow:0 30px 60px rgba(15,23,42,.16)}
.delete-modal-close{position:absolute;top:16px;right:16px;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:#fff;color:#0f172a;font-size:1.45rem;line-height:1}
.delete-modal-copy{margin:12px 0 0;color:#64748b;font-size:.95rem;line-height:1.7}
.delete-modal-summary{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}
.delete-modal-summary span{display:inline-flex;align-items:center;min-height:34px;padding:0 12px;border-radius:10px;background:#f8fafc;color:#0f172a;font-size:.84rem;font-weight:600}
.delete-modal-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:24px}
.delete-cancel-button{background:#fff;border-color:rgba(148,163,184,.32);color:#0f172a;box-shadow:none}
.delete-confirm-button{background:#ef4444;border-color:#ef4444;color:#fff;box-shadow:0 12px 24px rgba(239,68,68,.18)}
@media (max-width: 900px){
    .table-toolbar{flex-direction:column;align-items:stretch}
    .toolbar-search{margin-left:0;flex-direction:column;align-items:stretch}
    .toolbar-search input{width:100%}
    .table-footer{grid-template-columns:1fr}
    .footer-center{text-align:left}
}
@media (max-width: 720px){
    .surface-panel,.empty-card{padding:24px}
    .dashboard-table-wrap{display:none}
    .mobile-card-stack{display:grid;gap:16px;margin-top:16px}
    .mobile-record-card{display:grid;gap:16px;padding:18px;border:1px solid rgba(4,21,31,.08);border-radius:12px;background:#fff}
    .mobile-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
    .mobile-card-title-group{display:grid;gap:6px;min-width:0}
    .mobile-card-kicker{color:#64748b;font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
    .mobile-card-title{color:#0f172a;font-size:.98rem;font-weight:700;word-break:break-word}
    .mobile-status-pill{display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border-radius:999px;background:#f8fafc;color:#475569;font-size:.78rem;font-weight:700}
    .mobile-card-grid{display:grid;grid-template-columns:1fr;gap:12px}
    .mobile-card-field{display:grid;gap:5px}
    .mobile-field-label{color:#64748b;font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em}
    .mobile-field-value{color:#0f172a;font-size:.92rem;line-height:1.55;word-break:break-word}
    .mobile-card-footer{padding-top:4px;border-top:1px solid rgba(226,232,240,.9)}
    .actions-cell{flex-wrap:wrap}
    .delete-modal{padding:24px 20px 20px}
    .delete-modal-actions{flex-direction:column}
}
</style>
