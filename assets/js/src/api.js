/**
 * API client and network handlers for Alireza Smart Search.
 *
 * @package Alireza_Ajax_Search
 */

import { postTypePriority } from './config.js';

// Bounded FIFO cache: caps memory growth over a long session with many
// distinct search terms instead of letting a plain object grow forever.
const SEARCH_CACHE_MAX_ENTRIES = 100;
export const searchCache = new Map();

function cacheGet(term) {
    return searchCache.get(term);
}

function cacheSet(term, data) {
    // Refresh recency so frequently repeated terms aren't the first evicted.
    if (searchCache.has(term)) {
        searchCache.delete(term);
    } else if (searchCache.size >= SEARCH_CACHE_MAX_ENTRIES) {
        const oldestKey = searchCache.keys().next().value;
        searchCache.delete(oldestKey);
    }
    searchCache.set(term, data);
}

// Separate AbortController per input (desktop/mobile) so an in-flight
// request from one field is never cancelled by typing in the other.
const activeFetchControllers = {};

/**
 * Sort posts by pre-defined post type priority.
 *
 * @param {Array} posts
 * @return {Array}
 */
export function sortPostsByType(posts) {
    return (posts || []).slice().sort((a, b) => {
        const pa = postTypePriority[a.post_type] !== undefined ? postTypePriority[a.post_type] : 99;
        const pb = postTypePriority[b.post_type] !== undefined ? postTypePriority[b.post_type] : 99;
        return pa - pb;
    });
}

/**
 * Perform an AJAX search request with AbortController and client caching.
 *
 * @param {string} term
 * @param {string} [source] Identifies the calling input ('desktop'/'mobile') so
 *                           concurrent requests from different inputs don't abort each other.
 * @return {Promise<{data: Object|null, fromCache: boolean, aborted?: boolean, error?: boolean}>}
 */
export async function fetchSearchResults(term, source = 'default') {
    const cached = cacheGet(term);
    if (cached) {
        return { data: cached, fromCache: true };
    }

    if (activeFetchControllers[source]) {
        activeFetchControllers[source].abort();
    }
    const controller = new AbortController();
    activeFetchControllers[source] = controller;

    const formData = new FormData();
    formData.append('action', 'alireza_global_search');
    formData.append('term', term);

    if (typeof window.alirezaSearchSettings !== 'undefined' && window.alirezaSearchSettings.nonce) {
        formData.append('nonce', window.alirezaSearchSettings.nonce);
    }

    const ajaxUrl = (typeof window.alirezaSearchSettings !== 'undefined' && window.alirezaSearchSettings.ajax_url)
        ? window.alirezaSearchSettings.ajax_url
        : '/wp-admin/admin-ajax.php';

    try {
        const response = await fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
            signal: controller.signal
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success && data.data && (data.data.posts || data.data.categories)) {
            cacheSet(term, data.data);
            return { data: data.data, fromCache: false };
        }

        return { data: null, fromCache: false, error: true };
    } catch (error) {
        if (error.name === 'AbortError') {
            return { data: null, fromCache: false, aborted: true };
        }
        return { data: null, fromCache: false, error: true };
    }
}
