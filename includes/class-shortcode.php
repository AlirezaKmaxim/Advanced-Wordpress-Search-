<?php
/**
 * Class Alireza_Shortcode
 * Handles the registration and rendering of the search shortcode.
 *
 * @package Alireza_Ajax_Search
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alireza_Shortcode {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'alireza_ajax_search', array( $this, 'render_shortcode' ) );
		add_shortcode( 'alireza_search', array( $this, 'render_shortcode' ) );
		add_shortcode( 'hamseda_ajax_search', array( $this, 'render_shortcode' ) );
		add_shortcode( 'adv_search', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render the search widget template via shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output of the search template.
	 */
	public function render_shortcode( $atts = array() ) {
		// Ensure styles and scripts are loaded
		alireza_search()->assets->enqueue_assets();

		ob_start();
		include ALIREZA_SEARCH_PATH . 'templates/search-template.php';
		return ob_get_clean();
	}
}
