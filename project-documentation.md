# HamSeda Ajax Search — Project Documentation

## Overview

**HamSeda Ajax Search** is a WordPress plugin that provides a **premium, high-performance, fuzzy AJAX search widget** with full Persian (Farsi) language support. It enables real-time searching across multiple post types (`esanj`, `post`, `page`, `product`) with an interactive dual-interface UI (desktop dropdown + mobile modal).

- **Version:** 2.0.8
- **Author:** Alireza KMaxim (HamSeda.com)
- **License:** GPL2
- **Text Domain:** `hamseda-ajax-search`

---

## Directory Structure

```
Advanced- Search/
│
├── hamseda-ajax-search.php          # Main plugin file (bootstrap, Singleton core)
├── uninstall.php                    # Cleanup on plugin deletion (options, transients)
├── package.json                     # Node.js config (Tailwind CSS build pipeline)
├── tailwind.config.js               # Tailwind CSS configuration
│
├── assets/
│   ├── css/
│   │   ├── hamseda-search.css       # Minified production CSS (Tailwind output)
│   │   ├── src/
│   │   │   └── input.css            # Tailwind source + custom animations/scrollbar
│   │   └── index.php                # Directory guard
│   └── js/
│       ├── hamseda-search.js        # Frontend JS: event handling, AJAX, rendering
│       └── index.php                # Directory guard
│
├── includes/
│   ├── class-admin-settings.php     # Admin settings page (post types, taxonomies, labels)
│   ├── class-asset-manager.php      # CSS/JS registration & enqueuing
│   ├── class-search-query.php       # WP_Query fuzzy search + category search
│   ├── class-ajax-handler.php       # AJAX endpoint handler
│   ├── class-shortcode.php          # [hamseda_ajax_search] shortcode
│   ├── hamseda-icons.php            # Inline SVG icon spritesheet
│   └── index.php                    # Directory guard
│
├── templates/
│   ├── search-template.php          # Search UI HTML (desktop + mobile)
│   └── index.php                    # Directory guard
│
└── node_modules/                    # Tailwind CSS dependencies
```

---

## File-by-File Breakdown

### 1. `hamseda-ajax-search.php` (Main Plugin Bootstrap)

**Role:** Entry point of the plugin. Defines constants, loads all includes, and instantiates the core singleton.

**Key Components:**
- `HamSeda_Search_Core` — Singleton class that holds references to all sub-modules (`$assets`, `$query`, `$ajax`, `$shortcode`, `$settings`).
- `define_constants()` — Defines `HAMSEDA_SEARCH_VERSION`, `HAMSEDA_SEARCH_FILE`, `HAMSEDA_SEARCH_PATH`, `HAMSEDA_SEARCH_URL`.
- `includes()` — Requires all files from `includes/` (including `class-admin-settings.php` conditionally when in admin context).
- `init_hooks()` — Hooks into `plugins_loaded`.
- `hamseda_search()` — Global helper function to access the singleton instance.
- `is_woocommerce_active()` — Checks if WooCommerce is active via `class_exists('WooCommerce')`.

**Impact on Export Data:** Provides the central access point (`hamseda_search()->query`, `hamseda_search()->assets`, etc.) used by all other modules.

---

### 2. `includes/class-asset-manager.php`

**Role:** Registers and enqueues the plugin's CSS and JavaScript files.

**Key Components:**
- `register_assets()` — Registers `hamseda-search-css` and `hamseda-search-js`. Localizes the script with `ajax_url` and `nonce` via `wp_localize_script`. Checks if the current post has the shortcode and auto-enqueues assets.
- `enqueue_assets()` — Manually enqueues the registered CSS and JS.

**Impact on Export Data:** Ensures the frontend has the required styles and scripts loaded. Passes `ajax_url` and `nonce` to JavaScript for AJAX security.

---

### 3. `includes/class-search-query.php`

**Role:** Core search engine — executes `WP_Query` with fuzzy Persian word matching and searches product categories.

**Key Components:**
- `execute($search_term)` — Normalizes Persian/Arabic characters, runs `WP_Query` with a custom `posts_search` filter for fuzzy matching.
- `normalize_persian_text()` — Converts Arabic `ي`/`ك` to Persian `ی`/`ک`.
- `custom_posts_search()` — Modifies the SQL `LIKE` clause to replace specific Persian words with wildcard versions (e.g., `اضطراب` → `ا_طراب` for fuzzy matching of common misspellings).
- `get_fuzzy_wildcard_word()` — Dictionary of fuzzy pairs for psychiatric terms (اضطراب, وسواس, افسردگی, تمرکز, حافظه, هیپنوتیزم).
- `search_product_categories()` — Searches `product_cat` taxonomy with transient caching (12-hour TTL). Features a 6-level relevance scoring system: exact match (5), starts with (4), contains (3), starts with word (2), contains word (1), no match (0). Falls back to space-stripped compound form and per-word matching for multi-word queries.
- **Cache invalidation:** Hooks `saved_term` and `deleted_term` actions to auto-purge taxonomy search transients when categories change.

**Impact on Export Data:** Produces the search results (posts and categories) that are encoded into JSON by the AJAX handler. All search refinement, normalization, and fuzzy logic happen here.

---

### 4. `includes/class-ajax-handler.php`

**Role:** Handles incoming AJAX search requests from the frontend.

**Key Components:**
- `__construct()` — Registers `wp_ajax_hamseda_global_search` and `wp_ajax_nopriv_hamseda_global_search` actions.
- **Rate limiting:** Constants `RATE_LIMIT_MAX = 30` requests per `RATE_LIMIT_WINDOW = 60` seconds, enforced per IP via transient tracking.
- `handle_search()` — Verifies nonce, checks rate limit, sanitizes the search term, calls `->query->execute()` and `->query->search_product_categories()`. Formats results into a structured JSON response with post metadata (title, permalink, image, badge color, `post_type_label` from settings, price, stock status for products).

**Export Data Structure:**
```json
{
  "success": true,
  "data": {
    "categories": [
      { "term_id": 1, "name": "...", "url": "...", "count": 5 }
    ],
    "posts": [
      {
        "id": 123,
        "title": "...",
        "permalink": "...",
        "image_url": "...",
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

---

### 5. `includes/class-shortcode.php`

**Role:** Registers the `[hamseda_ajax_search]` WordPress shortcode.

**Key Components:**
- `render_shortcode()` — Enqueues assets via `hamseda_search()->assets->enqueue_assets()`, then uses output buffering to include and return the search template HTML.

**Impact on Export Data:** This is the entry point for rendering the search UI on the frontend. Without this shortcode, the search interface would not appear.

---

### 6. `includes/hamseda-icons.php`

**Role:** Provides an inline SVG spritesheet for icons used in the UI.

**Key Components:**
- `HamSeda_Icons::render_spritesheet()` — Outputs a `<svg>` element with `<symbol>` definitions for `#icon-cart` (shopping cart) and `#icon-folder` (folder/tag). These are referenced in the template as `<use href="#icon-cart"/>`.

**Impact on Export Data:** Purely presentational — no impact on data export, but contributes to the visual rendering of search results.

---

### 7. `includes/class-admin-settings.php`

**Role:** Admin settings page for configuring search post types, taxonomies, custom labels, and section headers.

**Key Components:**
- `add_settings_page()` — Adds a submenu under **Settings → جستجوی هوشمند همصدا** via `add_options_page()`.
- `register_settings()` — Registers the `hamseda_search_settings` option array with a sanitization callback.
- `sanitize_settings()` — Sanitizes all inputs: boolean flags for post types/taxonomies, text fields for labels/headers.
- `render_settings_page()` — Three-card UI layout:
  - **Active Post Types** — Grid of checkboxes with custom label text inputs. Dynamically discovers all public, searchable post types (excludes internal types like `attachment`, `revision`, etc.). Default: `post`, `page`, `product`, `esanj`.
  - **Active Taxonomies** — Grid of taxonomy checkboxes with custom label inputs. Default: `product_cat`.
  - **Results Section Titles** — Customizable header text for "Products & Posts" and "Related Categories" result sections.
- `get_discoverable_post_types()` — Auto-discovers public post types not excluded from search, filtering out WordPress internals.
- `get_discoverable_taxonomies()` — Auto-discovers public taxonomies, filtering out internal ones.

**Impact on Export Data:** Controls which post types and taxonomies appear in search results, their display labels (`post_type_label` in JSON), and the section header text rendered in the template.

---

### 8. `uninstall.php`

**Role:** Cleans up all plugin data when the plugin is deleted from the WordPress Plugins screen.

**Key Components:**
- `_hamseda_delete_transients()` — Deletes all transients with the `hamseda_%` prefix via a direct SQL query on `{$wpdb->options}`.
- **Multisite support:** Iterates through all sites in the network (excluding spam/deleted/archived), switches to each blog, deletes options and transients, then restores.
- Cleans up the `hamseda_search_settings` option.

**Impact on Export Data:** Ensures no orphaned options or transients remain after plugin deletion.

---

### 9. `templates/search-template.php`

**Role:** Full HTML markup for the search widget.

**Structure:**
- **Desktop Search (hidden on mobile):** Inline input with a dropdown results panel containing a loader, categories grid, posts list, and a "no results" message.
- **Mobile Search (hidden on desktop):** A trigger button that opens a full-screen modal with an input field and scrollable results.
- **Shared:** SVG icons, animated loader dots, custom scrollbar styling, and RTL layout (`dir="rtl"`).

**Impact on Export Data:** The template receives data from JavaScript and renders it dynamically. It defines the DOM structure that JS manipulates to display search results.

---

### 10. `assets/js/hamseda-search.js`

**Role:** All frontend interactivity — event handling, AJAX calls, DOM rendering.

**Key Components:**
- **Debounced Search:** Waits 400ms after the user stops typing before firing a search.
- **AbortController:** Cancels the previous in-flight AJAX request when a new one starts (prevents race conditions).
- **`performSearch()`:** Constructs a `FormData` POST request to `admin-ajax.php` with `action=hamseda_global_search`, `term`, and `nonce`. Returns JSON.
- **`renderSearchResults()`:** Builds HTML for categories (with product count badges) and posts (with type badges, images, prices/stock status).
- **`createResultHTML()`:** Generates individual result item markup with animated entry delays.
- **Keyboard Shortcuts:** `Ctrl+K` / `Cmd+K` and `/` (when not focused on an input) open the search UI.
- **Desktop Events:** `input` on search field, `click` on clear button, `focus` to show dropdown, document click to hide.
- **Mobile Events:** `click` on trigger to open modal, `click` on close button to close with slide transition, body scroll lock via `modal-open` class.

**Impact on Export Data:** This is the consumer of the exported JSON data. It transforms the structured data into rendered HTML displayed to the user.

---

### 11. `assets/css/src/input.css`

**Role:** Tailwind CSS source file with custom component styles.

**Key Animations:**
- `fadeSlideIn` — Results fade in and slide up (0.3s ease-out).
- `pulseDot` — Loader dots pulse with staggered delays (1.2s loop).
- Custom thin scrollbar with brand colors (`#5977BF` thumb, `#EDF0F8` track).
- Body scroll lock (`body.modal-open { overflow: hidden }`).

**Impact on Export Data:** No direct data impact; purely visual.

---

### 12. `tailwind.config.js`

**Role:** Tailwind CSS configuration.

**Key Settings:**
- `important: '#hamseda-ajax-search-app'` — Scopes all Tailwind utilities under the app wrapper ID for isolation.
- Content paths: all `.php` and `.js` files (excluding `node_modules`).
- Custom colors: `primaryLight`, `secondaryBlue`, `deepNavy`, `darkText`, `lavender`.

---

### 13. `package.json`

**Role:** Node.js project configuration.

**Scripts:**
- `npm run build` — Compiles Tailwind from `input.css` to `hamseda-search.css` (minified).
- `npm run watch` — Watches for changes and recompiles.

**Dependency:** `tailwindcss ^3.4.3`
---

## Data Flow Diagram

```
User types in search input (desktop or mobile)
        │
        ▼
hamseda-search.js (debounce 400ms)
        │
        ▼
POST /wp-admin/admin-ajax.php
  │  action=hamseda_global_search
  │  term=<query>
  │  nonce=<security token>
        │
        ▼
class-ajax-handler.php :: handle_search()
  │  ── Verifies nonce
  │  ── Checks rate limit (30 req/60s per IP)
  │  ── Sanitizes term
        │
        ├──► class-search-query.php :: execute()
        │      ├── Normalize Persian chars
        │      ├── Apply fuzzy wildcard filter (posts_search)
        │      └── WP_Query → posts
        │
        └──► class-search-query.php :: search_product_categories()
               └── get_terms() + transient cache
        │
        ▼
JSON Response:
{
  categories: [...],
  posts: [...]
}
        │
        ▼
hamseda-search.js :: renderSearchResults()
        │
        ├── Categories → grid with count badges
        └── Posts → list with type badges, images, prices
        │
        ▼
DOM Updated in search-template.php
```

---

## Export Data Specifications

All exported data flows through the AJAX handler into JSON. The key export payload is:

### Categories Array
| Field     | Type     | Description               |
|-----------|----------|---------------------------|
| `term_id` | int      | WordPress term ID         |
| `name`    | string   | Category name (decoded)   |
| `url`     | string   | Category permalink        |
| `count`   | int      | Product count in category |

### Posts Array
| Field           | Type     | Description                        |
|-----------------|----------|------------------------------------|
| `id`            | int      | Post ID                            |
| `title`         | string   | Post title (HTML-decoded)          |
| `permalink`     | string   | Post URL                           |
| `image_url`     | string?  | Thumbnail URL or `null`            |
| `post_type`     | string   | `esanj`, `post`, `page`, `product` |
| `badge_color`   | string   | Hex color per post type            |
| `post_type_label` | string | Custom display label from admin settings |
| `regular_price` | string   | (Product only) Regular price       |
| `sale_price`    | string   | (Product only) Sale price          |
| `stock_status`  | string   | (Product only) `instock`/`outofstock` |

---

## Key Features Summary

- **Singleton architecture** with module separation (assets, query, AJAX, shortcode, settings)
- **Admin settings panel** with dynamic post type/taxonomy discovery, custom labels, and section headers
- **Persian text normalization** (Arabic → Persian character conversion)
- **Fuzzy wildcard matching** for common psychiatric/medical misspellings (6 word groups)
- **Multi-post-type search:** Custom post type `esanj` + posts + pages + WooCommerce products
- **Product category search** with 12-hour transient caching, 6-level relevance scoring, and auto-invalidation on term changes
- **Dual responsive UI:** Inline desktop dropdown + full-screen mobile modal
- **AbortController** for canceling stale AJAX requests
- **Debounced input** (400ms delay)
- **Rate-limited AJAX** (30 requests per 60 seconds per IP)
- **Keyboard shortcuts:** `Ctrl+K` / `Cmd+K` and `/` to open search
- **Animated results** with staggered fade-in and loading dots
- **Custom scrollbar** styled with brand colors
- **Nonce-protected AJAX** for security
- **Tailwind CSS** with scoped isolation via `important` selector
- **RTL-first design** (Persian language)
- **Thorough uninstall** with multisite support and full transient cleanup

---

## Build Pipeline

```bash
npm run build    # tailwindcss -i input.css -o hamseda-search.css --minify
npm run watch    # tailwindcss -i input.css -o hamseda-search.css --watch
```

The CSS source is `assets/css/src/input.css` and the minified output is `assets/css/hamseda-search.css`.
