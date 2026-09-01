/**
 * Entry point for Alireza Smart Search plugin frontend.
 *
 * @package Alireza_Ajax_Search
 */

import { debounce } from './utils.js';
import { fetchSearchResults } from './api.js';
import { renderSearchResults, showError, toggleClear } from './render.js';
import { setupKeyboardShortcuts } from './shortcuts.js';

function initSearchApp() {
    // Look for app wrapper or input element
    const desktopInput = document.getElementById('desktopSearchInput');
    const appWrapper   = document.getElementById('alireza-ajax-search-app') || document.getElementById('hamseda-ajax-search-app') || desktopInput?.closest('.font-yekan');

    if (!desktopInput && !appWrapper) return;

    // --- DOM Elements ---
    const desktopClearBtn = document.getElementById('desktopClearBtn');
    const desktopDropdown = document.getElementById('desktopDropdown');
    const desktopLoader   = document.getElementById('desktopLoader');
    const desktopResults  = document.getElementById('desktopResults');

    const mobileTrigger   = document.getElementById('mobileSearchTrigger');
    const mobileModal     = document.getElementById('mobileModal');
    const mobileCloseBtn  = document.getElementById('mobileCloseBtn');
    const mobileInput     = document.getElementById('mobileSearchInput');
    const mobileClearBtn  = document.getElementById('mobileClearBtn');
    const mobileLoader    = document.getElementById('mobileLoader');
    const mobileResults   = document.getElementById('mobileResults');

    /**
     * Perform search and update UI.
     *
     * @param {string} term
     * @param {HTMLElement} loader
     * @param {HTMLElement} resultsContainer
     * @param {boolean} isDesktop
     */
    async function performSearch(term, loader, resultsContainer, isDesktop) {
        if (!resultsContainer || !loader) return;

        const categoriesWrapper   = resultsContainer.querySelector('[id$="CategoriesWrapper"]');
        const categoriesContainer = resultsContainer.querySelector('[id$="Categories"]');
        const postsWrapper        = resultsContainer.querySelector('[id$="PostsWrapper"]');
        const postsContainer      = resultsContainer.querySelector('[id$="Posts"]');
        const noResults           = resultsContainer.querySelector('[id$="NoResults"]');

        if (term.length === 0) {
            resultsContainer.classList.add('hidden');
            resultsContainer.style.display = 'none';
            if (categoriesContainer) categoriesContainer.innerHTML = '';
            if (postsContainer) postsContainer.innerHTML = '';
            if (isDesktop && desktopDropdown) {
                desktopDropdown.classList.add('hidden');
                desktopDropdown.style.display = 'none';
            }
            return;
        }

        loader.classList.remove('hidden');
        loader.style.display = 'flex';
        resultsContainer.classList.add('hidden');
        resultsContainer.style.display = 'none';

        if (categoriesContainer) categoriesContainer.innerHTML = '';
        if (postsContainer) postsContainer.innerHTML = '';
        if (categoriesWrapper) {
            categoriesWrapper.classList.add('hidden');
            categoriesWrapper.style.display = 'none';
        }
        if (postsWrapper) {
            postsWrapper.classList.add('hidden');
            postsWrapper.style.display = 'none';
        }
        if (isDesktop && desktopDropdown) {
            desktopDropdown.classList.remove('hidden');
            desktopDropdown.style.display = 'block';
        }

        const result = await fetchSearchResults(term, isDesktop ? 'desktop' : 'mobile');

        if (result.aborted) {
            return;
        }

        loader.classList.add('hidden');
        loader.style.display = 'none';

        if (result.data) {
            renderSearchResults(
                result.data,
                resultsContainer,
                categoriesWrapper,
                categoriesContainer,
                postsWrapper,
                postsContainer,
                noResults,
                term
            );
        } else {
            showError(resultsContainer, noResults);
        }
    }

    // --- Desktop Event Listeners ---
    if (desktopInput && desktopDropdown && desktopLoader && desktopResults) {
        const debouncedDesktopSearch = debounce((term) => performSearch(term, desktopLoader, desktopResults, true), 150);

        desktopInput.addEventListener('input', () => {
            toggleClear(desktopInput, desktopClearBtn);
            const val = desktopInput.value.trim();
            if (val.length >= 2) {
                desktopResults.classList.add('hidden');
                desktopResults.style.display = 'none';
                
                const catsContainer  = desktopResults.querySelector('#desktopCategories');
                const postsContainer = desktopResults.querySelector('#desktopPosts');
                const catsWrapper    = desktopResults.querySelector('#desktopCategoriesWrapper');
                const postsWrapper   = desktopResults.querySelector('#desktopPostsWrapper');
                if (catsContainer) catsContainer.innerHTML = '';
                if (postsContainer) postsContainer.innerHTML = '';
                if (catsWrapper) {
                    catsWrapper.classList.add('hidden');
                    catsWrapper.style.display = 'none';
                }
                if (postsWrapper) {
                    postsWrapper.classList.add('hidden');
                    postsWrapper.style.display = 'none';
                }

                desktopLoader.classList.remove('hidden');
                desktopLoader.style.display = 'flex';
                desktopDropdown.classList.remove('hidden');
                desktopDropdown.style.display = 'block';

                const old = desktopDropdown.querySelector('#alireza-view-all-link') || desktopDropdown.querySelector('#hamseda-view-all-link');
                if (old) old.remove();

                debouncedDesktopSearch(val);
            } else {
                desktopResults.classList.add('hidden');
                desktopResults.style.display = 'none';
                desktopDropdown.classList.add('hidden');
                desktopDropdown.style.display = 'none';
                const old = desktopDropdown.querySelector('#alireza-view-all-link') || desktopDropdown.querySelector('#hamseda-view-all-link');
                if (old) old.remove();
            }
        });

        if (desktopClearBtn) {
            desktopClearBtn.addEventListener('click', () => {
                desktopInput.value = '';
                toggleClear(desktopInput, desktopClearBtn);
                desktopDropdown.classList.add('hidden');
                desktopDropdown.style.display = 'none';
                desktopResults.classList.add('hidden');
                desktopResults.style.display = 'none';
                const old = desktopDropdown.querySelector('#alireza-view-all-link') || desktopDropdown.querySelector('#hamseda-view-all-link');
                if (old) old.remove();
                desktopInput.focus();
            });
        }

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#alireza-ajax-search-app') && !e.target.closest('#hamseda-ajax-search-app') && !e.target.closest('.alireza-search-inner')) {
                desktopDropdown.classList.add('hidden');
                desktopDropdown.style.display = 'none';
            }
        });
        
        desktopInput.addEventListener('focus', () => {
            if (desktopInput.value.trim().length >= 2) {
                desktopDropdown.classList.remove('hidden');
                desktopDropdown.style.display = 'block';
            }
        });
    }

    // --- Mobile Event Listeners ---
    if (mobileTrigger && mobileModal) {
        mobileTrigger.addEventListener('click', () => {
            mobileModal.classList.remove('hidden');
            mobileModal.style.display = 'block';
            document.body.classList.add('modal-open');
            if (mobileInput) {
                setTimeout(() => mobileInput.focus(), 100);
            }
        });

        if (mobileCloseBtn) {
            mobileCloseBtn.addEventListener('click', () => {
                mobileModal.classList.add('hidden');
                mobileModal.style.display = 'none';
                document.body.classList.remove('modal-open');
            });
        }

        if (mobileInput && mobileLoader && mobileResults) {
            const debouncedMobileSearch = debounce((term) => performSearch(term, mobileLoader, mobileResults, false), 150);

            mobileInput.addEventListener('input', () => {
                toggleClear(mobileInput, mobileClearBtn);
                const val = mobileInput.value.trim();
                if (val.length >= 2) {
                    debouncedMobileSearch(val);
                } else {
                    mobileResults.classList.add('hidden');
                    mobileResults.style.display = 'none';
                }
            });

            if (mobileClearBtn) {
                mobileClearBtn.addEventListener('click', () => {
                    mobileInput.value = '';
                    toggleClear(mobileInput, mobileClearBtn);
                    mobileResults.classList.add('hidden');
                    mobileResults.style.display = 'none';
                    mobileInput.focus();
                });
            }
        }
    }

    // --- Keyboard Shortcuts ---
    setupKeyboardShortcuts(() => {
        if (desktopInput) {
            desktopInput.focus();
            desktopInput.select();
        } else if (mobileTrigger) {
            mobileTrigger.click();
        }
    });
}

// Auto initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSearchApp);
} else {
    initSearchApp();
}

// Re-init when window loads to cover dynamic headers / builders
window.addEventListener('load', initSearchApp);
