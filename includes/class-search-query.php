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

		// Hook our custom SQL generator to posts_search
		add_filter( 'posts_search', array( $this, 'custom_posts_search' ), 10, 2 );

		// Initialize WP_Query
		$query = new WP_Query( $args );

		// Unhook immediately to prevent affecting other queries on the page
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
	 * Filter posts_search to inject fuzzy search wildcards.
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
				$escaped_wildcard = str_replace( '\\_', '_', $wpdb->esc_like( $wildcard_word ) );

				// Replace the original escaped word in the SQL query with the wildcard version
				$search = str_replace( $escaped_word, $escaped_wildcard, $search );
			}
		}

		return $search;
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

		// Handle key matching (make sure it works for both inputs)
		if ( isset( $wildcards[ $word ] ) ) {
			return $wildcards[ $word ];
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
				$escaped_wildcard = str_replace( '\\_', '_', $wpdb->esc_like( $wildcard_word ) );

				// Replace the original escaped word in the WHERE clause
				$clauses['where'] = str_replace( $escaped_word, $escaped_wildcard, $clauses['where'] );
			}
		}

		return $clauses;
	}

	/**
	 * Search within all enabled taxonomies using get_terms with transient caching.
	 * Works for any public taxonomy (custom, WooCommerce, etc.).
	 *
	 * @param string $search_term The search keyword.
	 * @return array Array of matching WP_Term objects with taxonomy info.
	 */
	public function search_product_categories( $search_term ) {
		$options = get_option( 'hamseda_search_settings', array() );
		$enabled_taxonomies = array();

		if ( ! empty( $options ) && isset( $options['taxonomies'] ) && is_array( $options['taxonomies'] ) ) {
			foreach ( $options['taxonomies'] as $slug => $enabled ) {
				if ( $enabled ) {
					$enabled_taxonomies[] = sanitize_key( $slug );
				}
			}
		}

		// No taxonomies enabled — return empty
		if ( empty( $enabled_taxonomies ) ) {
			return array();
		}

		$normalized_term = $this->normalize_persian_text( $search_term );

		$cache_key    = 'hamseda_tax_search_' . md5( $normalized_term . implode( '|', $enabled_taxonomies ) );
		$cached_terms = get_transient( $cache_key );

		if ( false !== $cached_terms ) {
			return $cached_terms;
		}

		// Build a list of search variants to cover multi-word input.
		// e.g. "تست های" → [ "تست های", "تستهای", "تست", "های" ]
		$search_variants = array( $normalized_term );

		// Space-stripped compound form (e.g. "تست های" → "تستهای")
		$stripped = str_replace( ' ', '', $normalized_term );
		if ( $stripped !== $normalized_term && ! in_array( $stripped, $search_variants, true ) ) {
			$search_variants[] = $stripped;
		}

		// Individual words (only words with 3+ characters)
		$words = explode( ' ', $normalized_term );
		if ( count( $words ) > 1 ) {
			foreach ( $words as $word ) {
				$word = trim( $word );
				if ( mb_strlen( $word ) >= 3 && ! in_array( $word, $search_variants, true ) ) {
					$search_variants[] = $word;
				}
			}
		}

		$merged_terms = array();
		$seen_term_ids = array();

		foreach ( $search_variants as $variant ) {
			// Store context for the filter
			$this->current_category_search_term = $variant;
			$this->current_search_taxonomies    = $enabled_taxonomies;

			// Hook terms_clauses filter
			add_filter( 'terms_clauses', array( $this, 'custom_terms_clauses' ), 10, 3 );

			$args = array(
				'taxonomy'   => $enabled_taxonomies,
				'hide_empty' => false,
				'search'     => $variant,
				'number'     => 8,
			);

			$terms = get_terms( $args );

			// Unhook immediately
			remove_filter( 'terms_clauses', array( $this, 'custom_terms_clauses' ), 10 );

			// Reset context
			$this->current_category_search_term = '';
			$this->current_search_taxonomies    = array();

			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( ! in_array( $term->term_id, $seen_term_ids, true ) ) {
						$merged_terms[]  = $term;
						$seen_term_ids[] = $term->term_id;
					}
				}
			}

			// Stop early if we already have enough results
			if ( count( $merged_terms ) >= 8 ) {
				break;
			}
		}

		if ( ! empty( $merged_terms ) ) {
			set_transient( $cache_key, $merged_terms, 12 * HOUR_IN_SECONDS );
			return $merged_terms;
		}

		return array();
	}
}
