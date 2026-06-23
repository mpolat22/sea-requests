export const consentKey = 'sea_requests_cookie_consent';
export const consentValue = 'accepted';
export const consentTimestampKey = `${consentKey}_at`;

export const hasCookieConsent = () => {
    if (typeof window === 'undefined') {
        return true;
    }

    try {
        if (window.localStorage.getItem(consentKey) === consentValue) {
            return true;
        }
    } catch {
        // Ignore storage access errors and fall back to cookie check.
    }

    return document.cookie
        .split('; ')
        .some((part) => part === `${consentKey}=${consentValue}` || part.startsWith(`${consentKey}=${consentValue};`));
};

export const persistCookieConsent = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const acceptedAt = new Date().toISOString();

    try {
        window.localStorage.setItem(consentKey, consentValue);
        window.localStorage.setItem(consentTimestampKey, acceptedAt);
    } catch {
        // Ignore storage access errors and still persist through a cookie.
    }

    document.cookie = `${consentKey}=${consentValue}; Max-Age=31536000; Path=/; SameSite=Lax`;
};
