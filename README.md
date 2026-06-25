# HamSeda Ajax Search

A premium, high-performance, fuzzy AJAX search plugin for WordPress with full Persian (Farsi) language support. Provides real-time searching across multiple post types and taxonomies with an interactive dual-interface UI — inline desktop dropdown + full-screen mobile modal.

## Features

- **Fuzzy Persian Search** — Intelligent wildcard matching for common psychiatric/medical misspellings (اضطراب, وسواس, افسردگی, تمرکز, حافظه, هیپنوتیزم) and dynamic `د`/`ذ` replacement
- **Compound Word Splitting** — Automatically splits compound Persian words into prefix + `%` + suffix for broader matching (e.g. روانشناس → روان%شناس)
- **Persian Text Normalization** — Automatic conversion of Arabic `ي`/`ك` to Persian `ی`/`ک`
- **Multi Post-Type Search** — Searches across custom post types, posts, pages, and WooCommerce products
- **WooCommerce Integration** — Searches product SKU, attributes (`pa_%`), brands (`product_brand`, `pwb-brand`, `yith_product_brand`, `brand`), categories, and tags via custom SQL JOIN
- **Post Type Priority Sorting** — Results sorted by priority: `esanj` > `post` > `page` > `product` > others
- **Product Category Search** — Searches enabled taxonomies with 12-hour transient caching and 6-level relevance scoring (exact → starts with → contains → per-word starts with → per-word contains)
- **Dual Responsive UI** — Inline desktop dropdown + full-screen mobile modal with slide transition
- **AJAX with AbortController** — Cancels stale requests to prevent race conditions
- **Rate-Limited AJAX** — 30 requests per 60 seconds per IP to prevent abuse
- **Debounced Input** — 400ms delay before firing search
- **Keyboard Shortcuts** — `Ctrl+K` / `Cmd+K` and `/` to open search (skips when typing in inputs)
- **View All Results Link** — Footer link to standard WordPress search results page
- **Animated Results** — Staggered fade-in animations and animated loading dots
- **Nonce-Protected AJAX** — Secure search endpoints
- **WooCommerce Product Display** — Regular/sale prices with thousands separators, stock status badges, and sale highlighting
- **Admin Settings Panel** — Enable/disable post types, taxonomies, custom result labels, section headers, search placeholder
- **Auto Cache Invalidation** — Taxonomy search transients automatically purged on term save/delete
- **RTL-First Design** — Built for Persian language
- **Tailwind CSS with Scoped Isolation** — All styles scoped under `#hamseda-ajax-search-app` and `#mobileModal`

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
| Search Placeholder | Custom placeholder text for the search input |

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
│   ├── class-search-query.php       # Fuzzy WP_Query + compound word splitting +
│   │                                 #   WooCommerce SKU/attribute/brand JOIN +
│   │                                 #   taxonomy search with relevance scoring
│   ├── class-ajax-handler.php       # AJAX endpoint + rate limiting
│   ├── class-shortcode.php          # [hamseda_ajax_search] shortcode
│   └── hamseda-icons.php            # Inline SVG icon spritesheet
├── templates/
│   └── search-template.php          # Search UI HTML (desktop + mobile)
├── assets/
│   ├── css/
│   │   ├── hamseda-search.css       # Minified Tailwind CSS output
│   │   └── src/input.css            # Tailwind source + custom animations/scrollbar/isolation
│   └── js/hamseda-search.js         # Frontend JS: events, AJAX, rendering, keyboard shortcuts
├── tailwind.config.js
└── package.json
```

## Data Flow

1. User types in the search input
2. Frontend JS debounces input (400ms), then POSTs to `admin-ajax.php` with action `hamseda_global_search`
3. `HamSeda_AJAX_Handler::handle_search()` verifies nonce, checks rate limit (30 req/60s per IP), and sanitizes input
4. `HamSeda_Search_Query::execute()` runs a fuzzy WP_Query across configured post types (from admin settings) with custom `posts_search`, `posts_join`, and `posts_distinct` filters for WooCommerce SKU/attribute/brand matching
5. Results are sorted by post type priority: `esanj` → `post` → `page` → `product` → others
6. `HamSeda_Search_Query::search_product_categories()` searches enabled taxonomies with 3-phase fallback (full term → space-stripped → per-word), 6-level relevance scoring, and transient caching
7. JSON response is returned with posts and categories
8. Frontend JS renders results with animated entry, type badges, and "View all results" link

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
        "badge_color": "#FCE16D",
        "regular_price": "100000",
        "sale_price": "80000",
        "stock_status": "instock"
      }
    ]
  }
}
```

## Fuzzy Search Details

### Psychiatric Term Wildcards
Common misspellings of psychiatric terms are mapped to SQL `_` wildcard patterns, e.g. اضطراب/اظطراب/ازطراب → `ا_طراب`, وسواس/وصواص → `و_وا_`.

### Dynamic Character Replacement
Persian letters `د` and `ذ` are interchangeable in common misspellings; both are replaced with the SQL `_` wildcard.

### Compound Word Splitting
Known Persian prefixes (روان, خود, خوش, کتاب, پیش, هم, بی, etc.) and suffixes (نویس, شناسی, سنجی, کننده, یابی, سازی, گزاری, etc.) are detected. If a compound word is written together, a `%` wildcard is inserted between the prefix and the remainder (e.g. روانشناس → `روان%شناس`).

### Taxonomy Relevance Scoring (6 levels)
| Score | Match Type |
|-------|-----------|
| 5 | Exact match |
| 4 | Term starts with full query |
| 3 | Term contains full query |
| 2 | Term starts with any query word |
| 1 | Term contains any query word |
| 0 | No match (filtered out) |

## Changelog

### 2.1.0
- Compound word splitting for Persian prefixes/suffixes
- WooCommerce SKU, attribute, and brand search via custom SQL JOIN/DISTINCT
- Dynamic `د`/`ذ` fuzzy replacement
- Taxonomy `terms_clauses` filter for fuzzy category search
- 6-level relevance scoring with scored sorting
- Space-stripped compound form fallback for category search
- Post type priority sorting (`esanj` > `post` > `page` > `product`)
- View all results footer link
- Custom scrollbar, mobile modal body scroll lock, style isolation rules
- Search placeholder and enable search toggle in admin settings
- Keyboard shortcut `/` detection (skips when typing in inputs)

### 1.0.0
- Initial release
- Fuzzy Persian search with wildcard matching
- Dual responsive UI (desktop dropdown + mobile modal)
- WooCommerce product support with price/stock display
- Admin settings panel with dynamic post type discovery
- Tailwind CSS styling with component isolation
- Rate-limited AJAX, nonce protection, AbortController

## License

GPLv2 or later
