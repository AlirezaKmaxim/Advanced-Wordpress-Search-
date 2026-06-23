<?php
/**
 * Class HamSeda_AJAX_Handler
 * Handles AJAX search requests.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HamSeda_AJAX_Handler {

	/**
	 * Constructor.
	 */
	/**
	 * Maximum number of requests allowed per time window.
	 *
	 * @var int
	 */
	const RATE_LIMIT_MAX = 30;

	/**
	 * Time window in seconds for rate limiting.
	 *
	 * @var int
	 */
	const RATE_LIMIT_WINDOW = 60;

	public function __construct() {
		// Hook AJAX handlers for logged in and guest users
		add_action( 'wp_ajax_hamseda_global_search', array( $this, 'handle_search' ) );
		add_action( 'wp_ajax_nopriv_hamseda_global_search', array( $this, 'handle_search' ) );
	}

	/**
	 * Handle AJAX search request.
	 */
	/**
	 * Check if the current visitor has exceeded the rate limit.
	 *
	 * Uses a Transient keyed by a hash of the visitor's IP address.
	 * The sliding window is fixed: it starts on the first request and resets
	 * only after the full window duration has elapsed.
	 *
	 * @return bool True if the request is allowed, false if rate-limited.
	 */
	private function check_rate_limit() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
		$key = 'hamseda_rl_' . md5( $ip );

		$data = get_transient( $key );
		$now  = time();

		if ( false === $data || $data['reset_at'] <= $now ) {
			// Start a fresh window.
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
			// Limit exceeded — do NOT update the transient.
			return false;
		}

		// Increment counter while preserving the original window expiry.
		$data['count']++;
		$remaining_ttl = max( 1, $data['reset_at'] - $now );
		set_transient( $key, $data, $remaining_ttl + 10 );
		return true;
	}

	/**
	 * Handle AJAX search request.
	 */
	public function handle_search() {
		// 1. Verify Security Nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'hamseda_search_nonce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Forbidden access.', 'hamseda-ajax-search' ) ),
				403
			);
		}

		// 2. Check Rate Limit
		if ( ! $this->check_rate_limit() ) {
			wp_send_json_error(
				array( 'message' => __( 'Too many requests. Please wait a moment and try again.', 'hamseda-ajax-search' ) ),
				429
			);
		}

		// 3. Sanitize and retrieve the search term
		$search_term = isset( $_POST['term'] ) ? sanitize_text_field( $_POST['term'] ) : '';

		if ( empty( $search_term ) ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		// 3. Execute the Fuzzy Search WP_Query
		$query = hamseda_search()->query->execute( $search_term );
		
		// Execute category search
		$categories = hamseda_search()->query->search_product_categories( $search_term );

		$categories_results = array();
		$posts_results = array();

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

		// 4. Format the output JSON
		if ( $query->have_posts() ) {
			$options = get_option( 'hamseda_search_settings', array() );
			$custom_labels = isset( $options['custom_labels'] ) ? $options['custom_labels'] : array();

			while ( $query->have_posts() ) {
				$query->the_post();

				$post_type = get_post_type();
				$badge_color = '';

				switch ( $post_type ) {
					case 'esanj':
						$badge_color = '#FFB3C1';
						break;
					case 'post':
						$badge_color = '#7BA4F5';
						break;
					case 'page':
						$badge_color = '#3A3A4A';
						break;
					case 'product':
						$badge_color = '#FCE16D';
						break;
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
				if ( 'product' === $post_type && hamseda_search()->is_woocommerce_active() ) {
					$item_data['regular_price'] = get_post_meta( get_the_ID(), '_regular_price', true );
					$item_data['sale_price']    = get_post_meta( get_the_ID(), '_sale_price', true );
					$item_data['stock_status']  = get_post_meta( get_the_ID(), '_stock_status', true );
				}

				$posts_results[] = $item_data;
			}
			wp_reset_postdata();
		}

		// 5. Send JSON Response
		wp_send_json_success( array( 
			'categories' => $categories_results,
			'posts'      => $posts_results 
		) );
	}
}
