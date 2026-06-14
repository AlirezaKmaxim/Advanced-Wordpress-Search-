/**
 * Frontend logic for HamSeda Ajax Search plugin.
 */
document.addEventListener('DOMContentLoaded', () => {
    const appWrapper = document.getElementById('hamseda-ajax-search-app');
    if (!appWrapper) return;

    // --- DOM Elements ---
    const desktopInput = document.getElementById('desktopSearchInput');
    const desktopClearBtn = document.getElementById('desktopClearBtn');
    const desktopDropdown = document.getElementById('desktopDropdown');
    const desktopLoader = document.getElementById('desktopLoader');
    const desktopResults = document.getElementById('desktopResults');

    const mobileTrigger = document.getElementById('mobileSearchTrigger');
    const mobileModal = document.getElementById('mobileModal');
    const mobileCloseBtn = document.getElementById('mobileCloseBtn');
    const mobileInput = document.getElementById('mobileSearchInput');
    const mobileClearBtn = document.getElementById('mobileClearBtn');
    const mobileLoader = document.getElementById('mobileLoader');
    const mobileResults = document.getElementById('mobileResults');

    let activeFetchController = null;

    // Badge configuration logic ported from PHP/previous JS
    const badgeConfig = {
        esanj: { text: 'تست روانسنجی', classes: 'bg-[#5977BF] text-white', svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>' },
        post: { text: 'مقاله', classes: 'bg-[#DDCBFB] text-[#1F3161]', svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>' },
        page: { text: 'صفحه', classes: 'bg-[#1F3161] text-white', svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>' },
        product: { text: 'محصول', classes: 'bg-[#F59E0B] text-white', svg: '<use href="#icon-cart"></use>' },
        product_cat: { text: 'دسته‌بندی', classes: 'bg-[#10B981] text-white', svg: '<use href="#icon-folder"></use>' },
        default: { text: 'دیگر', classes: 'bg-[#EDF0F8] text-[#242424]', svg: '' }
    };

    // Helper: Escape HTML
    function escapeHTML(str) {
        if (!str) return '';
        return str.replace(/[&<>'"]/g, 
            tag => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[tag] || tag)
        );
    }

    function debounce(func, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

    // Helper: Format price with thousands separators
    function formatPrice(priceStr) {
        if (!priceStr) return '';
        const numeric = parseFloat(priceStr);
        if (isNaN(numeric)) return priceStr;
        return Math.round(numeric).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // --- Helper: Generate Result HTML ---
    function createResultHTML(item, index) {
        // Fallback styling for unknown post types
        const badge = badgeConfig[item.post_type] || {
            classes: 'bg-[#242424] text-white',
            svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><circle cx="12" cy="10" r="2" stroke-width="2"></circle>'
        };
        const badgeText = item.post_type_label ? escapeHTML(item.post_type_label) : (badge.text || 'دیگر');
        const delay = index * 50; 
        
        const isNoImage = (item.image_url === null || item.image_url === 'null' || !item.image_url);

        const imageContent = !isNoImage 
            ? `<img src="${escapeHTML(item.image_url)}" class="w-full h-full object-cover" alt="">`
            : `<div class="w-full h-full bg-[#F7F9FC] flex items-center justify-center">
                 <svg class="w-6 h-6 text-[#5977BF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">${badge.svg || '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>'}</svg>
               </div>`;

        let extraHTML = '';
        if (item.post_type === 'product') {
            const isOutOfStock = item.stock_status === 'outofstock';
            let priceContent = '';
            
            if (isOutOfStock) {
                priceContent = '<span class="text-red-500 text-xs font-bold bg-red-50 px-2 py-0.5 rounded border border-red-100">ناموجود</span>';
            } else if (item.sale_price) {
                priceContent = `<span class="line-through text-gray-400 text-xs ml-1.5">${escapeHTML(formatPrice(item.regular_price))}</span><span class="text-[#10B981] font-bold text-sm">${escapeHTML(formatPrice(item.sale_price))} تومان</span>`;
            } else if (item.regular_price) {
                priceContent = `<span class="text-[#5977BF] font-bold text-sm">${escapeHTML(formatPrice(item.regular_price))} تومان</span>`;
            }
            
            if (priceContent) {
                extraHTML = `<div class="mt-1.5 flex items-center justify-start w-full gap-2">${priceContent}</div>`;
            }
        }

        return `
            <a href="${escapeHTML(item.permalink)}" class="result-item flex items-center gap-4 p-3 rounded-xl transition-all duration-200 hover:bg-[#F7F9FC] group cursor-pointer border border-gray-100" style="animation-delay: ${delay}ms; display: flex;">
                <div class="w-14 h-14 rounded-lg overflow-hidden flex-shrink-0 border border-gray-100">
                    ${imageContent}
                </div>
                <div class="flex-1 min-w-0 flex flex-col justify-center">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <h4 class="text-sm md:text-base text-[#1F3161] font-bold truncate group-hover:text-[#5977BF] transition-colors">${escapeHTML(item.title)}</h4>
                        <span class="px-3 py-0.5 rounded-full text-xs font-medium flex items-center gap-1 flex-shrink-0 scale-[1.02] origin-center ${badge.classes}">
                            ${badge.svg ? `<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">${badge.svg}</svg>` : ''}
                            ${badgeText}
                        </span>
                    </div>
                    ${extraHTML}
                </div>
            </a>
        `;
    }

    // --- Helper: Render Categories as Pills ---
    function renderCategories(categoriesData) {
        let html = '';
        categoriesData.forEach((cat, index) => {
            const delay = index * 50;
            html += `
                <a href="${escapeHTML(cat.url)}" class="result-item inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-primaryLight hover:bg-secondaryBlue hover:text-white transition-colors duration-200 group border border-transparent hover:border-secondaryBlue/20" style="animation-delay: ${delay}ms;">
                    <svg class="w-4 h-4 text-secondaryBlue group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><use href="#icon-folder"></use></svg>
                    <span class="text-sm font-medium text-deepNavy group-hover:text-white transition-colors">${escapeHTML(cat.name)}</span>
                    <span class="text-xs font-bold bg-white text-secondaryBlue group-hover:bg-deepNavy group-hover:text-white px-1.5 py-0.5 rounded-full transition-colors">${escapeHTML(String(cat.count))}</span>
                </a>
            `;
        });
        return html;
    }

    // --- Helper: Toggle Clear Button ---
    function toggleClear(input, clearBtn) {
        if (input.value.length > 0) {
            clearBtn.classList.remove('opacity-0', 'scale-75', 'invisible');
            clearBtn.classList.add('opacity-100', 'scale-100', 'visible');
        } else {
            clearBtn.classList.add('opacity-0', 'scale-75', 'invisible');
            clearBtn.classList.remove('opacity-100', 'scale-100', 'visible');
        }
    }

    async function performSearch(term, loader, resultsContainer, isDesktop) {
        const categoriesWrapper = resultsContainer.querySelector('[id$="CategoriesWrapper"]');
        const categoriesContainer = resultsContainer.querySelector('[id$="Categories"]');
        const postsWrapper = resultsContainer.querySelector('[id$="PostsWrapper"]');
        const postsContainer = resultsContainer.querySelector('[id$="Posts"]');
        const noResults = resultsContainer.querySelector('[id$="NoResults"]');

        if (term.length === 0) {
            resultsContainer.classList.add('hidden');
            if (categoriesContainer) categoriesContainer.innerHTML = '';
            if (postsContainer) postsContainer.innerHTML = '';
            if (isDesktop) desktopDropdown.classList.add('hidden');
            return;
        }

        if (activeFetchController) {
            activeFetchController.abort();
        }
        activeFetchController = new AbortController();

        loader.classList.remove('hidden');
        resultsContainer.classList.add('hidden');
        if (isDesktop) desktopDropdown.classList.remove('hidden');

        const formData = new FormData();
        formData.append('action', 'hamseda_global_search');
        formData.append('term', term);
        // Default nonce value fallback if not enqueued correctly
        if (typeof hamsedaSearchSettings !== 'undefined') {
            formData.append('nonce', hamsedaSearchSettings.nonce);
        }

        try {
            const ajaxUrl = typeof hamsedaSearchSettings !== 'undefined' ? hamsedaSearchSettings.ajax_url : '/wp-admin/admin-ajax.php';
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                signal: activeFetchController.signal
            });

            if (!response.ok) throw new Error('Network response error');

            const data = await response.json();
            loader.classList.add('hidden');

            if (data.success && data.data && (data.data.posts || data.data.categories)) {
                renderSearchResults(data.data, resultsContainer, categoriesWrapper, categoriesContainer, postsWrapper, postsContainer, noResults);
            } else {
                showError(resultsContainer, noResults);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                loader.classList.add('hidden');
                showError(resultsContainer, noResults);
            }
        }
    }

    function showError(resultsContainer, noResults) {
        resultsContainer.classList.remove('hidden');
        resultsContainer.style.display = 'flex';
        noResults.innerHTML = 'خطایی در دریافت اطلاعات رخ داد.';
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

    function renderSearchResults(data, resultsContainer, categoriesWrapper, categoriesContainer, postsWrapper, postsContainer, noResults) {
        const posts = data.posts || [];
        const categories = data.categories || [];

        resultsContainer.classList.remove('hidden');
        resultsContainer.style.display = 'flex';

        if (posts.length === 0 && categories.length === 0) {
            if (categoriesWrapper) {
                categoriesWrapper.classList.add('hidden');
                categoriesWrapper.style.display = 'none';
            }
            if (postsWrapper) {
                postsWrapper.classList.add('hidden');
                postsWrapper.style.display = 'none';
            }
            noResults.innerHTML = 'نتیجه‌ای یافت نشد.';
            noResults.classList.remove('hidden');
            return;
        }

        noResults.classList.add('hidden');
        
        // Render Categories
        if (categories.length > 0) {
            categoriesContainer.innerHTML = renderCategories(categories);
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
                // Offset the index for animation delay
                postHtml += createResultHTML(item, index + categories.length);
            });
            postsContainer.innerHTML = postHtml;
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
    }

    // --- Desktop Event Listeners ---
    if (desktopInput) {
        const debouncedDesktopSearch = debounce((term) => performSearch(term, desktopLoader, desktopResults, true), 400);

        desktopInput.addEventListener('input', () => {
            toggleClear(desktopInput, desktopClearBtn);
            if (desktopInput.value.trim().length > 2) {
                debouncedDesktopSearch(desktopInput.value.trim());
            } else {
                desktopResults.classList.add('hidden');
                desktopDropdown.classList.add('hidden');
            }
        });

        desktopClearBtn.addEventListener('click', () => {
            desktopInput.value = '';
            toggleClear(desktopInput, desktopClearBtn);
            desktopDropdown.classList.add('hidden');
            desktopResults.classList.add('hidden');
            desktopInput.focus();
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.relative')) desktopDropdown.classList.add('hidden');
        });
        
        desktopInput.addEventListener('focus', () => {
            if (desktopInput.value.trim().length > 2) {
                desktopDropdown.classList.remove('hidden');
            }
        });
    }

    // --- Mobile Event Listeners ---
    if (mobileTrigger) {
        const debouncedMobileSearch = debounce((term) => performSearch(term, mobileLoader, mobileResults, false), 400);

        mobileTrigger.addEventListener('click', () => {
            mobileModal.classList.remove('hidden');
            mobileModal.style.zIndex = '99999';
            setTimeout(() => mobileModal.classList.remove('translate-y-full'), 10);
            document.body.classList.add('modal-open');
            setTimeout(() => mobileInput.focus(), 100);
        });

        mobileCloseBtn.addEventListener('click', () => {
            mobileModal.classList.add('translate-y-full');
            document.body.classList.remove('modal-open');
            setTimeout(() => mobileModal.classList.add('hidden'), 300);
        });

        mobileInput.addEventListener('input', () => {
            toggleClear(mobileInput, mobileClearBtn);
            if (mobileInput.value.trim().length > 2) {
                debouncedMobileSearch(mobileInput.value.trim());
            } else {
                mobileResults.classList.add('hidden');
            }
        });

        mobileClearBtn.addEventListener('click', () => {
            mobileInput.value = '';
            toggleClear(mobileInput, mobileClearBtn);
            mobileResults.classList.add('hidden');
            mobileInput.focus();
        });
    }

});
