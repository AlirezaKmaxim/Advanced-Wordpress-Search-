<?php
/**
 * Search template file.
 *
 * @package HamSeda_Ajax_Search
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- Search Wrapper -->
<div id="hamseda-ajax-search-app" class="font-yekan w-full" dir="rtl">
    <?php 
    if ( class_exists( 'HamSeda_Icons' ) ) HamSeda_Icons::render_spritesheet(); 
    $options = get_option( 'hamseda_search_settings', array() );
    $results_header_posts = isset( $options['results_header_posts'] ) && ! empty( $options['results_header_posts'] ) ? $options['results_header_posts'] : 'محصولات و مطالب';
    $results_header_taxonomies = isset( $options['results_header_taxonomies'] ) && ! empty( $options['results_header_taxonomies'] ) ? $options['results_header_taxonomies'] : 'دسته‌بندی‌های مرتبط';
    $search_placeholder = isset( $options['search_placeholder'] ) && ! empty( $options['search_placeholder'] ) ? $options['search_placeholder'] : 'جستجوی هوشمند...';
    ?>
    <!-- ========================================== -->
    <!-- 1. DESKTOP SEARCH (Hidden on Mobile)       -->
    <!-- ========================================== -->
    <div class="hidden md:flex justify-center items-center w-full mx-auto relative">
        <div class="relative w-full">
            <input 
                id="desktopSearchInput"
                type="text" 
                placeholder="<?php echo esc_attr( $search_placeholder ); ?>" 
                class="!w-full !h-16 !bg-white !border !border-gray-300 !rounded-full !outline-none transition-all duration-300 focus:!border-black !text-[#3A3A4A] placeholder:!text-gray-400 !pr-4 !pl-24 !text-lg !shadow-none focus:!ring-0"
                autocomplete="off"
            >
            <!-- Search Icon (Left-aligned, side by side with clear button) -->
            <div id="desktopSearchIcon" class="absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none text-black z-10">
                <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <!-- Clear Button (Left-aligned) -->
            <button 
                id="desktopClearBtn"
                class="absolute left-12 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full flex items-center justify-center transition-all duration-200 opacity-0 scale-75 invisible text-[#707085]/30 hover:text-[#707085]/80 hover:bg-[#FFB3C1] z-10 !border-none !outline-none hover:!shadow-none !shadow-none !bg-transparent !p-0"
                type="button"
            >
                <svg class="w-8 h-8" viewBox="18 23 43 30" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" baseProfile="full" enable-background="new 0 0 76.00 76.00" xml:space="preserve" fill="currentColor">
                    <path fill="currentColor" fill-opacity="1" stroke-width="0.2" stroke-linejoin="round" d="M 57.9853,41.5355L 49.0354,50.4854C 47.9317,51.589 47,52 45,52L 24,52C 21.2386,52 19,49.7614 19,47L 19,29C 19,26.2386 21.2386,24 24,24L 45,24C 47,24 47.9317,24.4113 49.0354,25.5149L 57.9853,34.4645C 59.9379,36.4171 59.9379,39.5829 57.9853,41.5355 Z M 28.4719,42.9497L 31.0503,45.5281L 36,40.5784L 40.9498,45.5281L 43.5282,42.9497L 38.5785,37.9999L 43.5282,33.0502L 40.9498,30.4718L 36,35.4215L 31.0503,30.4718L 28.4719,33.0502L 33.4216,37.9999L 28.4719,42.9497 Z "/>
                </svg>
            </button>

            <!-- Dropdown Container -->
            <div id="desktopDropdown" class="absolute top-20 right-0 left-0 bg-white rounded-3xl shadow-2xl p-6 hidden z-50 border border-[#E2E2E2]">
                <!-- Preloader -->
                <div id="desktopLoader" class="flex justify-center items-center py-10 hidden">
                    <div class="loader-dot w-3 h-3 rounded-full bg-[#FA7993] mx-1.5"></div>
                    <div class="loader-dot w-3 h-3 rounded-full bg-[#FFB3C1] mx-1.5"></div>
                    <div class="loader-dot w-3 h-3 rounded-full bg-[#FCE16D] mx-1.5"></div>
                </div>

                <!-- Results List -->
                <div id="desktopResults" class="flex-col gap-4 max-h-[400px] overflow-y-auto custom-scroll pr-1 hidden">
                    <div id="desktopCategoriesWrapper" class="hidden">
                        <div class="px-2 py-1 text-xs font-bold text-[#707085] mb-2"><?php echo esc_html( $results_header_taxonomies ); ?></div>
                        <div id="desktopCategories" class="flex flex-wrap gap-2"></div>
                    </div>
                    <div id="desktopPostsWrapper" class="hidden">
                        <div class="px-2 py-1 text-xs font-bold text-[#707085] mb-2 mt-2"><?php echo esc_html( $results_header_posts ); ?></div>
                        <div id="desktopPosts" class="flex flex-col gap-3"></div>
                    </div>
                    <div id="desktopNoResults" class="hidden p-4 text-center text-[#707085] text-sm">نتیجه‌ای یافت نشد.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 2. MOBILE SEARCH (Hidden on Desktop)       -->
    <!-- ========================================== -->
    <div class="md:hidden flex justify-center w-full mobile-search-container">
        <div id="mobileSearchTrigger" class="cursor-pointer flex items-center w-full h-12 bg-white border border-gray-300 rounded-full pr-2 pl-10 relative">
            <!-- Search Icon (Left-aligned) -->
            <div class="absolute left-2 top-1/2 -translate-y-1/2 text-black pointer-events-none">
                <svg class="w-5 h-5 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <span class="text-gray-400 text-sm"><?php echo esc_html( $search_placeholder ); ?></span>
        </div>
    </div>

    <!-- Mobile Modal -->
    <div id="mobileModal" class="fixed inset-0 bg-white z-[999] flex flex-col hidden transition-transform duration-300 transform translate-y-full">
        <!-- Modal Header -->
        <div class="p-4 border-b border-[#FCFAFA]">
            <div class="flex items-center gap-3">
                <button id="mobileCloseBtn" class="!min-w-[44px] !min-h-[44px] !w-11 !h-11 rounded-full bg-[#FCFAFA] flex items-center justify-center text-[#525266] hover:bg-[#FFB3C1] hover:text-[#3A3A4A] transition-all flex-shrink-0 !border-none !outline-none !shadow-none !p-0 shadow-[0_4px_14px_0_rgba(250,121,147,0.15)]" type="button">
                    <svg class="!w-8 !h-8" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"/>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"/>
                        <g id="SVGRepo_iconCarrier"> <rect width="48" height="48" fill="white" fill-opacity="0.01"/> <path d="M24 44C35.0457 44 44 35.0457 44 24C44 12.9543 35.0457 4 24 4C12.9543 4 4 12.9543 4 24C4 35.0457 12.9543 44 24 44Z" fill="#FA7993" stroke="#FA7993" stroke-width="4" stroke-linejoin="round"/> <path d="M29.6569 18.3431L18.3432 29.6568" stroke="white" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/> <path d="M18.3432 18.3431L29.6569 29.6568" stroke="white" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/> </g>
                    </svg>
                </button>
                <div class="relative flex-1">
                    <input 
                        id="mobileSearchInput"
                        type="text" 
                        placeholder="<?php echo esc_attr( $search_placeholder ); ?>" 
                        class="!w-full !h-12 !bg-white !border !border-gray-300 !rounded-full !outline-none transition-all duration-300 focus:!border-black !text-[#3A3A4A] placeholder:!text-gray-400 !pr-2 !pl-20 !text-base !shadow-none focus:!ring-0"
                        autocomplete="off"
                    >
                    <!-- Search Icon (Left-aligned, side by side with clear button) -->
                    <div id="mobileSearchIcon" class="absolute left-2 top-1/2 -translate-y-1/2 pointer-events-none text-black z-10">
                        <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <!-- Clear Button (Left-aligned) -->
                    <button 
                        id="mobileClearBtn"
                        class="absolute left-[38px] top-1/2 -translate-y-1/2 w-8 h-8 rounded-full flex items-center justify-center transition-all duration-200 opacity-0 scale-75 invisible text-[#707085]/30 hover:text-[#707085]/80 hover:bg-[#FFB3C1] !border-none !outline-none hover:!shadow-none !shadow-none !bg-transparent !p-0"
                        type="button"
                    >
                        <svg class="w-6 h-6" viewBox="18 23 43 30" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" baseProfile="full" enable-background="new 0 0 76.00 76.00" xml:space="preserve" fill="currentColor">
                            <path fill="currentColor" fill-opacity="1" stroke-width="0.2" stroke-linejoin="round" d="M 57.9853,41.5355L 49.0354,50.4854C 47.9317,51.589 47,52 45,52L 24,52C 21.2386,52 19,49.7614 19,47L 19,29C 19,26.2386 21.2386,24 24,24L 45,24C 47,24 47.9317,24.4113 49.0354,25.5149L 57.9853,34.4645C 59.9379,36.4171 59.9379,39.5829 57.9853,41.5355 Z M 28.4719,42.9497L 31.0503,45.5281L 36,40.5784L 40.9498,45.5281L 43.5282,42.9497L 38.5785,37.9999L 43.5282,33.0502L 40.9498,30.4718L 36,35.4215L 31.0503,30.4718L 28.4719,33.0502L 33.4216,37.9999L 28.4719,42.9497 Z "/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Body (Scrollable Results) -->
        <div class="flex-1 overflow-y-auto p-4 custom-scroll">
            <!-- Preloader -->
            <div id="mobileLoader" class="flex justify-center items-center py-20 hidden">
                <div class="loader-dot w-3 h-3 rounded-full bg-[#FA7993] mx-1.5"></div>
                <div class="loader-dot w-3 h-3 rounded-full bg-[#FFB3C1] mx-1.5"></div>
                <div class="loader-dot w-3 h-3 rounded-full bg-[#FCE16D] mx-1.5"></div>
            </div>

            <!-- Results List -->
            <div id="mobileResults" class="flex-col gap-4 hidden">
                <div id="mobileCategoriesWrapper" class="hidden">
                    <div class="px-2 py-1 text-xs font-bold text-[#707085] mb-2"><?php echo esc_html( $results_header_taxonomies ); ?></div>
                    <div id="mobileCategories" class="flex flex-wrap gap-2"></div>
                </div>
                <div id="mobilePostsWrapper" class="hidden">
                    <div class="px-2 py-1 text-xs font-bold text-[#707085] mb-2 mt-2"><?php echo esc_html( $results_header_posts ); ?></div>
                    <div id="mobilePosts" class="flex flex-col gap-3"></div>
                </div>
                <div id="mobileNoResults" class="hidden p-4 text-center text-[#707085] text-sm">نتیجه‌ای یافت نشد.</div>
            </div>
        </div>
    </div>
</div>
