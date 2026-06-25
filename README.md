# HamSeda Ajax Search

A premium, high-performance, fuzzy AJAX search plugin for WordPress with full Persian (Farsi) language support. Provides real-time searching across multiple post types with an interactive dual-interface UI — inline desktop dropdown + full-screen mobile modal.

## Features

- **Fuzzy Persian Search** — Intelligent wildcard matching for common psychiatric/medical misspellings (اضطراب, وسواس, افسردگی, تمرکز, حافظه, هیپنوتیزم)
- **Persian Text Normalization** — Automatic conversion of Arabic `ي`/`ك` to Persian `ی`/`ک`
- **Multi Post-Type Search** — Searches across custom post types, posts, pages, and WooCommerce products
- **Product Category Search** — Searches WooCommerce product categories with 12-hour transient caching
- **Dual Responsive UI** — Inline desktop dropdown + full-screen mobile modal with slide transition
- **AJAX with AbortController** — Cancels stale requests to prevent race conditions
- **Rate-Limited AJAX** — 30 requests per 60 seconds per IP to prevent abuse
- **Debounced Input** — 400ms delay before firing search
- **Keyboard Shortcuts** — `Ctrl+K` / `Cmd+K` and `/` to open search
- **Animated Results** — Staggered fade-in animations and animated loading dots
- **Nonce-Protected AJAX** — Secure search endpoints
- **WooCommerce Integration** — Displays prices, sale badges, and stock status for products
- **Admin Settings Panel** — Enable/disable post types, taxonomies, and customize result labels
- **RTL-First Design** — Built for Persian language
- **Tailwind CSS with Scoped Isolation** — All styles scoped under `#hamseda-ajax-search-app`

## Requirements

- WordPress 5.0+
- PHP 7.4+
- WooCommerce (optional, for product support)

## Installation

1. Download the plugin ZIP and extract it, or clone this repository into `/wp-content/plugins/hamseda-ajax-search/`
2. Activate the plugin from the WordPress **Plugins** admin page
3. Go to **Settings → جستجوی هوشمند همصدا** to configure search options

## Usage

Insert the search widget anywhere on your site using the shortcode:

```
[hamseda_ajax_search]
```

The shortcode renders a fully responsive search interface with:
- **Desktop:** Inline search bar with a dropdown results panel
- **Mobile:** A trigger button that opens a full-screen modal

## Configuration

Navigate to **Settings → جستجوی هوشمند همصدا** (Settings → HamSeda Smart Search) in the WordPress admin.

| Setting | Description |
|---------|-------------|
| Enable Search | Master toggle for the AJAX search system |
| Active Post Types | Choose which post types appear in results (esanj, post, page, product, etc.) |
| Custom Post Type Labels | Override display labels for each post type |
| Active Taxonomies | Choose which taxonomies appear in category results |
| Results Section Titles | Customize the "Products & Posts" and "Related Categories" headings |

## Build Pipeline

This plugin uses Tailwind CSS for styling. To modify styles:

```bash
npm install
npm run build    # Compile & minify CSS
npm run watch    # Watch for changes
```

CSS source: `assets/css/src/input.css` → Output: `assets/css/hamseda-search.css`

## Architecture

```
hamseda-ajax-search/
├── hamseda-ajax-search.php          # Bootstrap & Singleton core
├── uninstall.php                    # Cleanup on plugin deletion
├── includes/
│   ├── class-admin-settings.php     # Settings page (post types, taxonomies, labels)
│   ├── class-asset-manager.php      # CSS/JS registration & enqueuing
│   ├── class-search-query.php       # Fuzzy WP_Query + taxonomy search
│   ├── class-ajax-handler.php       # AJAX endpoint handler
│   ├── class-shortcode.php          # [hamseda_ajax_search] shortcode
│   └── hamseda-icons.php            # Inline SVG icon spritesheet
├── templates/
│   └── search-template.php          # Search UI HTML (desktop + mobile)
├── assets/
│   ├── css/hamseda-search.css       # Minified Tailwind CSS
│   ├── css/src/input.css            # Tailwind source + custom animations
│   └── js/hamseda-search.js         # Frontend JS: events, AJAX, rendering
├── tailwind.config.js
└── package.json
```

## Data Flow

1. User types in the search input
2. Frontend JS debounces input (400ms), then POSTs to `admin-ajax.php` with action `hamseda_global_search`
3. `HamSeda_AJAX_Handler::handle_search()` verifies nonce, checks rate limit (30 req/60s per IP), and sanitizes input
4. `HamSeda_Search_Query::execute()` runs a fuzzy WP_Query across configured post types (from admin settings)
5. `HamSeda_Search_Query::search_product_categories()` searches categories with transient caching and relevance scoring
6. JSON response is returned with posts and categories
7. Frontend JS renders results with animated entry

## AJAX Response Format

```json
{
  "success": true,
  "data": {
    "categories": [
      { "term_id": 1, "name": "Category", "url": "https://...", "count": 5 }
    ],
    "posts": [
      {
        "id": 123,
        "title": "Post Title",
        "permalink": "https://...",
        "image_url": "https://...",
        "post_type": "product",
        "post_type_label": "محصول",
        "badge_color": "#F59E0B",
        "regular_price": "100000",
        "sale_price": "80000",
        "stock_status": "instock"
      }
    ]
  }
}
```

## Changelog

### 1.0.0
- Initial release
- Fuzzy Persian search with wildcard matching
- Dual responsive UI (desktop dropdown + mobile modal)
- WooCommerce product support with price/stock display
- Admin settings panel with dynamic post type discovery
- Tailwind CSS styling with component isolation

## License

GPLv2 or later
