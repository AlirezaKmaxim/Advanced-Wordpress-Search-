/**
 * Keyboard shortcuts handler for Alireza Smart Search.
 * Supports multilingual layouts (Persian, English, etc.).
 *
 * @package Alireza_Ajax_Search
 */

import { isTypingElsewhere } from './utils.js';

/**
 * Setup keyboard shortcuts (Ctrl+K, Cmd+K, /).
 *
 * @param {Function} onOpen
 */
export function setupKeyboardShortcuts(onOpen) {
    document.addEventListener('keydown', (e) => {
        // Multi-layout Ctrl+K / Cmd+K check (supports English 'k', Persian 'ن', and physical keycode KeyK)
        const isKKey = e.code === 'KeyK' || e.key.toLowerCase() === 'k' || e.key === 'ن';
        
        if ((e.ctrlKey || e.metaKey) && isKKey) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof onOpen === 'function') {
                onOpen();
            }
            return;
        }

        // "/" shortcut when not typing in another input field
        const isSlash = e.code === 'Slash' || e.key === '/';
        if (isSlash && !isTypingElsewhere()) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof onOpen === 'function') {
                onOpen();
            }
        }
    });
}
