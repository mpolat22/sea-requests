<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import MainLayout from '../../Layouts/MainLayout.vue';
import PublicMetaHead from '../../Components/PublicMetaHead.vue';
import RequestCard from '../../Components/RequestCard.vue';

const props = defineProps({
    requestsPage: {
        type: Object,
        required: true,
    },
    requestSummary: {
        type: Object,
        default: () => ({
            total: 0,
            draft: 0,
            submitted: 0,
            closed: 0,
        }),
    },
    buyerContext: {
        type: Object,
        default: () => ({
            canCreate: false,
            createUrl: null,
        }),
    },
    indexUrl: {
        type: String,
        required: true,
    },
    meta: {
        type: Object,
        default: () => ({
            title: 'Published Requests | Sea Requests',
            description: '',
            canonical: '',
            robots: 'index, follow',
            ogImage: '',
            twitterCard: 'summary_large_image',
        }),
    },
});

const page = usePage();
const requests = ref(props.requestsPage.data ?? []);
const currentPage = ref(props.requestsPage.current_page ?? 1);
const lastPage = ref(props.requestsPage.last_page ?? 1);
const isLoadingMore = ref(false);
const sentinel = ref(null);
let observer = null;

const copy = {
    eyebrow: 'Request directory',
    title: 'Published RFQs and Service Requests',
    text: 'Browse live marine requests, review requirement summaries and open the detail page for the opportunities that match your scope.',
    emptyTitle: 'No published RFQs yet.',
    emptyText: 'Draft RFQs do not appear here. Your first live RFQ will be listed in this area once it is published.',
    create: 'Create New RFQ',
    loading: 'Loading more requests...',
    reachedEnd: 'You have reached the end of the request list.',
};

const currentCopy = computed(() => copy);

const loadMore = () => {
    if (isLoadingMore.value || currentPage.value >= lastPage.value) {
        return;
    }

    isLoadingMore.value = true;

    router.get(
        props.indexUrl,
        {
            page: currentPage.value + 1,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['requestsPage'],
            onSuccess: () => {
                const incoming = page.props.requestsPage?.data ?? [];
                requests.value = [...requests.value, ...incoming];
                currentPage.value = page.props.requestsPage?.current_page ?? currentPage.value;
                lastPage.value = page.props.requestsPage?.last_page ?? lastPage.value;
            },
            onFinish: () => {
                isLoadingMore.value = false;
            },
        },
    );
};

const setupObserver = () => {
    if (!sentinel.value) {
        return;
    }

    observer = new IntersectionObserver((entries) => {
        const [entry] = entries;

        if (entry?.isIntersecting) {
            loadMore();
        }
    }, {
        rootMargin: '400px 0px 400px 0px',
    });

    observer.observe(sentinel.value);
};

onMounted(() => {
    setupObserver();
});

onBeforeUnmount(() => {
    if (observer) {
        observer.disconnect();
        observer = null;
    }
});

watch(
    () => props.requestsPage,
    (value) => {
        if (!isLoadingMore.value) {
            requests.value = value?.data ?? [];
            currentPage.value = value?.current_page ?? 1;
            lastPage.value = value?.last_page ?? 1;
        }
    },
);
</script>

<template>
    <PublicMetaHead :meta="props.meta" />

    <MainLayout>
        <section class="listing-shell">
            <header class="section-header intro-card">
                <p class="eyebrow">{{ currentCopy.eyebrow }}</p>
                <h1 class="directory-page-title">{{ currentCopy.title }}</h1>
                <p>{{ currentCopy.text }}</p>
            </header>

            <div v-if="requests.length" class="listing-grid">
                <RequestCard v-for="item in requests" :key="item.id" :item="item" />
            </div>

            <div v-else class="empty-card">
                <strong>{{ currentCopy.emptyTitle }}</strong>
                <p>{{ currentCopy.emptyText }}</p>
                <Link v-if="buyerContext.canCreate && buyerContext.createUrl" :href="buyerContext.createUrl" class="cta-link">
                    {{ currentCopy.create }}
                </Link>
            </div>

            <div v-if="requests.length" ref="sentinel" class="load-state">
                <span v-if="isLoadingMore">{{ currentCopy.loading }}</span>
                <span v-else-if="currentPage >= lastPage">{{ currentCopy.reachedEnd }}</span>
            </div>
        </section>
    </MainLayout>
</template>

<style scoped>
.listing-shell {
    padding: 16px 0 56px;
}

.intro-card {
    padding: 32px 36px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.78);
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.08);
}

.section-header p:not(.eyebrow) {
    max-width: 86ch;
    margin-top: 16px;
    color: rgba(4, 21, 31, 0.72);
    line-height: 1.7;
}

.eyebrow {
    margin: 0 0 12px;
    font-size: 0.82rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--color-ocean);
    font-weight: 700;
}

.listing-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-top: 24px;
}

.empty-card {
    display: grid;
    gap: 10px;
    margin-top: 24px;
    padding: 36px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 18px 34px rgba(15, 23, 42, 0.08);
    color: #334155;
}

.empty-card strong {
    color: #0f172a;
    font-size: 1.06rem;
}

.empty-card p {
    margin: 0;
    color: #64748b;
    line-height: 1.7;
}

.cta-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    width: fit-content;
    padding: 0 18px;
    border-radius: 10px;
    background: #0e7490;
    color: #fff;
    font-size: 0.92rem;
    font-weight: 700;
    text-decoration: none;
}

.load-state {
    display: flex;
    justify-content: center;
    margin-top: 18px;
    color: #64748b;
    font-size: 0.9rem;
    font-weight: 600;
}

@media (max-width: 1180px) {
    .listing-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 960px) {
    .listing-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 720px) {
    .intro-card,
    .empty-card {
        padding: 24px;
    }

    .listing-grid {
        grid-template-columns: 1fr;
    }
}
</style>
