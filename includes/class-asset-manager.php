<?php
/**
 * Class HamSeda_Asset_Manager
 * Handles registration and enqueuing of plugin styles and scripts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HamSeda_Asset_Manager {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register plugin assets.
	 */
	public function register_assets() {
		// Register CSS
		wp_register_style(
			'hamseda-search-css',
			HAMSEDA_SEARCH_URL . 'assets/css/hamseda-search.css',
			array(),
			HAMSEDA_SEARCH_VERSION
		);

		// Register JS
		wp_register_script(
			'hamseda-search-js',
			HAMSEDA_SEARCH_URL . 'assets/js/hamseda-search.js',
			array(),
			HAMSEDA_SEARCH_VERSION,
			true // Load in footer
		);

		// Localize script with AJAX URL and Nonce
		wp_localize_script(
			'hamseda-search-js',
			'hamsedaSearchSettings',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'hamseda_search_nonce' ),
				'search_url' => home_url( '/' ),
			)
		);

		// Check if the current post contains the shortcode and enqueue early
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'hamseda_ajax_search' ) ) {
			$this->enqueue_assets();
		}
	}

	/**
	 * Enqueue registered assets.
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'hamseda-search-css' );
		wp_enqueue_script( 'hamseda-search-js' );
	}
}
