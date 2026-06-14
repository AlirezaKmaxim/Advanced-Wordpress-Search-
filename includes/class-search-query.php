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

		// Restrict strictly to product_cat taxonomy query
		if ( ! in_array( 'product_cat', $taxonomies, true ) ) {
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
	 * Search within product categories using get_terms with transient caching.
	 *
	 * @param string $search_term The search keyword.
	 * @return array Array of matching WP_Term objects.
	 */
	public function search_product_categories( $search_term ) {
		if ( ! hamseda_search()->is_woocommerce_active() ) {
			return array();
		}

		$options = get_option( 'hamseda_search_settings', array() );
		$taxonomies = array();

		if ( ! empty( $options ) && isset( $options['taxonomies'] ) && is_array( $options['taxonomies'] ) ) {
			foreach ( $options['taxonomies'] as $slug => $enabled ) {
				if ( $enabled ) {
					$taxonomies[] = $slug;
				}
			}
		} else {
			// Fallback if settings are empty (new install)
			$taxonomies = array( 'product_cat' );
		}

		// If product_cat is not enabled in settings, skip category search
		if ( ! in_array( 'product_cat', $taxonomies, true ) ) {
			return array();
		}

		$normalized_term = $this->normalize_persian_text( $search_term );

		$cache_key = 'hamseda_cat_search_' . md5( $normalized_term );
		$cached_terms = get_transient( $cache_key );

		if ( false !== $cached_terms ) {
			return $cached_terms;
		}

		// Store term to be accessed by filter
		$this->current_category_search_term = $normalized_term;

		// Hook terms_clauses filter
		add_filter( 'terms_clauses', array( $this, 'custom_terms_clauses' ), 10, 3 );

		$args = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'search'     => $normalized_term,
			'number'     => 5,
		);

		$terms = get_terms( $args );
		
		// Unhook immediately
		remove_filter( 'terms_clauses', array( $this, 'custom_terms_clauses' ), 10 );

		if ( ! is_wp_error( $terms ) ) {
			set_transient( $cache_key, $terms, 12 * HOUR_IN_SECONDS );
			return $terms;
		}

		return array();
	}
}
