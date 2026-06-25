<?php
/**
 * Class HamSeda_Search_Query
 * Handles backend search logic using WP_Query.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HamSeda_Search_Query {

	/**
	 * Constructor.
	 * Registers hooks to automatically invalidate the taxonomy search cache
	 * whenever a term is created, updated, or deleted.
	 */
	public function __construct() {
		// Fires after any term is added or updated (covers create + edit).
		add_action( 'saved_term', array( $this, 'invalidate_taxonomy_cache' ), 10, 3 );
		// Fires after a term is permanently deleted.
		add_action( 'deleted_term', array( $this, 'invalidate_taxonomy_cache' ), 10, 3 );
	}

	/**
	 * Purge all hamseda taxonomy-search transients from the database.
	 *
	 * Called automatically when any term is saved or deleted so that stale
	 * cached results never hide newly added (or removed) categories from users.
	 *
	 * The $term_id, $tt_id and $taxonomy parameters are supplied by WordPress
	 * but are not needed here because we clear the entire cache group.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return void
	 */
	public function invalidate_taxonomy_cache( $term_id, $tt_id, $taxonomy ) {
		global $wpdb;

		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_hamseda_tax_search_%'
			    OR option_name LIKE '_transient_timeout_hamseda_tax_search_%'"
		);
	}

	/**
	 * Execute search query based on search term.
	 *
	 * @param string $search_term The search keyword.
	 * @return WP_Query
	 */
	public function execute( $search_term ) {
		// Normalize Persian/Arabic characters
		$normalized_term = $this->normalize_persian_text( $search_term );

		$options = get_option( 'hamseda_search_settings', array() );
		$post_types = array();

		if ( ! empty( $options ) && isset( $options['post_types'] ) && is_array( $options['post_types'] ) ) {
			foreach ( $options['post_types'] as $slug => $enabled ) {
				if ( $enabled ) {
					$post_types[] = $slug;
				}
			}
		}

		// Fallback if no settings saved yet or no post types are checked
		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
			if ( hamseda_search()->is_woocommerce_active() ) {
				$post_types[] = 'product';
			}
		}

		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			's'              => $normalized_term,
		);

		// Hook our custom SQL generators
		add_filter( 'posts_join', array( $this, 'custom_search_join' ), 10, 2 );
		add_filter( 'posts_distinct', array( $this, 'custom_search_distinct' ), 10, 2 );
		add_filter( 'posts_search', array( $this, 'custom_posts_search' ), 10, 2 );

		// Initialize WP_Query
		$query = new WP_Query( $args );

		// Unhook immediately to prevent affecting other queries on the page
		remove_filter( 'posts_join', array( $this, 'custom_search_join' ), 10 );
		remove_filter( 'posts_distinct', array( $this, 'custom_search_distinct' ), 10 );
		remove_filter( 'posts_search', array( $this, 'custom_posts_search' ), 10 );

		// Sort posts by post_type priority: esanj > post > page > product > others
		if ( ! empty( $query->posts ) ) {
			$priority = array(
				'esanj'   => 0,
				'post'    => 1,
				'page'    => 2,
				'product' => 3,
			);
			usort( $query->posts, function( $a, $b ) use ( $priority ) {
				$pa = isset( $priority[ $a->post_type ] ) ? $priority[ $a->post_type ] : 99;
				$pb = isset( $priority[ $b->post_type ] ) ? $priority[ $b->post_type ] : 99;
				return $pa - $pb;
			} );
			// Reindex the posts array so have_posts/the_post works correctly
			$query->posts         = array_values( $query->posts );
			$query->post_count    = count( $query->posts );
			$query->current_post  = -1;
		}

		return $query;
	}

	/**
	 * Normalize Arabic letters to Persian equivalents.
	 *
	 * @param string $text Raw input text.
	 * @return string Normalized text.
	 */
	private function normalize_persian_text( $text ) {
		$arabic_chars  = array( 'ي', 'ك' );
		$persian_chars = array( 'ی', 'ک' );

		return str_replace( $arabic_chars, $persian_chars, $text );
	}

	/**
	 * Filter posts_search to inject fuzzy search wildcards and WooCommerce SKU, attribute, and brand matching.
	 *
	 * @param string   $search   Search SQL clause.
	 * @param WP_Query $wp_query The WP_Query instance.
	 * @return string Modified Search SQL clause.
	 */
	public function custom_posts_search( $search, $wp_query ) {
		global $wpdb;

		if ( empty( $search ) ) {
			return $search;
		}

		$search_term = $wp_query->get( 's' );
		if ( empty( $search_term ) ) {
			return $search;
		}

		// Split the search term into words
		$words = explode( ' ', $search_term );

		foreach ( $words as $word ) {
			$word = trim( $word );
			if ( empty( $word ) || mb_strlen( $word ) < 3 ) {
				continue;
			}

			$wildcard_word = $this->get_fuzzy_wildcard_word( $word );
			if ( $wildcard_word !== $word ) {
				// WordPress escapes search terms for SQL LIKE.
				// We obtain the escaped version of both original and wildcard words.
				$escaped_word     = $wpdb->esc_like( $word );
				$escaped_wildcard = str_replace( array( '\\_', '\\%' ), array( '_', '%' ), $wpdb->esc_like( $wildcard_word ) );

				// Replace the original escaped word in the SQL query with the wildcard version
				$search = str_replace( $escaped_word, $escaped_wildcard, $search );
			}
		}

		// Inject WooCommerce SKU, Attribute and Brand search if WooCommerce is active
		if ( hamseda_search()->is_woocommerce_active() ) {
			// Find each pattern like `(wp_posts.post_content LIKE '%[escaped_word]%')` and append the postmeta & term matches
			$pattern = '/\(' . preg_quote( $wpdb->posts, '/' ) . '\.post_content\s+LIKE\s+(\'([^\']+)\')\)/';
			$replacement = '(' . $wpdb->posts . '.post_content LIKE $1) OR (hamseda_pm.meta_value LIKE $1) OR (hamseda_t.name LIKE $1 AND (hamseda_tt.taxonomy LIKE \'pa_%\' OR hamseda_tt.taxonomy IN (\'product_cat\', \'product_tag\', \'product_brand\', \'pwb-brand\', \'yith_product_brand\', \'brand\')))';
			$search = preg_replace( $pattern, $replacement, $search );
		}

		return $search;
	}

	/**
	 * Join postmeta and taxonomy tables when WooCommerce search is active.
	 *
	 * @param string   $join     Join SQL clause.
	 * @param WP_Query $wp_query The WP_Query instance.
	 * @return string Modified Join SQL clause.
	 */
	public function custom_search_join( $join, $wp_query ) {
		global $wpdb;

		if ( ! empty( $wp_query->get( 's' ) ) && hamseda_search()->is_woocommerce_active() ) {
			// Join postmeta for SKU search (with a unique alias to prevent collisions)
			$join .= " LEFT JOIN {$wpdb->postmeta} AS hamseda_pm ON ({$wpdb->posts}.ID = hamseda_pm.post_id AND hamseda_pm.meta_key = '_sku') ";
			
			// Join term tables for attributes and brands search
			$join .= " LEFT JOIN {$wpdb->term_relationships} AS hamseda_tr ON ({$wpdb->posts}.ID = hamseda_tr.object_id) ";
			$join .= " LEFT JOIN {$wpdb->term_taxonomy} AS hamseda_tt ON (hamseda_tr.term_taxonomy_id = hamseda_tt.term_taxonomy_id) ";
			$join .= " LEFT JOIN {$wpdb->terms} AS hamseda_t ON (hamseda_tt.term_id = hamseda_t.term_id) ";
		}

		return $join;
	}

	/**
	 * Set SELECT DISTINCT when WooCommerce search is active to prevent duplicates.
	 *
	 * @param string   $distinct Distinct SQL clause.
	 * @param WP_Query $wp_query The WP_Query instance.
	 * @return string Modified Distinct SQL clause.
	 */
	public function custom_search_distinct( $distinct, $wp_query ) {
		if ( ! empty( $wp_query->get( 's' ) ) && hamseda_search()->is_woocommerce_active() ) {
			return 'DISTINCT';
		}
		return $distinct;
	}

	/**
	 * Get the wildcard version of a word for SQL LIKE query.
	 *
	 * @param string $word
	 * @return string
	 */
	private function get_fuzzy_wildcard_word( $word ) {
		// Define target psychiatric words and their wildcard equivalents
		$wildcards = array(
			'اضطراب'    => 'ا_طراب',
			'اظطراب'    => 'ا_طراب',
			'ازطراب'    => 'ا_طراب',
			'اذطراب'    => 'ا_طراب',
			'اضتراب'    => 'ا_تراب',
			'اظتراب'    => 'ا_تراب',

			'وسواس'     => 'و_وا_',
			'وصواص'     => 'و_وا_',
			'وسواص'     => 'و_وا_',
			'وصواس'     => 'و_وا_',

			'افسردگی'   => 'اف_ردگی',
			'افصردگی'   => 'اف_ردگی',

			'تمرکز'     => 'تمرک_',
			'تمرکذ'     => 'تمرک_',

			'حافظه'     => 'حاف_ه',
			'حافضه'     => 'حاف_ه',
			'حافذه'     => 'حاف_ه',
			'حافزه'     => 'حاف_ه',

			'هیپنوتیزم'  => 'هیپنوتی_م',
			'هیپنوتیسم'  => 'هیپنوتی_م', // Note: using wildcard _ for the last confused letter
		);

		// 1. Split compound words if they are written together
		$processed = $this->split_compound_word( $word );

		// 2. Handle psychiatric wildcard dictionary
		if ( isset( $wildcards[ $processed ] ) ) {
			$processed = $wildcards[ $processed ];
		}

		// 3. Dynamic replacement for د and ذ (replace them with SQL single-character wildcard '_')
		$fuzzy_word = str_replace( array( 'د', 'ذ' ), '_', $processed );
		if ( $fuzzy_word !== $processed ) {
			$processed = $fuzzy_word;
		}

		return $processed;
	}

	/**
	 * Insert % wildcard between known prefixes/suffixes of compound words if they are written together.
	 *
	 * @param string $word
	 * @return string
	 */
	private function split_compound_word( $word ) {
		$prefixes = array( 'روان', 'خود', 'خوذ', 'خوش', 'برنامه', 'کتاب', 'پیش', 'هم', 'بی', 'تست' );
		$suffixes = array( 'نویس', 'نویسی', 'شناس', 'شناسی', 'سنج', 'سنجی', 'نامه', 'خانه', 'دهنده', 'کننده', 'یاب', 'یابی', 'ساز', 'سازی', 'کار', 'کاری', 'گذار', 'گذاری', 'گزار', 'گزاری', 'یار', 'یاری' );

		// Check prefixes first
		foreach ( $prefixes as $prefix ) {
			$prefix_len = mb_strlen( $prefix );
			if ( mb_strlen( $word ) > $prefix_len && mb_substr( $word, 0, $prefix_len ) === $prefix ) {
				if ( mb_strpos( $word, '%' ) === false && mb_strpos( $word, '_' ) === false ) {
					return $prefix . '%' . mb_substr( $word, $prefix_len );
				}
			}
		}

		// Check suffixes
		foreach ( $suffixes as $suffix ) {
			$suffix_len = mb_strlen( $suffix );
			$word_len   = mb_strlen( $word );
			if ( $word_len > $suffix_len && mb_substr( $word, $word_len - $suffix_len ) === $suffix ) {
				if ( mb_strpos( $word, '%' ) === false && mb_strpos( $word, '_' ) === false ) {
					return mb_substr( $word, 0, $word_len - $suffix_len ) . '%' . $suffix;
				}
			}
		}

		return $word;
	}

	/**
	 * Temporary property to hold current category search term.
	 *
	 * @var string
	 */
	private $current_category_search_term = '';

	/**
	 * Temporary property to hold taxonomies being searched.
	 *
	 * @var array
	 */
	private $current_search_taxonomies = array();

	/**
	 * Filter terms_clauses to inject fuzzy search wildcards for get_terms search.
	 *
	 * @param array $clauses    Terms query clauses.
	 * @param array $taxonomies Taxonomies queried.
	 * @param array $args       Arguments passed to get_terms.
	 * @return array Modified terms query clauses.
	 */
	public function custom_terms_clauses( $clauses, $taxonomies, $args ) {
		global $wpdb;

		if ( empty( $clauses['where'] ) || empty( $this->current_category_search_term ) ) {
			return $clauses;
		}

		// Only apply to the taxonomies we are currently searching
		$has_match = false;
		foreach ( $this->current_search_taxonomies as $enabled_tax ) {
			if ( in_array( $enabled_tax, $taxonomies, true ) ) {
				$has_match = true;
				break;
			}
		}
		if ( ! $has_match ) {
			return $clauses;
		}

		// Split the search term into words
		$words = explode( ' ', $this->current_category_search_term );

		foreach ( $words as $word ) {
			$word = trim( $word );
			if ( empty( $word ) || mb_strlen( $word ) < 3 ) {
				continue;
			}

			$wildcard_word = $this->get_fuzzy_wildcard_word( $word );
			if ( $wildcard_word !== $word ) {
				$escaped_word     = $wpdb->esc_like( $word );
				$escaped_wildcard = str_replace( array( '\\_', '\\%' ), array( '_', '%' ), $wpdb->esc_like( $wildcard_word ) );

				// Replace the original escaped word in the WHERE clause
				$clauses['where'] = str_replace( $escaped_word, $escaped_wildcard, $clauses['where'] );
			}
		}

		return $clauses;
	}

	/**
	 * Calculate a relevance score for a term against the search query.
	 * Higher score = more relevant result.
	 *
	 * Scoring rules:
	 *  5 — Exact match (normalized names are identical)
	 *  4 — Term name starts with the full search term
	 *  3 — Term name contains the full search term
	 *  2 — Term name starts with any individual search word
	 *  1 — Term name contains any individual search word
	 *  0 — No recognisable match (fallback)
	 *
	 * @param WP_Term $term        The taxonomy term to score.
	 * @param string  $search_term Normalized search input.
	 * @return int Relevance score (higher = more relevant).
	 */
	private function get_term_relevance_score( $term, $search_term ) {
		$term_name   = mb_strtolower( $this->normalize_persian_text( $term->name ) );
		$search_term = mb_strtolower( $search_term );

		// Exact match
		if ( $term_name === $search_term ) {
			return 5;
		}

		// Starts with full term
		if ( mb_strpos( $term_name, $search_term ) === 0 ) {
			return 4;
		}

		// Contains full term anywhere
		if ( mb_strpos( $term_name, $search_term ) !== false ) {
			return 3;
		}

		// Per-word checks (for multi-word queries)
		$words = explode( ' ', $search_term );
		foreach ( $words as $word ) {
			$word = trim( $word );
			if ( mb_strlen( $word ) < 3 ) {
				continue;
			}
			if ( mb_strpos( $term_name, $word ) === 0 ) {
				return 2;
			}
			if ( mb_strpos( $term_name, $word ) !== false ) {
				return 1;
			}
		}

		return 0;
	}

	/**
	 * Run a single get_terms query for a given search variant.
	 *
	 * @param string $variant           The search variant string.
	 * @param array  $enabled_taxonomies Taxonomies to search within.
	 * @param int    $limit             Maximum number of terms to return.
	 * @return array Array of WP_Term objects.
	 */
	private function run_term_query( $variant, $enabled_taxonomies, $limit = 20 ) {
		$this->current_category_search_term = $variant;
		$this->current_search_taxonomies    = $enabled_taxonomies;

		add_filter( 'terms_clauses', array( $this, 'custom_terms_clauses' ), 10, 3 );

		$terms = get_terms( array(
			'taxonomy'   => $enabled_taxonomies,
			'hide_empty' => false,
			'search'     => $variant,
			'number'     => $limit,
		) );

		remove_filter( 'terms_clauses', array( $this, 'custom_terms_clauses' ), 10 );

		$this->current_category_search_term = '';
		$this->current_search_taxonomies    = array();

		return ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms : array();
	}

	/**
	 * Search within all enabled taxonomies using get_terms with transient caching.
	 *
	 * Strategy (relevance-first):
	 *  1. Search using the FULL normalised term.
	 *  2. Score every result so the closest match comes first.
	 *  3. Filter out any result whose score is 0 (no real match).
	 *  4. Only fall back to individual-word variants when the full-term search
	 *     returns fewer than the requested limit.
	 *
	 * This prevents "خودکار" from returning every category that contains "کار".
	 *
	 * @param string $search_term The search keyword.
	 * @return array Array of matching WP_Term objects sorted by relevance.
	 */
	public function search_product_categories( $search_term ) {
		$options            = get_option( 'hamseda_search_settings', array() );
		$enabled_taxonomies = array();

		if ( ! empty( $options ) && isset( $options['taxonomies'] ) && is_array( $options['taxonomies'] ) ) {
			foreach ( $options['taxonomies'] as $slug => $enabled ) {
				if ( $enabled ) {
					$enabled_taxonomies[] = sanitize_key( $slug );
				}
			}
		}

		// No taxonomies enabled — return empty.
		if ( empty( $enabled_taxonomies ) ) {
			return array();
		}

		$normalized_term = $this->normalize_persian_text( $search_term );
		$max_results     = 8;

		$cache_key    = 'hamseda_tax_search_' . md5( $normalized_term . implode( '|', $enabled_taxonomies ) );
		$cached_terms = get_transient( $cache_key );

		if ( false !== $cached_terms ) {
			return $cached_terms;
		}

		/* ------------------------------------------------------------------ *
		 * PHASE 1 — Search with the complete (normalised) term.               *
		 * ------------------------------------------------------------------ */
		$raw_terms     = $this->run_term_query( $normalized_term, $enabled_taxonomies, $max_results * 3 );
		$scored_terms  = array();
		$seen_term_ids = array();

		foreach ( $raw_terms as $term ) {
			$score = $this->get_term_relevance_score( $term, $normalized_term );

			// Skip completely irrelevant results (score 0 means only an
			// accidental DB match via a very broad LIKE wildcard).
			if ( $score === 0 ) {
				continue;
			}

			if ( ! in_array( $term->term_id, $seen_term_ids, true ) ) {
				$term->_hamseda_score = $score;
				$scored_terms[]       = $term;
				$seen_term_ids[]      = $term->term_id;
			}
		}

		/* ------------------------------------------------------------------ *
		 * PHASE 2 — Also try the space-stripped compound form.                *
		 * e.g. "تست های" → "تستهای"                                           *
		 * ------------------------------------------------------------------ */
		$stripped = str_replace( ' ', '', $normalized_term );
		if ( $stripped !== $normalized_term && count( $scored_terms ) < $max_results ) {
			$compound_terms = $this->run_term_query( $stripped, $enabled_taxonomies, $max_results * 2 );
			foreach ( $compound_terms as $term ) {
				if ( in_array( $term->term_id, $seen_term_ids, true ) ) {
					continue;
				}
				// Score against the original term so relevance is consistent.
				$score = $this->get_term_relevance_score( $term, $normalized_term );
				if ( $score === 0 ) {
					continue;
				}
				$term->_hamseda_score = $score;
				$scored_terms[]       = $term;
				$seen_term_ids[]      = $term->term_id;
			}
		}

		/* ------------------------------------------------------------------ *
		 * PHASE 3 — Fallback to individual words ONLY when we still have      *
		 * fewer results than needed. This avoids polluting the results of a   *
		 * precise single-word query (e.g. "خودکار") with unrelated terms that *
		 * merely contain one of the letters/words.                             *
		 * ------------------------------------------------------------------ */
		$words = explode( ' ', $normalized_term );
		if ( count( $words ) > 1 && count( $scored_terms ) < $max_results ) {
			foreach ( $words as $word ) {
				$word = trim( $word );
				if ( mb_strlen( $word ) < 3 ) {
					continue;
				}

				$word_terms = $this->run_term_query( $word, $enabled_taxonomies, $max_results * 2 );
				foreach ( $word_terms as $term ) {
					if ( in_array( $term->term_id, $seen_term_ids, true ) ) {
						continue;
					}
					$score = $this->get_term_relevance_score( $term, $normalized_term );
					if ( $score === 0 ) {
						// For word-level fallbacks, allow score-1 terms too
						// but only if the word itself appears in the term name.
						$term_name = mb_strtolower( $this->normalize_persian_text( $term->name ) );
						$word_lc   = mb_strtolower( $word );
						if ( mb_strpos( $term_name, $word_lc ) === false ) {
							continue;
						}
						$score = 1;
					}
					$term->_hamseda_score = $score;
					$scored_terms[]       = $term;
					$seen_term_ids[]      = $term->term_id;
				}

				if ( count( $scored_terms ) >= $max_results ) {
					break;
				}
			}
		}

		/* ------------------------------------------------------------------ *
		 * Sort by relevance score descending, then limit to $max_results.     *
		 * ------------------------------------------------------------------ */
		if ( ! empty( $scored_terms ) ) {
			usort( $scored_terms, function( $a, $b ) {
				return $b->_hamseda_score - $a->_hamseda_score;
			} );

			$scored_terms = array_slice( $scored_terms, 0, $max_results );

			set_transient( $cache_key, $scored_terms, 12 * HOUR_IN_SECONDS );
			return $scored_terms;
		}

		return array();
	}
}
