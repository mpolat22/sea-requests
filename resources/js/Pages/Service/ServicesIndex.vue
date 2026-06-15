<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import MainLayout from '../../Layouts/MainLayout.vue';
import ServiceListingCard from '../../Components/ServiceListingCard.vue';
import { buildServiceDirectoryUrl } from '../../lib/serviceDirectoryUrl.js';

const props = defineProps({
    categories: {
        type: Array,
        required: true,
    },
    brands: {
        type: Array,
        default: () => [],
    },
    initialSubcategories: {
        type: Array,
        default: () => [],
    },
    suppliersPage: {
        type: Object,
        required: true,
    },
    portIndex: {
        type: Object,
        default: () => ({}),
    },
    countryOptions: {
        type: Array,
        default: () => [],
    },
    filterDataUrls: {
        type: Object,
        default: () => ({
            brands: '',
            subcategories: '',
            ports: '',
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            brands: [],
            countries: [],
            ports: [],
            parentCategories: [],
            subcategories: [],
        }),
    },
    meta: {
        type: Object,
        default: () => ({
            title: 'Services | Sea Requests',
            description: '',
            canonical: '',
            robots: 'index, follow',
            ogImage: '',
            twitterCard: 'summary',
            heroEyebrow: '',
            heroTitle: '',
            heroText: '',
        }),
    },
});

const page = usePage();
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
const defaultHeroCopy = {
    eyebrow: 'Supplier directory',
    title: 'Maritime Service Suppliers',
    text: 'Browse approved supplier companies, compare service coverage and open detailed company profiles.',
};
const heroEyebrow = computed(() => props.meta.heroEyebrow || defaultHeroCopy.eyebrow);
const heroTitle = computed(() => props.meta.heroTitle || defaultHeroCopy.title);
const heroText = computed(() => props.meta.heroText || defaultHeroCopy.text);

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
        matches: ['repair', 'technical', 'engineering', 'maintenance', 'inspection'],
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

const resolveCategoryTheme = (item) => {
    const haystack = [
        item?.primary_category?.name,
        item?.primary_category?.slug,
        item?.secondary_category?.name,
        item?.secondary_category?.slug,
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

    return categoryThemes.find((theme) => theme.matches.some((match) => haystack.includes(match))) ?? defaultCategoryTheme;
};

const countryOptions = computed(() => {
    if (props.countryOptions?.length) {
        return props.countryOptions;
    }

    const names = Object.keys(props.portIndex ?? {})
        .map((value) => normalizeValue(value))
        .filter(Boolean)
        .filter((value) => !/^[A-Z]{2,3}$/.test(value))
        .sort((left, right) => left.localeCompare(right, 'en', { sensitivity: 'base' }));

    return names;
});

const createSortedKey = (values) => [...values]
    .map((value) => normalizeValue(value))
    .filter(Boolean)
    .sort((left, right) => left.localeCompare(right, 'en', { sensitivity: 'base' }))
    .join('|');

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

const ui = computed(() => ({
        filters: 'Filters',
        applyFilters: 'Apply filters',
        clearAll: 'Clear all',
        search: 'Search',
        searchPlaceholder: 'Search service, subcategory, brand, country, port...',
        parentCategory: 'Parent category',
        subcategory: 'Subcategory',
        allSubcategories: 'All subcategories',
        brand: 'Brands',
        allBrands: 'All brands',
        country: 'Country',
        allCountries: 'All countries',
        port: 'Port',
        allPorts: 'All ports',
        chooseCountryFirst: 'Select country first',
        activeFilters: 'Active filters',
        noActiveFilters: 'No active filters',
        results: 'results',
        viewProfile: 'View Details',
        noDescription: 'Company overview has not been added yet.',
        loading: 'Loading more companies...',
        reachedEnd: 'You have reached the end of the directory.',
        serviceCountries: 'Service countries',
        ports: 'ports',
}));

const filters = reactive({
    search: props.filters.search ?? '',
    parentCategories: Array.isArray(props.filters.parentCategories) ? [...props.filters.parentCategories] : [],
    subcategories: Array.isArray(props.filters.subcategories) ? [...props.filters.subcategories] : [],
    brands: Array.isArray(props.filters.brands) ? [...props.filters.brands] : [],
    countries: Array.isArray(props.filters.countries) ? [...props.filters.countries] : [],
    ports: Array.isArray(props.filters.ports) ? [...props.filters.ports] : [],
});
const appliedFilters = reactive({
    parentCategories: Array.isArray(props.filters.parentCategories) ? [...props.filters.parentCategories] : [],
    subcategories: Array.isArray(props.filters.subcategories) ? [...props.filters.subcategories] : [],
    brands: Array.isArray(props.filters.brands) ? [...props.filters.brands] : [],
    countries: Array.isArray(props.filters.countries) ? [...props.filters.countries] : [],
    ports: Array.isArray(props.filters.ports) ? [...props.filters.ports] : [],
});
const appliedSearch = ref(props.filters.search ?? '');

const normalizeValue = (value) => (value ?? '').toString().trim();
const createPortToken = (country, port) => `${country}::${port}`;
const parsePortToken = (token) => {
    const [country = '', port = ''] = String(token ?? '').split('::', 2);

    return {
        country,
        port,
    };
};
const cloneFilterArray = (values) => (Array.isArray(values) ? [...values] : []);
const sameFilterArray = (left, right) => left.length === right.length && left.every((value, index) => value === right[index]);

const suppliers = computed(() => props.suppliersPage.data ?? []);
const currentPage = computed(() => props.suppliersPage.current_page ?? 1);
const lastPage = computed(() => props.suppliersPage.last_page ?? 1);
const resultsSectionRef = ref(null);
const categoryMenuOpen = ref(false);
const subcategoryMenuOpen = ref(false);
const brandMenuOpen = ref(false);
const countryMenuOpen = ref(false);
const portMenuOpen = ref(false);
const categoryMenuRef = ref(null);
const subcategoryMenuRef = ref(null);
const brandMenuRef = ref(null);
const countryMenuRef = ref(null);
const portMenuRef = ref(null);
const categorySearch = ref('');
const subcategorySearch = ref('');
const brandSearch = ref('');
const countrySearch = ref('');
const portSearch = ref('');
const brandOptionsState = ref([...(props.brands ?? [])]);
const subcategoryGroupsState = ref([]);
const portGroupsState = ref(
    Object.entries(props.portIndex ?? {}).map(([country, ports]) => ({
        country,
        ports: (ports ?? []).map((port) => ({
            token: createPortToken(country, port),
            name: port,
        })),
    })),
);
const isLoadingBrands = ref(false);
const isLoadingSubcategories = ref(false);
const isLoadingPorts = ref(false);
const loadedSubcategoryKey = ref(
    [...(props.filters.parentCategories ?? [])].sort((left, right) => left.localeCompare(right, 'en', { sensitivity: 'base' })).join('|'),
);
const loadedPortKey = ref(
    [...(props.filters.countries ?? [])].sort((left, right) => left.localeCompare(right, 'en', { sensitivity: 'base' })).join('|'),
);
const brandsLoaded = ref(false);
const normalizeSearchText = (value) => String(value ?? '')
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase('en')
    .trim();

const normalizeSearchBlob = (value) => normalizeSearchText(value)
    .replace(/[^a-z0-9]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

const normalizeSearchCompact = (value) => normalizeSearchBlob(value).replace(/\s+/g, '');

const tokenizeSearch = (value) => normalizeSearchBlob(value).split(' ').filter(Boolean);

const levenshteinDistance = (left, right) => {
    if (left === right) {
        return 0;
    }

    if (!left.length) {
        return right.length;
    }

    if (!right.length) {
        return left.length;
    }

    const matrix = Array.from({ length: left.length + 1 }, () => Array(right.length + 1).fill(0));

    for (let row = 0; row <= left.length; row += 1) {
        matrix[row][0] = row;
    }

    for (let column = 0; column <= right.length; column += 1) {
        matrix[0][column] = column;
    }

    for (let row = 1; row <= left.length; row += 1) {
        for (let column = 1; column <= right.length; column += 1) {
            const cost = left[row - 1] === right[column - 1] ? 0 : 1;

            matrix[row][column] = Math.min(
                matrix[row - 1][column] + 1,
                matrix[row][column - 1] + 1,
                matrix[row - 1][column - 1] + cost,
            );
        }
    }

    return matrix[left.length][right.length];
};

const scoreSearchMatch = (values, query) => {
    const queryBlob = normalizeSearchBlob(query);
    const queryCompact = normalizeSearchCompact(query);
    const queryTokens = tokenizeSearch(query);

    if (!queryBlob) {
        return 1;
    }

    let bestScore = 0;

    values.forEach((value) => {
        const blob = normalizeSearchBlob(value);
        const compact = normalizeSearchCompact(value);
        const words = blob.split(' ').filter(Boolean);

        if (!blob) {
            return;
        }

        let score = 0;

        if (blob === queryBlob) {
            score = 1200;
        } else if (compact === queryCompact) {
            score = 1180;
        } else if (blob.startsWith(queryBlob)) {
            score = 1100;
        } else if (compact.startsWith(queryCompact)) {
            score = 1060;
        } else if (words.some((word) => word.startsWith(queryBlob))) {
            score = 1020;
        } else if (blob.includes(queryBlob)) {
            score = 920;
        } else if (compact.includes(queryCompact)) {
            score = 880;
        } else if (queryTokens.length > 1 && queryTokens.every((token) => blob.includes(token))) {
            score = 820;
        } else if (
            queryCompact.length >= 3
            && compact.length >= queryCompact.length
            && levenshteinDistance(compact.slice(0, queryCompact.length), queryCompact) <= 1
        ) {
            score = 720;
        } else if (
            queryBlob.length >= 3
            && words.some((word) => word.length >= queryBlob.length && levenshteinDistance(word.slice(0, queryBlob.length), queryBlob) <= 1)
        ) {
            score = 680;
        }

        bestScore = Math.max(bestScore, score);
    });

    return bestScore;
};

const rankSearchOptions = (items, query, getValues, getLabel) => {
    const queryBlob = normalizeSearchBlob(query);

    if (!queryBlob) {
        return [...items];
    }

    return items
        .map((item) => ({
            item,
            score: scoreSearchMatch(getValues(item), query),
            label: normalizeSearchBlob(getLabel(item)),
        }))
        .filter((entry) => entry.score > 0)
        .sort((left, right) => right.score - left.score || left.label.localeCompare(right.label, 'en', { sensitivity: 'base' }))
        .map((entry) => entry.item);
};

const ensureBrandOptionsLoaded = async () => {
    if (brandsLoaded.value || isLoadingBrands.value || !props.filterDataUrls?.brands) {
        return;
    }

    isLoadingBrands.value = true;

    try {
        const payload = await requestJson(props.filterDataUrls.brands);
        brandOptionsState.value = Array.isArray(payload?.brands) ? payload.brands : [];
        brandsLoaded.value = true;
    } catch (error) {
        console.error('Failed to load brand options.', error);
    } finally {
        isLoadingBrands.value = false;
    }
};

const ensureSubcategoryOptionsLoaded = async () => {
    const categoryKey = createSortedKey(filters.parentCategories);

    if (!filters.parentCategories.length) {
        subcategoryGroupsState.value = [];
        loadedSubcategoryKey.value = '';
        return;
    }

    if (loadedSubcategoryKey.value === categoryKey && subcategoryGroupsState.value.length) {
        return;
    }

    if (isLoadingSubcategories.value || !props.filterDataUrls?.subcategories) {
        return;
    }

    isLoadingSubcategories.value = true;

    try {
        const searchParams = new URLSearchParams();
        filters.parentCategories.forEach((slug) => searchParams.append('categories[]', slug));
        const payload = await requestJson(`${props.filterDataUrls.subcategories}?${searchParams.toString()}`);
        subcategoryGroupsState.value = Array.isArray(payload?.groups) ? payload.groups : [];
        loadedSubcategoryKey.value = categoryKey;
    } catch (error) {
        console.error('Failed to load subcategory options.', error);
    } finally {
        isLoadingSubcategories.value = false;
    }
};

const ensurePortOptionsLoaded = async () => {
    const countryKey = createSortedKey(filters.countries);

    if (!filters.countries.length) {
        portGroupsState.value = [];
        loadedPortKey.value = '';
        return;
    }

    if (loadedPortKey.value === countryKey && portGroupsState.value.length) {
        return;
    }

    if (isLoadingPorts.value || !props.filterDataUrls?.ports) {
        return;
    }

    isLoadingPorts.value = true;

    try {
        const searchParams = new URLSearchParams();
        filters.countries.forEach((country) => searchParams.append('countries[]', country));
        const payload = await requestJson(`${props.filterDataUrls.ports}?${searchParams.toString()}`);
        portGroupsState.value = Array.isArray(payload?.groups) ? payload.groups : [];
        loadedPortKey.value = countryKey;
    } catch (error) {
        console.error('Failed to load port options.', error);
    } finally {
        isLoadingPorts.value = false;
    }
};

const subcategoryOptionGroups = computed(() => {
    if (!filters.parentCategories.length) {
        return [];
    }

    return subcategoryGroupsState.value;
});

const subcategoryOptions = computed(() => {
    return subcategoryOptionGroups.value.flatMap((group) => group.subcategories);
});

const selectedCountryPortGroups = computed(() => portGroupsState.value);

const selectedParentCategoryOptions = computed(() => props.categories.filter((category) => filters.parentCategories.includes(category.slug)));
const selectedSubcategoryOptions = computed(() => {
    const bySlug = new Map();

    [...(props.initialSubcategories ?? []), ...subcategoryOptions.value]
        .filter((subcategory) => filters.subcategories.includes(subcategory.slug))
        .forEach((subcategory) => {
            bySlug.set(subcategory.slug, subcategory);
        });

    return Array.from(bySlug.values());
});
const selectedBrandOptions = computed(() => {
    const bySlug = new Map();

    [...(props.brands ?? []), ...brandOptionsState.value]
        .filter((brand) => filters.brands.includes(brand.slug))
        .forEach((brand) => {
            bySlug.set(brand.slug, brand);
        });

    return Array.from(bySlug.values());
});
const appliedParentCategoryOptions = computed(() => props.categories.filter((category) => appliedFilters.parentCategories.includes(category.slug)));
const appliedSubcategoryOptions = computed(() => {
    const bySlug = new Map();

    [...(props.initialSubcategories ?? []), ...subcategoryOptions.value]
        .filter((subcategory) => appliedFilters.subcategories.includes(subcategory.slug))
        .forEach((subcategory) => {
            bySlug.set(subcategory.slug, subcategory);
        });

    return Array.from(bySlug.values());
});
const appliedBrandOptions = computed(() => {
    const bySlug = new Map();

    [...(props.brands ?? []), ...brandOptionsState.value]
        .filter((brand) => appliedFilters.brands.includes(brand.slug))
        .forEach((brand) => {
            bySlug.set(brand.slug, brand);
        });

    return Array.from(bySlug.values());
});
const filteredParentCategoryOptions = computed(() => rankSearchOptions(
    props.categories,
    categorySearch.value,
    (category) => [category.name, category.slug],
    (category) => category.name,
));
const filteredSubcategoryOptionGroups = computed(() => {
    const query = subcategorySearch.value;

    if (!normalizeSearchBlob(query)) {
        return subcategoryOptionGroups.value;
    }

    return subcategoryOptionGroups.value
        .map((group) => {
            const categoryScore = scoreSearchMatch([group.name, group.slug], query);
            const subcategories = categoryScore > 0
                ? group.subcategories
                : rankSearchOptions(
                    group.subcategories,
                    query,
                    (subcategory) => [subcategory.name, subcategory.slug, subcategory.category_name],
                    (subcategory) => subcategory.name,
                );

            return {
                ...group,
                subcategories,
                __score: Math.max(
                    categoryScore,
                    ...subcategories.map((subcategory) => scoreSearchMatch([
                        subcategory.name,
                        subcategory.slug,
                        subcategory.category_name,
                    ], query)),
                ),
            };
        })
        .filter((group) => group.subcategories.length > 0 || group.__score > 0)
        .sort((left, right) => right.__score - left.__score || left.name.localeCompare(right.name, 'en', { sensitivity: 'base' }))
        .map(({ __score, ...group }) => group);
});
const filteredBrandOptions = computed(() => rankSearchOptions(
    brandOptionsState.value,
    brandSearch.value,
    (brand) => [brand.name, brand.slug],
    (brand) => brand.name,
));
const filteredCountryOptions = computed(() => rankSearchOptions(
    countryOptions.value,
    countrySearch.value,
    (country) => [country],
    (country) => country,
));
const filteredCountryPortGroups = computed(() => {
    const query = portSearch.value;

    if (!normalizeSearchBlob(query)) {
        return selectedCountryPortGroups.value;
    }

    return selectedCountryPortGroups.value
        .map((group) => {
            const countryScore = scoreSearchMatch([group.country], query);
            const ports = countryScore > 0
                ? rankSearchOptions(
                    group.ports,
                    query,
                    (port) => [port.name, port.token, group.country],
                    (port) => port.name,
                )
                : rankSearchOptions(
                    group.ports,
                    query,
                    (port) => [port.name, port.token, group.country],
                    (port) => port.name,
                );

            return {
                ...group,
                ports,
                __score: Math.max(
                    countryScore,
                    ...ports.map((port) => scoreSearchMatch([port.name, port.token, group.country], query)),
                ),
            };
        })
        .filter((group) => group.ports.length > 0 || group.__score > 0)
        .sort((left, right) => right.__score - left.__score || left.country.localeCompare(right.country, 'en', { sensitivity: 'base' }))
        .map(({ __score, ...group }) => group);
});

const selectedParentCategoriesLabel = computed(() => {
    if (!selectedParentCategoryOptions.value.length) {
        return ui.value.parentCategory;
    }

    if (selectedParentCategoryOptions.value.length === 1) {
        return selectedParentCategoryOptions.value[0].name;
    }

    return `${selectedParentCategoryOptions.value.length} categories selected`;
});

const selectedSubcategoriesLabel = computed(() => {
    if (!filters.parentCategories.length) {
        return 'Select Categories First';
    }

    if (!selectedSubcategoryOptions.value.length) {
        return ui.value.allSubcategories;
    }

    if (selectedSubcategoryOptions.value.length === 1) {
        return selectedSubcategoryOptions.value[0].name;
    }

    return `${selectedSubcategoryOptions.value.length} subcategories selected`;
});

const selectedBrandsLabel = computed(() => {
    if (!selectedBrandOptions.value.length) {
        return ui.value.allBrands;
    }

    if (selectedBrandOptions.value.length === 1) {
        return selectedBrandOptions.value[0].name;
    }

    return `${selectedBrandOptions.value.length} brands selected`;
});

const selectedCountriesLabel = computed(() => {
    if (!filters.countries.length) {
        return ui.value.allCountries;
    }

    if (filters.countries.length === 1) {
        return filters.countries[0];
    }

    return `${filters.countries.length} countries selected`;
});

const selectedPortsLabel = computed(() => {
    if (!filters.countries.length) {
        return ui.value.chooseCountryFirst;
    }

    if (!filters.ports.length) {
        return ui.value.allPorts;
    }

    if (filters.ports.length === 1) {
        return parsePortToken(filters.ports[0]).port || filters.ports[0];
    }

    return `${filters.ports.length} ports selected`;
});

const appliedParentCategoriesLabel = computed(() => {
    if (!appliedParentCategoryOptions.value.length) {
        return ui.value.parentCategory;
    }

    if (appliedParentCategoryOptions.value.length === 1) {
        return appliedParentCategoryOptions.value[0].name;
    }

    return `${appliedParentCategoryOptions.value.length} categories selected`;
});

const appliedSubcategoriesLabel = computed(() => {
    if (!appliedFilters.parentCategories.length) {
        return 'Select Categories First';
    }

    if (!appliedSubcategoryOptions.value.length) {
        return ui.value.allSubcategories;
    }

    if (appliedSubcategoryOptions.value.length === 1) {
        return appliedSubcategoryOptions.value[0].name;
    }

    return `${appliedSubcategoryOptions.value.length} subcategories selected`;
});

const appliedBrandsLabel = computed(() => {
    if (!appliedBrandOptions.value.length) {
        return ui.value.allBrands;
    }

    if (appliedBrandOptions.value.length === 1) {
        return appliedBrandOptions.value[0].name;
    }

    return `${appliedBrandOptions.value.length} brands selected`;
});

const appliedCountriesLabel = computed(() => {
    if (!appliedFilters.countries.length) {
        return ui.value.allCountries;
    }

    if (appliedFilters.countries.length === 1) {
        return appliedFilters.countries[0];
    }

    return `${appliedFilters.countries.length} countries selected`;
});

const appliedPortsLabel = computed(() => {
    if (!appliedFilters.countries.length) {
        return ui.value.chooseCountryFirst;
    }

    if (!appliedFilters.ports.length) {
        return ui.value.allPorts;
    }

    if (appliedFilters.ports.length === 1) {
        return parsePortToken(appliedFilters.ports[0]).port || appliedFilters.ports[0];
    }

    return `${appliedFilters.ports.length} ports selected`;
});

const hasPendingFilterChanges = computed(() => (
    normalizeSearchText(filters.search) !== normalizeSearchText(appliedSearch.value)
    || !sameFilterArray(filters.parentCategories, appliedFilters.parentCategories)
    || !sameFilterArray(filters.subcategories, appliedFilters.subcategories)
    || !sameFilterArray(filters.brands, appliedFilters.brands)
    || !sameFilterArray(filters.countries, appliedFilters.countries)
    || !sameFilterArray(filters.ports, appliedFilters.ports)
));

const filteredSuppliersCount = computed(() => props.suppliersPage.total ?? suppliers.value.length);
const shouldShowPagination = computed(() => lastPage.value > 1);
const hasPreviousPage = computed(() => currentPage.value > 1);
const hasNextPage = computed(() => currentPage.value < lastPage.value);
const mobilePaginationLabel = computed(() => `${currentPage.value} / ${lastPage.value}`);
const desktopPaginationItems = computed(() => {
    const total = lastPage.value;
    const current = currentPage.value;

    if (total <= 1) {
        return [];
    }

    const windowSize = 5;
    let start = Math.max(1, current - 2);
    let end = Math.min(total, start + windowSize - 1);

    start = Math.max(1, end - windowSize + 1);

    const items = [];

    if (start > 1) {
        items.push({ type: 'page', value: 1 });

        if (start > 2) {
            items.push({ type: 'ellipsis', key: 'start-gap' });
        }
    }

    for (let value = start; value <= end; value += 1) {
        items.push({ type: 'page', value });
    }

    if (end < total) {
        if (end < total - 1) {
            items.push({ type: 'ellipsis', key: 'end-gap' });
        }

        items.push({ type: 'page', value: total });
    }

    return items;
});

const assignAppliedSelectionsFromDraft = () => {
    appliedFilters.parentCategories = cloneFilterArray(filters.parentCategories);
    appliedFilters.subcategories = cloneFilterArray(filters.subcategories);
    appliedFilters.brands = cloneFilterArray(filters.brands);
    appliedFilters.countries = cloneFilterArray(filters.countries);
    appliedFilters.ports = cloneFilterArray(filters.ports);
};

const activeFilterChips = computed(() => {
    const chips = [];

    if (appliedSearch.value.trim()) {
        chips.push({
            key: 'search',
            label: `${ui.value.search}: ${appliedSearch.value.trim()}`,
            clear: () => {
                filters.search = '';
                applySelectionFilters();
            },
        });
    }

    if (appliedFilters.parentCategories.length) {
        chips.push({
            key: 'parentCategories',
            label: `${ui.value.parentCategory}: ${appliedParentCategoriesLabel.value}`,
            clear: () => {
                filters.parentCategories = [];
                filters.subcategories = [];
                applySelectionFilters();
            },
        });
    }

    if (appliedFilters.subcategories.length) {
        chips.push({
            key: 'subcategories',
            label: `${ui.value.subcategory}: ${appliedSubcategoriesLabel.value}`,
            clear: () => {
                filters.subcategories = [];
                applySelectionFilters();
            },
        });
    }

    if (appliedFilters.brands.length) {
        chips.push({
            key: 'brands',
            label: `${ui.value.brand}: ${appliedBrandsLabel.value}`,
            clear: () => {
                filters.brands = [];
                applySelectionFilters();
            },
        });
    }

    if (appliedFilters.countries.length) {
        chips.push({
            key: 'countries',
            label: `${ui.value.country}: ${appliedCountriesLabel.value}`,
            clear: () => {
                filters.countries = [];
                filters.ports = [];
                applySelectionFilters();
            },
        });
    }

    if (appliedFilters.ports.length) {
        chips.push({
            key: 'ports',
            label: `${ui.value.port}: ${appliedPortsLabel.value}`,
            clear: () => {
                filters.ports = [];
                applySelectionFilters();
            },
        });
    }

    return chips;
});

const emptyState = computed(() => {
    const primaryCountry = appliedFilters.countries[0] ?? '';
    const primaryCategory = appliedParentCategoryOptions.value[0]?.name ?? '';
    const primarySubcategory = appliedSubcategoryOptions.value[0]?.name ?? '';
    const hasCountry = appliedFilters.countries.length > 0;
    const hasCategory = appliedFilters.parentCategories.length > 0;
    const hasSubcategory = appliedFilters.subcategories.length > 0;
    const hasSearch = Boolean(appliedSearch.value.trim());
    const hasFilters = hasCountry
        || hasCategory
        || hasSubcategory
        || hasSearch
        || appliedFilters.ports.length > 0
        || appliedFilters.brands.length > 0;

    if (hasCountry && hasSubcategory) {
        return {
            title: `No approved suppliers were found in ${primaryCountry} for ${primarySubcategory}.`,
            text: 'You can broaden the search, switch filters, or join the directory as a supplier for this service area.',
        };
    }

    if (hasCountry && hasCategory) {
        return {
            title: `No approved suppliers were found in ${primaryCountry} for ${primaryCategory}.`,
            text: 'There are no matching results for this country and category yet. You can adjust the filters or join the directory as a supplier.',
        };
    }

    if (hasCountry) {
        return {
            title: `No approved suppliers were found in ${primaryCountry}.`,
            text: 'There are no visible matches for this country right now. You can clear filters, choose another country, or join the directory as a supplier.',
        };
    }

    if (hasSearch || hasFilters) {
        return {
            title: 'No approved suppliers match these filters.',
            text: 'Try broadening your search or changing the selected category, subcategory, country, or port.',
        };
    }

    return {
        title: 'No approved suppliers are listed yet.',
        text: 'When the first approved suppliers go live, this directory will automatically show their services, countries, ports, and company profiles.',
    };
});

const structuredData = computed(() => JSON.stringify({
    '@context': 'https://schema.org',
    '@type': 'CollectionPage',
    name: props.meta.title,
    description: props.meta.description,
    url: props.meta.canonical || undefined,
    mainEntity: {
        '@type': 'ItemList',
        numberOfItems: props.suppliersPage.total ?? suppliers.value.length,
        itemListElement: suppliers.value.slice(0, 12).map((item, index) => ({
            '@type': 'ListItem',
            position: index + 1,
            url: item.href,
            name: item.secondary_category?.name || item.primary_category?.name || item.name,
        })),
    },
}));

const closeAllMenus = () => {
    categoryMenuOpen.value = false;
    subcategoryMenuOpen.value = false;
    brandMenuOpen.value = false;
    countryMenuOpen.value = false;
    portMenuOpen.value = false;
    categorySearch.value = '';
    subcategorySearch.value = '';
    brandSearch.value = '';
    countrySearch.value = '';
    portSearch.value = '';
};

const toggleMenu = async (menuKey) => {
    const menuMap = {
        category: categoryMenuOpen,
        subcategory: subcategoryMenuOpen,
        brand: brandMenuOpen,
        country: countryMenuOpen,
        port: portMenuOpen,
    };
    const menuState = menuMap[menuKey];

    if (!menuState) {
        return;
    }

    const nextValue = !menuState.value;
    closeAllMenus();
    menuState.value = nextValue;

    if (!nextValue) {
        return;
    }

    if (menuKey === 'brand') {
        await ensureBrandOptionsLoaded();
    }

    if (menuKey === 'subcategory') {
        await ensureSubcategoryOptionsLoaded();
    }

    if (menuKey === 'port') {
        await ensurePortOptionsLoaded();
    }
};

const isParentCategorySelected = (slug) => filters.parentCategories.includes(slug);
const isSubcategorySelected = (slug) => filters.subcategories.includes(slug);
const isBrandSelected = (slug) => filters.brands.includes(slug);
const isCountrySelected = (country) => filters.countries.includes(country);
const isPortSelected = (token) => filters.ports.includes(token);

const toggleParentCategory = (slug) => {
    filters.parentCategories = isParentCategorySelected(slug)
        ? filters.parentCategories.filter((item) => item !== slug)
        : [...filters.parentCategories, slug];
};

const toggleSubcategory = (slug) => {
    filters.subcategories = isSubcategorySelected(slug)
        ? filters.subcategories.filter((item) => item !== slug)
        : [...filters.subcategories, slug];
};

const selectAllSubcategories = () => {
    filters.subcategories = filteredSubcategoryOptionGroups.value.flatMap((group) => group.subcategories.map((subcategory) => subcategory.slug));
};

const clearAllSubcategories = () => {
    filters.subcategories = [];
};

const toggleBrand = (slug) => {
    filters.brands = isBrandSelected(slug)
        ? filters.brands.filter((item) => item !== slug)
        : [...filters.brands, slug];
};

const toggleCountry = (country) => {
    if (isCountrySelected(country)) {
        filters.countries = filters.countries.filter((item) => item !== country);
        filters.ports = filters.ports.filter((token) => parsePortToken(token).country !== country);
        return;
    }

    filters.countries = [...filters.countries, country];
};

const togglePort = (token) => {
    filters.ports = isPortSelected(token)
        ? filters.ports.filter((item) => item !== token)
        : [...filters.ports, token];
};

const selectAllPorts = () => {
    filters.ports = filteredCountryPortGroups.value.flatMap((group) => group.ports.map((port) => port.token));
};

const clearAllPorts = () => {
    filters.ports = [];
};

const requestDirectory = ({
    categories,
    subcategories,
    brands,
    countries,
    ports,
    search,
    page = null,
    preserveScroll = false,
    only = ['suppliersPage', 'filters', 'meta'],
    replace = true,
    onSuccess,
    onFinish,
} = {}) => {
    router.get(
        buildServiceDirectoryUrl({
            categories,
            subcategories,
            search,
            brands,
            countries,
            ports,
            page,
        }),
        {},
        {
            preserveState: true,
            preserveScroll,
            replace,
            only,
            onSuccess: () => {
                onSuccess?.();
            },
            onFinish,
        },
    );
};

const applySelectionFilters = () => {
    closeAllMenus();

    requestDirectory({
        categories: filters.parentCategories,
        subcategories: filters.subcategories,
        brands: filters.brands,
        countries: filters.countries,
        ports: filters.ports,
        search: filters.search,
        onSuccess: () => {
            assignAppliedSelectionsFromDraft();
            appliedSearch.value = filters.search;
        },
    });
};

const goToPage = (targetPage) => {
    const pageNumber = Number(targetPage);

    if (!Number.isInteger(pageNumber) || pageNumber < 1 || pageNumber > lastPage.value || pageNumber === currentPage.value) {
        return;
    }

    closeAllMenus();

    requestDirectory({
        categories: appliedFilters.parentCategories,
        subcategories: appliedFilters.subcategories,
        brands: appliedFilters.brands,
        countries: appliedFilters.countries,
        ports: appliedFilters.ports,
        search: appliedSearch.value,
        page: pageNumber,
        preserveScroll: true,
        only: ['suppliersPage'],
        replace: false,
        onSuccess: () => {
            window.requestAnimationFrame(() => {
                resultsSectionRef.value?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            });
        },
    });
};

const clearAllFilters = () => {
    filters.search = '';
    filters.parentCategories = [];
    filters.subcategories = [];
    filters.brands = [];
    filters.countries = [];
    filters.ports = [];
    closeAllMenus();

    requestDirectory({
        categories: [],
        subcategories: [],
        brands: [],
        countries: [],
        ports: [],
        search: '',
        onSuccess: () => {
            assignAppliedSelectionsFromDraft();
            appliedSearch.value = '';
        },
    });
};

watch(
    () => [...filters.parentCategories],
    async () => {
        const categoryKey = createSortedKey(filters.parentCategories);
        const selectedCategorySet = new Set(filters.parentCategories);

        if (!filters.parentCategories.length) {
            subcategoryGroupsState.value = [];
            loadedSubcategoryKey.value = '';
        } else if (loadedSubcategoryKey.value !== categoryKey) {
            subcategoryGroupsState.value = [];
        }

        const validSubcategorySlugs = new Set([
            ...subcategoryOptions.value.map((subcategory) => subcategory.slug),
            ...(props.initialSubcategories ?? [])
                .filter((subcategory) => selectedCategorySet.has(subcategory.category_slug))
                .map((subcategory) => subcategory.slug),
        ]);

        if (filters.subcategories.some((slug) => !validSubcategorySlugs.has(slug))) {
            filters.subcategories = filters.subcategories.filter((slug) => validSubcategorySlugs.has(slug));
        }

        if (subcategoryMenuOpen.value) {
            await ensureSubcategoryOptionsLoaded();
        }
    },
);

watch(
    () => [...filters.countries],
    async () => {
        const countryKey = createSortedKey(filters.countries);

        if (!filters.countries.length) {
            portGroupsState.value = [];
            loadedPortKey.value = '';
        } else if (loadedPortKey.value !== countryKey) {
            portGroupsState.value = [];
        }

        const validCountries = new Set(filters.countries);

        if (filters.ports.some((token) => !validCountries.has(parsePortToken(token).country))) {
            filters.ports = filters.ports.filter((token) => validCountries.has(parsePortToken(token).country));
        }

        if (portMenuOpen.value) {
            await ensurePortOptionsLoaded();
        }
    },
);

const handleDocumentClick = (event) => {
    const target = event.target;
    const menuRefs = [
        categoryMenuRef,
        subcategoryMenuRef,
        brandMenuRef,
        countryMenuRef,
        portMenuRef,
    ];

    if (menuRefs.some((menuRef) => menuRef.value?.contains(target))) {
        return;
    }

    closeAllMenus();
};

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
});
</script>

<template>
    <Head :title="meta.title">
        <meta name="description" :content="meta.description" />
        <meta name="robots" :content="meta.robots" />
        <meta property="og:title" :content="meta.title" />
        <meta property="og:description" :content="meta.description" />
        <meta property="og:type" content="website" />
        <meta property="og:image" :content="meta.ogImage" />
        <meta name="twitter:card" :content="meta.twitterCard || 'summary'" />
        <meta name="twitter:title" :content="meta.title" />
        <meta name="twitter:description" :content="meta.description" />
        <meta name="twitter:image" :content="meta.ogImage" />
        <link v-if="meta.canonical" rel="canonical" :href="meta.canonical">
        <component :is="'script'" type="application/ld+json" v-html="structuredData" />
    </Head>

    <MainLayout>
        <section class="listing-shell">
            <header class="section-header intro-card">
                <p class="eyebrow">{{ heroEyebrow }}</p>
                <h1 class="directory-page-title">{{ heroTitle }}</h1>
                <p>{{ heroText }}</p>
            </header>

            <div class="directory-layout">
                <aside class="directory-sidebar">
                    <section class="filters-card directory-sidebar-card">
                        <div class="filters-head">
                            <div class="filters-head-actions">
                                <span class="filters-label">{{ ui.filters }}</span>
                            </div>

                            <div class="results-badge">
                                <strong>{{ filteredSuppliersCount }}</strong>
                                <span>{{ ui.results }}</span>
                            </div>
                        </div>

                        <div class="search-row">
                            <label class="field search-field">
                                <span>{{ ui.search }}</span>
                                <input
                                    v-model="filters.search"
                                    type="text"
                                    :placeholder="ui.searchPlaceholder"
                                    @keydown.enter.prevent="applySelectionFilters"
                                />
                            </label>
                        </div>

                        <div class="filters-grid">
                            <div class="field selection-field">
                                <span>{{ ui.country }}</span>
                                <div ref="countryMenuRef" class="dropdown-shell">
                                    <button
                                        type="button"
                                        class="dropdown-trigger"
                                        :class="{ 'is-placeholder': !filters.countries.length }"
                                        @click.stop="toggleMenu('country')"
                                    >
                                        <span>{{ selectedCountriesLabel }}</span>
                                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                            <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>

                                    <div v-if="countryMenuOpen" class="dropdown-menu dropdown-menu-wide" @click.stop>
                                        <div class="dropdown-search-wrap">
                                            <input
                                                v-model="countrySearch"
                                                type="text"
                                                class="dropdown-search-input"
                                                placeholder="Search..."
                                            />
                                        </div>

                                        <label
                                            v-for="option in filteredCountryOptions"
                                            :key="option"
                                            class="dropdown-option"
                                        >
                                            <input
                                                type="checkbox"
                                                :checked="isCountrySelected(option)"
                                                @change="toggleCountry(option)"
                                            />
                                            <span>{{ option }}</span>
                                        </label>

                                        <p v-if="!filteredCountryOptions.length" class="selector-empty">No matching results.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="field selection-field">
                                <span>{{ ui.port }}</span>
                                <div ref="portMenuRef" class="dropdown-shell">
                                    <button
                                        type="button"
                                        class="dropdown-trigger"
                                        :class="{ 'is-placeholder': !filters.ports.length }"
                                        :disabled="!filters.countries.length"
                                        @click.stop="toggleMenu('port')"
                                    >
                                        <span>{{ selectedPortsLabel }}</span>
                                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                            <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>

                                    <div v-if="portMenuOpen" class="dropdown-menu dropdown-menu-wide" @click.stop>
                                        <div class="dropdown-search-wrap">
                                            <input
                                                v-model="portSearch"
                                                type="text"
                                                class="dropdown-search-input"
                                                placeholder="Search..."
                                            />
                                        </div>

                                        <div v-if="filteredCountryPortGroups.length" class="country-port-groups">
                                            <div class="ports-menu-actions">
                                                <button type="button" class="ports-menu-action" @click="selectAllPorts">
                                                    All
                                                </button>
                                                <button type="button" class="ports-menu-action" @click="clearAllPorts">
                                                    Clear
                                                </button>
                                            </div>

                                            <section
                                                v-for="group in filteredCountryPortGroups"
                                                :key="group.country"
                                                class="country-port-group"
                                            >
                                                <div class="country-port-group-head">
                                                    <span class="country-port-group-title">{{ group.country }}</span>
                                                </div>

                                                <label
                                                    v-for="port in group.ports"
                                                    :key="port.token"
                                                    class="dropdown-option"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        :checked="isPortSelected(port.token)"
                                                        @change="togglePort(port.token)"
                                                    />
                                                    <span>{{ port.name }}</span>
                                                </label>
                                            </section>
                                        </div>

                                        <p v-else class="selector-empty">{{ isLoadingPorts ? 'Loading...' : (filters.countries.length ? 'No matching results.' : 'Select one or more countries to choose ports.') }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="field selection-field">
                                <span>{{ ui.parentCategory }}</span>
                                <div ref="categoryMenuRef" class="dropdown-shell">
                                    <button
                                        type="button"
                                        class="dropdown-trigger"
                                        :class="{ 'is-placeholder': !filters.parentCategories.length }"
                                        @click.stop="toggleMenu('category')"
                                    >
                                        <span>{{ selectedParentCategoriesLabel }}</span>
                                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                            <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>

                                    <div v-if="categoryMenuOpen" class="dropdown-menu dropdown-menu-wide" @click.stop>
                                        <div class="dropdown-search-wrap">
                                            <input
                                                v-model="categorySearch"
                                                type="text"
                                                class="dropdown-search-input"
                                                placeholder="Search..."
                                            />
                                        </div>

                                        <label
                                            v-for="option in filteredParentCategoryOptions"
                                            :key="option.id ?? option.slug"
                                            class="dropdown-option"
                                        >
                                            <input
                                                type="checkbox"
                                                :checked="isParentCategorySelected(option.slug)"
                                                @change="toggleParentCategory(option.slug)"
                                            />
                                            <span>{{ option.name }}</span>
                                        </label>

                                        <p v-if="!filteredParentCategoryOptions.length" class="selector-empty">No matching results.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="field selection-field">
                                <span>{{ ui.subcategory }}</span>
                                <div ref="subcategoryMenuRef" class="dropdown-shell">
                                    <button
                                        type="button"
                                        class="dropdown-trigger"
                                        :class="{ 'is-placeholder': !filters.subcategories.length }"
                                        :disabled="!filters.parentCategories.length"
                                        @click.stop="toggleMenu('subcategory')"
                                    >
                                        <span>{{ selectedSubcategoriesLabel }}</span>
                                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                            <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>

                                    <div v-if="subcategoryMenuOpen" class="dropdown-menu dropdown-menu-wide" @click.stop>
                                        <div class="dropdown-search-wrap">
                                            <input
                                                v-model="subcategorySearch"
                                                type="text"
                                                class="dropdown-search-input"
                                                placeholder="Search..."
                                            />
                                        </div>

                                        <div v-if="filteredSubcategoryOptionGroups.length" class="ports-menu-actions">
                                            <button type="button" class="ports-menu-action" @click="selectAllSubcategories">
                                                Select all
                                            </button>
                                            <button type="button" class="ports-menu-action" @click="clearAllSubcategories">
                                                Clear
                                            </button>
                                        </div>

                                        <div v-if="filteredSubcategoryOptionGroups.length" class="country-port-groups">
                                            <section
                                                v-for="group in filteredSubcategoryOptionGroups"
                                                :key="group.slug"
                                                class="country-port-group"
                                            >
                                                <div class="country-port-group-head">
                                                    <span class="country-port-group-title">{{ group.name }}</span>
                                                </div>

                                                <label
                                                    v-for="option in group.subcategories"
                                                    :key="option.id ?? `${group.slug}-${option.slug}`"
                                                    class="dropdown-option"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        :checked="isSubcategorySelected(option.slug)"
                                                        @change="toggleSubcategory(option.slug)"
                                                    />
                                                    <span>{{ option.name }}</span>
                                                </label>

                                                <p v-if="!group.subcategories.length" class="selector-empty">No subcategories in this category.</p>
                                            </section>
                                        </div>

                                        <p v-else class="selector-empty">{{ isLoadingSubcategories ? 'Loading...' : 'No matching results.' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="field selection-field">
                                <span>{{ ui.brand }}</span>
                                <div ref="brandMenuRef" class="dropdown-shell">
                                    <button
                                        type="button"
                                        class="dropdown-trigger"
                                        :class="{ 'is-placeholder': !filters.brands.length }"
                                        @click.stop="toggleMenu('brand')"
                                    >
                                        <span>{{ selectedBrandsLabel }}</span>
                                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                            <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>

                                    <div v-if="brandMenuOpen" class="dropdown-menu dropdown-menu-wide" @click.stop>
                                        <div class="dropdown-search-wrap">
                                            <input
                                                v-model="brandSearch"
                                                type="text"
                                                class="dropdown-search-input"
                                                placeholder="Search..."
                                            />
                                        </div>

                                        <label
                                            v-for="option in filteredBrandOptions"
                                            :key="option.id ?? option.slug"
                                            class="dropdown-option"
                                        >
                                            <input
                                                type="checkbox"
                                                :checked="isBrandSelected(option.slug)"
                                                @change="toggleBrand(option.slug)"
                                            />
                                            <span>{{ option.name }}</span>
                                        </label>

                                        <p v-if="!filteredBrandOptions.length" class="selector-empty">{{ isLoadingBrands ? 'Loading...' : 'No matching results.' }}</p>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="filters-submit-row">
                            <button
                                type="button"
                                class="apply-button"
                                :disabled="!hasPendingFilterChanges"
                                @click="applySelectionFilters"
                            >
                                {{ ui.applyFilters }}
                            </button>
                            <button type="button" class="clear-button clear-button-inline" @click="clearAllFilters">{{ ui.clearAll }}</button>
                        </div>

                        <div class="active-filters">
                            <span class="field-label">{{ ui.activeFilters }}</span>
                            <div v-if="activeFilterChips.length" class="chip-row">
                                <button
                                    v-for="chip in activeFilterChips"
                                    :key="chip.key"
                                    type="button"
                                    class="filter-chip"
                                    @click="chip.clear"
                                >
                                    <span>{{ chip.label }}</span>
                                    <strong>&times;</strong>
                                </button>
                            </div>
                            <p v-else class="inactive-copy">{{ ui.noActiveFilters }}</p>
                        </div>
                    </section>
                </aside>

                <section ref="resultsSectionRef" class="directory-results">
                    <div v-if="suppliers.length" class="listing-grid">
                        <ServiceListingCard
                            v-for="item in suppliers"
                            :key="item.id"
                            :item="item"
                            :label="ui.viewProfile"
                            :no-description="ui.noDescription"
                            variant="directory"
                        />
                    </div>

                    <div v-else class="empty-card">
                        <strong>{{ emptyState.title }}</strong>
                        <p>{{ emptyState.text }}</p>
                        <div class="empty-card-actions">
                            <button v-if="activeFilterChips.length" type="button" class="empty-card-button empty-card-button-secondary" @click="clearAllFilters">
                                {{ ui.clearAll }}
                            </button>
                            <Link
                                v-if="!isAuthenticated"
                                href="/register"
                                class="empty-card-button empty-card-button-primary"
                            >
                                Join as a Supplier
                            </Link>
                        </div>
                    </div>

                    <div v-if="suppliers.length && shouldShowPagination" class="pagination-shell">
                        <div class="pagination-desktop">
                            <button
                                type="button"
                                class="pagination-button"
                                :disabled="!hasPreviousPage"
                                @click="goToPage(currentPage - 1)"
                            >
                                Previous
                            </button>

                            <template v-for="item in desktopPaginationItems" :key="item.type === 'page' ? `page-${item.value}` : item.key">
                                <button
                                    v-if="item.type === 'page'"
                                    type="button"
                                    class="pagination-button pagination-page"
                                    :class="{ 'is-active': item.value === currentPage }"
                                    @click="goToPage(item.value)"
                                >
                                    {{ item.value }}
                                </button>
                                <span
                                    v-else
                                    class="pagination-ellipsis"
                                >
                                    ...
                                </span>
                            </template>

                            <button
                                type="button"
                                class="pagination-button"
                                :disabled="!hasNextPage"
                                @click="goToPage(currentPage + 1)"
                            >
                                Next
                            </button>
                        </div>

                        <div class="pagination-mobile">
                            <button
                                type="button"
                                class="pagination-button"
                                :disabled="!hasPreviousPage"
                                @click="goToPage(currentPage - 1)"
                            >
                                Previous
                            </button>

                            <span class="pagination-mobile-state">{{ mobilePaginationLabel }}</span>

                            <button
                                type="button"
                                class="pagination-button"
                                :disabled="!hasNextPage"
                                @click="goToPage(currentPage + 1)"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </MainLayout>
</template>

<style scoped>
.listing-shell {
    padding: 16px 0 56px;
}

.intro-card,
.filters-card {
    padding: 32px 36px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.78);
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.08);
}

.filters-card {
    margin-top: 24px;
}

.directory-layout {
    display: grid;
    grid-template-columns: minmax(280px, 320px) minmax(0, 1fr);
    align-items: start;
    gap: 24px;
    margin-top: 24px;
}

.directory-sidebar {
    min-width: 0;
}

.directory-sidebar-card {
    position: sticky;
    top: 104px;
    margin-top: 0;
}

.directory-results {
    min-width: 0;
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

.filters-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.filters-head-actions {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.filters-label,
.field-label {
    color: rgba(4, 21, 31, 0.64);
    font-size: 0.96rem;
    font-weight: 650;
}

.apply-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 0 16px;
    border: 1px solid #0f172a;
    border-radius: 10px;
    background: #0f172a;
    color: #ffffff;
    font-size: 0.88rem;
    font-weight: 600;
    transition: opacity 160ms ease, transform 160ms ease;
}

.apply-button:disabled {
    opacity: 0.42;
    cursor: not-allowed;
}

.apply-button:not(:disabled):hover {
    transform: translateY(-1px);
}

.clear-button {
    border: 0;
    background: transparent;
    color: rgba(4, 21, 31, 0.5);
    font-weight: 500;
    transition: color 180ms ease;
}

.clear-button:hover {
    color: rgba(4, 21, 31, 0.76);
}

.clear-button-inline {
    min-height: 40px;
    padding: 0 4px;
}

.empty-card-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 18px;
}

.empty-card-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 18px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 600;
    transition: transform 160ms ease, background-color 160ms ease, color 160ms ease, border-color 160ms ease;
}

.empty-card-button:hover {
    transform: translateY(-1px);
}

.empty-card-button-primary {
    border: 1px solid #0f172a;
    background: #0f172a;
    color: #ffffff;
}

.empty-card-button-secondary {
    border: 1px solid #d9e2ef;
    background: #ffffff;
    color: #0f172a;
}

.results-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 48px;
    padding: 0 16px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: white;
}

.results-badge strong {
    font-size: 0.98rem;
    line-height: 1;
}

.results-badge span {
    color: rgba(4, 21, 31, 0.64);
    font-size: 0.92rem;
    font-weight: 500;
}

.search-row {
    margin-top: 16px;
}

.filters-submit-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 16px;
}

.search-field input {
    background: white;
}

.filters-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
    margin-top: 16px;
}

.field {
    display: grid;
    gap: 5px;
}

.field-wide {
    grid-column: span 2;
}

.field span {
    font-size: 0.86rem;
    font-weight: 600;
    color: rgba(4, 21, 31, 0.7);
}

.field input,
.field select {
    width: 100%;
    height: 48px;
    padding: 0 16px;
    border: 1px solid rgba(4, 21, 31, 0.14);
    border-radius: 10px;
    background: white;
    color: var(--color-ink);
    font-size: 0.93rem;
    font-weight: 400;
    line-height: 1.2;
}

.field input::placeholder {
    color: rgba(4, 21, 31, 0.44);
    font-weight: 400;
}

.selection-field {
    min-width: 0;
}

.dropdown-shell {
    position: relative;
}

.dropdown-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    min-height: 48px;
    padding: 0 16px;
    border: 1px solid rgba(4, 21, 31, 0.14);
    border-radius: 10px;
    background: white;
    color: var(--color-ink);
    font-size: 0.93rem;
    font-weight: 400;
    line-height: 1.2;
    text-align: left;
}

.dropdown-trigger:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.dropdown-trigger.is-placeholder > span {
    color: rgba(4, 21, 31, 0.72);
}

.dropdown-trigger > span {
    color: rgba(4, 21, 31, 0.86);
    font-size: 0.93rem;
    font-weight: 400;
    line-height: 1.2;
}

.dropdown-trigger svg {
    width: 14px;
    height: 14px;
    flex: 0 0 14px;
    color: rgba(4, 21, 31, 0.74);
}

.dropdown-menu {
    position: absolute;
    top: calc(100% + 10px);
    left: 0;
    width: 100%;
    z-index: 20;
    display: grid;
    align-content: start;
    gap: 2px;
    max-height: 320px;
    overflow: auto;
    padding: 10px;
    border: 1px solid rgba(4, 21, 31, 0.1);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.98);
    box-shadow: 0 22px 38px rgba(15, 23, 42, 0.12);
}

.dropdown-menu-wide {
    width: 100%;
}

.dropdown-search-wrap {
    padding: 2px 0 8px;
}

.dropdown-search-input {
    width: 100%;
    height: 40px;
    padding: 0 12px;
    border: 1px solid rgba(4, 21, 31, 0.12);
    border-radius: 8px;
    background: #fff;
    color: #04151f;
    font-size: 0.9rem;
    font-weight: 400;
    line-height: 1.2;
}

.dropdown-search-input::placeholder {
    color: rgba(4, 21, 31, 0.46);
}

.dropdown-option {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 40px;
    padding: 0 8px;
    border-radius: 8px;
    cursor: pointer;
}

.dropdown-option:hover {
    background: rgba(4, 21, 31, 0.04);
}

.dropdown-option input {
    width: 16px;
    height: 16px;
    margin: 0;
    flex: 0 0 16px;
}

.dropdown-option span {
    color: #04151f;
    font-size: 0.94rem;
    font-weight: 400;
    line-height: 1.4;
}

.ports-menu-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 2px 8px 8px;
}

.ports-menu-action {
    border: 0;
    background: transparent;
    color: rgba(4, 21, 31, 0.76);
    font-size: 0.85rem;
    font-weight: 600;
    line-height: 1.3;
}

.selector-empty {
    margin: 0;
    padding: 8px 2px;
    color: rgba(4, 21, 31, 0.68);
    font-size: 0.9rem;
    line-height: 1.6;
}

.country-port-groups {
    display: grid;
    align-content: start;
    gap: 12px;
}

.country-port-group {
    display: grid;
    gap: 6px;
}

.country-port-group-head {
    padding: 2px 8px 0;
}

.country-port-group-title {
    color: rgba(4, 21, 31, 0.82);
    font-size: 0.9rem;
    font-weight: 500;
    line-height: 1.4;
}

.field select:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.active-filters {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid rgba(4, 21, 31, 0.08);
}

.chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 8px;
}

.filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-height: 48px;
    padding: 0 14px;
    border: 1px solid rgba(4, 21, 31, 0.12);
    border-radius: 10px;
    background: white;
    color: rgba(4, 21, 31, 0.74);
    font-weight: 500;
}

.filter-chip strong {
    line-height: 1;
    font-weight: 600;
}

.inactive-copy {
    margin: 10px 0 0;
    color: rgba(4, 21, 31, 0.62);
}

.listing-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 16px;
    width: 100%;
    margin-top: 0;
}

.listing-card {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.08);
    min-height: 100%;
    transition:
        transform 220ms ease,
        box-shadow 220ms ease,
        border-color 220ms ease;
}

.listing-card:hover {
    transform: translateY(-6px) scale(1.01);
    box-shadow: 0 30px 56px rgba(15, 23, 42, 0.14);
    border-color: rgba(14, 116, 144, 0.16);
}

.card-cover {
    position: relative;
    aspect-ratio: 16 / 9;
    overflow: hidden;
}

.cover-badge {
    position: absolute;
    top: 14px;
    left: 14px;
    max-width: calc(100% - 28px);
    padding: 7px 11px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.78);
    color: rgba(4, 21, 31, 0.78);
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.01em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    border: 1px solid rgba(255, 255, 255, 0.42);
    box-shadow: 0 10px 22px rgba(4, 21, 31, 0.1);
    backdrop-filter: blur(10px);
}

.brand-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cover-fallback {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.2), transparent 24%),
        radial-gradient(circle at left bottom, rgba(15, 118, 110, 0.35), transparent 28%),
        linear-gradient(135deg, rgba(255, 255, 255, 0.08), transparent 54%);
    opacity: 0.9;
}

.cover-art {
    position: absolute;
    right: 18px;
    bottom: 18px;
    width: 68px;
    height: 68px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.26);
    box-shadow: 0 18px 34px rgba(4, 21, 31, 0.12);
    backdrop-filter: blur(10px);
}

.cover-art svg {
    width: 30px;
    height: 30px;
}

.card-body {
    display: grid;
    gap: 16px;
    padding: 20px 20px 20px;
    flex: 1;
}

.brand-copy {
    display: grid;
    gap: 6px;
    min-width: 0;
}

.brand-copy strong {
    font-size: 1.06rem;
    line-height: 1.28;
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.brand-copy > span:not(.meta-line) {
    display: none;
}

.meta-line {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    color: rgba(4, 21, 31, 0.68);
    font-size: 0.9rem;
    min-width: 0;
    line-height: 1.3;
}

.meta-line svg {
    width: 14px;
    height: 14px;
    flex-shrink: 0;
    color: rgba(4, 21, 31, 0.54);
    margin-top: 1px;
}

.meta-line span {
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 1;
}

.company-line {
    color: rgba(4, 21, 31, 0.72);
    font-weight: 500;
    margin-top: 1px;
}

.company-line span {
    -webkit-line-clamp: 2;
    word-break: normal;
    overflow-wrap: break-word;
}

.country-line {
    color: rgba(4, 21, 31, 0.58);
}

.card-text {
    margin: 0;
    color: rgba(4, 21, 31, 0.74);
    line-height: 1.65;
    min-height: 106px;
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 4;
}

.card-link {
    width: 100%;
    margin-top: auto;
    padding: 13px 16px;
    border-radius: 10px;
    background: rgba(14, 116, 144, 0.1);
    color: #0e7490;
    font-weight: 600;
    letter-spacing: 0.01em;
    text-align: center;
    transition:
        background-color 180ms ease,
        color 180ms ease,
        transform 180ms ease;
}

.listing-card:hover .card-link {
    background: #0e7490;
    color: white;
}

.card-link:hover {
    transform: translateY(-1px);
}

.pagination-shell {
    display: grid;
    gap: 12px;
    margin-top: 20px;
}

.pagination-desktop,
.pagination-mobile {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
}

.pagination-mobile {
    display: none;
}

.pagination-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 42px;
    min-height: 42px;
    padding: 0 14px;
    border: 1px solid rgba(4, 21, 31, 0.1);
    border-radius: 10px;
    background: #ffffff;
    color: #0f172a;
    font-size: 0.85rem;
    font-weight: 600;
    transition: border-color 180ms ease, background-color 180ms ease, color 180ms ease, transform 180ms ease;
}

.pagination-button:hover:not(:disabled) {
    transform: translateY(-1px);
    border-color: rgba(14, 116, 144, 0.24);
}

.pagination-button:disabled {
    opacity: 0.42;
    cursor: not-allowed;
}

.pagination-page {
    min-width: 42px;
    padding: 0;
}

.pagination-page.is-active {
    border-color: #0e7490;
    background: #0e7490;
    color: #ffffff;
}

.pagination-ellipsis,
.pagination-mobile-state {
    color: rgba(4, 21, 31, 0.6);
    font-size: 0.86rem;
    font-weight: 600;
}

.empty-card {
    margin-top: 18px;
    padding: 32px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.78);
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.08);
}

.empty-card p {
    margin-bottom: 0;
    color: rgba(4, 21, 31, 0.72);
    line-height: 1.7;
}

@media (max-width: 1180px) {
    .directory-layout {
        grid-template-columns: minmax(260px, 300px) minmax(0, 1fr);
    }
}

@media (max-width: 960px) {
    .directory-layout {
        grid-template-columns: 1fr;
    }

    .directory-sidebar-card {
        position: static;
    }

    .filters-head {
        align-items: flex-start;
        flex-direction: column;
    }

    .filters-grid,
    .category-grid {
        grid-template-columns: 1fr;
    }

    .filters-submit-row {
        flex-wrap: wrap;
    }
}

@media (max-width: 720px) {
    .pagination-desktop {
        display: none;
    }

    .pagination-mobile {
        display: flex;
    }

    .filters-grid,
    .category-grid,
    .listing-grid {
        grid-template-columns: 1fr;
    }

    .intro-card,
    .filters-card,
    .empty-card {
        padding: 24px;
    }

    .field-wide {
        grid-column: auto;
    }
}
</style>




