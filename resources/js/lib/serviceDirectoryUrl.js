const normalizeQueryValue = (value) => {
    if (value === undefined || value === null) {
        return null;
    }

    const stringValue = String(value).trim();

    if (stringValue === '') {
        return null;
    }

    return stringValue;
};

const normalizeQueryArray = (values) => {
    if (!Array.isArray(values)) {
        return [];
    }

    return values
        .map((value) => normalizeQueryValue(value))
        .filter(Boolean);
};

export const buildServiceDirectoryUrl = ({
    categories = [],
    subcategories = [],
    search = '',
    brands = [],
    countries = [],
    ports = [],
    page = null,
} = {}) => {
    const segments = ['/services'];
    const query = new URLSearchParams();
    const normalizedCategories = normalizeQueryArray(categories);
    const normalizedSubcategories = normalizeQueryArray(subcategories);
    const normalizedSearch = normalizeQueryValue(search);
    const normalizedBrands = normalizeQueryArray(brands);
    const normalizedCountries = normalizeQueryArray(countries);
    const normalizedPorts = normalizeQueryArray(ports);
    const normalizedPage = normalizeQueryValue(page);

    normalizedCategories.forEach((value) => query.append('categories[]', value));
    normalizedSubcategories.forEach((value) => query.append('subcategories[]', value));

    if (normalizedSearch) {
        query.set('search', normalizedSearch);
    }

    normalizedBrands.forEach((value) => query.append('brands[]', value));

    normalizedCountries.forEach((value) => query.append('countries[]', value));

    normalizedPorts.forEach((value) => query.append('ports[]', value));

    if (normalizedPage && Number(normalizedPage) > 1) {
        query.set('page', normalizedPage);
    }

    const path = segments.join('/');
    const queryString = query.toString();

    return queryString ? `${path}?${queryString}` : path;
};
