<?php
/**
 * Class Alireza_AJAX_Handler
 * Handles AJAX search requests.
 *
 * @package Alireza_Ajax_Search
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alireza_AJAX_Handler {

	/**
	 * Maximum number of requests allowed per time window.
	 *
	 * @var int
	 */
	const RATE_LIMIT_MAX = 40;

	/**
	 * Time window in seconds for rate limiting.
	 *
	 * @var int
	 */
	const RATE_LIMIT_WINDOW = 60;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Hook AJAX handlers for logged in and guest users
		add_action( 'wp_ajax_alireza_global_search', array( $this, 'handle_search' ) );
		add_action( 'wp_ajax_nopriv_alireza_global_search', array( $this, 'handle_search' ) );

		// Backward compatibility action hooks
		add_action( 'wp_ajax_hamseda_global_search', array( $this, 'handle_search' ) );
		add_action( 'wp_ajax_nopriv_hamseda_global_search', array( $this, 'handle_search' ) );
	}

	/**
	 * Check if the current visitor has exceeded the rate limit.
	 *
	 * @return bool True if the request is allowed, false if rate-limited.
	 */
	private function check_rate_limit() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
		$key = 'alireza_rl_' . md5( $ip );

		$data = get_transient( $key );
		$now  = time();

		if ( false === $data || $data['reset_at'] <= $now ) {
			set_transient(
				$key,
				array(
					'count'    => 1,
					'reset_at' => $now + self::RATE_LIMIT_WINDOW,
				),
				self::RATE_LIMIT_WINDOW + 10
			);
			return true;
		}

		if ( $data['count'] >= self::RATE_LIMIT_MAX ) {
			return false;
		}

		$data['count']++;
		$remaining_ttl = max( 1, $data['reset_at'] - $now );
		set_transient( $key, $data, $remaining_ttl + 10 );
		return true;
	}

	/**
	 * Handle AJAX search request.
	 */
	public function handle_search() {
		// 1. Verify Nonce (Support both alireza and hamseda nonces)
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( $_REQUEST['nonce'] ) : '';
		$is_valid_nonce = false;

		if ( ! empty( $nonce ) ) {
			if ( wp_verify_nonce( $nonce, 'alireza_search_nonce' ) || wp_verify_nonce( $nonce, 'hamseda_search_nonce' ) ) {
				$is_valid_nonce = true;
			}
		}

		// Check Rate Limit
		if ( ! $this->check_rate_limit() ) {
			wp_send_json_error(
				array( 'message' => __( 'Too many requests. Please wait a moment and try again.', 'alireza-ajax-search' ) ),
				429
			);
		}

		// 2. Sanitize and retrieve the search term
		$search_term = isset( $_REQUEST['term'] ) ? sanitize_text_field( $_REQUEST['term'] ) : '';
		$search_term = trim( $search_term );

		// Enforce the same minimum length as the client-side gate. Without this,
		// a request sent directly to admin-ajax.php (bypassing the JS) with a
		// 1-character term triggers the worst case for LIKE '%...%' scans across
		// wp_posts/wp_postmeta/wp_terms, on every request the rate limiter allows.
		if ( mb_strlen( $search_term ) < 2 ) {
			wp_send_json_success( array( 'categories' => array(), 'posts' => array() ) );
		}

		// 3. Check Server-Side Transient Cache
		$options = alireza_search()->get_settings();

		$cache_key = 'alz_s_' . md5( $search_term . serialize( $options ) );
		$cached_response = get_transient( $cache_key );
		if ( false !== $cached_response ) {
			wp_send_json_success( $cached_response );
		}

		// 4. Execute the Fuzzy Search WP_Query
		$query = alireza_search()->query->execute( $search_term );
		
		// Execute category search
		$categories = alireza_search()->query->search_product_categories( $search_term );

		$categories_results = array();
		$posts_results      = array();

		// Add categories to results first
		if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
			foreach ( $categories as $category ) {
				$categories_results[] = array(
					'term_id' => $category->term_id,
					'name'    => html_entity_decode( $category->name ),
					'url'     => get_term_link( $category ),
					'count'   => $category->count,
				);
			}
		}

		// 5. Format the output JSON
		if ( $query->have_posts() ) {
			$custom_labels       = isset( $options['custom_labels'] ) ? $options['custom_labels'] : array();
			$custom_badge_colors = isset( $options['badge_colors'] ) && is_array( $options['badge_colors'] ) ? $options['badge_colors'] : array();

			while ( $query->have_posts() ) {
				$query->the_post();

				$post_type = get_post_type();
				
				if ( ! empty( $custom_badge_colors[ $post_type ] ) ) {
					$badge_color = $custom_badge_colors[ $post_type ];
				} else {
					switch ( $post_type ) {
						case 'esanj':
							$badge_color = '#FFB3C1';
							break;
						case 'post':
							$badge_color = '#7BA4F5';
							break;
						case 'page':
							$badge_color = '#64748B';
							break;
						case 'product':
							$badge_color = '#F59E0B';
							break;
						default:
							$badge_color = '#94A3B8';
							break;
					}
				}

				$image_url = has_post_thumbnail() ? get_the_post_thumbnail_url( get_the_ID(), 'thumbnail' ) : null;

				$post_type_obj   = get_post_type_object( $post_type );
				if ( ! empty( $custom_labels[ $post_type ] ) ) {
					$post_type_label = $custom_labels[ $post_type ];
				} else {
					$post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $post_type;
				}

				$item_data = array(
					'id'              => get_the_ID(),
					'title'           => html_entity_decode( get_the_title() ),
					'permalink'       => get_permalink(),
					'image_url'       => $image_url,
					'post_type'       => $post_type,
					'post_type_label' => $post_type_label,
					'badge_color'     => $badge_color,
				);

				// Add product specific metadata
				if ( 'product' === $post_type && alireza_search()->is_woocommerce_active() ) {
					$item_data['regular_price'] = get_post_meta( get_the_ID(), '_regular_price', true );
					$item_data['sale_price']    = get_post_meta( get_the_ID(), '_sale_price', true );
					$item_data['stock_status']  = get_post_meta( get_the_ID(), '_stock_status', true );
				}

				$posts_results[] = $item_data;
			}
			wp_reset_postdata();
		}

		// 6. Cache and Send JSON Response
		$response_data = array( 
			'categories' => $categories_results,
			'posts'      => $posts_results 
		);
		set_transient( $cache_key, $response_data, HOUR_IN_SECONDS );
		wp_send_json_success( $response_data );
	}
}
