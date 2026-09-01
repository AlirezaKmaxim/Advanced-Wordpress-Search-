/**
 * Utility functions for Alireza Smart Search.
 *
 * @package Alireza_Ajax_Search
 */

/**
 * Escape HTML special characters to prevent XSS.
 *
 * @param {string} str
 * @return {string}
 */
export function escapeHTML(str) {
    if (!str) return '';
    return String(str).replace(/[&<>'"]/g, 
        tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag)
    );
}

/**
 * Debounce a function call by a specified delay.
 *
 * @param {Function} func
 * @param {number} delay
 * @return {Function}
 */
export function debounce(func, delay) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
    };
}

/**
 * Build the base URL for the WordPress search results page.
 *
 * @param {string} term
 * @return {string}
 */
export function getSearchPageUrl(term) {
    const base = (typeof window.alirezaSearchSettings !== 'undefined' && window.alirezaSearchSettings.search_url)
        ? window.alirezaSearchSettings.search_url
        : '/';
    return base + '?s=' + encodeURIComponent(term);
}

/**
 * Format price string with thousands separators.
 *
 * @param {string|number} priceStr
 * @return {string}
 */
export function formatPrice(priceStr) {
    if (!priceStr) return '';
    const numeric = parseFloat(priceStr);
    if (isNaN(numeric)) return priceStr;
    return Math.round(numeric).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

/**
 * Decide whether the user is already typing inside any input/textarea.
 *
 * @return {boolean}
 */
export function isTypingElsewhere() {
    const el = document.activeElement;
    if (!el) return false;
    const tag = el.tagName.toUpperCase();
    return (
        tag === 'INPUT' ||
        tag === 'TEXTAREA' ||
        tag === 'SELECT' ||
        el.isContentEditable
    );
}
