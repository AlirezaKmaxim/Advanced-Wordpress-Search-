<?php
/**
 * Class Alireza_Asset_Manager
 * Handles registration and enqueuing of plugin styles and scripts.
 *
 * @package Alireza_Ajax_Search
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alireza_Asset_Manager {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register and enqueue plugin assets.
	 */
	public function register_assets() {
		// Register CSS
		wp_register_style(
			'alireza-search-css',
			ALIREZA_SEARCH_URL . 'assets/css/alireza-search.css',
			array(),
			ALIREZA_SEARCH_VERSION
		);

		// Inject Dynamic CSS Variables from Admin Color Settings
		$this->inject_custom_styles();

		// Register JS (use .min.js in production if available, with SCRIPT_DEBUG fallback)
		$suffix  = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$js_file = file_exists( ALIREZA_SEARCH_PATH . "assets/js/alireza-search{$suffix}.js" )
			? "assets/js/alireza-search{$suffix}.js"
			: 'assets/js/alireza-search.js';

		wp_register_script(
			'alireza-search-js',
			ALIREZA_SEARCH_URL . $js_file,
			array(),
			ALIREZA_SEARCH_VERSION,
			true // Load in footer
		);

		$settings_payload = array(
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'alireza_search_nonce' ),
			'search_url' => home_url( '/' ),
		);

		// Localize script with AJAX URL and Nonce
		wp_localize_script( 'alireza-search-js', 'alirezaSearchSettings', $settings_payload );
		wp_localize_script( 'alireza-search-js', 'hamsedaSearchSettings', $settings_payload );

		// Enqueue on frontend so search works seamlessly across headers, widgets & popups
		if ( ! is_admin() ) {
			$this->enqueue_assets();
		}
	}

	/**
	 * Inject dynamic CSS custom properties from admin settings into the frontend stylesheet.
	 */
	public function inject_custom_styles() {
		$options = alireza_search()->get_settings();

		$colors = isset( $options['colors'] ) && is_array( $options['colors'] ) ? $options['colors'] : array();

		$primary     = ! empty( $colors['primary'] ) ? sanitize_hex_color( $colors['primary'] ) : '#FA7993';
		$secondary   = ! empty( $colors['secondary'] ) ? sanitize_hex_color( $colors['secondary'] ) : '#FFB3C1';
		$input_bg    = ! empty( $colors['input_bg'] ) ? sanitize_hex_color( $colors['input_bg'] ) : '#FFFFFF';
		$border      = ! empty( $colors['border'] ) ? sanitize_hex_color( $colors['border'] ) : '#E2E8F0';
		$text        = ! empty( $colors['text'] ) ? sanitize_hex_color( $colors['text'] ) : '#1E293B';
		$placeholder = ! empty( $colors['placeholder'] ) ? sanitize_hex_color( $colors['placeholder'] ) : '#94A3B8';
		$price       = ! empty( $colors['price'] ) ? sanitize_hex_color( $colors['price'] ) : '#E11D48';

		$custom_css = "
		:root, #alireza-ajax-search-app, #hamseda-ajax-search-app {
			--alz-primary: {$primary};
			--alz-secondary: {$secondary};
			--alz-input-bg: {$input_bg};
			--alz-border: {$border};
			--alz-text: {$text};
			--alz-placeholder: {$placeholder};
			--alz-price: {$price};
			--hms-primary: {$primary};
			--hms-secondary: {$secondary};
			--hms-input-bg: {$input_bg};
			--hms-border: {$border};
			--hms-text: {$text};
			--hms-placeholder: {$placeholder};
			--hms-price: {$price};
		}
		";

		wp_add_inline_style( 'alireza-search-css', $custom_css );
	}

	/**
	 * Enqueue registered assets.
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'alireza-search-css' );
		wp_enqueue_script( 'alireza-search-js' );
	}
}
