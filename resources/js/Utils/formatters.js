/**
 * Format raw number string or number to thousand-separated rupiah format (e.g., 500000 -> "500.000").
 *
 * @param {number|string} value
 * @returns {string}
 */
export function formatRupiahInput(value) {
    if (value === null || value === undefined || value === '') return '';
    const cleanStr = String(value).replace(/\D/g, '');
    if (!cleanStr) return '';
    return new Intl.NumberFormat('id-ID').format(cleanStr);
}

/**
 * Parse thousand-separated rupiah string back to a numeric value (e.g., "500.000" -> 500000).
 *
 * @param {string} formatted
 * @returns {number|string}
 */
export function parseRupiahInput(formatted) {
    if (formatted === null || formatted === undefined || formatted === '') return '';
    const cleanStr = String(formatted).replace(/\D/g, '');
    return cleanStr ? parseInt(cleanStr, 10) : '';
}

/**
 * Format numeric value as Indonesian Rupiah or specified currency.
 *
 * @param {number|string} amount
 * @param {string} currency
 * @returns {string}
 */
export function formatCurrency(amount, currency = 'Rp') {
    const num = parseFloat(amount) || 0;
    const formatted = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(num);

    return `${currency} ${formatted}`;
}

/**
 * Format standard date to Indonesian locale readable date.
 *
 * @param {string} dateString
 * @param {boolean} withDay
 * @returns {string}
 */
export function formatDate(dateString, withDay = false) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;

    const options = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    };

    if (withDay) {
        options.weekday = 'long';
    }

    return new Intl.DateTimeFormat('id-ID', options).format(date);
}
