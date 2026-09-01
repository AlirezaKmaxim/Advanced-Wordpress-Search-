<?php
/**
 * Search template file — Alireza Smart Search UI/UX PRO MAX Edition.
 *
 * @package Alireza_Ajax_Search
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- Alireza Ajax Search Wrapper -->
<div id="alireza-ajax-search-app" class="font-yekan" dir="rtl">
    <script>
    window.alirezaSearchSettings = window.alirezaSearchSettings || {
        ajax_url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
        nonce: '<?php echo esc_js( wp_create_nonce( 'alireza_search_nonce' ) ); ?>',
        search_url: '<?php echo esc_js( home_url( '/' ) ); ?>'
    };
    window.hamsedaSearchSettings = window.alirezaSearchSettings;
    </script>
    <?php 
    if ( class_exists( 'Alireza_Icons' ) ) {
        Alireza_Icons::render_spritesheet(); 
    }
    $options = alireza_search()->get_settings();
    $results_header_posts      = ! empty( $options['results_header_posts'] ) ? $options['results_header_posts'] : 'محصولات و مطالب';
    $results_header_taxonomies = ! empty( $options['results_header_taxonomies'] ) ? $options['results_header_taxonomies'] : 'دسته‌بندی‌های مرتبط';
    $search_placeholder        = ! empty( $options['search_placeholder'] ) ? $options['search_placeholder'] : 'جستجوی هوشمند محصول، مقاله...';
    ?>
    
    <!-- ========================================== -->
    <!-- MODERN FLOATING SEARCH INPUT CONTAINER     -->
    <!-- ========================================== -->
    <div class="alireza-search-inner flex justify-center items-center w-full h-full mx-auto relative">
        <div class="relative w-full h-full">
            <div class="alireza-input-wrapper relative flex items-center w-full h-full">
                <!-- Search Input Field -->
                <input 
                    id="desktopSearchInput"
                    type="text" 
                    placeholder="<?php echo esc_attr( $search_placeholder ); ?>" 
                    class="alireza-main-input !w-full !h-full !outline-none !text-base md:!text-lg !pr-11 !pl-11 !shadow-none !transition-all !duration-200"
                    autocomplete="off"
                    spellcheck="false"
                >
                
                <!-- Search Icon (Right-aligned in RTL) -->
                <div id="desktopSearchIcon" class="alireza-icon-search absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none z-10 flex items-center justify-center transition-transform duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </div>

                <!-- Left Controls: Keyboard Shortcut Badge & Clear Button -->
                <div class="alireza-controls-left absolute left-3 top-1/2 -translate-y-1/2 z-10 flex items-center">
                    <!-- Clear Button -->
                    <button 
                        id="desktopClearBtn"
                        class="alireza-clear-btn w-7 h-7 rounded-full flex items-center justify-center transition-all duration-200 opacity-0 scale-75 hidden hover:bg-slate-100 active:scale-90 !border-none !outline-none !p-0 !bg-transparent cursor-pointer"
                        type="button"
                        aria-label="<?php esc_attr_e( 'پاک کردن', 'alireza-ajax-search' ); ?>"
                    >
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>

                    <!-- Keyboard Shortcut Badge -->
                    <span id="alirezaKbdBadge" class="alireza-kbd-badge hidden sm:inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md border select-none pointer-events-none transition-opacity duration-150">
                        Ctrl K
                    </span>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- FLOATING GLASSMORPHIC DROPDOWN RESULTS     -->
            <!-- ========================================== -->
            <div id="desktopDropdown" class="alireza-dropdown absolute top-[calc(100%+12px)] right-0 left-0 hidden z-50 overflow-hidden">
                <!-- Preloader with Fluid Modern Dots -->
                <div id="desktopLoader" class="alireza-loader flex justify-center items-center py-10 hidden">
                    <div class="loader-dot w-2.5 h-2.5 rounded-full mx-1"></div>
                    <div class="loader-dot w-2.5 h-2.5 rounded-full mx-1"></div>
                    <div class="loader-dot w-2.5 h-2.5 rounded-full mx-1"></div>
                </div>

                <!-- Results List Container -->
                <div id="desktopResults" class="alireza-results-container flex-col gap-4 max-h-[440px] overflow-y-auto overflow-x-hidden custom-scroll p-4 md:p-5 hidden">
                    <!-- Categories Wrapper -->
                    <div id="desktopCategoriesWrapper" class="alireza-section-taxonomies hidden">
                        <div class="flex items-center gap-2 px-1 py-1 text-xs font-bold uppercase tracking-wider mb-2.5 text-slate-500">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                            </svg>
                            <span><?php echo esc_html( $results_header_taxonomies ); ?></span>
                        </div>
                        <div id="desktopCategories" class="flex flex-wrap gap-2"></div>
                    </div>

                    <!-- Posts & Products Wrapper -->
                    <div id="desktopPostsWrapper" class="alireza-section-posts hidden">
                        <div class="flex items-center gap-2 px-1 py-1 text-xs font-bold uppercase tracking-wider mb-2.5 mt-2 text-slate-500">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                            </svg>
                            <span><?php echo esc_html( $results_header_posts ); ?></span>
                        </div>
                        <div id="desktopPosts" class="flex flex-col gap-2.5"></div>
                    </div>

                    <!-- Empty / No Results Message -->
                    <div id="desktopNoResults" class="alireza-no-results hidden py-8 px-4 text-center">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-slate-50 flex items-center justify-center text-slate-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"></path>
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-slate-700 mb-1">نتیجه‌ای یافت نشد</p>
                        <p class="text-xs text-slate-400">لطفاً عبارت دیگری را امتحان کنید یا املای کلمه را بررسی نمایید.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
