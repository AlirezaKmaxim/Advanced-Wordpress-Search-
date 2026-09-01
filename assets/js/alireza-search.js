/*! Alireza Smart Search | (c) Alireza KMaxim | GPL-2.0 License */
(() => {
  // assets/js/src/utils.js
  function escapeHTML(str) {
    if (!str) return "";
    return String(str).replace(
      /[&<>'"]/g,
      (tag) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        "'": "&#39;",
        '"': "&quot;"
      })[tag] || tag
    );
  }
  function debounce(func, delay) {
    let timeout;
    return function(...args) {
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(this, args), delay);
    };
  }
  function getSearchPageUrl(term) {
    const base = typeof window.alirezaSearchSettings !== "undefined" && window.alirezaSearchSettings.search_url ? window.alirezaSearchSettings.search_url : "/";
    return base + "?s=" + encodeURIComponent(term);
  }
  function formatPrice(priceStr) {
    if (!priceStr) return "";
    const numeric = parseFloat(priceStr);
    if (isNaN(numeric)) return priceStr;
    return Math.round(numeric).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }
  function isTypingElsewhere() {
    const el = document.activeElement;
    if (!el) return false;
    const tag = el.tagName.toUpperCase();
    return tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT" || el.isContentEditable;
  }

  // assets/js/src/config.js
  var badgeConfig = {
    esanj: {
      text: "\u062A\u0633\u062A \u0631\u0648\u0627\u0646\u0633\u0646\u062C\u06CC",
      classes: "bg-[#FFB3C1] text-white",
      svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>'
    },
    post: {
      text: "\u0645\u0642\u0627\u0644\u0647",
      classes: "bg-[#7BA4F5] text-white",
      svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>'
    },
    page: {
      text: "\u0635\u0641\u062D\u0647",
      classes: "bg-[#3A3A4A] text-white",
      svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>'
    },
    product: {
      text: "\u0645\u062D\u0635\u0648\u0644",
      classes: "bg-[#FCE16D] text-[#3A3A4A]",
      svg: '<use href="#icon-cart"></use>'
    },
    product_cat: {
      text: "\u062F\u0633\u062A\u0647\u200C\u0628\u0646\u062F\u06CC",
      classes: "bg-[#FA7993] text-white",
      svg: '<use href="#icon-folder"></use>'
    },
    default: {
      text: "\u062F\u06CC\u06AF\u0631",
      classes: "bg-[#EFEFF3] text-[#3A3A4A]",
      svg: ""
    }
  };
  var postTypePriority = {
    esanj: 0,
    post: 1,
    page: 2,
    product: 3
  };

  // assets/js/src/api.js
  var SEARCH_CACHE_MAX_ENTRIES = 100;
  var searchCache = /* @__PURE__ */ new Map();
  function cacheGet(term) {
    return searchCache.get(term);
  }
  function cacheSet(term, data) {
    if (searchCache.has(term)) {
      searchCache.delete(term);
    } else if (searchCache.size >= SEARCH_CACHE_MAX_ENTRIES) {
      const oldestKey = searchCache.keys().next().value;
      searchCache.delete(oldestKey);
    }
    searchCache.set(term, data);
  }
  var activeFetchControllers = {};
  function sortPostsByType(posts) {
    return (posts || []).slice().sort((a, b) => {
      const pa = postTypePriority[a.post_type] !== void 0 ? postTypePriority[a.post_type] : 99;
      const pb = postTypePriority[b.post_type] !== void 0 ? postTypePriority[b.post_type] : 99;
      return pa - pb;
    });
  }
  async function fetchSearchResults(term, source = "default") {
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
    formData.append("action", "alireza_global_search");
    formData.append("term", term);
    if (typeof window.alirezaSearchSettings !== "undefined" && window.alirezaSearchSettings.nonce) {
      formData.append("nonce", window.alirezaSearchSettings.nonce);
    }
    const ajaxUrl = typeof window.alirezaSearchSettings !== "undefined" && window.alirezaSearchSettings.ajax_url ? window.alirezaSearchSettings.ajax_url : "/wp-admin/admin-ajax.php";
    try {
      const response = await fetch(ajaxUrl, {
        method: "POST",
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
      if (error.name === "AbortError") {
        return { data: null, fromCache: false, aborted: true };
      }
      return { data: null, fromCache: false, error: true };
    }
  }

  // assets/js/src/render.js
  function renderViewAllLink(term) {
    const url = getSearchPageUrl(term);
    return `
        <a href="${escapeHTML(url)}"
           id="alireza-view-all-link"
           class="view-all-link">
            <span>\u0646\u0645\u0627\u06CC\u0634 \u0647\u0645\u0647 \u0646\u062A\u0627\u06CC\u062C \u0628\u0631\u0627\u06CC \xAB${escapeHTML(term)}\xBB</span>
            <svg class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"></path>
            </svg>
        </a>
    `;
  }
  function createResultHTML(item, index) {
    const badge = badgeConfig[item.post_type] || {
      classes: "bg-slate-700 text-white",
      svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><circle cx="12" cy="10" r="2" stroke-width="2"></circle>'
    };
    const badgeText = item.post_type_label ? escapeHTML(item.post_type_label) : badge.text || "\u0645\u062D\u062A\u0648\u0627";
    const delay = index * 40;
    const isNoImage = item.image_url === null || item.image_url === "null" || !item.image_url;
    const imageContent = !isNoImage ? `<img src="${escapeHTML(item.image_url)}" loading="lazy" decoding="async" class="w-full h-full object-cover rounded-lg group-hover:scale-105 transition-transform duration-300" alt="">` : `<div class="w-full h-full bg-slate-50 flex items-center justify-center text-slate-400 rounded-lg">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">${badge.svg || '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>'}</svg>
           </div>`;
    const badgeStyle = item.badge_color ? `style="background-color: ${escapeHTML(item.badge_color)}; color: #FFFFFF;"` : "";
    const badgeClass = item.badge_color ? "" : badge.classes;
    let extraHTML = "";
    if (item.post_type === "product") {
      const isOutOfStock = item.stock_status === "outofstock";
      let priceContent = "";
      if (isOutOfStock) {
        priceContent = '<span class="text-rose-600 text-xs font-bold bg-rose-50 px-2 py-0.5 rounded-md border border-rose-200">\u0646\u0627\u0645\u0648\u062C\u0648\u062F</span>';
      } else if (item.sale_price) {
        priceContent = `<span class="line-through text-slate-400 text-xs ml-1.5">${escapeHTML(formatPrice(item.regular_price))}</span><span class="price-highlight font-bold text-sm">${escapeHTML(formatPrice(item.sale_price))} <span class="text-xs font-normal">\u062A\u0648\u0645\u0627\u0646</span></span>`;
      } else if (item.regular_price) {
        priceContent = `<span class="text-slate-800 font-bold text-sm">${escapeHTML(formatPrice(item.regular_price))} <span class="text-xs font-normal text-slate-500">\u062A\u0648\u0645\u0627\u0646</span></span>`;
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
                        ${badge.svg ? `<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">${badge.svg}</svg>` : ""}
                        ${badgeText}
                    </span>
                </div>
                ${extraHTML}
            </div>
        </a>
    `;
  }
  function renderCategories(categoriesData) {
    let html = "";
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
  function toggleClear(input, clearBtn) {
    if (!input || !clearBtn) return;
    const kbdBadge = input.closest(".alireza-input-wrapper") ? input.closest(".alireza-input-wrapper").querySelector("#alirezaKbdBadge") : document.getElementById("alirezaKbdBadge");
    if (input.value.length > 0) {
      clearBtn.classList.remove("hidden", "opacity-0", "scale-75", "invisible");
      clearBtn.classList.add("opacity-100", "scale-100");
      clearBtn.style.display = "flex";
      if (kbdBadge) {
        kbdBadge.classList.add("hidden");
        kbdBadge.style.display = "none";
      }
    } else {
      clearBtn.classList.add("hidden", "opacity-0", "scale-75");
      clearBtn.classList.remove("opacity-100", "scale-100");
      clearBtn.style.display = "none";
      if (kbdBadge) {
        kbdBadge.classList.remove("hidden");
        kbdBadge.style.display = "";
      }
    }
  }
  function showError(resultsContainer, noResults) {
    if (!resultsContainer || !noResults) return;
    resultsContainer.classList.remove("hidden");
    resultsContainer.style.display = "flex";
    noResults.innerHTML = `
        <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-rose-50 flex items-center justify-center text-rose-500">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
        </div>
        <p class="text-sm font-semibold text-slate-700 mb-1">\u062E\u0637\u0627\u06CC\u06CC \u062F\u0631 \u062F\u0631\u06CC\u0627\u0641\u062A \u0627\u0637\u0644\u0627\u0639\u0627\u062A \u0631\u062E \u062F\u0627\u062F</p>
        <p class="text-xs text-slate-400">\u0644\u0637\u0641\u0627\u064B \u0627\u062A\u0635\u0627\u0644 \u0627\u06CC\u0646\u062A\u0631\u0646\u062A \u062E\u0648\u062F \u0631\u0627 \u0628\u0631\u0631\u0633\u06CC \u06A9\u0631\u062F\u0647 \u0648 \u0645\u062C\u062F\u062F\u0627\u064B \u062A\u0644\u0627\u0634 \u06A9\u0646\u06CC\u062F.</p>
    `;
    noResults.classList.remove("hidden");
    const cats = resultsContainer.querySelector('[id$="CategoriesWrapper"]');
    const posts = resultsContainer.querySelector('[id$="PostsWrapper"]');
    if (cats) {
      cats.classList.add("hidden");
      cats.style.display = "none";
    }
    if (posts) {
      posts.classList.add("hidden");
      posts.style.display = "none";
    }
  }
  function renderSearchResults(data, resultsContainer, categoriesWrapper, categoriesContainer, postsWrapper, postsContainer, noResults, currentTerm) {
    const posts = sortPostsByType(data.posts || []);
    const categories = data.categories || [];
    resultsContainer.classList.remove("hidden");
    resultsContainer.style.display = "flex";
    resultsContainer.classList.add("overflow-y-hidden-imp");
    setTimeout(() => {
      resultsContainer.classList.remove("overflow-y-hidden-imp");
    }, 600);
    const existingViewAll = resultsContainer.parentElement ? resultsContainer.parentElement.querySelector("#alireza-view-all-link") : null;
    if (existingViewAll) existingViewAll.remove();
    if (posts.length === 0 && categories.length === 0) {
      if (categoriesWrapper) {
        categoriesWrapper.classList.add("hidden");
        categoriesWrapper.style.display = "none";
      }
      if (postsWrapper) {
        postsWrapper.classList.add("hidden");
        postsWrapper.style.display = "none";
      }
      noResults.classList.remove("hidden");
      return;
    }
    noResults.classList.add("hidden");
    if (categories.length > 0) {
      if (categoriesContainer) categoriesContainer.innerHTML = renderCategories(categories);
      if (categoriesWrapper) {
        categoriesWrapper.classList.remove("hidden");
        categoriesWrapper.style.display = "block";
      }
    } else {
      if (categoriesContainer) categoriesContainer.innerHTML = "";
      if (categoriesWrapper) {
        categoriesWrapper.classList.add("hidden");
        categoriesWrapper.style.display = "none";
      }
    }
    if (posts.length > 0) {
      let postHtml = "";
      posts.forEach((item, index) => {
        postHtml += createResultHTML(item, index + categories.length);
      });
      if (postsContainer) postsContainer.innerHTML = postHtml;
      if (postsWrapper) {
        postsWrapper.classList.remove("hidden");
        postsWrapper.style.display = "block";
      }
    } else {
      if (postsContainer) postsContainer.innerHTML = "";
      if (postsWrapper) {
        postsWrapper.classList.add("hidden");
        postsWrapper.style.display = "none";
      }
    }
    if (currentTerm) {
      resultsContainer.insertAdjacentHTML("afterend", renderViewAllLink(currentTerm));
    }
  }

  // assets/js/src/shortcuts.js
  function setupKeyboardShortcuts(onOpen) {
    document.addEventListener("keydown", (e) => {
      const isKKey = e.code === "KeyK" || e.key.toLowerCase() === "k" || e.key === "\u0646";
      if ((e.ctrlKey || e.metaKey) && isKKey) {
        e.preventDefault();
        e.stopPropagation();
        if (typeof onOpen === "function") {
          onOpen();
        }
        return;
      }
      const isSlash = e.code === "Slash" || e.key === "/";
      if (isSlash && !isTypingElsewhere()) {
        e.preventDefault();
        e.stopPropagation();
        if (typeof onOpen === "function") {
          onOpen();
        }
      }
    });
  }

  // assets/js/src/index.js
  function initSearchApp() {
    const desktopInput = document.getElementById("desktopSearchInput");
    const appWrapper = document.getElementById("alireza-ajax-search-app") || document.getElementById("hamseda-ajax-search-app") || desktopInput?.closest(".font-yekan");
    if (!desktopInput && !appWrapper) return;
    const desktopClearBtn = document.getElementById("desktopClearBtn");
    const desktopDropdown = document.getElementById("desktopDropdown");
    const desktopLoader = document.getElementById("desktopLoader");
    const desktopResults = document.getElementById("desktopResults");
    const mobileTrigger = document.getElementById("mobileSearchTrigger");
    const mobileModal = document.getElementById("mobileModal");
    const mobileCloseBtn = document.getElementById("mobileCloseBtn");
    const mobileInput = document.getElementById("mobileSearchInput");
    const mobileClearBtn = document.getElementById("mobileClearBtn");
    const mobileLoader = document.getElementById("mobileLoader");
    const mobileResults = document.getElementById("mobileResults");
    async function performSearch(term, loader, resultsContainer, isDesktop) {
      if (!resultsContainer || !loader) return;
      const categoriesWrapper = resultsContainer.querySelector('[id$="CategoriesWrapper"]');
      const categoriesContainer = resultsContainer.querySelector('[id$="Categories"]');
      const postsWrapper = resultsContainer.querySelector('[id$="PostsWrapper"]');
      const postsContainer = resultsContainer.querySelector('[id$="Posts"]');
      const noResults = resultsContainer.querySelector('[id$="NoResults"]');
      if (term.length === 0) {
        resultsContainer.classList.add("hidden");
        resultsContainer.style.display = "none";
        if (categoriesContainer) categoriesContainer.innerHTML = "";
        if (postsContainer) postsContainer.innerHTML = "";
        if (isDesktop && desktopDropdown) {
          desktopDropdown.classList.add("hidden");
          desktopDropdown.style.display = "none";
        }
        return;
      }
      loader.classList.remove("hidden");
      loader.style.display = "flex";
      resultsContainer.classList.add("hidden");
      resultsContainer.style.display = "none";
      if (categoriesContainer) categoriesContainer.innerHTML = "";
      if (postsContainer) postsContainer.innerHTML = "";
      if (categoriesWrapper) {
        categoriesWrapper.classList.add("hidden");
        categoriesWrapper.style.display = "none";
      }
      if (postsWrapper) {
        postsWrapper.classList.add("hidden");
        postsWrapper.style.display = "none";
      }
      if (isDesktop && desktopDropdown) {
        desktopDropdown.classList.remove("hidden");
        desktopDropdown.style.display = "block";
      }
      const result = await fetchSearchResults(term, isDesktop ? "desktop" : "mobile");
      if (result.aborted) {
        return;
      }
      loader.classList.add("hidden");
      loader.style.display = "none";
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
    if (desktopInput && desktopDropdown && desktopLoader && desktopResults) {
      const debouncedDesktopSearch = debounce((term) => performSearch(term, desktopLoader, desktopResults, true), 150);
      desktopInput.addEventListener("input", () => {
        toggleClear(desktopInput, desktopClearBtn);
        const val = desktopInput.value.trim();
        if (val.length >= 2) {
          desktopResults.classList.add("hidden");
          desktopResults.style.display = "none";
          const catsContainer = desktopResults.querySelector("#desktopCategories");
          const postsContainer = desktopResults.querySelector("#desktopPosts");
          const catsWrapper = desktopResults.querySelector("#desktopCategoriesWrapper");
          const postsWrapper = desktopResults.querySelector("#desktopPostsWrapper");
          if (catsContainer) catsContainer.innerHTML = "";
          if (postsContainer) postsContainer.innerHTML = "";
          if (catsWrapper) {
            catsWrapper.classList.add("hidden");
            catsWrapper.style.display = "none";
          }
          if (postsWrapper) {
            postsWrapper.classList.add("hidden");
            postsWrapper.style.display = "none";
          }
          desktopLoader.classList.remove("hidden");
          desktopLoader.style.display = "flex";
          desktopDropdown.classList.remove("hidden");
          desktopDropdown.style.display = "block";
          const old = desktopDropdown.querySelector("#alireza-view-all-link") || desktopDropdown.querySelector("#hamseda-view-all-link");
          if (old) old.remove();
          debouncedDesktopSearch(val);
        } else {
          desktopResults.classList.add("hidden");
          desktopResults.style.display = "none";
          desktopDropdown.classList.add("hidden");
          desktopDropdown.style.display = "none";
          const old = desktopDropdown.querySelector("#alireza-view-all-link") || desktopDropdown.querySelector("#hamseda-view-all-link");
          if (old) old.remove();
        }
      });
      if (desktopClearBtn) {
        desktopClearBtn.addEventListener("click", () => {
          desktopInput.value = "";
          toggleClear(desktopInput, desktopClearBtn);
          desktopDropdown.classList.add("hidden");
          desktopDropdown.style.display = "none";
          desktopResults.classList.add("hidden");
          desktopResults.style.display = "none";
          const old = desktopDropdown.querySelector("#alireza-view-all-link") || desktopDropdown.querySelector("#hamseda-view-all-link");
          if (old) old.remove();
          desktopInput.focus();
        });
      }
      document.addEventListener("click", (e) => {
        if (!e.target.closest("#alireza-ajax-search-app") && !e.target.closest("#hamseda-ajax-search-app") && !e.target.closest(".alireza-search-inner")) {
          desktopDropdown.classList.add("hidden");
          desktopDropdown.style.display = "none";
        }
      });
      desktopInput.addEventListener("focus", () => {
        if (desktopInput.value.trim().length >= 2) {
          desktopDropdown.classList.remove("hidden");
          desktopDropdown.style.display = "block";
        }
      });
    }
    if (mobileTrigger && mobileModal) {
      mobileTrigger.addEventListener("click", () => {
        mobileModal.classList.remove("hidden");
        mobileModal.style.display = "block";
        document.body.classList.add("modal-open");
        if (mobileInput) {
          setTimeout(() => mobileInput.focus(), 100);
        }
      });
      if (mobileCloseBtn) {
        mobileCloseBtn.addEventListener("click", () => {
          mobileModal.classList.add("hidden");
          mobileModal.style.display = "none";
          document.body.classList.remove("modal-open");
        });
      }
      if (mobileInput && mobileLoader && mobileResults) {
        const debouncedMobileSearch = debounce((term) => performSearch(term, mobileLoader, mobileResults, false), 150);
        mobileInput.addEventListener("input", () => {
          toggleClear(mobileInput, mobileClearBtn);
          const val = mobileInput.value.trim();
          if (val.length >= 2) {
            debouncedMobileSearch(val);
          } else {
            mobileResults.classList.add("hidden");
            mobileResults.style.display = "none";
          }
        });
        if (mobileClearBtn) {
          mobileClearBtn.addEventListener("click", () => {
            mobileInput.value = "";
            toggleClear(mobileInput, mobileClearBtn);
            mobileResults.classList.add("hidden");
            mobileResults.style.display = "none";
            mobileInput.focus();
          });
        }
      }
    }
    setupKeyboardShortcuts(() => {
      if (desktopInput) {
        desktopInput.focus();
        desktopInput.select();
      } else if (mobileTrigger) {
        mobileTrigger.click();
      }
    });
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initSearchApp);
  } else {
    initSearchApp();
  }
  window.addEventListener("load", initSearchApp);
})();
//# sourceMappingURL=alireza-search.js.map
