<?php
/**
 * Class Alireza_Search_Query
 * Handles backend search logic using WP_Query.
 *
 * @package Alireza_Ajax_Search
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alireza_Search_Query {

	/**
	 * Minimum number of relevant taxonomy matches considered "enough" to skip
	 * the more expensive fallback get_terms() variants (stripped/compound and
	 * per-word). The previous behavior only stopped once the full $max_results
	 * (8) was reached, which meant most multi-word searches ran every fallback
	 * query even though the first variant already had usable results.
	 *
	 * @var int
	 */
	const SUFFICIENT_CATEGORY_RESULTS = 1;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'saved_term', array( $this, 'invalidate_taxonomy_cache' ), 10, 3 );
		add_action( 'deleted_term', array( $this, 'invalidate_taxonomy_cache' ), 10, 3 );
		add_action( 'save_post', array( $this, 'invalidate_post_search_cache' ) );
	}

	/**
	 * Purge all taxonomy-search transients from the database.
	 */
	public function invalidate_taxonomy_cache( $term_id, $tt_id, $taxonomy ) {
		global $wpdb;

		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_alireza_tax_search_%'
			    OR option_name LIKE '_transient_timeout_alireza_tax_search_%'
			    OR option_name LIKE '_transient_hamseda_tax_search_%'
			    OR option_name LIKE '_transient_timeout_hamseda_tax_search_%'"
		);
	}

	/**
	 * Purge all post-search transients from the database.
	 *
	 * Skips autosaves and revisions: WordPress fires `save_post` for both
	 * (autosave runs every 60s while an editor has a post open), and a full
	 * transient wipe on every one of those never lets the search cache warm up.
	 *
	 * @param int $post_id Post ID passed by the `save_post` action.
	 */
	public function invalidate_post_search_cache( $post_id ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		global $wpdb;

		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_alz_s_%'
			    OR option_name LIKE '_transient_timeout_alz_s_%'
			    OR option_name LIKE '_transient_hms_s_%'
			    OR option_name LIKE '_transient_timeout_hms_s_%'"
		);
	}

	/**
	 * Execute search query based on search term.
	 *
	 * @param string $search_term The search keyword.
	 * @return WP_Query
	 */
	public function execute( $search_term ) {
		global $wpdb;

		// Normalize Persian/Arabic characters
		$normalized_term = $this->normalize_persian_text( $search_term );

		$options = alireza_search()->get_settings();

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
			if ( alireza_search()->is_woocommerce_active() ) {
				$post_types[] = 'product';
			}
		}

		$merged_post_ids = array();

		// 1. Direct indexed scan on wp_postmeta for SKU matching (WooCommerce active & product type is searchable)
		$sku_post_ids = array();
		if ( alireza_search()->is_woocommerce_active() && in_array( 'product', $post_types, true ) ) {
			$sku_post_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = '_sku' AND meta_value LIKE %s
				 LIMIT 50",
				'%' . $wpdb->esc_like( $normalized_term ) . '%'
			) );
		}

		// 2. Direct indexed scan on wp_term_relationships for attributes/categories/brands matching
		$term_post_ids = array();
		if ( alireza_search()->is_woocommerce_active() ) {
			$term_post_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT tr.object_id FROM {$wpdb->term_relationships} AS tr
				 INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				 INNER JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id
				 WHERE t.name LIKE %s AND (tt.taxonomy LIKE 'pa_%%' OR tt.taxonomy IN ('product_cat', 'product_tag', 'product_brand', 'pwb-brand', 'yith_product_brand', 'brand'))
				 LIMIT 50",
				'%' . $wpdb->esc_like( $normalized_term ) . '%'
			) );
		}

		// 3. WP_Query search on text (post_title and post_content)
		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			's'              => $normalized_term,
			'fields'         => 'ids',
			'no_found_rows'  => true, // Skip SQL_CALC_FOUND_ROWS + FOUND_ROWS() — pagination is never used here.
		);

		add_filter( 'posts_search', array( $this, 'custom_posts_search' ), 10, 2 );
		$text_query = new WP_Query( $args );
		remove_filter( 'posts_search', array( $this, 'custom_posts_search' ), 10 );

		$text_post_ids = ! empty( $text_query->posts ) ? $text_query->posts : array();
		$merged_post_ids = array_unique( array_merge( $text_post_ids, $sku_post_ids, $term_post_ids ) );

		// 4. Retrieve complete objects for merged IDs
		if ( ! empty( $merged_post_ids ) ) {
			$final_args = array(
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'post__in'               => $merged_post_ids,
				'posts_per_page'         => 10,
				'orderby'                => 'post__in',
				'no_found_rows'          => true, // Same as above — no pagination is ever rendered for this query.
				'update_post_term_cache' => false, // The JSON response never reads post terms; skip priming/joining them.
			);
			$query = new WP_Query( $final_args );

			// Prime thumbnail meta for every result in one query instead of one
			// per-post lookup triggered later by get_the_post_thumbnail_url().
			if ( ! empty( $query->posts ) ) {
				update_post_thumbnail_cache( $query );
			}
		} else {
			$query = new WP_Query();
			$query->posts = array();
			$query->post_count = 0;
		}

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
			$query->posts        = array_values( $query->posts );
			$query->post_count   = count( $query->posts );
			$query->current_post = -1;
		}

		return $query;
	}

	/**
	 * Normalize Arabic letters to Persian equivalents.
	 */
	private function normalize_persian_text( $text ) {
		$arabic_chars  = array( 'ي', 'ك' );
		$persian_chars = array( 'ی', 'ک' );

		return str_replace( $arabic_chars, $persian_chars, $text );
	}

	/**
	 * Filter posts_search to inject fuzzy search wildcards.
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

		$words = explode( ' ', $search_term );

		foreach ( $words as $word ) {
			$word = trim( $word );
			if ( empty( $word ) || mb_strlen( $word ) < 3 ) {
				continue;
			}

			$wildcard_word = $this->get_fuzzy_wildcard_word( $word );
			if ( $wildcard_word !== $word ) {
				$escaped_word     = $wpdb->esc_like( $word );
				$escaped_wildcard = str_replace( array( '\\_', '\\%' ), array( '_', '%' ), $wpdb->esc_like( $wildcard_word ) );

				$search = str_replace( $escaped_word, $escaped_wildcard, $search );
			}
		}

		return $search;
	}

	/**
	 * Retrieve dictionary configuration arrays.
	 */
	private function get_dictionary() {
		static $dictionary = null;
		if ( is_null( $dictionary ) ) {
			$file_path = ALIREZA_SEARCH_PATH . 'includes/alireza-dictionary.php';
			if ( file_exists( $file_path ) ) {
				$dictionary = include $file_path;
			}
			if ( ! is_array( $dictionary ) ) {
				$dictionary = array();
			}
			$dictionary = wp_parse_args( $dictionary, array(
				'fuzzy_words' => array(),
				'prefixes'    => array(),
				'suffixes'    => array(),
			) );
		}
		return $dictionary;
	}

	/**
	 * Get the wildcard version of a word for SQL LIKE query.
	 */
	private function get_fuzzy_wildcard_word( $word ) {
		$dictionary = $this->get_dictionary();
		$wildcards  = $dictionary['fuzzy_words'];

		$processed = $this->split_compound_word( $word );

		if ( isset( $wildcards[ $processed ] ) ) {
			$processed = $wildcards[ $processed ];
		}

		$fuzzy_word = str_replace( array( 'د', 'ذ' ), '_', $processed );
		if ( $fuzzy_word !== $processed ) {
			$processed = $fuzzy_word;
		}

		return $processed;
	}

	/**
	 * Split compound words.
	 */
	private function split_compound_word( $word ) {
		$dictionary = $this->get_dictionary();
		$prefixes   = $dictionary['prefixes'];
		$suffixes   = $dictionary['suffixes'];

		foreach ( $prefixes as $prefix ) {
			$prefix_len = mb_strlen( $prefix );
			if ( mb_strlen( $word ) > $prefix_len && mb_substr( $word, 0, $prefix_len ) === $prefix ) {
				if ( mb_strpos( $word, '%' ) === false && mb_strpos( $word, '_' ) === false ) {
					return $prefix . '%' . mb_substr( $word, $prefix_len );
				}
			}
		}

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

	private $current_category_search_term = '';
	private $current_search_taxonomies    = array();

	public function custom_terms_clauses( $clauses, $taxonomies, $args ) {
		global $wpdb;

		if ( empty( $clauses['where'] ) || empty( $this->current_category_search_term ) ) {
			return $clauses;
		}

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

				$clauses['where'] = str_replace( $escaped_word, $escaped_wildcard, $clauses['where'] );
			}
		}

		return $clauses;
	}

	private function get_term_relevance_score( $term, $search_term ) {
		$term_name   = mb_strtolower( $this->normalize_persian_text( $term->name ) );
		$search_term = mb_strtolower( $search_term );

		if ( $term_name === $search_term ) {
			return 5;
		}

		if ( mb_strpos( $term_name, $search_term ) === 0 ) {
			return 4;
		}

		if ( mb_strpos( $term_name, $search_term ) !== false ) {
			return 3;
		}

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
	 * Search within enabled taxonomies.
	 */
	public function search_product_categories( $search_term ) {
		$options = alireza_search()->get_settings();

		$enabled_taxonomies = array();

		if ( ! empty( $options ) && isset( $options['taxonomies'] ) && is_array( $options['taxonomies'] ) ) {
			foreach ( $options['taxonomies'] as $slug => $enabled ) {
				if ( $enabled ) {
					$enabled_taxonomies[] = sanitize_key( $slug );
				}
			}
		}

		// Fallback if no taxonomies enabled yet
		if ( empty( $enabled_taxonomies ) ) {
			$enabled_taxonomies = array( 'category' );
			if ( alireza_search()->is_woocommerce_active() ) {
				$enabled_taxonomies[] = 'product_cat';
			}
		}

		$normalized_term = $this->normalize_persian_text( $search_term );
		$max_results     = 8;

		$cache_key    = 'alireza_tax_search_' . md5( $normalized_term . implode( '|', $enabled_taxonomies ) );
		$cached_terms = get_transient( $cache_key );

		if ( false !== $cached_terms ) {
			return $cached_terms;
		}

		$raw_terms     = $this->run_term_query( $normalized_term, $enabled_taxonomies, $max_results * 3 );
		$scored_terms  = array();
		$seen_term_ids = array();

		foreach ( $raw_terms as $term ) {
			$score = $this->get_term_relevance_score( $term, $normalized_term );

			if ( $score === 0 ) {
				continue;
			}

			if ( ! in_array( $term->term_id, $seen_term_ids, true ) ) {
				$term->_alireza_score = $score;
				$scored_terms[]       = $term;
				$seen_term_ids[]      = $term->term_id;
			}
		}

		$stripped = str_replace( ' ', '', $normalized_term );
		if ( $stripped !== $normalized_term && count( $scored_terms ) < self::SUFFICIENT_CATEGORY_RESULTS ) {
			$compound_terms = $this->run_term_query( $stripped, $enabled_taxonomies, $max_results * 2 );
			foreach ( $compound_terms as $term ) {
				if ( in_array( $term->term_id, $seen_term_ids, true ) ) {
					continue;
				}
				$score = $this->get_term_relevance_score( $term, $normalized_term );
				if ( $score === 0 ) {
					continue;
				}
				$term->_alireza_score = $score;
				$scored_terms[]       = $term;
				$seen_term_ids[]      = $term->term_id;
			}
		}

		$words = explode( ' ', $normalized_term );
		if ( count( $words ) > 1 && count( $scored_terms ) < self::SUFFICIENT_CATEGORY_RESULTS ) {
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
						$term_name = mb_strtolower( $this->normalize_persian_text( $term->name ) );
						$word_lc   = mb_strtolower( $word );
						if ( mb_strpos( $term_name, $word_lc ) === false ) {
							continue;
						}
						$score = 1;
					}
					$term->_alireza_score = $score;
					$scored_terms[]       = $term;
					$seen_term_ids[]      = $term->term_id;
				}

				if ( count( $scored_terms ) >= self::SUFFICIENT_CATEGORY_RESULTS ) {
					break;
				}
			}
		}

		if ( ! empty( $scored_terms ) ) {
			usort( $scored_terms, function( $a, $b ) {
				return $b->_alireza_score - $a->_alireza_score;
			} );

			$scored_terms = array_slice( $scored_terms, 0, $max_results );

			set_transient( $cache_key, $scored_terms, 12 * HOUR_IN_SECONDS );
			return $scored_terms;
		}

		return array();
	}
}
