/**
 * DOM rendering helpers for Alireza Smart Search — Modern UI/UX Edition.
 *
 * @package Alireza_Ajax_Search
 */

import { badgeConfig } from './config.js';
import { escapeHTML, formatPrice, getSearchPageUrl } from './utils.js';
import { sortPostsByType } from './api.js';

/**
 * Render "View all results" footer button.
 *
 * @param {string} term
 * @return {string}
 */
export function renderViewAllLink(term) {
    const url = getSearchPageUrl(term);
    return `
        <a href="${escapeHTML(url)}"
           id="alireza-view-all-link"
           class="view-all-link">
            <span>نمایش همه نتایج برای «${escapeHTML(term)}»</span>
            <svg class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"></path>
            </svg>
        </a>
    `;
}

/**
 * Generate individual item HTML result.
 *
 * @param {Object} item
 * @param {number} index
 * @return {string}
 */
export function createResultHTML(item, index) {
    const badge = badgeConfig[item.post_type] || {
        classes: 'bg-slate-700 text-white',
        svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><circle cx="12" cy="10" r="2" stroke-width="2"></circle>'
    };
    const badgeText = item.post_type_label ? escapeHTML(item.post_type_label) : (badge.text || 'محتوا');
    const delay = index * 40; 
    
    const isNoImage = (item.image_url === null || item.image_url === 'null' || !item.image_url);

    const imageContent = !isNoImage 
        ? `<img src="${escapeHTML(item.image_url)}" loading="lazy" decoding="async" class="w-full h-full object-cover rounded-lg group-hover:scale-105 transition-transform duration-300" alt="">`
        : `<div class="w-full h-full bg-slate-50 flex items-center justify-center text-slate-400 rounded-lg">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">${badge.svg || '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>'}</svg>
           </div>`;

    // Dynamic badge inline style if custom color is returned
    const badgeStyle = item.badge_color 
        ? `style="background-color: ${escapeHTML(item.badge_color)}; color: #FFFFFF;"` 
        : '';
    const badgeClass = item.badge_color ? '' : badge.classes;

    let extraHTML = '';
    if (item.post_type === 'product') {
        const isOutOfStock = item.stock_status === 'outofstock';
        let priceContent = '';
        
        if (isOutOfStock) {
            priceContent = '<span class="text-rose-600 text-xs font-bold bg-rose-50 px-2 py-0.5 rounded-md border border-rose-200">ناموجود</span>';
        } else if (item.sale_price) {
            priceContent = `<span class="line-through text-slate-400 text-xs ml-1.5">${escapeHTML(formatPrice(item.regular_price))}</span><span class="price-highlight font-bold text-sm">${escapeHTML(formatPrice(item.sale_price))} <span class="text-xs font-normal">تومان</span></span>`;
        } else if (item.regular_price) {
            priceContent = `<span class="text-slate-800 font-bold text-sm">${escapeHTML(formatPrice(item.regular_price))} <span class="text-xs font-normal text-slate-500">تومان</span></span>`;
        }
        
        if (priceContent) {
            extraHTML = `<div class="mt-1.5 flex items-center justify-start w-full gap-2">${priceContent}</div>`;
        }
    }

    return `
        <a href="${escapeHTML(item.permalink)}" class="result-item flex items-center gap-3.5 p-2.5 rounded-xl transition-all duration-200 group cursor-pointer" style="animation-delay: ${delay}ms; display: flex;">
            <div class="w-14 h-14 rounded-lg overflow-hidden flex-shrink-0 bg-slate-50 border border-slate-100">
                ${imageContent}
            </div>
            <div class="flex-1 min-w-0 flex flex-col justify-center">
                <div class="flex items-center justify-between gap-2 mb-1 w-full min-w-0">
                    <h4 class="result-title text-sm md:text-base font-bold text-slate-800 truncate group-hover:text-[var(--alz-primary,#FA7993)] transition-colors flex-1 min-w-0">${escapeHTML(item.title)}</h4>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold flex items-center gap-1 flex-shrink-0 shadow-2xs ${badgeClass}" ${badgeStyle}>
                        ${badge.svg ? `<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">${badge.svg}</svg>` : ''}
                        ${badgeText}
                    </span>
                </div>
                ${extraHTML}
            </div>
        </a>
    `;
}

/**
 * Render categories pills.
 *
 * @param {Array} categoriesData
 * @return {string}
 */
export function renderCategories(categoriesData) {
    let html = '';
    categoriesData.forEach((cat, index) => {
        const delay = index * 40;
        html += `
            <a href="${escapeHTML(cat.url)}" class="result-item inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs md:text-sm font-medium transition-all duration-200 group" style="animation-delay: ${delay}ms;">
                <svg class="w-3.5 h-3.5 transition-colors opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><use href="#icon-folder"></use></svg>
                <span>${escapeHTML(cat.name)}</span>
                <span class="text-[11px] font-bold bg-white text-slate-600 group-hover:bg-black/15 group-hover:text-white px-1.5 py-0.5 rounded-md transition-colors shadow-2xs">${escapeHTML(String(cat.count))}</span>
            </a>
        `;
    });
    return html;
}

/**
 * Toggle visibility of input clear button & keyboard shortcut badge.
 *
 * @param {HTMLInputElement} input
 * @param {HTMLElement} clearBtn
 */
export function toggleClear(input, clearBtn) {
    if (!input || !clearBtn) return;
    const kbdBadge = input.closest('.alireza-input-wrapper') 
        ? input.closest('.alireza-input-wrapper').querySelector('#alirezaKbdBadge') 
        : document.getElementById('alirezaKbdBadge');

    if (input.value.length > 0) {
        clearBtn.classList.remove('hidden', 'opacity-0', 'scale-75', 'invisible');
        clearBtn.classList.add('opacity-100', 'scale-100');
        clearBtn.style.display = 'flex';
        if (kbdBadge) {
            kbdBadge.classList.add('hidden');
            kbdBadge.style.display = 'none';
        }
    } else {
        clearBtn.classList.add('hidden', 'opacity-0', 'scale-75');
        clearBtn.classList.remove('opacity-100', 'scale-100');
        clearBtn.style.display = 'none';
        if (kbdBadge) {
            kbdBadge.classList.remove('hidden');
            kbdBadge.style.display = '';
        }
    }
}

/**
 * Display an error message inside the results container.
 *
 * @param {HTMLElement} resultsContainer
 * @param {HTMLElement} noResults
 */
export function showError(resultsContainer, noResults) {
    if (!resultsContainer || !noResults) return;
    resultsContainer.classList.remove('hidden');
    resultsContainer.style.display = 'flex';
    noResults.innerHTML = `
        <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-rose-50 flex items-center justify-center text-rose-500">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
        </div>
        <p class="text-sm font-semibold text-slate-700 mb-1">خطایی در دریافت اطلاعات رخ داد</p>
        <p class="text-xs text-slate-400">لطفاً اتصال اینترنت خود را بررسی کرده و مجدداً تلاش کنید.</p>
    `;
    noResults.classList.remove('hidden');
    
    const cats = resultsContainer.querySelector('[id$="CategoriesWrapper"]');
    const posts = resultsContainer.querySelector('[id$="PostsWrapper"]');
    if (cats) {
        cats.classList.add('hidden');
        cats.style.display = 'none';
    }
    if (posts) {
        posts.classList.add('hidden');
        posts.style.display = 'none';
    }
}

/**
 * Render all search results into the DOM.
 *
 * @param {Object} data
 * @param {HTMLElement} resultsContainer
 * @param {HTMLElement} categoriesWrapper
 * @param {HTMLElement} categoriesContainer
 * @param {HTMLElement} postsWrapper
 * @param {HTMLElement} postsContainer
 * @param {HTMLElement} noResults
 * @param {string} currentTerm
 */
export function renderSearchResults(data, resultsContainer, categoriesWrapper, categoriesContainer, postsWrapper, postsContainer, noResults, currentTerm) {
    const posts = sortPostsByType(data.posts || []);
    const categories = data.categories || [];

    resultsContainer.classList.remove('hidden');
    resultsContainer.style.display = 'flex';
    
    // Disable scrollbar temporarily during entry animation to prevent flickering
    resultsContainer.classList.add('overflow-y-hidden-imp');
    setTimeout(() => {
        resultsContainer.classList.remove('overflow-y-hidden-imp');
    }, 600);

    // Remove any previously rendered "view all" footer.
    const existingViewAll = resultsContainer.parentElement
        ? resultsContainer.parentElement.querySelector('#alireza-view-all-link')
        : null;
    if (existingViewAll) existingViewAll.remove();

    if (posts.length === 0 && categories.length === 0) {
        if (categoriesWrapper) {
            categoriesWrapper.classList.add('hidden');
            categoriesWrapper.style.display = 'none';
        }
        if (postsWrapper) {
            postsWrapper.classList.add('hidden');
            postsWrapper.style.display = 'none';
        }
        noResults.classList.remove('hidden');
        return;
    }

    noResults.classList.add('hidden');
    
    // Render Categories
    if (categories.length > 0) {
        if (categoriesContainer) categoriesContainer.innerHTML = renderCategories(categories);
        if (categoriesWrapper) {
            categoriesWrapper.classList.remove('hidden');
            categoriesWrapper.style.display = 'block';
        }
    } else {
        if (categoriesContainer) categoriesContainer.innerHTML = '';
        if (categoriesWrapper) {
            categoriesWrapper.classList.add('hidden');
            categoriesWrapper.style.display = 'none';
        }
    }
    
    // Render Posts/Products
    if (posts.length > 0) {
        let postHtml = '';
        posts.forEach((item, index) => {
            postHtml += createResultHTML(item, index + categories.length);
        });
        if (postsContainer) postsContainer.innerHTML = postHtml;
        if (postsWrapper) {
            postsWrapper.classList.remove('hidden');
            postsWrapper.style.display = 'block';
        }
    } else {
        if (postsContainer) postsContainer.innerHTML = '';
        if (postsWrapper) {
            postsWrapper.classList.add('hidden');
            postsWrapper.style.display = 'none';
        }
    }

    // Append "View all results" link after the scrollable results area.
    if (currentTerm) {
        resultsContainer.insertAdjacentHTML('afterend', renderViewAllLink(currentTerm));
    }
}
