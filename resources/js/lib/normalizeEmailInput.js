export const normalizeEmailInput = (value = '') => String(value)
    .trim()
    .replace(/\u0130/g, 'I')
    .replace(/\u0131/g, 'i')
    .replace(/\u0307/g, '')
    .toLowerCase();

