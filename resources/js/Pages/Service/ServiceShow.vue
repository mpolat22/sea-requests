<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import MainLayout from '../../Layouts/MainLayout.vue';
import ServiceListingCard from '../../Components/ServiceListingCard.vue';

const props = defineProps({
    service: {
        type: Object,
        required: true,
    },
    meta: {
        type: Object,
        default: () => ({
            title: 'Service | Sea Requests',
            description: '',
            canonical: '',
            robots: 'index, follow',
            ogImage: '',
            twitterCard: 'summary_large_image',
            preview: false,
        }),
    },
});

const allowedProfileTabs = new Set(['about', 'categories', 'brands', 'ports', 'business', 'reviews']);
const activeProfileTab = ref(allowedProfileTabs.has(props.service.initial_tab) ? props.service.initial_tab : 'about');
const similarStartIndex = ref(0);
const similarVisibleCount = ref(4);
const reviewSummaryState = ref(props.service.review_summary ?? null);
const reviewItemsState = ref(props.service.review_items ?? []);
const reviewEligibilityState = ref(props.service.review_eligibility ?? null);
const reviewsLoaded = ref(Boolean(props.service.reviews_loaded));
const isLoadingReviews = ref(false);
const similarVendorServicesState = ref(props.service.similar_vendors ?? []);
const similarVendorsLoaded = ref(Boolean(props.service.similar_vendors_loaded));
const isLoadingSimilarVendors = ref(false);
const similarSectionSentinel = ref(null);
const canViewContactDetails = computed(() => Boolean(props.service.can_view_contact_details));
const contactAccessState = computed(() => props.service.contact_access_state || 'guest');
const reviewSummary = computed(() => reviewSummaryState.value ?? {
    count: 0,
    average: null,
});
const reviewItems = computed(() => reviewItemsState.value ?? []);
const reviewEligibility = computed(() => reviewEligibilityState.value ?? {
    access_state: 'restricted',
    can_submit_review: false,
    submit_url: null,
    targets: [],
});
const reviewTargets = computed(() => reviewEligibility.value.targets ?? []);
const selectedReviewTargetId = ref(Number(props.service.initial_review_offer_id ?? reviewTargets.value[0]?.offer_id ?? 0) || null);
const hoveredRating = ref(0);
const sellerReplyEditorReviewId = ref(null);
const deleteModalState = ref(null);
const reviewForm = useForm({
    offer_id: '',
    rating: 0,
    review_text: '',
});
const sellerReplyForm = useForm({
    seller_reply: '',
});

const categoryThemes = [
    {
        matches: ['tank', 'clean', 'hull', 'coating', 'wash'],
        gradient: 'linear-gradient(135deg, #d8f3dc 0%, #95d5b2 38%, #1b4332 100%)',
        accent: '#1b4332',
        icon: 'sparkles',
    },
    {
        matches: ['crew', 'manning', 'training', 'recruitment'],
        gradient: 'linear-gradient(135deg, #e0f2fe 0%, #7dd3fc 40%, #0f172a 100%)',
        accent: '#0f172a',
        icon: 'users',
    },
    {
        matches: ['supply', 'spare', 'provision', 'stores', 'logistics'],
        gradient: 'linear-gradient(135deg, #fef3c7 0%, #fbbf24 42%, #7c2d12 100%)',
        accent: '#7c2d12',
        icon: 'boxes',
    },
    {
        matches: ['port', 'cargo', 'terminal', 'stevedore', 'agency'],
        gradient: 'linear-gradient(135deg, #ede9fe 0%, #a78bfa 42%, #312e81 100%)',
        accent: '#312e81',
        icon: 'anchor',
    },
    {
        matches: ['repair', 'technical', 'engineering', 'maintenance', 'inspection', 'hold'],
        gradient: 'linear-gradient(135deg, #fee2e2 0%, #fca5a5 42%, #7f1d1d 100%)',
        accent: '#7f1d1d',
        icon: 'tools',
    },
    {
        matches: ['document', 'compliance', 'legal', 'survey', 'certificate'],
        gradient: 'linear-gradient(135deg, #ecfccb 0%, #bef264 42%, #365314 100%)',
        accent: '#365314',
        icon: 'document',
    },
];

const defaultCategoryTheme = {
    gradient: 'linear-gradient(135deg, #dbeafe 0%, #93c5fd 40%, #082f49 100%)',
    accent: '#082f49',
    icon: 'waves',
};

const pageTitle = computed(() => props.service.vendor.name || props.service.title);
const backToResultsUrl = computed(() => props.service.back_url || '/services');

const socialIconItems = computed(() => {
    const items = [];

    [
        ['instagram', props.service.vendor.instagram, 'Instagram'],
        ['linkedin', props.service.vendor.linkedin, 'LinkedIn'],
        ['facebook', props.service.vendor.facebook, 'Facebook'],
        ['twitter', props.service.vendor.twitter, 'X'],
        ['telegram', props.service.vendor.telegram, 'Telegram'],
    ].forEach(([key, value, label]) => {
        if (value) {
            items.push({ key, href: value, icon: key, label, external: true });
        }
    });

    return items;
});

const primaryContactItems = computed(() => ([
    {
        key: 'contact_name',
        href: null,
        icon: 'user',
        label: 'Contact person',
        value: props.service.vendor.contact_name || '-',
    },
    {
        key: 'phone',
        href: props.service.vendor.phone ? `tel:${props.service.vendor.phone}` : null,
        icon: 'phone',
        label: 'Mobile / GSM',
        value: props.service.vendor.phone || '-',
    },
    {
        key: 'landline',
        href: props.service.vendor.landline ? `tel:${props.service.vendor.landline}` : null,
        icon: 'phone',
        label: 'Landline',
        value: props.service.vendor.landline || '-',
    },
    {
        key: 'email',
        href: props.service.vendor.email ? `mailto:${props.service.vendor.email}` : null,
        icon: 'mail',
        label: 'Email address',
        value: props.service.vendor.email || '-',
    },
    {
        key: 'whatsapp',
        href: props.service.vendor.whatsapp ? `https://wa.me/${props.service.vendor.whatsapp.replace(/\D/g, '')}` : null,
        icon: 'whatsapp',
        label: 'WhatsApp',
        value: props.service.vendor.whatsapp || '-',
        external: true,
    },
    {
        key: 'website',
        href: props.service.vendor.website || null,
        icon: 'globe',
        label: 'Website',
        value: props.service.vendor.website || '-',
        external: true,
    },
]));

const coverageGroups = computed(() => (props.service.vendor.ports_by_country ?? []).filter((group) => group.ports?.length));

const companyDetails = computed(() => ([
    { key: 'address', label: 'Address', value: props.service.vendor.address },
]).filter((item) => item.value));
const profileTabs = computed(() => ([
    { key: 'about', label: 'About' },
    { key: 'categories', label: 'Categories' },
    { key: 'brands', label: 'Brands' },
    { key: 'ports', label: 'Service Ports' },
    { key: 'business', label: 'Contact Information' },
    { key: 'reviews', label: 'Reviews' },
]));
const categorySections = computed(() => {
    const categories = (props.service.categories ?? []).filter((item) => item?.id && item?.name);
    const subcategories = (props.service.subcategories ?? []).filter((item) => item?.id && item?.name);
    const subcategoriesByCategory = props.service.subcategories_by_category ?? {};
    const fallbackCategories = props.service.primary_category?.id && props.service.primary_category?.name
        ? [{
            id: props.service.primary_category.id,
            name: props.service.primary_category.name,
        }]
        : [];
    const resolvedCategories = categories.length ? categories : fallbackCategories;

    const sections = resolvedCategories.map((category, index) => {
        const allowedSubcategoryIds = new Set(
            (subcategoriesByCategory[String(category.id)] ?? []).map((value) => Number(value)).filter(Boolean),
        );
        const linkedSubcategories = subcategories
            .filter((item) => Number(item?.category_id) === Number(category.id))
            .filter((item) => allowedSubcategoryIds.size === 0 || allowedSubcategoryIds.has(Number(item.id)))
            .map((item) => item.name)
            .filter(Boolean);

        const fallbackSubcategories = Number(props.service.secondary_category?.id)
            && Number(props.service.secondary_category?.category_id ?? category.id) === Number(category.id)
            && props.service.secondary_category?.name
            ? [props.service.secondary_category.name]
            : [];

        const resolvedSubcategories = linkedSubcategories.length ? linkedSubcategories : fallbackSubcategories;

        return {
            key: `category-${index}-${category.id}`,
            title: category.name,
            body: resolvedSubcategories.length ? resolvedSubcategories.join(', ') : 'No subcategories listed',
        };
    });

    return sections;
});
const brandSections = computed(() => {
    const brands = (props.service.brands ?? []).filter((item) => item?.id && item?.name);

    if (!brands.length) {
        return [{
            key: 'brands-empty',
            title: 'Brands',
            body: 'No brands listed',
        }];
    }

    return brands.map((brand, index) => ({
        key: `brand-${index}-${brand.id}`,
        title: brand.name,
        body: null,
    }));
});
const selectedReviewTarget = computed(() => reviewTargets.value
    .find((item) => Number(item.offer_id) === Number(selectedReviewTargetId.value)) ?? null);
const averageRatingText = computed(() => {
    const average = Number(reviewSummary.value.average ?? 0);
    return average > 0 ? average.toFixed(1) : '0.0';
});
const isSellerOwnerReviewView = computed(() => reviewEligibility.value.access_state === 'owner');
const isEditingReview = ref(false);
const activeReviewRating = computed(() => hoveredRating.value || Number(reviewForm.rating || 0));
const hasPublicReviews = computed(() => reviewItems.value.length > 0);
const selectedTargetHasSavedReview = computed(() => Boolean(selectedReviewTarget.value?.review));
const showReviewEditor = computed(() => reviewEligibility.value.can_submit_review && (!selectedTargetHasSavedReview.value || isEditingReview.value));
const editableReviewOfferIds = computed(() => new Set(
    reviewTargets.value
        .filter((item) => item.review)
        .map((item) => Number(item.offer_id))
        .filter(Boolean),
));
const reviewAccessMessage = computed(() => {
    if (reviewEligibility.value.access_state === 'eligible') {
        return 'Leave a star rating and buyer comment for the confirmed work you received from this supplier.';
    }

    if (reviewEligibility.value.access_state === 'guest') {
        return 'Only buyers with confirmed awards will be able to leave reviews here after signing in.';
    }

    if (reviewEligibility.value.access_state === 'owner') {
        return 'Buyer reviews and your replies will appear here once confirmed buyers start leaving feedback.';
    }

    return 'Only buyers with confirmed awards can leave reviews here. The supplier company will be able to reply on-platform.';
});
const showContactAuthActions = computed(() => contactAccessState.value === 'guest');
const reviewActionCopy = computed(() => ({
    deleteReview: 'Delete',
    deleteReply: 'Delete Reply',
    deleteReviewTitle: 'Delete review',
    deleteReplyTitle: 'Delete reply',
    deleteReviewBody: 'If you delete this review, your buyer feedback will be removed from the supplier profile. You can publish a new review again later if needed.',
    deleteReplyBody: 'If you delete this reply, the buyer review will remain published and you can write a new reply again later if needed.',
    confirmDeleteReview: 'Delete Review',
    confirmDeleteReply: 'Delete Reply',
    cancelDelete: 'Cancel',
}));
const lockedContactTitle = computed(() => {
    if (contactAccessState.value === 'guest') {
        return 'Sign in to continue through the RFQ workflow';
    }

    return 'Direct contact stays on-platform until award confirmation';
});
const lockedContactText = computed(() => {
    if (contactAccessState.value === 'guest') {
        return 'Supplier phone, email, website, WhatsApp and address become visible only after a confirmed award is established through the platform.';
    }

    return 'Phone, email, website, WhatsApp and address are visible only to the awarded buyer and the supplier company after award confirmation.';
});

const resetReviewFormForTarget = () => {
    const target = selectedReviewTarget.value;

    reviewForm.offer_id = target?.offer_id ? String(target.offer_id) : '';
    reviewForm.rating = 0;
    reviewForm.review_text = '';
    reviewForm.clearErrors();
    isEditingReview.value = false;
};

watch(reviewTargets, (targets) => {
    if (!targets.length) {
        selectedReviewTargetId.value = null;
        reviewForm.reset();
        isEditingReview.value = false;
        return;
    }

    const currentExists = targets.some((item) => Number(item.offer_id) === Number(selectedReviewTargetId.value));

    if (!currentExists) {
        selectedReviewTargetId.value = Number(props.service.initial_review_offer_id ?? targets[0].offer_id);
    }

    resetReviewFormForTarget();
}, { immediate: true });

watch(selectedReviewTargetId, () => {
    resetReviewFormForTarget();
});

watch(activeProfileTab, (tab) => {
    if (tab === 'reviews') {
        fetchReviewsIfNeeded();
    }
});

const selectReviewRating = (value) => {
    reviewForm.rating = Number(value);
};

const startEditingReview = (offerId = selectedReviewTargetId.value) => {
    const target = reviewTargets.value.find((item) => Number(item.offer_id) === Number(offerId) && item.review);

    if (!target?.review) {
        return;
    }

    selectedReviewTargetId.value = Number(target.offer_id);
    reviewForm.offer_id = String(target.offer_id);
    reviewForm.rating = Number(target.review.rating ?? 0);
    reviewForm.review_text = target.review.review_text ?? '';
    reviewForm.clearErrors();
    isEditingReview.value = true;
    activeProfileTab.value = 'reviews';
};

const cancelReviewEditing = () => {
    resetReviewFormForTarget();
};

const canEditReviewItem = (item) => editableReviewOfferIds.value.has(Number(item.offer_id));
const isSellerReplyEditorOpen = (item) => Number(sellerReplyEditorReviewId.value) === Number(item.id);

const openSellerReplyEditor = (item) => {
    sellerReplyEditorReviewId.value = Number(item.id);
    sellerReplyForm.seller_reply = item.seller_reply ?? '';
    sellerReplyForm.clearErrors();
    activeProfileTab.value = 'reviews';
};

const cancelSellerReplyEditing = () => {
    sellerReplyEditorReviewId.value = null;
    sellerReplyForm.reset();
    sellerReplyForm.clearErrors();
};

const deleteModalTitle = computed(() => {
    if (deleteModalState.value?.mode === 'reply') {
        return reviewActionCopy.value.deleteReplyTitle;
    }

    return reviewActionCopy.value.deleteReviewTitle;
});

const deleteModalBody = computed(() => {
    if (deleteModalState.value?.mode === 'reply') {
        return reviewActionCopy.value.deleteReplyBody;
    }

    return reviewActionCopy.value.deleteReviewBody;
});

const deleteModalConfirmLabel = computed(() => {
    if (deleteModalState.value?.mode === 'reply') {
        return reviewActionCopy.value.confirmDeleteReply;
    }

    return reviewActionCopy.value.confirmDeleteReview;
});

const deleteModalSummaryItems = computed(() => {
    const item = deleteModalState.value?.item;

    if (!item) {
        return [];
    }

    return [
        item.delete_modal_buyer_company || item.buyer_company || null,
        item.reference_no || null,
        item.ship_name || null,
    ].filter((value) => String(value ?? '').trim() !== '');
});

const openDeleteReviewModal = (mode, item) => {
    if (!item || (mode === 'reply' && !item.delete_reply_url) || (mode === 'review' && !item.delete_review_url)) {
        return;
    }

    deleteModalState.value = { mode, item };
    activeProfileTab.value = 'reviews';
};

const closeDeleteReviewModal = () => {
    deleteModalState.value = null;
};

const showSimilarVendorsSection = computed(() => similarVendorsLoaded.value && similarVendorServices.value.length > 0);

const requestJson = async (url) => {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
    }

    return response.json();
};

const fetchReviewsIfNeeded = async () => {
    if (reviewsLoaded.value || isLoadingReviews.value || !props.service.reviews_data_url) {
        return;
    }

    isLoadingReviews.value = true;

    try {
        const payload = await requestJson(props.service.reviews_data_url);
        reviewSummaryState.value = payload.review_summary ?? { count: 0, average: null };
        reviewItemsState.value = payload.review_items ?? [];
        reviewEligibilityState.value = payload.review_eligibility ?? {
            access_state: 'restricted',
            can_submit_review: false,
            submit_url: null,
            targets: [],
        };
        reviewsLoaded.value = true;
    } catch (error) {
        console.error('Failed to load reviews data.', error);
    } finally {
        isLoadingReviews.value = false;
    }
};

const fetchSimilarVendorsIfNeeded = async () => {
    if (similarVendorsLoaded.value || isLoadingSimilarVendors.value || !props.service.similar_vendors_url) {
        return;
    }

    isLoadingSimilarVendors.value = true;

    try {
        const payload = await requestJson(props.service.similar_vendors_url);
        similarVendorServicesState.value = payload.similar_vendors ?? [];
        similarVendorsLoaded.value = true;
        syncSimilarViewport();
        similarSectionObserver?.disconnect();
        similarSectionObserver = null;
    } catch (error) {
        console.error('Failed to load similar vendors.', error);
    } finally {
        isLoadingSimilarVendors.value = false;
    }
};

const submitSellerReply = (item) => {
    if (!item?.reply_url) {
        return;
    }

    sellerReplyForm.post(item.reply_url, {
        preserveScroll: true,
        onSuccess: () => {
            sellerReplyEditorReviewId.value = null;
            sellerReplyForm.reset();
            activeProfileTab.value = 'reviews';
        },
    });
};

const deleteBuyerReview = (item) => {
    if (!item?.delete_review_url) {
        return;
    }

    openDeleteReviewModal('review', item);
};

const deleteSellerReply = (item) => {
    if (!item?.delete_reply_url) {
        return;
    }

    openDeleteReviewModal('reply', item);
};

const confirmDeleteReviewAction = () => {
    const item = deleteModalState.value?.item;
    const mode = deleteModalState.value?.mode;

    if (!item || !mode) {
        return;
    }

    const targetUrl = mode === 'reply' ? item.delete_reply_url : item.delete_review_url;

    if (!targetUrl) {
        return;
    }

    router.delete(targetUrl, {
        preserveScroll: true,
        onSuccess: () => {
            if (mode === 'review' && Number(selectedReviewTargetId.value) === Number(item.offer_id)) {
                resetReviewFormForTarget();
            }

            if (mode === 'reply' && isSellerReplyEditorOpen(item)) {
                cancelSellerReplyEditing();
            }

            activeProfileTab.value = 'reviews';
        },
        onFinish: () => {
            closeDeleteReviewModal();
        },
    });
};

const submitReview = () => {
    if (!reviewEligibility.value.submit_url || !reviewForm.offer_id) {
        return;
    }

    reviewForm.post(reviewEligibility.value.submit_url, {
        preserveScroll: true,
        onSuccess: () => {
            hoveredRating.value = 0;
            isEditingReview.value = false;
            activeProfileTab.value = 'reviews';
        },
    });
};

const formatReviewDate = (value) => {
    if (!value) return '-';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
};

const similarVendorServices = computed(() => similarVendorServicesState.value ?? []);
const visibleSimilarVendorServices = computed(() =>
    similarVendorServices.value.slice(similarStartIndex.value, similarStartIndex.value + similarVisibleCount.value)
);
const canSlideSimilarPrev = computed(() => similarStartIndex.value > 0);
const canSlideSimilarNext = computed(() => similarStartIndex.value + similarVisibleCount.value < similarVendorServices.value.length);

const resolveCategoryTheme = (item) => {
    const haystack = [
        item?.primary_category?.name,
        item?.primary_category?.slug,
        item?.secondary_category?.name,
        item?.secondary_category?.slug,
    ].filter(Boolean).join(' ').toLowerCase();

    return categoryThemes.find((theme) => theme.matches.some((match) => haystack.includes(match))) ?? defaultCategoryTheme;
};

const renderIcon = (icon) => icon;

const structuredData = computed(() => {
    const areaServed = (props.service.vendor.ports_by_country ?? [])
        .map((group) => group.country_name)
        .filter(Boolean)
        .map((name) => ({
            '@type': 'Country',
            name,
        }));

    return JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'Service',
        name: props.service.title,
        description: props.meta.description || props.service.overview || props.service.summary || '',
        url: props.meta.canonical || undefined,
        areaServed,
        provider: {
            '@type': 'Organization',
            name: props.service.vendor.name,
            logo: props.meta.ogImage || undefined,
            email: props.service.vendor.email || undefined,
            telephone: props.service.vendor.phone || undefined,
            url: props.service.vendor.website || undefined,
            address: props.service.vendor.address ? {
                '@type': 'PostalAddress',
                streetAddress: props.service.vendor.address,
                addressLocality: props.service.vendor.city || undefined,
                addressCountry: props.service.vendor.country || undefined,
            } : undefined,
        },
        category: [
            props.service.primary_category?.name,
            props.service.secondary_category?.name,
        ].filter(Boolean),
    });
});

const syncSimilarViewport = () => {
    if (typeof window === 'undefined') {
        return;
    }

    if (window.innerWidth <= 720) {
        similarVisibleCount.value = 1;
    } else if (window.innerWidth <= 960) {
        similarVisibleCount.value = 2;
    } else {
        similarVisibleCount.value = 4;
    }

    const maxStart = Math.max(0, similarVendorServices.value.length - similarVisibleCount.value);
    similarStartIndex.value = Math.min(similarStartIndex.value, maxStart);
};

const slideSimilarPrev = () => {
    similarStartIndex.value = Math.max(0, similarStartIndex.value - 1);
};

const slideSimilarNext = () => {
    const maxStart = Math.max(0, similarVendorServices.value.length - similarVisibleCount.value);
    similarStartIndex.value = Math.min(maxStart, similarStartIndex.value + 1);
};

let similarSectionObserver = null;

onMounted(() => {
    window.addEventListener('resize', syncSimilarViewport);
    syncSimilarViewport();

    if (activeProfileTab.value === 'reviews') {
        fetchReviewsIfNeeded();
    }

    if (typeof window !== 'undefined' && 'IntersectionObserver' in window && similarSectionSentinel.value) {
        similarSectionObserver = new window.IntersectionObserver((entries) => {
            const hasIntersectingEntry = entries.some((entry) => entry.isIntersecting);

            if (!hasIntersectingEntry) {
                return;
            }

            fetchSimilarVendorsIfNeeded();
        }, {
            rootMargin: '320px 0px',
        });

        similarSectionObserver.observe(similarSectionSentinel.value);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', syncSimilarViewport);

    if (similarSectionObserver) {
        similarSectionObserver.disconnect();
        similarSectionObserver = null;
    }
});

</script>

<template>
    <Head :title="meta.title">
        <meta name="description" :content="meta.description" />
        <meta name="robots" :content="meta.robots" />
        <meta property="og:title" :content="meta.title" />
        <meta property="og:description" :content="meta.description" />
        <meta property="og:type" content="article" />
        <meta property="og:image" :content="meta.ogImage" />
        <meta name="twitter:card" :content="meta.twitterCard || 'summary_large_image'" />
        <meta name="twitter:title" :content="meta.title" />
        <meta name="twitter:description" :content="meta.description" />
        <meta name="twitter:image" :content="meta.ogImage" />
        <link v-if="meta.canonical" rel="canonical" :href="meta.canonical">
        <component :is="'script'" type="application/ld+json" v-html="structuredData" />
    </Head>

    <MainLayout>
        <section class="service-shell">
            <div class="page-grid">
                <div class="main-column">
                    <article class="surface-card main-surface">
                        <header class="hero-block">
                            <div class="hero-surface">
                                <div class="hero-utility-row">
                                    <Link :href="backToResultsUrl" class="hero-back-link">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="m15 18-6-6 6-6" />
                                        </svg>
                                        <span>Back to search results</span>
                                    </Link>
                                </div>

                                <div class="hero-brand">
                                    <div class="hero-brand-main">
                                        <div class="hero-media">
                                            <div class="hero-showcase hero-showcase-logo-stage hero-logo-card">
                                                <img v-if="service.logo_url" :src="service.logo_url" :alt="service.vendor.name" class="hero-showcase-logo-display" />
                                                <span v-else class="hero-showcase-initials hero-showcase-initials-large">
                                                    {{ service.vendor.name?.slice(0, 2)?.toUpperCase() }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="hero-copy hero-copy-profile">
                                            <h1 class="directory-page-title hero-title" :title="pageTitle">{{ pageTitle }}</h1>
                                            <div class="hero-rating-row">
                                                <div class="hero-rating-stars" aria-hidden="true">
                                                    <span
                                                        v-for="star in 5"
                                                        :key="`hero-rating-${star}`"
                                                        class="hero-rating-star"
                                                        :class="{ 'is-filled': star <= Math.round(Number(reviewSummary.average ?? 0)) }"
                                                    >★</span>
                                                </div>
                                                <span class="hero-rating-value">{{ averageRatingText }}</span>
                                                <span class="hero-rating-count">
                                                    {{ Number(reviewSummary.count ?? 0) }} review{{ Number(reviewSummary.count ?? 0) === 1 ? '' : 's' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <Link
                                        v-if="props.service.request_service_url"
                                        :href="props.service.request_service_url"
                                        class="hero-contact-button"
                                    >
                                        Request Service
                                    </Link>
                                    <a v-else href="#contact-information" class="hero-contact-button" @click="activeProfileTab = 'business'">
                                        Contact supplier
                                    </a>
                                </div>
                            </div>
                        </header>

                        <section id="company-profile" class="content-flow">
                            <div class="profile-tabs-layout">
                                <nav class="profile-tabs-nav" aria-label="Company profile sections">
                                    <button
                                        v-for="tab in profileTabs"
                                        :key="tab.key"
                                        type="button"
                                        class="profile-tab-button"
                                        :class="{ 'is-active': activeProfileTab === tab.key }"
                                        @click="activeProfileTab = tab.key"
                                    >
                                        {{ tab.label }}
                                    </button>
                                </nav>

                                <div class="profile-tab-panel">
                                    <section v-if="activeProfileTab === 'about'" class="content-section overview-section">
                                        <div class="section-head">
                                            <span class="section-kicker">About</span>
                                        </div>

                                        <div class="content-section-body">
                                            <p class="body-copy overview-copy">
                                                {{ service.overview }}
                                            </p>
                                        </div>
                                    </section>

                                    <section v-else-if="activeProfileTab === 'categories'" class="content-section">
                                        <div class="section-head">
                                            <span class="section-kicker">Categories</span>
                                        </div>

                                        <div class="content-section-body">
                                            <div class="profile-section-grid">
                                                <article v-for="section in categorySections" :key="section.key" class="profile-data-card">
                                                    <span class="profile-data-card-title">{{ section.title }}</span>
                                                    <p v-if="section.body" class="profile-data-card-body">{{ section.body }}</p>
                                                </article>
                                            </div>
                                        </div>
                                    </section>

                                    <section v-else-if="activeProfileTab === 'brands'" class="content-section">
                                        <div class="section-head">
                                            <span class="section-kicker">Brands</span>
                                        </div>

                                        <div class="content-section-body">
                                            <div class="profile-section-grid">
                                                <article v-for="section in brandSections" :key="section.key" class="profile-data-card">
                                                    <p class="profile-data-card-body">{{ section.title }}</p>
                                                    <p v-if="section.body" class="profile-data-card-body">{{ section.body }}</p>
                                                </article>
                                            </div>
                                        </div>
                                    </section>

                                    <section v-else-if="activeProfileTab === 'ports'" class="content-section">
                                        <div class="section-head">
                                            <span class="section-kicker">Service Ports</span>
                                        </div>

                                        <div class="content-section-body">
                                            <div class="profile-section-grid">
                                                <article v-for="group in coverageGroups" :key="group.country_code" class="profile-data-card">
                                                    <span class="profile-data-card-title">{{ group.country_name }}</span>
                                                    <p class="profile-data-card-body">{{ group.ports.map((port) => port.port_name).join(', ') }}</p>
                                                </article>
                                            </div>
                                        </div>
                                    </section>

                                    <section v-else-if="activeProfileTab === 'business'" class="content-section">
                                        <div class="section-head">
                                            <span class="section-kicker">Contact Information</span>
                                        </div>

                                        <div class="content-section-body business-profile-stack">
                                            <section id="contact-information" v-if="primaryContactItems.length || socialIconItems.length || companyDetails.length" class="embedded-contact-section">
                                                <div v-if="!canViewContactDetails" class="contact-panel-body contact-panel-locked embedded-contact-locked">
                                                    <div class="locked-card">
                                                        <h3>{{ lockedContactTitle }}</h3>
                                                        <p class="body-copy locked-copy">
                                                            {{ lockedContactText }}
                                                        </p>

                                                        <div v-if="showContactAuthActions" class="locked-actions">
                                                            <Link href="/login" class="hero-action hero-action-primary">
                                                                Sign In
                                                            </Link>
                                                            <Link href="/register" class="hero-action hero-action-secondary">
                                                                Create Account
                                                            </Link>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div v-else class="contact-panel-body">
                                                    <div class="contact-info-grid">
                                                        <component
                                                            v-for="item in primaryContactItems"
                                                            :key="item.key"
                                                            :is="item.href ? 'a' : 'div'"
                                                            :href="item.href || undefined"
                                                            :target="item.href && item.external ? '_blank' : null"
                                                            :rel="item.href && item.external ? 'noopener' : null"
                                                            class="profile-data-card contact-info-card"
                                                        >
                                                            <span class="profile-data-card-title">{{ item.label }}</span>
                                                            <p class="profile-data-card-body contact-card-value">{{ item.value }}</p>
                                                        </component>

                                                        <article
                                                            v-for="detail in companyDetails"
                                                            :key="detail.key"
                                                            class="profile-data-card contact-info-card contact-info-card-wide"
                                                        >
                                                            <span class="profile-data-card-title">{{ detail.label }}</span>
                                                            <p class="profile-data-card-body contact-card-value">{{ detail.value }}</p>
                                                        </article>

                                                        <article v-if="socialIconItems.length" class="profile-data-card contact-info-card contact-info-card-wide">
                                                            <span class="profile-data-card-title">Social Media</span>
                                                            <div class="contact-actions">
                                                            <a
                                                                v-for="item in socialIconItems"
                                                                :key="item.key"
                                                                :href="item.href"
                                                                :target="item.external ? '_blank' : null"
                                                                :rel="item.external ? 'noopener' : null"
                                                                class="contact-action"
                                                                :title="item.label"
                                                                :aria-label="item.label"
                                                            >
                                                                <span class="contact-action-shell">
                                                                    <svg v-if="item.icon === 'instagram'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                                        <rect width="20" height="20" x="2" y="2" rx="5" ry="5"/>
                                                                        <path d="M16 11.37a4 4 0 1 1-3.37-3.37 4 4 0 0 1 3.37 3.37z"/>
                                                                        <line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/>
                                                                    </svg>
                                                                    <svg v-else-if="item.icon === 'linkedin'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                                        <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/>
                                                                        <rect width="4" height="12" x="2" y="9"/>
                                                                        <circle cx="4" cy="4" r="2"/>
                                                                    </svg>
                                                                    <svg v-else-if="item.icon === 'facebook'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                                        <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                                                                    </svg>
                                                                    <svg v-else-if="item.icon === 'twitter'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                                        <path d="m18 3-5.5 6.3L8 3H3l7 9-7 9h5l5.8-6.7L18 21h5l-7.3-9L23 3z"/>
                                                                    </svg>
                                                                    <svg v-else-if="item.icon === 'telegram'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                                        <path d="M21.2 2.8 2.9 10.1a1 1 0 0 0 .1 1.9l4.6 1.5 1.6 5.1a1 1 0 0 0 1.8.2l2.6-3.5 4.5 3.3a1 1 0 0 0 1.6-.6L22 3.8a1 1 0 0 0-1.3-1z"/>
                                                                        <path d="m7.6 13.5 9.7-7.3"/>
                                                                    </svg>
                                                                    <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                                        <circle cx="12" cy="12" r="10"/>
                                                                        <path d="M2 12h20"/>
                                                                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10Z"/>
                                                                    </svg>
                                                                </span>
                                                            </a>
                                                            </div>
                                                        </article>
                                                    </div>
                                                </div>
                                            </section>
                                        </div>
                                    </section>

                                    <section v-else class="content-section">
                                        <div class="section-head">
                                            <span class="section-kicker">Reviews</span>
                                        </div>

                                        <div class="content-section-body">
                                            <div class="reviews-stack">
                                                <section v-if="isLoadingReviews && !reviewsLoaded" class="review-placeholder-card">
                                                    <strong class="review-placeholder-title">Loading reviews...</strong>
                                                    <p class="body-copy review-placeholder-copy">
                                                        Buyer ratings, comments and reply controls are being prepared.
                                                    </p>
                                                </section>

                                                <template v-else>
                                                    <section
                                                        v-if="!reviewEligibility.can_submit_review"
                                                        class="review-access-banner"
                                                    >
                                                        <p class="body-copy review-access-copy">
                                                            {{ reviewAccessMessage }}
                                                        </p>
                                                    </section>

                                                    <section class="review-summary-card">
                                                        <div class="review-summary-score">
                                                            <strong>{{ averageRatingText }}</strong>
                                                            <span>/ 5</span>
                                                        </div>
                                                        <div class="review-summary-meta">
                                                            <div class="review-stars-static" aria-hidden="true">
                                                                <span
                                                                    v-for="star in 5"
                                                                    :key="`summary-${star}`"
                                                                    class="review-star-static"
                                                                    :class="{ 'is-filled': star <= Math.round(Number(reviewSummary.average ?? 0)) }"
                                                                >★</span>
                                                            </div>
                                                            <p class="body-copy review-summary-copy">
                                                                {{ Number(reviewSummary.count ?? 0) }} review{{ Number(reviewSummary.count ?? 0) === 1 ? '' : 's' }}
                                                            </p>
                                                        </div>
                                                    </section>

                                                    <section
                                                        v-if="reviewEligibility.can_submit_review"
                                                        class="review-form-card"
                                                    >
                                                    <div class="review-form-head">
                                                        <div>
                                                            <strong class="review-form-title">Rate this supplier</strong>
                                                            <p class="body-copy review-form-copy">{{ reviewAccessMessage }}</p>
                                                        </div>
                                                    </div>

                                                    <label v-if="reviewTargets.length > 1" class="review-form-field">
                                                        <span class="profile-data-card-title">Confirmed Work</span>
                                                        <select v-model="selectedReviewTargetId" class="review-select">
                                                            <option
                                                                v-for="target in reviewTargets"
                                                                :key="target.offer_id"
                                                                :value="target.offer_id"
                                                            >
                                                                {{ target.reference_no }} - {{ target.status_label }}
                                                            </option>
                                                        </select>
                                                    </label>

                                                    <div v-else-if="selectedReviewTarget" class="review-selected-target">
                                                        <span class="profile-data-card-title">Confirmed Work</span>
                                                        <strong>{{ selectedReviewTarget.reference_no }}</strong>
                                                    </div>

                                                    <div
                                                        v-if="selectedTargetHasSavedReview && !showReviewEditor"
                                                        class="review-saved-state"
                                                    >
                                                        <p class="body-copy review-saved-copy">
                                                            You already published a review for this confirmed work. Use the edit action below to update it.
                                                        </p>
                                                        <button
                                                            type="button"
                                                            class="hero-action hero-action-secondary"
                                                            @click="startEditingReview()"
                                                        >
                                                            Edit Saved Review
                                                        </button>
                                                    </div>

                                                    <template v-else>
                                                        <div class="review-form-field">
                                                            <span class="profile-data-card-title">Star Rating</span>
                                                            <div class="review-stars-input">
                                                                <button
                                                                    v-for="star in 5"
                                                                    :key="`rating-${star}`"
                                                                    type="button"
                                                                    class="review-star-button"
                                                                    :class="{ 'is-filled': star <= activeReviewRating }"
                                                                    @mouseenter="hoveredRating = star"
                                                                    @mouseleave="hoveredRating = 0"
                                                                    @click="selectReviewRating(star)"
                                                                >★</button>
                                                            </div>
                                                            <p v-if="reviewForm.errors.rating" class="review-error">{{ reviewForm.errors.rating }}</p>
                                                        </div>

                                                        <label class="review-form-field">
                                                            <span class="profile-data-card-title">Buyer Comment</span>
                                                            <textarea
                                                                v-model="reviewForm.review_text"
                                                                class="review-textarea"
                                                                rows="5"
                                                                maxlength="2000"
                                                                placeholder="Share your experience with this supplier."
                                                            />
                                                            <p v-if="reviewForm.errors.review_text" class="review-error">{{ reviewForm.errors.review_text }}</p>
                                                        </label>

                                                        <div class="review-form-actions">
                                                            <button
                                                                v-if="isEditingReview"
                                                                type="button"
                                                                class="hero-action hero-action-secondary"
                                                                @click="cancelReviewEditing"
                                                            >
                                                                Cancel
                                                            </button>
                                                            <button
                                                                type="button"
                                                                class="hero-action hero-action-primary review-submit-button"
                                                                :disabled="reviewForm.processing || !reviewForm.offer_id || !reviewForm.rating || !reviewForm.review_text.trim()"
                                                                @click="submitReview"
                                                            >
                                                                {{ isEditingReview ? 'Update Review' : 'Publish Review' }}
                                                            </button>
                                                        </div>
                                                        </template>
                                                    </section>

                                                    <section v-if="hasPublicReviews" class="review-list">
                                                        <article
                                                            v-for="item in reviewItems"
                                                            :key="item.id"
                                                            class="review-entry-card"
                                                        >
                                                        <div class="review-entry-head">
                                                            <div>
                                                                <strong class="review-entry-company">{{ item.buyer_company }}</strong>
                                                                <p class="review-entry-meta">
                                                                    {{ item.reference_no }} · {{ formatReviewDate(item.created_at) }}
                                                                </p>
                                                            </div>
                                                            <div class="review-entry-head-side">
                                                                <div class="review-entry-rating">
                                                                    <div class="review-stars-static" aria-hidden="true">
                                                                        <span
                                                                            v-for="star in 5"
                                                                            :key="`${item.id}-${star}`"
                                                                            class="review-star-static"
                                                                            :class="{ 'is-filled': star <= Number(item.rating ?? 0) }"
                                                                        >★</span>
                                                                    </div>
                                                                    <span class="review-rating-number">{{ item.rating }}/5</span>
                                                                </div>

                                                                <div v-if="canEditReviewItem(item)" class="review-entry-actions review-entry-actions-owner">
                                                                    <button
                                                                        type="button"
                                                                        class="hero-action hero-action-secondary review-edit-button"
                                                                        @click="startEditingReview(item.offer_id)"
                                                                    >
                                                                        Edit
                                                                    </button>
                                                                    <button
                                                                        v-if="item.can_delete_review"
                                                                        type="button"
                                                                        class="hero-action hero-action-secondary review-edit-button review-delete-button"
                                                                        @click="deleteBuyerReview(item)"
                                                                    >
                                                                        {{ reviewActionCopy.deleteReview }}
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <p class="body-copy review-entry-text">{{ item.review_text }}</p>

                                                        <div v-if="item.can_reply && !item.seller_reply" class="review-entry-actions review-entry-actions-reply">
                                                            <button
                                                                type="button"
                                                                class="hero-action hero-action-secondary review-edit-button"
                                                                @click="openSellerReplyEditor(item)"
                                                            >
                                                                Reply
                                                            </button>
                                                        </div>

                                                        <div v-if="item.can_reply && isSellerReplyEditorOpen(item)" class="review-reply-editor">
                                                            <label class="review-form-field">
                                                                <span class="profile-data-card-title">Supplier Reply</span>
                                                                <textarea
                                                                    v-model="sellerReplyForm.seller_reply"
                                                                    class="review-textarea"
                                                                    rows="4"
                                                                    maxlength="2000"
                                                                    placeholder="Write your reply to this buyer review."
                                                                />
                                                                <p v-if="sellerReplyForm.errors.seller_reply" class="review-error">{{ sellerReplyForm.errors.seller_reply }}</p>
                                                            </label>

                                                            <div class="review-reply-actions">
                                                                <button
                                                                    type="button"
                                                                    class="hero-action hero-action-secondary review-edit-button"
                                                                    @click="cancelSellerReplyEditing"
                                                                >
                                                                    Cancel
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    class="hero-action hero-action-primary review-submit-button"
                                                                    :disabled="sellerReplyForm.processing || !sellerReplyForm.seller_reply.trim()"
                                                                    @click="submitSellerReply(item)"
                                                                >
                                                                    Save Reply
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <div v-if="item.seller_reply" class="review-reply-card">
                                                            <div class="review-reply-head">
                                                                <span class="profile-data-card-title">{{ service.vendor.name }}</span>
                                                                <div class="review-reply-head-side">
                                                                    <span class="review-reply-label">Reply</span>
                                                                    <div v-if="item.can_reply" class="review-reply-card-actions">
                                                                        <button
                                                                            type="button"
                                                                            class="hero-action hero-action-secondary review-edit-button"
                                                                            @click="openSellerReplyEditor(item)"
                                                                        >
                                                                            Edit Reply
                                                                        </button>
                                                                        <button
                                                                            v-if="item.can_delete_reply"
                                                                            type="button"
                                                                            class="hero-action hero-action-secondary review-edit-button review-delete-button"
                                                                            @click="deleteSellerReply(item)"
                                                                        >
                                                                            {{ reviewActionCopy.deleteReply }}
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <p class="body-copy review-entry-text">{{ item.seller_reply }}</p>
                                                            <span class="review-reply-date">{{ formatReviewDate(item.seller_replied_at) }}</span>
                                                        </div>
                                                        </article>
                                                    </section>

                                                    <section v-else class="review-placeholder-card">
                                                        <strong class="review-placeholder-title">No reviews yet</strong>
                                                        <p class="body-copy review-placeholder-copy">
                                                            This supplier has not received a buyer review yet.
                                                        </p>
                                                    </section>
                                                </template>
                                            </div>
                                        </div>
                                    </section>
                                </div>
                            </div>
                        </section>
                    </article>

                </div>
            </div>

            <div ref="similarSectionSentinel" class="similar-section-sentinel" aria-hidden="true"></div>

            <div v-if="deleteModalState" class="delete-modal-backdrop" @click.self="closeDeleteReviewModal">
                <div class="delete-modal">
                    <button type="button" class="delete-modal-close" @click="closeDeleteReviewModal">&times;</button>
                    <h3 class="directory-section-title">{{ deleteModalTitle }}</h3>
                    <p class="delete-modal-copy">{{ deleteModalBody }}</p>
                    <div class="delete-modal-summary">
                        <span v-for="(summaryItem, index) in deleteModalSummaryItems" :key="`${deleteModalState.mode}-${index}`">
                            {{ summaryItem }}
                        </span>
                    </div>
                    <div class="delete-modal-actions">
                        <button type="button" class="hero-action hero-action-secondary delete-cancel-button" @click="closeDeleteReviewModal">
                            {{ reviewActionCopy.cancelDelete }}
                        </button>
                        <button type="button" class="hero-action delete-confirm-button" @click="confirmDeleteReviewAction">
                            {{ deleteModalConfirmLabel }}
                        </button>
                    </div>
                </div>
            </div>

            <section v-if="showSimilarVendorsSection" class="main-related-section related-full-width">
                <div class="sidebar-head main-related-head">
                    <div>
                        <span class="sidebar-kicker">Similar vendors</span>
                        <h2>Other vendors in this category</h2>
                    </div>

                    <div v-if="similarVendorServices.length > similarVisibleCount" class="related-nav">
                        <button type="button" class="related-nav-button" :disabled="!canSlideSimilarPrev" @click="slideSimilarPrev" aria-label="Previous vendors">
                            <svg class="related-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m15 18-6-6 6-6" />
                            </svg>
                            
                        </button>
                        <button type="button" class="related-nav-button" :disabled="!canSlideSimilarNext" @click="slideSimilarNext" aria-label="Next vendors">
                            <svg class="related-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m9 18 6-6-6-6" />
                            </svg>
                            
                        </button>
                    </div>
                </div>

                <div class="main-related-grid">
                    <ServiceListingCard
                        v-for="item in visibleSimilarVendorServices"
                        :key="item.id"
                        :item="item"
                        :label="'View Details'"
                        :no-description="'Company overview has not been added yet.'"
                    />
                </div>
            </section>
        </section>
    </MainLayout>
</template>

<style scoped>
.service-shell {
    --detail-panel-height: 560px;
    --overview-panel-height: 560px;
    --badge-radius: 10px;
    --badge-font-size: 0.8rem;
    --badge-font-weight: 600;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    padding: 16px 0 56px;
    background: transparent;
    overflow-x: clip;
}

.page-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 0;
    align-items: start;
}

.main-column {
    min-width: 0;
}

.surface-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #ffffff;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
}

.main-surface {
    overflow: hidden;
}

.main-related-section {
    margin-top: 20px;
    min-width: 0;
}

.related-full-width {
    position: relative;
    width: 100%;
    max-width: 100%;
    overflow: visible;
}

.hero-block {
    padding: 24px 24px 0;
    border-bottom: 0;
}

.hero-surface {
    display: grid;
    gap: 26px;
    padding: 22px 24px 24px;
    border-radius: 12px;
    background: #f8fafb;
}

.hero-utility-row {
    display: flex;
    align-items: center;
    justify-content: flex-start;
}

.hero-back-link {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    color: rgba(4, 21, 31, 0.72);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 700;
    transition: color 160ms ease, transform 160ms ease;
}

.hero-back-link:hover {
    color: #04151f;
    transform: translateX(-1px);
}

.hero-back-link svg {
    width: 16px;
    height: 16px;
    flex: 0 0 16px;
}

.hero-brand {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 24px;
    align-items: center;
    min-width: 0;
}

.hero-brand-main {
    display: flex;
    align-items: center;
    gap: 22px;
    min-width: 0;
}

.hero-media {
    display: grid;
    gap: 0;
    align-content: center;
    flex: 0 0 auto;
}

.hero-showcase {
    width: 100%;
}

.hero-showcase-logo-stage {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 96px;
    min-width: 96px;
    height: 84px;
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    background: rgba(255, 255, 255, 0.98);
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
}

.hero-showcase-logo-display {
    display: block;
    width: 72px;
    max-width: 72px;
    height: 60px;
    object-fit: contain;
}

.hero-showcase-initials {
    color: #0f172a;
    font-size: 1.55rem;
    font-weight: 700;
    letter-spacing: -0.04em;
}

.hero-showcase-initials-large {
    font-size: clamp(1.55rem, 2vw, 1.9rem);
}

.hero-copy {
    display: grid;
    gap: 8px;
    align-content: end;
    min-width: 0;
}

.hero-copy-profile {
    padding: 0;
}

.hero-title {
    margin: 0;
    color: #04151f;
    font-size: clamp(2rem, 3vw, 3rem);
    line-height: 1.08;
    letter-spacing: -0.04em;
    text-wrap: balance;
}

.hero-rating-row {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    min-height: 24px;
}

.hero-rating-stars {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.hero-rating-star {
    color: #cbd5e1;
    font-size: 1rem;
    line-height: 1;
}

.hero-rating-star.is-filled {
    color: #f59e0b;
}

.hero-rating-value {
    color: #0f172a;
    font-size: 0.94rem;
    font-weight: 700;
    line-height: 1;
}

.hero-rating-count {
    color: #64748b;
    font-size: 0.88rem;
    font-weight: 500;
    line-height: 1.2;
}

.hero-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 18px;
    border-radius: var(--badge-radius);
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: var(--badge-font-weight);
    transition: transform 160ms ease, box-shadow 160ms ease, background-color 160ms ease, color 160ms ease, border-color 160ms ease;
}

.hero-action:hover {
    transform: translateY(-1px);
}

.hero-action-primary {
    background: linear-gradient(135deg, #6da4ff 0%, #4f8cf7 100%);
    color: #ffffff;
    box-shadow: 0 18px 34px rgba(79, 140, 247, 0.24);
}

.hero-action-secondary {
    border: 1px solid #d9e2ef;
    background: #ffffff;
    color: #0f172a;
}

.hero-contact-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    align-self: center;
    min-height: 40px;
    padding: 0 20px;
    border-radius: 8px;
    border: 1px solid rgba(37, 99, 235, 0.14);
    background: #ffffff;
    color: #4f46e5;
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 700;
    white-space: nowrap;
    box-shadow: 0 10px 20px rgba(15, 23, 42, 0.06);
    transition: transform 160ms ease, box-shadow 160ms ease;
}

.hero-contact-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 14px 24px rgba(15, 23, 42, 0.1);
}

.gallery-strip {
    display: flex;
    flex-wrap: nowrap;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 4px;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}

.gallery-strip::-webkit-scrollbar {
    height: 6px;
}

.gallery-strip::-webkit-scrollbar-track {
    background: transparent;
}

.gallery-strip::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 999px;
}

.gallery-thumb {
    width: 68px;
    height: 68px;
    flex: 0 0 68px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    cursor: pointer;
    box-shadow: 0 10px 18px rgba(15, 23, 42, 0.05);
    transition: transform 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
}

.gallery-thumb:hover {
    transform: translateY(-1px);
    border-color: #cbd5e1;
    box-shadow: 0 14px 24px rgba(15, 23, 42, 0.08);
}

.gallery-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.content-flow {
    display: grid;
    gap: 30px;
    padding: 28px 32px 32px;
}

.similar-section-sentinel {
    width: 100%;
    height: 1px;
}

.profile-tabs-layout {
    display: grid;
    grid-template-columns: 220px minmax(0, 1fr);
    gap: 18px;
    align-items: start;
    padding: 18px;
    border-radius: 12px;
    background: #f8fafb;
}

.profile-tabs-nav {
    display: grid;
    gap: 10px;
    position: sticky;
    top: 24px;
}

.profile-tab-button {
    min-height: 46px;
    padding: 11px 14px;
    border: 1px solid #dbe4ef;
    border-radius: 12px;
    background: #ffffff;
    color: #475569;
    text-align: left;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease, transform 160ms ease;
}

.profile-tab-button:hover {
    transform: translateY(-1px);
    border-color: #cbd5e1;
    color: #0f172a;
}

.profile-tab-button.is-active {
    background: #0f172a;
    border-color: #0f172a;
    color: #ffffff;
    box-shadow: 0 16px 30px rgba(15, 23, 42, 0.16);
}

.profile-tab-panel {
    display: grid;
    gap: 16px;
    min-width: 0;
    min-height: var(--detail-panel-height);
}

.business-profile-stack {
    display: grid;
    gap: 18px;
}

.embedded-contact-section {
    display: grid;
    gap: 14px;
    padding: 18px 20px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
}

.embedded-contact-locked {
    min-height: 0;
}

.content-section {
    display: grid;
    gap: 14px;
    min-height: var(--detail-panel-height);
    align-content: start;
}

.overview-section {
    min-height: var(--overview-panel-height);
}

.overview-section .content-section-body {
    padding: 18px 20px;
    border-radius: 12px;
    background: #f8fafb;
}

.section-kicker {
    color: rgba(4, 21, 31, 0.64);
    font-size: 0.96rem;
    font-weight: 650;
    letter-spacing: 0;
    text-transform: none;
}

.body-copy {
    margin: 0;
    color: #475569;
    font-size: 0.93rem;
    font-weight: 400;
    line-height: 1.8;
}

.content-section-body {
    min-height: 0;
    max-height: calc(var(--detail-panel-height) - 72px);
    overflow-y: auto;
    padding-right: 8px;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}

.content-section-body::-webkit-scrollbar {
    width: 6px;
}

.content-section-body::-webkit-scrollbar-track {
    background: transparent;
}

.content-section-body::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 999px;
}

.overview-copy {
    max-height: none;
}

.profile-section-grid {
    display: grid;
    gap: 14px;
}

.profile-data-card {
    display: grid;
    gap: 12px;
    padding: 16px 18px;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
}

.profile-data-card-title {
    color: #0f172a;
    font-size: 0.9rem;
    font-weight: 700;
}

.profile-data-card-body {
    margin: 0;
    color: #475569;
    font-size: 0.93rem;
    font-weight: 400;
    line-height: 1.75;
}

.sidebar-head {
    display: grid;
    gap: 6px;
    margin-bottom: 14px;
}

.sidebar-kicker {
    color: rgba(4, 21, 31, 0.64);
    font-size: 0.96rem;
    font-weight: 650;
}

.sidebar-head h2 {
    margin: 0;
    color: #020617;
    font-size: 1rem;
    font-weight: 650;
    letter-spacing: -0.02em;
}

.contact-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.contact-info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.contact-panel-body {
    min-height: 0;
}

.contact-panel-locked {
    display: grid;
    align-content: start;
    min-height: 575px;
}

.locked-card {
    display: grid;
    gap: 14px;
    padding: 8px 0 2px;
}

.locked-card h3 {
    margin: 0;
    color: #020617;
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.45;
}

.locked-copy {
    max-width: none;
}

.review-placeholder-card {
    display: grid;
    gap: 10px;
    padding: 18px 20px;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
}

.review-placeholder-title {
    color: #020617;
    font-size: 0.96rem;
    font-weight: 700;
}

.review-placeholder-copy {
    max-width: 56ch;
}

.reviews-stack {
    display: grid;
    gap: 14px;
}

.review-access-banner {
    padding: 0 2px;
}

.review-access-copy {
    color: #64748b;
    font-size: 0.9rem;
    line-height: 1.6;
}

.review-summary-card,
.review-form-card,
.review-entry-card,
.review-reply-card,
.review-reply-editor {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #ffffff;
}

.review-summary-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 18px 20px;
}

.review-summary-score {
    display: inline-flex;
    align-items: baseline;
    gap: 8px;
    color: #020617;
}

.review-summary-score strong {
    font-size: 2rem;
    line-height: 1;
    letter-spacing: -0.04em;
}

.review-summary-score span {
    color: #64748b;
    font-size: 0.96rem;
    font-weight: 600;
}

.review-summary-meta {
    display: grid;
    justify-items: end;
    gap: 8px;
}

.review-summary-copy {
    color: #64748b;
}

.review-stars-static {
    display: inline-flex;
    gap: 6px;
}

.review-star-static {
    color: #cbd5e1;
    font-size: 1rem;
    line-height: 1;
}

.review-star-static.is-filled {
    color: #f59e0b;
}

.review-form-card {
    display: grid;
    gap: 16px;
    padding: 18px 20px;
}

.review-form-head {
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 16px;
}

.review-form-title {
    color: #020617;
    font-size: 1rem;
    font-weight: 700;
}

.review-form-copy {
    margin-top: 6px;
}

.review-form-field,
.review-selected-target {
    display: grid;
    gap: 10px;
}

.review-saved-state {
    display: grid;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.review-saved-copy {
    color: #475569;
}

.review-select,
.review-textarea {
    width: 100%;
    border: 1px solid #dbe4ef;
    border-radius: 10px;
    background: #ffffff;
    color: #0f172a;
    font-size: 0.94rem;
}

.review-select {
    min-height: 46px;
    padding: 0 14px;
}

.review-textarea {
    min-height: 142px;
    padding: 14px;
    resize: vertical;
    line-height: 1.7;
}

.review-stars-input {
    display: inline-flex;
    gap: 8px;
}

.review-star-button {
    padding: 0;
    border: 0;
    background: transparent;
    color: #cbd5e1;
    font-size: 1.9rem;
    line-height: 1;
    cursor: pointer;
    transition: transform 160ms ease, color 160ms ease;
}

.review-star-button:hover {
    transform: translateY(-1px);
}

.review-star-button.is-filled {
    color: #f59e0b;
}

.review-error {
    margin: 0;
    color: #dc2626;
    font-size: 0.84rem;
    font-weight: 600;
}

.review-form-actions {
    display: flex;
    justify-content: flex-start;
}

.review-submit-button[disabled] {
    opacity: 0.6;
    cursor: not-allowed;
    box-shadow: none;
}

.review-list {
    display: grid;
    gap: 14px;
}

.review-entry-card {
    display: grid;
    gap: 14px;
    padding: 18px 20px;
}

.review-entry-head {
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 16px;
}

.review-entry-company {
    color: #020617;
    font-size: 0.98rem;
    font-weight: 700;
}

.review-entry-meta,
.review-rating-number,
.review-reply-date {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 0.86rem;
    font-weight: 500;
}

.review-entry-rating {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
}

.review-entry-head-side {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 12px;
}

.review-entry-text {
    color: #334155;
}

.review-entry-actions {
    display: flex;
    justify-content: flex-start;
    flex-wrap: wrap;
    gap: 10px;
}

.review-entry-actions-reply {
    justify-content: flex-end;
}

.review-entry-actions-owner {
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    padding-left: 12px;
    border-left: 1px solid #e2e8f0;
}

.review-reply-editor {
    display: grid;
    gap: 12px;
    padding: 14px 16px;
    background: #f8fafc;
}

.review-reply-actions {
    display: flex;
    justify-content: flex-start;
    gap: 10px;
}

.review-edit-button {
    min-height: 38px;
    padding: 0 14px;
    font-size: 0.84rem;
}

.review-delete-button {
    border-color: rgba(239, 68, 68, 0.24);
    color: #dc2626;
    background: rgba(254, 242, 242, 0.9);
}

.review-reply-card {
    display: grid;
    gap: 8px;
    padding: 14px 16px;
    background: #f8fafc;
}

.review-reply-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.review-reply-head-side {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 12px;
}

.review-reply-label {
    color: #64748b;
    font-size: 0.82rem;
    font-weight: 700;
    white-space: nowrap;
}

.review-reply-card-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
    padding-left: 12px;
    border-left: 1px solid #e2e8f0;
}

.delete-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1500;
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
    background: #ffffff;
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
    background: #ffffff;
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
    box-shadow: none;
}

.delete-confirm-button {
    border: 1px solid #ef4444;
    background: #ef4444;
    color: #ffffff;
    box-shadow: 0 12px 24px rgba(239, 68, 68, 0.18);
}

.locked-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.contact-info-card {
    text-decoration: none;
    color: inherit;
    transition: transform 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
}

.contact-info-card:hover {
    transform: translateY(-1px);
    border-color: #cbd5e1;
    box-shadow: 0 10px 20px rgba(15, 23, 42, 0.06);
}

.contact-info-card-wide {
    grid-column: 1 / -1;
}

.contact-card-value {
    word-break: break-word;
}

.contact-action {
    text-decoration: none;
    color: inherit;
}

.contact-action-shell {
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    transition: transform 160ms ease, color 160ms ease, border-color 160ms ease;
}

.contact-action:hover .contact-action-shell {
    transform: translateY(-1px);
    color: #2563eb;
    border-color: #bfdbfe;
}

.contact-action-shell svg {
    width: 15px;
    height: 15px;
}

.coverage-subsection {
    width: 100%;
    min-width: 0;
    margin-top: 0;
    display: grid;
    align-content: start;
    gap: 12px;
    min-height: 580px;
    max-height: 580px;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 6px;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}

.coverage-subsection::-webkit-scrollbar {
    width: 6px;
}

.coverage-subsection::-webkit-scrollbar-track {
    background: transparent;
}

.coverage-subsection::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 999px;
}

.social-block-title {
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0;
    text-transform: none;
}

.sidebar-list {
    display: grid;
    gap: 14px;
}

.main-related-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-top: 18px;
}

.main-related-head {
    display: flex;
    align-items: flex-end;
    justify-content: flex-start;
    gap: 16px;
}

.related-carousel {
    position: relative;
}

.related-nav {
    position: absolute;
    top: 214px;
    left: 10px;
    right: 10px;
    z-index: 3;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    pointer-events: none;
}

.related-nav-button {
    pointer-events: auto;
    width: 46px;
    height: 46px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(15, 23, 42, 0.14);
    border-radius: 10px;
    background: rgba(15, 23, 42, 0.92);
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.28);
    backdrop-filter: blur(12px);
    color: #ffffff;
    font-size: 0;
    font-weight: 600;
    line-height: 1;
    cursor: pointer;
    transition: transform 160ms ease, background-color 160ms ease, color 160ms ease, border-color 160ms ease, opacity 160ms ease, box-shadow 160ms ease;
}

.related-nav-icon {
    width: 20px;
    height: 20px;
    flex: 0 0 20px;
}

.related-nav-button:hover:not(:disabled) {
    transform: translateY(-1px);
    background: #020617;
    color: #ffffff;
    border-color: #020617;
    box-shadow: 0 22px 42px rgba(2, 6, 23, 0.36);
}

.related-nav-button:disabled {
    opacity: 0.34;
    cursor: not-allowed;
}

@media (max-width: 1180px) {
    .main-related-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 960px) {
    .profile-tabs-layout {
        grid-template-columns: 1fr;
    }

    .hero-brand {
        grid-template-columns: 1fr;
        gap: 20px;
        align-items: start;
    }

    .hero-brand-main {
        align-items: center;
    }

    .hero-showcase-logo-stage {
        width: 92px;
        min-width: 92px;
        height: 82px;
    }

    .profile-tabs-nav {
        grid-template-columns: repeat(5, minmax(0, 1fr));
        position: static;
    }

    .profile-tab-button {
        text-align: center;
        padding: 11px 10px;
    }

    .contact-info-grid {
        grid-template-columns: 1fr;
    }

    .review-summary-card,
    .review-entry-head {
        grid-template-columns: 1fr;
        flex-direction: column;
        align-items: start;
    }

    .review-summary-meta {
        justify-items: start;
    }

    .review-reply-head {
        flex-direction: column;
        align-items: start;
    }

    .review-entry-head-side,
    .review-entry-rating,
    .review-reply-head-side {
        justify-content: flex-start;
    }

    .review-entry-actions-reply,
    .review-entry-actions-owner,
    .review-reply-card-actions {
        justify-content: flex-start;
        padding-left: 0;
        border-left: 0;
    }

    .main-related-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .related-nav {
        top: 50%;
        left: 0;
        right: 0;
        transform: translateY(-50%);
    }
}

@media (max-width: 720px) {
    .hero-block {
        padding: 20px 20px 0;
    }

    .hero-surface {
        gap: 20px;
        padding: 18px;
    }

    .content-flow {
        padding: 22px 22px 24px;
    }

    .profile-tabs-nav {
        grid-template-columns: 1fr;
    }

    .hero-title {
        font-size: 1.8rem;
    }

    .review-stars-input {
        flex-wrap: wrap;
    }

    .delete-modal {
        padding: 24px 20px 20px;
    }

    .delete-modal-actions {
        flex-direction: column;
    }

    .main-related-grid {
        grid-template-columns: 1fr;
    }

    .related-nav {
        top: 50%;
        left: 0;
        right: 0;
        transform: translateY(-50%);
    }
}

.sidebar-card {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #ffffff;
    text-decoration: none;
    transition: transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease;
}

.sidebar-card:hover {
    transform: translateY(-2px);
    border-color: #bfdbfe;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
}

.sidebar-card-cover {
    position: relative;
    aspect-ratio: 16 / 8.8;
    overflow: hidden;
}

.sidebar-cover-fallback {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.2), transparent 24%),
        radial-gradient(circle at left bottom, rgba(15, 118, 110, 0.35), transparent 28%),
        linear-gradient(135deg, rgba(255, 255, 255, 0.08), transparent 54%);
}

.sidebar-cover-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    max-width: calc(100% - 24px);
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.9);
    color: #0f172a;
    font-size: 0.68rem;
    font-weight: 700;
}

.sidebar-cover-art {
    position: absolute;
    right: 16px;
    bottom: 16px;
    width: 52px;
    height: 52px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.24);
    backdrop-filter: blur(10px);
}

.sidebar-cover-art svg {
    width: 24px;
    height: 24px;
}

.sidebar-card-body {
    display: grid;
    gap: 9px;
    padding: 15px 16px 16px;
}

.sidebar-title {
    color: #020617;
    font-size: 1.06rem;
    font-weight: 650;
    line-height: 1.35;
}

.sidebar-meta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #64748b;
    font-size: 0.9rem;
    font-weight: 500;
}

.sidebar-meta svg {
    width: 15px;
    height: 15px;
    flex: 0 0 15px;
    color: #94a3b8;
}

.sidebar-cta {
    justify-self: start;
    margin-top: 4px;
    display: inline-flex;
    align-items: center;
    min-height: 38px;
    padding: 0 14px;
    border-radius: var(--badge-radius);
    background: #eff6ff;
    border: 1px solid #dbeafe;
    color: #2563eb;
    font-size: var(--badge-font-size);
    font-weight: var(--badge-font-weight);
}

.lightbox {
    position: fixed;
    inset: 0;
    z-index: 2100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px;
    background: rgba(2, 6, 23, 0.88);
    backdrop-filter: blur(10px);
}

.lightbox-figure {
    margin: 0;
    max-width: min(1100px, 100%);
    max-height: 85vh;
}

.lightbox-image {
    display: block;
    max-width: 100%;
    max-height: 85vh;
    object-fit: contain;
    border-radius: 10px;
}

.lightbox-close,
.lightbox-nav {
    position: absolute;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border: 0;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
    color: #ffffff;
    cursor: pointer;
}

.lightbox-close {
    top: 24px;
    right: 24px;
    font-size: 1.9rem;
}

.lightbox-nav {
    top: 50%;
    transform: translateY(-50%);
    font-size: 2rem;
}

.lightbox-nav-prev {
    left: 24px;
}

.lightbox-nav-next {
    right: 24px;
}

@media (max-width: 1024px) {
    .page-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .service-shell {
        padding: 12px 0 40px;
    }

    .hero-block {
        padding: 20px 20px 0;
    }

    .hero-brand {
        grid-template-columns: 1fr;
        gap: 18px;
    }

    .hero-brand-main {
        gap: 16px;
        align-items: center;
    }

    .hero-showcase-logo-stage {
        width: 84px;
        min-width: 84px;
        height: 76px;
        padding: 10px;
    }

    .hero-showcase-logo-display {
        width: 64px;
        max-width: 64px;
        height: 52px;
    }

    .hero-title {
        font-size: 1.72rem;
    }

    .hero-contact-button,
    .hero-action {
        width: 100%;
    }

    .content-flow {
        gap: 26px;
        padding: 24px 20px 24px;
    }

    .gallery-thumb {
        width: 58px;
        height: 58px;
        flex-basis: 58px;
    }
}
</style>






