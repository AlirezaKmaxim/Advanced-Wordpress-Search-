/**
 * Configuration for badges, icons, and post type priorities.
 *
 * @package Alireza_Ajax_Search
 */

export const badgeConfig = {
    esanj: {
        text: 'تست روانسنجی',
        classes: 'bg-[#FFB3C1] text-white',
        svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>'
    },
    post: {
        text: 'مقاله',
        classes: 'bg-[#7BA4F5] text-white',
        svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>'
    },
    page: {
        text: 'صفحه',
        classes: 'bg-[#3A3A4A] text-white',
        svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>'
    },
    product: {
        text: 'محصول',
        classes: 'bg-[#FCE16D] text-[#3A3A4A]',
        svg: '<use href="#icon-cart"></use>'
    },
    product_cat: {
        text: 'دسته‌بندی',
        classes: 'bg-[#FA7993] text-white',
        svg: '<use href="#icon-folder"></use>'
    },
    default: {
        text: 'دیگر',
        classes: 'bg-[#EFEFF3] text-[#3A3A4A]',
        svg: ''
    }
};

export const postTypePriority = {
    esanj: 0,
    post: 1,
    page: 2,
    product: 3
};
