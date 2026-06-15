<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SupplierDashboardShell from './Shell.vue';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
    reviews: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: () => ({
            count: 0,
            average: null,
        }),
    },
});

const searchQuery = ref('');
const rowsPerPage = ref(10);
const currentPage = ref(1);
const deleteModalReview = ref(null);

const copy = {
    title: 'Supplier Dashboard',
    tabTitle: 'Reviews',
    tabText: 'Track buyer reviews for confirmed work here. You can reply from the Reviews area on your supplier profile.',
    searchPlaceholder: 'Search buyer / ref / ship',
    search: 'Search',
    averageLabel: 'Average Rating',
    totalLabel: 'Total Reviews',
    table: {
        order: '#',
        statusMini: 'Status',
        buyer: 'Buyer',
        referenceNo: 'Reference No',
        ship: 'Ship',
        rating: 'Rating',
        review: 'Review',
        replied: 'Reply',
        date: 'Date',
        actions: 'Actions',
    },
    view: 'View',
    noReply: 'No reply yet',
    replySaved: 'Reply saved',
    awaitingReply: 'Awaiting reply',
    emptyTitle: 'No buyer review has arrived yet.',
    emptySearchTitle: 'No review matched your search.',
    emptySearchText: 'Try a different keyword or clear the search and try again.',
    emptyText: 'Buyer reviews will appear here once they are submitted.',
    recordsPerPage: 'Records per page:',
    showing: 'Showing',
    of: 'of',
    records: 'records',
    prev: 'Prev',
    next: 'Next',
};

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

const filteredReviews = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (!query) {
        return props.reviews;
    }

    return props.reviews.filter((review) => {
        const haystack = [
            review.buyer_company,
            review.reference_no,
            review.ship_name,
            review.review_text,
            review.seller_reply,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(query);
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredReviews.value.length / rowsPerPage.value)));
const paginatedReviews = computed(() => {
    const start = (currentPage.value - 1) * rowsPerPage.value;
    return filteredReviews.value.slice(start, start + rowsPerPage.value);
});
const showingFrom = computed(() => filteredReviews.value.length ? ((currentPage.value - 1) * rowsPerPage.value) + 1 : 0);
const showingTo = computed(() => filteredReviews.value.length ? Math.min(currentPage.value * rowsPerPage.value, filteredReviews.value.length) : 0);
const hasSearchQuery = computed(() => searchQuery.value.trim().length > 0);
const rowNumber = (index) => filteredReviews.value.length - (((currentPage.value - 1) * rowsPerPage.value) + index);
const averageRatingText = computed(() => {
    const average = Number(props.summary.average ?? 0);
    return average > 0 ? average.toFixed(1) : '0.0';
});
const statusTone = (review) => review?.seller_reply ? 'is-awarded' : 'is-review';
const statusTitle = (review) => review?.seller_reply ? copy.replySaved : copy.awaitingReply;
const deleteReplyTitle = computed(() => 'Delete Reply');
const deleteReplyBody = computed(() => 'If you delete this supplier reply, the buyer review will remain published and you can write a new reply again later.');
const cancelLabel = computed(() => 'Cancel');

const changeRowsPerPage = (event) => {
    rowsPerPage.value = Number(event.target.value) || 10;
    currentPage.value = 1;
};

const goPrev = () => {
    currentPage.value = Math.max(1, currentPage.value - 1);
};

const goNext = () => {
    currentPage.value = Math.min(totalPages.value, currentPage.value + 1);
};

const openDeleteReviewModal = (review) => {
    if (!review?.delete_reply_url) {
        return;
    }

    deleteModalReview.value = review;
};

const closeDeleteReviewModal = () => {
    deleteModalReview.value = null;
};

const confirmDeleteReview = () => {
    if (!deleteModalReview.value?.delete_reply_url) {
        return;
    }

    router.delete(deleteModalReview.value.delete_reply_url, {
        preserveScroll: true,
        onFinish: () => {
            closeDeleteReviewModal();
        },
    });
};
</script>

<template>
    <SupplierDashboardShell :dashboard="dashboard" :title="copy.title" active-tab="reviews">
        <section class="surface-panel stats-panel">
            <article class="stat-card">
                <span class="stat-label">{{ copy.averageLabel }}</span>
                <strong class="stat-value">{{ averageRatingText }}</strong>
            </article>
            <article class="stat-card">
                <span class="stat-label">{{ copy.totalLabel }}</span>
                <strong class="stat-value">{{ summary.count ?? 0 }}</strong>
            </article>
        </section>

        <section class="surface-panel table-panel">
            <div class="table-toolbar">
                <div class="table-intro">
                    <h2 class="directory-section-title">{{ copy.tabTitle }}</h2>
                    <p class="section-copy">{{ copy.tabText }}</p>
                </div>

                <div class="toolbar-search">
                    <input v-model="searchQuery" type="text" :placeholder="copy.searchPlaceholder">
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
                                    <span>{{ copy.table.order }}</span>
                                    <span>{{ copy.table.statusMini }}</span>
                                </div>
                            </th>
                            <th>{{ copy.table.buyer }}</th>
                            <th>{{ copy.table.referenceNo }}</th>
                            <th>{{ copy.table.ship }}</th>
                            <th>{{ copy.table.rating }}</th>
                            <th>{{ copy.table.review }}</th>
                            <th>{{ copy.table.replied }}</th>
                            <th>{{ copy.table.date }}</th>
                            <th>{{ copy.table.actions }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(review, index) in paginatedReviews" :key="review.id">
                            <td>
                                <div class="order-cell">
                                    <span class="order-index">{{ rowNumber(index) }}</span>
                                    <span class="status-dot" :class="statusTone(review)" :title="statusTitle(review)"></span>
                                </div>
                            </td>
                            <td>{{ review.buyer_company || '-' }}</td>
                            <td>{{ review.reference_no || '-' }}</td>
                            <td>{{ review.ship_name || '-' }}</td>
                            <td>{{ review.rating }}/5</td>
                            <td class="review-copy-cell">{{ review.review_text || '-' }}</td>
                            <td class="review-copy-cell">{{ review.seller_reply || copy.noReply }}</td>
                            <td>{{ formatDate(review.created_at) }}</td>
                            <td>
                                <div class="actions-cell">
                                    <Link :href="review.service_url" class="action-button action-view" :title="copy.view">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </Link>
                                    <button
                                        v-if="review.delete_reply_url"
                                        type="button"
                                        class="action-button action-delete"
                                        :title="deleteReplyTitle"
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
                <article v-for="(review, index) in paginatedReviews" :key="`mobile-${review.id}`" class="mobile-record-card">
                    <div class="mobile-card-head">
                        <div class="mobile-card-title-group">
                            <span class="mobile-card-kicker">#{{ rowNumber(index) }}</span>
                            <span class="mobile-card-title">{{ review.buyer_company || '-' }}</span>
                        </div>
                        <span class="mobile-status-pill">
                            <span class="status-dot" :class="statusTone(review)"></span>
                            {{ statusTitle(review) }}
                        </span>
                    </div>

                    <div class="mobile-card-grid">
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.referenceNo }}</span>
                            <span class="mobile-field-value">{{ review.reference_no || '-' }}</span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.ship }}</span>
                            <span class="mobile-field-value">{{ review.ship_name || '-' }}</span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.rating }}</span>
                            <span class="mobile-field-value">{{ review.rating }}/5</span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.review }}</span>
                            <span class="mobile-field-value">{{ review.review_text || '-' }}</span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.replied }}</span>
                            <span class="mobile-field-value">{{ review.seller_reply || copy.noReply }}</span>
                        </div>
                        <div class="mobile-card-field">
                            <span class="mobile-field-label">{{ copy.table.date }}</span>
                            <span class="mobile-field-value">{{ formatDate(review.created_at) }}</span>
                        </div>
                    </div>

                    <div class="mobile-card-footer">
                        <div class="actions-cell">
                            <Link :href="review.service_url" class="action-button action-view" :title="copy.view">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </Link>
                            <button
                                v-if="review.delete_reply_url"
                                type="button"
                                class="action-button action-delete"
                                :title="deleteReplyTitle"
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
                <strong>{{ hasSearchQuery ? copy.emptySearchTitle : copy.emptyTitle }}</strong>
                <p>{{ hasSearchQuery ? copy.emptySearchText : copy.emptyText }}</p>
            </div>

            <div class="table-footer">
                <div class="footer-left">
                    <span>{{ copy.recordsPerPage }}</span>
                    <select :value="rowsPerPage" @change="changeRowsPerPage">
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                </div>

                <div class="footer-center">
                    {{ copy.showing }} {{ showingFrom }} - {{ showingTo }} {{ copy.of }} {{ filteredReviews.length }} {{ copy.records }}
                </div>

                <div class="footer-right">
                    <button type="button" class="pager-button" :disabled="currentPage === 1" @click="goPrev">
                        {{ copy.prev }}
                    </button>
                    <span class="page-indicator">{{ currentPage }} / {{ totalPages }}</span>
                    <button type="button" class="pager-button" :disabled="currentPage === totalPages" @click="goNext">
                        {{ copy.next }}
                    </button>
                </div>
            </div>
        </section>

        <div v-if="deleteModalReview" class="delete-modal-backdrop" @click.self="closeDeleteReviewModal">
            <div class="delete-modal">
                <button type="button" class="delete-modal-close" @click="closeDeleteReviewModal">&times;</button>
                <h3 class="directory-section-title">{{ deleteReplyTitle }}</h3>
                <p class="delete-modal-copy">{{ deleteReplyBody }}</p>
                <div class="delete-modal-summary">
                    <span>{{ deleteModalReview.buyer_company || '-' }}</span>
                    <span>{{ deleteModalReview.reference_no || '-' }}</span>
                    <span>{{ deleteModalReview.ship_name || '-' }}</span>
                </div>
                <div class="delete-modal-actions">
                    <button type="button" class="toolbar-button delete-cancel-button" @click="closeDeleteReviewModal">
                        {{ cancelLabel }}
                    </button>
                    <button type="button" class="toolbar-button delete-confirm-button" @click="confirmDeleteReview">
                        {{ deleteReplyTitle }}
                    </button>
                </div>
            </div>
        </div>
    </SupplierDashboardShell>
</template>

<style scoped>
.surface-panel,
.empty-card {
    padding: 32px 36px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.78);
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.08);
}

.stats-panel {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.stat-card {
    display: grid;
    gap: 8px;
    padding: 18px 20px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: #fff;
}

.stat-label {
    color: #64748b;
    font-size: 0.86rem;
    font-weight: 600;
}

.stat-value {
    color: #020617;
    font-size: 1.9rem;
    line-height: 1;
}

.table-panel {
    margin-top: 24px;
}

.table-toolbar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
}

.table-intro {
    display: grid;
    gap: 8px;
}

.table-intro :deep(.directory-section-title) {
    margin: 0;
}

.section-copy,
.empty-card p {
    margin: 0;
    color: #64748b;
    font-size: 0.9rem;
    line-height: 1.7;
}

.toolbar-search {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: auto;
}

.toolbar-search input {
    width: 290px;
    min-height: 46px;
    padding: 0 14px;
    border: 1px solid rgba(148, 163, 184, 0.38);
    border-radius: 8px;
    background: #fff;
    color: #0f172a;
    font-size: 0.92rem;
}

.toolbar-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 18px;
    border-radius: 10px;
    border: 1px solid transparent;
    font-size: 0.92rem;
    font-weight: 600;
    text-decoration: none;
}

.toolbar-button-primary {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
    box-shadow: 0 12px 24px rgba(37, 99, 235, 0.18);
}

.dashboard-table-wrap {
    margin-top: 16px;
    overflow-x: auto;
}

.mobile-card-stack {
    display: none;
}

.dashboard-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1080px;
}

.dashboard-table thead th {
    padding: 16px 14px;
    background: #f4f7fb;
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
}

.dashboard-table tbody td {
    padding: 16px 14px;
    border-top: 1px solid rgba(4, 21, 31, 0.06);
    color: #0f172a;
    font-size: 0.94rem;
    line-height: 1.55;
    vertical-align: top;
    white-space: nowrap;
}

.order-cell,
.order-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    min-width: 52px;
}

.order-cell {
    font-weight: 600;
}

.order-index {
    color: #0f172a;
}

.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    display: inline-block;
    box-shadow: 0 0 0 3px transparent;
}

.status-dot.is-review {
    background: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.16);
}

.status-dot.is-awarded {
    background: #0f766e;
    box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.16);
}

.review-copy-cell {
    min-width: 180px;
    max-width: 260px;
    white-space: normal !important;
    word-break: break-word;
}

.actions-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.action-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border: 0;
    background: transparent;
    padding: 0;
    text-decoration: none;
}

.action-button svg {
    width: 17px;
    height: 17px;
}

.action-view {
    color: #2563eb;
}

.action-delete {
    color: #ef4444;
}

.empty-card {
    margin-top: 18px;
}

.table-footer {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 16px;
    margin-top: 18px;
}

.footer-left,
.footer-right {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #64748b;
    font-size: 0.9rem;
}

.footer-left select {
    min-height: 38px;
    padding: 0 12px;
    border: 1px solid rgba(148, 163, 184, 0.32);
    border-radius: 10px;
    background: #fff;
    color: #0f172a;
    font-size: 0.9rem;
}

.footer-center {
    color: #64748b;
    font-size: 0.9rem;
    text-align: center;
}

.pager-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 0 14px;
    border: 1px solid rgba(148, 163, 184, 0.3);
    border-radius: 10px;
    background: #fff;
    color: #0f172a;
    font-size: 0.88rem;
    font-weight: 600;
}

.pager-button:disabled {
    color: #94a3b8;
    background: #f8fafc;
    cursor: not-allowed;
}

.page-indicator {
    color: #0f172a;
    font-size: 0.9rem;
    font-weight: 600;
}

.delete-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(4, 21, 31, 0.58);
    backdrop-filter: blur(10px);
}

.delete-modal {
    position: relative;
    width: min(620px, 100%);
    padding: 28px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 30px 60px rgba(15, 23, 42, 0.16);
}

.delete-modal-close {
    position: absolute;
    top: 16px;
    right: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: #fff;
    color: #0f172a;
    font-size: 1.45rem;
    line-height: 1;
}

.delete-modal-copy {
    margin: 12px 0 0;
    color: #64748b;
    font-size: 0.95rem;
    line-height: 1.7;
}

.delete-modal-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 18px;
}

.delete-modal-summary span {
    display: inline-flex;
    align-items: center;
    min-height: 34px;
    padding: 0 12px;
    border-radius: 10px;
    background: #f8fafc;
    color: #0f172a;
    font-size: 0.84rem;
    font-weight: 600;
}

.delete-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
}

.delete-cancel-button {
    background: #fff;
    border-color: rgba(148, 163, 184, 0.32);
    color: #0f172a;
    box-shadow: none;
}

.delete-confirm-button {
    background: #ef4444;
    border-color: #ef4444;
    color: #fff;
    box-shadow: 0 12px 24px rgba(239, 68, 68, 0.18);
}

@media (max-width: 900px) {
    .surface-panel,
    .empty-card {
        padding: 24px 20px;
    }

    .stats-panel {
        grid-template-columns: 1fr;
    }

    .table-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .toolbar-search {
        margin-left: 0;
    }

    .table-footer {
        grid-template-columns: 1fr;
    }

    .footer-center {
        text-align: left;
    }

    .dashboard-table {
        min-width: 980px;
    }
}

@media (max-width: 720px) {
    .toolbar-search {
        flex-direction: column;
        align-items: stretch;
    }

    .toolbar-search input {
        width: 100%;
    }

    .dashboard-table-wrap {
        display: none;
    }

    .mobile-card-stack {
        display: grid;
        gap: 16px;
        margin-top: 16px;
    }

    .mobile-record-card {
        display: grid;
        gap: 16px;
        padding: 18px;
        border: 1px solid rgba(4, 21, 31, 0.08);
        border-radius: 12px;
        background: #fff;
    }

    .mobile-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .mobile-card-title-group {
        display: grid;
        gap: 6px;
        min-width: 0;
    }

    .mobile-card-kicker {
        color: #64748b;
        font-size: 0.76rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .mobile-card-title {
        color: #0f172a;
        font-size: 0.98rem;
        font-weight: 700;
        line-height: 1.45;
        word-break: break-word;
    }

    .mobile-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        border-radius: 999px;
        background: #f8fafc;
        color: #475569;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .mobile-card-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .mobile-card-field {
        display: grid;
        gap: 5px;
    }

    .mobile-field-label {
        color: #64748b;
        font-size: 0.76rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .mobile-field-value {
        color: #0f172a;
        font-size: 0.92rem;
        line-height: 1.55;
        word-break: break-word;
    }

    .mobile-card-footer {
        padding-top: 4px;
        border-top: 1px solid rgba(226, 232, 240, 0.9);
    }

    .actions-cell {
        flex-wrap: wrap;
    }

    .delete-modal {
        padding: 24px 20px 20px;
    }

    .delete-modal-actions {
        flex-direction: column;
    }
}
</style>
